# Cirla C7 — fra test til produktion på steamgears.eu (11/11 2026)

C7 i `projektstatus.json` blev kørt mod det **levende** site gennem Novamira-fladen
(`/wp-json/mcp/novamira-oauth`) med fuld MCP-adgang — ikke mod et lokalt WordPress som C6.
Rækkefølgen er dokumentets §9.6: modul slukket → begrænset rolle → slå til → handlinger →
overvågning → slå fra som rollback.

## 1. Udgangstilstanden, målt

| Måling | Værdi |
| --- | --- |
| Plugin på sitet | **7.76.0** (repoet var 7.75.0 — `origin/main` blev hentet og rebaset først) |
| `sgap_cirla_enabled` | `no` |
| Cirla-abilities over MCP | 0 |
| `sgap_cirla_daily` i cron | ikke planlagt |
| Rolle `cirla_operator` | fandtes ikke |
| Bruger `cirla` | fandtes ikke |
| Tabel `…_sgap_ai_drafts` | findes (19 kolonner, fra 7.75.0) |

Slukket betød altså reelt slukket: filen blev ikke indlæst, så hverken ability eller cron kunne
registreres. Det er den samme måling som C6 gjorde lokalt, nu gentaget på det levende site.

## 2. Den begrænsede rolle — og fejlen den afslørede

Rollen blev oprettet som `cirla_operator` med præcis **`read` + `sgap_cirla_run`**, og brugeren
`cirla` fik den. Første måling med pluginnets eget filter aktivt (`SGAP_Cirla::init()`), som det
kører i drift:

| Bruger | `current_user_can('sgap_cirla_run')` | `manage_options` |
| --- | --- | --- |
| `cirla` (rollen `cirla_operator`) | **false** | false |
| administrator (bruger 1) | true | true |

`SGAP_Cirla::map_cap()` returnerede `['manage_options']` for `sgap_cirla_run` **uanset** hvad
brugeren ellers havde, så en rolle med capabilityen direkte blev afvist. Kravet i §9.4.6 —
»redaktør + kun `sgap_cirla_run`« — kunne altså ikke opfyldes: enten fik brugeren hele sitet, eller
også kunne den intet.

**Rettet i 7.79.0:** en direkte tildeling vinder (`['exist']`), og uden den går capabilityen stadig
gennem `manage_options`, så administratorer er uændrede. Målt efter installation:

| Bruger | `sgap_cirla_run` | `manage_options` | `install_plugins` | `edit_users` | `edit_posts` |
| --- | --- | --- | --- | --- | --- |
| `cirla` | **true** | false | false | false | false |

`SGAP_Cirla::status()` og `seo_check()` blev kørt som brugeren og svarede korrekt — den kan altså
køre Cirla og intet andet.

## 3. Den anden fejl: en afvisning blev til en exception

Med det daglige loft sat til 0 svarede `cirla-social-suggestions`:

> `Ability "steamgears-autopilot/cirla-social-suggestions" callback threw an exception:`
> `SGAP_Cirla::social_suggestions(): Return value must be of type array, WP_Error returned`

`seo_check()` og `social_suggestions()` var deklareret `: array`, men returnerer `WP_Error` ved
403/409/429. Klienten fik altså en serverfejl i stedet for den tilsigtede kode — præcis den klasse
fejl 7.71.2 rettede for `WP_Error` uden status, blot i PHP's type-system i stedet for i HTTP-laget.
Begge er nu `array|WP_Error`; efter installation svarer det samme kald en ren 429 med teksten
»The daily Cirla budget is used up (0 of 0 AI calls)«. `cirla-draft` var upåvirket, fordi den ikke
havde en returtype.

## 4. Handlingerne over MCP, målt

