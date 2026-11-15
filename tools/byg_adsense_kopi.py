#!/usr/bin/env python3
"""byg_adsense_kopi.py — statisk kopi af steamgears.eu's indhold, klar til AdSense.

Simplys WAF svarer `454 Checking your browser` på HTML herfra (målt 11/11 2026), så
kopien kan ikke spejle færdige sider. Den bygges i stedet af REST-indholdet
(`wp/v2/pages` og `wp/v2/posts`), som svarer 200, og får sit eget lette layout.

Hver side får:
  * to annoncepladser (top og bund) med `adsbygoogle`,
  * `rel="canonical"` til den oprindelige URL, så kopien ikke konkurrerer med sitet,
  * `noindex` medmindre `--indekser` bruges (duplikat-indhold rangordnes ikke),
  * interne links omskrevet til den lokale fil når målet findes i kopien.

Kør:
    python3 tools/byg_adsense_kopi.py              # skriv adsense-kopi/
    python3 tools/byg_adsense_kopi.py --check      # kontrollér den byggede kopi
    python3 tools/byg_adsense_kopi.py --indekser   # tillad indeksering

AdSense-id'erne er pladsholdere. Sæt de rigtige som miljøvariabler:
`ADSENSE_CLIENT`, `ADSENSE_SLOT_TOP`, `ADSENSE_SLOT_BOTTOM` — eller ret dem her.
Intet hemmeligt ligger i repoet; en pub-id er offentlig, men skal være ejerens.
"""

from __future__ import annotations

import argparse
import html
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "adsense-kopi"
SITE = os.environ.get("SITE_URL") or "https://steamgears.eu"
API = os.environ.get("SITE_API") or f"{SITE}/wp-json/wp/v2"
CLIENT = os.environ.get("ADSENSE_CLIENT") or "ca-pub-0000000000000000"
SLOT_TOP = os.environ.get("ADSENSE_SLOT_TOP") or "0000000001"
SLOT_BOTTOM = os.environ.get("ADSENSE_SLOT_BOTTOM") or "0000000002"
UA = "steamgears-adsense-kopi/1.0"

REST_SIDER = ("pages", "posts")


def hent(url: str) -> list[dict]:
    """Alle poster fra et REST-endepunkt, 100 pr. side."""
    poster: list[dict] = []
    side = 1
    while True:
        sep = "&" if "?" in url else "?"
        kald = f"{url}{sep}per_page=100&page={side}&_fields=id,slug,link,title,content,date,modified"
        req = urllib.request.Request(kald, headers={"Accept": "application/json", "User-Agent": UA})
        try:
            with urllib.request.urlopen(req, timeout=60) as svar:
                data = json.loads(svar.read().decode("utf-8"))
        except urllib.error.HTTPError as exc:
            if exc.code == 400 and side > 1:  # WordPress svarer 400 når siden er tom
                break
            raise SystemExit(f"REST-kald fejlede (HTTP {exc.code}): {kald}")
        except urllib.error.URLError as exc:
            raise SystemExit(f"nåede ikke {kald}: {exc.reason}")
        if not data:
            break
        poster.extend(data)
        if len(data) < 100:
            break
        side += 1
    return poster


def ren_tekst(vaerdi: str) -> str:
    return html.unescape(re.sub(r"<[^>]+>", "", vaerdi or "")).strip()


def filnavn(slug: str, brugt: set[str]) -> str:
    navn = re.sub(r"[^a-z0-9-]+", "-", (slug or "side").lower()).strip("-") or "side"
    kandidat, nr = navn, 2
    while kandidat in brugt or kandidat in {"index", "style", "ads"}:
        kandidat, nr = f"{navn}-{nr}", nr + 1
    brugt.add(kandidat)
    return f"{kandidat}.html"


def omskriv_links(indhold: str, kort: dict[str, str]) -> str:
    """Interne links til kendte sider bliver lokale filer; resten står urørt."""
    def erstat(match: re.Match[str]) -> str:
        href = match.group(1)
        sti = urlsplit(href).path if href.startswith("http") else href.split("#")[0].split("?")[0]
        if not sti or not sti.startswith("/"):
            return match.group(0)
        sti = sti if sti.endswith("/") else sti + "/"
        if sti == "/" and "index" in kort:
            return 'href="index.html"'
        return f'href="{kort[sti]}"' if sti in kort else match.group(0)

    return re.sub(r'href="([^"]+)"', erstat, indhold)


