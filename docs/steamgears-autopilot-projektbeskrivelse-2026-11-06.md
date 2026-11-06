# Steamgears Autopilot — projektbeskrivelse til eksterne AI-værktøjer

**Dato:** 6. november 2026 · **Plugin:** 7.38.2 · **Status:** 63,6 % (16,60 af 26,10 developer-dage)

Dette dokument er skrevet, så det kan kopieres **som én blok** ind i ChatGPT, Claude eller et andet
værktøj, der skal arbejde videre på pluginnet. Alt nedenfor er målt eller læst i repoet 6/11 2026 —
ikke antaget. Tal, stier, versionsnumre og funktionsnavne kan slås op i kilden.

> Dokumentet er en briefing bygget på `plugins/steamgears-autopilot/ROADMAP.md`,
> `plugins/steamgears-autopilot/README.md`, `projektstatus.json` og en gennemgang af koden.
> Kilden er altid koden, ikke denne tekst.

---

## 1. Projektets formål

Et WordPress-plugin, **Steamgears Autopilot**, der automatiserer indholdsproduktion og social udgivelse
for **steamgears.eu** (steampunk-kostumer, tilbehør og DIY-guides).

Pluginnet skal kunne:

- **Skrive** — en AI-model skriver ét opslag pr. kanal ud fra sitets egne indlæg og produkter.
- **Godkende** — opslag lander som kladder (AI Drafts) eller går direkte i kø ved Full auto.
- **Publicere** — via Publer (RSS-feed eller API), plus **direkte** til Bluesky, Mastodon og Telegram.
- **Oversætte** — én kilde-post giver én oversat caption pr. sprog (WPML/Polylang).
- **Spore** — hvert opslag linker via `/go/<id>/` med UTM-parametre og klik-/ordre-attribution.
- **Kvalitetssikre** — quality gate (forbudte ord, døde links, manglende alt-tekst, næsten-dubletter)
  og pre-flight pr. kanal (tekstlængde, hashtag-loft, billedformat, board).
- **Overvåge** — fejlmail, kø-runway, backoff, audit-log og selvkontrol.
- **Styres udefra** — abilities (WordPress 6.9+) eksponeret over MCP, plus et katalog af
  almene WordPress-værktøjer (`steamgears-tools`).
- **Vedligeholde sig selv** — produktfeed til Google Merchant, IndexNow, sitemap-audit, cron og
  selvopdatering fra GitHub-zip.

**Uden for scope:** at ændre priser, lager eller ordrer; at publicere uden menneskelig godkendelse i
produktion; at bygge nye REST-broer til Command Center (Command Center er udfaset i denne retning).

---

## 2. Repo, stier og miljø

| Ting | Værdi |
| --- | --- |
| Repo | `https://github.com/humle93/steamgears-site` |
| Gren | `main` (alt arbejde skal ende der) |
| Lokal sti (Termux/Android) | `~/steamgears-site` |
| Plugin-sti | `~/steamgears-site/plugins/steamgears-autopilot` |
| Hovedfil | `plugins/steamgears-autopilot/steamgears-autopilot.php` |
| Roadmap | `plugins/steamgears-autopilot/ROADMAP.md` |
| README | `plugins/steamgears-autopilot/README.md` |
| Tests | `plugins/tests/` |
| Projektstatus | `projektstatus.json` → `docs/projektstatus.md` (AUTO-blokke) |

Vigtigt: mappen hedder **`steamgears-autopilot`**, ikke `social-autopilot`.

**Miljøer.** WordPress-kernen ligger hos Simply. Repoet er dokumentation, plugin-kilde og assets;
ændringer på selve sitet sker over MCP, ikke herfra. I Freebuff/cloud findes ingen `php` som standard —
brug den statiske binær i `.tools/php` (ikke i git). I Termux findes `php` normalt direkte.

---

## 3. Verificeret tilstand 6/11 2026

| Måling | Resultat |
| --- | --- |
| `php -l` på alle PHP-filer | 0 syntaksfejl (PHP 8.3.14) |
| `php plugins/tests/smoke.php` | **453 PASS, 0 FAIL**, exit 0 |
| PHP-filer i pluginnet | 30 (28 i `includes/` + hovedfil + `uninstall.php`) |
| Linjer PHP | 10.754 |
| Klasser | 42 |
| PHP-krav | 8.0+ i headeren; CI linter 8.0–8.5 |
| WordPress-krav | 6.4+ (abilities kræver 6.9+) |
| Seneste release | 7.38.2 — provider-kald logges med culprit-oversættelse, så afviste værktøjsnavne kan diagnosticeres |

