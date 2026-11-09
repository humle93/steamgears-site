# KABINE — fem sider, klar til at klistre ind

Kilde: `deploy/nicher/kabine.json`. Siderne er kladder; hvert `[EJER: …]` skal erstattes af en oplysning, ejeren har givet, før siden må publiceres.

## Start her

Slug: `/start-here/`

<p><strong>KABINE</strong> — Kabinen er bilens interiør. Vi gør den ren.</p><p>Butikken sælger bil-interiør og detailing til danske bilejere og leasingkunder, 28-55 år, der vil have bilen ren uden at betale 1.500 kr. til en detailer.</p><h2>Begynd med</h2><ol><li><strong>Interiør</strong> — Saeder, panel og alle de steder støvsugeren ikke når.</li><li><strong>Kabine Kit 01</strong> — Børste, mikrofiber og interiørrens i ét sæt til hele kabinen.</li><li><strong>Levering og retur</strong> — hvad der kommer hvornår.</li></ol><p>[EJER: det ene, butikken gør anderledes, i en sætning ejeren selv ville sige.]</p>

## Om os

Slug: `/om-os/`

<p>KABINE er en lille dansk butik med bil-interiør og detailing. Kabinen er bilens interiør. Vi gør den ren.</p><p>[EJER: registreret firmanavn, CVR, adresse og telefon — kræves i handelsbetingelserne og i GPSR-blokken.]</p><p>[EJER: hvem står bag, og hvordan svarer man.]</p>

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
| Interiør | `interioer` | Saeder, panel og alle de steder støvsugeren ikke når. |
| Aflevering | `aflevering` | Til dig der skal aflevere leasingbilen og have depositummet tilbage. |
| Opbevaring | `opbevaring` | Smaa systemer til kabinens mange løse dele. |
| Pleje | `pleje` | Mikrofiber, børster og det der holder tekstilet pænt. |
| Gaver | `gaver` | Til ham eller hende, der elsker sin bil højere end gennemsnittet. |

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
2. E2: leverandoervalg med GPSR-dokumentation og to prøver
3. E3: registrering i producentregistret for emballage
4. E4: domaenet kabine.dk og Shopify-konto
5. E5: startdato - Q4-priser eller januar
6. E6: annoncekonto med betalingsmetode

## Annoncehooks og KPI-gates

- Hook: Din bil er ikke beskidt. Den er bare aldrig blevet renset her.
- Hook: Detaileren ville have 1.500 kr. Jeg brugte 499.
- Hook: Tre steder i din bil du aldrig har rengjort.
- Hook rate, 3 sek.: >= 25 %
- CTR: >= 1,2 %
- Konvertering: >= 0,8 %
- CPA: <= break-even, ellers pause efter tre dage
