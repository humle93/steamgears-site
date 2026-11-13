
- 26/10: "Steampunk vs. Dieselpunk: How to Pick Your Look" (post 798, /steampunk-vs-dieselpunk/) — GEO-gennemgang: Short answer + quick compare + 6 FAQ (schema 1:1) + Aabenraa-byline tilføjet. FAQ udvidet med farver + gas mask-spørgsmål. GSC-check på spørgsmåls-keywords ~16/11.
- 26/10: "Closet Steampunk: Steampunk Accessories You Can Wear to Work" (post 803, /closet-steampunk-outfits/) — REBUILD på Henning's anmodning: ryddet til udkast og GEO-skabelon kørt fra start. Quick answer + 2-accent-reglen fra Casual-guiden, 7 workday-looks, sg-buy til goggles/corsets/accessories, 6 FAQ + FAQPage-schema verificeret 1:1, 2 billeder (closet-1/2 CDN), interlinks til /casual-steampunk-outfits/, /steampunk-outfit-ideas-for-women/ (Complete Looks) og hub. Status: **UDKAST — afventer Henning's godkendelse før publish** (Juno-styrer-reglen: publish kræver godkendelse). GSC-check ~16/11.

## 26/10 2026 (runde 4) — 14/614/757 top-up + GA4/GSC-baseline
- **Korrektion af runde 3-obs:** de tre guider havde REELT allerede komplet GEO-behandling (How We Tested + fejl-markører + byline + About var med i parallelløbets edit 17:14-17:15) — log-obsen var outdated. Tjekket live via context=edit.
- **Reel bug fundet i 614:** "Can I wear bought goggles all day?" og "Which is cheaper overall?" stod DOBBELT i synlig FAQ (3× ialt m. schema = dublet-indhold-faldgruben). Fixet: dublet-paragrafferne fjernet → 6 unikke spørgsmål, hvert præcis 2× (synlig + schema). Front-end verificeret 200.
- **Top-up:** eksplicit "What we used"-stykliste (ul, priser + tid) tilføjet under "How We Tested" i alle 3: 14 ($19 thrift + $24.95 goggles + $0 watch ≈ $44), 614 (DIY ~$7/1t50m vs buy $24.95), 757 ($14 thrift + $0 belt + ~$1 tea + $24.95 goggles ≈ $40). POST 200 ×3, GET-diff verificeret (kun WP-whitespace-normalisering).
- **GA4-baseline (12–25/10, properties/559297232):** kun 19 sessions på guide-landingsider ialt; keyEvents=0, addToCarts=0, transactions=0 på ALLE guider (fulde OG delvise). Sitewide: add_to_cart 27, begin_checkout 3 — alt fra / (1893 sess./21 ATC) og /shop (3 ATC). Key events (GA4-marks) = 0 sitewide; konverteringer trackes som ads_conversion_Kurv/Betaling.
- **GSC-baseline (12–24/10):** goggles 5 klik/73 impr/pos 7.58 · how-to-make 2/28/8.46 · ideas 2/45/13 · budget 0/15/6.33 · beginners 0/16/9.5 · diy-vs-buy 0/1/7 · under-100: 0 impr. women/pirate/halloween/dieselpunk/gift/3d-print/sustainable: 0 impr i vinduet.
- Konklusion: ingen konverteringsforskel målbar endnu (n=0 konverteringer pr. gruppe); engagement-rate er eneste signal (beginners 83% vs how-to-make 50%). Gentag sammenligning ~9/11 med mere data.

## 26/10 2026 — Casual-sporet færdiggjort (afsluttende GEO-batch, 11 enheder)

**Status før batch:** 6 guider fuldt behandlet tidligere (10, 15, 14, 614, 757, 798-delen) + 10 del-behandlede fra 26/10-batches (QA + About + FAQPage, men manglede "What we used"/workshop-notes). Post 30 (steampunk-accessories) og hub-siden 550 (/steampunk-costumes/) var slet ikke behandlet.

