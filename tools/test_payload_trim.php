<?php
/**
 * Offline test af wordpress/mu-plugins/sga-payload-trim.php.
 *
 * Filen kostede 7/11 2026 hele forsiden: WooCommerce var slået fra, så `is_woocommerce()`
 * ikke fandtes, og det ukontrollerede kald i `wp_enqueue_scripts` gav HTTP 500 på /, /shop/
 * og /?nocache=1. Testen kører enqueue-callbacks i tre tilstande og kræver at fraværet af
 * Woo er en no-op i stedet for en fatal:
 *
 *     1. uden WooCommerce          → intet kastes, ingen Woo-handles røres
 *     2. med Woo, tekstside        → de seks Woo-handles fjernes
 *     3. med Woo, butiksside       → de seks handles bliver stående
 *
 * Kør: `./.tools/php tools/test_payload_trim.php`.
 */

$actions = [];
$dequeued = [];

function add_action(string $hook, callable $cb, int $prio = 10, int $args = 1): void
{
    global $actions;
    $actions[$hook][] = $cb;
}
function add_filter(string $hook, callable $cb, int $prio = 10, int $args = 1): void
{
    global $actions;
    $actions[$hook][] = $cb;
}
function remove_action(string $hook, string $cb, int $prio = 10): void
{
}
function remove_filter(string $hook, string $cb, int $prio = 10): void
{
}
function apply_filters(string $hook, $value)
{
    return $value;
}
function wp_dequeue_script(string $handle): void
{
    global $dequeued;
    $dequeued[] = $handle;
}

// mu-pluginnet afbryder uden ABSPATH, ligesom i WordPress.
define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/../wordpress/mu-plugins/sga-payload-trim.php';

const WOO_HANDLES = ['wc-jquery-blockui', 'wc-add-to-cart', 'wc-js-cookie', 'woocommerce', 'sourcebuster-js', 'wc-order-attribution'];

function run_enqueues(): array
{
    global $actions, $dequeued;
    $dequeued = [];
    foreach ($actions['wp_enqueue_scripts'] ?? [] as $cb) {
        $cb();
    }

    return $dequeued;
}

function woo_handles_touched(array $dequeued): array
{
    return array_values(array_intersect($dequeued, WOO_HANDLES));
}

/** Kører mu-pluginnet i en frisk PHP-proces, så Woo-funktionerne kan sige noget andet. */
function run_with_woo(bool $isShop): array
{
    $plugin = var_export(__DIR__ . '/../wordpress/mu-plugins/sga-payload-trim.php', true);
    $stub = <<<PHP
<?php
define('ABSPATH', __DIR__ . '/');
\$GLOBALS['trim_dequeued'] = [];
\$GLOBALS['trim_actions'] = [];
function add_action(\$h, \$c, \$p = 10, \$a = 1) { \$GLOBALS['trim_actions'][\$h][] = \$c; }
function add_filter(\$h, \$c, \$p = 10, \$a = 1) { \$GLOBALS['trim_actions'][\$h][] = \$c; }
function remove_action(\$h, \$c, \$p = 10) {}
function remove_filter(\$h, \$c, \$p = 10) {}
function apply_filters(\$h, \$v) { return \$v; }
function wp_dequeue_script(\$h) { \$GLOBALS['trim_dequeued'][] = \$h; }
function is_woocommerce() { return %s; }
function is_cart() { return false; }
function is_checkout() { return false; }
function is_account_page() { return false; }
require $plugin;
foreach (\$GLOBALS['trim_actions']['wp_enqueue_scripts'] ?? [] as \$cb) { \$cb(); }
echo json_encode(\$GLOBALS['trim_dequeued']);
PHP;
    $code = sprintf($stub, $isShop ? 'true' : 'false');
    $tmp = tempnam(sys_get_temp_dir(), 'trim') . '.php';
    file_put_contents($tmp, $code);
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1');
    @unlink($tmp);

    return (array) json_decode((string) $out, true);
}

$pass = 0;
$fail = 0;
function check_trim(string $name, bool $ok, string $info = ''): void
{
    global $pass, $fail;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : ' — ' . $info) . "\n";
    $ok ? $pass++ : $fail++;
}

// 1) WooCommerce er slået fra: funktionerne findes ikke. Det må ikke kaste.
$threw = '';
$touched = [];
try {
    $touched = woo_handles_touched(run_enqueues());
} catch (Throwable $e) {
    $threw = get_class($e) . ': ' . $e->getMessage();
}
check_trim('uden WooCommerce kastes ingen fejl', $threw === '', $threw);
check_trim('uden WooCommerce røres ingen Woo-handles', $touched === [], implode(',', $touched));
check_trim('TikTok-trimmen kører stadig uden Woo', in_array('tt4b_ajax_script', run_enqueues(), true), 'tt4b_ajax_script blev ikke fjernet');

// 2) WooCommerce er indlæst, og siden er en almindelig tekstside.
$touched = woo_handles_touched(run_with_woo(false));
check_trim('på en tekstside fjernes alle seks Woo-handles', count($touched) === 6, implode(',', $touched));

// 3) Butiksside: scriptene skal blive stående.
$shop = woo_handles_touched(run_with_woo(true));
check_trim('på en butiksside bliver Woo-handles stående', $shop === [], implode(',', $shop));

echo "\n" . ($fail === 0 ? "OK   {$pass} checks bestået" : "FEJL {$fail} af " . ($pass + $fail) . " checks") . "\n";
exit($fail === 0 ? 0 : 1);
