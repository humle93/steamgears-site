# Beslutningsnotat: teleskopreolen — hvad der skal forhandles (9/11 2026)

De tre tilbud kørt gennem `tools/tilbuds_sammenligning.py`, og hvad hvert af dem mangler for at kunne
købes. Notatet er tredje led i kæden: [leverandørbriefen](leverandoerbrief-teleskopreol-2026-11-09.md)
skaffer tallene, [tilbudssammenligningen](tilbudssammenligning-2026-11-09.md) regner dem, og dette notat
beslutter, hvad der skal siges til hvem.

> **Ingen leverandør er kontaktet, og ingen pris er et tilbud.** De tre rækker er scenarierne fra
> `tools/fixtures/tilbud-eksempel.tsv`: en FOB fra Kina, en DAP fra Polen og en EXW fra Kina, valgt
> fordi de tre Incoterms opfører sig forskelligt. Notatet viser derfor **metoden og forhandlingsrummet**,
> ikke markedets svar. Tallene skal genberegnes på de rigtige tilbud, når de kommer ind — det er én
> kommando, og resten af notatet holder.

## 1. Grundlaget: tre tilbud, to CPA-loft

300 stk., salgspris 749 kr, modellens input (7 % retur à 85 kr, 59 kr fragt til kunden, 12 kr emballage,
betaling 1,4 % + 1 kr, 25 % moms).

| | A · FOB fra CN | B · DAP fra PL | C · EXW fra CN |
| --- | --- | --- | --- |
| Stykpris ved 300 | 232 kr | 240 kr | 168 kr |
| International fragt | 38 kr | i prisen | 52 kr |
| Toldbehandling + 3PL | 12 kr | 0 kr | 24 kr |
| **Landed cost** | **282 kr** | **240 kr** | **244 kr** |
| DB efter retur ved 749 kr | 214 kr | 253 kr | 236 kr |
| Loft ved CPA 200 | 297 kr | 297 kr | 282 kr |
| Luft til CPA 200 | +15 kr | +57 kr | +38 kr |
| Loft ved CPA 281 (modellens mål) | 210 kr | 210 kr | 195 kr |
| Luft til CPA 281 | −72 kr | −30 kr | −49 kr |
| Pris der kræves ved CPA 281 | 840 kr | 787 kr | 811 kr |
| Dom | nej | nej | kan ikke sælges |

To ting står fast, og de er ikke i konflikt. **Ved CPA 281 kan ingen af de tre købes til 749 kr** — det
er modellens eget mål, hvor annoncen lige nøjagtig går i nul. **Ved CPA 200 kan både A og B købes**, A
med 15 kr og B med 57 kr til overs. Beslutningen handler derfor ikke om, hvem der er billigst, men om
hvor meget luft der skal forhandles hjem, før en pris på 749 kr kan forsvares.

**B er 42 kr billigere i landed end A, selv om B er 8 kr dyrere pr. stk.** Hele forskellen er fragt og
toldbehandling: 50 kr på A, 0 kr på B, fordi DAP fra Polen har fragten i prisen og ingen import. Det er
den ene sætning, der afgør rækkefølgen i §3.

<!-- sidebrud -->

## 2. Forhandlingsrummet: hvad prisen alene kan bære

Prisen og landed cost trækker i samme regnestykke. Hæves prisen 20 kr, løfter det CPA-loftet med 15,7 kr
(749 → 769 giver loft 210,3 → 226,0); sænkes landed med 10 kr, løfter det loftet med 10 kr. Tabellen
viser luften ved hver pris: **positivt tal er råd til det faktiske landed, negativt er mangel.**

