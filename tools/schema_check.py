#!/usr/bin/env python3
"""schema_check.py — kontrollerer Product-JSON-LD på de live produktsider.

Kør:
  python3 tools/schema_check.py                 # alle produkter fra REST
  python3 tools/schema_check.py --limit 5       # kun de første 5
  python3 tools/schema_check.py --json          # maskinlæsbar oversigt

Tjekker pr. produkt: brand, hasMerchantReturnPolicy, shippingDetails,
priceCurrency og om aggregateRating findes (den må ikke være opdigtet).

Exit: 0 = alle produkter har felterne · 1 = mindst ét produkt mangler noget · 2 = kunne ikke hente data.

Bruger curl uden browser-UA: Simplys WAF svarer 454/455 til browser- og bot-UA'er
fra datacenter-IP'er, men 200 til en neutral klient (se docs/library/waf-diagnose-2026-11-02.md).
"""

from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
import time
from json import JSONDecodeError

BASE = "https://steamgears.eu"
PACE = 0.45
LD_RE = re.compile(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>', re.S)


def fetch(path: str) -> str:
    out = subprocess.run(
        ["curl", "-sS", "--max-time", "30", f"{BASE}{path}"],
        capture_output=True, text=True,
    )
    if out.returncode != 0:
        raise RuntimeError(f"curl fejlede for {path}: {out.stderr.strip()}")
    return out.stdout


def product_paths() -> list[str]:
    raw = fetch("/wp-json/wp/v2/product?per_page=100&_fields=link")
    items = json.loads(raw)
    return [re.sub(r"^https?://[^/]+", "", it["link"]) for it in items if it.get("link")]


def product_entities(html: str) -> list[dict]:
    found = []
    for block in LD_RE.findall(html):
        try:
            data = json.loads(block.strip())
        except JSONDecodeError:
            continue
        stack = [data]
        while stack:
            node = stack.pop()
            if isinstance(node, dict):
                types = node.get("@type")
                types = types if isinstance(types, list) else [types]
                if "Product" in types:
                    found.append(node)
                stack.extend(v for v in node.values() if isinstance(v, (dict, list)))
            elif isinstance(node, list):
                stack.extend(node)
    return found


def offers_of(product: dict) -> list[dict]:
    offers = product.get("offers")
    if isinstance(offers, list):
        return [o for o in offers if isinstance(o, dict)]
    return [offers] if isinstance(offers, dict) else []


def inspect(product: dict) -> dict:
    offers = offers_of(product)
    return {
        "brand": bool(product.get("brand")),
        "return_policy": any(bool(o.get("hasMerchantReturnPolicy")) for o in offers),
        "shipping": any(bool(o.get("shippingDetails")) for o in offers),
        "currency": next((o.get("priceCurrency") for o in offers if o.get("priceCurrency")), None),
        "rating": bool(product.get("aggregateRating")),
        "offers": len(offers),
    }


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    try:
        paths = product_paths()
    except (RuntimeError, JSONDecodeError) as exc:
        print(f"kunne ikke hente produktlisten: {exc}", file=sys.stderr)
        return 2
    if args.limit:
        paths = paths[: args.limit]

    rows = []
    for i, path in enumerate(paths, 1):
        try:
            html = fetch(path)
        except RuntimeError as exc:
            print(f"  ! {path}: {exc}", file=sys.stderr)
            continue
        products = product_entities(html)
        if not products:
            rows.append({"path": path, "error": "ingen Product-JSON-LD"})
            continue
        info = inspect(products[0])
        info["path"] = path
        rows.append(info)
        if not args.json:
            missing = [k for k in ("brand", "return_policy", "shipping") if not info.get(k)]
            status = "OK  " if not missing else "MANGLER " + ", ".join(missing)
            print(f"{i:>2}/{len(paths)} {status:<28} {path}")
        time.sleep(PACE)

    scored = [r for r in rows if "error" not in r]
    ok = [r for r in scored if r["brand"] and r["return_policy"] and r["shipping"]]
    currencies = sorted({r["currency"] for r in scored if r.get("currency")})

    if args.json:
        print(json.dumps({"checked": len(scored), "complete": len(ok), "rows": rows},
                         ensure_ascii=False, indent=1))
    else:
        print()
        print(f"Produkter undersøgt: {len(scored)}   fuldt udfyldt: {len(ok)}")
        print(f"brand: {sum(1 for r in scored if r['brand'])}"
              f" · returret: {sum(1 for r in scored if r['return_policy'])}"
              f" · levering: {sum(1 for r in scored if r['shipping'])}")
        print(f"valuta: {', '.join(c for c in currencies if c) or '—'}"
              f" · med aggregateRating: {sum(1 for r in scored if r['rating'])}")
        if len(ok) == len(scored) and scored:
            print("Alle produkter har brand, returret og leveringstid.")
        else:
            print("Mangler stadig — deploy wordpress/mu-plugins/sgap-product-schema.php")

    return 0 if scored and len(ok) == len(scored) else (1 if scored else 2)


if __name__ == "__main__":
    sys.exit(main())
