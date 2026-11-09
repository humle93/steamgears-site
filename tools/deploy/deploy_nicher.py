#!/usr/bin/env python3
"""
Gør de fire niche-payloads klar til at blive lagt ud i ét hug.

    python3 tools/deploy/deploy_nicher.py            # byg importfiler (standard)
    python3 tools/deploy/deploy_nicher.py --check    # kun preflight, ingen filer
    python3 tools/deploy/deploy_nicher.py --woo      # WooCommerce-vejen via MCP (kræver token)

Standardkørslen skriver pr. niche:

    deploy/nicher/shopify-<slug>.csv      produkter, klar til Shopify → Produkter → Importér
    deploy/nicher/<slug>-sider.md         de fem sider som tekst, klar til at klistre ind

CSV'en er den vej, der findes i dag: KABINE og de tre andre nicher er Shopify-butikker, og der
findes ingen Shopify-token i repoet eller sandkassen. Rækkefølgen er derfor:
preflight → importér CSV → opret de fem kollektioner → indsæt siderne → ryd ejerfelterne.

`--woo` er den anden vej og er **ikke** det samme indhold: plugin'ets `niche-apply` bygger sin
egen katalog ud fra en nichestreng og skriver kun kladder. Den bruges til en WooCommerce-demo,
ikke til at publicere de fire butikker, og den kræver et MCP-token, som sandkassen ikke har.

Rollback: Shopify → Produkter → Importér → Fortryd importen (inden 24 timer), eller slet de
importerede handles. Hver niche har sin egen `plan_key`, som står i payloaden.
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import niche_preflight as preflight  # noqa: E402

ROOT = preflight.ROOT
OUT_DIR = preflight.OUT_DIR

SHOPIFY_COLUMNS = [
    "Handle", "Title", "Body (HTML)", "Vendor", "Type", "Tags", "Published",
    "Option1 Name", "Option1 Value", "Variant SKU", "Variant Grams",
    "Variant Inventory Tracker", "Variant Inventory Policy", "Variant Fulfillment Service",
    "Variant Price", "Variant Compare At Price", "Variant Requires Shipping",
    "Variant Taxable", "Variant Barcode", "Image Src", "Image Alt Text",
    "SEO Title", "SEO Description", "Status",
]


def product_html(payload: dict, product: dict) -> str:
    bullets = "".join(f"<li>{b}</li>" for b in product["bullets"])
    return (
        f"<p>{product['short']}</p>"
        f"<ul>{bullets}</ul>"
        f"<p>Kollektion: {product['collection']}. {payload['tagline']}</p>"
        "<ul><li>Produktionstid: [EJER: dage].</li>"
        "<li>Levering: [EJER: zoner og dage].</li>"
        "<li>Retur: 14 dages fortrydelsesret, [EJER: returadresse].</li></ul>"
    )


def grams(product: dict) -> int:
    """En grov vægt til fragtberegning — erstattes af leverandørens varevægt."""
    return 900 if product["role"] == "bundle" else 500


def write_shopify_csv(payload: dict) -> str:
    path = os.path.join(OUT_DIR, f"shopify-{payload['slug']}.csv")
    with open(path, "w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=SHOPIFY_COLUMNS)
        writer.writeheader()
        for product in payload["products"]:
            writer.writerow({
                "Handle": product["slug"],
                "Title": product["name"],
                "Body (HTML)": product_html(payload, product),
                "Vendor": payload["name"],
                "Type": product["collection"],
                "Tags": ", ".join([payload["niche"], product["collection"], product["role"]]),
                "Published": "FALSE",
                "Option1 Name": "Title",
                "Option1 Value": "Default Title",
                "Variant SKU": product["sku"],
                "Variant Grams": grams(product),
                "Variant Inventory Tracker": "shopify",
                "Variant Inventory Policy": "deny",
                "Variant Fulfillment Service": "manual",
                "Variant Price": f"{product['price']:.2f}",
                "Variant Compare At Price": "",
                "Variant Requires Shipping": "TRUE",
                "Variant Taxable": "TRUE",
                "Variant Barcode": "",
                "Image Src": "",
                "Image Alt Text": product["name"],
                "SEO Title": f"{product['name']} · {payload['name']}",
                "SEO Description": product["short"][:155],
                "Status": "draft",
            })
    return path


def write_pages(payload: dict) -> str:
    path = os.path.join(OUT_DIR, f"{payload['slug']}-sider.md")
    lines = [
        f"# {payload['name']} — fem sider, klar til at klistre ind",
        "",
        f"Kilde: `deploy/nicher/{payload['slug']}.json`. Siderne er kladder; "
        "hvert `[EJER: …]` skal erstattes af en oplysning, ejeren har givet, "
        "før siden må publiceres.",
        "",
    ]
    for page in payload["pages"]:
        lines += [f"## {page['title']}", "", f"Slug: `/{page['slug']}/`", "",
                  page["body"], ""]
    lines += [
        "## Kollektioner, der skal oprettes",
        "",
        "| Navn | Slug | Beskrivelse |",
        "| --- | --- | --- |",
    ]
    lines += [f"| {c['name']} | `{c['slug']}` | {c['description']} |"
              for c in payload["collections"]]
    lines += ["", "## Ejeri punkter, der blokerer publicering", ""]
    lines += [f"- `{b}`" for b in payload["owner_blockers"]]
    lines += ["", "## Ejerhandlinger", ""]
    lines += [f"{i}. {step}" for i, step in enumerate(payload["owner_steps"], 1)]
    lines += ["", "## Annoncehooks og KPI-gates", ""]
    lines += [f"- Hook: {h}" for h in payload["hooks"]]
    lines += [f"- {k['gate']}: {k['limit']}" for k in payload["kpi"]]
    with open(path, "w", encoding="utf-8") as fh:
        fh.write("\n".join(lines) + "\n")
    return path


def run_woo(payloads: list[dict], apply: bool) -> int:
    """WooCommerce-vejen: plugin'ets egen niche-motor over MCP. Skriver kun kladder."""
    token = os.environ.get("MCP_ACCESS_TOKEN", "").strip()
    if not token:
        print("Ingen MCP_ACCESS_TOKEN — Woo-vejen kan ikke køre her. "
              "Brug standardkørslen (Shopify-import) eller sæt tokenet.", file=sys.stderr)
        return 1
    results = []
    for payload in payloads:
        args = json.dumps({"niche": payload["niche"],
                           "count": len(payload["products"]),
                           "margin": 45})
        tool = "steamgears-autopilot/niche-plan" if not apply else "steamgears-autopilot/niche-apply"
        print(f"→ {tool} {payload['slug']}: {args}")
        proc = subprocess.run(
            [sys.executable, os.path.join(ROOT, "tools", "mcp_rest.py"), "--call", tool, args],
            capture_output=True, text=True, cwd=ROOT,
        )
        results.append({"niche": payload["slug"], "exit": proc.returncode,
                        "out": (proc.stdout or "").strip()[:400]})
        if proc.returncode != 0:
            print(f"  fejlede: {(proc.stderr or proc.stdout).strip()[:200]}", file=sys.stderr)
    print(json.dumps(results, ensure_ascii=False, indent=2))
    return 0 if all(r["exit"] == 0 for r in results) else 1


