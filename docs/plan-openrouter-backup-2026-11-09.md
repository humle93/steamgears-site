# OpenRouter som automatisk backup for AIMLAPI — implementeringsplan

**Mål:** når AIMLAPI ikke kan svare (tom konto, død nøgle, rate limit, nedbrud), skal autopiloten,
Content Studio og AI Chat fortsætte på OpenRouter i stedet for at fejle — med AIMLAPI som valgt udbyder hele tiden.

**Status:** plan, ikke bygget. Kræver ejerens godkendelse før kode (AGENTS.md §7.1).

**Forudsætning målt 9/11 2026:** `main` står på **7.67.0**, hvor **P1.23** allerede gjorde OpenRouter til et
eget valg i *AI provider* (`SGAP_AI::OPENROUTER_URL`, `provider_name()`, `check_openrouter()`, katalog under
`sgap_ai_catalog_openrouter`). Det, der stadig mangler, er **failover**: i dag vælger ejeren én udbyder, og en
tom AIMLAPI-konto standser alt, indtil nogen skifter udbyder i hånden. Denne plan bygger oven på 7.67.0 og
ændrer ikke P1.23's beslutninger (fast adresse, intet gættet standardmodel).

---

## 1. Afgrænsning

Med i planen:

- failover for de tre flader der bruger `chat/completions`: autopilot-generatoren (`generate`/`openai_compatible`),
  Content Studio (`json_call`) og AI Chat (`chat_call` → `loop_chat_completions`)
- en **separat** OpenRouter-nøgle til backuppen (fordi `ai_api_key` er bundet til den valgte udbyder),
  et backup-model-felt med OpenRouter's eget katalog, og en nøgletest for netop det par
- besked til ejeren når backuppen blev brugt (log + note i chatten)

Ude af planen:

- **Anthropic-stien** (`anthropic()`, `loop_anthropic`, Anthropic-grenen af `json_call`). Den har sit eget
  wire-format og sit eget server-side refusal-fallback; failover dertil kræver en oversættelse af requesten og
  er en separat opgave. Backuppen gælder derfor kun når den valgte udbyder taler `chat/completions`, og
  `provider() === 'openrouter'` slår den fra (så en backup ikke kalder den samme tjeneste som primæren).
