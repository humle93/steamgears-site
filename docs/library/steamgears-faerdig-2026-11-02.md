# Steamgears.eu — vejen til færdig: status 2/11 og resten

Afløser den løbende status i [site-roadmap-2026-11-01.md](../site-roadmap-2026-11-01.md) for de punkter der er ændret, og samler det der stadig står mellem butikken og "klar til at tage imod fremmede kunders penge". Alt nedenfor er målt 2/11 2026 mod den levende side, ikke citeret fra 1/11-auditerne.

## 1. Hvad der er lukket siden 1/11

| Punkt | 1/11 | 2/11 (verificeret) |
|---|---|---|
| Døde interne links | 39 forekomster på 15 sider | **0** — 124 interne mål crawlet fra de 17 berørte sider, alle svarer med rigtigt indhold |
| `/start-here/` | 404 | **200**, 8.439 ord, 65 interne links |
| `/build/` | 404 | **200**, 8.271 ord, 48 interne links |
| Stripe-nøgle over offentlig REST | `sk_live_` i klartekst | **401** — endpointet er lukket |
| Cache af AI Engine-markup | `mwai-` i HTML | væk |
| Sitemap-URL'er | — | **121/121 lever** |

De 39 døde links er altså ordnet, og to af de fem manglende hubs er bygget for alvor. Det ændrer prioriteringen: S0.4 er ikke længere den største strukturelle mangel.

## 2. Nyt fund: siden udleverer 404 som 200

Det vigtigste fund i dag, og det forklarer hvorfor auditeringerne ikke stemmer overens.

**Reproduktion:** fem URL'er der aldrig har været set før (`/soft404-test-<tilfældig>/`) svarer alle `404` ved første kald. Tre sekunder senere svarer de samme fem `200` med temaets "Page Not Found"-skabelon (`<body class="error404">`). Samme mønster på rigtige URL'er: `/inspiration/` svarede 404 kl. 03:2x og 200 kl. 03:3x.

**Hvad det betyder:**

- Enhver statusbaseret linkaudit rapporterer "alt er fint" for hver URL nogen — eller en bot — har rørt én gang. Det er derfor 1/11-auditen og dagens første crawl var uenige.
- Google ser et uendeligt antal 200-sider med titlen "Page Not Found". Det er soft-404 i den kategori søgemaskiner straffer, og det koster crawl-budget på en side med 121 rigtige URL'er.
- Effekten er selvforstærkende: hvert bot-forsøg på `/product/<gæt>/` laver endnu en 200-side.

**Fix:** slå "cache 404-sider" fra i W3TC (Page Cache → Advanced) eller ekskludér 404 i temaets/hostens cache, og purge. Det er en indstilling, ikke kode.

**Verifikation bagefter:** `python3 tools/audit/link_check.py /ny-tilfaeldig-url/` skal svare 404 to gange i træk. Indtil da er alle statuskoder på siten upålidelige, og det er grunden til at `tools/audit/link_check.py` sender en frisk query-parameter og dømmer på indhold i stedet for status.

## 3. Hvad der blev lavet i dag

| Leverance | Fil | Effekt |
|---|---|---|
| Soft-404-bevidst link-tjek | `tools/audit/link_check.py` | Fanger det statuskoder skjuler. Kan køre som månedlig rutine og fejler med exit 1 |
| Redirect-kort, verificeret | `deploy/steamgears/redirects.json` + `redirects.csv` | 9 rækker, alle kilder døde og alle mål levende ved kørsel. Kan importeres i Redirection uden en gruppe — hvilket var det der blokerede S0.4 |
| Redirect-bygger | `tools/deploy/build_redirects.py --verify` | Validerer rækker og tjekker dem live; fejler hvis en kilde lever eller et mål er dødt |
| Punk-lancering, klar til MCP | `deploy/steamgears/` + `assets/punk/` | 3 kollektioner klar, 6 kladser med tekst, 25 mockup-pladser og medie-upload testet |
| Punk-tekster og upload-plan | [punk-lancering](punk-lancering-2026-11-02.md), [punk-upload](punk-upload-2026-11-02.md) | Seks produkter kan oprettes som kladde så snart credentials og de fem tekstblokke er der |
| `/inspiration/` som kladde | `deploy/steamgears/pages/inspiration.json` + `pages-manifest.json` | 803 ord, 34 interne links, alle verificeret levende. Den sidste af de tre hubs |

De ni redirects dækker: `/inspiration/`, `/shipping/`, `/returns/`, `/refund-and-returns/`, tre forældede kategorislugs og to produkter der aldrig har eksisteret. De er ikke intern-link-rester — de er indgange fra pins, annoncer og gamle bogmærker.

## 4. Resten, i rækkefølge

### P0 — blokerer annoncering (2,5 dage, heraf 1,5 dag kræver ejer)