def main() -> int:
    parser = argparse.ArgumentParser(description="Klargør de fire nicher til udrulning")
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--check", action="store_true", help="kun preflight")
    group.add_argument("--woo", action="store_true", help="WooCommerce-vejen via MCP (kladder)")
    group.add_argument("--woo-apply", action="store_true", help="som --woo, men skriv kladderne")
    args = parser.parse_args()

    with open(preflight.SOURCE, encoding="utf-8") as fh:
        source = json.load(fh)
    problems = preflight.Problems()
    payloads = preflight.validate_all(source, problems)
    preflight.check_drift(payloads, source, problems)
    for kind, msg in problems:
        print(f"{kind}: {msg}", file=sys.stderr)
    if any(kind == "FEJL" for kind, _ in problems):
        print("Preflight fejlede — intet blev skrevet.", file=sys.stderr)
        return 1

    if args.check:
        print(f"OK: {len(payloads)} nicher klar, ingen fejl, ingen drift.")
        return 0
    if args.woo or args.woo_apply:
        return run_woo(payloads, apply=args.woo_apply)

    csv_paths = [write_shopify_csv(p) for p in payloads]
    page_paths = [write_pages(p) for p in payloads]
    print(f"{len(payloads)} nicher klar til import:")
    for payload, csv_path, page_path in zip(payloads, csv_paths, page_paths):
        print(f"  {payload['name']:<8} {payload['counts']['products']} varer · "
              f"{payload['counts']['collections']} kollektioner · "
              f"{payload['counts']['pages']} sider · "
              f"{payload['counts']['owner_fields']} ejerfelter → "
              f"{os.path.relpath(csv_path, ROOT)}, {os.path.relpath(page_path, ROOT)}")
    print("\nRækkefølge: importér CSV (kladder) → opret kollektionerne → indsæt siderne → "
          "udfyld ejerfelterne → publicér.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