def annonce(slot: str, etiket: str) -> str:
    return f"""      <aside class="annonce" aria-label="{etiket}">
        <ins class="adsbygoogle" style="display:block"
             data-ad-client="{CLIENT}" data-ad-slot="{slot}"
             data-ad-format="auto" data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({{}});</script>
      </aside>"""


def skabelon(*, titel: str, beskrivelse: str, indhold: str, canonical: str,
             indekser: bool, top: str, bund: str) -> str:
    robots = "index,follow" if indekser else "noindex,follow"
    return f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{html.escape(titel)} · Steamgears</title>
  <meta name="description" content="{html.escape(beskrivelse)}">
  <meta name="robots" content="{robots}">
  <link rel="canonical" href="{html.escape(canonical)}">
  <link rel="stylesheet" href="style.css">
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={CLIENT}"
          crossorigin="anonymous"></script>
</head>
<body>
  <header class="top">
    <a class="brand" href="index.html">Steamgears</a>
    <nav><a href="{SITE}/">Original site</a></nav>
  </header>
{top}
  <main>
{indhold}
  </main>
{bund}
  <footer>
    <p>Static copy of <a href="{html.escape(canonical)}">{html.escape(canonical)}</a>.
       Content and images belong to Steamgears.</p>
  </footer>
</body>
</html>
"""


def hub(sider: list[dict], poster: list[dict], indekser: bool, top: str, bund: str) -> str:
    def liste(emner: list[dict]) -> str:
        punkter = "\n".join(
            f'        <li><a href="{e["fil"]}">{html.escape(e["titel"])}</a></li>' for e in emner)
        return f"      <ul>\n{punkter}\n      </ul>"

    indhold = (f"    <h1>Steamgears — steampunk guides and looks</h1>\n"
               f"    <p>Every guide from the shop, collected in one place.</p>\n"
               f"    <h2>Guides</h2>\n{liste(poster)}\n"
               f"    <h2>Pages</h2>\n{liste(sider)}")
    return skabelon(titel="Steamgears guides", beskrivelse="Steampunk guides, outfits and looks.",
                    indhold=indhold, canonical=f"{SITE}/", indekser=indekser, top=top, bund=bund)


STYLE = """:root{--bg:#14100d;--panel:#1e1813;--tekst:#efe3cf;--daemp:#b8a891;--gulv:#c9a227}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tekst);font:17px/1.65 Georgia,serif}
a{color:var(--gulv)}
.top{display:flex;justify-content:space-between;align-items:center;gap:1rem;
     padding:1rem 1.25rem;border-bottom:1px solid #3a2f25;background:var(--panel)}
.brand{font-size:1.25rem;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;color:var(--tekst)}
main{max-width:46rem;margin:0 auto;padding:2rem 1.25rem 3rem}
h1{font-size:2rem;line-height:1.25;margin:0 0 1rem}
h2{margin-top:2.5rem;font-size:1.35rem}
img{max-width:100%;height:auto}
figure{margin:1.5rem 0}
ul{padding-left:1.25rem}
li{margin:.35rem 0}
.annonce{margin:1.5rem auto;max-width:46rem;padding:0 1.25rem;min-height:90px}
footer{border-top:1px solid #3a2f25;color:var(--daemp);font-size:.85rem;padding:1.5rem 1.25rem;text-align:center}
footer a{color:var(--daemp)}
@media (max-width:480px){body{font-size:16px}main{padding:1.5rem 1rem 2rem}h1{font-size:1.6rem}}
"""


def byg(indekser: bool) -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    kort: dict[str, str] = {}
    emner: list[dict] = []
    sprunget: list[dict] = []

    for rest_typer, gruppe in (("pages", "side"), ("posts", "guide")):
        for post in hent(f"{API}/{rest_typer}"):
            sti = urlsplit(post.get("link") or "").path or "/"
            sti = sti if sti.endswith("/") else sti + "/"
            titel = ren_tekst(post.get("title", {}).get("rendered", "")) or post.get("slug", "side")
            indhold = post.get("content", {}).get("rendered", "")
            # AdSense må ikke stå på sider uden redaktionelt indhold. Kurv, kasse,
            # min konto og opt-out er funktionssider — de hoppes over, ikke pyntes.
            if len(" ".join(re.sub(r"<[^>]+>", " ", indhold).split())) < 200:
                sprunget.append({"titel": titel, "original": post.get("link") or f"{SITE}{sti}"})
                continue
            emner.append({
                "gruppe": gruppe,
                "slug": post.get("slug") or "side",
                "sti": sti,
                "link": post.get("link") or f"{SITE}{sti}",
                "titel": titel,
                "indhold": indhold,
                "dato": (post.get("modified") or post.get("date") or "")[:10],
            })

    brugte: set[str] = set()
    for emne in emner:
        emne["fil"] = filnavn(emne["slug"], brugte)
        kort[emne["sti"]] = emne["fil"]
    kort.setdefault("/", "index.html")

    top, bund = annonce(SLOT_TOP, "Annonce"), annonce(SLOT_BOTTOM, "Annonce")
    for emne in emner:
        indhold = omskriv_links(emne["indhold"], kort)
        dato = f'<p class="dato">Updated {emne["dato"]}</p>' if emne["dato"] else ""
        (OUT / emne["fil"]).write_text(skabelon(
            titel=emne["titel"],
            beskrivelse=f'{emne["titel"]} — Steamgears steampunk guides and looks.',
            indhold=f'    <article>\n      <h1>{html.escape(emne["titel"])}</h1>\n      {dato}\n{indhold}\n    </article>',
            canonical=emne["link"], indekser=indekser, top=top, bund=bund), encoding="utf-8")

    sider = [e for e in emner if e["gruppe"] == "side"]
    poster = [e for e in emner if e["gruppe"] == "guide"]
    (OUT / "index.html").write_text(hub(sider, poster, indekser, top, bund), encoding="utf-8")
    (OUT / "style.css").write_text(STYLE, encoding="utf-8")
    (OUT / "ads.txt").write_text(
        f"google.com, {CLIENT.removeprefix('ca-')}, DIRECT, f08c47fec0942fa0\n", encoding="utf-8")
    (OUT / "manifest.json").write_text(json.dumps({
        "bygget": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "kilde": SITE, "indekser": indekser,
        "adsense": {"client": CLIENT, "slot_top": SLOT_TOP, "slot_bottom": SLOT_BOTTOM},
        "sprunget_over": sprunget,
        "sider": [{"fil": e["fil"], "titel": e["titel"], "original": e["link"], "gruppe": e["gruppe"]}
                  for e in emner],
    }, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    print(f"Byggede {len(emner)} sider + hub i {OUT.relative_to(ROOT)}/ "
          f"({len(sider)} pages, {len(poster)} posts). "
          f"Sprang {len(sprunget)} indholdsløse sider over.")
    return 0


def check() -> int:
    fejl: list[str] = []
    manifest_fil = OUT / "manifest.json"
    if not manifest_fil.exists():
        print("Ingen kopi at kontrollere — kør uden --check først.")
        return 1
    manifest = json.loads(manifest_fil.read_text(encoding="utf-8"))
    for post in manifest["sider"]:
        fil = OUT / post["fil"]
        if not fil.exists():
            fejl.append(f"mangler fil: {post['fil']}")
            continue
        raw = fil.read_text(encoding="utf-8")
        krop = re.search(r"<main>(.*?)</main>", raw, re.S)
        tekst = re.sub(r"<[^>]+>", " ", krop.group(1) if krop else "")
        if len(" ".join(tekst.split())) < 200:
            fejl.append(f"tynd side: {post['fil']}")
        for markør in ("adsbygoogle", 'rel="canonical"', "pagead2.googlesyndication.com"):
            if markør not in raw:
                fejl.append(f"{post['fil']} mangler {markør}")
        if post["original"] not in raw:
            fejl.append(f"{post['fil']} mangler canonical til originalen")
    for fil in ("index.html", "style.css", "ads.txt"):
        if not (OUT / fil).exists():
            fejl.append(f"mangler {fil}")
    if (OUT / "ads.txt").exists() and "google.com, pub-" not in (OUT / "ads.txt").read_text():
        fejl.append("ads.txt mangler google.com-linje")
    html_filer = list(OUT.glob("*.html"))
    if len(html_filer) != len(manifest["sider"]) + 1:
        fejl.append(f"forventede {len(manifest['sider']) + 1} html-filer, fandt {len(html_filer)}")
    if fejl:
        print("Kopien er ikke i orden:")
        for punkt in fejl[:20]:
            print(f"  - {punkt}")
        return 1
    print(f"[OK] {len(html_filer)} html-filer, alle med annonceplads og canonical; "
          f"ads.txt og style.css på plads.")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Statisk AdSense-kopi af steamgears.eu.")
    parser.add_argument("--check", action="store_true", help="kontrollér den byggede kopi")
    parser.add_argument("--indekser", action="store_true", help="tillad søgemaskine-indeksering")
    args = parser.parse_args()
    return check() if args.check else byg(args.indekser)


if __name__ == "__main__":
    raise SystemExit(main())
