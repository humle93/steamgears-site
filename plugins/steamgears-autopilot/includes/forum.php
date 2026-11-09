<?php
/**
 * Forum — P5.
 *
 * Why it exists: the shop had nowhere to answer a question in public. A forum is not
 * traffic by itself; it is the page Reddit, search and AI answers can point at. This
 * module builds the smallest forum that can carry that job without becoming a spam
 * magnet:
 *
 *   - **Threads are a post type** (`sgap_topic`), **replies are ordinary comments**.
 *     Native comments bring moderation, the spam filter, threading and the `comment`
 *     array that `DiscussionForumPosting` needs — no second table, no second UI.
 *   - **Boards are a taxonomy** (`sgap_board`), so a board can be pointed at a subreddit
 *     with term meta and the mapping never needs a code change.
 *   - **Only members can write.** Reading is public; writing needs a login, a nonce, a
 *     filled-in honeypot check that must stay empty, and at most three threads an hour.
 *     Anything posted by someone without `edit_others_posts` lands as `pending`, so a
 *     burst of spam costs a click to reject instead of a page to clean.
 *   - **The thread page sells without shouting.** It shows the board, who wrote it and
 *     how many answered, a "Discuss on Reddit" link when the thread was shared, and up
 *     to three product links through `/go/<id>/` so a visitor from Reddit lands on
 *     something buyable instead of a dead end.
 *   - **`DiscussionForumPosting` is printed only on a thread**, with the author, the
 *     dates, the comment count and the ten oldest approved comments. It is data, not a
 *     claim: every field comes from what WordPress actually stored.
 *
 * What it deliberately does not do: guest posting, voting, badges, or posting anything
 * to Reddit on its own — that is SGAP_Reddit's job, and it only ever posts links.
 *
 * Loaded from steamgears-autopilot.php.
 */
if (!defined('ABSPATH')) exit;

class SGAP_Forum {
    const TOPIC = 'sgap_topic';
    const BOARD = 'sgap_board';
    const NONCE = 'sgap_forum_topic';
    const SHARE_NONCE = 'sgap_forum_share';

    /** Post meta written by SGAP_Reddit; read here so one status is shown everywhere. */
    const META_REDDIT = '_sgap_reddit_url';
    const META_SUB = '_sgap_reddit_sub';
    const META_STATE = '_sgap_reddit_state';

    /** Threads one member may start per hour. */
    const RATE = 3;

    /** Shortest body, in words, that is a thread rather than a shout. */
    const MIN_WORDS = 40;

    /** Seeded once on activation; the keys are the slugs and the values the labels. */
    const BOARDS = [
        'cosplay' => 'Costume & Cosplay',
        'workshop' => 'Steampunk Workshop',
        'buying' => 'Buying advice',
        'builds' => 'Show your build'
    ];

