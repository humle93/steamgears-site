# Procedure for niche-scanning — 2/11 2026

Research-dokument. Værktøjet det skal styre: [`tools/niche_scanner.py`](../../tools/niche_scanner.py)
(kilder og score er beskrevet i [`niche-produkt-scanner-2026-11-02.md`](niche-produkt-scanner-2026-11-02.md)).

Formålet er at svare på ét spørgsmål uden at snyde sig selv: **hvilke nicher er værd at lave produkter i,
og hvornår ved vi nok til at sige nej?** Kilderne er trend- og søgeindeks, og de måler ikke det man tror.

## 1. Hvad kilderne faktisk måler

| Kilde | Normalisering | Hvad tallet betyder | Målt i |
| --- | --- | --- | --- |
| Google Trends | Hvert punkt divideres med **total søgning** i periode+geografi, Skala 0–100 | **Relativ** popularitet, indekseret til sit eget peak i vinduet | Søgninger |
| Pinterest Trends | Ratio af søgninger til **alle** søgninger på platformen; derefter **max = 100, min = 0 pr. graf** | Relativ interesse inden for én graf | Søgninger |
| TikTok Creative Center | Ikke offentliggjort | «Popularity» i Keyword Insights = **antal annoncekreativer** med ordet (sekundærkilde) | Annoncer |
| TikTok Keyword Planner | Ikke offentliggjort | Absolut søgevolumen — men kun 12 lande | Søgninger |

Tre konsekvenser, som ændrer hvordan en scanning skal læses:

- **Google Trends' 0 betyder «under tærsklen», ikke «ingen efterspørgsel».** Google skriver selv at
  lavvolumen-ord vises som 0, og at der lægges statistisk støj ind, som er *mest* mærkbar netop på
  lavvolumen-ord: «This means that for these queries, Trends data will sometimes reflect statistical noise
  rather than actual search interest.» Smalle nicher er altså dér, indekset er dårligst.
- **Pinterests min-max-normalisering opfinder volatilitet.** Fordi både top og bund tvinges til 100 og 0 pr.
  graf, bliver en næsten flad serie strakt ud over hele skalaen. Vækstprocenter udregnet af den serie er
  derfor ikke til at stole på; brug Pinterests egne `yoy`/`mom`-felter og serien til **form** (hvornår på året).
- **Creative Centers «popularity» er annonceaktivitet, ikke forbrugerefterspørgsel.** En niche med organisk
  interesse men få annoncører læser som «lav»; en tungt annonceret dropshipping-kategori læser som «høj».
  Til gengæld er Creative Centers felt `views`/`posts` i Top Products/Keyword Insights tættere på aktivitet.

Og den vigtigste begrænsning: **TikTok Keyword Planner — den eneste kilde med absolut volumen — dækker
USA, UK, Canada, Tyskland, Frankrig, Italien, Spanien, Australien, Mexico, Brasilien, Saudi-Arabien og UAE.**
Danmark, Sverige, Norge og Finland er ikke med. For en nordisk butik findes der derfor **ingen** absolut
TikTok-volumen; kun relative og annoncebaserede tal.

## 2. Proceduren — syv trin

Rækkefølgen er ikke tilfældig: relevansgaten kommer før alle tal (Moz' regel er at man skal svare ja til
«er det her noget vores publikum går op i, og kan vi levere det?» **før** man ser på metrics — ellers
stryger kandidaten), og skala kommer efter form, fordi et indeks ikke kan bruges som størrelse.

| # | Trin | Konkret krav | Status i dag |
| --- | --- | --- | --- |
| 1 | **Skriv reglen først** | Notér hypotesen, beslutningsreglen og hvad der ville gøre det til et nej, *før* data trækkes | Gøres i commit-beskeden |
| 2 | **Kandidater fra flere signaltyper** | Kollektioner + kundesprog (reviews, mails) + trendlister; aldrig ét søgeord alene | Scanneren: 11 seeds fra 6 kollektioner |
| 3 | **Relevansgate** | Kan vi levere, og kan vi differentiere? Ellers dræb kandidaten uanset tal | Manuelt |
| 4 | **Formdiagnose** | 5-års kurve (Google Trends API rækker 1.800 dage): **mindst 3 gentagne årstop** eller flad/stigende baseline. Ét enkelt, gentaget-løst spike = nej | Scanneren: `seasonal` + 1 års ugentlig serie |
| 5 | **Skala** | Absolut volumen fra en kilde der tæller (Google Ads Keyword Planner, TikTok Keyword Planner). Trends kun til retning | **Mangler** — ingen adgang |
| 6 | **Konkurrence** | Antal sælgere, reviewtal, prisbånd, annoncedækning + manuel gennemgang af top 10 for «cracks»: forældede resultater, svage domæner, intent-mismatch | **Mangler** — kilderne giver ingen salgs-/konkurrenttal |
| 7 | **Sæson-sizing og go/no-go** | Omsætning i **lavsæson**, ikke top; gå ind 2–3 mdr. før toppen; derefter skemaet i §4 | Delvist — `momentum` og YoY findes |

