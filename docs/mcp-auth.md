# MCP-auth i `.mcp.json` — steamgears.eu og Publer

`.mcp.json` har tre servere: to HTTP-servere der kræver hver sin slags credential, og én lokal stdio-server
uden credential.

| Server | Endpoint | Auth i `.mcp.json` | Credential |
| --- | --- | --- | --- |
| `steamgears` | `https://steamgears.eu/mcp` | `Bearer ${ROYAL_MCP_TOKEN}` | Royal MCP API-nøgle (32 hex, laves i wp-admin) |
| `publer` | `https://mcp.publer.com/` | `Bearer ${PUBLER_API_KEY}` | Publer MCP API key (Business/Enterprise) |
| `steamgears-profil` | `tools/mcp_project_profile.py` (stdio) | ingen | ingen — læser kun repoet |

**Status 2/11 2026:** `steamgears`-nøglen ligger i `.env` som `ROYAL_MCP_TOKEN`. Den virkede om formiddagen —
`python3 wp_mcp_stdio.py --check` gav `ok: true`, server 1.5.13 og **270 værktøjer** — men **sent 2/11 blev den
afvist**: `401 {"code":-32600,"message":"Invalid or expired access token."}` på hvert kald, også med
browser-UA og på en frisk session. Nøglen skal altså roteres (wp-admin → Royal MCP → Settings → ny API-nøgle
→ ind i `.env` som `ROYAL_MCP_TOKEN`), før nogen MCP-verifikation kan køre. At sitet svarer `454` på
forskiden til curl med browser-UA er bot-reglen fra Simply og har intet med nøglen at gøre — `/mcp` svarer
korrekt `401` med JSON-RPC-fejlen. `PUBLER_API_KEY` er stadig usat, så `publer` står som *Pending approval*.
`steamgears-profil` virker uden credential; den er verificeret med et rigtigt `initialize` + `tools/list`-håndtryk.

> **Navnet er `ROYAL_MCP_TOKEN`.** Broen (`wp_mcp_stdio.py`) og `.mcp.json` bruger begge dette navn, og
> broen læser desuden det ældre `ROYAL_MCP_API_KEY`, hvis nogen foretrækker det. Før 2/11 2026 ledte broen
> **kun** efter `ROYAL_MCP_API_KEY`, mens `.env` havde `ROYAL_MCP_TOKEN` — resultatet var 401 på hver ny
> session, selv om nøglen lå i repoet, og nogen måtte ind og eksportere den manuelt hver gang. Det er rettet:
> nøglen læses nu fra `.env` automatisk, så der ikke skal eksporteres noget pr. session.

> > **Ny nøgle uden at den rammer historikken:** `python3 tools/set_mcp_key.py`. Nøglen læses med `getpass`
> (ingen echo, ingen shell-historik), den gamle `ROYAL_MCP_TOKEN`/`ROYAL_MCP_API_KEY` fjernes så broen ikke kan
> bruge en forældet linje, filen får mode 600, og broens `--check` køres bagefter. Scriptet printer kun nøglens
> **længde**, aldrig værdien — og afbryder uden at røre `.env`, hvis input er tomt.
>
> **Uden terminal:** `python3 tools/set_mcp_key.py --from-file mcp_key.txt`. Læg nøglen i den gitignored fil
> `mcp_key.txt` i roden (fx via editor-panelet, som ikke skjuler dotfiler), kør scriptet, og det sletter selv
> kildefilen igen. Det er vejen når `.env` er skjult i fillister og terminalen ikke er tilgængelig.

**Rettelse 2/11 2026:** denne fil påstod tidligere at Royal MCP kunne bruges med et WordPress
> application password over Basic. Det er **forkert** for MCP-ruten — målt på sitets eget endpoint, se
> tabellen i afsnittet om `steamgears`. Den headless-venlige vej er en **API-nøgle sendt som Bearer**.

## Steamgears (`steamgears`)

Endpoint: `https://steamgears.eu/mcp` (kanonisk, jf. sitets serverkort).
Alias: `https://steamgears.eu/wp-json/royal-mcp/v1/mcp` — samme server, samme svar.

Sitet kører **Royal MCP 1.5.14** (Free) med **270 værktøjer** og tilbyder tre credential-typer:

| Vej | Credential | Headless? | Bruges til |
| --- | --- | --- | --- |
| **API-nøgle** (anbefalet her) | `Authorization: Bearer <32 hex>` | ✅ | MCP-ruten; er lavet til CI og scripts |
| OAuth2 (`authorization_code` + PKCE S256) | `Authorization: Bearer <access token>` | ❌ kræver browser til consent | Claude/ChatGPT-klienter; 24 t token + refresh |
| Application password (Basic) | `Authorization: Basic <base64(bruger:nøgle)>` | ✅ | kun `wp-abilities`-ruten, **ikke** MCP |

