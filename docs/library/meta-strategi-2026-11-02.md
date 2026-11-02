# Meta-strategi for produkter og kategorier — 2/11 2026

Beslutning om hvad der skal skrives i hånden, og hvad der skal blive ved med at være
auto-genereret — plus en prioriteret plan for de meta-afvigelser, der blev målt i
[`titel-meta-audit-2026-11-02.md`](titel-meta-audit-2026-11-02.md).

Måltallet er reglen fra 1/11: **titel ≤ 62 tegn, meta 140–158 tegn**.

## 1. Beslutning

| Type | Antal | Beslutning | Hvorfor |
| --- | --- | --- | --- |
| Produkter | 39 | **Auto — men templaten rettes én gang** | 33 af 39 følger samme genererede sætning; kun 6 kræver egen tekst |
| Produktkategorier | 20 | **Hybrid** — auto er fint for 15, manuel meta på 5 | De 5 lange er ikke skrevet: de er kategoriens beskrivelse dumpet råt |
| Sider og indlæg | 49 | **Manuel** (som i dag) | Median 142 tegn: teksten er allerede skrevet, den mangler blot et par tegn |
| Indlægskategorier | 5 | **Manuel** | 5 små sider, ingen template at arve fra |
| Forfattersider | 2 | **Noindex, ikke meta** | Tynd arkivside; en meta ville pynte på en side, der ikke skal indekseres |

Kort sagt: **produkter og kategorier skal blive ved med at være automatiske — men templaten skal
være kortere end grænsen, så den ikke klipper midt i et ord.** Det er ikke 84 håndskrevne tekster;
det er to template-rettelser, 11 håndskrevne tekster og en polish-runde på 22 sider.

## 2. Hvad der er målt, ikke gættet

- **De 33 produktmetaer er genereret, ikke skrevet.** De følger mønsteret
  «`<produktnavn> — <kollektion> <type> from the Steamgears collection. Printed to order and shipped
  from our EU partners — matching tees, posters and accessories i…`» og **klippes hårdt ved 165 tegn
  midt i ordet** («accessories i»). Fire produkter er tjekket mod deres korttekst via REST: meta og
  korttekst er *forskellige* tekster, så metaen kommer fra en template og ikke fra produktets eget felt.
- **De 6 produktmetaer med samme tekst er produktets egen korttekst**, ordret:
  «Steamgears gear, checked before dispatch. Adjustable fit. Production 3–7 business days, EU 14-day
  returns.» (106 tegn). Her er meta = korttekst, bekræftet via REST på to produkter. Det er de seks
  produkter, der deler boilerplate — et duplikatproblem, ikke et længdeproblem.
- **De 5 lange kategorimetaer er kategoriens beskrivelse, afkortet med ét tegn.** For
  `airship-captain` (389), `beginner-steampunk` (408), `dark-clockwork` (390), `mechanical-explorer`
  (393) og `victorian-inventor` (369) matcher metaens start beskrivelsen ordret. Rank Math falder
  altså tilbage til beskrivelsen, når der ikke er skrevet en meta — og beskrivelsen er 370–409 tegn.
- **De øvrige 15 kategorier har en median på 141 tegn**, altså inden for grænsen. Det er den ønskede
  tilstand og kræver ingen handling.

To kørsler samme dag gav 46 og 44 på «meta under 140». Forskellen på to sider er ikke undersøgt; tallene
nedenfor er fra den gentagelige kørsel (`python3 seo_title_audit.py --by-type --dupes`), og planen skal
måles igen ved anvendelse i stedet for at stole på denne tabel.

## 3. Plan, i den rækkefølge den skal udføres

### P1 — 6 produkter med identisk meta (duplikat)

`copper-pilot-goggles`, `steampunk-aviator-goggles`, `steampunk-overbust-corset`,
`steampunk-top-hat-with-removable-goggles`, `time-traveler-top-hat`, `vintage-driver-goggles`.

Disse har alle kortteksten «Steamgears gear, checked before dispatch…» som meta. Google ser seks sider
med samme beskrivelse og viser typisk ingen af dem. **Handling:** skriv en korttekst pr. produkt på
140–158 tegn, der beskriver det konkrete produkt (materiale, pasform, hvad der følger med).
**Måling:** `--dupes` skal gå fra 1 delt tekst til 0.

### P2 — 5 kategorier hvor beskrivelsen dumpes

`airship-captain`, `beginner-steampunk`, `dark-clockwork`, `mechanical-explorer`, `victorian-inventor`.

**Handling:** skriv en manuel Rank Math-meta på 140–158 tegn til hver. Beskrivelsen bliver liggende
som brødtekst — den skal ikke forkortes, den skal bare ikke være meta.
**Måling:** `--by-type` skal vise 0 for `meta>158` under produktkategori.

### P3 — 22 sider og indlæg på 125–139 tegn

De er allerede håndskrevet og mangler typisk 6–20 tegn, ikke en omskrivning. **Handling:** forlæng
med ét konkret led (hvad læseren får, eller hvem det er til) til 140–158. Ingen nye sider, ingen
ændret vinkling. **Måling:** `meta<140` for `side/indlaeg` går fra 22 mod 0.

### P4 — produkttemplaten (33 produkter, én rettelse)

**Handling:** find templaten (sandsynligvis Rank Math → Titles & Meta → Products, eller den funktion i
temaet der bygger produktmetaen) og gør sætningen kortere end 158 tegn, så den slutter helt. Et
fungerende mål er at fjerne «— matching tees, posters and accessories…»-halen og i stedet slutte efter
«Printed to order and shipped from our EU partners.» — det er den samme information som kortteksten
allerede giver. **Måling:** `meta>158` under produkt går fra 32 mod 0, og ingen meta må ende midt i et ord.

Dette er P4 og ikke P1, fordi 164–165 tegn kun koster de sidste par tegn i SERP'en, mens P1 og P2 koster
hele visningen.

### P5 — titler (samme mønster, egen plan)

4 titler over 62 tegn og 20 under 30. Rettelserne for de fire ligger klar i
[`titelrettelser-2026-11-02.md`](titelrettelser-2026-11-02.md); de 20 korte er kategorier og
politik-sider, hvor titlen bør have et søgeord mere, fx `Privacy Policy` → `Privacy Policy And Cookies
| Steamgears`.

## 4. Hvad der ikke kan afgøres uden skriveadgang

- **Hvor templaten bor.** Om P4 er ét felt i Rank Math eller en funktion i temaet, afgøres i wp-admin.
  Uden adgang kan jeg kun bevise, at teksten er genereret og klippet ved 165 tegn — ikke hvor koden står.
- **Om klipningen ved 165 kommer fra Rank Math eller fra temaet.** 165 er ikke Rank Maths standardgrænse,
  så det skal bekræftes, før man retter den forkerte indstilling.
- **Om de 6 duplikatprodukter er kladder eller live.** De står i sitemappen, men tidligere er
  kladde-produkter (27, 28, 29, 75, 183, 765) noteret som ikke-linkbare; om de seks er de samme, skal
  tjekkes, før nogen skriver tekst til dem.

Alt ovenstående kræver `WP_USER` + `WP_APP_PASS` (eller `ROYAL_MCP_TOKEN`) sat som secrets. Indtil da er
denne fil planen — ikke udført arbejde.

## 5. Sådan kontrolleres det bagefter

```
python3 seo_title_audit.py --by-type --dupes
```

Forventet sluttilstand: 0 titler over 62, 0 meta uden for 140–158 på de typer, der er besluttet manuelle,
0 delte meta-tekster og ingen meta, der ender midt i et ord.
