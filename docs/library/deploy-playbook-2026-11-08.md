# Deploy-playbook — den næste side, hurtigere og målt

**Skrevet:** 8/11 2026 · **Målt:** samme dag mod det levende site · **Gælder:** næste side, næste guide, næste
policy-side og næste mu-plugin.

Dette dokument er **den daglige vej for én side**. Det erstatter ikke
[`deploy-roadmap-2026-11-03.md`](deploy-roadmap-2026-11-03.md), der dækker hele sitet i syv faser og 6–9
udviklerdage; det er den gentagne opskrift, der gør den næste side hurtigere end de foregående, fordi hver
fælde der allerede er betalt for, nu står som en regel med en kommando.

---

## 0. Kort version

1. **Et push til `main` deployer ikke sitet.** GitHub ændrer dokumentation, assets og release-zip. WordPress
   ændres over MCP/admin — de to veje er adskilt med vilje (AGENTS §5).
2. Rækkefølgen er fast: **copy i repo → metadata → opret som kladde → preflight på kladden → publicér →
   mål udefra → dokumentér.** Publicér aldrig i samme kald som du opretter.
3. **302 er ikke et bevis.** Et `/go/<id>/`-link skal følges helt til en side, der svarer **200** og bærer
   produktets titel. Målt 8/11: `/go/475/` og `/go/567/` giver pænt **302**, men lander på **404**.
4. **Mål med neutral UA.** Chrome-UA får Simplys WAF-challenge (**454**, 7 296 B, »Checking your browser…«),
   mens neutral UA får siden (**200**). Ellers måler man WAF'en og tror at sitet er nede.
5. En side er først færdig når den står i **sitemappen**, har **ét H1**, **én meta-beskrivelse**, **ét
   OG-billede**, **0 døde links** og en HTML-vægt inden for skabelonens loft.
6. **Blokeret arbejde er ikke fremgang.** Kan et led kun ejeren gøre, står siden som kladde med blokeringen
   navngivet — ikke som »næsten færdig«.

### Tiden det tager

| Situation | Realistisk tid | Hvad der driver tiden |
| --- | --- | --- |
| Copy og assets findes, siden er en ny guide/policy-side | **3–5 timer** | metadata, kladde-preflight, måling, dokumentation |
| Copy skal skrives fra bunden (1 000–1 300 ord) | **1–1,5 dag** | research, priser fra Store API, billeder, FAQ |
| Ny funktion (mu-plugin, skabelon, schema) | **0,5–2 dage** | `php -l`, offline-test, rollback-vej, cache |
| Kun en rettelse i en eksisterende side | **15–45 min** | før/efter-måling og cache-purge |

Tallene er arbejdstid for agenten. Ejerens beslutninger, tokens og *Plugins → Update*-klik kommer oveni og
tælles ikke som agent-tid — de står i §8.

---

## 1. Hvad »deploy« betyder i dette repo

Der er **tre flader**, og de har hver sin vej, sit eget bevis og sin egen rollback. At blande dem er den
dyreste fejl, fordi et grønt GitHub-run kan se ud som en udrulning uden at være det.

De tre flader er: **repoet** (dokumentation, assets og plugin-kilde), **plugin-zip'en** og **selve sitet**.

| Flade | Vej ind | Push gør | Rollback |
| --- | --- | --- | --- |
| **1. Repo** — `docs/`, `reports/`, `css/` | `cloud_git` push til `main` | opdaterer repoet | `git revert` / `git show <sha>:<sti>` |
| **2. Plugin-zip** — `plugins/*.zip` | WordPress henter den fra `main` | gør zip'en tilgængelig | gammel zip fra git-historikken |
| **3. Site** — sider, indlæg, produkter, mu-plugins | MCP, wp-admin eller REST | **intet** | kladde, revision, fil-backup, option |

**Målt 8/11:** de ni workflows i `.github/workflows/` har **ingen** FTP-, rsync-, SSH- eller upload-trin
(`grep -rniE 'ftp|rsync|ssh|scp|deploy'` giver nul hits). Der findes ingen pull-hook i `public_html`, og et
push har aldrig ændret en fil på sitet. Det er ikke en mangel — det er sikkerhedsgrænsen.

### Hvad der kræver et menneske