| Salgspris | Loft ved CPA 281 | B (landed 240) | A (landed 282) | C (landed 244) |
| --- | --- | --- | --- | --- |
| 749 kr | 210 kr | −30 kr | −72 kr | −49 kr |
| 769 kr | 226 kr | −14 kr | −56 kr | −33 kr |
| 789 kr | 242 kr | **+2 kr** | −40 kr | −17 kr |
| 799 kr | 250 kr | +10 kr | −32 kr | −9 kr |
| 819 kr | 266 kr | +26 kr | −17 kr | **+7 kr** |
| 849 kr | 289 kr | +49 kr | **+7 kr** | +30 kr |

C-kolonnen regner med den 132 cm karton, altså 74 kr i kundefragt. Kommer kartonnen under 120 cm, er
loftet 15 kr højere, og C's tal rykker én kolonne til højre.

**Den vigtigste linje i tabellen er, hvor lidt der er til overs.** Ethvert enkeltgreb lander inden for få
kroner af nul: B reddes af prisen alene ved 789 kr (+2 kr), A først ved 849 kr (+7 kr), og 30 kr i rabat
på B giver præcis 0 kr i luft ved 749 kr. Et regnestykke, der går i nul, tåler ikke en returrate på 12 %
eller en fragtstigning. Derfor skal forhandlingen hente **både** pris og landed — og derfor er målet
luft, ikke nul:

| Tilbud | Enkeltgreb (luft) | Anbefalet kombination (luft) |
| --- | --- | --- |
| B | 30 kr ned → 749 kr (+0) · pris 789 kr (+2) | 15 kr ned (240 → 225) og prisen til 789 kr (**+17**) |
| A | 72 kr ned → 749 kr (+0) · pris 849 kr (+7) | 30 kr ned (282 → 252) og prisen til 819 kr (+14) |
| C | 49 kr ned → 749 kr (+0) · pris 819 kr (+6) | dokumentation, karton ≤ 115 cm, MOQ 300 og pris 799 kr (+6) |

Den blandede vej er den, en leverandør kan sige ja til: 15 kr på 300 stk. er 4.500 kr for dem og 6,3 % af
stykprisen, mens 30 kr er 12,4 % og bliver afvist. Til gengæld kræver kombinationen, at salgsprisen
hæves til 789 kr — 40 kr over prisankeret, hvilket er en beslutning om positionering, ikke om indkøb.

## 3. De tre tilbud, hver for sig

### 3.1 B · DAP fra Polen — den eneste, der kan købes nu

Bedst på næsten alt, der ikke er stykpris: lavest landed (240 kr), lavest MOQ (200), ingen import,
21 dages levering, prøve til 480 kr, og PL ligger i EU, så der skal ikke findes en EU-ansvarlig person.
Den er også den eneste, der har luft ved CPA 200 (+57 kr).

**Det, der skal forhandles:**

1. **Karton 118 cm → højst 115 cm.** Tre centimeter. Det er ikke en prisforhandling, det er et
   spec-krav: over 120 cm går pakken til særbehandling med 15 kr tillæg pr. ordre, og ved 118 cm er der
   kun 2 cm til grænsen. Skriftlig bekræftelse på målene, ikke »cirka«.
2. **15 kr ned i landed** ved 300 stk. (240 → 225), mod at prisen sættes til 789 kr. Det giver 17 kr i
   luft. Skal 749 kr holdes, kræves 30 kr — og så er der 0 kr tilbage, hvilket er et nej i praksis.
3. **Prisen ved 300 stk.** Tilbuddet har 255/240/232/225 — springet fra 300 til 500 er 8 kr. Bed om
   300-prisen bekræftet skriftligt, og om 500-prisen gælder ved genbestilling uden ny MOQ.
4. **GPSR-pakken, og hvem der er fabrikant.** Sælges reolen under butikkens navn, er butikken fabrikant
   efter GPSR med 10 års dokumentationspligt — ikke distributør. Det skal afklares, før annoncen skrives,
   fordi det flytter arbejde fra leverandøren til butikken.
5. **Reservedele:** fod, hylde og samlestykke løst, med pris. Uden dem koster en reklamation en hel reol.

