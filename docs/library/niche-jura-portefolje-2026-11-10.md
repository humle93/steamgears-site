# Fire butikker, fire juratekster — og en tabel der ikke sagde hvad den manglede

KABINEs juridiske sider blev skrevet færdige 10/11. Samme dag blev de tre øvrige butikker målt — og de havde
præcis det samme hul: **OPLOFT, STILLE og IMMER stod stadig med de tynde kladder**, som KABINE netop var
kommet ud af.

Det er værd at skrive ned, fordi det siger noget om, hvordan et »færdigt« modul kan se færdigt ud: indholdspakken
blev beskrevet som komplet med 36 sider, og preflighten var grøn. Den var grøn, fordi den **tæller felter** — ikke
fordi den læser dem.

## Målt før og efter

| Butik | Handelsbetingelser | Privatliv | Fortrydelse | Levering og retur |
| --- | ---: | ---: | ---: | ---: |
| KABINE (skrevet 10/11 formiddag) | 3.756 | 3.791 | 2.400 | 2.072 |
| OPLOFT — før | 1.608 | 1.780 | 1.263 | 1.227 |
| OPLOFT — efter | **4.141** | **3.883** | **2.567** | **2.404** |
| STILLE — før | 1.608 | 1.780 | 1.247 | 1.203 |
| STILLE — efter | **4.347** | **3.867** | **2.616** | **2.421** |
| IMMER — før | 1.607 | 1.780 | 1.263 | 1.195 |
| IMMER — efter | **4.485** | **3.879** | **2.624** | **2.362** |

Alle fire butikker manglede de samme otte ting, som KABINE manglede: standardfortrydelsesformularen,
6-måneders-formodningsreglen, 14-dages-tilbagebetalingen, GPSR-afsnittet med EU-ansvarlig person,
emballageregistreringen, cookie-tabellen, formål og grundlag pr. behandling og tredjelandsoverførslen. Det er ikke
en tilfældig liste — det er de elementer, en dansk netbutik bliver målt på, og de stod i alle tre butikker som
»skal gennemgås af ejeren og en rådgiver« uden at være skrevet.

## Hvad der blev tilpasset, ikke kopieret

Fællesstoffet (aftale, moms, betaling, reklamation, tvist, cookies, rettigheder) er det samme, fordi loven er den
samme. Det butiksspecifikke er ikke:

| Butik | Det, kun den butik kunne skrive |
| --- | --- |
| OPLOFT | Montering og **belastning gælder underlaget, ikke ønsket**; udlejerens tilladelse; reolen sendes i sektioner; en monteret reol, der ikke kan afmonteres uden skade, kan give værdiforringelse |
| STILLE | **Hygiejneundtagelsen** for forseglede ørepropper; tryk og vægt er ikke behandling; vægtede varer er til voksne, ikke til søvn, og en person, der ikke selv kan tage varen af, skal have en voksen ved sig |
| IMMER | **WEEE-registrering** fordi hvid støj indeholder elektronik og batteri; dynen skal kunne løftes af den, der sover under den; forseglet maske og ørepropper er undtaget |

Det er de sætninger, der gør teksten brugbar i en tvist: de peger på den vare, kunden har købt.

## Den tabel, der manglede noget

STILLEs side **Størrelse og pasform** havde en størrelsestabel med 12 felter, der stod som `[EJER]` — uden navn,
så ejeren ikke kunne se, om der skulle stå brystmål, længde eller ærmelængde. Samme side skrev desuden »vælg
omkring 10 % af din kropsvægt«, men tæppet findes kun i **én vægt (7 kg)**; det samme falske valg stod i FAQ og
på produktsiden.

Tre rettelser:

1. De 12 felter hedder nu `[EJER: brystmål S]`, `[EJER: længde S]`, `[EJER: ærmelængde S]` … så ejeren kan se,
   hvad der skal måles, og i hvilken størrelse.
2. Tæppet beskrives som **7 kg i én vægt**, der typisk passer til en kropsvægt omkring 70 kg — i stedet for et
   valg, der ikke findes. FAQ-spørgsmålet hedder nu »Passer tæppet til min vægt?«.
3. Tabellen er markeret som **hættetrøjens** mål; vesten har ingen ærmer.

## Værnet, der lod dem slippe

`tools/deploy/niche_preflight.py` havde et tjek for ejerfelter, men det sammenlignede kun **antallet** af `[EJER`
med antallet af velformede `[EJER …]`-felter. Et felt uden navn er velformet, så det gik igennem. Der er nu en
regel mere:

```python
BARE_OWNER_RE = re.compile(r"\[EJER\s*\]")
…
if BARE_OWNER_RE.search(collected):
    problems.error(f"{niche_slug}: ejerfelt uden navn — skriv hvad ejeren skal indsætte, "
                   "fx [EJER: brystmål S] i stedet for [EJER]")
```

Reglen er prøvet negativt: et `[EJER]` indsat i en side giver **exit 1** med beskeden
»sensorisk: ejerfelt uden navn — skriv hvad ejeren skal indsætte, fx [EJER: brystmål S] i stedet for [EJER]«.
Filen blev lagt tilbage, og `--check` svarer igen grønt.

## Kontrol — kørt 10/11

```
$ python3 tools/deploy/niche_preflight.py --check
OK: 4 nicher, ingen fejl, 0 advarsler, ingen drift.

$ python3 tools/deploy/deploy_nicher.py
KABINE   5 varer · 5 kollektioner · 9 sider · 57 ejerfelter
OPLOFT   6 varer · 5 kollektioner · 9 sider · 59 ejerfelter
STILLE   5 varer · 5 kollektioner · 9 sider · 71 ejerfelter
IMMER    5 varer · 5 kollektioner · 9 sider · 59 ejerfelter

$ python3 tools/deploy/deploy_nicher.py --check
OK: 4 nicher klar, ingen fejl, ingen drift.
```

I alt **36 sider** med **246 ejerfelter i 134 unikke felter** (29 KABINE · 31 OPLOFT · 43 STILLE · 31 IMMER).
STILLE har flest, fordi størrelsestabellen nu spørger om et tal pr. størrelse og pr. mål i stedet for at bede om
»noget«.

## Hvad der stadig er ejerens

Det er de samme seks slags oplysninger som før, nu fordoblet til fire butikker: identitet (CVR, adresse,
telefon, e-mail), logistik (behandlingstid, fragtfirma, priser pr. zone, lande, returadresse), betaling
(betalingsmetoder og -udbyder), data og leverandører (platform, statistik, marketing, cookie-levetider),
jura og sikkerhed (EU-ansvarlig person, emballageregistrering, og for IMMER også WEEE-registreringen) samt
tekst og dato.

Det, der **ikke** er ejerens: teksten. Den er skrevet, den er tilpasset hver butik, og den er kontrolleret af
værnet. Det næste, der sker, er at værdierne sættes ind, at en rådgiver læser de fire sæt igennem, og at siderne
publiceres sammen med butikken — det kræver E1, E2 og E3.
