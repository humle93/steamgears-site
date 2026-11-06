# design.md — Steamgears' visuelle identitet

**Status:** specifikation. Målt på den live side 5/11-2026; ingen CSS er ændret endnu.
**Omfatter:** steamgears.eu — temaet `extendable`, template-delene `css/header-dark-clockwork-active.html` og `css/footer-active.html`.

Dokumentet er skrevet som en måling af, hvad der faktisk er på siden i dag, ikke som en ønskeliste. Hvor der står et tal, er det aflæst; hvor der står et token, er det en beslutning, der skal lægges ind i fase 1.

---

## 1. Hvad dokumentet er — og ikke er

Det er den ene kilde til, hvordan butikken skal se ud: skrifter, farver, afstande, komponenter, billeder, bevægelse, tilgængelighed og stemme. Det er **ikke** en CSS-fil og ikke et redesign af layoutet. Layoutet bliver, hvor det er; det er overfladen, der skal strammes op.

Retningen er mørk akademisk: messing, urværk, gammelt papir, ingen glansbilleder. Kurateret, ikke kostumeret.

---

## 2. Typografi

### Problemet, som det står i dag

- Headeren indlæser **omkring 30 skriftfamilier**. **Fire** bruges.
- Resten er død vægt på hver eneste sidevisning, og det er den slags vægt, der koster tidligt i en sidevisning, fordi den ligger i `<head>`.
- Overskrifterne bæres af **Georgia** — en systemfont. Den ser standard ud, fordi den *er* standard, og den er det tydeligste enkelttegn på, at butikken ikke er kurateret. En kunde kan ikke navngive fonten, men kan sagtens se den.

### Løsningen

**Marcellus** ligger allerede i temaets fontmappe. Det er en rolig romersk kapitalserif, der gør præcis det, Georgia ikke gør: den ser valgt ud. Ingen ny download, ingen ny licens, bare et andet valg i samme mappe.

- Overskrifter: **Marcellus**, kun kapitalnær brug, aldrig til brødtekst.
- Brødtekst: én neutral grotesk, allerede indlæst. Ikke en ny.
- Skrifterne skal indlæses lokalt fra temaets mappe, ikke fra tredjepart.
- Alt andet fjernes fra headeren. Målet er **to familier i alt**.

### Skala — syv trin

| Trin | Rolle | Størrelse (rem) | Vægt | Linjehøjde |
| --- | --- | --- | --- | --- |
| 1 | Mikro: etiketter, lovtekst | 0,75 | 500 | 1,4 |
| 2 | Sekundær: hjælpetekst, metadata | 0,875 | 400 | 1,5 |
| 3 | Brødtekst | 1,0 | 400 | 1,6 |
| 4 | Indledning, produktkortets titel | 1,25 | 500 | 1,4 |
| 5 | Underoverskrift | 1,625 | 400 | 1,3 |
| 6 | Sektionsoverskrift | 2,125 | 400 | 1,2 |
| 7 | Forsideoverskrift | 2,75 | 400 | 1,1 |

Syv trin er nok. Hver ny størrelse, der ikke står i tabellen, er en fejl, ikke en undtagelse.

---

## 3. Farver

Siden bruger i dag **18 hex-værdier** fordelt over tema, plugins og inline-styles. Retningen er rigtig — næsten-sort, messing, creme, lidt vinrødt — men antallet gør det umuligt at ændre noget ét sted. Fire roller erstatter de 18.

| Rolle | Token | Hex | Bruges til |
| --- | --- | --- | --- |
| Blek | `--sg-ink` | `#14110E` | Brødtekst, overskrifter, mørke flader |
| Pergament | `--sg-parchment` | `#F2EAD9` | Sidens bund, lyse flader |
| Messing | `--sg-brass` | `#B08D57` | **Kun** linjer, rammer, store tal, ikoner |
| Messing-tekst | `--sg-brass-text` | `#7A5C33` | Al messingfarvet tekst |
| Vin | `--sg-wine` | `#6B2B2B` | Accent, udsolgt, fare |

`--sg-brass` er målt til **2,70:1** mod pergament. Det er under kravet for tekst og må derfor aldrig bære tekst — heller ikke i knapper, heller ikke i 16 px. Til messingfarvet tekst bruges `--sg-brass-text`, som er målt til **5,38:1** og dermed lovlig til brødtekststørrelse.

To flader, der ligner hinanden for meget, er et problem i sig selv: kort på pergament skal have en 1 px messing-linje eller en toneforskel på mindst 4 % lyshed, ellers forsvinder kantens funktion.

**Regel:** ingen ny farve uden at den erstatter en af de fem ovenfor. Ingen gradienter. Ingen ren `#000` eller `#fff`.

---

## 4. Afstande

Alt sidder på et **4 px-grid**. Tilladte værdier: 4, 8, 12, 16, 24, 32, 48, 64, 96. Der er ingen 10, 15 eller 20 px.

- Lodret rytme mellem sektioner: 96 px desktop, 64 px mobil.
- Indvendig luft i kort og bokse: 24 px.
- Afstand mellem overskrift og brødtekst: 12 px.
- Maksimal brødtekstbredde: 68 tegn. Længere linjer læses ikke, uanset hvor pæne de er.

