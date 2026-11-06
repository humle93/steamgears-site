# Analytics: to GA4-properties tæller samme besøg — målt 6/11 2026

S0.9 spurgte, om de to tag-id'er på forsiden tæller dobbelt. Svaret er **ja**, og det er ikke længere en
antagelse: begge properties sendte et `page_view` i samme browsersession, med hver sit measurement-id.

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
- `G-3MYLFKY8MY` kommer fra en separat inline `gtag`-blok, ikke fra Site Kit — sandsynligvis en snippet sat ind
  ved siden af, eller et plugin der stadig har sit eget id.

## Hvad der ikke blev gjort, og hvorfor

**Intet tag blev fjernet.** To grunde, som begge står i vejen:

1. Det kræver **skriveadgang til WordPress**, og dette miljø har ingen credential: `python3 tools/mcp_rest.py
   --check` svarer `{"ok": false, "error": "ingen credential"}`. Der er ingen OAuth-token i
   `~/.config/steamgears-mcp/`.
2. Hvilket af de to id'er der skal blive, er **ejerens beslutning**, ikke en teknisk detalje: det afhænger af,
   hvilken property der er koblet til det dashboard og den historik, ejeren bruger. At gætte kan slette den
   eneste måling butikken har — det var netop advarslen i S0.9.

Det anbefalede valg er at beholde **`G-0HC9BLQ88R`** (Site Kit + Ads) og fjerne den inline
`gtag("config","G-3MYLFKY8MY")`-blok, fordi Site Kit er den property der er koblet til dashboardet og til
Ads-kontoen. Men det skal bekræftes af ejeren, før noget røres.

## Sådan lukkes S0.9

1. Ejer bekræfter hvilken property der er "sandheden" (anbefaling: `G-0HC9BLQ88R`).
2. Skriveadgang etableres: `python3 tools/mcp_oauth.py --start`, godkend i browseren,
   `python3 tools/mcp_oauth.py --finish '<kode>'`.
3. Fjern den inline `G-3MYLFKY8MY`-blok (snippet eller det plugin der injicerer den), ryd W3TC-cachen.
4. Genmål som ovenfor: der skal nu være **præcis ét** `g/collect`-kald med `en=page_view`.

## Filer

- De øvrige målinger samme dag: [`site-maalinger-2026-11-06.md`](site-maalinger-2026-11-06.md).
- Adgangsvejen og dens fælder: [`../mcp-auth.md`](../mcp-auth.md).
- Roadmap-punktet: [`../roadmap.md`](../roadmap.md) S0.9 og §9 P5.
