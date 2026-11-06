# Cirla — teknisk dokumentation og build- og deploy-plan

*Kilde: ejerens dokument "Cirla.pdf" (12 sider, 6/11 2026, @Henning Amundsen), overført ord for ord til
Markdown 6/11 2026. Dokumentet er ejerens plan, ikke en måling af sitet — det er ikke verificeret af agenten,
og de fem åbne spørgsmål i §8 står stadig åbne. Repoets egne målinger, der grænser op til dokumentet, er
noteret til sidst i "Repoets noter".*

## 1. Overblik

Cirla er Steamgears' WordPress-administrationsbot. Cirla har superbruger-rettigheder i WordPress, kører på
AIMLAPI som AI-lag og er indtil videre kun godkendt til testmiljøet. Cirla er den udførende del af det samlede
Steamgears Autopilot & Command Center-system, som skal drive content, SEO og social distribution for
steamgears.eu med så lidt manuelt arbejde som muligt.

### Formål

- Automatisere søgeords- og trendanalyse og omsætte resultaterne til WooCommerce-kladder og artikler.
- Udgive og planlægge sociale opslag (først Pinterest-serien Steam Rail Legends, senere TikTok).
- Sikre kvalitet før publicering gennem en kvalitetsgate og pre-flight-tjek.
- Give ét styringspunkt (Command Center) med overblik over status, fejl og resultater.

### Scope

**Princip:** Cirla laver forslag og kladder; et menneske godkender, før noget går live i produktion.
Superbruger-rettighederne bruges kun i testmiljøet, indtil sikkerheds- og rollback-kravene i afsnit 5 og 7 er
opfyldt.

| Inden for scope | Uden for scope (indtil videre) |
| --- | --- |
| Kladder af produkter, artikler og sider i WordPress/WooCommerce | Automatisk publicering i produktion uden menneskelig godkendelse |
| SEO-, AEO- og GEO-optimering af meta, overskrifter og intern linking | Ændring af priser, lager og ordrer |
| Social planlægning via kø og Publer | Betalt annoncering |
| Kvalitetstjek og rapportering i Command Center | Juno/GSC-broen (droppet) |

## 2. Systemarkitektur

### Komponenter

```
Command Center v7.9 PRO (FastAPI + SQLite, Ultra.cc)
  -> REST + X-API-Key -> WordPress / WooCommerce (steamgears.eu)
       -> Steamgears Autopilot-plugin
            -> AIMLAPI (Cirlas AI-lag)
            -> SerpApi (søgeord og SERP)
            -> Publer (Pinterest / TikTok)
            -> Kvalitetsgate & Pre-Flight
       -> steamgears-tools (MCP)

Publer -> Pinterest / TikTok -> (UTM-trafik) -> GA4 -> Command Center
```

### Dataflow

1. Command Center (eller Autopilot-planlæggeren) starter et job: søgeordsanalyse, kladde eller socialt opslag.
2. Autopilot henter data fra SerpApi og genererer tekst og meta via AIMLAPI.
3. Resultatet sendes gennem kvalitetsgaten. Fejler det, stopper jobbet og fejlen logges.
4. Godkendt indhold gemmes som kladde i WordPress med et `_cc_sync_id` i `wp_postmeta`, så samme job aldrig
   skaber dubletter.
5. Sociale opslag lægges i kø og sendes til Publer på de faste tidspunkter.
6. Trafik måles i GA4 via UTM-parametre og vises i Command Center.

### Miljøer

| Miljø | Formål | Cirla-rettigheder | Publicering |
| --- | --- | --- | --- |
| Lokal | Udvikling af CC og plugin | Ingen | Ingen |
| Test (staging) | Afprøvning af jobs, nøgler og kvalitetsgate | Superbruger | Kun kladder og test-opslag |
| Produktion (steamgears.eu) | Live butik | Begrænset rolle (se afsnit 5) | Kladder; publicering efter godkendelse |

### Om "uafhængig drift"

Systemet kører selv sin planlægning og logik, men er afhængigt af eksterne tjenester: AIMLAPI, SerpApi og
Publer. "Uafhængig drift" betyder derfor: ingen afhængighed af manuelle trin eller tredjeparts-dashboards for
styring, men stadig afhængighed af disse API'er for data, AI og udgivelse.

## 3. Komponenter i detaljer

### 3.1 Command Center (v7.9 PRO → v8)

Teknologi: FastAPI-backend med SQLite, kørende på Ultra.cc. Rolle: styringspunkt for jobs, status, logs og
nøgletal (SEO, social, GA4). v8: modernisering af design og arbejdsgange. Juno/GSC-broen udgår. Data:
jobhistorik, sync-id'er, fejllog og målinger gemmes i SQLite. Projektmateriale ligger på Google Drive.

