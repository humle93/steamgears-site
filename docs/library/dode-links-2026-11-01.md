# Døde interne links — 1/11 2026

Kortlægning af interne links på steamgears.eu, der peger på URL'er som returnerer 404. Supplerer [indholdsaudit-2026-11-01.md](indholdsaudit-2026-11-01.md) og punkt S0.4 i [site-roadmap-2026-11-01.md](../site-roadmap-2026-11-01.md).

## Metode

- Udgangspunkt: `sitemap_index.xml` → 6 sub-sitemaps, 103 URL'er (post, page, product, category, product_cat, author) plus `/`, `/journal/`, `/about/`, `/faq/`, `/shop/` og `/steampunk-costumes/`.
- Hver side blev hentet som renderet HTML, og alle `href` blev udtrukket, normaliseret (fragment og query fjernet) og begrænset til eget domæne. `wp-admin`, `wp-json`, `/feed` og medie-filtyper blev frasorteret.
- Alle 231 unikke interne mål blev efterfølgende hentet og deres HTTP-status noteret.
- 39 forekomster af døde links blev derefter matchet mod produktkataloget i **alle** statusser (publish, draft, private, trash) samt mod de 15 live produktkategorier.

## Resultat

| Måling | Tal |
|---|---|
| Sider gennemgået | 109 |
| Unikke interne link-mål | 231 |
| Mål der svarer 200 | 207 |
| Mål der svarer 404 | **24** |
| Link-forekomster der rammer 404 | **39** |
| Berørte sider | **15** |
| 301/302-kæder eller 5xx | 0 |

Ingen redirect-kæder, ingen serverfejl og ingen døde links i navigationen — alle 39 sidder i brødteksten i publicerede artikler. Det gør dem usynlige i menuer, men de rammer læseren præcis der, hvor et køb er tættest på.

## Årsager og anbefalet handling

### A. Kladde-produkter som artiklerne allerede linker til — 6 produkter, 11 links

Disse produkter findes, har priser og er linket fra live-artikler, men står som **kladde**. Publicering løser linkene med ét klik og gør samtidig varen salgbar.

| Produkt | ID | Links fra |
|---|---|---|
| victorian-underbust-corset | 28 | how-to-make-a-steampunk-costume, pirate-cosplay-for-beginners, steampunk-cosplay-on-a-budget, steampunk-outfit-ideas-for-women |
| brass-rivet-steampunk-goggles | 27 | pirate-cosplay-for-beginners, steampunk-cosplay-on-a-budget |
| airship-captain-steampunk-unisex-t-shirt | 75 | steampunk-halloween, steampunk-outfit-ideas-for-women |
| airship-blueprint-poster | 29 | steampunk-cosplay-on-a-budget |
| clockwork-octopus-steampunk-unisex-t-shirt | 183 | steampunk-halloween-costume-women |
| steampunk-goggle-diy-starter-kit | 765 | steampunk-halloween-costume-men |

### B. Skraldespands-produkter — 12 produkter, 17 links

Produkterne er slettet (status `trash`), men artiklerne linker stadig. Her er der ingen hurtig løsning: enten gendannes produktet, eller linket skal pege på et tilsvarende produkt eller på kategorien.

| Produkt | ID | Links |
|---|---|---|
| clockwork-skull-steampunk-unisex-t-shirt | 237 | 3 |
| alchemist-desk-steampunk-unisex-t-shirt | 233 | 2 |
| the-curious-automaton-steampunk-unisex-t-shirt | 95 | 2 |
| skeleton-key-steampunk-unisex-t-shirt | 213 | 2 |
| birdcage-phoenix-, clockwork-heart- (79), compass-mandala- (209), diving-helmet- (201), steam-locomotive- (227), sun-compass- (239), victorian-vampire-hunter- (99), whale-airship- (211) | — | 1 hver |

`steam-locomotive-steampunk-unisex-t-shirt` (227) er linket direkte fra **forsiden**.

### C. Produkter der aldrig har eksisteret — 2 links

| Dødt mål | Linket fra |
|---|---|
| /product/aviator-goggles-steampunk-retro-motorcycle-windproof/ | steampunk-halloween |
| /product/steampunk-leather-utility-belt-holster/ | steampunk-halloween-costume-men |

### D. Produktkategorier med forkerte slugs — 3 kategorier, 8 links

| Dødt mål | Findes som | Links |
|---|---|---|
| /product-category/goggles/ | /product-category/steampunk-goggles/ | 5 |
| /product-category/posters-wall-art/ | /product-category/posters-prints/ | 2 |
| /product-category/steampunk-top-hats/ | findes ikke — nærmeste er /product-category/accessories/ | 1 |

### E. Intern side med gammel slug — 1 link

| Dødt mål | Korrekt mål | Links fra |
|---|---|---|
| /refund-and-returns/ | /refund_returns/ | steampunk-gift-guide-2026 |

## Fordeling pr. side

| Side | Døde links |
|---|---|
| /steampunk-outfit-ideas-for-women/ | 9 |
| /steampunk-halloween-costume-men/ | 5 |
| /steampunk-halloween/ | 5 |
| /steampunk-halloween-costume-women/ | 4 |
| /steampunk-cosplay-on-a-budget/ | 3 |
| /3d-print-steampunk-parts/ | 2 |
| /how-to-build-a-steampunk-costume-for-under-100-complete-2027-workshop-plan/ | 2 |
| /pirate-cosplay-for-beginners/ | 2 |
| / (forside) | 1 |
| /diy-steampunk-goggles/ | 1 |
| /steampunk-costumes/ | 1 |
| /sustainable-steampunk/ | 1 |
| /how-to-make-a-steampunk-costume/ | 1 |
| /steampunk-costume-for-women/ | 1 |
| /steampunk-gift-guide-2026/ | 1 |

## Anbefalet rækkefølge

1. **Publicér de 6 kladder i gruppe A** — størst effekt pr. indsats (11 links væk, og 6 varer bliver salgbare).
2. **Ret de 3 kategorislugs og refund-linket i gruppe D og E** — rent tekstbyte, ingen forretningsmæssig beslutning (9 links).
3. **Tag stilling til gruppe B** — gendan eller omdirigér. `steam-locomotive` på forsiden haster mest.
4. **Ret de 2 links i gruppe C** til et beslægtet produkt eller fjern dem.
5. Kør linktjekket igen som fast månedlig rutine; det tager under et minut og fanger nye døde links efter produktoprydninger.
