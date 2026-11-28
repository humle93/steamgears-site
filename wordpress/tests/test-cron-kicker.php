<?php
/**
 * wordpress/tests/test-cron-kicker.php — prøver `sgap-cron-kicker.php` uden WordPress og uden net.
 *
 * Fundet live 28/11 2026 i steamgears.eu's fejllog: »Cron reschedule event error for hook:
 * sgap_reddit_tick, Error code: could_not_set« flere gange i timen. Årsagen var kicker'en:
 * for gentagne opgaver lagde den den næste forekomst, men fjernede aldrig den gamle, så den
 * stod stadig som forfalden og kørte igen ved næste pas og i wp-cron.php (som så fik
 * could_not_set, fordi næste forekomst allerede fandtes). Kicker'en tog heller ikke core's
 * `doing_cron`-lås, så den kunne køre samtidig med wp-cron.php.
 *
 *   php wordpress/tests/test-cron-kicker.php
 *
 * Stubbene efterligner core's cron-semantik: wp_schedule_event()/wp_unschedule_event()
 * giver WP_Error `could_not_set`, når cron-arrayet ikke ændrer sig (update_option = false).
 *
 * Prøver:
 *   1. En gentagen opgave kører én gang: den gamle forekomst er væk, den næste findes.
 *   2. Et pas mere lige efter kører den ikke igen.
 *   3. Bagefter giver core's egen genplanlægning ingen could_not_set (det, loggen viste).
 *   4. En engangsopgave fjernes og køres én gang.
 *   5. Har wp-cron.php låsen (`doing_cron` frisk), kører kicker'en intet.
 *   6. En udløbet lås overtages, og kicker'en slipper den bagefter.
 *   7. En forekomst, som en anden kørsel tog efter øjebliksbilledet, køres ikke.
 *   8. Fremmed lås slippes ikke.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
define('SGAP_KICKER_NO_EXIT', true);

$GLOBALS['ck'] = [
    'cron' => [],
    'transients' => [],
    'options' => [],
    'calls' => [],
    'on_action' => null,
];

class WP_Error {
    public $code;
    public function __construct($code = '', $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
class WP_REST_Response {
    public $data;
    public function __construct($data = null, $status = 200) { $this->data = $data; }
}

function is_wp_error($v) { return $v instanceof WP_Error; }
function add_action(...$a) { return true; }
function add_filter(...$a) { return true; }
function register_rest_route(...$a) { return true; }
function get_option($k, $d = false) { return $GLOBALS['ck']['options'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) {
    $old = $GLOBALS['ck']['options'][$k] ?? null;
    if ($old === $v) {
        return false;
    }
    $GLOBALS['ck']['options'][$k] = $v;
    return true;
}
function get_transient($k) { return $GLOBALS['ck']['transients'][$k] ?? false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['ck']['transients'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['ck']['transients'][$k]); return true; }
function wp_get_schedules() {
    return ['sgap_quarter_hour' => ['interval' => 900], 'daily' => ['interval' => 86400], 'sgap_five_minutes' => ['interval' => 300]];
}
function _get_cron_array() { return $GLOBALS['ck']['cron']; }
function _set_cron_array($crons, $wp_error = false) {
    ksort($crons);
    $changed = update_option('cron_stub', $crons);
    if ($changed) {
        $GLOBALS['ck']['cron'] = $crons;
        return true;
    }
    return $wp_error ? new WP_Error('could_not_set', 'The cron event list could not be saved.') : false;
}
function wp_schedule_event($ts, $recurrence, $hook, $args = [], $wp_error = false) {
    $s = wp_get_schedules();
    $crons = _get_cron_array();
    $crons[$ts][$hook][md5(serialize($args))] = ['schedule' => $recurrence, 'args' => $args, 'interval' => $s[$recurrence]['interval']];
    return _set_cron_array($crons, $wp_error);
}
function wp_schedule_single_event($ts, $hook, $args = [], $wp_error = false) {
    $crons = _get_cron_array();
    $crons[$ts][$hook][md5(serialize($args))] = ['schedule' => false, 'args' => $args];
    return _set_cron_array($crons, $wp_error);
}
function wp_reschedule_event($ts, $recurrence, $hook, $args = [], $wp_error = false) {
    $interval = wp_get_schedules()[$recurrence]['interval'];
    $now = time();
    $next = $ts >= $now ? $now + $interval : $now + ($interval - (($now - $ts) % $interval));
    $GLOBALS['ck']['calls'][] = ['reschedule', $hook];
    return wp_schedule_event($next, $recurrence, $hook, $args, $wp_error);
}
function wp_unschedule_event($ts, $hook, $args = [], $wp_error = false) {
    $crons = _get_cron_array();
    $key = md5(serialize($args));
    unset($crons[$ts][$hook][$key]);
    if (empty($crons[$ts][$hook])) {
        unset($crons[$ts][$hook]);
    }
    if (empty($crons[$ts])) {
        unset($crons[$ts]);
    }
    return _set_cron_array($crons, $wp_error);
}
function wp_get_ready_cron_jobs() {
    $out = [];
    foreach (_get_cron_array() as $ts => $hooks) {
        if ($ts <= time()) {
            $out[$ts] = $hooks;
        }
    }
    return $out;
}
function wp_next_scheduled($hook, $args = []) {
    foreach (_get_cron_array() as $ts => $hooks) {
        if (isset($hooks[$hook][md5(serialize($args))])) {
            return $ts;
        }
    }
    return false;
}
function do_action_ref_array($hook, $args) {
    $GLOBALS['ck']['calls'][] = ['run', $hook];
    if (is_callable($GLOBALS['ck']['on_action'])) {
        ($GLOBALS['ck']['on_action'])($hook);
    }
}

require __DIR__ . '/../mu-plugins/sgap-cron-kicker.php';

$fail = 0;
$pass = 0;
function check(string $name, bool $ok): void {
    global $fail, $pass;
    if ($ok) { $pass++; echo "  ok   $name\n"; } else { $fail++; echo "  FAIL $name\n"; }
}
function reset_state(): void {
    $GLOBALS['ck']['cron'] = [];
    $GLOBALS['ck']['options'] = [];
    $GLOBALS['ck']['transients'] = [];
    $GLOBALS['ck']['calls'] = [];
    $GLOBALS['ck']['on_action'] = null;
}
function runs(string $hook): int {
    return count(array_filter($GLOBALS['ck']['calls'], fn($c) => $c[0] === 'run' && $c[1] === $hook));
}
function run_due(): array {
    $m = new ReflectionMethod('SGAP_Cron_Kicker', 'run_due');
    $m->setAccessible(true);
    return $m->invoke(null, 12, 40);
}
/** Det wp-cron.php gør for én forekomst (core 6.1+), og om det gav could_not_set. */
function core_cron_pass(): array {
    $errors = [];
    foreach (wp_get_ready_cron_jobs() as $ts => $hooks) {
        foreach ($hooks as $hook => $items) {
            foreach ($items as $item) {
                if ($item['schedule']) {
                    $r = wp_reschedule_event($ts, $item['schedule'], $hook, $item['args'], true);
                    if (is_wp_error($r)) {
                        $errors[] = $hook . ':' . $r->get_error_code();
                    }
                }
                wp_unschedule_event($ts, $hook, $item['args'], true);
                do_action_ref_array($hook, $item['args']);
            }
        }
    }
    return $errors;
}

