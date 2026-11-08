#!/usr/bin/env python3
"""test_live_channels.py — selftest af P1.5-målingen uden netværk og uden et rigtigt token.

Kør: python3 tools/test_live_channels.py

Stubben efterligner Novamiras MCP-flade og svarer som plugin'ets `channel-health`-ability.
Det der testes er dommen: at et virkende token uden landede tal er rødt, at en kanal uden
token er blokeret (ikke rødt), at Telegram er `no_metric` og aldrig en manglende måling, at
et ældre plugin uden `analytics` ikke bliver til "bestået", og at tokenet ikke lækker.
"""

from __future__ import annotations

import io
import json
import sys
import threading
import unittest
from contextlib import redirect_stdout
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import live_channels  # noqa: E402

TOKEN = "at-hemmelig-987654"
STATE: dict = {"payload": None, "session": "sess-9", "tool_name": "execute-ability"}


def analytics(state: str, total: float = 0.0, last: str | None = None) -> dict:
    return {"state": state, "types": ["impressions"], "counts": {"impressions": total},
            "total": total, "rows": 1 if state == "measured" else 0, "last_at": last, "note": ""}


def good_payload(**overrides) -> dict:
    channels = {
        "pinterest": {"id": "pinterest", "label": "Pinterest", "state": "ready", "next": "Ready."},
        "instagram": {"id": "instagram", "label": "Instagram", "state": "missing_token", "next": "Paste a token."},
        "bluesky": {"id": "bluesky", "label": "Bluesky", "state": "missing_token", "next": "Paste a token."},
        "mastodon": {"id": "mastodon", "label": "Mastodon", "state": "missing_token", "next": "Paste a token."},
        "telegram": {"id": "telegram", "label": "Telegram", "state": "missing_token", "next": "Paste a token."},
        "facebook": {"id": "facebook", "label": "Facebook", "state": "missing_token", "next": "Paste a token."},
    }
    rows = {
        "pinterest": analytics("measured", 128.0, "2026-11-08 10:00:00"),
        "instagram": analytics("empty"), "bluesky": analytics("empty"),
        "mastodon": analytics("empty"),
        "telegram": {"state": "no_metric", "types": [], "counts": {}, "total": 0.0, "rows": 0,
                     "last_at": None, "note": ""},
        "facebook": analytics("empty"),
    }
    payload = {"window_days": 90, "channels": channels, "analytics": rows,
               "analytics_summary": {"measured": 1, "empty": 4, "no_metric": 1, "total": 6, "rows": 1},
               "summary": {"ready": 1, "recoverable": 0, "refused": 0, "needs_scope": 0,
                           "missing": 5, "broken": 0, "total": 6}}
    payload.update(overrides)
    return payload


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
            self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {"tools": [
                {"name": STATE["tool_name"],
                 "inputSchema": {"type": "object", "properties": {"name": {"type": "string"},
                                                                 "input": {"type": "object"}}}},
            ]}})
            return
        if method == "tools/call":
            arguments = (payload.get("params") or {}).get("arguments") or {}
            STATE["last_days"] = (arguments.get("input") or {}).get("days")
            if arguments.get("name") != live_channels.CHANNELS_ABILITY or STATE["payload"] is None:
                self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {
                    "isError": True, "content": [{"type": "text", "text": "unknown ability"}]}})
                return
            self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {
                "content": [{"type": "text", "text": json.dumps(STATE["payload"])}], "isError": False}})
            return
        self._json(200, {"jsonrpc": "2.0", "id": payload.get("id"), "error": {"code": -32601}})


