#!/usr/bin/env python3
"""payment_methods.py — mål hvilke betalingsmetoder butikken faktisk tilbyder i kassen.

Punkt C i `docs/roadmap.md` §4d lød: »3 Stripe-metoder er slået til men utilgængelige
(`amazon_pay`, `klarna`, `link`)«. Målt 12/11 er den formulering forkert, og det kan kun
vises med tal fra det levende site, fordi svaret afhænger af **hvilken kassetype Stripe
kører**:

  * Kører butikken Stripes nye kasse (OCS, `WC_Stripe_OCS_Payment_Gateway`), så er
    `stripe_klarna`, `stripe_link` osv. **skygger**: de arver `enabled` fra Stripe-kassen,
    deres egen indstilling kan ikke slås fra (målt: en skrivning til
    `woocommerce_stripe_klarna_settings` ændrer optionen, men ikke gatewayens tilstand), og
    `is_available()` er falsk, fordi metoderne vises **inde i** den ene `stripe`-gateway.
    En skygge er derfor ikke en fejl — den er en dublet i admin-listen.
  * Er gatewayen derimod en selvstændig gateway, er `enabled && !available` en ægte fejl:
    kunden kan ikke vælge den, selv om butikken tror den er tændt.

Værktøjet skelner de to og fejler kun på det ægte. Det læser også Stripes kontoevner
(`capabilities`), så en skygge uden aktiv evne bliver meldt som **WARN** — dér er der
noget ejeren skal slå til i Stripe Dashboard.

Kør:

    python3 tools/payment_methods.py                 # mål det levende site
    python3 tools/payment_methods.py --json          # samme, som JSON
    python3 tools/payment_methods.py --from-file x   # døm et gemt svar offline

Exit: 0 = ingen ægte fejl · 1 = en ægte fejl eller manglende måling · 2 = ingen credential.
Tokenet printes aldrig.
"""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RESOURCE = os.environ.get("NOVAMIRA_RESOURCE") or "https://steamgears.eu/wp-json/mcp/novamira-oauth"

# De metoder §4d C handlede om, med den Stripe-evne der afgør om de kan tilbydes.
WATCHED = {
    "stripe_amazon_pay": "amazon_pay_payments",
    "stripe_klarna": "klarna_payments",
    "stripe_link": "link_payments",
}
SHADOW_PREFIX = "WC_Stripe_UPE_Payment_Method"

PHP = r'''
$all = WC()->payment_gateways()->payment_gateways();
$rows = [];
foreach ($all as $id => $g) {
  $rows[] = [
    'id'          => $id,
    'class'       => get_class($g),
    'enabled'     => ($g->enabled === 'yes'),
    'available'   => (bool) $g->is_available(),
    'needs_setup' => method_exists($g, 'needs_setup') ? (bool) $g->needs_setup() : null,
  ];
}
$caps = [];
try {
  $data = WC_Stripe::get_instance()->account->get_cached_account_data();
  foreach ($data['capabilities'] ?? [] as $name => $state) {
    if (in_array($name, ['amazon_pay_payments','klarna_payments','link_payments','card_payments'], true)) {
      $caps[$name] = $state;
    }
  }
} catch (Throwable $e) {
  $caps = ['_fejl' => $e->getMessage()];
}
return [
  'wc'                => defined('WC_VERSION') ? WC_VERSION : '?',
  'currency'          => get_woocommerce_currency(),
  'base_country'      => WC()->countries->get_base_country(),
  'ocs'               => isset($all['stripe']) && str_contains(get_class($all['stripe']), 'OCS'),
  'parent_class'      => isset($all['stripe']) ? get_class($all['stripe']) : '',
  'checkout_available'=> array_keys(WC()->payment_gateways()->get_available_payment_gateways()),
  'capabilities'      => $caps,
  'gateways'          => $rows,
];
'''


# ------------------------------------------------------------------ vurdering

def is_shadow(gateway: dict, payload: dict) -> bool:
    """En skygge er en Stripe-metode der vises inde i den ene kasse, ikke som egen gateway."""
    if not payload.get("ocs"):
        return False
    return (gateway.get("enabled")
            and not gateway.get("available")
            and str(gateway.get("class", "")).startswith(SHADOW_PREFIX))


def judge(payload: dict) -> list[dict]:
    """Returnér én række pr. krav. `status` er PASS, WARN eller FAIL."""
    checks: list[dict] = []

    def add(name: str, status: str, detail) -> None:
        checks.append({"krav": name, "status": status, "maalt": detail})

    gateways = payload.get("gateways") or []
    enabled = [g for g in gateways if g.get("enabled")]
    available = [g for g in gateways if g.get("available")]
    checkout = payload.get("checkout_available") or []

    add("kassen tilbyder mindst én betalingsmetode", "PASS" if checkout else "FAIL",
        ", ".join(checkout) or "ingen")

    dead = [g["id"] for g in enabled if not g.get("available") and not is_shadow(g, payload)]
    add("ingen tændt gateway er uvalgbar", "PASS" if not dead else "FAIL",
        "alle tændte gateways kan vælges" if not dead else "uvalgbar: " + ", ".join(dead))

    add("kortbetaling er tilgængelig", "PASS" if "stripe" in checkout else "FAIL",
        "stripe" if "stripe" in checkout else "stripe mangler i kassen")

    shadows = [g["id"] for g in enabled if is_shadow(g, payload)]
    if payload.get("ocs"):
        add("Stripe-metoder uden egen gateway er klassificeret som skygger", "PASS",
            ", ".join(shadows) or "ingen")
        caps = payload.get("capabilities") or {}
        for gateway_id, capability in WATCHED.items():
            if gateway_id not in shadows:
                continue
            state = caps.get(capability)
            add(f"{gateway_id} har aktiv Stripe-evne ({capability})",
                "PASS" if state == "active" else "WARN",
                state or "ikke målt")
    else:
        add("butikken kører ikke Stripe-OCS", "PASS", payload.get("parent_class") or "ukendt")

    add("opsummering", "PASS",
        f"{len(enabled)} tændt · {len(available)} tilgængelig i kassen · {len(shadows)} skygger")
    return checks


