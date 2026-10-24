# steamgears-site

Versionstyring for **steamgears.eu**-assets. WordPress-kernen og databasen ligger hos Simply — repoet holder design/CSS, backup-data, planer og SEO-dokumentation.

## Struktur
- `backup-2026-10-23/` — fuld WP REST-backup (19 sider, 9 guides, 5 kategorier, 86 medier-metadata, 27 template-parts, 17 templates, 45 plugins) taget 23/10 2026 lige før genopbygningen. Billeder + zip: Juno `/downloads/steamgears-backup-2026-10-23.zip`.
- `css/header-dark-clockwork-active.html` — live template part `extendable//header` (Dark Clockwork Theatre v3 CSS + GA4 e-commerce fallback-shim), hentet 24/10 2026.
- `docs/` — AdSense-setup, konkurrentnoter, SEO-handlingsplan 24/10 (PDF-audit gap-analyse), keyword- & konkurrent-scan, SEO-research-log, daily brief.

## Noter
- Repoet oprettes og opdateres via GitHub API (commit-per-batch); ingen lokal git-CLI i Juno-workspace.
- Fix-ressourcer: sitemap-toggles via Rank Math REST, GTM Kit via `/wp-json/gtmkit/v1/set-options`, WAF-429 trick via steamgears.juno.page browser-side.
