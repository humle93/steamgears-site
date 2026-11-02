#!/usr/bin/env python3
"""Test for niche_bridge.py — kun grønne nicher, ingen opdigtede URL'er, altid draft.

Kør: python3 -m unittest discover -s tests -v
"""

import contextlib
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

import niche_bridge as bridge  # noqa: E402
import niche_scan  # noqa: E402

FIXTURE = ROOT / "docs/library/niche-kandidater-2026-11-02.csv"
EXAMPLE_URL = "https://example.com/goggles"
# sha256("pinterest|https://example.com/goggles|Steampunk goggles") — spejler SGAP_Queue::add().
EXPECTED_FINGERPRINT = "d0d518e6f58bde85e915260b9409e8be15aa710f35cb0acd289f64bc1b29258b"


def ranked(rows=None):
    return niche_scan.scan(rows or niche_scan.load(FIXTURE))


def write(path: Path, text: str) -> Path:
    path.write_text(text, encoding="utf-8")
    return path


class OnlyGreen(unittest.TestCase):
    def test_default_is_green_only(self):
        built, skipped = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        self.assertEqual(len(built), 1)
        self.assertEqual(built[0]["attribution"]["niche"], "steampunk goggles")
        self.assertEqual(built[0]["attribution"]["verdict"], "grøn")
        self.assertEqual(skipped, [])

    def test_yellow_can_be_included_and_stays_below_green(self):
        links = {"steampunk goggles": EXAMPLE_URL, "steampunk corset": "https://example.com/corset"}
        built, _ = bridge.drafts(ranked(), links, ["pinterest"], include_yellow=True)
        self.assertEqual([item["attribution"]["niche"] for item in built],
                         ["steampunk goggles", "steampunk corset"])

    def test_hard_no_is_never_drafted(self):
        links = {row["niche"]: f"https://example.com/{i}" for i, row in enumerate(ranked())}
        built, _ = bridge.drafts(ranked(), links, ["pinterest"], include_yellow=True)
        self.assertNotIn("steampunk costume", [item["attribution"]["niche"] for item in built])

    def test_score_is_carried_over_unchanged(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        self.assertEqual(built[0]["attribution"]["score"], ranked()[0]["score"])


class NoInventedValues(unittest.TestCase):
    def test_missing_url_is_skipped_with_a_reason(self):
        built, skipped = bridge.drafts(ranked(), {}, ["pinterest"])
        self.assertEqual(built, [])
        self.assertEqual(len(skipped), 1)
        self.assertIn("ingen URL", skipped[0]["reason"])

    def test_non_http_url_is_refused(self):
        for bad in ("ftp://example.com/x", "javascript:alert(1)", "example.com/uden-skema", "https://"):
            built, skipped = bridge.drafts(ranked(), {"steampunk goggles": bad}, ["pinterest"])
            self.assertEqual(built, [], bad)
            self.assertIn("http(s)", skipped[0]["reason"])

    def test_payload_has_no_invented_copy(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        payload = built[0]["payload"]
        self.assertEqual(payload["body"], "")
        self.assertEqual(payload["image_url"], "")
        self.assertEqual(payload["title"], "Steampunk goggles")


class PayloadShape(unittest.TestCase):
    def test_keys_match_create_queue(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        self.assertEqual(
            sorted(built[0]["payload"]),
            sorted(["post_id", "channel", "angle", "content_type", "format", "destination_url",
                    "title", "body", "image_url", "status", "scheduled_at"]),
        )

    def test_status_is_always_draft(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest", "facebook"])
        self.assertEqual({item["payload"]["status"] for item in built}, {"draft"})

    def test_defaults_match_the_manual_queue_post_ability(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        payload = built[0]["payload"]
        self.assertEqual((payload["content_type"], payload["format"]), ("custom", "post"))
        self.assertEqual(payload["scheduled_at"], None)

    def test_fingerprint_matches_the_queue_dedup_key(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        self.assertEqual(built[0]["fingerprint"], EXPECTED_FINGERPRINT)


class Channels(unittest.TestCase):
    def test_one_row_per_channel(self):
        built, _ = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL},
                                 ["pinterest", "facebook", "instagram"])
        self.assertEqual([item["payload"]["channel"] for item in built], ["pinterest", "facebook", "instagram"])

    def test_require_image_drops_pinterest_and_instagram(self):
        built, skipped = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL},
                                       ["pinterest", "facebook"], require_image=True)
        self.assertEqual([item["payload"]["channel"] for item in built], ["facebook"])
        self.assertIn("kræver foto", skipped[0]["reason"])

    def test_unknown_channel_is_an_error(self):
        with self.assertRaises(bridge.BridgeError) as caught:
            bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, ["myspace"])
        self.assertIn("Ukendte kanaler", str(caught.exception))

    def test_no_channel_is_an_error(self):
        with self.assertRaises(bridge.BridgeError):
            bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL}, [])


