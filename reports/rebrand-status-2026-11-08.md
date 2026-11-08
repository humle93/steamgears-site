# Om-mærkningen målt 8/11 2026 — hvad der er i effekt, og hvad der ikke er

_Kør: `python3 tools/rebrand_audit.py` (offentlig Store API, ingen token). Exit 1 = mindst ét krav mangler._

Målingen besvarer det spørgsmål roadmappet hidtil har besvaret på tillid: **er om-mærkningen faktisk på
sitet?** Svaret er delt — titlerne ja, kollektionerne nej.

## 1. Titlerne er i effekt

De 20 publicerede produkter bærer de nye titler fra gruppe A og B, ikke de gamle »Steampunk Unisex …«:

| ID | Live-titel 8/11 | Planlagt titel (1/11) |
| --- | --- | --- |
| 413 | Airship Storm — Vintage Engraving Hoodie | samme |
| 401 | Whale Airship — Vintage Voyage Hoodie | samme |
| 399 | Clockwork Octopus — Curiosity Hoodie | samme |
| 403 | Mechanical Elephant — Curiosity Hoodie | samme |
| 557 | Airship Armada — Vintage Engraving Tee | samme |

To nyere produkter (1255, 1282) bærer »— Steampunk Tee«. Det er gruppe C (steampunk-kapslen), hvor
steampunk-ordet med vilje bliver stående, så det er ikke en fejl.

## 2. Kollektionerne er **ikke** i effekt

Samme API, filtreret på kategori — to uafhængige mål (API-filter og arkivside) giver samme svar:

| Kollektion | Produkter 8/11 | Arkivside |
| --- | --- | --- |
| dark-academia | **0** | 200, tom |
| gothic-victorian | **0** | 200, tom |
| natural-history | **0** | 200, tom |
| vintage-maritime-prints | **0** | **404 — findes ikke** |
| witchy-tarot | **0** | 200, tom |
| steampunk-capsule | **0** | 200, tom |
| airship (gammel) | 5 | 200 |
| herbarium (gammel) | 5 | 200 |
| curiosities (gammel) | 6 | 200 |
| atelier (gammel) | 4 | 200 |

Alle 20 publicerede produkter ligger altså stadig kun i de fire oprindelige kollektioner. Tildelingen fra
1/11 er væk — formentlig overskrevet ved en katalog-synk eller genskabelse af termene (fem af de seks
termer findes stadig med tomme arkiver; `vintage-maritime-prints` er helt væk). Det er præcis den risiko
planen selv advarede om.

Det er en **regression, ikke udført arbejde der mangler**: omdøbningen og de 39 tekster er der, men
effekten af batch 2 er der ikke. Derfor er gentildelingen skilt ud som eget modul (0,4 t) i
`projektstatus.json` med en dokumenteret scope-korrektion — ikke gemt inde i de 8,5 t der allerede er brugt.

## 3. Billederne — 62 placeringer mangler

| Familie | Produkter | Billeder i dag | Krav | Mangler |
| --- | --- | --- | --- | --- |
| Tees | 4 | 6 | 5 | 14 |
| Hoodies | 4 | 4 | 5 | 16 |
| Art prints | 3 | 3 | 3 | 6 |
| Phone case | 1 | 1 | 3 | 2 |
| Småting | 8 | 8 | 4 | 24 |
| **I alt** | **20** | **22** | — | **62** |

0 af 20 produkter har 3 eller flere billeder. Arbejdsordren med filnavne, alt-tekster og rækkefølge ligger
i `docs/library/batch-4-arbejdsordre-2026-11-08.md`; selve billederne kan ikke produceres i repoet.

## 4. De to ejerhandlinger

1. **Gentildel kollektionerne (0,4 t).** Kræver skriveadgang (MCP eller wp-admin). Rækkefølge: opret
   `vintage-maritime-prints` igen, tildel de 20 publicerede (og de 4 kladder) efter planens grupper, kør
   `python3 tools/rebrand_audit.py` og bekræft at ingen kollektion er tom.
2. **Mockups (5 t).** Kræver Printful-adgang eller færdige billeder. Acceptkriterium: 0 produkter under
   3 billeder — i dag 20 af 20.

Indtil begge er gjort, er om-mærkningen **ikke** færdig, uanset hvor mange teksttimer der er brugt.
