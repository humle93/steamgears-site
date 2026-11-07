<?php
/**
 * Værn: dashboard-kortet må ikke kunne sige “Up to date” i op til 6 timer efter en release.
 *
 * Kører uden WordPress (stubs nedenfor). Beviser fire ting, i den rækkefølge fejlen opstod:
 *   1. den cachede læsning (`remote_version`) kan stå fast på en gammel version — det var fælden,
 *   2. den friske læsning (`remote_version(true)`) henter den nye version fra GitHub-headeren,
 *   3. `refresh_for_admin()` — den `panel()` bruger — henter frisk OG rydder
 *      `update_plugins`, så Plugins-listen tilbyder samme version med det samme,
 *   4. er vi ajour, ryddes transienten ikke (ingen unødig WordPress-opdateringstjek).
 *
 * Usage: php plugins/tests/updater_cache.php
 */
declare(strict_types=1);

define('ABSPATH', __DIR__.'/fixtures/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('WEEK_IN_SECONDS', 604800);
define('DAY_IN_SECONDS', 86400);

$main = (string) file_get_contents(__DIR__.'/../steamgears-autopilot/steamgears-autopilot.php');
if (!preg_match('/^[\s\*#@]*Version:\s*([0-9][0-9A-Za-z.\-]*)\s*$/mi', $main, $m)) {
    fwrite(STDERR, "FAIL  kunne ikke læse Version fra hovedfilen\n");
    exit(1);
}
define('SGAP_VERSION', $m[1]);
define('SGAP_FILE', __DIR__.'/../steamgears-autopilot/steamgears-autopilot.php');

/** Én version højere, så testen ikke skal rettes ved hver release. */
function sguc_newer(string $v): string {
    return (string) preg_replace_callback('/\.(\d+)$/', static fn(array $x): string => '.'.((int) $x[1] + 1), $v);
}

$GLOBALS['site_transients'] = [];
$GLOBALS['deleted'] = [];

function plugin_basename($f) { return 'steamgears-autopilot/steamgears-autopilot.php'; }
function add_filter(...$a) {}
function add_action(...$a) {}
function get_site_transient($k) { return $GLOBALS['site_transients'][$k] ?? false; }
function set_site_transient($k, $v, $exp = 0) { $GLOBALS['site_transients'][$k] = $v; return true; }
function delete_site_transient($k) { unset($GLOBALS['site_transients'][$k]); $GLOBALS['deleted'][] = $k; return true; }
function delete_transient($k) { return true; }
function wp_remote_get($u, $a = []) { return ['response' => ['code' => 200], 'body' => $GLOBALS['remote_body'] ?? '']; }
function wp_remote_retrieve_response_code($r) { return (int) ($r['response']['code'] ?? 0); }
function wp_remote_retrieve_body($r) { return (string) ($r['body'] ?? ''); }
function is_wp_error($v) { return $v instanceof WP_Error; }
class WP_Error { public function __construct(public $code = '', public $msg = '', public $data = []) {} }
function esc_html($s) { return htmlspecialchars((string) $s); }
function esc_url($s) { return (string) $s; }

require __DIR__.'/../steamgears-autopilot/includes/updater.php';

$fail = 0;
$n = 0;
function t(string $name, bool $ok, string $detail = ''): void {
    global $fail, $n;
    $n++;
    if ($ok) {
        echo "PASS  {$name}\n";
        return;
    }
    $fail++;
    echo "FAIL  {$name}".($detail !== '' ? " — {$detail}" : '')."\n";
}

$newer = sguc_newer(SGAP_VERSION);
/** Enhver plugin-header med en Version-linje, som parse_version kan læse. */
$header = static fn(string $v): string => "<?php\n/**\n * Plugin Name: Steamgears Autopilot\n * Version: {$v}\n */\n";
$GLOBALS['remote_body'] = $header($newer);

// 1. Cachen kan stå med den gamle version — det var fælden der gjorde “Update” tom.
$GLOBALS['site_transients'][SGAP_Updater::CACHE] = SGAP_VERSION;
t('cachet læsning giver den installerede version', SGAP_Updater::remote_version() === SGAP_VERSION, (string) SGAP_Updater::remote_version());
t('cachet læsning ser derfor ingen opdatering', !SGAP_Updater::update_available());

// 2. Frisk læsning henter den nye version fra headeren.
t("frisk læsning henter {$newer}", SGAP_Updater::remote_version(true) === $newer, (string) SGAP_Updater::remote_version(true));
t('frisk læsning ser opdateringen', SGAP_Updater::update_available(true));

// 3. Panel-vejen: frisk læsning + ryd update_plugins, så Plugins-listen også tilbyder den.
$GLOBALS['site_transients'][SGAP_Updater::CACHE] = SGAP_VERSION; // fælden igen
unset($GLOBALS['site_transients'][SGAP_Updater::ADMIN_CACHE]);
$GLOBALS['site_transients']['update_plugins'] = (object) ['response' => []];
$GLOBALS['deleted'] = [];
t("refresh_for_admin henter {$newer}", SGAP_Updater::refresh_for_admin() === $newer, (string) SGAP_Updater::refresh_for_admin());
t('refresh_for_admin rydder update_plugins', in_array('update_plugins', $GLOBALS['deleted'], true), implode(',', $GLOBALS['deleted']));
t('cachen står nu på den nye version', SGAP_Updater::remote_version() === $newer, (string) SGAP_Updater::remote_version());

// 4. Er vi ajour, må transienten ikke ryddes.
$GLOBALS['remote_body'] = $header(SGAP_VERSION);
unset($GLOBALS['site_transients'][SGAP_Updater::ADMIN_CACHE]);
$GLOBALS['deleted'] = [];
t('refresh_for_admin er ajour uden at rydde', SGAP_Updater::refresh_for_admin() === SGAP_VERSION && $GLOBALS['deleted'] === [], implode(',', $GLOBALS['deleted']));

// 5. Kortet må ikke længere kalde den cachede læsning direkte.
$src = (string) file_get_contents(__DIR__.'/../steamgears-autopilot/includes/updater.php');
preg_match('/public static function panel\(\): void \{(.*?)\n    \}/s', $src, $panel);
t('panel() bruger refresh_for_admin, ikke den cachede læsning', isset($panel[1]) && str_contains($panel[1], 'refresh_for_admin') && !str_contains($panel[1], 'self::remote_version()'));

echo $fail === 0 ? "\nOK: {$n} checks\n" : "\n{$fail} af {$n} checks fejlede\n";
exit($fail === 0 ? 0 : 1);
