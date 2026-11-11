# Patch notes — Steamgears Autopilot

Nyeste version øverst. Hver note siger hvad der blev **tilføjet, ændret eller rettet**, hvordan det er
målt, og hvilket versionsnummer den hører til. Versionsnummeret i den øverste overskrift skal matche
`SGAP_VERSION`, plugin-headerens `Version:` og navnet på zip'en i `plugins/` — det tjekkes af
`python3 tools/patch_notes.py --check`.

Noterne er skrevet ud fra commit-historikken og de dokumenter der hører til hver version. Versioner før
7.65.0 er ikke backfillet; deres historik står i git og i README's afsnit pr. funktion.

<!-- patch-notes: baseline 7.65.0 -->

## 7.71.2 — 2026-11-11

**Cirla svarer nu med de rigtige HTTP-statuskoder — målt på et rigtigt WordPress.**

### Rettet
- Alle 13 `WP_Error` i modulet bærer `['status' => …]`, som resten af plugin'et gør: dagligt loft **429**,
  kvalitetsgate **422**, slukket modul **409**, manglende rettighed **403**, dårligt input **400**, ukendt
  indhold **404** og gemmefejl **500**. Før blev et budgetafslag og en gate-blokering sendt som HTTP 500,
  så en klient ikke kunne se forskel på «din kvote er brugt» og «serveren fejlede».
- Fundet ved at køre modulet på en rigtig WordPress-installation, ikke i sandkassen.

### Målt
- WordPress 7.1.4 med SQLite-driver, plugin installeret fra zip: kvalitetsgaten blokerede linket til en kladde
  (500 → **422**, ingen ny række), det daglige loft stoppede kald 6 (500 → **429**), en subscriber blev afvist
  (**403**), og et slukket modul gav **404** på ability-ruten. Bevis:
  `docs/library/cirla-wordpress-roegtest-2026-11-11.md`.

## 7.71.1 — 2026-11-11

**Patch notes er nu en fast del af hver udgivelse.** Ingen PHP-adfærd er ændret.

### Tilføjet
- `CHANGELOG.md` i pluginnet: patch notes for 7.65.0 og frem, og fra nu af en note ved hver version.
- `tools/patch_notes.py`: `--check` (nyeste note matcher versionen, ingen huller i historikken),
  `--latest` (noten klar til svaret), `--version X` og `--selftest` med fixtures.
- Værnet kører i `tools/natlige-vaern.sh` og i `tools/release.py --check --guards`.
- Regel i AGENTS.md §7.4: en version uden patch note er ikke en udgivelse.

### Målt
- `php -l`, smoke og `fejlscan` uændret grønne; zip'en er genopbygget med `CHANGELOG.md` i og er
  byte-identisk med kilden.

## 7.71.0 — 2026-11-10

**P9: Cirla er et modul, der kan slås til og fra, og som er slukket som standard.**

### Tilføjet
- `modules/cirla/class-cirla.php` indlæses kun, når `sgap_cirla_enabled` er `yes` — slået fra betyder
  ingen klasse, altså ingen ability og intet cron-job.
- Fire handlinger bag capabilityen `sgap_cirla_run`: `cirla-status`, `cirla-draft`, `cirla-seo-check`
  og `cirla-social-suggestions`.
- Dagligt AI-loft (`sgap_cirla_daily_calls`, standard 5), modul-log, dagligt cron-job og filteret
  `sgap_cirla_generate`; indstillingerne ligger under *Settings & keys → Cirla*.
- En kladde gemmes som `pending` i `sgap_ai_drafts` med `angle = cirla` — den køes eller publiceres aldrig.

### Målt
- 1 471 PASS / 0 FAIL (28 nye checks), fra → til → fra igen, `php -l` rent, fejlscan uden fund, zip
  byte-identisk.

## 7.70.1 — 2026-11-10

**OpenRouter-headeren bygges nu ét sted — den der blev testet, er den der bliver sendt.**

### Rettet
- `SGAP_AI::bearer()` renser tokenet (linjeskift og usynlige tegn inde i værdien fjernes) i stedet for
  kun `trim()`.
- `openrouter_headers()` / `openrouter_post()` er nu den eneste vej til `/chat/completions` for
  nøgletest, failover og generator.
- `stripped_by()` kører sitets eget `http_request_args`-filter på en kopi af requesten, så et plugin der
  fjerner headeren bliver navngivet i stedet for at sende ejeren til `openrouter.ai/keys`.

