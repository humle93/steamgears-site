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
    public static function meta(bool $readonly, bool $idempotent, bool $destructive = false): array {
        return [
            'show_in_rest' => true,
            'public' => true, // read by MCP servers that use the high-level flag
            'mcp' => ['public' => true, 'type' => 'tool'],
            'annotations' => ['readonly' => $readonly, 'destructive' => $destructive, 'idempotent' => $idempotent]
        ];
    }

    public static function object(array $properties, array $required = []): array {
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
            'channel-health' => [
                'Channel analytics status',
                'Per channel: is a token stored, what is the next step, and which platform numbers have actually landed in sgap_events inside the window (default 90 days). Telegram reports no_metric, because the Bot API has no per-post numbers; a channel that is ready but empty is the one where a working token returned nothing.',
                [
                    'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 90]
                ], [], [__CLASS__, 'channel_health'], true, true
            ],
            'agent-brief' => [
                'What the agents know',
                'The measured brief every writing agent receives: site facts, clicks/orders/revenue per channel and angle in the window, and the lessons rebuilt from those numbers. Read-only, and the same text is injected into the daily writer, the campaign agent and the Content Studio prompts.',
                [
                    'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 30]
                ], [], [__CLASS__, 'agent_brief'], true, true
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
                    'scheduled_at' => ['type' => 'string', 'description' => 'Optional date/time in site time, e.g. 2026-11-02 18:00'],
                    'angle' => ['type' => 'string', 'maxLength' => 20, 'description' => 'Optional angle used by the learning table, e.g. angle_a. Empty = angle_a.'],
                    'content_type' => ['type' => 'string', 'maxLength' => 40, 'description' => 'Optional content type, e.g. product or custom. Empty = custom.'],
                    'format' => ['type' => 'string', 'maxLength' => 40, 'description' => 'Optional format, e.g. pin or post. Empty = post.'],
                    'campaign' => ['type' => 'string', 'description' => 'Optional utm_campaign for this post, e.g. clockwork_punk_2026_11. Empty = the campaign name in Settings → General.'],
                    'force' => ['type' => 'boolean', 'description' => 'Queue even when the quality gate blocks the post (a banned word, a dead link, a repeated caption). The problems come back as warnings.'],
                    'remind' => ['type' => 'boolean', 'description' => 'Do not post it: mail the owner the text, image and link so it can be posted by hand (stories, carousels and native uploads). The item goes to status reminded and is never retried.']
                ], ['channel', 'title', 'text', 'destination_url'], [__CLASS__, 'queue_post'], false, false
            ],
            'generate' => [
                'Generate posts now',
                'Runs the AI writer now (normally daily): picks site posts/products not promoted recently and writes one post per channel. Uses the AI API (costs a little). Results land in drafts, or in the queue when Full Auto is on.',
                ['posts' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'description' => 'How many site pages to write for. Default: the posts-per-day setting.']],
                [], [__CLASS__, 'generate'], false, false
            ],
            'campaign-drafts' => [
                'Write a campaign\'s drafts',
                'Runs the campaign agent for one published post, page or product: it writes one post per chosen channel, checks each one against that channel\'s rules and the quality gate, and saves them as drafts waiting for review. It never queues and never publishes — a person approves each draft. Returns the draft ids, the notes from the channel checks and the review URL.',
                [
                    'post_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The published post, page or product the campaign promotes.'],
                    'goal' => ['type' => 'string', 'minLength' => 8, 'maxLength' => 160, 'description' => 'Why this page is promoted now, e.g. "sell the goggles to cosplayers before Halloween".'],
                    'channels' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => SGAP_Publer::CHANNELS], 'minItems' => 1, 'description' => 'Channels to write for. Only configured channels are used.'],
                    'campaign' => ['type' => 'string', 'maxLength' => 60, 'description' => 'utm_campaign for this run, e.g. halloween_2026. Empty = the page slug.']
                ], ['post_id', 'goal', 'channels'], [__CLASS__, 'campaign_drafts'], false, false
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
            'update' => [
                'Update the plugin',
                'Installs the newest Steamgears Autopilot from GitHub (humle93/steamgears-site, main) over the installed version, if GitHub has a newer one. The plugin stays active. Returns the versions before and after.',
                [], [], [__CLASS__, 'update'], false, true
            ],
            'check-post' => [
                'Check a post before queueing',
                'Runs both gates on a post without queueing it. Pre-flight: caption length (with the tracking link counted in), hashtag ceiling, Instagram photo type/size/aspect, Pinterest board. Quality: banned words, dead links, missing alt text and a caption that already went out on this channel. Returns the problems that would make Publer refuse it or the quality gate stop it, the text after the mechanical repairs (shortened caption, surplus hashtags removed) and whether it would go through as-is.',
                [
                    'channel' => $channel,
                    'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    'text' => ['type' => 'string', 'minLength' => 1],
                    'destination_url' => ['type' => 'string', 'format' => 'uri'],
                    'image_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'The photo the post would use, if it has one']
                ], ['channel', 'title', 'text', 'destination_url'], [__CLASS__, 'check_post'], true, true
            ],
            'check-publer' => [
                'Check Publer connection',
                'Tests the Publer API key: lists workspaces, connected social accounts, and configured channels that have no Publer account.',
                [], [], [__CLASS__, 'check_publer'], true, true
            ],
            'list-evergreen' => [
                'List evergreen pages',
                'Lists the pages and products marked for evergreen recycling with their share budget, expiry date, last share and whether they are ready now. Also returns the recycle settings and how many shares have been used.',
                ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]], [], [__CLASS__, 'list_evergreen'], true, true
            ],
            'mark-evergreen' => [
                'Mark or unmark an evergreen page',
                'Marks a blog post or product for evergreen recycling, or stops it. Optional max shares override (0 = the setting) and an expiry date (YYYY-MM-DD); an empty date never expires.',
                [
                    'post_id' => ['type' => 'integer', 'minimum' => 1],
                    'evergreen' => ['type' => 'boolean', 'default' => true, 'description' => 'false stops recycling this page'],
                    'max' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Share budget for this page. 0 = the default in Settings → Evergreen.'],
                    'until' => ['type' => 'string', 'description' => 'Last day it may be recycled, YYYY-MM-DD. Empty = never expires.'],
                    'reset' => ['type' => 'boolean', 'description' => 'Also set the share count back to 0.']
                ], ['post_id'], [__CLASS__, 'mark_evergreen'], false, true
            ],
            'recycle-evergreen' => [
                'Recycle evergreen pages now',
                'Runs the evergreen recycler now (normally daily): writes a fresh variation for the next due pages and queues it on the next channel in rotation. Each share still passes the quality gate, preflight, blackout and duplicate guard.',
                ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'description' => 'How many pages to recycle. Default: the daily setting.']], [], [__CLASS__, 'recycle_evergreen'], false, false
            ],
            'meta-audit' => [
                'Audit post meta',
                'Audits titles and meta descriptions on published posts and pages: how many have no description, how many are outside the limits (title 60, description 70-155 characters), and which posts are worst. Read-only — it changes nothing.',
                [
                    'scan' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100, 'description' => 'How many of the newest published posts to check'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20, 'description' => 'How many of the worst posts to return']
                ], [], [__CLASS__, 'meta_audit'], true, true
            ],
            'niche-plan' => [
                'Plan a shop from a niche',
                'Turns one niche name into a complete shop plan: brand suggestions, five collections, priced products across the print-on-demand families, page drafts, a menu, the schema types the shop needs, and a readiness list of the steps only the owner can take. Read-only — nothing is created.',
                [
                    'niche' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 60, 'description' => 'The niche, e.g. "steampunk airship" or "cottagecore mushroom"'],
                    'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 40, 'default' => 10, 'description' => 'How many products the plan should hold'],
                    'margin' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500, 'description' => 'Margin floor in percent. Default: the shop setting (Settings → Shop & feeds).']
                ], ['niche'], [__CLASS__, 'niche_plan'], true, true
            ],
            'niche-apply' => [
                'Create the shop drafts from a niche',
                'Runs the niche plan and creates WooCommerce product drafts, product categories and page drafts. Nothing is published, no existing product is touched, and every created id is stored under a run key so the whole run can be rolled back.',
                [
                    'niche' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 60],
                    'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 40, 'default' => 10],
                    'margin' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                    'force' => ['type' => 'boolean', 'default' => false, 'description' => 'Create again even if this plan key has already been applied.']
                ], ['niche'], [__CLASS__, 'niche_apply'], false, false
            ],
            'niche-runs' => [
                'List niche shop runs',
                'Lists the niche runs that created drafts, with their key, the niche and how many collections, products and pages each created. Read-only.',
                [], [], [__CLASS__, 'niche_runs'], true, true
            ],
            'niche-rollback' => [
                'Roll back a niche shop run',
                'Deletes the drafts and unused product categories one niche run created. Published items and items not created by that run are kept and reported. Destructive: it deletes draft content, so it needs the run key from niche-runs.',
                [
                    'plan_key' => ['type' => 'string', 'minLength' => 6, 'description' => 'The run key returned by niche-apply'],
                    'dry' => ['type' => 'boolean', 'default' => false, 'description' => 'Only report what would be deleted']
                ], ['plan_key'], [__CLASS__, 'niche_rollback'], false, false
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
                'execute_callback' => static fn($input = null) => call_user_func($execute, $input),
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
            'latest_on_github' => SGAP_Updater::remote_version() ?: null,
            'autopilot' => SGAP_Settings::enabled('autopilot_enabled'),
            'full_auto' => SGAP_Settings::enabled('full_auto_enabled'),
            'channels' => SGAP_Publer::channels(),
            'publish_mode' => SGAP_RSS::enabled() ? 'rss (Publer RSS feeds)' : 'api (Publer API)',
            'rss_feeds' => SGAP_RSS::enabled() ? array_map([SGAP_RSS::class, 'url'], SGAP_Publer::channels()) : null,
            'publer_configured' => SGAP_RSS::enabled() || SGAP_Publer::configured(),
            'ai_configured' => SGAP_Settings::get('ai_api_key') !== '',
            'ai_provider' => SGAP_AI::provider(),
            'ai_model' => SGAP_AI::model(),
            'key_test' => get_option(SGAP_KeyCheck::RESULT) ?: null,
            'sending_paused_because' => SGAP_Queue::blocked_reason() !== '' ? SGAP_Queue::blocked_message() : null,
            'blackout' => SGAP_Blackout::summary(),
            'command_center' => class_exists('SGAP_CC') ? SGAP_CC::status() : null,
            'quality_gate' => ['mode' => SGAP_Quality::mode(), 'banned_words' => count(SGAP_Quality::words())],
            'evergreen' => SGAP_Evergreen::summary(),
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

    /**
     * The answer P1.5 is verified with: can the channel run, and did its numbers land?
     *
     * Read-only and idempotent: it queries sgap_events and the stored key state, and writes
     * nothing. The two halves are returned separately instead of summed, because "ready" and
     * "measured" answer different questions and a channel can be the first without the second.
     */
    public static function channel_health($input = null): array {
        $input = (array) $input;
        $days = max(SGAP_Channel_Health::MIN_DAYS,
            min(SGAP_Channel_Health::MAX_DAYS, (int) ($input['days'] ?? SGAP_Channel_Health::WINDOW_DAYS)));
        $analytics = SGAP_Channel_Health::analytics($days);
        // P7: in RSS mode there is no token to test, so the feed itself is the health reading.
        $feeds = SGAP_RSS::health();
        return [
            'window_days' => $days,
            'channels' => SGAP_Channel_Health::report(),
            'summary' => SGAP_Channel_Health::summary(),
            'analytics' => $analytics,
            'analytics_summary' => SGAP_Channel_Health::analytics_summary($analytics),
            'feeds' => $feeds,
            'feeds_summary' => SGAP_RSS::health_summary($feeds),
        ];
    }

    /** P8: what the agents have measured and learned — the same brief their prompts carry. */
    public static function agent_brief($input = null): array {
        $input = (array) $input;
        $days = max(1, min(365, (int) ($input['days'] ?? SGAP_Coach::WINDOW)));
        $outcomes = SGAP_Coach::outcomes($days);
        return [
            'window_days' => $outcomes['window_days'],
            'facts' => SGAP_Coach::facts(),
            'channels' => $outcomes['channels'],
            'angles' => $outcomes['angles'],
            'lessons' => SGAP_Coach::lessons(),
            'brief' => SGAP_Coach::brief()
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
            'angle' => $input['angle'] ?? '',
            'content_type' => $input['content_type'] ?? 'custom',
            'format' => $input['format'] ?? 'post',
            'campaign' => $input['campaign'] ?? '',
            'scheduled_at' => $scheduled,
            'force' => !empty($input['force']),
            'remind' => !empty($input['remind'])
        ]);
        return is_wp_error($result) ? $result : $result + ['status' => 'queued'];
    }

    /** Dry run of both gates — the publish-time pre-flight and the quality gate before queueing. Writes nothing. */
    public static function check_post($input = null): array {
        $input = (array) $input;
        $channel = (string) ($input['channel'] ?? '');
        $post = [
            'title' => (string) ($input['title'] ?? ''),
            'body' => (string) ($input['text'] ?? ''),
            'image_url' => (string) ($input['image_url'] ?? ''),
            'destination_url' => (string) ($input['destination_url'] ?? '')
        ];
        $check = SGAP_Preflight::check($channel, $post);
        $quality = SGAP_Quality::check($post + ['channel' => $channel]);
        return [
            'channel' => $channel,
            'ok' => $check['ok'] && $quality['ok'],
            'problems' => $check['problems'],
            'repairs' => $check['notes'],
            'quality' => ['ok' => $quality['ok'], 'mode' => $quality['mode'], 'problems' => $quality['problems']],
            'would_send' => ['title' => $check['row']['title'], 'text' => $check['row']['body']]
        ];
    }

    public static function generate($input = null): array {
        $input = (array) $input;
        return SGAP_Generator::run(isset($input['posts']) ? min(5, max(1, (int) $input['posts'])) : null);
    }

    public static function publish($input = null): array {
        return SGAP_Publer::tick();
    }

    /** P6: the campaign agent. Drafts only — approving and queueing stays a human step. */
    public static function campaign_drafts($input = null) {
        $input = (array) $input;
        return SGAP_Campaign_Agent::run([
            'post_id' => $input['post_id'] ?? 0,
            'goal' => $input['goal'] ?? '',
            'channels' => (array) ($input['channels'] ?? []),
            'campaign' => $input['campaign'] ?? ''
        ]);
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

    public static function update($input = null): array {
        return SGAP_Updater::run();
    }

    public static function check_keys($input = null): array {
        return SGAP_KeyCheck::run();
    }

    public static function list_evergreen($input = null): array {
        $input = (array) $input;
        $limit = min(100, max(1, (int) ($input['limit'] ?? 50)));
        $items = [];
        foreach (array_slice(SGAP_Evergreen::marked(), 0, $limit) as $id) {
            $items[] = ['title' => get_the_title($id), 'type' => get_post_type($id)] + SGAP_Evergreen::status($id);
        }
        return ['settings' => SGAP_Evergreen::summary(), 'items' => $items];
    }

    public static function mark_evergreen($input = null) {
        $input = (array) $input;
        $id = absint($input['post_id'] ?? 0);
        if (array_key_exists('evergreen', $input) && !$input['evergreen']) {
            SGAP_Evergreen::unmark($id);
            return ['post_id' => $id, 'marked' => false];
        }
        $result = SGAP_Evergreen::mark($id, [
            'max' => absint($input['max'] ?? 0),
            'until' => (string) ($input['until'] ?? ''),
            'reset' => !empty($input['reset'])
        ]);
        return $result;
    }

    public static function recycle_evergreen($input = null): array {
        $input = (array) $input;
        return SGAP_Evergreen::run(isset($input['limit']) ? min(10, max(1, (int) $input['limit'])) : null);
    }

    /** Read-only audit of titles and meta descriptions. Delegates to SGAP_Meta_Audit so the logic can be tested. */
    public static function meta_audit($input = null): array {
        return SGAP_Meta_Audit::audit($input);
    }

    /* ---- niche shop: one field, then drafts ---- */

    public static function niche_plan($input = null) {
        $input = (array) $input;
        $plan = SGAP_Niche_Shop::plan((string) ($input['niche'] ?? ''), $input);
        return is_wp_error($plan) ? $plan : SGAP_Niche_Shop::summary($plan);
    }

    public static function niche_apply($input = null) {
        $input = (array) $input;
        $input['apply'] = true;
        return SGAP_Niche_Shop::run($input);
    }

    public static function niche_runs($input = null): array {
        $runs = SGAP_Niche_Shop::runs();
        $out = [];
        foreach ($runs as $key => $run) {
            $out[] = [
                'plan_key' => $key,
                'niche' => (string) ($run['niche'] ?? ''),
                'created_at' => (string) ($run['created_at'] ?? ''),
                'collections' => count($run['created']['collections'] ?? []),
                'products' => count($run['created']['products'] ?? []),
                'pages' => count($run['created']['pages'] ?? []),
                'errors' => $run['errors'] ?? []
            ];
        }
        return ['count' => count($out), 'runs' => $out];
    }

    public static function niche_rollback($input = null) {
        $input = (array) $input;
        return SGAP_Niche_Shop::rollback((string) ($input['plan_key'] ?? ''), empty($input['dry']));
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
