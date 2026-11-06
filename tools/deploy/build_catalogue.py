#!/usr/bin/env python3
"""
Turn catalogue.json into manifest steps, and show the money.

WooCommerce products are created over MCP (`wc_create_product`), not REST —
REST has no product endpoint without the Woo consumer key/secret pair, while MCP
already carries Woo tools once WooCommerce is active.

This script:
  1. reads deploy/warpstorm/catalogue.json
  2. rewrites every mcp-product-* step in manifest.json (idempotent — safe to
     re-run after a price change)
  3. prints a margin table using material + packaging cost only

    python3 tools/deploy/build_catalogue.py
    python3 tools/deploy/build_catalogue.py --check     # margins only, no write
"""

from __future__ import annotations

import argparse
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
CATALOGUE = os.path.join(ROOT, "deploy", "warpstorm", "catalogue.json")
MANIFEST = os.path.join(ROOT, "deploy", "warpstorm", "manifest.json")

VAT = 0.25          # Danish VAT, prices are shown incl. VAT
MIN_MARGIN = 0.55   # below this the SKU is not worth printing


def load(path: str) -> dict:
    with open(path, encoding="utf-8") as fh:
        return json.load(fh)


def gpsr_block(op: dict) -> str:
    return (
        "<h3>Responsible operator (GPSR)</h3>"
        f"<p>{op['name']}<br>{op['street']}<br>{op['city']}, {op['country']}"
        f"<br>CVR {op['cvr']} · {op['email']} · {op['phone']}</p>"
        "<p>Warhammer 40,000 is a trademark of Games Workshop Ltd. "
        "This is an original design, not a copy of any Games Workshop product. "
        "This site is not affiliated with or endorsed by Games Workshop.</p>"
    )


def margins(catalogue: dict) -> list:
    rows = []
    for p in catalogue["products"]:
        price = float(p["regular_price"])
        cost = float(p["cost_eur"])
        ex_vat = price / (1 + VAT)
        gross = ex_vat - cost
        pct = gross / ex_vat if ex_vat else 0
        rows.append({
            "sku": p["sku"],
            "name": p["name"],
            "price": price,
            "cost": cost,
            "gross": gross,
            "pct": pct,
            "print_h": p["print_minutes"] / 60.0,
            "proposed": bool(p.get("proposed")),
            "photos": len(p.get("images") or []),
            "status": p.get("stock_status", "instock"),
        })
    return rows


def print_table(rows: list) -> int:
    print(f"{'sku':12} {'price':>6} {'cost':>6} {'gross':>7} {'marg':>6} {'print':>6}  name")
    print("-" * 92)
    thin = 0
    for r in rows:
        flag = ""
        if r["pct"] < MIN_MARGIN:
            flag = "  <-- below target"
            thin += 1
        if r["proposed"]:
            flag += "  [proposed]"
        print(f"{r['sku']:12} {r['price']:6.0f} {r['cost']:6.1f} {r['gross']:7.1f} "
              f"{r['pct']*100:5.0f}% {r['print_h']:5.1f}h  {r['name'][:40]}{flag}")
    print("-" * 92)
    hours = sum(r["print_h"] for r in rows)
    print(f"{len(rows)} SKU · {hours:.0f} print hours for one of each · "
          f"target margin {MIN_MARGIN*100:.0f}% · {thin} below target")
    print("cost is material + packaging only; print time is not costed.")

    # photo coverage is reported, not enforced: products are created as drafts,
    # so a missing photo blocks the launch, not the deploy
    with_photos = [r for r in rows if r["photos"]]
    print(f"photos: {len(with_photos)}/{len(rows)} SKU have images"
          + ("" if len(with_photos) == len(rows) else
             "  (drafts until the photo day — see docs/warpstorm-print-og-foto-2026-11-02.md)"))
    return thin


def build_steps(catalogue: dict) -> list:
    op = catalogue["operator"]
    steps = []
    for p in catalogue["products"]:
        args = {
            "name": p["name"],
            "type": "simple",
            "status": "draft",
            "sku": p["sku"],
            "regular_price": p["regular_price"],
            "description": p["description"] + gpsr_block(op),
            "short_description": p["short_description"],
            "categories": [p["category"]],
            "weight": p["weight"],
            "dimensions": p["dimensions"],
            "manage_stock": False,
            "stock_status": p.get("stock_status", "instock"),
            "shipping_class": p["shipping_class"],
        }
        if p.get("compare_at"):
            args["sale_price"] = ""  # price is the regular price; compare-at is display only
        # attachment IDs, gallery order — omitted entirely while the photo set
        # does not exist, so the tool call stays valid instead of sending []
        if p.get("images"):
            args["images"] = p["images"]
        steps.append({
            "id": "mcp-product-" + p["sku"].lower(),
            "type": "mcp",
            "title": f"Product {p['sku']} — {p['name']}",
            "tool": "wc_create_product",
            "optional": True,
            "args": args,
        })
    return steps


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--check", action="store_true", help="print margins and write nothing")
    args = ap.parse_args()

    catalogue = load(CATALOGUE)
    rows = margins(catalogue)
    thin = print_table(rows)

    if args.check:
        sys.exit(1 if thin else 0)

    manifest = load(MANIFEST)
    before = len(manifest["steps"])
    manifest["steps"] = [s for s in manifest["steps"]
                         if not s.get("id", "").startswith("mcp-product-")]
    removed = before - len(manifest["steps"])

    # products go last: they need WooCommerce active and a shipping class to exist
    manifest["steps"].extend(build_steps(catalogue))

    with open(MANIFEST, "w", encoding="utf-8") as fh:
        json.dump(manifest, fh, ensure_ascii=False, indent=2)
        fh.write("\n")

    print(f"\nmanifest: removed {removed} old product step(s), "
          f"added {len(rows)} — {len(manifest['steps'])} steps total")


if __name__ == "__main__":
    main()
