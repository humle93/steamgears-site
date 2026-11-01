# Butiksaudit — WooCommerce + Printify — 1/11 2026

Fuld gennemgang af steamgears.eu som Printify-dropshippingbutik, målt mod gældende praksis for print-on-demand i WooCommerce og mod danske/EU-krav til netbutikker. Supplerer [indholdsaudit-2026-11-01.md](indholdsaudit-2026-11-01.md), [dode-links-2026-11-01.md](dode-links-2026-11-01.md) og [site-roadmap-2026-11-01.md](../site-roadmap-2026-11-01.md).

## Metode

Læst via Royal MCP og direkte HTTP: alle 37 aktive plugins, WooCommerce Store API (39 produkter), ordrehistorik, Complianz-konfiguration og -dokumenter, betalingsside-markering, produkt- og kurvside-HTML, load-tider, samt de 15 sider med døde links fra linkauditten. Juridisk og POD-fagligt grundlag er hentet fra Forbrugerombudsmanden, forbrug.dk, Sikkerhedsstyrelsen, Dansk Erhverv, Dansk Industri, Europa-Kommissionen, Printifys vilkår og Baymards nedbrudsdata.

**Vigtig kontekst:** butikken har haft **2 ordrer i alt** (én gennemført på 0,00 USD, én refundert på 49,95 USD). Det er ikke en butik der skal optimeres — det er en butik der skal sættes rigtigt op, før der kommer trafik.

## Statusoversigt

| Område | Status | Alvor |
|---|---|---|
| Cookie-samtykke (Complianz) | **US/opt-out** — forkert for EU-kunder | P0 |
| Valuta | **USD** i en dansk butik med EU-kunder | P0 |
| Juridisk identitet (CVR, adresse, telefon) | Mangler helt — ingen imprint-side | P0 |
| Betalingsmetoder | Kun Stripe — ingen MobilePay/PayPal | P0 |
| Fortrydelsesfunktion | Ingen digital funktion (krav fra 19/6 2026) | P1 |
| Anmeldelser | 0 på hele sitet, ingen indsamling | P1 |
| Produktbilleder | **Alle 39 produkter har under 3 billeder** (max 2) | P1 |
| Størrelsesguides | Findes som side, men ikke på produktsiderne | P1 |
| Fragt (Printify live rates) | Plugin aktivt, konfiguration ikke verificeret | P1 |
| Ydeevne | `/shop/` 5,8 s, 470 KB | P1 |
| Døde links | 39 forekomster på 15 sider | P1 |
| Printify-fulfillment | Plugin installeret, forbindelse ikke verificeret | P2 |

## P0 — skal løses før annoncering

### 1. Cookie-samtykke kører i amerikansk opt-out-tilstand

`complianz_status` returnerer `regions: ["us"]`, `consent_types: ["optout"]`. Det betyder, at banneret er sat op til CCPA-logik (fortryd-senere) i stedet for EU-opt-in, og at der kan sættes ikke-nødvendige cookies **før** samtykke. Datatilsynet har cookie-samtykke som prioriteret tilsynsområde i 2026, og danske målinger viser, at 70 % af de undersøgte sider sætter ikke-nødvendige cookies før samtykke.

Yderligere: Complianz har kun genereret et **US**-dokument (`opt-out-preferences`) og en US-privacy-erklæring. Der findes ingen EU-cookiepolitik, og imprint-dokumentet står til `mode: none`.

**Handling:** kør Complianz-wizarden igen med region **EU** og consent-type **opt-in**, med "afvis" lige så tilgængeligt som "accepter" i første lag, og granulære kategorier. Kør derefter ny cookie-scan.
**Estimat:** 1,5–2 t (plus 30 min. kontrol af banner i browser).

### 2. Butikken prissætter i USD

Store API bekræfter `currency_code: USD` på alle produkter (`$21.90`–`$29.90`). For en dansk butik med EU-kunder er det tre problemer i ét: priserne ligner ikke noget, kunden kan forholde sig til; momsen bliver uigennemsigtig ("include applicable VAT where required" i handelsbetingelserne er ikke et svar); og prissammenligning med EU-konkurrenter er umulig.

Det kan **ikke** løses ved at skifte valutakode — WooCommerce omregner ikke beløb, så 21,90 USD ville blive 21,90 kr. Priserne skal genberegnes.

**Handling:** beslut valuta (EUR er det brede EU-valg, DKK det danske), genberegn 39 produktpriser ud fra Printify-kostpris + fragt + margen, og sæt butikken til visning inkl. moms med "Prices entered with tax: Yes".
**Estimat:** 3–5 t (mest beslutning og priskalkule, ikke klik).

