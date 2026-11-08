<?php
/**
 * Assets and UI — the stylesheet is a file now, not 40 KB of <head>.
 *
 * Before 7.65.2 every plugin page carried its whole stylesheet inline, plus menu CSS/JS on
 * every admin page and a submit script per page. Nothing was cacheable, and a CSS-only fix
 * still meant a byte-identical page payload. The rules below keep it that way: assets live
 * in assets/, they are enqueued only where they are used, and no include may echo a <style>.
 *
 * Run through the smoke test: php plugins/tests/smoke.php
 */

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $file = '') {
        return 'https://steamgears.eu/wp-content/plugins/steamgears-autopilot/' . ltrim((string) $path, '/');
    }
}
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        $GLOBALS['enqueued_styles'][$handle] = $src;
    }
}
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $footer = false) {
        $GLOBALS['enqueued_scripts'][$handle] = $src;
    }
}

$assets = SGAP_PATH . 'assets/';
$dir    = $assets;

check('assets: admin.css findes og er ikke tom', is_readable($dir . 'admin.css') && filesize($dir . 'admin.css') > 30000);
check('assets: menu.css findes', is_readable($dir . 'menu.css') && filesize($dir . 'menu.css') > 300);
check('assets: menu.js findes', is_readable($dir . 'menu.js') && filesize($dir . 'menu.js') > 200);
check('assets: admin.js findes', is_readable($dir . 'admin.js') && filesize($dir . 'admin.js') > 150);

$css = (string) file_get_contents($dir . 'admin.css');
check('assets: stylesheet() læser filen, ikke en PHP-streng', SGAP_UI::stylesheet() === $css);
check('assets: stylesheet() har ingen <style>-wrapper', !str_contains($css, '<style') && !str_contains($css, '</style>'));
check('assets: stylesheet() har ingen <script>', !str_contains($css, '<script'));
check('assets: CSS starter med .sgap', str_starts_with(ltrim($css), '.sgap'));
check('assets: kort og felter er stadig stylet', str_contains($css, '.sgap .sgap-card') && str_contains($css, '.sgap .sgap-stat'));
check('assets: synligt fokus på alle kontroller', str_contains($css, ':focus-visible'));
check('assets: smalle skærme har egne regler', str_contains($css, '@media (max-width:782px)'));
check('assets: tabeller kan scrolles på mobil', str_contains($css, 'overflow-x:auto'));
check('assets: reduceret bevægelse respekteres', str_contains($css, 'prefers-reduced-motion'));

$menu_css = (string) file_get_contents($dir . 'menu.css');
check('menu: styling rammer kun adminmenuen', str_contains($menu_css, '#adminmenu') && !str_contains($menu_css, '<style'));
check('menu: "More"-tilstanden er stylet', str_contains($menu_css, 'sgap-menu-more'));

$menu_js = (string) file_get_contents($dir . 'menu.js');
check('menu: fold-ud virker uden inline script', str_contains($menu_js, 'sgap-menu-more') && !str_contains($menu_js, '<script'));

$admin_js = (string) file_get_contents($dir . 'admin.js');
check('admin.js: ventetilstand på knapper', str_contains($admin_js, 'disabled=true') && str_contains($admin_js, 'admin-post.php'));
check('admin.js: ingen script-tags i filen', !str_contains($admin_js, '<script'));

// Ingen include-fil må sende CSS ind i <head> igen. Side-scripts med konfiguration
// (JSON pr. side) er der stadig elleve af; de har et loft, så de ikke vokser.
$inline_styles = [];
$inline_scripts = 0;
foreach (glob(SGAP_PATH . 'includes/*.php') as $file) {
    $body = (string) file_get_contents($file);
    if (str_contains($body, "echo '<style")) {
        $inline_styles[] = basename($file);
    }
    $inline_scripts += substr_count($body, "echo '<script");
}
check('assets: ingen include echoer <style>', $inline_styles === []);
check('assets: side-scripts er ikke vokset (loft 12)', $inline_scripts <= 12);
check('menu: menu-CSS er ikke inline længere', !str_contains((string) file_get_contents(SGAP_PATH . 'includes/admin-menu.php'), '<style'));

// Skærmgenkendelsen er ren og skal ikke ramme andre sider i wp-admin.
check('screen: pluginets hovedside', SGAP_Assets::is_plugin_screen('toplevel_page_steamgears-autopilot'));
check('screen: pluginets underside', SGAP_Assets::is_plugin_screen('steamgears-autopilot_page_sgap-studio'));
check('screen: andet plugin med sgap-slug', SGAP_Assets::is_plugin_screen('admin_page_sgap-chat'));
check('screen: indlæg er ikke pluginets', !SGAP_Assets::is_plugin_screen('edit.php'));
check('screen: plugins-listen er ikke pluginets', !SGAP_Assets::is_plugin_screen('plugins.php'));
check('screen: dashboardet er ikke pluginets', !SGAP_Assets::is_plugin_screen('index.php'));
check('screen: tom hook er ikke pluginets', !SGAP_Assets::is_plugin_screen(''));

// Enqueue-kontrakten: menu overalt, side-assets kun på pluginets sider.
$GLOBALS['enqueued_styles'] = [];
$GLOBALS['enqueued_scripts'] = [];
SGAP_Assets::enqueue('edit.php');
check('enqueue: menu-CSS på en fremmed side', isset($GLOBALS['enqueued_styles'][SGAP_Assets::MENU_STYLE]));
check('enqueue: menu-JS på en fremmed side', isset($GLOBALS['enqueued_scripts'][SGAP_Assets::MENU_SCRIPT]));
check('enqueue: ingen side-CSS på en fremmed side', !isset($GLOBALS['enqueued_styles'][SGAP_Assets::STYLE]));

$GLOBALS['enqueued_styles'] = [];
$GLOBALS['enqueued_scripts'] = [];
SGAP_Assets::enqueue('toplevel_page_steamgears-autopilot');
check('enqueue: side-CSS på pluginets side', isset($GLOBALS['enqueued_styles'][SGAP_Assets::STYLE]));
check('enqueue: side-JS på pluginets side', isset($GLOBALS['enqueued_scripts'][SGAP_Assets::SCRIPT]));
check('enqueue: CSS-version er filens mtime', ctype_digit(SGAP_Assets::version('admin.css')));
check('enqueue: URL peger på assets/admin.css', str_ends_with(SGAP_Assets::url('admin.css'), '/assets/admin.css'));

// UI::css() må ikke længere printe noget.
ob_start();
SGAP_UI::css();
$printed = ob_get_clean();
check('ui: css() printer intet i <head>', $printed === '');