    public static function init(): void {
        add_action('init', [__CLASS__, 'register'], 6);
        add_shortcode('steamgears_forum', [__CLASS__, 'shortcode']);
        add_action('admin_post_sgap_forum_topic', [__CLASS__, 'handle_topic']);
        add_action('admin_post_nopriv_sgap_forum_topic', [__CLASS__, 'handle_topic']);
        add_action('admin_post_sgap_forum_share', [__CLASS__, 'handle_share']);
        add_filter('the_content', [__CLASS__, 'thread_header'], 9);
        add_filter('comments_open', [__CLASS__, 'comments_open'], 10, 2);
        add_action('wp_head', [__CLASS__, 'jsonld'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('admin_menu', [__CLASS__, 'menu'], 21);
        add_action('save_post_' . self::TOPIC, [__CLASS__, 'on_publish'], 20, 3);
    }

    /* ------------------------------------------------------------------ *
     * Registration
     * ------------------------------------------------------------------ */

    public static function slug(): string {
        $slug = sanitize_title((string) SGAP_Settings::get('forum_slug', 'forum'));
        return $slug !== '' ? $slug : 'forum';
    }

    public static function register(): void {
        $slug = self::slug();
        register_post_type(self::TOPIC, [
            'labels' => [
                'name' => 'Forum threads',
                'singular_name' => 'Forum thread',
                'add_new_item' => 'Start a thread',
                'edit_item' => 'Edit thread',
                'all_items' => 'All threads'
            ],
            'public' => true,
            'has_archive' => $slug,
            'rewrite' => ['slug' => $slug, 'with_front' => false],
            'menu_icon' => 'dashicons-format-chat',
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'comments', 'author'],
            'taxonomies' => [self::BOARD],
            'show_in_rest' => true
        ]);
        register_taxonomy(self::BOARD, [self::TOPIC], [
            'labels' => [
                'name' => 'Boards',
                'singular_name' => 'Board',
                'all_items' => 'All boards'
            ],
            'public' => true,
            'hierarchical' => false,
            'rewrite' => ['slug' => $slug . '/board'],
            'show_admin_column' => true,
            'show_in_rest' => true
        ]);
        // Seeded once, on the first request that has the taxonomy registered: an
        // activation hook runs before init, where the taxonomy does not exist yet.
        if (get_option('sgap_forum_seeded') !== '2') {
            self::seed();
            update_option('sgap_forum_seeded', '2', false);
        }
    }

    /** Creates the four boards on activation; idempotent, so an update does not duplicate them. */
    public static function seed(): void {
        foreach (self::BOARDS as $slug => $label) {
            if (!term_exists($slug, self::BOARD)) {
                wp_insert_term($label, self::BOARD, ['slug' => $slug]);
            }
        }
    }

    public static function index_url(): string {
        $url = get_post_type_archive_link(self::TOPIC);
        return $url !== false && $url !== '' ? (string) $url : home_url('/' . self::slug() . '/');
    }

    /** The board term a thread sits in, or an empty string. */
    public static function board_of(int $post_id): string {
        $terms = wp_get_post_terms($post_id, self::BOARD);
        if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
            return '';
        }
        return (string) ($terms[0]->slug ?? '');
    }

    public static function board_label(string $slug): string {
        return self::BOARDS[$slug] ?? $slug;
    }

    /** The subreddit mapped to a thread's board, else the site-wide default. */
    public static function subreddit_for(int $post_id): string {
        $board = self::board_of($post_id);
        if ($board !== '') {
            $terms = wp_get_post_terms($post_id, self::BOARD);
            if (!is_wp_error($terms) && $terms !== []) {
                $mapped = sanitize_key((string) get_term_meta((int) $terms[0]->term_id, 'reddit_sub', true));
                if ($mapped !== '') {
                    return $mapped;
                }
            }
        }
        return sanitize_key((string) SGAP_Settings::get('reddit_default_sub', 'steampunk'));
    }

    /* ------------------------------------------------------------------ *
     * Frontend
     * ------------------------------------------------------------------ */

    public static function assets(): void {
        if (!SGAP_Settings::enabled('forum_enabled')) {
            return;
        }
        $post = get_post();
        $needed = is_singular(self::TOPIC)
            || (is_singular() && is_object($post) && has_shortcode((string) $post->post_content, 'steamgears_forum'));
        if ($needed) {
            wp_enqueue_style('sgap-forum', plugins_url('assets/forum.css', SGAP_FILE), [], SGAP_VERSION);
        }
    }

    /** `[steamgears_forum board="workshop" limit="20"]` — the index, or one thread inside a page. */
    public static function shortcode($atts = [], $content = ''): string {
        if (!SGAP_Settings::enabled('forum_enabled')) {
            return '<p class="sgf-notice">The forum is closed right now.</p>';
        }
        $atts = shortcode_atts(['board' => '', 'limit' => 20], (array) $atts, 'steamgears_forum');
        if (is_singular(self::TOPIC)) {
            return self::thread_box((int) get_queried_object_id());
        }
        return self::index((string) $atts['board'], max(1, min(50, (int) $atts['limit'])));
    }

