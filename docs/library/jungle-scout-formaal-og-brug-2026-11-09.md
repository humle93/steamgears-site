# Jungle Scout: formålet og brugen — og hvad det ikke kan svare på

**Skrevet 9. november 2026 · steamgears.eu · kilder pr. oktober 2026**

Jungle Scout er et **Amazon-værktøj**. Det bliver ofte nævnt i samme åndedrag som Google Trends og
Pinterest Trends, men det besvarer et andet spørgsmål: *hvor meget sælger der på Amazon-markedet, og til
hvilken pris?* — ikke *hvor mange søger efter det på Google?* Den forskel er hele grunden til at dokumentet
findes: vores efterspørgselsgates i
[niche-scan-procedure-2026-11-02.md](niche-scan-procedure-2026-11-02.md) bygger på Google og Pinterest, og
Jungle Scout kan hverken bekræfte eller afvise dem.

## 1. Hvad programmet er

Seks dele, der deler én database over Amazons katalog (600 mio.+ produkter, 24 kategorier ifølge
leverandøren selv):

| Del | Formål | Datagrundlag |
| --- | --- | --- |
| **Product Database** | Filtrér hele kataloget: kategori, pris, anmeldelser, BSR, estimeret salg pr. måned, sælgerland | Estimeret salg pr. ASIN, rullende 30 dage |
| **Product Tracker** | Følg udvalgte ASIN'er over tid: pris, BSR, anmeldelser, lager | Daglige målinger på de ASIN'er du selv vælger |
| **Opportunity Finder** | Søgeord → nicher med gennemsnitligt salg, pris, søgevolumen og 30/90-dages trend, plus Demand- og Competition-scorer | Amazons søgedata + de samme salgsestimater |
| **Keyword Scout** | **Amazon**-søgeord: volumen, relevans, konkurrence til titler og listings | Amazons søgefelt, ikke Google |
| **Supplier Database** | Leverandører og fabrikker (mest Kina) med responsrater | Egen indsamling |
| **Sales Analytics / Listing Builder / Inventory Manager** | Egen drift: økonomi, listing-kvalitet, lagerbehov | Dine egne data |

Dertil en browser-extension (tal direkte på Amazon-sider) og et **API** med seks endpoints — Product
Database, Sales Estimates pr. dag, Share of Voice m.fl. — hvis man vil automatisere.

## 2. Formålet — tre spørgsmål, det faktisk besvarer

1. **Er der købere på Amazon?** Hvor mange enheder en ASIN sælger pr. måned, og hvad den koster. Det er
   den ene ting, Google Trends ikke kan: Trends måler interesse, Jungle Scout estimerer **handler**.
2. **Hvordan ser konkurrentbilledet ud?** Prisbånd, anmeldelsesmængde, hvor længe en listing har kørt, og
   hvor mange sælgere der deles om en niche — grundlaget for de to konkurrencetærskler vi allerede citerer
   fra Jungle Scout: **≥ 5 af top 10 med ≤ 500 anmeldelser**, og **ingen sælger over 75 % andel**.
3. **Hvad er en realistisk pris?** Net revenue efter Amazons referal- og FBA-gebyrer pr. enhed — den pris
   en POD-t-shirt reelt skal kunne bære, hvis Amazon bliver en kanal.

Det, det **ikke** besvarer: om nogen søger efter motivet på Google, om Pinterest viser en voksende kurve,
eller om vores egen butik kan sælge varen. De tre spørgsmål har hver sin kilde.

## 3. Hvad tallene er — og ikke er

Salgsestimaterne kommer fra **AccuSales**, som modellerer ud fra Best Seller Rank, kategori, pris, lager og
benchmarks indberettet af sælgere. Tre konsekvenser, som man skal kende før man citerer et tal:

- **Rullende 30 dage, ikke en prognose.** »12 660/md« betyder *solgt de sidste 30 dage*, ikke *forventet
  næste måned*. En sæsonvare læst i januar ser død ud og omvendt.
- **Estimatet gælder parent-ASIN.** Amazons egne »5 000+ købt«-tal gælder den valgte variant, så en
  variant-sammenlægning kan se ud som om Jungle Scout overdriver med 100 % — det er måleobjektet, ikke
  modellen, der er forskelligt.
- **Det er et interval forklædt som et tal.** Uafhængige tests finder 80–90 % overensstemmelse på
  størrelsesordenen, ikke på enheden. Behandl tallet som retning: 300/md og 900/md er en forskel der betyder
  noget, 8 000 og 8 400 er støj.

Opportunity Finder's **Demand**- og **Competition**-scorer har offentliggjorte input, men **ikke
offentliggjorte vægte**. De kan derfor ikke efterprøves, og de må ikke være det eneste argument — samme
konklusion som [niche-scan-proceduren](niche-scan-procedure-2026-11-02.md) §6.

## 4. Sådan bruges det — opskrift i syv trin

Skriv beslutningsreglen ned **før** data trækkes (samme regel som niche-scan §2, trin 1), så tallet ikke
bliver valgt for at passe til konklusionen.

1. **Afgræns markedet.** Vælg marketplace først — `amazon.com` (US), `amazon.de`, `amazon.co.uk`. Tallene
   er ikke sammenlignelige på tværs, og en US-tærskel overført til DE er en anden beslutning.
2. **Product Database som grovsigte.** Kategori = clothing/apparel, prisbånd = vores DKK-priser omregnet,
   anmeldelser ≤ 500, estimeret salg ≥ vores break-even pr. måned. Eksportér CSV — eksporten tager én side
   ad gangen, så notér hvilke sider der blev eksporteret.
