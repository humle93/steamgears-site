#!/usr/bin/env python3
"""
Bygger og validerer de fire niche-payloads.

Kilden er `deploy/nicher/nicher.json` (priser, kostpriser, roller) og
`deploy/nicher/indhold/<slug>.json` (den færdige danske tekst). Dette script regner økonomien
efter de fire formler i `docs/library/roi-model-dropshipping-2026-11-09.md` §2, fletter
teksten ind og skriver én payload pr. niche plus en samlet `all.json`, som er det en
deploy-kørsel læser.

Indholdet er ikke pynt: en niche uden tekst til hver vare, hver side og hver kollektion er en
fejl. Så kan der ikke genereres en CSV, der ser færdig ud, men sælger tomme sider.

    python3 tools/deploy/niche_preflight.py --build   # skriv deploy/nicher/*.json
    python3 tools/deploy/niche_preflight.py --check   # validér + drift mod det committede
    python3 tools/deploy/niche_preflight.py --stats   # tabel med økonomien pr. niche

Tre ting scriptet nægter at overse, fordi de var dyre at finde på live:

  * **En vare under 120 kr i break-even-CPA kan ikke annonceres alene.** Den må kun være
    mer-salg, så `ads: true` på sådan en vare er en fejl, ikke en indstilling.
  * **To nicher på samme vært deler taksonomi.** Samme kollektions-slug på samme host betyder
    at den anden kørsel genbruger den første kørsels termer — derfor er det en fejl, mens samme
    slug på to forskellige værter kun er en advarsel.
  * **Ejerfelter må ikke fjernes for at få en grøn kørsel.** `[EJER: …]` tælles, listes og
    kræves: en side uden dem er en side, hvor nogen har gættet en juridisk oplysning.

Exit 1 ved enhver fejl. `--check` fejler også ved drift, så en håndredigeret payload fanges.
"""

from __future__ import annotations

import argparse
import json
import math
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SOURCE = os.path.join(ROOT, "deploy", "nicher", "nicher.json")
OUT_DIR = os.path.join(ROOT, "deploy", "nicher")
DEFAULT_FIXED_MONTHLY = 1400.0
OWNER_RE = re.compile(r"\[EJER[^\]]*\]")
CPA_FLOOR = 120.0
CONTENT_DIR = os.path.join(OUT_DIR, "indhold")
REQUIRED_PAGES = [
    "start-here", "om-os", "levering-og-retur", "kontakt",
    "handelsbetingelser", "privatlivspolitik", "fortrydelse",
]
LEGAL_PAGES = {"handelsbetingelser", "privatlivspolitik", "fortrydelse"}
# Påstande, der kræver dokumentation, som butikken ikke har endnu. Ordet "medicinsk" er
# tilladt i den ene sætning, der netop afviser, at varen er medicinsk udstyr.
CLAIM_RE = re.compile(
    r"\b(behandler|helbreder|kurerer|lindrer|garanterer|dokumenteret|videnskabeligt|"
    r"testet|melatonin|adhd|autisme)\b", re.I)
# "ikke medicinsk udstyr" er den ene tilladte brug: en afvisning. Alt andet er en påstand.
MEDICAL_RE = re.compile(r"(?<!ikke )medicinsk", re.I)
ABSOLUTE_RE = re.compile(r"\b100\s?%")
ARTIFACT_RE = re.compile(
    r"\b(Saeder|Smaa|Loefter|aeske|Stoerrelse|Jaevn|Spaendes|Haenger|Baerer|hoejder|"
    r"Saadan|Taeppe|behoever|soevn|moerke|vaegt|oerepropper|daarlig|foerste|aend)\b")


class Problems(list):
    def error(self, msg: str) -> None:
        self.append(("FEJL", msg))

    def warn(self, msg: str) -> None:
        self.append(("ADVARSEL", msg))


def money(value: float) -> str:
    return f"{value:,.2f}".replace(",", "\u00a0").replace(".", ",")


def sku_for(niche_slug: str, product_slug: str) -> str:
    """En SKU der ikke kolliderer: nicheprefix + hash, ikke en afkortet slug."""
    import hashlib
    digest = hashlib.sha1(f"{niche_slug}|{product_slug}".encode()).hexdigest()[:6].upper()
    return "SG-" + niche_slug[:3].upper() + "-" + digest


