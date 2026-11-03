#!/usr/bin/env python3
"""Værn for guide-copy: tjekker docs/library/guides-*.md mod SEO-skabelonen.

Reglerne der kontrolleres, står i docs/seo.md §1 og §3:

- Rank Math title <= 62 tegn, H1 findes præcis én gang, meta 140-155 tegn
- mindst 800 ord brødtekst
- mindst ét produktlink gennem /go/<id>/ og mindst to egne guides plus hub
- mindst fire FAQ-spørgsmål
- ingen pladsholdere (TODO, TBD, [EJER, XXX, lorem)
- hvert /go/<id>/ findes i produkt-ID-mappingen, og hvert $beløb findes i pristabellen
- linkteksten på et /go/<id>/ deler mindst to ord med mappingens titel for samme id
- klyngen: hver guide linker hubben OG hver af de andre guides (ingen envejs-links)

Brug:
    python3 tools/guide_check.py                 # tjekker alle docs/library/guides-*.md
    python3 tools/guide_check.py --selftest      # beviser at værnene fanger fejl
"""

import glob
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LIB = os.path.join(ROOT, "docs", "library")
ID_DOC = os.path.join(LIB, "om-maerkning-dark-academia-2026-11-01.md")
PRICE_DOC = os.path.join(LIB, "valuta-og-priser-2026-11-02.md")

TITLE_MAX = 62
META_MIN, META_MAX = 140, 155
WORDS_MIN = 800
FAQ_MIN = 4
GUIDES_MIN = 2
HUB = "steampunk-costumes"
NAME_WORDS_MIN = 2   # fælles ord mellem linktekst og mappingens titel


def mapping_titles() -> dict:
    """{id: [nuværende titel, ny titel]} fra ID-mappingen — bruges til at fange forkerte /go/-links."""
    titles = {}
    with open(ID_DOC, encoding="utf-8") as fh:
        for line in fh:
            if not line.lstrip().startswith("|"):
                continue
            cells = [c.strip() for c in line.strip().strip("|").split("|")]
            if len(cells) >= 3 and re.fullmatch(r"\d{3,4}", cells[0]):
                titles[cells[0]] = cells[1:4]
    return titles


def name_words(text: str) -> set:
    return {w.lower() for w in re.findall(r"[A-Za-zÆØÅæøå]{4,}", text)}


def check_product_names(block: str) -> list:
    """Et /go/-link skal hedde nogenlunde det samme som produktet med det id.

    Fanger den fejl der er lettest at lave i hånden: at kopiere et link og glemme at rette id'et,
    så guiden kalder produktet ét navn og linket peger på et andet.
    """
    problems = []
    titles = mapping_titles()
    for anchor, pid in re.findall(r"\[([^\]]+)\]\(https://steamgears\.eu/go/(\d+)/\)", block):
        names = titles.get(pid)
        if not names:
            continue  # ukendt id fanges af id-værnet
        anchor_words = name_words(anchor)
        shared = max(len(anchor_words & name_words(title)) for title in names)
        # Et kort linknavn ("goggles") skal gå igen i titlen; et længere skal dele mindst to ord.
        if shared < min(NAME_WORDS_MIN, len(anchor_words)):
            problems.append(
                f"linket til /go/{pid}/ hedder {anchor!r}, men mappingen kalder produktet "
                f"{names[1] if len(names) > 1 else names[0]!r} — forkert id?"
            )
    return problems


def check_cluster(block: str, slugs: set) -> list:
    """Klyngen: hub + alle søsterguides skal være linket fra hver guide.

    Uden dette kan en guide kun pege på den guide der blev skrevet før den, og klyngen bliver en kæde
    med et hul i stedet for en hub. Returnerer fejl for hvert manglende link.
    """
    problems = []
    body = copy_section(block)
    own = field(block, "Slug")
    linked = set(re.findall(r"https://steamgears\.eu/([a-z0-9-]+)/", body))
    linked.discard("go")
    if HUB not in linked:
        problems.append(f"hub-link mangler (/ {HUB}/)")
    missing = sorted(slugs - {own} - linked)
    if missing:
        problems.append("søsterguides ikke linket: " + ", ".join("/" + s + "/" for s in missing))
    return problems


