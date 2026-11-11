<?php
/**
 * P10 (7.76.0): OpenRouter as the default provider, images through OpenRouter, the
 * "Switch to OpenRouter now" button and the one-click login to Command Center.
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

// ---- OpenRouter is the default, AIMLAPI is kept but marked ----
p10_opts(['ai_provider' => null]);
check('p10: OpenRouter is the provider when none is saved', SGAP_AI::provider() === 'openrouter');
p10_opts(['ai_provider' => 'aimlapi']);
check('p10: a saved AIMLAPI choice is kept (an update never cuts off a working key)', SGAP_AI::provider() === 'aimlapi');
p10_opts(['ai_provider' => 'bogus']);
check('p10: an unknown provider falls back to OpenRouter', SGAP_AI::provider() === 'openrouter');
$p10_choices = SGAP_Settings::CHOICE_KEYS['ai_provider'];
check('p10: OpenRouter is first and recommended, AIMLAPI says it is being phased out',
    array_key_first($p10_choices) === 'openrouter' && str_contains($p10_choices['openrouter'], 'recommended')
    && str_contains($p10_choices['aimlapi'], 'phased out') && count($p10_choices) === 4);
check('p10: the OpenRouter image model is a text setting with help, in AI content',
    in_array('openrouter_image_model', SGAP_Settings::TEXT_KEYS, true) && isset(SGAP_Settings::HELP['openrouter_image_model'])
    && in_array('openrouter_image_model', SGAP_Settings::SECTIONS['AI content'], true));
$p10_setup = file_get_contents(__DIR__ . '/../steamgears-autopilot/includes/setup.php');
check('p10: the setup wizard offers OpenRouter first as Recommended',
    strpos($p10_setup, "foreach (['openrouter' => ['OpenRouter', 'Recommended.") !== false && !str_contains($p10_setup, "'AIMLAPI', 'Recommended."));

// ---- images: OpenRouter when it has a key and a model, else AIMLAPI, else nothing ----
p10_opts(['ai_provider' => 'aimlapi', 'ai_api_key' => 'aiml-key', 'openrouter_api_key' => '', 'openrouter_image_model' => '', 'image_api_key' => '']);
check('p10 images: AIMLAPI stays the path until OpenRouter is set up', SGAP_Images::backend() === 'aimlapi');
p10_opts(['openrouter_api_key' => 'sk-or-backup-1234', 'openrouter_image_model' => 'acme/image-model']);
check('p10 images: OpenRouter key (backup) + image model = OpenRouter',
    SGAP_Images::backend() === 'openrouter' && SGAP_AI::openrouter_key() === 'sk-or-backup-1234' && SGAP_Images::active_model() === 'acme/image-model');
p10_opts(['ai_provider' => 'openrouter', 'ai_api_key' => 'sk-or-main-5678']);
check('p10 images: with OpenRouter as provider the main key is used', SGAP_AI::openrouter_key() === 'sk-or-main-5678');
p10_opts(['openrouter_image_model' => '']);
check('p10 images: an OpenRouter key without an image model is not enough', SGAP_Images::backend() === '');
$p10_err = SGAP_Images::create('A brass goggle');
check('p10 images: no backend gives an error that names OpenRouter', $p10_err instanceof WP_Error && str_contains($p10_err->get_error_message(), 'OpenRouter'));
check('p10 images: aspect ratios snap to the nearest common one',
    SGAP_Images::aspect_ratio(1600, 1024) === '3:2' && SGAP_Images::aspect_ratio(1000, 1000) === '1:1'
    && SGAP_Images::aspect_ratio(1080, 1920) === '9:16' && SGAP_Images::aspect_ratio(1920, 1080) === '16:9');
$p10_body = SGAP_Images::openrouter_body('acme/image-model', 'Brass goggles', 1600, 1024);
check('p10 images: the request asks for image output with an aspect ratio',
    $p10_body['modalities'] === ['image', 'text'] && $p10_body['image_config']['aspect_ratio'] === '3:2'
    && $p10_body['messages'][0]['content'] === 'Brass goggles' && $p10_body['model'] === 'acme/image-model');
$p10_img = SGAP_Images::openrouter_image(['choices' => [['message' => ['images' => [['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,iVBORw0KGgo=']]]]]]]);
check('p10 images: a base64 data URL becomes b64', $p10_img === ['url' => '', 'b64' => 'iVBORw0KGgo=']);
check('p10 images: an https URL is kept, http and nothing are refused',
    SGAP_Images::openrouter_image(['choices' => [['message' => ['images' => [['image_url' => ['url' => 'https://cdn.example/x.png']]]]]]])['url'] === 'https://cdn.example/x.png'
    && SGAP_Images::openrouter_image(['choices' => [['message' => ['images' => [['image_url' => ['url' => 'http://cdn.example/x.png']]]]]]]) === ['url' => '', 'b64' => '']
    && SGAP_Images::openrouter_image(['choices' => [['message' => ['content' => 'no image']]]]) === ['url' => '', 'b64' => '']);
p10_opts(['openrouter_image_model' => 'acme/image-model']);
$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [sgap_http(200, ['error' => ['message' => 'Provider returned error', 'code' => 502]])];
$p10_err = SGAP_Images::create('Brass goggles', 'Goggles', 1600, 1024);
$p10_call = end($GLOBALS['http_calls']);
check('p10 images: the call goes to OpenRouter chat/completions with the OpenRouter key',
    is_array($p10_call) && $p10_call[0] === 'https://openrouter.ai/api/v1/chat/completions'
    && ($p10_call[1]['headers']['Authorization'] ?? '') === 'Bearer sk-or-main-5678'
    && (json_decode((string) $p10_call[1]['body'], true)['model'] ?? '') === 'acme/image-model');
check('p10 images: an error object in a 200 answer becomes an error with the message',
    $p10_err instanceof WP_Error && str_contains($p10_err->get_error_message(), 'Provider returned error'));
$GLOBALS['http_queue'] = [sgap_http(200, ['choices' => [['message' => ['content' => 'Here is a description instead.']]]])];
$p10_err = SGAP_Images::create('Brass goggles');
check('p10 images: a text-only answer says the model may not draw', $p10_err instanceof WP_Error && str_contains($p10_err->get_error_message(), 'image output'));

// ---- "Switch to OpenRouter now" ----
p10_opts(['ai_provider' => 'aimlapi', 'ai_api_key' => 'aiml-key', 'ai_model' => 'anthropic/claude-opus-5.5', 'openrouter_api_key' => '', 'openrouter_model' => '']);
check('p10 move: needed while AIMLAPI is the provider, not ready without a backup', SGAP_OpenRouter_Move::needed() && !SGAP_OpenRouter_Move::ready());
$p10_html = SGAP_OpenRouter_Move::notice_html();
check('p10 move: without a backup the notice explains the steps, no button', str_contains($p10_html, 'openrouter.ai/keys') && !str_contains($p10_html, 'Switch to OpenRouter now'));
check('p10 move: apply() refuses without a backup key and model', SGAP_OpenRouter_Move::apply() instanceof WP_Error && SGAP_AI::provider() === 'aimlapi');
p10_opts(['openrouter_api_key' => 'sk-or-backup-1234', 'openrouter_model' => 'acme/text-model']);
$p10_html = SGAP_OpenRouter_Move::notice_html();
check('p10 move: with a backup the notice offers the one-click switch and names the model',
    str_contains($p10_html, 'Switch to OpenRouter now') && str_contains($p10_html, 'acme/text-model') && str_contains($p10_html, 'sgap_switch_openrouter') && !str_contains($p10_html, 'sk-or-backup'));
check('p10 move: apply() makes OpenRouter the provider with the backup key and model',
    SGAP_OpenRouter_Move::apply() === true && SGAP_AI::provider() === 'openrouter'
    && SGAP_AI::api_key() === 'sk-or-backup-1234' && SGAP_AI::model() === 'acme/text-model');
check('p10 move: the AIMLAPI key and the backup are left alone, so switching back is one setting',
    SGAP_AI::fallback_key() === 'sk-or-backup-1234' && !SGAP_OpenRouter_Move::needed() && SGAP_OpenRouter_Move::apply() instanceof WP_Error);
$GLOBALS['deny_caps'] = ['manage_options'];
$p10_denied = false;
try { SGAP_OpenRouter_Move::handle(); } catch (RuntimeException $e) { $p10_denied = true; }
check('p10 move: only an administrator can click it', $p10_denied);
unset($GLOBALS['deny_caps']);

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
