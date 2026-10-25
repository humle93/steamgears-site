## 2026-10-25 07:00 — Daglig drift/checkout-tjek
- **Frontend:** homepage 200 ✅, /shop/ 200 ✅ (curl krævede 429/vent-cyklus pga W3TC WAF, som forventet)
- **WooCommerce:** aktiv, v11.2.1 ✅
- **Stripe Gateway:** aktiv, v11.1.1, **testmode=no (live)** ✅, pk_live_51Tt... — tilbud endnu ikke afgivet (intet KYC/ordre endnu; stående opfølgning)
- **Ordrer seneste 24h:** 0 nye, 0 fejlede ✅
- **Kritiske plugins aktive:** WooCommerce, Stripe, Rank Math 1.0.280, W3TC 2.11.0, GTM Kit 2.22.0, Complianz 7.5.6 ✅ — ingen ukendte nye plugins
- **Bemærkninger (ikke kritisk):**
  - SeedProd "Coming Soon/Maintenance" plugin er aktivt i plugin-listen (siden svarer normalt, så ingen maintenance-mode). Husk at deaktivere det når genopbygningen er afsluttet, så det ikke kan activate maintenance ved uheld.
  - PHP-warning set på wp-login: "Cannot use bool as array in w3-total-cache/Cache_File.php line 237" — W3TC-fejl, kun admin-siden, men tag med til næste W3TC-tjek.
- **Resultat: Alt i orden** ✅

## 2026-10-25 10:30 — Manuelt driftstjek + kurv/checkout-test + W3TC-purge (efter Woo-genaktivering 23/10)
- **Frontend:** homepage 200 ✅, /shop/ 200 ✅, /cart/ 200 ✅ (raw curl OK denne gang — ingen 429/454 ved GET indtil checkout-hentningen, hvor 454 kom som forventet; browser-session brugt i stedet)
- **Kurv/checkout end-to-end (browser-session, gæst):** produktside 619 → ADD TO CART ✅ (cart count 1, View Cart-link) → /cart/ viser produkt m. qty/remove/kuponer + PROCEED TO CHECKOUT ✅ → checkout 200 ✅ m. fuld form (contact, shipping, landeruliste, fri fragt $0, ordreoversigt $26.95 inkl. 25% moms, PLACE ORDER) ✅ — **Stripe Elements loades live på checkout** (js.stripe.com/v3 + elements-iframe + r.stripe.com beacons bekræftet i network-log) ✅
- **OBServation (ikke fejl):** tom kurv på /checkout/ giver 302 → /cart/ ("YOUR CART IS CURRENTLY EMPTY!") — forventet WC Blocks-opførsel, ikke en afvigelse
- **OBServation (ikke fejl):** Stripe-kortets radio-label i checkout renderes som "Payment methods" (gateway-title er korrekt "Credit / Debit Card" i REST) — kosmetisk, tjek ved næste checkout-besøg
- **WooCommerce:** aktiv v11.2.1 ✅, Stripe Gateway aktiv v11.1.1, enabled=true, **testmode=no (live)**, pk_live_51Ttlwg... ✅
- **Ordrer seneste 24h:** 0 nye (kun #688, refunded) ✅ — den rigtige live-ordre (KYC/payout) er STADIG udestående (kræver Henning)
- **Kritiske plugins aktive:** Woo 11.2.1, Stripe 11.1.1, Rank Math 1.0.280, W3TC 2.11.0, GTM Kit 2.22.0, Complianz 7.5.6 ✅
- **W3TC-purge:** "Empty All Caches" kørt via Juno wp-admin-session (Performance → Dashboard) ✅ — success-besked bekræftet; homepage efter purge 200 ✅
- **Stående bemærkninger (uændret):** SeedProd coming-soon fortsat aktivt i plugin-listen (deaktivér efter genopbygning); W3TC license "Warning: Undefined..."-notice i admin-bar (kosmetisk); W3TC PHP-warning på wp-login (Cache_File.php:237) fra 07:00-tjek fortsat noteret
- **Resultat: Alt i orden** ✅ — kurv og checkout fungerer efter Woo-genaktiveringen
