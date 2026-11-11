<?php
/**
 * The Cirla page: one screen with the four actions, wired to the same code as the abilities.
 */

check('cirla-admin: the page is not loaded while the module is off', !class_exists('SGAP_Cirla_Admin'));
require_once __DIR__ . '/../steamgears-autopilot/modules/cirla/class-cirla-admin.php';
SGAP_Cirla_Admin::init();
check('cirla-admin: the page class loads with the module', class_exists('SGAP_Cirla_Admin'));
SGAP_Cirla_Admin::menu();
check('cirla-admin: the page is registered in the Steamgears menu', !empty($GLOBALS['actions']['page_sgap-cirla']));
check('cirla-admin: the page and the abilities offer the same four actions',
    array_keys(SGAP_Cirla_Admin::forms()) === ['status', 'draft', 'seo', 'social']);
check('cirla-admin: an unknown action is refused', SGAP_Cirla_Admin::requested(['cirla_action' => 'delete-everything']) === '');
check('cirla-admin: a known action is accepted', SGAP_Cirla_Admin::requested(['cirla_action' => 'draft']) === 'draft');
$posted = SGAP_Cirla_Admin::input('draft', [
    'cirla_topic' => '  Brass goggles <b>x</b> ',
    'cirla_channel' => 'Facebook'
]);
check('cirla-admin: the draft form sanitises the topic and the channel',
    $posted['topic'] === 'Brass goggles x' && $posted['channel'] === 'facebook');
check('cirla-admin: a count is kept between 1 and 5',
    SGAP_Cirla_Admin::input('social', ['cirla_post_id' => '12', 'cirla_count' => '99']) === ['post_id' => 12, 'count' => 5]);
$rows = SGAP_Cirla_Admin::result_rows('draft', [
    'id' => 7, 'duplicate' => false, 'channel' => 'facebook', 'title' => 'T', 'queued' => false, 'published' => false
]);
check('cirla-admin: a draft result says it was neither queued nor published',
    in_array(['Draft', '#7'], $rows, true) && in_array(['Queued', 'no'], $rows, true) && in_array(['Published', 'no'], $rows, true));
$rows = SGAP_Cirla_Admin::result_rows('status', [
    'enabled' => true, 'actions' => ['status', 'draft'], 'cron' => ['next' => '2026-11-11T18:56:55+00:00'],
    'ai_calls_today' => ['used' => 2, 'limit' => 5], 'pending_drafts' => 3, 'queues_or_publishes' => false
]);
check('cirla-admin: the status rows show the budget and the drafts',
    in_array(['AI calls today', '2 of 5'], $rows, true) && in_array(['Pending Cirla drafts', '3'], $rows, true));

// ---- the page renders, and the handler refuses what it should ----
$GLOBALS['transient_store'] = true;
$GLOBALS['transients'] = [];
ob_start();
SGAP_Cirla_Admin::page();
$html = ob_get_clean();
check('cirla-admin: the page renders one form per action', substr_count($html, 'name="cirla_action"') === 4);
check('cirla-admin: the page links to the Cirla settings', str_contains($html, '#tab-cirla'));
check('cirla-admin: the page says nothing is queued or published by it', str_contains($html, 'Queues or publishes'));
$GLOBALS['deny_caps'] = ['manage_options'];
$refused = false;
try {
    SGAP_Cirla_Admin::handle();
} catch (RuntimeException $e) {
    $refused = true;
}
$GLOBALS['deny_caps'] = [];
check('cirla-admin: the handler refuses an account without manage_options', $refused);
$GLOBALS['redirects'] = [];
$_POST = ['cirla_action' => 'status'];
SGAP_Cirla_Admin::handle();
check('cirla-admin: a posted action leaves one result and redirects back',
    isset($GLOBALS['transients'][SGAP_Cirla_Admin::FLASH . 1])
    && in_array('/wp-admin/admin.php?page=sgap-cirla', $GLOBALS['redirects'], true));
$_POST = [];
