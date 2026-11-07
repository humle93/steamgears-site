# Trin 0–2 af S2.7 — målt 7/11 2026

Beslutningsgrundlaget i [`payload-loft-beslutning-2026-11-07.md`](payload-loft-beslutning-2026-11-07.md)
pegede på tre indgreb og et loft pr. skabelonklasse. Dette dokument er **udførelsen og den nye måling**:
hvad der blev fjernet, hvad det gav pr. skabelon, og hvor de otte skabeloner stadig ligger i forhold til
loftet. Konklusionen er ikke den forventede: indgrebene virker, men **klasse 1's loft på 20 scripts kan
ikke nås** med de indgreb der var besluttet.

## 1. Hvad blev fjernet

Alt er gjort med to ting: et nyt mu-plugin og én rettelse i header-template-parten.

| Trin | Indgreb | Hvad der forsvandt | Hvordan |
| --- | --- | --- | --- |
| **0** | Emoji-scriptet | `wp-emoji-release.min.js` + inline-loader + emoji-style | `remove_action` på `print_emoji_detection_script`/`print_emoji_styles` i mu-pluginnet |
| **1a** | TikTok for Business' browser-pixel | `ajaxSnippet.js` + `tt4b_ajax_script` + to inline pixel-blokke (4 scripts) | dequeue + præcist output-filter (TikTok registrerer scriptet inde i renderingen, så et tidligt dequeue rammer for sent) |
| **1b** | Kliken ads-pixel | `frontendInjection.js` fra `*.azurefd.net` (1 script-modul) | `wp_dequeue_script_module` + output-filter |
| **1c** | Site Kit *Sign in with Google* | `gsi/client` + `sign-in-with-google-*.js` (2 scripts) | modulet `sign-in-with-google` fjernet fra `googlesitekit_active_modules`; indstillingerne er bevaret |
| **2a** | Headerens blok-mini-kurv og blok-konto | `mini-cart.js`, `customer-account.js`, drawer-markup, importmap og interactivity-data (~9 scripts) | blokkene erstattet af to klassiske links i template-part 12 **plus** filter på `hooked_block_types`, fordi Woo hooker dem ind igen på render-tid |
| **2b** | Woo's kurv-scripts på tekstsider | `blockUI`, `add-to-cart`, `js-cookie`, `woocommerce.min`, `sourcebuster`, `order-attribution` (6 scripts + 2 inline) | dequeue pr. skabelon: kun hvor `is_woocommerce()`, `is_cart()`, `is_checkout()` eller `is_account_page()` |

**Én bevidst undtagelse:** Google Listings & Ads blev **ikke** fjernet. Dets `AW-18518250875`-tag er
den eneste Ads-konvertering tilbage efter S0.9, og en fjernelse ville slukke konverteringssporingen i
Google Ads uden at nogen bad om det. Det holder 2 scripts + 2 inline-blokke på hver side, og det er
grunden til at tektskabelonerne lander på 28 og ikke på 26.

## 2. Målingen — før og efter, samme otte skabeloner

Målt med `tools/payload_audit.py` (neutral UA, cache ryddet med W3TC `scope: all` før hver måling).

| Skabelon | Klasse | HTML før | HTML efter | Δ HTML | Scripts før | Scripts efter | Δ scripts |
| --- | --- | --- | --- | --- | --- | --- | --- |
| forside | 1 — tekst | 117.697 B | **80.307 B** | −32 % | 51 | **28** | −23 |
| side (`/faq/`) | 1 — tekst | 111.208 B | **73.818 B** | −34 % | 51 | **28** | −23 |
| indlæg | 1 — tekst | 141.501 B | **104.230 B** | −26 % | 51 | **28** | −23 |
| søgning | 1 — tekst | 135.067 B | **97.796 B** | −28 % | 48 | **25** | −23 |
| arkiv shop | 2 — lister | 286.089 B | **257.065 B** | −10 % | 54 | **43** | −11 |
| kategori | 2 — lister | 162.545 B | **133.496 B** | −18 % | 54 | **43** | −11 |
| produkt | 3 — transaktion | 337.000 B | **289.569 B** | −14 % | 105 | **94** | −11 |
| kurv | 3 — transaktion | 346.164 B | **336.263 B** | −3 % | 120 | **111** | −9 |

Største inline-blok er stadig 3.455–4.064 B, så **4 KB-kravet er opfyldt på alle otte**.

## 3. Status mod loftet

| Klasse | Loft | Målt nu | Afstand | Status |
| --- | --- | --- | --- | --- |
| 1 — tekst | 130 KB · 20 scripts | 73,8–104,2 KB · 25–28 | 5–8 scripts over | HTML ✓ · scripts ✗ |
| 2 — lister | 200 KB · 30 scripts | 133,5–257,1 KB · 43 | 13 scripts over | HTML ✗ (shop) · scripts ✗ |
| 3 — transaktion | 340 KB · 90 (produkt) / 105 (kurv) | 289,6 KB · 94 og 336,3 KB · 111 | 4 og 6 over | HTML ✓ · scripts ✗ |

**0 af 8 skabeloner opfylder script-loftet.** HTML-loftet er opfyldt på syv af otte — kun
`/shop/` er 57 KB over sit eget loft, fordi siden bærer et produktgrid i blok-markup.

