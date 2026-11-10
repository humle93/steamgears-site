#!/usr/bin/env python3
"""Tests for agent_brief.py — kører uden netværk.

    python3 tools/test_agent_brief.py
"""

from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import agent_brief as ab  # noqa: E402

FIXTURE = Path(__file__).resolve().parent / "fixtures" / "agent_brief.json"


def load_fixture() -> dict:
    return json.loads(FIXTURE.read_text(encoding="utf-8"))


class PriceTests(unittest.TestCase):
    def test_minor_units_are_two_for_dkk(self):
        product = {"prices": {"price": "69900", "currency_code": "DKK", "currency_minor_unit": 2}}
        self.assertEqual(ab.price_of(product), 699.0)

    def test_minor_units_zero_is_read_as_whole_currency(self):
        self.assertEqual(ab.price_of({"prices": {"price": "699", "currency_minor_unit": 0}}), 699.0)

    def test_a_product_without_price_is_none_not_zero(self):
        self.assertIsNone(ab.price_of({"prices": {}}))
        self.assertIsNone(ab.price_of({"prices": {"price": "not a number"}}))
        self.assertIsNone(ab.price_of({}))

    def test_currency_comes_from_the_first_product_that_has_one(self):
        self.assertEqual(ab.currency_of([{}, {"prices": {"currency_code": "dkk"}}]), "DKK")
        self.assertEqual(ab.currency_of([]), "")


class BuildTests(unittest.TestCase):
    def test_fixture_gives_the_measured_catalogue(self):
        raw = load_fixture()
        facts = ab.build(raw["products"], raw["posts_total"], raw["pages_total"], raw["newest_post"])
        self.assertEqual(facts["products"], 3)
        self.assertEqual(facts["currency"], "DKK")
        self.assertEqual(facts["price_min"], 69.0)
        self.assertEqual(facts["price_max"], 1495.0)
        self.assertEqual(facts["categories"], 3)
        self.assertEqual(facts["posts"], 33)
        self.assertEqual(facts["pages"], 29)
        self.assertEqual(facts["newest"], "2026-11-08")
        self.assertEqual(facts["missing"], [])

    def test_unreadable_sources_are_named_not_guessed(self):
        facts = ab.build([], None, None, None)
        self.assertEqual(facts["products"], 0)
        self.assertIsNone(facts["price_min"])
        self.assertEqual(len(facts["missing"]), 4)
        self.assertTrue(any("produkter" in item for item in facts["missing"]))
        self.assertTrue(any("nyeste indlæg" in item for item in facts["missing"]))


class RenderTests(unittest.TestCase):
    def facts(self) -> dict:
        raw = load_fixture()
        return ab.build(raw["products"], raw["posts_total"], raw["pages_total"], raw["newest_post"])

    def test_brief_carries_the_measured_numbers(self):
        text = ab.render(self.facts(), "2026-11-10")
        self.assertIn(ab.MARKER, text)
        self.assertIn("| Produkter i kataloget | 3 |", text)
        self.assertIn("| Valuta | DKK |", text)
        self.assertIn("69–1\u00a0495 DKK", text)
        self.assertIn("| Publicerede indlæg | 33 |", text)
        self.assertIn("| Nyeste indlæg | 2026-11-08 |", text)

    def test_brief_tells_the_agent_what_it_may_not_claim(self):
        text = ab.render(self.facts(), "2026-11-10")
        self.assertIn("Nævn kun produkter og priser der findes nu", text)
        self.assertIn("Hvad filen ikke ved", text)
        self.assertIn("sgap_events", text)

    def test_a_missing_measurement_is_visible_in_the_table(self):
        facts = ab.build([], None, None, None)
        text = ab.render(facts, "2026-11-10")
        self.assertIn("| Produkter i kataloget | 0 |", text)
        self.assertIn("| Valuta | utilgængelig |", text)
        self.assertIn("Kilder der ikke kunne læses", text)
        self.assertNotIn("None", text)
        self.assertNotIn("null", text)

    def test_render_is_danish_and_dated(self):
        text = ab.render(self.facts(), "2026-11-10")
        self.assertIn("Målt **2026-11-10**", text)
        self.assertIn("utilgængelig", ab.render(ab.build([], None, None, None), "2026-11-10"))


class CheckTests(unittest.TestCase):
    def test_a_missing_brief_fails_the_check(self):
        with tempfile.TemporaryDirectory() as tmp:
            self.assertEqual(ab.check(Path(tmp) / "agent-brief.md"), 1)

    def test_a_real_brief_passes_and_a_wrong_file_does_not(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "agent-brief.md"
            path.write_text(ab.render(ab.build([], 1, 1, None), "2026-11-10"), encoding="utf-8")
            self.assertEqual(ab.check(path), 0)
            path.write_text("# Noget andet\n", encoding="utf-8")
            self.assertEqual(ab.check(path), 1)

    def test_offline_snapshot_uses_the_fixture(self):
        raw, errors = ab.snapshot(offline=True)
        self.assertEqual(errors, {})
        self.assertEqual(len(raw["products"]), 3)
        self.assertEqual(raw["posts_total"], 33)


if __name__ == "__main__":
    unittest.main(verbosity=2)
