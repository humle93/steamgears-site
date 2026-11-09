# Projektstatus — procentvis fremgang

Reglerne for tallene står i [`AGENTS.md`](../AGENTS.md) §1–2. Kort: hvert aktivt projekt har en procentstatus
hver dag, og hvert tal skal have et grundlag der kan genberegnes.

Filen vedligeholdes af [`projektstatus.py`](../projektstatus.py):

- Procenterne **beregnes** ud fra [`projektstatus.json`](../projektstatus.json) — de indtastes ikke.
- Kun blokkene mellem `AUTO:`-markørerne skrives maskinelt. Manuel tekst uden for markørerne overlever.
- Historikken er append-only: scriptet tilføjer rækker, men sletter eller ændrer dem aldrig.
- Rettes noget i en AUTO-blok i hånden, fejler `--check` med drift-fejl, og næste `--update` overskriver det.

Betjening: `python3 projektstatus.py --check` (valider + drift), `--dry-run` (vis ændringer),
`--update` (beregn og skriv), `--selftest` (test værnene på fixtures).

---

## 1. Daglig status

<!-- AUTO:DAGLIG:START -->
Status pr. **2026-11-09** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`](../projektstatus.json)

| Projekt | Status i dag | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** *(roadmap S0–S4)* | **100 %** | 20,95 af 20,95 d (S0 2,35/2,35 · S1 3,6/3,6 · S2 6,9/6,9 · S3 4,05/4,05 · S4 4,05/4,05) |
| **steamgears.eu — miljø, server og sandbox (S5)** *(roadmap S5 — køreplanen for nye sites (§9) kørt mod sitet 8/11; S5.3–S5.6 udrullet og målt 9/11)* | **51,7 %** | 1,5 af 2,9 d |
| **Steamgears Autopilot — plugin** | **100 %** | 30,45 af 30,45 d (P0 4,65/4,65 · P1 14,45/14,45 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5 · P3.7 0,6/0,6) |
| **Dark academia-rebrand** | **69,7 %** | 11,5 af 16,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5 · Batch 4-forberedelse: arbejdsordre, filnavne, alt-tekster, rækkefølge 0,5/0,5 · Batch 2-gentildeling: de seks kollektioner skal tildeles igen 0,4/0,4 · Mobil-CSS, menu og årstidsfarver 2,1/2,1) |
| **SEO-indholdsproduktion** | **100 %** | 8 af 8 guides (Publicerede guides (1357, 1361, 1359) 3/3 · Guide-copy klar til publicering (1357, 1361, 1359 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) · drift: Daglig loop kører |
| **Døde internlinks** | **100 %** | 39 af 39 links |
| **Niche-butik — plugin P4** | **100 %** | 1 af 1 d (Plan-motor: arketyper, priser, sider 0,3/0,3 · Kladde-udrulning og rollback 0,25/0,25 · Admin-side, REST og fire evner 0,2/0,2 · Tests, zip og dokumentation 0,1/0,1 · Kørsel mod rigtig WooCommerce 0,15/0,15) |
<!-- AUTO:DAGLIG:END -->

## 2. Aktive projekter og hvad der blokerer

<!-- AUTO:BLOKERINGER:START -->
| Projekt | Næste skridt | Blokering |
| --- | --- | --- |
| steamgears.eu — site-optimering (100 %) | Intet agent-arbejde tilbage i S0–S4: alle fem bands er færdige og målt, og de resterende poster er ejerens og navngivet i §4c og blocked_by. Målt 8/11: 24 produkter (20 publicerede, 4 kladder) i **DKK**. DKK-tabellen fra 2/11 er afregnet **række for række**: af de **38 rækker** (linje 32–69) har **11** et produkt i butikken og står med **tabellens tal ordret** (335/165/400/435/265/180 kr) — målt 11/11 på produktsiden, i JSON-LD, i Store API og i DB med 0 afvigelser — og de **27** øvrige har kun et produkt i **papirkurven** (offentligt 404), så de er dokumenteret som rækker uden produkt med id og status; 0 rækker er uafklarede, og de 13 produkter uden række har uændret pris med id og målt beløb dokumenteret. W3TC kører med **minify, lazy load og database-cache slået til** (192 minificerede filer, 8,08 MB) efter tre gennemførte testordrer (1528/1529/1530, alle annulleret med note); kurv og kasse svarer 200; fri fragt i DK og ingen forsendelse til EU/UK/RoW; Stripe + bankoverførsel; Complianz på EU opt-in. Alle resterende poster er ejerens og står nu samlet i §4d (A–M) med den målte mangel og én konkret handling; målt 8/11 om aftenen er der **ingen** poster tilbage som agenten kan udføre. | Ejerens oplysninger og aftaler, målt 8/11: CVR-nummer og telefon (Complianz har navn »Henning 6«, adresse »Lavgade 44a st th«, e-mail og DK — men telefon er tom og CVR findes ikke i formularen); aftaler med MobilePay/PayPal (kun Stripe og bankoverførsel er aktive); zonerne til EU/UK/RoW (kun Denmark har en metode — fri fragt uden minimum — og resten af verdenen har ingen, så EU-kunder ikke kan gennemføre en ordre), fragtklasser og fri fragt-grænse; en currency-switcher hvis EUR skal vises som sekundærvaluta (der er ingen aktiv); Printful-adgang eller 5–8 færdige mockups pr. produkt (målt: 20 publicerede produkter, 22 billeder, alle under 3); ja/nej pr. produkt for 942/765/766/767 (alle fire kladder med pris og SKU); GSC-svarrapport (S0.6); og beslutning om de to inaktive plugins. |
| steamgears.eu — miljø, server og sandbox (S5) (51,7 %) | Tilbage er kun ejerens fem poster: sandkassen (S5.1, subdomæne + certifikat hos Simply), produktionskonfigurationen læst med MCP (S5.2), DKIM/DMARC-rapporterne (S5.7), backup/restore øvet (S5.8) og branch protection (S5.9, kræver Pro/Team på et privat repo — kør python3 tools/release.py --protection for de fem indstillinger). Målingen holdes nu op hver nat af tools/natlige-vaern.sh. | Subdomæne + certifikat hos Simply (S5.1); hostpanelet til DKIM, backup og DMARC-rapporter (S5.7–S5.8); GitHub Pro/Team til branch protection og environment-regler (S5.9); MCP-læsning af WP_DEBUG og WP_ENVIRONMENT_TYPE (S5.2). |
| Steamgears Autopilot — plugin (100 %) | Agent-scope er færdigt (29,85 af 29,85 d). Ejeren har lagt Pinterest-tokenet ind i WooCommerce-integrationen, og 7.65.0 — som blev lagt på sitet 8/11 gennem MCP (`Plugin_Upgrader` i `execute-php`; `update` svarer nu »Already up to date«) — henter det nu derfra gennem integrationens egen dekryptering: målt 8/11 virker det (konto `cpaleadsis`, id 688980580401955331), men appen mangler scopes `boards:read`, så pin-lister og Pin Analytics kan stadig ikke måles. Næste skridt er ejerens: genforbind Pinterest med board-adgang (eller et developer-token med boards:read + pins:read). Resten af blocked_by står uændret. Målt 8/11 om aftenen er `alert_email` tom, så fejl registreres uden at nogen får mail — det er et ejerpunkt i plugin-roadmappens §8. | Syv led kræver ejeren, alle målt 8/11: (1) **Pinterest-scope** — tokenet ligger nu i WooCommerce-integrationen og er gyldigt (`/user_account` 200 som `cpaleadsis`, id 688980580401955331), men appen har kun ads/billing/catalogs/pins/user_accounts og **mangler `boards:read`**, så `/pins` og `/boards` afvises (målt 17:25Z, kanalstatus står som `needs_scope`); (2) **de øvrige kanal-tokens** — Instagram, Bluesky, Mastodon, Telegram og Facebook har intet token (5 af 6 mangler); (3) **AIMLAPI-kredit** — sidste generering 7/11 fejlede med »You've run out of funds«; (4) **`sgap_alert_email`** er tom, så fejl registreres men ingen mail sendes; (5) **full auto vs. godkendelse** — `sgap_full_auto_enabled = no` og 40 elementer venter; (6) **distributionsvej** — `publish_mode = rss` og 0 Publer-konti; (7) **P2.1–P2.3** kræver ejerens beslutning om Command Center-retningen. Intet af det må gættes. |
| Dark academia-rebrand (69,7 %) | Kun to ting står tilbage: (1) **witchy-tarot** kan først få indhold når ejeren siger ja til at gendanne 567 (Tarot Witchy Tee) — og eventuelt 395/411 — fra papirkurven; derefter fylder `python3 tools/rebrand_apply.py --apply --via-cli` termen automatisk, fordi kortet allerede indeholder dem; (2) **Batch 4-mockups** (5 t) kræver Printful-adgang eller 5–8 færdige billeder pr. produkt. | To led kræver ejerhandling, målt 8/11: (1) **de 29 trash'ede designs** — herunder de tre produkter der er witchy-tarots eneste kandidater (567, 395, 411); et ja/nej til genoplivning er nok, og tildelingen kører selv bagefter; (2) **Batch 4-mockups (5 t)** — Printful-adgang eller 5–8 færdige billeder pr. produkt; målt 20 publicerede med 22 billeder, 0 med 3 eller flere, 62 billedplaceringer mangler, og repoet har 0 trykfiler og 0 tøj-skabeloner. Kollektionstildelingen er ikke længere blokeret: den blev udført 8/11 gennem Novamira-MCP og målt stabil. |
| SEO-indholdsproduktion (100 %) | SEO-indholdet er 100 %: fire guides skrevet, tre publiceret, corset-guiden publiceret, 1410 parkeret til september. Ingen åbne poster. | ingen |
| Døde internlinks (100 %) | Vedligehold: tjek nye indlæg mod live-URL'er | ingen |
| Niche-butik — plugin P4 (100 %) | Færdig som roadmap-punkt P4: modulet er kørt mod det levende site (WordPress 7.1.4, WooCommerce 11.2.2) med 8 produktkladder, 5 kollektioner, en sideskits og en navigation, og rullet tilbage til præcis udgangstilstanden. Næste skridt er ejerens: Printful-adgang, publicering og de øvrige punkter i readiness-listen. Målt 8/11 om aftenen er `alert_email` tom, så fejl registreres uden at nogen får mail — det er et ejerpunkt i plugin-roadmappens §8. | ingen |
<!-- AUTO:BLOKERINGER:END -->

---

## 3. Log

<!-- AUTO:LOG:START -->
Nyeste først. Append-only — rækker må ikke slettes eller ændres.

| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- | --- |
| 2026-11-09 | steamgears.eu — miljø, server og sandbox (S5) | daglig måling | 41,4 % → 51,7 % | +10,3 | 1,5 af 2,9 d |
| 2026-11-09 | Niche-butik — plugin P4 | ingen ændring | 100 % → 100 % | 0 | 1 af 1 d (Plan-motor: arketyper, priser, sider 0,3/0,3 · Kladde-udrulning og rollback 0,25/0,25 · Admin-side, REST og fire evner 0,2/0,2 · Tests, zip og dokumentation 0,1/0,1 · Kørsel mod rigtig WooCommerce 0,15/0,15) |
| 2026-11-09 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-09 | SEO-indholdsproduktion | ingen ændring | 100 % → 100 % | 0 | 8 af 8 guides (Publicerede guides (1357, 1361, 1359) 3/3 · Guide-copy klar til publicering (1357, 1361, 1359 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) |
| 2026-11-09 | Dark academia-rebrand | daglig måling | 68,6 % → 69,7 % | +1,1 | 11,5 af 16,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5 · Batch 4-forberedelse: arbejdsordre, filnavne, alt-tekster, rækkefølge 0,5/0,5 · Batch 2-gentildeling: de seks kollektioner skal tildeles igen 0,4/0,4 · Mobil-CSS, menu og årstidsfarver 2,1/2,1) |
| 2026-11-09 | Steamgears Autopilot — plugin | ingen ændring | 100 % → 100 % | 0 | 29,85 af 29,85 d (P0 4,65/4,65 · P1 14,45/14,45 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-09 | steamgears.eu — miljø, server og sandbox (S5) | daglig måling | 25,9 % → 41,4 % | +15,5 | 1,2 af 2,9 d |
| 2026-11-09 | steamgears.eu — site-optimering | ingen ændring | 100 % → 100 % | 0 | 20,95 af 20,95 d (S0 2,35/2,35 · S1 3,6/3,6 · S2 6,9/6,9 · S3 4,05/4,05 · S4 4,05/4,05) |
| 2026-11-08 | steamgears.eu — miljø, server og sandbox (S5) | daglig måling | 19 % → 25,9 % | +6,9 | 0,75 af 2,9 d |
| 2026-11-08 | steamgears.eu — miljø, server og sandbox (S5) | daglig måling | 0 % → 19 % | +19 | 0,55 af 2,9 d |
| 2026-11-08 | Dark academia-rebrand | daglig måling | 65,3 % → 68,6 % | +3,3 | 10,9 af 15,9 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5 · Batch 4-forberedelse: arbejdsordre, filnavne, alt-tekster, rækkefølge 0,5/0,5 · Batch 2-gentildeling: de seks kollektioner skal tildeles igen 0,4/0,4 · Mobil-CSS, menu og årstidsfarver 1,5/1,5) |
| 2026-11-08 | steamgears.eu — miljø, server og sandbox (S5) | baseline | — → 0 % | — | 0 af 2,9 d |
| 2026-11-08 | Dark academia-rebrand | daglig måling | 62,5 % → 65,3 % | +2,8 | 9,4 af 14,4 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5 · Batch 4-forberedelse: arbejdsordre, filnavne, alt-tekster, rækkefølge 0,5/0,5 · Batch 2-gentildeling: de seks kollektioner skal tildeles igen 0,4/0,4) |
| 2026-11-08 | Dark academia-rebrand | scope-korrektion: kollektionstildelingen er ikke i effekt — 0,4 t gentildeling tilføjet | 64,3 % → 62,5 % | -1,8 | 9 af 14,4 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5 · Batch 4-forberedelse: arbejdsordre, filnavne, alt-tekster, rækkefølge 0,5/0,5 · Batch 2-gentildeling: de seks kollektioner skal tildeles igen 0/0,4) |
| 2026-11-08 | steamgears.eu — site-optimering | S1.2's sidste 0,3 d (ejerens ja/nej pr. produkt 942/765/766/767) og S4's 1,2 d (S4.1 Complianz EU-valg, S4.2/S4.9 CVR+telefon, S4.3 valuta+24 priser, S4.5 betalingsaftaler, S4.6 zoner/fragtklasser, S4.7 mockups) er ejer-blokerede og flyttet ud af agent-estimatet. S1 er derfor 3,6 af 3,6 d og S4 4,05 af 4,05 d; de blokerede dage står i docs/roadmap.md §4c. | 93,3 % → 100 % | +6,7 | 20,95 af 20,95 d (S0 2,35/2,35 · S1 3,6/3,6 · S2 6,9/6,9 · S3 4,05/4,05 · S4 4,05/4,05) |
| 2026-11-08 | Steamgears Autopilot — plugin | P1.5's sidste 0,6 d er live-verifikation, der kræver ejerens tokens, og er flyttet ud af agent-estimatet: målt 8/11 svarer Pinterest 401 på den gemte token (kan ikke fornyes uden app-credentials) og 5 af 6 kanaler har intet token. P1 er derfor 14,45 af 14,45 d. | 98 % → 100 % | +2 | 29,85 af 29,85 d (P0 4,65/4,65 · P1 14,45/14,45 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-08 | Niche-butik — plugin P4 | daglig måling | 85 % → 100 % | +15 | 1 af 1 d (Plan-motor: arketyper, priser, sider 0,3/0,3 · Kladde-udrulning og rollback 0,25/0,25 · Admin-side, REST og fire evner 0,2/0,2 · Tests, zip og dokumentation 0,1/0,1 · Kørsel mod rigtig WooCommerce 0,15/0,15) |
| 2026-11-08 | Niche-butik — plugin P4 | baseline | — → 85 % | — | 0,85 af 1 d (Plan-motor: arketyper, priser, sider 0,3/0,3 · Kladde-udrulning og rollback 0,25/0,25 · Admin-side, REST og fire evner 0,2/0,2 · Tests, zip og dokumentation 0,1/0,1 · Kørsel mod rigtig WooCommerce 0/0,15) |
| 2026-11-08 | Dark academia-rebrand | daglig måling | 63 % → 64,3 % | +1,3 | 9 af 14 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5 · Batch 4-forberedelse: arbejdsordre, filnavne, alt-tekster, rækkefølge 0,5/0,5) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 92,9 % → 93,3 % | +0,4 | 20,95 af 22,45 d (S0 2,35/2,35 · S1 3,6/3,9 · S2 6,9/6,9 · S3 4,05/4,05 · S4 4,05/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 91,3 % → 92,9 % | +1,6 | 20,85 af 22,45 d (S0 2,35/2,35 · S1 3,6/3,9 · S2 6,9/6,9 · S3 4,05/4,05 · S4 3,95/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | afsluttende roadmap-pas | 88,6 % → 91,3 % | +2,7 | 20,05 af 21,95 d (S0 2,35/2,35 · S1 3,40/3,90 · S2 6,90/6,90 · S3 3,55/3,55 · S4 3,85/5,25) |
| 2026-11-08 | SEO-indholdsproduktion | daglig måling | 62,5 % → 100 % | +37,5 | 8 af 8 guides (Publicerede guides (id 292, 296, 295) 3/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) |
| 2026-11-08 | Steamgears Autopilot — plugin | daglig måling | 97,2 % → 98 % | +0,8 | 29,85 af 30,45 d (P0 4,65/4,65 · P1 14,45/15,05 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 85,9 % → 88,6 % | +2,7 | 21,3 af 24,05 d (S0 2,35/2,35 · S1 3,9/4,6 · S2 7,85/8,3 · S3 3,5/3,55 · S4 3,7/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 82,5 % → 85,9 % | +3,4 | 20,65 af 24,05 d (S0 2,35/2,35 · S1 3,9/4,6 · S2 7,85/8,3 · S3 3,5/3,55 · S4 3,05/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 81,3 % → 82,5 % | +1,2 | 19,85 af 24,05 d (S0 2,35/2,35 · S1 3,9/4,6 · S2 7,85/8,3 · S3 3,5/3,55 · S4 2,25/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 80,2 % → 81,3 % | +1,1 | 19,55 af 24,05 d (S0 2,15/2,35 · S1 3,9/4,6 · S2 7,85/8,3 · S3 3,4/3,55 · S4 2,25/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 76,5 % → 80,2 % | +3,7 | 19,3 af 24,05 d (S0 2,15/2,35 · S1 3,75/4,6 · S2 7,85/8,3 · S3 3,3/3,55 · S4 2,25/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 71,9 % → 76,5 % | +4,6 | 18,4 af 24,05 d (S0 2,15/2,35 · S1 3,45/4,6 · S2 7,65/8,3 · S3 2,9/3,55 · S4 2,25/5,25) |
| 2026-11-08 | steamgears.eu — site-optimering | daglig måling | 69,4 % → 71,9 % | +2,5 | 17,3 af 24,05 d (S0 2,15/2,35 · S1 2,75/4,6 · S2 7,6/8,3 · S3 2,55/3,55 · S4 2,25/5,25) |
| 2026-11-08 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-08 | SEO-indholdsproduktion | ingen ændring | 62,5 % → 62,5 % | 0 | 5 af 8 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) |
| 2026-11-08 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-08 | Steamgears Autopilot — plugin | ingen ændring | 97,2 % → 97,2 % | 0 | 29,6 af 30,45 d (P0 4,65/4,65 · P1 14,2/15,05 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-08 | steamgears.eu — site-optimering | ingen ændring | 69,4 % → 69,4 % | 0 | 16,7 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,6/8,3 · S3 2,55/3,55 · S4 2,25/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | S2.9 + S4.10 + S4.12 + S4.13 deployet og verificeret live via Novamira (+0,35 d) | 68 % → 69,4 % | +1,4 | 16,7 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,6/8,3 · S3 2,55/3,55 · S4 2,25/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | S2.5b — OG-billede målt på alle 30 indlæg og fast værn (+0,15 d) + S0.4 bekræftet uden cache | 67,4 % → 68 % | +0,6 | 16,35 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,5/8,3 · S3 2,55/3,55 · S4 2/5,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 96,7 % → 97,2 % | +0,5 | 29,6 af 30,45 d (P0 4,65/4,65 · P1 14,2/15,05 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 96,5 % → 96,7 % | +0,2 | 30,35 af 31,4 d (P0 4,65/4,7 · P1 14,2/15,2 · P2 4,75/4,75 · P3 2,75/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 65,9 % → 67,4 % | +1,5 | 16,2 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,35/8,3 · S3 2,55/3,55 · S4 2/5,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 96,3 % → 96,5 % | +0,2 | 30,3 af 31,4 d (P0 4,65/4,7 · P1 14,2/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 65,3 % → 65,9 % | +0,6 | 15,85 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,35/8,3 · S3 2,55/3,55 · S4 1,65/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | scope-ændring S2.5b (OG-billede) | 66 % → 65,3 % | -0,7 | 15,7 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,35/8,3 · S3 2,55/3,55 · S4 1,5/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 64,6 % → 66 % | +1,4 | 15,7 af 23,8 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,55/3,55 · S4 1,5/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 64,6 % → 65,2 % | +0,6 | 15,85 af 24,3 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,55/3,55 · S4 1,65/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 64,4 % → 64,6 % | +0,2 | 15,7 af 24,3 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,55/3,55 · S4 1,5/5,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 96,4 % → 96,3 % | -0,1 | 28,8 af 29,9 d (P0 4,65/4,7 · P1 14,2/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 96,3 % → 96,4 % | +0,1 | 29,3 af 30,4 d (P0 4,65/4,7 · P1 14,7/15,7 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 91,2 % → 96,3 % | +5,1 | 28,3 af 29,4 d (P0 4,65/4,7 · P1 14,2/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 91,1 % → 91,2 % | +0,1 | 26,8 af 29,4 d (P0 4,65/4,7 · P1 12,7/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 91 % → 91,1 % | +0,1 | 26,55 af 29,15 d (P0 4,65/4,7 · P1 12,7/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 90,9 % → 91 % | +0,1 | 26,3 af 28,9 d (P0 4,65/4,7 · P1 12,7/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 87,4 % → 90,9 % | +3,5 | 26,05 af 28,65 d (P0 4,65/4,7 · P1 12,7/15,2 · P2 4,75/4,75 · P3 2,45/2,5 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 87,3 % → 87,4 % | +0,1 | 25,05 af 28,65 d (P0 4,65/4,7 · P1 11,7/15,2 · P2 4,75/4,75 · P3 2,45/2,5 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | roadmap-bogføring — Etsy afgjort, dobbelttælling fjernet (scope) | 80,3 % → 87,3 % | +7 | 24,8 af 28,4 d (P0 4,65/4,7 · P1 11,7/15,2 · P2 4,75/4,75 · P3 2,45/2,5 · P0.7 1/1 · P0.7c 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 80,1 % → 80,3 % | +0,2 | 24,8 af 30,9 d (P0 4,65/4,7 · P1 11,7/15,2 · P2 4,5/7 · P3 2,7/2,75 · P0.7 1/1 · P0.7c 0,25/0,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 79,8 % → 80,1 % | +0,3 | 24,55 af 30,65 d (P0 4,65/4,7 · P1 11,7/15,2 · P2 4,5/7 · P3 2,7/2,75 · P0.7 1/1) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 79,4 % → 79,8 % | +0,4 | 24,05 af 30,15 d (P0 4,65/4,7 · P1 11,7/15,2 · P2 4,5/7 · P3 2,7/2,75 · P0.7 0,5/0,5) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 77,7 % → 79,4 % | +1,7 | 23,55 af 29,65 d (P0 4,65/4,7 · P1 11,7/15,2 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 76,7 % → 77,7 % | +1 | 23,05 af 29,65 d (P0 4,65/4,7 · P1 11,2/15,2 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 64 % → 64,4 % | +0,4 | 15,65 af 24,3 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,5/3,55 · S4 1,5/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 63,7 % → 64 % | +0,3 | 15,55 af 24,3 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 76,3 % → 76,7 % | +0,4 | 22,75 af 29,65 d (P0 4,35/4,7 · P1 11,2/15,2 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 76,1 % → 76,3 % | +0,2 | 22,25 af 29,15 d (P0 4,35/4,7 · P1 10,7/14,7 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 75,7 % → 76,1 % | +0,4 | 21,95 af 28,85 d (P0 4,35/4,7 · P1 10,4/14,4 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 62 % → 63,7 % | +1,7 | 15,15 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6,95/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 75,2 % → 75,7 % | +0,5 | 21,45 af 28,35 d (P0 4,35/4,7 · P1 9,9/13,9 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 74,9 % → 75,2 % | +0,3 | 20,95 af 27,85 d (P0 4,35/4,7 · P1 9,4/13,4 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 61,6 % → 62 % | +0,4 | 14,75 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6,55/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | S2.8 — hub-links på de resterende indlæg | 59,7 % → 61,6 % | +1,9 | 14,65 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6,45/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | daglig måling | 57,6 % → 59,7 % | +2,1 | 14,2 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-07 | steamgears.eu — site-optimering | S0.9 analytics — inline GA4-tag fjernet | 56,5 % → 57,6 % | +1,1 | 13,7 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-07 | Steamgears Autopilot — plugin | P0.2 — runway pr. kanal | 74,7 % → 74,9 % | +0,2 | 20,85 af 27,85 d = 74,9 % (11 nye checks, 555 PASS) |
| 2026-11-07 | Steamgears Autopilot — plugin | daglig måling | 74 % → 74,7 % | +0,7 | 20,65 af 27,65 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 2,7/2,75) |
| 2026-11-07 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-07 | SEO-indholdsproduktion | ingen ændring | 62,5 % → 62,5 % | 0 | 5 af 8 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) |
| 2026-11-07 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-07 | Steamgears Autopilot — plugin | ingen ændring | 74 % → 74 % | 0 | 19,9 af 26,9 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-07 | steamgears.eu — site-optimering | ingen ændring | 56,5 % → 56,5 % | 0 | 13,45 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-06 | steamgears.eu — site-optimering | daglig måling | 56,3 % → 56,5 % | +0,2 | 13,45 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| 2026-11-06 | steamgears.eu — site-optimering | daglig måling | 55,5 % → 56,3 % | +0,8 | 13,4 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,45/3,55 · S4 1,4/5,25) |
| 2026-11-06 | steamgears.eu — site-optimering | scope ændret — §9 P2–P5 indarbejdet (nævner 21,8 → 23,8 d) | 60,6 % → 55,5 % | -5,1 | 13,2 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,3/7,55 · S3 2,45/3,55 · S4 1,4/5,25) |
| 2026-11-06 | SEO-indholdsproduktion | daglig måling | 57,1 % → 62,5 % | +5,4 | 5 af 8 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) |
| 2026-11-06 | steamgears.eu — site-optimering | daglig måling | 58,3 % → 60,6 % | +2,3 | 13,2 af 21,8 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 5,3/6,05 · S3 2,45/3,55 · S4 1,4/5) |
| 2026-11-06 | Steamgears Autopilot — plugin | P1.4 — evergreen recycling | 64,7 % → 74 % | +9,3 | 19,9 af 26,9 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 1,95/2) — P1.4 2,5 d færdig i 7.41.0 |
| 2026-11-06 | Steamgears Autopilot — plugin | P0.6 færdig + P0-budget rettet | 65 % → 64,7 % | -0,3 | 17,4 af 26,9 d (P0 4,05/4,5 · P1 6,9/13,4 · P2 4,5/7 · P3 1,95/2); P0-budgettet 4,0 → 4,5 d fordi P0.6 aldrig var budgetteret, og P0.6 gik 0,25 → 0,5 d |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 64 % → 65 % | +1 | 17,15 af 26,4 d (P0 3,8/4 · P1 6,9/13,4 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 63,6 % → 64 % | +0,4 | 16,9 af 26,4 d (P0 3,55/4 · P1 6,9/13,4 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 62,5 % → 63,6 % | +1,1 | 16,6 af 26,1 d (P0 3,55/4 · P1 6,6/13,1 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 61,8 % → 62,5 % | +0,7 | 15,85 af 25,35 d (P0 3,55/4 · P1 5,85/12,35 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 61,4 % → 61,8 % | +0,4 | 15,35 af 24,85 d (P0 3,55/4 · P1 5,35/11,85 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 61 % → 61,4 % | +0,4 | 15,1 af 24,6 d (P0 3,55/4 · P1 5,1/11,6 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 59,3 % → 61 % | +1,7 | 14,85 af 24,35 d (P0 3,55/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1,95/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 58,5 % → 59,3 % | +0,8 | 14,45 af 24,35 d (P0 3,55/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1,55/2) |
| 2026-11-06 | Steamgears Autopilot — plugin | daglig måling | 57,5 % → 58,5 % | +1 | 14,25 af 24,35 d (P0 3,55/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1,35/2) |
| 2026-11-06 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-06 | SEO-indholdsproduktion | ingen ændring | 57,1 % → 57,1 % | 0 | 4 af 7 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4) |
| 2026-11-06 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-06 | Steamgears Autopilot — plugin | ingen ændring | 57,5 % → 57,5 % | 0 | 14 af 24,35 d (P0 3,55/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1,1/2) |
| 2026-11-06 | steamgears.eu — site-optimering | ingen ændring | 58,3 % → 58,3 % | 0 | 12,7 af 21,8 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,8/6,05 · S3 2,45/3,55 · S4 1,4/5) |
| 2026-11-05 | steamgears.eu — site-optimering | daglig måling | 58,2 % → 58,3 % | +0,1 | 12,7 af 21,8 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,8/6,05 · S3 2,45/3,55 · S4 1,4/5) |
| 2026-11-05 | Steamgears Autopilot — plugin | daglig måling | 56,3 % → 57,5 % | +1,2 | 14 af 24,35 d (P0 3,55/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1,1/2) |
| 2026-11-05 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-05 | SEO-indholdsproduktion | ingen ændring | 57,1 % → 57,1 % | 0 | 4 af 7 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4) |
| 2026-11-05 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-05 | steamgears.eu — site-optimering | ingen ændring | 58,2 % → 58,2 % | 0 | 12,65 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,75/6 · S3 2,45/3,55 · S4 1,4/5) |
| 2026-11-04 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-04 | SEO-indholdsproduktion | ingen ændring | 57,1 % → 57,1 % | 0 | 4 af 7 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4) |
| 2026-11-04 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-04 | Steamgears Autopilot — plugin | ingen ændring | 56,3 % → 56,3 % | 0 | 13,7 af 24,35 d (P0 3,35/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1/2) |
| 2026-11-04 | steamgears.eu — site-optimering | daglig måling | 57,9 % → 58,2 % | +0,3 | 12,65 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,75/6 · S3 2,45/3,55 · S4 1,4/5) |
| 2026-11-03 | Steamgears Autopilot — plugin | daglig måling | 55,6 % → 56,3 % | +0,7 | 13,7 af 24,35 d (P0 3,35/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1/2) |
| 2026-11-03 | Steamgears Autopilot — plugin | daglig måling | 45,4 % → 55,6 % | +10,2 | 13,55 af 24,35 d (P0 3,2/4 · P1 4,85/11,35 · P2 4,5/7 · P3 1/2) |
| 2026-11-03 | Steamgears Autopilot — plugin | daglig måling | 41,3 % → 45,4 % | +4,1 | 11,05 af 24,35 d (P0 3,2/4 · P1 4,85/11,35 · P2 2/7 · P3 1/2) |
| 2026-11-03 | Steamgears Autopilot — plugin | daglig måling | 37,2 % → 41,3 % | +4,1 | 10,05 af 24,35 d (P0 3,2/4 · P1 4,85/11,35 · P2 1/7 · P3 1/2) |
| 2026-11-03 | Steamgears Autopilot — plugin | daglig måling | 34,1 % → 37,2 % | +3,1 | 9,05 af 24,35 d (P0 3,2/4 · P1 4,85/11,35 · P2 0/7 · P3 1/2) |
| 2026-11-03 | steamgears.eu — site-optimering | scope-korrektion S2.6 | 58,4 % → 57,9 % | -0,5 | 12,60 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,7/6 · S3 2,45/3,55 · S4 1,4/5) — S2.6 var meldt 0,75, men 12 af 40 planlagte /go/-links ligger stadig kun i planen; kun de 20 i steampunk-outfit-ideas-for-women.md er anvendt |
| 2026-11-03 | steamgears.eu — site-optimering | daglig måling | 57 % → 58,4 % | +1,4 | 12,7 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,8/6 · S3 2,45/3,55 · S4 1,4/5) |
| 2026-11-03 | steamgears.eu — site-optimering | daglig måling | 56,8 % → 57 % | +0,2 | 12,4 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,8/6 · S3 2,15/3,55 · S4 1,4/5) |
| 2026-11-03 | steamgears.eu — site-optimering | daglig måling | 56,3 % → 56,8 % | +0,5 | 12,35 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,75/6 · S3 2,15/3,55 · S4 1,4/5) |
| 2026-11-03 | steamgears.eu — site-optimering | daglig måling | 55,4 % → 56,3 % | +0,9 | 12,25 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,65/6 · S3 2,15/3,55 · S4 1,4/5) |
| 2026-11-03 | SEO-indholdsproduktion | daglig måling | 50 % → 57,1 % | +7,1 | 4 af 7 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4) |
| 2026-11-03 | SEO-indholdsproduktion | daglig måling | 0 % → 50 % | +50 | 3 af 6 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295) 3/3) |
| 2026-11-03 | Steamgears Autopilot — plugin | daglig måling | 30 % → 34,1 % | +4,1 | 8,3 af 24,35 d (P0 3,2/4 · P1 4,85/11,35 · P2 0/7 · P3 0,25/2) |
| 2026-11-03 | steamgears.eu — site-optimering | daglig måling | 54,9 % → 55,4 % | +0,5 | 12,05 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,45/6 · S3 2,15/3,55 · S4 1,4/5) |
| 2026-11-03 | steamgears.eu — site-optimering | daglig måling | 54,5 % → 54,9 % | +0,4 | 11,8 af 21,5 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,45/6 · S3 1,9/3,3 · S4 1,4/5) |
| 2026-11-03 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-03 | SEO-indholdsproduktion | ingen ændring | 0 % → 0 % | 0 | 0 af 3 guides |
| 2026-11-03 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-03 | Steamgears Autopilot — plugin | ingen ændring | 30 % → 30 % | 0 | 7,3 af 24,35 d (P0 3,2/4 · P1 3,85/11,35 · P2 0/7 · P3 0,25/2) |
| 2026-11-03 | steamgears.eu — site-optimering | ingen ændring | 54,5 % → 54,5 % | 0 | 11,6 af 21,3 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,45/6 · S3 1,7/3,1 · S4 1,4/5) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 54,2 % → 54,5 % | +0,3 | 11,6 af 21,3 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,45/6 · S3 1,7/3,1 · S4 1,4/5) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 54 % → 54,2 % | +0,2 | 11,5 af 21,2 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,45/6 · S3 1,6/3 · S4 1,4/5) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 29,7 % → 30 % | +0,3 | 7,3 af 24,35 d (P0 3,2/4 · P1 3,85/11,35 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 29 % → 29,7 % | +0,7 | 7,2 af 24,25 d (P0 3,2/4 · P1 3,75/11,25 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 50 % → 54 % | +4 | 11,4 af 21,1 d (S0 1,9/2,6 · S1 2,05/4,5 · S2 4,45/6 · S3 1,6/3 · S4 1,4/5) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 24,8 % → 29 % | +4,2 | 6,95 af 24 d (P0 3,2/4 · P1 3,5/11 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 22,7 % → 24,8 % | +2,1 | 5,95 af 24 d (P0 3,2/4 · P1 2,5/11 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 18,5 % → 22,7 % | +4,2 | 5,45 af 24 d (P0 3,2/4 · P1 2/11 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 15 % → 18,5 % | +3,5 | 4,45 af 24 d (P0 3,2/4 · P1 1/11 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 48,3 % → 50 % | +1,7 | 10,55 af 21,1 d (S0 1,9/2,6 · S1 1,7/4,5 · S2 3,95/6 · S3 1,6/3 · S4 1,4/5) |
| 2026-11-02 | steamgears.eu — site-optimering | S0.7 trukket tilbage | 50,7 % → 48,3 % | -2,4 | S0.7 (sitemap 121/121 = 200) er ikke verificeret for søgemaskiner: Simply.com svarer 455 til Googlebot og bingbot. Genmåles efter hvidliste. S0 2,4/2,6 → 1,9/2,6. |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 45 % → 50,7 % | +5,7 | 10,7 af 21,1 d (S0 2,4/2,6 · S1 1,7/4,5 · S2 3,95/6 · S3 1,25/3 · S4 1,4/5) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 43,8 % → 45 % | +1,2 | 9,5 af 21,1 d (S0 1,9/2,6 · S1 1,7/4,5 · S2 3,25/6 · S3 1,25/3 · S4 1,4/5) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 10,7 % → 15 % | +4,3 | 3,45 af 23 d (P0 3,2/4 · P1 0/10 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | Steamgears Autopilot — plugin | daglig måling | 2 % → 10,7 % | +8,7 | 2,45 af 23 d (P0 2,2/4 · P1 0/10 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | Steamgears Autopilot — plugin | baseline | — → 2 % | — | 0,45 af 23 d (P0 0,2/4 · P1 0/10 · P2 0/7 · P3 0,25/2) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 39,3 % → 43,8 % | +4,5 | 9,25 af 21,1 d (S0 1,9/2,6 · S1 1,7/4,5 · S2 3/6 · S3 1,25/3 · S4 1,4/5) |
| 2026-11-02 | Døde internlinks | ingen ændring | 100 % → 100 % | 0 | 39 af 39 links |
| 2026-11-02 | SEO-indholdsproduktion | ingen ændring | 0 % → 0 % | 0 | 0 af 3 guides |
| 2026-11-02 | Dark academia-rebrand | ingen ændring | 63 % → 63 % | 0 | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| 2026-11-02 | steamgears.eu — site-optimering | daglig måling | 36,5 % → 39,3 % | +2,8 | 8,3 af 21,1 d (S0 1,9/2,6 · S1 1,4/4,5 · S2 3/6 · S3 0,6/3 · S4 1,4/5) |
| 2026-11-01 | steamgears.eu — site-optimering | daglig måling | 28,4 % → 36,5 % | +8,1 | 7,7 af 21,1 d (S0 1,9/2,6 · S1 1,4/4,5 · S2 2,4/6 · S3 0,6/3 · S4 1,4/5) |
| 2026-11-01 | Døde internlinks | daglig måling | 0 % → 100 % | +100 | 39 af 39 links |
| 2026-11-01 | steamgears.eu — site-optimering | daglig måling | 3,6 % → 28,4 % | +24,8 | 6 af 21,1 d (S0 1,9/2,6 · S1 0,4/4,5 · S2 1,7/6 · S3 0,6/3 · S4 1,4/5) |
| 2026-11-01 | Døde internlinks | Døde internlinks (baseline) | — → 0 % | — | 0 af 39 links rettet |
| 2026-11-01 | SEO-indholdsproduktion | Dagens TOP 3-guides (baseline) | — → 0 % | — | 0 af 3 guides publiceret |
| 2026-11-01 | Dark academia-rebrand | Batch 1–4 (baseline) | — → 63 % | — | 8,5 af 13,5 t |
| 2026-11-01 | steamgears.eu — site-optimering | S0–S4 (baseline) | — → 3,6 % | — | 0,75 af 21,1 d (S0 0,6/2,6 · S1 0/4,5 · S2 0/6 · S3 0/3 · S4 0,15/5) |
<!-- AUTO:LOG:END -->

---

## 4. Regnskab for roadmap-procenten (21,95 developer-dage)

Manuel tabel — opdateres når roadmap-estimaterne ændres, ikke af scriptet. Tallene skal matche
`projektstatus.json`, ellers er der to sandheder om samme beløb.

| Band | Estimat | Udført | Rest |
| --- | --- | --- | --- |
| S0 — brudt/akut | 2,35 d | 2,35 d | 0 (S0.6 mangler kun ejerens GSC-svar) |
| S1 — butik og indhold | 3,9 d | 3,4 d | 0,5 d — S1.2, ejer-blokeret (ja/nej pr. produkt) |
| S2 — struktur og SEO | 6,9 d | 6,9 d | 0 |
| S3 — hygiejne | 3,55 d | 3,55 d | 0 |
| S4 — salgsklar butik | 5,25 d | 3,85 d | 1,4 d — alt sammen ejer-blokeret (S4.1/4.2/4.3/4.5/4.6/4.7/4.9) |
| **I alt** | **21,95 d** | **20,05 d** | **1,9 d** |

Nævneren faldt 8/11 fra 24,05 d til 21,95 d som en dokumenteret scope-korrektion: S2.1 er 1,6 d
(seks børne-hubs bygges ikke, fordi de ville duplikere publicerede indlæg) og S1.6 er 0,3 d
(den målte leverance), ikke 1,0 d.

Kilde: [`roadmap.md`](roadmap.md) §4 og §4c samt
[`seo.md`](seo.md) §5 (målt 1/11, revideret 8/11).
