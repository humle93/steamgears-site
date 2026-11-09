#!/usr/bin/env python3
"""Værn for al copy i repoet: finder hver copyfil, tjekker den mod SEO-skabelonen og den LEVENDE butik.

Dækningen følger fil-sættet, ikke en håndholdt liste: en fil er copy, hvis den bærer husets copy-markører
(`**H1:**` eller Rank Math-tabellen), og den bliver målt med det samme — uanset hvad den heder. En fil med
`/go/<id>/`-links eller 20+ prismålinger, som hverken er copy eller står i registeret `IKKE_COPY` med en grund,
fejler kørslen: så kan en ny copyfil ikke ligge uden for værnene, og en gammel prisliste kan ikke gemme sig som
"bare dokumentation".

Reglerne der kontrolleres, står i docs/seo.md §1 og §3:

- Rank Math title <= 62 tegn, H1 findes præcis én gang, meta 140-155 tegn
- mindst 800 ord brødtekst
- mindst ét produktlink gennem /go/<id>/ og mindst to egne guides plus hub
- mindst fire FAQ-spørgsmål
- ingen pladsholdere (TODO, TBD, [EJER, XXX, lorem)
- hvert /go/<id>/ findes i den levende butik (Store API), og et id der ikke kan måles er en advarsel
- hvert kr.-beløb findes i den målte prisliste (docs/library/priser-live-*.md) eller i et dokumenteret total
- et $-beløb i en fil hvis butik prissætter i DKK er en fejl — valutaen er en påstand om hvad kunden ser
- linkteksten på et /go/<id>/ deler mindst to ord med produktets navn i butikken
- klyngen: hver guide linker hubben OG hver af de andre guides (ingen envejs-links)

Brug:
    python3 tools/guide_check.py                 # lokal kontrol + hvert /go/-id måles i butikken
    python3 tools/guide_check.py --offline       # uden netværk: kun de lokale regler
    python3 tools/guide_check.py --selftest      # beviser at værnene fanger fejl, også de to nye

Netværkskaldet er standard, fordi den fejl der kostede en runde 9/11 2026 var usynlig uden det: copyen var
konsistent med sin egen gamle prisliste, mens 12 af 18 produkt-id'er svarede 404 i butikken. `--offline`
findes til miljøer uden netværk, ikke som en nem vej uden om.
"""

import glob
import json
import os
import re
import sys
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LIB = os.path.join(ROOT, "docs", "library")
STORE_API = "https://steamgears.eu/wp-json/wc/store/v1/products"
STORE_CURRENCY = "DKK"

TITLE_MAX = 62
META_MIN, META_MAX = 140, 155
WORDS_MIN = 800
FAQ_MIN = 4
GUIDES_MIN = 2
HUB = "steampunk-costumes"
NAME_WORDS_MIN = 2   # fælles ord mellem linktekst og produktets navn

AMOUNT = r"\d{1,3}(?:\.\d{3})*,\d{2}"          # 1.234,56
AMOUNT_KR = re.compile(r"(" + AMOUNT + r")\s*kr\.")
AMOUNT_USD = re.compile(r"\$\s?\d[\d.]*")


# --------------------------------------------------------------------------- historisk grundlag
#
# Disse tre hjælpere beskriver kataloget og priserne som de var 1.–3/11 2026 (39 produkter i USD). De findes
# KUN fordi `tools/link_plan_check.py` fører regnskab med den historiske link-plan fra 3/11, hvor de id'er og
# priser hører til. Ny copy må ikke bruges dem: `catalogue()` (den målte DKK-prisliste) er facit for alt der
# skal publiceres, og det er den `check_guide` og `check_live` læser.

ID_DOC = os.path.join(LIB, "om-maerkning-dark-academia-2026-11-01.md")
PRICE_DOC = os.path.join(LIB, "valuta-og-priser-2026-11-02.md")
MEASURED_DOC = os.path.join(LIB, "priser-fra-store-api-2026-11-03.md")


def mapping_titles() -> dict:
    """{id: [kolonner fra mappingen]} — det historiske ID-katalog, ikke butikken."""
    titles = {}
    with open(ID_DOC, encoding="utf-8") as fh:
        for line in fh:
            if not line.lstrip().startswith("|"):
                continue
            cells = [c.strip() for c in line.strip().strip("|").split("|")]
            if len(cells) >= 3 and re.fullmatch(r"\d{3,4}", cells[0]):
                titles[cells[0]] = cells[1:4]
    return titles


