# Integrationstesten med CI's PHP 8.2 og MySQL 8.0

6/11 2026. [`wordpress-matrix-lokalt-2026-11-06.md`](wordpress-matrix-lokalt-2026-11-06.md) kørte CI's tre
WordPress-versioner lokalt, men på PHP 8.3.14 og MariaDB 11.4.5 — ikke de versioner CI faktisk bruger
(`plugin-ci.yml`: PHP 8.2 med `mysqli`, `mysql:8.0`). Denne fil lukker den forskel: samme test, samme script,
kørt på PHP 8.2 og MySQL 8.0. Opskriften på miljøet står her, fordi den kostede to runder at få rigtig.

## 1. Resultat

| WordPress | Core rapporterer | Exit | PASS | FAIL | SKIP |
| --- | --- | --- | --- | --- | --- |
| 6.4 | `6.4` | 0 | 184 | 0 | 2 grupper |
| 7.1 | `7.1` | 0 | 249 | 0 | 0 |
| latest | `7.1.4` | 0 | 249 | 0 | 0 |

Miljø: PHP **8.2.32** (static-php-cli bulk, med `mysqli`, `pdo_mysql` og `mysqlnd`), MySQL **8.0.43**
(minimal tarball), wp-cli 2.12.0. Alle tre kørsler slutter med `All HTTP and uninstall checks passed.`

Tallene er identiske med dem i [`wordpress-matrix-lokalt-2026-11-06.md`](wordpress-matrix-lokalt-2026-11-06.md)
(184, 249 og 249, målt på MySQL 8.0.36): hverken PHP- eller MySQL-patchversionen flyttede en enkelt check, og
der var intet at rette i plugin-koden. Det er den forventede konklusion, men den er nu målt og ikke antaget.
`smoke.php` giver **530 PASS / 0 FAIL** på PHP 8.2, og `php -l` er ren på alle plugin-filer.

De 2 SKIP-grupper på 6.4 er de samme som før: Abilities API findes først fra WordPress 6.9, så
`abilities`- og `admin tools`-grupperne (65 checks, 249 − 184) springes over. Sprunget over er ikke bestået.

## 2. Miljøet, som det blev bygget

Docker var ikke tilgængeligt (socklen er `root:docker`, og sudo er spærret), så begge dele blev hentet som
binære og pakket ud i `/tmp`:

```bash
# PHP 8.2 med mysqli — bulk-byggene har de udvidelser WordPress kræver
curl -sSL -o /tmp/php82.tgz \
  https://dl.static-php.dev/static-php-cli/bulk/php-8.2.32-cli-linux-x86_64.tar.gz
mkdir -p /tmp/php82 && tar xzf /tmp/php82.tgz -C /tmp/php82

# MySQL 8.0 minimal (samme hovedversion som CI's mysql:8.0-image)
curl -sSL -o /tmp/mysql80.tar.xz \
  https://cdn.mysql.com//archives/mysql-8.0/mysql-8.0.43-linux-glibc2.17-x86_64-minimal.tar.xz
mkdir -p /tmp/mysql80 && tar xJf /tmp/mysql80.tar.xz -C /tmp/mysql80 --strip-components=1

# libaio og libnuma mangler på Ubuntu 24.04 — hentet som .deb og udpakket uden root
dpkg-deb -x libaio1_0.3.113-4_amd64.deb /tmp/libs/root
dpkg-deb -x libnuma1_2.0.16-1_amd64.deb /tmp/libs/root
```

Serveren blev initialiseret og startet med en egen `my.cnf` i `/tmp`, `--initialize-insecure` og derefter
`ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root'`. `mysql_native_password`
er ikke et tilfælde: det er hvad `mysql:8.0`-imaget sætter som standard, og det er samtidig det der virker
med `mysqli` uden SSL-forhandling. Databasen `wordpress` blev oprettet, og `sql_mode` er MySQL 8.0's
standard (`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,…`), altså det samme som i CI.

Shims i `/tmp/bin` (samme princip som i den tidligere opskrift) med to tilføjelser der viste sig nødvendige:

```bash
# php og wp: CI's setup-php kører med memory_limit=-1
printf '#!/bin/sh\nexec /tmp/php82/php -d memory_limit=-1 "$@"\n' > /tmp/bin/php
printf '#!/bin/sh\nexec /tmp/php82/php -d memory_limit=-1 /tmp/wp-cli.phar --allow-root "$@"\n' > /tmp/bin/wp

# mysql-klienten: wp-cli kalder den for at læse sql_mode og droppe databasen
export LD_LIBRARY_PATH=/tmp/libs/root/usr/lib/x86_64-linux-gnu
ln -sf /lib/x86_64-linux-gnu/libncurses.so.6 /tmp/libs/root/usr/lib/x86_64-linux-gnu/libncurses.so.5
ln -sf /lib/x86_64-linux-gnu/libtinfo.so.6  /tmp/libs/root/usr/lib/x86_64-linux-gnu/libtinfo.so.5

chmod +x /tmp/bin/*
```

