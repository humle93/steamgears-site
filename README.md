# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder
design/CSS, backup-data, dokumentation og status.

## Læs først

| Dokument | Hvad det er |
| --- | --- |
| [`docs/library/README.md`](docs/library/README.md) | **Dokumentindeks** — indgangen til hvert audit-, research- og driftsdokument. Start her. |
| [`docs/roadmap.md`](docs/roadmap.md) | Optimeringsplanen for sitet, S0–S4 med tidestimater og blokeringer |
| [`docs/seo.md`](docs/seo.md) | SEO-regler, GEO-skabelon og måling |
| [`docs/projektstatus.md`](docs/projektstatus.md) | Daglig procentstatus pr. projekt, beregnet af `projektstatus.py` |
| [`AGENTS.md`](AGENTS.md) | Arbejdsregler: hvert pass melder % før → efter med grundlag |

Reglen fra `AGENTS.md` §5: nye dokumenter lægges i `docs/` (audits og research i `docs/library/`) og skrives
ind i dokumentindekset, så de ikke bliver forældreløse.

## Struktur

| Sti | Indhold |
| --- | --- |
| `docs/` | Seks hoveddokumenter: roadmap, seo, projektstatus, mcp-auth, adsense-setup, competitors, command-center-kilde |
| `docs/library/` | Daterede audits, research, specs og driftslogger — historik, ikke planer |
| `docs/daily-briefs/` | Én brief pr. dag (`AUTO:PROJEKTSTATUS` skrives af CI) |
| `docs/site-checks/` | Rutine-tjek af den live side: PageSpeed, GSC, meta, alt-tekster |
| `reports/` | PDF-projektrapport pr. projekt: status med regnestykket, roadmap-indeks, udviklingsvej og hvad der virker/ikke virker |
| `tools/project_reports.py` | Genererer rapporterne i `reports/` ud fra `projektstatus.json` og roadmap'erne (`python3 tools/project_reports.py`) |
| `tools/setup-agent-tools.sh` | Genskaber PHP 8.5 og PDF-bibliotekerne i `tools/_deps` (gitignored) — ingen root nødvendig |
| `knowledge_base/` | Høstede Google-kilder; læses af `seo_agent.py` sammen med `docs/seo.md` |
| `plugins/steamgears-autopilot/` | Autopilot-pluginnet + dets egen `ROADMAP.md` |
| `backup-2026-10-23/` | Fuld WP REST-backup taget lige før genopbygningen |
| `css/` | Live template part `extendable//header` (Dark Clockwork Theatre v3) |
| `posters-seo/` | Produktbeskrivelser (HTML) til poster-serien |

## Automation

- `.github/workflows/projektstatus.yml` — dagligt: selftest → check → update → efterkontrol, committer kun ved ændring.
- `.github/workflows/seo_audit.yml` — ugentligt: høster kilder, kører `seo_agent.py`, committer planen. Kræver
  `GEMINI_API_KEY`, `WP_USER` og `WP_APP_PASS` som repo-secrets.
- `.github/workflows/plugin-ci.yml` — lint + smoke/integration for pluginnet.

Ændringer på selve sitet sker over MCP, ikke herfra. Repoet dokumenterer og forbereder dem.
