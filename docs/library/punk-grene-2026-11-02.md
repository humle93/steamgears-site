# Punk-grene — 18 nye designs: clockpunk, dieselpunk, teslapunk

Retningsark for tre grene, butikken ikke dækker i dag, bygget til at rette de seks fejl der går igen i de 39 eksisterende designs.

Skrevet 2/11 2026. Hører til [om-maerkning-dark-academia-2026-11-01.md](om-maerkning-dark-academia-2026-11-01.md) (de 39 produkter) og [mockup-brief-2026-11-01.md](mockup-brief-2026-11-01.md).

**Kontaktark:** `concepts/punk-gallery/index.html` — palette, værdistruktur og komposition som abstrakt skitse pr. design.
**Prompter:** [punk-prompts-2026-11-02.md](punk-prompts-2026-11-02.md) — genereret, ikke håndskrevet.
**De seks første SKU:** [punk-lancering-2026-11-02.md](punk-lancering-2026-11-02.md) — produkttekster, mockup-plan og de tre nye kollektioner.
**Data:** `concepts/punk-gallery/designs.json` er eneste kilde. Ret den, kør `python3 tools/designs/build_gallery.py`, og både arket og prompt-packen skrives igen.

> **Hvad dette er, og hvad det ikke er.** Der er ikke genereret et eneste færdigt billede her — workspacet har ingen billedmodel. Det, der ligger klar, er retningen, prompterne og tryk-kravene, så de kan køres igennem uden at nogen skal opfinde noget undervejs. Skitsearket er komposition, ikke kunst; det er der for at retningen kan godkendes, før der bruges credits.

---

## 1. Diagnosen — hvorfor de 39 ikke kan skaleres som de er

Seks fejl, der går igen. Det er dem de 18 er bygget til at undgå, ikke bare "flere motiver".

| # | Fejl | Hvor man ser den | Konsekvens |
|---|---|---|---|
| 1 | **Svævende emblem** — motivet står alene midt på brystet uden handling eller ramme | Næsten alle 39 | Læses som clipart, ikke som et design nogen har bestilt |
| 2 | **Tandhjul som tekstur** — gear fyldt på for at signalere "steampunk" | Gear Filigree, Compass Mandala, Sun Compass | Det mest slidte udtryk i nichen; alle konkurrenter har det |
| 3 | **Midt-tone på midt-tone** — gråligt motiv på mørkt stof | Flere af hoodierne | Forsvinder på 1 meters afstand, og i annoncen på 200 px |
| 4 | **Hårfine streger** — "graveret" look under 0,5 mm | De fem plakater især | Flyder sammen i DTG og bliver grå plamager |
| 5 | **Kun symmetri** — alt er centreret og spejlet | Mandala-familien | Ingenting trækker blikket; ingen bevægelse i kompositionen |
| 6 | **Ingen figur, ingen genstand, ingen arkitektur** — kun dyr og maskindele | Hele kataloget | Kan ikke sælge til dem, der vil have et motiv med en historie |

**Husreglerne** (i `designs.json`, vist øverst på arket) er svaret på hver af dem. De er ikke smag — de er de seks ting, der gør et motiv salgbart i POD frem for bare pænt på en skærm.

---

## 2. De tre grene — og hvorfor de er tre og ikke én

De deler ikke palette, ikke periode og ikke formsprog. Det er pointen: de rammer tre forskellige søgninger, og en kunde der kan lide den ene, skal ikke se et katalog af varianter af den anden.

