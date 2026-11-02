# Deep-dive: my-steampunk-style.com — "steampunk wall art" (#1) & corset-priser

**Dato:** 24/10 2026 · **Metode:** live SERP (web_search gl=us), Shopify-sitemaps + products.json, RDAP, Moz Domain Analysis (browser-rendered) · Alle tal verificerbare via kilder i dokumentet.

---

## 1. Hvad ranker de på lige nu (verificeret live 24/10)

| Query | Deres plads | Side | Noter |
|---|---|---|---|
| steampunk wall art | **#1** | /collections/steampunk-wall-art | kategoriside; URL bærer `srsltid=` → aktiv Google Merchant feed (free listings) |
| steampunk wall decor | **#1** | /collections/steampunk-decor | samme kategori-træ |
| steampunk decor | **#1** | /collections/steampunk-decor | slår iCanvas/elephantstock |
| steampunk corset | **#1** | /collections/steampunk-corset | + deres blog-post `steampunk-corsets` i top-8 — double placement |
| steampunk accessories | #1 (3 scans i træk) | /collections/steampunk-accessories | kendt fra watchlist |
| steampunk pirate | top-5 | /blogs/steampunk-blog/steampunk-pirate | indholdsside, ikke kategori |
| steampunk poster | **IKKE i top-8** | — | her ejer Pinterest (steamngears-boardet) + marketplaces |

