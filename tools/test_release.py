#!/usr/bin/env python3
"""Tests for release.py — kører uden git-historik, netværk og GitHub-adgang.

    python3 tools/test_release.py

Preflighten er den eneste ting der står mellem et uheld og et release-tag, og
den fejler kun ved at sige nej. Derfor prøves hver regel for sig: en zip der
ikke matcher headeren skal afvises, et tag der findes skal afvises, og et rødt
værn skal afvises — også når alt andet ser rigtigt ud.
"""

from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import release as rel  # noqa: E402


def state(**kwargs) -> rel.State:
    base = dict(
        header_version="7.65.1",
        zip_versions=["7.65.1"],
        tags=[],
        dirty=False,
        branch="main",
    )
    base.update(kwargs)
    return rel.State(**base)


class VersionTests(unittest.TestCase):
    def test_header_version_reads_plugin_header(self):
        php = "<?php\n/**\n * Plugin Name: X\n * Version: 7.65.1\n */\n"
        self.assertEqual(rel.parse_header_version(php), "7.65.1")

    def test_header_version_ignores_star_and_indent(self):
        self.assertEqual(rel.parse_header_version("* Version: 1.2.3-beta"), "1.2.3-beta")

    def test_header_version_missing_is_none(self):
        self.assertIsNone(rel.parse_header_version("<?php // ingen header"))

    def test_zip_version_reads_filename(self):
        self.assertEqual(rel.zip_version("plugins/steamgears-autopilot-v7.65.1.zip"), "7.65.1")

    def test_zip_version_rejects_other_files(self):
        self.assertIsNone(rel.zip_version("plugins/andet-plugin.zip"))

    def test_tag_name_prefixes_v(self):
        self.assertEqual(rel.tag_name("7.65.1"), "v7.65.1")


class PreflightTests(unittest.TestCase):
    def test_clean_state_passes(self):
        report = rel.check_state(state())
        self.assertTrue(report.ok, report.problems)

    def test_dirty_worktree_is_refused(self):
        report = rel.check_state(state(dirty=True))
        self.assertTrue(any("rent" in p for p in report.problems))

    def test_wrong_branch_warns_but_does_not_block(self):
        report = rel.check_state(state(branch="freebuff/xyz"))
        self.assertTrue(report.ok)
        self.assertTrue(any("main" in w for w in report.warnings))

    def test_wrong_branch_blocks_a_tag(self):
        report = rel.check_state(state(branch="freebuff/xyz"), require_main=True)
        self.assertTrue(any("main" in p for p in report.problems))

    def test_main_passes_with_require_main(self):
        report = rel.check_state(state(branch="main"), require_main=True)
        self.assertTrue(report.ok, report.problems)

    def test_missing_zip_is_refused(self):
        report = rel.check_state(state(zip_versions=[]))
        self.assertTrue(any("ingen plugin-zip" in p for p in report.problems))

    def test_two_zips_are_refused(self):
        report = rel.check_state(state(zip_versions=["7.65.1", "7.64.9"]))
        self.assertTrue(any("2 zips" in p for p in report.problems))

    def test_zip_must_match_header(self):
        report = rel.check_state(state(header_version="7.65.1", zip_versions=["7.64.9"]))
        self.assertTrue(any("matcher ikke" in p for p in report.problems))

    def test_existing_tag_is_refused(self):
        report = rel.check_state(state(tags=["v7.65.1"]))
        self.assertTrue(any("findes allerede" in p for p in report.problems))

    def test_other_tag_is_fine(self):
        report = rel.check_state(state(tags=["v7.64.9"]))
        self.assertTrue(report.ok, report.problems)

    def test_confirm_must_match(self):
        report = rel.check_state(state(confirm="7.64.9"))
        self.assertTrue(any("--confirm" in p for p in report.problems))

    def test_confirm_matching_passes(self):
        report = rel.check_state(state(confirm="7.65.1"))
        self.assertTrue(report.ok, report.problems)

    def test_behind_origin_is_refused(self):
        report = rel.check_state(state(behind=2))
        self.assertTrue(any("bagud" in p for p in report.problems))

    def test_unpushed_commits_only_warn(self):
        report = rel.check_state(state(ahead=3))
        self.assertTrue(report.ok)
        self.assertTrue(any("ikke pushet" in w for w in report.warnings))


class NotesTests(unittest.TestCase):
    def test_notes_keep_subjects_and_drop_hashes(self):
        notes = rel.release_notes(["abc123 Ret en fejl", "def456 Tilføj en test"])
        self.assertEqual(notes, "- Ret en fejl\n- Tilføj en test")

    def test_notes_skip_bot_and_merge_commits(self):
        notes = rel.release_notes([
            "aaa Merge branch 'x'",
            "bbb Merge pull request #12 from y",
            "ccc Revert \"noget\"",
            "ddd Genereret af Codebuff",
            "eee Rigtig ændring",
        ])
        self.assertEqual(notes, "- Rigtig ændring")

    def test_notes_fall_back_when_empty(self):
        self.assertEqual(rel.release_notes([]), "- Ingen ændringer siden sidste tag.")


class ProtectionTests(unittest.TestCase):
    def test_all_settings_wait_for_a_plan(self):
        slaaet_til, mangler = rel.protection_report(can_enable=False)
        self.assertEqual(slaaet_til, [])
        self.assertEqual(len(mangler), len(rel.PROTECTION_SETTINGS))

    def test_settings_can_be_enabled_when_github_allows(self):
        slaaet_til, mangler = rel.protection_report(can_enable=True)
        self.assertTrue(slaaet_til)
        self.assertTrue(any("Pro/Team" in m for m in mangler))

    def test_branch_protection_is_named(self):
        _, mangler = rel.protection_report(can_enable=False)
        self.assertTrue(any("Branch protection" in m for m in mangler))


if __name__ == "__main__":
    unittest.main(verbosity=2)
