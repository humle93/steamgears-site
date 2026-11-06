# Command Center — Roadmap (Henning godkendt 27/10 2026)

Prioritet: **1) undgå datatab/nedetid → 2) pålidelig automation → 3) SEO-funktioner.**
Kilde: Henning's prioriterede liste (pasted i chat 27/10). Status-kolonne opdateres af Juno.

| # | Forbedring | Status 27/10 | Fase |
|---|---|---|---|
| 1 | Persistent jobkø + låsning | ❌ mangler — cron kan dobbeltkøre | Fase 1 |
| 2 | Off-site krypteret backup + restore-test | ❌ mangler (kun lokal) | Fase 1 |
| 3 | Health checks + auto-restart | 🟡 delvist: /health + diagnose.sh; mangler systemd Restart + ekstern uptime | Fase 1 |
| 4 | Disk/RAM/log-overvågning | ❌ mangler | Fase 1 |
| 5 | Sikker self-update med rollback | 🟡 delvist: update-restart.sh, men ingen backup-før-update/rollback/smoke-test | Fase 1 |
| 6 | Nøgle-health + budgetgrænser | ❌ mangler | Fase 2 |
| 7 | WordPress health-bro | 🟡 delvist: Woo-tjek i optimeringsguide + auto-reparationer; mangler plugin/cron/PHP-fejl/checkout-status | Fase 3 |
| 8 | Artikel-kvalitetsgate | ❌ mangler | Fase 3 |
| 9 | Audit-log + ændringskø | 🟡 delvist: task_runs + repairs-resultat; mangler fuld audit-log | Fase 4 |
| 10 | Tre autonomi-niveauer | ❌ mangler (i dag: alt kræver godkendelse undtagen scan/rapport) | Fase 4 |
| 11 | Alarm-runbooks + weekly report | ❌ mangler (daglig Telegram-rapport findes) | Fase 4 |
| 12 | Internal linking engine | 🟡 delvist: /seo/linksuggest (manuell) — mangler auto i artikelkladder | Fase 5 |
| 13 | Product SEO monitor | 🟡 delvist: repair_scan finder billede/lager/meta — mangler titel/pris/beskrivelse/schema-tjek | Fase 5 |
| 14 | Conversion attribution | ❌ mangler | Fase 5 |
| 15 | Multi-site support | 🟡 delvist: tenancy findes i v8; mangler per-site domæner/nøgler/budgetter | Senere |

## Fase 1 — v8.4 (næste build)
1. **Persistent jobkø**: `jobs`-tabel (queued/running/success/failed/timeout), ét aktivt job pr. type, timeout + max 2-3 retries, UI-side med køen.
2. **Off-site backup**: krypteret upload (Backblaze B2/S3/GDrive) af SQLite + .env + rapporter; 7 daglige/4 ugentlige/3 månedlige; ugentlig restore-test; kritisk alarm hvis backup mangler; self-update blokeres uden grøn backup.
3. **Health + auto-restart**: systemd Restart=always, /health udvides (DB-skrivning, disk, scheduler), UptimeRobot eksternt, alarm først efter gentagne fejl.
4. **Resource-overvågning**: disk 80%/90% alarm, RAM/CPU, logrotation, vacuum efter store crawls.

## Fase 2 — v8.5
5. Sikker self-update: backup → download til tmp → smoke-test → swap → rollback ved fejl + lock.
6. Integrations-side: nøgle-health (sidste OK-kald, fejlrate, kvote, månedligt forbrug), autopilot-pause ved 401/403/429, budget pr. API + hard stop.

## Fase 3 — v8.6
7. WP health-bro: versioner, opdateringer, fejlde WP-cron, PHP fatal errors, checkout-status, produkter uden billede/pris/lager/beskrivelse, sidste WP-backup. Ingen auto-plugin-opdateringer — kun "safe update"-liste.
8. Artikel-kvalitetsgate før WP-kladde: fokuskeyword i titel/H1/URL/intro/meta, min. ordtal vs SERP, ≥3 interne links, ≥1 ekstern kilde, duplikattjek, FAQ/schema/billedplan, CTA, bloker ukildede sundheds-/juridiske påstande, menneskelig godkendelse.

## Fase 4 — v8.7
9. Audit-log (tid/handling/mål/resultat/årsag/rollback-mulig).
10. Autonomi-niveauer: Safe mode / Auto-fix / Full autopilot (se tabel i original).
11. Alarm-runbooks + ugentlig rapport (status, fejl, keywords, artikler, API-forbrug, beslutninger der venter).

## Fase 5 — v8.8+
12. Internal linking engine i artikelkladden (3-5 links auto).
13. Product SEO monitor udvidet (SEO-titel/meta, pris, beskrivelseslængde, FAQ/schema).
14. Conversion attribution (GSC × WooCommerce-ordrer).
15. Multi-site (først ved >1 domæne).

## Allerede bygget (før roadmap)
- v8.2: Auto-reparationer (scan → batch-godkendelse → auto-fix → verificering → Telegram), cron 05:45.
- v8.3 (i deploy 27/10): strategi-skift-fix, keyword-høst fra konkurrenter, AI batch billedgenerator, moderne lys UI-skin (Inter/blå).
