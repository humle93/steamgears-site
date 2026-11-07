# Samtalens vigtigste punkter

*Formålet er context-håndtering: når samtalen nærmer sig grænsen — klientens context meter omkring **95 %** —
skal det vigtigste fra samtalen stå her og være committet, ikke kun i chat-historikken, som forsvinder når
konteksten komprimeres. Filen er repoets hukommelse om **hvorfor** tingene blev gjort, ikke en kopi af
[`projektstatus.md`](projektstatus.md), der har tallene.*

## Sådan bruges filen

- **Ved ~95 % context** (eller før en komprimering): opdatér "Beslutninger og fund" og "Næste skridt" nedenfor
  med det der er kommet til siden sidst, kør `python3 tools/samtalepunkter.py --update`, og commit + push i
  samme tur. Så kan næste pas læse sig ind på under et minut.
- **AUTO-blokken** skrives af [`tools/samtalepunkter.py`](../tools/samtalepunkter.py) ud fra
  [`projektstatus.json`](../projektstatus.json) og git-historikken. Den må ikke håndredigeres.
- Blokken indeholder dagens dato og de seneste commits, så den **skal opdateres, ikke bare tjekkes**: kør
  `--update` og derefter `--check` i samme pas. Et `--check` dagen efter vil med rette melde drift.
- Prosaen uden for markørerne er derimod kun for den der arbejder. Skriv kort og konkret: hvad blev besluttet,
  hvad blev målt, og hvad var fælden.

<!-- AUTO:SAMTALEPUNKTER:START -->
_Opdateret 2026-11-07 af `tools/samtalepunkter.py`. Blokken er maskinelt genereret — ret i `projektstatus.json`, ikke her._

### Status

