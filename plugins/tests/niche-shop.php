<?php
/**
 * 7.64.0: niche-butikken — ét felt bliver til en plan og en reversibel kladde-udrulning.
 * Required fra smoke.php. Alt her kører uden WooCommerce, fordi planen er ren PHP.
 *
 * Smoke-harnesset har ingen slette- eller term-API, fordi ingen andre moduler rører den.
 * Rollback gør, så den del af WordPress stubber vi her — ellers kunne testen ikke se
 * forskel på "slettede kladder" og "et fatalt kald".
 */
if (!function_exists('wp_delete_post')) {
    function wp_delete_post($id, $force = false) { $GLOBALS['deleted_posts'][] = (int) $id; unset($GLOBALS['poststatus'][$id]); return true; }
}
if (!function_exists('get_term')) {
    function get_term($id, $taxonomy = '') { return $GLOBALS['terms'][(int) $id] ?? null; }
}
if (!function_exists('wp_delete_term')) {
    function wp_delete_term($id, $taxonomy) { $GLOBALS['deleted_terms'][] = (int) $id; unset($GLOBALS['terms'][(int) $id]); return true; }
}
if (!function_exists('term_exists')) {
    function term_exists($slug, $taxonomy = '') { return $GLOBALS['existing_terms'][$slug] ?? null; }
}

// 1. Planen: deterministisk, uden WordPress-data.
$plan = SGAP_Niche_Shop::plan('steampunk airship', ['count' => 10, 'margin' => 60]);
check('niche: a plan is an array with the keys the UI reads', is_array($plan)
    && isset($plan['plan_key'], $plan['brand']['suggested'], $plan['collections'], $plan['products'], $plan['pages'], $plan['readiness'], $plan['counts']));
check('niche: same niche and settings give the same run key', $plan['plan_key'] === SGAP_Niche_Shop::plan('steampunk airship', ['count' => 10, 'margin' => 60])['plan_key']);
check('niche: another count is another run', $plan['plan_key'] !== SGAP_Niche_Shop::plan('steampunk airship', ['count' => 12, 'margin' => 60])['plan_key']);
check('niche: the archetype is found from the words', $plan['archetype']['id'] === 'steampunk' && in_array('airship', $plan['archetype']['matched'], true));
check('niche: five collections, each with its own slug', count($plan['collections']) === 5 && count(array_unique(array_column($plan['collections'], 'slug'))) === 5);
check('niche: the wanted product count is honoured and slugs are unique', count($plan['products']) === 10 && count(array_unique(array_column($plan['products'], 'slug'))) === 10);
check('niche: every product has a price above cost and a margin that clears the floor', (function ($plan) {
    foreach ($plan['products'] as $product) {
        if ($product['price'] <= $product['cost'] || $product['margin_percent'] === null || $product['margin_percent'] < 60) {
            return false;
        }
    }
    return true;
})($plan));
check('niche: costs are marked as estimates until Printful is connected', count(array_unique(array_column($plan['products'], 'cost_source'))) === 1 && $plan['products'][0]['cost_source'] === 'estimate');
check('niche: every SKU is unique and prefixed', count(array_unique(array_column($plan['products'], 'sku'))) === 10 && str_starts_with($plan['products'][0]['sku'], 'SG-'));
check('niche: five pages, start-here first', count($plan['pages']) === 5 && $plan['pages'][0]['slug'] === 'start-here');
check('niche: the owner-only facts are marked, not guessed', $plan['counts']['owner_fields'] > 0 && str_contains($plan['pages'][1]['body'], '[EJER'));
check('niche: the menu is the shop skeleton', $plan['menu'] === ['Start here', 'Shop', 'Airship', 'Clockwork', 'Herbarium', 'Guides', 'About', 'Contact']);
check('niche: the readiness list is owner-only and names Printful first', ($plan['readiness'][0]['id'] ?? '') === 'printful' && count(array_filter($plan['readiness'], fn($r) => empty($r['owner']))) === 0);
check('niche: schema covers the types the shop page needs', in_array('ItemList', $plan['schema'], true) && in_array('Product', $plan['schema'], true));

// 2. Arketyper: ordene bestemmer hylden, og ukendte nicher får den generiske.
check('niche: cottagecore is recognised without the exact phrase', SGAP_Niche_Shop::archetype('cottagecore mushroom')['id'] === 'cottagecore');
check('niche: "dark academia library" beats a looser match', SGAP_Niche_Shop::archetype('dark academia library')['id'] === 'dark_academia');
check('niche: an unknown niche falls back to neutral and says so', SGAP_Niche_Shop::archetype('bespoke tax advisory')['id'] === 'neutral');
check('niche: an empty niche is refused, not guessed', is_wp_error(SGAP_Niche_Shop::plan('a')) && SGAP_Niche_Shop::plan('a')->get_error_code() === 'sgap_niche_short');

