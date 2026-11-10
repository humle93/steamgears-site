# OpenRouter-headeren — hvorfor nøgletesten svarede »Missing Authentication header«

**Målt 10/11 2026 · rettet i 7.70.1 · kilden er koden og testen, ikke et live-kald**

Nøgletesten mod OpenRouter svarede `401 Missing Authentication header`, selv om der stod en nøgle i feltet
og beskeden i 7.69.1 allerede havde lært at sige, at headeren manglede. Diagnosen fra 7.69.1 forklarede
svaret, men den **fik ikke headeren til at nå frem**. Denne runde gik efter requesten selv.

## 1. Hvad der faktisk blev sendt

Indtil 7.70.1 blev `Authorization`-headeren bygget **to steder**: i `SGAP_KeyCheck::check_openrouter()` (den
test ejeren kører) og i `SGAP_AI::post_backup()` (den request der faktisk redder en generation). De lignede
hinanden, men var ikke den samme kode, og kun testen blev dækket af en check. En test kan derfor bevise en
header, som produktionskaldet aldrig sender — og det var præcis den slags afvigelse der gjorde fejlen så
svær at se.

Nøglen blev desuden kun kørt gennem `trim()`. Det fjerner mellemrum i **enderne**, ikke et linjeskift eller
et usynligt tegn **inde i** værdien. Et tegn som U+200B (zero-width space), U+00A0 (hårdt mellemrum) eller
et `\r\n` midt i en indsat nøgle giver en header-værdi, som cURL afviser eller afkorter, eller som
gatewayen ikke kan læse som et bearer-token. Svaret bliver så »der var ingen Authorization-header« — ikke
»nøglen er forkert«. Det er den ene vej til fejlen.

Den anden vej er lige så konkret: WordPress sender **alle** udgående requests gennem filteret
`http_request_args`. Et sikkerheds- eller cache-plugin, der fjerner `Authorization` fra udgående kald, giver
præcis samme svar, uden at nøglen er involveret. Indtil nu pegede beskeden i begge tilfælde på nøglen.

## 2. Rettelsen

Fire små steder i `includes/generator.php` og ét i `includes/keycheck.php`:

| Funktion | Hvad den gør |
| --- | --- |
| `SGAP_AI::bearer($key)` | Fjerner alle kontroltegn, mellemrum og usynlige tegn (U+00A0, U+200B–200D, U+2060, U+FEFF, U+2028/2029) fra tokenet, så headeren altid er én linje med token-tegn. Falder tilbage til byte-vis fjernelse, hvis strengen ikke er gyldig UTF-8 |
| `SGAP_AI::openrouter_headers($key)` | Den ene header-opbygning: `Authorization: Bearer …`, `Content-Type`, `HTTP-Referer`, `X-OpenRouter-Title` |
| `SGAP_AI::openrouter_post($key, $body, $timeout)` | Den ene POST til `https://openrouter.ai/api/v1/chat/completions` — brugt af både nøgletesten og failoveren |
| `SGAP_AI::stripped_by($key)` | Kører `http_request_args` på en kopi af requesten og svarer, om headeren overlever. Navngiver de callbacks der fjerner den (`SGAP_AI::http_filter_names()`) |
| `SGAP_KeyCheck::check_openrouter()` | Bruger `openrouter_post()`; ved »missing header« skelnes der nu mellem tre årsager: et filter på sitet, en header der forlod serveren korrekt (så ligger tabet uden for sitet), og en nøgle med forkert form (7.69.1) |

Rækkefølgen i beskeden er bevidst: form først (koster ingen request), så requesten, så filter-tjekket.
Ingen af dem citerer nøglen — kun længde og header-navn.

## 3. Verifikation

Seksten checks i `plugins/tests/smoke.php`, hvoraf elleve er nye i 7.70.1. De ser på **de argumenter
plugin'et giver WordPress' HTTP-lag** (`$GLOBALS['http_calls']`), ikke på en sætning bagefter:

- nøjagtig én POST til den faste OpenRouter-adresse,
- `Authorization` er `Bearer sk-or-v1-abc123`, også når den gemte nøgle indeholder U+200B,
- header-værdien matcher `^Bearer [A-Za-z0-9._-]+$` — én linje, ingen mellemrum,
- generatoren sender samme rensede header, og `openrouter_post()` giver byte-identiske headers som
  `openrouter_headers()` (test og produktion kan ikke længere drive fra hinanden),
- en installeret `http_request_args`-filter, der fjerner headeren, **opdages**, og beskeden navngiver
  filteret i stedet for at sende ejeren til openrouter.ai/keys,
- uden et sådant filter siger beskeden, at headeren overlevede sitets filtre — altså ligger tabet mellem
  serveren og OpenRouter,
- nøglen optræder ikke i nogen af beskederne.

**Regressionen er bevist, ikke antaget:** med `bearer()` midlertidigt sat tilbage til `trim()` fejler fire
af de nye checks (`the request carries Authorization: Bearer …`, `the header value is one line …`,
`whitespace … removed from the token`, `the generator sends the same cleaned header`). Med rettelsen er
suiten **1 443 PASS / 0 FAIL** (1 432 før).

## 4. Hvad der stadig ikke er målt

- **Intet kald er gået til OpenRouter herfra.** Der findes ingen nøgle i sandkassen, og ejerens nøgle
  bruges ikke. Det der er bevist, er at requesten **bygges** med en korrekt Bearer-header, og at et filter
  på sitet ville blive opdaget.
- **Værtens proxy kan ikke testes indefra.** Hvis `stripped_by()` svarer »intet filter« og OpenRouter
  stadig melder manglende header, ligger tabet uden for WordPress: så skal Simply spørges, om et udgående
  proxy fjerner `Authorization`, eller testen køres fra et andet netværk.
- **7.70.1 er ikke installeret på sitet** (målt senest 7.65.5, 9/11). Rettelsen virker først efter
  ejerens *Plugins → Update*.
- **`integrations.php` er ikke udvidet.** Den kører kun i CI med rigtig WordPress og MySQL; den nye
  dækning ligger i smoke-suiten, som er den der kan køres uden en database.
