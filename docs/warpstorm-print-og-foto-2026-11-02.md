# Warpstorm — print- og fotoplan: de fire første SKU

Hvad der skal printes, i hvilken rækkefølge, og hvordan det bliver fotograferet, så butikken kan åbne med rigtige billeder i stedet for SVG-pladsholdere.

Skrevet 2/11 2026. Bygger på [catalogue.json](../deploy/warpstorm/catalogue.json) (priser, print-tider, materialekost) og [warpstorm-deploy-plan-2026-11-01.md](warpstorm-deploy-plan-2026-11-01.md) §5 P3 og §6.

---

## 0. Kort svar

| | |
|---|---|
| **Print, fire SKU** | **24 timer** rent, **~28 timer** med svind |
| **Kalendertid** | 3 nætter — helten alene er 9 timer pr. eksemplar |
| **Filament** | ~3,2 kg → køb 6 kg (4 spoler går med, 2 i reserve) |
| **Maling af foto-sættet** | 4–6 timer, og det er den post ingen havde budgetteret |
| **Fotodag** | 4–5 timer for alle fire SKU |
| **Efterbehandling** | 20 min. pr. SKU |

Det tal, der ændrede sig fra sidste gennemregning: **9 timer bliver til 18**, fordi helten skal printes to gange. Ét eksemplar males og bliver studio-model, ét forbliver rå og er det, kunden faktisk modtager. Uden begge kan billedserien ikke vise både «det her får du» og «det her kan det blive».

---

## 1. Hvilke fire — og hvorfor ikke de andre

| SKU | Print | Pris | Rolle i lanceringen |
|---|---|---|---|
| `WS-TER-01` Sector Ruins | 9,0 t | 79 | **Helten.** Ankre på forsiden, det eneste produkt der sælger hele boardet |
| `WS-TER-05` Rubble pack | 2,0 t | 19 | Billig indgang, bedste margin pr. print-time, købes sammen med alt |
| `WS-BAS-40` Bases | 1,5 t | 12 | Næsten ingen materialekost, sælger til enhver der ejer en squad |
| `WS-TER-03` Floor plate | 2,5 t | 17 | Gør at terrænet kan fotograferes på et board i stedet for at svæve |
| | **15,0 t** | | |

**Udsat:** `WS-TER-02` bastion, `WS-TER-01B` og `WS-BND-01` bundles, `WS-TER-04` hab block.

Bundles venter af en teknisk grund, ikke en markedsføringsmæssig: et bundle-foto skal bygges af komponenternes egne fotos, så det kan først laves når delene er fotograferet. `WS-TER-04` er stadig markeret `[proposed]` og er ikke printet endnu — den skal have en fysisk test før den får en pris.

---

## 2. Printkøen — rækkefølgen er ikke ligegyldig

Print de billige ting først. Det er ikke for at spare tid, det er for at **fejle billigt**: profilen skal køres ind på en 90-minutters plade, ikke på en 9-timers.

| Nat | Plade | Tid | Formål |
|---|---|---|---|
| 1a | 40 × 25 mm bases | 1,5 t | Kalibrering. Fejl koster 15 g |
| 1b | Rubble pack | 2,0 t | Stringing-test — ruiner har mange små kanter |
| 1c | Floor plate | 2,5 t | Test af stort fladt emne, krumning i hjørnerne |
| 2 | Sector Ruins (rå, til salg) | 9,0 t | — |
| 3 | Sector Ruins (til malings-sæt) | 9,0 t | — |

**Svind:** regn **15 %** oveni. Ruinevægge er 2 mm tykke og 120 mm høje, og det er præcis den geometri der vælter i lag 80. 15 % er ikke pessimisme, det er branchestandard for FDM-terræn. Måler du under 5 % efter de tre første plader, skriv det ned og justér — men planlæg ikke efter det.

**Kapacitet:** 28 timer er halvandet døgn uafbrudt. I praksis tre nætter, fordi nogen skal skifte plade mellem jobs. Printeren skal stå et sted hvor den må køre om natten.

---

## 3. Slicer-indstillinger (PLA+, 0,4 mm dyse)

| Parameter | Værdi | Hvorfor |
|---|---|---|
| Laghøjde | 0,2 mm | 0,12 ser bedre ud men fordobler tiden på en 9-timers plade |
| Perimetre | 3 (1,2 mm) | Ruinevægge bliver slidt i spil; 2 perimetre knækker |
| Top / bund | 4 / 3 | — |
| Infill | 15 % gyroid | Gyroid tåler slag bedre end grid ved samme vægt |
| Dyse / bord | 215 °C / 60 °C | PLA+ kører 5–10 °C varmere end alm. PLA |
| Hastighed | 60 mm/s ydervæg, 120 mm/s indre | Ydervæggen bestemmer overfladen |
| Brim | 5 mm på vægge | Hjørnerne på 120 mm-vægge løfter sig ellers |
| Support | Kun hvor nødvendigt, tree | Support på ruiner koster mere efterarbejde end det sparer |

