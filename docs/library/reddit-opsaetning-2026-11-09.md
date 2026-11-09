# Reddit-koblingen: fra tomme felter til første link post

**Guiden dækker P5's sidste 0,1 d — den del der kræver din konto, ikke mere kode.** Den er skrevet efter at
Reddits vilkår blev læst 9/11 2026, og de har flyttet sig: Data API kræver nu **forhåndsgodkendelse**, og
kommerciel brug kræver en **skriftlig aftale**. Derfor har guiden to spor, og du skal vælge det første med
vilje:

| Spor | Virker i dag | Kræver | Passer til |
| --- | --- | --- | --- |
| **A — manuel post** | Ja, med det samme | intet fra Reddit | de første 2–4 uger, hvor du alligevel skal lære hvilke tavler der giver trafik |
| **B — API** | Kun efter Reddits ja | godkendt app + adgang | når linket beviseligt konverterer, og du vil automatisere 2 posts i døgnet |

Modulet er bygget så begge spor kan køre samtidig: radaren og køen er adskilt, og knappen *Queue on Reddit*
skriver kun til plugin'ets egen tabel indtil en cron-kørsel sender linket. Springer du API'et over, står
køen stille og intet går tabt.

---

## 1. De to slags adgang — og hvad hver af dem låser op

| Felt | Hvor det gemmes | Hvad det giver | Er det en hemmelighed |
| --- | --- | --- | --- |
| `reddit_client_id` | Settings → **Forum & Reddit** | appens identitet; sammen med secret giver det **læsning** (radaren) | nej |
| `reddit_client_secret` | Settings → **Keys** → Reddit | samme; uden den intet kald | **ja** |
| `reddit_username` | Settings → **Forum & Reddit** | den konto der poster | nej |
| `reddit_password` | Settings → **Keys** → Reddit | bruges **én gang** til at hente et refresh token | **ja** |
| `reddit_refresh_token` | Settings → **Keys** → Reddit | den varige skriveadgang; plugin'et roterer den selv | **ja** |

Rækkefølgen betyder noget: med kun app-id og secret står status som `read_only` — radaren virker, men intet
kan postes. Det er med vilje, så et halvt opsat site ikke kan sende et link ud i dit navn.

---

## 2. Spor A — manuel post (start her)

1. Skriv og publicér tråden på `/forum/`, så den har mindst 120 ord. Kortere tråde bliver ikke køet automatisk.
2. Åbn **Steamgears → Forum & Reddit**, find tråden i tabellen og kopiér dens permalink.
3. Tilføj sporingen i hånden — præcis disse tre parametre, ellers kan GA4 ikke skelne Reddit fra direkte trafik:

   ```
   ?utm_source=reddit&utm_medium=forum&utm_campaign=<tavle>
   ```

   Tavlen er `cosplay`, `workshop`, `buying` eller `builds` — samme værdi som plugin'et ville have sat.
4. Læs subredditets regler **før** du poster. Mange store subreddits har en fast ugentlig tråd til
   selvpromovering; et selvstændigt link uden for den bliver fjernet, og gentagne forsøg giver et ban.
5. Post som **link post**, ikke som tekst med link i. Det er den form Reddit og moderatorerne forventer af en
   henvisning til en diskussion.
6. Sæt et personligt loft: **2 posts i døgnet og mindst 90 minutter imellem**, også når du poster i hånden.
   Det er den samme grænse plugin'et håndhæver, og den findes fordi kontoalder og karma er den egentlige
   begrænsning, ikke API'et.

Fordelen ved spor A er ikke bare, at det er tilladt: du får målt om tråden konverterer, **før** du beder
Reddit om adgang. Konverterer den ikke, er en godkendt app spildt arbejde.

---

## 3. Spor B — app og felter, trin for trin

### 3.1 Ansøg om adgang først

Reddits *Responsible Builder Policy* (opdateret 5/10 2026) siger, at man skal **anmode om og få udtrykkelig
godkendelse** før man tilgår Reddit-data gennem API'et, og *Developer Terms* §4.1 siger, at adgang for eller
på vegne af en **virksomhed** eller som del af et produkt der tjenes penge på, kræver en **skriftlig aftale**
med Reddit. En butik der poster links for at få salg er netop det. Ansøgningsskemaet ligger på
`support.reddithelp.com` → *Submit a request* → Data API. Skriv ærligt hvad appen gør: læser offentlige
lister i fem subreddits og poster højst to link posts i døgnet til butikkens eget forum. Skriv ikke at det er
"moderationsværktøj" — det er en forkert angivelse og i sig selv grund til afvisning.

**Uden et ja: brug spor A.** Modulet fejler ikke af den grund; køen står stille, og radaren kan ikke læse.

### 3.2 Opret appen

