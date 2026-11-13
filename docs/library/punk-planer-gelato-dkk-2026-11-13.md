# Clockwork- og Dieselpunkt-planerne: flyttet og skrevet om til Gelato/DKK (13/11 2026)

**Hvad der var galt (målt 13/11):** begge temaer havde en køreplan i Drive, men de lå byttet om —
Dieselpunkt-mappen indeholdt Solarpunk-planen, og Solarpunk-mappen indeholdt Dieselpunkt-planen.
Clockwork-planen lå korrekt. Alle tre planer var desuden skrevet i Printify-tiden: de nævnte Printify som
produktionsvej, priser i euro (€29,95 / €39,95) og størrelserne 30×40 + 50×70 cm.

**Flytningen (verificeret via metadata):**

| Dokument | Fra | Til |
| --- | --- | --- |
| Dieselpunk-køreplan | Solarpunk-mappen | **Dieselpunk-mappen** |
| Solarpunk-køreplan | Dieselpunk-mappen | **Solarpunk-mappen** |

Clockwork-planen blev ikke flyttet. Intet blev slettet, og Solarpunk-mappen indeholder nu kun sin egen plan.

**Omskrivningen:** Drive-integrationen kan flytte filer og oprette Google Docs, men kan ikke redigere
indholdet i en eksisterende native Google Doc. Derfor ligger der nu en **gældende Gelato/DKK-udgave** af hver
plan i den rigtige mappe, mens 27/10-originalen er bevaret og omdøbt til «(FORÆLDET 27-10-2026 - Printify og euro)» med en beskrivelse, der peger på den gældende udgave. Den tekniske mellemudgave fra samme pas, hvor
historik-linjen stadig nævnte den gamle vej, er omdøbt til «(kladde 13-11-2026, forkastet …)».

Indholdet i de gældende udgaver:

- Produktionsvej: **Gelato** (den officielle Gelato-integration til WooCommerce), ikke Printify.
- Valuta: **DKK**. Den mindste plakat står i butikken til **145 kr** (målt 8/11); 50×70 og 70×100 er ikke
  prissat i DKK endnu og fastsættes ved publicering ud fra Gelato-kostpris + fragt + margen. Ingen euro-pris
  er brugt som erstatning.
- Størrelser: **30×42, 50×70 og 70×100 cm**, samme tre formater som masterkataloget for de 50 plakater
  (13/11). 50×70 kræver **5906×8268 px** ved 300 dpi.
- Alle ti designs er bevaret med fokuskeyword, Rank Math-titel, meta-beskrivelse, kort beskrivelse, fuld
  beskrivelse og alt-tekst: SG-CLK-01…05 og SG-DSL-01…05. Pinterest-afsnittet er bevaret, med
  størrelsesangivelserne rettet til de tre formater.

**Verifikation (læst tilbage fra de nye placeringer):**

- Begge gældende dokumenter: **0** forekomster af "Printify", **0** af "euro" (uanset case), **0** af "€".
- **6** forekomster af "Gelato", **5** af "DKK", **2** af "145 kr" pr. dokument.
- Alle fem SKU'er pr. dokument har alle **6** felter; teksten er sammenlignet felt for felt mod 27/10-udgaven.
  Eneste afvigelse: SG-CLK-01's fulde beskrivelse havde det russiske ord "золотых" midt i en engelsk sætning
  ("Candlelight glints off золотых escapements"); det er rettet til "golden". Resten er ordret identisk
  (efter normalisering af Markdown-escaping).
- Mappelisterne er læst tilbage: Clockwork-mappen indeholder kun Clockwork-dokumenter, Dieselpunkt-mappen kun
  Dieselpunkt-dokumenter, og Solarpunk-mappen kun Solarpunk-planen.

**Hvad der stadig mangler:** Gelato-forbindelsen (så kostpris, variant og mockup kan måles), DKK-priser for
50×70 og 70×100, samt billeder til de ti designs — der findes fortsat ingen SG-CLK/SG-DSL-billedfiler i
Drive, og serierne er ikke en del af 50-plakatkataloget. De ti designs er **ikke** lagt i plakatpipelinen som
kladder i dette pas; det er en separat opgave.
