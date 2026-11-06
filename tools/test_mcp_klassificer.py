#!/usr/bin/env python3
"""test_mcp_klassificer.py — offline selftest af mcp_klassificer.py.

Kør: python3 tools/test_mcp_klassificer.py

Testene kræver hverken netværk eller credential: de læser de indpakkede fixturer i
`tools/fixtures/` (en `tools/list`-eksport med de 74 indbyggede AI Engine-værktøjer, vores
26 fra `mcp-tools.php`, de 32 abilities fra regnearket og fem bro-værktøjer) og konstruerer
de værktøjer der skal bevise en enkelt regel.

Værnene dækker de fejl der ellers er lette at lave: at en annotation ikke vinder over navnet,
at `mode` rammer `moderate`, at `set` rammer `settings`, at et præfiks flytter kategorien, og
at en ufuldstændig eksport (nextCursor) bliver læst som om den var komplet.
"""

from __future__ import annotations

import contextlib
import csv
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import mcp_klassificer as mk  # noqa: E402

FIXTURE = HERE / "fixtures" / "mcp-tools-list.json"
PAGED = HERE / "fixtures" / "mcp-tools-list-side1.json"


def rows_by_name(payload) -> dict[str, dict]:
    tools, meta = mk.load_tools(payload)
    return {row["navn"]: row for row in mk.rows_for(tools)}


def tool(name: str, **annotations) -> dict:
    body = {"name": name, "description": "", "inputSchema": {"type": "object", "properties": {}}}
    if annotations:
        body["annotations"] = annotations
    return body


class FixtureTests(unittest.TestCase):
    """Hele den kendte værktøjsflade på én gang — 137 navne fra kilden, ikke fra hukommelsen."""

    @classmethod
    def setUpClass(cls):
        cls.payload = json.loads(FIXTURE.read_text(encoding="utf-8"))
        cls.rows = rows_by_name(cls.payload)
        cls.tools, cls.meta = mk.load_tools(cls.payload)
        cls.summary = mk.summarise(mk.rows_for(cls.tools), cls.meta)

    def test_01_alle_137_er_klassificeret(self):
        self.assertEqual(len(self.rows), 137)
        self.assertEqual(self.summary["vaerktoejer"], 137)
        self.assertEqual(self.summary["ukendte"], [])

    def test_02_alle_otte_kategorier_er_i_brug(self):
        self.assertEqual(set(self.summary["kategorier"]), set(mk.CATEGORIES),
                         "en tom kategori betyder at hint-reglen er brudt")

    def test_03_risikoklasserne_er_fire_og_ingen_ukendt(self):
        self.assertEqual(set(self.summary["risiko"]), set(mk.RISK_LEVELS))

    def test_04_sletninger_er_r3(self):
        for name in ("wp_delete_post", "wp_delete_media", "wp_delete_term", "wp_delete_comment",
                     "wp_delete_note", "wp_delete_post_meta"):
            self.assertEqual(self.rows[name]["risiko"], "R3", name)

    def test_05_livsfarlige_indstillinger_er_r3(self):
        for name in ("wp_update_option", "steamgears-tools_update-option",
                     "steamgears-tools_set-plugin-state", "steamgears-autopilot_enable-full-access",
                     "steamgears-autopilot_manage-plugin", "steamgears-autopilot_update"):
            self.assertEqual(self.rows[name]["risiko"], "R3", name)

    def test_06_broerne_er_r3_uanset_navn(self):
        """`call-ability` og `execute-ability` kan kalde alt — også det der sletter."""
        for name in ("steamgears-tools_call-ability", "mcp-adapter/execute-ability"):
            self.assertEqual(self.rows[name]["kategori"], "system", name)
            self.assertEqual(self.rows[name]["risiko"], "R3", name)
            self.assertEqual(self.rows[name]["grundlag"], "overstyring", name)

    def test_07_laesende_er_r0(self):
        for name in ("wp_get_post", "wp_get_posts", "wp_count_posts", "wp_get_seo", "mcp_ping",
                     "steamgears-tools_list-products", "steamgears-autopilot_list-queue"):
            self.assertEqual(self.rows[name]["risiko"], "R0", name)

    def test_08_kategorierne_rammer_rigtigt(self):
        expected = {
            "wp_get_post": "content", "wp_edit_blocks": "content",
            "wp_get_media": "media", "wp_upload_media": "media", "wp_set_featured_image": "media",
            "steamgears-tools_list-products": "commerce", "woocommerce_products_list": "commerce",
            "wp_get_redirects": "seo", "wp_update_seo": "seo", "steamgears-tools_indexnow-submit": "seo",
            "steamgears-autopilot_queue-post": "social", "steamgears-autopilot_publish": "social",
            "mwai_vision": "ai", "mwai_image": "ai",
            "wp_list_plugins": "ops", "wp_get_option": "ops", "wp_get_users": "ops",
            "mcp_ping": "system", "steamgears-tools_list-abilities": "system",
        }
        for name, category in expected.items():
            self.assertEqual(self.rows[name]["kategori"], category, name)

    def test_09_mode_rammer_ikke_moderate(self):
        """`mode` er et ops-hint, men »moderate« er et ord for sig: kommentarer er content."""
        self.assertEqual(self.rows["wp_moderate_comments"]["kategori"], "content")
        self.assertEqual(self.rows["wp_moderate_comments"]["risiko"], "R2")

    def test_10_set_rammer_ikke_settings(self):
        """Verbet `set` må ikke gøre `get-settings` til en skrivning."""
        self.assertEqual(self.rows["steamgears-autopilot_get-settings"]["risiko"], "R0")
        self.assertEqual(self.rows["steamgears-autopilot_set-mode"]["risiko"], "R2")

    def test_11_praefiks_flytter_ikke_kategorien(self):
        """`steamgears-autopilot_` er ejeren, ikke indholdet: et billede er media, ikke social."""
        self.assertEqual(self.rows["steamgears-autopilot_generate-image"]["kategori"], "media")
        self.assertEqual(self.rows["steamgears-autopilot_generate-image"]["risiko"], "R1")

    def test_12_grundlaget_er_sporbart(self):
        for row in self.rows.values():
            self.assertIn(row["grundlag"], {
                "overstyring", "annotation (readOnlyHint)", "annotation (destructiveHint)",
                "annotation (skriver, ukendt udsagnsord)", "udsagnsord",
            }, row["navn"])
            if row["grundlag"] == "overstyring":
                self.assertTrue(row["notat"], f"{row['navn']} mangler en begrundelse")

    def test_13_csv_har_en_linje_pr_vaerktoej(self):
        buffer = io.StringIO()
        with contextlib.redirect_stdout(buffer):
            mk.write_csv(mk.rows_for(self.tools), None)
        lines = list(csv.reader(io.StringIO(buffer.getvalue())))
        self.assertEqual(lines[0], ["navn", "kategori", "risiko", "grundlag", "notat"])
        self.assertEqual(len(lines) - 1, 137)
        self.assertTrue(all(row[1] in mk.CATEGORIES for row in lines[1:]))

    def test_14_raekkefoelgen_er_kategori_og_navn(self):
        rows = mk.rows_for(self.tools)
        keys = [(mk.CATEGORIES.index(row["kategori"]), row["navn"].lower()) for row in rows]
        self.assertEqual(keys, sorted(keys))


