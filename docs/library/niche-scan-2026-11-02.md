# Niche-scanning (eget repo) — 2026-11-02

**Status: bygget, testet og i CI i `steamgears-site` — 92,9 % (3,25 af 3,5 d).** Scoren, de 62 Python-tests, workflowet, broen og plugin-modulet kører; det sidste er at flytte koden til `humle93/steamgears-niche-scan`, som **ikke er oprettet**, fordi `GITHUB_TOKEN` mangler i agent-miljøet.

Koden ligger midlertidigt her: [`niche_scan.py`](../../niche_scan.py), [`tests/test_niche_scan.py`](../../tests/test_niche_scan.py) og kandidatfilen [`niche-kandidater-2026-11-02.csv`](niche-kandidater-2026-11-02.csv). Den er stdlib-only (Python 3.12) og har ingen afhængigheder at flytte med.

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

| Modul | Estimat | Status | Grundlag |
| --- | --- | --- | --- |
| M1 — indlæsning og validering af kandidatdata | 0,5 d | **færdig** | CSV/JSON, danske feltnavne, manglende felter fejler med række og feltnavn |
| M2 — scoring efter niche-vurderingens grænser | 1,0 d | **færdig** | 45/25/20/10-point, normaliseret ved manglende felt, hårdt nej over 50.000 listings |
| M3 — Markdown-rapport og test | 0,5 d | **færdig** | 30 unittest-tests, grænseværdier + rangorden + validering |
| M4 — flyt til eget repo og CI | 0,5 d | **50 %** | workflowet [`niche-scan.yml`](../../.github/workflows/niche-scan.yml) kører testene her; selve flytningen kræver `GITHUB_TOKEN` |
| M5 — bro til social-køen (uden WP) | 0,4 d | **færdig** | [`niche_bridge.py`](../../niche_bridge.py) + 32 tests; se [`niche-social-bro-2026-11-02.md`](niche-social-bro-2026-11-02.md) |
| M6 — scorer og kø-bro som plugin-modul (PHP) | 0,4 d | **færdig** | [`includes/niche.php`](../../plugins/steamgears-autopilot/includes/niche.php) + 32 smoke-checks, paritet med Python målt; se [`niche-plugin-modul-2026-11-02.md`](niche-plugin-modul-2026-11-02.md) |
| M7 — CSV-indgang i plugin-modulet | 0,2 d | **færdig** | `parse_csv`/`read_csv_file` + 19 checks; kandidatfilen læses uændret og giver samme scorer |
| **I alt** | **3,5 d** | **3,25 af 3,5 d = 92,9 %** | M4 blev tilføjet, da koden var bygget men repoet ikke findes; M5 da broen blev bestilt; M6 da broen skulle kunne skrive i køen; M7 da filen skulle kunne læses inde i WordPress |

## Ikke i scope

- Automatisk research af nye nicher (kræver datakilde og nøgle).
- Pivot-anbefalinger. Værktøjet rangerer nicher; beslutningen om at skifte bliver hos ejeren.

## Sådan køres den nu

```
python3 niche_scan.py --input docs/library/niche-kandidater-2026-11-02.csv
python3 niche_scan.py --input kandidater.csv --report   # skriver docs/library/niche-scan-rapport-<dato>.md
python3 niche_scan.py --input kandidater.json --json --top 5
python3 -m unittest discover -s tests -v                  # 62 tests

# broen til social-køen (kræver en linkfil: niche,url)
python3 niche_bridge.py --input docs/library/niche-kandidater-2026-11-02.csv \
    --links links.csv --channels pinterest --out drafts.json --plan plan.md

# inde i WordPress, uden REST (fra v7.16.0; CSV fra v7.17.0)
# score-niches / queue-niches med enten "candidates" eller "csv" (rå tekst / fil under uploads)
# se niche-plugin-modul-2026-11-02.md
php plugins/tests/smoke.php                               # pluginets checks, inkl. niche-modulet
```

Resultat på de fire søgeord fra niche-vurderingen: goggles 80,8 (grøn) · corset 61,2 (gul) · top hat 53,6 (gul) · costume 38,9 (nej, hårdt nej-reglen slår til over 50.000 listings). Det er samme konklusion som den håndskrevne vurdering, men nu med et regnestykke bag.

## CI

[`.github/workflows/niche-scan.yml`](../../.github/workflows/niche-scan.yml) kører ved push og pull request til `niche_scan.py`, `tests/**` eller kandidatfilerne, samt manuelt. Tre trin: unit-testene på Python 3.10 og 3.12, en scanning af kandidatfilen (fejler hvis rapporten ikke nævner `steampunk goggles` eller den hårde regel), og to identiske kørsler der skal give byte-identisk JSON. Workflowet har ingen hemmeligheder og ingen netværksadgang — kun `contents: read`.

Det kan flyttes med uændret til det nye repo; eneste tilpasning er stien til kandidatfilen.

## Blokeret af

`GITHUB_TOKEN` — `python3 sync_repos.py --apply` nægter uden det og gætter ikke. Så snart tokenet er sat som projekt-secret, oprettes repoet og får `AGENTS.md`-kontrakten automatisk.

## Næste skridt

1. Sæt `GITHUB_TOKEN`, kør `python3 sync_repos.py --apply`.
2. M4: flyt `niche_scan.py`, `tests/` og kandidatfilen, og lad et workflow køre testene på hvert push.
3. Kør v7.18.0 på sitet og kald `score-niches` med enten kandidatfilens sti under uploads eller CSV-teksten — plugin-modulet er testet med stubbe, ikke i en installeret WordPress.
4. Udvid datagrundlaget — sæsonsving mangler for tre af fire kandidater, og det er den ene vægt der reelt kan flytte rækkefølgen.
