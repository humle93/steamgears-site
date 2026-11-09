# Steamgears — SEO-regler 2026, research-log

Recurring research: mandag + torsdag 09:00 (skema "Steamgears SEO 2026 research (Gemini)").
Format: dato · top-ændringer · effekt for steamgears.eu · handlinger · kilder.

## 2026-10-23 (baseline, Juno — Gemini utilgængelig pga. forbindelsesfejl)

**Top-ændringer (fra søgning):**
- Rygter om en Google-opdatering under udrulning i oktober 2026 — follow status på Googles Search Status Dashboard.
- AI Overviews er nu dominerende i amerikansk søgning; guides skal optimeres til generative svar (klare spørgsmål+svar-struktur, entity-rigt indhold).
- Ecommerce/product review-krav: styrkede krav om førstehånds erfaring og transparens i produktindhold.

**Effekt for steamgears.eu:**
- Guides (en-US) er allerede instruktionsbaserede — godt fit for AI Overviews-citater.
- Shopkategori/produktsider bør have original beskrivelse + erfaringssignaler (photos, "how we styled it").

**Handlinger:**
1. Overvåg status.dashboard for den October-update ugen ud; ved rul-out: tjek GSC query_stats for position-drift.
2. Tilføj Q&A-stil-sektioner (2-3 spørgsmål) i de 7 udgivne guides.
3. Produktbeskrivelser: hold dem unikke, tekstfri designnote + styling-kontekst.

**Kilder:**
- https://status.search.google.com/products/rGHU1u87FJnkP6W2GwMi/history
- https://searchenginewatch.com/google-october-2026-update-rumors/
- https://seovendor.co/google-algorithm-updates-october-2026/
- https://developers.google.com/search/docs/fundamentals/ai-optimization-guide
- https://seoprofy.com/blog/google-ai-overviews/

## 2026-10-26 (Gemini 3.8 Flash ✅)

**Top 3 ændringer:**
1. **Google AI-indholds-guiden opdateret 1/10 2026** — AI-indhold kræver nu eksplicit menneskelig faktatjek + førstehåndserfaring (Quality Raters-materiale foldet ind); AI content mills rammes.
2. **Universal Cart + UCP (agentic checkout)** — køb direkte i AI Mode/Gemini uden site-besøg; Merchant Center-feeds bliver konverteringskritiske for US-webshops.
3. **Ingen bekræftet core update i oktober, men kraftig ubekræftet volatility 20-21/10** (Sep spam update afsluttet 8/10). AI Overviews ~13% af queries, AI Mode ~75M daglige brugere — zero-click er normalen for informationelle søgninger.

**Effekt for steamgears.eu:**
- Guides risikerer klik-tap til AI Overviews; skal bære first-hand experience-signaler (egne fotos, process-noter, forfatterprofil) for at undgå "content mill"-devaluering.
- US-målgruppe = agentic checkout rulles ud først der — produktfeed (priser, lager, shipping til US, retur) bliver en direkte salgskanal, ikke kun synlighed.
- Volatility-uro kan vise sig som GSC-drift i de kommende dage.

**Handlinger:**
1. Tjek/etablér Google Merchant Center-feed med komplet US shipping + return-data som forberedelse til UCP/AI Mode-checkout.
2. Berig de 7 udgivne guides med first-hand-elementer (nærbilleder, "what we used", forfatter-/maker-profil) — kombineres med de planlagte Q&A-sektioner fra 23/10.
3. Fuld Product/HowTo-schema på produktsider + guides (Product, Offer, ItemAvailability, AggregateRating).
4. Indholds-audit: omskriv/berig tynde generiske artikler efter spam-updaten.
5. Overvåg GSC query_stats ugentlig (mandag 08:30-dashboardet) for drift fra 20-21/10-uroen.

**Kilder:**
- https://developers.google.com/search/docs/fundamentals/using-gen-ai-content (opdateret 2026-10-01)
- https://blog.google/products-and-platforms/products/shopping/universal-cart-launch/
- https://support.google.com/merchants/answer/16837055 (UCP)
- https://blog.google/products-and-platforms/products/search/search-io-2026/
- https://searchengineland.com/google-september-2026-spam-update-done-rolling-out-493550
- https://www.seroundtable.com/google-search-ranking-volatility-42347.html
- https://www.digitalapplied.com/blog/seo-after-ai-overviews-complete-strategy-guide-2026

