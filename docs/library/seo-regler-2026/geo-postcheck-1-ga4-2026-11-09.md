# GA4 post-GEO-måling #1 — 27/10–8/11 2026 (trukket 9/11 2026)

**Property:** 559297232 (G-3MYLFKY8MY) · **Vindue:** 27/10–8/11 2026 (13 dage; GEO-rollout var 26/10 → 26/10 selv ligger i baseline-vinduet, ikke her)
**Metode:** GA4 Data API — samme målemetode som baseline: landingPage = de 12 guide-slugs; shop-besøg = screenPageViews på shop|product|cart|checkout i guide-sessions; cart-events = add_to_cart/view_cart/begin_checkout/purchase per guide-landing. Rå responses: /workspace/tmp/ga4_post_out_1109.json + ga4_lp14b_1109.json + ga4_evt_guide_1109.json + ga4_shoppages_1109.json + ga4_src_1109.json.

## Vigtige dataforbehold (fakta, ikke meninger)

1. **Baseline-vinduet (13/10–26/10) var forurenet af spiket 25–26/10** (1.874 + 1.267 sessions, 97% på "/"); de 32 guide-sessions i baseline ligger alle i det normale spænd. Post-vinduet har ingen spik: site-total 214 sessions på 13 dage (16,5/dag vs. 242/dag i baseline-vinduet pga. spiket).
2. Site-total i post-vinduet: 214 sessions (133 på "/" = 62%). Baseline-vinduet: 3.333 sessions (3.075 på "/" = 92%).
3. Fra 2/11 er der ny, stabil trafik: 58 (2/11), 33 (3/11), 41 (4/11), 5 (5/11), 13 (6/11) sessions/dag — kilder: facebook 102, (direct) 40, pinterest 23, google 16, instagram 29 i vinduet.
4. Første API-kørsel (ankeret FULL_REGEXP på landingPage) returnerede kun 1 af de 12 slugs; fallback-kørsel med mid-regex (ga4_lp14b_1109.json) returnerede 4 slugs. De 8 øvrige slugs = 0 sessions i begge kørsler (også bekræftet i den ufiltrerede landingside-liste, ga4_landing_all_1109.json: kun de 4 slugs optræder).
5. Google/organic-sessions på guides i post-vinduet: 2 (goggles 1, how-to-make 1). Baseline-vinduets fordeling er ikke opdelt pr. kilde i baseline-filen.

## Tabel: post-GEO #1 vs. baseline (delta = post minus baseline)

| Landing page | Base sess | Base PV | Base eng | Base ER | Sess (Δ) | PV (Δ) | Eng (Δ) | ER | KeyEvents (Δ) |
|---|---|---|---|---|---|---|---|---|---|
| /how-to-make-a-steampunk-costume | 6 | 8 | 2 | 33% | 2 (-4) | 7 (-1) | 1 (-1) | 50% | 0 (0) |
| /diy-steampunk-goggles | 7 | 7 | 6 | 86% | 6 (-1) | 11 (+4) | 5 (-1) | 83% | 0 (0) |
| /steampunk-cosplay-for-beginners | 15 | 25 | 7 | 47% | 0 (-15) | 0 (-25) | 0 (-7) | — | 0 (0) |
| /steampunk-costume-ideas | 2 | 3 | 1 | 50% | 1 (-1) | 2 (-1) | 1 (0) | 100% | 0 (0) |
| /steampunk-cosplay-on-a-budget | 0 | 0 | 0 | — | 0 (0) | 0 (0) | 0 (0) | — | 0 (0) |
| /diy-vs-buy-steampunk-goggles | 0 | 0 | 0 | — | 0 (0) | 0 (0) | 0 (0) | — | 0 (0) |
| /casual-steampunk-outfits | 2 | 11 | 1 | 50% | 0 (-2) | 0 (-11) | 0 (-1) | — | 0 (0) |
| /steampunk-outfit-ideas-for-women | 0 | 0 | 0 | — | 0 (0) | 0 (0) | 0 (0) | — | 0 (0) |
| /pirate-cosplay-for-beginners | 0 | 0 | 0 | — | 0 (0) | 0 (0) | 0 (0) | — | 0 (0) |
| /how-to-build-a-steampunk-costume-for-under-100 | 0 | 0 | 0 | — | 0 (0) | 0 (0) | 0 (0) | — | 0 (0) |
| /steampunk-halloween-costume-women | 0 | 0 | 0 | — | 1 (+1) | 1 (+1) | 1 (+1) | 100% | 0 (0) |
| /steampunk-vs-dieselpunk | 0 | 0 | 0 | — | 0 (0) | 0 (0) | 0 (0) | — | 0 (0) |
| **I alt** | **32** | **54** | **17** | — | **10 (-22)** | **21 (-33)** | **8 (-9)** | — | **0 (0)** |

## Shop- og konverteringstal

- **Shop/product/cart/checkout pageviews i guide-sessions: 0** (baseline: 3 — alle fra beginners-guiden 13/10–26/10). Kørsel med alle 4 ord-betonede filtre (shop/product/cart/checkout × landingPage=guides) returnerede 0 rækker i hvert enkelt filter.
- **add_to_cart/view_cart/begin_checkout/purchase attribueret til guide-landings: 0** (baseline: 0). Uændret.
- **Sitewide i post-vinduet:** add_to_cart 2 · view_cart 9 · begin_checkout 5 · purchase 1 · view_item 18 (sitewide totals, ikke guide-attribueret; gæster kom fra / og /shop-lignende landings, jf. ga4_landing_all_1109.json).

## AI-referral (opfølging af 27/10-baseline: perplexity 7, chatgpt 0, gemini 0)

- **Perplexity: 0 sessions i 27/10–8/11.**
- **gemini.google.com: 1 session** (landede på "/", 13 pageviews, engaged) — første gemini-source nogensinde i propertyen. Ikke en guide-landing.
- chatgpt/claude/copilot/deepseek/poe/you.com/mistral: 0.
- Kombineret AI-referral: 1 session / 214 site-sessions ≈ 0,47% (baseline: 7/3.333 ≈ 0,21%). n=1 og n=7 er begge under enhver tærskel for trend — kun registreret som fakta.

## Tal i kontekst (fakta)

- Guide-andel af site-sessions: 10/214 ≈ 4,7% i post-vinduet vs. 32/3.333 ≈ 1,0% i baseline-vinduet (begge vinduer domineret af "/"-landinger; andelen påvirkes af spiket i baseline-vinduet).
- Google-sessions på guides i post-vinduet: 2/10 (goggles 1, how-to-make 1); baseline-filen opdeler ikke guide-sessions pr. kilde.
- Hverken add_to_cart, view_cart, begin_checkout, purchase eller view_item_list blev registreret fra nogen af de 12 guide-landings i post-vinduet.
- weekly-performance/2026-11-09.md (samme property, uge 02/11–08/11): 150 sessions sitewide, 0 purchase-events; top-landingsider var produkt- og evergreen-sider, ingen af de 12 GEO-guider i top 4.

## Konklusion (fakta, ingen effekt-vurdering)

- Konverteringshændelser fra guide-trafik: **n=0 i begge vinduer** → GEO-effekt på konvertering kan ikke måles endnu.
- Traffiktal pr. guide er 0–6 sessions pr. 13 dage — stikprøven er for lille til at udlæde GEO-effekt; tal registreret som baseline for måling #2 (16/11).
- Næste måling: 16/11 (14 dage post-GEO) + GSC-check af spørgsmåls-keywords (13–16/11 iflg. geo-optimering-log.md).
