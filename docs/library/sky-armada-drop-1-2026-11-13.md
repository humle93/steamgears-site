# Sky Armada — første drop, Gelato-vejen og billedkoblingen (13/11 2026)

**Kort:** Gelato-pluginnet er installeret og aktiveret, men **ikke forbundet**. Repoet indeholder nu
katalogarket, et dry-run-værktøj der kobler råbilleder til SG-SKU'er, en dokumenteret drop-plan på fem
designs og en GPSR-kladde. Intet er uploadet, publiceret eller sat til salg.

Dette dokument erstatter ikke [batch 4-arbejdsordren](batch-4-arbejdsordre-2026-11-08.md), som nu også
peger på Gelato og dækker tøj og de øvrige familier; det beskriver plakatserien, hvor Gelato er den
valgte printvej.

---

## 1. Hvad der er målt — og hvad der ikke er

| Måling | Resultat | Kilde |
| --- | --- | --- |
| Filer i Drive-mappen "Råbilleder - Steamgears Posters (Genereret Batch)" | **12**, ikke 13 | Drive-liste 13/11, begge tilsluttede konti giver samme 12 |
| Unikke SG-SKU'er med billede | **11** (SG-AIR-002 har to filer) | [data/posters-50-import.csv](../../data/posters-50-import.csv) + filnavne |
| Bredde × højde | **848–896 × 1200–1264 px** | Pillow 13/11 på alle 12 filer |
| Billedformat | 848×1264 ≈ **2:3**, 864×1216 ≈ **5:7**, 896×1200 = **3:4** | udregnet, tolerance 0,01 |
| Trykstørrelse ved 300 dpi med den nuværende bredde | **7,2–7,6 cm** | 848/300·2,54 og 896/300·2,54 |
| Krav for 50×70 cm ved 300 dpi | **5906 × 8268 px** | masterkataloget, §1 |
| Nødvendig opskalering | **5,6–6,8 ×** | 5906 / 848 og 5906 / 896 |
| Antal designs i kataloget | **50** (5:7 og 2:3, 30×42 til 70×100 cm) | importarket, 50 rækker, 50 unikke SKU |
| Gelato-forbindelse | **ikke oprettet** | ejerens måling 13/11: plugin aktiveret, Connect ikke gennemført |
| Gelato-kostpriser, variantmapping, mockups, fragtrater | **ikke målt** | følger af at forbindelsen mangler |

**Konklusionen på opløsningen:** de 12 filer er råmateriale. Ved 300 dpi dækker de 7,2–7,6 cm i bredden,
altså omtrent et visitkort, hvor plakaten kræver 50–70 cm. Masterkataloget siger selv, at råbilledet skal
opskaleres 4× **før** efterbearbejdning; målt her er behovet 5,6–6,8×, fordi filerne er mindre end
katalogets antagelse. Ingen af dem kan gå i tryk som de er, og ingen tryk-PDF findes i repoet.

**Billedforholdet er ikke ens.** 848×1264 og 864×1216 rammer 2:3 og 5:7 inden for en halv procent, men
896×1200 er 3:4 og skal beskæres bevidst til 5:7 (SG-AIR-001, SG-AIR-002-celestial, SG-ENG-011).
Beskæringen skal tages i den færdige efterbearbejdning, fordi katalogets motivregel kræver en rolig,
mørk nederste femtedel til typografien.

## 2. Gelato — hvad der er gjort, og hvad der ikke er

**Gjort (ejerens måling 13/11):** det officielle *Gelato Integration for WooCommerce* (v1.3.3, udgivet af
Gelato) er installeret og aktiveret, og forsiden og `/shop/` svarer stadig 200 efter installationen.

**Ikke gjort:** forbindelsen. Den kræver ejerens Gelato-login:

1. Åbn `https://steamgears.eu/wp-admin/admin.php?page=gelato-main-menu`.
2. Klik **Connect** og log ind med Gelato-kontoen.
3. Vælg butik/organisation, så WooCommerce og Gelato peger på samme katalog.

Først derefter findes der en målt sandhed om varianter, kostpriser, produktionstider og fragtrater. Dette
dokument påstår derfor **ingen** Gelato-egenskaber ud over det målte: at pluginnet er installeret og
aktiveret, og at sitet stadig svarer 200.

**Dublet-fælden:** Gelato opretter som udgangspunkt sine egne WooCommerce-produkter. Ved forbindelsen skal
de kobles til de eksisterende plakatkladder med SKU `SG-AIR-001` osv. — ellers står butikken med to
produkter pr. motiv. Ryddes op bagefter, er det kun de produkter Gelato selv har oprettet, der skal slettes.

## 3. Første drop: præcis fem designs

Drop 1 er fem Sky Armada-motiver, alle med et råbillede i mappen. Resten af kataloget bliver kladder.