| Handling | Kan agenten? | Bevis |
| --- | --- | --- |
| Skrive en side som kladde over MCP | Ja, når OAuth er godkendt i sandkassen | [`trust-sider-2026-11-06.md`](trust-sider-2026-11-06.md) |
| Publicere den | Ja, samme vej — men først efter preflight (§4) | samme |
| Skifte plugin-mappen | **Nej**, kun WordPress selv | [`live-7-64-9-2026-11-08.md`](live-7-64-9-2026-11-08.md) |
| Lægge en mu-plugin-fil ind | Ja, via `novamira/execute-php` — med fil-backup først | [`robots-filter-2026-11-08.md`](robots-filter-2026-11-08.md) |
| Rydde W3TC- og Rank Math-cache | Ja, samme vej | [`w3tc-og-valuta-2026-11-08.md`](w3tc-og-valuta-2026-11-08.md) |
| Bestemme CVR, valuta, betaling, fragt | **Nej** — det er ejerens tal og aftaler | `docs/roadmap.md` §4c |

---

## 2. Målt baseline 8/11 2026

Målt med neutral UA (`Mozilla/5.0 (compatible; steamgears-agent)`) og ét request pr. URL. Tallene er
**øjebliksbilledet**, ikke en garanti: cache og minify flytter byte-tallet, så før/efter skal måles i samme
opsætning.

| Side | Status | HTML, bytes | Scripts | H1 |
| --- | ---: | ---: | ---: | ---: |
| `/` | 200 | 75 136 | 27 | 1 |
| `/shop/` | 200 | 269 297 | 38 | 1 |
| `/start-here/` | 200 | 71 722 | 26 | 1 |
| `/shipping/` | 200 | 67 793 | 26 | 1 |
| `/steampunk-corset-guide/` | 200 | 100 091 | 25 | 1 |
| `/steampunk-wall-decor-diy/` | 200 | 102 147 | 25 | 1 |

| Side | Meta-desc, tegn | Canonical | OG-billede | `/go/`-id'er |
| --- | ---: | --- | ---: | ---: |
| `/` | 143 | ja | 1 | 0 |
| `/shop/` | 126 | ja | 1 | 0 |
| `/start-here/` | 153 | ja | 1 | 0 |
| `/shipping/` | 156 | ja | **0** | 0 |
| `/steampunk-corset-guide/` | 146 | ja | 1 | 2 |
| `/steampunk-wall-decor-diy/` | 148 | ja | 1 | 3 |

Alle seks sider har `robots: follow, index`, og **fem af dem** har præcis **ét** `og:image`. Det er den
standard den næste side skal matche — og det er samtidig forklaringen på, at `/shipping/` stikker ud.

**Fundet i målingen:** `/shipping/` (side 1396) har **intet `og:image`**. Den er dermed det ene kendte hul i
rækken, og det er samtidig den billigste rettelse at tage med, når en ny side alligevel røres.

**Sitemappen** svarer 200 og indeholder `post-sitemap.xml` (**34** lokationer) og `page-sitemap.xml`
(**27** lokationer). En guide som `/steampunk-corset-guide/` ligger i **post**-sitemappen, mens
`/shipping/` og `/start-here/` ligger i **page**-sitemappen. Det er ikke kosmetik: det afgør, hvilken fil der
skal kontrolleres efter publicering (§6).

### Lofter for den næste side

| Skabelon | HTML-loft | Script-loft | Kommentar |
| --- | ---: | ---: | --- |
| Tekstside (policy, hub, guide) | **130 KB** | **30** | `/start-here/` og `/shipping/` ligger på 67–72 KB og 26 scripts |
| Liste (shop, kategori) | 290 KB | 45 | `/shop/` ligger på 269 KB og 38 scripts |
| Transaktionsside (kurv, kasse, produkt) | 350 KB | 95–112 | målt i S2.7-arbejdet |

Lofterne er de vedtagne fra [`payload-loft-beslutning-2026-11-07.md`](payload-loft-beslutning-2026-11-07.md);
de er et **gulv målt på det nuværende site**, ikke et ønske. En ny tekstside, der lander over 130 KB eller 30
scripts, har arvet noget, den ikke skulle have — typisk et Woo- eller sporings-script uden betingelse.

---

## 3. Faserne — med porte og bevis

