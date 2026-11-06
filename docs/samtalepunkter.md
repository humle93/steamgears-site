# Samtalens vigtigste punkter

*Formålet er context-håndtering: når samtalen nærmer sig grænsen — klientens context meter omkring **95 %** —
skal det vigtigste fra samtalen stå her og være committet, ikke kun i chat-historikken, som forsvinder når
konteksten komprimeres. Filen er repoets hukommelse om **hvorfor** tingene blev gjort, ikke en kopi af
[`projektstatus.md`](projektstatus.md), der har tallene.*

## Sådan bruges filen

- **Ved ~95 % context** (eller før en komprimering): opdatér "Beslutninger og fund" og "Næste skridt" nedenfor
  med det der er kommet til siden sidst, kør `python3 tools/samtalepunkter.py --update`, og commit + push i
  samme tur. Så kan næste pas læse sig ind på under et minut.
- **AUTO-blokken** skrives af [`tools/samtalepunkter.py`](../tools/samtalepunkter.py) ud fra
  [`projektstatus.json`](../projektstatus.json) og git-historikken. Den må ikke håndredigeres.
- Blokken indeholder dagens dato og de seneste commits, så den **skal opdateres, ikke bare tjekkes**: kør
  `--update` og derefter `--check` i samme pas. Et `--check` dagen efter vil med rette melde drift.
- Prosaen uden for markørerne er derimod kun for den der arbejder. Skriv kort og konkret: hvad blev besluttet,
  hvad blev målt, og hvad var fælden.

<!-- AUTO:SAMTALEPUNKTER:START -->
_Opdateret 2026-11-06 af `tools/samtalepunkter.py`. Blokken er maskinelt genereret — ret i `projektstatus.json`, ikke her._

### Status

| Projekt | Status | Grundlag |
| --- | --- | --- |
| **steamgears.eu — site-optimering** | **60,6 %** | 13,2 af 21,8 d (S0 1,9/2,6 · S1 2,15/4,6 · S2 5,3/6,05 · S3 2,45/3,55 · S4 1,4/5) |
| **Steamgears Autopilot — plugin** | **74 %** | 19,9 af 26,9 d (P0 4,05/4,5 · P1 9,4/13,4 · P2 4,5/7 · P3 1,95/2) |
| **Dark academia-rebrand** | **63 %** | 8,5 af 13,5 t (TEKSTER 8,5/8,5 · MOCKUPS 0/5) |
| **SEO-indholdsproduktion** | **62,5 %** | 5 af 8 guides (BATCH 0/3 · COPY 4/4 · CORSET 1/1) |
| **Døde internlinks** | **100 %** | 39 af 39 links (FIX 39/39) |

### Åbne blokeringer

- **steamgears.eu — site-optimering** — S1.1 Herbarium kræver varianter i Printify; S0.4 5 vanity-301 kræver én Redirection-gruppe (ejer). S2.2 er lukket 6/11 — trust-siderne findes og er linket ind
- **Steamgears Autopilot — plugin** — P1.5 kræver Pinterest-token (401) og Meta-review; P0.4 kræver host-cron (ejer); P3.1 mangler ejerens labor_rate-beslutning
- **Dark academia-rebrand** — Printify-adgang
- **SEO-indholdsproduktion** — Intet eksternt for de tre resterende guides: copy, FAQ, priser og links ligger i docs/library/guides-2026-11-03.md. De otte /go/-id'er 411/475/481/485/497/567 kunne ikke verificeres fra sandkassen (Simplys WAF giver browser-UA en challenge), så nye produktlinks skal måles fra ejerens side eller efter WAF-hvidlistning

### Næste skridt

