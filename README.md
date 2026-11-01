# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder design/CSS, backup-data, planer og SEO-dokumentation.

## Struktur
- `backup-2026-10-23/` — fuld WP REST-backup (19 sider, 9 guides, 5 kategorier, 86 medier-metadata, 27 template-parts, 17 templates, 45 plugins) taget 23/10 2026 lige før genopbygningen. Billeder + zip: Juno `/downloads/steamgears-backup-2026-10-23.zip`.
- `css/header-dark-clockwork-active.html` — live template part `extendable//header` (Dark Clockwork Theatre v3 CSS + GA4 e-commerce fallback-shim), hentet 24/10 2026.
- `docs/` — AdSense-setup, konkurrentnoter, SEO-handlingsplan 24/10 (PDF-audit gap-analyse), keyword- & konkurrent-scan, SEO-research-log, daily brief.

## Sikkerhed
- [`docs/library/sikkerhed-ai-engine-2026-11-01.md`](docs/library/sikkerhed-ai-engine-2026-11-01.md) — AI Engine 3.8.6 er under 3.8.7 og dermed omfattet af CVE-2026-110732 (uautentificeret administrator-overtagelse via MCP OAuth). Indeholder opdateringsplan og tjekliste. **Åben sag indtil sitet kører 3.8.7+.**
- [`docs/library/sikkerhed-plugin-versioner-2026-11-01.md`](docs/library/sikkerhed-plugin-versioner-2026-11-01.md) — versionsstatus for alle 48 plugins pr. 23/10 2026: 16 er bagud, 3 af dem har en sikkerhedsrettelse der mangler.

## Noter
- Repoet oprettes og opdateres via GitHub API (commit-per-batch); ingen lokal git-CLI i Juno-workspace.
- Fix-ressourcer: sitemap-toggles via Rank Math REST, GTM Kit via `/wp-json/gtmkit/v1/set-options`, WAF-429 trick via steamgears.juno.page browser-side.
