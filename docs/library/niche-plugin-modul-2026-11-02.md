# Niche-scanneren som plugin-modul — 2026-11-02

**Status: bygget og testet i PHP 8.3, 150 smoke-checks grønne, paritet med Python-versionen bekræftet på kandidatfilen — både som objekter og læst som CSV.** Modulet ligger i [`plugins/steamgears-autopilot/includes/niche.php`](../../plugins/steamgears-autopilot/includes/niche.php) og er med i plugin-zip'en fra **v7.16.0** (CSV-indgangen fra **v7.17.0**).

## Hvorfor det flyttede ind i pluginnet

Broen ([`niche_bridge.py`](../../niche_bridge.py)) regnede rigtigt, men kunne ikke afslutte sit eget arbejde: den kunne ikke skrive i køen, den kunne ikke gemme attribution, og den kunne ikke verificeres mod WordPress uden `WP_USER`/`WP_APP_PASS`. Hvert af de tre huller havde samme årsag — den stod uden for WordPress.

Modulet fjerner mellemleddet. Scoren og draft-rækkerne beregnes nu inde i pluginnet og skrives med `SGAP_Queue::add()`, altså præcis den funktion køsiden, REST-ruten og `queue-post`-ability'en selv bruger. Der er ingen HTTP-rute, ingen nøgle og ingen payload-kontrakt der kan gå i stykker mellem to sprog.

Python-værktøjet bliver liggende som reference og CI: det er den implementering, PHP-versionen er målt imod, og det kan køre uden WordPress.

## Hvad der er porteret, og hvad der er nyt

| | Python (`niche_scan.py` / `niche_bridge.py`) | PHP (`SGAP_Niche`) |
| --- | --- | --- |
| Grænser og vægte | 2.000 / 50.000 listings, 3x / 17x sving, 45/25/20/10 | samme konstanter, samme formler |
| Manglende felt | vægten normaliseres, hullet rapporteres | samme |
| Hårdt nej | > 50.000 listings slår scoren | samme, verificeret ved score 48 |
| Tusindadskillelse | `18.100` afvises | samme regel, samme fejltekst |
| Duplikatnøgle | `sha256(channel\|url\|title)` | samme, låst i test med samme hexværdi |
| Kilder | CSV/JSON-fil | `candidates` som objekter **eller** `csv` — rå tekst eller en fil under uploads |
| Skriver i køen | nej — kun payloads | **ja**, via `SGAP_Queue::add()` som `draft` |
| Attribution | kun i JSON-filen | gemmes pr. queue-id |

## De to abilities

| Ability | Skriver | Gør |
| --- | --- | --- |
| `steamgears-autopilot/score-niches` | nej | Rangerer kandidaterne, viser planen, lister hvad der blev sprunget over og hvorfor |
| `steamgears-autopilot/queue-niches` | ja | Det samme, men de grønne rækker skrives i køen som `draft` og queue-id'erne returneres |

Begge tager `candidates` (array af nicher med `searches`, `listings`, `swing`, `competitor`, `difficulty`, `price`) og `links` (niche → URL). `channels` vælges blandt `SGAP_Publer::CHANNELS`, og `require_image` holder instagram/pinterest ude, når der ikke er et foto.

## CSV-indgang (v7.17.0)

Kandidatfilen skal ikke længere konverteres til objekter først. Begge abilities tager
`csv` som enten rå tekst eller stien til en `.csv` under `wp-content/uploads`, så en
fil der er uploadet i WordPress kan scores uden at forlade sitet.

Læseren er en port af Python-værktøjets, inklusive det der ellers plejer at gå galt:

- separator findes selv (komma, semikolon eller tabulator) ud fra headeren, så Excel-i-DK-filer virker uden omskrivning
- BOM og CRLF tåles (`\u{feff}` fjernes, `\r\n` normaliseres)
- anførselstegn respekteres, så `"goggles, brass"` er ét felt og ikke to
- danske kolonnenavne (`navn`, `søgninger`, `antal_listings`, `konkurrent`, `sværhedsgrad`, `pris`) accepteres side om side med de engelske
- en manglende kolonne fejler med feltnavn **og** de navne der ville have virket, fx `'listings' (listings, antal_listings, konkurrenter)`
- ukendte kolonner ignoreres i stedet for at vælte filen
- en valgfri `url`/`link`/`destination`-kolonne udfylder linklisten, så samme fil både scorer og fortæller hvor opslaget skal pege hen

Stilæsning er begrænset med vilje: kun `.csv`, maks. 1 MB, og filen skal ligge under
uploads — `realpath()` afvises hvis den peger ud af mappen, så `../` eller en sti til
`wp-config.php` ikke kan bruges som læseorakel. Uden for uploads kan CSV-teksten
sendes direkte i stedet.

`csv` og `candidates` er gensidigt udelukkende: begge eller ingen af dem giver en
fejl i stedet for et gæt på hvilken der var ment.

## Attribution uden migration

`sgap_queue` har ingen `niche`- eller `score`-kolonner, og en migration kan ikke verificeres uden WP-adgang. Modulet gemmer derfor `niche`, `score`, `verdict`, `channel`, `fingerprint` og tidspunkt i optionen `sgap_niche_attribution`, nøglet på queue-id, op til 200 rækker. Det er nok til at se hvor en draft kom fra, og det kræver ingen skemamigration.

