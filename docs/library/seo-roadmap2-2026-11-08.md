# Det andet roadmap (docs/seo.md §8) — målt punkt for punkt, 8/11

Roadmappet ved siden af site-roadmappet er [`docs/seo.md`](../seo.md). Dets åbne punkter står i **§8 Kendte,
ikke-løste punkter**. Her er hvert punkt målt mod det live site før indgreb, og derefter målt igen.

| # | Punkt | Før | Indgreb | Efter |
| --- | --- | --- | --- | --- |
| 1 | To H1 | `/` **1** (allerede rettet), `/free-starter-kit/` **2**, `/steampunk-halloween/` **2** | Indholdets egen H1 (blok-niveau 1) gjort til H2 på 801 og 713 | **1 / 1 / 1**, alle 200 |
| 2 | To `User-agent: *`-grupper i robots.txt | **3 grupper** | mu-plugin `sgap-robots-consolidation.php` (1 282 bytes, `php -l` ren) fletter grupperne via `robots_txt`-filter | **1 gruppe**, 14 `Disallow` + 1 `Allow` + 1 `Sitemap` bevaret, 200 |
| 3 | Navigation er `wp:page-list` | `page-list` **0** forekomster, nav viser *Start Here · Build · Shop All Products · Curiosities · Airship · Herbarium · Atelier · Gallery · Guides · About · Contact* — ingen kurv/kasse | intet (spec var allerede anvendt) | **lukket som verificeret opfyldt** |
| 4 | Performance | produktside **267 KB** HTML, **34,8 KB** inline CSS, **88** script-tags (var 458 KB / 151 KB / 105) | intet i denne tur — W3TC-indstillingerne kan stadig ikke sættes over MCP | **står åbent**: kræver admin-adgang eller beslutning om caching-lag |
| 5 | GPSR mangler postadresse | 39 produkter uden adresse | intet | **står åbent**: afhænger af CVR/adresse (S4.2, ejerbeslutning) |
| 6 | Printful kan overskrive titler | `get_plugins()`: **ingen** POD-connector (hverken Printful eller Printify); `printify_settings` **tom** | intet | **lukket som verificeret**: der er ingen sync der kan overskrive |
| 7 | `/steampunk-costumes/` ikke indekseret | siden svarer **200**, `<meta name="robots">` = `follow, index`, og den **står i `page-sitemap.xml`** | intet | **ingen fejl på sitet** — opfølgning hører i GSC, ikke i koden |

## Hvad der stadig kræver en beslutning

- **Punkt 4 (performance):** caching/minify/lazyload kan ikke sættes over MCP, og det er et valg om hvilket
  lag der skal bære det (W3TC vs. Cloudflare vs. mu-plugin). Målt tal: 267 KB, 34,8 KB inline CSS, 88 scripts.
- **Punkt 5 (GPSR):** postadresse kræver CVR/adresse — samme ejerbeslutning som S4.2.
- **Guide-kladderne i `docs/seo.md`s søsterprojekt** (SEO-indholdsproduktion): fire guides er skrevet færdige
  og ligger som kladder — **1410** (11 403 tegn), **1361** (11 454), **1359** (11 237), **1357** — og skal
  publiceres af ejeren. Publicering er et redaktionelt valg, ikke en teknikfejl, så de er ikke rørt.
  Et tidligere modulnavn pegede på id 292/296/295; de id'er er **produktvarianter** (t-shirt L/M/S), ikke guides,
  og de ligger i papirkurven efter oprydningen 8/11.
