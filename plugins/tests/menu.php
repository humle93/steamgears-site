<?php
/**
 * 7.60.0: sidemenuen er ryddet (7.88.0: Printful er fjernet). Required fra smoke.php.
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

// 2. 7.88.0: Printful er ude (Gelato er printvejen): ingen nøgletest, intet kort, og de gemte felter slettes.
check('printful (7.88): the key test no longer checks Printful', !array_key_exists('printful', SGAP_KeyCheck::run()) && !method_exists('SGAP_KeyCheck', 'check_printful')
    && !in_array('printful_api_key', SGAP_KeyCheck::WATCH, true));
$GLOBALS['opts']['sgap_printful_api_key'] = 'pf-old'; $GLOBALS['opts']['sgap_printful_store_id'] = '77'; $GLOBALS['opts']['sgap_version'] = '7.87.1';
SGAP_Install::maybe_upgrade();
check('printful (7.88): the upgrade deletes the saved Printful token and store id', !isset($GLOBALS['opts']['sgap_printful_api_key']) && !isset($GLOBALS['opts']['sgap_printful_store_id'])
    && ($GLOBALS['opts']['sgap_version'] ?? '') === SGAP_VERSION);
