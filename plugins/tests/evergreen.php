<?php
/**
 * P1.4 evergreen checks that need the admin and ability surfaces. Required from
 * smoke.php, so $opts, $GLOBALS and check() are already in scope.
 */

ob_start();
do_action('page_sgap-evergreen');
$evhtml = ob_get_clean();
check('evergreen: the admin page renders the marked page and its budget',
    str_contains($evhtml, 'Evergreen recycling') && str_contains($evhtml, 'Brass Goggles Guide')
    && str_contains($evhtml, '2 / 2') && str_contains($evhtml, 'Stop'));

ob_start();
do_action('page_sgap-settings');
$evset = ob_get_clean();
check('evergreen: the four settings are on their own tab',
    str_contains($evset, 'name="sgap_evergreen_enabled"') && str_contains($evset, 'name="sgap_evergreen_after_days"')
    && str_contains($evset, 'name="sgap_evergreen_max_shares"') && str_contains($evset, 'name="sgap_evergreen_batch"'));

check('evergreen: the abilities are registered',
    isset($GLOBALS['abilities']['steamgears-autopilot/list-evergreen'],
        $GLOBALS['abilities']['steamgears-autopilot/mark-evergreen'],
        $GLOBALS['abilities']['steamgears-autopilot/recycle-evergreen']));
check('evergreen: the read-only list carries no writer meta',
    ($GLOBALS['abilities']['steamgears-autopilot/list-evergreen']['meta']['annotations']['readonly'] ?? false) === true);

$list = SGAP_Abilities::list_evergreen(['limit' => 10]);
check('evergreen: the list reports settings and the page state',
    ($list['settings']['marked'] ?? 0) >= 1 && count($list['items']) >= 1
    && isset($list['items'][0]['shares'], $list['items'][0]['eligible']));

$GLOBALS['opts']['sgap_evergreen_enabled'] = 'no';
$off = SGAP_Evergreen::run();
check('evergreen: the off switch stops the run before any AI call',
    $off['recycled'] === 0 && str_contains($off['errors'][0] ?? '', 'off'));
$GLOBALS['opts']['sgap_evergreen_enabled'] = 'yes';
