#!/usr/bin/env python3
"""Test for niche_scan.py — grænseværdier, rangorden og at fejl ikke går stille igennem.

Kør: python3 -m unittest discover -s tests -v
"""

import contextlib
import csv
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

import niche_scan as ns  # noqa: E402

# Tallene fra niche-vurdering-2026-11-01.md (tredjepartsestimater, ikke målte).
DOC_NICHES = [
    {"niche": "steampunk goggles", "searches": 9900, "listings": 3175, "competitor": "handmade", "difficulty": 22},
    {"niche": "steampunk corset", "searches": 2270, "listings": 7114, "competitor": "handmade", "difficulty": 33},
    {"niche": "steampunk top hat", "searches": 1600, "listings": 10686, "competitor": "handmade"},
    {"niche": "steampunk costume", "searches": 18100, "listings": 45063, "swing": 16.8, "competitor": "handmade", "difficulty": 41},
]


class ListingsPoints(unittest.TestCase):
    def test_green_band_scores_full(self):
        self.assertEqual(ns.score_listings(10), 45.0)
        self.assertEqual(ns.score_listings(ns.LISTINGS_EASY), 45.0)

    def test_hard_band_scores_zero(self):
        self.assertEqual(ns.score_listings(ns.LISTINGS_HARD + 1), 0.0)
        self.assertEqual(ns.score_listings(500_000), 0.0)

    def test_yellow_band_edges(self):
        self.assertAlmostEqual(ns.score_listings(ns.LISTINGS_HARD), 10.0, places=6)
        self.assertTrue(10.0 < ns.score_listings(5_000) < 45.0)

    def test_monotone_decreasing(self):
        values = [ns.score_listings(n) for n in (1_000, 2_000, 5_000, 20_000, 50_000, 60_000)]
        self.assertEqual(values, sorted(values, reverse=True))


class SearchPoints(unittest.TestCase):
    def test_floor_and_ceiling(self):
        self.assertEqual(ns.score_searches(50), 0.0)
        self.assertEqual(ns.score_searches(100), 0.0)
        self.assertEqual(ns.score_searches(20_000), 25.0)
        self.assertEqual(ns.score_searches(900_000), 25.0)

    def test_middle_is_between(self):
        self.assertTrue(0 < ns.score_searches(1_000) < 25)


class SwingPoints(unittest.TestCase):
    def test_missing_is_not_a_zero(self):
        self.assertIsNone(ns.score_swing(None))

    def test_flat_season_scores_full(self):
        self.assertEqual(ns.score_swing(1), 20.0)
        self.assertEqual(ns.score_swing(3), 20.0)

    def test_brutal_season_scores_zero(self):
        self.assertEqual(ns.score_swing(17), 0.0)
        self.assertEqual(ns.score_swing(30), 0.0)

    def test_between_is_monotone(self):
        self.assertTrue(ns.score_swing(6) > ns.score_swing(12) > 0)


class Scoring(unittest.TestCase):
    def test_competitor_type_matters(self):
        base = {"niche": "x", "searches": 5_000, "listings": 4_000, "swing": 2}
        handmade = ns.score_row({**base, "competitor": "handmade"}, 1)["score"]
        mixed = ns.score_row({**base, "competitor": "mixed"}, 1)["score"]
        pod = ns.score_row({**base, "competitor": "pod"}, 1)["score"]
        self.assertLess(handmade, mixed)
        self.assertLess(mixed, pod)

    def test_hard_rule_beats_a_high_score(self):
        row = ns.score_row(
            {"niche": "stort marked", "searches": 500_000, "listings": 60_000, "swing": 1, "competitor": "pod"}, 1
        )
        self.assertGreaterEqual(row["score"], 45)
        self.assertEqual(row["verdict"], "nej")
        self.assertTrue(any("hårdt nej" in note for note in row["notes"]))

    def test_missing_swing_is_normalised_not_penalised(self):
        row = ns.score_row(
            {"niche": "uden sæson", "searches": 20_000, "listings": 1_000, "competitor": "pod"}, 1
        )
        self.assertEqual(row["available_weight"], 80.0)
        self.assertEqual(row["score"], 100.0)
        self.assertEqual(row["verdict"], "grøn")
        self.assertTrue(any("sæsonsving mangler" in note for note in row["notes"]))

    def test_score_is_recomputable_from_parts(self):
        row = ns.score_row(DOC_NICHES[0], 1)
        points = sum(value for value in row["parts"].values() if value is not None)
        expected = round(100 * points / row["available_weight"], 1)
        self.assertEqual(row["score"], expected)


