<?php
/**
 * Pre-flight rules per channel: checks a queue item against the network's own
 * limits *before* anything is sent to Publer, and repairs what can be repaired
 * mechanically (a too-long caption, too many hashtags, a too-long pin title)
 * instead of letting Publer reject the post with a vague error later.
 *
 * Pure: no HTTP, no database writes, no AI. Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Preflight {
    /**
     * Caption limit per channel in characters, before the tracking link is added.
     * Instagram, Facebook and LinkedIn take the link in their own field, so the
     * caption keeps the whole budget there.
     */
    const CAPTION = [
        'facebook' => 63206,
        'linkedin' => 3000,
        'instagram' => 2200,
        'pinterest' => 500,
        'threads' => 500,
        'twitter' => 280,
        'bluesky' => 300,
        'mastodon' => 500,
        'telegram' => 4096,
        'google' => 1500
    ];

    /** Channels that put the link inside the caption: the link eats this much of the budget. */
    const LINK_IN_CAPTION = ['threads', 'bluesky', 'mastodon', 'telegram', 'google'];

    /** X counts every link as 23 characters, plus the two newlines the payload adds. */
    const TWITTER_LINK_COST = 25;

    /** Hashtag ceiling per channel — Threads allows exactly one. */
    const HASHTAGS = ['instagram' => 30, 'threads' => 1];

    /** Title length per channel (Pinterest cuts pins at 100). */
    const TITLE = ['pinterest' => 100];

    /** Instagram's photo rules (Meta content publishing documentation). */
    const IG_MIN_WIDTH = 320;
    const IG_MIN_RATIO = 0.8;
    const IG_MAX_RATIO = 1.91;

    /**
     * Characters a caption may use on this channel once the tracking link is accounted for.
     * 0 means "no caption limit we know of".
     */
    public static function caption_budget(string $channel, string $url): int {
        $limit = self::CAPTION[$channel] ?? 0;
        if ($limit === 0) {
            return 0;
        }
        if ($channel === 'twitter') {
            return $limit - self::TWITTER_LINK_COST;
        }
        if (in_array($channel, self::LINK_IN_CAPTION, true)) {
            return $limit - mb_strlen($url) - 2;
        }
        return $limit;
    }

    /**
     * Cuts a text at a word boundary and marks the cut, so a shortened caption
     * still reads like a sentence instead of stopping mid-word.
     */
    public static function cut(string $text, int $max): string {
        if ($max <= 1) {
            return '';
        }
        $text = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($text, ' ');
        if ($space !== false && $space > (int) ($max * 0.6)) {
            $text = mb_substr($text, 0, $space);
        }
        return rtrim($text, " \t\n\r.,;:") . '…';
    }

    /**
     * Mechanical repairs: shorten the caption, drop surplus hashtags, cut the pin title.
     * @return array{row: array, notes: string[]}
     */
    public static function repair(string $channel, array $row): array {
        $notes = [];
        $url = (string) ($row['tracking_url'] ?? '');
        if ($url === '') {
            $url = (string) ($row['destination_url'] ?? '');
        }
        $text = trim((string) ($row['body'] ?? ''));
        $budget = self::caption_budget($channel, $url);
        if ($budget > 0 && mb_strlen($text) > $budget) {
            $text = self::cut($text, $budget);
            $notes[] = ucfirst($channel) . ' caption shortened to ' . $budget . ' characters.';
        }
        $max_tags = self::HASHTAGS[$channel] ?? 0;
        if ($max_tags > 0 && $text !== '') {
            preg_match_all('/#[\p{L}\p{N}_]+/u', $text, $matches);
            $tags = $matches[0] ?? [];
            if (count($tags) > $max_tags) {
                foreach (array_slice($tags, $max_tags) as $tag) {
                    $at = mb_strrpos($text, $tag);
                    if ($at !== false) {
                        $text = mb_substr($text, 0, $at) . mb_substr($text, $at + mb_strlen($tag));
                    }
                }
                $text = trim((string) preg_replace('/[ \t]{2,}/', ' ', $text));
                $notes[] = ucfirst($channel) . ' allows ' . $max_tags . ' hashtag' . ($max_tags === 1 ? '' : 's') . '; kept the first ' . $max_tags . '.';
            }
        }
        $row['body'] = $text;

        $title = (string) ($row['title'] ?? '');
        $max_title = self::TITLE[$channel] ?? 0;
        if ($max_title > 0 && mb_strlen($title) > $max_title) {
            $row['title'] = self::cut($title, $max_title);
            $notes[] = ucfirst($channel) . ' title shortened to ' . $max_title . ' characters.';
        }
        return ['row' => $row, 'notes' => $notes];
    }

    /**
     * What the post image is: mime type and pixel size, from the media library
     * when the URL is an attachment, otherwise from the file extension.
     * Never fetches the image. @return array{mime: string, width: int, height: int, source: string}
     */
    public static function image_info(string $url): array {
        $info = ['mime' => '', 'width' => 0, 'height' => 0, 'source' => 'unknown'];
        if ($url === '') {
            return $info;
        }
        $id = (int) attachment_url_to_postid($url);
        if ($id > 0) {
            $meta = wp_get_attachment_metadata($id);
            if (is_array($meta)) {
                $info['width'] = (int) ($meta['width'] ?? 0);
                $info['height'] = (int) ($meta['height'] ?? 0);
                $info['mime'] = (string) ($meta['mime_type'] ?? '') ?: (string) get_post_mime_type($id);
                $info['source'] = 'attachment';
            }
        }
        if ($info['mime'] === '') {
            $path = (string) wp_parse_url($url, PHP_URL_PATH);
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
            if (isset($map[$ext])) {
                $info['mime'] = $map[$ext];
                $info['source'] = 'extension';
            }
        }
        return $info;
    }

    /**
     * Everything that would make Publer reject this item, for its channel.
     * @return array<int, array{code: string, field: string, message: string}>
     */
    public static function problems(string $channel, array $row): array {
        $problems = [];
        if (!in_array($channel, SGAP_Publer::CHANNELS, true)) {
            return [self::problem('unsupported_channel', 'channel', "Channel '{$channel}' is not supported by the Publer publisher.")];
        }
        $image = (string) ($row['image_url'] ?? '');

        if (in_array($channel, SGAP_Publer::NEEDS_PHOTO, true) && $image === '') {
            $problems[] = self::problem('no_photo', 'image_url',
                ucfirst($channel) . ' needs a photo: give the post a featured image.');
        }
        if ($channel === 'pinterest' && (string) SGAP_Settings::get('publer_pinterest_board') === ''
            && !(SGAP_Settings::get('pinterest_direct', 'no') === 'yes' && trim((string) SGAP_Settings::get('pinterest_board_id', '')) !== '')) {
            $problems[] = self::problem('no_board', 'publer_pinterest_board',
                'Pinterest needs a board: set "Publer pinterest board" in Settings.');
        }
        if ($channel === 'instagram' && $image !== '') {
            $info = self::image_info($image);
            if ($info['mime'] !== '' && $info['mime'] !== 'image/jpeg') {
                $problems[] = self::problem('image_not_jpeg', 'image_url',
                    'Instagram only accepts JPEG; this image is ' . strtoupper(str_replace('image/', '', $info['mime'])) . '. Regenerate it as a JPEG or pick another featured image.');
            }
            if ($info['width'] > 0 && $info['width'] < self::IG_MIN_WIDTH) {
                $problems[] = self::problem('image_too_small', 'image_url',
                    'Instagram needs an image at least ' . self::IG_MIN_WIDTH . ' px wide; this one is ' . $info['width'] . ' px.');
            }
            if ($info['width'] > 0 && $info['height'] > 0) {
                $ratio = $info['width'] / $info['height'];
                if ($ratio < self::IG_MIN_RATIO || $ratio > self::IG_MAX_RATIO) {
                    $problems[] = self::problem('image_out_of_ratio', 'image_url',
                        'Instagram needs a photo between 4:5 and 1.91:1; this one is ' . $info['width'] . '×' . $info['height'] . ' px.');
                }
            }
        }
        return $problems;
    }

    private static function problem(string $code, string $field, string $message): array {
        return ['code' => $code, 'field' => $field, 'message' => $message];
    }

    /**
     * Repairs the item, then reports what is left.
     * @return array{ok: bool, problems: array, notes: string[], row: array}
     */
    public static function check(string $channel, array $row): array {
        if (empty($row['tracking_url']) && !empty($row['id'])) {
            $row['tracking_url'] = SGAP_Tracking::url((int) $row['id']);
        }
        $repaired = self::repair($channel, $row);
        $problems = self::problems($channel, $repaired['row']);
        return [
            'ok' => !$problems,
            'problems' => $problems,
            'notes' => $repaired['notes'],
            'row' => $repaired['row']
        ];
    }

    /** The problems as one sentence for the queue row, the publish log and the admin. */
    public static function message(array $problems): string {
        $messages = [];
        foreach (array_slice($problems, 0, 3) as $problem) {
            $messages[] = (string) ($problem['message'] ?? '');
        }
        return implode(' ', array_filter($messages));
    }
}
