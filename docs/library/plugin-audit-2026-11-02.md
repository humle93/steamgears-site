# Plugin-audit 2/11 2026

**Kort:** 38 plugins installeret, 36 aktive, 4 drop-ins. **3 er bagud** ift. WordPress.org, 3 kan ikke
tjekkes udefra (to er premium, én er egenudviklet). Den største gevinst er ikke opdateringer, men
**fem overlap** hvor to plugins gør det samme — og ét inaktivt plugin der kan løse en blokeret
roadmap-opgave.

_Målt igen 2/11 2026 om aftenen, efter at Autopilot 7.22.0 og AI Engine 3.9.0 kom på sitet: 38 installeret,
36 aktive, og to færre bagud end om eftermiddagen. Ændringerne står i §1, §2 og §4._

## Metode

| Hvad | Hvordan |
| --- | --- |
| Installeret stand | `steamgears-autopilot/list-plugins` og `wp_list_plugins` over MCP 2/11 2026, sent (navn, version, aktiv, fil) |
| Seneste version | WordPress.org plugin-API pr. slug, samme dag (`custom-css-js` er det rigtige slug for Simple Custom CSS and JS) |
| Runtime | `wp_get_diagnostics`: WP 7.1.4, PHP 8.5.12, MySQL 8.4.11, 36 aktive plugins, 2048M, `DISABLE_WP_CRON` slået til (server-cron) |
| Cache og HTTPS | HTTP-kald udefra: `http://` → 301 til https, `/cart/` og `/checkout/` svarer `private, no-cache` |

**Ikke verificeret:** plugin-indstillingerne er ikke eksponeret over MCP (`wp_get_plugin_settings`
returnerer tomme `options` for alle seks forsøgte), så alt om *konfiguration* nedenfor er læst ud af
adfærd udefra, ikke af indstillingssider. Premium-plugins' versionsnumre kan ikke sammenlignes med
WordPress.org, fordi deres opdateringskanal er sælgerens.

## 1. Bagud (3)

| Plugin | Installeret | Seneste | Kommentar |
| --- | --- | --- | --- |
| Simple Custom CSS and JS | 3.54.3 | 3.55 | Se overlap §3.4. Slug i wp.org er `custom-css-js`, ikke `simple-custom-css-js` |
| Site Kit by Google | 1.190.0 | 1.191.0 | Minor |
| Smash Balloon TikTok Feed | 1.9.0 | 1.9.1 | Se overlap §3.3 |

Alle tre er minor-opdateringer; ingen af dem er sikkerhedsrettelser, så de kan lægges i et batch.

**To forlod listen 2/11 om aftenen:** AI Engine blev opdateret 3.8.9 → **3.9.0** og er nu på seneste
version (og er stadig MCP-serveren, se §4), og Royal MCP står nu i **1.5.14**, som også er seneste — men
den er inaktiv og dermed i §4 i stedet. MCP-adgangen kan læse plugin-listen men ikke skrive plugin-filer,
heller ikke nu hvor OAuth virker (AI Engines *Drafts Only* tillader kun læsning), så opdateringen sker i
WP-admin → Plugins → Opdateringer.

**Bemærkning til de "16 plugins bagud".** Tallet stammer fra
`plugin-opdateringer-triage-2026-11-02.md`, som bygger på **`backup-2026-10-23/plugins.json`** og
eksplicit skriver, at der ikke var live-adgang. Det snapshot indeholder plugins, der **ikke findes på
det live site**: `seo-engine`, `vibe-ai`, `wplr-sync`, `mainwp`, `mainwp-child`, `media-file-renamer`.
Den live målte liste har 38 plugins, og dér er 3 bagud (§1). De to tal beskriver altså ikke samme site-tilstand.

