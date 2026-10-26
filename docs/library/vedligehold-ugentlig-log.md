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
