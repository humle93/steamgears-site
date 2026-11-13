# GPSR- og tillidsside — kladde, ikke publiceret (13/11 2026)

**Status:** kladde. Intet herfra er lagt på sitet, og ingen produktside er ændret. Dokumentet indeholder
kun bekræftede fakta; felter der mangler, står som `[TODO: …]` med en forklaring, ikke som en gættet værdi.

## 1. Hvad der er bekræftet

| Faktum | Værdi | Hvorfra |
| --- | --- | --- |
| Gade og husnummer | **Lavgade 44a** | Complianz' `address_company`, målt 8/11 (`Lavgade 44a st th`) |
| By | **Aabenraa** | repoets egen site-copy ("the Steamgears workshop in Aabenraa") |
| Land | **Danmark** | Complianz-feltet og butikkens danske valuta/leveringszone |
| Butikkens navn i sitets formular | "Henning 6" | Complianz `organisation_name`, målt 8/11 — **ikke** bekræftet som juridisk navn |
| CVR | **findes ikke som felt** | Complianz har intet CVR-felt; ingen CVR i repoet |
| Telefon | **tom** | Complianz `telephone_company: ""` |
| Postnummer | **ikke fundet** | adressefeltet har gade og etage/dør, ikke postnummer |
| E-mail | **ikke bekræftet** | auditten 1/11 beskriver kontakten som en gmail-adresse, men den præcise adresse står ikke i dette dokument |

Det er hele grundlaget. Alt nedenfor, der ikke står i tabellen, er markeret som TODO.

## 2. Produktside-blok (engelsk copy, klar til indsættelse)

> **Product safety and responsible person**
>
> Posters are printed to order and shipped by our print partner. They are decorative wall art, not toys,
> and are not intended for children to handle unsupervised.
>
> **EU responsible person (GPSR, Regulation (EU) 2023/988):**
> `[TODO: legal entity name]`, Lavgade 44a, `[TODO: postcode]` Aabenraa, Denmark.
> Contact: `[TODO: e-mail]` · `[TODO: phone]` · CVR `[TODO: CVR number]`.
>
> Questions about a specific print, a return or a damaged parcel go to the same address and are answered
> within two working days.

**Regler for blokken:**

- **Producentens rolle er ikke gættet.** Hvem der er "manufacturer" i GPSR-forstand — butikken eller
  printpartneren — afhænger af aftalen og skal bekræftes i Gelatos dashboard/vilkår, **efter** forbindelsen
  er oprettet. Indtil da står printpartneren beskrevet som "our print partner", ikke som en navngivet
  juridisk enhed.
- **Papirvægt, mål og emballage** er ikke målt. Katalogets danske tekst lover "kraftigt mat kunstpapir", men
  den konkrete gramvægt er ikke bekræftet, så den står ikke på siden endnu.
- **Ingen påstande om oprindelse** ("printed in Denmark", "ships from the EU") før Gelato viser, hvor
  produktionen faktisk ligger for den valgte produkttype.

## 3. AI-linjen — gated pr. design

Den ønskede tekst er:

> Designed with AI tools, finished by hand.

Den må **kun** sættes på et produkt, når efterbearbejdningen af netop det motiv er udført og kan peges på:
opskalering, beskæring til 5:7 eller 2:3, fjernelse af AI-artefakter og pålægning af typografi i den rolige
nederste femtedel. Råbillederne i Drive er 848–896 px brede og kræver 5,6–6,8× opskalering, så ingen af dem
er "finished" i dag.

Praktisk: linjen er et felt pr. design — ikke en global tekst i temaet — og sættes først, når designet har
en godkendt trykfil. Ellers er den en påstand om arbejde, der ikke er gjort.

## 4. Tillidsside — de fem blokke

| Blok | Indhold | Status |
| --- | --- | --- |
| Hvem vi er | Steamgears, workshop i Aabenraa, Danmark | ✅ kan skrives fra repoets copy |
| Virksomhedsoplysninger | juridisk navn, CVR, fuld adresse, telefon, e-mail | ✗ `[TODO]` — S4.2/S4.9 |
| Levering | produktionstid og fragt | ✗ venter på Gelato-forbindelsen (rater og produktionstid er ikke målt) |
| Retur og fortrydelse | link til `/right-of-withdrawal/` og `/shipping/`/`/returns/` | ✅ siderne findes og svarer 200 |
| Kontakt | e-mail og telefon | ✗ `[TODO]` — telefon er tom, e-mail ikke bekræftet |

Siden skal **ikke** publiceres som "trust page", før de tre `[TODO]`-blokke er udfyldt. En tillidsside med
tomme felter gør mere skade end ingen side.

## 5. Hvad der kræves for at færdiggøre

1. **CVR-nummer** og det **registrerede juridiske navn**.
2. **Fuldt adressefelt** — postnummer og by (gaden og husnummeret er bekræftet).
3. **Telefon** og den **e-mail**, kunderne skal bruge.
4. **Gelato-forbindelsen**, så producentrolle, produktionstid og fragt kan beskrives korrekt.
5. **Pr. design:** bekræftelse på at efterbearbejdningen er udført, før AI-linjen sættes på.

Ingen af delene kan udledes af repoet, og ingen af dem er gættet her.
