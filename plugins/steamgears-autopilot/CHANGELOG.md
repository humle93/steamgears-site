# Patch notes — Steamgears Autopilot

Nyeste version øverst. Hver note siger hvad der blev **tilføjet, ændret eller rettet**, hvordan det er
målt, og hvilket versionsnummer den hører til. Versionsnummeret i den øverste overskrift skal matche
`SGAP_VERSION`, plugin-headerens `Version:` og navnet på zip'en i `plugins/` — det tjekkes af
`python3 tools/patch_notes.py --check`.

Noterne er skrevet ud fra commit-historikken og de dokumenter der hører til hver version. Versioner før
7.65.0 er ikke backfillet; deres historik står i git og i README's afsnit pr. funktion.

<!-- patch-notes: baseline 7.65.0 -->

## 7.83.1 — 2026-11-12

**Rettet: AI-redaktøren og den gratis reserve sendte for mange modeller til OpenRouter.**

### Rettet
- OpenRouter afviser mere end 3 modeller i `models`. Målt live med 7.83.0: »'models' array must have 3
  items or fewer«.
  - `SGAP_Editor::MODELS` er nu `nemotron-3-ultra:free`, `nemotron-3.5-super-vl:free` og
    `openrouter/free`.
  - Både `SGAP_Editor::models()` og `SGAP_AI::free_models()` skærer en filtreret liste ned til 3.
- Fejlen var sikker. Uden et svar holdt redaktøren alle opslag tilbage som `pending`, og intet kom i
  køen.
- 1 nyt smoke-check (1 612 → 1 613 PASS, 0 FAIL).

## 7.83.0 — 2026-11-12

**En AI-redaktør læser hvert socialt opslag, før det må komme i køen. Svage opslag afvises med grunden, og når ingen redaktør svarer, venter opslagene på dig, også i Full auto.**

### Tilføjet
- `includes/editor.php` (`SGAP_Editor`). Hver side får ét gratis kald til OpenRouter (kun `:free`-modeller
  og `openrouter/free`), der giver sidens opslag en score fra 0 til 10 ud fra selve siden.
  - Der trækkes hårdt for priser, rabatter, fragtløfter og påstande, der ikke står på siden, og for
    AI-klichéer, ens indledninger på tværs af kanaler, hashtag-fyld og forkert tone.
- `write_for()` bruger dommen:
  - **≥ 7:** kladden kan sættes i kø som før.
  - **< 7:** kladden gemmes som `rejected` med begrundelsen i *Checks*.
  - **Ingen svar eller ikke-læsbar JSON:** kladden bliver `pending` og sættes **ikke** i kø
    automatisk.
- Kørselsrapporten tæller `rejected` og `held`, og selvtjekket nævner dem.
- Redaktøren kører kun med en OpenRouter-nøgle (hoved- eller reservenøgle). Anthropic-opsætninger
  er uændrede.
  - Filtrene `sgap_editor_min_score` og `sgap_editor_models` ændrer grænse og modeller.
  - En tom modelliste slår redaktøren fra.
- 12 nye smoke-checks (1 600 → 1 612 PASS, 0 FAIL).

### Bemærk
- Billeder til artikler laves og kvalitetssikres nu af Command Center (v7.34.0) og kommer med
  artiklen som udvalgt billede. Pluginnets egne sociale opslag bruger sidens udvalgte billede som
  før.

## 7.82.0 — 2026-11-12

**Command Center fortæller nu autopiloten, hvilke sider der er tæt på toppen af Google, og for hvilket søgeord. De sider får sociale opslag først, og søgeordet bruges i opslaget.**

