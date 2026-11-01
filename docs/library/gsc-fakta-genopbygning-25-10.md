# GSC Fakta-dokument: steamgears.eu efter genopbygningen (23/10 2026)

**Trukket:** 25/10 2026 via Google Search Console API (searchconsole.googleapis.com)
**Property:** `https://steamgears.eu/` (URL-prefix, permissionLevel: siteOwner)
**Perioder:** Daglige tal 15/10–23/10; side- og forespørgelses-opdeling 23/10–24/10
**OBS:** GSC API-data har typisk 1–2 dages forsinkelse — 24/10 og 25/10 er endnu ikke færdige i API'et. Datoerne nedenfor er Googles egne tal, ikke estimater.

---

## 1. Daglige tal (klik / visninger / CTR / gennemsnitsposition)

| Dato | Klik | Visninger | CTR | Position |
|------|------|-----------|-----|----------|
| 15/10 | 0 | 0 | 0% | – |
| 16/10 | 0 | 0 | 0% | – |
| 17/10 | 1 | 9 | 11,11% | 10,33 |
| 18/10 | 1 | 15 | 6,67% | 10,67 |
| 19/10 | 3 | 28 | 10,71% | 7,11 |
| 20/10 | 0 | 25 | 0% | 10,68 |
| 21/10 | 0 | 19 | 0% | 14,21 |
| 22/10 | 0 | 21 | 0% | 12,05 |
| 23/10 (genopbygning) | 4 | 37 | 10,81% | 12,19 |

**Faktapunkter:**
- 15–16/10: nul visninger (første synlige aktivitet 17/10).
- Bedste dag målt på klik: 23/10 (4 klik, 37 visninger) — genopbygningsdagen.
- Bedste dag målt på visninger: 19/10 (28) og 23/10 (37).
- Positionen falder (bliver dårligere) fra 19/10 (7,11) til 21/10 (14,21) og stabiliserer ~12.

## 2. Sider 23/10–24/10 (efter genopbygningen)

| URL | Klik | Visninger | CTR | Position |
|-----|------|-----------|-----|----------|
| /diy-steampunk-goggles/ | 3 | 13 | 23,08% | 5,85 |
| /steampunk-costume-ideas/ | 1 | 13 | 7,69% | 12,62 |
| / (forside) | 0 | 3 | 0% | 14,33 |
| /how-to-make-a-steampunk-costume/ | 0 | 5 | 0% | 10,20 |
| /steampunk-accessories/ | 0 | 4 | 0% | 29,00 |
| /steampunk-cosplay-on-a-budget/ | 0 | 2 | 0% | 7,50 |
| /about/ | 0 | 1 | 0% | 3,00 |
| /casual-steampunk-outfits/ | 0 | 1 | 0% | 6,00 |
| /diy-vs-buy-steampunk-goggles/ | 0 | 1 | 0% | 7,00 |
| /steampunk-cosplay-for-beginners/ | 0 | 1 | 0% | 6,00 |
| /product-category/hoodies/ | 0 | 1 | 0% | 8,00 |
| /shop/ | 0 | 1 | 0% | 10,00 |

12 forskellige URL'er fik visninger i perioden. Alle 4 klik i perioden gik til de to goggle/costume-guides.

## 3. Forespørgsler 23/10–24/10

| Query | Klik | Visninger | Position |
|-------|------|-----------|----------|
| steampunk costume ideas | 0 | 7 | 10,00 |
| steampunk gears | 0 | 3 | 47,00 |
| steam punk costume ideas | 0 | 1 | 10,00 |
| steampunk costume female diy | 0 | 1 | 25,00 |
| steampunk hoodie | 0 | 1 | 8,00 |

## 4. Sitemaps (stand pr. 25/10)

**Indeksfil:** `https://steamgears.eu/sitemap_index.xml`
- 116 web-URL'er indsendt, 0 indexeret (pr. API)
- 84 billede-URL'er indsendt, 0 indexeret
- 0 fejl, 0 advarsler, isPending: false
- Sidst indsendt: 24/10 01:20 UTC · Sidst hentet af Google: 24/10 01:20 UTC

**Undersitemaps:**

| Sitemap | Web indsendt | Billeder indsendt | Sidst hentet |
|---------|-------------|-------------------|--------------|
| product-sitemap.xml | 79 | 78 | 24/10 00:42 |
| page-sitemap.xml | 14 | 6 | 24/10 00:40 |
| post-sitemap.xml | 10 | – | 24/10 00:46 |
| product_cat-sitemap.xml | 7 | – | 24/10 00:43 |
| category-sitemap.xml | 4 | – | 24/10 01:22 |
| author-sitemap.xml | 2 | – | 23/10 10:59 |

Alle: 0 fejl, 0 advarsler. Sum: **116 web-URL'er**.

**Anden indsendelse:** `https://steamgears.eu/sitemap.xml` — indsendt 18/10 12:31, isPending: **true** (Google har endnu ikke behandlet den; sitemap_index.xml er den aktive).

**Vigtig fakta-kontekst:** "0 indexeret" i sitemap-metadatatallet er kendt for at hænge 3–7 dage bagefter. De nul-tal skal derfor ikke læses som "intet er indexeret" — URL-inspektionerne nedenfor er det præcise holdepunkt.

## 5. URL-inspektion (indekseringsstatus pr. 25/10)

| URL | Verdict | Coverage | Sidst crawlet |
|-----|---------|----------|---------------|
| / (forside) | PASS | **Submitted and indexed** (MOBILE) | 21/10 09:30 UTC |
| /diy-steampunk-goggles/ | PASS | **Submitted and indexed** (MOBILE) | 23/10 23:26 UTC |
| /shop/ | PASS | **Submitted and indexed** | 17/10 22:49 UTC |
| /steampunk-costumes/ | NEUTRAL | **Discovered – currently not indexed** | – |

- Forsiden: googleCanonical = userCanonical = https://steamgears.eu/ (ingen canonical-konflikt). Robots.txt ALLOWED, fetch SUCCESSFUL.
- /diy-steampunk-goggles/ blev crawlet **efter** genopbygningen (23/10 kl. 23:26) og er indexeret — Google har altså gen-crawlet og gen-indekseret mindst én guide post-rebuild.
- /shop/: indexeret, men sidst crawlet 17/10 (før genopbygningen) — Google har ikke gencrawlet shop siden da.
- **/steampunk-costumes/ (hub-siden) er IKKE indexeret endnu** — kun "discovered". Det er den eneste af de 4 kontrollerede, der ikke er indeks.
- Bemærk: /steampunk-costumes/ fik ingen visninger i GSC-dataene — konsistent med manglende indeksering.

## 6. Resumé til cross-check

1. 116 web-URL'er i sitemap (79 produkter, 14 sider, 10 posts, 7 produktkategorier, 4 kategorier, 2 forfattere).
2. 4 nøgle-URL'er kontrolleret: 3 indexeret, 1 (hub /steampunk-costumes/) ikke.
3. Trafikniveau post-rebuild: 37 visninger / 4 klik 23/10 — små tal, ingen datamængde til trendkonklusioner endnu.
4. Mest synlige indhold: goggle-guiden (position ~5,9, CTR 23%) og costume-ideas-guiden (~12,6).
5. Sitemap-metadatatallet "0 indexeret" er forsinket — brug URL-inspektion som sandhedskilde.
6. /shop/ og resten af sidemappe-sitet afhænger af Google's gencrawl; kun forsiden + goggles er bekræftet gen-crawlet efter 23/10.

*Rå API-responses arkiveret i denne fil uden ændringer; CTR og position er impression-vægtede gennemsnit pr. GSC-definition.*
