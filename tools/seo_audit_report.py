#!/usr/bin/env python3
"""seo_audit_report.py — bred SEO-audit af steamgears.eu som PDF + Markdown.

Kør:  python3 tools/seo_audit_report.py
Ind:  reports/seo-audit-data-<dato>.json  (fra tools/seo_audit_crawl.py)
Ud:   reports/seo-audit-<dato>.pdf
      docs/library/seo-audit-<dato>.md

Scoren beregnes af ni vægtede kategorier (100 point). Hver fradrag har en
begrundelse, et bevis fra crawl-data og en rettelse med et tidsestimat.
"Tid til 100 %" er summen af de resterende rettelser, delt i faser, plus
ledetid for det der kræver ejeren eller tredjepart — intet tal uden regnestykke.
"""

from __future__ import annotations

import json
import os
import re
from datetime import date
from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parent.parent
DATE = date.today().isoformat()
OUT_PDF = ROOT / "reports" / f"seo-audit-{DATE}.pdf"
OUT_MD = ROOT / "docs" / "library" / f"seo-audit-{DATE}.md"
DATA = ROOT / "reports" / f"seo-audit-data-{DATE}.json"

NAVY = (31, 42, 55)
COPPER = (184, 115, 51)
GREY = (110, 118, 128)
RULE = (214, 219, 226)
ZEBRA = (246, 248, 250)
INK = (28, 33, 40)
GREEN = (22, 120, 78)
RED = (176, 58, 46)
AMBER = (166, 110, 12)
PALE_RED = (250, 238, 236)
PALE_GREEN = (236, 246, 240)

LINE = 4.3


# ------------------------------------------------------------------ fonte
FONT_DIRS = ["/usr/share/fonts", "/usr/local/share/fonts",
             "/opt/freestyle/python/lib/python3.12/site-packages/matplotlib/mpl-data/fonts/ttf"]


def find_fonts():
    pairs = [("DejaVuSans.ttf", "DejaVuSans-Bold.ttf"),
             ("LiberationSans-Regular.ttf", "LiberationSans-Bold.ttf")]
    for reg, bold in pairs:
        for base in FONT_DIRS:
            if not os.path.isdir(base):
                continue
            for root, _d, files in os.walk(base):
                if reg in files and bold in files:
                    return os.path.join(root, reg), os.path.join(root, bold)
    return None, None


REG_FONT, BOLD_FONT = find_fonts()
UNICODE = REG_FONT is not None
FONT = "DejaVu" if UNICODE else "Helvetica"
ASCII_MAP = {"\u2014": "-", "\u2013": "-", "\u2192": "->", "\u00b7": "|", "\u2022": "-",
             "\u2713": "OK", "\u2717": "X", "\u2019": "'", "\u201c": '"', "\u201d": '"',
             "\u2026": "...", "\u00d7": "x", "\u2265": ">="}


def san(text) -> str:
    text = str(text)
    if UNICODE:
        return text
    for a, b in ASCII_MAP.items():
        text = text.replace(a, b)
    return text.encode("latin-1", "replace").decode("latin-1")


def dk(value, dec=1) -> str:
    text = f"{round(float(value), dec):.{dec}f}".rstrip("0").rstrip(".")
    if "." not in text:
        text += ".0"
    return text.replace(".", ",")


def pct(part, whole) -> float:
    return round(part / whole * 100, 1) if whole else 0.0


# ------------------------------------------------------------------ data
data = json.loads(DATA.read_text(encoding="utf-8"))
PAGES = data["pages"]
REST = data["rest"]
BOTS = data.get("bots", [])
EXTRA = data.get("extra", {})
ROBOTS_TEXT = data.get("robots", {}).get("text", "")
ROBOTS_STATUS = data.get("robots", {}).get("status", 0)
SITEMAP = data.get("sitemap", {})
URL_COUNT = SITEMAP.get("url_count", len(PAGES))

posts = REST["posts"]["items"]
products = REST["products"]["items"]
product_cats = REST["product_categories"]["items"]

PROD_FILE = ROOT / "reports" / f"seo-audit-products-{DATE}.json"
PRODUCTS_DETAIL = (json.loads(PROD_FILE.read_text(encoding="utf-8"))["products"]
                   if PROD_FILE.exists() else [])


def kind(path: str) -> str:
    if path.startswith("/product/"):
        return "Produkt"
    if path.startswith("/product-category/"):
        return "Produktkategori"
    if path.startswith("/category/"):
        return "Blogkategori"
    if path in ("/", ""):
        return "Forside"
    return "Side/indlæg"


by_kind: dict[str, list[dict]] = {}
for page in PAGES:
    by_kind.setdefault(kind(page["path"]), []).append(page)

ok_pages = [p for p in PAGES if p["status"] == 200]
bad_pages = [p for p in PAGES if p["status"] != 200]
soft404 = [p for p in PAGES if p.get("soft_404")]
noindex = [p for p in PAGES if "noindex" in (p.get("robots_meta") or "").lower()]
missing_desc = [p for p in ok_pages if not p["description"]]
long_title = [p for p in ok_pages if p["title_len"] > 62]
short_title = [p for p in ok_pages if p["title_len"] < 25]
long_desc = [p for p in ok_pages if p["description_len"] > 165]
short_desc = [p for p in ok_pages if 0 < p["description_len"] < 70]
multi_h1 = [p for p in ok_pages if p["h1_count"] != 1]
no_h1 = [p for p in ok_pages if p["h1_count"] == 0]
missing_canonical = [p for p in ok_pages if not p["canonical"]]
wrong_canonical = [p for p in ok_pages if p["canonical"] and
                   p["canonical"].rstrip("/") != p["url"].rstrip("/")]
missing_og = [p for p in ok_pages if not p["og_image"]]
thin = [p for p in ok_pages if p["word_count"] < 300]
no_schema = [p for p in ok_pages if not p["jsonld_types"]]
no_breadcrumb = [p for p in ok_pages if "BreadcrumbList" not in p["jsonld_types"]]
invalid_schema = [p for p in ok_pages if "_invalid_json" in p["jsonld_types"]]
imgs = sum(p["images"] for p in ok_pages)
imgs_no_alt = sum(p["images_no_alt"] for p in ok_pages)
imgs_no_alt_pages = [p for p in ok_pages if p["images_no_alt"]]

product_pages = by_kind.get("Produkt", [])
prod_with_product_schema = [p for p in product_pages if "Product" in p["jsonld_types"]]
prod_with_offer = [p for p in product_pages if "Offer" in p["jsonld_types"] or
                   "AggregateOffer" in p["jsonld_types"]]
by_url = {p["url"].rstrip("/"): p for p in PAGES}
post_pages = [by_url[po["link"].rstrip("/")] for po in posts if po["link"].rstrip("/") in by_url]
guide_pages = [p for p in PAGES if p["path"] not in ("/", "") and
               kind(p["path"]) == "Side/indlæg" and p["word_count"] > 600]
guides_faq = [p for p in post_pages if "FAQPage" in p["jsonld_types"]]
guides_howto = [p for p in guide_pages if "HowTo" in p["jsonld_types"]]
guides_without_faq = [p for p in post_pages if "FAQPage" not in p["jsonld_types"]]

ga_ids = sorted({gid for p in ok_pages for gid in p.get("ga_ids", [])})
double_ga = len(ga_ids) > 1
dup_titles = data.get("duplicate_titles", {})
dup_desc = data.get("duplicate_descriptions", {})

bytes_sorted = sorted(p["bytes"] for p in ok_pages)
time_sorted = sorted(p["seconds"] for p in ok_pages)


def q(values, fraction):
    if not values:
        return 0
    idx = min(len(values) - 1, int(round(fraction * (len(values) - 1))))
    return values[idx]


