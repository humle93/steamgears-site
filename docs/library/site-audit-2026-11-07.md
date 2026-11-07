# Site-audit 7/11 2026 — kun det der stadig kan måles

**Status: sitet er nede for alle dynamiske requests.** `managed-wordpress-robots.php` har en parse-fejl, så
`/cart/`, `/checkout/`, `/wp-json/`, `/robots.txt` og `/sitemap_index.xml` svarer **193 B `text/html`** med
`Parse error`. Derfor er denne audit en **cache-audit**: kun de to sider W3TC stadig leverer fra cachen —
`/` og `/shop/` — kan måles. De 30 øvrige sider, produkterne, JSON-LD på produktsider og hele
købsflowet kan ikke auditeres, før filen er rettet (se
[robots-og-nedbrud-2026-11-07.md](robots-og-nedbrud-2026-11-07.md) §4).

## 1. Hvad der blev målt

Hentet 7/11 2026 med `curl` og `User-Agent: SteamgearsAudit/1.0`, talt på den rå HTML.

| Måling | `/` | `/shop/` |
| --- | --- | --- |
| HTML | **80.289 B** | **257.047 B** |
| `<title>` | 51 tegn | 51 tegn |
| Meta description | 143 tegn | 126 tegn |
| Canonical | `https://steamgears.eu/` ✓ | `https://steamgears.eu/shop/` ✓ |
| Robots-meta | `index, follow` | `index, follow` |
| `og:`-tags | 13 | 12 |
| `<h1>` | 1 — «Steampunk Gear & Costumes – Shop the Looks» | 1 — **«Shop»** |
| `<h2>` | 5 | **1** |
| Billeder | 7, **0 uden alt** | 17, **0 uden alt** |
| Scripts | 28 (14 med `src`) | **43 (24 med `src`)** |
| Inline CSS | 13.323 B | **28.409 B** |
| JSON-LD | 1 blok: Organization, WebSite, WebPage, **Person, Article** | 2 blokke: BreadcrumbList, CollectionPage, BreadcrumbList |
| Synlige ord | 673 | **490** |
| Links | 59 (46 interne) | 96 (84 interne) |

**Headers (begge sider):** HTTP/2 200, `server: Simply.com`, `strict-transport-security: max-age=31536000;
includeSubDomains; preload`, `x-content-type-options: nosniff`. **Ingen** `content-security-policy`,
`x-frame-options`, `referrer-policy` eller `permissions-policy`.

## 2. Fund, i den rækkefølge de koster noget

1. **Forsiden udgiver sig for at være en artikel.** JSON-LD'en på `/` indeholder `Person` og `Article` ved
   siden af `Organization`/`WebSite`. En forside der sælger tøj og udstyr skal ikke bære en artikel-entitet —
   det er Rank Maths standard-skema, der ikke er overstyret. Det er samme lag som **S4.13** (merchant-schema)
   og bør rettes i samme pas: `ItemList`/`CollectionPage` på `/shop/` og `Organization` + `WebSite` på `/`,
   uden `Article`.
2. **`/shop/` har «Shop» som H1 og præcis én H2.** Det er den side der skal rangere på «steampunk shop» og
   dens produkter, og den har hverken struktur eller tekst at rangere med: **490 synlige ord** fordelt på
   20+ produkter er tomt. En H1 med vareord («Steampunk Costumes, Tees & Goggles») og 3–4 H2-blokke
   (kategorier, størrelser, levering, materialer) er den billigste SEO-gevinst på sitet.
3. **Siden vejer 257 KB og har 43 scripts** mod forsidens 80 KB og 28. Det bekræfter **S4.11** (målt 5,8 s mod
   2,1 s) fra den cachede HTML alene: forskellen ligger i selve markupen, ikke i netværket. 24 eksterne
   scripts på en produktliste er React-parret, Woo-blokkene, sporings-laget og chatbot; **S2.7**s loft
   (30/45/95/112) er stadig den beslutning, der afgør om det kan bringes ned.
4. **Manglende sikkerhedsheaders.** HSTS og `nosniff` er der; CSP, `X-Frame-Options`, `Referrer-Policy` og
   `Permissions-Policy` er ikke. Det er ikke et roadmap-punkt i dag, men det er den slags der bliver et krav
   ved en PCI-gennemgang, og det koster fire linjer i `.htaccess` eller et mu-plugin.
5. **Cachen er det eneste, der holder butikken åben.** `/` og `/shop/` svarer 200 og er intakte, mens alt
   dynamisk er dødt. Det er præcis derfor en statuskode-crawl ville kalde sitet raskt — samme fælde som
   6/11, hvor `/shop/`, `/cart/` og `/checkout/` svarede 200 fra cachen, mens produkterne var 404. **En
   audit skal altid hente mindst én dynamisk URL og kontrollere `content-type`.**

## 3. Hvad der ikke kunne auditeres

- **Produktsider og deres JSON-LD** — 20+ produkter, `shippingDetails` og `MerchantReturnPolicy` (S4.13).
- **`/robots.txt` og `/sitemap_index.xml`** — og dermed om de 20 produkter overhovedet er annonceret.
- **Kurv og kasse** — Printify-rater, W3TC-udelukkelse, betalingsmetoder (S4.6, S4.5).
- **De 24 indlæg** — meta-titler, descriptions, fokusnøgleord og OG-billeder (S2.5).
- **Mediefilerne** — 245 filer, webp-dækning og alt-tekst uden for de to sider (S3.3).
- **Alle 121 sitemap-URL'er** — S0.7's crawl kan ikke gentages, før sitet svarer.

## 4. Rækkefølge når sitet svarer igen

1. Ryd filen (30 sekunder, tre veje i nedbruds-dokumentet) og bekræft at `/wp-json/` svarer
   `application/json` og `/robots.txt` én `User-agent`-gruppe.
2. **S0.2** — læg den rettede fil ind; den er testet offline, 8 PASS / 0 FAIL
   ([`tools/test_robots_muplugin.php`](../../tools/test_robots_muplugin.php)).
3. **S4.13** — merchant-schema ud, så produkterne får levering og returret i JSON-LD.
4. **S2.5** — Rank Math-meta på de 24 indlæg, og fjern `Article` fra forsidens skema i samme pas (fund 1).
5. **S2.6** — intern linking fra historier og guides til produkter og hubs.
6. **S3.3** — mediepasset: 245 filer, webp, alt-tekst.
7. **`/shop/`-teksten** (fund 2) — H1 og 3–4 H2-blokke; hører under S2.4's beslutning om butiks- eller
   værkstedsrammen, fordi H1'en er den samme beslutning.