1. Log ind på den konto der skal poste — ikke en ekstra konto. Reddit tillader ikke flere konti til samme
   formål, og en ung konto uden karma bliver filtreret uanset hvad API'et svarer.
2. Gå til `https://www.reddit.com/prefs/apps` → **create another app** (eller *create app*).
3. Udfyld:
   - **name**: `steamgears forum`
   - **type**: **script** — ikke *web app* og ikke *installed app*; kun script-appen kan hente et token med
     brugernavn og adgangskode.
   - **description**: tom eller én linje.
   - **about url**: `https://steamgears.eu/forum/`
   - **redirect uri**: `http://localhost:8080` — feltet er påkrævet, men bliver **ikke** brugt af en
     script-app. Reddit afviser en tom værdi.
4. Gem. Nu vises to værdier:
   - **app-id**: de ~14 tegn lige under *personal use script*.
   - **secret**: de ~27 tegn ved siden af *secret*.

### 3.3 Sæt felterne ind

| Værdi fra Reddit | Hvor i WordPress | Feltnavn |
| --- | --- | --- |
| app-id | Settings → **Forum & Reddit** | *Reddit app id* |
| secret | Settings → **Keys** → kortet **Reddit** | *Reddit app secret* |
| brugernavn (uden `u/`) | Settings → **Forum & Reddit** | *Reddit username* |
| adgangskode | Settings → **Keys** → kortet **Reddit** | *Reddit password* |

Et tomt secret-felt beholder den gemte værdi — du kan altså rette brugernavnet uden at indtaste secret igen.
Efter den første vellykkede skrivning skriver plugin'et selv `reddit_refresh_token` og bruger den herefter i
stedet for adgangskoden.

### 3.4 Læs status, ikke gæt

| Status | Betyder | Næste handling |
| --- | --- | --- |
| `off` | Reddit er slået fra i indstillingerne | slå *Reddit* til |
| `missing_token` | app-id eller secret mangler | udfyld begge |
| `read_only` | appen kan læse, men ingen bruger er sat | udfyld brugernavn + adgangskode |
| `refused` | sidste kald blev afvist; teksten står på siden | ret det fejlen peger på, og prøv igen |
| `ready` | alt er sat | kør en enkelt testpost |

`refused` forsvinder først ved næste **vellykkede** kald. Det er med vilje: en fejl der blev skjult af et
tilfældigt 200-svar er den dyreste slags.

### 3.5 Første post — i det mindste publikum du kan finde

1. Publicér en tråd med mindst 120 ord, og lad den gerne ligge et døgn, så eventuelle rettelser er på plads.
2. **Steamgears → Forum & Reddit** → *Refresh the radar*. Kommer der rækker, virker læseadgangen. Kommer der
   `refused`, er det app-id/secret — ikke brugeren.
3. Skriv subreddit i feltet ved tråden og tryk **Queue on Reddit**. Køen sender **højst én post pr.
   cron-kørsel (15 minutter)** og respekterer 2/døgn og 90 minutter.
4. Kontrollér i tabellen **Queue**: `sent` betyder at Reddit svarede med et id; `failed` betyder tre forsøg
   med 5 → 15 → 45 minutters mellemrum og en fejltekst du kan læse.
5. Se efter i tråden: der skal stå et *Discuss this on Reddit*-link, og Reddit-postens URL skal pege tilbage
   på tråden med `utm_campaign=<tavle>`.

Første gang bør gå til et lille, relevant subreddit — eller din egen profil, hvis kontoen har profil-posting
slået til. Et forkert første link i et stort subreddit koster mere end en uges forsinkelse.

---

## 4. Fejlkoder og hvad de faktisk betyder

| Svar | Sandsynlig årsag | Rettelse |
| --- | --- | --- |
| `Reddit refused the token (401): invalid_grant` | forkert brugernavn/adgangskode, eller appen er ikke type *script* | ret oplysningerne; kontrollér app-typen |
| `Reddit refused the token (401)` uden tekst | secret hører til en anden app | indsæt secret fra samme app som id'et |
| `401` på selve posten, derefter `403` | tokenet er udløbet eller tilbagekaldt | gem adgangskoden igen, så et nyt token hentes |
| `403 Forbidden` | subreddit afviser kontoen: alder, karma eller regler | post i et andet subreddit; læs reglerne |
| `SUBREDDIT_NOTALLOWED` | subreddittet findes ikke, eller er privat/lukket | kontrollér stavemåden |
| `RATELIMIT` / `429` | for mange kald; grænsen er 100 kald i minuttet pr. app | vent; plugin'et laver højst 5 kald pr. radar-opdatering |
| `you are doing that too much` | kontoen er for ny eller poster for ofte | stop i et døgn; hæv ikke loftet |
| `already submitted` | samme URL er postet før | slet kø-rækken; post et andet indlæg |
| intet sker, status `queued` | cron kører ikke (cachet site, `DISABLE_WP_CRON`) | cron-kickeren prikker hvert 15. minut; se *Troubleshooting* |

