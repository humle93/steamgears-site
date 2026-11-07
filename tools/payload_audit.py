#!/usr/bin/env python3
"""HTML- og scriptbudget pr. skabelon (S2.7) — måling, ikke ændring.

Måler de samme tal for hver skabelon på det levende site, så et indgreb kan sammenlignes 1:1:

- HTML-bytes (body som leveret, ukomprimeret)
- `<script>`-tags i alt, heraf eksterne (`src`) og inline
- `<style>`-blokke, deres samlede bytes og den største enkelte blok
- `link rel=stylesheet` (eksterne stylesheets)

Budget fra `docs/roadmap.md` S2.7: **HTML < 100 KB, < 20 `<script>`-tags, ingen inline-blok over 4 KB.**

Brug:
    python3 tools/payload_audit.py                     # tabel for alle skabeloner
    python3 tools/payload_audit.py --json reports/x.json
    python3 tools/payload_audit.py --only forside,produkt

Neutral `Mozilla/5.0`-UA, ingen credential. Tallene er sammenlignelige før/efter, ikke Core Web Vitals.
"""

import argparse
import json
import os
import re
import sys
import urllib.error
import urllib.request
from datetime import date

BASE = "https://steamgears.eu"
UA = "Mozilla/5.0"
HTML_MAX = 100 * 1024        # 100 KB, som budgettet skriver
SCRIPTS_MAX = 20
INLINE_BLOCK_MAX = 4096      # 4 KB
TIMEOUT = 30

# Skabelon → sti. Hver række er en skabelontype, ikke en tilfældig side.
TEMPLATES = [
    ("forside", "/"),
    ("arkiv shop", "/shop/"),
    ("produkt", "/product/astrolabe-planisphere-tee/"),
    ("indlæg", "/steampunk-corset-guide/"),
    ("side", "/faq/"),
    ("kategori", "/product-category/atelier/"),
    ("søgning", "/?s=steampunk"),
    ("kurv", "/cart/"),
]

STYLE_RE = re.compile(r"<style\b([^>]*)>(.*?)</style>", re.S | re.I)
SCRIPT_RE = re.compile(r"<script\b([^>]*)>(.*?)</script>", re.S | re.I)
SRC_RE = re.compile(r"\bsrc\s*=", re.I)
LINK_RE = re.compile(r"<link\b[^>]*rel=[\"']stylesheet[\"'][^>]*>", re.I)


def fetch(path: str) -> tuple:
    req = urllib.request.Request(BASE + path, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
            raw = resp.read()
            status = resp.status
    except urllib.error.HTTPError as exc:      # 404-siden er en skabelon som alle andre
        raw = exc.read()
        status = exc.code
    return status, raw.decode("utf-8", errors="replace"), len(raw)


def measure(html: str) -> dict:
    styles = [(m.group(1), len(m.group(2).encode())) for m in STYLE_RE.finditer(html)]
    scripts = []
    for m in SCRIPT_RE.finditer(html):
        scripts.append((bool(SRC_RE.search(m.group(1))), len(m.group(2).encode())))
    largest_style = max((size for _, size in styles), default=0)
    largest_script = max((size for inline, size in scripts if inline), default=0)
    return {
        "scripts": len(scripts),
        "scripts_src": sum(1 for src, _ in scripts if src),
        "scripts_inline": sum(1 for src, _ in scripts if not src),
        "styles": len(styles),
        "css_inline_bytes": sum(size for _, size in styles),
        "js_inline_bytes": sum(size for inline, size in scripts if inline),
        "largest_inline_block": max(largest_style, largest_script),
        "stylesheets": len(LINK_RE.findall(html)),
    }


def verdict(row: dict) -> dict:
    return {
        "html": row["html_bytes"] < HTML_MAX,
        "scripts": row["scripts"] < SCRIPTS_MAX,
        "inline_block": row["largest_inline_block"] <= INLINE_BLOCK_MAX,
    }


def run(only=None) -> dict:
    rows = []
    for label, path in TEMPLATES:
        if only and label not in only:
            continue
        try:
            status, html, size = fetch(path)
        except Exception as exc:                      # netværk/WAF: rækken skal stadig med
            rows.append({"template": label, "path": path, "error": str(exc)})
            continue
        row = {"template": label, "path": path, "status": status, "html_bytes": size}
        row.update(measure(html))
        row["budget"] = verdict(row)
        row["meets_budget"] = all(row["budget"].values())
        rows.append(row)
    return {
        "measured": date.today().isoformat(),
        "base": BASE,
        "budget": {"html_bytes": HTML_MAX, "scripts": SCRIPTS_MAX, "inline_block": INLINE_BLOCK_MAX},
        "rows": rows,
    }


def table(result: dict) -> str:
    head = ("| Skabelon | Sti | HTML | `<script>` | heraf `src` / inline | `<style>` | inline CSS | "
            "største blok | Budget |")
    sep = "| --- | --- | --- | --- | --- | --- | --- | --- | --- |"
    lines = [head, sep]
    for r in result["rows"]:
        if "error" in r:
            lines.append(f"| {r['template']} | `{r['path']}` | — | — | — | — | — | — | fejl: {r['error'][:60]} |")
            continue
        ok = r["budget"]
        mark = "✅" if r["meets_budget"] else "❌"
        fails = [name for name, good in ok.items() if not good]
        lines.append(
            f"| {r['template']} | `{r['path']}` | {r['html_bytes']:,} B | {r['scripts']} | "
            f"{r['scripts_src']} / {r['scripts_inline']} | {r['styles']} | {r['css_inline_bytes']:,} B | "
            f"{r['largest_inline_block']:,} B | {mark}{'' if not fails else ' (' + ', '.join(fails) + ')'} |"
        )
    return "\n".join(lines)


def main() -> int:
    ap = argparse.ArgumentParser(description="HTML- og scriptbudget pr. skabelon (S2.7)")
    ap.add_argument("--json", help="skriv målingen som JSON til denne sti")
    ap.add_argument("--only", help="kommasepareret liste af skabelonnavne")
    args = ap.parse_args()

    only = {s.strip() for s in args.only.split(",")} if args.only else None
    result = run(only)
    print(table(result))
    fails = [r for r in result["rows"] if not r.get("meets_budget", False)]
    print(f"\n{len(result['rows'])} skabeloner målt · {len(fails)} over budget")
    if args.json:
        os.makedirs(os.path.dirname(os.path.abspath(args.json)), exist_ok=True)
        with open(args.json, "w", encoding="utf-8") as fh:
            json.dump(result, fh, ensure_ascii=False, indent=2)
            fh.write("\n")
        print(f"[OK] skrev {args.json}")
    return 0 if not fails else 1


if __name__ == "__main__":
    sys.exit(main())