**Orientering — den beslutning der betyder mest:** print væggene **liggende**. Det giver styrke på tværs af lagene og skjuler laglinjerne på den flade side, som er den kunden ser. Prisen er at overkanten får en synlig trappekant. Print dem stående og overkanten bliver pæn, men væggen knækker ved første gang en model vælter ind i den. **Liggende vinder** for et spilprodukt.

**0,6 mm dyse — en teste der er værd at tage:** på rubble-pakken, som er billig at fejle på. Holder kvaliteten, falder Sector Ruins fra 9 timer til omkring 5,5. Det er 40 % af al printtid på den dyreste vare, så testen betaler sig selv første nat. Skift ikke dyse midt i en plade.

---

## 4. Materiale — hvad der faktisk går med

`cost_eur` i kataloget er materiale + emballage. For at få gram: vej spolen før og efter, eller læs slicerens estimat — det er gratis og præcist.

| SKU | Antal | Plast |
|---|---|---|
| Sector Ruins | 2 | ~2,0 kg |
| Rubble pack | 1 | ~0,25 kg |
| Bases | 1 | ~0,15 kg |
| Floor plate | 1 | ~0,35 kg |
| **Sum** | | **~2,75 kg** |
| + 15 % svind | | **~3,2 kg** |

Køb **6 kg** (4 spoler går med, 2 i reserve). Reserven er ikke overforsigtighed: opdager du en ny farve eller en dårlig spole kl. 23, står printet stille til posten kommer.

**Tør filament.** PLA+ trækker vand, og våd PLA+ giver stringing — på ruiner betyder det sanding på hver eneste kant. Har du ikke en filamenttørrer, så opbevar spolerne i en pose med silicagel og print fra den.

---

## 5. Foto — den beslutning der flytter mest

**Rå eller malet?**

Begge, i denne rækkefølge:

1. **Billede 1 = det kunden får.** Rå PLA+, lige fra printeren, støvet af. Det er ikke det pæneste billede, men det er det ærlige, og det er det der forhindrer returneringer.
2. **Billede 2 = malet eksempel.** Det er det billede der sælger.

Grunden til at rækkefølgen ikke kan byttes om: rå lysegrå PLA+ læser som «3D-print» og trækker prisen ned mod materialeprisen. Malet terræn læser som terræn og bærer €79. Men viser det malede først, forventer kunden at modtage noget malet, og så er der en retur med tabt fragt begge veje.

Derfor **to eksemplarer af helten**. Det er de 9 ekstra timer, og det er den eneste post i hele planen der ikke kan skæres væk uden at sælge på et billede der ikke viser varen.

**Maling af foto-sættet: 4–6 timer** for Sector Ruins. Prime, basisfarve, drybrush, wash, og scatter på rubble-bunkerne. Det er arbejde der ikke står i nogen margin-tabel — det er en engangsudgift til studio-modellen, ikke pr. solgt enhed, men det skal med i tidsplanen eller bliver det aldrig gjort.

---

## 6. Optagelsesliste — fem billeder pr. SKU

| # | Billede | Hvad det skal bevise |
|---|---|---|
| 1 | **Hero**, 3/4-vinkel, kamera i ca. 15 cm højde | Produktet fylder 70–80 % af rammen. Bordhøjde, ikke fugleperspektiv — spillerne ser terræn oppefra bordkanten, ikke fra loftet |
| 2 | **Malet eksempel** | Hvad det kan blive |
| 3 | **Skala**, med 1–2 figurer på/ved siden af | At en 25 mm-base faktisk passer i slidsen |
| 4 | **Detalje**, makro på samlingen/slidsen | At der er et gennemtænkt system og ikke bare klumper |
| 5 | **Alle dele**, flad udlægning ovenfra | At «9 dele» er ni dele, ikke ni varianter af det samme |

Til bases og rubble-pakke falder 1 og 2 sammen — der er intet at male der ser markant anderledes ud. Tre billeder er nok.

**Figurerne på skalabilledet er dine egne og er ikke inkluderet.** Det skal stå i alt-teksten og i beskrivelsen. Det er både en handelsbetingelse og en ophavsretslig grænse — du viser en figur for at vise størrelse, du sælger den ikke, og du antyder ikke at den følger med.

---

## 7. Lys og baggrund — hvor det typisk går galt

**Baggrund: mellemgrå, ikke hvid og ikke sort.**