### Målt
- 11 nye smoke-checks ser på de argumenter plugin'et giver WordPress' HTTP-lag; med `bearer()` sat tilbage
  til `trim()` fejler 4 af dem. 1 443 PASS / 0 FAIL.

## 7.70.0 — 2026-11-10

**P8: agenterne læser sidens målte tal i stedet for en fast antagelse.**

### Tilføjet
- `SGAP_Coach`: et målt brief fra `sgap_events`, kladdebeholdningen og WordPress' egne tællinger går ind i
  prompten hos den daglige forfatter, kampagne-agenten, Studio-artiklen og Studio-SEO-redaktøren.
- Dagligt cron der omsætter tallene til højst 12 daterede regler med bevis (25+ klik, 14+ dage uden
  publicering, 20+ ventende kladder).
- Read-only ability `agent-brief` og et kort på dashboardet; `tools/agent_brief.py` måler det levende
  katalog og skriver `docs/agent-brief.md`, som `seo_agent.py` læser før den planlægger.

### Målt
- 44 nye coach-checks (1 383 → 1 427 PASS, efter rebase 1 432), 13 Python-tests, fejlscan uden fund.

## 7.69.1 — 2026-11-10

**Nøgletesten siger nu hvad der er galt med nøglen.**

### Rettet
- Formen tjekkes før der bruges en request: en OpenRouter-nøgle skal starte med `sk-or-v1-`; alt andet
  navngives med sin længde og en henvisning til `openrouter.ai/keys`.
- `SGAP_AI::api_key()` trimmer nøglen for generator, Studio, AI Chat og nøgletest, så et linjeskift fra en
  indsættelse ikke ødelægger Bearer-tokenet.

### Målt
- 5 nye smoke-checks (1 432 → 1 437 PASS).

## 7.69.0 — 2026-11-10

**P7: RSS-tilstanden kan måles — »kan kanalen sende?« og »har dens feed noget brugbart?«**

### Tilføjet
- `SGAP_RSS::health()` giver hver konfigureret kanal én tilstand: `ok`, `empty`, `stale` (ældre end 7
  dage, filter `sgap_feed_stale_days`), `invalid` (post uden titel/tekst/foto hvor Instagram og Pinterest
  kræver det) eller `off` (API-tilstand) — med antal poster, alder og feed-adresse.
- `health_summary()` tæller tilstandene og navngiver de kanaler der kræver handling; felterne
  `feeds`/`feeds_summary` ligger i abilityen `channel-health` og i `/sgap/v1/channel-health`.

### Målt
- 89 checks i `plugins/tests/feed-health.php`, ingen ny tabel, indstilling eller side.

## 7.68.0 — 2026-11-10

**P6: kampagne-agenten skriver kladder, men publicerer aldrig.**

### Tilføjet
- `SGAP_Campaign_Agent`: en publiceret side, et skrevet mål (8–160 tegn) og de konfigurerede kanaler giver
  ét udkast pr. kanal — altid `pending` i `sgap_ai_drafts`, også med Full Auto slået til. Agenten kalder
  hverken `SGAP_Queue` eller `SGAP_Publer`.
- To nye kolonner: `gate_report` bærer preflightens og kvalitetsgatens fund med kladden, og `campaign`
  følger kladden gennem `from_draft()` til `utm_campaign`.
- Abilityen `campaign-drafts` og et dashboardkort med formular.

### Målt
- 42 nye smoke-checks (1 316 → 1 358 PASS).

## 7.67.1 — 2026-11-10

**P1.24: OpenRouter som automatisk backup for AIMLAPI.**

### Tilføjet
- Backuppen har sin egen nøgle og sit eget model-felt, så en rotation af den ene ikke peger backuppen på
  den anden.
- Kun udbyderfejl starter den (transport, 401/403, 402, 429, 5xx); et 2xx-svar gør aldrig, heller ikke når
  det er tomt eller afkortet. 400/404/422 rapporteres uændret som vores egen fejl.
- `SGAP_AI::chat_completions()` er nu den eneste vej til `/chat/completions`, så generator, Content Studio
  og AI Chat deler samme failover; hver failover giver én række i fejlloggen og en sætning i chat-svaret.

### Målt
- 30 nye smoke-checks (1 286 → 1 316 PASS). Live er intet målt — der findes ingen OpenRouter-nøgle i
  sandkassen.

## 7.67.0 — 2026-11-09

**P1.23: OpenRouter som egen tekst-provider.**

### Tilføjet
- `openrouter` er et eget valg: adressen er fast (`SGAP_AI::OPENROUTER_URL`), og `base_url()` ignorerer det
  håndskrevne felt for den provider.
