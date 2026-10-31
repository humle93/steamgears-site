<?php
/**
 * Exposes the autopilot as WordPress abilities, so Claude (or any MCP client
 * connected through the site's MCP adapter) can check and run it.
 * Every ability requires an administrator. Nothing here deletes data.
 * Loaded from steamgears-autopilot.php; does nothing on WordPress without the Abilities API.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Abilities {
    const CATEGORY = 'steamgears-autopilot';

    public static function init(): void {
        add_action('wp_abilities_api_categories_init', [__CLASS__, 'register_category']);
        add_action('wp_abilities_api_init', [__CLASS__, 'register']);
    }

    public static function register_category(): void {
        if (function_exists('wp_register_ability_category')) {
            wp_register_ability_category(self::CATEGORY, [
                'label' => 'Steamgears Autopilot',
                'description' => 'Social media autopilot for steamgears.eu: AI drafts, publishing queue via Publer, results.'
            ]);
        }
    }

    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /** Shared meta: visible to MCP and REST, with MCP behaviour hints. */
    private static function meta(bool $readonly, bool $idempotent): array {
        return [
            'show_in_rest' => true,
            'public' => true, // read by MCP servers that use the high-level flag
            'mcp' => ['public' => true, 'type' => 'tool'],
            'annotations' => ['readonly' => $readonly, 'destructive' => false, 'idempotent' => $idempotent]
        ];
    }

    private static function object(array $properties, array $required = []): array {
        $schema = ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
        if ($required) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    /** name => [label, description, input schema properties, required, execute, readonly, idempotent] */
    private static function definitions(): array {
        $channel = ['type' => 'string', 'enum' => SGAP_Publer::CHANNELS];
        return [
            'status' => [
                'Autopilot status',
                'Overview of the Steamgears social autopilot: on/off switches, channels, whether Publer and the AI are configured, why sending is paused (if it is), queue counts by status, pending drafts, the last generation run, recent errors and 30-day results per channel.',
                [], [], [__CLASS__, 'status'], true, true
            ],
            'list-drafts' => [
                'List AI drafts',
                'Lists AI-written social posts. Default: those waiting for approval. Each has id, channel, title, text, hashtags and the page it promotes.',
                [
                    'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'used'], 'default' => 'pending'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20]
                ], [], [__CLASS__, 'list_drafts'], true, true
            ],
            'review-draft' => [
                'Approve or reject a draft',
                'Decides on one AI draft. "queue" approves it and adds it to the publishing queue, "approve" only marks it approved, "reject" rejects it.',
                [
                    'draft_id' => ['type' => 'integer', 'minimum' => 1],
                    'decision' => ['type' => 'string', 'enum' => ['queue', 'approve', 'reject']]
                ], ['draft_id', 'decision'], [__CLASS__, 'review_draft'], false, true
            ],
            'list-queue' => [
                'List queue',
                'Lists posts in the publishing queue (newest first) with status, attempts, last error and tracking link.',
                [
                    'status' => ['type' => 'string', 'enum' => ['queued', 'sending', 'published', 'failed', 'draft']],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20]
                ], [], [__CLASS__, 'list_queue'], true, true
            ],
            'queue-post' => [
                'Queue a custom post',
                'Adds a post you wrote yourself to the publishing queue. Instagram and Pinterest need image_url. The link is added automatically as a tracked /go/ link.',
                [
                    'channel' => $channel,
                    'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    'text' => ['type' => 'string', 'minLength' => 1],
                    'destination_url' => ['type' => 'string', 'format' => 'uri'],
                    'image_url' => ['type' => 'string', 'format' => 'uri'],
                    'scheduled_at' => ['type' => 'string', 'description' => 'Optional date/time in site time, e.g. 2026-11-02 18:00']
                ], ['channel', 'title', 'text', 'destination_url'], [__CLASS__, 'queue_post'], false, false
            ],
            'generate' => [
                'Generate posts now',
                'Runs the AI writer now (normally daily): picks site posts/products not promoted recently and writes one post per channel. Uses the AI API (costs a little). Results land in drafts, or in the queue when Full Auto is on.',
                ['posts' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'description' => 'How many site pages to write for. Default: the posts-per-day setting.']],
                [], [__CLASS__, 'generate'], false, false
            ],
            'publish' => [
                'Publish queue now',
                'Runs the publisher now (normally every 15 minutes). RSS mode: releases due posts into the per-channel RSS feeds that Publer auto-posts from. API mode: records the outcome of posts already sent to Publer, then sends due posts. Quiet hours, daily cap and batch size apply.',
                [], [], [__CLASS__, 'publish'], false, false
            ],
            'set-mode' => [
                'Switch autopilot on/off',
                'Turns the autopilot (daily writing + publishing) and/or Full Auto (skip approval) on or off. Leave a field out to keep it as is.',
                [
                    'autopilot' => ['type' => 'boolean'],
                    'full_auto' => ['type' => 'boolean']
                ], [], [__CLASS__, 'set_mode'], false, true
            ],
            'check-keys' => [
                'Test API keys',
                'Tests the saved Publer and AI API keys (no AI tokens used): key valid, workspace, connected accounts per channel, model available. Fills in the Publer workspace id when the key has exactly one workspace.',
                [], [], [__CLASS__, 'check_keys'], false, true
            ],
            'check-publer' => [
                'Check Publer connection',
                'Tests the Publer API key: lists workspaces, connected social accounts, and configured channels that have no Publer account.',
                [], [], [__CLASS__, 'check_publer'], true, true
            ]
        ];
    }

    public static function register(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        foreach (self::definitions() as $name => [$label, $description, $properties, $required, $execute, $readonly, $idempotent]) {
            $args = [
                'label' => $label,
                'description' => $description,
                'category' => self::CATEGORY,
                'output_schema' => ['type' => 'object'],
                'execute_callback' => $execute,
                'permission_callback' => [__CLASS__, 'can_manage'],
                'meta' => self::meta($readonly, $idempotent)
            ];
            if ($properties) {
                $args['input_schema'] = self::object($properties, $required);
            }
            wp_register_ability(self::CATEGORY . '/' . $name, $args);
        }
    }

    /* ---- callbacks: each takes the validated input array and returns an array or WP_Error ---- */

    public static function status($input = null): array {
        global $wpdb;
        $queue = $wpdb->prefix . 'sgap_queue';
        $counts = [];
        foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$queue} GROUP BY status") as $row) {
            $counts[$row->status] = (int) $row->n;
        }
        $last = get_option('sgap_last_generate');
        return [
            'version' => SGAP_VERSION,
            'autopilot' => SGAP_Settings::enabled('autopilot_enabled'),
            'full_auto' => SGAP_Settings::enabled('full_auto_enabled'),
            'channels' => SGAP_Publer::channels(),
            'publish_mode' => SGAP_RSS::enabled() ? 'rss (Publer RSS feeds)' : 'api (Publer API)',
            'rss_feeds' => SGAP_RSS::enabled() ? array_map([SGAP_RSS::class, 'url'], SGAP_Publer::channels()) : null,
            'publer_configured' => SGAP_RSS::enabled() || SGAP_Publer::configured(),
            'ai_configured' => SGAP_Settings::get('ai_api_key') !== '',
            'ai_provider' => (string) SGAP_Settings::get('ai_provider', 'anthropic'),
            'key_test' => get_option(SGAP_KeyCheck::RESULT) ?: null,
            'sending_paused_because' => SGAP_Queue::blocked_reason() ?: null,
            'can_send_now' => SGAP_Queue::capacity(),
            'limits' => [
                'daily_cap' => (int) SGAP_Settings::get('daily_cap', 8),
                'batch_size' => (int) SGAP_Settings::get('batch_size', 5),
                'quiet_hours' => SGAP_Settings::get('quiet_start', 23) . ':00-' . SGAP_Settings::get('quiet_end', 7) . ':00 (site time)'
            ],
            'queue' => $counts,
            'pending_drafts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts WHERE status = 'pending'"),
            'last_generation' => is_array($last) ? $last : null,
            'recent_errors' => $wpdb->get_results(
                "SELECT id, channel, status, attempts, error FROM {$queue} WHERE error IS NOT NULL AND error <> '' ORDER BY id DESC LIMIT 5",
                ARRAY_A
            ),
            'last_30_days' => $wpdb->get_results($wpdb->prepare(
                "SELECT channel, SUM(event_type = 'publish') AS published, SUM(event_type = 'click') AS clicks,
                        SUM(event_type = 'order') AS orders, SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue
                 FROM {$wpdb->prefix}sgap_events WHERE created_at >= %s GROUP BY channel",
                wp_date('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
            ), ARRAY_A)
        ];
    }

    public static function list_drafts($input = null): array {
        global $wpdb;
        $input = (array) $input;
        $status = in_array($input['status'] ?? '', ['pending', 'approved', 'rejected', 'used'], true) ? $input['status'] : 'pending';
        $limit = min(50, max(1, (int) ($input['limit'] ?? 20)));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, channel, title, body, hashtags, alt_text, status, created_at FROM {$wpdb->prefix}sgap_ai_drafts
             WHERE status = %s ORDER BY id DESC LIMIT %d",
            $status, $limit
        ), ARRAY_A);
        foreach ($rows as &$row) {
            $row['promotes'] = $row['post_id'] ? ['title' => get_the_title((int) $row['post_id']), 'url' => get_permalink((int) $row['post_id'])] : null;
            $row['has_image'] = $row['post_id'] ? has_post_thumbnail((int) $row['post_id']) : false;
            unset($row['post_id']);
        }
        unset($row);
        return ['status' => $status, 'drafts' => $rows];
    }

    public static function review_draft($input = null) {
        global $wpdb;
        $input = (array) $input;
        $id = absint($input['draft_id'] ?? 0);
        $decision = (string) ($input['decision'] ?? '');
        $draft = $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}sgap_ai_drafts WHERE id = %d", $id));
        if ($draft === null) {
            return new WP_Error('sgap_not_found', 'Unknown draft id.');
        }
        if ($decision === 'queue') {
            $queued = SGAP_Queue::from_draft($id);
            return is_wp_error($queued) ? $queued : ['draft_id' => $id, 'status' => 'used', 'queued' => $queued];
        }
        $status = $decision === 'reject' ? 'rejected' : 'approved';
        $wpdb->update($wpdb->prefix . 'sgap_ai_drafts', ['status' => $status], ['id' => $id]);
        delete_transient('sgap_dashboard_stats');
        return ['draft_id' => $id, 'status' => $status];
    }

    public static function list_queue($input = null): array {
        global $wpdb;
        $input = (array) $input;
        $limit = min(50, max(1, (int) ($input['limit'] ?? 20)));
        $where = !empty($input['status']) ? $wpdb->prepare(' WHERE status = %s', (string) $input['status']) : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, channel, title, status, attempts, scheduled_at, sent_at, published_at, error
             FROM {$wpdb->prefix}sgap_queue{$where} ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A);
        foreach ($rows as &$row) {
            $row['tracking_url'] = SGAP_Tracking::url((int) $row['id']);
        }
        unset($row);
        return ['items' => $rows];
    }

    public static function queue_post($input = null) {
        $input = (array) $input;
        $scheduled = null;
        if (!empty($input['scheduled_at'])) {
            $ts = strtotime((string) $input['scheduled_at']);
            if (!$ts) {
                return new WP_Error('sgap_invalid', 'scheduled_at is not a date/time.');
            }
            $scheduled = wp_date('Y-m-d H:i:s', $ts);
        }
        $result = SGAP_Queue::add([
            'channel' => $input['channel'] ?? '',
            'title' => $input['title'] ?? '',
            'body' => $input['text'] ?? '',
            'destination_url' => $input['destination_url'] ?? '',
            'image_url' => $input['image_url'] ?? '',
            'content_type' => 'custom',
            'format' => 'post',
            'scheduled_at' => $scheduled
        ]);
        return is_wp_error($result) ? $result : $result + ['status' => 'queued'];
    }

    public static function generate($input = null): array {
        $input = (array) $input;
        return SGAP_Generator::run(isset($input['posts']) ? min(5, max(1, (int) $input['posts'])) : null);
    }

    public static function publish($input = null): array {
        return SGAP_Publer::tick();
    }

    public static function set_mode($input = null): array {
        $input = (array) $input;
        foreach (['autopilot' => 'autopilot_enabled', 'full_auto' => 'full_auto_enabled'] as $field => $key) {
            if (array_key_exists($field, $input)) {
                SGAP_Settings::set($key, $input[$field] ? 'yes' : 'no');
            }
        }
        return [
            'autopilot' => SGAP_Settings::enabled('autopilot_enabled'),
            'full_auto' => SGAP_Settings::enabled('full_auto_enabled')
        ];
    }

    public static function check_keys($input = null): array {
        return SGAP_KeyCheck::run();
    }

    public static function check_publer($input = null) {
        if (SGAP_Settings::get('publer_api_key') === '') {
            return new WP_Error('sgap_publer', 'No Publer API key saved (Steamgears → Settings → Publer).');
        }
        $workspaces = SGAP_Publer::workspaces();
        if (is_wp_error($workspaces)) {
            return $workspaces;
        }
        $result = [
            'workspaces' => array_map(fn($w) => ['id' => $w['id'] ?? null, 'name' => $w['name'] ?? null], array_filter((array) $workspaces, 'is_array')),
            'workspace_id_set' => SGAP_Settings::get('publer_workspace_id') !== ''
        ];
        if ($result['workspace_id_set']) {
            $accounts = SGAP_Publer::accounts(true);
            if (is_wp_error($accounts)) {
                return $accounts;
            }
            $result['accounts'] = $accounts;
            $result['channels_without_account'] = array_values(array_diff(SGAP_Publer::channels(), array_column($accounts, 'provider')));
        }
        return $result;
    }
}
