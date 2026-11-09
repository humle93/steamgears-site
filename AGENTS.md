# AGENTS.md — arbejdsregler for steamgears-site

Reglerne her gælder alt arbejde i dette repo, og de gælder også når arbejdet udføres af en AI-agent.

> **Læs også [`docs/agent-profil.md`](docs/agent-profil.md)** — den er indholdet (hvad projektet er,
> verificerede fakta, adgangsmatrix, opskrifter, fælder). Denne fil er formen (procenter, status, AUTO-blokke).
> Profilen kan også hentes over MCP som `project_profile`, `project_status` og `roadmap_index`
> (serveren `steamgears-profil` i [`.mcp.json`](.mcp.json), ingen site-credential nødvendig).

## 1. Fremskridtsprocent (obligatorisk)

Alt arbejde — hvert modul, hver opgave, hver batch — skal melde sin **procentvise fremgang**.
Ingen undtagelser: gælder både når noget laves færdigt, og når der bare arbejdes videre på det.

Ved hvert arbejdspas skal der meldes:

| Felt | Krav |
| --- | --- |
| Modul | Navnet på det modul/den opgave der blev arbejdet på |
| % før | Status før passet |
| % efter | Status efter passet |
| Δ | Ændringen i procentpoint |
| Grundlag | Regnestykket bag tallet (fx `3 af 5 faser` eller `0,75 af 21,1 d`) |

Regler for tallene:

- **Procenten skal kunne genberegnes** ud fra grundlaget. Ingen tal uden regnestykke, ingen "føles som 70 %".
  Brug de dokumenterede estimater (developer-dage i `docs/roadmap.md`, timer i
  `docs/library/om-maerkning-dark-academia-2026-11-01.md` osv.) som nævner.
- **Første måling** skrives som `% før: —` (ingen tidligere baseline).
- **Procenten må ikke falde** uden at årsagen står i loggen (fx at scope er ændret eller et modul er opdelt).
- **100 % betyder færdigt og verificeret**, ikke "kode skrevet". Er noget blokeret, bliver modulet stående
  under 100 % med blokeringen noteret.
- **Blokeret arbejde tæller ikke som fremgang.** S0.4 er 0 %, selv om den er forsøgt.

## 2. Daglig projektstatus (obligatorisk)

Hvert **aktivt projekt** skal have en procentstatus hver dag i [`docs/projektstatus.md`](docs/projektstatus.md).

- Filen har to dele: **§1 Daglig status** (én række pr. aktivt projekt, dags dato) og **§2 Log** (alle målinger,
  nyeste først).
- En dag uden arbejde skrives stadig: samme procent, med grundlaget `ingen ændring`.
- `§1` overskrives hver dag; `§2` er append-only og må ikke ryddes.
- Daglig status skal **også meldes i svaret til ejeren**, ikke kun stå i filen.

## 3. Format

```
Modul: S2.3 navigation — nav-links
Før: 55,4 %  →  Efter: 56,3 %  (Δ +0,9)
Grundlag: 12,25 af 21,75 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 4,65/6 · S3 2,15/3,55 · S4 1,4/5)
```

## 4. Automation og værn (`projektstatus.py`)

Reglen over holdes automatisk, så lidt som muligt afhænger af hukommelse:

- Procenterne **beregnes** ud fra [`projektstatus.json`](projektstatus.json) (moduler med `done`/`total`/`unit`).
  Ingen procenter tastes ind i Markdown.
- [`docs/projektstatus.md`](docs/projektstatus.md) skrives kun mellem `AUTO:`-markørerne. **Manuel tekst i en
  AUTO-blok er forbudt**, og `--check` fejler hvis den ændres — ret i stedet JSON-filen.
- Sådan opdateres en procent: ret `done`/`total` i `projektstatus.json`, kør `python3 projektstatus.py --update`.
  Scriptet beregner procenter, skriver filerne og tilføjer historik-rækken selv.
