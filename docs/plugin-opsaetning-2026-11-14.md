# Plugin — nul-adgangs-punkterne som klar-til-brug opsætning (14/11 2026)

De syv ejerpunkter for Steamgears Autopilot er her skrevet som **én handling hver**: hvilken skærm, hvilket
felt, hvilken værdi, og hvordan det verificeres bagefter. Alt er læst ud af plugin'ets egen kode
(`includes/core.php` standardværdier, `includes/keys.php`, `includes/alerts.php`) — ikke gættet.

**Hvor felterne bor:** alle nøgler på **én** skærm, *Settings → Keys*
(`/wp-admin/admin.php?page=sgap-settings#keys`), ét kort pr. tjeneste. Ingen andre faner viser et nøglefelt.
Funktionsindstillinger (auto, alarmer, publicering) ligger på *Settings → Autopilot*, *Settings → Alerts* og
*Settings → Publishing*.

## 1. Pinterest — mangler kun scopet `boards:read`

| | |
| --- | --- |
| Målt nu | tokenet ligger i **Pinterest for WooCommerce** og virker: `/user_account` svarer 200 som `cpaleadsis` (id 688980580401955331). Men appen har kun `ads`, `billing`, `catalogs`, `pins`, `user_accounts`, så `/pins` og `/boards` afvises, og kanalstatus står `needs_scope` |
| Handling | **Pinterest → Developer app → Configure → scopes:** tilføj `boards:read` (behold `pins:read`). Genforbind integrationen, så det nye token får scopet. Alternativt: indsæt et developer-token med `boards:read` + `pins:read` under *Settings → Keys → Pinterest* |
| Verifikation | `steamgears-autopilot/channel-health` skal skifte `pinterest` fra `needs_scope` til `ready`. Derefter `python3 tools/live_channels.py` |

## 2. De fem kanaler uden token

Alle fem indsættes under *Settings → Keys*, hver sit kort. Feltnavnene er plugin'ets egne option-navne, så de
kan slås op i `wp_options`, hvis noget skal verificeres:

| Kanal | Felter der skal udfyldes | Hvor værdien kommer fra | Krav |
| --- | --- | --- | --- |
| **Instagram** | `instagram_user_id`, `instagram_token`, `instagram_graph_host` | Meta-appens *Instagram Login* → langlivet **User**-token (60 dage) + professionelt konto-id. `graph_host` = `graph.instagram.com` (skift kun til `graph.facebook.com` ved Facebook Login) | kontoen skal være professionel |
| **Facebook** | `facebook_page_id`, `facebook_page_token` | Meta-app → *Facebook Login for Business → Page*: **Page**-token (et user-token kan ikke poste som siden) | `pages_read_engagement` + `pages_manage_posts` |
| **Bluesky** | `bluesky_handle`, `bluesky_app_password` | bluesky → Settings → App passwords (ikke dit login-password) | handle med `@` |
| **Mastodon** | `mastodon_instance`, `mastodon_token` | din instans → Preferences → Development → New application | scope `write:statuses` |
| **Telegram** | `telegram_bot_token`, `telegram_chat_id` | @BotFather → `/newbot`; chat-id via `@userinfobot` eller `getUpdates` | botten skal være medlem af kanalen/gruppen |

**Verifikation:** `channel-health` skal give `ready` for hver kanal, og `analytics_summary` skal gå fra
`measured 0` til mindst én række pr. kanal, der faktisk har tal.

## 3. Fejlmailen — punktet der allerede virker, men bør stå synligt

| | |
| --- | --- |
| Sandheden | `sgap_alert_email` er tom, men `SGAP_Alerts::recipient()` falder tilbage til `admin_email` — så mailen går ud alligevel. Hjælpeteksten i feltet siger det samme: *Empty = the site admin address* |
| Handling | *Settings → Alerts*: skriv butikkens driftsadresse i **Alert e-mail** (så den ikke afhænger af, hvem der er WordPress-admin), lad **Mode** stå på `immediate`, og lad **Warn when the queue has fewer days than** stå på `2` |
| Valgfrit | **Channel caps** (fx `pinterest=2, facebook=3`) giver hver kanal sin egen runway; uden caps måles alle mod **Max posts per day** (standard 8) |
| Verifikation | `SGAP_Alerts::recipient()` returnerer den adresse, du skrev, og en tvunget fejl giver en mail inden for 6 timer (dedup pr. element, maks. 5 mails/time) |

## 4. AI-kredit — én påfyldning

| | |
| --- | --- |
| Målt | sidste generering 7/11 fejlede med **»You've run out of funds«**; 40-45 kladder venter |
| Handling | [aimlapi.com](https://aimlapi.com/) → Dashboard → **Billing**: fyld op. Nøglen ligger allerede i *Settings → Keys → AI text* (provider `aimlapi`) |
| Verifikation | *Test the saved keys* på Keys-skærmen svarer OK, og `steamgears-autopilot/check-keys` giver ikke længere »no credit left«. Derefter kører `generate` igen |

Vil du hellere bruge Anthropic direkte: skift **AI provider** til `anthropic` og indsæt nøglen — men så kan
pluginnet ikke lave billeder (det kræver AIMLAPI).

## 5. Full auto — en beslutning, ikke en opsætning

| | |
| --- | --- |
| Nu | `sgap_full_auto_enabled = no`: alt skal godkendes, og 40 elementer venter i køen |
| Handling | *Settings → Autopilot*: sæt **Full auto** til `yes`, hvis pluginnet må publicere uden din godkendelse. Det er et kvalitetsvalg, ikke en teknikalitet — med `no` koster hvert opslag et klik |
| Verifikation | `steamgears-autopilot/status` viser `full_auto: yes`, og køen falder uden manuelle godkendelser |

## 6. Distributionsvej — RSS eller API

| | |
| --- | --- |
| Nu | `sgap_publish_mode = rss`, og der er **0 Publer-konti**: RSS-feedet læses af en ekstern tjeneste, og pluginnet kan ikke se, om opslaget blev publiceret |
| Handling | Enten (a) tilslut Publer (**Business**-plan) og sæt *Publishing* → mode `api`, eller (b) bliv på `rss` og accepter, at publiceringsstatus er ukendt |
| Verifikation | Ved `api`: `steamgears-autopilot/check-publer` viser workspaces og konti; ved `rss`: feedet svarer 200 for hver kanal |

## 7. Command Center-retningen (P2.1–P2.3)

Et **ja/nej**: skal pluginnet være énvejs integreret med Command Center (plugin → CC, ingen data tilbage)?
Koden er bygget og testet; det er retningen, der mangler et svar på. Svar »ja«, og posten lukkes som bevidst
valg; svar »nej/udskudt«, og den bliver stående som en åben beslutning uden at holde andet tilbage.

## Rækkefølge — mest effekt for mindst arbejde

1. **AI-kredit** (5 minutter): låser 40-45 kladder op.
2. **Alert-mail** (2 minutter): gør alle senere fejl synlige.
3. **Pinterest-scope** (10 minutter): den ene kanal, der faktisk har et token.
4. **Full auto** (1 beslutning): afgør, om køen tømmer sig selv.
5. **De fem tokens** (10-30 minutter, afhængigt af hvor mange konti du har).
6. **Publer/distributionsvej** (kræver abonnement — kan vente).
7. **Command Center** (et ja/nej, ingen teknik).

**Verifikation efter hvert punkt:** `python3 tools/live_channels.py` og abilityen
`steamgears-autopilot/channel-health`. Agenten kan køre begge, så snart der er et token — der skal ikke gættes
på, om det virkede.
