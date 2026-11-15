# HowTo-schema og fast produkt-callout på DIY-guiderne (15/11 2026)

**Kort:** Syv DIY-guider har nu **præcis ét** HowTo-JSON-LD, der er udledt af guidens egne synlige
trin (5–18 trin pr. guide), og **fem** af dem har en fast produkt-callout med levende DKK-priser fra
WooCommerce. Begge dele er målt i den leverede HTML på live-siderne, valideret i schema.orgs validator
(**0 fejl**) og set i browser ved 1440 og 390 px. **Tre guider mangler callout, fordi de produkter de
faktisk bruger er kladder** — det kræver ejerens beslutning, ikke en rettelse.

Alt er bygget som én mu-plugin, `mu-plugins/sgap-guide-enhance.php` (10 969 bytes, version 1.0.0), lagt på
sitet over MCP og committet i repoet.

---

## 1. Hvad pluginnet gør — og hvad det ikke gør

| Mekanisme | Sådan | Hvorfor |
| --- | --- | --- |
| **HowTo fra indholdet** | Trinene læses ud af guidens eget indhold i tre strategier: (A) `<h2>`/`<h3>` af formen »Step 3: …«, (B) den `<ol>` der står efter en »Step-by-step«-overskrift, (C) løse `<li>`-punkter under én metodeoverskrift (goggle-guidens can-lid-metode) | Antallet af trin kan ikke drive fra siden: schema tæller de samme punkter som læseren ser |
| **Eget script, ikke et filter** | HowTo printes som `<script class="sgap-howto">` i `wp_head` | Rank Math udsender ikke HowTo her; et eget script kan ikke kollidere med dets `@graph`, og der er derfor præcis ét HowTo pr. side |
| **Ingen dubletter** | Guidens egen inline HowTo fjernes i `the_content` (kun når vores egne trin findes), og alle andre schema-blokke røres ikke | 8 og 10 havde allerede inline HowTo; 8's havde 6 trin mod 5 synlige og blev erstattet |
| **Callout som filter** | `the_content` indsætter `<aside class="sgap-guide-callout">` **lige før FAQ** i alle guides, med identisk markup og CSS | Samme mønster og placering på tværs af guiderne, uden at røre deres indhold |
| **Priser** | `wc_get_product()` → `get_price_html()` på publicerede produkter | Ingen hardkodede tal; prisen følger butikken (DKK, »kr.395.00« — samme format som produktsiderne) |
| **Idempotent** | Indsætter ikke callout hvis `sgap-guide-callout` allerede står i indholdet | To visninger kan ikke opstå ved et ekstra kald |

## 2. Målt pr. guide (15/11 2026)

Hentet fra den leverede HTML efter cache-flush; »synlige trin« er talt i samme HTML.

| Guide | Post | Schema-trin | Synlige trin | HowTo | Callout | Produkter |
| --- | --- | --- | --- | --- | --- | --- |
| How to Make a Steampunk Costume | 8 | 5 | 5 (`Step 1–5`) | 1 | **ja** | 941 |
| DIY Steampunk Goggles | 10 | 6 | 6 (can-lid-metodens punkter) | 1 | nej — blokeret | — |
| Costume for under $100 | 757 | 6 | 6 (`Step 1–6`) | 1 | **ja** | 941 |
| Steampunk Wall Decor DIY | 1357 | 18 | 18 (9 + 9 i to lister) | 1 | **ja** | 965, 966, 967 |
| Steampunk Utility Belt | 1359 | 9 | 9 | 1 | **ja** | 940, 941 |
| Mad Scientist Costume | 1361 | 7 | 7 | 1 | **ja** | 940, 941 |
| Steampunk Corset Guide | 1394 | 10 | 10 | 1 | nej — blokeret | — |
| DIY vs. Buy Goggles | 614 | 0 | 0 | 0 | nej | — (sammenligning, ikke en tutorial) |

Alle syv HowTo-navne findes ordret i den synlige tekst (målt: 0 navne uden match), og hver guide har
**præcis én** HowTo-node — heller ikke 8 og 10 har to efter ændringen.

**Validator:** schema.orgs validator (`https://validator.schema.org/validate`, POST med sidens HTML)
svarer **0 fejl og 0 property-fejl** på alle otte sider. De øvrige typer står uændret og fejlfrit:
`BlogPosting`, `FAQPage`, `BreadcrumbList`, `WebPage`, `Person`, `Organization`, `WebSite`, `ImageObject`.

**Callout-links:** alle fem produktlinks svarer **200** (brass-luna-moth-print, clockwork-rose-print,
clockwork-seer-pendant, raven-chronometer-print, the-aeronaut-pocket-watch). Ingen tom callout: pluginnet
springer guider over uden publicerede produkter.

**Layout set i browser:** 1440 px → tre kolonner à 199 px, callout 672 px bred, 0 vandret overflow;
390 px → to kolonner à 151 px, 0 overflow, ingen navne klippet. Ét produkt (guide 8) er begrænset til
230 px, så billedet ikke fylder hele boksen. 0 JS-fejl på de målte sider. Forside, `/shop/`,
produktside, `/cart/` og `/checkout/` svarer alle **200**.

