# `/go/`-links og guide-copy — målt 9/11 2026

> **Rettet samme dag — og dækningen udvidet bagefter.** Copyfilerne er skrevet om til den levende butiks
> katalog og valuta: **15 levende `/go/`-id'er**, DKK-priser og genberegnede totaler. `python3 tools/guide_check.py`
> (uden flag) melder **0 døde id'er og 0 valutaafvigelser** for alle **seks copy-blokke i fem filer** — også
> hub-copyen og outfit-siden, som ingen værn målte før. Værnet fejler fortsat på den gamle fil — se §7.
> Tabellerne nedenfor er målingen **før** rettelsen og står som dokumentation for fejlen, ikke som status.

De tre publicerede guides (1357, 1361, 1359) er rene: hvert produktlink peger på et produkt butikken
faktisk har. **Guide-copyen i repoet gør ikke.** `docs/library/guides-2026-11-03.md` og
`…-corset.md` er skrevet til det katalog og den valuta butikken havde 2.–3/11, og tolv af deres atten
`/go/`-id'er svarer nu **404**. Forskellen mellem de to er præcis det, denne måling dokumenterer — og
den er usynlig i repoet, fordi `guide_check.py` hidtil kun sammenlignede copyen med gamle prislister.

Samme måling viser at REST-nedbruddet er overstået: `/wp-json/?cb=98761` svarer **200 med 1.454.146 B
`application/json`**, ikke 1.650 B fatal som 7/11. Det er den kanal der gjorde målingen mulig.

---

## 1. Hvad der blev målt

