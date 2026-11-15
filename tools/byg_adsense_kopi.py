#!/usr/bin/env python3
"""byg_adsense_kopi.py — selvstændig statisk kopi af steamgears.eu, klar til AdSense.

Simplys WAF svarer `454 Checking your browser` på HTML herfra (målt 11/11 2026), så
kopien kan ikke spejle færdige sider. Den bygges i stedet af REST-indholdet
(`wp/v2/pages` og `wp/v2/posts`), som svarer 200, og får sit eget lette layout.

Kopien er **selvstændig**: alle billeder hentes ind i `media/`, og hver reference
skrives om ved kilden, så intet peger på en fil der ikke findes:

  * intern URL der findes i kopien  → lokal fil
  * intern URL der ikke kopieres    → absolut URL til det levende site
    (fx kurv, kasse, produktkategorier og `/go/<id>/`, som ikke har indhold)
  * ekstern URL                     → urørt
  * billede                         → `media/<navn>-<hash>.<ext>`

Hver side får to annoncepladser, `rel="canonical"` til den oprindelige URL og
`noindex` medmindre `--indekser` bruges (duplikat-indhold skal ikke rangordnes).

Kør:
    python3 tools/byg_adsense_kopi.py              # byg (henter også billeder)
    python3 tools/byg_adsense_kopi.py --check      # kontrollér hele mappen, ingen netværk
    python3 tools/byg_adsense_kopi.py --indekser   # tillad indeksering

AdSense-id'erne er pladsholdere. Sæt de rigtige som miljøvariabler:
`ADSENSE_CLIENT`, `ADSENSE_SLOT_TOP`, `ADSENSE_SLOT_BOTTOM` — eller ret dem her.
Intet hemmeligt ligger i repoet; en pub-id er offentlig, men skal være ejerens.
"""

from __future__ import annotations

import argparse
import hashlib
import html
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "adsense-kopi"
MEDIA = "media"
SITE = os.environ.get("SITE_URL") or "https://steamgears.eu"
API = os.environ.get("SITE_API") or f"{SITE}/wp-json/wp/v2"
CLIENT = os.environ.get("ADSENSE_CLIENT") or "ca-pub-0000000000000000"
SLOT_TOP = os.environ.get("ADSENSE_SLOT_TOP") or "0000000001"
SLOT_BOTTOM = os.environ.get("ADSENSE_SLOT_BOTTOM") or "0000000002"
UA = "steamgears-adsense-kopi/1.0"

