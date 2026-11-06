# Hvorfor butikken faldt: tidslinjen, beviset og logningen der fanger næste gang

**Undersøgt 6/11 2026, ca. 22:25–23:05 UTC.** Alt nedenfor er aflæst på det levende site gennem
Novamira MCP (WP-CLI, `execute-php` og fil-aflæsning), ikke gættet ud fra repoet. Hvor beviset ikke
rakker, står der **ikke fastslået** — ikke en formodning klædt ud som en konklusion.

## 1. Konklusionen først

De fire butiks-plugins blev ikke slået fra én ad gangen af en fejl, og det var ikke WordPress'
opdateringsrutine, der glemte at slå dem til igen. De blev slået fra **i samme sekund, i én request**,
tolv sekunder efter at den sidste af dem var blevet opdateret på disken. Det er signaturen på en
**manuelt udløst bulk-deaktivering** — `deactivate_plugins()` kaldt med en liste, som
*Plugins → Deaktiver* gør det.

Hvem der gjorde det, kan **ikke** aflæses: der findes ingen brugerhandlingslog i WordPress, ingen
`debug.log`, ingen adgangslog i kontoen, og de tre MCP-logs på sitet holder alle op før 6/11.
Det, der *kan* aflæses, er hvad der skete lige før — og det peger på en administrator, der forsøgte at
reparere et wp-admin, som kastede en fatal error.

**Og den fatale fejl var vores egen.** Se §4: den er rettet i repoet i dette pas.

## 2. Tidslinjen, målt

| Hændelse (UTC) | Bevis |
| --- | --- |
| **19:54:59** — Google for WooCommerce skriver i sin log | `uploads/wc-logs/google-for-woocommerce-…log` |
| **19:55:00** — CRITICAL: `Undefined constant "home"` i `steamgears-autopilot/includes/site-seo.php:354`, kastet i `admin_init` fra `wp-admin/index.php` | `uploads/wc-logs/fatal-errors-2026-11-06-….log` |
| **19:55:46** — WooCommerce: »Automatic database update triggered … from 11.2.1«, opgaven planlægges | `uploads/wc-logs/wc-updater-…log` |
| **19:56:07** — samme databasemigrering planlægges igen | samme log |
| **19:56:11** — pluginfolderen `google-listings-and-ads` skrives, hele træet | `filemtime` på mappe + filer |
| **19:56:27** — `pinterest-for-woocommerce` skrives | samme måling |
| **19:57:02** — `woocommerce` skrives | samme måling |
| **19:57:04** — `woocommerce-gateway-stripe` skrives | samme måling |
| **19:57:14** — alle fire deaktiveres, med samme `recently_activated`-tidsstempel | optionen `recently_activated` |
| **19:59:30** — `w3-total-cache` deaktiveres | samme option |
| **ca. 21:35** — alle fire genaktiveres (forrige pas), butikken svarer igen | `WC_VERSION` 11.2.2, produkt-URL 200 |

Fire plugins skrevet på disken 19:56:11 → 19:57:04 er en **opdateringskørsel**: filerne blev pakket ud
igen, og versionerne på disken er de samme som før (11.2.2, 11.1.1, 1.6.2, 3.10.0), altså en
geninstallering af samme version — det er hvad en bulk-opdatering gør, når den kører mod de versioner
den allerede har hentet.

## 3. Hvad der er udelukket, og hvorfor

**Det var ikke WordPress' egen opdateringsrutine.** `wp-admin/includes/class-plugin-upgrader.php`
gør præcis det modsatte af hvad man skulle tro: den slår plugin'et fra **før** den installerer
(`deactivate_plugin_before_upgrade()`, linje 562–580, med `deactivate_plugins($plugin, true)`), og
slår det til igen bagefter. To ting udelukker den som forklaring:

1. Så ville deaktiveringen ligge **før** hver installation — målt 19:56:11, 19:56:27, 19:57:02, 19:57:04
   — ikke samlet bagefter kl. 19:57:14.
