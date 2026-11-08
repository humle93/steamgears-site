# Batch 4-arbejdsordre — mockups pr. produkt (8/11 2026)

_Forberedelsen er færdig og målt; selve mockup-billederne kræver stadig ejerens
Printful-adgang. Ingen af de 24 produkter har 3 eller flere billeder i dag._

Denne arbejdsordre er det stykke, der **kan** laves uden adgang: hvilke billeder der mangler, hvad de skal
hedde, hvilken alt-tekst de skal have, og hvordan man bagefter bekræfter at de er kommet ind. Den erstatter
ikke mockup-generatoren — den gør kun ejerens del mekanisk.

## 1. Målt udgangspunkt (8/11, live)

| Måling | Tal |
| --- | --- |
| Produkter i alt (`product`) | **24** (20 publiceret, 4 kladde, 92 i papirkurv) |
| Billeder i alt på produkterne | **26** |
| Produkter med **under 3** billeder | **24 af 24** |
| Billeder uden alt-tekst | **0** |
| Billeder under 1000 px i bredden | **8** |

POD-normen er 5–8 billeder pr. produkt (mockup forfra, bagfra, detalje, model/kontekst, størrelsesskema).
Med ét billede pr. produkt kan kunden hverken se pasform, materiale eller bagside — det er den største
enkeltårsag til tøjretur, og det er præcis det, batch 4 lukker.

## 2. De fem familier og hvad de skal have

| Familie | Produkter | Billeder i dag | Mål | Motiver pr. produkt |
| --- | --- | --- | --- | --- |
| **Tees** | 6 | 6 | 5 | front, bag, detalje (søm/print), model forfra, størrelsesskema |
| **Hoodies** | 4 | 4 | 5 | front, bag, hætte/detalje, model, størrelsesskema |
| **Art prints** | 4 | 4 | 3 | motiv i ramme på væg, nærbillede af papir/kant, mål-skema |
| **Phone cases** | 1 | 1 | 3 | bagside, på telefon i hånd, detalje af kameraudskæring |
| **Småting** (pendanter, lommeure, armbånd, bælte, goggles, top hat, corset, frakker, PDF-mønster) | 9 | 11 | 4 | produkt isoleret, båret/ i brug, detalje, mål/variant |

## 3. Filnavne og alt-tekster (fast skabelon)

```
{produkt-slug}-{nummer}-{rolle}.jpg      fx  airship-armada-tee-03-detalje.jpg
```

Rollerne er `front`, `bag`, `detalje`, `model`, `storrelse`, `vaeg`, `naer`, `baeret`, `isolated`.
Alt-teksten skrives efter samme mønster, så den både beskriver motivet og produktet:

> «{Motiv} på {produkttype} — {rolle}. {Materiale/pasform}. Steamgears.»

Eksempel: *«Airship Armada på t-shirt — detalje af trykket. 100 % bomuld, regular fit. Steamgears.»*

## 4. Arbejdsrækkefølge (så intet skal gøres to gange)

1. **Kostpris og variant først:** bekræft Printful-variant og kostpris for de 24 produkter, så priserne i
   S4.3 kan sættes én gang (i dag står butikken i USD, se `valuta-og-priser-2026-11-02.md`).
2. **Tees og hoodies:** de seks t-shirts og fire hoodies har størst trafik og flest returneringer — tag dem først.
3. **Art prints:** kun tre billeder pr. produkt, hurtig gevinst.
4. **Småting:** ni produkter, fire billeder hver; mange kan genbruge samme scene.
5. **Kladde-produkterne 942, 765, 766, 767** får mockups **samtidig** med publiceringsbeslutningen (S1.2), så
   de ikke skal åbnes to gange.

Upload i WooCommerce → Produkt → Produktgalleri (ikke som enkeltbillede), så hovedbilledet bevares.
Billederne skal ligge i mediebiblioteket med alt-teksten sat **før** de lægges i galleriet.

## 5. Sådan bekræftes det bagefter (måling, ikke mavefornemmelse)

```bash
# Antal billeder pr. produkt, og hvor mange der stadig mangler alt-tekst
export PATH="$HOME/.novamira-npm/bin:$PATH"
# (samme probe som blev brugt 8/11 — se docs/library/cron-og-koehygiejne-2026-11-08.md for mønstret)
```

Acceptkriterium for batch 4: **0 produkter med under 3 billeder**, 0 billeder uden alt-tekst, og
`/shop/` viser mindst 3 billeder pr. produkt ved gennemklik. Målingen 8/11 gav 24 af 24 under 3 — det tal
skal være 0, før modulet kan meldes færdigt.

## 6. Hvad der stadig kræver ejeren

- **Printful-adgang** (kontoen) til at generere mockups i de fem familier — eller 5–8 færdige billeder pr.
  produkt uploadet direkte.
- **Valuta og alle priser** (S4.3), fordi mockups og pris hænger sammen i samme produktopsætning.
- **Ja/nej pr. produkt** til 942, 765, 766 og 767 (S1.2).

Uden de tre kan batch 4 ikke lukkes — men alt forarbejdet er nu gjort, så det er ren udførelse.