### 3.2 WordPress API-bro

Lukket REST-endepunkt, der kun accepterer kald med gyldig `X-API-Key`-header. Faste timeouts på alle kald, så
et hængende kald ikke blokerer køen. Idempotens: hvert job har et unikt `_cc_sync_id`, som gemmes i
`wp_postmeta`. Findes id'et allerede, opdateres den eksisterende post i stedet for at oprette en ny.

### 3.3 Steamgears Autopilot-plugin

Planlægger og kører jobs via WP-Cron: daglig skrivning, udgivelseskø og selvtjek. Skriver artikler, guides og
produktbeskrivelser som kladder med titel, slug, meta, overskrifter, intern linking og FAQ. Håndterer social
kø, kanaler og tracking-links (`/go/`). Bør bruge samme `_cc_sync_id`-mønster som broen, så også Autopilots
egne jobs er idempotente (se åbne spørgsmål).

### 3.4 steamgears-tools (MCP)

MCP-værktøjer, der lader Claude og Cirla læse og ændre indhold, produkter, SEO-meta, cache og indeksering
(IndexNow) direkte i WordPress. Skriveoperationer laves som standard som kladder eller tørkørsel.

### 3.5 SEO/AEO/GEO Smart Optimizer

Gennemgår sider, indlæg og produkter mod regler for klassisk SEO, svarmaskiner (AEO) og generative søgemaskiner
(GEO). Skriver kun meta (titel, beskrivelse, fokusord) via Rank Math. Brødtekst ændres ikke automatisk. Tjekker
sitedækkende forhold: llms.txt, sitemap, robots og produktfeed.

### 3.6 Kvalitetsgate & Pre-Flight

Se afsnit 4.3.

### 3.7 AI-lag (AIMLAPI)

Cirla bruger AIMLAPI til tekst og billeder. Modelvalg og omkostninger følges via AIMLAPI-kontoens saldo og
forbrug. Prompter og stilregler versioneres sammen med pluginet.

## 4. Pipelines

### 4.1 Content- og SEO-pipeline

1. Søgeords- og trendanalyse via SerpApi: "Folk spurgte også", relaterede søgninger og top-sider.
2. Emnevalg: prioritering af lang-hale-søgeord og emner, sitet ikke dækker endnu.
3. Generering via AIMLAPI: titel, slug, meta, H2/H3-struktur, FAQ (med schema) og intern linking til
   eksisterende sider.
4. Kvalitetsgate (4.3).
5. Kladde i WordPress/WooCommerce med `_cc_sync_id`.
6. Godkendelse af et menneske og derefter publicering og IndexNow-ping.

### 4.2 Pinterest-pipeline: Steam Rail Legends

Tidspunkter: 08:30, 15:00 og 20:30 CET. UTM-parametre:
`utm_source=pinterest&utm_medium=social&utm_campaign=steam_rail_legends`.

Krav: hvert pin har et billede (Pinterest kræver det), et board, alt-tekst og et tracking-link.

Afstemning: tidspunkterne i dokumentationen skal matche pluginets indstillinger for stille timer, dagligt loft
og batchstørrelse. Afvigelser er en kendt fejl (afsnit 8).

Fase 2-udvidelse: TikTok-kø med samme mønster.

### 4.3 Kvalitetsgate & Pre-Flight

Intet går videre uden at bestå:

| Tjek | Handling ved fejl |
| --- | --- |
| Døde links | Stop, log, ret link |
| Forbudte ord | Stop, log |
| Manglende alt-tekst | Stop eller generér alt-tekst og tjek igen |
| Dubletter (titel, tekst eller opslag sendt før på kanalen) | Stop |
| Tekstlængde og hashtag-loft pr. kanal | Forkort automatisk, ellers stop |
| Billedformat og -størrelse (Instagram/Pinterest) | Stop |

Resultatet af hvert tjek logges i Command Center, så fejlrate pr. tjek kan følges over tid.

## 5. Sikkerhed, rettigheder og nøglehåndtering

### Adgang

- API-broen er lukket og kræver `X-API-Key`. Nøglen roteres mindst hvert kvartal og straks ved mistanke om læk.
- Cirla i test: superbruger, så alle funktioner kan afprøves.
- Cirla i produktion: egen WordPress-bruger med mindst mulige rettigheder (fx redaktør plus de specifikke
  rettigheder pluginet kræver). Ingen adgang til brugere, betaling eller plugin-installation.

### Nøgler

