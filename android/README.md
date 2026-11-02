# Command Center — Android-klient (M1)

Tynd klient mod WordPress-pluginnet `steamgears-autopilot`. Al logik bliver i
pluginnet; appen viser status, kladder og kø, og kalder de samme `sgap/v1`-endpoints
som dashboardet.

**M1 er API-klienten og login.** Der er ingen skærme endnu (det er M2), og appen
er derfor ikke en APK der kan installeres — men den del der kan gå galt (URL,
application password, parsing af det WordPress faktisk sender, fejlbeskeder)
er bygget og testet.

## Moduler

| Sti | Hvad | Kræver Android SDK? |
| --- | --- | --- |
| `core/` | `SiteConfig`, `OkHttpTransport`, `SgapClient`, DTO'er, `ApiResult` | **Nej** — ren Kotlin/JVM |
| `app/` | Compose-skærme (M2) | Ja |

`core` har ingen Android-afhængigheder, så det kan bygges og testes på en almindelig
JVM. Det er med vilje: CI og en hvilken som helst maskine kan køre testene uden at
installere Android SDK, og fejl i kontrakten findes før nogen har startet en emulator.

## Tests

```bash
cd android
./gradlew :core:test          # 46 tests, JVM, ingen Android SDK
```

Testene dækker:

- **URL-normalisering** — `steamgears.eu`, `https://steamgears.eu/`, `…/wp-json`,
  `…/wp-json/sgap/v1` og installationer i en undermappe giver samme REST-base;
  `http://` afvises uden for localhost, fordi application password ellers sendes i klartekst.
- **Auth** — application password sendes som Basic auth med mellemrummene fjernet
  (WordPress viser det i grupper af fire; med mellemrummene i headeren svarer det 401).
- **Kontrakten** — `snapshot`, `drafts`, `drafts/{id}/status`, `queue`, `queue/{id}/status`
  og `stats` kaldes med de metoder, stier og bodies pluginnet forventer, mod en rigtig
  HTTP-server (MockWebServer). Tomme felter udelades, så WordPress' defaults gælder.
- **Parsing** — felterne læses som WordPress sender dem: MySQL giver tal som strenge
  (`"id": "12"`, `"published": "42"`), `SUM()` over nul rækker giver `null`, og
  `post_id` findes i `/drafts` men ikke i `/snapshot`.
- **Fejl** — 401/403/404/400/500, HTML i stedet for JSON, tomt svar og et site der
  ikke svarer, bliver til beskeder ejeren kan handle på i stedet for en stacktrace.

### Fixtures kommer fra pluginnet, ikke fra hånden

`core/src/test/resources/snapshot.json`, `drafts.json` og `stats.json` er genereret ved
at kalde den rigtige PHP:

```bash
php core/src/test/fixtures/generate-fixtures.php snapshot > core/src/test/resources/snapshot.json
php core/src/test/fixtures/generate-fixtures.php drafts   > core/src/test/resources/drafts.json
php core/src/test/fixtures/generate-fixtures.php stats    > core/src/test/resources/stats.json
```

Scriptet stubb'er WordPress-funktionerne og lader `$wpdb` svare ud fra forespørgslen,
så `SGAP_REST::snapshot()` kører uændret. Ændrer pluginnet sit svar, skal fixtures
regeneres — og så fejler testene hvis klienten ikke følger med.

## Brug

```kotlin
val config = SiteConfig.parse("https://steamgears.eu", "owner", "abcd efgh ijkl mnop")
val api = SgapClient(config)

when (val result = api.snapshot()) {
    is ApiResult.Ok -> println(result.value.headline)   // "Sender — 5 klar nu"
    is ApiResult.Failure -> println(result.message)     // fx hvad der skal rettes i WordPress
}

api.setDraftStatus(31, DraftStatus.Approved)
```

`SgapApi` er grænsefladen skærmene skal tale med, så M2 kan testes med en fake og
ikke skal have et WordPress-site at køre imod.

## Hvad der ikke er bevist

- Klienten har **aldrig talt med steamgears.eu**. Testene kører mod MockWebServer og
  mod fixtures fra PHP, ikke mod en installeret WordPress med rigtige credentials.
- Application password er ikke prøvet mod en rigtig bruger, så 401-vejen er kun
  testet med et konstrueret svar.
- `core` er ikke brugt fra Android endnu. OkHttp og `java.util.Base64` (via OkHttp's
  `Credentials`) kræver `minSdk 26`, hvilket `app`-modulet skal sætte i M2.
