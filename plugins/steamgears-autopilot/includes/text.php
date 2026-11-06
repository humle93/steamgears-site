<?php
/**
 * Text helpers shared by the channels that post directly (P2.2, P2.3).
 *
 * The unit is an extended grapheme cluster (\X), not a byte and not a code point:
 * a flag, an emoji with a skin-tone modifier or an accented letter is one thing to
 * the reader, and cutting inside it produces a broken character on the other side.
 *
 * Kept apart from the publishers so the length rule is written once, and so the
 * per-instance limit (Mastodon) and the per-network limit (Telegram) stay settings
 * rather than constants buried in three classes.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Text {
    /** Extended grapheme clusters. */
    public static function graphemes(string $text): array {
        preg_match_all('/\X/u', $text, $matches);
        return $matches[0] ?? [];
    }

    public static function length(string $text): int {
        return count(self::graphemes($text));
    }

    /** Cuts at a grapheme boundary and marks the cut, so a shortened post reads as one. */
    public static function shorten(string $text, int $max): string {
        $graphemes = self::graphemes($text);
        if (count($graphemes) <= $max) {
            return $text;
        }
        return rtrim(implode('', array_slice($graphemes, 0, max(0, $max - 1)))) . '…';
    }

    /**
     * Body plus link, inside $max graphemes. The link is reserved first and never
     * shortened — a cut URL is worse than a short caption — and the body falls back
     * to the title. Returns '' when not even the link fits.
     */
    public static function compose(array $row, string $url, int $max): string {
        $body = trim((string) ($row['body'] ?? ''));
        if ($body === '') {
            $body = trim((string) ($row['title'] ?? ''));
        }
        if ($body === '') {
            return '';
        }
        $room = $max - self::length($url) - 2; // the two newlines before the link
        if ($room < 1) {
            return '';
        }
        return self::shorten($body, $room) . "\n\n" . $url;
    }

    /** The tracking link of a queue row, generated when the row was queued without one. */
    public static function link(array $row): string {
        $url = (string) ($row['tracking_url'] ?? '');
        if ($url === '' && !empty($row['id'])) {
            $url = SGAP_Tracking::url((int) $row['id']);
        }
        return $url;
    }
}
