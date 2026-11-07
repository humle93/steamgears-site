# HTML- og scriptbudget pr. skabelon (S2.7) — målt 7/11 2026

S2.7 blev åbnet 6/11 på en enkelt måling af forsiden (215.638 B). Denne måling dækker otte
skabeloner, viser hvad der faktisk fylder, og dokumenterer det første indgreb: **store inline
`<style>`-blokke er flyttet til cachede filer**, så HTML'en på indholdsskabelonerne er faldet
med 43–54 %. Budgettet er **ikke** nået på alle tre kriterier — det kræver ejerbeslutninger, og
de står nederst med tal.

## Budgettet (fra roadmap S2.7)

| Kriterium | Grænse |
| --- | --- |
| HTML pr. side | < 100 KB |
| `<script>`-tags pr. side | < 20 |
| Største inline-blok (CSS eller JS) | ≤ 4 KB |

## Metode

- `python3 tools/payload_audit.py` — nyt værktøj, der henter hver skabelon med neutral
  `Mozilla/5.0`-UA (ingen credential) og tæller HTML-bytes, `<script>`-tags (eksterne/inline),
  `<style>`-blokke, deres samlede bytes, den største enkelte blok og `link rel=stylesheet`.
  `--json` gemmer målingen, så før/efter kan sammenlignes 1:1.
- Rådatanne ligger i [`../../reports/payload-2026-11-07.json`](../../reports/payload-2026-11-07.json)
  (før) og [`../../reports/payload-2026-11-07-efter.json`](../../reports/payload-2026-11-07-efter.json) (efter).
- Målt med `curl`/`urllib` fra sandkassen, ikke Lighthouse. Tallene er sammenlignelige, ikke
  Core Web Vitals.
- **Soft-404-fælden:** en ukendt sti som `/denne-side-findes-ikke-4f2a/` svarer **200** med
  indhold, så 404-skabelonen kan ikke måles isoleret. Den er derfor udeladt, ikke glemt.

## Før — 8 skabeloner, alle over budget

| Skabelon (sti) | HTML | `<script>` | inline CSS | største blok |
| --- | --- | --- | --- | --- |
| forside (`/`) | 255.154 B | 51 | 155.734 B | 45.052 B |
| arkiv shop (`/shop/`) | 420.097 B | 54 | 164.957 B | 45.450 B |
| produkt (`/product/astrolabe-planisphere-tee/`) | 469.961 B | 105 | 163.060 B | 45.587 B |
| indlæg (`/steampunk-corset-guide/`) | 272.119 B | 51 | 161.277 B | 45.798 B |
| side (`/faq/`) | 241.371 B | 51 | 148.336 B | 45.052 B |
| kategori (`/product-category/atelier/`) | 286.639 B | 54 | 154.396 B | 45.052 B |
| søgning (`/?s=steampunk`) | 266.477 B | 48 | 159.170 B | 46.116 B |
| kurv (`/cart/`) | 476.407 B | 120 | 147.986 B | 45.132 B |

Fælles for alle: **148–165 KB inline CSS** — mere end selve markup'en — og den samme CSS om og
om igen, fordi den printes i hver enkelt svar.

## Hvor CSS'en boede

Filsøgning og `wp_options`-søgning gav intet; kilden var **indhold**, ikke kode:

| Blok | Bytes | Bor i |
| --- | --- | --- |
| «DARK CLOCKWORK THEATRE v3» (temaets CSS) | 37.151 | `wp_template_part` **12** (`Header`) — en `<style>`-blok i template-parten, derfor på hver side |
| «CLOCKWORK PUNK x ATELIER» + «Clockwise» | 11.031 | `custom-css-js` **945** (Simple Custom CSS and JS, post type `custom-css-js`, ikke `sccss`) |
| WordPress' global styles | 45.052 | `wp_enqueue_global_styles()` i kernen — printes inline pr. design |
| Blok-CSS (social links, customer account, block library m.fl.) | ~62.000 | kernens `wp_add_inline_style()` pr. blok |

## Indgrebet — inline CSS til cachede filer

Et mu-plugin, [`wordpress/mu-plugins/sga-inline-css.php`](../../wordpress/mu-plugins/sga-inline-css.php),
buffers på `template_redirect` og bytter hver `<style>`-blok **≥ 4 KB** ud med et
`<link rel="stylesheet">` til en fil i `wp-content/uploads/sga-css/`. Filnavnet er et sha1 af
indholdet, så ændret CSS giver ny fil og ny URL — ingen manuel cache-rydning, og gammel CSS kan
ikke hænge fast i browseren.

Bevidste valg:

- **Indholdet ændres ikke.** Samme bytes, samme rækkefølge i dokumentet, samme `id` og `media`
  på det nye `<link>`, så CSS-in-JS og cascaden opfører sig som før.
- **Kun blokke ≥ 4 KB.** De små blokke (128–3.455 B) bliver stående; det er dem der giver
  betinget CSS pr. blok, og de koster ikke noget at sende med.
- **Fejler filsystemet, bliver blokken stående inline.** `file_put_contents` med `LOCK_EX`, og
  en side kan ikke blive ustylet af et skriveproblem.
- **Ingen admin, feeds, AJAX eller REST.** Kun frontend-svar bliver omskrevet.
- 11 filer blev skrevet ved første sidevisning; efter otte skabeloner er der 21 filer på i alt
  537 KB i `uploads/sga-css/`. De er statiske og caches af browseren på tværs af sider — det er
  hele pointen: 148–165 KB pr. sidevisning bliver til ét kald pr. browser.