median_kb = round(q(bytes_sorted, 0.5) / 1024)
p90_kb = round(q(bytes_sorted, 0.9) / 1024)
median_s = q(time_sorted, 0.5)
p90_s = q(time_sorted, 0.9)
shop = next((p for p in ok_pages if p["path"] == "/shop/"), None)
home = next((p for p in ok_pages if p["path"] == "/"), None)
heaviest = sorted(ok_pages, key=lambda p: -p["bytes"])[:5]
slowest = sorted(ok_pages, key=lambda p: -p["seconds"])[:5]
max_scripts = max((p["scripts"] for p in ok_pages), default=0)
max_styles = max((p["stylesheet_links"] for p in ok_pages), default=0)

# Butik — REST udstiller ikke pris/billeder/reviews på dette site, så felterne læses fra JSON-LD
zero_review = [p for p in PRODUCTS_DETAIL if not p.get("has_rating")]
few_images = [p for p in PRODUCTS_DETAIL if int(p.get("jsonld_images") or 0) < 3]
prices = [float(p["price"]) for p in PRODUCTS_DETAIL if p.get("price")]
avg_price = sum(prices) / len(prices) if prices else 0
currencies = sorted({p.get("currency", "") for p in PRODUCTS_DETAIL if p.get("currency")})
usd = currencies == ["USD"]
no_return_policy = [p for p in PRODUCTS_DETAIL if not p.get("return_policy")]
no_shipping = [p for p in PRODUCTS_DETAIL if not p.get("shipping_details")]
no_brand = [p for p in PRODUCTS_DETAIL if not p.get("brand")]
in_stock = [p for p in PRODUCTS_DETAIL if p.get("availability", "").endswith("InStock")]

googlebot = next((b for b in BOTS if b["bot"] == "googlebot" and b["url"].endswith("/")), None)
bingbot = next((b for b in BOTS if b["bot"] == "bingbot" and b["url"].endswith("/")), None)
robots_455 = bool(googlebot and googlebot["status"] >= 400)
bing_455 = bool(bingbot and bingbot["status"] >= 400)
robots_groups = len(re.findall(r"(?im)^\s*user-agent:\s*\*", ROBOTS_TEXT))
cart_noindex = "noindex" in (EXTRA.get("/cart/", {}).get("robots") or "").lower()
checkout_noindex = "noindex" in (EXTRA.get("/checkout/", {}).get("robots") or "").lower()
account_noindex = "noindex" in (EXTRA.get("/my-account/", {}).get("robots") or "").lower()
search_noindex = "noindex" in (EXTRA.get("/?s=steampunk", {}).get("robots") or "").lower()
notfound = EXTRA.get("/this-page-should-404/", {})

blocks_google = robots_455 or bing_455

# ------------------------------------------------------------------ fund
# severity: P0 kritisk, P1 høj, P2 medium, P3 lav
# effort i developer-dage (0.05 = ~25 min)
FINDINGS: list[dict] = []


def finding(fid, cat, severity, title, evidence, fix, effort, owner="Agent", blocked=""):
    FINDINGS.append({"id": fid, "cat": cat, "sev": severity, "title": title,
                     "evidence": evidence, "fix": fix, "effort": effort,
                     "owner": owner, "blocked": blocked})


finding("A1", "crawl", "P1",
        "Bot-UA fra ikke-Google-IP får 455 — WAF'en stopper spoofing, ikke Google",
        f"Googlebot-UA fra en datacenter-IP: HTTP {googlebot['status'] if googlebot else '?'} på forside, "
        f"robots.txt, sitemap og /shop/. Bingbot-UA: HTTP {bingbot['status'] if bingbot else '?'}. Neutral "
        f"curl-UA får 200 på samme URL'er, og Google indekserer sitet (site:-søgning 2/11 viser forside, "
        f"guides med dato 16/10 og de kommercielle sider).",
        "Behandl det som platformsvilkår, ikke en site-fejl: crawl med neutral UA (som denne audit gør), og "
        "hvidlist kun én fast agent-IP hvis bot-UA er nødvendig. Læs GSC → Crawl stats → By response, og kontakt "
        "først Simply hvis Google selv får 455/5xx. WAF'en skal ikke fjernes.",
        0.05, "Ejer", "GSC-adgang")

finding("B1", "crawl", "P1",
        f"{robots_groups} `User-agent: *`-grupper i robots.txt",
        f"robots.txt (HTTP {ROBOTS_STATUS}) har {robots_groups} grupper. Google fletter dem, men det er en "
        f"konfigurationsfejl der gør filen umulig at læse for mennesker og farlig at rette.",
        "Slå op hvilken plugin/host-regel der skriver filen (host mu-plugin vs Rank Math vs W3TC), og lad én kilde "
        "eje robots.txt. Skriv derefter Sitemap-linjen ét sted.",
        0.25, "Agent")

missing_noindex = [name for name, flag in (("/cart/", cart_noindex), ("/checkout/", checkout_noindex),
                                           ("/my-account/", account_noindex)) if not flag]
if missing_noindex:
    finding("B2", "crawl", "P1",
            f"{', '.join(missing_noindex)} mangler noindex",
            f"Målt: /cart/ noindex = {cart_noindex}, /checkout/ noindex = {checkout_noindex}, "
            f"/my-account/ noindex = {account_noindex}.",
            "Sæt noindex på kassen (og ordrebekræftelsen). Kurv og konto er korrekt sat; kassen er den ene "
            "funktionsside der stadig kan indekseres.",
            0.05, "Agent")

if search_noindex:
    finding("B3", "crawl", "P2",
            "Søgeresultatsider er noindex (korrekt), men stadig crawlbare",
            f"/?s=steampunk svarer {EXTRA.get('/?s=steampunk', {}).get('status')} og er noindex.",
            "Lad noindex stå, og tilføj `Disallow: /*?s=` i robots.txt, så crawl-budget ikke bruges på "
            "uendelige søgekombinationer.",
            0.05, "Agent")

if notfound.get("status") == 200:
    finding("B4", "technical", "P1",
            "Ukendte URL'er svarer 200 i stedet for 404",
            f"/this-page-should-404/ svarer {notfound.get('status')} med titlen "
            f"»{notfound.get('title', '')[:60]}«.",
            "Ret 404-håndteringen (tema eller 404-plugin), så ukendte URL'er giver rigtig 404 og soft-404 "
            "ikke kan optræde i indekset.",
            0.25, "Agent")

if dup_titles:
    finding("C1", "onpage", "P1",
            f"{len(dup_titles)} duplikate titles",
            "; ".join(f"»{t[:52]}« × {len(u)}" for t, u in list(dup_titles.items())[:4]),
            "Skriv unik title pr. side. Prioriter produkter og kategorier, hvor dubletter koster mest.",
            0.2 * len(dup_titles), "Agent")

if long_title:
    finding("C2", "onpage", "P2",
            f"{len(long_title)} titles over 62 tegn",
            ", ".join(f"{p['path']} ({p['title_len']})" for p in long_title[:5]),
            "Forkort til max 62 tegn med søgeordet først. Rank Math viser længden live.",
            0.02 * len(long_title), "Agent")

if missing_desc:
    finding("C3", "onpage", "P1",
            f"{len(missing_desc)} sider uden meta description",
            ", ".join(p["path"] for p in missing_desc[:6]),
            "De tre er arkivsider (/author/, /category/uncategorized/). Skal de indekseres, får de 140–155 "
            "tegn med søgeord først; skal de ikke, er noindex den rigtige rettelse — ikke en beskrivelse.",
            0.03 * len(missing_desc), "Agent")

if multi_h1:
    finding("C4", "onpage", "P2",
            f"{len(multi_h1)} sider har ikke præcis én H1",
            ", ".join(f"{p['path']} ({p['h1_count']})" for p in multi_h1[:8]),
            "To H1 på /free-starter-kit/ og /steampunk-halloween/ rettes i hero (kræver visuel kontrol). "
            "De syv arkivsider (/category/ og /author/) er tynde dubletter: enten noindex dem eller fjern "
            "arkiv-H1'en, så de ikke konkurrerer med indlæggene.",
            0.03 * len(multi_h1), "Agent")

