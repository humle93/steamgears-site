<?php
/**
 * Telegram — direct posting to a channel or chat through a bot (P2.3).
 *
 * One call: POST https://api.telegram.org/bot{token}/sendMessage with chat_id and text.
 * The bot token is the credential; the chat id says where the message lands (a channel
 * is @name or a numeric id, a group is a negative id).
 *
 * The failure mode that matters: Telegram answers **HTTP 200 with {"ok": false}** for a
 * refused message — a wrong chat id, a removed bot, a blocked channel. Treating 200 as
 * success would report a post that nobody can see, so `ok` is checked before anything
 * else and `description` becomes the error message.
 *
 * Length: 4096 characters per message. Trimmed grapheme-aware with the tracking link
 * reserved, so a long caption is shortened instead of being refused by the API.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Telegram {
    const API = 'https://api.telegram.org/';
    const MAX = 4096;

    public static function token(): string {
        return trim((string) SGAP_Settings::get('telegram_bot_token', ''));
    }

    public static function chat(): string {
        return trim((string) SGAP_Settings::get('telegram_chat_id', ''));
    }

    public static function configured(): bool {
        return self::token() !== '' && self::chat() !== '';
    }

    /** On when the credentials exist and telegram is in the channel list. */
    public static function enabled(): bool {
        return self::configured() && in_array('telegram', SGAP_Publer::channels(), true);
    }

    public static function handles(string $channel): bool {
        return $channel === 'telegram' && self::enabled();
    }

    public static function compose(array $row, string $url): string {
        return SGAP_Text::compose($row, $url, self::MAX);
    }

    /**
     * Publishes one queue row. Returns 'telegram:<message id>' (the queue's external id)
     * or a WP_Error with Telegram's own description. The token never enters a message:
     * it is in the URL, and error text only ever carries the API's description.
     */
    public static function publish(array $row) {
        if (!self::configured()) {
            return new WP_Error('sgap_telegram_setup', 'Telegram is not configured: set the bot token and the chat id under Settings → Publer, and add telegram to the channels list.');
        }
        $url = SGAP_Text::link($row);
        $text = self::compose($row, $url);
        if ($text === '') {
            return new WP_Error('sgap_telegram_text', 'Telegram: the post has no text, or the tracking link alone leaves no room inside ' . self::MAX . ' characters.');
        }
        $response = wp_remote_post(self::API . 'bot' . self::token() . '/sendMessage', [
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'chat_id' => self::chat(),
                'text' => $text,
                'disable_web_page_preview' => false
            ])
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('sgap_telegram_network', 'Telegram could not be reached: ' . $response->get_error_message());
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return new WP_Error('sgap_telegram_http', 'Telegram answered with something that is not JSON (HTTP ' . $code . ').', ['status' => $code]);
        }
        // HTTP 200 is not success here: Telegram reports a refused message as ok=false.
        if (empty($data['ok'])) {
            $reason = trim((string) ($data['description'] ?? ''));
            return new WP_Error(
                'sgap_telegram_http',
                'Telegram: ' . ($reason !== '' ? $reason : 'the API said ok=false (HTTP ' . $code . ')'),
                ['status' => $code, 'error_code' => (int) ($data['error_code'] ?? 0)]
            );
        }
        if (empty($data['result']['message_id'])) {
            return new WP_Error('sgap_telegram_http', 'Telegram accepted the message but returned no message id.');
        }
        return 'telegram:' . (string) $data['result']['message_id'];
    }
}
