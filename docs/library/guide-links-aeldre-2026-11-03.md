# Link-plan: de ældre publicerede guides — 3/11 2026

De fire nye guides fik `/go/<id>/`-links og en fast struktur. De ældre publicerede guides har ikke fået samme
behandling. **4/11 2026 er alle fyrre rækker afgjort:** 34 links står nu på en publiceret side og er målt der,
og 6 af planens produkter nævnes slet ikke i den publicerede artikel og afventer en omskrivning af den. Hver
række har stadig både ID og pris — de otte der før stod som `pris mangler`, fik deres pris målt i den levende
butik 3/11 2026, se [`priser-fra-store-api-2026-11-03.md`](priser-fra-store-api-2026-11-03.md).

Målingen ligger i [`guide-links-live-2026-11-05.md`](guide-links-live-2026-11-05.md): hver guide er hentet, hvert
link fundet i HTML'en, og hvert `/go/<id>/` fulgt til det produkt det lander på. Det er den fil
`link_plan_check` holder `indsat`-rækkerne op imod, så et "indsat" her altid har et opslag bag sig. **5/11**
blev de sidste direkte produktlinks konverteret, så der nu står **54 sporede links** på de ti guides — planens
40 rækker er altså et mindre udsnit end det arbejde der faktisk er gjort (se §1).

Link-kolonnen er stien på sitet (`/go/<id>/`). Produkt-ID'er: [`om-maerkning-dark-academia-2026-11-01.md`](om-maerkning-dark-academia-2026-11-01.md) (39 designs
plus appendix med de seks produkter uden for dem). Priser:
[`valuta-og-priser-2026-11-02.md`](valuta-og-priser-2026-11-02.md). Intet er gættet; hver række nedenfor er
kontrolleret af `tools/link_plan_check.py`, og rækker mærket `indsat` tjekkes **mod målingen af det levende
site**, mens `kladde` tjekkes mod filen i repoet.

## 1. Outfit-guiden — den publicerede artikel og filen i repoet

Her var planen skrevet mod den forkerte tekst. `/steampunk-outfit-ideas-for-women/` findes nemlig **to steder**,
og de er ikke samme artikel:

- **Den publicerede artikel** (post 699, "12 Complete Looks (With Prices)") har 12 andre looks — Airship Captain,
  Victorian Inventress, Cosy Engineer — og 34 direkte `/product/`-links, men **nul** `/go/`-links. Den har 14 af
  planens 20 produkter. 4/11 blev de 14 konverteret til `/go/<id>/` ved første omtale: samme synlige tekst,
  samme mål, nu med sporing. **5/11 blev de resterende 20 hrefs konverteret** (15 produkter, 21 unikke
  produkt-ID'er i alt på siden), så **hele siden er sporet: 34 af 34 produktlinks**. Kun `href`-værdierne blev
  ændret — resten af HTML'en er byte-identisk, og ejerens billedbyt samme dag (juno.page-CDN → lokal upload af
  `victorian-underbust-corset.jpg`) blev bevaret, fordi indholdet blev læst før det blev skrevet.
- **Omskrivningen i repoet** ([`steampunk-outfit-ideas-for-women.md`](steampunk-outfit-ideas-for-women.md)) er en
  anden tekst med 12 andre looks og alle 20 produkter linket, men den er aldrig publiceret.

De 6 produkter — 559 Clockwork Fox tee, 719 Sky Corsair print, 567 Clockwork Seer tee, 561 Cabinet of
Curiosities tee, 403 Mechanical Elephant hoodie og 399 Clockwork Octopus hoodie — nævnes hverken i den
publicerede artikel eller i det snapshot af guiden der ligger i repoet (upstream erstattede 4/11 filen med en
kildefil uden links). De har derfor status `klar`: der er intet sted at sætte linket hen. Den gamle omskrivning
med alle 20 links findes kun i git-historikken. De bliver `indsat` den dag guiden omskrives og produkterne
kommer med — indtil da tæller de hverken som indsatte eller som blokerede.

