<?php
/**
 * P9 — Cirla as a module of its own: off by default, on with one setting, off again
 * without losing a draft. The eight rows in cirla-teknisk-dokumentation §9.5, plus the
 * settings that make the switch readable.
 */

// ---- off: the default, and what "off" has to mean ----
check('cirla: off by default', SGAP_Install::DEFAULTS['sgap_cirla_enabled'] === 'no' && get_option('sgap_cirla_enabled', 'no') === 'no');
check('cirla: the module file is not even loaded while it is off', !class_exists('SGAP_Cirla'));
check('cirla: no Cirla ability is registered while it is off', preg_grep('#cirla-#', array_keys($GLOBALS['abilities'] ?? [])) === []);
check('cirla: no Cirla cron is scheduled while it is off', !in_array('sgap_cirla_daily', $GLOBALS['cron'] ?? [], true));
check('cirla: it has its own settings section, switch and log level', isset(SGAP_Settings::SECTIONS['Cirla'])
    && in_array('cirla_enabled', SGAP_Settings::BOOL_KEYS, true)
    && in_array('cirla_actions', SGAP_Settings::TEXT_KEYS, true)
    && in_array('cirla_daily_calls', SGAP_Settings::INT_KEYS, true)
    && isset(SGAP_Settings::CHOICE_KEYS['cirla_log_level'])
    && isset(SGAP_Settings::HELP['cirla_enabled']));

// ---- on: next request loads the file, and only then does anything appear ----
SGAP_Settings::set('cirla_enabled', 'yes');
require_once __DIR__ . '/../steamgears-autopilot/modules/cirla/class-cirla.php';
SGAP_Cirla::init();
check('cirla: switching it on loads the module and schedules its daily job',
    class_exists('SGAP_Cirla') && in_array('sgap_cirla_daily', $GLOBALS['cron'] ?? [], true));
check('cirla: the four safe actions are allowed by default',
    SGAP_Cirla::actions() === ['status', 'draft', 'seo', 'social']);
check('cirla: the capability is its own and maps to administrators',
    SGAP_Cirla::map_cap(['do_not_allow'], 'sgap_cirla_run', 1) === ['manage_options']
    && SGAP_Cirla::map_cap(['edit_posts'], 'edit_posts', 1) === ['edit_posts']);
$GLOBALS['abilities'] = [];
do_action('wp_abilities_api_init');
$cirla_names = array_values(preg_grep('#^steamgears-autopilot/cirla-#', array_keys($GLOBALS['abilities'] ?? [])));
check('cirla: four abilities appear when it is on', count($cirla_names) === 4);
check('cirla: every ability is guarded by can_run', (function () use ($cirla_names) {
    foreach ($cirla_names as $name) {
        if (($GLOBALS['abilities'][$name]['permission_callback'] ?? null) !== ['SGAP_Cirla', 'can_run']) {
            return false;
        }
    }
    return true;
})());
$status = SGAP_Cirla::status();
check('cirla: status names the module, the cron and the daily budget',
    ($status['enabled'] ?? false) === true && ($status['cron']['hook'] ?? '') === 'sgap_cirla_daily'
    && ($status['ai_calls_today']['limit'] ?? 0) === 5 && ($status['queues_or_publishes'] ?? true) === false);

// ---- a refusal that names the missing capability ----
$GLOBALS['deny_caps'] = ['sgap_cirla_run'];
check('cirla: an action without the capability is refused', SGAP_Cirla::can_run() === false);
$refused = SGAP_Cirla::draft(['topic' => 'Brass goggles']);
check('cirla: the refusal names the capability', $refused instanceof WP_Error && $refused->get_error_code() === 'sgap_cirla_forbidden'
    && str_contains($refused->get_error_message(), 'sgap_cirla_run'));
$GLOBALS['deny_caps'] = [];

