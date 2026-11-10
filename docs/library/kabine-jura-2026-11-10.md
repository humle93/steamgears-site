# KABINEs juridiske sider — skrevet, ikke gættet

KABINEs fire juridiske sider er nu skrevet færdige i tekstkilden: **handelsbetingelser, privatlivspolitik,
fortrydelse og levering/retur**. Det var det største åbne punkt i repoet, som ikke var ejer-blokeret —
identitetsdataene er ejerens, men teksten er ikke.

Målt 10/11 2026: `deploy/nicher/indhold/kabine.json` er kilden, `deploy/nicher/kabine-sider.md` er den
genererede kladde, og `tools/deploy/deploy_nicher.py --check` svarer **»OK: 4 nicher klar, ingen fejl, ingen
drift«**.

## Hvad der var galt

Modulet `b8` stod som **0 af 2,5 t** med noten »ikke startet: kræver E1 (CVR, adresse, telefon) og E3«. Det
var rigtigt så langt, at siderne ikke kan **publiceres** uden ejerens oplysninger — men teksten var heller
ikke skrevet. Der lå tre kladder på 1.339–1.780 tegn, og de manglede de dele, en dansk netbutik bliver målt på:

| Manglende del | Hvorfor det ikke er kosmetik |
| --- | --- |
| Standardfortrydelsesformularen | Forbrugeraftaleloven kræver, at formularen følger med; en fritekst-formular er ikke nok |
| 6-måneders-formodningsreglen | Uden den skriver butikken en ringere reklamationsret, end kunden har |
| 14-dages-fristen for tilbagebetaling | Fortrydelsesretten er tom uden fristen for at betale pengene igen |
| GPSR-afsnittet med EU-ansvarlig person | Uden den er varen ikke lovlig at sælge i EU (forordning (EU) 2023/988) |
| Emballageregistreringen | Producentansvaret gælder, før markedsføringen starter |
| Cookie-tabellen med udbyder og levetid | Cookiebekendtgørelsen kræver, at det konkrete valg kan dokumenteres |
| Formål og grundlag pr. behandling | GDPR art. 6/13: et samlet afsnit »vi behandler for at sælge« er ikke nok |
| Tredjelandsoverførsel og dataportabilitet | To rettigheder, der ellers kun står halvt |

## Hvad der nu står

| Side | Tekst | Dækker |
| --- | ---: | --- |
| Handelsbetingelser | 3.756 tegn | Firmaoplysninger og emballageregistrering, aftale/ordrebekræftelse, priser og moms med før-pris-regel, betaling, levering og risikoovergang, fortrydelse, reklamation med formodningsregel, GPSR, ansvar, rettigheder, version, tvist |
| Privatlivspolitik | 3.772 tegn | Dataansvarlig, fem kategorier af oplysninger, formål og grundlag pr. formål (art. 6 b/c/f/a), databehandlere, tredjelandsoverførsel, opbevaring pr. kategori, seks rettigheder, Datatilsynet, cookie-tabel, sikkerhed, ændringer |
| Fortrydelse | 2.388 tegn | 14-dages-fristen, fire trin, standardformularen ord for ord, returfragt og værdiforringelse, undtagelser, tilbagebetaling inden 14 dage |
| Levering og retur | 2.072 tegn | Behandlingstid og 1-3 hverdage, sporing, beskadiget eller væk pakke, fortrydelse, retur, reklamation, hvorfor der ikke sendes væske |

To ting er bevidst gjort ens: **fortrydelse** står på begge sider med samme frist og samme formulering, så en
kunde ikke kan læse to forskellige regler; og **reklamation** henviser til handelsbetingelserne i stedet for at
gentage vilkårene i en kortere version.

## Fælden: ret kilden, ikke den genererede fil

`kabine-sider.md` bliver **skrevet af værktøjet** ud fra `deploy/nicher/indhold/kabine.json` og bliver
driftskontrolleret bagefter. En håndrettelse i markdown-filen forsvinder ved næste kørsel — og `--check` fejler
med »ude af drift — kør --build«, hvis payloaden ikke matcher kilden. Rækkefølgen er derfor:

```bash
# 1. ret teksten i kilden
$EDITOR deploy/nicher/indhold/kabine.json

# 2. byg payloads (validerer teksten: SEO-længder, påstande, ejerfelter)
python3 tools/deploy/niche_preflight.py --build

# 3. generér sider og CSV
python3 tools/deploy/deploy_nicher.py

# 4. kontrol: ingen fejl, ingen drift
python3 tools/deploy/deploy_nicher.py --check
```

Preflighten tæller `[EJER: …]`-felter og **nægter at fjerne dem for at få en grøn kørsel** — et ejerfelt, der
bliver gættet, er en juridisk oplysning, nogen har opfundet. Efter normaliseringen af dubletter
(`firmanavn`/`registreret firmanavn`, `CVR`/`CVR-nummer`) står der **29 unikke felter i 57 forekomster** fordelt
på ni sider.

## Hvad der stadig er ejerens

| Kategori | Felter | Blokerer |
| --- | --- | --- |
| Identitet | registreret firmanavn, CVR-nummer, adresse, telefon, butikkens e-mail | E1 |
| Logistik | behandlingstid, fragtfirma, fragtpris pr. zone, fri fragt-grænse, lande, returadresse, hvem betaler returfragt | E1/E2 |
| Betaling | betalingsmetoder, betalingsudbyder | E1/E4 |
| Data og leverandører | webshop-platform, statistik- og marketingudbyder, cookie-levetid, opbevaringsår, e-mail- og analyseværktøjer | E4 |
| Jura og sikkerhed | EU-ansvarlig person med e-mail, emballageregistreringsnummer | E2/E3 |
| Tekst og dato | »hvem står bag« med ejerens egne ord, svartid, opdateringsdato | E1 |

Det er **0,5 af de 2,5 timer**: at sætte værdierne ind, få siderne læst af en rådgiver og publicere dem. De
2,0 timer var at skrive dem, og de er nu brugt.

## Kontrol — kørt 10/11

```
$ python3 tools/deploy/deploy_nicher.py --check
OK: 4 nicher klar, ingen fejl, ingen drift.

$ python3 tools/deploy/deploy_nicher.py
KABINE   5 varer · 5 kollektioner · 9 sider · 57 ejerfelter
```

Preflighten dækker SEO-titler (15–60 tegn), meta-beskrivelser (60–155), sidetekst over 300 tegn, at de syv
påkrævede sider findes, at ingen påstand uden dokumentation står i markedsføringsteksten, at der ikke står et
»100 %« nogen steder, og at hvert ejerfelt er korrekt lukket. Den siger **ikke**, at teksten er juridisk
korrekt — den siger, at den er komplet nok til at blive læst af en rådgiver og derefter publiceret med
ejerens oplysninger i.
