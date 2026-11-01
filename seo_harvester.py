import os
import re
import sys
import json
import time
import argparse
import urllib.request
import urllib.parse
import xml.etree.ElementTree as ET
from html.parser import HTMLParser

KB_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "knowledge_base")
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")

class ArticleExtractor(HTMLParser):
    """Udtrækker ren tekst og overskrifter fra HTML og ignorerer støj."""
    def __init__(self):
        super().__init__()
        self.ignore_tags = {"script", "style", "nav", "footer", "header", "aside", "noscript", "svg", "form", "iframe"}
        self.ignore_depth = 0
        self.current_tag = ""
        self.title = ""
        self.chunks = []

    def handle_starttag(self, tag, attrs):
        if tag in self.ignore_tags:
            self.ignore_depth += 1
        elif self.ignore_depth == 0:
            self.current_tag = tag
            if tag in ("h1", "h2", "h3", "h4"):
                level = int(tag[1])
                self.chunks.append(f"\n\n{'#' * level} ")
            elif tag == "li":
                self.chunks.append("\n- ")
            elif tag in ("p", "div", "br", "tr"):
                self.chunks.append("\n")

    def handle_endtag(self, tag):
        if tag in self.ignore_tags and self.ignore_depth > 0:
            self.ignore_depth -= 1
        if tag == self.current_tag:
            self.current_tag = ""

    def handle_data(self, data):
        if self.ignore_depth == 0:
            clean = re.sub(r"\s+", " ", data)
            if self.current_tag == "title" and not self.title:
                self.title = clean.strip()
            elif clean.strip():
                self.chunks.append(clean)

    def get_markdown(self):
        text = "".join(self.chunks)
        text = re.sub(r"\n{3,}", "\n\n", text)
        return self.title, text.strip()

def fetch_url(url):
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": "Mozilla/5.0 (X11; Linux aarch64) AppleWebKit/537.36 Chrome/125.0 Safari/537.36",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
        }
    )
    with urllib.request.urlopen(req, timeout=25) as res:
        charset = res.headers.get_content_charset() or "utf-8"
        return res.read().decode(charset, errors="replace")

def parse_sitemap(sitemap_url, keyword_filter=None, max_urls=10):
    print(f"[*] Scanner sitemap: {sitemap_url}")
    xml_data = fetch_url(sitemap_url)
    root = ET.fromstring(xml_data)
    urls = []

    for elem in root.iter():
        if elem.tag.endswith("loc") and elem.text:
            loc = elem.text.strip()
            if loc.endswith(".xml") and loc != sitemap_url:
                # Rekursivt kald hvis det er et sitemap-index
                urls.extend(parse_sitemap(loc, keyword_filter, max_urls - len(urls)))
            else:
                if keyword_filter:
                    if not any(k.lower() in loc.lower() for k in keyword_filter):
                        continue
                urls.append(loc)
            if len(urls) >= max_urls:
                break
    return urls[:max_urls]

def distill_with_gemini(title, url, raw_text):
    """Kondenserer rå-artiklen til skarpe SEO-regler via Gemini, hvis nøgle findes."""
    if not GEMINI_API_KEY:
        return f"# {title}\n\nKilde: {url}\n\n{raw_text[:15000]}"

    api_url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={GEMINI_API_KEY}"
    prompt = f"""Udtræk de mest værdifulde, konkrete og tekniske SEO-regler fra denne guide.
Kilde: {title} ({url})

Omskriv indholdet til en skarp, struktureret Markdown-vidensfil med:
1. **Kerneprincipper & Ranking-faktorer** (konkrete tal, grænser og tekniske krav).
2. **E-Commerce & WooCommerce Anvendelse** (hvordan det bruges på produktsider, kategorier, billed-SEO og schema).
3. **Audit Tjekliste** (hvad vores SEO-bot skal tjekke automatisk på Steamgears.eu).

RÅ TEKST FRA GUIDE:
{raw_text[:30000]}
"""
    payload = json.dumps({
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"temperature": 0.1}
    }).encode("utf-8")

    try:
        req = urllib.request.Request(api_url, data=payload, headers={"Content-Type": "application/json"}, method="POST")
        with urllib.request.urlopen(req, timeout=60) as res:
            data = json.loads(res.read().decode("utf-8"))
            distilled = data["candidates"][0]["content"]["parts"][0]["text"]
            return f"<!-- Kilde: {url} -->\n{distilled}"
    except Exception as e:
        print(f"  [!] Gemini-destillering fejlede ({e}), gemmer rå Markdown i stedet.")
        return f"# {title}\n\nKilde: {url}\n\n{raw_text[:15000]}"

def slugify(text):
    slug = re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")
    return slug[:60] or "seo-guide"

def harvest_url(url, raw_only=False):
    print(f"[+] Suger: {url}")
    try:
        html = fetch_url(url)
        parser = ArticleExtractor()
        parser.feed(html)
        title, raw_md = parser.get_markdown()

        if len(raw_md) < 200:
            print(f"  [-] Sprang over (for lidt tekst fundet: {len(raw_md)} tegn)")
            return

        path_slug = slugify(urllib.parse.urlparse(url).path or title)
        out_file = os.path.join(KB_DIR, f"harvested_{path_slug}.md")

        if raw_only:
            final_md = f"# {title or path_slug}\n\nKilde: {url}\n\n{raw_md}"
        else:
            print("  [*] Destillerer SEO-regler med Gemini...")
            final_md = distill_with_gemini(title or path_slug, url, raw_md)

        os.makedirs(KB_DIR, exist_ok=True)
        with open(out_file, "w", encoding="utf-8") as f:
            f.write(final_md)
        print(f"  [OK] Gemt i: {out_file}")
    except Exception as e:
        print(f"  [FEJL] Kunne ikke hente {url}: {e}")

if __name__ == "__main__":
    ap = argparse.ArgumentParser(description="Sug SEO-guides fra URLs eller Sitemaps ind i knowledge_base/")
    ap.add_argument("--url", help="Hent en enkelt URL")
    ap.add_argument("--sitemap", help="Hent fra et XML-sitemap")
    ap.add_argument("--filter", default="seo,ecommerce,woocommerce,product,schema,speed",
                    help="Komma-separerede søgeord til at filtrere sitemap-URLs")
    ap.add_argument("--max", type=int, default=5, help="Max antal URLs fra et sitemap (default: 5)")
    ap.add_argument("--list", help="Sti til en .txt fil med én URL pr. linje")
    ap.add_argument("--raw", action="store_true", help="Gem rå tekst uden Gemini-destillering")
    args = ap.parse_args()

    targets = []
    if args.url:
        targets.append(args.url)
    if args.list and os.path.exists(args.list):
        with open(args.list, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#"):
                    targets.append(line)
    if args.sitemap:
        keywords = [k.strip() for k in args.filter.split(",") if k.strip()]
        targets.extend(parse_sitemap(args.sitemap, keywords, args.max))

    if not targets:
        print("Brug f.eks.: python seo_harvester.py --url https://...")
        sys.exit(1)

    for idx, t_url in enumerate(targets, 1):
        print(f"\n--- [{idx}/{len(targets)}] ---")
        harvest_url(t_url, raw_only=args.raw)
        time.sleep(1.5)
