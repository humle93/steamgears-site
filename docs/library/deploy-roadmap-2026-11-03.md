# Deploy-roadmap — steamgears.eu

**Dato:** 2026-11-03
**Formål:** sikker, målbar rækkefølge for at omsætte [`sitewide-audit-2026-11-03.md`](sitewide-audit-2026-11-03.md) til ændringer på den hostede WordPress/WooCommerce-butik.
**Estimater:** udviklingstid for én person; ventetid på ejer, host, betalingsudbyder eller Printify er ikke medregnet.
**Samlet indsats:** ca. **6–9 udviklerdage**, plus eksterne godkendelser og ejerbeslutninger. Dette er et nyt, separat scope-estimat og ændrer ikke den autoritative status i `projektstatus.json`.

## Deploy-model og sikkerhedsgrænser

WordPress-kernen og databasen ligger hos Simply. Dette GitHub-repo er ikke en komplet WordPress-installation og er ikke en direkte produktions-deploypipeline. Siteændringer skal foretages gennem godkendt WordPress-adgang/MCP eller WordPress-admin; CSS/assets/plugins i repoet følger hver deres releaseproces.

Tilgængelighedsnotatet [`../agent-profil.md`](../agent-profil.md) dokumenterer, at live-skriveadgang, staging og browserbaseret Lighthouse ikke nødvendigvis er til rådighed i agentmiljøet. **Der deployes ikke automatisk** til production. Mangler skriveadgang, leveres ændringsspecifikation, testpayload og owner-checklist — og live-arbejdet markeres blokeret og ikke verificeret.

### Porten for hver produktionsfase

1. **Bemyndigelse:** ændringens ejer og mål (staging/production) er godkendt.
2. **Tilbagerulning:** frisk, gendanbar fil- og databasebackup; ændringen har en præcis fortrydelsesmåde.
3. **Sammenligningsgrundlag:** før-måling og de berørte URL'er/indstillinger er skrevet ned.
4. **Afgrænset levering:** én logisk ændringskurv ad gangen; ingen brede plugin-opdateringer samtidig.
5. **Kontrol:** ændringen konstateres i WordPress REST/API eller admin-visningen og i den brugervendte visning, hvor adgang tillader det.
6. **Go/no-go:** hvis checkout, samtykke eller betaling brækker, stoppes udrulningen og rulles tilbage.

## Faseopdeling

| Fase | Arbejde | Estimat | Færdig når |
| --- | --- | ---: | --- |
| 0. Bekræft nuværende tilstand | Bekræft audittens kritiske fund, teknologier, ejerskab, live-URL'er og adgang; tag nye målinger. | 0,5–1 dag | Hvert P0-fund har frisk bevis, ejer og beslutning; gammel viden behandles ikke som nuværende tilstand. |
| 1. Sikkert udrulningsgrundlag | Backup/gendannelsesøvelse, staging eller tilsvarende testvej, adgangsgrænser, ændringslog, vedligeholdelsesvindue og tilbagerulningsprocedure. | 0,5–1 dag | Gendannelse fra backup er testet eller dokumenteret gendanbar; en live-ændring kan fortrydes. |
| 2. Compliance og analysemæssigt grundlag | Ejer bekræfter virksomhedsoplysninger, cookie-/consent-konfiguration, GA4-dublettering og checkout-politikker; der rettes kun godkendte forhold. | 1–1,5 dag | Juridiske data er autoriserede; samtykke er testet; hver analytikhændelse fyrer én gang; intet kritisk checkout-fund. |
| 3. Pris, fragt og betaling | Afklar valuta, genberegn priser, bekræft moms, Printify-zoner/-rater, gateways og cache-undtagelser. Testordre i kontrolleret flow. | 1–1,5 dag + udbyderventetid | Totalpris og levering stemmer gennem hele flowet for DK/EU; betaling, e-mail og refund-/annulleringsprocedure er testet. |
| 4. Produktoplevelse | Prioritér produkter først: varianter, SKU, billeder, størrelse, fit, levering, retur, GPSR og relationer/kollektioner. | 1–2 dage | Testkataloget dækker hver produkttype; ingen uoverensstemmelse mellem produkttekst, variantdata, feed og checkout. |
| 5. Ydeevne, SEO og tilgængelighed | Mobil-baseline, billeder/CWV, H1/meta/alt/schema, kritiske URL'er, tastatur og kontrast. Ret afgrænset. | 1–1,5 dag | Nye målinger er skrevet ned; checkout er ikke cachet; schema svarer til den synlige produktoplysning; ingen kritiske regressioner. |
| 6. Accept og gradvis udrulning | Staging-test, accept, udrulning til production i små kurve, smoke-test efter hver, overvågning og overdragelse. | 0,5–1 dag | Alle acceptkriterier er gennemført; overvågning kører; tilbagerulningsberedskab og ejer er bekræftet. |

Fase 0 er målt 3/11 med read-only HTTP: se [`phase-0-baseline-2026-11-03.md`](phase-0-baseline-2026-11-03.md). Ejerbeslutningerne i fase 0–3 udestår, og checkout kan ikke måles uden en session.

