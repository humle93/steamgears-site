<?php
/**
 * Tests the Publer and AI API keys as soon as they are saved, and shows the
 * result as an admin notice. Also available as a dashboard button and a
 * Claude/MCP ability. The checks only read; none of them costs AI tokens.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_KeyCheck {
    /** Saving any of these settings triggers a check. */
    const WATCH = ['publer_api_key', 'publer_workspace_id', 'channels', 'ai_api_key', 'ai_provider', 'ai_model', 'ai_base_url'];
    const PENDING = 'sgap_keycheck_pending';
    const RESULT = 'sgap_key_status';

    public static function init(): void {
        foreach (self::WATCH as $key) {
            add_action('update_option_sgap_' . $key, [__CLASS__, 'flag']);
            add_action('add_option_sgap_' . $key, [__CLASS__, 'flag']);
        }
        add_action('admin_notices', [__CLASS__, 'notices']);
    }

    /** A watched setting changed: check on the next admin page (after the settings redirect). */
    public static function flag(): void {
        set_transient(self::PENDING, 1, HOUR_IN_SECONDS);
    }

    public static function notices(): void {
        if (!get_transient(self::PENDING) || !current_user_can('manage_options')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || (!str_contains((string) $screen->id, 'sgap') && !str_contains((string) $screen->id, 'steamgears-autopilot'))) {
            return;
        }
        $result = self::run();
        foreach (['publer' => 'Publer', 'ai' => 'AI'] as $part => $label) {
            $check = $result[$part];
            if ($check['ok'] === null) {
                continue; // no key saved: nothing to report
            }
            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>%s key test:</strong> %s</p></div>',
                $check['ok'] ? 'success' : 'error',
                esc_html($label),
                esc_html($check['message'])
            );
        }
    }

    /** Runs both checks, stores the result for the dashboard and status tool, and returns it. */
    public static function run(): array {
        $result = [
            'publer' => self::check_publer(),
            'ai' => self::check_ai(),
            'checked_at' => current_time('mysql')
        ];
        update_option(self::RESULT, $result, false);
        // Clear last: auto-filling the workspace id above flags a new check.
        delete_transient(self::PENDING);
        return $result;
    }

    /** @return array{ok: ?bool, message: string} ok is null when no key is saved. */
    public static function check_publer(): array {
        if (SGAP_RSS::enabled()) {
            return ['ok' => null, 'message' => 'RSS mode: no Publer API key needed. Add the feeds shown on the dashboard in Publer.'];
        }
        if (SGAP_Settings::get('publer_api_key') === '') {
            return ['ok' => null, 'message' => 'No Publer API key saved.'];
        }
        $workspaces = SGAP_Publer::workspaces();
        if (is_wp_error($workspaces)) {
            $status = (int) ($workspaces->get_error_data()['status'] ?? 0);
            $why = $status === 401 ? 'the key is invalid or revoked'
                : ($status === 403 ? 'the key lacks the "workspaces" scope (needs workspaces, accounts, posts, media)'
                : $workspaces->get_error_message());
            return ['ok' => false, 'message' => 'Publer refused the key: ' . $why . '.'];
        }
        $workspaces = array_values(array_filter((array) $workspaces, fn($w) => is_array($w) && !empty($w['id'])));
        $notes = [];
        if (SGAP_Settings::get('publer_workspace_id') === '') {
            if (count($workspaces) === 1) {
                SGAP_Settings::set('publer_workspace_id', (string) $workspaces[0]['id']);
                $notes[] = 'Workspace id filled in automatically (' . ($workspaces[0]['name'] ?? $workspaces[0]['id']) . ').';
            } else {
                $list = implode(', ', array_map(fn($w) => ($w['name'] ?? '?') . ' = ' . $w['id'], $workspaces));
                return ['ok' => false, 'message' => 'Key works, but choose a workspace id in Settings: ' . ($list ?: 'no workspaces found') . '.'];
            }
        } elseif (!in_array(SGAP_Settings::get('publer_workspace_id'), array_column($workspaces, 'id'), true)) {
            return ['ok' => false, 'message' => 'Key works, but the workspace id is not one of this key\'s workspaces.'];
        }
        $accounts = SGAP_Publer::accounts(true);
        if (is_wp_error($accounts)) {
            return ['ok' => false, 'message' => 'Could not list accounts: ' . $accounts->get_error_message() . ' (the key needs the "accounts" scope).'];
        }
        $providers = array_values(array_unique(array_column($accounts, 'provider')));
        $missing = array_values(array_diff(SGAP_Publer::channels(), $providers));
        $notes[] = count($accounts) . ' connected account(s): ' . ($providers ? implode(', ', $providers) : 'none') . '.';
        if ($missing) {
            return ['ok' => false, 'message' => 'Key works. ' . implode(' ', $notes) . ' No Publer account for: ' . implode(', ', $missing) . ' – connect it in Publer or remove it from Channels.'];
        }
        if (in_array('pinterest', SGAP_Publer::channels(), true) && SGAP_Settings::get('publer_pinterest_board') === '') {
            return ['ok' => false, 'message' => 'Key works. ' . implode(' ', $notes) . ' Set the Pinterest board id to post to Pinterest.'];
        }
        return ['ok' => true, 'message' => 'Key works. ' . implode(' ', $notes)];
    }

    /** @return array{ok: ?bool, message: string} ok is null when no key is saved. */
    public static function check_ai(): array {
        $key = (string) SGAP_Settings::get('ai_api_key');
        if ($key === '') {
            return ['ok' => null, 'message' => 'No AI API key saved.'];
        }
        if (SGAP_AI::provider() === 'aimlapi') {
            return self::check_aimlapi($key);
        }
        if (SGAP_AI::provider() === 'openai_compatible') {
            return self::check_openai_compatible($key);
        }
        // The Models API validates key and model without generating (no token cost).
        $model = SGAP_AI::model();
        $response = wp_remote_get('https://api.anthropic.com/v1/models/' . rawurlencode($model), [
            'timeout' => 20,
            'headers' => ['x-api-key' => $key, 'anthropic-version' => SGAP_AI::ANTHROPIC_VERSION]
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => 'Could not reach Anthropic: ' . $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['ok' => true, 'message' => "Anthropic key works, model {$model} is available."];
        }
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'message' => 'Anthropic refused the key: it is invalid, revoked or lacks access.'];
        }
        if ($code === 404) {
            return ['ok' => false, 'message' => "Anthropic key works, but the model \"{$model}\" does not exist. Leave AI model empty for " . SGAP_AI::DEFAULT_MODEL . '.'];
        }
        return ['ok' => false, 'message' => "Anthropic answered HTTP {$code}; try again later."];
    }

    /**
     * AIMLAPI lists its models without a key, so the key is tested with a one-token
     * chat request to the chosen model (costs a fraction of a cent).
     */
    private static function check_aimlapi(string $key): array {
        $model = SGAP_AI::model();
        $response = wp_remote_post(SGAP_AI::base_url() . '/chat/completions', [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(['model' => $model, 'max_tokens' => 1, 'messages' => [['role' => 'user', 'content' => 'ping']]])
        ]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => 'Could not reach AIMLAPI: ' . $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $detail = is_array($body) ? (string) ($body['error']['message'] ?? $body['message'] ?? '') : '';
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'message' => "AIMLAPI key works, model {$model} answers."];
        }
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'message' => 'AIMLAPI refused the key' . ($detail !== '' ? ": {$detail}" : '.')];
        }
        if ($code === 402) {
            return ['ok' => false, 'message' => 'AIMLAPI key works, but the account has no credit left. Top up at aimlapi.com.'];
        }
        if ($code === 404 || ($code === 400 && stripos($detail, 'model') !== false)) {
            return ['ok' => false, 'message' => "AIMLAPI does not offer the model \"{$model}\". Leave AI model empty for " . SGAP_AI::AIMLAPI_MODEL . '.'];
        }
        return ['ok' => false, 'message' => "AIMLAPI answered HTTP {$code}" . ($detail !== '' ? ": {$detail}" : '; try again later.')];
    }

    private static function check_openai_compatible(string $key): array {
        $base = SGAP_AI::base_url();
        $model = SGAP_AI::model();
        $response = wp_remote_get($base . '/models', ['timeout' => 20, 'headers' => ['Authorization' => 'Bearer ' . $key]]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => "Could not reach {$base}: " . $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'message' => "{$base} refused the key."];
        }
        if ($code !== 200) {
            return ['ok' => false, 'message' => "{$base}/models answered HTTP {$code}."];
        }
        if ($model === '') {
            return ['ok' => false, 'message' => 'Key works, but set an AI model name in Settings.'];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $ids = array_column((array) ($body['data'] ?? []), 'id');
        if ($ids && !in_array($model, $ids, true)) {
            return ['ok' => false, 'message' => "Key works, but the model \"{$model}\" is not offered by {$base}."];
        }
        return ['ok' => true, 'message' => "Key works" . ($ids ? ", model {$model} is available." : '.')];
    }
}
