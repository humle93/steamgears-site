# Fire nicher, én start — roadmap og deploymentplan

**Målt og skrevet 9. november 2026.** Dokumentet vælger den første butik, rangerer de tre andre med
åbne kriterier og lægger en faseplan med gates, budget og stopregler. Det er skrevet sammen med den
færdige indholdspakke i `deploy/nicher/indhold/`, som gør hver niche til en importerbar butik.

> **Det vigtigste først:** KABINE starter. Ikke fordi bil-interiør er det største marked, men fordi
> det er den niche hvor målgruppe, pris, fragt, retur og påstandsrisiko hænger sammen uden et
> uløst problem. Alle fire `.dk`-domæner er desuden taget (målt 9/11), så navn og domæne er en
> ejerhandling, der skal lukkes før indkøb, emballage og fotos — ikke efter.

---

## 1. Kort svar

| Rækkefølge | Niche | Brand | Samlet score | Start | Første gate |
| --- | --- | --- | ---: | --- | --- |
| 1 | Bil-interiør | KABINE | **4,00 / 5** | 16/11 2026 | CPA ≤ 223 kr. og ≥ 30 ordrer i fire uger |
| 2 | Søvn | IMMER | 3,55 / 5 | 5/1 2027 | KABINE på break-even to uger i træk |
| 3 | Lejebolig-opbevaring | OPLOFT | 3,30 / 5 | 2/2 2027 | IMMER-retur ≤ 10 % og positiv DB |
| 4 | Sensorisk | STILLE | 2,40 / 5 | Q2 2027 | CE/EN 71-afklaring og ingen behandlingspåstande |

**Hvorfor ikke fire butikker på én gang:** Hver lancering kræver samme ejer i 3-4 uger (leverandør,
prøver, fotos, import, testordre, annoncer, kundeservice). Fire samtidige butikker ville dele én
annoncekonto, én lagerplads og én supportindbakke, og ingen af dem ville få nok læring til at
bestemme, om den skal leve. Planen kører dem sekventielt med en gate imellem.

**Hvad der er lavet allerede:** 21 varer og 36 sider med færdig dansk tekst, 20 kollektionstekster,
fire FAQ-sæt med strukturerede data, tre annoncer og tre e-mails til KABINE, samt en Shopify-CSV pr.
niche med fuld HTML. `python3 tools/deploy/niche_preflight.py --check` er grøn.

---

## 2. Sådan er valget lavet

Seks kriterier, vægtet efter hvad der faktisk kan vælte en lille dansk webshop — ikke efter hvor
spændende nichen lyder. Score 1-5 er en faglig vurdering oven på evidensen i §3; vægtene er et valg,
og de står her, så de kan ændres.

| Kriterium | Vægt | KABINE | IMMER | OPLOFT | STILLE |
| --- | ---: | ---: | ---: | ---: | ---: |
| Efterspørgsel og målgruppestørrelse | 20 % | 5 | 4 | 4 | 3 |
| Købsintention og priselasticitet | 15 % | 4 | 5 | 3 | 3 |
| Konkurrence og differentiering | 20 % | 3 | 2 | 2 | 2 |
| Dækningsbidrag og annonceplads | 20 % | 4 | 5 | 5 | 3 |
| Compliance- og påstandsrisiko | 15 % | 4 | 2 | 3 | 1 |
| Driftsrisiko (retur, fragt, skade) | 10 % | 4 | 3 | 2 | 2 |
| **Vægtet score** | | **4,00** | **3,55** | **3,30** | **2,40** |

Sådan læses de tre tætte kriterier:

- **Konkurrence:** KABINE møder store katalogbutikker, men få der sælger et forklaret sæt til
  kabinen og taler til leasingaflevering. IMMER og OPLOFT møder specialiserede butikker med
  præcis samme vare og lavere prisankre.
- **Compliance:** KABINE og OPLOFT kan beskrives fysisk (børste, hylde, vægt, mål). IMMER og især
  STILLE ligger tæt på helbreds- og behandlingssprog, hvor hver sætning skal kunne dokumenteres.
