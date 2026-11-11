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

// ---- the log filter ----
check('cirla-admin: the log filter takes one of the four actions and nothing else',
    SGAP_Cirla_Admin::filter(['cirla_log' => 'draft']) === 'draft'
    && SGAP_Cirla_Admin::filter(['cirla_log' => 'nonsense']) === ''
    && SGAP_Cirla_Admin::filter([]) === '');
update_option('sgap_cirla_log', [
    ['at' => '2026-11-11 10:00:00', 'action' => 'draft', 'result' => 'Draft #1'],
    ['at' => '2026-11-11 10:01:00', 'action' => 'seo', 'result' => 'ok'],
    ['at' => '2026-11-11 10:02:00', 'action' => 'draft', 'result' => 'Draft #2']
]);
check('cirla-admin: the log can be read one action at a time',
    count(SGAP_Cirla::entries(20, 'draft')) === 2 && count(SGAP_Cirla::entries(20)) === 3);
$_GET = ['cirla_log' => 'draft'];
ob_start();
SGAP_Cirla_Admin::page();
$html = ob_get_clean();
check('cirla-admin: the filtered log shows only that action\'s rows',
    substr_count($html, 'Draft #1') === 1 && substr_count($html, 'Draft #2') === 1 && !str_contains($html, '>ok<'));
check('cirla-admin: the page marks the chosen filter', str_contains($html, 'aria-current="page"'));
$_GET = ['cirla_log' => 'social'];
ob_start();
SGAP_Cirla_Admin::page();
$html = ob_get_clean();
check('cirla-admin: an empty filter still shows an empty state with a way back',
    str_contains($html, 'rows in the last 20') && str_contains($html, 'Show all 3 rows'));
$_GET = [];
update_option('sgap_cirla_log', []);
ob_start();
SGAP_Cirla_Admin::page();
$html = ob_get_clean();
check('cirla-admin: an empty log keeps the original empty state', str_contains($html, 'Nothing yet.'));


// ---- log rows that point at something ----
SGAP_Cirla::log('draft', 'draft #7 for facebook: Brass', ['id' => 7]);
$newest = SGAP_Cirla::entries(1)[0] ?? [];
check('cirla-admin: the log keeps the id of the draft or page it is about', (int) ($newest['id'] ?? -1) === 7);
SGAP_Cirla::log('status', 'read the status');
$newest = SGAP_Cirla::entries(1)[0] ?? [];
check('cirla-admin: a row without a target stores 0, not a stale id', (int) ($newest['id'] ?? -1) === 0);
$GLOBALS['post_by_id'][5] = (object) ['ID' => 5, 'post_title' => 'Kobberbriller', 'post_excerpt' => ''];
$GLOBALS['wpdb']->row = ['id' => 42, 'title' => 'Brass goggles', 'channel' => 'facebook'];
$target = SGAP_Cirla_Admin::row_target(['action' => 'draft', 'id' => 42]);
check('cirla-admin: a draft row names the draft and links to it',
    $target['url'] === '/wp-admin/admin.php?page=sgap-ai#sgap-draft-42'
    && $target['label'] === 'Draft #42 · Brass goggles');
$GLOBALS['wpdb']->row = null;
$target = SGAP_Cirla_Admin::row_target(['action' => 'draft', 'id' => 42]);
check('cirla-admin: a draft that is gone gives neither a link nor a title',
    $target['url'] === '' && $target['label'] === ''
    && SGAP_Cirla_Admin::row_url(['action' => 'draft', 'id' => 42]) === '');
$target = SGAP_Cirla_Admin::row_target(['action' => 'seo', 'id' => 5]);
check('cirla-admin: an SEO row names the page and links to its editor',
    $target['url'] === '/edit/5' && $target['label'] === 'SEO check · Kobberbriller');
$target = SGAP_Cirla_Admin::row_target(['action' => 'social', 'id' => 5]);
check('cirla-admin: a suggestion row names the page too',
    $target['url'] === '/edit/5' && $target['label'] === 'Suggestions · Kobberbriller');
$target = SGAP_Cirla_Admin::row_target(['action' => 'seo', 'id' => 999]);
check('cirla-admin: a page that is gone gives neither a link nor a title',
    $target['url'] === '' && $target['label'] === '');
$target = SGAP_Cirla_Admin::row_target(['action' => 'status', 'id' => 5]);
check('cirla-admin: rows with no natural target and old rows without an id stay plain text',
    $target['url'] === '' && $target['label'] === ''
    && SGAP_Cirla_Admin::row_target(['action' => 'draft', 'result' => 'draft #42 for facebook'])['label'] === '');
// The page reads the status first, so the mock must answer every question on it.
$GLOBALS['wpdb']->next = array_fill(0, 40, 1);
$GLOBALS['wpdb']->row = ['id' => 42, 'title' => 'Brass <b>goggles</b>', 'channel' => 'facebook'];
update_option('sgap_cirla_log', [
    ['at' => '2026-11-11 12:00:00', 'action' => 'draft', 'result' => 'Kladde A', 'id' => 42],
    ['at' => '2026-11-11 12:01:00', 'action' => 'status', 'result' => 'status ok', 'id' => 0]
]);
ob_start();
SGAP_Cirla_Admin::page();
$html = ob_get_clean();
check('cirla-admin: the log shows the draft title inside its link and leaves the status row as text',
    str_contains($html, 'page=sgap-ai#sgap-draft-42') && str_contains($html, 'Draft #42 · Brass')
    && !str_contains($html, '<b>goggles') && !str_contains($html, '>status ok</a>'));
update_option('sgap_cirla_log', []);

$_POST = [];
