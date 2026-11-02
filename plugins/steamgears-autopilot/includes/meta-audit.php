<?php
/**
 * Read-only meta audit: how the published posts look to a search engine.
 *
 * Answers the question the meta rounds keep asking by hand — how many published
 * posts have no meta description, how many titles/descriptions are outside the
 * limits, and which posts are worst — without touching a single row.
 *
 * Exposed as the ability steamgears-autopilot/meta-audit (see abilities.php).
 */

if (!defined('ABSPATH')) exit;

class SGAP_Meta_Audit {
    /** Where the two SEO plugins keep the meta description. Rank Math first, then Yoast. */
    const DESCRIPTION_KEYS = ['rank_math_description', '_yoast_wpseo_metadesc'];

    public static function limits(): array {
        return ['title_max' => 60, 'description_max' => 155, 'description_min' => 70];
    }

    /** Length in characters, not bytes — a Danish "æ" is one character. */
    public static function length(string $text): int {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }

    /** The saved meta description for a post, from whichever SEO plugin is in use. */
    public static function description(int $post_id): string {
        foreach (self::DESCRIPTION_KEYS as $key) {
            $value = trim((string) get_post_meta($post_id, $key, true));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /**
     * One row per published post: what it has, and what is wrong with it.
     * Pure apart from get_the_title/get_post_meta, so the summary below can be tested on fixtures.
     */
    public static function rows(int $scan = 100): array {
        $scan = min(500, max(1, $scan));
        $posts = get_posts([
            'numberposts' => $scan,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        $limits = self::limits();
        $rows = [];
        foreach ((array) $posts as $post) {
            $id = is_object($post) ? (int) ($post->ID ?? 0) : (int) $post;
            if ($id <= 0) {
                continue;
            }
            $title = trim(wp_strip_all_tags((string) get_the_title($id)));
            $description = self::description($id);
            $title_length = self::length($title);
            $description_length = self::length($description);
            $problems = [];
            if ($description === '') {
                $problems[] = 'missing description';
            } else {
                if ($description_length > $limits['description_max']) {
                    $problems[] = 'description too long';
                }
                if ($description_length < $limits['description_min']) {
                    $problems[] = 'description too short';
                }
            }
            if ($title_length > $limits['title_max']) {
                $problems[] = 'title too long';
            }
            $rows[] = [
                'id' => $id,
                'title' => $title,
                'url' => (string) get_permalink($id),
                'title_length' => $title_length,
                'description_length' => $description_length,
                'problems' => $problems
            ];
        }
        return $rows;
    }

    /**
     * Counts and the worst offenders. Pure: takes rows, returns a report.
     * Worst = most problems first, then the longest title, then the longest description.
     */
    public static function summarise(array $rows, int $limit = 20): array {
        $limit = min(100, max(1, $limit));
        $counts = [
            'checked' => count($rows),
            'clean' => 0,
            'missing_description' => 0,
            'title_too_long' => 0,
            'description_too_long' => 0,
            'description_too_short' => 0
        ];
        $flagged = [];
        foreach ($rows as $row) {
            $problems = (array) ($row['problems'] ?? []);
            if ($problems === []) {
                $counts['clean']++;
                continue;
            }
            foreach ($problems as $problem) {
                $key = str_replace(' ', '_', (string) $problem);
                if (isset($counts[$key])) {
                    $counts[$key]++;
                }
            }
            $flagged[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'title_length' => (int) ($row['title_length'] ?? 0),
                'description_length' => (int) ($row['description_length'] ?? 0),
                'problems' => array_values($problems)
            ];
        }
        usort($flagged, function (array $a, array $b): int {
            $by_problems = count($b['problems']) <=> count($a['problems']);
            if ($by_problems !== 0) {
                return $by_problems;
            }
            $by_title = $b['title_length'] <=> $a['title_length'];
            return $by_title !== 0 ? $by_title : $b['description_length'] <=> $a['description_length'];
        });
        return [
            'ok' => $flagged === [],
            'counts' => $counts,
            'limits' => self::limits(),
            'worst' => array_slice($flagged, 0, $limit)
        ];
    }

    /** The ability entry point: scan the site, then report. Writes nothing. */
    public static function audit($input = null): array {
        $input = (array) $input;
        $scan = (int) ($input['scan'] ?? 100);
        $limit = (int) ($input['limit'] ?? 20);
        $report = self::summarise(self::rows($scan), $limit);
        $report['scanned'] = min(500, max(1, $scan));
        $report['seo_plugin'] = self::description_keys_in_use();
        return $report;
    }

    /** Which SEO plugin is actually filling the description, so a report of "0 missing" can be trusted. */
    private static function description_keys_in_use(): string {
        return defined('RANK_MATH_VERSION') ? 'rank math' : (defined('WPSEO_VERSION') ? 'yoast' : 'ukendt');
    }
}