Det er den vigtigste korrektion til beslutningsgrundlaget: det anslog ~26 scripts for klasse 1 efter
trin 0–2, og det anslog `< 20` som nåeligt. Målt er gulvet **25–28**, og de sidste 5–8 scripts er ikke
sporingsstøj men funktion:

- **AI Engine-chatbot** (2 scripts: `chatbot.js`, `highlight.min.js`) — indlæses på alle sider.
- **React + react-dom** (2 scripts) — stadig nødvendige for Woo's kurv-/kasse-blokke, som Woo køer
  globalt via deres delte cart-store.
- **Google Listings & Ads** (2 scripts + 2 inline) — Ads-konverteringen, se §1.
- **Complianz, gtag `GT-PL9L67B4`, temaet og Rank Math-schema** — samtykke, GA4, header og SEO.

## 4. Forslag: loftet sættes efter det målte gulv

Et loft der ikke kan nås, er ikke et loft — det er et ønske. Derfor foreslås loftet sat til den målte
værdi, så en ny funktion skal modsvares af en fjernelse:

| Klasse | Loft (forslag) | Målt nu | Margin |
| --- | --- | --- | --- |
| 1 — tekst | **30 scripts · 105 KB** | 25–28 · 73,8–104,2 KB | 2–5 scripts |
| 2 — lister | **45 scripts · 260 KB** | 43 · 133,5–257,1 KB | 2 scripts |
| 3 — transaktion | **95 (produkt) / 112 (kurv) · 340 KB** | 94 · 111 | 1 script |

Skal `< 20` alligevel nås, er vejen ikke flere dequeue-forsøg men tre beslutninger: chatboten skal
loades ved interaktion (2), GLA skal fjernes eller flyttes til GTM (2 + 2 inline), og de sidste
React-scripts skal væk ved at gøre kurven klassisk (trin 3, 0,75 d). Tilsammen ~22–24 — stadig ikke 20
med Complianz og gtag tilbage.

## 5. Kontrol

- `/cart/`, `/checkout/`, `/my-account/`, `/shop/`, produkt, `/faq/`, indlæg og kategori svarer **200**.
- Produktets købsformular og Store API virker: `GET /wp-json/wc/store/v1/cart` svarer 200 med tom kurv.
- Ingen nye fatale fejl: `wp_get_php_errors` viser samme tre poster som 6/11 og `paused_plugins: []`.
- Headeren har de to nye links (`sg-header-tools`, `sg-header-tool__label`) og **nul** forekomster af
  `wc-block-mini-cart` og `wc-block-customer-account`.
- `/wp-login.php` svarer 455 til en ikke-browser — det er Simplys WAF, ikke ændringen.

## 6. Fælder

- **W3TC serverer den gamle HTML.** Efter hvert indgreb skal cachen ryddes (`w3tc-purge-cache`,
  `scope: all`), ellers måler man sin egen cache. En byte-identisk side er ikke bevis på fiasko.
- **Woo hooker mini-kurven ind igen.** At fjerne blokken fra template-parten er ikke nok: Woo's
  block-hooks sætter den tilbage på render-tid. Uden `hooked_block_types`-filteret forsvandt kun kontoen,
  og mini-kurven blev stående med React som følge.
- **TikTok og Kliken kan ikke dequeues tidligt.** TikTok registrerer sit script inde i renderingen, og
  Kliken er et script-modul udenom script-køen. Derfor det præcise output-filter, som fjerner netop de
  kendte URLs/ids og intet andet.
- **Én 500 undervejs.** Den første request efter mu-pluginnet svarede 500, og den næste 200; der er ingen
  ny post i fejlloggen. Målingen blev derfor gentaget med `Cache-Control: no-cache`, og alle otte
  skabeloner er hentet igen bagefter.
- **`wp_update_post` normaliserer.** Det gemte indhold var ikke byte-identisk med det beregnede
  (`gemt_ligner: false`), men blokkene og linkene var korrekte. Kontrollér markup, ikke kun længden.

## 7. Rollback

| Indgreb | Sådan rulles det tilbage |
| --- | --- |
| Emoji, TikTok, Kliken, Woo-dequeue, mini-kurv-hook | `add_filter( 'sga_payload_trim_enabled', … )` med `false` for det ene indgreb — eller slet `wp-content/mu-plugins/sga-payload-trim.php` |
| Headerens links | backup ligger i `wp-content/novamira-sandbox/s2-7-backup-header-part-12.html` (51.414 B) og kan skrives tilbage til post 12 |
| Site Kit-modulet | backup i `wp-content/novamira-sandbox/s2-7-backup-sitekit-modules.json`; modulet slås til igen i Site Kit → Settings |

## 8. Filer

- Mu-plugin: [`../../wordpress/mu-plugins/sga-payload-trim.php`](../../wordpress/mu-plugins/sga-payload-trim.php)
  (samme fil ligger i `wp-content/mu-plugins/` på sitet).
- Måling: `reports/payload-2026-11-07-efter-trin-0-2.json` (efter) og `reports/payload-2026-11-07-for.json` (før).
- Værktøj: [`../../tools/payload_audit.py`](../../tools/payload_audit.py) — måler nu mod loftet pr. klasse
  og har `--cache-buster`.
- Beslutningsgrundlaget: [`payload-loft-beslutning-2026-11-07.md`](payload-loft-beslutning-2026-11-07.md).
- Roadmap: [`../roadmap.md`](../roadmap.md) S2.7.
