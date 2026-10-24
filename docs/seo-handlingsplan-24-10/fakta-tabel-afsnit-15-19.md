# Fakta-tabel: Afsnit 15–19 cross-check (verificeret live 24/10 2026)

Kilde: "SEO-status for steamgears.eu.pdf" (Drive, fil-ID 111MpV2q4i3PIoVI0R3m_CsNQ5aNPxi3W), afsnit 15–19.
Alle "faktisk"-kolonner verificeret via WooCommerce/WordPress REST + GSC API + HTTP-headers pr. 24/10.

## Afsnit 15 — Analytics og Search Console

| Planen foreslår | Faktisk tilstand (verificeret) |
|---|---|
| Opsæt GA4 | ✅ Live: G-3MYLFKY8MY via GTM/gtag på alle sider (manuelt injiceret, ingen GA-plugin) |
| Aktivér e-commerce tracking (view_item/add_to_cart/begin_checkout/purchase) | ❌ Ikke konfigureret/verificeret. Ingen GA-integrationsplugin aktiv; events kræver GTM-tags + en testordre i GA4 DebugView |
| Opsæt GSC | ✅ Property verificeret (URL-prefix) |
| Indsend XML-sitemap | ⚠️ sitemap_index.xml indsendt 23/10 23:09, processed, 2 URLs, 0 indexeret endnu. Gammel /sitemap.xml (18/10) stadig pending |
| Overvåg klik/CTR/placering | ✅ Ugentlig dashboard-rutine fra 26/10 (Keyword & SERP Coverage app) |
| "XML-sitemap indeholder aktive sider, kategorier og produkter" | ❌ **KRITISK: sitemap_index.xml indeholder KUN author-sitemap.xml (2 entries).** post-, page- og product-sitemaps returnerer 404. Rank Math-sitemap-modulet genererer kun author efter genopbygningen |
| robots.txt | ✅ OK — blokerer kun WooCommerce-logs/uploads + add-to-cart-params, sitemap-erklæring til stede |
| Noindex cart/checkout/account | ✅ cart + my-account: `noindex, follow`. Checkout redirecter til /cart/ ved tom kurv → ikke direkte verificerbar; gencheck ved fyldt kurv |
| Schema (Product) | ✅ Product + Offer JSON-LD på produktsider (Rank Math); ingen aggregateRating (der er 0 anmeldelser — korrekt ift. planen) |

## Afsnit 16 — Indholdsplan (12 artikler)

| Planens artikel | Faktisk tilstand |
|---|---|
| 1. How to Build a Steampunk Costume from Scratch | ✅ Dækket: "How to Make a Steampunk Costume" (16/10) |
| 2. Best Steampunk Costume Ideas for Beginners | 🟡 Delvist dækket af "Steampunk Costume Ideas" + "Cosplay for Beginners" (samlet keyword "for beginners" ikke særskilt) |
| 3. Steampunk Outfit Ideas for Men | ❌ Mangler (nuværende idé-artikel dækker begge køn i én) |
| 4. Steampunk Outfit Ideas for Women | ❌ Mangler |
| 5. Best Steampunk Accessories for Cosplay | ✅ Dækket: "Steampunk Accessories: 25+ Must-Have Pieces" (16/10) |
| 6. How to Style Steampunk Goggles | ❌ Mangler (vi har DIY + DIY-vs-Buy, ingen styling-vinkel) |
| 7. Steampunk Costume Ideas on a Budget | ✅ Dækket: "Steampunk Cosplay on a Budget" (16/10) |
| 8. What to Wear to a Steampunk Convention | ❌ Mangler |
| 9. Best Steampunk Gifts for Cosplayers | ❌ Mangler (og kategorien steampunk-gifts findes ikke) |
| 10. Steampunk Costume Ideas for Kids | ❌ Mangler |
| 11. Steampunk Costume Ideas for Couples | ❌ Mangler |
| 12. Steampunk Workshop Decor Ideas | ❌ Mangler (workshop-decor-kategori findes ikke) |
| Pillar page /steampunk-costumes/ (krav fra 19) | ✅ Findes allerede live |
| Artikel-krav (2+ billeder, CTA, sg-buy links) | ✅ Standard i vores guide-skabelon; meta/opdateringsdato styres via Rank Math |

Score: 4/12 dækket (1 helt, 2 delvist), 8 mangler — matcher vores 2–3 guides/uge-velocity: ~3 ugers guide-arbejde.

## Afsnit 17 — 8-ugers tidsplan