    private static function notice(): string {
        $errors = [
            'login' => 'Sign in to start a thread.',
            'spam' => 'That submission looked automated and was not saved.',
            'rate' => 'You have started three threads this hour. Try again later.',
            'title' => 'Give the thread a title between 8 and 140 characters.',
            'short' => 'Write at least ' . self::MIN_WORDS . ' words, so others can answer something.',
            'board' => 'Choose one of the boards.',
            'failed' => 'The thread could not be saved. Try again.'
        ];
        $key = isset($_GET['forum_error']) ? sanitize_key(wp_unslash($_GET['forum_error'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ($key !== '' && isset($errors[$key])) {
            return '<p class="sgf-notice sgf-notice-error">' . esc_html($errors[$key]) . '</p>';
        }
        $done = isset($_GET['forum']) ? sanitize_key(wp_unslash($_GET['forum'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ($done === 'pending') {
            return '<p class="sgf-notice">Thanks — the thread is waiting for a moderator.</p>';
        }
        if ($done === 'published') {
            return '<p class="sgf-notice">The thread is live. Share it if it is worth an argument.</p>';
        }
        return '';
    }

    private static function index(string $board, int $limit): string {
        $out = self::notice();
        $out .= '<div class="sgf"><div class="sgf-head">';
        $out .= '<h2>Ask, show, argue</h2><p>Costumes, builds and the odd brass problem. Members start threads; answers are public.</p>';
        $out .= '</div>';
        $out .= self::board_chips($board);
        $out .= self::search_box();
        $out .= is_user_logged_in() ? self::form() : self::login_box();
        $out .= self::thread_list($board, $limit);
        return $out . '</div>';
    }

    private static function board_chips(string $current): string {
        $terms = get_terms(['taxonomy' => self::BOARD, 'hide_empty' => false]);
        if (is_wp_error($terms) || !is_array($terms) || $terms === []) {
            return '';
        }
        $out = '<nav class="sgf-boards"><a class="' . ($current === '' ? 'is-active' : '') . '" href="' . esc_url(self::index_url()) . '">All</a>';
        foreach ($terms as $term) {
            $slug = (string) ($term->slug ?? '');
            $out .= '<a class="' . ($slug === $current ? 'is-active' : '') . '" href="'
                . esc_url(add_query_arg('board', $slug, self::index_url())) . '">'
                . esc_html((string) ($term->name ?? self::board_label($slug)))
                . ' <span>' . (int) ($term->count ?? 0) . '</span></a>';
        }
        return $out . '</nav>';
    }

    private static function search_box(): string {
        return '<form class="sgf-search" method="get" action="' . esc_url(self::index_url()) . '">'
            . '<label class="screen-reader-text" for="sgf-q">Search the forum</label>'
            . '<input id="sgf-q" type="search" name="forum_q" placeholder="Search threads" value="'
            . esc_attr(isset($_GET['forum_q']) ? sanitize_text_field(wp_unslash($_GET['forum_q'])) : '') . '">' // phpcs:ignore WordPress.Security.NonceVerification
            . '<button type="submit">Search</button></form>';
    }

    private static function login_box(): string {
        return '<div class="sgf-card sgf-login"><h3>Join the workshop</h3>'
            . '<p>Accounts keep the spam out. Sign in, or create one, and the thread form opens.</p>'
            . '<p><a class="sgf-button" href="' . esc_url(wp_login_url(self::index_url())) . '">Sign in</a> '
            . '<a href="' . esc_url(wp_registration_url()) . '">Create an account</a></p></div>';
    }

    private static function form(): string {
        $options = '';
        foreach (self::BOARDS as $slug => $label) {
            $options .= '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        }
        return '<div class="sgf-card"><h3>Start a thread</h3>'
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="sgap_forum_topic">'
            . wp_nonce_field(self::NONCE, '_wpnonce', true, false)
            . '<p class="sgf-hp"><label>Website <input type="text" name="sgap_website" value="" tabindex="-1" autocomplete="off"></label></p>'
            . '<p><label for="sgf-title">Title</label><input id="sgf-title" name="sgap_title" maxlength="140" required></p>'
            . '<p><label for="sgf-board">Board</label><select id="sgf-board" name="sgap_board">' . $options . '</select></p>'
            . '<p><label for="sgf-body">Your post</label><textarea id="sgf-body" name="sgap_body" rows="8" required></textarea></p>'
            . '<p><button class="sgf-button" type="submit">Post thread</button></p>'
            . '</form></div>';
    }

    private static function thread_list(string $board, int $limit): string {
        $args = [
            'post_type' => self::TOPIC,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'paged' => max(1, (int) get_query_var('paged'))
        ];
        if ($board !== '' && isset(self::BOARDS[$board])) {
            $args['tax_query'] = [['taxonomy' => self::BOARD, 'field' => 'slug', 'terms' => $board]];
        }
        $search = isset($_GET['forum_q']) ? sanitize_text_field(wp_unslash($_GET['forum_q'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        if ($search !== '') {
            $args['s'] = $search;
        }
        $query = new WP_Query($args);
        $rows = is_object($query) && isset($query->posts) && is_array($query->posts) ? $query->posts : [];
        if ($rows === []) {
            return '<p class="sgf-empty">No threads here yet. The first one sets the tone.</p>';
        }
        $out = '<ul class="sgf-threads">';
        foreach ($rows as $topic) {
            $id = (int) ($topic->ID ?? 0);
            $board_slug = self::board_of($id);
            $out .= '<li><a class="sgf-thread-title" href="' . esc_url(get_permalink($id)) . '">'
                . esc_html(get_the_title($id)) . '</a>'
                . '<span class="sgf-meta">' . esc_html(self::board_label($board_slug)) . ' · '
                . (int) get_comments_number($id) . ' replies · ' . esc_html(get_the_date('j M Y', $id))
                . self::reddit_badge($id) . '</span></li>';
        }
        return $out . '</ul>';
    }

    private static function reddit_badge(int $id): string {
        $state = (string) get_post_meta($id, self::META_STATE, true);
        $labels = ['sent' => 'On Reddit', 'queued' => 'Queued for Reddit', 'failed' => 'Reddit failed'];
        if (!isset($labels[$state])) {
            return '';
        }
        return ' <span class="sgf-badge sgf-badge-' . esc_attr($state) . '">' . esc_html($labels[$state]) . '</span>';
    }

    /** The box above the thread body: who, where, how many, and the Reddit link. */
    public static function thread_box(int $id): string {
        $board = self::board_of($id);
        $out = '<div class="sgf sgf-thread-head"><p class="sgf-meta">'
            . '<a href="' . esc_url(self::index_url()) . '">Forum</a> / ' . esc_html(self::board_label($board))
            . ' · ' . (int) get_comments_number($id) . ' replies · started ' . esc_html(get_the_date('j M Y', $id))
            . '</p>';
        $reddit = (string) get_post_meta($id, self::META_REDDIT, true);
        if ($reddit !== '') {
            $out .= '<p class="sgf-cta"><a class="sgf-button" rel="nofollow noopener" href="' . esc_url($reddit)
                . '">Discuss this on Reddit</a> <span class="sgf-meta">r/'
                . esc_html((string) get_post_meta($id, self::META_SUB, true)) . '</span></p>';
        }
        return $out . '</div>';
    }

    /** Prepends the thread box and appends product tips; nothing else is touched. */
    public static function thread_header($content) {
        if (!is_singular(self::TOPIC)) {
            return $content;
        }
        $id = (int) get_the_ID();
        return self::thread_box($id) . $content . self::product_tips($id);
    }

    /**
     * Three product short links. A visitor from Reddit should be one click from something
     * buyable; `/go/<id>/` also counts the click, so the thread's value is measurable.
     */
    public static function product_tips(int $id): string {
        if (!function_exists('wc_get_products')) {
            return '';
        }
        $products = wc_get_products(['limit' => 3, 'status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
        if (!is_array($products) || $products === []) {
            return '';
        }
        $out = '<div class="sgf-card sgf-tips"><h3>If the thread made you curious</h3><ul>';
        foreach ($products as $product) {
            if (!is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }
            $out .= '<li><a rel="nofollow" href="' . esc_url(home_url('/go/' . (int) $product->get_id() . '/')) . '">'
                . esc_html(method_exists($product, 'get_name') ? (string) $product->get_name() : 'Product') . '</a></li>';
        }
        return $out . '</ul><p class="sgf-meta">Links are tracked; the price and the product page are the shop\'s.</p></div>';
    }

    /** Threads need replies even when the theme has comments switched off elsewhere. */
    public static function comments_open($open, $post_id) {
        return get_post_type((int) $post_id) === self::TOPIC ? true : $open;
    }

    /**
     * `DiscussionForumPosting` for one thread. Only fields WordPress can answer are printed;
     * an empty comment list stays empty rather than claiming a discussion that is not there.
     */
    public static function jsonld(): void {
        if (!is_singular(self::TOPIC)) {
            return;
        }
        $id = (int) get_queried_object_id();
        if ($id <= 0) {
            return;
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'DiscussionForumPosting',
            'headline' => get_the_title($id),
            'url' => get_permalink($id),
            'datePublished' => get_the_date('c', $id),
            'dateModified' => get_the_modified_date('c', $id),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', (int) get_post_field('post_author', $id))
            ],
            'interactionStatistic' => [[
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/CommentAction',
                'userInteractionCount' => (int) get_comments_number($id)
            ]]
        ];
        $board = self::board_of($id);
        if ($board !== '') {
            $data['about'] = self::board_label($board);
        }
        $comments = get_comments(['post_id' => $id, 'status' => 'approve', 'number' => 10, 'order' => 'ASC']);
        foreach ((array) $comments as $comment) {
            $data['comment'][] = [
                '@type' => 'Comment',
                'text' => wp_strip_all_tags((string) ($comment->comment_content ?? '')),
                'dateCreated' => mysql2date('c', (string) ($comment->comment_date ?? '')),
                'author' => ['@type' => 'Person', 'name' => (string) ($comment->comment_author ?? '')]
            ];
        }
        printf('<script type="application/ld+json">%s</script>' . "\n", wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /* ------------------------------------------------------------------ *
     * Writing
     * ------------------------------------------------------------------ */

    private static function back(string $error = '', int $post_id = 0): void {
        $url = $post_id > 0 ? (string) get_permalink($post_id) : self::index_url();
        if ($error !== '') {
            $url = add_query_arg('forum_error', $error, self::index_url());
        }
        wp_safe_redirect($url);
    }

    private static function throttled(int $user_id): bool {
        $key = 'sgap_forum_rate_' . $user_id;
        $count = (int) get_transient($key);
        if ($count >= self::RATE) {
            return true;
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return false;
    }

    public static function handle_topic(): void {
        if (!is_user_logged_in()) {
            self::back('login');
            return;
        }
        check_admin_referer(self::NONCE);
        // The honeypot is invisible to people and irresistible to bots.
        if (trim((string) ($_POST['sgap_website'] ?? '')) !== '') {
            self::back('spam');
            return;
        }
        $title = sanitize_text_field(wp_unslash($_POST['sgap_title'] ?? ''));
        $body = wp_kses_post(wp_unslash($_POST['sgap_body'] ?? ''));
        $board = sanitize_key(wp_unslash($_POST['sgap_board'] ?? ''));
        $user_id = get_current_user_id();
        if (mb_strlen($title) < 8 || mb_strlen($title) > 140) {
            self::back('title');
            return;
        }
        if (!isset(self::BOARDS[$board])) {
            self::back('board');
            return;
        }
        if (str_word_count(wp_strip_all_tags($body)) < self::MIN_WORDS) {
            self::back('short');
            return;
        }
        if (self::throttled($user_id)) {
            self::back('rate');
            return;
        }
        $status = current_user_can('edit_others_posts') ? 'publish' : 'pending';
        $id = wp_insert_post([
            'post_type' => self::TOPIC,
            'post_status' => $status,
            'post_title' => $title,
            'post_content' => $body,
            'post_author' => $user_id
        ], true);
        if (is_wp_error($id) || !$id) {
            self::back('failed');
            return;
        }
        wp_set_object_terms((int) $id, $board, self::BOARD);
        update_post_meta((int) $id, self::META_STATE, 'none');
        if ($status === 'publish') {
            self::on_publish((int) $id, get_post((int) $id), false);
        }
        self::back('', (int) $id);
    }

    /** A published thread may go to Reddit when the owner has asked for it. */
    public static function on_publish(int $post_id, $post = null, $update = false): void {
        if (!class_exists('SGAP_Reddit')) {
            return;
        }
        SGAP_Reddit::maybe_queue($post_id, $post, (bool) $update);
    }

    /* ------------------------------------------------------------------ *
     * Admin
     * ------------------------------------------------------------------ */

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'Forum & Reddit', 'Forum & Reddit', 'manage_options', 'sgap-forum', [__CLASS__, 'page'], 21);
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to see this page.');
        }
        global $wpdb;
        $topics = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", self::TOPIC));
        $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'pending'", self::TOPIC));
        $replies = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1' AND comment_post_ID IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = %s)", self::TOPIC));
        $status = class_exists('SGAP_Reddit') ? SGAP_Reddit::status() : ['state' => 'off', 'label' => 'Reddit is not loaded', 'detail' => ''];
        $settings = admin_url('admin.php?page=sgap-settings');
        $shared = class_exists('SGAP_Reddit') ? SGAP_Reddit::recent(10) : [];
        $radar = class_exists('SGAP_Reddit') ? SGAP_Reddit::radar_cached() : [];

        echo '<div class="wrap sgap-wrap">';
        echo '<h1>Forum &amp; Reddit</h1>';
        echo '<p class="description">Threads give the shop pages search and AI answers can cite. Reddit gives them readers. Everything below is measured, not estimated: the counters come from WordPress, the queue from the plugin\'s own table.</p>';

        echo '<div class="sgap-grid">';
        foreach ([
            ['Published threads', $topics],
            ['Waiting for moderation', $pending],
            ['Approved replies', $replies],
            ['Reddit state', $status['label']]
        ] as [$label, $value]) {
            echo '<div class="sgap-card"><h3>' . esc_html((string) $label) . '</h3><p class="sgap-metric">' . esc_html((string) $value) . '</p></div>';
        }
        echo '</div>';

        echo '<div class="sgap-card"><h2>Reddit</h2><p><strong>' . esc_html($status['label']) . '</strong></p><p>'
            . esc_html($status['detail']) . '</p><p><a class="button" href="' . esc_url($settings . '#tab-forum') . '">Open the settings</a> '
            . '<a class="button" href="' . esc_url(admin_url('admin-post.php?action=sgap_reddit_radar&_wpnonce=' . wp_create_nonce('sgap_reddit_radar'))) . '">Refresh the radar</a></p></div>';

        echo '<div class="sgap-card"><h2>Threads</h2>';
        $query = new WP_Query(['post_type' => self::TOPIC, 'post_status' => ['publish', 'pending'], 'posts_per_page' => 20]);
        $rows = is_object($query) && isset($query->posts) && is_array($query->posts) ? $query->posts : [];
        if ($rows === []) {
            echo '<p>No threads yet. The first one is the expensive one; after that the board has a tone.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Thread</th><th>Status</th><th>Reddit</th><th></th></tr></thead><tbody>';
            foreach ($rows as $topic) {
                $id = (int) ($topic->ID ?? 0);
                $state = (string) get_post_meta($id, self::META_STATE, true);
                $reddit = (string) get_post_meta($id, self::META_REDDIT, true);
                echo '<tr><td><a href="' . esc_url(get_permalink($id)) . '">' . esc_html(get_the_title($id)) . '</a>'
                    . '<br><span class="description">' . esc_html(self::board_label(self::board_of($id))) . ' · '
                    . (int) get_comments_number($id) . ' replies</span></td>';
                echo '<td>' . esc_html((string) ($topic->post_status ?? '')) . '</td>';
                echo '<td>' . ($reddit !== '' ? '<a href="' . esc_url($reddit) . '" rel="nofollow noopener">' . esc_html($reddit) . '</a>' : esc_html($state !== '' ? $state : 'not shared')) . '</td>';
                echo '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                    . '<input type="hidden" name="action" value="sgap_forum_share"><input type="hidden" name="topic_id" value="' . $id . '">'
                    . wp_nonce_field(self::SHARE_NONCE . '_' . $id, '_wpnonce', true, false)
                    . '<input type="text" name="subreddit" value="' . esc_attr(self::subreddit_for($id)) . '" size="14"> '
                    . '<button class="button" type="submit"' . ($reddit !== '' ? ' disabled' : '') . '>Queue on Reddit</button></form></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div class="sgap-card"><h2>Radar — threads worth answering</h2>';
        echo '<p class="description">Read-only. The plugin never comments on Reddit: an automated sales reply is spam and costs the account. Answer as yourself, with the thread you actually have something to say about.</p>';
        if (!is_array($radar) || $radar === []) {
            echo '<p>Nothing read yet. Save the Reddit app id and secret, then refresh the radar.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Thread</th><th>Subreddit</th><th>Score</th><th>Comments</th></tr></thead><tbody>';
            foreach ($radar as $item) {
                echo '<tr><td><a href="' . esc_url((string) ($item['url'] ?? '')) . '" rel="nofollow noopener" target="_blank">'
                    . esc_html((string) ($item['title'] ?? '')) . '</a></td><td>r/' . esc_html((string) ($item['subreddit'] ?? ''))
                    . '</td><td>' . (int) ($item['score'] ?? 0) . '</td><td>' . (int) ($item['comments'] ?? 0) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        echo '<div class="sgap-card"><h2>Queue</h2>';
        if (!is_array($shared) || $shared === []) {
            echo '<p>Nothing has been queued for Reddit yet.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>Thread</th><th>Subreddit</th><th>Status</th><th>Attempts</th><th>Error</th></tr></thead><tbody>';
            foreach ($shared as $row) {
                echo '<tr><td>' . esc_html((string) ($row['title'] ?? '')) . '</td><td>r/' . esc_html((string) ($row['subreddit'] ?? ''))
                    . '</td><td>' . esc_html((string) ($row['status'] ?? '')) . '</td><td>' . (int) ($row['attempts'] ?? 0)
                    . '</td><td>' . esc_html(mb_substr((string) ($row['error'] ?? ''), 0, 120)) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div></div>';
    }

    public static function handle_share(): void {
        if (!current_user_can('edit_others_posts')) {
            wp_die('You are not allowed to share threads.');
        }
        $topic_id = (int) ($_POST['topic_id'] ?? 0);
        check_admin_referer(self::SHARE_NONCE . '_' . $topic_id);
        $subreddit = sanitize_key(wp_unslash($_POST['subreddit'] ?? ''));
        $result = SGAP_Reddit::queue($topic_id, $subreddit);
        wp_safe_redirect(add_query_arg(
            'forum_notice',
            is_wp_error($result) ? 'error' : 'queued',
            admin_url('admin.php?page=sgap-forum')
        ));
    }

    /* ------------------------------------------------------------------ *
     * REST
     * ------------------------------------------------------------------ */

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/forum/topics', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_topics'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route(SGAP_REST::NS, '/forum/share', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_share'],
            'permission_callback' => fn() => current_user_can('edit_others_posts')
        ]);
    }

    public static function rest_topics(): array {
        $query = new WP_Query(['post_type' => self::TOPIC, 'post_status' => 'publish', 'posts_per_page' => 10]);
        $rows = is_object($query) && isset($query->posts) && is_array($query->posts) ? $query->posts : [];
        $out = [];
        foreach ($rows as $topic) {
            $id = (int) ($topic->ID ?? 0);
            $out[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'url' => get_permalink($id),
                'board' => self::board_label(self::board_of($id)),
                'replies' => (int) get_comments_number($id),
                'reddit' => (string) get_post_meta($id, self::META_REDDIT, true)
            ];
        }
        return ['topics' => $out];
    }

    /** @return array|WP_Error */
    public static function rest_share($request) {
        $topic_id = (int) (is_object($request) && method_exists($request, 'get_param') ? $request->get_param('topic_id') : 0);
        $subreddit = (string) (is_object($request) && method_exists($request, 'get_param') ? $request->get_param('subreddit') : '');
        $result = SGAP_Reddit::queue($topic_id, sanitize_key($subreddit));
        if (is_wp_error($result)) {
            return $result;
        }
        return ['queued' => $result];
    }
}
