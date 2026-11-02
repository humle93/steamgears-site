#!/usr/bin/env python3
"""seo_audit_crawl.py — bredt SEO-crawl af steamgears.eu.

Kør:  python3 tools/seo_audit_crawl.py
Ud:   reports/seo-audit-data-<dato>.json  (+ raw REST-snapshots i /tmp)

Måler, ikke konkluderer:
  - robots.txt, sitemap-index og alle børn-sitemaps
  - hver sitemap-URL: status, svarstid, bytes, title, meta description, H1,
    canonical, robots-meta, og:image, JSON-LD-typer, billeder uden alt,
    ordantal, interne/eksterne links, render-blocking CSS, scripts
  - REST: posts, pages, produkter, produktkategorier, kategorier
  - Googlebot- og bingbot-adgang (WAF)
  - tekniske ruter: /feed/, søgning, 404, kurv, kasse, konto, /wp-json/
  - duplikate titles og descriptions

Henter med curl uden browser-UA: Simplys WAF svarer 454/455 på browser- og
bot-UA fra denne maskine, men lader curl igennem. Pacing 0,45 s.
"""

from __future__ import annotations

import json
import re
import subprocess
import time
from datetime import date
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlparse

ROOT = Path(__file__).resolve().parent.parent
SITE = "https://steamgears.eu"
PACE = 0.45
DATE = date.today().isoformat()
BOT_UA = "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"
BING_UA = "Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)"


def fetch(url: str, ua: str | None = None, timeout: int = 30) -> dict:
    cmd = ["curl", "-sS", "--max-time", str(timeout), "-o", "/tmp/seo_body",
           "-w", "%{http_code} %{size_download} %{time_total}"]
    if ua:
        cmd += ["-A", ua]
    cmd.append(url)
    started = time.time()
    proc = subprocess.run(cmd, capture_output=True, text=True)
    parts = (proc.stdout or "").split()
    try:
        status, size, total = int(parts[0]), int(parts[1]), float(parts[2])
    except Exception:
        status, size, total = 0, 0, round(time.time() - started, 2)
    body = Path("/tmp/seo_body").read_bytes() if Path("/tmp/seo_body").exists() else b""
    return {"status": status, "body": body, "bytes": size, "seconds": round(total, 2)}


