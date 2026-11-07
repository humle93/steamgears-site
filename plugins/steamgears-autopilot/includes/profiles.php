<?php
/**
 * P3.3 — AI profiles for the admin chat: who the assistant is, what it may touch,
 * and the one switch that turns on "access all".
 *
 *   assistant      the autopilot's own assistant (default) — the chat's EXCLUDED list applies
 *   site_admin     site administration — nothing excluded, and AI Engine's Dynamic REST
 *                  plus Abilities are switched on, so the whole REST surface is reachable
 *   wp_seo_expert  WordPress SEO — content, SEO fields, products and media; no ops
 *
 * The profile is both a setting (Settings → AI content) and an ability
 * (`set-ai-profile`), so the owner can switch it by hand and the AI can switch it when
 * asked. Switching to site_admin is what activates access all; the switch is never
 * turned on as a side effect of a normal settings save.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Profiles {
    /** Stored as sgap_ai_profile through SGAP_Settings. */
    const OPTION = 'ai_profile';
    const DEFAULT_ID = 'assistant';

    /**
     * Tools that stay out of the chat in every profile, including access all: turning
     * the site's own access level on or off is an owner decision on the Settings page,
     * and `set-ai-profile` already performs the activation that belongs to a profile.
     */
    const ALWAYS_EXCLUDED = ['enable-full-access'];

    /** id => [label, summary, access, boost, extra excluded, prompt, note]. */
    const PROFILES = [
        'assistant' => [
            'label' => 'Autopilot assistant',
            'summary' => 'Drafts, queue and results. Cannot change the site outside the autopilot.',
            'access' => 'limited',
            'boost' => ['steamgears-autopilot', 'social'],
            'extra' => [],
            'prompt' => '',
            'note' => 'You cannot change this plugin\'s settings yourself, and you must never guess at a model name for the AI provider: '
                . 'name the setting and the new value and let the owner write it on the Settings page.'
        ],
        'site_admin' => [
            'label' => 'Site administration',
            'summary' => 'Full access: posts, products, options, plugins, cron — and AI Engine full REST access is switched on.',
            'access' => 'all',
            'boost' => ['steamgears-autopilot', 'steamgears-tools', 'ops', 'content', 'commerce'],
            'extra' => [],
            'prompt' => 'You are also the site administrator of steamgears.eu: WordPress, WooCommerce, Rank Math and the page cache. '
                . 'Diagnose before you change, use the narrowest tool that does the job, and read a value back after writing it. '
                . 'Name the undo path before anything is deleted or deactivated.',
            'note' => 'You have full access on this site: the tools can change posts, products, options, plugin settings, plugins and cron, '
                . 'and every change is live on the shop immediately. Act only on what the owner asked for in this conversation, '
                . 'never delete without saying what disappears, and prefer a reversible step over an irreversible one.'
        ],
        'wp_seo_expert' => [
            'label' => 'WordPress SEO expert',
            'summary' => 'Technical and on-page SEO: titles, descriptions, schema, internal links, content. No ops.',
            'access' => 'limited',
            'boost' => ['seo', 'content', 'commerce', 'steamgears-tools'],
            'extra' => [],
            'prompt' => 'You are also a WordPress SEO expert for a small print-on-demand shop: technical SEO (indexing, canonicals, '
                . 'schema/JSON-LD, sitemaps, Core Web Vitals), on-page work (title, meta description 140–160 characters, one H1, '
                . 'internal links that earn their place) and E-E-A-T content that answers real search intent. '
                . 'One focus keyword per page, no keyword stuffing, and measure before and after instead of assuming.',
            'note' => 'You may read everything and change content, SEO fields, products, media text and the publishing queue. '
                . 'You may not install or deactivate plugins, change this plugin\'s settings or run maintenance; say what you would '
                . 'change there and let the owner do it.'
        ]
    ];

    /** id => profile, with the label and access fields filled in. */
    public static function all(): array {
        $out = [];
        foreach (self::PROFILES as $id => $profile) {
            $out[$id] = $profile + ['id' => $id];
        }
        return $out;
    }

    /** An unknown or empty id falls back to the default, never to access all. */
    public static function valid(string $id): string {
        return isset(self::PROFILES[$id]) ? $id : self::DEFAULT_ID;
    }

    public static function get(string $id): array {
        $id = self::valid($id);
        return self::PROFILES[$id] + ['id' => $id];
    }

    public static function id(): string {
        $stored = class_exists('SGAP_Settings') ? (string) SGAP_Settings::get(self::OPTION, self::DEFAULT_ID) : self::DEFAULT_ID;
        return self::valid($stored);
    }

    public static function current(): array {
        return self::get(self::id());
    }

    public static function access(): string {
        return self::current()['access'];
    }

    /** True when the profile reaches the whole site surface (and full REST is on). */
    public static function is_full(): bool {
        return self::access() === 'all';
    }

    /** Short names the chat may not run under the current profile. */
    public static function excluded(): array {
        $profile = self::current();
        $base = $profile['access'] === 'all' ? [] : self::base_excluded();
        $extra = is_array($profile['extra'] ?? null) ? $profile['extra'] : [];
        return array_values(array_unique(array_merge($base, $extra, self::ALWAYS_EXCLUDED)));
    }

    /** The chat's own list, so the baseline lives in one place. */
    private static function base_excluded(): array {
        return class_exists('SGAP_Chat') ? SGAP_Chat::EXCLUDED : [];
    }

    /** Categories ranked first in the bound tool list for this profile. */
    public static function boost(): array {
        $boost = self::current()['boost'] ?? [];
        return is_array($boost) ? array_values(array_map('strval', $boost)) : [];
    }

    /** The persona sentence prepended to the chat's system prompt. */
    public static function prompt(): string {
        return trim((string) (self::current()['prompt'] ?? ''));
    }

    /** What the assistant may change, in the profile's own words. */
    public static function note(): string {
        $note = trim((string) (self::current()['note'] ?? ''));
        return $note !== '' ? $note
            : 'The site tools can read posts, products, media, plugins, cron and options (never credentials) — reading is always '
                . 'allowed; changing a post, a product or an option happens on the site\'s MCP surface, not here, so say what you '
                . 'would change and let the owner do it.';
    }

    /** Label + access for the dashboard, without loading the whole profile. */
    public static function describe(): array {
        $profile = self::current();
        return [
            'id' => $profile['id'],
            'label' => $profile['label'],
            'access' => $profile['access'],
            'summary' => $profile['summary'],
            'excluded' => self::excluded()
        ];
    }

    /**
     * Switches the profile and, for site_admin, turns on AI Engine's full REST access.
     * @return array|WP_Error
     */
    public static function activate(string $id) {
        if (!isset(self::PROFILES[$id])) {
            return new WP_Error('sgap_unknown_profile', 'Unknown profile. Use one of: ' . implode(', ', array_keys(self::PROFILES)) . '.');
        }
        $before = self::id();
        SGAP_Settings::set(self::OPTION, $id);
        $profile = self::get($id);
        $full = null;
        if ($profile['access'] === 'all') {
            if (!class_exists('SGAP_AdminTools') || !method_exists('SGAP_AdminTools', 'enable_full_access')) {
                $full = ['error' => 'The admin tools are not loaded, so full REST access could not be switched on.'];
            } else {
                $result = SGAP_AdminTools::enable_full_access(['enabled' => true]);
                $full = is_wp_error($result) ? ['error' => $result->get_error_message()] : $result;
            }
        }
        return [
            'before' => $before,
            'profile' => $id,
            'label' => $profile['label'],
            'access' => $profile['access'],
            'full_access' => $full,
            'note' => $full && empty($full['error'])
                ? 'Reconnect the MCP client (start a new conversation) to see the tools the new access level adds.'
                : 'The profile is set. Full REST access is unchanged.'
        ];
    }
}