class Duplicates(unittest.TestCase):
    def test_same_channel_url_and_title_is_only_drafted_once(self):
        row = {"niche": "steampunk goggles", "score": 80.8, "verdict": "grøn"}
        built, skipped = bridge.drafts([row, dict(row)], {"steampunk goggles": EXAMPLE_URL}, ["pinterest"])
        self.assertEqual(len(built), 1)
        self.assertIn("duplikat", skipped[0]["reason"])

    def test_different_channels_are_not_duplicates(self):
        built, skipped = bridge.drafts(ranked(), {"steampunk goggles": EXAMPLE_URL},
                                       ["pinterest", "facebook"])
        self.assertEqual((len(built), skipped), (2, []))

    def test_two_niches_may_share_a_url(self):
        links = {"steampunk goggles": EXAMPLE_URL, "steampunk corset": EXAMPLE_URL}
        built, _ = bridge.drafts(ranked(), links, ["pinterest"], include_yellow=True)
        self.assertEqual(len(built), 2)


class LinkFile(unittest.TestCase):
    def test_csv_and_json_agree(self):
        with tempfile.TemporaryDirectory() as tmp:
            tmp = Path(tmp)
            csv_path = write(tmp / "links.csv", "niche,url\nsteampunk goggles,https://example.com/goggles\n")
            json_path = write(tmp / "links.json", json.dumps({"steampunk goggles": "https://example.com/goggles"}))
            list_path = write(tmp / "links-list.json",
                              json.dumps({"links": [{"niche": "steampunk goggles", "url": "https://example.com/goggles"}]}))
            loaded = [bridge.load_links(path) for path in (csv_path, json_path, list_path)]
            self.assertEqual(loaded[0], loaded[1])
            self.assertEqual(loaded[0], loaded[2])

    def test_matching_ignores_case_and_extra_spaces(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = write(Path(tmp) / "links.csv", "niche,url\n  Steampunk   Goggles ,https://example.com/goggles\n")
            links = bridge.load_links(path)
        built, _ = bridge.drafts(ranked(), links, ["pinterest"])
        self.assertEqual(len(built), 1)

    def test_missing_file_is_an_error(self):
        with self.assertRaises(bridge.BridgeError):
            bridge.load_links(Path("findes-ikke.csv"))

    def test_csv_without_niche_column_is_an_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = write(Path(tmp) / "links.csv", "navn;adresse\na;b\n")
            with self.assertRaises(bridge.BridgeError):
                bridge.load_links(path)

    def test_empty_link_file_is_an_error(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = write(Path(tmp) / "links.csv", "niche,url\n,https://example.com/x\n")
            with self.assertRaises(bridge.BridgeError):
                bridge.load_links(path)


class Helpers(unittest.TestCase):
    def test_clean_key_mirrors_sanitize_key(self):
        self.assertEqual(bridge.clean_key("Pin-Terest!  ", "angle_a", 20), "pin-terest")
        self.assertEqual(bridge.clean_key("", "angle_a", 20), "angle_a")
        self.assertEqual(bridge.clean_key("a" * 60, "", 20), "a" * 20)

    def test_valid_url(self):
        self.assertTrue(bridge.valid_url("https://steamgears.eu/a-b?x=1"))
        self.assertTrue(bridge.valid_url("http://example.com"))
        self.assertFalse(bridge.valid_url("ikke en url"))
        self.assertFalse(bridge.valid_url("ftp://example.com/x"))

    def test_title_for_only_capitalises(self):
        self.assertEqual(bridge.title_for("steampunk goggles"), "Steampunk goggles")
        self.assertEqual(bridge.title_for("  "), "")


class Cli(unittest.TestCase):
    def run_cli(self, argv):
        out, err = io.StringIO(), io.StringIO()
        with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            code = bridge.main(argv)
        return code, out.getvalue(), err.getvalue()

    def test_writes_payloads_and_plan(self):
        with tempfile.TemporaryDirectory() as tmp:
            tmp = Path(tmp)
            links = write(tmp / "links.csv", "niche,url\nsteampunk goggles,https://example.com/goggles\n")
            code, _, err = self.run_cli(["--input", str(FIXTURE), "--links", str(links),
                                         "--out", str(tmp / "drafts.json"), "--plan", str(tmp / "plan.md")])
            self.assertEqual(code, 0)
            data = json.loads((tmp / "drafts.json").read_text(encoding="utf-8"))
            self.assertEqual(data["count"], 1)
            self.assertEqual(data["drafts"][0]["payload"]["status"], "draft")
            plan = (tmp / "plan.md").read_text(encoding="utf-8")
            self.assertIn("status: draft", plan)
            self.assertIn("steampunk goggles", plan)
            self.assertIn("OK", err)

    def test_plan_does_not_leak_absolute_paths(self):
        with tempfile.TemporaryDirectory() as tmp:
            links = write(Path(tmp) / "links.csv", "niche,url\nsteampunk goggles,https://example.com/goggles\n")
            code, out, _ = self.run_cli(["--input", str(FIXTURE), "--links", str(links)])
            self.assertEqual(code, 0)
            self.assertNotIn(str(Path(tmp)), out)
            self.assertIn("docs/library/niche-kandidater-2026-11-02.csv", out)

    def test_plan_explains_the_skips(self):
        with tempfile.TemporaryDirectory() as tmp:
            tmp = Path(tmp)
            # Linkfilen kender kun en niche uden for kandidatlisten, så den grønne kandidat mangler URL.
            links = write(tmp / "links.csv", "niche,url\nnoget andet,https://example.com/x\n")
            code, _, _ = self.run_cli(["--input", str(FIXTURE), "--links", str(links),
                                       "--plan", str(tmp / "plan.md")])
            self.assertEqual(code, 0)
            plan = (tmp / "plan.md").read_text(encoding="utf-8")
            self.assertIn("ingen URL i linkfilen", plan)
            self.assertIn("Ingen draft-rækker", plan)

    def test_two_runs_are_byte_identical(self):
        with tempfile.TemporaryDirectory() as tmp:
            tmp = Path(tmp)
            links = write(tmp / "links.csv", "niche,url\nsteampunk goggles,https://example.com/goggles\n")
            outputs = []
            for name in ("a.json", "b.json"):
                self.run_cli(["--input", str(FIXTURE), "--links", str(links), "--out", str(tmp / name)])
                outputs.append((tmp / name).read_bytes())
            self.assertEqual(outputs[0], outputs[1])

    def test_bad_input_returns_one(self):
        with tempfile.TemporaryDirectory() as tmp:
            links = write(Path(tmp) / "links.csv", "niche,url\nx,https://example.com/x\n")
            code, _, err = self.run_cli(["--input", str(Path(tmp) / "mangler.csv"), "--links", str(links)])
            self.assertEqual(code, 1)
            self.assertIn("findes ikke", err)

    def test_unknown_channel_returns_one(self):
        with tempfile.TemporaryDirectory() as tmp:
            links = write(Path(tmp) / "links.csv", "niche,url\nsteampunk goggles,https://example.com/goggles\n")
            code, _, err = self.run_cli(["--input", str(FIXTURE), "--links", str(links), "--channels", "myspace"])
            self.assertEqual(code, 1)
            self.assertIn("Ukendte kanaler", err)


if __name__ == "__main__":
    unittest.main()