- Hvid baggrund på lysegråt terræn brænder kanterne ud, og produktet mister sin form.
- Sort baggrund æder netop de fordybninger og skygger der gør terræn læsbart.
- **Sort er især fristende her**, fordi sitets palette er grimdark. Lad være. Sort produkt på sort baggrund forsvinder, og det er den fejl der koster mest at opdage efter fotodagen.
- En rulle mellemgråt karton eller et stort ark 18 % grå er nok. Buet op bagfra, så der ikke er en vandret linje.

**Lys:** et vindue på en overskyet dag er den bedste gratis softbox der findes. Suppler med hvid skumplade på den modsatte side. **Undgå direkte sol** — laglinjerne bliver hvide spekulære striber, og så ser printet dårligere ud end det er.

**Telefon:**
- Brug 1× eller 2× objektivet, **aldrig 0,5×** — vidvinklen bøjer ruinerne skæve.
- Stativ eller klemme, 3 sekunders timer.
- Tryk fokus på den forreste samling, lås eksponeringen.
- **Manuel hvidbalance, samme værdi (ca. 5000 K) på alle fire SKU.** Ellers får de fire produkter fire forskellige gråtoner og butikken ser ud som om den er samlet fra fire leverandører.
- Slå HDR og nattilstand fra. RAW hvis telefonen kan.

---

## 8. Filnavne, formater og rækkefølge

```
WS-TER-01_1-raw.jpg
WS-TER-01_2-painted.jpg
WS-TER-01_3-scale.jpg
WS-TER-01_4-detail.jpg
WS-TER-01_5-parts.jpg
```

- **SKU først**, så filen kan kobles til produktet uden at nogen skal huske noget.
- Eksport: 2000 px på den lange led, JPEG kvalitet 85, sRGB. Imagify (allerede i manifestet) laver WebP selv — upload fuld størrelse og lad den arbejde.
- **Hero beskæres 1:1.** Woo-temaet cropper kvadrater til thumbnails, og alt andet mister kompositionen. Galleri-resten i 4:3.
- Alt-tekst på dansk og beskrivende: *«Sektorruiner i grå PLA+, ni moduldele med 25 mm-slidser»* — ikke «produktbillede 1».
- Rækkefølgen i galleriet er rækkefølgen i tabellen ovenfor. Rå først.

Når filerne ligger i mediebiblioteket, sættes deres attachment-ID'er i `images`-feltet på produktet i `catalogue.json`, og `build_catalogue.py` sender dem med i produkt-oprettelsen. Feltet er tilføjet og står tomt nu — det er den ene ting der stadig kræver et menneske med en telefon.

---

## 9. Tidsplan

| Hvornår | Hvad | Timer |
|---|---|---|
| Nat 1 | Bases, rubble, floor plate — profil køres ind | 6 t print |
| Nat 1–3, løbende | Maling af foto-sættet mens der printes | 4–6 t arbejde |
| Nat 2–3 | Sector Ruins ×2 | 18 t print |
| Fotodag | Alle fire SKU | 4–5 t |
| Aften | Beskæring, alt-tekst, upload | 1,5 t |

**Tre døgn kalendertid, ca. to arbejdsdage med hænder i det.** Printet kører om natten; dagene går med maling og foto.

---

## 10. Hvad der stadig ikke er løst

- **Media-upload over MCP er ikke verificeret.** `tools/list` er ikke kørt endnu, fordi applikationsadgangskoden mangler. REST `/wp/v2/media` virker med app-password, men skal have binæren sendt med — det kan automatiseres, men rækkefølgen afhænger af hvad MCP'en faktisk udstiller. Ikke gættet her.
- **Maling er ikke i marginerne.** Det er en engangsudgift på foto-sættet, ikke pr. enhed. Den hører i tidsplanen, ikke i margin-tabellen.
- **0,4 eller 0,6 mm dyse** er ikke besluttet. Testen ligger på rubble-pakken første nat.
- **`WS-TER-04` hab block er ikke printet.** Den har en pris i kataloget men ingen fysisk test, og den bliver ikke lanceret på et gæt.

---

## 11. Tjekliste der kan køres uden at beslutte noget

- [ ] 6 kg PLA+ bestilt, samme farve og samme batch til hele lanceringen
- [ ] Filament tørt, spoler vejet
- [ ] Bases printet, profil justeret
- [ ] Rubble printet, stringing vurderet, 0,6 mm-test taget eller afvist
- [ ] Floor plate printet, krumning målt med linealen
- [ ] Sector Ruins ×2 printet, svind noteret
- [ ] Foto-sæt primet, malet, tørret
- [ ] Grå baggrund, vindue, telefon på stativ, hvidbalance låst
- [ ] Fem billeder pr. SKU, 2000 px, sRGB
- [ ] Alt-tekst skrevet på dansk
- [ ] Uploadet, ID'er i `catalogue.json`, `build_catalogue.py` kørt
- [ ] `deploy.py run --offline` stadig 0 failed
