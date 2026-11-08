# Rød CI på WordPress 7.1 — rækkefølgen der skiftede med databasen

7/11 2026, plugin 7.46.1. Skrevet fordi `Plugin CI` stod rød på `main` i to job (WordPress 7.1 og
nyeste) mens de øvrige syv var grønne, og fordi fejlen ikke var det den lignede.

**Kort:** `SGAP_Evergreen::candidates()` sorterede kun på tidspunktet for seneste share. To sider der
aldrig er delt har samme tid, og så afgjorde databasen rækkefølgen. Rettelsen er et sekundært
sorteringsled (`id`), så rotationen bliver den samme overalt.

## 1. Symptomet

```
== Install, activate, exercise and uninstall the plugin
FAIL evergreen: the prompt asks for a new angle and forbids invented offers
Error: Integration checks failed.
```

Kun på WordPress 7.1 og `latest`. WordPress 6.4 var grøn — og det er den version der **ikke** har
Abilities API, hvilket først pegede mistænkeligt mod ability-laget.

## 2. Hvordan det blev målt i stedet for gættet

CI's joblog blev leveret trunkeret fra toppen, så den fejlende linje var ikke til at læse ud af
API'et. Opskriften fra [`integrationstest-lokalt-2026-11-06.md`](integrationstest-lokalt-2026-11-06.md)
blev derfor kørt igen i sandkassen — MariaDB-bintar, static-php-cli **bulk** (den eneste med `mysqli`),
wp-cli-phar og fire shims i `/tmp/bin` — og fejlen kom igen med samme navn. Det tog én kørsel at
bevise at den var ægte og ikke et CI-artefakt.

```
PATH=/tmp/bin:$PATH WP_DIR=/tmp/wp71 WP_VERSION=7.1 \
  DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root DB_NAME=wordpress71 \
  bash plugins/tests/integration.sh
# 147 PASS, 1 FAIL
```

## 3. Årsagen

```php
usort($ids, fn($a, $b) => self::last_share($a) <=> self::last_share($b));
```

Testen markerer to sider og forventer at den med det laveste id deles først. Begge har
`last_share = 0`, fordi ingen af dem er delt endnu — altså er sammenligningen `0 <=> 0` for hvert par,
og `usort` bevarer den rækkefølge `get_posts()` gav. Den rækkefølge kommer fra `ORDER BY post_date DESC`
uden et sekundært led, så to sider oprettet i samme sekund kommer i **databasens** rækkefølge. MySQL 8
og MariaDB 11 svarer ikke ens, og WordPress-versionerne ovenpå flytter rundt på hvad der ellers ligger i
forespørgslen.

Det gør fejlen til mere end en skrøbelig test: på sitet kunne »ældste share først« blive til »hvad
tabellen nu returnerede« — og dermed dele den samme side to gange i træk, mens en anden blev sultet.

## 4. Rettelsen

```php
// Oldest share first, and the id breaks the tie.
usort($ids, fn($a, $b) => [self::last_share($a), $a] <=> [self::last_share($b), $b]);
```

Sekundært led på `id` gør rækkefølgen total og dermed ens på alle databaser. Testen behøver ingen
ændring: den påstod noget om verden (laveste id først), som koden nu lever op til.

Desuden talte to CI-checks stadig **35** abilities, efter `woo-cost-import` gjorde dem til 36
(`plugins/tests/admin-tools.php`). Et værktøjstal står nu tre steder — `plugins/tests/smoke.php`,
`admin-tools.php` og integrationens egen optælling — og skal rettes alle tre steder når kataloget vokser.

## 5. Resultat

| Kørsel | Før | Efter |
| --- | --- | --- |
| WordPress 7.1 lokalt | 147 PASS, 1 FAIL | **249 PASS, 0 FAIL** |
| WordPress 7.1.4 (`latest`) lokalt | — (samme fejl i CI) | **249 PASS, 0 FAIL** |
| Smoke | 643 PASS | 643 PASS |

## 6. Fælder til næste gang

- **En sortering uden sekundært led er ikke en sortering.** Ens værdier giver vilkårlig rækkefølge, og
  den slags fejl dukker først op på en anden database eller en anden WordPress-version.
- **Grøn på 6.4 betyder ikke grøn i praksis.** 6.4 springer ability-grupperne over (184 af 249 checks),
  så en fejl i den del af koden kan ikke ses der. Den ældste matrix-kørsel er den mindst informativ.
- **En test der pinner indeks 0 i en liste kræver at koden lover rækkefølgen.** Ellers tester den
  databasen, ikke pluginnet.
- **Joblog fra GitHub API kommer trunkeret fra toppen.** Den fejlende linje står i bunden; en lokal
  kørsel er hurtigere end at grave i logfiler.
