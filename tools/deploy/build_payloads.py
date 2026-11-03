#!/usr/bin/env python3
"""
Build WordPress page payloads from the static prototypes in concepts/.

The shop and product prototypes are self-contained HTML with an external
stylesheet. WordPress needs a page body, so this:

  1. takes the contents of <main>
  2. inlines style.css into a <style> block (the .ws-* classes are namespaced,
     so nothing collides with the theme)
  3. rewrites prototype links to the real slugs
  4. wraps the result in a wp:html block

Output: deploy/warpstorm/payloads/*.json — one file per page, referenced by
manifest.json. Re-run it whenever the prototype changes.

    python3 tools/deploy/build_payloads.py
"""

from __future__ import annotations

import json
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SRC = os.path.join(ROOT, "concepts", "warpstorm-supply-depot")
OUT = os.path.join(ROOT, "deploy", "warpstorm", "payloads")

PAGES = [
    {
        "file": "shop.html",
        "slug": "shop",
        "title": "Supply Depot",
        "status": "draft",
        "links": {"product.html": "/sector-ruins/", "shop.html": "/shop/"},
    },
    {
        "file": "product.html",
        "slug": "sector-ruins",
        "title": "Sector Ruins — modular terrain",
        "status": "draft",
        "links": {"product.html": "/sector-ruins/", "shop.html": "/shop/"},
    },
]


def read(path: str) -> str:
    with open(path, encoding="utf-8") as fh:
        return fh.read()


def extract_main(html: str) -> str:
    m = re.search(r'<main id="main">(.*?)</main>', html, re.S)
    if not m:
        raise SystemExit("no <main id=\"main\"> found")
    body = m.group(1)
    # prototypes only; never ship the demo script or the toast node
    body = re.sub(r'<script\b[^>]*>.*?</script>', "", body, flags=re.S)
    body = re.sub(r'<div class="ws-toast".*?</div>', "", body, flags=re.S)
    return body.strip()


def main() -> None:
    css = read(os.path.join(SRC, "style.css"))
    os.makedirs(OUT, exist_ok=True)

    for page in PAGES:
        body = extract_main(read(os.path.join(SRC, page["file"])))
        for old, new in page["links"].items():
            body = body.replace(f'href="{old}"', f'href="{new}"')

        content = (
            "<!-- wp:html -->\n"
            "<style>\n" + css.strip() + "\n</style>\n"
            + body + "\n"
            "<!-- /wp:html -->"
        )

        payload = {
            "slug": page["slug"],
            "title": page["title"],
            "status": page["status"],
            "content": content,
        }
        path = os.path.join(OUT, page["slug"] + ".json")
        with open(path, "w", encoding="utf-8") as fh:
            json.dump(payload, fh, ensure_ascii=False, indent=2)
        print(f"{path}  ({len(content) // 1024} KB)")


if __name__ == "__main__":
    main()