## 3. Blokeringen: tre guider kan ikke få en ærlig callout

Guiderne 10 (goggles), 614 (goggles) og 1394 (corset) bruger produkter der **ligger som kladder**:

| Produkt | Status | Pris |
| --- | --- | --- |
| 477 Copper Pilot Goggles — Vintage Windproof | draft | 165 kr |
| 483 Time Traveler Top Hat with Goggles | draft | 335 kr |
| 489 Steampunk Overbust Corset — Leather Lace-Up | draft | 400 kr |
| 491 Gothic Overbust Corset — Faux Leather, Silver | draft | 435 kr |

Målt: **0 publicerede produkter** i goggles- og corset-kategorierne. En callout på de tre guider ville
enten pege på noget andet end det guiden handler om, eller på et 404 — begge dele er værre end ingen
callout. At publicere de fire produkter er en ejerbeslutning (sortiment og den posters-only-retning
§6 i oprydningslisten peger på), ikke en teknisk rettelse.

**Samme årsag har et ældre fund:** guidens egne `/go/`-links peger også på kladderne, og de ender i
**404** (`/go/477/` → `?post_type=product&p=477` → 404, målt på alle fire):

| Guide | Døde `/go/`-id'er | Levende |
| --- | --- | --- |
| 8 | 477, 483 | — |
| 10 | 477, 483 | — |
| 614 | 483 | — |
| 757 | 477, 483, 489 | — |
| 1359 | 477 | 940, 941 |
| 1361 | 477, 483 | 940 |
| 1394 | 489, 491 | — |

Det er 13 døde link-forekomster i syv guides. De er **ikke** rettet her: hver rettelse ville enten
ændre guidens indhold eller kræve at produkterne publiceres. Calloutens links er derimod målt levende.

## 4. Fælder der kostede en runde

1. **W3TC serverede den cachede HTML.** Efter upload så alt uændret ud — filen var skrevet, men siden
   blev ikke eksekveret. `w3tc_flush_all()` + `?nocache=1` viste resultatet. Enhver måling på dette site
   skal cache-bustes eller flushes først.
2. **Em-dash i en byte-baseret regex.** `Step 1 — …` blev fanget uden `/u`, så `(.+?)` startede midt i
   UTF-8-sekvensen; `preg_replace('/\s+/u')` returnerede `null`, og trinnavnene blev tomme. Alle mønstre
   har nu `/u`, og `sgap_guide_plain()` falder tilbage via `mb_check_encoding`/`mb_convert_encoding`.
3. **Guidens egen HowTo.** 8 og 10 havde inline `<script>` med HowTo i indholdet. Uden fjernelse ville
   siden have to — og 8's havde 6 generiske trin mod 5 synlige. Fjernelsen sker kun, når vores egne trin
   findes (≥2), så en guide uden læsbare trin beholder sin egen schema.
4. **Én vare blev 672 px bred.** `repeat(auto-fit, minmax(150px,1fr))` giver én kolonne i fuld bredde ved
   ét produkt. Kortet er nu max 230 px og centreret.

## 5. Verifikation (kørt 15/11)

| Check | Resultat |
| --- | --- |
| `php -l` på mu-pluginnet (PHP 8.3.14, statisk binær i `.tools/`) | ingen syntaksfejl |
| Trinudtræk mod de rigtige post-typer (offline harness) | 8: 5 · 10: 6 · 757: 6 · 1357: 18 · 1359: 9 · 1361: 7 · 1394: 10 · 614: 0 |
| Live HTML, otte sider | 1 HowTo pr. guide, 0 trinnavne uden match, 0 dubletter |
| schema.org-validator | 0 fejl, 0 property-fejl, øvrige typer urørte |
| Callout-links | 5/5 svarer 200 |
| Browser 1440 px og 390 px | 0 overflow, 0 JS-fejl, grid 3 → 2 kolonner |
| Forside, shop, produkt, kurv, kasse | 200 |

## 6. Hvad der stadig er åbent

1. **Ejerbeslutning:** skal 477, 483, 489 og 491 publiceres? Det låser callout på 10, 614 og 1394 og
   gør 13 døde `/go/`-links levende i samme handling.
2. **Guide 614** er en sammenligning (DIY vs. køb), ikke en tutorial. Den har bevidst hverken HowTo
   eller callout; skal den have et »HowTo«, kræver det at guiden omskrives til en trinsekvens.
3. **Prisformatet** er »kr.395.00« — WooCommerce' egen opsætning på sitet, ikke calloutens. Skal der
   stå »395,00 kr.«, er det butikkens decimal- og tusindtalsindstilling der skal ændres, ikke pluginnet.

## 7. Filer

- `mu-plugins/sgap-guide-enhance.php` — pluginnet (repoet) og samme fil i `wp-content/mu-plugins/` på sitet
- `artifacts/guide-1357-desktop.png`, `artifacts/guide-1357-mobil.png`, `artifacts/guide-8-callout.png` — de tre browserbilleder
- `reports/guide-howto-og-callout-2026-11-15.pdf` — denne rapport som PDF
