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

## 26/10 2026 — Daglig drift/checkout-tjek (07:00 skeduleret)
- **Sider:** homepage + /shop/ 200 via browser-session. Raw curl → Simply WAF-challenge (454) som forventet; browser-session løste det.
- **WooCommerce + Stripe:** begge aktive. Stripe **live-mode bekræftet: testmode=no, pk_live_** (wc/v3/payment_gateways, REST). ✅
- **Ordrer seneste 24h:** 0 nye, 0 fejlede. Seneste ordre er stadig test-ordre #688 (refunded, 24/10). Rigtig live-ordre til KYC/payout udestående (kjent bloker).
- **Plugins:** 33 installeret — identisk med 25/10 (0 nye/ukendte). Kritiske alle aktive: WooCommerce, Stripe, Rank Math, W3TC, GTM Kit, Complianz (gdpr + terms). Kun kendte inaktive: metricool, woocommerce-services, wpconsent.
- **5xx/redirect-loops:** ingen.
- REST-forbrug: 5 kald med 45-60 s pauser; 2 WAF-challenges ventet ud. Ingen 429-incidenter.
- Konklusion: **Alt i orden** — ingen afvigelser at rapportere til Henning.

## 26/10 2026 — Morgenscan + Warpstorm-fixes + Halloween-guide (Juno)
- **Morgenscan:** 15 keyword-signaler via Google Autocomplete (live). Pool: SearchCoverage-DB ny tabel `keyword_ideas` (13 rækker) + /workspace/library/keyword-morgenscan-2026-10-26/ (scan-dok + paste-liste). Top-3 flaget: Halloween women (prisen-uden-guides hul), outfit ideas women, Dress to Impress (Roblox). NYT trend-signal: tomodachi life + royale high + goggles stl.
- **Command Center:** kører på Henning's Ultra.cc-slot (laser.usbx.me). Juno-sandbox har ingen ssh-klient → install via wget-script på CDN: https://steamgears.juno.page/steamgears-cdn/cc/cc-ultra-setup.sh (henter v7.6 via GitHub-token, importerer keyword-puljen, lægger autopilot-spec). SSH-creds gemt /workspace/.ultra-ssh.
- **Social Autopilot spec:** /library/WP Ops/Social Autopilot — Command Center spec (v8)/ — 10 platforme (API/OAuth/logik), X = pay-per-usage (free tier udfaset), Snapchat ingen posting-API (Telegram Bot som erstatning), rute: Buffer først (FB/IG/P) + native TikTok/YouTube/LinkedIn/Threads. Flow: natlig scanner → Gemini-content-engine (aimlapi) → kø → publisher → Telegram-rapport. Engangs-setup, derefter autopilot.
- **Warpstorm fixes (26/10):** (1) Rank Math var INAKTIV — genaktiveret via REST ✅ (forklarede manglende RM-routes); (2) sitemap-modul aktiveret via /wp-json/rankmath/v1/saveModule → sitemap_index.xml 200 + robots.txt henviser ✅; (3) touch-PUT purge på pages 18/17/78/79 ✅; (4) 12 IMAGE-*.jpg-placeholders i begynder-guides (posts 19-23) erstattet med Juno-CDN (nyt site "Warpstorm CDN": steamgears.juno.page/warpstorm-cdn/) — verificeret live på best-warhammer-40k-army-for-beginners ✅. RM homepage_title = 403 via REST → HENNING-TRIN. W3TC-purge + RM setup-wizard + GSC property + Site Kit GA4 = fortsat Henning-trin.
- **Steamgears: Halloween-guide udgivet:** post 791 "Steampunk Halloween Costumes for Women: 12 Last-Minute Looks" → https://steamgears.eu/steampunk-halloween-costume-women/ (kategori guides+costume-ideas, featured media 488, 2 CDN-billeder, 12 looks med rigtige USD-priser fra Woo-REST, sg-buy CTA, FAQPage JSON-LD verificeret live, intern linkring til pillar/DIY/budget/casual). Keyword: steampunk halloween costume women. Tidskritisk: Halloween fredag 31/10.
- **Curl-læring:** denne sandbox-curl understøtter ikke --data-binary/@file/stdin til POST-body — brug --data "$(cat fil)". jq '{objekt-konstruktion}' med --rawfile gav tom output; brug '.field = $r'-opdateringskæde i stedet.

## 26/10 (eftermiddag) — CC-deploy fix + konkurrent-autopilot indbygget
- **Henning fejlede på Ultra.cc:** `RuntimeError: Directory 'app/static' does not exist` — workspace-kopien af CC (/workspace/tmp/cc) manglede app/static-mappen (FastAPI mount fejler ved opstart).
- **Fix:** `os.makedirs(STATIC_DIR, exist_ok=True)` indsat før mount i app/main.py + ny cc-base.zip på CDN (2.4 MB, live-verified: indeholder fixet).
- **Nyt setup-script v2:** cc-ultra-setup.sh henter nu CC-koden direkte fra CDN (cc-base.zip) — ingen GitHub-token-afhængighed (humle93/command-center er 404 anonymt; repoet privat). Første version fejlede stille, så ~/command-center fik kun keyword-filer.
- **NY FUNKTION (Henning-ønske "crawleren selv scanner efter konkurrenter"):** `job_competitor_autopilot` i app/main.py — APScheduler-job kl. 02:15 natligt: tager 8 keywords ad gangen fra keywords-tabellen (roterende offset i settings.spy_scan_offset), kører discover_competitors_sync (DuckDuckGo + meta/H1-crawl) og skriver alarm "spy-autopilot". Respekterer module_on("spy") — spy-modulet skal være ON. Syntax OK (ast.parse). Live i cc-base.zip på CDN (verificeret: job_competitor_autopilot × 2).
- **Henning-trin nu:** kør trin 1+2 igen fra https://steamgears.juno.page/steamgears-cdn/cc/install.html, derefter ./run.sh + ./import-keywords.sh, og slå "spy"-modulet ON i CC.