| Fase | Arbejde | Tid | Gate: det der skal være sandt | Bevis |
| --- | --- | ---: | --- | --- |
| 0 | Beslut scope, type og slug; mål »før« | 15 min | Type (post/page) og slug er valgt, og der findes en før-måling | udfyldt skema i §3.0 |
| 1 | Copy og assets i repo | 40–120 min | 0 `[EJER]`-felter, alle priser målt, alle `/go/`-mål publicerede | fil i `docs/library/`, `tools/guide_check.py` |
| 2 | Metadata og schema | 20–30 min | Titel ≤60, desc 120–160, ét fokusord, FAQPage hvis FAQ | felt-tabel i §3.2 |
| 3 | Opret som **kladde** | 10–15 min | HTTP 201/200 med et id; status er `draft` | id noteret |
| 4 | Preflight på kladden | 20 min | 0 FEJL i sidekontrollen (§4) | output gemt |
| 5 | Publicér, ryd cache, sitemap | 10–15 min | Siden er 200 uden `?nocache`, og står i rigtig sitemap | 3 kommandoer |
| 6 | Verificér udefra | 15–20 min | 12/12 checks i §6 | tabel i dokumentet |
| 7 | Dokumentér, PDF, status | 20–30 min | Dokument + PDF + indeksrække + procent | commit på `main` |

**Samlet: 3–5 timer** for en side, hvor copy og assets findes. Uden copy: læg 5–7 timer til.

### 3.0 Fase 0 — beslut og mål før

**Post eller page?** Det er en strukturel beslutning, ikke en smagssag:

| Skal siden … | Vælg | Ligger i | Schema |
| --- | --- | --- | --- |
| svare på et søgespørgsmål og opdateres løbende | **post** (guide) | `post-sitemap.xml` | `Article` + `FAQPage` |
| være en fast indgang, politik eller hub | **page** | `page-sitemap.xml` | `WebPage`, `FAQPage` hvis FAQ |

**Slug valgfrit nu, dyrt senere.** Når siden er publiceret og linket fra header, footer eller guides, kræver
en slug-ændring en 301 fra Redirection — og sitet har **ingen Redirection-gruppe** (målt 2/11). Vælg slug'en
i fase 0, skriv den i arbejdsordren, og rør den ikke efter publicering.

**Før-målingen** skrives ind i dokumentet før noget oprettes:

| Felt | Før | Efter | Krav |
| --- | --- | --- | --- |
| URL og status | | | 200 |
| HTML-bytes | | | ≤ loft for skabelonen |
| Scripts | | | ≤ loft |
| H1 | | | 1 |
| Meta-beskrivelse | | | 120–160 tegn |
| Canonical | | | sig selv |
| OG-billede | | | 1, og ≥ 768 px |
| `/go/`-links | | | alle 302 → 200 |
| Sitemap | | | i post- eller page-sitemap |

### 3.1 Fase 1 — copy og assets

- **Kilden er Markdown i repoet.** Læg copy i `docs/library/<emne>-<dato>.md`, så den kan genbruges, diffs
  og godkendes, før den nogen sinde når WordPress.
- **Ingen `[EJER: …]`-felter i en publiceret side.** Enten er tallet målt og skrevet ind, eller også er
  afsnittet udeladt. En placeholder på live er værre end et manglende afsnit.
- **Priser læses fra Store API**, ikke fra hukommelsen:

  ```bash
  curl -sS 'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=100&_fields=id,name,prices' \
    | python3 -c 'import json,sys; [print(p["id"], p["prices"]["price"], p["name"][:50]) for p in json.load(sys.stdin)]'
  ```

  Fælden: beløbene er **minor units** — `2995` er 29,95. Butikken kører **DKK** siden 8/11, så en gammel
  USD-pris i copy er en fejl, ikke en smagssag.
- **`/go/<id>/` må kun bruges om et produkt, der faktisk er publiceret.** Målt 8/11 giver 21 gamle id'er 404,
  og selv et pænt 302 kan ende på »Page Not Found«. Kontrollér før linket skrives (§4).
- **Billeder:** ≤ 300 KB pr. fil, ≥ 1 000 px bredde, alt-tekst der beskriver motivet — ikke »produktbillede«.
  OG-billedet skal være **mindst 768 px** i den størrelse WordPress leverer; ellers beskæres det væk på
  Facebook og LinkedIn (målt på indlæg 615: 576 × 1024).
- **Ingen direkt `|`-tabeller i `create-post`.** Markdown-tabeller konverteres **ikke** til Gutenberg af
  MCP-værktøjet; de ender som rå `|`-tekst. Skriv lister — eller ret blokken bagefter og læs HTML'en efter.

### 3.2 Fase 2 — metadata og schema

