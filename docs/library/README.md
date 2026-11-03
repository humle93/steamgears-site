# Dokumentindeks — docs/library

Biblioteket rummer audits, research og driftslogger for **steamgears.eu**. Reglen i
[`AGENTS.md`](../../AGENTS.md) §5 er at nye dokumenter skal refereres fra `README.md` eller roadmap'en, så de
ikke bliver forældreløse — denne side er den indgang: alt i `docs/library/` er linket herfra, og repoets
hoveddokumenter står nederst.

**Tilføjer du et dokument, så skriv det ind i den tabel det hører til.** Kolonnen »Dato« er dokumentets
sidste commit-dato. Målt 2/11 2026.

PDF-eksporterne af dokumenterne ligger i [`reports/`](../../reports/) med samme navn som kilden; rækkerne
nedenfor peger på dem hvor der findes en. De er lavet med `tools/md_to_pdf.py` og er det der lægges i det
lukkedes research-bibliotek.

> Oprydning 2/11: SEO-dokumenterne er slået sammen til ét [`../seo.md`](../seo.md), roadmap'en til ét
> [`../roadmap.md`](../roadmap.md), og de gamle 24/10-planer er slettet. De kan hentes frem med
> `git show b7a0438:<sti>`.
>
> Oprydning 2/11 (aften): de udløbne dagsplaner, 24.–30/10-scanningerne og Velocity-bundlet er flyttet til
> [`arkiv/`](arkiv/README.md), så biblioteket kun rummer det der stadig gælder. Intet er slettet, og hvert
> arkiveret dokument har sin afløser noteret i [arkivets indeks](arkiv/README.md).

## Igangværende arbejde