- **steamgears.eu — site-optimering** — Skriv de tre manglende guides fra den verificerede copy (S2.5-meta-runde og S2.6-links er kørt; S2.2 og corset-guiden er lukket 6/11); S1.1 Herbarium kræver varianter i Printify; S0.4 kræver én Redirection-gruppe (ejer)
- **Steamgears Autopilot — plugin** — P0.4 rigtig host-cron (ejer); P3.1 labor_rate (ejer); P1.5 er eksternt blokeret (Pinterest-token + Meta-review)
- **Dark academia-rebrand** — Batch 4 — mockups (~130 billeder)
- **SEO-indholdsproduktion** — Skriv de tre manglende guides (mens-steampunk-costume-essentials, steampunk-clothing-three-budgets, womens-steampunk-essentials) fra den verificerede copy — corset-guiden er publiceret 6/11
- **Døde internlinks** — Vedligehold: tjek nye indlæg mod live-URL'er

### Seneste commits

- `d7cfd7d Modul: Samtalepunkter + Cirlas dokumentation Før: 60,6 % → Efter: 60,6 % (Δ 0) Grundlag: 0 roadmap-poster berørt — dokumentation og værktøj, ingen ændring i 13,2 af 21,8 d`
- `33c022a Modul: Plugin P1.4 — evergreen-kørslen i integrationstesten Før: 74,0 % → Efter: 74,0 % (Δ +0) Grundlag: 19,9 af 26,9 d — testdækning styrket, ingen ændring i estimatet`
- `f47ba22 style: fjern Inter-preloadet og skriv typografien ind i design.md`
- `9e1be40 chore: anden og sidste del af typografi-oprydningen`
- `4beb61f style: to skrifter i stedet for tredive — Marcellus i overskrifterne`
- `11fd392 chore: engangsarbejdsgang der retter skrifterne i header-filen`
<!-- AUTO:SAMTALEPUNKTER:END -->

## Beslutninger og fund (nyeste først)

### 6/11 — MCP-adgang: fornyelse, ikke secrets

- **Beslutning:** ingen repository-secrets til Novamira. I stedet fornyes tokenet løbende hvert 58. minut.
  Baggrunden er ejerens valg: secreterne krævede en PAT med Secrets: write, og det blev vurderet som mere
  bøvl end gavn.
- **Målt:** access-token 3600 s, refresh-token **roteres** ved hver fornyelse (det gamle er dødt bagefter).
  Introspection er admin-only, så refresh-tokenets levetid kan ikke læses udefra.
- **Bygget:** [`tools/mcp_keepalive.py`](../tools/mcp_keepalive.py) (forny + verificér med `initialize` +
  `tools/list`) og [`tools/keepalive-loop.sh`](../tools/keepalive-loop.sh) (loop hvert 3480 s, log i
  `.tools/keepalive-loop.log`, pid i `.tools/keepalive-loop.pid`, prøver igen efter 5 min og stopper efter fem
  fejl i træk).
- **Grænsen, sagt højt:** loopet lever kun, mens worktreen kører. Cloud-sandkassen er bundet til chatten, ikke
  en permanent server — bliver den arkiveret, dør processen. De 30 dage kræver enten CI-varianten
  ([`novamira-keepalive.yml`](../.github/workflows/novamira-keepalive.yml), som så kræver de to secrets) eller
  at fornyelsen flyttes ind i pluginnet og kører på sitets egen WP-Cron. Sidstnævnte er den eneste vej uden
  secrets, og den er ikke bygget endnu.
- **Fælder:** godkendelseslinket skal bruge `&` når endepunktet har query, og token-kaldene skal være
  form-encoded (JSON giver `unsupported_grant_type`). `tools/list` kræver `Mcp-Session-Id` fra `initialize`.

### 6/11 — Roadmap: to punkter lukket

- **S2.2 `/shipping/` + `/returns/`** (1396, 1397) er publiceret og målt 200. 301-vejen var umulig, fordi
  Redirection ingen gruppe har; siderne er derfor skrevet opgave-orienteret af **sitets egne publicerede
  politikfakta** (`/delivery/`, `/refund_returns/`, `/terms/`) — ingen nye løfter om tid, pris eller fortrydelse.
  Begge politik-sider linker nu ind til hubberne.
