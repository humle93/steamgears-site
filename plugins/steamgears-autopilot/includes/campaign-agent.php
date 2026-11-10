<?php
/**
 * P6 — the campaign agent: one published page, one stated goal, one post per
 * channel, every draft gated and stored for human review.
 *
 * The agent deliberately cannot publish. It writes `pending` rows in sgap_ai_drafts
 * even when Full Auto is on, never calls SGAP_Queue or SGAP_Publer, and keeps the
 * per-channel findings of the pre-flight and the quality gate next to the draft so the
 * owner sees them before approving it.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Campaign_Agent {
    /** admin-post action behind the dashboard form. */
    const ACTION = 'sgap_campaign';
    const NONCE = 'sgap_campaign_run';
    /** Drafts one run may write, so a wide channel list cannot flood the review page. */
    const MAX_DRAFTS = 10;
    const MAX_GOAL = 160;
    const MIN_GOAL = 8;

    public static function init(): void {
        add_action('admin_post_' . self::ACTION, [__CLASS__, 'handle']);
    }

    /** Post types a campaign may promote: the same two the daily writer reads. */
    public static function types(): array {
        return array_values(array_filter(['post', 'page', 'product'], 'post_type_exists'));
    }

    /**
     * Validates the brief without writing anything.
     * @return array{post: WP_Post, goal: string, campaign: string, channels: string[]}|WP_Error
     */
    public static function validate(array $input) {
        $id = absint($input['post_id'] ?? 0);
        $post = $id > 0 ? get_post($id) : null;
        if (!is_object($post) || !in_array((string) $post->post_type, self::types(), true)) {
            return new WP_Error('sgap_campaign_source', 'Pick a published post, page or product to promote.');
        }
        if ((string) $post->post_status !== 'publish') {
            return new WP_Error('sgap_campaign_source', 'That page is not published yet, so the campaign would link to nothing.');
        }
        $goal = trim(sanitize_text_field((string) ($input['goal'] ?? '')));
        if (mb_strlen($goal) < self::MIN_GOAL) {
            return new WP_Error('sgap_campaign_goal', 'Write the campaign goal in at least ' . self::MIN_GOAL . ' characters — e.g. “sell the brass goggles to cosplayers before Halloween”.');
        }
        if (mb_strlen($goal) > self::MAX_GOAL) {
            return new WP_Error('sgap_campaign_goal', 'Keep the campaign goal under ' . self::MAX_GOAL . ' characters.');
        }
        $configured = SGAP_Publer::channels();
        $wanted = array_values(array_unique(array_map('sanitize_key', (array) ($input['channels'] ?? []))));
        $channels = array_values(array_intersect($wanted, $configured));
        if ($channels === []) {
            return new WP_Error('sgap_campaign_channels', $configured === []
                ? 'No channels are configured yet: pick them under Settings → Publer → Channels.'
                : 'Pick at least one of the configured channels: ' . implode(', ', $configured) . '.');
        }
        $campaign = sanitize_key((string) ($input['campaign'] ?? ''));
        if ($campaign === '') {
            $campaign = sanitize_key(get_post_field('post_name', $post->ID));
        }
        if ($campaign === '') {
            $campaign = 'campaign_' . (int) $post->ID;
        }
        return ['post' => $post, 'goal' => $goal, 'campaign' => substr($campaign, 0, 60), 'channels' => array_slice($channels, 0, self::MAX_DRAFTS)];
    }

    /**
     * Runs one campaign. Writes pending drafts and nothing else: no queue rows, no
     * publishing call, no settings change.
     * @return array|WP_Error
     */
    public static function run(array $input) {
        global $wpdb;
        $brief = self::validate($input);
        if (is_wp_error($brief)) {
            return $brief;
        }
        $post = $brief['post'];
        $report = [
            'post_id' => (int) $post->ID,
            'source' => get_the_title($post),
            'goal' => $brief['goal'],
            'campaign' => $brief['campaign'],
            'drafts' => [],
            'skipped' => [],
            'errors' => [],
            'review_url' => admin_url('admin.php?page=sgap-ai')
        ];
        $channels = [];
        foreach ($brief['channels'] as $channel) {
            if (self::pending_exists((int) $post->ID, $channel, $brief['campaign'])) {
                $report['skipped'][] = ['channel' => $channel, 'reason' => 'A draft for this campaign is already waiting for review.'];
                continue;
            }
            $channels[] = $channel;
        }
        if ($channels === []) {
            $report['note'] = 'Nothing written: every chosen channel already has a draft for this campaign waiting on AI Drafts.';
            return $report;
        }
        $source = SGAP_Generator::source($post) + ['campaign_goal' => $brief['goal']];
        $generated = SGAP_AI::generate($source, $channels);
        if (is_wp_error($generated)) {
            $report['errors'][] = $generated->get_error_message();
            return $report;
        }
        foreach ($generated as $item) {
            $channel = (string) $item['channel'];
            $body = trim((string) $item['text'] . ($item['hashtags'] !== '' ? "\n\n" . $item['hashtags'] : ''));
            $draft = [
                'post_id' => (int) $post->ID,
                'channel' => $channel,
                'angle' => 'campaign',
                'content_type' => $post->post_type === 'product' ? 'product' : 'guide',
                'format' => $channel === 'pinterest' ? 'pin' : 'post',
                'title' => $item['title'] !== '' ? $item['title'] : get_the_title($post),
                'body' => $body,
                'hashtags' => (string) $item['hashtags'],
                'alt_text' => (string) $item['alt_text'],
                'campaign' => $brief['campaign'],
                'gate_report' => self::gate($channel, $item, $body, (string) get_permalink((int) $post->ID), self::image($post)),
                'status' => 'pending',
                'ai_model' => substr(SGAP_AI::model(), 0, 40),
                'created_at' => current_time('mysql')
            ];
            if (!$wpdb->insert($wpdb->prefix . 'sgap_ai_drafts', $draft)) {
                $report['errors'][] = $channel . ': the draft could not be saved.';
                continue;
            }
            $report['drafts'][] = [
                'id' => (int) $wpdb->insert_id,
                'channel' => $channel,
                'title' => $draft['title'],
                'warnings' => $draft['gate_report'] === '' ? [] : explode("\n", $draft['gate_report'])
            ];
        }
        update_post_meta($post->ID, '_sgap_campaign_at', time());
        delete_transient('sgap_dashboard_stats');
        return $report;
    }

    /**
     * The findings for one channel, as lines for the owner. Warnings are kept even when
     * they would not block: the point of the review step is to see them before approving.
     */
    public static function gate(string $channel, array $item, string $body, string $link, string $image): string {
        $row = ['channel' => $channel, 'title' => (string) $item['title'], 'body' => $body, 'destination_url' => $link, 'image_url' => $image];
        $lines = [];
        $pre = SGAP_Preflight::check($channel, $row);
        foreach ($pre['problems'] as $problem) {
            $lines[] = ucfirst($channel) . ': ' . (string) ($problem['message'] ?? '');
        }
        foreach ($pre['notes'] as $note) {
            $lines[] = ucfirst($channel) . ': ' . (string) $note;
        }
        $quality = SGAP_Quality::check($row);
        foreach ($quality['problems'] as $problem) {
            $lines[] = ucfirst($channel) . ': ' . (string) ($problem['message'] ?? '');
        }
        return implode("\n", array_slice(array_filter($lines), 0, 12));
    }

    /** The image the posts will use, if the page has one. */
    public static function image($post): string {
        return is_object($post) && !empty($post->ID) ? (string) get_the_post_thumbnail_url((int) $post->ID, 'large') : '';
    }

    /** True when this campaign already has a draft waiting on the same channel. */
    public static function pending_exists(int $post_id, string $channel, string $campaign): bool {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sgap_ai_drafts WHERE post_id = %d AND channel = %s AND campaign = %s AND status = 'pending' LIMIT 1",
            $post_id, $channel, $campaign
        ));
        return (int) $found > 0;
    }

    /** The campaign runs waiting for review, newest first — the card's list. */
    public static function waiting(int $limit = 10): array {
        global $wpdb;
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, campaign, COUNT(*) AS drafts, MAX(created_at) AS last_at
             FROM {$wpdb->prefix}sgap_ai_drafts WHERE status = 'pending' AND campaign <> ''
             GROUP BY post_id, campaign ORDER BY last_at DESC LIMIT %d",
            max(1, min(50, $limit))
        ), ARRAY_A);
    }

    /** admin-post: the dashboard form. Capability and nonce first, then the run. */
    public static function handle(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 403);
        }
        check_admin_referer(self::NONCE);
        $input = [
            'post_id' => isset($_POST['post_id']) ? absint($_POST['post_id']) : 0,
            'goal' => isset($_POST['goal']) ? wp_unslash((string) $_POST['goal']) : '',
            'campaign' => isset($_POST['campaign']) ? wp_unslash((string) $_POST['campaign']) : '',
            'channels' => isset($_POST['channels']) ? (array) wp_unslash($_POST['channels']) : []
        ];
        $report = self::run($input);
        if (is_wp_error($report)) {
            SGAP_Admin::done('Campaign not written: ' . $report->get_error_message());
        }
        SGAP_Admin::done(self::summary($report));
    }

    /** One message for the dashboard, in the same shape the other runs use. */
    public static function summary(array $r): string {
        $lines = [];
        if (!empty($r['drafts'])) {
            $lines[] = 'Campaign “' . $r['campaign'] . '” for “' . $r['source'] . '”: ' . count($r['drafts']) . ' draft(s) waiting for review — nothing is queued or published.';
            foreach ($r['drafts'] as $draft) {
                $lines[] = '  #' . $draft['id'] . ' ' . $draft['channel'] . (count($draft['warnings']) ? ' · ' . count($draft['warnings']) . ' note(s)' : '');
            }
        } elseif (!empty($r['note'])) {
            $lines[] = (string) $r['note'];
        }
        foreach ($r['skipped'] as $skip) {
            $lines[] = 'Skipped ' . $skip['channel'] . ': ' . $skip['reason'];
        }
        foreach ($r['errors'] as $error) {
            $lines[] = 'Error: ' . $error;
        }
        if ($lines === []) {
            $lines[] = 'The model returned no usable posts. Try again, or write the goal more concretely.';
        }
        return implode("\n", $lines);
    }

    /**
     * The dashboard card: the brief, the configured channels, and what is already
     * waiting. A form here means the agent is one press away and its output is reviewed
     * on AI Drafts, not here.
     */
    public static function card(): void {
        SGAP_UI::card_open('Campaign agent', 'awards');
        echo '<p class="description" style="margin-top:0">One page, one goal, one post per channel — written by the AI, checked against each channel\'s rules, and <strong>saved for your review</strong>. It never queues or publishes, even when Full Auto is on.</p>';
        $channels = SGAP_Publer::channels();
        if ($channels === []) {
            echo '<p>' . SGAP_UI::pill('No channels configured', 'warn') . ' '
                . '<a href="' . esc_url(admin_url('admin.php?page=sgap-settings')) . '">Settings → Publer</a>' . '</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="sgap-campaign-form">'
            . '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">'
            . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<p><label for="sgap-campaign-source"><strong>Page to promote</strong></label><br>'
            . self::source_field() . '</p>'
            . '<p><label for="sgap-campaign-goal"><strong>Campaign goal</strong></label><br>'
            . '<input type="text" class="regular-text" id="sgap-campaign-goal" name="goal" maxlength="' . (int) self::MAX_GOAL . '" required '
            . 'placeholder="e.g. sell the brass goggles to cosplayers before Halloween"></p>'
            . '<p><label for="sgap-campaign-name"><strong>Campaign name (utm_campaign)</strong></label><br>'
            . '<input type="text" class="regular-text" id="sgap-campaign-name" name="campaign" maxlength="60" placeholder="empty = the page slug"></p>'
            . '<fieldset><legend><strong>Channels</strong></legend>';
        foreach ($channels as $channel) {
            echo '<label style="margin-right:12px"><input type="checkbox" name="channels[]" value="' . esc_attr($channel) . '" checked> ' . esc_html($channel) . '</label>';
        }
        echo '</fieldset><p><button class="button button-primary"' . ($channels === [] ? ' disabled' : '') . '>'
            . '<span class="dashicons dashicons-edit"></span>Write campaign drafts</button></p></form>';
        $waiting = self::waiting();
        if ($waiting !== []) {
            echo '<p class="description"><strong>Waiting for review:</strong> ';
            $parts = [];
            foreach ($waiting as $row) {
                $parts[] = esc_html(get_the_title((int) $row['post_id']) . ' · ' . $row['campaign'] . ' · ' . (int) $row['drafts'] . ' draft(s)');
            }
            echo implode(' — ', $parts) . '</p>';
        }
        echo '<p class="description">Review them under <a href="' . esc_url(admin_url('admin.php?page=sgap-ai')) . '">Drafts to approve</a>. '
            . 'The notes from the channel checks are stored with each draft.</p>';
        SGAP_UI::card_close();
    }

    /** A date-ordered source picker with published pages only. */
    private static function source_field(): string {
        $posts = get_posts([
            'post_type' => self::types(),
            'post_status' => 'publish',
            'posts_per_page' => 60,
            'orderby' => 'modified',
            'no_found_rows' => true
        ]);
        $selected = isset($_GET['source']) ? absint($_GET['source']) : 0;
        $html = '<select id="sgap-campaign-source" name="post_id" required><option value="">Choose a page…</option>';
        foreach ($posts as $post) {
            $html .= '<option value="' . esc_attr($post->ID) . '"' . selected($selected, (int) $post->ID, false) . '>'
                . esc_html(get_the_title($post) . ' (' . $post->post_type . ')') . '</option>';
        }
        return $html . '</select>';
    }
}