def economics(product: dict, rules: dict) -> dict:
    """De fire formler. Momsen ud af omsætningen — ikke ud af annoncerne."""
    vat = float(rules["vat"])
    rate = float(rules["rate"])
    fixed = float(rules["fixed"])
    price = float(product["price"])
    netto = round(price / (1 + vat), 2)
    payment = round(netto * rate + fixed, 2)
    db = round(netto - float(product["cost"]) - float(product["shipping"])
               - float(product["packaging"]) - payment, 2)
    retur = float(product["return_rate"])
    db_after = round(db * (1 - retur) - retur * float(product["return_cost"]), 2)
    return {
        "price": price,
        "netto": netto,
        "payment": payment,
        "db": db,
        "db_after_return": db_after,
        "db_percent": round((db_after / netto) * 100, 2) if netto else 0.0,
        "break_even_cpa": db_after,
        "break_even_roas": round(price / db_after, 2) if db_after > 0 else None,
    }


def page_bodies(niche: dict, with_sizes: bool) -> list[dict]:
    name = niche["name"]
    tagline = niche["tagline"]
    first = niche["collections"][0]
    hero = next(p for p in niche["products"] if p["role"] == "hero")
    size_slug = "storrelsesguide" if with_sizes else "maal-og-pasform"
    size_title = "Størrelsesguide" if with_sizes else "Mål og pasform"
    size_body = (
        "<p>Alle mål er på det færdige produkt. Mål et stykke tøj du ejer, fladt, og sammenlign.</p>"
        "<table><thead><tr><th>Størrelse</th><th>Bryst (cm)</th><th>Længde (cm)</th>"
        "<th>Bryst (in)</th><th>Længde (in)</th></tr></thead><tbody>"
        + "".join(f"<tr><td>{s}</td><td>[EJER]</td><td>[EJER]</td>"
                  "<td>[EJER]</td><td>[EJER]</td></tr>" for s in ("S", "M", "L", "XL"))
        + "</tbody></table><p>[EJER: tabellen kopieret fra leverandørens måleskema for den "
          "præcise vare.]</p>"
        if with_sizes else
        "<p>Målene står på produktsiden. Mål det sted, varen skal sidde, inden du bestiller.</p>"
        "<ul><li>[EJER: mål og tolerancer pr. vare, målt på en prøve.]</li>"
        "<li>[EJER: maksimal belastning, hvis varen bærer noget.]</li>"
        "<li>[EJER: hvad der ikke er egnet — underlag, vægt, temperatur.]</li></ul>"
    )
    return [
        {"slug": "start-here", "title": "Start her", "body":
            f"<p><strong>{name}</strong> — {tagline}</p>"
            f"<p>Butikken sælger {niche['niche']} til {niche['audience'].lower()}</p>"
            f"<h2>Begynd med</h2><ol><li><strong>{first['name']}</strong> — "
            f"{first['description']}</li><li><strong>{hero['name']}</strong> — {hero['short']}</li>"
            "<li><strong>Levering og retur</strong> — hvad der kommer hvornår.</li></ol>"
            "<p>[EJER: det ene, butikken gør anderledes, i en sætning ejeren selv ville sige.]</p>"},
        {"slug": "om-os", "title": "Om os", "body":
            f"<p>{name} er en lille dansk butik med {niche['niche']}. {tagline}</p>"
            "<p>[EJER: registreret firmanavn, CVR, adresse og telefon — kræves i "
            "handelsbetingelserne og i GPSR-blokken.]</p>"
            "<p>[EJER: hvem står bag, og hvordan svarer man.]</p>"},
        {"slug": "levering-og-retur", "title": "Levering og retur", "body":
            "<p>Alle ordrer sendes fra lager i Danmark.</p><ul>"
            "<li>Behandlingstid: [EJER: dage].</li>"
            "<li>Levering: [EJER: zoner og dage].</li>"
            "<li>Fragtpris: [EJER: pris pr. zone, fri fragt over X kr.].</li></ul>"
            "<h2>Fortrydelsesret</h2><p>Du har 14 dage fra modtagelsen. "
            "[EJER: returadresse og hvem der betaler returfragten.]</p>"
            "<p>Brug fortrydelsesformularen på <a href=\"/fortrydelse/\">Fortrydelse</a>.</p>"},
        {"slug": size_slug, "title": size_title, "body": size_body},
        {"slug": "kontakt", "title": "Kontakt", "body":
            "<p>Spørgsmål om en ordre, en størrelse eller en retur?</p>"
            "<p>E-mail: [EJER: butikkens e-mail] · Telefon: [EJER: telefon] · "
            "Adresse: [EJER: adresse].</p><p>Vi svarer inden for [EJER: timer].</p>"},
    ]


