# Robots.txt: filter i stedet for fil-adgang — 8/11

S0.2 havde stået åben siden 7/11, fordi rettelsen krævede at redigere hostens mu-plugin
`managed-wordpress-robots.php` — og det forsøg gav et nedbrud, hvor filindholdet blev interpoleret væk i en
dobbelt-citeret PHP-streng. En parse-fejl i et mu-plugin er en `E_COMPILE_ERROR` på hvert request.

**Målt før:** `/robots.txt` svarede 200 med **3** `User-agent: *`-grupper — Rank Maths genererede gruppe,
hostens otte `?filter_cat`/`?min_price`/`?orderby`-`Disallow` i sin egen gruppe, og endnu en.

**Løsningen var at lade filen være.** Et eget mu-plugin, `sgap-robots-consolidation.php`, hægter sig på
`robots_txt`-filteret (priority 20), læser output, samler alle `User-agent: *`-regler i én gruppe, lægger
`Sitemap` sidst og sender resten uændret videre. 1 282 bytes, `php -l` ren, og intet skrives til hostens filer.

**Målt efter:** **1** gruppe, **14** `Disallow` + **1** `Allow` + **1** `Sitemap` bevaret, `/robots.txt` 200.

**Fælden der er værd at huske:** filer i `wp-content/mu-plugins/` kan skrives fra WordPress-konteksten, men en
enkel strengfejl tager hele sitet ned, fordi mu-plugins indlæses ubetinget. Et filter i en lille, lintet fil er
den billigere vej til samme resultat — og det kan rulles tilbage ved at slette én fil.