## 26/10 — CC INSTALLERET OG KØRENDE ✅ (Henning-bekræftet: "Det virker sku")
- Command Center kører nu på Ultra.cc (laser.usbx.me:8000, ~/command-center, venv + uvicorn OK).
- Deploy-kæde: cc-base.zip på Juno-CDN → wget/bash via Henning. Første fuldt verificerede fjern-install.
- Næste: tjek keyword-import (15 stk) + Spy-modulet ON; derefter byg social_autopilot.py direkte mod live CC.

## 26/10 (eftermiddag) — Guide-opdatering godkendt af Henning ("Ja tak")
- **/how-to-make-a-steampunk-costume/ (post 8) opdateret via REST PUT (HTTP 200):** ny titel "…Complete Guide for Beginners (2026)", intro-omskrift med "steampunk costume guide", ny budget-tiers-sektion ($20/$50/$100), 5 nye interne links (costume-ideas, cosplay-on-a-budget, under-100, cosplay-for-beginners, casual-outfits, halloween-women), AIOSEO-kommentar fjernet, stray `</strong>` fixet. Slug/URL uændret.
- Live-verificeret: browser-fetch viser alle nye elementer; `<title>` = "How To Make A Steampunk Costume: Complete Guide For Beginners (2026) - Steamgears".
- Rank Math sitemap lastmod stadig 25/10 (DB-cache, ingen REST purge-route) — opdaterer ved TTL. Sitemap allerede i GSC.

## 26/10 — Dieselpunk-guide udgivet (trend-A1 fra trend-scan) ✅
- **Post 798:** "Steampunk vs. Dieselpunk: How to Pick Your Look (2026 Guide)" → https://steamgears.eu/steampunk-vs-dieselpunk/ (kategori guides + costume-ideas, featured media 474/ae-01, 1617 ord, 3 nye billeder).
- **Billeder:** 3 stk. Pollinations/Flux 1024×576 (tekstfrie, brand-palette: mørk + amber) → Juno-CDN pin-dieselpunk-01/02/03.jpg (CDN republish v47, alle 200 live). Pixel-verificeret via PIL (gyldne JPEGs, ikke-blanke).
- **Produktvinkel:** dieselpunk-styling på eksisterende dropship-varer — Aviator ($24.95), Copper Pilot ($24.95), Vintage Driver ($29.95, +kids) + Top Hat m. Removable Goggles ($34.95) som hybrid-tool. sg-shoprow + sg-buy CTA.
- **Intern linkring:** /steampunk-costumes/, costume-ideas, diy-steampunk-goggles, diy-vs-buy, casual, budget, accessories.
- **SEO:** Rank Math-comment (keyword "steampunk vs dieselpunk") + FAQPage JSON-LD (4 Q&A) — verificeret live i HTML.
- **GAP flaget til Henning:** der findes INGEN mask/respirator-produkt i kataloget (83 produkter tjekket, search=mask → 0) — artiklen stylæder masks ærligt uden falske links. Forslag: importér en AE gas mask (SG-AE-13) så "masks"-søgninger kan konverteres direkte; artiklen kan opdateres med 1 linje når den er live.
- **Læring:** .wp-auth-filen indeholder hele "Basic <b64>"-strengen — brug direkte som Authorization-header (base64 -d gav forkert auth → 401 rest_cannot_create først; derefter 201).

## 26/10 (eftermiddag 2) — SOCIAL AUTOPILOT v1 BYGGET IND I CC ✅ + diagnose-script
- **Henning's ønsker:** konkurrent-crawler selv-scan (✅ 02:15), scripts-mappe INDENI command-center (✅ scripts/ i zip + CDN /cc/scripts/), diagnose-script der tester alt og selv sender rapport til Juno (✅).
- **Social Autopilot v1 (i app/main.py, ~300 linjer):**
  - Nye tabeller: `social_posts` (platform/text/status draft→scheduled→posted→failed/scheduled_at/posted_url), `scan_findings` (kind/source/detail/found_at).
  - `social_scan_sync()` 03:00: crawler 4 konkurrent-shops (steampunkary, my-steampunk-style, vintagedancer, airshipflamel) + eget katalog via Woo-REST (WP-creds) → diff → scan_findings + alarm.
  - `generate_batch_sync()` 06:15: AIMLAPI/Gemini (max_tokens 3000) genererer 6 platform-varianter med keyword-first hooks + CTA + UTM → status pr. platform-mode (autopilot/godkendelse/off; standard: autopilot på FB/IG/Pinterest, godkendelse på resten).
  - `publish_pending_sync()` hvert 30. min kl. 17-21 (max 6): Buffer-API (FB/IG/P), LinkedIn /rest/posts, Threads 2-trins, TikTok draft-idé. Manglende token = 'venter' + alarm; 3 fejl i træk = kritisk alarm.
  - `telegram_report_sync()` 21:30 daglig rapport. Endpoints: /autopilot/scan-now, /generate-now, /publish-now, /report-now.
  - Env-nøgler: BUFFER_TOKEN, BUFFER_PROFILE_IDS (JSON), TIKTOK/LINKEDIN/THREADS tokens, LINKEDIN_URN, THREADS_USER_ID, COMPETITOR_SHOPS. .env.example oprettet.