- billeder (`SGAP_Images` bruger AIMLAPI's `/images/generations`; OpenRouter er ikke billedudbyder her)
- automatisk omkostnings- eller kvalitetsrouting, `openrouter/auto`, prisoptimering

## 2. Låste beslutninger

| Spørgsmål | Beslutning | Grund |
| --- | --- | --- |
| Hvor er backuppen i UI'et? | Eget **Keys-kort** ("OpenRouter (backup)") + eget model-felt i samme kort. Ikke et valg i `ai_provider`, ikke et felt under *AI & images* | P1.23 gjorde OpenRouter til et *valg*; backuppen er noget andet og må ikke kunne forveksles med det |
| Nøgle | Ny secret `openrouter_api_key` (ikke autoloadet, aldrig gengivet, `SGAP_OPENROUTER_API_KEY` virker) | `ai_api_key` er "én nøgle ad gangen" for den valgte udbyder; en backup skal ikke overskrive den |
| Model | Ny tekst-indstilling `openrouter_model`, valgt fra OpenRouter's **live katalog** i et dropdown | 7.67.0's beslutning: intet gættet standard-slug. Er feltet tomt, er backuppen slået fra med en navngiven grund |
| Katalog-cache | `sgap_ai_catalog_openrouter_backup` (ikke det eksisterende `sgap_ai_catalog_openrouter`) | Ellers ville primær- og backup-katalog blande sig, når udbyderen skifter |
| Endpoint | `SGAP_AI::OPENROUTER_URL . '/chat/completions'` med Bearer | Genbruger P1.23's faste adresse — ingen ny konstant |
| Body til backuppen | Samme body med `model` = `openrouter_model`, og `reasoning_effort` **fjernet** | `reasoning_effort` sættes kun for AIMLAPI (Claude tænker først); den er ikke en OpenRouter-parameter |
| Begge fejler | Fejlen bærer **begge** grunde: primærens først, derefter "Backuppen (OpenRouter) svarede heller ikke: …" | Ejeren skal se hvad der faktisk gik galt |
| Spor | `SGAP_Diagnostics::log('ai-fallback', …)` + én sætning i chat-svaret | Ringbufferen findes og vises på Fejlsøgnings-siden |
| Nøgletest | `check_openrouter()` får en valgfri model-parameter, så den kan teste **backup**-nøglen mod **backup**-modellen uden at røre `SGAP_AI::model()` | Den eksisterende test læser primærens model; en backup-test der gør det, ville teste det forkerte par |

## 3. Trigger-matrix (den regel der ikke må gættes)

| Svar fra den primære | Handling |
| --- | --- |
| 2xx med tekst/JSON | færdig — backuppen kaldes aldrig |
| 2xx men tomt svar, afkortet (`finish_reason=length`), refusal eller ubrugelig JSON | fejl til ejeren — **ingen** backup (svaret kom; et nyt kald ville koste dobbelt) |
| Modelnavn afvist (eksisterende `model_fallback`, AIMLAPI/`openai_compatible`) | ét genforsøg på udbyderens standardmodel; først hvis **det** også fejler på transport/HTTP, bruges backuppen |
| Transportfejl (`WP_Error` fra `wp_remote_post`) | backup |
| 401 / 403 (nøgle død, tilbagekaldt, mangler adgang) | backup |
| 402 (ingen kredit) | backup |
| 429 (rate limit) | backup |
| 5xx (500, 502, 503, 504) | backup |
| 400 / 404 / 422 (dårlig request, ukendt rute) | **ingen** backup — fejlen er i vores kald, ikke i udbyderen |
| `openrouter_api_key` eller `openrouter_model` mangler | ét forsøg, den oprindelige fejl returneres uændret |
| `provider() === 'openrouter'` | ingen backup (primæren *er* OpenRouter) |

## 4. Filer

| Fil | Ændring |
| --- | --- |
| `includes/generator.php` | `fallback_key()`, `fallback_model()`, `fallback_ready()`, `should_fallback(WP_Error)`, `last_fallback()`; `catalog()` får en valgfri udbyder (og en cache-nøgle der kan være `openrouter_backup`); ny `chat_completions($key, $body, $timeout, $who)` der ejer hele request-vejen (model-genforsøg + backup); `chat_call()`, `generate()`/`openai_compatible()` og `json_call()` kalder den |
| `includes/core.php` | `SECRET_KEYS` += `openrouter_api_key`; `TEXT_KEYS` += `openrouter_model`; `SECTIONS['AI content']` += `openrouter_model`; `HELP` for begge; `sanitize_model`-søskend for backup-modellen der validerer mod OpenRouter-kataloget |
| `includes/setup.php` | `LABELS` for begge; `TABS['keys']` += `openrouter_api_key`; backup-model-feltet renderes i Keys-kortet (ikke på *AI & images*) |
| `includes/keys.php` | kort `openrouter_backup` (optional, `test => 'openrouter_backup'`, link til openrouter.ai/keys), `CACHE_MAP['openrouter_api_key'] = ['sgap_ai_catalog_openrouter_backup']` |
| `includes/keycheck.php` | `WATCH` += `openrouter_api_key` og `openrouter_model`; `check_openrouter(string $key, string $model = '')`; `check_fallback()`; `run()`/`notices()` får nøglen `openrouter_backup` |
| `includes/chat.php` | noten efter svaret dækker både "modellen blev rettet" og "backuppen svarede" |
| `plugins/tests/smoke.php` | ny sektion med stubbet HTTP (§7) |
| `steamgears-autopilot.php`, `README.md`, `ROADMAP.md`, `plugins/*.zip` | version **7.67.1**, changelog-række, ny zip, gammel zip slettet |
| `projektstatus.json` | nyt modul `p1fallback` 0,35 af 0,35 d (P1.23 er optaget af 7.67.0 og røres ikke) |

## 5. Opgaver

### Opgave 1 — backup-nøgle og backup-model (0,08 d)

- [ ] `SECRET_KEYS`/`TEXT_KEYS`/`SECTIONS`/`HELP`, `LABELS`, Keys-kort med model-dropdown.
- [ ] Dropdownet læser **OpenRouter's** katalog (ny `catalog($fresh, 'openrouter')` med cache-nøgle
      `sgap_ai_catalog_openrouter_backup`), ikke den valgte udbyders; uden katalog bliver feltet fritekst med
      en advarsel, som `model_field()` allerede gør.
- [ ] Test: nøglen er ikke autoloadet; tomt felt beholder værdien; værdien står aldrig i HTML; kortet dækker
      nøglen præcis én gang mod `SECRET_KEYS`; en gemt nøgle uden model siger "backup slået fra: vælg model".

### Opgave 2 — nøgletest for backuppen (0,03 d)

- [ ] `check_openrouter($key, $model = '')` bruger `$model !== '' ? $model : SGAP_AI::model()`; eksisterende
      kaldssite er uændret. `check_fallback()` returnerer `ok => null` når intet er gemt, ellers testen af
      backup-parret.
- [ ] Test med stubbet HTTP: ét token til `OPENROUTER_URL/chat/completions` med backup-nøgle og backup-model;
      200/401/402/404 mappes som i dag; resultatet lander i `sgap_key_status['openrouter_backup']`.

### Opgave 3 — den fælles request-vej med backup (0,08 d)

- [ ] `chat_completions()` sender til `base_url()`, laver model-genforsøget og kalder backuppen pr. §3.
      Ved succes sætter den `last_fallback()`; ved dobbeltfejl returneres den primære fejl med backup-grunden.
