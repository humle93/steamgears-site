# Forum og Reddit som trafikaccelerator — bygget som P5 i Autopilot (7.66.0)

**Et forum uden distribution er en tom side, og et Reddit-link uden en landingsside er et bounce.** Derfor er
dette ét modul med tre led, ikke tre features: en tråd pr. spørgsmål (indekserbar, citerbar), en link post til
et emne-relevant subreddit (lånt publikum), og en radar der finder de tråde hvor nogen allerede spørger om noget
vi sælger (så ejeren kan svare som menneske). Alle tre kan måles: trådens side, `utm_campaign=<tavle>` i GA4, og
`/go/<produkt-id>/` som sidste klik før butikken.

Målt 9/11 2026 i repoet: **1 277 PASS / 0 FAIL** i smoke-suiten, `php -l` rent på alle 67 PHP-filer, og zip'en
`plugins/steamgears-autopilot-v7.66.0.zip` er byte-identisk med kilden (se `tools/fejlscan.py`). **Intet er kørt
mod en rigtig WordPress eller Reddit endnu** — det er den ærlige grænse, og den står i §7.

---

## 1. Hvad der blev bygget

| Fil | Indhold |
| --- | --- |
| `plugins/steamgears-autopilot/includes/forum.php` | `SGAP_Forum`: CPT `sgap_topic`, taksonomi `sgap_board`, `[steamgears_forum]`, trådside, nyt-emne-formular med nonce/honeypot/rate-limit, kommentar-svar, moderation, `DiscussionForumPosting`, produkttips, admin-side, to REST-ruter |
| `plugins/steamgears-autopilot/includes/reddit.php` | `SGAP_Reddit`: OAuth (tre grants), token-cache, `/api/submit` som link post, kø i egen tabel med loft/interval/backoff, radar over `hot`, status, tre REST-ruter |
| `plugins/steamgears-autopilot/assets/forum.css` | Frontend-styling, kun enqueuet på en tråd eller en side med shortcoden |
| `includes/core.php` | Tabel `sgap_reddit_posts`, 17 nye settings, ny sektion *Forum & Reddit* med hjælpetekster |
| `includes/setup.php` | Fane *Forum & Reddit* + labels; de tre Reddit-secrets ligger på *Keys* |
| `includes/keys.php` | Ét Reddit-kort, så alle secrets stadig findes på én side |
| `includes/admin-menu.php`, `uninstall.php`, `steamgears-autopilot.php` | Menupunkt i Publish, fuld oprydning, requires/init og version 7.66.0 |
| `plugins/tests/forum.php` + `plugins/tests/smoke.php` | Stubs og assertions for forum, kø, token, radar, REST og admin-siden |

### Hvorfor det er ét modul og ikke et nyt plugin

`tools/fejlscan.py` kræver **præcis én zip** i `plugins/` og at hver fil i den er byte-identisk med kilden.
Et selvstændigt forum-plugin ville være en anden zip, en anden opdateringsvej og et andet sted at lede efter
en nøgle. Det ligger derfor i Autopilot, hvor opdatering, nøgler, backup og CI allerede virker.

---

## 2. Reddit-fakta der styrer koden (læst 9/11 2026)

| Fakta | Konsekvens i koden |
| --- | --- |
| Token-endpointet er `https://www.reddit.com/api/v1/access_token` med **HTTP Basic** (app-id : secret) og appens egen User-Agent | `token()` sender Basic-auth og `SGAP_Reddit::UA`; intet token står i en URL |
| `client_credentials` læser offentlige lister **uden** en bruger | Radaren virker før publicering er forbundet; admin-siden siger `read_only` i stedet for at fejle ved første submit |
| En **script-app** svarer på `grant_type=password` og returnerer et `refresh_token`, der **roteres ved hver brug** | Det nye refresh token skrives tilbage med det samme, ellers er adgangen væk |
| `POST /api/submit` tager `sr`, `kind=link`, `title`, `url`, `api_type=json` | Kun link posts; `submit()` nægter en URL der ikke peger på sitets eget host |
| Et afslag kommer som **HTTP 200 med `json.errors`**, ikke som en fejlkode | `submit()` læser `json.errors` og gør den første besked til fejlteksten |
| Offentlige lister er `GET /r/<sub>/hot?limit=25&raw_json=1`; `data.children[]`, `score`, `num_comments` | Radaren rangerer `score + 2 × kommentarer`: et spørgsmål med svar er mere værd end et billede |
| **Data API kræver forhåndsgodkendelse** (Responsible Builder Policy, 5/10 2026), og kommerciel brug kræver en skriftlig aftale (Developer Terms §4.1) | Modulet er bygget så den manuelle vej virker uden API: køen står stille indtil en cron sender, og radaren er valgfri. Guiden i [`reddit-opsaetning-2026-11-09.md`](reddit-opsaetning-2026-11-09.md) har de to spor |
| Reddits egne regler og hvert subreddits regler forbyder selvpromovering i andres tråde | Modulet **kommenterer aldrig**; radaren læser og foreslår, ejeren svarer |

