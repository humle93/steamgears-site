# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder
design/CSS, backup-data, dokumentation og status.

## Læs først

| Dokument | Hvad det er |
| --- | --- |
| [`docs/library/README.md`](docs/library/README.md) | **Dokumentindeks** — indgangen til hvert audit-, research- og driftsdokument. Start her. |
| [`docs/roadmap.md`](docs/roadmap.md) | Optimeringsplanen for sitet, S0–S4 med tidestimater og blokeringer |
| [`docs/seo.md`](docs/seo.md) | SEO-regler, GEO-skabelon og måling |
| [`docs/library/seo-audit-2026-11-02.md`](docs/library/seo-audit-2026-11-02.md) | **Bred SEO-audit 2/11** — score 56,5/100, 21 fund med bevis, roadmap og tid til 100 % (PDF i `reports/seo-audit-2026-11-02.pdf`) |
| [`docs/library/waf-diagnose-2026-11-02.md`](docs/library/waf-diagnose-2026-11-02.md) | **WAF-diagnose 2/11** — 455 rammer spoofede bot-UA'er, ikke Google; hvidlistning kræver en offentlig IP-liste (PDF i `reports/waf-diagnose-2026-11-02.pdf`) |
| [`docs/projektstatus.md`](docs/projektstatus.md) | Daglig procentstatus pr. projekt, beregnet af `projektstatus.py` |
| [`AGENTS.md`](AGENTS.md) | Arbejdsregler: hvert pass melder % før → efter med grundlag |
| [`docs/agent-profil.md`](docs/agent-profil.md) | **Agentprofil** — alt en bot skal vide: fakta, adgang, opskrifter, fælder. Kan også hentes over MCP |

Reglen fra `AGENTS.md` §5: nye dokumenter lægges i `docs/` (audits og research i `docs/library/`) og skrives
ind i dokumentindekset, så de ikke bliver forældreløse.

## Struktur

| Sti | Indhold |
| --- | --- |
| `docs/` | Syv hoveddokumenter: roadmap, seo, projektstatus, mcp-auth, adsense-setup, competitors, command-center-kilde — plus `agent-profil.md` til botter |
| `docs/library/` | Daterede audits, research, specs og driftslogger — historik, ikke planer. Kun det der stadig gælder |
| `docs/library/arkiv/` | Afløst materiale med afløseren noteret i indekset — tæller ikke som status |
| `docs/daily-briefs/` | Én brief pr. dag (`AUTO:PROJEKTSTATUS` skrives af CI) |
| `docs/site-checks/` | Rutine-tjek af den live side: PageSpeed, GSC, meta, alt-tekster |
| `reports/` | PDF-projektrapport pr. projekt: status med regnestykket, roadmap-indeks, udviklingsvej og hvad der virker/ikke virker |
| `tools/project_reports.py` | Genererer rapporterne i `reports/` ud fra `projektstatus.json` og roadmap'erne (`python3 tools/project_reports.py`) |
| `tools/roadmap_pdf.py` | Én samlet PDF med status, tid til 100 %, alle roadmaps og Command Centers dokumentation (`PYTHONPATH=tools/_deps python3 tools/roadmap_pdf.py`) |
| `tools/setup-agent-tools.sh` | Genskaber PHP 8.5 og PDF-bibliotekerne i `tools/_deps` (gitignored) — ingen root nødvendig |
| `tools/mcp_project_profile.py` | Lokal MCP-server (stdio) der serverer profilen, status og roadmap'erne — registreret som `steamgears-profil` |
| `tools/niche_scanner.py` | Niche-produkt-scanner: Pinterest Trends (API) + TikTok Creative Center-eksport × vores katalog → `reports/niche-scan-<dato>.md` |
| `wordpress/mu-plugins/` | Deploybare mu-plugins til sitet — i dag `sgap-product-schema.php` (brand, returret, leveringstid) |
| `tools/md_to_pdf.py` | Renderer en Markdown-fil fra `docs/library` som A4-PDF i husstilen (`python3 tools/md_to_pdf.py <md> <pdf>`) |
| `tools/waf_check.sh` | WAF/UA-check: neutral klient skal give 200; spoofede bot-UA'er giver 455 (forventet) — `bash tools/waf_check.sh` |
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

Lokalt miljø: `cp .env.example .env` og udfyld nøglerne, koden læser — `GEMINI_API_KEY`, `WP_USER`,
`WP_APP_PASS`, `PINTEREST_ACCESS_TOKEN`, `ROYAL_MCP_TOKEN` (MCP mod sitet) og `PUBLER_API_KEY`.
`.env` er gitignored; skabelonen er det ikke, og den indeholder kun navne. Til CI lægges de samme navne som
repo-secrets. Hvilke credentials der faktisk virker, står i [`docs/agent-profil.md`](docs/agent-profil.md) og
[`docs/mcp-auth.md`](docs/mcp-auth.md).

Ændringer på selve sitet sker over MCP, ikke herfra. Repoet dokumenterer og forbereder dem.

Udgivelse af pluginnet er zip'en på `main`: selv-opdateren tilbyder den under *Plugins → Update*, og det klik
_er_ deploy'en — MCP kan læse plugins, men ikke installere eller aktivere dem. Hvad der kører live mod hvad der
ligger i repoet, og hvordan en release efterkontrolleres, står i [`docs/roadmap.md`](docs/roadmap.md) §2b.
