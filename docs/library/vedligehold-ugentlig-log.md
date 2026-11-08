# Ugentlig backup + 1 indholdselement — log

## 2026-11-08 (plugin-pas) — P1.5 kanalstatus, 7.61.0
- **Bygget:** `includes/channel-health.php` + `/sgap/v1/channel-health` (401 uden login) og 11 nye smoke-checks.
- **Målt:** smoke **1027 PASS / 0 FAIL**, `php -l` ren, fejlscan **0 fund**, zip `v7.61.0` 349 946 bytes / 46 filer,
  installeret live (`status: active, version: 7.61.0`).
- **Live kanalstatus:** ready 1 · recoverable 0 · missing 5 (Pinterest `ready`, men 401 ved refresh).
- **Fælde:** `SGAP_VERSION`-konstanten skal bumpes sammen med headeren, ellers fanger fejlscan en version-mismatch.

## 2026-11-08 (senere pas) — S4.9 og S4.11 lukket
**Formål:** de to sidste S4-punkter der ikke kræver ejerens adgang.
- **S4.9:** produktteksten er nu synlig server-side via `woocommerce_after_single_product_summary`; målt i browser
  (tee + hoodie: 7-rækkers størrelseskort, cm og inches, fit/materiale/produktion/levering) og i HTML på alle otte
  tøjprodukter. `/shop/`, `/`, `/right-of-withdrawal/` og `/journal/` har **0** forekomster.
- **S4.11:** `/shop/` målt til **263 070 bytes / 2,93–3,16 s** (cache 263 442 / 2,46 s) mod kravet 470 KB / 5,8 s.
- **Fælde:** `?p=<tal>` på `/shop/` giver 404 (WordPress læser `p` som post-id) — brug `?v=` til cache-bust.

## 2026-11-08 — ugentlig kontrol (værn, smoke, zip, live-målinger)
**Formål:** S3.5's tredje post. Kørt i agentens cloud-sandkasse med Novamira-adgang, så tallene er både
repo-værn og live-målinger fra samme dag.

**1) Repo-værn:**
- `plugins/tests/smoke.php`: **1016 PASS / 0 FAIL**
- `python3 tools/fejlscan.py`: **0 fund** (version, zip, includes, bibliotek, testfiler konsistente)
- `python3 projektstatus.py --check`: **uden drift**, 133 historik-rækker
- `plugins/steamgears-autopilot-v7.60.1.zip` er den eneste zip og `diff -r`-identisk med kilden

**2) Live-målinger (samme dag):**
- `/`, `/shop/`, `/wp-json/sgap/v1`: **200**
- Billedvægt: **0 af 41** i brug værende billeder over 300 KB (var 8)
- H1: **1** på `/`, `/free-starter-kit/` og `/steampunk-halloween/` (var 1/2/2)
- `robots.txt`: **1** `User-agent: *`-gruppe (var 3), 14 `Disallow` + 1 `Allow` + `Sitemap` bevaret
- Scripts: `wp-emoji`/TikTok/Kliken **0** forekomster; 31 scripts på `/`, 46 på `/shop/`
- Lead-endpoint: `POST /wp-json/sgap/v1/lead` → `{"ok":true,"count":1}` (testpost ryddet igen)

**3) Status (beregnet):** site-optimering **82,5 %** (19,85 af 24,05 d) · plugin **97,2 %**
· Dark academia-rebrand **63 %** (batch 4-mockups blokeret på Printify-adgang) · SEO-indhold **66,7 %**
(publiceringsvalg).

## 2026-11-07 — ugentlig kontrol (værn, smoke, zip)
**Formål:** S3.5's anden post: ugens kontrol kørt efter opskriften i [`../projektstatus.md`](../projektstatus.md) og
roadmap'ens tjekliste. Backup blev **ikke** kørt i dette pas, og der er ingen live-skrivning: sandkassen har ingen
MCP-credential (`tools/mcp_oauth.py --status` → »Ingen klient registreret endnu«, `tools/mcp_rest.py --check` →
»ingen credential«).

**1) Status fra `projektstatus.json` (beregnet, ikke håndskrevet):**
- steamgears.eu — site-optimering: **64,6 %** (15,7 af 24,3 d)
- Steamgears Autopilot — plugin: **91,1 %** (26,55 af 29,15 d)
- Dark academia-rebrand: **63 %** (8,5 af 13,5 t)
- SEO-indholdsproduktion: **62,5 %** (5 af 8 guides)
- Døde internlinks: **100 %** (39 af 39 links)

