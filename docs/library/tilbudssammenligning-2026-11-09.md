# Tilbudssammenligning på ét kald (9/11 2026)

Værktøjet til at holde tre leverandørtilbud op mod hinanden på **reel indkøbspris** i stedet
for stykpris. Det er anden halvdel af [leverandørbriefen](leverandoerbrief-teleskopreol-2026-11-09.md):
brevet skaffer tallene, `tools/tilbuds_sammenligning.py` regner dem.

> **Eksempeltilbuddene i dette dokument er opdigtede.** Ingen leverandør er kontaktet. Tallene
> er sat ind for at vise, hvad værktøjet gør ved dem — ikke hvad markedet svarer.

## 1. Hvad der sker på ét kald

```
python3 tools/tilbuds_sammenligning.py tilbud.tsv --cpa 281
```

Arket er en TSV/CSV med ét tilbud pr. række, og udskriften er tre tabeller med tilbuddene som
kolonner, så de kan læses på tværs: **landed cost** Specificeret pr. led, **økonomien ved den
salgspris man overvejer**, og **dokumentationsportene**. Til sidst en rangering og en liste over,
hvad der stadig mangler svar på. `--md` giver markdown til et dokument, `--csv` giver et ark
til Excel med danske decimaler.

**Hvorfor et script og ikke et regneark.** `--csv` skriver selv arket — semikolonsepareret med
danske decimaler, så Excel åbner det uden import-trin. Forskellen er, hvor formlerne bor: i et
håndbygget ark skal syv formler kopieres pr. tilbud, og én forkert celle reference er usynlig i
tallene. Her ligger de ét sted, og `--selftest` fejler, hvis de flytter sig.

```
python3 tools/tilbuds_sammenligning.py --selftest     # 30 checks mod briefens facit
```

Selftesten er ikke pynt: den holder formlerne op mod de tal, der allerede står i
[ROI-modellen](roi-model-dropshipping-2026-11-09.md) §2 og briefen §4 — landed 210 ved 749 kr
skal give DB 281, loftet ved CPA 200 skal være 297, og den nødvendige pris skal give 749 tilbage.
Ændrer man en sats, fejler testen i stedet for at flytte alle tal stiltiende.

Sådan ser den midterste tabel ud — tilbuddene fra §4, kørt med `--cpa 281`:

```
2. Økonomi ved 749 kr
                        | A · FOB fra CN | B · DAP fra PL | C · EXW fra CN
DB efter retur          | 214 kr         | 253 kr         | 236 kr
Maks landed ved loftet  | 210 kr         | 210 kr         | 195 kr
Luft til loftet         | -72 kr         | -30 kr         | -49 kr
Pris for CPA 281        | 840 kr         | 787 kr         | 811 kr
Dom                     | nej            | nej            | kan ikke sælges
```

<!-- sidebrud -->

## 2. Arket

Kolonnerne er de samme felter, som briefen beder leverandøren svare i, så svaret kan klippes
direkte ind. Tomme felter er **»ved det ikke«** og tælles som manglende dokumentation — ikke som
et nej, og ikke som nul.

| Kolonne | Betydning | Konsekvens hvis den mangler |
| --- | --- | --- |
| `navn` | Tilbuddets navn | Rækken springes over |
| `incoterms` | EXW, FOB, CIF, DAP, DDP … | Fragt og told lægges oveni, som om det var EXW |
| `pris_100` … `pris_1000` | Stykpris pr. prisgruppe | Mindst én skal udfyldes |
| `fragt_pr_stk` | International fragt pr. stk. | Landed er undervurderet — dommen får forbehold |
| `told_pct` | Toldsats i procent | Samme forbehold; 0 % skal være målt, ikke antaget |
| `toldbehandling` | Speditørgebyr pr. stk. | Landed er undervurderet |
| `tpl` | 3PL-gebyr pr. stk., hvis varen skal lagerføres | Landed er undervurderet |
| `moq` | Mindste ordre | Kan ikke sammenlignes med den prisgruppe der regnes på |
| `karton_laengde` | cm, længste side | Fragttillægget over 120 cm bliver ikke regnet med |
| `karton_vaegt` | kg | Vises ikke i regnestykket; bruges til fragtklasse |
| `hs`, `oprindelse` | Toldkode og land | Told og antidumping kan ikke efterprøves |
| `proeve_pris` | Prøve inkl. fragt | Prøven er gaten før køb; loftet er 600 kr |
| `gpsr`, `belastningstest`, `eu_ansvarlig` | ja/nej | Tomt = ikke oplyst; **nej** på GPSR er stop |
| `landed_ifoelge` | Leverandørens eget landed-tal | Afvigelsen kan ikke findes |

## 3. Incoterms er den fælde, der koster mest

