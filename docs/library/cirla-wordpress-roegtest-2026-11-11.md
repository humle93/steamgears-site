# Cirla på et rigtigt WordPress — røgtesten i §9.5 målt

**Målt 11/11 2026 på en rigtig WordPress-installation, ikke i sandkassen.** Modulet blev installeret fra
`plugins/steamgears-autopilot-v7.71.2.zip`, slået til, kørt igennem alle fire handlinger over HTTP og slået
fra igen. Undervejs blev én fejl fundet og rettet: et brugt AI-budget og en gate-blokering blev sendt som
HTTP 500 i stedet for 429 og 422.

## 1. Hvad der blev rejst

| Del | Version / valg | Hvorfor |
| --- | --- | --- |
| WordPress | 7.1.4 | Nyeste kerne; har Abilities API (`wp_register_ability`, `wp_get_abilities`) |
| PHP | 8.3.14 (statisk binær, `.tools/php`) | Samme binær som repoets `php -l` og smoke bruger |
| Database | SQLite via `sqlite-database-integration` 3.1.3 + `wp-content/db.php` | Docker-daemonen i miljøet svarer `permission denied`, så MySQL i container var ikke en vej |
| Webserver | `php -S 127.0.0.1:8899` med router | Rigtige HTTP-kald mod `/wp-json/…`, ikke `wp eval` |
| Auth | Application password for `admin` + `WP_ENVIRONMENT_TYPE=local` | Samme auth-vej som en MCP-klient ville bruge |
| AI | mu-plugin der svarer på `sgap_cirla_generate` | Ingen nøgle findes i miljøet; modulets eget filter er den dokumenterede udskiftningsvej |

Routeren gendanner `PHP_AUTH_USER`/`PHP_AUTH_PW` fra `Authorization: Basic …`, fordi PHPs indbyggede server
ikke gør det — uden det svarer WordPress 401 på hvert eneste application-password-kald.

## 2. Udgangspunktet: slukket er slukket

Målt før modulet blev slået til, med `SGAP_Cirla` slet ikke indlæst:

| Kontrol | Målt |
| --- | --- |
| `class_exists('SGAP_Cirla')` | nej |
| Cirla-abilities blandt de 82 registrerede | 0 |
| `wp_next_scheduled('sgap_cirla_daily')` | false |
| Modul-log | tom |

## 3. Slået til via indstillingen

`update-settings` med `{"settings":{"cirla_enabled":"yes"}}` gav HTTP 200 og `{"changed":{"cirla_enabled":"yes"}}`.
Herefter, på næste request:

| Kontrol | Målt |
| --- | --- |
| Klassen | indlæst |
| Cron | `sgap_cirla_daily` planlagt (næste kørsel 18:56 UTC) |
| Abilities | 4: `cirla-status`, `cirla-draft`, `cirla-seo-check`, `cirla-social-suggestions` |
| Tilladte handlinger | `status,draft,seo,social` |
| Dagligt loft | 5 |

Skiftet virker først på næste request, fordi hovedfilen kun indlæser modulfilen når indstillingen er `yes` —
det er præcis den adfærd §9.4.1 beder om.

## 4. Skemaet

`wp_sgap_ai_drafts` findes med **19 kolonner**, heriblandt `angle`, `gate_report`, `campaign`, `status` og
`ai_model`. `SGAP_Install::activate()` blev kørt igen oven på den kørende installation som
dbDelta-opgradering: ingen fejl, ingen tabte rækker. (Tabellen har ikke `source_id`/`language` — de hører til
kø-tabellen, ikke kladdekøen.)

## 5. De fire handlinger, kaldt over HTTP

| Handling | Metode | Svar | Resultat |
| --- | --- | --- | --- |
| `cirla-status` | GET | 200 | `enabled: true`, cron sat, `ai_calls_today: {used: 0, limit: 5}`, `pending_drafts: 0`, `queues_or_publishes: false` |
| `cirla-seo-check` | GET `?input[post_id]=5` | 200 | titel 24 tegn (grænse 60), beskrivelse 120 tegn (70–155), `ok: true`, `written: false` |
| `cirla-social-suggestions` | POST | 200 | 2 forslag, ét pr. kanal, `queued: false`, `published: false`, intet gemt |
| `cirla-draft` | POST | 200 | kladde #4 gemt som `pending`, `duplicate: false`, `queued: false`, `published: false` |

