# Verificeret register — ældre commits der ikke kan rettes

`tools/commit_check.py` kræver `Modul / Før / Efter / Δ / Grundlag` i hver commit-besked og
krydstjekker tallene mod historikken i [`projektstatus.json`](../projektstatus.json). En commit er
uforanderlig uden force-push, og force-push er ikke tilladt på `main`. Når en commit fra før værnene
blev skarpe (eller fra en anden agent) ikke kan opfylde reglen, bliver den derfor **efterprøvet i
hånden og registreret her** — én post pr. commit, med grund og bevis. Registret undtager den
enkelte commit; det lemper ikke reglen for nye.

| Commit | Besked påstår | Rækken i historikken | Afvigelse | Afgørelse |
| --- | --- | --- | --- | --- |
| `1c15f939` | 76,3 % → 76,7 % (Δ +0,4), `P1.18 0,5 d af 29,65 d` | Række **96**: 76,3 → 76,7 %, `22,75 af 29,65 d (P0 4,35/4,7 · P1 11,2/15,2 · …)` | Ingen i tallene. `Grundlag` citerer **modul-deltaet** (P1 10,7 → 11,2 = 0,5 d) i stedet for totalen, og værnet kan kun læse totalen ud af rækken | **Godkendt.** Procenterne er rækkens egne; kun formuleringen falder uden for det værnet kan genberegne |
| `c747a47a` | 91,0 % → 96,2 % (Δ +5,2), `27,8 af 28,9 d` | Række **110**: 91,2 → 96,3 % (Δ +5,1), `28,3 af 29,4 d` | Statuslinjen blev udregnet **før** det merge der satte række 110 (samme dag, samme arbejde) | **Godkendt med note.** Arbejdet er på `main` (`includes/instagram.php`, `SGAP_Instagram`), og den gældende status er række 110's. Beskedens tal er en overhalet mellemregning, ikke en påstand om mere arbejde |

## Sådan tilføjes en post

1. Slå commit'en op: `git log -1 --format=%B <sha>` og find den historik-række den refererer til.
2. Sammenlign `Før`/`Efter`/`Δ` og `Grundlag` med rækkens `before`, `after`, `delta` og `basis`.
3. Kan tallene **ikke** forklares ud fra en række, må commit'en ikke registreres — så er der reelt
   meldt falsk fremgang, og det skal stå i roadmap'ets changelog i stedet.
4. Tilføj SHA (mindst 8 tegn) og grunden til `VERIFIED_LEGACY` i
   [`tools/commit_check.py`](../tools/commit_check.py), og en række her.
5. `python3 tools/commit_check.py --selftest` skal være grøn; værnet tæller registeret med i svaret.

Registret er ikke en sovepude: `--range` skriver hver undtagelse med sin grund, så en udvidelse af
listen er synlig i CI-loggen, og `--selftest` fejler hvis et opslag på en forkortelse under 7 tegn
rammer et register.
