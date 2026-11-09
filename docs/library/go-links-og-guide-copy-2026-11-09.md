# `/go/`-links og guide-copy — målt 9/11 2026

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

Målt med det nye `--live`-værn i `tools/guide_check.py` mod `docs/library/guides-*.md`:

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
python3 tools/guide_check.py --live
```

- Hvert `/go/<id>/` i `docs/library/guides-*.md` slås op i Store API.
- **404** er en fejl, med id og hvilke guides der bruger det.
- Et id der **ikke kan måles** (netværk, WAF, 5xx) er en **advarsel** — aldrig et «bestået».
- Prissætter butikken i **DKK**, mens copyen regner i `$`, er det en fejl: hvert beløb og hvert total er
  så forkert for kunden, uanset hvor rigtigt det var den dag det blev skrevet.
- Fire fixtures i `--selftest` beviser at både det døde id, valuta-skredet og den umålelige butik fanges
  (15/15 grønne).

Det kører **ikke** i den natlige kontrol, fordi det kræver netværk; det køres i hånden før en publicering.

## 6. Konsekvens og næste skridt

`guides-2026-11-03.md` og `…-corset.md` har fået en statuslinje øverst: **de må ikke publiceres som de
står.** Den publicerede serie (1357, 1361, 1359 + corset) dækker SEO-modulet, og de sider er målt rene
ovenfor.

Skal de tre gamle guides (292, 296, 295) alligevel bruges, er om-mappingen den nærmeste levende
erstatning pr. plads — og tallene er rene, fordi de kommer fra Store API 9/11:

| Guide | Ti pladser / tre trin | Total |
| --- | --- | ---: |
| Men's essentials (292) | 477 · 483 · 495 · 493 · 413 · 399 · 557 · 954 · 965 · 967 | **3.370,00 kr.** |
| Three budgets (296) | 477+965 · +483+557 · +495+413+399 | **560 / 1.075 / 2.010,00 kr.** |
| Women's essentials (295) | 489 · 491 · 477 · 483 · 495 · 493 · 413 · 954 · 965 · 940 | **4.160,00 kr.** |

Teksten omkring hvert produkt skal skrives om — designene bag de døde id'er findes ikke længere, så et
søg-og-erstat duer ikke. Rækkefølgen er: skriv om, kør `--live` til den er grøn, publicér.
