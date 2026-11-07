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
| **steamgears.eu — site-optimering** | **59,7 %** | 14,2 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 6/7,55 · S3 2,5/3,55 · S4 1,4/5,25) |
| **Steamgears Autopilot — plugin** | **74,9 %** | 20,85 af 27,85 d (P0 4,25/4,7 · P1 9,4/13,4 · P2 4,5/7 · P3 2,7/2,75) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (TEKSTER 8,5/8,5 · MOCKUPS 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (BATCH 0/3 · COPY 4/4 · CORSET 1/1) |
| **Døde internlinks** | **100 %** | 39 af 39 links (FIX 39/39) |

### Åbne blokeringer

- **steamgears.eu — site-optimering** — S1.1 Herbarium kræver varianter i Printify; S0.4 5 vanity-301 kræver én Redirection-gruppe (ejer); S2.7's mål om < 100 KB HTML og < 20 scripts pr. skabelon kan ikke nås uden at fjerne Woo-blokkenes React-stak, AI Engine-chatbot'en eller TikTok/Site Kit-sporing (ejerbeslutning, målt 7/11). S2.2 er lukket 6/11, S0.9 7/11.
- **Steamgears Autopilot — plugin** — P1.5 kræver Pinterest-token (401) og Meta-review; P0.4 kræver host-cron (ejer); P3.1 mangler ejerens labor_rate-beslutning
- **Dark academia-rebrand** — Printify-adgang
- **SEO-indholdsproduktion** — Intet eksternt for de tre resterende guides: copy, FAQ, priser og links ligger i docs/library/guides-2026-11-03.md. De otte /go/-id'er 411/475/481/485/497/567 kunne ikke verificeres fra sandkassen (Simplys WAF giver browser-UA en challenge), så nye produktlinks skal måles fra ejerens side eller efter WAF-hvidlistning

### Næste skridt

- **steamgears.eu — site-optimering** — Skriv de tre manglende guides fra den verificerede copy; S2.8 hub-links på de resterende indlæg; S2.7's sidste to kriterier kræver en ejerbeslutning om Woo-blokke/chatbot/sporing; S1.1 Herbarium kræver varianter i Printify; S0.4 kræver én Redirection-gruppe (ejer)
- **Steamgears Autopilot — plugin** — P0.4 rigtig host-cron (ejer); P3.1 labor_rate (ejer); P1.5 er eksternt blokeret (Pinterest-token + Meta-review)
- **Dark academia-rebrand** — Batch 4 — mockups (~130 billeder)
- **SEO-indholdsproduktion** — Skriv de tre manglende guides (mens-steampunk-costume-essentials, steampunk-clothing-three-budgets, womens-steampunk-essentials) fra den verificerede copy — corset-guiden er publiceret 6/11
- **Døde internlinks** — Vedligehold: tjek nye indlæg mod live-URL'er

### Seneste commits

- `d5ea466 Modul: S0.9 analytics — inline GA4-tag fjernet Før: 56,5 %  →  Efter: 57,6 %  (Δ +1,1) Grundlag: 13,7 af 23,8 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,5/3,55 · S4 1,4/5,25)`
- `dd09915 Modul: P0.2 — kanal-cap som reelt loft (7.43.0) Før: 74,9 % Efter: 74,9 % Δ: 0,0 Grundlag: ingen ændring`
- `693c1f1 Modul: Docs — MCP-fladen vs. plugin-profilen (ingen ændring) Før: 74,7 %  →  Efter: 74,7 %  (Δ 0) Grundlag: ingen ændring`
- `57e6910 Modul: P0.2 — runway pr. kanal (7.42.0) Før: 74,7 % Efter: 74,9 % Δ: +0,2 Grundlag: 20,85 af 27,85 d = 74,9 % (11 nye checks, 555 PASS)`
- `c674b82 chore(status): daglig projektstatus 2026-11-07`
- `65a6336 Modul: Plugin P3.3 — AI-profiler og access all (7.41.2) Før: 74,0 %  →  Efter: 74,7 %  (Δ +0,7) Grundlag: 20,65 af 27,65 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 2,7/2,75)`
<!-- AUTO:SAMTALEPUNKTER:END -->

## Beslutninger og fund (nyeste først)

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

- **Novamira-fornyelse:** CI med to secrets, eller fornyelse i pluginnet via WP-Cron (ingen secrets)?
- **Cirlas produktionsrolle:** hvilke rettigheder præcist, og hvornår må modulet slås til i produktion?
- **De tre resterende guides:** publiceres de som de er skrevet, eller skal der fotos og gennemlæsning først?
- **S0.4:** én Redirection-gruppe skal oprettes af ejer, før de fem vanity-301 kan laves.

## Næste skridt

1. De tre manglende guides (SEO 62,5 % → 100 %): copy ligger færdig og verificeret i
   [`library/guides-2026-11-03.md`](library/guides-2026-11-03.md).
2. Byg fornyelsen ind i pluginnet, hvis de 30 dage skal holde uden secrets (WP-Cron + option, ingen nøgler i repo).
3. Cirla som til/fra-modul i pluginnet efter dokumentets §9.4 — start med trin 1, modulet slukket som standard.
4. Plugin 7.41.0 er i repo, 7.40.1 er live: opdateringen kræver ejerens klik i wp-admin.

## Hvad der ikke kan gøres herfra

- **Live publicering af produktændringer** og alt der kræver wp-admin-klik (plugin-opdatering, Redirection-gruppe).
- **Pinterest, GA4 og Command Center** — ingen adgang fra sandkassen. Undtagelse målt 7/11: WordPress selv nås via Novamira CLI'ens device-godkendelse (`install_code`-rettelsen blev skrevet ad den vej), og GA4-tag'et blev verificeret i cloud-browseren — men selve GA4-rapporterne er stadig utilgængelige.
- **Printify** — rebrand-mockups er blokeret uden ejerens adgang.
- **At holde en proces i live efter arkivering.** Ingen kommando her kan love det; kun CI eller sitets egen
  WP-Cron kan.
