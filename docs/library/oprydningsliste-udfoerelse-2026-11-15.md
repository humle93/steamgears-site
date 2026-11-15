# Oprydningslisten 15/11 — udført så langt adgangen rækker

**Kilde:** [Steamgears.eu – Oprydnings- og Fejlrettelsesliste (2026-11-15)](https://docs.google.com/document/d/1new93jBcR1kQqmurn2LnKAi7NHqtYflo2SerRTKIRFs/edit)
i Drive-mappen *Steamgears → 02 Images & Media* (oprettet 15/11 11:51, sidst rettet 12:08).

**Status for dette pas:** punkt 1–7 kan **ikke** udføres herfra — de kræver WordPress-skriveadgang, og
denne sandkasse har intet MCP-token (målt, se §1). Det der **kunne** gøres er gjort: hvert punkt er målt mod
det levende site, så listen nu siger hvad der stadig er sandt, hvad der allerede er løst, og hvad der ikke
kan afgøres uden wp-admin. Fire af de otte afsnit viser sig at bygge på en katalogtilstand der ikke findes
længere.

**Blokeret arbejde tæller ikke som fremgang** (`AGENTS.md` §1). Derfor er der ingen procent flyttet: `site`
står stadig 100 % (20,95 af 20,95 d, verified) og ingen historik-række er skrevet.

---

## 1. Hvad der blokerer, målt

| Kontrol | Resultat 15/11 |
| --- | --- |
| `env` med `MCP*`, `WP_*`, `NOVAMIRA*`, `PUBLER*` | **ingen** variabler sat |
| `.env` i worktreen | findes ikke |
| `~/.config/steamgears-mcp` (OAuth-token) | findes ikke |
| `python3 tools/mcp_oauth.py --status` | »Ingen klient registreret endnu — kør `--start`« |
| WooCommerce REST-nøgler (Consumer key/secret) | findes ikke — kun **læsende** Store API virker |

Uden et token kan agenten læse sitet (Store API, HTML, sitemaps) men ikke skrive: ingen produktstatus,
ingen kategori- eller redirect-ændring, ingen Complianz-tekst, ingen webhook-sletning, ingen
Media Library-upload. `docs/agent-profil.md` §4 siger det samme: hver ny sandkasse kræver **ejerens
browsergodkendelse** af OAuth, og credential ligger uden for repoet.

Det er den ene ting der skal på plads, før resten kan køres: `python3 tools/mcp_oauth.py --start`, og ejeren
godkender i browseren. Derefter kan `tools/mcp_rest.py --check` bevise at fladen svarer.

## 2. Den målte tilstand 15/11 (læsende kald, ingen credential)

| Måling | Resultat | Metode |
| --- | --- | --- |
| Publicerede produkter | **14** | `wc/store/v1/products?per_page=100` |
| Shop-sidens eget tal | »Showing all **14** results« | `/shop/` |
| REST (`wp/v2/product`) | `x-wp-total: 14` | `/wp-json/wp/v2/product?per_page=1` |
| Kategorier | **16** | `wc/store/v1/products/categories` |
| Produkttyper | 8 variable, 6 simple | Store API |
| Valuta og pris | **DKK**, 530,00 kr / 330,00 kr | `prices` pr. produkt |
| Analytics i head | **`GT-PL9L67B4`** (Google tag) + **`AW-18518250875`** (Ads) | `googletagmanager.com/gtag/js?id=…` |
| Produkt-JSON-LD | `Product` + `Offer` + `Brand` + `OfferShippingDetails` + `MerchantReturnPolicy` | `/product/astrolabe-planisphere-tee/` |
| Guide-JSON-LD | `BlogPosting` + `FAQPage` + `BreadcrumbList`, **ingen `HowToStep`** | `/steampunk-utility-belt/`, `/steampunk-wall-decor-diy/` |
| Cookie-bannerets tekst | den korrekte engelske standardtekst, ord for ord | forsidens HTML, `cmplz`-markup |
| Footer | »© 2026 Steamgears · Steampunk cosplay guides & gear · steamgears.eu« | forsidens HTML |
| Forsidens H1/H2 | *Steampunk Posters, Prints & Apparel* · *Three Ways In* · *Selected Works* · *Make the Rest Yourself* | forsidens HTML |

## 3. Punkt for punkt

| # | Planens punkt | Målt 15/11 | Status |
| --- | --- | --- | --- |
| 1a | Slåfejl i cookie-banneret (»we te technologies«, »soch as beowsing«) | Teksten står ordret som den korrekte standardtekst; `we te `, `soch`, `beowsing` = **0 træf** | **Løst allerede** |
| 1b | Footer-copyright | »© 2026 Steamgears · Steampunk cosplay guides & gear · steamgears.eu« | **Løst allerede** (planen angav intet mål) |
| 1c | »thrin-store« → »thrift-store« | `thrin` = **0 træf** på forsiden | **Udført / findes ikke** |
| 1d | Dansk »Pirat Piger« i engelsk tekst | `Pirat` = **0 træf** | **Udført / findes ikke** |
| 2 | 74 i REST mod 83 i frontend | **14 = 14 = 14** (Store API, shop, REST) | **Præmis forældet** — ingen afvigelse at afstemme |
| 3a | v5-mockups er PNG med `.jpg`-endelse | **Bekræftet i Drive:** alle 10 `v5-*-mock.jpg` har MIME `image/png`, 1,3–1,8 MB | **Stadig gældende** (kildefiler, intet på sitet) |
| 3b | WebP/lazy loading på guide-billeder | Ikke målt — kræver sidens `<img>`-markup pr. guide og Media Library | **Kan ikke afgøres herfra** |
| 4a | Dobbelt GA4 (`G-3MYLFKY8MY` + `G-GE85RJJKD1`) | Ingen af de to id'er i HTML; kun `GT-PL9L67B4` + `AW-18518250875` | **Løst 7/11** (se `analytics-dobbelt-2026-11-06.md`) |
| 4b | Produkter mangler `Product`/`Offer` | Komplet schema med pris, lager, DKK, fragt og retur på det målte produkt | **Løst for produkter** |
| 4b | Guides mangler `HowTo` | 0 `HowToStep` på to målte guides (`BlogPosting` + `FAQPage` findes) | **Stadig gældende** |
| 4c | Ingen interne links guides → shop | 0 direkte `/product/`-links, men **3** hhv. **1** `/go/`-link pr. guide | **Delvist gældende** — tynd kobling, ikke nul |
| 5 | Gamle Juno-webhooks og `_webhook_*`-postmeta | Kræver wp-admin/WP-CLI | **Blokeret af credential** |
| 6 | 83 produkter, 57 tøj, 37 kategorier → 50 plakater i 6 kategorier | **14** produkter, **16** kategorier: 4 T-shirts, 4 Hoodies, 1 Phone Case, 2 Jewelry, 3 Posters & Wall Art | **Præmis forældet** — beslutningen skal træffes om 14 varer, ikke 83 |
| 7a | »Choose Your Path« promoverer corsets/t-shirts | Sektionen findes ikke; forsiden siger *Steampunk Posters, Prints & Apparel* | **Udført / findes ikke** |
| 7b | Afskåret logo på mobil (STEAMGEA/STEAMGEARE) | Ikke målt — kræver rendering ved 360–412 px | **Kan ikke afgøres herfra** |
| 7c | Cookie-banner dækker hero på desktop | Ikke målt — kræver rendering | **Kan ikke afgøres herfra** |
| 7d | »$26.95« ligner »826.95« | Butikken kører **DKK** (»530,00 kr.«) | **Præmis forældet** |
| 8 | Tre Python-scripts | Findes **ikke** i `tools/` og intet sted i repoet; de kræver desuden WooCommerce REST-nøgler | **Ikke udført** |

**Opsummering:** 6 punkter er allerede i orden eller bygger på forældede tal; 3 er stadig gældende
(3a, 4b-HowTo, 4c); 2 kan ikke afgøres uden rendering; 1 er blokeret af credential; og §6 er en
forretningsbeslutning, ikke en rettelse.

## 4. Den præcise arbejdsordre, når tokenet findes

Rækkefølgen er valgt så intet trin kan gøre skade før det næste er målt. Hvert trin har en kontrol der
beviser resultatet, og en vej tilbage.

1. **Læs først, skriv bagefter.** `tools/mcp_rest.py --check` (initialize + `tools/list`) og
   `tools/live_state.py` — så er fladen og plugin-versionen dokumenteret før en enkelt ændring.
2. **Webhooks (§5).** Læs `wc_get_webhooks()`; slet kun dem der ikke peger på Command Center. Kontrol:
   listen er tom eller kun Ultra.cc; ordreflowet uændret. Ingen `wp_postmeta`-oprydning i samme trin —
   den rører produktdata og skal have egen kontrol.
3. **HowTo-schema (§4b).** Kun på guides der faktisk er trin-for-trin. Kontrol: Rich Results-test på to
   URL'er, og `FAQPage` stadig intakt.
4. **Guidenes produktkobling (§4c).** 3 faste callouts pr. guide mod de 6 kollektioner der findes.
   Kontrol: `tools/guide_check.py` — 0 døde `/go/`-id'er, 0 valutaafvigelser.
5. **Billederne (§3a).** Konvertér de 10 mockups til ægte JPG/WebP < 245 KB med `#0D0D0D` baggrund, først
   derefter upload til Media Library. Kontrol: MIME matcher endelsen, sidevægt falder. Intet af det rører
   et publiceret produkt, fordi ingen af v5-motiverne er live.
6. **Posters-only (§6) — beslutning først.** Med 14 varer betyder »sæt tøj på draft« at 9 af 14 forsvinder
   fra shoppen. Det er en ejerbeslutning, ikke en oprydning; den skal træffes med antal og omsætning foran
   sig, og med 301-redirects skrevet ned **før** kategorierne skjules.
7. **Til sidst, hvis stadig ønsket:** mobilheader, cookie-bannerets placering og prismærket — alle tre
   kræver en rendering ved 360 px og 1 280 px før og efter, og de er kosmetiske.

## 5. Fælder målt i dette pas

- **WAF'en svarer 454 på en browser-UA.** `curl` med `Mozilla/5.0 … Chrome/120` gav `454 Checking your
  browser` på både Store API og HTML. Uden `-A` svarer de samme URL'er 200. Efterlign ikke en browser —
  det er præcis det WAF-reglen fanger (`waf-diagnose-2026-11-02.md`).
- **`/?p=<id>` giver 301.** Produktets schema kan kun læses med `curl -L`, ellers måler man en tom krop.
- **`/guides/` findes ikke (404).** Guiderne ligger som posts; brug `post-sitemap.xml`.
- **Planens katalogtal er fra en anden tilstand af butikken.** 83 mod 74 produkter, 57 tøj og 37 kategorier
  kan ikke genfindes: butikken har 14 varer i 16 kategorier. En oprydning efter de tal ville ramme
  forkerte varer.
- **`wp-sitemap.xml` er en indeksfil.** Antal URL'er skal tælles i undersitemapsne, ikke i indekset.

## 6. Sådan gentages målingen

```bash
# produkter, kategorier, priser (læsende, ingen credential, ingen browser-UA)
curl -s 'https://steamgears.eu/wp-json/wc/store/v1/products?per_page=100&_fields=id,name,type,categories,prices'
curl -s 'https://steamgears.eu/wp-json/wc/store/v1/products/categories?per_page=100&_fields=id,name,count'
curl -sL 'https://steamgears.eu/wp-json/wp/v2/product?per_page=1' -D- -o /dev/null | grep -i x-wp-total

# schema og analytics på én URL
curl -sL 'https://steamgears.eu/product/astrolabe-planisphere-tee/' | grep -oE '"@type":"[A-Za-z]+"' | sort | uniq -c
curl -s 'https://steamgears.eu/' | grep -oE 'gtag/js\?id=[A-Za-z0-9-]+'
```