def _strings(value):
    """Alle tekststrenge i en struktur, så påstande kan tjekkes ét sted."""
    if isinstance(value, str):
        yield value
    elif isinstance(value, dict):
        for item in value.values():
            yield from _strings(item)
    elif isinstance(value, list):
        for item in value:
            yield from _strings(item)


def load_content(niche_slug: str, problems: Problems) -> dict | None:
    path = os.path.join(CONTENT_DIR, niche_slug + ".json")
    if not os.path.isfile(path):
        problems.error(f"{niche_slug}: indhold mangler — {os.path.relpath(path, ROOT)} "
                       "skal beskrive sider, varer, kollektioner og FAQ")
        return None
    try:
        with open(path, encoding="utf-8") as fh:
            content = json.load(fh)
    except json.JSONDecodeError as exc:
        problems.error(f"{niche_slug}: indhold er ikke gyldig JSON ({exc})")
        return None
    if content.get("slug") != niche_slug:
        problems.error(f"{niche_slug}: indholdets slug er {content.get('slug')!r}")
        return None
    return content


def faq_page(faq: list[dict], brand: str) -> dict:
    body = "".join(f"<h3>{item['q']}</h3><p>{item['a']}</p>" for item in faq)
    structured = json.dumps({
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {"@type": "Question", "name": item["q"],
             "acceptedAnswer": {"@type": "Answer", "text": item["a"]}}
            for item in faq
        ],
    }, ensure_ascii=False)
    return {
        "slug": "ofte-stillede-spoergsmaal",
        "title": "Ofte stillede spørgsmål",
        "seo_title": f"Ofte stillede spørgsmål om køb og brug | {brand}",
        "seo_description": "Svar på det, kunderne spørger om: forskellen på sættene, rensemiddel, "
                           "materialer, pasform, levering og fortrydelse.",
        "body": body + "<script type='application/ld+json'>" + structured + "</script>",
    }


def check_copy(niche_slug: str, content: dict, problems: Problems) -> None:
    """Teksten skal kunne publiceres: dækning, længder, ejerfelter og ingen udokumenterede påstande."""
    pages = content.get("pages") or []
    slugs = [page.get("slug") for page in pages]
    if len(set(slugs)) != len(slugs):
        problems.error(f"{niche_slug}: to sider deler slug")
    required = set(REQUIRED_PAGES) | {content.get("page_extra", "")}
    missing = sorted(required - set(slugs))
    if missing:
        problems.error(f"{niche_slug}: sider mangler: {', '.join(missing)}")
    for page in pages:
        where = f"{niche_slug}/{page.get('slug', '?')}"
        if not page.get("title"):
            problems.error(f"{where}: sidetitel mangler")
        for field, low, high in (("seo_title", 15, 60), ("seo_description", 60, 155)):
            value = page.get(field, "")
            if not low <= len(value) <= high:
                problems.error(f"{where}: {field} er {len(value)} tegn (krav {low}-{high})")
        if len(page.get("body", "")) < 300:
            problems.error(f"{where}: sideteksten er under 300 tegn")

    faq = content.get("faq") or []
    if len(faq) < 6:
        problems.error(f"{niche_slug}: færre end seks FAQ-poster")
    collected = "\n".join(_strings(content))
    # Jura-siderne taler om at "behandle personoplysninger"; markedsføringen må ikke tale om at
    # behandle noget som helst. Derfor scannes de to ting hver for sig.
    marketing = [page for page in pages if page.get("slug") not in LEGAL_PAGES]
    marketing += [{key: content.get(key) for key in
                   ("products", "collections", "faq", "hooks", "ads", "emails")}]
    claim_text = "\n".join(_strings(marketing))
    identifiers = {content.get("slug", ""), content.get("page_extra", "")}
    identifiers |= {page.get("slug", "") for page in pages}
    identifiers |= {collection.get("slug", "") for collection in content.get("collections") or []}
    identifiers |= set((content.get("products") or {}).keys())
    artifact_text = collected
    for identifier in sorted(identifiers, key=len, reverse=True):
        if identifier:
            artifact_text = artifact_text.replace(identifier, " ")
    for pattern, label, text in ((CLAIM_RE, "påstand", claim_text),
                                 (MEDICAL_RE, "medicinsk påstand", claim_text),
                                 (ABSOLUTE_RE, "absolut påstand", collected),
                                 (ARTIFACT_RE, "ASCII-rest", artifact_text)):
        hits = sorted({match.group(0).lower() for match in pattern.finditer(text)})
        if hits:
            problems.error(f"{niche_slug}: {label} uden dokumentation: {', '.join(hits)}")
    if collected.count("[EJER") != len(OWNER_RE.findall(collected)):
        problems.error(f"{niche_slug}: et ejerfelt er skrevet uden afsluttende ]")


