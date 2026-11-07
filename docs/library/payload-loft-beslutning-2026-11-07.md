# Loft pr. skabelon — beslutningsgrundlag for S2.7 (målt 7/11 2026)

S2.7's skrevne budget er **< 100 KB HTML, < 20 `<script>`-tags og ingen inline-blok over 4 KB** pr.
side. Det tredje kriterium er opfyldt (3.455–4.064 B). Denne måling svarer på de to andre: hvad
består script-tallet af, hvad kan fjernes uden at fjerne funktioner, og hvad er det reelle gulv —
så loftet kan besluttes på tal i stedet for på et ønske.

## 1. Hvor de 51/105/120 scripts kommer fra

Målt 7/11 med neutral UA på `/?sga=1`, produktsiden og `/cart/?sga=1`, hvert `<script>` grupperet
efter plugin eller host (eksterne `src`) plus inline-blokke.

| Kilde | Forside (51) | Produkt (105) | Kurv (120) |
| --- | --- | --- | --- |
| inline `<script>`-blokke | 23 | 40 | 35 |
| `wp-includes` (kernel) | 8 | 27 | 40 |
| `woocommerce` | 8 | 23 | 28 |
| AI Engine (`chatbot.js`, `highlight.min.js`) | 2 | 2 | 2 |
| TikTok for Business (+ 3 inline pixel-blokke) | 4 | 4 | 4 |
| Kliken ads-pixel (`*.azurefd.net` + inline-check) | 3 | 3 | 3 |
| Google Listings & Ads | 4 | 4 | 4 |
| Site Kit Sign-in-with-Google (`gsi/client`) | 2 | 2 | 2 |
| gtag (`GT-PL9L67B4` + `AW-18518250875`) | 2 | 2 | 2 |
| tema (`header.js`, `navigation-customization.js`) | 2 | 2 | 2 |
| Complianz, navigation-blok, Stripe, Printful | 1–2 | 4 | 5 |

Tre ting falder ud af tabellen:

1. **Det er ikke temaet, der vejer.** Temaet er 2 scripts. Vægten er kernens blok-infrastruktur
   (react, react-dom, element, escape-html, hooks, interactivity) og Woo-blokkenes egne filer.
2. **Produkt og kurv er blok-skabeloner.** Produktsiden har 40 forskellige `wc-block-*`-klasser og
   bruger `wp-block-woocommerce-product`; kurven er `wp-block-woocommerce-cart`. Derfor 27–40
   kernel-scripts: det er prisen for blok-rendering, ikke en fejlkonfiguration.
3. **Sporingsstakkene er tilsammen 13 scripts på hver eneste side** (TikTok 4, Kliken 3, GLA 4,
   Google-login 2) — uanset om siden har noget med annoncer at gøre.

**Ikke en dublet:** de to `gtag/js`-kald er `GT-PL9L67B4` (Google Tag → GA4 `G-0HC9BLQ88R`) og
`AW-18518250875` (Ads-konvertering). De skal begge bruges, hvis Ads kører — de er ikke det samme
tag to gange, og S0.9's dublet var en anden (Rank Maths `install_code`).

## 2. Gulvet — hvad der ikke kan fjernes

Headeren indeholder **blok-mini-kurven** (`wc-block-mini-cart__button`, 1 forekomst i `<header>`,
drawer-markup i body) og **blok-kontoen** (`wc-block-customer-account`, 4 forekomster i header).
Det er dem, der trækker react, react-dom, element, escape-html, hooks, `mini-cart.js`,
`customer-account.js`, `js-cookie` og interactivity-imports ind på **alle** sider — 155
`data-wp-*`-direktiver på forsiden alene.

- **jquery + jquery-migrate** — 2 scripts; tema og Complianz.
- **navigation-blokken + temaets to filer** — 3 scripts; menu og header.
- **Complianz, gtag ×2 og Rank Math-schema** — 3 scripts + 5 inline; samtykke (lovkrav), GA4 + Ads, SEO.
- **blok-headerens mini-kurv og konto** — 8 scripts + 2 inline; kurv-udtræk og login i header.

Det giver et **gulv på ca. 22–24 scripts** på en tekstside, hvis blok-headeren bliver. Skal
`< 20` nås, skal headerens mini-kurv og konto være **klassiske links** — så forsvinder 8 scripts
(react, react-dom, element, escape-html, hooks, mini-cart, customer-account, js-cookie) og 2
inline-blokke, og gulvet er ~13.

## 3. Tre indgrebstrin, målt effekt og pris

Anslået effekt er regnet ud fra scriptlisten ovenfor og skal måles efter hvert trin.

| Trin | Indgreb | Effekt (anslået) | Pris | Risiko |
| --- | --- | --- | --- | --- |
| **0** | Slå emoji-scriptet fra (`print_emoji_detection_script`), og læg de små config-blokke sammen | −2 scripts, −3,4 KB | 0,05 d | ingen |
| **1** | Fjern annonce- og sporingsstakkene: TikTok for Business, Kliken ads-pixel, Google Listings & Ads, Site Kit Sign-in-with-Google | −13 scripts, −5,7 KB HTML | 0,1 d | mister TikTok- og Kliken-sporing, Merchant-ads-events og Google-login på konto-siden |
| **2** | Gør headerens mini-kurv og konto til klassiske links (behold kurv-linket, drop drawer og blok-konto) | −8 scripts, −2 inline, −~4 KB HTML; ~150 KB JS mindre at hente | 0,25 d | intet kurv-udtræk i header; kræver visuel kontrol af header på alle skabeloner |
| **3** | Klassisk produktskabelon i stedet for blok-skabelonen | produkt −35 til −45 scripts, markup 135 → ~40 KB | 0,75 d | temaarbejde, og designsiden skal godkendes |

