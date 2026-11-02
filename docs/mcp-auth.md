# MCP-adgang — steamgears.eu og Publer

`.mcp.json` har tre servere: to HTTP-servere der kræver hver sin slags credential, og én lokal stdio-server
uden credential.

| Server | Endpoint | Auth i `.mcp.json` | Credential |
| --- | --- | --- | --- |
| `steamgears` | `https://steamgears.eu/wp-json/mcp/v1/http` | `Bearer ${MCP_ACCESS_TOKEN}` | OAuth-token fra `tools/mcp_oauth.py` (ligger uden for repoet) |
| `publer` | `https://mcp.publer.com/` | `Bearer ${PUBLER_API_KEY}` | Publer MCP API key (Business/Enterprise) |
| `steamgears-profil` | `tools/mcp_project_profile.py` (stdio) | ingen | ingen — læser kun repoet |

**Status 2/11 2026, sent: forbindelsen virker.** OAuth-klienten blev godkendt af ejeren, tokenet lever 3600 s med
refresh, og `python3 tools/mcp_rest.py --check` svarer `ok: true` med serveren `AI Engine - Steamgears` og
**206 værktøjer**. Læsning virker hele vejen (`wp_list_plugins`, `list-queue`, `check-post`). Skrivning gør
**ikke**: AI Engine har *Drafts Only* slået til, så `queue-post` afvises med »This site lets assistants work on
drafts only«, før plugin'et ser kaldet. Skal agenten skrive, skal den switch slås fra i
*Meow Apps → AI Engine → Settings → MCP* — det er en beslutning om risikoprofil, ikke en fejl.

Sitet kører **AI Engine (Meow Apps)** som MCP-server. Det er standard MCP over HTTP med `Mcp-Session-Id`, og
det kræver en credential på hvert kald: uden en svarer ruten `401 rest_forbidden`. Den gamle site-side
MCP-server og dens API-nøgle er væk, og stdio-broen er derfor også fjernet — der er ingen nøgle at gemme i
`.env` længere, kun et token der hentes med OAuth og ligger uden for repoet.

## Steamgears (`steamgears`)

### Hvad endpointet svarer (målt 2/11 2026)

| Kald | Svar | Betydning |
| --- | --- | --- |
| `POST /wp-json/mcp/v1/http` (`initialize`) | `401 rest_forbidden` | ruten findes og kræver login — ikke en 404 |
| `GET` samme rute | `401 rest_forbidden` | også læsning kræver credential |
| `OPTIONS` samme rute | `200` | tillader headere `Mcp-Protocol-Version`, `Mcp-Session-Id`, `Accept` — altså Streamable HTTP |
| `POST` uden token | `401 rest_forbidden` + `WWW-Authenticate: Bearer realm="MCP", resource_metadata="…/?rest_route=/mcp/v1/.well-known/oauth-protected-resource"` | serveren fortæller selv hvor metadataet ligger; bemærk `?rest_route=`-formen, som WAF'en lukker igennem |
| `GET /wp-json/mwai/v1/simpleAuthCheck` | `401 rest_forbidden` | AI Engine er aktiv — ruten er **ikke** MCP, se nedenfor |
| `GET /.well-known/oauth-protected-resource` | `200` | `resource` = `/wp-json/mcp/v1/http`, `scopes_supported` = `["mcp"]` |
| `GET /.well-known/oauth-authorization-server` | `200` | `authorize`, `token`, `register`, `revoke`; `authorization_code` + `refresh_token`; `S256` |
| `POST /wp-json/mcp/v1/oauth/register` | `201` | dynamisk klientregistrering virker; svarer `client_id`, intet secret |
| `GET .../oauth/authorize` fra cloud-worktreen | `454` | Simplys WAF blokerer HTML-sider for datacenter-IP'er — gælder ikke JSON-ruterne |
| `POST /wp-json/mcp/mcp-adapter-default-server` | `401 rest_forbidden` | WordPress' egen MCP Adapter er også installeret, se [`library/mcp-adapter-abilities-2026-11-02.md`](library/mcp-adapter-abilities-2026-11-02.md) |

