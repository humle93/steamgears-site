# HowTo-schema og fast produkt-callout på DIY-guiderne (15/11 2026)

**Kort:** Syv DIY-guider har nu **præcis ét** HowTo-JSON-LD, der er udledt af guidens egne synlige
trin (5–18 trin pr. guide), og **alle otte** har en fast produkt-callout med levende DKK-priser fra
WooCommerce. Begge dele er målt i den leverede HTML på live-siderne, valideret i schema.orgs validator
(**0 fejl**) og set i browser ved 1440 og 390 px. **Butikken kører kun plakater** (ejerbeslutning 15/11), så callouten viser de tre publicerede prints, og de 94 links i indholdet der pegede på kladde-produkter er afkoblet.

Alt er bygget som én mu-plugin, `mu-plugins/sgap-guide-enhance.php` (11 194 bytes, version 1.0.0), lagt på
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
| How to Make a Steampunk Costume | 8 | 5 | 5 (`Step 1–5`) | 1 | **ja** | 965, 966, 967 |
| DIY Steampunk Goggles | 10 | 6 | 6 (can-lid-metodens punkter) | 1 | **ja** | 965, 966, 967 |
| Costume for under $100 | 757 | 6 | 6 (`Step 1–6`) | 1 | **ja** | 965, 966, 967 |
| Steampunk Wall Decor DIY | 1357 | 18 | 18 (9 + 9 i to lister) | 1 | **ja** | 965, 966, 967 |
| Steampunk Utility Belt | 1359 | 9 | 9 | 1 | **ja** | 965, 966, 967 |
| Mad Scientist Costume | 1361 | 7 | 7 | 1 | **ja** | 965, 966, 967 |
| Steampunk Corset Guide | 1394 | 10 | 10 | 1 | **ja** | 965, 966, 967 |
| DIY vs. Buy Goggles | 614 | 0 | 0 | 0 | **ja** | 965, 966, 967 |

Callouten er den samme på alle otte: titlen »Prints from this world«, tre plakater (Clockwork Rose,
Brass Luna Moth, Raven Chronometer) og levende DKK-priser. 614 er en sammenligning og har derfor ingen
HowTo — den har bevidst ikke fået et kunstigt trinforløb.

Alle syv HowTo-navne findes ordret i den synlige tekst (målt: 0 navne uden match), og hver guide har
**præcis én** HowTo-node — heller ikke 8 og 10 har to efter ændringen.

**Validator:** schema.orgs validator (`https://validator.schema.org/validate`, POST med sidens HTML)
svarer **0 fejl og 0 property-fejl** på alle otte sider. De øvrige typer står uændret og fejlfrit:
`BlogPosting`, `FAQPage`, `BreadcrumbList`, `WebPage`, `Person`, `Organization`, `WebSite`, `ImageObject`.

**Callout-links:** de tre plakater (clockwork-rose-print, brass-luna-moth-print, raven-chronometer-print)
svarer **200** — målt på alle otte guides. Ingen tom callout og ingen hardkodede priser. Prisen står på
linje i de tre kort, også når det længste produktnavn løber over tre linjer (`margin-top:auto` på prisen).

**Layout set i browser:** 1440 px → tre kolonner à 199 px, callout 672 px bred, 0 vandret overflow;
390 px → to kolonner à 151 px, 0 overflow, ingen navne klippet. Ét produkt (guide 8) er begrænset til
230 px, så billedet ikke fylder hele boksen. 0 JS-fejl på de målte sider. Forside, `/shop/`,
produktside, `/cart/` og `/checkout/` svarer alle **200**.

## 3. De fire kladder blev ikke publiceret

Guiderne 10 (goggles), 614 (goggles) og 1394 (corset) pegede på produkter der **ligger som kladder**:

| Produkt | Status | Pris |
| --- | --- | --- |
| 477 Copper Pilot Goggles — Vintage Windproof | draft | 165 kr |
| 483 Time Traveler Top Hat with Goggles | draft | 335 kr |
| 489 Steampunk Overbust Corset — Leather Lace-Up | draft | 400 kr |
| 491 Gothic Overbust Corset — Faux Leather, Silver | draft | 435 kr |