Testfilerne: `smoke.php` (stubbet WordPress, kører overalt), `admin-tools.php` (49 checks, kræver
`wp eval-file`), `automation.php`, `integration.php` + `integration.sh` (rigtig WordPress + MySQL, CI),
`router.php` og `fixtures/`.

---

## 4. Komplet filstruktur — kode-roadmap

Hver fil er et selvstændigt ansvar. Kolonnen "Indgang" er den vej koden faktisk bliver kaldt fra.

| Fil | Linjer | Klasser | Ansvar | Indgang |
| --- | --- | --- | --- | --- |
| `steamgears-autopilot.php` | 77 | — | Plugin-header, `SGAP_VERSION`, værn mod dobbelt-load, loader | WordPress |
| `uninstall.php` | 32 | — | Dropper 9 tabeller, options, transients og post-meta | Afinstallation |
| `includes/abilities.php` | 388 | `SGAP_Abilities` | Ability-katalog og permission-callbacks (`manage_options`) | `wp-abilities/v1` |
| `includes/admin-tools.php` | 546 | `SGAP_AdminTools` | 29 admin-operationer som abilities; læs/skriv indstillinger | Abilities, chat, MCP |
| `includes/admin-ui.php` | 241 | `SGAP_UI` | Admin-skal, kort, pills, tomme tilstande, ikoner | Alle admin-sider |
| `includes/alerts.php` | 154 | `SGAP_Alerts` | Fejlmail pr. post, kø-runway-advarsel, dedupe (6 t / 5 pr. time) | `sgap_queue_failed`, dagligt selvtjek |
| `includes/audit.php` | 252 | `SGAP_Audit` | Publish log: ét forsøg pr. række, HTTP-status, aktør, retention | `SGAP_Queue::mark()` |
| `includes/automation.php` | 577 | `SGAP_Rescue`, `SGAP_Auto` | Billed-rescue, promover nye sider, ugentlig artikel + mail, Site Health | Cron |
| `includes/blackout.php` | 158 | `SGAP_Blackout` | Blackout-kalender; flytter i stedet for at droppe | Publish tick, `Queue::add()` |
| `includes/bluesky.php` | 234 | `SGAP_Bluesky` | Direkte AT Protocol-posting (createSession + createRecord) | `SGAP_Publer::tick()` |
| `includes/chat.php` | 308 | `SGAP_Chat` | AI-chat i wp-admin med værktøjs-loop (maks. runder) | Admin-side + AJAX |
| `includes/command-center.php` | 86 | `SGAP_CC` | Noterer `X-Command-Center`, advarer når CC er tavs > 2 t | REST, selvtjek |
| `includes/core.php` | 2186 | `SGAP_Install`, `SGAP_Settings`, `SGAP_UTM`, `SGAP_Learning`, `SGAP_ROI`, `SGAP_Tracking`, `SGAP_Queue`, `SGAP_REST`, `SGAP_Maintenance`, `SGAP_Admin` | dbDelta (9 tabeller), indstillinger + secrets, UTM, læring/ROI, `/go/`-routing, kø og loft, REST, oprydning, admin-menu | Alt |
| `includes/generator.php` | 485 | `SGAP_AI`, `SGAP_Generator` | **AI-klient** (Anthropic + alle `/chat/completions`, dvs. AIMLAPI) og daglig generering | Cron `sgap_generate`, abilities |
| `includes/keycheck.php` | 201 | `SGAP_KeyCheck` | Tester Publer- og AI-nøgler, inkl. AIMLAPI (1-token-kald) | Gem indstilling, dashboard, ability |
| `includes/locales.php` | 168 | `SGAP_Locales` | Oversatte varianter pr. sprog, WPML/Polylang-opslag | `Queue::add()` |
| `includes/mastodon.php` | 112 | `SGAP_Mastodon` | Direkte posting med `Idempotency-Key` | `SGAP_Publer::tick()` |
| `includes/mcp-tools.php` | 1234 | `SGAP_MCP_Tools`, `SGAP_MCP_Key` | MCP-bro, **secret-option-værn**, 26 almene værktøjer, undo-token | MCP |
| `includes/meta-audit.php` | 155 | `SGAP_Meta_Audit` | Titel- og meta-description-audit (kun læsning) | Ability |
| `includes/preflight.php` | 227 | `SGAP_Preflight` | Kanalens egne grænser **før** første API-kald; reparerer det maskinelle | `Publer::publish()` |
| `includes/publer.php` | 369 | `SGAP_Publer` | Publer-klient, payload pr. netværk, tick-loop der ruter direkte kanaler | Cron, admin |
| `includes/quality.php` | 288 | `SGAP_Quality` | Quality gate: forbudte ord, døde links, alt-tekst, dubletter | `Queue::add()` |
| `includes/reminder.php` | 126 | `SGAP_Reminder` | "Send som påmindelse" i stedet for at poste | Queue-knap, ability |
| `includes/rss.php` | 186 | `SGAP_RSS` | Én feed pr. kanal til Publer Professional | `/wp-json/sgap/v1/feed/<kanal>` |
| `includes/setup.php` | 379 | `SGAP_Setup` | Setup-wizard, fanebladet indstillingsside, tjekliste | Admin |
| `includes/site-seo.php` | 681 | `SGAP_Site_SEO` | Hele sitet i ét gennemløb: 20 SEO/AEO/GEO-regler + 4 domænetjek | Admin, ability |
| `includes/studio.php` | 573 | `SGAP_Images`, `SGAP_Research`, `SGAP_Studio` | Artikler/guides/produkttekster, **AIMLAPI-billeder**, SerpApi-research | Admin, abilities |
| `includes/telegram.php` | 93 | `SGAP_Telegram` | Direkte posting; `ok:false` med HTTP 200 behandles som fejl | `SGAP_Publer::tick()` |
| `includes/text.php` | 63 | `SGAP_Text` | Grafem-sikker afkortning med linket reserveret | Alle kanaler |
| `includes/updater.php` | 175 | `SGAP_Updater` | Selvopdatering fra GitHub-zip (`plugins/steamgears-autopilot-v<version>.zip` på `main`) | `Update URI` |

