# Indholdsaudit 1/11 2026 — posts, sider og produkter

_Kørt 1/11 2026 via MCP-forbindelsen til steamgears.eu (Royal MCP, kun læsende værktøjer) · gælder siden, ikke dette repo_

## TL;DR

- **24 udgivne posts · 8 kladder** — kladserne er alle fra 25.–30. oktober og ligger klar.
- **21 udgivne sider**, heraf 2 PDF-lead-magnets. 3 private designsider (Home V3/V4 + Dark Clockwork-preview) og 1 kladde der er en dublet af den udgivne privatlivspolitik.
- **39 udgivne produkter · 23 kladder · 43 i papirkurven** (105 i alt). Alle udgivne er `instock`.
- Den store kladde-bunke er **Clockwork Herbarium-linjen** (12 SKU'er) plus 3 smykker/ure og 3 DIY/digitale varer — alle med pris og SKU, men historien de hører til (**post 973**) indeholder **ingen produktlinks**.
- **Post 750** (women-guide v2) har ligget som kladde siden 25/10 — den ventede allerede godkendelse i `mangler-og-plan-25-10.md`.
- Ingen skrivende kald er brugt. Alt nedenfor er læst, ikke ændret.

## 1. Sådan er det målt

| Værktøj | Brugt til |
| --- | --- |
| `wp_get_post_types` | Hvilke post-typer der findes (der er ingen custom "guide"-type) |
| `wp_count_posts` | Statusoptælling pr. type |
| `wp_get_posts` / `wp_get_pages` | Lister pr. status (`publish`, `draft`, `private`) |
| `wc_get_products` / `wc_get_product` / `wc_get_product_variations` | Produktstatus, priser, lager, varianter |
| `wp_count_media` / `wp_get_post` | Medietyper og indhold i enkelte posts |

Bemærk: `wc_get_categories` findes ikke i værktøjslisten (kaldet returnerede en fejl), så produktkategorier er ikke talt med her.

## 2. Tal

| Type | Udgivet | Kladde | Andre |
| --- | --- | --- | --- |
| Posts | 24 | 8 | 1 papirkurv, 1 auto-draft |
| Sider | 21 | 1 | 3 private, 3 papirkurv |
| Produkter | 39 | 23 | 43 papirkurv |
| Medier | 245 | — | 214 jpeg, 17 png, 12 webp, 2 pdf |

Post-typer på sitet: `post`, `page`, `product`, `attachment`, `mailpoet_page`. Der er altså **ingen separat guide- eller poster-type** — guides er almindelige posts.

## 3. Posts

De 24 udgivne fordeler sig på: 3 noveller (993, 973, 994 — alle 30/10), Halloween/costume-guides 27/10 (891, 913, 875), en stribe 26/10-guides (806, 805, 804, 803, 798, 791), 25/10 (757, 751) og grundstammen fra 16.–21. oktober (8, 10, 14, 15, 17, 30, 614, 615, 699, 572).

### Kladder (8)

| ID | Dato | Titel | Note |
| --- | --- | --- | --- |
| 989 | 30/10 | What Is Gaslamp Fantasy? Victorian Magic, Explained | Ny vinkel, ingen konflikt |
| 988 | 30/10 | The Best Steampunk Books: A Reading Guide | Ny vinkel |
| 974 | 29/10 | What Is Dieselpunk? A Guide to the Diesel & Deco Aesthetic | Overlapper 938 og udgivne 798 |
| 938 | 27/10 | Guide: Dieselpunk Steampunk | Titel ligner et arbejdsudkast |
| 898 | 27/10 | Steampunk Couple Costumes: 10 Pairs Ready to Buy (2026) | Delvist overlap med 891/913 |
| 897 | 27/10 | Steampunk Outfit Ideas for Men: 12 Complete Looks (2026) | Mandlig modpart til udgivne 699 — mangler reelt |
| 789 | 26/10 | Steampunk Accessories for Men: The Complete 2026 Checklist | Overlapper udgivne 30 |
| 750 | 25/10 | Steampunk Outfit Ideas for Women: 12 Complete Looks (2026) | **Dublet af udgivne 699** — ventet siden 25/10 |

## 4. Sider

- **Udgivet (21):** forside/About Steamgears 35, shop 22, kurv 23, kasse 24, min konto 25, Contact 36, FAQ 580, Cookie Policy 583, Delivery &amp; Shipping 582, Privacy Policy 37, Refund and Returns 26, Terms of Sale 581, Opt-out 872, Size Guide 597, Cosplay Gallery 32, Workshop Journal 41, Steampunk Costumes 550, Gear &amp; Costumes 42, Halloween 713 — og de to lead-magnets: **Starter Kit (801)** og **Thrifting Hacks (764)**.
- **Private (3):** 692 "Design Preview: Dark Clockwork Theatre", 870 "Home V3 — Clocktower Edition", 871 "Home V4 — Workshop Edition" — layout-staging, kan ryddes når designet er valgt.
- **Kladde (1):** 72 "Privacy Policy" — dublet af udgivne 37.
- **Papirkurv (3).**

## 5. Produkter

- **39 udgivne**, alle `instock`, 23 variable og 16 simple, prisinterval 0–64,95. Gratis vare: 809 ("Steampunk Costume Checklist", lead magnet).
- **23 kladder**, alle med pris og SKU. Variable produkter (75, 183) viser tom `regular_price` på forældreniveau — prisen ligger på de 5 variationer hver; det er WooCommerce-normalen, ikke manglende priser.
- **43 i papirkurven.**

### Kladde-produkter grupperet

| Gruppe | ID'er | Pris |
| --- | --- | --- |
| Clockwork Herbarium — tees | 953–956 | 26,95 |
| Clockwork Herbarium — art prints | 965–968 | 29,95 |
| Clockwork Herbarium — phone cases | 969–972 | 24,95 |
| Atelier-smykker/ure (SG-ATELIER-JW-01…03) | 940, 941, 942 | 89 / 149 / 69 |
| DIY &amp; digitalt | 765, 766, 767 | 29,90 / 24,90 / 6,90 |
| Ældre enkeltvarer | 27, 28, 29, 75, 183 | 29,95 / 59,95 / 19,95 / 26,95 / 26,95 |

## 6. Fund — hvad der bør gøres

1. **Herbarium-salgskæden er åben i den forkerte ende.** Novellen 973 er udgivet, men indeholder ingen `/product/`-links, og de 12 Herbarium-varer er kladder. Enten udgiv varene og link fra historien, eller lad dem ligge bevidst.
2. **Post 750** er en dublet af udgivne 699 og har ventet siden 25/10. Beslut: flet, omskriv eller slip.
3. **Dieselpunk-kladserne** 974 og 938 dækker samme emne som udgivne 798 — vælg én vinkel før udgivelse, ellers kannibalisering.
4. **Oprydning:** 43 produkter og 3 sider i papirkurven, 3 private designsider, og kladde-side 72 der dubletterer 37.
5. **Titel-tjek:** 757 hedder "…(Complete 2027 Workshop…)" men er udgivet i 2026; 938 er en arbejdstitel.

## 7. Ikke undersøgt

- Ingen skrivende værktøjer kaldt — ingen statusændringer, ingen sletninger.
- Ingen ordre-, omsætnings- eller lagerbevægelsesdata hentet (kun `stock_status` pr. produkt).
- Ingen SEO-metrics, GSC-data eller crawl af de udgivne sider; kun det der står i WordPress-databasen.
- Produktkategorier kunne ikke tælles (værktøjet findes ikke i MCP-listen).