- **`/steampunk-corset-guide/`** (1394) er publiceret: to `/go/`-links (489, 491 — målt 302) i stedet for rå
  `/product/`-hrefs, plus hub-link til `/steampunk-costumes/`. De øvrige otte produktlinks blev **bevidst ikke**
  sat, fordi de ikke kunne måles herfra.
- **Fælde:** Markdown-tabeller konverteres ikke til Gutenberg af `create-post` — de ender som rå `|`-tekst.
  Skriv lister i stedet, eller ret blokken bagefter.
- **Fælde:** Simplys WAF giver browser-UA en *"Checking your browser…"*-challenge og default-UA en side med
  titlen "Page Not Found". Produkt-URL'er uden pæn permalink kan derfor ikke verificeres fra sandkassen — et
  umålt link hører ikke i en publiceret side.

### 6/11 — Repoets egne regler, der koster en runde at genopdage

- **Procent ved hvert pas**, med `Modul / Før / Efter / Δ / Grundlag` — også når svaret ellers er færdigt.
  `projektstatus.json` er sandheden, `projektstatus.md` skrives kun i AUTO-blokke, og et fald i procent
  afvises af `--check` uden `scope_change`.
- **Alt arbejde skal ende på `main`** i samme tur. Den natlige kørsel kl. 02 er et sikkerhedsnet, ikke en
  erstatning — den ser ikke ucommittet arbejde i en sandkasse.
- **Ny viden skal ud som PDF** i `reports/` og stå i [`library/README.md`](library/README.md).
- **Instruktions-gaten:** en turns første værktøjs-batch skal indeholde en læsning af `AGENTS.md`, ellers
  afvises hele batchen.

### 6/11 — Cirla

- Ejerens dokument "Cirla.pdf" er overført til
  [`library/cirla-teknisk-dokumentation-2026-11-06.md`](library/cirla-teknisk-dokumentation-2026-11-06.md).
  Det er en **plan**, ikke en måling: Cirla er endnu ikke bygget, og de fem åbne spørgsmål i dokumentets §8
  står åbne.
- Det der allerede findes i pluginnet og rører ved planen: kvalitetsgaten (P1.6), dublet-værnet (P0.6, anden
  nøgle end `_cc_sync_id`), `/go/`-tracking, og en Pinterest-kanal der er **blokeret** af et manglende token —
  ikke af manglende kode.

## Åbne beslutninger til ejeren

- **Novamira-fornyelse:** CI med to secrets, eller fornyelse i pluginnet via WP-Cron (ingen secrets)?
- **Cirlas produktionsrolle:** hvilke rettigheder præcist, og hvornår må modulet slås til i produktion?
- **De tre resterende guides:** publiceres de som de er skrevet, eller skal der fotos og gennemlæsning først?
- **S0.4:** én Redirection-gruppe skal oprettes af ejer, før de fem vanity-301 kan laves.

## Næste skridt

1. De tre manglende guides (SEO 62,5 % → 100 %): copy ligger færdig og verificeret i
   [`library/guides-2026-11-03.md`](library/guides-2026-11-03.md).
2. Byg fornyelsen ind i pluginnet, hvis de 30 dage skal holde uden secrets (WP-Cron + option, ingen nøgler i repo).
3. Cirla som til/fra-modul i pluginnet efter dokumentets §9.4 — start med trin 1, modulet slukket som standard.
4. Plugin 7.41.0 er i repo, 7.40.1 er live: opdateringen kræver ejerens klik i wp-admin.

## Hvad der ikke kan gøres herfra

- **Live publicering af produktændringer** og alt der kræver wp-admin-klik (plugin-opdatering, Redirection-gruppe).
- **Pinterest, GA4 og Command Center** — ingen adgang fra sandkassen.
- **Printify** — rebrand-mockups er blokeret uden ejerens adgang.
- **At holde en proces i live efter arkivering.** Ingen kommando her kan love det; kun CI eller sitets egen
  WP-Cron kan.
