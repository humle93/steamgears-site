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
function apply_filters(string $hook, $value, ...$args)
{
    // Samme kontrakt som WordPress: et filter kan slaa et enkelt indgreb fra.
    if ($hook === 'sga_payload_trim_enabled' && !empty($GLOBALS['trim_disable_block_css']) && ($args[0] ?? '') === 'block_css') {
        return false;
    }

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

// 4) Blokstile for blokke der ikke findes paa siden (trin 3, maalt 10/11 2026).
//
// Stilen fjernes kun naar blokkens klassemarkoer mangler i body. Hvert fixture er en
// hel side, saa head/body-graensen proeves som den virker i produktion.
function trim_page(string $head_extra, string $body): string
{
    return '<!DOCTYPE html><html><head>' . $head_extra . '</head><body>' . $body . '</body></html>';
}

$dead_style = '<style id="ai_summarization-inline-css" type="text/css">.ai-summarization-summary{--ai-summary-border-color:#1d2327}</style>';
$translation_style = '<style id="ai_content_translation-inline-css" type="text/css">@keyframes ai-content-translation-text-shimmer{0%{mask-position:300%}}</style>';
$table_style = '<style id="wp-block-table-inline-css" type="text/css">.wp-block-table{overflow-x:auto}</style>';

$out = sga_payload_trim_html(trim_page($dead_style . $translation_style . $table_style, '<p>Ingen blokke her</p>'));
check_trim('doed blok-CSS fjernes naar blokken ikke findes', strpos($out, 'ai_summarization-inline-css') === false && strpos($out, 'ai_content_translation-inline-css') === false, $out);
check_trim('fjernelsen rammer ikke andre blokstile', strpos($out, 'wp-block-table-inline-css') !== false, $out);
check_trim('body og afsluttende tags er intakte', strpos($out, '<p>Ingen blokke her</p></body></html>') !== false, $out);

$out = sga_payload_trim_html(trim_page($dead_style . $translation_style, '<div class="ai-summarization">Kort version</div>'));
check_trim('blokstilen bliver staaende naar blokken er paa siden', strpos($out, 'ai_summarization-inline-css') !== false, $out);
check_trim('den anden doede stil fjernes stadig', strpos($out, 'ai_content_translation-inline-css') === false, $out);

// Markoeret inde i CSS-teksten er ikke bevis paa at blokken findes i indholdet.
$out = sga_payload_trim_html(trim_page($dead_style, '<p>Tekst uden blok</p>'));
check_trim('klassenavn i CSS-teksten taeller ikke som blok paa siden', strpos($out, 'ai_summarization-inline-css') === false, $out);

// En side uden head/body-graense skal staa urørt, saa et spa-brudstykke ikke bliver klippet.
$fragment = $dead_style . '<p>Brudstykke</p>';
check_trim('side uden </head> er uaendret', sga_payload_trim_html($fragment) === $fragment, sga_payload_trim_html($fragment));

// Rollback: filteret slukker kun dette ene indgreb.
$GLOBALS['trim_disable_block_css'] = true;
$out = sga_payload_trim_html(trim_page($dead_style, '<p>Tekst</p>'));
check_trim('filteret kan slaa blok-CSS fra uden at roere de andre indgreb', strpos($out, 'ai_summarization-inline-css') !== false, $out);
$GLOBALS['trim_disable_block_css'] = false;

// Kortet er en allowlist, saa en ny blokstil ikke bliver fjernet uden en maaling.
check_trim('kun de maalte ids staar i kortet', array_keys(sga_payload_trim_dead_css_map()) === ['ai_summarization-inline-css', 'ai_content_translation-inline-css'], implode(',', array_keys(sga_payload_trim_dead_css_map())));

echo "\n" . ($fail === 0 ? "OK   {$pass} checks bestået" : "FEJL {$fail} af " . ($pass + $fail) . " checks") . "\n";
exit($fail === 0 ? 0 : 1);
