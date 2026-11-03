#!/usr/bin/env python3
"""project_reports.py — én PDF pr. projekt: status, roadmap-indeks, udviklingsvej, virker/virker ikke.

Kør:  python3 tools/project_reports.py
Ud:   reports/<projekt>-2026-11-02.pdf

Tallene kommer fra projektstatus.json (samme kilde som den daglige status) og
roadmap'erne i docs/roadmap.md og plugins/steamgears-autopilot/ROADMAP.md.
Ingen procent tastes ind her — de læses fra JSON og kan genberegnes.
"""

from __future__ import annotations

import glob
import json
import os
from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "reports"
DATE = "2026-11-02"

NAVY = (31, 42, 55)
COPPER = (184, 115, 51)
GREY = (110, 118, 128)
RULE = (214, 219, 226)
ZEBRA = (246, 248, 250)
INK = (28, 33, 40)
GREEN = (22, 120, 78)
RED = (176, 58, 46)
AMBER = (166, 110, 12)

LINE = 4.3


# ---------------------------------------------------------------- fonte
FONT_DIRS = [
    "/usr/share/fonts",
    "/usr/local/share/fonts",
    "/opt/freestyle/python/lib/python3.12/site-packages/matplotlib/mpl-data/fonts/ttf",
]


def find_fonts() -> tuple[str | None, str | None]:
    """Find et TTF-par (regular + bold) på maskinen. DejaVu foretrækkes."""
    pairs = [("DejaVuSans.ttf", "DejaVuSans-Bold.ttf"), ("LiberationSans-Regular.ttf", "LiberationSans-Bold.ttf")]
    for reg_name, bold_name in pairs:
        for root_dir in FONT_DIRS:
            if not os.path.isdir(root_dir):
                continue
            for root, _dirs, files in os.walk(root_dir):
                if reg_name in files and bold_name in files:
                    return os.path.join(root, reg_name), os.path.join(root, bold_name)
    return None, None


REG_FONT, BOLD_FONT = find_fonts()
UNICODE = REG_FONT is not None
FONT = "DejaVu" if UNICODE else "Helvetica"

# Latin-1 rummer hele det danske alfabet (æ ø å Æ Ø Å), så kun typografi skal mappes.
ASCII_MAP = {"\u2014": "-", "\u2013": "-", "\u2192": "->", "\u00b7": "|", "\u2022": "-",
              "\u2713": "OK", "\u2019": "'", "\u201c": '"', "\u201d": '"', "\u2026": "..."}


def sanitize(text: str) -> str:
    if UNICODE:
        return text
    for a, b in ASCII_MAP.items():
        text = text.replace(a, b)
    return text.encode("latin-1", "replace").decode("latin-1")


# ---------------------------------------------------------------- data
def load_state() -> dict:
    return json.load(open(ROOT / "projektstatus.json", encoding="utf-8"))


def project(state: dict, pid: str) -> dict:
    return next(p for p in state["projects"] if p["id"] == pid)


def pct(p: dict) -> tuple[float, float, float]:
    mods = [m for m in p["modules"] if not m.get("recurring")]
    done = sum(float(m["done"]) for m in mods)
    total = sum(float(m["total"]) for m in mods)
    return round(done / total * 100, 1), done, total


def n(value: float, dec: int = 2) -> str:
    """Dansk talformat med mindst én decimal: 3 -> 3,0 · 0.25 -> 0,25 · 21.1 -> 21,1."""
    text = f"{round(float(value), dec):.{dec}f}"
    if "." in text:
        text = text.rstrip("0")
        if text.endswith("."):
            text += "0"
    return text.replace(".", ",")


