# Pinterest-tokenet: kilden bestemmer hvem der må forny — 7.64.7, 8/11 2026

**Målt:** `plugins/tests/smoke.php` **1105 → 1116 PASS / 0 FAIL**, `php -l` rent, zip byte-identisk med kilden.
**Ingen procent er flyttet:** rettelsen er en fejlrettelse i P1.5's allerede byggede 3,0 d, ikke nye dage.
Plugin står på **100 % (29,85 af 29,85 d)** for agent-scope, og P1.5's sidste **0,6 d** er fortsat ejer-blokeret.

## Hvad der var galt

7.64.6 begyndte at hente Pinterest-tokenet fra **Pinterest for WooCommerce** gennem integrationens egen
`get_access_token()` (optionen `pinterest_for_woocommerce_data` indeholder ciphertext, som svarer 401 hvis
man læser den direkte). Det var rigtigt. Tre ting omkring det var ikke:

| Fejl | Konsekvens | Rettelse i 7.64.7 |
| --- | --- | --- |
| Et 401 blev fornyet med pluginnets **egne** app-credentials, uanset hvilket token der var sendt | Fornyelsen roterer vores eget token — det der fejlede bliver aldrig sendt igen, og næste kald fejler på samme måde. Et **scope**-svar (`Missing: ['boards:read']`) blev fornyet forgæves, hvilket også koster et forsøg i Pinterests rate limit | Fornyelse kræver `token_source() !== 'woocommerce'` **og** `own_refreshable()`, og et scope-svar fornyes aldrig (`scope_denial()`) |
| `refreshable()` betød både »vi kan forny« og »der findes et token« | `status()` bad om app-credentials på et site der kørte fint på integrationens token, og `token()` kunne finde på at forny med det forkerte credential-sæt | Delt i `own_refreshable()` (egne app-credentials + refresh-token) og `refreshable()` (det ene eller det andet) |
| **Ethvert** 200-svar ryddede både `REFUSED` og `SCOPE` | `/user_account` svarer 200 på det samme token, så ét vellykket kald skjulte at `/pins` mangler `boards:read`. Kanalstatus gik fra `needs_scope` til `ready` uden at noget var blevet løst | Verdictet husker sit **endpoint** (`path`), og kun et 200 fra det endpoint der blev afvist rydder det. Et token-afslag ryddes stadig af ethvert 200, for det beviser tokenet |

## To ting mere, som rettelsen krævede

**Afvisninger skal kunne henføres til ét credential.** Integrationen roterer sit token af sig selv. En
`refused` skrevet før rotationen ville ellers blive hængende på det nye, virkende token. Verdictet gemmer nu
`source` **og** `fingerprint` — de første 8 hex-tegn af en SHA-256 af tokenet, aldrig tokenet selv, samme
skema `SGAP_Keys::record()` bruger i ændringsloggen. Stemmer fingerprintet ikke, ignoreres verdictet.
Verdicts skrevet før 7.64.7 har intet fingerprint og vises fortsat: en afvisning på skærmen er den sikre side.

**Udløbstiden skal læses uden at røre hemmeligheden.** `status()` læser kun `token_data.refresh_time` og
`token_data.expires_in` fra integrationens option og regner udløbstiden ud derfra; ciphertexten returneres
eller logges aldrig. Et udløbet integrationstoken siges nu at være udløbet — »reconnect that integration« —
i stedet for at bede ejeren indsætte et nyt token i vores eget felt, som integrationen ville overhøre.

## Testene der holder det

11 nye checks i `plugins/tests/pinterest.php`, alle gennem `SGAP_Pinterest::$transport` (ingen netværk, ingen
rigtig credential):

- et 401 på integrationens token giver **præcis ét** kald og **ingen** `/oauth/token`-anmodning;
- afvisningen attribueres til `woocommerce`, og `status()` navngiver kilden, læser udløbstiden og lækker
  hverken token eller ciphertext;
- et roteret integrationstoken arver ikke den gamle afvisning;
- et udløbet integrationstoken siges at være udløbet;
- et scope-afslag husker `/pins`, overlever at `/user_account` svarer 200, og ryddes først når `/pins` selv
  svarer 200.

I `plugins/tests/channel-health.php` tæller summen nu **alle fem** tilstande (`ready`, `recoverable`, `refused`,
`needs_scope`, `missing`) — den udelod `refused` og `needs_scope` og stoppede dermed med at gå op, netop når
en kanal var noget andet end klar — og konsistenskontrollen sender scope-verdictet med, så en `needs_scope`-række
ikke længere sammenlignes med en regel der kun kendte `refused`.

## Hvad der stadig mangler — og kun ejeren kan gøre

- **`boards:read` på Pinterest-appen.** Tokenet virker (`/user_account` 200 som `cpaleadsis`, id
  688980580401955331), men `/pins` afvises. Genforbind Pinterest med board-adgang, eller indsæt et
  developer-token med `boards:read` + `pins:read`. Først derefter kan Pin Analytics måles, og P1.5's 0,6 d
  kan lukkes.
- **7.64.7 skal installeres.** Repoet er på 7.64.7; live er 7.64.6. Et plugin-folder kan kun skiftes af
  WordPress selv, så det slutter med ejerens *Plugins → Update*-klik.
- **De fem andre kanaler** (Instagram, Bluesky, Mastodon, Telegram, Facebook) har intet token. De indtastes på
  **Settings → Keys** — aldrig i chat eller repo.
