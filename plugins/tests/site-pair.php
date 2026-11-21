<?php
/**
 * P18 (7.92.0): the plugin reads which site it runs on, pairs with Command Center by link code,
 * and lets Command Center log its users in to wp-admin.
 * Required from smoke.php, so $GLOBALS['opts'], the stubs and check() are already here.
 */

if (!function_exists('user_can')) {
    function user_can($u, $cap) { return !in_array((int) ($u->ID ?? 0), (array) ($GLOBALS['p18_non_admins'] ?? []), true); }
}
if (!function_exists('get_user_by')) {
    function get_user_by($f, $v) { return $GLOBALS['p18_users'][strtolower((string) $v)] ?? false; }
}
if (!function_exists('wp_is_application_passwords_available_for_user')) {
    function wp_is_application_passwords_available_for_user($u) { return empty($GLOBALS['p18_no_app_passwords']); }
}
if (!function_exists('is_ssl')) {
    function is_ssl() { return true; }
}
if (!class_exists('WP_Application_Passwords')) {
    class WP_Application_Passwords {
        public static function create_new_application_password($uid, $args) {
            $GLOBALS['p18_app_made'][] = [$uid, $args['name'] ?? ''];
            return ['abcd efgh ijkl mnop qrst uvwx', ['uuid' => 'x']];
        }
    }
}
function p18_b64url(string $raw): string { return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); }
function p18_b64url_decode(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }
$p18_saved = $GLOBALS['opts'];
$p18_user = new WP_User(7, 'henning@example.dk', 'henning');

// ---- SGAP_Site ----
unset($GLOBALS['opts']['sgap_site_about'], $GLOBALS['opts']['sgap_site_voice'], $GLOBALS['opts']['sgap_content_language']);
check('p18 site: the domain is read from home_url without www', SGAP_Site::domain() === 'steamgears.eu' && SGAP_Site::is_home());
check('p18 site: steamgears.eu keeps the old about and voice text as its default',
    SGAP_Site::about() === SGAP_Site::HOME_ABOUT && SGAP_Site::voice() === SGAP_Site::HOME_VOICE);
check('p18 site: the locale gives the language (da_DK = Danish, unknown = English)',
    SGAP_Site::language_of_locale('da_DK') === 'Danish' && SGAP_Site::language_of_locale('nb_NO') === 'Norwegian'
    && SGAP_Site::language_of_locale('xx') === 'English');
$GLOBALS['opts']['sgap_site_about'] = 'a Danish guide to coffee at home';
$GLOBALS['opts']['sgap_site_voice'] = 'friendly and practical';
$GLOBALS['opts']['sgap_content_language'] = 'Danish';
check('p18 site: the owner\'s texts win over the defaults',
    SGAP_Site::about() === 'a Danish guide to coffee at home' && SGAP_Site::voice() === 'friendly and practical' && SGAP_Site::language() === 'Danish');
$p18_sys = SGAP_AI::system();
check('p18 site: the social post prompt uses the site, its voice and its language',
    str_contains($p18_sys, 'a Danish guide to coffee at home') && str_contains($p18_sys, 'friendly and practical')
    && str_contains($p18_sys, 'Write in Danish') && !str_contains($p18_sys, 'Write in English') && !str_contains($p18_sys, 'Victorian'));
check('p18 site: the translator prompt names the site', str_contains(SGAP_Locales::system(), SGAP_Site::label()));
$p18_src = '';
foreach (['generator', 'editor', 'locales', 'evergreen', 'studio', 'site-seo', 'automation', 'chat', 'profiles'] as $p18_f) {
    $p18_src .= file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/' . $p18_f . '.php');
}
check('p18 site: no AI prompt has steamgears.eu or "Write in English" written in any more',
    !preg_match('/(on|for) steamgears\.eu/', $p18_src) && !str_contains($p18_src, "Write in English") && !str_contains($p18_src, 'small Danish POD shop'));
