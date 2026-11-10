#!/usr/bin/env python3
"""agent_brief.py — måler butikken og skriver den brief den ugentlige SEO-agent læser.

Hvorfor: `seo_agent.py` planlagde ud fra `docs/seo.md` og et snapshot af de første 20 sider.
Det billede ældes: kataloget gik fra 39 til 20 produkter og valutaen fra USD til DKK, mens
planen blev ved med at tale om det gamle. Denne fil måler det offentlige katalog og skriver
`docs/agent-brief.md`, som SEO-agenten læser som en kilde på linje med `docs/seo.md`.

Kilder (alle offentlige, ingen nøgle):
  /wp-json/wc/store/v1/products   produkter, priser, valuta, kategorier
  /wp-json/wp/v2/posts            antal publicerede indlæg (X-WP-Total) + nyeste dato
  /wp-json/wp/v2/pages            antal publicerede sider (X-WP-Total)

Kan en kilde ikke læses, står den som `utilgængelig` med årsagen — den gættes ikke. Hvad filen
ikke ved (klik, ordrer, konvertering, GSC) står nederst, så agenten ikke tror den har dem: de tal
ligger i pluginets egen `sgap_events` og læses af pluginets agenter gennem abilityen `agent-brief`.

Kør:
    python3 tools/agent_brief.py              # måler live og skriver docs/agent-brief.md
    python3 tools/agent_brief.py --offline    # fixtures, ingen netværk
    python3 tools/agent_brief.py --check      # findes briefen og er den læsbar?
    python3 tools/test_agent_brief.py         # tests
"""

from __future__ import annotations

import argparse
import json
import sys
import urllib.request
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BRIEF = ROOT / "docs" / "agent-brief.md"
FIXTURE = Path(__file__).resolve().parent / "fixtures" / "agent_brief.json"
SITE = "https://steamgears.eu"
UA = "steamgears-agent-brief/1.0"
MARKER = "Agent-brief — steamgears.eu"


def fetch_json(url: str, timeout: int = 25):
    request = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "application/json"})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return json.loads(response.read().decode("utf-8"))


def fetch_total(url: str, timeout: int = 25):
    """X-WP-Total fra et REST-svar — antallet uden at hente alle rækker. None hvis header mangler."""
    request = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "application/json"})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        raw = response.headers.get("X-WP-Total")
        response.read()
    try:
        return int(raw)
    except (TypeError, ValueError):
        return None


def price_of(product: dict) -> float | None:
    """Prisen i butikkens valuta. Store API sender mindste enhed som streng (69900 = 699,00)."""
    prices = product.get("prices") if isinstance(product.get("prices"), dict) else {}
    raw = prices.get("price") or prices.get("regular_price") or ""
    try:
        value = float(raw)
    except (TypeError, ValueError):
        return None
    try:
        unit = int(prices.get("currency_minor_unit", 2) or 0)
    except (TypeError, ValueError):
        unit = 2
    return round(value / (10 ** unit), 2) if unit >= 0 else None


def currency_of(products: list[dict]) -> str:
    for product in products:
        prices = product.get("prices") if isinstance(product.get("prices"), dict) else {}
        code = str(prices.get("currency_code") or "").strip().upper()
        if code:
            return code
    return ""


def build(products, posts_total, pages_total, newest_post) -> dict:
    """Fakta ud af rå svar. Alt hvad der ikke kunne læses havner i `missing` med sin årsag."""
    products = [p for p in (products or []) if isinstance(p, dict)]
    prices = [value for value in (price_of(p) for p in products) if isinstance(value, (int, float))]
    categories = set()
    for product in products:
        for category in product.get("categories") or []:
            if isinstance(category, dict) and category.get("name"):
                categories.add(str(category["name"]))
    facts = {
        "products": len(products),
        "currency": currency_of(products),
        "price_min": min(prices) if prices else None,
        "price_max": max(prices) if prices else None,
        "categories": len(categories),
        "posts": posts_total,
        "pages": pages_total,
        "newest": "",
        "missing": [],
    }
    if isinstance(newest_post, dict) and str(newest_post.get("date") or "").strip():
        facts["newest"] = str(newest_post["date"])[:10]
    if not products:
        facts["missing"].append("produkter (Store API svarede uden rækker)")
    if posts_total is None:
        facts["missing"].append("antal indlæg (X-WP-Total manglede)")
    if pages_total is None:
        facts["missing"].append("antal sider (X-WP-Total manglede)")
    if not facts["newest"]:
        facts["missing"].append("nyeste indlæg (ingen dato i svaret)")
    return facts


def snapshot(offline: bool = False) -> tuple[dict, dict]:
    """Rå målinger fra live-sitet eller fixture. Returnerer (rå, fejl pr. kilde)."""
    if offline:
        return json.loads(FIXTURE.read_text(encoding="utf-8")), {}

    raw: dict = {}
    errors: dict = {}
    try:
        raw["products"] = fetch_json(f"{SITE}/wp-json/wc/store/v1/products?per_page=100")
    except Exception as exc:  # netværk, WAF eller 5xx: kilden meldes, ikke gættes
        raw["products"] = []
        errors["produkter"] = str(exc)
    try:
        raw["posts_total"] = fetch_total(f"{SITE}/wp-json/wp/v2/posts?per_page=1&_fields=id")
    except Exception as exc:
        raw["posts_total"] = None
        errors["indlæg"] = str(exc)
    try:
        raw["pages_total"] = fetch_total(f"{SITE}/wp-json/wp/v2/pages?per_page=1&_fields=id")
    except Exception as exc:
        raw["pages_total"] = None
        errors["sider"] = str(exc)
    try:
        newest = fetch_json(f"{SITE}/wp-json/wp/v2/posts?per_page=1&_fields=date,link")
        raw["newest_post"] = newest[0] if isinstance(newest, list) and newest else None
    except Exception as exc:
        raw["newest_post"] = None
        errors["nyeste indlæg"] = str(exc)
    return raw, errors