| Felt | Regel | Målt reference |
| --- | --- | --- |
| Rank Math title | ≤ 60 tegn, primært søgeord først | forside og guides ligger på 50–58 |
| Meta description | 120–160 tegn, en konkret gevinst — ikke »læs mere« | `/shipping/` 156, `/start-here/` 153 |
| Fokusnøgleord | ét, og det skal stå i title **og** H1 | `shipping`, `returns`, `steampunk costumes` |
| Robots | `index, follow` — intet `noindex` på en side der skal findes | alle seks målte sider |
| Canonical | sig selv, uden query-parametre | alle seks |
| OG-billede | præcis **ét** `og:image` | `/shipping/` mangler — ret den |

Schema vælges efter indhold, ikke efter skabelon: `FAQPage` kun når spørgsmålene faktisk står synligt på
siden; `Product`/`Offer` hører på produktsider og sættes af `sgap-product-schema.php`, ikke i en side.
Markup skal sige det samme som teksten — en `handlingTime`, der ikke står i copy, er en påstand.

### 3.3 Fase 3 — opret som kladde

Tre veje, i den rækkefølge de er prøvet:

**A. Novamira-ability (foretrukket, når tokenet er der).** Tokenet ligger i
`~/.local/state/novamira/credentials/v1/` (owner-only, uden for repoet) og fornyes af CLI'en selv:

```bash
novamira auth status --json                     # expired? næste kald fornyer
novamira --site steamgears.eu discover --json   # -> credentialState: fresh
novamira --site steamgears.eu --json --yes run miriamschwab/create-post --input @page.json
```

`page.json` bærer `post_type: "page"`, `status: "draft"`, titel, slug, Markdown og Rank Math-felterne
`rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`. Det er den vej
`/shipping/` og `/returns/` blev oprettet ad, og Markdown blev konverteret til native Gutenberg-blokke
server-side.

**B. AI Engine `wp_create_post`** — tilladt af *Drafts Only*, men **kun** med `status: draft`/`pending`;
en publicering i samme kald afvises af gaten. Det er den rigtige spærring, ikke en fejl.

**C. REST med application password** — `/wp-json/wp/v2/pages` med `status: draft`. Kræver `WP_USER` +
`WP_APP_PASSWORD`, som **ikke** findes i dag (målt: 0 repository-secrets). Brug den som reserve, ikke som plan.

Gem **id'et** med det samme. Hver senere fase refererer til id, ikke til slug — en slug kan ændres, et id kan
ikke.

### 3.4 Fase 4 — preflight på kladden

Kladden er den billigste fejlretning: intet er indekseret, intet er linket, ingen kunde ser den. Kør
sidekontrollen i §4 mod **preview-linket** (eller mod den publicerede URL med `?preview=1`), og ret alt rødt
før publicering. De fem ting der oftest fejler:

1. **Markdown-tabel** står som rå pipe-tegn i HTML'en.
2. **`/go/`-link** peger på en kladde eller et slettet produkt — 302 til 404.
3. **To H1** fordi sidetitlen står både i temaets header og i indholdet.
4. **Pladsholder** (`[EJER: …]`, `TODO`, `lorem`) sluppet igennem.
5. **Billede** uden alt-tekst, eller et OG-billede under 768 px.

### 3.5 Fase 5 — publicér, ryd cache, sitemap

Publicér **én** ændring ad gangen, og ryd de tre cache-lag bagefter. Rækkefølgen betyder noget:

```bash
# 1. publicér (samme ability-vej som kladden, nu med status publish)
# 2. ryd W3TC + object cache
novamira --site steamgears.eu --json --yes run novamira/execute-php \
  --input @flush.json     # {"code":"if (function_exists(\"w3tc_flush_all\")) { w3tc_flush_all(); } wp_cache_flush();"}

# 3. Rank Maths sitemap er fil-baseret og ryddes ikke af w3tc_flush_all():
#    slet uploads/rank-math/*.xml og re-save indlægget/siden, ellers mangler den nye URL i sitemappen
```

**Cache-fælden, der kostede en runde 7/11:** efter en rettelse blev den **fatale side** fortsat serveret fra
W3TC, så sitet så nede ud længe efter at fejlen var væk. `w3tc_flush_all()` var det, der vendte forsiden. Ryd
cachen **før** du konkluderer, at en rettelse ikke virkede.

### 3.6 Fase 6 — verificér udefra

Seks ting, i denne rækkefølge, fordi hver af dem kan skjule den foregående:

| # | Check | Krav | Kommando |
| --- | --- | --- | --- |
| 1 | URL uden cache-buster | 200 | `curl -s -o /dev/null -w '%{http_code}\n' <url>` |
| 2 | HTML og titel | rigtig titel, ikke »Page Not Found« | se §4 |
| 3 | H1 og meta | 1 H1, desc 120–160 | §4 |
| 4 | Sitemap | URL'en står i post- eller page-sitemap | `curl -s <sitemap> \| grep -c '<loc>'` |
| 5 | Links | alle interne links 200; `/go/` 302 → 200 | §4 |
| 6 | Billeder | 200 og alt-tekst på alle | §4 |

### 3.7 Fase 7 — dokumentér og meld status

- Skriv et dateret dokument i `docs/library/`, og **skriv det ind i indekset**
  ([`README.md`](README.md)), ellers bliver det forældreløst (AGENTS §5).
- Render PDF til `reports/` og se mindst to sider efter med `pypdfium2` — en PDF der ikke er set på, er ikke
  færdig.
- Meld **Modul / % før / % efter / Δ / Grundlag** som AGENTS §1 kræver, og opdatér
  `projektstatus.json` → `python3 projektstatus.py --update`.
- Commit og push i samme tur. Commit-beskeden skal bære de fem felter, ellers afviser `commit_check.py` den.

---

## 4. Kommandoark — sidekontrol i ét kald

Gem blokken som `sidekontrol.py`, og kør den med stien som argument. Den tjekker status, vægt, H1, scripts,
meta, canonical, robots, OG-billede, alle `/go/`-links (fulgt helt til ende) og de første otte rigtige
billedfiler. Den er **læsende** — den skriver intet på sitet.

```python
import re, sys, urllib.request, urllib.error
from html import unescape

UA = "Mozilla/5.0 (compatible; steamgears-agent)"
BASE = "https://steamgears.eu"
PATH = sys.argv[1] if len(sys.argv) > 1 else "/"

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None

def fetch(url, follow=True):
    opener = urllib.request.build_opener() if follow else urllib.request.build_opener(NoRedirect)
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with opener.open(req, timeout=30) as r:
            return r.status, r.read().decode("utf-8", "replace"), r.geturl()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), url

status, html, final = fetch(BASE + PATH)
title = re.search(r"<title>(.*?)</title>", html, re.S)
desc = re.search(r'<meta\s+name="description"\s+content="([^"]*)"', html, re.I)
canon = re.search(r'<link\s+rel="canonical"\s+href="([^"]*)"', html, re.I)
robots = re.search(r'<meta\s+name="robots"\s+content="([^"]*)"', html, re.I)
og = re.findall(r'<meta\s+property="og:image"\s+content="([^"]*)"', html, re.I)
h1 = len(re.findall(r"<h1\b", html, re.I))
scripts = len(re.findall(r"<script\b", html, re.I))
imgs = re.findall(r"<img\b[^>]*>", html, re.I)
noalt = [i for i in imgs if not re.search(r'\balt="', i, re.I)]  # alt="" er bevidst dekorativt

def line(ok, label, value):
    print(f"  {'OK  ' if ok else 'FEJL'} {label:22} {value}")

print(f"\n{PATH} -> {status}  {len(html)} B  ({final})")
line(status == 200, "status", status)
clean_title = unescape(title.group(1)).strip() if title else ""
line(bool(clean_title) and "Page Not Found" not in clean_title, "titel", clean_title[:70] or "-")
line(h1 == 1, "H1", h1)
line(120 <= len(desc.group(1)) <= 160 if desc else False,
     "meta-desc", f"{len(desc.group(1)) if desc else 0} tegn")
line(bool(canon), "canonical", canon.group(1) if canon else "-")
line(bool(robots) and "noindex" not in (robots.group(1) if robots else ""),
     "robots", robots.group(1) if robots else "-")
line(len(og) == 1, "og:image", f"{len(og)} stk")
line(len(html) <= 130_000, "HTML-loft 130 KB", f"{len(html)} B")
line(scripts <= 30, "script-loft 30", scripts)
line(not noalt, "alt-tekst", f"{len(noalt)} mangler")

ids = sorted(set(re.findall(r"/go/(\d+)/", html)))
print(f"  {'--  '} /go/-links            {len(ids)} unikke")
for gid in ids:
    st, _, loc = fetch(f"{BASE}/go/{gid}/", follow=False)
    fst, _, fend = fetch(loc) if loc else (0, "", "-")
    ok = st == 302 and fst == 200
    tail = fend.replace(BASE, "")
    print(f"  {'OK  ' if ok else 'FEJL'} /go/{gid:<6} {st} -> {fst}  {tail}")

print("  --   billeder (første 8 rigtige filer)")
shown = 0
for tag in imgs:
    # W3TC lazy load: den rigtige fil ligger i data-src, src er en data:-placeholder
    src = re.search(r'data-src="([^"]+)"', tag) or re.search(r'src="([^"]+)"', tag)
    if not src or src.group(1).startswith("data:"):
        continue
    st, _, _ = fetch(src.group(1))
    print(f"  {'OK  ' if st == 200 else 'FEJL'} {st}  {src.group(1).replace(BASE, '')[:90]}")
    shown += 1
    if shown == 8:
        break
```

