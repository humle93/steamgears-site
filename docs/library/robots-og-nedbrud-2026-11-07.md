# Robots-filen og nedbruddet 7/11 2026

Dette dokument handler om to ting: hvem der ejer `robots.txt` på steamgears.eu (roadmap-række **S0.2**), og
det **nedbrud jeg selv forårsagede** undervejs — hvad der skete, hvordan det rettes, og hvilken regel der
forhindrer gentagelsen.

> **Status da dette blev skrevet:** sitet svarer **fatal parse-fejl** på alle dynamiske URL'er
> (`/?nocache=1`, `/shop/`, `/cart/`, `/wp-json/…`). Kun W3TC's cachede forside virker. Fixen tager under et
> minut og kræver kun fil-adgang — se §4.

## 1. Hvem ejer robots.txt (målt)

| Lag | Hvad det gør | Bevis |
| --- | --- | --- |
| **Rank Math SEO 1.0.281** | Genererer filen dynamisk (`robots_txt`-filteret). Der findes **ingen** fysisk `robots.txt` i webroden — `rank-math/get-robots-txt` svarer `exists: false` | Første linje i filen: `# This file is automatically added by Rank Math SEO plugin…` |
| **`managed-wordpress-robots.php`** (mu-plugin, host-laget) | Tilføjer otte `Disallow`-linjer for query-parametre (`filter_cat`, `min_price`, `orderby`, …) | Filen ligger i `wp-content/mu-plugins/` og hooker `robots_txt` |

Filen havde derfor **to `User-agent: *`-grupper**: Rank Maths gruppe øverst, Sitemap-linjen, og derefter
mu-pluginnets gruppe. Det er teknisk lovligt — RFC 9309 §2.2.1 siger at grupper med samme user-agent slås
sammen — men det gør filen svær at læse, og de otte regler stod **efter** Sitemap, hvor de let overses.

## 2. Rettelsen, der var planlagt

`managed-wordpress-robots.php` skulle indsætte sine linjer **i** Rank Maths gruppe (før `\n\nSitemap:`)
i stedet for at åbne en ny gruppe. Den korrigerede fil ligger i repoet som
[`wordpress/mu-plugins/managed-wordpress-robots.php`](../../wordpress/mu-plugins/managed-wordpress-robots.php)
og er idempotent: står reglerne der allerede, røres filen ikke.

## 3. Nedbruddet — hvad der gik galt

Jeg skrev den nye fil med `novamira/execute-php`, men med filindholdet som en **dobbelt-citeret PHP-streng**:

```php
$src = "<hele filens indhold>";
file_put_contents($dst, $src);
```

PHP interpolerer `$variable` inde i dobbelt-citerede strenge, så `$output`, `$rules` og `$block` blev
erstattet med **tomme strenge**. Den skrevne fil fik derfor linjer som ` = array(` og `if (strpos(, ) !== false)`
— en parse-fejl på linje 13. Resultatet er ikke en advarsel men en **E_COMPILE_ERROR ved hvert request**, fordi
mu-plugins indlæses før alt andet. Siden da har alle dynamiske sider svaret med fejlen; kun W3TC's cachede
HTML slap igennem.

To ting gjorde det værre:

1. Kaldet **rapporterede succes** (`bytes: 1222`), fordi `file_put_contents` jo godt kunne skrive den ødelagte
   streng. Jeg tjekkede ikke den skrevne fils indhold, før jeg gik videre.
2. Da jeg ville rette det med et nyt kald, svarede MCP `server_unsupported` — REST-API'et var selv dødt af
   parse-fejlen. **En fejl i et mu-plugin kan ikke rettes over MCP**, fordi MCP'en kører gennem det samme PHP.

## 4. Sådan rettes det (under et minut, kræver ikke PHP)

Vælg én af tre veje i Simplys kontrolpanel (Filhåndtering eller SFTP):

| Vej | Handling | Resultat |
| --- | --- | --- |
| **A — slet filen** | Slet `wp-content/mu-plugins/managed-wordpress-robots.php` | Sitet er tilbage med det samme. Robots.txt mister de otte query-param-regler; Rank Maths regler og Sitemap bliver stående |
| **B — genskab backup** | Kopiér `wp-content/novamira-sandbox/s0-2-backup-managed-wordpress-robots.php` til `wp-content/mu-plugins/managed-wordpress-robots.php` | Præcis den gamle tilstand: to grupper, men ingen fejl |
| **C — læg den rettede fil** | Kopiér [`wordpress/mu-plugins/managed-wordpress-robots.php`](../../wordpress/mu-plugins/managed-wordpress-robots.php) ind som filen | S0.2 er løst: én gruppe og reglerne før Sitemap |

Den gamle fils indhold, ordret, hvis hverken backup eller repo er ved hånden:

```php
<?php
add_filter('robots_txt', function ($output) {
    return rtrim($output) . '

User-agent: *
Disallow: /*?*filter_cat=
Disallow: /*?*min_price=
Disallow: /*?*max_price=
Disallow: /*?*orderby=
Disallow: /*?*stock_status=
Disallow: /*?*sale_status=
Disallow: /*?*filter_brand=
Disallow: /*?*et_columns-count=
';
});
```

Efter indgrebet skal `https://steamgears.eu/robots.txt` svare én `User-agent: *`-gruppe (vej C) eller to
(vej A/B), og `/wp-json/` skal igen svare `application/json`.

**Vej C er verificeret offline, før den lægges på sitet:** filen er `php -l`-ren, og
[`tools/test_robots_muplugin.php`](../../tools/test_robots_muplugin.php) kører otte checks mod den — én
`User-agent`-gruppe, de otte regler før Sitemap, urørt Sitemap-linje, idempotens ved andet genløb, ingen
blank-linje-skade og en fallback når der ikke er nogen Sitemap-linje. Resultat: **8 PASS / 0 FAIL**. Kør
`./.tools/php tools/test_robots_muplugin.php` for at gentage det.

## 5. Reglen der forhindrer gentagelsen

- **PHP-filindhold må aldrig indsættes i en dobbelt-citeret PHP-streng.** Brug `base64_decode('…')` (som
  andet forsøg gjorde) eller en nowdoc, og skriv først **efter** at strengen er tjekket for `$`.
- **Skrivning er ikke det samme som succes.** Efter en filskrivning: læs filen tilbage, sammenlign `sha1` med
  kilden, kør `php -l`-ækvivalenten (`token_get_all(..., TOKEN_PARSE)`) og hent den URL der bruger filen.
  Et `bytes`-tal beviser kun at disken tog imod noget.
- **Et mu-plugin er det farligste sted at skrive.** Det indlæses før alle plugins og før MCP, så en fejl der
  fjerner den eneste kanal der kunne rette den. Ændringer i `mu-plugins/` skal skrives til en **ny** fil med
  et andet navn først, verificeres, og først derefter erstatte den gamle — eller lægges i sandkassen.
- **Host-laget skal ikke ændres for en roadmap-række.** `managed-wordpress-robots.php` er Simplys fil; S0.2
  kunne være lukket som «målt: to grupper, teknisk gyldigt, ejer dokumenteret» uden at røre den.

## 6. Hvad der ikke blev ændret

Intet andet på sitet blev rørt i dette pas: ingen poster, ingen plugin-tilstand, ingen indstillinger. Den
eneste live-ændring var filen i §3. Repoet indeholder den rettede fil og dette dokument.
