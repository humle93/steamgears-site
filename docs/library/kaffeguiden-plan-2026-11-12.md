# Kaffeguiden: domæne, søgeordskort og de første 20 sider

**Dato:** 12/11 2026 · **Bygger på:** [`affiliate-niche-plan-2026-11-12.md`](affiliate-niche-plan-2026-11-12.md) ·
**Første søjleartikel (udkast):** [`kaffeguiden-artikel-kaffekvaern-2026-11-12.md`](kaffeguiden-artikel-kaffekvaern-2026-11-12.md)

## 1. Domæne

Tjekket 12/11 med et DNS-opslag (navneservere) fra steamgears.eu. Et domæne uden navneservere er **sandsynligvis
ledigt**. Det er ikke det samme som tjekket hos DK Hostmaster, så slå det op på punktum.dk, før du køber.

| Optaget (har navneservere) | Sandsynligt ledigt (ingen navneservere) |
| --- | --- |
| kaffeguiden.dk, kaffegrej.dk, hjemmebarista.dk, kaffeudstyr.dk, kaffekvaern.dk, bedstekaffe.dk, kaffehjoernet.dk | **brygguiden.dk**, kaffenoerd.dk, kaffetest.dk, barista-hjemme.dk, brygkaffe.dk |

**Anbefaling: brygguiden.dk.** Navnet er kort og uden æ/ø/å, og det passer til hele emnet (filter, espresso, stempelkande,
cold brew) uden at låse siden til én produkttype. Nummer to er **kaffetest.dk**. Det matcher »bedst i test«-søgningerne, men
lover tests, som siden så skal kunne leve op til. »Kaffeguiden« kan stadig være sidens *navn* i logo og titel.

**Hvor domænet købes:** hos **Simply.com**, hvor steamgears.eu allerede ligger (målt 12/11: navneservere ns1-3.simply.com, mail mx.simply.com, server linux161.unoeuro.com med wp-cli). Så ligger domæne, DNS og hosting på samme konto, og `tools/kaffeguiden_setup.sh` kan køres over SSH som på steamgears. Om det nuværende abonnement har plads til et site mere, eller om det kræver et nyt webhotel, er **ikke tjekket**. Det står i kontrolpanelet.

## 2. Søgeordskort (OpenSEO / DataForSEO, Danmark, dansk, 12/11 2026)

Tallene er søgninger pr. måned. KD er udeladt, fordi den er 0-6 for næsten alle danske ord (for lidt data, se planen §2).
»Competition HIGH« handler om annoncer. Intent er DataForSEO's gæt.

### 2.1 Kværne (søjle 1, den største)

| Søgeord | Volumen | Side |
| --- | --- | --- |
| kaffekværn | 8 100 | Søjle 1 |
| kaffekværn bedst i test | 1 000 | Søjle 1 (samme side, »bedst i test« i H2) |
| bedste kaffekværn · kaffekværn test · elektrisk kaffekværn bedst i test | 260 · 170 · 90 | Søjle 1 |
| manuel kaffekværn / kaffekværn manuel · hånd kaffekværn · håndkværn kaffe · manuel kaffekværn test | 880 · 110 · 170 · 40 | Sammenligning 1 |
| kaffekværn elektrisk | 390 | Søjle 1 (afsnit) |
| kaffekværn espresso · bedste espressokværn · bedste kaffekværn espresso | 320 · 70 · 50 | Sammenligning 2 |
| bedste kaffekværn til filterkaffe · kaffekværn til filterkaffe | 90 · 40 | Sammenligning 3 |
| kaffekværn til stempelkande | 50 | Guide 3 (stempelkande) |
| wilfa kaffekværn · bodum kaffekværn · sage kaffekværn · delonghi kaffekværn | 880 · 590 · 260 · 210 | Sammenligning 4 (mærker) |
| fellow opus · eureka · comandante | 110 · 110 · 50 | Senere: én anmeldelse pr. model, når den er prøvet |
| billig kaffekværn · lille kaffekværn · kaffekværn tilbud | 70 · 110 · 170 | Søjle 1 (afsnit »billig«) |
| kaffekværn med indbygget vægt · kaffekværn rens | 40 · 30 | Guide 7 (rengøring) |

### 2.2 Espresso (søjle 2)

