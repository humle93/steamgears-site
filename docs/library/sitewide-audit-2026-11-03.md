# Sitewide-audit — steamgears.eu

**Dato:** 2026-11-03  
**Scope:** WordPress- og WooCommerce-webshop med print-on-demand via Printify.  
**Status:** Dokumentbaseret audit. Dette er en prioritering af eksisterende fund — ikke en ny live-crawl eller en bekræftelse af, at forholdene stadig er uændrede.

## Konklusion

steamgears.eu har et solidt indholdsmæssigt og teknisk fundament, men bør behandles som en butik, der skal gøres købsklar og målbar før annoncebudget og større trafikindsats. De højeste risici er tillid/compliance, produktvalg og checkout-friktion, ikke mangel på flere marketingfunktioner.

Den seneste dokumenterede SEO-audit (2026-11-02) målte 121/121 sitemap-URL'er med HTTP 200, ingen døde interne links og Product + Offer-schema på 39/39 produktsider. Samme audit gav 56,5/100 og fandt især svagheder i performance, commerce-SEO, produktbilleder, metadata og måling. Butiksauditen (2026-11-01) beskrev USD-priser, US/opt-out-cookieopsætning, manglende juridisk identitet og kun Stripe. Roadmap/statusdokumenterne fra 2026-11-03 rummer senere ændringer, så hvert livefund skal genmåles før en produktionsændring.

## Afgrænsning og evidens

Gennemgangen bygger på de nyeste tilgængelige repo-dokumenter pr. 2026-11-03:

- [`seo-audit-2026-11-02.md`](seo-audit-2026-11-02.md): scorecard, sitemap-crawl og SEO-fund.
- [`butiksaudit-2026-11-01.md`](butiksaudit-2026-11-01.md): købsklarhed, checkout, betaling, fragt og compliance.
- [`roadmap-status-2026-11-02.md`](roadmap-status-2026-11-02.md) og [`../roadmap.md`](../roadmap.md): status og kendte afhængigheder.
- [`../seo.md`](../seo.md): gældende SEO- og indholdspraksis.
- [`agent-profil.md`](../agent-profil.md): platform, adgang og begrænsninger.

**Ikke genverificeret i denne audit:** live WordPress-indstillinger, GSC/Crawl Stats, GA4-events, browserbaserede Core Web Vitals, rigtig checkout/fragtberegning, juridiske virksomhedsoplysninger, Printify-forbindelse og betalingsgatewayernes godkendelser. De historiske tal nedenfor er pejlemærker, ikke en påstand om dagens live-tilstand.

## Fund og anbefalinger

| Prioritet | Område | Seneste dokumenterede observation | Anbefaling |
| --- | --- | --- | --- |
| P0 | Juridisk identitet og EU-tillid | Butiksauditten fandt manglende CVR/firmanavn, adresse og telefon; imprint stod til `none`. | Afklar virksomhedens registrerede oplysninger med ejeren. Udgiv korrekte kontakt-, handels-, retur- og privatlivsoplysninger; publicér aldrig gættede felter. Få juridisk gennemgang ved tvivl. |
| P0 | Cookie-samtykke og måling | Butiksauditten fandt US/opt-out-konfiguration; SEO-dokumentationen beskrev dobbelt GA4 pageview fra to installationer. | Revider consent-regioner, kategorier og script-gating for relevante markeder. Behold én GA4-installation og test consent-tilstande før trafikmåling. |
| P0 | Valuta, moms og samlet pris | Butiksauditten ja; USD blev observeret i produktdata, mens butikken henvender sig til danske/EU-kunder. | Vælg DKK eller EUR, genberegn produktpriser ud fra Printify-kostpris, fragt, moms og margin. Test den faktiske pris og valuta gennem checkout — skift ikke kun valutakoden. |
| P0 | Checkout og levering | Printify live rates, zoner og cache-undtagelser var ikke bekræftet. | Test kurv til ordre i et sikkert testflow for mindst DK og et EU-land. Kontrollér fragt, skat, lager, e-mails, mobilvisning og at kurv/checkout ikke serveres fra cache. |
| P1 | Produktkøb og katalogkvalitet | Tidligere audit: 39 produkter med højst to billeder; størrelsestabeller manglede på produktsider; variantmapping var en kendt blokering. | Prioritér topsælgende/strategiske produkter. Sørg for korrekte varianter/SKU'er, størrelsesguide, materialer, fit, produktion/leveringstid, returbetingelser, GPSR-oplysninger og flere repræsentative billeder. |
| P1 | Performance | Tidligere måling af `/shop/`: ca. 5,8 s / 470 KB i butiksaudit; SEO-audit målte 3,1 s / 448 KB. Målemetoder/tidspunkter varierer. | Etabler en ensartet mobil-baseline. Optimer produktbilleder og billedstørrelser, fjern unødvendige scripts, gennemgå cache/minificering og database/object-cache. Undgå ændringer i checkout-cache. |
| P1 | SEO og katalogschema | SEO-audit: 7/30 indlæg uden FAQPage, 39 produkter uden returpolitik i Offer og 39 uden `shippingDetails`; 3 lange titler og arkivsider med manglende meta var også registreret. | Valider synligt indhold mod schema. Implementér retur-/fragtfelter først når politik og vilkår er bekræftet. Ret title/meta med Rank Math og mål schema på repræsentative produkt-, kategori- og guidesider. |
| P1 | Tillid og konvertering | Tidligere 0 produktanmeldelser og kun Stripe var dokumenteret. | Tilføj en ægte anmeldelsesproces efter levering med tydelig verificering. Undersøg MobilePay/PayPal og evt. andre relevante betalingsmetoder efter aftale, kompatibilitet og omkostningsvurdering. Ingen falske anmeldelser eller udokumenterede badges. |
| P1 | Indhold → produkt → ordre | Guides og produkter findes, men tracking og konverteringsmåling var usikker pga. GA4-dublet. | Standardisér CTA'er og interne links til relevante produkter; brug `/go/<id>/` hvor det passer. Mål `view_item`, `add_to_cart`, `begin_checkout` og `purchase`, og afstem køb mod WooCommerce. |
| P2 | Drift og udgivelse | WordPress ligger hos Simply; repoet indeholder dokumentation, assets og plugin-kilde, ikke hele live WordPress-installationen. WP-Cron på cachet site kan være upålidelig. | Brug sikker staging/backup, kontrollerede ejer-godkendte ændringer og reel cron til tidskritiske jobs. Hold plugin-release adskilt fra site-indhold og verificér efter deploy. |
| P2 | Tilgængelighed og mobil UX | Ikke målt med browser-/Lighthouse-audit i denne gennemgang. | Test tastatur, fokus, labels, kontrast, alt-tekst, zoom, fejlbeskeder og købstrin på mobil. Brug automatiske tests som supplement til manuel test. |

