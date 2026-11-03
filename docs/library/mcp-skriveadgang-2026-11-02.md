# MCP-skriveadgang og 7.25.0 — hvad der blev målt 2/11 2026

**Status:** forbindelsen virker, læsning virker, **skrivning er spærret af sitet** — og spærren kan aflæses
direkte i databasen. Rapporten samler det nye viden fra passet: hvorfor REST-ruten svarer 401, hvad AI Engines
«Drafts Only» egentlig blokerer, hvordan man ser den uden at skrive, og hvorfor plugin 7.25.0 var nødvendig, før
Clockwork Punk-serien kan lægges i køen.

## 1. Kort version

| Spørgsmål | Svar målt 2/11 2026 |
| --- | --- |
| Virker OAuth-tokenet? | Ja. `tools/mcp_rest.py --check` → `ok: true`, server `AI Engine - Steamgears`, 206 værktøjer |
| Kan agenten skrive til sitet? | Nej. Alle skrivende kald afvises af AI Engine, før plugin'et ser dem |
| Hvorfor? | `mcp_drafts_only: true` i option `mwai_options` — ikke en fejl, men en switch |
| Kan agenten selv slå den fra? | Nej, og det er med vilje: spærren dækker også `wp_update_option` |
| Kan kampagnen lægges i køen? | Ikke endnu. 12/12 poster er forhåndsvalideret og klar |
| Hvilken plugin-version kører sitet? | **7.22.0** — 7.25.0 ligger på `main`, men er ikke installeret |

## 2. To forskellige flader, to forskellige tokens

Sitet har to måder at nå ind på, og et token gælder kun den ene:

| Flade | Endpoint | Token | Svar med OAuth-tokenet |
| --- | --- | --- | --- |
| AI Engines MCP | `/wp-json/mcp/v1/http` | OAuth (Bearer, 3600 s + refresh) | `200` — 206 værktøjer |
| Pluginets eget REST | `/wp-json/sgap/v1/queue` | WordPress-app-password eller cookie | **`401 Sorry, you are not allowed to do that`** |

Det betyder at den oprindelige plan — `POST /wp-json/sgap/v1/queue` med Bearer — aldrig kunne virke, uanset hvor
gyldigt tokenet var. Den eneste skrivevej er MCP-abilliteten `steamgears-autopilot/queue-post`, som kalder den
samme `SGAP_Queue::add()` og dermed den samme quality-gate.

## 3. Hvad «Drafts Only» blokerer

```
update      → "…lets assistants work on drafts only, so this was not done."
purge-cache → same
queue-post  → same (sent an invalid post, so nothing could be written)
```

Den fulde sætning er »This site lets assistants work on drafts only, so this was not done. It would change the
live site; ask the owner to do it.« — den kommer fra AI Engine, ikke fra plugin'et.

Læsende abilliteter svarer normalt: `status`, `check-post`, `list-queue`, `list-plugins`, `get-settings`.
Spærren er altså ikke pr. værktøj og ikke en scopes-begrænsning i tokenet — den er global.

Testen med en **ugyldig** post er værd at gentage: den beviser at afvisningen kommer fra AI Engine og ikke fra
plugin'ets egen validering, uden at der skrives noget som helst.

## 4. Sådan læses spærren uden at skrive

AI Engines indstillinger ligger i option `mwai_options`. Læsende værktøjer må læse den, og `raw: true` går
udenom object-cachen, så svaret er den faktiske tilstand i databasen og ikke et cachet billede:

```bash
python3 tools/mcp_rest.py --call wp_get_option '{"key":"mwai_options","raw":true}' \
  | grep -o '"mcp_[a-z_]*": [^,}]*'
```

| Nøgle | Målt 2/11 2026 | Betydning |
| --- | --- | --- |
| `mcp_drafts_only` | **true** | spærren: alle skrivende kald afvises |
| `mcp_role` | `admin` | tokenet har administratorkraft — kun Drafts Only holder det tilbage |
| `mcp_approvals` | `off` | ingen godkendelse pr. værktøj |
| `mcp_default_readonly` | false | læse-only er ikke slået til |
| `mcp_dynamic_rest` | true | Dynamic REST er allerede tændt |
| `mcp_abilities` / `module_mcp` | true | Abilities og MCP-modulet kører |
| `mcp_bearer_token` | `••••be6f` | findes, men bruges ikke af MCP-fladen |

