# Bil-interiør som dropshipping-niche (9/11 2026)

Nichen er **valgt**: bil-interiør og detailing, drevet som egen butik med tre produkter og
ét mer-salg. Dokumentet er forberedelsen — beslutningerne, de syv input, økonomien, de 30 dage,
de ti manuskripter og de krav butikken skal opfylde, før den første annonce går live.

Aritmetikken er kørt gennem de fire formler i
[roi-model-dropshipping-2026-11-09.md](roi-model-dropshipping-2026-11-09.md) §2 og verificeret
med Python i dette pas. Alle priser er i DKK inkl. 25 % moms, medmindre andet står. **Indkøbs-,
fragt- og annoncepriser er estimater** — der er ingen leverandørtilbud bag tallene.

## 1. Beslutningen

| Valg | Beslutning | Begrundelse |
| --- | --- | --- |
| Niche | Bil-interiør / detailing | Kreativt billigst at producere (hænder + en beskidt måtte, intet ansigt), evergreen søgeefterspørgsel, ingen pasform og dermed lav retur, højeste margin af de fire kandidater |
| Brand | **KABINE** (`kabine.dk` skal tjekkes, ikke antages) | »Kabinen« er bilens interiør på dansk — navnet forklarer kategorien uden billede og holder til mere end rengøring |
| Hero-produkt | **Kabine Kit 01**, 499 kr. | Eneste produkt i uge 1. Én side, ét løfte, ét før/efter |
| Produkt 2 | **Kabine Kit 02 — Aflevering**, 449 kr. | Egen landingsside og eget pain point (leasingaflevering), så de to annoncer ikke konkurrerer |
| Mer-salg | **Sæde-gap-organizer** til 129 kr. som order bump | Kan ikke bære betalt trafik alene (§4), men har 62,20 kr. i marginalt DB i samme pakke |
| Fravalgt | Alt med væske i pakken | Rensemidler er farligt gods, må ikke flyve og kræver CLP-mærkning og sikkerhedsdatablad. Væsken købes hos EU-leverandør og lægges i pakken hos 3PL, eller kunden bruger sin egen |

**Hvad der blev fravalgt af nicher, og hvorfor:** sensorisk/neurodivergent (kræver ægte
community-troværdighed i kreativet — sværere at producere 10-15 videoer om ugen), katte-tech
(elektronik, CE/WEEE og supportbyrde), søvn (mættet på kreativt niveau og højeste annoncepris).
Bil-interiør er den mindst dårlige niche for en ny butik, ikke den mest spændende.

## 2. De syv input pr. produkt

| Produkt | Pris | Indk. | Fragt | Emb. | Retur | Returomk. | Faste |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Kabine Kit 01 (interiør) | 499 | 95 | 45 | 10 | 6 % | 85 | — |
| Kabine Kit 02 (aflevering) | 449 | 85 | 45 | 10 | 6 % | 85 | — |
| Sæde-gap-organizer (alene) | 199 | 30 | 39 | 6 | 8 % | 85 | — |
| Gap-organizer (order bump) | 129 | 30 | 0 | 4 | 3 % | 85 | — |
| Bundle Kit 01 + gap | 599 | 125 | 45 | 12 | 6 % | 85 | — |
| Butik i alt | — | — | — | — | — | — | **1.400** |

Faste 1.400 kr/md er sat sammen af webshop 279, apps 150, domæne 12, regnskab 700, forsikring
150 og emballageproducentansvar ca. 110. Returraten er 6 % og ikke de 7 %, kategorien plejer at
få, fordi der ikke er pasform eller størrelser — men det er en **antagelse**, ikke en måling.
Bump'en har 0 i fragt, fordi den lægges i den pakke kunden allerede betaler for.

## 3. Produktøkonomien

| Produkt | Pris | Netto | DB | DB efter retur | DB % | Break-even CPA |
| --- | --- | --- | --- | --- | --- | --- |
| Kabine Kit 01 (interiør) | 499 | 399,20 | 242,61 | **222,95** | 55,85 % | **223** |
| Kabine Kit 02 (aflevering) | 449 | 359,20 | 213,17 | **195,28** | 54,37 % | **195** |
| Sæde-gap-organizer (alene) | 199 | 159,20 | 80,97 | **67,69** | 42,52 % | **68** |
| Gap-organizer (order bump) | 129 | 103,20 | 66,76 | **62,20** | 60,27 % | — |
| Bundle Kit 01 + gap | 599 | 479,20 | 289,49 | **267,02** | 55,72 % | **267** |