// ---- a draft: saved as pending, never queued or published ----
$GLOBALS['filters']['sgap_cirla_generate'] = fn($value, $source, $channels) => [[
    'channel' => $channels[0], 'title' => 'The brass goggles are back',
    'text' => 'The brass goggles are back in the shop.', 'hashtags' => '#steampunk', 'alt_text' => 'Brass goggles'
]];
$GLOBALS['wpdb']->log = [];
$draft = SGAP_Cirla::draft(['topic' => 'Brass goggles', 'channel' => 'facebook']);
$log = implode("\n", $GLOBALS['wpdb']->log);
check('cirla: a draft is saved and reported as not queued', is_array($draft) && ($draft['queued'] ?? true) === false
    && ($draft['published'] ?? true) === false && (int) ($draft['id'] ?? 0) === 42);
check('cirla: the draft lands in AI Drafts with the Cirla angle', str_contains($log, 'INSERT wp_sgap_ai_drafts')
    && str_contains($log, '"angle":"cirla"') && str_contains($log, '"status":"pending"'));
check('cirla: nothing was written to the queue', !str_contains($log, 'sgap_queue'));

// ---- the same task twice is one draft ----
$GLOBALS['wpdb']->next = 42;
$GLOBALS['wpdb']->row = ['id' => 42, 'title' => 'The brass goggles are back', 'channel' => 'facebook'];
$before = substr_count(implode("\n", $GLOBALS['wpdb']->log), 'INSERT wp_sgap_ai_drafts');
$again = SGAP_Cirla::draft(['topic' => 'Brass goggles', 'channel' => 'facebook']);
$after = substr_count(implode("\n", $GLOBALS['wpdb']->log), 'INSERT wp_sgap_ai_drafts');
check('cirla: the same task twice does not make a second draft', ($again['duplicate'] ?? false) === true
    && (int) ($again['id'] ?? 0) === 42 && $before === $after);
$GLOBALS['wpdb']->next = null;
$GLOBALS['wpdb']->row = null;

// ---- the quality gate stops a dead link, and says so ----
// The gate is set explicitly: the campaign-agent tests leave it off, and a gate that is
// off proves nothing about a gate that is on.
$gate_mode_before = (string) SGAP_Settings::get('quality_gate', 'block');
SGAP_Settings::set('quality_gate', 'block');
$GLOBALS['postid_for']['https://steamgears.eu/draft-page/'] = 77;
$GLOBALS['poststatus'][77] = 'draft';
$GLOBALS['filters']['sgap_cirla_generate'] = fn() => [[
    'channel' => 'facebook', 'title' => 'Read this', 'hashtags' => '', 'alt_text' => '',
    'text' => 'A page nobody can open: https://steamgears.eu/draft-page/'
]];
$blocked = SGAP_Cirla::draft(['topic' => 'A page that is still a draft']);
check('cirla: the quality gate stops a link to an unpublished page', $blocked instanceof WP_Error
    && $blocked->get_error_code() === 'sgap_cirla_gate');
check('cirla: the refusal is in the module log', str_contains(json_encode(SGAP_Cirla::entries(3)), 'quality gate'));
SGAP_Settings::set('quality_gate', $gate_mode_before);

// ---- the daily AI budget ----
$today = substr((string) current_time('mysql'), 0, 10);
$GLOBALS['opts']['sgap_cirla_spend'] = ['date' => $today, 'n' => 5];
$GLOBALS['filters']['sgap_cirla_generate'] = fn($value, $source, $channels) => [[
    'channel' => $channels[0], 'title' => 'T', 'text' => 'Text without a link.', 'hashtags' => '', 'alt_text' => ''
]];
$budget = SGAP_Cirla::draft(['topic' => 'One more']);
check('cirla: the daily budget refuses once the five calls are spent', $budget instanceof WP_Error
    && $budget->get_error_code() === 'sgap_cirla_budget' && SGAP_Cirla::calls_today() === 5);
$GLOBALS['opts']['sgap_cirla_spend'] = ['date' => $today, 'n' => 0];

