# `labor_rate` bliver læst — P3.1 lukket (7.59.0)

**Dato:** 7/11 2026 · **Release:** 7.59.0 · **Roadmap-punkt:** P3.1, de sidste 0,05 d · **Plugin-roadmap:** 96,5 → 96,7 %

## Problemet

`labor_rate` var den sidste indstilling i pluginnet der blev **registreret, vist og gemt — og aldrig læst**.
Den har stået under Settings → Economics siden 7.15.0 med labelen »Your hourly rate« og en standardværdi på
250, men både ROI, ROAS og alle rapporter regner med `monthly_budget` og `target_roi`. Ejerens tal blev altså
gemt uden at ændre noget som helst, og ROADMAP §8 punkt 6 havde derfor stillet tre udgange op: **fjern** feltet,
**brug** det, eller **omdøb** det til det, det reelt er.

## Valget, og hvorfor det ikke er et gæt

De to første udgange kræver begge noget agenten ikke kan skaffe:

* **fjern** kræver ejerens godkendelse, fordi feltet er synligt og sat til 250 — at slette en indstilling ejeren
  har udfyldt er en produktbeslutning, ikke en oprydning.
* **brug** som *kostpris* kræver et **minutforbrug pr. opslag**, som hverken pluginnet eller repoet kan måle.
  Enhver omkostningsberegning ville være et opdigtet tal klædt ud som økonomi.

Den tredje udgang kræver intet nyt tal: den bruger den pris ejeren **allerede** har sat, og den eneste
aritmetik er en division. Derfor er det den, der er gennemført — og feltets label »Your hourly rate« dækker nu
præcis det, koden gør.

## Hvad der blev bygget

```php
public static function hours(float $amount, float $rate): ?float {
    return $rate > 0 ? round($amount / $rate, 1) : null;
}
```

ROI-kortet (Steamgears → Reports) læser indstillingen og viser den som en fjerde størrelse ved siden af
omsætning, budget, ROI og ROAS:

> 30-Day Attributed Revenue: 0.00 · Monthly Budget: 1,000.00 · ROI: -100% (Target: 200%) · ROAS: 0x ·
> **Labour equivalent: 0 h at 250.00/h (budget ≈ 4 h)**

Er prisen 0, står der »Labour equivalent: off (set your hourly rate to see it)« i stedet for en division med
nul. ROI, ROAS og target er **urørte** — ingen økonomi er ændret, kun det faktum at feltet nu bliver læst.

## Verifikation

**Offline (grøn test).** `plugins/tests/roi.php` er nyt og køres af `plugins/tests/smoke.php`, fem checks:

| Check | Bevis |
| --- | --- |
| division | `hours(1000, 250) === 4.0`, `hours(250, 250) === 1.0` |
| nul-pris | `hours(1000, 0) === null`, `hours(0, 0) === null` |
| afrunding | `hours(100, 300) === 0.3` |
| kortet læser indstillingen | HTML'en indeholder `Labour equivalent:</strong> 4 h at 250.00/h` og `budget &asymp; 4 h` |
| nul-pris i kortet | HTML'en indeholder `Labour equivalent:</strong> off (set your hourly rate to see it)` |

Hele suiten: **995 PASS, 0 FAIL** (990 før). `tools/fejlscan.py`: ingen fund. Zip'en er genopbygget og
byte-identisk med kilden.

**Live (læst tilbage fra sitet).** 7.59.0 er installeret og aktiv på steamgears.eu gennem Novamira
(`wp plugin install … --force` fra repoets zip), og kortet blev renderet server-side på sitet:

```json
{"version":"7.59.0","active":true,"rate":250,"hours_1000_250":4,"hours_zero":null,
 "has_labour":true,"line":"Labour equivalent:</strong> 0 h at 250.00/h (budget &asymp; 4 h)"}
```

Sitet svarer fortsat **200** på `/`, `/shop/` og `/wp-json/sgap/v1`.

## Det ene punkt der stadig er åbent

**P1.5's live-verifikation (0,85 d).** Koden til Pinterest- og Instagram-analytics er bygget (7.53.0 og
7.56.0) og dækket af smoke-checks, men der er **ingen tokens på sitet**, så API-svarene kan ikke fremkaldes.
Målt 7/11 er `sgap_pinterest_access_token`, `sgap_pinterest_refresh_token`, `sgap_pinterest_client_id`,
`sgap_pinterest_client_secret`, `sgap_instagram_access_token` og `sgap_instagram_user_id` alle tomme, og de to
endepunkter svarer præcis det:

```json
{"/sgap/v1/pinterest/refresh": {"status":200, "configured":false,
  "error":"Pinterest analytics is not connected: save a Pinterest access token, or the app credentials plus a refresh token under Settings → Keys."},
 "/sgap/v1/instagram/refresh": {"status":200, "configured":false,
  "error":"Instagram insights is not connected: save the professional account id and a long-lived Instagram User token under Settings → Keys."}}
```

Det er ikke en fejl i koden: begge ruter svarer 200 og siger hvad der mangler. Det er ejerens to nøgler, som
ikke må gå gennem chat eller repo. Når de er indtastet i wp-admin, er verifikationen to kald til de samme
ruter, og de 0,85 d kan bogføres på målte tal.

## Status

| Før | Efter |
| --- | --- |
| 96,5 % — 30,3 af 31,4 d | **96,7 % — 30,35 af 31,4 d** |
