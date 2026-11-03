# Punk-lancering — 6 SKU: produkttekster og mockup-plan

Klar-til-publikation for de seks første punk-designs, så de kan gå i butikken i samme øjeblik billederne findes.

Skrevet 2/11 2026. Bygger på [punk-grene-2026-11-02.md](punk-grene-2026-11-02.md) (retningen og de 18), [produkttekster-2026-11-01.md](produkttekster-2026-11-01.md) (syv-parts-strukturen) og [mockup-brief-2026-11-01.md](mockup-brief-2026-11-01.md) (billedreglerne).

**De seks SKU'er** — to pr. gren, som prioriteret i punk-grene-dokumentet:

| Design | Titel | Type | Gren |
|---|---|---|---|
| C1 | The Escapement — Clockpunk Print | Plakat | Clockpunk |
| C5 | The Keymaker's Hands — Clockpunk Tee | Tee | Clockpunk |
| D1 | Zeppelin Line No. 9 — Dieselpunk Travel Poster | Plakat | Dieselpunk |
| D2 | The Riveter's Prayer — Dieselpunk Tee | Tee | Dieselpunk |
| T1 | Coil No. 1 — Teslapunk Tee | Tee | Teslapunk |
| T5 | Static Saint — Teslapunk Hoodie | Hoodie | Teslapunk |

> **Rækkefølgen er ikke vilkårlig.** Den følger "billigst at producere og stærkeste signal først": T1 er én farve på sort, D1 er den stærkeste plakat, og T5 er det eneste motiv der kan bære hoodie-prisen. C5 er sværest at få rigtigt i AI og skal derfor i gang tidligt.

---

## 1. Hvad der er fast, og hvad der skrives pr. produkt

Teksten har de samme syv dele som de 39 eksisterende produkter. Fem af dem er **faste blokke** der kopieres ordret — de er allerede sande for butikken, og de skal ikke skrives igen pr. vare:

| Del | Fast eller pr. produkt |
|---|---|
| 1. Hook | **Pr. produkt** — én sætning, aldrig en skabelon |
| 2. Why you'll like it | **Pr. produkt** — tre punkter, konkrete |
| 3. Details & fit | Delt: pasform og tryk er pr. produkt, stof og måletabel er fast |
| 4. Made to order & delivery | **Fast** — produktions- og leveringstider fra `/delivery/` |
| 5. Care | **Fast** — 30 °C, vrangen ud, ingen tørretumbler |
| 6. GPSR | **Fast**, men ufuldstændig — se §5 |
| 7. Kollektionslink | **Pr. produkt** — og kræver tre nye kollektioner, se §4 |

**Placeholders** markeret med `{{…}}` er de felter jeg ikke må udfylde fra gæt. De skal erstattes med tekst fra et allerede udgivet produkt:

| Token | Erstat med |
|---|---|
| `{{FABRIC_LINE}}` | Materialelinjen fra en udgivet tee (fx 563) eller hoodie (fx 405) — ikke opfundet, den er allerede skrevet og korrekt |
| `{{SIZE_TABLE}}` | Den eksisterende S–2XL-tabel med chest/length i cm |
| `{{GPSR_BLOCK}}` | Den eksisterende GPSR-blok. Uændret indtil CVR og adresse foreligger |

**Om mål:** design-dokumentet siger løst "A2/A3", men butikken sælger **30 × 40 og 50 × 70 cm**. Teksterne nedenfor bruger de rigtige mål. Ret design-dokumentets løse omtale, hvis det skal bruges i kundevendt tekst.

**Sprog:** produkttekst er engelsk, som resten af butikken. Denne fil er dansk, fordi den er arbejdsinstruktion.

---

## 2. De seks produkter

### C1 · The Escapement — Clockpunk Print

- **Slug:** `the-escapement-clockpunk-print`
- **SEO-titel:** The Escapement — Clockpunk Print | Steamgears
- **Meta description:** A clock's escapement, taken apart and drawn as a nineteenth-century plate. Brass on walnut, printed on mat paper in 30 × 40 or 50 × 70 cm.
- **Fokus-søgeord:** `vintage technical drawing print`
- **Pris:** samme lag som de fem eksisterende plakater. Gated af valutabeslutningen — se §6.

