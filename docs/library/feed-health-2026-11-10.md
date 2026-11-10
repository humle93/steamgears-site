# Feed health: RSS-tilstanden kan nu måles

**Målt 10/11 2026 · Autopilot 7.69.0 · P7 i `projektstatus.json`**

I RSS-tilstand — standarden — sender pluginnet ingen token. Publer henter én RSS-feed pr. kanal og poster det
den finder. Derfor findes der ingen token-status at vise, og spørgsmålet »kan kanalen sende?« har et andet
svar end i API-tilstand. Roadmap'en skrev det selv 1/11, i §4.1 pkt. 2: »We hold no tokens in RSS mode (Publer
does), so the equivalent for us is feed health — and there is none.« Det har stået åbent i ni dage.

## Hvad der blev målt i stedet

Feedet er den eneste observerbare flade, og det er faktisk nok: hvis feedet er tomt, har Publer intet at poste;
hvis den nyeste post er gammel, er kanalen gået i stå; og hvis en post mangler titel, tekst eller det foto
Instagram/Pinterest kræver, bliver den afvist eller postet halvfærdigt. Alle tre kan ses uden en Publer-konto,
fordi feedet er vores eget.

`SGAP_RSS::health()` læser **præcis de rækker `xml()` renderer** (`items()`, `external_id='rss'`,
`status='published'`) og giver hver konfigureret kanal én tilstand:

| Tilstand | Betydning |
| --- | --- |
| `ok` | Feedet bærer poster med titel, tekst og — hvor kanalen kræver det — foto |
| `empty` | Intet at poste: Publer ser ingen ny post |
| `stale` | Nyeste post er ældre end grænsen (7 dage; `sgap_feed_stale_days` kan ændre den) |
| `invalid` | En post kan ikke postes som den står: ingen titel, ingen tekst, eller intet foto på Instagram/Pinterest |
| `off` | API-tilstand er slået til, så der findes ingen feed at bedømme |

Rækken bærer også antal poster, alderen på den nyeste og den feed-adresse Publer får. `health_summary()`
tæller tilstandene og navngiver de kanaler der kræver handling — det er den sætning den natlige kørsel kan
læse, uden at nogen skal åbne et feed i en browser.

## Hvor det kan ses

Ingen ny side, ingen ny tabel, ingen ny indstilling. Felterne `feeds` og `feeds_summary` er lagt ind i de to
flader der allerede findes:

- abilityen `steamgears-autopilot/channel-health` (read-only), som MCP og den natlige kørsel bruger
- `GET /sgap/v1/channel-health` (admin-only)

Så ét kald svarer på begge halvdele: *kan kanalen sende?* (`channels`) og *har dens feed noget brugbart at
sende?* (`feeds`).

## Målt

- `php plugins/tests/smoke.php`: **1 383 PASS / 0 FAIL** (1 358 før P7, altså 25 nye i `plugins/tests/feed-health.php`).
- `php -l` rent på alle 49 PHP-filer; `tools/fejlscan.py` uden fund.
- Zip: `plugins/steamgears-autopilot-v7.69.0.zip`, **439 251 bytes**, `diff -r` mod kilden er tom.

## Hvad det ikke beviser

- **Ingen Publer-kvittering.** Vi ved at feedet er det Publer henter, men ingen konto har hentet det endnu, så
  »postet korrekt« er stadig kun målt indirekte gennem feedets indhold.
- **Ingen live-kørsel.** Testene styrer `$wpdb` og tiden; den rigtige side er ikke kaldt (denne sandkasse har
  hverken MCP-token eller adgang til sitet).
- **`stale` er en tommelfingerregel.** 7 dage passer til det daglige flow; en kanal der med vilje kun poster
  sjældent skal have grænsen hævet med filteret.

## Fælder undervejs

- **Stubben svarer på alle tabeller.** `$wpdb->get_results()` i smoke-suiten returnerer `$GLOBALS['rows']`
  uanset SQL, så `channel-health`'s `ARRAY_A`-forespørgsel fik feed-rækker som `stdClass` og døde på
  `$row['channel']`. Testen tømmer `rows` før den kalder abilityen — og det er værd at huske: en grøn test kan
  skyldes at stubben aldrig blev spurgt.
- **To ure.** Alder regnes mod `current_time('mysql')`, samme ur som køen skriver med, ikke `time()`, så en post
  ikke kan se en dag ældre ud end den er (samme fejlklasse som `gmdate()`-fundet i 7.53.1).
- **Kun konfigurerede kanaler.** En kanal der ikke er slået til har ingen feed; at rapportere den som `empty`
  ville sende ejeren på jagt efter et problem der ikke findes.