**Fælde: `mwai/v1/simpleAuthCheck` beviser intet om MCP.** Ruten hører til AI Engines **Public API**-modul, har
sin egen bearer-token (`public_api_bearer_token` — et helt andet felt end MCP-token'et), og svarer på `GET` med
`{"success":true,"data":{"type":"email","value":"<admin-email>"}}`, altså **administratorens e-mail**. Den er en
credential-selvtest, ikke en agent-kanal: en token der virker der, virker ikke nødvendigvis på
`/wp-json/mcp/v1/http`. CVE-2026-16954 (rettet i 3.6.4) handlede netop om at den rute lækkede admin-e-mail,
API-nøgler og bearer-tokens til Editor-brugere — slå Public API fra hvis modulet ikke bruges.

**Værktøjsdækning, målt:** de 206 værktøjer inkluderer `wp_list_plugins` og `steamgears-autopilot_*`-familien
(31 værktøjer: `status`, `queue-post`, `check-post`, `list-queue`, `manage-queue`, `publish`, `set-mode` …).
Plugin-listen er altså **tilgængelig her**, selv om den i AI Engines dokumentation hører til Pro-sættet — mål
`tools/list` før du konkluderer noget om licensen.

WAF-bemærkningen er vigtig: `401` på JSON-ruterne er serverens eget svar, mens `454`/`455` er Simplys
bot-regel og kun rammer HTML. Derfor kan OAuth-linket ikke åbnes herfra — det skal åbnes i ejerens browser.

### 1. Forbind med OAuth (den vej der virker)

```bash
python3 tools/mcp_oauth.py --start           # registrerer klient (én gang) og printer godkendelseslinket
# åbn linket i browseren, log ind som administrator, godkend
python3 tools/mcp_oauth.py --finish '<kode>' # eller hele adressen fra adresselinjen
python3 tools/mcp_rest.py --check            # initialize + tools/list
```

- Redirect er `http://127.0.0.1:8765/callback`, som **ikke** har nogen lytter: siden kan ikke indlæses, og
  det er med vilje. Kopier hele adressen fra browserens adresselinje (eller bare `code=`-værdien) og kør
  `--finish` med det samme — koden lever kun kort.
- Klient og tokens ligger i `~/.config/steamgears-mcp/` (`client.json`, `tokens.json`) med mode `600`, uden
  for repoet, så de ikke kan committes ved et uheld. `--status` viser om der er et gyldigt token.
- Access-tokenet er kortlivet, og `refresh_token` fornyer det automatisk: `mcp_rest.py` spørger
  `mcp_oauth.access_token()`, som fornyer når udløbstiden er passeret. `--refresh` gør det manuelt.
- PKCE-verifieren bliver i `client.json` og står aldrig i linket; `--finish` rydder den efter brug.

### 2. Alternativ: application password

`python3 tools/mcp_rest.py --ask` (getpass, ingen echo) gemmer `MCP_USER`/`MCP_APP_PASSWORD` i `.env` med
mode 600 og bruger Basic-auth. Om AI Engines rute accepterer Basic er **ikke målt endnu** — den er en
almindelig WordPress-REST-rute, så chancen er god, men OAuth er den dokumenterede vej. Klienten foretrækker
Bearer og falder kun tilbage til Basic når der ikke er et token.

### 3. Verificér

```bash
python3 tools/mcp_oauth.py --status
python3 tools/mcp_rest.py --check
python3 tools/mcp_rest.py --abilities --diff docs/library/mcp-adapter-abilities.csv
python3 tools/mcp_rest.py --abilities --csv /tmp/abilities-live.csv
```

`--diff` svarer med `mangler_live` og `ikke_i_arket`, så regnearket kan rettes mekanisk i stedet for ved
gennemlæsning. Klassificeringen af skrive-værktøjer er testet i `tools/test_mcp_rest.py` (14 fixtures) og
OAuth-flowet i `tools/test_mcp_oauth.py` (10 fixtures, lokal authorization server).

### Fejlsøgning

| Svar | Betydning | Rettelse |
| --- | --- | --- |
| `401 rest_forbidden` | intet gyldigt token | kør `--start`/`--finish`, eller `--ask` |
| `400 invalid_grant` | koden er brugt, udløbet eller hører til en anden klient | kør `--start` igen og godkend på nyt |
| `400 invalid_request` ved `/register` | redirect-URI eller grant-type afvist | brug `http://127.0.0.1:8765/callback` præcis som klienten registrerer |
| `454` / `455` | Simplys WAF på HTML | åbn linket i en rigtig browser, ikke fra worktreen |
| `404 rest_no_route` | forkert sti | ruten er `/wp-json/mcp/v1/http`, ikke `/mcp` |
| tomt `tools/list` | forbundet, men ingen værktøjer eksponeret | tjek AI Engines MCP-indstillinger i wp-admin |

## Publer (`publer`)

Publer har en officiel MCP-server på `https://mcp.publer.com`. Uden credential svarer den 401 med
`{"code":-32003,"message":"Missing Publer API key. Use ?api_key=YOUR_KEY (or Authorization: Bearer YOUR_KEY)"}`.

**Status: halvt konfigureret.** Header-formatet i `.mcp.json` (`Authorization: Bearer ${PUBLER_API_KEY}`) er
korrekt — det er præcis det format Publer angiver. Det der mangler, er værdien: `PUBLER_API_KEY` findes
hverken i miljøet eller i en `.env` i repoet, så serveren har ingen credential.

Publer tilbyder to måder at forbinde på (hjælpeartikel opdateret 15/10 2026):

| Metode | Kræver | I `.mcp.json` |
| --- | --- | --- |
| **OAuth** | browser + Publer-login; ingen API-nøgle | ingen `headers` — forbind via klientens OAuth-flow |
| **No auth** med API-nøgle | API-nøgle med scopes | `Authorization: Bearer ${PUBLER_API_KEY}`, eller `?api_key=` i URL'en |

MCP kræver **Business- eller Enterprise-planen**.

### Sådan får du nøglen

1. Log ind på Publer → profilmenu → **Settings → AI & Automations → MCP**.
2. Vælg fanen **No auth**, og tryk **Create an API key** (har du en, indsæt den).
3. Navngiv nøglen, fx `steamgears-mcp`. **Workspaces** og **Accounts** er påkrævet; tilføj **Posts** og
   **Media**, hvis agenten skal oprette eller planlægge indlæg.
4. Generér og kopiér nøglen — den vises ikke igen.
5. Eksportér den i shellen, så `.mcp.json` kan læse den:

   ```bash
   export PUBLER_API_KEY='...'
   ```

6. Verificér — `Accept`-headeren **skal** med, ellers svarer serveren 406:

   ```bash
   curl -sS -X POST https://mcp.publer.com/ \
     -H 'Content-Type: application/json' \
     -H 'Accept: application/json, text/event-stream' \
     -H "Authorization: Bearer $PUBLER_API_KEY" \
     -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify","version":"1"}}}'
   ```

   Succes: `result` med `serverInfo`. Manglende nøgle giver `-32003`; en forkert nøgle afvises typisk først
   ved det første værktøjskald, ikke ved `initialize`.

### Pas på: to forskellige Publer-nøgler

- **MCP-nøglen** (Settings → AI & Automations → MCP) bruges mod `mcp.publer.com` med
  `Authorization: Bearer <nøgle>`.
- **REST-nøglen** (Settings → Access & Login → API Keys, Business-plan) bruges af
  `steamgears-autopilot` mod `app.publer.com/api/v1` med `Authorization: Bearer-API <nøgle>`. Den ligger i
  WordPress-options (`sgap_publer_api_key`), ikke i `.mcp.json`, og kan ikke uden videre bruges som MCP-nøgle.

`Bearer-API`-skemaet hører til REST-API'et; mod `mcp.publer.com` forventes `Bearer`.

### Fejlsøgning (Publer)

| Symptom | Betydning | Handling |
| --- | --- | --- |
| `-32003 Missing Publer API key` | ingen credential sendt | sæt `PUBLER_API_KEY` eller brug `?api_key=` |
| `406 Not Acceptable … must accept both application/json and text/event-stream` | `Accept`-header mangler ved manuelle kald | send begge typer; MCP-klienter gør det selv |
| `401` med `www-authenticate: Bearer resource_metadata=…` | serveren tilbyder OAuth | forbind via OAuth i stedet for nøgle |
| Nøglen afvises trods frisk kopi | forkert nøgletype (REST i stedet for MCP) eller manglende scopes | opret nøgle under AI & Automations → MCP, kontrollér Workspaces/Accounts |

## Profil-serveren (`steamgears-profil`)

[`tools/mcp_project_profile.py`](../tools/mcp_project_profile.py) er en lokal **stdio**-server der gør
[`agent-profil.md`](agent-profil.md) og den aktuelle status tilgængelig for enhver bot der forbinder til
repoet — uden site-credential. Den læser kun filer, skriver intet og kalder intet netværk.

| Type | Navn | Indhold |
| --- | --- | --- |
| Værktøj | `project_profile` | hele agentprofilen |
| Værktøj | `project_status` | beregnet procentstatus pr. projekt |
| Værktøj | `roadmap_index` | roadmap for `site` (S0–S4) eller `plugin` (P0–P3) |
| Ressource | `steamgears://profil` | samme profil som Markdown |
| Ressource | `steamgears://status` | samme status som Markdown |

Test uden MCP-klient:

```bash
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | python3 tools/mcp_project_profile.py
```

## Sikkerhed

- Tokenet arver den WP-bruger der godkender OAuth-flowet. Godkend som en dedikeret administratorkonto, ikke
  den daglige konto, og kun med scope `mcp`.
- Godkendelser og aktive tokens bør ryddes op jævnligt i wp-admin (AI Engine), og et token der ikke længere
  bruges skal tilbagekaldes — ikke bare slettes lokalt. Det står som åbent punkt i
  [`library/sikkerhed-ai-engine-2026-11-01.md`](library/sikkerhed-ai-engine-2026-11-01.md).
- Token og refresh-token ligger i `~/.config/steamgears-mcp/` med mode `600`, aldrig i `.mcp.json`, i commits,
  i logs eller i chat. Klienten printer kun længder og serverens svar, aldrig værdien.
- `refresh_token` er den følsomme del: den giver nye access-tokens uden nyt ejerklik. Tilbagekald den i
  wp-admin hvis maskinen har været delt.
- Publer-nøglen hører i `PUBLER_API_KEY`, ikke i URL'en: `?api_key=` lækker nemt gennem logs, historik og
  skærmbilleder. Nøglen giver adgang til workspaces og konti, og scopes afgør hvor meget agenten kan.
- Har en nøgle været eksponeret: tilbagekald den i WordPress **først**, og opret derefter en ny.

## Referencer

- [`.mcp.json`](../.mcp.json) — `steamgears`-serveren med `Bearer ${MCP_ACCESS_TOKEN}`.
- [`tools/mcp_oauth.py`](../tools/mcp_oauth.py) — registrering, PKCE-link, token-udveksling og fornyelse.
  `--status` viser om der er et gyldigt token.
- [`tools/mcp_rest.py`](../tools/mcp_rest.py) — `--check`, `--abilities`, `--diff`, `--csv`, `--call`.
- [`tools/test_mcp_oauth.py`](../tools/test_mcp_oauth.py) og [`tools/test_mcp_rest.py`](../tools/test_mcp_rest.py)
  — 24 fixtures mod lokale efterligninger, ingen credential nødvendig.
- [`seo_agent.py`](../seo_agent.py) — `call_mcp()` sender Bearer fra `MCP_ACCESS_TOKEN` mod samme endpoint.
- [MCP — authorization](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization) —
  OAuth 2.1, PKCE og resource-indikatoren som AI Engine følger.
- [AI Engine](https://meowapps.com/ai-engine/) — plugin'et bag `/wp-json/mcp/v1/`.
- [Publer MCP — officiel opsætning](https://publer.com/help/en/article/how-to-set-up-and-use-publers-mcp-server-14orlq0/) — OAuth og API-nøgle, opdateret 15/10 2026.
- [Publer MCP — integrationsside](https://publer.com/integrations/mcp) — endpoint og OAuth-flow.
