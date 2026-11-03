# Deploy-roadmap — steamgears.eu

**Dato:** 2026-11-03  
**Formål:** sikker, målbar rækkefølge for at omsætte [`sitewide-audit-2026-11-03.md`](sitewide-audit-2026-11-03.md) til ændringer på den hostede WordPress/WooCommerce-butik.  
**Estimater:** udviklingstid for én person; ventetid på ejer, host, betalingsudbyder eller Printify er ikke medregnet.  
**Samlet indsats:** ca. **6–9 udviklerdage**, plus eksterne godkendelser og ejerbeslutninger. Dette er et nyt, separat scope-estimat og ændrer ikke den autoritative status i `projektstatus.json`.

## Deploy-model og sikkerhedsgrænser

WordPress-kernen og databasen ligger hos Simply. Dette GitHub-repo er ikke en komplet WordPress-installation og er ikke en direkte produktions-deploypipeline. Siteændringer skal foretages gennem godkendt WordPress-adgang/MCP eller WordPress-admin; CSS/assets/plugins i repoet følger hver deres releaseproces.

Tilgængelighedsnotat [`../agent-profil.md`](../agent-profil.md) dokumenterer, at live-skriveadgang, staging og browserbaseret Lighthouse ikke nødvendigvis er til rådighed i agentmiljøet. **Ikke deployer automatisk** til production. Hvis write access mangler, leveres ændringsspecifikation, testpayload og owner checklist — live-arbejde markeres blokeret, ei verifioiduksi.

### Jokaisen tuotantovaiheen portti

1. **Valtuutus:** muutoksen omistaja ja kohde (staging/production) on hyväksytty.
2. **Palautuminen:** tuore, palautettavissa oleva tiedosto- ja tietokantavarmistus; muutokselle on täsmällinen peruutustapa.
3. **Vertailutaso:** ennen-mittaus ja muuttuvat URL:t/asetukset on kirjattu.
4. **Rajattu toimitus:** yksi looginen muutoskori kerrallaan; ei laajoja plugin-päivityksiä samalla kertaa.
5. **Tarkistus:** muutos todetaan WordPress REST/API- tai admin-näkymästä sekä käyttäjän näkyvästä näkymästä, kun pääsy sallii.
6. **Go/no-go:** jos checkout, suostumus tai maksu hajoaa, julkaisu pysäytetään ja palautetaan.

## Vaiheistus

| Vaihe | Työ | Arvio | Valmis kun |
| --- | --- | ---: | --- |
| 0. Varmista nykytilanne | Vahvista auditin kriittiset havainnot, teknologiat, omistajuus, live-URL:t ja pääsy; ota uudet mittarit. | 0,5–1 päivä | Jokaisella P0-havainnolla on tuore näyttö, omistaja ja päätös; vanhaa tietoa ei käsitellä nykytilana. |
| 1. Turvallinen julkaisupohja | Varmistus/palautusharjoitus, staging tai vastaava testitapa, käyttöoikeusrajat, muutosloki, ylläpitoikkuna ja palautusmenettely. | 0,5–1 päivä | Varmistuksesta palautus on testattu tai dokumentoidusti todettu palautettavaksi; live-muutos voidaan perua. |
| 2. Compliance ja analytiikan perusta | Ejer bekræfter oplysninger, cookie-/consent-konfiguration, GA4-duplikering og checkout-politikker; korrigér kun godkendte forhold. | 1–1,5 dage | Juridiske data er autoriserede; samtykke er testet; hver analytikhændelse fyrer én gang; intet kritisk checkout-fund. |
| 3. Pris, fragt og betaling | Afklar valuta, genberegn priser, bekræft moms, Printify-zoner/rater, gateways og cache-undtagelser. Testordre i kontrolleret flow. | 1–1,5 dage + udbyderventetid | Totalpris og levering stemmer gennem hele flowet for DK/EU; betaling, e-mail og refund-/cancel-procedure er testet. |
| 4. Produktoplevelse | Prioriterede produkter først: varianter, SKU, billeder, størrelse, fit, levering, retur, GPSR og relationer/kollektioner. | 1–2 dage | Testkatalog dækker hver produkttype; ingen uoverensstemmelse mellem produkttekst, variantdata, varianter, feed og checkout. |
| 5. Ydelse, SEO ja saavutettavuus | Mobil-baseline, kuvat/CWV, H1/meta/alt/schema, kriittiset URL:t, näppäimistö ja kontrasti. Fixit rajatusti. | 1–1,5 päivää | Uudet mittaukset kirjattu; checkout ei ole cachetettu; schema vastaa näkyvää tuotetietoa; ei kriittisiä regressioita. |
| 6. Hyväksyminen ja asteittainen julkaisu | Staging-testit, hyväksyntä, julkaisu tuotantoon pienissä koreissa, smoke test jokaisen jälkeen, seuranta ja handoff. | 0,5–1 päivä | Kaikki hyväksymiskriteerit läpäisty; seuranta käynnissä; palautusvalmius ja omistaja vahvistettu. |

**Yhteensä: 6–9 kehittäjäpäivää.** Vaiheet 2–4 voivat viivästyä ulkoisista hyväksynnöistä tai omistajan tiedoista. Aikaa ei lasketa valmiiksi työnä, jos live-toteutusta ei voida verifioida.

## Ehdotettu julkaisu- ja palautusjärjestys

