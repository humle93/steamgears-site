# Produktlinkdækning 6/11 2026 — S1.5 målt på alle publicerede indlæg

Målingen besvarer S1.5: **har hver publiceret guide mindst ét produktlink, og går det gennem
`/go/<id>/`?** Den er lavet read-only mod det levende site og ændrer intet. Resultatet er ikke en
påstand om at linkene virker: hvert link er talt i den HTML, WordPress selv leverer.

## Metode

- `GET /wp-json/wp/v2/posts?per_page=100&_fields=id,slug,link,title,content` med neutral
  `steamgears-link-audit/1.0`-UA. Sitet svarede **30 publicerede indlæg** på én side.
- `/go/(\d+)/` og `/product/([^/"']+)/` talt som **unikke** links pr. indlæg, ikke som forekomster.
  Et link der står to gange i samme tekst tæller altså én gang.
- Et indlæg kaldes en **guide**, når det er vejledende indhold. Tre short stories (973, 993, 994) og
  produktlanceringen 572 holdes ude af guide-tallet, men står i tabellen for gennemsigtighed.
- Tabeldelen følger ikke redirects. Opfølgningen **7/11** fulgte derimod alle **31 unikke** `/go/<id>/`
  (53 forekomster; de 40 i tabellen er unikke pr. indlæg).

## Opfølgning 7/11: ruten virker, men 21 links er døde

Efter ejerens rewrite-flush er den gamle 200/journal-måling historisk. Alle **31 unikke** `/go/<id>/`
svarer nu **302**, og **0** ender i `/journal/`. Men kun **10** ender på en live produktside;
**21** ender på `?post_type=product&p=<id>` med **404**, fordi produkt-ID'et ikke findes i det
aktuelle katalog. Det er **35 af de 53 forekomster**.

Den fulde måling med de 21 døde IDs, linktekster og indlæg står i
[`go-links-verifikation-2026-11-07.md`](go-links-verifikation-2026-11-07.md). Kort: post 699
(`steampunk-outfit-ideas-for-women`) bærer 17 af de 21 døde IDs; **401** peger på en live hoodie,
men linkteksten siger “Tee”.

Ruten er altså ikke længere problemet. Det er kataloget og de gamle ID'er.

## Resultat

| Gruppe | Indlæg | `/go/`-links | Direkte `/product/`-links |
| --- | ---: | ---: | ---: |
| Kun `/go/` | 7 | 33 | 0 |
| Blandet | 2 | 7 | 6 |
| Kun `/product/` | 13 | 0 | 94 |
| Ingen produktlink | 8 | 0 | 0 |
| **I alt** | **30** | **40** | **100** |

### Guiderne

Af de **26 guides** har **9** mindst ét `/go/`-link. De **17** der ikke har, fordeler sig sådan:

- **10 guides med kun direkte produktlinks:** 897, 791, 891, 913, 751, 757, 875, 798, 17, 614.
  Til sammen **73** direkte links. De virker for kunden, men klikket kan ikke måles.
- **7 guides uden ét eneste produktlink:** 10, 30, 14, 15, 898, 974, 989.
  Her er der ingen købsvej i teksten, uanset om produktet findes i butikken.

### Alle 30 indlæg

Tabellen er delt efter status, så hver gruppe starter med sin egen header.

**Fuldt sporede — kun `/go/` (7)**

| ID | Slug | Titel | `/go/` | `/product/` |
| ---: | --- | --- | ---: | ---: |
| 699 | `steampunk-outfit-ideas-for-women` | Steampunk Outfit Ideas for Women: 12 Complete Looks (With Prices) | 21 | 0 |
| 805 | `3d-print-steampunk-parts` | 3D-Print Steampunk Parts: Gears, Goggle Frames and Props for Beginners | 2 | 0 |
| 988 | `best-steampunk-books` | The Best Steampunk Books: A Reading Guide for Every Kind of Reader | 2 | 0 |
| 615 | `casual-steampunk-outfits` | Casual Steampunk Outfits: Wear the Look Every Day | 2 | 0 |
| 803 | `closet-steampunk-outfits` | Closet Steampunk: Steampunk Accessories You Can Wear to Work | 2 | 0 |
| 1394 | `steampunk-corset-guide` | Steampunk Corset Guide: Underbust vs Overbust, Fit & Styling | 2 | 0 |
| 806 | `sustainable-steampunk` | Sustainable Steampunk: Thrift and Upcycle Your Costume | 2 | 0 |

**Blandet — både `/go/` og direkte links (2)**

| ID | Slug | Titel | `/go/` | `/product/` |
| ---: | --- | --- | ---: | ---: |
| 804 | `steampunk-gift-guide-2026` | Steampunk Gift Guide 2026: Gifts for Every Kind of Steampunk Fan | 4 | 5 |
| 8 | `how-to-make-a-steampunk-costume` | How to Make a Steampunk Costume in a Weekend (2026) | 3 | 1 |

**Kun direkte `/product/`-links (13)**

