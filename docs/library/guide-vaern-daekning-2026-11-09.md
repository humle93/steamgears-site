# Værnsdækning for copy — hvad der blev målt, og hvad der ikke blev, 9/11 2026

`tools/guide_check.py` meldte **4 af 4 guides** — men repoet havde fem copyfiler. Hub-copyen til
`/steampunk-costumes/` var skrevet om til DKK og levende id'er uden nogensinde at blive målt, fordi værnet
ledte efter filnavnet `guides-*.md`. Og outfit-siden til post 699 lå med 61 dollar-beløb, nul `/go/`-links og
produkter der er slettet — helt uden for værnene. Et grønt værn der kun dækker fire femtedele af copyen er
en påstand, ikke en kontrol.

Dette dokument er målingen af den udvidede dækning: hvordan filerne findes, hvad der blev målt pr. fil, og
hvad der stadig mangler.

## 1. Kortlægningen: 57 filer med links eller priser

En scanning af `docs/**/*.md` for `/go/<id>/`-links og prismålinger gav **57 filer**. De er ikke alle copy:

| Slags | Antal | Eksempel | Skal måles som copy? |
| --- | ---: | --- | --- |
| Copy med husets skabelon (`**H1:**` + Rank Math-tabel) | 4 filer / 6 blokke | `guides-2026-11-03.md` | **ja** |
| Rå side-copy uden skabelon | 1 | `steampunk-outfit-ideas-for-women.md` | **ja — den var usynlig** |
| Historiske prislister og målinger | 13 | `priser-fra-store-api-2026-11-03.md` | nej, men skal være registreret |
| Logfiler, roadmap, status og research | 39 | `docs/roadmap.md` | nej |

Forskellen mellem de to sidste rækker og den første er hele pointen: et dokument der **citerer** en gammel
pris er ikke det samme som en fil der **påstår** en pris. Værnet skal kunne se forskellen, ellers kan en
gammel prisliste gemme sig som "bare dokumentation".

## 2. Hvad værnet gør nu

- **Copy findes på indhold, ikke på filnavn.** En fil med `**H1:**` eller Rank Math-tabellen er copy og
  bliver målt med det samme — uanset hvad den heder.
- **Enkeltsider får deres egen vej.** En fil uden `## Guide`-blokke er én blok: hele siden måles med de
  samme pris-, valuta- og linkregler, men uden krav om `Slug`, hub-link og søsterklynge, som kun giver
  mening for en guide i klyngen.
- **Intet kan ligge uden for.** En fil under `docs/` med `/go/`-links eller 20+ prismålinger, som hverken er
  copy eller står i registeret `IKKE_COPY` med en grund, får kørslen til at fejle. Registeret har 23 poster,
  hver med en sætning om hvorfor filen ikke er copy.
- **`--coverage` viser regnestykket:** hvilke filer der måles, hvilke der er registreret, og hvad der er
  uklassificeret.

Det er den regel der blev prøvet med det samme: den første kørsel efter udvidelsen fejlede med
`docs/library/steampunk-outfit-ideas-for-women.md: 0 /go/-links og 61 prismålinger … klassificér den` —
den fil havde ligget uden for værnene i to uger.

## 3. Målingen pr. fil, 9/11 2026

Hvert `/go/<id>/` er slået op i Store API med neutral UA; hvert beløb er holdt op mod
[`priser-live-2026-11-09.md`](priser-live-2026-11-09.md), og hvert total er regnet efter i øre.

| Fil | Blokke | Ord | `/go/`-id'er | Beløb | Resultat |
| --- | ---: | ---: | ---: | ---: | --- |
| `guides-2026-11-03.md` (292, 296, 295) | 3 | 3.558 | 13 | 92 | **PASS** |
| `guides-2026-11-03-corset.md` (1394) | 1 | 1.348 | 10 | 32 | **PASS** |
| `hub-steampunk-costumes-2026-11-03.md` (550) | 1 | 968 | 10 | 20 | **PASS** |
| `steampunk-outfit-ideas-for-women.md` (699) | 1 | 1.796 | 13 | 63 | **PASS** |
| **I alt** | **6** | **7.670** | **15 unikke** | **207** | **0 døde · 0 valutaafvigelser** |

