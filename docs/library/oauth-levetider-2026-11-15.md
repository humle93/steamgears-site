# OAuth-levetider i Novamira — 60 sekunder der koster en runde

**Målt 15/11 2026 mod steamgears.eu (Novamira 1.12.15) og læst i plugin'ets kildekode.**

## 1. Problemet, som det viste sig

Payload-rettelsen ventede på MCP-adgang. Ejeren åbnede godkendelseslinket, loggede ind og godkendte, og sendte
callback-adressen tilbage i chatten. Da agenten byttede koden, svarede token-endepunktet:

```
HTTP 400 · error: invalid_request · hint: Authorization code has expired
```

State og PKCE-verifier passede (`state` var identisk med den gemte, verifieren var intakt). Koden var altså
ægte, udstedt til netop denne klient — den var bare **for gammel**, og der gik under et par minutter.

## 2. Hvorfor: levetiden er en konstant i pluginnet

Novamira er AGPL og ligger offentligt (`use-novamira/novamira`). `includes/oauth/server-factory.php` bygger
authorization-serveren med League OAuth2 og sætter koden sådan:

```php
$authCodeGrant = new AuthCodeGrant(
    new AuthCodeRepository(),
    new RefreshTokenRepository(),
    new DateInterval('PT1M'),      // authorization code: 60 sekunder
);
$authCodeGrant->setRefreshTokenTTL(new DateInterval('P14D'));
$server->enableGrantType($authCodeGrant, new DateInterval('PT1H'));   // access token: 1 time
```

| Token | Levetid | Kan ændres? |
| --- | --- | --- |
| Authorization code | **60 s** (`PT1M`) | Nej — hardcodet. Ingen filter, ingen option, ingen metadata-parameter |
| Access token | 3600 s (`PT1H`) | Nej, men fornyes automatisk |
| Refresh token | 14 dage (`P14D`, roteres) | Nej |
| Device code | **600 s** (`CODE_TTL` i `includes/oauth/endpoints/device.php`) | Nej, men 10× længere og polles automatisk |

Den eneste filter i hele OAuth-laget er `novamira_oauth_max_clients` (antal samtidige klienter). Der findes
**ingen** `novamira_oauth_authorization_code_ttl` eller tilsvarende. Svar på spørgsmålet «kan levetiden
forlænges?»: **ikke ved konfiguration** — kun ved at patche plugin'et, og det er ikke en vej vi går.

## 3. Løsningen: RFC 8628 device authorization

Serveren annoncerer `urn:ietf:params:oauth:grant-type:device_code` i sin metadata, og
`includes/oauth/endpoints/device.php` sætter `CODE_TTL = 600` og `POLL_INTERVAL = 5`. Ejerens trin bliver da:

1. agenten beder om en device-kode og printer en kode på otte tegn (`BCDF-GHJK`-format, uden vokaler og tal),
2. ejeren åbner `/wp-admin/admin.php?page=novamira-oauth-device` og godkender koden,
3. agenten poller token-endepunktet hvert femte sekund og gemmer tokens, så snart godkendelsen er der.

Ingen loopback-listener, ingen kopiering af en lang callback-adresse, og ti minutter i stedet for ét.

```bash
export MCP_CONFIG_DIR="$HOME/.config/steamgears-mcp/novamira"
export MCP_RESOURCE="https://steamgears.eu/wp-json/mcp/novamira-oauth"
export MCP_SCOPE=full_access
python3 tools/mcp_oauth.py --device        # printer kode + godkendelsesside
python3 tools/mcp_oauth.py --device-poll   # venter til godkendelsen kommer
```

## 4. Fælden der kostede den første halve time

DCR-registreringen (`/wp-json/novamira/v1/oauth/register`) svarer **altid**
`['authorization_code', 'refresh_token']`, når anmodningen indeholder en `redirect_uris`-liste — også når man
beder om device-granten. `/oauth/device` svarer så `unauthorized_client: This client is not registered for the
device authorization grant`. Målt adfærd:

| Anmodning til DCR | Svar |
| --- | --- |
| `redirect_uris: []` + device-grant | `201` · `['urn:ietf:params:oauth:grant-type:device_code', 'refresh_token']` |
| `redirect_uris: [http://127.0.0.1:8765/callback]` + device-grant | `201` · `['authorization_code', 'refresh_token']` (granten er væk, uden fejl) |
| uden `redirect_uris`-nøgle | afvist (kræver listen, også når den er tom) |

En device-klient og en loopback-klient er altså **to forskellige klienter** — samme konklusion som Novamiras
egen CLI-dokumentation (`novamira-cli/docs/v1-contract.md`): «The device client is registered separately from
the loopback client, with no redirect_uris and the device-code and refresh_token grants.»

## 5. Grænser der rammer en agent, der prøver flere gange

