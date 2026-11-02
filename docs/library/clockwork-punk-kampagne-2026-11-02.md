# Clockwork Punk — kampagne 3.–14. november 2026

En 12-dages serie hen over de 12 clockwork-produkter butikken allerede har, fordelt på tre kanaler og tre
vinkler. Kampagnen er **kø-payloaden** [`clockwork-punk-koe-2026-11-02.json`](clockwork-punk-koe-2026-11-02.json),
ikke dette dokument: captions, links, billeder og tidspunkter findes kun ét sted, så de ikke kan drive fra
hinanden. Her står hvorfor, hvordan og hvad der stadig mangler.

Målt mod live-katalog 2/11 2026 (`wc/store/v1/products`, 56 produkter) og læst i pluginnet
`plugins/steamgears-autopilot/` (7.22.0).

---

## 1. Koncept

**Clockwork punk** er den gren af steampunk hvor *mekanikken er ornamentet*: tandhjul som blomster, escapement
som ansigt, messing som materiale man kan se alderen på. Det er ikke dieselpunk (rust, industri, krig) og ikke
dark academia (bøger, botanik, melankoli) — det er **præcision som æstetik**. Butikken har allerede linjen:
seks hoodies, fire tees, to art prints, et phone case, to lommeure og et charm-armbånd.

Kampagnens påstand, som al copy tjener: *ting der virker, er smukkere end ting der bare er gamle.* Derfor er
hver caption skrevet som en lille iagttagelse af mekanikken — ikke som "køb nu".

| Led | Valg | Grundlag |
| --- | --- | --- |
| Periode | 3.–14/11 2026, **1 post pr. dag** | `daily_cap` er 8, men 1/dag holder vinkeltesten ren og undgår at kvalitetsgaten ser sine egne captions som næsten-dubletter |
| Kanaler | Pinterest 6, Instagram 3, Facebook 3 | Pinterest er den kanal der faktisk leverer visninger på tekstfri wall art; IG bærer tøj; FB bærer de dyre smykker |
| Vinkler | 3 × 4 poster | Læringsmatricen i pluginnet sammenligner `angle` — 12 poster delt 4/4/4 er den mindste serie der kan afgøre noget |
| Prisklasser | $21,90 · $24,95–29,95 · $49,95 · $69 · $89 · $149 | Trappen fra "impulskøb til væggen" til "gave man overvejer" |

**Vinklerne** (værdierne er pluginets frie `angle`-felt, 20 tegn):

- `angle_a` — **Atmosfære.** Hvad motivet gør ved rummet eller personen. Til den der allerede kan lide stilen.
- `angle_b` — **Lejlighed og gave.** Hvem man giver den til, og hvorfor den løser et problem. Bærer pris og
  leveringstid.
- `angle_c` — **Håndværk og detalje.** Mekanikken, materialet, det man først ser tredje gang. Til den kritiske.

## 2. Produkterne

Alle tolv er verificeret i kataloget med pris og permalink; ingen er opfundet, og ingen er på listen over
produkter uden størrelsesvalg.

| Dag | Produkt | Type | Pris | Kanal | Vinkel |
| --- | --- | --- | --- | --- | --- |
| 1 | Clockwork Figurehead | Poster 30×40/50×70 | $21,90 | Pinterest | a |
| 2 | Clockwork Tea Robots | Tee, unisex | $26,95 | Instagram | c |
| 3 | Clockwork Rose — Herbarium | Art print | $29,95 | Pinterest | b |
| 4 | The Aeronaut | Brass skeleton pocket watch | $149,00 | Facebook | b |
| 5 | Brass Luna Moth — Herbarium | Art print | $29,95 | Pinterest | a |
| 6 | Clockwork Owl | Hoodie, unisex | $49,95 | Instagram | a |
| 7 | Clockwork Rose — Herbarium | Phone case | $24,95 | Pinterest | c |
| 8 | Ink and Steam | Clockwork charm bracelet | $69,00 | Facebook | c |
| 9 | The Clockwork Seer | Tarot tee, unisex | $26,95 | Pinterest | b |
| 10 | Clockwork Heart | Gothic romance hoodie | $49,95 | Instagram | b |
| 11 | Clockwork Octopus | Curiosity hoodie | $49,95 | Pinterest | c |
| 12 | Clockwork Seer | Brass pocket watch pendant | $89,00 | Facebook | a |

To produkter deler motiv (Clockwork Rose findes som print og som phone case) — de ligger med fem dages
mellemrum og forskellig vinkel, så de ikke konkurrerer i samme feed.

