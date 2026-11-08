# Restpunkterne gennemgået med skriveadgang — hvad der blev udført, og hvad ejeren skal gøre

Listen »kun ydre blokeringer tilbage« blev skrevet **før** der fandtes en skrivevej. Denne gennemgang tager
hvert eneste restpunkt i begge roadmaps og afprøver det mod den vej der nu virker — MCP-abilityen
`novamira/execute-php` med `--yes` — i stedet for at antage at det er blokeret.

Resultatet er todelt: **to poster blev udført på live-sitet** (Complianz EU opt-in, plugin opdateret til
7.65.0), og resten er omskrevet til den ene konkrete handling ejeren udfører, med det målte svar der viser
hvorfor. Intet står tilbage som »kræver adgang«.

## 1. Udført på live-sitet

### S4.1 — Complianz kører nu EU opt-in

| | Før | Efter |
|---|---|---|
| `regions` | `us` | **`eu`** |
| `eu_consent_regions` | `no` | **`yes`** |
| `other_region_behaviour` | `none` | `none` |

Sket med `update_option('cmplz_options', …)`, derefter `cmplz_clear_cache()` og `w3tc_flush_all()`.
**Verificeret udefra** i forsidens HTML: `"region":"eu"` og stylesheetet `cmplz-banner-1-optin-css` — det er
opt-in-banneret, ikke opt-out. Før stod butikken på US opt-out, hvilket var i strid med S4.1's krav.

### Plugin P1.5, første del — sitet kører nu 7.65.0

Sitet kørte **7.64.7**, ikke 7.64.6 som roadmappet hidtil skrev. Opdateringen blev kørt gennem
`Plugin_Upgrader` i `execute-php` fra repoets egen zip:

```json
{"foer": "7.64.7", "efter": "7.65.0", "bytes": 388837, "resultat": true, "aktiv": true,
 "beskeder": ["Unpacking the package…", "Installing the plugin…", "Removing the current plugin…",
              "Plugin installed successfully."]}
```

Bagefter svarer `steamgears-autopilot/update`: `{"updated": false, "from": "7.65.0", "to": "7.65.0",
"message": "Already up to date (7.65.0)."}` — dvs. det »klik« roadmappet bad om, er nu overflødigt.

**Verificeret udefra efter opdateringen:** `/` 200 (3,0 s), `/shop/` 200 (2,4 s), en produktside 200 (2,1 s),
`/wp-json/sgap/v1/tick` 200 og `/wp-json/wp/v2/types` 200.

### P1.5's live-måling — kørt, og den siger hvad der mangler

`steamgears-autopilot/channel-health` på 7.65.0:

| Kanal | State |
|---|---|
| pinterest | **needs_scope** |
| instagram | missing_token |
| bluesky | missing_token |
| mastodon | missing_token |
| telegram | missing_token |
| facebook | missing_token |

