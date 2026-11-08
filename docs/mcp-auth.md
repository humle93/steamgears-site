# MCP-adgang — steamgears.eu og Publer

`.mcp.json` har tre servere: to HTTP-servere der kræver hver sin slags credential, og én lokal stdio-server
uden credential.

| Server | Endpoint | Auth i `.mcp.json` | Credential |
| --- | --- | --- | --- |
| `steamgears` | `https://steamgears.eu/wp-json/mcp/v1/http` | `Bearer ${MCP_ACCESS_TOKEN}` | OAuth-token fra `tools/mcp_oauth.py` (ligger uden for repoet) |
| `publer` | `https://mcp.publer.com/` | `Bearer ${PUBLER_API_KEY}` | Publer MCP API key (Business/Enterprise) |
| `steamgears-profil` | `tools/mcp_project_profile.py` (stdio) | ingen | ingen — læser kun repoet |

**Status 4/11 2026: både læsning og skrivning virker.** OAuth-klienten blev godkendt af ejeren i browseren,
tokenet lever 3600 s med refresh, og `python3 tools/mcp_rest.py --check` svarer `ok: true` med serveren
`AI Engine - Steamgears` og **236 værktøjer**, rolle `admin`. `mcp_drafts_only` er nu **`false`** (målt i
`mwai_options` 4/11), så skrivende kald går igennem: 4/11 blev 20 `/go/`-links indsat i ni guides med
`wp_alter_post`, og 14 hrefs blev konverteret med `wp_update_post`. Opskriften og fælderne står i
[`library/mcp-skriveadgang-2026-11-04.md`](library/mcp-skriveadgang-2026-11-04.md).

Indtil 4/11 var svaret det modsatte: *Drafts Only* var slået til, så `queue-post` blev afvist med »This site lets
assistants work on drafts only«, før plugin'et så kaldet. Den switch slås til og fra i
*Meow Apps → AI Engine → Settings → MCP*, og den er den eneste der holder et admin-token tilbage — den kan ikke
slås fra over MCP, fordi spærren også dækker `wp_update_option`. Det er med vilje.

**Sådan tjekkes spærren uden at skrive** (målt 2/11 2026, efter ejeren havde slået den fra — den var stadig
til): AI Engines indstillinger ligger i option `mwai_options`, og læsende værktøjer må gerne læse den. Med
`raw: true` læses databasen udenom object-cachen, så svaret er den faktiske tilstand og ikke et cachet billede:

```bash
python3 tools/mcp_rest.py --call wp_get_option '{"key":"mwai_options","raw":true}' | grep -o '"mcp_[a-z_]*": [^,}]*'
```

| Nøgle | Målt 2/11 | Betydning |
| --- | --- | --- |
| `mcp_drafts_only` | 2/11: true · **4/11: false** | spærren: da den var `true`, blev alle skrivende kald afvist, også plugin'ets egne |
| `mcp_role` | `admin` | tokenet har administratorkraft, så kun Drafts Only holdt det tilbage |
| `mcp_approvals` | `off` | ingen godkendelse pr. værktøj — spærren er den eneste |
| `mcp_default_readonly` | false | læse-only er ikke slået til |
| `mcp_dynamic_rest` | true | Dynamic REST er allerede tændt, så `wp_update_option` m.fl. er tilgængelige |
| `mcp_abilities` / `module_mcp` | true | Abilities og MCP-modulet kører |

Pointen: en switch der ikke blev gemt, eller som blev sat på en anden fane, kan ses her med det samme — og
agenten kan ikke selv slå den fra, fordi spærren også dækker `wp_update_option`. Det er med vilje.

