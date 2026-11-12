# Affiliate-niche: plan for den første nicheside ved siden af steamgears

**Dato:** 12/11 2026 · **Beslutning:** ejeren følger anbefalingen om at holde steamgears som hovedsite og
starte **én** affiliate-nicheside med samme værktøjer (Command Center + Autopilot).

## 1. Kort svar

**Anbefaling: en dansk guide-side om kaffeudstyr til hjemmet** (kværne, vægte, pour over, espresso).
Siden tjener på provision fra danske kaffebutikker og varehuse. Det er ikke dropshipping: intet lager,
ingen kundeservice og ingen returvarer.

Tre grunde fra målinger, ikke fra mavefornemmelse:

1. **Der er efterspørgsel på købsord:** kaffevægt 1 000, bedste espressomaskine 720, pour over kaffe 720,
   bedste kaffekværn 260 og håndkværn kaffe 170 søgninger om måneden i Danmark. Dertil kommer en lang hale
   af modeller og metoder.
2. **Konkurrencen er svag på indholdet.** På side 1 for »kaffevægt« og »pour over kaffe« står maskinoversatte
   Reddit-tråde (r/Coffee), forum-indlæg og produktsider fra butikker. Der er kun én egentlig sammenlignings-
   guide (gastrogrej.dk). Det er netop den slags huller, en god guide kan tage.
3. **Købene er store, og køberne er entusiaster.** En espressomaskine eller kværn koster tusindvis af kroner,
   og kaffefolk køber tilbehør igen og igen. Kaffe var også runner-up i butiksresearchen 1/11
   ([`niche-research-2026-11-01.md`](niche-research-2026-11-01.md)). Her bruges den bare uden lager.

## 2. Målingen (OpenSEO / DataForSEO, Danmark, dansk, 12/11 2026)

| Klynge | Søgeord (måned) | Side 1 i Google |
| --- | --- | --- |
| **Kaffe** | kaffevægt 1 000 · bedste espressomaskine 720 · pour over kaffe 720 · bedste kaffekværn 260 · håndkværn kaffe 170 | Butikker, Reddit-oversættelser, forum, én guide-side → **svag indholdskonkurrence** |
| Hund | hundebur til bil 2 400 · hundesele 2 400 · bedste hundefoder 390 | PriceRunner #1, og **eksisterende affiliate-sider** (hundegrejet.dk, billige-hundebure.dk) ranker allerede |
| Have | højbed 3 600 · bedste robotplæneklipper 1 900 · sauna til haven 480 | Bolius, Plantorama, Stark og store kæder; stærkt sæsonbetonet |
| Gadgets | bedste robotstøvsuger 2 400 · bedste airfryer 1 300 | Store prisportaler og testmedier (ikke målt her, men kendt mønster) |
| Andet | padelsko 1 300 · fyldepen 1 000 · bedste hovedpude 720 · brevpapir 590 | Ikke undersøgt dybere |

**Forbehold:** DataForSEO's sværhedsgrad (KD) er 0-10 for næsten alle danske ord. Det skyldes for lidt data,
ikke let konkurrence, så KD er ikke brugt i vurderingen. »Competition: HIGH« gælder annoncer, ikke organisk
søgning. Konkurrencen er derfor læst direkte af Google-resultaterne for de fire kandidater. Hund er nummer
to: større volumen, men beviselig konkurrence fra andre affiliate-sider.

## 3. Hvad siden skal være

- **Navn og domæne:** ejerens valg. Kort, dansk og uden et mærkenavn (fx noget med »kaffe« + »guide«/»grej«).
- **Indhold i tre lag:**
  1. *Søjlesider*: »Bedste kaffekværn«, »Bedste kaffevægt«, »Bedste espressomaskine til hjemmet«,
     »Pour over: udstyr og guide«.
  2. *Sammenligninger og tests*: model mod model, prisklasser, »til begyndere«, »under 1 000 kr.«
  3. *Guides*: opskrifter, kværnindstillinger, rengøring og vand. De bygger troværdighed og interne links
     til søjlesiderne.
- **De første 20 sider:** 4 søjler, 8 sammenligninger og 8 guides. Søgeordene skal hentes fra Command
  Centers søgeordsideer og Google Suggest, som er gratis, før der bruges flere OpenSEO-credits (103 tilbage).
