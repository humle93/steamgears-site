<?php
/**
 * AI access (7.94.0): ChatGPT and other AI clients get their own login to the site.
 *
 * The owner gives an AI client access from one page (Steamgears Autopilot -> AI access) instead of
 * sharing their own login. Each client gets:
 *
 *   - its own WordPress user (ai-chatgpt, ai-claude, ...) with a role the owner picks: editor or
 *     author, never administrator, so plugins, users, settings and code stay out of reach;
 *   - one application password, shown once. Creating a new key deletes the old one;
 *   - no wp-admin login: the user's normal password is random and refused at the login form, so
 *     the key only works through the REST API;
 *   - new content as drafts only, unless the owner allows publishing for that client;
 *   - no raw HTML/scripts (unfiltered_html), users, plugins, themes or settings, even as editor;
 *   - a log of every REST call it makes (the newest 200).
 *
 * ChatGPT connects as a Custom GPT with Actions, imported from GET /sgap/v1/openapi. Every
 * operation in that schema is marked x-openai-isConsequential=false, so ChatGPT asks once
 * ("Always allow") instead of before every call: the limits above are enforced by the site, not
 * by the chat window. Closing the access (one click) deletes the key and removes the role.
 *
 * Not here on purpose: the Claude app's own permission prompts and Novamira's approval cutoff.
 * Those are the owner's settings in those tools.
 *
 * Loaded from steamgears-autopilot.php.
 */
if (!defined('ABSPATH')) {
    exit;
}

