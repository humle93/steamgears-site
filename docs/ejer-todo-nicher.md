# Ejer-to-do — fire nicher, én start

**Til ejeren. Skrevet 9. november 2026.** Roadmap'en ligger i
[`docs/library/niche-roadmap-2026-11-09.md`](library/niche-roadmap-2026-11-09.md). Denne liste er
kun de handlinger, **du** skal udføre — agenten kan ikke oprette konti, underskrive aftaler, tage
fotos eller godkende jura.

Sådan bruges den: arbejd oppefra. Et punkt er først færdigt, når der står et bevis i parentesen.
Kryds af i rækkefølge; spring ikke et punkt over, fordi det er kedeligt — de dyre fejl ligger i
F1-F7, ikke i annoncen.

**Tidsforbrug:** Fase 0 ca. 12-16 timer, KABINE ca. 20-25 timer fordelt over fire uger. Regn ikke
med at gøre det på en weekend.

---

## A. Beslutninger, der skal tages først (ca. 2 timer)

- [ ] **A1. Startvindue.** Vælg 17/11 (Q4, højere CPM, julegaver) eller 5/1 (lavere CPM, mere ro).
  Anbefaling: 17/11 med **800 kr.** i første uge, hvis F1-F7 er lukket senest 15/11. Er de ikke
  det, så flyt starten til 5/1 i stedet for at haste. *(Bevis: datoen står i din kalender, og
  annoncebudgettet er sat.)*
- [ ] **A2. Navn og domæne.** Alle fire ønskede `.dk`-domæner er taget (målt 9/11). Beslut: køb
  `kabine.dk` af ejeren, lej det, eller skift til fx `kabinekit.dk`. Tjek også varemærke, og om
  navnet findes som dansk butik. **KABINE kan ikke åbne uden denne beslutning.** *(Bevis:
  registrar-kvittering eller skriftligt afslag, plus et notat om varemærketjek.)*
- [ ] **A3. Kit 01's indhold.** Sættet er uden væske (børste, mundstykke, to mikrofibre) ved 499 kr.
  Beslut om det er nok værdi, eller om prisen skal til 399 kr., eller om der skal en interiørrens
  med fra en dansk leverandør. 399 kr. sænker break-even-CPA fra ca. 223 til ca. 149 kr.
  *(Bevis: én linje i beslutningsnotatet med pris, indhold og begrundelse.)*
- [ ] **A4. Lager eller direkte levering.** Beslut om du vil holde lager (hurtigere levering,
  mere kapital) eller sende fra leverandør (mindre kapital, længere leveringstid). Leveringstiden
  skal stå på siden, uanset hvad du vælger. *(Bevis: valget står i butikkens fragtindstillinger.)*
- [ ] **A5. Returfragt.** Beslut hvem der betaler retur, og hvad det koster. Det skal stå i
  handelsbetingelser og på fortrydelsessiden. *(Bevis: beløbet er udfyldt begge steder.)*

---

## B. Fælles fundament — gælder alle fire (ca. 10-14 timer)

- [ ] **B1. CVR, telefon, adresse og bank.** Opret eller bekræft firma, telefonnummer, adresse og
  bankaftale, så du kan fakturere og modtage betaling. *(Bevis: oplysningerne kan sættes ind i
  handelsbetingelserne, og banken accepterer modtagelse.)*
- [ ] **B2. Shopify-konto.** Opret butikken, vælg plan og sæt butikkens navn. *(Bevis: du kan logge
  ind, og butikkens adresse virker.)*
- [ ] **B3. Betaling.** Aktivér MobilePay, Apple Pay og kort. Hvis Shopify Payments ikke dækker
  det, du vil bruge, så vælg en dansk udbyder og test den. *(Bevis: en testordre på 1 kr. er
  gennemført og refunderet.)*
- [ ] **B4. Domæne og e-mail.** Forbind domænet, sæt HTTPS op, og opsæt SPF, DKIM og DMARC, så
  ordrebekræftelser ikke ender i spam. *(Bevis: en mail fra butikken lander i indbakken, ikke i
  spam, og domænet svarer på https.)*
- [ ] **B5. Emballageproducentansvar.** Registrér dig, hvis du er omfattet, og gem
  registreringsbeviset. Ansvaret afhænger af din rolle i kæden — læs vejledningen, og spørg hvis
  du er i tvivl. *(Bevis: registrering eller skriftlig afklaring.)*
- [ ] **B6. GPSR pr. vare.** Skaf producentens navn/adresse, produkt-ID, materialer, vægt/mål,
  sikkerheds- og advarselsoplysninger. Sæt dem ind i produktteksten, ikke i en pdf langt væk.
  *(Bevis: hver vare har en GPSR-blok, og der ligger en teknisk fil hos dig.)*
