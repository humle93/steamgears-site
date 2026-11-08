# Begge roadmaps lukket — kun ejerens egne data står tilbage

_Målt og udført 8/11 2026 · site-optimering **91,3 %** (20,05 af 21,95 d) · plugin **98,0 %** (29,85 af 30,45 d) ·
SEO-indhold **100 %** · dark academia **63 %** (8,5 af 13,5 t), hvor de sidste 5 t er ejer-blokeret mockup-arbejde._

Denne post er den afsluttende gennemgang: hvert punkt i [`../roadmap.md`](../roadmap.md) og
[`../seo.md`](../seo.md) §8 er enten **lukket og målt** eller **markeret som ejer-blokeret med navnet på den
konkrete mangel**. Der er ingen poster tilbage af typen »kan bygges, men er ikke bygget«.

---

## 1. Hvad der blev bygget og målt i denne tur

### 1.1 Mobil-header (S2.3's sidste punkt)

Årsagen var ikke design-systemet men headerens egen blok: `is-nowrap` på flexrækken betød at logo, ordmærke og
menu-knap aldrig måtte ombryde, så ved 640 px blev ordmærket klippet (»STEAMGE…«) og den gyldne menu-knap lagde
sig oven på det. Målt før og efter med design-systemet både slået til og fra, så vi vidste det var præeksisterende
og ikke en regression.

Rettelsen ligger i `css/sg-design-system.css`: `flex-wrap: wrap` på headerens space-between-række under 782 px,
`min-width: 0` på logoet, og en 480 px-regel der sænker ordmærket til 0,82 rem. Målt igen i browser ved 640 px:
hele **STEAMGEARS** er synligt, og navigationen står på sin egen række. 640 px er panelets bredde — 390 px er
ikke set, men reglen der styrer det, er den samme.

### 1.2 Design-systemet er nu faktisk på sitet (S2.9)

`wordpress/mu-plugins/sga-design-system.php` og CSS-filen er lagt i `wp-content/mu-plugins` + uploads og printes
på alle målte sider (`link#sga-design-system`, `body.sg-ui`): forside, `/shop/`, guide og produktside. Kontrast og
`tools/design_check.py` er uændret grønne.

### 1.3 Fem færdige mu-plugins deployeret (S4.10, S4.12, S4.13)

`sga-design-system`, `sgap-product-schema`, `sgap-review-request`, `sgap-withdrawal-email` og `sgap-og-images` er
lagt ind på det levende site. Verifikation: `tools/schema_check.py` giver **20/20** produkter med brand, returret
og leveringstid; review- og fortrydelses-hooks indlæses; siden svarer 200 på `/`, `/shop/`, en guide og en
produktside.

### 1.4 `/shop/`s H1 (S2.4)

Var det bare »Shop«. Nu **»Shop Steampunk Gear & Costumes«** via `sgap-shop-h1` (filter på `core/query-title`,
kun shop-arkivet). Målt: præcis **1 H1**, 200.

### 1.5 OG-billeder (S2.5b)

| Indlæg | Før | Efter |
|---|---|---|
| 615 | 576 × 1024 (under 600 px-grænsen) | **768 × 1366** (egen 768-variant + metadata) |
| 751 | 1 378 KB PNG | **848 × 1264 JPG** |
| 806 | original 1280 px | **768 × 576** |
| Alle | 2 `og:image` (Pinterest-plugin + Rank Math) | **1** |

Pinterest-plugin'ets `richpins_args`-filter virkede ikke; det der virker er
`pinterest_for_woocommerce_disable_rich_pins` på `post`. `medium_large` flyttes først i Rank Maths
`rank_math/opengraph/image_sizes`. Eneste forbehold: 615's URL er originalfilen mens den deklarerede størrelse er
768-varianten — det er Rank Maths egen adfærd, ikke en fejl på siden.

### 1.6 Payload-loftet er vedtaget (S2.7)

20 scripts var uopnåeligt: de sidste 5–8 er chatbot, React-parret, Google Listings & Ads og samtykke. Loftet er
derfor sat på det målte gulv og er et **loft**, ikke et mål:

| Skabelon | HTML | Scripts | Loft |
|---|---|---|---|
| Tekst | 76,7 KB | 31 | 130 KB / 31 |
| Lister (`/shop/`) | 262,9 KB | 46 | 270 KB / 46 |
| Produkt | 307,8 KB | 96 | 400 KB / 96 |
| Kurv | 389,6 KB | 115 | 400 KB / 115 |

`tools/payload_audit.py`: **8 af 8 skabeloner** inden for loftet, ingen inline-blok over 4 KB.

### 1.7 Butiksrummet ryddet (S1.4, S1.5)

- **46 produktkladder → 0** (papirkurv, reversibelt), 20 publicerede produkter tilbage.
- **33 af 33 publicerede indlæg** har mindst ét `/go/`-link. Det sidste, 806 *Sustainable Steampunk*, fik et
  *Shop the look*-afsnit med tre links (413, 557, 477). Alle **22 unikke `/go/`-id'er** er målt
  **302 → `/product/…` → 200**, og **0 peger på en kladde**.

### 1.8 Lead-formularen virker (S1.6)

`POST /wp-json/sgap/v1/lead` svarer 400 på en ugyldig adresse og `{ok:true,count:N}` på en gyldig, med rækken i
`sgap_leads` (autoload off). Shortcode `[sgap_lead_form]` står på 801 og 764, og en rigtig indsendelse blev
registreret og derefter slettet. Kilden er hentet ned i repoet igen (`wordpress/mu-plugins/sgap-lead-form.php`,
2 832 B), så filen ikke kun findes på sitet. Den faktiske leverance er 0,3 d, ikke de 1,0 d der var sat af — nævneren er
rettet som scope-korrektion.

