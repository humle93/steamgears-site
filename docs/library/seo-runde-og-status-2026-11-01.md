# SEO-optimeringsrunde + projektstatus — 1/11 2026

Kørt autonomt efter «optimer efter nyeste SEO-standarder, få siden gjort færdig». Alt nedenstående er udført og verificeret mod den live side; intet er planer der mangler at blive kørt.

## 1. Målt udgangspunkt

108 URL'er fra sitemap crawlet og auditeret (status, title, description, canonical, H1, schema, ord, vægt, svartid).

| Måling | Før | Nu |
|---|---|---|
| Sider der svarer 200 | 108/108 | 108/108 |
| Manglende meta description | 3 | **0** |
| Description uden for 70–165 tegn | 15 | **4** (125–133 tegn, inden for grænsen) |
| Titler over 62 tegn | 19 | **0** |
| Sider med noindex-fejl (opt-out-side indekserbar) | 1 | **0** |
| Døde interne links (forekomster) | 39 | **0** |
| Døde link-mål i de 15 berørte sider | 24 | **0** |
| Produktbilleder med forældet alt-tekst | 39 | **0** |
| Schema (Organization, WebSite, BreadcrumbList, Product) | 108/108 | 108/108 |

## 2. Hvad der blev ændret

**Meta (23 ændringer).** 18 sider fik ny SEO-titel og meta description, skrevet til længdekravene og med søgeordet først. 5 kategorisider (4 blog-kategorier + `digital-downloads`) fik term-beskrivelser, som Rank Math bruger som meta description. `/opt-out-preferences/` er sat til noindex — den er en cookie-side uden søgeværdi og lå åbent i indekset.

**Døde links (42 rettelser i 15 indlæg).** Alle 24 døde mål er omskrevet til fungerende URL'er:

- 4 rene slug-fejl: `/product-category/goggles/` → `steampunk-goggles`, `posters-wall-art` → `posters-prints`, `steampunk-top-hats` → `accessories`, `/refund-and-returns/` → `/refund_returns/`
- 6 kladde-produkter → det tilsvarende live-produkt (fx victorian-underbust-corset → Victorian Lace-Up Corset Top, brass-rivet-goggles → Copper Pilot Goggles)
- 12 skraldespands-produkter → nærmeste live-produkt hvor motivet findes (clockwork skull, compass mandala, sun compass, clockwork heart, whale airship, vampire hunter), ellers den relevante kollektion
- 2 produkter der aldrig har eksisteret → live-ækvivalent

Efter rettelsen er der **0 døde mål** i de 15 sider, tjekket ved at crawle dem igen og teste hvert eneste linkmål. Det gør S0.4 (301-map for `/start-here/` m.fl.) langt mindre presserende: lækagen sad i brødteksten, ikke i de fem vanity-URL'er.

**Alt-tekst (39 billeder).** Alle produkters hovedbillede havde alt-tekst med det gamle navn ("Clockwork Owl - Steampunk Unisex Hoodie"). De er opdateret til de nye titler, så billedsøgning og tilgængelighed matcher omdøbningen.

**Cache** purget efter hver batch; alle ændringer verificeret i den rendererede HTML.

## 3. Performance — diagnosticeret, ikke løst

Det er den største tilbageværende SEO-risiko, og den kan **ikke** løses via MCP.

| Måling | Værdi |
|---|---|
| Svartid, median | 2,1–3,0 s |
| Værste side | 13,7 s (`/the-clockwork-herbarium-short-story/`) |
| Produktside, HTML | 458 KB |
| Heraf inline CSS | **151–153 KB** |
| Heraf inline JS (produktside) | **166 KB** |
| Script-tags på en produktside | **105** |
| Eksterne CSS-filer | 0 (alt er inline) |
| Gzip | slået til |
| Billeder | srcset + lazy-load findes, ~243 KB pr. billede |

