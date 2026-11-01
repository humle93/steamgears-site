<?php
/** Command Center link (includes/command-center.php), run with: wp eval-file plugins/tests/command-center.php */
$fail = 0;
function tc($name, $ok, $info = null) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($info !== null ? ' — ' . substr(is_string($info) ? $info : wp_json_encode($info), 0, 240) : '') . "\n"; if (!$ok) $fail = 1; }
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
delete_option(SGAP_CC::SEEN);
tc('status: never connected at first', SGAP_CC::status()['state'] === 'never');
$r = SGAP_CC::create_pairing($admin->ID);
tc('pairing code created', !is_wp_error($r) && str_starts_with($r['code'], 'SGCC1.'), is_wp_error($r) ? $r->get_error_message() : null);
$data = json_decode(base64_decode(strtr(substr($r['code'], 6), '-_', '+/')), true);
tc('code holds site URL, user and password', ($data['url'] ?? '') === home_url() && ($data['user'] ?? '') === $admin->user_login && strlen(str_replace(' ', '', $data['password'] ?? '')) === 24, array_keys((array) $data));
add_filter('application_password_is_api_request', '__return_true');
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$auth = wp_authenticate_application_password(null, $data['user'], $data['password']);
tc('the password logs in as that administrator', $auth instanceof WP_User && $auth->ID === $admin->ID, is_wp_error($auth) ? $auth->get_error_message() : null);
tc('listed as a Command Center pairing password', count(SGAP_CC::app_passwords($admin->ID)) === 1);
$req = new WP_REST_Request('POST', '/sgap/v1/heartbeat');
$req->set_header('X-Command-Center', '7.11.0');
$req->set_header('Content-Type', 'application/json');
$req->set_body(wp_json_encode(['version' => '7.11.0', 'health' => 'ok', 'open_alarms' => 2]));
$res = rest_do_request($req);
$d = $res->get_data();
tc('heartbeat answers with plugin version, queue and cron lag', $res->get_status() === 200 && $d['plugin_version'] === SGAP_VERSION && array_key_exists('cron_lag_minutes', $d) && isset($d['pending_drafts']), $d);
$st = SGAP_CC::status();
tc('status: connected, CC version and health remembered', $st['state'] === 'connected' && $st['seen']['version'] === '7.11.0' && $st['seen']['health'] === 'ok' && $st['seen']['alarms'] === 2, $st);
update_option(SGAP_CC::SEEN, ['at' => wp_date('Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS), 'version' => '7.11.0']);
tc('status: stale after 45 minutes of silence', SGAP_CC::status()['state'] === 'stale', SGAP_CC::status()['label']);
$req = new WP_REST_Request('GET', '/sgap/v1/stats');
$req->set_header('X-Command-Center', '7.11.1');
rest_do_request($req);
tc('any API call from CC counts as seen', SGAP_CC::status()['state'] === 'connected' && SGAP_CC::seen()['version'] === '7.11.1' && SGAP_CC::seen()['route'] === '/sgap/v1/stats', SGAP_CC::seen());
wp_set_current_user(0);
$res = rest_do_request(new WP_REST_Request('POST', '/sgap/v1/heartbeat'));
tc('heartbeat refuses anonymous callers', in_array($res->get_status(), [401, 403], true), $res->get_status());
wp_set_current_user($admin->ID);
// Keep one password for the HTTP check in integration.sh.
update_option('sgap_ci_cc_login', $data['user'] . ':' . str_replace(' ', '', $data['password']), false);
$r2 = SGAP_CC::create_pairing($admin->ID);
tc('a second pairing adds a second password', count(SGAP_CC::app_passwords($admin->ID)) === 2);
WP_Application_Passwords::delete_application_password($admin->ID, $r2['uuid']);
if (function_exists('wp_get_ability')) {
    $a = wp_get_ability('steamgears-autopilot/command-center-status')->execute();
    tc('command-center-status tool', is_array($a) && $a['pairing_passwords'] === 1 && $a['state'] === 'connected', $a);
}
if ($fail) { WP_CLI::error('command center: some checks failed'); }
WP_CLI::line('command center: all checks passed');
