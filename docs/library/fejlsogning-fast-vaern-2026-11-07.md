# Fejlsøgning som fast værn — 7.53.1 (7/11 2026)

**Spørgsmålet var: «scan alt igennem for fejl, indfør fejlsøgning i roadmap, fix alle fejl».** Svaret er
todelt. Der er nu en **kørsel** der leder efter de fejl der ikke giver en fejlkode — og den fandt fem, som alle
er rettet i 7.53.1. Og fejlsøgning står nu som et fast punkt i begge roadmap'er i stedet for at være noget nogen
skal huske: `tools/fejlscan.py` er **værn nr. 23–24** i `tools/natlige-vaern.sh`, og plugin-roadmappet har fået
rækken **P3.6**.

Målt: **770 smoke-checks, 0 FAIL** (var 766) · **24 af 24 værn grønne** · zip byte-identisk med kilden ·
plugin **90,9 % → 91,0 %** (26,3 af 28,9 d) · site uændret **64,4 %** (15,65 af 24,3 d).

---

## 1. Hvad «scan alt igennem» betyder her

En scanning der kun kører det der allerede kører, finder kun det der allerede er fanget. Rækkefølgen var
derfor: kør de automatiske værn, læs det nye modul linje for linje, og sammenlign de tal der står to steder.

| Trin | Hvad | Resultat |
| --- | --- | --- |
| 1 | `php -l` på alle PHP-filer, smoke-suiten, `projektstatus.py --check`, `samtalepunkter.py --check`, zip-diff | Alt grønt — ingen fejl at finde ad den vej |
| 2 | `TODO`/`FIXME`/placeholders i plugin- og site-PHP | Ingen (kun HTML-`placeholder`-attributter) |
| 3 | `includes/pinterest.php` læst linje for linje mod Pinterests egen dokumentation | **Tre fejl** (fund 1–3) |
| 4 | Tidsstempler: hvad skrives rækkerne med, og hvad måles vinduerne med | **Én fejl** (fund 4) |
| 5 | Testenes egne assertions: kan de fejle? | **Én fejl** (fund 5) |
| 6 | Roadmap-totaler mod `projektstatus.json` | **To stale totaler** + én stale site-linje |
| 7 | Biblioteksindekset mod `docs/library/` | 20+ ældre noter uden for indekset (gæld, ikke fejl — se §4) |

---

## 2. De fem fejl, og hvad de gjorde

### Fund 1 — et token i `wp-config.php` blev overskygget af det roterede

`SGAP_Pinterest::token()` læste det cachede token fra optionen `sgap_pinterest_access_token`, men
`SGAP_Settings::get()` læser en **konstant først**. Stod der `SGAP_PINTEREST_ACCESS_TOKEN` i `wp-config.php`,
blev der hentet data med konstanten — og hver fornyelse skrev et nyt token til en option som konstanten altid
vandt over. Resultatet ville være et site der så ud til at forny sig, men som blev ved med at bruge det gamle
token indtil Pinterest afviste det, uden at nogen kunne se hvorfor.

Nu tjekker `constant_locked()` **begge** nøgler (refresh *og* access), `token()` bruger konstanten som den er,
og `refresh()` afviser med konstantens navn i stedet for at skrive en værdi ingen læser.

### Fund 2 — et indklistret token blev kaldt «ugyldigt»

`status()['token_valid']` krævede `(int) get_option(EXPIRES) > time()`. Et token der er klistret ind i
hånden (et Pinterest *Trial*-token, som netop ikke har en udløbsdato i vores felt) har `EXPIRES = 0`, så
dashboardet sagde «token ugyldigt» om et token der virkede. Feltet blev rettet til at behandle **ukendt udløb
som brugbart** — og kun et *registreret* udløb i fortiden som udløbet.

### Fund 3 — et afslag på pin-listen stoppede hele målingen

`sync()` returnerede med det samme hvis `scan()` fejlede. Mangler app'en `boards:read`, svarer Pinterest 429,
eller er der et nedbrud, blev **også de pins der allerede var kortlagt** droppet for den kørsel — selv om
deres analytics sagtens kunne være læsbar. Nu rapporteres fejlen som `Pin listing: …` ved siden af resultatet,
og de kendte pins måles stadig. Kørslen er dermed delvist nyttig i stedet for helt tom.

### Fund 4 — to ure i samme vindue

`sgap_events.created_at` og `sgap_queue.published_at` skrives med `current_time('mysql')`, altså **sitets
lokal tid**. Vinduerne blev målt med `gmdate()`, altså **UTC**. I Danmark er forskellen 1–2 timer, men koden
ville også køre på et site i et andet tidsrum — op til ±14 timer — og en post publiceret sent på dagen kunne
falde uden for eller ind i vinduet et døgn forkert. Hele pluginnet bruger `wp_date()`; Pinterest-modulet gør nu
det samme, både for `start_date`/`end_date` og for `since` i `summary()` og `targets()`.

### Fund 5 — en test der ikke kunne fejle

