<?php
/**
 * Plugin Name: Steamgears — Product schema (Rank Math)
 * Description: Tilføjer brand, returret og leveringstid til Product/Offer-JSON-LD på steamgears.eu.
 * Version: 1.0.0
 * Author: Steamgears.eu
 * License: GPL-2.0-or-later
 *
 * Installeres i wp-content/mu-plugins/ (eller som almindeligt plugin).
 *
 * Værdierne er ikke gættet: de kommer fra sitets egne sider, målt 2/11 og 6/11 2026.
 *   /terms/    "EU consumers have 14 days to withdraw from the purchase from the day the item is received"
 *   /delivery/ "Denmark: 6-10 business days" · "Rest of EU: 7-12 business days"
 *   /shipping/ "Production: your item is printed and finished — 3-7 business days for printed
 *               goods", derefter 6-10 dage i DK og 7-12 i resten af EU. Derfor staar
 *               produktionstiden som handlingTime og fragtperioden som transitTime; Google
 *               behandler dem som to led i samme leveringstid.
 *
 * Bevidst udeladt, fordi sitet ikke oplyser det (og gæt ville være fabrikation):
 *   aggregateRating  — ingen anmeldelser på sitet; opdigtede ratings er en Google-overtrædelse
 *   shippingRate     — "Shipping costs are shown before you confirm your order", ingen fast pris
 *   returnFees       — ingen side siger hvem der betaler returfragten
 *   priceCurrency    — butikken prissætter i USD; det er en ejerbeslutning, ikke en schema-fix
 *
 * Konsekvens, som er vaerd at kende: Google kraever shippingRate.value eller .maxValue for at
 * vise fragtoplysningen i merchant listings (Search Central, OfferShippingDetails). Uden en fast
 * fragtpris er markupen gyldig og returretten kvalificerer, men selve fragt-linjen bliver ikke
 * vist. Det kan kun loeses ved at butikken oplyser en pris — ikke ved at gaette en.
 */

if (!defined('SGAP_SCHEMA_VERSION')) {
    define('SGAP_SCHEMA_VERSION', '1.0.0');
}

if (!defined('SGAP_RETURN_DAYS')) {
    define('SGAP_RETURN_DAYS', 14);
}

// Produktionstid for trykte varer, fra /shipping/ og /delivery/. Den kommer foer fragtperioden.
if (!defined('SGAP_HANDLING_DAYS_MIN')) {
    define('SGAP_HANDLING_DAYS_MIN', 3);
}

if (!defined('SGAP_HANDLING_DAYS_MAX')) {
    define('SGAP_HANDLING_DAYS_MAX', 7);
}

/**
 * EU-landene. Returretten på 14 dage gælder hele EU, ikke kun Danmark.
 *
 * @return string[]
 */
function sgap_schema_countries_eu(): array
{
    return ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU',
            'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];
}

/**
 * @return string[]
 */
function sgap_schema_countries_eu_rest(): array
{
    return array_values(array_diff(sgap_schema_countries_eu(), ['DK']));
}

/**
 * 14 dages fortrydelsesret, retur med post.
 *
 * @return array<string, mixed>
 */
function sgap_schema_return_policy(): array
{
    return [
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => sgap_schema_countries_eu(),
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => SGAP_RETURN_DAYS,
        'returnMethod' => 'https://schema.org/ReturnByMail',
    ];
}

/**
 * Leveringstid pr. destination: produktion (handlingTime) plus fragt (transitTime).
 * Ingen fragtpris, fordi sitet ikke har en fast.
 *
 * @return array<int, array<string, mixed>>
 */
function sgap_schema_shipping_details(): array
{
    return [
        [
            '@type' => 'OfferShippingDetails',
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'DK'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => SGAP_HANDLING_DAYS_MIN,
                    'maxValue' => SGAP_HANDLING_DAYS_MAX,
                    'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 6,
                    'maxValue' => 10,
                    'unitCode' => 'DAY',
                ],
            ],
        ],
        [
            '@type' => 'OfferShippingDetails',
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => sgap_schema_countries_eu_rest(),
            ],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => SGAP_HANDLING_DAYS_MIN,
                    'maxValue' => SGAP_HANDLING_DAYS_MAX,
                    'unitCode' => 'DAY',
                ],
                'transitTime' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => 7,
                    'maxValue' => 12,
                    'unitCode' => 'DAY',
                ],
            ],
        ],
    ];
}

/**
 * Er arrayet en liste (0,1,2 ...) og ikke et associeret array?
 *
 * @param array<mixed> $value
 */
function sgap_schema_is_list(array $value): bool
{
    return array_keys($value) === range(0, count($value) - 1);
}

/**
 * @param array<string, mixed> $offer
 * @return array<string, mixed>
 */
function sgap_schema_enrich_offer(array $offer): array
{
    // Varerne laves paa bestilling og er nye; brugte varer findes ikke i butikken.
    if (empty($offer['itemCondition'])) {
        $offer['itemCondition'] = 'https://schema.org/NewCondition';
    }
    if (empty($offer['hasMerchantReturnPolicy'])) {
        $offer['hasMerchantReturnPolicy'] = sgap_schema_return_policy();
    }
    if (empty($offer['shippingDetails'])) {
        $offer['shippingDetails'] = sgap_schema_shipping_details();
    }

    return $offer;
}

/**
 * Beriger ét Product-objekt. Andre typer sendes uændret igennem.
 *
 * @param array<string, mixed> $entity
 * @return array<string, mixed>
 */
function sgap_schema_enrich_product(array $entity): array
{
    $types = $entity['@type'] ?? [];
    $types = is_array($types) ? $types : [$types];

    if (!in_array('Product', $types, true)) {
        return $entity;
    }

    if (empty($entity['brand'])) {
        $entity['brand'] = ['@type' => 'Brand', 'name' => 'SteamGears'];
    }

    if (!empty($entity['offers']) && is_array($entity['offers'])) {
        if (sgap_schema_is_list($entity['offers'])) {
            foreach ($entity['offers'] as $i => $offer) {
                if (is_array($offer)) {
                    $entity['offers'][$i] = sgap_schema_enrich_offer($offer);
                }
            }
        } else {
            $entity['offers'] = sgap_schema_enrich_offer($entity['offers']);
        }
    }

    return $entity;
}

/**
 * Går gennem hele Rank Math-grafen. Idempotent: felter der allerede findes røres ikke.
 *
 * @param array<mixed> $graph
 * @return array<mixed>
 */
function sgap_product_schema_enrich(array $graph): array
{
    foreach ($graph as $key => $entity) {
        if (is_array($entity)) {
            $graph[$key] = sgap_schema_enrich_product($entity);
        }
    }

    return $graph;
}

if (function_exists('add_filter')) {
    add_filter('rank_math/json_ld', static function ($data) {
        return is_array($data) ? sgap_product_schema_enrich($data) : $data;
    }, 99, 2);
}