### 3.2 A · FOB fra Kina — reserve, ikke kandidat

72 kr væk ved 749 kr. Det er 31 % af stykprisen, og et sådant afslag findes ikke. Selv ved 849 kr er der
kun 7 kr til overs, og så er prisen 100 kr over ankeret for et tilbud, der stadig har 38 kr fragt, 12 kr
toldbehandling og en EU-ansvarlig, der ikke er oplyst.

**Det, der skal forhandles, hvis B falder:** 30 kr ned i landed (232 → 202, altså fragt og
toldbehandling ned, ikke nødvendigvis stykprisen) **og** prisen til 819 kr. Kommer der kun 10 kr, skal
prisen til 849 kr, og så er det en dyr redning. Kan de ikke det, er svaret nej — og det er et nej til
tilbuddet, ikke til Kina.

### 3.3 C · EXW fra Kina — ikke en prissamtale

Den billigste stykpris (168 kr mod 240) og den næstdyreste landed (244 kr). Forskellen spises af 52 kr
fragt, 18 kr toldbehandling og 6 kr 3PL. Oveni en karton på 132 cm, der koster 15 kr i kundefragt — DB
falder fra 250 kr til 236 kr alene på den karton.

Men den kan ikke sælges: nej til GPSR-pakken og nej til belastningstesten. Det er ikke et
forhandlingspunkt, det er en stopklods. Rækkefølgen er derfor: **dokumentation først, pris bagefter.**
Kommer der en GPSR-pakke og en testprotokol, og kommer kartonnen på 115 cm, bliver regnestykket: landed
244 kr og loft 210 kr ved 749 kr — der mangler stadig 34 kr. Ved 799 kr er loftet 250 kr, og så er der
6 kr til overs. MOQ på 500 stk. skal samtidig ned på 300, ellers binder den 84.000 kr.

## 4. Kontantbinding — hvad det koster at vælge

MOQ er ikke en detalje i denne case, fordi hele ordren betales, før varen er solgt.

| Tilbud | MOQ | Varepris ved MOQ | Importmoms (likviditet) | Kontant ved ordre |
| --- | --- | --- | --- | --- |
| A · FOB fra CN | 300 | 69.600 kr | 21.150 kr | **90.750 kr** |
| B · DAP fra PL | 200 | 48.000 kr | 0 kr | **48.000 kr** |
| C · EXW fra CN | 500 | 84.000 kr | 30.500 kr | **114.500 kr** |

Importmomsen er ikke en omkostning — den kommer tilbage i momsafregningen — men den skal ligge klar, når
varen fortoldes. B kræver 48.000 kr og ingen moms ved grænsen; C kræver 114.500 kr, mere end dobbelt så
meget, for en vare der ikke må sælges endnu. Det er den anden grund til, at B kommer først.

## 5. Hvad der ikke skal forhandles

- **Prisen under 749 kr.** Ved 699 kr falder loftet til 171 kr ved CPA 281 og 258 kr ved CPA 200. B's
  240 kr holder stadig ved CPA 200 med 18 kr til overs, men casen bliver skrøbelig, og prisankeret i
  [niche 2](niche-2-lejebolig-opbevaring-2026-11-09.md) er 749 kr mod IKEA's 585. At gå under 699 kr for
  at matche IKEA er at sælge med underskud.
- **Kartonnen.** 115 cm er ikke en præference. 132 cm koster 15 kr pr. ordre, og det er 6 % af
  dækningsbidraget.
- **Dokumentationen.** GPSR-pakke og belastningstest er adgangsbilletten, ikke et tilvalg.
- **Belastningsangivelsen.** 5 kg pr. hylde skal være testet på prøven, ikke lovet i en mail.