| Dokument | Indhold | Dato |
| --- | --- | --- |
| [mcp-drafts-only-gaten-2026-11-03.md](mcp-drafts-only-gaten-2026-11-03.md) | **Drafts Only-gaten:** kildelæst i AI Engine 3.9.0 — hvilke 10+21 værktøjer der slipper igennem, hvorfor `wp_update_option` selv er spærret, hvorfor et gem kan se gennemført ud uden at være det, og de tre veje til 7.25.0 (PDF i `reports/mcp-drafts-only-gaten-2026-11-03.pdf`) | 2026-11-03 |
| [mcp-skriveadgang-2026-11-02.md](mcp-skriveadgang-2026-11-02.md) | **MCP-skriveadgang og 7.25.0:** hvorfor REST-ruten svarer 401, hvad Drafts Only blokerer, hvordan `mcp_options` aflæses uden at skrive, og 12/12 forhåndsvaliderede kampagneposter (PDF i `reports/mcp-skriveadgang-2026-11-02.pdf`) | 2026-11-02 |
| [clockwork-punk-kampagne-2026-11-02.md](clockwork-punk-kampagne-2026-11-02.md) | Clockwork Punk-kampagne 3.–14/11: koncept, 12 verificerede produkter, 3 vinkler, måling og blokeringer | 2026-11-02 |
| [clockwork-punk-koe-2026-11-02.json](clockwork-punk-koe-2026-11-02.json) | Kø-payload til `POST /sgap/v1/queue` — 12 poster med captions, links og tidspunkter | 2026-11-02 |
| [ai-engine-opdaterings-tjekliste-2026-11-03.md](ai-engine-opdaterings-tjekliste-2026-11-03.md) | Tjekliste før/efter en AI Engine-opdatering: seks mekanismer der kan flytte MCP-værktøjslisten, 7 anonyme og 6 login-krævende målinger med baseline, og en efterprøvet kommando der udleder de 74 indbyggede værktøjsnavne af kilden (PDF i `reports/ai-engine-opdaterings-tjekliste-2026-11-03.pdf`) | 2026-11-03 |
| [woo-opdaterings-tjekliste-2026-11-03.md](woo-opdaterings-tjekliste-2026-11-03.md) | Tjekliste før/efter en WooCommerce-opdatering: 13 målinger, målte baseline-værdier 3/11, acceptkriterier og rollback-grænse | 2026-11-03 |
| [woo-11-3-vaerktoejsliste-2026-11-02.md](woo-11-3-vaerktoejsliste-2026-11-02.md) | Driftsnote: ni udfasede Woo REST-spejle kan forsvinde fra MCP-listen ved næste Woo-opdatering — hvad der falder, og hvad vi gør | 2026-11-02 |
| [mcp-adapter-abilities-2026-11-02.md](mcp-adapter-abilities-2026-11-02.md) | MCP Adapter: 3 meta-værktøjer, 7 Woo-abilities, 9 udfasede — og hvorfor sider/indlæg kræver REST | 2026-11-02 |
| [mcp-adapter-abilities.csv](mcp-adapter-abilities.csv) | Regneark: 24 abilities og ruter med type, parametre, roadmap-kobling og nytte 1–5 | 2026-11-02 |
| [meta-fixes-og-produktlinks-2026-11-02.md](meta-fixes-og-produktlinks-2026-11-02.md) | 11 meta-rettelser med tælte længder, 2 H1-fix og produktlinkplan til de ni guides | 2026-11-02 |
| [roadmap-status-2026-11-02.md](roadmap-status-2026-11-02.md) | Crawl af 119 URL'er, WAF-fundet (454/455) og publish-klar copy til `/shipping/` + `/returns/` | 2026-11-02 |
| [egen-mcp-vaerktoejer-2026-11-02.md](egen-mcp-vaerktoejer-2026-11-02.md) | **Egne værktøjer:** fire veje til egne MCP-værktøjer, opskrift på en ability, de fire kandidater vi bør bygge først og hvad Drafts Only betyder for dem | 2026-11-02 |
| [egen-abilities-2026-11-02.csv](egen-abilities-2026-11-02.csv) | Regneark: de 32 abilities vores eget plugin registrerer, med readonly-flag, MCP-public og label — grundlaget for `mcp_rest.py --split` | 2026-11-02 |
| [mcp-vaerktoejer-2026-11-02.md](mcp-vaerktoejer-2026-11-02.md) | **MCP-inventar:** AI Engines 74 værktøjer (29 læsende/31 skrivende/14 admin) og mcp-adapterens 3 meta-værktøjer, med den målte rute-flade og hvad der først kan siges med credential | 2026-11-02 |
| [forside-2026-11-02.md](forside-2026-11-02.md) | Forsiden: to H1'er, S2.4-beslutningen og paste-klar hero | 2026-11-02 |
| [navigation-og-meta-2026-11-02.md](navigation-og-meta-2026-11-02.md) | Header-links, footer-fortrydelse, meta-runde og sitemap-kontrol — fire punkter lukket, fem blokeringer | 2026-11-02 |
| [navigation-spec-2026-11-02.md](navigation-spec-2026-11-02.md) | Navigation og menu — S2.3, verificeret mod REST 2/11 | 2026-11-02 |
| [plugin-opdateringer-triage-2026-11-02.md](plugin-opdateringer-triage-2026-11-02.md) | Triage af de 16 bagudværende plugins: 8 nu, 5 i vindue, 2 fjernes | 2026-11-02 |
| [butik-fund-2026-11-02.md](butik-fund-2026-11-02.md) | Butiksfund: tees uden størrelsesvalg | 2026-11-02 |
| [tee-varianter-spec-2026-11-02.md](tee-varianter-spec-2026-11-02.md) | Størrelsesvarianter på de ni tees — spec og blokering | 2026-11-02 |
| [valuta-og-priser-2026-11-02.md](valuta-og-priser-2026-11-02.md) | Valuta og priser — forslag til S4.3 | 2026-11-02 |
| [drive-watch-log.md](drive-watch-log.md) | Log over ændringer i Drive | 2026-11-02 |
| [niche-produkt-scanner-2026-11-02.md](niche-produkt-scanner-2026-11-02.md) | Niche-scanner: kilder, score og hvad Pinterest/TikTok ikke kan levere | 2026-11-02 |
| [niche-scanner-lavsaeson-2026-11-03.md](niche-scanner-lavsaeson-2026-11-03.md) | Niche-scanneren: lavsæson, censur af nuller og tre-dages-gaten — de tre §7-krav er kodet | 2026-11-03 |
| [niche-scan-ci-2026-11-03.md](niche-scan-ci-2026-11-03.md) | Natlig niche-scanning i CI: gaten der passer sig selv, de to gate-fejl og hvad et token kræver | 2026-11-03 |

