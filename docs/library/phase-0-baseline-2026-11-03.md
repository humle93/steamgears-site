# Fase 0-baseline — steamgears.eu

**Dato:** 2026-11-03, kl. 21:05–21:12 UTC
**Formål:** fase 0 i [`deploy-roadmap-2026-11-03.md`](deploy-roadmap-2026-11-03.md) — bekræft audittens kritiske fund med friske målinger, før nogen produktionsændring.
**Metode:** read-only HTTP med `curl`, UA `Mozilla/5.0 (compatible; steamgears-audit)`, ingen cookies, ingen login, ingen JavaScript. To målinger pr. URL med ca. 5 minutters mellemrum. Der er **ikke** ændret noget på sitet.

## Hvad målingen kan og ikke kan

Kan: HTTP-status, HTML-størrelse, svarstid for selve HTML, titler, H1-antal, schema-indhold, plugin- og tag-spor i kilden, pris/valuta i markup.

Kan ikke: Core Web Vitals (kræver browser), checkout-flow med kurv (kræver session), GA4-hændelser i realtid, GSC/Crawl Stats, Printful-mapping og gateway-godkendelser. De punkter står stadig åbne, uanset hvad tabellerne nedenfor viser.

## 1. Status på de vigtige sider

| URL | Status | HTML | Svarstid (måling 1 / 2) |
| --- | ---: | ---: | --- |
| `/` | 200 | 251 KB | 2,2 s / 3,6 s |
| `/shop/` | 200 | 475 KB | 2,9 s / 6,5 s |
| `/cart/` | 200 | 472 KB | 4,9 s / 5,2 s |
| `/checkout/` | 302 → `/cart/` | — | 1,4 s |
| `/product/nightshade-apothecary-herbarium-phone-case/` | 200 | 450 KB | 3,8 s |
| `/shipping/` | **404** | 235 KB | 3,6 s |
| `/returns/` | **404** | 235 KB | 6,4 s |
| `/terms-and-conditions/` | **404** | 235 KB | — |
| `/refund_returns/` | 200 | — | — |
| `/privacy-policy/` | 200 | — | — |
| `/cookie-policy/` | 200 | — | — |
| `/contact/` | 200 | — | — |
| `/about/` | 200 | — | — |

`/checkout/` svarer 302 til kurven, når kurven er tom — det er WooCommerce-adfærd og ikke et fund i sig selv. Checkout med varer i kurven kan ikke måles uden en session.

**Side-sitemap:** 25 sider. Sitemap-indekset har post-, page-, product-, category-, product_cat- og author-sitemap.

## 2. Produktside — pris, schema og indhold

Målt på `nightshade-apothecary-herbarium-phone-case`, det første produkt i `product-sitemap.xml`:

| Måling | Resultat |
| --- | --- |
| `Offer.price` / `priceCurrency` | `24.95` / **`USD`** |
| `priceValidUntil` | `2027-12-31` |
| `availability` | `InStock` |
| `shippingDetails` i schema | **0 forekomster** |
| `MerchantReturnPolicy` i schema | **0 forekomster** |
| `reviewCount` / `aggregateRating` | **ingen** |
| Galleribilleder (`woocommerce-product-gallery__image`) | **2** |
| H1 på siden | 1 |
| Schema-typer i alt | Product, Offer, ItemPage, BreadcrumbList, WebSite, Organization, ImageObject, ListItem |

## 3. Måling, tags og samtykke (forsiden)

| Måling | Resultat |
| --- | --- |
| GA4-id | `G-3MYLFKY8MY`, ét `gtag('config', …)`-kald |
| Google Ads | `AW-18518250875` (3 forekomster) |
| Google Tag | `GT-PL9L67B4` som loader |
| `gtag(`-kald i alt | 12 |
| Meta Pixel | 0 forekomster |
| Consent-værktøj | Complianz aktiv (`complianz-gdpr`), 162 `cmplz`-markører |
| Consent-konfiguration | `region: ['AT', …]` og `region":"us"` — dvs. en regionsopdelt opsætning |
| Plugins synlige i kilden | woocommerce, ai-engine, complianz-gdpr, tiktok-for-business, google-site-kit, google-listings-and-ads |

Der er **ét** GA4-config-kald i den statiske HTML. Den tidligere dobbelte pageview kan derfor ikke bekræftes herfra — den skal afgøres i browserens Network-panel eller i GA4's realtidsvisning med samtykke givet. Det er en ændring i forhold til audittens formulering og skal verificeres, ikke antages.

## 4. Fund mod auditten

| Audit-fund | Status efter målingen |
| --- | --- |
| P0 juridisk identitet mangler | **Bekræftet.** Hverken `/contact/`, `/about/`, `/privacy-policy/` eller `/refund_returns/` indeholder CVR-nummer, `+45`-telefon eller adresse. |
| P0 cookie-samtykke er US/opt-out | **Delvist bekræftet.** Complianz kører med en regionsopdelt konfiguration, og `us` optræder som region. Om EU-besøgende får opt-in, kan kun afgøres ved at teste banneret i browser. |
| P0 valuta og samlet pris | **Bekræftet.** Produktets Offer står stadig til `USD 24.95`. |
| P0 checkout og levering | **Kan ikke afgøres.** Kræver kurv, session og en testordre. `/shipping/` og `/returns/` er desuden 404. |
| P1 produktkatalog (billeder, størrelser) | **Bekræftet i stikprøven.** To galleribilleder på det målte produkt. Størrelsesvalg og varianter kræver gennemgang af flere produkttyper. |
| P1 performance | **Bekræftet som HTML-tung.** 450–475 KB HTML alene på shop, kurv og produktside; 2,2–6,5 s for HTML uden assets. Det er dårligere end begge de tidligere målinger og skal måles ensartet i fase 5. |
| P1 schema mangler fragt/retur | **Bekræftet.** Nul `shippingDetails` og nul `MerchantReturnPolicy` på det målte produkt. |
| P1 0 anmeldelser | **Bekræftet.** Intet `aggregateRating` i markup. |
| P1 GA4 dublerer pageview | **Ikke bekræftet.** Ét config-kald i HTML; kræver browserverifikation. |
| Manglende politik-sider | **Nyt, konkret:** `/shipping/` og `/returns/` giver 404, selv om copy blev meldt publish-klar 2/11. `/refund_returns/` findes, men har et maskinelt slug og er ikke den side, teksterne peger på. |

## 5. Hvad der stadig er blokeret

- Live-skriveadgang (MCP/OAuth eller application password) — uden den kan fase 1–6 ikke udføres, kun specificeres.
- Staging og backup-adgang hos Simply.
- GSC/Crawl Stats, GA4-realtid og browserbaseret Lighthouse/CWV.
- Printful-variantkort, fragtprofiler og gateway-godkendelser.
- Ejergodkendte virksomhedsoplysninger (navn, CVR, adresse, telefon, kundeserviceadresse).

## 6. Næste skridt

1. **Fase 1-porten:** test en gendannelse fra backup, og skriv ned hvordan `/shipping/`, `/returns/` og priser rulles tilbage.
2. **Fase 2:** beslut valuta (DKK/EUR), og få de juridiske oplysninger fra ejeren — de kan ikke gættes.
3. **Fase 3:** genberegn priser mod Printful-kostpris, og test en ordre i et kontrolleret flow.
4. **Før nogen af dem:** tag en frisk backup, og gem de nuværende indstillinger, så porten i roadmappen er opfyldt.

Alle tal ovenfor er fra 2026-11-03 og skal genmåles samme dag som en ændring udføres — de er et grundlag, ikke en varig sandhed.
