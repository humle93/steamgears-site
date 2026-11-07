# design.md — Steamgears' visuelle identitet

**Status:** specifikation. Målt på den live side 5/11-2026. CSS'en ligger i [`css/sg-design-system.css`](css/sg-design-system.css) og lægges på siden af `wordpress/mu-plugins/sga-design-system.php` — laget er skrevet og værnet 7/11, men **ikke indlæst på sitet endnu**, se §14. Skrifterne i header-delen er skiftet 6/11 — se §13.
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

Fase 3 har en fælde, som CSS-filen dokumenterer øverst: temaets header erklærer allerede `--sg-brass: #c9963f`, og den værdi bærer tekst i overskrifterne i dag. Skal navnet skifte betydning til `#B08D57` (messing der *ikke* må bære tekst), bliver alle messing-kanter mørkere på hele sitet. Det er hensigten, men det skal ske sammen med font-oprydningen, ikke som et selvstændigt indgreb.

Hvert trin skal kunne måles bagefter. Et trin uden en måling er et trin, vi ikke ved om virkede.

---

## 12. Det dokumentet ikke dækker

- Selve CSS-filen er skrevet og ligger som [`css/sg-design-system.css`](css/sg-design-system.css). Den er **ikke indlæst**: `css/` indeholder template-dele, så den skal enqueues eller indsættes som dem — og indtil det sker, er der intet ændret på den live side.
- Navigationens informationsarkitektur — hører i katalog- og taksonomiplanen.
- Konvertering. Dette er identitet, ikke salgsoptimering; de to skal måles hver for sig.

---

*Målt 5/11-2026. Ret dokumentet, når CSS'en er lagt ind — ikke før, ellers beskriver det en intention i stedet for en virkelighed.*


---

## 13. Udført 6/11-2026 — typografien

Skiftet i §2 er gennemført i `css/header-dark-clockwork-active.html`.

| | Før | Efter |
| --- | --- | --- |
| `@font-face`-erklæringer | 33 | 5 |
| Overskrifter, ordmærke, produkttitler | Georgia 700 | Marcellus 400 |
| Brødtekst (blev tvunget med `!important`) | Georgia | IBM Plex Sans |
| Knapper | Georgia 800 | IBM Plex Sans 700 |
| Preload af Inter i `<head>` | 1 fil ved hver sidevisning | fjernet |
| Filstørrelse | 50 819 bytes | 44 403 bytes |

Beholdt: Marcellus (400) og IBM Plex Sans (400, 500, 600, 700). Fjernet: Archivo, Arsenal,
Baloo Tamma 2, Geom, Gotu, Heebo, Hind (×2), Inconsolata, Inter, Jost, Karla, Koulen, Manrope,
Montserrat, Mulish, Noto Serif Display, Oswald, Radio Canada, Roboto Flex, Rubik,
Source Serif Pro, Space Mono (×2) samt Plex 100/200/300.

Georgia står tilbage som reserve i serif-stakken. Den findes på alle enheder, bliver ikke
hentet, og sikrer at overskrifterne ikke falder til en systemfont, hvis Marcellus svigter.

Overskrifterne er sat til vægt 400, fordi Marcellus kun findes i 400. En kunstig 700 ville
sløre stavelserne — og på et display-ansigt er det tydeligt.

**Verificeret:** filen er byte-identisk med den lokalt gennemgåede version (sha256-kontrol i
arbejdsgangen), klammer 333/333 → 305/305, `@font-face` 33 → 5.
**Ikke verificeret:** hvordan det ser ud på den live side. Header-delen skal stadig ind i
WordPress, før en besøgende ser forskellen.

---

## 14. Udført 7/11-2026 — designsystemet som ét lag

`css/sg-design-system.css` er skrevet om fra 275 ubrugte linjer til det lag der bestemmer hele sidens
overflade, og `wordpress/mu-plugins/sga-design-system.php` lægger det på siden.

**Rækkefølge slår specificitet.** Temaet printer sine `<style>`-blokke i header-template-parten, og
blok-temaet printer sine globale styles efter dem. Loaderen printer i `wp_footer` (prioritet 999), altså
efter begge, og vinder derfor uden `!important`. Det er hele grunden til at der er en loader: uden den
skulle filen slås med ~300 inline-regler i stedet for at ligge ovenpå dem.

**Paletten blev den mørke, ikke pergament.** §3's lyse palette er ikke slået til: den er ikke set i en
browser, og temaets inline-regler er skrevet mod den mørke flade, så et halvt skift ville give en hybrid
med umålte kontraster. Pergament-udgaven ligger nederst i CSS-filen som `body.sg-theme--parchment` og kan
slås til med én klasse, når nogen har set og målt den. Seks farveroller bærer resten.

**Målte kontraster** (WCAG 2.2, `tools/design_check.py`): creme på blek 15,72:1 · creme på panel 15,11:1 ·
messing-tekst på panel 9,52:1 · messing-tekst på blek 9,91:1 · dæmpet tekst 9,38:1 · blek på
messing-knap 6,09:1 · messing-linje 6,09:1 · fokusring på blek 12,73:1. Fokusringen på en messing-knap
måler kun **2,09:1**, så ringen er dobbelt: mørk inderring på messing-flader, lys ring på alt andet.

| | Før | Efter |
| --- | --- | --- |
| Fil | 275 linjer, indlæst af intet | 778 linjer, printet sidst på siden |
| Farveroller | spredt i temaet | 6 tokens med 42 afledte variable |
| Typografi | 7 trin, ikke brugt | 7 `clamp()`-trin, mål 68 tegn |
| Fokusring | temaets standard | dobbelt ring, målt på begge flader |
| Butik | Woo's egne styles | liste, produkt, kurv, kasse, betaling |
| Tilstande | ingen | `prefers-contrast`, `forced-colors`, reduced motion, print, reflow 900/782/480 px |
| Værn | ingen | `tools/design_check.py` (12 fixtures) + PHP-harness (9 prøver) |

**Verificeret:** `php -l` rent, 21/21 værn grønne, loaderen printer 778 linjers CSS i footeren, `body`
får `sg-ui`, kill-switchen fjerner begge dele, og en manglende fil giver en kommentar i stedet for CSS.
**Ikke verificeret:** laget er ikke på det levende site (ingen WordPress-adgang i sessionen) og er ikke
set i en browser. Hvordan det lægges på, og hvad der skal kigges efter, står i
[`library/designsystem-hele-siden-2026-11-07.md`](docs/library/designsystem-hele-siden-2026-11-07.md) §9.