Skal attribution i selve rækken (fx til rapporter i SQL), er vejen stadig:

```sql
ALTER TABLE wp_sgap_queue
  ADD COLUMN niche VARCHAR(60) NULL AFTER angle,
  ADD COLUMN niche_score DECIMAL(5,1) NULL AFTER niche;
```

Den er **ikke** kørt — den kræver WP-adgang og en genbygget plugin-zip.

## Verifikation

Alt nedenstående er kørt i dette pas med PHP 8.3.32 (statisk CLI-binær, hentet fordi sandboxen ikke har PHP som pakke):

```
php -l plugins/steamgears-autopilot/includes/niche.php      → No syntax errors
php plugins/tests/smoke.php                                  → 130 PASS, ALL PASS
```

Pariteten er målt på den rigtige kandidatfil, ikke på en kopi af tallene:

```
steampunk goggles  python= 80.8/grøn php= 80.8/grøn OK
steampunk corset   python= 61.2/gul  php= 61.2/gul  OK
steampunk top hat  python= 53.6/gul  php= 53.6/gul  OK
steampunk costume  python= 38.9/nej  php= 38.9/nej  OK
```

Samme måling efter CSV-indgangen, hvor PHP selv læser filen med `parse_csv()` i
sted for at få tallene som objekter:

```
steampunk goggles  python= 80.8/grøn php(csv)= 80.8/grøn OK
steampunk corset   python= 61.2/gul  php(csv)= 61.2/gul  OK
steampunk top hat  python= 53.6/gul  php(csv)= 53.6/gul  OK
steampunk costume  python= 38.9/nej  php(csv)= 38.9/nej  OK
CSV-PARITET OK
```

De 19 CSV-checks dækker: fire rækker læst med komma, `url`-kolonne der udfylder linklisten, at `preview` giver samme scorer og samme antal drafts som objekt-indgangen, at kilden rapporteres (`from`, antal rækker, links fra CSV), BOM + semikolon + danske headers, anførtstegn om et felt med komma, manglende kolonne med alias-liste, tom fil, header uden rækker, ukendte kolonner ignoreret, fil under uploads læst, `..` der ikke kan slippe ud af uploads, sti uden for uploads afvist, kun `.csv`, manglende fil navngivet, ability'en med sti, `csv` + `candidates` samtidig afvist, ingen af dem afvist, og den dokumenterede kandidatfil parset uændret til 80,8 / 61,2 / 53,6 / 38,9.

De øvrige checks i [`plugins/tests/smoke.php`](../../plugins/tests/smoke.php) dækker: score- og domparitet, den hårde regel der slår en 48-point-score, normalisering uden sæsonsving, danske feltnavne og konkurrent-alias, afvisning af tusindadskillelse / nul søgninger / sving under 1 / ukendt konkurrent, kun grønne nicher som drafts, tom `body` og `image_url`, `angle: niche`, `content_type: custom`, `format: post`, fingerprint mod `SGAP_Queue::add()`, linknøgler uden hensyn til store bogstaver og mellemrum, manglende URL → sprunget over med grund, `javascript:`-URL afvist, `require_image`, duplikat sammenklappet, `include_yellow`, ukendt kanal afvist, planen med dansk decimal og foto-advarsel, at køen skriver `draft`, at attribution huskes pr. queue-id, at et kø-duplikat rapporteres i stedet for at kaste, og at begge abilities registreres med rigtig readonly-markering.

`plugin-ci.yml` kører allerede `php plugins/tests/smoke.php` på PHP 8.0–8.5, så de checks kører på hvert push uden nye workflow-trin.

## Hvad der stadig ikke er verificeret

- **Intet er kørt i en rigtig WordPress.** Smoke-testen bruger stubbede WP-funktioner og en stub-`$wpdb`; `integration.sh` kører først i CI med MySQL og WP 6.4–7.1. At rækkerne lander som `draft` i den faktiske tabel er derfor stadig en CI- og ejer-verifikation, ikke noget jeg har set.
- **Den installerede plugin er stadig v7.15.1** — v7.18.0 ligger klar som zip og i `main`, men opdateringen kræver WP-adgang.
- **Stilæsningen er kun testet mod en stub.** `wp_upload_dir()` er stubbet i smoke-testen, så det er ikke bevist at stien til uploads på steamgears.eu giver samme svar (fx hvis `basedir` er anderledes end forventet). Det kræver WP-adgang.
- **Ingen rigtig URL er brugt.** Alle testdestinationer er `example.com`; køen er ikke rørt på steamgears.eu.

## Næste skridt

1. Kør `update`-ability'en (eller *Update now* i dashboardet), så v7.18.0 kommer på sitet.
2. Upload kandidatfilen til Media-biblioteket og kald `score-niches` med dens sti — eller med CSV-teksten — og se om planen matcher Python-rapporten på sitets egne data.
3. Kald `queue-niches` for én kanal, godkend rækken på Queue-siden, og først derefter overveje om attribution skal have sine egne kolonner.