**Profilen i pluginnet rører chattens værktøjer, ikke denne flade.** `sgap_ai_profile` (7.41.2) afgør hvem
Autopilotens *egen* AI Chat er, og hvilke af pluginnets værktøjer den må kalde; profilen *Site administration*
slår `mcp_dynamic_rest` og `mcp_abilities` til gennem `enable_full_access()`. Begge nøgler stod **allerede** på
`true` her (målt 2/11 og 4/11), så aktiveringen er idempotent på dette site og ændrer intet i `mwai_options` —
og den eksterne MCP-klient fik ikke mere adgang af profilen, fordi den havde `mcp_role: admin` i forvejen.
Det der faktisk kan ses efter et profilskift, er chattens egen værktøjsliste og persona, ikke REST-fladen.
Tilstanden læses uden at skrive med `wp_get_option mwai_options raw` eller pluginnets `site_health`, der svarer
`ai_engine_mcp: {abilities, dynamic_rest, role}` plus `autopilot_version`.

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

## Novamira (`novamira`) — den anden MCP-flade (målt 6/11 2026)

Sitet kører **Novamira 1.12.14** ved siden af AI Engine. Den har sin egen OAuth-server og sin egen flade:

| | |
| --- | --- |
| Resource | `https://steamgears.eu/wp-json/mcp/novamira-oauth` — `401` uden token, med `resource_metadata` i `WWW-Authenticate` |
| Authorization server | `https://steamgears.eu/.well-known/oauth-authorization-server` (issuer `https://steamgears.eu`) |
| Endpoints | authorize `admin.php?page=novamira-oauth-authorize`, token/register/revoke/introspect/device under `/wp-json/novamira/v1/oauth/` |
| Scope | `full_access` (eneste understøttede) |
| Flera | **3 meta-værktøjer** — `discover-abilities`, `get-ability-info`, `execute-ability` — over **207 abilities**, heraf 60 fra plugin'et og resten fra WordPress, WooCommerce, Rank Math m.fl. |

**Tre ting der koster en runde, hvis man ikke ved dem:**

1. **DCR registrerer ikke device-grant.** Metadata annoncerer `urn:ietf:params:oauth:grant-type:device_code`, men
   en dynamisk registreret klient får kun `authorization_code` + `refresh_token` tilbage, og `/oauth/device`
   svarer `unauthorized_client`. Brug authorization code + PKCE med redirect `http://127.0.0.1:8765/callback`.
2. **Token-endepunkterne kræver form-encoding.** JSON giver `unsupported_grant_type` — serveren kan ikke læse
   `grant_type` og afviser, før den ser koden. `tools/mcp_oauth.py` sender nu form-data til token-kald og JSON
   til DCR (RFC 6749 §4.1.3).
3. **`tools/list` kræver `Mcp-Session-Id`** fra `initialize`-svarets header. Uden den svarer fladen `400` med
   0 værktøjer, selv om `initialize` gik igennem.

### Fornyelse i pluginnet (7.44)

Siden 7.44 holder **sitet selv** Novamira-tokenet i live, så adgangen ikke afhænger af repository-secrets eller
af et loop i en sandkasse. [`plugins/steamgears-autopilot/includes/mcp-auth.php`](../plugins/steamgears-autopilot/includes/mcp-auth.php)
(`SGAP_MCP_Auth`) kører cron-hooket `sgap_mcp_refresh` **hvert 30. minut**:

1. fornyer med det gemte refresh-token (form-encoded kald til `/wp-json/novamira/v1/oauth/token`),
2. gemmer det nye access-token og det **roterede** refresh-token med det samme,
3. verificerer med `initialize` → `Mcp-Session-Id` → `tools/list`, og
4. gemmer resultatet: tid, antal værktøjer, rotation og API'ets egen fejltekst.

Et token der er udstedt men ikke virker bliver **ikke** meldt som fornyet. Fejlen står i det daglige selvtjek
(samme mail-vej som de øvrige alarmer), i værktøjet `steamgears-tools/mcp-status` og på *Settings → MCP access*,
hvor **MCP client ID** og **MCP refresh token** indtastes. Refresh-tokenet er en secret: det gemmes med
autoload `false` og vises aldrig igen. Det skal ligge i databasen — en konstant i `wp-config.php` afvises, fordi
serveren roterer tokenet og ikke kan skrive konstanten tilbage. `steamgears-tools/mcp-refresh` fornyer på
kommando efter en afbrydelse.