**2) Værn og test — alle kørt i dette pas:**
- `bash tools/natlige-vaern.sh`: **24 af 24 grønne**. Listen er udvidet siden 6/11 (17 værn dengang), så tallet
  kan ikke sammenlignes direkte med den forrige post.
- `plugins/tests/smoke.php`: **781 PASS / 0 FAIL**, exit 0.
- `python3 projektstatus.py --check`: **5 projekter · 110 historik-rækker · ingen drift**.
- PHP var **ikke** installeret i sandkassen efter genoprettelsen (`tools/_deps/bin/php` manglede, så den samlede
  kørsel stoppede før første værn). Hent med `bash tools/setup-agent-tools.sh`, eller — som her — den statiske
  binær fra AGENTS.md §7.3; kørslen brugte **PHP 8.3.14**.

**3) Fund: en zip der var ældre end kilden.** `diff -r` mellem `plugins/steamgears-autopilot` og den udpakkede
`plugins/steamgears-autopilot-v7.43.0.zip` gav to afvigelser, begge i den indlejrede `ROADMAP.md`: P0.4 stod som
»Delvist (0,4 af 0,5 d) … Rigtig host-cron mangler stadig (ejer)« og overskriften nævnte ikke cron, mens kilden
havde P0.4 lukket og ejerbekræftet. En selvopdatering fra den pakke ville altså have installeret en ældre
roadmap-tekst end repoet. **Ved rebasen var `main` rykket 41 commits frem og udgiver nu 7.54.0**, hvis zip er
`diff -r`-identisk med kilden — så den gamle pakke blev **droppet** i stedet for genopbygget, og der ligger stadig
præcis én zip i `plugins/`. Lærdommen står: kør zip-sammenligningen **før** push, og mål igen efter en rebase —
tal fra før et rebase er ikke status for det der udgives.

**4) Fund: setup-scriptet kunne ikke hente PHP.** `tools/setup-agent-tools.sh` fejlede med `IndentationError` i
sin `python3 -c`-streng, fordi list-comprehensionen var ombrudt med indrykning — så PHP-trinnet døde før download.
Rettet til én linje og efterprøvet: den vælger nu `php-8.5.8-cli-linux-x86_64.tar.gz`, altså samme minor som
produktionen. `bash -n` er grøn.

**5) Blokeret — uændret:** S0.2/S0.6 (robots-ejerskab og GSC Crawl stats kræver ejerens login), S0.4 (én
Redirection-gruppe), S1.1/S1.2 (Printify-varianter), S2.7's resterende del (ejerbeslutninger), S3.1–S3.4 og
S4.1–S4.13 (ejerklik, betaling/forsendelse eller live-skrivning), plugin P1.5 (Pinterest-token 401 + Meta-review)
og P3.1 (`labor_rate`). **Live-skrivning kræver et nyt OAuth-token i denne sandkasse**
(`python3 tools/mcp_oauth.py --start` → ejerens klik → `--finish '<kode>'`).

**6) Næste ugentlige kontrol:** samme kommandoer plus zip-sammenligningen — og den skal køres **efter** en rebase,
så en zip der er ældre end kilden ikke når at blive udbudt til selvopdatering.

## 2026-11-06 — roadmap-status (ingen backupkørsel i dette pas)
**Formål:** S3.5 kræver at loggen følger roadmap'ets checks, ikke kun backup. Denne post samler ugens målte
tal og de blokeringer der står mellem roadmap'et og næste procent. Backup blev **ikke** kørt i dette pas, og
det står her i stedet for at blive skjult.

**1) Status fra `projektstatus.json` (beregnet, ikke håndskrevet):**
- steamgears.eu — site-optimering: **56,3 %** (13,4 af 23,8 d)
- Steamgears Autopilot — plugin: **74,0 %** (19,9 af 26,9 d)
- Dark academia-rebrand: **63 %** (8,5 af 13,5 t)
- SEO-indholdsproduktion: **62,5 %** (5 af 8 guides)
- Døde internlinks: **100 %** (39 af 39 links)

**2) Værn og test:**
- `tools/natlige-vaern.sh`: **17 af 17 grønne** (PHP-lint, projektstatus, commit-værn, smoke, MCP, linkplan,
  guide-copy, niche-scanner og PDF-render).
- Smoke: **530 PASS / 0 FAIL**. De seks nyeste checks dækker chat-loopet i både OpenAI-kompatibelt og
  Anthropic wire-format; den syvende er site-seo-rettelsen fra 6/11.
- Integration: **249 PASS** mod WordPress 7.1/latest og **184 PASS** mod 6.4 ved seneste kørsel. Ikke genkørt
i dette pas, fordi MySQL ikke kører i sandkassen (port 3306 lukket).
- Zip 7.41.1 er `diff -r`-identisk med kilden, og der ligger præcis én zip i `plugins/`.

