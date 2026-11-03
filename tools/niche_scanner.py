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

Tre grænser som koden respekterer (se docs/library/niche-scan-procedure-2026-11-02.md §7):

  1. **Momentum kommer fra kildens egne felter** (`pct_growth_mom`, ellers `yoy`), ikke
     fra tidsserien: Pinterest min-max-normaliserer hver graf, så et forhold mellem to
     uger i serien er skævt. Serien bruges kun til **form** — top- og bundmåned.
  2. **Lavsæson vises som indeksniveau**, ikke kun som vækst: det er lavsæsonen der
     afgør om en sæsonniche kan bære lager.
  3. **Nuller er censur, ikke nul efterspørgsel.** De tælles og holdes ude af
     gennemsnittene; en række der kun består af nuller rangeres ikke. Og en kørsel skal
     have set søgeordet på **mindst tre forskellige dage** før rækken kaldes «Anbefalet».

Kør:
    PINTEREST_ACCESS_TOKEN=... python3 tools/niche_scanner.py --seeds "steampunk corset,dark academia outfit"
    python3 tools/niche_scanner.py --offline            # fixtures, ingen netværk/token
    python3 tools/test_niche_scanner.py                 # tests

Pinterest-tokenet holder 30 dage. Er PINTEREST_REFRESH_TOKEN, PINTEREST_CLIENT_ID og
PINTEREST_CLIENT_SECRET sat, byttes det automatisk når Pinterest svarer 401, og det nye
par gemmes i filen fra `--token-out`, så næste kørsel kan bruge det.

Ud: reports/niche-scan-<dato>.md + .json og reports/niche-scan-history.json (kørselsdage).
En kørsel uden data skriver intet og rykker ikke tre-dages-gaten — den natlige CI-kørsel
(.github/workflows/niche-scan.yml) bruger netop det til at skelne «målt» fra «forsøgt».
"""

from __future__ import annotations

import argparse
import base64
import csv
import json
import os
import sys
import time
import urllib.error
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

# Hvor mange forskellige dage et søgeord skal være målt på, før rækken kaldes «Anbefalet».
MIN_DAYS = 3
# Hvor mange gange et 429/5xx-svar på Pinterest forsøges før kilden meldes fejlet.
RETRIES = 3
HISTORY_NAME = "niche-scan-history.json"

DA_MONTHS = ("jan", "feb", "mar", "apr", "maj", "jun", "jul", "aug", "sep", "okt", "nov", "dec")

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
    volume_hint: float | None = None  # TikTok views, kun til visning
    points: list[tuple[str, float]] = field(default_factory=list)  # (dato/indeks, værdi)

    @property
    def momentum(self) -> float | None:
        """Retning fra kildens eget `mom`-felt — aldrig udregnet af den normaliserede serie.

        Mangler feltet (TikTok-eksporten har kun én vækstkolonne), er retningen ukendt og
        vægtes neutralt. At genbruge `yoy` her ville tælle samme tal to gange i efterspørgslen.
        """
        return self.growth_mom


@dataclass
class Season:
    """Form og lavsæson udledt af tidsserien, med nuller behandlet som censur."""
    observed: int = 0      # punkter over nul
    censored: int = 0      # nuller = under kildens tærskel, ikke "ingen efterspørgsel"
    has_series: bool = False         # False = kilden giver ingen tidsserie (TikTok-eksporten)
    low_level: float | None = None   # gennemsnit af den nederste kvartil
    peak_label: str | None = None
    trough_label: str | None = None

    @property
    def all_censored(self) -> bool:
        """Kun censur når serien *findes* og udelukkende består af nuller."""
        return self.has_series and self.observed == 0

    @property
    def low_text(self) -> str:
        return "–" if self.low_level is None else f"{self.low_level:.0f}"


@dataclass
class Row:
    keyword: str
    source: str
    demand: float
    growth_yoy: float | None
    momentum: float | None
    matches: list[str]
    opportunity: float
    season: Season = field(default_factory=Season)
    days_seen: int = 1

    @property
    def coverage(self) -> float:
        return min(len(self.matches), 3) / 3 * 100

    @property
    def rankable(self) -> bool:
        return not self.season.all_censored

    @property
    def status(self) -> str:
        if self.season.all_censored:
            return "Censureret"
        if self.days_seen >= MIN_DAYS:
            return f"Anbefalet ({self.days_seen} dage)"
        return f"For tidlig ({self.days_seen}/{MIN_DAYS} dage)"


# --------------------------------------------------------------------- scoring
def clamp_pct(value: float | None) -> float:
    """Vækst i procent → 0-100. Klippet ved -50 % og +200 %, så ét udsving ikke dominerer."""
    if value is None:
        return 50.0
    return max(0.0, min(100.0, (float(value) + 50) / 250 * 100))


def parse_points(raw) -> list[tuple[str, float]]:
    """Tidsserie fra API'et (dict med datonøgler) eller fixture (liste) → sorteret liste."""
    if isinstance(raw, dict):
        pairs = [(str(k), float(v)) for k, v in raw.items()]
    elif isinstance(raw, list):
        pairs = [(str(i), float(v)) for i, v in enumerate(raw)]
    else:
        return []
    return sorted(pairs, key=lambda kv: kv[0])


