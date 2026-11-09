# Pinterest-kanalen: koden kan ikke veksles uden app-id, og integrationen ejer tokenet

**Målt 10/11 2026 kl. 00:05–00:20 UTC.** To ting blev afgjort i dette pas: hvorfor et nyt
Pinterest-token ikke i sig selv løser `boards:read`, og at appens **client id** ikke findes noget
sted i repo, historik, database, logs eller artefakter. Værktøjet der gør resten til én indtastning
ligger klar som `tools/pinterest_connect.py`.

## 1. Hvad der blev målt først

| Måling (server-side) | Svar |
| --- | --- |
| `SGAP_Pinterest::token_source()` | **`woocommerce`** — kanalen bruger Pinterest for WooCommerce-integrationens token |
| `GET /v5/boards` med `SGAP_Pinterest::token()` | **401**, `"…sufficient permissions… Missing: ['boards:read']"` |
| `sgap_pinterest_client_id` | **0 tegn** (feltet er tomt) |
| `sgap_pinterest_client_secret` | **40 tegn**, hex, præfiks `a98…` |
| `sgap_pinterest_access_token` / `refresh_token` | 101 tegn hver |
| `SGAP_PINTEREST_CLIENT_ID` / `…_SECRET` i `wp-config.php` | ikke defineret |
| GitHub-repo-variabler | `total_count: 0` — workflowen læser `secrets.PINTEREST_CLIENT_ID`, og secrets kan ikke læses ad API |

`token_source()` returnerer `woocommerce` så længe integrationen har et token, fordi integrationen
fornyer sit eget token selv. Det betyder, at **selv et perfekt token i vores eget felt ikke bliver
det token kanalen bruger** — rækkefølgen i `token()` er constant → integration → eget felt. Et nyt
token med `boards:read` virker derfor først, når integrationen er afbrudt, eller når integrationens
eget token har scope't.

## 2. Hvor client id'et blev søgt — og ikke fundet

| Sted | Søgning | Resultat |
| --- | --- | --- |
| Repoet | `grep -rIl` med mønstrene `pinterest.com/oauth`, `api.pinterest.com/oauth` og `response_type=code` | kun `tools/mcp_oauth.py` (Novamira-MCP) |
| Git-historikken | `git grep` over **alle** commits (`git rev-list --all`) | samme ene fil, ingen Pinterest-URL |
| `docs/samtalepunkter.md` + 10 backups | `grep -n` efter `pinterest.com/oauth`, `authorize` og `client_id` | 0 træf |
| Databasen | `option_value LIKE '%8765%' OR '%client_id%'` | `royal_mcp_settings`, `jetpack_constants_sync_checksum`, `cmplz_options`, `woocommerce-ppcp-data-common`, `mwseo_options` — intet Pinterest |
| Databasen | `postmeta`/`usermeta` med `client_id` | 0 rækker |
| Databasen | alle `sgap_pinterest*`-options med længder | `client_id=0`, `client_secret=40` |
| WooCommerce-integrationen | nøgler i `pinterest_for_woocommerce_data` | `token_data` (access/refresh/scopes), `connection_info_data` (advertiser_id, tag_id, merchant_id, clientHash) — intet app-id |
| CI | `.github/workflows/niche-scan.yml` | bruger `secrets.*`, som ikke kan læses |
| MCP-klientfiler | tre `client.json` (ai-engine, topniveau, novamira) | ingen med callbackens `state`; koden blev afvist af Novamira-endpoints (401 `invalid_client` / 400 `invalid_request`) → ikke en MCP-kode |
| Agent-log | `~/agent-output.log` (1,7 MB) | kun Novamira-autorisationslenker |

Konklusion: id'et findes kun i ejerens Pinterest-app (developers.pinterest.com → My apps) eller i
repository-secrets. Det er **ikke** en hemmelighed — det står i selve autorisationslinket — men det
kan ikke udledes af secret'en, som er 40 tilfældige hex-tegn.

## 3. Værktøjet: `tools/pinterest_connect.py`

Alt kører server-side over MCP (`novamira_run-wp-cli` → `wp eval`), så koden og tokenet aldrig
forlader WordPress, og der printes kun længder, scope og Pinterests egne svarlynjer.

```bash
python3 tools/pinterest_connect.py --status      # id/secret/token-længder, scope-afslag, token-kilde
python3 tools/pinterest_connect.py --authorize   # autorisations-URL med state gemt i databasen
python3 tools/pinterest_connect.py --finish '<hele callback-adressen>'
python3 tools/pinterest_connect.py --verify      # /v5/boards med det token plugin'et faktisk sender
python3 tools/pinterest_connect.py --selftest    # 22 offline-checks
```

- `--authorize` nægter med en forklarende linje når `pinterest_client_id` er tom, og gemmer ellers
  en tilfældig `state` i optionen `sgap_pinterest_connect_state`.
- `--finish` tjekker `state`, veksler koden mod `https://api.pinterest.com/v5/oauth/token` med HTTP
  Basic (`client_id:client_secret`), `grant_type=authorization_code` og `continuous_refresh=true`
  (påkrævet for apps oprettet før 25/9 2025), gemmer access- og refresh-token, rydder
  `sgap_pinterest_refused` og `sgap_pinterest_scope`, og kalder til sidst `/v5/boards`.
- Fejlsvar oversættes: `invalid_grant` → koden er udløbet eller brugt, `invalid_client` → id og
  secret passer ikke sammen, manglende scope → appen fik ikke board-adgang med.
- `--verify` bruger `SGAP_Pinterest::token()` — ikke `saved_token()` — så målingen viser den
  credential kanalen reelt sender, og rapporterer `token_source` med.

## 4. Den ene handling, og hvad der sker bagefter

1. Indsæt appens **App ID** i *Settings → Keys* (`pinterest_client_id`). Feltets egen hjælpetekst
   siger allerede, at det er appens offentlige id.
2. `--authorize` giver en frisk lenke (den indsendte kode er kortlivet og éngangs), ejeren godkender
   board-adgang, og callback-adressen klistres ind i `--finish`.
3. Vekslingen gemmer tokenet og bekræfter med `/v5/boards`. Rapporterer værktøjet
   `token_source: woocommerce`, er svaret stadig 401 — og så skal integrationen afbrudt være, før
   kanalen bruger det nye token. Det er en ejerbeslutning, fordi integrationen også driver
   produkt-feedet til Pinterest.

## 5. Fælder

- **Koden er éngangs og kortlivet.** Et forkert endpoint-forsøg koster ikke koden, men en vellykket
  veksling gør. Kør `--finish` én gang, med rigtigt id.
- **Pinterest kræver Basic med både id og secret.** Secret alene giver `invalid_client`.
- **`continuous_refresh=true`** skal med for apps oprettet før 25/9 2025; nyere ignorerer feltet.
- **Integrationen vinder over eget felt.** Uden at kende rækkefølgen ser et gemt token ud som en
  løsning, mens kanalen fortsat svarer 401.
- **Scope-svaret skal henføres til den rigtige credential.** Afslaget fra 9/11 kl. 20:00 gjaldt
  integrationens token, ikke vores eget — `token_source` i `--status` er det, der afgør sagen.