- **Drift:** KABINE sender lette pakker med lav retur. OPLOFT og IMMER sender store pakker med
  højere returfragt og større risiko for transportskade.

**Hvad der ville ændre rækkefølgen:** Et IKEA-prisbrud under 300 kr. på en borefri reol ville gøre
OPLOFT uinteressant ved 749 kr. En CE/EN 71-dokumentation plus en community-partner ville løfte
STILLE over OPLOFT. Et leverandørtilbud, der gør IMMER-dynen 100 kr. dyrere, ville sænke IMMER til
tredjepladsen. Ingen af delene er målt endnu.

---

## 3. Markedet pr. niche

Tallene er efterspørgselsproxyer, ikke bevis for at et bestemt produkt sælger. Konkurrentbilledet er
set i butikkernes egne kataloger 9/11 2026, ikke en pristest.

### 3.1 KABINE — bil-interiør

- **Målgruppe:** Danmarks Statistik opgør **2,86 mio. personbiler** ved indgangen til 2025, og
  **61,6 % af husstandene** råder over en bil. Det er en bred målgruppe, men købsintentionen er
  lav uden et konkret problem — derfor er annoncen et problem (sømmen), ikke et produkt.
- **Konkurrenter:** thansen, GreenGoing (katalog med mere end 1.600 bilplejeprodukter), Luxcare,
  Autoglym-forhandlere og lokale detailere. De vinder på sortiment og genkendelse; de taber på
  forklaring og på at sælge et lille sæt frem for en katalog.
- **Differentiering:** Kit 01 som ét forklaret sæt til søm, panel og luftdyse; Kit 02 til
  leasingaflevering med tjekliste; bundle med organizer. Prisen 499 kr. ligger under en
  detailerbehandling til 1.500 kr. — men over en løs børste.
- **Økonomi:** hero-DB efter retur **222,95 kr.**, break-even ROAS **2,24x**. Det er den tyndeste
  margen af de fire og samtidig den mest forudsigelige drift: let pakke, 6 % retur, ingen pasform.
- **Risiko:** Sættet indeholder ikke væske. Ved 499 kr. skal kunden se værdi i børste og mikrofibre
  alene. Det er den vigtigste ejerbeslutning i hele planen (se §7.1).

### 3.2 IMMER — søvn

- **Målgruppe:** SIF rapporterede, at **15 % af voksne — omkring 800.000 — næsten aldrig får søvn
  nok til at føle sig udhvilede**. Det er en stor målgruppe med høj intention om aftenen og i
  januar.
- **Konkurrenter:** Matas, Sleepbetter, Nattelyst, Anew og en lang række mærker med tyngdedyner,
  masker og hvid støj. Flere har abonnement, fysiske butikker og anmeldelser; priserne er trykket.
- **Differentiering:** Få produkter uden app og konto, ærlig tekst om at det ikke er behandling,
  og en pakke der samler vægt, mørke og lyd. Det er en svagere differentiering end KABINEs
  "sømmen" — og derfor er annoncen også sværere.
- **Økonomi:** hero-DB **241,53 kr.**, break-even ROAS **3,31x** — den højeste ROAS og det største
  AOV-løft. Til gengæld 10 % retur, stor pakke og højere fragtomkostning.
- **Risiko:** Helbreds- og søvnpåstande. Forbrugerombudsmanden kræver dokumentation for
  sundhedsudsagn, og "behandler søvnproblemer" er ikke en sætning, butikken kan bruge. Al tekst i
  pakken er skrevet som fysisk beskrivelse + afvisning, ikke som effekt.

### 3.3 OPLOFT — opbevaring i lejebolig

- **Målgruppe:** **51,2 % af danske boliger bebos af lejere** (1.434.000 boliger), og **19,9 % af
  husstandene bor i alment byggeri** (569.756). Lejere må ofte ikke bore, og de flytter oftere.