W3TC-status: `pgcache` ON, `objectcache` ON, `browsercache` ON — men `minify` **off**, `lazyload` **off**, `dbcache` **off**, `cdn` off. Der findes ingen MCP-metode til at skrive W3TC-konfiguration, så minify/lazyload/db-cache skal slås til i wp-admin. Det er også der, de 151 KB inline CSS og de 105 scripts skal fjernes (de kommer fra tema/plugins, ikke fra indhold).

**Anbefalet rækkefølge:** 1) W3TC → Minify: aktiver CSS+JS, undlad «combine», test i inkognito. 2) Lazyload på. 3) DB-cache på. 4) Find de 105 scripts — sandsynligvis WooCommerce-blocks + elementor-lignende plugins — og deaktiver det der ikke bruges. Forventet effekt: 458 KB → ca. 120–160 KB HTML og TTFB under 1 s på cache-hit.

## 4. Plugins og «cc»

`wp_get_plugins` er **læse-adgang** — der findes ingen opdateringsfunktion i MCP, så plugin-opdateringer kan ikke køres herfra. Status fra audit 23/10: 48 plugins, 16 bagud, 3 mangler en sikkerhedsrettelse. De skal opdateres i wp-admin, og helst efter en backup (der ligger en fuld backup fra 23/10 i repoet).

AI Engine er undtagelsen: 3.8.9, inaktiv, MCP-OAuth-ruterne svarer 404. Den sag er lukket.

## 5. Projektstatus pr. modul

Vægte fra [site-roadmap-2026-11-01.md](../site-roadmap-2026-11-01.md). «Færdig» tæller kun det der er udført og verificeret — ikke det der er planlagt.

| Modul | Vægt | Færdig | % | Resterende |
|---|---|---|---|---|
| **S0 — brudt eller akut** | 2,6 d | 1,9 d | **73 %** | 0,7 d (Health Check-afklaring, Coming Soon-tjek, 5 vanity-301) |
| **S1 — indhold der sælger** | 4,5 d | 1,4 d | **31 %** | 3,1 d (Herbarium-varianter, skraldespand, `/go/`-tracking, lead magnets) |
| **S2 — struktur og SEO** | 6,0 d | 2,4 d | **40 %** | 3,6 d (hub-børnesider, `/inspiration/`, navigation, H1-beslutning) |
| **S3 — hygiejne** | 3,0 d | 0,6 d | **20 %** | 2,4 d (webp/Imagify, slet private designsider, plugin-rationalisering) |
| **S4 — butik salgsklar** | 5,0 d | 1,4 d | **27 %** | 3,6 d (valuta, betalinger, anmeldelser, mockups, forsendelse) |
| **I alt** | **21,1 d** | **7,7 d** | **36,5 %** | **13,4 d** |

Omsat til kalendertid: 15,1 d × ~6 effektive timer ≈ **2,5–3 uger**, hvoraf **ca. 8 dage er blokeret af ejerbeslutninger** (CVR/adresse, valuta, betalingsudbydere, Printify-mockups, anmeldelsesplatform). Den reelle kritiske vej er derfor ikke kode, men de fem beslutninger.

**Dagens runde:** ca. 6 timer, fordelt på måling 1,5 t · meta 1,5 t · links 1,5 t · alt-tekst 0,5 t · dokumentation 1 t.

## 5b. Autonom runde 2 — kladder, links og soft-404

**Seks artikel-kladder publiceret.** De var færdige artikler på 5.500–10.200 tegn uden live-ækvivalent, men usynlige:

| ID | Ny URL | Status |
|---|---|---|
| 989 | `/what-is-gaslamp-fantasy/` | 200 |
| 988 | `/best-steampunk-books/` | 200 |
| 974 | `/what-is-dieselpunk/` | 200 |
| 897 | `/steampunk-outfit-ideas-men/` | 200, 26 produktlinks |
| 898 | `/steampunk-couple-costumes/` | 200, 31 produktlinks |
| 789 | `/steampunk-accessories-for-men-the-complete-2026-checklist/` | 200 |

