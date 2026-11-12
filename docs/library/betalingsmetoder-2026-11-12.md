# Betalingsmetoderne i kassen — §4d C var formuleret forkert (12/11 2026)

**Kort:** Roadmap'ens ejerpunkt C lød »3 Stripe-metoder er slået til men utilgængelige
(`amazon_pay`, `klarna`, `link`) — slå dem til i Stripe Dashboard, eller fra i WooCommerce«.
Målt 12/11 er **ingen af de to halvdele rigtige**: metoderne kan ikke slås fra i WooCommerce,
fordi deres tilstand arves fra Stripe-kassen, og der mangler ingen aftale i Stripe — alle tre
evner er `active` på kontoen. Butikken fejler ikke; formuleringen gjorde. Punktet er lukket
som **rettelse, ikke som arbejde**, præcis som §4d K blev lukket 9/11.

Alt nedenstående er målt mod det levende site gennem Novamira (`novamira/execute-php`) og kan
genskabes med ét kald: `python3 tools/payment_methods.py`.

## 1. Hvad der faktisk står på sitet

| Måling | Værdi |
|---|---|
| WooCommerce | 11.2.2 |
| Valuta · baseland | DKK · DK |
| Stripes gateway-klasse | `WC_Stripe_OCS_Payment_Gateway` — butikken kører Stripes nye kasse (OCS) |
| `upe_checkout_experience_enabled` · `testmode` | `yes` · `no` |
| Tændte gateways | `stripe`, `stripe_amazon_pay`, `stripe_klarna`, `stripe_link`, `bacs` (5) |
| Tilgængelige i kassen | `stripe`, `bacs` (2) |
| Tændt, men uvalgbar | `stripe_amazon_pay`, `stripe_klarna`, `stripe_link` (3) |

Det er præcis de tre rækker §4d C beskriver. Det nye er **hvorfor** de står sådan.

## 2. Årsagen: de tre er skygger under OCS, ikke egne gateways

Under OCS vises betalingsmetoderne **inde i** den ene `stripe`-gateway. De tre separate
gateway-klasser findes stadig i Woo's liste, men de er dubletter:

* deres `is_available()` er falsk, fordi metoden ikke vælges som gateway, men som metode i
  Stripe-kassen;
* deres `enabled` **arves** — den gemmes ikke pr. gateway. Målt: en skrivning til
  `woocommerce_stripe_klarna_settings['enabled'] = 'no'` opretter optionen, men et nyt request
  svarer stadig `enabled: yes`. **Switchen kan ikke slås fra i WooCommerce.**

Det sidste blev prøvet og rullet tilbage: de tre options blev skrevet, målt virkningsløse og
slettet igen. Efterprøvet bagefter: tændte gateways og kasse-listen er **identisk** med
udgangspunktet (5 tændt · `stripe`, `bacs` i kassen). Der er ikke efterladt nogen ændring.

## 3. Stripe mangler intet

| Evne på kontoen | Tilstand |
|---|---|
| `card_payments` | active |
| `amazon_pay_payments` | active |
| `klarna_payments` | active |
| `link_payments` | active |

Kontoen har 17 evner i cachen, alle `active`. Valutaen er heller ikke hindringen: både Amazon
Pay og Klarna lister **DKK** blandt deres understøttede valutaer i Woo's egen gateway-klasse.
Der er altså intet at slå til i Stripe Dashboard for at fjerne den målte tilstand — tilstanden
er en arkitektur, ikke en mangel.

## 4. Hvad der stadig er ejerens (og hvad der ikke er målt her)

Om Klarna, Amazon Pay og Link **vises til kunden** i den nye kasse styres af Stripes egen
metodekonfiguration for kontoen — ikke af Woo's gateway-liste. Det er **ikke målt** her: der
kræves en rigtig kurv og en kasse-session, og svaret ligger i Stripe, ikke i WordPress.
Vil ejeren have dem synlige, er vejen Stripe Dashboard → Settings → Payment methods; det er
en anden handling end den §4d C beskrev, og den kan agenten ikke udføre.

## 5. Så det ikke rådner igen

`tools/payment_methods.py` måler listen og **skelner** de to tilstande, så en fremtidig
måling ikke råber ulv:

* `FAIL` kun når en tændt gateway er uvalgbar **uden** at være en OCS-skygge (fx en
  selvstændig MobilePay-gateway uden nøgler), eller når kassen ikke tilbyder kortbetaling;
* `WARN` når en skygge mangler en aktiv Stripe-evne — dét er ejerens;
* `PASS` for skygger, der bare er dubletter.

Kør `python3 tools/payment_methods.py` (kræver MCP-token), eller døm et gemt svar offline med
`--from-file`. 13 offline-fixtures i `tools/test_payment_methods.py` holder klassificeringen
fast; de kører i `tools/natlige-vaern.sh`.

Målt 12/11 i drift: **8 PASS · 0 WARN · 0 FAIL** — `5 tændt · 2 tilgængelig i kassen · 3 skygger`.

## 6. Status

§4d C er **lukket 12/11** som en rettelse af formuleringen, ikke som fremgang i procent:
S4 står uændret **4,05 af 4,05 d**, og ingen projektprocent er flyttet. Læren er den samme som
K's: en indstilling der *ser* forkert ud, er ikke det samme som en funktion der mangler — læs
koden der læser feltet, før punktet erklæres åbent.
