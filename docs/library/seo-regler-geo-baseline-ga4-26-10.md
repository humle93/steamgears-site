# GA4 baseline — de 12 GEO-opdaterede guider (trukket 26/10 2026)

**Property:** 559297232 (G-3MYLFKY8MY) · **Vindue:** 13/10–26/10 2026 (26/10 er partial day)
**Metode:** GA4 Data API — landingPage = de 12 guide-slugs; shop-besøg = screenPageViews på /shop|product|cart|checkout i sessions med guide som landingPage; cart-events = add_to_cart/view_cart/begin_checkout/purchase samme måde.

## Vigtige dataforbehold (fakta, ikke meninger)
1. **GEO-opdateringen blev rullet 26/10 2026** (runde 1–3 + 798, jf. geo-optimering-log.md) — hele 14-dagesvinduet er dermed **100% baseline**. Der findes ingen post-GEO-periode endnu.
2. **GA4 propertyen har kun data fra 18/10 2026** (første dag med sessions). Der findes derfor ingen tidligere GA4-baseline.
3. Trafik-spike: 25/10 = 1.874 sessions, 26/10 (kl. ~18) = 1.267 — 97%+ lander på "/". Normalniveau før 24/10 var 4–26 sessions/dag. Spiket forurener ethvert gennemsnit i vinduet.

## Baseline-tabel (13/10–26/10)

| Landing page | Sessions | Pageviews | Engaged | ER | Shop/product-visits fra session | Cart/checkout-events | KeyEvents |
|---|---|---|---|---|---|---|---|
| /steampunk-cosplay-for-beginners | 15 | 25 | 7 | 47% | 3 (/shop/ ×2, /product/airship-armada… ×1) | 0 | 0 |
| /diy-steampunk-goggles | 7 | 7 | 6 | 86% | 0 | 0 | 0 |
| /how-to-make-a-steampunk-costume | 6 | 8 | 2 | 33% | 0 | 0 | 0 |
| /casual-steampunk-outfits | 2 | 11 | 1 | 50% | 0 | 0 | 0 |
| /steampunk-costume-ideas | 2 | 3 | 1 | 50% | 0 | 0 | 0 |
| /steampunk-cosplay-on-a-budget | 0 | — | — | — | 0 | 0 | 0 |
| /diy-vs-buy-steampunk-goggles | 0 | — | — | — | 0 | 0 | 0 |
| /steampunk-outfit-ideas-for-women | 0 | — | — | — | 0 | 0 | 0 |
| /pirate-cosplay-for-beginners | 0 | — | — | — | 0 | 0 | 0 |
| /how-to-build-a-steampunk-costume-for-under-100… | 0 | — | — | — | 0 | 0 | 0 |
| /steampunk-halloween-costume-women | 0 | — | — | — | 0 | 0 | 0 |
| /steampunk-vs-dieselpunk | 0 | — | — | — | 0 | 0 | 0 |
| **I alt** | **32** | **54** | **17** | — | **3** | **0** | **0** |

## Tal i kontekst
- Site total i vinduet: ~3.333 session_starts; 3.075 af dem landede på "/". Guide-andel: 32 sessions ≈ 1%.
- Events fra guide-sessions: kun standard events (page_view, scroll, first_visit, user_engagement) + 1 view_item_list på beginners-guiden (shop-row render via GTM). Ingen click/add_to_cart-attribueret til guide-landingssessions.
- GA4 key events konfigureret: purchase, ads_conversion_Kurv_1 (cart), ads_conversion_Betaling_1 (checkout) — oprettet 17/10.

## Opfølgning
- Samme ekstraktion (samme filtre, batch def i /workspace/tmp/ga4_b1.json) genkøres 9/11 og 16/11 mod denne baseline → første reelle post-GEO-måling (7+14 dage).
- GSC-check på spørgsmåls-keywords ~16/11 (jf. geo-optimering-log.md) = AI-citationssignal.
