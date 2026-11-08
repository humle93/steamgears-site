# Fejlsøgning i pluginnet — og forbeholdene der følger tallene

**Dato:** 7/11 2026 · **Release:** 7.57.0 · **Modul:** P1.22

Indtil nu var fejlsøgning noget der skete **uden for** pluginnet: `tools/fejlscan.py` tjekker repoet, den natlige
kørsel tjekker testene, og selvtjekket (`SGAP_Auto::health()`) samler de problemer det selv kender. Det dækker
koden — men ikke det ejeren står med på sitet: *hvad fejlede, hvornår, og kan jeg regne med tallene?*

Dette pas giver de to ting hver sin form:

1. **Fejlsøgning som en side i pluginnet** (`SGAP_Diagnostics`, Steamgears → Fejlsøgning), med en ringbuffer af
   modulfejl og ni tjek der læses ud af den tilstand modulerne allerede holder.
2. **Forbehold som en del af svaret**, ikke som fodnote: hvert modul med tal udefra får en linje under sine tal
   der siger hvad tallene *ikke* indeholder — og den samme tekst står samlet på fejlsøgningssiden.

## 1. Hvorfor en log, når der allerede er en fejltekst

Hvert modul holder **én** fejltekst (`sgap_instagram_state['error']`, `sgap_pinterest_state['error']`), og den
bliver ryddet af den næste kørsel der lykkes. Det er rigtigt for statusvisningen — den skal vise *nu* — men det
betyder at et natligt afslag kl. 03 forsvinder kl. 09, uden andet spor end et tal der er lidt lavere end
forventet. Det er præcis den slags fejl der ikke bliver fundet.

`SGAP_Diagnostics::log()` skriver derfor til `sgap_error_log` (ikke autoloadet, 40 rækker):

- **Kilden** (`instagram`, `pinterest`, `publer`, `mcp`, …), fejlkoden og beskeden.
- **To tidsstempler:** `at` i sitets ur til visning, og `ts` som `time()` til regnestykker. Den adskillelse er
  ikke kosmetik: en tidligere fejl i Pinterest-modulet sammenlignede `gmdate()` med rækker skrevet på sitets ur,
  og det gav op til ±14 timers forskydning. Vinduet »fejl i de sidste 24 timer« må ikke arve den fejl.
- **Et antal:** den samme fejl fra samme kilde lægges ikke oveni som ny række — den nyeste række opdateres og
  `count` går op. En natlig fejl der gentager sig er **ét** problem, ikke tredive, og antallet er information:
  det er antallet af kørsler problemet overlevede.
- Beskeder klippes ved 300 tegn og kontekst ved 80, så en HTML-fejlside ikke kan fylde hele optionen.

Modulerne skriver til loggen gennem deres egen `record()`: Instagram og Pinterest kalder `log()` når `ok` er
falsk og der er en fejltekst. Det er bevidst lagt der og ikke i hvert kaldested — `record()` er det ene sted
tilstanden skrives, så en ny fejlvej ikke kan glemme loggen.

## 2. De ni tjek

Siden læser tilstand, den skriver den ikke. Hvert tjek har en tilstand (`ok`, `warn`, `bad`, `off`), en sætning om
hvad det fandt, og en sætning om hvad man gør — også når det er gået godt, så rækken ikke er tom.

| Tjek | Hvad det fanger | Hvorfor det er med |
| --- | --- | --- |
| PHP-version | under 8.0 | pluginnet erklærer 8.0, og en host kan skifte uden varsel |
| Databasetabeller | manglende `sgap_queue`, `sgap_events`, `sgap_ai_drafts`, `sgap_publish_log` | et halvt aktiveret plugin ligner bare »ingen data« |
| Planlagte job | job uden næste kørsel, eller timer bagud | hvis cron står stille, ser alle andre tal fine ud |
| Fejlede posts | antal + den seneste fejltekst | den ene fejl der kræver en handling, med teksten |
| AI-nøgle | mangler, eller sidste test fejlede | uden nøgle skrives intet nyt |
| Pinterest | konfigureret, token, sidste og næste kørsel | en udløbet token ligner »ingen pins i denne måned« |
| Instagram | samme | samme |
| MCP-credential | afvist fornyelse | forbindelsen dør ellers stille |
| Modulfejl (24 t) | antal rækker i loggen | det eneste sted en fejl der *blev rettet* af næste kørsel stadig findes |

En kanal der **aldrig er blevet forbundet** er `off`, ikke `bad`. Det er ikke en fejl at have slået en kanal fra,
og et dashboard der råber op om det, lærer ejeren at ignorere farverne.

