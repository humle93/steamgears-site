# Designsystem for hele sitet — 7/11 2026

Efter plugin-UI'et fik sit eget lag med én kilde til sandheden, blev det samme gjort for **sitet**:
`css/sg-design-system.css` er skrevet om fra en ubrugt fil til det lag der bestemmer typografi, farver,
knapper, formularer og butikkens komponenter — og et nyt mu-plugin lægger det på siden sidst, så det
vinder over temaet uden at slås med det. Dokumentet her er beslutningerne, de målte kontraster, den fejl
der blev fundet undervejs, og hvad der **ikke** er verificeret.

## 1. Problemet, som det stod

Sitet har ingen samlet CSS-fil. Den visuelle flade ligger i to **base64-kodede template-parts** i repoet:

| Fil | Base64 | HTML efter afkodning | Indhold |
| --- | --- | --- | --- |
| `css/header-dark-clockwork-active.html` | 67.164 B | 50.348 B / 1.383 linjer | 2 `<style>`-blokke, Clarity-script, header-markup |
| `css/footer-active.html` | 5.524 B | 4.136 B / 121 linjer | footer-markup og -styles |

Dertil kommer blok-temaets globale styles og omkring **300 inline-regler** hist og her, mange med
`!important`, fordi hver rettelse hidtil er blevet et nyt lag oven på det forrige. `css/sg-design-system.css`
fandtes allerede — 275 linjer — men blev **aldrig indlæst** af noget. Den var altså en fil med gode
hensigter og ingen virkning.

## 2. Beslutningen: ét lag, indlæst sidst

To ting blev besluttet, og den anden er den vigtige:

1. **Filen er kilden.** Alt hvad der hedder skrift, farve, afstand, knap, formular og butikskomponent står
   ét sted. Nye regler lægges der, ikke i template-parts.
2. **Rækkefølge slår specificitet.** Header-template-parten printer temaets `<style>` i `<head>`, og
   blok-temaet printer sine globale styles efter den. Alt i footeren kommer efter begge — så et nyt
   mu-plugin printer CSS'en i `wp_footer` (prioritet 999) og vinder uden `!important`. Det er hele
   grunden til at loaderen findes: en fil der ikke indlæses sidst, ville skulle kæmpe med temaet i stedet
   for at ligge ovenpå det.

## 3. Farverne: den mørke palette blev beholdt

`design.md` §3 beskriver en lys pergament-palette. Den er **ikke** slået til. Grunden er ikke smag men
risiko: paletten kan ikke ses i en browser fra denne session, og temaets ~300 inline-regler er skrevet mod
den mørke flade. Et halvt paletteskift ville give en hybrid — lyse flader med mørke komponenter og
kontraster der ikke er målt. Derfor er laget bygget i den identitet butikken allerede har (blek, messing,
creme, vin), og pergament-udgaven ligger nederst i filen som `body.sg-theme--parchment`: en **fravalgt
variant**, ikke en glemt mulighed. Den kan slås til med én klasse på `body` den dag nogen har set den i en
browser og målt den.

Seks farveroller bærer alt; der er ingen syvende:

| Token | Værdi | Rolle |
| --- | --- | --- |
| `--sg-ink` | `#14110E` | bund |
| `--sg-surface` | `#1B1510` | panel/kort |
| `--sg-on-dark` | `#F2EAD9` | tekst |
| `--sg-brass` | `#B08D57` | flade og linje (ikke tekst på mørkt) |
| `--sg-brass-text` | `#D8B87A` | messing som **tekst** |
| `--sg-wine` | `#6B2B2B` | accent |

At messing har to tokens er ikke dovenskab: `#B08D57` som tekst på blek måler 6,1:1 og er i orden, men
den lysere `#D8B87A` måler 9,9:1 og bliver brugt hvor teksten er lille. Forskellen er målt, ikke gættet.

## 4. Kontrasterne — målt, ikke ønsket

Alle tal er regnet med WCAG 2.2's formel (relativ luminans, `(L1+0,05)/(L2+0,05)`) af
`tools/design_check.py`, som også er det værn der holder dem på plads bagefter.