**Hook:** A clock's escapement, taken apart and drawn the way a nineteenth-century anatomist would have drawn it: every pivot accounted for, every tooth explained.

**Why you'll like it**
- It is a plate, not a poster. Brass wheel, steel pallet fork, mainspring laid out flat — the drawing is doing the work, not the decoration.
- One warm metal on a deep walnut ground, so the lines stay sharp instead of turning grey.
- Printed on mat paper and shipped in a tube, in 30 × 40 or 50 × 70 cm.

**Details & fit:** Printed on mat paper, 30 × 40 or 50 × 70 cm, sent in a rigid tube. The drawing is sized so the finest hatching still holds at 30 × 40 — nothing fills in.

**Collection link:** Clockpunk.

---

### C5 · The Keymaker's Hands — Clockpunk Tee

- **Slug:** `the-keymakers-hands-clockpunk-tee`
- **SEO-titel:** The Keymaker's Hands — Clockpunk Tee | Steamgears
- **Meta description:** Two hands assembling a brass key, lit from one side. An engraved artisan print with no face and no background — just the work.
- **Fokus-søgeord:** `artisan t-shirt`
- **Pris:** samme lag som de øvrige tees.

**Hook:** Two hands, one brass key, and a single light from the left — the only thing in the frame is the work.

**Why you'll like it**
- No face. It sits the same on everyone, which is the point of a print you wear rather than a portrait.
- Heavy black fill and a few brass highlights, so it survives the wash and the print stays legible at arm's length.
- Chest print, sized to read from across a room without covering the whole shirt.

**Details & fit:** Chest print, 28 cm wide, centred 7 cm below the collar. {{FABRIC_LINE}} {{SIZE_TABLE}}

**Collection link:** Clockpunk.

---

### D1 · Zeppelin Line No. 9 — Dieselpunk Travel Poster

- **Slug:** `zeppelin-line-no-9-dieselpunk-travel-poster`
- **SEO-titel:** Zeppelin Line No. 9 — Dieselpunk Travel Poster | Steamgears
- **Meta description:** A 1930s travel poster for an airship line that never existed. Flat Art Deco colour, ochre sky, olive mountains — printed on mat paper.
- **Fokus-søgeord:** `art deco travel poster`
- **Pris:** samme lag som de fem eksisterende plakater.

**Hook:** A travel poster for an airship line that never existed, and the lower third left empty on purpose — the way a real booking poster left room for the timetable.

**Why you'll like it**
- Flat Art Deco colour blocks and no photographic detail: it reads as a period poster, not as a rendering of one.
- Deliberately empty lower third, so it hangs well beside furniture instead of fighting it.
- 30 × 40 or 50 × 70 cm on mat paper, shipped in a tube.

**Details & fit:** Printed on mat paper, 30 × 40 or 50 × 70 cm, sent in a rigid tube. Flat colour means no banding in the sky, which is where cheap poster prints usually fail.

**Collection link:** Dieselpunk. Cross-link from the existing guide `/steampunk-vs-dieselpunk/`, which is the page people land on when they are choosing between the two looks.

---

### D2 · The Riveter's Prayer — Dieselpunk Tee

- **Slug:** `the-riveters-prayer-dieselpunk-tee`
- **SEO-titel:** The Riveter's Prayer — Dieselpunk Tee | Steamgears
- **Meta description:** A worker in silhouette with a rivet gun, built from constructivist planes. Olive and rust on black, one amber light, no insignia and no flag.
- **Fokus-søgeord:** `constructivist t-shirt`
- **Pris:** samme lag som de øvrige tees.

**Hook:** A worker in silhouette, three-quarter turned, built from a handful of flat planes and lit from behind — the poster language of the 1930s with none of its symbols.

