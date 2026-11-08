#!/usr/bin/env python3
"""test_live_state.py — selftest af live-målingen uden netværk og uden et rigtigt token.

Kør: python3 tools/test_live_state.py

Stubben efterligner Novamiras MCP-flade: `initialize` kræver intet, men giver en
`Mcp-Session-Id` som de følgende kald skal sende med; `tools/list` annoncerer de tre
meta-værktøjer; `tools/call` svarer som plugin'ets `site-health` og `get-settings`.

Det der testes er dommen, ikke sitet: at `dynamic_rest: false` giver "fejlet", at
argumentnavnene læses af skemaet (både `{name, input}` og `{ability, arguments}`),
at et ældre plugin uden `ai_engine_mcp` ikke bliver til "bestået", og at tokenet
aldrig havner i rapporten.
"""

from __future__ import annotations

import io
import json
import os
import re
import sys
import tempfile
import threading
import unittest
from contextlib import redirect_stdout
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import live_state  # noqa: E402

TOKEN = "at-hemmelig-123456"
STATE: dict = {"health": None, "settings": None, "schema": ("name", "input"), "session": "sess-1",
              "tool_name": "execute-ability", "accept": ("name", "ability")}


def good_health(dynamic_rest=True, abilities=True, version="7.42.0", mcp=True) -> dict:
    payload = {"wordpress": "7.1.4", "php": "8.5.12", "autopilot_version": version}
    if mcp:
        payload["ai_engine_mcp"] = {"abilities": abilities, "dynamic_rest": dynamic_rest, "role": "admin"}
    return payload


def good_settings(profile="site_admin") -> dict:
    return {"AI content": {"ai_profile": {"value": profile, "kind": "text"}}}


class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args):  # tavs
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
        payload = json.loads(self.rfile.read(length) or b"{}")
        if self.headers.get("Authorization") != f"Bearer {TOKEN}":
            self._json(401, {"code": "rest_forbidden"})
            return
        method = payload.get("method")
        if method == "initialize":
            self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {
                "protocolVersion": "2025-06-18", "capabilities": {"tools": {}},
                "serverInfo": {"name": "novamira", "version": "1.12.14"}}},
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
            ability_key, input_key = STATE["schema"]
            properties: dict = {}
            if ability_key:
                properties[ability_key] = {"type": "string"}
            if input_key:
                properties[input_key] = {"type": "object"}
            self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {"tools": [
                {"name": "discover-abilities", "annotations": {"readOnlyHint": True}},
                {"name": "get-ability-info", "annotations": {"readOnlyHint": True}},
                {"name": STATE["tool_name"], "inputSchema": {"type": "object", "properties": properties}},
            ]}})
            return
        if method == "tools/call":
            arguments = (payload.get("params") or {}).get("arguments") or {}
            ability = next((arguments[key] for key in STATE["accept"] if arguments.get(key)), "")
            if ability == live_state.HEALTH_ABILITY and STATE["health"] is not None:
                body = STATE["health"]
            elif ability == live_state.SETTINGS_ABILITY and STATE["settings"] is not None:
                body = STATE["settings"]
            else:
                self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {
                    "isError": True, "content": [{"type": "text", "text": f"unknown ability {ability}"}]}})
                return
            self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {
                "content": [{"type": "text", "text": json.dumps(body)}], "isError": False}})
            return
        self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "error": {"code": -32601}})


class LiveStateTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        cls.base = f"http://127.0.0.1:{cls.server.server_address[1]}/mcp"
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def setUp(self):
        self._resource = live_state.RESOURCE
        live_state.RESOURCE = self.base
        self.addCleanup(lambda: setattr(live_state, "RESOURCE", self._resource))
        # Versionen følger repoet, så testen ikke skal rettes hver gang plugin'et bumpes.
        STATE["health"] = good_health(version=live_state.repo_version() or "7.42.0")
        STATE["settings"] = good_settings()
        STATE["schema"] = ("name", "input")
        STATE["tool_name"] = "execute-ability"
        STATE["accept"] = ("name", "ability")
        self.dir = tempfile.TemporaryDirectory()
        self.addCleanup(self.dir.cleanup)
        self.out = Path(self.dir.name)

    # -- dommen ---------------------------------------------------------

    def test_01_bestaar_naar_dynamic_rest_og_abilities_er_taendt(self):
        measured = live_state.measure(TOKEN)
        checks = live_state.evaluate(measured, expect_profile="site_admin",
                                     expect_version=live_state.repo_version() or "7.42.0")
        self.assertEqual(live_state.verdict(checks), "bestået", checks)
        self.assertEqual(live_state.failed(checks), 0)
        self.assertEqual(measured["ai_profile"], "site_admin")
        self.assertEqual(measured["tools"], 3)
        self.assertTrue(measured["session"], "session-headeren skal genbruges")

    def test_02_dynamic_rest_false_er_fejl(self):
        STATE["health"] = good_health(dynamic_rest=False)
        checks = live_state.evaluate(live_state.measure(TOKEN), expect_profile="site_admin")
        self.assertEqual(live_state.verdict(checks), "fejlet")
        row = next(check for check in checks if check["navn"] == "AI Engine Dynamic REST")
        self.assertEqual(row["status"], "fejl")
        self.assertEqual(row["maalt"], "false")

    def test_03_aeldre_plugin_uden_ai_engine_mcp_er_ikke_bestaet(self):
        STATE["health"] = good_health(mcp=False)
        checks = live_state.evaluate(live_state.measure(TOKEN))
        self.assertIn(live_state.verdict(checks), ("fejlet", "delvist målt"))
        self.assertNotEqual(live_state.verdict(checks), "bestået")

    def test_04_forkert_profil_naar_den_er_kraevet(self):
        STATE["settings"] = good_settings("assistant")
        checks = live_state.evaluate(live_state.measure(TOKEN), expect_profile="site_admin")
        row = next(check for check in checks if check["navn"] == "AI-profil")
        self.assertEqual(row["status"], "fejl")

    def test_05_profil_uden_krav_er_kun_oplysning(self):
        STATE["settings"] = good_settings("wp_seo_expert")
        checks = live_state.evaluate(live_state.measure(TOKEN))
        row = next(check for check in checks if check["navn"] == "AI-profil")
        self.assertEqual(row["status"], "ok")
        self.assertEqual(row["maalt"], "wp_seo_expert")

    def test_06_version_forskel_er_advarsel_ikke_fejl(self):
        STATE["health"] = good_health(version="7.40.1")
        checks = live_state.evaluate(live_state.measure(TOKEN), expect_version="7.42.0")
        row = next(check for check in checks if check["navn"] == "Plugin-version på sitet")
        self.assertEqual(row["status"], "advarsel")
        self.assertEqual(live_state.verdict(checks), "bestået med advarsel")
        self.assertEqual(live_state.failed(checks), 0, "en bagudværende version må ikke give rødt job")

    # -- skemaet --------------------------------------------------------

    def test_07_argumentnavne_laeses_af_skemaet(self):
        STATE["schema"] = ("ability", "arguments")
        measured = live_state.measure(TOKEN)
        self.assertEqual(measured["argument_navne"], {"ability": "ability", "input": "arguments"})
        self.assertEqual(measured["ai_profile"], "site_admin", "kaldet skal virke med de rigtige navne")

    def test_08_intet_skema_bruger_standardnavnet(self):
        # Uden skema kan navnet ikke læses: så prøves den foretrukne form først.
        STATE["schema"] = ("", "")
        measured = live_state.measure(TOKEN)
        self.assertEqual(measured["argument_navne"]["ability"], "name")
        self.assertEqual(measured["ai_profile"], "site_admin")

    def test_08b_uden_skema_proeves_den_anden_form(self):
        # Afviser fladen `name`, forsøges `ability` — og rapporten skriver hvilken der virkede.
        STATE["schema"] = ("", "")
        STATE["accept"] = ("ability",)
        measured = live_state.measure(TOKEN)
        self.assertEqual(measured["argument_navne"]["ability"], "ability")
        self.assertEqual(measured["ai_profile"], "site_admin")

    def test_09_navne_praefiks_paa_vaerktoejet(self):
        # Fladen kan hedde `mcp-adapter/execute-ability`; suffikset er det der matches.
        STATE["tool_name"] = "mcp-adapter/execute-ability"
        measured = live_state.measure(TOKEN)
        self.assertNotIn("tools/list", measured["errors"])
        self.assertEqual(measured["ai_profile"], "site_admin")

    # -- rapporten ------------------------------------------------------

    def test_10_rapporten_skrives_og_naevner_tokenet_ikke(self):
        markdown, payload, checks = live_state.report(TOKEN, out_dir=self.out, when="2026-11-07T03:00:00Z")
        self.assertTrue(markdown.exists() and payload.exists())
        self.assertIn("live-tilstand-2026-11-07", markdown.name)
        blob = markdown.read_text(encoding="utf-8") + payload.read_text(encoding="utf-8")
        self.assertNotIn(TOKEN, blob)
        self.assertIn("site_admin", blob)
        self.assertIn("Dynamic REST", blob)
        self.assertEqual(json.loads(payload.read_text(encoding="utf-8"))["verdict"], "bestået")

    def test_10b_versionen_laeses_fra_repoet(self):
        # Rapporten sammenligner med plugin-headeren, ikke med et hardkodet tal.
        header = live_state.PLUGIN_FILE.read_text(encoding="utf-8", errors="replace")[:4000]
        match = re.search(r"Version:\s*([0-9][0-9A-Za-z.\-]*)", header)
        self.assertIsNotNone(match, "plugin-headeren skal have en Version-linje")
        self.assertEqual(live_state.repo_version(), match.group(1))

    def test_11_rapporten_fortæller_hvad_der_gik_galt(self):
        STATE["health"] = None  # værktøjet svarer isError
        markdown, payload, checks = live_state.report(TOKEN, out_dir=self.out, when="2026-11-07T03:00:00Z")
        text = markdown.read_text(encoding="utf-8")
        self.assertIn("Fejl under målingen", text)
        self.assertIn("unknown ability", text)
        self.assertNotEqual(json.loads(payload.read_text(encoding="utf-8"))["verdict"], "bestået")

    def test_12_uden_credential_skrives_intet(self):
        before_argv, before_token = sys.argv, live_state.local_token
        sys.argv = ["live_state.py", "--out", str(self.out)]
        live_state.local_token = lambda: ""
        buffer = io.StringIO()
        try:
            with redirect_stdout(buffer):
                code = live_state.main()
        finally:
            sys.argv = before_argv
            live_state.local_token = before_token
        self.assertEqual(code, 2)
        self.assertEqual(list(self.out.glob("live-tilstand-*")), [])
        self.assertIn("Ingen credential", buffer.getvalue())


if __name__ == "__main__":
    unittest.main(verbosity=2)
