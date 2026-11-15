# 11 plakater publiceret — Clockwork Court og Airship Chronicles (15/11 2026)

**Kort:** De sidste 11 plakater i den nye batch er publiceret: ni **Clockwork Court** og to
**Airship Chronicles**. Butikken går fra **3 til 14 publicerede varer**, og alle 14 er plakater.
De 11 manglede kun en slug og en publiceringsstatus — billede, alt-tekst, Rank Math-titel og
-beskrivelse, to størrelser og lager var allerede på plads. De 8 **Diesel Age**-plakater og de
50 katalogkladder er urørte.

Målt 15/11 2026 mod den levende butik (DB, HTTP og Store API), ikke kun i koden.

---

## 1. De 11 publicerede

| Id | SKU | Serie | Titel | Slug | 3040 | 5070 |
| --- | --- | --- | --- | --- | --- | --- |
| 1843 | SG-CC-01 | CC 01 | The Golden Stag — Clockwork Stag Poster | `the-golden-stag-poster` | 199 | 299 |
| 1846 | SG-CC-02 | CC 02 | The Tower Inventor — Steampunk Inventor Poster | `the-tower-inventor-poster` | 199 | 299 |
| 1849 | SG-CC-03 | CC 03 | Cathedral of Time — Clockwork Cathedral Poster | `cathedral-of-time-poster` | 199 | 299 |
| 1852 | SG-CC-04 | CC 04 | The Automaton's Sonata — Automaton Art Print | `the-automatons-sonata-poster` | 199 | 299 |
| 1855 | SG-CC-05 | CC 05 | The Tower Guard — Steampunk Warrior Poster | `the-tower-guard-poster` | 199 | 299 |
| 1858 | SG-CC-06 | CC 06 | The Brass Duellist — Steampunk Fencer Poster | `the-brass-duellist-poster` | 199 | 299 |
| 1861 | SG-CC-07 | CC 07 | The Phoenix Watchmaker — Watchmaker Poster | `the-phoenix-watchmaker-poster` | 199 | 299 |
| 1864 | SG-CC-08 | CC 08 | The Royal Horologist — Orrery Poster | `the-royal-horologist-poster` | 199 | 299 |
| 1867 | SG-CC-09 | CC 09 | The Glasshouse Botanist — Steampunk Botanist Poster | `the-glasshouse-botanist-poster` | 199 | 299 |
| 1870 | SG-AC-10 | AC 10 | The Wind-Up Galleon — Flying Ship Poster | `the-wind-up-galleon-poster` | 199 | 299 |
| 1873 | SG-AC-12 | AC 12 | The Venetian Ornithopter — Ornithopter Poster | `the-venetian-ornithopter-poster` | 199 | 299 |

Kolonnerne 3040 og 5070 er cm (30×40 og 50×70), og alle priser er i **kr.**; CC = Clockwork Court, AC = Airship Chronicles. Alle 11 er `variable` produkter med to variationer og `instock`. De lå som kladder med tom
`post_name`; de har fået en kort, unik slug. Priser, billeder, tekster, kategorier og
SEO-felter er ikke ændret i dette pas.

## 2. Før og efter

| Måling | Før | Efter | Ændring |
| --- | --- | --- | --- |
| Publicerede produkter | 3 | **14** | +11 |
| Kladder | 90 | **79** | −11 |
| Papirkurv | 92 | 92 | 0 |
| Plakatkladder | 69 | **58** | -11 |
| Ikke-plakatkladder | 21 | 21 | 0 |

Regnestykket: `3 + 11 = 14` og `90 - 11 = 79`. De 69 plakatkladder var 50 fra
`data/posters-50-import.csv` plus 19 fra den lokale motivbatch; efter publiceringen står
50 + 8 = 58 tilbage. Intet er slettet.

## 3. Hvorfor netop disse 11

Den nye batch på 19 plakater blev oprettet 15/11 og består af 8 **Diesel Age**, 9
**Clockwork Court** og 2 **Airship Chronicles**. De sidste 11 i batchen — Clockwork Court
1–9 og Airship Chronicles 10 og 12 — er dem der blev publiceret her; de 8 Diesel Age står
stadig som kladder. De 50 katalogkladder fra 13/11 er også urørte, herunder de 11 der har
et billede (1559–1587, 1599, 1679 og 1683).

**Nummereringsgap:** Airship Chronicles springer fra No. 10 til No. 12. Der findes ingen
`SG-AC-11` i kataloget (hverken publiceret, kladde eller papirkurv). Om No. 11 er bevidst
droppet eller aldrig blevet oprettet, er ikke afgjort her; serien er ikke omdøbt, fordi
SKU'en også står på variationerne.

## 4. Hvad der blev ændret

1. `post_status` fra `draft` til `publish` for de 11 id'er.
2. `post_name` fra tom streng til de 11 slugs i tabellen ovenfor.
3. WooCommerce-produktgemning via CRUD (`$product->save()`), så lookup-tabeller og
   produkt-transients følger med.
4. Cache ryddet: W3TC (`w3tc_flush_all()`), objektcache (`wp_cache_flush()`) og Rank Maths
   sitemap-cache (`\RankMath\Sitemap\Cache::invalidate_storage()`).

Ingen ændring i pris, lager, billede, beskrivelse, kategori, betaling eller forsendelse.

## 5. Verifikation (kørt 15/11)

| Check | Resultat |
| --- | --- |
| DB-status | **14 publish · 79 draft · 92 trash** |
| De 11 produktsider | **11 af 11 svarer 200** |
| Pris og størrelser på produktsiden | 199 kr. og 299 kr. synlige; `30×40` og `50×70` i variantvælgeren; `InStock` i schema |
| Kurv via Store API | `add-item` svarer **201**; 30 × 40 giver **19 900 øre**, 50 × 70 giver **29 900 øre**, begge i **DKK** |
| `/shop/` | **14** produktkort, ingen paginering (side 2 giver 404, som den skal med 14 varer) |
| `/product-category/clockwork/` | **11** produkter (2 gamle + 9 nye) |
| `/product-category/airship/` | **2** produkter — kategorien var tom og gav 302 til `/shop/`; nu svarer den 200 |
| `/product-category/posters-prints/` | **14** produkter |
| `product-sitemap.xml` | **14** produkt-URL'er |

Kurven blev tømt igen efter målingen. Der er **ikke** kørt browser-måling af overflow eller
JS-fejl i dette pas, og der er ikke crawlet hele sitet for døde links; de 11 produktlinks,
shopkortene og de tre kategorisider er målt direkte og svarer 200.

## 6. Åbne punkter

- **Trykopløsning:** de synlige filer er **1000 × 1400 px**. Det er nok til web og mockup,
  men et 50 × 70 cm-tryk kræver **5 906 × 8 268 px** ved 300 dpi. De 11 er derfor
  salgbare i butikken, men **ikke trykklare** som de står. Det er samme åbne post som i
  [de 19 motiver](punk-billedprompts-2026-11-14.md).
- **Gelato er ikke forbundet** (målt 13/11), og der findes ingen tryk-PDF i repoet. Før en
  ordre på 50 × 70 cm kan produceres, skal ejeren forbinde Gelato og lægge trykfiler ind.
- **8 Diesel Age-plakater** (1819–1840) står stadig som kladder og kan publiceres på samme
  måde, hvis de skal med.
- **Airship Chronicles No. 11** mangler i kataloget, mens No. 10 og No. 12 er publiceret.
- **Forsiden** viser fortsat de tre oprindelige plakater som faste kort; de 11 nye vises i
  shop, kategorier og sitemap, ikke som nye forsidemoduler.