```bash
python3 sidekontrol.py /den-nye-side/
python3 sidekontrol.py /shipping/        # kendt tilfælde: FEJL på og:image
```

Bemærk: W3TC lazy loader billederne, så den rigtige fil ligger i `data-src` og `src` er en `data:`-placeholder;
scriptet læser `data-src` først. En `<img>` inde i en `noscript`-blok (Pinterest) tælles også med — det er
harmløst, men forklarer en ekstra linje.

### Enkeltkommandoer

```bash
# Er siden i sitemappen? (post- og page-sitemap, 8/11: 34 og 27 lokationer)
curl -s https://steamgears.eu/post-sitemap.xml | grep -c '<loc>'
curl -s https://steamgears.eu/page-sitemap.xml | grep -c '<loc>'

# Følg et /go/-link hele vejen — 302 alene er ikke svar
curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}\n' https://steamgears.eu/go/940/

# Mål én URL som neutral klient, uden cache-buster
curl -s -o /dev/null -w '%{http_code} %{size_download} B\n' -A 'Mozilla/5.0' https://steamgears.eu/ny-side/

# Priser fra den levende butik (minor units: 2995 = 29,95)
curl -s 'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=100&_fields=id,name,prices' | head -c 400

# PHP-syntaks før en mu-plugin lægges ind
tools/_deps/bin/php -l wordpress/mu-plugins/<fil>.php

# PDF-eksport og kontrol af siden
PYTHONPATH=tools/_deps python3 tools/md_to_pdf.py docs/library/<emne>-<dato>.md \
  reports/<emne>-<dato>.pdf --kicker "STEAMGEARS.EU · <EMNE>"

# Repoets værn, før push
python3 projektstatus.py --check && bash tools/natlige-vaern.sh
```

---

## 5. Definition of Done

En side er **færdig** når alle femten punkter er sande. De er skrevet, så de kan tjekkes maskinelt med §4.

| # | Krav | Tærskel |
| --- | --- | --- |
| 1 | URL svarer 200 uden cache-buster | 200 |
| 2 | Titel er sidens egen | ikke »Page Not Found« |
| 3 | Præcis ét H1 | 1 |
| 4 | Meta-beskrivelse | 120–160 tegn |
| 5 | Canonical peger på siden selv | 1 |
| 6 | Robots | `index, follow` |
| 7 | Præcis ét OG-billede | 1, ≥ 768 px |
| 8 | HTML under skabelonens loft | ≤ 130 / 290 / 350 KB |
| 9 | Scripts under loftet | ≤ 30 / 45 / 95 |
| 10 | Alle `/go/`-links | 302 → 200 med produktets titel |
| 11 | Alle interne links | 200 |
| 12 | Alle billeder | 200 + alt-tekst |
| 13 | Ingen pladsholdere | 0 forekomster af `[EJER`, `TODO`, `lorem` |
| 14 | Står i rigtig sitemap | post eller page |
| 15 | Dokumenteret og pushet | dokument + PDF + indeks + `main` |

---

## 6. Fældekatalog — det der allerede er betalt for

Hver række er en fejl der kostede en runde, med det værn der forhindrer gentagelsen.

