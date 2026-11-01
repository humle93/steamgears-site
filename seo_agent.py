import os
import re
import glob
import json
import base64
import datetime
import urllib.request
import urllib.error

WP_SITE = "https://steamgears.eu"
WP_USER = os.getenv("WP_USER", "")
WP_APP_PASS = os.getenv("WP_APP_PASS", "")

def get_gemini_key():
    key = os.getenv("GEMINI_API_KEY", "")
    if key:
        return key
    if os.path.exists(".env"):
        with open(".env", "r", encoding="utf-8") as ef:
            m = re.search(r"(AIza[0-9A-Za-z\-_]{35})", ef.read())
            if m:
                return m.group(1)
    for path in glob.glob(os.path.expanduser("~/.gemini/**/*"), recursive=True) + [os.path.expanduser("~/.gemini/.env"), os.path.expanduser("~/.bashrc")]:
        if os.path.isfile(path):
            try:
                with open(path, "r", encoding="utf-8", errors="ignore") as f:
                    match = re.search(r"(AIza[0-9A-Za-z\-_]{35})", f.read())
                    if match:
                        return match.group(1)
            except Exception:
                pass
    return ""

GEMINI_API_KEY = get_gemini_key()

AUTH_FILE = os.path.expanduser("~/.gemini/steamgears_auth.json")
if (not WP_USER or not WP_APP_PASS) and os.path.exists(AUTH_FILE):
    with open(AUTH_FILE, "r") as f:
        auth_data = json.load(f)
        WP_USER = auth_data.get("user", "")
        WP_APP_PASS = auth_data.get("password", "")

def get_wp_headers():
    headers = {
        "Content-Type": "application/json",
        "User-Agent": "Steamgears-SEO-Auditor/1.0"
    }
    if WP_USER and WP_APP_PASS:
        creds = f"{WP_USER}:{WP_APP_PASS}"
        b64 = base64.b64encode(creds.encode("utf-8")).decode("utf-8")
        headers["Authorization"] = f"Basic {b64}"
    return headers

def load_seo_knowledge():
    kb_texts = []
    for path in sorted(glob.glob("knowledge_base/*.*")):
        with open(path, "r", encoding="utf-8") as f:
            kb_texts.append(f"--- KILDE: {os.path.basename(path)} ---\n{f.read()[:8000]}")
    return "\n\n".join(kb_texts)

def fetch_wp_json(endpoint):
    url = f"{WP_SITE}{endpoint}"
    req = urllib.request.Request(url, headers=get_wp_headers())
    try:
        with urllib.request.urlopen(req, timeout=20) as res:
            return json.loads(res.read().decode("utf-8"))
    except Exception as e:
        return {"error": str(e), "endpoint": endpoint}

def call_royal_mcp(method, params=None):
    url = f"{WP_SITE}/wp-json/royal-mcp/v1/mcp"
    payload = json.dumps({"jsonrpc": "2.0", "method": method, "params": params or {}, "id": 1}).encode("utf-8")
    req = urllib.request.Request(url, data=payload, headers=get_wp_headers(), method="POST")
    try:
        with urllib.request.urlopen(req, timeout=20) as res:
            return json.loads(res.read().decode("utf-8"))
    except Exception as e:
        return {"mcp_status": str(e)}

def gather_site_snapshot():
    print("[1/3] Henter live-data fra Steamgears.eu...")
    snapshot = {
        "mcp_tools": call_royal_mcp("tools/list"),
        "pages": [],
        "posts": [],
        "products": []
    }

    pages = fetch_wp_json("/wp-json/wp/v2/pages?per_page=20")
    if isinstance(pages, list):
        for p in pages:
            snapshot["pages"].append({
                "id": p.get("id"),
                "slug": p.get("slug"),
                "link": p.get("link"),
                "title": p.get("title", {}).get("rendered", ""),
                "excerpt_len": len(p.get("excerpt", {}).get("rendered", "")),
                "content_len": len(p.get("content", {}).get("rendered", "")),
                "yoast_head_json": p.get("yoast_head_json", None)
            })

    products = fetch_wp_json("/wp-json/wc/store/v1/products?per_page=30")
    if isinstance(products, list):
        for prod in products:
            images = prod.get("images", [])
            missing_alt = sum(1 for img in images if not img.get("alt"))
            snapshot["products"].append({
                "id": prod.get("id"),
                "name": prod.get("name"),
                "slug": prod.get("slug"),
                "permalink": prod.get("permalink"),
                "short_description": prod.get("short_description", "")[:200],
                "description_length": len(prod.get("description", "")),
                "image_count": len(images),
                "images_missing_alt": missing_alt,
                "categories": [c.get("name") for c in prod.get("categories", [])]
            })
    else:
        snapshot["products"] = products

    return snapshot

def generate_seo_plan(knowledge, site_data):
    print("[2/3] Analyserer med Gemini og bygger optimeringsplan...")
    if not GEMINI_API_KEY:
        raise ValueError("Kunne ikke finde GEMINI_API_KEY automatisk. Kør: export GEMINI_API_KEY='din_nøgle'")

    url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={GEMINI_API_KEY}"
    prompt = f"""Du er Lead Technical SEO & E-Commerce Architect for Steamgears.eu.
Brug nedenstående SEO-vidensbase til at lave et knivskarpt SEO-audit af sidens live-data.

=== SEO VIDENSBASE ===
{knowledge}

=== LIVE DATA FRA STEAMGEARS.EU ===
{json.dumps(site_data, indent=2, ensure_ascii=False)}

Lever en konkret, handlingsorienteret optimeringsplan på dansk opdelt i:
1. **Kritisk Status & Lavthængende Frugter** (hvad koster flest klik lige nu?).
2. **Produkt- & Kategori-optimering** (konkrete forslag til nye Title Tags, Meta Descriptions, Alt-tekster og omskrivning af tynde Printify-beskrivelser for de specifikke produkter i listen).
3. **Teknisk SEO & Struktur** (slugs, interne links, manglende sider/kollektioner).
4. **30-Dages Action Plan & MCP Auto-Fixes** (konkrete JSON-payloads eller ændringer, vi kan skubbe direkte tilbage til WordPress via vores MCP-bro).
"""

    payload = json.dumps({
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"temperature": 0.2}
    }).encode("utf-8")

    req = urllib.request.Request(url, data=payload, headers={"Content-Type": "application/json"}, method="POST")
    with urllib.request.urlopen(req, timeout=120) as res:
        result = json.loads(res.read().decode("utf-8"))
        return result["candidates"][0]["content"]["parts"][0]["text"]

if __name__ == "__main__":
    os.makedirs("reports", exist_ok=True)
    kb = load_seo_knowledge()
    snapshot = gather_site_snapshot()
    report = generate_seo_plan(kb, snapshot)

    today = datetime.date.today().isoformat()
    dated_file = f"reports/seo_audit_{today}.md"
    latest_file = "reports/LATEST_OPTIMIZATION_PLAN.md"

    print(f"[3/3] Gemmer rapport i {latest_file}...")
    for path in (dated_file, latest_file):
        with open(path, "w", encoding="utf-8") as f:
            f.write(report)
    print("[OK] SEO Audit færdig!")