class LiveChannelsTest(unittest.TestCase):
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
        self._resource = live_channels.live_state.RESOURCE
        live_channels.live_state.RESOURCE = self.base
        self.addCleanup(lambda: setattr(live_channels.live_state, "RESOURCE", self._resource))
        STATE["payload"] = good_payload()
        STATE["tool_name"] = "execute-ability"

    def run_main(self, *argv: str) -> tuple[int, str]:
        before_argv, before_token = sys.argv, live_channels.live_state.local_token
        sys.argv = ["live_channels.py", *argv]
        live_channels.live_state.local_token = lambda: TOKEN
        buffer = io.StringIO()
        try:
            with redirect_stdout(buffer):
                code = live_channels.main()
        finally:
            sys.argv, live_channels.live_state.local_token = before_argv, before_token
        return code, buffer.getvalue()

    # -- dommen ---------------------------------------------------------

    def test_01_et_landet_tal_er_bevis(self):
        rows = live_channels.evaluate(live_channels.measure(TOKEN))
        # Pinterest har landet et tal; de fem andre har intet token, så dommen er blokeret —
        # ikke rød. Blokeret betyder "kan ikke måles endnu", og det er ikke det samme som fejl.
        self.assertEqual(live_channels.verdict(rows), "blokeret", rows)
        self.assertFalse([row for row in rows if row["status"] == "fejl"])
        pinterest = next(row for row in rows if row["kanal"] == "Pinterest")
        self.assertEqual(pinterest["status"], "ok")
        self.assertEqual(pinterest["total"], 128.0)
        self.assertEqual(pinterest["sidste"], "2026-11-08 10:00:00")

    def test_02_virkende_token_uden_tal_er_roedt(self):
        STATE["payload"] = good_payload(analytics={
            **good_payload()["analytics"],
            "pinterest": analytics("empty"),
        })
        rows = live_channels.evaluate(live_channels.measure(TOKEN))
        self.assertEqual(live_channels.verdict(rows), "fejlet", rows)
        pinterest = next(row for row in rows if row["kanal"] == "Pinterest")
        self.assertEqual(pinterest["status"], "fejl")
        self.assertIn("intet er landet", pinterest["detalje"])

    def test_03_kanal_uden_token_er_blokeret_ikke_fejl(self):
        rows = live_channels.evaluate(live_channels.measure(TOKEN))
        instagram = next(row for row in rows if row["kanal"] == "Instagram")
        self.assertEqual(instagram["status"], "blokeret")
        self.assertIn("Paste a token", instagram["detalje"])

    def test_04_telegram_uden_metric_er_ikke_en_manglende_maaling(self):
        rows = live_channels.evaluate(live_channels.measure(TOKEN))
        telegram = next(row for row in rows if row["kanal"] == "Telegram")
        self.assertEqual((telegram["kanal"], telegram["landet"], telegram["status"]),
                         ("Telegram", "no_metric", "ok"))

    def test_05_aeldre_plugin_uden_analytics_er_blokeret(self):
        # Live kørte 7.64.6 da abilityen blev bygget: den findes ikke før Update-klikket,
        # og det må ikke melde rødt — der er intet at rette i koden, kun et plugin at opdatere.
        STATE["payload"] = {"channels": {}, "summary": {}}
        rows = live_channels.evaluate(live_channels.measure(TOKEN))
        self.assertEqual(live_channels.verdict(rows), "blokeret")
        self.assertIn("ældre end abilityen", rows[0]["detalje"])

    def test_06_ukendt_ability_er_blokeret_ikke_fejl(self):
        STATE["payload"] = None  # værktøjet svarer isError
        measured = live_channels.measure(TOKEN)
        rows = live_channels.evaluate(measured)
        self.assertEqual(live_channels.verdict(rows), "blokeret")
        self.assertIn("unknown ability", rows[0]["detalje"])

    def test_06b_manglende_forbindelse_er_fejl(self):
        rows = live_channels.evaluate({"errors": {"initialize": "HTTP 401: rest_forbidden"}})
        self.assertEqual(live_channels.verdict(rows), "fejlet")
        self.assertEqual(rows[0]["kanal"], "MCP-forbindelse")

    def test_07_scope_afslag_er_blokeret_med_scopet_navnt(self):
        payload = good_payload()
        payload["channels"]["pinterest"] = {"id": "pinterest", "label": "Pinterest",
                                           "state": "needs_scope",
                                           "next": "Missing scope: boards:read."}
        payload["analytics"]["pinterest"] = analytics("empty")
        STATE["payload"] = payload
        rows = live_channels.evaluate(live_channels.measure(TOKEN))
        pinterest = next(row for row in rows if row["kanal"] == "Pinterest")
        self.assertEqual(pinterest["status"], "blokeret")
        self.assertIn("boards:read", pinterest["detalje"])
        self.assertEqual(live_channels.verdict(rows), "blokeret")

    # -- kommandolinjen -------------------------------------------------

    def test_08_exitkoder_fejl_og_blokeret(self):
        code, text = self.run_main()
        self.assertEqual(code, 3, "en manglende token er blokeret, ikke rødt")
        self.assertIn("Dom: blokeret", text)

        STATE["payload"] = good_payload(analytics={
            **good_payload()["analytics"], "pinterest": analytics("empty")})
        code, text = self.run_main()
        self.assertEqual(code, 1)
        self.assertIn("Dom: fejlet", text)

        STATE["payload"] = good_payload()
        for channel in STATE["payload"]["channels"]:
            STATE["payload"]["channels"][channel]["state"] = "ready"
        for channel in STATE["payload"]["analytics"]:
            if STATE["payload"]["analytics"][channel]["state"] == "empty":
                STATE["payload"]["analytics"][channel] = analytics("measured", 5.0, "2026-11-08 10:00:00")
        code, text = self.run_main()
        self.assertEqual(code, 0)
        self.assertIn("Dom: bestået", text)

    def test_09_tokenet_naevnes_aldrig(self):
        _, text = self.run_main()
        self.assertNotIn(TOKEN, text)
        self.assertIn("Pinterest", text)

    def test_10_uden_credential_maa_intet_vaere_maalt(self):
        before = live_channels.live_state.local_token
        live_channels.live_state.local_token = lambda: ""
        buffer = io.StringIO()
        try:
            with redirect_stdout(buffer):
                code = live_channels.main()
        finally:
            live_channels.live_state.local_token = before
        self.assertEqual(code, 2)
        self.assertIn("Ingen credential", buffer.getvalue())

    def test_11_vinduet_klemmes_og_laeses_fra_svaret(self):
        self.run_main("--days", "500")
        self.assertEqual(STATE["last_days"], 365, "et for stort vindue skal klemmes, ikke sendes")
        code, text = self.run_main("--days", "30")
        self.assertEqual(STATE["last_days"], 30)
        # Vinduet der vises er det plugin'et svarede med, ikke det vi bad om.
        self.assertIn("90 dages vindue", text)
        self.assertEqual(code, 3)


if __name__ == "__main__":
    unittest.main(verbosity=2)