def known_ids() -> set:
    """De id'er der stod i mappingen 1/11 — flere af dem er slettet i butikken."""
    return set(mapping_titles())


def known_prices() -> set:
    """De USD-priser der blev målt 2.–3/11, som tekst uden valutategn."""
    prices = set()
    for path in (PRICE_DOC, MEASURED_DOC):
        if not os.path.exists(path):
            continue
        with open(path, encoding="utf-8") as fh:
            for line in fh:
                m = re.search(r"\|\s*\$(\d+\.\d{2})\s*\|", line)
                if m:
                    prices.add(m.group(1))
    return prices


# --------------------------------------------------------------------------- dækning
#
# Copy findes på indhold, ikke på filnavn: husets skabelon kræver `**H1:**` og Rank Math-tabellen, og en fil der
# har dem, er copy der skal kunne publiceres. Alt andet med /go/-links eller mange priser skal stå herunder med
# en grund — ellers ved værnene ikke om filen er gældende copy eller et historisk dokument, og så fejler de.

# Markørerne skal stå i starten af en linje: en dokumentation der *nævner* `**H1:**` indvendigt i en sætning
# er ikke copy, men en fil der har markøren som sin egen linje er det.
COPY_MARKERS = (r"(?m)^\*\*H1:\*\*", r"(?m)^\| \*\*Rank Math title\*\* \|")
MONEY_HEAVY = 20   # antal prismålinger der gør en fil til noget værnene skal kende

IKKE_COPY = {
    "docs/library/guide-links-aeldre-2026-11-03.md": "link-plan fra 3/11; planens id'er er siden slettet",
    "docs/library/guide-links-live-2026-11-05.md": "måling af planen 5/11, ikke en status nu",
    "docs/library/priser-fra-store-api-2026-11-03.md": "prismåling 3/11 i USD, erstattet af priser-live",
    "docs/library/valuta-og-priser-2026-11-02.md": "den gamle USD-pristabel, historisk",
    "docs/library/w3tc-og-valuta-2026-11-08.md": "afregning af valutaen 8/11, citerer begge valutaer",
    "docs/library/guide-udkast-27-10-promo-kit.md": "annonce-kit fra 27/10 med datidens USD-priser",
    "docs/library/guide-udkast-27-10-research.md": "research fra 27/10, citerer datidens priser",
    "docs/library/deploy-playbook-2026-11-08.md": "playbook med eksempel-links, ikke copy",
    "docs/library/go-ruten-produktlinks-2026-11-03.md": "diagnose af /go/-ruten 3/11",
    "docs/library/s15-produktlink-daekning-2026-11-08.md": "måling 8/11 med de daværende id'er",
    "docs/library/tre-saeson-guides-2026-11-08.md": "rapport om de tre publicerede guides 8/11",
    "docs/library/vedligehold-ugentlig-log.md": "driftslog",
    "docs/library/vedligehold-daglig-log.md": "driftslog med dagens priser på måletidspunktet",
    "docs/library/pinterest-analytics-2026-11-07.md": "måling af Pinterest 7/11",
    "docs/library/seo-sporet-2026-11-08.md": "spor/procesdokument",
    "docs/library/go-links-og-guide-copy-2026-11-09.md": "målingen 9/11; citerer den fil der blev rettet",
    "docs/library/guide-vaern-daekning-2026-11-09.md": "målingen af værnsdækningen; citerer filer og beløb",
    "docs/publiceringskoersel-2026-11-09.md": "publiceringskørslen; citerer copyen ordret (kilden er copyfilerne)",
    "docs/library/go-links-verifikation-2026-11-07.md": "verifikation 7/11",
    "docs/library/seo-dashboard-fakta-26-10.md": "faktasamling fra 26/10 med datidens priser",
    "docs/library/seo-regler-geo-optimering-log.md": "log",
    "docs/library/README.md": "bibliotekets indeks",
    "docs/roadmap.md": "roadmap, citerer historik",
    "docs/samtalepunkter.md": "hukommelse om hvorfor; citerer historik",
    "docs/competitors.md": "konkurrent-research",
}


