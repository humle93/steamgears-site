# Navigation, footer og meta — autonomt pas 2/11 2026

_Udført 11:35–13:05 CET mod live-sitet via MCP (OAuth, ingen nøgle i repo). Fire punkter lukket,
tre stadig blokeret. Alle tal nedenfor er målt efter ændringen, ikke antaget._

## 1. S2.3 — navigation: hub-links ind i headeren

Headeren var **ikke** `<!-- wp:page-list /-->` længere, som `navigation-spec-2026-11-02.md` antog. Den er en
mega-nav i template part **12** (50.477 tegn) med Shop-dropdown, Gallery, Guides, About og Contact — 13 links.

Det reelle hul var et andet: **`/start-here/` og `/build/` havde nul site-wide links**, selv om begge hubs har
været live siden runde 3–4. Det er præcis den link-equity, S2.3 skulle rette.

Indgrebet var to `wp:navigation-link`-blokke foran Shop-punktet:

```
<!-- wp:navigation-link {"label":"Start Here","type":"page","id":1183,…} /-->
<!-- wp:navigation-link {"label":"Build","type":"page","id":1185,…} /-->
```

Verifikation — headeren på `/`, `/shop/`, `/faq/` og `/journal/` indeholder nu begge links præcis én gang, og
rækkefølgen er `STEAMGEARS · Start Here · Build · Shop · Gallery · Guides · About · Contact`.

**Ikke gjort:** de fem-punkts struktur med Info-dropdown fra spec'ens §3. Den ville fjerne About/Contact fra
topniveauet, og det er en designbeslutning, ikke en teknisk mangel.

## 2. Footer: fortrydelseslinket ind

`/right-of-withdrawal/` stod **ikke** i footeren. Det er den digitale fortrydelsesfunktion fra 19/6 2026 og
skal være lettilgængelig på hver side — ikke gemt i en dropdown, jf. `navigation-spec` §5.

Legal-kolonnen i footer-part **52** har nu, ud over Privacy/Refund/Terms/Cookie: **Right of Withdrawal** og
**Cookie Settings** (til `/opt-out-preferences/`, som heller ikke var linket fra footeren). Verificeret på
forskellige sider: begge links findes nu i footeren på dem alle.

## 3. S0.7 — sitemap-kontrol: 121 af 121 svarer 200

Hele sitemap-indekset blev crawlet med 10 parallelle requests, én pr. URL:

| Sitemap | URL'er |
| --- | --- |
| post | 31 |
| page | 24 |
| product | 40 |
| product_cat | 20 |
| category | 5 |
| **i alt (indeks + sitemaps)** | **121** |

Alle 121 svarede `200`. Ingen 404, ingen 500, ingen redirect i sitemap. S0.7 er dermed lukket: Rank Maths
sitemap indeholder det publicerede sæt og peger ikke på døde URL'er.

## 4. S2.5 — meta-runde: 15 indekserbare huller lukket

En revision af alle 59 publicerede sider og indlæg fandt 18 uden meta description. Tre af dem er
`/cart/`, `/checkout/` og `/my-account/`, som er `noindex` — de skal ikke have en. De resterende **15** fik
title (≤ 62 tegn), description (130–165 tegn) og fokusnøgleord:

| ID | Side/indlæg | Titel |
| --- | --- | --- |
| 15 | 30+ Steampunk Costume Ideas | 62 tegn |
| 26 | Refund & Returns Policy | 36 |
| 32 | Steampunk Cosplay Gallery | 38 |
| 35 | About Steamgears | 41 |
| 36 | Contact | 51 |
| 37 | Privacy Policy | 27 |
| 41 | Workshop Journal | 45 |
| 550 | Steampunk Costumes (kommerciel hub) | 44 |
| 572 | Curiosities Vol. 3 | 48 |
| 580 | FAQ | 44 |
| 581 | Terms of Sale | 26 |
| 582 | Delivery & Shipping | 45 |
| 583 | Cookie Policy | 26 |
| 597 | Size Guide | 40 |
| 615 | Casual Steampunk Outfits | 52 |
| 1133 | Right of Withdrawal | 43 |
| 1183 | Start Here (titel var 65 tegn, nu 46) | 46 |

Efter skrivning blev alle 17 læst tilbage: **0 med tom titel, 0 med tom description, 0 over 62 tegn, alle med
fokusnøgleord.** W3TC purget bagefter.

## 5. Det, der stadig er blokeret

| Punkt | Blokering | Hvem |
| --- | --- | --- |
| **S0.4 + S2.2** — 301 for `/inspiration/`, `/shipping/`, `/returns/` | `redirection_create_redirect` svarer **«Invalid group»**: `wp_redirection_groups` er tom, og der findes intet MCP-værktøj der opretter en gruppe. Redirections egne options er ikke i MCP'ens allowlist, så gruppen kan ikke seedes ad den vej heller | Ejer: Tools → Redirection → **Add Group** (ét klik), derefter tager de tre redirects ét kald |
| **S1.1** — Herbarium-linjen (12 SKU'er) | Alle 12 er `simple` produkter med fulde tekster, kategorier, priser og SEO — men t-shirts mangler størrelser, telefoncovers mangler modeller, og art prints mangler størrelsesvalg (den publicerede plakatlinje sælger 30 × 40 og 50 × 70). At publicere nu ville sende kunden et produkt uden valgmulighed | Printful-variantkortlægning, derefter kan de sættes live |
| **S1.2** — Atelier-smykker (940–942) | Beskrivelserne starter med `[PLACEHOLDER — afventer leverandørvalg og egne produktfotos før publicering]` | Ejer: leverandør + fotos |
| **S0.5** — fjern redundant Health Check | Der findes intet plugin-aktiverings-/deaktiveringsværktøj i MCP-overfladen | Ejer: Plugins → deaktiver én af de to |
| **S0.2** — robots.txt med to `User-agent: *`-grupper | Begge grupper indeholder kun `Disallow`-linjer og modsiger ikke hinanden, så risikoen er kosmetisk. Ejerforholdet kan ikke afgøres herfra: kilden er enten host-mu-pluginnet eller en plugin, og MCP kan ikke læse filen | Ejer: afgør ejerskab, flet grupperne |

## 6. Sådan blev det udført

Alt gik gennem MCP med Bearer-token fra OAuth-flowet (24 timer, refresh-token). Hver skrivning returnerede
et undo-token med 72 timers levetid, og de originale versioner af begge template parts ligger desuden i
`artifacts/` (`part12-original.html`, `footer52-original.html`) — uden for repoet, som backup til rollback.

Rækkefølgen var: læs → byg ændringen lokalt → diff → skriv → purge → verificér mod live-HTML. Intet blev skrevet
uden at læse den nuværende værdi først.
