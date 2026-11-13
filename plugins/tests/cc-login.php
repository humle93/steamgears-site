<?php
/**
 * P10: OpenRouter as the one AI provider (7.85.0 made it the only one) and the
 * one-click login to Command Center.
 * Required from smoke.php, so $GLOBALS['opts'], the stubs and check() are already here.
 */

if (!class_exists('WP_User')) {
    class WP_User { function __construct(public $ID = 1, public $user_email = '', public $user_login = '') {} }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() { return $GLOBALS['current_user_obj'] ?? new WP_User(1, 'Henning@Steamgears.eu', 'henning'); }
}
if (!function_exists('wp_redirect')) {
    function wp_redirect($u, $s = 302) { $GLOBALS['redirects'][] = $u; return true; }
}
function p10_b64url_decode(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }
function p10_opts(array $set): void { foreach ($set as $k => $v) { if ($v === null) { unset($GLOBALS['opts']['sgap_' . $k]); } else { $GLOBALS['opts']['sgap_' . $k] = $v; } } }
$p10_saved = $GLOBALS['opts'];

// ---- 7.85.0: OpenRouter is the only provider, and there is no image path ----
p10_opts(['ai_provider' => 'aimlapi', 'ai_base_url' => 'https://wrong.example/v1']);
check('p10: the provider is OpenRouter whatever an old saved value says', SGAP_AI::provider() === 'openrouter' && SGAP_AI::provider_name() === 'OpenRouter');
check('p10: a typed base url cannot redirect the key', SGAP_AI::base_url() === 'https://openrouter.ai/api/v1');
check('p10: no provider choice is registered any more',
    !isset(SGAP_Settings::CHOICE_KEYS['ai_provider']) && !in_array('ai_provider', SGAP_Settings::TEXT_KEYS, true)
    && !in_array('ai_provider', SGAP_Settings::SECTIONS['AI'], true) && !isset(SGAP_Settings::HELP['ai_provider']));
check('p10: no AI image key, image model or image settings are left',
    !in_array('image_api_key', SGAP_Settings::SECRET_KEYS, true) && !in_array('image_model', SGAP_Settings::TEXT_KEYS, true)
    && !in_array('openrouter_image_model', SGAP_Settings::TEXT_KEYS, true) && !class_exists('SGAP_Images')
    && !in_array('auto_images', SGAP_Settings::BOOL_KEYS, true));
$p10_studio = file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/studio.php');
check('p10: the generate-image ability is gone', !str_contains($p10_studio, "'generate-image'") && !str_contains($p10_studio, 'SGAP_Images'));
$p10_setup = file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/setup.php');
check('p10: the setup wizard offers OpenRouter as the one AI provider',
    str_contains($p10_setup, 'Connect OpenRouter') && !str_contains($p10_setup, 'settings[ai_provider]'));
check('p10: the old switch-to-OpenRouter module and its notice are gone',
    !class_exists('SGAP_OpenRouter_Move') && !is_file(__DIR__ . '/../steamgears-autopilot/includes/openrouter.php'));
check('p10: the setup wizard no longer offers AIMLAPI or an image model',
    !str_contains($p10_setup, 'AIMLAPI') && !str_contains($p10_setup, 'image_model') && !str_contains($p10_setup, 'image_api_key'));

// ---- one-click login to Command Center ----
delete_option(SGAP_CC::LINK);
SGAP_CC::routes();
check('p10 cc: the plugin registers POST /cc-link', in_array('/cc-link', $GLOBALS['routes']['sgap/v1'] ?? [], true));
check('p10 cc: no link yet - status says sso false and the button needs CC 7.27',
    SGAP_CC::status()['sso'] === false && SGAP_CC::login_url(wp_get_current_user()) instanceof WP_Error
    && str_contains(SGAP_CC::login_url(wp_get_current_user())->get_error_message(), '7.27'));
check('p10 cc: the address must be https (http only for localhost), and is cleaned',
    SGAP_CC::clean_url('https://CC.Steamgears.eu/app/?x=1#f') === 'https://cc.steamgears.eu/app'
    && SGAP_CC::clean_url('http://127.0.0.1:8000') === 'http://127.0.0.1:8000'
    && SGAP_CC::clean_url('http://cc.steamgears.eu') === '' && SGAP_CC::clean_url('javascript:alert(1)') === '');
