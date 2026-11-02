#!/usr/bin/env python3
"""niche_scanner.py — niche-produkt-scanner: efterspørgsel × vores katalog.

Hvad den gør: læser efterspørgselssignaler fra de kilder der **faktisk** findes, og
sammenholder dem med de produkter butikken allerede har. Et søgeord med høj
efterspørgsel og nul matchende produkter er en produktmulighed; et søgeord vi
allerede dækker, er ikke.

Kilder (og hvorfor kun disse):

  pinterest  Officiel Pinterest API v5, Trends:
             GET /v5/trends/keywords/{region}/top/{trend_type}?include_keywords=…
             Kræver PINTEREST_ACCESS_TOKEN (Standard-adgang). Returnerer RELATIV
             interesse (0-100 pr. søgeord), vækst wow/mom/yoy og en ugentlig
             tidsserie på ca. et år. Ikke absolutte søgetal.
             Der findes INGEN officiel endpoint der søger alle platformens pins på
             et søgeord: /v5/search/partner/pins er closed beta og giver top 10 uden
             engagementstal. Derfor bruges den ikke.

  tiktok     Der findes ingen officiel API til søgeords- eller produktsøgning for
             kommercielle brugere (Display API = egne videoer, Research API = kun
             akademiske institutioner, Shop API = egen katalog). Kilden er derfor en
             manuel eksport fra TikTok Creative Center (Top Products / Keyword
             Insights), gemt som CSV: keyword,views,posts,growth_pct

Ingen af kilderne opfinder tal: mangler token eller eksport, står kilden som
"utilgængelig" i rapporten med årsagen, og scanningen kører videre på de andre.

Kør:
    PINTEREST_ACCESS_TOKEN=... python3 tools/niche_scanner.py --seeds "steampunk corset,dark academia outfit"
    python3 tools/niche_scanner.py --offline            # fixtures, ingen netværk/token
    python3 tools/test_niche_scanner.py                 # tests

Ud: reports/niche-scan-<dato>.md + .json
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import sys
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FIXTURES = Path(__file__).resolve().parent / "fixtures"
REPORTS = ROOT / "reports"
WP_SITE = "https://steamgears.eu"
PINTEREST_API = "https://api.pinterest.com/v5"

# Pinterest bruger 18 kuraterede regioner, ikke frie ISO-koder. Norden er ét område.
REGIONS = (
    "US", "CA", "DE", "FR", "ES", "IT", "DE+AT+CH", "GB+IE", "IT+ES+PT+GR+MT",
    "PL+RO+HU+SK+CZ", "SE+DK+FI+NO", "NL+BE+LU", "AR", "BR", "CO", "MX",
    "MX+AR+CO+CL", "AU+NZ",
)
TREND_TYPES = ("growing", "monthly", "yearly", "seasonal")

STOPWORDS = {
    "the", "and", "for", "with", "your", "you", "from", "that", "this", "into",
    "out", "are", "how", "best", "ideas", "idea", "new", "per", "til", "og", "med",
}


def tokens(text: str) -> set[str]:
    raw = "".join(ch.lower() if ch.isalnum() else " " for ch in text)
    return {w for w in raw.split() if len(w) > 2 and w not in STOPWORDS}


# --------------------------------------------------------------------- modeller
@dataclass
class Signal:
    keyword: str
    source: str
    growth_yoy: float | None = None
    growth_mom: float | None = None
    momentum: float | None = None
    volume_hint: float | None = None  # TikTok views, kun til visning
    series: list[float] = field(default_factory=list)


@dataclass
class Row:
    keyword: str
    source: str
    demand: float
    growth_yoy: float | None
    momentum: float | None
    matches: list[str]
    opportunity: float

    @property
    def coverage(self) -> float:
        return min(len(self.matches), 3) / 3 * 100


# --------------------------------------------------------------------- scoring
def clamp_pct(value: float | None) -> float:
    """Vækst i procent → 0-100. Klippet ved -50 % og +200 %, så ét udsving ikke dominerer."""
    if value is None:
        return 50.0
    return max(0.0, min(100.0, (float(value) + 50) / 250 * 100))


def slope_momentum(series: list[float]) -> float | None:
    """Ændring fra de fire ældste til de fire nyeste uger, i procent."""
    if len(series) < 8:
        return None
    old = sum(series[-8:-4]) / 4
    new = sum(series[-4:]) / 4
    if old <= 0:
        return 100.0 if new > 0 else 0.0
    return (new - old) / old * 100


def demand_score(growth_yoy: float | None, momentum: float | None) -> float:
    """60 % årlig vækst + 40 % seneste fire ugers retning. Dokumenteret, ikke finjusteret."""
    return round(0.6 * clamp_pct(growth_yoy) + 0.4 * clamp_pct(momentum), 1)


def opportunity_score(demand: float, matches: int) -> float:
    """Efterspørgsel gange den del af kataloget vi IKKE dækker.

    Dækning måles i antal matchende produkter (0, 1, 2, 3+) — ikke i kvalitet, fordi
    hverken Pinterest eller TikTok giver os konkurrent- eller salgstal. Det er en
    bevidst grov model: den skal pege på huller, ikke bevise dem.
    """
    coverage = min(matches, 3) / 3
    return round(demand * (1 - coverage), 1)


# --------------------------------------------------------------------- kilder
def load_catalog(offline: bool = False) -> list[dict]:
    """Produkter fra butikken (offentlig Store API — ingen auth). Slug + navn + kategorier."""
    if offline:
        return json.loads((FIXTURES / "catalog.json").read_text(encoding="utf-8"))

    url = f"{WP_SITE}/wp-json/wc/store/v1/products?per_page=100"
    req = urllib.request.Request(url, headers={"User-Agent": "steamgears-niche-scanner/1.0"})
    with urllib.request.urlopen(req, timeout=25) as res:
        data = json.loads(res.read().decode("utf-8"))
    return [
        {
            "id": p.get("id"),
            "name": p.get("name", ""),
            "slug": p.get("slug", ""),
            "categories": [c.get("name", "") for c in p.get("categories", [])],
        }
        for p in data
    ]


def catalog_tokens(products: list[dict]) -> list[set[str]]:
    return [tokens(p.get("name", "")) | tokens(" ".join(p.get("categories", []))) for p in products]


def match_products(keyword: str, catalog: list[dict]) -> list[str]:
    """Produkter hvis navn/kategori deler et *distinktivt* ord med søgeordet.

    Et ord er generisk — og vægtes ikke — hvis det både står i over 40 % af kataloget og i
    mindst fem produkter. Ellers ville "steampunk goggles" melde dækning, selv om vi ikke sælger
    goggles; omvendt må "corset" ikke kasseres i en lille katalog, hvor 4 af 9 produkter er
    corsets. Er alle ord generiske, bruges de alligevel, så et match aldrig forsvinder helt.
    """
    wanted = tokens(keyword)
    if not wanted:
        return []
    haystacks = catalog_tokens(catalog)
    total = max(len(haystacks), 1)
    frequency: dict[str, int] = {}
    for hay in haystacks:
        for token in hay:
            frequency[token] = frequency.get(token, 0) + 1

    def generic(token: str) -> bool:
        count = frequency.get(token, 0)
        return count >= 5 and count / total > 0.4

    distinctive = {token for token in wanted if not generic(token)}
    probe = distinctive or wanted
    return [
        product.get("name") or product.get("slug") or str(product.get("id"))
        for product, hay in zip(catalog, haystacks)
        if probe & hay
    ]


def pinterest_signals(terms: list[str], region: str, trend_type: str, token: str | None,
                      offline: bool = False) -> tuple[list[Signal], str]:
    """Returnerer (signaler, status). Status er menneskelæsbar og havner i rapporten."""
    if offline:
        payload = json.loads((FIXTURES / "pinterest_trends_dk.json").read_text(encoding="utf-8"))
    elif not token:
        return [], "utilgængelig: PINTEREST_ACCESS_TOKEN er ikke sat (kræver Standard-adgang)"
    else:
        query = urllib.parse.urlencode({
            "include_keywords": ",".join(terms),
            "limit": 50,
        })
        url = f"{PINTEREST_API}/trends/keywords/{urllib.parse.quote(region)}/top/{trend_type}?{query}"
        req = urllib.request.Request(url, headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "User-Agent": "steamgears-niche-scanner/1.0",
        })
        try:
            with urllib.request.urlopen(req, timeout=25) as res:
                payload = json.loads(res.read().decode("utf-8"))
        except Exception as exc:  # 401/403/429 skal stå i rapporten, ikke dræbe kørslen
            return [], f"fejl: {exc}"

    items = payload.get("items") or payload.get("data") or []
    signals = []
    for item in items:
        series = [float(v) for v in (item.get("time_series") or {}).values()]
        signals.append(Signal(
            keyword=item.get("keyword", "").strip(),
            source="pinterest",
            growth_yoy=item.get("pct_growth_yoy"),
            growth_mom=item.get("pct_growth_mom"),
            momentum=slope_momentum(series),
            series=series,
        ))
    return [s for s in signals if s.keyword], f"ok: {len(signals)} søgeord ({region}, {trend_type})"


def tiktok_signals(export_path: str | None, offline: bool = False) -> tuple[list[Signal], str]:
    """TikTok har ingen API til dette — læser en manuel Creative Center-eksport."""
    path = Path(export_path) if export_path else (FIXTURES / "tiktok_creative_center.csv" if offline else None)
    if path is None:
        return [], ("utilgængelig: TikTok har ingen officiel søge-API for kommercielle brugere — "
                    "eksportér fra Creative Center og giv stien med --tiktok-export")
    if not path.exists():
        return [], f"fejl: filen findes ikke ({path})"

    signals = []
    with path.open(encoding="utf-8", newline="") as handle:
        for row in csv.DictReader(handle):
            keyword = (row.get("keyword") or "").strip()
            if not keyword:
                continue
            try:
                growth = float(row.get("growth_pct") or 0)
            except ValueError:
                growth = 0.0
            try:
                views = float(row.get("views") or 0)
            except ValueError:
                views = 0.0
            signals.append(Signal(keyword=keyword, source="tiktok", growth_yoy=growth,
                                  volume_hint=views))
    return signals, f"ok: {len(signals)} søgeord (manuel eksport: {path.name})"


# --------------------------------------------------------------------- rapport
def score_rows(signals: list[Signal], catalog: list[dict]) -> list[Row]:
    rows = []
    for signal in signals:
        demand = demand_score(signal.growth_yoy, signal.momentum)
        matches = match_products(signal.keyword, catalog)
        rows.append(Row(
            keyword=signal.keyword,
            source=signal.source,
            demand=demand,
            growth_yoy=signal.growth_yoy,
            momentum=signal.momentum,
            matches=matches,
            opportunity=opportunity_score(demand, len(matches)),
        ))
    return sorted(rows, key=lambda r: (-r.opportunity, -r.demand, r.keyword))


def fmt(value: float | None, suffix: str = "") -> str:
    if value is None:
        return "–"
    text = f"{value:+.0f}".replace("+", "+") if suffix == "%" else f"{value:.0f}"
    return f"{text}{suffix}".replace(".", ",")


def render(rows: list[Row], sources: dict[str, str], meta: dict) -> str:
    gaps = [r for r in rows if not r.matches]
    covered = [r for r in rows if r.matches]

    lines = [
        f"# Niche-produkt-scanning — {meta['date']}",
        "",
        f"Region: **{meta['region']}** · trend-type: **{meta['trend_type']}** · "
        f"katalog: **{meta['catalog_count']} produkter** · søgeord: **{len(rows)}**",
        "",
        "## Kilder",
        "",
        "| Kilde | Status |",
        "| --- | --- |",
    ]
    for name, status in sources.items():
        lines.append(f"| {name} | {status} |")

    lines += [
        "",
        "## Rangerede huller — efterspørgsel vi ikke dækker",
        "",
        "| Søgeord | Kilde | Efterspørgsel | Vækst YoY | Momentum | Match i katalog | Mulighed |",
        "| --- | --- | --- | --- | --- | --- | --- |",
    ]
    if not gaps:
        lines.append("| (ingen huller i dette udtræk) | | | | | | |")
    for row in gaps:
        lines.append(
            f"| {row.keyword} | {row.source} | {row.demand:.0f} | {fmt(row.growth_yoy, '%')} | "
            f"{fmt(row.momentum, '%')} | 0 | **{row.opportunity:.0f}** |"
        )

    lines += [
        "",
        "## Dækket i forvejen",
        "",
        "| Søgeord | Kilde | Efterspørgsel | Match | Mulighed |",
        "| --- | --- | --- | --- | --- |",
    ]
    if not covered:
        lines.append("| (ingen af søgeordene matcher kataloget) | | | | |")
    for row in covered:
        names = ", ".join(row.matches[:3]) + (" …" if len(row.matches) > 3 else "")
        lines.append(f"| {row.keyword} | {row.source} | {row.demand:.0f} | {names} | {row.opportunity:.0f} |")

    lines += [
        "",
        "## Sådan læses tallene",
        "",
        "- **Efterspørgsel** = 60 % årlig vækst + 40 % retning de seneste fire uger, hver klippet til 0-100.",
        "  Pinterest giver *relativ* interesse (0-100 pr. søgeord), ikke absolutte søgetal.",
        "- **Mulighed** = efterspørgsel × (1 − dækning), hvor dækning er 0/1/2/3+ matchende produkter.",
        "  Høj mulighed betyder \"efterspurgt, men vi sælger det ikke\" — ikke \"det sælger godt\".",
        "- **Match** er søgeords-overlap mod produktnavne og -kategorier. Et ord er generisk — og tæller",
        "  ikke — hvis det står i over 40 % af kataloget *og* i mindst fem produkter (fx \"steampunk\"),",
        "  så \"steampunk goggles\" ikke melder dækning. Stadig groft: \"corset\" matcher alle corsets.",
        "",
        "## Hvad kilderne ikke kan levere",
        "",
        "- **Konkurrence og salgstal.** Hverken Pinterest eller TikTok giver adgang til andre sælgeres",
        "  salg, priser eller engagement. Saturation indgår derfor ikke i modellen.",
        "- **Pinterest pinsøgning.** Der findes ingen officiel endpoint til at søge hele platformens pins",
        "  på et søgeord (`/v5/search/partner/pins` er closed beta, giver top 10 og ingen gem/kommentar-tal).",
        "- **TikTok-søgning.** Display API læser kun egne videoer, Research API er forbeholdt akademiske",
        "  institutioner, og Shop API ser kun egen katalog. Creative Center-eksporten er den eneste vej.",
        "",
        f"_Genereret af `tools/niche_scanner.py`. Rå data: `reports/niche-scan-{meta['date']}.json`._",
    ]
    return "\n".join(lines) + "\n"


def run(terms: list[str], region: str, trend_type: str, offline: bool,
        tiktok_export: str | None) -> tuple[list[Row], dict[str, str], list[dict], int]:
    catalog = load_catalog(offline=offline)
    signals: list[Signal] = []
    sources: dict[str, str] = {}

    pin, pin_status = pinterest_signals(terms, region, trend_type,
                                        os.getenv("PINTEREST_ACCESS_TOKEN"), offline=offline)
    sources["Pinterest Trends (API v5)"] = pin_status
    signals += pin

    tt, tt_status = tiktok_signals(tiktok_export, offline=offline)
    sources["TikTok Creative Center (eksport)"] = tt_status
    signals += tt

    rows = score_rows(signals, catalog)
    exit_code = 0 if rows else 2
    return rows, sources, catalog, exit_code


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Niche-produkt-scanner: Pinterest Trends + TikTok-eksport × katalog")
    parser.add_argument("--seeds", default="", help="kommaseparerede søgeord")
    parser.add_argument("--seeds-file", help="fil med ét søgeord pr. linje")
    parser.add_argument("--region", default="SE+DK+FI+NO", choices=REGIONS)
    parser.add_argument("--trend-type", default="seasonal", choices=TREND_TYPES)
    parser.add_argument("--tiktok-export", help="CSV fra TikTok Creative Center (keyword,views,posts,growth_pct)")
    parser.add_argument("--offline", action="store_true", help="brug fixtures, ingen netværk eller token")
    parser.add_argument("--out-dir", default=str(REPORTS))
    args = parser.parse_args(argv)

    terms = [t.strip() for t in args.seeds.split(",") if t.strip()]
    if args.seeds_file:
        terms += [line.strip() for line in Path(args.seeds_file).read_text(encoding="utf-8").splitlines()
                  if line.strip() and not line.startswith("#")]
    if not terms:
        parser.error("giv mindst ét søgeord med --seeds eller --seeds-file")

    rows, sources, catalog, code = run(terms, args.region, args.trend_type, args.offline, args.tiktok_export)

    meta = {"date": date.today().isoformat(), "region": args.region, "trend_type": args.trend_type,
            "catalog_count": len(catalog), "seeds": terms}
    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    md_path = out_dir / f"niche-scan-{meta['date']}.md"
    json_path = out_dir / f"niche-scan-{meta['date']}.json"

    md_path.write_text(render(rows, sources, meta), encoding="utf-8")
    json_path.write_text(json.dumps({
        "meta": meta,
        "sources": sources,
        "rows": [{"keyword": r.keyword, "source": r.source, "demand": r.demand,
                  "growth_yoy": r.growth_yoy, "momentum": r.momentum,
                  "matches": r.matches, "opportunity": r.opportunity} for r in rows],
    }, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"  kilder: {sources}")
    print(f"  søgeord: {len(rows)} · huller: {sum(1 for r in rows if not r.matches)}")
    for row in rows[:8]:
        print(f"    {row.opportunity:>5.0f}  {row.source:<9} {row.keyword:<32} match={len(row.matches)}")
    shown = md_path.relative_to(ROOT) if md_path.is_relative_to(ROOT) else md_path
    print(f"  skrev {shown} og {json_path.name}")

    if code == 2:
        print("  [FEJL] ingen kilde gav data — sæt PINTEREST_ACCESS_TOKEN eller giv --tiktok-export / --offline",
              file=sys.stderr)
    return code


if __name__ == "__main__":
    sys.exit(main())
