# Butikken var nede i 1 t 38 min — hvad der skete, og hvad der stadig mangler

**Målt og udført 6/11 2026, ca. 21:30–21:45 UTC.** Alt nedenfor er aflæst på det levende site gennem
Novamira MCP (WP-CLI og `execute-php`), ikke antaget ud fra repoet. Tal der ikke kunne måles står som
"ikke målt".

## 1. Hvad der var galt

Da jeg gik i gang med navigation og intern linkning, viste en rutinemæssig tælling af publicerede
indlæg og sider, at WooCommerce ikke var indlæst: `defined('WC_VERSION')` var falsk, og
`post_type_exists('product')` svarede nej — i **alle** PHP-kontekster, også Novamiras egen.

| Måling mens butikken var nede | Svar |
| --- | --- |
| `/product/astrolabe-planisphere-tee/` | **404 · “Page Not Found”** |
| `/product-category/hoodies/` | **404 · “Page Not Found”** |
| `/wp-json/wc/store/v1/products` (Store API) | **404** |
| `/product-sitemap.xml` | HTTP **200**, men brødteksten var temaets 404-side (“Page not found”) |
| `/sitemap_index.xml` | 200 med **3** sitemaps: post, page, category — ingen produkter |
| `/shop/`, `/cart/`, `/checkout/` | **200** — serveret fra sidecachen |

Det sidste er fælden: **forskellen mellem “sitet svarer” og “butikken virker”**. Forsiden, butikssiden,
kurven og kassen svarede alle 200, fordi W3TC havde dem på disk. Det var kun de URL'er, der *skal*
genereres af WooCommerce — produkt, produktkategori, Store API — der afslørede, at butikken var væk.
En statuskode-crawl af de “vigtige” sider ville have erklæret sitet raskt.

## 2. Hvornår og hvordan

WordPress gemmer deaktiverede plugins i optionen `recently_activated` med et tidsstempel. Den viste
fire plugins slået fra på samme sekund:

| Plugin | Version | Deaktiveret |
| --- | --- | --- |
| `woocommerce/woocommerce.php` | 11.2.2 | 2026-11-06 **19:57:14 UTC** |
| `woocommerce-gateway-stripe/…` | 11.1.1 | 19:57:14 UTC |
| `pinterest-for-woocommerce/…` | 1.6.2 | 19:57:14 UTC |
| `google-listings-and-ads/…` | 3.10.0 | 19:57:14 UTC |

Fire butiks-plugins på samme sekund er en **batch**, ikke fire uafhængige fejl. WooCommerce stod på
11.2.2 mens `woocommerce_db_version` stadig var 11.2.1, så et opdateringsforsøg er den nærmeste
forklaring — men hvem eller hvad der udløste batch-deaktiveringen, kan ikke aflæses herfra: der findes
ingen `debug.log` (`WP_DEBUG_LOG` er ikke slået til), og `recently_activated` gemmer kun tidspunktet.
**Det er den vigtigste mangel i denne rapport:** årsagen er ikke fastslået, kun virkningen og tidspunktet.

Butikken var altså nede i ca. **1 time og 38 minutter** (19:57 → 21:35 UTC), midt på aftenen dansk tid.

## 3. Hvad jeg gjorde

Genaktiveret i denne rækkefølge, med måling efter hver: WooCommerce → Stripe → Pinterest →
Google for WooCommerce. Ingen filer blev ændret, intet blev slettet, og hver aktivering blev bekræftet
med `active: true` fra `set-plugin-state`.

**Verifikation bagefter (alt målt, ikke påstået):**

| Kontrol | Svar |
| --- | --- |
| `WC_VERSION` | 11.2.2, `product`-posttypen registreret |
| `/wp-json/wc/store/v1/products` | **200** (6 893 byte JSON) |
| `/product/astrolabe-planisphere-tee/` | **200**, titel “Astrolabe Planisphere — Steampunk Tee”, købsknap i HTML |
| `/product-category/hoodies/` | **200**, titel “Steampunk Hoodies & Jackets” |
| `/product-sitemap.xml` | **200** med **21** URL'er |
| `/product_cat-sitemap.xml` | **200** med **4** URL'er |
| Betalingsmetoder (`get_available_payment_gateways()`) | `stripe`, `bacs` |
| Produkter i butikken | **20 publiceret · 46 kladde · 50 i papirkurv** |