Et afslag kommer som **HTTP 200 med et `errors`-array**, ikke som en fejlkode. Det er derfor en post kan se
"grøn" ud i et naivt overvågningssystem og alligevel ikke eksistere.

---

## 5. Rotation, gendannelse og hvad du aldrig skal gøre

- **Refresh-tokenet roteres ved hver brug.** Plugin'et skriver det nye tilbage med det samme. Indsætter du et
  gammelt token manuelt, virker det ikke.
- **Har kontoen 2FA**, virker adgangskode-vejen typisk ikke. Brug en konto uden 2FA til appen, eller skaf et
  refresh token ad den vej Reddit godkender — og indsæt det under *Keys*.
- **Skift aldrig adgangskode uden at opdatere feltet**, ellers dør skriveadgangen ved næste token-forsøg.
- **Del aldrig secret, adgangskode eller token** — heller ikke i en chat, en supportmail eller et screenshot.
  Skal de videre, skal de indtastes direkte i wp-admin.
- **Slet ikke appen** for at "nulstille": et nyt app-id kræver nye felter overalt, og det løser sjældent det
  der var galt.

---

## 6. Reglerne modulet er bygget efter

| Regel | Hvad den betyder her |
| --- | --- |
| Godkendelse før adgang | API-vejen kræver Reddits ja; spor A kræver intet |
| Kommerciel brug kræver skriftlig aftale | en butik der markedsfører sig er kommerciel brug |
| Ingen spam eller automatiserede kommentarer | modulet kommenterer, stemmer og sender aldrig beskeder |
| Ikke identisk indhold på tværs af subreddits | hver tråd går til **ét** subreddit, og dublet-værnet stopper et andet forsøg |
| Ærlig User-Agent | `web:steamgears-forum:1.0 (forum on steamgears.eu)` |
| Kald skal kunne identificeres og begrænses | 1 post pr. kørsel, 2 i døgnet, radar cachet i en time |
| Slettet indhold skal slettes hos dig | plugin'et gemmer kun titel, URL og id — ikke Reddit-brødtekst |

---

## 7. Tjekliste

- [ ] valgt spor A eller B med vilje
- [ ] tråd publiceret med mindst 120 ord og en tavle
- [ ] tavlerne har `reddit_sub` (term meta), ellers bruges standard-subredditet
- [ ] ved spor B: app type **script**, redirect `http://localhost:8080`
- [ ] app-id + brugernavn under *Forum & Reddit*, secret + adgangskode under *Keys*
- [ ] status viser `read_only` eller `ready` — ikke `missing_token`
- [ ] radaren giver rækker (læseadgang bevist)
- [ ] første link post gik til et lille publikum, ikke et stort subreddit
- [ ] `sent` i kø-tabellen, og `utm_campaign` står i URL'en
- [ ] efter to posts: refresh-tokenet er stadig sat, og status er `ready`

**Det kan jeg ikke gøre herfra:** oprette appen, logge ind på Reddit, indtaste hemmelighederne eller se
resultatet i et subreddit. Ingen af delene må lægges i repoet, og ingen af dem kan køres fra en sandkasse uden
en browser. Guiden er skrevet så hvert trin kan kontrolleres i wp-admin uden at læse kode.

---

## 8. Kilder (læst 9/11 2026)

- Reddit Data API Wiki, opdateret 11/5 2026 — OAuth-krav, User-Agent-format, 100 QPM pr. app-id:
  `support.reddithelp.com/hc/en-us/articles/16160319875092`
- Responsible Builder Policy, opdateret 5/10 2026 — godkendelse før adgang, forbud mod spam og identisk
  indhold på tværs af subreddits: `support.reddithelp.com/hc/en-us/articles/42728983564564`
- Developer Terms §1.4 og §4.1 — Access Info, kommerciel brug kræver separat aftale:
  `redditinc.com/policies/developer-terms`
- OAuth2- og script-app-vejledning (app-typer, redirect uri, 14/27 tegns id og secret):
  `github.com/reddit-archive/reddit/wiki/oauth2` og `.../oauth2-quick-start-example`
- PRAW's authentication-side (samme fire felter, samme krav om redirect uri):
  `praw.readthedocs.io/en/latest/getting_started/authentication.html`

Kilderne er Reddits egne, men de ældre API-sider er markeret som *legacy* af Reddit selv. Vilkårene vinder
over tekniksiderne, og de kan ændre sig igen — derfor er spor A altid tilgængeligt.