**Why you'll like it**
- Silence instead of slogans: no insignia, no flag, no nation. The geometry does the work.
- Olive drab and rust on near-black, with one amber highlight that still reads in daylight.
- Works in a single colour, so it stays sharp on every shirt colour we print it on.

**Details & fit:** Chest print, 28 cm wide, centred 7 cm below the collar. {{FABRIC_LINE}} {{SIZE_TABLE}}

**Collection link:** Dieselpunk.

---

### T1 · Coil No. 1 — Teslapunk Tee

- **Slug:** `coil-no-1-teslapunk-tee`
- **SEO-titel:** Coil No. 1 — Teslapunk Tee | Steamgears
- **Meta description:** A Tesla coil mid-discharge, drawn as luminous line art in cyan and violet on black. One colour, hard edges, no glow effects.
- **Fokus-søgeord:** `electricity t-shirt`
- **Pris:** samme lag som de øvrige tees.

**Hook:** A Tesla coil caught mid-discharge, with the arcs branching out to the edges of the print and nothing else in the frame.

**Why you'll like it**
- Drawn, not rendered: the arcs are hard edges and thin filaments, so the print looks engraved rather than photographed.
- One colour family on black, which is the most forgiving thing you can print on a dark shirt.
- The apparatus is the subject — no lab, no scientist, no lightning storm cliché.

**Details & fit:** Chest print, 28 cm wide, centred 7 cm below the collar. Best on black; on lighter shirts the negative space stops doing its job. {{FABRIC_LINE}} {{SIZE_TABLE}}

**Collection link:** Teslapunk.

---

### T5 · Static Saint — Teslapunk Hoodie

- **Slug:** `static-saint-teslapunk-hoodie`
- **SEO-titel:** Static Saint — Teslapunk Hoodie | Steamgears
- **Meta description:** A porcelain-faced figure with copper-wire hair and a corona of electric arcs instead of a halo. Indigo and violet, printed on a heavy hoodie.
- **Fokus-søgeord:** `gothic hoodie`
- **Pris:** ét lag over tee — se §6.

**Hook:** A figure with porcelain skin and copper-wire hair, and a corona of branching arcs where a halo would have been.

**Why you'll like it**
- The one print in the collection that carries a face, and it is calm rather than frightening — serene, eyes closed.
- Deep indigo ground with violet only at the outer tips of the arcs, so it stays graphic instead of glowing.
- Large chest print, sized for a hoodie rather than scaled up from a tee.

**Details & fit:** Large chest print, 30 cm wide, centred 8 cm below the collar. {{FABRIC_LINE}} {{SIZE_TABLE}}

**Collection link:** Teslapunk, with an overlap into Gothic & Victorian — this is the one design in the six that belongs to both.

---

## 3. Mockup-plan

Samme regler som de 39: **ingen hvid baggrund**, **tre faste modeller** brugt igen, **ét print pr. billede**, **min. 2000 px** på den lange led, JPG under 500 KB, og **billede 1 er altid det rene produktmockup**.

Filnavn: `{design}-{motiv}-{view}.jpg` — fx `t1-coil-no1-onmodel.jpg`. Omdøb til `{woo-id}-…` når produkterne er oprettet, så de kan findes igen.

### C1 — plakat, 4 billeder

| # | View | Scene | Alt-tekst |
|---|---|---|---|
| 1 | Produktmockup | Rent mockup, motivet fylder rammen | `The Escapement clockpunk print, technical plate of a clock escapement in brass on walnut` |
| 2 | Indrammet på væg | A3 i sort ramme, mørk væg, bøger i forgrunden nederst til venstre | `Framed Escapement clockpunk print in A3 on a dark study wall with books` |
| 3 | Nærbillede | 1:1 på tandhjulet, så man ser at stregerne ikke flyder | `Close-up of the brass escapement line work on the clockpunk print` |
| 4 | Format-sammenligning | 30 × 40 og 50 × 70 side om side, samme væg | `Escapement print shown in 30 × 40 and 50 × 70 cm side by side` |

### C5 — tee, 4 billeder