def documents() -> list:
    """Alle markdown-filer under docs/ — undtagen arkiv, backup og skabeloner."""
    out = []
    for base, dirs, files in os.walk(os.path.join(ROOT, "docs")):
        dirs[:] = sorted(d for d in dirs if d not in {"arkiv", "_deps", "node_modules"} and not d.startswith("."))
        for name in sorted(files):
            if name.endswith(".md"):
                out.append(os.path.join(base, name))
    return out


def is_copy(text: str) -> bool:
    """Copy = husets skabelon. En fil uden markørerne er ikke copy, og det er en påstand værnene kontrollerer."""
    return any(re.search(marker, text) for marker in COPY_MARKERS)


def copy_files() -> list:
    """De filer værnene måler som copy. Rækkefølgen er stabil så udskriften kan sammenlignes."""
    return [path for path in documents() if is_copy(open(path, encoding="utf-8").read())]


def unclassified_file(rel: str, text: str) -> str:
    """Grunden til at en fil er uklassificeret, eller '' hvis den er i orden. Ren funktion, så den kan testes."""
    if is_copy(text) or rel in IKKE_COPY:
        return ""
    links = len(re.findall(r"/go/\d+/", text))
    money = len(re.findall(r"\$\s?\d|" + AMOUNT + r"\s*kr\.", text))
    if not links and money < MONEY_HEAVY:
        return ""
    return (
        f"{rel}: {links} /go/-links og {money} prismålinger, men filen er hverken copy "
        "(mangler `**H1:**`/Rank Math-tabellen) eller registreret i IKKE_COPY — klassificér den"
    )


def unclassified() -> list:
    """Filer med /go/-links eller mange priser, som hverken er copy eller registreret i IKKE_COPY."""
    problems = []
    for path in documents():
        rel = os.path.relpath(path, ROOT).replace(os.sep, "/")
        problem = unclassified_file(rel, open(path, encoding="utf-8").read())
        if problem:
            problems.append(problem)
    return problems


def price_document() -> str:
    """Den nyeste målte prisliste. Der må kun være én, ellers ved værnene ikke hvad der gælder."""
    paths = sorted(glob.glob(os.path.join(LIB, "priser-live-*.md")))
    if len(paths) != 1:
        raise SystemExit(
            f"FAIL  forventede præcis én docs/library/priser-live-*.md, fandt {len(paths)} — "
            "prislisterne er grundlaget for hver prismåling"
        )
    return paths[0]


def parse_cents(text: str) -> int:
    """'1.234,56' -> 123456 øre. Dansk format, ikke engelsk."""
    return int(text.replace(".", "").replace(",", ""))


def catalogue() -> dict:
    """{id: {"name": str, "cents": int}} fra den målte prisliste."""
    out = {}
    with open(price_document(), encoding="utf-8") as fh:
        for line in fh:
            if not line.lstrip().startswith("|"):
                continue
            cells = [c.strip() for c in line.strip().strip("|").split("|")]
            if len(cells) < 3 or not re.fullmatch(r"\d{3,4}", cells[0]):
                continue
            amount = re.fullmatch(AMOUNT, cells[-1])
            if not amount:
                continue
            out[cells[0]] = {"name": cells[1], "cents": parse_cents(cells[-1])}
    return out


def name_words(text: str) -> set:
    return {w.lower() for w in re.findall(r"[A-Za-zÆØÅæøå]{4,}", text)}


def check_product_names(block: str, known: dict) -> list:
    """Et /go/-link skal hedde nogenlunde det samme som produktet med det id.

    Fanger den fejl der er lettest at lave i hånden: at kopiere et link og glemme at rette id'et,
    så guiden kalder produktet ét navn og linket peger på et andet.
    """
    problems = []
    for anchor, pid in re.findall(r"\[([^\]]+)\]\(https://steamgears\.eu/go/(\d+)/\)", block):
        product = known.get(pid)
        if not product:
            continue  # ukendt id fanges af id-værnet
        anchor_words = name_words(anchor)
        shared = len(anchor_words & name_words(product["name"]))
        if shared < min(NAME_WORDS_MIN, len(anchor_words)):
            problems.append(
                f"linket til /go/{pid}/ hedder {anchor!r}, men butikken kalder produktet {product['name']!r} — forkert id?"
            )
    return problems


