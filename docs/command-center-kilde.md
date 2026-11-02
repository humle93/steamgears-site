# Command Center — hvor kilden ligger

Kort: koden til Steamgears Command Center ligger **kun** i GitHub-repoet
[`humle93/command-center`](https://github.com/humle93/command-center) (branch `main`).
Drive-mappen `command-center-main/` er et **forældet snapshot (v7.9, 26/10 2026)** og må hverken bruges til
installation eller som udgangspunkt for et nyt repo.

Målt 2/11 2026.

| Placering | Status | Version | Handling |
| --- | --- | --- | --- |
| GitHub `humle93/command-center` (`main`) | **Master** | v7.14.0 @ `ac7dd53` (målt 2/11) | brug denne |
| GitHub `humle93/command-center` (`master`) | forældet skelet | — | kan slettes |
| Drive `command-center-main/` | ryddet 2/11 2026 | v7.9, 26/10 | ligger i skraldespanden, kan gendannes |
| GitHub `steamgears-site` (dette repo) | dokumentation og assets | — | må ikke bruges som Command Center-kilde |

## Sådan er det fastslået

- Drive-mappen indeholder en komplet app-struktur: `app/`, `deploy/`, `docs/`, `img/`, `scripts/`,
  `templates/` samt `README.md`, `install.sh`, `Dockerfile`, `docker-compose.yml`, `requirements.txt`,
  `run.sh`/`run.bat` og `.env.example`.
- Statusdokumentet der ligger ved siden af (`LÆS MIG – Command Center status 31-10 (GitHub er master)`)
  fastslår at filerne i mappen er byte-identiske med GitHub **v7.9 fra 26/10**, at aktuel version er
  **v7.10**, og at mappen ikke skal bruges til installation.
- `git ls-remote https://github.com/humle93/command-center.git` den 2/11 2026 viser branchene `main`,
  `master`, `claude/cc-code-check` og `claude/cc-v8-features` samt to pull requests; `HEAD` peger på `main`.

## Regler

- Nye ændringer i Command Center sker i `humle93/command-center`, ikke her i `steamgears-site`.
- Serveren (Ultra.cc) må ikke hente Command Center fra `steamgears-site`; koden ligger kun i
  `command-center`.
- Drive-mappen bevares ikke som arbejdskilde. Den er ryddet 2/11 2026: `command-center-main/`,
  v8-planerne (`v8_1`, `v8_2`, `v8_3`, `Juno_Implementeringsvejledning_v8.0`),
  `Social Autopilot — Command Center spec v8 (2026-10-26).md`, `command-center-social-hook.py` og
  `_GAMLE_COMMAND_CENTER_FILER_SLET_MIG` er flyttet til skraldespanden. Ét pejledokument i Drive-mappen
  `Gemini` peger på dette dokument og på GitHub-repoet. Opret ikke et nyt repo ud fra de gamle filer —
  det ville genopføre v7.9 og sprede et forældet `.env`-setup.
- `Velocity_Command_Center_v2.1_Complete_Bundle.md` og `Velocity_Trading_Motor_Plan.md` er **beholdt**, men
  Velocity-bundlet ligger nu i [`library/arkiv/`](library/arkiv/): det er et andet produkt (FastAPI-backend +
  Android-app under `com.velocity.commandcenter`), ikke Steamgears Command Center.

## Åbne punkter (fra statusdokumentet 31/10)

- `master`-branchen i `command-center` er forældet og kan slettes.
- Et WordPress application password har ligget offentligt på GitHub og skal tilbagekaldes. Se
  [`library/sikkerhed-ai-engine-2026-11-01.md`](library/sikkerhed-ai-engine-2026-11-01.md) for den
  generelle nøglehygiejne.

## Referencer

- [`library/command-center-opdatering-2026-11-02.md`](library/command-center-opdatering-2026-11-02.md) — sådan opdateres installationen på Ultra.cc til 7.14 med bruger-login (første besøg opretter brugeren på `/setup`; `APP_TOKEN` findes ikke siden 7.11).
- [`library/command-center/`](library/command-center/) — CC's egen dokumentation (roadmap, arkitektur, manual,
  installationsdokument og skærmbilleder) kopieret fra samme commit.
- [Statusdokument i Drive](https://docs.google.com/document/d/1Uk4mRpWRcaOyEYi7WaYEXVSTjx6RT7TGSuDjFpXSJRc/edit) — »LÆS MIG – Command Center status 31-10«.
- [`humle93/command-center`](https://github.com/humle93/command-center) — kilden til Command Center.
- [`README.md`](../README.md) — dette repos scope.