- `python3 projektstatus.py --brief` skriver samme status ind i `docs/daily-briefs/<dato>.md` (markør
  `AUTO:PROJEKTSTATUS`), så procenterne også står i det brief der læses hver dag. CI kører den efter `--update`.

**Værn før skrivning** (`--selftest`, `--check`):

- 22 selftest-fixtures bekræfter at værnene fanger fejl, før noget installeres eller skrives.
- Skema og typer, `done <= total`, `total > 0`, duplikerede id'er, blandede enheder i samme projekt,
  manglende enhed/grundlag, internationally gyldige datoer og ingen dato i fremtiden.
- **100 % kræver `"verified": true`** — ellers er tallet en påstand, ikke en status.
- **Fald i procent afvises** både i forhold til forrige række og til modulernes tal, medmindre rækken har
  `"scope_change": true` (og så skal årsagen stå i `module`/`basis`).
- En **planlagt** scope-ændring markeres med `scope_change_pending` på projektet: en sætning der forklarer
  hvad der tages ud/ind. Den sætter `scope_change: true` på den ene række den gælder og forbruges derefter;
  en række for et andet projekt kan ikke brænde den. `true` eller en tom streng afvises, og en
  `scope_change`-række med et fald på 0,5 pp eller mere skal navngive ændringen i `module`.
- `delta` skal passe med `after - before`. Drift i dokumentet (håndredigerede AUTO-blokke) fanges.

**Værn efter skrivning:**

- Atomisk skrivning (temp-fil + `os.replace`) og backup til `.projektstatus-backups/` (de 10 nyeste).
- Filerne læses og valideres igen bagefter; matcher indholdet ikke det beregnede, rulles den forrige version
  tilbage og kommandoen fejler.
- Kørselslås (`.projektstatus.lock`) så to samtidige kørsler ikke kan overskrive hinanden.
- CI (`.github/workflows/projektstatus.yml`) kører selftest → check → update → efterkontrol **hver dag**, fejler
  jobbet med en annotation ved brud og committer kun når noget faktisk ændrede sig.
- **Natlig auto-push** (`.github/workflows/natlig-push.yml`): hver nat **kl. 02 dansk tid** kører værnene,
  opdaterer daglig status + brief og committer **alt** i træet og pusher til `main`, med tre genforsøg og
  `pull --rebase` hvis nogen kom først. Kørslen ligger på begge de UTC-timer der kan være kl. 02 lokalt og
  springer selv over når den lokale time ikke er 2, så sommertid ikke skal rettes. Den kan kun pushe det der
  ligger i repoet — ucommittet arbejde i en agent-sandkasse ser den ikke — så reglen om at committe og pushe i
  samme tur gælder stadig; nattens kørsel er sikkerhedsnettet, ikke en erstatning.
  Efter pushet kører kørslen **alle repoets værn** via [`tools/natlige-vaern.sh`](tools/natlige-vaern.sh):
  `php -l` på alle plugin-filer,  `projektstatus.py --selftest` og `--check`, `load_campaign.py --selftest`,
  `plugins/tests/smoke.php`, `test_mcp_rest.py`, `test_mcp_oauth.py`, `test_live_state.py`, `test_niche_scanner.py` og en
  PDF-render-kontrol af tre biblioteksdokumenter. Et rødt værn
  giver en `::error`-annotation, skriver en tabel og de fejlende linjer i job-summaryen og fejler jobbet. Det
  ligger bevidst efter pushet, så en testfejl ikke holder status-dokumenterne tilbage — men det kan ikke
  overses. Den samme kommando kan køres lokalt før et push: `bash tools/natlige-vaern.sh`.