| Nøgle | Hvor den bruges | Opbevaring |
| --- | --- | --- |
| `X-API-Key` (CC ↔ WP) | Command Center og WordPress | Miljøvariabel på Ultra.cc og wp-config/plugin-indstilling, aldrig i kode |
| AIMLAPI | Autopilot / Cirla | Plugin-indstilling, vises kun som sat/ikke sat |
| SerpApi | Autopilot | Samme |
| Publer | Autopilot | Samme |

Regler: ingen nøgler i Git, logs eller dokumenter. Separate nøgler for test og produktion. Forbrugsgrænser sat
hos AIMLAPI, så en fejlløkke ikke tømmer saldoen.

### Integritet

Idempotens via `_cc_sync_id` forhindrer dubletter ved gentagne kald. Alle skrivninger logges med tidspunkt,
job-id og afsender. Backup af database og filer før hver deploy (afsnit 7).

## 6. Build-plan

**Fase 1: Fundament (afsluttet)** — Sikkerhed: lukket endepunkt med X-API-Key og timeouts. API-bro mellem
Command Center og WordPress med `_cc_sync_id`. WooCommerce-kladder fra content-pipelinen.

**Fase 2: Social og måling (nu)**

| Opgave | Acceptkriterie |
| --- | --- |
| Pinterest-kø (Steam Rail Legends) | 3 pins/dag sendes til de aftalte tider i 14 dage uden manuel indgriben |
| Afstem tidspunkter med plugin | Pluginets indstillinger og dokumentationen viser samme tider |
| TikTok-kø | Første 10 opslag planlagt og sendt via kø |
| Idempotens i Autopilot | Samme job kørt to gange giver én post/ét opslag |
| GA4 UTM-verifikation | Sessioner med `utm_campaign=steam_rail_legends` er synlige i GA4 inden 48 timer efter første pin, og tallet vises i Command Center |

**Fase 3: Flows** — Anbefalingsflow (relaterede produkter og artikler). Supportflow (svar på typiske
kundespørgsmål ud fra eksisterende indhold). Acceptkriterier fastlægges, når fase 2 er lukket. Command Center
v8: nyt design og forenklede arbejdsgange, jobstatus, fejl pr. kvalitetstjek og GA4-tal samlet på én side,
Juno/GSC-bro fjernet fra koden.

## 7. Deploy-plan

### Miljøer og rækkefølge

Lokal → Test → Produktion. Intet går i produktion uden at have kørt i test.

### Release-proces

1. Ændring laves lokalt og versioneres i Git med versionsnummer (fx CC v8.0.0, plugin x.y.z).
2. Deploy til test: Command Center-slot på Ultra.cc og testsitet.
3. Røgtest: opret en kladde, kør kvalitetsgaten, send et test-opslag, kontrollér at sync-id forhindrer dublet.
4. Godkendelse fra Henning.
5. Backup af produktionsdatabase, plugin-mappe og SQLite-fil.
6. Deploy til produktion uden for de planlagte udgivelsestider.
7. Tøm cache og kør selvtjek.
8. Overvåg i 24 timer.

### Rollback

Plugin: gendan forrige version fra backup eller Git-tag og deaktivér nye cron-jobs. Command Center: genstart
forrige release på Ultra.cc og gendan SQLite-fil. Indhold: fejlagtige kladder findes via `_cc_sync_id` og
flyttes til papirkurven. Rollback udløses ved: fejlrate over aftalt grænse, dubletter, eller sider der svarer
med fejl.

### Overvågning

Selvtjek dagligt: feeds, AI-nøgle, autopilot-status, kladder der hober sig op, fejlede opslag. PHP-fejllog og
WP-Cron-status. AIMLAPI-saldo og forbrug. Oppetid på steamgears.eu og Command Center.

## 8. Drift, risici og åbne spørgsmål

### Risici

| Risiko | Konsekvens | Modtræk |
| --- | --- | --- |
| Ekstern tjeneste nede (AIMLAPI, SerpApi, Publer) | Jobs stopper | Kø med genforsøg, fejl vises i CC |
| Superbruger-rettigheder i produktion | Stor skade ved fejl | Begrænset rolle i produktion |
| Dubletter fra Autopilot-jobs | Dobbelte kladder/opslag | Indfør `_cc_sync_id` i Autopilot |
| Ukontrolleret AI-forbrug | Omkostninger | Forbrugsgrænser og daglig saldo-kontrol |
| Lav kvalitet i genereret indhold | SEO- og brandskade | Kvalitetsgate og menneskelig godkendelse |

### Åbne spørgsmål