def check_cluster(block: str, slugs: set) -> list:
    """Klyngen: hub + alle søsterguides skal være linket fra hver guide."""
    problems = []
    body = copy_section(block)
    own = field(block, "Slug")
    linked = set(re.findall(r"https://steamgears\.eu/([a-z0-9-]+)/", body))
    linked.discard("go")
    if HUB not in linked:
        problems.append(f"hub-link mangler (/{HUB}/)")
    missing = sorted(slugs - {own} - linked)
    if missing:
        problems.append("søsterguides ikke linket: " + ", ".join("/" + s + "/" for s in missing))
    return problems


def blocks(text: str) -> list:
    """Deler dokumentet i guides på '## Guide …' og returnerer (navn, blok).

    En copyfil der er én side (hub, outfitside) har ingen guide-blokke; så er hele dokumentet blokken, og
    den bliver målt med de samme regler. Det er dét der gør hub-copyen målbar — før lå den uden for værnene,
    fordi den hverken heder `guides-*` eller har `## Guide`-blokke.
    """
    parts = re.split(r"(?m)^## (Guide \d+[^\n]*)$", text)
    out = []
    for i in range(1, len(parts), 2):
        out.append((parts[i].strip(), parts[i + 1]))
    if not out:
        m = re.search(r"(?m)^#\s+(.+)$", text)
        out.append(((m.group(1).strip() if m else "enkeltside"), text))
    return out


def field(block: str, label: str) -> str:
    m = re.search(r"\*\*" + re.escape(label) + r"\*\*\s*\|\s*`([^`]+)`", block)
    return m.group(1).strip() if m else ""


NOTES_HEADING = r"(?=^#{2,3} (?:Publishing notes|Hvad der stadig|Verifikation|Sådan publiceres)|\Z)"


def copy_section(block: str) -> str:
    """Brødteksten: fra '### Copy' til '### Publishing notes' — de øvrige '### ' er H2'er i copy.

    En enkeltside-copy har ikke `### Copy`, men `**H1:**`; der løber brødteksten fra H1 til noterne.
    """
    m = re.search(r"(?ms)^### Copy\s*$\n(.*?)" + NOTES_HEADING, block)
    if m:
        return m.group(1).strip()
    m = re.search(r"(?ms)^\*\*H1:\*\*.*?" + NOTES_HEADING, block)
    return m.group(0).strip() if m else ""


def totals(block: str, known: dict) -> tuple:
    """Læser '| **Totals** | 3.370,00 = 165,00+335,00 |' og regner efter i øre.

    Returnerer (beløb der er dokumenteret, fejl). Et total må bygges af pricelistens beløb og af
    tidligere dokumenterede totaler, så en tier kan bygge på den foregående.
    """
    accepted, problems = set(), []
    line = re.search(r"(?m)^\|\s*\*\*Totals\*\*\s*\|([^|]+)\|\s*$", block)
    if not line:
        return accepted, ["ingen **Totals**-linje med regnestykket"]
    prices = {p["cents"] for p in known.values()}
    for entry in [e.strip() for e in line.group(1).split(";") if e.strip()]:
        m = re.match(r"^(" + AMOUNT + r")\s*=\s*(.+)$", entry)
        if not m:
            problems.append(f"kan ikke læse totalen {entry!r} (skriv '3.370,00 = 165,00+335,00')")
            continue
        stated, parts = m.group(1), [p.strip() for p in m.group(2).split("+") if p.strip()]
        cents = 0
        for part in parts:
            if not re.fullmatch(AMOUNT, part):
                problems.append(f"totalen {stated} bruger {part!r}, som ikke er et kronebeløb")
                continue
            value = parse_cents(part)
            if value in prices or value in accepted:
                cents += value
            else:
                problems.append(f"totalen {stated} bruger {part}, som hverken er i prislisten eller en tidligere total")
        if cents != parse_cents(stated):
            problems.append(f"totalen {stated} stemmer ikke: delene giver {cents / 100:.2f}".replace(".", ","))
        accepted.add(parse_cents(stated))
    return accepted, problems


