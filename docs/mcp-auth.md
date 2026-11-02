# MCP-auth i `.mcp.json` — steamgears.eu og Publer

`.mcp.json` har tre servere: to HTTP-servere der kræver hver sin slags credential, og én lokal stdio-server
uden credential.

| Server | Endpoint | Auth i `.mcp.json` | Credential |
| --- | --- | --- | --- |
| `steamgears` | `https://steamgears.eu/wp-json/royal-mcp/v1/mcp` | `Basic ${ROYAL_MCP_AUTH}` | WordPress application password (base64) |
| `publer` | `https://mcp.publer.com/` | `Bearer ${PUBLER_API_KEY}` | Publer MCP API key (Business/Enterprise) |
| `steamgears-profil` | `tools/mcp_project_profile.py` (stdio) | ingen | ingen — læser kun repoet |

Begge variabler er usatte i dag, så `steamgears` og `publer` står som *Pending approval* indtil de er
konfigureret. `steamgears-profil` virker uden credential og er den eneste MCP-kilde der svarer i dag.

## Steamgears (`steamgears`)

Royal MCP-plugin kan ikke bruge en statisk Bearer-token. Endpointet accepterer enten et **WordPress
application password over Basic** eller en **OAuth2 access token over Bearer**. `.mcp.json` bruger
Basic-varianten.

Endpoint: `https://steamgears.eu/wp-json/royal-mcp/v1/mcp` (kanonisk alias: `https://steamgears.eu/mcp`).

### Hvad endpointet accepterer

| Metode | Credential | Svar ved forkert credential |
| --- | --- | --- |
| `Authorization: Basic base64(bruger:nøgle)` | WordPress application password | `invalid_username` fra WP core — credentials går gennem WP's egen brugerauth |
| `Authorization: Bearer <token>` | OAuth2 access token fra royal-mcp | `Invalid or expired access token.` |
| `X-WP-Nonce` + session cookie | Browser-session | kun relevant i browser |

Serverkortet (`https://steamgears.eu/.well-known/mcp/server-cards.json`) angiver `oauth2` og `session-cookie`
som auth-metoder. Der findes ingen rute der udsteder en langlivet API-nøgle, og Bearer accepterer **ikke**
application passwords — derfor Basic.

### 1. Opret et application password

