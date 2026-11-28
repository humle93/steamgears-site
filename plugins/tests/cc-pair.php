<?php
/**
 * P24 (7.95.0): Command Center connects to a new site with a link code (ported from p18-flere-sites).
 * Required from smoke.php, so $GLOBALS['opts'], the stubs and check() are already here.
 */

if (!function_exists('user_can')) {
    function user_can($u, $cap) { return !in_array((int) ($u->ID ?? 0), (array) ($GLOBALS['p20_non_admins'] ?? []), true); }
}
if (!function_exists('wp_is_application_passwords_available_for_user')) {
    function wp_is_application_passwords_available_for_user($u) { return empty($GLOBALS['p20_no_app_passwords']); }
}
if (!function_exists('get_userdata')) {
    function get_userdata($id) { return $GLOBALS['cirla_test_user'] ?? false; }
}
if (!class_exists('WP_Application_Passwords')) {
    class WP_Application_Passwords {
        public static function create_new_application_password($uid, $args) {
            $GLOBALS['p20_app_made'][] = [$uid, $args['name'] ?? ''];
            return ['abcd efgh ijkl mnop qrst uvwx', ['uuid' => 'x']];
        }
    }
}
function p20_b64url_decode(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }
$p20_saved = $GLOBALS['opts'];
$p20_cur = $GLOBALS['cirla_test_user'] ?? null;
$p20_user = new WP_User(7, 'henning@example.dk', 'henning');

check('7.95 pair: the module is loaded by the plugin',
    str_contains(file_get_contents(__DIR__ . '/../steamgears-autopilot/steamgears-autopilot.php'), "require_once __DIR__ . '/includes/cc-pair.php';"));
delete_option(SGAP_CC_Pair::CODE);
SGAP_CC_Pair::routes();
check('7.95 pair: the plugin registers POST /pair', in_array('/pair', $GLOBALS['routes']['sgap/v1'] ?? [], true));
$p20_made = SGAP_CC_Pair::make($p20_user, 1000);
check('7.95 pair: a code is 12 characters in three groups, from an alphabet without 0/O/1/I',
    is_array($p20_made) && preg_match('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $p20_made['code']));
$p20_link = json_decode(p20_b64url_decode(substr($p20_made['link'], strlen(SGAP_CC_Pair::PREFIX))), true);
check('7.95 pair: the link string carries the site address and the code',
    str_starts_with($p20_made['link'], 'sgap1.') && ($p20_link['u'] ?? '') === home_url('/') && ($p20_link['c'] ?? '') === $p20_made['code']);
$p20_store = get_option(SGAP_CC_Pair::CODE);
check('7.95 pair: only a hash of the code is stored', !str_contains(json_encode($p20_store), str_replace('-', '', $p20_made['code']))
    && !str_contains(json_encode($p20_store), $p20_made['code']) && strlen($p20_store['hash']) === 64);
$p20_bad = SGAP_CC_Pair::pair('AAAA-AAAA-AAAA', '', '', 1001);
check('7.95 pair: a wrong code is refused with 403 and counted', $p20_bad instanceof WP_Error && ($p20_bad->data['status'] ?? 0) === 403
    && (int) get_option(SGAP_CC_Pair::CODE)['tries'] === 1);
$GLOBALS['cirla_test_user'] = $p20_user;
$GLOBALS['p20_app_made'] = [];
$p20_ok = SGAP_CC_Pair::pair(strtolower(str_replace('-', ' ', $p20_made['code'])), 'https://cc.example.dk/', '7.73.0', 1002);
check('7.95 pair: the right code (any case, any spacing) gives an application password for the admin who made it',
    is_array($p20_ok) && $p20_ok['app_password'] !== '' && $p20_ok['user'] === 'henning' && ($GLOBALS['p20_app_made'][0] ?? null) === [7, 'Command Center']
    && $p20_ok['site'] === home_url('/'));
check('7.95 pair: the answer names the site (domain without www, name, locale, shop) and nothing secret of the site',
    ['domain', 'name', 'locale', 'woocommerce'] === array_keys($p20_ok['info'] ?? []) && $p20_ok['info']['domain'] === 'steamgears.eu');
check('7.95 pair: the Command Center address is saved', get_option('sgap_command_center_url') === 'https://cc.example.dk');
$p20_again = SGAP_CC_Pair::pair($p20_made['code'], '', '', 1003);
check('7.95 pair: a code works only once', $p20_again instanceof WP_Error && ($p20_again->data['status'] ?? 0) === 404);
$p20_made = SGAP_CC_Pair::make($p20_user, 2000);
$p20_late = SGAP_CC_Pair::pair($p20_made['code'], '', '', 2000 + SGAP_CC_Pair::TTL + 1);
check('7.95 pair: an expired code is refused with 410 and removed', $p20_late instanceof WP_Error && ($p20_late->data['status'] ?? 0) === 410 && get_option(SGAP_CC_Pair::CODE) === false);
$p20_made = SGAP_CC_Pair::make($p20_user, 3000);
for ($i = 0; $i < SGAP_CC_Pair::MAX_TRIES; $i++) {
    $p20_last = SGAP_CC_Pair::pair('BBBB-BBBB-BBBB', '', '', 3001);
}
check('7.95 pair: five wrong codes burn the code, so the right one no longer works',
    ($p20_last->data['status'] ?? 0) === 429 && SGAP_CC_Pair::pair($p20_made['code'], '', '', 3002) instanceof WP_Error);
$GLOBALS['p20_non_admins'] = [7];
check('7.95 pair: a non-administrator cannot make a code', SGAP_CC_Pair::make($p20_user, 4000) instanceof WP_Error);
$p20_made = null;
$GLOBALS['p20_non_admins'] = [];
$GLOBALS['p20_no_app_passwords'] = true;
check('7.95 pair: no code when application passwords are switched off', SGAP_CC_Pair::make($p20_user, 4000) instanceof WP_Error);
unset($GLOBALS['p20_no_app_passwords']);
$p20_src = file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/cc-pair.php');
check('7.95 pair: the code is deleted before the application password is made',
    strpos($p20_src, 'delete_option(self::CODE);' . "\n" . '        $user = get_userdata') !== false);
check('7.95 pair: the site never calls Command Center (no outgoing request in the module)',
    !preg_match('/wp_remote_|wp_safe_remote_|curl_/', $p20_src));
$p20_core = file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/core.php');
check('7.95 pair: the Command Center card shows the link-code button, also on a site never connected',
    str_contains($p20_core, "SGAP_CC_Pair::dashboard_button()") && str_contains($p20_core, "\$pair === ''") && str_contains($p20_core, 'echo $pair;'));
check('7.95 pair: the button posts to admin-post with a nonce', str_contains(SGAP_CC_Pair::dashboard_button(), 'sgap_cc_pair_make'));

$GLOBALS['cirla_test_user'] = $p20_cur;
$GLOBALS['opts'] = $p20_saved;