**Det vigtigste designvalg:** ingen automatiske kommentarer, ingen automatisk crosspost til flere subreddits, og
ingen mere end **2 link posts i døgnet** med **mindst 90 minutter** imellem. En shop-konto der poster hurtigt
bliver banned, og så er hele kanalen væk — også de links der virkede.

---

## 3. Trafikmekanikken, trin for trin

1. **Tråden** publiceres som `sgap_topic` med titel, brødtekst, tavle og kommentarer. Den får `DiscussionForumPosting`
   med forfatter, datoer, svarantal og de ti ældste godkendte svar. Det er den side søgemaskiner og AI-svar kan citere.
2. **Delingen** sker automatisk når tråden er ≥ 120 ord (indstilling), tavlen har en subreddit-mapping (term meta
   `reddit_sub`) eller der er sat en standard, og dagens loft ikke er nået. Ellers står den på *Queue on Reddit* på
   admin-siden.
3. **Linket** er trådens permalink med `utm_source=reddit`, `utm_medium=forum`, `utm_campaign=<tavle>`. GA4 kan
   dermed svare på hvilken tavle der gav besøget — ikke bare at der kom besøg.
4. **Landingen** er tråden, ikke forsiden: øverst står tavle, svarantal og et «Discuss this on Reddit»-link når
   tråden er delt, og nederst op til tre produkter gennem `/go/<id>/`, så klikket også tælles af butikken.
5. **Radaren** læser `hot` i op til fem subreddits, matcher nøgleord i titel og brødtekst, springer stickied og
   allerede sete tråde over, og gemmer de 12 bedste med deres Reddit-URL. Efter 400 sete id'er ruller listen.

### Køen (`sgap_reddit_posts`)

| Felt | Betydning |
| --- | --- |
| `topic_id`, `subreddit`, `title`, `url` | Hvad der skal postes, og hvorhen |
| `reddit_id`, `reddit_url` | Reddits eget id og permalink efter succes |
| `status` | `queued` → `sent` / `failed` |
| `attempts`, `scheduled_at`, `error` | Backoff **5 → 15 → 45 min**, tredje fejl er terminal |
| `sent_at`, `created_at` | Dagligt loft og minimumsinterval regnes af de rækker der faktisk gik ud |

Et dublet-værn på `topic_id` betyder at samme tråd ikke kan ligge i køen to gange, uanset hvem der trykkede.

---

## 4. Sikkerhed og moderation

- **Læsning er offentlig; skrivning kræver login.** Gæster ser en login-boks, ikke formular-felter.
- Nyt emne: `check_admin_referer()` på `sgap_forum_topic`, et **honeypot-felt** der skal være tomt, titel 8–140 tegn,
  mindst 40 ord, og **3 tråde pr. bruger pr. time** gennem en transient.
- Alt fra en bruger uden `edit_others_posts` gemmes som **`pending`** — spam koster et klik at afvise, ikke en side at rydde.
- Alle felter saniteres (`sanitize_text_field`, `wp_kses_post`), og alt output escapes (`esc_html`, `esc_attr`, `esc_url`).
- Reddit-secrets ligger i `SECRET_KEYS` (autoload **fra**), vises aldrig igen, og et tomt felt beholder den gemte værdi.
  `reddit_refresh_token` kan ikke være en `wp-config.php`-konstant, fordi serveren skal kunne rotere den.
- `uninstall.php` dropper den nye tabel, sletter de tre post-meta-nøgler, `sgap_reddit_tick` og alle `sgap_%`-options.

---

## 5. Indstillinger (Settings → Forum & Reddit)

