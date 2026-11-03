# WordPress & WooCommerce Plugin Rules (AGENTS.md)

## 1. Arbejdsform (Roadmap -> 100% Færdigt Plugin)
- Start ALDRIG på koden før du har præsenteret et Roadmap med:
  1. Komplet filstruktur for pluginnet.
  2. Estimeret tidsforbrug og procentvis opdeling (0% -> 100%).
- Når Roadmappet er godkendt: Stop IKKE undervejs. Skriv samtlige filer færdige uden placeholders eller `// TODO`.
- Angiv status ved hvert skridt (f.eks. `[50% - REST API & Admin UI færdig]`).

## 2. WordPress & PHP Arkitektur (KRAV)
- PHP 8.2+ kompatibel, objektorienteret struktur (OOP) eller tydeligt namespacede klasser.
- Hovedfilen skal indeholde gyldig WordPress Plugin Header og starte med: `if ( ! defined( 'ABSPATH' ) ) exit;`
- Sikkerhed: Brug altid `check_ajax_referer()` / `wp_verify_nonce()`, `current_user_can( 'manage_options' )`, `sanitize_text_field()` på input og `esc_html()` / `esc_url()` på output.
- Database: Brug `$wpdb->prepare()` ved custom tabeller (`dbDelta` ved aktivering) eller WordPress Options/Transients API.
- SEO / AEO / GEO: Hvis pluginnet genererer frontend-indhold eller produktsider, skal det understøtte gyldig JSON-LD Schema.org struktur og hurtig indlæsning (kun enqueue scripts på relevante sider).

## 3. Automatisk Test & Pakning (.zip)
- Efter hver PHP-fil er skrevet, SKAL du køre syntakstjek i terminalen:
  `find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*" -exec php -l {} +`
- Findes der ingen `php` i miljøet (gælder Freebuff/cloud), brug den statiske binær i `.tools/php` (PHP 8.3, hentes fra `https://dl.static-php.dev/static-php-cli/common/php-8.3.14-cli-linux-x86_64.tar.gz` og pakkes ud, så binæren ligger som `.tools/php`). `.tools/` er ikke i git:
  `find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*" -exec ./.tools/php -l {} +`
- Hvis `php -l` melder fejl, skal du rette fejlen med det samme, før du går videre.
- Som sidste trin (100%): Pak hele pluginnet i en installationsklar `.zip`-fil i `plugins/` (udelad `.git`, `AGENTS.md`, `node_modules` og `.tools`), så den kan uploades direkte under *Plugins -> Tilføj nyt* i WordPress.
- ZIP-filen SKAL ligge i `plugins/` på `main` og hedde `steamgears-autopilot-v<version>.zip`. Selvopdateringen henter præcis `https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/steamgears-autopilot-v<version>.zip` (`SGAP_Updater::package_url()`), så en zip i rodmappen eller på en sidegren bliver aldrig fundet.