- **Natlig niche-scanning** (`.github/workflows/niche-scan.yml`): kl. 02:30 dansk tid scanner de 11 søgeord
  fra `tools/niche_seeds.txt` mod Pinterest Trends, skriver `reports/niche-scan-<dato>.md`/`.json` og
  committer kørselsdagen i `reports/niche-scan-history.json` — så tre-dages-gaten passer sig selv. Kræver
  adgang til Pinterest: et access-token eller refresh-oplysninger; mangler begge, springes scanningen over
  med en `::notice`, og gaten flyttes ikke. Er access-tokenet udløbet (401), fornyes det med
  refresh-tokenet, og det nye par gemmes tilbage i repository-secrets via `gh secret set` — Pinterest
  roterer refresh-tokenet, så det gamle er dødt bagefter. Hvad der kræves, og hvad kørslen ikke kan: se
  [`docs/library/pinterest-token-fornyelse-2026-11-03.md`](docs/library/pinterest-token-fornyelse-2026-11-03.md).

- **Novamira keep-alive** (`.github/workflows/novamira-keepalive.yml`): MCP-adgangen til Novamira-fladen
  (`/wp-json/mcp/novamira-oauth`) har et access-token på 3600 s og et refresh-token der **roteres** ved hver
  fornyelse. Kørslen fornyer dagligt kl. 03 dansk tid med [`tools/mcp_keepalive.py`](tools/mcp_keepalive.py),
  verificerer med `initialize` + `tools/list` og gemmer det nye token tilbage som repository-secret. Uden
  `NOVAMIRA_REFRESH_TOKEN` og en PAT med Secrets: write springer den sig selv over med en `::notice`.
  Den samme fornyelse **måler site-tilstanden** med [`tools/live_state.py`](tools/live_state.py): AI Engines
  `mcp_dynamic_rest` og `mcp_abilities`, `mcp_role`, den gemte profil `sgap_ai_profile` og sitets
  plugin-version — det token kræver, findes kun i CI. Rapporten skrives til
  `reports/live-tilstand-<dato>.md`/`.json` og committes; det roterede token gemmes **før** målingen kan gøre
  jobbet rødt, så en målefejl aldrig koster adgangen. Det er den vej et spørgsmål som »har profilen tændt
  Dynamic REST på det levende site?« besvares uden ejerens browser.
  I en sandkasse kan [`tools/keepalive-loop.sh`](tools/keepalive-loop.sh) i stedet forny hvert 58. minut
  (`setsid nohup bash tools/keepalive-loop.sh 3480 &`) — den dækker kun mens worktreen kører.
  Opsætning og fælder: [`docs/mcp-auth.md`](docs/mcp-auth.md) → Novamira.

- **Månedlig backup-øvelse** (`.github/workflows/backup-doelse.yml`): øvelsen i `tools/wp_db_drill.py` blev kørt
  9/11 og beviste at dumpet kan afspilles — men et engangstal ældes. Værnet
  [`tools/db_drill_check.py`](tools/db_drill_check.py) kræver at seneste kørsel er højst **31 dage** gammel og grøn
  (0 rækkeafvigelser, 0 mangler, 0 rester, RTO under **300 s**), med **3 dages varsel** i `--due` og 15
  offline-fixtures. Det kører **hver nat** i [`tools/natlige-vaern.sh`](tools/natlige-vaern.sh), så fristen tælles
  ned dagligt, og den **1. i hver måned** kører workflowen og skriver trenden i job-summaryen. Øvelsen skriver én
  række pr. kørsel til `reports/db-drill-history.json`, så RTO og paritet er en serie og ikke ét tal. Kørslen
  **udfører ikke selv øvelsen**: den kræver MCP-adgang, og keep-alive-kørslen roterer refresh-tokenet, så en ekstra
  fornyelse ville slå begge kørsler ihjel — selve kørslen er derfor en agenthandling med rapporten committet.