- **Kvalitet:** Google straffer tyndt AI-indhold. Command Center skriver udkast, og AI-redaktøren skal give
  mindst 7/10, men **et menneske skal læse hver søjleside** før publicering. Egne fotos af udstyr, der
  faktisk er prøvet, er den stærkeste fordel mod Reddit-oversættelserne.
- **Affiliate-mærkning:** hver side med links skal tydeligt sige »indeholder affiliate-links«
  (markedsføringsloven).

## 4. Indtjening — realistisk, ikke optimistisk

Modellen bygger på **antagelser, ikke målinger**: provision 5-8 % (typisk dansk niveau via netværk,
**ikke bekræftet** for de konkrete butikker), gennemsnitskøb 1 200 kr., 15 % af læserne klikker videre, og
3 % af klikkene køber.

| Besøg pr. måned | Klik videre | Køb | Provision (6 %) |
| --- | --- | --- | --- |
| 2 000 | 300 | 9 | ca. 650 kr. |
| 10 000 | 1 500 | 45 | ca. 3 200 kr. |
| 30 000 | 4 500 | 135 | ca. 9 700 kr. |

Nye domæner ranker typisk langsomt: **regn med 6-12 måneder** før trafikken er reel. Det er et langt spor.
Udgiften er lille (domæne, hosting, AI-kredit), og maskinen er allerede bygget.

## 5. Hvad der skal bygges eller ændres i værktøjerne

| Del | I dag | Skal til |
| --- | --- | --- |
| Command Center | Ét site (`TRACK_DOMAIN`, ét `WP_URL`) | **Flere projekter:** søgeord, crawl, flows og WordPress-forbindelse pr. site. Største opgave, ca. 1-1½ dag. Alternativt kører en ekstra installation på samme server (port + `.env`), så det kan begynde i dag uden kode |
| Steamgears Autopilot | Prompter og stil er steamgears/steampunk | Brand, tone og billedstil som indstilling, så pluginnet kan installeres på nichesiden. Ca. ½ dag |
| Affiliate-links | Klik spores med `/go/`-links til egne sider | `/go/`-links til partnerens sporingslink, så klik og provision kan følges pr. side |
| Billeder | CC laver billeder i steampunk-stil (`MEDIA_STYLE`) | Billedstil pr. projekt |

**Hurtigste start:** en ekstra Command Center-installation til nichesiden på samme server (8 TB, ingen
konflikt med fair use) og et nyt WordPress-site. Fler-projekt-understøttelsen bygges, når siden har vist
sig værd at holde.

## 6. Ejerens opgaver

1. **Domæne og hosting** (fx et nyt site hos Simply, eller samme konto).
2. **Affiliate-netværk:** opret konto hos Partner-Ads og Adtraction og søg om at komme med i
   kaffebutikkernes programmer. Hvilke butikker, der har et program og med hvilken provision, er **ikke
   bekræftet** her. Det ser du ved tilmeldingen.
3. **CPAGrip:** bruges ikke på nichesiden fra start. Content-lockers og incentive-tilbud passer ikke til en
   guide-side, der skal opbygge tillid i Google.
4. **Eget udstyr:** gerne én kværn og én vægt, der faktisk bruges og fotograferes. Det er det, der adskiller
   siden fra maskinoversatte Reddit-tråde.

## 7. Milepæle og beslutningspunkt

| Uge | Mål | Måles med |
| --- | --- | --- |
| 1-2 | Domæne, WordPress, CC-installation, affiliate-ansøgninger | Siden svarer, CC crawler den |
| 3-6 | 20 sider udgivet (4 søjler læst af et menneske) | Kvalitetstjekket i CC, publiceringsliste |
| 7-12 | Indeksering og de første placeringer | CC's søgeordshistorik (keyword_history) |
| **Dag 90** | **Beslutning:** fortsæt og skaler, eller skift til hund-nichen | Placeringer på plads 4-30 for mindst 10 ord, og de første klik på `/go/` |

## 8. Kilde

Søgevolumen og Google-resultater: OpenSEO MCP (`get_keyword_metrics`, 34 ord; `get_serp_results`, 4 ord, Danmark,
dansk, desktop), 12/11 2026, ca. 40 credits. Rådata ligger i sessionens log, ikke i repoet.
