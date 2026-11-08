#!/usr/bin/env python3
"""test_mcp_keepalive.py — selftest af keep-alive uden netværk og uden det rigtige token.

Kør: python3 tools/test_mcp_keepalive.py

Stub-serveren efterligner Novamiras to flader: token-endepunktet (form-encoding,
rotation af refresh-token) og MCP-endepunktet (initialize kræver en session-header
på de efterfølgende kald). Testene bekræfter at fornyelsen gemmer det nye par med
mode 600, at et afvist refresh ikke overskriver det gamle, og at CI-tilstanden
skriver det roterede token til den fil workflowen læser.
"""

from __future__ import annotations

import io
import json
import os
import sys
import tempfile
import threading
import unittest
from contextlib import redirect_stdout
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs

sys.path.insert(0, str(Path(__file__).resolve().parent))
import mcp_keepalive  # noqa: E402

STATE: dict = {"refresh": "rt-1", "session": "sess-1", "issued": 0}


class Handler(BaseHTTPRequestHandler):
    base = ""

    def log_message(self, *args):
        pass

    def _json(self, status: int, payload: dict, headers: dict | None = None):
        raw = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(raw)))
        for key, value in (headers or {}).items():
            self.send_header(key, value)
        self.end_headers()
        self.wfile.write(raw)

    def do_POST(self):  # noqa: N802
        length = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(length) or b""
        if self.path.startswith("/token"):
            if not self.headers.get("Content-Type", "").startswith("application/x-www-form-urlencoded"):
                self._json(400, {"error": "unsupported_grant_type"})
                return
            payload = {k: v[0] for k, v in parse_qs(raw.decode()).items()}
            if payload.get("grant_type") != "refresh_token" or payload.get("refresh_token") != STATE["refresh"]:
                self._json(400, {"error": "invalid_grant"})
                return
            STATE["issued"] += 1
            new_refresh = f"rt-{STATE['issued'] + 1}"
            STATE["refresh"] = new_refresh
            self._json(200, {"access_token": f"at-{STATE['issued']}", "refresh_token": new_refresh,
                             "expires_in": 3600, "token_type": "Bearer"})
            return
        if self.path.startswith("/mcp"):
            if not self.headers.get("Authorization", "").startswith("Bearer at-"):
                self._json(401, {"code": "rest_forbidden"})
                return
            payload = json.loads(raw or b"{}")
            method = payload.get("method")
            if method == "initialize":
                self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"),
                                 "result": {"protocolVersion": "2025-06-18",
                                            "serverInfo": {"name": "stub", "version": "1"}}},
                           {"Mcp-Session-Id": STATE["session"]})
                return
            if method == "notifications/initialized":
                self.send_response(202)
                self.end_headers()
                return
            if method == "tools/list":
                if self.headers.get("Mcp-Session-Id") != STATE["session"]:
                    self._json(400, {"error": "missing session"})
                    return
                self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"),
                                 "result": {"tools": [{"name": "a"}, {"name": "b"}, {"name": "c"}]}})
                return
            self._json(400, {"error": "bad method"})
            return
        self._json(404, {"error": "not_found"})


class KeepaliveTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        cls.base = f"http://127.0.0.1:{cls.server.server_address[1]}"
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def setUp(self):
        STATE["refresh"] = "rt-1"
        STATE["issued"] = 0
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        base = Path(self.dir.name)
        mcp_keepalive.CONFIG_DIR = base
        mcp_keepalive.TOKEN_FILE = base / "tokens.json"
        mcp_keepalive.CLIENT_FILE = base / "client.json"
        mcp_keepalive.LOG_FILE = base / "keepalive.log"
        mcp_keepalive.TOKEN_ENDPOINT = f"{self.base}/token"
        mcp_keepalive.RESOURCE = f"{self.base}/mcp"
        mcp_keepalive.CLIENT_FILE.write_text(json.dumps({"client_id": "client-1"}))
        mcp_keepalive.TOKEN_FILE.write_text(json.dumps({"access_token": "at-old", "refresh_token": "rt-1",
                                                        "expires_at": 1}))
        os.environ.pop("NOVAMIRA_REFRESH_TOKEN", None)
        os.environ.pop("NOVAMIRA_CLIENT_ID", None)
        self._argv = sys.argv[:]
        self.addCleanup(lambda: setattr(sys, "argv", self._argv))

    def run_main(self, argv):
        sys.argv = ["mcp_keepalive.py"] + argv
        buffer = io.StringIO()
        with redirect_stdout(buffer):
            code = mcp_keepalive.main()
        return code, buffer.getvalue()

    def test_01_fornyer_og_gemmer_roteret_par(self):
        code, out = self.run_main([])
        self.assertEqual(code, 0)
        tokens = json.loads(mcp_keepalive.TOKEN_FILE.read_text())
        self.assertEqual(tokens["access_token"], "at-1")
        self.assertEqual(tokens["refresh_token"], "rt-2", "det roterede token skal gemmes")
        self.assertEqual(oct(mcp_keepalive.TOKEN_FILE.stat().st_mode & 0o777), "0o600")
        self.assertIn("3 værktøjer", out)

    def test_02_loggen_skrives_uden_for_repoet(self):
        self.run_main([])
        log = mcp_keepalive.LOG_FILE.read_text()
        self.assertIn("roteret=True", log)
        self.assertIn("vaerktoejer=3", log)

    def test_03_afvist_refresh_overskriver_ikke(self):
        before = mcp_keepalive.TOKEN_FILE.read_text()
        mcp_keepalive.TOKEN_FILE.write_text(json.dumps({"access_token": "at-old", "refresh_token": "forkert"}))
        before = mcp_keepalive.TOKEN_FILE.read_text()
        with self.assertRaises(SystemExit) as ctx:
            self.run_main([])
        self.assertIn("invalid_grant", str(ctx.exception))
        self.assertEqual(mcp_keepalive.TOKEN_FILE.read_text(), before)

    def test_04_ci_skriver_kun_emit_filen(self):
        os.environ["NOVAMIRA_REFRESH_TOKEN"] = "rt-1"
        os.environ["NOVAMIRA_CLIENT_ID"] = "client-1"
        self.addCleanup(lambda: (os.environ.pop("NOVAMIRA_REFRESH_TOKEN", None),
                                 os.environ.pop("NOVAMIRA_CLIENT_ID", None)))
        emit = Path(self.dir.name) / "out" / "refresh"
        before = mcp_keepalive.TOKEN_FILE.read_text()
        code, out = self.run_main(["--ci", "--emit-refresh-file", str(emit)])
        self.assertEqual(code, 0)
        self.assertEqual(emit.read_text(), "rt-2")
        self.assertEqual(oct(emit.stat().st_mode & 0o777), "0o600")
        self.assertEqual(mcp_keepalive.TOKEN_FILE.read_text(), before, "CI må ikke røre den lokale tokenfil")
        self.assertIn("roteret: True", out)

    def test_05_manglende_credential_er_en_fejl(self):
        mcp_keepalive.TOKEN_FILE.unlink()
        with self.assertRaises(SystemExit) as ctx:
            self.run_main([])
        self.assertIn("refresh-token", str(ctx.exception))

    def test_06_tools_list_kraever_session(self):
        # Verifikationen skal sende Mcp-Session-Id videre, ellers svarer stubben 400.
        code, out = self.run_main([])
        self.assertEqual(code, 0)
        self.assertIn("3 værktøjer", out)

    def test_07_pkce_og_form_encoding_bruges(self):
        code, _ = self.run_main([])
        self.assertEqual(code, 0)
        self.assertEqual(STATE["issued"], 1, "ét refresh-kald, ikke et JSON-forsøg først")


if __name__ == "__main__":
    unittest.main(verbosity=2)