def merge_content(niche: dict, payload: dict, content: dict, problems: Problems) -> dict:
    """Læg den færdige tekst oven på økonomien, og kontrollér at ingen vare er uden tekst."""
    products = {p["slug"]: p for p in payload["products"]}
    copy = content.get("products") or {}
    missing = sorted(set(products) - set(copy))
    extra = sorted(set(copy) - set(products))
    if missing:
        problems.error(f"{niche['slug']}: indhold mangler for: {', '.join(missing)}")
    if extra:
        problems.error(f"{niche['slug']}: indholdet har ukendte varer: {', '.join(extra)}")
    for slug, product in products.items():
        text = copy.get(slug) or {}
        for field in ("name", "short", "bullets", "long", "specs", "seo_title",
                      "seo_description", "image_alt"):
            value = text.get(field)
            if value in (None, "", []):
                problems.error(f"{niche['slug']}/{slug}: indholdsfeltet '{field}' mangler")
            else:
                product[field] = value
        if len(product.get("bullets") or []) < 3:
            problems.error(f"{niche['slug']}/{slug}: færre end tre punkter")
        if len(product.get("long") or "") < 300:
            problems.error(f"{niche['slug']}/{slug}: produktteksten er under 300 tegn")
        if not 3 <= len(product.get("specs") or []) <= 8:
            problems.error(f"{niche['slug']}/{slug}: antal specifikationer skal være 3-8")
        for field, high in (("seo_title", 60), ("seo_description", 155)):
            if len(product.get(field) or "") > high:
                problems.error(f"{niche['slug']}/{slug}: {field} over {high} tegn")

    collections = content.get("collections") or []
    if len(collections) != 5:
        problems.error(f"{niche['slug']}: indholdet skal have fem kollektioner")
    by_slug = {c["slug"]: c for c in collections}
    for collection in payload["collections"]:
        text = by_slug.get(collection["slug"])
        if not text:
            problems.error(f"{niche['slug']}: indhold mangler for kollektionen "
                           f"{collection['slug']}")
            continue
        collection["intro"] = text.get("intro", "")
        collection["seo_title"] = text.get("seo_title", "")
        collection["seo_description"] = text.get("seo_description", "")
        if len(collection["intro"]) < 100:
            problems.error(f"{niche['slug']}/{collection['slug']}: kollektionsteksten er for kort")

    faq = content.get("faq") or []
    pages = list(content.get("pages") or [])
    if faq:
        pages.append(faq_page(faq, niche["name"]))
    payload["pages"] = pages
    payload["faq"] = faq
    payload["ads"] = content.get("ads") or []
    payload["emails"] = content.get("emails") or []
    payload["positioning"] = content.get("positioning", "")
    payload["tagline"] = content.get("tagline", payload["tagline"])
    payload["hooks"] = content.get("hooks") or payload["hooks"]
    if payload["ads"] and len(payload["ads"]) < 2:
        problems.error(f"{niche['slug']}: annoncesættet skal have mindst to annoncer")
    payload["counts"].update({
        "pages": len(pages),
        "faq": len(faq),
        "ads": len(payload["ads"]),
        "emails": len(payload["emails"]),
        "owner_fields": sum(len(OWNER_RE.findall(page["body"])) for page in pages),
    })
    payload["owner_blockers"] = sorted(
        {m for page in pages for m in OWNER_RE.findall(page["body"])})
    return payload