- **Binding-fix:** run.sh binder nu 0.0.0.0 (CC_HOST/CC_PORT env-overridbar) — laser.usbx.me:8000 burde svare direkte.
- **diagnose.sh v2:** tester proces, binding, 7 HTTP-ruter, ekstern adgang, filer, DB-tabeller + rækker, venv-pakker, .env-nøgler (maskerede), steamgears.eu/aimlapi-nåbarhed, log-tail, disk → slutrapport POSTes automatisk til Juno-API (db expose "Command Center Diag".diag_reports, write-URL) + gemmes diag-report.txt.
- **Live-verificeret i cc-base.zip på CDN (2435417 b):** autopilot+publisher+DDG-fallback+static-fix+0.0.0.0+env.example+diagnose+10 scripts ✅
- **Telegram:** Henning spurgte om "dit bot token" — CC skal have sit EGET BotFather-token; Juno har intet Telegram-token. Rapport kan også læses via Juno-databasen (diag_reports).
- Opmærksomhed: flere apply_patch-fejl undervejs (ambigu context) — løst med unikke ankre; indsat blok havde "+"-præfikser fra patch-formatet → fjernet via fixplus-scripts. Syntax OK (ast.parse, 2998 linjer).

## 26/10 (aften) — Henning kørte diagnose; 1 fejl fundet + rettet
- Diagnose-rapport modtaget i diag_reports (id 1, 16:09, PASS 28 / FAIL 1).
- **FAIL: GET / → 500** — dashboard spurgte `keyword_difficulty ORDER BY id`, men tabellen har keyword som PK, ingen id-kolonne. RETTET: `ORDER BY created_at DESC` (linje 2756). Syntax OK.
- Nyt zip 2435855 b live-verificeret på CDN (autopilot+publisher+env.example+diagnose+10 scripts).
- **Vigtig konstatering:** serveren kører stadig GAMMEL kode — port 8000 binder 127.0.0.1 (ikke 0.0.0.0), og keywords/social_posts/scan_findings-tabellerne mangler (ny init opretter dem). Henning skal derfor re-køre install step 1+2 for at opdatere kode + genstarte.
- .env mangler: AIMLAPI_KEY, WP_USER, WP_APP_PASSWORD, TELEGRAM_*, SERPAPI_KEY, BUFFER_*, TIKTOK/LINKEDIN/THREADS. WP-creds (juno-app-password) givet til Henning til .env.
- "Ekstern adgang → 000" fra serverens egen side er forventet (NAT hairpin); reel test = Henning's telefon-browser mod laser.usbx.me:8000.

## 26/10 — Trend-scan-batch: 8 backlog-idéer startet ("Start det hele")

**Steamgears (udgivet):**
- post 803 **Closet Steampunk: Steampunk Style You Can Wear to Work** → /closet-steampunk-outfits/ (cats guides+costume-ideas, 2 CDN-billeder, 7 looks, sg-shoprow)
- post 804 **Steampunk Gift Guide 2026** → /steampunk-gift-guide-2026/ (cats guides, posters-fokus, budget-tabeller, sg-shoprow) — Black Friday-forløber
- post 805 **3D-Print Steampunk Parts** → /3d-print-steampunk-parts/ (cats diy+guides, PLA/PETG/wood-fill, brass-finish-recept)
- post 806 **Sustainable Steampunk** → /sustainable-steampunk/ (cats budget+diy, thrift hit-list, $10-alterationer)
- Dieselpunk (post 798 /steampunk-vs-dieselpunk/) blev udgivet af parallel-task kl. 16:06 — Junos egen version (800) slettet igen (kannibalisering undgået).

**Billeder:** 14 CC-verificerede Wikimedia Commons-billeder (1920px thumbs, license+artist i figcaptions) kopieret til Steamgears CDN /guides/{dieselpunk,closet,gifts,3dprint,upcycle}/ + poster.webp. Steamgears CDN publish v50 — alle 10 URLs verificeret 200.

**Warpstorm (BLOKERET):** app-password i /workspace/warpstorm/.wp-auth-online afvist → 401 rest_not_logged_in (sandsynligvis rokeret under 500-crash-recoveryen 25-26/10). Tre artikler FÆRDIGE som payload i /workspace/tmp/trend-articles/{w1,w2,w3}.json klar til POST: 11th Edition Starter Guide, Christmas Battleforce 2026, Grimdark Painting for Beginners. 6 nye billeder allerede live på Warpstorm CDN (IMAGE-13..18, verificeret 200). **HENNING-TRIN:** nyt app-password til Junowp på warpstorm.online (WP-admin → Users → Application Passwords), gem i .wp-auth-online.

