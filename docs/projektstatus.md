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
Status pr. **2026-11-01** · beregnet af `projektstatus.py` · kilder: [`projektstatus.json`](../projektstatus.json)

| Projekt | Status i dag | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** *(roadmap S0–S4)* | **3,6 %** | 0,75 af 21,1 d (S0 0,6/2,6 · S1 0/4,5 · S2 0/6 · S3 0/3 · S4 0,15/5) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (Batch 1–3: kortlægning, titler/SEO/kollektioner, 39 produkttekster 8,5/8,5 · Batch 4: mockups 0/5) |
| **SEO-indholdsproduktion** | **0 %** | 0 af 3 guides · drift: Daglig loop kører |
| **Døde internlinks** | **0 %** | 0 af 39 links |
<!-- AUTO:DAGLIG:END -->

## 2. Aktive projekter og hvad der blokerer

<!-- AUTO:BLOKERINGER:START -->
| Projekt | Næste skridt | Blokering |
| --- | --- | --- |
| steamgears.eu — site-optimering (3,6 %) | S0.2, S0.5, S0.6, S0.7 — config, rører ikke indhold | S0.4 kræver én Redirection-gruppe (ejer) |
| Dark academia-rebrand (63 %) | Batch 4 — mockups (~130 billeder) | Printify-adgang |
| SEO-indholdsproduktion (0 %) | Skriv guide id 292 (men's steampunk essentials) | ingen |
| Døde internlinks (0 %) | Ret linkene i library/dode-links-2026-11-01.md | ingen |
<!-- AUTO:BLOKERINGER:END -->

---

## 3. Log

<!-- AUTO:LOG:START -->
Nyeste først. Append-only — rækker må ikke slettes eller ændres.

| Dato | Projekt | Modul | Før → Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- | --- |
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
| S0 — brudt/akut | 2,6 d | 0,6 d (S0.1, S0.3, S0.8) | 2,0 d (heraf S0.4 0,5 d blokeret) |
| S1 — butik og indhold | 4,5 d | 0 d | 4,5 d |
| S2 — struktur og SEO | 6,0 d | 0 d | 6,0 d |
| S3 — hygiejne | 3,0 d | 0 d | 3,0 d |
| S4 — salgsklar butik | 5,0 d | 0,15 d (S4.4 formular-del) | 4,85 d |
| **I alt** | **21,1 d** | **0,75 d** | **20,35 d** |

Kilde: [`site-roadmap-2026-11-01.md`](site-roadmap-2026-11-01.md) §4 og
[`library/butiksaudit-2026-11-01.md`](library/butiksaudit-2026-11-01.md).
