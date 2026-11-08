<?php
/**
 * Tidies the Steamgears side menu: one fixed order, five short groups, the rarely used pages
 * folded behind "More", and plain names that say what a page is for. Pages and capabilities
 * are untouched — this only reorders and relabels what the other classes already registered,
 * so a page that is not listed here still shows up (at the end of "More").
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Menu {
    const PARENT = 'steamgears-autopilot';

    /** group label => [slug => new label or '' to keep]. The first slug of the first group is the dashboard. */
    const GROUPS = [
        '' => ['steamgears-autopilot' => 'Overview', 'sgap-setup' => ''],
        'Create' => ['sgap-chat' => 'AI Chat', 'sgap-studio' => 'Content Studio', 'sgap-ai' => 'Drafts to approve'],
        'Publish' => ['sgap-queue' => 'Queue', 'sgap-campaigns' => 'Campaigns', 'sgap-evergreen' => 'Evergreen', 'sgap-fullauto' => 'Full Auto'],
        'Shop' => ['sgap-niche' => 'Niche shop', 'sgap-merchant' => 'Shop & feeds', 'sgap-etsy' => 'Etsy'],
        'Insight' => ['sgap-reports' => 'Reports'],
        'Configure' => ['sgap-settings' => 'Settings & keys']
    ];
    /** Rarely used: collapsed behind "More" unless one of them is the open page. */
    const MORE = ['sgap-facebook' => 'Facebook', 'sgap-log' => 'Publish log', 'sgap-tools' => 'Tools', 'sgap-diagnostics' => 'Troubleshooting'];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'tidy'], 999);
        add_action('admin_head', [__CLASS__, 'assets']);
    }

    /** The new order, as a list of submenu rows. Pure, so the tests can run it without WordPress. */
    public static function arrange(array $rows, string $current = ''): array {
        $by = [];
        foreach ($rows as $row) {
            $by[(string) $row[2]] = $row;
        }
        $out = [];
        $take = function (string $slug, string $label, string $class = '') use (&$by, &$out): void {
            if (!isset($by[$slug])) {
                return;
            }
            $row = $by[$slug];
            if ($label !== '' && !str_contains((string) $row[0], '<span')) {
                $row[0] = $label; // keep the Setup badge when the wizard is unfinished
            }
            $row[4] = trim(($row[4] ?? '') . ' ' . $class);
            $out[] = $row;
            unset($by[$slug]);
        };
        $label = function (string $slug, string $text) use (&$out): void {
            $out[] = [$text, 'manage_options', 'sgap-group-' . sanitize_title($slug), $text, 'sgap-menu-label'];
        };
        foreach (self::GROUPS as $group => $pages) {
            $present = array_filter(array_keys($pages), fn($s) => isset($by[$s]));
            if ($group !== '' && $present) {
                $label($group, $group);
            }
            foreach ($pages as $slug => $new) {
                $take($slug, $new);
            }
        }
        // Whatever is left (new pages, third-party additions) joins "More".
        $more = array_merge(array_keys(self::MORE), array_diff(array_keys($by), array_keys(self::MORE)));
        $has_more = false;
        foreach ($more as $slug) {
            $has_more = $has_more || isset($by[$slug]);
        }
        if ($has_more) {
            $out[] = ['More', 'manage_options', 'sgap-group-more', 'More', 'sgap-menu-more' . (isset(self::MORE[$current]) || ($current !== '' && !self::known($current)) ? ' is-open' : '')];
            foreach ($more as $slug) {
                $take($slug, self::MORE[$slug] ?? '', 'sgap-menu-adv');
            }
        }
        return $out;
    }

    private static function known(string $slug): bool {
        foreach (self::GROUPS as $pages) {
            if (isset($pages[$slug])) {
                return true;
            }
        }
        return false;
    }

    public static function tidy(): void {
        global $submenu;
        if (empty($submenu[self::PARENT]) || !is_array($submenu[self::PARENT])) {
            return;
        }
        $current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $submenu[self::PARENT] = self::arrange(array_values($submenu[self::PARENT]), $current);
    }

    public static function assets(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<style id="sgap-menu-css">'
            . '#adminmenu .wp-submenu li.sgap-menu-label{pointer-events:none;margin-top:6px}'
            . '#adminmenu .wp-submenu li.sgap-menu-label a{font-size:10px;letter-spacing:.14em;text-transform:uppercase;opacity:.6;padding-block:6px 2px;font-weight:600}'
            . '#adminmenu .wp-submenu li.sgap-menu-more a::after{content:"\25BE";float:right;opacity:.7}'
            . '#adminmenu .wp-submenu li.sgap-menu-more.is-open a::after{content:"\25B4"}'
            . '#adminmenu .wp-submenu.sgap-menu-js li.sgap-menu-adv{display:none}'
            . '#adminmenu .wp-submenu.sgap-menu-js.sgap-more-open li.sgap-menu-adv,#adminmenu .wp-submenu.sgap-menu-js li.sgap-menu-adv.current{display:list-item}'
            . '</style>'
            . '<script id="sgap-menu-js">document.addEventListener("DOMContentLoaded",function(){'
            . 'var m=document.querySelector("#adminmenu .wp-submenu li.sgap-menu-more");if(!m)return;var ul=m.parentNode;'
            . 'ul.classList.add("sgap-menu-js");if(m.classList.contains("is-open"))ul.classList.add("sgap-more-open");'
            . 'm.querySelector("a").addEventListener("click",function(e){e.preventDefault();m.classList.toggle("is-open",ul.classList.toggle("sgap-more-open"));});});</script>';
    }
}
