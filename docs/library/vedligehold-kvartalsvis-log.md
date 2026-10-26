# Kvartalsvis fuld crawl + top-20 — 26/10 2026

## Crawl-omfang (kilder: sitemap_index.xml, firecrawl-crawl 100 sider, GSC API, PageSpeed API)
- Sitemap: **127 URL'er** (127/127 verificeret: 93 unikke crawl'ede + 34 HEAD-tjek)
- Statuskoder: 122× HTTP 200, **1× 404** (`/product/airship-blueprint-poster/` — draft-produkt 29 i sitemap), **5× soft-404** (HTTP 200 + "Page Not Found"-titel)
- Soft-404-URL'er: `/product-category/goggles/`, `/product-category/posters-wall-art/`, `/product/aviator-goggles-steampunk-retro-motorcycle-windproof/`, `/product/brass-rivet-steampunk-goggles/`, `/product/victorian-underbust-corset/`
- Interne kildesider til soft-404'erne: diy-steampunk-goggles, steampunk-costumes, under-100-plan, how-to-make-a-steampunk-costume, steampunk-cosplay-on-a-budget, pirate-cosplay-for-beginners, steampunk-outfit-ideas-for-women, steampunk-halloween, posters-wall-art selv
- Transiente serverfejl under crawl-load: **11× "508 Resource Limit Is Reached"** + **2× 503** (/journal/, /steampunk-cosplay-gallery/) — alle verificeret HTTP 200 enkeltvis efterfølgende. Hosting-ressourcegrænse (Simply/CloudLinux) rammer ved samtidige kald.

## GSC (28 dage: 25/9–23/10 2026, URL-prefix-property)
- 9 klik / 298 impressions / gennemsnitlig position 7 (20 URL'er med data)
- Top: diy-steampunk-goggles 5/63 pos 7,0 · how-to-make-a-steampunk-costume 2/25 pos 8,0 · steampunk-costume-ideas 2/43 pos 12,4
- Top-query: "steampunk costume ideas" 1/9 pos 10,4 · "steampunk gears" 0/6 pos 39,8 · "steampunk goggles diy" 0/4 pos 13,8

## Top-20 PageSpeed (mobil, Lighthouse 13.5.0, 26/10)
| Side | PSI-mob | LCP-mob | Ord | Interne links | Produktlinks | Billeder | GSC k/i/pos | Indholds-score |
|---|---|---|---|---|---|---|---|---|
| Forside | 62 | 10,1 s | 575 | 22 | 0 | 7 | 0/15/9,2 | 48 |
| /shop/ | 56 | 14,2 s | 479 | 42 | 44 | 18 | 0/16/4,6 | 83 |
| /steampunk-costumes/ (hub) | 52 | 13,5 s | 779 | 27 | 0 | 2 | 0/0/- | 48 |
| /diy-steampunk-goggles/ | 55 | 5,0 s | 2148 | 23 | 0 | 12 | 5/63/7,0 | 65 |
| /how-to-make-a-steampunk-costume/ | 72 | 4,7 s | 1856 | 28 | 1 | 15 | 2/25/8,0 | 83 |
| /steampunk-costume-ideas/ | 57 | 13,8 s | 1655 | 19 | 0 | 5 | 2/43/12,4 | 55 |
| /steampunk-outfit-ideas-for-women/ | 49 | 14,9 s | 1669 | 45 | 37 | 5 | 0/0/- | 90 |
| /steampunk-halloween-costume-women/ | 56 | 15,2 s | 1406 | 36 | 24 | 5 | 0/0/- | 90 |
| /steampunk-halloween/ | 55 | 14,4 s | 1115 | 35 | 14 | 3 | 0/0/- | 80 |
| /pirate-cosplay-for-beginners/ | 51 | 15,5 s | 1438 | 26 | 6 | 4 | 0/0/- | 80 |
| /steampunk-cosplay-for-beginners/ | 48 | 14,7 s | 1506 | 16 | 0 | 5 | 0/15/9,3 | 55 |
| /steampunk-cosplay-on-a-budget/ | 56 | 14,3 s | 1051 | 23 | 3 | 5 | 0/15/6,3 | 60 |
| /casual-steampunk-outfits/ | 39 | 18,3 s | 985 | 17 | 0 | 4 | 0/1/6 | 45 |
| /diy-vs-buy-steampunk-goggles/ | 50 | 16,5 s | 1371 | 15 | 1 | 5 | 0/1/7 | 63 |
| /steampunk-accessories/ | 40 | 13,0 s | 1035 | 13 | 0 | 5 | 0/35/16,0 | 35 |
| /how-to-build-...-under-100-.../ | 60 | 5,4 s | 1362 | 29 | 5 | 8 | 0/0/- | 90 |
| /product-category/t-shirts/ | 42 | 12,6 s | 498 | 38 | 44 | 18 | 0/0/- | 83 |
| /product-category/hoodies/ | 51 | 14,2 s | 429 | 33 | 36 | 14 | 0/8/8 | 83 |
| /product-category/steampunk-goggles/ | 57 | 14,3 s | 376 | 15 | 6 | 3 | 0/0/- | 53 |
| /product-category/posters-prints/ | 71 | 4,8 s | 374 | 19 | 15 | 7 | 0/0/- | 73 |
| Produktside (aviator goggles) | 26 | 22,3 s | 624 | 22 | 23 | 6 | 0/0/- | 73 |

**Indholds-score-formel (gennemsigtig, max 100):** Længde ≥1200 ord=25 / 800-1199=15 / 300-799=8 / <300=0 · unikke interne links ≥25=25 / 15-24=15 / <15=5 · billeder ≥6=25 / 2-5=15 / <2=0 · links til /product/ ≥10=25 / 3-9=15 / 1-2=8 / 0=0. Kategori-/shopsider bedømes primært på grid (produktlinks).

## Anbefalinger (talbaseret)
1. **Soft-404-fix (5 URL'er)** — interne links peger på gamle slugs; ret til /product-category/steampunk-goggles/, /product-category/posters-prints/ og aktuelle produktslugs. Kræver godkendelse (URLs) pr. v2.1.
2. **Sitemap 404** — /product/airship-blueprint-poster/ (draft id 29) ligger i sitemap og returnerer 404; udelad fra sitemap eller udgiv.
3. **LCP** — 16/21 sider ≥13 s mobil; unused JS ~478 KiB på tværs; produktside worst (perf 26, LCP 22,3 s, TBT 2.970 ms). W3TC-minify virker stadig ikke på Simply.
4. **Server 508 under last** — 13 URL'er fik 508/503 under samtidig crawl; potentielt checkout- og indexering-risiko ved trafikspidser.
5. **/steampunk-accessories/** — 35 impressions, pos 16,0, 0 produktlinks: højeste klikbare gevinst pr. redigering.
6. **/casual-steampunk-outfits/** og hub — 0 produktlinks + lavest PSI (39/52).