**Fund 1: dette er ikke en 120-krone-niche.** Hero-produktet har 222,95 kr. i dækningsbidrag efter
retur. Til sammenligning lå den bedste vare i småhunde-casen på 148,59 kr. Det er hele
forskellen: en bilvare til 499 kr. kan betale 223 kr. for en ordre, og det er nok til at overleve
et dyrt første møde med Meta. **Men først når CPA kommer under 249 kr.** — se §5.

**Fund 2: bundlen redder ikke casen, den løfter den.** I småhunde-casen var bundlen den eneste
vare, der tjente penge ved CPA 150. Her er break-even-ROAS næsten identisk med og uden bundle
(2,24x mod 2,25x, §5). Bundlen er derfor et **AOV-løft**, ikke en redningsplanke — og det er
ærligt talt en svagere begrundelse for at bygge den. Den bygges alligevel, fordi den koster næsten
intet og giver 18,81 kr. mere pr. ordre.

## 4. CPA-træffet — og hvorfor bump'en ikke står i tabellen

| CPA | Kit 01 | Kit 02 | Gap (alene) | Bundle |
| --- | --- | --- | --- | --- |
| 70 | +152,95 | +125,28 | **−2,31** | +197,02 |
| 90 | +132,95 | +105,28 | −22,31 | +177,02 |
| 110 | +112,95 | +85,28 | −42,31 | +157,02 |
| 130 | +92,95 | +65,28 | −62,31 | +137,02 |
| 150 | +72,95 | +45,28 | −82,31 | +117,02 |
| 200 | +22,95 | **−4,72** | −132,31 | +67,02 |
| 250 | **−27,05** | −54,72 | −182,31 | +17,02 |
| 300 | −77,05 | −104,72 | −232,31 | −32,98 |

**Fund 3: 199-kroners-varen taber penge ved CPA 70.** Den kan ikke annonceres alene under nogen
realistisk annoncepris, og skal derfor kun eksistere som order bump og som loft for fri fragt.
Det er samme fælde som snustæppet i den forrige case, og den er værd at gentage: når en niche
»ikke virker«, er det ofte prisklassen, der ikke kunne betale for klikket.

Bump'en står bevidst ikke i tabellen. En CPA er anskaffelsesomkostningen for en **kunde**, og
bump'en har ingen — den sælges til en kunde, der allerede er købt. At sammenligne dens 62,20 kr.
med en CPA er en kategorifejl, og den slags fejl får en til at forkaste et mer-salg, der i
virkeligheden er ren margin.

## 5. Den blandede kurv — det tal alt hænger på

Med 50 % Kit 01, 25 % Kit 02, 25 % bundle og 35 % bump-tilslutning:

| Kurv | AOV | DB efter retur | DB % af netto | Break-even ROAS |
| --- | --- | --- | --- | --- |
| Med bundle og bump | **556,65** | **248,82** | 55,88 % | **2,24x** |
| Uden bundle, med bump | 529,15 | 236,42 | 55,72 % | 2,24x |
| Uden bundle og bump | 484,00 | 214,65 | 55,43 % | 2,25x |

**Fund 4: break-even er 248,82 kr. pr. ordre, og det er den ene sætning, der afgør butikken.**
Alt over 249 kr. i CPA er et underskud, uanset hvor mange ordrer der kommer — der findes ingen
volumen, der redder en negativ enhedsøkonomi. Med Dansk CPM på 32-48 kr. i mode/livsstil, CTR
1,5 % og konvertering 1,8 % lander CPA på ca. 167 kr. i regnestykket; ved CPM 45 kr. og
konvertering 1,0 % lander den på 300 kr. Spændet mellem de to tal er hele forretningen.

## 6. Måned 1 som regnestykke

Planen i §7 spenderer 10.000 kr. og bliver gradvist bedre til at ramme CPA:

| Uge | Spend | CPA | Ordrer |
| --- | --- | --- | --- |
| 1 | 800 | 350 | 2,29 |
| 2 | 1.700 | 280 | 6,07 |
| 3 | 3.000 | 220 | 13,64 |
| 4 | 4.500 | 190 | 23,68 |
| **I alt** | **10.000** | **219** | **45,68** |

Omsætning 25.426,50 kr., dækningsbidrag 11.365,70 kr., og efter 10.000 kr. annoncer og 1.400 kr.
faste står der **−34,30 kr.** Måneden kræver **45,82 ordrer** for at gå i nul. Planen giver 45,68.

**Fund 5: planen er ikke optimistisk — den er kalibreret til at lande på nul, og det er med vilje.**
Måned 1 er en udgift til markedsundersøgelse, ikke en investering. Det, der ejes 8. december, er
45-55 ordrer, 40-60 anmeldelser, 3-5 validerede hooks og et pixel med nok data til at januar kan
skaleres. Forventet kontantresultat: **−1.500 kr. til +1.500 kr.**

## 7. De 30 dage

Vinduet er **9. november – 8. december 2026**. Det ligger midt i årets dyreste annoncevindue
(Black Friday 27/11), så CPM skal forventes 30-60 % over normalen i uge 3-4. Alternativet — at
starte 5. januar — giver 25-40 % lavere CPA, men mister læringen før jul.

| Uge | Fokus | Leverancer | Budget | KPI-gate |
| --- | --- | --- | --- | --- |
| 1 (9.-15/11) | Fundament og kreativ fabrik | Butik live med MobilePay, Apple Pay og kort; Meta CAPI + Consent Mode v2; 4 obligatoriske sider; 10-12 videoer optaget på én dag; 6 hooks testet ens | 800 | Hook rate ≥ 25 %, CTR ≥ 1,2 %, CVR ≥ 0,8 %, 2-5 ordrer |
| 2 (16.-22/11) | Første validering | 4-5 hooks dræbt; 3-4 varianter af de 2 bedste; Kit 02 får egen side; abandoned checkout + review-request flow; support svarer inden 4 timer | 1.700 | CTR ≥ 1,5 %, CVR ≥ 1,3 %, 8-14 ordrer, AOV ≥ 470, CPA ≤ 210 |
| 3 (23.-29/11) | Black Week | Tilbud uden prisnedsættelse: Kit 01 + gratis gap-organizer (koster 30 kr., føles som 199); julegave-vinkel på plads; AOV-maskine tændt | 3.000 | CTR ≥ 1,8 %, CVR ≥ 2,0 %, 18-24 ordrer, AOV ≥ 520, ROAS ≥ 3,2x |
| 4 (30/11-8/12) | Konsolidering og julehandel | Alt under ROAS 2,0x skåret; sidste-leveringsdato kommunikeret; gave-bundle 599; hook-bibliotek gemt til januar | 4.500 | CPA ≤ 175, ROAS ≥ 2,9x, 22-28 ordrer, AOV ≥ 540 |

**Dag 14-gaten:** ≥ 8 ordrer og mindst én annonce over ROAS 2,5x → fortsæt. Under 5 ordrer *og*
CVR under 1 % efter 300 klik → problemet er siden, prisen eller leveringen, ikke annoncen. Stop
og fiks, brug ikke flere penge.

**Daglig rutine, 20 minutter:** tjek CPA og ROAS pr. annonce, sluk 1-2 tabere over 250 kr.,
upload 1-2 nye creatives, svar på support. Kreativ udbrænding er 7-14 dage på TikTok og 10-21 på
Meta — opgaven i 30 dage er at være en kreativ fabrik, ikke at optimere målgrupper.

**Scale-regel:** CPA under 170 kr. i tre dage → +25 % budget hver anden dag. **Kill-regel:** CPA
over 250 kr. i tre dage → pause. Ingen undtagelser; nybegyndere taber pengene på at »give det én
dag mere«.