// ---- the SEO check reads the page and writes nothing ----
$GLOBALS['post_by_id'][501] = new WP_Post(['ID' => 501, 'post_title' => str_repeat('A', 70), 'post_content' => 'x']);
$GLOBALS['postmeta'][501]['rank_math_description'] = 'Too short';
$seo = SGAP_Cirla::seo_check(['post_id' => 501]);
check('cirla: the SEO check reports both a long title and a short description', is_array($seo)
    && ($seo['ok'] ?? true) === false && count($seo['problems'] ?? []) === 2
    && ($seo['title_length'] ?? 0) === 70 && ($seo['written'] ?? true) === false);
$GLOBALS['post_by_id'][502] = new WP_Post(['ID' => 502, 'post_title' => 'A short title', 'post_content' => 'x']);
$GLOBALS['postmeta'][502]['rank_math_description'] = str_repeat('Good description. ', 5);
check('cirla: a page that passes comes back with no problems', ($GLOBALS['post_by_id'][502] ? (SGAP_Cirla::seo_check(['post_id' => 502])['ok'] ?? false) : false) === true);

// ---- suggestions are read-only ----
$GLOBALS['wpdb']->log = [];
$GLOBALS['filters']['sgap_cirla_generate'] = fn($value, $source, $channels) => array_map(fn($c) => [
    'channel' => $c, 'title' => 'Idea for ' . $c, 'text' => 'An idea for ' . $c . '.', 'hashtags' => '#steampunk', 'alt_text' => ''
], $channels);
$ideas = SGAP_Cirla::social_suggestions(['post_id' => 502, 'count' => 2]);
check('cirla: suggestions come back per channel and save nothing', count($ideas['suggestions'] ?? []) === 2
    && ($ideas['queued'] ?? true) === false && !str_contains(implode("\n", $GLOBALS['wpdb']->log), 'INSERT'));

// ---- off again: abilities and cron gone, the drafts kept ----
SGAP_Cirla::sync('yes', 'no');
check('cirla: switching it off clears the daily job', in_array('sgap_cirla_daily', $GLOBALS['cleared'] ?? [], true));
SGAP_Settings::set('cirla_enabled', 'no');
$GLOBALS['abilities'] = [];
SGAP_Cirla::register_abilities();
check('cirla: switching it off removes every Cirla ability', preg_grep('#cirla-#', array_keys($GLOBALS['abilities'] ?? [])) === []);
check('cirla: switching it off keeps the drafts it wrote', !str_contains(implode("\n", $GLOBALS['wpdb']->log), 'DELETE')
    && !str_contains(implode("\n", $GLOBALS['wpdb']->log), 'UPDATE wp_sgap_ai_drafts'));
$off = SGAP_Cirla::draft(['topic' => 'Anything']);
check('cirla: every action refuses while it is off, with a readable reason', $off instanceof WP_Error
    && $off->get_error_code() === 'sgap_cirla_off' && str_contains($off->get_error_message(), 'switched off'));
check('cirla: the daily job does nothing when it is called while off', (function () {
    $before = get_option('sgap_cirla_last_tick');
    SGAP_Cirla::tick();
    return get_option('sgap_cirla_last_tick') === $before;
})());

// ---- every refusal carries a status code, so a client can tell a limit from a failure ----
// Målt på et rigtigt WordPress: før 7.71.2 blev et brugt budget og en gate-blokering sendt som HTTP 500.
SGAP_Settings::set('cirla_enabled', 'yes');
$GLOBALS['deny_caps'] = ['sgap_cirla_run'];
$coded_forbidden = SGAP_Cirla::draft(['topic' => 'No capability']);
check('cirla: a missing capability answers 403', $coded_forbidden instanceof WP_Error
    && ($coded_forbidden->get_error_data()['status'] ?? 0) === 403);
$GLOBALS['deny_caps'] = [];
$today = substr((string) current_time('mysql'), 0, 10);
$GLOBALS['opts']['sgap_cirla_spend'] = ['date' => $today, 'n' => 5];
$coded_budget = SGAP_Cirla::draft(['topic' => 'Budget again']);
check('cirla: a spent budget answers 429, not a server error', $coded_budget instanceof WP_Error
    && ($coded_budget->get_error_data()['status'] ?? 0) === 429);