### Hvad endpointet faktisk svarer (målt 2/11 2026)

| Kald | Svar |
| --- | --- |
| `initialize` uden credential, begge ruter | `401` · `{"code":-32600,"message":"Authentication required. Use Authorization: Bearer <token>."}` |
| `Bearer` med ugyldig nøgle | `401` · `{"code":-32600,"message":"Invalid or expired access token."}` |
| `Basic` med vilkårligt `bruger:nøgle` | `401` · `{"code":"invalid_username"}` — WP core prøver brugerlogin, men Royal MCP accepterer ikke Basic som MCP-credential |
| `GET /wp-json/wp-abilities/v1/abilities` uden credential | `401` · `rest_forbidden` |
| `GET /wp-json/mcp/mcp-oauth-server` | `401` · `mcp_unauthorized` |
| `GET /wp-json/wc/store/v1/products?per_page=1` | `200` — offentlig læsning virker uden credential |

Offentligt uden credential: `.well-known/mcp/server-card.json`, `.well-known/agent-skills/index.json`,
`.well-known/oauth-authorization-server`, `.well-known/oauth-protected-resource` og `wp-json`-ruteindekset.
**Ingen værktøjskald er anonyme**, og der findes ingen `client_credentials`- eller device-code-grant —
metadataet annoncerer kun `authorization_code` og `refresh_token`.

### 1. Lav API-nøglen

1. Log ind som administrator på <https://steamgears.eu/wp-admin/>.
2. Gå til **Royal MCP → Settings** og opret/vis API-nøglen (32 hex-tegn).
3. Kopiér den med det samme — den vises kun én gang og i højst 15 minutter.

Nøglen er knyttet til den WP-bruger der oprettede den, og der er **én pr. site**. Den kan roteres i wp-admin
eller med `wp royal-mcp rotate-api-key`, og plugin-versionen kan slå den helt fra på Settings-skærmen.
(2/11 2026 svarer sitet med **1.5.13** — denne fil nævnte tidligere 1.5.14.)

### 2. Gem nøglen ét sted — helst `.env`

Repoets `.env` er gitignored (`.gitignore` linje 2-3) og læses automatisk af broen og af `.mcp.json`, når
variablen er eksporteret. Det er den ene plads, nøglen skal stå:

```bash
# i .env — dette repo bruger dette navn
ROYAL_MCP_TOKEN=din-32-hex-noegle
```

Alternativt uden for repoet, hvis `.env` ikke må indeholde den:

```bash
printf '%s' 'din-32-hex-noegle' > ~/.royal_mcp_key
chmod 600 ~/.royal_mcp_key
```

Broen læser `ROYAL_MCP_TOKEN`/`ROYAL_MCP_API_KEY` fra miljøet, derefter `.env` i repoets rod, derefter
`~/.royal_mcp_key` — så en session der starter uden eksporterede variabler virker alligevel.

### 3. Giv MCP-klienten nøglen

`.mcp.json` sender `Authorization: Bearer ${ROYAL_MCP_TOKEN}` — altså nøglen **rå**, ikke base64. En
MCP-klient kan ikke selv læse `.env`, så variablen skal være eksporteret i den shell klienten startes fra:

```bash
export ROYAL_MCP_TOKEN="$(grep -m1 '^ROYAL_MCP_TOKEN=' .env | cut -d= -f2-)"
```

Til scripts og stdio-broen er det ikke nødvendigt: `wp_mcp_stdio.py` læser `.env` selv, så der skal intet
eksporteres pr. session.

### 4. Verificér

```bash
curl -sS -X POST https://steamgears.eu/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H "Authorization: Bearer $ROYAL_MCP_API_KEY" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify","version":"1"}}}'
```

Succes: `result` med `serverInfo`, og `Mcp-Session-Id` i svarets header. Session-id'en skal sendes med på alle
efterfølgende kald i samme session.

Derefter `claude mcp list` — `steamgears` skal vise ✓ Connected i stedet for *Pending approval*. Uden browser
kan samme forbindelse bruges gennem stdio-broen: `printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | python3 wp_mcp_stdio.py`
(læser nøglen fra `ROYAL_MCP_API_KEY` eller `~/.royal_mcp_key`).

### Fejlsøgning (steamgears)

