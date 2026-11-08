# Pinterest-analytics i pluginnet — P1.5b

**Dato:** 2026-11-07 · **Plugin:** 7.53.0 · **Status:** bygget og offline-verificeret; live-kørsel venter på et Pinterest-token

## 1. Hvad der blev bygget, og hvorfor

P1.5a læser Bluesky og Mastodon, fordi begge netværk svarer med offentlige tal (likes,
reposts, replies). Pinterest gør ikke: dets tal kommer fra **Pin analytics** i API v5, og
kun for pins som den konto, tokenet tilhører, ejer. Det er samtidig det eneste sted i
pluginnet hvor »blev pin'et set?« kan besvares — `sgap_cro` ved hvor mange der klikkede på
vores sporingslink, og WooCommerce ved hvad der blev solgt, men ingen af dem kender
impressions.

Modulet er `includes/pinterest.php` (`SGAP_Pinterest`) og lægger sig ved siden af P1.5a
uden at dele lagring:

| | P1.5a (Bluesky, Mastodon) | P1.5b (Pinterest) |
| --- | --- | --- |
| Event-typer | `likes`, `reposts`, `replies` | `impressions`, `saves`, `outbound_clicks` |
| Vindue | 30 dage | 90 dage (Pinterests eget loft) |
| Auth | ingen (offentlig læsning) | OAuth-token + rotation af refresh-token |
| Match til kø-posten | `external_id` (AT URI / status-id) | pin'ets `link` → `/go/<kø-id>/` |

At de to sæt event-typer er adskilte er ikke kosmetik: en `save` er en platformshandling,
en `click` er vores eget sporingslink, og en `order` er en WooCommerce-ordrer. Blander man
dem i samme sum, kan ingen af dem fortolkes bagefter. Derfor har `sgap_events` nu seks
platformstal, og `SGAP_Engagement::summary()` ser fortsat kun sine tre.

## 2. API-fakta, som koden hviler på (officiel v5-dokumentation)

| Fakta | Konsekvens i koden |
| --- | --- |
| `GET /v5/pins/{pin_id}/analytics` kræver `start_date` + `end_date`, og `start_date` kan **ikke være mere end 90 dage tilbage** | `WINDOW = 90`; `floor_date()` er dagens dato minus 89 dage, og `start_date()` hæver en gammel kø-post op til det gulv. Et pin fra januar bliver altså målt på de seneste 90 dage, ikke afvist |
| `metric_types` er en kommasepareret liste; svaret har `summary_metrics` pr. metrik-gruppe (`all` ved standard) | Der spørges om `IMPRESSION,SAVE,OUTBOUND_CLICK`, og `summary_metrics()` tager den første gruppe med et summary frem for at hardcode nøglen `all` |
| `summary_metrics` udelader metrikker uden data | En manglende metrik gemmes som **0**, ikke sprunget over — ellers ville summen arve den forrige kørsels tal |
| `GET /v5/pins` giver 25 pr. side som standard, højst 250, med `bookmark`-cursor | `PAGE = 100`, `MAX_PAGES = 10`; bookmark gemmes i en option, så en stor konto bliver gennemgået over flere dage i stedet for én lang kæde af kald |
| Pin-objektet har `link`, `id`, `created_at` | Matchningen sker på `link` — ikke på titel. Se §3 |
| Access-token lever **30 dage** (`expires_in` 2592000), continuous refresh-token **60 dage**, og **hver fornyelse roterer refresh-tokenet** | Det nye par gemmes med det samme (§4) |
| Fornyelse er `POST /v5/oauth/token` med **HTTP Basic** (`client_id:client_secret`) og form-encoded body | `refresh()` sender `grant_type=refresh_token`, `refresh_token` og `continuous_refresh=true` (påkrævet for apps oprettet før 25/9-2025, ignoreres af nyere) |
| 429 er rate limit, 401/403 er auth/scope | 401 fornyer én gang og prøver igen; 403 og 429 får deres egen forklarende fejl, og næste kørsel fortsætter |

## 3. Sådan matches et pin til en kø-post

Publisheren giver Publer kø-postens sporingsadresse (`/go/<id>/`) som pin'ets link. Det gør
linket til en **nøjagtig** identifikation, hvor en titel kun ville være et gæt. Reglen er
derfor:

1. `queue_id()` accepterer kun en `http(s)`-adresse på **denne** sides host (www. er
   valgfrit) og kræver stien `/go/<tal>/`. Et fremmed domænes `/go/42/` er ikke vores
   kvittering og giver 0.
2. Pin uden et sådant link tælles som **unmapped** — det er fx andres pins, eller pins der
   linker direkte til butikken.