1. Log ind som administrator på <https://steamgears.eu/wp-admin/>.
2. Gå til **Brugere → Profil** (<https://steamgears.eu/wp-admin/profile.php>).
3. Find **Application Passwords**, navngiv nøglen fx `mcp-lokal`, og tryk **Add New Application Password**.
4. Kopiér nøglen **med mellemrum** (format `xxxx xxxx xxxx xxxx xxxx xxxx`) — den vises kun én gang.

Nøglen er knyttet til den bruger du er logget ind som; brugernavnet i næste trin skal være samme login
(ikke visningsnavn eller e-mail).

### 2. Gem credential lokalt — aldrig i repoet

```bash
printf '%s' 'brugernavn:xxxx xxxx xxxx xxxx xxxx xxxx' > ~/.royal_mcp_auth
chmod 600 ~/.royal_mcp_auth
```

Filen ligger uden for repoet og må ikke committes.

### 3. Sæt `ROYAL_MCP_AUTH`

`.mcp.json` forventer headeren `Authorization: Basic ${ROYAL_MCP_AUTH}`, så værdien skal være **base64 af
`brugernavn:nøgle`** — ikke nøglen alene.

```bash
export ROYAL_MCP_AUTH="$(printf '%s' "$(cat ~/.royal_mcp_auth)" | base64 -w0)"
```

Start Claude Code i samme shell, så variablen arves. Skal den være permanent, læg linjen i shell-profilen —
ikke i en fil der ligger i repoet.

### 4. Verificér

```bash
curl -sS -X POST https://steamgears.eu/wp-json/royal-mcp/v1/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -u "$(cat ~/.royal_mcp_auth)" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify","version":"1"}}}'
```

Succes: `result` med `serverInfo` og `protocolVersion`. Fejl: `error` med kode `-32600`.

Derefter `claude mcp list` — `steamgears` skal vise ✓ Connected i stedet for *Pending approval*.

### Fejlsøgning (steamgears)

| Symptom | Betydning | Handling |
| --- | --- | --- |
| `invalid_username` | Basic-brugernavnet findes ikke | brug WP-loginnet, ikke visningsnavn/e-mail |
| `Invalid or expired access token` | Bearer brugt med forkert værditype | skift til Basic (dette dokument) eller brug OAuth |
| `Missing environment variables: ROYAL_MCP_AUTH` | variablen er ikke eksporteret i shellen | trin 3 |
| 403 fra sikkerhedsplugin | app passwords blokeret | whitelist i Solid Security/Wordfence, eller brug OAuth |
| `initialize` svarer 200, men ingen værktøjer | brugeren mangler rettigheder | brug en administratorkonto |

### OAuth uden browser på maskinen (verificeret 2/11 2026)

Royal MCP kan også bruges fra et miljø uden browser, hvis callbacken kan lande et sted, agenten kan læse.
Fremgangsmåden er afprøvet mod steamgears.eu og gav et Bearer-token på 24 timer med refresh-token:

1. **Læs metadata** på `https://steamgears.eu/.well-known/oauth-authorization-server`. Serveren tilbyder kun
   `authorization_code` med PKCE S256 — der findes **intet** device-code-flow — men dynamisk klientregistrering
   (`registration_endpoint`) er understøttet.
2. **Registrér en klient** med `POST https://steamgears.eu/register` og en `redirect_uris`, der peger på en
   adresse agenten selv kan læse. Svar `201` med `client_id` (`rmcp_…`).
3. **Start en callback-modtager** på den port, der er eksponeret til ejerens browser, og verificér at den svarer.
4. **Send ejeren til** `https://steamgears.eu/authorize?response_type=code&client_id=…&redirect_uri=…&scope=mcp:full&code_challenge=…&code_challenge_method=S256&state=…`.
   Uden session svarer ruten `302` til `wp-login.php` — det er forventet og betyder, at klient og redirect er
   accepteret.
5. **Veksl koden** på `POST https://steamgears.eu/token` som form-data med `grant_type=authorization_code`,
   `code`, `redirect_uri`, `client_id` og `code_verifier`. Svar `200` med `access_token`, `expires_in` og
   `refresh_token`.
6. **Kald MCP** på `https://steamgears.eu/wp-json/royal-mcp/v1/mcp` med `Authorization: Bearer <token>`,
   `Accept: application/json, text/event-stream` og `Mcp-Session-Id` fra `initialize`-svaret.

To fælder undervejs: `code_verifier` skal være den oprindelige streng, ikke challenge-værdien, og
`redirect_uri` skal være byte-identisk med den registrerede. Token og kode hører i en fil uden for repoet med
`chmod 600` — aldrig i chat, log eller commit.

### Alternativ: OAuth

Royal MCP foretrækker OAuth2 (`authorization_code` med PKCE S256, dynamisk klientregistrering,
`refresh_token`, scope `mcp:full`). Det kræver en browser med en aktiv WP-admin-session:

```bash
claude mcp add --transport http steamgears-oauth https://steamgears.eu/mcp
```

Kør derefter `claude` og vælg `/mcp` → *Authenticate*. Claude Code registrerer klienten selv og fornyer
tokens automatisk, uden miljøvariabel. Vælg OAuth hvis nøglen skal kunne rotere uden manuelt arbejde; vælg
application password hvis forbindelsen skal køre i miljøer uden browser.

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

- Et application password giver samme rettigheder som brugeren. Brug en dedikeret administratorkonto til
  MCP frem for den daglige konto.
- Rotering af application passwords står som åbent punkt i
  [`library/sikkerhed-ai-engine-2026-11-01.md`](library/sikkerhed-ai-engine-2026-11-01.md). Tilbagekald
  nøglen i WordPress når den ikke længere bruges.
- Nøglen må ikke stå i `.mcp.json`, i commits, i logs eller i chat — kun i miljøvariablen og i
  `~/.royal_mcp_auth`.
- Publer-nøglen hører i `PUBLER_API_KEY`, ikke i URL'en: `?api_key=` lækker nemt gennem logs, historik og
  skærmbilleder. Nøglen giver adgang til workspaces og konti, og scopes afgør hvor meget agenten kan.
- Har en nøgle været eksponeret: tilbagekald den i WordPress **først**, og opret derefter en ny.

## Referencer

- [`.mcp.json`](../.mcp.json) — `steamgears`-serveren med `Basic ${ROYAL_MCP_AUTH}`.
- [`wp_mcp_stdio.py`](../wp_mcp_stdio.py) — eksisterende stdio-bro der bruger samme Basic-princip.
- [`seo_agent.py`](../seo_agent.py) — `call_royal_mcp()` kalder samme endpoint.
- [Publer MCP — officiel opsætning](https://publer.com/help/en/article/how-to-set-up-and-use-publers-mcp-server-14orlq0/) — OAuth og API-nøgle, opdateret 15/10 2026.
- [Publer MCP — integrationsside](https://publer.com/integrations/mcp) — endpoint og OAuth-flow.