class Validation(unittest.TestCase):
    def assert_rejects(self, row, fragment):
        with self.assertRaises(ns.RowError) as caught:
            ns.score_row(row, 7)
        self.assertIn(fragment, str(caught.exception))

    def test_missing_required_field_names_the_field(self):
        self.assert_rejects({"niche": "x", "searches": 100}, "listings")

    def test_negative_and_zero_rejected(self):
        self.assert_rejects({"niche": "x", "searches": 0, "listings": 100, "competitor": "pod"}, "searches")
        self.assert_rejects({"niche": "x", "searches": 100, "listings": -5, "competitor": "pod"}, "listings")

    def test_unknown_competitor_rejected(self):
        self.assert_rejects({"niche": "x", "searches": 100, "listings": 100, "competitor": "etsy"}, "competitor")

    def test_bad_difficulty_rejected(self):
        self.assert_rejects(
            {"niche": "x", "searches": 100, "listings": 100, "competitor": "pod", "difficulty": 140}, "difficulty"
        )

    def test_thousands_separator_is_refused_not_misread(self):
        self.assert_rejects(
            {"niche": "x", "searches": "18.100", "listings": 100, "competitor": "pod"}, "tusindadskillelse"
        )

    def test_danish_headers_are_accepted(self):
        row = ns.score_row(
            {"navn": "x", "søgninger": 1000, "listings": 500, "sæsonsving": 2, "konkurrent": "håndlavet"}, 1
        )
        self.assertEqual(row["niche"], "x")
        self.assertEqual(row["competitor"], "handmade")
        self.assertEqual(row["swing"], 2.0)

    def test_unknown_column_is_ignored(self):
        row = ns.score_row(
            {"niche": "x", "searches": 1000, "listings": 500, "competitor": "pod", "kommentar": "hej"}, 1
        )
        self.assertEqual(row["listings"], 500.0)

    def test_empty_input_rejected(self):
        with self.assertRaises(ns.RowError):
            ns.scan([])


class Ranking(unittest.TestCase):
    def test_doc_niches_end_up_in_the_expected_order(self):
        rows = ns.scan(DOC_NICHES)
        self.assertEqual(
            [row["niche"] for row in rows],
            ["steampunk goggles", "steampunk corset", "steampunk top hat", "steampunk costume"],
        )
        self.assertEqual(rows[0]["verdict"], "grøn")
        self.assertEqual(rows[-1]["verdict"], "nej")

    def test_order_does_not_depend_on_input_order(self):
        forward = [row["niche"] for row in ns.scan(DOC_NICHES)]
        backward = [row["niche"] for row in ns.scan(list(reversed(DOC_NICHES)))]
        self.assertEqual(forward, backward)

    def test_ties_break_on_difficulty_then_name(self):
        rows = ns.scan(
            [
                {"niche": "b", "searches": 5_000, "listings": 3_000, "competitor": "pod", "difficulty": 50},
                {"niche": "a", "searches": 5_000, "listings": 3_000, "competitor": "pod", "difficulty": 50},
                {"niche": "c", "searches": 5_000, "listings": 3_000, "competitor": "pod", "difficulty": 10},
            ]
        )
        self.assertEqual([row["niche"] for row in rows], ["c", "a", "b"])


class Loading(unittest.TestCase):
    def test_csv_and_json_agree(self):
        with tempfile.TemporaryDirectory() as tmp:
            tmp = Path(tmp)
            csv_path = tmp / "kandidater.csv"
            with csv_path.open("w", newline="", encoding="utf-8") as handle:
                fields = ["niche", "searches", "listings", "swing", "competitor", "difficulty"]
                writer = csv.DictWriter(handle, fieldnames=fields, restval="")
                writer.writeheader()
                writer.writerows(DOC_NICHES)
            json_path = tmp / "kandidater.json"
            json_path.write_text(json.dumps({"niches": DOC_NICHES}), encoding="utf-8")

            from_csv = ns.scan(ns.load(csv_path))
            from_json = ns.scan(ns.load(json_path))
            self.assertEqual(
                [(row["niche"], row["score"]) for row in from_csv],
                [(row["niche"], row["score"]) for row in from_json],
            )

    def test_missing_file_is_an_error_not_an_empty_scan(self):
        with self.assertRaises(ns.RowError):
            ns.load(Path("findes-ikke.csv"))

    def test_repo_fixture_loads(self):
        fixture = ROOT / "docs/library/niche-kandidater-2026-11-02.csv"
        rows = ns.scan(ns.load(fixture))
        self.assertEqual(len(rows), 4)
        self.assertEqual(rows[0]["niche"], "steampunk goggles")


class Report(unittest.TestCase):
    def test_report_lists_every_candidate_and_the_hard_rule(self):
        text = ns.render(ns.scan(DOC_NICHES), "fixture")
        for row in DOC_NICHES:
            self.assertIn(row["niche"], text)
        self.assertIn("hårdt nej", text)
        self.assertIn("50 000", text)

    def test_cli_writes_report_and_returns_error_on_bad_input(self):
        with tempfile.TemporaryDirectory() as tmp:
            tmp = Path(tmp)
            report = tmp / "rapport.md"
            code = ns.main(["--input", str(ROOT / "docs/library/niche-kandidater-2026-11-02.csv"), "--report", str(report)])
            self.assertEqual(code, 0)
            self.assertTrue(report.exists())
            self.assertIn("steampunk goggles", report.read_text(encoding="utf-8"))

            stderr = io.StringIO()
            with contextlib.redirect_stderr(stderr), contextlib.redirect_stdout(io.StringIO()):
                bad = ns.main(["--input", str(tmp / "mangler.csv")])
            self.assertEqual(bad, 1)
            self.assertIn("findes ikke", stderr.getvalue())


if __name__ == "__main__":
    unittest.main()
