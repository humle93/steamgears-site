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

    def test_demand_score_is_bounded_and_monotonic(self):
        low = ns.demand_score(-40, -40)
        high = ns.demand_score(180, 60)
        self.assertTrue(0 <= low < high <= 100)

    def test_opportunity_is_highest_when_we_sell_nothing(self):
        self.assertAlmostEqual(ns.opportunity_score(80, 0), 80.0)
        self.assertAlmostEqual(ns.opportunity_score(80, 3), 0.0)
        self.assertEqual(ns.opportunity_score(80, 1), round(80 * (1 - 1 / 3), 1))


class SeriesTests(unittest.TestCase):
    def test_parse_points_sorts_dated_series(self):
        points = ns.parse_points({"2026-08-11": 22, "2026-08-04": 18})
        self.assertEqual([p[0] for p in points], ["2026-08-04", "2026-08-11"])
        self.assertEqual([p[1] for p in points], [18.0, 22.0])

    def test_parse_points_indexes_lists(self):
        self.assertEqual(ns.parse_points([3, 5]), [("0", 3.0), ("1", 5.0)])
        self.assertEqual(ns.parse_points(None), [])

    def test_month_label_reads_iso_dates_only(self):
        self.assertEqual(ns.month_label("2026-10-06"), "okt")
        self.assertEqual(ns.month_label("2026-01-06"), "jan")
        self.assertIsNone(ns.month_label("7"))
        self.assertIsNone(ns.month_label("2026-13-01"))

    def test_low_season_is_lowest_quartile(self):
        points = [(f"2026-{month:02d}-01", value) for month, value in
                  enumerate([10, 10, 20, 20, 40, 40, 80, 100, 100, 60, 40, 20], start=1)]
        season = ns.season_shape(points)
        self.assertEqual(season.observed, 12)
        self.assertEqual(season.censored, 0)
        self.assertAlmostEqual(season.low_level, 40 / 3)  # nederste kvartil = 10, 10, 20
        self.assertEqual(season.peak_label, "aug")  # første top i serien, ikke den anden
        self.assertEqual(season.trough_label, "jan")

    def test_zeros_are_censored_not_averaged(self):
        points = [("2026-08-04", 0), ("2026-08-11", 0), ("2026-08-18", 30), ("2026-08-25", 10)]
        season = ns.season_shape(points)
        self.assertEqual(season.censored, 2)
        self.assertEqual(season.observed, 2)
        self.assertAlmostEqual(season.low_level, 10.0)  # nul'erne trækker ikke snittet ned

    def test_all_zero_series_is_not_rankable(self):
        season = ns.season_shape([("2026-08-04", 0), ("2026-08-11", 0)])
        self.assertTrue(season.has_series)
        self.assertTrue(season.all_censored)
        self.assertIsNone(season.low_level)
        self.assertEqual(season.low_text, "–")

    def test_missing_series_is_not_censoring(self):
        season = ns.season_shape([])
        self.assertFalse(season.has_series)
        self.assertFalse(season.all_censored)  # TikTok-eksporten har ingen serie, men er ikke censureret
        row = ns.Row(keyword="herbarium art", source="tiktok", demand=60, growth_yoy=190,
                     momentum=None, matches=[], opportunity=60, season=season, days_seen=1)
        self.assertTrue(row.rankable)
        self.assertEqual(row.status, "For tidlig (1/3 dage)")