def month_label(key: str) -> str | None:
    """«2026-10-06» → «okt». Indeksnøgler (uden dato) giver None."""
    parts = key.split("-")
    if len(parts) < 2:
        return None
    try:
        month = int(parts[1])
    except ValueError:
        return None
    return DA_MONTHS[month - 1] if 1 <= month <= 12 else None


def season_shape(points: list[tuple[str, float]]) -> Season:
    """Lavsæson-niveau, top- og bundmåned — med nuller holdt ude som censur.

    Lavsæson er gennemsnittet af den nederste fjerdedel af de målte punkter. Det er et
    indeks inden for samme graf, ikke et søgetal: det kan sammenlignes med de andre
    søgeord i samme kørsel, ikke på tværs af kørsler.
    """
    season = Season()
    if not points:
        return season
    season.has_series = True
    live = [(key, value) for key, value in points if value > 0]
    season.observed = len(live)
    season.censored = len(points) - len(live)
    if not live:
        return season

    ordered = sorted(value for _, value in live)
    count = max(1, round(len(ordered) * 0.25))
    season.low_level = sum(ordered[:count]) / count
    season.peak_label = month_label(max(live, key=lambda kv: kv[1])[0])
    season.trough_label = month_label(min(live, key=lambda kv: kv[1])[0])
    return season


def demand_score(growth_yoy: float | None, momentum: float | None) -> float:
    """60 % årlig vækst + 40 % seneste måneds retning, hvor ukendt retning vejer neutralt (50)."""
    return round(0.6 * clamp_pct(growth_yoy) + 0.4 * clamp_pct(momentum), 1)


def opportunity_score(demand: float, matches: int) -> float:
    """Efterspørgsel gange den del af kataloget vi IKKE dækker.

    Dækning måles i antal matchende produkter (0, 1, 2, 3+) — ikke i kvalitet, fordi
    hverken Pinterest eller TikTok giver os konkurrent- eller salgstal. Det er en
    bevidst grov model: den skal pege på huller, ikke bevise dem.
    """
    coverage = min(matches, 3) / 3
    return round(demand * (1 - coverage), 1)


# ------------------------------------------------------------ kørselshistorik
def history_path(out_dir: Path) -> Path:
    return Path(out_dir) / HISTORY_NAME


def load_history(out_dir: Path) -> dict[str, list[str]]:
    """Søgeord → de datoer det er målt på. Bruges til tre-dages-gaten."""
    path = history_path(out_dir)
    if not path.exists():
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (json.JSONDecodeError, OSError):
        return {}
    keywords = data.get("keywords")
    if not isinstance(keywords, dict):
        return {}
    return {key: sorted({str(day) for day in days}) for key, days in keywords.items() if isinstance(days, list)}


def days_seen(keyword: str, history: dict[str, list[str]], today: str) -> int:
    days = set(history.get(keyword.strip().lower(), []))
    days.add(today)
    return len(days)


