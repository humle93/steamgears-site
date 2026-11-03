#!/usr/bin/env python3
"""Værn for link-planen til de ældre publicerede guides.

Planen i `docs/library/guide-links-aeldre-2026-11-03.md` er den eneste kilde til hvilke `/go/<id>/`-links
der skal ind i de guides der allerede er live. Værnet kontrollerer at planen ikke kan indeholde noget der
ikke findes:

- hver guide-sti ser ud som en sti på sitet (`/noget/`)
- hvert `/go/<id>/` findes i produkt-ID-mappingen
- linkteksten deler ord med mappingens titel, så et link ikke lover en corset og sælger goggles
- hver pris findes i pristabellen (eller er markeret `gratis` for den gratis PDF)
- ingen guide/produkt-par står to gange, og hver guide har mindst ét link

Brug:
    python3 tools/link_plan_check.py              # tjekker planen
    python3 tools/link_plan_check.py --selftest   # beviser at værnene fanger fejl
"""

import importlib.util
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLAN = os.path.join(ROOT, "docs", "library", "guide-links-aeldre-2026-11-03.md")
FREE_IDS = {"809"}  # den gratis tjekliste-PDF har ingen pris

_spec = importlib.util.spec_from_file_location("guide_check", os.path.join(ROOT, "tools", "guide_check.py"))
gc = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(gc)


def parse_rows(text: str) -> list:
    """Læser plan-tabellen: | Guide | Link | Anchor | Pris | — header og skillelinje springes over."""
    rows = []
    for line in text.splitlines():
        if not line.startswith("|") or "/go/" not in line:
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if len(cells) < 4:
            continue
        guide, link, anchor, price = cells[0], cells[1], cells[2], cells[3]
        m = re.search(r"/go/(\d+)/", link)
        rows.append({"guide": guide.strip("`"), "id": m.group(1) if m else "", "anchor": anchor, "price": price})
    return rows


def check_rows(rows: list, ids: set, titles: dict, prices: set) -> list:
    problems = []
    if not rows:
        return ["ingen rækker i plan-tabellen"]
    seen = set()
    guides = set()
    for row in rows:
        guide, pid, anchor, price = row["guide"], row["id"], row["anchor"], row["price"]
        if not re.fullmatch(r"/[a-z0-9][a-z0-9/-]*/", guide):
            problems.append(f"guide-stien {guide!r} ser ikke ud som en sti på sitet")
        guides.add(guide)
        if not pid:
            problems.append(f"{guide}: linket har intet /go/<id>/")
            continue
        if pid not in ids:
            problems.append(f"{guide}: /go/{pid}/ findes ikke i produkt-ID-mappingen")
        names = titles.get(pid)
        if names:
            anchor_words = gc.name_words(anchor)
            shared = max(len(anchor_words & gc.name_words(t)) for t in names)
            if shared < min(gc.NAME_WORDS_MIN, len(anchor_words)):
                problems.append(
                    f"{guide}: linkteksten {anchor!r} passer ikke på produktet "
                    f"{names[1] if len(names) > 1 else names[0]!r} (/go/{pid}/)"
                )
        if len(gc.name_words(anchor)) < 1:
            problems.append(f"{guide}: linkteksten {anchor!r} er tom eller uden brugbare ord")
        if price.lower() != "gratis":
            m = re.fullmatch(r"\$(\d+\.\d{2})", price)
            if not m:
                problems.append(f"{guide}: prisen {price!r} er hverken '$x.xx' eller 'gratis'")
            elif m.group(1) not in prices:
                problems.append(f"{guide}: prisen {price} findes ikke i pristabellen")
        elif pid not in FREE_IDS:
            problems.append(f"{guide}: /go/{pid}/ er markeret gratis, men er ikke den gratis PDF")
        if (guide, pid) in seen:
            problems.append(f"{guide}: /go/{pid}/ står to gange — ét link pr. produkt pr. guide")
        seen.add((guide, pid))
    print(f"{len(rows)} plan-rækker · {len(guides)} guides · {len({r['id'] for r in rows})} unikke produkter")
    return problems


def selftest() -> int:
    ids = {"477", "481", "728"}
    titles = {"477": ["Copper Pilot Goggles"], "481": ["Steampunk Top Hat with Removable Goggles"], "728": ["First Mate's Oath – Steampunk Pirate Poster", "First Mate's Oath — Vintage Maritime Print"]}
    prices = {"24.95", "34.95", "21.90"}
    good = [
        {"guide": "/how-to-make-a-steampunk-costume/", "id": "477", "anchor": "Copper Pilot goggles", "price": "$24.95"},
        {"guide": "/best-steampunk-books/", "id": "728", "anchor": "First Mate's Oath print", "price": "$21.90"},
    ]
    cases = [
        ("gyldig plan består", good, 0),
        ("ukendt /go/-id fanges", [dict(good[0], id="9999")], 1),
        ("opdigtet pris fanges", [dict(good[0], price="$19.99")], 1),
        ("gratis på et betalt produkt fanges", [dict(good[0], price="gratis")], 1),
        ("forkert produktnavn fanges", [dict(good[0], anchor="Victorian corset")], 1),
        ("dubleret guide/produkt fanges", [good[0], dict(good[0])], 1),
        ("ugyldig guide-sti fanges", [dict(good[0], guide="how-to-make-a-steampunk-costume")], 1),
        ("tom plan fanges", [], 1),
    ]
    failed = 0
    for label, rows, expect in cases:
        problems = check_rows(rows, ids, titles, prices)
        got = 1 if problems else 0
        ok = got == expect
        failed += 0 if ok else 1
        print(f"{'PASS' if ok else 'FAIL'} {label}" + ("" if ok else f" (forventede {expect}, fik {got}: {problems})"))
    print(f"\n{len(cases) - failed} af {len(cases)} selftest-fixtures bestod")
    return 1 if failed else 0


def main() -> int:
    if "--selftest" in sys.argv:
        return selftest()
    if not os.path.exists(PLAN):
        print(f"FAIL {os.path.relpath(PLAN, ROOT)} findes ikke")
        return 1
    with open(PLAN, encoding="utf-8") as fh:
        text = fh.read()
    problems = check_rows(parse_rows(text), gc.known_ids(), gc.mapping_titles(), gc.known_prices())
    if problems:
        print("FAIL guide-links-aeldre")
        for problem in problems:
            print(f"       - {problem}")
        return 1
    print("PASS guide-links-aeldre — alle links, navne og priser har hold i mappingen og pristabellen")
    return 0


if __name__ == "__main__":
    sys.exit(main())
