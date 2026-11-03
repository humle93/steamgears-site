#!/usr/bin/env python3
"""Værn for link-planen til de ældre publicerede guides.

Planen i `docs/library/guide-links-aeldre-2026-11-03.md` er kilden til hvilke `/go/<id>/`-links de ældre
guides skal have. Værnet kontrollerer at planen ikke kan indeholde noget der ikke findes:

- hver guide-sti ser ud som en sti på sitet (`/noget/`)
- hvert `/go/<id>/` findes i produkt-ID-mappingen (inkl. appendix for de seks produkter uden for de 39 designs)
- linkteksten deler ord med mappingens titel, så et link ikke lover en corset og sælger goggles
- hver pris findes i pristabellen, i de målte priser fra butikken (3/11) — eller er `gratis` for den gratis
  PDF (809). `—` er ikke en gyldig pris: hvert produkt i planen har en pris der kan slås op
- status er en af `indsat` eller `klar`; `pris mangler` er afskaffet, fordi alle priser nu er målt
- en række med status `indsat` kræver at `/go/<id>/` faktisk står i den fil guiden bor i — planen må ikke
  påstå at et link er anvendt, hvis det ikke er
- ingen guide/produkt-par står to gange

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
FREE_IDS = {"809"}          # den gratis tjekliste-PDF har ingen pris
STATUSES = {"indsat", "klar"}
REPO_FILES = {"/steampunk-outfit-ideas-for-women/": "docs/library/steampunk-outfit-ideas-for-women.md"}

_spec = importlib.util.spec_from_file_location("guide_check", os.path.join(ROOT, "tools", "guide_check.py"))
gc = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(gc)


def parse_rows(text: str) -> list:
    """Læser plan-tabellen: | Guide | Link | Linktekst | Pris | Status |."""
    rows = []
    for line in text.splitlines():
        if not line.startswith("|") or "/go/" not in line:
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if len(cells) < 5:
            continue
        m = re.search(r"/go/(\d+)/", cells[1])
        rows.append({
            "guide": cells[0].strip("`"),
            "id": m.group(1) if m else "",
            "anchor": cells[2],
            "price": cells[3],
            "status": cells[4].strip("`"),
        })
    return rows


def check_rows(rows: list, ids: set, titles: dict, prices: set, repo_texts: dict | None = None) -> list:
    problems = []
    if not rows:
        return ["ingen rækker i plan-tabellen"]
    repo_texts = repo_texts or {}
    seen, guides = set(), set()
    for row in rows:
        guide, pid, anchor, price, status = row["guide"], row["id"], row["anchor"], row["price"], row["status"]
        if not re.fullmatch(r"/[a-z0-9][a-z0-9/-]*/", guide):
            problems.append(f"guide-stien {guide!r} ser ikke ud som en sti på sitet")
        guides.add(guide)
        if status not in STATUSES:
            problems.append(f"{guide}: ukendt status {status!r} (skal være en af {', '.join(sorted(STATUSES))})")
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
        if price == "—":
            problems.append(
                f"{guide}: /go/{pid}/ har pris '—' — prisen skal måles (butikken, 3/11) i stedet for at stå tom"
            )
        elif price.lower() == "gratis":
            if pid not in FREE_IDS:
                problems.append(f"{guide}: /go/{pid}/ er markeret gratis, men er ikke den gratis PDF")
        else:
            m = re.fullmatch(r"\$(\d+\.\d{2})", price)
            if not m:
                problems.append(f"{guide}: prisen {price!r} er hverken '$x.xx', 'gratis' eller '—'")
            elif m.group(1) not in prices:
                problems.append(f"{guide}: prisen {price} findes ikke i pristabellen")
        if status == "indsat":
            path = REPO_FILES.get(guide)
            if not path:
                problems.append(f"{guide}: står som 'indsat', men guiden findes ikke som fil i repoet")
            elif f"/go/{pid}/" not in repo_texts.get(path, ""):
                problems.append(f"{guide}: står som 'indsat', men /go/{pid}/ findes ikke i {path}")
        if (guide, pid) in seen:
            problems.append(f"{guide}: /go/{pid}/ står to gange — ét link pr. produkt pr. guide")
        seen.add((guide, pid))
    print(
        f"{len(rows)} plan-rækker · {len(guides)} guides · {len({r['id'] for r in rows})} unikke produkter · "
        f"{sum(1 for r in rows if r['status'] == 'indsat')} indsat i repoet · "
        f"{sum(1 for r in rows if r['status'] == 'klar')} klar til adgang · "
        f"{sum(1 for r in rows if r['price'] == '—')} uden pris"
    )
    return problems


def selftest() -> int:
    ids = {"477", "481", "728", "766"}
    titles = {
        "477": ["Copper Pilot Goggles"],
        "481": ["Steampunk Top Hat with Removable Goggles"],
        "728": ["First Mate's Oath – Steampunk Pirate Poster", "First Mate's Oath — Vintage Maritime Print"],
        "766": ["Steampunk Leather Utility Belt & Holster"],
    }
    prices = {"24.95", "34.95", "21.90", "6.90"}
    repo = {"docs/library/steampunk-outfit-ideas-for-women.md": "[x](https://steamgears.eu/go/477/)"}
    files = {"/steampunk-outfit-ideas-for-women/": "docs/library/steampunk-outfit-ideas-for-women.md"}
    def row(guide, pid, anchor, price, status):
        return {"guide": guide, "id": pid, "anchor": anchor, "price": price, "status": status}

    good = [
        row("/steampunk-outfit-ideas-for-women/", "477", "Copper Pilot goggles", "$24.95", "indsat"),
        row("/best-steampunk-books/", "728", "First Mate's Oath print", "$21.90", "klar"),
        row("/build/weathering/", "766", "Utility belt and holster", "$6.90", "klar"),
    ]
    old_files = dict(globals().get("REPO_FILES", {}))
    globals()["REPO_FILES"] = files
    cases = [
        ("gyldig plan består", good, 0),
        ("ukendt /go/-id fanges", [dict(good[0], id="9999")], 1),
        ("opdigtet pris fanges", [dict(good[0], price="$19.99")], 1),
        ("gratis på et betalt produkt fanges", [dict(good[0], price="gratis")], 1),
        ("tom pris '—' fanges", [dict(good[0], price="—")], 1),
        ("den afskaffede status 'pris mangler' fanges", [dict(good[0], status="pris mangler")], 1),
        ("forkert produktnavn fanges", [dict(good[0], anchor="Victorian corset")], 1),
        ("dubleret guide/produkt fanges", [good[0], dict(good[0])], 1),
        ("ugyldig guide-sti fanges", [dict(good[0], guide="how-to-make-a-steampunk-costume")], 1),
        ("ukendt status fanges", [dict(good[0], status="måske")], 1),
        ("indsat uden link i filen fanges", [dict(good[0], id="481", anchor="Steampunk top hat")], 1),
        ("indsat uden fil i repoet fanges", [dict(good[0], guide="/casual-steampunk-outfits/")], 1),
        ("tom plan fanges", [], 1),
    ]
    failed = 0
    for label, rows, expect in cases:
        problems = check_rows(rows, ids, titles, prices, repo)
        got = 1 if problems else 0
        ok = got == expect
        failed += 0 if ok else 1
        print(f"{'PASS' if ok else 'FAIL'} {label}" + ("" if ok else f" (forventede {expect}, fik {got}: {problems})"))
    globals()["REPO_FILES"] = old_files
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
    repo_texts = {}
    for path in REPO_FILES.values():
        with open(os.path.join(ROOT, path), encoding="utf-8") as fh:
            repo_texts[path] = fh.read()
    problems = check_rows(parse_rows(text), gc.known_ids(), gc.mapping_titles(), gc.known_prices(), repo_texts)
    if problems:
        print("FAIL guide-links-aeldre")
        for problem in problems:
            print(f"       - {problem}")
        return 1
    print("PASS guide-links-aeldre — alle links, navne, priser og statusser har hold i repoet")
    return 0


if __name__ == "__main__":
    sys.exit(main())