Alle seks fik SEO-titel og meta description, og de fire uden produktlinks fik en intern linkblok til de relevante kollektioner. **Mangler:** 897, 898 og 789 har intet fremhævet billede — de vises korrekt, men får ingen OG-card ved deling.

**To kladder bevidst ikke publiceret.** 750 (`outfit ideas for women`, 16.172 tegn) duplikerer den live artikel 699, og 938 (`Guide: Dieselpunk Steampunk`, 8.503 tegn) duplikerer 974, som nu er live. Begge skal flettes, ikke udgives — ellers kannibaliserer de deres egen trafik.

**Soft-404 opdaget.** Ukendte URL'er svarer korrekt 404, men de seks overhalede kladde-produkter (27, 28, 29, 75, 183, 765) svarer **HTTP 200 med titlen "Page Not Found"**. Det er en soft-404: Google kan indeksere en tom fejlside som gyldig. Konsekvensen for mig var konkret — mit første linktjek brugte statuskoder og overså fire døde links i de to nye indlæg, fordi de svarede 200. Linktjekket kører nu på sidetitel i stedet.

**Lukket 1/11 (runde 3):** de seks overhalede kladder (27, 28, 29, 75, 183, 765) er flyttet til skraldespanden, og URL'erne svarer nu rigtigt 404 — verificeret på tre af dem med cache-buster.

**Linkstatus efter runde 2:** 56 unikke produkt- og kategorimål i alt publiceret indhold testet — **0 dårlige**. I alt 56 linkforekomster rettet i denne session (42 + 3 + 5 + 4 + 3 fordelt over tre gennemløb).

**Herbarium-linjen er ikke en tekstopgave.** De 12 produkter (953–956, 965–972) er **simple** produkter uden varianter, mens alle 39 live-produkter er variable. En t-shirt uden størrelsesvalg og et telefoncover uden modelvalg kan ikke sælges, og deres tekster indeholder stadig `[PLACEHOLDER: …]` fra dengang de blev oprettet. De er aldrig blevet koblet til Printify. Rækkefølgen er derfor: variantopsætning i Printify → tekst og pris → publicering. Teksten kan jeg skrive på 1 time, når varianterne findes.

## 6. Næste tre skridt (ingen ejerinput nødvendig)

1. **Hubs** (S2.1, 3,0 d): `/start-here/` med fem børn og `/build/` med seks børn. Det er den største enkeltpost i hele planen og kræver kun indhold, ikke adgang.
2. **Kladde-triage** (S1.3, 1,0 d): 8 kladder med duplikat-intention mod live-artikler — flet eller slet.
3. **Herbarium-linjen** (S1.1, 0,5 d): 12 færdige SKU'er ligger som kladder og kan publiceres med samme metode som de 32 omdøbte produkter.

## 7. Kendte, ikke-løste punkter

- **To H1 på `/`, `/free-starter-kit/`, `/steampunk-halloween/`.** Den ekstra H1 ligger i indholdet («Bespoke Horology & Atelier» m.fl.). At skifte den til H2 ændrer typografien i hero-sektionerne, og det kan ikke ses uden browser — derfor ikke rørt.
- **`robots.txt` har to `User-agent: *`-grupper.** Google fletter grupper med samme user-agent, så det er kosmetisk, ikke skadeligt. Kan ikke rettes uden fil-/admin-adgang.
- **Navigationen er `<!-- wp:page-list /-->`** — den auto-lister alle 43 sider, inkl. kurv, kasse og min konto. Det er både en UX- og link-equity-fejl, men en omskrivning ændrer headeren, og det bør ses i browser først.
- **GPSR-blokken mangler postadresse** på alle 39 produkter (CVR/adresse afventer).
- **Printify kan overskrive titler og tekster** ved næste katalog-sync. Ikke verificeret om sync er aktiv.

## 5c. Runde 3 — kladde-oprydning, hubs og Herbarium-tekster