| Handling | Resultat |
| --- | --- |
| `update-settings` `{cirla_enabled: yes}` | `changed: cirla_enabled` — den dokumenterede vej virker |
| `cirla-status` | `enabled: true`, fire handlinger tilladte, cron næste 22:25 UTC, loft 0/5 |
| `cirla-seo-check` post 1361 | **200**: titel 59/60, beskrivelse 147 (70–155), `ok: true`, intet skrevet |
| `cirla-draft` uden `topic` | afvist af input-skemaet (`topic` er required) — intet AI-kald |
| `cirla-social-suggestions` `post_id: 0` | afvist af skemaet (`post_id >= 1`) — intet AI-kald |
| `cirla-social-suggestions` med loft 0 | **429** efter rettelsen (før: exception) |
| `cirla-draft` med loft 0 | **429** — loftet stopper kaldet, før der bruges kreditter |
| `discover-abilities` efter tænd | fire abilities: `cirla-status`, `cirla-draft`, `cirla-seo-check`, `cirla-social-suggestions` |

**Ingen AI-kald blev sendt.** Både kladde og forslag kræver et modelkald, og der blev ikke brugt
kreditter: gaten blev målt gennem skema-afvisning og det brugte loft, som begge stopper kaldet før
netværket. Det er den samme begrænsning som C6, nu med vilje.

## 5. Rollback, målt

| Efter `cirla_enabled: no` | Værdi |
| --- | --- |
| `cirla_enabled` | `no` |
| `sgap_cirla_daily` i cron | ikke planlagt (`false`) |
| Cirla-abilities over MCP | **0** — `not found` ved kald |
| Modulens log | 4 rækker bevaret |
| `cirla_actions` / `cirla_daily_calls` | bevares (`status,draft,seo,social` / 5) |

Rollback er altså stadig bare at slå fra, og intet gemt går tabt.

## 6. Udrulningen (7.79.0)

Zip'en blev bygget fra repoet og lagt på serveren med `novamira/create-upload-link` + `PUT`, og
installeret med `novamira/run-wp-cli`:

```
{"args":["plugin","install","<sti>","--force","--activate"]}
```

`args` skal være et array — ellers svarer WP-CLI med hjælpetekst og `exit_code: 0`. Zip'en blev
slettet fra serveren bagefter. **Versionsforløbet er værd at kende:** rettelsen blev først bygget
oven på 7.76.0 og udrullet som 7.78.0; mens arbejdet kørte, pushede en anden kørsel 7.76.1
(REST-datoer, falske boolske felter, retry-vej). 7.78.0 blev derfor genbygget oven på 7.76.1 som
**7.79.0** og rullet ud igen, så sitet ikke står med en version der mangler 7.76.1's rettelser.
Første request efter en installation kan læse den gamle version fra OPcache; næste request svarer
den nye, og filens header på disken stemmer.

## 7. Hvad der ikke er målt

- **24 timers overvågning.** Cron-jobbet blev planlagt og verificeret, men vinduet kan ikke
  observeres i én session. Modulet blev slået fra igen som C7 foreskriver, så der er intet at
  overvåge nu. Skal overvågningen gennemføres, skal modulet tændes igen og stå tændt i 24 timer.
- **Et rigtigt AI-svar.** Ingen kreditter brugt; se afsnit 4.
- **Et MCP-kald som Cirla-brugeren selv.** Rettighederne blev målt i WordPress med brugeren sat som
  aktuel bruger (`wp_set_current_user`), ikke med et separat OAuth-token for den bruger. Det er den
  samme gate (`current_user_can` i `permission_callback`), men ikke den samme transport.

## 8. Efterladt på sitet

- Rollen `cirla_operator` (`read`, `sgap_cirla_run`) og brugeren `cirla` findes stadig. Brugeren har
  en tilfældig adgangskode, som ikke er vist eller gemt nogen steder.
- Modulet er **slukket**; `sgap_cirla_actions`, `sgap_cirla_daily_calls` og loggen er bevaret.
- Plugin på sitet: **7.79.0**, aktiv.