// 3. Prisen: margin-gulvet holdes, og prisen ser ud som en butikspris.
check('niche: the floor price never lands below the margin', SGAP_Niche_Shop::price(10.0, 60) >= 16.0);
check('niche: a high margin raises the price instead of undercutting it', SGAP_Niche_Shop::price(10.0, 200) > SGAP_Niche_Shop::price(10.0, 60));
check('niche: prices end in ,95', str_ends_with((string) SGAP_Niche_Shop::price(12.5, 45), '.95'));

// 4. Kørslen: uden WooCommerce skrives intet, og intet går tabt.
$dry = SGAP_Niche_Shop::apply($plan, false);
check('niche: a dry run reports what it would create and writes nothing', $dry['dry_run'] === true && $dry['would_create']['products'] === 10 && SGAP_Niche_Shop::runs() === []);
$no_woo = SGAP_Niche_Shop::apply($plan, true);
check('niche: without WooCommerce the apply is refused with a reason', is_wp_error($no_woo) && $no_woo->get_error_code() === 'sgap_niche_no_woo');
check('niche: a refused apply leaves no run behind', SGAP_Niche_Shop::runs() === []);
check('niche: rolling back an unknown key is refused', is_wp_error(SGAP_Niche_Shop::rollback('doesnotexist')));
check('niche: the REST layer is registered on the plugin namespace', in_array('/niche', $GLOBALS['routes']['sgap/v1'] ?? [], true) && in_array('/niche/rollback', $GLOBALS['routes']['sgap/v1'] ?? [], true));

// 5. Run-bogen: en kørsel kan ryddes op, og kun dens egne kladder rammes.
$GLOBALS['opts'][SGAP_Niche_Shop::RUNS] = ['abc123abc123' => [
    'plan_key' => 'abc123abc123', 'niche' => 'steampunk airship', 'archetype' => 'steampunk',
    'created_at' => '2026-11-08T12:00:00+00:00', 'user' => 1,
    'created' => [
        'collections' => [['id' => 501, 'slug' => 'airship', 'name' => 'Airship']],
        'products' => [['id' => 601, 'slug' => 'airship-armada-tee', 'title' => 'Airship Armada Tee', 'price' => 32.95]],
        'pages' => [['id' => 701, 'slug' => 'start-here', 'title' => 'Start here']]
    ],
    'skipped' => [], 'errors' => []
]];
$GLOBALS['poststatus'] = [601 => 'draft', 701 => 'draft'];
$GLOBALS['postmeta'] = [601 => ['_sgap_niche' => 'abc123abc123'], 701 => ['_sgap_niche' => 'abc123abc123']];
$preview = SGAP_Niche_Shop::rollback('abc123abc123', false);
check('niche: a rollback preview lists what it would delete and keeps the run', $preview['dry_run'] === true && in_array('product:airship-armada-tee', $preview['deleted'], true) && in_array('page:start-here', $preview['deleted'], true) && isset(SGAP_Niche_Shop::runs()['abc123abc123']));
$GLOBALS['poststatus'][601] = 'publish';
$report = SGAP_Niche_Shop::rollback('abc123abc123', true);
check('niche: a published product is kept, the draft page is deleted', in_array('product:airship-armada-tee (publish)', $report['kept'], true) && in_array('page:start-here', $report['deleted'], true));
check('niche: the run is forgotten once it has been rolled back', !isset(SGAP_Niche_Shop::runs()['abc123abc123']));
unset($GLOBALS['opts'][SGAP_Niche_Shop::RUNS], $GLOBALS['poststatus'], $GLOBALS['postmeta']);

// 6. Evnerne: feltet kan nås af en MCP-klient, og planen er læsende.
// Registret kan være name-keyed (rigtigt) eller en liste af navne, hvis en tidligere test har
// fladet det ud — begge former læses, så testen ikke afhænger af rækkefølgen mellem filene.
$registered = [];
$niche_plan_entry = null;
foreach ($GLOBALS['abilities'] as $key => $value) {
    $name = is_string($key) ? $key : (string) $value;
    $registered[] = $name;
    if ($name === 'steamgears-autopilot/niche-plan' && is_array($value)) {
        $niche_plan_entry = $value;
    }
}
check('niche: the four abilities are registered', count(array_filter($registered, fn($a) => str_contains($a, 'niche-'))) === 4);
check('niche: niche-plan is read-only and needs no write access', is_array($niche_plan_entry)
    && ($niche_plan_entry['meta']['annotations']['readonly'] ?? false) === true
    && ($niche_plan_entry['input_schema']['required'] ?? []) === ['niche']);
$summary = SGAP_Abilities::niche_plan(['niche' => 'celestial zodiac', 'count' => 6]);
check('niche: niche-plan answers with a summary, not a WP_Error', !is_wp_error($summary) && $summary['counts']['products'] === 6 && $summary['archetype']['id'] === 'celestial');