SITE_ROADMAP = [
    ("S0.1", "AI Engine-sagen lukket (CVE-fix kravet var 3.8.7; 3.9.0 kører)", "Færdig", "0,25 d"),
    ("S0.2", "To `User-agent: *`-grupper i robots.txt afklares", "Åben", "0,5 d"),
    ("S0.3", "CI matrix PHP 8.0–8.5 + WP 6.4/7.1/latest", "Færdig", "0,25 d"),
    ("S0.4", "301-map for de fem vanity-URL'er", "Blokeret", "0,5 d"),
    ("S0.5", "Fjern det overflødige Health Check-plugin", "Åben", "0,25 d"),
    ("S0.6", "Bekræft at Coming Soon ikke rammer crawlers", "Åben", "0,25 d"),
    ("S0.7", "Rank Math-sitemap uden 404", "Åben", "0,5 d"),
    ("S0.8", "W3TC purge af AI Engine-markup", "Færdig", "0,1 d"),
    ("S1.1", "Herbarium-linjen: 12 SKU'er publiceres", "Blokeret", "0,5 d"),
    ("S1.2", "Beslut Atelier-jewellery og DIY/digital", "Åben", "0,5 d"),
    ("S1.3", "Kladde-triage: 750 og 938 flettet, resten publiceret", "Færdig", "1,0 d"),
    ("S1.4", "Tøm produkt- og sideskraldespand", "Åben", "0,5 d"),
    ("S1.5", "Produktlinks i alle guides via /go/-tracking", "Delvis", "1,0 d"),
    ("S1.6", "Lead magnets 801/764 får capture-path", "Åben", "1,0 d"),
    ("S2.1", "Hubs: 2 hubs + 5 undersider bygget", "Delvis", "3,0 d"),
    ("S2.2", "/shipping/ og /returns/ (eller 301)", "Åben", "0,5 d"),
    ("S2.3", "Navigation væk fra wp:page-list", "Spec klar", "0,5 d"),
    ("S2.4", "Homepage H1/title-beslutning", "Åben", "0,25 d"),
    ("S2.5", "Rank Math-meta over 24 indlæg", "Færdig", "1,0 d"),
    ("S2.6", "Intern linkning guides → produkter og hubs", "Færdig", "0,75 d"),
    ("S3.1", "Tre private designsider i skraldespand", "Færdig", "0,25 d"),
    ("S3.2", "Duplikat Privacy Policy-side fjernet", "Færdig", "0,1 d"),
    ("S3.3", "Mediepas: webp/Imagify + alt-tekster", "Delvis", "0,75 d"),
    ("S3.4", "Plugin-rationalisering 33 → færre", "Åben", "1,0 d"),
    ("S3.5", "Ugentlig vedligeholdelseslog følges op", "Delvis", "0,5 d"),
    ("S4.1", "Complianz til EU opt-in", "Åben", "0,25 d"),
    ("S4.2", "Juridisk identitet: CVR, navn, adresse, telefon", "Blokeret", "0,25 d"),
    ("S4.3", "Valuta og priser (USD → DKK/EUR)", "Blokeret", "0,5 d"),
    ("S4.4", "Digital fortrydelsesfunktion", "Delvis", "0,45 d"),
    ("S4.5", "MobilePay/PayPal/Klarna", "Åben", "0,4 d"),
    ("S4.6", "Fragtzoner, klasser og live-rates", "Åben", "0,4 d"),
    ("S4.7", "5–8 billeder pr. produkt", "Åben", "1,0 d"),
    ("S4.8", "Størrelsestabeller i cm og tommer", "Åben", "0,5 d"),
    ("S4.9", "Produkttekst: fit, tid, GPSR-blok", "Delvis", "0,5 d"),
    ("S4.10", "Anmeldelsesplatform + automatisk anmodning", "Åben", "0,4 d"),
    ("S4.11", "/shop/ ydeevne (5,8 s / 470 KB)", "Åben", "0,4 d"),
    ("S4.12", "Fortrydelsesinfo i ordrebekræftelsen", "Åben", "0,2 d"),
]

PLUGIN_ROADMAP = [
    ("P0.1", "Fejl-alarmering: mail ved failed + dagligt digest", "Åben", "0,5 d"),
    ("P0.2", "\"Køen løber tør\"-alarm pr. kanal", "Åben", "0,5 d"),
    ("P0.3", "Retry med backoff (5 min → 30 min → 2 t)", "Åben", "1,0 d"),
    ("P0.4", "Cron-honesty: Site Health-test + dokumentation", "Delvis", "0,5 d"),
    ("P0.5", "Publish-auditlog pr. forsøg", "Åben", "1,0 d"),
    ("P0.6", "Duplicate-guard mod Command Center", "Blokeret", "0,5 d"),
    ("P1.1", "Pre-flight-validering pr. kanal", "Åben", "1,0 d"),
    ("P1.2", "\"Påmind mig\" i stedet for at poste", "Åben", "1,0 d"),
    ("P1.3", "Blackout-kalender", "Åben", "0,5 d"),
    ("P1.4", "Evergreen-recycling", "Blokeret", "2,5 d"),
    ("P1.5", "Platforms-analytics ind i sgap_events", "Blokeret", "4,0 d"),
    ("P1.6", "Kvalitetsgate før kø", "Åben", "1,0 d"),
    ("P2.1", "Bluesky direkte posting", "Åben", "1,0 d"),
    ("P2.2", "Mastodon direkte posting", "Åben", "0,5 d"),
    ("P2.3", "Telegram-kanal (også ekstra alarmkanal)", "Åben", "0,5 d"),
    ("P2.4", "Etsy: byg OAuth eller skjul de fem felter", "Beslutning", "2,5 / 0,25 d"),
    ("P2.5", "Sprogvarianter pr. post", "Åben", "2,5 d"),
    ("P3.1", "Slet død metode + ryd de 15 ubrugte indstillinger", "Åben", "0,5 d"),
    ("P3.2", "Tests for /feed og /reposts, auth på /snapshot", "Åben", "0,5 d"),
    ("P3.3", "PHP 8.5 + WP 7.1 i CI", "Færdig", "0,25 d"),
    ("P3.4", "Release notes i update-modalen", "Åben", "0,5 d"),
    ("P3.5", "Verificér uninstall rydder alt", "Delvis", "0,25 d"),
]