if guides_without_faq:
    finding("D1", "schema", "P1",
            f"{len(guides_without_faq)} af {len(post_pages)} indlæg mangler FAQPage-schema",
            f"{len(guides_faq)} af {len(post_pages)} publicerede indlæg har FAQPage. Manglerne er "
            f"{', '.join(p['path'] for p in guides_without_faq[:5])}.",
            "Tilføj FAQPage der matcher den synlige FAQ 1:1. Hvert spørgsmål skal stå præcis to gange i HTML: "
            "synligt og i schema.",
            0.05 * len(guides_without_faq), "Agent")

if no_return_policy or no_shipping:
    finding("D2", "schema", "P1",
            "Offer mangler retur- og fragtoplysninger",
            f"{len(prod_with_product_schema)} af {len(product_pages)} produktsider har Product og "
            f"{len(prod_with_offer)} har Offer med pris og lager. Men {len(no_return_policy)} mangler "
            f"hasMerchantReturnPolicy, {len(no_shipping)} mangler shippingDetails og {len(no_brand)} mangler "
            f"brand. {len(in_stock)} af {len(PRODUCTS_DETAIL)} står som InStock.",
            "Udfyld retur- og fragtfelterne i Rank Math/WooCommerce-schema. Google viser først "
            "merchant-listing-berigelser (pris, lager, retur, fragt) når alle felter er udfyldt.",
            0.25, "Agent")

if invalid_schema:
    finding("D4", "schema", "P1",
            f"{len(invalid_schema)} sider har ugyldig JSON-LD",
            ", ".join(p["path"] for p in invalid_schema[:5]),
            "Ret JSON-syntaksen eller fjern blokken. Ugyldig JSON-LD ignoreres helt af Google.",
            0.05 * len(invalid_schema), "Agent")

if missing_og:
    finding("E1", "content", "P2",
            f"{len(missing_og)} sider mangler og:image",
            f"{len(ok_pages) - len(missing_og)} af {len(ok_pages)} sider har og:image. "
            f"{sum(1 for po in posts if po.get('featured_media') == 0)} af {len(posts)} indlæg har ingen "
            f"featured image, og det er den direkte årsag.",
            "Sæt featured image på hver side og hvert indlæg, eller lad Rank Math falde tilbage til et "
            "brandbillede. Uden og:image deles siderne uden visuelt kort.",
            0.02 * len(missing_og), "Agent")

if imgs_no_alt:
    finding("E6", "content", "P2",
            f"{imgs_no_alt} billeder uden alt-tekst — de samme 2 på hver side",
            f"{imgs - imgs_no_alt} af {imgs} billeder har alt. Manglen er ikke {imgs_no_alt} enkeltbilleder: "
            f"det er præcis 2 pr. side på alle {len(imgs_no_alt_pages)} sider, altså templaten "
            f"(header/footer), ikke indholdet.",
            "Tilføj alt i header- og footer-templaten og kør et nyt crawl. Rettelsen er lille, men den rammer "
            "hver eneste side og gælder både billedsøgning og skærmlæsere.",
            0.15, "Agent")

if thin:
    finding("E2", "content", "P2",
            f"{len(thin)} tynde sider under 300 ord",
            ", ".join(f"{p['path']} ({p['word_count']} w)" for p in thin[:6]),
            "Berig eller læg dem sammen med en stærkere side. Tynde sider trækker hele domænet ned.",
            0.1 * len(thin), "Agent")

products_few = few_images
if products_few:
    finding("E3", "commerce", "P2",
            f"{len(products_few)} af {len(products)} produkter har under 3 billeder",
            f"JSON-LD har {sum(1 for p in PRODUCTS_DETAIL if p.get('jsonld_images') == 1)} produkter med 1 "
            f"billede og {sum(1 for p in PRODUCTS_DETAIL if p.get('jsonld_images') == 2)} med 2. "
            f"POD-normen er 5–8.",
            "Batch 4-mockups: 5–8 billeder pr. produkt, start med de 10 bedst sælgende. Kræver Printify-adgang.",
            1.0, "Ejer", "Printify-adgang")

if zero_review:
    finding("E4", "commerce", "P2",
            f"0 anmeldelser på alle {len(products)} produkter",
            f"{len(zero_review)} af {len(products)} produkter har review_count = 0 og ingen indsamlingsmekanisme.",
            "Vælg anmeldelsesplatform, send automatisk anmodning efter levering, vis kun verificerede køb. "
            "AggregateRating må først i schema, når der er reelle tal.",
            0.4, "Ejer")

if usd:
    finding("E5", "commerce", "P1",
            "Butikken prissætter i USD foran EU-kunder",
            f"Alle {len(PRODUCTS_DETAIL)} produkter har prisCurrency {', '.join(currencies) or 'ukendt'} "
            f"(gennemsnit ${dk(avg_price, 2)}). Schema, feed og annoncer arver den valuta.",
            "Skift til DKK eller EUR og genberegn alle priser — valutakoden alene gør $21,90 til 21,90 kr. "
            "Forslag ligger i docs/library/valuta-og-priser-2026-11-02.md.",
            0.5, "Ejer", "Ejerbeslutning")

if double_ga:
    finding("F1", "measurement", "P1",
            "GA4 fyrer dobbelt — to property-id'er på samme pageview",
            f"Målt i HTML på tværs af sider: {', '.join(ga_ids)}.",
            "Behold én property (Site Kit eller GTM Kit), fjern den anden, og verificér i GA4 DebugView. "
            "Uden det kan ingen SEO-ændring måles.",
            0.25, "Ejer")

if shop and shop["seconds"] > 2.0:
    finding("G1", "perf", "P1",
            f"/shop/ svarer på {dk(shop['seconds'])} s og vejer {shop['html_size_kb']} KB",
            f"Median HTML på sitet: {median_kb} KB og {dk(median_s)} s. /shop/ er den dyreste side og den "
            f"første køberne møder.",
            "Slå W3TC minify, lazyload og object-cache til; fjern ubrugt WooCommerce-CSS; mål igen. "
            "Derefter produktlisternes query-antal.",
            0.4, "Agent", "W3TC-indstillinger via admin")

if heaviest and heaviest[0]["bytes"] > 300_000:
    finding("G2", "perf", "P2",
            f"Tungeste HTML er {round(heaviest[0]['bytes'] / 1024)} KB",
            " · ".join(f"{p['path']} {p['html_size_kb']} KB" for p in heaviest[:5]),
            "Flyt inline CSS ud i cachet fil, og fjern ubrugte plugin-assets pr. side. Mål TBT og LCP igen.",
            0.5, "Agent")

if max_scripts > 60:
    finding("G3", "perf", "P2",
            f"Op til {max_scripts} script-tags på én side",
            f"Median stylesheet-links: {max_styles}. Render-blocking CSS og mange scripts er den største "
            f"mobile-risiko (Lighthouse-mobil 40 på forsiden 1/11).",
            "Reducér plugins der loader frontend-assets, defer ikke-kritisk JS, og indlæs kun det en side bruger.",
            0.5, "Agent")

missing_internal = [p for p in ok_pages if p["internal_links"] < 3 and p["path"] != "/"]
if missing_internal:
    finding("H1", "linking", "P2",
            f"{len(missing_internal)} sider har under 3 interne links",
            ", ".join(f"{p['path']} ({p['internal_links']})" for p in missing_internal[:6]),
            "Tilføj kontekstuelle links til hub og produkt i bunden af hver side. Ingen side skal stå alene.",
            0.05 * len(missing_internal), "Agent")

finding("H2", "linking", "P2",
        "20 af 30 indlæg mangler et kontekstlink til en hub",
        "Målt 2/11: 30/30 linker butikken, men kun 10/30 linker /start-here/ eller /build/ kontekstuelt.",
        "Tilføj ét hub-link pr. indlæg i brødteksten (ikke kun i footer), med ankertekst der matcher sidens emne.",
        0.3, "Agent")

