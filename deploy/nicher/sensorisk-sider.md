# STILLE — fem sider, klar til at klistre ind

Kilde: `deploy/nicher/sensorisk.json`. Siderne er kladder; hvert `[EJER: …]` skal erstattes af en oplysning, ejeren har givet, før siden må publiceres.

## Start her

Slug: `/start-here/`

<p><strong>STILLE</strong> — Ro, der kan tages på.</p><p>Butikken sælger sensorisk ro og neurodivergent hverdag til voksne med sansetraghed og deres pårørende, der vil have ro i hverdagen uden at gøre det til en diagnose.</p><h2>Begynd med</h2><ol><li><strong>Tryk</strong> — Vaegt og berøring, der dæmper signalerne.</li><li><strong>Vægtet hættetrøje</strong> — To kilo jævn vægt fordelt over skuldre og ryg.</li><li><strong>Levering og retur</strong> — hvad der kommer hvornår.</li></ol><p>[EJER: det ene, butikken gør anderledes, i en sætning ejeren selv ville sige.]</p>

## Om os

Slug: `/om-os/`

<p>STILLE er en lille dansk butik med sensorisk ro og neurodivergent hverdag. Ro, der kan tages på.</p><p>[EJER: registreret firmanavn, CVR, adresse og telefon — kræves i handelsbetingelserne og i GPSR-blokken.]</p><p>[EJER: hvem står bag, og hvordan svarer man.]</p>

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
| Tryk | `tryk` | Vaegt og berøring, der dæmper signalerne. |
| Lyd | `lyd` | Daempning uden at lukke verden helt ude. |
| Sansero | `sansero` | Det der hjaelper i sofaen, i skolen og på kontoret. |
| Rejse | `rejse` | Det samme, men i en taske. |
| Pakker | `pakker` | Sammensatte løsninger til søvn, arbejde og rejse. |

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
2. E2: leverandør med GPSR-dokumentation og to prøver i to vægtklasser
3. E3: registrering i producentregistret for emballage
4. E4: domæne og Shopify-konto
5. E5: beslut om community-samarbejde før foerste annonce
6. E6: annoncekonto med betalingsmetode

## Annoncehooks og KPI-gates

- Hook: Jeg kan ikke holde ud at have tøj på. Nu har jeg det her.
- Hook: Ro behoever ikke være et lukket værelse.
- Hook: To kilo, fordelt rigtigt, gør en forskel.
- Hook rate: >= 25 %
- Konvertering: >= 1,0 %
- Retur: <= 12 %, ellers stopper pasformen salget
- CPA: <= break-even pr. vare