**Godkendelsen er stadig ejerens:** `python3 tools/mcp_oauth.py --start` giver linket, `--finish '<kode>'`
gemmer parret uden for repoet, og de to værdier indsættes i wp-admin. Den vej nedenfor (worktree-loop og
CI-workflow) virker stadig, men er nu kun et supplement.

### Del adgangen med en anden agent eller person

Det der deles er **opskriften, ikke tokenet**. Hver agent registrerer sin egen offentlige klient (DCR), får sit
eget token-par og kan tilbagekaldes for sig i wp-admin — et token der sendes videre i chat eller mail kan ikke
tilbagekaldes særskilt, og det er hele grunden til at det ikke gøres.

**Vælg flade først** — de to servere har hver sit resource og scope, og et token udstedt til den ene virker
ikke på den anden:

| Flade | `MCP_METADATA_URL` | `MCP_RESOURCE` | `MCP_SCOPE` |
| --- | --- | --- | --- |
| AI Engine (`/wp-json/mcp/v1/http`, 206 værktøjer) | `https://steamgears.eu/wp-json/mcp/v1/.well-known/oauth-authorization-server` | `https://steamgears.eu/wp-json/mcp/v1/http` | `mcp` |
| Novamira (3 meta-værktøjer over 207 abilities) | `https://steamgears.eu/.well-known/oauth-authorization-server` | `https://steamgears.eu/wp-json/mcp/novamira-oauth` | `full_access` |

**De fire trin, som den nye agent kører** (alt ligger uden for repoet, mode 600):

```bash
# 1. egen token-mappe pr. agent, så to agenter ikke overskriver hinandens par
export MCP_CONFIG_DIR="$HOME/.config/steamgears-mcp/agent-2"
export MCP_RESOURCE="https://steamgears.eu/wp-json/mcp/novamira-oauth"
export MCP_SCOPE="full_access"
python3 tools/mcp_oauth.py --start          # registrerer klient og printer godkendelseslinket

# 2. EJEREN (eller en administrator) åbner linket, logger ind og godkender

# 3. agenten bytter koden — koden er éngangs og lever kun kort
python3 tools/mcp_oauth.py --finish '<hele redirect-adressen eller code=-værdien>'
python3 tools/mcp_oauth.py --status
```

4. **Verificér med et kald, ikke med et håb:** `python3 tools/live_state.py` (Novamira) eller
   `python3 tools/mcp_rest.py --check` (AI Engine). Et token der er udstedt men ikke virker, skal meldes som
   fejl — ikke som adgang.

**Hvad den nye agent ikke skal have:** `~/.config/steamgears-mcp/tokens.json` fra en anden maskine, et
refresh-token i en besked, eller `NOVAMIRA_REFRESH_TOKEN` fra repository-secrets. Skal adgangen køre uden
ejerens hænder i CI, oprettes `NOVAMIRA_REFRESH_TOKEN` + `NOVAMIRA_SECRETS_TOKEN` som beskrevet nedenfor — det
er den eneste vej der ikke kræver et menneske ved hver fornyelse, fordi refresh-tokenet **roteres** og det gamle
dør.

**Tilbagekaldelse:** wp-admin → Novamira → OAuth-apps (eller AI Engine → Settings → MCP for den anden flade).
Slet klienten, ikke bare filen lokalt — ellers lever tokenet videre indtil det udløber.

### Hold adgangen i live

Access-tokenet lever **3600 s**, og refresh-tokenet **roteres** ved hver fornyelse (målt 6/11: `refresh_token`
ændres, `expires_in` er 3600, ingen `refresh_token_expires_in` oplyses). Introspection er admin-only, så
refresh-tokenets levetid kan ikke læses udefra — derfor skal fornyelsen køre planlagt, ikke på held.

