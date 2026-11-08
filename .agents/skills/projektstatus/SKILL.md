---
name: projektstatus
description: Meld procentvis fremgang og vedligehold projektstatus i steamgears-site. Brug den når et arbejdspas ændrer filer i repoet eller lukker et roadmap-punkt, når projektstatus.json skal rettes, når værnene (--selftest, --check, --update, --brief) skal køres, eller når en procent skal op på 100 % eller falde.
---

# Projektstatus og fremskridtsprocent

Hvert pas i dette repo melder **Modul / % før / % efter / Δ / Grundlag**, og tallene kommer altid fra
`projektstatus.json` — aldrig fra hovedet. Denne skill er den korte udgave af `AGENTS.md` §1–2 og §6 til brug
under arbejdet; er de uenige, vinder `AGENTS.md` og `projektstatus.json`.

## 1. Hvornår skillen gælder

- Pas der **ændrer filer** i repoet eller lukker et roadmap-punkt (site S0–S4, plugin P0–P3, rebrand batch 1–4,
  en guide i SEO-batchen, et link i link-modulet).
- Pas hvor et modul **rammer 100 %**, eller hvor nogen vil **sænke** en procent.
- Når `--check` melder **drift**, eller statusdatoen er ældre end i dag.
- **Ikke** ved rene læsespørgsmål uden ændringer: der er intet at måle — svar, og rør ikke statusfilerne.

## 2. Sandheden står i JSON, ikke i Markdown

- `projektstatus.json` har fem projekter: `site` (developer-dage), `plugin` (developer-dage), `rebrand` (timer),
  `seo` (guides), `links` (links). Hvert modul har `id`, `name`, `done`, `total`, `unit`.
- `docs/projektstatus.md` er **genereret**. Kun blokkene mellem `AUTO:`-markørerne skrives af scriptet;
  håndredigering i dem giver drift ved næste `--check`.
- `100 %` kræver `"verified": true` på projektet — ellers er tallet en påstand.
- Et **fald** kræver `"scope_change": true` plus forklaringen i `note`/`basis`.
- `unit` gælder **pr. projekt**: developer-dage, timer, guides og links må ikke blandes inden for samme projekt.

## 3. Arbejdsgang — i denne rækkefølge

```bash
python3 projektstatus.py --selftest   # værnene tester sig selv (22 fixtures)
python3 projektstatus.py --check      # valider + drift, skriver intet
# ret done/total i projektstatus.json — kun tal der kan forsvares
python3 projektstatus.py --dry-run    # se hvilke historik-rækker der ville blive skrevet
python3 projektstatus.py --update     # beregn, skriv atomisk, backup, efterkontrol
python3 projektstatus.py --brief      # samme status ind i docs/daily-briefs/<dato>.md
python3 projektstatus.py --check      # efterkontrol: ingen drift
```

- Efter en manuel JSON-rettelse melder `--check` med vilje **drift**, indtil `--update` er kørt. Det er
  funktionen, ikke en fejl.
- `--update` tilføjer kun historik-rækker når et tal faktisk har ændret sig; er dagen allerede målt med samme
  tal, skriver den `uændret` og rører intet. Den skal derfor ikke køres "for en sikkerheds skyld".
- `--update` **beregner** kun det JSON'en siger — den kan ikke hæve en procent af sig selv.
- Rør ikke §4 (roadmap-regnskabet) i `docs/projektstatus.md` som led i dagens status; den er manuel og gælder
  estimaterne, ikke målingen.

## 4. Rapporteringen i svaret

```
Modul: S2.3 navigation — nav-links
Før: 54,9 %  →  Efter: 55,4 %  (Δ +0,5)
Grundlag: 12,05 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,45/6 · S3 2,15/3,55 · S4 1,4/5)
```

- **Første måling** skrives `% før: —` (ingen baseline).
- **Grundlaget skal kunne genberegnes** — `3 af 5 faser`, `0,25 af 5 t`, `39 af 39 links`. Ingen tal uden
  regnestykke.
- **Blokeret arbejde tæller ikke som fremgang.** Står et punkt fast på en ekstern blokering (manglende nøgle,
  ejerens klik, Printful-adgang), bliver procenten stående, og blokeringen skrives i svaret.
- **100 % = færdigt og verificeret**, ikke "kode skrevet".
- **Daglig status for alle fem projekter** skal også stå i svaret til ejeren, ikke kun i filen:

  | Projekt | Status i dag | Grundlag |
  | --- | --- | --- |
  | site · plugin · rebrand · seo · links | som i §1 i `docs/projektstatus.md` | samme grundlag |

- **Commit-beskeden bærer samme tal.** Dansk, med `🤖 Generated with Codebuff` + `Co-Authored-By`-fod.
  Værnet [`tools/commit_check.py`](../../../tools/commit_check.py) afviser beskeden hvis blokken mangler, hvis et
  procentfelt ikke er et tal eller en streg, hvis `Grundlag` ikke kan genberegnes, eller hvis `Δ` ikke passer med
  `Efter − Før`. Tallene krydstjekkes desuden mod historik-rækkerne i `projektstatus.json`: `Før`/`Efter`/`Δ`
  skal matche en række, og `Grundlag` skal stemme med rækkens `basis`, så et opdigtet regnestykke afvises.
  Er hooken ikke installeret i sandkassen: `python3 tools/commit_check.py --install`. Merge-, revert- og
  bot-commits er undtaget.

## 5. Efter passet

- Rører passet plugin, `tools/` eller PDF-rendereren: kør `bash tools/natlige-vaern.sh` før push
  (`php -l`, `--selftest`/`--check`, smoke, MCP-tests, PDF-kontrol).
- **Alt arbejde ender på `main`**: commit og `cloud_git`-push i samme tur. Den natlige kørsel kl. 02 er et
  sikkerhedsnet, ikke en erstatning — den ser ikke ucommittet arbejde i en sandkasse.
- `.projektstatus-backups/` og `.projektstatus.lock` er gitignored; backupper følger ikke med repoet.
- Instruktions-gaten: en turns **første** værktøjs-batch skal indeholde en læsning af `AGENTS.md`, ellers afvises
  hele batchen. Skrivninger og deres efterkontrol må ikke køre i samme batch.

## 6. Fælder

| Fælde | Lærdom |
| --- | --- |
| Drift | Betyder at nogen har redigeret i en AUTO-blok — ret i `projektstatus.json` og kør `--update`. |
| "Føles som 70 %" | Afvises. Uden grundlag er der ingen procent; skriv `% før: —` eller lad den stå. |
| 100 % uden `verified: true` | Afvises af `--update`, uanset hvor færdigt det føles. |
| Fald uden `scope_change` | Afvises både mod forrige række og mod modulernes tal. |
| Parallelle kørsler | Låsen (`.projektstatus.lock`, 600 s) afviser den anden — kør ikke `--update` samtidig med en anden agent. |
| `--today` | Kun til test; en dato i fremtiden afvises. |

## 7. Pegere

- Reglerne: [`AGENTS.md`](../../../AGENTS.md) §1–2 (procenter, daglig status) og §6 (agent-kørsel).
- Hvad projektet er, og hvad der er sandt nu: [`docs/agent-profil.md`](../../../docs/agent-profil.md).
- Rækkefølge og estimater: [`docs/roadmap.md`](../../../docs/roadmap.md) og
  [`plugins/steamgears-autopilot/ROADMAP.md`](../../../plugins/steamgears-autopilot/ROADMAP.md).