1. GA4-mål: hvilket tal er succes for Pinterest-trafikken (fx sessioner pr. uge eller konverteringer)?
2. Idempotens i Autopilot: skal pluginet bruge samme `_cc_sync_id`-mønster som broen?
3. Pinterest-tider: stemmer 08:30/15:00/20:30 CET med pluginets stille timer og daglige loft?
4. "Uafhængig drift": er definitionen i afsnit 2 dækkende?
5. Produktionsrolle for Cirla: hvilke rettigheder skal den præcist have?

Rettelser til kildedokumentet: "Oversikt" → Overblik, "tidsouts" → timeouts.

## 9. Bygge- og opsætningsguide til ChatGPT

Dette afsnit kan gives direkte til ChatGPT (som Custom GPT, Project eller første besked i en chat). I denne
fase bygges Cirla ikke sammen med Command Center. Cirla bygges som et modul i Steamgears Autopilot-pluginet,
som kan slås til og fra. Adgangen går gennem den MCP-forbindelse, der allerede er sat op
(`steamgears-autopilot-*` og `steamgears-tools-*`). Del aldrig rigtige nøgler med ChatGPT; brug pladsholdere
som `<AIMLAPI_KEY>`.

### 9.1 Instruktioner til ChatGPT (kopiér hele blokken)

> Du er teknisk assistent for Henning på projektet "Cirla".
>
> **SYSTEMET**
> - Cirla: WordPress-administrationsbot med AI-lag på AIMLAPI.
> - Cirla bygges som et MODUL i pluginet Steamgears Autopilot (social media-pluginet).
> - Modulet skal kunne slås til og fra med én indstilling. Slukket = ingen hooks, ingen cron, ingen AI-kald.
> - Adgang sker via den eksisterende MCP-forbindelse til WordPress (`steamgears-autopilot_*` og
>   `steamgears-tools_*`). Der skal IKKE bygges nye REST-broer, API-nøgler eller Command Center-integration i
>   denne fase.
> - Pluginet har allerede: AI-nøgle (AIMLAPI), Publer, SerpApi, kvalitetsgate, kø, kladder og selvtjek.
> - Miljøer: testsite først, steamgears.eu (produktion) til sidst.
>
> **REGLER**
> 1. Rækkefølge: lokal/test → produktion. Foreslå aldrig ændringer direkte i produktion.
> 2. Modulet skal være slukket som standard ved installation og opdatering.
> 3. Bed aldrig om rigtige nøgler. Genbrug pluginets gemte nøgler; vis dem aldrig i logs eller svar.
> 4. Alt indhold oprettes som kladde. Publicering kræver Hennings godkendelse.
> 5. Alt Cirla laver skal gennem pluginets kvalitetsgate (døde links, forbudte ord, alt-tekst, dubletter,
>    længde, billedformat).
> 6. Cirla må kun bruge rettigheder, modulet eksplicit tildeler. Superbruger kun på testsite.
> 7. Før hver deploy: backup af database og plugin-mappe. Beskriv altid rollback.
> 8. Svar på dansk. Ét trin ad gangen med konkrete filstier og kode, og spørg efter output før næste trin.
>
> **PINTEREST (Steam Rail Legends)**
> - Tider: 08:30, 15:00, 20:30 CET.
> - UTM: `utm_source=pinterest&utm_medium=social&utm_campaign=steam_rail_legends`
>
> **NÅR DU ER I TVIVL** — Spørg i stedet for at gætte, især om rettigheder, nøgler og produktion.

### 9.2 Forudsætninger

- Steamgears Autopilot er installeret og aktivt på testsite og steamgears.eu.
- MCP-forbindelsen virker (fx svarer `steamgears-autopilot_status` og `steamgears-autopilot_self-check`).
- AIMLAPI-nøglen er gemt i pluginets indstillinger (tjek med `steamgears-autopilot_check-keys`).
- Administratoradgang til testsite og adgang til pluginets kildekode (Git-repo).
- Fungerende backup af testsite.

### 9.3 Modulets opbygning

| Del | Placering | Formål |
| --- | --- | --- |
| Modulfil | `modules/cirla/class-cirla.php` | Indlæses kun, når modulet er slået til |
| Til/fra-indstilling | Option `cirla_enabled` (`yes` / `no`, standard `no`) | Hovedafbryder for hele modulet |
| Indstillingsfane | Autopilot → Indstillinger → Cirla | Til/fra, tilladte handlinger, log-niveau |
| Handlinger (abilities) | Registreres via WordPress Abilities API | Gør Cirlas handlinger tilgængelige over den eksisterende MCP |
| Rettighedstjek | Egen capability, fx `sgap_cirla_run` | Begrænser hvem og hvad må køre |
| Log | Pluginets eksisterende log / fejllog | Hver Cirla-handling logges med tidspunkt og resultat |