De 15 unikke id'er er 399, 403, 413, 477, 483, 489, 491, 493, 495, 557, 940, 954, 965, 967 og 1282 — alle
svarer **200** i butikken, alle i **DKK**. Butikken er målt igen samme dag: 20 publicerede produkter,
`currency_code: DKK`, og **0** afvigelser mod prislisten fra tidligere på dagen, så listen er stadig facit.

## 4. Outfit-siden: fra USD til tolv looks i kroner

Filen var den gamle copy til post **699**: 12 looks prissat i USD (`$44.95`, `$59.95` …), produkter som
`Gear Corset Lady Hoodie`, `Clockwise Fox tee` og `Sky Corsair` der alle er slettet, og **nul** `/go/`-links.
Siden er publiceret og viser stadig *Prices are in USD*.

Den er skrevet om til husets skabelon og butikkens katalog: tolv looks bygget af de tretten levende
produkter, hvert look med sine egne `/go/`-links, DKK-priser og en total der er regnet efter. Billigste look
er **500,00 kr.** (hoodie + goggles), dyreste **1.300,00 kr.** (lommeur på kæde + fløjlsfrakke), og
corsetten bærer fem af de tolv looks — derfor står den samme vare flere steder med samme pris.

Rank Math-felterne er sat (title 55 tegn, meta 153 tegn), der er seks FAQ-spørgsmål med rigtige beløb, og
`### Publishing notes` siger hvad der skal ske ved indsættelsen: behold slug og post-ID, udskift brødteksten,
behold `[sg-shoprow]`/`[sg-buy]`, og skift valutaen — det er hele grunden til udskiftningen.

## 5. Bevis

| Kontrol | Resultat |
| --- | --- |
| `python3 tools/guide_check.py` | 6 af 6 copy-blokke bestod, 15 id'er målt, 0 døde, 0 valutaafvigelser |
| `python3 tools/guide_check.py --selftest` | **24 af 24** fixtures (8 nye for dækning og enkeltsider) |
| `python3 tools/guide_check.py --coverage` | 4 copyfiler · 23 registrerede dokumenter · 0 uklassificerede |
| Den gamle guide-fil fra `f75a5da` kørt gennem det nye værn | `FAIL` på alle tre guides + `DØD /go/475/ → 404` |
| `bash tools/natlige-vaern.sh` | alle værn grønne, inkl. `guide-copy` og `guide-copy-selftest` |

## 6. Hvad der stadig mangler

Publiceringen. Post 292, 296 og 295 er kladder, side 550 skal have hub-copyen, og post 699 skal have
outfit-copyen — det kræver skriveadgang til WordPress, som agenten ikke har. Indtil da er repoet og værnene
færdige og verificerede, men **siden 699 viser stadig dollar**, og det er en fejl på sitet, ikke i repoet.

Der mangler også to egne fotos pr. guide og pr. look, og en *Request indexing* i GSC bagefter.

## Lukket 10/11: outfit-copyen er nu en målt copyblok

Klassificeringsværnet gjorde præcis det arbejde det blev bygget til: `docs/library/steampunk-outfit-ideas-for-women.md`
stod med **61 `$`-beløb, nul `/go/`-links og 0 kronebeløb**, og fordi filen hverken havde `**H1:**` eller
Rank Math-tabellen, kunne den ikke måles som copy — den var usynlig for alle de øvrige værn og rød i nattekørslen
(10/11: `guide-copy` exit 1, »4 af 5 copy-blokke bestod«).

Den er nu skrevet om i husets skabelon mod butikkens **tyve levende produkter**: tolv komplette looks med hvert
sit `/go/`-link og en total der er regnet efter, DKK i dansk format, fem FAQ, klyngelinks til hub og de fire
søsterguides, 2.247 ord. Målt 10/11: `guide_check.py` → **6 af 6 copyblokke består**, **18 unikke `/go/`-id'er**,
**0 døde**, **0 valutaafvigelser**, og `unclassified()` er tom.

**Fælden der er værd at gemme:** README-rækken for filen påstod allerede 9/11 at copyen var »omskrevet til DKK«
med »tretten levende produkter« — men filen på disk indeholdt den gamle USD-tekst. En dokumentation der
beskriver et arbejde, som blev rullet tilbage, er ikke bare ubrugelig, den holder værnene i ro: kun fordi
klassificeringen måler **filen** og ikke **README-rækken**, blev forskellen fundet. Rækken er nu rettet til det
målte (500,00–1.465,00 kr.), og `dkk`-modulet tæller seks sider i stedet for fem.