**Kørsel:** web_search (4 søgninger, qdr:w) → Gemini 3.8 Flash (2.229 tokens, $0,0088) — Gemini fungerede, ingen fallback.

## 2026-10-26 (eftermiddag) — Trend-scan begge sites ✅

Fuld trend-scan på nettet for Steamgears + Warpstorm → brief i /workspace/library/trend-scan-2026-10-26/trend-brief.md.
Top-signaler: 40K 11th ed. launch (Armageddon-box, warhammer-community 26/3), codex-hul til jan 2027 (spikeybits 14/10), Christmas Battleforces live (19/10), grimdark painting-trend, dieselpunk/gasmask på Pinterest, closet-cosplay + 3D-print/etisk mode (2026-trends). 8 backlog-idéer prioriteret i briefen. Intet startet — venter på workshop-plan (110%-prioritet).
**Kørsel:** 5 web_search + 4 website fetch, Juno-syntese (Gemini ikke brugt — rå data var tilstrækkelig).

## 2026-10-26 — Posters & Wall Art gift-guide pipeline
- 72 tekst-fri poster-motiver total: 12 steampunk (GG-001..012) + 5 genrer × 12 (dieselpunk DG, atompunk AT, solarpunk SL, cyberpunk CY, weird west WW).
- Kvalitet: alle enkeltvist inspiceret; 10 re-gens/edits pga. pseudo-tekst (diesel 02/03/05/06, atompunk 06+08, solar 03+12, cyber 03/05/06/10/12, west 03/05/09/12). Cyberpunk-skilte er abstrakte geometriske glød-symboler (ulæselige med vilje).
- Generering: Juno indbygget billedmodel (Gemini image) — IKKE fal (balance tom) og IKKE aimlapi (mangler image-endpoint fra CLI; scripts blokerer netværk). Henning spurgte om aimlapi til billeder — svar givet: indbygget model er vejen.
- Canvas-arkitektur: "Posters & Wall Art — Gift Guide Mockups 2026" (id 7b09a4c9) med art-masters, art-masters-genres + 12 mockup-asset-sider (2 størrelser × 6 linjer). SKU'er forudbestilt SG-POSTER-GG/DG/AT/SL/CY/WW-001..012, $29.95/$39.95.
- Næste: upscale til print-res → Printify-varianter → CSV → Gift Guide-artikel med sg-buy CTA'er før Black Friday.

## 2026-11-02 (uge 45) — Ugentlig SEO-research ✅

