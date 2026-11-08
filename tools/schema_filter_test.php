<?php
/**
 * schema_filter_test.php — tester schema-filteret uden WordPress.
 *
 * Kør:  tools/_deps/bin/php tools/schema_filter_test.php
 * Ind:  tools/fixtures/product-schema-before.json  (rigtigt JSON-LD fanget fra en produktside)
 * Ud:   PASS/FAIL pr. påstand; exit 0 kun hvis alle består.
 */

require_once __DIR__ . '/../wordpress/mu-plugins/sgap-product-schema.php';

$failures = 0;

function t(string $label, bool $ok, $detail = null): void
{
    global $failures;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label;
    if (!$ok && $detail !== null) {
        echo '  -> ' . (is_string($detail) ? $detail : json_encode($detail));
    }
    echo PHP_EOL;
}

$fixture = __DIR__ . '/fixtures/product-schema-before.json';
if (!is_file($fixture)) {
    fwrite(STDERR, "mangler fixture: $fixture\n");
    exit(2);
}

$raw = json_decode((string) file_get_contents($fixture), true);
if (!is_array($raw) || empty($raw['graph'])) {
    fwrite(STDERR, "fixture kan ikke læses som {graph: [...]}\n");
    exit(2);
}

$before = $raw['graph'];

// --- før: dokumentér udgangspunktet, så testen ikke kan bestå af sig selv
$product = null;
foreach ($before as $entity) {
    if (is_array($entity) && in_array('Product', (array) ($entity['@type'] ?? []), true)) {
        $product = $entity;
    }
}
t('fixture indeholder et Product', $product !== null);
t('udgangspunkt: intet brand', empty($product['brand']), $product['brand'] ?? null);
t('udgangspunkt: ingen returret', empty($product['offers']['hasMerchantReturnPolicy']));
t('udgangspunkt: ingen leveringstid', empty($product['offers']['shippingDetails']));

$after = sgap_product_schema_enrich($before);

$enriched = null;
foreach ($after as $entity) {
    if (is_array($entity) && in_array('Product', (array) ($entity['@type'] ?? []), true)) {
        $enriched = $entity;
    }
}
t('Product findes stadig efter berigelse', $enriched !== null);

// --- efter: felterne er der, med de værdier sitet selv oplyser
t('brand = SteamGears', ($enriched['brand']['name'] ?? '') === 'SteamGears', $enriched['brand'] ?? null);
t('brand har @type Brand', ($enriched['brand']['@type'] ?? '') === 'Brand');

$policy = $enriched['offers']['hasMerchantReturnPolicy'] ?? [];
t('returret: 14 dage', ($policy['merchantReturnDays'] ?? 0) === 14, $policy);
t('returret: finite return window',
    ($policy['returnPolicyCategory'] ?? '') === 'https://schema.org/MerchantReturnFiniteReturnWindow');
t('returret: gælder DK', in_array('DK', (array) ($policy['applicableCountry'] ?? []), true));
t('returret: gælder hele EU (27 lande)', count((array) ($policy['applicableCountry'] ?? [])) === 27,
    $policy['applicableCountry'] ?? null);

$ship = $enriched['offers']['shippingDetails'] ?? [];
t('levering: to destinationer', count((array) $ship) === 2, count((array) $ship));
$dk = null;
foreach ((array) $ship as $detail) {
    $dest = $detail['shippingDestination']['addressCountry'] ?? null;
    if ($dest === 'DK') {
        $dk = $detail;
    }
}
t('levering: DK findes som destination', $dk !== null);
t('levering: DK 6-10 dage',
    ($dk['deliveryTime']['transitTime']['minValue'] ?? 0) === 6
    && ($dk['deliveryTime']['transitTime']['maxValue'] ?? 0) === 10, $dk);
t('levering: unitCode DAY', ($dk['deliveryTime']['transitTime']['unitCode'] ?? '') === 'DAY');
t('levering: ingen opdigtet fragtpris', !isset($dk['shippingRate']));

