# Niche-scanning (eget repo) — 2026-11-02

**Status: planlagt, 0 % bygget.** Registreret i [`projektstatus.json`](../../projektstatus.json) og `repos.json`. Repoet `humle93/steamgears-niche-scan` er **ikke oprettet** — `GITHUB_TOKEN` mangler.

## Hvorfor

[`niche-vurdering-2026-11-01.md`](niche-vurdering-2026-11-01.md) konkluderede "skift ikke niche", men byggede på håndindtastede tredjepartstal (RankHero/Etsy) og én engangsresearch. Næste gang spørgsmålet kommer — og det gør det, hver gang et produkt flopper — skal svaret kunne genberegnes, ikke huskes. Det er hvad dette repo skal levere: **samme rubrik, kørt igen, med tallene som input.**

## Hvad værktøjet gør

1. Læser kandidat-nicher fra en CSV/JSON (søgeord, søgninger/md, antal listings, sværhedsgrad, sæsonsving, gennemsnitspris).
2. Scorer hver niche efter de grænser, der allerede er besluttet i niche-vurderingen:
   - listings < 2.000 = grøn, 2.000–50.000 = gul, > 50.000 = nej (Printifys tommelfingerregel)
   - sæsonsving (okt/jun-forhold) som selvstændigt fradrag, ikke som diskvalifikation
   - konkurrenttype (håndlavet vs. POD) som kvalitativt flag
3. Skriver en Markdown-rapport med rækkefølge, begrundelse pr. niche og hvad der mangler af data.

Ingen API-nøgler i første version: tallene indtastes eller importeres. Det gør værktøjet deterministisk og testbart, og det flytter ikke beslutningen over i et tredjepartslink. En senere datakilde (fx SerpApi, som pluginnet allerede kender) er en udvidelse, ikke en forudsætning.

## Moduler og estimat

| Modul | Estimat | Grundlag |
| --- | --- | --- |
| M1 — indlæsning og validering af kandidatdata | 0,5 d | CSV/JSON, manglende felter skal fejle højt |
| M2 — scoring efter niche-vurderingens grænser | 1,0 d | 3 regler + sæsonfradrag + konkurrentflag |
| M3 — Markdown-rapport og CI-test | 0,5 d | fixture med kendte nicher, forventet rækkefølge |
| **I alt** | **2,0 d** | Rubrikken findes; arbejdet er at gøre den kørbar |

## Ikke i scope

- Automatisk research af nye nicher (kræver datakilde og nøgle).
- Pivot-anbefalinger. Værktøjet rangerer nicher; beslutningen om at skifte bliver hos ejeren.

## Blokeret af

`GITHUB_TOKEN` — `python3 sync_repos.py --apply` nægter uden det og gætter ikke. Så snart tokenet er sat som projekt-secret, oprettes repoet og får `AGENTS.md`-kontrakten automatisk.

## Næste skridt

1. Sæt `GITHUB_TOKEN`, kør `python3 sync_repos.py --apply`.
2. M1 + M3 sammen, så scoringen har en test fra dag ét.
3. M2, med niche-vurderingens fire søgeord som første fixture.