---

## 5. Roadmap og status (fra `projektstatus.json`)

| Modul | Færdig | Total | Procent | Indhold |
| --- | --- | --- | --- | --- |
| P0 — reliability | 3,55 d | 4,0 d | 88,8 % | Fejlmail, runway, backoff, cron-ærlighed, audit-log, dublet-værn |
| P1 — paritet | 4,85 d | 11,35 d | 42,7 % | Pre-flight, remind, blackout, evergreen, analytics, quality gate, SEO, UTM |
| P2 — kanaler | 4,5 d | 7,0 d | 64,3 % | Bluesky, Mastodon, Telegram, Etsy, multi-sprog |
| P3 — hygiejne | 1,1 d | 2,0 d | 55,0 % | Død kode, ubrugte indstillinger, testdækning, release notes |
| **I alt** | **14 d** | **24,35 d** | **57,5 %** | |

Konkret pr. punkt:

- **P0.1 ✅** fejlmail (`alerts.php`). **P0.2 ✅** kø-runway i dage. **P0.3 ✅** backoff 5 → 15 → 45 min.
- **P0.4 ~** cron-detektion + dokumentation færdig, men **rigtig host-cron mangler** (0,4 af 0,5 d).
- **P0.5 ✅** publish log. **P0.6 ✗** cross-system dublet-værn — **åben**, afhænger af Command Center-siden.
- **P1.1 ✅** pre-flight, **P1.2 ✅** remind, **P1.3 ✅** blackout, **P1.6 ✅** quality gate,
  **P1.7 ✅** helsides SEO, **P1.8 ✅** `utm_campaign`.
- **P1.4 ✅** evergreen-recycling (2,5 d, 7.41.0): markér et indlæg/produkt, få en frisk variation efter 60 dage til næste kanal, indtil budgettet eller udløbsdatoen stopper det — egen cron, metabox, admin-side og tre abilities (523 smoke-checks). **P1.5 ✗** platform-analytics (4,0 d) —
  blokeret af Pinterest-token (401) og Meta-review.
