# CI flyttet til ubuntu-26.04 — og den ene fælde var ikke imaget

**Målt 9/11 2026.** Alle 15 `runs-on`-linjer i repoets 12 workflow-filer peger nu på
`ubuntu-26.04` i stedet for `ubuntu-24.04`. Skiftet holdt seks af ti jobs grønne i første
forsøg; de tre WordPress-jobs døde, fordi `setup-php` ikke kunne installere **wp-cli** på det
nye image. Det er rettet, og alle kørsler på `e316a04` er grønne.

## 1. Hvorfor nu

`ubuntu-latest` migrerer fra 24.04 til 26.04 i vinduet **19/10–19/11 2026**, altså netop nu
([announcement #14748](https://github.com/actions/runner-images/issues/14748)). Image 26.04 har
været **generelt tilgængeligt siden 17/9 2026** for x64 og arm64
([#14747](https://github.com/actions/runner-images/issues/14747)), så `ubuntu-26.04` er en
 produktionslabel — ikke preview, ikke en ventekø.

Forrige pas låste kørslerne til `ubuntu-24.04` for ikke at blive flyttet midt i GitHubs
migrering. Dette pas tager skridtet med vilje og med en kørsel som bevis.

## 2. Hvad der blev ændret

| Fil | Linjer |
| --- | --- |
| `commit-vaern.yml`, `cron-tick.yml`, `natlig-push.yml`, `niche-scan.yml`, `novamira-keepalive.yml`, `promote-vaern.yml`, `rebrand-apply.yml`, `release.yml`, `seo_audit.yml` | 1 hver |
| `projektstatus.yml` | 2 |
| `backup-doelse.yml` | 1 (kom ind fra en parallel chat, stadig på 24.04) |
| `plugin-ci.yml` | 3 runners + 11 linjer wp-cli-fix |

Alt er parset som YAML efter ændringen: **12 filer, 15 jobs, 0 fejl**. Ingen workflow
installerer pakker med `apt`, så der var ingen pakkenavne at rette; `setup-python` henter
sine builds versionsuafhængigt, og `python-version` står eksplicit i alle otte trin.

## 3. Hvad imaget selv meldte

| Felt | Målt |
| --- | --- |
| OS | Ubuntu **26.04.1 LTS** |
| Image-version | `20261101.186.1` (`ubuntu-26.04`) |
| Git | 2.56.0 |
| Docker API | 1.54 (service-containere virker) |
| PHP forudinstalleret | 8.5 |

`shivammathur/setup-php` fik 26.04-support i **2.40.0**, og på GitHub-runnere virker det også
for de ældre PHP-versioner. Målt: alle seks matrix-ben (**8.0, 8.1, 8.2, 8.3, 8.4, 8.5**)
installerede og kørte `php -l` plus `plugins/tests/smoke.php` grønt. PHP 8.2 blev rapporteret som
**8.2.35**.

## 4. Fælden: wp-cli, ikke WordPress

Første kørsel på det nye image (`099f8d3`, Plugin CI run **42568069074**) gav tre røde jobs —
`WordPress 6.4`, `7.1` og `latest` — alle med samme mønster: jobbet fejlede **under et sekund**
efter testscriptet startede.

Loggen viste to linjer der peger på hinanden:

```
✓ PHP Installed PHP 8.2.35
✓ mysqli Enabled
✓ composer Added composer 2.10.3
✗ wp-cli Could not setup wp-cli
...
== Install WordPress latest
plugins/tests/integration.sh: line 17: wp: command not found
```

Altså: `setup-php` **melder ikke fejl** når et værktøj ikke kan installeres — den skriver
`✗` og fortsætter. Steppet var grønt, og først `integration.sh` faldt med exit 127. Det er
derfor fejlen ser ud som en kodefejl men er et miljøproblem: **et grønt setup-trin er ikke
bevis for at værktøjet findes.**

Rettelsen er 11 linjer i `plugin-ci.yml`: `tools: composer` i stedet for `tools: wp-cli`, og
et eget trin der henter phar'en og kræver svar fra den:

```yaml
- name: Install wp-cli
  run: |
    set -euo pipefail
    curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /tmp/wp-cli.phar
    sudo mv /tmp/wp-cli.phar /usr/local/bin/wp
    wp --info
```

`wp --info` er med vilje det sidste led: hentes phar'en men kan ikke køre, fejler trinnet selv
og ikke 40 linjer inde i testen.

## 5. Resultat efter rettelsen (`e316a04`)

| Workflow | Jobs | Dom |
| --- | --- | --- |
| Plugin CI — Lint + smoke (8.0, 8.1, 8.2, 8.3, 8.4, 8.5) | 6 | ✓ |
| Plugin CI — WordPress 6.4 / 7.1 / latest + MySQL 8.0 | 3 | ✓ |
| Plugin CI — Zip matcher kilden | 1 | ✓ |
| Commit-besked-værn | 1 | ✓ (0 annotationer) |
| Promote-værn | 1 | ✓ (0 annotationer) |
| Projektstatus, Novamira keep-alive, Backup-øvelse (pushet i `099f8d3`) | 3 | ✓ |

Tre kørsler for `e316a04`: **3 success, 0 failure, 0 in_progress**. Den månedlige
backup-øvelse, projektstatus og keep-alive blev kørt af pushet fordi deres egen fil var rørt,
så de er også målt på 26.04.

## 6. Hvad der ikke er målt endnu

`cron-tick`, `natlig-push`, `niche-scan`, `seo_audit`, `rebrand-apply` og `release` blev ikke
udløst af pushet — deres ændring er kun YAML-valideret. De kører på deres tidsplan (cron-tick
hvert 15. minut, natlig-push kl. 00–01 UTC, niche-scan 00:30, seo_audit mandag 06:00), og
`release` kræver et menneske bag `confirm: TAG`. Næste naturlige kontrol er den første
planlagte kørsel efter 9/11 kl. 05:38 UTC.

## 7. Opskrift til næste image-skift

1. Slå op i `actions/runner-images` om labelen er GA — en preview-label kan give lange køer.
2. Skift `runs-on` mekanisk, og tæl bagefter: `grep -rn "runs-on" .github/workflows/*.yml`.
3. Parse alle filer som YAML før push (15 jobs, 0 fejl er den slags tal der fanger tastefejl).
4. Læs **job-niveauet**, ikke workflow-niveauet: tredjeparts-actions kan fejle stille på et nyt OS.
5. Kig efter `✗` i setup-trinene, ikke kun efter `##[error]` — `setup-php` fortsætter efter et
   fejlet værktøj, og så rammer fejlen først det script der skulle bruge værktøjet.