| Kombination | Målt | Krav | Status |
| --- | --- | --- | --- |
| creme på blek | 15,72:1 | 4,5 | ✓ |
| creme på panel | 15,11:1 | 4,5 | ✓ |
| messing-tekst på panel | 9,52:1 | 4,5 | ✓ |
| messing-tekst på blek | 9,91:1 | 4,5 | ✓ |
| dæmpet tekst på panel | 9,38:1 | 4,5 | ✓ |
| blek på messing-knap | 6,09:1 | 4,5 | ✓ |
| messing-linje på blek | 6,09:1 | 3,0 | ✓ |
| fokusring på blek | 12,73:1 | 3,0 | ✓ |
| **fokusring på messing-knap** | **2,09:1** | 3,0 | ✗ — se §5 |

## 5. Fejlen undervejs: fokusringen forsvandt på knapperne

Den første udgave satte én fokusring: `outline: 2px solid #F0D27C`. Den måler 12,7:1 på blek og ser
rigtig ud — indtil man tabber til en messing-knap. Ringens farve mod messing-fladen måler **2,09:1**, altså
under WCAG's 3:1 for ikke-tekst, og ringen er praktisk talt usynlig netop der hvor brugeren er på vej hen.

Rettelsen er en **dobbelt ring**: mørk inderring på messing-flader, lys ring på alt andet.

```
:where(...):focus-visible        { outline: 2px solid var(--sg-focus); outline-offset: 2px; }
:where(.button, button, …):focus-visible { outline-color: var(--sg-focus-on-brass); }
```

Fælden er værd at huske: en fokusring skal måles mod **hver** flade den kan lande på, ikke kun mod sidens
bund. Værnet kontrollerer nu begge par — og at det svage par stadig er 2,09:1, så en «retning» af
messing-farven ikke kan gøre ringen fin på papiret og usynlig i praksis.

## 6. Hvad laget indeholder

| Afsnit | Indhold |
| --- | --- |
| 1 · tokens | 42 variable: 6 farveroller, afledte gennemsigtigheder, 7 typografitrin, 4 px-afstandsskala, radius, tap-mål, fokus |
| 2 · typografi | `clamp()`-skala fra 0,75 rem til 2,75 rem, mål på 68 tegn, Marcellus til overskrifter og IBM Plex Sans til tekst |
| 3 · indhold | brødtekst, lede, citat, lister, links med understregning (ikke kun farve), `hr`, figurer |
| 4 · komponenter | kort, knapper (primær/sekundær/outline), formularfelter med synlig fokus, noter, Woo-beskeder |
| 5 · header/nav | sticky header, logo, aktiv-markering der **ikke** er farvebaseret (understregning + `aria-current`), 44 px tap-mål, skip-link |
| 6 · WooCommerce | produktliste som grid, produktbillede, titel/pris, udsalgsmærke, produktkolonner, mængde + køb-knap, faner, kurv- og kassetabel, betalingsboks |
| 7 · tilstande | `:focus-visible` på alt interaktivt, `prefers-contrast: more`, `forced-colors`, reflow ved 900/782/480 px, `prefers-reduced-motion`, print |

Butiksdelene følger Baymards målinger for produktlister og produktsider: produktlisten skal have visuelt
adskilte felter (billede, titel, pris, handling), og køb-knappen skal være entydig på produktsiden — derfor
`flex: 1 1 220px` på køb-knappen ved siden af mængdefeltet, ikke en løs knap et tilfældigt sted.

## 7. Loaderen — `wordpress/mu-plugins/sga-design-system.php`

| Egenskab | Hvordan |
| --- | --- |
| Rækkefølge | `add_action( 'wp_footer', …, 999 )` — efter temaets `<head>`-styles og blokkens globale styles |
| Kilde | `SGA_DESIGN_CSS` → `uploads/sga-design/` → samme mappe som mu-plugin'et → repoets `css/` |
| Afgrænsning | `body_class` tilføjer `sg-ui`, så alt i filen er scopet til `body.sg-ui` |
| Kill-switch | `add_filter( 'sga_design_enabled', '__return_false' )` — eller slet filen |
| Værn | `if ( ! defined( 'ABSPATH' ) ) exit;` |
| Fail-open | findes filen ikke, printes en HTML-kommentar i stedet for CSS; siden er **uændret**, ikke ustylet |
| Sikkerhed | en fil med `</style` eller `<?` printes ikke inline (den kan ikke lukke blokken uden at bryde siden) |
| Caching | indholdet læses én gang pr. request (`static`), ikke pr. kald |

Valget af fail-open er bevidst: et manglende filsystem på et webhotel må ikke koste forsiden. En loader der
kaster en fejl, er en loader der tager butikken ned for at pynte den.

## 8. Værnet — `tools/design_check.py` og en PHP-harness

CSS fejler i stilhed, så filen har sit eget værn med **12 fixtures**:

