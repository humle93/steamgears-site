# Steamgears + Warpstorm — komplet mangel-liste og udførelsesplan
*Dato: 25/10 2026. Kilder: wiki/fuld projektlog + live-verificering af steamgears.eu samme dag.*

## 1. Kontekst (til ekstern læser)
- steamgears.eu = steampunk cosplay/merch (WooCommerce, 86 produkter: Printify tees/hoodies + AliExpress gear + ny posters-linje), genopbygget fra bunden 23/10 2026 på Simply-hosting (Extendable theme, Rank Math, GA4 via GTM, Stripe live, Complianz).
- Ejeren (Henning, Aabenraa) kører overordnet plan "LEARN → BUILD → SHOP". Andet projekt: warpstorm.online (Warhammer 40K begynder-hub + eget univers), deploy-pakke klar men ikke kørt.

## 2. Live-verificeret 25/10 (råtjek)
- HTTP 200: /, /shop/, /faq/, /steampunk-costumes/, /steampunk-halloween/
- **HTTP 404: /start-here/, /build/, /inspiration/, /shipping/, /returns/** — workshop-planens hub-struktur mangler stadig efter genopbygningen
- Sider: 19 · Guides: 11 udgivne + 1 draft (post 750, women-guide v2, venter godkendelse)
- Produkter: 86 · sitemap_index.xml indeholder nu 6 sitemaps (post/page/product/category/product_cat/author) efter fix 24/10
- Forsidens H1 er stadig "Steampunk Costumes, DIY Guides & Gear" — IKKE planens "Build Your Own Steampunk Story"
- Stripe: gateway i live mode, KYC 100% afklaret (charges/payouts enabled, 0 requirements), ordreliste indeholder kun test-ordre #688 (refunded)

## 3. MANGLER — A: Ting der kræver Henning (login/beslutning/kort)
1. **Rigtig live-ordre** i shoppen (billigste: poster 30×40 $21.90) → verificerer payout, reelt gebyr (test viste 3,7%, live forventes ~1,9%+fast), GA4 purchase-event. Stripe KYC er klart — kun ordren mangler.
2. **AdSense genaktivering** (pub-3051998776166018 deaktiveret 18/9 for non-activation; Google bekræftet genaktivering mulig): adsense.google.com → Genaktivér → tilføj steamgears.eu. Script + ads.txt er allerede live.
3. **Pinterest**: opret 4 godkendte boards (Steampunk DIY Guides · Steampunk Outfit Ideas · Steampunk Posters & Wall Art · Steampunk Pirate Girls) + verify steamgears.eu (meta-tag live). Navne klar i /workspace/library/pinterest-board-plan.md. API-token stadig 401 (Buffer dækker pinning indtil videre).
4. **Rank Math → Titles & Meta → Local Social**: Facebook = facebook.com/steamgears · Twitter = blank · profiles = IG+Pinterest+YouTube · Email = blank (schema viser "Humle91" som email).
5. **GSC**: slet gammel sitemap.xml-submission (sitemap_index.xml er den rigtige, resubmitted 24/10).
6. **Facebook**: slet test-post "Test"; sæt username @steamgears + avatar/cover/bio.
7. **Instagram @steamgears**: opret konto (bio/captions klar).
8. **steamgearseu@gmail.com som arbejdsmail**: ejer på YouTube-kanal @hotsteampunk723 → connect til Buffer; bruger i Search Console; Google Business Profile (Aabenraa); WP Mail SMTP med Gmail app-password.
9. **HubSpot**: sluk Gmail-synk i UI (Settings → Integrations → Email) — ellers fylder CRM op igen med støj. (208 kontakter allerede arkiveret.)
10. **Godkend draft post 750** (women-guide v2, 35 verificerede produktlinks) → jeg udgiver og rydder op i slug.
11. **TikTok**: business account kræver IKKE CVR — opret, så clips kan udkastes (Buffer gratis-plan kan ikke TikTok).
12. **fal.ai**: top-up eller drop (balance låst; billedflow kører på aimlapi/Gemini i stedet).
13. **Afgør**: er boardet steamngears/steampunk-posters-prints din alt-konto eller en copycat?
14. **Nauti-control.com**: har samme ad-injector-script som Clarity-pluginet — er det dit site?
15. **Legal-gennemgang**: læs Terms/Cookie/Delivery/Privacy igennem én gang (standard-templates).

## 4. MANGLER — B: Steamgears (mine opgaver)
**Workshop-plan-struktur (Fase B — verificeret 404 i dag):**
1. /start-here/ hub + 5 undersider (beginner, first costume, budget, diy-or-buy, find-your-style)
2. /build/ hub + 6 undersider (goggles, corsets, jackets, props, accessories, full-outfits)
3. /inspiration/ hub (gallery, outfit-ideas, design-collections, journal)
4. /shipping/ + /returns/ sider (returns = omdøb/301 eksisterende refund_returns)
5. Ny hovednavigation (Start Here · Build · Shop · Inspiration · Info)
6. Forside: H1 "Build Your Own Steampunk Story" + 4 værkstedsstationer + 2 CTA'er

**Indhold (content-plan 24/10, 10 guides — kun #1 + Halloween-hub er lavet):**
7. Corset-guide (prio 16 — SERP 100% retail, 7 corsets i katalog) ← dagens top-prio
8. Vest-styling-guide (prio 14)
9. Flagship-boost: retitle /how-to-make-a-steampunk-costume/ (pos 7.4) med budget-tabel
10. Men's outfit ideas (12 looks) · Couple costumes · Cost-FAQ ($25/$75/$150/$250) · Costume mistakes · Cardboard goggles · Convention checklist · Aesthetic forklaring · Dress-to-Impress (Roblox)
11. Ny keywords i DB: "steampunk boots and spats" + "steampunk hats" (nye i dag)

**Produkter/shop:**
12. Posters: print-DPI-filer (300 DPI, 3543×4724 / 5943×8268) mangler — kun web-opløsning nu; Printify blueprint-setup mangler (posters-ordrer kræver manuel print indtil da)
13. 5 Complete Looks-kollektioner som produktkategorier (Airship Captain, Victorian Inventor, Dark Clockwork, Mechanical Explorer, Beginner Steampunk) + shop-portalens 4 intent-valg
14. Pirat Piger 04 (Compass Duchess): minimale tal i kortet skal renses
15. Design-flow: min. 15 billeder/dag + alle designs tekstfrie (AI-tekst forbydes)

**Teknik/performance (PageSpeed-regression efter genopbygning: mobil 56-68 vs baseline 86; produktside 28-44):**
16. Extendable-theme loader 32 font-familier (~3,9 MB woff2); IBM Plex Sans 7 filer med font-display:block → preload/subset/swap
17. vol3-03-1024x559.png = 1,14 MB på produktsider → komprimer/WebP; related-images 1024px → 600px
18. Woo loader checkout-assets + Stripe UPE på produktsider; ai-engine chatbot.js ~150 KB på shop-sider
19. W3TC JS-minify/defer slået fra efter rebuild (102 script-tags på produktside) → slå til
20. GA4: mangler Event-tags for e-commerce i GTM (GTM-54M3JCPM) — gtag-shim sender dem midlertidigt til G-3MYLFKY8MY (fjern shim efter, ellers dobbelt-tælling); gammel Ads-conversion "Kurv" i GTM bør renses; to GA4-properties (559297232 + 559271833) samler dobbelt — Site Kit-skrivning kræver Henning-OAuth
21. DebugView-verifikation af view_item → purchase (efter live-ordre)
22. **SIKKERHED:** /wp-json/wc/v3/payment_gateways/stripe eksponerer sk_live_ + webhook-secret i klartekst via REST → bloker endpoint/rotér nøgle
23. Valuta-spørgsmål: Woo i USD, Stripe opkræver DKK — beslut pris-visning (F-03)

**Måling/øvrigt:**
24. GSC: sitemap indexed=0 endnu (afventer processing); effekt aflæses ~26-27/10; ugentligt dashboard-refresh mandag 08:30
25. String Locator-plugin kan slettes igen (situationsbestemt værktøj)
26. Kvartalsvis afvigelses-mapping mod planens rutine-liste (januar)

## 5. MANGLER — C: Warpstorm (projekt 2)
1. **Deploy Grimdark-pakken** (klar i /workspace/warpstorm/deploy/: RUNBOOK, deploy.sh, payloads til 7 kategorier + 9 sider + 4 plugins + 17 posts, CSS v1.1) — WAF POST-quarantinen er udløbet
2. 17/34 artikler skrevet → 17 resterende (liste + bekræftelse fra Henning)
3. Billedvej: fal.ai tom balance; aimlapi mangler image-endpoint fra CLI — beslutning: top-up / all_images_ai / andet
4. warpstorm.dk SSL afventer (warpstorm.online har SSL ✅)
5. 35 Printify-designkoncepter indeholder tekst → text-free-regel: Printify-tekstlag i stedet for AI-tekst
6. grimdark-site repo har deploy-filer; .wp-auth-filer må ALDRIG committes

## 6. UDFØRELSESPLAN (prioriteret)
**Nu/i dag (Henning, ~30 min):** A1 live-ordre · A2 AdSense · A3 Pinterest-boards · A10 godkend 750
**I dag/morgen (Juno):** corset-guide (B7) · udgiv 750 ved godkendelse · Pin Pack #2-refill man/tors
**Denne uge (Juno):** Workshop-plan fase B: alle 4 hubs + undersider + nav + forside-H1 (B1-B6) · men's guide (B10) · posters print-DPI (B12) · Complete Looks-kollektioner (B13)
**Denne uge (Henning):** A4-A9 (2-minutters-fixes + arbejdsmail) · A11 TikTok
**Næste uge:** PageSpeed-batch (B16-B19) · GA4 Event-tags + shim-fjernelse (B20-21) · sikkerheds-fix (B22) · resten af content-planen
**Når Steamgears er i vedligeholdelsesmode (fra i dag mulig):** Warpstorm-deploy (C1) → derefter 17 resterende artikler parallelt med Steamgears-rutiner
**Løbende:** daglig drift/checkout-tjek · ugentlig backup+indhold · månedlig GSC/SEO · konkurrent-scans nat · Buffer-kø man/tors · git-snapshot daglig 23:55