Trin 5 og 6 kan ikke køres i dag. Det betyder ikke at scanningen er værdiløs — den betyder at den kun må
bruges som **shortlist**, ikke som beslutning.

## 3. Tærskler — og hvor faste de er

| Tærskel | Værdi | Oprindelse |
| --- | --- | --- |
| Google Trends' historik | 1.800 dage (~5 år), ~2 dages forsinkelse | **Dokumenteret** (Google) |
| Trends-sammenligningsvinduer med YoY | 1 uge, 1 måned, 3 måneder | **Dokumenteret** (Google) |
| Trends-forespørgsler pr. sammenligning | 5 på sitet, «dozens» i API'et | **Dokumenteret** (Google) |
| Pinterest Trends-historik | op til 2 år; 4 søgeord pr. graf | **Dokumenteret** (Pinterest) |
| Pinterest Trends' iscenesatte 0/100 | min og max pr. graf | **Dokumenteret** (Pinterest) |
| Gentagne udtræk før en beslutning | **3 dage** som minimum; litteraturen bruger 5–50 udtræk og advarer om 24-timers cache | Metode (Rovetta 2021; Cebrián & Domenech 2024) |
| Årstoppe før «varig efterspørgsel» | **≥ 3** | Tidsserie-praksis, ikke publiceret niche-standard |
| Sæsonstyrke (STL) | `Fs = max(0, 1 − Var(R)/Var(S+R))`; differens ved Fs > 0,64 | Delvist verificeret (Hyndman; 0,64 er snippet-niveau) |
| Absolut volumen-gulv | 8.000 exact/md (US) | **Praksis/opinion** (Jungle Scout) |
| Konkurrence | ≥ 5 af top 10 har ≤ 500 reviews; ingen sælger > 75 % andel | **Praksis/opinion** (Jungle Scout) |
| SERP-kvalitet | KD ≤ egen målt baseline; forældede/svage resultater i top 10 er et crack | Metode (Moz' KD-baseline, Ahrefs' crack-review) |
| Annoncedækning | Ingen tal findes; brug forventet organisk CTR som symptom (84 % ren SERP vs 41 % fyldt) | Mekanisme dokumenteret, tærskel ikke |

**Det der ikke findes:** en autoritativ minimumsgrænse for antal datapunkter, antal sælgere eller hvor
mange procent vækst der gør en trend «rigtig». Exploding Topics' metodside indeholder ingen tærskler, og
Googles dokumentation giver ingen volumenvejledning til niche-research. Alle tal som «10 % MoM i 6 måneder»
er folklore. Behandl dem derefter.

## 4. Go/no-go

**Kør** kun hvis alle disse er sande: relevansgaten er bestået · absolut volumen over kategoriens gulv ·
kurven viser ≥ 3 gentagne årstop eller flad/stigende baseline · sæsonen er lav, *eller* lavsæson-omsætningen
dækker kapitalbindingen · konkurrencen er under egen baseline (og ≥ 5 af top 10 har ≤ 500 reviews) · ingen
enkelt sælger har > 75 % · mindst ét crack i top 10 · og der er afsat penge til en rigtig test bagefter.

**Nej** ved: ét gentaget-løst spike · volumen under kildens rapporteringsgrænse (0 = censureret, ikke nul) ·
stor-brand-ejet SERP · én sælger med > 75 % · og — vigtigst — **hvis scoren er det eneste argument**.

## 5. De fem fælder

1. **Unicorn-fælden.** Højt gennemsnitligt volumen kan skjule en død trend. Ahrefs' eksempel: «fidget
   spinner» ~900.000/md mod «yoyo» ~47.000/md ved samme sværhedsgrad — men kurven viste et spike i maj 2017
   og næsten nul bagefter. Gennemsnittet er fælden; kurven er svaret.
