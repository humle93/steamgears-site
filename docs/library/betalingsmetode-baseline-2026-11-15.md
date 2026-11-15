# Betalingsmetode-overvågningen havde en blind vinkel — nu har den en baseline (15/11 2026)

**Kort:** `tools/payment_methods.py` svarede **8 PASS · 0 WARN · 0 FAIL**, selv om `bacs` var
slået fra og havde været tilgængelig 12/11. Checken sammenlignede kun gateways med sig selv:
en metode, der blev slukket, forsvandt stille fra listen og blev aldrig savnet. Nu læser
værktøjet en **baseline** (`tools/payment_methods_baseline.json`), sammenligner den med det
kassen faktisk tilbyder og melder en forsvundet metode med navn. Målt 15/11 giver den
**8 PASS · 2 WARN · 0 FAIL**, hvor begge advarsler navngiver `bacs`.

## 1. Den blinde vinkel

| Spørgsmål | Før | Efter |
|---|---|---|
| Er en tændt gateway uvalgbar? | Ja, fanges | Ja, fanges |
| Er kortbetaling tilgængelig? | Ja, fanges | Ja, fanges |
| Er en gateway, der **var** tændt, nu slukket? | **Nej — usynligt** | **WARN med metodenavn** |
| Er en metode, kassen **plejede** at tilbyde, væk? | **Nej — usynligt** | **WARN med metodenavn og grund** |
| Er en krævet metode væk (`stripe`)? | Delvist (kun kort-checken) | **FAIL med metodenavn** |
| Er `bacs` tændt uden bankoplysninger? | Nej | **FAIL** |
| Findes baselinen? | — | **FAIL hvis den mangler** (ellers kunne checken slås fra ved et uheld) |

## 2. Sådan virker det

`tools/payment_methods_baseline.json` er håndskrevet og indeholder:

* `required` — metoder kassen **skal** tilbyde. Mangler en, er det **FAIL** (`stripe`).
* `expected` — metoder kassen plejede at tilbyde, med en `grund` der vises i advarslen (`bacs`).
* `enabled_gateways` — gateways der var `enabled=yes` da baselinen blev skrevet. Er en af dem
  nu slået fra, giver det **WARN**, også selv om metoden aldrig nåede kassen.
* `last_known_checkout` — dokumentation af den målte tilstand, ikke brugt i dommen.

Baseline opdateres **bevidst i hånden**. En automatisk opdatering ville gøre præcis den
nedlukning, checken skal fange, til den nye sandhed. Når ejeren med vilje fjerner en metode,
skal rækken flyttes/ændres i samme commit som beslutningen — så forsvinder advarslen af en
grund, ikke af sig selv.

## 3. bacs-afklaringen: slået fra og ikke konfigureret

| Måling 15/11 | Værdi |
|---|---|
| `woocommerce_bacs_settings.enabled` | **`no`** (12/11: tilgængelig i kassen) |
| `iban`, `account_number`, `account_details` | **alle tomme** |
| Ordrer med `payment_method=bacs` (HPOS) | 3, alle fra 8/11 og alle annulleret (testordrer 1528–1530) |
| Ændringer af `bacs` i MainWP-/MCP-logs | **0 træf** |
| Kode i repoet der skriver `bacs` | **ingen** |

Der er altså ingen kode, plugin eller deploy i repoet, der har slået metoden fra; det er en
indstilling ændret i wp-admin. Samtidig er bankfelterne tomme, så metoden **kan ikke
gennemføres** for en kunde: den ville vise »Make your payment directly into our bank account«
uden et kontonummer at overføre til. At »gendanne« den ville derfor ikke give kassen en brugbar
betalingsmetode — det ville give den en død knap. Den er derfor **ladt slået fra**, og den nye
check melder den som WARN med begrundelsen, så den ikke længere er usynlig.

Skal bankoverførsel tilbydes, skal ejeren først indsætte IBAN/kontonavn i
**WooCommerce → Betalinger → Direkte bankoverførsel** og derefter slå den til. Så snart den er
tændt og konfigureret, kvitterer checken med PASS (se §4).

## 4. Bevis — kørt 15/11

Aktuel tilstand, live mod sitet:

```
[WARN] betalingsmetode forsvundet fra kassen: bacs — bankfelterne (IBAN/kontonummer) er tomme, …
[WARN] tidligere tændt gateway er ikke længere tændt: bacs — bankfelterne (IBAN/kontonummer) er tomme, …
[OK] 8 PASS · 2 WARN · 0 FAIL
```

Kunstig tilstand: den aktive metode `stripe` fjernet fra et gemt live-svar
(`--from-file /tmp/artificial_pm.json`):

```
[FAIL] kassen tilbyder mindst én betalingsmetode: ingen
[FAIL] kortbetaling er tilgængelig: stripe mangler i kassen
[FAIL] kassen opfylder baseline (krævede metoder): mangler: stripe
[WARN] tidligere tændt gateway er ikke længere tændt: stripe; bacs — …
[FEJL] 6 PASS · 1 WARN · 3 FAIL        (exit 1)
```

Kunstig tilstand: `bacs` konfigureret og slået til igen (`--from-file /tmp/restored_bacs.json`):

```
[PASS] kassen matcher baseline: 2 forventede metoder er tilgængelige
[PASS] bacs er tændt med bankoplysninger: bankoplysninger findes
[OK] 11 PASS · 0 WARN · 0 FAIL
```

Exitkoderne er uændrede: **0** ved PASS/WARN, **1** ved FAIL eller manglende måling, **2** uden
credential. Det gør WARN synligt i output uden at vælte den natlige kørsel; en krævet metode
vælter den.

## 5. Tests og værn

`tools/test_payment_methods.py` er gået fra **13 til 20** offline-fixtures. De nye dækker:
forsvundet `expected`-metode (WARN med navn), forsvundet `required`-metode (FAIL med navn),
tidligere tændt gateway der nu er slukket (WARN med navn), manglende baseline (FAIL),
ugyldig baseline-fil, `bacs` tændt uden bankoplysninger (FAIL) og konfigureret `bacs` (PASS).
Testen kører i `tools/natlige-vaern.sh` som »betalingsmetoder (selftest)«.

## 6. Grænser

* Basalinen er et **øjebliksbillede**, ikke en kontrakt med Stripe. En metode, der aldrig har
  været tilgængelig, fanges ikke — den skal stå i `expected` for at blive savnet.
* `expected` er bevidst blød (WARN): en ejer kan slå en metode fra med rette, og så skal
  checken ikke fejle, men sige det højt, indtil baselinen opdateres.
* Kort og Google Pay er målt i kassen samtidig: `stripe` er valgt, 7 betalingsfelter,
  »Place Order« og Google Pay-knappen synlig. Selve betalingen er ikke gennemført.