---

## 5. Komponenter

**Knap, primær:** messingbaggrund med blektekst, 1 px mørkere ramme, 12/24 px luft, radius 2 px. Ingen skygge. Ingen pil. Hover: baggrunden mørknes ét trin, ikke lysnes.

**Knap, sekundær:** gennemsigtig baggrund, 1 px messing-linje, messing-tekstfarve. Hover: linjen bliver blek.

**Produktkort:** billede i 4:5, ingen ramme om billedet. Titel i trin 4, pris i trin 3 med messing-tekstfarve. Ingen "Tilføj til kurv" i selve kortet — den hører på produktsiden. Kortet skal kunne læses på 250 px bredde uden at knække.

**Fokus:** 2 px messing-tekstfarve, forskudt 2 px. Synligt på både pergament og mørke flader. Gælder alle interaktive elementer, uden undtagelse.

---

## 6. Billedstandard

- **4:5**, altid. Portræt, ikke kvadratisk, ikke 16:9.
- **Tre billeder pr. vare:** ét produktbillede på neutral baggrund, ét i brug, ét af detaljen (søm, messing, gravering).
- **Ingen mockups.** Et billede af en t-shirt med et tryk på en model er et løfte, kunden kan se er tomt. Et rigtigt foto af det faktiske produkt er det eneste, der holder.
- Baggrund: pergament eller mørk flade — aldrig hvid, aldrig stuens rod.
- Filformat: WebP, én størrelse pr. brug (kort, produkt, zoom), eksplicit bredde og højde i HTML, `alt` der beskriver varen.

---

## 7. Bevægelse

Bevægelse skal være næsten usynlig. 150–200 ms, `ease-out`, kun på farve, kant og opacity. Ingen elementer, der glider ind ved scroll, ingen parallakse, ingen talende animationer. Respekter `prefers-reduced-motion` og slå alt fra, når den er sat.

---

## 8. Tilgængelighed

- Brødtekst mindst 16 px, aldrig grå på grå.
- Al tekst skal kunne læses uden farve: messing-linjer alene må ikke være den eneste markering.
- Fokusmarkering må ikke fjernes, uanset hvor pænere det ser ud uden.
- Kontrastkrav: 4,5:1 for tekst under 24 px, 3:1 for store overskrifter og for grænsefladekomponenter.
- Formularfelter har rigtige `<label>`, ikke placeholder som etikette.

---

## 9. Stemmen — og footer'en

Footer'en siger i dag: *"DIY steampunk costumes, goggles & gear — build it, wear it, own it."*

Det er den stik modsatte stemme af den, butikken er ved at bygge. "DIY" og "costumes" placerer varen som hobbyprojekt og udklædning, ikke som håndværk. To ord, der gør hele forskellen, og de står på hver eneste side.

Forslag i stedet: *"Messing, læder og urværk — til dem der hellere bygger end køber."*

Regler for stemmen: konkret før stemningsfuld, håndværk før tema, korte sætninger, ingen udråbstegn, ingen "DIY".

---

## 10. Gør / undgå

| Gør | Undgå |
| --- | --- |
| To skriftfamilier, syv trin | 30 indlæste familier, fire brugt |
| Fem farveroller i tokens | 18 hex-værdier spredt i tema og plugins |
| 4 px-grid | 10, 15, 20 px hist og her |
| Messing til linjer, messing-tekst til tekst | Messing som tekstfarve (2,70:1) |
| Rigtige produktfotos, 4:5 | Mockups og kvadratiske udsnit |
| Rolig, konkret stemme | "DIY", "build it, wear it", udråbstegn |
| Fokusmarkering, der kan ses | `outline: none` |

---

## 11. Rækkefølge, når det skal ind

1. **Font-oprydning først.** Billigst, størst effekt, nemmest at fortryde. Fjern de ubrugte familier, skift Georgia til Marcellus, indlæs lokalt.
2. **Footer-stemmen.** Fem minutter, ingen CSS, og den er den første sætning en kunde læser nederst på siden.
3. **Farve-tokens.** De fem roller ind som CSS-variabler, derefter udskiftning af de 18 hex-værdier ét sted ad gangen — med en kontrol af kontrast efter hver.
4. **Spacing og komponenter.** 4 px-grid, knapper, produktkort, fokusmarkering.
5. **Billedstandarden.** Kræver nye fotos; kan først gøres ordentligt, når kataloget er kurateret.

Hvert trin skal kunne måles bagefter. Et trin uden en måling er et trin, vi ikke ved om virkede.

---

## 12. Det dokumentet ikke dækker

- Selve CSS-filen. Den skal skrives som `css/`-del eller som temaets override, og den findes ikke endnu.
- Navigationens informationsarkitektur — hører i katalog- og taksonomiplanen.
- Konvertering. Dette er identitet, ikke salgsoptimering; de to skal måles hver for sig.

---

*Målt 5/11-2026. Ret dokumentet, når CSS'en er lagt ind — ikke før, ellers beskriver det en intention i stedet for en virkelighed.*