| Søgeord | Volumen | Side |
| --- | --- | --- |
| espressomaskine | 27 100 | Søjle 2. Hårdt ord (butikker og prisportaler), men det er klyngens hoved |
| sage barista express | 6 600 | Senere: anmeldelse, når maskinen er prøvet |
| espressomaskine bedst i test · bedste espressomaskine · bedste espressomaskine til hjemmet | 1 300 · 720 · 90 | Søjle 2 |
| manuel espressomaskine · fuldautomatisk espressomaskine test | 210 · 90 | Sammenligning 5 |
| mælkeskummer | 8 100 | Sammenligning 6 |
| latte art | 1 000 | Guide 5 |
| afkalkning af espressomaskine | 480 | Guide 6 |
| hvordan laver man espresso · espresso opskrift | 50 · 40 | Guide 4 |

### 2.3 Filter og manuel brygning (søjle 3)

| Søgeord | Volumen | Side |
| --- | --- | --- |
| moccamaster | 12 100 | Sammenligning 7 (filterkaffemaskiner). Stort mærkeord, hovedsageligt navigation til butikker |
| kaffemaskine bedst i test · bedste filterkaffemaskine · filterkaffe | 1 300 · 90 · 720 | Sammenligning 7 |
| pour over kaffe · hario v60 · chemex | 720 · 880 · 480 | Søjle 3 |
| aeropress | 3 600 | Guide 2 |
| stempelkande · kaffe til stempelkande | 3 600 · 590 | Guide 3 |
| mokkakande | 390 | Guide 8 |
| cold brew | 880 | Guide 1 |
| kaffefilter | 1 000 | Søjle 3 (afsnit) |

### 2.4 Tilbehør og bønner (søjle 4)

| Søgeord | Volumen | Side |
| --- | --- | --- |
| kaffevægt | 1 000 | Søjle 4 |
| kaffebønner · bedste kaffebønner · friskristet kaffe | 4 400 · 390 · 210 | Sammenligning 8 |
| kaffe abonnement | 260 | Sammenligning 8 (afsnit). Høj CPC (2,57) = butikkerne betaler for kunderne |
| kapselmaskine | 720 | Ikke i de første 20. Passer dårligt til en side om friskmalet kaffe |
| kaffe gave · gave til kaffeelsker | 90 · 30 | Sæsonside før jul (efter de første 20) |

**Ikke målt** (sparet for credits): »svanehalskedel«, »kværngrad«, »malingsgrad kaffe«, »moka kande« (stavemåden med mellemrum)
og »rens kaffemaskine« gav intet tal. De bruges som underemner, ikke som egne sider. Command Centers Google Suggest
(gratis) kan finde flere varianter, når projektet er oprettet.

## 3. Sidestruktur

```
/                         Forside: »Find det rigtige kaffegrej«, links til de 4 søjler
/kaffekvaern/             Søjle 1  ← sammenligning 1-4, guide 3, 7
/espressomaskine/         Søjle 2  ← sammenligning 5-6, guide 4-6
/pour-over/               Søjle 3  ← sammenligning 7, guide 1-3, 8
/kaffevaegt/              Søjle 4  ← sammenligning 8
/guides/<emne>/           De 8 guides
/om/  /saadan-tester-vi/  /affiliate/   Troværdighed: hvem, hvordan, hvordan siden tjener penge
```

Hver guide linker op til sin søjle, og hver søjle linker ned til alle sine sider. Ingen side står alene. Siderne
»Sådan tester vi« og »Affiliate« skal være der fra dag ét. Google lægger vægt på dem for købsguides, og
markedsføringsloven kræver mærkningen.

## 4. De første 20 sider

| # | Type | Titel (arbejdstitel) | Hovedsøgeord (volumen) | Kræver udstyr? |
| --- | --- | --- | --- | --- |
| 1 | Søjle | Kaffekværn: sådan vælger du den rigtige (bedst i test 2026) | kaffekværn (8 100) | Nej til udkastet. Ja til »vores valg« |
| 2 | Søjle | Bedste espressomaskine til hjemmet | espressomaskine bedst i test (1 300) | Ja |
| 3 | Søjle | Pour over: udstyr, opskrift og fejl | pour over kaffe (720) | Nej |
| 4 | Søjle | Kaffevægt: hvorfor og hvilken | kaffevægt (1 000) | Delvist |
| 5 | Sammenligning | Manuel kaffekværn: de bedste håndkværne | manuel kaffekværn (880) | Ja |
| 6 | Sammenligning | Kaffekværn til espresso | kaffekværn espresso (320) | Ja |
| 7 | Sammenligning | Kaffekværn til filterkaffe | bedste kaffekværn til filterkaffe (90) | Delvist |
| 8 | Sammenligning | Wilfa, Bodum, Sage eller DeLonghi: hvilken kværn? | wilfa kaffekværn (880) | Delvist |
| 9 | Sammenligning | Manuel eller fuldautomatisk espressomaskine | manuel espressomaskine (210) | Delvist |
| 10 | Sammenligning | Mælkeskummer: de bedste typer | mælkeskummer (8 100) | Delvist |
| 11 | Sammenligning | Bedste filterkaffemaskine (Moccamaster og alternativer) | kaffemaskine bedst i test (1 300) | Delvist |
| 12 | Sammenligning | Kaffebønner: sådan finder du de bedste | kaffebønner (4 400) | Nej |
| 13 | Guide | Cold brew: opskrift og forhold | cold brew (880) | Nej |
| 14 | Guide | AeroPress: opskrifter og tips | aeropress (3 600) | Nej |
| 15 | Guide | Stempelkande: sådan bliver kaffen god | stempelkande (3 600) | Nej |
| 16 | Guide | Sådan laver du espresso derhjemme | hvordan laver man espresso (50) | Nej |
| 17 | Guide | Latte art for begyndere | latte art (1 000) | Nej |
| 18 | Guide | Afkalkning af espressomaskine | afkalkning af espressomaskine (480) | Nej |
| 19 | Guide | Rens din kaffekværn | kaffekværn rens (30) | Nej |
| 20 | Guide | Mokkakande: sådan bruger du den | mokkakande (390) | Nej |

