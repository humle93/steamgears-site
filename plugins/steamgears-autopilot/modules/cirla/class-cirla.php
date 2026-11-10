<?php
/**
 * Cirla — a module of its own, switched on and off with one setting.
 *
 * Built after docs/library/cirla-teknisk-dokumentation-2026-11-06.md §9.3–9.5. The file is
 * loaded from steamgears-autopilot.php only while `sgap_cirla_enabled` is `yes`, so a module
 * that is off cannot register one ability or one cron job — that is what "off" has to mean.
 *
 * The four actions are deliberately the safe ones: read the status, write one draft, run the
 * SEO check on one page, suggest social posts. None of them publishes, queues or deletes
 * anything; a draft lands under AI Drafts and waits for the owner, exactly like the AI writer.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Cirla {
    /** Stored as sgap_cirla_enabled, like every other setting in the plugin. */
    const SETTING = 'cirla_enabled';
    /** Its own capability, mapped to administrators in map_cap(). */
    const CAP = 'sgap_cirla_run';
    const CRON = 'sgap_cirla_daily';
    /** The plugin's existing ability category, so MCP shows Cirla next to the rest. */
    const CATEGORY = 'steamgears-autopilot';
    /** The drafts this module writes carry this angle, so they can be told apart. */
    const SOURCE = 'cirla';
    const LOG = 'sgap_cirla_log';
    const SEEN = 'sgap_cirla_seen';
    const SPEND = 'sgap_cirla_spend';

    /** action => what it does. A setting decides which of them are allowed. */
    const ACTIONS = [
        'status' => 'Read the module status',
        'draft' => 'Write one social post as a draft',
        'seo' => 'Run the SEO check on one page',
        'social' => 'Suggest social posts for one page'
    ];

    public static function option(): string {
        return (string) get_option('sgap_' . self::SETTING, 'no') === 'yes' ? 'yes' : 'no';
    }

    public static function enabled(): bool {
        return self::option() === 'yes';
    }

    public static function init(): void {
        add_action('wp_abilities_api_init', [__CLASS__, 'register_abilities']);
        add_action(self::CRON, [__CLASS__, 'tick']);
        // The module's own capability, so "may run Cirla" is not the same question as
        // "may manage the site" once the owner gives a redaktør the module and nothing else.
        add_filter('map_meta_cap', [__CLASS__, 'map_cap'], 10, 4);
        add_action('update_option_sgap_' . self::SETTING, [__CLASS__, 'sync'], 10, 2);
        if (self::enabled()) {
            self::schedule();
        }
    }

    /** Administrators get sgap_cirla_run from the capability they already have. */
    public static function map_cap($caps, $cap, $user_id = 0, $args = []) {
        if ((string) $cap === self::CAP) {
            return ['manage_options'];
        }
        return $caps;
    }

    /** The actions the owner has left switched on. Empty list = nothing may run. */
    public static function actions(): array {
        $raw = (string) get_option('sgap_cirla_actions', implode(',', array_keys(self::ACTIONS)));
        $allowed = [];
        foreach (array_map('trim', explode(',', $raw)) as $name) {
            if (isset(self::ACTIONS[$name])) {
                $allowed[] = $name;
            }
        }
        return $allowed;
    }

    public static function allows(string $action): bool {
        return in_array($action, self::actions(), true);
    }

    public static function can_run(): bool {
        return self::enabled() && current_user_can(self::CAP);
    }

    /** Null when the action may run, otherwise the refusal to hand back. */
    public static function guard(string $action): ?WP_Error {
        if (!self::enabled()) {
            return new WP_Error('sgap_cirla_off', 'The Cirla module is switched off. Turn it on under Steamgears → Settings & keys → Cirla.');
        }
        if (!self::allows($action)) {
            return new WP_Error('sgap_cirla_action_off', sprintf(
                'The Cirla action "%s" is switched off. Allowed now: %s.',
                $action,
                self::actions() ? implode(', ', self::actions()) : 'none'
            ));
        }
        if (!current_user_can(self::CAP)) {
            return new WP_Error('sgap_cirla_forbidden', 'This account may not run Cirla actions: it needs the sgap_cirla_run capability, which administrators have.');
        }
        return null;
    }

    /* ---- abilities ---- */

    public static function register_abilities(): void {
        // Off means off: no ability is registered at all, so nothing shows up over MCP.
        if (!self::enabled() || !function_exists('wp_register_ability')) {
            return;
        }
        foreach (self::definitions() as $name => [$label, $description, $properties, $required, $callback, $readonly]) {
            $args = [
                'label' => $label,
                'description' => $description,
                'category' => self::CATEGORY,
                'output_schema' => ['type' => 'object'],
                'execute_callback' => static function ($input = null) use ($callback) {
                    return call_user_func($callback, is_array($input) ? $input : []);
                },
                'permission_callback' => [__CLASS__, 'can_run'],
                'meta' => [
                    'show_in_rest' => true,
                    'public' => true,
                    'mcp' => ['public' => true, 'type' => 'tool'],
                    'annotations' => ['readonly' => $readonly, 'destructive' => false, 'idempotent' => $readonly]
                ]
            ];
            if ($properties) {
                $schema = ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
                if ($required) {
                    $schema['required'] = $required;
                }
                $args['input_schema'] = $schema;
            }
            wp_register_ability(self::CATEGORY . '/' . $name, $args);
        }
    }

    /** name => [label, description, input properties, required, callback, readonly] */
    private static function definitions(): array {
        return [
            'cirla-status' => [
                'Cirla status',
                'Whether the Cirla module is switched on, which of its four actions are allowed, when its daily job runs next, how much of the daily AI budget is spent, and the last actions it took. Read-only.',
                [], [], [__CLASS__, 'status'], true
            ],
            'cirla-draft' => [
                'Cirla: write one draft',
                'Writes one social post for a topic (or for a page, when post_id is given) and saves it under AI Drafts as pending. It runs through the plugin\'s quality gate first and is never queued or published by this call.',
                [
                    'topic' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    'channel' => ['type' => 'string', 'enum' => SGAP_Publer::CHANNELS, 'default' => 'facebook'],
                    'post_id' => ['type' => 'integer', 'minimum' => 0, 'default' => 0]
                ],
                ['topic'], [__CLASS__, 'draft'], false
            ],
            'cirla-seo-check' => [
                'Cirla: SEO check',
                'Checks one page or post against the plugin\'s own meta limits (title 60 characters, description 70–155) and reports what is missing. Reads WordPress and Rank Math/Yoast; no AI call and nothing is written.',
                ['post_id' => ['type' => 'integer', 'minimum' => 1]],
                ['post_id'], [__CLASS__, 'seo_check'], true
            ],
            'cirla-social-suggestions' => [
                'Cirla: suggest social posts',
                'Suggests up to five social posts for one page, one per channel, without saving anything: a suggestion you can read, edit and hand to queue-post yourself. Spends AI calls from the module\'s daily budget.',
                [
                    'post_id' => ['type' => 'integer', 'minimum' => 1],
                    'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 3]
                ],
                ['post_id'], [__CLASS__, 'social_suggestions'], false
            ]
        ];
    }

    /* ---- actions ---- */

    public static function status($input = null): array {
        global $wpdb;
        $spent = self::spent();
        $next = (int) wp_next_scheduled(self::CRON);
        return [
            'module' => 'cirla',
            'version' => defined('SGAP_VERSION') ? SGAP_VERSION : '',
            'enabled' => self::enabled(),
            'capability' => self::CAP,
            'actions' => self::actions(),
            'cron' => ['hook' => self::CRON, 'next' => $next > 0 ? gmdate('c', $next) : null],
            'ai_calls_today' => ['used' => $spent['n'], 'limit' => self::daily_limit()],
            'pending_drafts' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts WHERE angle = %s AND status = %s",
                self::SOURCE,
                'pending'
            )),
            'log' => self::entries(5),
            'queues_or_publishes' => false
        ];
    }

    /** @return array|WP_Error */
    public static function draft($input = null) {
        $input = is_array($input) ? $input : [];
        if ($error = self::guard('draft')) {
            return $error;
        }
        $topic = sanitize_text_field((string) ($input['topic'] ?? ''));
        if ($topic === '') {
            return new WP_Error('sgap_cirla_topic', 'Give the draft a topic, or a post_id to write about.');
        }
        $channel = (string) ($input['channel'] ?? 'facebook');
        if (!in_array($channel, SGAP_Publer::CHANNELS, true)) {
            return new WP_Error('sgap_cirla_channel', sprintf('"%s" is not one of the plugin\'s channels.', $channel));
        }
        $post_id = max(0, (int) ($input['post_id'] ?? 0));
        if ($error = self::spend()) {
            return $error;
        }
        $source = ['title' => $topic, 'type' => 'topic', 'summary' => $topic];
        if ($post_id > 0) {
            $post = get_post($post_id);
            if (!$post) {
                return new WP_Error('sgap_cirla_post_missing', sprintf('No post with id %d.', $post_id));
            }
            $source = SGAP_Generator::source($post);
        }
        $generated = self::generate($source, [$channel]);
        if (is_wp_error($generated)) {
            self::fail('draft', $generated->get_error_code(), $generated->get_error_message());
            return $generated;
        }
        $item = $generated[0] ?? null;
        if (!is_array($item) || trim((string) ($item['text'] ?? '')) === '') {
            return new WP_Error('sgap_cirla_empty', 'The model returned no usable post, so nothing was saved.');
        }
        $title = (string) ($item['title'] ?? '') !== '' ? (string) $item['title'] : $topic;
        $body = trim((string) $item['text'] . ((string) ($item['hashtags'] ?? '') !== '' ? "\n\n" . $item['hashtags'] : ''));
        $destination = $post_id > 0 ? (string) get_permalink($post_id) : '';
        $gate = SGAP_Quality::check([
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
            'destination_url' => $destination,
            'image_url' => ''
        ]);
        if (empty($gate['ok'])) {
            $message = SGAP_Quality::message(SGAP_Quality::blocking((array) ($gate['problems'] ?? [])));
            self::log('draft', 'stopped by the quality gate: ' . $message);
            self::fail('draft', 'sgap_cirla_gate', $message);
            return new WP_Error('sgap_cirla_gate', 'The quality gate stopped this draft: ' . $message);
        }

        // The same task asked twice is the same draft, not a second one: the row is keyed on
        // the channel, the page and the topic, so a retrying client cannot fill AI Drafts.
        $key = self::key($channel, $post_id, $topic);
        $seen = self::seen();
        if (isset($seen[$key]) && self::draft_exists((int) $seen[$key])) {
            self::log('draft', sprintf('the same task is already waiting as draft #%d', (int) $seen[$key]));
            return [
                'id' => (int) $seen[$key],
                'duplicate' => true,
                'channel' => $channel,
                'title' => $title,
                'warnings' => [],
                'queued' => false,
                'published' => false
            ];
        }

        global $wpdb;
        $row = [
            'post_id' => $post_id,
            'channel' => $channel,
            'angle' => self::SOURCE,
            'content_type' => $post_id > 0 ? 'guide' : 'topic',
            'format' => in_array($channel, ['pinterest'], true) ? 'pin' : 'post',
            'title' => $title,
            'body' => $body,
            'hashtags' => (string) ($item['hashtags'] ?? ''),
            'alt_text' => (string) ($item['alt_text'] ?? ''),
            'campaign' => '',
            'gate_report' => implode("\n", array_map(static fn($problem) => (string) ($problem['message'] ?? ''), (array) ($gate['problems'] ?? []))),
            'status' => 'pending',
            'ai_model' => substr((string) SGAP_AI::model(), 0, 40),
            'created_at' => current_time('mysql')
        ];
        if (!$wpdb->insert($wpdb->prefix . 'sgap_ai_drafts', $row)) {
            return new WP_Error('sgap_cirla_save', 'The draft could not be saved.');
        }
        $id = (int) $wpdb->insert_id;
        $seen[$key] = $id;
        self::remember($seen);
        self::log('draft', sprintf('draft #%d for %s: %s', $id, $channel, $title));
        return [
            'id' => $id,
            'duplicate' => false,
            'channel' => $channel,
            'title' => $title,
            'warnings' => array_map(static fn($problem) => (string) ($problem['message'] ?? ''), (array) ($gate['problems'] ?? [])),
            'queued' => false,
            'published' => false
        ];
    }

    public static function seo_check($input = null): array {
        $input = is_array($input) ? $input : [];
        if ($error = self::guard('seo')) {
            return $error;
        }
        $post_id = (int) ($input['post_id'] ?? 0);
        if ($post_id <= 0) {
            return new WP_Error('sgap_cirla_post', 'Give the check a post id.');
        }
        if (!get_post($post_id)) {
            return new WP_Error('sgap_cirla_post_missing', sprintf('No post with id %d.', $post_id));
        }
        $limits = SGAP_Meta_Audit::limits();
        $title = trim(wp_strip_all_tags((string) get_the_title($post_id)));
        $description = SGAP_Meta_Audit::description($post_id);
        $title_length = SGAP_Meta_Audit::length($title);
        $description_length = SGAP_Meta_Audit::length($description);
        $problems = [];
        if ($title === '') {
            $problems[] = 'the title is empty';
        } elseif ($title_length > $limits['title_max']) {
            $problems[] = sprintf('the title is %d characters, over the %d limit', $title_length, $limits['title_max']);
        }
        if ($description === '') {
            $problems[] = 'no meta description is saved';
        } elseif ($description_length < $limits['description_min']) {
            $problems[] = sprintf('the meta description is %d characters, under the %d minimum', $description_length, $limits['description_min']);
        } elseif ($description_length > $limits['description_max']) {
            $problems[] = sprintf('the meta description is %d characters, over the %d limit', $description_length, $limits['description_max']);
        }
        self::log('seo', sprintf('checked post #%d: %s', $post_id, $problems ? implode('; ', $problems) : 'no problems'));
        return [
            'post_id' => $post_id,
            'title' => $title,
            'title_length' => $title_length,
            'title_max' => (int) $limits['title_max'],
            'description_length' => $description_length,
            'description_min' => (int) $limits['description_min'],
            'description_max' => (int) $limits['description_max'],
            'problems' => $problems,
            'ok' => $problems === [],
            'written' => false
        ];
    }

    public static function social_suggestions($input = null): array {
        $input = is_array($input) ? $input : [];
        if ($error = self::guard('social')) {
            return $error;
        }
        $post_id = (int) ($input['post_id'] ?? 0);
        if ($post_id <= 0) {
            return new WP_Error('sgap_cirla_post', 'Give the suggestions a post id.');
        }
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('sgap_cirla_post_missing', sprintf('No post with id %d.', $post_id));
        }
        $count = min(5, max(1, (int) ($input['count'] ?? 3)));
        $channels = array_slice(['facebook', 'instagram', 'pinterest', 'threads', 'bluesky'], 0, $count);
        if ($error = self::spend()) {
            return $error;
        }
        $generated = self::generate(SGAP_Generator::source($post), $channels);
        if (is_wp_error($generated)) {
            self::fail('social', $generated->get_error_code(), $generated->get_error_message());
            return $generated;
        }
        $suggestions = [];
        foreach ($generated as $item) {
            $suggestions[] = [
                'channel' => (string) ($item['channel'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'text' => (string) ($item['text'] ?? ''),
                'hashtags' => (string) ($item['hashtags'] ?? '')
            ];
        }
        self::log('social', sprintf('suggested %d posts for post #%d', count($suggestions), $post_id));
        return [
            'post_id' => $post_id,
            'suggestions' => $suggestions,
            'queued' => false,
            'published' => false
        ];
    }

    /**
     * The AI call, behind one filter so the owner can replace it (a human editor, another
     * model) exactly like sgap_evergreen_variation — and so the tests can answer without
     * a network. Everything else about the call is the plugin's own path.
     *
     * @return array|WP_Error
     */
    private static function generate(array $source, array $channels) {
        $override = apply_filters('sgap_cirla_generate', null, $source, $channels);
        if (is_wp_error($override)) {
            return $override;
        }
        if (is_array($override)) {
            return $override;
        }
        return SGAP_AI::generate($source, $channels);
    }

    /* ---- the daily job and the switch ---- */

    public static function tick(): void {
        if (!self::enabled()) {
            return;
        }
        // The daily job only records that it ran. Writing posts stays a called action:
        // a module that is on must not spend the owner's credits on its own.
        update_option('sgap_cirla_last_tick', current_time('mysql'), false);
        self::log('cron', 'the daily job ran; no post was written or sent by it');
    }

    public static function schedule(): void {
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
        }
    }

    /** Fired by WordPress when the setting changes; the module itself is unchanged. */
    public static function sync($old = '', $new = ''): void {
        if ((string) $new === 'yes') {
            self::schedule();
            self::log('toggle', 'the module was switched on');
            return;
        }
        wp_clear_scheduled_hook(self::CRON);
        self::log('toggle', 'the module was switched off; existing drafts are kept');
    }

    /* ---- budget ---- */

    private static function daily_limit(): int {
        return max(0, (int) get_option('sgap_cirla_daily_calls', 5));
    }

    /** @return array{date: string, n: int} */
    private static function spent(): array {
        $today = substr((string) current_time('mysql'), 0, 10);
        $raw = get_option(self::SPEND);
        if (!is_array($raw) || (string) ($raw['date'] ?? '') !== $today) {
            return ['date' => $today, 'n' => 0];
        }
        return ['date' => $today, 'n' => (int) ($raw['n'] ?? 0)];
    }

    /** Counts one AI call, or refuses when the day's budget is gone. */
    private static function spend(): ?WP_Error {
        $limit = self::daily_limit();
        $spent = self::spent();
        if ($spent['n'] >= $limit) {
            return new WP_Error('sgap_cirla_budget', sprintf(
                'The daily Cirla budget is used up (%d of %d AI calls). It resets after midnight, site time.',
                $spent['n'],
                $limit
            ));
        }
        update_option(self::SPEND, ['date' => $spent['date'], 'n' => $spent['n'] + 1], false);
        return null;
    }

    /** How many AI calls the module has spent today. Used by the status action. */
    public static function calls_today(): int {
        return self::spent()['n'];
    }

    /* ---- memory and log ---- */

    private static function key(string $channel, int $post_id, string $topic): string {
        return substr(hash('sha256', $channel . '|' . $post_id . '|' . $topic), 0, 40);
    }

    /** @return array<string, int> */
    private static function seen(): array {
        $seen = get_option(self::SEEN);
        return is_array($seen) ? $seen : [];
    }

    private static function remember(array $seen): void {
        update_option(self::SEEN, array_slice($seen, -50, 50, true), false);
    }

    private static function draft_exists(int $id): bool {
        if ($id <= 0) {
            return false;
        }
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}sgap_ai_drafts WHERE id = %d AND status = %s",
            $id,
            'pending'
        ));
    }

    /** The module's own log: time, action and result, one row per action. */
    public static function log(string $action, string $result): void {
        if ((string) get_option('sgap_cirla_log_level', 'actions') === 'off') {
            return;
        }
        $entries = self::entries(20);
        array_unshift($entries, ['at' => current_time('mysql'), 'action' => $action, 'result' => $result]);
        update_option(self::LOG, array_slice($entries, 0, 20), false);
    }

    /** @return array<int, array{at: string, action: string, result: string}> */
    public static function entries(int $limit = 20): array {
        $entries = get_option(self::LOG);
        return is_array($entries) ? array_slice($entries, 0, max(1, $limit)) : [];
    }

    /** A failure is both in the module log and in the plugin's error log, so it is not missed. */
    private static function fail(string $action, string $code, string $message): void {
        self::log($action, $message);
        if (class_exists('SGAP_Diagnostics')) {
            SGAP_Diagnostics::log('cirla', ['code' => $code, 'message' => $message], ['action' => $action]);
        }
    }
}
