# Mobil, menu og årstidsfarver — målt 8/11 2026

**Kort:** Sitet var bygget til desktop og faldt fra hinanden på en telefon: headeren fyldte 337 px
af en 538 px skærm, indholdet var klemt ned i en 198 px kolonne, menuen var et halvt panel med en
guldbjælke som lukkeknap, og tre ting fik siden til at «ryste». Alt er nu målt, rettet og set i en
browser på 320 px. CSS'en ligger som et selvstændigt lag, så intet af det gamle er slettet.

## 1. Hvad der blev målt før (320 px viewport, 8/11 2026)

| Måling | Før | Efter |
| --- | --- | --- |
| Header-højde | **337 px** (63 % af skærmen) | **73 px**, sticky |
| Indholdskolonne | **198 px** (dobbelt indsnævring: `main` 20 px + wrap 24 px) | **278 px** |
| Forsidens højde | **11 120 px** | **8 574 px** (−23 %) |
| Menuen | halvt panel, 320 px bredt med hvid kant, lukkeknap = 63 px guldbjælke | fuldskærms-ark, 44 px rund lukkeknap, 52 px rækker |
| Produktgalleri | tom bjælke over fotoet | foto i 4:5-ramme, ingen klipping |
| Vandret scroll | `body{overflow-x:hidden}` skjulte problemet | **0** elementer uden for viewport, `scrollWidth` = 310 |
| Paneler | bordeaux `#301e20 → #140e10` overalt | årstidens grønne flade |
| Valuta/indhold | uændret | uændret |

## 2. De tre rystekilder

1. **AI-boblen** (`.mwai-icon-container`) kørte `animation: mwai-glass-iris 10s` i evig løkke. En
   animation der aldrig stopper tvinger en repaint af det faste element hele tiden — på en telefon
   ser det ud som om hele siden vibrerer. Slået fra under 783 px; boblen beholder sin skygge.
2. **`backdrop-filter` på en sticky header på 337 px.** Blur beregnes på ny ved hver scroll-frame
   over et stort område. Headeren er nu en uigennemsigtig flade uden blur.
3. **`transition: all`** på grupper, navigation og links animerede også layout-egenskaber. Erstattet
   med en eksplicit liste: farve, baggrund, kant, opacity, skygge og transform.

`@media (prefers-reduced-motion: reduce)` slår stadig alt fra for brugere der beder om det.

## 3. Headermenuen

Den gamle header var tre indlejrede grupper med hver sin messingramme, og en `::after` på den ydre
gruppe malede en **63 px guldgradient** hen over bjælken — det var «guldstangen» under logoet. Nu:

- én række: logo (30 px mærke + ordmærke) · burger · konto · kurv, alle med 44 px trykflade
- `position: sticky; top: 0` med hårlinje i bund og sikkerhed for `env(safe-area-inset-top)`
- menuen er et fuldskærms-ark (`100vw × 100dvh`) i årstidens bundfarve, med lukkeknap som 44 px
  cirkel, 52 px rækker, hårlinje mellem punkterne, undermenu med messingstreg til venstre
- `body:has(.is-menu-open)` låser siden bag menuen, og AI-boblen skjules mens menuen er åben
- fokusringen ligger indeni rækken (`outline-offset: -3px`) i stedet for at tegne en kæmpe boks

## 4. Årstidens farver

`mu-plugins/sga-season.php` sætter `data-season` på `<html>` ud fra måneden i dansk tid — og CSS'en
har fire komplette paletter:

| Sæson | Måneder | Accent | Flade |
| --- | --- | --- | --- |
| `winter` | dec–feb | frost `#8fb7bd` | `#0b1013` |
| `spring` | mar–maj | ung gran `#8fae7a` | `#0d130f` |
| `summer` | jun–aug | sol `#d9a24b` | `#0f120d` |
| `autumn` | sep–nov | rust `#b4643a` | `#0e120e` |

Basis er den samme mørke atelier-flade hele året; det der skifter er messing, accent og glød, så
brandet ikke hopper. Temaets blok-farver (`--wp--preset--color--*`) peger på de samme tokens, så
blokke, knapper og Woo-elementer følger med. Vælges en anden sæson midlertidigt, kan det gøres med
filteret `sga_season` uden at røre CSS'en.

I dag (8/11) står der `autumn`. Vinterpaletten er set i browseren ved at sætte attributten manuelt:
`--sg-bg` bliver `#0b1013`, accent `#8fb7bd`, messing `#cfae66`.

## 5. Filer

| Fil | Rolle |
| --- | --- |
| `css/sg-season.css` | hele laget: tokens, sæsoner, header, menu, indhold, mobil, tilgængelighed (33 KB) |
| `mu-plugins/sga-season.php` | sætter `data-season`, printer CSS'en sidst i footeren, og svarer på `/wp-json/sga/v1/season` |

Live-kopien ligger i `wp-content/mu-plugins/sga-season.css` + `.php`. CSS'en læses fra filen ved hvert
request (ingen build), og `data-no-minify` holder W3TC fra at trække den ind i en samlet fil, hvor den
ville stå **før** den CSS den skal rette — det var den første fejl: tokens og `body` tabte, mens
header og hero vandt. Løsningen er både attributten og `html:root`/`html body` som vægt.

## 6. Sådan blev det verificeret

Målt i cloud-browseren på 320 px (telefonbredde) med `getBoundingClientRect` og `getComputedStyle`,
side for side:

- **forside**: header 73 px, `position: sticky`, side 8 574 px, 0 elementer uden for viewport
- **butik** (`/shop/`): ingen ramme, værktøjslinjen er et panel, 0 overflow
- **produkt** (`/product/airship-armada-tee/`): ingen bordeaux kasse, brødkrumme på tre korte linjer,
  foto i 4:5-ramme
- **menu**: åbner/lukker, fuld bredde, ingen vandret scroll, `body` låst bag arket
- **sæson**: `data-season="autumn"` i HTML'en, `/wp-json/sga/v1/season` svarer `autumn` + 33 KB CSS
- **ryst**: `.mwai-icon-container` har `animation-name: none` ved 320 px

Pixels er også prøvet af i PNG'en: header `rgb(21,29,22)`, indhold `rgb(14,18,14)` — altså den
grønne sæsonflade og ikke den gamle bordeaux.

## 7. Hvad der ikke er gjort

- **Desktop er ikke set visuelt.** Cloud-browserens viewport er 320 px, og Playwright kunne ikke sætte
  en anden størrelse i denne kørsel. Desktop-reglerne er derfor konservative: navigationen får kun
  små versaler og understregning ved ≥ 783 px, og hover-løft er låst til `(hover: hover)`. Skal det
  godkendes, kræver det et blik i en bred browser.
- **Produktgalleriet** står i en 4:5-ramme med fotoet centreret. Det ser ud som en indrammet plakat og
  er valgt frem for at klippe motivet; vil man have rammen helt væk, skal Woo-blokkens
  `--wc-block-product-gallery-large-image-ratio-*`-variabler nulstilles.
- **Farvevalget** er taget ud fra «årstidens farver» i november (efterår): rust og messing på mørk
  gran. Vinterpaletten ligger klar og slår selv ind 1. december.
