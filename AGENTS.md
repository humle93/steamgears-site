# AGENTS.md — arbejdsregler for steamgears-site

Reglerne her gælder alt arbejde i dette repo, og de gælder også når arbejdet udføres af en AI-agent.

## 0. Startkontrakt (læses ved hver opstart)

<!-- AUTO:STARTKONTRAKT:START -->
Denne blok er den fælles besked til alle agenter, der arbejder i repoet. Den læses ved hver opstart, før noget andet, og hvert arbejdspas lukkes først, når alle syv punkter er besvaret.

| # | Punkt | Krav før passet lukkes |
| --- | --- | --- |
| 1 | **Timer** | Brugt tid angivet i modulets egen enhed (`t` eller `d`), ikke i "lidt" eller "meget". |
| 2 | **Procenter** | `Før → Efter (Δ)` beregnet ud fra [`projektstatus.json`](projektstatus.json), aldrig gættet. |
| 3 | **Dokumentation** | Nye fund og beslutninger i `docs/library/`, refereret fra `README.md` eller roadmap'en. |
| 4 | **Roadmap** | Berørt række i [`docs/site-roadmap-2026-11-01.md`](docs/site-roadmap-2026-11-01.md) har fået en changelog-linje for passet. |
| 5 | **% færdig** | Projekt- og modulprocent efter passet, med regnestykket. |
| 6 | **Resterende tid** | `total - done` pr. projekt, i modulernes enhed (fx `21,1 - 7,4 = 13,7 d tilbage`). |
| 7 | **MCP-login** | `WP_USER` + `WP_APP_PASS` eller `ROYAL_MCP_TOKEN` faktisk testet. Mangler de, er passet **blokeret**, ikke fremgang. |

Regler:

