# Opdater Command Center på Ultra.cc til 7.14 (bruger-login)

Skrevet 2/11 2026. Kilder: `humle93/command-center` @ `ac7dd53` (`README.md`, `scripts/update.sh`,
`install.sh`, `deploy/command-center.user.service`, `docs/Command_Center_Install_Script.md`).

## Hvad du får

Alt fra **7.11** og frem har bruger-login: første besøg opretter din bruger på `/setup`, derefter log ind
med brugernavn + adgangskode (PBKDF2-SHA256, salt pr. bruger, 30 dages HttpOnly-session). `APP_TOKEN`
findes ikke længere. Seneste `main` er **7.14.0** — der er ingen grund til at stoppe på 7.11, de senere
versioner er alarmer, Autopilot-bro og nginx/Ultra.cc-understøttelse ovenpå.

Installationen på `laser.usbx.me:8000` svarede ikke fra arbejdsmaskinen 2/11 (timeout), så versionen dér
er ikke målt. Trin 1 afgør det.

## 1. Tjek hvad der kører (1 minut)

```bash
cd ~/command-center
tail -20 app/data/startup.log     # version + nyheder, skrevet ved hver opstart
curl -s http://127.0.0.1:8000/health   # 200 = appen kører; 503 = DB/disk-problem
```

Login-siden viser også version og commit uden at man er logget ind (7.13+).

- **7.14 / 7.13** → du er på seneste. Har du stadig token-login, er det fordi der endnu ikke er oprettet
  en bruger: gå til `/setup` og lav den. Intet skal opdateres.
- **7.11–7.12** → kør trin 3 for at få nginx-, alarm- og Autopilot-forbedringerne.
- **7.10 eller ældre** → token-login er stadig i brug. Kør trin 2 og 3.

## 2. Backup først (2 minutter)

```bash
cd ~/command-center
cp app/data/center.db ~/center-$(date +%F-%H%M).db
ls -1t app/backups/ | head -3    # appen laver også cc-daglig-* og cc-pre-update-* selv
```

Databasen indeholder brugere, sessioner, crawl-data og indstillinger. Den er den eneste fil der ikke kan
genskabes fra GitHub.

## 3. Opdater (vælg én vej)

**A. Cockpit — nemmest, findes fra 7.11.** Cockpit → *Opdatering* → **Tjek for nye commits** (viser
installeret vs. seneste commit) → **Opdater**. Den tager en `pre-update`-backup, henter kun projektets
filer (`app/`, `scripts/`, `deploy/`, requirements, start-scripts, `install.sh`), kører `pip install -r`
hvis `requirements.txt` er ændret, og genstarter sig selv efter ca. 1,5 s. `.env`, databasen og
`app/data/` røres ikke.

**B. Terminal — samme kæde, ingen browser.**

```bash
cd ~/command-center
bash scripts/update.sh --check     # kun tjek: hvad venter der?
bash scripts/update.sh             # filer + requirements + genstart, venter til /health svarer
```

`--no-restart` opdaterer uden genstart (genstart selv med `bash scripts/restart.sh`). Scriptet kræver
`venv/` — mangler den: start appen én gang med `./run.sh`.

**C. Version så gammel at `scripts/update.sh` ikke findes.** Så er der ingen opdateringsfunktion i appen,
og `install.sh` er vejen: hent den fra repoet og kør den i `~/command-center`.

```bash
cd ~/command-center
curl -fsSL https://raw.githubusercontent.com/humle93/command-center/main/install.sh -o install.sh
bash install.sh
```

`install.sh` **rører aldrig en eksisterende `.env`** (den opretter kun `.env` fra `.env.example` hvis den
mangler), men den overskriver projektfilerne — derfor backup i trin 2. Start bagefter appen igen som du
plejer (`systemctl --user restart command-center` eller `./run.sh`).

**Docker:** `cd ~/command-center && docker compose up -d --build`. Self-update inde i containeren er ikke
vejen — byg imageet igen.

## 4. Opret brugeren (1 minut)

Åbn `CC_PUBLIC_URL` (eller `http://127.0.0.1:8000` via SSH-tunnel). Appen sender dig til **`/setup`**, fordi
der ikke findes brugere endnu:

1. Vælg brugernavn og adgangskode (mindst 8 tegn), bekræft.
2. Du er logget ind med det samme, og hændelsen logges som alarm.
3. Flere brugere, sletning og adgangskode-skift: fanen **Brugere**.

Herefter fører `/setup` altid til `/login`, så længe der er mindst én bruger.

## 5. Ultra.cc-specifikt (kun hvis noget ikke virker)

| Symptom | Årsag og rettelse |
| --- | --- |
| 502 fra nginx | Appen kører ikke: `systemctl --user status command-center`, `journalctl --user -u command-center -n 50` |
| Session smides ud hele tiden | `X-Forwarded-Proto $scheme;` mangler i proxy-blokken → cookien bliver ikke `Secure` |
| Alle loginforsøg ser ud til at komme fra samme IP | `X-Forwarded-For $proxy_add_x_forwarded_for;` mangler |
| 504 ved crawl/backlink | `proxy_read_timeout 300s;` mangler |
| Siden viser HTTP-advarsel | `.env`: `CC_PUBLIC_URL=https://<din-adresse>`, `CC_HOST=127.0.0.1`, `CC_FORWARDED_ALLOW_IPS=127.0.0.1` |
| Opdatering svarer 401/403 fra GitHub | `GITHUB_TOKEN=` i `.env` (repoet er offentligt, men GitHub rate-limiter uautentificerede kald) |

Proxy-filen ligger i `~/.apps/nginx/proxy.d/` på Ultra.cc; genindlæs nginx efter ændringer. Appen kan
**ikke** ligge på en understi (`https://host/apps/cc/`) — brug subdomæne eller egen port.

## 6. Hvis opdateringen går galt

```bash
# nyeste pre-update-backup tilbage:
cd ~/command-center
cp "$(ls -1t app/backups/cc-pre-update-*.db | head -1)" app/data/center.db
bash scripts/restart.sh
```

Koden rulles tilbage ved at køre `install.sh` fra den commit du vil have (fx
`https://raw.githubusercontent.com/humle93/command-center/<sha>/install.sh`). Databasen er bagudkompatibel
— appen migrerer selv ved opstart og sletter ikke data.