### 3. Juridisk identitet mangler

Footer viser kun "© 2026 Steamgears". Der er **ingen** CVR-nummer, intet firmanavn, ingen adresse og intet telefonnummer — hverken i footer eller i handelsbetingelserne. Forbrugerombudsmanden kræver firmanavn, CVR, fysisk adresse, **e-mail og telefonnummer** (en kontaktformular er udtrykkeligt ikke nok), tilgængeligt fra forsiden. Kontaktadressen er i øvrigt en privat gmail-adresse, hvilket er lovligt men koster troværdighed.

**Handling:** indsæt identitetsblok i footer, opret `/imprint/` (Complianz-dokument står på `none`), og skift kontaktadresse til domænemail, når den findes.
**Estimat:** 1–2 t, forudsat at oplysningerne kommer fra ejer.

### 4. Kun én betalingsmetode

Aktive gateway-plugins: Stripe (kort, og Apple/Google Pay via Stripe) — intet andet. Dansk Erhverv har målt, at betalingsapps (MobilePay, Apple Pay) i 2024 overhalede kort som den mest brugte metode i dansk e-handel, og at kunder **fravalgt butikker** der ikke tilbyder dem. Baymard: 9 % afbryder fordi der mangler betalingsmetoder, 19 % fordi de ikke tør betale med kort.

**Handling:** tilføj MobilePay (QuickPay/Reepay/Stripe-app), PayPal og overvej Klarna. Kræver aftale med indløser — ikke noget der kan kodes på plads.
**Estimat:** 1–3 t arbejde, men op til flere dages ventetid på godkendelse.

## P1 — konvertering og drift

### 5. Ingen digital fortrydelsesfunktion

Fra **19. juni 2026** skal webshops mod EU-forbrugere tilbyde en tydelig, lettilgængelig digital funktion til at fortryde, med bekræftelse på varigt medium. Siden har ikke haft nogen.

**Løst i dag:** ny side `/right-of-withdrawal/` med procedure, EU-standardformular og hvad der sker bagefter, linket fra refund-siden. Det er den dokumenterede proces — men ikke en ét-kliks-funktion med automatisk bekræftelsesmail. Til det kræves WPForms Pro eller en WooCommerce-returns-udvidelse (WPForms Lite kan ikke oprette formularer via MCP).
**Rest-estimat:** 2–3 t.

### 6. Ingen anmeldelser

0 produkter med anmeldelser, og intet plugin til at indsamle dem. For en ukendt butik er verificerede anmeldelser den billigste tillid at købe — men de skal være ægte, og det skal fremgå, hvordan de verificeres (Omnibus-krav).
**Handling:** vælg platform (Judge.me/Trustpilot/Loox), sæt automatisk anmodning efter levering, og vis antal + kilde.
**Estimat:** 2–4 t.

### 7. Produktbilleder: alle 39 produkter har under 3 billeder

Ingen produkt har 3 eller flere billeder; de fleste har 2. Printify-mockups alene konverterer dårligt, og Printifys egne vilkår fraskriver sig, at varen ligner mockuppen. POD-kataloger, der sælger, har typisk 5–8 billeder: mockup, livsstilsfoto, close-up af print, bagside, størrelsestabel og mål.
**Estimat:** 6–10 t for 39 produkter (eller prioritér de 10 bedste: 2–3 t).

### 8. Størrelsesguides er ikke på produktsiderne

`/size-guide/` findes, men ingen produktbeskrivelse indeholder mål. Forkert størrelse er den største enkeltårsag til tøjretur, og "bracket buying" (køb to størrelser, returnér én) dræber POD-marginen, fordi Printify ikke refunderer produktion.
**Handling:** tabel i cm **og** tommer, brystvidde + længde, "læg din yndlingsshirt fladt og mål fra ærmegab til ærmegab", plus fit-note pr. model. Skal ind i både billedkarrusel og beskrivelse — og screenshottes som dokumentation.
**Estimat:** 3–5 t (afhænger af, om Printify-blankens mål findes).

### 9. Fragtkonfiguration er ikke verificeret

`printify-for-woocommerce` 3.2 er aktiv, så live-fragtrater kan være slået til. Det er ikke verificeret, og der er to kendte fælder: (a) live-rater forsvinder, hvis kurv/checkout side-caches — W3TC kører på sitet; (b) blandede kurve (t-shirt + poster) faktureres som **to** first-item-rater, fordi rabatten kun gælder samme varetype hos samme leverandør.
**Handling:** verificér rater i en rigtig kurv, ekskludér kurv/checkout fra W3TC, opsæt zoner (DK / EU / UK / Resten) og fragtklasser (tee, hoodie, poster-tube, mug, accessories), og sæt fri fragt-tærskel over gennemsnitsordre uden tunge klasser.
**Estimat:** 2–3 t.

