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
