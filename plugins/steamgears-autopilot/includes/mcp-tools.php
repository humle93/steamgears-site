<?php
/**
 * The MCP tool catalogue: every WordPress action the autopilot has needed in
 * practice (options, posts, media, plugins, cron, caches, IndexNow, sitemaps)
 * plus a bridge that exposes *every* ability registered on the site — core,
 * WooCommerce, Rank Math, AI Engine, other plugins — as tools.
 *
 * Everything here is registered as a WordPress ability (WordPress 6.9+), which
 * is what the site's MCP server turns into tools. Two rules hold for all of it:
 *
 *   1. Administrator only (`manage_options`, filterable).
 *   2. Secrets stay secrets: option names that look like credentials can never
 *      be read, written or listed, and writes go through an allowlist, a dry run
 *      and an undo token.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_MCP_Tools {
    const CATEGORY = 'steamgears-tools';
    const UNDO_OPTION = 'sgap_mcp_undo';
    const UNDO_TTL = 259200; // 72 hours

    /** Option names that look like credentials. Never readable, never writable. */
    const SECRET_PATTERN = '/(_key$|_keys$|_secret|_token|_pass|password|api_?key|license|licence|_auth|authorization|royal_mcp|private)/i';

    /** Options a tool is allowed to write. Everything else is refused. */
    const WRITABLE = [
        'blogname', 'blogdescription', 'permalink_structure', 'timezone_string', 'gmt_offset',
        'date_format', 'time_format', 'start_of_week', 'posts_per_page', 'default_category',
        'show_on_front', 'page_on_front', 'page_for_posts',
        'default_ping_status', 'default_comment_status',
        'woocommerce_currency', 'woocommerce_price_num_decimals', 'woocommerce_weight_unit',
        'woocommerce_dimension_unit', 'woocommerce_default_country', 'woocommerce_calc_taxes',
        'woocommerce_enable_reviews', 'woocommerce_shop_page_id', 'woocommerce_cart_page_id',
        'woocommerce_checkout_page_id', 'woocommerce_myaccount_page_id', 'woocommerce_terms_page_id',
    ];

    /** Post meta readable/writable through the post tools. Nothing else is touched. */
    const POST_META = [
        'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword',
        'rank_math_robots', 'rank_math_canonical_url', 'rank_math_facebook_title',
        'rank_math_facebook_description', 'rank_math_twitter_title', 'rank_math_twitter_description',
        '_sku', '_price', '_regular_price', '_sale_price', '_stock', '_stock_status',
        '_thumbnail_id', '_yoast_wpseo_title', '_yoast_wpseo_metadesc'
    ];

    public static function init(): void {
        add_action('wp_abilities_api_categories_init', [__CLASS__, 'register_category']);
        add_action('wp_abilities_api_init', [__CLASS__, 'register']);
    }

    public static function register_category(): void {
        if (function_exists('wp_register_ability_category')) {
            wp_register_ability_category(self::CATEGORY, [
                'label' => 'Steamgears tools',
                'description' => 'General WordPress and WooCommerce tools for MCP clients: options, posts, media, plugins, cron, caches, IndexNow, sitemaps, and a bridge that runs any registered ability.'
            ]);
        }
    }

    public static function can_manage(): bool {
        $cap = apply_filters('sgap_mcp_tool_capability', 'manage_options');
        return current_user_can($cap);
    }

    /* ------------------------------------------------------------------ *
     * Registry
     * ------------------------------------------------------------------ */

    /**
     * name => [label, description, properties, required, execute, readonly, idempotent, destructive]
     */
    private static function definitions(): array {
        $post_type = ['type' => 'string', 'description' => 'Post type, e.g. post, page, product, attachment.'];
        $post_status = ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private', 'future', 'any', 'trash']];
        return [
            /* ---- site ---- */
            'site-info' => [
                'Site info',
                'WordPress, PHP, theme and permalink setup, the active plugin list with versions, SEO/shop plugin versions, and the sitemap URLs.',
                ['include_plugins' => ['type' => 'boolean', 'default' => true]],
                [], [__CLASS__, 'site_info'], true, true
            ],
            'get-option' => [
                'Read an option',
                'Reads one WordPress option. Refused for anything that looks like a credential (keys, tokens, passwords, licences).',
                ['name' => ['type' => 'string', 'minLength' => 1]],
                ['name'], [__CLASS__, 'get_option_tool'], true, true
            ],
            'update-option' => [
                'Write an option',
                'Changes one option from a fixed allowlist (site title, tagline, permalinks, timezone, reading and WooCommerce page settings). Dry run by default: nothing is written until dry_run is false and confirm is true. Returns an undo token valid for 72 hours.',
                [
                    'name' => ['type' => 'string', 'minLength' => 1],
                    'value' => ['description' => 'New value (string, number, boolean or null).'],
                    'dry_run' => ['type' => 'boolean', 'default' => true],
                    'confirm' => ['type' => 'boolean', 'default' => false]
                ],
                ['name', 'value'], [__CLASS__, 'update_option_tool'], false, true
            ],
            'undo' => [
                'Undo an option change',
                'Puts back the previous value of an option change made through update-option, using the undo token that call returned. Tokens expire after 72 hours.',
                ['token' => ['type' => 'string', 'minLength' => 8]],
                ['token'], [__CLASS__, 'undo'], false, true
            ],
            'list-plugins' => [
                'List plugins',
                'Every installed plugin with file, name, version, whether it is active and whether an update is available.',
                [], [], [__CLASS__, 'list_plugins'], true, true
            ],
            'set-plugin-state' => [
                'Activate or deactivate a plugin',
                'Activates or deactivates one installed plugin by its file path (e.g. "rank-math/rank-math.php"). Deactivation is how a broken plugin gets out of the way; nothing is ever deleted.',
                [
                    'plugin' => ['type' => 'string', 'minLength' => 3],
                    'state' => ['type' => 'string', 'enum' => ['activate', 'deactivate']]
                ],
                ['plugin', 'state'], [__CLASS__, 'set_plugin_state'], false, false
            ],
            'list-themes' => [
                'List themes',
                'Installed themes with name, version and which one is active.',
                [], [], [__CLASS__, 'list_themes'], true, true
            ],
            'flush-rewrites' => [
                'Flush rewrite rules',
                'Rebuilds the permalink rules. Needed when a new route (sitemap, key file, feed) answers 404 until WordPress regenerates its rules.',
                [], [], [__CLASS__, 'flush_rewrites'], false, true
            ],
            'flush-caches' => [
                'Flush caches',
                'Flushes the object cache, the Rank Math sitemap cache and known page-cache plugins (LiteSpeed, WP Rocket, W3TC, Autoptimize) so fresh content is visible immediately.',
                [], [], [__CLASS__, 'flush_caches'], false, true
            ],
            'list-cron' => [
                'List scheduled events',
                'Scheduled cron events with hook, next run and recurrence — useful to see whether publishing or cleanup jobs are actually scheduled.',
                ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50]],
                [], [__CLASS__, 'list_cron'], true, true
            ],
            'run-cron' => [
                'Run a cron event now',
                'Runs one scheduled hook immediately instead of waiting for the next visit.',
                ['hook' => ['type' => 'string', 'minLength' => 1]],
                ['hook'], [__CLASS__, 'run_cron'], false, false
            ],

            /* ---- content ---- */
            'list-posts' => [
                'List posts',
                'Lists content of any post type with id, title, status, date, URL and (for products) price and stock.',
                [
                    'post_type' => $post_type + ['default' => 'post'],
                    'status' => $post_status + ['default' => 'any'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0]
                ],
                [], [__CLASS__, 'list_posts'], true, true
            ],
            'get-post' => [
                'Read a post',
                'Full content of one post plus its SEO meta and the product fields that exist (SKU, price, stock).',
                ['id' => ['type' => 'integer', 'minimum' => 1]],
                ['id'], [__CLASS__, 'get_post_tool'], true, true
            ],
            'create-post' => [
                'Create a post',
                'Creates a post, page or product with title, content and status, plus SEO meta. Draft by default so nothing goes live by accident.',
                [
                    'title' => ['type' => 'string', 'minLength' => 1],
                    'content' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'pending', 'publish', 'private'], 'default' => 'draft'],
                    'post_type' => $post_type + ['default' => 'post'],
                    'excerpt' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'seo_title' => ['type' => 'string'],
                    'seo_description' => ['type' => 'string'],
                    'focus_keyword' => ['type' => 'string'],
                    'featured_image_id' => ['type' => 'integer', 'minimum' => 1]
                ],
                ['title'], [__CLASS__, 'create_post'], false, false
            ],
            'update-post' => [
                'Update a post',
                'Changes title, content, excerpt, slug, status, SEO meta or featured image of one post. Only the fields you send are touched.',
                [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                    'excerpt' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'pending', 'publish', 'private']],
                    'seo_title' => ['type' => 'string'],
                    'seo_description' => ['type' => 'string'],
                    'focus_keyword' => ['type' => 'string'],
                    'featured_image_id' => ['type' => 'integer', 'minimum' => 1]
                ],
                ['id'], [__CLASS__, 'update_post'], false, true
            ],
            'trash-post' => [
                'Move a post to the trash',
                'Moves one post to the trash. Nothing is permanently deleted unless force is true, and that is refused for anything but trash already in the trash.',
                [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'force' => ['type' => 'boolean', 'default' => false]
                ],
                ['id'], [__CLASS__, 'trash_post'], false, false, true
            ],

            /* ---- media ---- */
            'list-media' => [
                'List media',
                'Attachment library with id, filename, MIME type, URL and dimensions.',
                [
                    'mime' => ['type' => 'string', 'description' => 'Filter by MIME prefix, e.g. image.'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]
                ],
                [], [__CLASS__, 'list_media'], true, true
            ],
            'upload-media' => [
                'Upload media',
                'Adds an image or file to the media library from a URL or base64 content, optionally sets alt text, attaches it to a post and makes it that post\'s featured image.',
                [
                    'url' => ['type' => 'string', 'format' => 'uri'],
                    'content_base64' => ['type' => 'string'],
                    'filename' => ['type' => 'string'],
                    'alt' => ['type' => 'string'],
                    'attach_to' => ['type' => 'integer', 'minimum' => 1],
                    'set_featured' => ['type' => 'boolean', 'default' => false]
                ],
                [], [__CLASS__, 'upload_media'], false, false
            ],

            /* ---- shop ---- */
            'list-products' => [
                'List products',
                'WooCommerce products with id, name, status, price, stock status and stock quantity.',
                [
                    'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private', 'any'], 'default' => 'publish'],
                    'search' => ['type' => 'string'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
                ],
                [], [__CLASS__, 'list_products'], true, true
            ],
            'get-product' => [
                'Read a product',
                'One WooCommerce product: prices, stock, SKU, categories, tags and the first gallery images.',
                ['id' => ['type' => 'integer', 'minimum' => 1]],
                ['id'], [__CLASS__, 'get_product'], true, true
            ],
            'update-product' => [
                'Update a product',
                'Changes price, sale price, stock status, stock quantity, name, description or status of one product. Only the fields you send are touched.',
                [
                    'id' => ['type' => 'integer', 'minimum' => 1],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'regular_price' => ['type' => 'string'],
                    'sale_price' => ['type' => 'string'],
                    'stock_status' => ['type' => 'string', 'enum' => ['instock', 'outofstock', 'onbackorder']],
                    'stock_quantity' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'private']]
                ],
                ['id'], [__CLASS__, 'update_product'], false, true
            ],

            /* ---- indexing ---- */
            'indexnow-submit' => [
                'Submit URLs to IndexNow',
                'Pings IndexNow (Bing, Yandex, Seznam and others) with a list of URLs so they are crawled within minutes instead of waiting for the next crawl. Uses this plugin\'s own key file, which the tool reports the URL of.',
                ['urls' => ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uri'], 'minItems' => 1, 'maxItems' => 100]],
                ['urls'], [__CLASS__, 'indexnow_submit'], false, false
            ],
            'indexnow-status' => [
                'IndexNow status',
                'The key this plugin serves, the URL IndexNow fetches it from, whether that URL currently answers with the key, and the most recent entries in Rank Math\'s IndexNow log.',
                [], [], [__CLASS__, 'indexnow_status'], true, true
            ],
            'sitemap-audit' => [
                'Audit the sitemap',
                'Fetches sitemap_index.xml, follows the child sitemaps and reports how many URLs each one holds. With a URL argument it also says whether that URL is present and in which sitemap.',
                ['url' => ['type' => 'string', 'format' => 'uri']],
                [], [__CLASS__, 'sitemap_audit'], true, true
            ],

            /* ---- everything else ---- */
            'list-abilities' => [
                'List every ability on the site',
                'Lists every ability registered through the WordPress Abilities API — this plugin, WordPress core, WooCommerce, Rank Math, AI Engine and any other plugin — with name, label, description and input schema.',
                ['category' => ['type' => 'string', 'description' => 'Only abilities in this category.']],
                [], [__CLASS__, 'list_abilities'], true, true
            ],
            'call-ability' => [
                'Run any ability',
                'Runs any registered ability by name with the given input, after checking that ability\'s own permission callback. This is the escape hatch: anything exposed as an ability on this site is reachable through it.',
                [
                    'name' => ['type' => 'string', 'minLength' => 3],
                    'input' => ['type' => 'object', 'additionalProperties' => true]
                ],
                ['name'], [__CLASS__, 'call_ability'], false, false
            ],
        ];
    }

    public static function register(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        foreach (self::definitions() as $name => $def) {
            [$label, $description, $properties, $required, $execute, $readonly, $idempotent] = $def;
            $destructive = (bool) ($def[7] ?? false);
            $args = [
                'label' => $label,
                'description' => $description,
                'category' => self::CATEGORY,
                'output_schema' => ['type' => 'object'],
                'execute_callback' => static fn($input = null) => call_user_func($execute, $input),
                'permission_callback' => [__CLASS__, 'can_manage'],
                'meta' => class_exists('SGAP_Abilities')
                    ? SGAP_Abilities::meta($readonly, $idempotent, $destructive)
                    : ['show_in_rest' => true, 'public' => true, 'mcp' => ['public' => true, 'type' => 'tool']]
            ];
            if ($properties) {
                $args['input_schema'] = class_exists('SGAP_Abilities')
                    ? SGAP_Abilities::object($properties, $required)
                    : ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
            }
            wp_register_ability(self::CATEGORY . '/' . $name, $args);
        }
    }

    /* ------------------------------------------------------------------ *
     * Pure helpers (no WordPress state; unit-tested in plugins/tests)
     * ------------------------------------------------------------------ */

    /** True when an option name looks like a credential and must never be exposed. */
    public static function is_secret_option(string $name): bool {
        return (bool) preg_match(self::SECRET_PATTERN, $name);
    }

    /** True when update-option may write this option. */
    public static function is_writable_option(string $name): bool {
        if (self::is_secret_option($name)) {
            return false;
        }
        return in_array($name, apply_filters('sgap_mcp_writable_options', self::WRITABLE), true);
    }

    /** The IndexNow request body, so it can be asserted without sending anything. */
    public static function indexnow_payload(array $urls, string $key, string $key_location): array {
        $urls = array_values(array_filter(array_map('strval', $urls), static fn($u) => preg_match('#^https?://#i', $u) === 1));
        $payload = ['host' => (string) wp_parse_url(home_url(), PHP_URL_HOST), 'key' => $key, 'keyLocation' => $key_location];
        if ($urls) {
            $payload['urlList'] = array_slice($urls, 0, 100);
        }
        return $payload;
    }

    /** Every <loc> value in a sitemap document, in order. */
    public static function sitemap_locs(string $xml): array {
        if (!preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $xml, $m)) {
            return [];
        }
        return array_values(array_unique(array_map('html_entity_decode', $m[1])));
    }

    /** Normalised description of one ability, whether it is an object or an array. */
    public static function describe_ability($ability): array {
        $get = static function (string $method, $fallback = null) use ($ability) {
            return is_object($ability) && method_exists($ability, $method) ? $ability->{$method}() : $fallback;
        };
        $name = $get('get_name', is_array($ability) ? ($ability['name'] ?? '') : '');
        $meta = $get('get_meta', is_array($ability) ? ($ability['meta'] ?? []) : []);
        return [
            'name' => (string) $name,
            'label' => (string) $get('get_label', is_array($ability) ? ($ability['label'] ?? '') : ''),
            'description' => (string) $get('get_description', is_array($ability) ? ($ability['description'] ?? '') : ''),
            'category' => (string) $get('get_category', is_array($ability) ? ($ability['category'] ?? '') : ''),
            'input_schema' => $get('get_input_schema', is_array($ability) ? ($ability['input_schema'] ?? null) : null),
            'mcp' => is_array($meta) ? ($meta['mcp'] ?? null) : null
        ];
    }

    /** All registered abilities, from whichever registry this WordPress has. */
    public static function all_abilities(): array {
        if (function_exists('wp_get_abilities')) {
            $all = wp_get_abilities();
            return is_array($all) ? $all : [];
        }
        if (class_exists('WP_Abilities_Registry') && method_exists('WP_Abilities_Registry', 'get_instance')) {
            $registry = WP_Abilities_Registry::get_instance();
            if ($registry && method_exists($registry, 'get_all_registered')) {
                $all = $registry->get_all_registered();
                return is_array($all) ? $all : [];
            }
        }
        return [];
    }

    /* ------------------------------------------------------------------ *
     * Site
     * ------------------------------------------------------------------ */

    public static function site_info($input = null): array {
        $input = (array) $input;
        $info = [
            'home_url' => home_url(),
            'site_url' => site_url(),
            'name' => get_bloginfo('name'),
            'tagline' => get_bloginfo('description'),
            'wordpress' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'multisite' => is_multisite(),
            'theme' => wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'),
            'permalink_structure' => (string) get_option('permalink_structure'),
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : (string) get_option('timezone_string'),
            'front_page' => ['show_on_front' => get_option('show_on_front'), 'page_on_front' => (int) get_option('page_on_front')],
            'sitemaps' => [
                'index' => home_url('/sitemap_index.xml'),
                'products' => home_url('/product-sitemap.xml'),
                'pages' => home_url('/page-sitemap.xml'),
                'posts' => home_url('/post-sitemap.xml'),
                'robots' => home_url('/robots.txt')
            ],
            'versions' => [
                'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null,
                'rank_math' => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : null,
                'abilities_api' => function_exists('wp_register_ability') ? 'yes' : 'no'
            ],
            'counts' => [
                'abilities' => count(self::all_abilities()),
                'products' => post_type_exists('product') ? (int) wp_count_posts('product')->publish : null,
                'posts' => (int) wp_count_posts('post')->publish,
                'pages' => (int) wp_count_posts('page')->publish
            ]
        ];
        if (!empty($input['include_plugins'])) {
            $info['plugins'] = self::list_plugins()['plugins'];
        }
        return $info;
    }

    public static function get_option_tool($input = null) {
        $name = (string) (($input['name'] ?? '') ?: '');
        if ($name === '') {
            return new WP_Error('sgap_mcp_missing', 'name is required.');
        }
        if (self::is_secret_option($name)) {
            return new WP_Error('sgap_mcp_forbidden', 'That option name looks like a credential and is never exposed through MCP.');
        }
        if (!function_exists('get_option')) {
            return new WP_Error('sgap_mcp_no_wp', 'WordPress is not loaded.');
        }
        $value = get_option($name, null);
        $exists = ($value !== null);
        return ['name' => $name, 'exists' => $exists, 'value' => $value, 'writable' => self::is_writable_option($name)];
    }

    public static function update_option_tool($input = null) {
        $input = (array) $input;
        $name = (string) ($input['name'] ?? '');
        if ($name === '' || !array_key_exists('value', $input)) {
            return new WP_Error('sgap_mcp_missing', 'name and value are required.');
        }
        if (self::is_secret_option($name)) {
            return new WP_Error('sgap_mcp_forbidden', 'That option name looks like a credential and is never written through MCP.');
        }
        if (!self::is_writable_option($name)) {
            return new WP_Error('sgap_mcp_forbidden', 'That option is not on the write allowlist. Add it with the sgap_mcp_writable_options filter.');
        }
        $old = get_option($name, null);
        $new = $input['value'];
        $dry_run = array_key_exists('dry_run', $input) ? (bool) $input['dry_run'] : true;
        $confirm = !empty($input['confirm']);
        if ($dry_run || !$confirm) {
            return [
                'name' => $name, 'dry_run' => true, 'written' => false,
                'current' => $old, 'proposed' => $new,
                'how_to_write' => 'Send the same call with dry_run false and confirm true.'
            ];
        }
        if ($old === $new) {
            return ['name' => $name, 'dry_run' => false, 'written' => false, 'unchanged' => true, 'value' => $new];
        }
        update_option($name, $new);
        $token = self::remember_undo($name, $old, $new);
        return [
            'name' => $name, 'dry_run' => false, 'written' => true,
            'previous' => $old, 'value' => get_option($name),
            'undo_token' => $token, 'undo_valid_seconds' => self::UNDO_TTL
        ];
    }

    /** Stores the previous value in one bounded option and returns the token. */
    private static function remember_undo(string $name, $old, $new): string {
        $token = bin2hex(random_bytes(8));
        $log = get_option(self::UNDO_OPTION, []);
        $log = is_array($log) ? $log : [];
        $now = time();
        $log = array_filter($log, static fn($e) => is_array($e) && ($e['expires'] ?? 0) > $now);
        $log = array_slice($log, -49, null, true);
        $log[$token] = ['option' => $name, 'old' => $old, 'new' => $new, 'at' => $now, 'expires' => $now + self::UNDO_TTL];
        update_option(self::UNDO_OPTION, $log, false);
        return $token;
    }

    public static function undo($input = null) {
        $token = (string) (($input['token'] ?? '') ?: '');
        $log = get_option(self::UNDO_OPTION, []);
        if (!is_array($log) || !isset($log[$token])) {
            return new WP_Error('sgap_mcp_unknown_token', 'Unknown or expired undo token.');
        }
        $entry = $log[$token];
        if (($entry['expires'] ?? 0) < time()) {
            unset($log[$token]);
            update_option(self::UNDO_OPTION, $log, false);
            return new WP_Error('sgap_mcp_expired_token', 'That undo token has expired.');
        }
        update_option($entry['option'], $entry['old']);
        unset($log[$token]);
        update_option(self::UNDO_OPTION, $log, false);
        return ['restored' => $entry['option'], 'value' => get_option($entry['option']), 'undone_change' => $entry['new']];
    }

    public static function list_plugins($input = null): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all = function_exists('get_plugins') ? get_plugins() : [];
        $active = (array) get_option('active_plugins', []);
        $updates = function_exists('get_site_transient') ? get_site_transient('update_plugins') : false;
        $plugins = [];
        foreach ($all as $file => $data) {
            $plugins[] = [
                'file' => $file,
                'name' => $data['Name'] ?? $file,
                'version' => $data['Version'] ?? '',
                'active' => in_array($file, $active, true),
                'update_available' => is_object($updates) && isset($updates->response[$file]) ? ($updates->response[$file]->new_version ?? true) : false
            ];
        }
        return ['count' => count($plugins), 'plugins' => $plugins];
    }

    public static function set_plugin_state($input = null) {
        $plugin = (string) (($input['plugin'] ?? '') ?: '');
        $state = (string) (($input['state'] ?? '') ?: '');
        if ($plugin === '' || !in_array($state, ['activate', 'deactivate'], true)) {
            return new WP_Error('sgap_mcp_missing', 'plugin and state (activate|deactivate) are required.');
        }
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ($state === 'activate') {
            $result = activate_plugin($plugin);
            if (is_wp_error($result)) {
                return $result;
            }
        } else {
            deactivate_plugins([$plugin]);
        }
        $active = (array) get_option('active_plugins', []);
        return ['plugin' => $plugin, 'state' => $state, 'active' => in_array($plugin, $active, true)];
    }

    public static function list_themes($input = null): array {
        $themes = function_exists('wp_get_themes') ? wp_get_themes() : [];
        $out = [];
        foreach ($themes as $slug => $theme) {
            $out[] = [
                'slug' => $slug,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'active' => $slug === get_stylesheet()
            ];
        }
        return ['themes' => $out, 'active' => get_stylesheet()];
    }

    public static function flush_rewrites($input = null): array {
        flush_rewrite_rules(true);
        return ['flushed' => true, 'permalink_structure' => (string) get_option('permalink_structure')];
    }

    public static function flush_caches($input = null): array {
        $done = [];
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $done[] = 'object cache';
        }
        delete_option('rank_math_sitemap_cache_files');
        delete_option('rank_math_sitemap_cache_files_meta');
        $done[] = 'rank math sitemap cache';
        foreach (['litespeed_purge_all', 'rocket_clean_domain', 'w3tc_flush_all', 'autoptimize_flush_pagecache'] as $hook) {
            if (has_action($hook)) {
                do_action($hook);
                $done[] = $hook;
            }
        }
        return ['flushed' => $done];
    }

    public static function list_cron($input = null): array {
        $limit = min(200, max(1, (int) (($input['limit'] ?? 50) ?: 50)));
        $events = [];
        if (function_exists('_get_cron_array')) {
            foreach ((array) _get_cron_array() as $timestamp => $hooks) {
                foreach ((array) $hooks as $hook => $schedules) {
                    foreach ((array) $schedules as $key => $event) {
                        $events[] = [
                            'hook' => $hook,
                            'next_run' => wp_date('Y-m-d H:i:s', (int) $timestamp),
                            'next_run_utc' => gmdate('Y-m-d H:i:s', (int) $timestamp),
                            'recurrence' => $event['schedule'] ?? 'once',
                            'args' => $event['args'] ?? []
                        ];
                        if (count($events) >= $limit) {
                            break 3;
                        }
                    }
                }
            }
        }
        usort($events, static fn($a, $b) => strcmp($a['next_run_utc'], $b['next_run_utc']));
        return ['count' => count($events), 'events' => $events];
    }

    public static function run_cron($input = null) {
        $hook = (string) (($input['hook'] ?? '') ?: '');
        if ($hook === '') {
            return new WP_Error('sgap_mcp_missing', 'hook is required.');
        }
        $scheduled = function_exists('wp_next_scheduled') ? wp_next_scheduled($hook) : false;
        do_action_ref_array($hook, []);
        return ['hook' => $hook, 'was_scheduled' => (bool) $scheduled, 'ran' => true];
    }

    /* ------------------------------------------------------------------ *
     * Content
     * ------------------------------------------------------------------ */

    public static function list_posts($input = null): array {
        $input = (array) $input;
        $args = [
            'post_type' => (string) ($input['post_type'] ?? 'post'),
            'post_status' => (string) (($input['status'] ?? 'any') ?: 'any'),
            'numberposts' => min(100, max(1, (int) (($input['limit'] ?? 20) ?: 20))),
            'offset' => max(0, (int) ($input['offset'] ?? 0)),
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        if (!empty($input['search'])) {
            $args['s'] = (string) $input['search'];
        }
        $posts = get_posts($args);
        $out = [];
        foreach ($posts as $post) {
            $row = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'status' => $post->post_status,
                'type' => $post->post_type,
                'date' => $post->post_date,
                'modified' => $post->post_modified,
                'url' => get_permalink($post),
                'slug' => $post->post_name
            ];
            if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
                if ($product) {
                    $row['price'] = $product->get_price();
                    $row['stock_status'] = $product->get_stock_status();
                }
            }
            $out[] = $row;
        }
        return ['count' => count($out), 'posts' => $out];
    }

    public static function get_post_tool($input = null) {
        $id = (int) (($input['id'] ?? 0) ?: 0);
        $post = $id ? get_post($id) : null;
        if (!$post) {
            return new WP_Error('sgap_mcp_not_found', 'No post with that id.');
        }
        $meta = [];
        foreach (self::POST_META as $key) {
            $value = get_post_meta($id, $key, true);
            if ($value !== '' && $value !== null && $value !== false) {
                $meta[$key] = $value;
            }
        }
        return [
            'id' => (int) $post->ID,
            'type' => $post->post_type,
            'status' => $post->post_status,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'url' => get_permalink($post),
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
            'meta' => $meta,
            'featured_image' => get_post_thumbnail_id($id) ?: null
        ];
    }

    public static function create_post($input = null) {
        $input = (array) $input;
        $title = (string) (($input['title'] ?? '') ?: '');
        if ($title === '') {
            return new WP_Error('sgap_mcp_missing', 'title is required.');
        }
        $postarr = [
            'post_title' => $title,
            'post_content' => (string) ($input['content'] ?? ''),
            'post_excerpt' => (string) ($input['excerpt'] ?? ''),
            'post_status' => in_array(($input['status'] ?? 'draft'), ['draft', 'pending', 'publish', 'private'], true) ? $input['status'] : 'draft',
            'post_type' => (string) (($input['post_type'] ?? 'post') ?: 'post')
        ];
        if (!empty($input['slug'])) {
            $postarr['post_name'] = sanitize_title((string) $input['slug']);
        }
        $id = wp_insert_post($postarr, true);
        if (is_wp_error($id)) {
            return $id;
        }
        self::write_meta($id, $input);
        return self::get_post_tool(['id' => $id]);
    }

    public static function update_post($input = null) {
        $input = (array) $input;
        $id = (int) (($input['id'] ?? 0) ?: 0);
        if (!$id || !get_post($id)) {
            return new WP_Error('sgap_mcp_not_found', 'No post with that id.');
        }
        $postarr = ['ID' => $id];
        foreach (['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt'] as $field => $column) {
            if (array_key_exists($field, $input)) {
                $postarr[$column] = (string) $input[$field];
            }
        }
        if (isset($input['status']) && in_array($input['status'], ['draft', 'pending', 'publish', 'private'], true)) {
            $postarr['post_status'] = $input['status'];
        }
        if (!empty($input['slug'])) {
            $postarr['post_name'] = sanitize_title((string) $input['slug']);
        }
        if (count($postarr) > 1) {
            $result = wp_update_post($postarr, true);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        self::write_meta($id, $input);
        return self::get_post_tool(['id' => $id]);
    }

    /** Writes the SEO meta and featured image of a post from tool input. */
    private static function write_meta(int $id, array $input): void {
        $map = [
            'seo_title' => 'rank_math_title',
            'seo_description' => 'rank_math_description',
            'focus_keyword' => 'rank_math_focus_keyword'
        ];
        foreach ($map as $field => $meta_key) {
            if (array_key_exists($field, $input)) {
                update_post_meta($id, $meta_key, sanitize_text_field((string) $input[$field]));
            }
        }
        if (!empty($input['featured_image_id'])) {
            set_post_thumbnail($id, (int) $input['featured_image_id']);
        }
    }

    public static function trash_post($input = null) {
        $input = (array) $input;
        $id = (int) (($input['id'] ?? 0) ?: 0);
        $post = $id ? get_post($id) : null;
        if (!$post) {
            return new WP_Error('sgap_mcp_not_found', 'No post with that id.');
        }
        if (!empty($input['force'])) {
            if ($post->post_status !== 'trash') {
                return new WP_Error('sgap_mcp_refused', 'Permanent deletion is only allowed for a post that is already in the trash.');
            }
            $deleted = wp_delete_post($id, true);
            return ['id' => $id, 'deleted' => (bool) $deleted];
        }
        $result = wp_trash_post($id);
        return ['id' => $id, 'status' => $result ? 'trash' : 'unchanged'];
    }

    /* ------------------------------------------------------------------ *
     * Media
     * ------------------------------------------------------------------ */

    public static function list_media($input = null): array {
        $input = (array) $input;
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'numberposts' => min(100, max(1, (int) (($input['limit'] ?? 20) ?: 20))),
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        if (!empty($input['mime'])) {
            $args['post_mime_type'] = (string) $input['mime'];
        }
        if (!empty($input['search'])) {
            $args['s'] = (string) $input['search'];
        }
        $items = [];
        foreach (get_posts($args) as $post) {
            $items[] = [
                'id' => (int) $post->ID,
                'title' => $post->post_title,
                'mime' => $post->post_mime_type,
                'url' => wp_get_attachment_url($post->ID),
                'alt' => get_post_meta($post->ID, '_wp_attachment_image_alt', true),
                'date' => $post->post_date
            ];
        }
        return ['count' => count($items), 'media' => $items];
    }

    public static function upload_media($input = null) {
        $input = (array) $input;
        $attach_to = (int) ($input['attach_to'] ?? 0);
        $url = (string) ($input['url'] ?? '');
        $base64 = (string) ($input['content_base64'] ?? '');
        if ($url === '' && $base64 === '') {
            return new WP_Error('sgap_mcp_missing', 'Give either url or content_base64.');
        }
        foreach (['file.php', 'media.php', 'image.php'] as $include) {
            $file = ABSPATH . 'wp-admin/includes/' . $include;
            if (file_exists($file)) {
                require_once $file;
            }
        }
        if ($url !== '') {
            if (!function_exists('media_sideload_image')) {
                return new WP_Error('sgap_mcp_unavailable', 'media_sideload_image() is not available.');
            }
            $attachment_id = media_sideload_image(esc_url_raw($url), $attach_to, $input['alt'] ?? null, 'id');
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }
        } else {
            $bits = base64_decode($base64, true);
            if ($bits === false) {
                return new WP_Error('sgap_mcp_invalid', 'content_base64 is not valid base64.');
            }
            $filename = sanitize_file_name((string) (($input['filename'] ?? '') ?: 'upload-' . time() . '.bin'));
            $upload = wp_upload_bits($filename, null, $bits);
            if (!empty($upload['error'])) {
                return new WP_Error('sgap_mcp_upload_failed', $upload['error']);
            }
            $attachment_id = wp_insert_attachment([
                'post_mime_type' => $upload['type'] ?? '',
                'post_title' => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            ], $upload['file'], $attach_to);
            if (is_wp_error($attachment_id)) {
                return $attachment_id;
            }
            if (function_exists('wp_generate_attachment_metadata')) {
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
            }
        }
        if (!empty($input['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field((string) $input['alt']));
        }
        if (!empty($input['set_featured']) && $attach_to) {
            set_post_thumbnail($attach_to, $attachment_id);
        }
        return [
            'id' => (int) $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'attached_to' => $attach_to ?: null,
            'featured_image_set' => !empty($input['set_featured']) && $attach_to
        ];
    }

    /* ------------------------------------------------------------------ *
     * Shop
     * ------------------------------------------------------------------ */

    public static function list_products($input = null) {
        if (!function_exists('wc_get_products')) {
            return new WP_Error('sgap_mcp_no_woocommerce', 'WooCommerce is not active.');
        }
        $input = (array) $input;
        $args = [
            'limit' => min(100, max(1, (int) (($input['limit'] ?? 20) ?: 20))),
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'status' => (string) (($input['status'] ?? 'publish') ?: 'publish'),
            'orderby' => 'date',
            'order' => 'DESC',
            'paginate' => true
        ];
        if (!empty($input['search'])) {
            $args['s'] = (string) $input['search'];
        }
        $result = wc_get_products($args);
        $products = is_object($result) ? $result->products : (array) $result;
        $out = [];
        foreach ($products as $product) {
            $out[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'status' => $product->get_status(),
                'price' => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'stock_status' => $product->get_stock_status(),
                'stock_quantity' => $product->get_stock_quantity(),
                'sku' => $product->get_sku(),
                'url' => get_permalink($product->get_id())
            ];
        }
        return [
            'count' => count($out),
            'total' => is_object($result) ? (int) $result->total : count($out),
            'products' => $out
        ];
    }

    public static function get_product($input = null) {
        $id = (int) (($input['id'] ?? 0) ?: 0);
        if (!function_exists('wc_get_product')) {
            return new WP_Error('sgap_mcp_no_woocommerce', 'WooCommerce is not active.');
        }
        $product = $id ? wc_get_product($id) : null;
        if (!$product) {
            return new WP_Error('sgap_mcp_not_found', 'No product with that id.');
        }
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'url' => get_permalink($id),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'price' => $product->get_price(),
            'sku' => $product->get_sku(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'categories' => wp_get_post_terms($id, 'product_cat', ['fields' => 'names']),
            'tags' => wp_get_post_terms($id, 'product_tag', ['fields' => 'names']),
            'image' => wp_get_attachment_url($product->get_image_id()) ?: null,
            'gallery' => array_values(array_filter(array_map('wp_get_attachment_url', $product->get_gallery_image_ids()))),
            'seo' => [
                'title' => get_post_meta($id, 'rank_math_title', true),
                'description' => get_post_meta($id, 'rank_math_description', true),
                'focus_keyword' => get_post_meta($id, 'rank_math_focus_keyword', true)
            ]
        ];
    }

    public static function update_product($input = null) {
        $input = (array) $input;
        $id = (int) (($input['id'] ?? 0) ?: 0);
        if (!function_exists('wc_get_product')) {
            return new WP_Error('sgap_mcp_no_woocommerce', 'WooCommerce is not active.');
        }
        $product = $id ? wc_get_product($id) : null;
        if (!$product) {
            return new WP_Error('sgap_mcp_not_found', 'No product with that id.');
        }
        if (isset($input['name'])) {
            $product->set_name((string) $input['name']);
        }
        if (isset($input['description'])) {
            $product->set_description((string) $input['description']);
        }
        if (isset($input['regular_price'])) {
            $product->set_regular_price((string) $input['regular_price']);
        }
        if (isset($input['sale_price'])) {
            $product->set_sale_price((string) $input['sale_price']);
        }
        if (isset($input['stock_status']) && in_array($input['stock_status'], ['instock', 'outofstock', 'onbackorder'], true)) {
            $product->set_stock_status($input['stock_status']);
        }
        if (isset($input['stock_quantity'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $input['stock_quantity']);
        }
        if (isset($input['status']) && in_array($input['status'], ['publish', 'draft', 'pending', 'private'], true)) {
            $product->set_status($input['status']);
        }
        $product->save();
        return self::get_product(['id' => $id]);
    }

    /* ------------------------------------------------------------------ *
     * Indexing
     * ------------------------------------------------------------------ */

    public static function indexnow_submit($input = null) {
        $urls = (array) (($input['urls'] ?? []) ?: []);
        if (!$urls) {
            return new WP_Error('sgap_mcp_missing', 'urls is required.');
        }
        $key = SGAP_MCP_Key::key();
        $payload = self::indexnow_payload($urls, $key, SGAP_MCP_Key::url());
        $response = wp_remote_post('https://api.indexnow.org/indexnow', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => wp_json_encode($payload)
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $history = get_option('sgap_indexnow_history', []);
        $history = is_array($history) ? array_slice($history, -19, null, true) : [];
        $history[time()] = ['status' => $code, 'urls' => count($payload['urlList'] ?? [])];
        update_option('sgap_indexnow_history', $history, false);
        return [
            'status' => $code,
            'accepted' => $code === 200,
            'note' => $code === 202 ? '202 means IndexNow accepted the request but key validation is asynchronous; it does not prove the key was found.' : null,
            'key_location' => $payload['keyLocation'],
            'urls_sent' => count($payload['urlList'] ?? []),
            'response' => substr($body, 0, 500)
        ];
    }

    public static function indexnow_status($input = null): array {
        $key = SGAP_MCP_Key::key();
        $url = SGAP_MCP_Key::url();
        $probe = wp_remote_get($url, ['timeout' => 15, 'redirection' => 2]);
        $served = is_wp_error($probe) ? null : trim((string) wp_remote_retrieve_body($probe));
        $rm_log = get_option('rank_math_indexnow_log', []);
        $tail = [];
        if (is_array($rm_log)) {
            foreach (array_slice($rm_log, -10, null, true) as $row) {
                $tail[] = [
                    'url' => is_array($row) ? ($row['url'] ?? '') : (string) $row,
                    'status' => is_array($row) ? ($row['status'] ?? null) : null,
                    'time' => is_array($row) ? ($row['time'] ?? null) : null
                ];
            }
        }
        return [
            'key' => $key,
            'key_url' => $url,
            'key_url_served_correctly' => is_wp_error($probe) ? 'request failed: ' . $probe->get_error_message() : ($served === $key),
            'probe_status' => is_wp_error($probe) ? null : (int) wp_remote_retrieve_response_code($probe),
            'our_submissions' => get_option('sgap_indexnow_history', []),
            'rank_math_log_tail' => $tail,
            'rank_math_log_count' => is_array($rm_log) ? count($rm_log) : 0
        ];
    }

    public static function sitemap_audit($input = null): array {
        $input = (array) $input;
        $index = home_url('/sitemap_index.xml');
        $fetch = static function (string $url) {
            $response = wp_remote_get($url, ['timeout' => 20, 'redirection' => 3]);
            return is_wp_error($response) ? $response : (string) wp_remote_retrieve_body($response);
        };
        $xml = $fetch($index);
        if (is_wp_error($xml)) {
            return ['index_url' => $index, 'error' => $xml->get_error_message(), 'hint' => 'Rank Math may be inactive, or the sitemap is cached by the host.'];
        }
        $children = [];
        $all = [];
        foreach (self::sitemap_locs($xml) as $loc) {
            $child_xml = $fetch($loc);
            $locs = is_wp_error($child_xml) ? [] : self::sitemap_locs($child_xml);
            $children[] = ['url' => $loc, 'count' => count($locs), 'error' => is_wp_error($child_xml) ? $child_xml->get_error_message() : null];
            $all = array_merge($all, $locs);
        }
        $wanted = (string) ($input['url'] ?? '');
        $result = [
            'index_url' => $index,
            'sitemaps' => $children,
            'total_urls' => count($all)
        ];
        if ($wanted !== '') {
            $normalised = untrailingslashit($wanted);
            $match = null;
            foreach ($all as $loc) {
                if (untrailingslashit($loc) === $normalised) {
                    $match = $loc;
                    break;
                }
            }
            $result['checked_url'] = $wanted;
            $result['present'] = $match !== null;
            $result['matched'] = $match;
        }
        return $result;
    }

    /* ------------------------------------------------------------------ *
     * Abilities bridge
     * ------------------------------------------------------------------ */

    public static function list_abilities($input = null): array {
        $input = (array) $input;
        $category = (string) ($input['category'] ?? '');
        $all = self::all_abilities();
        $out = [];
        foreach ($all as $name => $ability) {
            $row = self::describe_ability($ability);
            if ($row['name'] === '') {
                $row['name'] = is_string($name) ? $name : '';
            }
            if ($category !== '' && $row['category'] !== $category) {
                continue;
            }
            $row['callable_here'] = !self::is_secret_option($row['name']);
            $out[] = $row;
        }
        usort($out, static fn($a, $b) => strcmp($a['name'], $b['name']));
        return [
            'count' => count($out),
            'source' => function_exists('wp_get_abilities') ? 'wp_get_abilities()' : 'WP_Abilities_Registry',
            'abilities' => $out
        ];
    }

    public static function call_ability($input = null) {
        $input = (array) $input;
        $name = (string) (($input['name'] ?? '') ?: '');
        $args = (array) (($input['input'] ?? []) ?: []);
        if ($name === '') {
            return new WP_Error('sgap_mcp_missing', 'name is required.');
        }
        $ability = function_exists('wp_get_ability') ? wp_get_ability($name) : null;
        if (!$ability) {
            return new WP_Error('sgap_mcp_not_found', 'No ability named ' . $name . '. Use list-abilities to see them.');
        }
        if (method_exists($ability, 'check_permissions') && !$ability->check_permissions($args)) {
            return new WP_Error('sgap_mcp_forbidden', 'The ability ' . $name . ' refused the current user.');
        }
        foreach (['execute', 'run', 'invoke'] as $method) {
            if (method_exists($ability, $method)) {
                $result = $ability->{$method}($args);
                return is_array($result) ? $result + ['_ability' => $name] : ['_ability' => $name, 'result' => $result];
            }
        }
        return new WP_Error('sgap_mcp_unavailable', 'The ability ' . $name . ' has no executable method.');
    }
}

/**
 * Serves this plugin's IndexNow key so IndexNow can validate the host.
 *
 * Accepted at two URLs, because hosts differ on whether pretty permalinks reach
 * a new rule before the rewrite cache is rebuilt:
 *   https://example.com/sgap-indexnow-<key>.txt
 *   https://example.com/?sgap_indexnow_key=<key>
 */
class SGAP_MCP_Key {
    const QV = 'sgap_indexnow_key';
    const OPTION = 'sgap_indexnow_key';
    const RULE = '^sgap-indexnow-([a-f0-9]{32})\.txt$';

    public static function init(): void {
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('init', [__CLASS__, 'add_rule']);
        add_action('init', [__CLASS__, 'serve_from_query'], 1);
        add_action('template_redirect', [__CLASS__, 'serve_from_rewrite']);
    }

    /** The key, created on first use. 32 hex characters, as IndexNow requires. */
    public static function key(): string {
        $key = (string) get_option(self::OPTION, '');
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            $key = bin2hex(random_bytes(16));
            update_option(self::OPTION, $key, false);
        }
        return $key;
    }

    /** Where IndexNow fetches the key from. */
    public static function url(): string {
        return home_url('/?sgap_indexnow_key=' . self::key());
    }

    public static function query_vars($vars) {
        $vars[] = self::QV;
        return $vars;
    }

    public static function add_rule(): void {
        add_rewrite_rule(self::RULE, 'index.php?' . self::QV . '=$matches[1]', 'top');
    }

    public static function serve_from_query(): void {
        if (!isset($_GET[self::QV])) {
            return;
        }
        self::output(sanitize_text_field(wp_unslash((string) $_GET[self::QV])));
    }

    public static function serve_from_rewrite(): void {
        $value = get_query_var(self::QV);
        if ($value === '' || $value === null) {
            return;
        }
        self::output((string) $value);
    }

    /** Sends the key as text/plain when it matches, 404 otherwise, and stops. */
    private static function output(string $candidate): void {
        $key = self::key();
        if (function_exists('status_header')) {
            status_header($candidate === $key ? 200 : 404);
        }
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo $candidate === $key ? $key : 'not found';
        exit;
    }
}
