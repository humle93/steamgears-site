# Genaktivering og oprydning på steamgears.eu — 7/11 2026

**Målt:** 7/11 2026, ca. 18:35–19:05 UTC, gennem Novamira (full_access) og offentlige HTTP-kald.
**Spørgsmål dette dokument svarer på:** hvorfor svarede hele sitet 500, mens `/wp-json/` svarede 200 — og
hvilke plugins skal køre, og hvilke kunne fjernes?

---

## 1. Udgangspunktet: to ting var slået fra, og den tredje var en cachet fejlside

| Måling | Svar |
| --- | --- |
| `/` , `/shop/` , `/?nocache=1` | **HTTP 500** |
| `/wp-json/` | **200** — PHP kørte altså |
| Fejlteksten | `Uncaught Error: Call to undefined function is_woocommerce()` i `mu-plugins/sga-payload-trim.php:134` |
| `Steamgears Autopilot` | **7.58.0, inaktivt** (`/wp-json/sgap/v1` → 404) |
| `WooCommerce` | **11.2.2, inaktivt** — derfor findes `is_woocommerce()` ikke |

Tre ting skal holdes fra hinanden, fordi de peger i hver sin retning:

1. **Pluginnet var ikke opdateret forkert — det var slået fra.** Update-klikket var sket (7.58.0 lå på disken),
   men aktiv tilstand er en anden indstilling. `plugin list` og `get_plugins()` viser begge `inactive`.
2. **500-fejlen kom ikke fra Autopilot.** Den kom fra et mu-plugin, der kalder en Woo-funktion uden at tjekke, om
   WooCommerce er indlæst. Det er samme fil, der tidligere har kostet et nedbrud (dobbeltdeklaration).
3. **Fejlen blev serveret fra cachen, længe efter den var død.** W3 Total Cache havde gemt den anonyme HTML for
   forsiden. Da WooCommerce blev aktiveret, svarede serveren selv **200** på `home_url('/')` gennem
   `wp_remote_get()`, mens udefrakommende kald stadig fik **500**. Først efter `w3tc_flush_all()` vendte
   forsiden, `/shop/` og `/cart/` tilbage til 200.

**Lærdom:** en 500 er ikke bevis på, at fejlen stadig findes. Sammenlign et kald udefra med et kald fra serveren
selv, og tøm sidecachen, før der debugges videre.

## 2. Hvad der blev gjort, i den rækkefølge der virker

```text
1. wp plugin install .../plugins/steamgears-autopilot-v7.58.2.zip --force
   (7.58.0 tog hele REST ned: SGAP_Admin::NS findes ikke — rettet i 7.58.1/7.58.2)
2. wp plugin activate woocommerce           → is_woocommerce() findes igen
3. w3tc_flush_all()                         → den cachede fatale side forsvandt
4. wp plugin activate steamgears-autopilot seo-by-rank-math redirection woocommerce-gateway-stripe
5. wp plugin activate google-listings-and-ads wp-mail-smtp
```

Efterkontrol (målt 19:05 UTC): `/` 200 · `/shop/` 200 · `/cart/` 200 · `/checkout/` 302 (tom kurv) ·
`/wp-json/` 200 · `/wp-json/sgap/v1` **200 med 19 ruter**, herunder `/facebook/refresh`, `/facebook/reply` og
`/facebook/hide`. `steamgears-autopilot/status` svarer `version 7.58.2`, `autopilot: true`, ti kanaler,
`publish_mode: rss`, Publer og AI konfigureret. Kø-tabellen har 30 publicerede poster og ingen ventende.

## 3. Hvilke plugins skal køre

Aktiveret fordi butikken ikke fungerer uden dem:

| Plugin | Hvorfor |
| --- | --- |
| WooCommerce 11.2.2 | selve butikken; uden den fataler mu-pluginnet |
| WooCommerce Stripe Gateway | butikkens eneste betalingsmulighed |
| Rank Math SEO | sidens titler, metabeskrivelser og sitemap er bygget på det |
| Redirection | 301-reglerne, som S2.3/S4-arbejdet refererer til |
| Google Listings & Ads | `gla_merchant_id` = 5893937205, og AW-18518250875 er den eneste Ads-konvertering tilbage |
| WP Mail SMTP | ordre- og anmeldelsesmails; konfigurationen ligger allerede i databasen |
| Steamgears Autopilot 7.58.2 | autopiloten |

Beholdt installeret men inaktiv, fordi der ligger data i dem (de slettes ikke uden en beslutning):

| Plugin | Data |
| --- | --- |
| WPCode Lite | **6 snippets** i databasen (`wpcode`-poster) |
| Site Kit by Google | GSC-forbindelse (`googlesitekit_db_version` 1.185.0) |
| Pinterest for WooCommerce | katalogkonfiguration |
| Website LLMs.txt | `llms.txt` findes i roden; pluginnet kan genskabe den |

## 4. Hvad der blev slettet (18 plugins)

Ingen af dem havde indhold: `royal-mcp`, `mcp-adapter`, `ms-wp-abilities` (alle tre overflødiggjort af Novamira),
`health-check`, `wppusher`, `imagify` (W3TC dækker), `really-simple-ssl` (Easy HTTPS Redirection kører),
`custom-css-js` (0 poster), `feeds-for-tiktok`, `tiktok-for-business` (ingen TikTok-option sat),
`microsoft-clarity`, `mailpoet`, `woo-update-manager`, `printful-shipping-for-woocommerce` og
`printify-for-woocommerce` (0 produktmeta, 0 options — hverken Printify eller Printful er koblet på butikken),
`wpforms-lite` (0 formularer), `svg-support` (0 SVG-vedhæftninger), `royal-links` (0 links).

Sletning fjerner filerne, ikke databasen: options og postmeta bliver liggende, så et geninstalleret plugin finder
sin konfiguration igen. Det er grunden til at de fire i §3 blev beholdt i stedet for slettet.

## 5. Det der stadig mangler

- **`health-check-troubleshooting-mode` er et must-use-plugin** og kan ikke fjernes med `wp plugin delete`; det
  kræver File Manager eller FTP.
- **mu-plugin-filen på sitet er stadig 1.3.0.** Rettelsen (1.3.1 med `function_exists`-værn) ligger i repoet og er
  dækket af `tools/test_payload_trim.php`, men Novamira skriver kun PHP i sin sandkasse (`outside_sandbox`, 403),
  så den skal lægges ind via File Manager. Indtil da er siden kun sikker, så længe WooCommerce er aktiv.
- **Ejerens nøgler:** Page access token + side-id (P2.6), Pinterest- og Instagram-token (P1.5b/c).

## 6. Kilder

- `docs/roadmap.md` §2b (deployment-status) og `docs/agent-profil.md` §3.
- `docs/library/rest-nedbrud-7-58-0-2026-11-07.md` (REST-fatalen i 7.58.0).
- Målingerne i dette dokument er taget med `novamira/run-wp-cli`, `novamira/read-file` og `curl` mod
  `https://steamgears.eu/`.