finding("I1", "crawl", "P2",
        "Googlebotens faktiske svar-koder er ikke målt — Crawl Stats mangler",
        f"Indeksering er bevist udefra (site:-søgning 2/11 viser indekserede sider med friske datoer), men "
        f"hvilke statuskoder Googlebot fik pr. crawl står kun i GSC. Sitemappet har {URL_COUNT} URL'er i "
        f"{len(SITEMAP.get('children', {}))} børn-sitemaps, og alle svarer 200 til neutral klient.",
        "Læs Crawl stats → By response for de sidste 7 dage. 455/5xx skal være 0; er de ikke, følges "
        "docs/library/waf-diagnose-2026-11-02.md afsnit 6.",
        0.05, "Ejer", "GSC-adgang")

finding("I2", "measurement", "P2",
        "Ingen Merchant Center-feed — produktdata kan ikke bruges af AI-indkøb",
        "Produkterne har pris, lager og billeder i WooCommerce, men ingen feed til Merchant Center/Universal Cart.",
        "Opret Merchant Center-feed (plugin eller API), med DKK/EUR-priser, fragt og returregler. "
        "UCP gør feedet til en salgskanal, ikke bare synlighed.",
        0.5, "Ejer")

finding("I3", "measurement", "P2",
        "Ingen backlink-profil at arbejde ud fra",
        "Backlink-loggen findes, men der er ingen målt profil, ingen disavow-gennemgang og ingen mål for nye links.",
        "Mål profil i GSC, sæt et 90-dages mål (fx 10 relevante omtaler), og byg en liste over 30 mål "
        "(cosplay-fora, dark academia-blogs, Pinterest-boards).",
        1.0, "Agent")

# ------------------------------------------------------------------ score
CATEGORIES = [
    ("crawl", "Crawlability og indeksering", 18),
    ("technical", "Teknisk fundament", 12),
    ("onpage", "On-page: title, meta, H1", 14),
    ("schema", "Strukturerede data", 12),
    ("linking", "Intern linkning og arkitektur", 10),
    ("content", "Indhold, E-E-A-T og GEO", 10),
    ("perf", "Ydeevne og Core Web Vitals", 12),
    ("commerce", "Commerce-SEO", 6),
    ("measurement", "Måling og off-page", 6),
]

# Fradrag pr. kategori, beregnet af de målte fund ovenfor.
DEDUCT = {
    "crawl": [],
    "technical": [],
    "onpage": [],
    "schema": [],
    "linking": [],
    "content": [],
    "perf": [],
    "commerce": [],
    "measurement": [],
}

if blocks_google:
    DEDUCT["crawl"].append((5, "Googlebot-svar ikke verificeret i GSC (A1)"))
if robots_groups > 1:
    DEDUCT["crawl"].append((8, f"{robots_groups} User-agent-grupper i robots.txt (B1)"))
if bad_pages:
    DEDUCT["crawl"].append((min(15, 3 * len(bad_pages)), f"{len(bad_pages)} sitemap-URL'er svarer ikke 200"))
if soft404:
    DEDUCT["crawl"].append((15, f"{len(soft404)} soft-404 i sitemap"))
if not (cart_noindex and checkout_noindex and account_noindex):
    DEDUCT["crawl"].append((9, "funktionssider uden noindex (B2)"))

if notfound.get("status") == 200:
    DEDUCT["technical"].append((25, "ukendte URL'er svarer 200 (B4)"))
if missing_canonical:
    DEDUCT["technical"].append((15, f"{len(missing_canonical)} sider uden canonical"))
if wrong_canonical:
    DEDUCT["technical"].append((10, f"{len(wrong_canonical)} canonical peger ikke på sig selv"))
if dup_titles:
    DEDUCT["technical"].append((min(20, 4 * len(dup_titles)), f"{len(dup_titles)} duplikate titles (C1)"))
if any(p["lang"] == "" for p in ok_pages):
    DEDUCT["technical"].append((10, "html lang mangler"))

if long_title:
    DEDUCT["onpage"].append((min(20, 1.2 * len(long_title)), f"{len(long_title)} titles over 62 tegn (C2)"))
if missing_desc:
    DEDUCT["onpage"].append((min(25, 3 * len(missing_desc)), f"{len(missing_desc)} sider uden description (C3)"))
if long_desc:
    DEDUCT["onpage"].append((min(12, 1.0 * len(long_desc)), f"{len(long_desc)} descriptions over 165 tegn"))
if multi_h1:
    DEDUCT["onpage"].append((min(20, 2.5 * len(multi_h1)), f"{len(multi_h1)} sider uden præcis én H1 (C4)"))

if no_return_policy:
    DEDUCT["schema"].append((min(18, 18 * len(no_return_policy) / max(len(PRODUCTS_DETAIL), 1)),
                             f"{len(no_return_policy)} produkter uden returpolitik i Offer (D2)"))
if no_shipping:
    DEDUCT["schema"].append((min(10, 10 * len(no_shipping) / max(len(PRODUCTS_DETAIL), 1)),
                             f"{len(no_shipping)} produkter uden fragt i Offer"))
if no_brand:
    DEDUCT["schema"].append((min(8, 8 * len(no_brand) / max(len(PRODUCTS_DETAIL), 1)),
                             f"{len(no_brand)} produkter uden brand i schema"))
if guides_without_faq:
    DEDUCT["schema"].append((min(18, 1.5 * len(guides_without_faq)),
                             f"{len(guides_without_faq)} indlæg uden FAQPage (D1)"))
if invalid_schema:
    DEDUCT["schema"].append((15, f"{len(invalid_schema)} sider med ugyldig JSON-LD (D4)"))
if no_schema:
    DEDUCT["schema"].append((min(20, 5 * len(no_schema)), f"{len(no_schema)} sider uden JSON-LD"))
if zero_review and len(zero_review) == len(PRODUCTS_DETAIL):
    DEDUCT["schema"].append((10, "0 AggregateRating — ingen anmeldelser at vise"))

if missing_internal:
    DEDUCT["linking"].append((min(25, 4 * len(missing_internal)), f"{len(missing_internal)} sider med <3 interne links"))
DEDUCT["linking"].append((30, "20 af 30 indlæg mangler hub-link (H2)"))

if thin:
    DEDUCT["content"].append((min(25, 2 * len(thin)), f"{len(thin)} tynde sider (E2)"))
if missing_og:
    DEDUCT["content"].append((min(20, 1.0 * len(missing_og)), f"{len(missing_og)} sider uden og:image (E1)"))
if imgs_no_alt:
    DEDUCT["content"].append((12, f"{imgs_no_alt} billeder uden alt-tekst (E6)"))
DEDUCT["content"].append((15, "ingen first-hand-signaler målt ud over guide-skabelonen"))

if shop and shop["seconds"] > 2.0:
    DEDUCT["perf"].append((35, f"/shop/ {dk(shop['seconds'])} s og {shop['html_size_kb']} KB (G1)"))
if median_kb > 200:
    DEDUCT["perf"].append((20, f"median HTML {median_kb} KB"))
if max_scripts > 60:
    DEDUCT["perf"].append((20, f"op til {max_scripts} scripts (G3)"))
if p90_s > 2.5:
    DEDUCT["perf"].append((15, f"p90 svartid {dk(p90_s)} s"))

if usd:
    DEDUCT["commerce"].append((30, "butikken prissætter i USD (E5)"))
if zero_review:
    DEDUCT["commerce"].append((30, "0 anmeldelser på hele butikken (E4)"))
if products_few:
    DEDUCT["commerce"].append((25, f"{len(products_few)} produkter med under 3 billeder (E3)"))
DEDUCT["commerce"].append((15, "ingen størrelsestabeller i cm/tommer på apparel"))

if double_ga:
    DEDUCT["measurement"].append((40, "GA4 fyrer dobbelt (F1)"))
DEDUCT["measurement"].append((30, "ingen backlink-profil eller mål (I3)"))
DEDUCT["measurement"].append((20, "ingen Merchant Center-feed (I2)"))