def known_ids() -> set:
    ids = set()
    with open(ID_DOC, encoding="utf-8") as fh:
        for line in fh:
            m = re.match(r"\|\s*(\d{3,4})\s*\|", line)
            if m:
                ids.add(m.group(1))
    return ids


def known_prices() -> set:
    prices = set()
    with open(PRICE_DOC, encoding="utf-8") as fh:
        for line in fh:
            m = re.search(r"\|\s*\$(\d+\.\d{2})\s*\|", line)
            if m:
                prices.add(m.group(1))
    return prices


def blocks(text: str) -> list:
    """Deler dokumentet i guides på '## Guide …' og returnerer (navn, blok)."""
    parts = re.split(r"(?m)^## (Guide \d+[^\n]*)$", text)
    out = []
    for i in range(1, len(parts), 2):
        out.append((parts[i].strip(), parts[i + 1]))
    return out


def field(block: str, label: str) -> str:
    m = re.search(r"\*\*" + re.escape(label) + r"\*\*\s*\|\s*`([^`]+)`", block)
    return m.group(1).strip() if m else ""


def copy_section(block: str) -> str:
    """Brødteksten: fra '### Copy' til '### Publishing notes' — de øvrige '### ' er H2'er i copy."""
    m = re.search(r"(?ms)^### Copy\s*$\n(.*?)(?=^### Publishing notes)", block)
    return m.group(1).strip() if m else ""


def totals(block: str) -> tuple:
    """Læser '| **Totals** | 337.40 = 24.95+34.95 |' og regner efter.

    Returnerer (beløb der er dokumenteret, fejl). Et total må bygges af pristabellens
    beløb og af tidligere dokumenterede totaler, så en tier kan bygge på den foregående.
    """
    accepted, problems = set(), []
    line = re.search(r"(?m)^\|\s*\*\*Totals\*\*\s*\|([^|]+)\|\s*$", block)
    if not line:
        return accepted, ["ingen **Totals**-linje med regnestykket"]
    for entry in [e.strip() for e in line.group(1).split(";") if e.strip()]:
        m = re.match(r"^(\d+\.\d{2})\s*=\s*(.+)$", entry)
        if not m:
            problems.append(f"kan ikke læse totalen {entry!r} (skriv '337.40 = 24.95+34.95')")
            continue
        stated, parts = m.group(1), [p.strip() for p in m.group(2).split("+") if p.strip()]
        cents = 0
        for part in parts:
            if part in known_prices():
                cents += round(float(part) * 100)
            elif part in accepted:
                cents += round(float(part) * 100)
            else:
                problems.append(f"totalen {stated} bruger {part}, som hverken er i pristabellen eller en tidligere total")
        if cents != round(float(stated) * 100):
            problems.append(f"totalen {stated} stemmer ikke: delene giver {cents / 100:.2f}")
        accepted.add(stated)
    return accepted, problems


def check_guide(name: str, block: str, slugs: set | None = None) -> list:
    """Returnerer listen af fejl. Tom liste = guiden er i orden."""
    problems = []
    title = field(block, "Rank Math title")
    meta = field(block, "Meta description")
    slug = field(block, "Slug")
    body = copy_section(block)

    if not slug:
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
    unknown = sorted({i for i in go_ids if i not in known_ids()})
    if unknown:
        problems.append(f"/go/-id uden produktmapping: {', '.join(unknown)}")
    problems.extend(check_product_names(body))

    guides = set(re.findall(r"https://steamgears\.eu/([a-z0-9-]+)/", body))
    guides.discard("go")
    if len(guides) < GUIDES_MIN:
        problems.append(f"{len(guides)} egne guides linket (min {GUIDES_MIN})")
    if "steampunk-costumes" not in guides and "start-here" not in guides:
        problems.append("hub mangler (steampunk-costumes eller start-here)")
    if slugs is not None:
        problems.extend(check_cluster(block, slugs))

    derived, total_problems = totals(block)
    problems.extend(total_problems)
    allowed = known_prices() | derived
    prices = set(re.findall(r"\$(\d+\.\d{2})", body))
    bad_prices = sorted(p for p in prices if p not in allowed)
    if bad_prices:
        problems.append(f"pris uden hold i pristabellen eller et dokumenteret total: {', '.join('$' + p for p in bad_prices)}")

    placeholders = re.findall(r"(TODO|TBD|FIXME|\[EJER|XXX|lorem ipsum)", body, re.IGNORECASE)
    if placeholders:
        problems.append(f"pladsholder i teksten: {', '.join(sorted(set(placeholders)))}")

    return problems


