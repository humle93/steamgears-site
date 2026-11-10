# Hele fladen målt: 36 sider, 21 varer, 95 live-URL'er — og de huller der var tilbage

Efter at jura-teksterne var skrevet for alle fire butikker, blev resten af fladen målt i stedet for antaget.
Spørgsmålet var ikke »ser det færdigt ud?«, men »hvor står der stadig noget, ingen har skrevet?«.

Målingen er gentagelig: `python3 tools/niche_surface.py` læser de fire indholdsfiler og melder tynde sider,
kladde-tekst, tomme sektioner og unavngivne ejerfelter. Exit 1 hvis der findes et hul.

## Hvad der blev målt

| Flade | Omfang | Hvordan |
| --- | --- | --- |
| Butikkernes sider | 4 × 8 kildesider + 4 genererede FAQ-sider = **36** | længde, overskrifter, kladde-ord, ejerfelter |
| Varer | **21** | kort tekst, lang tekst, bullets |
| FAQ | **32** poster | svarlængde |
| Hooks | **16** | længde |
| Annoncer | **12** | primary, headline, description, creative, landing |
| Mails | **12** | subject, preheader, body |
| Shopify-CSV | **4 filer, 21 rækker** | tomme celler, pladsholdere |
| Det levende site | **95 URL'er fra sitemap** | HTTP-status, ordtal, kladde-markører |

## Hullerne der var

1. **Nul annoncer og nul mails i tre af fire butikker.** Kun KABINE havde de tre annoncer og tre mails, som
   indholdspakken blev beskrevet med. OPLOFT, STILLE og IMMER havde tomme lister — en sektion, der ser færdig
   ud i JSON, men ikke findes i butikken.
2. **To hooks under 40 tegn**, hvoraf den ene var 34: »Reolen kan flytte med, når du gør.« — en halv sætning,
   ikke en hook.
3. **Kontaktsiderne var de tyndeste sider på fladen:** 546–614 tegn med fire felter og to overskrifter. De sagde
   ikke, hvad en kunde skal skrive for at få et svar, og de sagde ikke, hvor fortrydelsen står.
4. **Shopify-CSV'en har tre tomme celler pr. vare** (`Variant Compare At Price`, `Variant Barcode`, `Image Src`).
   Det er ikke en tekstlig kladde — det er ejerens priser, stregkoder og fotos.

## Hvad der blev skrevet

| Butik | Annoncer | Mails | Kontaktside før → efter |
| --- | --- | --- | --- |
| KABINE | 3 (uændret) | 3 (uændret) | 614 → **1.300** tegn |
| OPLOFT | **3 nye**: Hullet · Depositum · Målet | **3 nye**: Velkomst · Brug · Flytning | 546 → **1.312** |
| STILLE | **3 nye**: To kilo · Syv kanaler · Pasformen | **3 nye**: Velkomst · Brug · Sikkerhed | 558 → **1.325** |
| IMMER | **3 nye**: Mørke · Lyden · Vægten | **3 nye**: Velkomst · Brug · Elektronik | 553 → **1.300** |

Annoncerne er skrevet som tre vinkler pr. butik — problemet, prisen og den praktiske indvending — og lander på
den vare, teksten handler om. Mailene er de tre, en kunde faktisk har brug for: hvad gør jeg nu, hvordan vedligeholder
jeg det, og hvad skal jeg være opmærksom på. Hver mail slutter med noget, der er sandt for netop den vare:
reolen kan tages ned, tæppet kan ikke vaskes i maskine, batteriet må ikke ligge under en pude.

Kontaktsiden fik fire afsnit, alle fire butikker: hvad du skal skrive for at få et hurtigt svar (ordrenummer og
billede), hvor fortrydelsen står, hvor den butiksspecifikke viden står (pleje, montering, pasform eller
sikkerhed), og firmaoplysningerne. Den er stadig kort — men nu kan en kunde bruge den.