| SKU | Titel | Fil | Rolle | px | Format | Næste håndværk |
| --- | --- | --- | --- | --- | --- | --- |
| SG-AIR-001 | Kaptajn Aurora ved Messingroret | `sg-air-001-aurora-helm-r1a.jpg` | hoved | 896×1200 | 3:4 → 5:7 | beskær, op 6,6×, efterbearbejd |
| SG-AIR-002 | Stjerne-Navigatøren i Glaskuplen | `sg-air-002-navigator-dome-r2b.jpg` | **hoved** | 864×1216 | 5:7 | op 6,8×, efterbearbejd |
| SG-AIR-002 | — samme produkt | `sg-air-002-celestial-dome-r2a.jpg` | **sekundær/galleri** | 896×1200 | 3:4 | beskær hvis den skal vises i 5:7 |
| SG-AIR-003 | Entring Over Skyhavet | `sg-air-003-sky-boarding-r3a.jpg` | hoved | 848×1264 | 2:3 | op 5,6×, efterbearbejd |
| SG-AIR-005 | Udkigsposten i Masten | `sg-air-005-crows-nest-bridge-r2f.jpg` | hoved | 864×1216 | 5:7 | op 6,8×, efterbearbejd |
| SG-AIR-008 | Armadaen Over Victoriansk Metropolis | `sg-air-008-armada-victorian-city-k1a.jpg` | hoved | 848×1264 | 2:3 | op 5,6×, efterbearbejd |

**Reglen for SG-AIR-002:** kataloget peger på `sg-air-002-celestial-dome` som filnavn, men dets egen
karakterbeskrivelse — østasiatisk navigatør, sølvstribet knold, messingbriller, lysende armillarsfære — er
navigator-billedet. Ejeren valgte 13/11 navigator som hovedbillede og celestial som sekundært galleribillede.
Reglen står i værktøjet (`OVERRIDES`), ikke i en læsers hukommelse.

**Backloggen er 45 designs**, ikke 45 "halve" produkter. De ligger som kladder med SKU og titel fra
importarket, og de publiceres først, når de har et færdigt tryk og et drop-vindue. Det holder butikken under
loftet på **~50 aktive produkter**: 20 publicerede i dag + 50 plakater ville ellers give 70.

Drop 1 må ikke publiceres, før Gelato-forbindelsen findes og hvert design har en færdig trykfil. Intet
produkt, ingen pris og ingen lagerstatus er ændret i dette pas.

## 4. De 12 filer mod de 13, der blev sagt

Målt i mappen: **12 filer**. De dækker **11 SKU'er** — SG-AIR-001 til 008 (otte), SG-ENG-011 (én) og
SG-NAT-031/032 (to) — plus den ekstra SG-AIR-002-variant. Det er præcis "12 motiver plus én variant",
bortset fra at der kun er 11 motiver.

Det trettende billede findes ikke i mappen, og det kan ikke aflæses af filnavnene, hvilket motiv det skulle
have været. Det kan **kun** siges, at det ville have dækket præcis **ét** af de **39 SKU'er, der ikke har et
billede** i dag. Inden for Sky Armada er SG-AIR-009 (Sky-Korsaren med Mekanisk Falk) og SG-AIR-010 (Den
Sidste Luftskibsbygger) de eneste uden billede; batchen rummer også ENG- og NAT-designs, så en manglende
ENG- eller NAT-plakat er lige så sandsynlig. Serien er altså **ét motiv kort** i forhold til optællingen, og
det skal findes i genereringsbatchen eller genoplades fra Drive-papirkurven, før nogen kalder serien komplet.

## 5. Koblingen fil → SKU (dry run, ikke en upload)