- **P2.1 ✅** Bluesky, **P2.2 ✅** Mastodon, **P2.3 ✅** Telegram, **P2.5 ✅** multi-sprog.
  **P2.4 ✗** Etsy (byg 2,5 d eller skjul de fem indstillinger på 15 min — ejerens beslutning).
- **P3.2 ✅**, **P3.3 ✅**, **P3.5 ✅**. **P3.1 ✗** død kode + 17 ubrugte indstillinger. **P3.4 ✗** release notes.

**Åbne beslutninger for ejeren:** Etsy (byg/skjul), direkte netværks-tokens (fjern/behold),
Action Scheduler (2 d) vs. WP-Cron, platform-analytics (4 d), Command Center-retning (envejs anbefalet).

---

## 6. AIMLAPI — hvor klienten ligger

AIMLAPI er **tekst- og billedlaget**. Der er ingen separat SDK; alt går gennem WordPress' HTTP-API, så
tests kan stubbe transporten.

### 6.1 Tekst (chat completions)

| Ting | Sted |
| --- | --- |
| Konstant endpoint-base | `includes/generator.php` → `SGAP_AI::DEFAULT_BASE_URL = 'https://api.aimlapi.com/v1'` |
| Standardmodel | `SGAP_AI::AIMLAPI_MODEL = 'anthropic/claude-opus-5.5'` |
| Provider-valg | `SGAP_AI::provider()` → `anthropic` \| `aimlapi` \| `openai_compatible` |
| Effektiv base-URL | `SGAP_AI::base_url()` — for `aimlapi` **altid** `api.aimlapi.com/v1` (en gammel `ai_base_url` kan ikke sende nøglen til et fremmed domæne) |
| Kald | `openai_compatible()`, `chat_body()`, `json_call()` → `POST {base}/chat/completions` |
| Fejl | `SGAP_AI::decode()` → `WP_Error` med HTTP-status i `data['status']` |
| Nøgletest | `includes/keycheck.php` → `SGAP_KeyCheck::check_aimlapi()` (ét kald med `max_tokens: 1`) |
| Chat med værktøjer | `includes/chat.php` → `SGAP_Chat::loop_chat_completions()` |

Request-formen (som implementeret):

```php
$response = wp_remote_post( SGAP_AI::base_url() . '/chat/completions', [
    'timeout' => 120,
    'headers' => [
        'Authorization' => 'Bearer ' . $key,   // nøglen i header, aldrig i URL
        'Content-Type'  => 'application/json',
    ],
    'body' => wp_json_encode( $body ),
] );
```

`$body` indeholder `model`, `max_tokens`, `messages` og ved AIMLAPI desuden
`reasoning_effort: 'low'` (Claude på AIMLAPI tænker først; tænkning spiser token-budgettet og giver
ellers tomme svar). Ved struktureret output tilføjes `response_format: json_object`.

### 6.2 Billeder

| Ting | Sted |
| --- | --- |
| Endpoint | `includes/studio.php` → `SGAP_Images::ENDPOINT = 'https://api.aimlapi.com/v1/images/generations'` |
| Standardmodel | `bytedance/seedream-5-0-pro` (ca. 0,045 USD pr. billede) |
| Nøglevalg | `SGAP_Images::key()`: `image_api_key`, ellers `ai_api_key` når provideren er AIMLAPI |
| Størrelse | `SGAP_Images::body()` — Seedream 1024–2752, flux 256–2048, ellers `size` |

Billedet gemmes i mediebiblioteket (`media_handle_sideload`) og kan blive udvalgt billede eller
billede på et kø-element.

### 6.3 Hvad AIMLAPI **ikke** bruges til

SerpApi (søgeord/SERP), Publer (publicering), Bluesky/Mastodon/Telegram (direkte kanaler) og WooCommerce
har deres egne nøgler. AIMLAPI-nøglen må ikke genbruges til dem.

---

## 7. API-nøgler — den faktiske implementering

Nøgler er **ikke** fritekst i koden og **ikke** en separat `sgap_get_api_key()`-funktion. De går gennem
indstillingslaget:

```php
// includes/core.php
SGAP_Settings::get( $key )   // læser optionen 'sgap_<key>'; en wp-config-konstant vinder for secrets
SGAP_Settings::set( $key, $v ) // skriver; secrets skrives med autoload = false
```

