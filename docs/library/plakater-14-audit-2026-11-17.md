# Alle 14 plakater — browser-, kurv- og link-audit

**17. november 2026** · steamgears.eu · 14 publicerede plakater

## Formål

Butikken har 14 publicerede plakater. Denne audit skulle måle alle produktsider i en rigtig browser ved
desktop og mobil, kontrollere at pris og begge størrelser kan lægges i kurven, gennemgå kurv og
checkout-start, crawle alle interne links — og rette fejlene, ikke bare beskrive dem.

Målingen er lavet mod det levende site med browserens egen session, så JavaScript, billeder, fonte,
WooCommerce-blokke og Stripe-elementer blev indlæst som hos en kunde.

## Resultat efter rettelser

| Måling | Resultat |
| --- | --- |
| Produktsider, 1440 × 900 og 390 × 844 | 14 af 14, alle med præcis **1 H1**, **0 horisontal overflow**, **0 manglende alt-attributter**, **0 ødelagte billeder** og **0 samme-origin 4xx/5xx** |
| JavaScript | 0 `pageerror` på de målte sider; ingen samme-origin konsolfejl i den afsluttende måling |
| Størrelser | Alle 14 har **30 × 40 cm** og **50 × 70 cm**; begge kan vælges og lægges i kurven |
| Kurv | **28 linjer / 28 enheder / 7.848,00 kr.** — 14 plakater × 2 størrelser |
| Kurv og checkout | 1 H1, 0 overflow, 0 manglende alt, 0 ødelagte billeder, 0 samme-origin fejl ved 1440 og 390 |
| Interne links | **95 URL'er crawlet: 95 × 200, 0 døde, 0 uventede redirects** |
| Shop | 14 kort, “Showing all 14 results”, 1 H1, 0 overflow |
| Collections | 200 med **11 varer** efter rettelsen (før: 302 til `/shop/`) |

## Fejl fundet og rettet

### 1. `collections` blev sendt til `/shop/` med 11 varer

`mu-plugins/sgap-catalog-cleanup.php` 1.0.0 behandlede en kategori som tom, når `$term->count` var 0.
`collections` har 0 direkte varer, men 11 i underkategorien `clockwork`; WooCommerce viser dem på
forældresiden. Redirecten skjulte altså 11 varer.

**Rettelse:** version 1.1.0 tæller hele undertræet med `get_term_children()` og redirecter kun, når
hverken kategorien eller en efterkommer har varer. Helt tomme kategorier som `gaslight` redirecter stadig.
**Verifikation:** `/product-category/collections/` svarer 200, H1 “COLLECTIONS”, 11 produkter.

### 2. Hvid-på-hvid totaler i kurv og checkout

Temaets lyse summary-panel arvede den cremefarvede tekstfarve fra den mørke side. `CART TOTALS`,
`Estimated total`, momslinjen, `Free`, `Order summary` og totalerne var derfor praktisk talt usynlige.

**Rettelse:** Additional CSS (post 1969) giver mørk tekst i `.wc-block-cart__sidebar` og
`.wc-block-checkout__sidebar`, inkl. `Cart totals`-overskriften. Ingen tema-filer blev ændret.
**Verifikation:** alle tekstknuder i begge sidepaneler har `rgb(29, 26, 21)`; totalerne står nu med
**7.848,00 kr.** og moms **1.569,60 kr.**

### 3. Mobil-kurven klippede total-kolonnen

Ved 390 px var kurvtabellen 341 px bred i en 306 px container. Sidens `overflow` var 0, fordi tabellen selv
klarede overfløden — men kunden så kun begyndelsen af “kr.395.00” ude til højre.

**Rettelse:** ved max-width 600 px skjules den overflødige linje-total-kolonne; prisen står allerede under
produktnavnet. Tabellen måler nu **306/306 px** ved 390 px, og desktop viser stadig total-kolonnen.
**Verifikation:** mobilskærmbillede efter rettelsen viser hele produktnavnet, prisen, størrelsen og
antal-kontrollen uden klip.

### 4. De tre gamle prints havde kun én størrelse

965 `Clockwork Rose`, 966 `Brass Luna Moth` og 967 `Raven Chronometer` var `simple` produkter til 395 kr.
uden størrelsesattribut. De tre andre produkter i serien er variable.

**Rettelse:** de er konverteret til `variable` med `Size` = 30 × 40 cm / 50 × 70 cm og præcis to
variationer hver, alle 395 kr., `instock`. Variationerne er 1971/1972, 1973/1974 og 1975/1976. Den gamle
tilstand er gemt i option `sgap_size_backup_20261117`, så konverteringen kan rulles tilbage.
**Verifikation:** begge størrelser kan vælges og lægges i kurven; produkterne har 1 H1, 0 overflow,
0 manglende alt og ingen konsolfejl ved 1440 og 390.