| Guide | Link | Linktekst | Pris | Status |
| --- | --- | --- | --- | --- |
| `/steampunk-outfit-ideas-for-women/` | /go/487/ | Victorian corset top | $44.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/497/ | Medieval Assassin coat | $39.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/481/ | Steampunk top hat with removable goggles | $34.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/489/ | Steampunk overbust corset | $59.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/493/ | Wine Red velvet tailcoat | $64.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/479/ | Vintage Driver goggles | $29.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/491/ | Gothic PU overbust corset | $64.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/485/ | Gothic bowler top hat | $39.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/477/ | Copper Pilot goggles | $24.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/559/ | Clockwork Fox tee | $26.95 | `klar` |
| `/steampunk-outfit-ideas-for-women/` | /go/555/ | Nautilus Voyage tee | $26.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/475/ | Steampunk Aviator goggles | $24.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/409/ | Gear Corset Lady hoodie | $49.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/495/ | Victorian Vampire tailcoat | $39.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/483/ | Time Traveler top hat | $49.95 | `indsat` |
| `/steampunk-outfit-ideas-for-women/` | /go/719/ | Sky Corsair print | $21.90 | `klar` |
| `/steampunk-outfit-ideas-for-women/` | /go/567/ | Clockwork Seer tee | $26.95 | `klar` |
| `/steampunk-outfit-ideas-for-women/` | /go/561/ | Cabinet of Curiosities tee | $26.95 | `klar` |
| `/steampunk-outfit-ideas-for-women/` | /go/403/ | Mechanical Elephant hoodie | $49.95 | `klar` |
| `/steampunk-outfit-ideas-for-women/` | /go/399/ | Clockwork Octopus hoodie | $49.95 | `klar` |

## 2. Indsat i de syv guides der kun findes på sitet

Ingen af disse guides har en fil i repoet, så der er ikke noget at tjekke mod — kun det levende site. Alle
tolv rækker blev 4/11 indsat i brødteksten ved første omtale af stykket, og hver af dem står i
målingen med `ja`.

| Guide | Link | Linktekst | Pris | Status |
| --- | --- | --- | --- | --- |
| `/how-to-make-a-steampunk-costume/` | /go/477/ | Copper Pilot goggles | $24.95 | `indsat` |
| `/how-to-make-a-steampunk-costume/` | /go/481/ | Steampunk top hat with removable goggles | $34.95 | `indsat` |
| `/how-to-make-a-steampunk-costume/` | /go/809/ | Steampunk costume checklist | gratis | `indsat` |
| `/3d-print-steampunk-parts/` | /go/479/ | Vintage Driver goggles | $29.95 | `indsat` |
| `/best-steampunk-books/` | /go/728/ | First Mate's Oath print | $21.90 | `indsat` |
| `/best-steampunk-books/` | /go/721/ | Kraken Widow print | $21.90 | `indsat` |
| `/casual-steampunk-outfits/` | /go/563/ | Ink and Steam Muse tee | $26.95 | `indsat` |
| `/casual-steampunk-outfits/` | /go/405/ | Clockwork Owl hoodie | $49.95 | `indsat` |
| `/closet-steampunk-outfits/` | /go/561/ | Cabinet of Curiosities tee | $26.95 | `indsat` |
| `/closet-steampunk-outfits/` | /go/483/ | Time Traveler top hat | $49.95 | `indsat` |
| `/sustainable-steampunk/` | /go/809/ | Steampunk costume checklist | gratis | `indsat` |
| `/build/tools-and-materials/` | /go/809/ | Steampunk costume checklist | gratis | `indsat` |

## 2b. Dækning pr. guide — ti guides, fyrre rækker

Planen har **ti** guides: **ni** fra 2/11-planen plus outfit-guiden. Det er de samme ti som `link_plan_check`
tæller i sin første linje — antallet i tabellen her, i værnet og i rapporten er det samme. Kolonnen Status tæller
de enkelte rækker, så outfit-guidens 20 er delt: 14 `indsat` på den publicerede side, 6 `kladde` i omskrivningen.