| Symptom | Betydning | Handling |
| --- | --- | --- |
| `invalid_username` | du sendte Basic — MCP-ruten tager ikke app passwords | send `Authorization: Bearer <API-nøgle>` |
| `Invalid or expired access token` | nøglen er forkert, roteret eller slået fra | lav en ny på Royal MCP → Settings, og sæt `ROYAL_MCP_API_KEY` |
| `Missing environment variables: ROYAL_MCP_TOKEN` | variablen er ikke eksporteret i shellen (gælder MCP-klienter; broen læser `.env` selv) | trin 3 |
| `406 Not Acceptable` | `Accept: application/json, text/event-stream` mangler | send begge typer (broen gør det selv) |
| 401 på alle kald trods frisk nøgle | nøglen er slået fra i 1.5.14+, eller brugeren er ikke administrator | tjek Settings og brugerens rolle |
| `initialize` svarer 200, men ingen værktøjer | session-id mangler på efterfølgende kald | send `Mcp-Session-Id` med |
| nøglen er opdateret, men svaret er stadig 401 | `.env` i **denne** worktree er ikke ændret | tjek filens mtime — en cloud-worktree har sin egen gitignored `.env`-kopi, så en rettelse på ejerens maskine rammer ikke VM'en |
| ny nøgle virker ikke, gammel ligger stadig i filen | broen bruger den **første** match i `.env` | erstat linjen, eller slet den gamle — læg ikke den nye under den gamle |

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

> **Målt 2/11 2026 — OAuth kan ikke køres fra cloud-worktreen.** `/mcp` er whitelistet i Simplys WAF, men
> resten af flowet er det ikke: `.well-known/oauth-authorization-server`, `/register` og `/token` svarer alle
> `454` med en JS-browser-check, som et script ikke kan løse. Dertil kræver consent en browser med en aktiv
> WP-admin-session, og callbacken skal lande på en adresse agenten kan læse. Kør OAuth på ejerens egen maskine
> (`claude mcp add --transport http steamgears-oauth https://steamgears.eu/mcp` → `/mcp` → *Authenticate*) — den
> token bliver liggende der, ikke her. Til cloud-worktreen er API-nøglen i `.env` den eneste vej, fordi `/mcp`
> er den ene rute WAF'en lukker igennem. En sjælden `454` på `/mcp` efter mange kald i træk er rate limiting og
> forsvinder igen af sig selv (målt: væk efter 60 s).

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

- API-nøglen er **én pr. site** og arver den WP-bruger der oprettede den. Lav den fra en dedikeret
  administratorkonto, ikke den daglige konto.
- Rotering af nøgler står som åbent punkt i
  [`library/sikkerhed-ai-engine-2026-11-01.md`](library/sikkerhed-ai-engine-2026-11-01.md). Rotér i wp-admin
  (eller `wp royal-mcp rotate-api-key`) når nøglen ikke længere bruges; 1.5.14+ kan slå den helt fra.
- Nøglen må ikke stå i `.mcp.json`, i commits, i logs eller i chat — kun i miljøvariablen, i `.env`
  (gitignored) eller i `~/.royal_mcp_key` uden for repoet.
- Broen (`wp_mcp_stdio.py`) printer aldrig nøglen: fejl citeres som `HTTP 401` uden header-indhold.
- Publer-nøglen hører i `PUBLER_API_KEY`, ikke i URL'en: `?api_key=` lækker nemt gennem logs, historik og
  skærmbilleder. Nøglen giver adgang til workspaces og konti, og scopes afgør hvor meget agenten kan.
- Har en nøgle været eksponeret: tilbagekald den i WordPress **først**, og opret derefter en ny.

## Referencer

- [`.mcp.json`](../.mcp.json) — `steamgears`-serveren med `Bearer ${ROYAL_MCP_API_KEY}`.
- [`wp_mcp_stdio.py`](../wp_mcp_stdio.py) — stdio-bro til samme endpoint, med Bearer-nøgle og `Mcp-Session-Id`.
  `--check` laver `initialize` + `tools/list` og skriver ét JSON-svar på stdout.
- [`seo_agent.py`](../seo_agent.py) — `call_royal_mcp()` sender stadig **Basic** og får derfor `401` på
  MCP-ruten. Brug `wp_mcp_stdio.py` i stedet, eller skift funktionen til Bearer-nøglen.
- [Royal MCP — API-nøgler](https://royalplugins.com/support/royal-mcp/api-keys/) — nøgleformat og rotation.
- [Royal MCP — threat model](https://royalplugins.com/support/royal-mcp/direct-connect-threat-model/) — hvorfor der ikke findes anonym adgang.
- [Royal MCP på WordPress.org](https://wordpress.org/plugins/royal-mcp/advanced/) — version og Free/Pro-fordeling.
- [`seo_agent.py`](../seo_agent.py) — `call_royal_mcp()` kalder samme endpoint.
- [Publer MCP — officiel opsætning](https://publer.com/help/en/article/how-to-set-up-and-use-publers-mcp-server-14orlq0/) — OAuth og API-nøgle, opdateret 15/10 2026.
- [Publer MCP — integrationsside](https://publer.com/integrations/mcp) — endpoint og OAuth-flow.