Siden har to knapper, begge POST til `admin-post.php` med nonce: **Run the checks now** og **Clear the error log**.
Et refresh i browseren gentager dem ikke. Rapporten kan også hentes som `GET /wp-json/sgap/v1/diagnostics`
(`manage_options`), så en MCP-klient eller en shell kan stille samme spørgsmål som siden.

Dashboardkortet vises **kun** når der er noget at sige. Et grønt kort der gentager »alt er godt« hver dag er den
hurtigste måde at få ejeren til at springe det sted over hvor en fejl ville stå.

## 3. Forbeholdene

Et tal uden sin begrænsning bliver læst som en påstand. Derfor har hvert modul en tekst der siger hvad der er
*uden for* tallet, og den står to steder: som en linje under modulets eget dashboardkort (`SGAP_UI::caveat()`) og
samlet på fejlsøgningssiden. De seks tekster er:

- **instagram** — det Instagram havde rapporteret da vi spurgte; en metric der ikke er offentliggjort endnu
  (op til 48 timer) er **udeladt, ikke gemt som 0**, og hvert medie rapporterer sine egne livstidstal, så
  tabellen er ikke en sum over en periode.
- **pinterest** — samme forsinkelse; `saves` og `likes` er event-typer vi også bruger til andre kanaler, så
  hver forespørgsel er afgrænset til kanalen `pinterest`.
- **engagement** — likes, reposts og replies er hvad platformene svarede; en platform der ikke er forbundet
  eller afviser, giver ingen række — ikke nul — og årsagen står i fejl-loggen.
- **roi** — omsætning er last-click inden for vinduet: en ordre uden et sporet klik hører til ingen kanal, og
  vores egne `/go/`-klik er talt af os, ikke af platformen.
- **automation** — et opslag der aldrig kom afsted har ingen tal; et fejlet opslag prøves én gang igen og står
  derefter som fejlet indtil nogen ser årsagen.
- **mcp** — fornyelsen rapporteres, men en klient der holder op med at kalde, ligner et site ingen spørger om.

Teksterne er skrevet som **begrænsningen**, ikke som en ansvarsfraskrivelse: hvad der er udeladt, og hvorfor.

## 4. Hvad det ikke løser

- **Ingen live-kørsel.** Der findes hverken Pinterest- eller Instagram-token i repoet, så kanal-rækkerne er
  testet mod stubbede svar. Første rigtige kørsel skal bekræfte at en 400/code 190 faktisk lander i loggen med
  den tekst Instagram giver.
- **Loggen er lokal.** Den ligger i `wp_options` og overlever ikke en `DELETE` af pluginnet (det er med vilje:
  `uninstall.php` rydder alt `sgap_%`). Den sendes ikke til nogen ekstern tjeneste.
- **Siden måler ikke sitet.** Den kan sige at Pinterest ikke svarede, ikke at Pinterest svarede forkert.
- **Tavse fejl findes stadig.** En kodevej der fanger en fejl uden at kalde `record()` eller `log()` er usynlig
  for både siden og selvtjekket. Det er den slags `tools/fejlscan.py` kan finde i kilden, ikke pluginnet i drift.

## 5. Verifikation

- **67 nye smoke-checks** (838 → **905 PASS, 0 FAIL**): ringbufferens loft, at ældste rækker ryger først, at
  `ts` og ikke `at` styrer 24-timers-vinduet, at et `WP_Error` beholder kode og besked, at ens fejl tælles op i
  stedet for at duplikere, at ikke-skalar kontekst ikke gemmes, at et manglende token i `wp-config.php` er en
  fejl mens en uforbundet kanal er `off`, at tre fejl i vinduet vender tjekket til `bad`, at sidens HTML escapes,
  og at dashboardkortet har en tavs vej (`quiet()`).
- **24/24 værn grønne**, `php -l` rent, `tools/fejlscan.py` uden fund, zip byte-identisk med kilden.

## 6. Filer

| Fil | Ændring |
| --- | --- |
| `plugins/steamgears-autopilot/includes/diagnostics.php` | ny: `SGAP_Diagnostics` — log, ni tjek, side, REST, dashboardkort |
| `plugins/steamgears-autopilot/includes/admin-ui.php` | `SGAP_UI::caveat()` + CSS til forbehold |
| `plugins/steamgears-autopilot/includes/instagram.php`, `pinterest.php` | `record()` logger nu fejl; forbehold under kortet |
| `plugins/steamgears-autopilot/includes/engagement.php` | forbehold under engagement-kortet |
| `plugins/steamgears-autopilot/includes/automation.php` | selvtjekket nævner fejl-loggen |
| `plugins/steamgears-autopilot/includes/core.php` | dashboardkortet kaldes |
| `plugins/steamgears-autopilot/steamgears-autopilot.php` | require + init, version 7.57.0 |
| `plugins/tests/diagnostics.php` | 67 checks |