### 9.4 Byggetrin

1. **Opret modulmappen og indlæsning.** Tilføj `modules/cirla/`. I pluginets hovedfil: indlæs kun modulet, hvis
   `get_option('cirla_enabled') === 'yes'`. Sæt standardværdien til `no` ved aktivering og opdatering.
2. **Til/fra-indstilling.** Tilføj nøglen `cirla_enabled` til pluginets indstillinger, så den også kan styres via
   `steamgears-autopilot_update-settings`. Når modulet slås fra: afmeld Cirlas cron-jobs og stop igangværende
   Cirla-opgaver.
3. **Registrér Cirlas handlinger.** Start med få, sikre handlinger: læs status, lav kladde, kør SEO-tjek, foreslå
   sociale opslag. Hver handling tjekker først, at modulet er slået til, og at brugeren har `sgap_cirla_run`.
   Ingen handling må publicere direkte eller slette indhold permanent.
4. **AI-laget.** Genbrug pluginets eksisterende AIMLAPI-opsætning; ingen ny nøgle. Faste timeouts og et dagligt
   loft for antal AI-kald.
5. **Kvalitetsgate og kø.** Alt Cirla-output sendes gennem pluginets kvalitetsgate og lander som kladde eller i
   kladdekøen.
6. **Cirla-bruger.** Test: administrator. Produktion (senere): redaktør + kun `sgap_cirla_run` og de rettigheder
   handlingerne kræver.

### 9.5 Verifikation (røgtest på testsite)

| Test | Forventet resultat |
| --- | --- |
| Modulet slået fra (standard) | Ingen Cirla-handlinger synlige over MCP, ingen Cirla-cron |
| Slå til via `steamgears-autopilot_update-settings` | Cirla-handlinger dukker op ved næste MCP-forbindelse |
| Handling uden rettighed | Afvist med tydelig fejl |
| Lav kladde via Cirla | Kladde i WordPress, intet publiceret |
| Samme opgave to gange | Ingen dublet |
| Indhold med dødt link | Stoppet af kvalitetsgaten og logget |
| Slå fra igen | Handlinger og cron forsvinder; eksisterende kladder bevares |
| `steamgears-autopilot_self-check` | Ingen nye fejl |

### 9.6 Fra test til produktion

Følg afsnit 7 (release-proces og rollback). Deploy pluginopdateringen med modulet slukket, opret Cirla-brugeren
med begrænset rolle, slå modulet til og overvåg i 24 timer. Rollback er i første omgang blot at slå modulet fra.

### 9.7 Startbesked til ChatGPT

> Jeg vil bygge Cirla som et til/fra-modul i Steamgears Autopilot efter instruktionerne. Start med trin 9.4.1.
> Giv mig kun det første trin og vent på mit output.

### 9.8 Senere fase

Command Center-integration (v8) er udskudt. Når den bliver aktuel, kan den bygges som en ekstra forbindelse til
det samme modul uden at ændre til/fra-princippet.

---

## Repoets noter (tilføjet af agenten 6/11 2026)

Ikke en del af kildedokumentet, men det i repoet der allerede rører ved planen — så dokumentet ikke læses som
om alt er nyt:

- **Kvalitetsgaten findes allerede** i pluginnet (P1.6, 7.22.0, `includes/quality.php`): døde links, bandeord,
  manglende alt-tekst og næsten-dubletter, med tilstandene `block`/`warn`/`off`. Målt live 6/11: `mode: block`,
  men `banned_words: 0`, fordi ejerens liste er tom — det tjek kan ikke fyre endnu.
- **Idempotens er delvist bygget**: P0.6 (7.40.1, `includes/duplicate.php`) afviser en kø-post med samme
  `channel` + `destination_url` + `title` (409) med et tidsvindue for publicerede poster. Det er ikke samme
  nøgle som `_cc_sync_id`, så spørgsmål 2 i §8 er stadig åbent.
- **`/go/`-tracking virker**: 34 links på outfit-guiden og nu også corset-guiden (489, 491), alle målt 302.
- **Pinterest-kanalen er blokeret**, ikke bygget: tokenet svarer 401 og et nyt kræver ejerens godkendelse
  (P1.5). Tiderne 08:30/15:00/20:30 CET kan derfor ikke afstemmes mod en kørende kø endnu.
- **Plugin-versionen live er 7.40.1**, repoet er 7.41.0; opdateringen kræver ejerens klik i wp-admin.
- **Command Center er ikke tilgængeligt herfra**, så alt i §3.1 og §6 om CC er plan, ikke måling.
