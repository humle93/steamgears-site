# Command Center M1 — API-klient og application-password-auth (2026-11-02)

**Status: bygget og testet på JVM, 46 tests grønne, ingen Android SDK nødvendig.** Koden
ligger i [`android/`](../../android/README.md), indtil repoet `steamgears-command-center`
kan oprettes. Intet er kørt mod steamgears.eu — det kræver WP-credentials.

## Hvad M1 er

Appen er en **tynd klient**: al logik bliver i pluginnet, og telefonen kalder de samme
`sgap/v1`-endpoints som dashboardet. M1 er det lag der kan gå galt uden at nogen ser
det — adresse, login, parsing og fejl — og derfor er det bygget og testet før nogen skærm.

| Endpoint | Metode | I klienten |
| --- | --- | --- |
| `/wp-json/sgap/v1/snapshot` | GET | `snapshot(days, limit)` |
| `/wp-json/sgap/v1/drafts` | GET | `drafts(status, limit)` |
| `/wp-json/sgap/v1/drafts/{id}/status` | POST | `setDraftStatus(id, status)` |
| `/wp-json/sgap/v1/queue` | POST | `enqueue(NewQueueItem)` |
| `/wp-json/sgap/v1/queue/{id}/status` | POST | `setQueueStatus(id, status, error)` |
| `/wp-json/sgap/v1/stats` | GET | `stats(days)` |

## Fire beslutninger der former koden

**1. `core` er ren Kotlin/JVM, ikke et Android-modul.** Der er ingen Android SDK i
denne sandbox, og et Android-modul ville gøre testene afhængige af en emulator eller
en SDK-installation. `core` har ingen Android-afhængigheder, så `./gradlew :core:test`
kører på en hvilken som helst JVM — også i CI. Først `app`-modulet (M2, Compose) bliver
Android-specifikt.

**2. Fixtures genereres af den rigtige PHP.** `android/core/src/test/fixtures/generate-fixtures.php`
stubb'er WordPress-funktionerne og lader `$wpdb` svare ud fra forespørgslen, så
`SGAP_REST::snapshot()`, `list_drafts()` og `stats()` kører uændret. Det afslørede noget
en håndskrevet fixture ville have skjult: **WordPress giver tal som strenge**. `id`,
`published`, `clicks`, `count` og `value` kommer som `"12"`, `"42"`, `"310"`, og
`SUM()` over nul rækker giver `null`. Uden tolerante serializers (`LenientInt`,
`LenientDouble`, `LenientLong`) ville klienten fejle på hver anden række.

**3. Fejl er værdier, ikke exceptions.** `ApiResult` har `Ok` og fire fejltyper:
`Unauthorized` (401/403), `Http` (WordPress' egen `code` + `message`), `Network`
(intet svar) og `Parse` (svar der ikke var det forventede). Beskederne er skrevet til
ejeren: en 401 siger at det er et application password og hvor det laves, en 404
spørger om pluginnet er aktivt, og en 500 viser et uddrag i stedet for hele fejlsiden.

**4. Adressen er tilgivende, passwordet er ikke.** `SiteConfig.parse` accepterer hvad
browseren viste — `steamgears.eu`, med eller uden `/wp-json`, med eller uden
`/sgap/v1`, i en undermappe — men afviser `http://` uden for localhost, fordi
application password ellers sendes i klartekst. Mellemrummene i WordPress'
`abcd efgh ijkl mnop` fjernes før headeren bygges; med dem i svarer WordPress 401.

## Verifikation

```bash
cd android && ./gradlew :core:test     # 46 tests, 0 fejl, JDK 17
```

Dækket: URL-normalisering (8 varianter → samme base), auth-header, at de seks
endpoints rammes med rigtig metode/sti/body mod MockWebServer, at tomme felter
udelades i stedet for at overskrive WordPress' defaults, at snapshots `promotes`/
`has_image` og listens `post_id` parses hver for sig, at `isSending`/`headline` svarer
på "sendes der, og hvis ikke hvorfor", og at 401/403/404/400/409/500, HTML-i-stedet-for-JSON,
tomt svar og et site der ikke svarer bliver til handlingsrettede beskeder.

To fejl blev fanget af testene undervejs og rettet i koden: `isSending` regnede en
pause-årsag som "sender" (fordi `can_send_now` er kvoten, ikke et løfte), og `https://`
alene gav en URI-fejl i stedet for beskeden om manglende domæne.

## Hvad der ikke er bevist

- **Intet kald er gået til steamgears.eu.** Testene kører mod MockWebServer og mod
  fixtures fra PHP. Application password er ikke prøvet mod en rigtig bruger.
- **Ingen APK.** `app`-modulet findes ikke endnu (M2), så `minSdk 26` (OkHttp,
  `java.util.Base64` via OkHttp's `Credentials`) er ikke sat i en rigtig build.
- **Ingen skærme.** `SgapApi` er grænsefladen M2 skal tale med, så skærmene kan testes
  med en fake i stedet for et site.

## Næste skridt

1. M2: `app`-modul med de tre skærme, der bruger de seks endpoints.
2. M3: CI der bygger APK — `.github/workflows/command-center-ci.yml` kører allerede
   `:core:test` på JDK 17, så kontrakten fejler i CI og ikke på telefonen.
3. Flyt `android/` til `steamgears-command-center`, når repoet kan oprettes
   (`GITHUB_TOKEN`).