def build_niche(niche: dict, rules: dict, problems: Problems,
                content: dict | None = None) -> dict:
    products = []
    for raw in niche["products"]:
        row = dict(raw)
        row.update(economics(raw, rules))
        row["sku"] = sku_for(niche["slug"], raw["slug"])
        products.append(row)

    pages = page_bodies(niche, with_sizes=False)
    owner_fields = sum(len(OWNER_RE.findall(page["body"])) for page in pages)
    blockers = sorted({m for page in pages for m in OWNER_RE.findall(page["body"])})
    fixed = float(niche.get("fixed_monthly", DEFAULT_FIXED_MONTHLY))
    hero = next((p for p in products if p["role"] == "hero"), None)
    ads = [p for p in products if p.get("ads")]
    worst = min((p["break_even_cpa"] for p in ads), default=0.0)

    # --- krav, der gælder hver niche -------------------------------------------------
    if len(niche["collections"]) != 5:
        problems.error(f"{niche['slug']}: 5 kollektioner krævet, fandt {len(niche['collections'])}")
    slugs = [c["slug"] for c in niche["collections"]]
    if len(set(slugs)) != len(slugs):
        problems.error(f"{niche['slug']}: to kollektioner deler slug")
    if hero is None:
        problems.error(f"{niche['slug']}: ingen vare med role=hero")
    if not any(p["role"] == "bundle" for p in products):
        problems.error(f"{niche['slug']}: ingen bundle")
    if not any(p["role"] == "bump" for p in products):
        problems.error(f"{niche['slug']}: ingen mer-salg-vare (role=bump)")
    for p in products:
        if not 0 <= float(p["return_rate"]) <= 1:
            problems.error(f"{niche['slug']}/{p['slug']}: return_rate uden for 0-1")
        if p["role"] == "bump" and float(p["shipping"]) != 0:
            problems.error(f"{niche['slug']}/{p['slug']}: mer-salg skal have 0 i fragt")
        if p["break_even_cpa"] < CPA_FLOOR and p.get("ads"):
            problems.error(
                f"{niche['slug']}/{p['slug']}: break-even-CPA {money(p['break_even_cpa'])} kr. "
                f"er under {CPA_FLOOR:.0f} kr. og må ikke annonceres alene (sæt ads: false)"
            )
        if p["collection"] not in slugs:
            problems.error(f"{niche['slug']}/{p['slug']}: ukendt kollektion {p['collection']}")
        if len(p["bullets"]) < 3:
            problems.error(f"{niche['slug']}/{p['slug']}: færre end tre punkter")
    if owner_fields < 6:
        pass  # tælles igen efter teksten er flettet ind i merge_content

    if len(niche.get("owner_steps", [])) < 4:
        problems.error(f"{niche['slug']}: færre end fire ejerhandlinger")
    if len(niche.get("hooks", [])) < 3:
        problems.error(f"{niche['slug']}: færre end tre hooks")
    if len(niche.get("kpi", [])) < 3:
        problems.error(f"{niche['slug']}: færre end tre KPI-gates")

    payload = {
        "schema": 1,
        "slug": niche["slug"],
        "name": niche["name"],
        "niche": niche["niche"],
        "host": niche["host"],
        "platform": niche["platform"],
        "tagline": niche["tagline"],
        "audience": niche["audience"],
        "status": niche["status"],
        "plan_key": "niche-" + niche["slug"] + "-" + str(len(products)) + "p",
        "collections": niche["collections"],
        "products": products,
        "pages": pages,
        "menu": ["Start her", "Shop"] + [c["name"] for c in niche["collections"][:3]]
                + ["Guides", "Om os", "Kontakt"],
        "schema": ["Organization", "WebSite", "BreadcrumbList", "ItemList", "Product", "FAQPage"],
        "hooks": niche["hooks"],
        "kpi": niche["kpi"],
        "owner_steps": niche["owner_steps"],
        "owner_blockers": blockers,
        "assumptions": {
            "fixed_monthly": fixed,
            "vat": rules["vat"],
            "payment_rate": rules["rate"],
            "payment_fixed": rules["fixed"],
            "costs": "estimater, ingen leverandørtilbud",
        },
        "counts": {
            "collections": len(niche["collections"]),
            "products": len(products),
            "ads": len(ads),
            "pages": len(pages),
            "faq": 0,
            "emails": 0,
            "owner_fields": owner_fields,
            "owner_steps": len(niche["owner_steps"]),
        },
        "economics": {
            "hero_break_even_cpa": hero["break_even_cpa"] if hero else None,
            "hero_break_even_roas": hero["break_even_roas"] if hero else None,
            "worst_ad_break_even_cpa": worst,
            "bundle_lift": round(
                max((p["db_after_return"] for p in products if p["role"] == "bundle"), default=0)
                - (hero["db_after_return"] if hero else 0), 2),
            "orders_to_cover_fixed": math.ceil(fixed / hero["db_after_return"])
            if hero and hero["db_after_return"] > 0 else None,
        },
        "readiness": [
            {"id": step.split(":")[0].strip(), "owner": True,
             "step": step.split(":", 1)[1].strip() if ":" in step else step}
            for step in niche["owner_steps"]
        ] + [
            {"id": "printful", "owner": True, "step": "Mockups og trykfiler pr. vare"},
            {"id": "publish", "owner": True, "step": "Godkend og publicér — agenten skriver kun kladder"},
        ],
    }
    if content is not None:
        payload = merge_content(niche, payload, content, problems)
    return payload


