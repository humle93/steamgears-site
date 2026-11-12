# Kaffeguiden: AI-billeder til de 14 sider

**Dato:** 12/11 2026 · **Baggrund:** ejeren har ikke selv kaffeudstyr, så alle billeder er AI-illustrationer. Command Center
laver dem med sit billedtjek (`make_image` → `image_rules` → `review_image` → `qa_verdict`, »fail closed«), når projektet findes
og OpenRouter-saldoen er mindst $1.

## 1. Hvad det betyder for siden

- **Ingen sider bliver »testet« eller »bedst i test«.** Uden udstyr er alle udstyrssider *købsguides*: de bygger på
  producenternes oplysninger, almindelig kaffeviden og offentlige anmeldelser, og det står på siden. »Sådan tester vi« siger
  allerede, at alle udstyrssider er købsguides.
- **»Vores valg« i søjle 1 og sammenligningerne** udfyldes som »vores bud ud fra producentdata og anmeldelser, ikke testet af
  os«. Det er stadig nyttigt for læseren og ærligt over for Google.
- **Hvert billede får teksten »Illustration (AI)«** i billedteksten. Billederne viser ingen bestemte mærker eller modeller, så
  de ikke kan forveksles med et produktfoto.

## 2. Billedstil for projektet

Indsæt i feltet *Billedstil* ved oprettelsen af projektet »Kaffeguiden« i Command Center (felt `media_style`, højst 600 tegn):

```
Editorial photograph for a Danish home-coffee guide: bright Scandinavian kitchen, light oak and white
surfaces, soft natural window light, shallow depth of field, warm neutral colours, generic unbranded
equipment, sharp focus, no text, no letters, no logos, no brand names, no watermark, no faces.
```

## 3. Billeder pr. side

Motivet sendes som `subject` til *Medier → Lav billede* (fokusordet som `keyword`). Formatet er 16:9 til topbilleder.
Alt-teksten skrives ind i WordPress sammen med billedet.

| Side | Motiv (subject) | Alt-tekst (dansk) |
| --- | --- | --- |
| /kaffekvaern/ | Close-up of a generic burr coffee grinder on a kitchen counter, freshly ground coffee in the cup below, whole beans scattered beside it | Kaffekværn med skiver og friskmalet kaffe på et køkkenbord |
| /pour-over/ | A pour-over dripper on a glass carafe, a gooseneck kettle pouring a thin stream of water onto blooming coffee grounds, a small scale underneath | Pour over: vand hældes fra en kedel med svanehals over kaffen i en dripper |
| /kaffeboenner/ | Three small open bags of whole coffee beans in light, medium and dark roast side by side, beans spilling onto a wooden board | Kaffebønner i lys, mellem og mørk ristning side om side |
| /guides/cold-brew/ | A large glass jar of cold brew coffee steeping in a fridge-lit kitchen, next to a glass of iced coffee with milk swirling in | Cold brew i en glaskrukke og et glas iskaffe med mælk |
| /guides/aeropress/ | A generic clear plastic coffee press plunger pressing coffee into a mug, hands visible from the wrists, no face | Kaffe presses med en AeroPress ned i et krus |
| /guides/stempelkande/ | A glass French press full of coffee with the plunger half down, a cup of black coffee beside it on a breakfast table | Stempelkande med kaffe og en kop sort kaffe på morgenbordet |
| /guides/espresso/ | A double espresso shot pouring from a generic espresso machine's portafilter into a small glass cup with rich crema | Espresso løber fra portafilteret ned i en lille glaskop |
| /guides/latte-art/ | Overhead view of a wide cup of latte with a heart pattern in the milk foam, a stainless steel milk jug beside it | Latte art: et hjerte i mælkeskummet set ovenfra |
| /guides/afkalkning-espressomaskine/ | A generic home espresso machine with its water tank removed, a jug of clear water and a measuring cup on the counter, clean and tidy | Espressomaskine klar til afkalkning med vandtanken taget af |
| /guides/rens-kaffekvaern/ | A disassembled burr grinder: the upper burr removed, a small brush and a few coffee grounds on a cloth | Kaffekværn skilt ad med børste til rengøring af skiverne |
| /guides/mokkakande/ | A classic octagonal aluminium stovetop moka pot on a gas flame, coffee bubbling out of the spout, an espresso cup nearby | Mokkakande på komfuret med kaffen på vej op |
| /om/ | A calm morning kitchen scene with a steaming cup of coffee on a wooden table by the window, a notebook beside it | Kop kaffe på et træbord i morgenlys |
| /saadan-tester-vi/ | A neat flat-lay of a notebook, a kitchen scale, a timer and a cup of filter coffee on a white table | Notesbog, vægt, ur og en kop filterkaffe |
| /affiliate/ | A cup of coffee next to an open laptop on a light wooden desk, soft daylight, nothing on the screen | Kop kaffe ved siden af en bærbar computer |

## 4. Rækkefølge

1. Opret projektet »Kaffeguiden« i Command Center med stilen fra §2 (kan gøres, så snart sitet findes).
2. Sæt mindst $1 ind hos OpenRouter. Billeder kræver det (målt 12/11: 402 under $1). Budgettet er de $30 om måneden.
3. Lav billederne fra tabellen i *Medier*. Billedtjekket afviser dårlige billeder og laver et nyt (højst 2 forsøg). Et billede,
   som bedømmerne ikke svarer på, bliver stående som `venter` og bruges ikke.
4. Godkendte billeder sendes til WordPress (de bliver konverteret til JPEG) og sættes som topbillede på kladderne.

**Uden at vente på saldoen:** motiverne i §3 kan også bruges i Canvas billedgenerator, sammen med stilen fra §2. Så springes
Command Centers billedtjek over, og du skal selv se billederne igennem.
