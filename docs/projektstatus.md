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
Status pr. **2026-11-02** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`](../projektstatus.json)

| Projekt | Status i dag | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** *(roadmap S0–S4)* | **43,8 %** | 9,25 af 21,1 d (S0 1,9/2,6 · S1 1,7/4,5 · S2 3/6 · S3 1,25/3 · S4 1,4/5) |
| **Steamgears Autopilot — plugin** | **2 %** | 0,45 af 23 d (P0 0,2/4 · P1 0/10 · P2 0/7 · P3 0,25/2) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| **SEO-indholdsproduktion** | **0 %** | 0 af 3 guides · drift: Daglig loop kører |
| **Døde internlinks** | **100 %** | 39 af 39 links |
<!-- AUTO:DAGLIG:END -->

## 2. Aktive projekter og hvad der blokerer

<!-- AUTO:BLOKERINGER:START -->
| Projekt | Næste skridt | Blokering |
| --- | --- | --- |
| steamgears.eu — site-optimering (43,8 %) | S1.1 Herbarium-varianter (kræver Printify-mapping); derefter S2.3 navigation og S4.3 valuta efter ejerbeslutning | S1.1 Herbarium kræver varianter i Printify; S0.4 5 vanity-301 kræver én Redirection-gruppe (ejer) |
| Steamgears Autopilot — plugin (2 %) | P0.1 fejl-alarmering (0,5 d), derefter P0.2 kø-udtørring og P0.3 retry med backoff | Intet for P0. P1.5 kræver Pinterest-token (401) og Meta-review; P0.6 kræver Command Center |
| Dark academia-rebrand (63 %) | Batch 4 — mockups (~130 billeder) | Printify-adgang |
| SEO-indholdsproduktion (0 %) | Skriv guide id 292 (men's steampunk essentials) | ingen |
| Døde internlinks (100 %) | Vedligehold: tjek nye indlæg mod live-URL'er | ingen |
<!-- AUTO:BLOKERINGER:END -->

---

## 3. Log

<!-- AUTO:LOG:START -->
Nyeste først. Append-only — rækker må ikke slettes eller ændres.

| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- | --- |
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
| S0 — brudt/akut | 2,6 d | 1,9 d | 0,7 d (Health Check, Coming Soon, 5 vanity-301) |
| S1 — butik og indhold | 4,5 d | 1,7 d | 2,8 d (Herbarium-varianter, `/go/`-tracking, lead magnets) |
| S2 — struktur og SEO | 6,0 d | 3,0 d | 3,0 d (navigation, H1-beslutning, `/shipping/` og `/returns/`) |
| S3 — hygiejne | 3,0 d | 1,25 d | 2,4 d (webp, designsider, plugins) |
| S4 — salgsklar butik | 5,0 d | 1,4 d | 3,6 d (valuta, betaling, anmeldelser) |
| **I alt** | **21,1 d** | **9,25 d** | **11,85 d** |

Kilde: [`roadmap.md`](roadmap.md) §4 og
[`seo.md`](seo.md) §5 (målt 1/11).