| Symptom | Årsag | Regel / værn |
| --- | --- | --- |
| Push til `main` ændrede intet på sitet | Repoet er dokumentation, ikke live-koden | Deploy = MCP/admin; push = dokumentation (§1) |
| Hele sitet 500/193 bytes, også `/wp-json` og wp-admin | En mu-plugin med `$output` i en **dobbeltciteret** PHP-streng fik en parse-fejl; `E_COMPILE_ERROR` på hvert request | Skriv filer med single-quoted/nowdoc, `php -l` først, og læg aldrig PHP i en citeret streng uden escape |
| Rettelsen virkede ikke — siden stadig 500 | W3TC serverede den **cachede fatale side** | `w3tc_flush_all()` + `wp_cache_flush()` før konklusion |
| `/` svarer 200, men `/wp-json/` og kurv/kasse er nede | En forkert klassekonstant (`SGAP_Admin::NS`) kastede i `rest_api_init` | `fejlscan`-kontrollen `class-konstanter` + smoke-check af `routes()` |
| 200 med »product not found« | WordPress' **soft-404** | Verificér på titel/indhold, ikke statuskode |
| `/go/475/` giver 302 — »så virker linket« | Målet er en kladde/slettet vare; 302 → **404** | Følg redirecten til 200 med produktets titel (§4) |
| Chrome-UA får 454/7 296 B »Checking your browser…« | Simplys WAF | Neutral UA til måling; en 454/455 er WAF, ikke et sitemål |
| Ny URL mangler i sitemappen | Rank Maths **fil-baserede** sitemap-cache i `uploads/rank-math/` | Slet `*.xml` + re-save, derefter optælling |
| Markdown-tabel står som rå pipe-tegn | `create-post` konverterer ikke tabeller | Skriv lister, eller ret blokken og læs HTML'en |
| To H1 på siden | Titlen står både i temaheader og indhold | Tæl `<h1>` i HTML, ikke i editoren |
| OG-kortet beskæres eller mangler | OG-billede under 768 px, eller `og:image` helt fraværende (`/shipping/` 8/11) | Ét OG-billede, ≥ 768 px, kontrolleret i `<head>` |
| Siden er dobbelt så tung som søstrene | Et Woo-/sporings-script enqueued uden betingelse | Sammenlign med loftet i §2 og fjern kilden |
| Plugin-mappen kan ikke skiftes | Kun WordPress selv må skifte den | Ejerens *Plugins → Update*, eller `Plugin_Upgrader` via `execute-php` |
| Self-updater installerer noget andet end repoet | Zip'en svarer ikke til kilden | Én zip i `plugins/`, byte-identisk, `Version:` = `SGAP_VERSION` |
| Gammel plugin-version kan ikke gendannes | Der ligger kun én zip (CI-regel) | `git show <sha>^:plugins/<zip>` → upload; læg den **ikke** tilbage i `plugins/` |
| En skrivning »lykkedes« uden at ændre noget | *Drafts Only* afviste den, eller tokenet manglede | Læs svaret i databasen/HTML bagefter, ikke i UI'et |
| Prisen er 100× for stor | Store API bruger **minor units** | `2995` = 29,95; butikken er i DKK |
| Et link ingen kunne måle blev publiceret | Produkt-URL uden pænt permalink kan ikke verificeres fra sandkassen | »Et umålt link hører ikke i en publiceret side« |

---

## 7. Rollback pr. flade

| Flade | Handling | Kommando / sted |
| --- | --- | --- |
| Side publiceret ved en fejl | Sæt til `draft` (bevarer id, links og revisioner) | `wp_update_post` med `status: draft` |
| Side med ødelagt indhold | Gendan seneste gode revision | `wp_get_revisions` → `wp_restore_revision` |
| Side helt forkert | Til papirkurv (30 dage), ikke hard delete | `wp_delete_post` uden `force` |
| mu-plugin | Slet filen, eller genskab backup fra `wp-content/novamira-sandbox/` | File Manager eller `execute-php` |
| Plugin | Gammel zip fra git-historikken uploades i wp-admin | se fældetabellen |
| Cache | W3TC + object cache + Rank Math-sitemapfiler | `w3tc_flush_all()`, `wp_cache_flush()`, slet `uploads/rank-math/*.xml` |
| Option-indstilling | Gem den gamle serialiserede værdi **før** skrivning | `wp_get_option` med `raw: true` |

**Reglen der gør rollback billig:** gem den gamle værdi, før du skriver den nye. Det gælder options, filer
og indhold — og det er grunden til, at `execute-php`-kald læser `before` og skriver `after` i sit svar.

---

## 8. Ejerporte — det agenten ikke kan gøre

Målt 8/11. Intet af det må gættes eller opfindes, og en side der afhænger af et af punkterne bliver **kladde**
med blokeringen navngivet.

