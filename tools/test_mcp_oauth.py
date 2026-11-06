#!/usr/bin/env python3
"""test_mcp_oauth.py — selftest af OAuth-forbindelsen mod en lokal authorization server.

Kør: python3 tools/test_mcp_oauth.py

Testene kører uden netværk og uden den rigtige ejer-godkendelse: de starter en lille
authorization server (metadata, DCR-registrering, token-udveksling, refresh) og bekræfter at
klienten registrerer sig, bygger et PKCE-link, bytter koden til tokens, fornyer dem og skriver
filerne med mode 600 uden for repoet.
"""

from __future__ import annotations

import json
import os
import sys
import tempfile
import threading
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, urlparse

sys.path.insert(0, str(Path(__file__).resolve().parent))
import mcp_oauth  # noqa: E402

STATE: dict = {"codes": set(), "refreshed": 0, "verifiers": {}, "types": []}


class Handler(BaseHTTPRequestHandler):
    base = ""

    def log_message(self, *args):
        pass

    def _json(self, status: int, payload: dict):
        raw = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_GET(self):  # noqa: N802
        if self.path.startswith("/.well-known/oauth-authorization-server"):
            self._json(200, {
                "issuer": self.base,
                "authorization_endpoint": f"{self.base}/oauth/authorize",
                "token_endpoint": f"{self.base}/oauth/token",
                "registration_endpoint": f"{self.base}/oauth/register",
                "response_types_supported": ["code"],
                "grant_types_supported": ["authorization_code", "refresh_token"],
                "code_challenge_methods_supported": ["S256"],
                "scopes_supported": ["mcp"],
            })
            return
        self._json(404, {"error": "not_found"})

    def do_POST(self):  # noqa: N802
        length = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(length) or b"{}"
        STATE["types"].append((self.path, self.headers.get("Content-Type", "")))
        try:
            payload = json.loads(raw)
        except ValueError:
            # Token-endepunkterne tager form-encoding; parse_qs giver lister.
            payload = {k: (v[0] if isinstance(v, list) and len(v) == 1 else v)
                       for k, v in parse_qs(raw.decode()).items()}
        if self.path == "/oauth/register":
            self._json(201, {"client_id": "test-client", "client_name": payload.get("client_name"),
                             "redirect_uris": payload.get("redirect_uris"),
                             "token_endpoint_auth_method": "none"})
            return
        if self.path == "/oauth/token":
            grant = payload.get("grant_type")
            if grant == "authorization_code":
                if payload.get("code") not in STATE["codes"] or not payload.get("code_verifier"):
                    self._json(400, {"error": "invalid_grant"})
                    return
                STATE["verifiers"]["used"] = payload["code_verifier"]
                STATE["codes"].discard(payload["code"])
                self._json(200, {"access_token": "at-1", "refresh_token": "rt-1",
                                 "expires_in": 3600, "token_type": "Bearer", "scope": "mcp"})
                return
            if grant == "refresh_token":
                if payload.get("refresh_token") != "rt-1":
                    self._json(400, {"error": "invalid_grant"})
                    return
                STATE["refreshed"] += 1
                self._json(200, {"access_token": "at-2", "refresh_token": "rt-2",
                                 "expires_in": 3600, "token_type": "Bearer", "scope": "mcp"})
                return
            self._json(400, {"error": "unsupported_grant_type"})
            return
        self._json(404, {"error": "not_found"})


class OAuthTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        cls.base = f"http://127.0.0.1:{cls.server.server_address[1]}"
        Handler.base = cls.base
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def setUp(self):
        STATE["codes"] = {"god-kode"}
        STATE["verifiers"] = {}
        STATE["refreshed"] = 0
        STATE["types"] = []
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        base = Path(self.dir.name)
        mcp_oauth.CONFIG_DIR = base
        mcp_oauth.CLIENT_FILE = base / "client.json"
        mcp_oauth.TOKEN_FILE = base / "tokens.json"
        mcp_oauth.METADATA_URL = f"{self.base}/.well-known/oauth-authorization-server"

    def start_client(self) -> str:
        import io
        from contextlib import redirect_stdout

        buffer = io.StringIO()
        with redirect_stdout(buffer):
            self.assertEqual(mcp_oauth.start(), 0)
        return buffer.getvalue()

    def test_01_start_registrerer_og_bygger_pkce_link(self):
        output = self.start_client()
        config = json.loads(mcp_oauth.CLIENT_FILE.read_text())
        self.assertEqual(config["client_id"], "test-client")
        self.assertTrue(config["code_verifier"])
        self.assertEqual(oct(mcp_oauth.CLIENT_FILE.stat().st_mode & 0o777), "0o600")
        link = next(line for line in output.splitlines() if "authorize?" in line)
        query = parse_qs(urlparse(link).query)
        self.assertEqual(query["code_challenge_method"], ["S256"])
        self.assertEqual(query["client_id"], ["test-client"])
        self.assertEqual(query["scope"], ["mcp"])
        self.assertNotIn(config["code_verifier"], link, "verifier må ikke stå i linket")

    def test_02_start_genbruger_klient(self):
        self.start_client()
        first = json.loads(mcp_oauth.CLIENT_FILE.read_text())["client_id"]
        second_out = self.start_client()
        self.assertIn("authorize?", second_out, "andet gennemløb skal ikke registrere igen")
        self.assertEqual(json.loads(mcp_oauth.CLIENT_FILE.read_text())["client_id"], first)

    def test_03_extract_code_fra_redirect(self):
        self.assertEqual(mcp_oauth.extract_code("http://127.0.0.1:8765/callback?code=abc123&state=x"), "abc123")
        self.assertEqual(mcp_oauth.extract_code("  abc123  "), "abc123")

    def test_04_finish_gemmer_tokens(self):
        self.start_client()
        self.assertEqual(mcp_oauth.finish("http://127.0.0.1:8765/callback?code=god-kode&state=irrelevant"), 0)
        tokens = json.loads(mcp_oauth.TOKEN_FILE.read_text())
        self.assertEqual(tokens["access_token"], "at-1")
        self.assertEqual(tokens["refresh_token"], "rt-1")
        self.assertGreater(tokens["expires_at"], 0)
        self.assertEqual(oct(mcp_oauth.TOKEN_FILE.stat().st_mode & 0o777), "0o600")
        self.assertNotIn("code_verifier", json.loads(mcp_oauth.CLIENT_FILE.read_text()))

    def test_05_finish_afviser_forkert_kode(self):
        self.start_client()
        import io
        from contextlib import redirect_stdout

        buffer = io.StringIO()
        with redirect_stdout(buffer):
            code = mcp_oauth.finish("en-kode-der-ikke-findes")
        self.assertEqual(code, 1)
        self.assertIn("invalid_grant", buffer.getvalue())
        self.assertFalse(mcp_oauth.TOKEN_FILE.exists())

    def test_06_finish_kraever_start(self):
        import io
        from contextlib import redirect_stdout

        buffer = io.StringIO()
        with redirect_stdout(buffer):
            self.assertEqual(mcp_oauth.finish("god-kode"), 1)
        self.assertIn("--start", buffer.getvalue())

    def test_07_access_token_fornyer_udloebet_token(self):
        self.start_client()
        mcp_oauth.finish("god-kode")
        self.assertEqual(mcp_oauth.access_token(), "at-1")
        tokens = json.loads(mcp_oauth.TOKEN_FILE.read_text())
        tokens["expires_at"] = 1
        mcp_oauth.TOKEN_FILE.write_text(json.dumps(tokens))
        self.assertEqual(mcp_oauth.access_token(), "at-2")
        self.assertEqual(STATE["refreshed"], 1)

    def test_08_access_token_uden_filer(self):
        self.assertIsNone(mcp_oauth.access_token())
        self.assertIsNone(mcp_oauth.refresh())

    def test_09_status_rapporterer_tilstand(self):
        self.assertEqual(mcp_oauth.status(), 1, "uden klient skal status sige til")
        self.start_client()
        self.assertEqual(mcp_oauth.status(), 1, "klient uden token er ikke klar")
        mcp_oauth.finish("god-kode")
        self.assertEqual(mcp_oauth.status(), 0)

    def test_10_pkce_verifier_bruges_i_udvekslingen(self):
        self.start_client()
        verifier = json.loads(mcp_oauth.CLIENT_FILE.read_text())["code_verifier"]
        mcp_oauth.finish("god-kode")
        self.assertEqual(STATE["verifiers"]["used"], verifier)


    def test_11_authorize_link_haandterer_endpunkt_med_query(self):
        # Novamira-sidens endepunkt er en wp-admin-side og har allerede `?page=...`.
        self.assertEqual(
            mcp_oauth.authorize_url("https://site/wp-admin/admin.php?page=novamira-oauth-authorize", "scope=full_access"),
            "https://site/wp-admin/admin.php?page=novamira-oauth-authorize&scope=full_access",
        )
        self.assertEqual(
            mcp_oauth.authorize_url("https://site/oauth/authorize", "scope=mcp"),
            "https://site/oauth/authorize?scope=mcp",
        )
        self.assertNotIn("?", urlparse(mcp_oauth.authorize_url(
            "https://site/wp-admin/admin.php?page=x", "a=1")).query)


    def test_12_token_endepunktet_faar_form_encoding(self):
        # RFC 6749 §4.1.3: token-kald er form-encoded. JSON giver unsupported_grant_type
        # på Novamira, som er det site'et kører ved siden af AI Engine.
        self.start_client()
        self.assertEqual(mcp_oauth.finish("god-kode"), 0)
        token_types = [ct for path, ct in STATE["types"] if path == "/oauth/token"]
        self.assertTrue(token_types)
        self.assertTrue(all(ct.startswith("application/x-www-form-urlencoded") for ct in token_types),
                        token_types)
        register_types = [ct for path, ct in STATE["types"] if path == "/oauth/register"]
        self.assertTrue(register_types and register_types[0].startswith("application/json"),
                        "DCR-registrering er JSON")
        mcp_oauth.access_token(force_refresh=True)
        self.assertEqual(STATE["refreshed"], 1)


if __name__ == "__main__":
    unittest.main(verbosity=2)
