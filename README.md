# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder design/CSS, backup-data, planer og SEO-dokumentation.

## Struktur
- `backup-2026-10-23/` — fuld WP REST-backup (19 sider, 9 guides, 5 kategorier, 86 medier-metadata, 27 template-parts, 17 templates, 45 plugins) taget 23/10 2026 lige før genopbygningen. Billeder + zip: Juno `/downloads/steamgears-backup-2026-10-23.zip`.
- `css/header-dark-clockwork-active.html` — live template part `extendable//header` (Dark Clockwork Theatre v3 CSS + GA4 e-commerce fallback-shim), hentet 24/10 2026.
- `docs/` — AdSense-setup, konkurrentnoter, SEO-handlingsplan 24/10 (PDF-audit gap-analyse), keyword- & konkurrent-scan, SEO-research-log, daily brief.
- `docs/site-roadmap-2026-11-01.md` — samlet optimeringsplan for sitet (S0–S3) med plugin-roadmap'en importeret i §5.
- `docs/library/indholdsaudit-2026-11-01.md` — status på posts, sider og produkter målt via MCP 1/11 2026.
- `docs/library/hub-blueprint-2026-11-01.md` — **S2.1**: færdig copy, URL-kort og link-map til `/start-here/`, `/build/` og `/inspiration/` (6 sider i stedet for 13, med begrundelse), klar til indsættelse via MCP. `/start-here/` og `/build/` er live og verificeret 2/11; de fire øvrige sider er kladde-klar copy i `publish_hubs.py`.
- `publish_hubs.py` — WP-klare payloads (H1, SEO-titel, meta, HTML, parent) til de fire manglende hub-sider. `--dry-run` validerer længder, ordantal og at hvert link er målt 200; `--check-links` crawler dem; `--verify` sammenligner de live sider med payloadene og fanger soft-404. `--apply` opretter kladder og `--publish` publicerer via WP REST, når `WP_USER` + `WP_APP_PASS` findes i miljøet.
- `seo_title_audit.py` — crawler alle 121 sitemap-URLs cache-bustet og rapporterer titler over 62 / under 30 tegn, meta uden for 140–158 og soft-404 til `docs/library/titel-meta-audit-<dato>.md`.
  `--by-type` fordeler fundene på produkt, produktkategori, side/indlæg og forfatter; `--dupes` viser meta-tekster, der går igen på flere sider — det er sådan duplikater og template-problemer skelnes fra enkeltsider.
- [`docs/library/meta-produkter-2026-11-02.md`](docs/library/meta-produkter-2026-11-02.md) — de 6 færdige meta-tekster (140–158 tegn, unikke, kun fakta fra produktteksten) til produkterne, der deler samme boilerplate; afventer skriveadgang.
- [`docs/library/meta-strategi-2026-11-02.md`](docs/library/meta-strategi-2026-11-02.md) — beslutning om auto-genereret vs. håndskrevet meta pr. sidetype, og prioriteret plan P1–P5 for de 84 afvigelser; afventer skriveadgang.
- [`docs/library/titelrettelser-2026-11-02.md`](docs/library/titelrettelser-2026-11-02.md) — de fire live-titler over 62 tegn med præcis erstatningstekst og tegnoptælling; afventer skriveadgang.
- `docs/projektstatus.md` — **daglig procentstatus** på hvert aktivt projekt + log over fremgang pr. modul. Skrives af `projektstatus.py` ud fra `projektstatus.json` (procenter beregnes, indtastes ikke) og kører automatisk dagligt via `.github/workflows/projektstatus.yml`.
- [`AGENTS.md`](AGENTS.md) — arbejdsregler: hvert arbejdspas skal melde procentsats før → efter med grundlag, og hvert aktivt projekt har en daglig %-status.

## Sikkerhed
- [`docs/library/sikkerhed-ai-engine-2026-11-01.md`](docs/library/sikkerhed-ai-engine-2026-11-01.md) — AI Engine er nu **3.8.9 og inaktiv**, og MCP-OAuth-ruterne svarer 404, så CVE-2026-110732 (uautentificeret administrator-overtagelse) er lukket. **Sagen er lukket 1/11 2026** (sidecachen er purget, så den gamle chatbot-markup er væk); tilbage er kun WAF-blokering af `changelog.txt`/`readme.txt`.
- [`docs/library/sikkerhed-plugin-versioner-2026-11-01.md`](docs/library/sikkerhed-plugin-versioner-2026-11-01.md) — versionsstatus for alle 48 plugins pr. 23/10 2026: 16 er bagud, 3 af dem har en sikkerhedsrettelse der mangler.

## Noter
- Repoet oprettes og opdateres via GitHub API (commit-per-batch); ingen lokal git-CLI i Juno-workspace.
- Fix-ressourcer: sitemap-toggles via Rank Math REST, GTM Kit via `/wp-json/gtmkit/v1/set-options`, WAF-429 trick via steamgears.juno.page browser-side.