def record_run(out_dir: Path, history: dict[str, list[str]], keywords: list[str],
               today: str) -> dict[str, list[str]]:
    """Skriver dagens udtræk ind i historikken (idempotent pr. dag)."""
    merged = {key: list(days) for key, days in history.items()}
    for keyword in keywords:
        key = keyword.strip().lower()
        if not key:
            continue
        days = set(merged.get(key, []))
        days.add(today)
        merged[key] = sorted(days)
    path = history_path(out_dir)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps({"min_days": MIN_DAYS, "keywords": merged},
                               ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return merged


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


PINTEREST_TOKEN_URL = "https://api.pinterest.com/v5/oauth/token"


def refresh_pinterest_token(refresh_token: str, client_id: str, client_secret: str,
                            token_url: str = PINTEREST_TOKEN_URL) -> dict:
    """Byt et refresh-token til et nyt access-token (Pinterest v5, refresh_token-grant).

    Pinterest roterer refresh-tokenet: svaret indeholder et nyt, og det man sendte er brugt
    op i samme øjeblik. Kalderen skal derfor gemme svaret, før access-tokenet bruges — ellers
    er nøglen til næste fornyelse væk, og kun en fuld re-autorisering bringer adgangen tilbage.
    """
    body = urllib.parse.urlencode({
        "grant_type": "refresh_token",
        "refresh_token": refresh_token,
    }).encode("utf-8")
    basic = base64.b64encode(f"{client_id}:{client_secret}".encode("utf-8")).decode("ascii")
    req = urllib.request.Request(token_url, data=body, method="POST", headers={
        "Authorization": f"Basic {basic}",
        "Content-Type": "application/x-www-form-urlencoded",
        "Accept": "application/json",
        "User-Agent": "steamgears-niche-scanner/1.0",
    })
    with urllib.request.urlopen(req, timeout=25) as res:
        tokens = json.loads(res.read().decode("utf-8"))
    if not isinstance(tokens, dict) or not tokens.get("access_token"):
        raise RuntimeError("Pinterest svarede uden access_token")
    return tokens


def _write_secret(path: Path, data: dict) -> None:
    """Skriv tokens atomisk med mode 600 — en afbrudt kørsel må ikke efterlade halve data."""
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_name(path.name + ".tmp")
    tmp.write_text(json.dumps(data, ensure_ascii=False), encoding="utf-8")
    tmp.chmod(0o600)
    os.replace(tmp, path)


def pinterest_refresher(token_out, env: dict | None = None):
    """Forny-funktion fra miljøet, eller None hvis fornyelse ikke er sat op.

    Kræver PINTEREST_CLIENT_ID, PINTEREST_CLIENT_SECRET og PINTEREST_REFRESH_TOKEN — og et
    sted at gemme det roterede token (`--token-out`). Uden det sidste returneres None med
    vilje: en fornyelse ville bruge det gamle token op og efterlade næste nat uden adgang.
    """
    env = os.environ if env is None else env
    client_id = (env.get("PINTEREST_CLIENT_ID") or "").strip()
    client_secret = (env.get("PINTEREST_CLIENT_SECRET") or "").strip()
    refresh_token = (env.get("PINTEREST_REFRESH_TOKEN") or "").strip()
    if not (client_id and client_secret and refresh_token and token_out):
        return None

    state = {"refresh_token": refresh_token}

    def refresh() -> str:
        tokens = refresh_pinterest_token(state["refresh_token"], client_id, client_secret)
        # Gem først, brug bagefter: det gamle refresh-token er dødt, uanset hvad der sker nu.
        _write_secret(Path(token_out), {
            "access_token": tokens["access_token"],
            "refresh_token": tokens.get("refresh_token") or "",
        })
        if tokens.get("refresh_token"):
            state["refresh_token"] = tokens["refresh_token"]
        return tokens["access_token"]

    return refresh


def _fetch_trends(url: str, token: str) -> dict:
    """GET mod Pinterest Trends med genforsøg på 429/5xx — raten skal ikke dræbe kørslen."""
    last: Exception | None = None
    for attempt in range(1, RETRIES + 1):
        req = urllib.request.Request(url, headers={
            "Authorization": f"Bearer {token}",
            "Accept": "application/json",
            "User-Agent": "steamgears-niche-scanner/1.0",
        })
        try:
            with urllib.request.urlopen(req, timeout=25) as res:
                return json.loads(res.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            last = exc
            if exc.code not in (429, 500, 502, 503, 504) or attempt == RETRIES:
                raise
            time.sleep(2 ** attempt)
        except Exception as exc:  # netværksfejl: ét genforsøg er nok til at afgøre det
            last = exc
            if attempt == RETRIES:
                raise
            time.sleep(2 ** attempt)
    raise last if last else RuntimeError("Pinterest-kald fejlede uden undtagelse")


def pinterest_signals(terms: list[str], region: str, trend_type: str, token: str | None,
                      offline: bool = False, refresh=None) -> tuple[list[Signal], str]:
    """Returnerer (signaler, status). Status er menneskelæsbar og havner i rapporten.

    Svarer Pinterest 401, byttes refresh-tokenet én gang og kaldet gentages — det er den
    eneste måde en natlig kørsel overlever 30-dages-udløbet uden hænder. Kun 401: en 403 er
    manglende adgang, ikke et udløbet token, og en fornyelse ville bare brænde et rotation.
    """
    note = ""
    if offline:
        payload = json.loads((FIXTURES / "pinterest_trends_dk.json").read_text(encoding="utf-8"))
    else:
        query = urllib.parse.urlencode({
            "include_keywords": ",".join(terms),
            "limit": 50,
        })
        url = f"{PINTEREST_API}/trends/keywords/{urllib.parse.quote(region)}/top/{trend_type}?{query}"
        if not token and refresh is not None:
            try:
                token = refresh()
                note = " · token hentet med refresh-token"
            except Exception as exc:
                return [], f"fejl: intet access-token, og fornyelsen fejlede: {exc}"
        if not token:
            return [], "utilgængelig: PINTEREST_ACCESS_TOKEN er ikke sat (kræver Standard-adgang)"
        try:
            payload = _fetch_trends(url, token)
        except urllib.error.HTTPError as exc:
            if exc.code != 401 or refresh is None:
                return [], f"fejl: {exc}"
            try:
                token = refresh()
                note = " · token fornyet efter 401"
            except Exception as refresh_exc:
                return [], (f"fejl: tokenet er udløbet (401), og fornyelsen fejlede: {refresh_exc}"
                            " — kør OAuth-flowet igen og sæt PINTEREST_REFRESH_TOKEN")
            try:
                payload = _fetch_trends(url, token)
            except Exception as exc:  # efter en fornyelse er der ingen vej videre
                return [], f"fejl: {exc} (selv efter fornyelse)"
        except Exception as exc:  # 403/429 efter genforsøg eller netværk: i rapporten, ikke dødeligt
            return [], f"fejl: {exc}"

    items = payload.get("items") or payload.get("data") or []
    signals = []
    for item in items:
        signals.append(Signal(
            keyword=(item.get("keyword") or "").strip(),
            source="pinterest",
            growth_yoy=item.get("pct_growth_yoy"),
            growth_mom=item.get("pct_growth_mom"),
            points=parse_points(item.get("time_series") or []),
        ))
    return [s for s in signals if s.keyword], f"ok: {len(signals)} søgeord ({region}, {trend_type}){note}"


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
            # Eksporten har ingen tidsserie: form og lavsæson kan ikke udledes, og rækken
            # får derfor ingen top-/bundmåned. Det står i rapporten frem for at blive gættet.
            signals.append(Signal(keyword=keyword, source="tiktok", growth_yoy=growth,
                                  volume_hint=views))
    return signals, f"ok: {len(signals)} søgeord (manuel eksport: {path.name})"


# --------------------------------------------------------------------- rapport
def score_rows(signals: list[Signal], catalog: list[dict],
               history: dict[str, list[str]] | None = None,
               today: str | None = None) -> list[Row]:
    rows = []
    for signal in signals:
        momentum = signal.momentum
        demand = demand_score(signal.growth_yoy, momentum)
        matches = match_products(signal.keyword, catalog)
        rows.append(Row(
            keyword=signal.keyword,
            source=signal.source,
            demand=demand,
            growth_yoy=signal.growth_yoy,
            momentum=momentum,
            matches=matches,
            opportunity=opportunity_score(demand, len(matches)),
            season=season_shape(signal.points),
            days_seen=days_seen(signal.keyword, history or {}, today) if today else 1,
        ))
    # Censurerede rækker rangeres ikke — de har ingen målbar efterspørgsel at rangere på.
    return sorted(rows, key=lambda r: (not r.rankable, -r.opportunity, -r.demand, r.keyword))


def fmt(value: float | None, suffix: str = "") -> str:
    if value is None:
        return "–"
    text = f"{value:+.0f}" if suffix == "%" else f"{value:.0f}"
    return f"{text}{suffix}".replace(".", ",")


def render(rows: list[Row], sources: dict[str, str], meta: dict) -> str:
    rankable = [r for r in rows if r.rankable]
    censored = [r for r in rows if not r.rankable]
    gaps = [r for r in rankable if not r.matches]
    covered = [r for r in rankable if r.matches]

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
        "| Søgeord | Kilde | Efterspørgsel | Vækst YoY | Momentum | Lavsæson | Top | Match | Mulighed | Status |",
        "| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |",
    ]
    if not gaps:
        lines.append("| (ingen huller i dette udtræk) | | | | | | | | | |")
    for row in gaps:
        lines.append(
            f"| {row.keyword} | {row.source} | {row.demand:.0f} | {fmt(row.growth_yoy, '%')} | "
            f"{fmt(row.momentum, '%')} | {row.season.low_text} | {row.season.peak_label or '–'} | 0 | "
            f"**{row.opportunity:.0f}** | {row.status} |"
        )

    lines += [
        "",
        "## Dækket i forvejen",
        "",
        "| Søgeord | Kilde | Efterspørgsel | Lavsæson | Match | Mulighed | Status |",
        "| --- | --- | --- | --- | --- | --- | --- |",
    ]
    if not covered:
        lines.append("| (ingen af søgeordene matcher kataloget) | | | | | | |")
    for row in covered:
        names = ", ".join(row.matches[:3]) + (" …" if len(row.matches) > 3 else "")
        lines.append(f"| {row.keyword} | {row.source} | {row.demand:.0f} | {row.season.low_text} | "
                     f"{names} | {row.opportunity:.0f} | {row.status} |")

    if censored:
        lines += [
            "",
            "## Censurerede rækker — ikke rangeret",
            "",
            "Kilden rapporterer nul for hele serien. Det er censur (under tærsklen), ikke nul",
            "efterspørgsel, og rækken indgår derfor hverken i rangeringen eller i gennemsnit.",
            "",
            "| Søgeord | Kilde | Nul-punkter | Status |",
            "| --- | --- | --- | --- |",
        ]
        for row in censored:
            lines.append(f"| {row.keyword} | {row.source} | {row.season.censored} | {row.status} |")

    if meta.get("history"):
        lines += [
            "",
            "## Kørselshistorik",
            "",
            f"Et søgeord skal være målt på mindst **{MIN_DAYS} dage** før rækken kaldes «Anbefalet».",
            "",
            "| Søgeord | Dage | Datoer |",
            "| --- | --- | --- |",
        ]
        for keyword, days in sorted(meta["history"].items()):
            lines.append(f"| {keyword} | {len(days)} | {', '.join(days)} |")

    lines += [
        "",
        "## Sådan læses tallene",
        "",
        "- **Efterspørgsel** = 60 % årlig vækst + 40 % seneste måned, hver klippet til 0-100.",
        "  Pinterest giver *relativ* interesse (0-100 pr. søgeord), ikke absolutte søgetal.",
        "- **Momentum** er kildens eget `mom`-felt, **ikke** udregnet af tidsserien:",
        "  Pinterest min-max-normaliserer hver graf, så et forhold mellem to uger i serien er skævt.",
        "  Mangler feltet (TikTok-eksporten), står rækken neutralt i efterspørgslen.",
        "- **Lavsæson** er gennemsnittet af den nederste fjerdedel af de målte punkter — et indeks,",
        "  ikke et søgetal. Det er lavsæsonen der afgør om en sæsonniche kan bære lager.",
        "- **Top** er den måned serien topper i. TikTok-eksporten har ingen serie og får «–».",
        "- **Nuller** er censur og holdes ude af gennemsnittene; en række med kun nuller rangeres ikke.",
        f"- **Status** kræver mindst {MIN_DAYS} måledage før «Anbefalet»; ellers står rækken som «For tidlig».",
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
        "- **Absolut volumen.** Ingen af kilderne tæller søgninger for Norden, så skala kan ikke aflæses her.",
        "",
        f"_Genereret af `tools/niche_scanner.py`. Rå data: `reports/niche-scan-{meta['date']}.json`._",
    ]
    return "\n".join(lines) + "\n"


def run(terms: list[str], region: str, trend_type: str, offline: bool,
        tiktok_export: str | None, history: dict[str, list[str]] | None = None,
        today: str | None = None, refresh=None) -> tuple[list[Row], dict[str, str], list[dict], int]:
    catalog = load_catalog(offline=offline)
    signals: list[Signal] = []
    sources: dict[str, str] = {}

    pin, pin_status = pinterest_signals(terms, region, trend_type,
                                        os.getenv("PINTEREST_ACCESS_TOKEN"), offline=offline,
                                        refresh=refresh)
    sources["Pinterest Trends (API v5)"] = pin_status
    signals += pin

    tt, tt_status = tiktok_signals(tiktok_export, offline=offline)
    sources["TikTok Creative Center (eksport)"] = tt_status
    signals += tt

    rows = score_rows(signals, catalog, history=history, today=today)
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
    parser.add_argument("--token-out", metavar="FIL",
                        help="fil hvor et fornyet access/refresh-token gemmes (mode 600)")
    parser.add_argument("--out-dir", default=str(REPORTS))
    args = parser.parse_args(argv)

    terms = [t.strip() for t in args.seeds.split(",") if t.strip()]
    if args.seeds_file:
        terms += [line.strip() for line in Path(args.seeds_file).read_text(encoding="utf-8").splitlines()
                  if line.strip() and not line.startswith("#")]
    if not terms:
        parser.error("giv mindst ét søgeord med --seeds eller --seeds-file")

    today = date.today().isoformat()
    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    history = load_history(out_dir)

    refresh = pinterest_refresher(Path(args.token_out) if args.token_out else None)
    rows, sources, catalog, code = run(terms, args.region, args.trend_type, args.offline,
                                       args.tiktok_export, history=history, today=today,
                                       refresh=refresh)
    if not rows:
        # Uden data overskrives dagens rapport ikke, og dagen tæller ikke som måledag: en
        # natlig kørsel der ikke kunne hente noget må ikke flytte tre-dages-gaten.
        print("  [FEJL] ingen kilde gav data — ingen rapport skrevet, gaten er urørt", file=sys.stderr)
        for name, status in sources.items():
            print(f"    {name}: {status}", file=sys.stderr)
        return code or 2
    history = record_run(out_dir, history, [r.keyword for r in rows], today)

    meta = {"date": today, "region": args.region, "trend_type": args.trend_type,
            "catalog_count": len(catalog), "seeds": terms, "history": history}
    md_path = out_dir / f"niche-scan-{today}.md"
    json_path = out_dir / f"niche-scan-{today}.json"

    md_path.write_text(render(rows, sources, meta), encoding="utf-8")
    json_path.write_text(json.dumps({
        "meta": {k: v for k, v in meta.items() if k != "history"},
        "sources": sources,
        "rows": [{"keyword": r.keyword, "source": r.source, "demand": r.demand,
                  "growth_yoy": r.growth_yoy, "momentum": r.momentum,
                  "matches": r.matches, "opportunity": r.opportunity,
                  "low_season": r.season.low_level, "peak_month": r.season.peak_label,
                  "censored_points": r.season.censored, "rankable": r.rankable,
                  "days_seen": r.days_seen, "status": r.status} for r in rows],
    }, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"  kilder: {sources}")
    print(f"  søgeord: {len(rows)} · huller: {sum(1 for r in rows if r.rankable and not r.matches)}"
          f" · censurerede: {sum(1 for r in rows if not r.rankable)}")
    for row in rows[:8]:
        print(f"    {row.opportunity:>5.0f}  {row.source:<9} {row.keyword:<32} "
              f"lav={row.season.low_text:<4} match={len(row.matches)}  {row.status}")
    shown = md_path.relative_to(ROOT) if md_path.is_relative_to(ROOT) else md_path
    print(f"  skrev {shown}, {json_path.name} og {HISTORY_NAME}")
    return code


if __name__ == "__main__":
    sys.exit(main())
