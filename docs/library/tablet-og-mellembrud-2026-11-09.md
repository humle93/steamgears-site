# Mellembruddene: seks layoutfejl mellem 320 og 1 023 px

**Målt 9. november 2026 · steamgears.eu · 320 / 768 / 800 / 1 024 / 1 280 px**

Desktop blev målt 8/11 og mobilen 8/11, men **ingen af de to målinger siger noget om
mellembruddene** — og det var præcis der fejlene lå. **Seks fejl** blev fundet: fem i intervallet 600–1 023 px, og
én på 320 px som mobilmålingen 8/11 ikke dækkede (den målte forsiden, ikke produktsiden).

Målingen blev lavet ved at lægge en same-origin-iframe med den ønskede bredde ind i browserfanen,
så iframen får sin egen layoutbredde, og forælderen kan læse `contentDocument`. Skærmbillederne
er taget ved at skalere iframen (`transform: scale(300/bredde)`), så hele bredden kan ses på én gang.

## 1. De seks fejl

| # | Bredde | Symptom målt | Årsag | Efter |
| --- | --- | --- | --- | --- |
| 1 | 600–1 023 | Header **171 px** med både syv inline-links og burger (768 og 767 px) | Temaet viser de inline links helt ned til ~600 px, men min desktop-regel begyndte først ved 783 px, og burgeren var tvunget synlig under den | Burger **< 1 024 px**, inline links **≥ 1 024 px**; header **73 px** ved 768 og 95 px ved 1 024 |
| 2 | 783–1 023 | **210 px vandret scroll** ved 800 px: header-gruppen var 805 px bred med højre kant i 1 011 px | Samme brudpunkt; den nowrap-gruppe der rummer logo, links og værktøj kræver ~1 024 px | Ingen overflow (scrollWidth 790 ved 800 px) |
| 3 | 783–1 023 | Forsidens varegrid faldt til **én kolonne**: «Selected Works» blev **7 111 px** høj | `.sga-grid` havde kun regler under 783 px og under 380 px | Tre pr. række → sektionen **1 588 px**, forsiden 11 633 → **6 263 px** |
| 4 | 600–1 023 | Butikken faldt til **én kolonne** på 668 px: 16 varer gav **18 343 px** side | Temaets `ul.wc-block-product-template__responsive[class*="columns-"] { 1fr !important }` (én klasse + ét attribut) slog min ren-klasse-selector, selv med `!important` | To kolonner à 341 px → siden **6 767 px** |
| 5 | 783–1 023 | Pris og «View Product» stod på samme linje i 223 px brede kort: rækken var 188 px, pris 68, knap 121 → knappen lagde sig ind over prisen | `.sga-buyrow` er `flex; nowrap; space-between` og knappen kan ikke krympe | Pris på egen linje, knap i fuld bredde under (målt: pris y 4 059–4 087, knap y 4 097, 188 px bred) |
| 6 | 320 | Produkttitlen var **54 px bred** — én bogstav pr. linje | Købsboksen er fire grupper inde i hinanden med 20 px luft hver: 224 → 180 → 138 → 96 → **54 px** | Indre grupper uden vandret luft, yderste ramme 14 px → titlen **186 × 76 px** |

Punkt 4 er den lærerigste: `!important` slår ikke altid. Temaets regel havde **højere
specificitet** (én klasse + ét attribut mod min ene klasse), så min regel tab selv om begge havde
`!important`. Løsningen var at matche deres specificitet med `[class*="columns-"]` — ikke at
skrive `!important` igen.

## 2. Målingen efter

| Bredde | Forside | Butik | Produktside |
| --- | --- | --- | --- |
| 320 | header 73 · docH 8 574 · 0 overflow | 1 kolonne (278 px) · docH 11 100 | titel 186 × 76 · docH 8 920 · 0 overflow |
| 768 | header **73** (var 171) · docH **6 906** | **2 kolonner** (341 px) · docH **6 767** (var 18 343) | titel **35,3 px** (var 17,6) · docH 5 798 |
| 800 | header 73 · docH **6 263** (var 11 633) · grid 3 kolonner | — | — |
| 1 024 | header 95 · 12 links · 0 overflow · docH 4 986 | 4 kolonner à 222 px · docH 3 119 | 2 kolonner à 425 px · docH 5 527 |
| 1 280 | header 95 · docH 5 122 (uændret) | 4 kolonner à 285,5 px | titel 351 × 168 · kolonner 553/553 · docH 4 899 (uændret) |

320 og 1 280 er **uændrede** — det er hele pointen med at scoping: rettelserne ligger i
`@media (min-width: 783px) and (max-width: 1023px)`, `@media (min-width: 600px) and (max-width:
1023px)` og `@media (max-width: 599px)`, så de to ender af skalaen ikke rører sig.

Mobilmenuen blev også prøvet ved 800 px: den åbner 800 × 1 500 px, viser alle 12 links, har en
44 px lukkeknap, låser `body` og lukker igen.

## 3. Ét forsøg der blev rullet tilbage

Mobilgalleriets 4:5-ramme (222 × 278 px) giver tomme bånd over og under et 4:3-print. Forsøget var
at sætte `aspect-ratio: auto` på rammen og billedet, men flex-containeren strakte så sliden til
**1 502 px** — samme fælde som desktop-galleriet 8/11, hvor `aspect-ratio: auto` gav 551 × 1 502.
Rammen står derfor urørt på mobil, og reglen er fjernet igen frem for at ligge som død CSS.
Fælden er generel: **`aspect-ratio: auto` på et flex-barn betyder »stræk til højeste søskende«**,
ikke »brug billedets forhold«.

## 4. Fund uden for opgaven

Forsidens varekort viser priser i **USD** (`$21.90+`, `$24.95`, `$26.95`), mens butikken kører
**DKK** (`kr.180.00` på produktsiden). Det er ikke rettet: priser og produktdata er ejerens flade,
og en ændring ville kræve en beslutning om hvilken valuta kortet skal vise. Det bør rettes sammen
med batch 4 (mockups), som venter på Printful-token eller fotos.
