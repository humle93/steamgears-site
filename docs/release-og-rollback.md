# Release og rollback — gaten, promote-trinnet og vejen tilbage

**Gælder:** steamgears.eu og dette repo · **Bygget:** 8/11 2026 som S5.9 i [roadmap'en](roadmap.md)
· **Kilder:** køreplanen for nye sites §9.4–§9.5 ([library/website-guide-2026-11-08.md](library/website-guide-2026-11-08.md))
og målingen af sitet ([library/site-mod-koereplan-2026-11-08.md](library/site-mod-koereplan-2026-11-08.md))

Reglen er enkel: **intet går på sitet uden at være tagget, og intet rulles tilbage ved at redigere i filer.**
Tilbage er altid en ny release eller en gendannelse — aldrig et manuelt indgreb midt i produktionen.

---

## 1. Hvad gaten består af

| Lag | Fil | Stopper |
| --- | --- | --- |
| Commit-besked | `tools/commit_check.py` (hook + CI) | et pas uden Modul / Før / Efter / Δ / Grundlag |
| Preflight | `tools/release.py --check --guards` | ucommittet træ, forkert gren, zip der ikke matcher headeren, tag der findes, rødt værn |
| Pre-push (lokal klon) | `.git/hooks/pre-push` (installeres af `tools/release.py --install-hook`) | et push hvor preflighten ikke holder |
| CI på hvert push | `.github/workflows/promote-vaern.yml` | samme preflight, synligt på commit'en — den del der virker på Free |
| Promote | `.github/workflows/release.yml` | en release uden et menneske, der har skrevet `TAG` |
| Tag | annotated `v<version>` | at en udgivelse ikke kan peges på bagefter |
| Rollback | nedenfor | at en fejl rettes ved at redigere i produktion |

### Det gaten ikke kan: GitHubs egen beskyttelse

Repoet er **privat på Free-planen**, hvor beskyttede branches, required status checks og environments
ignoreres (målt 8/11: en konfigureret regel ville se aktiv ud uden at virke). Derfor håndhæves reglerne
lokalt og i workflows i stedet. Den dag planen tillader det, skal disse sættes — de står også i
`python3 tools/release.py --protection`:

| Hvor | Indstilling | Plan |
| --- | --- | --- |
| Branch protection på `main` | Require a pull request before merging | Pro/Team for private repos |
| Branch protection på `main` | Require status checks: `commit-vaern`, `plugin-ci` | Pro/Team for private repos |
| Branch protection på `main` | Do not allow bypassing the above settings | Pro/Team for private repos |
| Tags | Beskyttelse af `v*`: ingen sletning, intet tvunget flyt | Pro/Team for private repos |
| Environments | `production` med required reviewer | Free kun for offentlige repos |

Indtil da er `main` beskyttet af **værnene**, ikke af GitHub: `promote-vaern.yml` kører preflight på hvert
push, den natlige kørsel kører alle 34 værn, og en lokal klon kan desuden installere pre-push-hooken med
`python3 tools/release.py --install-hook`. Det er en svagere garanti end en rigtig branch protection — den
fejler bagefter i stedet for at forhindre — og det skal stå sådan, ikke kaldes »beskyttet«. I en delt
sandkasse ligger git-mappen uden for worktreen; der installeres ingen hook.

---

## 2. Promote — fra grøn til live

1. **Værnene skal være grønne.** `bash tools/natlige-vaern.sh` (33 værn) og CI på `main`.
2. **Versionér op.** Ret `Version:` i `plugins/steamgears-autopilot/steamgears-autopilot.php`, læg den nye
   zip i `plugins/` og **slet den gamle** — der må ligge præcis én.
3. **Preflight.** `python3 tools/release.py --check --guards` skal svare `✓ alle regler holder`.
4. **Tag med et menneske bag.** Actions → *Release (manuel promote)* → `version` = 7.65.1, `confirm` = `TAG`.
   Workflowen kører preflight igen, opretter annotated tag `v7.65.1`, pusher det og laver release-noterne.
5. **Læg på sitet.** Pluginnet via *Plugins → Tilføj nyt → Upload* (selvopdateringen kan ikke bruges mens
   repoet er privat). Mu-plugins via Simplys File Manager til `wp-content/mu-plugins/` — de kan ikke uploades
   som plugin.
6. **Ryd cachen og mål.** `w3tc_flush_all()` (eller WP Admin → Performance → Empty all caches), derefter
   smoke-test: `/`, `/shop/`, produktside, `/cart/`, `/checkout/`, `/wp-json/sgap/v1/tick` og — for
   hardningen — `python3 tools/s5_measure.py --strict`.
7. **Skriv det ned.** Hvad blev udgivet, hvilket tag, hvem godkendte, og hvad målte smoke-testen.

**Stop-linjen:** fejler et trin, rulles der tilbage (§3) eller rettes fremad med en **ny** release. Der
redigeres ikke i filer på serveren for at få en release igennem.

---

## 3. Rollback — fire niveauer

| Niveau | Situation | Handling | Tid |
| --- | --- | --- | --- |
| **1. Kode i repoet** | en commit er forkert, men intet er udgivet | `git revert <sha>` → ny commit → normal promote | ~5 min |
| **2. Plugin på sitet** | en ny plugin-version fejler efter upload | upload den forrige zip fra git-historikken (§3.1) | ~5 min |
| **3. Mu-plugin eller indstilling** | hardningen eller et mu-plugin giver en fejl | fjern filen i File Manager, ryd cachen, mål igen (§3.2) | ~2 min |
| **4. Data** | ordrer, kunder eller indhold er beskadiget | gendan fra backup (§3.3) — aldrig ved at rulle kode tilbage | 30–60 min |

**Historikken skrives aldrig om.** `git reset --hard` og force-push mod `main` er forbudt; et tag flyttes
aldrig. En fejl rettes med en ny commit og et **nyt** versionsnummer, så `v7.65.1` altid betyder det samme.

### 3.1 Forrige plugin-version tilbage

```bash
# find den commit hvor den gamle zip lå i plugins/
git log --oneline -- plugins/steamgears-autopilot-v7.64.6.zip

# hent den ud af historikken UDEN at røre arbejdstræet
git show <sha>:plugins/steamgears-autopilot-v7.64.6.zip > /tmp/steamgears-autopilot-v7.64.6.zip

# bevis at arkivet er intakt, før det uploades
unzip -t /tmp/steamgears-autopilot-v7.64.6.zip | tail -2
sha256sum /tmp/steamgears-autopilot-v7.64.6.zip
```

Derefter *Plugins → Tilføj nyt → Upload* → vælg zip → »Erstat nuværende med den uploadede«. Ryd cachen, mål
`/shop/` og `/wp-json/sgap/v1/tick`, og skriv versionen ned.

**Målt 8/11:** 7.64.6 ligger i git-historikken som 375 815 bytes, 48 filer, SHA-256
`a7399406…69e6c2d` — så rollback er mulig uden en ekstra kopi i `plugins/`.

### 3.2 Mu-plugin tilbage

Mu-plugins aktiveres ikke; de indlæses fra `wp-content/mu-plugins/`. Rollback er derfor at **fjerne filen**:

1. File Manager → `wp-content/mu-plugins/` → omdøb `sgap-hardening.php` til `sgap-hardening.php.off`
   (omdøb frem for slet, så den kan komme tilbage med ét klik).
2. Ryd cachen.
3. Mål: `curl -sSI https://steamgears.eu/ | grep -i x-frame-options` skal nu være tom, og forsiden skal
   stadig svare 200. `python3 tools/s5_measure.py` viser hvilke rækker der faldt tilbage.

### 3.3 Data

Kode rulles aldrig tilbage for at rette data. En gendannelse er en **beslutning**, ikke en rutine:

- Backuppen er daglig (DB + filer), offsite, 14–30 dages retention. RPO er derfor højst 24 timer.
- Alt hvad der er sket efter backup-tidspunktet — ordrer, kundeoprettelser, lagerændringer — skal
  genindtastes eller accepteres tabt. Det er ejerens beslutning, ikke agentens.
- Gendan først til **staging**, mål at butikken virker, og først derefter til produktion.
- Skriv tidspunkt, backup-id og hvad der gik tabt i `docs/library/`.

**Målt 9/11 2026 — vores egen vej, ikke kun værtens:** `python3 tools/wp_db_drill.py` tager en dump med
`mysqldump` og **beviser** at den kan afspilles, uden at røre de levende tabeller:

| Måltal | Målt 9/11 | Hvordan |
| --- | --- | --- |
| RPO (hvor meget data må forsvinde) | **0 t** ved kommando · 24 t på værtens daglige backup | dump tages on-demand; værtens kadence kan ikke ses udefra |
| Dump | **3 338 987 bytes** (gzip) på **5 s**, sha256 `e1d028e8…` | 269 tabeller, 64,4 MB rå |
| RTO (gendannelse) | **6 s** på serveren · + tid til staging/live-flyt | gendannelse i scratch-prefix `sgtest_` |
| Bevis for indhold | **269 af 269 tabeller med identisk rækkeantal**, 0 mangler | `COUNT(*)` pr. tabel på begge sider |
| Oprydning | 269 tabeller + 4 visninger droppet, **0 tilbage** | `DROP TABLE` + `DROP VIEW` |

Tre ting skal man vide før man bruger den i en krise:

1. **Der kan ikke oprettes en ekstra database.** `GRANT ALL` gælder kun `steamgears_eu_db`, så
   `CREATE DATABASE` svarer `ERROR 1044` — gendannelsen skal ske i samme database under et scratch-prefix,
   og alle tabeller skal ryddes bagefter (værktøjet fejler hvis noget står tilbage).
2. **Et gammelt WordPress-prefix ligger stadig i databasen** (`clk4219fc96b5_`, 12 tabeller, 136 rækker).
   En dump tager dem med, og en naiv gendannelse overskriver dem, fordi `mysqldump` skriver
   `DROP TABLE IF EXISTS` før hver tabel. Værktøjet udelader dem på dump-siden.
3. **Visninger står i `information_schema` som tabeller.** `DROP TABLE` fejler på dem, så en oprydning der
   kun bruger `DROP TABLE` efterlader dem — de skal droppes med `DROP VIEW` (4 i denne database).

**Kadencen holdes maskinelt.** `python3 tools/db_drill_check.py --strict` kræver at seneste kørsel er højst
**31 dage** gammel og grøn (0 afvigelser, 0 mangler, 0 rester, RTO under **300 s**); den kører hver nat i
`tools/natlige-vaern.sh`, og `.github/workflows/backup-doelse.yml` kører den 1. i måneden med trenden i
job-summaryen. Øvelsen skriver én række pr. kørsel til `reports/db-drill-history.json`, så RTO og paritet kan
ses over tid. Selve kørslen er stadig en agenthandling: den kræver MCP-adgang, og keep-alive-kørslen roterer
refresh-tokenet, så en ekstra fornyelse i CI ville ødelægge adgangen.

En gendannelse er stadig en beslutning: prefix-bytte ændrer også data (fx `{prefix}user_roles` i `options`),
så en afspilning beviser at dumpet er komplet — ikke at siden ville virke urørt i produktion.

---

## 4. Hvornår rulles der tilbage

| Symptom | Handling | Frist |
| --- | --- | --- |
| Forside, butik eller kasse svarer 500/503 | niveau 2 eller 3 med det samme, derefter undersøg | straks |
| Kassen kan ikke gennemføre en ordre | niveau 2/3, og butikken sættes i vedligeholdelsestilstand hvis intet virker | straks |
| Ny header blokerer betaling eller tredjepart | niveau 3 (fjern mu-plugin), mål checkout | inden 15 min |
| Priser eller valuta vises forkert | niveau 1 → ny release; butikken tåler ikke gætværk | samme dag |
| Søgemaskiner indekserer testindhold | niveau 3 + `noindex`, og en ny release | samme dag |
| Kanaler eller tokens fejler efter opdatering | niveau 2 hvis kunden mærker det, ellers næste release | efter impact |

**Beslutningen tages af den der ejer resultatet.** En agent må rulle niveau 1–3 tilbage hvis den kan måle
fejlen og gendanne den målte tilstand; niveau 4 kræver ejeren.

---

## 5. Øvelse — kvartalsvis, 20 minutter

En rollback der aldrig er prøvet, er en formodning. Øvelsen køres på staging når den findes (S5.1), ellers
på et plugin der ikke er i brug:

1. Vælg den release der kører, og skriv starttid.
2. Hent den forrige zip (§3.1) og verificér `unzip -t` + SHA-256.
3. Upload, ryd cachen, mål `/shop/` og `/wp-json/sgap/v1/tick`.
4. Skriv sluttid. **Målet er under 5 minutter** — det er S5's måltal for rollback-tid.
5. Skriv resultatet i `docs/library/` med dato, tid og hvad der overraskede.

Kode-rollback (niveau 1) øves i samme omgang: `git revert` af en vilkårlig commit på en sidegren, set efter
at preflighten stadig er grøn.

---

## 6. Hvad rutinen ikke dækker

- **Sitet kan ikke ændres af CI.** Der er ingen site-credential i workflows (0 secrets målt 8/11), så trin 5
  i promote er altid et menneske med File Manager eller wp-admin.
- **GitHubs beskyttelse** er ikke slået til og kan ikke slås til på Free/private (§1).
- **Object cache og CDN** har ingen rollback: efter en ændring skal cachen ryddes, ellers ser rollbacken ud
  som om den ikke virkede.
- **Database-migreringer** er ikkeversionsstyrede. En ændring i DB rulles ikke tilbage med koden — den skal
  have sin egen backup først.
- **Rollback af forsiden og temaet** sker over MCP/Novamira, ikke her; samme regel gælder: genskab den
  forrige tilstand, redigér ikke i den nuværende.