class AnnotationTests(unittest.TestCase):
    """Annotationen vinder over navnet — også når navnet lyver."""

    def test_20_readonly_vinder_over_delete(self):
        row = mk.classify_tool(tool("wp_delete_post", readOnlyHint=True))
        self.assertEqual(row["risiko"], "R0")
        self.assertEqual(row["grundlag"], "annotation (readOnlyHint)")

    def test_21_destructive_vinder_over_get(self):
        row = mk.classify_tool(tool("wp_get_post", destructiveHint=True))
        self.assertEqual(row["risiko"], "R3")
        self.assertEqual(row["grundlag"], "annotation (destructiveHint)")

    def test_22_skriver_uden_udsagnsord_bliver_r2(self):
        row = mk.classify_tool(tool("wp_frobnicate", readOnlyHint=False))
        self.assertEqual(row["risiko"], "R2")
        self.assertEqual(row["grundlag"], "annotation (skriver, ukendt udsagnsord)")

    def test_23_læsende_uden_udsagnsord_bliver_r0(self):
        row = mk.classify_tool(tool("wp_frobnicate", readOnlyHint=True))
        self.assertEqual(row["risiko"], "R0")

    def test_24_uden_annotation_bruges_navnet(self):
        row = mk.classify_tool(tool("wp_update_post"))
        self.assertEqual((row["kategori"], row["risiko"], row["grundlag"]),
                         ("content", "R2", "udsagnsord"))