| # | View | Scene | Alt-tekst |
|---|---|---|---|
| 1 | Produktmockup | Rent mockup på sort tee | `The Keymaker's Hands clockpunk tee in black with an engraved hands-and-key print` |
| 2 | På model | Hænder i billedet — model der holder noget messing, værksted, én lampe | `Model wearing the Keymaker's Hands tee at a workbench under a single lamp` |
| 3 | Fladlæg | Oppefra på mørkt linned, ærmerne foldet ind | `Flat lay of the Keymaker's Hands tee on dark linen` |
| 4 | Detalje | 1:1 på hånden og nøglen | `Close-up of the engraved hands and brass key print detail` |

### D1 — plakat, 4 billeder

| # | View | Scene | Alt-tekst |
|---|---|---|---|
| 1 | Produktmockup | Rent mockup | `Zeppelin Line No. 9 dieselpunk travel poster with an airship in an ochre Art Deco sky` |
| 2 | Indrammet på væg | A2 i træramme over en lænestol, lampelys | `Framed Zeppelin Line No. 9 poster in A2 above an armchair in low lamplight` |
| 3 | Nærbillede | Papirets tekstur og trykkets kant, skrå lys | `Close-up of the mat paper texture and print edge on the Zeppelin poster` |
| 4 | Format-sammenligning | 30 × 40 og 50 × 70, så den tomme nederste tredjedel kan aflæses | `Zeppelin Line No. 9 poster in 30 × 40 and 50 × 70 cm` |

### D2 — tee, 4 billeder

| # | View | Scene | Alt-tekst |
|---|---|---|---|
| 1 | Produktmockup | Rent mockup på sort tee | `The Riveter's Prayer dieselpunk tee with a constructivist worker silhouette` |
| 2 | På model | Udendørs mod betonmur, gråvejr, halv figur | `Model wearing the Riveter's Prayer tee against a concrete wall in overcast light` |
| 3 | Fladlæg | Oppefra på mørkt linned | `Flat lay of the Riveter's Prayer dieselpunk tee on dark linen` |
| 4 | Detalje | 1:1 på silhuettens kant og nitterne | `Close-up of the silhouette edge and rivet detail on the dieselpunk tee` |

### T1 — tee, 4 billeder

| # | View | Scene | Alt-tekst |
|---|---|---|---|
| 1 | Produktmockup | Rent mockup på sort tee | `Coil No. 1 teslapunk tee with a Tesla coil and cyan electric arcs on black` |
| 2 | På model | Indendørs, mørkt rum, én lyskilde fra siden så det sorte stof ikke bliver gråt | `Model wearing the Coil No. 1 tee in a dark room lit from one side` |
| 3 | Fladlæg | Oppefra på skifer eller mørkt metal | `Flat lay of the Coil No. 1 tee on a dark slate surface` |
| 4 | Detalje | 1:1 på buerne og filamenterne — viser at de er tegnet, ikke glød | `Close-up of the electric arc line work on the teslapunk tee` |

### T5 — hoodie, 5 billeder

| # | View | Scene | Alt-tekst |
|---|---|---|---|
| 1 | Produktmockup | Rent mockup på mørk hoodie | `Static Saint teslapunk hoodie in indigo with a figure and electric arc corona` |
| 2 | På model, portræt | Brystprintet i fokus, ansigtet i kanten af billedet, hætten nede | `Model wearing the Static Saint hoodie, chest print in focus` |
| 3 | På model, hætte oppe | Viser at printet stadig læses med hætten oppe | `Model wearing the Static Saint hoodie with the hood up` |
| 4 | Fladlæg | Oppefra, ærmerne ud, mørkt underlag | `Flat lay of the Static Saint hoodie on a dark surface` |
| 5 | Detalje | 1:1 på ansigtet og buerne ved tindingen | `Close-up of the porcelain face and arc detail on the Static Saint hoodie` |

**Egne fotos er nødvendige** til T5 billede 2 og 3 (Printifys hoodie-scener giver ikke det portrætudsnit), og til D1 billede 2 hvis der ikke findes en træramme-scene til plakat-blueprinten. Alt andet kan køres i Printify.