REBRAND_ROADMAP = [
    ("B1", "Kortlægning: 39 designs → 21 dark academia, 6 gothic, 7 steampunk", "Færdig", "—"),
    ("B2", "Titler, SEO-titel, meta og 6 kollektioner", "Færdig", "—"),
    ("B3", "39 produkttekster med fit, produktion, levering, GPSR-blok", "Færdig", "—"),
    ("B4", "Mockups: ~130 billeder i fem faser", "Åben", "5 t"),
    ("—", "Printify variant-mapping for 9 tees + 12 Herbarium", "Blokeret", "—"),
    ("—", "GPSR-postadresse i alle 39 produkter", "Blokeret", "—"),
]

SEO_ROADMAP = [
    ("G1", "Guide id 292 — men's steampunk essentials", "Åben", "1 guide"),
    ("G2", "Guide id 296", "Åben", "1 guide"),
    ("G3", "Guide id 295", "Åben", "1 guide"),
    ("—", "Daglig loop: brief, keyword-DB, konkurrentscan", "Kører", "løbende"),
    ("—", "GA4-dubletfyr fjernes (G-0HC9BLQ88R + G-3MYLFKY8MY)", "Åben", "—"),
    ("—", "Post-GEO-måling 9/11 og 16/11 i GSC", "Planlagt", "—"),
]

LINKS_ROADMAP = [
    ("A", "6 kladde-produkter publiceret → 11 links løst", "Færdig", "—"),
    ("B", "12 skraldespands-produkter → nærmeste live-produkt", "Færdig", "—"),
    ("C", "2 produkter der aldrig har eksisteret", "Færdig", "—"),
    ("D", "3 kategorislugs rettet (goggles, posters, top hats)", "Færdig", "—"),
    ("E", "1 gammel side-slug (/refund-and-returns/)", "Færdig", "—"),
    ("—", "Månedlig re-crawl som fast rutine", "Åben", "løbende"),
]


