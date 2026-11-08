<?php
/**
 * Look of the Steamgears admin pages: one stylesheet scoped to .sgap and small
 * HTML helpers (cards, pills, stat tiles, channel badges). Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_UI {
    const ICONS = [
        'facebook' => 'facebook-alt', 'instagram' => 'instagram', 'pinterest' => 'pinterest', 'linkedin' => 'linkedin',
        'twitter' => 'twitter', 'threads' => 'format-status', 'bluesky' => 'cloud', 'mastodon' => 'admin-comments',
        'telegram' => 'email-alt', 'google' => 'google'
    ];

    private static $enqueued = false;

    /**
     * Enqueue the stylesheet instead of printing 40 KB of CSS into every page.
     *
     * Before 7.65.2 the whole stylesheet was echoed inside <head>, so it was downloaded
     * again with every admin page and could not be cached. Safe to call twice.
     */
    public static function css(): void {
        if (self::$enqueued) {
            return;
        }
        self::$enqueued = true;
        if (class_exists('SGAP_Assets')) {
            SGAP_Assets::enqueue_admin();
        }
    }

    /** The stylesheet as a string, so a test can assert on it without rendering a page. */
    public static function stylesheet(): string {
        $file = SGAP_PATH . 'assets/admin.css';
        return is_readable($file) ? (string) file_get_contents($file) : '';
    }

    public static function pill(string $text, string $kind = ''): string {
        return '<span class="sgap-pill ' . esc_attr($kind) . '">' . esc_html($text) . '</span>';
    }

    public static function stat(string $label, $value, string $link = ''): string {
        $inner = '<b>' . esc_html((string) $value) . '</b><span>' . esc_html($label) . '</span>';
        return '<div class="sgap-stat">' . ($link !== '' ? '<a href="' . esc_url($link) . '">' . $inner . '</a>' : $inner) . '</div>';
    }

    public static function card_open(string $title, string $icon = ''): void {
        echo '<div class="sgap-card"><h2>' . ($icon !== '' ? '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>' : '') . esc_html($title) . '</h2>';
    }

    public static function card_close(): void {
        echo '</div>';
    }

    public static function channel(string $channel): string {
        $icon = self::ICONS[$channel] ?? 'share';
        return '<span class="sgap-chan"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span>' . esc_html($channel) . '</span>';
    }

    /**
     * The line under a module's numbers that says what they leave out. Kept short here and
     * spelled out on the Fejlsøgning page, so the card stays readable and nothing is hidden.
     */
    public static function caveat(string $key): void {
        if (!class_exists('SGAP_Diagnostics')) {
            return;
        }
        $text = SGAP_Diagnostics::caveat($key);
        if ($text !== '') {
            echo '<p class="sgap-caveat"><strong>Caveat:</strong> ' . esc_html($text) . '</p>';
        }
    }

    public static function status_pill(string $status): string {
        $kinds = ['published' => 'ok', 'failed' => 'bad', 'sending' => 'warn', 'queued' => 'brass', 'reminded' => 'brass', 'pending' => 'brass', 'approved' => 'ok', 'rejected' => 'bad', 'used' => 'ok'];
        return self::pill($status, $kinds[$status] ?? '');
    }

    /** A dashboard group. One question per heading; the cards below it answer that question. */
    public static function section(string $title, string $hint = ''): void {
        echo '<div class="sgap-section-head"><h2>' . esc_html($title) . '</h2>'
            . ($hint !== '' ? '<p class="sgap-section-hint">' . esc_html($hint) . '</p>' : '') . '</div>';
    }

    /** One number with its label, for a row of related metrics inside a card. */
    public static function metric(string $label, $value, string $hint = ''): string {
        return '<div class="sgap-metric"><b>' . esc_html((string) $value) . '</b><span>' . esc_html($label) . '</span>'
            . ($hint !== '' ? '<em>' . esc_html($hint) . '</em>' : '') . '</div>';
    }

    public static function empty_state(string $icon, string $text): void {
        echo '<div class="sgap-card sgap-empty"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span><p>' . esc_html($text) . '</p></div>';
    }
}
