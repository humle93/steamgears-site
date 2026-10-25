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

## 2026-10-25 ~11:55 — Hurtig vedligeholdelse (manuelt kørt efter opfordring: driftstjek + kurv/checkout-test + cache-purge)
- **Frontend:** homepage + /shop/ live og raske (browser-session). /checkout/ redirecter korrekt til tom-kurv-visning ved tom kurv ✅
- **Kurv/checkout end-to-end (UI-test, ingen ordre afgivet):**
  - Add to cart ✅ ("added to your cart", tæller opdateres)
  - Cart-totals ✅ (subtotal, free shipping, est. total beregnes)
  - Checkout ✅: guest-checkout-form, DK forudvalgt, moms 25% beregnet korrekt ($26.95 → $5.39 moms), Stripe **Card-element renderes** (card number, Visa/MC/Amex/Discover, expiry, CVC, Link) + Direct bank transfer + express (Apple/Google/Amazon Pay)
  - Ordre BLEV IKKE afgivet (Stripe er live; rigtig live-ordre er stadig Henning-godkendt trin)
- **Stripe:** enabled, testmode=no (live) ✅. Ordrer: stadig kun #688 (refunderet testordre) — 0 nye, 0 fejlede
- **Cache-purge (W3TC):** wp-admin login via app-password slog FEJL ("Invalid login details" — app-password virker kun til REST, ikke wp-login-form). Brugte i stedet det dokumenterede touch-PUT-trick: PUT excerpt:"" på pages 42 (forside), 22 (/shop/), 550 (/steampunk-costumes/), 41 (/journal/) → alle 200. Verificeret: live HTML viser article:modified_time = purge-tidspunkt (2026-10-25T11:54:31+01:00) ✅
- **Afvigelser/log:**
  1. Mini-cart-fragment viste "Number of items in the cart: 33" på /shop/ mens kurven faktisk indeholdt 3 varer (cart-siden var konsistent: 2×Airship Armada Engraved + 1×Cabinet of Curiosities). Mistænkt forældet/stale fragment eller dobbeltrender. **Iagttages ved næste tjek** — kosmetisk, men kan forvirre kunder hvis reelt.
  2. wp-login: Juno app-password afvises i login-formen ("Invalid login details"), mens samme Basic-auth virker fint mod wp-json REST. Ingen admin-password på lager → wp-admin UI-purge ikke mulig; touch-PUT-trick brugt. Henning kan evt. oprette et wp-admin-kodeord til Juno-kontoen hvis UI-adgang ønskes.
  3. Konsol: hCaptcha intern EvalError (wasm, ukritisk, påvirker ikke checkout). Stripe-scripts loader normalt.
  4. SeedProd (coming-soon) stadig aktiv — stående note: deaktiver når genopbygningen er afsluttet.
  5. Browserbase-session hang én gang under checkout-load (infra, ikke sitet) — session genstartet.
- **Resultat: Drift og checkout OK. 1 kosmetisk afvigelse under observation (mini-cart-tæller), 1 arbejdsmiljø-afvigelse (ingen wp-admin-adgang).** ✅

## 25/10 2026 — 'Posters & Wall Art'-kategori verificeret (0-produkt-audit fra 20/10 lukket)
- **Fund:** Woo-REST viser term 28 (posters-prints) med 5 publishede produkter — Sky Corsair (719), The Kraken Widow (721), Clockwork Figurehead (724), The Compass Duchess (726), First Mate's Oath (728), alle Steampunk Pirate Posters ("Pirat Piger"). 20/10-audit'en (0 produkter) var taget FØR genopbygningen 23/10; tildelingen var korrekt efter genskabelsen. Ingen fix nødvendig i Woo.
- **Purge (touch-PUT):** PUT excerpt:"" → 200 på produkter 719/721/724/726/728 + pages 42 (forside) og 22 (/shop/). modified-tid = 2026-10-25T12:14–12:15 verificeret via REST.
- **Verificeret live:** /product-category/posters-prints/ viser "Showing all 5 results" med alle 5 pirat-posters; forsidens CTA peger korrekt på kategorien.
- **Note:** Produkt 29 "Airship Blueprint Poster" ligger i kategorien som draft — decide: publish eller slet.
- WAF: raw curl fik 455 (Security Incident) + 429 på kategoriside-fetch; REST-kald via curl virkede fint med 12 s spacing. Browser-session brugt til live-side-verifikation.

