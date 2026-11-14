# Ejerpakken — det der ikke krævede adgang (14/11 2026)

Ejerlisten var skrevet som *opgaver*. Nu er de punkter, der ikke kræver ejerens adgang, konti, paneler eller
underskrift, skrevet færdige som filer, så det, der er tilbage, er **indsæt og klik**. Ingen af punkterne er
talt som fremgang, hvor der stadig mangler ejerens data.

## 1. Hvad der nu ligger, og hvad ejeren gør

| Post | Artefakt | Ejeren gør derefter |
| --- | --- | --- |
| KABINE jura (E1/E3) | [kabine/juridik/README.md](../kabine/juridik/README.md): indsæt-liste over 29 pladsholdere. Teksten ligger i [`deploy/nicher/indhold/kabine.json`](../../deploy/nicher/indhold/kabine.json) | indsætter felterne, læser korrektur, publicerer |
| S5.7 DKIM/DMARC | [dns-og-miljoe-pakke-2026-11-14.md](../dns-og-miljoe-pakke-2026-11-14.md) §S5.7 | sletter CNAME, opretter TXT, slår DKIM til, kopierer TXT |
| S5.1 staging | samme dokument §S5.1 | klikker syv trin i Simply |
| S5.8 backup | samme dokument §S5.8 | skriver fire tal ind i tabellen |
| S5.9 GitHub | samme dokument §S5.9 | køber planen, kører to `gh`-kald |
| Plugin, syv led | [plugin-opsaetning-2026-11-14.md](../plugin-opsaetning-2026-11-14.md) | indsætter nøgler, svarer ja/nej |

## 2. To målinger, der ændrede instruktionerne

**a) `staging.steamgears.eu` ser ud til at findes — og gør det ikke.** Navnet svarer `94.231.103.182`, samme
IP som live. Men zonen har **wildcard**: `findes-ikke-8f3a.steamgears.eu` svarer præcis samme adresse. Et
`dig A` på et hvilket som helst navn under domænet giver altså svar uden at navnet findes, og certifikatet
dækker stadig kun `steamgears.eu` og `www.steamgears.eu`:

```
$ curl -sI https://staging.steamgears.eu
curl: (60) SSL: no alternative certificate subject name matches target host name 'staging.steamgears.eu'
```

Det **eneste** gyldige bevis for S5.1 er derfor certifikatets SAN-liste og et **401** fra HTTP-auth — ikke et
A-opslag. Det står nu som krav i pakken, så posten ikke lukkes på et wildcard.

**b) `_dmarc` er en CNAME, og det blokerer rettelsen.** Zonen svarer:

```
_dmarc.steamgears.eu.  3600  IN  CNAME  dmarc.simply.com.
dmarc.simply.com.             IN  TXT    "v=DMARC1; p=reject; rua=mailto:dmarc@robot.simply.com; …"
```

En CNAME og en TXT kan ikke ligge på samme navn, så en DMARC-TXT lagt ind ovenpå CNAME'en slår ikke igennem —
uanset hvad panelet melder. Rækkefølgen (slet CNAME, gem, opret TXT) er derfor skrevet ind som et krav, ikke
som en anbefaling. Det forklarer også, hvorfor ejerens melding 9/11 ikke kunne ses i zonen: der var intet at
se, fordi posten ikke kan eksistere ved siden af CNAME'en.

Zonen er i øvrigt uændret siden 9/11: apex `A 94.231.103.182`, `www` samme, TXT
`v=spf1 include:spf.simply.com -all`, `MX 10 mx.simply.com.`, ingen `_domainkey`, og ingen DKIM-post blandt 32
prøvede selectorer.

## 3. KABINE-juraen: teksten var skrevet, det manglende var listen

De tre sider blev skrevet 10/11 og ligger som færdig HTML i `deploy/nicher/indhold/kabine.json`
(`pages[2]` og `pages[4]`–`pages[7]`), med 2,0 af 2,5 t krediteret. Jeg har **ikke** skrevet en kopi — en kopi
ville blive en anden sandhed, næste gang nogen retter i JSON'en — men verificeret den og gjort de sidste 0,5 t
til en liste:

- **8 sider**, **29 unikke pladsholdere i 57 forekomster**, alle markeret `[EJER: …]`, **0 umarkerede huller**.
- `python3 tools/deploy/niche_preflight.py --check` → **OK: 4 nicher, ingen fejl, 0 advarsler, ingen drift**.
- Hver pladsholder er nu klassificeret (E1, E2/GPSR, E3-emballage, E4-platform eller **valg**) med hvor den
  står, og hvad der skal ind.
- **Fem fælder** ved gennemlæsningen, hvoraf de tre koster penge, hvis de overses: `[EJER: kunde/butik]` er et
  valg (slet det ene ord, og oplys returfragten før køb, ellers gælder den ikke); fri fragt-sætningen skal
  slettes **helt**, hvis der ikke skal være fri fragt; og behandlingstid må ikke lægges sammen med de 1-3 dages
  levering, så siden lover 2-6 dage i alt.

## 4. Hvad der ikke blev talt som fremgang

Miljø står på **60,0 %** (1,80 af 3,00 d) — uændret, fordi zonen svarer det samme, og fordi et dokument ikke
er en DNS-post. KABINE står også uændret: jura-modulet var allerede på 2,0 af 2,5 t, og en feltliste er ikke
de sidste 0,5 t — de er data, rådgiverlæsning og publicering. Rebrand og site er urørte.

## 5. Næste skridt

1. Slet `_dmarc`-CNAME'en og læg TXT'en ind — 2 minutter, og S5.7's 0,15 d kan måles.
2. Skriv de fire backup-tal ind (S5.8) og klik staging-trinnene (S5.1).
3. Indsæt de 29 felter i KABINE, læs korrektur, publicér.
4. Fyld AIMLAPI op og sæt alert-adressen — de to billigste plugin-punkter.