| # | Handling | Blokeret af | Effekt |
|---|---|---|---|
| P0.1 | Slå 404-cache fra, purge, verificér | Kun adgang | Alt andet måles først rigtigt bagefter |
| P0.2 | Complianz til EU opt-in, ny cookie-scan, EU-dokumenter | Plugin-UI | Kører i dag US opt-out foran EU-kunder |
| P0.3 | Juridisk identitet: navn, CVR, adresse, **telefon** i footer + `/imprint/` | **Ejer** | Lovkrav, og det mangler helt |
| P0.4 | Valuta + 39 priser genberegnet | **Ejer** (valuta) + kostpriser | Butikken prissætter i USD; 0,00–64,95 |
| P0.5 | MobilePay/PayPal ud over Stripe | Indløser | 19 % afbryder fordi de ikke tør betale med kort |
| P0.6 | GPSR-blok med rigtig postadresse på alle produkter | **Ejer** (adresse) | Blok findes, adressen gør ikke |

### P1 — konvertering (3 dage)

| # | Handling | Note |
|---|---|---|
| P1.1 | Produktbilleder: 0 af 39 har 3+ (34 har 1, 5 har 2) | Største enkeltforbedring; mockup-planen findes |
| P1.2 | Størrelsestabel i beskrivelse **og** karrusel på alt tøj | Returårsag nr. 1 |
| P1.3 | ~~Beslut `/inspiration/`~~ **udgivet 2/11** — side 1243 på `/inspiration/`, 803 ord, 34 links, Rank Math-meta sat | Redirect-rækken er fjernet fra `redirects.csv`/`.json`; `/start-here/` linker nu til hubben |
| P1.4 | Ret `robots.txt`: to `User-agent: *`-grupper | Kan deindeksere stille |
| P1.5 | Anmeldelsesplatform + automatisk anmodning | 0 anmeldelser i dag |
| P1.6 | `/shop/`: 5,8 s / 470 KB mod 2,1 s på forsiden | Dyreste side at have langsom |
| P1.7 | Fortrydelsesfunktion (ét klik) + fortrydelsestekst i ordrebekræftelsen | Varigt medium-krav |

### P2 — indhold og oprydning (2 dage)

| # | Handling | Note |
|---|---|---|
| P2.1 | ~~23 kladde-produkter~~ **17 udgivet 2/11** — Herbarium (12), Atelier (3), DIY (2). De øvrige 6 (27, 28, 29, 75, 183, 765) findes ikke længere | Alle 17 har billede, alt-tekst, kort beskrivelse og GPSR; se `drafts-udgivet-2026-11-02.md` |
| P2.2 | 8 kladde-posts: 750 er dublet af 699, 974/938 overlapper 798 | Kannibalisering |
| P2.3 | 43 produkter + 3 sider i papirkurven, 3 private designsider | Efter linktjek |
| P2.4 | Punk-lanceringen: 3 kollektioner, 6 kladser | Klar, se `deploy/steamgears/` |
| P2.5 | Plugin-oprydning: dublet Health Check, TikTok vs. Smash Balloon, WP Pusher | 33 aktive |

## 5. Beslutninger der kun kan tages af ejer

1. **CVR, firmanavn, adresse, telefon** — blokerer P0.3 og P0.6, altså to lovkrav.
2. **Valuta:** EUR eller DKK, og med hvilke kostpriser pr. SKU — blokerer P0.4 og alle 39 priser.
3. **`/inspiration/`:** byg hubben, eller lev med redirecten til galleriet.
4. ~~**Herbarium:** udgiv de 12 varer og link fra novellen.~~ **Lukket 2/11** — udgivet, og novelle 973 linker til alle tolv.
5. **Kladde-post 750:** flet ind i 699, eller skriv den om som selvstændig vinkel.

## 6. Kør dette, når adgangen er der

```bash
python3 tools/audit/link_check.py                    # 121/121 ok i dag
python3 tools/audit/link_check.py --crawl            # også alle interne links
python3 tools/deploy/build_redirects.py --verify     # 9 rækker, 0 problemer
python3 tools/deploy/build_punk.py --check           # punk-kladserne
python3 tools/deploy/check_assets.py                 # 0/25 mockups indtil videre
```

Alt over er læsende eller genererer filer. Intet er kørt mod siten med skrivende kald — der har ikke været credentials i denne session, og `deploy.py` har derfor kun kørt `--offline`.

## 7. Kontrolleret og ikke kontrolleret

**Kontrolleret i dag:** 121 sitemap-URL'er, 124 interne link-mål fra 17 sider, ni redirect-kilder og -mål, Store API (39 produkter, valuta, prisinterval, billeddækning, kategorier), Stripe-endpointets status, robots.txt, soft-404-mønstret fem gange på fem friske URL'er, samt at `state.cartItem.permalink` er en Interactivity-API-direktiv i `data-wp-bind--href` og ikke et rigtigt link.

**Ikke kontrolleret:** ingen Lighthouse/Core Web Vitals, ingen GSC- eller GA4-data, ingen ordre- eller omsætningsdata, ingen crawl af de 24 posts' brødtekst ud over de 17 sider der havde døde links, og ingen adgang til plugin-UI'et bag Complianz, W3TC eller Redirection. Påstande om ydeevne og samtykke er derfor citeret fra 1/11-auditen, ikke målt i dag.
