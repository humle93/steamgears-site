# Niche-produkt-scanner — Pinterest og TikTok, 2/11 2026

Værktøj: [`tools/niche_scanner.py`](../../tools/niche_scanner.py) · tests: `python3 tools/test_niche_scanner.py`

Scanneren finder **efterspørgsel vi ikke dækker**: den læser efterspørgselssignaler fra Pinterest og
TikTok, sammenholder dem med de 39 produkter butikken allerede har, og rangerer de huller hvor
efterspørgslen er høj og kataloget er tomt.

## Kilderne — og hvad der ikke findes

Research 2/11 2026 afviste den oplagte antagelse: **man kan ikke søge platformene på et søgeord.**

| Kilde | Findes der en API? | Hvad vi bruger |
| --- | --- | --- |
| Pinterest | Ja, men **ikke** en søgning i alle pins. `/v5/search/partner/pins` er **closed beta**, giver top 10 og ingen gem/kommentar-tal. `/v5/search/pins` søger kun **egne** pins | `GET /v5/trends/keywords/{region}/top/{trend_type}` — trendende søgeord med vækst wow/mom/yoy og ugentlig tidsserie (~1 år), filtreret med `include_keywords` |
| TikTok | **Nej.** Display API læser kun egne videoer, Research API er forbeholdt akademiske institutioner ("_commercial users: No_"), Shop API ser kun egen katalog | Manuel eksport fra **Creative Center** (Top Products / Keyword Insights) som CSV: `keyword,views,posts,growth_pct` |

Konsekvens for designet: Pinterest er den eneste programmerbare kilde, og den kræver **Standard-adgang**
(app-review med demo-video; Trial giver 1.000 kald/dag i alt og er ikke brugbar til research). TikTok-delen
er en fil, brugeren lægger ind — ikke fordi det er nemmere, men fordi alternativet ikke eksisterer.

Hvad ingen af kilderne giver: **konkurrence- og salgstal**. Modellen måler derfor ikke saturation. Det er
en bevidst udeladelse frem for en skjult antagelse.

## Scoren

```
efterspørgsel = 0,6 × klippet(årlig vækst) + 0,4 × klippet(momentum)
mulighed      = efterspørgsel × (1 − dækning),   dækning = min(antal matchende produkter, 3) / 3
```

- `klippet(x)` mapper vækst i procent til 0–100 med grænserne −50 % og +200 %, så ét enkelt udsving ikke
  dominerer rækken.
- `momentum` er ændringen fra de fire ældste til de fire nyeste uger i tidsserien.
- Dækning er **antal** matchende produkter, ikke kvalitet: søgeords-overlap mod produktnavne og
  -kategorier. Et ord er *generisk* — og tæller ikke — hvis det står i over 40 % af kataloget **og** i
  mindst fem produkter, så "steampunk goggles" ikke melder dækning bare fordi alt hedder steampunk.
  "corset" matcher stadig alle corsets, uanset hvilken kunden søger — modellen peger på huller, den
  beviser dem ikke.

## Kør

```bash
# uden netværk og uden token — fixtures, så værktøjet kan afprøves og testes
python3 tools/niche_scanner.py --offline --seeds "steampunk corset,herbarium print"

# rigtig kørsel, når Pinterest-token findes (Standard-adgang)
PINTEREST_ACCESS_TOKEN=... python3 tools/niche_scanner.py \
  --seeds-file tools/niche_seeds.txt --region "SE+DK+FI+NO" --trend-type seasonal \
  --tiktok-export ~/creative-center-top-products.csv
```

`--region` tager Pinterests 18 kuraterede områder (`SE+DK+FI+NO` er Norden). `--trend-type seasonal` er
den nyttige til produkter, fordi den fanger tilbagevendende årlig efterspørgsel; `growing` fanger nye nicher.

Udfil: `reports/niche-scan-<dato>.md` (læsbar) og `.json` (rå). Exit-kode 2 betyder «ingen kilde gav data»,
så en CI-kørsel ikke melder grønt på en tom scanning.

## Status

| Del | Status |
| --- | --- |
| Scoring, katalogmatch, rapport, CLI, tests | **Færdig** — 38 tests, kører offline |
| Lavsæson, censur af nuller, tre-dages-gate | **Færdig** 3/11 — se [niche-scanner-lavsaeson-2026-11-03.md](niche-scanner-lavsaeson-2026-11-03.md) |
| Pinterest-kilde | **Kode klar, blokeret** af `PINTEREST_ACCESS_TOKEN` (Standard-adgang + app-review) |
| TikTok-kilde | **Kode klar, halvt blokeret** — virker ved manuel Creative Center-eksport, som ikke findes endnu |
| Saturation/konkurrence | **Ikke muligt** med de officielle API'er |

Momentum kommer nu fra kildens `mom`-felt, rapporten viser lavsæson og topmåned, og en række kaldes først
«Anbefalet» efter tre måledage. Det er kodet 3/11; detaljerne står i
[niche-scanner-lavsaeson-2026-11-03.md](niche-scanner-lavsaeson-2026-11-03.md).

Næste skridt, når Pinterest-adgangen er på plads: kør med rigtige søgeord fra
[`docs/seo.md`](../seo.md) og de seks kollektioner, og læg resultatet ind som kandidater til batch 4 og
produktlinjen — ikke som beslutningsgrundlag alene, da salgstal ikke indgår.

## Referencer

- Pinterest: [trends-endpoint](https://developers.pinterest.com/docs/api/v5/trending_keywords-list/),
  [rate limits](https://developers.pinterest.com/docs/reference/rate-limits/),
  [access tiers](https://developers.pinterest.com/docs/getting-started/access-tiers/)
- TikTok: [Display API](https://developers.tiktok.com/doc/display-api-overview),
  [Research API-adgang](https://developers.tiktok.com/products/research-api/),
  [Creative Center](https://ads.tiktok.com/business/creativecenter/)