## 25/10 — TikTok-lancering (aftalt)
- Henning: "Vi får API senere" → TikTok postes manuelt i appen for nu; Buffer-forbindelse/API udskydes (Buffer gratis-plan understøtter TikTok, men 3-kanals-grænsen er brugt: FB+IG+Pinterest).
- Henning opretter selv TikTok business-konto (2 min: tiktok.com → email-verifikation → Switch to Business Account, gratis, uden CVR). Brugernavn: steamgears (fallback steamgears.eu).
- 5 launch-scripts klar: /library/TikTok/TikTok Launch — First 5 Scripts/ (#1 corset-myter + #2 bracer fra Batch 10, #3-5 fra Veo-promptene). Posting 1/klip/dag, 17-21 DK-tid.
- TikTok data-pakke (Bright Data) bekræftet nede 25/10 i konkurrent-scans (provider-error) — scans kører uden TikTok indtil videre.

## 25/10 — DSers (AliExpress-fulfillment)
- Henning bad om DSers-plugin. Verificeret: DSers Dropshipping Solution er IKKE på wordpress.org — det er en zip-upload (v1.5.6, fra help.dsers.com). Zip hentet: /workspace/downloads/dsers-woo-plugin-1.5.6.zip (3.15 MB, valid).
- Juno-editor-kontoen kan ikke installere plugins (install_plugins kræver admin; admin Humle91 holdes ren) → Henning uploader zip'et i wp-admin (2 min), derefter DSers-menu → Connect → DSers-konto (Basic, gratis, 3 stores, 1 sales channel) → link AliExpress + Chrome extension.
- Pluginets rolle: connection-bridge + shipping-confirmation-mails fra WP. Selve order-flowet kører i DSers-appen.

## 25/10 — Produktbilleder (brille- og bæltesættet) indsat i shop + 4 guides
- **Drive-tjek:** changes-feed (token 41683→41709) + fuldt billed-inventar (200 billeder, ingen nextPage): INGEN nye produktbilleder på Google Drive. Billederne Gemini (nano-banana) genererede 12:38–12:45 til Goggle DIY Starter Kit og Leather Utility Belt & Holster ligger i WP mediebiblioteket: goggle-diy-kit.jpg (770) og utility-belt.jpg (771), 1408×768, tekstfrie, med alt-tekster — og på Juno-CDN (/steamgears-cdn/products/).
- **Shop:** Woo-kladder #765 (Goggle DIY Starter Kit) og #766 (Leather Utility Belt & Holster) har featured image sat. Stadig drafts = afventer Henning-godkendelse (playbook).
- **Guides opdateret (PUT 200, verificeret live via HTML):** post 10 (diy-steampunk-goggles) → goggle-diy-kit.jpg i "The Finished Look"; post 614 (diy-vs-buy) → goggle-diy-kit.jpg i "The Buy Route"; post 757 (under-100) → utility-belt.jpg i Step 4 (Belt Rig); post 8 (how-to-make-a-steampunk-costume) → utility-belt.jpg efter Step 4-accessories-listen.
- Bemærk: Hvis Henning uploader NYE Gemini-billeder til Drive, fanger Drive-watch (hver time, næste 14:00) dem automatisk.

## 25/10 — Plugin Index gennemløb (skeduleret 14:37)
- Requests-tabellen: ingen pending (1 række i alt, health-check, status "koert" 18/10). Intet at køre.
- Live-sync verificeret via WP REST (/wp-json/wp/v2/plugins): 33 plugins live. Alle versioner + status i databasen matcher live (0 diffs; 30 aktive + 3 inaktive: metricool, woocommerce-services, wpconsent).
- 25 "Ikke installeret"-rækker i index er rester fra genopbygningen 23/10 — ikke rørt (kun anmodede ændringer udføres).
- Ingen 429/challenge; 1 REST-kald brugt.