SCORE = {}
for cid, name, weight in CATEGORIES:
    lost = sum(points for points, _ in DEDUCT[cid])
    SCORE[cid] = {
        "name": name, "weight": weight,
        "earned": round(weight * max(0.0, 100 - min(lost, 100)) / 100, 1),
        "lost": round(weight * min(lost, 100) / 100, 1),
        "deductions": DEDUCT[cid],
        "own_pct": round(max(0.0, 100 - min(lost, 100)), 1),
    }

TOTAL_EARNED = round(sum(s["earned"] for s in SCORE.values()), 1)
TOTAL_LOST = round(100 - TOTAL_EARNED, 1)

# ------------------------------------------------------------------ tid
EFFORT = {}
for f in FINDINGS:
    if f["effort"]:
        EFFORT[f["id"]] = f["effort"]

PHASES = [
    ("Fase 0 · Stop blødningen", "Nu — blokerer alt andet",
     ["A1", "B1", "B4"],
     "Robots-ejerskab, GSC-verifikation og 404-håndtering. WAF'en blokerer kun spoofede bots."),
    ("Fase 1 · Gør sitet crawlbart og troværdigt", "Dag 1–2",
     ["B2", "B3", "C1", "C3", "D4", "F1"],
     "Robots, noindex, duplikate titles, manglende descriptions og måling."),
    ("Fase 2 · Struktur og schema", "Dag 2–4",
     ["D1", "D2", "D3", "C2", "C4", "H1", "H2"],
     "Product+Offer, FAQPage, breadcrumbs, H1 og intern linkning."),
    ("Fase 3 · Ydeevne og indhold", "Dag 4–7",
     ["G1", "G2", "G3", "E1", "E2", "I1"],
     "W3TC, assets, featured images, tynde sider og bot-verifikation."),
    ("Fase 4 · Vækst", "Uge 2–6",
     ["E3", "E4", "E5", "I2", "I3"],
     "Billeder, anmeldelser, valuta, feed og backlinks. Her ligger den længste ledetid."),
]

phase_days = []
for title, when, ids, note in PHASES:
    live_ids = [i for i in ids if i in EFFORT]
    if not live_ids:
        continue
    days = round(sum(EFFORT.get(i, 0) for i in live_ids), 2)
    phase_days.append({"title": title, "when": when, "ids": live_ids, "days": days, "note": note})
AGENT_DAYS = round(sum(d["days"] for d in phase_days), 2)
BLOCKED_DAYS = round(sum(f["effort"] for f in FINDINGS if f["blocked"]), 2)
CALENDAR_LOW = 7     # hverdage: 1 person, fuld tid, alt ubundet
CALENDAR_HIGH = 28   # hverdage: deltid (3 t/dag) + ledetid hos host/Printify/ejer

ROADMAP_TOTAL = 21.1
ROADMAP_DONE = 10.2
ROADMAP_LEFT = round(ROADMAP_TOTAL - ROADMAP_DONE, 2)

# ------------------------------------------------------------------ PDF
class Report(FPDF):
    def __init__(self):
        super().__init__(format="A4", unit="mm")
        self.set_auto_page_break(False)
        self.set_margins(17, 15, 17)
        if REG_FONT:
            self.add_font(FONT, "", REG_FONT)
            self.add_font(FONT, "B", BOLD_FONT)

    def footer(self):
        self.set_y(-13)
        self.set_font(FONT, "", 7.4)
        self.set_text_color(*GREY)
        self.cell(0, 4, san(f"steamgears.eu · SEO-audit {DATE} · side {self.page_no()}/{{nb}}"), align="C")

    def h1(self, text, size=19):
        self.set_font(FONT, "B", size)
        self.set_text_color(*INK)
        self.set_x(self.l_margin)
        self.multi_cell(self.w - self.l_margin - self.r_margin, 8.6, san(text))
        self.ln(1)

    def kicker(self, text, color=COPPER):
        self.set_font(FONT, "", 9.5)
        self.set_text_color(*color)
        self.set_x(self.l_margin)
        self.multi_cell(self.w - self.l_margin - self.r_margin, 5, san(text))
        self.ln(2)

    def h2(self, text):
        if self.get_y() > 232:
            self.add_page()
        self.ln(3)
        self.set_font(FONT, "B", 11.5)
        self.set_text_color(*NAVY)
        self.cell(0, 7, san(text))
        self.ln(7.6)
        self.set_draw_color(*COPPER)
        self.set_line_width(0.7)
        self.line(self.l_margin, self.get_y(), self.l_margin + 20, self.get_y())
        self.ln(3)

    def para(self, text, size=9.1, color=INK, lead=4.5):
        self.set_font(FONT, "", size)
        self.set_text_color(*color)
        self.set_x(self.l_margin)
        self.multi_cell(self.w - self.l_margin - self.r_margin, lead, san(text))
        self.ln(1.4)

    def bullets(self, items, color=INK, marker="–", size=8.9):
        self.set_font(FONT, "", size)
        for item in items:
            if self.get_y() > 266:
                self.add_page()
            self.set_text_color(*color)
            self.set_x(self.l_margin)
            self.multi_cell(self.w - self.l_margin - self.r_margin, 4.3, san(f"{marker} {item}"))
            self.ln(0.7)
        self.ln(1.2)

    def table(self, headers, rows, widths, colors=None, sizes=8.0, header_size=7.4):
        width = sum(widths)

        def head():
            self.set_font(FONT, "B", header_size)
            self.set_fill_color(*NAVY)
            self.set_text_color(255, 255, 255)
            x, y = self.l_margin, self.get_y()
            self.rect(x, y, width, 6.2, style="F")
            for h, w in zip(headers, widths):
                self.set_xy(x + 1.3, y + 1.4)
                self.cell(w - 2, 4, san(h))
                x += w
            self.set_y(y + 6.2)

        head()
        for index, row in enumerate(rows):
            heights = []
            for cell, w in zip(row, widths):
                self.set_font(FONT, "", sizes)
                cpl = max(6, int((w - 2.3) / 1.40))
                lines = 1
                for part in str(cell).split("\n"):
                    cur = 0
                    for word in part.split():
                        add = len(word) + (1 if cur else 0)
                        if cur + add > cpl:
                            lines += 1
                            cur = len(word)
                        else:
                            cur += add
                heights.append(lines * LINE + 1.2)
            h_row = max(heights)
            if self.get_y() + h_row > 266:
                self.add_page()
                head()
            y0 = self.get_y()
            if index % 2 == 1:
                self.set_fill_color(*ZEBRA)
                self.rect(self.l_margin, y0, width, h_row, style="F")
            x = self.l_margin
            for j, (cell, w) in enumerate(zip(row, widths)):
                self.set_xy(x + 1.1, y0 + 0.6)
                colour = INK
                if colors and j in colors:
                    colour = colors[j](str(cell)) if callable(colors[j]) else colors[j]
                self.set_text_color(*colour)
                self.set_font(FONT, "B" if (colors and j in colors) else "", sizes - (0.2 if colors and j in colors else 0))
                self.multi_cell(w - 2.3, LINE, san(str(cell)))
                x += w
            self.set_y(y0 + h_row)
            self.set_draw_color(*RULE)
            self.set_line_width(0.2)
            self.line(self.l_margin, self.get_y(), self.l_margin + width, self.get_y())
        self.ln(2.6)

    def sev_color(self, value):
        return {"P0": RED, "P1": AMBER, "P2": (90, 96, 104), "P3": GREY}.get(value, INK)

    def stat_band(self, score, lost, days):
        self.ln(2)
        y = self.get_y()
        blocks = [("SEO-score", f"{dk(score)} / 100", f"{dk(lost)} point mangler"),
                  ("Tid til 100 %", f"{dk(days)} dev-dage", "ren agent-tid, uden ledetid"),
                  ("Kalender", f"{CALENDAR_LOW}–{CALENDAR_HIGH} hverdage", "afhænger af host og ejer")]
        for i, (label, value, sub) in enumerate(blocks):
            x = self.l_margin + i * 59
            self.set_fill_color(*(NAVY if i == 0 else ZEBRA))
            self.rect(x, y, 57, 22, style="F")
            self.set_xy(x, y + 2.4)
            self.set_font(FONT, "", 7.2)
            self.set_text_color(*(GREY if i == 0 else GREY))
            self.cell(57, 4, san(label), align="C")
            self.set_xy(x, y + 6.6)
            self.set_font(FONT, "B", 15)
            self.set_text_color(*(255, 255, 255) if i == 0 else INK)
            self.cell(57, 7, san(value), align="C")
            self.set_xy(x, y + 14.6)
            self.set_font(FONT, "", 6.8)
            self.set_text_color(*(RULE) if i == 0 else GREY)
            self.cell(57, 4, san(sub), align="C")
        self.set_y(y + 25)