Alle kald er gjort med **neutral UA** (curl's egen), som `tools/waf_check.sh` bekræfter giver 200. En
Chrome-UA fra denne maskine får **454 «Checking your browser»**, og Googlebot-/bingbot-UA får **455** —
Simplys bot-værn, ikke en fejl på sitet.

| Kald | Svar |
| --- | --- |
| `/wp-json/?cb=98761` | **200**, 1.454.146 B, `application/json` |
| `/wp-json/wc/store/v1/products?per_page=100` | 200, 94.634 B — **20 produkter** |
| `x-wp-total` på `/wp-json/wp/v2/product` | **20** |
| `/steampunk-corset-guide/?cb=98762` | 200, 100.125 B |
| `/mens-steampunk-costume-essentials/?cb=98763` | **404** (denne trio er ikke publiceret) |
| `/steampunk-clothing-three-budgets/?cb=98764` | **404** |
| `/womens-steampunk-essentials/?cb=98765` | **404** |

Butikken prissætter i **DKK** (`currency_code: DKK`, vist som `kr.335.00`). `/shop/` indeholder **0** `$`
og **66** `kr.` — det er ikke en proxy-fejl.

## 2. De publicerede guides er rene

Hver side er hentet og hvert `/go/`-link i den HTML slået op i Store API:

| Post | URL | `/go/`-links | Status |
| ---: | --- | --- | --- |
| 1357 | `/steampunk-wall-decor-diy/` | 965, 966, 967 | alle live (395,00 kr. pr. stk.) |
| 1361 | `/steampunk-mad-scientist-costume/` | 477, 483, 940 | alle live (165 / 335 / 865,00 kr.) |
| 1359 | `/steampunk-utility-belt/` | 477, 940, 941 | alle live (165 / 865 / 1.000,00 kr.) |
| 1394 | `/steampunk-corset-guide/` | 489, 491 | alle live (400 / 435,00 kr.) |

Corset-guiden er samtidig beviset på at en **live-side kan være rettet uden at filen i repoet er det**:
siden bærer to links, begge levende, mens `docs/library/guides-2026-11-03-corset.md` stadig rummer ti
`/go/`-id'er, hvoraf seks er døde.

## 3. Repo-copyen: tolv døde id'er

Målt med det nye live-værn i `tools/guide_check.py` mod de daværende `docs/library/guides-*.md` (målingen er
fra før rettelsen og er historisk):

| `/go/`-id | Butikken | Brugt i |
| --- | --- | --- |
| 397 | **404** — slettet | Guide 1, 2 |
| 411 | **404** — slettet | Guide 3, 4 |
| 475 | **404** — slettet | Guide 1, 2, 3, 4 |
| 481 | **404** — slettet | Guide 1, 2, 3, 4 |
| 485 | **404** — slettet | Guide 3, 4 |
| 487 | **404** — slettet | Guide 3, 4 |
| 497 | **404** — slettet | Guide 1, 2, 3, 4 |
| 561 | **404** — slettet | Guide 1, 2 |
| 563 | **404** — slettet | Guide 1 |
| 567 | **404** — slettet | Guide 3, 4 |
| 724 | **404** — slettet | Guide 1, 2 |
| 728 | **404** — slettet | Guide 1 |
| 413, 483, 489, 491, 493, 495 | findes | (seks af atten) |

Store API svarer `woocommerce_rest_product_invalid_id` for alle tolv, så det er slettede produkter og
ikke en midlertidig fejl. Otte af dem «virker» som 302 og lander på `?post_type=product&p=<id>`, som
svarer **404 «Page Not Found»** — derfor er et 302 i sig selv ikke et bevis.

## 4. Hvorfor det ikke blev opdaget

`guide_check.py` kontrollerede, at hvert `/go/`-id **findes i produkt-ID-mappingen** og at hvert `$`-beløb
**findes i prislisterne**. Begge dele var sande: mappingen og priserne var skrevet 1.–3/11 og er ikke
blevet opdateret, siden butikken blev ryddet og skiftet til DKK. Værnet målte altså copyen mod sit eget
fortidige grundlag — den slags værn består altid, og det er derfor et grønt værn ikke er det samme som en
side der virker.

## 5. Det nye værn

```bash
python3 tools/guide_check.py              # finder hver copyfil, måler mod butikken, fejler på døde id'er
python3 tools/guide_check.py --coverage   # viser hvad der er copy, og hvad der ikke er (med grund)
python3 tools/guide_check.py --offline    # kun de lokale regler; til miljøer uden netværk
```

- **Dækningen følger fil-sættet.** En fil er copy, hvis den bærer husets markører (`**H1:**` eller
  Rank Math-tabellen) — den bliver målt uanset filnavn. Det er derfor hub-copyen og outfit-siden nu er med:
  før kiggede værnet kun på `docs/library/guides-*.md`.
- **Ingen fil kan ligge uden for.** En fil under `docs/` med `/go/<id>/`-links eller 20+ prismålinger, som
  hverken er copy eller står i registeret `IKKE_COPY` med en grund, får kørslen til at fejle. Registeret er
  de dokumenter der med vilje citerer gamle tal (link-planen fra 3/11, USD-prislisterne, logfilerne).
- Hvert `/go/<id>/` slås op i Store API. **404** er en fejl, med id og hvilken fil der bruger det. Et id der
  **ikke kan måles** (netværk, WAF, 5xx) er en **advarsel** — aldrig et «bestået».
- Prissætter butikken i **DKK**, mens copyen regner i `$`, er det en fejl.
- **24 fixtures** i `--selftest` beviser at døde id'er, valuta-skred, forkerte totaler og uklassificerede
  filer alle bliver fanget.

Netværkskaldet er **standard**, og det kører i den natlige værn-kørsel (`tools/natlige-vaern.sh`).

## 6. Konsekvens og næste skridt
Copyen er nu publiceringsklar på butikkens egne vilkår. Fem filer er skrevet om —
`guides-2026-11-03.md` (292, 296, 295), `guides-2026-11-03-corset.md` (1394), hub-copyen
`hub-steampunk-costumes-2026-11-03.md` (side 550) og outfit-siden
`steampunk-outfit-ideas-for-women.md` (post 699) — med disse tal, alle læst i Store API 9/11:

| Fil | Produkter | Total |
| --- | --- | ---: |
| Men's essentials (292) | 477 · 483 · 495 · 493 · 413 · 399 · 557 · 954 · 965 · 967 | **3.370,00 kr.** |
| Three budgets (296) | 477+965 · +483+557 · +495+413+399 | **560 / 1.075 / 2.010,00 kr.** |
| Women's essentials (295) | 489 · 491 · 477 · 483 · 495 · 493 · 413 · 954 · 965 · 940 | **4.160,00 kr.** |
| Corset (1394) | 489 · 491 · 477 · 483 · 495 · 493 · 954 · 940 · 965 · 557 | **4.005,00 kr.** |
| Hub (550) | de ti fra men's essentials | **3.370,00 kr.** |
| Outfit (699) | tolv looks af 477 · 483 · 489 · 491 · 493 · 495 · 399 · 403 · 557 · 940 · 965 · 967 · 1282 | **500–1.300,00 kr.** |

Prisgrundlaget er [`priser-live-2026-11-09.md`](priser-live-2026-11-09.md), som nu er den **eneste** kilde
`guide_check.py` læser — de gamle USD-lister fra 2.–3/11 kan ikke længere bruges som facit.

## 7. Sådan kan fejlen ikke komme igen

`tools/guide_check.py` fejler nu på disse måder, alle dækket af fixtures i `--selftest` (24/24):

| Regel | Fixture |
| --- | --- |
| et `/go/<id>/` som butikken svarer 404 på | «slettet produkt fanges» |
| et `$`-beløb i en fil hvis butik prissætter i DKK | «dollar i en DKK-fil fanges» |
| en pris der ikke står i den målte liste | «opdigtet pris fanges» |
| et total der ikke er summen af sine dele | «forkert total fanges» |
| butikken svarer i en anden valuta end copyen | «valuta-skred fanges» |
| butikken kan ikke måles (5xx/netværk) | «umålbar butik bliver advarsel, ikke bestået» |
| en copyfil uden for `guides-*.md` (hub, outfit) | «enkeltside uden slug og hub består» |
| en side-copy der stadig er i `$` | «enkeltside med dollar fanges» |
| en fil med `/go/`-links som ingen værn kender | «uklassificeret fil med /go/-link fanges» |
| en gammel prisliste der gemmer sig som dokumentation | «uklassificeret fil med mange priser fanges» |

Netværkskaldet er **standard**, ikke et flag: den gamle fil blev kørt igen som kontrol og gav `FAIL` på alle
tre guides plus `DØD /go/475/ → 404`. `--offline` findes kun til miljøer uden netværk.

Det, der stadig kræver et menneske, er publiceringen: post 292, 296 og 295 er kladder, og side 550 skal have
den nye copy. Intet af det kan gøres uden skriveadgang til WordPress.