Et tilbud på »240 kr« kan betyde fire forskellige indkøbspriser. Værktøjet lægger leddene på efter
leveringsklausulen i stedet for at antage, at stykprisen er prisen:

| Incoterms | Fragt | Told og toldbehandling | Landed for et tilbud på 240 kr med 40 kr fragt, 18 kr behandling, 3 % told |
| --- | --- | --- | --- |
| EXW | Lægges oveni | Lægges oveni | 240 + 40 + 8,40 + 18 = **306,40** |
| FOB | Lægges oveni | Lægges oveni | Samme: **306,40** |
| CIF | Ligger i prisen | Lægges oveni | 240 + 7,20 + 18 = **265,20** |
| DAP | Ligger i prisen | Lægges oveni — importen er købers | 240 + 7,20 + 18 = **265,20** |
| DDP | Ligger i prisen | Ligger i prisen | **240,00** |

Forskellen mellem EXW og DDP er **66 kr på den samme vare** — 31 % af det CPA-loft, hele casen
hviler på. Lægges fragten oveni et DDP-tilbud, ser det dyrt ud; lægges den ikke oveni et
EXW-tilbud, ser det billigt ud, og fejlen opdages først på den første faktura. Derfor er
`incoterms` ikke en formalitet i arket.

## 4. Kørt på de tre eksempeltilbud

Tre tilbud, 300 stk., salgspris 749 kr. A er FOB fra Kina med 38 kr fragt og 12 kr toldbehandling,
B er DAP fra Polen med fragten i prisen, C er EXW fra Kina med 52 kr fragt, 18 kr behandling,
6 kr 3PL og en karton på 132 cm.

| | A · FOB fra CN | B · DAP fra PL | C · EXW fra CN |
| --- | --- | --- | --- |
| Stykpris ved 300 | 232 kr | 240 kr | 168 kr |
| International fragt | 38 kr | i prisen | 52 kr |
| Toldbehandling + 3PL | 12 kr | 0 kr | 24 kr |
| **Landed cost** | **282 kr** | **240 kr** | **244 kr** |
| DB efter retur ved 749 kr | 214 kr | 253 kr | 236 kr |
| Maks landed ved CPA 281 | 210 kr | 210 kr | 195 kr |
| Pris for CPA 281 | 840 kr | 787 kr | 811 kr |
| Dom | nej | nej | kan ikke sælges |

**Fund 1: den billigste stykpris er ikke den billigste vare.** C er 30 % billigere i stykpris end
B — 168 mod 240 kr — men efter fragt, toldbehandling, 3PL og 15 kr i karton-tillæg er forskellen
**4 kr**. Kartonnen på 132 cm spiser alene 15 kr, fordi PostNord tager særbehandling over 120 cm.

**Fund 2: dommen afhænger af, hvilket CPA man holder tilbuddet op imod.** Ved CPA 200 er loftet
297 kr, og både A og B består ved 749 kr. Ved modellens eget mål — DB 281, altså CPA 281 — består
**ingen** af dem: B mangler 30 kr og skal op på 787 kr (789 på -9-ladderen), A mangler 72 kr og
skal op på 840 kr (849).
Det er ikke en modsigelse; det er forskellen på »kan annoncen lige gå i nul« og »kan den bære den
CPA, planen regner med«. Værktøjet skriver begge tal, så valget bliver eksplicit.

**Fund 3: den billigste kan slet ikke sælges.** C svarer nej til GPSR-pakken og nej til
belastningstesten. Uanset pris er den ikke et tilbud — den er en samtale, der skal tages, før
der regnes videre.

## 5. Hvad værktøjet ikke ved

- **Toldsatsen er et input, ikke en beregning.** 0 % for HS 9403 20 80 er målt i TARIC 9/11, men
  antidumping for den konkrete underkode og oprindelse er ikke slået op. Skriver man 0 %, regner
  værktøjet med 0 %.
- **Fragt til kunden er fast 59 kr**, løftet til 74 kr når kartonnen er over 120 cm. Den faktiske
  fragtpris er ikke indhentet.
- **Retur er modellens 7 % à 85 kr.** Kommer der en volumen-retur à 149 kr som i niche 2-casen,
  skal satsen ændres, ikke tilbuddet.
- **Importmomsen indgår ikke som omkostning.** Den bindes ved indførsel og kommer tilbage i
  momsafregningen; ved landed 240 kr er det ca. 60 kr pr. stk. i likviditet, ikke i tab.
- **Eksempeltilbuddene er opdigtede, og deres `landed_ifoelge` stemmer per konstruktion.** Feltet
  findes for at fange en leverandør, der glemmer fragt eller toldbehandling — ikke for at rose dem,
  der har læst brevet.