Katalogarket ligger nu i repoet som [`data/posters-50-import.csv`](../../data/posters-50-import.csv) (50
rækker, 50 unikke SKU'er). Værktøjet [`tools/poster_attach.py`](../../tools/poster_attach.py) læser det og
kobler filnavne til SKU'er efter tre regler, i denne rækkefølge:

1. **Eksplicit override** med en skrevet grund (SG-AIR-002 navigator/celestial, SG-AIR-005
   `crows-nest-bridge` mod katalogets `crows-nest-lookout`).
2. **Præcis katalog-stamme** efter revision-suffikset er fjernet (`-r1a`, `-k2a` osv.).
3. **Alt andet afvises** med den nærmeste kandidat som spor — ingen fuzzy match.

Værktøjet uploader intet og skriver intet til WordPress. Det måler også opløsning og trykbehov. Kørsel
13/11 mod de 12 hentede originaler:

```text
POSTER-ATTACH — dry run, 12 billeder, intet uploadet

fil                                          px          format    SKU          rolle      tryk
sg-air-001-aurora-helm-r1a.jpg               896×1200    3:4→5:7   SG-AIR-001   main       uden for katalogformat (3:4); beskår til 5:7; 7.6 cm nu; 6.6× op
sg-air-002-celestial-dome-r2a.jpg            896×1200    3:4→5:7   SG-AIR-002   secondary  uden for katalogformat (3:4); beskår til 5:7; 7.6 cm nu; 6.6× op
                                             ↳ katalogets Filnavn_PNG, men en anden figur — beholdt som galleri/sekundært
sg-air-002-navigator-dome-r2b.jpg            864×1216    5:7       SG-AIR-002   main       7.3×10.3 cm @300 dpi → 6.8× op
                                             ↳ katalogets beskrivelse (østasiatisk navigatør, sølvstribet knold, messingbriller, armillarsfære) — valgt som hovedbillede 13/11
sg-air-003-sky-boarding-r3a.jpg              848×1264    2:3       SG-AIR-003   main       7.2×10.7 cm @300 dpi → 5.6× op
sg-air-004-captain-chart-table-r2m.jpg       864×1216    5:7       SG-AIR-004   main       7.3×10.3 cm @300 dpi → 6.8× op
sg-air-005-crows-nest-bridge-r2f.jpg         864×1216    5:7       SG-AIR-005   main       7.3×10.3 cm @300 dpi → 6.8× op
                                             ↳ samme motiv som katalogets 'crows-nest-lookout' (Udkigsposten i Masten); genereringsbatchen brugte et andet slang-ord
sg-air-006-storm-plank-leap-r3b.jpg          848×1264    2:3       SG-AIR-006   main       7.2×10.7 cm @300 dpi → 5.6× op
sg-air-007-sky-harbor-dawn-r5a.jpg           848×1264    2:3       SG-AIR-007   main       7.2×10.7 cm @300 dpi → 5.6× op
sg-air-008-armada-victorian-city-k1a.jpg     848×1264    2:3       SG-AIR-008   main       7.2×10.7 cm @300 dpi → 5.6× op
sg-eng-011-chief-engineer-valve-r4a.jpg      896×1200    3:4→5:7   SG-ENG-011   main       uden for katalogformat (3:4); beskår til 5:7; 7.6 cm nu; 6.6× op
sg-nat-031-strix-mechanica-owl-k2a.jpg       848×1264    2:3       SG-NAT-031   main       7.2×10.7 cm @300 dpi → 5.6× op
sg-nat-032-cor-horologium-heart-k3b.jpg      848×1264    2:3       SG-NAT-032   main       7.2×10.7 cm @300 dpi → 5.6× op

Drop (5): SG-AIR-001, SG-AIR-002, SG-AIR-003, SG-AIR-005, SG-AIR-008
  SG-AIR-001: sg-air-001-aurora-helm-r1a.jpg
  SG-AIR-002: sg-air-002-celestial-dome-r2a.jpg, sg-air-002-navigator-dome-r2b.jpg
  SG-AIR-003: sg-air-003-sky-boarding-r3a.jpg
  SG-AIR-005: sg-air-005-crows-nest-bridge-r2f.jpg
  SG-AIR-008: sg-air-008-armada-victorian-city-k1a.jpg
Backlog: 45 designs som kladder (loft 50 aktive produkter)

OK — hvert billede er koblet til præcis én SG-SKU, og alle fem drop-designs har et hovedbillede.
```

Selftesten er **20 fixtures**: de otte rigtige filnavne, to bevidst forkerte (`sg-air-009-...` og en
tastefejl i 001), dublet-detektion, katalogintegritet (50 rækker, 50 unikke SKU), en midlertidig mappe hvor
en ukendt fil skal få kørslen til at fejle, og tre formatdomme. Den sidste er tilføjet 13/11, fordi den første
kørsel kaldte en 896×1200-fil (3:4) for "5:7", selv om den ligger uden for katalogformatet: 3:4 er ikke en
produktstørrelse, så filen skal beskæres bevidst, og værktøjet siger det nu højt (`3:4→5:7`) i stedet for at
vælge det nærmeste format i stilhed. Den kører som værn i `tools/natlige-vaern.sh`.

**Sådan bruges den efter upload:** læg filerne i Mediebiblioteket med de samme navne, og kør værktøjet mod
en mappe med de samme filnavne. Så længe navnene er uændrede, giver det samme tabel. Upload og
produktkobling kræver skriveadgang til sitet; værktøjet gør det ikke af sig selv.

## 6. GPSR og AI-linjen

GPSR-blokken er skrevet som kladde i
[gpsr-og-tillidsside-2026-11-13.md](gpsr-og-tillidsside-2026-11-13.md). Den indeholder kun bekræftede
fakta — adressen **Lavgade 44a, Aabenraa** fra repoets egen copy og Complianz' adressefelt — og
markerer CVR, postnummer, telefon og det juridiske navn som felter, der skal leveres. Linjen *"Designed
with AI tools, finished by hand"* må først komme på et produkt, når efterbearbejdningen af netop det motiv
faktisk er udført; derfor er den gated pr. design og ikke en fast del af skabelonen.

## 7. Hvad der stadig kræver ejeren

1. **Forbind Gelato** (Connect-knappen ovenfor) og kobl til de eksisterende `SG-AIR-…`-kladder.
2. **Læg de 12 billeder i Mediebiblioteket** — eller giv skriveadgang, så agenten kan gøre det. Det
   trettende billede skal findes først, hvis serien skal være komplet.
3. **Lever CVR, fuldt adressefelt og telefon**, så GPSR-blokken kan færdiggøres.

Intet af det kan agenten gøre uden ejerens login, ejerens upload eller ejerens virksomhedsdata.
