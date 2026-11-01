# Plugin-versioner på steamgears.eu — status 1/11 2026

_Kilde: `backup-2026-10-23/plugins.json` (fra `/wp-json/wp/v2/plugins`, 48 plugins) krydset med wordpress.org's plugin-API og WPScan's sårbarhedsbase · kun læsende opslag_

## TL;DR

- **48 plugins** installeret: 41 aktive, 7 inaktive.
- **16 er bagud** i forhold til nyeste version på wordpress.org, **29 er opdateret**, **3 kan ikke vurderes** (findes ikke på wordpress.org).
- **3 plugins mangler en sikkerhedsrettelse**: `ai-engine` (to sager, hvoraf den ene er CVE-2026-110732 med CVSS 7.5), `database-cleaner` (8.7) og `wplr-sync` (4.9).
- De øvrige 13 der er bagud har **ingen kendte sårbarheder** — det er versions-nøk, ikke sikkerhed.
- Vigtigst: **`ai-engine` 3.8.6 → 3.8.7+** (se [`sikkerhed-ai-engine-2026-11-01.md`](sikkerhed-ai-engine-2026-11-01.md)). Resten kan køre i normal opdateringsrytme.

## 1. Sådan er det målt

| Trin | Metode |
| --- | --- |
| Installeret version | `/wp-json/wp/v2/plugins`-dump 23/10 2026 (kræver admin-adgang, derfor ikke live) |
| Nyeste version | `api.wordpress.org/plugins/info/1.2` pr. 1/11 2026 |
| Kendte sårbarheder | `wpscan.com/plugin/<slug>/` — alle sager hvor "Fixed in" er **højere** end den installerede version |

**Begrænsninger du skal kende:**

1. **Versionerne er 9 dage gamle.** Sitet kan være auto-opdateret siden 23/10. Live-verifikation er ikke mulig udefra:
   Simplys WAF svarer `454`/`455` på `readme.txt`, `changelog.txt` og `languages/*.pot`, og `changelog.txt` er i øvrigt
   forældet (den toppede ved 3.8.1 mens WordPress rapporterede 3.8.6). Aflæs de faktiske versioner i wp-admin.
2. **"Bagud" betyder ikke "sårbar".** Kun de tre nævnt i TL;DR har en advisory der kræver en nyere version end den
   installerede. For de 29 der kører nyeste version findes der per definition ingen åbne, kendte sager.
3. WPScan dækker offentligt kendte sager. Premium-plugins og helt nye huller kan mangle.

## 2. De tre der mangler en sikkerhedsrettelse

### `ai-engine` 3.8.6 → 3.8.9 (2 åbne)

| Sag | Alvor | Rettet i | Kræver |
| --- | --- | --- | --- |
| **CVE-2026-110732** — Missing Authorization to Administrator Account Takeover via Unauthenticated MCP OAuth Client Registration | **7.5 high** | **3.8.7** | Uautentificeret (kræver at en admin klikker på et craftet consent-link) |
| Missing Authorization | 4.3 medium | 3.8.7 | — |

Den alvorlige. Fuld gennemgang med opdateringsplan og tjekliste: [`sikkerhed-ai-engine-2026-11-01.md`](sikkerhed-ai-engine-2026-11-01.md).

### `database-cleaner` 1.4.4 → 1.4.8 (1 åben)

| Sag | Alvor | Rettet i | Kræver |
| --- | --- | --- | --- |
| Authenticated (Administrator+) Arbitrary Table Deletion | **8.7 high** | 1.4.8 | Administrator |

Høj CVSS, men kræver at man **allerede er administrator** — så den er ikke i sig selv en vej ind. Den er stadig værd at
lukke, fordi den gør en kompromitteret admin-konto (eller en ondsindet admin) i stand til at slette vilkårlige
databasetabeller. Offentliggjort 30/10 2026.

### `wplr-sync` (Photo Engine) 6.5.8 → 6.6.0 (1 åben)

| Sag | Alvor | Rettet i | Kræver |
| --- | --- | --- | --- |
| Author+ Arbitrary Content Modification via IDOR | 4.9 medium | 6.6.0 | Author |