def validate_all(source: dict, problems: Problems) -> list[dict]:
    rules = source["payment"]
    niches = source["niches"]
    if len(niches) != 4:
        problems.error(f"fire nicher krævet, fandt {len(niches)}")
    seen: dict[str, dict] = {}
    hosts: dict[str, str] = {}
    for niche in niches:
        for field in ("slug", "name", "host", "audience", "tagline", "status",
                      "collections", "products", "hooks", "kpi", "owner_steps"):
            if not niche.get(field):
                problems.error(f"{niche.get('slug', '?')}: feltet '{field}' mangler")
        if niche["slug"] in seen:
            problems.error(f"dubleret niche-slug: {niche['slug']}")
        seen[niche["slug"]] = niche
        if niche["host"] in hosts:
            problems.error(f"{niche['slug']} og {hosts[niche['host']]} deler værten {niche['host']}")
        hosts[niche["host"]] = niche["slug"]

    contents = {niche["slug"]: load_content(niche["slug"], problems) for niche in niches}
    for niche in niches:
        content = contents.get(niche["slug"])
        if content is None:
            continue
        check_copy(niche["slug"], content, problems)
        products = content.get("products") or {}
        known = {p["slug"] for p in niche["products"]}
        for slug, text in products.items():
            if slug not in known:
                problems.error(f"{niche['slug']}: indholdet har en ukendt vare: {slug}")
            if not text.get("seo_title") or not text.get("seo_description"):
                problems.error(f"{niche['slug']}/{slug}: SEO-tekst mangler")
    built = [build_niche(niche, rules, problems, contents.get(niche["slug"]))
             for niche in niches]

    # Produkt-slugs og SKU'er skal være unikke på tværs af alle fire.
    product_slugs: dict[str, str] = {}
    skus: dict[str, str] = {}
    for payload in built:
        for p in payload["products"]:
            if p["slug"] in product_slugs:
                problems.error(f"produkt-slug {p['slug']} bruges af både "
                               f"{product_slugs[p['slug']]} og {payload['slug']}")
            product_slugs[p["slug"]] = payload["slug"]
            if p["sku"] in skus:
                problems.error(f"SKU {p['sku']} bruges af både {skus[p['sku']]} og {payload['slug']}")
            skus[p["sku"]] = payload["slug"]

    # Taksonomi: samme slug på samme vært er en fejl, på forskellige værter kun en advarsel.
    taxonomy: dict[tuple[str, str], str] = {}
    for payload in built:
        for collection in payload["collections"]:
            key = (payload["host"], collection["slug"])
            if key in taxonomy:
                problems.error(f"kollektionen '{collection['slug']}' findes på {payload['host']} "
                               f"i både {taxonomy[key]} og {payload['slug']}")
            taxonomy[key] = payload["slug"]
    by_slug: dict[str, list[str]] = {}
    for payload in built:
        for collection in payload["collections"]:
            by_slug.setdefault(collection["slug"], []).append(payload["slug"])
    for slug, owners in by_slug.items():
        if len(owners) > 1:
            problems.warn(f"kollektionen '{slug}' bruges af {', '.join(owners)} — "
                          "fint på hver sin vært, en kollision hvis de deler WooCommerce-site")
    return built


