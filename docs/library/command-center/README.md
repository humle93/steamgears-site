# Command Center — kopieret dokumentation

Kopieret 2/11 2026 fra [`humle93/command-center`](https://github.com/humle93/command-center) på commit
`ac7dd53` (version **7.14.0** i `app/main.py`). Kilden er master; rettelser sker i det repo, ikke her.

| Fil | Indhold |
| --- | --- |
| [ROADMAP.md](ROADMAP.md) | Command Centers egen roadmap og prioriteringer |
| [Command_Center_Dokumentation.md](Command_Center_Dokumentation.md) | Arkitektur, dataflow, sikkerhedsmodel, jobs og ruter |
| [Command_Center_Bruger_og_Systemmanual.md](Command_Center_Bruger_og_Systemmanual.md) | Manual: de 14 faner, login, brugere, drift |
| [Command_Center_Install_Script.md](Command_Center_Install_Script.md) | Hvad `install.sh` skriver, og hvad den ikke rører |
| [img/](img/) | Skærmbilleder brugt i manualen (taget mod 7.13.x-UI'et) |

## Om links

De kopierede dokumenter er skrevet til at ligge i Command Center-repoet, så nogle relative links peger på
filer der **kun** findes der:

- `../AGENTS.md` og `../CLAUDE.md` → udviklerinstruktioner i Command Center-repoet (bevidst ikke kopieret her,
  så de ikke bliver læst som regler for dette repo).
- `../docs/...` og `Command_Center_Install_Script.md` → findes i Command Center-repoet; installationsdokumentet
  er kopieret her, så det link virker.

Ved tvivl: slå filen op i [`humle93/command-center`](https://github.com/humle93/command-center) i stedet for at
gætte ud fra kopien.

## Se også

- [`../command-center-opdatering-2026-11-02.md`](../command-center-opdatering-2026-11-02.md) — opdatering af
  installationen på Ultra.cc til 7.14 med bruger-login.
- [`../../command-center-kilde.md`](../../command-center-kilde.md) — hvor kilden ligger, og hvad der ikke må
  bruges.
