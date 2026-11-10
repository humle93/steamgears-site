#!/usr/bin/env python3
"""
Måler hele niche-fladen: sider, varer, FAQ, hooks, annoncer og mails — og melder hullerne.

Formålet er at svare på »er der stadig en tynd kladde?« med tal i stedet for et indtryk.
Værktøjet skriver intet; det læser `deploy/nicher/indhold/<slug>.json` og rapporterer:

  * sider under det gulv, preflighten kræver for deres type (`PAGE_FLOOR`),
  * kladde-tekst (TODO, »kommer snart«, »skal udfyldes« …) og ejerfelter uden navn,
  * tomme eller tynde sektioner: annoncer, mails, hooks og produkttekst,
  * hvor mange felter ejeren stadig skal udfylde.

Exit 1 hvis der findes et hul, så det kan bruges som gate i hånden:

    python3 tools/niche_surface.py            # hele fladen
    python3 tools/niche_surface.py --json     # samme tal som JSON

Det er en måling, ikke et værn: det kører ikke i `tools/natlige-vaern.sh`, fordi
`niche_preflight.py --check` allerede afviser de samme fejl ved build.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CONTENT_DIR = ROOT / "deploy" / "nicher" / "indhold"
NICHES = [("kabine", "KABINE"), ("lejebolig", "OPLOFT"), ("sensorisk", "STILLE"), ("soevn", "IMMER")]

sys.path.insert(0, str(ROOT / "tools" / "deploy"))
try:  # samme gulve som preflighten, så de to ikke kan blive uenige
    from niche_preflight import PAGE_FLOOR, EXTRA_PAGE_FLOOR  # type: ignore
except Exception:  # pragma: no cover - kun hvis filen er flyttet
    PAGE_FLOOR = {"handelsbetingelser": 3000, "privatlivspolitik": 3000, "fortrydelse": 2000,
                  "levering-og-retur": 1800, "start-here": 1000, "om-os": 900, "kontakt": 900,
                  "ofte-stillede-spoergsmaal": 2500}
    EXTRA_PAGE_FLOOR = 900

STUB_RE = re.compile(
    r"(\bTBD\b|\bTODO\b|\bFIXME\b|\bXXX\b|lorem ipsum|kommer snart|under opbygning|"
    r"skal udfyldes|skal være udfyldt|indsættes senere|fyldes ud senere|placeholder)",
    re.I,
)
BARE_OWNER_RE = re.compile(r"\[EJER\s*\]")
OWNER_RE = re.compile(r"\[EJER[^\]]*\]")

AD_FLOORS = {"name": 3, "angle": 8, "primary": 150, "headline": 8, "description": 20, "creative": 80}
MAIL_FLOORS = {"name": 3, "subject": 10, "preheader": 15, "body": 250}


def plain(html: str) -> str:
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", html)).strip()


def audit() -> dict:
    report = {"niches": [], "gaps": []}
    for slug, name in NICHES:
        path = CONTENT_DIR / f"{slug}.json"
        if not path.is_file():
            report["gaps"].append(f"{name}: {path.relative_to(ROOT)} mangler")
            continue
        content = json.loads(path.read_text(encoding="utf-8"))
        entry = {"slug": slug, "name": name, "pages": [], "ads": len(content.get("ads") or []),
                 "emails": len(content.get("emails") or []), "hooks": len(content.get("hooks") or []),
                 "products": len(content.get("products") or {}), "faq": len(content.get("faq") or [])}
        for page in content.get("pages") or []:
            body = page.get("body", "")
            floor = PAGE_FLOOR.get(page.get("slug"), EXTRA_PAGE_FLOOR)
            row = {"slug": page.get("slug"), "chars": len(body), "floor": floor,
                   "fields": len(OWNER_RE.findall(body)), "stubs": sorted({m.group(0).lower()
                                                                            for m in STUB_RE.finditer(plain(body))}),
                   "bare": len(BARE_OWNER_RE.findall(body))}
            entry["pages"].append(row)
            where = f"{name}/{row['slug']}"
            if row["chars"] < floor:
                report["gaps"].append(f"{where}: {row['chars']} tegn, gulvet er {floor}")
            if row["stubs"]:
                report["gaps"].append(f"{where}: kladde-tekst ({', '.join(row['stubs'])})")
            if row["bare"]:
                report["gaps"].append(f"{where}: {row['bare']} ejerfelter uden navn")
        for index, ad in enumerate(content.get("ads") or [], 1):
            for field, low in AD_FLOORS.items():
                if len(str(ad.get(field, "")).strip()) < low:
                    report["gaps"].append(f"{name}/annonce {index}: {field} under {low} tegn")
        for index, mail in enumerate(content.get("emails") or [], 1):
            for field, low in MAIL_FLOORS.items():
                if len(str(mail.get(field, "")).strip()) < low:
                    report["gaps"].append(f"{name}/mail {index}: {field} under {low} tegn")
        for index, hook in enumerate(content.get("hooks") or [], 1):
            if len(str(hook).strip()) < 25:
                report["gaps"].append(f"{name}/hook {index}: under 25 tegn")
        for key, product in (content.get("products") or {}).items():
            words = len(plain(product.get("long", "")).split())
            if len(plain(product.get("short", ""))) < 40 or words < 60 or len(product.get("bullets") or []) < 3:
                report["gaps"].append(f"{name}/vare {key}: kort {len(plain(product.get('short', '')))} tegn, "
                                      f"lang {words} ord, {len(product.get('bullets') or [])} bullets")
        if entry["ads"] < 3:
            report["gaps"].append(f"{name}: {entry['ads']} annoncer (kræver mindst 3)")
        if entry["emails"] < 3:
            report["gaps"].append(f"{name}: {entry['emails']} mails (kræver mindst 3)")
        if entry["hooks"] < 4:
            report["gaps"].append(f"{name}: {entry['hooks']} hooks (kræver mindst 4)")
        report["niches"].append(entry)
    return report


def main() -> int:
    parser = argparse.ArgumentParser(description="Mål niche-fladen for tynde sider og tomme sektioner")
    parser.add_argument("--json", action="store_true", help="skriv rapporten som JSON")
    args = parser.parse_args()

    report = audit()
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return 1 if report["gaps"] else 0

    header = f"{'butik':<8}{'sider':>6}{'tegn':>7}{'felt':>6}{'ads':>5}{'mails':>6}{'hooks':>6}{'varer':>6}{'faq':>4}"
    print(header)
    print("-" * len(header))
    for entry in report["niches"]:
        chars = sum(page["chars"] for page in entry["pages"])
        fields = sum(page["fields"] for page in entry["pages"])
        print(f"{entry['name']:<8}{len(entry['pages']):>6}{chars:>7}{fields:>6}{entry['ads']:>5}"
              f"{entry['emails']:>6}{entry['hooks']:>6}{entry['products']:>6}{entry['faq']:>4}")

    print()
    if report["gaps"]:
        print(f"{len(report['gaps'])} hul(ler):")
        for gap in report["gaps"]:
            print("  -", gap)
        return 1
    print("Ingen huller: ingen tynde sider, ingen kladde-tekst, ingen tomme sektioner.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