## Værnet: seks nye regler, hver prøvet negativt

`tools/deploy/niche_preflight.py` kunne før acceptere en juridisk side på 1.200 tegn, et `[EJER]` uden navn, en
tom annoncesektion og ordet »kommer snart«. Det kan den ikke mere:

| Regel | Fejlmeddelelse ved brud |
| --- | --- |
| Ejerfelt uden navn | »ejerfelt uden navn — skriv hvad ejeren skal indsætte« |
| Gulv pr. sidetype (`PAGE_FLOOR`) | »sideteksten er 1.500 tegn — gulvet for denne sidetype er 3.000; en kortere side er en kladde« |
| Kladde-tekst (`STUB_RE`) | »kladde-tekst i indholdet: kommer snart« |
| Mindst 3 annoncer + felternes længde | »færre end tre annoncer — en tom annoncesektion ser færdig ud« / »primary er 7 tegn (kræver mindst 150)« |
| Mindst 3 mails + felternes længde | »færre end tre mails« |
| Mindst 4 hooks, hver ≥ 25 tegn | »hook 1: under 25 tegn — en stub, ikke en hook« |

Alle seks er prøvet ved at ødelægge `indhold/sensorisk.json`, køre `--check` og lægge filen tilbage:

```
ejerfelt uden navn                 exit=1
tynd juridisk side (1.500 tegn)    exit=1
kladde-tekst "kommer snart"        exit=1
tom annoncesektion                 exit=1
annonce med 7 tegns primary        exit=1
kun én mail                        exit=1
hook på 6 tegn                     exit=1
fil gendannet: True
OK: 4 nicher, ingen fejl, 0 advarsler, ingen drift.
```

Det sidste er pointen: reglerne fanger fejlen, og den urørte fil består bagefter.

## Fladen nu

```
$ python3 tools/niche_surface.py
butik    sider   tegn  felt  ads mails hooks varer faq
KABINE       8  17390    57    3     3     4     5   8
OPLOFT       8  17994    59    3     3     4     6   8
STILLE       8  18694    71    3     3     4     5   8
IMMER        8  18514    59    3     3     4     5   8

Ingen huller: ingen tynde sider, ingen kladde-tekst, ingen tomme sektioner.
```

## Det levende site

Sitemap-kortlægning af **steamgears.eu** og måling af alle **95 URL'er** (produkter, kategorier, guides, sider):

| Måling | Resultat |
| --- | --- |
| HTTP-status | **95 af 95 svarer 200**, 0 fejl |
| Mindste ordtal | **320** (`/product-category/vintage-maritime/`) |
| Median ordtal | **412** |
| Sider under 120 ord | **0** |
| Kladde-markører (`[EJER`, TODO, lorem, »kommer snart«) | **0** |

Sitet har altså ingen tynde sider eller pladsholdere at skrive færdige. Det, der står tilbage der, er indhold
ejeren skal beslutte (GPSR-blokken med CVR, mockups) — ikke tekst nogen kan skrive for ham.

## Det der stadig er ejerens

- **Værdierne i de fire butikker:** identitet (E1), leverandør og GPSR-dokumentation (E2), emballage- og
  WEEE-registrering (E3) — 246 ejerfelter i 134 unikke felter, plus produktfotos, stregkoder og før-priser i CSV'en.
- **Publicering:** domæne og Shopify-konto (E4), annoncekonto og startdato (E5/E6), rådgiverlæsning af de fire
  jurasæt.
- **Det levende site:** DKIM findes ikke i zonen (S5.7), staging-gendannelsen mangler (S5.8), GitHub-beskyttelse
  kræver Pro (S5.9), sandkassen kræver et Simply-subdomæne (S5.1), Printful-token til mockups (Batch 4), og
  Pinterest/kanal-tokens til plugin'et.

Ingen af dem kan lukkes indefra sandkassen — og ingen af dem er tekst, der mangler at blive skrevet.
