# Roadmap-status målt på ny — intet uafklaret, og de tretten ejerpunkter navngivet

8/11 2026, aften. Begge roadmaps stod allerede som »lukket eller ejer-blokeret«. Dette pas stillede det
spørgsmål der ikke var stillet: **holder påstandene stadig?** Alt er derfor målt igen — på det levende site
gennem Novamira, med plugin'ets egne diagnostikværktøjer, og med repoets test- og kontrolværktøjer. Fem
påstande holdt ikke og er rettet i roadmap-filerne; resten er bekræftet med frisk bevis. Til sidst står
**tretten ejerpunkter** med præcis hvad der mangler, og hvad ejeren skal gøre — ikke en kategori.

## 1. Hvad der blev målt igen, og hvordan

| Måling | Metode | Resultat 8/11 (aften) |
|---|---|---|
| Valuta | `get_woocommerce_currency()` | **DKK** |
| Produkter | `wp_posts` i alle statusser | **20 publicerede · 4 kladder · 92 i papirkurven** |
| Billedlag | hoved- + galleribilleder pr. publiceret produkt | **22 billeder på 20 produkter · 0 med 3+ · 0 uden alt-tekst** |
| Forsendelse | `WC_Shipping_Zones::get_zones()` + zone 0 | **kun »Denmark« med `free_shipping`**; zone 0 (resten af verdenen) har **0 metoder** |
| Betaling | `WC()->payment_gateways()->payment_gateways()` | **5 slået til** (`stripe`, `stripe_amazon_pay`, `stripe_klarna`, `stripe_link`, `bacs`), **2 tilgængelige i kassen** (`stripe`, `bacs`) |
| Complianz | `cmplz_options` | **`regions: eu`, `eu_consent_regions: yes`** (EU opt-in); `telephone_company: ""`, intet CVR-felt |
| Kollektioner | `product_cat`-tællinger | dark-academia **5** · gothic-victorian **3** · natural-history **2** · vintage-maritime **1** · steampunk-capsule **3** · witchy-tarot **0** |
| Plugin | `SGAP_VERSION` | live **7.65.0**; repo **7.65.1** (dokumentations-patch af plugin-roadmappen, se §2) |
| Aktive plugins | `active_plugins` | **22 aktive**, 2 inaktive (`website-llms-txt` 8.6.3, `insert-headers-and-footers` 2.4.1) |
| Kø | `sgap_queue` hvor `status='pending'` | **0** |
| AI-kladder | `sgap_ai_drafts` hvor `status='pending'` | **45** |
| Nøgler, live | `SGAP_Keycheck::run()` kl. 21:24 | publer `n/a` (RSS-tilstand) · **ai `ok: false` — »You've run out of funds«** · **printful »No Printful token saved.«** |
| Kanaler, live | `SGAP_Channel_Health::summary()` | `ready 0 · needs_scope 1 · missing 5 · refused 0 · broken 0` af 6 |
| Kanalanalytics | `analytics_summary(analytics())` | `measured 0 · empty 5 · no_metric 1` (0 rækker i `sgap_events`) |
| `/go/`-links | 33 publicerede indlæg, alle `/go/<id>/` | **110 links, alle 110 peger på et publiceret produkt**; stikprøve 483/965/1255 → **302** til produktsiden |
| Strukturdata | `tools/schema_check.py` | **20/20 produkter fuldt udfyldt** (brand, returret, leveringstid), valuta **DKK** |
| ItemList | `/shop/` og to kategorier | **16** elementer på `/shop/` (sidens 16 pr. side), **5** på `/product-category/airship/`, **4** på `/product-category/atelier/` |
| Plugin-tests | `plugins/tests/smoke.php` | **1136 PASS · 0 FAIL** (exit 0) |
| PHP-syntaks | `.tools/php -l` på alle 65 filer | **ingen fejl** |

## 2. Fem påstande holdt ikke — og er rettet

| Hvor | Stod der | Målt nu | Rettet til |
|---|---|---|---|
| `roadmap.md` §4c, indledningen | »butikken kører **USD**«, »Complianz står på **US opt-out**«, »26 billeder på 24 produkter« | DKK · EU opt-in · 22 billeder på 20 publicerede | de målte tal, med dato |
| `roadmap.md` §4c, S4.4 | »**20** elementer på `/shop/`« | **16** (shop viser 16 pr. side) | 16, med sidenotation |
| `roadmap.md` §4c, S4.5 | »aktive gateways: stripe, amazon_pay, klarna, link, bacs« | 5 **slået til**, men kun **2 tilgængelige** i kassen | begge tal, og de tre utilgængelige navngivet som ejerpunkt |
| plugin-`ROADMAP.md` §5 | »målt 8/11 på **7.64.9**« | repo **og** live er 7.65.0 | 7.65.0 |
| plugin-`ROADMAP.md` P1.5 | ejerens skridt (1): »*Plugins → Update* til 7.64.9« | sitet kører 7.65.0; `update` svarer »Already up to date (7.65.0)« | skridtet er **fjernet** — det var allerede udført, og listen er nu to skridt |

Det er præcis den slags der gør en roadmap »uafklaret«: ikke en manglende opgave, men en status der ikke
længere passer. En roadmap der beder ejeren om noget han allerede har gjort, koster ham et klik og en undren.