def da_number(value: float | None) -> str:
    """Dansk tusindtal: 1495.0 → «1 495», 69.0 → «69»."""
    if value is None:
        return "utilgængelig"
    text = f"{value:,.2f}".rstrip("0").rstrip(".")
    return text.replace(",", "\u00a0")


def render(facts: dict, today: str, site: str = SITE) -> str:
    currency = facts.get("currency") or ""
    price_range = "utilgængelig"
    if isinstance(facts.get("price_min"), (int, float)) and isinstance(facts.get("price_max"), (int, float)):
        low, high = da_number(facts["price_min"]), da_number(facts["price_max"])
        price_range = low if low == high else f"{low}–{high}"
        if currency:
            price_range += f" {currency}"

    def value(key: str, suffix: str = "") -> str:
        item = facts.get(key)
        if item is None or item == "" or item == 0:
            return "utilgængelig" if item is None or item == "" else "0"
        return f"{item}{suffix}"

    lines = [
        f"# {MARKER}",
        "",
        f"_Målt **{today}** af `tools/agent_brief.py` mod {site}. Markdown-kilden er sandheden; "
        "`seo_agent.py` læser filen som en kilde på linje med `docs/seo.md`._",
        "",
        "## Butikken som den er nu",
        "",
        "| Faktum | Målt værdi |",
        "| --- | --- |",
        f"| Produkter i kataloget | {value('products')} |",
        f"| Valuta | {currency or 'utilgængelig'} |",
        f"| Prisinterval | {price_range} |",
        f"| Produktkategorier | {value('categories')} |",
        f"| Publicerede indlæg | {value('posts')} |",
        f"| Publicerede sider | {value('pages')} |",
        f"| Nyeste indlæg | {facts.get('newest') or 'utilgængelig'} |",
        "",
        "## Sådan bruger agenten den",
        "",
        "- **Antal produkter og prisinterval er målte tal.** Skriv planen efter dem i stedet for at gentage "
        "ældre audits' katalogtal, og antag aldrig en anden valuta end den ovenfor.",
        "- **Nævn kun produkter og priser der findes nu.** Et forslag der kræver et produkt der er væk, "
        "eller en pris der ikke står i butikken, skal afvises af agenten selv.",
        "- **Manglende måling siges højt.** Står en række som `utilgængelig`, skal planen sige det og "
        "foreslå en måling — ikke gætte tallet.",
        "",
        "## Hvad filen ikke ved",
        "",
        "- **Klik, ordrer, konvertering og tilskrivning.** De ligger i pluginets `sgap_events` og læses af "
        "pluginets egne agenter gennem abilityen `agent-brief`; denne fil har dem ikke.",
        "- **GSC, GA4 og annoncedata.** Kræver ejerens adgang og står i de daterede målinger i `docs/library/`.",
        "- **Lager, leveringstid og kostpris.** Ingen af dem findes i det offentlige katalog.",
    ]
    if facts.get("missing"):
        lines += ["", "## Kilder der ikke kunne læses", ""]
        lines += [f"- {item}" for item in facts["missing"]]
    return "\n".join(lines) + "\n"


def check(path: Path = BRIEF) -> int:
    """Findes briefen, og er den den fil agenten læser? 0 = ja, 1 = nej."""
    if not path.exists():
        print(f"[FEJL] {path.name} findes ikke — kør tools/agent_brief.py", file=sys.stderr)
        return 1
    text = path.read_text(encoding="utf-8")
    if MARKER not in text or "| Produkter i kataloget |" not in text:
        print(f"[FEJL] {path} er ikke en agent-brief (mangler overskrift eller tabel)", file=sys.stderr)
        return 1
    print(f"[OK] {path.relative_to(ROOT) if path.is_relative_to(ROOT) else path} findes og kan læses")
    return 0


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Mål butikken og skriv docs/agent-brief.md")
    parser.add_argument("--offline", action="store_true", help="brug fixtures, ingen netværk")
    parser.add_argument("--out", default=str(BRIEF), help="sti til briefen")
    parser.add_argument("--check", action="store_true", help="kontrollér den eksisterende brief")
    parser.add_argument("--print", dest="show", action="store_true", help="skriv briefen til stdout")
    args = parser.parse_args(argv)

    out = Path(args.out)
    if args.check:
        return check(out)

    raw, errors = snapshot(offline=args.offline)
    facts = build(raw.get("products"), raw.get("posts_total"), raw.get("pages_total"), raw.get("newest_post"))
    for source, reason in errors.items():
        facts["missing"].append(f"{source} (kilden svarede ikke: {reason})")
    text = render(facts, today=date.today().isoformat())
    if args.show:
        print(text, end="")
        return 0
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(text, encoding="utf-8")
    print(f"[OK] {out.relative_to(ROOT) if out.is_relative_to(ROOT) else out}: "
          f"{facts['products']} produkter, {facts['currency'] or 'ukendt valuta'}, "
          f"{len(facts['missing'])} manglende måling(er)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