def check_guide(name: str, block: str, known: dict, slugs: set | None = None, page: bool = False) -> list:
    """Returnerer listen af fejl. Tom liste = guiden er i orden (bortset fra netværkskaldet)."""
    problems = []
    title = field(block, "Rank Math title")
    meta = field(block, "Meta description")
    slug = field(block, "Slug")
    body = copy_section(block)

    if not slug and not page:
        problems.append("slug mangler")
    if not title:
        problems.append("Rank Math title mangler")
    elif len(title) > TITLE_MAX:
        problems.append(f"title er {len(title)} tegn (max {TITLE_MAX}): {title!r}")
    if not meta:
        problems.append("meta description mangler")
    elif not (META_MIN <= len(meta) <= META_MAX):
        problems.append(f"meta er {len(meta)} tegn (skal være {META_MIN}-{META_MAX})")
    if not body:
        problems.append("ingen Copy-sektion")
        return problems

    if body.count("**H1:**") != 1:
        problems.append(f"H1 står {body.count('**H1:**')} gange (skal være 1)")
    if re.search(r"(?m)^#\s+\S", body):
        problems.append("brødteksten indeholder en H1-markdown-overskrift")

    words = len(re.findall(r"[A-Za-zÆØÅæøå0-9'’-]+", body))
    if words < WORDS_MIN:
        problems.append(f"kun {words} ord (min {WORDS_MIN})")

    faq = len(re.findall(r"(?m)^\*\*[^*]+\?\*\*$", body))
    if faq < FAQ_MIN:
        problems.append(f"{faq} FAQ-spørgsmål (min {FAQ_MIN})")

    go_ids = re.findall(r"/go/(\d+)/", body)
    if not go_ids:
        problems.append("intet /go/<id>/ produktlink")
    unknown = sorted({i for i in go_ids if i not in known})
    if unknown:
        problems.append(f"/go/-id uden produkt i den målte prisliste: {', '.join(unknown)}")
    problems.extend(check_product_names(body, known))

    # Valutaen: en fil der blander $ og kr. beskriver to butikker, og kun den ene findes.
    usd = AMOUNT_USD.findall(body)
    if STORE_CURRENCY == "DKK" and usd:
        problems.append(
            f"{len(usd)} $-beløb i en butik der prissætter i DKK (fx {usd[0]}) — hvert beløb skal være i kroner"
        )

    guides = set(re.findall(r"https://steamgears\.eu/([a-z0-9-]+)/", body))
    guides.discard("go")
    if len(guides) < GUIDES_MIN:
        problems.append(f"{len(guides)} egne guides linket (min {GUIDES_MIN})")
    if not page and HUB not in guides and "start-here" not in guides:
        problems.append(f"hub mangler ({HUB} eller start-here)")
    if slugs is not None and not page:
        problems.extend(check_cluster(block, slugs))

    derived, total_problems = totals(block, known)
    problems.extend(total_problems)
    allowed = {p["cents"] for p in known.values()} | derived
    bad = sorted({a for a in AMOUNT_KR.findall(body) if parse_cents(a) not in allowed})
    if bad:
        problems.append(
            "beløb uden hold i prislisten eller et dokumenteret total: " + ", ".join(f"{b} kr." for b in bad)
        )

    placeholders = re.findall(r"(TODO|TBD|FIXME|\[EJER|XXX|lorem ipsum)", body, re.IGNORECASE)
    if placeholders:
        problems.append(f"pladsholder i teksten: {', '.join(sorted(set(placeholders)))}")

    return problems


def fetch_product(pid: str) -> dict:
    """Slår ét produkt op i den levende butik. Returnerer {"ok": bool, ...} og kaster ikke.

    404 med `woocommerce_rest_product_invalid_id` betyder at produktet er slettet. Alt andet
    (netværk, WAF, 5xx) meldes som `ukendt`, så en målefejl ikke bliver til en påstand om at linket virker.
    """
    req = urllib.request.Request(f"{STORE_API}/{pid}", headers={"User-Agent": "curl/8"})
    try:
        with urllib.request.urlopen(req, timeout=25) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        prices = data.get("prices") or {}
        minor = int(prices.get("currency_minor_unit", 2))
        return {
            "ok": True,
            "name": data.get("name", ""),
            "currency": prices.get("currency_code", ""),
            "cents": int(prices.get("price", "0")) * 100 // (10 ** minor),
        }
    except urllib.error.HTTPError as exc:
        if exc.code == 404:
            return {"ok": False, "status": 404}
        return {"ok": None, "status": exc.code}
    except Exception as exc:  # netværk, timeout, ugyldig JSON
        return {"ok": None, "status": type(exc).__name__}