Bemærk at 6.5.8 lukkede to tidligere sager (stored XSS og attachment deletion), så pluginnet er ikke forsømt — det er
kun IDOR-sagen fra 27/10 der mangler. Lav alvor, men Author-rollen er let at få (fx via kommentar-/bidragsyder-adgang).

## 3. Handlingsliste (prioriteret)

1. **`ai-engine` → 3.8.7+** i dag. Slå MCP fra hvis det ikke bruges. (Se den separate sag.)
2. **`database-cleaner` → 1.4.8** ved næste vedligeholdelsesvindue. Kræver admin, så ikke akut.
3. **`wplr-sync` → 6.6.0** ved næste vindue.
4. **De 13 andre bagud** (`mailpoet`, `mainwp`, `mainwp-child`, `media-file-renamer`, `meow-workflow`,
   `microsoft-clarity`, `pinterest-for-woocommerce`, `really-simple-ssl`, `seo-engine`, `svg-support`,
   `woocommerce-services`, `wpforms-lite`, `vibe-ai`) — tag dem i et batch. Ingen kendte sager, men de er billige.
5. **Ryd op i inaktive plugins.** Syv er inaktive men ligger stadig som filer i `wp-content/plugins` og er derfor
   web-tilgængelige. Slet dem der ikke skal bruges igen (fx `mainwp-child`, `google-listings-and-ads`,
   `facebook-for-woocommerce`), og opdatér resten.
6. **`vibe-ai` 1.20.5 → 1.21.3** og **`mcpwp`** bør ses efter i samme omgang som AI Engine: der er tre MCP-plugins
   installeret samtidig, og `/mcp/v1` er eksponeret. Afklar hvilket plugin der faktisk bruges.

## 4. Fuld liste (48 plugins)

Sorteret: bagud først, derefter de ikke-vurderbare, derefter de opdaterede.