## 3. De tretten ejerpunkter — hvad der mangler, og hvad ejeren skal gøre

Grupperet efter **hvad ejeren skal levere**, fordi flere roadmap-rækker deler samme mangel.

| # | Mangel (målt) | Roadmap-rækker | Én konkret handling |
|---|---|---|---|
| A | `telephone_company: ""` og intet CVR-felt i Complianz | **S4.2 · S4.9 GPSR · `seo.md` #5** | Complianz → Wizard → Organization: indsæt **telefon** og **CVR** (ret firmanavnet »Henning 6« samtidig) |
| B | Zone 0 har 0 fragtmetoder; kun DK har fri fragt | **S4.6** | Beslut tre tal: **hvilke lande**, **pris pr. zone**, **fri fragt-grænse** — agenten konfigurerer og måler bagefter |
| C | `stripe_amazon_pay`, `stripe_klarna`, `stripe_link` er slået til men **utilgængelige** i kassen | **S4.5** | Slå dem til i **Stripe Dashboard → Payment methods**, eller slå dem fra i WooCommerce så kassen ikke lover mere end den holder |
| D | MobilePay og PayPal findes ikke som gateway | **S4.5** | Opret aftalen og læg nøglerne ind som secret |
| E | 22 billeder på 20 produkter, **0 har 3+**; intet Printful-token (`keycheck`: »No Printful token saved.«) | **S4.7 · Rebrand batch 4** | Sæt **Printful-token** i Settings → Keys, **eller** upload 5–8 fotos pr. produkt efter [batch-4-arbejdsordre](batch-4-arbejdsordre-2026-11-08.md) |
| F | Fire kladder med pris og SKU: 942, 765, 766, 767 | **S1.2** | Svar **ja/nej** pr. produkt — agenten publicerer eller lader dem ligge |
| G | To inaktive plugins indlæser ingen kode | **S3.4b** | **Plugins → Inaktive → Slet** på begge, eller skriv »behold« |
| H | 29 af om-mærkningens 45 designs står i papirkurven; witchy-tarot har 0 produkter | **Rebrand — trash'ede designs · witchy-tarot** | Beslut om de **29** skal genoplives, og om **567/395/411** skal tilbage i witchy-tarot |
| I | Pinterest mangler `boards:read`; fem kanaler mangler token (`needs_scope 1`, `missing 5`) | **Plugin P1.5** | Genforbind Pinterest med board-adgang; indsæt tokens for Instagram, Bluesky, Mastodon, Telegram, Facebook under **Settings → Keys** |
| J | AIMLAPI afviser nøglen: »You've run out of funds« — **45 kladder venter** | **indholdsproduktionen** | Fyld op på AIMLAPI-kontoen (aimlapi.com → Billing) |
| K | `alert_email` er tom, så fejl registreres uden at nogen får mail | **Plugin P0.1** | Sæt en **e-mailadresse** i Settings → Alerts |
| L | GSC-kaldet svarer **401** — intet credential findes | **S0.6** | Search Console → Crawl statik → By response → screenshot af de 455-rækker |
| M | Command Center-retningen er ikke bekræftet | **Plugin §8, punkt 5** | Svar **ja/nej** til énvejs (CC læser `/snapshot`, lægger kladder via `POST /sgap/v1/drafts`) |

**Ikke-blokerende, allerede dokumenteret:** valget mellem A/B/C for plugin'ets opdateringskanal efter at
repoet blev privat. Det står i [repo-privat-2026-11-08.md](repo-privat-2026-11-08.md) med de tre veje, og det
holder ikke noget andet punkt tilbage — derfor er det ikke talt med ovenfor.

## 4. Hvad der beviseligt virker nu (kørt, ikke antaget)

- **Pluginnet:** 65 PHP-filer uden syntaksfejl, **1136 smoke-checks PASS / 0 FAIL**. `keycheck` og
  `channel-health` kører mod de rigtige API'er og svarer med den målte tilstand i stedet for at gætte.
- **Butikken:** DKK, 20 publicerede produkter med priser fra dokumentets tabel (11 rækker) og urørte priser
  på de 13 uden række; kurv og kasse svarer 200 med vare i kurven; W3TC kører minify + lazy load + dbcache.
- **Indhold og links:** 33/33 publicerede indlæg har `/go/`-links, **110 links og alle 110 peger på et
  publiceret produkt**, og stikprøverne redirecter 302 til den rigtige produktside.
- **Strukturdata:** 20/20 produkter har brand, returret og leveringstid; `/shop/` og kategorisiderne har
  ItemList (16/5/4 elementer), og produktsiderne har præcis ét `Product`.
- **Kollektioner:** 5 af 6 har produkter; witchy-tarot er tom, hvilket er ejerbeslutning H.

## 5. Hvad der ikke blev rørt

Ingen pris, intet produkt, ingen gateway, ingen fragtzone, ingen Complianz-indstilling og ingen
kanal-token blev ændret i dette pas. Det er en måling og en dokumentrettelse — de tretten punkter ovenfor
kræver ejerens tal, aftaler eller valg, og ingen af dem er gættet.