[`tools/mcp_keepalive.py`](../tools/mcp_keepalive.py) fornyer, verificerer med `initialize` + `tools/list` og
gemmer det nye par (mode 600, uden for repoet). Kør den manuelt med:

```bash
python3 tools/mcp_keepalive.py          # lokalt: forny + verificér + gem
python3 tools/mcp_keepalive.py --ci --emit-refresh-file /tmp/refresh   # CI: skriv det roterede token til fil
```

**Løbende fornyelse i sandkassen (hvert 58. minut):**
[`tools/keepalive-loop.sh`](../tools/keepalive-loop.sh) kalder keep-alive hvert 3480 s, så access-tokenet aldrig
når at udløbe, og logger hver kørsel i `.tools/keepalive-loop.log`. Den tåler enkelte netværksfejl (prøver igen
efter 5 min, op til 5 gange i træk) og stopper selv med en klar besked, hvis tokenet er dødt.

```bash
setsid nohup bash tools/keepalive-loop.sh 3480 >/dev/null 2>&1 < /dev/null &   # start
kill "$(cat .tools/keepalive-loop.pid)"                                        # stop
```

Grænsen er arkivering: processen lever kun, mens worktreen kører, og efter en genstart skal kommandoen køres
igen. Det dækker arbejdsdage, ikke 30 dage uden at nogen åbner chatten — det gør kun CI-varianten nedenfor.

**Planlagt fornyelse (30+ dage uden ejerens hænder):**
[`novamira-keepalive.yml`](../.github/workflows/novamira-keepalive.yml) kører dagligt kl. 03 dansk tid,
fornyer og gemmer det roterede token tilbage som repository-secret. Den springer sig selv over med en
`::notice`, indtil disse findes:

| Navn | Type | Hvad |
| --- | --- | --- |
| `NOVAMIRA_REFRESH_TOKEN` | secret | refresh-tokenet fra den lokale godkendelse |
| `NOVAMIRA_SECRETS_TOKEN` | secret | fine-grained PAT med **Secrets: write** (`PINTEREST_SECRETS_TOKEN` bruges som fallback) |
| `NOVAMIRA_CLIENT_ID` | variabel, valgfri | den offentlige klient-id; ellers bruges klienten fra 6/11 2026 |

Uden dem virker adgangen kun så længe refresh-tokenet lever, og et nyt kræver ejerens godkendelse igen.

### Natlig måling af site-tilstanden

Den samme kørsel **måler** sitet, ikke bare forbindelsen: [`tools/live_state.py`](../tools/live_state.py) kaldes af
`mcp_keepalive.py --live-report reports` med det netop fornyede token og spørger fladen om fire ting — det er
svaret på »har *Site administration*-profilen faktisk tændt Dynamic REST og Abilities?«, som ellers kræver et
token i en sandkasse:

| Krav | Hvor det kommer fra | Dom |
| --- | --- | --- |
| AI Engine **Dynamic REST** | `steamgears-autopilot/site-health` → `ai_engine_mcp.dynamic_rest` | `true` kræves |
| AI Engine **Abilities** | samme svar → `ai_engine_mcp.abilities` | `true` kræves |
| `mcp_role` | samme svar → `ai_engine_mcp.role` | advarsel hvis ikke `admin` |
| Profilen `sgap_ai_profile` | `steamgears-autopilot/get-settings` → `ai_profile` | oplyses; krav kun hvis `LIVE_EXPECT_PROFILE` er sat |
| Plugin-version på sitet | `site-health` → `autopilot_version` | **advarsel** ved forskel fra repoet — et plugin-folder skiftes kun af WordPress |

Rapporten skrives til `reports/live-tilstand-<dato>.md` og `.json` og committes af workflowet; er et **krævet**
krav ikke opfyldt, får jobbet en `::error`-annotation. Rækkefølgen er med vilje: refresh-tokenet gemmes som
repository-secret **før** målingen kan farve jobbet rødt, så en målefejl aldrig koster adgangen.

