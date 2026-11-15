#!/usr/bin/env python3
"""Offline-tests for tools/payment_methods.py.

Alle fixtures er små, håndskrevne svar fra `novamira/execute-php`, så testen kører uden
token og uden netværk. Det der prøves: skellet mellem en ægte uvalgbar gateway og en
Stripe-skygge under OCS, samt at en manglende måling ikke bliver til et falsk PASS.

    python3 tools/test_payment_methods.py
"""

from __future__ import annotations

import io
import json
import sys
import tempfile
import unittest
from contextlib import redirect_stdout
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import payment_methods as pm  # noqa: E402


def gateway(gid, cls="WC_Gateway_BACS", enabled=True, available=True):
    return {"id": gid, "class": cls, "enabled": enabled, "available": available,
            "needs_setup": False}


NEUTRAL = {"required": [], "expected": {}, "enabled_gateways": []}


def payload(gateways=None, checkout=None, ocs=True, caps=None, bacs_configured=True):
    return {
        "wc": "11.2.2",
        "currency": "DKK",
        "base_country": "DK",
        "ocs": ocs,
        "parent_class": "WC_Stripe_OCS_Payment_Gateway" if ocs else "WC_Stripe_Payment_Gateway",
        "checkout_available": ["stripe", "bacs"] if checkout is None else checkout,
        "bacs_configured": bacs_configured,
        "capabilities": caps if caps is not None else {
            "amazon_pay_payments": "active",
            "klarna_payments": "active",
            "link_payments": "active",
            "card_payments": "active",
        },
        "gateways": gateways if gateways is not None else [
            gateway("stripe", "WC_Stripe_OCS_Payment_Gateway", True, True),
            gateway("stripe_amazon_pay", "WC_Stripe_UPE_Payment_Method_Amazon_Pay", True, False),
            gateway("stripe_klarna", "WC_Stripe_UPE_Payment_Method_Klarna", True, False),
            gateway("stripe_link", "WC_Stripe_UPE_Payment_Method_Link", True, False),
            gateway("bacs", "WC_Gateway_BACS", True, True),
            gateway("cod", "WC_Gateway_COD", False, False),
        ],
    }


def judge(data, baseline=None):
    """Kør dommen med en neutral baseline, så de gamle fixtures ikke blandes ind."""
    return pm.judge(data, NEUTRAL if baseline is None else baseline)


def named(checks, krav):
    return next(c for c in checks if c["krav"] == krav)


class JudgementTests(unittest.TestCase):
    def test_ocs_shadows_are_not_failures(self):
        checks = judge(payload())
        self.assertEqual(pm.failed(checks), 0)
        self.assertEqual(named(checks, "ingen tændt gateway er uvalgbar")["status"], "PASS")
        self.assertIn("stripe_klarna", named(checks,
                       "Stripe-metoder uden egen gateway er klassificeret som skygger")["maalt"])

    def test_real_dead_gateway_fails(self):
        data = payload()
        data["gateways"].append(gateway("mobilepay", "WC_Gateway_MobilePay", True, False))
        checks = judge(data)
        self.assertEqual(pm.failed(checks), 1)
        row = named(checks, "ingen tændt gateway er uvalgbar")
        self.assertEqual(row["status"], "FAIL")
        self.assertIn("mobilepay", row["maalt"])

    def test_shadow_without_ocs_is_a_failure(self):
        """Uden OCS er den samme tilstand en ægte fejl — derfor må klassen ikke redde den."""
        data = payload(ocs=False)
        checks = judge(data)
        self.assertEqual(pm.failed(checks), 1)
        self.assertIn("stripe_klarna", named(checks, "ingen tændt gateway er uvalgbar")["maalt"])

    def test_empty_checkout_fails(self):
        data = payload()
        data["checkout_available"] = []
        checks = judge(data)
        self.assertEqual(named(checks, "kassen tilbyder mindst én betalingsmetode")["status"], "FAIL")
        self.assertEqual(named(checks, "kortbetaling er tilgængelig")["status"], "FAIL")

    def test_missing_stripe_in_checkout_fails(self):
        data = payload(checkout=["bacs"])
        checks = judge(data)
        self.assertEqual(named(checks, "kortbetaling er tilgængelig")["status"], "FAIL")
        self.assertEqual(pm.failed(checks), 1)

    def test_inactive_capability_warns_but_does_not_fail(self):
        caps = {"amazon_pay_payments": "inactive", "klarna_payments": "active",
                "link_payments": "active", "card_payments": "active"}
        checks = judge(payload(caps=caps))
        row = named(checks, "stripe_amazon_pay har aktiv Stripe-evne (amazon_pay_payments)")
        self.assertEqual(row["status"], "WARN")
        self.assertEqual(row["maalt"], "inactive")
        self.assertEqual(pm.failed(checks), 0)

    def test_unmeasured_capability_is_warned_not_guessed(self):
        checks = judge(payload(caps={}))
        row = named(checks, "stripe_klarna har aktiv Stripe-evne (klarna_payments)")
        self.assertEqual(row["status"], "WARN")
        self.assertEqual(row["maalt"], "ikke målt")

    def test_summary_counts(self):
        checks = judge(payload())
        summary = named(checks, "opsummering")["maalt"]
        self.assertIn("5 tændt", summary)
        self.assertIn("2 tilgængelig", summary)
        self.assertIn("3 skygger", summary)


