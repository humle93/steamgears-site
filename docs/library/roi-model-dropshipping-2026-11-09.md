# ROI-modellen for en dropshipping-niche (9/11 2026)

Genbrugelig beregningsmodel, kørt på casen »senior- og småhunde-tilbehør«. Alle tal er
**estimater**, aritmetikken er script-verificeret. Modellen svarer på tre spørgsmål: hvad
tjener én ordre, hvad koster en test, og hvad giver et år — og hvilke tre tal der vælter det hele.

## 1. Hvorfor modellen findes

Niche-research ender typisk i en påstand om, at »markedet vokser«. Det er ikke en beslutning.
Det, der afgør om en niche kan bære betalt trafik i Danmark, er fire tal: **dækningsbidrag pr.
ordre, annoncepris pr. ordre (CPA), returrate og faste omkostninger**. Modellen regner dem sammen,
så en niche kan forkastes eller godkendes, før der er købt en eneste vare.

Modellen er skrevet til **draft-only dropshipping**: intet lager, ingen abonnementer, ingen
markedsplads. Den er ikke egnet til nicher med lagerbinding eller lang leveringstid uden at
arbejdskapital lægges ind som en post.

## 2. De fire formler

```
netto        = pris inkl. moms / 1,25
betaling     = netto * 1,4 % + 1 kr
DB           = netto - indkøb - fragt - emballage - betaling
DB efter ret = DB * (1 - returrate) - returrate * returomkostning
break-even CPA = DB efter retur
resultat/ordre = DB efter retur - CPA
break-even ordrer/md = faste omkostninger/md / (DB efter retur - CPA)
ROI          = resultat / (annoncer + faste omkostninger)
MER          = omsætning inkl. moms / annonceforbrug
```

Tre detaljer, som er lette at glemme, og som modellen tvinger ind:

- **Momsen skal ud af omsætningen, men ikke ud af annoncerne.** Annonceforbrug er en
  omkostning uden moms for en momsregistreret virksomhed, så break-even-CPA sammenlignes med
  netto-dækningsbidrag, ikke med bruttoavancen.
- **Retur er ikke kun fragt.** En retur koster fragt begge veje, håndtering og i praksis en
  vis andel ukurant vare. Modellen bruger **85 kr pr. retur** som samlet enhedsomkostning.
- **Emballage er både en udgift og en forpligtelse.** Siden 1/1 2025 er den, der først gør
  pakkede varer tilgængelige i Danmark, omfattet af emballageproducentansvaret og skal
  registreres i producentregistret senest 14 dage før markedsføring. Gebyr og indberetning
  hører i de faste omkostninger, ikke i den variable.

## 3. Regneeksemplet: fire produkter

Forudsætninger: 25 % moms, **7 % returrate**, 85 kr pr. retur, betaling 1,4 % + 1 kr,
fri fragt over 399 kr.

| Produkt | Pris | Netto | DB | DB efter retur | DB % | Break-even CPA |
| --- | --- | --- | --- | --- | --- | --- |
| Bæresele | 449 | 359,20 | 166,17 | **148,59** | 41,4 % | 149 |
| Snustæppe | 249 | 199,20 | 95,41 | **82,78** | 41,6 % | 83 |
| Sele XS–S | 349 | 279,20 | 136,29 | **120,80** | 43,3 % | 121 |
| Bundle (tæppe + sele) | 598 | 478,40 | 271,70 | **246,73** | 51,6 % | 247 |

Og resultatet pr. ordre ved fem annoncepriser:

| Produkt | CPA 70 | CPA 90 | CPA 110 | CPA 130 | CPA 150 |
| --- | --- | --- | --- | --- | --- |
| Bæresele | +78,6 | +58,6 | +38,6 | +18,6 | −1,4 |
| Snustæppe | +12,8 | **−7,2** | −27,2 | −47,2 | −67,2 |
| Sele XS–S | +50,8 | +30,8 | +10,8 | −9,2 | −29,2 |
| Bundle | +176,7 | +156,7 | +136,7 | +116,7 | +96,7 |

**Fund 1: den billige vare kan ikke bære betalt trafik.** Snustæppet til 249 kr. taber penge
allerede ved CPA 90. Det skal sælges som mer-salg eller i bundle — aldrig som selvstændig
annonce. Det er den samme fælde, der får folk til at konkludere »nichen virker ikke«, når det i
virkeligheden er prisklassen, der ikke kan betale for et klik.

**Fund 2: hele casen hviler på, at kunderne køber to ting.** Bundle'en er den eneste, der stadig
tjener penge ved CPA 150, hvor alle tre enkeltprodukter er døde. Falder bundle-andelen under ca.
40 %, ryger CPA-loftet fra 247 til 121 — og så er casen væk. Derfor skal bundlen være
standardvalget på produktsiden, ikke et tilvalg.

## 4. Testens ROI (3.000 kr)

| CPA | Ordrer | Dækningsbidrag | Resultat | ROAS |
| --- | --- | --- | --- | --- |
| 80 | 37,5 | 9.252 | +6.252 | 7,47 |
| 100 | 30,0 | 7.402 | +4.402 | 5,98 |
| 120 | 25,0 | 6.168 | +3.168 | 4,98 |
| 150 | 20,0 | 4.935 | +1.935 | 3,99 |
| 200 | 15,0 | 3.701 | +701 | 2,99 |