echo "sgap-cron-kicker\n";

// 1-3: gentagen opgave
reset_state();
$due = time() - 120;
wp_schedule_event($due, 'sgap_quarter_hour', 'sgap_reddit_tick');
run_due();
$cron = _get_cron_array();
check('1. gentagen opgave kørt én gang', runs('sgap_reddit_tick') === 1);
check('1. gammel forekomst er fjernet', !isset($cron[$due]['sgap_reddit_tick']));
check('1. næste forekomst ligger i fremtiden', (int) wp_next_scheduled('sgap_reddit_tick') > time());
run_due();
check('2. et pas mere kører den ikke igen', runs('sgap_reddit_tick') === 1);
check('3. wp-cron.php bagefter: ingen could_not_set', core_cron_pass() === []);
check('3. og heller ingen ekstra kørsel', runs('sgap_reddit_tick') === 1);

// 4: engangsopgave
reset_state();
wp_schedule_single_event(time() - 10, 'sgap_once');
run_due();
run_due();
check('4. engangsopgave kørt én gang og fjernet', runs('sgap_once') === 1 && wp_next_scheduled('sgap_once') === false);

// 5: wp-cron.php har låsen
reset_state();
wp_schedule_event(time() - 60, 'sgap_quarter_hour', 'sgap_reddit_tick');
set_transient('doing_cron', sprintf('%.22F', microtime(true)));
$r = run_due();
check('5. frisk doing_cron-lås: intet køres', runs('sgap_reddit_tick') === 0 && ($r['skipped'] ?? '') === 'wp-cron');

// 6: udløbet lås overtages og slippes
set_transient('doing_cron', sprintf('%.22F', microtime(true) - 3600));
run_due();
check('6. udløbet lås overtages: opgaven kører', runs('sgap_reddit_tick') === 1);
check('6. låsen er sluppet bagefter', get_transient('doing_cron') === false);

// 7: en anden kørsel tager forekomsten efter øjebliksbilledet
reset_state();
$due = time() - 30;
wp_schedule_event($due, 'sgap_quarter_hour', 'sgap_first');
wp_schedule_event($due, 'sgap_quarter_hour', 'sgap_second');
$GLOBALS['ck']['on_action'] = function ($hook) use ($due) {
    if ($hook === 'sgap_first') {
        // wp-cron.php i en anden proces tager sgap_second imens.
        wp_reschedule_event($due, 'sgap_quarter_hour', 'sgap_second', [], true);
        wp_unschedule_event($due, 'sgap_second', [], true);
    }
};
$r = run_due();
check('7. forekomst taget af en anden køres ikke', runs('sgap_first') === 1 && runs('sgap_second') === 0);
check('7. rapporten tæller den', ($r['taken_elsewhere'] ?? 0) === 1);

// 8: fremmed lås slippes ikke
reset_state();
$m = new ReflectionMethod('SGAP_Cron_Kicker', 'release_cron_lock');
$m->setAccessible(true);
set_transient('doing_cron', 'fremmed');
$m->invoke(null, 'vores');
check('8. fremmed lås bliver stående', get_transient('doing_cron') === 'fremmed');

echo "\n$pass ok, $fail fejl\n";
exit($fail > 0 ? 1 : 0);