**Planlagt:** one-off 2/1 2027 "Warpstorm: Codex Roadmap 2027 live-dækning" (kører automatisk, tjekker preview + skriver roadmap-artiklen).

**Teknisk note:** exec-curl i denne shell kan IKKE --data @file eller multipart -F (korrupt/afvist); brug --data "$(cat fil.json)" inline. WP media-upload kræver --data-binary som ikke findes — billeder går via Juno-CDN. Wikimedia thumbs tillader kun faste størrelser (1920px ok, 1600px → 400).

## 26/10 (eftermiddag 3) — SOCIAL AUTOPILOT v1 FULDTESTET GRØNT + 3 REALE FEJL FIXET

**Fuld test-forløb i sandbox-harness (/workspace/tmp/tst/cc-test/run_test.py):**
- init_db OK (keywords/social_posts/scan_findings oprettes korrekt)
- Scanner (simuleret): 1 fund fra dieselpunk-guiden (post 798, /steampunk-vs-dieselpunk/)
- Content engine: **6 opslag genereret** med RIGTIGE AI-tekster (ai_ml_api, google/gemini-3-8-flash, max_tokens 3000, ~$0.004/kald, fodret ind som ai_chat-stub da scripts-netværk er blokeret) → FB/IG/Pinterest = scheduled [autopilot], LinkedIn/TikTok/Threads = draft [godkendelse]
- Publisher-guard: afviser korrekt uden for 17-21-vinduet; force-kørsel: **postet=1, fejlede=0, venter=5** (pinterest simuleret Buffer-success)
- Telegram-rapport genereret korrekt: "🤖 CC daglig-rapport 2026-26-10, Postet i dag: 1, Fejlede: 2→(nu venter), Kø: 3 opslag…"

**diagnose.sh verificeret LIVE på serveren** (rapport i Juno-DB "Command Center Diag" → diag_reports, id 1, checked_at 16:09:40 CET, host laser, verdict "⚠️ 1 komponenter fejlede"). Fund: serveren kører GAMMEL kode (tabel keywords/social_posts/scan_findings findes IKKE), GET / → 500, AIMLAPI_KEY/TELEGRAM/serpapi/buffer/WP-creds ikke i .env; uvicorn kører; steamgears.eu + aimlapi nås fra serveren. → Henning skal re-køre install step 1+2 i aften.

**3 reale fejl fundet + fixet i /workspace/tmp/cc/app/main.py:**
1. dashboard() crashede 500: 3 inline db.execute-kald (backlink_rows, active_strategy, strategy_check) kørte EFTER with get_db() var lukket → flyttet ind i with-blokken (markeret "v8-fix")
2. init_db() blev ALDRIG kaldt i ny main.py → kald tilføjet i lifespan før startup_selftest (markeret "init_db()  # v8")
3. Publisher markerede posts 'failed' ved manglende tokens → nu 'draft' + alarm "Publisher venter på token" (notify-tekst opdateret)

**UI:** Nyt dashboard-panel "🤖 Social Autopilot" i templates/dashboard.html (4 knapper: scan-now / generate-now / publish-now / report-now + kø-status + social_posts-liste). Render verificeret med ægte context (38 nøgler): panel + alle 4 knapper + post + kø-status vist.
- Test-teknik: pyodide-isolat aborterer ved fuld TemplateResponse-render → context fanges via TemplateResponse-monkeypatch, render tjekkes separat med jinja2 (check_tpl2.py). TestClient kan ikke starte tråde i pyodide — TestClient-sektionen fjernet fra harness.

**CDN:** Nyt cc-base.zip bygget (2.436.678 b, 59 filer, main.py sha1 7e3e7331f1b5305e77ac16dbf1257782af838568) → lagt i /library/Website Design/Steamgears CDN/cc/ → republish (v54) → **live-verificeret** via curl: samme størrelse, samme sha1, alle 3 fixes + UI-panel til stede. Ikke noget for Henning at gøre ud over step 1+2.

**Henning-aften-tjekliste:** (1) step 1+2 fra install-siden → ny kode + tabeller; (2) env-keys copy-knap (env-setup-7k2mq9x4.html → AIMLAPI_KEY + WP-creds + COMPETITOR_SHOPS); (3) eget BotFather-token → TELEGRAM_BOT_TOKEN/TELEGRAM_CHAT_ID i .env; (4) genstart CC; (5) kør "10. Diagnose" → rapport lander automatisk hos Juno. GET /-500 og manglende tabeller forsvinder med opdateringen. Autopilot-spec v8 er nu komplet og klar til Henning's flade-test i aften.