Tabellen ser for god ud, og det er pointen: den forudsætter, at CPA 80–150 rammes fra dag ét på
et produkt, ingen kender, i en butik uden anmeldelser. **Realistisk CPA i uge 1–2 er 250–400 kr.**,
og så er testen et underskud på 1.500–2.500 kr.

Derfor er reglen: **en test er en udgift, ikke en investering.** Den budgetteres som
markedsundersøgelse, og den måles på, om den giver et klart svar — ikke på ROAS. Forventet
kontantresultat for 3.000 kr: **−100 % til +10 %**.

## 5. Årsmodellen

Med bundle-DB 246,73 og CPA 110 er der **137 kr tilbage pr. ordre efter annoncer**. Faste
omkostninger på 1.667 kr/md (20.000 kr/år: webshop, forsikring, regnskab, DPA/PRO-gebyr, domæne)
kræver **12,2 ordrer om måneden** — cirka én hver anden dag — før butikken er selvkørende.

| Ordrer/md | Omsætning | Annoncer | Resultat/md | Årlig ROI | MER |
| --- | --- | --- | --- | --- | --- |
| 40 | 23.920 | 4.400 | +3.803 | 62,7 % | 5,44 |
| 80 | 47.840 | 8.800 | +9.272 | 88,6 % | 5,44 |
| 120 | 71.760 | 13.200 | +14.741 | 99,2 % | 5,44 |
| 200 | 119.600 | 22.000 | +25.680 | 108,5 % | 5,44 |
| 300 | 179.400 | 33.000 | +39.353 | 113,5 % | 5,44 |

ROI stiger med volumen, fordi de faste omkostninger fordeles — ikke fordi økonomien pr. ordre
bliver bedre. Ved 40 ordrer/md er butikken teknisk set rentabel og praktisk set skrøbelig.

**Følsomhed** ved 120 ordrer/md og CPA 110:

| Indkøbspris | DB efter retur | Resultat/md | Årlig ROI |
| --- | --- | --- | --- |
| 105 kr | 279 | +18.647 | 125,4 % |
| 140 kr (basis) | 247 | +14.741 | 99,2 % |
| 175 kr | 214 | +10.835 | 72,9 % |

35 kr dyrere indkøb koster **26 procentpoint ROI**. Leverandørforhandling er derfor den enkeltaktivitet
med højest afkast i hele modellen — højere end annonceoptimering.

## 6. Stopkriterier, aftalt før start

| Signal | Grænse | Beslutning |
| --- | --- | --- |
| CTR | under 1 % efter 1.500 visninger | Kreativet er dødt, ikke produktet |
| CPC | over 9 kr | Forkert målgruppe eller for bredt keyword |
| Add-to-cart | under 5 % af besøg | Pris, tillid eller side — ikke annoncen |
| Konvertering | under 1,5 % ved 300 besøg | Produktet sælger ikke i denne form |
| CPA | over break-even tre dage i træk | Stop annoncen, ikke nødvendigvis produktet |
| Retur/klage | over 8 % | Kvalitet eller pasform — stop indkøb |
| Dokumentation | ikke komplet | Stop uanset efterspørgsel |

Fortolkningen er den del, folk blander sammen: **lav CTR og høj ATC** = kreativet fejler.
**Høj ATC og lav konvertering** = siden eller prisen fejler. **Lav ATC og høj konvertering** =
for lidt trafik, ikke et dårligt produkt.

## 7. Genbrug til næste niche

Modellen kræver kun syv input pr. produkt:

1. salgspris inkl. moms
2. indkøbspris (leverandørtilbud, ikke gæt)
3. fragt til kunde
4. emballage
5. returrate (brug 7 % indtil du har egne tal)
6. returomkostning (85 kr som standard)
7. faste omkostninger pr. måned

Kør dem gennem formlerne i §2, og læs tre tal: **break-even CPA**, **CPA-loftet hvis
bundle-andelen svigter**, og **antal ordrer/md før de faste omkostninger er dækket**. Ligger
break-even under ca. 120 kr for et produkt, der skal sælges alene, er svaret nej.

## 8. Hvad modellen ikke ved

- **Indkøbspriser, fragtpriser, CPA og returrate er estimater.** Der er ingen leverandørtilbud
  og ingen annoncedata bag tallene.
- **Toldsatserne mangler.** HS 4201 (sele, halsbånd) og HS 9404 (senge, puder) skal slås op i
  TARIC før indkøbsprisen er reel; de er bevidst ikke gættet her.
- **Ingen lager- eller arbejdskapitalpost.** Modellen antager, at varen sendes direkte fra
  leverandør; købes der ind til lager, skal bindingen ind som en omkostning.
- **Ingen sæson.** Kæledyrsudstyr er helårs, men fx altan-have er det ikke; en sæsonniche skal
  have en lavere aktiv periode med i de faste omkostninger.
- **Kilder:** GPSR-vejledningen (C/2025/6233) og Dansk Producentansvar.
