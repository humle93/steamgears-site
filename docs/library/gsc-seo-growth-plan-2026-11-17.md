# GSC SEO-status og vækstplan — Steamgears.eu (2026-11-17)

**Formål:** omsætte milepælen på 20 organiske klik til en kort, målelig SEO-plan. Dette dokument skelner mellem verificerede data og anbefalinger; det dokumenterer ingen ændringer på live-sitet.

## 1. Konklusion

Steamgears.eu har fået et tydeligt løft i synlighed fra et meget lille sammenligningsgrundlag. GSC målte **20 klik og 789 visninger** i 28-dagesperioden 18/10–14/11 2026, mod **1 klik og 9 visninger** i perioden 20/9–17/10. CTR var 2,53 % mod 11,11 %, og gennemsnitlig position var 13,74 mod 10,33 (`get_site_summary`). Klik- og visningsvæksten er positiv; faldet i CTR og den højere positionsværdi viser samtidig, at den nye synlighed endnu ikke omsættes effektivt til klik. Den foregående periode var meget lille, så den er ikke en stabil baseline.

**Vurdering: Stabil med realistiske vækstmuligheder.** Næste indsats bør fokusere på få sider, der allerede får visninger, og på at afklare tekniske datafejl. Hold informationsindhold adskilt fra produktløfter: butikken skiftede til plakatfokus 15/11 ifølge [posters-only-status](posters-only-butik-2026-11-15.md), altså efter GSC-periodens afslutning.

## 2. Verificerede Search Console-data

Alle tal i denne tabel kommer fra GSC Wizard. Hovedvindue: 18/10–14/11 2026; Search Console-data var afklaret til og med 15/11.

| Måling | 18/10–14/11 | Sammenligning 20/9–17/10 | Fortolkning |
| --- | ---: | ---: | --- |
| Klik | 20 | 1 | Flere klik, men meget lille sammenligningsgrundlag |
| Visninger | 789 | 9 | Synligheden er steget markant fra lav base |
| CTR | 2,53 % | 11,11 % | Lavere CTR; tidligere periode havde kun 9 visninger |
| Gennemsnitlig position | 13,74 | 10,33 | Højere tal betyder lavere placering |

Kilde: `get_site_summary`. CTR-deltaen er −8,58 procentpoint; positionsforskellen er +3,40 (forringelse, fordi lavere positionstal er bedre). Disse deltaer er beregnet fra de to perioders værktøjsværdier.

### Synligt søgeordsudsnit

`get_position_distribution` viste 286 visninger og 4 klik fordelt på de synlige søgeordsrækker: position 1–3 havde 5 visninger (1,7 %), position 4–10 havde 87 (30,4 %), position 11–20 havde 59 (20,6 %), og position 21+ havde 135 (47,2 %). Søgninger på position 21+ er dermed den største gruppe i dette udsnit.

Søgeordsdimensionen dækker ikke hele ejendommens total. GSC kan udelade anonyme forespørgsler; brug derfor de 286 søgeordsvisninger som et delmængde-udsnit, ikke som et alternativ til de 789 samlede visninger.

### Søgeord med konkret landingsside

| Søgeord | Landingsside | Visninger | Klik | Gns. position | GSC Wizards estimerede ekstra klik* |
| --- | --- | ---: | ---: | ---: | ---: |
| `steampunk costume ideas` | `/steampunk-costume-ideas/` | 28 | 1 | 11,64 | 6 |
| `inventor outfit` | `/steampunk-mad-scientist-costume/` | 13 | 0 | 8,38 | 3 |
| `dieselpunk vs steampunk` | `/steampunk-vs-dieselpunk/` | 11 | 0 | 12,91 | 3 |

Kilde: `query_search_analytics` for søgeord + side samt `score_opportunities`, 18/10–14/11 2026. *PotentialClicks er modellens mulighedsskøn ved 25 % CTR for søgeord med position under 3; det er ikke et trafikløfte. De tre værdier summerer til 12 estimerede ekstra klik, beregnet fra samme værktøjsrækker.*

Andre søgesignaler: `get_ranking_changes` fandt 88 nye synlige forespørgsler, 0 tabte, 1 forbedret og 0 forringede. Den stærke nyhedsmængde skal læses sammen med den minimale sammenligningsperiode. `steampunk goggles` fik 28 visninger omkring position 32,68; det er et længere sigt-indholdssignal, ikke en af de tre højest scorede muligheder.

## 3. Teknisk og indholdsmæssig status

GSC Wizards on-page-audit crawlede 10 af de mest viste sider for perioden 18/10–14/11 (`audit_onpage_seo`). Alle 10 var indekserbare. Auditten fandt 29 forhold: 11 medium og 18 low, ingen high eller critical.

