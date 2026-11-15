# Google Pay-ikonet i kassen — advarslen er Googles egen tomme placeholder (15/11 2026)

**Kort:** Konsoladvarslen

> `Failed to download or decode a non-empty icon for payment app with "https://pay.google.com/gp/p/web_manifest.json" manifest.`

kommer fra **Chrome**, ikke fra sitet. Chromes payment-handler henter Googles
web-app-manifest for at vise Google Pay som betalingsapp; manifestet svarer 200 og peger på
`https://www.gstatic.com/instantbuy/svg/transparent_square.svg`, som er et **tomt** SVG
(`<svg width="41" height="41" xmlns="http://www.w3.org/2000/svg"></svg>` — nul tegneelementer).
Chrome kan derfor ikke dekode et ikke-tomt ikon og logger advarslen. Den fjerner eller ændrer
**ingen** betalingsmetode, og den kan ikke rettes i sitets kode.

Målt 15/11 2026 mod den levende butik (Chrome 156 i cloud-browseren, `main` 9b90737).

## 1. Hvad der faktisk blev målt

| Måling | Resultat |
|---|---|
| `pay.google.com/gp/p/web_manifest.json` | **200**, `application/json`, 330 bytes, `access-control-allow-origin: *` |
| Manifestets ikon | `https://www.gstatic.com/instantbuy/svg/transparent_square.svg` |
| Ikonets indhold | **200**, `image/svg+xml`, men `<svg width="41" height="41" …></svg>` — **tomt** |
| `pay.google.com/gp/p/js/pay.js` | **200**, 249 208 bytes |
| `pay.google.com`-referencer i leveret `/checkout/`-HTML | **0** (alt hentes af Stripe.js/Chrome i runtime) |
| Sikker kontekst | `window.isSecureContext === true`; certifikat Let's Encrypt, `Verify return code: 0` |
| Express-knapper i kassen | 4 iframes: **Amazon Pay, Apple Pay, Google Pay, Link** (163 × 48 px), Google Pay-tegningen vises korrekt |
| Klik på Google Pay-knappen | åbner `pay.google.com/gp/p/ui/payframe` (200), ingen fejl i UI |
| Betalingsmetode i kassen | `stripe` (kort + wallets). `bacs` er `enabled=no` (målt 15/11; var tændt 12/11) |
| `tools/payment_methods.py` | **8 PASS · 0 WARN · 0 FAIL** |

Advarslen er ikke stabil fra load til load: ved genindlæsning af kassen skiftede Chrome mellem
ikon-advarslen og `Unable to download payment manifest "https://pay.google.com/gp/p/payment_method_manifest.json"`
(og varianter for `google.com/pay`). Alle er de samme **Blink > Payments**-beskeder fra Chromes
egen payment-handler-crawler.

## 2. Hvorfor det ikke påvirker kundens betalingsvalg

1. **Ikonet bruges kun til at forvælge appen.** Chromiums
   `ServiceWorkerPaymentApp::CanPreselect()` kræver et ikon, der ikke er tomt; mangler det, kan
   Google Pay ikke *forvælges* i Chromes eget betalingsark — men `IsCompleteForPayment()` returnerer
   stadig sandt, så appen er tilgængelig. Ingen metode fjernes.
2. **Stripe tegner sin egen Google Pay-knap.** Express Checkout-elementet ligger i en
   `js.stripe.com`-iframe med sit eget brand og åbner Google Pay via `pay.js`/`payframe` — ikke via
   manifest-ikonet. Målt: knappen renderes korrekt, og klik åbner Googles betalingsframe.
3. **Google Pay understøttes i Danmark.** Stripe lister Danmark blandt kundelande for Google Pay og
   DKK blandt valutaerne; knappen vises kun i understøttede lande. Kortbetaling via `stripe` er
   uafhængig af dette og altid tilgængelig.
4. **Advarslen er konsol-only.** Den har ingen synlig effekt og blokerer ikke `Place Order`,
   kortfelterne eller kasseflowet.

**Konklusion:** advarslen er støj fra Googles egen placeholder, ikke en mangel i butikken. Der er
intet at rette, og den skal **ikke** "repareres" ved at fjerne Google Pay-knappen — det ville fjerne
en betalingsmulighed for at skjule en advarsel, kunden aldrig ser.

## 3. Hvad der ikke kunne måles her

En **rigtig** Google Pay-betaling kan ikke gennemføres i cloud-browseren: Chromes
payment-request-lag rapporterer `SSL certificate is not valid. Security level: NONE.` og
`No UI will be shown…` og afviser at vise betalingsarket. Det er browser-sandboxens
TLS-intercept, ikke sitet: certifikatet er gyldigt (Let's Encrypt, verify 0), og `isSecureContext`
er sand. I en almindelig Chrome på en telefon eller desktop med Google Pay opsat åbner knappen
Googles betalingsark; selve gennemførslen er ikke målt her.

Samme begrænsning gælder Apple Pay i den pågældende browser (Chrome på Windows), hvor knappen
vises, men Apple Pay kræver Safari/macOS eller en understøttet enhed.

## 4. Drift bemærket undervejs (ikke rettet)

`bacs` (bankoverførsel) er `enabled=no` den 15/11, hvor `docs/library/betalingsmetoder-2026-11-12.md`
målte den som tilgængelig i kassen. Det er en ændring i betalingsudvalget, ikke i denne sag; den er
ikke rettet, fordi det er ejerens beslutning om bankoverførsel skal tilbydes. `tools/payment_methods.py`
melder stadig **8 PASS · 0 WARN · 0 FAIL**, fordi mindst én metode (kort) er tilgængelig.