- **Commit-besked-værnet** ([`tools/commit_check.py`](tools/commit_check.py)): reglen i §1 bliver kontrolleret
  maskinelt på tre flader — `--message-file` er **commit-msg-hooken** (`python3 tools/commit_check.py --install`,
  som [`tools/setup-agent-tools.sh`](tools/setup-agent-tools.sh) også kører), `--range A..B` tjekker de commits
  der blev pushet (CI: [`.github/workflows/commit-vaern.yml`](.github/workflows/commit-vaern.yml)), og
  `--selftest` kører 29 fixtures. Værnet kræver `Modul:`, `Før:`, `Efter:`, `Δ:` og `Grundlag:` — også når de
  står som `Før: 55,4 % → Efter: 56,3 % (Δ +0,9)` på én linje — afviser procentfelter der ikke er et tal eller en
  streg, kræver at `Grundlag` kan genberegnes (tal eller `ingen ændring`), og at `Δ` passer med `Efter − Før`.
  Tallene krydstjekkes desuden mod `projektstatus.json`: `Før`/`Efter`/`Δ` skal matche en historik-række, og
  `Grundlag` skal kunne læses ud af den rækkes `basis` (også når beskeden kun citerer rækkens `X af Y enhed`-del),
  så et opdigtet regnestykke afvises. Kan JSON-filen ikke læses, springes kun krydstjekket over — formen kræves
  stadig. Et pas der ikke flytter noget kan skrive `ingen ændring` uden en række. Merge-, revert- og bot-committene
  fra de to workflows er undtaget, fordi de ikke kan bære procenter; historikken før 3/11 er ikke tjekket efter. Commits der ligger fast i historikken og ikke kan rettes, står i et **verificeret register** ([`docs/commit-vaern-register.md`](docs/commit-vaern-register.md)) med grund og bevis — og registret lemper **kun den enkelte commit og kun den regel der er efterprøvet** (`basis` eller `history`). Formkravene, `Δ = Efter − Før` og kravet om et regnestykke gælder også de registrerede commits, så et nyt værn rammer dem medmindre det udtrykkeligt står i posten. `--range` skriver hver undtagelse med sin grund og hvilken regel der blev lempet.

Automationen dækker **den daglige status**, og commit-beskeden bliver holdt op på formen. Det manuelle i §1 — at
vurdere hvad der reelt blev udført og melde `% før → % efter` i svaret — kan scriptet ikke gøre og falder stadig
på den der arbejder.

## 5. Øvrige regler

- **Alt arbejde skal ende på `main`.** Push i samme tur som arbejdet blev lavet (`cloud_git`), og senest af den
  natlige kørsel kl. 02. Efterlad ikke commits lokalt "til senere".
- Repoet er dokumentation og assets for **steamgears.eu**; WordPress-kernen ligger hos Simply. Ændringer på
  selve sitet sker over MCP, ikke her.
- Sproget i commits og dokumentation er dansk, med tekniske termer på engelsk.
- Nye dokumenter lægges i `docs/` (audits og research i `docs/library/`), og refereres fra `README.md` eller
  roadmap'en, så de ikke bliver forældreløse.
- **Ny viden skal ud som PDF.** Viden der er værd at gemme — målte fakta, diagnoser, opskrifter, fejl der kostede
  en runde — skrives som dokument i `docs/library/` og renderes til en PDF i `reports/`, som lægges i det lukkede
  research-bibliotek:

  ```bash
  PYTHONPATH=tools/_deps python3 tools/md_to_pdf.py docs/library/<emne>-<dato>.md \
      reports/<emne>-<dato>.pdf --kicker "STEAMGEARS.EU · <EMNE>"
  ```

  Markdown-kilden er sandheden, PDF'en er eksporten, og **begge** skal stå i `docs/library/README.md`. Tjek siden
  visuelt før den regnes som færdig (`pypdfium2` → PNG). Rendereren klarer selv de tre ting der tidligere gik
  galt: enkelt-stjerne kursiv (`*sådan*`, også med `**fed**` indeni) skrives som «sådan», glyffer DejaVu ikke har
  oversættes (`✅`→`✓`, `❌`→`✗`) eller fjernes **med en advarselslinje** i stedet for i stilhed, og kodelinjer
  ombrydes inden for den grå boks. Et sidebrud midt i et afsnit flytter resten korrekt med over — det gjorde det
  ikke før 3/11, hvor en side fyldt til bunden kunne blive til 20 næsten tomme sider. Værnet `PDF-renderer` i
  [`tools/natlige-vaern.sh`](tools/natlige-vaern.sh) renderer tre dokumenter og fejler på sideantal, glyf-advarsler
  og tilbageværende stjerner.