De 20 publicerede produkter er et fald fra **39** målt 2/11. Om det er rebrand-arbejdet, der har lagt 19
produkter tilbage til kladde, eller om noget andet har flyttet dem, er **ikke afklaret** her — men det
bør afklares, fordi hver af dem var en salgsside.

## 4. Hvad der stadig ikke virker: sitemap-indekset

`product-sitemap.xml` og `product_cat-sitemap.xml` svarer nu 200 hver for sig, men
`sitemap_index.xml` lister dem **stadig ikke**. Indekset indeholder kun post, page og category.

Det er prøvet og virker ikke: W3TC `flush_all`, Rank Maths sitemap-cache, `flush-rewrites`,
`flush-caches`, og at slå `pt_product_sitemap` fra og til igen gennem `set-sitemap-settings`
(`saved: true, updated: ["post_types"]`). Indekset bliver ved med at have tre poster, og det serveres
med Apache-headere (`etag`, `accept-ranges`, `last-modified`), altså som en fil — ikke som PHP-svar.

Den mest sandsynlige forklaring er rækkefølgen af `init`-hooks: WooCommerce registrerer `product` og
`product_cat` senere end Rank Math bygger indekset, så indekset dannes før de findes, mens den direkte
URL til hver sitemap resolver efter `parse_request` og derfor virker. **Det er en hypotese, ikke en
måling.** Rettelsen vil være et filter (`rank_math/sitemap/index`) i en mu-plugin — det er en
kodeændring på live-sitet og skal godkendes af ejeren først, så den er ikke lavet.

Konsekvensen er konkret: Google opdager sitemaps gennem indekset, så **de 20 produkter og 4
produktkategorier er ikke annonceret** i det dokument, søgemaskinen læser først.

## 5. To tomme testindlæg var publicerede

Samme måling fandt to publicerede indlæg uden indhold fra 2/11 kl. 00:19:

| ID | Slug | Indhold | Indgående links |
| --- | --- | --- | --- |
| 789 | `steampunk-accessories-for-men-the-complete-2026-checklist-budget-to-premium` | `<p>t3</p>` (9 tegn) | **0** |
| 898 | `steampunk-couple-costumes` | `test` (4 tegn) | **3** — `/start-here/` (1183), `/where-to-wear-it/` (1211), `/inspiration/` (1243) |

**789 er sat til kladde** (URL'en svarer nu 404, den havde ingen indgående links, og den var i post-sitemappen).
**898 er bevidst ikke rørt:** de tre hub-sider lover alle “10 couple costumes — pairs that read as one
story”, så en kladde ville gøre tre publicerede løfter til døde links. Valget står mellem at skrive
artiklen eller fjerne løftet fra de tre sider, og det er en indholdsbeslutning, ikke en opsætningsfejl.

## 6. Hvad der blev rettet i opsætningen

| Ændring | Bevis |
| --- | --- |
| `/shipping/` (1396) og `/returns/` (1397) ind i footerens Support-kolonne | Footeren (template part 52) gik fra 4 000 → 4 140 tegn, og begge links optræder **præcis 1 gang** på `/`, `/shop/` og `/faq/` |
| Corset-guiden (1394) ind i hubbens liste “Every Steamgears costume guide” på `/steampunk-costumes/` (550) | 1394 havde **0** indgående interne links, nu **1** |
| Tomt testindlæg 789 → kladde | URL 404, væk fra sitemappen |

## 7. Læren

1. **En 200 er ikke et bevis.** Butikssiden, kurven og kassen svarede 200 hele tiden, fordi de lå i
   sidecachen. Det var `WC_VERSION` og en produkt-URL, der afgjorde sagen.
2. **`recently_activated` er det eneste spor efter en batch-deaktivering.** Det giver sekundet, men ikke
   årsagen — derfor bør `WP_DEBUG_LOG` være slået til, så næste gang har vi en stak at læse.
3. **Et sitemap-indeks er ikke det samme som sitemaps.** Alle enkelt-sitemaps kan svare 200, mens
   indekset — det dokument Google først læser — udelader dem.
4. **Tjek butikkens kerne, ikke dens forside,** når butiks-plugins er rørt: `WC_VERSION`,
   Store API, én produkt-URL, én produktkategori og indekset.