def run(paths: list) -> int:
    failures = 0
    total = 0
    parsed = []
    for path in paths:
        with open(path, encoding="utf-8") as fh:
            text = fh.read()
        guides = blocks(text)
        if not guides:
            print(f"FAIL {os.path.basename(path)}: ingen '## Guide …'-blokke")
            failures += 1
            continue
        for name, block in guides:
            parsed.append((path, name, block))

    slugs = {field(block, "Slug") for _, _, block in parsed} - {""}
    for path, name, block in parsed:
        total += 1
        problems = check_guide(name, block, slugs)
        label = f"{os.path.basename(path)} · {name.split('—')[0].strip()}"
        if problems:
            failures += 1
            print(f"FAIL {label}")
            for problem in problems:
                print(f"       - {problem}")
        else:
            words = len(re.findall(r"[A-Za-zÆØÅæøå0-9'’-]+", copy_section(block)))
            print(f"PASS {label} ({words} ord)")
    print(f"\n{total - failures} af {total} guides bestod, {failures} fejlede")
    return 1 if failures else 0


def selftest() -> int:
    """Beviser at værnene fanger fejl, før de bruges på rigtige dokumenter."""
    good = """## Guide 9 — Test
| | |
| --- | --- |
| **Slug** | `test-guide` |
| **Rank Math title** | `Men's Steampunk Costume Essentials: 10 Pieces, $337` |
| **Meta description** | `Ten steampunk pieces that read as one outfit: goggles, top hat, coat, hoodies and prints, priced from our own shop at $337.40 for the whole set.` |
| **Totals** | 337.40 = 24.95+34.95+39.95+39.95+49.95+49.95+26.95+26.95+21.90+21.90 |

### Copy

**H1:** En titel

"""
    body = "Ord og tal [Steampunk Aviator Goggles](https://steamgears.eu/go/475/) og $24.95 og $21.90. " * 130
    good += body + """
**Kan man bruge goggles?**
Ja.

**Hvorfor en hat?**
Fordi.

**Hvad koster den?**
$24.95.

**Kan jeg starte småt?**
Ja.

Se [steampunk goggles](https://steamgears.eu/steampunk-goggles/) og [hub](https://steamgears.eu/steampunk-costumes/).

Hele sættet koster $337.40.

### Publishing notes

1. Intet.
"""
    cases = [
        ("gyldig guide består", good, 0),
        ("meta for kort fanges", good.replace("$337.40 for the whole set.", "Kort."), 1),
        ("ukendt /go/-id fanges", good.replace("/go/475/", "/go/9999/"), 1),
        ("opdigtet pris fanges", good.replace("$24.95", "$19.99"), 1),
        ("pladsholder fanges", good.replace("Ord og tal", "TODO Ord og tal"), 1),
        ("for få ord fanges", good.replace(body, "Kort tekst. " * 10), 1),
        ("forkert total fanges", good.replace("337.40 = 24.95", "338.40 = 24.95"), 1),
        ("forkert produktnavn på /go/-link fanges", good.replace("[Steampunk Aviator Goggles](https://steamgears.eu/go/475/)", "[Gothic Corset](https://steamgears.eu/go/475/)"), 1),
        ("manglende hub-link fanges", good.replace(" og [hub](https://steamgears.eu/steampunk-costumes/)", ""), 1),
    ]
    failed = 0
    for label, text, expect in cases:
        name, block = blocks(text)[0]
        problems = check_guide(name, block, {"test-guide"})
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

    print(f"\n{len(cases) + 2 - failed} af {len(cases) + 2} selftest-fixtures bestod")
    return 1 if failed else 0


def main() -> int:
    if "--selftest" in sys.argv:
        return selftest()
    paths = sorted(glob.glob(os.path.join(LIB, "guides-*.md")))
    if not paths:
        print("FAIL ingen docs/library/guides-*.md fundet")
        return 1
    return run(paths)


if __name__ == "__main__":
    sys.exit(main())