2. **Nul-fælden.** En 0 fra Trends er censur. Gennemsnit ikke nuller ind; kassér rækken.
3. **Normaliserings-fælden.** Vækstprocent udregnet af en min-max-normaliseret serie (Pinterest) eller af en
   serie indekseret til sit eget peak (Google, enkelt-serie) er ikke sammenlignelig på tværs af søgeord.
   Sammenlign kun ord der er hentet **samtidigt i samme graf**.
4. **Aktivitets-fælden.** Annoncekreativer og post-antal er ikke efterspørgsel. Mest tydeligt på TikTok.
5. **Overlap-fælden.** Niche = snit mellem to målgrupper (fx «botanik» × «dark academia»). Er snittet for
   lille, findes der ingen volumen uanset hvor god kombinationen lyder.

## 6. Hvad der ikke er bevist

Der findes **ingen publiceret undersøgelse** af om POD-sælgere der følger trend-/søgeordsscorer sælger
bedre end dem der ikke gør. Det nærmeste er forecasting-litteratur, hvor Trends-data forbedrer prognoser for
produkter man **allerede kender** — en lettere opgave end at vælge et nyt produkt. Og alle kommercielle
«opportunity scores» (Jungle Scout, SellerApp, eRank) offentliggør input men **ikke vægte**, hvilket gør dem
uafprøvelige. Den eneste formel med forskningsbaggrund er Ulwicks
`opportunity = importance + max(importance − satisfaction, 0)` — og den kræver **spørgeskemadata**, ikke
søgevolumen.

Konsekvens: en score herfra er en **billig forfiltrering**, der gør priorer eksplicitte og reviderbare. Den
er ikke evidens for at produktet sælger. Den ærlige opfølgning er en lille rigtig test — 5–10 listings og
et lille annoncebudget — ikke flere scores.

## 7. Hvad det betyder for scanneren

Tre konkrete ændringer følger af research'en — **udført 3/11 2026**, se
[niche-scanner-lavsaeson-2026-11-03.md](niche-scanner-lavsaeson-2026-11-03.md):

1. **`momentum` må ikke udregnes som procent af Pinterests serie.** Serien er min-max-normaliseret pr. graf,
   så forholdet mellem to uger er skævt. Brug `yoy`/`mom`-felterne til retning og serien til at finde
   top- og bundmåned.
2. **Rapporten bør vise lavsæson-niveauet**, ikke kun vækst — det er lavsæsonen der afgør om en sæsonniche
   kan bære lager.
3. **Nuller skal markeres som censurerede** i stedet for at indgå i gennemsnit, og en kørsel bør kræve
   gentagne udtræk over mindst tre dage før den skriver «anbefalet».

Også efter ændringerne: brug `reports/niche-scan-<dato>.md` som shortlist og skriv i beslutningen hvilke
trin (§2) der faktisk blev kørt. Tre-dages-gaten gør den første kørsel til en shortlist, ikke en dom.

## Referencer

- Google: [Trends FAQ — sampling, normalisering, støj, 0-værdier](https://support.google.com/trends/answer/4365533),
  [sammenligningsvinduer og YoY](https://support.google.com/trends/answer/17261722),
  [Trends API — 1.800 dage](https://developers.google.com/search/blog/2025/07/trends-api)
- Pinterest: [hvordan Trends normaliseres (ratio, max 100 / min 0 pr. graf, 2 år)](https://help.pinterest.com/en/business/article/pinterest-trends)
- TikTok: [Creative Center-hjælp](https://ads.tiktok.com/resources/help/article/creative-center?redirected=2),
  [Keyword Planner — 12 lande](https://ads.tiktok.com/resources/help/article/about-the-keyword-planner-tool-in-tiktok-as-manager?lang=en)
- Søgemaskine-metode: [Moz — relevansgate, KD-baseline, forventet CTR](https://moz.com/keyword-research-guide/prioritizing-keywords),
  [Ahrefs — KD er backlinks, crack-review, unicorn-test, 2–3 mdr. lead time](https://ahrefs.com/blog/keyword-difficulty/)
- Stabilitet: Rovetta 2021, [Trends varierer med indsamlingsdagen](https://pmc.ncbi.nlm.nih.gov/articles/PMC8186442/);
  Cebrián & Domenech 2024, *Technological Forecasting & Social Change* 202 — udtræksgennemsnit og cachen
- Praksis-tærskler (opinion): [Jungle Scout — valideringspunkter](https://www.junglescout.com/blog/seller-strategies-how-to-validate-products/)
- Markedsføring uden metode: [Printify](https://printify.com/blog/trending-products/),
  [Exploding Topics' metodside](https://explodingtopics.com/methodology) — kilder til kandidater, ikke til bevis