Målt: **0 publicerede produkter** i goggles- og corset-kategorierne. Ejeren svarede, at butikken **kun
kører plakater**, så de fire bliver liggende som kladder — callouten blev i stedet lagt om til de tre
publicerede prints (se §6b), og linkene til kladserne er afkoblet i indholdet.

**Det ældre fund, som det også løste:** guidens egne `/go/`-links pegede på de samme kladder og endte i
**404** (`/go/477/` → `?post_type=product&p=477` → 404, målt på alle fire). Det var 14 forekomster i de
syv guides og **94 i alt i 17 publicerede indlæg og sider**. Alle er nu afkoblet — anchor-teksten står
tilbage som ren tekst — og målingen efter viser **0 døde `/go/`-mål** på sitet.

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
| Callout-links | 3/3 plakater svarer 200 på alle otte guides |
| Browser 1440 px og 390 px | 0 overflow, 0 JS-fejl, grid 3 → 2 kolonner |
| `/go/`-mål i publicerede indlæg og sider | 0 døde (94 links afkoblet) |
| Forside, shop, produkt, kurv, kasse | 200 |

## 6. Hvad der stadig er åbent

1. **Elleve publicerede ikke-plakater står stadig i butikken** (T-shirts 557, 954, 1255, 1282; hoodies
   399, 401, 413; phone case 970; lommeure 940, 941). Skal butikken være tre plakater, sættes de på kladde,
   og cirka 30 flere `/go/`-links skal afkobles. Det er en sortsimentsbeslutning, ikke en rettelse.
2. **Guide 614** er en sammenligning (DIY vs. køb), ikke en tutorial. Den har callout, men bevidst ingen
   HowTo; skal den have et, kræver det at guiden omskrives til en trinsekvens.
3. **Prisformatet** er »kr.395.00« — WooCommerce' egen opsætning på sitet, ikke calloutens. Skal der
   stå »395,00 kr.«, er det butikkens decimal- og tusindtalsindstilling der skal ændres, ikke pluginnet.

## 6b. Posters-only (ejerbeslutning 15/11 2026)

Efter målingen svarede ejeren: **»vi kører kun posters«**. Det ændrede to ting — ikke schemaet.

| Handling | Omfang | Resultat |
| --- | --- | --- |
| Callouten peger nu kun på de tre publicerede plakater (965, 966, 967) | alle otte guides, også 10, 614 og 1394 | 8/8 guides har én callout med samme titel (»Prints from this world«), samme placering og levende DKK-priser |
| Introduktionen lover ikke længere tøj eller gear | én sætning i pluginnet | »The shop runs on prints. These three share the palette this guide is built around …« |
| Links til kladde-produkter er afkoblet i indholdet | **94 links i 17 indlæg/sider** (8, 1214, 14, 17, 30, 699, 751, 791, 798, 803, 805, 806, 875, 913, 891, 897, 898) | Anchor-teksten står tilbage som ren tekst; ingen 404 fra et produktlink |
| De fire kladde-produkter (477, 483, 489, 491) forbliver kladder | 0 publicerede goggles/corsets | Ingen vare blev publiceret for at redde et link |

**Målt efter:** **0 døde `/go/`-mål** i alle publicerede indlæg og sider (var 94 forekomster fordelt på 17
poster, deriblandt de 16 i de syv guides). Senere samme dag blev de elleve ikke-plakater også sat på kladde,
og de sidste 61 links afkoblet — se [posters-only i hele butikken](posters-only-butik-2026-11-15.md).

**Afsluttet samme dag:** ejeren gav ordre til at butikken kun skal være plakater, så de elleve
ikke-plakater (T-shirts 557, 954, 1255, 1282; hoodies 399, 401, 403, 413; phone case 970; lommeure 940, 941)
blev sat på kladde, og de resterende links blev afkoblet. Butikken har nu tre publicerede varer og 0 døde
`/go/`-mål — detaljerne står i [posters-only i hele butikken](posters-only-butik-2026-11-15.md).

## 7. Filer

- `mu-plugins/sgap-guide-enhance.php` — pluginnet (repoet) og samme fil i `wp-content/mu-plugins/` på sitet
- `artifacts/guide-1357-desktop.png`, `artifacts/guide-1357-mobil.png`, `artifacts/guide-8-callout.png` — de tre browserbilleder
- `reports/guide-howto-og-callout-2026-11-15.pdf` — denne rapport som PDF
