#!/usr/bin/env python3
"""rebrand_audit.py — mål om dark academia-om-mærkningen faktisk er i effekt på sitet.

Om-mærkningen har fire dele: nye titler, seks kollektioner, nye produkttekster og mockups. De tre første kan
måles **offentligt** gennem WooCommerce' Store API — ingen token, ingen admin — og det er præcis den måling der
8/11 viste at titlerne var på plads, men at alle seks kollektioner var tomme.

    python3 tools/rebrand_audit.py               # tabel til skærmen + dom
    python3 tools/rebrand_audit.py --json         # samme som JSON
    python3 tools/rebrand_audit.py --selftest     # 8 fixtures, ingen netværk

Exit: 0 = alt i effekt · 1 = mindst ét krav mangler · 2 = kunne ikke måle (netværk/HTTP).

Kravene er batch 4-arbejdsordrens: tees og hoodies 5 billeder, art prints og phone cases 3, småting 4,
og alle seks kollektioner skal have mindst ét produkt.
"""

from __future__ import annotations

import json
import sys
import urllib.error
import urllib.parse
import urllib.request

API = "https://steamgears.eu/wp-json/wc/store/v1/products"
COLLECTIONS = ["dark-academia", "gothic-victorian", "natural-history",
               "vintage-maritime", "witchy-tarot", "steampunk-capsule"]
OLD_COLLECTIONS = ["airship", "herbarium", "curiosities", "atelier"]
# familie -> (mål for antal billeder, nøgleord i titlen)
FAMILIES = {
    "tee": (5, ("tee", "t-shirt", "t shirt")),
    "hoodie": (5, ("hoodie", "sweatshirt")),
    "print": (3, ("art print", "poster", "print")),
    "phone": (3, ("phone case",)),
}


def family(name: str) -> str:
    lowered = name.lower()
    for key, (_need, words) in FAMILIES.items():
        if any(word in lowered for word in words):
            return key
    return "small"


def required(name: str) -> int:
    return FAMILIES.get(family(name), (4, ()))[0]


def audit(products: list[dict], collections: dict[str, int]) -> dict:
    """Ren funktion: butikkens svar ind, dom ud. Ingen netværk — det er den selftesten prøver."""
    gaps = []
    for product in products:
        need = required(product.get("name", ""))
        have = len(product.get("images") or [])
        gaps.append({"id": product.get("id"), "name": product.get("name"),
                     "family": family(product.get("name", "")), "images": have,
                     "required": need, "missing": max(0, need - have)})
    empty = [slug for slug in COLLECTIONS if collections.get(slug, 0) == 0]
    return {
        "products": len(products),
        "images": sum(g["images"] for g in gaps),
        "with_three_or_more": sum(1 for g in gaps if g["images"] >= 3),
        "image_gaps": [g for g in gaps if g["missing"] > 0],
        "collections": collections,
        "empty_collections": empty,
        "old_collections": {slug: collections.get(slug, 0) for slug in OLD_COLLECTIONS},
    }


def verdict(result: dict) -> tuple[str, bool]:
    if result["empty_collections"]:
        return (f"om-mærkningen er IKKE i effekt: {len(result['empty_collections'])} af "
                f"{len(COLLECTIONS)} kollektioner er tomme ({', '.join(result['empty_collections'])})", True)
    if result["image_gaps"]:
        return (f"kollektionerne er i effekt, men {len(result['image_gaps'])} produkter mangler billeder "
                f"({sum(g['missing'] for g in result['image_gaps'])} i alt)", True)
    return ("om-mærkningen er i effekt: alle seks kollektioner har produkter, og hvert produkt har "
            "sine billeder", False)


def fetch(url: str) -> list[dict]:
    request = urllib.request.Request(url, headers={"User-Agent": "steamgears-rebrand-audit"})
    with urllib.request.urlopen(request, timeout=45) as response:
        return json.load(response)


def products() -> list[dict]:
    return fetch(API + "?" + urllib.parse.urlencode({"per_page": 100, "status": "publish"}))


