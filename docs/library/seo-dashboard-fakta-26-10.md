# Fakta-dokument: Self-hosted SEO-dashboard som kommercielt produkt (multi-user v8.0)

**Trukket:** 26/10 2026 (mandag) — alle priser/salg talt direkte fra live-siderne via browser
**Anledning:** vurdering af Keyword & SERP Coverage-appen ("CC") som kommerciel multi-user version
**Metode:** verifiable facts — live URLs, offentlige salgstal, API-dokumentation. Estimater er tydeligt mærket og model-baserede med åbne antagelser.

---

## 1. Markedstal — benchmarks og konkurrenter

### 1a. BabyLoveGrowth (SaaS-benchmark) — live prisside 26/10 2026

Kilde: https://www.babylovegrowth.ai/en/pricing (hentet 26/10 2026)

| Plan | Pris (billed yearly) | Strukket anker på siden | Indhold |
|---|---|---|---|
| Grow | **$59/md** | ~~$247~~ | 30 artikler/md, 10 AI-prompts tracket, "$800+/mo backlinks", auto-publish til WP/Shopify |
| Scale | **$199/md** | ~~$599~~ | 120 artikler/md, 50 prompts, dedikeret specialist, **unlimited seats**, prioriteret support |

- Månedlig fakturering (iflg. uafhængige reviews, ikke live-siden): Grow **$99/md**, Scale **$299/md** — kilde: ryandoser.com/babylovegrowth-review/ og keytomic.com/blog/babylovegrowth-vs-keytomic
- **Årsomkostning for kunden: $708–$2.388/år** (yearly) eller $1.188–$3.588 (monthly)
- 90-dages money-back-garanti; 3-dages trial; volumen-rabat 10–20% ved 2–15+ sites
- **Whitelabel-reseller-program findes** ("Resell BabyLoveGrowth to your clients") — agencies er en målgruppe BLG selv betaler for at nå
- OBS: BLG er et content/backlink-service med dashboards på siden — ikke et self-hosted dashboard-produkt. Prisniveauet er benchmarken for "hvor meget giver SEO-ops i abonnement?"

### 1b. CodeCanyon (self-hosted markedet) — talt 26/10 2026

Kilder: codecanyon.net/search/seo%20dashboard?sort=sales · codecanyon.net/search/seo%20script?sort=sales

| Faktum | Tal |
|---|---|
| Resultater på "seo dashboard" | 35 stk. — **0 Top Sellers, 3 "High"-salgs-niveau, 15 "Medium"** |
| Resultater på "seo script" | 66 stk. |
| Scripts med PHP 8.x (af "seo dashboard"- PHP-scripts) | 9 af 20 |
| Opdateret indenfor seneste år | 5 af 35 |

**De relevante SEO-items (sorteret efter salg):**

| Item | Pris (regular / extended) | Salg | Offentl. kommentarer | Reviews | Sidste opdatering |
|---|---|---|---|---|---|
| phpRank – SEO Reports & Tools Platform | $59 / $299 | **1.358** | 772 | 71 | 29/6 2026 |
| SumoSEOTools – Online SEO Tools Script | $38 / $1.338 | 776 | 716 | 40 | 10/10 2024 |
| WP SEO Propeller – Advanced SEO Analysis | $89 | 124 | — | 10 | **10/5 2023 (død)** |

- phpRank er det eneste seriøse self-hosted SEO-dashboard i kategorien. List-value: 1.358 × $59 ≈ **$80K over levetiden** (levetid ≥ 3 år; item-id 35522081 oprettet 2022).
- **Envato tager 50% Author Fee af item-prisen** — kilde: help.author.envato.com "Introduction to Earnings" + author.envato.com (revenue-share-ændring: "the author fee for all authors will move to 50%"). Dvs. phpRanks forfatter har realistisk tjent ~**$40K over ~4 år ≈ $10K/år** før refund/support-omkostninger.
- Support-forlængelse på phpRank: "Extend support to 12 months" koster $20,25 (nedsat fra $47,25).

### 1c. Feature-hul i markedet (verificeret i feature-listerne)

phpRanks fulde feature-liste (item-siden) og SumoSEOTools' 50+ tools indeholder **ingen af**:
- Google Search Console-integration (GSC-bro)
- WooCommerce-overvågning (pris/lager-drift)
- Konkurrent-pris-monitors

Category-søgningen bekræfter: 35 "seo dashboard"-scripts, 0 top sellers, 3 high-sellers — kategorien er lille og den tekniske barriere (GSC OAuth + API) er årsagen: ingen af items har løst den.