**Kontantbehov før første salg:** 15.700 kr. i det anbefalede scenarie (annoncer 10.000, prøver
700, startlager 2.400, Shopify og apps 629, kreativt udstyr 500, buffer 1.470). Lageret er
**revolverende** kapital, ikke en engangsudgift: genbestil når der er 8-10 dage tilbage, og brug
omsætningen. Modellen i §3-6 antager direkte forsendelse og har derfor **ingen lagerpost** — det
lokale lager er en ekstra binding, der skal med i likviditeten, ikke i enhedsøkonomien.

## 8. Ti hooks som manuskript

Alle er hånd-only, 15-30 sekunder, intet ansigt. Første linje er de tre sekunder, der afgør om
videoen lever.

- **Sømmen.** Åbn bagagerumslemmen, lys ind i sømmen. »Din bil er ikke beskidt. Den er bare aldrig blevet renset *her*.« → close-up af børsten, før/efter. CTA: »Kit i bio.«
- **ASMR, ingen tale.** Tør måtte, børste i femten sekunder, kun lyd. Slut på rent sæde. CTA i tekst.
- **Leasing.** Hånd holder afleveringspapir. »POV: du afleverer leasingbilen på fredag.« → tre hurtige indgreb. CTA: »Afleveringskittet.«
- **Prisen.** »Detaileren ville have 1.500 kr. Jeg brugte 499.« → samme vinkel, samme lys, split-screen.
- **Tre steder.** »Tre steder i din bil du aldrig har rengjort.« Tal overlays 1, 2, 3 med reveal pr. sted.
- **Kæresten.** »Min kæreste troede, jeg havde fået ny bil.« → pan over interiøret, slut på hendes reaktion (kun lyd).
- **Gap.** Hånd presser sæde-gap-organizeren ned. »Der ligger fire år med kaffe her.«
- **Depositum.** »Sådan får du depositummet tilbage.« → tre defekter, tre indgreb, samme vinkel.
- **Timeren.** Split-screen med klokke i hjørnet: 0:00 beskidt, 4:30 rent. Ingen klip, ingen tale.
- **Gave.** Julepapir, kit pakkes ud. »Gave til ham der elsker sin bil.« Kør fra 1. december.

To regler for kreativet: **byg aldrig et hook på et andet brand** (ingen »som [mærke]«), og
**lov aldrig et resultat du ikke kan vise i samme klip.**

## 9. Butik, betaling, tracking og jura

| Krav | Hvorfor | Status |
| --- | --- | --- |
| MobilePay + Apple Pay + kort | Uden MobilePay falder dansk konvertering mærkbart | Skal sættes op |
| Meta CAPI + Consent Mode v2 | Uden server-side tracking mister optimeringen 30-50 % af sin data | Skal sættes op |
| Leveringstid 1-3 dage på hero | Den største enkeltårsag til at en dansk køber gennemfører | Lokalt lager, 10-25 stk. |
| 14 dages fortrydelsesret og 2 års reklamation | Obligatorisk, og det løfter konvertering at skrive det tydeligt | Skal skrives |
| **GPSR: EU-ansvarlig person** | Uden dokumentation er varen ikke lovlig at sælge i EU | Krav til leverandørvalg |
| **Emballageproducentansvar** | Den der først gør pakkede varer tilgængelige i DK skal registreres senest 14 dage før markedsføring | Skal registreres |
| **Ingen falsk før-pris** | Dansk prisoplysningspraksis kræver at en før-pris reelt har været gældende op til tilbuddet | Gælder Black Week |
| Værdi under 150 EUR pr. pakke | Under grænsen kan moms håndteres ved checkout via IOSS; over får kunden en importregning | 499-599 kr. er sikkert |
| Ingen væske i fly | Rensemidler er farligt gods (ADR/limited quantity) og kræver CLP + sikkerhedsdatablad | Købes i EU |
| TARIC før indkøbsprisen er reel | Værktøj og mikrofiber skal slås op, ikke gættes (HS 9603 og 6307 er kandidater) | Ikke slået op |

## 10. Stopkriterier, aftalt før start

