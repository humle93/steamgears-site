# Kanal 1 — refresh af siderne der allerede har visninger

**Arbejdsordre, 10/11 2026.** Følger planen i
[`organisk-trafik-hurtigst-2026-11-10.md`](organisk-trafik-hurtigst-2026-11-10.md) §4, fase 0 punkt 3–4:
ret de fem watchlist-sider først, derefter titel-omskrivning på de sider der står på position 5–20.

**Hvorfor lige dem:** de har allerede bevist at Google viser dem (193 visninger, 2 klik, position 23,6).
En refresh virker i **2–6 uger**; en ny side tager **2–4 måneder**. Det er den eneste indsats i hele planen
hvor effekten kan måles før jul.

---

## 1. Grundlaget — hvad der er målt, og hvad der er antaget

| Datakilde | Hvad den giver | Status |
| --- | --- | --- |
| GSC 28 d (12/10–08/11), snapshot 09/11 | 2 klik, 193 visninger, 67 queries, 66 sider, position 23,6 | Brugt, [`weekly-performance-2026-11-09.md`](weekly-performance-2026-11-09.md) |
| Rank-drop-alarm 09/11 | 5 sider på watchlisten + 4 observerede fald | Brugt, samme dokument |
| `backup-2026-10-23/pages.json` + `posts.json` | Titel, meta/excerpt, ordtal, H2/H3, interne links pr. side | Brugt til at stille diagnosen |
| Frisk GSC-udtræk med side-position for alle 66 | Hvilke sider der reelt står på position 5–20 | **Mangler** — kræver ejerens OAuth, se §5 |

Antagelsen der bærer hele dokumentet: **faldet er ikke et indholdsproblem, men et positions- og SERP-problem.**
Siderne var på side 1–2 og er skubbet ned. Derfor er rækkefølgen: først teknisk/formel hygiejne (titel, meta,
interne links, schema), derefter indhold. Skriver vi indhold først, retter vi det forkerte lag.

---

## 2. De fem watchlist-sider — én arbejdsordre pr. side

Fælles for alle fem, jf. [`../seo.md`](../seo.md) §1–3: titel **≤ 62 tegn** med søgeordet inden for de første
50, meta **140–155 tegn**, mindst **to guides + den relevante hub** linket, ét H1, og schema der matcher
(HowTo på guides, FAQPage hvor FAQ findes). Ingen af siderne nævnes i `noindex`-listen.

### 2.1 `/diy-steampunk-goggles/` — den største enkeltgevinst

| Måltal | Nu | Før |
| --- | --- | --- |
| Visninger (28 d) | 14 | 56 |
| Klik | 0 | 2 |
| Position (query `diy steampunk goggles`) | 49 | 7 |
| Ord | 1 044 | — |
| Interne links **ud** | 1 (`/how-to-make-a-steampunk-costume/`) | — |
| Interne links **ind** | 7 | — |

**Diagnose.** Positionen er ikke faldet lidt, den er faldet fra side 1 til side 5 — det er et
intent-skift, ikke en indholdsdom. Google viser i dag video og Shopping på denne query, og siden har
hverken `HowTo`-schema i søgeresultatet eller et videopoint. Sideløbende er der en reel mangel:
kun ét internt link ud (reglen er to guides + hub), og ordtallet er højt nok til at bære en sammenligningstabel
uden at blive tynd.

**Ændringer, i denne rækkefølge**

1. **Titel:** `DIY Steampunk Goggles: Build Your Own in 30 Minutes` (51) →
   `How to Make Steampunk Goggles: 3 DIY Builds (2026)` (50). Queryen er »how to make«, ikke »build your own«,
   og årstallet matcher refresh-signalet.
2. **Meta:** `Three ways to make steampunk goggles — cardboard in 30 minutes, can lids for the classic
   look, or upgraded welding goggles. Costs, steps and styling.` (152)
3. **Ny H2 efter metode 3:** »Which Build Should You Pick?« med en tabel: tid, pris, sværhedsgrad, holdbarhed
   for de tre metoder. Tabellen er det AI-svar ikke kan give, og den er den mest sandsynlige kilde til et
   snippet.
4. **Interne links ud, tre stk.:** `/steampunk-accessories/` (»the rest of the accessory kit«),
   `/diy-vs-buy-steampunk-goggles/` (»if you would rather buy«), `/steampunk-costumes/` (hub).
5. **Indgående links:** `/steampunk-accessories/` linker allerede; tilføj ét fra `/steampunk-cosplay-on-a-budget/`
   (den har ingen goggles-link i dag) med ankerteksten »make the goggles yourself for under $5«.