**Top 3 ændringer (26/10–2/11):**
1. **Ny IETF AIPREF AI-training control (30/10):** Google adopterer frivilligt en ny åben standard (samt Bing, OpenAI, Anthropic), så websites kan signalere AI-trænings-præferencer på tværs af udbydere i ét signal — adskilt fra almindelig search-indeksering.
2. **UGC-spam-guidance opdateret (26/10):** Ny vejledning om at blokere indeksering af link-shared/dynamisk brugerindhold (ønskelister, delte kurve, bruger-URL'er) + monitoring i Search Console.
3. **Ingen bekræftet update, men vedvarende ubekræftet volatility 20–27/10:** Seroundtable meldte ny, større spike 27/10 (163 kommentarer). Seneste bekræftede er stadig September spam update (afsluttet 8/10).

**Effekt for steamgears.eu:**
- AIPREF: Mulighed for at styre om guides/produktdata må bruges til AI-træning — uden at miste AI Overviews-synlighed. Kræver en bevidst beslutning (Henning-trin).
- WooCommerce-UGC: delbare ønskeliste/kurve-URL'er skal noindex'es; rel="ugc" på review-links.
- US-guides kan have været påvirket af volatility 20–27/10 — tjek GSC US-filtreret uge 43/44.

**Handlinger:**
1. Beslut AIPREF/robots-tilgang til AI-træning (tillad eller bloker træning af guides) og implementér.
2. noindex på delbare WooCommerce-URL'er + rel="ugc" på anmeldelses-links (26/10-guidelines).
3. Tjek Merchant Center "AI Performance Insights" for produkt-synlighed i AI Mode/Universal Cart (Universal Cart live i USA 15/10).
4. GSC-audit: US-trafik uge 43/44 vs. tidligere for volatility-effekt.

**Kilder:**
- https://developers.google.com/search/blog/2026/10/ai-training-control
- https://www.seroundtable.com/google-ai-training-control-42430.html
- https://developers.google.com/search/updates
- https://www.seroundtable.com/google-search-ranking-volatility-42379.html
- https://blog.thepublive.com/google/google-search-ranking-volatility-in-october-2026-what-it-means-for-your-web-traffic-12741322
- https://blog.google/products/ads-commerce/agentic-commerce-ai-tools-protocol-retailers-platforms/
- https://superpowerdaily.com/posts/google-launches-a-shared-shopping-cart-across-search-and-gemini-in-the-u-s
- https://missionmedia.asia/google-ai-performance-insights-merchant-center/

**Kørsel:** web_search (7 søgninger, qdr:w/qdr:d + live fetch af developers.google.com/search/updates, status dashboard og seroundtable) → Gemini 3.8 Flash (4.039 tokens totalt, 2.996 completion inkl. 1.744 reasoning-tokens — maks på 3000 ramt, sidste handling let afkortet; hæv max_tokens til 3500 næste gang) — Gemini fungerede, ingen fallback. Ny i denne uge vs. 23/10-log: AIPREF + UGC-spam-guidance; Universal Cart/volatility kun nævnt som opfølgning.

## 2026-11-05 (uge 46) — Ugentlig SEO-research ✅

**Top 3 ændringer (29/10–5/11):**
1. **Google indskrænker AI-indhold: "effort & originality"-test kodificeret (1/10, eskaleret i nov-rapport):** Gen-AI-guidancen kræver nu manuel factcheck af ALT AI-indhold — inkl. meta descriptions, structured data og alt-tekster — og henviser direkte til Quality Rater Guidelines 4.6.5 (scaled content abuse) og 4.6.6 (main content with little effort, little originality). Fake forfatterprofiler og misinformation er ligeledes i sigte (https://developers.google.com/search/docs/fundamentals/using-gen-ai-content, https://technicalseonews.com/latest/google-adds-rater-effort-originality-test-to-content-docs).
2. **Core update er overhængende:** Ingen bekræftet update siden maj 2026 (seneste = September spam update, afsluttet 8/10), men SEJ-model estimerer 96,8% sandsynlighed for stor core update inden for en måned; SERP'er har været ustabile gennem oktober (https://www.seoforjournalism.com/p/google-updates-october-news-ai-overviews, https://www.redefineyourmarketing.com/blog/google-algorithm-update-running-list).
3. **AI Overviews oversvømmer kommercielle søgninger:** AIO nu på 80%+ af alle Google-søgninger inkl. brand-søgninger; 88% af kommercielle modifier-keywords; e-handel-queries 33%→85% på ét år; "review"-søgninger 90%; shopping-queries 2,1%→14% (5,6x). Kaiser-studie (7.083 e-commerce-domæner): AIO koster ~2,8% organisk sessions, men transaktioner/revenue er stort set upåvirkede (konvertering +2,6%) — position 4-10 rammes hårdest (https://www.semrush.com/blog/ai-overviews-commercial-modifiers-study/, https://searchengineland.com/google-ai-overviews-shopping-queries-report-471981, https://www.maximiliankaiser.org/publication/ai-overviews-ecommerce/, https://distinctly.co/blog/latest-organic-search-news-november-26/).

**Effekt for steamgears.eu:**
- Guides med flad/generisk AI-tone eller manglende forfatteridentitet risikerer devaluering i den forestående core update (QRG 4.6.5/4.6.6).
- US kommercielle keywords ("best steampunk goggles", "steampunk corset review"-agtigt) vil næsten sikkert trække AIO — organisk CTR presset, men købende trafik klarer sig relativt bedre.
- Google Merchant data (feeds + Product Reviews via Merchant API, min. 50 reviews) er nu en officiel vej ind i AI-responser — steampunk-produkter kan citeres direkte i AIO/AI Mode.
- Eksperiment-dokumentation: konkrete kunde-outcomes på sider løftede AI-citation-rate 23%→54%; 5+ credibility statements på forside = +62% AI-visibility for mid-authority brands.

**Handlinger:**
1. Manuel audit af guides + metadata (meta descriptions, schema, alt-tekster): fjern generisk AI-sprog, tilføj original indsigt/fotos, ægte forfattersignatur (E-E-A-T) — FØR core update rammer.
2. Berig guides/produkttekster med konkrete "outcomes" (testresultater, materialer, måltider, what broke) for at maksimere AI-citation.
3. Forsiden: sikr 5+ tydelige credibility-statements (levering, retur, trust, firmaoplysninger).
4. Merchant Center: tjek produkt-feed-kvalitet; plan for Product Reviews (min. 50 reviews via ny Merchant API).

**Kilder:**
- https://developers.google.com/search/docs/fundamentals/using-gen-ai-content
- https://technicalseonews.com/latest/google-adds-rater-effort-originality-test-to-content-docs
- https://www.seroundtable.com/november-2026-google-webmaster-report-42431.html
- https://www.redefineyourmarketing.com/blog/google-algorithm-update-running-list
- https://www.seoforjournalism.com/p/google-updates-october-news-ai-overviews
- https://www.semrush.com/blog/ai-overviews-commercial-modifiers-study/
- https://searchengineland.com/google-ai-overviews-shopping-queries-report-471981
- https://www.maximiliankaiser.org/publication/ai-overviews-ecommerce/
- https://distinctly.co/blog/latest-organic-search-news-november-26/
- https://developers.google.com/search/docs/fundamentals/ai-optimization-guide
- https://searchengineland.com/google-merchant-center-adds-api-support-for-product-reviews-489716
- https://support.google.com/merchants/answer/6098512?hl=en

**Kørsel:** web_search (4 søgninger) → Gemini 3.8 Flash via aimlapi (max_tokens 9000 pga. reasoning-tokens; 2. kald gav komplet svar, finish=stop) — Gemini fungerede, ingen fallback. Ny i denne uge vs. uge 45: effort/originality-kravet inkl. metadata, overhængende core update (96,8%-estimat), AIO-dækningstal for kommercielle/e-commerce-queries + Kaiser causal-studie. AIPREF/UGC-spam fra uge 45 ikke gentaget.

## 2026-11-09 (uge 46, 2. kørsel) — Ugentlig SEO-research ⚠️ Gemini utilgængelig

**Kørsel:** web_search (5 søgninger) → ai_ml_api/gemini-3-8-flash fejlede med **403: "You've run out of funds"** (aimlapi-balance er tom — https://aimlapi.com/app/billing/). **Gemini utilgængelig → Juno fallback-analyse** (markeret som fallback if. opgave-SOP).

**Top 3 ændringer i denne uge (3/11–9/11):**

1. **AI Overviews ≈ 80% af søgninger + Google udvider synlig link/attributions-visning.** Seroundtables november-rapport: AIO viser nu links oftere og mere fremtrædende, artikel-carousels, mere kontekst. Digiday: Preferred Sources er vokset 7x i år; page-level grounding controls (opt-out) kommer 3/3-2027; UK click-data til udgivere senest 3/12-2026.
   - Kilder: https://www.seroundtable.com/november-2026-google-webmaster-report-42431.html | https://digiday.com/media/as-publishers-await-click-data-googles-ai-visibility-push-complicates-the-opt-out-decision/

2. **Ziff Davis citation-to-click data (6/11): ~10x flere AI-visninger end klik, organisk -37% i Q3.** Eksternt eksperiment (Zyppy): AI search CTR ≈ 0,9% (0,7–1,1%), men AI search stod for 23% af site'ets samlede search-klik (halo-effekt). Google mangler fortsat AI click-reporting i GSC inden UK-deadline.
   - Kilder: https://www.aipublisher.ai/2026/11/07/audit-citation-to-click-gap/ | https://signal.zyppy.com/p/we-opted-out-of-google-ai-search | https://www.searchenginejournal.com/google-ai-click-reporting-uk-deadline/595663/

3. **Volatilitet uden navngiven update; core update ventes inden udgangen af november.** Google: ingen update 27/10–3/11 (dashboard), men Semrush Sensor/RankRanger/MozCast rapporterer skarpe udsving fra start af november, især på queries der trækker AIO — citation-cards skifter mellem autoritative domæner og niche-kilder. SEJ-model: 96,8% sandsynlighed for core update inden 30/11. Seneste bekræftede: sept. 2026 spam update (24/9–8/10), maj 2026 core.
   - Kilder: https://www.redefineyourmarketing.com/blog/google-algorithm-update-running-list | https://www.searchprex.com/resources/news/google-november-2026-search-ranking-volatility | https://tonconsultant.ca/en/seo-news-week-of-november-9-2026/ | https://www.seoforjournalism.com/p/google-updates-october-news-ai-overviews

**Øvrigt relevant:**
- GSC performance: unormalt klik-spring omkring 31/10 for nogle sites (mulig bug/bot-trafik — tjek egne GSC-tal mod det, før det tolkes som ægte vækst).
- Google opdaterede "Crawled - Currently Not Indexed"-dokumentation (3/11).
- 404: Illyes — "fixing a 404 is entirely optional"; redirect kun til ægte erstatning, aldrig blindt til forside.

**Effekt for steamgears.eu (fallback-analyse, Juno):**
- US kommercielle steampunk-queries vil næsten sikkert trække AIO (48% af kommercielle queries). Med ~0,9% AI-CTR er klik-pressure reelt — men Zyppy-data viser halo-effekten: synlighed i AI kan Drive samlet brand-search. Mål: bliv citéret (structured data, førstehånds-indhold) frem for at jagte den samme top-10.
- Novembers citation-card-fluctuation betyder, at niche-domæner (som steamgears.eu) faktisk kan vinde citationer i perioder — revisér/berig de 2-3 stærkeste guides i disse uger.
- Forfatterskabs-advarslen er kritisk: ingen fiktive forfatterprofiler/AI-headshots på guides. Ægte signatur (Henning/Steamgears Workshop) + "how we tested"-dokumentation.
- GSC klik-spring (31/10): validér med GA4 før konklusioner i månedens rapport.

**3 konkrete handlinger denne uge:**
1. **Forfatterskabs-audit:** Verificér at ALLE guides har ægte byline + about/credential-signal — ingen AI-fabrikerede profiler (Google: deception = low-quality signal). Kilde: https://searchengineland.com/google-warns-against-using-fake-author-information-on-your-content-493701
2. **AI-citation berigelse af top-guides:** Tilføj førstehånds-evidence (fotos af egne builds, målinger, "what broke") til de 2-3 vigtigste guides — non-commodity content per Googles AI-optimization guide. Kilder: https://developers.google.com/search/docs/fundamentals/ai-optimization-guide | https://developers.google.com/search/docs/specialty/ecommerce/write-high-quality-reviews
3. **Pandasentation til core update:** Da core update ventes inden udgangen af november: tjek at produkt-schema/feed-kvalitet (Merchant/structured data) er ren på tværs af de ~77-90 produkter, så shoppen er AI-læsbar før update. Kilde: https://www.yotpo.com/blog/ecommerce-ai-overviews-seo/

**Kilder (samlet):**
- https://www.seroundtable.com/november-2026-google-webmaster-report-42431.html
- https://digiday.com/media/as-publishers-await-click-data-googles-ai-visibility-push-complicates-the-opt-out-decision/
- https://www.aipublisher.ai/2026/11/07/audit-citation-to-click-gap/
- https://signal.zyppy.com/p/we-opted-out-of-google-ai-search
- https://www.searchenginejournal.com/google-ai-click-reporting-uk-deadline/595663/
- https://www.redefineyourmarketing.com/blog/google-algorithm-update-running-list
- https://www.searchprex.com/resources/news/google-november-2026-search-ranking-volatility
- https://tonconsultant.ca/en/seo-news-week-of-november-9-2026/
- https://www.seoforjournalism.com/p/google-updates-october-news-ai-overviews
- https://developers.google.com/search/docs/fundamentals/ai-optimization-guide
- https://developers.google.com/search/docs/specialty/ecommerce/write-high-quality-reviews
- https://www.yotpo.com/blog/ecommerce-ai-overviews-seo/

**Ny i denne uge vs. uge 45/46 (1. kørsel):** 80% AIO-dækningstal, Ziff Davis ~10x impression:click-forhold + -37% Q3, Zyppy 0,9% AI-CTR + 23% halo-effekt, UK click-data deadline 3/12-2026, page-level grounding controls 3/3-2027, Preferred Sources 7x, GSC klik-spring-bug, Illyes 404-afklaring. Ikke gentaget fra tidligere uger: fake-authorship, EOT/SA, Merchant Product Reviews API (dækket i 5/11-sektionen).
