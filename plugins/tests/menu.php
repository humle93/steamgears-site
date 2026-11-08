<?php
/**
 * 7.60.0: sidemenuen er ryddet og Printful-forbindelsen kan testes. Required fra smoke.php.
 */

// 1. Menuen: fast rækkefølge, grupper, "More" og intet tabt.
$rows = [];
foreach (['steamgears-autopilot' => 'Steamgears', 'sgap-settings' => 'Settings', 'sgap-queue' => 'Queue', 'sgap-ai' => 'AI Drafts', 'sgap-tools' => 'Tools',
          'sgap-log' => 'Publish log', 'sgap-setup' => 'Setup <span class="awaiting-mod">!</span>', 'sgap-chat' => 'AI Chat', 'sgap-ny' => 'Ny side'] as $slug => $title) {
    $rows[] = [$title, 'manage_options', $slug, $title];
}
$arranged = SGAP_Menu::arrange($rows, 'sgap-queue');
$slugs = array_column($arranged, 2);
check('menu: the overview stays first (WordPress needs the parent slug there)', $slugs[0] === 'steamgears-autopilot' && $arranged[0][0] === 'Overview');
check('menu: nothing is lost, only labels are added', count(array_diff(array_column($rows, 2), $slugs)) === 0);
check('menu: Setup keeps its badge', str_contains((string) $arranged[array_search('sgap-setup', $slugs, true)][0], 'awaiting-mod'));
check('menu: pages are grouped Create → Publish → Configure', array_search('sgap-chat', $slugs, true) < array_search('sgap-queue', $slugs, true) && array_search('sgap-queue', $slugs, true) < array_search('sgap-settings', $slugs, true));
$more = array_search('sgap-group-more', $slugs, true);
check('menu: rare and unknown pages sit after "More"', $more !== false && array_search('sgap-tools', $slugs, true) > $more && array_search('sgap-ny', $slugs, true) > $more && array_search('sgap-log', $slugs, true) > $more);
check('menu: the unused group labels are not invented', !in_array('sgap-group-shop', $slugs, true));
check('menu: "More" is closed while a main page is open, open on a rare one', !str_contains($arranged[$more][4], 'is-open') && str_contains(SGAP_Menu::arrange($rows, 'sgap-tools')[array_search('sgap-group-more', array_column(SGAP_Menu::arrange($rows, 'sgap-tools'), 2), true)][4], 'is-open'));
check('menu: AI Drafts is renamed to what it does', $arranged[array_search('sgap-ai', $slugs, true)][0] === 'Drafts to approve');

// 2. Printful: tokenet testes mod /stores, og butiks-id udfyldes selv når der kun er én.
$pf_keep = [$GLOBALS['opts']['sgap_printful_api_key'] ?? null, $GLOBALS['opts']['sgap_printful_store_id'] ?? null];
unset($GLOBALS['opts']['sgap_printful_api_key'], $GLOBALS['opts']['sgap_printful_store_id']);
check('printful: no token → nothing to report', SGAP_KeyCheck::check_printful()['ok'] === null);
$GLOBALS['opts']['sgap_printful_api_key'] = 'pf-key';
$answer = fn(int $code, array $body) => fn($pre, $url) => str_ends_with($url, '/stores') ? sgap_http($code, $body) : $pre;
$GLOBALS['filters']['sgap_woo_printful_response'] = $answer(401, []);
check('printful: a refused token is reported as invalid', SGAP_KeyCheck::check_printful()['ok'] === false);
$GLOBALS['filters']['sgap_woo_printful_response'] = $answer(200, ['result' => []]);
check('printful: a token that sees no store is not "connected"', SGAP_KeyCheck::check_printful()['ok'] === false);
$GLOBALS['filters']['sgap_woo_printful_response'] = $answer(200, ['result' => [['id' => 77, 'name' => 'Steamgears']]]);
$r = SGAP_KeyCheck::check_printful();
check('printful: one store → connected, and the id is saved for you', $r['ok'] === true && ($GLOBALS['opts']['sgap_printful_store_id'] ?? '') === '77' && str_contains($r['message'], 'Steamgears'));
$GLOBALS['opts']['sgap_printful_store_id'] = '999';
check('printful: a store id the token cannot see is reported', SGAP_KeyCheck::check_printful()['ok'] === false);
check('printful: the key test result carries Printful', array_key_exists('printful', SGAP_KeyCheck::run()));
check('printful: the key card has a test and the store id field', (SGAP_Keys::PROVIDERS['printful']['test'] ?? '') === 'printful' && in_array('printful_store_id', SGAP_Keys::PROVIDERS['printful']['extra'] ?? [], true));
unset($GLOBALS['filters']['sgap_woo_printful_response']);
foreach (['sgap_printful_api_key' => $pf_keep[0], 'sgap_printful_store_id' => $pf_keep[1]] as $k => $v) {
    if ($v === null) { unset($GLOBALS['opts'][$k]); } else { $GLOBALS['opts'][$k] = $v; }
}
