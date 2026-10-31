# Steamgears Autopilot – status 30/10 2026 (til i morgen)

## Opdatering 31/10 – oprydning, merge og Drive
- **GitHub `main` er opdateret**: `steamgears-site/main` (plugin 7.5.0) og `command-center/main` (**v7.10**: rettelser, bro, optimering + kvalitetsgate, API-budget og /health fra v8-planen).
- **Fjernet fra steamgears-site**: Command Center v8.4-kopien (startede ikke, login kunne omgås med en cookie, indbygget adgangskode) og `.env`-filerne.
- **Drive**: `command-center-main/` på Drive var en kopi af GitHub v7.9 (26/10) – nu med en "LÆS MIG"-fil om at GitHub er master. Mappen `_GAMLE_COMMAND_CENTER_FILER_SLET_MIG` er lagt i papirkurven (kan gendannes i 30 dage).
- **Serveren må ikke længere `git pull` fra steamgears-site** – Command Center ligger kun i `humle93/command-center`. Brug "Opdater" i CC eller install.sh derfra.
- 🔴 **Mangler stadig (kun du kan)**:
  1. Tilbagekald det WordPress Application Password der lå i `.env` (Brugere → Profil → Application Passwords), opret et nyt og sæt det i CC's `.env` på serveren.
  2. Stop v8.4 på serveren hvis den kører (port 8000, åben for alle).
  3. Upload `plugins/steamgears-autopilot-v7.5.1.zip` på steamgears.eu (sitet er ikke forbundet til WordPress.com, så Claude kan ikke uploade det).
  4. Valgfrit: slet branchen `master` i command-center (gammelt v8.0-skelet) under Branches på GitHub.

## TL;DR
- **v7.5.0 er klar til upload**: `plugins/steamgears-autopilot-v7.5.1.zip` (på `main`).
- Den indeholder **alt fra live 7.4.0** + AI-skribent + Publer-udsendelse + Claude-værktøjer + nøgletest.
- Da I har **Publer Professional** (API kræver Business), kører udsendelsen som standard via **RSS-feeds**, som Publer auto-poster fra.
- Intet er uploadet til steamgears.eu – det gør du selv.

## Hvad skal du gøre (ca. 15 min)
1. **Upload** `steamgears-autopilot-v7.5.1.zip` i WordPress → Plugins → Tilføj ny → Upload (erstat 7.4.0).
2. **Steamgears → Settings**
   - *Publer*: Publish mode = **RSS feeds**. Channels: fx `facebook, instagram, pinterest`.
   - *AI content*: provider **Anthropic**, indsæt Claude API-nøgle (model tom = claude-opus-5-5). Ved gem testes nøglen automatisk (✓/✗ øverst).
   - *General*: Autopilot **On**, Full auto **Off** (godkend selv de første dage).
3. **Publer** (for hver kanal-feed fra dashboardet, fx `https://steamgears.eu/sgap-feed/facebook/`):
   Explore → RSS Feeds → tilføj feed → Settings → **Auto-post** → "use link title/description as caption" →
   *photo* for Instagram/Pinterest/Google, *link* for resten → vælg kontoen. Pinterest: sæt default board i Publer.
4. Dashboard → **Generate posts now** → godkend under *AI Drafts* (Approve & Queue) → **Publish queue now**.
5. Skriv til Claude: "kør status på autopiloten" – så tjekker jeg det via sitets værktøjer.

## Command Center ↔ Autopilot (snakker sammen)
`humle93/command-center` **main** (v7.9.1, merget 31/10):
- **Rettet**: CC fra GitHub kunne ikke starte (manglende `app/static` + `python-multipart`), dashboard gav 500
  (`ORDER BY id` på keyword_difficulty + DB-læsning efter lukket forbindelse), `smoke_test.sh` returnerede fejl ved succes.
- **Ny bro**: CC henter hver 30. min status fra pluginnet (kø, kladder, klik/ordrer pr. kanal) → panel under *Social*;
  alarm + Telegram ved nye fejl/kladder eller hvis pluginnet ikke svarer; "→ Autopilot" sender et CC-opslag som kladde til pluginnet.
- Bruger CC's eksisterende `WP_URL` / `WP_USER` / `WP_APP_PASSWORD` (brugeren skal være **administrator**).
- **Aktivér**: tryk "Opdater" i CC (self-update henter install.sh fra main) → Moduler: "Autopilot-bro" er tændt som standard.
- Kræver at plugin **7.5.0** er uploadet (ellers viser panelet "findes ikke (404)").

## Flowet
1. Dagligt: Claude skriver ét opslag pr. kanal for 1–2 indlæg/produkter (ikke promoveret de sidste 30 dage).
2. Kladder venter under *AI Drafts* (eller direkte i kø med Full auto).
3. Hvert 15. min frigives næste opslag (stille-timer 23–07, dagligt loft 8, batch 5) til kanalens RSS-feed → Publer poster.
4. Links går via `/go/<id>/` → klik og (med WooCommerce) salg tælles pr. kanal.

## Vigtige fund i dag
- **7.1.0 crashede** admin (menu-callbacks fandtes ikke) → rettet i 7.1.1.
- **7.1.1 slog sig selv fra** (dobbeltindlæsnings-vagt kørte for sent) → rettet i 7.2.0.
- **Live 7.4.0 havde en fejl**: suppression blev aldrig gemt på MySQL strict mode, men API'et svarede "ok" → rettet i 7.5.0.
- **Publer MCP/API-nøgle afvises**: "Invalid API key or not active" – fordi API kræver Business. RSS-tilstand løser det på Pro.

## Begrænsninger i RSS-tilstand (vs. Business-API)
- Pluginnet kan ikke se om Publer faktisk postede; status "published" = frigivet til feedet.
- Publer henter feeds med eget interval → lidt forsinkelse.
- Billedet tages fra linkets og:image (Rank Math sætter det) – tjek at produkter har featured image.

## Claude-værktøjer (efter upload, via Steam-forbindelsen)
status · list-drafts · review-draft · list-queue · queue-post · generate · publish · set-mode · check-keys · check-publer
(admin-only, intet sletter data).

## Test
CI (GitHub Actions) kører ved hver ændring: PHP 8.0–8.4, zip = kilde, og fuld test på rigtig WordPress 6.4 + nyeste med MySQL
(installation, REST, AI→kladde→kø→udsendelse, RSS-feed over HTTP, kliksporing, oprydning, afinstallation).