- `SGAP_KeyCheck::check_openrouter()` beviser nøglen med ét token mod den valgte model og skelner afvist
  nøgle (401/403), tom konto (402), ukendt model (404/400) og rate limit (429).
- Ingen standardmodel: kataloget læses fra `/models` og caches under `sgap_ai_catalog_openrouter`, som
  ryddes ved rotation.

### Målt
- 9 nye smoke-checks (1 277 → 1 286). Alle kald er mocket — ingen nøgle findes i sandkassen.

## 7.66.0 — 2026-11-09

**P5: forum og Reddit som trafikaccelerator.**

### Tilføjet
- Tråde som CPT med native kommentarer og `DiscussionForumPosting`; svar kræver login, nonce og honeypot,
  højst 3 tråde i timen, og alt fra ikke-moderatorer lander som `pending`.
- Reddit får kun link posts gennem en egen kø: 2 i døgnet, 90 minutters interval og 5 → 15 → 45 minutters
  backoff, med `utm_source=reddit` og `utm_campaign=<tavle>`; tråden fører videre til produkter gennem `/go/`.
- Radar der læser hot i op til fem subreddits og rangerer `score + 2 × kommentarer`. Modulet kommenterer
  aldrig selv.

### Målt
- 1 277 PASS / 0 FAIL, `php -l` rent på 67 filer, zip byte-identisk. Intet er kaldt mod Reddit endnu;
  klienten er testet gennem transport-seamet.

## 7.65.5 — 2026-11-09

**S5.8: backup og gendannelse øvet — kun versionsnummeret er ændret i plugin-filen.**

### Ændret
- `tools/wp_db_drill.py` dumper databasen med `mysqldump`, gendanner den under scratch-prefixet `sgtest_`
  og sammenligner rækkeantal pr. tabel: 269 af 269 tabeller identiske, RTO 6 s, intet tilbage.

## 7.65.4 — 2026-11-09

**Femten inline side-scripts er blevet syv filer og et localize-objekt.**

### Ændret
- Syv filer i `assets/pages/` (dashboard, studio, seo, chat, tools, niche, setup) enqueues af
  `SGAP_Assets::page_script()` med filens mtime som version; konfigurationen sendes med
  `wp_localize_script`, så nonces, chat-historik og værktøjskatalog stadig beregnes pr. request.
- `site-seo`-ens nowdoc blev sin egen fil, og `script()` er væk. Det ene `<script>` tilbage i `includes/`
  er JSON-LD, som er data.

### Målt
- JS-teksten er byte-identisk med det HEAD printede (kontrolleret for alle syv filer) og `node --check`-ren.

## 7.65.3 — 2026-11-09

**Dokumentations-patch: §8 pkt. 8 og changelog.**

### Ændret
- Kun versionsnummer og dokumentation; zip'en blev genopbygget fra den merged kilde, så den matcher træet.

## 7.65.2 — 2026-11-08

**Admin-UI: CSS og JS ud af `<head>` — 41,8 KB mindre pr. side.**

### Ændret
- Fire filer i `assets/` enqueues af `SGAP_Assets`: menu-assets overalt, side-assets kun på pluginets egne
  skærme (`is_plugin_screen()`), begge kun for `manage_options`. Versionen er filens mtime, så en
  CSS-rettelse ikke serveres fra cachen.
- UI: `:focus-visible` på alle kontroller, scrollbare tabeller under 782 px, ét-kolonne-grid og mindre
  padding på mobil, `prefers-reduced-motion` slukker animationer, og tal står i `tabular-nums`.

### Målt
- 36 nye smoke-checks i `plugins/tests/assets.php` (1 172 PASS / 0 FAIL).

## 7.65.1 — 2026-11-08

**Dokumentations-patch: roadmap-måling og tretten ejerpunkter navngivet.**

### Ændret
- Kun versionsnummer og dokumentation; ingen pris, produkt, gateway, zone, Complianz-indstilling eller
  kanal-token blev rørt.

## 7.65.0 — 2026-11-08

**Kø-siden viser sprog og kilde.**

### Tilføjet
- En `Language`-kolonne med sproget og original/translation samt en `Source`-kolonne, der linker en
  oversættelse til den kø-post den kom fra og viser dens titel; hver række har fået et id, så linket
  hopper direkte til originalen.
- Ét opslag med self-join på `source_id`; en slettet kilde viser sit id i stedet for et gæt, og titler
  escapes som alle andre felter.

### Målt
- 8 nye smoke-checks i `plugins/tests/queue-page.php` (1 128 → 1 136 PASS).