| Kontrol | Hvad den fanger |
| --- | --- |
| klammertælling | en manglende `}` dræber resten af arket — 1 fixture |
| fokus | `outline: none` uden erstatning, og at `:focus-visible` findes — 2 fixtures |
| flader | 10 påkrævede selektorer: fokus, produktliste, køb-knap, skip-link, reflow, reduced-motion, forced-colors, print, `body.sg-ui` — 1 fixture |
| kontrast | 9 par: både WCAG-kravet og det dokumenterede tal, så en «uskyldig» farveretning fanges — 2 fixtures |
| variabler | hver `var(--sg-…)` skal være defineret, ellers falder reglen til ingenting — 1 fixture |
| uafhængighed | ingen `@import`, ingen ekstern `url(http…)` — 2 fixtures |
| `!important` | loft på 30; derover slås laget med temaet i stedet for at ligge ovenpå — 1 fixture |
| loaderen | `ABSPATH`, kill-switch, `body_class`, `wp_footer`, filnavn — 1 fixture |
| selve kontrollen | et gyldigt fixture skal **godkendes**, ellers er værnets dom bare en mur der altid er rød — 1 fixture |

Derudover prøver `wordpress/tests/test-design-system.php` loaderen uden en WordPress-installation, med syv
stub-funktioner i stedet for en hel installation: at footeren faktisk får et `<style id="sga-design-system">`
med tokens og regler (over 20.000 tegn, altså ikke en tom blok), at body får `sg-ui`, at kill-switchen fjerner
både klasse og stilblok, at der kun er én `</style>`, og at en manglende fil giver kommentaren i stedet for
CSS — det sidste i en kopi af loaderen, hvor der hverken ligger `css/` eller uploads ved siden af.

Begge er nu værn i `tools/natlige-vaern.sh` (21 i alt), og `php -l` dækker for første gang også
`wordpress/`-mappen.

## 9. Hvad der ikke er verificeret

Dette er den vigtige del, fordi den er let at overse i en rapport der ellers er grøn:

* **Laget er ikke på det levende site.** Denne session har ingen WordPress-adgang: `connect_integration`
  svarede at WordPress ikke er tilsluttet, og der er ingen MCP-credential. CSS'en er altså ikke indlæst på
  steamgears.eu, og intet af det ovenstående er set på den rigtige side.
* **Intet er set i en browser.** Kontrasterne er regnet, ikke kigget på. Reflow, sticky header og
  produktgrid er skrevet efter mål og specifikation, ikke efter en visuel kontrol.
* **Testet er logikken, ikke udseendet.** Værnet beviser at filen er konsistent og at loaderen printer den
  som aftalt; det beviser ikke at temaet ikke har en regel der stadig vinder.

Sådan lægges det på, når der er adgang:

1. Kopiér `css/sg-design-system.css` til `uploads/sga-design/` (eller ind i `mu-plugins/` ved siden af
   loaderen).
2. Kopiér `wordpress/mu-plugins/sga-design-system.php` til `wp-content/mu-plugins/`.
3. Åbn forsiden, en produktliste, en produktside og kurven. Kig efter: fokusring på messing-knapper,
   produktgrid uden overflow ved 390 px, og at header ikke hopper.
4. Går noget galt, så slå fra med `add_filter( 'sga_design_enabled', '__return_false' );` — eller slet
   mu-plugin-filen. Siden er da som før.

## 10. Procenter

| Modul | Før | Efter | Δ | Grundlag |
| --- | --- | --- | --- | --- |
| S2.9 designsystem ind i siden | 0 % | 80 % | +80 | 0,4 af 0,5 d — fil, loader, værn og 21 grønne kørsler er verificeret; de sidste 0,1 d er live-indlæsning og visuel kontrol, som kræver adgang |
| S2 — struktur og SEO | 92,1 % | 91,3 % | −0,8 | 7,35 af 8,05 d (ny nævner: S2.9 lægger 0,5 d til) |
| Site-projektet | 63,7 % | 64,0 % | +0,3 | 15,55 af 24,3 d (S0 2,15/2,85 · S1 2,15/4,6 · S2 7,35/8,05 · S3 2,5/3,55 · S4 1,4/5,25) |

S2's procent **falder**, selv om der blev arbejdet: modulet fik en ny opgave på 0,5 d, hvoraf 0,4 d er
udført. Det er en scope-ændring, ikke tilbagegang, og den står som sådan i `projektstatus.json`.
