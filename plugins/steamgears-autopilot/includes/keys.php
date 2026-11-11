<?php
/**
 * The one place keys are pasted.
 *
 * Every secret the plugin uses — AI, images, Publer, Printful, SerpApi, Bluesky, Mastodon,
 * Telegram, the merchant feed and the MCP token — lives on this single screen, one card per
 * service. Nothing else in the plugin renders a key field, so the owner never has to guess
 * which tab a credential was on.
 *
 * The card layout follows the pattern WordPress itself uses for Settings → Connectors
 * (7.0+): each provider is a card with its name, what it is for, where to get it and its
 * status, and the key source (wp-config.php constant or database) is shown, never the key.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Keys {
    /** Where a change is recorded: never the value, only its length, a short hash and the time. */
    const CHANGES = 'sgap_key_changes';
    /** Enough to see a rotation history without turning the option into a log that never ends. */
    const MAX_CHANGES = 120;
    /**
     * What is cached from a key and has to go when it changes: an account list, a model list or a
     * session fetched with the old key would otherwise answer 401 until it expired on its own.
     */
    const CACHE_MAP = [
        'ai_api_key' => ['sgap_ai_catalog_aimlapi', 'sgap_ai_catalog_anthropic', 'sgap_ai_catalog_openai_compatible', 'sgap_ai_catalog_openrouter'],
        // Its own catalogue: a backup list and a provider list must not overwrite each other.
        'openrouter_api_key' => ['sgap_ai_catalog_openrouter_backup'],
        'image_api_key' => ['sgap_ai_catalog_aimlapi'],
        'publer_api_key' => ['sgap_publer_accounts'],
        'publer_workspace_id' => ['sgap_publer_accounts'],
        'printful_api_key' => ['sgap_printful_costs'],
        'printful_store_id' => ['sgap_printful_costs'],
        'bluesky_app_password' => ['sgap_bluesky_session']
    ];
    /** Keys written earlier in this request, so the settings hook cannot log the same write twice. */
    private static array $wrote = [];
    /** Transients the last change cleared, for the screen and for the tests. */
    private static array $flushed = [];
    /**
     * One entry per service. `keys` lists the settings each card owns; together the cards
     * must cover SGAP_Settings::SECRET_KEYS exactly once (checked by plugins/tests/keys.php).
     */
    const PROVIDERS = [
        'ai' => [
            'title' => 'AI text',
            'icon' => 'superhero-alt',
            'keys' => ['ai_api_key'],
            'owner' => 'You — your own OpenRouter or Anthropic account (AIMLAPI is being phased out), paid by you.',
            'why' => 'Without it nothing writes: no posts, no articles, no chat answers and no AI photos.',
            'body' => 'Writes the posts, the articles and the answers in the chat. An OpenRouter key (recommended) puts hundreds of models from many providers — text and images — behind one bill; an Anthropic key works directly too. A saved AIMLAPI key keeps working until you switch.',
            'links' => [['OpenRouter', 'https://openrouter.ai/keys'], ['Anthropic', 'https://console.anthropic.com/settings/keys']],
            'hint' => 'OpenRouter: openrouter.ai/keys, then pick a model under AI & images. Anthropic: console.anthropic.com → API keys.',
            'test' => 'ai'
        ],
        'openrouter_backup' => [
            'title' => 'OpenRouter (backup)',
            'icon' => 'shield',
            'keys' => ['openrouter_api_key'],
            'extra' => ['openrouter_model'],
            'owner' => 'You — your own OpenRouter account, with a second key on purpose: the AI text key belongs to whichever provider is chosen, so a rotation of one must never point the backup at the wrong account.',
            'why' => 'Keeps the autopilot writing when the primary provider runs out of credit, loses its key or is down. Without it a dead primary stops every post, article and chat answer.',
            'body' => 'Used only when the primary provider fails: transport error, 401/403, no credit (402), rate limit (429) or a 5xx. A 2xx answer — even an empty one — never starts it, so a working primary is never billed twice.',
            'links' => [['OpenRouter keys', 'https://openrouter.ai/keys']],
            'hint' => 'openrouter.ai/keys → create a key, paste it here, then pick the backup model in this same card. An empty model means the backup is off, because OpenRouter has no default. While OpenRouter is the provider itself the card is ignored: the backup would be the same service.',
            'test' => 'openrouter_backup',
            'optional' => true
        ],
        'images' => [
            'title' => 'AI images',
            'icon' => 'format-image',
            'keys' => ['image_api_key'],
            'owner' => 'You — an AIMLAPI account (legacy). With an OpenRouter image model set under AI & images, images go through OpenRouter and this key is not needed.',
            'why' => 'Instagram and Pinterest posts need a photo. It exists so image cost can be separated from text cost, not because a second account is required.',
            'body' => 'Instagram and Pinterest need a photo on every post. Set an OpenRouter image model and images use your OpenRouter key instead; this AIMLAPI key is only the old path.',
            'links' => [['AIMLAPI', 'https://aimlapi.com/']],
            'hint' => 'Being phased out: leave it empty once OpenRouter images work.',
            'optional' => true
        ],
        'publer' => [
            'title' => 'Publer',
            'icon' => 'megaphone',
            'keys' => ['publer_api_key'],
            'owner' => 'You — your Publer workspace, where your social accounts are connected.',
            'why' => 'In API mode this is what sends each queued post out. Without it the queue stays on the site and nothing is published.',
            'body' => 'Publishes to Facebook, Instagram, Pinterest and the rest. In RSS mode Publer reads a feed instead, and no key is needed.',
            'links' => [['Publer', 'https://app.publer.com/']],
            'hint' => 'Publer → Settings → API. Needs Publer Business for direct posting.',
            'test' => 'publer'
        ],
        'printful' => [
            'title' => 'Printful',
            'icon' => 'cart',
            'keys' => ['printful_api_key'],
            'owner' => 'You — the Printful account that owns the store the shop syncs from.',
            'why' => 'Reads the real cost price of a synced product, so a price can never be proposed below what the product costs you.',
            'body' => 'Reads the synced variants so every product gets its real cost price, and the shop tools can work out a margin that cannot undercut it.',
            'links' => [['Printful', 'https://www.printful.com/dashboard/settings/developers']],
            'hint' => 'Printful → Settings → Developers → API tokens (scopes: View stores, View sync products). Leave the store id empty and it is filled in when the token is tested and belongs to one store.',
            'extra' => ['printful_store_id'],
            'test' => 'printful',
            'optional' => true
        ],
        'search' => [
            'title' => 'SerpApi',
            'icon' => 'search',
            'keys' => ['serpapi_key'],
            'owner' => 'You — your own SerpApi subscription (the free tier is yours too).',
            'why' => 'Gives the article writer live Google data. Without it guides are still written, just without real search volumes and related questions.',
            'body' => 'Optional. Keyword research for articles and the Content Studio, so a guide is written around what people actually search for.',
            'links' => [['SerpApi', 'https://serpapi.com/manage-api-key']],
            'hint' => 'serpapi.com → Dashboard → Your API key.',
            'optional' => true
        ],
        'bluesky' => [
            'title' => 'Bluesky',
            'icon' => 'cloud',
            'keys' => ['bluesky_app_password'],
            'owner' => 'You — the account behind the @handle set under Publishing, using an app password rather than your login.',
            'why' => 'Posts to Bluesky directly instead of through Publer. Only used once both the handle and this app password are saved.',
            'body' => 'Posts to Bluesky through its API. Use an app password — never your main password.',
            'links' => [['Bluesky', 'https://bsky.app/settings/app-passwords']],
            'hint' => 'Bluesky → Settings → App passwords. The handle is set under Publishing.',
            'optional' => true
        ],
        'mastodon' => [
            'title' => 'Mastodon',
            'icon' => 'admin-comments',
            'keys' => ['mastodon_token'],
            'owner' => 'You — your own account on the instance whose address is set under Publishing.',
            'why' => 'Posts to that instance directly. The token is only valid on the server it was created on, so it travels with the instance address.',
            'body' => 'Posts to your own Mastodon instance. The token needs write access.',
            'links' => [],
            'hint' => 'On your instance: Preferences → Development → New application.',
            'optional' => true,
            'instance_link' => 'mastodon_instance'
        ],
        'pinterest' => [
            'title' => 'Pinterest analytics',
            'icon' => 'chart-line',
            'keys' => ['pinterest_access_token', 'pinterest_refresh_token', 'pinterest_client_secret'],
            'owner' => 'You — your Pinterest developer app, authorised by the account that owns the pins.',
            'why' => 'Reads impressions, saves and outbound clicks for your own pins. Publishing stays with Publer, so the key can never post anything.',
            'body' => 'Reads impressions, saves and outbound clicks for the pins the account owns. Publishing still goes through Publer.',
            'links' => [['Pinterest developers', 'https://developers.pinterest.com/apps/']],
            'hint' => 'The App ID lives under Publishing. Scopes: boards:read and pins:read. The access token lives 30 days; with the app secret and a refresh token it renews itself on every run, and the rotated token is saved here automatically.',
            'optional' => true
        ],
        'instagram' => [
            'title' => 'Instagram insights',
            'icon' => 'instagram',
            'keys' => ['instagram_access_token'],
            'owner' => 'You — the professional Instagram account you manage, reached through your own Meta app.',
            'why' => 'Reads views, likes, comments, shares and saves for your own posts. It publishes nothing; that goes through Publer.',
            'body' => 'Reads views, likes, comments, shares and saves for the posts the account owns. Publishing still goes through Publer.',
            'links' => [['Meta app dashboard', 'https://developers.facebook.com/apps/']],
            'hint' => 'The professional account id and the Graph host live under Publishing. The long-lived token lasts 60 days and refreshes itself on every run with Instagram Login; Facebook Login tokens have to be replaced by hand.',
            'optional' => true
        ],
        'facebook' => [
            'title' => 'Facebook page',
            'icon' => 'facebook-alt',
            'keys' => ['facebook_access_token'],
            'owner' => 'You — the Facebook Page you administer, through your own Meta app.',
            'why' => 'Reads the page and its posts and can answer or hide a comment. Publishing only moves here if you turn that on under Publishing.',
            'body' => 'Reads the page and its posts, and can answer or hide a comment. Publishing goes through Publer unless you switch it on under Publishing.',
            'links' => [['Meta app dashboard', 'https://developers.facebook.com/apps/']],
            'hint' => 'A Page access token, not a user token. The page id and the Graph version live under Publishing. Scopes: pages_show_list, pages_read_engagement, pages_manage_posts, pages_manage_engagement.',
            'optional' => true
        ],
        'reddit' => [
            'title' => 'Reddit',
            'icon' => 'share',
            'keys' => ['reddit_client_secret', 'reddit_password', 'reddit_refresh_token'],
            'owner' => 'You — your own Reddit account and the script app you create at reddit.com/prefs/apps.',
            'why' => 'Publishes one link post per thread to the subreddit you name. The radar only reads; this card is what allows writing.',
            'body' => 'The script app\'s secret, plus either the account password or the refresh token Reddit rotates on every use. The app id and the username are not secrets and live under Forum & Reddit.',
            'links' => [['Reddit apps', 'https://www.reddit.com/prefs/apps']],
            'hint' => 'reddit.com/prefs/apps → create app → type script → redirect uri http://localhost:8080. Save the app id and username under Forum & Reddit; the plugin writes the refresh token here itself.',
            'optional' => true
        ],
        'telegram' => [
            'title' => 'Telegram',
            'icon' => 'email-alt',
            'keys' => ['telegram_bot_token'],
            'owner' => 'You — the bot you created with @BotFather, writing to a chat you control.',
            'why' => 'Sends queue notices and failure alerts to your phone. Without it the plugin fails silently; the dashboard still shows it.',
            'body' => 'A bot that sends queue notices and failure alerts to your chat.',
            'links' => [['@BotFather', 'https://t.me/BotFather']],
            'hint' => 'Message @BotFather, /newbot, and paste the token it prints.',
            'optional' => true
        ],
        'feed' => [
            'title' => 'Product feed',
            'icon' => 'rss',
            'keys' => ['merchant_feed_secret'],
            'owner' => 'You — a password you invent. No service is involved and nobody else holds it.',
            'why' => 'Keeps the product feed closed: Google and price comparison sites get the products, everyone else gets nothing.',
            'body' => 'A password you make up yourself. The merchant feed stays closed until it is in the URL, so Google and price comparison sites get the products and nobody else does.',
            'links' => [],
            'hint' => 'Any long random text. The feed address is shown under Shop & feeds.',
            'optional' => true
        ],
        'mcp' => [
            'title' => 'MCP access',
            'icon' => 'admin-links',
            'keys' => ['mcp_refresh_token'],
            'owner' => 'You — the MCP client you approved for this site; the site renews the token on your behalf.',
            'why' => 'Keeps the MCP connection alive between approvals. When it lapses, the tools cannot be used again until you approve a new client.',
            'body' => 'Lets the site renew its own MCP token so the connection does not die when the hour is up.',
            'links' => [],
            'hint' => 'Copied from the client you approved, or renewed with the button under MCP access.',
            'optional' => true
        ]
    ];

    public static function init(): void {
        // The Settings form and the wizard write through options.php, not through SGAP_Settings::set(),
        // so the change log watches the options themselves; set() records directly for paths the
        // hook cannot see (the abilities, the REST tools, the token renewals).
        foreach (SGAP_Settings::SECRET_KEYS as $key) {
            add_action('update_option_sgap_' . $key, function ($old, $new) use ($key): void {
                self::record((string) $key, (string) $old, (string) $new);
            }, 10, 2);
            add_action('add_option_sgap_' . $key, function ($name, $new) use ($key): void {
                self::record((string) $key, '', (string) $new);
            }, 10, 2);
        }
    }

    /**
     * Records that a key changed, without ever keeping the key: length, a short hash and the time
     * are enough to see that a rotation happened and that it is the same value as before.
     * @return bool true when a new entry was written
     */
    public static function record(string $key, string $old, string $new): bool {
        if ($old === $new || !in_array($key, SGAP_Settings::SECRET_KEYS, true)) {
            return false;
        }
        // A constant in wp-config.php wins over the database, so a write there does not change
        // which key the plugin uses and must not look like a rotation on this screen.
        if (SGAP_Settings::constant_name($key) !== '') {
            return false;
        }
        $stamp = $new === '' ? 'cleared' : self::fingerprint($new);
        if ((self::$wrote[$key] ?? '') === $stamp) {
            unset(self::$wrote[$key]);
            return false;
        }
        self::$wrote[$key] = $stamp;
        $log = self::changes();
        $log[] = [
            'key' => $key,
            'at' => current_time('mysql'),
            'action' => $new === '' ? 'cleared' : ($old === '' ? 'set' : 'changed'),
            'chars' => strlen($new),
            'fingerprint' => self::fingerprint($new),
            'by' => self::actor()
        ];
        if (count($log) > self::MAX_CHANGES) {
            $log = array_slice($log, -self::MAX_CHANGES);
        }
        update_option(self::CHANGES, $log, false);
        delete_option(SGAP_KeyCheck::RESULT);
        self::flush($key);
        return true;
    }

    /** @return array<int,array{key:string,at:string,action:string,chars:int,fingerprint:string,by:string}> */
    public static function changes(): array {
        $log = get_option(self::CHANGES, []);
        return is_array($log) ? array_values(array_filter($log, 'is_array')) : [];
    }

    /** The most recent change for one key, or [] when it has never changed since it was saved. */
    public static function last_change(string $key): array {
        $found = [];
        foreach (self::changes() as $entry) {
            if ((string) ($entry['key'] ?? '') === $key) {
                $found = $entry;
            }
        }
        return $found;
    }

    /** First 8 hex characters of the value's hash: comparable without being the value. */
    public static function fingerprint(string $value): string {
        return $value === '' ? '' : substr(hash('sha256', $value), 0, 8);
    }

    /** Whose change it was, for the history line; never guessed beyond what the request knows. */
    private static function actor(): string {
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if (is_object($user) && !empty($user->user_login)) {
                return (string) $user->user_login;
            }
        }
        return function_exists('wp_doing_cron') && wp_doing_cron() ? 'cron' : 'system';
    }

    /**
     * Drops what was cached from the old value. Runs on every change, because a stale account list
     * or model list is how a rotation turns into a 401 that looks like a broken key.
     * @return string[] the transients that were cleared
     */
    public static function flush(string $key): array {
        $names = self::CACHE_MAP[$key] ?? [];
        foreach ($names as $name) {
            if (function_exists('delete_site_transient')) {
                delete_site_transient($name);
            }
            delete_transient($name);
        }
        delete_transient('sgap_dashboard_stats');
        self::$flushed = $names;
        return $names;
    }

    /** Transients cleared by the last change in this request. */
    public static function flushed(): array {
        return self::$flushed;
    }

    /**
     * Every key with the account it belongs to and why it exists — one row per key, the same text
     * the screen shows. The docs and the screen therefore cannot drift apart.
     * @return array<int,array{key:string,label:string,provider:string,title:string,owner:string,why:string,optional:bool,state:string,source:string}>
     */
    public static function documentation(): array {
        $rows = [];
        foreach (self::PROVIDERS as $id => $provider) {
            $state = self::status((string) $provider['keys'][0]);
            foreach ((array) $provider['keys'] as $key) {
                $rows[] = [
                    'key' => (string) $key,
                    'label' => (string) (SGAP_Setup::LABELS[$key] ?? $key),
                    'provider' => (string) $id,
                    'title' => (string) $provider['title'],
                    'owner' => trim((string) ($provider['owner'] ?? '')),
                    'why' => trim((string) ($provider['why'] ?? '')),
                    'optional' => !empty($provider['optional']),
                    'state' => $state['state'],
                    'source' => $state['state'] === 'empty' ? 'not set' : ($state['state'] === 'constant' ? 'wp-config.php' : 'database')
                ];
            }
        }
        return $rows;
    }

    /** Every key a card owns, in card order. */
    public static function covered(): array {
        $keys = [];
        foreach (self::PROVIDERS as $provider) {
            foreach ((array) $provider['keys'] as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * What is stored for one key. The value itself is never part of this: the screen can say
     * that a key is saved without ever putting it back in the HTML.
     * @return array{state:string,label:string,kind:string,detail:string}
     */
    public static function status(string $key): array {
        if ($const = SGAP_Settings::constant_name($key)) {
            return ['state' => 'constant', 'label' => 'wp-config.php', 'kind' => 'brass', 'detail' => 'Set as ' . $const . ' — edit it there, not here.'];
        }
        if (trim((string) SGAP_Settings::get($key, '')) !== '') {
            return ['state' => 'saved', 'label' => 'Saved', 'kind' => 'ok', 'detail' => 'Stored. Paste a new value to replace it, or leave the field empty to keep it.'];
        }
        return ['state' => 'empty', 'label' => 'Not set', 'kind' => '', 'detail' => ''];
    }

    /** How many keys are saved, for the summary line. */
    public static function tally(): array {
        $saved = 0;
        $total = 0;
        foreach (self::covered() as $key) {
            $total++;
            if (self::status($key)['state'] !== 'empty') {
                $saved++;
            }
        }
        return ['saved' => $saved, 'total' => $total];
    }

    /** The last key test, when there is one. */
    private static function last_test(): array {
        $result = get_option(SGAP_KeyCheck::RESULT);
        return is_array($result) ? $result : [];
    }

    /** One card: service, state, field, and where the key comes from. */
    private static function card(string $id, array $provider): string {
        $keys = (array) $provider['keys'];
        $state = self::status((string) $keys[0]);
        $empty = $state['state'] === 'empty';
        $pill = $empty
            ? ($provider['optional'] ?? false ? SGAP_UI::pill('Optional', '') : SGAP_UI::pill('Needed', 'warn'))
            : SGAP_UI::pill($state['label'], $state['kind']);
        $html = '<section class="sgap-keycard sgap-keycard--' . esc_attr($state['state']) . '">'
            . '<header class="sgap-key-head"><span class="sgap-key-icon"><span class="dashicons dashicons-' . esc_attr($provider['icon']) . '"></span></span>'
            . '<div class="sgap-key-title"><h3>' . esc_html($provider['title']) . '</h3><p>' . esc_html($provider['body']) . '</p></div>'
            . $pill . '</header>';
        $html .= '<div class="sgap-key-body">';
        foreach ($keys as $key) {
            $html .= '<div class="sgap-key-field"><label for="sgap_' . esc_attr($key) . '">' . esc_html(SGAP_Setup::LABELS[$key] ?? $key) . '</label>'
                . SGAP_Setup::field((string) $key) . '</div>';
        }
        foreach ((array) ($provider['extra'] ?? []) as $extra) {
            $html .= '<div class="sgap-key-field"><label for="sgap_' . esc_attr($extra) . '">' . esc_html(SGAP_Setup::LABELS[$extra] ?? $extra) . '</label>'
                . SGAP_Setup::field((string) $extra) . '</div>';
        }
        $test = $provider['test'] ?? '';
        if ($test !== '') {
            $result = self::last_test()[$test] ?? null;
            if (is_array($result) && array_key_exists('ok', $result)) {
                $ok = !empty($result['ok']);
                $html .= '<p class="sgap-key-test">' . SGAP_UI::pill('Last test ' . ($ok ? '✓' : '✗'), $ok ? 'ok' : 'bad')
                    . ' <span>' . esc_html((string) ($result['message'] ?? '')) . '</span></p>';
            }
        }
        $links = [];
        foreach ((array) ($provider['links'] ?? []) as [$label, $url]) {
            $links[] = '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($label) . ' <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
        }
        if (isset($provider['instance_link'])) {
            $instance = trim((string) SGAP_Settings::get((string) $provider['instance_link'], ''));
            if ($instance !== '') {
                $host = preg_replace('#^https?://#', '', $instance);
                $links[] = '<a href="' . esc_url('https://' . $host . '/settings/applications') . '" target="_blank" rel="noopener">Your instance <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
            }
        }
        $owner = trim((string) ($provider['owner'] ?? ''));
        $why = trim((string) ($provider['why'] ?? ''));
        if ($owner !== '' || $why !== '') {
            $html .= '<dl class="sgap-key-owner">'
                . ($owner !== '' ? '<dt>Belongs to</dt><dd>' . esc_html($owner) . '</dd>' : '')
                . ($why !== '' ? '<dt>Why it is here</dt><dd>' . esc_html($why) . '</dd>' : '')
                . '</dl>';
        }
        // A card can own several fields (Pinterest has three): show the newest change of them.
        $change = [];
        foreach ($keys as $owned) {
            $entry = self::last_change((string) $owned);
            if ($entry !== [] && (string) ($entry['at'] ?? '') >= (string) ($change['at'] ?? '')) {
                $change = $entry;
            }
        }
        if ($change !== []) {
            $when = (string) ($change['at'] ?? '');
            $history = ($change['action'] ?? '') === 'cleared'
                ? 'Removed ' . $when
                : 'Changed ' . $when . ' · ' . (int) ($change['chars'] ?? 0) . ' characters · fingerprint ' . (string) ($change['fingerprint'] ?? '');
            $html .= '<p class="sgap-key-history">' . SGAP_UI::pill('Key history', 'brass')
                . ' <span>' . esc_html($history . '. The fingerprint is a hash, never the key itself.') . '</span></p>';
        }
        $detail = $state['detail'] !== '' ? $state['detail'] : (string) ($provider['hint'] ?? '');
        if ($detail !== '' || $links) {
            $html .= '<footer class="sgap-key-foot"><p>' . esc_html($detail) . '</p>'
                . ($links ? '<nav class="sgap-key-links">' . implode('', $links) . '</nav>' : '') . '</footer>';
        }
        return $html . '</div></section>';
    }

    /** The whole panel, rendered inside the settings form so options.php saves it as usual. */
    public static function panel(): void {
        $tally = self::tally();
        $result = self::last_test();
        echo '<div class="sgap-keys-top"><div><h2>Keys</h2><p class="sgap-sub">Every key the plugin uses, in one place. Each card says whose account it is and what stops working without it. Nothing is ever shown again after it is saved — paste a new value to replace it, or leave the field empty to keep what is stored.</p></div>'
            . '<div class="sgap-keys-tally">' . SGAP_UI::pill($tally['saved'] . ' of ' . $tally['total'] . ' saved', $tally['saved'] === $tally['total'] ? 'ok' : 'brass') . '</div></div>';
        echo '<div class="sgap-keys">';
        foreach (self::PROVIDERS as $id => $provider) {
            echo self::card((string) $id, $provider);
        }
        echo '</div>';
        echo '<details class="sgap-key-owners"><summary>Who owns each key — and why it is here</summary>'
            . '<p class="sgap-sub">Every account below is yours: the plugin never creates an account, never borrows one and never shares one between sites. A key is stored on this site only — in the database, or in <code>wp-config.php</code> when you prefer that — and the screen can tell you that it is saved without ever showing it again.</p>'
            . '<table class="widefat striped"><thead><tr><th>Key</th><th>Belongs to</th><th>Why it is here</th><th>Required</th></tr></thead><tbody>';
        foreach (self::documentation() as $row) {
            echo '<tr class="sgap-key-owner-row"><td><strong>' . esc_html($row['label']) . '</strong><br><code>' . esc_html($row['key']) . '</code></td>'
                . '<td>' . esc_html($row['owner']) . '</td><td>' . esc_html($row['why']) . '</td>'
                . '<td>' . ($row['optional'] ? 'Optional' : 'Yes') . '</td></tr>';
        }
        echo '</tbody></table></details>';
        echo '<div class="sgap-key-testbar"><button type="button" class="button" id="sgap-test-keys"><span class="dashicons dashicons-yes-alt"></span>Test connections</button>'
            . '<span class="sgap-key-testout" id="sgap-test-keys-out">'
            . (isset($result['ai']['message']) ? esc_html('AI: ' . $result['ai']['message']) : '')
            . (isset($result['publer']['message']) ? ' ' . esc_html('Publer: ' . $result['publer']['message']) : '')
            . (isset($result['printful']['message']) ? ' ' . esc_html('Printful: ' . $result['printful']['message']) : '')
            . (isset($result['fallback']['message']) ? ' ' . esc_html('OpenRouter backup: ' . $result['fallback']['message']) : '')
            . '</span></div>';
        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_setup')];
        // "Test connections" ligger i assets/pages/setup.js sammen med resten af siden.
    }
}