## Anbefalede funktioner — i rækkefølge efter værdi

### Først: gør købstragten troværdig og friktionsfri

1. **Klar leverings- og returinformation** på produkt, kurv, checkout og ordrebekræftelse, med enslydende og ejerbekræftede vilkår.
2. **Korrekte produktvarianter og størrelser**, lagerstatus, SKU'er og Printify-mapping. Undgå at love størrelser, der ikke kan vælges.
3. **Samlet pris tidligt:** valuta, moms og forventet fragt skal være forståelige før betaling.
4. **Mobil checkout uden overraskelser:** test gæstekøb, valideringsfejl, betalingsafvisning, genindlæsning og ordrebekræftelse.
5. **Tillidslag:** verificerede anmeldelser, tydelig kontakt, sikker betaling og realistiske produktion-/leveringstider.

### Dernæst: hjælp kunden med at vælge

6. **Kategorifiltre** efter produkttype, stil/kollektion, størrelse, pris og tilgængelighed — kun filtre der matcher reelle produktdata.
7. **Størrelses- og pasformhjælp** på alle relevante beklædningsprodukter, både cm og tommer.
8. **Komplet look / relaterede produkter** på guides og produktsider med lagerkontrol og relevante, ikke tilfældige, forslag.
9. **Søgning med gode tomme resultater**, forslag og stavetolerance, hvis den nuværende katalogstørrelse og søgedata retfærdiggør funktionen.
10. **Ønskeliste** kun efter checkout-, tracking- ja performance-forbedringer; kontroller konto-/cookieafhængigheder og GDPR-konsekvenser.

### Mål og forbedr løbende

11. **Samtykkestyret analytics** med én pageview-kilde, deduplikerede e-commerce events og testkøb.
12. **Dashboards for købstragt:** produktvisning → kurv → checkout → køb, opdelt efter enhed, kanal og kampagne.
13. **Lager- og katalogalarmer:** fejl i Printify-synk, udsolgte varianter, manglende billeder/meta og pris-/valutaafvigelser.
14. **Regressionskontrol** efter ændringer: sitemap/status, checkout-smoke test, schema, links og performance-baseline.

Undgå lige nu: tung headless-ombygning, flere AI/chat-widgets, avanceret loyalitetsprogram, bredt abonnementssystem eller mange overlappende plugins. De øger vedligeholdelse og risici uden dokumentation for, at kerneproblemerne er løst.

## Målepunkter og acceptkriterier

Etablér nye baselines før ændringer. Følgende er foreslåede styringsmål, ikke aktuelle målinger:

- Checkout testet end-to-end for DK og mindst ét EU-land uden fejl i pris, moms, fragt eller e-mail.
- Ingen produkt med uvalgbare/fejlkoblede varianter eller manglende obligatoriske produktoplysninger.
- Én GA4 pageview-kilde; testhændelser registreres én gang, og køb afstemmes med WooCommerce.
- Kritiske købs- og politik-URL'er giver forventet status; sitemap uden døde URL'er eller utilsigtede redirects.
- Mobil Core Web Vitals måles på forside, shop, produktside og checkout; regressionsgrænser fastsættes mod den målte baseline.
- Structured data svarer til synlige priser, valuta, lager, levering og returpolitik.
- Cookievalg respekteres og ikke-nødvendig måling starter først efter relevant samtykke.

## Beslutninger og adgang, der kræves

- Registreret virksomhedsnavn, CVR, postadresse, telefon og kontaktmail.
- Valg af DKK/EUR og prissætning/momsstrategi.
- Bekræftede Printify-varianter, leveringszoner/-priser og returbetingelser.
- Godkendelse af nye betalingsmetoder og eventuelle leverandøraftaler.
- Adgang til staging/backup, WordPress-admin og analytics/GSC til live-måling.
- Ejergodkendelse af ændringer, der påvirker betaling, juridiske oplysninger, samtykke eller publiceret indhold.

## Relateret plan

Den prioriterede udførelses- og deployplan følger i [`deploy-roadmap-2026-11-03.md`](deploy-roadmap-2026-11-03.md). Den er en separat arbejdsplan; auditten alene giver ikke tilladelse til at ændre live checkout eller juridiske indstillinger.