- [ ] **B7. Juridiske sider.** Udfyld `[EJER: …]` i handelsbetingelser, privatlivspolitik,
  fortrydelse, levering og kontakt. Få dem gennemlæst. *(Bevis: ingen `[EJER]` tilbage på de sider,
  og en rådgiver har set dem.)*
- [ ] **B8. Cookiebanner og tracking.** Sæt GA4 og Meta Pixel/CAPI op med samtykke, og test at
  køb, kurv og produktvisning registreres. *(Bevis: testhændelser ses i begge systemer, og
  banneret blokerer marketingcookies før samtykke.)*
- [ ] **B9. Forsikring og regnskab.** Afklar produktansvar, og aftal bogføring. *(Bevis:
  police/aftale eller en skriftlig vurdering af, at det ikke er nødvendigt.)*

**Stop:** brug ikke en krone på annoncer, før B1-B8 er lukket. En butik, der ikke kan svare på,
hvem der står bag, eller hvordan en retur foregår, koster mere i support end i annoncer.

---

## C. KABINE — 16/11 til 13/12 (ca. 20-25 timer)

### Uge 1: butikken

- [ ] **C1. Importér produkter.** Shopify → Produkter → Importér →
  `deploy/nicher/shopify-kabine.csv`. Status skal være **draft**. *(Bevis: fem varer står som
  kladder med pris og tekst.)*
- [ ] **C2. Ret `[EJER]` i produkterne.** Leveringstid, returadresse og eventuelle mål skal
  udfyldes. *(Bevis: ingen `[EJER]` i produktteksten.)*
- [ ] **C3. Opret fem kollektioner.** Interiør, Aflevering, Opbevaring, Pleje, Gaver. Brug
  teksten fra `deploy/nicher/kabine-sider.md`. *(Bevis: alle fem har tekst og mindst én vare.)*
- [ ] **C4. Indsæt ni sider.** Start her, Om os, Levering og retur, Pleje og brug,
  Handelsbetingelser, Privatlivspolitik, Fortrydelse, Kontakt og FAQ. FAQ'en har JSON-LD med i
  teksten. *(Bevis: alle ni sider svarer 200, og menuen linker til dem.)*
- [ ] **C5. Billeder.** Tag seks fotos: Kit 01 samlet, børsten i sømmen, før/efter af sømmen,
  organizer i bilen, bundle som gave, og et detaljefoto af mundstykket. Alt-teksterne ligger i
  CSV'en. *(Bevis: alle fem varer har mindst to billeder.)*
- [ ] **C6. Testordre.** Gennemfør køb med MobilePay/kort, tjek ordrebekræftelse, sporingsmail,
  fortrydelsesformular og refundering. *(Bevis: ordren er refunderet, og du har set alle mails.)*

### Uge 2: indstillinger og første kreative

- [ ] **C7. Fragt og levering.** Sæt fragtpris, gratis fragt-grænse og behandlingstid. Skriv det
  samme på leveringssiden. *(Bevis: testordrens fragt stemmer med siden.)*
- [ ] **C8. Fire kreative.** Optag hooksene: sømmen, prisen, afleveringen og timeren. Hånd-only,
  15-30 sekunder, første tre sekunder skal vise problemet. *(Bevis: fire filer klar, ingen andet
  brand i billedet.)*
- [ ] **C9. Annoncekonto.** Sæt betalingsmetode, pixel/CAPI og konverteringshændelse op. Start med
  800 kr. i uge 1. *(Bevis: kampagnen er godkendt, og der kommer visninger.)*

### Uge 3-4: mål og beslut

- [ ] **C10. Daglig rutine.** Tjek CPA, hook rate, CTR, konvertering, retur og lager. Pause en
  annonce efter tre dage over break-even. *(Bevis: en kort log pr. dag.)*
- [ ] **C11. Ugentlig gate.** Uge 1: hook ≥ 25 %, CTR ≥ 1,2 %. Uge 2: konvertering ≥ 0,8 %.
  Uge 4: CPA ≤ 223 kr. og ≥ 30 ordrer. *(Bevis: tallene står i loggen, og næste handling er valgt.)*
- [ ] **C12. Beslut.** Hvis K4 er bestået: fortsæt, og planlæg IMMER. Hvis ikke: stop annoncerne,
  ret pris/produktside, og brug to uger mere — ingen ny butik. *(Bevis: skriftlig go/no-go.)*

---

## D. IMMER — januar (kun hvis KABINE har bestået K4)