| | **Clockpunk** | **Dieselpunk** | **Teslapunk** |
|---|---|---|---|
| Periode | 1450–1750, før dampen | 1920–1945, mellemkrigstid | 1890–1910, elektricitetens optimisme |
| Materialer | Valnød, messing, ben, glas | Støbejern, nitter, aluminium, olie | Kobber, glas, porcelæn, buer |
| Formsprog | Fin gravering, plansje, asymmetri i detaljen | Art Deco, flade farveflader, propaganda-geometri | Linjekunst på sort, negativt rum, glød der skal oversættes |
| Palette | `#3A2A20` `#B08D57` `#E8DFCB` `#6B1F27` | `#1C1A17` `#4A4E3A` `#9C4A2A` `#D9A441` | `#0E1230` `#B87333` `#7FE3FF` `#8B5CF6` |
| Fejlgrebet | Male tandhjul om og kalde det clockpunk | 1930'erne uden grænser — uniformer, flag, symboler | Tegne glød, som ikke kan trykkes |
| Stærkeste produkt | Plakat og tee | Plakat, og den ene tee der virker på hvidt | Hoodie og tee på sort |
| Søgebane | vintage technical drawing, natural history, gothic | art deco poster, vintage travel poster, constructivist | anatomy tee, art nouveau poster, minimal science poster |

**Hvorfor netop disse tre:** dark academia-omlægningen dækker de 26 produkter, der ikke er steampunk i nogen andens øjne. Men den efterlader et hul: butikken har ingen **grafisk identitet ud over tandhjul**, og den har intet at sælge til dem, der godt kan lide perioderne men ikke vil gå med en maskine på brystet. Clockpunk giver dybden, dieselpunk giver plakaterne og den grafiske styrke, teslapunk giver det ene simple, høj-kontrast motiv, der trykker billigt og ser dyrt ud.

---

## 3. De 18

| ID | Titel | Produkt | Nøgleord | Pointen |
|---|---|---|---|---|
| C1 | The Escapement | Plakat, tee | vintage technical drawing | Mekanik som videnskabelig plansje, ikke som dekoration |
| C2 | Automaton Hare | Tee, hoodie | natural history tee | Dyr i bevægelse — kataloget har kun dyr stillet op |
| C3 | The Astronomer's Clock | Plakat | astronomy poster | Cirkulært uden at være endnu et mandala; symmetrien er brudt |
| C4 | Spring-Driven Saint | Hoodie (ryg), plakat | gothic hoodie | Stærkere figurmotiv end det ene kataloget har |
| C5 | The Keymaker's Hands | Tee | artisan tee | Arbejde uden ansigt — sælger til alle kropstyper |
| C6 | Cabinet 47 | Plakat, tee | cabinet of curiosities | Plakatversionen af en tee der allerede sælger |
| D1 | Zeppelin Line No. 9 | Plakat | art deco poster | Rejseplakat for et selskab der ikke findes |
| D2 | The Riveter's Prayer | Tee, hoodie | constructivist tee | Propaganda-geometri uden stat, symbol eller uniform |
| D3 | Diesel Cathedral | Plakat | architecture poster | Fabrik som katedral — sælger til dem der ikke klæder sig i stilen |
| D4 | Night Bomber | Tee | aviation tee | Det ene motiv der også virker på hvidt stof |
| D5 | The Mechanic's Alphabet | Tee, klistermærke | minimal logo tee | Butikkens første eget mærke |
| D6 | Last Train, 04:40 | Plakat | railway poster | Etableret samlerkategori butikken slet ikke er i |
| T1 | Coil No. 1 | Tee, hoodie | electricity tee | Hvidt på sort, én farve, næsten gratis at producere |
| T2 | The Wireless Tower | Plakat | art nouveau poster | Kan sælges i art nouveau-søget uden at være et mandala |
| T3 | Aether Anatomy | Tee (ryg), plakat | anatomy tee | Det stærkeste dark academia-motiv butikken mangler |
| T4 | The Glass Battery | Tee, plakat | object tee | Genstand i stedet for dyr — bredere købergruppe |
| T5 | Static Saint | Hoodie | gothic hoodie | Ansigtsmotivet der bærer hoodie-prisen |
| T6 | Frequency Chart | Plakat | minimal science poster | Fire streger og et gitter; bedste margin pr. krone |

---

