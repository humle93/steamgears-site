# Repoet er nu privat — og det koster plugin'ets selvopdatering

8/11 2026. `humle93/steamgears-site` blev gjort **privat** på ejerens anmodning. Det er ét felt i
GitHub, men det flytter hvem der kan læse repoet — og dermed også hvad der stadig virker. Dette
dokument er både kvitteringen for ændringen og den målte regning, så den ikke opdages ved næste
plugin-opdatering.

## 1. Hvad der blev gjort

| Før | Efter |
| --- | --- |
| `"private": false`, `"visibility": "public"` | `"private": true`, `"visibility": "private"` |

Ændret med GitHub-integrationens `GITHUB_UPDATE_A_REPOSITORY` (kun `private`/`visibility`), efter at
`GITHUB_GET_REPOSITORY_PERMISSIONS_FOR_A_USER` havde bekræftet **`role_name: admin`** for `humle93`.
Intet andet blev rørt: ikke navn, ikke beskrivelse, ikke branches, ikke workflows, ikke secrets, ingen
kode. Repoet har 0 forks og 0 watchers, så ingen anden parts klon bliver berørt af, at den ikke længere
kan hentes anonymt.

## 2. Verifikation — målt udefra uden credentials

| Probe (anonym, ingen token) | Før | Efter |
| --- | --- | --- |
| `api.github.com/repos/humle93/steamgears-site` | `200` (`"private": false`) | **`404`** |
| `raw.githubusercontent.com/…/main/README.md` | `200` | **`404`** |
| `raw.githubusercontent.com/…/main/docs/roadmap.md` | `200` | **`404`** |
| `raw.githubusercontent.com/…/main/plugins/steamgears-autopilot/steamgears-autopilot.php` | `200` | **`404`** |
| `codeload.github.com/humle93/steamgears-site/zip/refs/heads/main` | `200` | **`404`** |
| `raw.githubusercontent.com/…/plugins/steamgears-autopilot-v7.65.0.zip` | `200` | `200` fra min maskine (**kun CDN-cachen**) — **404 fra sitet selv** |
| samme zip under et navn der aldrig har eksisteret (`v1.0.0.zip`) | `404` | `404` |

Den næstsidste række er en cache-effekt: den eksisterende zip svarer stadig 200 fra min maskine, også
med cache-buster i query-strengen, mens et ikke-eksisterende zip-navn giver 404 — altså **edge-cachen fra
dengang repoet var offentligt**, ikke adgang. **Målt fra sitets egen IP er den også væk:**
`wp_remote_get(SGAP_Updater::package_url('7.65.0'))` giver **404** (se §3), så der er ingen cache at
regne med i praksis.

Agentens egen vej er intakt: `cloud_git fetch` og `push` mod `origin` virker fortsat efter skiftet (samme
credentials, samme remote-URL `https://github.com/humle93/steamgears-site.git`), og `git remote -v` er
uændret.

**Verifikationsmetoden ændrer sig også, og det er værd at huske:** `git ls-remote origin` uden credentials og
de anonyme Actions-badges virker ikke længere (henholdsvis `could not read Username` og `Not Found`). CI
læses nu gennem GitHub-integrationen (`GITHUB_LIST_CHECK_RUNS_FOR_A_REF` / `…_WORKFLOW_RUNS…`) eller af
`cloud_git`; en agent der forsøger med de gamle kommandoer vil tro at repoet er væk. Privat betyder altså ikke »agenten kan ikke komme ind« — det betyder »anonyme kan ikke«.

## 3. Regningen: plugin'ets selvopdatering læser de URL'er anonymt

`plugins/steamgears-autopilot/includes/updater.php` henter alt fra én konstant:

```php
const RAW = 'https://raw.githubusercontent.com/humle93/steamgears-site/main/plugins/';
```

- `remote_version()` → `wp_remote_get(RAW . 'steamgears-autopilot/steamgears-autopilot.php')` — den fil
  svarer nu **404**, så metoden returnerer `''` (og cacher fiaskoen i 30 minutter).
- Uden versionsnummer bliver `inject_update()` s tavle tom og `run()` svarer
  **»Could not read the latest version from GitHub.«** — *Update now*-knappen i wp-admin kan altså ikke
  længere hente en nyere version af sig selv.
- `package_url()` peger på zip'en, som sitet selv får **404** på (målt nedenfor).

**Målt på sitet selv 8/11, gennem `execute-php` (site'ts eget netværk, ikke min maskine):**
`SGAP_Updater::remote_version(true)` → `""`, `update_available(true)` → `false`, og
`wp_remote_get(package_url('7.65.0'))` → **404**. Det er ikke en formodning om koden — det er det svar
sitet får lige nu.

Det er ikke en fejl i koden; den er skrevet til et offentligt repo og gjorde det rigtigt indtil i dag.
`reports/release-preflight-2026-11-08.md` og `docs/library/live-7-64-9-2026-11-08.md` målte 200 på
begge URL'er — de målinger er historiske nu, og det står der også i dem.

**Den anden regning er handlinger:** GitHub Actions-minutter tælles på private repoer (offentlige repoer
kører gratis og ubegrænset). Repoet har ni workflows, hvoraf flere kører dagligt/natligt; det skal
holde sig inden for kontoens månedlige minutter, ellers fejler de kørsler, der i dag er grønne.

## 4. Tre veje frem — ejerens valg, ikke agentens

| Vej | Hvad det betyder | Konsekvens |
| --- | --- | --- |
| **A. Bliv privat, opdater manuelt** | Ny version hentes fra det private repo (zip'en i `plugins/`) og lægges ind under *Plugins → Tilføj nyt → Upload* | Nul kodeændring; selvopdateringen forbliver død, indtil den erstattes |
| **B. Bliv privat, flyt kun release-zip'en** | Zip'en publiceres et offentligt sted (fx et lille offentligt release-repo eller en Release-asset), og `SGAP_Updater::RAW` peger derhen i næste version | Selvopdateringen virker igen, uden at noget andet i repoet bliver offentligt — kræver en kodeændring og en ny version |
| **C. Gør repoet offentligt igen** | Samme felt tilbage til `public` | Selvopdateringen virker med det samme igen; alt andet i repoet bliver læsbart for alle |

Anbefalingen: vælg A, hvis det private er et princip (det er sandsynligvis derfor det blev spurgt om);
vælg B, hvis selvopdateringen skal virke uden at hele repoet bliver offentligt. C er den ene ændring, der
gør det hele som før.

## 5. Hvad der ikke blev gjort

- Ingen kode, ingen workflow, ingen secret og ingen branch blev ændret.
- Ingen procenter flyttet: dette pas flytter intet modul (0 af 0 d) — det ændrer en forudsætning.
- Ingen af de ejer-blokerede poster blev rørt: CVR/telefon, betalingsudbyder, fragtzoner, GSC,
  witchy-tarot, Printful, de fire kladder, AIMLAPI.

Målt 8/11 2026, ca. 20:13 UTC.