`check('engagement: reach is not invented …', SGAP_Engagement::summary()==[] || true)` — den sidste klausul
gør påstanden sand uanset hvad. Oveni læste den den **forrige** tests kø-rækker som snapshot-kolonner, fordi
den falske `$wpdb` leverer samme resultat til enhver query; det gav tre PHP-advarsler pr. kørsel, som støj der
kunne skjule en rigtig advarsel. Nu ryddes rækkerne først, og påstanden er `summary() === []`.

---

## 3. Det nye værn: `tools/fejlscan.py`

Værnet kører på filerne og **på tværs af dem**, fordi fejlene ovenfor netop er dem ingen enkelt fil kan se.

| Kontrol | Fanger |
| --- | --- |
| `version` | `SGAP_VERSION`, headerens `Version:` og zip-navnet er tre forskellige tal; mere end én zip |
| `zip` | en fil i zip'en er ikke byte-identisk med kilden, eller en fil mangler/er overskud |
| `includes` | `require` peger på en fil der ikke findes, eller en fil i `includes/` kræves ikke af nogen |
| `bibliotek` | et **nyt** dokument i `docs/library/` står ikke i `README.md`, eller indekset peger på en fil der ikke findes |
| `hemmeligheder` | GitHub-, OpenAI/Anthropic-, Slack-, Google- og NVIDIA-tokens samt private nøgler i sporede tekstfiler |
| `markører` | `TODO`/`FIXME`/`XXX` i plugin- og site-PHP |
| `tomme tests` | `\|\| true`, `&& true` og `assert(true)` i testfiler |

**13 fixtures i `--selftest`** beviser begge retninger: hver fejlklasse bliver fanget, **og** en ren kasse er
grøn. Det sidste er ikke kosmetik — et værn der altid er rødt bliver ignoreret, og så er det værre end intet.
Fixtures dækker også de to grænsetilfælde der ellers ville gøre det ubrugeligt: en Printful-SKU
(`sk-steampunk-unisex-t-shirt`) er **ikke** en OpenAI-nøgle, og en ældre note uden for indekset er gæld, ikke
en fejl.

**Hvad det ikke dækker** (og derfor stadig skal måles i hånden):

- **Live-sitet.** Ingen af værnene ser steamgears.eu. Med `managed-wordpress-robots.php`'s parse-fejl på sitet
  er enhver live-påstand en tidligere måling. Det gælder også P1.5b, som er kode-færdig men aldrig har talt
  med Pinterest.
- **Eksterne API'er.** Smoke-suiten stubber HTTP, så et svar der ændrer form først ses ved en rigtig kørsel.
- **Tredjepartskode.** Tema, WooCommerce og de øvrige plugins er ikke læst igennem.

---

## 4. Regnskabet blev også rettet

To totaler i `plugins/steamgears-autopilot/ROADMAP.md` §5 passede ikke med `projektstatus.json`:

| Total | Stod | Er nu | Hvorfor |
| --- | --- | --- | --- |
| P0 | 5,25 d (4,0 + P0.7 1,0 + P0.7c 0,25) | **5,95 d** (4,70 + 1,0 + 0,25) | P0.2's kanal-loft og P0.4's cron-verifikation lagde 0,70 d til i regnskabet, men ikke i tabellen |
| P1 | 13,30 d (9,30 uden P1.5) | **15,20 d** (11,35 uden P1.5) | Rækkerne summer 14,35 d, og P1.5 blev tre moduler (P1.5a/b/c) i stedet for ét — 3,0 → 3,85 d |

Og i `docs/roadmap.md` stod §9-linjen stadig på **S2 7,55 = 23,8 d**, mens bånde-totalerne i §4 summer
**8,05 = 24,3 d** (S2.9 lagde 0,5 d til). Alle tre linjer følger nu regnskabet, med årsagen skrevet ind i
linjen, så tallet kan genberegnes af den næste.

Bibliotekets egen indledning lovede at «alt i `docs/library/` er linket herfra». Det er ikke sandt for de
20+ noter der er ældre end indekset; sætningen er nu præcis om hvad indekset dækker, og værnet kræver
indeksering af **nye** dokumenter (reglen i `AGENTS.md`) i stedet for at være rødt på gammel gæld.

---

## 5. Verifikation

| Kontrol | Resultat |
| --- | --- |
| `php -l` på ændrede PHP-filer | rent |
| `plugins/tests/smoke.php` | **770 PASS / 0 FAIL**, ingen PHP-advarsler |
| `tools/fejlscan.py --selftest` | 13 fixtures, alle fanget, ren kasse grøn |
| `tools/fejlscan.py` | ingen fund (version, zip, includes, bibliotek, tokens, markører, tests) |
| `bash tools/natlige-vaern.sh` | **24 af 24 grønne** |
| zip mod kilde | byte-identisk (`7.53.1`) |
| `projektstatus.py --check` / `samtalepunkter.py --check` | ingen drift |

**Ikke gjort:** intet er kørt mod live-sitet, og Pinterest-kaldet er stadig kun bevist med stubber —
`includes/pinterest.php` har aldrig talt med Pinterests API.
