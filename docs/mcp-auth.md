# MCP-auth i `.mcp.json` — steamgears.eu, Publer og Simply.com

`.mcp.json` har fire servere: tre HTTP-servere og én lokal stdio-server. To af HTTP-serverne kræver hver sin
credential i en miljøvariabel, den tredje logger ind med OAuth i klienten, og stdio-serveren kræver intet.

| Server | Endpoint | Auth i `.mcp.json` | Credential |
| --- | --- | --- | --- |
| `steamgears` | `https://steamgears.eu/mcp` | `Bearer ${ROYAL_MCP_TOKEN}` | Royal MCP API-nøgle (32 hex, laves i wp-admin) |
| `publer` | `https://mcp.publer.com/` | `Bearer ${PUBLER_API_KEY}` | Publer MCP API key (Business/Enterprise) |
| `steamgears-profil` | `tools/mcp_project_profile.py` (stdio) | ingen | ingen — læser kun repoet |
| `simplycom` | `https://mcp.simply.com/v2` | ingen headers — OAuth 2.1 i klienten | Simply.com-konto (browser-login) |

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

> **Rettelse 2/11 2026:** denne fil påstod tidligere at Royal MCP kunne bruges med et WordPress
> application password over Basic. Det er **forkert** for MCP-ruten — målt på sitets eget endpoint, se
> tabellen i afsnittet om `steamgears`. Den headless-venlige vej er en **API-nøgle sendt som Bearer**.

## Måling 2/11 2026 (sen aften) — `mcp-adapter` installeret, Royal MCP-ruterne væk

Ejeren installerede `mcp-adapter` fra `https://github.com/WordPress/mcp-adapter/releases/latest/download/mcp-adapter.zip`
(`wp plugin install … --activate`) og bad om at få det verificeret. Målt fra denne container, neutral curl-UA,
lige efter installationen:

| Kald | Svar |
| --- | --- |
| `GET /wp-json/` | `200` — 42 namespaces; **`mcp` og `wp-abilities/v1` er der, `royal-mcp/v1` er ikke** |
| `POST /wp-json/mcp/mcp-adapter-default-server` (`initialize`, intet credential) | `401` · `{"code":"rest_forbidden"}` |
| `GET /wp-json/mcp/mcp-oauth-server` | `401` · `mcp_unauthorized` — uændret fra i formiddags, altså ikke adapterens |
| `POST /mcp` og `POST /wp-json/royal-mcp/v1/mcp` | **`404` `rest_no_route`** |
| `GET /.well-known/mcp/server-card.json` | **`404`** |
| `GET /.well-known/agent-skills/index.json` | **`404`** |
| `GET /.well-known/oauth-protected-resource` og `…/oauth-authorization-server` | **`301` → `https://steamgears.eu/`** |
| `GET /.well-known/oauth-authorization-server/<vilkårlig sti>` | **`500`** — WordPress' fatale fejlside (»There has been a critical error on this website«) |
| `GET /wp-json/wp-abilities/v1` | `200` (ruteindeks); `…/abilities` uden credential `401` |

Hvad det betyder:

- **Adapteren svarer og kræver credential.** Den registrerer præcis én HTTP-rute, `/wp-json/mcp/<server-id>`
  (`includes/Transport/HttpTransport.php`), og svarer `401 rest_forbidden` uden login. Intet er anonymt.
- **Adapteren har ingen OAuth og ingen `.well-known`-ruter.** Release-zip'en (0.7.1) indeholder hverken
  `well-known` eller et token-endpoint; `mcp-oauth-server` fandtes også før installationen. Med kun adapteren
  er credential-vejen WordPress' egen REST-auth — application password over Basic eller cookie + nonce —
  ikke Bearer-OAuth.
- **Adapteren arver ikke Royal MCP's værktøjer.** Den eksponerer Abilities API gennem tre meta-værktøjer
  (`mcp-adapter/discover-abilities`, `get-ability-info`, `execute-ability`), og kun abilities med
  `meta.public`/`meta.mcp.public` er synlige. Antallet af værktøjer er **ikke målt** — det kræver et credential
  og et `tools/list`.
- **Royal MCP's egne ruter svarer `404`**, og alle fire `.well-known`-dokumenter er væk eller ødelagte. Det kan
  være en deaktivering, men det kan også være tabte rewrite-regler: plugin-aktivering flusher rewrite-regler,
  og både `/mcp` og `.well-known`-stierne er rewrites. Afgørelsen ligger i to kommandoer på hosten:
  `wp plugin list --status=active --fields=name,version,status` og derefter `wp rewrite flush --hard`.
- **OAuth-flowet fra tidligere på dagen kan ikke længere discoveres.** `.well-known/oauth-authorization-server`
  omdirigerer til forsiden, og den dybe metadata-sti giver `500`. En standardklient finder derfor ingen
  autorisationsserver — det forklarer hvorfor callbacken på `127.0.0.1:8765` ikke kunne indløses.

Indtil credential-spørgsmålet er afgjort, står `steamgears` i `.mcp.json` på en død rute (`/mcp` → `404`), og
`wp_mcp_stdio.py --check` kan ikke give `ok: true`.

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