SITE_HOST = urlsplit(SITE).netloc
BILLEDE_EXT = {".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".avif", ".ico"}
FIL_EXT = BILLEDE_EXT | {".css", ".js", ".pdf", ".zip", ".mp4", ".webm"}
MAGIC = {
    b"\xff\xd8\xff": "jpeg",
    b"\x89PNG": "png",
    b"GIF8": "gif",
    b"RIFF": "webp",
    b"<svg": "svg",
    b"<?xm": "svg",
}


# ---------------------------------------------------------------- REST

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


def uden_markup(vaerdi: str) -> str:
    return " ".join(re.sub(r"<[^>]+>", " ", vaerdi or "").split())


# ---------------------------------------------------------------- medier

def medie_navn(url: str) -> str:
    """Stabilt, kollisionsfrit filnavn: læsbar stamme + hash af hele URL'en."""
    sti = urlsplit(url).path
    stamme = Path(sti).stem or "fil"
    stamme = re.sub(r"[^A-Za-z0-9._-]+", "-", stamme).strip("-.")[:60] or "fil"
    ext = Path(sti).suffix.lower()
    if ext not in BILLEDE_EXT:
        ext = ".jpg"
    return f"{stamme}-{hashlib.sha1(url.encode()).hexdigest()[:10]}{ext}"


def hent_medie(url: str) -> tuple[str, bytes | None]:
    req = urllib.request.Request(url, headers={"User-Agent": UA,
                                               "Accept": "image/avif,image/webp,image/*,*/*"})
    try:
        with urllib.request.urlopen(req, timeout=60) as svar:
            data = svar.read()
            ctype = svar.headers.get("Content-Type", "")
            if not data or (ctype and not ctype.startswith(("image/", "application/octet"))):
                return url, None
            return url, data
    except (urllib.error.HTTPError, urllib.error.URLError, TimeoutError):
        return url, None


def hent_medier(urls: list[str]) -> dict[str, str]:
    """Hent alle billeder til media/. Eksisterende, ikke-tomme filer genbruges."""
    (OUT / MEDIA).mkdir(parents=True, exist_ok=True)
    medier: dict[str, str] = {}
    mangler: list[str] = []
    for url in urls:
        navn = medie_navn(url)
        fil = OUT / MEDIA / navn
        if fil.exists() and fil.stat().st_size > 0:
            medier[url] = f"{MEDIA}/{navn}"
        else:
            mangler.append(url)

    if mangler:
        print(f"Henter {len(mangler)} billeder …")
        with ThreadPoolExecutor(max_workers=8) as ex:
            for url, data in ex.map(hent_medie, mangler):
                navn = medie_navn(url)
                if data is None:
                    print(f"  ! kunne ikke hente {url}")
                    continue
                (OUT / MEDIA / navn).write_bytes(data)
                medier[url] = f"{MEDIA}/{navn}"
    return medier


def omskriv_medier(indhold: str, medier: dict[str, str]) -> str:
    """src, srcset og url(...) i inline-styles peger på de lokale filer."""
    def en(url: str) -> str:
        return medier.get(url.strip(), url)

    def src_repl(m: re.Match[str]) -> str:
        return f'src="{en(m.group(1))}"'

    def srcset_repl(m: re.Match[str]) -> str:
        dele = []
        for del_ in m.group(1).split(","):
            del_ = del_.strip()
            if not del_:
                continue
            stykker = del_.split()
            stykker[0] = en(stykker[0])
            dele.append(" ".join(stykker))
        return 'srcset="' + ", ".join(dele) + '"'

    def css_repl(m: re.Match[str]) -> str:
        return f"url({en(m.group(2))})"

    indhold = re.sub(r'src="([^"]+)"', src_repl, indhold)
    indhold = re.sub(r'srcset="([^"]+)"', srcset_repl, indhold)
    indhold = re.sub(r'url\((["\']?)(https?://[^)"\']+)\1\)', css_repl, indhold)
    return indhold


# ---------------------------------------------------------------- links

def normaliser_sti(sti: str) -> str:
    return sti if sti.endswith("/") else sti + "/"


def omskriv_links(indhold: str, kort: dict[str, str], medier: dict[str, str]) -> str:
    """Hver href bliver lokal hvis målet er kopieret, ellers absolut til sitet."""
    def erstat(match: re.Match[str]) -> str:
        href = match.group(1).strip()
        if not href or href.startswith(("mailto:", "tel:", "data:", "#")):
            return match.group(0)
        if href in medier:
            return f'href="{medier[href]}"'
        if href.startswith(("http://", "https://", "//")):
            dele = urlsplit(href if not href.startswith("//") else "https:" + href)
            if dele.netloc and dele.netloc != SITE_HOST:
                return match.group(0)  # eksternt domæne
            sti = dele.path or "/"
            hale = (f"?{dele.query}" if dele.query else "") + (f"#{dele.fragment}" if dele.fragment else "")
        else:
            sti, _, fragment = href.partition("#")
            sti, _, query = sti.partition("?")
            hale = (f"?{query}" if query else "") + (f"#{fragment}" if fragment else "")
            if not sti.startswith("/"):
                return match.group(0)  # relativ sti, fx style.css
        sti = normaliser_sti(sti)
        if sti in kort:
            return f'href="{kort[sti]}{hale}"'
        # Ikke kopieret (kurv, kasse, kategori, /go/<id>/): peg på det levende site,
        # så kopien aldrig viser et link til en side der ikke findes her.
        return f'href="{SITE}{sti}{hale}"'

    return re.sub(r'href="([^"]*)"', erstat, indhold)


# ---------------------------------------------------------------- skabelon

def filnavn(slug: str, brugt: set[str]) -> str:
    navn = re.sub(r"[^a-z0-9-]+", "-", (slug or "side").lower()).strip("-") or "side"
    kandidat, nr = navn, 2
    while kandidat in brugt or kandidat in {"index", "style", "ads"}:
        kandidat, nr = f"{navn}-{nr}", nr + 1
    brugt.add(kandidat)
    return f"{kandidat}.html"


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


# ---------------------------------------------------------------- byg

def byg(indekser: bool) -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    kort: dict[str, str] = {}
    emner: list[dict] = []
    sprunget: list[dict] = []

    for rest_typer, gruppe in (("pages", "side"), ("posts", "guide")):
        for post in hent(f"{API}/{rest_typer}"):
            sti = urlsplit(post.get("link") or "").path or "/"
            sti = normaliser_sti(sti)
            titel = ren_tekst(post.get("title", {}).get("rendered", "")) or post.get("slug", "side")
            indhold = post.get("content", {}).get("rendered", "")
            # AdSense må ikke stå på sider uden redaktionelt indhold. Kurv, kasse,
            # min konto, shop og opt-out er funktionssider — de hoppes over, og
            # links til dem peger på det levende site i stedet (se omskriv_links).
            if len(uden_markup(indhold)) < 200:
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

    # Alle billed-URL'er samles først, så de kan hentes én gang for alle sider.
    billeder: set[str] = set()
    for emne in emner:
        for url in re.findall(r'src="(https?://[^"]+)"', emne["indhold"]):
            billeder.add(url)
        for srcset in re.findall(r'srcset="([^"]+)"', emne["indhold"]):
            for del_ in srcset.split(","):
                stykker = del_.strip().split()
                if stykker and stykker[0].startswith("http"):
                    billeder.add(stykker[0])
        for url in re.findall(r'url\((["\']?)(https?://[^)"\']+)\1\)', emne["indhold"]):
            billeder.add(url[1])
    billeder = {u for u in billeder if SITE_HOST in u}
    medier = hent_medier(sorted(billeder))

    top, bund = annonce(SLOT_TOP, "Annonce"), annonce(SLOT_BOTTOM, "Annonce")
    for emne in emner:
        indhold = omskriv_medier(emne["indhold"], medier)
        indhold = omskriv_links(indhold, kort, medier)
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
        "medier": len(medier),
        "sider": [{"fil": e["fil"], "titel": e["titel"], "original": e["link"], "gruppe": e["gruppe"]}
                  for e in emner],
    }, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")

    print(f"Byggede {len(emner)} sider + hub i {OUT.relative_to(ROOT)}/ "
          f"({len(sider)} pages, {len(poster)} posts, {len(medier)} billeder). "
          f"Sprang {len(sprunget)} indholdsløse sider over.")
    return 0