| Port | Hvad der mangler | Konsekvens for den næste side |
| --- | --- | --- |
| CVR og telefon | Complianz har navn, adresse, e-mail og DK — men tom telefon og ingen CVR | GPSR/ansvarlig-afsnit kan ikke skrives færdigt |
| Valuta/priser | DKK er sat 8/11; en EUR-visning kræver en currency-switcher, som ikke er aktiv | ingen EUR-priser i copy |
| Betaling | Kun Stripe og bankoverførsel | en side må ikke love MobilePay/PayPal |
| Forsendelse | Kun Denmark har en metode; EU/UK/RoW har ingen zone | ingen leveringsløfte ud over `/shipping/` og `/delivery/` |
| Printful og mockups | Intet token; 20 publicerede produkter med 22 billeder, alle under 3 | produktside kan ikke få flere mockups herfra |
| Kanal-tokens | Pinterest mangler `boards:read`; Instagram, Bluesky, Mastodon, Telegram og Facebook mangler token | kanalmåling står `blokeret`, ikke `bestået` |
| GSC | Crawl stats giver 401 fra sandkassen | indeksering kan ikke bekræftes i GSC, kun i sitemap og HTML |
| *Plugins → Update* | Kræver et menneske (eller `Plugin_Upgrader` med token) | en release er ikke live før klikket |

---

## 9. Arbejdsordre — kopiér og udfyld

```text
SIDE
  Titel:            <60 tegn>
  Slug:             /<slug>/            (låses i fase 0)
  Type:             post | page
  Sitemap:          post | page
  Fokusnøgleord:    <ét>
  Meta-beskrivelse: <120-160 tegn>

INDHOLD
  Copy (repo):      docs/library/<emne>-<dato>.md
  Ord:              <antal>
  FAQ:              ja/nej   (FAQPage kun hvis synligt)
  /go/-id'er:       <id, id, id>    alle målt 302 -> 200
  Billeder:         <fil, px, KB, alt-tekst>
  OG-billede:       <fil, px>

FØR / EFTER
  status:           ___ -> ___
  HTML:             ___ B -> ___ B     (loft ____)
  scripts:          ___ -> ___         (loft ____)
  H1:               ___ -> ___
  meta-desc:        ___ -> ___ tegn
  /go/ 302 -> 200:  ___/___ -> ___/___
  sitemap:          nej/ja -> nej/ja
  cache ryddet:     ja/nej
  dokument + PDF:   ja/nej
  procent:          __ % -> __ % (grundlag: ________)
  blokeret af:      <ejerport eller »ingen«>
```

---

## 10. Hvad der blev målt i dette pas

| Måling | Værdi | Metode |
| --- | --- | --- |
| Seks sider: status, bytes, scripts, H1, meta, canonical, robots, OG | se §2 | neutral UA, ét request pr. URL, 8/11 2026 |
| `sitemap_index.xml` | 200, 1 190 B | neutral UA |
| `post-sitemap.xml` / `page-sitemap.xml` | 34 / 27 lokationer | `<loc>`-optælling |
| `/go/475/`, `/go/567/` | 302 → **404** (»Page Not Found«) | redirect fulgt med neutral UA |
| `/go/940/`, `/go/941/`, `/go/966/` | 302 → 200 med produkttitel | samme |
| Chrome-UA mod en produktside | **454**, 7 296 B challenge | WAF, ikke sitemål |
| `/shipping/` | 200, 67 793 B, **0 og:image** | sidekontrol |

### Kilder

- [`deploy-roadmap-2026-11-03.md`](deploy-roadmap-2026-11-03.md) — den overordnede syv-fase-plan for hele sitet
- [`trust-sider-2026-11-06.md`](trust-sider-2026-11-06.md) — den prøvede vej: kladde → kontrol → publicering
- [`mcp-skrivevej-novamira-cli-2026-11-08.md`](mcp-skrivevej-novamira-cli-2026-11-08.md) — token, `--yes`, `execute-php`
- [`robots-og-nedbrud-2026-11-07.md`](robots-og-nedbrud-2026-11-07.md) — parse-fejlen, cachen og de tre genopretninger
- [`genaktivering-og-oprydning-2026-11-07.md`](genaktivering-og-oprydning-2026-11-07.md) — 500 vs. 200 og cache-fælden
- [`tre-saeson-guides-2026-11-08.md`](tre-saeson-guides-2026-11-08.md) — sitemap-filcachen og guide-publiceringen
- [`w3tc-og-valuta-2026-11-08.md`](w3tc-og-valuta-2026-11-08.md) — cache/minify og DKK-omlægningen
- [`rollback-til-7-64-6-2026-11-08.md`](rollback-til-7-64-6-2026-11-08.md) — rollback fra git-historikken
- [`payload-loft-beslutning-2026-11-07.md`](payload-loft-beslutning-2026-11-07.md) — lofterne pr. skabelon
- [`s15-produktlink-daekning-2026-11-08.md`](s15-produktlink-daekning-2026-11-08.md) — hvorfor et 302 ikke er nok
