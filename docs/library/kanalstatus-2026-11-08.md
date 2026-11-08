# P1.5: kanalstatus gjort målbar — 7.61.0, 8/11

P1.5 er det sidste åbne punkt i plugin-roadmappet (0,85 af 3,85 d). Selve indlæsningen er bygget
(P1.5a Bluesky/Mastodon, P1.5b Pinterest, P1.5c Instagram); det der manglede, var at kunne se **hvorfor** en
kanal er tavs, uden at gætte. Det er den ikke-blokerede del, og den er nu bygget, testet og målt live.

## Hvad der blev bygget

`includes/channel-health.php` (`SGAP_Channel_Health`) rapporterer pr. kanal — Pinterest, Instagram, Bluesky,
Mastodon, Telegram, Facebook — tre felter og én næste-handling:

| Felt | Betydning |
| --- | --- |
| `configured` | der ligger en credential (klassens egen `configured()`) |
| `refreshable` | credentialen kan fornyes af pluginnet selv |
| `state` | `ready` · `needs_refresh` · `missing_token` · `no_class` |
| `next` | én sætning om hvad der skal ske nu |

`classify()` er en ren funktion, så tilstandene kan testes uden netværk, og `report()`/`summary()` tæller
kanalerne sammen. `/wp-json/sgap/v1/channel-health` (GET) eksponerer det samme, beskyttet af
`SGAP_Abilities::can_manage` — målt **401** uden login, altså ikke offentligt.

## Tests

`plugins/tests/channel-health.php` tilføjer **11 checks** til smoke-suiten: de tre tilstande, at alle seks
kanaler rapporteres, at hver række har id/label/state/next, at rapporten **ikke** indeholder token-felter, at en
gemt Pinterest-token læses som `ready`, at en ryddet Telegram læses som `missing_token`, at hver række stemmer
med sine egne flag, at summen går op, og at ruten registreres under `sgap/v1`.

**Målt:** `plugins/tests/smoke.php` → **1027 PASS / 0 FAIL** (før 1016), `php -l` ren på alle filer,
`tools/fejlscan.py` → **0 fund** (version 7.61.0, zip byte-identisk med kilden, 46 filer, 349 946 bytes).

## Live

7.61.0 blev lagt op i 60 KB-bidder og installeret med `wp plugin install --force --activate`:
`{"name":"steamgears-autopilot","status":"active","version":"7.61.0"}`.

`SGAP_Channel_Health::summary()` live: **ready 1 · recoverable 0 · missing 5 · i alt 6**.

```
pinterest  ready          instagram  missing_token   bluesky   missing_token
mastodon   missing_token  telegram   missing_token   facebook  missing_token
```

**Vigtig sondring:** `ready` betyder »der ligger en credential, og refresh kan forsøges« — ikke »den virker«.
Pinterest står som `ready`, men det gemte token afvises stadig med **HTTP 401** ved refresh (målt 8/11, se
[`pinterest-noegle-gammelt-felt-2026-11-08.md`](pinterest-noegle-gammelt-felt-2026-11-08.md)). Derfor er
P1.5's 0,85 d **ikke** lukket: kun 0,25 d af dem er nu bygget og målt.

## Hvad der stadig kræver ejeren

| Kanal | Hvad mangler | Hvorfor det ikke kan gættes |
| --- | --- | --- |
| Pinterest | et gyldigt access-token (eller app-credentials + refresh-token) | tokenet er en personlig hemmelighed og må ikke indsættes i chat eller repo; det gemte svarer 401 |
| Instagram | professionelt konto-id + long-lived user token | kræver Meta-app og en IG Business-konto |
| Bluesky / Mastodon | app-password / instans-token | begge er kontospecifikke |
| Telegram | BotFather-token + chat-id | kræver ejerens bot |
| Facebook | side-token | Meta App Review |
