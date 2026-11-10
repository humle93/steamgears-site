# Kampagne-agenten: drafts med review-gate, aldrig publicering

**Målt 10/11 2026 · Autopilot 7.68.0 · P6 i `projektstatus.json`**

Autopiloten promoverer det der står for tur. Den kan ikke svare på »skriv noget for *den her* side, fordi vi
vil sælge goggles til cosplay inden Halloween« — den daglige forfatter vælger selv kilden ud fra cooldown, og
der findes ingen skrevet hensigt nogen steder i flowet. P6 er svaret: en agent der tager en publiceret side,
et formuleret mål og de kanaler der er sat op, og skriver kladder. Intet mere.

## Afgrænsningen er hele pointen

Agenten må undersøge, skrive og gate-teste. Den må **ikke** publicere, lægge i kø eller kontakte en leverandør.
Derfor:

- Hver kladde gemmes som `pending` i `sgap_ai_drafts`, **også når Full Auto er slået til**. Full Auto ignoreres
  bevidst her; det er en indstilling for det daglige flow, ikke en tilladelse for agenten.
- `SGAP_Queue` og `SGAP_Publer` kaldes aldrig fra `campaign-agent.php`. Godkendelsen er et menneske der trykker
  *Approve & Queue* på AI Drafts.
- Der findes ingen »kør kampagnen nu«-knap der springer reviewet over. Det er ikke en manglende funktion, det er
  kontrakten.

Konsekvensen er, at et fejlskrevet kampagneopslag koster et afvisningsklik — ikke et opslag på Facebook.

## Gate-fundene følger kladden

Preflighten og kvalitetsgaten returnerer fund, men i det daglige flow forsvinder de efter genereringen: de
bruges til at afgøre om noget må køes, ikke til at fortælle hvorfor. For en agent der skriver uden at køre, er
det omvendt — fundene *er* leverancen ved siden af teksten.

Derfor er `gate_report` en ny kolonne på `sgap_ai_drafts`. `SGAP_Campaign_Agent::gate()` samler op til 12 linjer
pr. kanal (preflight-problemer, preflight-noter, kvalitetsgate-problemer), og de vises på AI Drafts i en
`sgap-caveat`-boks over *Approve & Queue*. En advarsel kan altså ikke længere forsvinde mellem generering og
godkendelse.

## Kampagnenavnet følger med hele vejen

`campaign` er den anden nye kolonne. Navnet er det ejeren skriver, eller — hvis feltet er tomt — sidens slug.
Det gemmes på kladden og sendes videre i `SGAP_Queue::from_draft()` til `SGAP_Queue::add()['campaign']`, hvor
`SGAP_UTM::campaign()` allerede brugte det i `/go/`-linket. Resultatet er at GA4 kan skelne
`utm_campaign=halloween_2026` fra den sædvanlige strøm, også når opslaget er skrevet af agenten og godkendt
dage senere. Uden den kolonne ville kampagnen kun findes i ejerens hukommelse.

## Målet former vinklen, ikke fakta

`SGAP_AI::prompt()` får en ekstra linje når `campaign_goal` er sat:

> Campaign goal (shapes the angle; do not state it as a new fact or promise anything the page does not say): …

Den daglige forfatter sætter aldrig `campaign_goal`, så dens prompt er byte-identisk med før — og en test
holder den på det. Formuleringen er valgt fordi den oplagte fejl er at modellen skriver »nu til Halloween« som
om det var en nyhed; målet er en retning for tonen, ikke en kilde.

## Hvad der blev bygget

| Fil | Ændring |
| --- | --- |
| `includes/campaign-agent.php` | **Ny.** `SGAP_Campaign_Agent`: `validate()`, `run()`, `gate()`, `image()`, `pending_exists()`, `waiting()`, `handle()`, `summary()`, `card()`. `ACTION=sgap_campaign`, `MAX_DRAFTS=10`, mål 8–160 tegn |
| `includes/core.php` | To kolonner i `sgap_ai_drafts`-DDL'en; `campaign` sendes i `from_draft()`; AI Drafts vælger og viser `campaign` + `gate_report`; dashboardet kalder `SGAP_Campaign_Agent::card()` i »Now«-kolonnen |
| `includes/generator.php` | `prompt()` tilføjer kampagnemålet når det er sat (ellers uændret) |
| `includes/abilities.php` | Ny ability `campaign-drafts` (`post_id`, `goal`, `channels[]`, `campaign`); `readonly=false`, `idempotent=false` |
| `includes/updater.php` | Repository-link i opdateringskortet (repoet er privat, så linket er til konti med adgang) |
| `steamgears-autopilot.php` | require + `::init()`, version 7.68.0 |
| `plugins/tests/campaign-agent.php` | **Ny.** 42 checks |
| `plugins/tests/smoke.php` | Loader den nye testfil; `get_the_post_thumbnail_url` dækker også kampagnens billede |

Valideringen afviser en side der ikke er publiceret (kampagnen ville linke til ingenting), et mål under 8 tegn,
et mål over 160, og en kanalliste hvor ingen kanal er konfigureret. En blanding af konfigurerede og
ukonfigurerede kanaler skriver kun de konfigurerede — den skriver ikke tavst til en kanal der ikke er sat op.
`pending_exists()` springer en kanal over når samme kampagne allerede har en kladde til review, så to tryk på
knappen ikke giver to kladder pr. kanal.

## Målt

- `php plugins/tests/smoke.php`: **1 358 PASS / 0 FAIL** (1 316 før P6, altså 42 nye).
- `php -l` rent på alle 49 PHP-filer i pluginnet.
- Zip: `plugins/steamgears-autopilot-v7.68.0.zip`, **436 184 bytes**, `diff -r` mod kilden er tom — byte-identisk.
- Præcis én zip i `plugins/` (7.67.1 slettet i samme commit).

## Hvad der ikke er verificeret

- **Ingen WordPress-kørsel.** Formularen, dashboardkortet og AI Drafts-siden er testet som HTML i smoke-suiten,
  ikke set i en rigtig `wp-admin`. DDL-opgraderingen af en *kørende* installation (dbDelta der lægger to kolonner
  på en tabel med rækker) er heller ikke kørt — den kører først ved aktivering/opgradering på sitet.
- **Intet rigtigt AI-kald.** AIMLAPI-nøglen svarer »out of funds« på det levende site, så alle kald i testene er
  stubbet gennem `http_queue`. Det der er bevist er kontrakten (prompt → parse → gem), ikke modellens udkast.
- **Zip'en skal uploades af ejeren** under *Plugins → Tilføj nyt → Upload*, fordi repoet er privat og
  selvopdateringen henter anonymt (se [`repo-privat-2026-11-08.md`](repo-privat-2026-11-08.md)).

## Fælder undervejs

- `get_permalink($post)` og `get_the_post_thumbnail_url($attachment_id, …)` ser ens ud men tager ikke det samme:
  den første vil have et post-objekt eller et ID, den anden skal have **postens** ID, ikke attachment-ID'et.
  `image()` blev derfor `get_the_post_thumbnail_url($post->ID, 'large')` — den korte vej, som `from_draft()` også
  bruger.
- AI Drafts-siden kræver `table_exists()`, og i smoke-suiten betyder det `$wpdb->next`; uden den responderer
  siden med tom-tilstand og testen måler ingenting. Samme mønster som de øvrige sidetests.
- En ability-callback skal kunne kaldes **uden** argumenter (WordPress 7.1 gør det), så `campaign_drafts()` har
  `$input = null` og kaster ikke.