---

## 4. Tre nye kollektioner

De seks SKU'er passer ikke i nogen af de seks eksisterende kollektioner (Dark Academia 94, Gothic & Victorian 95, Natural History 96, Vintage Maritime 97, Witchy & Tarot 98, Steampunk Capsule 99). Der skal tre nye `product_cat`-termer til — ellers kan grenene ikke måles hver for sig, og hele pointen med at lancere to pr. gren forsvinder.

| Kollektion | Slug | Produkter |
|---|---|---|
| Clockpunk | `clockpunk` | C1, C5 |
| Dieselpunk | `dieselpunk` | D1, D2 |
| Teslapunk | `teslapunk` | T1, T5 |

Overlap, som husreglen tillader: **T5** ligger også i Gothic & Victorian (95), og **C1** også i Dark Academia (94). De øvrige fire ligger kun i deres egen gren, så målingen er ren.

Oprettelsen kræver samme MCP-vej som batch 2 i om-mærkningen — den kan ikke gøres herfra uden credentials. Kaldene er skrevet og valideret i [deploy/steamgears/](../../deploy/steamgears/README.md); det er kun udførelsen der mangler adgang.

---

## 5. Blokke der kopieres ordret

**Made to order & delivery:** produktion 3–7 hverdage, levering 6–10 hverdage i DK og 7–12 i resten af EU, tracking-mail ved afsendelse, gear kan ankomme separat, porto betales kun én gang. Kilde: `/delivery/` og `/faq/`.

**Care:** 30 °C, vrangen ud, ikke tørretumbler, ikke stryge direkte på trykket. Kilde: `/faq/`.

**GPSR:** `{{GPSR_BLOCK}}` er stadig ufuldstændig. Den indeholder materiale, producent og kontaktformular, men **ikke en fysisk postadresse**, fordi CVR og adresse mangler fra ejer. Det er samme blokering som på de 39, og de seks nye skal ikke udgives med en dårligere blok end de eksisterende — men de arver heller ikke fejlen uden at den er noteret her.

---

## 6. Pris — hvorfor der ikke står et tal

Butikken prissætter i **USD** ($21,90–$29,90), og det er selv et åbent problem: WooCommerce omregner ikke beløb, så en valutakodeændring ville gøre 21,90 USD til 21,90 kr. Priserne skal genberegnes fra kostpris + fragt + margen, og den beslutning (Batch 0, S4.3) er ikke taget endnu.

Derfor står der "samme lag" i stedet for et opfundet tal:

- **Tee (C5, D2, T1):** samme lag som de eksisterende tees
- **Plakat (C1, D1):** samme lag som de fem eksisterende plakater
- **Hoodie (T5):** ét lag over tee

Sæt først tal ind, når valutaen er besluttet — ellers skal de seks røres to gange, præcis som de 39 ville blive.

---

## 7. Rækkefølge og tidsforbrug

| # | Handling | Tid | Blokeret af |
|---|---|---|---|
| 1 | Kør prompterne, vælg på 200 px-aftryk | 4–5 t (inkl. ventetid) | Adgang til billedmodel |
| 2 | Oprensning + typografi i layout | 3 t | 1 |
| 3 | Printprøve i 30 cm, alle seks | 1,5 t | 2 |
| 4 | Opret produkter i Printify, læg mockups ind | 2 t | 3 |
| 5 | Opret tre kollektioner, tildel produkter | 0,5 t | Credentials |
| 6 | Sæt teksterne ind via MCP, sæt priser | 1 t | Valutabeslutning + 5 |
| 7 | Verificér 6/6 produktsider live, purge W3TC | 0,5 t | 6 |

**I alt ca. 12,5 timer**, hvoraf de 8 ligger før mockups og tekst kan bruges. Teksten her er den eneste del der ikke venter på noget — den kan lægges ind som kladde allerede nu, hvis kollektionerne oprettes først.

