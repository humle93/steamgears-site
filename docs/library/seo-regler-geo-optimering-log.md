
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