3. **Opportunity Finder som shortlist.** Søgeord fra vores egne kollektioner (fx »steampunk shirt«,
   »dark academia«) → gennemsnitligt salg pr. listing, prisbånd, søgevolumen, 30/90-dages trend. Brug den
   som **shortlist**, ikke som dom.
4. **Product Tracker i 30–90 dage.** Det er den eneste måde at se, om en »sæson« gentager sig: en listing der
   sælger i oktober skal måles igen i januar. Tre målinger før en beslutning, som vi allerede kræver af
   Trends-udtræk.
5. **Keyword Scout til titler — ikke til efterspørgsel.** Ordene er Amazons egne; de siger hvad købere skriver
   *i Amazon*, og de kan bruges til listing-titel og back-end keywords. De siger intet om Google.
6. **Supplier Database: spring over.** Vi producerer via Printful (POD), ikke via fabrikker i Kina, så
   leverandørdelen er uden anvendelse. Til gengæld er **net revenue** efter gebyrer det rigtige
   prissammenligningspunkt.
7. **Døm med vores eget skema.** Gå til go/no-go i niche-scan-proceduren §4. Jungle Scout leverer trin 6
   (konkurrence) og en del af trin 5 (skala) — for **Amazon**. Resten af skemaet står stadig.

## 5. Priser og forpligtelse (listepriser pr. oktober 2026)

| Plan | Pr. måned | Årligt | Grænser (udvalg) |
| --- | --- | --- | --- |
| Starter | **49 USD** | ca. 29 USD/md | 1 sæde, 50 tracked products, 500 salgsestimater, 100 Opportunity Finder- og 50 Keyword Scout-søgninger/md |
| Growth Accelerator | **79 USD** | ca. 49 USD/md | 150 tracked products, 500 tracked keywords, 1 000 estimater, 250 Keyword Scout/md |
| Brand Owner | **149 USD** | ca. 129 USD/md | 3 sæder, 2 000 tracked products, 5 000 estimater, 2 års historik, Competitive Intelligence |

Ingen gratis plan; 7 dages pengene-tilbage. Priserne er tredjepartsverificerede oversigter fra september–
oktober 2026 og skal tjekkes på `junglescout.com/pricing` før et køb — de er i USD og ændrer sig med
rabatkampagner.

**Omkostningen skal holdes op mod noget:** 49 USD/md er ~350 kr./md, altså mere end en måneds annoncebudget
for en lille test. Til sammenligning koster de kilder vi bruger i dag (Google Trends, Pinterest Trends,
Search Console) ingenting.

## 6. De fem fælder

1. **Markedsfælden.** Amazon-data siger intet om Google- eller Pinterest-efterspørgsel. En niche der sælger
   på Amazon kan være usynlig i søgning — og omvendt.
2. **Prognosefælden.** 30-dages estimatet læses som en forecast. Det er en måling bagud.
3. **Variantfælden.** Parent-ASIN vs variant giver systematisk forskel mod Amazons egne tal.
4. **Scorefælden.** Demand/Competition-scoren har hemmelige vægte. Er scoren det eneste argument, er
   beslutningen ikke efterprøvelig.
5. **Markedspladsfælden.** US-tærskler (8 000 søgninger/md, 500 anmeldelser) er kalibreret mod det største
   marked i verden. Danmark er ca. 1–2 % af det; tærsklerne skal skaleres eller skrottes, ikke kopieres.

## 7. Hvad det betyder for steamgears.eu

**Anbefaling: køb det ikke nu.** Amazon er ikke en kanal i dag — butikken sælger via sitet og
sociale kanaler, og de seks kanal-tokens i plugin'et dækker Pinterest, Instagram, Facebook, TikTok, X og
Telegram, ikke Amazon. Jungle Scout løser et problem vi ikke har, og dets tal kan ikke bruges som bevis for
vores egen efterspørgsel.

**Hvornår det bliver relevant:** hvis Amazon bliver besluttet som kanal (Printful kan producere og sende
Amazon-ordrer, så det er en produktionsmæssigt åben vej). Så er rækkefølgen: én måned på Starter, ét
afgrænset marked, tre målinger over 60 dage, og en beslutning skrevet ned **før** første søgning.

**Hvad der stadig er sandt uden abonnement:** de to konkurrencetærskler kan læses manuelt på Amazon —
antal anmeldelser i top 10 og én sælgers andel kan tælles i hånden for ti lister. Det er den billige
forfiltrering; abonnementet køber hastighed og historik, ikke en anden sandhed.

## Referencer

- Jungle Scout: [Product Database — filtre og eksport](https://support.junglescout.com/hc/en-us/articles/360008616554-How-to-use-the-Product-Database),
  [Opportunity Finder — filtre og scorer](https://support.junglescout.com/hc/en-us/articles/360037689513-Opportunity-Finder),
  [salgsestimater på extension — rullende 30 dage](https://support.junglescout.com/hc/en-us/articles/360008616614-Sales-and-Revenue-Estimates-on-Extension),
  [Sales Estimation — hvad modellen bygger på](https://www.junglescout.com/ai-use-cases/sales-estimation),
  [API — seks endpoints](https://support.junglescout.com/hc/en-us/articles/21641823937943-API-Endpoint-Descriptions)
- Priser (tredjepart, sept.–okt. 2026): [frontdeskreview.com](https://frontdeskreview.com/software/amazon-seller-tools/jungle-scout),
  [smb-seo.com](https://www.smb-seo.com/tools/jungle-scout/pricing)
- Nøjagtighed (uafhængig test, 2026): [revenuegeeks.com — Sales Estimator ~90 %, variant-forskellen forklaret](https://revenuegeeks.com/software/jungle-scout/accurate)
- Vores eget skema: [niche-scan-procedure-2026-11-02.md](niche-scan-procedure-2026-11-02.md) §2–§4