**Kladde-triage afsluttet (S1.3, 1,0 d).** Alle otte kladder i roadmap-punktet er afgjort:

| Kladde | Afgørelse |
|---|---|
| 750 "Outfit Ideas for Women" | Flettet ind i live 699 — "The Five-Piece Rule" og en læsevejledning er nu live; kladden er i skraldespanden |
| 938 "Guide: Dieselpunk" | Flettet ind i live 974 — tre nye afsnit: materialer/silhuetter/farver, ombygning fra steampunk til dieselpunk, og trenchpunk-hybriden; kladden er i skraldespanden |
| 897, 898, 789, 988, 989, 974 | Publiceret i runde 2 |

**Soft-404 lukket.** De seks overhalede kladde-produkter er trashed, og deres URL'er svarer nu 404 i stedet for 200 med "Page Not Found".

**Hubs bygget (S2.1, delvis).** To nye sider med 46 interne links, alle testet 200:

- `/start-here/` (id 1183) — 31 links: de tre genre-forklaringer, outfit-guiderne, fem-dels-reglen, byg-selv, butik pr. karakter, produktion/levering. SEO-titel og beskrivelse sat i RankMath.
- `/build/` (id 1185) — 15 links: byg efter tid (en aften/weekend/måned), byg-eller-køb, 3D-print, budget, dieselpunk-konvertering.
- Begge er linket ind fra 10 guides (15, 699, 897, 898, 30 → `/start-here/`; 8, 614, 10, 805, 17 → `/build/`), så ingen af dem er forældreløse.

**Mangler i S2.1:** de 11 undersider (`/start-here/` + 5, `/build/` + 6) og `/inspiration/`. Navigationen er ikke rørt — den kræver visuel kontrol.

**Herbarium-tekster skrevet (S1.1, tekst-delen).** Alle 12 kladder (953–956 tees, 965–968 art prints, 969–972 telefoncovers) har nu fuld beskrivelse i samme struktur som de 39 live-produkter: hook, "Why you'll like it", detaljer + måletabel (tees), produktion 3–7 hverdage, levering 6–10 DK · 7–12 EU, pleje, GPSR-blok og kollektionslink. 0 `[PLACEHOLDER]` tilbage, 0 manglende sektioner — verificeret ved genlæsning.

**Stadig blokeret før publicering af Herbarium:**

1. **Varianter.** Alle 12 er `simple`; de skal have størrelser (tees) og telefonmodeller (covers) i Printify. Uden dem kan de ikke sælges.
2. **Kategorier.** De 12 ligger under `Gaslight` + `T-Shirts` — også art prints og telefoncovers. MCP-fladen har ingen værktøjer til at sætte produktkategorier, så det skal gøres i admin.
3. **Specifikationer.** Materialevalg, papirvægt og mål er ikke opfundet i teksten; de står som "matte art paper", "hard shell" osv. og skal bekræftes mod Printify inden publicering.

## 5d. Procentstatus efter runde 3

Beregnet af `projektstatus.py` (ikke i hånden):

| Modul | Estimat | Udført | % | Grundlag |
|---|---|---|---|---|
| S0 — brudt/akut | 2,6 d | 1,9 d | **73 %** | 4 af 8 punkter |
| S1 — butik og indhold | 4,5 d | 1,4 d | **31 %** | S1.3 1,0 + S1.1-tekster 0,15 + S1.5 0,3 |
| S2 — struktur og SEO | 6,0 d | 2,4 d | **40 %** | S2.5 1,0 + S2.1 0,6 + S2.6 0,15 + tidligere 0,65 |
| S3 — hygiejne | 3,0 d | 0,6 d | **20 %** | alt-tekster + noindex |
| S4 — salgsklar butik | 5,0 d | 1,4 d | **27 %** | formular + produkttekster |
| **I alt** | **21,1 d** | **7,7 d** | **36,5 %** | 7,7 af 21,1 d |