class BaselineTests(unittest.TestCase):
    """Baseline skal fange en metode der forsvinder — ellers er checken blind."""

    def test_missing_expected_method_warns_with_its_name(self):
        data = payload(checkout=["stripe"])
        data["gateways"] = [g for g in data["gateways"] if g["id"] != "bacs"]
        baseline = {"required": ["stripe"], "expected": {"bacs": {"grund": "slået fra"}},
                    "enabled_gateways": ["stripe"]}
        checks = judge(data, baseline)
        row = named(checks, "betalingsmetode forsvundet fra kassen")
        self.assertEqual(row["status"], "WARN")
        self.assertIn("bacs", row["maalt"])
        self.assertEqual(pm.failed(checks), 0)

    def test_missing_required_method_fails_with_its_name(self):
        data = payload(checkout=["bacs"])
        baseline = {"required": ["stripe"], "expected": {}, "enabled_gateways": []}
        checks = judge(data, baseline)
        row = named(checks, "kassen opfylder baseline (krævede metoder)")
        self.assertEqual(row["status"], "FAIL")
        self.assertIn("stripe", row["maalt"])
        # To FAIL: både »kortbetaling er tilgængelig« og baseline-kravet kræver stripe.
        self.assertEqual(pm.failed(checks), 2)

    def test_previously_enabled_gateway_that_turned_off_warns(self):
        data = payload(checkout=["stripe"])
        data["gateways"] = [g for g in data["gateways"] if g["id"] != "bacs"]
        baseline = {"required": ["stripe"], "expected": {},
                    "enabled_gateways": ["stripe", "bacs"]}
        checks = judge(data, baseline)
        row = named(checks, "tidligere tændt gateway er ikke længere tændt")
        self.assertEqual(row["status"], "WARN")
        self.assertIn("bacs", row["maalt"])

    def test_missing_baseline_is_a_failure_not_a_pass(self):
        checks = pm.judge(payload(), {}, "filen findes ikke")
        row = named(checks, "baseline for betalingsmetoder findes")
        self.assertEqual(row["status"], "FAIL")
        self.assertIn("findes ikke", row["maalt"])

    def test_broken_baseline_file_is_reported(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "baseline.json"
            path.write_text("{}", encoding="utf-8")
            data, error = pm.load_baseline(path)
        self.assertEqual(data, {})
        self.assertIn("mangler", error)

    def test_bacs_enabled_without_bank_details_fails(self):
        data = payload(bacs_configured=False)
        checks = judge(data)
        row = named(checks, "bacs er tændt med bankoplysninger")
        self.assertEqual(row["status"], "FAIL")
        self.assertIn("IBAN", row["maalt"])

    def test_configured_bacs_passes(self):
        checks = judge(payload())
        self.assertEqual(named(checks, "bacs er tændt med bankoplysninger")["status"], "PASS")


class CommandLineTests(unittest.TestCase):
    def _run(self, *args):
        buf = io.StringIO()
        with redirect_stdout(buf):
            code = pm.main(list(args))
        return code, buf.getvalue()

    def test_from_file_prints_and_exits_zero(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "svar.json"
            path.write_text(json.dumps(payload()), encoding="utf-8")
            code, out = self._run("--from-file", str(path))
        self.assertEqual(code, 0)
        self.assertIn("[OK]", out)
        self.assertIn("11.2.2", out)

    def test_from_file_with_real_error_exits_one(self):
        data = payload()
        data["gateways"].append(gateway("mobilepay", "WC_Gateway_MobilePay", True, False))
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "svar.json"
            path.write_text(json.dumps(data), encoding="utf-8")
            code, out = self._run("--from-file", str(path))
        self.assertEqual(code, 1)
        self.assertIn("[FEJL]", out)

    def test_json_output_is_parseable(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "svar.json"
            path.write_text(json.dumps(payload()), encoding="utf-8")
            code, out = self._run("--from-file", str(path), "--json")
        self.assertEqual(code, 0)
        parsed = json.loads(out)
        baseline, error = pm.load_baseline()
        self.assertEqual(len(parsed["checks"]), len(pm.judge(payload(), baseline, error)))

    def test_broken_file_is_reported(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "svar.json"
            path.write_text("{ikke json", encoding="utf-8")
            code, out = self._run("--from-file", str(path))
        self.assertEqual(code, 1)
        self.assertIn("kunne ikke læse", out)

    def test_missing_file_is_reported(self):
        code, out = self._run("--from-file", "/findes/ikke.json")
        self.assertEqual(code, 1)
        self.assertIn("kunne ikke læse", out)


if __name__ == "__main__":
    unittest.main(verbosity=2)
