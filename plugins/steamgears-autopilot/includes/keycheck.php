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
    const WATCH = ['publer_api_key', 'publer_workspace_id', 'channels', 'ai_api_key', 'ai_model', 'printful_api_key', 'printful_store_id'];
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
        foreach (['publer' => 'Publer', 'ai' => 'OpenRouter', 'printful' => 'Printful'] as $part => $label) {
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
            'printful' => self::check_printful(),
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

    /**
     * Printful: GET /stores lists what the token can see. A token for one store is enough; with
     * exactly one store and no store id saved, the id is filled in, the way Publer's workspace is.
     * @return array{ok: ?bool, message: string} ok is null when no token is saved.
     */
    public static function check_printful(): array {
        $key = (string) SGAP_Settings::get('printful_api_key', '');
        if ($key === '') {
            return ['ok' => null, 'message' => 'No Printful token saved.'];
        }
        $url = SGAP_MCP_Woo::API . '/stores';
        $response = apply_filters('sgap_woo_printful_response', null, $url, $key, '');
        if ($response === null) {
            $response = wp_remote_get($url, ['timeout' => 15, 'headers' => ['Authorization' => 'Bearer ' . $key]]);
        }
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => 'Could not reach Printful: ' . $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 401) {
            return ['ok' => false, 'message' => 'Printful refused the token: it is invalid or revoked.'];
        }
        if ($code === 403) {
            return ['ok' => false, 'message' => 'Printful token lacks access. Create it with the scopes "View stores" and "View sync products".'];
        }
        if ($code === 429) {
            return ['ok' => false, 'message' => 'Printful is rate limiting; try again in a minute.'];
        }
        if ($code !== 200) {
            return ['ok' => false, 'message' => "Printful answered HTTP {$code}; try again later."];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $stores = is_array($body['result'] ?? null) ? $body['result'] : [];
        if ($stores === []) {
            return ['ok' => false, 'message' => 'The token works, but it sees no store. Create the token under the store that holds the products.'];
        }
        $saved = trim((string) SGAP_Settings::get('printful_store_id', ''));
        if ($saved === '' && count($stores) === 1) {
            $saved = (string) ($stores[0]['id'] ?? '');
            if ($saved !== '') {
                SGAP_Settings::set('printful_store_id', $saved);
            }
        }
        $names = [];
        foreach ($stores as $store) {
            $names[] = ($store['name'] ?? '?') . ' (id ' . ($store['id'] ?? '?') . ')';
            if ($saved !== '' && (string) ($store['id'] ?? '') === $saved) {
                return ['ok' => true, 'message' => 'Printful connected to store ' . ($store['name'] ?? $saved) . ' (id ' . $saved . ').'];
            }
        }
        if ($saved === '') {
            return ['ok' => true, 'message' => 'Printful token works. Pick a store id: ' . implode(', ', $names) . '.'];
        }
        return ['ok' => false, 'message' => "Printful token works, but store id {$saved} is not among: " . implode(', ', $names) . '.'];
    }

    /** @return array{ok: ?bool, message: string} ok is null when no key is saved. */
    public static function check_ai(): array {
        $key = SGAP_AI::api_key();
        if ($key === '') {
            return ['ok' => null, 'message' => 'No OpenRouter key saved.'];
        }
        return self::check_openrouter($key);
    }

    /**
     * OpenRouter answers `/models` without a key, so asking it would call every key valid. The
     * key is therefore proven with a one-token chat request to the chosen model — and when no
     * model is chosen the answer says that instead of spending a request on a guess.
     */
    private static function check_openrouter(string $key): array {
        $who = 'OpenRouter';
        // The same cleaning the real call gets: a header value with a line break or a zero-width
        // character is dropped by the transport, and the gateway then answers "no Authorization".
        $key = SGAP_AI::bearer($key);
        $model = SGAP_AI::model();
        if ($key === '') {
            return ['ok' => false, 'message' => $who . ': no key saved.'];
        }
        // Shape before request. OpenRouter keys start with sk-or-. Anything else — a key for another
        // service, a pasted curl command, a masked value — comes back as 401 "Missing Authentication header",
        // which reads as "your key was rejected" when the truth is "that is not the key".
        if (!str_starts_with($key, 'sk-or-')) {
            $odd = preg_match('/[\s$"\'`]/', $key) === 1 ? ' and it contains a space, a quote or a command character' : '';
            return ['ok' => false, 'message' => $who . ': the saved value is not an OpenRouter key. OpenRouter keys start with sk-or-v1-; this value is ' . strlen($key) . ' characters' . $odd . '. Save the key alone from openrouter.ai/keys.'];
        }
        if ($model === '') {
            return ['ok' => false, 'message' => $who . ' key saved, but pick a model under Settings → AI — OpenRouter has no default model.'];
        }
        $response = SGAP_AI::openrouter_post($key, [
            'model' => $model,
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'ping']]
        ], 30);
        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => 'Could not reach OpenRouter: ' . $response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $detail = is_array($body) ? (string) ($body['error']['message'] ?? $body['message'] ?? '') : '';
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'message' => "{$who} key works, model {$model} answers."];
        }
        if ($code === 401 || $code === 403) {
            if (stripos($detail, 'missing authentication header') !== false) {
                // The request was built with `Authorization: Bearer …`, so the header either left
                // without it or never arrived. WordPress runs every outgoing request through
                // `http_request_args`; if that filter removes the header, name it instead of
                // sending the owner back to openrouter.ai/keys for a key that is not the problem.
                $strip = SGAP_AI::stripped_by($key);
                if ($strip['stripped']) {
                    $culprit = $strip['filters'] !== [] ? implode(', ', $strip['filters']) : 'a filter on http_request_args';
                    return ['ok' => false, 'message' => $who . ' saw no Authorization header, and the request did carry one: ' . $culprit . ' removes it from every outgoing request before it leaves the site. Turn that filter (or its plugin) off and test again. The key was not the problem and was never sent.'];
                }
                return ['ok' => false, 'message' => $who . ' saw no usable Authorization header, and the saved key has the right shape (' . strlen($key) . ' characters, sk-or-). The header was built and survived this site\'s filters, so it is removed between the server and OpenRouter: ask the host whether an outgoing proxy strips Authorization, or test again from another network.'];
            }
            return ['ok' => false, 'message' => $who . ' refused the key' . ($detail !== '' ? ": {$detail}" : '.')];
        }
        if ($code === 402) {
            return ['ok' => false, 'message' => "{$who} key works, but the account has no credit left. Add credits at openrouter.ai/credits."];
        }
        if ($code === 404 || ($code === 400 && stripos($detail, 'model') !== false)) {
            return ['ok' => false, 'message' => "{$who} does not offer the model \"{$model}\". Pick one from the list under Settings → AI."];
        }
        if ($code === 429) {
            return ['ok' => false, 'message' => "{$who} key works, but it is rate limited right now (HTTP 429); try again in a minute."];
        }
        return ['ok' => false, 'message' => "{$who} answered HTTP {$code}" . ($detail !== '' ? ": {$detail}" : '; try again later.')];
    }

}