def payload_text(payload: dict) -> str:
    return json.dumps(payload, ensure_ascii=False, indent=2) + "\n"


def write_payloads(built: list[dict], source: dict) -> None:
    for payload in built:
        with open(os.path.join(OUT_DIR, payload["slug"] + ".json"), "w", encoding="utf-8") as fh:
            fh.write(payload_text(payload))
    combined = {
        "schema": 1,
        "updated": source["updated"],
        "note": "Samlet deploy-payload for alle fire nicher. Kilden er nicher.json; "
                "denne fil genereres af tools/deploy/niche_preflight.py --build.",
        "niches": built,
        "totals": {
            "niches": len(built),
            "products": sum(p["counts"]["products"] for p in built),
            "collections": sum(p["counts"]["collections"] for p in built),
            "pages": sum(p["counts"]["pages"] for p in built),
            "owner_fields": sum(p["counts"]["owner_fields"] for p in built),
            "owner_steps": sum(p["counts"]["owner_steps"] for p in built),
        },
    }
    with open(os.path.join(OUT_DIR, "all.json"), "w", encoding="utf-8") as fh:
        fh.write(json.dumps(combined, ensure_ascii=False, indent=2) + "\n")


def check_drift(built: list[dict], source: dict, problems: Problems) -> None:
    for payload in built:
        path = os.path.join(OUT_DIR, payload["slug"] + ".json")
        if not os.path.isfile(path):
            problems.error(f"{os.path.relpath(path, ROOT)} mangler — kør --build")
            continue
        with open(path, encoding="utf-8") as fh:
            committed = fh.read()
        if committed != payload_text(payload):
            problems.error(f"{os.path.relpath(path, ROOT)} er ude af drift — kør --build")
    all_path = os.path.join(OUT_DIR, "all.json")
    if not os.path.isfile(all_path):
        problems.error("deploy/nicher/all.json mangler — kør --build")


def print_stats(built: list[dict]) -> None:
    header = f"{'Niche':<12}{'Varer':>6}{'Hero-CPA':>11}{'ROAS':>7}{'Bundle+':>9}{'Ordre/fast':>12}{'Ejerfelter':>11}"
    print(header)
    print("-" * len(header))
    for p in built:
        eco = p["economics"]
        print(f"{p['name']:<12}{p['counts']['products']:>6}"
              f"{money(eco['hero_break_even_cpa']):>11}"
              f"{str(eco['hero_break_even_roas']).replace('.', ','):>7}"
              f"{money(eco['bundle_lift']):>9}"
              f"{str(eco['orders_to_cover_fixed']):>12}"
              f"{p['counts']['owner_fields']:>11}")
    total = sum(p["counts"]["owner_fields"] for p in built)
    steps = sum(p["counts"]["owner_steps"] for p in built)
    print(f"\n{len(built)} nicher · {sum(p['counts']['products'] for p in built)} varer · "
          f"{sum(p['counts']['pages'] for p in built)} sider · "
          f"{total} ejerfelter · {steps} ejerhandlinger")


def main() -> int:
    parser = argparse.ArgumentParser(description="Byg og validér niche-payloads")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--build", action="store_true", help="skriv deploy/nicher/*.json")
    group.add_argument("--check", action="store_true", help="validér og tjek drift")
    group.add_argument("--stats", action="store_true", help="vis økonomien pr. niche")
    args = parser.parse_args()

    with open(SOURCE, encoding="utf-8") as fh:
        source = json.load(fh)

    problems = Problems()
    built = validate_all(source, problems)
    if args.build and not any(kind == "FEJL" for kind, _ in problems):
        write_payloads(built, source)
    if args.check:
        check_drift(built, source, problems)

    if args.stats:
        print_stats(built)

    errors = [m for kind, m in problems if kind == "FEJL"]
    warnings = [m for kind, m in problems if kind == "ADVARSEL"]
    for kind, msg in problems:
        print(f"{kind}: {msg}", file=sys.stderr)
    if errors:
        print(f"\n{len(errors)} fejl, {len(warnings)} advarsler.", file=sys.stderr)
        return 1
    if args.build:
        print(f"Skrev {len(built)} payloads til deploy/nicher/.")
    if args.check:
        print(f"OK: {len(built)} nicher, ingen fejl, {len(warnings)} advarsler, ingen drift.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