| Planens uge | Faktisk tilstand |
|---|---|
| Uge 1: backup, eksport, dubletter, kategorier, CSS | ✅ Backup 23/10 (REST + zip). Dubletter: ingen ægte (23 "samme navne" = Vol 1/2/3-variationer). Produkt-CSV: ⚠️ JSON-backup ok, formel Woo-CSV-eksport ikke kørt |
| Uge 2: Uncategorized, 4 kategorier, flyt produkter | ✅ Uncategorized: 0 produkter. T-Shirts (54) + Hoodies (12) findes. ❌ Artist Series + Steampunk Gifts findes IKKE. 81 produkter fordelt på 6 kategorier |
| Uge 3: top-10 produkttekster, meta, materialer | ❌ Ikke påbegyndt. Sample: desc 361–4029 tegn, short desc 74–153 tegn, SKU ok (SG-V3-XX) |
| Uge 4: billeder, alt, relaterede, FAQ, schema | ⚠️ Alt-tekster ✅ men kun **1 billede pr. produkt** (plan: min. 3). Produkt-FAQ ❌. Relaterede produkter uafklaret. Schema ✅ |
| Uge 5: Dark Clockwork Theatre CSS | ❌ Bevidst ikke installeret — Workshop skin v3 kører (header-4/5). Designkonflikt, beslutning uafklaret |
| Uge 6: footer, supportsider, trust | 🟡 Pages findes: Delivery, FAQ, Terms, Refund, Contact, About, **Size Guide (oprettet efter planen!)**, Cookie Policy. ❌ Order Tracking, Size Guide ikke i footer, trust-badges mangler |
| Uge 7: GA4, GSC, sitemap, robots, noindex | ✅ GA4 + GSC + robots + noindex. ❌ **Sitemap broken (kun author)** — denne uge kan ikke "lukkes" før fix |
| Uge 8: 2 nye guides + måling | ❌ Ikke påbegyndt (8 guides mangler fra sekt. 16); målerutine starter 26/10 |

## Afsnit 18 — Afsluttende tjekliste (statuslinje)

- **Backup:** ✅ filer + DB + CSS gemt; ⚠️ produkt-CSV-eksport mangler formelt.
- **Produkter:** ✅ Uncategorized 0, SKU'er ok, ingen dubletter; ❌ 1 billede/produkt, produkt-FAQ mangler, relaterede produkter ukendt; ✅ levering/retur/size guide-sider findes (synlighed på produktside ⚠️).
- **Kategorier:** ✅ T-shirts/Hoodies/Goggles/Accessories/Corsets-Clothing live; ❌ Artist Series + Gifts; ⚠️ Posters & Wall Art kategori findes men **0 produkter** (samme blocker som 20/10-audit — posterne skal re-importeres efter genopbygningen).
- **SEO:** ✅ noindex cart/account, Product schema; ❌ **sitemap broken**, gammel /sitemap.xml pending.
- **Design:** ✅ Workshop skin v3 live (bevidst afvigelse fra Dark Clockwork).
- **Marketing:** ✅ GA4 basetagging, MailPoet aktiv; ❌ e-commerce events, review-flow (0 anmeldelser), TikTok-for-Woo aktiv men TikTok-kanal afhænger af Buffer-opgradering/Postiz.

## Afsnit 19 — Prioriteret rækkefølge

| Planens prioritet | Faktisk tilstand |
|---|---|
| P1 (1–7): backup, dubletter, Uncategorized, kategorier, URLs, links | ✅ Færdig 23–24/10 (backup, 0 dubletter, 0 uncategorized, 6 kategorier live) |
| P2 (8–14): produktsoptimering, billeder, size guide, levering/retur, footer, CTA'er | 🟡 Size Guide ✅ (ny), Delivery/FAQ/Refund/Contact ✅; ❌ top-10 produkttekster, 3 billeder/produkt, footer-Size Guide/Order Tracking, CTA-opstramning ("Wear It Tonight" m.fl. stadig på forsiden) |
| P3 (15–20): Dark Clockwork, hastighed, sitemap, schema, GA4/GSC | 🟡 PSI mobil 86 (23/10), schema ✅, GA4/GSC ✅; ❌ sitemap broken (KRITISK), Dark Clockwork bevidst skippes til designbeslutning |
| P4 (21–26): pillar page, nye guides, ekstra kategorier, anmeldelser, måling | 🟡 Pillar page ✅ allerede live (planen ved det ikke); ❌ 8 nye guides, Gifts/Workshop Decor, anmeldelser; ✅ målerutine fra 26/10 |

## Ikke i planen, men i vores backlog (må ikke glemmes)
1. **Stripe live-flip** — gateway står i testmode=yes efter testordre #688 (24/10). KRITISK før rigtige kunder + 1 rigtig live-ordre til KYC/payout.
2. Posters & Wall Art: 0 produkter — 75-design-CSV'en skal importeres.
3. Oprydning: dublet-sider `cookie-policy-2` + tom side `629-2` i pages-listen.
4. Pinterest API-token 401 (Buffer dækker indtil videre); pirat piger-serien; Rank Math Local Social-felter.
5. Sikkerhed: Stripe REST-endpoint eksponerer sk_test_-nøgle — overvej blokering/rotation ved live-flip.

## Konklusion
Planens uge 1–2 og P1 er reelt overstået. **Eneste nye kritiske fund: sitemap-modulet i Rank Math genererer kun author-sitemap efter genopbygningen** — det er højere prioritet end alt i planens uge 3–8, fordi alt indekseringsarbejde er spildt, til den er fixet. herefter: e-commerce events + Artist Series/Gifts-kategorier + de 8 manglende guides.