### Rollback

Slet `wp-content/mu-plugins/sga-inline-css.php`, og ryd cachen. Så printes CSS'en inline igen
som før, og filerne i `uploads/sga-css/` kan slettes uden risiko.

## Efter — målt samme dag, samme værktøj

| Skabelon | HTML før → efter | Δ | `<script>` | Budget |
| --- | --- | --- | --- | --- |
| forside | 255.154 → **117.697 B** | −54 % | 51 → 51 | ❌ html, scripts |
| arkiv shop | 420.097 → **286.089 B** | −32 % | 54 → 54 | ❌ html, scripts |
| produkt | 469.961 → **337.000 B** | −28 % | 105 → 105 | ❌ html, scripts |
| indlæg | 272.119 → **141.501 B** | −48 % | 51 → 51 | ❌ html, scripts |
| side | 241.371 → **111.208 B** | −54 % | 51 → 51 | ❌ html, scripts |
| kategori | 286.639 → **162.545 B** | −43 % | 54 → 54 | ❌ html, scripts |
| søgning | 266.477 → **135.250 B** | −49 % | 48 → 48 | ❌ html, scripts |
| kurv | 476.407 → **346.164 B** | −27 % | 120 → 120 | ❌ html, scripts |

**Det tredje kriterium er nu opfyldt:** største inline-blok er 3.455–4.064 B, altså under 4 KB
på alle otte skabeloner (før: 45–46 KB). HTML er under 100 KB på ingen skabelon, og
script-tallet er uændret — det er næste afsnit.

## Hvad der stadig ikke er i mål — og hvad det kræver

Efter indgrebet er produktsiden 337.047 B: **30.713 B CSS + 170.781 B script-blokke (heraf
154.571 B inline) + 135.553 B markup**. Kurven har 120 `<script>`-tags, hvoraf 85 er eksterne.
Ingen af delene er inline CSS længere, så det næste loft kræver strukturelle valg:

| Vej til budget | Effekt (målt/anslået) | Hvad det koster |
| --- | --- | --- |
| Woo-blokkenes React-stak af (react, react-dom, element, customer-account, mini-cart, interactivity) | −30 til −40 `<script>`-tags og ~150 KB på produkt/kurv | Kurv og mini-kurv er bygget som blokke; de skal erstattes af klassiske skabeloner først, ellers går checkout i stykker |
| AI Engine-chatbot af på sider uden chat | −2 scripts og ~11 KB (`mwai-chatbot-container` er 7,5 KB inline) | Chat-widgetten forsvinder fra de sider |
| TikTok-pixel + Smash Balloon af | −3 scripts | Ingen TikTok-sporing og intet feed |
| Site Kit Sign-in-with-Google af | −2 scripts (heraf `accounts.google.com/gsi/client`) | Google-login forsvinder fra konto-siden |
| Klassisk produktskabelon i stedet for blok-skabelonen | markup 135 KB → ~40 KB | Temaarbejde, og designsiden skal godkendes |

**Derfor er S2.7 ikke lukket:** de to sidste kriterier (< 100 KB HTML, < 20 scripts) kan ikke nås
på en Woo-butik med blok-kurv, chatbot og tre sporingsintegrationer uden at fjerne funktioner.
Det er ejerens valg, ikke en teknisk detalje — og et forsøg på at nå tallet ved at fjerne
scripts i blinde ville ramme checkout.

## Kontrol og filer

- Alle otte skabeloner svarer **200** og har samme titler som før målingen.
- De nye CSS-filer svarer **200** med `content-type: text/css` (fx `inline-93a72a941d30.css`,
  6.017 B; `inline-5d0f9937a8de.css`, 11.849 B).
- **Visuelt:** forsiden og produktsiden er fotograferet før og efter (fire PNG'er i chatten —
  `artifacts/` er ikke versionsstyret). Header, typografi, farver og produktbillede er identiske;
  kun AI-boblen dukker op efter load i begge målinger.
- Ingen `critical error`/`fatal error` i HTML'en efter indgrebet.

**Filer:** værktøjet [`../../tools/payload_audit.py`](../../tools/payload_audit.py) og mu-pluginnet
[`../../wordpress/mu-plugins/sga-inline-css.php`](../../wordpress/mu-plugins/sga-inline-css.php) (samme fil
ligger på sitet); råmålingerne `reports/payload-2026-11-07.json` (før) og `-efter.json` (efter); den
oprindelige måling 6/11 i [`site-maalinger-2026-11-06.md`](site-maalinger-2026-11-06.md); roadmap-punktet
i [`../roadmap.md`](../roadmap.md) S2.7 og §9 P2.

## Fælder

- **Værtscachen på `/`.** Efter indgrebet svarede `/` stadig den gamle HTML (255.154 B), indtil
  W3TC's page cache blev ryddet (`royal-mcp/w3tc-purge-cache`, `scope: all`). Ryd cachen og mål
  igen: `/?sga=1` går uden om sidecachen, og en uændret byte-størrelse er ikke bevis på fejl.
- **`<link>` kan stå i `<body>`.** Header-template-parten printer sin CSS midt i dokumentet, så
  det nye `<link>` arver placeringen. Browserne anvender det korrekt, men en HTML-validator vil
  klage. At flytte blokken til `<head>` ville ændre rækkefølgen i cascaden — det er ikke gjort.
- **Filer akkumulerer.** Hver CSS-ændring giver en ny sha1-fil; ryd `uploads/sga-css/` for
  filer ældre end fx 30 dage ved lejlighed.