`analytics_summary`: `{"measured": 0, "empty": 5, "no_metric": 1, "total": 6, "rows": 0}`. Målingen kører
altså nu (det var hele P1.5's pointe), og dommen er `blokeret` på tokens — ikke på kode.

## 2. Resten: den konkrete ejerhandling, med det målte svar

| Punkt | Målt nu (rå værdi) | Ejerhandling — ét sted, ét felt |
|---|---|---|
| **S0.6** Googlebot 455/5xx | `GET searchconsole.googleapis.com/webmasters/v3/sites` → **HTTP 401**, »Request is missing required authentication credential« | GSC → Ejendommen → **Indstillinger → Crawl-statik → By response → sidste 7 dage**: send et screenshot af tallene |
| **S1.2** 942/765/766/767 | Alle fire er `draft` med pris og SKU: 942 `Ink and Steam — Clockwork Charm Bracelet` 69 / `SG-ATELIER-JW-03` · 765 `Steampunk Goggle DIY Starter Kit` 29.90 / `SG-DIY-KIT-001` · 766 `Steampunk Leather Utility Belt & Holster` 24.90 / `SG-ACC-BELT-001` · 767 `Digital Blueprint: Victorian Corset & Vest` 6.90 / `SG-DIGI-CORSET-001` | Svar **ja/nej pr. produkt**: kan det leveres, og er prisen rigtig? (Så publicerer jeg dem) |
| **S3.4b** to inaktive plugins | `website-llms-txt/website-llms-txt.php v8.6.3` og `insert-headers-and-footers/ihaf.php v2.4.1` — begge inaktive, ingen indlæst kode | **Plugins → Inaktive → Slet** begge (eller sig »behold«, så lukker jeg posten som bevidst valg) |
| **S4.2 / S4.9** CVR + telefon | Complianz: `organisation_name: "Henning 6"`, `address_company: "Lavgade 44a st th"`, `telephone_company: ""`, intet CVR-felt | **Complianz → Wizard → Organization**: ret firmanavn, indsæt **CVR** i adressefeltets virksomhedsdel og **telefon**; jeg skriver derefter GPSR-blokken med de samme data |
| **S4.3** valuta + 24 priser | `woocommerce_currency: USD`, **24 af 24** produkter har pris | Godkend DKK-tabellen i [`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md) (fx »DKK, brug tabellen«) — så sætter jeg valuta og alle 24 priser i én kørsel og måler dem bagefter |
| **S4.5** betalinger | Aktive gateways: `stripe`, `stripe_amazon_pay`, `stripe_klarna`, `stripe_link`, `bacs` | Opret aftale med **MobilePay** og/eller **PayPal**, og send mig nøglerne som secret — ellers er Stripe (inkl. Klarna) dækningen |
| **S4.6** fragtzoner | Zonen »Denmark« har `free_shipping`; zone 0 (»Locations not covered by your other zones«) har **ingen** metoder | Beslut zonerne: hvilke lande, hvilken pris, og om fri fragt-grænsen skal være 0 eller fx 400 kr. Skriv de tre tal, så konfigurerer jeg dem |
| **S4.7 / batch 4** mockups | Plugin-status: `printful: {"ok": null, "message": "No Printful token saved."}`; kataloget har 22 billeder på 20 produkter | Gem et **Printful-token** i plugin-indstillingerne (Settings → Keys → Printful) **eller** upload 5–8 billeder pr. produkt; arbejdsordren ligger klar |
| **`seo.md` #4** W3TC | `pgcache: true`, `browsercache: true`, `minify: false`, `dbcache: false`, `lazyload: false` | Gennemfør **én rigtig testordre** i checkout, og sig »slå til«. Den tekniske blokering er væk — `execute-php` kan skrive `wp-content/w3tc-config/master.php`, hvilket dokuments teksten hidtil afviste — men dokumentets egen regel siger at det ikke må slås til før testordren |
| **`seo.md` #5** GPSR-adresse | Samme felter som S4.2 | Samme handling som S4.2 |
| **Rebrand — witchy-tarot** | Termen har 0 produkter; 567, 395, 411 er `trash` | **Produkter → Papirkurv → Gendan 567** (og evt. 395/411); så fylder `tools/rebrand_apply.py --apply --via-cli` termen automatisk |
| **P1.5 — tokens** | Pinterest `needs_scope`, fem kanaler `missing_token` | **Pinterest:** genforbind med `boards:read` (eller indsæt developer-token med `boards:read` + `pins:read`) · **Instagram, Bluesky, Mastodon, Telegram, Facebook:** indsæt tokens under **Settings → Keys** i pluginnet |

## 3. Nye fund undervejs (ikke på roadmappet)

**AI-generatoren står stille: AIMLAPI-nøglen er løbet tør for midler.** Plugin-status svarer:

```json
{"ai": {"ok": false, "message": "AIMLAPI refused the key: You've run out of funds. Please top up your balance or update your payment method to continue: https://aimlapi.com/app/billing/"},
 "pending_drafts": 45, "queue": {"published": 35},
 "last_generation": {"at": "2026-11-08 19:44:11", "drafts": 0, "queued": 0,
   "errors": ["Steampunk Mad Scientist Costume: 10 Inventor Ideas to Build: AIMLAPI: You've run out of funds."]}}
```

Det betyder at den daglige indholdsproduktion ikke giver nye kladder, uanset hvor mange artikler der ligger i
køen. **Ejerhandling:** fyld op på <https://aimlapi.com/app/billing/> — eller skift leverandør under
pluginets AI-indstillinger. Jeg målte det, fordi »status« var det første jeg kaldte efter opdateringen.

## 4. Hvad der stadig ikke kan afgøres af en agent

Intet af det ovenstående er en teknikblokering længere. Det der mangler er **beslutninger og aftaler**:
valutakurs og prisniveau, fragtpriser, betalingsaftaler, CVR/telefon, hvilke papirkurvs-produkter der skal
genoplives, og om W3TC-minify må slås til før eller efter en testordre. De kan ikke gættes uden at ramme
kundernes priser, levering eller juridiske oplysninger.