---

## 2. Feature-diff: CC (Keyword & SERP Coverage) vs markedet

### 2a. Hvad CC har i dag (fra vores egen database/app, talt 26/10 2026)

| Komponent | Status i dag | Tal |
|---|---|---|
| GSC-bro (Search Analytics API) | ✅ Bygget og kørende | 13 rows i query_stats, 18 i page_stats; 38 seed-keywords med SERP/coverage/opportunity-score |
| GA4-feed | ✅ Via fast Monday-refresh | G-3MYLFKY8MY, property 559297232 |
| Multi-site / multi-user | ❌ Single-site | 1 domæne (steamgears.eu), ingen auth-brugere |
| Woo-drift (pris/lager-drift) | ❌ Ikke i appen — men prototype eksisterer separat | Rival Price & Catalog Watch: **55 konkurrenter, 79 prisrows** |
| Pris-monitors | ❌ Ikke i appen — prototype separat | samme DB |
| On-page audit-tools (à la phpRank) | ❌ | — |
| Kommerciel betalingsinfra | Delvis | Stripe-gateway i live-mode (verificeret 25/10); rigtig live-ordre til KYC udestående |

### 2b. Diff-konklusioner pr. feature (fakta-baseret)

1. **GSC-bro er CC's eneste reelle differentiator i kategorien** — ingen af de 35 CC-items har den, og kvoterne er overkommelige: Search Analytics API tillader **1.200 QPM pr. site og pr. user, 40.000 QPM / 30M QPD pr. projekt** (kilde: developers.google.com/webmaster-tools/limits). URL inspection: 600 QPM / 2.000 QPD pr. site. Ét Google Cloud-projekt kan betjene mange tenants til Search Analytics-formål.
2. **Woo-drift er det største umættede hul**: WooCommerce driver **47,4% af alle e-handelssystemer og 7,8% af alle websites** (W3Techs, live 26/10 2026: w3techs.com/technologies/details/cm-woocommerce). Ingen self-hosted SEO-dashboard på CC overvåger Woo-pris/lager-drift. Vores egen prototype (79 prisrows over 55 konkurrenter) er proof-of-concept.
3. **Pris-monitors er den mest vedligeholdelsestunge feature at sælge**: AliExpress har ingen åben offentlig produkt-API (Portals/affiliate-API kræver godkendelse), så monitorer bygger på scraping som bryder — det er en support-drift i sig selv (se §3).

---

## 3. Support-byrde ved multi-user v8.0 (ANKRE = fakta; MODEL = estimat)

### Verificerbare ankre

| Anker | Tal | Kilde |
|---|---|---|
| Envato: support-forpligtelse | Min. **6 måneders support inkluderet** i prisen pr. køber | help.author.envato.com "Item Support – Best Practices" |
| Envato: author fee | **50%** af item-pris | help.author.envato.com "Introduction to Earnings" |
| Offentlig support-trafik pr. solgt licens (phpRank) | 772 kommentarer / 1.358 salg = **0,57 kommentar/salg** | codecanyon item-side |
| Offentlig support-trafik pr. solgt licens (SumoSEOTools) | 716 / 776 = **0,92 kommentar/salg** | codecanyon item-side |
| Google OAuth Testing-tilstand | **Hård grænse: 100 test-brugere** | developers.google.com/identity/protocols/oauth2/production-readiness/overview |
| Google OAuth-verifikation | Branding-review (automatisk, minutter) + data-access review; **sensitive scopes: typisk 3–5 hverdage** | developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification |
| GSC API-scopes (`webmasters`, `webmasters.readonly`) | Ikke listet under Googles restricted-scopes-sektion | developers.google.com/identity/protocols/oauth2/scopes |
| Vores supportbyrde i dag | **~0 timer** (1 bruger = Henning; Juno kører cron selv) | egen drift |

### MODEL: Estimeret årlig supportbyrde (assumptions åbne)

Antagelser: 100 solgte licenser i år 1; ticket-rate = den observerede comment/salgs-rate 0,57–0,92; 1,5–3 timer pr. ticket for self-hosted produkter (install, PHP-version, OAuth-setup).

| Scenario | Tickets år 1 | Timer år 1 | Arbejdsdage (7,5 t) |
|---|---|---|---|
| Lavt (0,57 × 100 × 1,5 t) | 57 | ~86 | ~11 |
| Højt (0,92 × 100 × 3 t) | 92 | ~276 | ~37 |

