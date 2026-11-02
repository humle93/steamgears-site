# Command Center (Android) — social pilot for steamgears.eu (2026-11-02)

**Status: M1 bygget og testet (API-klient + auth, 46 JVM-tests), resten planlagt.** Koden ligger i [`android/`](../../android/README.md), se [`command-center-m1-2026-11-02.md`](command-center-m1-2026-11-02.md). Projektet er registreret i [`projektstatus.json`](../../projektstatus.json) og `repos.json`, men repoet findes ikke endnu (`GITHUB_TOKEN` mangler), så koden bor her indtil videre.

## Hvad det er

En Android-app der styrer Steamgears' sociale autopilot fra telefonen: se om der sendes, godkend eller afvis AI-kladder, og se hvad der ligger i køen. Appen er en **tynd klient** — al logik bliver i WordPress-pluginnet `steamgears-autopilot`. Der kommer ingen Python-backend: `sgap/v1`-endpointene er backenden.

Bundlen [`Velocity_Command_Center_v2.1_Complete_Bundle.md`](Velocity_Command_Center_v2.1_Complete_Bundle.md) er **ikke** udgangspunktet. Den indeholder en trading-engine og en Python-backend, som ikke har noget med Steamgears at gøre. Kun Android-laget (Kotlin/Compose + CI-workflow) er genbrugeligt.

## Kontrakten der allerede findes

Alt nedenstående er registreret i `plugins/steamgears-autopilot/includes/core.php` (`SGAP_REST`, namespace `sgap/v1`). Alle kræver `manage_options`, altså et **application password** for en admin-bruger — ikke en cookie.

| Endpoint | Metode | Bruges til |
| --- | --- | --- |
| `/sgap/v1/snapshot` | GET | Én read-only call med status, kø, kladder, resultater, konverteringer, indstillinger (secrets som set/not set) og health. Polles af appens startskærm. |
| `/sgap/v1/drafts` | GET | AI-kladder med `promotes` (titel + URL) og `has_image`. |
| `/sgap/v1/drafts/{id}/status` | POST | `queue` (godkend), `approve` eller `reject`. |
| `/sgap/v1/queue` | POST | Læg et selvskrevet opslag i køen. |
| `/sgap/v1/queue/{id}/status` | POST | Ret status på et kø-element. |
| `/sgap/v1/stats` | GET | 30-dages tal pr. kanal. |

`/snapshot` returnerer bl.a. `status.sending_paused_because` og `status.can_send_now`, så appen kan svare på det eneste spørgsmål der betyder noget om morgenen: **sendes der, og hvis ikke hvorfor**.

## Skærme

1. **Status** — autopilot til/fra, full auto, publish mode (RSS/API), kanaler, dagens kapacitet, pause-årsag, sidste self-check, 30-dages tal pr. kanal.
2. **Kladder** — liste med kanal, titel, tekst og hvilken side den promoverer; Godkend / Afvis / Godkend + kø.
3. **Kø** — status, forsøg, fejl, tracking-link.

## Moduler og estimat

| Modul | Estimat | Grundlag |
| --- | --- | --- |
| M1 — API-klient og auth mod `sgap/v1` | 1,0 d | 6 endpoints, application-password Basic auth, fejlhåndtering |
| M2 — skærme: status, kladder, kø | 1,5 d | 3 skærme + 3 POST-handlinger |
| M3 — CI der bygger APK | 0,5 d | Gradle + GitHub Actions, signeret debug-build |
| **I alt** | **3,0 d** | Roadmap'ens 1–2 d dækkede kun at *læse* `/snapshot`; skærme og build kommer oveni |

## Ikke med i scope

- Egen Python-backend, database eller trading-engine (findes i bundlen, hører ikke til her).
- Direkte posting til Facebook/Instagram m.m. Pluginnet sender via Publer; appen må ikke gå uden om køen, dagskvoten og quiet hours.
- Push-notifikationer, offline-cache, iOS.

## Hvad der blokerer

1. `GITHUB_TOKEN` — repoet `humle93/steamgears-command-center` kan ikke oprettes af `sync_repos.py --apply` uden det.
2. Android-build — der er ingen Android SDK i denne sandbox, så APK'en skal bygges i CI (`.github/workflows/`), ikke her. Det skal siges højt, før nogen kalder appen "testet".

## Hvad M1 blev

Seks endpoints i en ren Kotlin/JVM-modul (`android/core`), så klienten kan testes uden
Android SDK: `snapshot`, `drafts`, `drafts/{id}/status`, `queue`, `queue/{id}/status` og
`stats`, med application password som Basic auth, tolerante serializers (MySQL giver tal
som strenge) og fejl som værdier med danske beskeder. Testfixtures genereres af den
rigtige PHP, ikke i hånden. Detaljer: [`command-center-m1-2026-11-02.md`](command-center-m1-2026-11-02.md).

## Næste skridt

1. Opret repoet (token) og læg `AGENTS.md`-kontrakten ind via `sync_repos.py`.
2. M2: `app`-modul med skærmene status, kladder og kø oven på `SgapApi`.
3. M3: CI der bygger APK — `command-center-ci.yml` kører allerede `:core:test`.
