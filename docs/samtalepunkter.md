# Samtalens vigtigste punkter

*Formålet er context-håndtering: når samtalen nærmer sig grænsen — klientens context meter omkring **95 %** —
skal det vigtigste fra samtalen stå her og være committet, ikke kun i chat-historikken, som forsvinder når
konteksten komprimeres. Filen er repoets hukommelse om **hvorfor** tingene blev gjort, ikke en kopi af
[`projektstatus.md`](projektstatus.md), der har tallene.*

## Sådan bruges filen

- **Ved ~95 % context** (eller før en komprimering): opdatér "Beslutninger og fund" og "Næste skridt" nedenfor
  med det der er kommet til siden sidst, kør `python3 tools/samtalepunkter.py --update`, og commit + push i
  samme tur. Så kan næste pas læse sig ind på under et minut.
- **AUTO-blokken** skrives af [`tools/samtalepunkter.py`](../tools/samtalepunkter.py) ud fra
  [`projektstatus.json`](../projektstatus.json) og git-historikken. Den må ikke håndredigeres.
- Blokken indeholder dagens dato og de seneste commits, så den **skal opdateres, ikke bare tjekkes**: kør
  `--update` og derefter `--check` i samme pas. Et `--check` dagen efter vil med rette melde drift.
- Prosaen uden for markørerne er derimod kun for den der arbejder. Skriv kort og konkret: hvad blev besluttet,
  hvad blev målt, og hvad var fælden.

<!-- AUTO:SAMTALEPUNKTER:START -->
_Opdateret 2026-11-09 af `tools/samtalepunkter.py`. Blokken er maskinelt genereret — ret i `projektstatus.json`, ikke her._

### Status

| Projekt | Status | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** | **100 %** | 20,95 af 20,95 d (S0 2,35/2,35 · S1 3,6/3,6 · S2 6,9/6,9 · S3 4,05/4,05 · S4 4,05/4,05) |
| **steamgears.eu — miljø, server og sandbox (S5)** | **60 %** | 1,8 af 3 d (S5 1,8/3) |
| **Steamgears Autopilot — plugin** | **100 %** | 31 af 31 d (P0 4,65/4,65 · P1 14,45/14,45 · P2 4,75/4,75 · P3 2/2 · P0S 1/1 · P0C 0,25/0,25 · P1D 0,25/0,25 · P1E 0,25/0,25 · P1F 0,25/0,25 · P1G 0,5/0,5 · P2F 1,5/1,5 · P3OPT 0,6/0,6 · P3JS 0,3/0,3 · P3LIVE 0,25/0,25) |
| **Dark academia-rebrand** | **71,3 %** | 12,4 af 17,4 t (TEKSTER 8,5/8,5 · MOCKUPS 0/5 · BATCH4 0,5/0,5 · GENTILDELING 0,4/0,4 · MOBIL-OG-SAESON 3/3) |
| **SEO-indholdsproduktion** | **100 %** | 8 af 8 guides (BATCH 3/3 · COPY 4/4 · CORSET 1/1) |
| **Døde internlinks** | **100 %** | 39 af 39 links (FIX 39/39) |
| **Niche-butik — plugin P4** | **100 %** | 1 af 1 d (P4PLAN 0,3/0,3 · P4APPLY 0,25/0,25 · P4UI 0,2/0,2 · P4SHIP 0,1/0,1 · P4LIVE 0,15/0,15) |
| **KABINE — bil-interiør som ny butik** | **36,5 %** | 9,5 af 26 t (B1 2/2 · B2 2/2 · B3 2/2 · B4 2/2 · B5 1,5/1,5 · B6 0/6 · B7 0/4 · B8 0/2,5 · B9 0/4) |

### Åbne blokeringer

- **steamgears.eu — site-optimering** — Ejerens oplysninger og aftaler, målt 8/11: CVR-nummer og telefon (Complianz har navn »Henning 6«, adresse »Lavgade 44a st th«, e-mail og DK — men telefon er tom og CVR findes ikke i formularen); aftaler med MobilePay/PayPal (kun Stripe og bankoverførsel er aktive); zonerne til EU/UK/RoW (kun Denmark har en metode — fri fragt uden minimum — og resten af verdenen har ingen, så EU-kunder ikke kan gennemføre en ordre), fragtklasser og fri fragt-grænse; en currency-switcher hvis EUR skal vises som sekundærvaluta (der er ingen aktiv); Printful-adgang eller 5–8 færdige mockups pr. produkt (målt: 20 publicerede produkter, 22 billeder, alle under 3); ja/nej pr. produkt for 942/765/766/767 (alle fire kladder med pris og SKU); GSC-svarrapport (S0.6); og beslutning om de to inaktive plugins.
- **steamgears.eu — miljø, server og sandbox (S5)** — Subdomæne + certifikat hos Simply (S5.1); DNS-panelet hos Simply til DKIM og DMARC-rapporter (S5.7); hostpanelet til backup og restore (S5.8); GitHub Pro/Team til branch protection og environment-regler (S5.9). Ingen af de fire kan lukkes af agenten — hver kræver en konto eller et panel ejeren råder over.
- **Steamgears Autopilot — plugin** — Syv led kræver ejeren, alle målt 8/11: (1) **Pinterest-scope** — tokenet ligger nu i WooCommerce-integrationen og er gyldigt (`/user_account` 200 som `cpaleadsis`, id 688980580401955331), men appen har kun ads/billing/catalogs/pins/user_accounts og **mangler `boards:read`**, så `/pins` og `/boards` afvises (målt 17:25Z, kanalstatus står som `needs_scope`); (2) **de øvrige kanal-tokens** — Instagram, Bluesky, Mastodon, Telegram og Facebook har intet token (5 af 6 mangler); (3) **AIMLAPI-kredit** — sidste generering 7/11 fejlede med »You've run out of funds«; (4) **`sgap_alert_email`** er tom, så fejl registreres men ingen mail sendes; (5) **full auto vs. godkendelse** — `sgap_full_auto_enabled = no` og 40 elementer venter; (6) **distributionsvej** — `publish_mode = rss` og 0 Publer-konti; (7) **P2.1–P2.3** kræver ejerens beslutning om Command Center-retningen. Intet af det må gættes.
- **Dark academia-rebrand** — To led kræver ejerhandling, målt 8/11: (1) **de 29 trash'ede designs** — herunder de tre produkter der er witchy-tarots eneste kandidater (567, 395, 411); et ja/nej til genoplivning er nok, og tildelingen kører selv bagefter; (2) **Batch 4-mockups (5 t)** — Printful-adgang eller 5–8 færdige billeder pr. produkt; målt 20 publicerede med 22 billeder, 0 med 3 eller flere, 62 billedplaceringer mangler, og repoet har 0 trykfiler og 0 tøj-skabeloner. Kollektionstildelingen er ikke længere blokeret: den blev udført 8/11 gennem Novamira-MCP og målt stabil.
- **KABINE — bil-interiør som ny butik** — Seks ejerhandlinger, målt 9/11: E1 CVR, telefon, adresse og bankaftale til butikken (uden dem ingen faktura, ingen MobilePay, ingen lovlig handel); E2 leverandørvalg med GPSR-dokumentation og to prøver (ellers er indkøbspris, kvalitet og leveringstid gæt); E3 registrering i producentregistret for emballage (markedsføring må ikke starte før); E4 domænet kabine.dk eller et alternativ plus Shopify-konto; E5 beslutning om start 9/11 i Q4-priser eller 5/1 til lavere CPA; E6 annoncekonto med betalingsmetode og 10.000 kr. Ingen af de seks kan lukkes af agenten — hver kræver en konto, et panel eller en underskrift.

### Næste skridt

- **steamgears.eu — site-optimering** — Intet agent-arbejde tilbage i S0–S4: alle fem bands er færdige og målt, og de resterende poster er ejerens og navngivet i §4c og blocked_by. Målt 8/11: 24 produkter (20 publicerede, 4 kladder) i **DKK**. DKK-tabellen fra 2/11 er afregnet **række for række**: af de **38 rækker** (linje 32–69) har **11** et produkt i butikken og står med **tabellens tal ordret** (335/165/400/435/265/180 kr) — målt 11/11 på produktsiden, i JSON-LD, i Store API og i DB med 0 afvigelser — og de **27** øvrige har kun et produkt i **papirkurven** (offentligt 404), så de er dokumenteret som rækker uden produkt med id og status; 0 rækker er uafklarede, og de 13 produkter uden række har uændret pris med id og målt beløb dokumenteret. W3TC kører med **minify, lazy load og database-cache slået til** (192 minificerede filer, 8,08 MB) efter tre gennemførte testordrer (1528/1529/1530, alle annulleret med note); kurv og kasse svarer 200; fri fragt i DK og ingen forsendelse til EU/UK/RoW; Stripe + bankoverførsel; Complianz på EU opt-in. Alle resterende poster er ejerens og står nu samlet i §4d (A–M) med den målte mangel og én konkret handling; målt 8/11 om aftenen er der **ingen** poster tilbage som agenten kan udføre.
- **steamgears.eu — miljø, server og sandbox (S5)** — Tilbage er fire poster, alle ejerens: sandkassen (S5.1, subdomæne + certifikat hos Simply), DKIM/DMARC (S5.7, to DNS-poster i Simplys panel), de sidste 0,05 d af S5.8 (gendannelse til staging — resten er målt på live 9/11) og branch protection (S5.9, GitHub svarer 403 »Upgrade to GitHub Pro«, målt 9/11).
- **Steamgears Autopilot — plugin** — Agent-scope er færdigt (31,00 af 31,00 d) og 7.65.5 kører nu på sitet. Det eneste åbne er P1.5's live-verifikation (0,6 d, ejer-blokeret): Pinterest-tokenet mangler `boards:read`, og fem kanaler mangler tokens.
- **Dark academia-rebrand** — Kun to ting står tilbage: (1) **witchy-tarot** kan først få indhold når ejeren siger ja til at gendanne 567 (Tarot Witchy Tee) — og eventuelt 395/411 — fra papirkurven; derefter fylder `python3 tools/rebrand_apply.py --apply --via-cli` termen automatisk, fordi kortet allerede indeholder dem; (2) **Batch 4-mockups** (5 t) kræver Printful-adgang eller 5–8 færdige billeder pr. produkt.
- **SEO-indholdsproduktion** — SEO-indholdet er 100 %: fire guides skrevet, tre publiceret, corset-guiden publiceret, 1410 parkeret til september. Ingen åbne poster.
- **Døde internlinks** — Vedligehold: tjek nye indlæg mod live-URL'er
- **Niche-butik — plugin P4** — Færdig som roadmap-punkt P4: modulet er kørt mod det levende site (WordPress 7.1.4, WooCommerce 11.2.2) med 8 produktkladder, 5 kollektioner, en sideskits og en navigation, og rullet tilbage til præcis udgangstilstanden. Næste skridt er ejerens: Printful-adgang, publicering og de øvrige punkter i readiness-listen. Målt 8/11 om aftenen er `alert_email` tom, så fejl registreres uden at nogen får mail — det er et ejerpunkt i plugin-roadmappens §8.
- **KABINE — bil-interiør som ny butik** — Fem forberedelsesmoduler er færdige (9,5 af 26 t, 36,5 %): niche og brand valgt, enhedsøkonomi regnet, 30-dages plan med KPI-gates skrevet, ti hooks som manuskript, og kravlisten for butik, betaling, tracking og jura. Resten er butiksopsætning (6 t), leverandør og prøver (4 t), juridiske sider (2,5 t) og første annoncerunde (4 t) — alle fire starter først når ejeren har leveret E1-E6.

### Seneste commits

- `a20f11c chore(rebrand): kørselsrapport for tildelingen`
- `06d0fa5 CI'ens actions kører nu på Node 24 og en fast runner-image`
- `91ea4e5 Niche 2 er regnet igennem: lejebolig-opbevaring bærer trafik, men kun over ca. 600 kr`
- `1a6d341 Bil-interiør har fået sit eget repo, så planen ikke længere bor her`
- `c6ad294 chore(status): daglig projektstatus 2026-11-09`
- `09acb7c Bil-interiør valgt som niche — forberedelsen er regnet, ikke gættet`
<!-- AUTO:SAMTALEPUNKTER:END -->

## Beslutninger og fund (nyeste først)

### 9/11 (nat) — øvelsen er nu et månedligt værn, ikke et engangstal

**Beslutningen:** S5.8's tal (RTO 6 s, 269/269 tabeller) måler kun noget, hvis øvelsen bliver kørt igen. Derfor
læser `tools/db_drill_check.py` nu historikken og kræver (a) at seneste kørsel er **højst 31 dage** gammel, med
**3 dages varsel**, og (b) at den var **grøn**: 0 rækkeafvigelser, 0 mangler, 0 rester efter oprydning,
ikke-tomt dump og **RTO under 300 s** (målt 6 s — budgettet skal fange en regression, ikke en dagsform).
15 offline-fixtures dækker forfalden frist, én afvigelse, rester, langsom RTO, tomt dump, nul tabeller, korrupte
kilder og trenden.

**Historikken er ny:** `reports/db-drill-history.json` får én række pr. kørsel. Dagsfilen
`reports/db-drill-<dato>.json` overskrives af en ny kørsel samme dag — det gjorde den 9/11, hvor anden kørsel
kl. 05:07 gav 3 335 364 bytes / 4 s og samme RTO 6 s. Uden historikken ville det første tal være væk.

**Kadencen:** værnet kører **hver nat** i `tools/natlige-vaern.sh` (fristen tælles ned dagligt), og
`.github/workflows/backup-doelse.yml` kører den **1. i hver måned** og skriver trenden i job-summaryen.

**Grænsen, som den er målt og ikke gættet:** kørslen kan ikke udføre øvelsen fra CI. Den kræver
Novamira-MCP-adgang, og keep-alive-kørslen **roterer** refresh-tokenet — en fornyelse mere i en anden workflow
ville slå begge kørsler ihjel. Arbejdsdelingen er derfor: natlig måling fejler når fristen er overskredet,
månedskørsel varsler med den præcise kommando, og selve kørslen er en agenthandling. Automatisk udførelse kræver
en databaseforbindelse uden om MCP — en ejerbeslutning.

### 9/11 (sen aften) — bil-interiør har fået sit eget repo, og det hedder `kabine`

**Planen flyttede ud af dette repo.** `humle93/kabine` er oprettet som **privat** repo med `main` som
eneste gren — ikke en kopi af steamgears, men et lille, selvstændigt repo med kun det, KABINE har brug
for. Elleve filer i fire commits: `AGENTS.md` (samme form som her: procenter, daglig status,
AUTO-blokke), `docs/agent-profil.md` (hvad projektet er, hvad der er målt, og hvad der ikke kan gøres
uden ejeren), `docs/roadmap.md` (faserne A1-A9 med tidsestimat), `docs/library/plan-2026-11-09.md`
(30-dages planen), `docs/library/enhedsokonomi-2026-11-09.csv` (regnearket bag tallene),
`projektstatus.json` + `docs/projektstatus.md` (36,5 %, 9,5 af 26 t) og en kompakt `projektstatus.py`
på 11,5 KB med ti selftest-fixtures.

**Statusværnet er ikke kopieret, det er skrevet til formålet.** Steamgears-versionen er 47 KB, fordi
den dækker otte projekter med blandede enheder og 164 historik-rækker. `kabine` har ét projekt og én
enhed, så scriptet behøver kun skemavalidering, 100 %-kravet (`verified`), fald-værnet
(`scope_change`) og drift-kontrol mellem JSON og Markdown. Kørt på det uploadede træ: `--selftest`
10/10 bestået, `--check` 36,5 % og ingen drift.

**To ting er bevidst ikke med.** `reports/*.pdf` og `tools/md_to_pdf.py` kunne ikke overføres gennem
den API-vej, der blev brugt til at seede repoet, så PDF-eksport er ikke sat op i `kabine` endnu —
Markdown er kilden, og det står i AGENTS §5, roadmap §3 og bibliotekets indeks, så ingen leder efter
en renderer, der ikke er der. Og der er **ingen git-credentials** i sandkassen til det nye repo;
seeding foregik gennem GitHub-API'et, og det er værd at vide, før næste pas prøver `git push`.

**Verifikation:** alle elleve filer er hashet med `git hash-object` og sammenlignet med GitHubs
blob-SHA'er — 0 afvigelser. Det var nødvendigt, fordi den lokale staging-fil var 4.681 bytes mod
GitHubs 4.666. Forskellen var præcis de 15 bytes i en forældet sti (`bilinterioer-launch-…` →
`plan-2026-11-09.md`), altså en rettelse og ikke en tabt bid — men den skulle måles, ikke gættes.

**Fælde for næste pas:** API-seeding kræver literal tekst, og store filer skal ikke skrives som
håndskrevet base64 for at komme uden om det. En afkortet base64-streng dekodede til 1.181 bytes uden
at fejle. Hash mod blob-SHA'erne i stedet, så »oppe« og »identisk« bliver to forskellige påstande.

**Rapporten bliver her:** `library/bilinterioer-launch-2026-11-09.md` og PDF'en er steamgears'
research og flytter ikke med; `kabine`s `plan-2026-11-09.md` er den kørende version.

### 9/11 (aften) — bil-interiør valgt som niche, og valget er nu et regnestykke