check('p18 site: the settings know site_about, site_voice and cc_login_in',
    in_array('site_about', SGAP_Settings::TEXT_KEYS, true) && in_array('site_voice', SGAP_Settings::SECTIONS['AI'], true)
    && in_array('cc_login_in', SGAP_Settings::BOOL_KEYS, true) && isset(SGAP_Settings::HELP['site_about']));
check('p18 site: info() has domain, language and shop', ['domain', 'name', 'about', 'voice', 'language', 'shop', 'home'] === array_keys(SGAP_Site::info()));

// ---- link code ----
delete_option(SGAP_CC_Pair::CODE);
SGAP_CC_Pair::routes();
check('p18 pair: the plugin registers POST /pair', in_array('/pair', $GLOBALS['routes']['sgap/v1'] ?? [], true));
$p18_made = SGAP_CC_Pair::make($p18_user, 1000);
check('p18 pair: a code is 12 characters in three groups, from an alphabet without 0/O/1/I',
    is_array($p18_made) && preg_match('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $p18_made['code']));
$p18_link = json_decode(p18_b64url_decode(substr($p18_made['link'], strlen(SGAP_CC_Pair::PREFIX))), true);
check('p18 pair: the link string carries the site address and the code',
    str_starts_with($p18_made['link'], 'sgap1.') && ($p18_link['u'] ?? '') === home_url('/') && ($p18_link['c'] ?? '') === $p18_made['code']);
$p18_store = get_option(SGAP_CC_Pair::CODE);
check('p18 pair: only a hash of the code is stored', !str_contains(json_encode($p18_store), str_replace('-', '', $p18_made['code']))
    && !str_contains(json_encode($p18_store), $p18_made['code']) && strlen($p18_store['hash']) === 64);
$p18_bad = SGAP_CC_Pair::pair('AAAA-AAAA-AAAA', '', '', 1001);
check('p18 pair: a wrong code is refused with 403 and counted', $p18_bad instanceof WP_Error && ($p18_bad->data['status'] ?? 0) === 403
    && (int) get_option(SGAP_CC_Pair::CODE)['tries'] === 1);
$GLOBALS['cirla_test_user'] = $p18_user;
$GLOBALS['p18_app_made'] = [];
$p18_ok = SGAP_CC_Pair::pair(strtolower(str_replace('-', ' ', $p18_made['code'])), 'https://cc.example.dk/', '7.61.0', 1002);
check('p18 pair: the right code (any case, any spacing) gives an application password for the admin who made it',
    is_array($p18_ok) && $p18_ok['app_password'] !== '' && $p18_ok['user'] === 'henning' && ($GLOBALS['p18_app_made'][0] ?? null) === [7, 'Command Center']
    && $p18_ok['site'] === home_url('/') && isset($p18_ok['info']['domain']));
check('p18 pair: the Command Center address is saved', get_option('sgap_command_center_url') === 'https://cc.example.dk');
$p18_again = SGAP_CC_Pair::pair($p18_made['code'], '', '', 1003);
check('p18 pair: a code works only once', $p18_again instanceof WP_Error && ($p18_again->data['status'] ?? 0) === 404);
$p18_made = SGAP_CC_Pair::make($p18_user, 2000);
$p18_late = SGAP_CC_Pair::pair($p18_made['code'], '', '', 2000 + SGAP_CC_Pair::TTL + 1);
check('p18 pair: an expired code is refused with 410 and removed', $p18_late instanceof WP_Error && ($p18_late->data['status'] ?? 0) === 410 && get_option(SGAP_CC_Pair::CODE) === false);
$p18_made = SGAP_CC_Pair::make($p18_user, 3000);
for ($i = 0; $i < SGAP_CC_Pair::MAX_TRIES; $i++) {
    $p18_last = SGAP_CC_Pair::pair('BBBB-BBBB-BBBB', '', '', 3001);
}
check('p18 pair: five wrong codes burn the code, so the right one no longer works',
    ($p18_last->data['status'] ?? 0) === 429 && SGAP_CC_Pair::pair($p18_made['code'], '', '', 3002) instanceof WP_Error);
$GLOBALS['p18_non_admins'] = [7];
check('p18 pair: a non-administrator cannot make a code', SGAP_CC_Pair::make($p18_user, 4000) instanceof WP_Error);
$GLOBALS['p18_non_admins'] = [];
$GLOBALS['p18_no_app_passwords'] = true;
check('p18 pair: no code when application passwords are switched off', SGAP_CC_Pair::make($p18_user, 4000) instanceof WP_Error);
unset($GLOBALS['p18_no_app_passwords']);
$p18_route = file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/cc-pair.php');
check('p18 pair: the code is deleted before the application password is made',
    strpos($p18_route, 'delete_option(self::CODE);' . "\n" . '        $user = get_userdata') !== false);

// ---- login from Command Center ----
$p18_secret = str_repeat('Kx9_-q', 8);
update_option(SGAP_CC::LINK, ['url' => 'https://cc.example.dk', 'secret' => $p18_secret, 'version' => '7.61.0', 'at' => 1]);
$GLOBALS['p18_users'] = ['henning@example.dk' => $p18_user];
$GLOBALS['transient_store'] = true;
$GLOBALS['transients'] = [];
$p18_ticket = function (array $over = [], ?string $secret = null) use ($p18_secret) {
    $now = time();
    $payload = p18_b64url(json_encode(array_merge(['v' => 1, 'aud' => 'wp', 'email' => 'henning@example.dk', 'site' => home_url('/'),
        'iat' => $now, 'exp' => $now + 60, 'jti' => bin2hex(random_bytes(16))], $over)));
    return $payload . '.' . p18_b64url(hash_hmac('sha256', $payload, $secret ?? $p18_secret, true));
};
$p18_t = $p18_ticket();
$p18_u = SGAP_CC_Pair::verify_in($p18_t);
check('p18 in: a signed ticket from the connected Command Center names the administrator', $p18_u instanceof WP_User && $p18_u->ID === 7);
check('p18 in: the same ticket cannot be used twice', SGAP_CC_Pair::verify_in($p18_t) instanceof WP_Error);
check('p18 in: a ticket signed with another secret is refused', SGAP_CC_Pair::verify_in($p18_ticket([], str_repeat('z', 48))) instanceof WP_Error);
check('p18 in: an expired ticket is refused', SGAP_CC_Pair::verify_in($p18_ticket(['iat' => time() - 600, 'exp' => time() - 500])) instanceof WP_Error);
check('p18 in: a ticket that lives longer than 120 s is refused', SGAP_CC_Pair::verify_in($p18_ticket(['exp' => time() + 3600])) instanceof WP_Error);
check('p18 in: a ticket for another site is refused', SGAP_CC_Pair::verify_in($p18_ticket(['site' => 'https://brygguiden.dk/'])) instanceof WP_Error);
check('p18 in: a ticket meant for Command Center itself (no aud: wp) is refused', SGAP_CC_Pair::verify_in($p18_ticket(['aud' => null])) instanceof WP_Error);
check('p18 in: an unknown e-mail gets no login', SGAP_CC_Pair::verify_in($p18_ticket(['email' => 'nobody@example.dk'])) instanceof WP_Error);
$GLOBALS['p18_non_admins'] = [7];
check('p18 in: a user who is not an administrator gets no login', SGAP_CC_Pair::verify_in($p18_ticket()) instanceof WP_Error);
$GLOBALS['p18_non_admins'] = [];
$GLOBALS['opts']['sgap_cc_login_in'] = 'no';
check('p18 in: the setting switches it off', SGAP_CC_Pair::verify_in($p18_ticket()) instanceof WP_Error);
$GLOBALS['opts']['sgap_cc_login_in'] = 'yes';
delete_option(SGAP_CC::LINK);
check('p18 in: without a Command Center link there is no login', SGAP_CC_Pair::verify_in($p18_ticket()) instanceof WP_Error);
check('p18 in: the snapshot carries the site block', str_contains(file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/core.php'), "'site' => SGAP_Site::info()"));

unset($GLOBALS['transient_store'], $GLOBALS['p18_users'], $GLOBALS['cirla_test_user']);
$GLOBALS['transients'] = [];
$GLOBALS['opts'] = $p18_saved;
