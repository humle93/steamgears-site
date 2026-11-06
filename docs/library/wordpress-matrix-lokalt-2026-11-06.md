# Integrationstesten mod CI's WordPress-matrix — lokalt

6/11 2026. CI kører `plugins/tests/integration.sh` mod tre WordPress-versioner (`plugin-ci.yml`, matrix
`wp: ['6.4', '7.1', 'latest']`). Denne fil dokumenterer at alle tre er kørt lokalt i en cloud-worktree, med
hvilket miljø, hvad der blev sprunget over, og hvorfor. Opskriften til selve miljøet (MariaDB uden Docker,
bulk-PHP med `mysqli`, wp-cli-shims) står i
[`integrationstest-lokalt-2026-11-06.md`](integrationstest-lokalt-2026-11-06.md) og gentages ikke her.

## 1. Resultat

| WordPress | Kørt som | Exit | PASS | FAIL | SKIP |
| --- | --- | --- | --- | --- | --- |
| 6.4 | `WP_VERSION=6.4` | 0 | 159 | 0 | 2 grupper |
| 7.1 | `WP_VERSION=7.1` (core rapporterer `7.1`) | 0 | 222 | 0 | 0 |
| latest | `WP_VERSION=latest` (core rapporterer `7.1.4`) | 0 | 222 | 0 | 0 |

Alle tre kørsler slutter med `All HTTP and uninstall checks passed.` og exit 0. Ingen `FAIL`-linje i nogen af
dem, og ingen rettelse var nødvendig i plugin-koden eller i scriptet for at få dem grønne.

Miljø for alle tre: PHP 8.3.14 (static-php-cli bulk), MariaDB 11.4.5. **CI bruger PHP 8.2 og MySQL 8.0**, så
kørslerne er ikke identiske med CI. Det er en begrænsning, ikke en dækning af den del af matrixen.

## 2. Hvorfor 6.4 har færre checks

Forskellen er 63 checks (222 − 159). Den kommer fra to grupper der springes over, ikke fra fejl:

- `SKIP abilities: this WordPress has no Abilities API`
- `SKIP admin tools: this WordPress has no Abilities API`

Abilities API findes først fra WordPress 6.9, og testen gater de to grupper på det. De 63 checks er alle i de
grupper der fanger ability-registrering, `enable-full-access`, admin-tools og de værktøjer der bygger på dem
(fx `edit-draft`, `manage-queue`, `update-settings`). Sprunget over betyder: **ikke kørt på 6.4**. Det er ikke
det samme som bestået.

Konsekvens for de fire modelpunkter: de ligger i `integration.php` og `smoke.php`, ikke i de to grupper, og de
blev kørt og bestod på 6.4. Det gælder også model-katalog- og fallback-checks mod Anthropic og AIMLAPI-mocken.

## 3. Hvad latest er

`latest` leverede WordPress **7.1.4** (`wp-includes/version.php`). 7.1 leverede `7.1`. De to kørsler er derfor
to forskellige versionsstrenge, men ikke to forskellige kernever. Hvis WordPress udgiver en ny version, skal
`latest` køres igen; kørslen her dækker kun den version der var nyest 6/11 2026.

## 4. Sådan køres matrixen lokalt

Miljøet fra `integrationstest-lokalt-2026-11-06.md` skal være oppe (MariaDB på `127.0.0.1:3306`, shims i
`/tmp/bin`). Derefter kører hver version i sin egen mappe, så installationerne ikke overskriver hinanden:

```bash
for v in 6.4 7.1 latest; do
  d="/tmp/wp-${v//./}"
  WP_VERSION="$v" WP_DIR="$d" PATH=/tmp/bin:$PATH bash plugins/tests/integration.sh > "/tmp/int_$v.log" 2>&1
  echo "$v EXIT=$? PASS=$(grep -c '^PASS' "/tmp/int_$v.log") FAIL=$(grep -c '^FAIL' "/tmp/int_$v.log")"
done
```

Scriptet dropper databasen og rydder `wp-content/plugins` før hver kørsel, så rækkefølgen ikke betyder noget.
Hvert log skal læses for `FAIL` og `SKIP` efter kørslen; exit-koden alene er ikke nok til at se SKIP.

## 5. Det der ikke er dækket

- PHP 8.2 og MySQL 8.0 (CI's faktiske versioner) er ikke kørt her.
- Den GitHub-hostede `ubuntu-latest`-runner er ikke genskabt.
- `latest` er et øjebliksbillede (7.1.4). En senere WordPress-version skal køres på ny.

## 6. Kontrol

```
6.4     EXIT=0  PASS=159  FAIL=0  SKIP=2 grupper (Abilities API findes ikke før 6.9)
7.1     EXIT=0  PASS=222  FAIL=0  SKIP=0
latest  EXIT=0  PASS=222  FAIL=0  SKIP=0   (WordPress 7.1.4)
```