- Rækkefølgen er fast. Intet punkt springes over — mangler der noget, skrives `intet` eller `blokeret`, ikke en tom linje.
- Kort format: brug skabelonen i [`README.md`](README.md#statusformat-kort) (maks. 8 linjer). Længere analyser hører i `docs/library/`.
- **Alt arbejde afsluttes med commit og push**, så ejeren kan se det på GitHub. Den daglige status pushes automatisk af CI; agentens egne commits pushes i samme pas.
- Punkt 7 er først opfyldt ved faktisk skriveadgang. Et forsøg på login tæller ikke som login.
<!-- AUTO:STARTKONTRAKT:END -->

## 1. Fremskridtsprocent (obligatorisk)

Alt arbejde — hvert modul, hver opgave, hver batch — skal melde sin **procentvise fremgang**.
Ingen undtagelser: gælder både når noget laves færdigt, og når der bare arbejdes videre på det.

Ved hvert arbejdspas skal der meldes:

| Felt | Krav |
| --- | --- |
| Modul | Navnet på det modul/den opgave der blev arbejdet på |
| % før | Status før passet |
| % efter | Status efter passet |
| Δ | Ændringen i procentpoint |
| Grundlag | Regnestykket bag tallet (fx `3 af 5 faser` eller `0,75 af 21,1 d`) |

Regler for tallene:

- **Procenten skal kunne genberegnes** ud fra grundlaget. Ingen tal uden regnestykke, ingen "føles som 70 %".
  Brug de dokumenterede estimater (developer-dage i `docs/site-roadmap-2026-11-01.md`, timer i
  `docs/library/om-maerkning-dark-academia-2026-11-01.md` osv.) som nævner.
- **Første måling** skrives som `% før: —` (ingen tidligere baseline).
- **Procenten må ikke falde** uden at årsagen står i loggen (fx at scope er ændret eller et modul er opdelt).
- **100 % betyder færdigt og verificeret**, ikke "kode skrevet". Er noget blokeret, bliver modulet stående
  under 100 % med blokeringen noteret.
- **Blokeret arbejde tæller ikke som fremgang.** S0.4 er 0 %, selv om den er forsøgt.

## 2. Daglig projektstatus (obligatorisk)

Hvert **aktivt projekt** skal have en procentstatus hver dag i [`docs/projektstatus.md`](docs/projektstatus.md).

- Filen har to dele: **§1 Daglig status** (én række pr. aktivt projekt, dags dato) og **§2 Log** (alle målinger,
  nyeste først).
- En dag uden arbejde skrives stadig: samme procent, med grundlaget `ingen ændring`.
- `§1` overskrives hver dag; `§2` er append-only og må ikke ryddes.
- Daglig status skal **også meldes i svaret til ejeren**, ikke kun stå i filen.

## 3. Format

```
Modul: Dark academia-rebrand — batch 4 (mockups)
Før: 63 %  →  Efter: 78 %  (Δ +15)
Grundlag: 2 af 5 mockup-faser klar (4,5 t af 6,5 t i mockup-brief)
```

## 4. Automation og værn (`projektstatus.py`)

Reglen over holdes automatisk, så lidt som muligt afhænger af hukommelse:

- Procenterne **beregnes** ud fra [`projektstatus.json`](projektstatus.json) (moduler med `done`/`total`/`unit`).
  Ingen procenter tastes ind i Markdown.
- [`docs/projektstatus.md`](docs/projektstatus.md) skrives kun mellem `AUTO:`-markørerne. **Manuel tekst i en
  AUTO-blok er forbudt**, og `--check` fejler hvis den ændres — ret i stedet JSON-filen.
- Sådan opdateres en procent: ret `done`/`total` i `projektstatus.json`, kør `python3 projektstatus.py --update`.
  Scriptet beregner procenter, skriver filerne og tilføjer historik-rækken selv.
- `python3 projektstatus.py --brief` skriver samme status ind i `docs/daily-briefs/<dato>.md` (markør
  `AUTO:PROJEKTSTATUS`), så procenterne også står i det brief der læses hver dag. CI kører den efter `--update`.

**Værn før skrivning** (`--selftest`, `--check`):

- 22 selftest-fixtures bekræfter at værnene fanger fejl, før noget installeres eller skrives.
- Skema og typer, `done <= total`, `total > 0`, duplikerede id'er, blandede enheder i samme projekt,
  manglende enhed/grundlag, internationally gyldige datoer og ingen dato i fremtiden.
- **100 % kræver `"verified": true`** — ellers er tallet en påstand, ikke en status.
- **Fald i procent afvises** både i forhold til forrige række og til modulernes tal, medmindre rækken har
  `"scope_change": true` (og så skal årsagen stå i `module`/`basis`).
- `delta` skal passe med `after - before`. Drift i dokumentet (håndredigerede AUTO-blokke) fanges.

**Værn efter skrivning:**

- Atomisk skrivning (temp-fil + `os.replace`) og backup til `.projektstatus-backups/` (de 10 nyeste).
- Filerne læses og valideres igen bagefter; matcher indholdet ikke det beregnede, rulles den forrige version
  tilbage og kommandoen fejler.
- Kørselslås (`.projektstatus.lock`) så to samtidige kørsler ikke kan overskrive hinanden.
- CI (`.github/workflows/projektstatus.yml`) kører selftest → check → update → efterkontrol **hver dag**, fejler
  jobbet med en annotation ved brud og committer kun når noget faktisk ændrede sig.

Automationen dækker **den daglige status**. Det manuelle i §1 — at vurdere hvad der reelt blev udført og melde
`% før → % efter` i svaret og commit-beskeden — kan scriptet ikke gøre og falder stadig på den der arbejder.

## 5. Øvrige regler

- Repoet er dokumentation og assets for **steamgears.eu**; WordPress-kernen ligger hos Simply. Ændringer på
  selve sitet sker over MCP, ikke her.
- Sproget i commits og dokumentation er dansk, med tekniske termer på engelsk.
- Nye dokumenter lægges i `docs/` (audits og research i `docs/library/`), og refereres fra `README.md` eller
  roadmap'en, så de ikke bliver forældreløse.