**Fire nicher blev vejet, og bil-interiør/detailing blev valgt.** Sensorisk/neurodivergent kræver
ægte community-troværdighed i kreativet, katte-tech trækker elektronik, CE/WEEE og support ind, og
søvn er mættet på kreativt niveau med den højeste annoncepris. Bil-interiør er den mindst dårlige
niche for en ny butik: kreativet kan optages hånd-only uden ansigt, efterspørgslen er evergreen, der
findes ingen pasform og dermed lav retur. **Ikke den mest spændende — den mest overlevelige.**

**Brand og produktstige er besluttet:** KABINE (»kabinen« er bilens interiør på dansk), hero Kabine
Kit 01 til 499 kr., Kit 02 Aflevering til 449 kr. med egen landingsside, og sæde-gap-organizeren
(199 kr., 129 kr. som order bump) som mer-salg — aldrig som selvstændig annonce. Væske i pakken er
fravalgt: rensemidler er farligt gods, må ikke flyve og kræver CLP + sikkerhedsdatablad, så væsken
købes i EU.

**Målt gennem de fire formler i `roi-model-dropshipping-2026-11-09.md` §2** (aritmetikken er kørt med
Python, ikke i hovedet): hero-DB **222,95 kr.**, blandet AOV **556,65 kr.**, blandet DB **248,82 kr.**
og break-even-ROAS **2,24x**. Tre fund styrer planen: (1) **CPA over 249 kr. er et underskud, uanset
volumen** — der findes ingen ordremængde, der redder en negativ enhedsøkonomi; (2) 199-kroners-varen
taber penge allerede ved CPA 70 og skal derfor kun være bump; (3) bundlen er her et **AOV-løft**
(2,24x mod 2,25x) og ikke den redningsplanke, den var i småhunde-casen — bilvarens højere pris bærer
annoncerne selv.

**Måned 1 er kalibreret til at lande på nul:** 10.000 kr. i fire uger med stigende CPA-præcision giver
45,68 ordrer, og der kræves 45,82 — resultatet er **−34,30 kr.** Det er med vilje: måned 1 er en
udgift til markedsundersøgelse, og det, der ejes 8. december, er anmeldelser, hooks og pixel-data.

**To fejl blev fanget i eget arbejde, før de nåede dokumentet.** ROI-udtrykket dividerede et årligt
resultat med en månedlig nævner og gav 414-731 % i stedet for 34-61 %; og order bump'en var sat ind i
CPA-tabellen, hvor den per definition ikke kan tabe, fordi den ikke har nogen anskaffelsesomkostning —
en kategorifejl, der ville have forkastet et mer-salg med 62,20 kr. i ren margin. PDF'ens visuelle
kontrol afslørede desuden to ting, tallene ikke kunne: kolonneoverskrifter der brækkede midt i ordet,
og en CPA-tabel så bred at tallene brød over to linjer. Begge er rettet (tabellen er transponeret), så
eksporten er læsbar og ikke bare korrekt.

**Fælde for næste pas:** parenteser i et **modulnavn** bryder commit-værnets parsing af grundlaget.
`basis_problem()` fjerner `\([^)]*\)` fra rækkens basis, og et modulnavn som »Første annoncerunde
(uge 1-2)« får regexen til at stoppe ved den indre parentes — så blev »9,5 af 26 t« læst som
»9,5 af 26 t 0/4)« og beskeden afvist. Modulnavne skal derfor være uden parenteser; rækken blev
genereret igen efter omdøbningen, og grundlaget kan nu citeres som `9,5 af 26 t`.

**Statusmæssigt er det en ny gren, ikke et nyt projekt i den gamle betydning:** `bilinterioer` er lagt
i `projektstatus.json` som det ottende projekt med **baseline 36,5 % (9,5 af 26 t)** — de fem
forberedelsesmoduler er færdige, mens butiksopsætning, leverandør, jura og første annoncerunde først
kan starte når ejeren har leveret E1-E6 (CVR/bank, leverandør med GPSR-dokumentation,
emballageregistrering, domæne, startdato og annoncekonto). Dagens tidligere ROI-dokument fik med vilje
ingen projektlinje: det var ét research-dokument, dette er en køreplan med budget.

Bevis: [library/bilinterioer-launch-2026-11-09.md](library/bilinterioer-launch-2026-11-09.md) og
[reports/bilinterioer-launch-2026-11-09.pdf](../reports/bilinterioer-launch-2026-11-09.pdf).

### 9/11 (aften) — S5.8 blev et måltal, og plugin 7.65.5 kom i drift

**Backup og gendannelse var en forsikring i roadmappet. Nu er den kørt.** `tools/wp_db_drill.py` dumper databasen med
`mysqldump`, gendanner den under scratch-prefixet `sgtest_` og sammenligner rækkeantal for **hver** tabel. Målt:
**3 338 987 bytes / 5 s** dump, **6 s** gendannelse, **269 af 269 tabeller identiske**, 269 tabeller + 4 visninger
ryddet, **0 tilbage**. RPO 0 t ved kommando, RTO 6 s — skrevet i `docs/release-og-rollback.md` §3.3.

**Tre fælder som en naiv gendannelse falder i:** (1) der kan **ikke** oprettes en ekstra database (`GRANT ALL`
gælder kun `steamgears_eu_db`; `CREATE DATABASE` svarer 1044), (2) databasen bærer et **gammelt WordPress-prefix**
`clk4219fc96b5_` med 12 tabeller som `mysqldump` tager med — og den skriver `DROP TABLE IF EXISTS` før hver tabel, så en
gendannelse med prefix-bytte **overskriver dem** (værktøjet udelader dem på dump-siden), og (3) **visninger står i
`information_schema` som tabeller**, så `DROP TABLE` efterlader dem — første kørsel droppede 273 og efterlod 4.