**Følsomheden, der skal stå i beslutningen:** modellen regner 7 % retur à 85 kr. En volumen-retur à 149 kr
ved 12 % — den advarsel niche 2 selv rejser — koster B 26 kr i DB (253 → 227 kr) og løfter den nødvendige
pris fra 787 til 826 kr. Forhandles der 15 kr hjem i landed, og returen samtidig bliver dårlig, er
fordelen halveret. Derfor skal prøven vurderes på holdbarhed og samling, ikke kun på pris.

## 6. Rækkefølge og beslutningsregel

1. **Prøve fra B** (480 kr, under loftet på 600). Mål kartonnen, vej den, saml den uden værktøj, og læg
   5 kg på hver hylde i et døgn. Det er den samme test, kunden vil lave.
2. **Forhandl B** på de fem punkter i §3.1: 15 kr i landed og prisen til 789 kr er den kombination, der
   giver luft nok til at tåle en dårlig returrate.
3. **Falder B**, gå til A med §3.2's to krav. Kommer der ikke mindst 30 kr, er svaret nej.
4. **C** følges kun op på dokumentation. Kommer den ikke, lukkes tilbuddet uden videre prissamtale.
5. **Første annonce** skrives først, når GPSR-pakken ligger i mappen, og når det er besluttet, om varen
   sælges under eget navn.

**Beslutningsreglen, i én sætning:** køb B, hvis (a) kartonnen er ≤ 115 cm skriftligt bekræftet,
(b) landed er ≤ 225 kr ved 300 stk. med en pris på 789 kr, (c) GPSR-pakken foreligger, og (d) prøven
holder 5 kg pr. hylde. Ellers er svaret nej til alle tre — og det er et bedre svar end en ordre, der ikke
kan bære annoncen.

## 7. Opfølgningen, der kan sendes

Kort, fordi de tre spørgsmål er de eneste, der flytter noget. Kladde — ikke afsendt.

```
Subject: Re: quote - floor-to-ceiling tension shelf

Thank you. Before we order a sample, three points:

1. Carton length. Our carrier charges a surcharge above 120 cm, so the
   carton must be 115 cm or less. Your quote says 118 cm. Can the poles
   be split so the carton stays at or below 115 cm, and can you confirm
   the exact dimensions in writing?

2. Price at 300 pcs. We compare on landed cost, not unit price. At 300
   pcs we need a landed cost of 225 DKK or better delivered to Denmark.
   Can you meet that, and what is your best price at 500 pcs?

3. GPSR. Please confirm whether the technical documentation and the load
   test report are issued under your brand or ours - it decides who is
   the manufacturer under EU rules - and send the package with the sample.

We will order the sample once the carton dimensions are confirmed.
```

Fragtgrænsen på 120 cm er PostNords, så den gælder kun, hvis forsendelsen sker med PostNord; med GLS er
grænsen 200 cm, og så er kartonkravet et spørgsmål om kundefragt, ikke om pakkevalg.

## 8. Hvad notatet ikke ved

- **Ingen priser er indhentet.** De tre tilbud er scenarier, og deres `landed_ifoelge` stemmer per
  konstruktion. Forhandlingsrummet i §2 er aritmetik; om en leverandør kan gå 15 kr ned, er et spørgsmål
  til leverandøren.
- **Returraten er modellens 7 % à 85 kr.** Erfaringen fra niche 2 siger, at en reol er dyrere at få retur
  end en sele, og følsomheden i §5 viser, hvad det betyder.
- **CPA 200 og 281 er ikke målt.** De er modellens break-even og driftsmål. Den faktiske CPA i uge 1–2 er
  250–400 kr ifølge ROI-modellen §4, altså over begge lofter — hvilket netop er argumentet for at
  forhandle, før der annonceres.
- **Toldsatsen er 0 % som input.** HS 9403 20 80 er målt i TARIC, men antidumping for den konkrete
  underkode og oprindelse er ikke slået op; en sats over 0 % flytter A og C, ikke B.
- **Ingen leverandør er kontaktet, og mailen i §7 er ikke sendt.** Det kræver ejerens godkendelse.