### 10. Ydeevne

Målt fra serveren: forside 2,1 s / 235 KB, produktside 2,5 s / 219 KB, **`/shop/` 5,8 s / 470 KB**. Baymard attribuerer 17 % af afbrydelser til langsom/fejlende side. Shop-siden er den dyreste side at have langsom.
**Handling:** undersøg shop-siden specifikt (antal produkter pr. side, billedstørrelser via Imagify, objekt-cache-hitrate), mål igen.
**Estimat:** 2–4 t.

### 11. 39 døde links på 15 sider

Se [dode-links-2026-11-01.md](dode-links-2026-11-01.md). 11 af dem forsvinder ved at publicere 6 kladde-produkter, som artiklerne allerede linker til.
**Estimat:** 0,5 d for hele oprydningen.

## P2 — skal verificeres, ikke nødvendigvis ændres

- **Printify-forbindelse:** plugin er installeret og aktivt, men om butikken faktisk er koblet til Printify, og om auto-approve er slået fra, kan ikke læses via MCP. Med 2 ordrer er der ingen driftshistorik at måle på. Anbefaling: kør de første 20–30 ordrer med manuel godkendelse.
- **Ordrebekræftelse:** fortrydelsesoplysningerne skal gives på **varigt medium** — altså i selve bekræftelsesmailen, ikke kun som link. Bør tjekkes i WooCommerce-mailopsætningen.
- **GPSR:** fra 13/12 2024 skal hvert produkt angive en EU-ansvarlig økonomisk aktør med navn og postadresse. Ingen produktside har det. Lettest løst ved at vælge EU-leverandører og skrive blokken ind i produktbeskrivelsen.
- **EU-leverandører:** samme valg løser fragtpris (ca. 4–6 USD mod 11–18 USD), leveringstid (3–7 dage mod 10–20), importmoms og GPSR i ét træk. Bør være standardvalg for alle SKU'er.
- **Moms:** butikken er merchant of record. Ved EU-salg over 10.000 EUR/år kræves destination-moms via **Union OSS**. Printify opkræver moms på produktion i EU — få momsundtagelse ved at sende CVR til Printify Support.
- **Tilgængelighed:** Tilgængelighedsloven gælder fra 28/6 2025, men mikrovirksomheder (under 10 årsværk **og** under 2 mio. EUR) er undtaget for tjenester. Butikken er derfor formentlig undtaget — men bør alligevel rette alt-tekster, kontrast og tastaturnavigation i checkout.

## Anbefalet rækkefølge med tidsestimater

| Sprint | Indhold | Estimat |
|---|---|---|
| **1. Compliance & identitet** | Complianz til EU opt-in, juridisk identitetsblok + imprint, valuta- og prispolitik, fortrydelsesfunktion (side ✓, formular mangler), fortrydelsestekst i ordremail | **8–12 t** (1,5 dag) |
| **2. Betaling & fragt** | MobilePay/PayPal/Klarna, zoner og fragtklasser, kurv/checkout undtages fra cache, verificér rater i rigtig kurv | **5–8 t** + ventetid på indløser |
| **3. Produktdata** | Størrelsestabeller på alle tøjprodukter, 5+ billeder pr. produkt, beskrivelser med fit/produktionstid/levering, GPSR-blok | **11–18 t** (2–2,5 dag) |
| **4. Tillid** | Anmeldelsesplatform + automatisk anmodning, verificeringslabel, e-mærket-overvejelse | **3–5 t** |
| **5. Oprydning** | 39 døde links, 6 kladde-produkter publiceres eller afklares, `/shop/`-ydeevne | **6–9 t** (1 dag) |

**Samlet: ca. 33–52 arbejdstimer ≈ 4,5–6,5 arbejdsdage.** Sprint 1 og 2 er blokerende for annoncering; 3–5 kan køre løbende.

## Det mangler jeg fra ejer

1. Selskabsform og registreret navn (ApS, I/S, enkeltmandsvirksomhed?)
2. **CVR-nummer**
3. Fysisk forretningsadresse
4. **Telefonnummer** (lovkrav, ikke en kontaktformular)
5. Er butikken momsregistreret, og er der registreret OSS?
6. Valutabeslutning: EUR eller DKK som butiksvaluta?
7. Findes der en domæne-mail (fx kontakt@steamgears.eu), eller skal gmail-adressen bruges?
8. Printify: er butikken koblet til, og er auto-approve slået fra?
