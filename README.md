# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder
design/CSS, backup-data, dokumentation og status.

## Læs først

| Dokument | Hvad det er |
| --- | --- |
| [`docs/library/README.md`](docs/library/README.md) | **Dokumentindeks** — indgangen til hvert audit-, research- og driftsdokument. Start her. |
| [`docs/library/sitewide-audit-2026-11-03.md`](docs/library/sitewide-audit-2026-11-03.md) | Sitewide audit: prioriterede risici og funktioner til WooCommerce-butikken |
| [`docs/library/deploy-roadmap-2026-11-03.md`](docs/library/deploy-roadmap-2026-11-03.md) | Deployment-roadmap med faser, estimater, testporte og rollback-plan |
| [`docs/roadmap.md`](docs/roadmap.md) | Optimeringsplanen for sitet, S0–S4 med tidestimater og blokeringer |
| [`docs/seo.md`](docs/seo.md) | SEO-regler, GEO-skabelon og måling |
| [`docs/library/seo-audit-2026-11-02.md`](docs/library/seo-audit-2026-11-02.md) | Bred SEO-audit fra 2026-11-02 med scorecard, evidens og roadmap |
| [`docs/projektstatus.md`](docs/projektstatus.md) | Daglig procentstatus pr. projekt, beregnet af `projektstatus.py` |
| [`AGENTS.md`](AGENTS.md) | Arbejdsregler for agenter |
| [`docs/agent-profil.md`](docs/agent-profil.md) | Agentprofil — fakta, adgang, opskrifter og fælder |

Nye audits og research placeres i `docs/library/` og tilføjes til [dokumentindekset](docs/library/README.md).

## Struktur

| Sti | Indhold |
| --- | --- |
| `docs/` | Roadmap, SEO, projektstatus, MCP-auth og agentprofil |
| `docs/library/` | Daterede audits, research, specifikationer og driftsnoter |
| `docs/daily-briefs/` | Daglige briefs og status |
| `docs/site-checks/` | Målinger af den live side |
| `reports/` | PDF-projektrapporter og audit-eksporter, når de er genereret |
| `tools/` | Crawlers, rapportgeneratorer, MCP-hjælpere og testværktøjer |
| `wordpress/mu-plugins/` | Deploybare mu-plugins |
| `plugins/steamgears-autopilot/` | Plugin-kilde og dens særskilte releaseproces |
| `css/` | WordPress-template parts og designassets |

## Automation

Repoet indeholder blandt andet workflows for projektstatus, SEO-audit, plugin-CI, niche-scanning og natlig vedligeholdelse. Workflowstatus og de tilhørende secrets skal verificeres i GitHub Actions; et dokumenteret workflow er ikke i sig selv bevis for en vellykket live-deploy.

Lokale credentials skal ligge i `.env` eller sikre secret stores, aldrig i commits. Se [`.env.example`](.env.example), [`docs/agent-profil.md`](docs/agent-profil.md) og [`docs/mcp-auth.md`](docs/mcp-auth.md).

Ændringer på selve sitet sker over godkendt WordPress-adgang/MCP eller WordPress-admin, ikke ved at pushe tämän dokumentaatiorepon sisällön tuotantoon. Pluginin julkaisu ja asentaminen on oma releasepolkunsa; lisätiedot ovat pluginin [README](plugins/steamgears-autopilot/README.md) ja site-roadmapissa [§2b](docs/roadmap.md).
