# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder
design/CSS, backup-data, dokumentation og status.

## Læs først

| Dokument | Hvad det er |
| --- | --- |
| [`docs/library/README.md`](docs/library/README.md) | **Dokumentindeks** — indgangen til hvert audit-, research- og driftsdokument. Start her. |
| [`docs/library/sitewide-audit-2026-11-03.md`](docs/library/sitewide-audit-2026-11-03.md) | Sitewide audit: prioriterede P0–P2-risici og anbefalede funktioner til WooCommerce-butikken |
| [`docs/library/deploy-roadmap-2026-11-03.md`](docs/library/deploy-roadmap-2026-11-03.md) | Deployment-roadmap: faser, estimater, porte for backup/rollback og testliste før og efter live |
| [`docs/roadmap.md`](docs/roadmap.md) | Optimeringsplanen for sitet, S0–S4 med tidestimater og blokeringer |
| [`docs/seo.md`](docs/seo.md) | SEO-regler, GEO-skabelon og måling |
| [`docs/library/seo-audit-2026-11-02.md`](docs/library/seo-audit-2026-11-02.md) | **Bred SEO-audit 2/11** — score 56,5/100, 21 fund med bevis, roadmap og tid til 100 % (PDF i `reports/seo-audit-2026-11-02.pdf`) |
| [`docs/projektstatus.md`](docs/projektstatus.md) | Daglig procentstatus pr. projekt, beregnet af `projektstatus.py` |
| [`AGENTS.md`](AGENTS.md) | Arbejdsregler: hvert pass melder % før → efter med grundlag, og §7 dækker plugin-arbejdet |
| [`docs/agent-profil.md`](docs/agent-profil.md) | **Agentprofil** — alt en bot skal vide: fakta, adgang, opskrifter, fælder. Kan også hentes over MCP |
| [`docs/steamgears-autopilot-projektbeskrivelse-2026-11-06.md`](docs/steamgears-autopilot-projektbeskrivelse-2026-11-06.md) | **Projektbeskrivelse af Autopilot-pluginnet** (7.37.0): filstruktur, roadmap, AIMLAPI- og nøglehåndtering — copy-paste-klar til et eksternt AI-værktøj |

Nye audits og research lægges i `docs/library/` og skrives ind i [dokumentindekset](docs/library/README.md),
så de ikke bliver forældreløse (`AGENTS.md` §5).

## Struktur

| Sti | Indhold |
| --- | --- |
| `docs/` | Syv hoveddokumenter: roadmap, seo, projektstatus, mcp-auth, adsense-setup, competitors, command-center-kilde — plus `agent-profil.md` til botter |
| `docs/library/` | Daterede audits, research, specs og driftslogger — historik, ikke planer. Kun det der stadig gælder |
| `docs/library/arkiv/` | Afløst materiale med afløseren noteret i indekset — tæller ikke som status |
| `docs/daily-briefs/` | Én brief pr. dag (`AUTO:PROJEKTSTATUS` skrives af CI) |
| `docs/site-checks/` | Rutine-tjek af den live side: PageSpeed, GSC, meta, alt-tekster |
| `reports/` | PDF-projektrapport pr. projekt: status med regnestykket, roadmap-indeks, udviklingsvej og hvad der virker/ikke virker |
| `wordpress/mu-plugins/` | Deploybare mu-plugins til sitet — i dag `sgap-product-schema.php` (brand, returret, leveringstid) og `sgap-mcp-schema-fix.php` + `aimlapi-tool-schema-sanitizer.php` (folder oneOf/anyOf/allOf ud af ability-skemaer) |
| `plugins/steamgears-autopilot/` | Autopilot-pluginnet, dets egen `ROADMAP.md` og release-zip i `plugins/` |
| `css/` | Live template part `extendable//header` (Dark Clockwork Theatre v3) |
| `knowledge_base/` | Høstede Google-kilder; læses af `seo_agent.py` sammen med `docs/seo.md` |
| `backup-2026-10-23/` | Fuld WP REST-backup taget lige før genopbygningen |
| `posters-seo/` | Produktbeskrivelser (HTML) til poster-serien |

## Værktøjer

| Værktøj | Hvad det gør |
| --- | --- |
| `tools/project_reports.py` | Genererer rapporterne i `reports/` ud fra `projektstatus.json` og roadmap'erne |
| `tools/roadmap_pdf.py` | Én samlet PDF med status, tid til 100 %, alle roadmaps og Command Centers dokumentation |
| `tools/md_to_pdf.py` | Renderer en Markdown-fil fra `docs/library` som A4-PDF i husstilen |
| `tools/mcp_project_profile.py` | Lokal MCP-server (stdio) der serverer profilen, status og roadmap'erne — registreret som `steamgears-profil` |
| `tools/niche_scanner.py` | Niche-produkt-scanner: Pinterest Trends + TikTok Creative Center-eksport × vores katalog |
| `tools/waf_check.sh` | WAF/UA-check: neutral klient skal give 200; spoofede bot-UA'er giver 455 (forventet) |
| `tools/setup-agent-tools.sh` | Genskaber PHP og PDF-bibliotekerne i `tools/_deps` (gitignored) — ingen root nødvendig |

`tools/_deps/` er gitignored og skal genskabes med `tools/setup-agent-tools.sh` før PDF-rendering virker i et
frisk miljø.

## Automation

- `.github/workflows/projektstatus.yml` — dagligt: selftest → check → update → efterkontrol, committer kun ved ændring.
- `.github/workflows/natlig-push.yml` — natligt kl. 02 dansk tid: opdaterer daglig status + brief, committer alt i
  træet, pusher til `main` og kører derefter alle værn via `tools/natlige-vaern.sh`.
- `.github/workflows/seo_audit.yml` — ugentligt: høster kilder, kører `seo_agent.py`, committer planen. Kræver
  `GEMINI_API_KEY`, `WP_USER` og `WP_APP_PASS` som repo-secrets.
- `.github/workflows/niche-scan.yml` — natligt kl. 02:30: scanner de 11 søgeord mod Pinterest Trends. Kræver
  `PINTEREST_ACCESS_TOKEN`; mangler det, springes scanningen over med en `::notice`.
- `.github/workflows/plugin-ci.yml` — lint + smoke/integration for pluginnet.

Workflowstatus og de tilhørende secrets skal verificeres i GitHub Actions; et dokumenteret workflow er ikke i sig
selv bevis for en vellykket kørsel. Den samme værns-kommando kan køres lokalt: `bash tools/natlige-vaern.sh`.

## Lokalt miljø

`cp .env.example .env` og udfyld nøglerne, koden læser — `GEMINI_API_KEY`, `WP_USER`, `WP_APP_PASS`,
`PINTEREST_ACCESS_TOKEN`, `MCP_ACCESS_TOKEN` (MCP mod sitet, hentes med `tools/mcp_oauth.py`) og `PUBLER_API_KEY`.
`.env` er gitignored; skabelonen er det ikke, og den indeholder kun navne. Til CI lægges de samme navne som
repo-secrets. Hvilke credentials der faktisk virker, står i [`docs/agent-profil.md`](docs/agent-profil.md) og
[`docs/mcp-auth.md`](docs/mcp-auth.md).

Ændringer på selve sitet sker over godkendt WordPress-adgang/MCP eller WordPress-admin, ikke ved at pushe dette
dokumentationsrepo til produktion. Plugin-udgivelse og installering er en egen releasevej; se plugin'ets
[README](plugins/steamgears-autopilot/README.md) og site-roadmappens [§2b](docs/roadmap.md).
