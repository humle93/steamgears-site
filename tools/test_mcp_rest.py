#!/usr/bin/env python3
"""test_mcp_rest.py — selftest af mcp_rest.py mod en lokal efterligning af MCP-adapteren.

Kør: python3 tools/test_mcp_rest.py

Testene kører uden netværk og uden credential: de starter en lille HTTP-server der svarer
som `/wp-json/mcp/mcp-adapter-default-server` (JSON-RPC over POST, Basic-auth, SSE-frames),
og bekræfter at optællingen klassificerer værktøjerne rigtigt. Det er værnene der gør at
`--abilities` kan køres mod det rigtige site uden at gætte.
"""

from __future__ import annotations

import base64
import csv
import json
import sys
import tempfile
import threading
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import mcp_rest  # noqa: E402

USER, SECRET = "agent", "abcd efgh ijkl mnop qrst uvwx"
BASIC = "Basic " + base64.b64encode(f"{USER}:{SECRET}".encode()).decode()
TOOLS = [
    {
        "name": "mcp-adapter/discover-abilities",
        "description": "Discover abilities exposed to MCP.",
        "inputSchema": {"type": "object", "properties": {"search": {"type": "string"}}},
        "annotations": {"readOnlyHint": True},
    },
    {
        "name": "mcp-adapter/get-ability-info",
        "description": "Get the full definition of one ability.",
        "inputSchema": {"type": "object", "properties": {"name": {"type": "string"}}},
        "annotations": {"readOnlyHint": True},
    },
    {
        # Ingen skrive-hint i navnet — kun annotationen og ordet "execute" afslører det.
        "name": "mcp-adapter/execute-ability",
        "description": "Execute a registered ability with input.",
        "inputSchema": {"type": "object", "properties": {"name": {"type": "string"}, "input": {"type": "object"}}},
        "annotations": {"readOnlyHint": False},
    },
    {
        "name": "woocommerce/products-query",
        "description": "Query products.",
        "inputSchema": {"type": "object", "properties": {"search": {"type": "string"}, "per_page": {"type": "integer"}}},
        "annotations": {"readOnlyHint": True, "idempotentHint": True},
    },
    {
        "name": "woocommerce/product-update",
        "description": "Update an existing product.",
        "inputSchema": {"type": "object", "properties": {"id": {"type": "integer"}, "fields": {"type": "object"}}},
        "annotations": {"readOnlyHint": False, "destructiveHint": False},
    },
]


class Handler(BaseHTTPRequestHandler):
    sse = False

    def log_message(self, *args):  # tavs
        pass

    def _send(self, status: int, body, extra: dict | None = None, sse: bool = False):
        raw = body if isinstance(body, str) else json.dumps(body)
        if sse:
            raw = f"event: message\ndata: {raw}\n\n"
        self.send_response(status)
        self.send_header("Content-Type", "text/event-stream" if sse else "application/json")
        for key, value in (extra or {}).items():
            self.send_header(key, value)
        self.end_headers()
        self.wfile.write(raw.encode())

    def do_POST(self):  # noqa: N802
        length = int(self.headers.get("Content-Length") or 0)
        payload = json.loads(self.rfile.read(length) or b"{}")
        expected = "Basic " + base64.b64encode(f"{USER}:{SECRET}".encode()).decode()
        if self.headers.get("Authorization") != expected:
            self._send(401, {"code": "rest_forbidden", "message": "Du har ikke tilladelse.", "data": {"status": 401}})
            return
        method = payload.get("method")
        if method == "initialize":
            self._send(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {
                "protocolVersion": mcp_rest.PROTOCOL,
                "capabilities": {"tools": {}},
                "serverInfo": {"name": "mcp-adapter", "version": "0.7.0"},
            }}, {"Mcp-Session-Id": "test-session"})
        elif method == "notifications/initialized":
            self._send(202, "")
        elif method == "tools/list":
            self._send(200, {"jsonrpc": "2.0", "id": payload.get("id"), "result": {"tools": TOOLS}}, sse=self.sse)
        else:
            self._send(200, {"jsonrpc": "2.0", "id": payload.get("id"),
                             "error": {"code": -32601, "message": f"ukendt metode {method}"}})


class AdapterTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()
        cls.url = f"http://127.0.0.1:{cls.server.server_address[1]}/wp-json/mcp/mcp-adapter-default-server"
        mcp_rest.URL = cls.url

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()

    def setUp(self):
        Handler.sse = False
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        # Ingen .env under test: credential kommer fra argumenterne, ikke fra disken.
        mcp_rest.ENV = Path(self.tmp.name) / ".env"

    def test_01_handshake_ok(self):
        ok, detail = mcp_rest.handshake(BASIC)
        self.assertTrue(ok, detail)
        self.assertIn("mcp-adapter", detail)
        self.assertIn('"session": true', detail)

    def test_02_handshake_afvises(self):
        ok, detail = mcp_rest.handshake(mcp_rest.basic_header(USER, "forkert-password"))
        self.assertFalse(ok)
        self.assertIn("401", detail)

    def test_03_tools_list(self):
        status, tools = mcp_rest.tools_list(BASIC)
        self.assertEqual(status, 200)
        self.assertEqual(len(tools), len(TOOLS))

    def test_04_sse_frame_parses(self):
        Handler.sse = True
        status, tools = mcp_rest.tools_list(BASIC)
        self.assertEqual(status, 200)
        self.assertEqual(len(tools), len(TOOLS))

    def test_05_classify_execute_er_skrivning(self):
        row = mcp_rest.classify(TOOLS[2])
        self.assertTrue(row["write"], "execute-ability skal tælles som skrivning")
        self.assertEqual(row["target"], "ability")
        self.assertEqual(row["params"], ["name", "input"])

    def test_06_classify_laesning(self):
        self.assertFalse(mcp_rest.classify(TOOLS[0])["write"])
        self.assertFalse(mcp_rest.classify(TOOLS[1])["write"])
        self.assertFalse(mcp_rest.classify(TOOLS[3])["write"])

    def test_07_classify_produkt_skrivning(self):
        row = mcp_rest.classify(TOOLS[4])
        self.assertTrue(row["write"])
        self.assertEqual(row["target"], "product")

    def test_08_classify_uden_annotations(self):
        row = mcp_rest.classify({"name": "mcp-adapter/execute-ability", "description": ""})
        self.assertTrue(row["write"], "navnet skal alene kunne afsløre skrivning")
        self.assertFalse(mcp_rest.classify({"name": "woocommerce/products-query"})["write"])

    def test_09_csv_skrives(self):
        out = Path(self.tmp.name) / "live.csv"
        code = mcp_rest.abilities(BASIC, out)
        self.assertEqual(code, 0)
        with out.open(encoding="utf-8") as handle:
            rows = list(csv.reader(handle))
        self.assertEqual(rows[0], ["navn", "skriver", "rammer", "parametre", "beskrivelse"])
        self.assertEqual(len(rows) - 1, len(TOOLS))
        self.assertEqual(rows[0 + 3][0], "mcp-adapter/execute-ability")
        self.assertEqual(rows[0 + 3][1], "True")
        self.assertEqual(rows[0 + 3][2], "ability")

    def test_10_hemmeligheden_skrives_ikke(self):
        out = Path(self.tmp.name) / "live.csv"
        mcp_rest.abilities(BASIC, out)
        blob = out.read_text(encoding="utf-8")
        self.assertNotIn(SECRET, blob)
        self.assertNotIn(SECRET.replace(" ", ""), blob)

    def test_11_csv_uden_sti_afvises(self):
        argv = sys.argv
        sys.argv = ["mcp_rest.py", "--abilities", "--csv"]
        try:
            import io
            from contextlib import redirect_stdout

            buffer = io.StringIO()
            with redirect_stdout(buffer):
                mcp_rest.URL = self.url
                code = mcp_rest.main()
            self.assertEqual(code, 1)
            self.assertIn("sti", buffer.getvalue())
        finally:
            sys.argv = argv

    def test_13_diff_mod_regnearket(self):
        sheet = Path(self.tmp.name) / "sheet.csv"
        sheet.write_text(
            "ability,kilde,type\n"
            "mcp-adapter/execute-ability,adapter,skrive\n"
            "(REST) wp/v2/pages,core,skrive\n"
            "woocommerce/product-delete,woo,skrive\n",
            encoding="utf-8",
        )
        self.assertEqual(mcp_rest.sheet_names(sheet), [
            "mcp-adapter/execute-ability", "wp/v2/pages", "woocommerce/product-delete",
        ])
        summary = mcp_rest.compare([mcp_rest.classify(tool) for tool in TOOLS], mcp_rest.sheet_names(sheet))
        self.assertEqual(summary["live"], len(TOOLS))
        self.assertIn("mcp-adapter/execute-ability", summary["live_skriver"])
        self.assertIn("woocommerce/product-update", summary["live_skriver"])
        self.assertNotIn("woocommerce/products-query", summary["live_skriver"])
        self.assertEqual(summary["mangler_live"], ["wp/v2/pages", "woocommerce/product-delete"])
        self.assertEqual(summary["ikke_i_arket"], ["mcp-adapter/discover-abilities",
                                                   "mcp-adapter/get-ability-info",
                                                   "woocommerce/product-update",
                                                   "woocommerce/products-query"])

    def test_14_diff_mod_tomt_regneark(self):
        self.assertEqual(mcp_rest.sheet_names(Path(self.tmp.name) / "findes-ikke.csv"), [])
        summary = mcp_rest.compare([mcp_rest.classify(TOOLS[0])], [])
        self.assertEqual(summary["mangler_live"], [])
        self.assertEqual(summary["ikke_i_arket"], ["mcp-adapter/discover-abilities"])

    def test_18_split_tre_spande(self):
        split = mcp_rest.split_tools([
            "steamgears-autopilot_status",
            "steamgears-autopilot_meta-audit",
            "wp_get_post",
            "woocommerce_products_list",
        ])
        self.assertEqual(split["counts"], {"vores": 2, "ai_engine": 1, "ability_bridge": 1})
        self.assertEqual(split["total"], 4)
        self.assertIn("steamgears-autopilot_meta-audit", split["vores"])
        self.assertIn("wp_get_post", split["ai_engine"])
        self.assertEqual(split["ability_bridge"], ["woocommerce_products_list"])

    def test_19_split_tomme_navne_tælles_ikke(self):
        split = mcp_rest.split_tools(["", "  ", "wp_count_posts"])
        self.assertEqual(split["total"], 1)
        self.assertEqual(split["counts"], {"vores": 0, "ai_engine": 1, "ability_bridge": 0})

    def test_20_split_kender_alle_vores_abilities(self):
        """CSV'en er den eneste kilde til hvad vores plugin registrerer: hver linje skal ramme »vores«."""
        csv_path = Path(__file__).resolve().parent.parent / "docs/library/egen-abilities-2026-11-02.csv"
        self.assertTrue(csv_path.exists(), f"mangler {csv_path}")
        expected = set()
        with csv_path.open(encoding="utf-8") as handle:
            for row in csv.DictReader(handle):
                if row.get("ability"):
                    expected.add(row["ability"].replace("/", "_"))
        self.assertEqual(len(expected), 32, "CSV'en skal have 32 abilities")
        empty = mcp_rest.split_tools([], csv_path)
        self.assertEqual(empty["mangler_live"], sorted(expected))
        live = mcp_rest.split_tools(sorted(expected), csv_path)
        self.assertEqual(live["counts"], {"vores": 32, "ai_engine": 0, "ability_bridge": 0})
        self.assertEqual(live["mangler_live"], [])
        self.assertEqual(live["uventet_live"], [])

    def test_21_native_listen_er_74_og_unik(self):
        self.assertEqual(len(mcp_rest.AI_ENGINE_NATIVE), 74)
        self.assertIn("wp_get_post", mcp_rest.AI_ENGINE_NATIVE)
        self.assertNotIn("steamgears-autopilot_status", mcp_rest.AI_ENGINE_NATIVE)

    def test_12_import_afviser_kort_password(self):
        bad = Path(self.tmp.name) / "mcp_rest.txt"
        bad.write_text(f"{USER}:kort\n", encoding="utf-8")
        self.assertEqual(mcp_rest.import_file(bad), 1)
        self.assertFalse(bad.exists(), "kildefilen skal slettes, også når den afvises")


if __name__ == "__main__":
    unittest.main(verbosity=2)