- **Option-navne:** `sgap_ai_api_key` (AI/AIMLAPI), `sgap_image_api_key` (billeder),
  `sgap_publer_api_key`, `sgap_serpapi_key`, `sgap_bluesky_app_password`, `sgap_mastodon_token`,
  `sgap_telegram_bot_token` m.fl. — alle listet i `SGAP_Settings::SECRET_KEYS`.
- **wp-config-konstant:** `SGAP_AI_API_KEY` (mønsteret er `SGAP_` + stort option-navn), dvs.
  `SGAP_IMAGE_API_KEY`, `SGAP_PUBLER_API_KEY`, `SGAP_TELEGRAM_BOT_TOKEN` osv.
  Der findes **ingen** `SGAP_AIMLAPI_KEY` og ingen option ved navn `sgap_aimlapi_key`.
- **Formularen** viser aldrig den gemte værdi: password-felt med tom værdi, en "Saved"-pill, og et
  "Remove saved key"-flueben. Et tomt felt betyder "behold den gemte nøgle".
- **Autoload:** ved aktivering oprettes alle secrets med `add_option(..., false)`, og
  `wp_set_option_autoload_values()` sætter dem til `no` (WordPress 6.6+).
- **Værn i MCP:** `SGAP_MCP_Tools::is_secret_option()` afviser at læse **og** skrive options, hvis navn
  ligner en credential (`_key`, `_secret`, `_token`, `password`, `api_key`, …).

### 7.1 Rettelse 6/11 2026 (commit `fbe6f9d`)

To skriveveje brød løftet om "ikke autoloadet" og "tomt felt beholder værdien":

| Sted | Før | Efter |
| --- | --- | --- |
| `SGAP_Settings::set()` | `update_option()` med standard-autoload, også for secrets | autoload `false` for alt i `SECRET_KEYS` |
| `SGAP_AdminTools::update_settings()` | rå `update_option()`; tom værdi slettede nøglen; ny option blev autoloadet; `wp-config`-nøgle blev "gemt" uden effekt | tom værdi beholder nøglen (`kept (the new value was empty)`), `wp-config`-nøgle rapporteres (`ignored (set in wp-config.php)`), og skrivningen går gennem `SGAP_Settings::set()` |

Dækket af fire nye smoke-checks (404 → 408 PASS).

### 7.2 Regler, der ikke må brydes

1. Nøglen sendes i `Authorization: Bearer` — aldrig i URL, query-streng eller HTML.
2. Nøglen må ikke optræde i fejlbeskeder, logs eller audit-rækker.
3. Nye nøgler skrives med `autoload = false` og læses gennem `SGAP_Settings::get()`.
4. `wp-config.php` vinder altid over databasen; en skrivning må ikke foregive at have effekt.
5. Undtagelsen er SerpApi, hvis API kræver `api_key` som query-parameter (`includes/studio.php`);
   Telegram kræver bot-token i URL-stien. Det er udbydernes kontrakt, ikke en fejl — men fejlteksten
   må stadig ikke gengive URL'en.

---

## 8. Sync-ID og idempotens — hvad der faktisk findes

**Vigtigt for enhver videre analyse:** strengen `sync_id`, `_cc_sync_id` og `cc_sync_id` findes
**ikke** i dette repo. En søgning over hele repoet (undtagen `.git`) giver nul træf — i plugin, tests,
docs og tools. Der er altså ingen sync-ID-kode at rette.

Hvad der findes i stedet:

| Mekanisme | Sted | Status |
| --- | --- | --- |
| Kø-fingerprint pr. opslag | `sgap_queue.fingerprint`; `SGAP_Queue::add()` | Findes |
| Cross-system dublet-værn | Roadmap **P0.6** (`channel` + `destination_url` + `title` inden for N dage, eksponeret i `/snapshot`) | **Ikke bygget** |
| Command Center-detektion | `includes/command-center.php`: header `X-Command-Center`, option `sgap_cc_seen` | Findes (envejs) |
| Post-meta om promovering | `_sgap_promoted_at` (cooldown pr. side) | Findes |
| AI-billede-meta | `_sgap_ai_image` (model + prompt) | Findes |

`_cc_sync_id` optræder kun i den vedlagte **Cirla**-dokumentation, hvor det beskriver den
Command Center ↔ WordPress-bro, der blev bygget uden om pluginnet. Command Center er udfaset i denne
retning (README: "Command Center is retired"), og pluginnet har derfor hverken feltet eller meta-nøglen.

