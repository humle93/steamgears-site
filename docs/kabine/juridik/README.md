# KABINE — de juridiske sider og de 29 felter der mangler (14/11 2026)

**Teksterne er allerede skrevet.** Handelsbetingelser, privatlivspolitik, fortrydelse, levering/retur og
kontakt ligger som færdig HTML i [`deploy/nicher/indhold/kabine.json`](../../../deploy/nicher/indhold/kabine.json)
`pages[2]` og `pages[4]`–`pages[7]` (skrevet 10/11, 2,0 af 2,5 t krediteret, preflight grøn). Dette dokument
er **ikke** en kopi af teksten — en kopi ville blive en anden sandhed, næste gang nogen retter i JSON'en. Det
er verificeringen og indsæt-listen: hvilke felter der står åbne, hvor de står, og hvad der skal ind.

**Verificeret 14/11:** 8 sider i alt, **29 unikke pladsholdere i 57 forekomster**, alle markeret som
`[EJER: …]`, og **0 umarkerede huller** (grep efter `TODO`, `XXX`, `[indsæt`, `[udfyld` gav 0 hits).
`python3 tools/deploy/niche_preflight.py --check` svarer **OK: 4 nicher, ingen fejl, 0 advarsler, ingen
drift**.

## Siderne

| Side | Sted i JSON | Tegn | Åbne felter |
| --- | --- | --- | --- |
| Handelsbetingelser | `pages[4].body` | 3 756 | 14 |
| Privatlivspolitik | `pages[5].body` | 3 791 | 17 |
| Fortrydelse (formular + regler) | `pages[6].body` | 2 400 | 6 |
| Levering og retur | `pages[2].body` | 2 072 | 10 |
| Kontakt | `pages[7].body` | 1 300 | 7 |
| Om os | `pages[1].body` | 1 183 | 3 |

## De 29 felter — indsæt-listen

Type: **E1** = virksomhedens data, **E2** = GPSR/leverandør, **E3** = emballageregistrering,
**E4** = platform/udbyder, **Valg** = en beslutning, ikke en oplysning.