To ting er værd at kende, hvis målingen skal køres i hånden:

- **Argumentnavnene læses af `tools/list`, ikke gættet.** Meta-værktøjet har heddet både `{name, input}` og
  `{ability, arguments}`; skemaet afgør formen, og rapporten skriver hvilken der virkede.
- **To kald, ikke flere.** `discover-abilities`/`get-ability-info` bruges ikke: de to abilities kaldes direkte,
  og et svar der ikke kan læses (fx et ældre plugin uden `ai_engine_mcp`) bliver `fejl`/`ukendt` — aldrig
  »bestået«.

```bash
python3 tools/live_state.py                 # lokalt: kræver token i ~/.config/steamgears-mcp/novamira/ eller NOVAMIRA_ACCESS_TOKEN
python3 tools/live_state.py --out /tmp/x    # skriv et andet sted
python3 tools/test_live_state.py            # 14 offline-fixtures, ingen token
```

## Kildesporet: hvad AI Engine og adapteren kan kalde (læst 2/11 2026)

Afsnittene ovenfor er den vej ind der virker. Dette er hvad kilden siger om værktøjerne — brugbart når et
`tools/list` ikke kan køres, og for at vide hvad der *kan* ligge bag et kald.

- **AI Engines kerneværktøjer i 3.9.0** (`labs/mcp*.php`): 74 værktøjer — 29 læsende, 31 skrivende, 14 admin
  — plus fire prompts (`tour_my_site`, `write_a_draft`, `audit_my_content`, `improve_a_post`), ingen resources
  (`get_resources_list()` returnerer `[]`) og en abilities-bro der lægger **ét værktøj pr. ability** oveni.
  Sitet annoncerer 206, så mindst 132 ligger ud over de 74: Autopilot-familien (31), bridgede abilities og
  resten af Pro-sættet. Fordelingen mellem dem er ikke målt. Hele listen med beskrivelser og adgangsniveau:
  [`library/mcp-vaerktoejer-2026-11-02.md`](library/mcp-vaerktoejer-2026-11-02.md).
- **mcp-adapter 0.7.1 har hverken OAuth eller `.well-known`-ruter.** Release-zip'en indeholder ingen
  `well-known`-streng og intet token-endpoint; adapterens ene rute er `/wp-json/mcp/<server-id>`, og dens
  credential-vej er WordPress' egen REST-auth (application password eller cookie + nonce). `mcp/mcp-oauth-server`
  hører derfor til AI Engine og ikke til adapteren.
- **Discovery flappede under aktiveringen — mål igen, før du fejlretter.** Minutter efter
  `wp plugin install … --activate` svarede `/.well-known/oauth-protected-resource` og
  `…/oauth-authorization-server` `301` til forsiden, og en dyb sti `500`; senere samme aften svarer alle tre
  `200` med gyldig JSON. Plugin-aktivering flusher rewrite-regler, så et probe lige efter en aktivering beviser
  intet om hvordan discovery opfører sig bagefter. (Det var også den måling der tidligere blev brugt som
  forklaring på den døde callback — den forklaring holder ikke.)

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

## Simply.com (`simplycom`)

Simply har en MCP-server ved siden af WordPress-fladen: den rører **hosten**, ikke sitet. Den er registreret i
[`.mcp.json`](../.mcp.json) som `simplycom`, og opsætningen er Simplys egen vejledning:
<https://www.simply.com/agent-setup/prompt.md>.

| | |
| --- | --- |
| Endpoint | `https://mcp.simply.com/v2` |
| Transport | streamable HTTP |
| Auth | OAuth 2.1 — **ingen nøgle at kopiere**; ejeren logger ind i browseren ved første kald |
| Navn | `simplycom` (den offentlige udgave uden login hedder `simplycom-public` og ligger på `https://mcp.simply.com/public`) |