6. **Schema:** `HowTo` findes — verificér at `step`-teksten matcher de tre metoder, og tilføj `totalTime`
   (PT30M / PT2H / PT3H).
7. **SERP-tjek før publicering:** søg queryen i én incognito-session og notér om video/Shopping/AI Overview
   dominerer. Dominerer de, er målet ikke position 1 men **at blive valgt inde i AI-svaret** — derfor
   tabellen og den korte »Short answer«-blok øverst.

**Accept-test (4 uger):** visninger ≥ 40, position ≤ 20, mindst 1 klik.

### 2.2 `/steampunk-costume-ideas/` — tættest på side 1

| Måltal | Nu | Før |
| --- | --- | --- |
| Visninger (28 d) | 13 | 37 |
| Query `steampunk costume ideas` | 23 visninger, 1 klik, **position 11,4** | 10,8 |
| Position, uge mod uge | 14,2 | 10,8 |
| Ord / H2 | 736 / 12 karakterer | — |

**Diagnose.** Dette er den side i hele porteføljen der står tættest på side 1 på en kommerciel query. Den
taber ikke fordi indholdet er dårligt, men fordi titlen lover »15+ ideer« mens konkurrenterne lover
»costume ideas« med billeder. 12 karakterer uden et eneste gruppebillede pr. karakter er den svageste del.

**Ændringer**

1. **Titel:** `15+ Steampunk Costume Ideas for Men and Women (With Shortcuts)` (62) →
   `Steampunk Costume Ideas: 15+ Looks for Men & Women` (50). Søgeordet flytter fra tegn 4 til tegn 1.
2. **Meta:** `Steampunk costume ideas for men and women: airship captain, mad scientist, sky pirate and 12
   more — with the key pieces and cheap shortcuts for each.` (152)
3. **Billede pr. karakter.** Hver af de 12+ karakterer skal have ét billede med alt-tekst der navngiver
   karakteren (»airship captain steampunk costume with goggles and brass buttons«). Det er den ændring der
   flytter mest for mindst arbejde på en visuel query.
4. **Ny H2 nederst:** »Costume Ideas by Budget« — under $50 / $50–150 / $150+. Link til
   `/steampunk-cosplay-on-a-budget/` og de produkter der bærer hvert niveau.
5. **Interne links ud:** `/steampunk-vs-dieselpunk/` (findes allerede via rotation-log) — tilføj
   `/steampunk-costumes/` (hub) og `/steampunk-accessories/`.
6. **Schema:** `ItemList` eller `FAQPage` — i dag intet af dem. FAQ-sektionen findes ikke; tilføj 4 spørgsmål
   fra PAA (»what do female steampunk characters wear«, »how do you dress steampunk for cheap«).

**Accept-test:** position ≤ 8, CTR ≥ 2 %, mindst 5 klik.

### 2.3 `/how-to-make-a-steampunk-costume/` — brud på egen regel

| Måltal | Nu | Før |
| --- | --- | --- |
| Visninger | 20 | 32 |
| Position | 20,8 | 9,2 |
| Ord | 1 131 | — |
| Interne links **ud** | **0** | — |

**Diagnose.** Siden har **nul** udgående interne links. Det er et direkte brud på
[`../seo.md`](../seo.md) §1 (»hvert indlæg linker mindst ét produkt og mindst to guides plus hub«) og den
mest sandsynlige forklaring på at en 1 131-ords guide med position 9,2 er faldet til 20,8: den lækker
autoritet i stedet for at samle den.

**Ændringer**

1. **Titel:** `How to Make a Steampunk Costume: A Complete Step-by-Step Guide` (62) →
   `How to Make a Steampunk Costume: Step-by-Step (2026)` (52). »A Complete« er fyld.
2. **Meta:** `How to make a steampunk costume step by step: the three-layer rule, thrifted base pieces, DIY
   goggles and the finishing touches that sell the look.` (150)
3. **Seks interne links ind i teksten** (ikke i en boks nederst): `/diy-steampunk-goggles/` i Step 3,
   `/steampunk-accessories/` i Step 4, `/steampunk-costume-ideas/` i introen, `/steampunk-costumes/` (hub) i
   afslutningen, `/steampunk-cosplay-on-a-budget/` i budget-afsnittet, og ét produkt via `/go/<id>/` i
   Step 2. Det lukker regelbruddet i samme redigering.
4. **HowTo-schema:** findes ikke i dag — tilføj med de fem step som `HowToStep`, `totalTime` PT4H og
   `tool`/`supply` fra materialelisten.
