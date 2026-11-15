# Backup-øvelsen kørt af CI — uden om MCP

Målt og bygget 15/11 2026. Afløser den tidligere konstatering i
[`backup-og-gendannelse-2026-11-09.md`](backup-og-gendannelse-2026-11-09.md) §4 om at øvelsen
ikke kunne køres af CI.

## 1. Hvorfor den gamle vej ikke kunne automatiseres

Øvelsen lå på serveren: PHP satte en `my.cnf` sammen fra `wp-config.php`, og et shell-script
tog dump, gendannede i scratch-prefixet `sgtest_`, sammenlignede rækkeantal og ryddede op.
Adgangen gik gennem Novamira-MCP, og **det var ikke en detalje**:

- access-tokenet lever 3600 s, og **refresh-tokenet roteres ved hver fornyelse** — den gamle
  død i samme øjeblik et nyt blev udstedt;
- keep-alive-kørslen fornyer én gang i døgnet og gemmer det nye token tilbage som
  repository-secret. En ekstra fornyelse i en anden workflow ville efterlade begge kørsler
  med et dødt token.

Derfor blev S5.11 et **frist-værn** i stedet: fristen blev målt hver nat, men selve øvelsen
var en agenthandling. Det er en forsikring, der kun udløses hvis nogen ringer.

## 2. Hvad der blev bygget

`tools/db_drill_ci.py` gør **den samme måling** med en almindelig MySQL-klient over TCP —
ingen MCP, ingen PHP, ingen token-rotation:

| Trin | Kommando | Måltal |
| --- | --- | --- |
| 1. Dump | `mysqldump --single-transaction --quick --skip-lock-tables --triggers …` → `gzip -9` | bytes, sha256, sekunder |
| 2. Gendannelse | `gunzip \| sed s/<prefix>/sgtest_/g \| mysql` | **RTO** = sekunder til `mysql` afslutter |
| 3. Paritet | ét `SELECT '<navn>', COUNT(*) …` pr. tabel, `UNION ALL` | afvigelser, mangler, uventede |
| 4. Oprydning | `DROP TABLE` pr. tabel, derefter `DROP VIEW` pr. visning | droppet, **rester** |

Rapporten har samme felter som MCP-vejen (`dump_bytes`, `restore_seconds`, `rto_seconds`,
`tables_expected`, `row_mismatches`, `missing_tables`, `left_behind`, `ok`), så
`tools/db_drill_check.py` læser den **uden ændringer** — fristen, RTO-budgettet på 300 s og
tallene bliver holdt op på præcis samme måde, uanset hvem der kørte øvelsen. Historikken
(`reports/db-drill-history.json`) er også den samme, så kurven blander de to kilder uden et
særligt tilfælde.

Workflowen `.github/workflows/backup-doelse.yml` kører den 1. i måneden og har fire trin, der
hver især kan sige nej uden at skjule noget:

1. **Offline selftest** (altid) — 42 fixtures i øvelsen og 15 i værnet.
2. **Er der database-oplysninger?** Mangler `DB_HOST`, `DB_NAME`, `DB_USER` eller
   `DB_PASSWORD` som repository-secret, springes øvelsen over med en `::notice`; frist-målingen
   kører stadig, så en forfalden øvelse bliver rød som før.
3. **Kan runneren nå databasen?** `--probe` svarer exit 3 ved netværks-, værts- eller
   credential-fejl og skrives som en `::warning` med hvad ejeren kan gøre — ikke som en rød
   øvelse, for det er ikke øvelsen der fejlede.
4. **Kør øvelsen**, skriv `reports/db-drill-<dato>.json`, tilføj historik-rækken, og commit
   og push rapporten med `chore(status):`-emne (undtaget af commit-værnet, da en målt rapport
   ikke kan bære §1-procenter).

## 3. Hvad der er målt — og hvad der ikke er

| Påstand | Bevis | Status |
| --- | --- | --- |
| Værtens 3306 nås fra en cloud-IP | TCP-forbindelse 0,49 s fra agentens VM | ✓ målt |
| Værten taler MySQL | handshake-banner: **8.4.11-12** | ✓ målt |
| Hele kæden giver en grøn rapport | 42 fixtures, heraf 8 der kører `run()` med falske SQL-kald | ✓ målt offline |
| En afvigelse/rest gør rapporten rød, og værnet siger hvorfor | fixture + den rigtige `--ci`-fejlsti skrev rød rapport og historik-række, som `db_drill_check --strict` dømte rød | ✓ målt |
| Passwordet står aldrig i en argv | fixture: argv indeholder ikke passwordet; filen er `0600` | ✓ målt |
| **En grøn kørsel mod den levende database** | kræver de fire secrets | ✗ **ikke målt endnu** |

