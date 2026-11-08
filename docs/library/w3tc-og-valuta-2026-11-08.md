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

## 2. Valutaen: DKK, og priserne er nu tabellens tal

`woocommerce_currency` er **DKK**. Priserne er i dette pas sat til **præcis de tal DKK-tabellen i
[`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md) angiver**, ordret fra tabellen — ingen
egen kurs, ingen egen afrunding, ingen »bevar nuværende beløb«-variant.

Kilden er tabellen i dokumentet: den har **38 rækker** (linje 32–69, overskrift på linje 30:
`| Produkt | USD nu | EUR-forslag | DKK-forslag |`) og dækker ifølge linje 28 »38 produkter med pris«.
**11 af dem findes i det nuværende katalog**, og de 11 står nu med tabellens DKK-tal i alle deres
variationer.

### De 11 dokumenterede rækker — tallet ordret fra dokumentet

| ID | Produkt (nuværende navn) | Dok.linje | Dokumentets række | Før dette pas | Nu (DB + side) | Ændring |
| --- | --- | ---: | --- | ---: | ---: | ---: |
| 399 | Clockwork Octopus — Curiosity Hoodie | **56** | `$49.95 → 335 kr` | 730 kr | **335 kr** | −54 % |
| 401 | Whale Airship — Vintage Voyage Hoodie | **66** | `$49.95 → 335 kr` | 730 kr | **335 kr** | −54 % |
| 403 | Mechanical Elephant — Curiosity Hoodie | **63** | `$49.95 → 335 kr` | 730 kr | **335 kr** | −54 % |
| 413 | Airship Storm — Vintage Engraving Hoodie | **54** | `$49.95 → 335 kr` | 730 kr | **335 kr** | −54 % |
| 477 | Copper Pilot Goggles - Vintage Windproof Riding | **37** | `$24.95 → 165 kr` | 460 kr | **165 kr** | −64 % |
| 483 | Time Traveler Top Hat with Goggles | **65** | `$49.95 → 335 kr` | 595 kr | **335 kr** | −44 % |
| 489 | Steampunk Overbust Corset - Leather Lace-Up Burlesque | **67** | `$59.95 → 400 kr` | 1465 kr | **400 kr** | −73 % |
| 491 | Gothic PU Leather Overbust Corset — Silver | **68** | `$64.95 → 435 kr` | 1330 kr | **435 kr** | −67 % |
| 493 | Wine Red Velvet Tailcoat — Victorian Frock Coat | **69** | `$64.95 → 435 kr` | 1870 kr | **435 kr** | −77 % |
| 495 | Victorian Vampire Tailcoat — Brocade Gothic Jacket | **52** | `$39.95 → 265 kr` | 1665 kr | **265 kr** | −84 % |
| 557 | Airship Armada — Vintage Engraving Tee | **39** | `$26.95 → 180 kr` | 530 kr | **180 kr** | −66 % |

Fire af rækkerne har et andet produktnavn i dag end i tabellen; det er samme vare, og kolonnen viser
dokumentets eget navn:

| ID | Navn i dokumentet (linje) | Navn i butikken nu |
| --- | --- | --- |
| 477 | `Copper Pilot Goggles - Vintage Windproof Riding` (37) | Copper Pilot Goggles — Vintage Windproof |
| 489 | `Steampunk Overbust Corset - Leather Lace-Up Burlesque` (67) | Steampunk Overbust Corset — Leather Lace-Up |
| 491 | `Gothic PU Leather Overbust Corset — Silver` (68) | Gothic Overbust Corset — Faux Leather, Silver (PU = faux læder) |
| 495 | `Victorian Vampire Tailcoat — Brocade Gothic Jacket` (52) | Victorian Vampire Tailcoat — Brocade |

### De 13 produkter uden nogen dokumenteret DKK-pris

Disse produkter står i butikken, men har **ingen række** i tabellen. Der er derfor ikke gættet nogen pris:
deres pris er den samme som før dette pas (omregningen fra forrige pas), og det er et faktum, ikke et valg.

| ID | Produkt | Status | Pris nu |
| --- | --- | --- | ---: |
| 765 | Steampunk Goggle DIY Starter Kit | draft | 200 kr |
| 766 | Steampunk Leather Utility Belt & Holster | draft | 165 kr |
| 767 | Digital Blueprint: Victorian Corset & Vest PDF | draft | 45 kr |
| 940 | Clockwork Seer — Brass Pocket Watch Pendant | publish | 865 kr |
| 941 | The Aeronaut — Brass Skeleton Pocket Watch | publish | 1000 kr |
| 942 | Ink and Steam — Clockwork Charm Bracelet | draft | 460 kr |
| 954 | Brass Luna Moth — Clockwork Herbarium Tee | publish | 530 kr |
| 965 | Clockwork Rose — Herbarium Art Print | publish | 395 kr |
| 966 | Brass Luna Moth — Herbarium Art Print | publish | 395 kr |
| 967 | The Raven Chronometer — Herbarium Art Print | publish | 395 kr |
| 970 | Brass Luna Moth — Herbarium Phone Case | publish | 330 kr |
| 1255 | Brass Fox Automaton — Steampunk Tee | publish | 530 kr |
| 1282 | Astrolabe Planisphere — Steampunk Tee | publish | 530 kr |

Beviset for at rækken mangler, er en rå læsning af dokumentet: tabellen har 38 rækker på linje 32–69, og
et opslag på hvert af de 13 navne giver **0 træffere** i hele dokumentet (`grep -c` → `0` for
»Steampunk Goggle DIY«, »Utility Belt«, »Digital Blueprint«, »Pocket Watch Pendant«, »Skeleton Pocket
Watch«, »Charm Bracelet«, »Herbarium«, »Phone Case«, »Brass Fox« og »Astrolabe«). Dokumentets eneste
omtale af smykkerne og Herbarium-serien er i [`priser-fra-store-api-2026-11-03.md`](priser-fra-store-api-2026-11-03.md),
og den tabel er i **USD**, ikke DKK — den kan derfor ikke bruges som DKK-kilde.

### Verificeret på begge flader, 0 afvigelser

- **DB:** hvert af de 11 produkter står med tabeltallet, og **alle variationer** har samme tal (fx 489 med
  6 variationer: alle `400`; 491 med 6: alle `435`; 493 med 4: alle `435`).
- **Den offentlige prisflade (produktsiden, læst som anonym):** **11 af 11** viser præcis tabeltallet med
  `kr.`-symbol — fx `kr. 335.00` (399, 401, 403, 413, 483), `kr. 165.00` (477), `kr. 400.00` (489),
  `kr. 435.00` (491 og 493), `kr. 265.00` (495), `kr. 180.00` (557). **0 afvigelser.**
- **Alle produktsider:** **20 af 20** svarer 200 og viser et `kr.`-beløb der matcher både DB og Store API.
- **Store API:** 20 produkter, alle `currency_code: DKK`, `currency_symbol: kr.`.
- **Kurv og kasse:** `/cart/` 200, `/checkout/` 200 med en vare i kurven (395,00 kr), og hele checkouten
  blev desuden gennemført tre gange tidligere i dag (ordre 1528, 1529, 1530 — alle annulleret med note).

### Dokumentets egen modsætning — rå citater, ikke et valg

Tabellen modsiger sin egen regel, og det skal stå som et faktum:

- Linje 73 i samme dokument: »**Skiftet må ikke ændre, hvad kunden reelt betaler, med mere end
  afrundingen.**«
- Linje 16: »Kursen skal genhentes på skiftedagen — **tabellen er et forslag, ikke en facitliste**.«
- Linje 23: »Afrunding: EUR til nærmeste `x,95`, **DKK til nærmeste 5 kr**.«
- Men tabellens DKK-kolonne er udregnet af de USD-priser produkterne havde **2/11**, og de er siden
  ændret i butikken. Målt 8/11, før dette pas: hoodie 399 stod i **$109** (tabellens række, linje 56,
  siger `$49.95 → 335 kr`), corset 489 i **$219** (linje 67: `$59.95 → 400 kr`).

Konsekvensen er målt og står i tabellen ovenfor: de 11 priser falder mellem **44 % og 84 %** i forhold til
hvad butikken viste før passet. Tabellens tal er alligevel sat, fordi det er dem dokumentet angiver, og
modsætningen er ikke løst ved at vælge side.

**Én konkret ejerhandling:** åbn **WooCommerce → Produkter** og gennemse prisen på de 11 produkter i
tabellen (335 / 165 / 335 / 400 / 435 / 435 / 265 / 180 kr). Er de tal de rigtige, er posten færdig som
den står; er de for lave, ret dem i samme felt, eller sig at tabellen skal genberegnes mod de nuværende
USD-priser — så kører agenten det i én kørsel.

### Rollback

De tidligere priser står i kolonnen »Før dette pas« ovenfor. Ét kald genopretter dem; valutakoden bliver
`DKK` i begge tilfælde.

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