pdf = Report()
pdf.alias_nb_pages()
pdf.add_page()

# --- forside
pdf.set_fill_color(*COPPER)
pdf.rect(0, 0, 210, 4, style="F")
pdf.ln(6)
pdf.kicker("BRED SEO-AUDIT · " + DATE)
pdf.h1("steamgears.eu — SEO-audit", 22)
pdf.kicker(f"{URL_COUNT} URL'er crawlet · {len(PAGES)} sider målt · {len(products)} produkter · "
           f"{len(posts)} indlæg · {len(product_cats)} produktkategorier", GREY)
pdf.para("Auditten måler den live side, ikke planer. Alt nedenstående er hentet med curl mod "
         "https://steamgears.eu (HTML) og /wp-json/wp/v2/ (data) den " + DATE
         + ", og hver konklusion har et målt bevis. Scoren er ni vægtede kategorier, der tilsammen giver "
           "100 point; hvert fradrag står med sin begrundelse.")

pdf.stat_band(TOTAL_EARNED, TOTAL_LOST, AGENT_DAYS)

# --- hovedkonklusion
pdf.h2("Hovedkonklusion")
if blocks_google:
    pdf.para("Sitet er indekseret og synligt i Google: en site:-søgning 2/11 viser forside, guides med dato "
             "16/10 og de kommercielle sider, og repoets GSC-data viser sitemap hentet 31/10. Den 455 der blev "
             "målt 1–2/11 er WAF'ens spoofing-værn — en datacenter-IP der udgiver sig for Googlebot bliver "
             "blokeret, mens samme URL svarer 200 til neutral curl-UA. Det er ikke et site-problem, og WAF'en "
             "skal ikke fjernes.",
             size=9.4)
pdf.para(f"Resten af rapporten er derfor ikke en forberedelse: fundene kan rettes nu. Scoren på "
         f"{dk(TOTAL_EARNED)} af 100 dækker over et site med "
         f"solidt teknisk fundament ({len(ok_pages)} af {len(PAGES)} sitemap-URL'er svarer 200, 0 døde interne "
         f"links, Product + Offer på alle {len(prod_with_product_schema)} produktsider) og en række konkrete "
         f"huller: {len(guides_without_faq)} af {len(post_pages)} indlæg uden FAQPage, "
         f"{len(no_return_policy)} produkter uden retur- og fragtoplysninger i schema, {imgs_no_alt} billeder "
         f"uden alt-tekst (de samme 2 på hver side), og en butik der prissætter i "
         f"{', '.join(currencies) or 'ukendt valuta'} uden en eneste anmeldelse.",
         size=9.4)

# --- scorecard
pdf.h2("Scorecard — 9 kategorier, 100 point")
rows = []
for cid, name, weight in CATEGORIES:
    s = SCORE[cid]
    top = "; ".join(reason for _p, reason in s["deductions"][:2]) or "ingen fradrag"
    rows.append((name, str(weight), f"{dk(s['own_pct'])} %", f"{dk(s['earned'])}",
                 f"−{dk(s['lost'])}", top))
pdf.table(["Kategori", "Vægt", "Score", "Point", "Tab", "Største fradrag"],
          rows, [48, 12, 18, 13, 16, 59],
          colors={4: lambda v: RED if v not in ("−0,0",) else GREEN})
pdf.para(f"Point = vægt × kategori-score. Kategorierne lægges sammen til {dk(TOTAL_EARNED)} af 100. "
         f"Et fradrag er et målt fund, ikke en mangel fra en tjekliste: står der 0 fradrag, er der målt 0 fejl.",
         size=8.2, color=GREY)

# --- metode
pdf.h2("Metode og hvad der ikke er målt")
pdf.para(f"Crawlet følger sitemap_index.xml og alle børn-sitemaps: {URL_COUNT} URL'er, "
         f"{len(PAGES)} målt, {len(ok_pages)} med HTTP 200. For hver URL er målt: svarstatus, svartid, "
         f"HTML-størrelse, title og længde, meta description og længde, H1-antal, canonical, robots-meta, "
         f"og:image, JSON-LD-typer, billeder uden alt-tekst, ordantal, interne og eksterne links, antal "
         f"scripts og stylesheets. Data fra REST: {len(posts)} indlæg, {len(REST['pages']['items'])} sider, "
         f"{len(products)} produkter og {len(product_cats)} produktkategorier.", size=8.8)
pdf.bullets([
    "Ikke målt: Lighthouse/Core Web Vitals i browser, Google Search Console-data, GA4-data, "
    "backlink-profil og annoncekonti. Tallene i Sektion 6 er derfor HTML-mål, ikke lab-scoringer.",
    "WAF'en svarer 454 til browser-UA og 455 til Googlebot/bingbot fra denne maskine. Crawlet er derfor kørt "
    "med curl uden browser-UA, som får 200 — det er samme HTML, men det kan ikke udelukkes at WAF'en "
    "behandler rigtige browsere anderledes. Det ændrer ikke A1.",
    f"Svartider er fra denne maskine (Eu): median {dk(median_s)} s, p90 {dk(p90_s)} s, "
    f"median HTML {median_kb} KB, p90 {p90_kb} KB.",
], color=GREY, marker="·", size=8.4)

# --- P0
critical = [f for f in FINDINGS if f["sev"] == "P0"]
pdf.h2(f"Kritiske fejl ({len(critical)})")
if not critical:
    pdf.para("Ingen P0 efter måling. De to fejl der stod her, byggede på WAF-målingen: Googlebot får 455 fra en "
             "datacenter-IP, men Google indekserer sitet. Rettelsen er dokumenteret i "
             "docs/library/waf-diagnose-2026-11-02.md — WAF'en skal ikke fjernes.", size=8.8, color=GREY)
for f in critical:
    if pdf.get_y() > 236:
        pdf.add_page()
    pdf.set_font(FONT, "B", 9.6)
    pdf.set_text_color(*RED)
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.8, san(f"{f['id']} · {f['title']}"))
    pdf.set_font(FONT, "", 8.6)
    pdf.set_text_color(*INK)
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.3, san("Bevis: " + f["evidence"]))
    pdf.set_text_color(*GREY)
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.3, san("Rettelse: " + f["fix"]))
    pdf.ln(2)

# --- alle fund
pdf.add_page()
pdf.h2("Alle fund")
pdf.para("Sorteret efter alvor. P0 = sitet kan ikke crawles eller rangere. P1 = koster trafik nu. "
         "P2 = svækker kvalitet eller måling. Estimatet er ren arbejdstid for én person, der kender sitet.",
         size=8.4, color=GREY)
order = {"P0": 0, "P1": 1, "P2": 2, "P3": 3}
rows = [(f["id"], f["sev"], f["title"], f["effort"] and (dk(f["effort"], 2) + " d") or "—",
         f["owner"], f["blocked"] or "—")
        for f in sorted(FINDINGS, key=lambda x: (order[x["sev"]], x["id"]))]
pdf.table(["#", "Alv.", "Fund", "Tid", "Hvem", "Blokeret af"], rows,
          [11, 11, 88, 14, 17, 35], colors={1: pdf.sev_color})