**Åbent ejerpunkt:** 50 × 70-prisen for de tre gamle prints er sat til **395 kr.**, samme som den
nuværende enkeltpris. Det er et bevidst konservativt valg, ikke en målt pris fra ejeren. Skal 50 × 70
koste mere, rettes de tre variationer 1972, 1974 og 1976.

### 5. Kategoriteksterne passede ikke til en posters-only-butik

`steampunk-gifts` havde `<title>` “Steampunk Gifts | T-Shirts, Hoodies & Gear” og en beskrivelse om
t-shirts og hoodies, selv om alle 14 varer er plakater. `collections` beskrev tees, art prints, phone cases
og Atelier pieces.

**Rettelse:** Rank Math-termtitlen er nu “Steampunk Gifts | Posters & Wall Art”; begge beskrivelser handler
om art prints og plakater. Navn og slug er uændret.
**Verifikation:** kategorisiden har 14 varer, 1 H1 og den nye titel.

### 6. Amazon Pay gav 500 i express checkout

Express checkout oprettede et Amazon Pay-element, som kaldte
`apay-us.amazon.com/amazonpayMerchantId`, og browseren fik gentagne **500**-svar. Amazon Pay var ikke en
del af de valgte betalingsmetoder i WooCommerce Stripe (kun `card` og `link`), men blev alligevel vist.

**Rettelse:** Amazon Pay-knapperne er fjernet fra express checkout via den understøttede Stripe-indstilling
`amazon_pay_button_locations = []`. Kort, Link og Google Pay er urørt.
**Verifikation:** den friske checkout har 0 Amazon-knapper, 0 Amazon-kald og ingen Amazon-fejl i konsollen;
Google Pay og Link vises stadig. Hvis ejeren vil tilbyde Amazon Pay, skal Stripe-forbindelsen og
merchant-ID'et undersøges, før knappen tændes igen.

## Link-crawl

Crawlen startede fra `/shop/`, en produktside, `/product-category/collections/`, `/cart/`, `/checkout/`,
sitemap-indekset og dets undersitemaps. Den samlede 95 unikke interne URL'er fra header, footer,
brødkrummer, produkter, kategorier og indlæg.

- **95 × HTTP 200**
- **0 døde links**
- **0 soft-404**
- **0 uventede redirects**

Den tomme kategori `/product-category/collections/gaslight/` redirecter fortsat bevidst til `/shop/` med
302; det er plugin'ets tilsigtede opførsel for en kategori uden varer.

## Tredjepartsstøj, som ikke er butikkens fejl

- **508 “Resource Limit Is Reached”** blev set én gang på kolde W3TC-minify-assets. Efter cache-opvarmning
  og flere flushes er 508 ikke set i de afsluttende målinger. Årsagen er ikke endeligt fastslået; det er en
  server-/cache-risk, ikke en fejl i produkt- eller kurvkoden.
- **hCaptcha 429** på checkout kommer fra `api.hcaptcha.com` og er en tredjepartsrate-limit; siden og
  betalingsmetoderne renders korrekt.
- **Google MerchantVerse 502** blev set på checkout fra `google.com/shopping/merchantverse/`; det er Googles
  widget, ikke et same-origin-kald.
- Browserens **“SSL certificate is not valid. Security level: NONE.”** er cloud-browserens
  TLS-intercept, ikke sitets certifikat.

## Beskyttede data

De 11 nye plakater (1843–1873) er ikke ændret i titel, slug, pris eller publiceringsstatus. De 8
Diesel Age-plakater og de øvrige kladder er ikke rørt. Ændringerne på de tre gamle prints er begrænset til
produkttype, størrelsesattribut og de to nye variationer; titel, slug, status og prisniveau er bevaret.

## Filer og live-ændringer

- `mu-plugins/sgap-catalog-cleanup.php` — 1.1.0, child-aware tomhedstest.
- Additional CSS (WordPress post 1969) — læsbarhed i kurv/checkout samt mobil kurvkolonne.
- Live WooCommerce: 965/966/967 konverteret til variable med to variationer hver.
- Live Rank Math/WooCommerce: `steampunk-gifts`-titel og to kategoribeskrivelser.
- Live Stripe-indstilling: `amazon_pay_button_locations = []`.
- Backup-options: `sgap_size_backup_20261117`, `sgap_amazon_pay_locations_backup_20261117`.

## Gentag målingen

1. Åbn `/shop/`, alle 14 produkter, kategorierne, `/cart/` og `/checkout/` i en browser ved 1440 og 390.
2. Vælg begge størrelser på alle 14 og kontrollér, at kurven ender på 28 linjer / 7.848 kr.
3. Crawl de samme 95 URL'er med cache-busting og kontrollér, at ingen giver 4xx/5xx eller soft-404.