def counts(slugs: list[str]) -> dict[str, int]:
    """Term-tællingerne fra produktkategori-endpointet: ét kald, og det viser også termer med 0.

    Kategori-filteret på produkter kan ikke skelne »tom term« fra »term findes ikke« — det var netop den
    forskel der blev læst forkert 8/11 (termen hed `vintage-maritime`, ikke `vintage-maritime-prints`).
    """
    terms = fetch("https://steamgears.eu/wp-json/wp/v2/product_cat?" +
                  urllib.parse.urlencode({"per_page": 100, "_fields": "id,slug,name,count"}))
    found = {term.get("slug"): int(term.get("count") or 0) for term in terms}
    return {slug: found.get(slug, 0) for slug in slugs}


def selftest() -> int:
    samples = [
        ({"id": 1, "name": "Airship Armada — Vintage Engraving Tee", "images": [1, 2]}, "tee", 5, 3),
        ({"id": 2, "name": "Clockwork Octopus — Curiosity Hoodie", "images": [1] * 5}, "hoodie", 5, 0),
        ({"id": 3, "name": "Brass Luna Moth — Herbarium Art Print", "images": [1]}, "print", 3, 2),
        ({"id": 4, "name": "Clockwork Rose — Herbarium Phone Case", "images": [1, 2, 3]}, "phone", 3, 0),
        ({"id": 5, "name": "The Aeronaut — Brass Skeleton Pocket Watch", "images": [], }, "small", 4, 4),
    ]
    for product, want_family, want_need, want_missing in samples:
        assert family(product["name"]) == want_family, (product["name"], family(product["name"]))
        result = audit([product], {slug: 1 for slug in COLLECTIONS})
        gap = result["image_gaps"][0] if result["image_gaps"] else {"missing": 0}
        assert gap["missing"] == want_missing, (product["name"], gap)

    full = audit([{"id": 1, "name": "X Tee", "images": [1, 2, 3, 4, 5]}], {slug: 2 for slug in COLLECTIONS})
    assert verdict(full)[1] is False, verdict(full)

    empty = audit([{"id": 1, "name": "X Tee", "images": [1, 2, 3, 4, 5]}],
                  {**{slug: 2 for slug in COLLECTIONS}, "witchy-tarot": 0})
    assert verdict(empty)[1] is True and "witchy-tarot" in verdict(empty)[0], verdict(empty)

    gap = audit([{"id": 1, "name": "X Tee", "images": [1]}], {slug: 2 for slug in COLLECTIONS})
    assert verdict(gap)[1] is True and "mangler billeder" in verdict(gap)[0], verdict(gap)
    print("[OK] rebrand_audit: 8 fixtures — familie, krav, huller, tom kollektion og dom")
    return 0


def main() -> int:
    args = sys.argv[1:]
    if args[:1] == ["--selftest"]:
        return selftest()
    try:
        found = products()
        result = audit(found, counts(COLLECTIONS + OLD_COLLECTIONS))
    except (urllib.error.URLError, urllib.error.HTTPError, ValueError) as exc:
        print(f"kunne ikke måle: {exc.__class__.__name__}: {exc}")
        return 2
    text, bad = verdict(result)
    if "--json" in args:
        print(json.dumps(result, ensure_ascii=False, indent=1))
    else:
        print(f"{'ID':>5}  {'billeder':>8}  {'krav':>4}  produkt")
        for row in sorted(result["image_gaps"], key=lambda r: (r["family"], r["name"])):
            print(f"{row['id']:>5}  {row['images']:>8}  {row['required']:>4}  {row['name'][:64]}")
        print()
        print(f"publiceret: {result['products']} · billeder: {result['images']} · "
              f"med 3+ billeder: {result['with_three_or_more']} · mangler i alt: "
              f"{sum(g['missing'] for g in result['image_gaps'])}")
        for slug in COLLECTIONS:
            print(f"  {slug:<26} {result['collections'].get(slug, 0)} produkter")
    print(f"\nDom: {text}")
    return 1 if bad else 0


if __name__ == "__main__":
    raise SystemExit(main())