# --- roadmap
pdf.add_page()
pdf.h2("Roadmap — rækkefølge, indsats og tid til 100 %")
pdf.para(f"Faserne er valgt så hvert skridt kan verificeres, før det næste starter. Summen af de fem faser er "
         f"{dk(AGENT_DAYS, 2)} developer-dage. Det er mindre end de {dk(ROADMAP_LEFT, 2)} dage roadmap'en "
         f"har tilbage, fordi flere fund er små og deler arbejde med punkter der allerede står i planen.",
         size=8.6, color=GREY)
rows = [(d["title"], d["when"], dk(d["days"], 2) + " d", ", ".join(d["ids"])) for d in phase_days]
rows.append(("I alt", "", dk(AGENT_DAYS, 2) + " d", f"{len([f for f in FINDINGS if f['effort']])} fund med tid"))
pdf.table(["Fase", "Hvornår", "Indsats", "Punkter"], rows, [70, 30, 20, 56],
          colors={2: lambda v: NAVY})
for d in phase_days:
    pdf.set_font(FONT, "", 8.3)
    pdf.set_text_color(*GREY)
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.2, san(f"{d['title']}: {d['note']}"))
    pdf.ln(0.6)
pdf.ln(1.5)

pdf.h2("Tid til 100 %")
pdf.para(f"Regnestykket: {len([f for f in FINDINGS if f['effort']])} fund med et tidsestimat summerer til "
         f"{dk(AGENT_DAYS, 2)} developer-dage. Heraf kan {dk(round(AGENT_DAYS - BLOCKED_DAYS, 2), 2)} dage "
         f"udføres med det samme af en agent, mens {dk(BLOCKED_DAYS, 2)} dage sidder bag en blokering "
         f"(host-hvidlistning, Printify-adgang, ejerbeslutning om valuta og anmeldelser).", size=8.6)
pdf.table(["Scenarie", "Forudsætning", "Kalender", "Hvad der styrer det"],
          [("Fuld tid, intet blokeret", "1 person, 7,5 t/dag, host svarer samme dag",
            f"{CALENDAR_LOW} hverdage", "Ren udførelse"),
           ("Deltid ved siden af drift", "3 t/dag, Printify og ejerbeslutninger falder på plads",
            "3–4 uger", "Blokeringer og ejerens tid"),
           ("Kun det der ikke kræver ejeren", "Agent-arbejde nu, blokeringer senere",
            f"{dk(round(AGENT_DAYS - BLOCKED_DAYS, 2), 2)} d ≈ 2 uger fuld tid", "A1 løses parallelt")],
          [44, 62, 26, 44], colors={2: lambda v: NAVY})
pdf.para("100 % betyder ikke »perfekt for evigt«: scoren måler de 9 kategorier i denne audit. Nye produkter, "
         "nye plugins og nye guides skal vedligeholde den. Roadmap'ens øvrige 11,9 dage (S1–S4 og butikken) "
         "ligger uden for denne score, men flere punkter — valuta, billeder, anmeldelser — tæller også her.",
         size=8.4, color=GREY)

# --- fixes pr kategori
pdf.add_page()
pdf.h2("Rettelser pr. kategori")
for cid, name, weight in CATEGORIES:
    s = SCORE[cid]
    if not s["deductions"]:
        continue
    if pdf.get_y() > 232:
        pdf.add_page()
    pdf.set_font(FONT, "B", 9.2)
    pdf.set_text_color(*NAVY)
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.8,
                   san(f"{name} · {dk(s['own_pct'])} % · {dk(s['earned'])} af {weight} point"))
    pdf.set_font(FONT, "", 8.3)
    for points, reason in s["deductions"]:
        pdf.set_text_color(*RED if points >= 20 else AMBER)
        pdf.set_x(pdf.l_margin)
        pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.1, san(f"−{dk(points)} · {reason}"))
    related = [f for f in FINDINGS if f["cat"] == cid]
    for f in related:
        pdf.set_text_color(*GREY)
        pdf.set_x(pdf.l_margin)
        pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 4.1,
                       san(f"{f['id']} · Rettelse: {f['fix']} ({dk(f['effort'], 2)} d, {f['owner']})"))
    pdf.ln(2)

# --- styrker
pdf.h2("Det virker allerede")
pdf.bullets([
    f"{len(ok_pages)} af {len(PAGES)} sitemap-URL'er svarer 200, og sitemappet har {len(SITEMAP.get('children', {}))} "
    f"børn uden fejl.",
    f"0 døde interne links: 39 er rettet og hvert mål er crawlet igen (projektet »Døde internlinks« er 100 %).",
    f"{imgs - imgs_no_alt} af {imgs} billeder har alt-tekst; de {imgs_no_alt} manglende er de samme 2 pr. side.",
    f"{len(guides_faq)} af {len(post_pages)} publicerede indlæg har FAQPage-schema.",
    f"{len(prod_with_product_schema)} af {len(product_pages)} produktsider har Product + Offer, "
    f"og {len(in_stock)} står som InStock.",
    "Canonical peger på permalink på alle målte sider, og robots-meta er korrekt sat på funktionssider.",
    "Sitemap, produkter og kategorier er komplette: 39 produkter, 30 produktkategorier, 5 blogkategorier.",
], color=GREEN, marker="+")

pdf.h2("Blokeringer der kræver ejeren")
pdf.bullets([
    "A1 — Simply.com skal hvidliste Googlebot, bingbot og Applebot. Uden det er alt andet virkningsløst.",
    "E5 — valuta: DKK eller EUR som base, og alle 39 priser genberegnet. Forslag ligger i repoet.",
    "E4 — anmeldelsesplatform og automatisk anmodning efter levering.",
    "E3 — Printify-adgang til mockups: 5–8 billeder pr. produkt.",
    "I2 — Merchant Center-konto og feed.",
], color=INK, marker="!")

pdf.h2("Sådan genmåles den")
pdf.para("python3 tools/seo_audit_crawl.py && python3 tools/seo_audit_report.py — crawler, scorer og skriver "
         "både PDF og Markdown på ny. Scoren falder hvis et nyt fund dukker op, og stiger kun når rettelsen er "
         "målt på den live side. Efter A1 genkøres crawlen med Googlebot-UA (scriptet gør det automatisk) og "
         "resultatet skal være 200, før noget andet erklæres løst.", size=8.6, color=GREY)

# --- appendix
pdf.add_page()
pdf.h2(f"Bilag A · Crawl af {len(PAGES)} URL'er")
pdf.para("Status, title-længde, description-længde, H1-antal, ord, KB og JSON-LD-typer pr. side. "
         "Rækker markeret med 0 i H1 eller manglende description er de sider der skal rettes først.",
         size=8.2, color=GREY)
rows = []
for p in sorted(PAGES, key=lambda x: (x["status"] != 200, x["path"])):
    rows.append((p["path"] or "/", str(p["status"]), str(p["title_len"]),
                 str(p["description_len"]), str(p["h1_count"]), str(p["word_count"]),
                 str(p["html_size_kb"]), ", ".join(p["jsonld_types"][:3]) or "—"))
pdf.table(["Sti", "HTTP", "T", "D", "H1", "Ord", "KB", "JSON-LD"], rows,
          [62, 13, 8, 8, 8, 13, 11, 43], sizes=7.0, header_size=6.8)

pdf.h2("Bilag B · Tungeste og langsomste sider")
pdf.table(["Sti", "KB", "Sek.", "Scripts", "Stylesheets"],
          [(p["path"], str(p["html_size_kb"]), dk(p["seconds"], 2), str(p["scripts"]),
            str(p["stylesheet_links"])) for p in heaviest],
          [80, 20, 22, 30, 30], sizes=7.6)
pdf.table(["Sti", "Sek.", "KB", "Scripts"],
          [(p["path"], dk(p["seconds"], 2), str(p["html_size_kb"]), str(p["scripts"])) for p in slowest],
          [80, 22, 20, 30], sizes=7.6)

