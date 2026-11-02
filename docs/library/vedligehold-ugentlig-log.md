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

## 2026-11-02 — autonomt pas (navigation, footer, meta, bot-adgang)

- **Header:** `Start Here` og `Build` tilføjet til site-wide navigation (template part 12). Verificeret på `/`, `/shop/`, `/faq/`, `/journal/`.
- **Footer:** `/right-of-withdrawal/` og `/opt-out-preferences/` tilføjet i Legal-kolonnen (part 52). Verificeret på `/` (begge links findes), W3TC purget.
- **Meta:** 15 indekserbare sider og indlæg fik titel, description og fokusnøgleord; 0 huller tilbage. Se [navigation-og-meta-2026-11-02.md](navigation-og-meta-2026-11-02.md).
- **Sitemap:** 121/121 URL'er svarer 200 (S0.7).
- **Interne links (S2.6-kontrol):** de tre guides `/steampunk-cosplay-for-beginners/`, `/how-to-make-a-steampunk-costume/` og `/steampunk-cosplay-gallery/` linker alle til `/start-here/`, `/build/` og `/shop/`.
- **Bot-adgang (ny finding, S0.6):** hosten Simply.com svarer **455 «Security Incident Detected»** på Googlebot- og bingbot-user-agents for alle sider, inkl. `/robots.txt` og `/sitemap_index.xml`. Browser-UA'er får 200. Det er hostens bot-regel, ikke SeedProd Coming Soon (plugin 6.20.10, som er aktivt). Det kan ikke rettes via MCP eller repoet. Se roadmap S0.6.
