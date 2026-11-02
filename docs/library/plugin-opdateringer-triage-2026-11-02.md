# Plugin-opdateringer — hvilke af de 16 er sikre at køre (2/11 2026)

_Opfølgning på [`sikkerhed-plugin-versioner-2026-11-01.md`](sikkerhed-plugin-versioner-2026-11-01.md) · kun læsende opslag (wordpress.org plugin-API 2/11, changelogs) · intet er opdateret på sitet endnu_

## TL;DR

- **8 af de 16 kan køres nu** i ét batch på ~15 minutter. **5 kræver et vindue med databasebackup.** **2 bør fjernes i stedet for opdateres.** **1 er allerede lukket** (`ai-engine` kører 3.8.9 og er inaktiv, verificeret 1/11).
- **Kompatibilitet er ikke flaskehalsen.** Produktion kører **PHP 8.5.12 / WordPress 7.1.4** (`roadmap.md` S0.3), og alle 16 kræver højst PHP 8.3, alle er testet til 7.1.4. Intet er blokeret af platformen.
- **Tre ting i changelogs ændrer billedet fra auditten:**
  1. **`seo-engine` 1.0.0 retter en stored XSS** (titler/beskrivelser renderet som rå HTML i admin-preview, insights og JSON-LD). Auditten førte den som »ingen kendte sårbarheder«, fordi WPScan ikke har posten — den hører i sikkerhedsbunken.
  2. **`vibe-ai` 1.21.2–1.21.3 retter sikkerhed i SeedProd-builder-sign-in** (Super Admin-begrænsning, afviste fil-uploads). Samme mønster: ikke i WPScan, men i changeloggen.
  3. **`media-file-renamer` 6.3.4 retter en PHP 8.5-deprecation i REST API'et** — og produktion kører netop 8.5.12. Opdateringen har konkret værdi her.
