# Analytics: to GA4-properties tæller samme besøg — målt 6/11, rettet 7/11 2026

S0.9 spurgte, om de to tag-id'er på forsiden tæller dobbelt. Svaret er **ja**, og det er ikke længere en
antagelse: begge properties sendte et `page_view` i samme browsersession, med hver sit measurement-id.

**Rettet 7/11 2026:** den inline blok viste sig at komme fra **Rank Math**, ikke fra et snippet, og den er nu
slået fra. Der er målt **ét** `g/collect` med `en=page_view` tilbage. Se »Rettet 7/11« nedenfor.

## Sådan blev det målt

- Forsiden `https://steamgears.eu/` åbnet i en rigtig Chrome-session (cloud-browseren), ingen cookie-banner
  accepteret, ingen cache-busting.
- `performance.getEntriesByType('resource')` læst for alle kald til `google-analytics.com` / `analytics.google.com`.
- De inline `gtag`-blokke og `window.google_tag_manager` læst for at se, hvilke containere og properties der
  overhovedet er indlæst.

## Hvad der blev sendt

| Rækkefølge | Request | Property | Event | Svar |
| --- | --- | --- | --- | --- |
| 1 | `POST analytics.google.com/g/collect?v=2&tid=G-3MYLFKY8MY…` | **G-3MYLFKY8MY** | `en=page_view` | 204 |
| 2 | `POST www.google-analytics.com/g/collect?v=2&tid=G-0HC9BLQ88R…` | **G-0HC9BLQ88R** | `en=page_view&ep.googlesitekit_post_type=page` | 204 |

Begge kald har samme `cid` (`132254375.1794004680`) og næsten samme `sid`, altså **samme bruger og samme
session** — det er præcis den dobbeltregning S0.9 skulle afgøre.

Hvor de kommer fra:

| Kilde i HTML | Hvad den gør |
| --- | --- |
| `gtag("config", "G-3MYLFKY8MY", {})` i en inline blok | sætter GA4-property `G-3MYLFKY8MY` direkte |
| `gtag("config", "GT-PL9L67B4", {...})` fra Google Site Kit | containeren loader **GA4 `G-0HC9BLQ88R`** og sender `page_view` med `googlesitekit_post_type` |
| `gtag("config", "AW-18518250875")` + `gtag("event","page_view",{send_to:"GLA"})` | Google Ads-konvertering, ikke en tredje GA4-property |

Der er altså **tre id'er i HTML'en, men kun to GA4-properties** — og begge fyrer `page_view`. `AW-…` er
Ads-konverteringssporingen og skal blive.

## Hvad det betyder

- Alle besøg på sitet registreres to gange i to forskellige GA4-properties. Sessions-, bruger- og
  konverteringstal i den ene kan ikke sammenlignes med den anden, og ingen af dem er "sandheden".
- Google Site Kit læser `G-0HC9BLQ88R`, fordi det er den property Site Kit er koblet til (den har også
  Ads-kontoen bag `AW-18518250875`). Det er den property der er koblet til ejerens dashboard.
- `G-3MYLFKY8MY` kommer fra en separat inline `gtag`-blok, ikke fra Site Kit. 6/11 stod der »sandsynligvis en
  snippet«; 7/11 blev kilden fundet: det er **Rank Maths analytics-modul**, ikke et snippet.

## Hvad der ikke blev gjort 6/11, og hvorfor

**Intet tag blev fjernet den 6/11.** To grunde, som begge stod i vejen:

1. Det kræver **skriveadgang til WordPress**, og dette miljø har ingen credential: `python3 tools/mcp_rest.py
   --check` svarer `{"ok": false, "error": "ingen credential"}`. Der er ingen OAuth-token i
   `~/.config/steamgears-mcp/`.
2. Hvilket af de to id'er der skal blive, er **ejerens beslutning**, ikke en teknisk detalje: det afhænger af,
   hvilken property der er koblet til det dashboard og den historik, ejeren bruger. At gætte kan slette den
   eneste måling butikken har — det var netop advarslen i S0.9.

Det anbefalede valg er at beholde **`G-0HC9BLQ88R`** (Site Kit + Ads) og fjerne den inline
`gtag("config","G-3MYLFKY8MY")`-blok, fordi Site Kit er den property der er koblet til dashboardet og til
Ads-kontoen. Men det skal bekræftes af ejeren, før noget røres.

## Rettet 7/11 2026 — kilden var Rank Math

### Sådan blev kilden fundet

Rækkefølgen er værd at gemme, fordi de to første spor var blindgyder:

1. **Filsøgning i `wp-content`:** 34.972 php/html/js/css/json-filer scannet for strengen `G-3MYLFKY8MY`.
   **Nul hits.** Snippet-plugins var heller ikke aktive (Code Snippets svarede »not active«). En filsøgning
   ville aldrig have fundet den.
