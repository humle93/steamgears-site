# MCP-auth til steamgears.eu — application password og `ROYAL_MCP_AUTH`

Kort: MCP-forbindelsen til **steamgears.eu** (Royal MCP-plugin) kan ikke bruge en statisk Bearer-token.
Endpointet accepterer enten et **WordPress application password over Basic** eller en **OAuth2 access token
over Bearer**. `.mcp.json` bruger Basic-varianten.

Endpoint: `https://steamgears.eu/wp-json/royal-mcp/v1/mcp` (kanonisk alias: `https://steamgears.eu/mcp`).

## Hvad endpointet accepterer

| Metode | Credential | Svar ved forkert credential |
| --- | --- | --- |
| `Authorization: Basic base64(bruger:nøgle)` | WordPress application password | `invalid_username` fra WP core — credentials går gennem WP's egen brugerauth |
| `Authorization: Bearer <token>` | OAuth2 access token fra royal-mcp | `Invalid or expired access token.` |
| `X-WP-Nonce` + session cookie | Browser-session | kun relevant i browser |

Serverkortet (`https://steamgears.eu/.well-known/mcp/server-cards.json`) angiver `oauth2` og `session-cookie`
som auth-metoder. Der findes ingen rute der udsteder en langlivet API-nøgle, og Bearer accepterer **ikke**
application passwords — derfor Basic.

## 1. Opret et application password

1. Log ind som administrator på <https://steamgears.eu/wp-admin/>.
2. Gå til **Brugere → Profil** (<https://steamgears.eu/wp-admin/profile.php>).
3. Find **Application Passwords**, navngiv nøglen fx `mcp-lokal`, og tryk **Add New Application Password**.
4. Kopiér nøglen **med mellemrum** (format `xxxx xxxx xxxx xxxx xxxx xxxx`) — den vises kun én gang.

Nøglen er knyttet til den bruger du er logget ind som; brugernavnet i næste trin skal være samme login
(ikke visningsnavn eller e-mail).

## 2. Gem credential lokalt — aldrig i repoet

```bash
printf '%s' 'brugernavn:xxxx xxxx xxxx xxxx xxxx xxxx' > ~/.royal_mcp_auth
chmod 600 ~/.royal_mcp_auth
```

Filen ligger uden for repoet og må ikke committes.

## 3. Sæt `ROYAL_MCP_AUTH`

`.mcp.json` forventer headeren `Authorization: Basic ${ROYAL_MCP_AUTH}`, så værdien skal være **base64 af
`brugernavn:nøgle`** — ikke nøglen alene.

```bash
export ROYAL_MCP_AUTH="$(printf '%s' "$(cat ~/.royal_mcp_auth)" | base64 -w0)"
```

Start Claude Code i samme shell, så variablen arves. Skal den være permanent, læg linjen i shell-profilen —
ikke i en fil der ligger i repoet.

## 4. Verificér

```bash
curl -sS -X POST https://steamgears.eu/wp-json/royal-mcp/v1/mcp \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -u "$(cat ~/.royal_mcp_auth)" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verify","version":"1"}}}'
```

Succes: `result` med `serverInfo` og `protocolVersion`. Fejl: `error` med kode `-32600`.

Derefter `claude mcp list` — `steamgears` skal vise ✓ Connected i stedet for *Pending approval*.

## Fejlsøgning

| Symptom | Betydning | Handling |
| --- | --- | --- |
| `invalid_username` | Basic-brugernavnet findes ikke | brug WP-loginnet, ikke visningsnavn/e-mail |
| `Invalid or expired access token` | Bearer brugt med forkert værditype | skift til Basic (dette dokument) eller brug OAuth |
| `Missing environment variables: ROYAL_MCP_AUTH` | variablen er ikke eksporteret i shellen | trin 3 |
| 403 fra sikkerhedsplugin | app passwords blokeret | whitelist i Solid Security/Wordfence, eller brug OAuth |
| `initialize` svarer 200, men ingen værktøjer | brugeren mangler rettigheder | brug en administratorkonto |

## Alternativ: OAuth

Royal MCP foretrækker OAuth2 (`authorization_code` med PKCE S256, dynamisk klientregistrering,
`refresh_token`, scope `mcp:full`). Det kræver en browser med en aktiv WP-admin-session:

```bash
claude mcp add --transport http steamgears-oauth https://steamgears.eu/mcp
```

Kør derefter `claude` og vælg `/mcp` → *Authenticate*. Claude Code registrerer klienten selv og fornyer
tokens automatisk, uden miljøvariabel. Vælg OAuth hvis nøglen skal kunne rotere uden manuelt arbejde; vælg
application password hvis forbindelsen skal køre i miljøer uden browser.

## Sikkerhed

- Et application password giver samme rettigheder som brugeren. Brug en dedikeret administratorkonto til
  MCP frem for den daglige konto.
- Rotering af application passwords står som åbent punkt i
  [`library/sikkerhed-ai-engine-2026-11-01.md`](library/sikkerhed-ai-engine-2026-11-01.md). Tilbagekald
  nøglen i WordPress når den ikke længere bruges.
- Nøglen må ikke stå i `.mcp.json`, i commits, i logs eller i chat — kun i miljøvariablen og i
  `~/.royal_mcp_auth`.
- Har en nøgle været eksponeret: tilbagekald den i WordPress **først**, og opret derefter en ny.

## Referencer

- [`.mcp.json`](../.mcp.json) — `steamgears`-serveren med `Basic ${ROYAL_MCP_AUTH}`.
- [`wp_mcp_stdio.py`](../wp_mcp_stdio.py) — eksisterende stdio-bro der bruger samme Basic-princip.
- [`seo_agent.py`](../seo_agent.py) — `call_royal_mcp()` kalder samme endpoint.