| # | Pladsholder | Type | Står på | Hvad der skal ind |
| --- | --- | --- | --- | --- |
| 1 | `[EJER: registreret firmanavn]` | E1 | handelsbetingelser, privatliv, om os | det registrerede navn, ikke brandnavnet KABINE |
| 2 | `[EJER: CVR-nummer]` | E1 | handelsbetingelser, privatliv | 8 cifre |
| 3 | `[EJER: adresse]` | E1 | handelsbetingelser, privatliv | gade, postnr. og by |
| 4 | `[EJER: butikkens e-mail]` | E1 | alle sider (9 steder) | én postkasse, der læses dagligt |
| 5 | `[EJER: telefon]` | E1 | handelsbetingelser, kontakt | telefonnummer med landekode |
| 6 | `[EJER: returadresse]` | E1 | levering og retur (2 steder) | hvor pakker faktisk kan modtages |
| 7 | `[EJER: timer]` | Valg | om os, kontakt | svartid i timer på hverdage |
| 8 | `[EJER: dato]` | E1 | handelsbetingelser (2 steder) | datoen siden lægges op |
| 9 | `[EJER: registreringsnummer]` | E3 | handelsbetingelser | nr. fra producentregistret for emballage |
| 10 | `[EJER: navn og adresse på EU-ansvarlig person]` | E2 | handelsbetingelser | jer selv, hvis I er etableret i DK — ellers leverandøren |
| 11 | `[EJER: e-mail for den EU-ansvarlige]` | E2 | handelsbetingelser | postkasse der kan svare på GPSR-spørgsmål |
| 12 | `[EJER: betalingsmetoder]` | Valg | handelsbetingelser | kun de metoder der er åbne i kassen |
| 13 | `[EJER: betalingsudbyder]` | E4 | handelsbetingelser, privatliv (3 steder) | fx MobilePay, QuickPay eller Stripe |
| 14 | `[EJER: kunde/butik]` | Valg | handelsbetingelser | **slet det ene ord** — se fælde 1 |
| 15 | `[EJER: eventuel brancheklageordning.]` | Valg | handelsbetingelser | en sætning, eller slet punktummet og feltet |
| 16 | `[EJER: webshop-platform]` | E4 | privatliv (2 steder) | Shopify, når butikken er oprettet |
| 17 | `[EJER: fragtfirma]` | E4 | levering og retur (2), privatliv | carrier ved navn |
| 18 | `[EJER: e-mail- og analyseværktøjer]` | E4 | privatliv | de apps der faktisk installeres |
| 19 | `[EJER: statistikudbyder]` | E4 | privatliv | fx Google Analytics — eller slet rækken |
| 20 | `[EJER: marketingudbyder]` | E4 | privatliv | Meta |
| 21 | `[EJER: levetid]` | E4 | privatliv (2 steder) | cookie-levetid pr. række, målt i browseren |
| 22 | `[EJER: antal år]` | E1 | privatliv | bogføringslovens 5 år |
| 23 | `[EJER: antal dage]` | Valg | levering og retur | frist for at melde en beskadiget pakke |
| 24 | `[EJER: antal hverdage, før pakken afgives til fragt]` | Valg | levering og retur | behandlingstid, adskilt fra de 1-3 dages levering |
| 25 | `[EJER: pris pr. zone]` | Valg | levering og retur | fragtpris, som kassen også viser |
| 26 | `[EJER: beløb]` | Valg | levering og retur | fri fragt-grænse — eller slet sætningen |
| 27 | `[EJER: lande]` | Valg | levering og retur | de lande der faktisk kan bestilles til |
| 28 | `[EJER: hvem betaler, og hvad den koster]` | Valg | levering og retur | fx »kunden, ca. 49 kr.« |
| 29 | `[EJER: hvem står bag butikken, og hvorfor lige kabinen? …]` | Tekst | om os | to-tre sætninger med ejerens egne ord |

## Fem fælder ved gennemlæsningen

1. **`[EJER: kunde/butik]` er et valg, ikke et felt.** Slet det ene ord. Vælger I »kunde«, skal returfragten
   stå tydeligt før køb — ellers gælder den ikke, og butikken betaler.
2. **Fri fragt:** skal der ikke være fri fragt, skal **hele sætningen** i `pages[2]` fjernes — ikke bare
   beløbet. Ellers lover siden noget, kassen ikke holder.
3. **To tidsfelter må ikke lægges sammen.** Siden lover allerede »1-3 hverdage efter afsendelse«; felt 24 er
   behandlingstid **før** afsendelse. Skriver man samme tal i begge, lover siden 2-6 dage i alt.
4. **Betalingsfelterne (12 og 13) skal matche kassen.** MobilePay og Apple Pay er ikke aftalt endnu (E1), så
   metodelisten må først skrives, når aftalen er der — ellers lover handelsbetingelserne en betaling, kunden
   ikke kan gennemføre.
5. **Cookie-tabellen (19-21) skal måles, ikke gættes.** Levetiden pr. række skal stemme med de cookies,
   browseren faktisk sætter; ellers er tabellen en påstand, og samtykket dækker ikke.

## Det der stadig ikke kan skrives

- **Fragtpris, fri fragt-grænse og zoner** er ikke besluttet (samme gab som S4.6 på steamgears).
- **Leverandør og GPSR-dokumentation** (E2) afgør felt 10-11 og producentoplysningerne på pakken.
- **Emballageregistreringen** (E3) skal være på plads, før markedsføring starter — felt 9 er nummeret.

Når felterne er indsat: kør `python3 tools/deploy/niche_preflight.py --check` igen (den afviser tomme
`[EJER:`-felter og drift), læs korrektur med en rådgiver, og publicér gennem
[`docs/publiceringskoersel-2026-11-09.md`](../../publiceringskoersel-2026-11-09.md) §7.
