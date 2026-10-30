<?php
/**
 * Publishes the queue through the Publer API (https://publer.com/docs).
 * Runs every 15 minutes from WP-Cron: follows up on posts already handed to
 * Publer, then sends the next due posts. Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Publer {
    const API = 'https://app.publer.com/api/v1/';

    /** Queue channels this publisher can post to (Publer provider keys). */
    const CHANNELS = ['facebook', 'instagram', 'pinterest', 'linkedin', 'twitter', 'threads', 'bluesky', 'mastodon', 'telegram', 'google'];

    /** Channels whose posts need a photo. */
    const NEEDS_PHOTO = ['instagram', 'pinterest'];

    /** A job still "working" after this long is treated as failed. */
    const JOB_TIMEOUT = 2 * HOUR_IN_SECONDS;

    public static function init(): void {
        add_action(SGAP_Install::PUBLISH_CRON, [__CLASS__, 'tick']);
    }

    public static function configured(): bool {
        return SGAP_Settings::get('publer_api_key') !== '' && SGAP_Settings::get('publer_workspace_id') !== '';
    }

    /** Channels from the settings that this publisher supports. */
    public static function channels(): array {
        $wanted = array_map('trim', explode(',', strtolower((string) SGAP_Settings::get('channels', ''))));
        return array_values(array_intersect(self::CHANNELS, $wanted));
    }

    /** @return array|WP_Error Decoded JSON body. */
    public static function request(string $method, string $path, ?array $json = null, array $args = []) {
        $headers = [
            'Authorization' => 'Bearer-API ' . SGAP_Settings::get('publer_api_key'),
            'Accept' => 'application/json'
        ];
        if (SGAP_Settings::get('publer_workspace_id') !== '') {
            $headers['Publer-Workspace-Id'] = (string) SGAP_Settings::get('publer_workspace_id');
        }
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($json);
        }
        $args['headers'] = array_merge($headers, $args['headers'] ?? []);
        $response = wp_remote_request(self::API . ltrim($path, '/'), $args + ['method' => $method, 'timeout' => 30]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = is_array($body) && isset($body['errors']) ? implode('; ', array_map('strval', (array) $body['errors'])) : 'HTTP ' . $code;
            return new WP_Error('sgap_publer_http', 'Publer: ' . $message, ['status' => $code]);
        }
        return is_array($body) ? $body : [];
    }

    /** Workspaces the key can see (used to find the workspace id). @return array|WP_Error */
    public static function workspaces() {
        return self::request('GET', 'workspaces');
    }

    /** Connected social accounts, cached for an hour. @return array|WP_Error */
    public static function accounts(bool $fresh = false) {
        $cached = get_transient('sgap_publer_accounts');
        if (!$fresh && is_array($cached)) {
            return $cached;
        }
        $body = self::request('GET', 'accounts');
        if (is_wp_error($body)) {
            return $body;
        }
        $list = isset($body['accounts']) && is_array($body['accounts']) ? $body['accounts'] : $body;
        $accounts = [];
        foreach ((array) $list as $account) {
            if (is_array($account) && !empty($account['id']) && !empty($account['provider'])) {
                $accounts[] = [
                    'id' => (string) $account['id'],
                    'provider' => (string) $account['provider'],
                    'name' => (string) ($account['name'] ?? ''),
                    'type' => (string) ($account['type'] ?? '')
                ];
            }
        }
        set_transient('sgap_publer_accounts', $accounts, HOUR_IN_SECONDS);
        return $accounts;
    }

    /**
     * Publer's per-network content for one queue item.
     * Pure function: $row needs channel, title, body, tracking_url; $media_id is '' without a photo.
     * @return array|string The network payload, or an error message.
     */
    public static function network_payload(string $channel, array $row, string $media_id) {
        $text = trim((string) ($row['body'] ?? ''));
        $url = (string) $row['tracking_url'];
        $media = $media_id !== '' ? [['id' => $media_id, 'type' => 'photo']] : [];
        if (in_array($channel, self::NEEDS_PHOTO, true) && !$media) {
            return ucfirst($channel) . ' needs a photo: give the post a featured image.';
        }
        switch ($channel) {
            case 'facebook':
            case 'linkedin':
                $link = ['url' => $url, 'title' => (string) $row['title']];
                if (!empty($row['image_url'])) {
                    $link['images'] = [(string) $row['image_url']];
                    $link['default_image'] = 0;
                }
                return ['type' => 'link', 'text' => $text, 'link' => $link];
            case 'instagram':
                // Links are not clickable in Instagram captions; the bio link does that job.
                return ['type' => 'photo', 'text' => $text, 'media' => $media];
            case 'pinterest':
                return [
                    'type' => 'photo',
                    'text' => mb_substr($text, 0, 500),
                    'title' => mb_substr((string) $row['title'], 0, 100),
                    'url' => $url,
                    'media' => $media
                ];
            case 'twitter':
                // X counts any link as 23 characters.
                return ['type' => 'status', 'text' => self::shorten($text, 280 - 25) . "\n\n" . $url];
            case 'bluesky':
                return ['type' => 'status', 'text' => self::shorten($text, 300 - mb_strlen($url) - 2) . "\n\n" . $url];
            case 'mastodon':
            case 'threads':
                return ['type' => 'status', 'text' => self::shorten($text, 500 - mb_strlen($url) - 2) . "\n\n" . $url];
            case 'telegram':
            case 'google':
                return $media
                    ? ['type' => 'photo', 'text' => $text . "\n\n" . $url, 'media' => $media]
                    : ['type' => 'status', 'text' => $text . "\n\n" . $url];
        }
        return "Channel '{$channel}' is not supported by the Publer publisher.";
    }

    private static function shorten(string $text, int $max): string {
        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, max(0, $max - 1))) . '…';
    }

    /**
     * Normalises a /job_status response. Publer's docs show both {status, payload}
     * and {success, data: {status, result: {payload: {failures}}}}; accept either.
     * @return array{state: string, error: string} state is working, done or failed.
     */
    public static function parse_job(array $body): array {
        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
        $status = strtolower((string) ($data['status'] ?? ($data['result']['status'] ?? '')));
        $payload = $data['result']['payload'] ?? ($data['payload'] ?? []);
        $failures = is_array($payload) ? ($payload['failures'] ?? ($payload['errors'] ?? [])) : [];
        if ($status === 'failed') {
            return ['state' => 'failed', 'error' => 'Publer job failed: ' . wp_json_encode($failures ?: $payload)];
        }
        if (in_array($status, ['complete', 'completed'], true)) {
            return $failures
                ? ['state' => 'failed', 'error' => 'Publer rejected the post: ' . wp_json_encode($failures)]
                : ['state' => 'done', 'error' => ''];
        }
        return ['state' => 'working', 'error' => ''];
    }

    /** Uploads the image at $url to Publer and returns its media id. @return string|WP_Error */
    public static function upload_photo(string $url) {
        $image = wp_remote_get($url, ['timeout' => 30]);
        if (is_wp_error($image) || (int) wp_remote_retrieve_response_code($image) !== 200) {
            return new WP_Error('sgap_image', 'Could not download the post image: ' . $url);
        }
        $bytes = (string) wp_remote_retrieve_body($image);
        if ($bytes === '' || strlen($bytes) > 8 * MB_IN_BYTES) {
            return new WP_Error('sgap_image', 'The post image is empty or larger than 8 MB.');
        }
        $name = sanitize_file_name(basename((string) wp_parse_url($url, PHP_URL_PATH))) ?: 'image.jpg';
        $type = (string) wp_remote_retrieve_header($image, 'content-type') ?: 'application/octet-stream';
        $boundary = 'sgap' . wp_generate_password(24, false);
        $body = "--{$boundary}\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"{$name}\"\r\n"
            . "Content-Type: {$type}\r\n\r\n"
            . $bytes . "\r\n"
            . "--{$boundary}--\r\n";
        $result = self::request('POST', 'media', null, [
            'body' => $body,
            'timeout' => 60,
            'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary]
        ]);
        if (is_wp_error($result)) {
            return $result;
        }
        return empty($result['id']) ? new WP_Error('sgap_image', 'Publer returned no media id.') : (string) $result['id'];
    }

    /** Hands one queue item to Publer. @return string|WP_Error The Publer job id. */
    public static function publish(array $row) {
        $channel = (string) $row['channel'];
        if (!in_array($channel, self::CHANNELS, true)) {
            return new WP_Error('sgap_channel', "Channel '{$channel}' is not supported by the Publer publisher.");
        }
        $accounts = self::accounts();
        if (is_wp_error($accounts)) {
            return $accounts;
        }
        $targets = [];
        foreach ($accounts as $account) {
            if ($account['provider'] === $channel) {
                $target = ['id' => $account['id']];
                if ($channel === 'pinterest') {
                    $board = (string) SGAP_Settings::get('publer_pinterest_board');
                    if ($board === '') {
                        return new WP_Error('sgap_board', 'Set "Publer pinterest board" in Settings to post to Pinterest.');
                    }
                    $target['album_id'] = $board;
                }
                $targets[] = $target;
            }
        }
        if (!$targets) {
            return new WP_Error('sgap_account', "No {$channel} account is connected in Publer.");
        }

        $media_id = '';
        $wants_photo = in_array($channel, self::NEEDS_PHOTO, true) || in_array($channel, ['telegram', 'google'], true);
        if ($wants_photo && !empty($row['image_url'])) {
            $media_id = self::upload_photo((string) $row['image_url']);
            if (is_wp_error($media_id)) {
                return $media_id;
            }
        }
        $row['tracking_url'] = SGAP_Tracking::url((int) $row['id']);
        $network = self::network_payload($channel, $row, $media_id);
        if (is_string($network)) {
            return new WP_Error('sgap_payload', $network);
        }
        $result = self::request('POST', 'posts/schedule/publish', [
            'bulk' => [
                'state' => 'scheduled',
                'posts' => [['networks' => [$channel => $network], 'accounts' => $targets]]
            ]
        ]);
        if (is_wp_error($result)) {
            return $result;
        }
        return empty($result['job_id']) ? new WP_Error('sgap_publer', 'Publer returned no job id.') : (string) $result['job_id'];
    }

    /**
     * Cron: finish posts handed to Publer earlier, then send the next due posts.
     * Returns what happened, for the dashboard button and the Claude tool.
     */
    public static function tick(): array {
        $report = ['configured' => self::configured(), 'followed_up' => [], 'sent' => [], 'failed' => [], 'blocked' => ''];
        if (!$report['configured']) {
            return $report;
        }
        $report['followed_up'] = self::follow_up();
        $report['blocked'] = SGAP_Queue::blocked_reason();
        if ($report['blocked'] !== '') {
            return $report;
        }
        global $wpdb;
        foreach (SGAP_Queue::claim(SGAP_Queue::capacity()) as $row) {
            $job = self::publish($row);
            if (is_wp_error($job)) {
                SGAP_Queue::mark((int) $row['id'], 'failed', $job->get_error_message());
                $report['failed'][] = ['id' => (int) $row['id'], 'channel' => $row['channel'], 'error' => $job->get_error_message()];
                continue;
            }
            $wpdb->update($wpdb->prefix . 'sgap_queue', ['external_id' => $job, 'sent_at' => current_time('mysql')], ['id' => (int) $row['id']]);
            $report['sent'][] = ['id' => (int) $row['id'], 'channel' => $row['channel'], 'job_id' => $job];
        }
        return $report;
    }

    /** Checks the Publer jobs of posts in 'sending' and records the outcome. Returns [id => new status]. */
    public static function follow_up(): array {
        $outcomes = [];
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, external_id, sent_at FROM {$wpdb->prefix}sgap_queue WHERE status = 'sending' AND external_id <> '' ORDER BY id ASC LIMIT 50"
        );
        foreach ($rows as $row) {
            $body = self::request('GET', 'job_status/' . rawurlencode($row->external_id));
            if (is_wp_error($body)) {
                continue; // try again next tick
            }
            $job = self::parse_job($body);
            if ($job['state'] === 'done') {
                $outcomes[(int) $row->id] = SGAP_Queue::mark((int) $row->id, 'published');
            } elseif ($job['state'] === 'failed') {
                $outcomes[(int) $row->id] = SGAP_Queue::mark((int) $row->id, 'failed', $job['error']);
            } elseif ($row->sent_at && strtotime($row->sent_at) < current_time('timestamp') - self::JOB_TIMEOUT) {
                $outcomes[(int) $row->id] = SGAP_Queue::mark((int) $row->id, 'failed', 'Publer job still working after 2 hours.');
            }
        }
        return $outcomes;
    }
}
