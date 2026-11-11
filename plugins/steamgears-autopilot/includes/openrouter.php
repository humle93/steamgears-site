<?php
/**
 * P10 (7.72.0): moving the site from AIMLAPI to OpenRouter.
 *
 * AIMLAPI is being phased out, but a saved AIMLAPI choice keeps working: an update must never cut off
 * the key the site is writing with. Instead every plugin screen shows a notice while AIMLAPI is the
 * provider, and — when the OpenRouter backup already has a key and a model — one button that makes
 * OpenRouter the provider with exactly that key and model. Nothing here calls out; the key test runs
 * on the next admin page like after any other key change (SGAP_KeyCheck watches ai_provider).
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_OpenRouter_Move {
    const ACTION = 'sgap_switch_openrouter';
    /** The result of the last click, shown once on the next plugin screen. */
    const RESULT = 'sgap_openrouter_move_result';

    public static function init(): void {
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle']);
    }

    /** True while the site still writes through AIMLAPI. */
    public static function needed(): bool {
        return SGAP_AI::provider() === 'aimlapi';
    }

    /** The backup holds what a switch needs: a key and a model OpenRouter answered with. */
    public static function ready(): bool {
        return SGAP_AI::fallback_key() !== '' && SGAP_AI::fallback_model() !== '';
    }

    /** Only on the plugin's own screens, only for admins, only while AIMLAPI is chosen. */
    public static function notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && !str_contains((string) $screen->id, 'sgap') && !str_contains((string) $screen->id, 'steamgears-autopilot')) {
            return;
        }
        $result = get_transient(self::RESULT);
        if (is_array($result)) {
            delete_transient(self::RESULT);
            printf('<div class="notice notice-%s is-dismissible"><p><strong>OpenRouter:</strong> %s</p></div>',
                !empty($result['ok']) ? 'success' : 'error', esc_html((string) ($result['message'] ?? '')));
        }
        if (self::needed()) {
            echo self::notice_html();
        }
    }

    public static function notice_html(): string {
        $text = '<strong>Steamgears Autopilot:</strong> AIMLAPI is being phased out. ';
        if (self::ready()) {
            $form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">'
                . '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">'
                . wp_nonce_field(self::ACTION, '_wpnonce', true, false)
                . '<button class="button button-primary">Switch to OpenRouter now</button></form>';
            return '<div class="notice notice-warning"><p>' . $text . 'Your OpenRouter backup already has a key and the model <code>'
                . esc_html(SGAP_AI::fallback_model()) . '</code> — one click makes it the provider. ' . $form . '</p></div>';
        }
        return '<div class="notice notice-warning"><p>' . $text . 'Create a key at openrouter.ai/keys, choose <em>OpenRouter</em> under <a href="'
            . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings → AI content</a> and pick a model from the list. The AIMLAPI key keeps working until then.</p></div>';
    }

    /**
     * Makes OpenRouter the provider with the backup's key and model. The backup key stays where it is
     * (it is also the image key), and the AIMLAPI key is not touched — switching back is one setting.
     * @return true|WP_Error
     */
    public static function apply(): bool|WP_Error {
        if (!self::needed()) {
            return new WP_Error('sgap_openrouter_move', 'OpenRouter is not needed: the provider is ' . SGAP_AI::provider() . '.');
        }
        if (!self::ready()) {
            return new WP_Error('sgap_openrouter_move', 'The OpenRouter backup needs a key and a model first (Settings → Keys).');
        }
        $key = SGAP_AI::fallback_key();
        $model = SGAP_AI::fallback_model();
        // Order matters. keep_provider_model() runs when ai_provider changes and asks the catalogue
        // whether the stored model is offered — with an AIMLAPI model and the old key that is a wasted
        // call. An empty model makes it return at once; the concrete model is written last.
        SGAP_Settings::set('ai_model', '');
        SGAP_Settings::set('ai_api_key', $key);
        SGAP_Settings::set('ai_provider', 'openrouter');
        SGAP_Settings::set('ai_model', $model);
        return true;
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to change the AI provider.', 403);
        }
        check_admin_referer(self::ACTION);
        $result = self::apply();
        set_transient(self::RESULT, is_wp_error($result)
            ? ['ok' => false, 'message' => $result->get_error_message()]
            : ['ok' => true, 'message' => 'OpenRouter is now the provider, with the model ' . SGAP_AI::model() . '. The key test runs on this page.'],
            10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=sgap-settings'));
        exit;
    }
}

SGAP_OpenRouter_Move::init();
