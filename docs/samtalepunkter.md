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
| **steamgears.eu — site-optimering** | **65,3 %** | 15,7 af 24,05 d (S0 2,15/2,35 · S1 2,15/4,6 · S2 7,35/8,3 · S3 2,55/3,55 · S4 1,5/5,25) |
| **Steamgears Autopilot — plugin** | **96,3 %** | 28,8 af 29,9 d (P0 4,65/4,7 · P1 14,2/15,2 · P2 4,75/4,75 · P3 2,7/2,75 · P0S 1/1 · P0C 0,25/0,25 · P1D 0,25/0,25 · P1E 0,25/0,25 · P1F 0,25/0,25 · P1G 0,5/0,5) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (TEKSTER 8,5/8,5 · MOCKUPS 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (BATCH 0/3 · COPY 4/4 · CORSET 1/1) |
| **Døde internlinks** | **100 %** | 39 af 39 links (FIX 39/39) |

### Åbne blokeringer

- **steamgears.eu — site-optimering** — Sitet svarer PHP-parsefejl på alle dynamiske kald (målt igen 7/11: /wp-json, robots.txt, sitemap_index.xml, /?sgap_feed=, og admin-ajax 500 — MCP-fladen svarer selv 500) — kun W3TC-cachede HTML-sider virker, og wp-admin er nede, så rettelsen kræver File Manager/SFTP (ejer). Der findes ingen deploy-vej i repoet: ingen FTP/SFTP/SSH-workflow, ingen .env, ingen gh-login og ingen MCP-credential i sandkassen. S2.7-loftet kræver tre ejerbeslutninger; S1.4/S1.6 kræver live-skrivning; S2.5 mangler OG-billedet.
- **Steamgears Autopilot — plugin** — P1.5b/P1.5c kræver et Pinterest- og Instagram-token (Settings → Keys); P3.1 mangler ejerens labor_rate-beslutning; P1.15 kræver ejerens MCP-godkendelse i wp-admin — og wp-admin er nede indtil mu-plugin-fejlen er ryddet, ligesom RSS-feedet (?sgap_feed=) som publiceringen bruger.
- **Dark academia-rebrand** — Printify-adgang
- **SEO-indholdsproduktion** — Intet eksternt for de tre resterende guides: copy, FAQ, priser og links ligger i docs/library/guides-2026-11-03.md. De otte /go/-id'er 411/475/481/485/497/567 kunne ikke verificeres fra sandkassen (Simplys WAF giver browser-UA en challenge), så nye produktlinks skal måles fra ejerens side eller efter WAF-hvidlistning

### Næste skridt

- **steamgears.eu — site-optimering** — Ryd op i hostens mu-plugin først: erstat wp-content/mu-plugins/managed-wordpress-robots.php med den rettede fil i repoet (wordpress/mu-plugins/, 8/8 offline-checks) via Simplys File Manager — derefter genmåles robots/sitemap/REST/feed. Så: S2.9 + S4.13 ud på siden, OG-billede pr. indlæg og /shop/s H1 (kræver live-adgang), og tre ejerbeslutninger til S2.7-loftet.
- **Steamgears Autopilot — plugin** — P1.5b (Pinterest) og P1.5c (Instagram) er kode-færdige, men den første live-kørsel kræver et token af hver (Settings → Keys); P3.1 mangler ejerens labor_rate-beslutning; P1.15 kræver ejerens MCP-godkendelse i wp-admin.
- **Dark academia-rebrand** — Batch 4 — mockups (~130 billeder)
- **SEO-indholdsproduktion** — Skriv de tre manglende guides (mens-steampunk-costume-essentials, steampunk-clothing-three-budgets, womens-steampunk-essentials) fra den verificerede copy — corset-guiden er publiceret 6/11
- **Døde internlinks** — Vedligehold: tjek nye indlæg mod live-URL'er

### Seneste commits

- `1bed4d8 Ryd op efter sammenfletningen: de genererede filer fik deres rigtige tal`
- `3b9e163 Merge main (S3.5-ugeskontrol og nedbrudsmåling) ind i P1.22`
- `f104f72 P1.22: fejlsøgning i pluginnet og forbehold pr. modul (7.57.0)`
- `58260e9 Drift: nedbruddet målt igen — hele den dynamiske del er nede`
- `2124512 S3.5: ugens kontrol kørt — 24/24 værn, 781 smoke-checks`
- `d0e86a9 Merge main (P1.20/P1.21, 7.55.0) ind i P1.5c Instagram-insights — udgivet som 7.56.0`
<!-- AUTO:SAMTALEPUNKTER:END -->

## Beslutninger og fund (nyeste først)

### 7/11 — Ingen vej uden File Manager, og seks rækker lukket med bevis

- **Syv veje prøvet, alle lukkede.** Der er ingen deploy-workflow i repoet (syv workflows, ingen upload/rsync/SSH), ingen FTP-/SFTP-/SSH-secret, intet `.env`, ingen `~/.ssh`, ingen gh-login og ingen MCP-credential. Vigtigst: **både REST og MCP kører gennem samme PHP og dør med filen** — `wp/v2/users/me` giver 200 med parsefejl, `mcp/v1/http` svarer 500. Et app-password eller et MCP-token ville altså ikke hjælpe. Det står med kommandoer og svar i `library/robots-og-nedbrud-2026-11-07.md` §3c.
- **Seks rækker var markeret åbne, selv om arbejdet var gjort:** S1.1, S2.2, S2.4, S2.5 og S2.6 er lukket med dokumentbevis (Herbarium-linket, `/shipping/`+`/returns/`, H1-beslutelsen, meta-runden på 59 sider, de 54 sporede `/go/`-links). **S0.4** er lukket som *overflødig*, og de 0,5 d er taget ud af nævneren — ikke ført som fremgang.
- **S2.5b er ny og åben:** OG-billede pr. indlæg blev aldrig målt, så det er skilt ud som egen række (+0,25 d) i stedet for at gemme sig i S2.5.
- **S1.4/S1.6 er ikke startet og står urørt** med præcis blokering: `wp_delete_post` og en formular-endring kræver live-adgang.
- **CI's commit-værn er grøn på hele rangen.** To ældre commits kunne ikke rettes; de er efterprøvet mod historikken og står nu i `docs/commit-vaern-register.md` — den ene havde korrekte tal med en anden ordlyd, den anden en overhalet mellemregning fra før et merge. `projektstatus.py` har fået `scope_change_pending`, så et planlagt fald mærker præcis én række og derefter forbruges.
- **Næste skridt** er stadig ét ejerklik: læg `wordpress/mu-plugins/managed-wordpress-robots.php` ind via File Manager. Alt andet venter på det.

### 7/11 — Fejlsøgning i pluginnet (P1.22, 7.57.0) og forbehold som en del af svaret

- **Fejlsøgning er nu også inde i pluginnet**, ikke kun i repoet: **Steamgears → Fejlsøgning**
  (`includes/diagnostics.php`) har ni tjek der **læses** ud af den tilstand modulerne allerede holder — PHP,
  tabeller, planlagte job, fejlede posts, AI-nøgle, Pinterest, Instagram, MCP og modulfejl — hvert med en
  tilstand, en sætning om hvad det fandt, og en om hvad man gør. Siden skriver ikke til den tilstand, så den
  kan ikke selv blive en ny fejlkilde.
- **En kanal der aldrig er blevet forbundet er `off`, ikke `bad`.** Det er ikke en fejl at have slået en kanal
  fra, og et dashboard der råber op om det, lærer ejeren at ignorere farverne. Et token i `wp-config.php` er
  derimod en fejl, fordi det ikke kan fornyes.
- **Loggen findes, fordi modulerne kun holder én fejltekst.** `sgap_instagram_state['error']` bliver ryddet af
  den næste kørsel der lykkes — et natligt afslag kl. 03 var væk kl. 09. `SGAP_Diagnostics::log()` skriver til
  en ringbuffer på 40 rækker, **tæller gentagelser i stedet for at duplikere**, og skiller visnings-`at` (sitets
  ur) fra regne-`ts` (`time()`) — 24-timers-vinduet må ikke arve den gamle `gmdate`-fejl på ±14 timer.
- **Logningen sidder i `record()`** i Instagram- og Pinterest-modulet, ikke i hvert kaldested: det er det ene
  sted tilstanden skrives, så en ny fejlvej ikke kan glemme loggen.
- **Forbeholdet er blevet indhold:** `SGAP_UI::caveat()` står under Instagram-, Pinterest- og
  engagement-kortet og samlet på fejlsøgningssiden. Teksterne siger hvad der er *uden for* tallet — at en
  metric Instagram ikke har rapporteret endnu er **udeladt, ikke 0**; at Pinterest-forespørgsler er afgrænset
  til kanalen fordi `saves`/`likes` deles med andre kanaler; at ROI er last-click, så en ordre uden klik hører
  til ingen kanal; og at et opslag der aldrig kom afsted slet ingen tal har.
- **Bogføringsfejl fanget og rettet i samme pas:** P1.22 blev først lagt både ind i P1's total (15,2 → 15,7) og
  ind som eget modul, så de 0,5 d blev talt to gange (96,4 %). Rettet til eget modul alene → **96,3 %
  (28,8 af 29,9 d)**; korrektionen står som historik-række med `scope_change`, ikke som en slettet række.
- **905 smoke-checks, 0 FAIL**, 24/24 værn grønne. Ikke kørt live — intet token findes.

### 7/11 — Nedbruddet målt igen: hele den dynamiske del er nede

- **Målt med neutral UA:** `/robots.txt`, `/sitemap_index.xml`, `/wp-json/…` og `/?sgap_feed=pinterest` svarer **HTTP 200 med 193 bytes** parsefejl fra `mu-plugins/managed-wordpress-robots.php` linje 13; cache-bustede sider (`/?nocache=`, `/cart/?cb=`, `/checkout/?cb=`) det samme; `admin-ajax.php` svarer **500 «critical error»**. Forsiden og `/shop/` ser raske ud, fordi de kommer fra W3TC-sidecachen.
- **Konsekvens:** wp-admin er nede (så ejeren kan ikke rette det gennem WordPress), **RSS-publiceringen er død**, og søgemaskiner kan ikke læse robots/sitemap. Alle live-punkter i S1–S4 og plugin P1.5b/P1.5c/P1.15 er blokeret af den samme fil.
- **Rettelsen er stadig vej C i** [`library/robots-og-nedbrud-2026-11-07.md`](library/robots-og-nedbrud-2026-11-07.md) §4: læg `wordpress/mu-plugins/managed-wordpress-robots.php` ind via Simplys File Manager. Filen er verificeret igen i dette pas: `php -l` ren og `tools/test_robots_muplugin.php` **8/8**.
- **Fælde:** alle fejl svarer 200. Overvågning skal tælle `Parse error` eller bodystørrelsen (193 bytes), ikke kigge på statuskoden — og altid teste med en cache-buster.

### 7/11 — S3.5: ugens kontrol kørt, og en zip var ældre end kilden

- **Sandkassen havde ingen PHP og ingen MCP-credential.** `tools/natlige-vaern.sh` stoppede derfor før første værn (`tools/_deps/bin/php` manglede), og `tools/mcp_oauth.py --status` svarede »Ingen klient registreret endnu«. PHP blev hentet som den statiske binær AGENTS §7.3 angiver (8.3.14); derefter gik alle **24/24** værn grønne.
- **Zip-sammenligningen var det værd:** `diff -r` mellem kilden og den udpakkede `v7.43.0.zip` viste at den indlejrede `ROADMAP.md` stadig havde P0.4 som »Delvist (0,4 af 0,5 d)«. `main` udgiver nu **7.54.0**, hvis zip er `diff -r`-identisk med kilden, så den gamle pakke blev droppet. **Konklusion: mål zip'en efter en rebase, før push — tal fra før rebasen er ikke status for det der udgives.**
- **`tools/setup-agent-tools.sh` kunne ikke hente PHP:** `python3 -c`-strengen var ombrudt med indrykning → `IndentationError`, så PHP-trinnet døde før download. Rettet til én linje og efterprøvet (vælger nu `php-8.5.8`).
- **Målt efter rebasen:** 781 smoke-checks / 0 FAIL, ingen drift i historikken, PDF renderet og set. S3.5 0,05 → **0,1 af 0,5 d**; site 64,4 % → **64,6 %** (15,7 af 24,3 d).
- **Backup blev ikke kørt** i dette pas, og det står i loggen i stedet for at blive skjult.

### 7/11 — Instagram-insights (P1.5c, 7.56.0): `views`, ikke `impressions` — og nul betyder «ved det ikke»

- **Modulet er `includes/instagram.php` (`SGAP_Instagram`), og det gemmer fem egne event-typer** (`views`,
  `likes`, `comments`, `shares`, `saves`) på kanalen `instagram`, adskilt fra vores `/go/`-klik, ordrerne og de
  to andre platformes snapshots. Sletningen er afgrænset til kanalen, fordi `likes` også er Bluesky/Mastodons
  snapshot-type — **samme latente fejl blev rettet i Pinterest-modulet i samme pas**, hvor `saves` deles.
- **`impressions` er deprecated for medier efter 2/7 2024** og afløst af `views`. Havde vi spurgt om
  `impressions`, ville hvert nyt opslag rapportere nul, og det ville se ud som en måling.
- **En metric Instagram ikke har rapporteret endnu udelades af svaret i stedet for at være 0** (op til 48 timer).
  Opslaget tælles som *waiting* og gemmes ikke som et opslag uden visninger; ellers ville hvert gennemsnit
  nedstrøms arve en påstand Meta ikke har fremsat.
- **Match på caption, ikke på permalink:** et medie har intet link-felt, og `/p/<shortcode>/` indeholder aldrig
  vores vært. Et opslag uden vores `/go/`-link er *unmapped*; en række uden publiceret Instagram-post i vinduet
  er *orphaned*. Begge står på dashboardet i stedet for at blive gættet.
- **To login-typer, to Graph-værter** — værten er derfor en indstilling og ikke et gæt ud fra tokenens form; et
  forkert gæt gør en fungerende token til en 400. Den 60-dages token fornyes **på stedet** (der findes ingen
  separat refresh-token), så en token i `wp-config.php` afvises helt.
- **Tre fejl fanget undervejs:** test-doublen manglede `WP_Error::get_error_code()`, det smallere metric-forsøg
  slugte token-fejl (en udløbet token blev «retried» i stedet for fornyet), og token-fejlen blev returneret før
  fornyelsen blev forsøgt — fordi Instagram svarer 400/code 190 og ikke 401.
- **Versionen blev 7.56.0, ikke 7.54.0:** en anden kørsel havde taget 7.54.0 (P1.20) og 7.55.0 (P1.21), så
  Instagram-modulet blev lagt oven på dem i stedet for at overskrive deres numre. P1.5 er **3,0 af 3,85 d**
  bygget; de sidste 0,85 d er live-verifikation af Pinterest og Instagram, som kræver tokens.
- **838 smoke-checks, 0 FAIL**, 24/24 værn grønne.

### 7/11 — de sidste to lister

- **AI Drafts var ét grid sorteret efter id.** Man skulle læse hver kladde for at se hvilken side
  den hørte til — men beslutningen er om en *side*: »er denne sides posts gode nok?« Derfor er
  kladderne nu grupperet efter kilde-siden, med antal pr. gruppe og tre tal øverst (kladder, sider,
  kanaler). En kladde uden kilde får gruppen *No source page* i stedet for at falde ud af listen.
- **Publish log havde filter, tabel og paginering som tre løse bjælker**, og retentionsnoten som en
  separat bjælke over det hele. Nu: *Filter* som egen sektion med levetiden i forklaringen, derefter
  *Attempts* med Sent · Failed · Requeued or held — **talt op fra de rækker der vises**, mærket »on
  this page«, så tallet ikke kan modsige tabellen under sig.
- **Fælde nummer to i harnessen:** log-siden spørger først `SHOW TABLES` og derefter `COUNT(*)`.
  `get_var()` i smoke-harnessen svarer med én værdi pr. kald, så en test med et enkelt tal blev
  afvist som »tabellen findes ikke« og renderede tom-tilstanden. Svaret lægges nu i kø:
  `$wpdb->next = ['wp_sgap_publish_log', 7]`. Samme familie som `mysql2date()`-hullet: en stub der
  kun kan svare ét sted, skjuler hvilken gren der faktisk blev kørt.
- **Verificeret:** 781 → **788 PASS**, 0 FAIL. UI'et er stadig **ikke** set i en wp-admin; 7.55.0
  venter på ejerens *Plugins → Update*-klik.
- **Bogføring:** P1.21 0,25 d, projektet 29,15 → 29,40 d, 91,1 → 91,2 %.

### 7/11 — sektionsmønstret blev bredt ud, og en skjult gren kom frem

- **Settings-fanerne åbnede direkte på felt-rækker** uden at sige hvad de afgjorde. Nu har hver fane
  overskrift + én linje (`SGAP_Setup::TAB_HINTS`, ni faner; Keys har sin egen overskrift), og et
  smoke-check kræver at hints' nøgler er præcis `TABS` minus `keys` — så en ny fane ikke kan komme
  til uden at få sit svar med.
- **Kø-siden var én tabel med en statuskolonne.** »Venter« og »sendt« lå blandet sammen sorteret
  efter id, så det man skulle handle på lå sammen med historikken. Nu *Waiting* (queued, sending,
  reminded, pending, approved) og *Done* (published, failed), hver med optælling i overskriften.
  Ingen SQL-ændring — samme 50 rækker, filtreret i PHP. Øverst en *Held*-linje der genbruger
  `SGAP_Queue::blocked_message()` i stedet for at opfinde en ny formulering af quiet hours/blackout.
- **Content Studio** var fire kort i to ens grids med resultatet imellem; nu *Write* og *Fix* med
  resultatboksene under hver sektion.
- **Fælden:** `plugins/tests/smoke.php` havde **ingen `mysql2date()`**. Den blev først nødt til at
  findes, da en kø-række fik en rigtig `scheduled_at` — hver tidligere test-række havde `null`, så
  datogrenen i tabellen var aldrig blevet kørt. Stubben er tilføjet, og de nye checks bruger en
  række med både planlagt og publiceret tid. Samme slags hul som 7.46.0's `wp_next_scheduled()`: en
  test der altid får »tom« svarer grønt uden at have rørt grenen.
- **Parallelle chats igen:** P1.5b (Pinterest-analytics) og P3.6 (fejlscan) blev pushet mens dette pas
  kørte, så UI-arbejdet blev rebaseret oven på dem og udgivet som **7.54.0** i stedet for 7.53.0 —
  versionsnummeret var taget. Tallene her er efter rebasen.
- **Verificeret:** 770 → **781 PASS**, 0 FAIL. UI'et er stadig **ikke** set i en wp-admin; 7.54.0
  venter på ejerens *Plugins → Update*-klik.
- **Bogføring:** P1.20 0,25 d, projektet 28,90 → 29,15 d, 91,0 → 91,1 %.

### 7/11 — Fejlsøgning er nu en kørsel, ikke en lejlighed

- **`tools/fejlscan.py` er værn nr. 23–24** og leder efter det `php -l` og smoke-suiten ikke kan se: version tre
  steder (konstant, header, zip-navn), zip mod kilde byte for byte, døde og forældreløse `include`, nye
  dokumenter uden for `docs/library/README.md`, tokens i sporede filer, `TODO`/`FIXME` og tests der ikke kan
  fejle. **13 fixtures** i `--selftest` beviser både at hver klasse fanges, og at en ren kasse er grøn — et
  værn der altid er rødt bliver ignoreret.
- **Fem fejl fundet og rettet i 7.53.1.** (1) Et access-token i `wp-config.php` blev brugt til at hente data,
  men fornyelsen skrev til en option som konstanten altid vandt over — nu afvises fornyelsen med konstantens
  navn. (2) Et indklistret token uden registreret udløb blev kaldt *ugyldigt* i `status`. (3) Et afslag på
  pin-listen stoppede hele kørslen, selv om de pins der allerede var kortlagt kunne måles. (4) Vinduerne blev
  målt med `gmdate()` mod rækker skrevet med `current_time('mysql')` — op til ±14 timers forskel; nu `wp_date()`.
  (5) Engagement-testen endte i `|| true`, kunne aldrig fejle, og læste den forrige tests kø-rækker som
  snapshot-kolonner (tre PHP-advarsler pr. kørsel).
- **Fejlsøgning står nu i roadmap'et som fast punkt:** plugin-roadmappet har fået rækken **P3.6**, og
  `docs/roadmap.md` §10 beskriver hvad der køres, hvornår — og hvad det **ikke** dækker (live-sitet, eksterne
  API'er, tredjepartskode). Det sidste er det vigtigste: grønne værn betyder ikke «sitet virker».
- **To roadmap-totaler var forkerte, og det er den slags fejl der overlever.** P0 stod til 5,25 d (regningen:
  5,95), P1 til 13,30 d (regningen: 15,20 — P1.5 blev tre moduler), og site-linjen i `docs/roadmap.md` §9 til
  23,8 d (bånde-totalerne summer 24,3). Alle tre linjer følger nu `projektstatus.json`, med årsagen skrevet ind.
- **`docs/agent-profil.md` var også bagud:** den sagde plugin 74,0 % og site 61,6 %, live-version 7.41.1 i
  begge, 44 klasser/30 filer og 523 smoke-checks. Nu: 91,0 % og 64,4 %, repo 7.53.1 mod live 7.41.1 (målt 7/11,
  ikke genmålt siden), 51 klasser/39 filer og 770 checks.

### 7/11 — Pinterest-analytics: match på link, ikke på titel

- **P1.5b er bygget (7.53.0), ikke bare planlagt.** `includes/pinterest.php` henter `IMPRESSION`, `SAVE` og
  `OUTBOUND_CLICK` fra `/v5/pins/{id}/analytics` og gemmer dem som **tre egne event-typer**. De blandes
  bevidst ikke med Bluesky/Mastodons likes/reposts/replies og heller ikke med vores egne klik (`sgap_cro`) og
  ordrer: en `save` er en platformshandling, en `click` er vores sporingslink, og en `order` er WooCommerce.
  Summer man dem, kan ingen af dem fortolkes bagefter.
- **Pin→kø-matchningen sker på pin'ets `link` mod `/go/<id>/`.** Publisheren giver Publer sporingsadressen, så
  linket er en nøjagtig identifikation, hvor titlen kun ville være et gæt. Et fremmed link tælles som
  **unmapped**, og et `/go/`-id uden en publiceret Pinterest-post i vinduet som **orphaned**; begge står på
  dashboardkortet. Kun denne sides host accepteres — et andet domænes `/go/42/` er ikke vores kvittering.
- **90 dage er Pinterests loft, ikke et valg.** `start_date` hæves til dagens dato minus 89 dage for en ældre
  post, så den måles på de sidste 90 dage i stedet for at blive afvist. Pin-listen pagineres med `bookmark`
  over flere kørsler, og hver kørsel bruger højst 25 analytics-kald, så en backlog drænes fair.
- **Refresh-tokenet roteres ved hver fornyelse.** Det nye par gemmes før noget andet kan fejle; ellers står man
  med et brugt token og skal autorisere appen igen. En `wp-config.php`-konstant afvises (kan ikke opdateres),
  og et afvist token når det daglige selvtjek frem for at blive opdaget ved, at tallene bare holder op.
- **Fælden ved at pushe:** `main` var rykket videre med P1.19 og en roadmap-bogføring, så første push blev
  afvist. Arbejdet blev lagt på `backup-p15b`, indholdet lagt oven på den nye main og procenterne regnet om
  derfra — ikke tvunget igennem med en gammel total.
- **Ikke kørt live.** Intet Pinterest-token findes i repoet eller i sandkassen, så første rigtige kørsel skal
  kontrollere tre ting: at `summary_metrics` ligger under `all`, at pin-`link` faktisk er `/go/`-adressen (og
  ikke en omskrevet UTM-variant), og at sidste side svarer `bookmark: ""`. Fejler en af dem, er det
  `summary_metrics()`/`queue_id()`/`scan()` der skal rettes — ikke resten.


### 7/11 — dashboardet blev læst som to ulige kolonner

- **Asymmetrien var målt i markup, ikke gættet:** venstre kolonne havde to kort (`Run now` og
  kanaltabellen), højre havde otte. Siden blev tre skærmhøjder lang på den ene side og næsten tom på
  den anden, og de kort der svarer på »går det?« (Scheduler, Engagement) lå nederst i den rækkefølge
  de var blevet tilføjet.
- **Beslutning: ét spørgsmål pr. sektion** — *Now* (Run now, AI Chat, Self-check, Scheduler),
  *Channels* (tal pr. kanal, Engagement, RSS-feeds, API-nøgler) og *System* (opdatering, Command
  Center, billed-rescue). Overskrifterne er `SGAP_UI::section()`, ikke kort, så mønstret kan flyttes
  til de øvrige sider uden at ændre deres markup.
- **Én `Needs you`-linje i stedet for grønne pills overalt.** Kun fire slags fund tæller: dumpek
  AI-test, self-check-problemer, blokeret publicering (oversat til ord) og kladder der venter.
  »Queue er tom« og »nøglen er ikke testet endnu« er bevidst ude — det sidste stod allerede i
  *Get started*, og med det i linjen ville advarslen være permanent og derfor ulæst.
- **Fælde: `SGAP_UI::css()` printer kun én gang pr. request** (`self::$printed`). En test der leder
  efter nye CSS-regler i sidens HTML fejler, hvis en tidligere side i samme kørsel har printet
  stylesheet'et. Læs `SGAP_UI::stylesheet()` i stedet. Samme slags fælde: assert på kortets titel
  (`Scheduler</h2>`) og ikke på `Next publish tick`, som afhænger af om cron-stubben svarer med en tid.
- **Verificeret:** 706 → **714 PASS**, 0 FAIL. **Ikke** set i en rigtig wp-admin — der er ingen
  adgang fra worktreen, og 7.52.0 venter på ejerens *Plugins → Update*-klik. Farver og hvordan de to
  kolonner bryder ved 782 px er uverificeret.
- **Bogføring:** ny modul **P1.19 0,25 d** (mønstret kunne genbruges, derfor ikke større), så
  projektet går fra 28,4 til 28,65 d — nævneren vokser med arbejdet, og procenten stiger kun det
  arbejdet er værd.

### 7/11 — to rækker i plugin-roadmappet talte den samme eftermiddag to gange

- **P2.4 (Etsy) stod stadig som »ikke afgjort og venter på ejeren«**, selv om den blev afgjort 6/11:
  skjult, ikke bygget (7.33.4). Rækken lovede derfor stadig en 2,5 d OAuth-klient, som ingen vil bygge.
- **De 0,25 d for at skjule Etsy lå samtidig i P3.1's note**, så den samme eftermiddag var booket to
  steder. Nu ligger den i P2.4, hvor roadmappet estimerede den, og P3.1 er 0,2 af 0,25 d (død metode +
  ni usynlige rester) med kun `labor_rate` tilbage.
- **Nævneren blev derfor rettet:** P2 7,0 → 4,75 d, P3 2,75 → 2,5 d, projektet 30,9 → 28,4 d. Uden
  rettelsen kunne P2 aldrig blive færdigt, og hver fremtidig procent ville være undervurderet med cirka
  otte point. **Stigningen 80,3 → 87,3 % er bogføring, ikke arbejde** — der er ikke bygget noget i dette pas.
- **P1.5 er samtidig delt op:** P1.5a (Bluesky + Mastodon) er bygget 7/11 i 7.51.0, og `next`/`blocked_by`
  nævner nu kun P1.5b/c, som kræver et Pinterest-trial-token og en IG-konto-token.

### 7/11 — merchant-schemaet var rigtigt, men sprang produktionen over

- **S4.13's kode lå der allerede** (skrevet 2/11), så runden handlede om at måle den mod Googles
  **nuværende** krav i stedet for mod hukommelsen. Kilden er Search Centrals merchant-listing-side.
- **Produktionstiden manglede.** `/shipping/` siger »Production: 3-7 business days«, derefter 6–10 dage i DK
  og 7–12 i EU. Markupen satte hele leveringstiden som `transitTime` og sprang produktionen over, så en
  dansk kunde så 6–10 dage mod sitets 3–7 **plus** 6–10. Den er nu `handlingTime` ved siden af `transitTime`.
- **`itemCondition: NewCondition`** er tilføjet hvor feltet mangler — butikken er print-on-demand og sælger
  ikke returvarer, så det er en kendsgerning, ikke en påstand om stand.
- **Fragt-linjen kan ikke kvalificere, og det skal siges højt.** Google kræver `shippingRate.value` eller
  `.maxValue` for at vise fragtoplysningen. Sitet har ingen fast fragtpris (»shown before you confirm your
  order«), og at skrive `0` ville love gratis fragt. Feltet er derfor bevidst udeladt: markupen er gyldig og
  **returretten** kvalificerer (den kræver kun `applicableCountry` + `returnPolicyCategory`), men
  fragt-linjen bliver ikke vist, uanset hvor meget kode der skrives. Det kræver en pris fra ejeren eller en
  indstilling i Merchant Center.
- **En overskrift fra marts 2025 holdt ikke.** Nyhedsartiklerne skrev at `returnPolicyCountry` nu er
  påkrævet — det gælder **organisation**-niveau, ikke den offer-politik filteret skriver. Havde jeg bygget
  på overskriften, var feltet blevet ignoreret, og dokumentationen havde påstået noget forkert.
- **Værnet var hullet.** `tools/schema_filter_test.php` har kørt siden 2/11, men var **ikke** med i den
  natlige kørsel — en regression i schema-filteret ville være gået ubemærket hen. Den er nu værn nr. 22, og
  testen gik 19 → 30 påstande.
- **Ikke deployet:** filen skal stadig i `wp-content/mu-plugins/` og måles med `tools/schema_check.py`. Det
  kræver WordPress-adgang, som sessionen ikke har.

### 7/11 — hele sidens overflade er nu ét lag, ikke 300 inline-regler

- **Problemet var ikke mangel på CSS, men mangel på rækkefølge.** `css/sg-design-system.css` fandtes i 275
  linjer og blev **aldrig indlæst af noget**. Temaet bærer sin flade i to base64-kodede template-parts
  (header 50.348 B / 1.383 linjer med to `<style>`-blokke, footer 4.136 B) plus ~300 inline-regler, mange
  med `!important` — hver rettelse hidtil blev et nyt lag oven på det forrige.
- **Beslutningen: ét lag, printet sidst.** Filen er skrevet om til 778 linjer og lægges på siden af
  `wordpress/mu-plugins/sga-design-system.php`, som printer den i `wp_footer` med prioritet 999 — efter
  temaets `<head>`-styles og blokkens globale styles. Rækkefølge slår specificitet, så laget ikke skal slås
  med temaet. Det er hele grunden til at loaderen findes; uden den var filen endnu en taber i en
  specificitetskrig.
- **Paletten blev den mørke, ikke pergament.** `design.md` §3's lyse palette er ikke slået til, fordi den
  ikke er set i en browser og temaets regler er skrevet mod den mørke flade. Den ligger nederst i filen som
  `body.sg-theme--parchment` — en fravalgt variant med én klasses afstand, ikke en glemt mulighed.
- **Fælden: fokusringen forsvandt på messing-knapperne.** `outline: 2px solid #F0D27C` måler 12,7:1 på blek
  og **2,09:1** mod messing-fladen, altså under WCAG's 3:1 netop der hvor brugeren er på vej hen. Ringen er
  nu dobbelt: mørk inderring på messing, lys ring ellers. Læren er generel — en fokusring skal måles mod
  **hver** flade den kan lande på, ikke kun mod sidens bund.
- **Værnet fangede straks min egen fejl.** `tools/design_check.py` afviste filen fordi den krævede
  `::where(...)` mens CSS'en (korrekt) skriver `:where(...)`; rettelsen var i værnet, ikke i filen. Det er
  den slags der ellers først bliver opdaget uger senere.
- **Ikke verificeret, og det skal stå klart:** laget er **ikke** på det levende site og er **ikke** set i en
  browser. Sessionen har ingen WordPress-adgang (`connect_integration` svarede ikke-tilsluttet, ingen
  MCP-credential). Det testede er logikken: 21/21 værn grønne, loaderen printer 778 linjers CSS, `body` får
  `sg-ui`, kill-switchen fjerner begge dele, og en manglende fil giver en HTML-kommentar i stedet for CSS.
  Sådan lægges det på: `library/designsystem-hele-siden-2026-11-07.md` §9.

### 7/11 — jeg lagde sitet ned med et mu-plugin, og MCP kunne ikke redde det

- **Hændelsen:** under S0.2 (robots.txt) skrev jeg `managed-wordpress-robots.php` med `novamira/execute-php`, men med
  filindholdet som en **dobbelt-citeret PHP-streng**. `$output`, `$rules` og `$block` blev interpoleret til tomme
  strenge, filen fik ` = array(` på linje 13, og en parse-fejl i et mu-plugin er en **E_COMPILE_ERROR på hvert
  request**. Siden da svarer `/?nocache=1`, `/shop/`, `/cart/` og `/wp-json/…` med fejlen; kun W3TC's cachede
  forside virker. Butikken kan ikke tage imod ordrer, indtil filen er rettet.
- **Fælden der gjorde det uopretteligt over MCP:** mu-plugins indlæses **før** plugins, så Novamira/MCP er også
  dødt. Der findes ingen anden kanal i sandkassen (ingen SFTP/SSH-credential), så genopretningen kræver
  ejerens fil-adgang i Simply — tre veje i `docs/library/robots-og-nedbrud-2026-11-07.md` §4.
- **Succes-kriteriet var forkert:** kaldet returnerede `bytes: 1222`, og jeg gik videre på det tal. Et
  `file_put_contents` beviser kun at disken tog imod noget — ikke at indholdet kan parses. Fra nu: læs filen
  tilbage, sammenlign `sha1`, kør `token_get_all(..., TOKEN_PARSE)`, hent den URL der bruger filen.
- **Robots-ejerskabet er nu målt (S0.2's anden halvdel):** Rank Math genererer filen (ingen fysisk fil), og
  `managed-wordpress-robots.php` er et **host-lag**, der kun tilføjer otte query-param-`Disallow`. To grupper med
  samme user-agent er gyldigt (RFC 9309 §2.2.1), så rækken kunne være lukket som «målt og dokumenteret» uden at
  røre en host-fil.
- **Roadmap-gennemgangen blev standset her.** Inventory var nået igennem: S0.4's fem URL'er svarer alle 200
  (rækken er forældet), siderne i S3.1/S3.2 ligger allerede i skraldespanden og er talt med, S4.13's
  schema-mu-plugin ligger i repoet men er **aldrig deployet** (`wp_get_mu_plugins()` viser fire filer, ikke fem),
  og Health Check + dets drop-in er stadig aktive (S0.5). Intet af det blev udført.


### 7/11 — jeg lagde sitet ned med et mu-plugin, og MCP kunne ikke redde det

- **Hændelsen:** under S0.2 (robots.txt) skrev jeg `managed-wordpress-robots.php` med `novamira/execute-php`, men med
  filindholdet som en **dobbelt-citeret PHP-streng**. `$output`, `$rules` og `$block` blev interpoleret til tomme
  strenge, filen fik ` = array(` på linje 13, og en parse-fejl i et mu-plugin er en **E_COMPILE_ERROR på hvert
  request**. Siden da svarer `/?nocache=1`, `/shop/`, `/cart/` og `/wp-json/…` med fejlen; kun W3TC's cachede
  forside virker. Butikken kan ikke tage imod ordrer, indtil filen er rettet.
- **Fælden der gjorde det uopretteligt over MCP:** mu-plugins indlæses **før** plugins, så Novamira/MCP er også
  dødt. Der findes ingen anden kanal i sandkassen (ingen SFTP/SSH-credential), så genopretningen kræver
  ejerens fil-adgang i Simply — tre veje i `docs/library/robots-og-nedbrud-2026-11-07.md` §4.
- **Succes-kriteriet var forkert:** kaldet returnerede `bytes: 1222`, og jeg gik videre på det tal. Et
  `file_put_contents` beviser kun at disken tog imod noget — ikke at indholdet kan parses. Fra nu: læs filen
  tilbage, sammenlign `sha1`, kør `token_get_all(..., TOKEN_PARSE)`, hent den URL der bruger filen.
- **Robots-ejerskabet er nu målt (S0.2's anden halvdel):** Rank Math genererer filen (ingen fysisk fil), og
  `managed-wordpress-robots.php` er et **host-lag**, der kun tilføjer otte query-param-`Disallow`. To grupper med
  samme user-agent er gyldigt (RFC 9309 §2.2.1), så rækken kunne være lukket som «målt og dokumenteret» uden at
  røre en host-fil.
- **Roadmap-gennemgangen blev standset her.** Inventory var nået igennem: S0.4's fem URL'er svarer alle 200
  (rækken er forældet), siderne i S3.1/S3.2 ligger allerede i skraldespanden og er talt med, S4.13's
  schema-mu-plugin ligger i repoet men er **aldrig deployet** (`wp_get_mu_plugins()` viser fire filer, ikke fem),
  og Health Check + dets drop-in er stadig aktive (S0.5). Intet af det blev udført.


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

0. **Sandkassen mangler et MCP-token** — uden det kan intet skrives til sitet, kun måles. `python3 tools/mcp_oauth.py --start`
   giver linket, ejeren godkender i browseren, `--finish '<kode>'` gemmer token uden for repoet.
1. **S2.9 designsystem: læg laget på sitet og se det** (0,1 d tilbage af 0,5). CSS-filen og loaderen er
   skrevet og værnet; det der mangler, er at kopiere `css/sg-design-system.css` til `uploads/sga-design/`
   (eller ind i `mu-plugins/`), kopiere `wordpress/mu-plugins/sga-design-system.php` til
   `wp-content/mu-plugins/`, og se forsiden, en produktliste, en produktside og kurven i en browser —
   især fokusringen på messing-knapper, produktgriddet ved 390 px og headeren. Slå fra med
   `add_filter( 'sga_design_enabled', '__return_false' );` hvis noget brækker.
2. **S2.7 HTML-/scriptbudget** (1,0 d) er stadig det næste ublokerede roadmap-punkt, men script-loftet
   kræver ejerens beslutning (chatbot, GLA, klassisk kurv).
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
