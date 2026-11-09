#!/usr/bin/env python3
"""
Bygger og validerer de fire niche-payloads.

Kilden er `deploy/nicher/nicher.json` (input). Dette script regner økonomien efter de fire
formler i `docs/library/roi-model-dropshipping-2026-11-09.md` §2 og skriver én payload pr.
niche plus en samlet `all.json`, som er det en deploy-kørsel læser.

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


def build_niche(niche: dict, rules: dict, problems: Problems) -> dict:
    products = []
    for raw in niche["products"]:
        row = dict(raw)
        row.update(economics(raw, rules))
        row["sku"] = sku_for(niche["slug"], raw["slug"])
        products.append(row)

    with_sizes = any("størrelse" in p["short"].lower() or "pasform" in p["short"].lower()
                     for p in niche["products"])
    pages = page_bodies(niche, with_sizes)
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
        problems.error(f"{niche['slug']}: kun {owner_fields} ejerfelter — noget er gættet")
    if len(niche.get("owner_steps", [])) < 4:
        problems.error(f"{niche['slug']}: færre end fire ejerhandlinger")
    if len(niche.get("hooks", [])) < 3:
        problems.error(f"{niche['slug']}: færre end tre hooks")
    if len(niche.get("kpi", [])) < 3:
        problems.error(f"{niche['slug']}: færre end tre KPI-gates")

    return {
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

    built = [build_niche(niche, rules, problems) for niche in niches]

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