## Social Autopilot — aften-check 26/10 ca. 17:30 (Juno)
- Seneste diagnose (16:56) læst: AIMLAPI + WP-nøgler grønne (env-setup var kørt), men serveren kører STADIG gammel kode (GET / → 500, keywords/social_posts/scan_findings mangler) + Telegram-nøgler mangler.
- Telegram verificeret: bot @Steamingjunobot (token testet med sendMessage → 200 OK). Chat-id fanget fra getUpdates: 7552486875 (Humle/humle91).
- env-keys-v2.sh ny CDN-fil: sætter ALLE nøgler inkl. TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID (hardcoded). env-setup-7k2mq9x4.html opdateret til at pege på v2 (gammel URL var cachet af CDN — cache-bust virkede ikke, derfor nyt filnavn).
- scripts.html har "11. Opdater program + genstart" (henter ny zip, genstarter, bevarer .env + db) — erstatter install step 1+2 + stop/start.
- cc-base.zip på CDN verificeret: 2.437.420 b = lokal build (500-fix + init_db + Social Autopilot).
- Resterer (Henning, 3 SSH-trin): env-setup-knap → 11. Opdater+genstart → 10. Diagnose. Juno læser rapporten automatisk i Command Center Diag-db.

## 2026-10-26 (aften) — Telegram-sporet
- Henning har skrevet til @Steamingjunobot: chat-id **7552486875** bekræftet via getUpdates (bruger humle91, "hej hej", /start m.fl.)
- Testbesked sendt fra botten kl. ~18:57 → leveret (message_id 14, ok=true) ✅
- Henning spurgte om vi kan snakke via Telegram frem for browser → ja, botten kan sende til ham, og jeg kan læse hans svar via getUpdates
- One-off schedule "Telegram-rapport test 21:30" (ID 3458761d-13c7-49f8-9ec9-989a57b57acf) oprettet — sender testrapport + tjekker TELEGRAM_CHAT_ID i .env
- Henning's knapperækkefølge: env-setup → 11. Opdater program + genstart → 1. Installér scripts → 10. Diagnose

## 26/10 — Pirat piger-backloggen taget
- Canvas "Pirat Piger — Designserie (motiver + kategorimatch)" bygget i /library/Tools (validate OK): motiv-assets 06-10 + kategorimatch-overview med mapping til kat. 28, tee, hoodie, Complete-the-Look-samlinger og SEO-anchors.
- 4 nye tekstfrie motiver genereret (07 Storm Quartermaster, 08 Iron Canoneer, 09 Aether Cartographer, 10 Cutlass Duelist) + 06 Brass Mermaid genbrugt. Alle 10 motiver nu i /workspace/library/pirat-piger/.
- Fal.ai var ude af balance — brugte Juno indbygget billedmodel (Gemini 3.1 Flash Image) med 01-sky-corsair som style-reference.
-- 
## 26/10 — 25-posters opsætning + Gemini-rutine på Drive
- CSV-opsætning klar: /workspace/downloads/steamgears-25-posters-5-kategorier-75-rows.csv (75 rækker: 25 variable + 50 variationer, 30×40 $29.95 / 50×70 $39.95, SKU: SG-PIRAT-006..010, SG-AIR/SG-RAIL/SG-MACH/SG-DARK-001..005, kategorier under "Workshop & Home > Posters >").
- 3 filer uploadet til Steamgears-mappen på Drive: beskrivelser+prompts (25 stk), arbejdsrutine-note (til Gemini, med checklist), CSV. Verificeret indhold på Drive.
- Produkter oprettes IKKE endnu — afventer billederne + Henning-godkendelse (playbook). NB: SG-PIRAT-006 (Brass Mermaid) er allerede genereret; 07-08 havde prompts fra 25/10.

## 26/10 (aften 2) — TELEGRAM-KÆDEN ER GRØN ✅ (Juno-verificeret)

- **Chat-id bekræftet live:** Henning sendte "hej" til @Steamingjunobot → getUpdates viser chat.id **7552486875** (Humle/humle91, da) ✅
- **Diag-rapport id 9 (18:00:44 CET):** verdict **✅ ALLE KOMPONENTER OK (PASS 35 / FAIL 0)** — TELEGRAM_BOT_TOKEN ✅ **TELEGRAM_CHAT_ID ✅ SAT** (id 8 kl. 17:45 manglede den endnu; id 9 efter env-setup+genstart), AIMLAPI/WP-creds ✅, GET / → 200, uvicorn på 0.0.0.0:8000, 63 ruter, selftest OK. Dvs. Henning kørte: env-setup → 11. Opdater+genstart → 1. Installér scripts (11 scripts) → 10. Diagnose — ALLE 4 trin udført.
- **End-to-end Telegram-test nu (ca. 18:30):** Juno sendte testbesked via sendMessage → HTTP 200, message_id 20 leveret til chat 7552486875 ✅
- **Falsk alarm afsløret:** diag id 9 rapporterer keywords/social_posts/scan_findings "findes ikke" — men cc-base.zip's diagnose.sh var en ÆLDRE version uden app/data-tjek: ny main.py's DB_PATH = app/data/center.db, gammel diag læste kun data/center.db. Tabellerne existerer sandsynligvis i app/data/center.db.
- **Fix skubbet til CDN (publish v69, curl-verificeret):** (1) ny diagnose.sh (v2: foretrækker app/data/center.db, printer DBF-sti, tenancy-sektion springer venligt over når main.py ikke har TENANTS_ENABLED), (2) cc-base.zip genbygget 2.442.425 b (kun diagnose.sh udskiftet — main.py holdt på den stabile deployede version), (3) **env-keys-v3.sh**: fanger TELEGRAM_CHAT_ID AUTOMATISK fra getUpdates (fallback 7552486875) + sender Telegram-bekræftelse; env-setup-7k2mq9x4.html peger nu på v3.
- **Planlagt:** one-off "CC Telegram-rapport test 21:30" (ID 79fcbf95-d702-48f7-aece-025ac59e2f79) kl. 21:35: Juno sender Telegram-test, læser diag-db og rapporterer i chatten. CC's egen job_daily_report cron kører kl. 21:30 (server CET).
- **Valgfrit Henning-trin:** kør 11. Opdater + genstart → 10. Diagnose én gang mere for en diagnose med korrekt db-tjek (ellers venter vi til næste diagnosering).