| ID | Slug | Titel | `/go/` | `/product/` |
| ---: | --- | --- | ---: | ---: |
| 897 | `steampunk-outfit-ideas-men` | Steampunk Outfit Ideas for Men: 12 Complete Looks (2026) | 0 | 17 |
| 791 | `steampunk-halloween-costume-women` | Steampunk Halloween Costumes for Women: 12 Last-Minute Looks | 0 | 14 |
| 891 | `steampunk-halloween-costume-men` | Steampunk Halloween Costumes for Men: 10 Last-Minute Looks (2026) | 0 | 12 |
| 973 | `the-clockwork-herbarium-short-story` | The Clockwork Herbarium: A Short Story | 0 | 12 |
| 572 | `curiosities-vol-3-live` | Curiosities Vol. 3 Is Live — 8 New Clockwork Tees | 0 | 8 |
| 913 | `steampunk-costume-for-women` | Steampunk Costume for Women: Complete Look Guide (2026) | 0 | 8 |
| 751 | `pirate-cosplay-for-beginners` | Pirate Cosplay for Beginners: Build a Steampunk Pirate Look | 0 | 6 |
| 757 | `how-to-build-a-steampunk-costume-for-under-100-complete-2027-workshop-plan` | How to Build a Steampunk Costume for Under $100 (Complete 2027 Workshop Plan) | 0 | 5 |
| 875 | `dieselpunk-costume` | How to Build a Dieselpunk Costume: The Complete DIY Guide (2026) | 0 | 4 |
| 798 | `steampunk-vs-dieselpunk` | Steampunk vs. Dieselpunk: How to Pick Your Look (2026 Guide) | 0 | 4 |
| 17 | `steampunk-cosplay-on-a-budget` | Steampunk Cosplay on a Budget: The $40 Full Outfit Formula | 0 | 2 |
| 614 | `diy-vs-buy-steampunk-goggles` | DIY vs. Buy: Steampunk Goggles Compared (2026) | 0 | 1 |
| 993 | `the-clockwork-seer-short-story` | The Clockwork Seer: A Steampunk Short Story | 0 | 1 |

**Ingen produktlink (8)**

| ID | Slug | Titel | `/go/` | `/product/` |
| ---: | --- | --- | ---: | ---: |
| 10 | `diy-steampunk-goggles` | DIY Steampunk Goggles: Build Your Own in 30 Minutes | 0 | 0 |
| 30 | `steampunk-accessories` | Steampunk Accessories: 25+ Must-Have Pieces (DIY + Where to Find Them) | 0 | 0 |
| 14 | `steampunk-cosplay-for-beginners` | Steampunk Cosplay for Beginners: Your First Outfit, Explained | 0 | 0 |
| 15 | `steampunk-costume-ideas` | 30+ Steampunk Costume Ideas: Airship Captains, Inventors & Explorers | 0 | 0 |
| 898 | `steampunk-couple-costumes` | Steampunk Couple Costumes: 10 Pairs Ready to Buy (2026) | 0 | 0 |
| 994 | `the-last-flight-of-the-meridian-short-story` | The Last Flight of the Meridian: A Dieselpunk Short Story | 0 | 0 |
| 974 | `what-is-dieselpunk` | What Is Dieselpunk? A Guide to the Diesel & Deco Aesthetic | 0 | 0 |
| 989 | `what-is-gaslamp-fantasy` | What Is Gaslamp Fantasy? Victorian Magic, Explained | 0 | 0 |

## Rækkefølge for rettelsen

0. **Ruten er fixed 7/11:** 31/31 `/go/`-links giver 302. Næste spærring er de 21 døde produkt-ID'er
   (35 forekomster); ret dem efter [verifikationen](go-links-verifikation-2026-11-07.md), før nye links
   konverteres.
1. **Konvertér de 73 direkte links i de 10 guides** til `/go/<produkt-id>/`. Produkt-id'et slås op i
   butikkens eget API, ikke gættes. Det er den største gevinst: alle linkene findes allerede, og kun
   `href`-værdien skal ændres.
2. **Tilføj ét kontekstuelt `/go/`-link i de 7 guides uden produktlink.** Det skal være et produkt
   teksten allerede taler om — ikke en produktboks klistret på.
3. **Tag stilling til de tre short stories og produktlanceringen.** De er ikke guides, men 973 og 572
   har mange direkte links. Samme konvertering kan måles som en ekstra gevinst.
4. **Mål igen** med samme forespørgsel. S1.5 er først lukket, når hver guide har mindst ét `/go/`-link
   og ingen guide har et direkte `/product/`-link tilbage.

## Hvad målingen ikke beviser

- At et produkt stadig er publiceret og på lager. Målingen tæller links, ikke produktstatus.
- At de 17 guides uden `/go/` er dårlige. Flere af dem kan med vilje være historiefortælling; derfor
  står short stories og lanceringen separat.
- At rettelsen kan laves herfra. Der findes **ingen** `WP_USER`/`WP_APP_PASS`, `MCP_ACCESS_TOKEN` eller
  `NOVAMIRA_REFRESH_TOKEN` i denne sandkasse, og S1.5 kræver skrivning til det levende site.

## Filer

- Roadmap-punktet: [`../roadmap.md`](../roadmap.md) §S1.5.
- Verifikationen 7/11: [`go-links-verifikation-2026-11-07.md`](go-links-verifikation-2026-11-07.md).
- Den tidligere måling af de ti ældre guides:
  [`guide-links-live-2026-11-05.md`](guide-links-live-2026-11-05.md).
- Linkplanen: [`guide-links-aeldre-2026-11-03.md`](guide-links-aeldre-2026-11-03.md).
