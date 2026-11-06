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
Status pr. **2026-11-06** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`](../projektstatus.json)

| Projekt | Status i dag | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** *(roadmap S0–S4)* | **56,3 %** | 13,4 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,45/3,55 · S4 1,4/5,25) |
| **Steamgears Autopilot — plugin** | **74 %** | 19,9 af 26,9 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 1,95/2) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) · drift: Daglig loop kører |
| **Døde internlinks** | **100 %** | 39 af 39 links |
<!-- AUTO:DAGLIG:END -->

## 2. Aktive projekter og hvad der blokerer

<!-- AUTO:BLOKERINGER:START -->
| Projekt | Næste skridt | Blokering |
| --- | --- | --- |
| steamgears.eu — site-optimering (56,3 %) | Skriv de tre manglende guides fra den verificerede copy (S2.5-meta-runde og S2.6-links er kørt; S2.2 og corset-guiden er lukket 6/11); S1.1 Herbarium kræver varianter i Printify; S0.4 kræver én Redirection-gruppe (ejer); S0.9 kræver en browser-session med GA4 DebugView (to page_view i samme session) | S1.1 Herbarium kræver varianter i Printify; S0.4 5 vanity-301 kræver én Redirection-gruppe (ejer). S2.2 er lukket 6/11 — trust-siderne findes og er linket ind |
| Steamgears Autopilot — plugin (74 %) | P0.4 rigtig host-cron (ejer); P3.1 labor_rate (ejer); P1.5 er eksternt blokeret (Pinterest-token + Meta-review) | P1.5 kræver Pinterest-token (401) og Meta-review; P0.4 kræver host-cron (ejer); P3.1 mangler ejerens labor_rate-beslutning |
| Dark academia-rebrand (63 %) | Batch 4 — mockups (~130 billeder) | Printify-adgang |
| SEO-indholdsproduktion (62,5 %) | Skriv de tre manglende guides (mens-steampunk-costume-essentials, steampunk-clothing-three-budgets, womens-steampunk-essentials) fra den verificerede copy — corset-guiden er publiceret 6/11 | Intet eksternt for de tre resterende guides: copy, FAQ, priser og links ligger i docs/library/guides-2026-11-03.md. De otte /go/-id'er 411/475/481/485/497/567 kunne ikke verificeres fra sandkassen (Simplys WAF giver browser-UA en challenge), så nye produktlinks skal måles fra ejerens side eller efter WAF-hvidlistning |
| Døde internlinks (100 %) | Vedligehold: tjek nye indlæg mod live-URL'er | ingen |
<!-- AUTO:BLOKERINGER:END -->

---

## 3. Log

<!-- AUTO:LOG:START -->
Nyeste først. Append-only — rækker må ikke slettes eller ændres.

| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- | --- |
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

## 4. Regnskab for roadmap-procenten (23,8 developer-dage)

Manuel tabel — opdateres når roadmap-estimaterne ændres, ikke af scriptet. Tallene skal matche
`projektstatus.json`, ellers er der to sandheder om samme beløb.

| Band | Estimat | Udført | Rest |
| --- | --- | --- | --- |
| S0 — brudt/akut | 2,85 d | 1,9 d | 0,95 d (robots.txt, Health Check, Coming Soon, bot-adgang, 2 vanity-301, S0.9 analytics) |
| S1 — butik og indhold | 4,6 d | 2,15 d | 2,45 d (`/go/`-tracking, lead magnets, resten af kladde-triage) |
| S2 — struktur og SEO | 7,55 d | 5,5 d | 2,05 d (meta, H1'er, S2.7 payload, S2.8 hub-links) |
| S3 — hygiejne | 3,55 d | 2,45 d | 1,1 d (webp, designsider, plugins) |
| S4 — salgsklar butik | 5,25 d | 1,4 d | 3,85 d (valuta, betaling, anmeldelser, S4.13 merchant-schema) |
| **I alt** | **23,8 d** | **13,4 d** | **10,4 d** |

Kilde: [`roadmap.md`](roadmap.md) §4 og
[`seo.md`](seo.md) §5 (målt 1/11).