$p10_secret = str_repeat('Ab3_-x', 8);
$r = SGAP_CC::rest_link(new WP_REST_Request(['url' => 'http://cc.steamgears.eu', 'secret' => $p10_secret]));
check('p10 cc: a plain http address is refused with 400', $r instanceof WP_Error && ($r->data['status'] ?? 0) === 400 && SGAP_CC::link() === null);
$r = SGAP_CC::rest_link(new WP_REST_Request(['url' => 'https://cc.steamgears.eu', 'secret' => 'kort']));
check('p10 cc: a short secret is refused', $r instanceof WP_Error && SGAP_CC::link() === null);
$r = SGAP_CC::rest_link(new WP_REST_Request(['url' => 'https://cc.steamgears.eu', 'secret' => str_repeat('a', 31) . '"<x>']));
check('p10 cc: a secret with other characters than URL-safe ones is refused', $r instanceof WP_Error);
$r = SGAP_CC::rest_link(new WP_REST_Request(['url' => 'https://cc.steamgears.eu/', 'secret' => $p10_secret, 'version' => '7.27.0']));
check('p10 cc: a valid link is stored, not autoloaded', is_array($r) && $r['linked'] === true && SGAP_CC::link()['url'] === 'https://cc.steamgears.eu'
    && ($GLOBALS['autoload'][SGAP_CC::LINK] ?? null) === false);
check('p10 cc: status and snapshot say sso true but never carry the secret',
    SGAP_CC::status()['sso'] === true && !str_contains(wp_json_encode(SGAP_CC::status()), $p10_secret)
    && !str_contains(wp_json_encode(SGAP_REST::snapshot(new WP_REST_Request(['days' => 7, 'limit' => 5]))), $p10_secret));

$p10_url = SGAP_CC::login_url(new WP_User(7, 'Henning@Steamgears.eu', 'henning'));
check('p10 cc: the button goes to {cc}/cc-sso?t=…', is_string($p10_url) && str_starts_with($p10_url, 'https://cc.steamgears.eu/cc-sso?t='));
[$p10_payload, $p10_sig] = explode('.', rawurldecode(substr($p10_url, strlen('https://cc.steamgears.eu/cc-sso?t='))));
$p10_data = json_decode(p10_b64url_decode($p10_payload), true);
check('p10 cc: the ticket is signed with HMAC-SHA256 over the payload',
    hash_equals(hash_hmac('sha256', $p10_payload, $p10_secret, true), p10_b64url_decode($p10_sig)));
check('p10 cc: the ticket names the user by lower-case e-mail and lives 60 s',
    $p10_data['email'] === 'henning@steamgears.eu' && $p10_data['login'] === 'henning' && $p10_data['v'] === 1
    && $p10_data['exp'] - $p10_data['iat'] === 60 && abs($p10_data['iat'] - time()) <= 2 && $p10_data['site'] === 'https://steamgears.eu/');
check('p10 cc: every ticket has its own 128-bit jti',
    preg_match('/^[0-9a-f]{32}$/', $p10_data['jti']) === 1
    && json_decode(p10_b64url_decode(explode('.', SGAP_CC::ticket(new WP_User(7, 'h@s.eu', 'h'), $p10_secret))[0]), true)['jti'] !== $p10_data['jti']);
check('p10 cc: a ticket signed with another secret does not verify',
    !hash_equals(hash_hmac('sha256', $p10_payload, 'another-secret-another-secret-xx', true), p10_b64url_decode($p10_sig)));
check('p10 cc: the ticket carries no secret and no password', !str_contains(p10_b64url_decode($p10_payload), $p10_secret));
check('p10 cc: a user without e-mail gets an explanation, no ticket', SGAP_CC::login_url(new WP_User(8, '', 'nomail')) instanceof WP_Error);

$p10_btn = SGAP_CC::dashboard_button();
check('p10 cc: the dashboard button posts to admin-post with a nonce and holds no ticket or secret',
    str_contains($p10_btn, 'admin-post.php') && str_contains($p10_btn, 'sgap_cc_login') && str_contains($p10_btn, 'Log ind i Command Center')
    && !str_contains($p10_btn, 'cc-sso') && !str_contains($p10_btn, $p10_secret));
$p10_target = SGAP_CC::login_target();
check('p10 cc: clicking gives a fresh ticket URL for the user who clicked',
    is_string($p10_target) && str_starts_with($p10_target, 'https://cc.steamgears.eu/cc-sso?t=') && $p10_target !== $p10_url);
$GLOBALS['deny_caps'] = ['manage_options'];
$p10_denied = false;
try { SGAP_CC::login_target(); } catch (RuntimeException $e) { $p10_denied = true; }
check('p10 cc: a non-administrator cannot get a ticket', $p10_denied);
check('p10 cc: and does not see the one-click button', !str_contains(SGAP_CC::dashboard_button(), 'sgap_cc_login'));
unset($GLOBALS['deny_caps']);

$r = SGAP_CC::rest_link(new WP_REST_Request(['secret' => '']));
p10_opts(['command_center_url' => 'https://cc.steamgears.eu']);
check('p10 cc: an empty secret removes the link, and the button falls back to the plain address',
    $r['linked'] === false && SGAP_CC::link() === null && str_contains(SGAP_CC::dashboard_button(), 'Open Command Center')
    && !str_contains(SGAP_CC::dashboard_button(), 'sgap_cc_login'));

$GLOBALS['opts'] = $p10_saved;