**Hvis idempotens er målet:** byg P0.6 i pluginnet (fingerprint + `/snapshot`), eller genbrug
`sgap_queue.fingerprint`. Opfind ikke et parallelt `_cc_sync_id`-felt uden at beslutte, hvem der ejer
ID'et — det var netop den uafklarede grænseflade i Cirla-dokumentet.

---

## 9. Publiceringsveje og kanaler

| Vej | Hvordan | Hvor |
| --- | --- | --- |
| RSS (standard, Publer Professional) | Én feed pr. kanal: `https://steamgears.eu/?sgap_feed=<kanal>`; Publer auto-poster | `rss.php` |
| API (Publer Business) | `POST` til Publer, job følges op, op til 3 forsøg med backoff | `publer.php` |
| Bluesky | `com.atproto.server.createSession` + `createRecord`, session cachet 90 min | `bluesky.php` |
| Mastodon | `POST {instance}/api/v1/statuses` med Bearer + `Idempotency-Key` | `mastodon.php` |
| Telegram | `POST bot<token>/sendMessage`; `ok:false` med HTTP 200 er en fejl | `telegram.php` |
| Påmindelse | Mail i stedet for post (terminal status `reminded`) | `reminder.php` |

Kanaler: facebook, instagram, pinterest, linkedin, twitter/x, threads, bluesky, mastodon, telegram,
google. Instagram og Pinterest kræver billede.

---

## 10. Arkitektur- og sikkerhedsregler