## 3. Kalender og regler

| Regel | Værdi | Hvorfor |
| --- | --- | --- |
| Klokkeslæt | 12:15 og 18:30 | Uden for stille-timerne 23–07 (`sgap_quiet_start`/`end`), og inden for dansk aften |
| Frekvens | 1 pr. dag, ingen huller | 12 dage i træk giver læringsmatricen et sammenligneligt grundlag |
| Caption-længde | 40–60 ord, maks. 500 tegn | Pinterest tillader 500; alt over klippes i feed'et |
| Overlap | højeste målte ord-overlap i serien: **0,23** | Gaten blokerer ved 0,85 (`SGAP_Quality::SIMILARITY`) |
| Hashtags | 5 pr. post, engelsk | Site og boards er en-US |
| Links | altid produktsiden via `/go/<id>/` | Klik og ordrer bindes til kø-posten gennem redirectet |

## 4. Sådan lægges den i køen

```bash
# 1) Tørkørsel — validerer mod pluginets egne regler, sender intet
python3 tools/load_campaign.py docs/library/clockwork-punk-koe-2026-11-02.json

# 2) Indlæs (kræver site-credential; tokenet ligger uden for repoet)
python3 tools/mcp_oauth.py --status
python3 tools/load_campaign.py docs/library/clockwork-punk-koe-2026-11-02.json --post

# 3) Værnene i indlæseren
python3 tools/load_campaign.py --selftest      # 13 fixtures
```

`--post` rammer `POST /wp-json/sgap/v1/queue`, som er den eneste vej ind i køen, og som selv kører
`SGAP_Quality` og fingerprint-tjekket. Et 409 betyder at posten allerede ligger der — indlæseren springer den
over i stedet for at fejle, så kommandoen kan køres igen uden at lave dubletter.

## 5. Måling

| Niveau | Kilde | Hvad den svarer på |
| --- | --- | --- |
| Pr. post | `/go/<id>/` → `sgap_cro` + `sgap_events` | klik og ordrer pr. post |
| Pr. vinkel | `GET /stats?days=30` → learning-tabellen | hvilken af a/b/c der bærer serien |
| Pr. kanal | samme | om Pinterest stadig er kanalen der viser noget |
| Kampagne | **ikke mulig i dag** | se nedenfor |

**Fund:** `SGAP_UTM::build()` i `includes/core.php:464` hardcoder
`utm_campaign = steamgears_premium_evergreen`. Hver post i hele butikken bærer altså samme kampagnenavn i GA4,
så en navngivet kampagne kan ikke skilles ud i analytics — kun i pluginets egen kø-statistik. Rettelsen er
lille (en `campaign`-parameter med nuværende værdi som standard), men den kræver plugin-release, version-bump
og genopbygget zip, så den er **ikke** gjort her. Indtil da er kampagnens sandhed pluginets `/stats`, ikke GA4.

## 6. Blokeringer

| Blokering | Konsekvens | Ejers handling |
| --- | --- | --- |
| Intet site-credential | `--post` kan ikke køre; intet ligger i køen endnu | ét klik på OAuth-linket fra `python3 tools/mcp_oauth.py --start` og koden indsat i terminalen |
| Ingen `PUBLER_API_KEY` | køen kan fyldes, men intet **udgives** automatisk | nøgle i WP-admin → Autopilot → Settings, eller RSS-tilstand |
| Ét Pinterest-board pr. publicering | 5 pins peger på `steampunk-posters-prints`, 1 på `steampunk-complete-looks` | boardet sættes i indstillingerne; den ene pin flyttes manuelt bagefter |
| Mockups 0 af 5 timer | hvert produkt har 1–2 billeder, så pins er produktbilleder, ikke 2:3-mockups | mockup-briefen, når den køres |

## 7. Status

| Del | Status |
| --- | --- |
| Koncept og vinkler | færdig |
| Produktvalg mod live-katalog | færdig (12/12 verificeret) |
| Kalender og regler | færdig |
| 12 captions (EN) | færdig, højeste overlap 0,23 |
| Kø-payload | færdig |
| Indlæser med værn | færdig (13 fixtures, tørkørsel grøn) |
| Indlæsning i køen | **blokeret** — intet site-credential |
| Udgivelse | **blokeret** — ingen Publer-nøgle |

8 af 10 dele = **80 %**. De to sidste kræver ejerens klik og en nøgle, og de tæller ikke som fremgang før de
er kørt.
