# Produkt-schema: brand, returret og leveringstid (2026-11-02)

Første konkrete fix efter den korrigerede SEO-audit — fund **D2** (Product-schema uden returret og fragt) og
**D5** (brand mangler). Koden ligger klar i repoet; den er **ikke** live endnu, fordi skriveadgang til
WordPress er blokeret (401).

## 1. Udgangspunktet, målt

JSON-LD på en produktside (`/product/airship-armada-…/`, hentet 2/11 2026):

```
Product: @id, @type, category, description, image, mainEntityOfPage, name, offers, sku
Offer:   @type, availability, price, priceCurrency, priceSpecification,
         priceValidUntil, seller, url
```

| Felt | Status før | Konsekvens |
| --- | --- | --- |
| `brand` | mangler på 34 af 39 | ingen mærkeidentitet i Shopping/Merchant-listen |
| `offers.hasMerchantReturnPolicy` | 0 af 39 | Merchant Center kræver det for handelsvarer i EU |
| `offers.shippingDetails` | 0 af 39 | samme |
| `aggregateRating` | 0 af 39 | må **ikke** opdigtes — se afsnit 3 |
| `priceCurrency` | USD på alle 39 | ejerbeslutning, ikke schema — se afsnit 3 |

## 2. Værdierne — hentet fra sitets egne sider, ikke gættet

| Værdi | Kilde | Ordret |
| --- | --- | --- |
| Returret 14 dage, hele EU | `/terms/` | »EU consumers have 14 days to withdraw from the purchase from the day the item is received, per EU consumer law.« |
| Returret 14 dage | `/delivery/` | »Returns — EU customers have a 14-day right of withdrawal.« |
| Levering Danmark 6–10 hverdage | `/delivery/` | »Denmark: 6-10 business days« |
| Levering resten af EU 7–12 hverdage | `/delivery/` | »Rest of EU: 7-12 business days« |
| Returmetode | `/delivery/` | »See the Refund &amp; Returns page for the full policy« — retur sendes med post |

Schema deraf: `MerchantReturnPolicy` med `merchantReturnDays: 14`, `MerchantReturnFiniteReturnWindow`,
`returnMethod: ReturnByMail` og `applicableCountry` = de 27 EU-lande; `OfferShippingDetails` med to
destinationer (`DK`, resten af EU) og `ShippingDeliveryTime` med `transitTime` 6–10 henholdsvis 7–12 dage.

## 3. Bevidst udeladt

| Felt | Hvorfor ikke |
| --- | --- |
| `aggregateRating` | Sitet har ingen anmeldelser. Opdigtede ratings er en overtrædelse af Googles retningslinjer og kan koste hele den strukturererede data. Ratings skal komme fra rigtige kunder. |
| `shippingRate` | `/delivery/` siger »Exact shipping options and prices are shown at checkout«, og `/terms/` siger »Shipping costs are shown before you confirm your order«. Der findes ingen fast pris at angive. |
| `returnFees` | Ingen side siger hvem der betaler returfragten. At gætte `FreeReturn` ville være et løfte butikken ikke har givet. |
| `priceCurrency` | Butikken prissætter i USD (fund **E5**). Det er en kommerciel beslutning — skal butikken vise DKK, er det en prisindstilling i WooCommerce, ikke et schema-filter. |

## 4. Filerne

| Fil | Hvad den gør |
| --- | --- |
| [`wordpress/mu-plugins/sgap-product-schema.php`](../../wordpress/mu-plugins/sgap-product-schema.php) | Filter på `rank_math/json_ld` der beriger Product og dets Offer. Idempotent: felter der findes i forvejen røres ikke. |
| [`tools/schema_filter_test.php`](../../tools/schema_filter_test.php) | Tester filteret mod et rigtigt JSON-LD-udsnit, uden WordPress. |
| `tools/fixtures/product-schema-before.json` | Udsnittet fra den live side — testen kan ikke bestå af sig selv. |
| [`tools/schema_check.py`](../../tools/schema_check.py) | Måler de live sider og siger hvor mange produkter der stadig mangler felter. |

Filteret er lagt som **mu-plugin** og ikke i `plugins/steamgears-autopilot/`, fordi pluginnets zip skal være
byte-identisk med kilden (se agentprofilen §6). En separat fil kan deployes og rulles tilbage uden at røre
autopiloten.

## 5. Deploy — når skriveadgangen er der

1. Læg `wordpress/mu-plugins/sgap-product-schema.php` i `wp-content/mu-plugins/` (mappen findes måske ikke;
   opret den). Mu-plugins aktiveres automatisk og kan ikke deaktiveres ved et uheld.
2. Alternativt: indsæt filens indhold via Royal MCP som et snippet-plugin, hvis MCP-adgangen er etableret
   (`ROYAL_MCP_TOKEN` som `Bearer`, se [`../mcp-auth.md`](../mcp-auth.md)).
3. Ryd cache (W3TC), så JSON-LD ikke serveres fra en gammel side.
4. Kør verifikationen nedenfor.

Rul tilbage ved at slette filen — der er ingen databaseændring, ingen option og intet cron.

## 6. Verifikation

```bash
# 1. Filteret logisk korrekt (ingen WordPress nødvendig)
tools/_deps/bin/php tools/schema_filter_test.php

# 2. PHP-syntaks
tools/_deps/bin/php -l wordpress/mu-plugins/sgap-product-schema.php

# 3. Live: hvor mange produkter har felterne nu?
python3 tools/schema_check.py --limit 5     # hurtigt kig
python3 tools/schema_check.py               # alle 39, exit 0 = alle har brand + returret + levering
```

Før deploy forventes `fuldt udfyldt: 0` og exit 1 — det er den målte baseline, ikke en fejl i værktøjet.
Efter deploy skal tallet være 39. Google genlæser struktureret data inden for dage til uger; Merchant Center
har sin egen genindlæsning.

## 7. Næste schema-skridt, når dette er live

1. `priceCurrency` — afklar med ejeren om butikken skal prissætte i DKK (fund E5).
2. Anmeldelser — den eneste ærlige vej til `aggregateRating` er en review-funktion med rigtige kunder.
3. `hasVariant`/størrelser, hvis Printify-variantmapping bliver tilgængelig (kræver ejerens variantdata).