| Guide | Antal | Produkt-ID'er | Status | ID-kilde |
| --- | ---: | --- | --- | --- |
| `/steampunk-outfit-ideas-for-women/` | 20 | 399, 403, 409, 475, 477, 479, 481, 483, 485, 487, 489, 491, 493, 495, 497, 555, 559, 561, 567, 719 | `14 indsat + 6 klar` | mapping (39 designs) |
| `/how-to-make-a-steampunk-costume/` | 3 | 477, 481, 809 | `indsat` | mapping |
| `/3d-print-steampunk-parts/` | 2 | 479, 766 | `indsat` | 766: appendix + målt pris |
| `/best-steampunk-books/` | 2 | 728, 721 | `indsat` | mapping |
| `/casual-steampunk-outfits/` | 2 | 563, 405 | `indsat` | mapping |
| `/closet-steampunk-outfits/` | 2 | 561, 483 | `indsat` | mapping |
| `/sustainable-steampunk/` | 2 | 809, 767 | `indsat` | 767: appendix + målt pris |
| `/build/tools-and-materials/` | 2 | 809, 767 | `indsat` | 767: appendix + målt pris |
| `/build/weathering/` | 1 | 767 | `indsat` | 767: appendix + målt pris |
| `/steampunk-gift-guide-2026/` | 4 | 940, 941, 942, 968 | `indsat` | appendix + målte priser |

20 + 3 + 2 + 2 + 2 + 2 + 2 + 2 + 1 + 4 = **40 rækker**, som er det `link_plan_check` rapporterer.

### De to stier: fra «pris mangler» til «klar»

- **`/build/weathering/`** har `/go/767/` (Digital Blueprint: Victorian Corset & Vest PDF). ID'et er sporet til
  [`drafts-udgivet-2026-11-02.md`](drafts-udgivet-2026-11-02.md) og
  [`meta-fixes-og-produktlinks-2026-11-02.md`](meta-fixes-og-produktlinks-2026-11-02.md) §3 og står i appendix
  i mappingen. Prisen — **$6.90** — blev målt i butikken 3/11, så rækken er nu `klar` som de andre.
- **`/steampunk-gift-guide-2026/`** har fire links — 940 (Clockwork Seer pendant, **$89.00**), 941 (The Aeronaut
  pocket watch, **$149.00**), 942 (Ink and Steam bracelet, **$69.00**) og 968 (Nightshade Apothecary art print,
  **$29.95**). Alle fire er sporet til `meta-fixes-og-produktlinks-2026-11-02.md` §3 (og 968 også til
  [`butik-fund-2026-11-02.md`](butik-fund-2026-11-02.md)) og står i appendix med målt pris.

**Ingen række i planen er uden ID eller pris.** Alle ti guides har mindst ét `/go/<id>/`, hvert ID peger på en
fil i repoet, og hver pris kan slås op i butikken. Det der mangler er en redigering — ikke en kortlægning og
ikke et opslag.

## 3. Priser målt i butikken — de seks produkter uden for de 39 designs