Offentligt uden credential (målt i formiddags 2/11): `.well-known/mcp/server-card.json`,
`.well-known/agent-skills/index.json`, `.well-known/oauth-authorization-server`,
`.well-known/oauth-protected-resource` og `wp-json`-ruteindekset.
**Ingen værktøjskald er anonyme**, og der findes ingen `client_credentials`- eller device-code-grant —
metadataet annoncerede kun `authorization_code` og `refresh_token`.

> **Rettet sent 2/11 2026:** de fire `.well-known`-dokumenter svarer ikke længere `200`. To er `404`, to
> omdirigerer til forsiden, og den dybe OAuth-metadata-sti giver `500`. Det er målt efter at `mcp-adapter`
> blev installeret — se afsnittet »Måling 2/11 2026 (sen aften)« ovenfor. Ruteindekset på `/wp-json/` svarer
> stadig `200`.

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

### OAuth uden browser på maskinen (verificeret 2/11 2026)

> **Forældet siden sent 2/11 2026:** trin 1 og 6 rammer ikke længere noget —
> `.well-known/oauth-authorization-server` omdirigerer til forsiden (den dybe metadata-sti giver `500`), og
> `/wp-json/royal-mcp/v1/mcp` svarer `404`. Flowet virkede, da det blev afprøvet om formiddagen, men kan ikke
> gentages før discovery og ruten er tilbage (se »Måling 2/11 2026 (sen aften)«).

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

**Bemærk:** `https://steamgears.eu/mcp` svarer `404` siden sent 2/11 2026, så kommandoen virker ikke, før ruten
eller en afløser er på plads.

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

## Simply.com (`simplycom`)

Simply.com hoster steamgears.eu og ejer domæne, DNS og mail. Deres AI Connector er en MCP-server med
**OAuth 2.1** — der findes ingen API-nøgle at kopiere, og `.mcp.json` skal derfor ikke indeholde andet end
endpointet.

| Felt | Værdi |
| --- | --- |
| Endpoint | `https://mcp.simply.com/v2` |
| Transport | streamable HTTP |
| Auth | OAuth 2.1, browser-login ved første værktøjskald |
| Server-navn | `simplycom` · uden konto: `simplycom-public` |

### Hvad agenten kan — og ikke kan

Agenten får **samme rettigheder som den bruger der logger ind**: DNS-poster, nameservere og DNSSEC,
domænetilgængelighed og ordrelinks, mailkonti og videresendelser, subdomæner, MySQL-databaser, website- og
MySQL slow-logs, deploy-oplysninger til SSH, SFTP, FTP og rsync samt brugerens support-sager.

Agenten **kan ikke** bestille, opsige eller betale for noget. Det håndhæves af serveren, ikke af en indstilling
her i repoet.

Konsekvens for dette projekt: forbindelsen giver DNS, mail, databaser, logs og support-sager — men **ikke**
WAF-regler. Den kan altså ikke hvidliste en IP. Den 455 der blev målt 1–2/11 er WAF'ens spoofing-værn og ikke
et Google-problem; hele sagen står i [`library/waf-diagnose-2026-11-02.md`](library/waf-diagnose-2026-11-02.md),
og WAF-afsnittet nedenfor beskriver hvad der faktisk kan gøres.

### Opsætning

`.mcp.json` har allerede blokken — der er intet at udfylde:

```json
"simplycom": {
  "type": "http",
  "url": "https://mcp.simply.com/v2"
}
```

Andre klienter registrerer samme endpoint med deres egen kommando:

```bash
claude mcp add --transport http --scope user simplycom https://mcp.simply.com/v2
codex mcp add simplycom --url https://mcp.simply.com/v2
```

I Cursor og Windsurf lægges den i `mcpServers` som `{"url": …}` henholdsvis `{"serverUrl": …}` — Windsurf
bruger `serverUrl`, ikke `url`, og er den fejl der oftest koster en runde.

### Login — kun ejeren kan gøre det

1. Genstart MCP-klienten, så den læser den nye server.
2. Kald et vilkårligt Simply-værktøj. Klienten åbner browseren.
3. Log ind på Simply.com og godkend. Consent gælder **den konto du er logget ind med** — brug kontoen der ejer
   steamgears.eu, ellers får agenten ingen værktøjer for domænet.
4. Kontrollér i klienten at `simplycom` er connected, fx `claude mcp list`.

Der findes **intet device-code-flow og ingen nøgle**: en agent i en container uden browser kan ikke etablere
forbindelsen selv. Det er med vilje, og det betyder at forbindelsen altid kræver et menneske første gang.

### Offentlig server uden konto

`https://mcp.simply.com/public` kræver intet login og kan slå domænetilgængelighed op, liste de understøttede
TLD'er, bygge et ordrelink og søge i Simplys hjælpeartikler. Registrér den som `simplycom-public` hvis agenten
skal kunne det uden at have en konto.

### WAF: 429/454/455 og hvorfor en egen IP ikke kan hvidlistes