**Konklusion:** Deres mønster er konsekvent — **kategorisider rangerer, produkter og blog understøtter**. De har tagget deres kategorisider med Merchant Center-feed (free listings giver ekstra SERP-real estate med `srsltid`-URL'er).

## 2. Hvad ligger der bag (indhold & struktur)

Fakta fra deres offentlige sitemap + Shopify-endpoints:

| Metric | my-steampunk-style.com | Steamgears.eu |
|---|---|---|
| Domæne alder | registreret **13/10 2021** (5 år) | 2026 (ny) |
| Platform | Shopify (theme: Impulse) | WordPress/Woo |
| Produkter (sitemap) | **1.293** (1001 + 292) | 78 |
| Kategorier/collections | **44** | ~10 |
| Blog-artikler | **87** (fra 6/2022, seneste 19/10/2026 ≈ 2/md) | 9 (alle okt 2026) |
| Moz Domain Authority | **25** | **1** |
| Moz Linking Root Domains | **1.800** | **0** |
| Moz Ranking Keywords | **2.900** | 0 (Moz-indeks) |
| Spam Score | 2% | — |
| Trustpilot | **2.6/5 (6 reviews, unclaimed)** | ingen profil |

Top-linking-domains (Moz): en.wikipedia.org (DA 98), pinterest.com, fandom.com, bing.com, notion.site, uoregon.edu — dvs. deres linkprofil er bred men ikke aggressiv opbygget: det er 4-5 års alder + Pinterest/ecommerce-fotrprint, ikke PBN-styrke. **DA 25 er slåeligt for et niche-site; det er ikke et DA 60-monster.**

### Deres wall art-kategoriside (den side der tog #1)
- URL: /collections/steampunk-wall-art — title "Steampunk Wall Art | My Steampunk Style", meta med CTA + "free shipping"
- **725 ord kategoribeskrivelse** med interne H2'er ("Steampunk wall art: the ultimate home decoration", "Steampunk drawings: unveiling the mechanical imagination")
- **64 produkter**, priser ved salg **$10.90–$30.90** (anatomi-tegninger billigst, canva/poster mellem $12.90–30.90), reviews på flere produkter (social proof)
- rel="next" paginering + atom-feed — teknisk ordentlig Shopify-SEO

### Corset-price-watch (verificeret via deres products.json)
Deres billigste corsets: **Golden Leaves $30.90**, **Brown Overbust $33.90**, Tie-up $33.90, Burlesque/Morticia/Night Fever/denim $35.90. 113 corsets i samlingen.
Vores (WC Store API, kategori Corsets & Clothing): Victorian Corset Top **$44.95**, Overbust Burlesque **$59.95**, Gothic PU Overbust **$64.95**.
→ De er $10–30 billigere på indgangsprisen. **Vi vinder ikke på pris på corsets — det skal vi ikke prøve**; vores spil er posters + pirat-nichen, hvor de er svage.

### ⚠️ Vigtigste fund: deres pirate-position er tynd
- /collections/steampunk-pirate: **46 produkter, men NUL kategoribeskrivelse, ingen meta description, title tag er kun "Steampunk pirate"** (uden brand-suffix). Siden er en rå produktliste — ingen SEO-tekst, ingen intern linking-tekst.
- Deres blog-artikel "Steampunk pirates: where do they come from?" er ægte indhold og ranker top-5 på "steampunk pirate" — men den peger kun på deres tynde kategoriside.
- **"steampunk pirate poster" er stadig helt uoptaget** (top-8: displate ×2, pixers, pinterest ×3, etsy, shutterstock — ingen dedikeret samling, nogen steder).

## 3. Plan: hvad vi bygger for at tage pladsen

Realitetstjek først: med DA 1 vs. DA 25 vinder vi **ikke** head-termet "steampunk wall art" på autoritet lige nu. Vores vej ind er (a) det ledige pirat-hul, (b) friskhed + relevans, (c) Pinterest-slots (der holder 3-5 pladser i alle poster-SERPs og vi har kontoen), (d) format — kategorisider vinder dette vertikal.

### Fase 1 — Import (blokerende, først)
1. Importér Pirat Piger-posters fra 75-design CSV'en til **/product-category/posters-prints/** (kategori id 28, live men 0 produkter). ~10-20 stykker min. — en kategori med 3 produkter rangerer ikke.
2. Prisstrategi: deres wall art indgang er $10.90-14.90 ved salg. Vores print-to-order skal lande **under $19.95 (30×40)** for at kunne stå i en pris-sammenligning, ellers taber vi pris-signalet helt.

### Fase 2 — Kategorisider (det der rangerer)
3. Parent-kategori "Posters & Wall Art" med **600-800 ord SEO-tekst** (match deres 725-ords format): H2'er på "Steampunk Wall Art", "Steampunk Posters", "Steampunk Pirate Posters", FAQ-blok (PAA-formatet, som steampunkary beviser virker).
4. **Sub-collection "Steampunk Pirate Posters"** på /product-category/steampunk-pirate-posters/ — det ledige query. Skriv 300+ ord + internal links fra pirat-guides. Deres pirate-kategori er tom af tekst; vi laver den rigtige version af det, de forsømte.
5. 301/korsslt: tilføj produkterne til Google Merchant Center via WooCommerce Google Feed plugin → `srsltid`-slots i Shopping (det er en synlig del af deres #1-position).

### Fase 3 — Indhold der peger på kategorierne
6. Guide: **"Steampunk Wall Art: 12 Ideas for Mechanical Walls"** (listicle, sg-shoprow + sg-buy ind i kategorien). Ingen content-site rangerer på dette query endnu — kun marketplaces + Pinterest.
7. Guide: **"Steampunk Pirate: Outfit, Decor & Posters"** — svar på deres blog-artikel, men med kategori-CTA. Cross-link begge veje med eksisterende guides (pirat-angle fra "pirat piger"-backloggen).
8. Pinterest: board **"Steampunk Posters & Wall Art"** på cpaleadsis-kontoen med friske pins af EGENI produkter (target = den sovende steamngears-#1 og de 3-5 Pinterest-slots i hver poster-SERP). Pin Pack #2 Pirat Piger (Buffer-kø 28/10) retargetes til kategorien efter import.

### Fase 4 — Pressefordelen (deres svagheder)
- **Trustpilot 2.6/5** på 6 reviews: social proof er deres blinde punkt. Vores reviews/ugc på produktsider er en reel differentiator over tid.
- **2 artikler/md og 0 kategoritekst på nye kategorier**: de vokser langsomt. Med 2-3 guides/uge + kategoritekst kan vi overhale relevans-mæssigt på long-tail inden for 60-90 dage; head-termet tager længere (link-opbygning via Pinterest + eventuel gæste-/listicle-omtale).

### Realistisk 90-dages mål
- Ejer "steampunk pirate poster" (ingen konkurrent i dag) og "steampunk pirate" top-5.
- Top-8 på "steampunk poster" via Pinterest-board + kategori.
- "steampunk wall art": top-10 realistisk; #1 kræver links — re-vurder efter Q1 med månedlig Moz/GSC-måling.

## 4. Kilder (tjek selv)
- Live SERP: web_search "steampunk wall art" / "steampunk corset" / "steampunk decor" / "steampunk pirate" (gl=us, 24/10)
- Sitemaps: my-steampunk-style.com/sitemap.xml (products 1001+292, collections 44, articles 87)
- Produkter/priser: /collections/{steampunk-wall-art,steampunk-corset,steampunk-pirate}/products.json
- Domæne: rdap.org/domain/my-steampunk-style.com (registreret 2021-10-13)
- Autoritet: moz.com/domain-analysis (my-steampunk-style.com: DA 25 / 1.8k / 2.9k kw; steamgears.eu: DA 1 / 0 / 0)
- Trustpilot: trustpilot.com/review/my-steampunk-style.com (2.6, 6 reviews, unclaimed)