- **Konkurrenter:** IKEA (borefri hylder og kroge fra 19 kr.), BAUHAUS, RackBuddy (698-1.155 kr.)
  og et stort udbud af teleskopreoler på markedspladser. Prisankeret er hårdt: en reol til 749 kr.
  skal slå en IKEA-løsning til under 600 kr. på plads, montering og kvalitet.
- **Differentiering:** Systemet (reol + dør + kasser), mål og belastning skrevet eksplicit, og
  entrepakken til 899 kr. mod 1.497 kr. købt separat.
- **Økonomi:** hero-DB **252,58 kr.**, break-even ROAS **2,97x**, bedste bundle-løft efter STILLE.
- **Risiko:** Volumen og skade. En reol på 240-290 cm kræver delt karton under 115 cm, og en retur
  koster 149 kr. i fragt alene. Prisen er desuden en introduktionspris, ikke en permanent pris.

### 3.4 STILLE — sensorisk

- **Målgruppe:** Voksne, der foretrækker jævnt tryk og mindre lyd. Målgruppen er reelt stor, men
  svær at ramme uden at bruge diagnoseord, og annoncen skal bevise noget om et produkt, der minder
  om tøj.
- **Konkurrenter:** Vicca, SpektrumShop, Bents ("størst i Danmark") og et stort udvalg af
  fidgets, vægtede tæpper og ørepropper. Flere har fysiske butikker og fællesskaber.
- **Differentiering:** Voksne, enkle produkter, ærlig sikkerhedstekst. Men kategorien er fyldt, og
  den følelsesmæssige tro værdighed kan ikke købes med et annoncebudget.
- **Økonomi:** hero-DB **173,44 kr.**, break-even ROAS **3,17x**, højeste retur (12 %) og største
  pasformsproblem.
- **Risiko:** Hvis et produkt kan opfattes som legetøj, gælder legetøjssikkerhed (CE/EN 71). Hvis
  det markedsføres mod en diagnose eller som behandling, gælder reglerne for sundhedsudsagn.
  Begge dele skal afklares, før der købes lager.

---

## 4. Økonomien som planen hviler på

Fra `tools/deploy/niche_preflight.py --stats`, 9/11 2026. Alle kostpriser og fragtpriser er
**estimater** — der ligger ingen leverandørtilbud i repoet.

| Niche | Hero-DB efter retur | Break-even ROAS | Bundle-løft | Ordrer til at dække 1.400 kr./md |
| --- | ---: | ---: | ---: | ---: |
| KABINE | 222,95 kr. | 2,24x | 44,07 kr. | 7 |
| OPLOFT | 252,58 kr. | 2,97x | 68,88 kr. | 6 |
| STILLE | 173,44 kr. | 3,17x | 196,26 kr. | 9 |
| IMMER | 241,53 kr. | 3,31x | 98,60 kr. | 6 |

**KABINE-følsomhed, regnet 9/11:** sænkes Kit 01 fra 499 til 399 kr. med samme kostpris (95 kr.),
falder break-even-CPA fra ca. 223 til ca. **149 kr.**. Det gør annoncen sværere, ikke lettere. Hæves
prisen, stiger CPA-loftet, men konkurrencen mod detailerprisen bliver svagere. Beslutningen skal
tages på en prøve og et leverandørtilbud, ikke på mavefornemmelse.

**Kontantbehov pr. lancering:** ca. **15.700 kr.** (10.000 kr. annoncebudget + 5.700 kr. buffer).
Fire lanceringer sekventielt er ca. 63.000 kr. over fem måneder; samtidigt ville de kræve mere
lagerkapital og flere hænder, end planen har.

---

## 5. Fælles fundament — Fase 0 (9.-15. november)

Gælder alle fire butikker. Intet annoncebudget må bruges, før punkterne er lukket.