- [ ] Backup-headere: Bearer, `Content-Type`, `HTTP-Referer: home_url('/')`, `X-OpenRouter-Title: Steamgears Autopilot`.
- [ ] Test: 402 → to kald; det andet går til `https://openrouter.ai/api/v1/chat/completions` med Bearer,
      `model = openrouter_model`, uden `reasoning_effort`, og med `messages`/`tools`/`response_format` identisk
      med det primære kald.

### Opgave 4 — de tre flader på samme vej (0,05 d)

- [ ] `generate()`/`openai_compatible()`, `json_call()` og `chat_call()` kalder `chat_completions()`; ingen
      `wp_remote_post` til `chat/completions` tilbage uden for den ene metode.
- [ ] Test pr. flade: primær 500 → backup 200 giver et rigtigt svar (poster parset, JSON parset, chat-loopet
      kører sin værktøjsrunde). Primær 200 → **nul** kald til OpenRouter.

### Opgave 5 — ærlig besked (0,02 d)

- [ ] `SGAP_Diagnostics::log('ai-fallback', …)` med primærens kode og den brugte backupmodel — ingen nøgle, ingen body.
- [ ] Chat-noten: at svaret kom fra backuppen, fordi den primære udbyder fejlede, og med hvilken fejl.
- [ ] Test: log-optionen får én række; noten indeholder ikke nøglen; noten udebliver når primæren svarede.

### Opgave 6 — release og regnskab (0,02 d)

- [ ] Version 7.67.1 i header og `SGAP_VERSION`; zip genopbygget, gammel zip slettet; `diff -r` mod kilden tom.
- [ ] README (nøglevejledning + hvad der sker når AIMLAPI svigter) og `ROADMAP.md` (P1.24-række + changelog).
- [ ] `projektstatus.json`: modul `p1fallback` 0,35/0,35 d, `verified: true`; `python3 projektstatus.py --update`.

## 6. Verifikation

```bash
./.tools/php -l plugins/steamgears-autopilot/includes/generator.php   # og de øvrige rørte filer
./.tools/php plugins/tests/smoke.php                                  # hele suiten, 0 FAIL
python3 tools/fejlscan.py                                             # version/zip/dokumentindeks
bash tools/natlige-vaern.sh                                           # alle værn
```

Live (kræver ejeren): OpenRouter-nøglen lægges ind under **Settings → Keys → OpenRouter (backup)**, modellen
vælges fra listen, og "Test connections" skal svare grønt. Selve failover kan først måles live ved en rigtig
AIMLAPI-fejl — eller ved midlertidigt at gøre AIMLAPI-nøglen ugyldig, hvilket er en ejerhandling, ikke noget
agenten gør på det levende site.

**Ikke dækket:** at den valgte backupmodel skriver lige så gode captions som Claude. Det er en kvalitetsmåling
der kræver en rigtig nøgle og en rigtig generering, og den skal stå som forbehold — ikke som påstand.

## 7. Smoke-checks (nye)

1. backuppen er klar når både nøgle og model er gemt, og ikke ellers (to checks)
2. primær succes kalder aldrig OpenRouter
3. 402 → backup med Bearer, backup-modellen og identisk body minus `reasoning_effort`
4. 401/429/500/transportfejl → backup (fire checks)
5. 400 med modelfejl → model-genforsøg, ingen backup
6. tomt 200-svar → ingen backup
7. begge fejler → fejlen navngiver begge udbydere og har den primære kode
8. `last_fallback()` sættes kun når backuppen svarede
9. `provider() === 'openrouter'` slår backuppen fra (ingen kald til sig selv)
10. nøgletesten bruger backup-nøgle og backup-model, ikke primærens
11. log-rækken og chat-noten indeholder hverken nøgle eller request-body

## 8. Regnskab

| Modul | Før | Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- |
| P1.24 OpenRouter-failover | — | 0,35 d | +0,35 | 0,08 indstillinger/katalog + 0,03 nøgletest + 0,08 request-vej + 0,05 flader + 0,02 besked + 0,02 release |

`plugin`-projektet går fra 31,25 af 31,25 d til 31,60 af 31,60 d, når arbejdet er færdigt og testet:
procenten bliver 100 % hele vejen, fordi modulet bogføres med `done == total` i samme pas.

## 9. Risici

- **Kvalitet på den valgte backupmodel.** Gratis modeller på OpenRouter er svagere end Claude, og en model der
  afviser `response_format` svarer 400. Modvirkning: modellen vælges fra den live liste (7.67.0's princip), og
  forbeholdet skrives i README frem for at love paritet.
- **Dobbelt regning.** Kun udbyderfejl udløser backuppen; et 2xx-svar gør aldrig.
- **Skjult failover.** En backup der tier, skjuler at den primære er nede. Derfor log + note + nøgletest på
  samme skærm som nøglen.
- **To nøgler, to formål.** `ai_api_key` er den valgte udbyders nøgle; `openrouter_api_key` bruges kun af
  backuppen. Kortet skal sige det, så en rotation ikke forveksles med et udbyder-skift.
- **`reasoning_effort`.** Sendes den videre, kan en model afvise hele requesten; den fjernes bevidst (testet).