2. I cron springes den helt over: *»When in cron (background updates) don't deactivate the plugin, as we
   require a browser to reactivate it«* (linje 568). Så en natlig auto-opdatering kan ikke efterlade
   fire plugins slukkede.

**Det var ikke fire uafhængige fejl.** Fire plugins på samme sekund er ét kald, ikke fire uheld. Det
var heller ikke et cron-loop: `sgap_*`- og WooCommerce-cron i vinduet kørte normalt, og de fejlende
Action Scheduler-poster kl. 20:01:45 er *følgevirkningen* (»no callbacks are registered«), fordi
WooCommerce var slået fra.

**Det var ikke MainWP.** Der ligger 31 `mainwp_*`-tabeller og en log med brugeren `Humle91` og
»CRON :: check sites health«, men de nyeste rækker er fra **23/10**, og der er hverken plugin-mappe
eller cron-hook tilbage. Tabellerne er rester efter et afinstalleret plugin.

**Det var ikke MCP.** Royal MCP's egen log (`royal_mcp_logs`, 3 416 rækker) holder op **2/11 kl. 21:15**.
`mcpwp_activity_log` har to rækker fra 22/10. AI Engines `mwai_tasklogs` har intet i vinduet. Ingen
agent kan altså ikke have gjort det gennem de kanaler, der logger — men en MCP-forbindelse, der ikke
logger, kan ikke udelukkes med sikkerhed.

## 4. Den fatale fejl var vores egen — og den er rettet nu

```
2026-11-06T19:55:00+00:00 CRITICAL Uncaught Error: Undefined constant "home"
in …/plugins/steamgears-autopilot/includes/site-seo.php:354
… SGAP_Site_SEO::site_checks() ← scan() ← maybe_scan() ← do_action('admin_init') ← wp-admin/index.php
```

`site_checks()` byggede fire URL'er med `home . 'llms.txt'` i stedet for `$home . 'llms.txt'`. I PHP 8
er en ukendt konstant en `Error`, ikke en advarsel, så hele requesten dør. Det sker i `admin_init` —
altså **på enhver wp-admin-side**, når den ugentlige scanning er due — og `maybe_scan()` sætter sin
guard-transient *før* den scanner, så det rammer **præcis én admin-request i døgnet**. Det passer med
det ene CRITICAL i loggen kl. 19:55:00 og med at wp-admin derefter kunne bruges igen.

Fejlen fandtes både i live-versionen **7.40.1** og i repoets **7.41.0**. Den er nu rettet i repoet:

| Ændring | Bevis |
| --- | --- |
| `$home` på de fire linjer i `site_checks()` | `php -l` ren; `diff` viser fire linjer |
| Ny smoke-check `site seo: the domain checks run without a fatal error` | Med fejlen genindført i en kopi dør testen med **samme** `Undefined constant "home"` på linje 354 (168 PASS derefter `Fatal error`, exit 255); med rettelsen er den grøn |
| Zip genopbygget til 7.41.0 | `diff -r` mellem zip og kilde er tom |

**Det er den mest sandsynlige udløser af hele hændelsen:** en administrator åbnede wp-admin kl. 19:55,
fik en kritisk fejl, opdaterede WooCommerce-stakken kl. 19:56–19:57 for at reparere den, fik stadig en
fejl, bulk-deaktiverede de fire butiks-plugins kl. 19:57:14 og cache-pluginnet to minutter efter — og
gik derfra. At det var *vores* fejl, og ikke WooCommerce, er den del der gør hændelsen dyr: butikken
blev slået fra for at reparere noget, der ikke var i butikken.

Live-sitet kører stadig 7.40.1 med fejlen, indtil ejeren klikker *Plugins → Update*. Indtil da kan
wp-admin kaste den kritiske fejl én gang i døgnet.