| # | Handling | Bevis på at den er lukket |
| --- | --- | --- |
| F1 | CVR, telefon, adresse og bankaftale | Firmaoplysningerne kan indsættes i handelsbetingelser og på fakturaen |
| F2 | Navn og domæne cleared | Registrar bekræfter køb/leje; ingen aktiv dansk butik med samme navn; varemærketjek noteret |
| F3 | Shopify-konto og betalingsaftale | Testordre gennemført med MobilePay/kort og refunderet |
| F4 | Emballageproducentansvar | Registrering bekræftet, før første salg |
| F5 | GPSR-dokumentation | Producent/ansvarlig person, produkt-ID, sikkerhedsoplysninger og advarsler pr. vare |
| F6 | Juridiske sider | Handelsbetingelser, privatliv, fortrydelse og cookiebanner udfyldt og gennemlæst |
| F7 | Tracking | GA4 + Meta Pixel/CAPI + Consent Mode v2; køb, kurv og visning målt i test |
| F8 | Forsikring og regnskab | Produktansvar/forsikring afklaret; bogføring aftalt |

**Domænefundet 9/11 (DNS-opslag):** alle fire ønskede domæner er registreret.

| Ønsket | Status målt 9/11 | Konsekvens |
| --- | --- | --- |
| kabine.dk | NS hos Curanet, ingen A/www | Taget, men ikke aktivt site. Kræver køb/leje eller nyt navn |
| oploft.dk | NS hos One.com, A 46.30.211.38, ingen titel | Taget; sandsynligvis parkeret |
| stille.dk | NS hos Simply.com, A 94.231.103.100 | Taget; aktiv dansk side |
| immer.dk | NS hos Simply.com, A 93.191.156.114 | Taget; aktiv dansk blog "Min (vid)underlige verden" |

DNS-svar uden NS betyder **ikke** fri domæne — kun at navnet ikke er delegeret. Registrar og
varemærkeregister skal bekræfte. Kandidater der så ledige ud i DNS 9/11: `kabinekit.dk`,
`kabinepleje.dk`, `kabinebiler.dk`, `oploeft.dk`, `oploftopbevaring.dk`, `lejeboligopbevaring.dk`,
`stillehverdag.dk`, `sansestille.dk`, `stillepause.dk`, `immersoevn.dk`, `soevnimmer.dk`,
`hvileimmer.dk`. De er ikke reserveret af os.

**Navneadvarsel:** `stille.dk` og `immer.dk` har aktive danske sites. Selv med et nyt domæne kan
navnet skabe forveksling og gøre varemærkebeskyttelse svær. KABINE og OPLOFT er almindelige ord;
STILLE og IMMER bør renames, hvis ejeren vil bygge på navnet i mere end en test.

---

## 6. Deploymentplan pr. niche

### 6.1 KABINE — 16. november til 13. december

**Uge 1-2: butikken**
1. Opret Shopify-butik, betaling og fragtzone. Sæt behandlingstid og fragtpris ind i
   `levering-og-retur` og `handelsbetingelser`.
2. Importér `deploy/nicher/shopify-kabine.csv` som **kladder**. Ret kostpriser og lager, og
   erstat `[EJER]` i produktteksten.
3. Opret de fem kollektioner med teksten fra `deploy/nicher/kabine-sider.md`, og indsæt de ni sider
   (inkl. FAQ med JSON-LD).
4. Læg billeder ind: Kit 01 i brug, sømmen før/efter, organizer i bil, bundle som gave. Alt-tekster
   ligger i CSV'en.
5. Testordre: betaling, ordrebekræftelse, sporingsmail, returformular og fortrydelsesmail.

**Uge 3-4: trafik**
6. Fire kreative varianter fra hooksene (sømmen, prisen, afleveringen, timeren). Hånd-only,
   intet andet brand i billedet, ingen påstand uden bevis i samme klip.
7. Budget 800 kr. i uge 1, 1.700 kr. i uge 2, 3.000 kr. i uge 3, 4.500 kr. i uge 4.
8. Daglig rutine: CPA, hook rate, CTR, konvertering, retur og lager. Pause en annonce efter tre
   dage over break-even.

**Gates**

