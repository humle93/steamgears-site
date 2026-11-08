# W3TC og valutaen — de to sidste poster, udført på live

8/11 2026. Begge poster stod som »ejer-blokeret«, fordi de blev skrevet **før** der fandtes en
skrivevej til sitet. De kunne begge udføres gennem MCP-abilityen `novamira/execute-php`, og begge er
målt udefra som anonym besøgende bagefter. Dette dokument er beviset: hvad der blev ændret, hvad der
blev målt — og de to steder hvor virkeligheden ikke længere stemmer med det ældre dokument.

## 1. W3TC: minify, lazy load og database-cache er slået til

Mål-tilstanden stod i [`../seo.md`](../seo.md) #4: »slå **Minify (JS+CSS), Lazy load images og
Database cache** til i W3TC«. Indstillingerne ligger i `wp-content/w3tc-config/master.php`, ikke i
options, så JSON-delen af filen blev rettet direkte (headeren `<?php exit; ?>` er beholdt) — efter en
backup.

| Indstilling | Før | Efter |
| --- | --- | --- |
| `minify.enabled` | `false` | **`true`** |
| `minify.engine` | `file` | `file` |
| `minify.js.enable` / `minify.css.enable` | `true` / `true` | `true` / `true` (nu virksomme) |
| `minify.html.enable` | `false` | `false` (dokumentet beder kun om JS+CSS) |
| `dbcache.enabled` | `false` | **`true`** |
| `dbcache.engine` | `file` | `file` |
| `lazyload.enabled` | `false` | **`true`** |
| `pgcache.enabled` / `browsercache.enabled` | `true` / `true` | uændret |

Backup: `wp-content/uploads/w3tc-master-foer-minify-2026-11-08.php`, 32 346 bytes, md5
`7e05b00219ed8081b90de355b70100ad`. Efter skrivningen: 32 312 bytes, md5
`f359998f2e490baa4c016101de762c31`. `w3tc_flush_all()` og `w3tc_flush_posts()` blev kørt, og
`wp-content/cache/minify/` + `wp-content/cache/dbcache/` blev oprettet.

**Database-cachen krævede et skridt, som ikke stod i dokumentet.** W3TC's dbcache virker kun gennem
drop-in-filen `wp-content/db.php`; den fandtes ikke, så indstillingen ville have stået tændt uden at
gøre noget. W3TC's egen fil blev kopieret på plads (2 543 bytes fra
`wp-content/plugins/w3-total-cache/wp-content/db.php`), og `$wpdb` er derefter
`W3TC\DbCache_WpdbNew` med `DbCache_WpdbInjection_QueryCaching` som aktiv processor.

### Bevis udefra, anonym besøgende

| Måling | Resultat |
| --- | --- |
| Minificerede filer i `wp-content/cache/minify/` | **157 filer, 7 695 269 bytes** |
| `/` — unikke minificerede filer i HTML | **15** |
| Produktside — `<script>`-tags i alt / med `src` | **69 / 33** (33 eksterne samlet i 19 minificerede JS-filer; `seo.md` målte 88 script-tags før) |
| Produktside — stylesheet-links | **12**, alle minificerede |
| `/shop/` — alle lokale JS/CSS hentet | **28 af 28 svarer 200**, 18 `application/x-javascript` + 10 `text/css`, 0 fejl |
| Lazy load på produktsiden | **22 `data-src`**, 5 `noscript`-fallbacks, 4 `class="lazy"` |
| Sitemap-gennemgang | **33 af 33 sider 200** (12 udvalgte sider + alle 21 produkt-URL'er) |
| `/cart/` og `/checkout/` med varer i kurven | **200 / 200**, kurven viser 865,00 kr i DKK |
| `/wp-json/`, `/wp-json/sgap/v1/tick`, `/feed/` | **200 / 200 / 200** |

### Testordren — forudsætningen dokumentet selv krævede

Ordren blev lagt gennem butikkens **egen checkout** (WooCommerce Store API, samme vej kunden går:
`POST /wp-json/wc/store/v1/cart/add-item` → `POST /wp-json/wc/store/v1/checkout`) med betalingsmåden
`bacs` (bankoverførsel), så intet kort blev rørt. Tre ordrer, én før og to efter ændringerne:

| Ordre | Hvornår | Indhold | Beløb | Status nu |
| --- | --- | --- | ---: | --- |
| **1528** | før valuta og cache | Copper Pilot Goggles (477) | $69.00 | annulleret med note |
| **1529** | efter valutaskiftet | Clockwork Rose Art Print (965) | 395,00 kr | annulleret med note |
| **1530** | efter W3TC blev slået til | The Raven Chronometer (967) | 395,00 kr | annulleret med note |

Alle tre fik noten »TESTORDRE … ingen betaling, intet skal sendes« og blev sat til `cancelled`, så de
ikke tæller som omsætning. At 1530 kunne gennemføres **efter** minify, lazy load og dbcache blev slået
til, er beviset for at cachen ikke ødelægger kurv eller kasse.

### Database-cachen: tændt og aktiv, men uden målbar effekt her

Det skal stå ærligt: `dbcache.enabled` er `true`, drop-in'en er på plads, og processoren kører — men
`wp-content/cache/dbcache/` er **tom (0 filer)** efter alle kaldene ovenfor. W3TC's egen
afvisningsgrund, læst ud af den aktive processor, er:

```
cache_reject_reason = 'modification query'   (request-wide: true)
```

Det er W3TC's design: en request der skriver til databasen (WooCommerce-session, transienter,
page-cache-priming) får dbcache slået fra for hele requesten, og sider der serveres fra page-cachen når
slet ikke databasen. Indstillingen står altså som dokumentet foreskriver, men dens effekt kan ikke måles
på dette stack — det er en anden konklusion end »den virker«, og den står her i stedet for at blive pyntet.

