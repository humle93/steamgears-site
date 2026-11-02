#!/usr/bin/env python3
"""Tests for niche_scanner.py — kører uden netværk og uden tokens.

    python3 tools/test_niche_scanner.py
"""

from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parent))
import niche_scanner as ns  # noqa: E402


class ScoringTests(unittest.TestCase):
    def test_clamp_pct_bounds(self):
        self.assertEqual(ns.clamp_pct(-500), 0.0)
        self.assertEqual(ns.clamp_pct(10_000), 100.0)
        self.assertAlmostEqual(ns.clamp_pct(-50), 0.0)
        self.assertAlmostEqual(ns.clamp_pct(200), 100.0)

    def test_clamp_pct_missing_value_is_neutral(self):
        self.assertEqual(ns.clamp_pct(None), 50.0)

    def test_slope_momentum_needs_eight_points(self):
        self.assertIsNone(ns.slope_momentum([1, 2, 3]))

    def test_slope_momentum_direction(self):
        rising = ns.slope_momentum([10, 10, 10, 10, 20, 20, 20, 20])
        falling = ns.slope_momentum([20, 20, 20, 20, 10, 10, 10, 10])
        self.assertAlmostEqual(rising, 100.0)
        self.assertAlmostEqual(falling, -50.0)

    def test_demand_score_is_bounded_and_monotonic(self):
        low = ns.demand_score(-40, -40)
        high = ns.demand_score(180, 60)
        self.assertTrue(0 <= low < high <= 100)

    def test_opportunity_is_highest_when_we_sell_nothing(self):
        self.assertAlmostEqual(ns.opportunity_score(80, 0), 80.0)
        self.assertAlmostEqual(ns.opportunity_score(80, 3), 0.0)
        self.assertEqual(ns.opportunity_score(80, 1), round(80 * (1 - 1 / 3), 1))


class CatalogTests(unittest.TestCase):
    def setUp(self):
        self.catalog = ns.load_catalog(offline=True)

    def test_fixture_has_products(self):
        self.assertGreaterEqual(len(self.catalog), 8)

    def test_keyword_matches_product(self):
        hits = ns.match_products("steampunk corset", self.catalog)
        self.assertTrue(any("Corset" in name for name in hits))
        self.assertGreaterEqual(len(hits), 3)

    def test_unrelated_keyword_matches_nothing(self):
        self.assertEqual(ns.match_products("herbarium print", self.catalog), [])

    def test_stopwords_do_not_create_matches(self):
        self.assertEqual(ns.match_products("the and for", self.catalog), [])


class SourceTests(unittest.TestCase):
    def test_pinterest_fixture_parses(self):
        signals, status = ns.pinterest_signals(["steampunk corset"], "SE+DK+FI+NO", "seasonal",
                                               token=None, offline=True)
        self.assertTrue(status.startswith("ok"))
        self.assertGreaterEqual(len(signals), 5)
        herbarium = next(s for s in signals if s.keyword == "herbarium print")
        self.assertEqual(herbarium.source, "pinterest")
        self.assertGreater(herbarium.momentum, 0)

    def test_pinterest_without_token_reports_why(self):
        signals, status = ns.pinterest_signals(["x"], "SE+DK+FI+NO", "seasonal", token=None, offline=False)
        self.assertEqual(signals, [])
        self.assertIn("PINTEREST_ACCESS_TOKEN", status)

    def test_tiktok_reads_csv(self):
        signals, status = ns.tiktok_signals(None, offline=True)
        self.assertTrue(status.startswith("ok"))
        self.assertEqual(len(signals), 4)
        self.assertEqual(signals[0].source, "tiktok")

    def test_tiktok_without_export_explains_the_gap(self):
        signals, status = ns.tiktok_signals(None, offline=False)
        self.assertEqual(signals, [])
        self.assertIn("ingen officiel søge-API", status)

    def test_missing_export_file_is_reported(self):
        signals, status = ns.tiktok_signals("/nonexistent/file.csv", offline=False)
        self.assertEqual(signals, [])
        self.assertIn("findes ikke", status)


class RunTests(unittest.TestCase):
    def test_offline_run_finds_gaps_and_coverage(self):
        rows, sources, catalog, code = ns.run(
            ["steampunk corset", "herbarium print"], "SE+DK+FI+NO", "seasonal", True, None)
        self.assertEqual(code, 0)
        self.assertEqual(len(rows), 10)  # 6 pinterest + 4 tiktok
        gaps = [r.keyword for r in rows if not r.matches]
        covered = [r.keyword for r in rows if r.matches]
        self.assertIn("herbarium print", gaps)
        self.assertIn("steampunk corset", covered)
        self.assertTrue(all(s.startswith("ok") for s in sources.values()))

    def test_rows_sorted_by_opportunity(self):
        rows, _sources, _catalog, _code = ns.run(["x"], "SE+DK+FI+NO", "seasonal", True, None)
        opportunities = [r.opportunity for r in rows]
        self.assertEqual(opportunities, sorted(opportunities, reverse=True))

    def test_no_source_is_exit_code_two(self):
        with mock.patch.object(ns, "pinterest_signals", return_value=([], "utilgængelig: test")), \
             mock.patch.object(ns, "tiktok_signals", return_value=([], "utilgængelig: test")):
            rows, _sources, _catalog, code = ns.run(["x"], "SE+DK+FI+NO", "seasonal", True, None)
        self.assertEqual(rows, [])
        self.assertEqual(code, 2)


class RenderTests(unittest.TestCase):
    def test_report_has_all_sections_and_no_none(self):
        rows, sources, catalog, _code = ns.run(["x"], "SE+DK+FI+NO", "seasonal", True, None)
        meta = {"date": "2026-11-02", "region": "SE+DK+FI+NO", "trend_type": "seasonal",
                "catalog_count": len(catalog), "seeds": ["x"]}
        report = ns.render(rows, sources, meta)
        for heading in ("## Kilder", "## Rangerede huller", "## Dækket i forvejen",
                        "## Sådan læses tallene", "## Hvad kilderne ikke kan levere"):
            self.assertIn(heading, report)
        self.assertNotIn("None", report)
        self.assertIn("herbarium print", report)


class CliTests(unittest.TestCase):
    def test_cli_writes_both_files(self):
        with tempfile.TemporaryDirectory() as tmp:
            code = ns.main(["--offline", "--seeds", "steampunk corset", "--out-dir", tmp])
            self.assertEqual(code, 0)
            files = sorted(p.name for p in Path(tmp).iterdir())
            self.assertEqual(len(files), 2, files)
            self.assertTrue(any(f.endswith(".md") for f in files))
            data = json.loads(next(Path(tmp).glob("*.json")).read_text(encoding="utf-8"))
            self.assertEqual(len(data["rows"]), 10)
            self.assertIn("Pinterest Trends (API v5)", data["sources"])

    def test_cli_requires_seeds(self):
        with self.assertRaises(SystemExit):
            ns.main(["--offline"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