$GLOBALS['opts']['sgap_cirla_spend'] = ['date' => $today, 'n' => 0];
$GLOBALS['filters']['sgap_cirla_generate'] = fn() => [[
    'channel' => 'facebook', 'title' => 'Read this', 'hashtags' => '', 'alt_text' => '',
    'text' => 'A page nobody can open: https://steamgears.eu/draft-page/'
]];
SGAP_Settings::set('quality_gate', 'block');
$GLOBALS['postid_for']['https://steamgears.eu/draft-page/'] = 77;
$GLOBALS['poststatus'][77] = 'draft';
$coded_gate = SGAP_Cirla::draft(['topic' => 'A page that is still a draft']);
check('cirla: the gate block answers 422', $coded_gate instanceof WP_Error
    && $coded_gate->get_error_code() === 'sgap_cirla_gate' && ($coded_gate->get_error_data()['status'] ?? 0) === 422);
SGAP_Settings::set('quality_gate', $gate_mode_before);
SGAP_Settings::set('cirla_enabled', 'no');
// Målt på steamgears.eu 11/11: seo/tjek og social-forslag var deklareret `: array`, så en afvisning
// gav en TypeError i stedet for 403/409/429. En afvisning skal kunne returneres som WP_Error.
SGAP_Settings::set('cirla_enabled', 'yes');
$GLOBALS['deny_caps'] = ['sgap_cirla_run'];
$seo_denied = SGAP_Cirla::seo_check(['post_id' => 1]);
check('cirla: an SEO check without the capability answers 403, not a TypeError',
    $seo_denied instanceof WP_Error && ($seo_denied->get_error_data()['status'] ?? 0) === 403);
$social_denied = SGAP_Cirla::social_suggestions(['post_id' => 1]);
check('cirla: social suggestions without the capability answer 403, not a TypeError',
    $social_denied instanceof WP_Error && ($social_denied->get_error_data()['status'] ?? 0) === 403);
$GLOBALS['deny_caps'] = [];
SGAP_Settings::set('cirla_enabled', 'no');
$seo_off = SGAP_Cirla::seo_check(['post_id' => 1]);
check('cirla: an SEO check while the module is off answers 409',
    $seo_off instanceof WP_Error && ($seo_off->get_error_data()['status'] ?? 0) === 409);
$coded_off = SGAP_Cirla::draft(['topic' => 'while off']);
check('cirla: a call while the module is off answers 409', $coded_off instanceof WP_Error
    && ($coded_off->get_error_data()['status'] ?? 0) === 409);

// ---- the production case: a role that holds sgap_cirla_run itself, without manage_options ----
// Målt på steamgears.eu 11/11: en rolle med kun read + sgap_cirla_run blev afvist, fordi
// map_cap altid sendte capabilityen videre til manage_options. En direkte tildeling skal vinde.
if (!function_exists('get_userdata')) {
    function get_userdata($id) { return $GLOBALS['cirla_test_user'] ?? false; }
}
$GLOBALS['cirla_test_user'] = new class {
    public function get_role_caps(): array { return ['read' => true, 'sgap_cirla_run' => true]; }
};
check('cirla: a role that holds the capability directly keeps it',
    SGAP_Cirla::map_cap(['do_not_allow'], 'sgap_cirla_run', 5) === ['exist']);
$GLOBALS['cirla_test_user'] = new class {
    public function get_role_caps(): array { return ['read' => true]; }
};
check('cirla: a role without it still goes through administrators',
    SGAP_Cirla::map_cap(['do_not_allow'], 'sgap_cirla_run', 5) === ['manage_options']);
check('cirla: another capability is untouched by the mapping',
    SGAP_Cirla::map_cap(['edit_posts'], 'edit_posts', 5) === ['edit_posts']);
unset($GLOBALS['cirla_test_user']);