## 6. Agent-kørsel og værktøjs-gaten

Platformen har en **instruktions-gate**: hvis en turns **første værktøjs-batch** ikke indeholder en læsning af
projektinstruktionerne, afvises hele batchen med _»The workspace is ready, og dette kald blev ikke kørt«_ og
en henvisning til denne fil. Gaten ligger i platformen — den findes **ikke** i repoet (ingen `.claude/`,
`.freebuff/` eller hook-scripts indeholder den besked).

Derfor:

- **Start hver tur med at læse `AGENTS.md` i samme batch som arbejdet**, fx `read_files(["AGENTS.md"])` plus de
  søgninger eller redigeringer der skal køre. Så afvises intet, og der spares en runde pr. tur.
- **Skrivninger og deres efterkontrol må ikke køre parallelt i samme batch.** Blokeres en skrivning mens en
  søskende-redigering går igennem, står repoet halvt opdateret — det er sket med et README-link til et
  dokument der endnu ikke var oprettet. Skriv først, verificér i næste kald.
- Afvises et kald alligevel: gentag det uændret i samme tur sammen med en `AGENTS.md`-læsning. Det er ikke
  nødvendigt at ændre noget i repoet for at komme videre.
- Reglerne i §1–2 og §6 findes også som **skill**: [`.agents/skills/projektstatus/SKILL.md`](.agents/skills/projektstatus/SKILL.md)
  (`/skill:projektstatus`). Den indlæses kun når et pas skal melde procenter eller røre statusfilerne —
  denne fil gælder stadig altid, og ved uenighed vinder den.

## 7. WordPress- og WooCommerce-pluginregler

Gælder alt arbejde i `plugins/`.

### 7.1 Roadmap før kode

- Start **aldrig** på koden før der ligger et roadmap med (1) komplet filstruktur for pluginnet og
  (2) estimeret tidsforbrug med procentvis opdeling fra 0 % til 100 %.
- Når roadmappet er godkendt: stop ikke undervejs. Skriv samtlige filer færdige uden placeholders eller `// TODO`.
- Angiv status ved hvert skridt, fx `[50 % — REST API & Admin UI færdig]`, og meld procenterne som §1 kræver.

### 7.2 WordPress- og PHP-arkitektur

- PHP 8.2+ kompatibel, objektorienteret struktur eller tydeligt namespacede klasser.
- Hovedfilen skal have gyldig WordPress Plugin Header og starte med `if ( ! defined( 'ABSPATH' ) ) exit;`.
- Sikkerhed: `check_ajax_referer()` / `wp_verify_nonce()`, `current_user_can( 'manage_options' )`,
  `sanitize_text_field()` på input og `esc_html()` / `esc_url()` på output.
- Database: `$wpdb->prepare()` ved custom tabeller (`dbDelta` ved aktivering) eller WordPress' Options- og
  Transients-API.
- SEO / AEO / GEO: genererer pluginnet frontend-indhold eller produktsider, skal det understøtte gyldig
  JSON-LD (Schema.org) og kun enqueue scripts på de sider der har brug for dem.

### 7.3 Syntakstjek og pakning

- Efter hver skrevet PHP-fil: `find . -name "*.php" -not -path "./vendor/*" -not -path "./.git/*" -exec php -l {} +`.
- Findes der ingen `php` i miljøet (gælder Freebuff/cloud), brug den statiske binær i `.tools/php` — hentet fra
  `https://dl.static-php.dev/static-php-cli/common/php-8.3.14-cli-linux-x86_64.tar.gz` og pakket ud, så binæren
  ligger som `.tools/php`; `.tools/` er ikke i git — og kør samme kommando med `./.tools/php -l`.