PROJECTS = [
    {
        "pid": "site",
        "slug": "site-optimering",
        "name": "steamgears.eu — site-optimering",
        "kicker": "Roadmap S0–S4 · 21,1 developer-dage",
        "intro": "Selve sitet: struktur, indhold, butik og hygiejne. Fem bands, 36 punkter. "
                 "Tallene er de samme som i den daglige status; roadmap'en er kilden til estimaterne.",
        "roadmap": SITE_ROADMAP,
        "roadmap_note": "Færdig = udført og verificeret på den live side. Blokeret = kræver ejer eller "
                        "tredjepart (Redirection-gruppe, Printify, CVR-data).",
        "path": [
            ("1", "S2.3 Navigation: wp:page-list → fem punkter", "0,5 d", "Spec klar, kræver Site Editor", "Ejer"),
            ("2", "S4.1 Complianz til EU opt-in", "0,25 d", "Ingen", "Ejer"),
            ("3", "S4.3 Valuta + 38 nye priser", "0,5 d", "Valutabeslutning (forslag klar)", "Ejer"),
            ("4", "S4.2 Juridisk identitet i footer + imprint", "0,25 d", "CVR, adresse, telefon", "Ejer"),
            ("5", "S0.4 Fem 301'er", "0,5 d", "Én Redirection-gruppe", "Ejer"),
            ("6", "S1.1 Herbarium publiceres", "0,5 d", "Printify-variantmapping", "Printify"),
            ("7", "S4.11 /shop/ ydeevne + S3.3 webp", "1,15 d", "W3TC-indstillinger", "Ejer"),
            ("8", "S0.5/S0.6/S0.7 hygiejne", "1,0 d", "Ingen", "Agent"),
        ],
        "works": [
            "Alle 108 sitemap-URL'er svarer 200, og sitemap har 0 fejl.",
            "Døde interne links: 39 → 0, hvert mål crawlet igen.",
            "Titler over 62 tegn: 19 → 0. Manglende meta descriptions: 3 → 0.",
            "210 billeder har alt-tekst, 0 mangler.",
            "To hubs (/start-here/, /build/) plus fem undersider er live med 0 dårlige links.",
            "Alle 30 publicerede indlæg linker butikken (28 unikke produktmål).",
            "39 produkter er publiceret og alle in stock; soft-404-produkterne er væk.",
            "AI Engine-sagen er lukket: CVE-fix kravet var 3.8.7, sitet kører 3.9.0, som nu også er MCP-serveren.",
        ],
        "fails": [
            "Navigationen er stadig en page-list: alle 29 sider i headeren, inkl. kurv og kasse.",
            "/inspiration/, /shipping/ og /returns/ findes ikke; de skal 301'es, ikke bygges.",
            "Butikken prissætter i USD med EU-kunder, og momsen bliver uigennemsigtig.",
            "Complianz kører US opt-out foran EU-kunder; kun US-dokumenter er genereret.",
            "Ingen CVR, firmanavn, adresse eller telefon — og imprint står på \"none\".",
            "Kun Stripe som betaling; ingen MobilePay eller PayPal.",
            "Ni tees er simple produkter uden størrelsesvalg, men teksten lover S–2XL.",
            "/shop/ tager 5,8 s og 470 KB; produktside har 151 KB inline CSS og 105 scripts.",
            "GA4 fyrer dobbelt på hvert pageview (to properties).",
        ],
        "decisions": [
            "Hubs: byg resten, eller drop dem og skriv navigationen om (S2.1)?",
            "Homepage: shop-framing eller workshop-framing (S2.4)?",
            "Valuta: DKK som base med EUR som sekundær — godkendes?",
            "Cron: Action Scheduler (2 d) eller rigtig cron på Simply (0,25 d)?",
        ],
    },
    {
        "pid": "plugin",
        "slug": "autopilot-plugin",
        "name": "Steamgears Autopilot — plugin",
        "kicker": "Roadmap P0–P3 · 23,0 developer-dage · v7.15.1",
        "intro": "Social autopilot som WordPress-plugin: AI-udkast, kø, publicering via Publer, "
                 "klik-tracking og ROI. 6.273 PHP-linjer i 15 filer, 20 af 21 subsystemer live.",
        "roadmap": PLUGIN_ROADMAP,
        "roadmap_note": "P0 = reliability og må komme først. P1.5 og P0.6 er eksternt blokeret "
                        "(Pinterest-token, Meta-review, Command Center).",
        "path": [
            ("1", "P0.1 Fejl-alarmering (wp_mail findes allerede ét sted)", "0,5 d", "Ingen", "Agent"),
            ("2", "P0.2 Kø-udtørrings-alarm i samme digest", "0,5 d", "P0.1", "Agent"),
            ("3", "P0.3 Retry med backoff + dead-letter-liste", "1,0 d", "Ingen", "Agent"),
            ("4", "P0.5 Publish-auditlog bag en Log-fane", "1,0 d", "Ingen", "Agent"),
            ("5", "P0.4 resten: Site Health-test + docs", "0,3 d", "Detektion findes", "Agent"),
            ("6", "P3.1 Slet død metode + skjul 15 ubrugte indstillinger", "0,5 d", "Ejer-beslutning", "Agent"),
            ("7", "P1.1 Pre-flight-validering pr. kanal", "1,0 d", "P0.1 hjælper", "Agent"),
            ("8", "P1.3 Blackout-kalender + P2.3 Telegram", "1,0 d", "Ingen", "Agent"),
        ],
        "works": [
            "Hele kæden virker: generér → godkend → publicér (RSS eller Publer API) → mål.",
            "20 af 21 subsystemer er live og nået fra cron, REST, admin, ability eller AJAX.",
            "Self-updateren virker: zip i repoet er byte-identisk med kilden til 7.15.1.",
            "Klik-tracking via /go/<id>/ med cookie og WooCommerce-attribution.",
            "904 linjer tests; CI grøn på PHP 8.0–8.5 og WP 6.4/7.1/latest.",
            "9 AJAX- og 5 admin_post-handlere har capability + nonce.",
            "uninstall.php fjerner 8 tabeller, alle sgap_% options/transients og 9 cron-hooks.",
            "RSS-feeden er cache-sikker: query-streng, DONOTCACHEPAGE, purge pr. kanal, ETag/304.",
        ],
        "fails": [
            "Ingen fejl-mail: hele pluginnet har præcis ét wp_mail-kald (ugerapporten).",
            "Ingen backoff: tre forsøg samme dag kan alle brænde under én udfaldstime.",
            "Ingen auditlog — kun en error-tekst pr. kø-element.",
            "Ingen alarm når en kanal løber tør for indhold.",
            "15 indstillinger registreres men læses aldrig; fem Etsy-felter lover en integration der ikke findes.",
            "Én død metode: SGAP_Admin::get_setting_help_link() har nul kald.",
            "Merchant-feed, /reposts og updaterens install-sti er utestede.",
            "Cron er WP-Cron på et cachet site — publicering kan blive forsinket.",
        ],
        "decisions": [
            "Etsy: byg OAuth-import (2,5 d + review) eller skjul felterne (15 min)?",
            "Direkte netværks-tokens: fjern dem, eller behold til senere direkte posting?",
            "Action Scheduler: accepter 2 d migration, eller bliv på WP-Cron?",
            "Analytics: er platformsdata værd 4 d plus review?",
        ],
    },
    {
        "pid": "rebrand",
        "slug": "dark-academia-rebrand",
        "name": "Dark academia-rebrand",
        "kicker": "Fire batches · 13,5 timer",
        "intro": "Ommærkning af de 39 designs fra generic steampunk til dark academia, gothic og "
                 "vintage maritime, med nye titler, kollektioner, tekster og mockups.",
        "roadmap": REBRAND_ROADMAP,
        "roadmap_note": "Batch 1–3 er tekstarbejde og kan gøres færdigt uden adgang. Batch 4 kræver Printify.",
        "path": [
            ("1", "Printify: variant-mapping for de 9 tees og 12 Herbarium-SKU'er", "—", "Printify-konto", "Ejer"),
            ("2", "Batch 4 mockups fase 1: 5 familier × mockup-typer", "5 t", "Printify-adgang", "Ejer"),
            ("3", "GPSR-postadresse ind i alle 39 beskrivelser", "0,5 t", "CVR/adresse", "Ejer"),
            ("4", "Kontrollér at Printify-sync ikke overskriver de nye titler", "0,5 t", "Ingen", "Agent"),
            ("5", "Genbesøg kollektionerne når billederne er skiftet", "1 t", "Batch 4", "Agent"),
        ],
        "works": [
            "32 af 39 produkter har fået nyt navn plus SEO-titel, meta og fokuskeyword.",
            "Seks kundevendte kollektioner er oprettet og svarer 200.",
            "Alle 39 produkter har ny lang og kort beskrivelse med fit, produktion og levering.",
            "Kortlægningen af alle 39 designs findes med begrundelse pr. produkt.",
            "Mockup-briefen er færdig: fem faser, ~130 billeder, ~6,5 t.",
            "Slugs er urørte, så de 39 indgående links stadig virker.",
        ],
        "fails": [
            "Batch 4 (mockups) er 0 af 5 t — hvert produkt har stadig kun 1–2 billeder.",
            "GPSR-blokken mangler postadresse, fordi CVR og adresse ikke findes endnu.",
            "Printify kan overskrive titler og tekster ved næste katalog-sync (uverificeret).",
            "De ni tees og 12 Herbarium-SKU'er kan ikke sælges uden varianter.",
            "Fem hoodie-varianter deler ét SKU (SG-HOOD-23), så pluklister er tvetydige.",
        ],
        "decisions": [
            "Skal titler og tekster redigeres i Printify i stedet for WordPress?",
            "Hvor mange billeder pr. produkt er nok til lanceringsdagen?",
        ],
    },
    {
        "pid": "seo",
        "slug": "seo-indholdsproduktion",
        "name": "SEO-indholdsproduktion",
        "kicker": "Tre guides pr. dag · GEO-skabelon",
        "intro": "Daglig indholdsproduktion på den GEO-skabelon som 18 af 18 guider allerede bærer. "
                 "Reglerne står i docs/seo.md; her måles kun det der faktisk er skrevet og publiceret.",
        "roadmap": SEO_ROADMAP,
        "roadmap_note": "Guides tælles først når de er publiceret og har meta, schema og produktlinks.",
        "path": [
            ("1", "Guide 292 — men's steampunk essentials", "1 guide", "Ingen", "Agent"),
            ("2", "Guide 296 og 295 efter samme skabelon", "2 guides", "292 som skabelon", "Agent"),
            ("3", "Fjern GA4-dubletfyringen, så målingen bliver brugbar", "—", "Site Kit-genforbindelse", "Ejer"),
            ("4", "Post-GEO-måling 9/11 og 16/11 i GSC", "—", "Tid", "Agent"),
            ("5", "Bed Google indeksere /steampunk-costumes/ igen", "—", "GSC-adgang", "Ejer"),
        ],
        "works": [
            "18 af 18 poster i guide-stakken bærer GEO-skabelonen (quick answer, byline, FAQ 1:1).",
            "Reglerne er samlet i ét dokument med skabelon, schema-tjek og cache-fælder.",
            "GA4- og GSC-baseline er målt og dokumenteret med forbehold.",
            "Døde links i guides er 0, og 39 produktbilleder har fået ny alt-tekst.",
            "Den daglige loop (brief, keyword-DB, konkurrentscan) kører.",
        ],
        "fails": [
            "0 af 3 guides er skrevet — modulet står på 0 %.",
            "Der findes ingen post-GEO-måling endnu; hele GA4-vinduet er baseline.",
            "GA4 tæller hvert pageview dobbelt, så konverteringer ikke kan aflæses sikkert.",
            "Guide-trafik er ca. 1 % af sitets sessions (32 af ~3.333).",
            "/steampunk-costumes/ var \"discovered, not indexed\" 1/11.",
        ],
        "decisions": [
            "Skal de tre guides skrives i den rækkefølge roadmap'en foreslår?",
            "Er 0,5 dage pr. guide den rigtige kadence, eller skal vi gå efter færre og længere?",
        ],
    },
    {
        "pid": "links",
        "slug": "doede-internlinks",
        "name": "Døde internlinks",
        "kicker": "39 forekomster på 15 sider · verificeret",
        "intro": "Kortlægning og rettelse af interne links der pegede på 404. Projektet er det eneste "
                 "der er 100 % færdigt, og det er samtidig det bedste eksempel på hvorfor verifikation "
                 "skal ske på sidetitel og ikke på statuskode.",
        "roadmap": LINKS_ROADMAP,
        "roadmap_note": "Alle fem grupper er lukket. Det der er tilbage, er rutinen — ikke rettelser.",
        "path": [
            ("1", "Månedlig re-crawl af alle publicerede sider", "løbende", "Ingen", "Agent"),
            ("2", "Tjek nye indlæg mod live-URL'er før publicering", "løbende", "Ingen", "Agent"),
            ("3", "Verificér på sidetitel, ikke statuskode (soft-404)", "—", "Ingen", "Agent"),
            ("4", "Hold øje med nye døde links efter produktoprydninger", "—", "Ingen", "Agent"),
        ],
        "works": [
            "39 af 39 døde links er rettet på 15 sider, hvert mål crawlet igen.",
            "6 kladde-produkter blev publiceret, så 11 links løste sig selv.",
            "3 kategorislugs og 1 gammel side-slug er rettet.",
            "Soft-404-produkterne er flyttet til skraldespand og svarer nu rigtigt 404.",
            "Linktjekket er skiftet fra statuskode til sidetitel, så soft-404 ikke lurer.",
        ],
        "fails": [
            "Rutinen er ikke automatiseret endnu — den kører manuelt.",
            "De fem vanity-301'er i S0.4 er stadig blokeret af Redirection-gruppen.",
            "Der findes ingen CI-vagt der fanger nye døde links ved commit.",
        ],
        "decisions": [
            "Skal linktjekket ind i CI som fast job, eller blive en månedlig rutine?",
        ],
    },
]