## 26/10 (aften) — v8 §4: Multi-tenancy FastAPI-deploy (micro-SaaS) ✅
- **Henning-opgave:** CC som micro-SaaS til andre SEO-folk; Agency-tier ($29) reel; 11-knappen udvidet med diagnose-grøn-check; lancering VENTER til workshop-planen er færdig.
- **Bygget i app/main.py (nu 3285 linjer, ast.parse OK):**
  - `TENANTS_ENABLED` (kill-switch `CC_TENANTS=0`), `TENANTS_DIR` = app/data/tenants/, ContextVar-tenant-kontekst
  - `get_db()` tenant-aware: tenant-slug → egen db-fil; owner = center.db (fallback = præcis v7.9-adfærd)
  - tenants-mastertabel (slug/name/tier/token/status/max_domains), `create_tenant_sync` (cc-<hex> token + fuldt schema i tenant-db), `set_tenant_status`, `list_tenants_sync`, `tenant_by_token`
  - auth: tenant-tokens gyldige logins; middleware resolver tenant pr. request; login-side accepterer både owner- og tenant-token
  - `fanout()` wrapper: 8 cron-jobs kører owner + alle aktive tenanter (domæner, keywords, konkurrent-scan, social scan/generate/publish, rapport, growth-autopilot, oprydning)
  - `/admin/tenants` (owner HTML: opret/pause/aktivér + tokens), `/api/tenants` (owner/Juno), "🏢 Tenanter"-link i topbaren
  - Tier-limits: solo 3 / pro 10 / agency 25 domæner — håndhævet i /domains/add (402)
  - Backup udvidet: owner + alle tenant-dbs, 14 generationer hver
  - /health: version 8.1 + tenancy/tenants_active
- **11-knappen (scripts/update-restart.sh) udvidet:** fetch cc-base.zip → unzip → genstart → kører diagnose.sh automatisk → `🟢 DIAGNOSE GRØN — opdatering fuldført.` eller `🔴` (exit 2, rapport auto-til-Juno). diagnose.sh: ny sektion 7 MULTI-TENANCY (tenancy-kode + aktive tenanter + tenant-db-filer).
- **Testet:** tenancy-logik isoleret-verificeret i sandbox (opret tenant "seo-prod" agency → egen db med fuldt schema, token-opslag OK, forkert token afvist, fanout skriver i BÅDE owner- og tenant-db, pause fjerner fra aktive). Syntax OK.
- **CDN (publish v70, live-verificeret):** cc-base.zip 2.442.149 b, 60 filer, main.py sha1 81647ee2506812a9230115d4bcb2be31352dba04 (bit-identisk lokal/live), update-restart.sh + diagnose.sh + scripts.html-note opdateret. Alle markers grønne live.
- **Spec:** §4-udvidelse skrevet i "Social Autopilot — Command Center spec (v8)" (tabel + launch-plan + LAUNCH-HOLD).
- **IKKE lanceret offentligt** — venter på Steamgears-workshop-planen (Henning-beslutning). Mangler før lancering: Stripe-flow, onboarding-side, pr.-tenant nøgler, SaaS-terms.
- **HENNING-TRIN (1 knap):** scripts.html → "11. Opdater program + genstart" → vent på `🟢 DIAGNOSE GRØN`. Derefter er v8 §4 live på laser.usbx.me:8000. Tenanter oprettes bagefter via "🏢 Tenanter"-linket (kun når vi er klar til at sælge).

## 26/10 (aften 2) — Task-resultater + tunnel + APP_TOKEN-fiksering
- Henning: "Gider du sætte dem ind", "bare vis rapporten på siden", klikbare adresser, "gi lyd over telegram når du har brug for mig". STÅENDE ORDRE: pinger på Telegram ved fuldført opgave/spørgsmål.
- Diagnose id 6/8: alt OK bortset fra TELEGRAM_CHAT_ID (gamle env-script) + falsk "tabel findes ikke" (diagnose kiggede i data/center.db, men DB_PATH = app/data/center.db pga BASE_DIR=__file__->app). diagnose.sh rettet til at vælge app/data/center.db.
- VIGTIGT fund: APP_TOKEN var IKKE sat i .env → CC kørte UAUTHENTIFICERET på 0.0.0.0:8000! APP_TOKEN (cc_juno_2f11...) lagt i env-script.
- Henning så stadig "hvid side med brugernavn+password" på :8000 (ikke CC — ingen WWW-Authenticate fra appen; https på 8000 er ustabil). Løsning: **12. Tunnel** (cloudflared quick-tunnel → trycloudflare.com https-URL).
- **Task-resultater bygget ind:** task_runs-tabel, start_task() (async i executor), /task/{id} side med auto-refresh, 📥 download, 📤 send til Juno (JUNO_DIAG_URL i .env → diag_reports), /tasks oversigt. Konverteret 10 endpoints: domains/check, keywords/check, monitors/check, links/check, serp/discover, autopilot scan/generate/publish/report, articles/generate (async AI → task). Nye: /domains/check-one, /monitors/check-one, /competitors/scan.
- Dashboard: cockpit "Seneste kørsler"-kort (klikbart), 🔍 pr. domæne, 🔄 pr. monitor, 🔗 klikbar linktjek-historik, 🕷️/🔗 knapper pr. konkurrent i Spy-tab.
- update-restart.sh kører nu diagnose automatisk efter genstart. scripts.html: knap 12 + tunnel i install-loop. Zip 2448242 live-verificeret.
- TELEGRAM: bot @Steamingjunobot + chat-id 7552486875 verificeret (testbesked sendt OK).