- Organisationsschema mangler `logo` på alle 10 auditerede sider.
- `/shop/` har et produkt-schema uden `image` i auditresultatet. Den gamle URL `/product-category/steampunk-goggles/` viderestiller til `/shop/`; kontrollér schemaet på den endelige side og kontrollér interne links/sitemap, før der ændres noget ved redirecten.
- Svartidsmålingerne for de 10 auditerede URL'er var ca. 2,4–4,0 sekunder. Det er audit-crawlerens HTTP-responstid, ikke et Core Web Vitals-feltmål.
- Auditten fandt manglende alt-tekst på enkelte billeder. Prioritér billeder på sider, der allerede har synlighed, og kontrollér at teksten beskriver motivet.
- `get_core_web_vitals` returnerede `notConfigured`: CrUX API-nøgle mangler. Der kan ikke drages en konklusion om reelle brugeroplevelsesmålinger fra denne rapport.

`analyze_cannibalization` fandt ét synligt søgeord over minimumsgrænsen: `steampunk gears` fordelt på fem URL'er med 17 søgeordsvisninger, nul klik. URL'erne var forsiden, `/steampunk-accessories/`, `/journal/`, `/product-category/accessories/` og `/build/tools-and-materials/`. Volumen er lav; det begrunder en afklaring af sidernes roller ved næste indholdsredigering, ikke en blind sammenlægning.

`detect_anomalies` fandt ingen enkeltdagsafvigelser over værktøjets tærskel i den faktiske dækning 15/10–14/11. `get_decay_overview` dækkede de 16 komplette måneder juli 2025–oktober 2026; synlige søgeordsrækker havde kun 1 klik og 116 visninger i alt. Historikken er for sparsom til en robust decay-konklusion.

## 4. Prioriteret strategi

Dette er en plan for næste vækstcyklus. De angivne indsatser er estimater for arbejdet i repo-/siteflowet, ikke registreret fremgang eller udført live-arbejde.

| Prioritet | Handling | Begrundelse og godkendelseskriterium | Estimat |
| --- | --- | --- | ---: |
| 1 | Kontrollér og ret organisationsschemaets `logo`; kontrollér produktbilledet på den endelige `/shop/`-side | Gentaget middelalvorligt fund. Bekræft at schema-kilden ikke allerede findes i en anden plugin-/theme-output, og mål JSON-LD igen efter ændring | 0,20 d |
| 2 | Opdatér `/steampunk-costume-ideas/`, `/steampunk-mad-scientist-costume/` og `/steampunk-vs-dieselpunk/` for deres konkrete søgeintention | De tre søgeord har placering 8–13 og 11–28 synlige visninger. Test snippet-vinkel og tydeligere svar; titler og beskrivelser var allerede inden for længdeintervallet i on-page-auditten, så undgå mekanisk omskrivning alene for længdens skyld | 0,60 d |
| 3 | Afklar sideroller for `steampunk gears`, og gennemgå den gamle kategori-URL's interne links | Fem sider konkurrerer om et lille synligt datasæt. Vælg kun én primær side hvis næste måling bekræfter samme mønster; bevar en korrekt redirect og fjern kun forældede interne linkmål | 0,15 d |
| 4 | Gennemgå alt-tekster og server-responstid på de sider med flest visninger; sæt CrUX API-nøgle op i GSC Wizard | Skil crawlerens svartid fra faktiske brugerdata. Efter CrUX-opsætning vurderes LCP, INP og CLS før eventuel ydelsesprioritering | 0,15 d |
| 5 | Genmål efter én fuld, afklaret 28-dagesperiode efter ændringerne | Sammenlign klik, visninger, CTR og gennemsnitsposition pr. søgeord + landingsside. Udelad de seneste 2–3 uafklarede dage | 0,10 d |
| **Samlet agentestimat** | | | **1,20 d** |

### Redaktionel porteføljeregel efter plakatskiftet

Kostume- og DIY-søgninger er fortsat relevante som informationsindhold, men de må ikke føre til købsløfter om produkter, der er sat på kladde. Behold hjælpsomme guides, og link til plakater kun når motivet faktisk supplerer emnet. Målet er at bevare opnået søgesynlighed uden at forringe læserens oplevelse eller gøre en plakat til en kunstig erstatning for et kostumeprodukt.

## 5. Måling og beslutningsregler

1. Gem baselineværdierne ovenfor og den aktive produktportefølje ved implementeringstidspunktet.
2. Redigér kun de målrettede sider; bevar eksisterende slugs og kanoniske URL'er.
3. Efter ændringer, registrér dato og hvilke title/meta/body/schema-felter der faktisk blev ændret.
4. Vent på én komplet 28-dages Search Console-periode. En positiv CTR alene er ikke nok ved små impression-tal; læs ændringen sammen med visninger og position.
5. Hvis query/page-data forbliver sparsomt, udsæt sammensmeltning af indhold og prioritér strukturerede data og sidekvalitet.

**Ikke udført i denne rapport:** ingen WordPress-side er redigeret, ingen produktstatus eller redirect er ændret, og ingen teknisk fejl er markeret som løst. Rapporten er strategi og dokumentation baseret på GSC Wizard-udtrækkene ovenfor.
