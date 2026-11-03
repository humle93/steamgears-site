# Niche-scanneren: lavsæson, censur og tre-dages-gaten — 3/11 2026

Værktøj: [`tools/niche_scanner.py`](../../tools/niche_scanner.py) · tests:
`python3 tools/test_niche_scanner.py` (38 tests, kører offline uden token)

[Procedure-dokumentet](niche-scan-procedure-2026-11-02.md) §7 listede tre ændringer, som research'en
2/11 krævede, men som ikke var kodet. De er nu bygget, så værktøjet ikke længere regner på noget det ikke
kan stå inde for.

| # | Krav fra §7 | Før | Nu |
| --- | --- | --- | --- |
| 1 | Momentum må ikke udregnes af Pinterests serie | `slope_momentum()`: forholdet mellem de fire ældste og de fire nyeste uger i en min-max-normaliseret serie | `Signal.momentum` læser kildens eget `pct_growth_mom`. Mangler feltet, vejer retningen neutralt (50) |
| 2 | Rapporten skal vise lavsæson-niveauet | Kun vækstkolonner | `season_shape()` giver **Lavsæson** (gennemsnit af nederste kvartil) og **Top** (måneden serien topper i) |
| 3 | Nuller er censur, og beslutninger kræver gentagne udtræk | Nuller indgik som nul i gennemsnittene; ingen gentagelses-krav | Nuller tælles, holdes ude af gennemsnittene, og en helt nul-serie rangeres ikke. **Status** kræver måling på 3 dage |

## Hvorfor de tre ændringer

Pinterest min-max-normaliserer hver graf: både top og bund tvinges til 100 og 0. En vækst udregnet af den
serie måler derfor hvor stejlt grafen er strakt ud, ikke hvor efterspørgslen bevæger sig. `yoy` og `mom` er
Pinterests egne tal og kan bruges; serien kan kun bruges til **form** — hvornår på året toppen ligger, og
hvor lavt bunden står. Det er præcis hvad `Lavsæson` og `Top` viser, og hvorfor `momentum` ikke længere
beregnes.

Et nul i serien betyder «under kildens rapporteringstærskel», ikke «ingen efterspørgsel». Indgik nuller i et
gennemsnit, ville en niche med mange censurerede uger se død ud. Nu tælles de som `censored`, holdes ude af
lavsæson-beregningen, og en række der **kun** består af nuller flyttes til sin egen sektion uden at blive
rangeret.

Den tredje ændring er den sværeste at bygge, fordi den kræver hukommelse mellem kørsler: samme søgeord skal
være målt på tre forskellige dage, før rækken kaldes «Anbefalet». Historikken ligger i
`reports/niche-scan-history.json` ved siden af rapporten.

## Sådan ser rækkerne ud nu

| Kolonne | Betydning |
| --- | --- |
| Efterspørgsel | 60 % årlig vækst + 40 % seneste måned, hver klippet til 0-100 |
| Vækst YoY | Kildens `pct_growth_yoy` |
| Momentum | Kildens `pct_growth_mom`; «–» når kilden ikke giver den |
| Lavsæson | Indeksniveau for den nederste fjerdedel af serien; «–» uden serie |
| Top | Måneden serien topper i; «–» uden serie |
| Status | `Anbefalet (n dage)` ved 3+ måledage, ellers `For tidlig (n/3 dage)`, eller `Censureret` |

TikTok-eksporten har ingen tidsserie. De rækker får ingen `Lavsæson` og ingen `Top`, står som `For tidlig`
indtil de er målt tre gange, og bliver **ikke** kaldt censurerede — manglende serie er ikke det samme som
nuller i en serie. Det skel er en test i sig selv, fordi den første version af koden stemplede alle
TikTok-rækker som censurerede.

## Kørselshistorikken

```text
reports/niche-scan-history.json
{ "min_days": 3, "keywords": { "herbarium print": ["2026-11-03"] } }
```

`record_run()` er idempotent pr. dag: to kørsler samme dag giver stadig én dato. `--out-dir` styrer hvor
historikken lægges, så en testkørsel ikke forurener `reports/`. Gaten er ikke en anbefaling i sig selv — den
siger kun at tallet er set tre gange, ikke at nichen er god. Trin 3, 5 og 6 i proceduren er stadig manuelle
eller umulige.

## Hvad der stadig ikke kan måles

Uændret fra research'en: ingen absolut volumen for Norden, ingen konkurrence- eller salgstal, og ingen
Pinterest-søgning i alle platformens pins. Lavsæson-tallet er et **indeks inden for samme graf,** ikke et søgetal, så det kan sammenlignes mellem søgeord i samme kørsel — ikke på tværs af kørsler eller regioner.

Der er også en pris for 3-dages-gaten: en enkelt kørsel kan ikke længere producere en «anbefalet» række.
Det er hensigten. Den første kørsel er en shortlist, og først efter tre dage er der noget at beslutte på.

## Testene

38 tests, alle offline: `clamp_pct`, `parse_points`, `season_shape` (lavsæson, top, censur, helt nul-serie,
manglende serie), momentum-feltet, katalogmatch (inkl. generiske ord), begge kilder (inkl. de tre
«utilgængelig»-beskeder), sortering med censurerede sidst, historikkens idempotens og dags-gate, rapportens
seks sektioner samt CLI'ens tre udfiler.