| Projekt | Status | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** | **63,7 %** | 15,15 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6,95/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| **Steamgears Autopilot — plugin** | **76,1 %** | 21,95 af 28,85 d (P0 4,35/4,7 · P1 10,4/14,4 · P2 4,5/7 · P3 2,7/2,75) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (TEKSTER 8,5/8,5 · MOCKUPS 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (BATCH 0/3 · COPY 4/4 · CORSET 1/1) |
| **Døde internlinks** | **100 %** | 39 af 39 links (FIX 39/39) |

### Åbne blokeringer

- **steamgears.eu — site-optimering** — S2.7's script-loft kan ikke naas uden tre nye beslutninger (chatbot ved interaktion, GLA vaek eller flyttet til GTM, klassisk kurv) — maalt 7/11; S1.1 Herbarium kraever varianter i Printify; S0.4 5 vanity-301 kraever en Redirection-gruppe (ejer). S2.2, S2.7-trin og S2.8 er udfoert 6-7/11, S0.9 7/11.
- **Steamgears Autopilot — plugin** — P1.5 kræver Pinterest-token (401) og Meta-review; P3.1 mangler ejerens labor_rate-beslutning
- **Dark academia-rebrand** — Printify-adgang
- **SEO-indholdsproduktion** — Intet eksternt for de tre resterende guides: copy, FAQ, priser og links ligger i docs/library/guides-2026-11-03.md. De otte /go/-id'er 411/475/481/485/497/567 kunne ikke verificeres fra sandkassen (Simplys WAF giver browser-UA en challenge), så nye produktlinks skal måles fra ejerens side eller efter WAF-hvidlistning

### Næste skridt

- **steamgears.eu — site-optimering** — Vedtag loftet paa det maalte gulv (tekst 30 · lister 45 · produkt 95 · kurv 112) eller bestil trin 3 (klassisk kurv/produkt, 0,75 d); skriv de tre manglende guides fra den verificerede copy; S1.1 Herbarium kraever varianter i Printify; S0.4 kraever en Redirection-gruppe (ejer)
- **Steamgears Autopilot — plugin** — P3.1 labor_rate (ejer); P1.5 er eksternt blokeret (Pinterest-token + Meta-review); P1.15 venter på ejerens MCP-godkendelse i wp-admin; P1.16 er klar, men kostprisen skal sættes på varerne (eller Printful-nøglen gemmes), før værktøjerne kan foreslå priser
- **Dark academia-rebrand** — Batch 4 — mockups (~130 billeder)
- **SEO-indholdsproduktion** — Skriv de tre manglende guides (mens-steampunk-costume-essentials, steampunk-clothing-three-budgets, womens-steampunk-essentials) fra den verificerede copy — corset-guiden er publiceret 6/11
- **Døde internlinks** — Vedligehold: tjek nye indlæg mod live-URL'er

### Seneste commits

- `faed5c9 chore(status): daglig projektstatus 2026-11-07`
- `5bd1ba8 Modul: S2.7 trin 0–2 — emoji, TikTok, Kliken, Google-login og headerens blok-kurv fjernet Før: 62 %  →  Efter: 63,7 %  (Δ +1,7) Grundlag: 15,15 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6,95/7,55 · S3 2,5/3,55 · S4 1,4/5,25)`
- `de3f1f4 chore(status): daglig projektstatus 2026-11-07`
- `da254f3 Modul: P1.15 — MCP-fornyelsen kører nu i pluginnet (7.44.0) Før: 75,2 %  →  Efter: 75,7 %  (Δ +0,5) Grundlag: 21,45 af 28,35 d (P0 4,35/4,7 · P1 9,9/13,9 · P2 4,5/7 · P3 2,7/2,75)`
- `fb787cf chore(status): samtalepunkter opdateret efter CI-vagten`
- `e5ee7e7 Modul: CI-vagt — 14 fixtures og version læst fra plugin-headeren i live_state Før: 74,9 % Efter: 74,9 % Δ: 0 Grundlag: ingen ændring`
<!-- AUTO:SAMTALEPUNKTER:END -->

## Beslutninger og fund (nyeste først)

### 7/11 — S2.7 trin 0–2 udført: 51 → 28 scripts, men loftet på 20 kan ikke nås

- **Fem indgreb, ikke tretten scripts:** emoji-scriptet (2), TikTok-pixelens fire scripts, Kliken-modulet (1),
  Site Kits Google-login (2) og headerens blok-mini-kurv/-konto (~9) er væk. Woo's seks kurv-scripts loades nu kun
  på butik, kurv, kasse og konto.
- **To fælder kostede runder:** TikTok registrerer sit script **inde i renderingen**, og Kliken er et script-modul
  udenom script-køen — et tidligt `wp_dequeue_script` rammer for sent, så det blev et præcist output-filter på de
  kendte URLs/ids. Og Woo **hooker mini-kurven ind igen** efter den er fjernet fra template-parten: uden
  `hooked_block_types`-filteret faldt kun 11 scripts, med det 23.
- **Google Listings & Ads blev bevidst ikke fjernet.** Dets `AW-18518250875` er den eneste Ads-konvertering tilbage
  efter S0.9; at fjerne det ville slukke konverteringssporingen uden at nogen bad om det. Derfor lander teksten på
  28 og ikke 26.
- **Målt:** forside 117.697 → 80.307 B og 51 → 28 scripts, `/faq/` → 73.818, søgning → 97.796, indlæg → 104.230,
  produkt → 289.569 (105 → 94), kurv → 336.263 (120 → 111), `/shop/` → 257.065 (54 → 43), kategori → 133.496.
  HTML-kravet er nu opfyldt på de fire tekstskabeloner; **script-loftet nås ikke på nogen af de otte**.
- **Loftet skal vedtages på det målte gulv (30/45/95/112)** — eller trin 3 (klassisk kurv/produkt, 0,75 d) bestilles.
  De sidste 5–8 scripts er chatbot, React-parret, GLA og Complianz, ikke sporingsstøj.
- **Rollback:** `add_filter( 'sga_payload_trim_enabled', … false … )` pr. indgreb, og backup af header-parten ligger i
  `wp-content/novamira-sandbox/s2-7-backup-header-part-12.html`.
- **Ikke verificeret:** hvordan "Add to cart" opfører sig visuelt nu hvor drawer'en er væk — der er ikke kørt browser-test.


### 7/11 — MCP-fornyelsen er flyttet ind i pluginnet (7.44.0)

- **Beslutning:** Novamira-adgangen holdes i live af sitets egen WP-Cron i stedet for repository-secrets eller
  et loop i en sandkasse. `includes/mcp-auth.php` (`SGAP_MCP_Auth`) fornyer hvert 30. minut, verificerer med
  `initialize` + `Mcp-Session-Id` + `tools/list` og gemmer det roterede refresh-token **før** noget andet.
- **Hvorfor det ikke kan være en konstant:** refresh-tokenet roteres ved hver fornyelse, så det skal skrives
  tilbage til databasen. Et token i `wp-config.php` afvises derfor med en forklaring i stedet for at give
  indtryk af at virke.
- **Synligt for ejeren:** *Settings → MCP access* (client ID + refresh token), `mcp-status` (læsende) og
  `mcp-refresh`, plus en linje i det daglige selvtjek når en fornyelse afvises. Et token der ikke virker bliver
  aldrig meldt som fornyet.
- **Ikke målt mod live endnu:** fornyelsen kræver ejerens godkendelse og de to værdier indtastet i wp-admin.
  20 nye smoke-checks kører begge veje med stubbede HTTP-svar (584 PASS, 0 FAIL).

### 7/11 — S2.7: script-tallet forklaret, loft pr. skabelon foreslået

- **Fordelingen, ikke bare tallet:** forsiden har 23 inline-blokke, 8 kernel-scripts, 8 Woo-scripts, 2 tema-scripts og **13 sporings-scripts** (TikTok 4, Kliken 3, Google Listings & Ads 4, Google Sign-in 2). Produkt (105) og kurv (120) har 27–40 kernel-scripts, fordi begge er blok-skabeloner (`wp-block-woocommerce-product`, `wp-block-woocommerce-cart`, 40 forskellige `wc-block-*`-klasser).
- **Headeren er den skjulte årsag:** `wc-block-mini-cart__button` står i `<header>` og `wc-block-customer-account` 4 steder samme sted, så React, react-dom, element, escape-html, hooks, `mini-cart.js`, `customer-account.js` og `js-cookie` indlæses på **alle** sider (155 `data-wp-*`-direktiver på forsiden). Gulvet er derfor 22–24 scripts med blok-header og ~13 uden.
- **`< 20` kan ikke nås på produkt og kurv** uden at skifte til klassiske skabeloner. Forslaget er derfor tre klasser med hvert sit loft: tekst 130 KB/20 scripts, lister 290 KB/30, transaktion 350 KB/90–105, og `< 20` trækkes eksplicit tilbage for transaktion.
- **Tre spørgsmål står til ejeren** (annoncepixels, header-mini-kurv, klasse 3-loft) i [`library/payload-loft-beslutning-2026-11-07.md`](library/payload-loft-beslutning-2026-11-07.md) §6. Trin 0–2 (0,4 d) venter på dem.
- **Fælde:** `GT-PL9L67B4` og `AW-18518250875` er **ikke** en dublet — det er Google Tag (GA4) og Ads-konvertering. S0.9's dublet var Rank Maths `install_code`.
- **PDF-rendereren** gentager nu tabelhovedet, når en tabel fortsætter over et sidebrud, og forstår `<!-- sidebrud -->`. Begge payload-PDF'er er renderet igen og set side for side.

### 7/11 — S2.8 lukket: 29 af 30 indlæg sender nu et hub-link

Målingen før passet var **9 af 30** publicerede indlæg med et kontekstlink til `/start-here/` eller
`/build/`. Tyve indlæg fik hver **én sætning** i brødteksten — 14 mod `/start-here/` (vælg-og-køb) og 8
mod `/build/` (byg-og-forarbejd) — og nu står der **29 af 30**. Det sidste er indlæg 898, hvis indhold er
ordet `test`; det er en ejerbeslutning, ikke link-arbejde.

**Fælden der kostede en runde:** første indsættelse søgte det sidste `"<p>"` før `<script` — men flere
indlæg har HTML inde i selve JSON-LD-strengen (fx et spørgsmål med `</p>` i teksten), så to links endte
**inde i schema-JSON'en**. Løsningen er at maskere alle `<script>…</script>`-blokke med mellemrum af
samme længde før positionssøgning, så byte-positionerne stadig passer. Hver indsættelse kontrolleres nu
på fem punkter: præcis +1 `<p>`, `script_delta` 0, `div_delta` 0, aldrig inde i en script-blok, og det
gemte indhold byte-identisk med det beregnede.

**Kontrol:** alle 20 sider er hentet igen fra serveren selv — **200, linket i den renderede HTML, ingen
PHP-fejl**. To batche ramte en forbigående **503 fra Simply**; siden svarede 200 igen efter ca. 45 s, og
batchene blev derefter delt op i fem ad gangen med pause.

**Og 7.41.1 er live:** opdateringen blev installeret 6/11 kl. 23:54 UTC, og den udrulles
`includes/site-seo.php` bruger nu `$home` på linje 354–360 — der findes ikke længere en bar `home` i
filen. Den daglige `admin_init`-fejl i wp-admin er dermed lukket i produktion.

Dokument: [`library/hub-links-posts-2026-11-07.md`](library/hub-links-posts-2026-11-07.md).

### 7/11 — S2.7 målt: al inline CSS var den samme CSS, sendt forfra hver gang

- Målt med **`tools/payload_audit.py`** (nyt) på otte skabeloner: 241.371–476.407 B HTML, **148–165 KB inline CSS** og 45–46 KB i den største blok. Forsiden var ikke 215 KB som 6/11, men 255 KB.
- **Kilden var indhold, ikke kode:** temaets 37 KB CSS er en `<style>`-blok i `wp_template_part` 12 (header), «Clockwise»-snippet'en er `custom-css-js` **945** (post type `custom-css-js`, ikke `sccss` — derfor gav `get_posts('sccss')` nul), og 45 KB er kernens `global-styles`. Filsøgning og `wp_options`-søgning gav intet, fordi CSS'en ikke er kode.
- **Indgreb:** `wordpress/mu-plugins/sga-inline-css.php` (samme fil ligger i `wp-content/mu-plugins/`) bytter hver `<style>`-blok ≥ 4 KB ud med `<link>` til en fil i `uploads/sga-css/` med sha1-navn. Fejler skrivningen, bliver blokken stående inline, så en side aldrig kan blive ustylet.
- **Effekt:** HTML −27 til −54 % (forside 255.154 → **117.697 B**, `/faq/` 241.371 → 111.208 B, produkt 469.961 → 337.000 B). Største inline-blok er nu 3.455–4.064 B, så **4 KB-kriteriet er opfyldt**; HTML < 100 KB og < 20 scripts er **ikke**.
- **Hvorfor ikke lukket:** produktsiden er stadig 30.713 B CSS + 170.781 B script-blokke (154.571 B inline) + 135.553 B markup, og `/cart/` har 120 script-tags. At nå < 20 scripts kræver at Woo-blokkenes React-stak, AI Engine-chatbot'en eller TikTok/Site Kit-sporingen fjernes — det er ejerens valg, og det står med tal i [`library/payload-budget-2026-11-07.md`](library/payload-budget-2026-11-07.md) §»Hvad der stadig ikke er i mål«.
- **Fælde:** efter indgrebet svarede `/` stadig den gamle HTML, indtil W3TC's page cache blev ryddet (`royal-mcp/w3tc-purge-cache`, `scope: all`). En uændret byte-størrelse er ikke bevis på fiasko — mål med `/?sga=1` for at se hvad PHP'en leverer.

### 7/11 — S0.9 lukket: det inline GA4-tag var Rank Math, ikke et snippet

- **Fælden først:** en filsøgning i `wp-content` efter `G-3MYLFKY8MY` gav **nul hits** blandt 34.972 filer, og Code Snippets er ikke aktiv. Tag'et bor i en **option**, ikke i en fil — søg i `wp_options` med `LIKE` før tema og snippets ledes igennem.
- Kilden var `rank_math_google_analytic_options`: `measurement_id: G-3MYLFKY8MY` + `install_code: "1"`. `seo-by-rank-math/includes/modules/analytics/class-gtag.php:71-74` returnerer tidligt når flaget er tomt, og det ene flag styrer **både** `google_gtagjs`-scriptet og den inline `gtag("config", …)`.
- Indgrebet: `install_code` → tom streng over Novamira CLI'ens `execute-php`; property-id, `GT-PL9L67B4` og `AW-18518250875` urørt. Den gamle serialiserede værdi blev gemt først, så rollback er ét felt.
- Målt: **0** forekomster i HTML (var 2), og **ét** `g/collect` med `en=page_view` på `/`, `/?nocache=1` og `/shop/` — alle `tid=G-0HC9BLQ88R` og samme `cid` som 6/11. Ads sporer stadig.
- Adgangsvejen var **Novamira CLI'ens device-godkendelse**, ikke `tools/mcp_oauth.py`: CLI'en har sin egen credential i `~/.config/novamira/config.json` (mode 600). `tools/mcp_rest.py --check` svarer stadig »ingen credential« — den vej er ikke rørt.
- Novamira-tokenet udløber efter 3600 s og forsvinder med sandkassen: næste pas skal køre `novamira doctor --json` først og om nødvendigt godkende igen med `--device`.
- Dokumentation: [`library/analytics-dobbelt-2026-11-06.md`](library/analytics-dobbelt-2026-11-06.md).

### 6/11 — hvorfor faldt butikken: vores egen fatale fejl, og nu en log der fanger næste gang

Tidslinjen er nu målt til sekundet: fire plugins skrevet på disken 19:56:11–19:57:04, alle fire
deaktiveret **i samme sekund** kl. 19:57:14 (ét kald med fire plugins — ikke fire uheld), W3TC kl.
19:59:30. WP's egen opdateringsrutine er **udelukket med kilden som bevis**:
`Plugin_Upgrader::deactivate_plugin_before_upgrade()` slår fra *før* installationen og springer helt
over i cron, så mønstret passer hverken på en bulk-opdatering eller en natlig auto-opdatering.
MainWP-tabellerne er fra 23/10, Royal MCP-loggen holder op 2/11 — **hvem** kan ikke aflæses, og det
står der i dokumentet.

**Det vigtigste fund: den fatale fejl var vores egen.** `Undefined constant "home"` i
`site-seo.php:354` kl. 19:55:00, kastet i `admin_init` fra `wp-admin/index.php` — altså en kritisk
fejl på en vilkårlig admin-side, **én gang i døgnet**, fordi `maybe_scan()` sætter sin guard før den
scanner. Rettet til `$home` på fire linjer; ny smoke-check dør med præcis samme fejl hvis den
genindføres (verificeret i en kopi: 168 PASS derefter `Fatal error`, exit 255). Live kører stadig
7.40.1 med fejlen, indtil ejeren klikker *Plugins → Update*.

Logningen er sat op som en **log-only vagt** i `wp-content/novamira-sandbox/steamgears-site-events.php`:
deaktivering, aktivering, opdatering, admin-login, fatale fejl — med bruger, IP, URI og kaldstak — til
`/var/www/steamgears.eu/steamgears-logs/` (0700/0600, uden for webroden). Verificeret med en syntetisk
`do_action('deactivated_plugin', …)`, og `/`, `/shop/`, en produkt-URL og `/wp-login.php` svarer
stadig 200. Filen ligger også i repoet som `library/hjalpefiler/steamgears-site-events.php`.

Dokument: [`library/hvorfor-butikken-faldt-2026-11-06.md`](library/hvorfor-butikken-faldt-2026-11-06.md).

### 6/11 — butikken var nede i 1 t 38 min (den vigtigste hændelse i dag)

Fire butiks-plugins blev deaktiveret i samme batch kl. **19:57:14 UTC**: WooCommerce 11.2.2, Stripe,
Pinterest for WooCommerce og Google for WooCommerce. Konsekvens: `/product/*` og `/product-category/*`
svarede **404**, Store API 404 — mens `/shop/`, `/cart/` og `/checkout/` svarede 200 fra sidecachen. Det er
fælden der skal huskes: **en 200 er ikke et bevis for at butikken virker.** Alle fire er genaktiveret og målt
bagefter (WC 11.2.2, Store API 200, produkt-URL 200 med købsknap, `stripe` + `bacs`, produkt-sitemap 21
URL'er). Årsagen er **ikke fastslået** — `recently_activated` giver sekundet, men der findes ingen
`debug.log`. Tre ting står stadig åbent: `sitemap_index.xml` udelader product og product_cat (kræver et
`rank_math/sitemap/index`-filter, altså en kodeændring), butikken har 20 publicerede produkter mod 39 målt
2/11, og indlæg 898 står som `test` med tre hub-links der lover “10 couple costumes”.

Dokument: [`library/butik-nede-og-genoprettet-2026-11-06.md`](library/butik-nede-og-genoprettet-2026-11-06.md).

To opsætningsrettelser blev lavet i samme pas, begge målt: `/shipping/` og `/returns/` står nu i footerens
Support-kolonne (1 forekomst på `/`, `/shop/`, `/faq/`), og corset-guiden (1394) er kommet ind i hubbens
guide-liste på `/steampunk-costumes/` — den havde **0** indgående interne links. Det tomme testindlæg 789
(`<p>t3</p>`, 0 indgående links) er sat til kladde.

### 6/11 — navigation: ejeren valgte native blokke

S2.3 fortsætter i **Gutenberg/native blocks** (Extendable-bloktema), ikke page builder eller child theme.
Headeren er allerede `wp:navigation-link`-markup i template part 12 med Start Here · Build · Shop (dropdown)
· Gallery · Guides · About · Contact — hub-links er på plads siden 2/11, så det der manglede var footeren og
den interne linkning, ikke topniveauet. Novamiras `gutenberg-*`-evner kræver en browser-finalizer for **nye**
statiske blokke; målrettede rettelser i eksisterende markup kan skrives direkte med `wp_update_post`.

### 6/11 — MCP-adgang: fornyelse, ikke secrets

- **Beslutning:** ingen repository-secrets til Novamira. I stedet fornyes tokenet løbende hvert 58. minut.
  Baggrunden er ejerens valg: secreterne krævede en PAT med Secrets: write, og det blev vurderet som mere
  bøvl end gavn.
- **Målt:** access-token 3600 s, refresh-token **roteres** ved hver fornyelse (det gamle er dødt bagefter).
  Introspection er admin-only, så refresh-tokenets levetid kan ikke læses udefra.
- **Bygget:** [`tools/mcp_keepalive.py`](../tools/mcp_keepalive.py) (forny + verificér med `initialize` +
  `tools/list`) og [`tools/keepalive-loop.sh`](../tools/keepalive-loop.sh) (loop hvert 3480 s, log i
  `.tools/keepalive-loop.log`, pid i `.tools/keepalive-loop.pid`, prøver igen efter 5 min og stopper efter fem
  fejl i træk).
- **Grænsen, sagt højt:** loopet lever kun, mens worktreen kører. Cloud-sandkassen er bundet til chatten, ikke
  en permanent server — bliver den arkiveret, dør processen. De 30 dage kræver enten CI-varianten
  ([`novamira-keepalive.yml`](../.github/workflows/novamira-keepalive.yml), som så kræver de to secrets) eller
  at fornyelsen flyttes ind i pluginnet og kører på sitets egen WP-Cron. Sidstnævnte er den eneste vej uden
  secrets, og den er ikke bygget endnu.
- **Fælder:** godkendelseslinket skal bruge `&` når endepunktet har query, og token-kaldene skal være
  form-encoded (JSON giver `unsupported_grant_type`). `tools/list` kræver `Mcp-Session-Id` fra `initialize`.

### 6/11 — Roadmap: to punkter lukket

- **S2.2 `/shipping/` + `/returns/`** (1396, 1397) er publiceret og målt 200. 301-vejen var umulig, fordi
  Redirection ingen gruppe har; siderne er derfor skrevet opgave-orienteret af **sitets egne publicerede
  politikfakta** (`/delivery/`, `/refund_returns/`, `/terms/`) — ingen nye løfter om tid, pris eller fortrydelse.
  Begge politik-sider linker nu ind til hubberne.
- **`/steampunk-corset-guide/`** (1394) er publiceret: to `/go/`-links (489, 491 — målt 302) i stedet for rå
  `/product/`-hrefs, plus hub-link til `/steampunk-costumes/`. De øvrige otte produktlinks blev **bevidst ikke**
  sat, fordi de ikke kunne måles herfra.
- **Fælde:** Markdown-tabeller konverteres ikke til Gutenberg af `create-post` — de ender som rå `|`-tekst.
  Skriv lister i stedet, eller ret blokken bagefter.
- **Fælde:** Simplys WAF giver browser-UA en *"Checking your browser…"*-challenge og default-UA en side med
  titlen "Page Not Found". Produkt-URL'er uden pæn permalink kan derfor ikke verificeres fra sandkassen — et
  umålt link hører ikke i en publiceret side.

### 6/11 — Repoets egne regler, der koster en runde at genopdage

- **Procent ved hvert pas**, med `Modul / Før / Efter / Δ / Grundlag` — også når svaret ellers er færdigt.
  `projektstatus.json` er sandheden, `projektstatus.md` skrives kun i AUTO-blokke, og et fald i procent
  afvises af `--check` uden `scope_change`.
- **Alt arbejde skal ende på `main`** i samme tur. Den natlige kørsel kl. 02 er et sikkerhedsnet, ikke en
  erstatning — den ser ikke ucommittet arbejde i en sandkasse.
- **Ny viden skal ud som PDF** i `reports/` og stå i [`library/README.md`](library/README.md).
- **Instruktions-gaten:** en turns første værktøjs-batch skal indeholde en læsning af `AGENTS.md`, ellers
  afvises hele batchen.

### 6/11 — Cirla

- Ejerens dokument "Cirla.pdf" er overført til
  [`library/cirla-teknisk-dokumentation-2026-11-06.md`](library/cirla-teknisk-dokumentation-2026-11-06.md).
  Det er en **plan**, ikke en måling: Cirla er endnu ikke bygget, og de fem åbne spørgsmål i dokumentets §8
  står åbne.
- Det der allerede findes i pluginnet og rører ved planen: kvalitetsgaten (P1.6), dublet-værnet (P0.6, anden
  nøgle end `_cc_sync_id`), `/go/`-tracking, og en Pinterest-kanal der er **blokeret** af et manglende token —
  ikke af manglende kode.

## Åbne beslutninger til ejeren

- ~~**Novamira-fornyelse:** CI med to secrets, eller fornyelse i pluginnet via WP-Cron (ingen secrets)?~~
  **Afgjort 7/11:** fornyelsen ligger nu i pluginnet (7.44.0, WP-Cron + option). CI-workflowet og
  worktree-loopet er stadig mulige, men ikke længere nødvendige.
- **Cirlas produktionsrolle:** hvilke rettigheder præcist, og hvornår må modulet slås til i produktion?
- **De tre resterende guides:** publiceres de som de er skrevet, eller skal der fotos og gennemlæsning først?
- **S0.4:** én Redirection-gruppe skal oprettes af ejer, før de fem vanity-301 kan laves.

## Næste skridt

1. **S2.7 HTML-/scriptbudget** (1,0 d) er det næste ublokerede roadmap-punkt: forsiden er 215.638 B HTML
   med 33 scripts og 28 inline style-blokke; budgettet er < 100 KB, < 20 scripts og ingen inline blok
   over 4 KB. Det kræver en beslutning om hvilke plugins der må røres ved (W3TC, Simple Custom CSS/JS,
   WPCode) — ikke bare en måling.
2. De tre manglende guides (SEO 62,5 % → 100 %): copy ligger færdig og verificeret i
   [`library/guides-2026-11-03.md`](library/guides-2026-11-03.md).
2. ✅ MCP-fornyelsen er bygget ind i pluginnet (7.44.0). Det der mangler, er ejerens godkendelse og de to
   værdier under *Settings → MCP access* — derefter holder adgangen i 30 dage uden secrets.
3. Cirla som til/fra-modul i pluginnet efter dokumentets §9.4 — start med trin 1, modulet slukket som standard.
4. Plugin 7.41.0 er i repo, 7.40.1 er live: opdateringen kræver ejerens klik i wp-admin.

## Hvad der ikke kan gøres herfra

- **Live publicering af produktændringer** og alt der kræver wp-admin-klik (plugin-opdatering, Redirection-gruppe).
- **Pinterest, GA4 og Command Center** — ingen adgang fra sandkassen. Undtagelse målt 7/11: WordPress selv nås via Novamira CLI'ens device-godkendelse (`install_code`-rettelsen blev skrevet ad den vej), og GA4-tag'et blev verificeret i cloud-browseren — men selve GA4-rapporterne er stadig utilgængelige.
- **Printify** — rebrand-mockups er blokeret uden ejerens adgang.
- **At holde en proces i live efter arkivering.** Ingen kommando her kan love det; kun CI eller sitets egen
  WP-Cron kan.