| Plugin | Status | Installeret (23/10) | Nyeste | Sikkerhed |
| --- | --- | --- | --- | --- |
| `ai-engine` | active | 3.8.6 | **3.8.9** ⚠️ | **2 åbne** (7.5 + 4.3) |
| `database-cleaner` | active | 1.4.4 | **1.4.8** ⚠️ | **1 åben** (8.7) |
| `mailpoet` | active | 5.43.0 | **5.44.0** ⚠️ | ok |
| `mainwp-child` | inactive | 6.3 | **6.4** ⚠️ | ok |
| `mainwp` | active | 6.3 | **6.4** ⚠️ | ok |
| `media-file-renamer` | active | 6.3.3 | **6.3.5** ⚠️ | ok |
| `meow-workflow` | active | 0.1.6 | **0.1.7** ⚠️ | ok |
| `microsoft-clarity` | active | 0.10.36 | **0.10.37** ⚠️ | ok |
| `wplr-sync` | active | 6.5.8 | **6.6.0** ⚠️ | **1 åben** (4.9) |
| `pinterest-for-woocommerce` | inactive | 1.6.0 | **1.6.1** ⚠️ | ok |
| `really-simple-ssl` | active | 9.8.3 | **9.8.4** ⚠️ | ok |
| `seo-engine` | active | 0.9.8 | **1.0.0** ⚠️ | ok |
| `svg-support` | active | 2.6.1 | **2.6.3** ⚠️ | ok |
| `woocommerce-services` | inactive | 3.7.3 | **3.7.4** ⚠️ | ok |
| `wpforms-lite` | active | 2.0.3 | **2.0.3.1** ⚠️ | ok |
| `vibe-ai` | active | 1.20.5 | **1.21.3** ⚠️ | ok |
| `mcpwp` | active | 3.14.10 | – | – (ikke på wordpress.org) |
| `01-ext-simply` | active | 1.0.0 | – | – (ikke på wordpress.org) |
| `woo-update-manager` | active | 1.0.3 | – | – (ikke på wordpress.org) |
| `ads-txt` | active | 1.4.6 | 1.4.6 | ok |
| `seo-kiss` | active | 0.1.9 | 0.1.9 | ok |
| `antispam-bee` | active | 2.11.13 | 2.11.13 | ok |
| `broken-link-checker-seo` | active | 1.3.1 | 1.3.1 | ok |
| `coming-soon` | active | 6.20.10 | 6.20.10 | ok |
| `complianz-terms-conditions` | active | 1.4.1 | 1.4.1 | ok |
| `complianz-gdpr` | active | 7.5.6 | 7.5.6 | ok |
| `https-redirection` | active | 2.0.2 | 2.0.2 | ok |
| `edd-ecommerce-analytics` | active | 0.0.7 | 0.0.7 | ok |
| `extendify` | active | 3.2.4 | 3.2.4 | ok |
| `google-listings-and-ads` | inactive | 3.10.0 | 3.10.0 | ok |
| `health-check` | active | 1.7.1 | 1.7.1 | ok |
| `hostinger` | active | 3.0.80 | 3.0.80 | ok |
| `imagify` | active | 2.3.4 | 2.3.4 | ok |
| `jetpack` | active | 16.3.1 | 16.3.1 | ok |
| `meow-analytics` | active | 1.3.2 | 1.3.2 | ok |
| `facebook-for-woocommerce` | inactive | 3.7.8 | 3.7.8 | ok |
| `metricool` | active | 2.1.2 | 2.1.2 | ok |
| `printify-for-woocommerce` | active | 3.2 | 3.2 | ok |
| `seo-by-rank-math` | active | 1.0.280 | 1.0.280 | ok |
| `rankready-ai-llm-seo` | active | 1.4.2 | 1.4.2 | ok |
| `simple-site-verify` | active | 1.0.8 | 1.0.8 | ok |
| `google-site-kit` | active | 1.190.0 | 1.190.0 | ok |
| `universally-language-translation-multilingual-tool` | active | 1.0.11 | 1.0.11 | ok |
| `w3-total-cache` | active | 2.11.0 | 2.11.0 | ok |
| `woocommerce` | inactive | 11.2.1 | 11.2.1 | ok |
| `woocommerce-gateway-stripe` | inactive | 11.1.1 | 11.1.1 | ok |
| `wp-mail-smtp` | active | 4.10.3 | 4.10.3 | ok |
| `wplr-theme-assistant` | active | 0.5.4 | 0.5.4 | ok |

## 5. Kan ikke vurderes (ikke på wordpress.org)

| Plugin | Installeret | Hvorfor |
| --- | --- | --- |
| `mcpwp` | 3.14.10 | Ingen post i wordpress.org's API (HTTP 404) — premium/bundlet |
| `01-ext-simply` | 1.0.0 | Simply.com's eget "Site Assistant" |
| `woo-update-manager` | 1.0.3 | Bundles med WooCommerce-abonnementer |

For disse tre findes ingen offentlig "nyeste version" at sammenligne med, og WPScan har ingen post. De skal tjekkes i
wp-admin, hvor opdateringsnotifikationen kommer fra plugin-udbyderen selv.

## 6. Inaktive plugins (7)

`mainwp-child` 6.3 · `pinterest-for-woocommerce` 1.6.0 · `woocommerce-services` 3.7.3 · `google-listings-and-ads` 3.10.0 ·
`facebook-for-woocommerce` 3.7.8 · `woocommerce` 11.2.1 · `woocommerce-gateway-stripe` 11.1.1

Inaktiv betyder ikke usynlig: PHP-filerne kan stadig rammes direkte, og de bliver først opdateret når nogen husker det.
Fire af dem er WooCommerce-relaterede, og WooCommerce selv er inaktivt — overvej om hele butiks-stakken skal fjernes.

## 7. Kilder

- wordpress.org plugin-API: `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=<slug>`
- WPScan sårbarhedsbase: `https://wpscan.com/plugin/<slug>/` (fx [ai-engine](https://wpscan.com/plugin/ai-engine/), [database-cleaner](https://wpscan.com/plugin/database-cleaner/), [wplr-sync](https://wpscan.com/plugin/wplr-sync/))
- Versionsgrundlag: `backup-2026-10-23/plugins.json`