2. **Databasesøgning:** `wp_options` med `LIKE '%G-3MYLFKY8MY%'` gav præcis én række:
   `rank_math_google_analytic_options` med `measurement_id: G-3MYLFKY8MY` og `install_code: "1"`.
3. **Bekræftelse i kilden:** `seo-by-rank-math/includes/modules/analytics/class-gtag.php:71-74` —
   `add_analytics_tag()` læser `install_code` og returnerer med det samme, hvis den er tom. Det ene flag
   styrer **både** `<script id="google_gtagjs">` og den inline `gtag("config", "G-3MYLFKY8MY", {})`.

### Indgrebet

| Før | Efter |
| --- | --- |
| `install_code: "1"` | `install_code: ""` |
| `measurement_id: G-3MYLFKY8MY`, `property_id: 559297232` | **uændret** — Rank Maths property-opsætning og dashboard røres ikke |

Selve skrivningen blev gjort med Novamira CLI 1.3.6 over `novamira/execute-php` (device-godkendt adgang,
`doctor` → `ok: true`): læs optionen, gem den serialiserede gamle værdi (355 bytes), sæt `install_code` til tom
streng, `update_option()`. Bagefter blev cachen ryddet i tre lag — object cache, Rank Maths sitemap-cache og
W3TC `flush_all` (`steamgears-tools/flush-caches` + `royal-mcp/w3tc-purge-cache` med `scope: all`).

**Rollback** er ét felt: sæt `install_code` tilbage til `"1"` i samme option og ryd cachen igen. Intet andet
blev ændret, og `AW-18518250875` (Ads) og `GT-PL9L67B4` (Site Kit) blev ikke rørt.

### Kontrol 1 — HTML'en

`curl` af forsiden efter cache-rydningen:

| Streng | 6/11 | 7/11 |
| --- | --- | --- |
| `G-3MYLFKY8MY` | 2 | **0** |
| `google_gtagjs-inline` (Rank Maths inline-blok) | 1 | **0** |
| `GT-PL9L67B4` (Site Kit) | 2 | 2 |
| `AW-18518250875` (Ads) | 3 | 3 |

De `gtag`-kald der står tilbage, er Site Kits `config GT-PL9L67B4` (med `googlesitekit_post_type`) og
Ads-konfigurationen `config AW-18518250875` + `event page_view {send_to: GLA}` — altså præcis det, der skulle
blive.

### Kontrol 2 — browseren

Samme målemetode som 6/11, i cloud-browseren med statistik-samtykke givet:

| Side | `g/collect` i alt | heraf `en=page_view` | `tid` |
| --- | --- | --- | --- |
| `/` (første load) | 1 | **1** | `G-0HC9BLQ88R` |
| `/?nocache=1` (hård reload) | 1 | **1** | `G-0HC9BLQ88R` |
| `/shop/` | 1 | **1** | `G-0HC9BLQ88R` |

`cid` er stadig `132254375.1794004680`, altså samme browser og samme bruger som 6/11 — sammenligningen er
1:1. Ads sporer fortsat (`AW-18518250875` loader, `google.com/ccm/collect?tid=AW-18518250875` svarer), og
der er **nul** requests til `G-3MYLFKY8MY` på alle tre sider.

**Resultat:** dobbeltregningen er væk. Alle besøg tælles nu én gang, i `G-0HC9BLQ88R` — den property der er
koblet til ejerens Site Kit-dashboard og til Ads-kontoen.

## Den oprindelige lukke-opskrift (6/11, nu udført)

1. Ejer bekræfter hvilken property der er "sandheden" (anbefaling: `G-0HC9BLQ88R`).
2. Skriveadgang etableres: `python3 tools/mcp_oauth.py --start`, godkend i browseren,
   `python3 tools/mcp_oauth.py --finish '<kode>'`. — 7/11 blev vejen i stedet Novamira CLI'ens
   device-godkendelse, som har sin egen credential uden om `tools/mcp_rest.py`.
3. Fjern den inline `G-3MYLFKY8MY`-blok (snippet eller det plugin der injicerer den), ryd W3TC-cachen. —
   kilden var Rank Math-optionen, ikke et snippet.
4. Genmål som ovenfor: der skal nu være **præcis ét** `g/collect`-kald med `en=page_view`. — **opfyldt** på
   tre sider.

## Filer

- De øvrige målinger samme dag: [`site-maalinger-2026-11-06.md`](site-maalinger-2026-11-06.md).
- Adgangsvejen og dens fælder: [`../mcp-auth.md`](../mcp-auth.md).
- Roadmap-punktet: [`../roadmap.md`](../roadmap.md) S0.9 og §9 P5.