Kørslen er den samme som i CI:

```bash
PATH=/tmp/bin:$PATH LD_LIBRARY_PATH=/tmp/libs/root/usr/lib/x86_64-linux-gnu \
WP_VERSION=7.1 WP_DIR=/tmp/wp71 \
DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root DB_NAME=wordpress \
bash plugins/tests/integration.sh
```

## 3. De to fælder der kostede runder

**Memory limit.** Første forsøg på 7.1 døde med
`Allowed memory size of 134217728 bytes exhausted … WP_CLI/Extractor.php`. WordPress 6.4's kerne er lille
nok til PHP's standard på 128 MB, men 7.x-arkivet er ikke, og fejlen rammer under udpakningen — altså før
en eneste check er kørt. GitHub Actions' `shivammathur/setup-php` sætter `memory_limit=-1`, så problemet
findes ikke i CI. Løsningen er at sætte samme værdi i shimsen, ikke at ændre scriptet. Efter rettelsen gik
6.4, 7.1 og latest igennem med identiske indstillinger.

**Manglende `mysql`-klient.** Uden klienten på `PATH` skrev wp-cli
`Error: Failed to get current SQL modes. Reason: /usr/bin/env: 'mysql': No such file or directory`, og
`wpc db drop --yes 2>/dev/null || true` i `integration.sh` fejlede **tavst**. Databasen blev derfor ikke
ryddet mellem kørslerne, og næste kørsel arvede rækker fra den forrige. Det gav et misvisende billede:

- `FAIL queue: created 201 -> {"code":"sgap_duplicate",…,"id":1}` for fire checks i træk
- `FAIL … already queued`, forkerte tællere i `learning: counters accumulate`
- til sidst `Fatal error: Cannot use object of type WP_Error as array … on line 478`

Linje 478 er `in_array((int) $rss_fb['id'], …)`, hvor `$rss_fb` er resultatet af `SGAP_Queue::add()`. Den
returnerer `WP_Error` når posten allerede står i køen — altså var det en følge af den snavsede database,
ikke en PHP 8.2- eller MySQL 8.0-inkompatibilitet. Efter at klienten kom på `PATH`, så `db drop` virkede, og
kørslen startede på en tom database, forsvandt både FAIL-linjerne og den fatale fejl uden en enkelt
kodelinje ændret.

Læren er generel og gælder også CI: **en `|| true` på `db drop` skjuler at oprydningen ikke skete.** Fejler
den, ser testresultatet ud som plugin-fejl. Ved en rød kørsel skal de første linjer i loggen derfor læses
før fejlene læses — `Failed to get current SQL modes` er et miljøproblem, ikke et plugin-problem.

## 4. Hvad der stadig ikke er dækket

- **Runneren.** Testen er kørt på Ubuntu 24.04 i en cloud-worktree, ikke på GitHub's `ubuntu-latest`.
- **PHP 8.0, 8.1, 8.3, 8.4 og 8.5.** CI's `smoke`-job kører `php -l` og `smoke.php` på seks PHP-versioner.
  Her er kun 8.2 kørt (den version `wordpress`-jobbet bruger). De fem øvrige er kørt efterfølgende i
  [`smoke-php-matrix-lokalt-2026-11-07.md`](smoke-php-matrix-lokalt-2026-11-07.md), hvor alle seks giver
  530 PASS / 0 FAIL.
- **`latest` er et øjebliksbillede.** Kørslen dækker 7.1.4, den nyeste kerne 6/11 2026.
- **MariaDB er ikke kørt i denne omgang.** Den tidligere kørsel dækkede MariaDB 11.4.5, så begge
  databasefamilier er nu prøvet — men aldrig i samme kørsel.

## 5. Kontrol

```
PHP 8.2.32 · MySQL 8.0.43 · wp-cli 2.12.0
6.4     EXIT=0  PASS=184  FAIL=0  SKIP=2 grupper (Abilities API findes ikke før 6.9)
7.1     EXIT=0  PASS=249  FAIL=0  SKIP=0
latest  EXIT=0  PASS=249  FAIL=0  SKIP=0   (WordPress 7.1.4)
smoke.php 530 PASS / 0 FAIL · php -l ren
```