Read-only-abilities kræver GET og POST afvises med 405 — det er WP's egen håndhævelse af
`readonly`-annotationen, ikke modulets.

## 6. Kvalitetsgaten

Indhold med et link til en **kladde** på samme site (`https://…/?p=6`, status `draft`) blev stoppet:

```
HTTP 422  {"code":"sgap_cirla_gate","message":"The quality gate stopped this draft: \"https://…/?p=6\" points at a post that is draft, not published."}
```

Antallet af kladde-rækker var 3 før og 3 efter: intet blev gemt. Et `http://`-link giver kun en advarsel
(`insecure_link`), fordi gaten vender tilbage før linkopslaget — det er derfor testen skal bruge `https://`
for at ramme blokeringen.

## 7. Det daglige loft

Fem AI-kald blev brugt i træk (social, draft, dublet-forsøg, gate-forsøg og endnu en draft). Kald nummer 6:

```
HTTP 429  {"code":"sgap_cirla_budget","message":"The daily Cirla budget is used up (5 of 5 AI calls). It resets after midnight, site time."}
```

`cirla-status` viste bagefter `used: 5, limit: 5`. Dubletforsøget tæller med i budgettet, fordi
`spend()` kaldes før dublet-tjekket — værd at vide, når man tester.

## 8. Kladdekøen

| Kontrol | Målt |
| --- | --- |
| Rækker med `angle = cirla` | 5, alle `status = pending` |
| Rækker i `wp_sgap_queue` | 0 |
| Rækker i `wp_sgap_publish_log` | 0 |
| Kanaler | facebook, instagram, pinterest |

Intet blev køet eller publiceret af modulet.

## 9. Rettigheder

En subscriber med eget application password blev afvist på `cirla-status`:

```
HTTP 403  {"code":"rest_ability_cannot_execute","message":"Sorry, you are not allowed to execute this ability."}
```

`sgap_cirla_run` mapper til `manage_options`, så det er den forventede afvisning.

## 10. Slået fra igen

`update-settings` med `cirla_enabled = no` → option `no`, klassen væk, cron ryddet, 0 cirla-abilities,
ability-ruten svarer **404**, og alle 5 kladder står urørt. Modul-loggen skriver «the module was switched
off; existing drafts are kept».

## 11. Fejlen der blev fundet — og rettet i 7.71.2

Før rettelsen svarede både et brugt budget og en gate-blokering **HTTP 500**, fordi modulets `WP_Error` ikke
bar en status. Resten af plugin'et gør det 44 steder (`['status' => …]`), så Cirla var undtagelsen. Nu bærer
alle 13 `WP_Error` i modulet en status: 429 budget, 422 gate, 409 slukket modul, 403 manglende rettighed,
400 dårligt input, 404 ukendt indhold, 500 gemmefejl. Fire nye smoke-checks låser koderne fast
(1 471 → 1 475 PASS, 0 FAIL).

## 12. Hvad der ikke er målt

- **AI-kaldet selv**: svaret kom fra `sgap_cirla_generate`-filteret. Intet netværkskald gik til en
  AI-udbyder, og der findes ingen nøgle i miljøet. Kanalvalg, kvalitetsgate, budget, kladde, log og
  statuskoder er derimod modulets egen kode.
- **Rigtig MySQL**: databasen er SQLite-driveren. SQL-dialekten i modulets forespørgsler er simpel
  (`INSERT`, `SELECT COUNT`, `SELECT id`), men MariaDB er ikke prøvet her.
- **MCP-klient**: abilities blev kaldt over WordPress' egen `wp-abilities/v1`-REST med application password,
  ikke gennem en MCP-forbindelse til sitet.
- **dbDelta fra en ældre version**: skemaet blev kørt på en frisk installation, ikke som opgradering fra
  7.70.x med data i tabellerne.
- **24 timers overvågning og Cirla-bruger med begrænset rolle** (C7 i `projektstatus.json`).

## 13. Næste skridt mod C7

Installer 7.71.2 på steamgears.eu med modulet slukket, opret Cirla-brugeren med begrænset rolle og kun
`sgap_cirla_run`, slå modulet til via `update-settings`, kør de fire handlinger over MCP mod det levende site
og overvåg i 24 timer. Rollback er stadig bare at slå modulet fra.