### Tilføjet
- `includes/priorities.php` (`SGAP_Priorities`) og ruten `POST/GET /sgap/v1/priorities` (kræver
  `manage_options`, som resten af API'et). Command Center sender op til 30 sider: `url`, `keyword`,
  `position` og `reason`.
  - Kun publicerede indlæg, sider og produkter gemmes. Ukendte sider, kladder og dubletter afvises
    med en grund i svaret.
  - Listen gælder i 14 dage.
- `SGAP_Generator::pick()` tager de prioriterede sider først, i Command Centers rækkefølge. Derefter
  følger den gamle regel: aldrig promoveret først, ellers længst siden.
  - En prioriteret side kan komme igen efter 7 dage. Den normale pause er `repost_cooldown_days`.
  - Dagens antal, køen, kanalerne, lofterne og kvalitetsgaten er uændrede.
- `source()` og `prompt()` tager søgeordet med. AI'en bruger det naturligt én gang og som hashtag,
  hvis det passer, uden at proppe det ind.
- `/snapshot` har `cc_priorities` (`received_at`, `active`, `due`), så Command Center kan se, hvad der
  kom frem.
- 13 nye smoke-checks (1 587 → 1 600 PASS, 0 FAIL).

## 7.81.1 — 2026-11-12

**Den gratis reserve prøver nu Nemotron Ultra først.**

### Ændret
- `SGAP_AI::FREE_MODELS` er nu `nvidia/nemotron-3-ultra-550b-a55b:free` → `google/gemma-4-31b-it:free` →
  `openrouter/free` (ejerens valg 12/11). Nemotron Ultra (550B) skrev det bedste gratis svar i testen
  fra sitet, mens Gemma ikke svarede, og `openrouter/free` valgte en model på 2,6B. Er Nemotron optaget,
  går OpenRouter selv videre ned ad listen.
- Smoke-checket for rækkefølgen er rettet (1 587 PASS, 0 FAIL).

## 7.81.0 — 2026-11-12

**Løber OpenRouter-kreditten tør, skriver autopiloten videre med en gratis model i stedet for at stå stille.**

### Tilføjet
- `SGAP_AI::free_retry()`: afviser OpenRouter den betalte model for manglende kredit (402, eller
  »requires more credits« / »can only afford«), prøver genereringen én gang til på gratis modeller med
  OpenRouters `models`-liste: `google/gemma-4-31b-it:free` og derefter `openrouter/free`, som er
  OpenRouters egen router over de gratis modeller. Målt fra steamgears.eu 12/11, mens den betalte model
  blev afvist (»can only afford 405 tokens«): `openrouter/free` svarede med `cost: 0`, og listen faldt
  videre til `nvidia/nemotron-3-ultra-550b-a55b:free` med brugbar tekst.
- Kvalitetsgaten bedømmer de gratis svar ligesom alle andre.
- Kører kun ved kreditfejl hos OpenRouter. 401, 404 og andre udbydere er uændrede. Nøgletestet og
  Content Studio er ikke berørt.
- Filteret `sgap_free_models` ændrer listen, og en tom liste slår reserven fra.
- Kørselsrapporten tæller `free` og `free_model`. Selvtjekket melder, når sider blev skrevet af den gratis
  model, så skiftet ikke er skjult.
- Fejler de gratis modeller også (fx dagsgrænsen for gratis kald), står begge grunde i fejlen.
- 9 nye smoke-checks (1 578 → 1 587 PASS, 0 FAIL).

## 7.80.0 — 2026-11-12

**Opslag til slukkede kanaler venter i køen i stedet for at blive "udgivet" ud i ingenting, AI-kaldet beder kun om de tokens det skal bruge, og selvtjekket siger til, når den seneste AI-kørsel ikke skrev noget.**

### Rettet
- `SGAP_Queue::claim()` frigav alle køens opslag, også til kanaler der er slukket under
  *Settings → Publer → Channels*. I RSS-tilstand blev de markeret `published` i et feed som Publer ikke
  læser. Målt på steamgears.eu 12/11: 22 opslag stod som udgivet på 7 slukkede kanaler. Nu frigives kun
  opslag til tændte kanaler. Påmindelser (`remind = 1`) er mail og går stadig ud. Uden nogen kanal i
  indstillingen filtreres der ikke, så intet fryses ved en fejl, og et REST-træk med en bestemt kanal
  er uændret.

### Ændret
- `SGAP_AI::token_budget()` sætter `max_tokens` til 1 500 + 700 pr. kanal (mindst 2 500, højst 8 000).
  Før var den fast 8 000 (chat completions) og 16 000 (Anthropic). OpenRouter reserverer kredit for hele
  `max_tokens` på forhånd, så en lav saldo afviste kald, der kun brugte en tiendedel. Med 3 kanaler er
  loftet nu 3 600.

### Tilføjet
- Selvtjekket (`SGAP_Auto::output_problems()`, en del af `health()`) melder to nye problemer:
  - Når den seneste AI-kørsel skrev 0 kladder og havde fejl, vises tidspunktet og den første fejl,
    fx manglende kredit. Før så man det først, når køen løb tør dage senere.
  - Når der står opslag i kø til slukkede kanaler, vises antallet pr. kanal (`SGAP_Queue::parked()`).
- 10 nye smoke-checks i `plugins/tests/smoke.php` (1 568 → 1 578 PASS, 0 FAIL).

## 7.79.0 — 2026-11-12

**Cirla kan nu gives til en rolle der kun har Cirla — uden at give rollen hele sitet — og en afvist handling svarer med en fejlkode i stedet for en exception.**

### Rettet
- `SGAP_Cirla::map_cap()` sendte `sgap_cirla_run` videre til `manage_options` uanset hvad, så en
  begrænset rolle (fx redaktør med kun `read` + `sgap_cirla_run`) blev afvist med 403, selv om rollen
  havde capabilityen. Målt på steamgears.eu 11/11: `current_user_can('sgap_cirla_run')` var `false` for
  en rolle med kun de to rettigheder, mens administrator var `true`. Nu vinder en direkte tildeling
  (`['exist']`), og uden den går capabilityen stadig gennem `manage_options`, så administratorer er
  uændrede. Det er forudsætningen for C7 i `projektstatus.json` (Cirla-bruger med begrænset rolle).
- `SGAP_Cirla::seo_check()` og `social_suggestions()` var deklareret `: array`, men returnerer
  `WP_Error` når modulet er slukket, rettigheden mangler eller det daglige loft er brugt. Målt på
  steamgears.eu 11/11 gav et social-kald med brugt budget **»Return value must be of type array,
  WP_Error returned«** i stedet for 429. Begge er nu `array|WP_Error`, så 403/409/429 når klienten
  som tilsigtet.
- 6 nye smoke-checks i `plugins/tests/cirla.php` (1 550 → 1 556 PASS, 0 FAIL).

## 7.76.1 — 2026-11-12

**REST-datoer, falske boolske felter og oprydningen efter pluginnet er rettet.**

### Rettet
- `scheduled_at` er site-wall-clock, ikke et tidspunkt: en lokal `2026-12-25 10:00` blev sendt gennem
  `strtotime()`/`wp_date()` og flyttet med sitets UTC-offset; `2026-02-31` blev normaliseret til en anden dag.
  `SGAP_Queue::schedule()` validerer nu dato og tid og bevarer værdien; en umulig dato giver **400** i REST i
  stedet for at falde tilbage til “nu”.
- `force=false` og `remind=false` blev læst som sande, fordi `(bool) "false"` er true. `SGAP_REST::flag()`
  accepterer kun `true`, `1`, `yes` og `on`; et form-request kan ikke længere omgå quality-gaten eller sende en
  reminder som en almindelig post.
- `POST /drafts/<id>/status` svarede **200** på et ukendt id. Den slår id op først og svarer **404**.
- `POST /queue/<id>/status` accepterer nu `status=queued`; det genbruger `SGAP_Queue::retry()` og nulstiller
  attempts, error, external id og reminder. Admin-knappen bruger samme metode.
- Kvalitetsgaten behandlede **enhver** query-streng som et arkiv. Nu er kun rigtige arkiv-parametre (`s`,
  `paged`, `page`, `orderby`, …) arkiver; `?utm_source=…` og `?p=123` går gennem linkkontrollen.
- Uninstall sletter nu alle `_sgap_*`-postmeta via `LIKE` og rydder også `sgap_evergreen`, `sgap_coach_learn`,
  `sgap_rescue` og `sgap_cirla_daily`. `SGAP_Install::TABLES` og `/snapshot` kender `sgap_publish_log` og
  `sgap_reddit_posts`.

### Målt
- 12 nye smoke-checks (1550 → **1562 PASS**, 0 FAIL); `php -l` på de ændrede filer. Ingen live- eller
  integrationstest i denne kørsel — releasen kræver ejerens *Plugins → Update*.

## 7.76.0 — 2026-11-11

**OpenRouter er standard, billeder kan laves via OpenRouter, og »Log ind i Command Center« logger ind med ét klik (P10).**

### Tilføjet
- `includes/openrouter.php`: en advarsel på pluginnets sider, mens AIMLAPI er valgt, og knappen
  **Switch to OpenRouter now**, når OpenRouter-reserven har nøgle og model. Den gør OpenRouter til
  hovedudbyder med præcis den nøgle og model; AIMLAPI-nøglen røres ikke.
- Indstillingen **OpenRouter image model** (*Settings → AI content*). Med en OpenRouter-nøgle (hoved eller
  reserve) laves billeder via OpenRouter (`chat/completions`, `modalities: image`, data-URL); uden den bruges
  AIMLAPI som før. Ingen standardmodel, fordi OpenRouters katalog skifter.
- `POST /sgap/v1/cc-link` (kræver `manage_options`): Command Center 7.27+ lægger sin adresse og en fælles
  nøgle her (option `sgap_cc_link`, ikke autoload). Knappen **Log ind i Command Center** laver en
  HMAC-SHA256-billet (60 s, 128-bit `jti`) og sender browseren til `{Command Center}/cc-sso`.

### Ændret
- OpenRouter er standardudbyder, når intet er gemt, og står først og anbefalet i guiden, indstillingerne og
  nøglesiden. AIMLAPI er markeret *being phased out*; et gemt AIMLAPI-valg virker stadig.
- Uden forbindelse fra Command Center hedder knappen **Open Command Center** og åbner adressen fra
  indstillingerne som før.

### Rettet
- Command Centers gamle engangslink sendte til `POST /sgap/v1/login-link`, en rute pluginnet aldrig har haft,
  så knappen i Command Center kunne ikke virke. Den nye vej erstatter den.

### Målt
- `plugins/tests/cc-login.php`: 45 nye checks (billet, signatur, udløb, rettigheder, ruten, billed-backend og
  flytte-knappen). Nøglen står hverken i status, snapshot, knappens HTML eller billetten.

## 7.75.0 — 2026-11-11

**Log-rækkerne på Cirla-siden siger nu hvad målet hedder, ikke kun hvor det ligger.**

### Tilføjet
- `SGAP_Cirla::pending_draft($id)` svarer på begge spørgsmål i én query: findes kladden stadig under
  AI Drafts, og hvad hedder den. `draft_exists()` bygger nu på den, så der er **én** eksistens-kontrol.
- En *draft*-række læses som **»Draft #7 · Brass goggles«** med link til den konkrete kladde
  (`#sgap-draft-7`); en *seo*- eller *social*-række læses som **»SEO check · sidens titel«** /
  **»Suggestions · sidens titel«** med link til sidens editor.
- `SGAP_Cirla_Admin::row_target()` giver url og titel samlet, så de to altid følges ad;
  `row_url()` er bevaret som tynd wrapper.

### Sikkerhed mod døde links
- Titel og link kommer fra samme kontrol: en kladde der er godkendt, afvist eller slettet, en slettet
  side, en gammel række uden id og en række uden naturligt mål giver **hverken** link **eller** titel —
  rækken står som ren tekst med sit oprindelige resultat.
- Titel og resultat escapes ved rendering, så en kladdetitel med markup ikke bliver HTML.
- Filteret fra 7.73.0 og begge tomme tilstande er uændrede.

### Målt
- Smoke: **1 505 PASS, 0 FAIL** (1 504 → 1 505; link- og titel-checks omskrevet, én ny).
  `php -l` rent, fejlscan uden fund.

## 7.74.0 — 2026-11-11

**Log-rækkerne på Cirla-siden peger nu på det de handler om.**

### Tilføjet
- `SGAP_Cirla::log($action, $result, $meta)` gemmer id'et på den kladde eller side rækken handler om,
  så loggen kan bruges som indgang og ikke kun som historik.
- En *draft*-række linker til kladden på **AI Drafts** (`admin.php?page=sgap-ai#sgap-draft-<id>`); hver
  kladde har fået et anker i `includes/core.php`.
- En *seo*- eller *social*-række linker til sidens editor.
- `SGAP_Cirla::draft_exists()` er nu offentlig og dokumenteret; den svarer kun ja for kladder der stadig
  venter under AI Drafts.

### Sikkerhed mod døde links
- Der linkes kun når målet stadig findes: en kladde der er godkendt, afvist eller slettet, og en side der
  er slettet, giver **ingen** link — rækken står som ren tekst. Gamle rækker uden id og rækker uden
  naturligt mål (status, cron, toggle) er også ren tekst.
- Filteret fra 7.73.0 og begge tomme tilstande er uændrede.

### Målt
- 8 nye smoke-checks (1 496 → 1 504 PASS, 0 FAIL), `php -l` rent, fejlscan uden fund.

## 7.73.0 — 2026-11-11

**Loggen på Cirla-siden kan ses én handling ad gangen.**

### Tilføjet
- `SGAP_Cirla::entries($limit, $action)` kan nu læse én handling; uden filter er svaret som før.
- Faner øverst i log-kortet — *All*, *Read the status*, *Write one draft*, *Run the SEO check*,
  *Suggest social posts* — med antal rækker i hver, så det er synligt hvor loggen har noget at vise.
  Den valgte fane er tekst med `aria-current="page"`, de andre er links (`?page=sgap-cirla&cirla_log=draft`).
- Ukendte eller opdigtede filtre falder tilbage til *All* i stedet for at give en tom side.

### Bevaret
- Den oprindelige tomme tilstand, når loggen slet ikke har rækker.
- En ny tom tilstand for et filter uden rækker: »No *Write one draft* rows in the last 20« med et link
  tilbage til alle rækker.

### Målt
- 6 nye smoke-checks (1 490 → 1 496 PASS, 0 FAIL), `php -l` rent, fejlscan uden fund.

## 7.72.0 — 2026-11-11

**Cirla har fået sin egen side i Steamgears-menuen — den kan bruges uden MCP.**

### Tilføjet
- `modules/cirla/class-cirla-admin.php`: en Cirla-side under **Create** med modulets tilstand (til/fra, næste
  daglige job, AI-budget brugt i dag, kladder under AI Drafts), de fire handlinger som knapper og modulets log.
  Siden indlæses kun sammen med modulet, så et slukket modul ikke har en side.
- Knapperne kalder præcis de samme funktioner som abilities (`status`, `draft`, `seo_check`,
  `social_suggestions`) via `admin-post.php` med nonce og `manage_options`, viser resultatet som en tabel og
  redirecter tilbage — en genindlæsning kører ikke handlingen igen.
- Emne, kanal, side-id og antal forslag valideres og saniteres i `SGAP_Cirla_Admin::input()`, så siden ikke
  kan sende noget abilities ikke også ville acceptere.

### Målt
- 15 nye smoke-checks (1 475 → 1 490 PASS, 0 FAIL), `php -l` rent, fejlscan uden fund, zip byte-identisk.

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