class SGAP_AI_Access {
    const PAGE = 'sgap-ai-access';
    const META = 'sgap_ai_client';
    const META_PUBLISH = 'sgap_ai_may_publish';
    const META_CREATED = 'sgap_ai_created';
    const META_REVOKED = 'sgap_ai_revoked';
    const LOG = 'sgap_ai_access_log';
    const LOG_MAX = 200;
    /** Transient prefix (+ admin user id) holding a new key until the page has shown it once. */
    const ONCE = 'sgap_ai_access_once_';
    const NOTICE = 'sgap_ai_access_notice_';
    const CLIENTS = ['chatgpt' => 'ChatGPT', 'claude' => 'Claude', 'gemini' => 'Gemini'];
    /** Never administrator: plugins, users, options and code stay with the owner. */
    const ROLES = ['editor' => 'Editor — all posts and pages', 'author' => 'Author — only its own posts'];
    const LIVE = ['publish', 'future', 'private'];
    const CONTENT_TYPES = ['post', 'page', 'product'];
    const SEO_META = ['rank_math_title', 'rank_math_description', 'rank_math_focus_keyword'];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 40);
        add_action('admin_post_sgap_ai_access_create', [__CLASS__, 'handle_create']);
        add_action('admin_post_sgap_ai_access_revoke', [__CLASS__, 'handle_revoke']);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('rest_api_init', [__CLASS__, 'register_seo_meta'], 5);
        add_filter('wp_authenticate_user', [__CLASS__, 'block_password_login'], 30, 2);
        add_filter('wp_insert_post_data', [__CLASS__, 'drafts_only'], 20, 2);
        add_filter('rest_request_after_callbacks', [__CLASS__, 'log_request'], 99, 3);
        add_filter('user_has_cap', [__CLASS__, 'limit_caps'], 99, 4);
    }

    /** Capabilities an AI user never gets, whatever its role: no raw scripts or embeds, no users, plugins or settings. */
    const DENIED_CAPS = ['unfiltered_html', 'unfiltered_upload', 'manage_options', 'edit_users', 'create_users', 'delete_users', 'promote_users',
                         'list_users', 'install_plugins', 'activate_plugins', 'edit_plugins', 'delete_plugins', 'update_plugins',
                         'edit_themes', 'switch_themes', 'edit_theme_options', 'update_core', 'import', 'export'];

    /** Removes DENIED_CAPS for an AI user. Pure on the arrays it gets. */
    public static function strip_caps(array $allcaps): array {
        foreach (self::DENIED_CAPS as $cap) {
            unset($allcaps[$cap]);
        }
        return $allcaps;
    }

    public static function limit_caps($allcaps, $caps, $args, $user) {
        if (is_array($allcaps) && $user instanceof WP_User && self::is_client((int) $user->ID)) {
            return self::strip_caps($allcaps);
        }
        return $allcaps;
    }

    public static function login_for(string $client): string {
        return 'ai-' . $client;
    }

    public static function is_client(int $user_id): bool {
        return $user_id > 0 && (string) get_user_meta($user_id, self::META, true) !== '';
    }

    /** "Basic" value for ChatGPT's API key field: base64 of login:password. Pure function. */
    public static function basic_key(string $login, string $password): string {
        return base64_encode($login . ':' . $password);
    }

    /**
     * Creates the AI user (or reuses its own earlier one) and a fresh application password.
     * An existing user with the same login that is not an AI user is never taken over.
     * @return array{user_id:int, login:string, label:string, password:string, basic:string}|WP_Error
     */
    public static function create(string $client, string $role, bool $may_publish) {
        if (!isset(self::CLIENTS[$client])) {
            return new WP_Error('sgap_ai_client', 'Unknown AI client.');
        }
        if (!isset(self::ROLES[$role])) {
            return new WP_Error('sgap_ai_role', 'Choose editor or author. AI clients never get administrator.');
        }
        if (!class_exists('WP_Application_Passwords')) {
            return new WP_Error('sgap_ai_app_passwords', 'This WordPress has no application passwords (5.6 or newer is needed).');
        }
        $login = self::login_for($client);
        $label = self::CLIENTS[$client];
        $uid = (int) username_exists($login);
        if ($uid > 0 && !self::is_client($uid)) {
            return new WP_Error('sgap_ai_taken', sprintf('A user named %s already exists and is not an AI user. Rename it first.', $login));
        }
        if ($uid === 0) {
            $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
            $uid = wp_insert_user([
                'user_login' => $login,
                'user_pass' => wp_generate_password(48, true, true),
                'user_email' => $login . '@ai-access.invalid',
                'display_name' => $label,
                'nickname' => $label,
                'description' => sprintf('AI access for %s on %s, made by Steamgears Autopilot. Close it under Steamgears Autopilot → AI access.', $label, $host),
                'role' => $role
            ]);
            if (is_wp_error($uid)) {
                return $uid;
            }
            $uid = (int) $uid;
        } else {
            (new WP_User($uid))->set_role($role);
        }
        update_user_meta($uid, self::META, $client);
        update_user_meta($uid, self::META_PUBLISH, $may_publish ? '1' : '0');
        update_user_meta($uid, self::META_CREATED, time());
        delete_user_meta($uid, self::META_REVOKED);
        WP_Application_Passwords::delete_all_application_passwords($uid);
        $made = WP_Application_Passwords::create_new_application_password($uid, ['name' => 'Steamgears AI access — ' . $label]);
        if (is_wp_error($made)) {
            return $made;
        }
        return ['user_id' => $uid, 'login' => $login, 'label' => $label, 'password' => (string) $made[0],
                'basic' => self::basic_key($login, (string) $made[0])];
    }

    /** Deletes the key and removes every role: the user stays (its posts keep their author) but can do nothing. */
    public static function revoke(int $user_id) {
        if (!self::is_client($user_id)) {
            return new WP_Error('sgap_ai_not_client', 'That user is not an AI access user.');
        }
        if (class_exists('WP_Application_Passwords')) {
            WP_Application_Passwords::delete_all_application_passwords($user_id);
        }
        (new WP_User($user_id))->set_role('');
        update_user_meta($user_id, self::META_REVOKED, time());
        return true;
    }

    /** The AI users for the page: who, role, publishing, key and last use. */
    public static function clients(): array {
        $out = [];
        foreach (get_users(['meta_key' => self::META, 'number' => 50]) as $u) {
            $last = 0;
            $keys = 0;
            if (class_exists('WP_Application_Passwords')) {
                foreach ((array) WP_Application_Passwords::get_user_application_passwords($u->ID) as $item) {
                    $keys++;
                    $last = max($last, (int) ($item['last_used'] ?? 0));
                }
            }
            $out[] = [
                'id' => (int) $u->ID,
                'login' => (string) $u->user_login,
                'client' => (string) get_user_meta($u->ID, self::META, true),
                'role' => implode(', ', (array) $u->roles),
                'may_publish' => get_user_meta($u->ID, self::META_PUBLISH, true) === '1',
                'created' => (int) get_user_meta($u->ID, self::META_CREATED, true),
                'revoked' => (int) get_user_meta($u->ID, self::META_REVOKED, true),
                'keys' => $keys,
                'last_used' => $last
            ];
        }
        return $out;
    }

    /** The AI user cannot sign in at wp-login.php: its key only works through the REST API. */
    public static function block_password_login($user, $password = '') {
        if ($user instanceof WP_User && self::is_client((int) $user->ID)) {
            return new WP_Error('sgap_ai_no_login', 'This is an AI access user. It can only use its application password through the REST API.');
        }
        return $user;
    }

    /**
     * New content from an AI client without publishing rights becomes a draft. Pure function:
     * $old is the post's status before this save ('' for a new post). Posts that are already live
     * can still be edited and stay live; only the step from not-live to live is stopped.
     */
    public static function draft_status(array $data, string $old): array {
        if (in_array((string) ($data['post_type'] ?? 'post'), self::CONTENT_TYPES, true)
            && in_array((string) ($data['post_status'] ?? ''), self::LIVE, true)
            && !in_array($old, self::LIVE, true)) {
            $data['post_status'] = 'draft';
        }
        return $data;
    }

    public static function drafts_only($data, $postarr) {
        $uid = (int) get_current_user_id();
        if (!is_array($data) || !self::is_client($uid) || get_user_meta($uid, self::META_PUBLISH, true) === '1') {
            return $data;
        }
        $id = (int) ($postarr['ID'] ?? 0);
        return self::draft_status($data, $id > 0 ? (string) get_post_status($id) : '');
    }

    /** Keeps the newest $max entries. Pure function. */
    public static function append_log(array $log, array $entry, int $max = self::LOG_MAX): array {
        $log[] = $entry;
        return count($log) > $max ? array_slice($log, -$max) : $log;
    }

    public static function log_request($response, $handler, $request) {
        $uid = (int) get_current_user_id();
        if (!self::is_client($uid) || !is_object($request) || !method_exists($request, 'get_route')) {
            return $response;
        }
        $status = is_wp_error($response) ? (int) (($response->get_error_data()['status'] ?? 500))
            : (is_object($response) && method_exists($response, 'get_status') ? (int) $response->get_status() : 200);
        $entry = ['at' => time(), 'client' => (string) get_user_meta($uid, self::META, true),
                  'method' => (string) $request->get_method(), 'route' => mb_substr((string) $request->get_route(), 0, 120),
                  'status' => $status];
        $log = get_option(self::LOG);
        update_option(self::LOG, self::append_log(is_array($log) ? $log : [], $entry), false);
        return $response;
    }

    public static function log(): array {
        $log = get_option(self::LOG);
        return is_array($log) ? $log : [];
    }

    /**
     * Rank Math's title, description and focus keyword in the posts REST API, so an AI client can
     * set them on its drafts. Only registered for a user who can edit posts: anonymous visitors do
     * not see the focus keywords.
     */
    public static function register_seo_meta(): void {
        if (!function_exists('register_post_meta') || !current_user_can('edit_posts')) {
            return;
        }
        foreach (['post', 'page'] as $type) {
            foreach (self::SEO_META as $key) {
                if (function_exists('registered_meta_key_exists') && registered_meta_key_exists('post', $key, $type)) {
                    continue;
                }
                register_post_meta($type, $key, [
                    'show_in_rest' => true, 'single' => true, 'type' => 'string',
                    'auth_callback' => function ($allowed, $meta_key, $post_id) {
                        return current_user_can('edit_post', (int) $post_id);
                    }
                ]);
            }
        }
    }

    public static function routes(): void {
        register_rest_route(SGAP_REST::NS, '/openapi', [
            'methods' => 'GET',
            'callback' => function () {
                return rest_ensure_response(self::openapi(rtrim(rest_url(), '/'), (string) get_bloginfo('name')));
            },
            // Public on purpose: ChatGPT imports the schema without a key. It only describes core routes.
            'permission_callback' => '__return_true'
        ]);
    }

    /** OpenAPI 3.1 schema for ChatGPT Actions. Pure function. */
    public static function openapi(string $server, string $site): array {
        $obj = ['type' => 'object'];
        $ok = ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $obj]]]];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $edit = ['name' => 'context', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['view', 'edit'], 'default' => 'edit'],
                 'description' => 'Use edit to get the raw content and the SEO fields.'];
        $list = [
            ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['publish', 'draft', 'pending', 'future', 'private', 'any']]],
            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20]],
            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]],
            $edit
        ];
        $body = ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Content']]]];
        $op = function (string $opid, string $summary, array $params = [], ?array $request = null) use ($ok) {
            $o = ['operationId' => $opid, 'summary' => $summary, 'x-openai-isConsequential' => false, 'responses' => $ok];
            if ($params) {
                $o['parameters'] = $params;
            }
            if ($request) {
                $o['requestBody'] = $request;
            }
            return $o;
        };
        $terms = [['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                  ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]]];
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => ($site !== '' ? $site : 'WordPress') . ' — WordPress',
                'version' => defined('SGAP_VERSION') ? SGAP_VERSION : '1',
                'description' => 'Read and write posts and pages on this WordPress site. New posts are saved as drafts unless the site owner allowed publishing for this AI. SEO fields go in meta: rank_math_title, rank_math_description, rank_math_focus_keyword.'
            ],
            'servers' => [['url' => $server]],
            'security' => [['basicAuth' => []]],
            'components' => [
                'securitySchemes' => ['basicAuth' => ['type' => 'http', 'scheme' => 'basic']],
                'schemas' => ['Content' => ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string', 'description' => 'HTML or block markup.'],
                    'excerpt' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'pending', 'publish', 'future']],
                    'date' => ['type' => 'string', 'description' => 'Local time, e.g. 2026-12-01T08:00:00. With status future it schedules the post.'],
                    'slug' => ['type' => 'string'],
                    'categories' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'meta' => ['type' => 'object', 'properties' => [
                        'rank_math_title' => ['type' => 'string'],
                        'rank_math_description' => ['type' => 'string'],
                        'rank_math_focus_keyword' => ['type' => 'string']
                    ]]
                ]]]
            ],
            'paths' => [
                '/wp/v2/posts' => ['get' => $op('listPosts', 'List posts', $list), 'post' => $op('createPost', 'Create a post (a draft unless publishing is allowed)', [], $body)],
                '/wp/v2/posts/{id}' => ['get' => $op('getPost', 'Get one post', [$id, $edit]), 'post' => $op('updatePost', 'Update a post', [$id], $body)],
                '/wp/v2/pages' => ['get' => $op('listPages', 'List pages', $list)],
                '/wp/v2/pages/{id}' => ['get' => $op('getPage', 'Get one page', [$id, $edit]), 'post' => $op('updatePage', 'Update a page', [$id], $body)],
                '/wp/v2/categories' => ['get' => $op('listCategories', 'List categories', $terms)],
                '/wp/v2/tags' => ['get' => $op('listTags', 'List tags', $terms)]
            ]
        ];
    }

    /* ------------------------------------------------------------------ *
     * Admin
     * ------------------------------------------------------------------ */

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'AI access', 'AI access', 'manage_options', self::PAGE, [__CLASS__, 'page']);
    }

    private static function back(): void {
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE));
        exit;
    }

    public static function handle_create(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to do that.');
        }
        check_admin_referer('sgap_ai_access');
        $r = self::create(sanitize_key((string) ($_POST['client'] ?? '')), sanitize_key((string) ($_POST['role'] ?? '')), !empty($_POST['may_publish']));
        $me = get_current_user_id();
        if (is_wp_error($r)) {
            set_transient(self::NOTICE . $me, $r->get_error_message(), 5 * MINUTE_IN_SECONDS);
        } else {
            set_transient(self::ONCE . $me, $r, 10 * MINUTE_IN_SECONDS);
        }
        self::back();
    }

    public static function handle_revoke(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to do that.');
        }
        check_admin_referer('sgap_ai_access');
        $r = self::revoke((int) ($_POST['user_id'] ?? 0));
        set_transient(self::NOTICE . get_current_user_id(), is_wp_error($r) ? $r->get_error_message() : 'The access is closed: the key is deleted and the user has no role.', 5 * MINUTE_IN_SECONDS);
        self::back();
    }

    private static function when(int $ts): string {
        return $ts > 0 ? wp_date('Y-m-d H:i', $ts) : '—';
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to see this page.');
        }
        $me = get_current_user_id();
        SGAP_Admin::open('AI access', 'Give ChatGPT (or another AI) its own login to this site. You approve once; the site keeps the limits.');
        $notice = get_transient(self::NOTICE . $me);
        if (is_string($notice) && $notice !== '') {
            delete_transient(self::NOTICE . $me);
            echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        }
        $once = get_transient(self::ONCE . $me);
        if (is_array($once)) {
            delete_transient(self::ONCE . $me);
            echo '<div class="notice notice-success"><p><strong>' . esc_html($once['label']) . ' has access.</strong> Copy the key now: it is shown only this once.</p>'
                . '<p>Username: <code>' . esc_html($once['login']) . '</code><br>Application password: <code>' . esc_html($once['password']) . '</code><br>'
                . 'ChatGPT API key (Basic): <code style="word-break:break-all">' . esc_html($once['basic']) . '</code></p></div>';
        }
        if (function_exists('wp_is_application_passwords_available') && !wp_is_application_passwords_available()) {
            echo '<div class="notice notice-warning"><p>Application passwords are turned off on this site (they need https, or a plugin disabled them). The key will not work until they are on.</p></div>';
        }

        echo '<h2>Give access</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sgap_ai_access');
        echo '<input type="hidden" name="action" value="sgap_ai_access_create"><table class="form-table"><tr><th>AI</th><td><select name="client">';
        foreach (self::CLIENTS as $k => $label) {
            echo '<option value="' . esc_attr($k) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr><tr><th>Role</th><td><select name="role">';
        foreach (self::ROLES as $k => $label) {
            echo '<option value="' . esc_attr($k) . '">' . esc_html($label) . '</option>';
        }
        echo '</select><p class="description">Never administrator: plugins, users, settings and code stay with you.</p></td></tr>'
            . '<tr><th>Publishing</th><td><label><input type="checkbox" name="may_publish" value="1"> May publish and schedule</label>'
            . '<p class="description">Off: everything new it writes is saved as a draft for you to publish. Posts that are already live can still be edited.</p></td></tr></table>'
            . '<p><button class="button button-primary" type="submit">Create access (or a new key)</button> <span class="description">A new key replaces the old one.</span></p></form>';

        $clients = self::clients();
        echo '<h2>Who has access</h2>';
        if (!$clients) {
            echo '<p class="description">No AI has access yet.</p>';
        } else {
            echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr><th>AI</th><th>User</th><th>Role</th><th>Publishing</th><th>Created</th><th>Last used</th><th></th></tr></thead><tbody>';
            foreach ($clients as $c) {
                $open = !$c['revoked'] && $c['keys'] > 0;
                echo '<tr><td>' . esc_html(self::CLIENTS[$c['client']] ?? $c['client']) . '</td><td><code>' . esc_html($c['login']) . '</code></td>'
                    . '<td>' . esc_html($c['role'] !== '' ? $c['role'] : 'none') . '</td><td>' . ($c['may_publish'] ? 'may publish' : 'drafts only') . '</td>'
                    . '<td>' . esc_html(self::when($c['created'])) . '</td><td>' . esc_html(self::when($c['last_used'])) . '</td><td>';
                if ($open) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:0">';
                    wp_nonce_field('sgap_ai_access');
                    echo '<input type="hidden" name="action" value="sgap_ai_access_revoke"><input type="hidden" name="user_id" value="' . esc_attr((string) $c['id']) . '">'
                        . '<button class="button" type="submit" onclick="return confirm(\'Close this access? The key stops working at once.\')">Close access</button></form>';
                } else {
                    echo 'closed ' . esc_html(self::when($c['revoked']));
                }
                echo '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        $schema = rest_url(SGAP_REST::NS . '/openapi');
        echo '<h2>Connect ChatGPT</h2><ol>'
            . '<li>In ChatGPT: <em>Explore GPTs → Create → Configure → Actions → Create new action</em>.</li>'
            . '<li><em>Import from URL</em>: <code>' . esc_html($schema) . '</code></li>'
            . '<li><em>Authentication</em>: API key, auth type <strong>Basic</strong>, and paste the ChatGPT API key shown above.</li>'
            . '<li>Save. The first time the GPT uses the site, choose <strong>Always allow</strong>; after that it does not ask again.</li></ol>'
            . '<p class="description">What it can do: read and write posts and pages, set categories, tags and Rank Math title, description and focus keyword. What it cannot do: log in to wp-admin, change plugins, users or settings. Claude\'s own permission prompts and Novamira\'s approval level are set in those tools, not here.</p>';

        $log = array_reverse(self::log());
        echo '<h2>Recent calls</h2>';
        if (!$log) {
            echo '<p class="description">No calls yet.</p>';
        } else {
            echo '<div class="sgap-scroll"><table class="widefat striped"><thead><tr><th>When</th><th>AI</th><th>Call</th><th>Answer</th></tr></thead><tbody>';
            foreach (array_slice($log, 0, 30) as $e) {
                echo '<tr><td>' . esc_html(self::when((int) ($e['at'] ?? 0))) . '</td><td>' . esc_html(self::CLIENTS[$e['client'] ?? ''] ?? (string) ($e['client'] ?? '')) . '</td>'
                    . '<td><code>' . esc_html((string) ($e['method'] ?? '') . ' ' . (string) ($e['route'] ?? '')) . '</code></td><td>' . esc_html((string) (int) ($e['status'] ?? 0)) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        SGAP_Admin::close();
    }
}

SGAP_AI_Access::init();