class MomentumTests(unittest.TestCase):
    def test_momentum_comes_from_the_mom_field(self):
        signals, _status = ns.pinterest_signals(["x"], "SE+DK+FI+NO", "seasonal", token=None, offline=True)
        outfit = next(s for s in signals if s.keyword == "dark academia outfit")
        self.assertEqual(outfit.growth_mom, 38)
        self.assertEqual(outfit.momentum, 38)  # ikke udregnet af den normaliserede serie

    def test_missing_mom_is_neutral_and_not_double_counted(self):
        signal = ns.Signal(keyword="x", source="tiktok", growth_yoy=60, growth_mom=None)
        self.assertIsNone(signal.momentum)
        self.assertEqual(ns.demand_score(60, None), round(0.6 * ns.clamp_pct(60) + 0.4 * 50.0, 1))
        self.assertNotEqual(ns.demand_score(60, None), ns.demand_score(60, 60))

    def test_tiktok_has_no_series_and_no_peak_month(self):
        signals, _status = ns.tiktok_signals(None, offline=True)
        self.assertEqual(signals[0].points, [])
        self.assertTrue(signals[0].momentum is None)
        rows, _sources, _catalog, _code = ns.run(["x"], "SE+DK+FI+NO", "seasonal", True, None)
        tiktok = [r for r in rows if r.source == "tiktok"]
        self.assertTrue(tiktok and all(r.rankable for r in tiktok))


class HistoryTests(unittest.TestCase):
    def test_missing_history_is_empty(self):
        with tempfile.TemporaryDirectory() as tmp:
            self.assertEqual(ns.load_history(Path(tmp)), {})

    def test_record_run_is_idempotent_per_day(self):
        with tempfile.TemporaryDirectory() as tmp:
            out = Path(tmp)
            history = ns.record_run(out, {}, ["Herbarium print"], "2026-11-03")
            history = ns.record_run(out, history, ["herbarium print"], "2026-11-03")
            self.assertEqual(history["herbarium print"], ["2026-11-03"])
            self.assertEqual(ns.load_history(out), history)

    def test_days_seen_counts_distinct_days(self):
        history = {"herbarium print": ["2026-11-01", "2026-11-02"]}
        self.assertEqual(ns.days_seen("Herbarium print", history, "2026-11-03"), 3)
        self.assertEqual(ns.days_seen("herbarium print", history, "2026-11-02"), 2)
        self.assertEqual(ns.days_seen("ukendt", history, "2026-11-03"), 1)

    def test_status_gate_needs_three_days(self):
        row = ns.Row(keyword="x", source="pinterest", demand=70, growth_yoy=50, momentum=20,
                     matches=[], opportunity=70, days_seen=1)
        self.assertEqual(row.status, "For tidlig (1/3 dage)")
        row.days_seen = 2
        self.assertEqual(row.status, "For tidlig (2/3 dage)")
        row.days_seen = 3
        self.assertEqual(row.status, "Anbefalet (3 dage)")


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
        self.assertGreater(len(herbarium.points), 0)

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
        gaps = [r.keyword for r in rows if r.matches == [] and r.rankable]
        covered = [r.keyword for r in rows if r.matches]
        self.assertIn("herbarium print", gaps)
        self.assertIn("steampunk corset", covered)
        self.assertTrue(all(s.startswith("ok") for s in sources.values()))

    def test_rows_sorted_by_opportunity_with_censored_last(self):
        catalog = ns.load_catalog(offline=True)
        signals = [
            ns.Signal(keyword="herbarium print", source="pinterest", growth_yoy=200, growth_mom=90),
            ns.Signal(keyword="nul-serie", source="pinterest", growth_yoy=200, growth_mom=90,
                      points=[("2026-08-04", 0), ("2026-08-11", 0)]),
            ns.Signal(keyword="steampunk corset", source="pinterest", growth_yoy=10, growth_mom=5),
        ]
        rows = ns.score_rows(signals, catalog)
        self.assertFalse(rows[-1].rankable)
        self.assertEqual(rows[-1].keyword, "nul-serie")
        opportunities = [r.opportunity for r in rows if r.rankable]
        self.assertEqual(opportunities, sorted(opportunities, reverse=True))

    def test_history_reaches_rows(self):
        rows, _sources, _catalog, _code = ns.run(
            ["x"], "SE+DK+FI+NO", "seasonal", True, None,
            history={"herbarium art": ["2026-11-01", "2026-11-02"]}, today="2026-11-03")
        herbarium = next(r for r in rows if r.keyword == "herbarium art")
        self.assertEqual(herbarium.days_seen, 3)
        self.assertTrue(herbarium.status.startswith("Anbefalet"))

    def test_no_source_is_exit_code_two(self):
        with mock.patch.object(ns, "pinterest_signals", return_value=([], "utilgængelig: test")), \
             mock.patch.object(ns, "tiktok_signals", return_value=([], "utilgængelig: test")):
            rows, _sources, _catalog, code = ns.run(["x"], "SE+DK+FI+NO", "seasonal", True, None)
        self.assertEqual(rows, [])
        self.assertEqual(code, 2)


