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
Status pr. **2026-11-08** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`](../projektstatus.json)

| Projekt | Status i dag | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** *(roadmap S0–S4)* | **88,6 %** | 21,3 af 24,05 d (S0 2,35/2,35 · S1 3,9/4,6 · S2 7,85/8,3 · S3 3,5/3,55 · S4 3,7/5,25) |
| **Steamgears Autopilot — plugin** | **98 %** | 29,85 af 30,45 d (P0 4,65/4,65 · P1 14,45/15,05 · P2 4,75/4,75 · P3 2/2 · P0.7 1/1 · P0.7c 0,25/0,25 · P1.19 0,25/0,25 · P1.20 0,25/0,25 · P1.21 0,25/0,25 · P1.22 0,5/0,5 · P2.6 1,5/1,5) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (Publicerede guides (id 292, 296, 295) 0/3 · Guide-copy klar til publicering (292, 296, 295 + corset) 4/4 · Corset-guiden publiceret (/steampunk-corset-guide/, 1394) 1/1) · drift: Daglig loop kører |
| **Døde internlinks** | **100 %** | 39 af 39 links |
<!-- AUTO:DAGLIG:END -->

## 2. Aktive projekter og hvad der blokerer

<!-- AUTO:BLOKERINGER:START -->
| Projekt | Næste skridt | Blokering |
| --- | --- | --- |
| steamgears.eu — site-optimering (88,6 %) | Tilbage af S4 kræver ejeren: CVR/adresse/telefon (S4.2) og dermed GPSR-blokken (S4.9), valuta og priser (S4.3), betalingsaftaler (S4.5), forsendelsesvalg (S4.6), Printify-billeder (S4.7) og EU-samtykkemodellen i Complianz (S4.1). | S4's resterende punkter kræver ejerens oplysninger og aftaler: CVR, adresse og telefon; valutaomregning af 39 priser; MobilePay/PayPal/Klarna-aftaler; forsendelseszoner og fri fragt-grænse; Printify-adgang til mockups; og valg af EU-samtykkemodel i Complianz. |
| Steamgears Autopilot — plugin (98 %) | P1.5's sidste 0,60 d er live-verifikation og kræver ejerens tokens: Pinterest (401 i dag), Instagram (intet token), Bluesky/Mastodon, Telegram og Facebook. Kanalstatus i 7.61.0 gør turen ét klik. | Pinterest-tokenet er personligt og svarer 401; Instagram kræver Meta-app + IG Business-konto; Bluesky/Mastodon kræver app-password/instans-token; Telegram kræver BotFather-token; Facebook kræver Meta App Review. Ingen af dem må indsættes i chat eller repo. |
| Dark academia-rebrand (63 %) | Batch 4 — mockups (~130 billeder) | Batch 4-mockups (0 af 5 t) kan ikke laves her: baade roadmap og library/om-maerkning-dark-academia-2026-11-01.md siger at mockups skal genereres i Printify eller uploades, og der er ingen Printify-adgang i dette miljoe. Kræver ejerens Printify-adgang eller upload. |
| SEO-indholdsproduktion (62,5 %) | Fire guides er skrevet færdige som kladder (1410: 11 403 tegn, 1361: 11 454, 1359: 11 237, 1357). Publicering er ejerens redaktionelle valg; intet er publiceret uden den. | Publicering af guide-kladderne kræver ejerens valg af hvilke der skal ud og hvornår. Tidligere modulnavn pegede på id 292/296/295, som er produktvarianter (t-shirt L/M/S), ikke guides. |
| Døde internlinks (100 %) | Vedligehold: tjek nye indlæg mod live-URL'er | ingen |
<!-- AUTO:BLOKERINGER:END -->

---

## 3. Log

<!-- AUTO:LOG:START -->
Nyeste først. Append-only — rækker må ikke slettes eller ændres.

| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- | --- |
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

## 4. Regnskab for roadmap-procenten (23,8 developer-dage)

Manuel tabel — opdateres når roadmap-estimaterne ændres, ikke af scriptet. Tallene skal matche
`projektstatus.json`, ellers er der to sandheder om samme beløb.

| Band | Estimat | Udført | Rest |
| --- | --- | --- | --- |
| S0 — brudt/akut | 2,85 d | 2,15 d | 0,7 d (robots.txt, Health Check, Coming Soon, bot-adgang, 2 vanity-301) |
| S1 — butik og indhold | 4,6 d | 2,15 d | 2,45 d (`/go/`-tracking, lead magnets, resten af kladde-triage) |
| S2 — struktur og SEO | 7,55 d | 6,55 d | 1,0 d (S2.7's trin 0–2) |
| S3 — hygiejne | 3,55 d | 2,5 d | 1,05 d (webp, designsider, plugins) |
| S4 — salgsklar butik | 5,25 d | 1,4 d | 3,85 d (valuta, betaling, anmeldelser, S4.13 merchant-schema) |
| **I alt** | **23,8 d** | **14,75 d** | **9,05 d** |

Kilde: [`roadmap.md`](roadmap.md) §4 og
[`seo.md`](seo.md) §5 (målt 1/11).
