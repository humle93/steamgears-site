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
_Opdateret 2026-11-06 af `tools/samtalepunkter.py`. Blokken er maskinelt genereret — ret i `projektstatus.json`, ikke her._

### Status

| Projekt | Status | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** | **56,3 %** | 13,4 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,45/3,55 · S4 1,4/5,25) |
| **Steamgears Autopilot — plugin** | **74 %** | 19,9 af 26,9 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 1,95/2) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (TEKSTER 8,5/8,5 · MOCKUPS 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (BATCH 0/3 · COPY 4/4 · CORSET 1/1) |
| **Døde internlinks** | **100 %** | 39 af 39 links (FIX 39/39) |

### Åbne blokeringer

- **steamgears.eu — site-optimering** — S1.1 Herbarium kræver varianter i Printify; S0.4 5 vanity-301 kræver én Redirection-gruppe (ejer). S2.2 er lukket 6/11 — trust-siderne findes og er linket ind
- **Steamgears Autopilot — plugin** — P1.5 kræver Pinterest-token (401) og Meta-review; P0.4 kræver host-cron (ejer); P3.1 mangler ejerens labor_rate-beslutning
- **Dark academia-rebrand** — Printify-adgang
- **SEO-indholdsproduktion** — Intet eksternt for de tre resterende guides: copy, FAQ, priser og links ligger i docs/library/guides-2026-11-03.md. De otte /go/-id'er 411/475/481/485/497/567 kunne ikke verificeres fra sandkassen (Simplys WAF giver browser-UA en challenge), så nye produktlinks skal måles fra ejerens side eller efter WAF-hvidlistning

### Næste skridt

- **steamgears.eu — site-optimering** — Skriv de tre manglende guides fra den verificerede copy (S2.5-meta-runde og S2.6-links er kørt; S2.2 og corset-guiden er lukket 6/11); S1.1 Herbarium kræver varianter i Printify; S0.4 kræver én Redirection-gruppe (ejer); S0.9 kræver en browser-session med GA4 DebugView (to page_view i samme session)
- **Steamgears Autopilot — plugin** — P0.4 rigtig host-cron (ejer); P3.1 labor_rate (ejer); P1.5 er eksternt blokeret (Pinterest-token + Meta-review)
- **Dark academia-rebrand** — Batch 4 — mockups (~130 billeder)
- **SEO-indholdsproduktion** — Skriv de tre manglende guides (mens-steampunk-costume-essentials, steampunk-clothing-three-budgets, womens-steampunk-essentials) fra den verificerede copy — corset-guiden er publiceret 6/11
- **Døde internlinks** — Vedligehold: tjek nye indlæg mod live-URL'er

### Seneste commits

- `de9cd1b CSS snapshot 07/11`
- `1678bf2 Modul: roadmap-kørsel — S0.9 målt, resten blokeret af manglende skriveadgang Før: —  →  Efter: —  (Δ —) Grundlag: ingen ændring`
- `3473e4f Merge branch 'main' of https://github.com/humle93/steamgears-site into freebuff/d96c797cb28964a7f8fb9d78`
- `6a77774 Modul: S2.3 navigation — footer-links til /shipping/ og /returns/ målt Før: 55,5 %  →  Efter: 56,3 %  (Δ +0,8) Grundlag: 13,4 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,5/7,55 · S3 2,45/3,55 · S4 1,4/5,25)`
- `d78b50d Modul: Plugin P3.2 — keycheck-test for Anthropic og custom OpenAI-kompatibel provider Før: 74,0 % → Efter: 74,0 % (Δ +0) Grundlag: 19,9 af 26,9 d — test- og cachefix, ingen ændring i estimatet`
- `49a27a2 Modul: site S0–S4 — §9 P2–P5 indarbejdet oven på main Før: 60,6 %  →  Efter: 55,5 %  (Δ -5,1) Grundlag: 13,2 af 23,8 d (S0 1,9/2,85 · S1 2,15/4,6 · S2 5,3/7,55 · S3 2,45/3,55 · S4 1,4/5,25)`
<!-- AUTO:SAMTALEPUNKTER:END -->

## Beslutninger og fund (nyeste først)

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
- **Pinterest, GA4 og Command Center** — ingen adgang fra sandkassen.
- **Printify** — rebrand-mockups er blokeret uden ejerens adgang.
- **At holde en proces i live efter arkivering.** Ingen kommando her kan love det; kun CI eller sitets egen
  WP-Cron kan.