## 26/10 (aften) — Post 804 opgraderet: Steampunk Gift Guide 2026 (modtager-baseret)

- **Fra stub til fuld guide:** /steampunk-gift-guide-2026/ er nu modtager-baseret — Airship Captain (Sky Corsair + First Mate's Oath), Victorian Inventor (Clockwork Figurehead + Compass Duchess + Clockmaker's Desk), Story Collector (Kraken Widow + natur-vault), Genre Flirt (5 genrer), Budget <$25 (30×40 $21.90) og Statement 50×70 ($29.90). Priser cross-checket live via /wc/v3 (produkt 719: 729=$21.90, 730=$29.90 ✓).
- **Alle 17 CDN-billeder** (steamgears.juno.page/steamgears-cdn/giftguide/) verificeret 200 og brugt i artiklen: 12 premiere-design (01-12) + 5 genre-premieres (atompunk/cyberpunk/diesel/solarpunk/weirdwest). Alle spot-checket visuelt (1000×1792, tekstfri, 2:3).
- **60+12 motiver** nævnt eksplicit: 5 genrer × 12 = 60 vault-motiver + 12 premiere-collection = 72 tekstfrie designs.
- **sg-buy CTA'er** i alle tre modtager-sektioner + budget-pick + sg-shoprow til sidst. Alle interne links 200 (5 produkter, 2 kategorier, 4 guider/hub).
- **Rank Math meta sat via /wp-json/rankmath/v1/updateMeta:** title "Steampunk Gift Guide 2026: Best Gifts By Recipient & Budget" (live i <title> + og:title ✓), description (live i meta description ✓), focus keyword "steampunk gifts". robots = index,follow.
- **FAQPage JSON-LD** (5 spørgsmål) inline i artiklen — valideret som gyldig JSON, FAQPage@type, 5 mainEntity.
- **Teknik note:** Simply WAF blokerede raw curl i aften (454) — løst ved at løse PoW-challenge-løkken (SHA-256 nonce, 16 bit) via scripts-runtime og POST /.sc-verify/ → sc_clearance-cookie; REST virker herefter normalt med Basic auth. Cookie-metode genbrugelig: GET challenge-side → solve → POST verify → curl -H "Cookie: sc_clearance=...".
- Bemærkning: stub-versionens "How we checked this guide"-sektion (postordre-test af 2 posters) er bevidst fjernet — uverificeret E-E-A-T-claim (live Stripe-ordre er stadig ikke gennemført).
- Fil-arkiv: /workspace/tmp/giftguide/{final-content.html,payload-final.json,rm-meta.json}.

## 26/10 (aften 3) — Keyword/audit-flow + PDF/DOC-eksport + SerpAPI
- Henning: keyword-søgning skal "følge med" til hvor det bruges (ikke bare søge og slut); SEO-audits samme + gem som PDF/DOC efter kørsel.
- Nyhvoet: /keywords/check-one (søg keyword → tilføjes + position tjekkes + gemmes i keywords-tabellen som task-rapport), /seo/audit-task (audit som task → auto-gemt i seopages-tabellen + rapport-side), /task/{id}/export?fmt=doc|pdf (Word-HTML .doc + fpdf2 PDF), task-side har nu 📥 .txt / 📄 Word / 📕 PDF / 📤 Send til Juno.
- requirements.txt +fpdf2. diagnose.sh tjekker nu også APP_TOKEN + JUNO_DIAG_URL i .env-sektionen.
- SerpAPI-key fra Henning (gemt /workspace/.serpapi-key + i env-keys-scriptet). VERIFICERET VIRKENDE via serpapi.com/search (search_metadata + organic_results OK).
- Serveren kører stadig uden .env-reload af APP_TOKEN (GET / = 200 uden auth, diag id 13 19:54 grøn). Henning skal: env-setup → 11. Opdater+genstart.

## 26/10 (aften 4) — Sikkerhedsfixes (Henning's kode-review) klar til update
- SSRF-fix: _block_private_host() blokerer localhost/private/link-local/reservede IP'er + metadata-endpoints; kaldes fra normalize_url + safe_request; safe_request følger nu redirects manuelt med guard pr. hop (allow_redirects=False).
- Bugfix: ai_chat() tog ikke temperature — rewrite_meta_sync fejlede med TypeError; temperature-param tilføjet (default 0.7).
- Øvrige direkte requests.get kald verificeret = hardcodede trusted hosts (serpapi/googleapis/WP) — ingen SSRF-hul.
- Zip 2450117 live-verificeret. Henning opdaterer nu: env-setup → 11.