Efter trin 0–2 lander skabelonerne her (anslået):

| Skabelon | Nu | Efter 0–2 |
| --- | --- | --- |
| forside | 51 scripts · 117,7 KB | ~26 scripts · ~101 KB |
| side (`/faq/`) | 51 · 111,2 KB | ~26 · ~95 KB |
| indlæg | 51 · 141,5 KB | ~26 · ~125 KB |
| søgning | 48 · 135,3 KB | ~23 · ~119 KB |
| arkiv shop | 54 · 286,1 KB | ~29 · ~276 KB |
| kategori | 54 · 162,5 KB | ~29 · ~152 KB |
| produkt | 105 · 337,0 KB | ~88 · ~327 KB |
| kurv | 120 · 346,2 KB | ~103 · ~336 KB |

Trin 0–2 er ikke nok til `< 20` på de fire tekstskabeloner (26 mod 20). De sidste 6 er Woo's
kurv-scripts på sider uden produkt-UI (`add-to-cart`, `blockUI`, `js-cookie`, `woocommerce.min`,
`sourcebuster`, `order-attribution`) plus 2 inline-blokke, der kan lægges sammen — det hører med i
trin 2 som dequeue pr. skabelon.

## 4. Forslag: loft pr. skabelonklasse

Et enkelt loft for alle otte skabeloner er forkert, fordi markup'en er forskellig: et produktgrid
på 20 varer og en blok-kurv kan ikke veje det samme som en FAQ-side. Derfor tre klasser, hver med
et **loft** (må ikke overskrides — ny funktion skal modsvares af en fjernelse) og et **mål**.

| Klasse | Skabeloner | Loft (nu) | Mål | Forudsætning |
| --- | --- | --- | --- | --- |
| **1 — tekst** | forside, side, indlæg, søgning | 145 KB · 52 scripts | **130 KB · 20 scripts** | trin 0–2 |
| **2 — lister** | `/shop/`, produktkategori | 290 KB · 55 scripts | **200 KB · 30 scripts** | trin 0–2; HTML er produktmarkup, så vægten flyttes med varer pr. side |
| **3 — transaktion** | produkt, kurv, checkout | 350 KB · 120 scripts | **340 KB · 90 (produkt) / 105 (kurv)** | trin 0–2; 20-kravet gælder **ikke** her |

Det sidste er den egentlige beslutning: **`< 20`-kravet trækkes tilbage for klasse 3**, fordi en
blok-baseret kurv og et blok-produkt kræver React — 27–40 kernel-scripts alene. Vil ejeren have
klasse 3 under 100, er vejen trin 3 (klassisk produktskabelon) og senere en klassisk kurv, ikke
flere dequeue-forsøg. 4 KB-kravet gælder uændret alle klasser.

## 5. Hvad det betyder for S2.7

- **Trin 0 og 1 kan køres nu** (0,15 d) og flytter alle skabeloner 13–15 scripts ned.
- **Trin 2 er den eneste vej til `< 20` på klasse 1** (0,25 d) — og den eneste ændring der også
  fjerner ~150 KB JavaScript fra hver sidevisning.
- **Trin 3 er valgfrit** og hører til klasse 3's mål, ikke til klasse 1.
- S2.7 kan lukkes som **opfyldt for klasse 1 og 2** og **bevidst afgrænset for klasse 3**, når
  trin 0–2 er målt. Uden trin 2 er klassens gulv ~26 og kravet kan ikke nås.

## 6. Tre spørgsmål til ejeren

1. **Må TikTok-, Kliken- og Google-ads-sporingen fjernes?** (trin 1, −13 scripts). Uden dem er
   klasse 1's mål 20 ikke muligt; sporingen kan genindsættes via GTM senere uden at ligge i HTML.
2. **Må headerens mini-kurv og konto blive klassiske links?** (trin 2). Det er den ene ændring der
   gør `< 20` muligt, og den fjerner mest JavaScript. Prisen er kurv-udtrækket i header.
3. **Skal produkt og kurv have deres eget loft** (klasse 3), eller skal der bygges klassiske
   skabeloner (trin 3, 0,75 d) for at presse dem ned?

Svarer ejeren nej til 1 eller 2, er den ærlige konsekvens at klasse 1's loft bliver ~35 scripts, og
at `< 20` trækkes tilbage for hele sitet — ikke at tallet bliver stående som uindfriet krav.

## 7. Fælder

- **`GT-` og `AW-` er ikke dubletter.** To `gtag/js`-kald er to forskellige formål; at fjerne det
  ene slår enten GA4 eller Ads-konverteringerne ud.
- **Blok-headeren er usynlig i script-listen.** Man ser `woocommerce` 8 gange, men årsagen er
  `wc-block-mini-cart__button` i `<header>`. Mål markup'en, ikke kun script-kilderne, før noget
  deaktiveres.
- **TikTok lægger en admin-fil på frontend.** `tiktok-for-business/admin/js/ajaxSnippet.js` hentes
  på alle sider — det er plugin'ets egen fejl, ikke temaet.
- **Mål med `?sga=1`.** Sidecachen svarer ellers den gamle HTML (se payload-dokumentet §Fælder).

## 8. Filer

- Målingen: `reports/payload-2026-11-07-efter.json` og dette dokuments tabel 1 (målt 7/11).
- Værktøjet: [`../../tools/payload_audit.py`](../../tools/payload_audit.py)
- Baggrunden for indgrebet: [`payload-budget-2026-11-07.md`](payload-budget-2026-11-07.md)
- Roadmap: [`../roadmap.md`](../roadmap.md) S2.7 og §9 P2