- Melder `php -l` fejl, rettes fejlen med det samme, før der gås videre.
- Ved 100 %: pak pluginnet i en installationsklar `.zip` i `plugins/` (uden `.git`, `AGENTS.md`, `node_modules`
  og `.tools`), så den kan uploades direkte under *Plugins → Tilføj nyt*.
- Der ligger **præcis én** zip i `plugins/` — den aktuelle versions. [`plugin-ci.yml`](.github/workflows/plugin-ci.yml)
  fejler hvis der er flere, så den gamle zip slettes i samme commit som den nye version er lagt ind.
- Zip-filen **skal** ligge i `plugins/` på `main` og hedde `steamgears-autopilot-v<version>.zip`. Selvopdateringen
  henter præcis `https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/steamgears-autopilot-v<version>.zip`
  (`SGAP_Updater::package_url()`), så en zip i rodmappen eller på en sidegren bliver aldrig fundet.
  **Forbehold (8/11 2026):** repoet er nu **privat**, og `SGAP_Updater` henter anonymt — zip-linket svarer
  derfor ikke længere stabilt for sitet (målt 9/11: `404`). Reglen om placering og navn gælder stadig.
  **Opdatering kan gøres af agenten over MCP (målt 9/11, 7.65.5 → 7.67.0):** læg zip'en på serveren med
  `novamira_create-upload-link` + `PUT`, og installer den med
  `novamira_run-wp-cli` og `{"args":["plugin","install","<sti>","--force","--activate"]}` — `args` **skal**
  være et array, ellers svarer WP-CLI med hjælpetekst og `exit_code: 0`. `manage-plugin` duer ikke (kun
  wordpress.org efter slug). Ryd zip'en bagefter med `novamira_delete-file`. Fælden: den første request efter
  installationen kan dø i `dbDelta` på W3TC's DB-cache og give 500 i hele REST-laget, indtil `install()` går
  igennem ved næste request — se
  [`docs/library/plugin-installation-via-mcp-2026-11-09.md`](docs/library/plugin-installation-via-mcp-2026-11-09.md)
  (den manuelle vej under *Plugins → Tilføj nyt → Upload* virker stadig og står i
  [`repo-privat-2026-11-08.md`](docs/library/repo-privat-2026-11-08.md)).

## 8. Context-håndtering — samtalens vigtigste punkter

Når klientens **context meter nærmer sig 95 %**, eller når samtalen står foran en komprimering, skal det
vigtigste fra samtalen over i [`docs/samtalepunkter.md`](docs/samtalepunkter.md) — ellers forsvinder
begrundelserne, når historikken komprimeres, og næste pas skal gætte dem igen.

- **Skriv først prosaen:** beslutninger, fund og fælder i "Beslutninger og fund" (nyeste først) og det der
  stadig mangler i "Næste skridt". Kort og konkret — hvad blev besluttet, hvad blev målt, hvad var fælden.
- **Opdatér derefter de maskinelle dele:** `python3 tools/samtalepunkter.py --update` skriver AUTO-blokken ud
  fra [`projektstatus.json`](projektstatus.json) og git-historikken; `--check` bekræfter at den matcher.
  Blokken må ikke håndredigeres, og den indeholder dagens dato og de seneste commits — derfor skal den
  **opdateres, ikke bare tjekkes**.
- **Commit og push i samme tur**, som §5 kræver. Filen er kun værd noget, hvis den ligger på `main`.
- Filen er **hukommelse om hvorfor**, ikke en kopi af [`docs/projektstatus.md`](docs/projektstatus.md), der har
  tallene. Står de to uenige, vinder `projektstatus.json`.
- Værktøjet har 10 selftest-fixtures og kører som værn i [`tools/natlige-vaern.sh`](tools/natlige-vaern.sh).
  Det regner procenter som `projektstatus.py` — undtagen `recurring`-moduler, som ikke tæller med.