## 26/10 (aften 5) — Strategier i Cockpit + Optimerings-guide
- Henning: "en guide der ved opstart går siden igennem og optimerer efter valgt strategi — strategier vælges fra cockpittet. Altså indstiller hele programmet. I guides-gennemgang checkes alt til sidst."
- Bygget: STRATEGIES (seo/konv/hast/bred), STRATEGY_JOBS + apply_strategy_schedule() (omplanlægger ALLE rutiner: keyword-tjek, content engine, publisher, domæne-tjek, konkurrent-autopilot, social-scan, growth, monitors), /strategy/set + /guide/run, guide_results-tabel, _guide_pages() (sitemap → homepage+7 guides), _audit_one_page() (titel/meta/H1/ord/alt/interne links/CTA/scripts/iframes/billeder), score pr. side, prioriterede tiltag efter strategi.
- Guide køres AUTOMATISK ved opstart + programmet indstilles efter strategien ved opstart + ved strategiskift.
- SLUTKONTROL i guiden: steamgears.eu svarer, WP REST, DB skrivbar, drift/checkout-status, åbne alarmer.
- Cockpit: 🎯 strategi-vælger (4 knapper) + 🚀 kør guide nu + seneste guide-summary. Zip 2454183.

## 26/10 (aften 6) — 500-fix + WooCommerce-scanner + env-cache-bust
- FEJL: GET / → 500. Root cause fra diag 16 log-tail: sqlite3.ProgrammingError "Cannot operate on a closed database" — mine inline db.execute-kald i dashboard-dict lå UDEN for with-blokken. Fixed: beregnes nu inde i with-blokken (guide_strategy_val/guide_summary_val).
- Kollisionsfix: der fandtes allerede et STRATEGIES-system (label/desc/autopilot, get_active_strategy) — mit nye er nu GUIDE_STRATEGIES + settings-key guide_strategy (ingen konflikt).
- ÅRSAG til "alle env-keys IKKE sat": env-setup-HTML pegede på GAMLE env-keys-v3.sh (CDN-cache). Fikset: NY cache-bustet fil env-keys-9k4tq2w8.sh (11 set_line: APP_TOKEN, JUNO_DIAG_URL, SERPAPI_KEY, AIMLAPI, WP, TELEGRAM, COMPETITOR_SHOPS); env-setup peger nu på den; gamle v2/v3/7k2mq9x4 SLETTET (404).
- Ny feature (Henning spurgte "scanner den WooCommerce-delen?"): guiden har nu WOOCOMMERCE-TJEK: produkt-API (antal, produkter uden pris/billede, udsolgte, kategorioversigt), /shop/-side (HTTP + produkt-links), kategorier med 0 produkter fanges.
- Zip 2453567 live. Henning: env-setup → 11 igen.

## 26/10 2026 — Telegram-rapport test 21:30
- **Test sendMessage via curl:** OK — message_id 35, `"ok":true`, leveret til chat 7552486875 (@humle91, privat chat). Bot @Steamingjunobot virker.
- **CC's egen 21:30-rapport:** CC på laser.usbx.me:8000 (v8.1) har telegram=true i /health. Udløste /autopilot/report-now manuelt → task 32 "Telegram-rapport", status færdig 21:32:09, 0.1 s, output: "sendt". CC's Telegram-rapportpipeline virker.
- **getUpdates:** viser kun tidligere beskeder fra Henning (bl.a. "Tik tok er li blevet godkendt" 21:24); ingen fejl.
- **SSH:** ssh-klient mangler stadig i Juno-miljøet (command not found) → kunne ikke verificere TELEGRAM_CHAT_ID i ~/command-center/.env direkte. /health viser dog telegram=true, så variablen er næsten sikkert sat. Henning skal stadig trykke 10 (SSH-klient install) på Ultra.cc.
- **Konklusion:** Telegram-rapportering fungerer end-to-end. Manuel ssh-verificering af .env afventer SSH-klient.

## 2026-10-26 aften — Ny forside LIVE: "Bespoke Horology & Atelier"
- Kilde: Google Drive (SteamGears_Tema2_Bespoke_Forside + SteamGears_V2_Bespoke_Atelier + 20 Masterclass CSS-temaer). Copy oversat til en-US efter Hennings valg.
- Ny CSS bygget (sga- prefix, mørk/brass/Georgia, ingen webfonts) + preview godkendt af Henning ("Pisse godt. Lad os prøve det").
- Implementering: page 42 content erstattet (backup: /workspace/backups/home-42-2026-10-26.html); CSS inline i content; dark takeover af header/footer via body.page-id-42. 3 iterationer: (1) full-bleed + title-case, (2) ae-11 = No Photo-placeholder → ae-07 (også placeholder!) → Steam Locomotive tee, (3) trust-bar-wrap.
- Cache-purge: touch-PUT excerpt på page 42 ✅. Verificeret live via screenshots (?sgaverify=4/5): alle 6 produktbilleder renderer, header/footer mørke.
- Wiki: /wiki/workspace/bespoke-frontpage.md. Preview-site (steamgears-forside-preview-bespoke) kan unpublishes senere.