**3) Lukket eller målt siden 25/10:**
- **S2.2:** `/shipping/` (1396) og `/returns/` (1397) er publiceret, målt 200 og linket fra footeren (S2.3).
- **S0.9:** dobbelt analytics er nu **bevist**, ikke antaget: to `g/collect`-kald med `en=page_view`, samme
  `cid` og samme session fra `G-3MYLFKY8MY` og `G-0HC9BLQ88R` (Site Kit via `GT-PL9L67B4`). Fjernelse venter
  på ejerens valg og skriveadgang.
- **P3.2:** keycheck-tests for Anthropic og custom OpenAI-kompatibel provider; testen afslørede og fik rettet
  en 12-timers modelkatalog-cache, der overlevede et base-URL-skift.
- **Site-seo fatal:** fire URL'er brugte `home` i stedet for `$home`; rettet med en smoke-check, så fejlen
  ikke kan vende tilbage uden at testen dør.

**4) Blokeret — og hvad der skal til:**
- **S0.4:** én Redirection-gruppe i wp-admin; derefter kan de fem 301'er oprettes.
- **S0.2 / S0.6:** robots.txt-ejerskab kræver WordPress-adgang; GSC Crawl stats kræver ejerens login.
- **S1.1 / S1.2 / S1.4 / S1.5 / S1.6 / S2.5 / S2.7 / S2.8:** live-skrivning eller Printify-varianter.
- **S3.1–S3.4 / S4.1–S4.13:** ejerklik, betalings-/forsendelsesopsætning eller live-skrivning.
- **Plugin P0.4:** rigtig host-cron; **P1.5:** Pinterest-token og Meta-review; **P3.1:** ejerens `labor_rate`.

**5) Næste ugentlige kontrol:** kør `python3 projektstatus.py --check`, `bash tools/natlige-vaern.sh` og
zip-sammenligningen igen; opdatér kun denne log når tallene faktisk er målt. Næste indholdselement vælges
først når der er skriveadgang eller en ejerbeslutning, så loggen ikke lover arbejde der ikke kan udføres.

## 2026-10-25 ~12:35 (søndag 11:00-run)
**1) Backup-verificering:**
- 3:30-schedule (625f5d7f) havde IKKE uploadet til Drive (nyeste zip på Drive var 18/10; /workspace/tmp/backup var halvfærdig: pages.json truncatet 15 KB, media/ tom).
- Fuld backup taget manuelt nu: /workspace/tmp/backup/ — posts.json (11 guides, alle publish), pages.json (19), products.json (86 via Woo REST), categories.json (5), media.json (91, pagineret 100+fetch side 2 rettet) — alt valideret med jq.
- 91 mediefiler downloadet til media/ (24 MB, alle >1 KB; ae-07/ae-11 er små Exif-JPG'er men gyldige).
- Zip i 2 dele (50-items-grænse): /downloads/steamgears-site-backup-2026-10-25-part1.zip (22.9 MB: JSONs + 43 media) + part2.zip (1.9 MB: 48 media).
- Uploadet til Drive-backupmappe 1y_PodmLaCF (ids 1rel3daC… / 1BiyDoO8…).
- Nye design-filer syncet til Drive billedmappe 1aUhfZ1i: 5 Pirat Piger master-PNG'er (01-05) fra /downloads/pirat-piger-v1/.
- Note: 3:30-rutinen fejlede stille denne søndag (scriptet fandtes, last_run sat) — dens prompt bruger gammelt auth-format; overvej at opdatere prompten (Basic-headeren for Humle91 i prompten matcher stadig, men media-download/zip-trinene virkede ikke). Følges op.

**2) Indholdselement: intern linkring til ny guide /pirate-cosplay-for-beginners/ (post 751, udgivet i dag 11:47):**
- Siderne pegede 0 steder på den nye guide. Tilføjet 3 kontekstuelle interne links via WP REST (Juno-konto, PUT):
  - post 8 (How to Make a Steampunk Costume): nyt listepunkt i budget-sektionen → pirate cosplay guide
  - post 699 (Outfit Ideas for Women): paragraph efter "Three Rules" → Look 4 → pirate build
  - page 713 (Steampunk Halloween): nyt guide-kort "Pirate Cosplay for Beginners" i DIY Workshop-grid'et
- Verificeret live på alle 3 URLs (grep på rendered HTML = 2 hits hver, link + oembed/Kessok? begge tæller som link fundet).
- Ingen kritiske indstillinger/URLs rørt → ingen Henning-godkendelse nødvendig pr. v2.1.