Det sidste punkt er hele pointen med at skrive det ned: **koden er verificeret, kørslen er
ikke.** Første grønne CI-kørsel er den manglende 0,05 d af S5.12, og den kan kun ejeren låse
op, fordi den kræver et credential.

## 4. De fem fælder

De tre første er arvet fra MCP-vejen og gælder uanset transporten:

1. **`CREATE DATABASE` findes ikke.** Brugeren har `GRANT ALL` på `steamgears_eu_db` alene —
   `CREATE DATABASE` svarer `ERROR 1044`. Gendannelsen skal derfor ske i samme database under
   scratch-prefixet, og oprydningen er en del af beviset, ikke en pænhed.
2. **Det gamle prefix ligger stadig i databasen** (`clk4219fc96b5_`, 12 tabeller). Et dump
   tager dem med, og `mysqldump` skriver `DROP TABLE IF EXISTS` foran hver tabel — så en naiv
   gendannelse ville overskrive dem. De udelades på dump-siden med `--ignore-table`.
3. **Visninger tælles som tabeller** i `information_schema`. `DROP TABLE` fejler på en visning,
   så oprydningen skal droppe tabeller først og visninger bagefter; ellers står de tilbage og
   `left_behind` fanger det.

To nye, som CI-vejen indførte:

4. **Et password må ikke i en argv.** `mysql -p<password>` er synligt i `ps` for alle på
   maskinen og havner i logs. Begge binærer får derfor `--defaults-extra-file` med en `0600`-fil;
   `#`, mellemrum og citationstegn escapes, så en `#` i passwordet ikke brækker filen.
5. **Et forkert `DB_PREFIX` må ikke give en tom, grøn måling.** Uden tabeller at tælle ville
   0 af 0 se perfekt ud. Øvelsen fejler derfor med det samme hvis ingen tabel bærer prefixet,
   og dommen kræver `tables_expected > 0` og `tables_restored == tables_expected`.

## 5. Grænsefladen

| Navn | Type | Betydning |
| --- | --- | --- |
| `DB_HOST` `DB_NAME` `DB_USER` `DB_PASSWORD` | repository-secrets | forbindelsen; aldrig i en fil i repoet |
| `DB_PORT` | variabel (valgfri) | standard `3306` |
| `DB_PREFIX` | variabel (valgfri) | standard `clk857797e3d3_`; skal slutte med `_` |

Exit-koder: `0` grøn · `1` øvelsen fejlede · `2` konfiguration mangler · `3` databasen blev
ikke nået. `--probe` er den hurtige diagnose; `--plan` viser kommandoerne med passwordet
maskeret, så en samtale om opsætningen ikke kræver et credential.

## 6. Hvad ejeren skal gøre

1. Opret en dedikeret databasebruger til øvelsen (ikke WordPress-brugeren) med rettigheder på
   `steamgears_eu_db` alene: `SELECT`, `SHOW VIEW`, `TRIGGER`, `EVENT`, `LOCK TABLES` til
   dumpet og `CREATE`, `DROP`, `INSERT`, `ALTER`, `INDEX`, `REFERENCES` til gendannelsen.
   Den kan droppe tabeller — det er nødvendigt for at bevise en gendannelse — så behandl
   secret'en som en adgangskode og rul den, hvis den lækker.
2. Tillad fjernadgang for brugeren. Værtens panel er typisk **IP-begrænset**, og GitHub-hostede
   runners skifter IP; enten tillades et bredt vært (`%`) for netop denne bruger, eller kørslen
   flyttes til en runner med fast IP.
3. Læg de fire værdier som repository-secrets: *Settings → Secrets and variables → Actions*.
4. Kør workflowen i hånden én gang (*Actions → Månedlig backup-øvelse → Run workflow*).
   `--probe`-trinnet siger præcis hvad der mangler, hvis forbindelsen afvises.

Tilbage til fristen: den natlige måling i `tools/natlige-vaern.sh` er uændret — den fanger en
forfalden øvelse uanset om det er CI eller et menneske der skulle have kørt den.