## 4. Produktionsplan — hvad der skal laves først

18 på én gang er for mange. **Seks først, to pr. gren**, så hver gren kan måles for sig:

| Prioritet | Design | Hvorfor først |
|---|---|---|
| 1 | **T1 Coil No. 1** | Billigst at producere (én farve, sort bund), og den tester om teslapunk-grenen overhovedet får respons |
| 2 | **D1 Zeppelin Line** | Den stærkeste plakat, og den eneste der ikke konkurrerer med noget i kataloget |
| 3 | **C5 Keymaker's Hands** | Sværest at få rigtigt i AI — start tidligt, så den ikke blokerer senere |
| 4 | **T5 Static Saint** | Det motiv der skal bære hoodie-prisen |
| 5 | **D2 Riveter's Prayer** | Sikker silhuet, fungerer i én farve, hurtig at godkende |
| 6 | **C1 The Escapement** | Reneste DTG-design i pakken, og den giver clockpunk-grenen et troværdigt ansigt |

**Arbejdsgang pr. design:** kør prompten 4–8 gange → vælg på et 200 px-aftryk, ikke på fuld størrelse → fjern baggrund og gør motivet rent i layout → læg typografi på hvis designet kræver det (aldrig genereret) → printprøve i 30 cm → først derefter mockup.

**Tidsforbrug:** ca. 45 min. pr. design til generering og udvælgelse, ca. 30 min. til oprensning, og 15 min. til printprøve. Seks designs er **~8 timer**, hvoraf halvdelen er ventetid på genereringer.

**Den ene regel der sparer mest tid:** vælg på thumbnail. Et motiv der ikke kan læses i 200 px bliver ikke bedre af at blive set i 4000 px — det bliver bare et dyrere design, der ikke sælger.

---

## 5. Juridisk — især dieselpunk

1930'erne er ikke et uskyldigt stilvalg. Grænserne, der skal holdes, står også i negativ-prompterne, fordi modellen ellers selv finder på dem:

- **Ingen uniformer, mærker, flag eller symboler fra virkelige nationer eller regimen.** D2 er derfor en anonym arbejder uden uniform og uden insignier. Formsproget, ikke indholdet.
- **Ingen virkelige varemærker.** "Zeppelin" er et firmanavn — D1 er et fiktivt selskab, og der står intet navn i billedet. "Tesla" bruges ikke som mærke eller logo; *teslapunk* som genrenavn er frit.
- **Ingen virkelige personer.** T5 er en anonym figur, ikke et portræt af nogen.
- **D5 må ikke ligne et eksisterende logo.** Et S bygget af stempel- og rørformer er tæt nok på flere industrielle mærker til at det skal søges igennem, før det bliver butikkens identitet.
- **GW-linjen gælder stadig:** intet i disse 18 refererer Warhammer, og ingen af dem skal bruge figurer eller ikoner fra spil.

---

## 6. Hvad der stadig ikke er løst

- **Ingen færdige billeder.** Prompterne er ikke kørt. Det kræver adgang til en billedmodel, og det er den ene del af opgaven der ikke kan gøres herfra.
- **Skitsearket er abstrakt.** Det viser komposition og palette, ikke hvordan motivet ser ud. Det er med vilje, men det betyder også at intet er godkendt før det første rigtige billede ligger der.
- **D5's logo-licens** er ikke undersøgt. Skal søges i varemærkeregistret, før den bruges som butikkens mærke.
- **Glød i teslapunk-serien** er ikke testet i DTG. T1's negativ siger "glow only inside the arcs" — det er stadig en tonet flade, og den skal printprøves før de seks andre i grenen sættes i gang.
- **Ingen prismodel endnu.** De 18 har produkt og nøgleord men ingen pris. Priser sættes først når mockups og printprøver findes — og først når valutaen er besluttet, se [punk-lancering-2026-11-02.md §6](punk-lancering-2026-11-02.md).
