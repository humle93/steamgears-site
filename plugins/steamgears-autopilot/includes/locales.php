<?php
/**
 * Multi-language variants (P2.5): one source post, a caption per locale.
 *
 * The translated page comes from WPML or Polylang — the plugin never guesses a
 * URL. A language with no translated page, no translator answer or an empty
 * answer is reported back to the caller; the source post stays queued and
 * nothing disappears silently. Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Locales {
    /** Languages per post. Six keeps the AI bill for one queue item sane. */
    const MAX = 6;
    const DEFAULT_SOURCE = 'en';
    /** A language tag: "da" or "pt_br". */
    const PATTERN = '/^[a-z]{2}(_[a-z]{2})?$/';

    const SYSTEM = 'You translate social media posts for Steamgears (steamgears.eu). Keep the voice, '
        . 'the hashtags, the emoji, the prices, the product names and every number from the source exactly '
        . 'as they are. Never add or remove a fact, never invent a price or a discount, and never put a URL '
        . 'in the text: the link is attached separately.';

    /** The language the source posts are written in. */
    public static function source(): string {
        return self::code((string) SGAP_Settings::get('locale_source', self::DEFAULT_SOURCE), self::DEFAULT_SOURCE);
    }

    /** One valid language tag, lowercased, or $default when the value is not one. */
    public static function code(string $raw, string $default = ''): string {
        $code = strtolower(trim($raw));
        return preg_match(self::PATTERN, $code) ? $code : $default;
    }

    /** The saved list, cleaned. */
    public static function locales(): array {
        return self::codes((string) SGAP_Settings::get('locales', ''));
    }

    /** @return string[] Every language in $raw that should get a variant: valid, unique, never the source. */
    public static function codes(string $raw): array {
        $source = self::source();
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower($raw)) ?: [] as $code) {
            $code = trim($code);
            if ($code === '' || !preg_match(self::PATTERN, $code) || $code === $source || in_array($code, $out, true)) {
                continue;
            }
            $out[] = $code;
            if (count($out) >= self::MAX) break;
        }
        return $out;
    }

    public static function enabled(): bool {
        return self::locales() !== [];
    }

    /** The translated post ID for $locale, or 0 when WPML/Polylang has no translation. */
    public static function translation_id(int $post_id, string $locale): int {
        if ($post_id <= 0 || $locale === '') return 0;
        // Polylang first: it answers directly, WPML only through its filter.
        if (function_exists('pll_get_post')) {
            $id = (int) pll_get_post($post_id, $locale);
            if ($id > 0) return $id;
        }
        $type = function_exists('get_post_type') ? (string) get_post_type($post_id) : '';
        $id = (int) apply_filters('wpml_object_id', $post_id, $type !== '' ? $type : 'post', false, $locale);
        return ($id > 0 && $id !== $post_id) ? $id : 0;
    }

    /** The URL of the translated page, or '' when there is none. Never guesses. */
    public static function permalink(int $post_id, string $locale): string {
        $id = self::translation_id($post_id, $locale);
        if ($id <= 0) return '';
        return (string) esc_url_raw((string) get_permalink($id), ['http', 'https']);
    }

    /** A translator callable from a filter, so a site can use DeepL or a human instead. */
    public static function translator(): ?callable {
        $translator = apply_filters('sgap_locale_translator', null);
        return is_callable($translator) ? $translator : null;
    }

    /**
     * One caption in $locale from the configured AI.
     * @return array|WP_Error
     */
    public static function ai_translate(array $row, string $locale) {
        if (!class_exists('SGAP_AI')) {
            return new WP_Error('sgap_locale_no_ai', 'The AI module is not loaded.');
        }
        $title = trim((string) ($row['title'] ?? ''));
        $body = trim((string) ($row['body'] ?? ''));
        if ($title === '' || $body === '') {
            return new WP_Error('sgap_locale_no_source', 'The source post has no caption to translate.');
        }
        $schema = [
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string'], 'body' => ['type' => 'string']],
            'required' => ['title', 'body'],
            'additionalProperties' => false
        ];
        $prompt = 'Translate this social post from ' . self::source() . " into {$locale}, keeping the same "
            . "length and shape.\n\nTITLE:\n{$title}\n\nBODY:\n{$body}";
        $answer = SGAP_AI::json_call(self::SYSTEM, $prompt, $schema, 1200, 'low');
        if (is_wp_error($answer)) {
            return $answer;
        }
        $out = [
            'title' => trim((string) ($answer['title'] ?? '')),
            'body' => trim((string) ($answer['body'] ?? ''))
        ];
        if ($out['title'] === '' || $out['body'] === '') {
            return new WP_Error('sgap_locale_empty', "The translator returned an empty caption for {$locale}.");
        }
        return $out;
    }

    /**
     * One queue item per locale for a source row that was just queued.
     * @return array{items: array<int, array>, errors: array<int, array{locale: string, error: string}>}
     */
    public static function expand(array $row, int $source_id, ?callable $translator = null): array {
        $out = ['items' => [], 'errors' => []];
        if (!self::enabled()) return $out;
        $translator = $translator ?? self::translator();
        $post_id = (int) ($row['post_id'] ?? 0);
        foreach (self::locales() as $locale) {
            // Without a translated page there is nothing to link to, so there is no variant.
            $page = self::permalink($post_id, $locale);
            if ($page === '') {
                $out['errors'][] = ['locale' => $locale, 'error' => "no translated page in {$locale} (WPML/Polylang has none for this post)"];
                continue;
            }
            $translated = $translator !== null ? $translator($row, $locale) : self::ai_translate($row, $locale);
            if (is_wp_error($translated)) {
                $out['errors'][] = ['locale' => $locale, 'error' => $translated->get_error_message()];
                continue;
            }
            if (!is_array($translated)) {
                $out['errors'][] = ['locale' => $locale, 'error' => "the translation into {$locale} was not a caption"];
                continue;
            }
            $title = trim((string) ($translated['title'] ?? ''));
            $body = trim((string) ($translated['body'] ?? ''));
            if ($title === '' || $body === '') {
                $out['errors'][] = ['locale' => $locale, 'error' => "the translation into {$locale} came back empty"];
                continue;
            }
            $out['items'][] = [
                'channel' => (string) ($row['channel'] ?? ''),
                'angle' => (string) ($row['angle'] ?? 'angle_a'),
                'content_type' => (string) ($row['content_type'] ?? 'product'),
                'format' => (string) ($row['format'] ?? 'pin'),
                'post_id' => $post_id,
                'destination_url' => $page,
                'title' => $title,
                'body' => $body,
                'image_url' => (string) ($row['image_url'] ?? ''),
                'locale' => $locale,
                'source_id' => $source_id
            ];
        }
        return $out;
    }
}