| Gate | Krav | Hvis nej |
| --- | --- | --- |
| K1 | Hook rate ≥ 25 % og CTR ≥ 1,2 % | Skift kreativt, ikke produkt |
| K2 | Konvertering ≥ 0,8 % | Ret produktside, pris eller fragt før mere spend |
| K3 | CPA ≤ 223 kr. | Pause annoncen; regn på pris/kostpris igen |
| K4 | ≥ 30 ordrer og positiv DB over fire uger | Ingen IMMER-start; brug uge 5-6 på at rette op |

**Stopregler:** CPA over 300 kr. i to uger uden forbedring; retur over 10 %; leverandør kan ikke
dokumentere GPSR; eller lageret ikke kan levere inden for den lovede tid. Så lukkes annoncerne, og
butikken går i "kun organisk" til årsagen er fundet.

### 6.2 IMMER — 5. januar til 1. februar

**Forudsætning:** KABINE har bestået K4, og der er ledig lagerplads og supportkapacitet.

1. Samme Shopify- og betalingsopskrift; nyt domæne, nye sider, ny farvepalet.
2. Importér `shopify-soevn.csv` som kladder. Læg vægt, mål og sikkerhed ind som **produktets
   vigtigste oplysninger**, ikke som fodnote.
3. Billeder: dynen på seng, masken på ansigt i dagslys, maskinen på natbordet, pakken samlet.
4. Tracking og testordre. Særligt: refusionsflow for en dyr og stor vare.
5. Annoncer i januar med "rummet"-vinklen, ikke "søvnløshed". Budget 800-1.000 kr./uge i to uger,
   derefter gate.

**Gates:** konvertering ≥ 1,0 %, CPA ≤ 242 kr., retur ≤ 10 %, og ingen kunde klager over løfter,
der ikke kan holdes. **Stop:** hvis retur eller klager peger på, at teksten lover effekt, stopper
annoncen samme dag — det er en compliance-fejl, ikke en kreativ justering.

### 6.3 OPLOFT — 2. februar til 1. marts

**Forudsætning:** IMMER har positiv DB og retur under 10 %, og der er kapital til reol-lager.

1. Importér `shopify-lejebolig.csv`; bekræft kartonlængde under 115 cm og fragtpris pr. reol.
2. Produktbilleder med mål på: reol i entre, dør med seks rum, hylde på flise, kasser i reol.
3. Mål- og monteringssiden skal ligge før annoncen: den er både kundeservice og returforebyggelse.
4. Annoncer mod "må ikke bore" og "flytbar plads", ikke mod "depositum garanteret".

**Gates:** konvertering ≥ 2,0 % ved 300 besøg, CPA ≤ 253 kr., retur ≤ 10 %, nul klager over hold.
**Stop:** hvis IKEA-prisankeret gør 749 kr. uforsvarligt, eller hvis returfragten æder marginen.

### 6.4 STILLE — Q2 2027, ikke før

**Forudsætning:** skriftlig afklaring af, om hvert produkt er legetøj, beklædning eller PPE, og
hvilke CE/EN-standarder der gælder. Uden den købes intet lager.

1. Vælg leverandør med teknisk fil, materialer, vægt og vaskeanvisning.
2. Få en ekstern gennemgang af alle tekster mod reglerne for sundhedsudsagn. Ingen diagnoseord,
   ingen behandlingsløfter, ingen "virker mod".
3. Byg en størrelsesguide med målte tal, ikke en konverteringstabel. 12 % retur er for højt til at
   gætte.
4. Start med ét produkt (hættetrøjen), ikke hele pakken, og mål retur og pasform i fire uger.

**Gates:** retur ≤ 12 %, CPA ≤ 173 kr., ingen påstande uden dokumentation. **Stop:** hvis
klassificeringen er uafklaret, eller hvis retur og pasform ikke kan forklares enkelt.

---

## 7. Indholdspakken — hvad der er lavet, og hvordan den bruges

Kilden er `deploy/nicher/indhold/<slug>.json`. Den indeholder al tekst, og `niche_preflight.py`
nægter at bygge, hvis en vare, en side eller en kollektion mangler tekst, hvis et `[EJER]`-felt er
skrevet forkert, eller hvis teksten indeholder en udokumenteret påstand.

