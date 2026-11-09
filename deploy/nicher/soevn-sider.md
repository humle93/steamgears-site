# IMMER — fem sider, klar til at klistre ind

Kilde: `deploy/nicher/soevn.json`. Siderne er kladder; hvert `[EJER: …]` skal erstattes af en oplysning, ejeren har givet, før siden må publiceres.

## Start her

Slug: `/start-here/`

<p><strong>IMMER</strong> — Aftenen, der slutter ordentligt.</p><p>Butikken sælger søvn og aftenro til danske voksne, der sover daarligt og har prøvet melatonin, men ikke en tyngdedyne.</p><h2>Begynd med</h2><ol><li><strong>Tyngde</strong> — Dyner og tæpper med dokumenteret vægt.</li><li><strong>Tyngdedyne</strong> — Seks kilo glasperler, der fordeler sig efter kroppen.</li><li><strong>Levering og retur</strong> — hvad der kommer hvornår.</li></ol><p>[EJER: det ene, butikken gør anderledes, i en sætning ejeren selv ville sige.]</p>

## Om os

Slug: `/om-os/`

<p>IMMER er en lille dansk butik med søvn og aftenro. Aftenen, der slutter ordentligt.</p><p>[EJER: registreret firmanavn, CVR, adresse og telefon — kræves i handelsbetingelserne og i GPSR-blokken.]</p><p>[EJER: hvem står bag, og hvordan svarer man.]</p>

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
| Tyngde | `tyngde` | Dyner og tæpper med dokumenteret vægt. |
| Moerke | `moerke` | Masker, der lukker lyset ude uden at trykke. |
| Natlyd | `natlyd` | Hvid støj og faldende toner. |
| Aften | `aften` | Rutiner og småting til de sidste 30 minutter. |
| Natpakker | `natpakker` | Soevnpakker til hjemme og til rejse. |

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
2. E2: leverandør med GPSR-dokumentation, vægtklasse og vasketest
3. E3: registrering i producentregistret for emballage
4. E4: domæne og Shopify-konto
5. E5: beslut om Q4-start, hvor CPM er 30-60 % højere
6. E6: annoncekonto med betalingsmetode

## Annoncehooks og KPI-gates

- Hook: Jeg har prøvet melatonin. Det her virker på en anden måde.
- Hook: Din søvn starter 30 minutter før, du går i seng.
- Hook: Seks kilo fordelt rigtigt slukker ikke kroppen - den holder den.
- Hook rate: >= 25 %
- Konvertering: >= 1,0 %
- CPA: <= break-even pr. vare
- Retur: <= 10 %