- [ ] **D1. Domæne og navn.** `immer.dk` er en aktiv dansk blog. Beslut nyt navn eller køb;
  tjek varemærke. *(Bevis: registrar og navnetjek.)*
- [ ] **D2. Leverandør og sikkerhed.** Vælg leverandør med teknisk fil, vægt, materialer og
  vaskeanvisning. Få styr på vægtklasse og advarsler. *(Bevis: dokumentation pr. vare.)*
- [ ] **D3. Butik og import.** Importér `shopify-soevn.csv`, indsæt ni sider, og ret `[EJER]`.
  *(Bevis: testordre gennemført.)*
- [ ] **D4. Tekstgennemgang.** Ingen behandlings-, diagnose- eller effektpåstande. Læs
  `brug-og-sikkerhed` og alle produkttekster højt. *(Bevis: gennemlæst af en anden end dig.)*
- [ ] **D5. Kreative og budget.** Start 800-1.000 kr./uge i to uger. Rummet, ikke søvnløshed.
  *(Bevis: kampagne og budget.)*
- [ ] **D6. Gate.** Konvertering ≥ 1,0 %, CPA ≤ 242 kr., retur ≤ 10 %. Ellers stop. *(Bevis:
  tallene i loggen.)*

---

## E. OPLOFT — februar (kun hvis IMMER har positiv DB og retur ≤ 10 %)

- [ ] **E1. Domæne og navn.** `oploft.dk` er taget. Beslut nyt domæne, og tjek navnet.
- [ ] **E2. Leverandør og karton.** Få tilbud med kartonlængde under 115 cm, TARIC og fragt.
  *(Bevis: tilbuddet ligger i mappen.)*
- [ ] **E3. Prøve og mål.** Mål reol, dør og hylde. Test belastning. *(Bevis: målene er sat ind i
  produktteksten i stedet for `[EJER]`.)*
- [ ] **E4. Butik og import.** Importér `shopify-lejebolig.csv`, indsæt sider, ret `[EJER]`.
- [ ] **E5. Gate.** Konvertering ≥ 2,0 % ved 300 besøg, CPA ≤ 253 kr., retur ≤ 10 %, nul
  holdklager. Ellers stop.

---

## F. STILLE — Q2 2027 (kun efter afklaring)

- [ ] **F1. Klassificering.** Skriftligt svar på, om hvert produkt er legetøj, beklædning eller
  PPE, og hvilke CE/EN-krav der gælder. **Ingen lagerkøb før.**
- [ ] **F2. Navn.** `stille.dk` er en aktiv dansk side. Vælg nyt navn, og tjek varemærke.
- [ ] **F3. Tekstgennemgang.** Ingen diagnoseord, ingen behandlingsløfter, ingen "virker mod".
- [ ] **F4. Størrelsesguide.** Målte tal pr. størrelse, ikke en omregningstabel. 12 % retur er
  for højt til at gætte.
- [ ] **F5. Et produkt først.** Start med hættetrøjen, mål retur og pasform i fire uger.
- [ ] **F6. Gate.** Retur ≤ 12 %, CPA ≤ 173 kr., ingen påstande uden dokumentation.

---

## G. Ugentlig rutine, når en butik kører (30 minutter hver mandag)

- [ ] Omsætning, ordrer, AOV og dækningsbidrag.
- [ ] CPA pr. annonce og pr. kreativ. Pause det, der er over break-even i tre dage.
- [ ] Returprocent og årsager. En ny returårsag er en tekstændring, ikke en annonceændring.
- [ ] Lager og leveringstid. Sælg ikke mere, end du kan levere.
- [ ] Support: antal mails, svartid og de tre hyppigste spørgsmål. Opdatér FAQ'en.
- [ ] Næste uges ene eksperiment: én pris, én tekst eller ét kreativt. Ikke tre på én gang.

---

## H. Det skal du ikke gøre

- **Ikke** starte annoncer før B1-B8 er lukket.
- **Ikke** åbne fire butikker på én gang. Gaten mellem dem er hele pointen.
- **Ikke** ændre en pris uden at regne break-even-CPA om. 399 kr. på Kit 01 sænker loftet med
  ca. 74 kr.
- **Ikke** skrive "behandler", "helbreder", "virker mod" eller en diagnose i en annonce. Det er
  ikke en tone-of-voice-regel; det er en dokumentationsregel.
- **Ikke** købe lager, emballage eller fotos, før navn og domæne er besluttet.
- **Ikke** bruge en førpris, du ikke kan dokumentere.
- **Ikke** tolke en enkelt god uge som bevis. Se på fire uger og på dækningsbidraget efter retur.