Simplys egen artikel [HTTP 429 / 454 / 455 Blocked](https://www.simply.com/en/support/faq/php/867-http-429-454-455-blocked/)
(hentet 2/11 2026) siger det direkte:

| Kode | Betydning ifølge Simply |
| --- | --- |
| `429` | For mange requests — midlertidigt; respektér `Retry-After` og sænk farten |
| `454` | Gammel/mistænkt browser eller IP med dårligt ry; normale browsere klarer JS-tjekket |
| `455` | En enkelt request blokeret af en firewall-regel; **årsagen står i Control Panel → Webserver logs** |

Tre sætninger i artiklen afgør sagen for en agent:

- **Kendte tjenester er allerede hvidlistet:** »Googlebot, OpenAI, Anthropic, Bing, LinkedIn, Jetpack, PayPal,
  Facebook, ManageWP etc.« — altså på IP, ikke på user-agent.
- **Spoofing opdages:** »many bots pretend to be these known services … always check whether it is actually an
  approved service that has been blocked (based on IP address)«.
- **Hvidlistning kræver en offentlig IP-liste:** Simply hvidlister en tjeneste, hvis der findes »a public and
  maintained URL with a list of the IP addresses in use«. Der findes **ingen selvbetjent IP-hvidlistning** i
  panelet.

Målt konsekvens (2/11 2026, egress `208.72.218.131`): neutral `curl` → 200; Googlebot-, bingbot-, Applebot- og
GPTBot-UA → 455. Det er forventet adfærd, ikke en fejl på sitet: IP'en tilhører ikke Google, så UA'en er en
spoof. Derfor:

1. **Crawl og verifikation kører med neutral UA** — aldrig med et påtaget bot-navn. Det er hvad
   [`tools/seo_audit_crawl.py`](../tools/seo_audit_crawl.py) gør, og hvorfor alle 121 URL'er gav 200.
2. **En vilkårlig cloud-IP kan ikke hvidlistes.** Den har ingen offentlig, vedligeholdt IP-liste, og Simply
   hvidlister tjenester — ikke enkeltadresser. IP'en kan desuden skifte mellem sessioner.
3. **Googlebots faktiske svar verificeres i GSC** (Crawl stats → By response), ikke ved at efterligne UA'en.
4. **Skal der alligevel hvidlistes**, gælder det kun en rigtig tredjepartstjeneste, og sagen skal indeholde
   tjenestens offentlige IP-liste. En agent-container er ikke sådan en tjeneste.

Kør checken efter en ændring — den skelner forventet bot-455 fra en ægte blokering:

```bash
bash tools/waf_check.sh
# exit 0 = neutral 200 · 1 = neutral blokeret · 2 = serverfejl
```

Fejlsøgning: 455 for en *neutral* klient betyder at vores egen trafik er ramt (typisk efter en burst) — vent
5–10 minutter og sænk farten. 455 for en bot-UA er normalt og kræver ingen handling. Den præcise regel findes
kun i Control Panel → Webserver logs for webhotellet.

### Tilbagekald adgang

Adgangen tilbagekaldes under **Connected apps** på
<https://www.simply.com/en/controlpanel/user/>. Det lukker OAuth-tokenet med det samme; blokken i `.mcp.json`
kan blive stående, men forbindelsen virker ikke før der logges ind igen. Skal serveren slet ikke tilbydes i
repoet, fjernes blokken — og derefter tilbagekaldes adgangen, i den rækkefølge.

### Fejlsøgning (Simply)

| Symptom | Betydning | Handling |
| --- | --- | --- |
| Serveren står som *needs authentication* | OAuth-login er ikke gennemført | åbn klienten, kald et værktøj, log ind |
| `401` efter genstart | token udløbet eller adgang tilbagekaldt | log ind igen |
| Login lykkes, men ingen domæneværktøjer | kontoen ejer ikke domænet | log ind med den konto der ejer steamgears.eu |
| Værktøjerne mangler helt | klienten læser ikke projektets `.mcp.json` | åbn repoet som projekt, eller registrér i bruger-scope |

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
- Simply.com-forbindelsen er stærkere end en API-nøgle: et aktivt OAuth-token giver DNS-, mail- og
  databaseadgang for hele kontoen. Log kun ind med kontoen der ejer steamgears.eu, og tilbagekald adgangen når
  den ikke bruges. Der findes ingen nøgle at lække — men heller ingen nøgle at rotere, så tilbagekald er den
  eneste bremse.
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
- [Simply.com AI Connector](https://www.simply.com/en/ai/connector/) — hvad connectoren kan, og hvad den ikke kan.
- [Simply.com agent-opsætning](https://www.simply.com/agent-setup/prompt.md) — kilden til Simply-afsnittet,
  hentet 2/11 2026.
- [Simply.com REST API](https://www.simply.com/en/docs/api/) — til sammenligning; connectoren er egne værktøjer.
- [Simply.com — HTTP 429 / 454 / 455 Blocked](https://www.simply.com/en/support/faq/php/867-http-429-454-455-blocked/)
  — WAF-koderne, hvilke tjenester der er hvidlistet, og kravet om en offentlig IP-liste, hentet 2/11 2026.
- [Simply.com — Tilbagekald adgang](https://www.simply.com/en/controlpanel/user/) — Connected apps.