### Rollback

Gendan `wp-content/uploads/w3tc-master-foer-minify-2026-11-08.php` til
`wp-content/w3tc-config/master.php`, slet `wp-content/db.php`, kør `w3tc_flush_all()`. Så er W3TC
tilbage på formiddagens tilstand (pgcache + browsercache, ingen minify/dbcache/lazyload).

## 2. Valutaen: butikken kører nu DKK

`woocommerce_currency` blev ændret **USD → DKK**, og alle priser blev sat i samme kørsel: **72
prisrækker** (12 simple produkter + 60 variationer) fordelt på 24 produkter. Der fandtes ingen
udsalgspriser, så ingen blev rørt.

**Metoden er dokumentets egen.** [`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md)
siger: primær valuta **DKK**, kursen skal **genhentes på skiftedagen**, og DKK afrundes til **nærmeste
5 kr**. Kursen blev hentet 8/11 fra `api.frankfurter.dev` (ECB-referencer, dateret 6/11 2026):
**1 USD = 6,6952 DKK**. Hvert produkt fik `round(pris × 6,6952 / 5) × 5`.

### De 24 priser, før og efter

| ID | Produkt | Status | Før (USD) | Efter (DKK) | 2/11-tabellens DKK |
| --- | --- | --- | ---: | ---: | ---: |
| 399 | Clockwork Octopus — Curiosity Hoodie | publish | $109 | **730 kr** | 335 |
| 401 | Whale Airship — Vintage Voyage Hoodie | publish | $109 | **730 kr** | 335 |
| 403 | Mechanical Elephant — Curiosity Hoodie | publish | $109 | **730 kr** | 335 |
| 413 | Airship Storm — Vintage Engraving Hoodie | publish | $109 | **730 kr** | 335 |
| 477 | Copper Pilot Goggles — Vintage Windproof | publish | $69 | **460 kr** | 165 |
| 483 | Time Traveler Top Hat with Goggles | publish | $89 | **595 kr** | 335 |
| 489 | Steampunk Overbust Corset — Leather Lace-Up | publish | $219 | **1465 kr** | 400 |
| 491 | Gothic Overbust Corset — Faux Leather, Silver | publish | $199 | **1330 kr** | 435 |
| 493 | Wine Red Velvet Tailcoat — Victorian Frock Coat | publish | $279 | **1870 kr** | 435 |
| 495 | Victorian Vampire Tailcoat — Brocade | publish | $249 | **1665 kr** | 265 |
| 557 | Airship Armada — Vintage Engraving Tee | publish | $79 | **530 kr** | 180 |
| 765 | Steampunk Goggle DIY Starter Kit | draft | $29.90 | **200 kr** | — |
| 766 | Steampunk Leather Utility Belt & Holster | draft | $24.90 | **165 kr** | — |
| 767 | Digital Blueprint: Victorian Corset & Vest PDF | draft | $6.90 | **45 kr** | — |
| 940 | Clockwork Seer — Brass Pocket Watch Pendant | publish | $129 | **865 kr** | — |
| 941 | The Aeronaut — Brass Skeleton Pocket Watch | publish | $149 | **1000 kr** | — |
| 942 | Ink and Steam — Clockwork Charm Bracelet | draft | $69 | **460 kr** | — |
| 954 | Brass Luna Moth — Clockwork Herbarium Tee | publish | $79 | **530 kr** | — |
| 965 | Clockwork Rose — Herbarium Art Print | publish | $59 | **395 kr** | — |
| 966 | Brass Luna Moth — Herbarium Art Print | publish | $59 | **395 kr** | — |
| 967 | The Raven Chronometer — Herbarium Art Print | publish | $59 | **395 kr** | — |
| 970 | Brass Luna Moth — Herbarium Phone Case | publish | $49 | **330 kr** | — |
| 1255 | Brass Fox Automaton — Steampunk Tee | publish | $79 | **530 kr** | — |
| 1282 | Astrolabe Planisphere — Steampunk Tee | publish | $79 | **530 kr** | — |

### Verificeret på begge flader

- **DB:** alle 24 produkter står med DKK-pris; de fire kladder (765/766/767/942) er ikke offentlige og
  er derfor kun kontrolleret i databasen: 200, 165, 45 og 460 kr.
- **Offentlig prisflade:** WooCommerce Store API (`/wp-json/wc/store/v1/products?per_page=100`) svarer
  **200** med **20 produkter, alle med `currency_code: DKK`** og `currency_symbol: kr.`.
- **Produktsiderne:** alle **20 af 20** offentlige produktsider svarer 200, viser prisen med `kr.` og
  **hvert vist beløb matcher databasen** (fx `kr. 530.00` for id 1282 og `kr. 1,465.00` for id 489).
  **0 afvigelser.**

### Afvigelsen der skal stå her: tabellen passer ikke længere på kataloget

Dette er dokumentets vigtigste måling. **Kun 11 af de 24 produkter har en række i 2/11-tabellen**, og
for alle 11 bygger tabellens DKK-tal på den USD-pris produktet havde **2/11**. Kataloget er blevet
genprissat i USD siden:

| Produkt | USD 2/11 (tabellens grundlag) | USD 8/11 (før skiftet) | Tabel | Anvendt |
| --- | ---: | ---: | ---: | ---: |
| Clockwork Octopus Hoodie (399) | $49.95 | $109 | 335 kr | 730 kr |
| Steampunk Overbust Corset (489) | $59.95 | $219 | 400 kr | 1 465 kr |
| Wine Red Velvet Tailcoat (493) | $64.95 | $279 | 435 kr | 1 870 kr |
| Airship Armada Tee (557) | $26.95 | $79 | 180 kr | 530 kr |

At føre tabellens absolutte tal ind ville derfor **sænke priserne med 45–78 %** — og det strider mod
tabellens egen regel: »Skiftet må ikke ændre, hvad kunden reelt betaler, med mere end afrundingen.« De
13 øvrige produkter (Atelier-smykkerne, Herbarium-serien, V5-tees og de fire kladder) har **ingen
række** i tabellen, så der findes intet dokumenteret tal at sætte for dem.

Valutaskiftet blev derfor udført som et **valutaskift**: samme pris, ny valuta, omregnet med den
dokumenterede kurs og afrundet efter den dokumenterede regel. Valutaen og regnestykket er udført som
foreskrevet; de absolutte tal fra 2/11 er **ikke** anvendt, fordi de ville ændre kundeprisen markant.
Kolonnen længst til højre viser præcis, hvad ejeren får, hvis de 11 tal foretrækkes alligevel.

**EUR som sekundærvaluta vises ikke.** Dokumentet foreskriver EUR »via currency switcher, ikke som
base«, men der er **ingen aktiv currency-switcher** på sitet (målt: 0 aktive plugins med »currenc« i
stien). Det er en ejerhandling: installer og aktivér en switcher, så konfigurerer agenten EUR.

### Rollback af priserne

De gamle USD-tal står i tabellen ovenfor. Ét kald genopretter dem: `woocommerce_currency` til `USD` og
hvert produkt tilbage til kolonnen »Før (USD)«.

## 3. Hvad der stadig ikke kan gøres af agenten

Intet nedenstående blev gættet — hver post er et klik eller et felt, ejeren udfører:

| Post | Rå svar fra forsøget | Én handling |
| --- | --- | --- |
| CVR + telefon (GPSR, S4.2/S4.9) | `telephone_company: ""`, intet CVR-felt i Complianz | Complianz → Wizard → Organization: indsæt telefon og CVR |
| Fragt til EU/UK/RoW (S4.6) | zone 0 (»Locations not covered«) har **ingen** metoder | WooCommerce → Indstillinger → Forsendelse: lande, pris pr. zone, fri fragt-grænse |
| MobilePay/PayPal (S4.5) | aktive gateways: `stripe`, `stripe_amazon_pay`, `stripe_klarna`, `stripe_link`, `bacs` | opret aftalen og læg nøglerne i secret |
| GSC-svarrapport (S0.6) | `GET searchconsole…/sites` → **HTTP 401** | Search Console → Crawl-statik → By response → screenshot |
| Printful-mockups (S4.7/batch 4) | `printful: {"ok": null, "message": "No Printful token saved."}` | Printful → Settings → API → token i pluginnet, eller 5–8 fotos pr. produkt |
| De fire kladder 942/765/766/767 (S1.2) | alle fire står `draft` med pris og SKU | svar ja/nej pr. produkt |
| witchy-tarot | 0 produkter; 567/395/411 ligger i papirkurven | gendan de tre produkter |
| AIMLAPI | 45 kladder i kø, seneste generering gav 0 nye | fyld op på kontoen |
| To inaktive plugins (S3.4b) | `website-llms-txt v8.6.3`, `insert-headers-and-footers v2.4.1` | Plugins → Inaktive → Slet, eller svar »behold« |
| EUR som sekundærvaluta | 0 aktive currency-switchere | installer og aktivér en switcher |