Målt 7/11 2026 fra cloud-worktreen: `GET /v2` svarer `401` — serveren lever og kræver auth — og
`/.well-known/oauth-protected-resource` svarer `200`, så discovery virker. Et kald uden token fejler altså på
**auth**, ikke på netværk. Selve login-flowet kan ikke gennemføres herfra: OAuth'en kræver ejerens browser, og
Simplys WAF svarer `454` på HTML-ruter fra datacenter-IP'er (se fejlsøgningstabellen).

Fladen giver DNS-poster, nameservere og DNSSEC, domænetilgængelighed og ordrelinks, mailkonti og
videresendinger, subdomæner, MySQL-databaser, website- og MySQL slow-logs, deploy-oplysninger til
SSH/SFTP/FTP/rsync samt support-sager — og agenten har samme rettigheder som brugeren. Den kan **ikke**
bestille, opsige eller betale.

### Sådan tages den i brug

1. `simplycom` står allerede i [`.mcp.json`](../.mcp.json) med `"type": "http"` og URL'en ovenfor. Andre
   klienter registreres med deres egen kommando fra Simplys side — brug samme navn, så henvisningerne passer.
2. Genstart agenten, så MCP-serveren bliver læst.
3. Første gang et Simply-værktøj kaldes, åbner klienten browser-login. **Kun ejeren kan gennemføre det** —
   agenten kan ikke logge ind på brugerens vegne, og der er ingen nøgle at indsætte i stedet.
4. Virker det ikke: tjek at URL'en er præcis `https://mcp.simply.com/v2` (ikke `/mcp` eller `/v1`), at
   klienten understøtter streamable HTTP + OAuth, og at adgangen ikke er tilbagekaldt.

Adgangen tilbagekaldes under *Connected apps* i Simplys kontrolpanel
(<https://www.simply.com/en/controlpanel/user/>) — det er den vej en agent fratages adgang igen.

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
- Simply.com-connectoren har ingen nøgle at lække — den arver brugerens rettigheder gennem OAuth og
  tilbagekaldes under *Connected apps*. Giv den derfor ikke adgang fra en konto der også kan betale.

## Referencer

- [`.mcp.json`](../.mcp.json) — `steamgears`-serveren med `Bearer ${MCP_ACCESS_TOKEN}`.
- [`tools/mcp_oauth.py`](../tools/mcp_oauth.py) — registrering, PKCE-link, token-udveksling og fornyelse.
  `--status` viser om der er et gyldigt token.
- [`tools/live_state.py`](../tools/live_state.py) — måler `mwai_options`' MCP-nøgler og profilen gennem
  Novamira-fladen og skriver `reports/live-tilstand-<dato>.md`/`.json`; `tools/test_live_state.py` er dets
  14 offline-fixtures.
- [`tools/mcp_rest.py`](../tools/mcp_rest.py) — `--check`, `--abilities`, `--diff`, `--csv`, `--call`.
- [`tools/test_mcp_oauth.py`](../tools/test_mcp_oauth.py) og [`tools/test_mcp_rest.py`](../tools/test_mcp_rest.py)
  — 24 fixtures mod lokale efterligninger, ingen credential nødvendig.
- [`seo_agent.py`](../seo_agent.py) — `call_mcp()` sender Bearer fra `MCP_ACCESS_TOKEN` mod samme endpoint.
- [MCP — authorization](https://modelcontextprotocol.io/specification/2025-06-18/basic/authorization) —
  OAuth 2.1, PKCE og resource-indikatoren som AI Engine følger.
- [AI Engine](https://meowapps.com/ai-engine/) — plugin'et bag `/wp-json/mcp/v1/`.
- [Publer MCP — officiel opsætning](https://publer.com/help/en/article/how-to-set-up-and-use-publers-mcp-server-14orlq0/) — OAuth og API-nøgle, opdateret 15/10 2026.
- [Publer MCP — integrationsside](https://publer.com/integrations/mcp) — endpoint og OAuth-flow.