Hvor de to dokumenter er uenige, gælder denne måling, fordi den er læst af det kørende site 2/11.
Triage-dokumentets **risikovurderinger pr. plugin** er stadig brugbare for de plugins, der faktisk er
installeret (`svg-support`, `really-simple-ssl`, `ai-engine`), og dets advarsel om at aflæse de faktiske
versioner i wp-admin før en batch er stadig den rigtige arbejdsgang.

## 2. Kan ikke tjekkes udefra (3)

| Plugin | Version | Status | Hvorfor |
| --- | --- | --- | --- |
| Steamgears Autopilot | 7.22.0 | aktiv | Egenudviklet, ingen wp.org-liste. Live-verificeret 2/11 2026 via både `wp_list_plugins` og `status` |
| WooCommerce.com Update Manager | 1.0.3 | aktiv | Følger med WooCommerce-abonnementet |
| WP Pusher | 3.0.20 | aktiv | Premium, sælgerens kanal |

## 3. Overlap — to plugins, samme opgave (5 par)

| # | Par | Konsekvens | Anbefaling |
| --- | --- | --- | --- |
| 3.1 | **Health Check & Troubleshooting** 1.7.1 + drop-in `health-check-troubleshooting-mode.php` | Ikke to plugins: drop-in'en *er* Health Checks egen fil. Roadmap S0.5 beskrev det som "to plugins", det er upræcist | Slet Health Check helt; drop-in'en forsvinder med den. Det er et fejlsøgningsværktøj, ikke drift |
| 3.2 | **Really Simple Security** 9.8.4 + **Easy HTTPS Redirection** 2.0.2 | To plugins der begge tvinger HTTPS. Verificeret udefra: `http://steamgears.eu/` svarer 301 til https, så mindst én virker | Fjern Easy HTTPS Redirection. Den er den ældste og gør mindst |
| 3.3 | **TikTok** 1.4.3 + **Smash Balloon TikTok Feed** 1.9.0 | Den ene poster/shopper, den anden viser en feed. Overlappet er kun navnet — men to TikTok-apps i samme butik er to steder at miste et token | Beslut om feeden vises på sitet. Hvis nej: fjern Smash Balloon |
| 3.4 | **Simple Custom CSS and JS** 3.54.3 + **WPCode Lite** 2.4.1 | To snippet-managere. Kode i databasen uden for git — den slags forsvinder ved et host-skift | Vælg WPCode (aktivt vedligeholdt, har revisionshistorik), flyt indholdet, fjern den anden |
| 3.5 | **Site Kit by Google** 1.190.0 + **Google for WooCommerce** 3.10.0 | Begge indsætter Google-tags; `/shop/` indeholder 14 `gtag(`-kald. Dobbelt sporing giver dobbelte sidevisninger i GA4 | Behold Site Kit til Analytics, Google for WooCommerce til Merchant Center-feeds, og slå tag-indsættelsen fra i den ene |

## 4. Inaktive plugins (2) — beslut, ikke lad dem ligge

| Plugin | Anbefaling | Grund |
| --- | --- | --- |
| **Royal MCP** 1.5.14 | Slet, eller lad den ligge urørt | Agentens tidligere vej ind, slukket 2/11 (svarer `404 rest_no_route`). Nu afløst af AI Engine på `/wp-json/mcp/v1/http`, så den gør ingen nytte — men en inaktiv connector er stadig filer og stadig en attack surface, hvis nogen genaktiverer den ved et uheld |
| **Royal Links** 2.0.15 | **Prøv den til S1.5** | Se nedenfor — det er det mest interessante fund i auditten |

**AI Engine er aktiv, ikke inaktiv** (rettet 2/11 2026, den stod tidligere i denne tabel). `/wp-json/mwai/v1/*`
svarer `401`, ikke `404`, og `/wp-json/mcp/v1/http` er den MCP-server `.mcp.json` peger på — den er agentens
eneste kanal og skal blive. Den har en CVE-historik (CVE-2026-16954 i Public API-selvtesten, CVE-2026-110732 i
chatboten), men chatboten er slået fra og den offentlige API-selvtest kræver et token. Efter 2/11 2026 er
status: OAuth virker, 206 værktøjer, **læsning tilladt og skrivning spærret af *Drafts Only***.

