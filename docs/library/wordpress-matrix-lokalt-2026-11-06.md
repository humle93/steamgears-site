# Integrationstesten mod CI's WordPress-matrix — lokalt

6/11 2026, opdateret efter P1.4. CI kører `plugins/tests/integration.sh` mod tre WordPress-versioner
(`plugin-ci.yml`, matrix `wp: ['6.4', '7.1', 'latest']`). Denne fil dokumenterer at alle tre er kørt lokalt i
en cloud-worktree, med hvilket miljø, hvad der blev sprunget over, og hvorfor. Opskriften til selve miljøet
(MySQL uden Docker, bulk-PHP med `mysqli`, wp-cli-shims) står i
[`integrationstest-lokalt-2026-11-06.md`](integrationstest-lokalt-2026-11-06.md) og gentages ikke her.

## 1. Resultat

| WordPress | Kørt som | Exit | PASS | FAIL | SKIP |
| --- | --- | --- | --- | --- | --- |
| 6.4 | `WP_VERSION=6.4` | 0 | 179 | 0 | 2 grupper (65 checks ikke kørt) |
| 7.1 | `WP_VERSION=7.1` (core rapporterer `7.1`) | 0 | 244 | 0 | 0 |
| latest | `WP_VERSION=latest` (core rapporterer `7.1.4`) | 0 | 244 | 0 | 0 |

Alle tre kørsler slutter med `All HTTP and uninstall checks passed.` og exit 0. Ingen `FAIL`-linje i nogen af
dem, og **ingen rettelse var nødvendig i plugin-koden eller i scriptet for at få 6.4 grøn**. Alle tre blev
kørt på ny efter P1.4's 20 evergreen-checks; `latest` er WordPress 7.1.4.

Miljø for alle tre: **PHP 8.2.32** (static-php-cli bulk) og **MySQL 8.0.36** — samme PHP- og MySQL-hovedversioner
som CI. Kørslerne er derfor sammenlignelige med CI på versionsniveau; kun runneren og dens opsætning mangler.

## 2. Hvorfor 6.4 har færre checks

Forskellen er **65 checks** (244 − 179). Det er ikke fejl, men checks der ikke kan køres uden Abilities API,
som først findes fra WordPress 6.9:

- `SKIP abilities: this WordPress has no Abilities API` — 9 checks i `integration.php`'s abilities-gruppe.
- `SKIP admin tools: this WordPress has no Abilities API` — 51 checks i `admin-tools.php`.
- 4 ability-gated checks i `integration.php` uden deres egen `SKIP`-linje: `check-keys` (1), `update` (1) og
  de to ability-checks i evergreen-blokken (2).
- 1 ability-gated check i `automation.php`: `rescue-images` og `self-check` som værktøjer.

9 + 51 + 4 + 1 = 65. Sprunget over betyder **ikke kørt**, ikke bestået. Kun de to grupper printer en
`SKIP`-linje; de øvrige fem checks forsvinder lydløst i deres `function_exists()`-guard. Derfor skal loggen
læses på både `SKIP` og de manglende PASS-linjer, ikke kun på exit-koden.

Konsekvens for de fire modelpunkter: de ligger i `integration.php` og `smoke.php`, ikke i de to grupper, og de
blev kørt og bestod på 6.4. Det gælder også model-katalog- og fallback-checks mod Anthropic og AIMLAPI-mocken.
Evergreen-blokkens 18 ikke-ability-checks kørte også på 6.4, inklusive cooldown, kanalrotation og budget.

## 3. Hvad latest er

`latest` leverede WordPress **7.1.4** (`wp-includes/version.php`). 7.1 leverede `7.1`. De to kørsler er derfor
to forskellige versionsstrenge, men ikke to forskellige kernever. Hvis WordPress udgiver en ny version, skal
`latest` køres igen; kørslen her dækker kun den version der var nyest 6/11 2026.

## 4. Sådan køres matrixen lokalt

Miljøet fra `integrationstest-lokalt-2026-11-06.md` skal være oppe (MySQL på `127.0.0.1:3306`, shims i
`/tmp/bin`). Kørslerne her brugte PHP 8.2.32 fra `/tmp/php82` og en wp-cli-shim i `/tmp/php82-bin`, fordi
`/tmp/bin/wp` peger på 8.5-binaryen; `php` og `wp` skal bruge samme version. Derefter kører hver version i sin
egen mappe, så installationerne ikke overskriver hinanden:

```bash
for v in 6.4 7.1 latest; do
  d="/tmp/wp-${v//./}"
  PATH=/tmp/php82:/tmp/php82-bin:/tmp/bin:$PATH WP_VERSION="$v" WP_DIR="$d" \
    bash plugins/tests/integration.sh > "/tmp/int_$v.log" 2>&1
  code=$?
  printf '%s EXIT=%s PASS=%s FAIL=%s SKIP=%s\n' "$v" "$code" \
    "$(grep -c '^PASS ' "/tmp/int_$v.log")" \
    "$(grep -c '^FAIL ' "/tmp/int_$v.log")" \
    "$(grep -c '^SKIP ' "/tmp/int_$v.log")"
done
```

Scriptet dropper databasen og rydder `wp-content/plugins` før hver kørsel, så rækkefølgen ikke betyder noget.
Hvert log skal læses for `FAIL` og `SKIP` efter kørslen; exit-koden alene er ikke nok til at se SKIP.

## 5. Det der ikke er dækket

- Den GitHub-hostede `ubuntu-latest`-runner er ikke genskabt; kun PHP/MySQL/WordPress-versionerne matcher.
- `latest` er et øjebliksbillede (7.1.4). En senere WordPress-version skal køres på ny.
- 65 checks er ikke kørt på 6.4, fordi Abilities API mangler (se §2).

6.4 er desuden kørt på PHP 8.5.8: samme **179 PASS / 0 FAIL**, men med deprecations fra kernen og wp-cli
(`wp_getimagesize()`, `Colors.php`). Det er støj fra en ældre kerne på en nyere PHP, ikke plugin-fejl:
`PASS no plugin notices in debug.log` bestod. CI's kombination er den på PHP 8.2, og den er ren.

## 6. Kontrol

```
6.4     EXIT=0  PASS=179  FAIL=0  SKIP=2 grupper (65 checks ikke kørt)
7.1     EXIT=0  PASS=244  FAIL=0  SKIP=0
latest  EXIT=0  PASS=244  FAIL=0  SKIP=0   (WordPress 7.1.4)
```
