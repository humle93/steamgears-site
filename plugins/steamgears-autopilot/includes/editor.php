<?php
/**
 * AI editor (7.83.0): a second opinion on every generated post before it may reach the queue.
 *
 * The quality gate (quality.php) checks rules: banned words, links, alt text, duplicates. It cannot
 * read. The editor does: one request per page to OpenRouter's free models judges all of the page's
 * posts against the page they promote and scores each 0-10. Below MIN_SCORE the draft is rejected
 * with the reason. When no editor answers, Full auto does not queue the posts: they wait under
 * AI Drafts for a person, because "nobody checked it" is not the same as "it is fine".
 * Costs nothing: only `:free` models are asked.
 */
if (!defined('ABSPATH')) {
    exit;
}

class SGAP_Editor {
    const MIN_SCORE = 7;
    /**
     * Free text models, in order; OpenRouter walks the list itself when one is busy. OpenRouter
     * refuses more than 3 (measured live 12/11: "'models' array must have 3 items or fewer").
     */
    const MODELS = ['nvidia/nemotron-3-ultra-550b-a55b:free', 'nvidia/nemotron-3.5-super-vl:free', 'openrouter/free'];
    const MAX_MODELS = 3;

    public static function min_score(): int {
        return max(0, min(10, (int) apply_filters('sgap_editor_min_score', self::MIN_SCORE)));
    }

    /** @return string[] */
    public static function models(): array {
        return array_slice(array_values(array_filter(array_map('strval', (array) apply_filters('sgap_editor_models', self::MODELS)))), 0, self::MAX_MODELS);
    }

    /** On when an OpenRouter key exists and the model list is not emptied by the filter. */
    public static function enabled(): bool {
        return SGAP_AI::api_key() !== '' && self::models() !== [];
    }

    public static function prompt(array $posts, array $source): string {
        $items = [];
        foreach ($posts as $p) {
            $items[] = ['channel' => (string) ($p['channel'] ?? ''), 'title' => (string) ($p['title'] ?? ''),
                'text' => (string) ($p['text'] ?? ''), 'hashtags' => array_values((array) ($p['hashtags'] ?? []))];
        }
        return "You are a strict social media editor for steamgears.eu, a steampunk shop. Score each post 0-10.\n"
            . "Deduct hard for: facts, prices, discounts, shipping promises or guarantees that are NOT in the page below; "
            . "generic AI filler and cliches (\"elevate\", \"unlock\", \"in today's world\", \"delve\"); repeating the same "
            . "opening across channels; broken or mixed language; hashtag or keyword stuffing; wrong tone for the channel; "
            . "anything misleading or offensive. 8+ only if you would post it on the shop's account as it is.\n"
            . 'Answer JSON only: {"reviews": [{"channel": "...", "score": <0-10>, "problems": ["..."]}]}' . "\n\n"
            . "PAGE: " . (string) ($source['title'] ?? '') . "\n" . mb_substr((string) ($source['summary'] ?? ''), 0, 2500) . "\n\n"
            . "POSTS:\n" . wp_json_encode($items);
    }

    /**
     * @return array{ok:bool, by_channel?:array<string,array{score:float,problems:string[]}>, model?:string, error?:string}
     */
    public static function review(array $posts, array $source): array {
        if (!self::enabled()) {
            return ['ok' => false, 'error' => 'no OpenRouter key for the editor'];
        }
        $models = self::models();
        // The free models reason first: 1 200 tokens were all spent thinking (finish_reason=length,
        // measured live 12/11); 4 000 leaves room for the answer. Free models cost nothing per token.
        $body = ['model' => $models[0], 'models' => $models, 'max_tokens' => 4000,
            'messages' => [['role' => 'user', 'content' => self::prompt($posts, $source)]]];
        $response = SGAP_AI::openrouter_post(SGAP_AI::api_key(), $body, 90);
        $decoded = SGAP_AI::decode($response, 'Editor');
        if (is_wp_error($decoded)) {
            return ['ok' => false, 'error' => $decoded->get_error_message()];
        }
        $text = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        if (!preg_match('/\{.*\}/s', $text, $m)) {
            return ['ok' => false, 'error' => 'the editor answered without JSON'];
        }
        $data = json_decode($m[0], true);
        $out = [];
        foreach ((array) ($data['reviews'] ?? []) as $r) {
            $channel = sanitize_key((string) ($r['channel'] ?? ''));
            $score = $r['score'] ?? null;
            if ($channel === '' || !is_numeric($score) || $score < 0 || $score > 10) {
                continue;
            }
            $out[$channel] = ['score' => round((float) $score, 1),
                'problems' => array_slice(array_map(fn($p) => mb_substr(sanitize_text_field((string) $p), 0, 160), (array) ($r['problems'] ?? [])), 0, 5)];
        }
        if (!$out) {
            return ['ok' => false, 'error' => 'the editor gave no usable scores'];
        }
        return ['ok' => true, 'by_channel' => $out, 'model' => (string) ($decoded['model'] ?? $models[0])];
    }

    /**
     * What happens to one post: 'approve' (may be queued), 'reject' (stored as rejected) or 'hold'
     * (stored as pending, never queued automatically). Plus the line for the draft's gate report.
     * @return array{action:string, note:string}
     */
    public static function verdict(array $review, string $channel): array {
        if (!$review['ok']) {
            return ['action' => 'hold', 'note' => 'Editor: not checked (' . mb_substr((string) ($review['error'] ?? ''), 0, 120) . ') – waits for you.'];
        }
        $r = $review['by_channel'][$channel] ?? null;
        if ($r === null) {
            return ['action' => 'hold', 'note' => 'Editor: no score for this channel – waits for you.'];
        }
        $line = 'Editor: ' . $r['score'] . '/10' . ($r['problems'] ? ' – ' . implode('; ', $r['problems']) : '');
        return ['action' => $r['score'] >= self::min_score() ? 'approve' : 'reject', 'note' => $line];
    }
}