# ---------------------------------------------------------------- pdf
class Report(FPDF):
    def __init__(self, title: str):
        super().__init__(format="A4", unit="mm")
        self.title_text = title
        self.set_auto_page_break(False)
        self.set_margins(18, 16, 18)
        if REG_FONT:
            self.add_font(FONT, "", REG_FONT)
            self.add_font(FONT, "B", BOLD_FONT)

    def footer(self):
        self.set_y(-14)
        self.set_font(FONT, "", 7.5)
        self.set_text_color(*GREY)
        self.cell(0, 4, sanitize(f"{self.title_text} · projektrapport {DATE} · side {self.page_no()}/{{nb}}"),
                  align="C")

    def space(self, h=3):
        if self.get_y() + h > 272:
            self.add_page()
        else:
            self.ln(h)

    def h1(self, text):
        self.set_font(FONT, "B", 20)
        self.set_text_color(*INK)
        self.multi_cell(0, 9, sanitize(text))
        self.ln(1)

    def kicker(self, text):
        self.set_font(FONT, "", 10)
        self.set_text_color(*COPPER)
        self.multi_cell(0, 5.5, sanitize(text))
        self.ln(2)

    def h2(self, text):
        # Overskrift må ikke stå alene nederst på en side — hold den sammen med indholdet under.
        if self.get_y() > 238:
            self.add_page()
        self.space(4)
        self.set_font(FONT, "B", 11.5)
        self.set_text_color(*NAVY)
        self.cell(0, 7, sanitize(text))
        self.ln(7.5)
        self.set_draw_color(*COPPER)
        self.set_line_width(0.7)
        self.line(self.l_margin, self.get_y(), self.l_margin + 22, self.get_y())
        self.ln(3)

    def para(self, text, size=9.2, color=INK, lead=4.6):
        self.set_font(FONT, "", size)
        self.set_text_color(*color)
        self.multi_cell(0, lead, sanitize(text))
        self.ln(1.5)

    def bullets(self, items, colour=INK, marker="•"):
        self.set_font(FONT, "", 9)
        for item in items:
            if self.get_y() > 268:
                self.add_page()
            width = self.w - self.l_margin - self.r_margin
            self.set_text_color(*colour)
            self.set_x(self.l_margin)
            self.multi_cell(width, 4.4, sanitize(f"{marker} {item}"))
            self.ln(0.8)
        self.ln(1.5)

    def table(self, headers, rows, widths, status_col=None):
        width = sum(widths)
        fs = 8.2

        def head():
            self.set_font(FONT, "B", 7.6)
            self.set_fill_color(*NAVY)
            self.set_text_color(255, 255, 255)
            x, y = self.l_margin, self.get_y()
            self.rect(x, y, width, 6.4, style="F")
            for h, w in zip(headers, widths):
                self.set_xy(x + 1.4, y + 1.4)
                self.cell(w - 2, 4, sanitize(h))
                x += w
            self.set_y(y + 6.4)

        head()
        for index, row in enumerate(rows):
            heights = []
            for cell, w in zip(row, widths):
                self.set_font(FONT, "", fs)
                chars = 1.42
                cpl = max(6, int((w - 2.4) / chars))
                lines = 1
                for part in str(cell).split("\n"):
                    words, cur = part.split(), 0
                    for word in words:
                        add = len(word) + (1 if cur else 0)
                        if cur + add > cpl:
                            lines += 1
                            cur = len(word)
                        else:
                            cur += add
                heights.append(lines * LINE + 1.4)
            h_row = max(heights)
            if self.get_y() + h_row > 268:
                self.add_page()
                head()
            y0 = self.get_y()
            if index % 2 == 1:
                self.set_fill_color(*ZEBRA)
                self.rect(self.l_margin, y0, width, h_row, style="F")
            x = self.l_margin
            for j, (cell, w) in enumerate(zip(row, widths)):
                self.set_xy(x + 1.2, y0 + 0.7)
                if status_col is not None and j == status_col:
                    color = {"Færdig": GREEN, "Kører": GREEN, "Delvis": AMBER,
                             "Blokeret": RED, "Beslutning": AMBER}.get(str(cell), INK)
                    self.set_text_color(*color)
                    self.set_font(FONT, "B", fs - 0.3)
                else:
                    self.set_text_color(*INK)
                    self.set_font(FONT, "", fs)
                self.multi_cell(w - 2.4, LINE, sanitize(str(cell)))
                x += w
            self.set_y(y0 + h_row)
            self.set_draw_color(*RULE)
            self.set_line_width(0.2)
            self.line(self.l_margin, self.get_y(), self.l_margin + width, self.get_y())
        self.ln(3)

    def stat_band(self, pct_value, done, total, unit, module_rows):
        self.space(2)
        y = self.get_y()
        self.set_fill_color(*NAVY)
        self.rect(self.l_margin, y, 52, 26, style="F")
        self.set_xy(self.l_margin, y + 3)
        self.set_font(FONT, "B", 24)
        self.set_text_color(255, 255, 255)
        self.cell(52, 12, sanitize(f"{n(pct_value)} %"), align="C")
        self.set_xy(self.l_margin, y + 15)
        self.set_font(FONT, "", 7.5)
        self.cell(52, 5, sanitize("af roadmap'ens arbejde"), align="C")

        self.set_xy(self.l_margin + 56, y + 1)
        self.set_text_color(*INK)
        self.set_font(FONT, "B", 9.5)
        self.cell(0, 5, sanitize(f"{n(done)} af {n(total)} {unit} udført og verificeret"))
        self.set_xy(self.l_margin + 56, y + 7)
        self.set_font(FONT, "", 8.2)
        self.set_text_color(*GREY)
        self.multi_cell(self.w - self.l_margin - self.r_margin - 56, 4,
                        sanitize("Grundlag: " + " · ".join(module_rows)))
        self.set_y(y + 28)


