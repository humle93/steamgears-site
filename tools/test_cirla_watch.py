#!/usr/bin/env python3
"""Offline fixtures for tools/cirla_watch.py — ingen netværk, ingen credential."""

import datetime
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import cirla_watch  # noqa: E402


def status(**overrides):
    base = {
        "module": "cirla", "version": "7.79.0", "enabled": True,
        "capability": "sgap_cirla_run", "actions": ["status", "draft", "seo", "social"],
        "cron": {"hook": "sgap_cirla_daily", "next": "2026-11-12T18:56:49+00:00"},
        "ai_calls_today": {"used": 0, "limit": 5}, "pending_drafts": 0,
        "log": [{"at": "2026-11-11 22:32:16", "action": "toggle", "result": "on", "id": 0}],
        "queues_or_publishes": False,
    }
    base.update(overrides)
    return base


class CirlaWatchTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "series.json"

    def tearDown(self):
        self.tmp.cleanup()

    def at(self, text):
        return datetime.datetime.fromisoformat(text)

    def test_observation_reads_every_field(self):
        row = cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00"))
        self.assertTrue(row["enabled"])
        self.assertEqual(row["actions"], ["status", "draft", "seo", "social"])
        self.assertTrue(row["cron_scheduled"])
        self.assertEqual(row["ai_calls_used"], 0)
        self.assertEqual(row["pending_drafts"], 0)
        self.assertEqual(row["log_rows"], 1)
        self.assertEqual(row["log_newest"], {"at": "2026-11-11 22:32:16", "action": "toggle"})
        self.assertEqual(row["version"], "7.79.0")

    def test_observation_refuses_an_error_payload(self):
        with self.assertRaises(ValueError):
            cirla_watch.observation({"_error": "Ability not found"})

    def test_observation_without_cron_marks_it_missing(self):
        row = cirla_watch.observation(status(cron={"hook": "x", "next": None}),
                                      self.at("2026-11-12T03:00:00+00:00"))
        self.assertFalse(row["cron_scheduled"])
        self.assertIsNone(row["cron_next"])

    def test_append_keeps_one_row_per_day(self):
        first = cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00"))
        again = cirla_watch.observation(status(), self.at("2026-11-12T09:30:00+00:00"))
        rows = cirla_watch.append(self.path, first)
        rows = cirla_watch.append(self.path, again)
        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]["at"], "2026-11-12T09:30:00Z")

    def test_append_is_chronological_across_days(self):
        day2 = cirla_watch.observation(status(), self.at("2026-11-13T03:00:00+00:00"))
        day1 = cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00"))
        cirla_watch.append(self.path, day2)
        rows = cirla_watch.append(self.path, day1)
        self.assertEqual([row["at"][:10] for row in rows], ["2026-11-12", "2026-11-13"])

    def test_window_is_not_covered_before_24_hours(self):
        # Samme dag overskrives med vilje i append(), så vinduet måles på rækker direkte.
        rows = [cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00")),
                cirla_watch.observation(status(), self.at("2026-11-12T15:00:00+00:00"))]
        state = cirla_watch.window(rows)
        self.assertFalse(state["covered"])
        self.assertIn("12.0 af 24", state["reason"])

    def test_window_is_covered_after_24_hours_with_a_healthy_module(self):
        cirla_watch.append(self.path, cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00")))
        cirla_watch.append(self.path, cirla_watch.observation(status(), self.at("2026-11-13T03:00:00+00:00")))
        state = cirla_watch.window(cirla_watch.load_series(self.path))
        self.assertTrue(state["covered"])
        self.assertEqual(state["reason"], "")
        self.assertEqual(state["observations"], 2)

    def test_window_names_a_module_that_was_switched_off(self):
        cirla_watch.append(self.path, cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00")))
        cirla_watch.append(self.path, cirla_watch.observation(status(enabled=False, cron={"next": None}),
                                                             self.at("2026-11-13T03:00:00+00:00")))
        state = cirla_watch.window(cirla_watch.load_series(self.path))
        self.assertTrue(state["covered"])
        self.assertIn("slukket", state["reason"])

    def test_window_names_spent_ai_budget(self):
        cirla_watch.append(self.path, cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00")))
        cirla_watch.append(self.path, cirla_watch.observation(status(ai_calls_today={"used": 3, "limit": 5}),
                                                             self.at("2026-11-13T03:00:00+00:00")))
        state = cirla_watch.window(cirla_watch.load_series(self.path))
        self.assertIn("forbrug", state["reason"])

    def test_missing_or_broken_file_is_an_empty_series(self):
        self.assertEqual(cirla_watch.load_series(self.path), [])
        self.path.write_text("{ikke json", encoding="utf-8")
        self.assertEqual(cirla_watch.load_series(self.path), [])
        self.path.write_text(json.dumps({"not": "a list"}), encoding="utf-8")
        self.assertEqual(cirla_watch.load_series(self.path), [])

    def test_parse_time_accepts_site_and_iso_formats(self):
        self.assertIsNotNone(cirla_watch.parse_time("2026-11-12T18:56:49+00:00"))
        self.assertIsNotNone(cirla_watch.parse_time("2026-11-12T18:56:49Z"))
        self.assertIsNotNone(cirla_watch.parse_time("2026-11-12 18:56:49"))
        self.assertIsNone(cirla_watch.parse_time(""))
        self.assertIsNone(cirla_watch.parse_time(None))
        self.assertIsNone(cirla_watch.parse_time("i går"))

    def test_render_row_names_the_state(self):
        row = cirla_watch.observation(status(), self.at("2026-11-12T03:00:00+00:00"))
        text = cirla_watch.render_row(row)
        for part in ("tændt", "cron planlagt", "AI 0/5", "kladder 0", "v7.79.0"):
            self.assertIn(part, text)


if __name__ == "__main__":
    unittest.main(verbosity=2)
