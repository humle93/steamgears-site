# Om-mærkningen målt 8/11 2026 — tildelingen er nu gennemført

_**Opdateret efter den vellykkede skrivning:** kollektionerne er tildelt gennem Novamira-MCP. Tabellerne nedenfor
er den første måling (før skrivningen) og står som historik; resultatet efter står i §2b og i
[`mcp-skrivevej-novamira-cli-2026-11-08.md`](../docs/library/mcp-skrivevej-novamira-cli-2026-11-08.md)._

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

## 2. Kollektionerne var **ikke** i effekt (før skrivningen kl. 19.0x)

Samme API, filtreret på kategori — to uafhængige mål (API-filter og arkivside) giver samme svar:

| Kollektion | Produkter 8/11 | Arkivside |
| --- | --- | --- |
| dark-academia | **0** | 200, tom |
| gothic-victorian | **0** | 200, tom |
| natural-history | **0** | 200, tom |
| vintage-maritime (ID 97) | **0** | 200, tom |
| witchy-tarot | **0** | 200, tom |
| steampunk-capsule | **0** | 200, tom |
| airship (gammel) | 5 | 200 |
| herbarium (gammel) | 5 | 200 |
| curiosities (gammel) | 6 | 200 |
| atelier (gammel) | 4 | 200 |

Alle 20 publicerede produkter ligger altså stadig kun i de fire oprindelige kollektioner. Tildelingen fra
1/11 er væk — formentlig overskrevet ved en katalog-synk. Alle seks termer findes (ID 94–99) og står på
`count: 0`; den maritime term hedder `vintage-maritime`, ikke `vintage-maritime-prints`, og det var den
forskel der først blev læst som »termen findes ikke«. Det er præcis den synk-risiko planen selv advarede om.

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

## 2b. Efter skrivningen — 5 af 6 kollektioner har indhold

| Kollektion | Produkter nu |
|---|---:|
| dark-academia | **5** |
| gothic-victorian | **3** |
| natural-history | **2** |
| vintage-maritime | **1** |
| steampunk-capsule | **3** |
| witchy-tarot | **0** — produkterne 567/395/411 ligger i papirkurven |

11 publicerede produkter blev tildelt i én `novamira/execute-php`-kørsel; genkørt plan melder »0 produkter
mangler«, og titlerne er uændrede. `witchy-tarot` kræver en ejerbeslutning om genoplivning — ikke teknik.

## 4. De to ejerhandlinger

1. **Gentildel kollektionerne (0,4 t) — ét klik, når et application password findes.** Værktøjet
   `tools/rebrand_apply.py` gør tildelingen (plan uden credentials, `--apply` med, `--verify` bagefter), og
   workflowen *Rebrand apply* kører det i CI. Den eneste ejerhandling er at oprette et application password
   og lægge det ind som repo-secrets — se trinnene nedenfor.
2. **Mockups (5 t).** Kræver Printful-adgang eller færdige billeder. Acceptkriterium: 0 produkter under
   3 billeder — i dag 20 af 20.

### Forsøgt fra CI: kørslen rapporterer selv at der ikke findes credentials

Workflowen *Rebrand apply* kørte på commit `bdb71a0` og skrev
[`rebrand-apply-log.md`](rebrand-apply-log.md) i repoet: planen (11 produkter, 14 placeringer), apply-trinnet
(»ingen credentials — tildelingen er ikke forsøgt«) og linjen **Credentials sat som repo-secrets: nej**.
Det er ikke en antagelse — det er kørslens eget svar, offentligt læsbart. Repository-secrets er altså tomme,
og hverken agenten eller CI kan skrive til sitet uden dem.

### Den konkrete ejerhandling (90 sekunder)

1. Log ind på `steamgears.eu/wp-admin` som admin.
2. **Brugere → Profil → Application Passwords**: navn fx `rebrand-ci`, klik *Add New Application Password*,
   og kopiér den viste kode (den vises kun én gang).
3. GitHub → repoet → **Settings → Secrets and variables → Actions**: opret `WP_USER` (dit brugernavn) og
   `WP_APP_PASS` (koden fra trin 2). Koden skal ikke i chat, i repoet eller i en fil.
4. **Actions → Rebrand apply → Run workflow** med `apply` slået til. Kørslen tildeler, verificerer og fejler
   hvis en kollektion stadig er tom.
5. Kør `python3 tools/rebrand_audit.py` (eller se kørslens verify-trin): **0 tomme kollektioner** er
   acceptkriteriet.

Indtil begge er gjort, er om-mærkningen **ikke** færdig, uanset hvor mange teksttimer der er brugt.
