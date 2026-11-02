#!/usr/bin/env python3
"""Titel- og meta-audit for steamgears.eu — måler, gætter ikke.

Henter alle URLs fra Rank Math-sitemappen, crawler dem cache-bustet og melder:

  * titler over 62 tegn (reglen fra 1/11: 0 titler over 62)
  * titler under 30 tegn (for korte til at bære et søgeord)
  * manglende eller for korte/lange meta descriptions (krav 140–158)
  * soft-404: sider der svarer 200, men viser «Page Not Found»

Cache-busteren er ikke kosmetik: sitet serverer cachede «Page Not Found»-dokumenter
med HTTP 200, så en måling uden ?sgcache= kan både melde en død side som levende og
en ny side som død.

Brug:
  python3 seo_title_audit.py                # tabel + markdown-rapport
  python3 seo_title_audit.py --no-report    # kun tabel
"""

import argparse
import html as htmllib
import re
import sys
import time
from concurrent.futures import ThreadPoolExecutor

from publish_hubs import WP_SITE, extract, fetch_html, is_soft_404

TITLE_MAX = 62
TITLE_MIN = 30
META_MIN, META_MAX = 140, 158


def sitemap_urls():
    """Alle URLer fra sitemap-index + børnesitemaps."""
    status, xml = fetch_html(f"{WP_SITE}/sitemap_index.xml")
    if status != 200:
        sys.exit(f"kunne ikke hente sitemap_index.xml (HTTP {status})")
    children = re.findall(r"<loc>([^<]+)</loc>", xml)
    urls = []
    for child in children:
        status, body = fetch_html(child)
        if status != 200:
            print(f"[advarsel] {child} -> HTTP {status}", file=sys.stderr)
            continue
        urls += re.findall(r"<loc>([^<]+)</loc>", body)
    return sorted(set(urls))


def audit_one(url):
    """Returnerer en række med status, titel, meta og fundne problemer."""
    status, html = fetch_html(url, bust=True)
    if status != 200:
        return {"url": url, "status": status, "title": "", "meta": "",
                "problems": [f"HTTP {status}"]}
    info = extract(html)
    problems = []
    if is_soft_404(info):
        problems.append("soft-404 (200 med 'Page Not Found')")
    # &amp; og venner tæller ikke som tegn i en SERP-titel — afkod før måling.
    title, meta = htmllib.unescape(info["title"]), htmllib.unescape(info["meta"])
    if not title:
        problems.append("ingen <title>")
    elif len(title) > TITLE_MAX:
        problems.append(f"titel {len(title)} tegn (>{TITLE_MAX})")
    elif len(title) < TITLE_MIN:
        problems.append(f"titel {len(title)} tegn (<{TITLE_MIN})")
    if not meta:
        problems.append("ingen meta description")
    elif not META_MIN <= len(meta) <= META_MAX:
        problems.append(f"meta {len(meta)} tegn (krav {META_MIN}-{META_MAX})")
    return {"url": url, "status": status, "title": title, "meta": meta,
            "problems": problems}


def main():
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--no-report", action="store_true", help="skriv ikke markdown")
    args = parser.parse_args()

    urls = sitemap_urls()
    print(f"{len(urls)} URLs fra sitemappen — crawler cache-bustet\n")
    started = time.time()
    with ThreadPoolExecutor(max_workers=8) as pool:
        rows = list(pool.map(audit_one, urls))
    print(f"crawlet på {time.time() - started:.0f}s\n")

    broken = [r for r in rows if r["problems"]]
    print(f"{len(broken)} af {len(rows)} URLs har et problem\n")
    for row in sorted(broken, key=lambda r: (r["problems"][0], r["url"])):
        print(row["url"])
        print(f"    titel {len(row['title']):>3} tegn: {row['title']}")
        print(f"    meta  {len(row['meta']):>3} tegn")
        for problem in row["problems"]:
            print(f"    -> {problem}")

    long_titles = [r for r in rows if len(r["title"]) > TITLE_MAX]
    no_meta = [r for r in rows if not r["meta"]]
    short_meta = [r for r in rows if r["meta"] and len(r["meta"]) < META_MIN]
    long_meta = [r for r in rows if len(r["meta"]) > META_MAX]
    short_titles = [r for r in rows if r["title"] and len(r["title"]) < TITLE_MIN]
    soft = [r for r in rows if any("soft-404" in p for p in r["problems"])]
    print(f"  titel >{TITLE_MAX}: {len(long_titles)} | titel <{TITLE_MIN}: {len(short_titles)} "
          f"| ingen meta: {len(no_meta)} | meta <{META_MIN}: {len(short_meta)} "
          f"| meta >{META_MAX}: {len(long_meta)} | soft-404: {len(soft)}")
    print(f"\nTitler over {TITLE_MAX} tegn: {len(long_titles)}")
    for row in sorted(long_titles, key=lambda r: -len(r["title"])):
        print(f"  {len(row['title'])} tegn  {row['url']}")

    if not args.no_report:
        write_report(rows, broken, long_titles)
    return 0 if not broken else 1


def write_report(rows, broken, long_titles):
    today = time.strftime("%Y-%m-%d")
    path = f"docs/library/titel-meta-audit-{today}.md"
    lines = [
        f"# Titel- og meta-audit — {today}",
        "",
        "Målt mod **steamgears.eu** med cache-buster, fordi sitet serverer cachede",
        "«Page Not Found»-dokumenter med HTTP 200 (se `publish_hubs.py --verify`).",
        "",
        "| Måling | Resultat |",
        "| --- | --- |",
        f"| URLs i sitemappen | {len(rows)} |",
        f"| URLs med mindst ét problem | {len(broken)} |",
        f"| Titler over {TITLE_MAX} tegn | {len(long_titles)} |",
        "",
        f"Regler: titel ≤ {TITLE_MAX} tegn (1/11), meta {META_MIN}–{META_MAX} tegn, ingen soft-404.",
        "",
    ]
    if long_titles:
        lines += [f"## Titler over {TITLE_MAX} tegn", "",
                  "| Tegn | URL | Titel |", "| --- | --- | --- |"]
        for row in sorted(long_titles, key=lambda r: -len(r["title"])):
            lines.append(f"| {len(row['title'])} | {row['url']} | {row['title']} |")
        lines.append("")
    other = [r for r in broken if len(r["title"]) <= TITLE_MAX]
    if other:
        lines += ["## Øvrige fund", "", "| URL | Problem |", "| --- | --- |"]
        for row in sorted(other, key=lambda r: r["url"]):
            lines.append(f"| {row['url']} | {'; '.join(row['problems'])} |")
        lines.append("")
    with open(path, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))
    print(f"\nRapport skrevet: {path}")


if __name__ == "__main__":
    sys.exit(main())