3. Pin med et `/go/<id>/` hvor der **ikke** findes en publiceret Pinterest-kø-post inden
   for vinduet, tælles som **orphaned** — posten kan være slettet eller ældre end 90 dage.

Begge tal står på dashboardkortet. Et gæt ville se pænere ud og være forkert: en fremmed
pin med i snittet forvrænger hver efterfølgende beslutning om hvilket angle der virker.

## 4. Token-håndtering — den del der ellers fejler efter to måneder

Pinterest roterer refresh-tokenet ved **hver** fornyelse, og det gamle er dødt bagefter. Tre
ting følger af det, og alle tre er implementeret:

- **Skriv først, verificér bagefter.** Det nye access-token, dets udløb og det roterede
  refresh-token gemmes, før noget andet kan fejle. Går det galt mellem kald og skrivning,
  står man ellers med et brugt refresh-token og skal autorisere appen igen.
- **Forny før udløb, ikke ved 401.** Access-tokenet fornyes når det er inden for 3 dage af
  udløb (`MARGIN`). 401-stien findes stadig som sikkerhedsnet, men er ikke den normale vej.
- **Ingen konstant i `wp-config.php` for refresh-tokenet.** En konstant kan ikke opdateres af
  serveren, så et roteret token ville blive skrevet et sted intet læser. Er den alligevel
  sat, afvises fornyelsen med en forklaring — samme valg som P1.15 traf for MCP-tokenet.

Derudover: højst én fornyelse pr. fem minutter (`RENEW_WINDOW`), så en daglig cron og et
manuelt klik ikke brænder to refresh-tokens af på samme tid, og en fejl får en ærlig tekst
i stedet for at blive gentaget i det uendelige.

Et token der er indsat i hånden (fx et Trial-token fra developer-portalen) virker uden
app-credentials, men kan ikke forny sig selv. Det er tilladt — og statusfeltet siger det
højt: `refreshable: false`, og dashboardet forklarer hvad der mangler.

## 5. Hvad der er verificeret, og hvad der ikke er

**Verificeret offline** (766 smoke-checks, 0 fejl; `plugins/tests/pinterest.php` står for de
nye):

- Fornyelse: POST til `/v5/oauth/token`, Basic-auth med app-id og secret, form-body med
  `continuous_refresh`, nyt access-token + udløb gemt, roteret refresh-token gemt uden
  autoload.
- Afvist fornyelse bærer Pinterests egen tekst og når det daglige selvtjek; en anden
  fornyelse inden for fem minutter sendes ikke.
- Analytics-kaldet: `/pins/<id>/analytics` med de tre metrikker, Bearer-header (aldrig token
  i URL'en), `summary_metrics` læst, manglende metrik = 0, svar uden summary = fejl.
- 401 fornyer én gang og prøver kaldet igen.
- Pin-listen følger `bookmark` til enden, beholder `synced_at` for kendte pins, og rydder
  bookmarken når sidste side er læst.
- En kørsel: 1 unmapped + 1 orphaned + 2 matched, ét analytics-kald pr. pin, 403 på ét pin
  rapporteres mens det andet gemmes, og budgettet (`MAX_CALLS`) respekteres.
- 90-dages-gulvet: en gammel post måles kun på de seneste 90 dage; en frisk post beholder
  sin egen publiceringsdag.
- Dashboardkortet viser tallene, og en uforbundet konto forklares i stedet for at vise 0.

**Ikke verificeret:** ingen live-kørsel. Der findes intet Pinterest-token i repoet eller i
denne sandkasse, og svarenes præcise form er derfor kun prøvet mod stubbed responses bygget
efter dokumentationen. Første rigtige kørsel skal kontrollere tre ting mod Pinterests svar:
at `summary_metrics` ligger under `all`, at pin-`link` faktisk er vores `/go/`-adresse (og
ikke en UTM-variant Publer har skrevet om), og at `GET /v5/pins` returnerer `bookmark: ""`
på sidste side. Fejler en af dem, er det `summary_metrics()`/`queue_id()`/`scan()` der skal
rettes — ikke resten af modulet.

## 6. Sådan tages den i brug

1. Pinterest developer-app med scopes **boards:read** og **pins:read** (Trial-adgang er nok
   til egne pins).
2. Settings → Keys → **Pinterest analytics**: App ID (under Publishing), app secret,
   access token og refresh token. Access-tokenet alene virker, men kan ikke fornyes.
3. Kørsel sker dagligt via `sgap_pinterest_refresh` (Action Scheduler når WooCommerce
   leverer den, ellers WP-Cron), eller med `POST /wp-json/sgap/v1/pinterest/refresh`.
4. Resultatet læses på dashboardet (**Pinterest · last 90 days**) og i `status`/`problem`
   på det daglige selvtjek.