| Fil | Indhold |
| --- | --- |
| `deploy/nicher/indhold/kabine.json` m.fl. | Sider, produkter, kollektioner, FAQ, hooks; KABINE også annoncer og e-mails |
| `deploy/nicher/shopify-<slug>.csv` | 5-6 varer pr. niche med fuld body-HTML, SEO-titel, meta og alt-tekst — **kladder** |
| `deploy/nicher/<slug>-sider.md` | Ni sider, fem kollektioner, FAQ, annoncer/e-mails og produkt-SEO klar til at klistre ind |
| `deploy/nicher/<slug>.json` | Maskinpayload med økonomi, counts og ejerfelter |

**Sådan importeres:** Shopify → Produkter → Importér → vælg CSV → status forbliver `draft`.
Opret derefter de fem kollektioner manuelt (teksten står i sidernes markdown), indsæt siderne, og
erstat alle `[EJER: …]` med ejerens oplysninger. CSV'en har **ingen billeder** — `Image Src` er tom
med vilje, fordi der ikke findes produktfotos endnu. Alt-teksterne er skrevet, så de kan bruges, når
billederne kommer.

**Det, der stadig er tomt:** firmaoplysninger, fragtpriser, leveringstider, returadresse, mål,
materialer, belastning, vaskeanvisninger og produktfotos. De står som `[EJER]` i stedet for at være
gættet. Det er med vilje: en juridisk oplysning eller en belastningsgrænse, som agenten finder på,
er dyrere end et tomt felt.

---

## 8. Jura og compliance — det, der gælder alle fire

Dette er en arbejdsliste, ikke juridisk rådgivning. Ejeren skal have handelsbetingelser, privatliv
og påstande gennemgået, før butikken åbner.