**Status 12/11:** nr. 1, 3 og 12-20 er skrevet som udkast (`kaffeguiden-artikel-*.md`), sammen med siderne Om, Sådan tester vi og Affiliate (`kaffeguiden-side-*.md`). `tools/kaffeguiden_setup.sh` lægger dem alle ind som kladder, se [`kaffeguiden-opsaetning-2026-11-12.md`](kaffeguiden-opsaetning-2026-11-12.md).

**Rækkefølge:** guides og søjle 1 + 3 først (nr. 1, 3, 12-20). De kræver ikke udstyr og kan skrives ærligt nu. Sammenligninger
med »vores valg« venter, til der er prøvet mindst én kværn og én vægt (planen §6, punkt 4). Indtil da står de som
*købsguides*: hvad man skal se efter, uden at påstå en test.

## 5. Regler for hver side

1. **Ingen opdigtede tests, priser eller specifikationer.** En pris eller et tal kommer fra butikken eller producenten
   og får en dato. Står der »testet«, er det testet.
2. **Affiliate-linje øverst** på sider med links: »Siden indeholder affiliate-links. Køber du via dem, får vi en lille
   provision. Det koster ikke dig noget ekstra.«
3. **Link-pladsholdere** skrives `[[AFFILIATE: produkt / butik]]`, indtil Partner-Ads/Adtraction er godkendt. De bliver
   til `/go/`-links, så klik kan tælles pr. side.
4. **Kvalitet:** Command Centers kvalitetstjek og AI-redaktør (mindst 7/10) på alt. Et menneske læser hver søjle før
   udgivelse. Alt går til WordPress som **kladde**.
5. **Billeder:** ejeren har ikke selv udstyr (12/11), så alle billeder er AI-illustrationer med »Illustration (AI)« i
   billedteksten, uden mærker, og kun efter CC's billedtjek. Motiver og stil: [`kaffeguiden-billeder-2026-11-12.md`](kaffeguiden-billeder-2026-11-12.md).
6. **Ingen tests uden udstyr:** alle udstyrssider er købsguides. »Vores valg« bygger på producentdata og anmeldelser og siger
   det. Køber ejeren senere udstyr, kan en side blive en rigtig test.

## 6. Når domæne og WordPress er klar

1. **Command Center → projektvælgeren → Nyt projekt »Kaffeguiden«** med sidens adresse. Projektet får sin egen database,
   så steamgears' data ikke blandes.
2. **Moduler → Forbindelser** i det nye projekt: WordPress-adresse, bruger og applikationsadgangskode.
3. **Søgeord:** læg søgeordene fra §2 ind (hovedordene først), så CC's historik begynder at tælle placeringer fra dag ét.
4. **Flows:** skabelonen *Indholdsmaskinen* med AI-loftet uændret (2 artikler pr. kørsel). Den laver kladder, aldrig
   udgivelser.
5. **Artikel 1** (udkastet ved siden af dette dokument) lægges ind som kladde, læses igennem, får fotos og
   affiliate-links og udgives derefter i hånden.

## 7. Kilde

OpenSEO MCP 12/11 2026: `research_keywords` (frø »kaffekværn«, 150 rækker) og `get_keyword_metrics` (35 ord), Danmark
(2208), dansk. Ca. 55 credits brugt i dag på Kaffeguiden. Domænerne er tjekket med DNS-opslag af navneservere fra
steamgears.eu, ikke hos registraren.