### Julkaisuerä A — mittaus ja riskien pienentäminen

- Ota tuore varmistus ja tallenna asetusten nykyarvot.
- Mittaa tärkeät funnel-URL:t (etusivu, shop, tuote, kori, checkout, politiikat) ja tallenna tulokset.
- Korjaa GA4:n kaksoislaukaisu vasta kun molemmat asennuslähteet on tunnistettu.
- Testaa suostumuspolku ja checkout stagingissa.
- Palautus: palauta aiemmat tagi-/consent-asetukset ja varmista ettei kaksinkertainen mittaus tai luvaton skripti palaa.

### Julkaisuerä B — kaupalliset perustat

- Päätä valuutta ja hinnat, varmista vero- ja toimitusehdot.
- Testaa shipping zones, Printify rate ja cache exclusion.
- Ota gateway-muutokset käyttöön vasta provider-hyväksynnällä ja testimaksulla.
- Palautus: palauta aiemmat hinnat/gateway-/shipping-asetukset; keskeytä liikenneohjaus, jos kokonaishinta tai maksu on epäselvä.

### Julkaisuerä C — tuotekatalogi

- Aloita 3–5 prioriteettituotteella, mukaan lukien relevantit tuotetyypit (apparel/poster/accessory).
- Varmista SKU-, varasto-, variantti- ja Printify-kytkentä ennen tekstien ja kuvien massamuokkausta.
- Kun mallikierros hyväksytään, vie sama tarkistuslista katalogin muihin tuotteisiin.
- Palautus: palauta kyseisten tuotteiden vanha sisältö/tila, jos ostettavuus tai tuotantokytkentä rikkoutuu.

### Julkaisuerä D — suorituskyky ja löydettävyys

- Muuta ensin kuvien kokoa/formaatteja ja sivukohtaisia assetteja, mittaa uudelleen.
- Tee välimuisti/minifiointi-muutoksia yksi kerrallaan; checkout, kori ja henkilökohtaiset näkymät ohitetaan välimuistista.
- Tarkista robots/sitemap ja yhden edustavan URL:n schema kunkin muutoksen jälkeen.
- Palautus: peru viimeisin cache-/asset-muutos ja purge cache vasta kontrolloidusti.

## Testauslista ennen liveä ja sen jälkeen

- [ ] Etusivu, shop, tuotekategoria, tuote, kori, checkout, tili ja tärkeimmät politiikkasivut avautuvat ja toimivat.
- [ ] Tuote: valinta, SKU, hinta, valuutta, saatavuus, kuva, määrä ja ostoskori vastaavat toisiaan.
- [ ] Kassan kokonaisuus: tuotteet + toimitus + verot; vähintään DK ja yksi EU-kohde.
- [ ] Onnistunut ja hylätty testimaksu sekä vahvistus-/virhesähköposti.
- [ ] Kuponki, guest checkout, mobiilikäyttö, validointivirheet ja tilauksen tila, jos ne kuuluvat nykyiseen myyntipolkuun.
- [ ] Peruutus/palautusprosessi vastaa julkaistuja ehtoja; juridiset faktat ovat omistajan vahvistamia.
- [ ] Consent: hylkääksessä ei-välttämättömät tagit eivät käynnisty; hyväksyessä asetukset toimivat kuten dokumentoitu.
- [ ] GA4: tapahtumat eivät tuplaannu ja `purchase`-arvo/valuutta täsmää WooCommerce-tilaukseen.
- [ ] Sitemaps/robots/canonical/indexing sekä Product/Offer/Shipping/Return schema tarkistetaan, kun soveltuu.
- [ ] Lighthouse/CWV mitataan samalla testiasetuksella ennen/jälkeen; ei väitetä parannusta ilman mittausta.
- [ ] Lokit, cron, kassasähköposti ja mahdolliset turvallisuusvaroitukset tarkistettu.

## Owner-gated riippuvuudet

Älä arvaa tai julkaise placeholder-tietoja. Tarvitaan:

1. Yrityksen virallinen nimi, CVR, osoite, puhelin ja oikea asiakaspalveluosoite.
2. Päätös valuutasta ja hyväksytty hintalaskelma veroineen/marginaaleineen.
3. Printifyn aktiivinen variantti-/SKU-kartta ja toimitusehdot tärkeimmille tuotteille.
4. Palautus-/peruutusehdot ja juridinen hyväksyntä tarvittaessa.
5. Maksupalveluiden merchant-/sandbox-hyväksyntä.
6. Varmuuskopio-, staging-, WordPress-admin- ja analytiikka-/GSC-pääsy.
7. Nimetty julkaisija ja lupa production-muutoksille.

## Rajaus: mitä roadmap ei tee

Tämä dokumentti ei muuta live-sivua, hyväksy oikeudellisia ehtoja, tilaa palveluita, tee oikeita maksutapahtumia tai väitä deployta suoritetuksi. Repo ei sisällä koko live-WP-koodia. Plugin-zipin julkaisu ja asentaminen on erillinen prosessi: ks. [`../../plugins/steamgears-autopilot/README.md`](../../plugins/steamgears-autopilot/README.md) ja [`../roadmap.md`](../roadmap.md) §2b. Jokaisen production-toteutuksen jälkeen on kirjattava mitattu tulos, muutettu asia, palautusvalmius ja mahdolliset avoimet estot.