- **`really-simple-ssl` 9.8.4 giver nul gevinst** på et enkelt-site: den eneste ændring er, at kun network admins kan deaktivere pluginnet på **multisite**.
- Den dyreste fejl at lave er **`media-file-renamer`** (flytter filer og dermed URL'er) og **`mainwp` + `mainwp-child`** (skal følges ad, ellers mister backup-funktionen sin modpart).

## 1. Metode og forbehold

| Trin | Kilde |
| --- | --- |
| Nyeste version, `requires_php`, `tested`, changelog | `api.wordpress.org/plugins/info/1.2`, hentet 2/11 2026 |
| Installeret version (23/10) | `backup-2026-10-23/plugins.json` via `sikkerhed-plugin-versioner-2026-11-01.md` |
| Produktionens PHP/WP | `docs/roadmap.md` S0.3: PHP 8.5.12, WP 7.1.4 |
| Sårbarheder | WPScan + plugin-changelogs (changeloggen fangede to sager WPScan ikke har) |

**Forbehold:**

1. **Installerede versioner er 10 dage gamle.** Sitet kan være auto-opdateret siden 23/10 — aflæs de faktiske versioner i wp-admin → Plugins før batchen. Konklusionen pr. plugin ændrer sig ikke, men rækkefølgen kan.
2. **Ingen live-adgang fra dette workspace.** Der er ingen MCP-forbindelse her, så alt om sitets tilstand er dokumentbaseret.
3. **`woocommerce` står som inaktiv (11.2.1) i snapshotet**, men butikken svarer på Store API og `printify-for-woocommerce` er aktiv. Det kan ikke begge være rigtigt — afklar i wp-admin, fordi det afgør, om Woo-satellit-plugins overhovedet er relevante.
4. WPScan dækker kun offentligt kendte sager. Changelogs er den bedste sekundære kilde, men de er ikke en garanti.

## 2. Dommen pr. plugin

| # | Plugin | 23/10 → nyeste | Ændringens art | Dom |
| --- | --- | --- | --- | --- |
| 1 | `ai-engine` | 3.8.6 → 3.8.9 | Sikkerhed (CVE-2026-110732) | **Lukket** — 3.8.9 installeret og inaktiv 1/11 |
| 2 | `seo-engine` | 0.9.8 → **1.0.0** | **Stored XSS-rettelse** + Meow-dashboard, sitemap-ændringer | **Kør nu** — verificér sitemap bagefter |
| 3 | `vibe-ai` | 1.20.5 → 1.21.3 | **Sikkerhed** (SeedProd sign-in) + ability-discovery | **Kør nu** — men afklar MCP-ejerskab samtidig |
| 4 | `wplr-sync` | 6.5.8 → 6.6.0 | Sikkerhed (Author+ IDOR, 4.9) | **Kør nu** |
| 5 | `svg-support` | 2.6.1 → 2.6.3 | Sikkerhedshærdning af SVG-uploads | **Kør nu** |
| 6 | `wpforms-lite` | 2.0.3 → 2.0.3.1 | Patch | **Kør nu** |
| 7 | `meow-workflow` | 0.1.6 → 0.1.7 | 0.x-patch | **Kør nu** |
| 8 | `microsoft-clarity` | 0.10.36 → 0.10.37 | Analytics-tag | **Kør nu** |
| 9 | `really-simple-ssl` | 9.8.3 → 9.8.4 | Kun multisite-rettelse | **Kan køres, men giver intet** her |
| 10 | `database-cleaner` | 1.4.4 → 1.4.8 | Sikkerhed (admin-only, 8.7) | **Vindue + DB-backup** |
| 11 | `mailpoet` | 5.43.0 → 5.44.0 | Features + **DB-migration** | **Vindue + DB-backup** |
| 12 | `mainwp` | 6.3 → 6.4 | Kræver PHP 8.3+, beta-backupmodul | **Vindue, sammen med #13** |
| 13 | `mainwp-child` | 6.3 → 6.4 | Skal matche dashboardet | **Vindue, sammen med #12** |
| 14 | `media-file-renamer` | 6.3.3 → 6.3.5 | **PHP 8.5-fix** + WPML-filnavne | **Vindue** — flytter filer |
| 15 | `pinterest-for-woocommerce` | 1.6.0 → 1.6.1 | Inaktiv | **Fjern** |
| 16 | `woocommerce-services` | 3.7.3 → 3.7.4 | Inaktiv | **Fjern** |

## 3. De 8 der kan køres nu

Ingen af dem har en database-migration, et filsystem-løft eller et major-hoft, og alle er sikre på PHP 8.5 / WP 7.1.

- **`seo-engine` 1.0.0** — den vigtigste i denne bunke, fordi den retter en stored XSS i admin-preview og JSON-LD. 0.9.8 → 1.0.0 ser ud som et major-hoft, men changeloggen viser en normal feature+fix-release fra Meow Apps (samme udvikler som `ai-engine`, `media-file-renamer`, `meow-workflow`, `meow-analytics` — fem af de 16 plugins er fra én udvikler). **Verificér sitemap bagefter:** 1.0.0 ændrer sitemap-ekskluderinger og fjerner attachments fra sitemap.
- **`vibe-ai` 1.21.3** — retter SeedProd-sign-in (Super Admin-grænse på multisite, afviser fil-uploads) og lader WPVibe køre abilities der kun er annonceret til AI-agenter. Den hører sammen med MCP-oprydningen: der er tre MCP-agtige plugins (`ai-engine`, `mcpwp`, `vibe-ai`), og det er stadig uafklaret hvilket der ejer `/mcp/v1`.
- **`wplr-sync` 6.6.0** — lukker IDOR-sagen (4.9, Author+). Lav alvor, men Author er en let rolle at få.
- **`svg-support` 2.6.3** — SVG-uploads er en XSS-vej, og pluginnet er selve forsvarflaget. Billig forsikring.
- **`wpforms-lite` 2.0.3.1** — ren patch. Bemærk at Lite ikke kan oprette formularer via MCP, så fortrydelsesformularen er stadig et separat punkt.
- **`meow-workflow` 0.1.7**, **`microsoft-clarity` 0.10.37`** — små patches; Clarity er kun en tag-injektor og er den eneste af de 16 der kun er testet til WP 7.0.8, hvilket er uden praktisk betydning for et script-tag.
- **`really-simple-ssl` 9.8.4** — sikker at køre, men den eneste ændring er multisite-deaktivering. På et enkelt-site er gevinsten nul; tag den med i batchen for at få den væk fra listen.

## 4. De 5 der kræver et vindue

Fælles for dem: **tag databasebackup først** (Simply-panelet eller den eksisterende `backup-2026-10-23`-rutine), og opdatér én ad gangen så en fejl kan henføres.

| Plugin | Hvorfor vindue | Hvad der konkret kan gå galt |
| --- | --- | --- |
| `mailpoet` 5.44.0 | Changeloggen siger eksplicit at **databaseopdateringer nu starter lige efter plugin-opdateringen**, og at fejlede DB-opdateringer rapporteres | Går migrationen i stå, står nyhedsbreve og abonnentlister stille. Verificér abonnenttal + send én testkampagne til egen adresse |
| `database-cleaner` 1.4.8 | Rettelsen er admin-only (8.7), men det er et **værktøj der sletter databasetabeller** | Sagen kræver allerede admin, så den er ikke en vej ind. Opdateringen er lav risiko — men kør den ikke samtidig med andet DB-arbejde |
| `mainwp` 6.4 | **Kræver PHP 8.3+** (produktion har 8.5.12 ✓) og tilføjer Built-In Backups (beta), som **kræver den matchende `mainwp-child`-release** | Opdateres kun dashboardet, mister backup-funktionen sin modpart på child-sitet. **Opdatér #12 og #13 i samme vindue** — og beslut om `mainwp-child` overhovedet skal være der (den er inaktiv) |
| `media-file-renamer` 6.3.5 | Pluginnet **flytter filer og dermed URL'er**; 6.3.4 retter desuden en PHP 8.5-deprecation i REST API'et, som produktionen faktisk rammer | Er »auto rename on upload« slået til, får nye uploads nye filnavne. **Kør aldrig bulk-rename** — det ville brække de 39 døde links-arbejde og alle indekserede billed-URL'er. Tjek indstillingen før og efter |
| `mainwp-child` 6.4 | Se ovenfor; inaktiv, men filerne ligger i `wp-content/plugins` og er web-tilgængelige | Opdateres den ikke sammen med dashboardet, kan backup/restore fejle subtilt |

## 5. De 2 der bør fjernes i stedet

`pinterest-for-woocommerce` 1.6.0 og `woocommerce-services` 3.7.3 er **inaktive**. Inaktiv betyder ikke usynlig: PHP-filerne kan rammes direkte, og de bliver først opdateret når nogen husker det. Auditten anbefaler allerede oprydning i de syv inaktive (§2.5).

**Undtagelse:** hvis Pinterest faktisk skal bruges til butikken, eller hvis WooCommerce-services er i brug, så **aktivér og opdatér** i stedet — men så skal `woocommerce`-status afklares først (forbehold 3).

## 6. Rækkefølge og tidsestimat

| Batch | Indhold | Tid |
| --- | --- | --- |
| **1. Sikkerhed + patches** | `seo-engine`, `vibe-ai`, `wplr-sync`, `svg-support`, `wpforms-lite`, `meow-workflow`, `microsoft-clarity`, `really-simple-ssl` | 15 min |
| **2. Vindue med backup** | `mailpoet`, `database-cleaner`, derefter `mainwp` + `mainwp-child` sammen, til sidst `media-file-renamer` | 45 min + backup |
| **3. Oprydning** | Slet `pinterest-for-woocommerce`, `woocommerce-services` og de øvrige ubrugte inaktive; afklar MCP-ejerskab | 20 min |

Efter batch 1 er **0 af de 16 tilbage med en kendt åben sårbarhed**.

## 7. Verifikation efter hver batch (kun læsende)

- [ ] Forside + `/shop/` svarer 200, og `/shop/` er stadig under ~6 s
- [ ] `sitemap.xml` og `product-sitemap.xml` indeholder stadig alle 39 produkter (`seo-engine` rører sitemap)
- [ ] `/wp-json/`-indekset svarer, og `sgap/v1` + `wc/v3` er stadig registreret
- [ ] Fortrydelses-/kontaktformularen renderer stadig (`wpforms-lite`)
- [ ] Clarity-tag og GA4 stadig til stede — og stadig **kun ét** GA4-property (dubletten fra site-check 1/11 er et separat punkt)
- [ ] MailPoet: abonnenttal uændret, testkampagne afsendt
- [ ] Et kendt produktbillede svarer 200 på sin gamle URL (`media-file-renamer`)
- [ ] `/wp-json/sgap/v1/snapshot` svarer som før (Autopilot deler AI-nøgle-sti med AI Engine)
- [ ] wp-admin → Plugins viser de nye versioner, og ingen plugin står med »update failed«

## 8. Det kan ikke afgøres herfra

1. De **faktisk installerede** versioner (snapshot 23/10 — aflæs i wp-admin).
2. Hvilket plugin der ejer `/mcp/v1` — og om MCP overhovedet skal være slået til.
3. Om »auto rename on upload« er slået til i `media-file-renamer`.
4. Om `woocommerce` er aktiv eller ej (modstrid mellem snapshot og Store API).
5. Om `mainwp` bruges som dashboard for andre sites — det afgør, om parret skal opdateres eller fjernes.

## 9. Kilder

- wordpress.org plugin-API (version, `requires_php`, `tested`, changelog), hentet 2/11 2026 for alle 16 slugs.
- [`sikkerhed-plugin-versioner-2026-11-01.md`](sikkerhed-plugin-versioner-2026-11-01.md) — de 48 plugins, de tre sager, metoden.
- [`sikkerhed-ai-engine-2026-11-01.md`](sikkerhed-ai-engine-2026-11-01.md) — CVE-2026-110732, lukket 1/11.
- [`../roadmap.md`](../roadmap.md) S0.3 — produktionens PHP 8.5.12 / WP 7.1.4.
- WPScan: `https://wpscan.com/plugin/<slug>/`.