def check_live(ids: dict, fetch) -> list:
    """Sammenligner copyens /go/-id'er med butikken. Returnerer (fejl, advarsler, målte)."""
    problems, warnings, measured = [], [], {}
    currencies = set()
    for pid in sorted(ids):
        info = fetch(pid)
        measured[pid] = info
        where = ", ".join(sorted(set(ids[pid])))
        if info.get("ok") is False:
            problems.append(f"/go/{pid}/ findes ikke i butikken (404) — brugt i {where}")
        elif info.get("ok") is None:
            warnings.append(f"/go/{pid}/ kunne ikke måles ({info.get('status')}) — ikke godkendt som virkende")
        else:
            currencies.add(info.get("currency", ""))
    foreign = {c for c in currencies if c and c != STORE_CURRENCY}
    if foreign:
        problems.append(
            f"butikken svarer i {', '.join(sorted(foreign))}, men copyen regner i {STORE_CURRENCY} — "
            "hvert beløb og hvert total skal være i butikkens valuta"
        )
    return problems, warnings, measured


def run(paths: list, offline: bool = False) -> int:
    failures = 0
    total = 0
    parsed = []
    known = catalogue()
    if len(known) < 5:
        print(f"FAIL  prislisten {os.path.basename(price_document())} gav kun {len(known)} produkter")
        return 1

    for path in paths:
        with open(path, encoding="utf-8") as fh:
            text = fh.read()
        guides = blocks(text)
        label = os.path.relpath(path, ROOT)
        print(f"== {label} ({len(guides)} blok{'ke' if len(guides) != 1 else ''})")
        single = not re.search(r"(?m)^## Guide \d", text)
        for name, block in guides:
            parsed.append((path, name, block, single))

    slugs = {field(block, "Slug") for _, _, block, _ in parsed} - {""}
    used: dict = {}
    for path, name, block, single in parsed:
        total += 1
        problems = check_guide(name, block, known, slugs, page=single)
        label = f"{os.path.basename(path)} · {name.split('—')[0].strip()}"
        if problems:
            failures += 1
            print(f"FAIL {label}")
            for problem in problems:
                print(f"       - {problem}")
        else:
            words = len(re.findall(r"[A-Za-zÆØÅæøå0-9'’-]+", copy_section(block)))
            print(f"PASS {label} ({words} ord)")
        for pid in re.findall(r"/go/(\d+)/", copy_section(block)):
            used.setdefault(pid, []).append(name.split("—")[0].strip())

    uncovered = unclassified()
    for problem in uncovered:
        print(f"FAIL  ukendt fil: {problem}")
    failures += len(uncovered)

    if offline:
        print("\n[--offline] butikken blev ikke spurgt; kør uden flaget før en publicering")
    else:
        print(f"\n[måling] {len(used)} unikke /go/-id'er slås op i butikken")
        problems, warnings, measured = check_live(used, fetch_product)
        for pid in sorted(measured):
            info = measured[pid]
            if info.get("ok"):
                print(f"  OK    /go/{pid}/ → {info['name']} ({info['currency']} {info['cents'] / 100:.2f})")
            elif info.get("ok") is False:
                print(f"  DØD   /go/{pid}/ → 404")
            else:
                print(f"  UKENDT /go/{pid}/ → {info.get('status')}")
        for problem in problems:
            print(f"FAIL  {problem}")
        for warning in warnings:
            print(f"ADVARSEL {warning}")
        failures += len(problems)

    print(f"\n{total - (failures if total >= failures else 0)} af {total} copy-blokke bestod"
          f"{'' if not failures else f' — {failures} fund'}")
    return 1 if failures else 0


