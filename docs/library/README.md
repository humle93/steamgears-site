# Dokumentindeks — docs/library

Biblioteket rummer audits, research og driftslogger for **steamgears.eu**. Reglen i
[`AGENTS.md`](../../AGENTS.md) §5 er at nye dokumenter skal refereres fra `README.md` eller roadmap'en, så de
ikke bliver forældreløse — denne side er den primære indgang til biblioteket.

**Tilføjer du et dokument, så skriv det ind i den relevante tabel.** Daterede audits har deres dokumentdato i filnavnet; målinger er ikke live-status, medmindre det står udtrykkeligt.

PDF-eksporter findes i [`reports/`](../../reports/) når de er genereret. Markdown-kilden er autoritativ.

## Igangværende arbejde

| Dokument | Indhold | Dato |
| --- | --- | --- |
| [sitewide-audit-2026-11-03.md](sitewide-audit-2026-11-03.md) | Sitewide audit: risici, anbefalede WooCommerce-funktioner, målepunkter og adgangsbehov | 2026-11-03 |
| [deploy-roadmap-2026-11-03.md](deploy-roadmap-2026-11-03.md) | Faseopdelt deployment-plan, estimater, staging-/rollback-porte og live-verifikation | 2026-11-03 |
| [mcp-drafts-only-gaten-2026-11-03.md](mcp-drafts-only-gaten-2026-11-03.md) | **Drafts Only-gaten:** kildelæst i AI Engine 3.9.0 — hvilke 10+21 værktøjer der slipper igennem, hvorfor `wp_update_option` også blokeres — PDF i `reports/mcp-drafts-only-gaten-2026-11-03.pdf` | 2026-11-03 |
| [mcp-skriveadgang-2026-11-02.md](mcp-skriveadgang-2026-11-02.md) | MCP-skriveadgang og pluginopdatering: adgangsbegrænsninger og verifikation | 2026-11-02 |
| [roadmap-status-2026-11-02.md](roadmap-status-2026-11-02.md) | Crawl-status, WAF-forbehold og klargjort indhold til politik-sider | 2026-11-02 |
| [seo-audit-2026-11-02.md](seo-audit-2026-11-02.md) | SEO-audit med scorecard, fund, evidens og estimater | 2026-11-02 |
| [butiksaudit-2026-11-01.md](butiksaudit-2026-11-01.md) | WooCommerce/Printify-butiksaudit, checkout, fragt og compliance-fund | 2026-11-01 |

## Øvrige audits, research og specifikationer

De eksisterende dokumenter i biblioteket forbliver tilgængelige i deres respektive emnegrupper nedenfor og i repoets historik. Vedligehold indeksrækkerne, når nye dokumenter tilføjes.

### Butik og produkter

- [om-maerkning-dark-academia-2026-11-01.md](om-maerkning-dark-academia-2026-11-01.md)
- [produkttekster-2026-11-01.md](produkttekster-2026-11-01.md)
- [mockup-brief-2026-11-01.md](mockup-brief-2026-11-01.md)
- [valuta-og-priser-2026-11-02.md](valuta-og-priser-2026-11-02.md)
- [tee-varianter-spec-2026-11-02.md](tee-varianter-spec-2026-11-02.md)

### Drift, sikkerhed og måling

- [vedligehold-daglig-log.md](vedligehold-daglig-log.md)
- [vedligehold-ugentlig-log.md](vedligehold-ugentlig-log.md)
- [vedligehold-kvartalsvis-log.md](vedligehold-kvartalsvis-log.md)
- [sikkerhed-noegler-historik-2026-11-02.md](sikkerhed-noegler-historik-2026-11-02.md)
- [waf-diagnose-2026-11-02.md](waf-diagnose-2026-11-02.md)

## Repoets hoveddokumenter

- [`../../README.md`](../../README.md) — repoets indgang
- [`../roadmap.md`](../roadmap.md) — den samlede site-optimeringsplan
- [`../seo.md`](../seo.md) — SEO-regler, GEO-skabelon og måling
- [`../agent-profil.md`](../agent-profil.md) — verificerede fakta, adgang og faldgruber
- [`../../plugins/steamgears-autopilot/ROADMAP.md`](../../plugins/steamgears-autopilot/ROADMAP.md) — plugin-roadmap
