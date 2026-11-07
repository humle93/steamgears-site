# Roadmap-afstemning for plugin-sporet — 7/11 2026

**Formål:** gøre `plugins/steamgears-autopilot/ROADMAP.md` §5 og `projektstatus.json` enige, linje for linje, så
procenten kan genberegnes ud fra kilden. Afstemningen er lavet fordi `projektstatus.json` havde **0,2 d i
nævneren som intet punkt dækkede** (P0 4,65 mod 4,70 · P1's linjer 15,05 mod 15,20), og fordi P3's total ikke
stemte med sine egne rækker.

**Resultat:** der findes **ingen uafsluttet post** bag de 0,2 d. Nævneren var regnet forkert tre steder, og de
tre fejl er rettet her — ikke ved at ændre en status, men ved at rette totalerne til summen af de dokumenterede
linjer. **Intet punkt er erklæret færdigt i dette pas**, og intet `done`-tal er hævet.

## 1. P0 — reliability

| Punkt | Kilde-anker | Dage | Status | Verifikation |
| --- | --- | --- | --- | --- |
| P0.1 alarmer | ROADMAP §5 P0.1 | 0,50 | ✅ 2/11 | `includes/alerts.php`; 6 t-dubletværn, loft 5 mail/t |
| P0.2 runway | ROADMAP §5 P0.2 | 0,50 | ✅ 2/11 | runway mod `alert_dry_days` i det daglige selvtjek |
| P0.2 kanal-loft | P0.2, 7.42.0 | 0,20 | ✅ 7/11 | `channel_caps` + `channel_runway()`, 11 checks (544 → 555 PASS) |
| P0.3 backoff | ROADMAP §5 P0.3 | 1,00 | ✅ 2/11 | 5 → 15 → 45 min i `scheduled_at`; tredje forsøg terminalt |
| P0.4 cron-ærlighed | ROADMAP §5 P0.4 | 0,50 | ✅ 7.18.0 + 7.33.0 | Site Health-test, `DISABLE_WP_CRON`-detektion, CC-link (8 CC-checks) |
| P0.4 cron-checks + host-cron | P0.4, 7/11 | 0,30 | ✅ 7/11 | fire checks på `cron_lag()`/`wp-cron.php`-linjen (662 → 666 PASS); ejerens URL-cron hver 5. min, Websundhed Good |
| P0.5 auditlog | ROADMAP §5 P0.5 | 1,00 | ✅ 2/11 | `includes/audit.php`, egen Publish log-side, retention 90 d |
| P0.6 dublet-værn | ROADMAP §5 P0.6 | 0,50 | ✅ 7.40.1 | `includes/duplicate.php`, 15 checks (479 → 494 PASS) |
| `/go/`-fallback | 7.32.0 (P0-scope) | 0,15 | ✅ 7/11 | produktlink uden kø-post går til produktet, 5 checks (404 PASS) |
| **P0 (P0.1–P0.6 + tilføjelser)** | | **4,65** | alle ✅ | |

**Hvad der var galt:** totalen sagde 4,70 d og lagde **CC-linket (0,20 d) til to gange** — det ligger allerede
inde i P0.4's 0,50 d, som rækken selv skriver (7.18.0 + 7.33.0) — mens **`/go/`-rettelsen (0,15 d) slet ikke var
talt med**. 4,00 (rækker) + 0,20 + 0,30 + 0,15 = **4,65**, hvilket er præcis det `done`-tal `projektstatus.json`
allerede havde. Totalen var altså 0,05 d for høj; `done` var rigtig.

P0.4's række var samtidig den eneste P0-række **uden ✅**, selv om den har været færdig og ejerbekræftet siden
7/11. Markeringen er tilføjet, så tabellen kan læses som det den er: alt P0-arbejde er lukket.

P0.7 (1,00 d) og P0.7c (0,25 d) bogføres som egne moduler i `projektstatus.json` (`p0s`, `p0c`) og er ✅.

## 2. P1 — parity with hosted tools

| Punkt | Kilde-anker | Dage | Status | Verifikation |
| --- | --- | --- | --- | --- |
| P1.1 pre-flight | §5 P1.1 | 1,00 | ✅ 2/11 (7.20.0) | 23 checks (128 → 151 PASS) |
| P1.2 remind | §5 P1.2 | 1,00 | ✅ 3/11 (7.27.0) | 20 checks (265 → 285 PASS) |
| P1.3 blackout | §5 P1.3 | 0,50 | ✅ 2/11 (7.21.0) | 21 checks (151 → 172 PASS) |
| P1.4 evergreen | §5 P1.4 | 2,50 | ✅ 6/11 (7.41.0) | 29 checks (494 → 523 PASS) |
| **P1.5 analytics** | §5 P1.5 | **3,85** | **3,0 ✅ + 0,85 åben** | P1.5a 0,5 (Bluesky/Mastodon) ✅ · P1.5b 1,0 (Pinterest, 52 checks, 714 → 766) ✅ · P1.5c 1,5 (Instagram, 50 checks, 770 → 820) ✅ · **live-verifikation 0,85 udestår** |
| P1.6 quality gate | §5 P1.6 | 1,00 | ✅ 2/11 (7.22.0) | 36 checks (172 → 208 PASS) |
| P1.7 site-SEO | §5 P1.7 | 1,00 | ✅ 2/11 (7.19.0) | 16 checks (112 → 128 PASS) |
| P1.8 utm_campaign | §5 P1.8 | 0,25 | ✅ 2/11 (7.24.0) | 10 checks (219 → 229 PASS) + 3 integration |
| P1.9 læsende MCP-værktøjer | §5 P1.9 | 0,25 | ✅ 6/11 (7.35.0) | 6 checks |
| P1.10 værktøjskatalog | §5 P1.10 | 0,25 | ✅ 6/11 (7.36.0) | 4 checks |
| P1.11 hele sitets abilities | §5 P1.11 | 0,50 | ✅ 6/11 (7.37.0) | 9 checks |
| P1.12 ability-indeks + Tools | §5 P1.12 | 0,75 | ✅ 6/11 (7.38.0) | 445 checks i suiten |
| P1.14 model-dropdown | §5 P1.14 | 0,30 | ✅ 6/11 (7.39.0) | 9 checks |
| P1.15 MCP-fornyelse | §5 P1.15 | 0,50 | ✅ 7/11 (7.44.0) | 20 checks (564 → 584 PASS) |
| P1.16 Woo/Printful-modul | §5 P1.16 | 0,50 | ✅ 7/11 (7.45.0) | 43 checks (584 → 627 PASS) |
| P1.17 kostpris-import | §5 P1.17 | 0,30 | ✅ 7/11 (7.46.0) | 16 checks (627 → 643 PASS) |
| P1.18 nøgler på ét sted | §5 P1.18 | 0,50 | ✅ 7/11 (7.47.0) | 19 checks (643 → 662 PASS) |
| queue-post angle/format/kampagne | 7.25.0 (P1-scope) | 0,10 | ✅ 2/11 | krævet fordi OAuth-tokenet ikke gælder pluginets REST-namespace |
| **P1 (uden P1.19–P1.22)** | | **15,05** | 14,20 ✅ · **0,85 åben** | |

**Hvad der var galt:** totalen sagde 15,20 d. P1's egne rækker (uden P1.19–P1.22, som bogføres som `p1d`–`p1g`)
summer til 14,95 d, og **queue-post-arbejdet på 0,10 d var aldrig lagt til**. 14,95 + 0,10 = **15,05**, og
15,05 − 14,20 = **0,85 = præcis P1.5's live-verifikation**. Den ekstra 0,25 d, der lå i nævneren, var
**P1.19 talt med to gange** (den ligger både i den gamle P1-total og i modulet `p1d`).

P1.19 (0,25 · 7.52.0), P1.20 (0,25 · 7.54.0), P1.21 (0,25 · 7.55.0) og P1.22 (0,50 · 7.57.0) er ✅ som egne
moduler, og P2.6 Facebook (1,50 · 7.58.0) ligeså.

## 3. P3 — hygiene and proof

| Punkt | Kilde-anker | Dage | Status | Verifikation |
| --- | --- | --- | --- | --- |
| P3.1 død kode + `labor_rate` | §5 P3.1 | 0,25 | ✅ 7.33.3 + 7.59.0 | fem smoke-checks i `plugins/tests/roi.php`; ROI-kortet læst tilbage på sitet: `0 h at 250.00/h (budget ≈ 4 h)` |
| P3.2 feed/reposts-tests | §5 P3.2 | 0,50 | ✅ 3/11 (7.28.0) | 22 checks + `/snapshot` 401 |
| P3.3 PHP/WP-matrix | §5 P3.3 | 0,25 | ✅ (som S0.3) | CI-matrix PHP 8.0–8.5, WP 6.4/7.1/latest |
| P3.4 changelog i update-modal | §5 P3.4 | 0,50 | ✅ 6/11 (7.34.0) | 6 checks |
| P3.5 uninstall mod DDL | §5 P3.5 | 0,25 | ✅ 3/11 (7.28.0) | ni tabeller kræves droppet |
| P3.6 fejlsøgning som fast kørsel | §5 P3.6 | 0,25 | ✅ 7/11 (7.53.1) | `tools/fejlscan.py`, 13 fixtures, værn 23–24 |
| **P3** | | **2,00** | alle ✅ | |

**Hvad der var galt:** totalen sagde 2,75 d, mens rækkerne summer til 2,00 d. De 0,75 d er aldrig blevet
fjernet, da P3.1's Etsy-halvdel (0,25 d) blev flyttet til P2.4 og P3.6 senere blev sat ned til 0,25 d — totalen
blev blot aldrig regnet om. Der er **ingen P3-række** (og har aldrig været, jf. `git log -S "P3.7"`) bag de
0,75 d, så de er strøget. Både `done` og `total` er sat til 2,00, så P3 stadig er 100 %.

## 4. De øvrige moduler

| Modul | Dage | Status | Kilde |
| --- | --- | --- | --- |
| P0.7 scheduler-adapter | 1,00 | ✅ 7.49.0 + 7.50.0 | 20 checks (679 → 700 PASS) |
| P0.7c statuskort | 0,25 | ✅ 7.51.0 | 6 checks |
| P1.19 dashboard-sektioner | 0,25 | ✅ 7.52.0 | 8 checks (706 → 714 PASS) |
| P1.20 Settings/Queue/Studio | 0,25 | ✅ 7.54.0 | 11 checks (770 → 781 PASS) |
| P1.21 AI Drafts/Publish log | 0,25 | ✅ 7.55.0 | 7 checks (781 → 788 PASS) |
| P1.22 fejlsøgningsside | 0,50 | ✅ 7.57.0 | 67 checks (838 → 905 PASS) |
| P2.6 Facebook-side | 1,50 | ✅ 7.58.0 | 82 checks (905 → 987 PASS) |

## 5. Summen

| | Før afstemning | Efter afstemning |
| --- | --- | --- |
| P0 | 4,65 / **4,70** | 4,65 / **4,65** |
| P1 | 14,20 / **15,20** | 14,20 / **15,05** |
| P2 | 4,75 / 4,75 | 4,75 / 4,75 |
| P3 | 2,75 / **2,75** | **2,00 / 2,00** |
| P0.7 + P0.7c + P1.19–P1.22 + P2.6 | 4,25 / 4,25 | 4,25 / 4,25 |
| **I alt** | **30,35 af 31,40 = 96,7 %** | **29,60 af 30,45 = 97,2 %** |
| **Åbent** | 1,05 d | **0,85 d = P1.5's live-verifikation** |

De 0,95 d der er forsvundet ud af nævneren, og de 0,75 d der er forsvundet ud af tælleren, var alle fantom:
CC-linket talt to gange, `/go/` udeladt, P1.19 talt to gange, queue-post udeladt og P3's total aldrig
regenet. **Procenten stiger 0,5 pp som en følge af det — ikke fordi noget er blevet udført.** Det er hele
pointen med tabellen: efter den kan åbent regnes direkte som `total − done` og give præcis ét punkt.

## 6. Det ene åbne punkt

**P1.5's live-verifikation, 0,85 d.** Målt 7/11 på sitet: `sgap_pinterest_access_token`,
`sgap_pinterest_refresh_token`, `sgap_pinterest_client_id`, `sgap_pinterest_client_secret`,
`sgap_instagram_access_token` og `sgap_instagram_user_id` er alle tomme, `SGAP_Diagnostics::checks()` svarer
`pinterest: off` og `instagram: off`, og de to ruter svarer 200 med deres egen besked:

* `POST /wp-json/sgap/v1/pinterest/refresh` → »Pinterest analytics is not connected: save a Pinterest access
  token, or the app credentials plus a refresh token under Settings → Keys.«
* `POST /wp-json/sgap/v1/instagram/refresh` → »Instagram insights is not connected: save the professional
  account id and a long-lived Instagram User token under Settings → Keys.«

Det er ikke en kodefejl: begge ruter svarer 200 og navngiver den indstilling der mangler. Nøglerne er ejerens
og må ikke gå gennem chat eller repo — derfor står punktet åbent, og derfor er det det eneste.