De seks produkter nedenfor var ikke i ID-mappingen; de er lagt ind i
[appendix i mappingen](om-maerkning-dark-academia-2026-11-01.md#appendix--produkter-uden-for-de-39-designs-tilføjet-311-2026)
med den kilde i repoet der nævner dem. De havde ingen række i pristabellen fra 2/11, så rækkerne stod først med
`—`. **3/11 blev priserne målt i den levende butik** gennem Store API (offentlig læsning, 200 uden credential),
så rækkerne er nu `klar` som de øvrige — målingen og hele produkttabellen står i
[`priser-fra-store-api-2026-11-03.md`](priser-fra-store-api-2026-11-03.md).

| Guide | Link | Linktekst | Pris | Status |
| --- | --- | --- | --- | --- |
| `/3d-print-steampunk-parts/` | /go/766/ | Utility belt and holster | $24.90 | `indsat` |
| `/build/weathering/` | /go/767/ | Victorian corset blueprint PDF | $6.90 | `indsat` |
| `/build/tools-and-materials/` | /go/767/ | Victorian corset blueprint PDF | $6.90 | `indsat` |
| `/sustainable-steampunk/` | /go/767/ | Victorian corset blueprint PDF | $6.90 | `indsat` |
| `/steampunk-gift-guide-2026/` | /go/940/ | Clockwork Seer pendant | $89.00 | `indsat` |
| `/steampunk-gift-guide-2026/` | /go/941/ | The Aeronaut pocket watch | $149.00 | `indsat` |
| `/steampunk-gift-guide-2026/` | /go/942/ | Ink and Steam bracelet | $69.00 | `indsat` |
| `/steampunk-gift-guide-2026/` | /go/968/ | Nightshade Apothecary print | $29.95 | `indsat` |

Priserne er ikke gættet: `prices.price` fra Store API er minor units, så **2995 → $29.95**. Alle 31 produkter i
planen findes i butikken, og de 25 priser der allerede stod i pristabellen stemte **25 af 25** — målingen
bekræfter altså også det der var skrevet før.

**Status pr. guide:** se dækningstabellen i §2b — ti guides, fyrre rækker, alle med ID og pris. 34 er `indsat`
og målt på siden; 6 er `klar`, fordi artiklen ikke nævner de produkter (se §1).


## 4. Strukturen der følger med

1. **Linket sidder ved første omtale** af stykket i brødteksten. Ingen boks i bunden, ingen "relaterede produkter".
2. **Højst ét link pr. produkt pr. guide** — også når guiden nævner varen to gange.
3. **Hub og klynge i bunden:** `/steampunk-costumes/` plus de fire 2026-guides, så den gamle guide ikke er en
   isoleret side.
4. **`/build/*` er hubs**, ikke guides: der linkes i brødteksten, ikke i en produktblok.

## 5. Verifikation

`python3 tools/link_plan_check.py` tjekker hver række: guide-stien, at `/go/<id>/` findes i mappingen (inkl.
appendix), at linkteksten deler ord med produktnavnet, at prisen findes i pristabellen **eller i de målte
priser** (eller er `gratis` for 809), og at status er gyldig. Status betyder nu noget forskelligt:

| Status | Kravet |
| --- | --- |
| `indsat` | `/go/<id>/` står i målingen for den guide — altså på den publicerede side |
| `kladde` | `/go/<id>/` står i den fil guiden bor i repoet, og guiden er ikke publiceret (ingen rækker bruger den nu — se §1) |
| `klar` | ingen af delene — rækken venter på at guiden nævner produktet, eller på adgang |

`—` er ikke længere en gyldig pris, og `pris mangler` er ikke længere en gyldig status: begge er afløst af
målingen 3/11. `--selftest` har seksten fixtures, der beviser at værnene fanger en opdigtet pris, en tom pris,
den afskaffede status, et ukendt id, et forkert produktnavn, en dublering, en ukendt status, en tom plan, et
påstået-indsat link der ikke står i filen, og et `indsat` der ikke står i målingen.

Målingen laves om med `python3 tools/live_link_report.py --dato "<dato>"`; `--check` fejler, hvis dokumentet
ikke længere matcher det levende site. Det er med vilje: redigeres en side, skal målingen laves om, før planen
må kalde linket indsat.

## 6. Ruten `/go/<id>/` — målt 3/11, rettet i plugin 7.32.0

Ruten fandtes allerede, men den slog kun op i `sgap_queue`. Produkt-ID'er gav derfor `302` til **forsiden**:
`/go/477/`, `/go/767/`, `/go/940/` og `/go/809/` landede alle på `https://steamgears.eu/` med `x-redirect-by:
Steamgears`. Det er rettet i plugin **7.32.0**, hvor et ID uden kø-post slås op som produkt og redirecter til
permalinken; ukendte ID'er går stadig til forsiden. Versionen er aktiv på sitet (målt 4/11), og alle 34
indsatte links svarer `302` til det rigtige produkt — se målingen.

Derfor bliver linkformatet i denne plan stående: `/go/<id>/` virker som produktkortlink uden at der skal
oprettes en Redirection-gruppe. Diagnosen, kilden og kontrolkommandoen står i
[`go-ruten-produktlinks-2026-11-03.md`](go-ruten-produktlinks-2026-11-03.md).