def selftest() -> int:
    """Beviser at værnene fanger fejl, før de bruges på rigtige dokumenter."""
    known = {
        "477": {"name": "Copper Pilot Goggles — Vintage Windproof", "cents": 16500},
        "413": {"name": "Airship Storm — Vintage Engraving Hoodie", "cents": 33500},
        "965": {"name": "Clockwork Rose — Herbarium Art Print", "cents": 39500},
    }
    good = """## Guide 9 — Test
| | |
| --- | --- |
| **Slug** | `test-guide` |
| **Rank Math title** | `Men's Steampunk Goggles: Three Pieces, 560 kr.` |
| **Meta description** | `Ten steampunk pieces that read as one outfit: goggles, a hoodie and a print, priced in DKK from our own shop at 560,00 kr. for the whole set.` |
| **Totals** | 560,00 = 165,00+395,00 |

### Copy

**H1:** En titel

"""
    body = "Ord og tal [Copper Pilot Goggles — Vintage Windproof](https://steamgears.eu/go/477/) og 165,00 kr. og 395,00 kr. " * 130
    good += body + """
**Kan man bruge goggles?**
Ja.

**Hvorfor en hat?**
Fordi.

**Hvad koster den?**
165,00 kr.

**Kan jeg starte småt?**
Ja.

Se [steampunk goggles](https://steamgears.eu/steampunk-goggles/) og [hub](https://steamgears.eu/steampunk-costumes/).

Hele sættet koster 560,00 kr.

### Publishing notes

1. Intet.
"""
    cases = [
        ("gyldig guide består", good, 0),
        ("meta for kort fanges", good.replace("560,00 kr. for the whole set.", "Kort."), 1),
        ("ukendt /go/-id fanges", good.replace("/go/477/", "/go/9999/"), 1),
        ("opdigtet pris fanges", good.replace("165,00 kr.", "199,00 kr."), 1),
        ("pladsholder fanges", good.replace("Ord og tal", "TODO Ord og tal"), 1),
        ("for få ord fanges", good.replace(body, "Kort tekst. " * 10), 1),
        ("forkert total fanges", good.replace("560,00 = 165,00", "660,00 = 165,00"), 1),
        ("dollar i en DKK-fil fanges", good.replace("165,00 kr.", "$24.95"), 1),
        ("forkert produktnavn på /go/-link fanges",
         good.replace("[Copper Pilot Goggles — Vintage Windproof](https://steamgears.eu/go/477/)",
                      "[Gothic Corset](https://steamgears.eu/go/477/)"), 1),
        ("manglende hub-link fanges", good.replace(" og [hub](https://steamgears.eu/steampunk-costumes/)", ""), 1),
    ]
    failed = 0
    for label, text, expect in cases:
        name, block = blocks(text)[0]
        problems = check_guide(name, block, known, {"test-guide"})
        got = 1 if problems else 0
        ok = got == expect
        failed += 0 if ok else 1
        print(f"{'PASS' if ok else 'FAIL'} {label}" + ("" if ok else f" (forventede {expect}, fik {got}: {problems})"))

    # Klyngen: en søsterguide der ikke er linket skal fanges, og et link den anden vej skal gøre det godt igen.
    name, block = blocks(good)[0]
    chain = check_cluster(block, {"test-guide", "anden-guide"})
    linked = good.replace(
        "[hub](https://steamgears.eu/steampunk-costumes/)",
        "[anden](https://steamgears.eu/anden-guide/) og [hub](https://steamgears.eu/steampunk-costumes/)",
    )
    closed = check_cluster(blocks(linked)[0][1], {"test-guide", "anden-guide"})
    for label, found, expect in (("envejs klynge fanges", chain, 1), ("lukket klynge består", closed, 0)):
        got = 1 if found else 0
        ok = got == expect
        failed += 0 if ok else 1
        print(f"{'PASS' if ok else 'FAIL'} {label}" + ("" if ok else f" (forventede {expect}, fik {got}: {found})"))

    # --live-værnet: et slettet produkt skal fanges, og et levende må ikke melde valuta-skred forkert.
    dead = check_live({"475": ["Guide 1"]}, lambda pid: {"ok": False, "status": 404})
    alive_dkk = check_live({"413": ["Guide 1"]}, lambda pid: {"ok": True, "name": "Hoodie", "currency": "DKK", "cents": 33500})
    alive_usd = check_live({"413": ["Guide 1"]}, lambda pid: {"ok": True, "name": "Hoodie", "currency": "USD", "cents": 33500})
    unknown = check_live({"413": ["Guide 1"]}, lambda pid: {"ok": None, "status": 503})
    export = [
        ("slettet produkt fanges", bool(dead[0]), 1),
        ("valuta-skred fanges", any("USD" in p for p in alive_usd[0]), 1),
        ("samme valuta giver ingen fejl", bool(alive_dkk[0]), 0),
        ("umålbar butik bliver advarsel, ikke bestået", bool(unknown[1]) and not unknown[0], 1),
    ]
    for label, found, expect in export:
        got = 1 if found else 0
        ok = got == expect
        failed += 0 if ok else 1
        print(f"{'PASS' if ok else 'FAIL'} {label}" + ("" if ok else f" (forventede {expect}, fik {got})"))

    # Dækningen: en ny copyfil kan ikke ligge uden for værnene, og en gammel prisliste kan ikke gemme sig.
    page_src = good.replace("| **Slug** | `test-guide` |\n", "").replace(
        " og [hub](https://steamgears.eu/steampunk-costumes/)",
        " og [en anden guide](https://steamgears.eu/steampunk-goggles-guide/)")
    page_block = blocks(page_src)[0]
    raw_page = "# En rå side uden husets skabelon\n\nKoster 165,00 kr. og 335,00 kr.\n"
    coverage = [
        ("enkeltside uden slug og hub består", bool(check_guide(*page_block, known, None, page=True)), 0),
        ("enkeltside med dollar fanges",
         bool(check_guide(page_block[0], page_block[1].replace("165,00 kr.", "$24.95"),
                          known, None, page=True)), 1),
        ("husets skabelon registreres som copy", is_copy(good), 1),
        ("rå side-copy uden skabelon er ikke copy", is_copy(raw_page), 0),
        ("uklassificeret fil med /go/-link fanges",
         bool(unclassified_file("docs/library/ny-side.md", "Se /go/477/ og 165,00 kr.\n")), 1),
        ("uklassificeret fil med mange priser fanges",
         bool(unclassified_file("docs/library/gammel-liste.md", "165,00 kr.\n" * 25)), 1),
        ("få priser uden links kræver ingen klassifikation",
         bool(unclassified_file("docs/library/lille-note.md", "165,00 kr.\n" * 3)), 0),
        ("registreret dokument er ikke uklassificeret",
         bool(unclassified_file("docs/roadmap.md", "Se /go/477/ og 165,00 kr.\n")), 0),
    ]
    for label, found, expect in coverage:
        got = 1 if found else 0
        ok = got == expect
        failed += 0 if ok else 1
        print(f"{'PASS' if ok else 'FAIL'} {label}" + ("" if ok else f" (forventede {expect}, fik {got})"))

    total_fixtures = len(cases) + 2 + len(export) + len(coverage)
    print(f"\n{total_fixtures - failed} af {total_fixtures} selftest-fixtures bestod")
    return 1 if failed else 0


def coverage_report(paths: list) -> int:
    """Skriver hvad der er copy, og hvilke dokumenter der med vilje ikke er — så dækningen kan læses."""
    print("COPY (måles mod butikken og prislisten):")
    for path in paths:
        print(f"  {os.path.relpath(path, ROOT)}")
    print(f"\nIKKE COPY, registreret med grund ({len(IKKE_COPY)}):")
    for rel in sorted(IKKE_COPY):
        print(f"  {rel} — {IKKE_COPY[rel]}")
    problems = unclassified()
    print("\nUKLASSIFICERET:")
    if problems:
        for problem in problems:
            print(f"  {problem}")
    else:
        print("  ingen — hver fil med /go/-links eller mange priser er klassificeret")
    return 1 if problems else 0


def main() -> int:
    if "--selftest" in sys.argv:
        return selftest()
    paths = copy_files()
    if not paths:
        print("FAIL ingen copyfiler fundet — forventede mindst én fil med `**H1:**`/Rank Math-tabellen")
        return 1
    if "--coverage" in sys.argv:
        return coverage_report(paths)
    return run(paths, offline="--offline" in sys.argv)


if __name__ == "__main__":
    sys.exit(main())