| Signal | Grænse | Beslutning |
| --- | --- | --- |
| Hook rate (3-sek.) | under 20 % efter 1.000 visninger | Kreativet er dødt, ikke produktet |
| CTR | under 1 % efter 1.500 visninger | Forkert målgruppe eller for bredt publikum |
| Add-to-cart | under 5 % af besøg | Pris, tillid eller side — ikke annoncen |
| Konvertering | under 1,5 % ved 300 besøg | Produktet sælger ikke i denne form |
| CPA | over 249 kr. tre dage i træk | Stop annoncen; grænsen er break-even, ikke en holdning |
| Retur og klage | over 8 % | Kvalitet eller levering — stop indkøb |
| Dokumentation | ikke komplet (GPSR, emballage) | Stop uanset efterspørgsel |

Fortolkningen er den del, folk blander sammen: **lav CTR og høj ATC** = kreativet fejler.
**Høj ATC og lav konvertering** = siden eller prisen fejler. **Lav ATC og høj konvertering** = for
lidt trafik, ikke et dårligt produkt.

## 11. Følsomhed

Ved 120 ordrer/md og CPA 150 giver 35 kr. dyrere indkøb på Kit 01:

| Indkøb Kit 01 | DB efter retur | Blandet DB | Resultat/md |
| --- | --- | --- | --- |
| 60 | 255,85 | 265,27 | +12.432,86 |
| 95 (basis) | 222,95 | 248,82 | +10.458,86 |
| 130 | 190,05 | 232,37 | +8.484,86 |

Og bundle-andelen, som i denne niche er et løft og ikke en livline:

| Bundle-andel | Blandet DB | Resultat/md |
| --- | --- | --- |
| 0 % | 235,50 | +8.860,11 |
| 25 % (basis) | 248,82 | +10.458,86 |
| 50 % | 262,15 | +12.057,61 |

Leverandørforhandling er stadig den enkeltaktivitet med højest afkast — 35 kr. på indkøbet er
1.974 kr./md ved 120 ordrer — men i denne niche er den **ikke** vigtigere end at få CPA ned.
Forskellen fra småhunde-casen er, at hero-varen allerede har margen nok.

## 12. Hvad modellen ikke ved

- **Ingen leverandørtilbud.** Indkøb 85-125 kr. er et estimat for et kit med børster, mikrofiber og
  en præsentabel æske. Fragt 45 kr. er et estimat for en 1-1,5 kg pakke til DK.
- **CPA 350/280/220/190 i §6 er en antagelse om læringskurven**, ikke en måling. Den er sat efter
  at et nyt pixel typisk starter 40-100 % over sit langsigtede leje.
- **Returraten er 6 %, ikke målt.** Kategorien har ingen pasform, men »jeg troede det var større«
  og »det virkede ikke på min tekstil« er reelle returårsager.
- **Ingen sæson i årsmodellen.** Bilpleje er helårligt, men CPM er ikke: Q4 koster 30-60 % mere.
- **Faste omkostninger er et minimum.** 1.400 kr./md dækker ikke revisor ved momsregistrering i
  udlandet, ikke lagerhotel og ikke en løn.
- **Ingen lagerpost.** Se §7: det lokale lager er likviditet, ikke enhedsøkonomi.
- **Kilder:** CPM-spænd og konverteringsrater er de samme som i
  [roi-model-dropshipping-2026-11-09.md](roi-model-dropshipping-2026-11-09.md); reglerne om
  GPSR, emballageansvar og prisoplysning er dansk/EU-ret og skal bekræftes af revisor eller
  Erhvervsstyrelsen før registrering, ikke af dette dokument.

## 13. Næste skridt — kun ejeren kan tage dem

| # | Handling | Blokerer |
| --- | --- | --- |
| E1 | CVR, telefon, adresse og bankaftale til butikken | Ingen faktura, ingen MobilePay, ingen lovlig handel |
| E2 | Leverandørvalg med GPSR-dokumentation og 2 prøver | Indkøbspris, kvalitet og leveringstid er ellers gæt |
| E3 | Registrering i producentregistret for emballage | Markedsføring må ikke starte |
| E4 | `kabine.dk` (eller alternativ) og Shopify-konto | Butikken kan ikke bygges |
| E5 | Beslutning: start 9/11 i Q4-priser eller 5/1 til lavere CPA | Spend-planen i §7 gælder kun det første |
| E6 | Annoncekonto med betalingsmetode og 10.000 kr. | Uge 3-4 kan ikke rulles |