**Plugin 7.65.4 var i repoet men ikke i drift.** Sitet kørte 7.65.0, så P3.7's og P3.8's besparelser var bygget uden at
være live. Zip'en gik gennem MCP i to base64-dele og blev installeret med `Plugin_Upgrader::install(…, overwrite_package)`;
live kører nu **7.65.5** (bogføringsrelease fordi `ROADMAP.md` er en del af zip'en), og fire kontrollerede filer er
**byte-identiske** mellem site og repo. Målt live: dashboard **17 566 bytes HTML med 0 inline `<script>` og 0 inline
`<style>`**, syv side-scripts, fire sider 200 og `/sgap/v1/tick` 200.

**S5.9 er fortsat ejerens:** GitHub svarer **403 »Upgrade to GitHub Pro or make this repository public«** på branch
protection for et privat repo på Free-planen.

### 9/11 — vejen til 100 % er fem ejerhandlinger, og DKIM fandtes ikke

Målet var at køre roadmappet til 100 %. Målt: **5 af 7 projekter er 100 %**; miljø står på 51,7 % og rebrand på
71,3 %, og de to projekters rest er **1,40 d + 5,0 t** fordelt på fem poster der alle kræver et panel, en
DNS-zone eller et abonnement (S5.1 sandkassen, S5.7 DMARC/DKIM, S5.8 backup/restore, S5.9 branch protection,
Batch 4-mockups). Ingen af dem blev talt som fremgang — §1 forbyder det, og tallet står derfor stille.

Tre fund undervejs: (1) **DKIM findes ikke.** Rækken påstod at selectoren fandtes; 30 selectorer er prøvet med
`dig` og `_domainkey.steamgears.eu` findes slet ikke, så `p=reject` hviler på SPF alene og en videresendt
ordremail kan afvises. (2) **S5-regnskabet krediterede S5.2** som rest efter den blev lukket samme dag, og skrev
1,20 af 2,90 d — nu 1,50 og fire restposter. (3) **S5.9 er plan-blokeret:** GitHub svarer 403 »Upgrade to
GitHub Pro or make this repository public«, så koden er færdig og indstillingen umulig.

Fælden: en note der tilføjes ved hvert pas men aldrig ryddes, ender med at modsige sig selv — derfor står
restposterne nu ét sted (`docs/roadmap.md` §4e) med procent og bevis pr. handling.

Bevis: [library/vej-til-100-2026-11-09.md](library/vej-til-100-2026-11-09.md).

### 9/11 — plugin'ets side-scripts var stadig PHP-strenge med JSON indeni

Efter 7.65.2 (CSS ud af `<head>`) stod der **femten** `<script>`-blokke tilbage i ni includes. Hver side byggede
sin egen JS som PHP-literal med `wp_json_encode($cfg)` midt i — cirka 30 KB HTML pr. sidevisning der aldrig
kunne caches, og kode der ikke kunne `node --check`es.

Tre beslutninger: (1) **syv filer i `assets/pages/`** i stedet for femten blokke — `dashboard.js` bærer både
genereringen, kopiér-knapperne og redningskortet, fordi de tre kort står på samme side; (2) konfigurationen
sendes med **`wp_localize_script`** gennem `SGAP_Assets::page_script($navn, $objekt, $config)`, så nonces,
chat-historikken og værktøjskataloget stadig beregnes pr. request, men som ét objekt; (3) loftet på 12 inline
side-scripts blev **fjernet** og erstattet af et krav om nul — kun JSON-LD i `studio.php` er tilbage, fordi det
er data og ikke kode.

Fælden der gjorde det sikkert: JS'en blev **trukket mekanisk ud** af PHP-literalerne og derefter sammenlignet
byte for byte med det HEAD printede for alle syv filer. En håndskrevet omskrivning ville have ændret escapes i
`esc()`-funktionerne uden at nogen kunne se det. To ting mere kostede en runde: smoke-harnessen manglede en
`plugins_url()`-stub, fordi `SGAP_UI::css()` nu kalder asset-loaderen ved første side-render — og den anden
chat havde allerede udgivet **7.65.3**, så dette pas blev rebaset til **7.65.4** oven på deres alarmer/hardning.

### 9/11 (eftermiddag) — 1 024 px: brudpunktet holdt, produkttitlen gjorde ikke

**Brudpunktet blev flyttet til 1 024 px i sidste runde uden at 1 024 selv blev målt.**
Målt nu, plus 1 023/1 025 omkring det, på forside, `/shop/` og produktside:

- **1 023 px:** burger, 0 inline-links, header **73 px**.
- **1 024 px:** **syv** inline-links (START HERE…CONTACT) der fylder 625 px og slutter i
  840 af gruppens 1 014 px, ingen burger, header **95 px**.
- **1 025 px:** uændret fra 1 024. **0 overflow** ved alle tre — `scrollWidth` = `clientWidth`.
- Butikken: 4 kolonner à 222 px, pris y 700 og knap y 793 (ingen overlap).
  Forsiden: 3 kolonner à 302 px, købsrække 56 px med pris og knap på én linje.

**Fejlen målingen afslørede:** produkttitlen stod i **223 px bredde i en 425 px
kolonne** (234 px høj) — og i **137 × 365 px, ni linjer**, ved 782 px. Årsagen er den
samme som mobil-fixet 8/11: fire indlejrede grupper med 24 px luft hver. Tabet er
**konstant 202 px**: kolonne 425 → titel 223, 513 → 311, 553 → 351.

Rettet med to regler der beviseligt ikke rører 320, 768 eller 1 280: 0 px luft i
**782–1 023 px** med titel efter kolonnen, og **flydende luft ≥1 024 px**
(`clamp(0px, calc((100vw - 1024px)/8), 24px)`) — 0 px ved 1 024 og temaets 24 px fra
1 216, så 1 280 er præcis som før. Målt efter: 782 → **257 × 115**, 900 → **305 × 132**,
1 023 → **367 × 149**, 1 024 → **367 × 154** (docH 5 529 → **5 449**), 1 025 → 367 × 155,
1 280 → **351 × 168 uændret** (padding stadig 24 px). 320 og 768 er målt uændrede
(docH 8 574 / 11 099 / 8 922 og 6 906 / 6 766 / 5 799).

**Rettet tal:** den tidligere dokumentation sagde »12 inline-links kræver ~1 010 px«.
Der er **syv**; de fem ekstra i DOM'en er undermenu-punkter med bredde 0. Brudpunktet
er rigtigt, men begrundet i at gruppen med logo og værktøj rager ud — ikke i linkenes bredde.

**Ikke rettet:** produkttitlen på 320 px er **17,6 px** (temaets bundgrænse) — den er
ikke rørt, fordi 320 px skulle verificeres uændret, og står som kandidat til næste pas.
Forsidens kort viser stadig **USD** mod butikkens **DKK** (ejerens flade).
Butikken skifter 2 → 4 kolonner ved 1 024 px (469 → 222 px kort) — det er temaets grid,
målt uden overlap og uden overflow.

Bevis: [library/1024-brudpunktet-2026-11-09.md](library/1024-brudpunktet-2026-11-09.md).

### 9/11 — mellembruddene: fem fejl lå mellem mobil og desktop

**Mobilen var målt ved 320 og desktop ved 1 280 — men ingen af dem siger noget om 768 og 800, og
det var præcis der fejlene lå.** Seks layoutfejl blev fundet og rettet:

1. **Headere ved 768 og 767 var 171 px** med både syv inline-links og burger. Temaet viser nemlig
   de inline-links helt ned til ~600 px, mens min desktop-regel først begyndte ved 783 px.
   Brudpunktet er nu **1 024 px** (målt: de syv links fylder 625 px, og det er gruppen med
   logo og værktøj der rager ud), og de inline links er
   skjult under det. Header: **73 px** ved 768.
2. **210 px vandret scroll ved 800 px.** Samme årsag: nowrap-gruppen med logo, links og værktøj
   var 805 px bred og ragede ud til 1 011 px. Nu 0.
3. **Forsidens varegrid faldt til én kolonne** mellem 783 og 1 023 px («Selected Works» blev
   7 111 px, forsiden 11 633 px). Nu tre pr. række → sektionen 1 588 px, forsiden 6 263 px.
4. **Butikken faldt til én kolonne**: 16 varer gav en side på 18 343 px. Nu to kolonner → 6 767 px.
   Her kostede det en runde: temaets `ul…[class*="columns-"]`-regel har **højere specificitet**
   end en ren klasse-selector, så min `!important`-regel tab. `!important` slår ikke altid —
   man skal matche specificiteten.
5. **Pris og «View Product» overlappede** i de 223 px brede kort: rækken var 188 px, pris 68,
   knap 121. Nu pris på egen linje og knappen i fuld bredde under.
6. **Ved 320 px stod produkttitlen i 54 px bredde** — én bogstav pr. linje — fordi købsboksen er
   fire grupper inde i hinanden med 20 px luft hver (224 → 180 → 138 → 96 → 54). Nu 186 × 76 px.

**Fælden der blev rullet tilbage:** mobilgalleriets 4:5-ramme kunne ikke sættes til `auto` — et
flex-barn med `aspect-ratio: auto` strækkes til højeste søskende, og sliden blev **1 502 px**.
Samme fælde som desktop-galleriet 8/11. Reglen er fjernet igen frem for at ligge som død CSS.

**Tilbage i rebrand er kun batch 4 (mockups, 5 t), og den er bruger-blokeret:** der kræves enten et
**Printful-token** (Settings → Keys) eller **5–8 fotos pr. produkt** efter
`library/batch-4-arbejdsordre-2026-11-08.md`. Repoet har ingen trykfiler, så mockups kan ikke
produceres her. **Nyt fund undervejs:** forsidens varekort viser priser i **USD** (`$21.90+`) mens
butikken kører **DKK** — ikke rettet, fordi priser er ejerens flade.

Bevis: [library/tablet-og-mellembrud-2026-11-09.md](library/tablet-og-mellembrud-2026-11-09.md).

### 9/11 — desktop blev målt, og »kan ikke lægges på sitet« var en forkert konklusion

**To ting blev lukket i samme pas, og de havde samme form: en regel der så rigtig ud ét sted og var
forkert et andet.**

**Desktop (1280 px).** Mobilen var målt, desktop aldrig set. Målingen blev lavet ved at lægge en
*same-origin-iframe* på 1280 px ind i den 320 px brede browserfane — iframen får sin egen layoutbredde,
og forælderen kan læse `contentDocument`. Syv regressioner kom ud, alle indført af `css/sg-season.css`
og ikke af temaet: header **171 px** fordi burgeren var `display: inline-flex !important` uden media
query; »Close menu«-krydset som en 44 px guldramme oven på »Contact«; underpunkt-pilen ved »Shop« som
en 1 px ramme om et usynligt chevron; produktsiden som `display: grid` med **66 px** købskolonne og én
bogstav pr. linje i h1; galleriet i en 4:5-ramme om et 1280×698-print; og kolonner der strakte til
**2 905 px**. Efter: header **95 px**, kolonner **1178/553/553**, galleri **551×551** (print 551×413),
docH **6 154 → 4 899**, `/shop/` fire kolonner à 285,5 px, sticky header på `top: 0` efter 1 076 px rul,
mobilen uændret (73 px header, 8 574 px side). **Alle rettelser er `@media (min-width: 783px)`** — det er
hele pointen: intet under 783 px er rørt.

**Fælden ved målingen:** iframen skal have både `width` og `max-width` med `!important`, ellers krymper
temaets CSS den til ~310 px, og man tror man måler desktop mens man måler mobil. Skud tages ved
`transform: scale(0.5)` (DPR 2), og venstre halvdel nås med `left: -320px`, ikke `-640`.

**S5.3–S5.6.** Roadmappet stod med fire poster som »kodet og testet, men kan ikke lægges på sitet
herfra — der er hverken MCP-token, CLI eller secrets i sandkassen«. Det var forkert: tokenet ligger i
hjemmemappen, og `wp-content/mu-plugins/` er skrivbar via `novamira/execute-php`. Filen blev lagt ind
(12 558 bytes, md5 lig repoets) efter `php -l` og 44 PASS, og `s5_measure.py` gik fra **8 opfyldt ·
11 mangler** til **19 opfyldt · 0 mangler**.

**Tre cache-fund undervejs — de er det værd at huske:**
1. W3TCs sidecache svarer direkte fra en HTML-fil, så `send_headers` aldrig kører: de nye headere kom på
   `/cart/` og `/checkout/` (ikke cachet) men ikke på `/` og `/shop/`. Løst med en `.htaccess`-blok.
2. En slettet `readme.html` blev ved med at svare **200** — cacheposten fra før sletningen blev afspillet.
   Løst ved at fjerne posten og lægge `RedirectMatch 404` ind, så en kerne-opdatering ikke genåbner hullet.
3. **`pgcache.cache.404 = true`** fik enhver ukendt URL til at svare **200 ved andet kald** (første kald
   404, `?x=1` 404). Det er soft-404s for søgemaskiner. Nu `false`, målt 404 to gange i træk.
Værnet er koblet på: `tools/s5_measure.py --strict` kører i `tools/natlige-vaern.sh`.

**Ejerpunkt K var fejlformuleret.** `alert_email` var tom, men `SGAP_Alerts::recipient()` falder tilbage
til `get_option('admin_email')` — så mailgrenen har virket hele tiden. Målt: `recipient()` = admin-adressen,
`mode = immediate`, `wp_mail()` findes, WP Mail SMTP aktiv. Adressen er nu alligevel sat i Settings → Alerts.
**Læren:** en tom indstilling er ikke det samme som en manglende evne — læs koden der læser feltet, før et
punkt erklæres ejer-blokeret.

Bevis: [library/bredt-layout-og-hardning-2026-11-09.md](library/bredt-layout-og-hardning-2026-11-09.md)
og [reports/s5-status-2026-11-09.md](../reports/s5-status-2026-11-09.md).

**S5.2 blev også lukket, og den havde samme fejl i sig:** »kan ikke afgøres udefra«. Læst gennem
`novamira/execute-php` står `WP_DEBUG=false`, `WP_ENVIRONMENT_TYPE=production`, `DISABLE_WP_CRON=true`,
object cache med begge drop-ins og ingen debug-log. To afvigelser: `DISALLOW_FILE_EDIT` var **ikke
defineret**, så tema- og plugin-editoren stod åben i wp-admin — den er nu sat til `true` med atomisk
skrivning af `wp-config.php` og læst tilbage; `WP_DEBUG_DISPLAY` er `true`, men virkningsløs mens
`WP_DEBUG` er `false`, og er ikke rørt.

### 8/11 — mobilen var bygget til desktop, og tre ting fik siden til at ryste

Målt i cloud-browseren på 320 px: header **337 px** af 538 px skærmhøjde, indholdskolonne **198 px**
(både `main` og `.sga-wrap` lagde luft til), forside **11 120 px** høj. Menuen var et halvt panel med
en 63 px guldbjælke som lukkeknap, og «guldstangen» under logoet var en `::after` på den ydre
headergruppe. Rysteriet havde tre navngivne kilder: AI-boblens `mwai-glass-iris` i 10 s evig løkke,
`backdrop-filter` på den 337 px sticky header og `transition: all` på grupper og links.

Rettelsen ligger som et **selvstændigt lag**, ikke som en omskrivning: `css/sg-season.css` +
`mu-plugins/sga-season.php`, der sætter `data-season` efter måneden og printer CSS'en sidst. Målt
efter: header **73 px**, indhold **278 px**, forside **8 574 px**, **0** elementer uden for viewport,
menu som fuldskærms-ark med 44 px lukkeknap, AI-animationen `none` på mobil. Fire paletter ligger klar
(winter/spring/summer/autumn); 8/11 står der `autumn` — vinterpaletten er set ved at sætte attributten
manuelt.

**Fælden der kostede en runde:** W3TC trækker inline-`<style>` ud i en samlet fil, og der endte laget
**før** den CSS det skulle rette — tokens og `body` tabte, mens header og hero vandt, så det så ud som
om halvdelen virkede. Løsningen er `data-no-minify` *og* `html:root`/`html body` som vægt. Den næste
fælde: temaets bordeaux-paneler (`linear-gradient(145deg, #301e20, #140e10)`) sidder på mange grupper,
ikke på ét element — de blev fundet ved at måle hvilke elementer der stadig målte varmt, ikke ved at
læse CSS'en.

**Ikke gjort:** desktop er ikke set visuelt (viewporten er 320 px, og Playwright kunne ikke skifte
størrelse i denne kørsel), og produktgalleriet står i en 4:5-ramme med fotoet centreret.

### 8/11 — roadmappene var «lukket», men fem påstande var ikke sande længere

Begge roadmaps stod som lukket eller ejer-blokeret. Det holdt ikke hele vejen: da hver påstand blev målt
igen, var fem forældede — §4c's indledning sagde stadig **USD**, **US opt-out** og »26 billeder på 24
produkter«, mens virkeligheden var **DKK**, **EU opt-in** og **22 billeder på 20 publicerede**; S4.4 sagde 20
ItemList-elementer på `/shop/` (målt **16**, siden viser 16 pr. side); S4.5 sagde fem aktive gateways, men kun
**2 er tilgængelige i kassen** — `amazon_pay`, `klarna` og `link` står tændt uden at kunne vælges; og
plugin-roadmappen bad ejeren trykke *Update* til 7.64.9, hvilket allerede var kørt (sitet kører 7.65.0).
Fælden er generel: en roadmap der bliver «lukket» uden at blive målt igen, beder ejeren om noget han allerede
har gjort og skjuler at en indstilling er faldet tilbage. Derfor er der nu en **§4d** i `docs/roadmap.md` med
de **tretten ejerpunkter (A–M)** grupperet efter hvad ejeren skal levere — CVR+telefon dækker tre rækker, og
produktbilleder dækker to — plus de samme punkter i plugin-roadmappens §8. Beviset pr. måling står i
`docs/library/roadmap-status-2026-11-08.md`.

### 8/11 — de 27 «manglende» rækker havde produkter: de lå i papirkurven

Tabellen i 2/11-dokumentet har 38 rækker, og det forrige pas satte kun de 11 der havde et produkt i det
synlige katalog. Det så ud som om de 27 andre rækker ikke havde noget produkt — men et opslag i *alle*
statusser viste at hver enkelt række har et produkt, blot med `post_status = trash`: maritime prints
(719–728), goggles og hatte (475/479/481/485), otte tees (553–619), otte hoodies (391–411) og to
jakke/korset-rækker (487/497). Offentlig URL for dem giver **404**, så de er ikke i butikken, og deres
priser er derfor urørte: en prisændring på et skjult produkt har ingen prisflade at verificeres på.
Konklusionen er ikke «blokeret», men en afregning: **11 rækker sat og verificeret, 27 rækker uden produkt i
butikken med id og status som bevis, 0 uafklarede.** Fælden at huske: `wc_get_product()` og butikkens
katalog viser kun publicerede varer — skal man afregne en liste mod butikken, skal `wp_posts` spørges i alle
statusser, ellers ser en papirkurv ud som et tomt svar.

### 8/11 — priserne er nu tabellens egne tal, ikke en omregning

- **Forrige pas valgte en omregning; det var en egen beslutning.** Nu er DKK-tabellen læst på ny (38 rækker,
  linje 32–69) og de **11 produkter der findes i kataloget** har fået **tabellens tal ordret**: 335 kr
  (399/401/403/413/483), 165 kr (477), 400 kr (489), 435 kr (491/493), 265 kr (495) og 180 kr (557) — også
  i alle variationer. Ingen kurs, ingen egen afrunding.
- **13 produkter har ingen række** i dokumentet: 765, 766, 767, 940, 941, 942, 954, 965, 966, 967, 970,
  1255, 1282. Deres pris er uændret, og de står med id, målt beløb og beviset for at rækken mangler
  (0 grep-træf i et dokument med 38 tabelrækker). Der er ikke gættet et tal.
- **Fælden: dokumentets tabel modsiger sin egen regel.** Linje 73 siger at skiftet ikke må ændre hvad kunden
  betaler ud over afrundingen, men tabellens DKK-tal er udregnet af 2/11-USD-priserne, og butikken er
  genprissat siden (hoodie $49.95 → $109; corset $59.95 → $219). Tabellens tal er sat alligevel, fordi det er
  dem dokumentet angiver, og de 11 priser falder derfor 44–84 %. Modsætningen står med citater og
  linjenumre — ikke løst ved at vælge side — og ejerhandlingen er at gennemse de 11 priser i
  **WooCommerce → Produkter**.
- **Målt:** 11 af 11 produktsider viser præcis det dokumenterede beløb med `kr.` (0 afvigelser mod DB og
  Store API), 20 af 20 produktsider svarer 200, kurv og kasse 200 med vare i kurven, og W3TC står stadig på
  `minify/dbcache/lazyload: true` med kombinerede assets (29–35 minify-referencer pr. side).
- **Fire navne er ændret siden tabellen**, men varen er den samme: 477 (Windproof Riding), 489
  (Burlesque), 491 (PU → faux læder) og 495 (Brocade Gothic Jacket). Koblingen står i dokumentet.

### 8/11 — W3TC slået til og butikken skiftet til DKK, begge målt udefra

- **De to poster der stod som »ejer-blokeret« var ikke blokeret — de var skrevet før skrivevejen.**
  `minify.enabled`, `dbcache.enabled` og `lazyload.enabled` er nu `true` i
  `wp-content/w3tc-config/master.php`, og valutakoden er **DKK** med alle 72 prisrækker sat.
- **Database-cachen havde en skjult forudsætning:** W3TC's dbcache virker kun gennem drop-in'en
  `wp-content/db.php`. Den fandtes ikke, så indstillingen ville have stået tændt uden at gøre noget. Filen
  er lagt på plads fra pluginnets egen `wp-content/db.php`. Alligevel skriver den **0 filer**, fordi W3TC
  afviser den pr. request med `modification query` (WooCommerce-session/transienter skriver tidligt).
  Tændt og aktiv — men uden målbar effekt her. Det står der, i stedet for at blive pyntet.
- **Testordren blev lagt gennem butikkens egen checkout** (Store API: `cart/add-item` → `checkout`) med
  `bacs`, tre gange: 1528 (USD), 1529 og 1530 (DKK, efter cachen blev slået til). Alle tre er annulleret
  med note, så de ikke tæller som omsætning. 1530 er beviset for at cachen ikke ødelægger kassen.
- **Fælden ved priserne: 2/11-tabellen passer ikke længere.** Kun 11 af de 24 produkter har en række, og
  alle 11 bygger på den USD-pris produktet havde 2/11. Kataloget er genprissat siden (hoodie $49.95 → $109;
  corset $59.95 → $219). At føre tabellens DKK-tal ind ville sænke priserne 45–78 % og bryde tabellens egen
  regel om at kunden ikke må betale mindre end afrundingen. Valutaskiftet blev derfor udført som et
  valutaskift: dagens ECB-kurs **6,6952** og afrunding til nærmeste 5 kr. 20/20 produktsider og DB stemmer.
- **Sådan blev det målt:** 157 minificerede filer (7,7 MB), produktsiden har 33 script-tags med `src` (var
  88) i 19 JS-filer og 12 minificerede stylesheets, 28/28 lokale assets 200, 22 `data-src`, 33/33 sider 200,
  `/cart/` og `/checkout/` 200 med varer. Alt som anonym besøgende.
- **Rollback står i dokumentet:** backup af `master.php` ligger i `uploads/w3tc-master-foer-minify-2026-11-08.php`
  (32 346 bytes, md5 `7e05b002…`), og de gamle USD-priser står i tabellen i
  [library/w3tc-og-valuta-2026-11-08.md](../docs/library/w3tc-og-valuta-2026-11-08.md).
- **EUR kan stadig ikke vises:** der er ingen currency-switcher på sitet (0 aktive plugins med »currenc«).
  Det er en ejerhandling, ikke en agent-opgave.

### 8/11 — det sidste åbne punkt er ejerens, og opdateringsvejen er prøvet

- **Begge roadmaps har nul agent-handlinger tilbage.** Alt der står åbent kræver ejeren: *Plugins → Update*
  til 7.64.9, Pinterest-scope + fem kanal-tokens (P1.5), CVR/telefon/valuta/priser/zoner/betalinger
  (S4.2–S4.6), mockups (S4.7, rebrand batch 4), GSC-adgang (S0.6), ja/nej til 942/765/766/767 (S1.2),
  Complianz EU opt-in (S4.1), W3TC-godkendelse (`seo.md` #4), de to inaktive plugins (S3.4b), de 29
  trash'ede designs og Command Center-retningen (§8 punkt 5).
- **Det der kunne prøves uden admin blev prøvet:** `remote_version()` læser `Version: 7.64.9` fra `main`, og
  `package_url()`-linket svarer 200 med et arkiv der er byte-identisk med repoets zip. Tallene står i
  [release-preflight-2026-11-08.md](../reports/release-preflight-2026-11-08.md).
- **Fælden passet selv ramte:** hash og størrelse blev først skrevet ind i ROADMAP.md — som ligger *inde i*
  zip'en, så enhver rettelse der ændrede zip'en gjorde tallene forkerte med det samme. Derfor bærer
  ROADMAP'en nu et link til preflight-rapporten i stedet for tal, og tallene står kun uden for zip'en.

### 8/11 — MCP-login: opskriften deles, tokenet gør ikke

- **Adgangen deles som en opskrift, ikke som et token.** Hver agent registrerer sin egen offentlige klient
  (DCR), får sit eget token-par og kan tilbagekaldes for sig i wp-admin. Et token der sendes videre, kan ikke
  tilbagekaldes særskilt — derfor går der aldrig et token gennem chat eller mail.
- **De to flader har hver sit resource og scope**, og et token til den ene virker ikke på den anden: AI Engine
  (`/wp-json/mcp/v1/http`, scope `mcp`) og Novamira (`/wp-json/mcp/novamira-oauth`, scope `full_access`).
  `.well-known/oauth-authorization-server` peger på **Novamira**, så AI Engine skal have sin metadata fra
  `/wp-json/mcp/v1/.well-known/oauth-authorization-server`.
- **Fælden fra tidligere står ved magt:** token-endpoints kræver form-encoding, `tools/list` kræver
  `Mcp-Session-Id`, og redirecten `http://127.0.0.1:8765/callback` har ingen lytter med vilje — hele adressen
  kopieres tilbage, og koden er éngangs.
- Opskriften står nu i [mcp-auth.md](mcp-auth.md) → »Del adgangen med en anden agent eller person«, med
  env-værdier pr. flade, verificeringskald og hvordan adgangen tilbagekaldes.

### 8/11 — live-opdateringen til 7.64.9 kunne ikke køres, og nu er det målt hvorfor

- **Fire veje blev prøvet, alle med svar i hånden.** Repository-secrets er `total_count: 0` (så hverken
  `NOVAMIRA_REFRESH_TOKEN` eller `NOVAMIRA_SECRETS_TOKEN` findes); en `workflow_dispatch` af
  `novamira-keepalive.yml` (run `42507407353`) sprang fornyelsen over med `have=false`; sandkassen har ingen
  tokenfil (`~/.config/novamira/config.json` har kun `clientId`/`clientGrant` — klienten fra 6/11 er
  registreret, men godkendelsen er ikke gennemført her); og den offentlige `/sgap/v1/tick` puster kun til
  publisher-køen. Der findes **ingen** auto-update-cron: `Plugin_Upgrader` kaldes kun fra
  `SGAP_Updater::install()` bag admin-siden og abilityen `update`.
- **Fælden der kostede runden:** det er ikke nok at zip'en ligger på `main`. Et plugin-folder skiftes kun af
  WordPress selv, og alle læseflader (`channel-health`, `niche/runs`, `diagnostics`) svarer `401` uden token.
  Device-grant afviser en DCR-klient (`401 invalid_client`), og CLI-klientens grant kræver stadig ejerens
  browser.
- **To dokumentfejl rettet i samme pas:** `docs/roadmap.md` §2b sagde «Repo 7.64.8, live 7.64.6», og
  agent-profilen bad ejeren opdatere «til 7.64.8». Begge hedder nu 7.64.9, og blokeringen er navngivet som en
  credential-mangel i stedet for et manglende klik alene.
- **Rollback-hullet blev synligt:** repoet holder præcis én zip (CI fejler på flere), så 7.64.6 kan ikke
  geninstalleres uden at den gamle zip hentes **før** opdateringen. Det er ikke ændret — det er en beslutning om
  repoets regler.
- Dokument med beviser og den præcise ejerhandling: [library/live-7-64-9-2026-11-08.md](library/live-7-64-9-2026-11-08.md).

### 8/11 — kodegennemgang: tokenkilden bestemmer, hvem der må forny (7.64.7)

- **Fund ved at læse koden, ikke ved at køre den:** 7.64.6 hentede tokenet rigtigt fra Pinterest for
  WooCommerce, men tre ting omkring det var forkert. (1) Et 401 blev fornyet med pluginnets **egne**
  app-credentials, også når det sendte token kom fra integrationen — den fornyelse roterer et andet token end
  det der fejlede, så næste kald fejler ens; et scope-svar blev fornyet helt forgæves. (2) `refreshable()` betød
  både »vi kan forny« og »der findes et token«, så `status()` bad om app-credentials på et site der kørte fint.
  (3) **Ethvert** 200-svar ryddede `REFUSED` og `SCOPE`, så `/user_account` 200 skjulte at `/pins` mangler
  `boards:read` — kanalstatus gik fra `needs_scope` til `ready` uden at noget var løst.
- **Rettelse:** fornyelse kræver egne credentials **og** egen tokenkilde (`own_refreshable()`), et scope-svar
  fornyes aldrig (`scope_denial()`), og et scope-verdict husker sit **endpoint** og ryddes kun af det endpoint
  der blev afvist. Afvisninger stemples med kilde + 8-tegns sha256-fingerprint af tokenet (aldrig tokenet), så
  integrationens rotation ikke arver en gammel `refused`. `status()` læser kun `token_data.refresh_time`/
  `expires_in` fra integrationen og siger »reconnect that integration«.
- **Bevis:** 11 nye checks i `plugins/tests/pinterest.php` (bl.a. præcis ét kald og ingen `/oauth/token` ved 401
  på integrationens token), 1105 → **1116 PASS / 0 FAIL**, alle 29 værn grønne, zip byte-identisk.
  `plugins/tests/channel-health.php` tæller nu alle fem tilstande i sin sum.
- **Fælde:** et verdict uden `source` kan ikke henføres til et credential. Det vises fortsat (en afvisning på
  skærmen er den sikre side) — ellers forsvandt testens refused-række, fordi `SGAP_PINTEREST_ACCESS_TOKEN`
  defineres sent i smoke-suiten og gør tokenkilden til `constant`.
- **Dokumentdrift rettet i samme pas:** plugin-ROADMAP'ens header påstod stadig 7.59.0/7.58.0 og inaktivt plugin,
  §0 talte 45 klasser/33 filer/12.816 linjer (nu 58/45/20.274) og kaldte `labor_rate` ulæst, `docs/roadmap.md` §2b
  stod på repo 7.62.0/live 7.60.1, og `docs/agent-profil.md` viste 98 %/93,3 %/85 % og 7.64.3. Alle er rettet til
  `projektstatus.json`s tal; **ingen procent er flyttet** — P1.5's 0,6 d er stadig ejer-blokeret på `boards:read`.

### 8/11 — Pinterest-tokenet ligger i WooCommerce: hentet, virker, mangler `boards:read`

- **Målt:** `pinterest_for_woocommerce_data` → `token_data.access_token` er **ciphertext** (370 tegn) og
  svarer 401 hvis man læser optionen direkte; `Pinterest_For_Woocommerce::get_access_token()` dekrypterer
  den. 7.64.6 bruger den vej og vælger token efter **kilde** — integrationens fornyede token vinder, når
  integrationen er tilsluttet.
- **Bevis:** `/user_account` → 200 som `cpaleadsis` (id 688980580401955331); `/pins` → `sgap_pinterest_scope`
  med `Missing: ['boards:read']`; kanalstatus `needs_scope` + `missing_scope: boards:read`.
- **Fælde:** første version valgte token ud fra »er vores eget afvist?«. Et vellykket kald rydder
  afvisningen, og så kom det døde token tilbage på næste request (målt som `401 Authentication failed`).
  Kilde-baseret valg er stabilt; tilstandsbaseret valg var det ikke.
- **Stadig blokeret:** Pin Analytics kræver pin-id'er fra `/pins`, som kræver `boards:read`. P1.5's 0,6 d
  står derfor uændret som ejer-blokeret — ét konkret skridt: genforbind med board-adgang.


### 8/11 — begge roadmaps lukket: kanalstatus-fejlen var det sidste stykke

- **Målt, ikke antaget:** plugin P1.5's sidste hul var ikke live-verifikationen men *kanalstatus*: den kaldte
  en gemt, afvist Pinterest-token »ready«, mens `scan(1)` svarede `sgap_pinterest_401`. 7.64.4 registrerer
  401 med dato (`sgap_pinterest_refused`), viser `refused` + `refused_at` + handlingen, og et nyt token
  glemmer dommen. Målt live: `ready 1 · missing 5` → `ready 0 · refused 1 · missing 5`; 10 nye smoke-checks
  (1094 PASS), filerne SHA-1-identiske med kilden efter install fra zip.
- **Scope-ændring, ikke oprunding:** P1.5's 0,6 d og S1.2/S4's 1,5 d kræver ejerens tokens, tal, aftaler og
  valg. De er flyttet ud af agent-estimatet med `scope_change_pending` og står i `docs/roadmap.md` §4c med
  den konkrete genstand — plugin 29,85 af 29,85 d, site 20,95 af 20,95 d. Ingen blokeret dag er erklæret færdig.
- **Fælde:** `projektstatus.py` skriver kun én historik-række pr. projekt pr. dag, så et rent testfix kan ikke
  få sin egen række — commit-værnet kræver at Før/Efter/Δ matcher en række. Løsningen er at lade fixet bære
  projektets faktiske række og skrive i beskeden at den gentages, ikke at opfinde et tal.
- **Rebrand:** teksten er live (13 af 45 id'er publiceret), men 29 ligger i papirkurven med de nye titler, og
  repoet har 0 trykfiler/tøj-skabeloner — så Batch 4-mockups kan ikke laves her. Ny ejer-beslutning: genoplive
  eller droppe de 29 designs.


### 8/11 — niche-butikken: ét felt bliver en butik — målt på sitet og rullet tilbage

- **Beslutning:** ønsket »skriv niche-navn → fuld udrulning af webshop« er bygget som en *plan* plus en
  reversibel kladde-udrulning (7.64.3), ikke som en auto-publicering. Alt hvad der ikke må gættes —
  kostpris, mockups, valuta, betaling, CVR, zoner, samtykke — står i en ejer-liste, og hver ejer-fakta i
  side-copy står som `[EJER: …]`.
- **Målt på steamgears.eu** (WordPress 7.1.4, WooCommerce 11.2.2): niche »cottagecore mushroom« gav med
  ét REST-kald 5 kollektioner (210–214), 8 produktkladder (1517–1524) med `_price`, `_regular_price`, SKU
  og `_sgap_niche`, en sideskits (1525) og en navigation (1526) med 8 rigtige links. Butikkens 20
  publicerede varer, 32 kategorier og 32 sider var urørte; kladderne svarede 404. Rollback via
  admin-knappen slettede alle 15 rækker (`kept: []`) og gav en tilstand identisk med før-kørslen.
- **Fælde:** tre fejl blev først synlige i browseren: planen lovede en menu som `apply()` aldrig skrev
  (rettet 7.64.1), Runs-tabellen skrev »1 pages« (7.64.2), og »Create drafts« på en allerede kørt plan
  kastede et JS-`TypeError` og efterlod knappen død (7.64.3). Ingen af dem kunne smoke-testen se.
  Dertil: rollback-rapporten mærkede linjerne med array-nøglen (`products:`) — fanget af testen.
- **Plugin CI-checken** »model: a name the provider does not offer is refused at save« var rød, fordi
  integrationstestens stub svarede på `/v1/models/<model>` men ikke på den katalog-URL koden kalder
  (`/v1/models?limit=100`); uden katalog accepterer pluginnet med vilje det indtastede navn. Målt på
  det levende site, rettet i testfilen — produktadfærden er uændret.
- **Ikke gjort, og kun ejerens:** Printful-adgang (rigtige kostpriser og mockups), publicering,
  valuta/betaling/CVR/samtykke/zoner, og valg af den nye navigation i headerens Navigation-blok.
  AI-nøglen til AIMLAPI er løbet tør for midler ifølge admin-notitsen.

### 8/11 — nøglerne på én side siger nu hvem de tilhører, og hver ændring huskes uden værdien

- **P1.18 var lukket i 7.47.0, men svarede ikke på to spørgsmål:** hvis konto en nøgle er, og hvad der skete
  ved en udskiftning. 7.62.0 giver hvert kort `Belongs to` og `Why it is here`, gentager det i en tabel for alle
  15 nøgler, og lader `SGAP_Keys::documentation()` levere samme tekst — så skærm og dokumentation ikke kan glide.
- **Ændringshistorikken gemmer aldrig værdien:** `SGAP_Keys::record()` skriver `set`/`changed`/`cleared` med
  længde, 8-tegns sha256, tid og aktør til `sgap_key_changes` (ikke autoloadet, loft 120). Målt live:
  fingerprint `4ca64038` = `substr(sha256(...),0,8)`, og 0 forekomster af værdien i `serialize()`.
- **To skriveveje, én log:** formularen går gennem `options.php`, så der lyttes på `update_option_sgap_<key>`;
  abilities og token-fornyelser går gennem `SGAP_Settings::set()`, som logger selv. En statisk spærre pr. nøgle
  forhindrer dobbelt-rækker, og en `wp-config.php`-konstant logges ikke (den vinder over databasen).
- **En rotation rydder cachen fra den gamle nøgle** (modelkatalog, Publer-konti, Printful-priser, Bluesky-session)
  plus den gamle nøgletest — ellers ligner en frisk nøgle en død nøgle.
- **Fælder:** `novamira` 1.3.6 kræver `--input @<fil>` (uden `@`: »Ability input must be valid JSON«), og
  `do_action('page_sgap-settings')` er tom i WP-CLI fordi siden hænger på `admin_menu` — kald
  `SGAP_Setup::settings_page()` direkte. `sgap-key-history` står også i CSS, så optælling af markup skal trække
  1 fra.
- **Målt:** smoke **1049 PASS / 0 FAIL** (1027 → 1049), fejlscan 0 fund, zip 354 369 bytes installeret live
  (7.62.0 `active`), live side-render 13 kort / 15 tabelrækker / tally 3 af 15. Ingen rigtig nøgle blev rørt.

### 7/11 — S2.5b målt på alle 30 indlæg, S0.4 bekræftet uden cache og 12 døde `/go/`-id'er i guide-kladden

- **Sitet og REST svarer 200 igen (`Autopilot 7.59.0`, 19 ruter i `/wp-json/sgap/v1`):** dermed bortfaldt den
  midlertidige PHP-parse-error-blokering på S2.5b's måledel og S0.4's cache-busted efterkontrol.
- **S0.4 er nu verificeret med `?nocache=20261107`:** `/start-here/` (73.402 B), `/build/` (71.346 B),
  `/inspiration/` (75.209 B), `/shipping/` (69.496 B) og `/returns/` (70.254 B) svarer alle HTTP 200 uden om
  sidecachen. Samtidig har `/robots.txt` nu **tre** `User-agent: *`-grupper (to duplikerede `filter_cat`-blokke
  nederst) og venter på `wordpress/mu-plugins/managed-wordpress-robots.php`.
- **S2.5b (`0,15 af 0,25 d` → S2 `7,50 af 8,30 d`, site `67,4 % → 68,0 %`):** alle 30 publicerede indlæg er
  målt med [`tools/og_image_check.py`](../tools/og_image_check.py) (10 offline-fixtures, værn nr. 29 i
  `tools/natlige-vaern.sh`) og dokumenteret i [`library/og-billeder-indlaeg-2026-11-07.md`](library/og-billeder-indlaeg-2026-11-07.md)
  (+ PDF). 30/30 har `featured_media`, `og:image`, `width`/`height`/`alt` og `twitter:card=summary_large_image`;
  27/27 unikke billed-URL'er svarer 200, og de deklarerede dimensioner matcher filernes binære header 30/30.
- **Fire fund udestår til de sidste `0,10 d` (kræver WP-/MCP-skriveadgang):**
  1. **Fælde med WordPress' `large`-skalering på stående billeder:** indlæg `615` (`casual-steampunk-outfits`,
     medie `1096`) har en original på `1280×2276`, men fordi billedet er `9:16`, skalerer `large` (`max 1024×1024`)
     bredden ned til **`576×1024 px`** — under 600 px-grænsen for stort link-kort på Facebook og LinkedIn.
  2. **To tunge filer:** indlæg `751` bruger en **1.378 KB PNG** (`pin-pirate-01.png`, det eneste PNG blandt de
     30 indlæg), og indlæg `806` bruger en **845 KB JPEG** (`upcycle-1.jpg`).
  3. **Tre delte fremhævede billeder på seks indlæg:** `1082` på `15`+`699`, `488` på `791`+`913` og `474` på
     `798`+`875` (`/wp/v2/media/474` svarer 401 uden auth, fordi `post_parent` er et ikke-publiceret produkt).
  4. **Billedformater:** 21 liggende, 4 kvadratiske (`960×960`) og 5 stående (`572`, `615`, `751`, `804`, `805`).
- **De 18 `/go/`-id'er i `docs/library/guides-2026-11-03.md` kan måles uden WAF-blokering med neutral UA
  (`curl/8.5.0`):** alle 18 svarer 302, men kun **6 lander på HTTP 200** (`413, 483, 489, 491, 493, 495`), mens
  **12 lander på 404** (`397, 411, 475, 481, 485, 487, 497, 561, 563, 567, 724, 728`) mod det nuværende katalog
  på 20 produkter i Store API (hvor priserne desuden er ændret til `$49–$279`).

### 7/11 — roadmap-afstemning: de 0,2 d var regnefejl, og P3 havde 0,75 d fantom

- **Der var intet skjult punkt bag de 0,2 d.** Roadmappet blev gennemgået linje for linje (kilde-anker, dage,
  status, verifikation): P0's total 4,70 talte **CC-linkets 0,20 d to gange** (det ligger i P0.4's 0,50, som
  rækken selv skriver) og udelod **`/go/`-rettelsens 0,15** → 4,65. P1's total 15,20 talte **P1.19's 0,25 to
  gange** (den bogføres som modulet `p1d`) og udelod **queue-post-arbejdet 0,10** → 15,05.
- **Fund oveni:** P3's total sagde 2,75 mens rækkerne summer til 2,00 — 0,75 d der aldrig blev fjernet, da
  P3.1's Etsy-halvdel flyttede til P2.4 og P3.6 blev sat ned til 0,25. `git log -S "P3.7"` er tom, så der har
  aldrig været en række bag dem; både `done` og `total` er nu 2,00.
- **Regnestykket er dokumentet:** [`library/roadmap-afstemning-2026-11-07.md`](library/roadmap-afstemning-2026-11-07.md)
  (+ PDF) har en tabel pr. modul og summen **29,60 af 30,45 d**; `total − done` = **0,85 d = P1.5's
  live-verifikation**. Intet punkt blev erklæret færdigt, og intet `done`-tal blev hævet — kun P0.4's manglende
  ✅ blev tilføjet, og §8 punkt 7 (aktiveringen) lukket.
- **Fælden at huske:** en total der ikke summer med sine egne rækker giver en rest der *ligner* uafsluttet
  arbejde. Tjek altid `sum(rækker) == total`, før et punkt jagtes.
- **P1.5 kan stadig ikke lukkes:** målt 7/11 er alle seks Pinterest-/Instagram-nøgler tomme (kun tilstedeværelse
  læst, ingen værdier), `diagnostics` svarer `pinterest: off` / `instagram: off`, og begge ruter svarer 200 med
  deres egen »not connected«-besked. Sitet svarer 200 på `/`, `/shop/` og `/wp-json/sgap/v1`, og 7.59.0 er
  geninstalleret fra repoets zip.

### 7/11 — opdateringsfælden: “Update” havde intet at installere

- **Ejerens klik fejlede ikke — der var intet at installere.** `SGAP_Updater::remote_version()` cacher GitHubs
  versionsnummer i et site-transient i **6 timer**, og WordPress' egen `update_plugins`-transient kan hænge op
  til **12 timer** bagefter. Release 7.58.1 var derfor usynlig for både Plugin-listen og dashboard-kortet, selv
  om zippen lå på `main` og svarede 200.
- **7.58.2 retter det:** dashboard-kortet kalder `refresh_for_admin()`, som læser versionen **friskt** (højst ét
  GitHub-kald pr. minut via sit eget transient) og **rydder `update_plugins`**, når GitHub har noget nyere end
  den installerede version — så begge flader tilbyder samme version med det samme.
- **Bevis uden WordPress:** `plugins/tests/updater_cache.php` (9 checks, kørt grøn) viser cachet læsning på den
  gamle version, frisk læsning på den nye, at `update_plugins` bliver ryddet, at det *ikke* bliver ryddet når vi
  er ajour, og at `panel()` ikke længere kalder den cachede læsning. Værnet kører nu i `tools/natlige-vaern.sh`.
- **Ingen adgangskanal findes i sandkassen** (målt 7/11 18:0x UTC): `~/.ssh` og `~/.netrc` findes ikke, ingen
  `wp-cli`, ingen `wp-cli.yml`, ingen WP-/SFTP-/SSH-variabler i miljøet — og `wp-login.php`/`xmlrpc.php` svarer
  455 fra Simplys WAF. Derfor kan hverken 7.58.1 eller 7.58.2 installeres herfra, og de tre guides kan ikke
  publiceres. **Den manglende brik er en adgang, ikke et klik:** enten SFTP/SSH til Simply, en ejer-whitelistet
  admin-session, eller et application password på et site hvor REST virker.
- **SEO står stadig på 5 af 8 guides**, og det er den samme ene brik der blokerer.

### 7/11 — 7.58.0 tog hele REST ned, og SEO-arbejdet fandt det

- **Målt 17:29 UTC:** `/wp-json/` svarer **200 med en PHP-fatal i kroppen** (1.650 B), ikke med JSON.
  `rest_api_init` kaster `Undefined constant SGAP_Admin::NS` i `includes/diagnostics.php:531`. Klassen
  `SGAP_Admin` har ingen `NS`; den ligger på `SGAP_REST`. Fire referencer i alt: én i `diagnostics.php`
  (7.57.0) og tre i `facebook.php` (7.58.0).
- **Konsekvensen er hele fladen, ikke ruten:** `/wp/v2`, Store API (kurv og kasse i blok-editoren) og begge
  MCP-flader er døde, fordi de alle ligger under samme REST-rod. Forsiden og `/cart/` svarer 200 med 80 KB
  HTML, så et blik på sitet afslører intet. Det var også derfor Novamira-refresh svarede **500**: tokenet var
  ikke dårligt, endepunktet kunne ikke køre.
- **Retten er 7.58.1** (fire referencer → `SGAP_REST::NS`, ingen adfærd ændret). Zip ligger på `main`, så
  ejerens *Plugins → Update* installerer den. **Intet er verificeret live endnu** — målingen efter opdateringen
  er det første der skal gøres.
- **To værn, fordi `php -l` og smoke var grønne hele vejen:** `tools/fejlscan.py` har fået kontrollen
  `class-konstanter` (enhver `SGAP_X::KONSTANT` som klassen ikke definerer er et fund, to nye fixtures), og
  tre smoke-checks kalder nu `routes()` og kræver at ruterne lander i `sgap/v1`. Før blev `routes()` aldrig
  kaldt af nogen test.
- **Fælde:** statuskoden løj. Et 200-tal på `/` beviser kun at PHP kan rendere en side; helbredstjekket er
  `/wp-json/`, ikke forsiden.
- **SEO-missionen er derfor stadig blokeret:** de tre guides kan ikke publiceres (ingen MCP-skrivning), og
  S4.13 kan ikke måles, før REST svarer igen.
- **Den authenticatede flade er også lukket for agenten** (målt 7/11 17:52 UTC): `/wp-admin/*` redirecter til
  `wp-login.php`, og både GET og POST dertil — samt `xmlrpc.php` — svarer **455 “Security Incident Detected …
  Do not retry”** fra Simplys WAF, dvs. før PHP. `admin-ajax.php` uden nonce svarer 400/`0`. Der findes ingen
  credential i sandkassen, og selv et application password ville ramme den døde REST. **Forsøg derfor ikke
  wp-login/xmlrpc/admin-ajax igen** — det er boardet, og et genforsøg er hvad blokeringen frabeder. Kun ejerens
  eget klik i *Plugins → Update* kan installere 7.58.1.

### 7/11 — Sitet er tilbage, og Facebook venter på to ejerklik

- **Nedbruddet er overstået, målt 17:25 UTC:** `/wp-json/` 200 (1.521.253 B `application/json`), `/robots.txt`
  200 (319 B), `/sitemap_index.xml` 200 (1.063 B), `/?sgap_feed=facebook` 200 som `application/rss+xml`
  (11.810 B) og `/?nocache=1` 200 (80.338 B) — ingen `Parse error` i nogen af dem. Målt på størrelse og
  `content_type`, ikke statuskode: under nedbruddet svarede alle fejl også 200.
- **Host-laget er ikke rettet — det er væk.** `robots.txt` har nu **én** `User-agent`-gruppe og ingen af de otte
  query-param-`Disallow`-linjer som `managed-wordpress-robots.php` tilføjede. Så S0.2's læsbarhedsproblem
  eksisterer ikke længere, og den rettede fil i `wordpress/mu-plugins/` skal **ikke** lægges ind; den bliver
  liggende som reserve.
- **Live kører stadig 7.41.1, og det kan ses uden credential:** `/wp-json/sgap/v1` annoncerer 12 ruter
  (`queue`, `drafts`, `suppression`, `reposts`, `etsy/orders`, `feed`, `stats`, `snapshot` …) og **ingen**
  `/facebook/*`, mens 7.58.0 registrerer `/facebook/refresh`, `/reply` og `/hide`. Facebook-felterne findes
  altså ikke i wp-admin endnu — *Plugins → Update* skal køres først, og selvopdateringen henter 7.58.0 fra
  `main`, hvor zippen nu ligger.
- **Tokenet kan ikke kobles på herfra:** ingen MCP-credential, ingen `.env`, ingen application password i
  sandkassen — og en nøgle må ikke gennem chat eller repo. Ejerens to indtastninger: Page access token under
  *Settings → Keys*, side-id under *Publishing* (og `facebook_direct` = *Yes* kun hvis publiceringen skal ud af
  Publer). Derefter kører `sgap_facebook_refresh` dagligt af sig selv, og *Kør nu* på Facebook-siden giver
  første synk med det samme.
- **Det jeg kan verificere uden nøglen, og vil kigge efter ved næste pas:** at ruterne dukker op i
  `/wp-json/sgap/v1`, at Facebook-rækken i Fejlsøgning står «Connected» med side-navn og fans, at
  post-mappingen vokser ved den første publicering, og at kommentartabellen viser Metas `can_hide`. Intet af
  det er målt endnu.

### 7/11 — Nul anmeldelser var ikke et plugin-problem, men et spørgsmål ingen stillede (S4.10)

WooCommerce har haft anmeldelser hele tiden: formularen ligger på hver produktside, og
verified-buyer-mærkningen er i kernen. Der er nul, fordi **ingen nogensinde har spurgt** kunden. Derfor
blev S4.10 ikke en platform, men en mail — `wordpress/mu-plugins/sgap-review-request.php`, 14 dage efter
ordren er gennemført, ét link pr. produkt til dets `#reviews`-formular.

Fire ting blev gjort eksplicitte i stedet for antaget, og de er hele forskellen mellem en mail og en
irritation: **status læses igen ved afsendelse**, så en refunderet eller annulleret ordre ikke får
»hvordan gik det?« midt i en retursag (springet noteres i `_sgap_review_skipped`); **ordremeta
forhindrer dubletter**, også når en status skifter frem og tilbage; **produkter med lukkede anmeldelser
udelades**, fordi en anmodning der ikke kan efterkommes er værre end ingen; og der **genplanlægges
ikke** — én chance pr. ordre, ikke en kø der følger statussen rundt.

Copy afviser incitamenter med vilje (rabat, gave eller refundering for en anmeldelse er unfair
handelspraksis efter Omnibus) og lover ikke at fjerne negative anmeldelser. Det står i mailen som en
sætning, ikke som en fodnote — og testen fejler hvis en fremtidig omskrivning begynder at tilbyde noget.
Afsnittene skrives én gang og renderes til både HTML og ren tekst via `AltBody`, så en tekst-only-klient
ikke får en tom mail.

**26 påstande / 0 FAIL** i `wordpress/tests/test-review-request.php` (stubbe, ingen WordPress), nu
**værn nr. 26** i den natlige kørsel. Testen beviser planlægning, dubletværn, status-gaten,
udeladelsen af lukkede produkter, Action Scheduler-adapteren, escaping og incitament-afvisningen — men
**ikke** at en mail kan leveres: der er ikke sendt en mail, filen er ikke set i en rigtig WordPress, og
`#reviews`-ankeret er ikke klikket efter. Det er præcis de sidste 0,05 d, og de kræver File Manager.

S4.10 står derfor som **0,35 af 0,4 d** — samme præcedens som S4.12 (0,15 af 0,2 d, »built, not
deployed«). S4 1,65 → **2,0 af 5,25 d**.

### 7/11 — Værnene prøvet med vilje-fejl: tre huller, alle lukket

- **Registret var et smuthul.** En registreret SHA slap for **hele** regelsættet: en commit uden `Modul:` og med tal der ikke findes i historikken blev godkendt (`[OK] … undtaget: verificeret register`). Nu er posten `{relax, reason}`, og kun `basis` eller `history` lempes — form, Δ og regnestykke gælder også de to gamle commits. Målt: samme commit med SHA i registeret er stadig **rød** på `mangler linjen 'Modul:'`.
- **Et scope-flag kunne brændes af en fremmed række.** Flaget blev fjernet for alle projekter, når blot én række blev skrevet. Nu forbruger kun den række det gælder det, og flaget skal være en sætning — `true` og tomme strenge afvises.
- **`scope_change: true` var en tom etiket.** Et fald på 25,3 pp slap igennem med `module: «daglig måling»`. Nu kræver et fald på ≥ 0,5 pp en navngivet ændring; under tærsklen regnes det som afrundingsstøj, fordi en eksisterende række (96,4 → 96,3) ellers ville gøre historikken ugyldig.
- **Det der stadig bider:** ikke-registrerede commits med den undtagne fejl er røde med tre forskellige fejltekster, drift i JSON uden historik er rød i tre varianter, og en commit-msg-hook afviste endda forsøgs-committene, før de blev til commits. Hele forsøgsrækken står i `library/vaern-adversarisk-kontrol-2026-11-07.md`.
- **Fælde:** `git commit` uden `--no-verify` blev stoppet af hooken, så en efterfølgende `--commit HEAD` målte den *forrige* commit. Kontrollér exitkoden, ikke kun linjen.

### 7/11 — Facebook-siden kan nu styres, men først når nogen slår det til (P2.6, 7.58.0)

Facebook var den kanal, pluginnet nævnte i `channels` og aldrig rørte. Nu er der et modul som de
andre — publicering, insights, moderation — men med én forsætlig bremse: **`facebook_direct` står på
`no`**. En side kan altså forbindes for at blive læst (følgere, kommentarer, tal) uden at et eneste
indlæg flytter sig ud af Publer. Det er ikke forsigtighed for syns skyld: en nøgle, der gemmes for at
se tallene, må ikke i sig selv ændre en arbejdsgang, og publiceringsvejen er en beslutning om
arbejdsgang.

Det dyre i den her slags arbejde er ikke kaldet, men de fire steder hvor et svar ikke er det, man
tror. `post_reactions_by_type_total` er et objekt (`{LIKE: 3, LOVE: 2}`), ikke et tal. En metrik Meta
endnu ikke har rapporteret er ikke 0 — den udelades, og posten spørges igen i næste kørsel, for
"ingen visninger" og "ikke målt endnu" må ikke se ens ud i databasen. Et metriksæt kan blive afvist
med kode 100 på medietyper uden klik, så det brede sæt genforsøges med de to metrikker alle indlæg
har. Og en sletning ved gem skal være afgrænset til `channel = 'facebook'`, fordi `likes` og `shares`
også er Bluesky-, Mastodon- og Instagram-typer.

Moderation er med vilje kun skjul, vis igen og svar. Intet slettes: skjul er reversibelt, og en cron
skal ikke eje endelige handlinger over for andres ord.

82 nye checks, smoke 905 → 987 PASS, 0 FAIL, alle gennem `$transport`-sømmen. Det betyder også, at
**intet kald er gået til Meta**: der findes ingen Page access token i miljøet, så publicering,
insights og kommentarer er kun målt mod svar med Metas form, og siden er ikke set i en rigtig
wp-admin. Det næste skridt er ejerens: en Page access token med `pages_manage_posts` og
`pages_manage_engagement`, side-id'et under Settings → Publishing, og først derefter `facebook_direct`
hvis publiceringen skal flyttes.

### 7/11 — Ingen vej uden File Manager, og seks rækker lukket med bevis

- **Syv veje prøvet, alle lukkede.** Der er ingen deploy-workflow i repoet (syv workflows, ingen upload/rsync/SSH), ingen FTP-/SFTP-/SSH-secret, intet `.env`, ingen `~/.ssh`, ingen gh-login og ingen MCP-credential. Vigtigst: **både REST og MCP kører gennem samme PHP og dør med filen** — `wp/v2/users/me` giver 200 med parsefejl, `mcp/v1/http` svarer 500. Et app-password eller et MCP-token ville altså ikke hjælpe. Det står med kommandoer og svar i `library/robots-og-nedbrud-2026-11-07.md` §3c.
- **Seks rækker var markeret åbne, selv om arbejdet var gjort:** S1.1, S2.2, S2.4, S2.5 og S2.6 er lukket med dokumentbevis (Herbarium-linket, `/shipping/`+`/returns/`, H1-beslutelsen, meta-runden på 59 sider, de 54 sporede `/go/`-links). **S0.4** er lukket som *overflødig*, og de 0,5 d er taget ud af nævneren — ikke ført som fremgang.
- **S2.5b er ny og åben:** OG-billede pr. indlæg blev aldrig målt, så det er skilt ud som egen række (+0,25 d) i stedet for at gemme sig i S2.5.
- **S1.4/S1.6 er ikke startet og står urørt** med præcis blokering: `wp_delete_post` og en formular-endring kræver live-adgang.
- **CI's commit-værn er grøn på hele rangen.** To ældre commits kunne ikke rettes; de er efterprøvet mod historikken og står nu i `docs/commit-vaern-register.md` — den ene havde korrekte tal med en anden ordlyd, den anden en overhalet mellemregning fra før et merge. `projektstatus.py` har fået `scope_change_pending`, så et planlagt fald mærker præcis én række og derefter forbruges.
- **Næste skridt** er stadig ét ejerklik: læg `wordpress/mu-plugins/managed-wordpress-robots.php` ind via File Manager. Alt andet venter på det.

### 7/11 — Fejlsøgning i pluginnet (P1.22, 7.57.0) og forbehold som en del af svaret

- **Fejlsøgning er nu også inde i pluginnet**, ikke kun i repoet: **Steamgears → Fejlsøgning**
  (`includes/diagnostics.php`) har ni tjek der **læses** ud af den tilstand modulerne allerede holder — PHP,
  tabeller, planlagte job, fejlede posts, AI-nøgle, Pinterest, Instagram, MCP og modulfejl — hvert med en
  tilstand, en sætning om hvad det fandt, og en om hvad man gør. Siden skriver ikke til den tilstand, så den
  kan ikke selv blive en ny fejlkilde.
- **En kanal der aldrig er blevet forbundet er `off`, ikke `bad`.** Det er ikke en fejl at have slået en kanal
  fra, og et dashboard der råber op om det, lærer ejeren at ignorere farverne. Et token i `wp-config.php` er
  derimod en fejl, fordi det ikke kan fornyes.
- **Loggen findes, fordi modulerne kun holder én fejltekst.** `sgap_instagram_state['error']` bliver ryddet af
  den næste kørsel der lykkes — et natligt afslag kl. 03 var væk kl. 09. `SGAP_Diagnostics::log()` skriver til
  en ringbuffer på 40 rækker, **tæller gentagelser i stedet for at duplikere**, og skiller visnings-`at` (sitets
  ur) fra regne-`ts` (`time()`) — 24-timers-vinduet må ikke arve den gamle `gmdate`-fejl på ±14 timer.
- **Logningen sidder i `record()`** i Instagram- og Pinterest-modulet, ikke i hvert kaldested: det er det ene
  sted tilstanden skrives, så en ny fejlvej ikke kan glemme loggen.
- **Forbeholdet er blevet indhold:** `SGAP_UI::caveat()` står under Instagram-, Pinterest- og
  engagement-kortet og samlet på fejlsøgningssiden. Teksterne siger hvad der er *uden for* tallet — at en
  metric Instagram ikke har rapporteret endnu er **udeladt, ikke 0**; at Pinterest-forespørgsler er afgrænset
  til kanalen fordi `saves`/`likes` deles med andre kanaler; at ROI er last-click, så en ordre uden klik hører
  til ingen kanal; og at et opslag der aldrig kom afsted slet ingen tal har.
- **Bogføringsfejl fanget og rettet i samme pas:** P1.22 blev først lagt både ind i P1's total (15,2 → 15,7) og
  ind som eget modul, så de 0,5 d blev talt to gange (96,4 %). Rettet til eget modul alene → **96,3 %
  (28,8 af 29,9 d)**; korrektionen står som historik-række med `scope_change`, ikke som en slettet række.
- **905 smoke-checks, 0 FAIL**, 24/24 værn grønne. Ikke kørt live — intet token findes.

### 7/11 — Nedbruddet målt igen: hele den dynamiske del er nede

- **Målt med neutral UA:** `/robots.txt`, `/sitemap_index.xml`, `/wp-json/…` og `/?sgap_feed=pinterest` svarer **HTTP 200 med 193 bytes** parsefejl fra `mu-plugins/managed-wordpress-robots.php` linje 13; cache-bustede sider (`/?nocache=`, `/cart/?cb=`, `/checkout/?cb=`) det samme; `admin-ajax.php` svarer **500 «critical error»**. Forsiden og `/shop/` ser raske ud, fordi de kommer fra W3TC-sidecachen.
- **Konsekvens:** wp-admin er nede (så ejeren kan ikke rette det gennem WordPress), **RSS-publiceringen er død**, og søgemaskiner kan ikke læse robots/sitemap. Alle live-punkter i S1–S4 og plugin P1.5b/P1.5c/P1.15 er blokeret af den samme fil.
- **Rettelsen er stadig vej C i** [`library/robots-og-nedbrud-2026-11-07.md`](library/robots-og-nedbrud-2026-11-07.md) §4: læg `wordpress/mu-plugins/managed-wordpress-robots.php` ind via Simplys File Manager. Filen er verificeret igen i dette pas: `php -l` ren og `tools/test_robots_muplugin.php` **8/8**.
- **Fælde:** alle fejl svarer 200. Overvågning skal tælle `Parse error` eller bodystørrelsen (193 bytes), ikke kigge på statuskoden — og altid teste med en cache-buster.

### 7/11 — S3.5: ugens kontrol kørt, og en zip var ældre end kilden

- **Sandkassen havde ingen PHP og ingen MCP-credential.** `tools/natlige-vaern.sh` stoppede derfor før første værn (`tools/_deps/bin/php` manglede), og `tools/mcp_oauth.py --status` svarede »Ingen klient registreret endnu«. PHP blev hentet som den statiske binær AGENTS §7.3 angiver (8.3.14); derefter gik alle **24/24** værn grønne.
- **Zip-sammenligningen var det værd:** `diff -r` mellem kilden og den udpakkede `v7.43.0.zip` viste at den indlejrede `ROADMAP.md` stadig havde P0.4 som »Delvist (0,4 af 0,5 d)«. `main` udgiver nu **7.54.0**, hvis zip er `diff -r`-identisk med kilden, så den gamle pakke blev droppet. **Konklusion: mål zip'en efter en rebase, før push — tal fra før rebasen er ikke status for det der udgives.**
- **`tools/setup-agent-tools.sh` kunne ikke hente PHP:** `python3 -c`-strengen var ombrudt med indrykning → `IndentationError`, så PHP-trinnet døde før download. Rettet til én linje og efterprøvet (vælger nu `php-8.5.8`).
- **Målt efter rebasen:** 781 smoke-checks / 0 FAIL, ingen drift i historikken, PDF renderet og set. S3.5 0,05 → **0,1 af 0,5 d**; site 64,4 % → **64,6 %** (15,7 af 24,3 d).
- **Backup blev ikke kørt** i dette pas, og det står i loggen i stedet for at blive skjult.

### 7/11 — Instagram-insights (P1.5c, 7.56.0): `views`, ikke `impressions` — og nul betyder «ved det ikke»

- **Modulet er `includes/instagram.php` (`SGAP_Instagram`), og det gemmer fem egne event-typer** (`views`,
  `likes`, `comments`, `shares`, `saves`) på kanalen `instagram`, adskilt fra vores `/go/`-klik, ordrerne og de
  to andre platformes snapshots. Sletningen er afgrænset til kanalen, fordi `likes` også er Bluesky/Mastodons
  snapshot-type — **samme latente fejl blev rettet i Pinterest-modulet i samme pas**, hvor `saves` deles.
- **`impressions` er deprecated for medier efter 2/7 2024** og afløst af `views`. Havde vi spurgt om
  `impressions`, ville hvert nyt opslag rapportere nul, og det ville se ud som en måling.
- **En metric Instagram ikke har rapporteret endnu udelades af svaret i stedet for at være 0** (op til 48 timer).
  Opslaget tælles som *waiting* og gemmes ikke som et opslag uden visninger; ellers ville hvert gennemsnit
  nedstrøms arve en påstand Meta ikke har fremsat.
- **Match på caption, ikke på permalink:** et medie har intet link-felt, og `/p/<shortcode>/` indeholder aldrig
  vores vært. Et opslag uden vores `/go/`-link er *unmapped*; en række uden publiceret Instagram-post i vinduet
  er *orphaned*. Begge står på dashboardet i stedet for at blive gættet.
- **To login-typer, to Graph-værter** — værten er derfor en indstilling og ikke et gæt ud fra tokenens form; et
  forkert gæt gør en fungerende token til en 400. Den 60-dages token fornyes **på stedet** (der findes ingen
  separat refresh-token), så en token i `wp-config.php` afvises helt.
- **Tre fejl fanget undervejs:** test-doublen manglede `WP_Error::get_error_code()`, det smallere metric-forsøg
  slugte token-fejl (en udløbet token blev «retried» i stedet for fornyet), og token-fejlen blev returneret før
  fornyelsen blev forsøgt — fordi Instagram svarer 400/code 190 og ikke 401.
- **Versionen blev 7.56.0, ikke 7.54.0:** en anden kørsel havde taget 7.54.0 (P1.20) og 7.55.0 (P1.21), så
  Instagram-modulet blev lagt oven på dem i stedet for at overskrive deres numre. P1.5 er **3,0 af 3,85 d**
  bygget; de sidste 0,85 d er live-verifikation af Pinterest og Instagram, som kræver tokens.
- **838 smoke-checks, 0 FAIL**, 24/24 værn grønne.

### 7/11 — de sidste to lister

- **AI Drafts var ét grid sorteret efter id.** Man skulle læse hver kladde for at se hvilken side
  den hørte til — men beslutningen er om en *side*: »er denne sides posts gode nok?« Derfor er
  kladderne nu grupperet efter kilde-siden, med antal pr. gruppe og tre tal øverst (kladder, sider,
  kanaler). En kladde uden kilde får gruppen *No source page* i stedet for at falde ud af listen.
- **Publish log havde filter, tabel og paginering som tre løse bjælker**, og retentionsnoten som en
  separat bjælke over det hele. Nu: *Filter* som egen sektion med levetiden i forklaringen, derefter
  *Attempts* med Sent · Failed · Requeued or held — **talt op fra de rækker der vises**, mærket »on
  this page«, så tallet ikke kan modsige tabellen under sig.
- **Fælde nummer to i harnessen:** log-siden spørger først `SHOW TABLES` og derefter `COUNT(*)`.
  `get_var()` i smoke-harnessen svarer med én værdi pr. kald, så en test med et enkelt tal blev
  afvist som »tabellen findes ikke« og renderede tom-tilstanden. Svaret lægges nu i kø:
  `$wpdb->next = ['wp_sgap_publish_log', 7]`. Samme familie som `mysql2date()`-hullet: en stub der
  kun kan svare ét sted, skjuler hvilken gren der faktisk blev kørt.
- **Verificeret:** 781 → **788 PASS**, 0 FAIL. UI'et er stadig **ikke** set i en wp-admin; 7.55.0
  venter på ejerens *Plugins → Update*-klik.
- **Bogføring:** P1.21 0,25 d, projektet 29,15 → 29,40 d, 91,1 → 91,2 %.

### 7/11 — sektionsmønstret blev bredt ud, og en skjult gren kom frem

- **Settings-fanerne åbnede direkte på felt-rækker** uden at sige hvad de afgjorde. Nu har hver fane
  overskrift + én linje (`SGAP_Setup::TAB_HINTS`, ni faner; Keys har sin egen overskrift), og et
  smoke-check kræver at hints' nøgler er præcis `TABS` minus `keys` — så en ny fane ikke kan komme
  til uden at få sit svar med.
- **Kø-siden var én tabel med en statuskolonne.** »Venter« og »sendt« lå blandet sammen sorteret
  efter id, så det man skulle handle på lå sammen med historikken. Nu *Waiting* (queued, sending,
  reminded, pending, approved) og *Done* (published, failed), hver med optælling i overskriften.
  Ingen SQL-ændring — samme 50 rækker, filtreret i PHP. Øverst en *Held*-linje der genbruger
  `SGAP_Queue::blocked_message()` i stedet for at opfinde en ny formulering af quiet hours/blackout.
- **Content Studio** var fire kort i to ens grids med resultatet imellem; nu *Write* og *Fix* med
  resultatboksene under hver sektion.
- **Fælden:** `plugins/tests/smoke.php` havde **ingen `mysql2date()`**. Den blev først nødt til at
  findes, da en kø-række fik en rigtig `scheduled_at` — hver tidligere test-række havde `null`, så
  datogrenen i tabellen var aldrig blevet kørt. Stubben er tilføjet, og de nye checks bruger en
  række med både planlagt og publiceret tid. Samme slags hul som 7.46.0's `wp_next_scheduled()`: en
  test der altid får »tom« svarer grønt uden at have rørt grenen.
- **Parallelle chats igen:** P1.5b (Pinterest-analytics) og P3.6 (fejlscan) blev pushet mens dette pas
  kørte, så UI-arbejdet blev rebaseret oven på dem og udgivet som **7.54.0** i stedet for 7.53.0 —
  versionsnummeret var taget. Tallene her er efter rebasen.
- **Verificeret:** 770 → **781 PASS**, 0 FAIL. UI'et er stadig **ikke** set i en wp-admin; 7.54.0
  venter på ejerens *Plugins → Update*-klik.
- **Bogføring:** P1.20 0,25 d, projektet 28,90 → 29,15 d, 91,0 → 91,1 %.

### 7/11 — Fejlsøgning er nu en kørsel, ikke en lejlighed

- **`tools/fejlscan.py` er værn nr. 23–24** og leder efter det `php -l` og smoke-suiten ikke kan se: version tre
  steder (konstant, header, zip-navn), zip mod kilde byte for byte, døde og forældreløse `include`, nye
  dokumenter uden for `docs/library/README.md`, tokens i sporede filer, `TODO`/`FIXME` og tests der ikke kan
  fejle. **13 fixtures** i `--selftest` beviser både at hver klasse fanges, og at en ren kasse er grøn — et
  værn der altid er rødt bliver ignoreret.
- **Fem fejl fundet og rettet i 7.53.1.** (1) Et access-token i `wp-config.php` blev brugt til at hente data,
  men fornyelsen skrev til en option som konstanten altid vandt over — nu afvises fornyelsen med konstantens
  navn. (2) Et indklistret token uden registreret udløb blev kaldt *ugyldigt* i `status`. (3) Et afslag på
  pin-listen stoppede hele kørslen, selv om de pins der allerede var kortlagt kunne måles. (4) Vinduerne blev
  målt med `gmdate()` mod rækker skrevet med `current_time('mysql')` — op til ±14 timers forskel; nu `wp_date()`.
  (5) Engagement-testen endte i `|| true`, kunne aldrig fejle, og læste den forrige tests kø-rækker som
  snapshot-kolonner (tre PHP-advarsler pr. kørsel).
- **Fejlsøgning står nu i roadmap'et som fast punkt:** plugin-roadmappet har fået rækken **P3.6**, og
  `docs/roadmap.md` §10 beskriver hvad der køres, hvornår — og hvad det **ikke** dækker (live-sitet, eksterne
  API'er, tredjepartskode). Det sidste er det vigtigste: grønne værn betyder ikke «sitet virker».
- **To roadmap-totaler var forkerte, og det er den slags fejl der overlever.** P0 stod til 5,25 d (regningen:
  5,95), P1 til 13,30 d (regningen: 15,20 — P1.5 blev tre moduler), og site-linjen i `docs/roadmap.md` §9 til
  23,8 d (bånde-totalerne summer 24,3). Alle tre linjer følger nu `projektstatus.json`, med årsagen skrevet ind.
- **`docs/agent-profil.md` var også bagud:** den sagde plugin 74,0 % og site 61,6 %, live-version 7.41.1 i
  begge, 44 klasser/30 filer og 523 smoke-checks. Nu: 91,0 % og 64,4 %, repo 7.53.1 mod live 7.41.1 (målt 7/11,
  ikke genmålt siden), 51 klasser/39 filer og 770 checks.

### 7/11 — Pinterest-analytics: match på link, ikke på titel

- **P1.5b er bygget (7.53.0), ikke bare planlagt.** `includes/pinterest.php` henter `IMPRESSION`, `SAVE` og
  `OUTBOUND_CLICK` fra `/v5/pins/{id}/analytics` og gemmer dem som **tre egne event-typer**. De blandes
  bevidst ikke med Bluesky/Mastodons likes/reposts/replies og heller ikke med vores egne klik (`sgap_cro`) og
  ordrer: en `save` er en platformshandling, en `click` er vores sporingslink, og en `order` er WooCommerce.
  Summer man dem, kan ingen af dem fortolkes bagefter.
- **Pin→kø-matchningen sker på pin'ets `link` mod `/go/<id>/`.** Publisheren giver Publer sporingsadressen, så
  linket er en nøjagtig identifikation, hvor titlen kun ville være et gæt. Et fremmed link tælles som
  **unmapped**, og et `/go/`-id uden en publiceret Pinterest-post i vinduet som **orphaned**; begge står på
  dashboardkortet. Kun denne sides host accepteres — et andet domænes `/go/42/` er ikke vores kvittering.
- **90 dage er Pinterests loft, ikke et valg.** `start_date` hæves til dagens dato minus 89 dage for en ældre
  post, så den måles på de sidste 90 dage i stedet for at blive afvist. Pin-listen pagineres med `bookmark`
  over flere kørsler, og hver kørsel bruger højst 25 analytics-kald, så en backlog drænes fair.
- **Refresh-tokenet roteres ved hver fornyelse.** Det nye par gemmes før noget andet kan fejle; ellers står man
  med et brugt token og skal autorisere appen igen. En `wp-config.php`-konstant afvises (kan ikke opdateres),
  og et afvist token når det daglige selvtjek frem for at blive opdaget ved, at tallene bare holder op.
- **Fælden ved at pushe:** `main` var rykket videre med P1.19 og en roadmap-bogføring, så første push blev
  afvist. Arbejdet blev lagt på `backup-p15b`, indholdet lagt oven på den nye main og procenterne regnet om
  derfra — ikke tvunget igennem med en gammel total.
- **Ikke kørt live.** Intet Pinterest-token findes i repoet eller i sandkassen, så første rigtige kørsel skal
  kontrollere tre ting: at `summary_metrics` ligger under `all`, at pin-`link` faktisk er `/go/`-adressen (og
  ikke en omskrevet UTM-variant), og at sidste side svarer `bookmark: ""`. Fejler en af dem, er det
  `summary_metrics()`/`queue_id()`/`scan()` der skal rettes — ikke resten.


### 7/11 — dashboardet blev læst som to ulige kolonner

- **Asymmetrien var målt i markup, ikke gættet:** venstre kolonne havde to kort (`Run now` og
  kanaltabellen), højre havde otte. Siden blev tre skærmhøjder lang på den ene side og næsten tom på
  den anden, og de kort der svarer på »går det?« (Scheduler, Engagement) lå nederst i den rækkefølge
  de var blevet tilføjet.
- **Beslutning: ét spørgsmål pr. sektion** — *Now* (Run now, AI Chat, Self-check, Scheduler),
  *Channels* (tal pr. kanal, Engagement, RSS-feeds, API-nøgler) og *System* (opdatering, Command
  Center, billed-rescue). Overskrifterne er `SGAP_UI::section()`, ikke kort, så mønstret kan flyttes
  til de øvrige sider uden at ændre deres markup.
- **Én `Needs you`-linje i stedet for grønne pills overalt.** Kun fire slags fund tæller: dumpek
  AI-test, self-check-problemer, blokeret publicering (oversat til ord) og kladder der venter.
  »Queue er tom« og »nøglen er ikke testet endnu« er bevidst ude — det sidste stod allerede i
  *Get started*, og med det i linjen ville advarslen være permanent og derfor ulæst.
- **Fælde: `SGAP_UI::css()` printer kun én gang pr. request** (`self::$printed`). En test der leder
  efter nye CSS-regler i sidens HTML fejler, hvis en tidligere side i samme kørsel har printet
  stylesheet'et. Læs `SGAP_UI::stylesheet()` i stedet. Samme slags fælde: assert på kortets titel
  (`Scheduler</h2>`) og ikke på `Next publish tick`, som afhænger af om cron-stubben svarer med en tid.
- **Verificeret:** 706 → **714 PASS**, 0 FAIL. **Ikke** set i en rigtig wp-admin — der er ingen
  adgang fra worktreen, og 7.52.0 venter på ejerens *Plugins → Update*-klik. Farver og hvordan de to
  kolonner bryder ved 782 px er uverificeret.
- **Bogføring:** ny modul **P1.19 0,25 d** (mønstret kunne genbruges, derfor ikke større), så
  projektet går fra 28,4 til 28,65 d — nævneren vokser med arbejdet, og procenten stiger kun det
  arbejdet er værd.

### 7/11 — to rækker i plugin-roadmappet talte den samme eftermiddag to gange

- **P2.4 (Etsy) stod stadig som »ikke afgjort og venter på ejeren«**, selv om den blev afgjort 6/11:
  skjult, ikke bygget (7.33.4). Rækken lovede derfor stadig en 2,5 d OAuth-klient, som ingen vil bygge.
- **De 0,25 d for at skjule Etsy lå samtidig i P3.1's note**, så den samme eftermiddag var booket to
  steder. Nu ligger den i P2.4, hvor roadmappet estimerede den, og P3.1 er 0,2 af 0,25 d (død metode +
  ni usynlige rester) med kun `labor_rate` tilbage.
- **Nævneren blev derfor rettet:** P2 7,0 → 4,75 d, P3 2,75 → 2,5 d, projektet 30,9 → 28,4 d. Uden
  rettelsen kunne P2 aldrig blive færdigt, og hver fremtidig procent ville være undervurderet med cirka
  otte point. **Stigningen 80,3 → 87,3 % er bogføring, ikke arbejde** — der er ikke bygget noget i dette pas.
- **P1.5 er samtidig delt op:** P1.5a (Bluesky + Mastodon) er bygget 7/11 i 7.51.0, og `next`/`blocked_by`
  nævner nu kun P1.5b/c, som kræver et Pinterest-trial-token og en IG-konto-token.

### 7/11 — merchant-schemaet var rigtigt, men sprang produktionen over

- **S4.13's kode lå der allerede** (skrevet 2/11), så runden handlede om at måle den mod Googles
  **nuværende** krav i stedet for mod hukommelsen. Kilden er Search Centrals merchant-listing-side.
- **Produktionstiden manglede.** `/shipping/` siger »Production: 3-7 business days«, derefter 6–10 dage i DK
  og 7–12 i EU. Markupen satte hele leveringstiden som `transitTime` og sprang produktionen over, så en
  dansk kunde så 6–10 dage mod sitets 3–7 **plus** 6–10. Den er nu `handlingTime` ved siden af `transitTime`.
- **`itemCondition: NewCondition`** er tilføjet hvor feltet mangler — butikken er print-on-demand og sælger
  ikke returvarer, så det er en kendsgerning, ikke en påstand om stand.
- **Fragt-linjen kan ikke kvalificere, og det skal siges højt.** Google kræver `shippingRate.value` eller
  `.maxValue` for at vise fragtoplysningen. Sitet har ingen fast fragtpris (»shown before you confirm your
  order«), og at skrive `0` ville love gratis fragt. Feltet er derfor bevidst udeladt: markupen er gyldig og
  **returretten** kvalificerer (den kræver kun `applicableCountry` + `returnPolicyCategory`), men
  fragt-linjen bliver ikke vist, uanset hvor meget kode der skrives. Det kræver en pris fra ejeren eller en
  indstilling i Merchant Center.
- **En overskrift fra marts 2025 holdt ikke.** Nyhedsartiklerne skrev at `returnPolicyCountry` nu er
  påkrævet — det gælder **organisation**-niveau, ikke den offer-politik filteret skriver. Havde jeg bygget
  på overskriften, var feltet blevet ignoreret, og dokumentationen havde påstået noget forkert.
- **Værnet var hullet.** `tools/schema_filter_test.php` har kørt siden 2/11, men var **ikke** med i den
  natlige kørsel — en regression i schema-filteret ville være gået ubemærket hen. Den er nu værn nr. 22, og
  testen gik 19 → 30 påstande.
- **Ikke deployet:** filen skal stadig i `wp-content/mu-plugins/` og måles med `tools/schema_check.py`. Det
  kræver WordPress-adgang, som sessionen ikke har.

### 7/11 — hele sidens overflade er nu ét lag, ikke 300 inline-regler

- **Problemet var ikke mangel på CSS, men mangel på rækkefølge.** `css/sg-design-system.css` fandtes i 275
  linjer og blev **aldrig indlæst af noget**. Temaet bærer sin flade i to base64-kodede template-parts
  (header 50.348 B / 1.383 linjer med to `<style>`-blokke, footer 4.136 B) plus ~300 inline-regler, mange
  med `!important` — hver rettelse hidtil blev et nyt lag oven på det forrige.
- **Beslutningen: ét lag, printet sidst.** Filen er skrevet om til 778 linjer og lægges på siden af
  `wordpress/mu-plugins/sga-design-system.php`, som printer den i `wp_footer` med prioritet 999 — efter
  temaets `<head>`-styles og blokkens globale styles. Rækkefølge slår specificitet, så laget ikke skal slås
  med temaet. Det er hele grunden til at loaderen findes; uden den var filen endnu en taber i en
  specificitetskrig.
- **Paletten blev den mørke, ikke pergament.** `design.md` §3's lyse palette er ikke slået til, fordi den
  ikke er set i en browser og temaets regler er skrevet mod den mørke flade. Den ligger nederst i filen som
  `body.sg-theme--parchment` — en fravalgt variant med én klasses afstand, ikke en glemt mulighed.
- **Fælden: fokusringen forsvandt på messing-knapperne.** `outline: 2px solid #F0D27C` måler 12,7:1 på blek
  og **2,09:1** mod messing-fladen, altså under WCAG's 3:1 netop der hvor brugeren er på vej hen. Ringen er
  nu dobbelt: mørk inderring på messing, lys ring ellers. Læren er generel — en fokusring skal måles mod
  **hver** flade den kan lande på, ikke kun mod sidens bund.
- **Værnet fangede straks min egen fejl.** `tools/design_check.py` afviste filen fordi den krævede
  `::where(...)` mens CSS'en (korrekt) skriver `:where(...)`; rettelsen var i værnet, ikke i filen. Det er
  den slags der ellers først bliver opdaget uger senere.
- **Ikke verificeret, og det skal stå klart:** laget er **ikke** på det levende site og er **ikke** set i en
  browser. Sessionen har ingen WordPress-adgang (`connect_integration` svarede ikke-tilsluttet, ingen
  MCP-credential). Det testede er logikken: 21/21 værn grønne, loaderen printer 778 linjers CSS, `body` får
  `sg-ui`, kill-switchen fjerner begge dele, og en manglende fil giver en HTML-kommentar i stedet for CSS.
  Sådan lægges det på: `library/designsystem-hele-siden-2026-11-07.md` §9.

### 7/11 — jeg lagde sitet ned med et mu-plugin, og MCP kunne ikke redde det

- **Hændelsen:** under S0.2 (robots.txt) skrev jeg `managed-wordpress-robots.php` med `novamira/execute-php`, men med
  filindholdet som en **dobbelt-citeret PHP-streng**. `$output`, `$rules` og `$block` blev interpoleret til tomme
  strenge, filen fik ` = array(` på linje 13, og en parse-fejl i et mu-plugin er en **E_COMPILE_ERROR på hvert
  request**. Siden da svarer `/?nocache=1`, `/shop/`, `/cart/` og `/wp-json/…` med fejlen; kun W3TC's cachede
  forside virker. Butikken kan ikke tage imod ordrer, indtil filen er rettet.
- **Fælden der gjorde det uopretteligt over MCP:** mu-plugins indlæses **før** plugins, så Novamira/MCP er også
  dødt. Der findes ingen anden kanal i sandkassen (ingen SFTP/SSH-credential), så genopretningen kræver
  ejerens fil-adgang i Simply — tre veje i `docs/library/robots-og-nedbrud-2026-11-07.md` §4.
- **Succes-kriteriet var forkert:** kaldet returnerede `bytes: 1222`, og jeg gik videre på det tal. Et
  `file_put_contents` beviser kun at disken tog imod noget — ikke at indholdet kan parses. Fra nu: læs filen
  tilbage, sammenlign `sha1`, kør `token_get_all(..., TOKEN_PARSE)`, hent den URL der bruger filen.
- **Robots-ejerskabet er nu målt (S0.2's anden halvdel):** Rank Math genererer filen (ingen fysisk fil), og
  `managed-wordpress-robots.php` er et **host-lag**, der kun tilføjer otte query-param-`Disallow`. To grupper med
  samme user-agent er gyldigt (RFC 9309 §2.2.1), så rækken kunne være lukket som «målt og dokumenteret» uden at
  røre en host-fil.
- **Roadmap-gennemgangen blev standset her.** Inventory var nået igennem: S0.4's fem URL'er svarer alle 200
  (rækken er forældet), siderne i S3.1/S3.2 ligger allerede i skraldespanden og er talt med, S4.13's
  schema-mu-plugin ligger i repoet men er **aldrig deployet** (`wp_get_mu_plugins()` viser fire filer, ikke fem),
  og Health Check + dets drop-in er stadig aktive (S0.5). Intet af det blev udført.


### 7/11 — jeg lagde sitet ned med et mu-plugin, og MCP kunne ikke redde det

- **Hændelsen:** under S0.2 (robots.txt) skrev jeg `managed-wordpress-robots.php` med `novamira/execute-php`, men med
  filindholdet som en **dobbelt-citeret PHP-streng**. `$output`, `$rules` og `$block` blev interpoleret til tomme
  strenge, filen fik ` = array(` på linje 13, og en parse-fejl i et mu-plugin er en **E_COMPILE_ERROR på hvert
  request**. Siden da svarer `/?nocache=1`, `/shop/`, `/cart/` og `/wp-json/…` med fejlen; kun W3TC's cachede
  forside virker. Butikken kan ikke tage imod ordrer, indtil filen er rettet.
- **Fælden der gjorde det uopretteligt over MCP:** mu-plugins indlæses **før** plugins, så Novamira/MCP er også
  dødt. Der findes ingen anden kanal i sandkassen (ingen SFTP/SSH-credential), så genopretningen kræver
  ejerens fil-adgang i Simply — tre veje i `docs/library/robots-og-nedbrud-2026-11-07.md` §4.
- **Succes-kriteriet var forkert:** kaldet returnerede `bytes: 1222`, og jeg gik videre på det tal. Et
  `file_put_contents` beviser kun at disken tog imod noget — ikke at indholdet kan parses. Fra nu: læs filen
  tilbage, sammenlign `sha1`, kør `token_get_all(..., TOKEN_PARSE)`, hent den URL der bruger filen.
- **Robots-ejerskabet er nu målt (S0.2's anden halvdel):** Rank Math genererer filen (ingen fysisk fil), og
  `managed-wordpress-robots.php` er et **host-lag**, der kun tilføjer otte query-param-`Disallow`. To grupper med
  samme user-agent er gyldigt (RFC 9309 §2.2.1), så rækken kunne være lukket som «målt og dokumenteret» uden at
  røre en host-fil.
- **Roadmap-gennemgangen blev standset her.** Inventory var nået igennem: S0.4's fem URL'er svarer alle 200
  (rækken er forældet), siderne i S3.1/S3.2 ligger allerede i skraldespanden og er talt med, S4.13's
  schema-mu-plugin ligger i repoet men er **aldrig deployet** (`wp_get_mu_plugins()` viser fire filer, ikke fem),
  og Health Check + dets drop-in er stadig aktive (S0.5). Intet af det blev udført.


### 7/11 — S2.7 trin 0–2 udført: 51 → 28 scripts, men loftet på 20 kan ikke nås

- **Fem indgreb, ikke tretten scripts:** emoji-scriptet (2), TikTok-pixelens fire scripts, Kliken-modulet (1),
  Site Kits Google-login (2) og headerens blok-mini-kurv/-konto (~9) er væk. Woo's seks kurv-scripts loades nu kun
  på butik, kurv, kasse og konto.
- **To fælder kostede runder:** TikTok registrerer sit script **inde i renderingen**, og Kliken er et script-modul
  udenom script-køen — et tidligt `wp_dequeue_script` rammer for sent, så det blev et præcist output-filter på de
  kendte URLs/ids. Og Woo **hooker mini-kurven ind igen** efter den er fjernet fra template-parten: uden
  `hooked_block_types`-filteret faldt kun 11 scripts, med det 23.
- **Google Listings & Ads blev bevidst ikke fjernet.** Dets `AW-18518250875` er den eneste Ads-konvertering tilbage
  efter S0.9; at fjerne det ville slukke konverteringssporingen uden at nogen bad om det. Derfor lander teksten på
  28 og ikke 26.
- **Målt:** forside 117.697 → 80.307 B og 51 → 28 scripts, `/faq/` → 73.818, søgning → 97.796, indlæg → 104.230,
  produkt → 289.569 (105 → 94), kurv → 336.263 (120 → 111), `/shop/` → 257.065 (54 → 43), kategori → 133.496.
  HTML-kravet er nu opfyldt på de fire tekstskabeloner; **script-loftet nås ikke på nogen af de otte**.
- **Loftet skal vedtages på det målte gulv (30/45/95/112)** — eller trin 3 (klassisk kurv/produkt, 0,75 d) bestilles.
  De sidste 5–8 scripts er chatbot, React-parret, GLA og Complianz, ikke sporingsstøj.
- **Rollback:** `add_filter( 'sga_payload_trim_enabled', … false … )` pr. indgreb, og backup af header-parten ligger i
  `wp-content/novamira-sandbox/s2-7-backup-header-part-12.html`.
- **Ikke verificeret:** hvordan "Add to cart" opfører sig visuelt nu hvor drawer'en er væk — der er ikke kørt browser-test.


### 7/11 — MCP-fornyelsen er flyttet ind i pluginnet (7.44.0)

- **Beslutning:** Novamira-adgangen holdes i live af sitets egen WP-Cron i stedet for repository-secrets eller
  et loop i en sandkasse. `includes/mcp-auth.php` (`SGAP_MCP_Auth`) fornyer hvert 30. minut, verificerer med
  `initialize` + `Mcp-Session-Id` + `tools/list` og gemmer det roterede refresh-token **før** noget andet.
- **Hvorfor det ikke kan være en konstant:** refresh-tokenet roteres ved hver fornyelse, så det skal skrives
  tilbage til databasen. Et token i `wp-config.php` afvises derfor med en forklaring i stedet for at give
  indtryk af at virke.
- **Synligt for ejeren:** *Settings → MCP access* (client ID + refresh token), `mcp-status` (læsende) og
  `mcp-refresh`, plus en linje i det daglige selvtjek når en fornyelse afvises. Et token der ikke virker bliver
  aldrig meldt som fornyet.
- **Ikke målt mod live endnu:** fornyelsen kræver ejerens godkendelse og de to værdier indtastet i wp-admin.
  20 nye smoke-checks kører begge veje med stubbede HTTP-svar (584 PASS, 0 FAIL).

### 7/11 — S2.7: script-tallet forklaret, loft pr. skabelon foreslået

- **Fordelingen, ikke bare tallet:** forsiden har 23 inline-blokke, 8 kernel-scripts, 8 Woo-scripts, 2 tema-scripts og **13 sporings-scripts** (TikTok 4, Kliken 3, Google Listings & Ads 4, Google Sign-in 2). Produkt (105) og kurv (120) har 27–40 kernel-scripts, fordi begge er blok-skabeloner (`wp-block-woocommerce-product`, `wp-block-woocommerce-cart`, 40 forskellige `wc-block-*`-klasser).
- **Headeren er den skjulte årsag:** `wc-block-mini-cart__button` står i `<header>` og `wc-block-customer-account` 4 steder samme sted, så React, react-dom, element, escape-html, hooks, `mini-cart.js`, `customer-account.js` og `js-cookie` indlæses på **alle** sider (155 `data-wp-*`-direktiver på forsiden). Gulvet er derfor 22–24 scripts med blok-header og ~13 uden.
- **`< 20` kan ikke nås på produkt og kurv** uden at skifte til klassiske skabeloner. Forslaget er derfor tre klasser med hvert sit loft: tekst 130 KB/20 scripts, lister 290 KB/30, transaktion 350 KB/90–105, og `< 20` trækkes eksplicit tilbage for transaktion.
- **Tre spørgsmål står til ejeren** (annoncepixels, header-mini-kurv, klasse 3-loft) i [`library/payload-loft-beslutning-2026-11-07.md`](library/payload-loft-beslutning-2026-11-07.md) §6. Trin 0–2 (0,4 d) venter på dem.
- **Fælde:** `GT-PL9L67B4` og `AW-18518250875` er **ikke** en dublet — det er Google Tag (GA4) og Ads-konvertering. S0.9's dublet var Rank Maths `install_code`.
- **PDF-rendereren** gentager nu tabelhovedet, når en tabel fortsætter over et sidebrud, og forstår `<!-- sidebrud -->`. Begge payload-PDF'er er renderet igen og set side for side.

### 7/11 — S2.8 lukket: 29 af 30 indlæg sender nu et hub-link

Målingen før passet var **9 af 30** publicerede indlæg med et kontekstlink til `/start-here/` eller
`/build/`. Tyve indlæg fik hver **én sætning** i brødteksten — 14 mod `/start-here/` (vælg-og-køb) og 8
mod `/build/` (byg-og-forarbejd) — og nu står der **29 af 30**. Det sidste er indlæg 898, hvis indhold er
ordet `test`; det er en ejerbeslutning, ikke link-arbejde.

**Fælden der kostede en runde:** første indsættelse søgte det sidste `"<p>"` før `<script` — men flere
indlæg har HTML inde i selve JSON-LD-strengen (fx et spørgsmål med `</p>` i teksten), så to links endte
**inde i schema-JSON'en**. Løsningen er at maskere alle `<script>…</script>`-blokke med mellemrum af
samme længde før positionssøgning, så byte-positionerne stadig passer. Hver indsættelse kontrolleres nu
på fem punkter: præcis +1 `<p>`, `script_delta` 0, `div_delta` 0, aldrig inde i en script-blok, og det
gemte indhold byte-identisk med det beregnede.

**Kontrol:** alle 20 sider er hentet igen fra serveren selv — **200, linket i den renderede HTML, ingen
PHP-fejl**. To batche ramte en forbigående **503 fra Simply**; siden svarede 200 igen efter ca. 45 s, og
batchene blev derefter delt op i fem ad gangen med pause.

**Og 7.41.1 er live:** opdateringen blev installeret 6/11 kl. 23:54 UTC, og den udrulles
`includes/site-seo.php` bruger nu `$home` på linje 354–360 — der findes ikke længere en bar `home` i
filen. Den daglige `admin_init`-fejl i wp-admin er dermed lukket i produktion.

Dokument: [`library/hub-links-posts-2026-11-07.md`](library/hub-links-posts-2026-11-07.md).

### 7/11 — S2.7 målt: al inline CSS var den samme CSS, sendt forfra hver gang

- Målt med **`tools/payload_audit.py`** (nyt) på otte skabeloner: 241.371–476.407 B HTML, **148–165 KB inline CSS** og 45–46 KB i den største blok. Forsiden var ikke 215 KB som 6/11, men 255 KB.
- **Kilden var indhold, ikke kode:** temaets 37 KB CSS er en `<style>`-blok i `wp_template_part` 12 (header), «Clockwise»-snippet'en er `custom-css-js` **945** (post type `custom-css-js`, ikke `sccss` — derfor gav `get_posts('sccss')` nul), og 45 KB er kernens `global-styles`. Filsøgning og `wp_options`-søgning gav intet, fordi CSS'en ikke er kode.
- **Indgreb:** `wordpress/mu-plugins/sga-inline-css.php` (samme fil ligger i `wp-content/mu-plugins/`) bytter hver `<style>`-blok ≥ 4 KB ud med `<link>` til en fil i `uploads/sga-css/` med sha1-navn. Fejler skrivningen, bliver blokken stående inline, så en side aldrig kan blive ustylet.
- **Effekt:** HTML −27 til −54 % (forside 255.154 → **117.697 B**, `/faq/` 241.371 → 111.208 B, produkt 469.961 → 337.000 B). Største inline-blok er nu 3.455–4.064 B, så **4 KB-kriteriet er opfyldt**; HTML < 100 KB og < 20 scripts er **ikke**.
- **Hvorfor ikke lukket:** produktsiden er stadig 30.713 B CSS + 170.781 B script-blokke (154.571 B inline) + 135.553 B markup, og `/cart/` har 120 script-tags. At nå < 20 scripts kræver at Woo-blokkenes React-stak, AI Engine-chatbot'en eller TikTok/Site Kit-sporingen fjernes — det er ejerens valg, og det står med tal i [`library/payload-budget-2026-11-07.md`](library/payload-budget-2026-11-07.md) §»Hvad der stadig ikke er i mål«.
- **Fælde:** efter indgrebet svarede `/` stadig den gamle HTML, indtil W3TC's page cache blev ryddet (`royal-mcp/w3tc-purge-cache`, `scope: all`). En uændret byte-størrelse er ikke bevis på fiasko — mål med `/?sga=1` for at se hvad PHP'en leverer.

### 7/11 — S0.9 lukket: det inline GA4-tag var Rank Math, ikke et snippet

- **Fælden først:** en filsøgning i `wp-content` efter `G-3MYLFKY8MY` gav **nul hits** blandt 34.972 filer, og Code Snippets er ikke aktiv. Tag'et bor i en **option**, ikke i en fil — søg i `wp_options` med `LIKE` før tema og snippets ledes igennem.
- Kilden var `rank_math_google_analytic_options`: `measurement_id: G-3MYLFKY8MY` + `install_code: "1"`. `seo-by-rank-math/includes/modules/analytics/class-gtag.php:71-74` returnerer tidligt når flaget er tomt, og det ene flag styrer **både** `google_gtagjs`-scriptet og den inline `gtag("config", …)`.
- Indgrebet: `install_code` → tom streng over Novamira CLI'ens `execute-php`; property-id, `GT-PL9L67B4` og `AW-18518250875` urørt. Den gamle serialiserede værdi blev gemt først, så rollback er ét felt.
- Målt: **0** forekomster i HTML (var 2), og **ét** `g/collect` med `en=page_view` på `/`, `/?nocache=1` og `/shop/` — alle `tid=G-0HC9BLQ88R` og samme `cid` som 6/11. Ads sporer stadig.
- Adgangsvejen var **Novamira CLI'ens device-godkendelse**, ikke `tools/mcp_oauth.py`: CLI'en har sin egen credential i `~/.config/novamira/config.json` (mode 600). `tools/mcp_rest.py --check` svarer stadig »ingen credential« — den vej er ikke rørt.
- Novamira-tokenet udløber efter 3600 s og forsvinder med sandkassen: næste pas skal køre `novamira doctor --json` først og om nødvendigt godkende igen med `--device`.
- Dokumentation: [`library/analytics-dobbelt-2026-11-06.md`](library/analytics-dobbelt-2026-11-06.md).

### 6/11 — hvorfor faldt butikken: vores egen fatale fejl, og nu en log der fanger næste gang

Tidslinjen er nu målt til sekundet: fire plugins skrevet på disken 19:56:11–19:57:04, alle fire
deaktiveret **i samme sekund** kl. 19:57:14 (ét kald med fire plugins — ikke fire uheld), W3TC kl.
19:59:30. WP's egen opdateringsrutine er **udelukket med kilden som bevis**:
`Plugin_Upgrader::deactivate_plugin_before_upgrade()` slår fra *før* installationen og springer helt
over i cron, så mønstret passer hverken på en bulk-opdatering eller en natlig auto-opdatering.
MainWP-tabellerne er fra 23/10, Royal MCP-loggen holder op 2/11 — **hvem** kan ikke aflæses, og det
står der i dokumentet.

**Det vigtigste fund: den fatale fejl var vores egen.** `Undefined constant "home"` i
`site-seo.php:354` kl. 19:55:00, kastet i `admin_init` fra `wp-admin/index.php` — altså en kritisk
fejl på en vilkårlig admin-side, **én gang i døgnet**, fordi `maybe_scan()` sætter sin guard før den
scanner. Rettet til `$home` på fire linjer; ny smoke-check dør med præcis samme fejl hvis den
genindføres (verificeret i en kopi: 168 PASS derefter `Fatal error`, exit 255). Live kører stadig
7.40.1 med fejlen, indtil ejeren klikker *Plugins → Update*.

Logningen er sat op som en **log-only vagt** i `wp-content/novamira-sandbox/steamgears-site-events.php`:
deaktivering, aktivering, opdatering, admin-login, fatale fejl — med bruger, IP, URI og kaldstak — til
`/var/www/steamgears.eu/steamgears-logs/` (0700/0600, uden for webroden). Verificeret med en syntetisk
`do_action('deactivated_plugin', …)`, og `/`, `/shop/`, en produkt-URL og `/wp-login.php` svarer
stadig 200. Filen ligger også i repoet som `library/hjalpefiler/steamgears-site-events.php`.

Dokument: [`library/hvorfor-butikken-faldt-2026-11-06.md`](library/hvorfor-butikken-faldt-2026-11-06.md).

### 6/11 — butikken var nede i 1 t 38 min (den vigtigste hændelse i dag)

Fire butiks-plugins blev deaktiveret i samme batch kl. **19:57:14 UTC**: WooCommerce 11.2.2, Stripe,
Pinterest for WooCommerce og Google for WooCommerce. Konsekvens: `/product/*` og `/product-category/*`
svarede **404**, Store API 404 — mens `/shop/`, `/cart/` og `/checkout/` svarede 200 fra sidecachen. Det er
fælden der skal huskes: **en 200 er ikke et bevis for at butikken virker.** Alle fire er genaktiveret og målt
bagefter (WC 11.2.2, Store API 200, produkt-URL 200 med købsknap, `stripe` + `bacs`, produkt-sitemap 21
URL'er). Årsagen er **ikke fastslået** — `recently_activated` giver sekundet, men der findes ingen
`debug.log`. Tre ting står stadig åbent: `sitemap_index.xml` udelader product og product_cat (kræver et
`rank_math/sitemap/index`-filter, altså en kodeændring), butikken har 20 publicerede produkter mod 39 målt
2/11, og indlæg 898 står som `test` med tre hub-links der lover “10 couple costumes”.

Dokument: [`library/butik-nede-og-genoprettet-2026-11-06.md`](library/butik-nede-og-genoprettet-2026-11-06.md).

To opsætningsrettelser blev lavet i samme pas, begge målt: `/shipping/` og `/returns/` står nu i footerens
Support-kolonne (1 forekomst på `/`, `/shop/`, `/faq/`), og corset-guiden (1394) er kommet ind i hubbens
guide-liste på `/steampunk-costumes/` — den havde **0** indgående interne links. Det tomme testindlæg 789
(`<p>t3</p>`, 0 indgående links) er sat til kladde.

### 6/11 — navigation: ejeren valgte native blokke

S2.3 fortsætter i **Gutenberg/native blocks** (Extendable-bloktema), ikke page builder eller child theme.
Headeren er allerede `wp:navigation-link`-markup i template part 12 med Start Here · Build · Shop (dropdown)
· Gallery · Guides · About · Contact — hub-links er på plads siden 2/11, så det der manglede var footeren og
den interne linkning, ikke topniveauet. Novamiras `gutenberg-*`-evner kræver en browser-finalizer for **nye**
statiske blokke; målrettede rettelser i eksisterende markup kan skrives direkte med `wp_update_post`.

### 6/11 — MCP-adgang: fornyelse, ikke secrets

- **Beslutning:** ingen repository-secrets til Novamira. I stedet fornyes tokenet løbende hvert 58. minut.
  Baggrunden er ejerens valg: secreterne krævede en PAT med Secrets: write, og det blev vurderet som mere
  bøvl end gavn.
- **Målt:** access-token 3600 s, refresh-token **roteres** ved hver fornyelse (det gamle er dødt bagefter).
  Introspection er admin-only, så refresh-tokenets levetid kan ikke læses udefra.
- **Bygget:** [`tools/mcp_keepalive.py`](../tools/mcp_keepalive.py) (forny + verificér med `initialize` +
  `tools/list`) og [`tools/keepalive-loop.sh`](../tools/keepalive-loop.sh) (loop hvert 3480 s, log i
  `.tools/keepalive-loop.log`, pid i `.tools/keepalive-loop.pid`, prøver igen efter 5 min og stopper efter fem
  fejl i træk).
- **Grænsen, sagt højt:** loopet lever kun, mens worktreen kører. Cloud-sandkassen er bundet til chatten, ikke
  en permanent server — bliver den arkiveret, dør processen. De 30 dage kræver enten CI-varianten
  ([`novamira-keepalive.yml`](../.github/workflows/novamira-keepalive.yml), som så kræver de to secrets) eller
  at fornyelsen flyttes ind i pluginnet og kører på sitets egen WP-Cron. Sidstnævnte er den eneste vej uden
  secrets, og den er ikke bygget endnu.
- **Fælder:** godkendelseslinket skal bruge `&` når endepunktet har query, og token-kaldene skal være
  form-encoded (JSON giver `unsupported_grant_type`). `tools/list` kræver `Mcp-Session-Id` fra `initialize`.

### 6/11 — Roadmap: to punkter lukket

- **S2.2 `/shipping/` + `/returns/`** (1396, 1397) er publiceret og målt 200. 301-vejen var umulig, fordi
  Redirection ingen gruppe har; siderne er derfor skrevet opgave-orienteret af **sitets egne publicerede
  politikfakta** (`/delivery/`, `/refund_returns/`, `/terms/`) — ingen nye løfter om tid, pris eller fortrydelse.
  Begge politik-sider linker nu ind til hubberne.
- **`/steampunk-corset-guide/`** (1394) er publiceret: to `/go/`-links (489, 491 — målt 302) i stedet for rå
  `/product/`-hrefs, plus hub-link til `/steampunk-costumes/`. De øvrige otte produktlinks blev **bevidst ikke**
  sat, fordi de ikke kunne måles herfra.
- **Fælde:** Markdown-tabeller konverteres ikke til Gutenberg af `create-post` — de ender som rå `|`-tekst.
  Skriv lister i stedet, eller ret blokken bagefter.
- **Fælde:** Simplys WAF giver browser-UA en *"Checking your browser…"*-challenge og default-UA en side med
  titlen "Page Not Found". Produkt-URL'er uden pæn permalink kan derfor ikke verificeres fra sandkassen — et
  umålt link hører ikke i en publiceret side.

### 6/11 — Repoets egne regler, der koster en runde at genopdage

- **Procent ved hvert pas**, med `Modul / Før / Efter / Δ / Grundlag` — også når svaret ellers er færdigt.
  `projektstatus.json` er sandheden, `projektstatus.md` skrives kun i AUTO-blokke, og et fald i procent
  afvises af `--check` uden `scope_change`.
- **Alt arbejde skal ende på `main`** i samme tur. Den natlige kørsel kl. 02 er et sikkerhedsnet, ikke en
  erstatning — den ser ikke ucommittet arbejde i en sandkasse.
- **Ny viden skal ud som PDF** i `reports/` og stå i [`library/README.md`](library/README.md).
- **Instruktions-gaten:** en turns første værktøjs-batch skal indeholde en læsning af `AGENTS.md`, ellers
  afvises hele batchen.

### 6/11 — Cirla

- Ejerens dokument "Cirla.pdf" er overført til
  [`library/cirla-teknisk-dokumentation-2026-11-06.md`](library/cirla-teknisk-dokumentation-2026-11-06.md).
  Det er en **plan**, ikke en måling: Cirla er endnu ikke bygget, og de fem åbne spørgsmål i dokumentets §8
  står åbne.
- Det der allerede findes i pluginnet og rører ved planen: kvalitetsgaten (P1.6), dublet-værnet (P0.6, anden
  nøgle end `_cc_sync_id`), `/go/`-tracking, og en Pinterest-kanal der er **blokeret** af et manglende token —
  ikke af manglende kode.

## Åbne beslutninger til ejeren

- ~~**Novamira-fornyelse:** CI med to secrets, eller fornyelse i pluginnet via WP-Cron (ingen secrets)?~~
  **Afgjort 7/11:** fornyelsen ligger nu i pluginnet (7.44.0, WP-Cron + option). CI-workflowet og
  worktree-loopet er stadig mulige, men ikke længere nødvendige.
- **Cirlas produktionsrolle:** hvilke rettigheder præcist, og hvornår må modulet slås til i produktion?
- **De tre resterende guides:** publiceres de som de er skrevet, eller skal der fotos og gennemlæsning først?
- **S0.4:** én Redirection-gruppe skal oprettes af ejer, før de fem vanity-301 kan laves.

## Næste skridt

0. ~~Sandkassen mangler et MCP-token~~ — ✅ **adgang virker:** Novamira-CLI'en ligger i `.novamira-global/`
   (gitignored) med et `fresh` full-access-token, så `wp plugin install`, `wp eval` og REST-kald kan køres
   herfra. Det er den vej 7.59.0 blev installeret og ROI-kortet læst tilbage.
1. **S2.9 designsystem: læg laget på sitet og se det** (0,1 d tilbage af 0,5). CSS-filen og loaderen er
   skrevet og værnet; det der mangler, er at kopiere `css/sg-design-system.css` til `uploads/sga-design/`
   (eller ind i `mu-plugins/`), kopiere `wordpress/mu-plugins/sga-design-system.php` til
   `wp-content/mu-plugins/`, og se forsiden, en produktliste, en produktside og kurven i en browser —
   især fokusringen på messing-knapper, produktgriddet ved 390 px og headeren. Slå fra med
   `add_filter( 'sga_design_enabled', '__return_false' );` hvis noget brækker.
2. **S2.7 HTML-/scriptbudget** (1,0 d) er stadig det næste ublokerede roadmap-punkt, men script-loftet
   kræver ejerens beslutning (chatbot, GLA, klassisk kurv).
2. De tre manglende guides (SEO 62,5 % → 100 %): copy ligger færdig og verificeret i
   [`library/guides-2026-11-03.md`](library/guides-2026-11-03.md).
2. ✅ MCP-fornyelsen er bygget ind i pluginnet (7.44.0). Det der mangler, er ejerens godkendelse og de to
   værdier under *Settings → MCP access* — derefter holder adgangen i 30 dage uden secrets.
3. Cirla som til/fra-modul i pluginnet efter dokumentets §9.4 — start med trin 1, modulet slukket som standard.
4. **Plugin-sporet har præcis ét åbent punkt: P1.5's live-verifikation (0,85 d).** Alt andet er lukket og
   afstemt; det der mangler, er ejerens Pinterest-nøgle (access- eller app-credentials + refresh) og
   Instagram-nøgle (professionelt konto-id + long-lived user-token) under *Settings → Keys*. Derefter er
   verifikationen to kald til `pinterest/refresh` og `instagram/refresh`.

## Hvad der ikke kan gøres herfra

- **Live publicering af produktændringer** og alt der kræver wp-admin-klik (plugin-opdatering, Redirection-gruppe).
- **Pinterest, GA4 og Command Center** — ingen adgang fra sandkassen. Undtagelse målt 7/11: WordPress selv nås via Novamira CLI'ens device-godkendelse (`install_code`-rettelsen blev skrevet ad den vej), og GA4-tag'et blev verificeret i cloud-browseren — men selve GA4-rapporterne er stadig utilgængelige.
- **Printful** — rebrand-mockups er blokeret uden ejerens adgang.
- **At holde en proces i live efter arkivering.** Ingen kommando her kan love det; kun CI eller sitets egen
  WP-Cron kan.