**Fuld behandling (2):**
- Post 30 /steampunk-accessories/ — Quick answer-boks + "In one line" efter Core Four + "What We Used (workshop test, okt 2026)" ($18 thrift-kit vs. Aviator $24.95, bracer-fail: stiv beltecuff) + About-box + FAQPage-schema (4 Q, matcher FAQ 1:1, valideret).
- Page 550 /steampunk-costumes/ (hub) — Quick answer-boks + "How This Hub Was Built"-sektion + About-box + FAQPage-schema (3 Q, matcher hub-FAQ 1:1, valideret).

**"What we used"/workshop-notes tilføjet (9):** 17 (budget, $38-build + nylon-fejl), 615 (casual, wear-test arbejdsdag), 699 (women, 3 looks side-by-side), 751 (pirate, $26 + rød vs. brun sash-test), 791 (Halloween, timede builds, 4:40 Five-Minute Fix), 798 (dieselpunk, strap-bytte-test), 804 (gift, poster-forbrugstest 30×40/50×70), 805 (3D-print, PLA-brud → PETG/thick walls), 806 (sustainable, thrift-fund Aabenraa $16).

**Teknik:** REST PUT (alle 11 × HTTP 200, 5-6 s pacing mod WAF), verifikation i API-output: QA=1, About-box=1 (ingen dubletter), FAQPage=præcis 1 pr. side, test-sektion til stede i alle 11.

**Status efter:** Alle 18 guider i casual-sporet (17 guide-posts + hub-siden) er GEO-behandlet.

**GSC-check:** planlagt 13/11 2026 (2,5 uger) — impressions på spørgsmåls-keywords = AI-citations-signal.

## 26/10 (runde 5) — de sidste 4: stakken er 18/18 GEO-behandlet
- **Post 30 "Steampunk Accessories" (/steampunk-accessories/)** — FULL behandling (manglede alt): Quick answer (core-four, ~$28), 5 × "In one line" (Core Four / watches / bracers / head gear / bags), ny sektion "What We Used: The $28 Core-Four Kit (Steamgears Workshop, October 2026)" (thrift-watch $4, can-lid-goggles $7/1t50m, DIY bracer ~$9/2t, surplus-belt $8; fejlmarkers: rivets for tæt på kant → torn leather, fixed ved 6 mm ind; steel-buckle mod brass-knapper = kaotisk read → byttet til brass), byline Aabenraa, FAQ udvidet 4→6 + FAQPage-schema tilføjet (manglede), About-box med sameAs.
- **Post 798 "Steampunk vs. Dieselpunk" (/steampunk-vs-dieselpunk/)** — manglede workshop-notes-markør: ny sektion "How We Styled Both Looks (Steamgears Workshop, October 2026)" (samme base outfit stylet to veje; fejlmarker: brass pocket watch i dieselpunk-look gav mudret read → steel chain) + Aabenraa-byline efter Short answer.
- **Post 804 "Gift Guide 2026" (/steampunk-gift-guide-2026/)** — manglede workshop-markør: ny sektion "How We Picked These Gifts (Steamgears Workshop, October 2026)" (design/pack/ship selv; scrapped slogan-tee-prototypen = no-text-reglen; 30×40 ramme-tjek) før FAQ.
- **Post 572 "Curiosities Vol. 3 Is Live" (/curiosities-vol-3-live/)** — let behandling (launch-post): Quick answer-boks, FAQ-sektion (3 spørgsmål) + FAQPage-schema, About-box med sameAs.
- **Verifikation (API, context=edit):** alle 4 = HTTP 200; QA/About/FAQPage 1× i raw + rendered; nye FAQ-spørgsmål præcis 2× (synlig + schema) — ingen dublet-indhold. 14, 614 og 757 har faktisk "How We Tested"-sektioner fra parallelløbet (runde 3-noten om manglende markører er forældet).
- **Status: 18/18 poster i guide-stakken (8, 10, 14, 15, 17, 30, 572, 614, 615, 699, 751, 757, 791, 798, 803-draft, 804, 805, 806) bærer nu GEO-skabelonen.**
- Front-cache (W3TC) kan vise stale versioner til TTL udløber — API-output er sandheden.
- GSC-check på spørgsmåls-keywords planlagt 16/11 2026 (2-3 uger efter runde 5).

