# S4 målt og delvist lukket — fortrydelse og størrelseskort, 8/11

S4 var den sidste åbne blok i site-roadmappet (2,25 af 5,25 d). Her er hvert punkt målt mod sitet, og det der
kunne gøres uden ejerens credentials, adgang eller juridiske valg er gennemført og målt igen.

## Gennemført

**S4.4 — fortrydelsesfunktion (+0,30 d).** `/right-of-withdrawal/` (1133) havde procedure og EU-modellen, men
ingen funktion, fordi WPForms ikke kan oprette formularer over MCP (og slet ikke er installeret). En mu-plugin,
`sgap-shop-compliance.php`, giver nu:

- `POST /wp-json/sgap/v1/withdrawal` med ordrenummer, navn, e-mail, hvilke varer og en valgfri grund.
  Ugyldigt input giver **400** med en læsbar besked; gyldigt gemmer i optionen `sgap_withdrawals` (autoload off)
  og sender en mail til butikkens adresse med 14-dages-svaret.
- Shortcode `[sgap_withdrawal_form]` sat ind nederst på siden.

**Målt:** `curl -X POST` med gyldige felter gav `{"ok":true,"id":1,"count":1}` og posten
`{"order":"TEST-1001","items":"1x Hoodie","at":"2026-11-08 12:26:48"}`; samme kald uden felter gav
`{"ok":false,"error":"Order number, a valid email and the items are required."}`. Siden svarer **200** og
indeholder formularen. Testposten er ryddet (0 tilbage).

**S4.8 — størrelseskort i cm og inches (+0,50 d).** Otte tøjprodukter (399, 401, 403, 413, 557, 954, 1255, 1282)
har nu et størrelseskort med bryst, længde og ærme i **både cm og inches** plus flad-målings-tippen.

Vejen derhen kostede tre forsøg og er værd at huske:

1. `woocommerce_product_tabs` bliver **ikke** kørt af Extendables produkt-skabelon — fanen dukkede aldrig op.
2. Blokke i `post_content` bliver **ikke** vist: temaet viser kun den korte beskrivelse.
3. Shortcodes i `post_excerpt` bliver ikke kørt, og rå HTML i uddraget bliver **strippet** af kses.

Det der virker: `woocommerce_short_description`-filteret, som temaet faktisk kalder. Målt i browser:
`Chest (cm) · Chest (in) · S 46-51 · 18.1-20.1` står som en rigtig tabel på produktsiden, og `/shop/` har
**0** forekomster (ingen lækage til produktkortene).

## Afgrænset — kræver ejeren

| Punkt | Hvorfor det står åbent |
| --- | --- |
| S4.1 Complianz til EU opt-in | Juridisk konfiguration af samtykkemodel. Målt nu: `regions` og `consent_types` er stadig US/optout, og banneret indlæses som `banner-1-optout.css`. Ændringen vælger butikkens juridiske model — det er ejerens valg, ikke en teknikfejl |
| S4.2 CVR, adresse, telefon | Kræver de faktiske oplysninger; intet kan gættes |
| S4.3 Valuta og priser | USD → EUR/DKK kræver omregning af alle 39 priser og en beslutning om prispunkt |
| S4.5 Betalingsmetoder | MobilePay/PayPal/Klarna kræver aftaler og nøgler |
| S4.6 Forsendelse | Zoner, klasser og fri fragt-grænse er forretningsvalg; W3TC-indstillingerne kan ikke sættes over MCP |
| S4.7 Produktbilleder | Printify-adgang (samme blokering som rebrand-batch 4) |
| S4.9 Produkttekst, GPSR-del | GPSR-blokken kræver CVR/adresse (S4.2). Fit/produktion/levering blev forsøgt lagt i samme filter, men temaet afskærer uddraget efter tabellen, så blokken er **ikke** synlig — derfor 0 d bogført |
| S4.11 `/shop/` performance | Målt 8/11: **262 736 bytes / 1,39 s** (roadmap'ens tal var 470 KB / 5,8 s). Caching-laget kan stadig ikke styres over MCP |

## Fælden

En `preg_replace`-oprydning blev kaldt med `wp_update_post(array(...) . '')`, hvilket er en fatal i PHP 8
(array + string). Mu-pluginnet var allerede skrevet, så sitet var oppe, men oprydningen kørte ikke — rettet i
næste kald og verificeret med `php -l` og en frisk måling.
