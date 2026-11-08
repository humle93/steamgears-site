# Nøgler på ét sted — mønstret fra WordPress 7.0 og 7.1

**Målt:** 7. november 2026 · **Brugt i:** Steamgears Autopilot 7.47.0 (P1.18)

Spørgsmålet var ikke «hvordan ser en pæn indstillingsside ud», men «hvor mange steder kan en
API-nøgle indtastes». Svaret i pluginnet var fem. Denne note samler det, kilderne faktisk siger om
WordPress' eget svar på det samme problem, og hvad der blev besluttet på den baggrund.

## 1. Core har allerede mønstret: Settings → Connectors

WordPress 7.0 introducerede **Connectors API** med skærmen *Indstillinger → Connectors*
([Make WordPress Core, 18/3 2026](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/)).
Det er præcis den situation pluginnet stod i: hver udbyder havde sin egen formular, og ejeren
skulle huske hvilken. Cores løsning er en **registry** og **ét kort pr. tjeneste**:

- Kortet tegnes af registreringsdata: `name`, `description`, `logo_url`.
- `authentication.credentials_url` vises som et **link til det sted, nøglen hentes** — ikke som en
  tekstballon med «find selv ud af det».
- `authentication.method` er i 7.0 `api_key` eller `none`; nøglen gemmes under `setting_name`
  (auto-genereret som `connectors_{type}_{id}_api_key`).
- Nøglekilden slås op i rækkefølgen **miljøvariabel → PHP-konstant → databaseindstilling**.

Det er den struktur `SGAP_Keys` kopierer: én post pr. tjeneste med titel, ikon, formål, hent-link,
valgfri test og status — og rækkefølgen konstant-før-database er allerede pluginnets
`SGAP_<KEY>`-regel fra `SGAP_Settings::constant_name()`.

## 2. Maskering: den fælde der gør en «pæn» nøgleside farlig

Core maskerer gemte nøgler i brugerfladen, men **gemmer dem som klartekst i `wp_options`**
(feltguiden for 7.0 siger det direkte; kryptering er en opfølgning). To ting følger:

1. En nøgle må aldrig sendes tilbage til browseren i en `value=""`. Pluginnet gør det modsatte:
   feltet er altid tomt, og placeholder siger *saved – leave empty to keep*.
2. Hvis man alligevel viser en maske som feltets værdi, bliver masken gemt som den nye nøgle ved
   næste gem — nøglen er så tabt, og fejlen ser ud som en udbyderfejl. Cores patch til Connectors
   validerer nøglen **før** den maskeres, netop fordi rækkefølgen er afgørende
   ([changeset 62194, branches/7.0](https://core.trac.wordpress.org/changeset/62194/branches/7.0)).

Konsekvens for pluginnet, som har **ti** hemmeligheder i samme formular: et tomt felt betyder
«behold», og sletning kræver en eksplicit afkrydsning (`sgap_clear[key]`). Det er allerede reglen i
`SGAP_Settings`; noten her er grunden til at den ikke må ændres for at gøre siden «nemmere».

## 3. Design-tokens i 7.1 kræver `wp-theme`-handle

WordPress 7.1 lægger design-tokens ud som **CSS custom properties** i `wp-theme`-stylesheetet
([Make WordPress Core, 31/7 2026](https://make.wordpress.org/core/2026/07/31/design-system-theming-in-wordpress-7-1/)),
fx `--wpds-color-background-surface-neutral-strong`. De kan bruges fra almindelig CSS, men kun hvis
siden har `wp-theme` enqueued. Pluginnet har ingen build-step og en fast brand-palette
(brass/gold), så tokens bruges som **fallback-supplement**, ikke som fundament: `var(--wpds-…, #9b1c1f)`.
Ellers ville siden skifte udseende alt efter WordPress-version.

## 4. `@wordpress/components`, ikke `@wordpress/ui`

`@wordpress/components` er stadig den understøttede vej til admin-UI. `@wordpress/ui` er den nye
designsystem-pakke og er **eksperimentel**; et plugin uden build-step kan ikke bruge den uden at
trække React og en bundler ind. Beslutningen blev derfor: egen stylesheet, WP-native markup
(`.wrap`, `.form-table`-lignende struktur, `submit_button()`), og kun de interaktioner der kan
skrives i ~20 linjer vanilla JS (faner, gemt-indikator, nøgletest).

## 5. Hvad pluginnet gør nu

- `includes/keys.php` (`SGAP_Keys`) ejer alle ti hemmeligheder: `publer_api_key`, `ai_api_key`,
  `image_api_key`, `serpapi_key`, `telegram_bot_token`, `merchant_feed_secret`,
  `bluesky_app_password`, `mastodon_token`, `mcp_refresh_token`, `printful_api_key`.
- Keys-fanen ligger **først** i `SGAP_Setup::TABS`, og `settings_page()` filtrerer
  `SGAP_Settings::SECRET_KEYS` væk fra alle andre paneler. Det gør reglen strukturel i stedet for
  disciplin: en nøgle der senere skrives på den forkerte fane vises stadig kun ét sted.
- Status pr. kort: `Not set` / `Optional` / `Saved` / `wp-config.php`, plus sidste AI- og
  Publer-test fra `SGAP_KeyCheck`.
- Mastodon-kortets link bygges af den gemte instans, fordi tokensiden er instans-specifik
  (`/settings/applications`) — et fast link ville sende ejeren til mastodon.social uanset hvor hun er.
- Guiden (`SGAP_Setup::wizard()`) spørger kun efter AI-nøglen så længe ingen er gemt; ellers peger
  den på *Settings → Keys*.

## 6. Ikke verificeret

Siden er **ikke** set i en rigtig WordPress-installation fra denne maskine: der findes ingen
MCP-godkendelse i sandkassen, og live-tilstand kan ikke læses herfra. Det der er målt, er
markup og adfærd i smoke-suiten (19 checks: kortdækning mod `SECRET_KEYS` begge veje, ét felt
eller én wp-config-note pr. nøgle, ingen gemt værdi i HTML'en, Mastodon-instanslinket, guidens
betingede felt, og CSS'ens nye dele). Det visuelle billede af kort-grid'et mangler stadig en
menneskelig gennemgang i wp-admin.