class UnknownTests(unittest.TestCase):
    """Det reglerne ikke kan afgøre skal stå som ukendt — ikke gættes ind i en åben profil."""

    def test_30_ukendt_navn_er_ukendt(self):
        row = mk.classify_tool(tool("zzz_qwerty"))
        self.assertEqual((row["kategori"], row["risiko"]), (mk.UNKNOWN, mk.UNKNOWN))
        self.assertEqual(row["grundlag"], "ukendt")
        self.assertIn("overstyringstabellen", row["notat"])

    def test_31_ukendt_taelles_i_resumeet(self):
        rows = mk.rows_for([tool("zzz_qwerty"), tool("wp_get_post")])
        summary = mk.summarise(rows, {"form": "test"})
        self.assertEqual(summary["ukendte"], ["zzz_qwerty"])
        self.assertEqual(summary["kategorier"][mk.UNKNOWN], 1)

    def test_32_strict_giver_exit_1(self):
        with tempfile.TemporaryDirectory() as tmp:
            source = Path(tmp) / "eksport.json"
            source.write_text(json.dumps({"tools": [tool("zzz_qwerty")]}), encoding="utf-8")
            with contextlib.redirect_stderr(io.StringIO()):
                self.assertEqual(mk.main(["--input", str(source), "--csv", str(Path(tmp) / "ud.csv"),
                                          "--strict"]), 1)
                self.assertEqual(mk.main(["--input", str(source), "--csv", str(Path(tmp) / "ud.csv")]), 0)

    def test_33_tom_navn_springes_over(self):
        rows = mk.rows_for([tool(""), tool("  "), tool("wp_get_post")])
        self.assertEqual([row["navn"] for row in rows], ["wp_get_post"])


class ExportFormTests(unittest.TestCase):
    """Eksporten optræder i flere former, og en ufuldstændig liste skal siges højt."""

    def test_40_json_rpc_og_objekt_og_liste_giver_samme_resultat(self):
        tools = [tool("wp_get_post"), tool("wp_delete_post")]
        forms = [
            {"jsonrpc": "2.0", "id": 2, "result": {"tools": tools}},
            {"tools": tools},
            tools,
            [{"result": {"tools": tools[:1]}}, {"result": {"tools": tools[1:]}}],
        ]
        counts = []
        for form in forms:
            loaded, _ = mk.load_tools(form)
            counts.append(len(loaded))
        self.assertEqual(counts, [2, 2, 2, 2])

    def test_41_next_cursor_markeres_som_ufuldstaendig(self):
        loaded, meta = mk.load_tools(json.loads(PAGED.read_text(encoding="utf-8")))
        self.assertEqual(len(loaded), 28)
        self.assertTrue(meta["naeste_cursor"])
        self.assertTrue(mk.summarise(mk.rows_for(loaded), meta)["ufuldstaendig"])

    def test_42_komplet_eksport_er_ikke_ufuldstaendig(self):
        _, meta = mk.load_tools(json.loads(FIXTURE.read_text(encoding="utf-8")))
        self.assertFalse(meta["naeste_cursor"])

    def test_43_ukendt_form_afvises(self):
        with self.assertRaises(ValueError):
            mk.load_tools({"noget": "andet"})

    def test_44_command_line_skriver_filen(self):
        with tempfile.TemporaryDirectory() as tmp:
            target = Path(tmp) / "klassifikation.csv"
            with contextlib.redirect_stderr(io.StringIO()):
                code = mk.main(["--input", str(FIXTURE), "--csv", str(target)])
            self.assertEqual(code, 0)
            with target.open(encoding="utf-8", newline="") as handle:
                rows = list(csv.DictReader(handle))
            self.assertEqual(len(rows), 137)
            self.assertEqual({row["kategori"] for row in rows}, set(mk.CATEGORIES))

    def test_45_tom_input_og_bad_json_giver_exit_2(self):
        with tempfile.TemporaryDirectory() as tmp:
            empty = Path(tmp) / "tom.json"
            empty.write_text("", encoding="utf-8")
            bad = Path(tmp) / "fejl.json"
            bad.write_text("{ikke json", encoding="utf-8")
            with contextlib.redirect_stderr(io.StringIO()):
                self.assertEqual(mk.main(["--input", str(empty)]), 2)
                self.assertEqual(mk.main(["--input", str(bad)]), 2)
                self.assertEqual(mk.main(["--csv"]), 2)
                self.assertEqual(mk.main(["--input", "/findes/ikke.json"]), 2)


class OverrideHygieneTests(unittest.TestCase):
    def test_50_overstyringer_er_gyldige(self):
        for name, (category, risk, reason) in mk.OVERRIDES.items():
            self.assertIn(category, mk.CATEGORIES, name)
            self.assertIn(risk, mk.RISK_LEVELS, name)
            self.assertGreaterEqual(len(reason), 8, f"{name} mangler en brugbar begrundelse")

    def test_51_kategorier_og_risiko_staver_ens(self):
        self.assertEqual(mk.CATEGORIES, ("content", "media", "commerce", "seo", "social", "ai", "ops", "system"))
        self.assertEqual(mk.RISK_LEVELS, ("R0", "R1", "R2", "R3"))

    def test_52_overstyring_slaas_op_paa_det_bare_navn(self):
        """Præfikset er ejeren, så én linje skal dække både `update` og `steamgears-autopilot_update`."""
        self.assertEqual(mk.classify_tool(tool("steamgears-autopilot_update"))["risiko"], "R3")
        self.assertEqual(mk.classify_tool(tool("update"))["risiko"], "R3")


if __name__ == "__main__":
    unittest.main(verbosity=2)
