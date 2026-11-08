# P1.5 kan måles nu — men den er ikke målt: 7.64.9, 8/11 2026

**Målt:** `plugins/tests/smoke.php` **1 118 → 1 128 PASS / 0 FAIL**, `tools/test_live_channels.py` **11 tests, OK**,
`php -l` rent, `tools/fejlscan.py` 0 fund (version, zip og includes konsistente).
**Ingen procent er flyttet:** læsefladen er en del af P1.5's allerede byggede 3,0 d; de sidste **0,6 d** er
fortsat ejer-blokeret. Plugin står på **100 % (29,85 af 29,85 d)** for agent-scope.

## Spørgsmålet der ikke kunne besvares

P1.5's acceptkrav er ikke »koden kører«, men **»platformtallene lander i `sgap_events` pr. kanal«**. Indtil
7.64.9 fandtes der ingen flade der svarede på det:

| Flade | Hvad den svarede på | Hvorfor det ikke var nok |
| --- | --- | --- |
| `SGAP_Channel_Health::report()` + `/sgap/v1/channel-health` | kan næste refresh *køre*? (`ready`, `needs_refresh`, `missing_token`, `refused`, `needs_scope`, `no_class`) | siger intet om hvorvidt det sidste refresh *landede* noget — en `ready` kanal kan være tom |
| `status`-abilityen | vores egne `publish`/`click`/`order` pr. kanal, 30 dage | platformstallene er andre event-typer og lå uden for svaret |
| Admin-kortene (Pinterest, engagement, Instagram, Facebook) | platformstal, visuelt | kun i ejerens browser; et menneskes aflæsning er ikke et bevis, og Telegram har intet kort |
| `/sgap/v1/channel-health` udefra | 401 uden login | kunne ikke kaldes af CI |

Det gjorde P1.5 til et spørgsmål om tillid: ejeren kunne se et dashboard, men hverken agenten eller CI kunne
efterprøve tallet. Det er den mangel 7.64.9 lukker.

## Hvad der blev bygget

**`SGAP_Channel_Health::PLATFORM_TYPES`** navngiver de event-typer der er *platformens* tal, pr. kanal:
Pinterest `impressions`/`saves`/`outbound_clicks`, Instagram `views`/`likes`/`comments`/`shares`/`saves`,
Bluesky og Mastodon `likes`/`reposts`/`replies`, Facebook `impressions`/`reach`/`reactions`/`post_clicks` —
og **Telegram ingen**, fordi Bot API ikke har tal pr. opslag.

**`analytics($days = 90)`** læser `sgap_events` i vinduet og svarer pr. kanal med `measured` (tal landet),
`empty` (intet i vinduet) eller `no_metric` (kanalen kan ikke måles), plus `counts`, `total`, `rows` og
`last_at`. Tre regler er indbygget, og de er alle sammen fejl der ville gøre svaret forkert:

- vores egne `publish`/`click`/`order` tælles **ikke** med — spørgsmålet er hvad platformen rapporterede,
  ikke hvad vi gjorde;
- `likes` tælles **pr. kanal**: fire platforme skriver samme type, så en sum uden kanal ville lægge fire
  platforme sammen;
- `10 000` dage klemmes til **365**, og standarden er **90**, fordi det er Pinterests eget loft.

**Ability `channel-health`** (read-only, administrator) returnerer begge halvdele — kanalernes tilstand og de
landede tal — og `/sgap/v1/channel-health` får samme `analytics`-blok, så REST og MCP ikke kan svare
forskelligt.

**`tools/live_channels.py`** kalder abilityen gennem Novamira-fladen og svarer med en dom:

| Dom | Exit | Betydning |
| --- | --- | --- |
| `bestået` | 0 | hver kanal med en metric har landet mindst én række |
| `blokeret` | 3 | mindst én kanal kan ikke måles endnu (token eller scope mangler) — ikke en fejl |
| `fejlet` | 1 | **et virkende token landede intet**, eller forbindelsen selv fejlede |
| — | 2 | intet credential: intet er målt, og intet er skrevet |

`fejlet` er den række der er pointen. `blokeret` er den række der forhindrer at en tom kanal bliver meldt som
en måling — og den dækker også et plugin der **ikke har abilityen endnu**: live kørte 7.64.6 da målingen blev
bygget, så den natlige kørsel ville ellers have meldt rødt på noget ejerens *Plugins → Update* løser.
Forskellen står i koden, ikke i en læser.

Målingen kører nu i [`novamira-keepalive.yml`](../../.github/workflows/novamira-keepalive.yml) med det netop
fornyede access-token og skriver `reports/live-kanaler-<dato>.md`. Exit 3 giver en `::notice`, exit 1 en
`::error`.

## Hvad testene holder

10 nye smoke-checks i `plugins/tests/channel-health.php` gennem det stubbede `$wpdb`: at en tom tabel læses
som `empty` og ikke som en målt nul; at en `click`-række fra os selv ikke bliver et platformstal; at `likes`
fra Instagram og Bluesky ikke lægges sammen; at Telegram er `no_metric`; at forespørgslen er vinduesbegrænset;
og at et for stort vindue klemmes. `plugins/tests/admin-tools.php` kræver nu **41** abilities og at
`channel-health` er read-only og svarer med begge halvdele. 11 Python-tests i `tools/test_live_channels.py`
dækker dommen: `ready` + tom er rød, manglende token er blokeret, `no_metric` er ok, et ældre plugin uden
`analytics` er ikke bestået, og tokenet optræder aldrig i rapporten.

## Hvad der stadig mangler — og kun ejeren kan gøre

Målingen er **ikke kørt**. Den ville svare `blokeret`, fordi ingen af de tre forudsætninger er på plads:

1. **Plugins → Update** til **7.64.9** — sitet kører 7.64.6; abilityen findes ikke før opdateringen.
2. **Pinterest `boards:read`** — tokenet virker (`/user_account` 200 som `cpaleadsis`), men `/pins` afvises.
3. **Tokens til Instagram, Bluesky, Mastodon, Telegram og Facebook** på **Settings → Keys**.

Derefter er beviset én kommando: `python3 tools/live_channels.py` — eller næste natlige kørsel, der gør det
af sig selv og lægger svaret i `reports/`.