5. **Fakta-årstal:** »2026« i første afsnit og i et nyt afsnit »What Changed in 2026« (AI-søgning gør
   informationsklik dyre; derfor er guiden bygget som en gør-det-selv-ordre, ikke som en artikel).

**Accept-test:** position ≤ 12 inden 4 uger, og 6 udgående interne links verificeret i kilden.

### 2.4 `/steampunk-accessories/` — for lang titel og for få indgående links

| Måltal | Nu | Før |
| --- | --- | --- |
| Visninger | 8 | 15 |
| Position | 16,2 | 10,3 |
| Titel | 70 tegn | — |
| Indgående links | 2 (`/steampunk-costumes/`, `/steampunk-cosplay-gallery/`) | — |

**Diagnose.** Titlen er 70 tegn og bliver klippet i søgeresultatet — den vises som
»Steampunk Accessories: 25+ Must-Have Pieces (DIY + Where to…«. Det koster CTR på en side der ellers har
et godt H2-stillads (687 ord, syv sektioner, FAQ). Kun to sider linker til den.

**Ændringer**

1. **Titel:** `Steampunk Accessories: 25+ Must-Have Pieces (DIY + Where to Find Them)` (70) →
   `Steampunk Accessories: 25+ Pieces to Buy or DIY` (47). Tallet bliver stående, fordi det er løftet.
2. **Meta:** `Every steampunk accessory worth wearing — goggles, pocket watches, bracers, belts — with
   thrift-store DIY shortcuts and ready-to-buy picks.` (142)
3. **Indgående links:** tilføj fra `/steampunk-costume-ideas/` (efter hver karakter: »pieces that finish the
   look«) og `/how-to-make-a-steampunk-costume/` (Step 4). Mål: 4 indgående links.
4. **Priser i tabellen:** hver af de 25+ dele skal have et interval (fx »$8–25«). Det er den forskel der gør
   siden brugbar frem for beskrivende, og det er hvad AI-svar citerer.
5. **Interne links ud:** `/steampunk-costumes/` (hub) mangler — den skal ind i »Ready to Gear Up?«.

**Accept-test:** position ≤ 10, titel ≤ 62 tegn i SERP, 4 indgående links.

### 2.5 `/steampunk-vs-dieselpunk/` — nyeste fald, ingen indgående links i backup

| Måltal | Nu | Før |
| --- | --- | --- |
| Query `dieselpunk vs steampunk` | 10 visninger, position **16,4** | 10,0 |
| Visninger (side) | 9 | 16 |
| Klik | 0 | 1 |
| Rank-drop-flag | +6,4 positioner (eneste alarm over grænsen) | — |

**Diagnose.** Eneste side der udløste rank-drop-alarmen 09/11. Siden blev GEO-gennemgået 26/10 og fik
Short answer, sammenligning, 6 FAQ og workshop-notes — altså er indholdet ikke problemet. Det er
**query-intent**: brugerne søger »dieselpunk vs steampunk« (omvendt rækkefølge), og sidens titel og H1 siger
»Steampunk vs. Dieselpunk«. Plus nul indgående links i 23/10-backup (den er publiceret senere som post 798;
rotation-loggen viser at `/steampunk-costume-ideas/` linker til den).

**Ændringer**

1. **Titel:** `Steampunk vs. Dieselpunk: How to Pick Your Look (2026 Guide)` (60) — behold, men vend
   rækkefølgen i H1 og i første sætning: »Dieselpunk vs steampunk — …«, så begge ordstillinger dækkes.
2. **Meta:** `Steampunk vs dieselpunk: what changes in colour, materials and silhouette, how to pick the
   look that suits you, and how to build either on a budget.` (151)
3. **Sammenligningstabel** (farve, materialer, æra, signaturdetalje, typisk pris) øverst, før Short answer.
   Tabeller er det format AI-svar og featured snippets foretrækker.
4. **Indgående links:** `/steampunk-costumes/` (hub), `/how-to-make-a-steampunk-costume/` og
   `/dieselpunk-costume/`. I dag linker kun costume-ideas.
5. **SERP-tjek:** queryen har et dokumenteret AI Overview-loft; hvis AI-svaret står øverst, er delmålet at
   blive citeret, ikke at rykke til position 1.

**Accept-test:** position ≤ 12 inden 3 uger (den var 10,0 for to uger siden — det er en genopretning, ikke
et nybrud).

---

## 3. Titel-omskrivning på resten af position 5–20-gruppen

Kun fire queries har en dokumenteret position ≤ 15 i 09/11-snapshotet, så denne gruppe kan ikke fyldes ud
uden et friskt udtræk (§5). De fire der kan handles på nu:

| Query | Position | Side | Handling |
| --- | --- | --- | --- |
| `steampunk archetypes` | 3,3 (ny) | ingen dedikeret side | Flettes ind som H2 i `/steampunk-costume-ideas/` — ikke en ny URL, jf. gate 3 |
| `steampunk character archetypes` | 3,5, 1 klik | ingen dedikeret side | Samme H2; ankertekst »steampunk character archetypes« |
| `steampunk terms` | 8,3 | ingen dedikeret side | Egen side er berettiget (position 8,3 uden side = efterspørgsel); se kanal 5 |
| `steampunk costume ideas` | 11,4 | `/steampunk-costume-ideas/` | §2.2 |

`steampunk goggles` (21 visninger, position 30,2) og `steampunk gears` (13 visninger) er de to største
visningskilder uden en side der matcher queryen — begge er kandidater til kanal 5, ikke til refresh.

**Regel for gruppen:** en titel ændres kun hvis den i dag er over 62 tegn, lover noget andet end queryen,
eller mangler søgeordet i de første 50 tegn. Alt andet er churn, og churn nulstiller de 2–6 ugers
modningstid.

---

## 4. Rækkefølge, tid og gate

| # | Handling | Tid | Hvornår |
| --- | --- | --- | --- |
| 1 | `/how-to-make-a-steampunk-costume/` — seks interne links + HowTo-schema | 40 min | dag 1 |
| 2 | `/diy-steampunk-goggles/` — titel, meta, sammenligningstabel, links | 60 min | dag 1 |
| 3 | `/steampunk-accessories/` — titel (70 → 46), priser, indgående links | 30 min | dag 2 |
| 4 | `/steampunk-costume-ideas/` — billede pr. karakter, budget-H2, schema | 90 min | dag 2–3 |
| 5 | `/steampunk-vs-dieselpunk/` — H1-rækkefølge, tabel, indgående links | 30 min | dag 3 |
| 6 | `steampunk archetypes`-H2 ind i costume-ideas | 30 min | dag 3 |

**Gate 0 (dag 28, 08/12):** de fem sider skal have visninger ≥ 80 % af før-niveau (dvs. ≥ 45, 30, 26, 12, 13)
og mindst to af dem skal have flyttet position ind i top 10. Ellers undersøges SERP'en for den enkelte query
**før** der skrives mere indhold — det er gate 0's eneste formål.

**Måling:** GSC's egne 28-dages tal er den eneste kilde der tæller. Sammenlign 08/12 mod 08/11 på
*side*-niveau, ikke på siteniveau, og hold informations- og købsintention adskilt (fase 0 punkt 2).

---

## 5. Hvad denne arbejdsordre ikke kan, uden et friskt udtræk

- **Side-position for alle 66 sider.** 09/11-snapshotet har query-positioner og en watchlist, ikke en
  komplet side-til-position-tabel. Uden den kan §3 kun fyldes med de fire queries ovenfor.
- **CTR pr. side.** Kun sitegennemsnittet (1,0 %) er kendt; om en titelændring virkede kan derfor først
  afgøres på næste snapshot.
- **Om faldet er AI Overviews eller en teknisk fejl.** Begge dele forklarer et positionsfald. Første
  kontrol er billig: er siden stadig `index, follow`, i sitemap og uden canonical-fejl (S0.6).
- **Live-redigering.** Intet i denne ordre er ført ud på sitet herfra; alle ændringer skal indsættes af
  ejeren eller via MCP-adgangen, og hver side skal ses i browseren efter (typografi i hero-blokke og
  tabeller er ikke verificeret visuelt).

## 6. Kilder

- GSC Search Analytics 12/10–08/11 (snapshot 09/11) og GA4 559297232 — gengivet i
  [`weekly-performance-2026-11-09.md`](weekly-performance-2026-11-09.md)
- [`organisk-trafik-hurtigst-2026-11-10.md`](organisk-trafik-hurtigst-2026-11-10.md) §3–4 — kanalrangering
  og modningstider
- [`../seo.md`](../seo.md) §1–3 — titel-, meta- og linkregler
- `backup-2026-10-23/pages.json`, `posts.json` — titler, ordtal, H2/H3 og interne links pr. side
- [`seo-rotation-log.md`](seo-rotation-log.md), [`seo-regler-geo-optimering-log.md`](seo-regler-geo-optimering-log.md)
  — hvad der allerede er ændret på siderne