## Butik og produkter

| Dokument | Indhold | Dato |
| --- | --- | --- |
| [butiksaudit-2026-11-01.md](butiksaudit-2026-11-01.md) | Butiksaudit — WooCommerce + Printify — PDF i `reports/butiksaudit-2026-11-01.pdf` | 2026-11-01 |
| [om-maerkning-dark-academia-2026-11-01.md](om-maerkning-dark-academia-2026-11-01.md) | Om-mærkning mod dark academia — de 39 designs — PDF i `reports/om-maerkning-dark-academia-2026-11-01.pdf` | 2026-11-01 |
| [produkttekster-2026-11-01.md](produkttekster-2026-11-01.md) | Produkttekster — 39 produkter — PDF i `reports/produkttekster-2026-11-01.pdf` | 2026-11-01 |
| [mockup-brief-2026-11-01.md](mockup-brief-2026-11-01.md) | Mockup-brief — 38 produkter — PDF i `reports/mockup-brief-2026-11-01.pdf` | 2026-11-01 |
| [niche-vurdering-2026-11-01.md](niche-vurdering-2026-11-01.md) | Niche-vurdering: fortsæt eller skift? — PDF i `reports/niche-vurdering-2026-11-01.pdf` | 2026-11-01 |
| [pinterest-board-plan.md](pinterest-board-plan.md) | Pinterest board-plan (godkendt 25/10) — PDF i `reports/pinterest-board-plan.pdf` | 2026-10-25 |

| [pirat-piger-prompts.md](pirat-piger-prompts.md) | Billedprompts til manglende motiver + Drive-navngivning | 2026-10-26 |

## Sikkerhed

| Dokument | Indhold | Dato |
| --- | --- | --- |
| [sikkerhed-noegler-historik-2026-11-02.md](sikkerhed-noegler-historik-2026-11-02.md) | Lækkede nøgler i git-historikken: rotations-tjekliste og plan for historik-rensning | 2026-11-02 |
| [sikkerhed-ai-engine-2026-11-01.md](sikkerhed-ai-engine-2026-11-01.md) | AI Engine CVE-2026-110732 (lukket 1/11) — PDF i `reports/sikkerhed-ai-engine-2026-11-01.pdf` | 2026-11-01 |
| [sikkerhed-plugin-versioner-2026-11-01.md](sikkerhed-plugin-versioner-2026-11-01.md) | Plugin-versioner — 48 plugins, 16 bagud — PDF i `reports/sikkerhed-plugin-versioner-2026-11-01.pdf` | 2026-11-01 |

## Vedligehold og driftslogs

| Dokument | Indhold | Dato |
| --- | --- | --- |
| [vedligehold-daglig-log.md](vedligehold-daglig-log.md) | Daglig driftslog (løbende) | 2026-11-01 |
| [vedligehold-ugentlig-log.md](vedligehold-ugentlig-log.md) | Ugentlig backup + 1 indholdselement | 2026-10-27 |
| [vedligehold-kvartalsvis-log.md](vedligehold-kvartalsvis-log.md) | Kvartalsvis fuld crawl + top-20 | 2026-10-27 |

## Research og analyser

