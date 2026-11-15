# Posters-only i hele butikken (15/11 2026)

**Kort:** Efter ejerens beslutning »vi kører kun posters« er butikken skåret ned til de tre publicerede
plakater. Elleve tøj-, gear- og smykkeprodukter er sat på **kladde** (ikke slettet), 155 links til
kladde-produkter er afkoblet, og de flader der kunne vise dem — forside, navigation, shop, kategorier,
sitemap og søgning — er målt efter. Resultatet: **3 publicerede produkter, 0 døde interne links**
(125/125 URL'er i crawl), 0 døde `/go/`-mål og 0 vandret overflow ved 1440 og 390 px.

Målt 15/11 2026 i den leverede HTML og i browseren, ikke kun i koden.

---

## 1. Sortimentet

| Status | Antal | Bemærkning |
| --- | --- | --- |
| publish | **3** | 965 Clockwork Rose · 966 Brass Luna Moth · 967 Raven Chronometer (395 kr. hver) |
| draft | **90** | de 11 tidligere publicerede ikke-plakater + 79 der allerede var kladder |
| trash | 92 | urørt — intet er slettet i dette pas |

De elleve der blev sat på kladde (kan genoprettes ved at sætte status tilbage til *publish*):

| Id | Produkt | Pris |
| --- | --- | --- |
| 399 | Clockwork Octopus — Curiosity Hoodie | 335 kr. |
| 401 | Whale Airship — Vintage Voyage Hoodie | 335 kr. |
| 403 | Mechanical Elephant — Curiosity Hoodie | 335 kr. |
| 413 | Airship Storm — Vintage Engraving Hoodie | 335 kr. |
| 557 | Airship Armada — Vintage Engraving Tee | 180 kr. |
| 940 | Clockwork Seer — Brass Pocket Watch Pendant | 865 kr. |
| 941 | The Aeronaut — Brass Skeleton Pocket Watch | 1 000 kr. |
| 954 | Brass Luna Moth — Clockwork Herbarium Tee | 530 kr. |
| 970 | Brass Luna Moth — Herbarium Phone Case | 330 kr. |
| 1255 | Brass Fox Automaton — Steampunk Tee | 530 kr. |
| 1282 | Astrolabe Planisphere — Steampunk Tee | 530 kr. |

403 var publiceret, men stod ikke i ejerens liste på ti id'er; den blev taget med, fordi butikken skulle
være tre plakater. De fire kladder 477, 483, 489 og 491 (goggles, top hat, to corsets) blev **ikke**
publiceret — de forbliver kladder.

## 2. Links der pegede på kladder

| Gennemløb | Afkoblede links | Ramte indlæg/sider |
| --- | --- | --- |
| 1. de fire goggles/corset-kladder | 94 | 17 (guide 8, 10, 614, 757, 1357, 1359, 1361, 1394 + 14, 17, 30, 699, 751, 791, 798, 803, 805, 806, 875, 891, 897, 898, 913, 1214) |
| 2. de elleve ikke-plakater | 59 | 18 (15, 30, 572, 615, 699, 791, 803, 806, 891, 897, 898, 913, 973, 974, 989, 994, 1359, 1361) |
| 3. to links der var kommet tilbage i guide 8 | 2 | 1 (8) |
| **I alt** | **155** | 26 unikke indlæg/sider |

Anchor-teksten står tilbage som ren tekst, så sætningerne stadig læser rigtigt. Ingen
`/product/<kladde>/`-links findes i publiceret indhold (målt i rå DB, med tomme slugs frasorteret — 19
plakatkladder har endnu ingen slug og gav falske træf i første optælling).

**Guide 8 var den svære:** to links kom tilbage efter første gennemløb (posten blev ændret igen kl. 14:48),
så den blev kørt igen. Den ligger nu med 0 `/go/`-links.

## 3. Flader

| Flade | Før | Efter |
| --- | --- | --- |
| Forside (side 42) | 6 produktkort: 3 plakater + tee, hoodie, phone case; kolonnen »Tees & Hoodies« og overskriften »Wall Art & Wearable Stories« | 3 plakatkort; kolonnen er skiftet til »Clockwork & Brass«; hero og lede taler kun om prints |
| Navigation (`wp_navigation` 4) | Shop, Posters & Prints, Mechanical Nature, **Tees**, **Hoodies**, Journal | Tees og Hoodies fjernet |
| Header (template part 12) | Shop-undermenu med **Curiosities** og **Airship** | begge fjernet; Herbarium og Posters & Prints står tilbage |
| `/shop/` | 14 produkter | 3 produkter |
| Produktkategorier | 13 kategorier med varer | 6 kategorier med varer; de 31 tomme svarer **302 → /shop/** |
| `product-sitemap.xml` | 21 produkt-URL'er inkl. kladder (cachet) | 3 produkter + `/shop/` |
| `product_cat-sitemap.xml` | 9 kategorier, heraf 8 tomme | 6 kategorier, alle med varer |
| Søgning (`?s=hoodie`, `tee`, `poster` …) | — | 0 træf på skjulte produkter |
| Gaveguiden (804) | 11 links til tee, hoodie, phone case og lommeure | 0; guiden er omskrevet til de tre prints |
| Forsidens titel og tagline | `<title>` »Steampunk Posters, Prints & Apparel«; tagline »Steampunk posters, prints & apparel« | `<title>` og H1 »Steampunk Posters, Prints & Wall Art«; tagline »Steampunk posters, prints & wall art«; og:title/twitter:title samme tekst |
| `/shop/` titel, H1 og social | `<title>` »Steampunk Shop: Posters, Prints, Tees & Hoodies«; H1 »Shop Steampunk Gear & Costumes« | `<title>` »Steampunk Shop: Posters & Prints«; H1 »Shop Steampunk Posters & Prints«; og:title/twitter:title samme tekst |

Tre små mu-plugins bærer de strukturelle rettelser:

- `mu-plugins/sgap-catalog-cleanup.php` — tomme produktkategorier sender **302** til `/shop/` (midlertidig
  redirect, så den forsvinder af sig selv, når en kategori får varer igen).
- `mu-plugins/sgap-layout-fix.php` — `.wp-block-group.alignfull{max-width:100%}`, fordi temaets
  `alignfull`-sidehoved regner scrollbaren med i `100vw` og gav **1 px** vandret overflow på kurv og kasse.
- `mu-plugins/sgap-shop-h1.php` — butikkens H1 kom fra denne fil, ikke fra sidens titel; teksten er
  skiftet til »Shop Steampunk Posters & Prints« (1.1.0), og filen er nu også i repoet.

## 4. Verifikation (kørt 15/11)

| Check | Resultat |
| --- | --- |
| Publicerede produkter (DB) | **3** — 965, 966, 967 |
| Kladder / trash | 90 / 92 (intet slettet) |
| Døde `/go/`-mål i publiceret indhold (rå DB) | **0** |
| Døde interne links — `tools/audit/link_check.py --crawl` | **125 af 125 ok, 0 døde** |
| Plakatlinks 200 | 3/3 |
| Sitemap | 3 produkter + 6 kategorier; ingen kladder |
| Kurv | plakat lagt i kurv via `?add-to-cart=965`, kurven viser Clockwork Rose |
| Kasse | 200 med 7 betalingsfelter, 395 kr. i alt |
| Browser 1440 px | forside, shop, produkt, kurv, kasse: **0 overflow**, 3 produkter i shop |
| Browser 390 px | samme fem sider: **0 overflow**, 2 kolonner i shop |
| JS-fejl | ingen fra sitets egen kode; kun tredjepart (GA/GTM, Stripe payment-request, CSP-report om Google-frame) |
| Forside: `<title>` + H1 | »Steampunk Posters, Prints & Wall Art«, 0 forekomster af »Apparel« i synlig tekst |
| `/shop/`: `<title>` + H1 | »Steampunk Shop: Posters & Prints« / »Shop Steampunk Posters & Prints« |
| `og:title` + `twitter:title` (forside og shop) | prints-only, ingen »Apparel«/»Tees & Hoodies« |
| `php -l` | alle tre nye/ændrede mu-plugins uden syntaksfejl |

## 5. Fælder der kostede en runde

1. **W3TC serverer gammel HTML efter `wp_update_post`.** Selv med `w3tc_flush_all()` kan en side komme
   igen med de gamle links; header-template-parten blev først rigtig, efter `wp_cache_flush()` også.
   Verificér altid med en unik query-parameter og et kig på den konkrete HTML — ikke på databasen alene.
2. **Rank Maths sitemap-cache ligger i filer.** `rank_math_sitemap_cache_files` holdt 21 produkt-URL'er
   med kladder, længe efter produkterne var sat på kladde. `\RankMath\Sitemap\Cache::invalidate_storage()`
   rydder den; derefter viste sitemap 3 produkter.
3. **Tomme slugs giver falske linktræf.** 19 plakatkladder har endnu ingen `post_name`, så et opslag på
   `/product/` + tom streng matcher alt. Filtrér tomme slugs fra, før der konkluderes.
4. **Novamira-tokenet lever 3 600 s.** Midt i passet udløb det og gav 401; `tools/mcp_keepalive.py`
   fornyer og roterer refresh-tokenet. Gør det først, når et kald svarer 401 — ikke som fast rutine.
5. **Et løb med 10 parallelle link-tjek gav falske 503/508** fra Simply. Med `--workers 2` svarede alle
   125 URL'er 200.
6. **Butikkens H1 stod i en mu-plugin, ikke i sidens titel.** `sgap-shop-h1.php` overskriver
   `core/query-title` på shop-arkivet; først da filen blev rettet, skiftede H1 — sidens `post_title`
   blev ændret forgæves. Samme mønster kan gælde andre hardcodede tekster: grep `wp-content` for
   strengen, før der konkluderes «cache».
7. **Rank Maths sociale titler (og:title/twitter:title) hænger efter.** De kommer fra
   `rank_math_facebook_title`/`rank_math_twitter_title` og blev først slået igennem efter endnu en
   flush-runde; mål dem i HTML'en, ikke i meta.

## 6. Stadig åbent

- **Genoprettelse:** hvert produkt kan komme tilbage ved at sætte status fra *draft* til *publish*; ingen
  data er slettet.
- **19 plakatkladder** (dieselpunk- og neodeco-serierne) har ingen slug og er ikke publiceret. De skal
  have deres egen gennemgang, før de kan sælges.
- **Eksterne feeds:** Google Merchant, Pinterest og TikTok kan stadig have de gamle produkter i deres
  katalog, indtil deres næste sync kører. Det er ikke målt her.
- **`sgap_seo_site`-optionen** indeholder stadig de gamle produkt-URL'er som intern SEO-cache; den er ikke
  offentlig og blev ikke rørt.