| Grænse | Værdi | Hvor | Virkning |
| --- | --- | --- | --- |
| DCR pr. IP | 10 i timen | `DCR_RATE_LIMIT_PER_HOUR`, transient 1 t | `429 rate_limited` i op til en time — et par diagnostiske registreringer brænder vinduet |
| Klienter pr. IP | 10 i alt | `MAX_CLIENTS_PER_IP` | ubrugte registreringer fylder pladserne; en administrator kan frigive dem med det samme |
| Pruning af ubrugte | 24 t | `STALE_UNUSED_CLIENT_TTL` | en klient der aldrig fik et token forsvinder af sig selv |
| Klienter pr. site | 50 aktive | `MAX_CLIENTS_PER_SITE` / `novamira_oauth_max_clients` | ikke nået her |
| Ukendt klient | `401 Unknown client_id` | — | en `client_id` fra en tidligere sandkasse er død; registrér igen |

**Lærdom:** registrér **én** klient pr. flade og gem den; brug `--device` med det samme i stedet for at
eksperimentere sig frem, når timen er ung.

## 5b. Vejen ud af låsen: *Reset registration limits*

De ubrugte registreringer kan **ikke** slettes fra **Manage Connections**. Siden viser kun apps med et levende
refresh-token (et mislykket forsøg får aldrig et) og de klient-ID'er en administrator selv har oprettet. En
klient der aldrig nåede en token-udveksling står altså ingen steder, og forsvinder først ved 24-timers-pruning —
derfor kan en agent være låst ude, mens wp-admin ser fuldstændig normalt ud.

Novamira har knappen i fejlfindingen:

1. **Novamira → Troubleshoot** (`/wp-admin/admin.php?page=novamira-troubleshoot`).
2. Vælg symptomet «Couldn’t register with [connection name]’s sign-in service…» (`registration`-grenen).
3. Klik **Reset registration limits**.

Svaret er fx «Registration limits reset. 7 incomplete registrations were removed.» Tallet er de pladser der
blev brændt. `includes/troubleshoot/admin.php` kalder `ClientValidation\reset_registration_limits()` bag
`check_admin_referer()` og `novamira_current_user_can_manage()`, så det kræver en administrator-session — ikke
MCP. Det er hele pointen: en agent der er låst ude kan ikke selv åbne døren.

**Hvad reset ikke klarer.** `reset_registration_limits()` (læst i `includes/oauth/client-validation.php`)
gør præcis to ting: bumper `novamira_oauth_dcr_epoch`, så timebudgettet er friskt, og sletter rækker med
`last_used_at IS NULL AND admin_created = 0`. Godkendte klienter bliver stående — også dem hvor selve
kodeudvekslingen bagefter fejlede, for godkendelsen sætter `last_used_at`. `client_count_for_ip()` tæller
netop **alle** rækker med samme `registered_by_ip_hash` (SHA-256 af `REMOTE_ADDR`), så de fylder stadig.

Kommer `Too many registered clients from this address` igen efter et reset, er vejen **Manage Connections**:
*Revoke* kalder `revoke_client_access()` + `ClientRepository::delete_if_unused()`, som sletter rækken, når der
ikke er et levende refresh-token. Gaten i `includes/oauth/endpoints/register.php` (`registration_refusal()`)
løber i rækkefølgen `prune_dead_clients()` → timebudget → antal pr. IP → loft for aktive (503), så de to 429'er
kan skelnes på teksten: *Too many registrations* er timen, *Too many registered clients from this address* er
rækkerne.

**Rækkefølgen er vigtig.** Knappen siger selv «Connect your AI client once now: each further attempt starts
filling the limits again.» Klik reset, og kør `--device` **én** gang — ikke en række forsøg, for hvert mislykket
registreringsforsøg tæller i samme vindue.

## 6. Sådan verificeres det

- `python3 tools/test_mcp_oauth.py` — 18 offline-checks, heraf tre nye: device-flowet gemmer tokens, en gemt
  device-klient registreres ikke igen, og `--device-poll` uden flow siger til.
- Mod det levende site: `python3 tools/mcp_oauth.py --device` skal printe en kode og en `verification_uri`, og
  `--device-poll` skal ende med `Har nu adgang: token gyldigt i 3600 s`.
- Metadata: `curl -s https://steamgears.eu/.well-known/oauth-authorization-server` viser
  `device_authorization_endpoint` og de tre grant-typer.

## 7. Hvad dette ikke løser

Device-flowet giver ti minutter, ikke uendelig tid. Skal adgangen køre uden ejerens hænder, er vejen stadig
refresh-tokenet i CI (`NOVAMIRA_REFRESH_TOKEN` + keep-alive-workflowen), eller `SGAP_MCP_Auth` i plugin'et, som
fornyer hvert 30. minut på sitet selv. Device-flowet er svaret på **godkendelsen**, ikke på fornyelsen.
