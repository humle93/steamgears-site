# Runway pr. kanal — så en tør kanal ikke skjules af en sund total

**Steamgears Autopilot 7.43.0 · P0.2 · 7. november 2026**

## Kort fortalt

Runway blev målt som hele køen divideret med dagscappen. Det tal kan være sundt, mens én kanal er ved at
stoppe: 40 Facebook-poster og 0 Pinterest-poster ved cap 8 giver **5,0 dage** — men Pinterest går i stå i
morgen. Alarmen så på totalen og sagde ingenting.

7.42.0 giver hver kanal sin egen cap og sin egen runway, så en tør kanal navngives i både den øjeblikkelige
mail og den daglige digest.

## Hvad der blev bygget

| Del | Opførsel |
| --- | --- |
| `channel_caps` (Settings → Alerts) | Fx `pinterest=2, facebook=3`. Læses af `SGAP_Alerts::caps()`; ukendte kanaler, tomme navne og tal uden for 1–50 ignoreres |
| `channel_runway()` | Ét `GROUP BY channel` over `status='queued'`; hver kanal måles mod sin egen cap, ellers `daily_cap` |
| `dry_channels()` | Kanaler under `alert_dry_days`. En kanal **med** cap tæller altid; en uden cap kun mens den har noget i køen |
| Immediate | Egen mail pr. kanal-sæt, emne »Steamgears: pinterest is running dry« ved én kanal; nøglen er `dry:<kanaler>` så samme sæt ikke mails igen inden for vinduet |
| Daily digest | Linjen »Channels below the warning level: …« plus tabellen pr. kanal; gaten tæller nu også tørre kanaler, så en digest ikke udelades når kun én kanal er tør |
| `alert_dry_days = 0` | Slår både total- og kanal-alarmen fra |

## Beslutninger og hvorfor

- **Cap er en plan, ikke et løfte fra publisheren.** Derfor er en tom kanal *med* cap et hul, mens en kanal
  *uden* cap ikke er det — ellers ville hver kanal uden cap stå som tør fra første dag.
- **Ukendt kanal ignoreres.** En tastefejl (`pinterest99`) må ikke give en evig alarm om en kanal der aldrig
  poster.
- **Loftet 1–50.** 0 ville give division med nul; 50 ligger over alle reelle daglige volumener.
- **`channel_caps` er et reelt loft (7.43.0).** En kanal med cap, der har publiceret eller sender sin cap i dag,
  springes over i udtrækket. Resten af køen går stadig ud, og de ventende poster for den kanal bliver i køen
  til i morgen. Kanaler uden cap påvirkes ikke. Det er `SGAP_Queue::full_channels()`, der måler det.

## Verifikation

- `php -l` uden fejl på `alerts.php`, `core.php`, `smoke.php` og hovedfilen.
- Smoke: **544 → 555 PASS** i 7.42.0 (11 nye checks på stubbede kø-rækker), og **→ 559 PASS, 0 FAIL** i 7.43.0
  (3 nye checks for loftet: fuld kanal rapporteres, under cap er ikke fuld, og uden cap er ingen fuld; `claim()`
  springer en fuld kanal over i SQL). Teststubbens `get_var` kan nu
  modtage en liste, så ét `after_health`-kald kan give forskellige tal til runway og til failed-tællingen.
- Testen fangede først en fejl i **testdata**: Facebook med 8 poster ved cap 8 er også tør, så mailen sagde
  korrekt »2 channels«. Testen blev rettet — koden var rigtig.

## Grænser

- Kun `status='queued'` tælles; `failed` har sin egen alarm, og `sending` er i gang.
- Ingen historik-prognose (»5 dage til 14. nov«); det er ikke bygget.
- Ikke målt på live-sitet — kun i smoke-suiten.

## Filer

`includes/alerts.php` (caps, channel_runway, dry_channels, after_health), `includes/core.php` (standard,
felt, hjælpetekst, `full_channels()` og `claim()`), `plugins/tests/smoke.php` (14 checks), `ROADMAP.md`
(P0.2-rækken og changelog), `README.md` (channel_caps-afsnit).