# ---------------------------------------------------------------- kontrol

def referencer(raw: str) -> list[tuple[str, str]]:
    """(attribut, værdi) for href, src, srcset og url(...) i dokumentet."""
    refs: list[tuple[str, str]] = []
    for vaerdi in re.findall(r'href="([^"]*)"', raw):
        refs.append(("href", vaerdi))
    for vaerdi in re.findall(r'src="([^"]*)"', raw):
        refs.append(("src", vaerdi))
    for vaerdi in re.findall(r'srcset="([^"]*)"', raw):
        for del_ in vaerdi.split(","):
            stykker = del_.strip().split()
            if stykker:
                refs.append(("srcset", stykker[0]))
    for _, url in re.findall(r'url\((["\']?)(https?://[^)"\']+)\1\)', raw):
        refs.append(("css-url", url))
    return refs


def se_medie(fil: Path) -> str | None:
    """Fejltekst hvis filen ikke er et billede med kendt signatur."""
    hoved = fil.read_bytes()[:16]
    if not any(hoved.startswith(sig) for sig in MAGIC):
        return f"{fil.name}: ukendt filsignatur"
    return None


def check() -> int:
    fejl: list[str] = []
    manifest_fil = OUT / "manifest.json"
    if not manifest_fil.exists():
        print("Ingen kopi at kontrollere — kør uden --check først.")
        return 1
    manifest = json.loads(manifest_fil.read_text(encoding="utf-8"))
    indekser = bool(manifest.get("indekser"))
    sider = {s["fil"]: s for s in manifest["sider"]}

    html_filer = sorted(OUT.glob("*.html"))
    forventet = len(sider) + 1
    if len(html_filer) != forventet:
        fejl.append(f"forventede {forventet} html-filer, fandt {len(html_filer)}")

    links_i_alt = 0
    lokale_links = 0
    eksterne_links = 0
    hub_raw = (OUT / "index.html").read_text(encoding="utf-8") if (OUT / "index.html").exists() else ""

    for fil in html_filer:
        raw = fil.read_text(encoding="utf-8")
        navn = fil.name

        # 1. Annoncepladser og canonical i hver fil.
        if raw.count('class="adsbygoogle"') < 2:
            fejl.append(f"{navn}: mangler annoncepladser (fandt {raw.count('class=\"adsbygoogle\"')})")
        canonical = re.search(r'<link rel="canonical" href="([^"]+)"', raw)
        if not canonical:
            fejl.append(f"{navn}: mangler canonical")
        elif not canonical.group(1).startswith(SITE):
            fejl.append(f"{navn}: canonical peger ikke på sitet ({canonical.group(1)})")
        if navn in sider and canonical and canonical.group(1) != sider[navn]["original"]:
            fejl.append(f"{navn}: canonical {canonical.group(1)} matcher ikke manifestet")
        robots = re.search(r'<meta name="robots" content="([^"]+)"', raw)
        if not robots:
            fejl.append(f"{navn}: mangler robots-meta")
        elif not indekser and "noindex" not in robots.group(1):
            fejl.append(f"{navn}: noindex mangler")
        if 'rel="stylesheet" href="style.css"' not in raw:
            fejl.append(f"{navn}: mangler stylesheet")

        # 2. Hver reference skal pege på en fil der findes.
        for attribut, vaerdi in referencer(raw):
            links_i_alt += 1
            vaerdi = vaerdi.strip()
            if not vaerdi or vaerdi.startswith(("mailto:", "tel:", "data:", "#")):
                continue
            if vaerdi.startswith(("http://", "https://", "//")):
                dele = urlsplit(vaerdi if not vaerdi.startswith("//") else "https:" + vaerdi)
                if dele.netloc == SITE_HOST:
                    eksterne_links += 1  # bevidst peget på det levende site
                    if attribut in {"src", "srcset", "css-url"}:
                        fejl.append(f"{navn}: {attribut} peger stadig på sitet ({vaerdi})")
                    continue
                eksterne_links += 1
                continue
            if vaerdi.startswith("/"):
                fejl.append(f"{navn}: root-relativ {attribut} kan ikke findes i mappen ({vaerdi})")
                continue
            sti = vaerdi.split("#")[0].split("?")[0]
            maal = (fil.parent / sti).resolve()
            if not maal.exists():
                fejl.append(f"{navn}: {attribut} → {vaerdi} findes ikke")
                continue
            if not str(maal).startswith(str(OUT.resolve())):
                fejl.append(f"{navn}: {attribut} → {vaerdi} peger ud af mappen")
                continue
            lokale_links += 1

        # 3. Hub'en skal linke til alle sider i manifestet.
        if navn == "index.html":
            for side in sider.values():
                if f'href="{side["fil"]}"' not in raw:
                    fejl.append(f"index.html: mangler link til {side['fil']}")

    # 4. Alle medier: findes, ikke tomme, rigtig signatur.
    medie_filer = sorted((OUT / MEDIA).glob("*")) if (OUT / MEDIA).exists() else []
    for fil in medie_filer:
        if fil.stat().st_size == 0:
            fejl.append(f"{MEDIA}/{fil.name}: tom fil")
            continue
        problem = se_medie(fil)
        if problem:
            fejl.append(problem)
    if not medie_filer:
        fejl.append("mappen media/ er tom — billederne blev ikke hentet")

    # 5. Faste filer.
    for fil in ("index.html", "style.css", "ads.txt"):
        if not (OUT / fil).exists():
            fejl.append(f"mangler {fil}")
    if (OUT / "ads.txt").exists() and "google.com, pub-" not in (OUT / "ads.txt").read_text():
        fejl.append("ads.txt mangler google.com-linje")

    if fejl:
        print(f"Kopien er ikke i orden — {len(fejl)} fejl:")
        for punkt in fejl[:25]:
            print(f"  - {punkt}")
        if len(fejl) > 25:
            print(f"  … og {len(fejl) - 25} mere")
        return 1

    print(f"[OK] {len(html_filer)} html-filer: alle har 2 annoncepladser, canonical og robots; "
          f"{lokale_links} lokale referencer peger på filer i mappen, "
          f"{eksterne_links} bevidste links til det levende site; "
          f"{len(medie_filer)} medier i {MEDIA}/ har gyldig signatur.")
    return 0


# ---------------------------------------------------------------- main

def main() -> int:
    parser = argparse.ArgumentParser(description="Selvstændig AdSense-kopi af steamgears.eu.")
    parser.add_argument("--check", action="store_true", help="kontrollér hele den byggede mappe")
    parser.add_argument("--indekser", action="store_true", help="tillad søgemaskine-indeksering")
    args = parser.parse_args()
    return check() if args.check else byg(args.indekser)


if __name__ == "__main__":
    raise SystemExit(main())