class RenderTests(unittest.TestCase):
    def setUp(self):
        self.rows, self.sources, self.catalog, _code = ns.run(
            ["x"], "SE+DK+FI+NO", "seasonal", True, None, today="2026-11-03")

    def meta(self, history=None):
        return {"date": "2026-11-03", "region": "SE+DK+FI+NO", "trend_type": "seasonal",
                "catalog_count": len(self.catalog), "seeds": ["x"], "history": history or {}}

    def test_report_has_all_sections_and_no_none(self):
        report = ns.render(self.rows, self.sources, self.meta())
        for heading in ("## Kilder", "## Rangerede huller", "## Dækket i forvejen",
                        "## Sådan læses tallene", "## Hvad kilderne ikke kan levere"):
            self.assertIn(heading, report)
        self.assertNotIn("None", report)
        self.assertIn("herbarium print", report)

    def test_report_shows_low_season_and_status(self):
        report = ns.render(self.rows, self.sources, self.meta())
        self.assertIn("Lavsæson", report)
        self.assertIn("For tidlig (1/3 dage)", report)
        self.assertIn("**Momentum** er kildens eget", report)

    def test_report_lists_censored_rows_separately(self):
        rows = self.rows + [ns.Row(keyword="nul-serie", source="pinterest", demand=80, growth_yoy=200,
                                   momentum=90, matches=[], opportunity=80,
                                   season=ns.Season(censored=12, has_series=True))]
        report = ns.render(rows, self.sources, self.meta())
        self.assertIn("## Censurerede rækker — ikke rangeret", report)
        self.assertIn("| nul-serie | pinterest | 12 | Censureret |", report)

    def test_report_has_history_section_when_present(self):
        report = ns.render(self.rows, self.sources, self.meta({"herbarium art": ["2026-11-03"]}))
        self.assertIn("## Kørselshistorik", report)
        self.assertIn("| herbarium art | 1 | 2026-11-03 |", report)


class CliTests(unittest.TestCase):
    def test_cli_writes_report_json_and_history(self):
        with tempfile.TemporaryDirectory() as tmp:
            code = ns.main(["--offline", "--seeds", "steampunk corset", "--out-dir", tmp])
            self.assertEqual(code, 0)
            files = sorted(p.name for p in Path(tmp).iterdir())
            self.assertEqual(len(files), 3, files)
            self.assertTrue(any(f.endswith(".md") for f in files))
            self.assertIn(ns.HISTORY_NAME, files)
            data = json.loads(next(Path(tmp).glob("niche-scan-*.json")).read_text(encoding="utf-8"))
            self.assertEqual(len(data["rows"]), 10)
            self.assertIn("Pinterest Trends (API v5)", data["sources"])
            self.assertIn("low_season", data["rows"][0])
            self.assertIn("days_seen", data["rows"][0])

    def test_cli_keeps_one_day_per_date(self):
        with tempfile.TemporaryDirectory() as tmp:
            ns.main(["--offline", "--seeds", "steampunk corset", "--out-dir", tmp])
            ns.main(["--offline", "--seeds", "steampunk corset", "--out-dir", tmp])
            history = json.loads((Path(tmp) / ns.HISTORY_NAME).read_text(encoding="utf-8"))
            self.assertEqual(history["min_days"], 3)
            self.assertTrue(all(len(days) == 1 for days in history["keywords"].values()))

    def test_cli_requires_seeds(self):
        with self.assertRaises(SystemExit):
            ns.main(["--offline"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
