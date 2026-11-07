# Facebook-siden som en kanal, pluginnet kan styre (7.58.0, P2.6)

Pluginnet kunne allerede læse Pinterest og Instagram og skrive til Bluesky, Mastodon og Telegram
uden om Publer. Facebook var det sidste sted, hvor en side blev *nævnt* i indstillingerne men aldrig
rørt: `channels` havde `facebook`, og der stoppede det. Efter denne udgivelse er Facebook en kanal som
de andre — med tre undtagelser, der er valgt med vilje, og som dette dokument handler om.

## Hvad modulet gør

`includes/facebook.php` (`SGAP_Facebook`) har fire opgaver:

1. **Publicere**, når ejeren beder om det (`facebook_direct` = `yes`): et tekstindlæg går til
   `/{page-id}/feed` med linket som `link`, så Facebook selv laver linkpreview; et indlæg med billede
   går til `/{page-id}/photos` med linket i captionen. Rækkefølgen er ikke kosmetisk — et `link` på et
   fotoindlæg ignoreres, og så står posten uden klikbar vej til siden.
2. **Måle**: `/{post-id}/insights` læses for `post_impressions`, `post_impressions_unique`,
   `post_reactions_by_type_total` og `post_clicks`, og gemmes som kanal-afgrænsede hændelser
   (`impressions`, `reach`, `reactions`, `post_clicks`).
3. **Moderere**: kommentarer læses, besvares og skjules. Intet slettes.
4. **Melde fra**: siden har sit eget admin-skærmbillede under Steamgears → Facebook, et kort på
   dashboardet, en REST-flade og en linje i Fejlsøgning.

## De fire valg, der ikke er tekniske

**Publicering er opt-in, og standarden er "nej".** En side kan forbindes for at blive *læst* —
følgere, kommentarer, tal — uden at et eneste indlæg flytter sig. Det er ikke en forsigtighedsregel
for sjov: en nøgle, der gemmes for at se tallene, må ikke i sig selv flytte publiceringen ud af
Publer, for det er en beslutning om arbejdsgang, ikke om API-adgang. Derfor `facebook_direct` som
egen indstilling under Publishing, og derfor `handles()` — den eneste indgang til den direkte kanal —
som svarer `false`, indtil nogen har slået den til.

**En side-nøgle, ikke en bruger-nøgle.** Det er den fejl, der koster mest tid: et bruger-token kan
læse `/me/accounts`, men det kan ikke skrive som siden. Modulet forklarer det i selve fejlmeddelelsen
ved kode 190 i stedet for at videresende Metas "Invalid OAuth access token" og lade ejeren gætte.

**Ingen sletning.** Skjul er reversibelt, svar er en tilføjelse, sletning er endeligt — og en cron
skal ikke kunne gøre endelige ting ved andres ord. `hide()` sender `is_hidden=true|false` som streng,
fordi Graph API'et læser form-felter som tekst.

**Graph-versionen er en indstilling, ikke en konstant.** Meta pensionerer versioner med ca. to års
varsel. En hardkodet version i koden er et tidspunkt for et nedbrud; `facebook_graph_version` er et
felt, nogen kan rette, og et ugyldigt svar falder tilbage til `v21.0` frem for at bygge en URL, der
ikke findes.

## De tekniske fælder, der blev ramt undervejs

- **`post_reactions_by_type_total` er et objekt**, ikke et tal: `{LIKE: 3, LOVE: 2}`. Det skal
  summeres, ellers gemmes `1` (eller `0`) som "reaktioner".
- **Ikke alle medietyper har alle metrikker.** Et indlæg uden klik kan få hele insights-kaldet afvist
  med kode 100. Derfor: prøv det brede sæt, og hvis svaret er en fejl, så spørg igen med de to
  metrikker alle indlæg har (`impressions` og `reach`) i stedet for at tabe posten.
- **En metrik Meta ikke har rapporteret endnu er ikke 0.** Den udelades fra `$counts`, og posten
  tælles som `waiting` og spørges igen i næste kørsel. Et indlæg med "0 visninger" og et indlæg Meta
  endnu ikke har målt er to forskellige ting, og kun den ene må skrives i databasen.
- **Sletningen ved gem skal være afgrænset til kanalen.** `likes` og `shares` er også Bluesky-,
  Mastodon- og Instagram-typer; en sletning på `queue_id` alene ville tage deres tal med. Derfor
  `channel = 'facebook' AND event_type IN (...)`.
- **`can_hide` kommer fra Meta** og styrer, om skjul-knappen tilbydes. Et felt, der ikke er med i
  svaret, behandles som "må skjules", fordi det er Metas standard — men et udtrykkeligt `false`
  respekteres.

## Verifikation, og hvad den ikke dækker

82 nye smoke-checks i `plugins/tests/facebook.php`, alle gennem `$transport`-sømmen, så ingen
anmodning forlader maskinen: ukonfigureret tilstand (ingen kald, ingen selvcheck-støj), begge
publiceringsveje, mapping og trimning, insights inkl. reaktionsobjektet og den smalle genforespørgsel,
tomme svar som `waiting`, kanal-afgrænset sletning, fejlkoderne 190/4/100, svar og skjul, at en god
kørsel rydder fejlen en dårlig efterlod, side-skærmens escaping og REST-svaret. Smoke går fra 905 til
987 PASS, 0 FAIL.

Det er **ikke** det samme som at have set siden virke. Der findes ingen Page access token i
arbejdsmiljøet, så ingen af disse kald er gået til Meta: hverken publicering, insights eller
kommentarer er målt mod den rigtige Graph API, og skærmbilledet er ikke set i en rigtig wp-admin.
Det, der er verificeret, er at pluginnet gør det, det siger, mod svar der har Metas form — og at det
melder ærligt, når det ikke kan.
