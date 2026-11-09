# OPLOFT — fem sider, klar til at klistre ind

Kilde: `deploy/nicher/lejebolig.json`. Siderne er kladder; hvert `[EJER: …]` skal erstattes af en oplysning, ejeren har givet, før siden må publiceres.

## Start her

Slug: `/start-here/`

<p><strong>OPLOFT</strong> — Opbevaring, der kan flytte med.</p><p>Butikken sælger lejebolig-venlig opbevaring til lejere i danske byer, der vil have opbevaring uden at bore i en bolig, de skal flytte fra.</p><h2>Begynd med</h2><ol><li><strong>Reolsystemer</strong> — Gulv-til-loft uden værktøj og uden huller.</li><li><strong>Teleskopreol, gulv til loft</strong> — Spaendes fast mellem gulv og loft uden skruer.</li><li><strong>Levering og retur</strong> — hvad der kommer hvornår.</li></ol><p>[EJER: det ene, butikken gør anderledes, i en sætning ejeren selv ville sige.]</p>

## Om os

Slug: `/om-os/`

<p>OPLOFT er en lille dansk butik med lejebolig-venlig opbevaring. Opbevaring, der kan flytte med.</p><p>[EJER: registreret firmanavn, CVR, adresse og telefon — kræves i handelsbetingelserne og i GPSR-blokken.]</p><p>[EJER: hvem står bag, og hvordan svarer man.]</p>

## Levering og retur

Slug: `/levering-og-retur/`

<p>Alle ordrer sendes fra lager i Danmark.</p><ul><li>Behandlingstid: [EJER: dage].</li><li>Levering: [EJER: zoner og dage].</li><li>Fragtpris: [EJER: pris pr. zone, fri fragt over X kr.].</li></ul><h2>Fortrydelsesret</h2><p>Du har 14 dage fra modtagelsen. [EJER: returadresse og hvem der betaler returfragten.]</p><p>Brug fortrydelsesformularen på <a href="/fortrydelse/">Fortrydelse</a>.</p>

## Mål og pasform

Slug: `/maal-og-pasform/`

<p>Målene står på produktsiden. Mål det sted, varen skal sidde, inden du bestiller.</p><ul><li>[EJER: mål og tolerancer pr. vare, målt på en prøve.]</li><li>[EJER: maksimal belastning, hvis varen bærer noget.]</li><li>[EJER: hvad der ikke er egnet — underlag, vægt, temperatur.]</li></ul>

## Kontakt

Slug: `/kontakt/`

<p>Spørgsmål om en ordre, en størrelse eller en retur?</p><p>E-mail: [EJER: butikkens e-mail] · Telefon: [EJER: telefon] · Adresse: [EJER: adresse].</p><p>Vi svarer inden for [EJER: timer].</p>

## Kollektioner, der skal oprettes

| Navn | Slug | Beskrivelse |
| --- | --- | --- |
| Reolsystemer | `reolsystemer` | Gulv-til-loft uden værktøj og uden huller. |
| Doersystemer | `doersystemer` | Hylder og kroge, der hager over døren. |
| Vaegopbevaring | `vaegopbevaring` | Klæbende hylder med dokumenteret belastning. |
| Moduler | `moduler` | Kasser og indsatser, der kan stables om. |
| Systempakker | `systempakker` | Hele løsninger til entre, bad og soveværelse. |

## Ejeri punkter, der blokerer publicering

- `[EJER: adresse]`
- `[EJER: butikkens e-mail]`
- `[EJER: dage]`
- `[EJER: det ene, butikken gør anderledes, i en sætning ejeren selv ville sige.]`
- `[EJER: hvad der ikke er egnet — underlag, vægt, temperatur.]`
- `[EJER: hvem står bag, og hvordan svarer man.]`
- `[EJER: maksimal belastning, hvis varen bærer noget.]`
- `[EJER: mål og tolerancer pr. vare, målt på en prøve.]`
- `[EJER: pris pr. zone, fri fragt over X kr.]`
- `[EJER: registreret firmanavn, CVR, adresse og telefon — kræves i handelsbetingelserne og i GPSR-blokken.]`
- `[EJER: returadresse og hvem der betaler returfragten.]`
- `[EJER: telefon]`
- `[EJER: timer]`
- `[EJER: zoner og dage]`

## Ejerhandlinger

1. E1: CVR, telefon, adresse og bankaftale
2. E2: leverandørtilbud på reol og dørsystem, med TARIC og fragt til DK
3. E3: registrering i producentregistret for emballage
4. E4: domæne og Shopify-konto
5. E5: pristest mod IKEA's 585 kr. før lagerkoeb
6. E6: annoncekonto med betalingsmetode

## Annoncehooks og KPI-gates

- Hook: Du må ikke bore. Du har stadig brug for plads.
- Hook: Saadan faar du plads uden at miste depositummet.
- Hook: Tre kvadratmeter du ikke vidste du havde.
- Konvertering ved 300 besoeg: >= 2,0 % - ellers er prisen eller siden problemet
- CPA: <= break-even pr. vare
- Retur: <= 10 %
- Klage over hold: 0 dokumenterede sager
