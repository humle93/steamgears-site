#!/usr/bin/env python3
"""seo_audit_products.py — produktfelter som REST ikke udstiller.

Kør:  python3 tools/seo_audit_products.py
Ud:   reports/seo-audit-products-<dato>.json

WooCommerce REST svarer kun med id/slug/type/link på dette site (felterne
price, images og review_count kommer tilbage som null). Derfor læses pris,
valuta, billedantal og AggregateRating ud af produktsidens JSON-LD i stedet.
Henter med curl uden browser-UA og 0,45 s pacing.
"""

from __future__ import annotations

import json
import re
import subprocess
import time
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATE = date.today().isoformat()
DATA = ROOT / "reports" / f"seo-audit-data-{DATE}.json"
OUT = ROOT / "reports" / f"seo-audit-products-{DATE}.json"


def curl(url: str) -> str:
    subprocess.run(["curl", "-sS", "--max-time", "30", "-o", "/tmp/prod.html", url], check=False)
    path = Path("/tmp/prod.html")
    return path.read_text(encoding="utf-8", errors="replace") if path.exists() else ""


def jsonld_blocks(html: str) -> list:
    blocks = []
    pattern = r'<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>'
    for match in re.finditer(pattern, html, re.S | re.I):
        try:
            blocks.append(json.loads(match.group(1)))
        except Exception:
            continue
    return blocks


def walk(node, out: dict) -> None:
    if isinstance(node, dict):
        kind = node.get("@type")
        if isinstance(kind, list):
            kind = kind[0] if kind else None
        if kind == "Product":
            offers = node.get("offers") or {}
            if isinstance(offers, list):
                offers = offers[0] if offers else {}
            images = node.get("image")
            if isinstance(images, str):
                images = [images]
            rating = node.get("aggregateRating") or {}
            out.update({
                "name": node.get("name", ""),
                "price": offers.get("price") or offers.get("lowPrice"),
                "currency": offers.get("priceCurrency", ""),
                "availability": offers.get("availability", ""),
                "price_valid_until": offers.get("priceValidUntil", ""),
                "return_policy": bool(offers.get("hasMerchantReturnPolicy")),
                "shipping_details": bool(offers.get("shippingDetails")),
                "jsonld_images": len(images or []),
                "brand": bool(node.get("brand")),
                "sku": node.get("sku", ""),
                "has_rating": bool(rating),
                "rating_count": rating.get("reviewCount") or rating.get("ratingCount"),
                "rating_value": rating.get("ratingValue"),
                "description_len": len(node.get("description", "") or ""),
            })
        for value in node.values():
            walk(value, out)
    elif isinstance(node, list):
        for value in node:
            walk(value, out)


def main() -> None:
    data = json.loads(DATA.read_text(encoding="utf-8"))
    urls = [p["url"] for p in data["pages"] if p["path"].startswith("/product/")]
    rows = []
    for i, url in enumerate(urls, 1):
        html = curl(url)
        info: dict = {}
        for block in jsonld_blocks(html):
            walk(block, info)
        rows.append({"path": url.split("steamgears.eu")[-1], **info,
                     "html_images": html.count("<img"), "bytes": len(html)})
        print(f"[{i}/{len(urls)}] {info.get('currency', '?')} {str(info.get('price')):>7} "
              f"imgs={info.get('jsonld_images', 0)} rating={info.get('rating_count')} "
              f"{url.split('steamgears.eu')[-1]}", flush=True)
        time.sleep(0.45)
    OUT.write_text(json.dumps({"date": DATE, "products": rows}, ensure_ascii=False, indent=1),
                   encoding="utf-8")
    print(f"{len(rows)} produkter -> {OUT.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