**Diagnostisk pointe:** en switch der ikke blev gemt, eller som blev sat på en forkert fane, kan ses her med det
samme. Det er stærkere end at gætte ud fra fejlbeskeder, og det kræver ingen skrivning.

## 5. Hvorfor 7.25.0 var nødvendig

Live-plugin 7.22.0 har denne signatur på `queue-post` (målt via `tools/list`):

```
felter: channel, destination_url, image_url, text, title
        (ingen angle, content_type eller campaign)
```

Den hardcodede `content_type=custom` og `format=post` og tog **ingen** `angle`. Havde kampagnen fået lov at
skrive gennem 7.22.0, var alle 12 poster landet som `angle_a`/`post` — og så havde serien ikke testet tre
vinkler, hvilket er hele pointen med at måle i læringsmatricen.

**7.25.0** giver `queue-post` felterne `angle`, `content_type` og `format` (defaults som før, så ældre kald er
upåvirkede), oveni `campaign` fra 7.24.0. Fire nye smoke-checks, 229 → **233 PASS / 0 FAIL**. Zip'en er
`diff -r` identisk med kilden og ligger på `main` som `plugins/steamgears-autopilot-v7.25.0.zip`.

Indlæseren `tools/load_campaign.py --post` er samtidig skiftet fra REST til MCP-abilliteten — samme
`SGAP_Queue::add()`, men ad den vej der faktisk er åben.

## 6. Forhåndsvalidering af kampagnen (læsende)

`check-post` blev kørt på alle 12 poster mod det live site:

| Resultat | Antal |
| --- | --- |
| Rene (ingen fund) | **12** |
| Advarsler | 0 |
| Blokerede | 0 |

Vinkelfordeling: 4× `angle_a` (atmosfære), 4× `angle_b` (gave/lejlighed), 4× `angle_c` (håndværk). Den tidligere
`unresolved_link`-advarsel på Figurehead-posten er væk. Ingen af de 12 vil altså blive afvist af quality-gaten,
når de først kan skrives.

## 7. Sitet som det stod i øvrigt

| Felt | Værdi |
| --- | --- |
| Plugin-version | 7.22.0 (`latest_on_github` 7.22.0 — cachet før pushet af 7.25.0) |
| Kø | 41 queued / 10 published |
| Nattens generering | 2026-11-03 00:09 — 6 kladder, 6 sat i kø, 0 fejl |
| `publish_mode` | rss (Publer RSS-feeds) |
| Quiet hours | 23:00–07:00 (site-tid 00:38 ved målingen) |
| Dagskvote | 8 pr. dag, batch 5, `can_send_now: 5` |
| Quality gate | `block`, 0 bandeord |
| AI | AIMLAPI, `anthropic/claude-opus-5.5`, nøgle bekræftet 2/11 12:44 |
| Sidste 30 dage | facebook 5 publiceret/24 klik · instagram 3/14 · pinterest 2/9 · 0 ordrer |

## 8. Hvad der stadig mangler, og hvem der gør det

1. **Ejeren:** sæt «Drafts Only» til **fra** i «Meow Apps → AI Engine → Settings → MCP» og tryk **Save**.
   Målingen viser at den ikke blev gemt, og agenten kan ikke gøre det selv.
2. **Agenten, når den er gemt:** kør `steamgears-autopilot_update` (den installerer 7.25.0 uden et
   Plugins-klik), verificér versionen, og kør derefter
   `python3 tools/load_campaign.py docs/library/clockwork-punk-koe-2026-11-02.json --post`.
   Indlæseren springer dubletter over, så kommandoen er sikker at gentage.
3. **Verifikation bagefter:** `steamgears-autopilot_list-queue` skal vise 12 nye poster med
   `campaign: clockwork_punk_2026_11` og de rigtige vinkler, og `status` skal svare `version: 7.25.0`.

Kampagnens 12 poster har faste `scheduled_at`-tidspunkter 3.–14. november kl. 18:30, så de ligger foran
quiet hours. Med 41 poster i køen i forvejen og en dagskvote på 8 kan enkelte poster blive skubbet et døgn.