def failed(checks: list[dict]) -> int:
    return sum(1 for c in checks if c["status"] == "FAIL")


def verdict(checks: list[dict]) -> str:
    return "OK" if not failed(checks) else "FEJL"


# ------------------------------------------------------------------ det levende site

def local_token() -> str:
    token = os.environ.get("NOVAMIRA_ACCESS_TOKEN") or ""
    if token:
        return token
    try:
        sys.path.insert(0, str(ROOT / "tools"))
        from mcp_keepalive import TOKEN_FILE, read_json
    except Exception:  # noqa: BLE001 — uden helper er der ingen credential
        return ""
    try:
        return str(read_json(TOKEN_FILE).get("access_token") or "")
    except Exception:  # noqa: BLE001
        return ""


def measure(access_token: str) -> dict:
    """Ét execute-php-kald gennem Novamira; skemaet læses fra tools/list."""
    sys.path.insert(0, str(ROOT / "tools"))
    from mcp_keepalive import post  # noqa: PLC0415 — kun nødvendig i live-tilstand

    status, body, headers = post(
        RESOURCE,
        {"jsonrpc": "2.0", "id": 1, "method": "initialize",
         "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                    "clientInfo": {"name": "steamgears-payment-methods", "version": "1.0"}}},
        token=access_token)
    if status != 200 or not isinstance(body, dict) or "result" not in body:
        raise RuntimeError(f"initialize svarede HTTP {status}: {str(body)[:160]}")
    session = headers.get("Mcp-Session-Id") or headers.get("mcp-session-id")
    extra = {"Mcp-Session-Id": session} if session else None
    post(RESOURCE, {"jsonrpc": "2.0", "method": "notifications/initialized"}, token=access_token, extra=extra)

    status, body, _ = post(RESOURCE, {"jsonrpc": "2.0", "id": 2, "method": "tools/list", "params": {}},
                           token=access_token, extra=extra)
    tools = (body.get("result") or {}).get("tools") if isinstance(body, dict) else None
    execute = next((t for t in tools or [] if str(t.get("name", "")).endswith("execute-ability")), None)
    if execute is None:
        raise RuntimeError("fladen har intet execute-ability")
    props = (execute.get("inputSchema") or {}).get("properties") or {}
    ability_key = "ability_name" if "ability_name" in props else "ability"
    input_key = "parameters" if "parameters" in props else "input"

    status, body, _ = post(
        RESOURCE,
        {"jsonrpc": "2.0", "id": 3, "method": "tools/call",
         "params": {"name": execute["name"],
                    "arguments": {ability_key: "novamira/execute-php",
                                  input_key: {"code": PHP}}}},
        token=access_token, extra=extra)
    if status != 200 or not isinstance(body, dict):
        raise RuntimeError(f"execute-ability svarede HTTP {status}")
    if body.get("error"):
        raise RuntimeError(str(body["error"])[:200])
    result = (body.get("result") or {})
    if result.get("isError"):
        text = "".join(p.get("text", "") for p in result.get("content", []) if p.get("type") == "text")
        raise RuntimeError(text[:200] or "execute-php fejlede")
    structured = result.get("structuredContent")
    payload = structured if isinstance(structured, dict) else None
    if payload is None:
        text = "".join(p.get("text", "") for p in result.get("content", []) if p.get("type") == "text")
        payload = json.loads(text)
    for key in ("data", "result"):
        if isinstance(payload.get(key), dict):
            payload = payload[key]
    inner = payload.get("return_value")
    if isinstance(inner, dict):
        payload = inner
    if not isinstance(payload.get("gateways"), list):
        raise RuntimeError(f"svaret havde ingen gateway-liste: {str(payload)[:200]}")
    return payload


# ------------------------------------------------------------------ udskrift

def render(checks: list[dict], payload: dict | None = None) -> str:
    lines = []
    if payload:
        lines.append(f"Live: WooCommerce {payload.get('wc')} · {payload.get('currency')} "
                     f"· {payload.get('base_country')} · kasse {payload.get('parent_class')}")
    for check in checks:
        lines.append(f"[{check['status']:4s}] {check['krav']}: {check['maalt']}")
    lines.append(f"[{verdict(checks)}] {sum(1 for c in checks if c['status'] == 'PASS')} PASS · "
                 f"{sum(1 for c in checks if c['status'] == 'WARN')} WARN · {failed(checks)} FAIL")
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    args = list(sys.argv[1:] if argv is None else argv)
    payload = None
    if "--from-file" in args:
        path = Path(args[args.index("--from-file") + 1])
        try:
            payload = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, ValueError) as exc:
            print(f"[FEJL] kunne ikke læse {path}: {exc}")
            return 1
    else:
        token = local_token()
        if not token:
            print("Ingen credential: sæt NOVAMIRA_ACCESS_TOKEN, eller kør tools/mcp_oauth.py. "
                  "Intet er målt.")
            return 2
        try:
            payload = measure(token)
        except (RuntimeError, ValueError) as exc:
            print(f"[FEJL] {exc}")
            return 1

    checks = judge(payload)
    if "--json" in args:
        print(json.dumps({"payload": payload, "checks": checks}, ensure_ascii=False, indent=1))
    else:
        print(render(checks, payload))
    return 0 if not failed(checks) else 1


if __name__ == "__main__":
    raise SystemExit(main())