**Royal Access 1.0.7 er ikke længere installeret** — den stod i den første måling som "aktivér eller slet", og
den er nu væk af sig selv. Listen ovenfor er den målte, ikke den antagede.

### Royal Links løser en blokeret roadmap-opgave

S1.5 kræver at hvert butikslink går gennem `/go/<id>/`, så klik kan måles. Planen var Redirection,
men den er blokeret: `wp_redirection_groups` er tom, `Red_Item::create` fejler med "Invalid group",
og MCP kan ikke oprette en gruppe — der skal et klik i WP-admin til.

**Royal Links er netop link-cloaking** ("Affiliate Link Cloaking"), den er allerede installeret, og
den er inaktiv. Aktiveres den, kan `/go/<id>/`-stien sandsynligvis leveres uden at røre Redirection.
Det skal testes på ét link først (den skal ikke ramme de 39 eksisterende links), men det flytter S1.5
fra "blokeret af ejerens klik" til "kan gøres nu".

## 5. Risikable, men nødvendige

| Plugin | Risiko | Hvad der skal tjekkes |
| --- | --- | --- |
| **Database Cleaner** 1.4.8 | Kan slette revisionshistorik og transienter automatisk | At ingen automatisk oprydning er slået til. Revisionshistorik er den eneste fortrydelse der findes på indhold |
| **W3 Total Cache** 2.11.0 + `advanced-cache.php` + `object-cache.php` | Sidecache mod WooCommerce-kurv. Verificeret: `/cart/` og `/checkout/` svarer `private, no-cache` — det er korrekt. Ingen W3TC-cache-headere ses på `/shop/`, så siden caches muligvis slet ikke (S4.11 måler 5,8 s) | Om siden faktisk caches, og om minify/lazyload er slået til (det er ikke, jf. S4.11) |
| **Printify Shipping Method** 3.2 | Eneste Printify-plugin (leverandøren er Printful). Der er **ingen fulfilment-connector**, hvilket er hele blokeringen for tees og Herbarium | Ikke et plugin-problem, men det gør plugin-listen til beviset på blokeringen |
| **WP Pusher** 3.0.20 | Deploy-vej uden om GitHub Actions + zip, som repoet dokumenterer | Om den er i brug. Hvis ikke: fjern, så der kun er én deploy-vej |
| **Coming Soon** 6.20.10 | Kan sætte hele butikken i "kommer snart" for gæster | Verificeret 2/11: `/shop/` svarer 200 med produkter for en anonym klient, og teksten "maintenance" på siden er et produktnavn, ikke en holding page. Ingen aktuel risiko |
| **Pinterest for WooCommerce** 1.6.1 | Token giver 401 (roadmap P1.5) | Plugin'et kører blindt. Enten forny token eller deaktivér |

## 6. Anbefalet rækkefølge (S3.4)

| Trin | Handling | Tid |
| --- | --- | --- |
| 1 | Slet Health Check (drop-in'en forsvinder med den) | 10 min |
| 2 | Fjern Easy HTTPS Redirection; bekræft at http stadig 301'er | 10 min |
| 3 | Beslut TikTok-feed; fjern den ene | 10 min |
| 4 | Flyt snippets til WPCode, fjern Simple Custom CSS and JS | 30 min |
| 5 | Slå Google-tag fra i den ene af Site Kit / Google for WooCommerce | 10 min |
| 6 | Test Royal Links på ét link som `/go/`-sti | 30 min |
| 7 | Batch-opdater de 3 resterende (§1) | 15 min |

I alt cirka **1,75 time** — mindre end roadmap'ens 1,0 developer-dag, fordi oprydningen er enklere end
antaget. Det er stadig ejerens klik: MCP kan læse plugins, ikke slette eller opdatere dem — og selv hvis
den kunne, ville *Drafts Only* stoppe det.