**I alt: 6–9 udviklerdage.** Fase 2–4 kan blive forsinket af eksterne godkendelser eller ejerens oplysninger. Tid tælles ikke som færdigt arbejde, hvis live-udførelsen ikke kan verificeres.

## Foreslået udrulnings- og rollback-rækkefølge

### Udrulningskurv A — måling og risikoreduktion

- Tag en frisk backup og gem indstillingernes nuværende værdier.
- Mål de vigtige funnel-URL'er (forside, shop, produkt, kurv, checkout, politikker) og gem resultaterne.
- Ret GA4's dobbeltaffyring først når begge installationskilder er identificeret.
- Test samtykkeflowet og checkout på staging.
- Rollback: gendan de tidligere tag-/consent-indstillinger, og bekræft at dobbeltmåling eller ikke-godkendt script ikke vender tilbage.

### Udrulningskurv B — kommercielle grundlag

- Beslut valuta og priser, og bekræft moms- og leveringsvilkår.
- Test shipping zones, Printify-rater og cache-undtagelser.
- Slå først gateway-ændringer til efter udbydergodkendelse og en testbetaling.
- Rollback: gendan de tidligere priser/gateway-/shipping-indstillinger; stands trafikstyringen hvis totalpris eller betaling er uklar.

### Udrulningskurv C — produktkatalog

- Start med 3–5 prioritetsprodukter, inkl. de relevante produkttyper (apparel/poster/accessory).
- Bekræft SKU-, lager-, variant- og Printify-kobling før masseredigering af tekster og billeder.
- Når modelrunden er godkendt, føres samme tjekliste videre til katalogets øvrige produkter.
- Rollback: gendan de pågældende produkters tidligere indhold/tilstand, hvis købsmulighed eller produktionskobling brækker.

### Udrulningskurv D — ydeevne og findbarhed

- Ret først billedstørrelser/-formater og sideafhængige assets, og mål igen.
- Lav cache-/minificeringsændringer én ad gangen; checkout, kurv og personlige visninger holdes uden for cachen.
- Kontrollér robots/sitemap og schema på én repræsentativ URL efter hver ændring.
- Rollback: fortryd den seneste cache-/asset-ændring, og purge cachen først kontrolleret.

## Testliste før og efter live

- [ ] Forside, shop, produktkategori, produkt, kurv, checkout, konto og de vigtigste politik-sider åbner og virker.
- [ ] Produkt: valg, SKU, pris, valuta, lager, billede, antal og kurv stemmer overens.
- [ ] Kassens total: produkter + levering + moms; mindst DK og ét EU-land.
- [ ] Gennemført og afvist testbetaling samt bekræftelses-/fejlmail.
- [ ] Kupon, gæstekøb, mobilbrug, valideringsfejl og ordrestatus, hvis de hører til den nuværende salgsvej.
- [ ] Annullerings-/returproces svarer til de publicerede vilkår; juridiske fakta er bekræftet af ejeren.
- [ ] Consent: ved afvisning starter ikke-nødvendige tags ikke; ved accept virker indstillingerne som dokumenteret.
- [ ] GA4: hændelser dubleres ikke, og `purchase`-værdi/valuta stemmer med WooCommerce-ordren.
- [ ] Sitemaps/robots/canonical/indexing samt Product/Offer/Shipping/Return-schema kontrolleres, hvor det er relevant.
- [ ] Lighthouse/CWV måles med samme testopsætning før/efter; der påstås ingen forbedring uden en måling.
- [ ] Logs, cron, kassemail og eventuelle sikkerhedsadvarsler er gennemgået.

## Afhængigheder der kræver ejeren

Der gættes ikke, og der udgives ikke placeholder-oplysninger. Der skal bruges:

1. Virksomhedens officielle navn, CVR, adresse, telefon og korrekte kundeserviceadresse.
2. Beslutning om valuta og en godkendt prisberegning med moms/marginer.
3. Printifys aktive variant-/SKU-kort og leveringsvilkår for de vigtigste produkter.
4. Retur-/annulleringsvilkår og juridisk godkendelse ved tvivl.
5. Merchant-/sandbox-godkendelse hos betalingsudbyderne.
6. Backup-, staging-, WordPress-admin- og analytics-/GSC-adgang.
7. En navngivet udgiver og tilladelse til production-ændringer.

## Afgrænsning: hvad roadmappen ikke gør

Dette dokument ændrer ikke live-siden, godkender ikke juridiske vilkår, bestiller ikke tjenester, laver ikke rigtige betalingstransaktioner og påstår ikke at et deploy er udført. Repoet indeholder ikke hele live-WP-koden. Udgivelse og installering af plugin-zippen er en separat proces: se [`../../plugins/steamgears-autopilot/README.md`](../../plugins/steamgears-autopilot/README.md) og [`../roadmap.md`](../roadmap.md) §2b. Efter hver production-udførelse skal det målte resultat, det ændrede, tilbagerulningsberedskabet og eventuelle åbne blokeringer skrives ned.