## 5. Logningen der fanger næste gang

Sitet gemmer kun *tidspunktet* for en deaktivering (`recently_activated`), og `WP_DEBUG_LOG` er slået
fra — derfor stod vi uden spor. Der er nu sat en **log-only vagt** op som en af Novamiras
sandbox-filer, `wp-content/novamira-sandbox/steamgears-site-events.php`, der indlæses på hvert request
som en mu-plugin. Den ændrer intet andet end at skrive en linje.

| Fanges | Felt |
| --- | --- |
| `deactivated_plugin` / `activated_plugin` | plugin-fil, netværksflag og **kaldstak** (`fil:linje funktion`) |
| `upgrader_process_complete` | type, handling, hvilke plugins |
| `pre_update_option_active_plugins` | sikkerhedsnet: `fjernet` og `tilføjet`, også hvis en kodevej går uden om hookene |
| `wp_login` (kun administratorer) | hvem der kom ind i wp-admin, med IP og user-agent |
| `shutdown` | fatale fejl med fil og linje |
| PHP's egen `error_log` | peget på den private mappe, så advarsler også lander der |

Alt havner i **`/var/www/steamgears.eu/steamgears-logs/`** — uden for webroden, med `0700` på mappen,
`0600` på filerne og en `.htaccess` som ekstra værn. Der logges ingen cookies, tokens eller
adgangskoder. Loggen roterer ved 5 MB.

**Verificeret, ikke antaget:** mappen blev oprettet med `0700`, `site-events.log` med `0600`, og en
syntetisk `do_action('deactivated_plugin', …)` skrev en komplet linje med bruger `Humle91`, IP,
user-agent, REST-flag og kaldstak. Testlinjen blev derefter fjernet, så loggen starter tom. Efter
opsætningen svarer `/`, `/shop/`, en produkt-URL og `/wp-login.php` alle **200**, og der er **ingen**
PHP-fejl i loggen.

Læs den med WP-CLI eller over MCP:

```bash
python3 .tools/site.py novamira/run-wp-cli '{"args":["eval","echo file_get_contents(\"/var/www/steamgears.eu/steamgears-logs/site-events.log\");"]}'
```

Filen kan slettes igen; så stopper logningen, og intet andet ændres. Den ligger også i repoet som
[`hjalpefiler/steamgears-site-events.php`](hjalpefiler/steamgears-site-events.php), så den kan genskabes.

## 6. Hvad logningen ikke kan give

- **Hvem der gjorde det 6/11.** Handlingen er allerede sket. Loggen fanger kun fremtiden.
- **Serverens adgangslog.** Den ligger hos Simply uden for kontoen; ejeren kan bede support om
  adgangslog for 6/11 kl. 19:45–20:05, og det er den eneste kilde der kan svare på IP'en bag
  deaktiveringen.
- **En garanti mod gentagelse.** Vagtens `pre_update_option_active_plugins`-hook *kunne* afvise
  ændringen, men den er bevidst kun lyttende: en log skal ikke kunne vælte et request eller blokere en
  ejer, der virkelig vil slå noget fra.

## 7. Næste skridt for ejeren

1. **Opdatér Steamgears Autopilot til 7.41.0** (*Plugins → Update*, eller zip'en i `plugins/`) — den
   retter den daglige fatale fejl i wp-admin.
2. **Bed Simply om adgangslog for 6/11 kl. 19:45–20:05**, hvis hvem-spørgsmålet skal besvares.
3. **Overvej at slå WooCommerce-stakken fra som gruppe:** deaktiveres WooCommerce, skal Stripe,
   Pinterest og Google for WooCommerce med, ellers står der tre plugins tilbage der kalder funktioner,
   der ikke findes. Det var præcis hvad der skete her — og det er derfor WP's egen fejlbeskyttelse
   (`wc_setcookie()` undefined, set 23/10) dukkede op i MainWP-loggen dengang.
