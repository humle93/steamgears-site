# Verificeret register — ældre commits der ikke kan rettes

`tools/commit_check.py` kræver `Modul / Før / Efter / Δ / Grundlag` i hver commit-besked og
krydstjekker tallene mod historikken i [`projektstatus.json`](../projektstatus.json). En commit er
uforanderlig uden force-push, og force-push er ikke tilladt på `main`. Når en commit fra før værnene
blev skarpe (eller fra en anden agent) ikke kan opfylde reglen, bliver den derfor **efterprøvet i
hånden og registreret her** — én post pr. commit, med grund og bevis.

## Undtagelsen er pr. regel, ikke pr. commit

Hver post nævner i `relax` **præcis de regler der er efterprøvet og godkendt** for den commit:

| `relax` | Regel der lempes | Det betyder |
| --- | --- | --- |
| `basis` | `Grundlag` mod historik-rækkens `basis` | Før/Efter/Δ skal stadig matche en række, og beskeden skal stadig have alle fem felter |
| `history` | Før/Efter/Δ mod en historik-række | Formen, `Δ = Efter − Før` og kravet om et regnestykke gælder stadig |

Alt andet valideres som for enhver anden commit — også for de registrerede. En commit der både har
den registrerede fejl **og** mangler `Modul:` eller har et `Δ` der ikke passer, er derfor stadig rød.
Et nyt værn gælder også de gamle commits, medmindre det udtrykkeligt står i postens `relax`.

| Commit | Besked påstår | Rækken i historikken | Afvigelse | Lempet | Afgørelse |
| --- | --- | --- | --- | --- | --- |
| `1c15f939` | 76,3 % → 76,7 % (Δ +0,4), `P1.18 0,5 d af 29,65 d` | Række **96**: 76,3 → 76,7 %, `22,75 af 29,65 d (P0 4,35/4,7 · P1 11,2/15,2 · …)` | Ingen i tallene. `Grundlag` citerer **modul-deltaet** (P1 10,7 → 11,2 = 0,5 d) i stedet for totalen | `basis` | **Godkendt.** Procenterne er rækkens egne; kun formuleringen falder uden for det værnet kan genberegne |
| `c747a47a` | 91,0 % → 96,2 % (Δ +5,2), `27,8 af 28,9 d` | Række **110**: 91,2 → 96,3 % (Δ +5,1), `28,3 af 29,4 d` | Statuslinjen blev udregnet **før** det merge der satte række 110 (samme dag, samme arbejde) | `history` | **Godkendt med note.** Arbejdet er på `main` (`includes/instagram.php`, `SGAP_Instagram`), og den gældende status er række 110's. Beskedens tal er en overhalet mellemregning, ikke en påstand om mere arbejde |

## Sådan tilføjes en post

1. Slå commit'en op: `git log -1 --format=%B <sha>` og find den historik-række den refererer til.
2. Sammenlign `Før`/`Efter`/`Δ` og `Grundlag` med rækkens `before`, `after`, `delta` og `basis`.
3. Afgør **hvilken enkelt regel** der ikke kan opfyldes. Kan afvigelsen ikke forklares ud fra en
   række, må commit'en ikke registreres — så er der reelt meldt falsk fremgang, og det skal stå i
   roadmap'ets changelog i stedet.
4. Tilføj SHA (mindst 8 tegn), `relax` (kun kendte nøgler: `basis`, `history`) og grunden til
   `VERIFIED_LEGACY` i [`tools/commit_check.py`](../tools/commit_check.py), og en række her.
5. `python3 tools/commit_check.py --selftest` skal være grøn; den tjekker at hver post har en
   begrundelse, en SHA på mindst 8 tegn og kun kendte lempelser, og at en lempelse **ikke** dækker
   andre regler.

Registret er ikke en sovepude: `--range` skriver hver undtagelse med sin grund og hvilken regel der
blev lempet, så en udvidelse af listen er synlig i CI-loggen. Den adversariske kontrol 7/11 er
dokumenteret i [`library/vaern-adversarisk-kontrol-2026-11-07.md`](library/vaern-adversarisk-kontrol-2026-11-07.md).
