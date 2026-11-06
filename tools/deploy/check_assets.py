#!/usr/bin/env python3
"""
Check the punk artwork against the rules that are cheap to test and expensive
to discover after printing or uploading.

Two destinations, deliberately kept apart:

  print-master.png   -> Printify. Full size, transparent for apparel. Never
                        uploaded to WordPress, so it is never a product image.
  NN-view.jpg        -> WordPress media library, one file per gallery slot,
                        in the order the catalogue lists them.

Missing files are not errors — they are the slots this pipeline exists to fill.
A file that is present but wrong is an error, because it would ship.

    python3 tools/deploy/check_assets.py            # report
    python3 tools/deploy/check_assets.py --strict   # exit 1 while any slot is empty
"""

from __future__ import annotations

import argparse
import json
import os
import sys

from PIL import Image

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
CATALOGUE = os.path.join(ROOT, "deploy", "steamgears", "punk-catalogue.json")

APPAREL = {"tee", "hoodie"}
MOCKUP_MIN_EDGE = 2000        # house rule: long edge
MOCKUP_MAX_BYTES = 500 * 1024  # house rule: JPG under 500 KB
MIN_DPI = 300


def load_catalogue() -> dict:
    with open(CATALOGUE, encoding="utf-8") as fh:
        return json.load(fh)


def check_print(product: dict, root: str) -> tuple:
    """Errors, warnings, present for the Printify master."""
    spec = product.get("print") or {}
    rel = spec.get("file", "")
    path = os.path.join(root, rel)
    if not os.path.exists(path):
        return [], [], False

    errors, warnings = [], []
    try:
        with Image.open(path) as img:
            width, height = img.size
            mode = img.mode
            dpi = img.info.get("dpi")
    except Exception as exc:                       # unreadable file is an error
        return [f"{rel}: cannot be read ({exc})"], [], True

    min_width, min_height = (spec.get("min_px") or [None, None])[:2]
    if min_width and width < min_width:
        errors.append(f"{rel}: {width} px wide, needs {min_width} px "
                      f"({spec['cm'][0]} cm at {MIN_DPI} DPI)")
    if min_height and height < min_height:
        errors.append(f"{rel}: {height} px high, needs {min_height} px "
                      f"({spec['cm'][1]} cm at {MIN_DPI} DPI)")

    has_alpha = mode in ("RGBA", "LA") or (mode == "P" and "transparency" in img.info)
    if product["type"] in APPAREL and not has_alpha:
        errors.append(f"{rel}: mode {mode} has no transparency — DTG needs the "
                      f"shirt colour to show through")
    if product["type"] not in APPAREL and mode not in ("RGB", "L"):
        warnings.append(f"{rel}: mode {mode} for a poster — RGB is what the "
                        f"printer expects")

    if dpi and float(dpi[0]) < MIN_DPI:
        warnings.append(f"{rel}: {float(dpi[0]):.0f} DPI in the metadata, "
                        f"{MIN_DPI} expected (pixels matter more, but set it)")
    if not dpi:
        warnings.append(f"{rel}: no DPI in the metadata — harmless for Printify, "
                        f"but it hides a resized file")

    return errors, warnings, True


def check_mockup(product: dict, mockup: dict, root: str) -> tuple:
    rel = mockup.get("file", "")
    path = os.path.join(root, rel)
    if not os.path.exists(path):
        return [], [], False

    errors, warnings = [], []
    if not rel.lower().endswith((".jpg", ".jpeg")):
        errors.append(f"{rel}: must be a JPG")
    size = os.path.getsize(path)
    if size > MOCKUP_MAX_BYTES:
        warnings.append(f"{rel}: {size // 1024} KB, house rule is under "
                        f"{MOCKUP_MAX_BYTES // 1024} KB")
    try:
        with Image.open(path) as img:
            long_edge = max(img.size)
            mode = img.mode
    except Exception as exc:
        return [f"{rel}: cannot be read ({exc})"], [], True

    if long_edge < MOCKUP_MIN_EDGE:
        errors.append(f"{rel}: long edge {long_edge} px, needs {MOCKUP_MIN_EDGE} px")
    if mode == "P":
        warnings.append(f"{rel}: palette mode — re-save as RGB, WordPress may "
                        f"render it differently than the viewer does")
    return errors, warnings, True


def main() -> None:
    ap = argparse.ArgumentParser(description="Validate punk artwork before upload")
    ap.add_argument("--strict", action="store_true",
                    help="exit 1 while any slot is still empty")
    args = ap.parse_args()

    catalogue = load_catalogue()
    root = ROOT
    products = sorted(catalogue["products"], key=lambda p: p.get("priority", 99))

    errors, warnings = [], []
    print_master = mockups = 0
    total_mockups = sum(len(p.get("mockups") or []) for p in products)

    print(f"{'id':4} {'type':7} {'master':8} {'mockups':8} name")
    print("-" * 88)
    for product in products:
        design = product["design"]
        e1, w1, has_master = check_print(product, root)
        errors += e1
        warnings += w1
        print_master += has_master

        found = 0
        for mockup in product.get("mockups") or []:
            e2, w2, present = check_mockup(product, mockup, root)
            errors += e2
            warnings += w2
            found += present
        mockups += found

        master_note = "yes" if has_master else "empty"
        if has_master:
            spec = product["print"]
            master_note += f" {spec['cm'][0]}cm"
        print(f"{design:4} {product['type']:7} {master_note:8} {found}/{len(product.get('mockups') or []):<6} "
              f"{product['name'][:40]}")

    print("-" * 88)
    print(f"print masters: {print_master}/{len(products)}   "
          f"mockups: {mockups}/{total_mockups}   "
          f"{len(errors)} error(s), {len(warnings)} warning(s)")

    for message in errors:
        print("error:   " + message)
    for message in warnings:
        print("warning: " + message)

    if errors:
        raise SystemExit(1)
    if args.strict and (print_master < len(products) or mockups < total_mockups):
        print("\nnot all slots are filled yet")
        raise SystemExit(1)
    if mockups < total_mockups or print_master < len(products):
        print(f"\n{total_mockups - mockups} mockup slot(s) and "
              f"{len(products) - print_master} print master(s) still empty — "
              f"see assets/punk/README.md")
    else:
        print("\nall files present. Next: python3 tools/deploy/build_punk.py")


if __name__ == "__main__":
    sys.exit(main())