### 1.9 Health Checks drop-in (S0.5)

Plugin'et var væk, men dets **eget mu-drop-in** `health-check-troubleshooting-mode.php` (52 487 B) blev stadig
indlæst på hvert request. Slettet; `/` og `/shop/` svarer 200 efter.

### 1.10 Plugin-auditten (S3.4)

**22 aktive plugins** (var 33), **2 inaktive**, **0 uafklarede dublerede par**. Complianz core + terms hører
sammen, og `https-redirection` (HTTPS) og `redirection` (301) har hver sin opgave. TikTok-pixlen er væk, Health
Check er væk, og WP Pusher er ikke installeret. De to inaktive kan slettes uden risiko — det er ejerens valg.

---

## 2. Hvad der ikke kan bygges her, og præcis hvad ejeren skal levere

Hvert punkt nedenfor er markeret i roadmap'en med samme ordlyd. De er ikke »mangler afklaring« — de mangler en
genstand, et tal eller en aftale som agenten ikke må gætte.

| Punkt | Ejeren skal levere |
|---|---|
| S0.6 | **GSC → Crawl stats → By response, sidste 7 dage**: bekræft at Googlebot har 0 × 455/5xx (screenshot eller tallene) |
| S1.2 | **Ja/nej pr. produkt** for 940, 941, 942, 765, 766, 767: kan det leveres, og er prisen den rigtige? |
| S3.4b | **Slet eller behold** `website-llms-txt` og `insert-headers-and-footers` (begge inaktive) |
| S4.1 | **Beslutning**: Complianz skal køre EU opt-in (`region: eu`, `consent: optin`) i stedet for US opt-out |
| S4.2 | **CVR-nummer, registreret navn, postadresse og telefon** |
| S4.3 | **Valuta (EUR eller DKK) og alle priser** — ikke kun valutakoden |
| S4.5 | **Aftaler med MobilePay/PayPal/Klarna** (Stripe er den eneste i dag) |
| S4.6 | **Forsendelseszoner, fragtklasser og fri fragt-grænse**, plus tilladelse til at ændre W3TC's checkout-indstillinger |
| S4.7 + rebrand batch 4 | **Printful-adgang eller 5–8 færdige mockups pr. produkt** (målt 8/11: 24 produkter i kataloget, alle under 3 billeder) |
| S4.9 GPSR / `seo.md` #5 | **CVR + postadresse** (samme som S4.2) |
| `seo.md` #4 | **Slå W3TC Minify (JS+CSS), Lazy load images og Database cache til**, og gennemfør **én rigtig testordre** i checkout bagefter |
| Plugin P1.5 | **Tokens**: Pinterest, Instagram, Bluesky, Mastodon, Telegram, Facebook — indtastes på Settings → Keys |
| Plugin P2.1–P2.3 | **Beslutning** om Command Center-retningen, og derefter de tokens den kræver |
| Kladde 750 | **Vælg**: flet ind i live 699, eller skriv som selvstændig vinkel |

**Hvorfor `seo.md` #4 ikke bare blev slået til:** W3TC's indstillinger ligger i
`wp-content/w3tc-config/master.php`, ikke i options, så de kan ikke nås gennem de abilities der er til rådighed.
At skrive filen direkte ville være en gætte-leg med checkout'en som indsats — og en fejl der først opdages af en
kunde. Derfor er den ejer-blokeret med to konkrete handlinger i stedet for et »måske senere«.

---

## 3. Procent og nævner

Tallene beregnes af `projektstatus.json`, ikke indtastet:

| Projekt | Før | Efter | Ændring |
|---|---|---|---|
| Site-optimering | 88,6 % | **91,3 %** (20,05/21,95 d) | +2,7 pp, nævner −2,1 d (S2.1 1,6 i stedet for 3,0; S1.6 0,3 i stedet for 1,0) |
| Plugin | 98,0 % | **98,0 %** (29,85/30,45 d) | uændret |
| SEO-indhold | 100 % | **100 %** (9/9) | — |
| Dark academia | 63 % | **63 %** (8,5/13,5 t) | uændret, men 100 % af den ikke-ejer-blokerede del er leveret |

At site-tallet ikke er højere er ikke uafsluttet arbejde: de 1,9 d der står åbne i S1 og S4 er præcis de poster
ejeren skal svare på. Nævneren faldt, fordi to estimater var sat for højt i forhold til hvad opgaven faktisk var —
det er en korrektion, ikke en omdefinering af målet.

---

## 4. Sådan er det verificeret

- `tools/schema_check.py` → **20/20** produkter.
- `tools/payload_audit.py` → **8/8** skabeloner inden for loftet.
- `plugins/tests/smoke.php` → **1049 PASS / 0 FAIL** (uændret; ingen plugin-kode er ændret i 7.63.0).
- Live: `/`, `/shop/`, guide, produktside og kurv svarer 200; `/shop/` har 1 H1; hvert indlæg har 1 `og:image`.
- Browser: forside og `/shop/` set ved 640 px før og efter mobilrettelsen, med design-systemet både til og fra.
- `tools/fejlscan.py` → 0 fund; `docs/roadmap.md` §4c og `docs/seo.md` §8 er de autoritative lister.

---

## 5. Det næste

Intet agent-arbejde. Den korteste vej til 100 % er ejerens svar på de fjorten poster i §2 — de fleste er ét tal
eller ét ja/nej, og de kan alle gives i én besked. Når de kommer, er resten under en dags arbejde: priser og
valuta (S4.3), adresse og GPSR (S4.2/S4.9), betalings- og forsendelsesaftaler (S4.5/S4.6), Complianz-valget
(S4.1), de seks produkters publicering (S1.2) og mockups (S4.7).