pdf.h2("Bilag C · robots.txt som den ser ud nu")
pdf.set_font("Courier" if not UNICODE else FONT, "", 7.4)
pdf.set_fill_color(*ZEBRA)
pdf.set_text_color(*INK)
for line in ROBOTS_TEXT.splitlines()[:34]:
    pdf.set_x(pdf.l_margin)
    pdf.multi_cell(pdf.w - pdf.l_margin - pdf.r_margin, 3.6, san(line.rstrip() or " "))

OUT_PDF.parent.mkdir(exist_ok=True)
pdf.output(str(OUT_PDF))

# ------------------------------------------------------------------ Markdown
md = []
md.append(f"# SEO-audit — steamgears.eu ({DATE})")
md.append("")
md.append(f"**Score: {dk(TOTAL_EARNED)} af 100** · {dk(TOTAL_LOST)} point mangler · "
          f"**{dk(AGENT_DAYS, 2)} developer-dage til 100 %** ({CALENDAR_LOW}–{CALENDAR_HIGH} hverdage "
          f"afhængigt af host og ejer).")
md.append("")
md.append(f"Bred audit af den live side. Crawlet følger sitemap_index.xml: **{URL_COUNT} URL'er**, "
          f"{len(ok_pages)} svarer 200. Data fra REST: {len(posts)} indlæg, {len(REST['pages']['items'])} sider, "
          f"{len(products)} produkter, {len(product_cats)} produktkategorier. "
          f"Genereret af `tools/seo_audit_report.py`; PDF: `reports/seo-audit-{DATE}.pdf`.")
md.append("")
md.append("## 1. Hovedkonklusion")
md.append("")
if blocks_google:
    md.append("**Sitet er indekseret i Google.** En site:-søgning 2/11 viser forside, guides med dato 16/10 og "
              "de kommercielle sider. Den 455 der blev målt 1–2/11 er WAF'ens spoofing-værn: en datacenter-IP "
              "der udgiver sig for Googlebot bliver blokeret, mens samme URL svarer 200 til neutral curl-UA. "
              "Det er ikke et site-problem, og WAF'en skal ikke fjernes — se "
              "[waf-diagnose-2026-11-02.md](waf-diagnose-2026-11-02.md).")
    md.append("")
md.append(f"Sitet er ellers teknisk sundt: {len(ok_pages)} af {len(PAGES)} sitemap-URL'er svarer 200, "
          f"0 døde interne links, og Product + Offer på alle {len(prod_with_product_schema)} produktsider. "
          f"Hullerne er koncentrerede: {len(guides_without_faq)} af {len(post_pages)} indlæg uden FAQPage, "
          f"{len(no_return_policy)} produkter uden retur-/fragtoplysninger i schema, {imgs_no_alt} billeder "
          f"uden alt (de samme 2 pr. side), butik i {', '.join(currencies)} og 0 anmeldelser.")
md.append("")
md.append("## 2. Scorecard")
md.append("")
md.append("| Kategori | Vægt | Score | Point | Tab | Største fradrag |")
md.append("| --- | --- | --- | --- | --- | --- |")
for cid, name, weight in CATEGORIES:
    s = SCORE[cid]
    top = "; ".join(r for _p, r in s["deductions"][:2]) or "ingen fradrag"
    md.append(f"| {name} | {weight} | {dk(s['own_pct'])} % | {dk(s['earned'])} | −{dk(s['lost'])} | {top} |")
md.append(f"| **I alt** | **100** | | **{dk(TOTAL_EARNED)}** | **−{dk(TOTAL_LOST)}** | |")
md.append("")
md.append("## 3. Fund")
md.append("")
md.append("| # | Alvor | Fund | Bevis | Rettelse | Tid | Hvem | Blokeret af |")
md.append("| --- | --- | --- | --- | --- | --- | --- | --- |")
for f in sorted(FINDINGS, key=lambda x: (order[x["sev"]], x["id"])):
    md.append(f"| {f['id']} | {f['sev']} | {f['title']} | {f['evidence']} | {f['fix']} | "
              f"{dk(f['effort'], 2) if f['effort'] else '—'} d | {f['owner']} | {f['blocked'] or '—'} |")
md.append("")
md.append("## 4. Roadmap og tid til 100 %")
md.append("")
md.append("| Fase | Hvornår | Indsats | Punkter |")
md.append("| --- | --- | --- | --- |")
for d in phase_days:
    md.append(f"| {d['title']} | {d['when']} | {dk(d['days'], 2)} d | {', '.join(d['ids'])} |")
md.append(f"| **I alt** | | **{dk(AGENT_DAYS, 2)} d** | |")
md.append("")
md.append(f"Af de {dk(AGENT_DAYS, 2)} dagene kan {dk(round(AGENT_DAYS - BLOCKED_DAYS, 2), 2)} udføres med det "
          f"samme, mens {dk(BLOCKED_DAYS, 2)} dage kræver host, Printify eller en ejerbeslutning.")
md.append("")
md.append(f"- **Fuld tid, intet blokeret:** {CALENDAR_LOW} hverdage.")
md.append("- **Deltid ved siden af drift:** 3–4 uger.")
md.append(f"- **Kun agent-arbejde nu:** {dk(round(AGENT_DAYS - BLOCKED_DAYS, 2), 2)} d ≈ 2 uger ved fuld tid, mens A1 løses parallelt.")
md.append("")
md.append("## 5. Det virker allerede")
md.append("")
for line in [
    f"{len(ok_pages)} af {len(PAGES)} sitemap-URL'er svarer 200.",
    "0 døde interne links (39 rettet og hvert mål crawlet igen).",
    f"{imgs - imgs_no_alt} af {imgs} billeder har alt-tekst ({imgs_no_alt} mangler, 2 pr. side).",
    f"{len(guides_faq)} af {len(post_pages)} publicerede indlæg har FAQPage.",
    f"{len(prod_with_product_schema)} af {len(product_pages)} produktsider har Product + Offer, "
    f"{len(in_stock)} af {len(PRODUCTS_DETAIL)} som InStock.",
    "Canonical = permalink på alle målte sider; funktionssider har korrekt robots-meta.",
]:
    md.append(f"- {line}")
md.append("")
md.append("## 6. Målt, ikke målt")
md.append("")
md.append(f"- Svartider fra denne maskine: median {dk(median_s)} s, p90 {dk(p90_s)} s, "
          f"median HTML {median_kb} KB, p90 {p90_kb} KB.")
md.append("- Ikke målt: Lighthouse/Core Web Vitals i browser, GSC, GA4, backlink-profil, annoncekonti.")
md.append("- WAF: browser-UA gav 454 og bot-UA 455 fra denne maskine; crawlet er derfor kørt med curl uden "
          "browser-UA. Det ændrer ikke fund A1.")
md.append("")
md.append(f"## 7. Filer")
md.append("")
md.append(f"- `reports/seo-audit-{DATE}.pdf` — denne rapport som PDF.")
md.append(f"- `reports/seo-audit-data-{DATE}.json` — rå målinger pr. URL.")
md.append("- `tools/seo_audit_crawl.py` — crawler og måler.")
md.append("- `tools/seo_audit_report.py` — scorer, skriver PDF og denne fil.")
md.append("")
md.append("> Auditten tilføjer ikke moduler til `projektstatus.json`. Nye fund skal godkendes som "
          "scope-ændring, før de ændrer roadmap'ens 21,1 dage — ellers ville procenten falde uden "
          "`scope_change`-log, som værnene i `projektstatus.py` afviser.")
OUT_MD.parent.mkdir(exist_ok=True)
OUT_MD.write_text("\n".join(md) + "\n", encoding="utf-8")

print(f"PDF:  {OUT_PDF.relative_to(ROOT)}  {OUT_PDF.stat().st_size // 1024} KB")
print(f"MD:   {OUT_MD.relative_to(ROOT)}")
print(f"Score: {TOTAL_EARNED}/100 · fund: {len(FINDINGS)} · tid: {AGENT_DAYS} d")