class Probe(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.title = ""
        self._in_title = self._in_h1 = self._in_h2 = self._in_ld = False
        self.meta: dict[str, str] = {}
        self.link: dict[str, str] = {}
        self.h1: list[str] = []
        self.h2: list[str] = []
        self.jsonld: list[str] = []
        self._ld = ""
        self.imgs: list[dict] = []
        self.links: list[str] = []
        self.lang = ""
        self.text: list[str] = []
        self.scripts = 0
        self.style_blocks = 0
        self.ld_scripts = 0

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == "html":
            self.lang = a.get("lang", "")
        elif tag == "title":
            self._in_title = True
        elif tag == "meta":
            key = a.get("property") or a.get("name")
            if key:
                self.meta[key.lower()] = (a.get("content") or "").strip()
        elif tag == "link":
            if a.get("rel"):
                self.link[a["rel"].lower()] = a.get("href", "")
        elif tag == "h1":
            self._in_h1 = True
        elif tag == "h2":
            self._in_h2 = True
        elif tag == "img":
            self.imgs.append({"src": a.get("src", ""), "alt": a.get("alt", None)})
        elif tag == "a" and a.get("href"):
            self.links.append(a["href"])
        elif tag == "script":
            self.scripts += 1
            if "ld+json" in (a.get("type") or ""):
                self._in_ld = True
                self._ld = ""
                self.ld_scripts += 1
        elif tag == "style":
            self.style_blocks += 1
        elif tag in ("p", "div", "li", "br", "h1", "h2", "h3", "h4", "td", "section", "span"):
            self.text.append(" ")

    def handle_endtag(self, tag):
        if tag == "title":
            self._in_title = False
        elif tag == "h1":
            self._in_h1 = False
        elif tag == "h2":
            self._in_h2 = False
        elif tag == "script" and self._in_ld:
            self._in_ld = False
            self.jsonld.append(self._ld)

    def handle_data(self, data):
        chunk = data.strip()
        if not chunk:
            return
        if self._in_title:
            self.title += chunk + " "
        elif self._in_h1:
            self.h1.append(chunk)
        elif self._in_h2:
            self.h2.append(chunk)
        elif self._in_ld:
            self._ld += data
        else:
            self.text.append(chunk)


def ld_types(blocks: list[str]) -> list[str]:
    found: list[str] = []

    def walk(node):
        if isinstance(node, dict):
            t = node.get("@type")
            if isinstance(t, str):
                found.append(t)
            elif isinstance(t, list):
                found.extend([x for x in t if isinstance(x, str)])
            for v in node.values():
                walk(v)
        elif isinstance(node, list):
            for v in node:
                walk(v)

    for block in blocks:
        try:
            walk(json.loads(block))
        except Exception:
            found.append("_invalid_json")
    out: list[str] = []
    for t in found:
        if t not in out:
            out.append(t)
    return out


def analyse(url: str, res: dict) -> dict:
    raw = res["body"].decode("utf-8", "replace")
    p = Probe()
    try:
        p.feed(raw)
    except Exception:
        pass
    host = urlparse(SITE).netloc
    internal = [u for u in p.links if urlparse(urljoin(url, u)).netloc in (host, "")]
    title = " ".join(p.title.split())
    bottom = raw.rsplit("</head>", 1)[-1]
    return {
        "url": url,
        "path": urlparse(url).path,
        "status": res["status"],
        "seconds": res["seconds"],
        "bytes": res["bytes"],
        "title": title,
        "title_len": len(title),
        "description": p.meta.get("description", ""),
        "description_len": len(p.meta.get("description", "")),
        "h1": " ".join(" ".join(p.h1).split()),
        "h1_count": len(p.h1),
        "h2_count": len(p.h2),
        "canonical": p.link.get("canonical", ""),
        "robots_meta": p.meta.get("robots", ""),
        "og_image": p.meta.get("og:image", ""),
        "twitter_card": p.meta.get("twitter:card", ""),
        "lang": p.lang,
        "jsonld_types": ld_types(p.jsonld),
        "jsonld_blocks": len(p.jsonld),
        "images": len(p.imgs),
        "images_no_alt": sum(1 for i in p.imgs if not i["alt"]),
        "internal_links": len(internal),
        "external_links": len(p.links) - len(internal),
        "word_count": len(" ".join(p.text).split()),
        "scripts": p.scripts,
        "style_blocks": p.style_blocks,
        "stylesheet_links": len(re.findall(r'<link[^>]+rel=["\']stylesheet', raw, re.I)),
        "preloads": len(re.findall(r'rel=["\']preload', raw, re.I)),
        "soft_404": "page not found" in title.lower(),
        "has_gtm": "googletagmanager" in raw,
        "ga_ids": sorted(set(re.findall(r"G-[A-Z0-9]{8,}", raw))),
        "viewport": p.meta.get("viewport", ""),
        "adsense": "adsbygoogle" in raw,
        "breadcrumb_html": 'class="breadcrumb' in raw.lower() or "rank-math-breadcrumb" in raw,
        "html_size_kb": round(res["bytes"] / 1024),
        "scripts_in_body": len(re.findall(r"<script", bottom, re.I)),
    }


def sitemap_urls() -> tuple[list[str], dict, dict]:
    idx = fetch(f"{SITE}/sitemap_index.xml")
    text = idx["body"].decode("utf-8", "replace")
    children = [c for c in re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", text) if c.endswith(".xml")]
    urls: list[str] = []
    detail = {}
    for child in children:
        time.sleep(PACE)
        sub = fetch(child)
        body = sub["body"].decode("utf-8", "replace")
        locs = [u for u in re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", body) if not u.endswith(".xml")]
        lm = re.findall(r"<lastmod>\s*([^<\s]+)\s*</lastmod>", body)
        detail[child] = {"status": sub["status"], "urls": len(locs),
                         "lastmod": max(lm) if lm else ""}
        urls.extend(locs)
    meta = {"status": idx["status"], "bytes": idx["bytes"],
            "children": children, "lastmod": max(re.findall(
                r"<lastmod>\s*([^<\s]+)\s*</lastmod>", text) or [""])}
    return sorted(set(urls)), detail, meta


REST = {
    "posts": "posts?per_page=100&_fields=id,slug,link,title,excerpt,featured_media,date,modified,categories,content",
    "pages": "pages?per_page=100&_fields=id,slug,link,title,featured_media,parent,content,date",
    "products": "product?per_page=100&_fields=id,slug,link,name,price,regular_price,sale_price,"
                "short_description,description,images,stock_status,categories,average_rating,"
                "review_count,type,sku,featured",
    "product_categories": "product_cat?per_page=100&_fields=id,slug,name,count,description,link,parent",
    "categories": "categories?per_page=100&_fields=id,slug,name,count,link",
}


def pull_rest() -> dict:
    out = {}
    for key, query in REST.items():
        time.sleep(PACE)
        res = fetch(f"{SITE}/wp-json/wp/v2/{query}")
        try:
            data = json.loads(res["body"].decode("utf-8", "replace"))
        except Exception:
            data = []
        out[key] = {"status": res["status"], "count": len(data) if isinstance(data, list) else 0,
                    "items": data if isinstance(data, list) else []}
        print(f"  REST {key}: {res['status']} · {out[key]['count']}", flush=True)
    return out


def main() -> None:
    out: dict = {"date": DATE, "site": SITE}
    robots = fetch(f"{SITE}/robots.txt")
    out["robots"] = {"status": robots["status"],
                     "text": robots["body"].decode("utf-8", "replace")}

    urls, children, idx = sitemap_urls()
    out["sitemap"] = {"index": idx, "children": children, "url_count": len(urls)}

    print(f"sitemap: {len(urls)} URL'er i {len(children)} børn", flush=True)
    out["rest"] = pull_rest()

    pages = []
    for i, url in enumerate(urls, 1):
        res = fetch(url)
        row = analyse(url, res)
        pages.append(row)
        print(f"[{i}/{len(urls)}] {row['status']} {row['title_len']:>3}t {row['word_count']:>5}w {row['path']}", flush=True)
        if row["status"] in (429, 454, 456):
            print("  WAF — venter 120 s", flush=True)
            time.sleep(120)
        time.sleep(PACE)
    out["pages"] = pages

    bot = []
    for label, ua in (("googlebot", BOT_UA), ("bingbot", BING_UA)):
        for url in (f"{SITE}/", f"{SITE}/robots.txt", f"{SITE}/sitemap_index.xml",
                    f"{SITE}/shop/", f"{SITE}/start-here/"):
            time.sleep(0.8)
            r = fetch(url, ua=ua)
            body = r["body"].decode("utf-8", "replace")
            bot.append({"bot": label, "url": url, "status": r["status"], "bytes": r["bytes"],
                        "snippet": " ".join(re.sub(r"<[^>]+>", " ", body).split())[:160]})
    out["bots"] = bot

    extra = {}
    for path in ["/feed/", "/?s=steampunk", "/this-page-should-404/", "/cart/", "/checkout/",
                 "/my-account/", "/wp-json/", "/opt-out-preferences/", "/product-category/posters/"]:
        time.sleep(PACE)
        r = fetch(f"{SITE}{path}")
        body = r["body"].decode("utf-8", "replace")
        title = re.search(r"<title[^>]*>(.*?)</title>", body, re.S | re.I)
        robots_m = re.search(r'<meta[^>]+name=["\']robots["\'][^>]+content=["\']([^"\']+)', body, re.I)
        extra[path] = {"status": r["status"], "bytes": r["bytes"],
                       "title": " ".join(title.group(1).split())[:120] if title else "",
                       "robots": robots_m.group(1) if robots_m else "",
                       "canonical": (re.search(r'rel=["\']canonical["\'][^>]*href=["\']([^"\']+)', body, re.I)
                                     or [None, ""])[1] if re.search(
                           r'rel=["\']canonical["\'][^>]*href=["\']([^"\']+)', body, re.I) else ""}
    out["extra"] = extra

    def dups(key: str) -> dict:
        seen: dict[str, list[str]] = {}
        for pg in pages:
            val = (pg.get(key) or "").strip()
            if val:
                seen.setdefault(val, []).append(pg["path"])
        return {k: v for k, v in seen.items() if len(v) > 1}

    out["duplicate_titles"] = dups("title")
    out["duplicate_descriptions"] = dups("description")

    target = ROOT / "reports" / f"seo-audit-data-{DATE}.json"
    target.parent.mkdir(exist_ok=True)
    target.write_text(json.dumps(out, ensure_ascii=False, indent=1), encoding="utf-8")
    print(f"\n{len(pages)} sider crawlet -> {target.relative_to(ROOT)}", flush=True)


if __name__ == "__main__":
    main()
