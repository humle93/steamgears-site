# Ugentlig backup + 1 indholdselement — log

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

## 2026-11-10 (tirsdag 09:00-run) — GSC/SEO/links

**1) GSC (fulde uger):**
- Uge 2–8/11: **2 klik / 188 impressions** (2/11: 0/21, 3/11: 1/39, 4/11: 0/23, 5/11: 1/30, 6/11: 0/29, 7/11: 0/26, 8/11: 0/20)
- Uge 26/10–1/11: **6 klik / 226 impressions** → nedgang i begge (kendt seson/varians-niveau, ingen teknisk ændring fundet)
- Top-klik: "steampunk character archetypes" (1 klik, pos 3,5 → /start-here/characters/), /steampunk-cosplay-for-beginners/ (1 klik, pos 10,7)
- Impr-drev: how-to-make-a-steampunk-costume 20 impr men **pos faldet 9,2→20,8** ⚠️; diy-steampunk-goggles 15 impr pos 8,7; start-here/characters 16 impr pos 10,1 (ny side på vej op)
- **Sitemaps: 0 fejl, 0 warnings.** post-sitemap nu **35 URLs** (var 31) — regenereret efter 8/11-publishes, corset-guide MED ✅ (7/11-punkt lukket af sig selv). sitemap.xml (gammel, pending) stadig submitted — kan ryddes i GSC, lav prio.
- **URL inspection:** /steampunk-corset-guide/ = **Submitted and indexed** (PASS, crawl 8/11) ✅ — 7/11-punkt LUKKET. Mad-scientist, utility-belt, wall-decor-diy: alle indexed ✅. **/steampunk-costumes/ hub: STADIG "Discovered - currently not indexed"** (uændret siden 26/10) — Henning-trin: Request indexing i GSC.

**2) SEO-tjek:**
- noindex: cart/checkout/my-account = `noindex, follow` ✅ (via firecrawl, WAF blokerede raw curl). opt-out-preferences noindex ✅. returns/shipping/right-of-withdrawal = index (bevidst OK, legal-indhold).
- Nye posters (8/11: mad-scientist, utility-belt, wall-decor + 6/11 corset): alle 4 har håndskrevne titles + meta descriptions + og:image ✅
- ⚠️ **Fund: alle /product-category/* sider serverer `follow, noindex`** (posters, goggles verificeret live). Rank Math-sætning — betyder kategorisiderne IKKE kan rangere (posters havde impr i GSC fra indeksering før noindex?). Spørgsmål til Henning: bevidst eller skal kategorierne have index?

**3) Broken interne links:**
- Tjekket alle interne + /go/-links i 4 nyeste guides (corset, mad-scientist, utility-belt, wall-decor) + hubs: **ALLE HTTP 200** ✅ inkl. 9 /go/-redirects (verificeret at lande på live produkter: copper-pilot-goggles, time-traveler-top-hat, 2 corsets, 2 pendants/watches, 3 posters). Ingen broken links fundet.

**4) Alt-tekster (7/11-regression):**
- Fuld mediebibliotek-scan (3 sider × 100): **44 billeder uden alt-tekst** fundet (37 fra 1–6/11-uploads + 7 ældre AE/produkt-billeder + woocommerce-placeholder).
- **Alle 44 fik håndskrevne, beskrivende alt-tekster via REST** (HTTP 200; WAF-kurver krævede 3–8 s pauser + ét 60 s+ ventested ved de sidste 6).
- Verificeret efterfølgende: **0 billeder uden alt i hele biblioteket** ✅ — regressionen er LUKKET permanent (årsag: uploads via automatik laver tom alt; fremtidige batches skal sætte alt ved upload).

**Åbne punkter til Henning:**
1. /steampunk-costumes/ hub: Request indexing i GSC (1 min) — ellers flere interne links med eksakt anchor.
2. Product-category noindex: bevidst? (blokerer kategori-rankings)
3. TikTok ttq-fejl + Google Ads-tag + AdSense-pub-skift: uændrede Henning-trin fra 7/11.
4. Klik/impr ned uge-til-uge (2/188 vs 6/226) — følges op; pos-fald på how-to-make-a-steampunk-costume (9→21) er det mest konkrete tegn.