**Trin 5 og 6 er forberedt som kørbare MCP-kald:** [deploy/steamgears/README.md](../../deploy/steamgears/README.md). Tre kollektioner er klare til at køre, de seks kladser er genereret og afventer kun de fem tekstblokke og term-id'erne — ikke credentials alene. Se §9.

---

## 8. Kontrolleret

- Alle seks titler og slugs følger butikkens eksisterende navnemønster (`{Motiv} — {Kollektion} {Type}`), med SEO-titel som `{Navn} | Steamgears` — samme som batch 1 på de 32 produkter.
- Leverings-, produktions- og plejetider er de samme som de 39 og kommer fra `/delivery/` og `/faq/`, ikke fra gæt.
- Ingen priser, ingen materialer og ingen måltabel er opfundet: de er enten fastslået i kilden eller markeret `{{…}}`.
- Kollektionsslugene er nye og kolliderer ikke med de seks eksisterende (94–99).
- Plakatmål er rettet til butikkens faktiske 30 × 40 og 50 × 70 cm.

**Ikke kontrolleret:** om Printify har de nævnte scener til hver blueprint (det kræver adgang til kontoen), og om teksterne kan lægges ind uden at Printify-sync overskriver dem — samme uafklarede risiko som på de 39.

---

## 9. Forberedt til kørsel (2/11)

Trin 5 og 6 er nu ikke bare beskrevet, men skrevet som kald: [deploy/steamgears/](../../deploy/steamgears/README.md).

| Fil | Indhold |
|---|---|
| `deploy/steamgears/punk-catalogue.json` | **Kilden** — de seks produkters tekst, slugs, SEO, kollektionsnøgler og `terms`-kortet med term-id'er (94–99 kendt, de tre nye `null`) |
| `deploy/steamgears/publish-blocks.json` | De fem faste blokke som tokens. Tom = uafklaret, og så bygges beskrivelsen ikke |
| `deploy/steamgears/punk-manifest.json` | Genereret: 3 kollektions-trin + 6 produkt-trin |
| `tools/deploy/build_punk.py` | Bygger manifestet, `--check` validerer, `--fetch-blocks` henter de fem blokke fra produkt 563/405 |

**Klar nu:** de tre kollektioner. **Blokeret:** de seks kladser, indtil de fem tekstblokke er hentet og de tre term-id'er er skrevet ind. Pris er *ikke* en blokering — kladser må godt oprettes uden, og en advarsel holder styr på det.

Fire ting ved runneren er lavet om, alle additive: `--manifest` (så warpstorm-manifestet ikke skal røres), `tool_aliases` (Royal MCP omdøber værktøjer mellem versioner — manifestet prøver fire stavemåder), `blocked` (et trin kan bære sin egen begrundelse og springes over) og `--force` (til at køre det alligevel, med åbne øjne). `deploy.py run --offline` på warpstorm giver stadig 27 ok / 1 skip / 0 fejl.

**Ikke idempotent for produkter.** `wc_create_product` har ingen upsert: en anden `--apply` laver seks nye kladser med `-2` i sluggen. Kør produkt-trinnene én gang, notér id'erne, og skift til `wc_update_product` bagefter.

**Filerne er også forberedt:** `assets/punk/{design}/` med fastlagte filnavne, alt-tekster, udregnede pixelmål pr. trykfil (`check_assets.py`) og medie-upload til WordPress med id-capture. Se [punk-upload-2026-11-02.md](punk-upload-2026-11-02.md), der også lister det der stadig er manuelt i Printify.

**Verificeret offline:** 0 fejl, 6 advarsler (alle "ingen pris"), 3 trin klar og 6 blokeret med begrundelse. Beskrivelserne er tjekket for løse tokens og for at måletabellen ikke havner inde i en `<p>`. **Ikke verificeret:** kollektionsværktøjets faktiske navn, om det tager `parent`, om `wc_create_product` accepterer kategorier som slug, og Rank Math-værktøjet — det første svarer `deploy.py tools` på, resten fejler synligt på en kladse.