def build(spec: dict, state: dict) -> Path:
    p = project(state, spec["pid"])
    value, done, total = pct(p)
    unit = next(m["unit"] for m in p["modules"] if not m.get("recurring"))

    pdf = Report(spec["name"])
    pdf.alias_nb_pages()
    pdf.add_page()

    # --- top
    pdf.set_fill_color(*COPPER)
    pdf.rect(0, 0, 210, 4, style="F")
    pdf.ln(4)
    pdf.kicker("PROJEKTRAPPORT · " + DATE)
    pdf.h1(spec["name"])
    pdf.kicker(spec["kicker"])
    pdf.para(spec["intro"])

    mods = [m for m in p["modules"] if not m.get("recurring")]
    stat_rows = [f"{m['name'].split(' —')[0]} {n(m['done'])}/{n(m['total'])}" for m in mods]
    pdf.stat_band(value, done, total, unit, stat_rows)

    # --- status table
    pdf.table(["Modul", "Udført", "Estimat", "Del", "Note"],
              [(m["name"], n(m["done"]) + " " + unit, n(m["total"]) + " " + unit,
                n(float(m["done"]) / float(m["total"]) * 100, 1) + " %", m.get("note", ""))
               for m in mods],
              [46, 16, 18, 17, 73])
    if p.get("next") or p.get("blocked_by"):
        pdf.set_font(FONT, "", 8.6)
        pdf.set_text_color(*GREY)
        pdf.multi_cell(0, 4.4, sanitize("Næste skridt: " + (p.get("next") or "—")))
        pdf.ln(1)
        pdf.multi_cell(0, 4.4, sanitize("Blokering: " + (p.get("blocked_by") or "ingen")))
        pdf.ln(2)

    # --- roadmap index
    pdf.h2("Roadmap-indeks")
    pdf.para(spec["roadmap_note"], size=8.4, color=GREY)
    pdf.table(["#", "Punkt", "Status", "Estimat"], spec["roadmap"], [13, 121, 22, 18], status_col=2)

    # --- development path
    pdf.h2("Udviklingsvej")
    pdf.para("Rækkefølgen er valgt så hvert skridt kan verificeres, før det næste starter. "
             "Tid er fra roadmap'en; ejer betyder at det kræver adgang eller en beslutning.", size=8.4, color=GREY)
    pdf.table(["#", "Skridt", "Tid", "Afhænger af", "Hvem"],
              spec["path"], [10, 84, 16, 42, 22])

    # --- works / fails
    pdf.h2("Det virker")
    pdf.bullets(spec["works"], colour=GREEN, marker="+")
    pdf.h2("Det virker ikke")
    pdf.bullets(spec["fails"], colour=RED, marker="–")

    # --- decisions
    pdf.h2("Beslutninger der mangler")
    pdf.bullets(spec["decisions"], colour=INK, marker="?")

    # --- footer note
    pdf.space(3)
    pdf.set_font(FONT, "", 7.6)
    pdf.set_text_color(*GREY)
    pdf.multi_cell(0, 3.9, sanitize(
        "Kilder: projektstatus.json (procenten beregnes, indtastes ikke), docs/roadmap.md, "
        "plugins/steamgears-autopilot/ROADMAP.md og docs/seo.md. Genberegn rapporten med "
        "python3 tools/project_reports.py. \"Færdig\" betyder udført og verificeret — ikke planlagt."))

    OUT.mkdir(exist_ok=True)
    target = OUT / f"{spec['slug']}-{DATE}.pdf"
    pdf.output(str(target))
    return target


def main() -> None:
    state = load_state()
    made = [build(spec, state) for spec in PROJECTS]
    for path in made:
        print(f"  {path.relative_to(ROOT)}  {path.stat().st_size // 1024} KB")
    print(f"{len(made)} rapporter · font: {FONT}")


if __name__ == "__main__":
    main()
