#!/usr/bin/env python3
"""Niche-scanning — niche-vurderingens rubrik, kørt igen på indtastede tal.

`docs/library/niche-vurdering-2026-11-01.md` svarede «skift ikke niche» ud fra
håndindtastede tredjepartstal. Svaret kan ikke genkøres, og det er problemet:
næste gang spørgsmålet kommer, skal rækkefølgen kunne regnes ud igen.

Værktøjet tager kandidater ind som CSV eller JSON og giver en score og en dom.
Det henter **ingen** data og har ingen API-nøgler — tallene er input, så samme
input altid giver samme output.

Felter pr. kandidat:

  niche        navnet/søgeordet (påkrævet)
  searches     søgninger pr. måned (påkrævet, > 0)
  listings     antal konkurrerende listings (påkrævet, > 0)
  swing        sæsonsving som okt/jun-forhold (valgfrit; 1 = flad)
  competitor   handmade | mixed | pod (påkrævet)
  difficulty   sværhedsgrad 0–100 (valgfrit, bruges kun som tiebreak)
  price        gennemsnitspris (valgfrit, vises i rapporten)

Danske feltnavne accepteres (`søgninger`, `sværhedsgrad`, `sæsonsving`,
`konkurrent`). Skriv tal uden tusindadskillelse: `18100`, ikke `18.100` —
sidstnævnte afvises med en fejl i stedet for at blive læst som 18,1.

Brug:
  python3 niche_scan.py --input docs/library/niche-kandidater-2026-11-02.csv
  python3 niche_scan.py --input kandidater.csv --report
  python3 niche_scan.py --input kandidater.json --json
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import re
import sys
from datetime import date
from pathlib import Path

# Grænserne er ikke valgt her — de kommer fra niche-vurderingen (Printifys
# tommelfingerregel: < 2.000 listings grøn, 2.000–50.000 gul, > 50.000 hårdt nej).
LISTINGS_EASY = 2_000
LISTINGS_HARD = 50_000
SEARCH_FLOOR = 100
SEARCH_CEILING = 20_000
SWING_FLAT = 3.0
SWING_BRUTAL = 17.0

WEIGHTS = {"listings": 45.0, "searches": 25.0, "swing": 20.0, "competitor": 10.0}

COMPETITOR_POINTS = {"handmade": 3.0, "mixed": 7.0, "pod": 10.0}
COMPETITOR_ALIASES = {
    "handmade": "handmade",
    "håndlavet": "handmade",
    "handlavet": "handmade",
    "mixed": "mixed",
    "blandet": "mixed",
    "pod": "pod",
    "print": "pod",
    "print-on-demand": "pod",
}

FIELD_ALIASES = {
    "niche": "niche",
    "navn": "niche",
    "keyword": "niche",
    "searches": "searches",
    "søgninger": "searches",
    "soegninger": "searches",
    "listings": "listings",
    "antal_listings": "listings",
    "konkurrenter": "listings",
    "swing": "swing",
    "sæsonsving": "swing",
    "saesonsving": "swing",
    "seasonality": "swing",
    "competitor": "competitor",
    "konkurrent": "competitor",
    "konkurrenttype": "competitor",
    "difficulty": "difficulty",
    "sværhedsgrad": "difficulty",
    "svaerhedsgrad": "difficulty",
    "price": "price",
    "pris": "price",
}

THOUSANDS = re.compile(r"^\d{1,3}(\.\d{3})+$")


class RowError(ValueError):
    """En kandidat kunne ikke læses. Fejlen navngiver rækken og feltet."""


def normalise(raw: dict) -> dict:
    """Oversætter feltnavne til de kanoniske, så dansk og engelsk blandes frit."""
    out = {}
    for key, value in raw.items():
        if key is None:
            continue
        canonical = FIELD_ALIASES.get(str(key).strip().lower())
        if canonical is None:
            continue
        out[canonical] = value
    return out


def _number(value, field: str, label: str, required: bool) -> float | None:
    if value is None or str(value).strip() == "":
        if required:
            raise RowError(f"{label}: feltet '{field}' mangler")
        return None
    text = str(value).strip().replace(" ", "").replace("\u00a0", "")
    if THOUSANDS.match(text):
        raise RowError(
            f"{label}: '{field}' = {value!r} ser ud som tusindadskillelse — skriv {text.replace('.', '')}"
        )
    try:
        number = float(text)
    except ValueError:
        raise RowError(f"{label}: '{field}' er ikke et tal: {value!r}") from None
    if not math.isfinite(number):
        raise RowError(f"{label}: '{field}' er ikke et endeligt tal: {value!r}")
    return number


def score_listings(listings: float) -> float:
    weight = WEIGHTS["listings"]
    if listings <= LISTINGS_EASY:
        return weight
    if listings > LISTINGS_HARD:
        return 0.0
    lo, hi = math.log10(LISTINGS_EASY), math.log10(LISTINGS_HARD)
    share = (math.log10(listings) - lo) / (hi - lo)
    return weight - (weight - 10.0) * share


def score_searches(searches: float) -> float:
    weight = WEIGHTS["searches"]
    if searches <= SEARCH_FLOOR:
        return 0.0
    if searches >= SEARCH_CEILING:
        return weight
    lo, hi = math.log10(SEARCH_FLOOR), math.log10(SEARCH_CEILING)
    return weight * (math.log10(searches) - lo) / (hi - lo)


def score_swing(swing: float | None) -> float | None:
    if swing is None:
        return None
    weight = WEIGHTS["swing"]
    if swing <= SWING_FLAT:
        return weight
    if swing >= SWING_BRUTAL:
        return 0.0
    share = (swing - SWING_FLAT) / (SWING_BRUTAL - SWING_FLAT)
    return weight * (1.0 - share)


def score_row(raw: dict, index: int) -> dict:
    row = normalise(raw)
    niche = str(row.get("niche") or "").strip()
    label = f"række {index}" if not niche else f"«{niche}»"

    searches = _number(row.get("searches"), "searches", label, required=True)
    listings = _number(row.get("listings"), "listings", label, required=True)
    swing = _number(row.get("swing"), "swing", label, required=False)
    difficulty = _number(row.get("difficulty"), "difficulty", label, required=False)
    price = _number(row.get("price"), "price", label, required=False)

    if searches <= 0:
        raise RowError(f"{label}: 'searches' skal være større end 0")
    if listings <= 0:
        raise RowError(f"{label}: 'listings' skal være større end 0")
    if swing is not None and swing < 1:
        raise RowError(f"{label}: 'swing' skal være mindst 1 (okt/jun-forhold)")
    if difficulty is not None and not 0 <= difficulty <= 100:
        raise RowError(f"{label}: 'difficulty' skal ligge mellem 0 og 100")

    competitor_raw = str(row.get("competitor") or "").strip().lower()
    competitor = COMPETITOR_ALIASES.get(competitor_raw)
    if competitor is None:
        allowed = ", ".join(sorted(COMPETITOR_POINTS))
        raise RowError(f"{label}: 'competitor' skal være en af {allowed} — fik {competitor_raw!r}")

    parts = {
        "listings": score_listings(listings),
        "searches": score_searches(searches),
        "swing": score_swing(swing),
        "competitor": COMPETITOR_POINTS[competitor],
    }
    available = sum(WEIGHTS[key] for key, value in parts.items() if value is not None)
    points = sum(value for value in parts.values() if value is not None)
    score = round(100.0 * points / available, 1)

    notes = []
    if listings > LISTINGS_HARD:
        notes.append("hårdt nej: over 50.000 listings")
        verdict = "nej"
    elif score >= 70:
        verdict = "grøn"
    elif score >= 45:
        verdict = "gul"
    else:
        verdict = "nej"
    if swing is None:
        notes.append("sæsonsving mangler — 20 % af grundlaget er ikke med")
    if difficulty is None:
        notes.append("sværhedsgrad mangler")
    if competitor == "handmade":
        notes.append("topresultater er håndlavet — POD konkurrerer på pris, ikke håndværk")
    if swing is not None and swing >= SWING_BRUTAL:
        notes.append(f"sæson {swing:g}x — likviditet skal planlægges over de flade måneder")

    return {
        "niche": niche,
        "searches": searches,
        "listings": listings,
        "swing": swing,
        "competitor": competitor,
        "difficulty": difficulty,
        "price": price,
        "score": score,
        "verdict": verdict,
        "parts": parts,
        "available_weight": available,
        "notes": notes,
    }


def rank(rows: list[dict]) -> list[dict]:
    """Højeste score først; sværhedsgrad og navn gør rækkefølgen reproducerbar."""
    return sorted(
        rows,
        key=lambda r: (
            -r["score"],
            r["difficulty"] if r["difficulty"] is not None else 999,
            r["niche"].lower(),
        ),
    )


def scan(records: list[dict]) -> list[dict]:
    if not records:
        raise RowError("ingen kandidater i input")
    return rank([score_row(raw, i) for i, raw in enumerate(records, start=1)])


def load_csv(path: Path) -> list[dict]:
    with path.open(newline="", encoding="utf-8-sig") as handle:
        reader = csv.DictReader(handle)
        if reader.fieldnames is None:
            raise RowError(f"{path}: filen har ingen header")
        return [row for row in reader]


def load_json(path: Path) -> list[dict]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if isinstance(data, dict):
        data = data.get("niches") or data.get("kandidater") or []
    if not isinstance(data, list):
        raise RowError(f"{path}: forventede en liste af kandidater")
    return data


def load(path: Path) -> list[dict]:
    if not path.exists():
        raise RowError(f"{path}: filen findes ikke")
    if path.suffix.lower() == ".json":
        return load_json(path)
    return load_csv(path)


def _fmt(value, digits: int = 0) -> str:
    if value is None:
        return "—"
    if digits:
        return f"{value:,.{digits}f}".replace(",", " ")
    return f"{int(round(value)):,}".replace(",", " ")


def render(rows: list[dict], source: str) -> str:
    lines = [
        f"# Niche-scan — {len(rows)} kandidater",
        "",
        f"Kørt {date.today().isoformat()} på `{source}`. Tallene er input, ikke hentet: "
        "samme fil giver samme rækkefølge.",
        "",
        "| # | Niche | Søgninger/md | Listings | Sværhedsgrad | Sæsonsving | Konkurrent | Score | Dom |",
        "| --- | --- | ---: | ---: | ---: | ---: | --- | ---: | --- |",
    ]
    for position, row in enumerate(rows, start=1):
        swing = f"{row['swing']:g}x" if row["swing"] is not None else "—"
        difficulty = _fmt(row["difficulty"]) if row["difficulty"] is not None else "—"
        lines.append(
            f"| {position} | {row['niche']} | {_fmt(row['searches'])} | {_fmt(row['listings'])} | "
            f"{difficulty} | {swing} | {row['competitor']} | {row['score']:g} | {row['verdict']} |"
        )

    flagged = [row for row in rows if row["notes"]]
    if flagged:
        lines += ["", "## Forbehold pr. kandidat", ""]
        for row in flagged:
            lines.append(f"- **{row['niche']}** — " + "; ".join(row["notes"]) + ".")

    lines += [
        "",
        "## Sådan er scoren regnet",
        "",
        f"- **Listings (45):** ≤ {_fmt(LISTINGS_EASY)} giver 45; derfra logaritmisk ned til 10 ved "
        f"{_fmt(LISTINGS_HARD)}; over {_fmt(LISTINGS_HARD)} giver 0 og **hårdt nej**, uanset score.",
        f"- **Søgninger (25):** 0 ved {_fmt(SEARCH_FLOOR)}/md, 25 ved {_fmt(SEARCH_CEILING)}/md, lineært i log10.",
        f"- **Sæsonsving (20):** 20 ved ≤ {SWING_FLAT:g}x, 0 ved ≥ {SWING_BRUTAL:g}x.",
        "- **Konkurrent (10):** handmade 3, mixed 7, pod 10 — håndlavede topresultater er nichens reelle svaghed.",
        "- **Manglende felt:** vægten normaliseres, så en kandidat uden sæsonsving ikke straffes for at mangle data; "
        "det står i forbeholdene i stedet.",
        f"- **Dom:** grøn ≥ 70, gul ≥ 45, ellers nej. Ved > {_fmt(LISTINGS_HARD)} listings altid nej.",
        "",
        "Grænserne kommer fra [niche-vurdering-2026-11-01.md](niche-vurdering-2026-11-01.md) og er ikke justeret her.",
    ]
    return "\n".join(lines) + "\n"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Scorer niche-kandidater ud fra niche-vurderingens rubrik.")
    parser.add_argument("--input", required=True, type=Path, help="CSV eller JSON med kandidater")
    parser.add_argument(
        "--report",
        nargs="?",
        const=Path(f"docs/library/niche-scan-rapport-{date.today().isoformat()}.md"),
        type=Path,
        help="skriv markdown-rapport (standard: docs/library/niche-scan-rapport-<dato>.md)",
    )
    parser.add_argument("--json", action="store_true", help="maskinlæsbar output i stedet for tabel")
    parser.add_argument("--top", type=int, default=0, help="kun de øverste N kandidater")
    args = parser.parse_args(argv)

    try:
        rows = scan(load(args.input))
    except RowError as error:
        print(f"[FEJL] {error}", file=sys.stderr)
        return 1
    if args.top > 0:
        rows = rows[: args.top]

    if args.json:
        print(json.dumps(rows, ensure_ascii=False, indent=2))
    else:
        print(render(rows, str(args.input)))

    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(render(rows, str(args.input)), encoding="utf-8")
        print(f"[OK] rapport skrevet: {args.report}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