// Produktionstiden er sitets egen tekst (/shipping/): 3-7 hverdage foer afsendelse. Google
// forventer at den staar som handlingTime ved siden af transitTime, ikke som en del af den.
t('levering: DK har produktionstid 3-7 dage',
    ($dk['deliveryTime']['handlingTime']['minValue'] ?? 0) === 3
    && ($dk['deliveryTime']['handlingTime']['maxValue'] ?? 0) === 7, $dk['deliveryTime'] ?? null);
t('levering: produktionstid i DAY',
    ($dk['deliveryTime']['handlingTime']['unitCode'] ?? '') === 'DAY');

$eu = null;
foreach ((array) $ship as $detail) {
    $dest = $detail['shippingDestination']['addressCountry'] ?? null;
    if (is_array($dest) && count($dest) === 26) {
        $eu = $detail;
    }
}
t('levering: resten af EU findes (26 lande)', $eu !== null);
t('levering: EU 7-12 dage',
    ($eu['deliveryTime']['transitTime']['minValue'] ?? 0) === 7
    && ($eu['deliveryTime']['transitTime']['maxValue'] ?? 0) === 12, $eu);
t('levering: EU har ogsaa produktionstid', ($eu['deliveryTime']['handlingTime']['maxValue'] ?? 0) === 7);
t('levering: kun ét deliveryTime pr. destination', count((array) ($dk['deliveryTime'] ?? [])) === 3);

// Nye varer, ikke brugte: butikken er print-on-demand og saelger ikke retourvarer.
t('offer: itemCondition = NewCondition',
    ($enriched['offers']['itemCondition'] ?? '') === 'https://schema.org/NewCondition',
    $enriched['offers']['itemCondition'] ?? null);
t('offer: en allerede sat itemCondition overskrives ikke',
    (sgap_schema_enrich_offer(['itemCondition' => 'https://schema.org/UsedCondition'])['itemCondition'] ?? '')
    === 'https://schema.org/UsedCondition');

// --- idempotens: filtre kan køre flere gange (Rank Math + cache)
$twice = sgap_product_schema_enrich($after);
$twiceProduct = null;
foreach ($twice as $entity) {
    if (is_array($entity) && in_array('Product', (array) ($entity['@type'] ?? []), true)) {
        $twiceProduct = $entity;
    }
}
t('idempotent: samme JSON ved anden kørsel', $twiceProduct === $enriched);

// --- ikke-Product-entiteter må ikke ændres
$nonProduct = ['@type' => 'WebPage', 'name' => 'Test'];
t('WebPage røres ikke', sgap_schema_enrich_product($nonProduct) === $nonProduct);

// --- offers som liste skal også håndteres
$multiple = ['@type' => 'Product', 'offers' => [
    ['@type' => 'Offer', 'price' => '1.00'],
    ['@type' => 'Offer', 'price' => '2.00'],
]];
$enrichedMulti = sgap_schema_enrich_product($multiple);
t('flere offers: begge beriget',
    isset($enrichedMulti['offers'][0]['hasMerchantReturnPolicy'], $enrichedMulti['offers'][1]['hasMerchantReturnPolicy']));
t('flere offers: begge har itemCondition',
    isset($enrichedMulti['offers'][0]['itemCondition'], $enrichedMulti['offers'][1]['itemCondition']));

// Graensen mod fabrikation: returfragten maa ikke pludselig staa som gratis.
t('returret: ingen opdigtet returnFees', !isset($policy['returnFees']), $policy['returnFees'] ?? null);
t('returret: returnMethod er ReturnByMail',
    ($policy['returnMethod'] ?? '') === 'https://schema.org/ReturnByMail');

echo PHP_EOL;
echo $failures === 0 ? "OK — alle påstande består\n" : "FEJL — $failures påstand(e) fejlede\n";
exit($failures === 0 ? 0 : 1);