**Hvor byrden kommer fra (kategoriseret):**
1. **OAuth/GSC-onboarding pr. tenant** — største nye kategori; hver køber skal forbinde Search Console. 100-bruger-grænsen i Testing-tilstand gør verifikation obligatorisk **før** kommercielt salg (hard blocker, 3–5 hverdage review).
2. **Hosting-varians** — kun 9 af 20 PHP-scripts i kategorien understøtter PHP 8.x; self-hosted = uendelige server-varianter (phpRanks 772 kommentarer er det levende bevis).
3. **Scraper-vedligeholdelse** — hvis pris-monitors sælges som feature: AliExpress-markup ændrer sig; hver ændring = support-bølge. (SumoSEOTools: sidste opdatering 10/10 2024, stadig 716 kommentarer — drift stopper ikke, når udviklingen holder op.)

**Envato-salg betyder også:** 6 måneders supportforpligtelse pr. køber er kontraktlig minimum, og offentlig kommentar-tråd skalerer bedre end privat email (svarene genbruges).

---

## 4. Salgsvinkler med tal

### Vinkel A — "Eet års abonnement = 12–40 licenser" (prisanker mod SaaS)
BabyLoveGrowth koster $708–$2.388/år (yearly-billing) for det, et self-hosted dashboard kan levere som **one-time**. Med en $59–$79-licens (phpRanks verificerede prispunkt, 1.358 salg viser markedet bærer det) er påstanden målelig: "Betaler du BLG Scale ($2.388/år), har du betalt 30–40× vores pris — og dataene er stadig hos dem." One-time self-hosted + kunden ejer sit eget GSC-data.

### Vinkel B — WooCommerce-niche-hullet (differentiering)
47,4% af alle e-handelssystemer kører WooCommerce (W3Techs 26/10 2026). Nul af de 35 SEO-dashboard-scripts på CC overvåger Woo-pris-/lager-drift eller konkurrentpriser. Vi har allerede en kørende prototype (79 prisrows / 55 konkurrenter) fra egen butik. Produktet: "SEO-dashboardet, der også passer på din butik." Det er et hul, ingen af 1.358 phpRank-køberne kan købe sig til i dag.

### Vinkel C — Distributions-økonomi (CodeCanyon vs egen checkout) — tal, ikke holdning
| Kanal | 100 salg à $59 | Faktorer |
|---|---|---|
| CodeCanyon | ~$2.950 netto (efter 50% Author Fee, før refunds) | Trafik er inkluderet (phpRank: 1.358 salg er bevis for kategorien sælger); 6 mdr. support er påkrævet; prisen konkurrerer mod $38–$89-items |
| Egen Stripe-checkout | ~$5.700 netto (efter ~2–3% Stripe-gebyr) | Gateway allerede i live-mode (25/10); ingen platform-gebyr; men **ingen indbygget trafik** — salget skal drives af Steamgears/Warpstorm-kanalerne (Pinterest, Buffer, blog) |

---

## 5. Hvad dokumentet IKKE kan svare på (huller i fakta-grunden)

- BabyLoveGrowth' omsætning/antal kunder er ikke offentlig ("Trusted by 5,000+ companies" er egen marketing-claim, ikke verificeret).
- CodeCanyon-omdanningsrater for nye listings (trafik → salg) er ikke offentlige — kun phpRank/SumoSEOTools' kumulative salg er målbare.
- Refund-rate og support-timer pr. ticket for selve kategorien er ikke offentlige — modeltallene i §3 er derfor intervaller, ikke punkter.
- Vores egen app mangler endnu historik: query_stats/page_stats er først på 13/18 rows (første refresh 26/10). Produkt-kvaliteten kan først bedømmes på reelle data efter 4–6 ugers drift.

## 6. Hårde blokkere før multi-user v8.0 kan sælges (fakta, rækkefølge)

1. **Google OAuth-verifikation** — 100-brugers hard cap i Testing-tilstand gør verifikation til en forudsætning for kommercielt salg (3–5 hverdage for sensitive scopes + branding-review).
2. **Stripe live-ordre/KYC** — allerede 80% på plads (gateway live 25/10); en rigtig ordre udeståender.
3. **Multi-tenant arkitektur** — i dag: 1 site, 0 brugere, 0 auth. Support-modellen i §3 forudsætter, at onboarding er selvtjent (docs + demo), ellers stiger 1,5–3 t/ticket.
4. **Beslutning: pris-monitors ind/ud** — de er differentierende (§2) men den dyreste feature i support (§3.3). Kan skilles ud som add-on.