| Nøgle | Standard | Hvad den gør |
| --- | --- | --- |
| `forum_enabled` | ja | Åbner/lukker forummet; eksisterende tråde bliver læsbare |
| `forum_slug` | `forum` | Adressen `/forum/`; kræver et besøg på *Permalinks* efter en ændring |
| `forum_topics_per_page` | 20 | Rækker pr. side i indekset |
| `forum_intro` | tom | Valgfri introduktion over listen |
| `reddit_enabled` | ja | Hovedafbryder for kø og radar |
| `reddit_auto_share` | ja | Deler automatisk en lang nok, publiceret tråd |
| `reddit_default_sub` | `steampunk` | Bruges når tavlen ikke har en mapping |
| `reddit_radar_subs` | `steampunk,cosplay` | Op til fem subreddits radaren læser |
| `reddit_keywords` | `steampunk,cosplay,goggles,top hat,brass` | **Komma** adskiller nøgleord, så `top hat` forbliver en frase |
| `reddit_daily_cap` | 2 | Loft pr. dag, 1–10 |
| `reddit_min_words` | 120 | Kortere tråde deles ikke automatisk |
| `reddit_min_interval` | 90 | Minutter mellem to posts |
| `reddit_client_id` / `reddit_username` | tom | Ikke-secrets; app-id og konto |
| `reddit_client_secret`, `reddit_password`, `reddit_refresh_token` | tom | Secrets på *Keys*-fanen |

---

## 6. Hvad der blev målt i dette pas

| Kontrol | Resultat |
| --- | --- |
| `php -l` på alle plugin-filer (PHP 8.5.8) | 67 filer, 0 syntaksfejl |
| `php plugins/tests/smoke.php` | **1 277 PASS / 0 FAIL**; forum-testen dækker registrering, formularværn, moderation, JSON-LD, OAuth-grants, submit-afslag, kø, backoff, radar, status, REST og admin-siden |
| `tools/fejlscan.py` | version, zip og includes hænger sammen; se næste afsnit |
| Zip | `steamgears-autopilot-v7.66.0.zip`, én rodmappe, byte-identisk med kilden |
| Live | **ikke kørt.** Sandkassen har ingen WordPress, ingen MySQL og intet Reddit-token; plugin-CI kører integrationen på WordPress 6.4/7.1/latest |

Det sidste punkt er ikke en fodnote: hele Reddit-klienten er testet gennem `SGAP_Reddit::$transport`, så
**ingen HTTP-kald er bevist mod Reddit**. Endpoint, grants og fejlformat kommer fra dokumentationen og er kodet
efter den, men det første rigtige kald er ejerens.

---

## 7. Hvad ejeren skal gøre (og hvad der stadig er åbent)

1. **Installer 7.66.0**: *Plugins → Tilføj nyt → Upload* med `plugins/steamgears-autopilot-v7.66.0.zip`.
   Repoet er privat, så selvopdateringen kan ikke hente zip'en anonymt.
2. **Vælg spor**: post linket i hånden de første uger (virker i dag), eller ansøg om Data API-adgang og opret
   derefter en Reddit script-app på `reddit.com/prefs/apps` (type *script*, redirect uri `http://localhost:8080`).
   Gem app-id og secret under *Settings → Keys → Reddit*, og brugernavn under *Forum & Reddit*.
   Adgangskoden bruges **kun** til at hente det første refresh token; derefter roterer plugin'et det selv.
   Trin, feltmapping og fejlkoder står i [`reddit-opsaetning-2026-11-09.md`](reddit-opsaetning-2026-11-09.md).
3. **Opret forummet som side**: indsæt `[steamgears_forum]` på en side og læg den i menuen. Selve arkivet
   `/forum/` virker også uden shortcoden.
4. **Map tavler til subreddits**: term meta `reddit_sub` på `sgap_board`-termen. Uden mapping bruges standarden,
   og så lander alle tråde samme sted — det er sjældent rigtigt.
5. **Svar i radaren som dig selv.** Modulet poster kun linket; det skriver ingen kommentarer. Læs subredditets
   regler før det første link — flere store subreddits tillader kun selvpromovering i ugentlige tråde.

**Åbne poster:** første rigtige link post (kræver 1–2), WRITE-tokenets rotation målt over to kørsler, og
WP-Crons forsinkelse på et cachet site (den eksisterende cron-kicker dækker, men er ikke målt for denne kø).

---

## 8. Fælder der er betalt for i dette pas

| Fælde | Lærdom |
| --- | --- |
| Nøgleord splittet på mellemrum | `top hat` blev til `top` + `hat` og matchede alle hattebilleder; nøgleord skal deles på **komma** |
| Token-cache før konfigurationscheck | Et cachet token fik `radar()` til at køre videre efter at app-secret var fjernet; ryd cachen når credentials ændrer sig — testen gør det nu eksplicit |
| Reddit-fejl i `json.errors` | Et afslag har HTTP 200; en klient der kun kigger på statuskoden tror posten gik igennem |
| `wp_delete_nav_menu`-stub i niche-testen | To testfiler kan definere samme stub; den første vinder, så test-globals skal hedde det samme (`termmeta`, `existing_terms`) |
| JSON-LD talt som inline script | `echo '<script …'` blev fanget af P3.8-værnet; `printf()` på en formatstreng tæller som JSON-LD, ikke som kode |
