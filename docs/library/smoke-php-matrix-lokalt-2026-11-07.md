# Smoke-testen på hele PHP-matrixen — lokalt

7/11 2026. CI's [`plugin-ci.yml`](../../.github/workflows/plugin-ci.yml) har to jobs der ligner hinanden, men
ikke dækker det samme. `wordpress`-jobbet kører integrationstesten på **PHP 8.2** og MySQL 8.0 — den er
dokumenteret i [`integrationstest-ci-parity-2026-11-06.md`](integrationstest-ci-parity-2026-11-06.md).
`smoke`-jobbet kører derimod `php -l` og `smoke.php` på **seks** PHP-versioner: 8.0, 8.1, 8.2, 8.3, 8.4 og 8.5.
Denne fil lukker den anden halvdel: alle seks er nu kørt lokalt med CI's egne kommandolinjer.

## 1. Resultat

| PHP | Byg | `smoke.php` exit | PASS | FAIL | SKIP | `php -l` exit | Deprecations |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 8.0.30 | static-php-cli bulk | 0 | 530 | 0 | 0 | 0 | 0 |
| 8.1.34 | static-php-cli bulk | 0 | 530 | 0 | 0 | 0 | 0 |
| 8.2.32 | static-php-cli bulk | 0 | 530 | 0 | 0 | 0 | 0 |
| 8.3.32 | static-php-cli bulk | 0 | 530 | 0 | 0 | 0 | 0 |
| 8.4.23 | static-php-cli bulk | 0 | 530 | 0 | 0 | 0 | 0 |
| 8.5.8 | static-php-cli bulk | 0 | 530 | 0 | 0 | 0 | 0 |

Alle seks versioner giver **530 PASS, 0 FAIL og exit 0**, og `php -l` er ren på alle 39 PHP-filer i
`plugins/` (samme `find`-kommando som CI bruger, uden undtagelser). Ingen version skrev en deprecation, og
der var intet at rette i plugin-koden — matrixen er dækket, ikke bare prøvet.

Hver kørsel er identificeret ved at spørge binæren selv i samme kald (`php -v` og
`php -r 'echo PHP_VERSION;'`), ikke ved at stole på en shim på `PATH`. Det er værd at gøre eksplicit her,
fordi hele pointen er at bevise *hvilken* version der bestod: en shim der peger tilbage på 8.2 ville give
seks grønne rækker uden at have testet noget nyt.

## 2. Den ene SKIP-linje — og hvorfor den ikke optræder

`smoke.php` har præcis én `SKIP`-udskrift:

```
SKIP live: AIMLAPI /models kunne ikke læses
```

Den ligger i en blok der kun kører når miljøvariablen `SGAP_LIVE_CATALOG` er sat. Uden den hentes intet fra
nettet, og linjen skrives aldrig — derfor 0 SKIP i tabellen og 0 SKIP i CI. Sætter man den alligevel (for at
sammenligne pluginnets model-dropdown med AIMLAPI's rigtige katalog) og kataloget ikke kan læses, printes
`SKIP`-linjen, og kørslen **exiter stadig 0**: det er en oplysning om at den levende kontrol ikke blev kørt,
ikke en bestået check. Skal den tælles med, skal den læses som "ikke kørt".

Til forskel fra `integration.php` er der ingen WordPress-version der kan springe noget over i `smoke.php`:
alle 530 checks kræver kun PHP og de stubbede WordPress-funktioner. SKIP i `integration.php` (Abilities
API, WordPress 6.4) hører altså til den anden test og smitter ikke over her.

## 3. Opskriften — seks versioner ved siden af hinanden

Static-php-cli's bulk-byg findes pr. minor-version og indeholder de udvidelser WordPress kræver (`mysqli`,
`pdo_mysql`, `mysqlnd`, `curl`, `json`). Til smoke-jobbet er der brug for **ingen** database, ingen wp-cli og
ingen MySQL — kun PHP-binæren:

```bash
cd /tmp
for spec in 80:8.0.30 81:8.1.34 82:8.2.32 83:8.3.32 84:8.4.23 85:8.5.8; do
  d=${spec%%:*}; v=${spec##*:}
  mkdir -p "php$d"
  curl -sSL --retry 3 -o "php$d.tgz" \
    "https://dl.static-php.dev/static-php-cli/bulk/php-$v-cli-linux-x86_64.tar.gz"
  tar xzf "php$d.tgz" -C "php$d"
  chmod +x "php$d/php"
done
```

Derefter køres CI's to trin pr. version, med binærens sti direkte — ikke via `/tmp/bin/php`, der peger på 8.2:

```bash
cd /path/til/steamgears-site
for d in 80 81 82 83 84 85; do
  P="/tmp/php$d/php"
  echo "== $($P -r 'echo PHP_VERSION;')"
  find plugins -name '*.php' -print0 | xargs -0 -n1 "$P" -l >/dev/null; echo "  lint exit=$?"
  "$P" -d memory_limit=-1 plugins/tests/smoke.php > "/tmp/smoke_$d.log" 2>&1; rc=$?
  echo "  smoke exit=$rc PASS=$(grep -c '^PASS ' "/tmp/smoke_$d.log") FAIL=$(grep -c '^FAIL ' "/tmp/smoke_$d.log")"
done
```

To detaljer matcher CI og er ikke kosmetik:

- **`-d memory_limit=-1`.** `shivammathur/setup-php` sætter `memory_limit=-1` for CLI. Uden den kan en
  enkelt check med store stubbe ramme PHP's standard på 128 MB og fejle på en måde der ligner en
  kodefejl. Det var netop fælden i den anden test (se CI-parity-dokumentet §3).
- **`find plugins -name '*.php'`.** CI's lint-trin undtager ikke `vendor/` eller `tests/fixtures/`; det gør
  kørslen her heller ikke, så de samme 39 filer bliver syntakstjekket på alle seks versioner.

De statiske byg er selvstændige binære: 8.0.30-bygget er fra november 2024 og kører uændret på Ubuntu 24.04
uden at trække distributionens PHP-indstallationer ind. Det er både styrken og begrænsningen — se §4.

## 4. Hvad der stadig ikke er dækket

- **Runneren.** Alt er kørt på Ubuntu 24.04 i en cloud-worktree, ikke på GitHub's `ubuntu-latest`.
- **`setup-php`'s ini-fil.** CI får PHP gennem `shivammathur/setup-php` med distributionens ini, opcache og
  den præcise udvidelsesliste. Her er brugt statiske byg med `-d memory_limit=-1`. Selve PHP-versionen er
  den samme, men ini-filen er ikke identisk.
- **Den levende model-katalog.** `SGAP_LIVE_CATALOG` blev ikke kørt, så den ene check der sammenligner
  pluginnets dropdown med AIMLAPI's rigtige `/v1/models` er ikke verificeret lokalt. Den kræver netadgang og
  er ikke en del af CI's smoke-job.
- **Patch-niveauet.** CI installerer den nyeste patch i hver minor på kørselsdagen; tabellen fryser de
  versioner der var nyeste 7/11 2026. En ny patch kan kræve en ny kørsel.
- **8.0 og 8.1 er EOL.** De køres fordi matrixen kræver det; 8.0.30 er den sidste 8.0-udgivelse og får
  ingen sikkerhedsrettelser.

Alle seks rækker i §1 er målt på pluginnet som det stod i `main` ved commit `2133a8d`, med versionen
bekræftet af binæren i samme kald — ingen af dem er arvet fra en tidligere kørsel.