| Dokument | Indhold | Dato |
| --- | --- | --- |
| [produkt-schema-fix-2026-11-02.md](produkt-schema-fix-2026-11-02.md) | Produkt-schema: brand, 14 dages returret og leveringstid — kode klar, ikke live (D2/D5) | 2026-11-02 |
| [waf-diagnose-2026-11-02.md](waf-diagnose-2026-11-02.md) | WAF-diagnose: 455 rammer spoofede bot-UA'er, ikke Google — retter A1/S0.6; PDF i `reports/waf-diagnose-2026-11-02.pdf` | 2026-11-02 |
| [indholdsaudit-2026-11-01.md](indholdsaudit-2026-11-01.md) | Indholdsudit — posts, sider og produkter — PDF i `reports/indholdsaudit-2026-11-01.pdf` | 2026-11-01 |
| [dode-links-2026-11-01.md](dode-links-2026-11-01.md) | Døde interne links — 39 fund — PDF i `reports/dode-links-2026-11-01.pdf` | 2026-11-01 |
| [mangler-og-plan-25-10.md](mangler-og-plan-25-10.md) | Komplet mangel-liste og udførelsesplan (25/10) — stadig citeret som evidens i roadmap'en — PDF i `reports/mangler-og-plan-25-10.pdf` | 2026-10-26 |
| [niche-scan-procedure-2026-11-02.md](niche-scan-procedure-2026-11-02.md) | Procedure for niche-scanning: 7 trin, tærskler, fem fælder og hvad kilderne ikke måler | 2026-11-02 |
| [backlinks-backlink-log.md](backlinks-backlink-log.md) | Backlink Monitor — ugentlig log | 2026-10-27 |
| [steampunk-outfit-ideas-for-women.md](steampunk-outfit-ideas-for-women.md) | Artikeludkast: steampunk outfit ideas for women — PDF i `reports/steampunk-outfit-ideas-for-women.pdf` | 2026-10-25 |

## Kilder

Høstede Google-kilder i [`knowledge_base/`](../../knowledge_base/) (indlæses af `seo_agent.py` sammen med
[`../seo.md`](../seo.md)):

- [SEO Starter Guide](../../knowledge_base/harvested_search-docs-fundamentals-seo-starter-guide.md)
- [Product structured data](../../knowledge_base/harvested_search-docs-appearance-structured-data-product.md)
- [Ecommerce SEO overview](../../knowledge_base/harvested_search-docs-specialty-ecommerce-overview.md)
- [Ecommerce URL structure](../../knowledge_base/harvested_search-docs-specialty-ecommerce-designing-a-url-structure-fo.md)
- [Voice search study (10k søgninger)](../../knowledge_base/harvested_voice-search-seo-study.md)

## Daglige målinger

- Daglige briefs: [`../daily-briefs/`](../daily-briefs/) — [24/10](../daily-briefs/2026-10-24.md) ·
  [25/10](../daily-briefs/2026-10-25.md) · [26/10](../daily-briefs/2026-10-26.md) ·
  [31/10](../daily-briefs/2026-10-31.md) · [1/11](../daily-briefs/2026-11-01.md) ·
  [2/11](../daily-briefs/2026-11-02.md)
- Site check + SEO: [1/11](../site-checks/2026-11-01.md)
- Procentstatus: [`../projektstatus.md`](../projektstatus.md) (beregnes af `projektstatus.py`)

## Repoets hoveddokumenter

- [`../../README.md`](../../README.md) — repoets indgang og mappestruktur
- [`../roadmap.md`](../roadmap.md) — **den eneste** optimeringsplan for sitet (S0–S4)
- [`../seo.md`](../seo.md) — **det eneste** SEO-dokument: regler, GEO-skabelon, måling
- [`../../plugins/steamgears-autopilot/ROADMAP.md`](../../plugins/steamgears-autopilot/ROADMAP.md) — plugin-produktets egen roadmap
- [`../mcp-auth.md`](../mcp-auth.md) — auth for MCP-serverne i `.mcp.json` (Royal Basic/OAuth, Publer-nøgler, Simply OAuth)
- [`../adsense-setup.md`](../adsense-setup.md) — AdSense-setup
- [`../competitors.md`](../competitors.md) — Competitor Watch
- [`../command-center-kilde.md`](../command-center-kilde.md) — hvor Command Center-kilden ligger
- [`command-center/`](command-center/) — Command Centers egen dokumentation hentet 2/11 2026 (roadmap, arkitektur/sikkerhed, bruger- og systemmanual) fra `humle93/command-center` @ `ac7dd53`
- [`command-center-opdatering-2026-11-02.md`](command-center-opdatering-2026-11-02.md) — opdatering af CC på Ultra.cc til 7.14 med bruger-login (tjek, backup, tre opdateringsveje, fejlsøgning)

## Arkiv

- [`arkiv/`](arkiv/README.md) — afløst materiale: udløbne dagsplaner, 24.–30/10-scanninger og Velocity-bundlet.
  Intet er slettet; hvert dokument har sin afløser noteret i arkivets indeks. Arkiveret materiale tæller
  **ikke** som status.
