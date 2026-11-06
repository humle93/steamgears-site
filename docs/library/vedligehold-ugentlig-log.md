# Ugentlig backup + 1 indholdselement — log

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
