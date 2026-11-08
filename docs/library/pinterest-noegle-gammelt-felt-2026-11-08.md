# Pinterest-nøglen lå i det gamle felt — og var udløbet

_Målt 8/11 2026 på steamgears.eu med **7.60.1** installeret og aktiv. P1.5's 0,85 d er **ikke** bogført:
den ene halvdel svarer 401, og den anden har ingen nøgle._

## Hvad der blev målt

Efter ejeren havde indtastet nøgler under **Settings → Keys**, var alle SGAP-nøgler stadig tomme — men ét
token lå et andet sted: under `sgap_pinterest_token`, det feltnavn der blev afregistreret i **7.33.3**, og
som intet har læst siden. Det er et rigtigt `pina_`-token på **101 tegn**.

| Måling | Før 7.60.1 | Efter 7.60.1 |
| --- | --- | --- |
| `pinterest_access_token` (længde) | 0 | **101** (identisk med det gamle felt) |
| `SGAP_Pinterest::configured()` | `false` | **`true`** |
| `SGAP_Pinterest::refreshable()` | `false` | `false` |
| `POST /sgap/v1/pinterest/refresh` | `configured:false` + "not connected" | **200 med rigtigt API-forsøg** |
| Pins / matched / updated | 0 / 0 / 0 | 0 / 0 / 0 |
| Fejl fra Pinterest | — | **HTTP 401: `Authentication failed.`** |
| Instagram `configured()` / token-længde | `false` / 0 | `false` / 0 (uændret) |

Svaret fra kørslen, ordret:

```json
{"configured":true,"pins":0,"matched":0,"updated":0,"unmapped":0,"orphaned":0,
 "errors":["Pin listing: Pinterest refused the access token (HTTP 401): Authentication failed.. Paste a fresh token, or save the app credentials and refresh token so it can renew itself."],
 "window":90,"at":"2026-11-08 11:47:42","status":{"configured":true,"refreshable":false,
 "access_token_saved":true,"refresh_token_saved":false,"token_valid":true,"pins_mapped":0,"last_ok":false}}
```

`token_valid: true` er værd at læse rigtigt: det betyder kun, at der ikke er registreret en udløbsdato —
ikke at Pinterest accepterer tokenet. Det gør Pinterest ikke.

## Hvorfor «ikke forbundet» var et forkert svar

7.33.3 fjernede feltet `pinterest_token` fra indstillingerne, fordi det var afløst af
`pinterest_access_token`. Et token indtastet **før** den version blev ikke flyttet, og fra da af læste
ingen det: modulet svarede `configured:false`, dashboardet sagde at kanalen ikke var forbundet, og der
fandtes ikke længere et felt hvor ejeren kunne se eller udskifte nøglen. Fejlen var ikke at nøglen var
udløbet — det var at systemet ikke kunne sige det.

## Rettelsen i 7.60.1

- `SGAP_Pinterest::saved_token()` læser `pinterest_access_token` og falder tilbage til det gamle navn
  (`const LEGACY`), så et tidligere indtastet token tæller som konfigureret og faktisk sendes med.
- `SGAP_Pinterest::migrate_legacy()` kører på `init` og kopierer tokenet ind i det felt Keys-kortet viser,
  så det kan ses, udskiftes og roteres. Den er idempotent: en gemt ny nøgle vinder altid, og det gamle
  felt lades urørt som tilbagerulningsvej.
- `token()` læser nu gennem `saved_token()`, så de to læsesteder ikke kan divergere.
- Seks smoke-checks (1010 → **1016 PASS**, 0 FAIL): fallback tæller som konfigureret, anmodningen sender
  det gamle token, kopien lander i det synlige felt, en ny nøgle overskrives ikke, og intet felt betyder
  stadig intet token.

Målt live efter installation: `sgap_version = 7.60.1`, `access_len = 101`, `migrated_equal = true`,
`configured = true`. Sitet svarer **200** på `/`, `/shop/` og `/wp-json/sgap/v1`; alle 29 værn grønne,
`projektstatus.py --check` uden drift.

## Hvad der stadig kræver ejeren

1. **Pinterest:** et **friskt** token — det gamle er udløbet (401). Enten et nyt access token indsat i
   *Settings → Keys → Pinterest → Access token*, eller app-credentials + refresh token, så det kan forny
   sig selv. Uden det kan `refreshable` aldrig blive `true`, og en Trial-nøgle udløber igen.
2. **Instagram:** der er **intet** token nogen steder (`instagram_access_token` og `instagram_user_id` er
   begge tomme — også efter indtastningen). Der skal et **professionelt konto-id** og et **long-lived user
   token** til, ellers svarer `configured()` fortsat `false`.

## Derfor er de 0,85 d ikke bogført

P1.5's live-verifikation kræver, at begge kanaler har hentet **rigtige** tal ind i `sgap_events`. Pinterest
svarede 401 (0 pins, 0 events), og Instagram har ingen nøgle. Der er ingen måling at bogføre på, og
totalen står derfor uændret på **29,60 af 30,45 d = 97,2 %**, med 0,85 d åbne. At skrive dem ind på et
`configured:true` eller en 200-status ville være at bogføre en indstilling som et resultat.
