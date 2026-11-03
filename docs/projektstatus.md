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
Status pr. **2026-11-03** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`](../projektstatus.json)

| Projekt | Status i dag | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** *(roadmap S0–S4)* | **57 %** | 12,4 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,8/6 · S3 2,15/3,55 · S4 1,4/5) |
| **Steamgears Autopilot — plugin** | **34,1 %** | 8,3 af 24,35 d (P0 3,2/4 · P1 4,85/11,35 · P2 0/7 · P3 0,25/2) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| **SEO-indholdsproduktion** | **57,1 %** | 4 af 7 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4) · drift: Daglig loop kører |
| **Døde internlinks** | **100 %** | 39 af 39 links |
<!-- AUTO:DAGLIG:END -->

## 2. Aktive projekter og hvad der blokerer

<!-- AUTO:BLOKERINGER:START -->
| Projekt | Næste skridt | Blokering |
| --- | --- | --- |
| steamgears.eu — site-optimering (57 %) | S2.6 sidste 0,15 d (links på ældre publicerede guides) og S2.2 /shipping/ + /returns/ når WordPress-adgang er der; derefter S2.3 navigation | S1.1 Herbarium kræver varianter i Printify; S0.4 5 vanity-301 kræver én Redirection-gruppe (ejer) |
| Steamgears Autopilot — plugin (34,1 %) | P3.1/P3.2-hygiejne; P0.6 er blokeret af Command Center | 7.27.0 kan ikke installeres af agenten (OAuth-token tilbagekaldt, intet application password); P1.5 kræver Pinterest-token (401) og Meta-review; P0.6 kræver Command Center; P1.4 kræver P1.5 |
| Dark academia-rebrand (63 %) | Batch 4 — mockups (~130 billeder) | Printify-adgang |
| SEO-indholdsproduktion (57,1 %) | Publicér de fire guides — copy, FAQ og links er klar (292, 296, 295 + ny side /steampunk-corset-guide/) | Publicering kræver WordPress-adgang. Filer der nu venter kun på det: docs/library/guides-2026-11-03.md (post 292, 296, 295) og docs/library/guides-2026-11-03-corset.md (ny side), plus fotos, HowTo/FAQPage-schema i Rank Math og selve publish. Copy er skrevet og verificeret i repoet (tools/guide_check.py). |
| Døde internlinks (100 %) | Vedligehold: tjek nye indlæg mod live-URL'er | ingen |
<!-- AUTO:BLOKERINGER:END -->

---

## 3. Log

<!-- AUTO:LOG:START -->
Nyeste først. Append-only — rækker må ikke slettes eller ændres.

| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- | --- |
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

## 4. Regnskab for roadmap-procenten (21,1 developer-dage)

Manuel tabel — opdateres når roadmap-estimaterne ændres, ikke af scriptet.

| Band | Estimat | Udført | Rest |
| --- | --- | --- | --- |
| S0 — brudt/akut | 2,6 d | 1,9 d | 0,7 d (robots.txt, Health Check, Coming Soon, bot-adgang, 2 vanity-301) |
| S1 — butik og indhold | 4,5 d | 2,05 d | 2,45 d (`/go/`-tracking, lead magnets, resten af kladde-triage) |
| S2 — struktur og SEO | 6,0 d | 4,45 d | 1,55 d (11 meta-rettelser, 2 H1'er, nav-punkter, `/shipping/` + `/returns/`) |
| S3 — hygiejne | 3,0 d | 1,6 d | 1,4 d (webp, designsider, plugins) |
| S4 — salgsklar butik | 5,0 d | 1,4 d | 3,6 d (valuta, betaling, anmeldelser) |
| **I alt** | **21,1 d** | **11,4 d** | **9,7 d** |

Kilde: [`roadmap.md`](roadmap.md) §4 og
[`seo.md`](seo.md) §5 (målt 1/11).
