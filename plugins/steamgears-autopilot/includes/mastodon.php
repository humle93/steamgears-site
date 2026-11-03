<?php
/**
 * Mastodon — direct posting to any instance (P2.2).
 *
 * One call: POST {instance}/api/v1/statuses with a Bearer token and the status text.
 * The instance is the credential's other half, because there is no central Mastodon:
 * the token belongs to one server and only that server accepts it.
 *
 * Length: an instance may raise its limit, but 500 is the value every server accepts,
 * so 500 is the default and `mastodon_limit` overrides it. The trimming is grapheme
 * aware and reserves the tracking link, so a shortened post never ends in half an emoji
 * or a broken URL.
 *
 * Errors are the ordinary WP_Error path the publisher already understands: the queue
 * puts the item back with backoff, the P0.1 alert fires after the last attempt, and the
 * publish log keeps the message. A 401 is reported, never swallowed.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Mastodon {
    const DEFAULT_MAX = 500;
    const FLOOR = 100;
    const CEILING = 5000;

    public static function instance(): string {
        $raw = trim((string) SGAP_Settings::get('mastodon_instance', ''));
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        return rtrim($raw, '/');
    }

    public static function token(): string {
        return trim((string) SGAP_Settings::get('mastodon_token', ''));
    }

    public static function configured(): bool {
        return self::instance() !== '' && self::token() !== '';
    }

    /** On when the credentials exist and mastodon is in the channel list. */
    public static function enabled(): bool {
        return self::configured() && in_array('mastodon', SGAP_Publer::channels(), true);
    }

    public static function handles(string $channel): bool {
        return $channel === 'mastodon' && self::enabled();
    }

    /** The instance's character budget; 500 unless Settings says otherwise. */
    public static function limit(): int {
        $set = (int) SGAP_Settings::get('mastodon_limit', self::DEFAULT_MAX);
        if ($set <= 0) {
            return self::DEFAULT_MAX;
        }
        return max(self::FLOOR, min(self::CEILING, $set));
    }

    public static function compose(array $row, string $url): string {
        return SGAP_Text::compose($row, $url, self::limit());
    }

    /**
     * Publishes one queue row. Returns the post URL (the queue's external id) or a
     * WP_Error that carries the instance's own message — Mastodon answers with
     * {"error": "..."}, which is far more useful than the status code alone.
     */
    public static function publish(array $row) {
        if (!self::configured()) {
            return new WP_Error('sgap_mastodon_setup', 'Mastodon is not configured: set the instance and an access token under Settings → Publer, and add mastodon to the channels list.');
        }
        $url = SGAP_Text::link($row);
        $text = self::compose($row, $url);
        if ($text === '') {
            return new WP_Error('sgap_mastodon_text', 'Mastodon: the post has no text, or the tracking link alone leaves no room inside ' . self::limit() . ' characters.');
        }
        $response = wp_remote_post(self::instance() . '/api/v1/statuses', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . self::token(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Idempotency-Key' => 'sgap-' . (int) ($row['id'] ?? 0) . '-' . substr(md5($text), 0, 8)
            ],
            'body' => wp_json_encode(['status' => $text, 'visibility' => 'public'])
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('sgap_mastodon_network', 'Mastodon could not be reached: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $reason = is_array($data) ? trim((string) ($data['error'] ?? '')) : '';
            if ($reason === '' && is_array($data) && isset($data['error_description'])) {
                $reason = trim((string) $data['error_description']);
            }
            return new WP_Error(
                'sgap_mastodon_http',
                'Mastodon: ' . ($reason !== '' ? $reason : 'HTTP ' . $code),
                ['status' => $code]
            );
        }
        if (!is_array($data) || empty($data['id'])) {
            return new WP_Error('sgap_mastodon_http', 'Mastodon accepted the post but returned no id.');
        }
        $posted = (string) ($data['url'] ?? '');
        return $posted !== '' ? $posted : 'mastodon:' . (string) $data['id'];
    }
}