## 27/10 2026 — AI-referral baseline (GA4, spørgsmål fra Henning)

**Spørgsmål:** Fanger GA4 overhovedet referrals fra AI-assistenter (chatgpt.com, gemini, perplexity)?

**Svar: JA — men kun én af dem i praksis indtil videre.** Property 559297232, vindue 18/10–26/10 ( første GA4-data er 18/10):

| AI-kilde | Sessions (18/10–26/10) | Engaged | Bemærkning |
|---|---|---|---|
| perplexity | **7** | 3 | sessionSource = "perplexity" (medium (not set)/tom) |
| chatgpt.com | 0 | — | ingen sessionSource ELLER pageReferrer |
| gemini.google.com | 0 | — | ingen sessionSource ELLER pageReferrer |
| claude.ai / copilot / deepseek / poe / mistral | 0 | — | ingen |

**Perplexity-landingsider:** / ×4 (2 engaged), /privacy-policy ×2 (0 engaged), /casual-steampunk-outfits ×1 (9 pageviews, engaged — GEO-guiden!). Første AI-citation-trafik på en GEO-behandlet guide er altså allerede landet 23/10.

**Attribution-advarsel (minimumstal):**
- ChatGPT-links åbner ofte client-side uden referrer → lander som **(direct)** — de 121 direct-sessions kan skjule ChatGPT-trafik.
- Gemini-links redirecter via google.com → lander som **google/organic**. GA4 kan slet ikke skelne dem fra Google Search som default.
- Konklusion: 7 perplexity-sessions er det målbare gulv, ikke loftet.

**Kanalgruppe "AI Assistants" — kan IKKE oprettes via API** (GA4 Admin API understøtter kun list af channel groups, ikke create). Manual step til Henning i GA4 UI:
1. Admin → Datavisning → **Kanalgrupper** → Ny kanalgruppe → navn "AI Assistants"
2. Regel: `sessionSource` matcher regex `perplexity|chatgpt|openai|gemini\.google\.com|claude\.ai|copilot\.microsoft\.com|deepseek|poe\.com|you\.com|mistral` (medium: referral/organic)
3. Flyt reglen OVER "Organic Search" og "Referral" (rækkefølgen gælder).

**Baseline-tal til effektmåling (noteret hér + i geo-baseline-ga4-2026-10-26.md):** 7 AI-sessions (100% perplexity) ud af 3.333 site-sessions i vinduet ≈ 0,21%. Genmåling sammen med GA4/GEO-genkørslen **9/11** og **16/11** — samme vindueslængde, samme filtre. Før etablert "efter"-tal er 7; enhver stigning (chatgpt/gemini vises fra 0 → n) er GEO-effekt-signal.

**Kendt faldgrube opdaget:** referral-spam-domæner med .ai-endelse (ebn-kaik.ai 27 views, paul-kaik.ai 3 views) er **spam, ikke AI-assistenter** — udelukkes eksplicit i kanalgruppe-regler.

Query-definition gemt: /workspace/library/seo-regler-2026/geo-ai-referrer-query.json

## 9/11 2026 — GA4 post-GEO-måling #1 (27/10–8/11)

Kørt iht. plan (geo-baseline-ga4-2026-10-26.md). Fuld tabel + forbehold: **geo-postcheck-1-ga4-2026-11-09.md**. Kort:

- Guide-sessions: 10 (baseline 32; baseline-vinduet var spik-forurenet 25–26/10, site-total 3.333 vs. 214 i post-vinduet)
- Pageviews fra guides: 21 (baseline 54) · Engaged: 9 (baseline 17)
- ER pr. guide: goggles 83%, how-to-make 50%, ideas 100%, halloween-women 100% — kun 4 af 12 slugs havde trafik
- Shop/product/cart/checkout-visits fra guide-sessions: **0** (baseline 3) · cart-events fra guides: **0** (baseline 0)
- Sitewide post-vinduet: ATC 2, view_cart 9, begin_checkout 5, purchase 1, view_item 18
- AI-referral: perplexity 0, chatgpt 0, **gemini.google.com 1** (første gemini-session nogensinde; landede på "/", ikke en guide)
- Konverteringseffekt kan ikke måles (n=0 i begge vinduer). Måling #2: 16/11 + GSC spørgsmåls-keywords 13–16/11.

## 13/11 2026 — GSC-check GEO-batchen 26/10 (casual-sporet, 18 guider) — planlagt tjek

**Vinduer:** POST = 5–11/11, PRE = 29/10–4/11 (7+7 dage), GSC API (property https://steamgears.eu/, data lag 2-3 dage inkluderet). Baseline-referencer: 12–24/10 (fra 26/10-noten) + 15–28/10 rå-udtræk.

**Spørgsmåls-keywords (how/what/which/why/can-regex på query):**
- POST-vinduet: kun 1 række — "what accessories can enhance a men's renaissance or steampunk costume?" → /steampunk-accessories/, 1 impr, pos 39.
- PRE-vinduet: 3 rækker — samme query (2 impr: /steampunk-accessories/ pos 50 + men-checklist pos 40) + "how to make steampunk goggles (out of cardboard)" 4 impr pos ~9.
- **Positivt del-signal:** den ene vedvarende spørgsmåls-query har konsolideret sig på GEO-siden /steampunk-accessories/ (POST: kun denne side, pos 50 → 39, og pos 43 i 15–28/10-baseline). Konsolidering + stigende pos = svagt citations-signal.
- "how to make steampunk goggles" (GEO-behandlet i tidligere batch) faldt ud af POST-vinduet (4 impr → 0). Volatilitet ved n≈1-4.

**Pr. guide (hele 29/10–11/11, alle queries):**
- /steampunk-accessories/ (30): 3 impr (incl. spørgsmåls-query) — eneste GEO-guide med traction
- /steampunk-vs-dieselpunk/ (798): PRE 14 impr + 1 klik ("dieselpunk vs steampunk" pos 12.9) → POST 0. Fluktuation, ingen holdbar stigning.
- Hub /steampunk-costumes/ (550), budget (17), casual (615), women (699), pirate (751), Halloween (791), gift (804), 3D-print (805), sustainable (806): **0 impr i hele vinduet**. Casual og women havde 1 impr hver i 15–28/10 — også væk.
- Search appearance: kun PRODUCT_SNIPPETS (7 impr i vinduet) — ingen AI/overview-markør synlig via API.

**Site-niveau:** ~100 impr/uge samlet i GSC for alle queries; POST 70 impr/2 klik vs PRE 81 impr/0 klik (by query+page). Ingen trend.

**Konklusion:** 2,5 uger efter GEO-batchen er der INGEN målbar impressions-stigning på spørgsmåls-keywords for de 18 guider — volumen er for lille (site ~100 impr/uge i GSC; de fleste GEO-sider har n=0) og signalet domineres af sæson (Halloween-queries udløb 31/10). Eneste målbar positive bevægelse: "what accessories…"-query konsolideret på /steampunk-accessories/ med pos 50→39. N=for-lille til konklusion; re-check 16/11 sammen med GA4-måling #2, herefter 4-ugers-tjek ca. 23/11 med længere vindue (14 dage) og AI-referral-tjek i GA4 (perplexity/gemini) som ledende indikator.
