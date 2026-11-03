# Punk — upload klar: 31 filer, to destinationer

Alt der kan forberedes uden billederne er nu forberedt: filnavne, alt-tekster, trykkrav, medie-upload til WordPress og produktkladserne. Det der mangler er filerne — og Printify-delen, som ingen MCP dækker.

Skrevet 2/11 2026. Bygger på [punk-lancering-2026-11-02.md](punk-lancering-2026-11-02.md) (teksterne og mockup-planen) og [mockup-brief-2026-11-01.md](mockup-brief-2026-11-01.md) (billedreglerne).

## De 31 filer

| Design | Trykfil (Printify) | Krav | Mockups (WordPress) |
|---|---|---|---|
| T1 Coil No. 1 | `assets/punk/t1/print-master.png` | 28 cm bred → **3307 px**, transp. baggrund | 4 |
| D1 Zeppelin Line No. 9 | `assets/punk/d1/print-master.png` | 50 × 70 cm → **5906 × 8268 px** | 4 |
| C5 Keymaker's Hands | `assets/punk/c5/print-master.png` | 28 cm bred → **3307 px**, transp. baggrund | 4 |
| T5 Static Saint | `assets/punk/t5/print-master.png` | 30 cm bred → **3543 px**, transp. baggrund | 5 |
| D2 Riveter's Prayer | `assets/punk/d2/print-master.png` | 28 cm bred → **3307 px**, transp. baggrund | 4 |
| C1 The Escapement | `assets/punk/c1/print-master.png` | 50 × 70 cm → **5906 × 8268 px** | 4 |

Pixel-tallene er ikke valgte — de er de solgte mål gange 300 DPI. Plakaterne har 50 × 70 som master, fordi den mindste størrelse kan skaleres ned, men ikke op.

**De to destinationer må ikke blandes sammen.** Trykfilen går til Printify og bliver aldrig et produktbillede. Mockups går til WordPress' mediebibliotek og bliver galleriet, i den rækkefølge kataloget angiver — 01 er altid det rene produktmockup, som på de 39 eksisterende.

Filnavne, alt-tekster og rækkefølge står i [punk-catalogue.json](../../deploy/steamgears/punk-catalogue.json); de er ikke frie at ændre, fordi de er nøglerne i upload-flowet.

## Rækkefølge, når filerne lander

```bash
python3 tools/deploy/check_assets.py          # hvad er forkert, hvad mangler
python3 tools/deploy/build_punk.py            # medie-trin for de filer der er der
python3 tools/deploy/deploy.py run --manifest punk-manifest.json --only media-c1-01 --apply
python3 tools/deploy/build_punk.py            # produkttrinnene får nu images
```

`check_assets.py` fejler på en fil der er **forkert** (for lille, mangler gennemsigtighed, ikke JPG) og er tavs om en fil der **mangler**. Det er med vilje: tomme pladser er arbejdsplanen, ikke en fejl. `--strict` vender det om og fejler, indtil alle 31 er der.

## Det der stadig er manuelt

**Printify.** Der findes ingen MCP til Printify, så produktopsætningen dér kan ikke forberedes herfra. Den består af blueprint, print provider, printområde, farver og størrelser pr. design — og den skal spejle WooCommerce, ellers kan en sync overskrive teksterne (samme uafklarede risiko som på de 39). Tallene til opsætningen er de samme som i tabellen ovenfor.

**Fire ting kan ikke tjekkes maskinelt** og skal ses med øjnene:

1. Ingen hvid baggrund i mockups.
2. Læsbar ved 200 px — aftrykket i shop-grid, hvor de fleste først møder motivet.
3. DTG-linjeminimum 0,8 mm i den solgte størrelse, ikke i skærmstørrelse.
4. Ingen tekst i motivet. Typografien lægges på i layout, aldrig i prompten.

## Ændret undervejs

Mockup-alt-teksterne for de to indrammede plakater sagde "A3" og "A2" i briefen. Det er rammeformater, ikke de mål butikken sælger, og på et billede af en indrammet plakat ville det være den forkerte oplysning. De siger nu "in a black frame" og "in a wooden frame"; målene står i stedet i `04-formats.jpg`, hvor de hører til.

## Klar / ikke klar

**Klar:** filstruktur, filnavne, alt-tekster, trykkrav med udregnede pixelmål, validering, medie-upload med id-capture, tre kollektioner, seks produktkladser med tekst og SEO.

**Ikke klar, og ikke noget jeg kan gøre ved:** de fem faste tekstblokke (hentes fra produkt 563/405), priser (venter på valutabeslutningen), GPSR-adressen (mangler CVR og adresse), trykprøven i 30 cm, og selve motiverne.

## Før noget går live

- [ ] 6/6 trykfiler består `check_assets.py`
- [ ] 25/25 mockups består, og billede 01 er rent produktmockup på alle seks
- [ ] Printprøve i 30 cm for alle seks — linjerne holder, ingen udfald i mørke partier
- [ ] Tekstblokkene er hentet, ikke genopført: GPSR på de nye må ikke være svagere end på de 39
- [ ] Priser sat efter valutabeslutningen, ikke før
- [ ] De seks produktsider og de tre kollektionssider svarer 200, W3TC purget
