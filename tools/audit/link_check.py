#!/usr/bin/env python3
"""
Link check for steamgears.eu that does not trust the status code.

Why this exists: the site's page cache stores a 404 response and replays it
with HTTP 200. A URL that has never been requested answers 404; three seconds
later the same URL answers 200 with the theme's "Page Not Found" template.
Measured 2/11 2026. So a plain status check reports "all fine" for any URL
someone (or a bot) has already hit once.

Two defences:
  1. every request carries a fresh query parameter, so the cache cannot answer
  2. a page counts as dead if the body says so, whatever the status says

    python3 tools/audit/link_check.py                 # every URL in the sitemap
    python3 tools/audit/link_check.py --crawl         # plus every internal link
    python3 tools/audit/link_check.py /inspiration/ /product-category/goggles/

Exit code 1 if anything is dead, so it can be the monthly routine's gate.
"""

from __future__ import annotations

import argparse
import re
import sys
import urllib.error
import urllib.request
import uuid
from concurrent.futures import ThreadPoolExecutor
from urllib.parse import urljoin, urlparse

BASE = "https://steamgears.eu"
UA = {"User-Agent": "steamgears-linkcheck/1.0"}
SKIP_EXT = re.compile(r"\.(jpg|jpeg|png|webp|gif|svg|pdf|css|js|xml|ico|zip)$", re.I)
SKIP_PATH = ("/wp-admin", "/wp-json", "/wp-login", "/feed", "/cdn-cgi", "/xmlrpc")


def fetch(path: str, timeout: int = 25) -> tuple:
    """(status, html). Cache-busted: a unique query string defeats the page cache."""
    url = f"{BASE}{path}" + ("&" if "?" in path else "?") + "nc=" + uuid.uuid4().hex[:8]
    try:
        req = urllib.request.Request(url, headers=UA)
        with urllib.request.urlopen(req, timeout=timeout) as res:
            return res.status, res.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, ""
    except Exception as exc:
        return 0, f"<error>{exc}</error>"


def is_dead(status: int, html: str) -> tuple:
    """(dead?, reason). Body beats status: a cached 404 is still a 404."""
    if status == 0:
        return True, "transport"
    if status >= 400:
        return True, f"http-{status}"
    title = re.search(r"<title>(.*?)</title>", html, re.S)
    if title and "Page Not Found" in title.group(1):
        return True, "soft-404"
    if "error404" in html[:6000]:
        return True, "soft-404"
    return False, "ok"


def internal_links(path: str, html: str) -> set:
    out = set()
    for match in re.finditer(r'(?<!data-wp-bind--)\bhref="([^"]+)"', html):
        url = urljoin(BASE + path, match.group(1))
        parts = urlparse(url)
        if parts.netloc not in ("steamgears.eu", "www.steamgears.eu"):
            continue
        if not parts.path or SKIP_EXT.search(parts.path):
            continue
        if any(s in parts.path for s in SKIP_PATH):
            continue
        out.add(parts.path)
    return out


def sitemap_paths() -> list:
    paths = []
    status, body = fetch("/sitemap_index.xml")
    if status != 200:
        return paths
    for sub in re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", body)[:20]:
        path = urlparse(sub).path
        status, body = fetch(path)
        if status != 200:
            continue
        for loc in re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", body):
            p = urlparse(loc).path
            if p and not SKIP_EXT.search(p):
                paths.append(p)
    return sorted(set(paths))


def main() -> None:
    ap = argparse.ArgumentParser(description="Soft-404 aware link check")
    ap.add_argument("paths", nargs="*", help="paths to check; default is the sitemap")
    ap.add_argument("--crawl", action="store_true", help="also check every internal link found")
    ap.add_argument("--workers", type=int, default=6)
    args = ap.parse_args()

    paths = [p if p.startswith("/") else "/" + p for p in args.paths] or sitemap_paths()
    if not paths:
        print("no URLs to check — give paths or make sure the sitemap answers")
        raise SystemExit(2)

    checked: dict = {}

    def work(path):
        status, html = fetch(path)
        dead, reason = is_dead(status, html)
        return path, status, dead, reason, html

    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        for path, status, dead, reason, html in pool.map(work, paths):
            checked[path] = (status, dead, reason)
            if args.crawl and not dead:
                for link in internal_links(path, html):
                    if link not in checked and link not in paths:
                        status2, html2 = fetch(link)
                        dead2, reason2 = is_dead(status2, html2)
                        checked[link] = (status2, dead2, reason2)

    dead_rows = {p: v for p, v in checked.items() if v[1]}
    print(f"{len(checked)} URL(s): {len(checked) - len(dead_rows)} ok, {len(dead_rows)} dead")
    for path, (status, _, reason) in sorted(dead_rows.items()):
        print(f"  DEAD  {reason:10} http {status}  {path}")

    soft = sum(1 for v in checked.values() if v[2] == "soft-404")
    if soft:
        print(f"\n{soft} of them answered 200 with the not-found template — that is the "
              f"page cache replaying a 404, not a working page.")
    raise SystemExit(1 if dead_rows else 0)


if __name__ == "__main__":
    main()