- PHP 8.2+-kompatibel, objektorienteret, klasser præfikset `SGAP_`.
- Hovedfilen har gyldig plugin-header og starter med `if (!defined('ABSPATH')) exit;`.
- Al input: `check_ajax_referer()` / `wp_verify_nonce()`, `sanitize_text_field()`/`sanitize_key()`/`absint()`.
- Al output: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()`.
- Alle admin-handlinger: `current_user_can('manage_options')`.
- Database: `$wpdb->prepare()` ved rå SQL; egne tabeller oprettes med `dbDelta` (9 tabeller).
- REST-callbacks returnerer data eller `WP_Error` og kalder ikke selv `wp_send_json()`.
- Kanal-/kanalgrænser håndhæves i `preflight.php`, ikke ved at lade API'et fejle.
- Ingen opfindelse af nye indstillinger uden at de også registreres, vises og læses (17 indstillinger
  er i dag registreret uden at blive brugt — se P3.1).

---

## 11. Test- og commit-procedure

```bash
cd ~/steamgears-site
php plugins/tests/smoke.php > smoke-result.txt 2>&1
echo "Exit: $?"
tail -n 20 smoke-result.txt
```

- `Exit: 0` og ingen `FAIL`-linjer = grønt. `FAIL` = stop og ret kun den nævnte test.
- Mangler `php` (Freebuff/cloud): brug `./.tools/php plugins/tests/smoke.php`.
- Syntakstjek: `./.tools/php -l <fil>` (eller `php -l`) efter hver skrevet fil.

Før commit:

```bash
git status
git diff --stat
git diff --check
```

Commit-beskeden **skal** indeholde (repoets `tools/commit_check.py` afviser ellers, også i CI):

```
Modul: <modul/opgave>
Før: <tal> %  →  Efter: <tal> %  (Δ <tal>)
Grundlag: <regnestykke med tal, fx "2 af 5 faser", eller "ingen ændring">
```

Derefter `git add` de konkrete filer, commit og push til `main` i samme arbejdspas.

---

## 12. Kendte huller og blokeringer

| Hul | Konsekvens | Status |
| --- | --- | --- |
| P0.6 dublet-værn | Command Center kan aflevere samme opslag to gange | Åben |
| P0.4 rigtig host-cron | WP-Cron kan sove på en cachet side; opslag kommer for sent | Detektion findes, cron mangler |
| P1.5 analytics | Ingen platform-reach/engagement, kun egne klik | Blokeret (Pinterest 401, Meta-review) |
| P1.4 evergreen | ✅ Lukket 6/11 i 7.41.0 | `SGAP_Evergreen` + daglig cron + Evergreen-side |
| Etsy | Fem synlige indstillinger lover en integration, der ikke findes | Beslutning mangler |
| P3.1 død kode | `SGAP_Admin::get_setting_help_link()` har nul kald | Åben |
| Graph API / X-priser | Direkte X-posting er økonomisk meningsløs med link | Ikke anbefalet |

**Ikke verificeret i dette miljø:** `integration.php` og `admin-tools.php` kræver rigtig WordPress
(`wp eval-file`) og MySQL; de kører i CI, ikke lokalt i cloud-sandkassen. Der er ikke kaldt live mod
AIMLAPI (koster credit og kræver nøgle).

---

## 13. Korrektioner til tidligere udkast

Disse punkter har været forkerte i tidligere briefing-udkast og skal ikke gentages:

1. **Seneste commit** er `fbe6f9d`, ikke `224cfd9` (den er forælderen).
2. **Nøgle-navne:** option `sgap_ai_api_key`, konstant `SGAP_AI_API_KEY`. Der findes ingen
   `sgap_aimlapi_key`/`SGAP_AIMLAPI_KEY` — et forslag om at indføre dem ville duplikere
   indstillingslaget.
3. **`sync_id` findes ikke.** Bed ikke en AI om at "rette sync-ID-logikken"; der er intet at rette.
   Idempotens er P0.6 (fingerprint) og er en beslutning, ikke en fejlretning.
4. **Testtal:** smoke-suiten er 453 PASS / 0 FAIL (6/11), ikke "ukendt".
5. **Plugin-mappen** er `plugins/steamgears-autopilot`; `ROADMAP.md` og `README.md` ligger i den.
6. **`php` findes ikke altid** i cloud-miljøet — brug `.tools/php`, som AGENTS.md foreskriver.
7. **Commit-formatet** kræver Modul/Før/Efter/Δ/Grundlag; en fri commit-besked fejler i CI.
8. **Søgninger** skal begrænses til `--include='*.php'` og udelade `vendor/`, `node_modules/`, `.git/`
   og JSON-dumps; en åben `grep -R` rammer REST-schema-cache og giver ubrugeligt output.

---

## 14. Kopiér-klar instruktion til ChatGPT

> Du er teknisk assistent på **Steamgears Autopilot**, et WordPress-plugin (7.33.1) i
> `plugins/steamgears-autopilot` i repoet `humle93/steamgears-site`. Pluginnet skriver, kvalitetssikrer,
> oversætter og publicerer sociale opslag for steamgears.eu via Publer og direkte til Bluesky, Mastodon
> og Telegram. AI-laget er AIMLAPI (`https://api.aimlapi.com/v1/chat/completions`, model
> `anthropic/claude-opus-5.5`), billeder via `https://api.aimlapi.com/v1/images/generations`.
>
> **Fakta du skal holde dig til:** nøgler ligger i options `sgap_ai_api_key` / `sgap_image_api_key`,
> læses med `SGAP_Settings::get()` og kan overstyres med `SGAP_AI_API_KEY` i `wp-config.php`.
> Nøglen sendes som `Authorization: Bearer`, aldrig i URL eller HTML, og skrives med `autoload = false`.
> Der findes **ingen** `sync_id`/`_cc_sync_id` i koden. Test er `php plugins/tests/smoke.php`
> (453 PASS, 0 FAIL). Commit-beskeder skal indeholde Modul/Før/Efter/Δ/Grundlag.
>
> **Sådan arbejder du:**
> 1. Ét trin ad gangen, med konkrete filstier og linjenumre.
> 2. Foreslå den mindste sikre ændring; ingen omstrukturering af pluginnet, ingen nye rammeværk.
> 3. Bed om **én fil ad gangen**, hvis du mangler kontekst — aldrig om ZIP, hele repoet eller store
>    JSON-dumps.
> 4. Efter hver rettelse: bede om `php -l` på filen og et smoke-run, og Stop hvis der kommer `FAIL`.
> 5. Rør ikke databasen uden grund; nye kolonner skal kunne tilføjes med `dbDelta`.
> 6. Bed aldrig om rigtige nøgler. Brug pladsholdere som `<AIMLAPI_KEY>`.
> 7. Svar på dansk med engelske tekniske termer.
>
> **Når du er i tvivl:** spørg i stedet for at gætte — især om nøgler, rettigheder og hvad der er
> implementeret kontra planlagt (se roadmap-tabellen i §5).