1. **Oplysningspligt:** navn, adresse, e-mail, pris inkl. moms, levering og returvilkår skal stå
   tydeligt. Se [Forbrug.dk](https://forbrug.dk/regler/opslagsvaerk-forbrugerleksikon/oplysningspligt).
2. **14 dages fortrydelse og 2 års reklamation:** formular, returadresse og hvem der betaler
   returfragten skal stå på siden — ikke først i en e-mail.
3. **GPSR (fra 13/12 2024):** produktidentifikation, producent/ansvarlig person i EU og
   sikkerhedsoplysninger skal med i det online produkttilbud. Se
   [forordningen](https://eur-lex.europa.eu/eli/reg/2023/988/oj/eng). Sælges varen under butikkens
   eget navn, er butikken fabrikant — ikke distributør.
4. **Emballageproducentansvar:** registrering og rapportering afhænger af virksomhedens rolle i
   kæden. Se [Dansk Producentansvar](https://producentansvar.dk/produkter-og-ansvar/emballage/hvem-har-producentansvaret-for-emballage/vaerd-at-vide-som-distributoer-af-emballage/).
5. **Sundheds- og effektpåstande:** hver påstand skal kunne dokumenteres. Se
   [Forbrugerombudsmanden](https://forbrugerombudsmanden.dk/alle-emner/forbud-mod-vildledning/helbreds-og-sundhedsudsagn).
   Derfor siger IMMER og STILLE, hvad produkterne **er** — ikke hvad de helbreder.
6. **Moms:** salg over 50.000 kr. i en 12-månedersperiode kræver momsregistrering. Se
   [Skat](https://skat.dk/en-us/businesses/vat/vat-what-to-do/how-to-register-your-business).
7. **Farligt gods:** ingen væske i fly. KABINEs kit er valgt uden rensemiddel af den grund; hvis
   ejeren vil sælge rens ved siden af, skal transportreglerne tjekkes separat.
8. **Førpris:** ingen opdigtet førpris. "Introduktionspris" skal være sand og tidsafgrænset.

---

## 9. Risici og hvad der får planen til at stoppe

| Risiko | Sandsynlighed | Konsekvens | Modtræk |
| --- | --- | --- | --- |
| Domæde/navn kan ikke cleared | Høj (alle fire taget) | Ingen butik | Køb/lej eller rename før emballage og fotos |
| Leverandør kan ikke levere GPSR-papir | Mellem | Ingen lovlig handel | Skift leverandør; prøv to |
| Kit 01 uden væske føles for dyr ved 499 | Mellem | Lav konvertering | Test prøve; overvej 399 eller rens fra dansk leverandør |
| Annonce-CPA over break-even i to uger | Høj i uge 1-2 | Underskud | Stopregel, ikke "vent og se" |
| Retur over 10-12 % | Mellem | Margin forsvinder | Mål, pasform og ærlig tekst før annonce |
| Helbredspåstand i IMMER/STILLE | Mellem | Juridisk og omdømme | Tekstgennemgang; ingen diagnoseord |
| Fire butikker deler ejerens tid | Høj hvis parallelt | Alle fire halve | Sekventiel plan med gates |

**Stopregel for hele porteføljen:** hvis KABINE ikke består K4 inden 13/12, startes ingen af de
andre butikker. Så bruges januar på at rette KABINE eller på at lukke den. Det er billigere end at
åbne fire butikker, der alle mangler det samme: en pris, der kan bære annoncen.

---

## 10. Hvad der ikke er verificeret

- **Leverandørpriser, fragt og leveringstid.** Alle kostpriser er estimater. Der ligger intet
  tilbud i repoet.
- **Annoncepriser (CPM/CPC/CPA).** Ingen kampagne er kørt. Break-even-CPA er regnet, ikke målt.
- **Søgevolumen og købsintention.** Markedstallene er proxyer; der er ikke hentet søgevolumen.
- **Domænets ejerskab og pris.** DNS viser, at navnene er delegeret; hvem der ejer dem, og om de
  er til salg, står ikke i DNS.
- **Shopify-, betalings- og fragtaftaler.** Ingen konto er åbnet. MobilePay/Apple Pay/kort skal
  bekræftes i den konkrete konto.
- **Jura.** Teksterne er skabeloner med ejerfelter. De skal gennemlæses af en rådgiver.
- **Produktmål, materialer og belastning.** Står som `[EJER]`, fordi ingen prøve er målt.
- **Billeder.** Der findes ingen produktfotos. CSV'ens billedkolonne er tom med vilje.

---

## 11. Kilder

- Danmarks Statistik, bilbestand: <https://www.dst.dk/da/Statistik/udgivelser/NytHtml?cid=51081>
- SIF/SDU, utilstrækkelig søvn: <https://www.sdu.dk/da/sif/ugens_tal/ut_32_en_stigende_andel_danskere_oplever_utilstraekkelig_soevn>
- Forbrugerombudsmanden, helbreds- og sundhedsudsagn: <https://forbrugerombudsmanden.dk/alle-emner/forbud-mod-vildledning/helbreds-og-sundhedsudsagn>
- GPSR, forordning (EU) 2023/988: <https://eur-lex.europa.eu/eli/reg/2023/988/oj/eng>
- Dansk Producentansvar, emballage: <https://producentansvar.dk/produkter-og-ansvar/emballage/hvem-har-producentansvaret-for-emballage/vaerd-at-vide-som-distributoer-af-emballage/>
- Forbrug.dk, oplysningspligt: <https://forbrug.dk/regler/opslagsvaerk-forbrugerleksikon/oplysningspligt>
- Skat, momsregistrering: <https://skat.dk/en-us/businesses/vat/vat-what-to-do/how-to-register-your-business>
- Interne forarbejder: [`bilinterioer-launch-2026-11-09.md`](bilinterioer-launch-2026-11-09.md),
  [`niche-2-lejebolig-opbevaring-2026-11-09.md`](niche-2-lejebolig-opbevaring-2026-11-09.md),
  [`niche-deploy-forarbejde-2026-11-09.md`](niche-deploy-forarbejde-2026-11-09.md).
- Domæne- og DNS-måling: egne opslag 9/11 2026 (`dig NS`, `dig A`, HTTP-titel).
