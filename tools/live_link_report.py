#!/usr/bin/env python3
"""Måler hvilke `/go/<id>/`-links de ældre guides faktisk har på det levende site.

Planen i `docs/library/guide-links-aeldre-<dato>.md` siger hvilke links hver guide skal have. Dette
værktøj henter hver guide, finder de `/go/<id>/`-links der står i HTML'en, og følger hvert link for at se
om det redirecter til det produkt ID'et hører til. Resultatet skrives som en målt tabel, som
[`link_plan_check.py`](link_plan_check.py) bagefter kan holde planens `indsat`-status op imod — så et
"indsat" i planen altid har en måling bag sig.

Brug:
    python3 tools/live_link_report.py --dato 4/11 2026
    python3 tools/live_link_report.py --dato 4/11 2026 --check   # fejler hvis dokumentet ikke matcher

Intet gættes: sider der ikke svarer 200, og links der ikke redirecter til et produkt, skrives som
`nej`/`ikke produkt` i tabellen i stedet for at blive talt med.
"""

import os
import re
import sys
import urllib.error
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLAN = os.path.join(ROOT, "docs", "library", "guide-links-aeldre-2026-11-03.md")
OUT = os.path.join(ROOT, "docs", "library", "guide-links-live-2026-11-04.md")
SITE = "https://steamgears.eu"
UA = "Mozilla/5.0 (compatible; steamgears-link-check/1.0)"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def fetch(url: str) -> tuple[int, str]:
    request = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return response.status, response.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as exc:
        return exc.code, ""
    except urllib.error.URLError as exc:
        return 0, f"nåede ikke {url}: {exc.reason}"


def follow(url: str) -> tuple[int, str]:
    """Returnerer status og Location uden at følge redirecten."""
    opener = urllib.request.build_opener(NoRedirect)
    request = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with opener.open(request, timeout=60) as response:
            return response.status, response.headers.get("Location", "")
    except urllib.error.HTTPError as exc:
        return exc.code, exc.headers.get("Location", "") if exc.headers else ""


def plan_rows() -> dict:
    """guide-sti -> [(produkt-ID, status)] fra planens tabeller, i planens rækkefølge."""
    with open(PLAN, encoding="utf-8") as fh:
        text = fh.read()
    guides: dict[str, list[tuple[str, str]]] = {}
    for line in text.splitlines():
        if not line.startswith("|") or "/go/" not in line:
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        if len(cells) < 5:
            continue
        guide = cells[0].strip("`")
        m = re.search(r"/go/(\d+)/", cells[1])
        if not m or not guide.startswith("/"):
            continue
        ids = guides.setdefault(guide, [])
        if m.group(1) not in [i for i, _ in ids]:
            ids.append((m.group(1), cells[4].strip("`")))
    return guides


def measure(dato: str) -> tuple[str, int]:
    guides = plan_rows()
    lines = [
        "# Målt: /go/-links i de ældre guides — " + dato,
        "",
        "**Målt:** " + dato + " mod det levende site. Hver række er et opslag, ikke en påstand: siden er hentet,",
        "linket er fundet i HTML'en, og `/go/<id>/` er fulgt for at se hvor det lander. Rækkefølgen følger",
        "[`guide-links-aeldre-2026-11-03.md`](guide-links-aeldre-2026-11-03.md), som er planen.",
        "",
        "Dokumentet skrives af `python3 tools/live_link_report.py --dato \"<dato>\"`, og",
        "[`tools/link_plan_check.py`](link_plan_check.py) kræver at hver række planen kalder `indsat` står her",
        "med `ja` — ellers fejler værnene. Målingen skal altså laves om, hvis en side redigeres.",
        "",
        "| Guide | Link | Side | Kode | Produkt | Plan |",
        "| --- | --- | --- | --- | --- | --- |",
    ]
    problems = 0
    total = 0
    expected = 0
    for guide, rows in guides.items():
        status, html = fetch(SITE + guide)
        found = set(re.findall(r'href="(?:%s)?/go/(\d+)/"' % re.escape(SITE), html))
        for pid, plan_status in rows:
            total += 1
            on_page = "ja" if (status == 200 and pid in found) else "nej"
            code, location = follow(f"{SITE}/go/{pid}/")
            product = location.replace(SITE + "/product/", "").strip("/")
            if "/product/" not in location:
                product = location or "—"
            # Kortes ned, så tabellen kan sættes på én side: hele permalinken kan altid hentes igen
            if len(product) > 24:
                product = product[:23] + "…"
            if plan_status == "indsat":
                expected += 1
                if on_page != "ja" or code not in (301, 302) or "/product/" not in location:
                    problems += 1
            lines.append(f"| `{guide}` | /go/{pid}/ | {on_page} | {code} | {product or '—'} | `{plan_status}` |")
    lines += [
        "",
        f"**Resultat:** {total} rækker målt. {expected} af dem kalder planen `indsat`, og de skal alle stå med `ja`;",
        f"{problems} afvigelser. Rækker med status `kladde` og `klar` må stå med `nej` — de er ikke publiceret endnu.",
        "Sider der ikke svarer 200, tælles som afvigelse — også når linket findes.",
        "",
        "| Guide | Svarer | /go/-links fundet på siden |",
        "| --- | ---: | ---: |",
    ]
    for guide, rows in guides.items():
        status, html = fetch(SITE + guide)
        found = len(set(re.findall(r'href="(?:%s)?/go/(\d+)/"' % re.escape(SITE), html)))
        on_page = sum(1 for pid, _ in rows if pid in set(re.findall(r'href="(?:%s)?/go/(\d+)/"' % re.escape(SITE), html)))
        lines.append(f"| `{guide}` | {status} | {on_page} af {len(rows)} |")
    lines.append("")
    return "\n".join(lines), problems


def main() -> int:
    dato = None
    for flag in ("--dato",):
        if flag in sys.argv:
            dato = sys.argv[sys.argv.index(flag) + 1]
    if not dato:
        print("brug: python3 tools/live_link_report.py --dato '4/11 2026' [--check]")
        return 2
    text, problems = measure(dato)
    if "--check" in sys.argv:
        if not os.path.exists(OUT):
            print(f"FAIL {os.path.relpath(OUT, ROOT)} findes ikke — mål igen")
            return 1
        with open(OUT, encoding="utf-8") as fh:
            saved = fh.read()
        if saved.strip() != text.strip():
            print("FAIL målingen i dokumentet matcher ikke det levende site — kør uden --check for at opdatere")
            return 1
        print("PASS målingen i dokumentet matcher det levende site")
        return 1 if problems else 0
    with open(OUT, "w", encoding="utf-8") as fh:
        fh.write(text)
    print(f"skrev {os.path.relpath(OUT, ROOT)} — {problems} afvigelser")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
