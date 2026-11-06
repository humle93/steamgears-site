# Natlig niche-scanning i CI — gaten der passer sig selv

Workflow: [`.github/workflows/niche-scan.yml`](../../.github/workflows/niche-scan.yml) ·
værktøj: [`tools/niche_scanner.py`](../../tools/niche_scanner.py) · søgeord:
[`tools/niche_seeds.txt`](../../tools/niche_seeds.txt)

Scanneren kræver tre måledage før en række kaldes «Anbefalet». Indtil nu kunne de dage kun komme
fra kørsler i hånden, så gaten var i praksis en påmindelse. Fra 3/11 2026 giver en natlig kørsel
dem: den scanner de 11 søgeord, skriver rapporten og committer kørselsdagen til `main`.

## Hvad kørslen gør

| Trin | Handling |
| --- | --- |
| 1 | Venter til kl. 02:30 dansk tid (samme sommertids-gate som den natlige push: begge UTC-timer, springer over hvis den lokale time ikke er 2) |
| 2 | Tjekker om der er et access-token **eller** et komplet sæt refresh-oplysninger — intet af det: scanningen springes over med en `::notice`, og intet skrives |
| 3 | Kører `tools/test_niche_scanner.py` (48 tests, offline) som værn før et rigtigt kald |
| 4 | Scanner `tools/niche_seeds.txt` mod `SE+DK+FI+NO`, `--trend-type seasonal`, og bruger `docs/library/tiktok-creative-center.csv` hvis filen findes |
| 5 | Skriver måledage og gate-status i job-summaryen |
| 6 | Committer `reports/niche-scan-<dato>.md`, `.json` og `reports/niche-scan-history.json` og pusher med tre genforsøg |

Kørslen ligger en halv time efter den natlige push, så de to ikke skriver i samme fil samtidig.
Den rører kun `reports/`, ikke status-filerne.

## Gaten: kun en måling tæller som en dag

`reports/niche-scan-history.json` er det eneste sted dagene står. Formatet er

```json
{ "min_days": 3, "keywords": { "herbarium print": ["2026-11-01", "2026-11-02", "2026-11-03"] } }
```

og `Status` bliver «Anbefalet (3 dage)» først når listen har tre datoer. Den natlige kørsel gør
gaten selvvedligeholdende — men kun fordi den ikke må tælle et forsøg som en måling.

**Det var ikke tilfældet i den første version.** To fejl blev fanget, da kørslen skulle bygges:

1. `main()` skrev dagens rapport **også når ingen kilde gav data**, så en nat uden token ville
   overskrive den sidste gode rapport med en tom.
2. Historikken blev skrevet med `[r.keyword for r in rows] or terms` — altså **alle seeds** når
   `rows` var tom. En forgæves kørsel ville dermed skrive alle 11 søgeord ind som målt i dag, og
   efter tre forgæves nætter ville gaten stå «Anbefalet» uden en eneste måling.

Nu returnerer en kørsel uden data exit-kode 2, skriver intet og lægger kun de søgeord i
historikken som faktisk blev målt. To nye tests holder det fast:
`test_cli_without_data_writes_nothing_and_keeps_gate` og
`test_cli_records_only_measured_keywords`.

Hvad der stadig ikke kan afgøres af koden: om de tre måledage er **repræsentative**. Gaten
beskytter mod at konkludere på ét udtræk, ikke mod at måle tre gange i en unormal uge.

## Hvad et token-krævende løb skal have på plads

| Krav | Hvor | Status 3/11 2026 |
| --- | --- | --- |
| Repository secret `PINTEREST_ACCESS_TOKEN` | GitHub → Settings → Secrets and variables → Actions → New repository secret | **Mangler** |
| App med **Standard-adgang** (Trial giver 1.000 kald i alt og er ikke brugbar til daglig research) | developers.pinterest.com → app → Access tier | Afventer app-review |
| OAuth-token med de læse-scopes Trends-endpointet kræver | Pinterest OAuth-flowet | Ikke udstedt |
| Fornyelse af tokenet **inden 30 dage** (2.592.000 sekunder), som Pinterest anbefaler | Ejerens rutine | Ikke aftalt |
| Refresh-token til automatisk fornyelse | Kræver `client_id` + `client_secret` + refresh-token som secrets | **Ikke sat op** — og bør være ejerens beslutning |

Pinterest roterer refresh-tokenet ved hver fornyelse, og access-tokenet udløber. Et secret med et
access-token er derfor en **dato**, ikke en installation: når det udløber, svarer API'et 401.
Siden 3/11 fornyer kørslen det selv — 401 → refresh-tokenet byttes, kaldet gentages, og det nye
par gemmes i secrets. Det kræver appens `client_secret` i CI og en PAT der må skrive secrets,
så det forbliver ejerens beslutning; indtil de fem secrets er sat, fejler kørslen stadig rødt
med en annotation der peger her. Se
[`pinterest-token-fornyelse-2026-11-03.md`](pinterest-token-fornyelse-2026-11-03.md).

TikTok-delen kræver intet token: læg en CSV fra Creative Center i
`docs/library/tiktok-creative-center.csv` med kolonnerne `keyword,views,posts,growth_pct`, så
bruger kørslen den automatisk næste nat. Filen er valgfri.

## Grænser ved at ligge i GitHub Actions

- **Planlagte kørsler slås fra efter 60 dage uden aktivitet i repoet.** Sker det, står gaten
  stille uden at nogen får en fejl — repoet skal holdes i gang, eller kørslen startes i hånden.
- **Cron er UTC og bliver forsinket.** GitHub kører scheduled workflows "best effort", typisk
  5–20 minutter efter det angivne tidspunkt; derfor er der en timer luft til den natlige push.
- **Time-gaten bruger kun timen:** 02:59 tæller som kl. 2, 03:01 springes over — næste nat tager den den.
- **Et tomt løb giver ingen commit** — og dermed heller ingen grøn historik.

## Verifikation

- `python3 tools/test_niche_scanner.py` → **48 tests, OK** (40 før den natlige kørsel, 38 før den).
- Workflowen er parset som YAML, og hver `run:`-blok er tjekket med `bash -n`.
- Summary-trinnet er kørt mod en rigtig offline-rapport: 10 søgeord, korrekt tabel og kilderække.
- **Ikke afprøvet:** et rigtigt Pinterest-kald (intet token), og dermed heller ikke 401-vejen mod
  den ægte API. Den er kun dækket af koden der returnerer `fejl: HTTP Error 401` og exit-kode 2.

## Næste skridt

1. Ejer: app-review til Standard-adgang, og læg tokenet som secret `PINTEREST_ACCESS_TOKEN`.
2. Første rigtige kørsel starter med 11 rækker «For tidlig (1/3 dage)» — det er gaten der
   virker, ikke en fejl.
3. Sæt refresh-secrets (`PINTEREST_REFRESH_TOKEN`, `PINTEREST_CLIENT_ID`,
   `PINTEREST_CLIENT_SECRET`, `PINTEREST_SECRETS_TOKEN`), så fornyelsen ikke er manuel hver
   30. dag — se [`pinterest-token-fornyelse-2026-11-03.md`](pinterest-token-fornyelse-2026-11-03.md).
