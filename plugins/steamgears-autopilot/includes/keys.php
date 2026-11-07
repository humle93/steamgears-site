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
    /**
     * One entry per service. `keys` lists the settings each card owns; together the cards
     * must cover SGAP_Settings::SECRET_KEYS exactly once (checked by plugins/tests/keys.php).
     */
    const PROVIDERS = [
        'ai' => [
            'title' => 'AI text',
            'icon' => 'superhero-alt',
            'keys' => ['ai_api_key'],
            'body' => 'Writes the posts, the articles and the answers in the chat. One AIMLAPI key covers Claude and 300+ models; an Anthropic key works directly too.',
            'links' => [['AIMLAPI', 'https://aimlapi.com/'], ['Anthropic', 'https://console.anthropic.com/settings/keys']],
            'hint' => 'AIMLAPI: Dashboard → API keys. Anthropic: console.anthropic.com → API keys.',
            'test' => 'ai'
        ],
        'images' => [
            'title' => 'AI images',
            'icon' => 'format-image',
            'keys' => ['image_api_key'],
            'body' => 'Instagram and Pinterest need a photo on every post. Leave the field empty and the AI text key is used instead.',
            'links' => [['AIMLAPI', 'https://aimlapi.com/']],
            'hint' => 'Only needed when images should be billed to a different key than the text.',
            'optional' => true
        ],
        'publer' => [
            'title' => 'Publer',
            'icon' => 'megaphone',
            'keys' => ['publer_api_key'],
            'body' => 'Publishes to Facebook, Instagram, Pinterest and the rest. In RSS mode Publer reads a feed instead, and no key is needed.',
            'links' => [['Publer', 'https://app.publer.com/']],
            'hint' => 'Publer → Settings → API. Needs Publer Business for direct posting.',
            'test' => 'publer'
        ],
        'printful' => [
            'title' => 'Printful',
            'icon' => 'cart',
            'keys' => ['printful_api_key'],
            'body' => 'Reads the synced variants so every product gets its real cost price, and the shop tools can work out a margin that cannot undercut it.',
            'links' => [['Printful', 'https://www.printful.com/dashboard/settings/developers']],
            'hint' => 'Printful → Settings → Developers → API tokens. The store id lives under Shop & feeds.',
            'optional' => true
        ],
        'search' => [
            'title' => 'SerpApi',
            'icon' => 'search',
            'keys' => ['serpapi_key'],
            'body' => 'Optional. Keyword research for articles and the Content Studio, so a guide is written around what people actually search for.',
            'links' => [['SerpApi', 'https://serpapi.com/manage-api-key']],
            'hint' => 'serpapi.com → Dashboard → Your API key.',
            'optional' => true
        ],
        'bluesky' => [
            'title' => 'Bluesky',
            'icon' => 'cloud',
            'keys' => ['bluesky_app_password'],
            'body' => 'Posts to Bluesky through its API. Use an app password — never your main password.',
            'links' => [['Bluesky', 'https://bsky.app/settings/app-passwords']],
            'hint' => 'Bluesky → Settings → App passwords. The handle is set under Publishing.',
            'optional' => true
        ],
        'mastodon' => [
            'title' => 'Mastodon',
            'icon' => 'admin-comments',
            'keys' => ['mastodon_token'],
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
            'body' => 'Reads impressions, saves and outbound clicks for the pins the account owns. Publishing still goes through Publer.',
            'links' => [['Pinterest developers', 'https://developers.pinterest.com/apps/']],
            'hint' => 'The App ID lives under Publishing. Scopes: boards:read and pins:read. The access token lives 30 days; with the app secret and a refresh token it renews itself on every run, and the rotated token is saved here automatically.',
            'optional' => true
        ],
        'instagram' => [
            'title' => 'Instagram insights',
            'icon' => 'instagram',
            'keys' => ['instagram_access_token'],
            'body' => 'Reads views, likes, comments, shares and saves for the posts the account owns. Publishing still goes through Publer.',
            'links' => [['Meta app dashboard', 'https://developers.facebook.com/apps/']],
            'hint' => 'The professional account id and the Graph host live under Publishing. The long-lived token lasts 60 days and refreshes itself on every run with Instagram Login; Facebook Login tokens have to be replaced by hand.',
            'optional' => true
        ],
        'telegram' => [
            'title' => 'Telegram',
            'icon' => 'email-alt',
            'keys' => ['telegram_bot_token'],
            'body' => 'A bot that sends queue notices and failure alerts to your chat.',
            'links' => [['@BotFather', 'https://t.me/BotFather']],
            'hint' => 'Message @BotFather, /newbot, and paste the token it prints.',
            'optional' => true
        ],
        'feed' => [
            'title' => 'Product feed',
            'icon' => 'rss',
            'keys' => ['merchant_feed_secret'],
            'body' => 'A password you make up yourself. The merchant feed stays closed until it is in the URL, so Google and price comparison sites get the products and nobody else does.',
            'links' => [],
            'hint' => 'Any long random text. The feed address is shown under Shop & feeds.',
            'optional' => true
        ],
        'mcp' => [
            'title' => 'MCP access',
            'icon' => 'admin-links',
            'keys' => ['mcp_refresh_token'],
            'body' => 'Lets the site renew its own MCP token so the connection does not die when the hour is up.',
            'links' => [],
            'hint' => 'Copied from the client you approved, or renewed with the button under MCP access.',
            'optional' => true
        ]
    ];

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
        echo '<div class="sgap-keys-top"><div><h2>Keys</h2><p class="sgap-sub">Every key the plugin uses, in one place. Nothing is ever shown again after it is saved — paste a new value to replace it, or leave the field empty to keep what is stored.</p></div>'
            . '<div class="sgap-keys-tally">' . SGAP_UI::pill($tally['saved'] . ' of ' . $tally['total'] . ' saved', $tally['saved'] === $tally['total'] ? 'ok' : 'brass') . '</div></div>';
        echo '<div class="sgap-keys">';
        foreach (self::PROVIDERS as $id => $provider) {
            echo self::card((string) $id, $provider);
        }
        echo '</div>';
        echo '<div class="sgap-key-testbar"><button type="button" class="button" id="sgap-test-keys"><span class="dashicons dashicons-yes-alt"></span>Test the saved keys</button>'
            . '<span class="sgap-key-testout" id="sgap-test-keys-out">'
            . (isset($result['ai']['message']) ? esc_html('AI: ' . $result['ai']['message']) : '')
            . (isset($result['publer']['message']) ? ' ' . esc_html('Publer: ' . $result['publer']['message']) : '')
            . '</span></div>';
        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_setup')];
        echo '<script>(function(){var b=document.getElementById("sgap-test-keys"),o=document.getElementById("sgap-test-keys-out"),C=' . wp_json_encode($cfg) . ';if(!b)return;'
            . 'b.addEventListener("click",function(){var l=b.innerHTML;b.disabled=true;b.textContent="Testing…";var fd=new FormData();fd.append("action","sgap_setup_test");fd.append("nonce",C.nonce);'
            . 'fetch(C.ajax,{method:"POST",credentials:"same-origin",body:fd}).then(function(r){return r.json();}).then(function(j){var d=j&&j.data?j.data:{};'
            . 'var parts=[];["ai","publer"].forEach(function(k){if(d[k])parts.push((k==="ai"?"AI":"Publer")+": "+d[k].message);});'
            . 'o.textContent=parts.join(" ");}).catch(function(){o.textContent="Could not reach the test endpoint.";})'
            . '.then(function(){b.disabled=false;b.innerHTML=l;});});})();</script>';
    }
}
