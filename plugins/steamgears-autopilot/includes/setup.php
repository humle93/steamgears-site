<?php
/**
 * Setup wizard, the tabbed Settings page and the "Get started" checklist.
 * Patterns from WooCommerce/WPForms/Rank Math onboarding: one decision per step,
 * choice cards and switches, an inline key test, and a checklist that lets you
 * finish later. Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Setup {
    const STATE = 'sgap_setup_state';

    /** Friendly labels for setting keys. */
    const LABELS = [
        'autopilot_enabled' => 'Autopilot', 'full_auto_enabled' => 'Full auto (skip approval)', 'channels' => 'Channels',
        'posts_per_day' => 'Pages to promote per day', 'ai_provider' => 'AI provider', 'ai_api_key' => 'AI API key', 'ai_model' => 'AI model',
        'ai_base_url' => 'API base URL', 'content_language' => 'Article language', 'image_model' => 'Image model', 'image_api_key' => 'AIMLAPI key for images',
        'serpapi_key' => 'SerpApi key (keyword research)', 'publish_mode' => 'How posts reach Publer', 'publer_api_key' => 'Publer API key',
        'publer_workspace_id' => 'Publer workspace', 'publer_pinterest_board' => 'Pinterest board ID', 'daily_cap' => 'Max posts per day',
        'batch_size' => 'Posts per 15-minute run', 'quiet_start' => 'Quiet from', 'quiet_end' => 'Quiet until', 'labor_rate' => 'Your hourly rate',
        'monthly_budget' => 'Monthly budget', 'target_roi' => 'Target ROI %', 'winner_min_clicks' => 'Winner after clicks', 'loser_min_clicks' => 'Loser after clicks',
        'repost_cooldown_days' => 'Days before a page is promoted again', 'repost_max' => 'Max reposts', 'merchant_feed_secret' => 'Product feed secret',
        'etsy_shop_id' => 'Etsy shop ID', 'etsy_oauth_token' => 'Etsy OAuth token', 'etsy_refresh_token' => 'Etsy refresh token',
        'etsy_shipping_profile_id' => 'Etsy shipping profile', 'etsy_return_policy_id' => 'Etsy return policy',
        'auto_images' => 'AI photos for Instagram & Pinterest', 'auto_seo' => 'SEO meta for new posts & products', 'articles_per_week' => 'SEO articles per week',
        'auto_publish_articles' => 'Publish weekly articles automatically', 'weekly_report' => 'Weekly e-mail report', 'rescue_hosts' => 'Copy images from these servers'
    ];

    /** Settings page tabs: id => [label, icon, keys]. */
    const TABS = [
        'basics' => ['Basics', 'admin-home', ['autopilot_enabled', 'full_auto_enabled', 'channels', 'posts_per_day']],
        'ai' => ['AI & images', 'superhero-alt', ['ai_provider', 'ai_api_key', 'ai_model', 'ai_base_url', 'content_language', 'image_model', 'image_api_key', 'serpapi_key']],
        'publishing' => ['Publishing', 'megaphone', ['publish_mode', 'publer_api_key', 'publer_workspace_id', 'publer_pinterest_board']],
        'schedule' => ['Schedule & limits', 'clock', ['quiet_start', 'quiet_end', 'daily_cap', 'batch_size', 'repost_cooldown_days']],
        'shop' => ['Shop & feeds', 'cart', ['merchant_feed_secret', 'etsy_shop_id', 'etsy_oauth_token', 'etsy_refresh_token', 'etsy_shipping_profile_id', 'etsy_return_policy_id']],
        'automation' => ['Automation', 'update-alt', ['auto_images', 'auto_seo', 'articles_per_week', 'auto_publish_articles', 'weekly_report', 'rescue_hosts']],
        'advanced' => ['Economics', 'chart-line', ['labor_rate', 'monthly_budget', 'target_roi', 'winner_min_clicks', 'loser_min_clicks', 'repost_max']]
    ];

    const CHANNEL_LABELS = [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'pinterest' => 'Pinterest', 'linkedin' => 'LinkedIn', 'twitter' => 'X / Twitter',
        'threads' => 'Threads', 'bluesky' => 'Bluesky', 'mastodon' => 'Mastodon', 'telegram' => 'Telegram', 'google' => 'Google Business'
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 19);
        add_action('wp_ajax_sgap_setup_save', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_sgap_setup_test', [__CLASS__, 'ajax_test']);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'Setup', self::complete() ? 'Setup' : 'Setup <span class="awaiting-mod">!</span>', 'manage_options', 'sgap-setup', [__CLASS__, 'wizard'], 0);
    }

    public static function state(): array {
        $s = get_option(self::STATE);
        return is_array($s) ? $s + ['feeds_done' => [], 'finished' => false] : ['feeds_done' => [], 'finished' => false];
    }

    /* ---- checklist ---- */

    /** id => [done, title, how, link]. */
    public static function checklist(): array {
        global $wpdb;
        $keys = get_option(SGAP_KeyCheck::RESULT);
        $ai_ok = is_array($keys) && !empty($keys['ai']['ok']);
        $channels = SGAP_Publer::channels();
        $state = self::state();
        $feeds_ok = SGAP_RSS::enabled() ? ($channels && !array_diff($channels, (array) $state['feeds_done'])) : SGAP_Publer::configured();
        $drafts = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts");
        $published = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_queue WHERE status = 'published'");
        $setup = admin_url('admin.php?page=sgap-setup');
        return [
            'ai' => [$ai_ok, 'Connect the AI', 'Add your AIMLAPI or Claude key and test it.', $setup . '#step-ai'],
            'channels' => [(bool) $channels, 'Choose your channels', 'Where should posts go?', $setup . '#step-channels'],
            'publer' => [$feeds_ok, SGAP_RSS::enabled() ? 'Add the feeds in Publer' : 'Connect Publer', SGAP_RSS::enabled() ? 'One RSS feed per channel, set to Auto-post.' : 'Publer API key and workspace.', $setup . '#step-publer'],
            'autopilot' => [SGAP_Settings::enabled('autopilot_enabled'), 'Switch the autopilot on', 'Daily writing and publishing.', $setup . '#step-schedule'],
            'drafts' => [$drafts > 0, 'Generate your first posts', 'Let the AI write drafts for your pages.', admin_url('admin.php?page=steamgears-autopilot')],
            'published' => [$published > 0, 'Approve and publish one', 'Approve a draft and release the queue.', admin_url('admin.php?page=sgap-ai')]
        ];
    }

    public static function complete(): bool {
        foreach (self::checklist() as [$done]) {
            if (!$done) {
                return false;
            }
        }
        return true;
    }

    /** Dashboard card; hidden once everything is done. */
    public static function checklist_card(): void {
        $items = self::checklist();
        $done = count(array_filter($items, fn($i) => $i[0]));
        if ($done === count($items)) {
            return;
        }
        echo '<div class="sgap-card sgap-checklist"><div class="sgap-check-head"><h2><span class="dashicons dashicons-flag"></span>Get started</h2><span class="sgap-pill brass">'
            . esc_html("{$done} of " . count($items) . ' done') . '</span></div>'
            . '<div class="sgap-bar"><span style="width:' . (int) round(100 * $done / count($items)) . '%"></span></div><ol class="sgap-steps">';
        foreach ($items as [$ok, $title, $how, $link]) {
            echo '<li class="' . ($ok ? 'done' : '') . '"><span class="sgap-dot">' . ($ok ? '✓' : '') . '</span><div><strong>' . esc_html($title) . '</strong><span>' . esc_html($how) . '</span></div>'
                . ($ok ? '' : '<a class="button button-small" href="' . esc_url($link) . '">Start</a>') . '</li>';
        }
        echo '</ol></div>';
    }

    /* ---- ajax ---- */

    private static function guard(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_setup', 'nonce');
    }

    public static function ajax_save(): void {
        self::guard();
        $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        $state = self::state();
        if (isset($_POST['feeds_done'])) {
            $state['feeds_done'] = array_values(array_intersect(SGAP_Publer::CHANNELS, array_map('sanitize_key', (array) wp_unslash($_POST['feeds_done']))));
        }
        if (!empty($_POST['finish'])) {
            $state['finished'] = true;
        }
        update_option(self::STATE, $state, false);
        // Empty secret fields mean "keep what is saved".
        foreach (SGAP_Settings::SECRET_KEYS as $secret) {
            if (array_key_exists($secret, $settings) && trim((string) $settings[$secret]) === '') {
                unset($settings[$secret]);
            }
        }
        $result = $settings ? SGAP_AdminTools::update_settings(['settings' => $settings]) : ['changed' => []];
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        delete_transient('sgap_dashboard_stats');
        $done = 0;
        foreach (self::checklist() as [$ok]) {
            $done += $ok ? 1 : 0;
        }
        wp_send_json_success(['changed' => $result['changed'], 'done' => $done]);
    }

    public static function ajax_test(): void {
        self::guard();
        $r = SGAP_KeyCheck::run();
        wp_send_json_success(['ai' => $r['ai'], 'publer' => $r['publer']]);
    }

    /* ---- fields ---- */

    public static function switch_field(string $name, bool $on, string $label = ''): string {
        return '<label class="sgap-switch"><input type="hidden" name="' . esc_attr($name) . '" value="no"><input type="checkbox" name="' . esc_attr($name) . '" value="yes"' . checked($on, true, false) . '>'
            . '<span class="sgap-slider" aria-hidden="true"></span>' . ($label !== '' ? '<span class="sgap-switch-label">' . esc_html($label) . '</span>' : '') . '</label>';
    }

    public static function channels_field(string $name): string {
        $current = SGAP_Publer::channels();
        $html = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(implode(',', $current)) . '" class="sgap-channels-value"><div class="sgap-channel-grid">';
        foreach (self::CHANNEL_LABELS as $key => $label) {
            $html .= '<label class="sgap-channel-card' . (in_array($key, $current, true) ? ' on' : '') . '"><input type="checkbox" value="' . esc_attr($key) . '"' . checked(in_array($key, $current, true), true, false) . '>'
                . '<span class="dashicons dashicons-' . esc_attr(SGAP_UI::ICONS[$key] ?? 'share') . '"></span><span>' . esc_html($label) . '</span>'
                . (in_array($key, SGAP_Publer::NEEDS_PHOTO, true) ? '<small>needs a photo</small>' : '') . '</label>';
        }
        return $html . '</div>';
    }

    public static function hour_field(string $name, int $value): string {
        $html = '<select name="' . esc_attr($name) . '">';
        for ($h = 0; $h < 24; $h++) {
            $html .= '<option value="' . $h . '"' . selected($value, $h, false) . '>' . sprintf('%02d:00', $h) . '</option>';
        }
        return $html . '</select>';
    }

    /** One settings field, named for options.php (sgap_<key>) or for the wizard (settings[<key>]). */
    public static function field(string $key, bool $wizard = false): string {
        $name = $wizard ? "settings[{$key}]" : 'sgap_' . $key;
        $id = 'sgap_' . $key;
        if (in_array($key, SGAP_Settings::BOOL_KEYS, true)) {
            return self::switch_field($name, SGAP_Settings::enabled($key));
        }
        if ($key === 'channels') {
            return self::channels_field($name);
        }
        if (in_array($key, SGAP_Settings::HOUR_KEYS, true)) {
            return self::hour_field($name, (int) SGAP_Settings::get($key));
        }
        if (isset(SGAP_Settings::CHOICE_KEYS[$key])) {
            $current = (string) SGAP_Settings::get($key);
            $html = '<div class="sgap-choice-row">';
            foreach (SGAP_Settings::CHOICE_KEYS[$key] as $value => $label) {
                $html .= '<label class="sgap-choice' . ($current === (string) $value ? ' on' : '') . '"><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . checked($current, (string) $value, false) . '><span>' . esc_html($label) . '</span></label>';
            }
            return $html . '</div>';
        }
        if (in_array($key, SGAP_Settings::SECRET_KEYS, true)) {
            if ($const = SGAP_Settings::constant_name($key)) {
                return '<em>Set in wp-config.php (<code>' . esc_html($const) . '</code>)</em>';
            }
            $saved = get_option('sgap_' . $key, '') !== '';
            $html = '<div class="sgap-secret"><input type="password" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="" autocomplete="new-password" placeholder="' . esc_attr($saved ? '•••••••• saved – leave empty to keep' : 'Paste the key') . '">'
                . ($saved ? '<span class="sgap-pill ok">Saved</span>' : '') . '</div>';
            if ($saved && !$wizard) {
                $html .= '<label class="sgap-clear"><input type="checkbox" name="sgap_clear[' . esc_attr($key) . ']" value="1"> Remove saved key</label>';
            }
            return $html;
        }
        $number = in_array($key, SGAP_Settings::INT_KEYS, true);
        return '<input type="' . ($number ? 'number' : 'text') . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) SGAP_Settings::get($key)) . '"'
            . ($number ? ' min="0" class="sgap-num"' : ' class="sgap-text"') . '>';
    }

    private static function row(string $key, bool $wizard = false): string {
        $label = self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
        $help = SGAP_Settings::HELP[$key] ?? '';
        return '<div class="sgap-field"><div class="sgap-field-label"><label for="sgap_' . esc_attr($key) . '">' . esc_html($label) . '</label>'
            . ($help !== '' ? '<p>' . esc_html($help) . '</p>' : '') . '</div><div class="sgap-field-input">' . self::field($key, $wizard) . '</div></div>';
    }

    /** Shared JS: switches, channel cards, choice cards. */
    private static function field_js(): string {
        return '<script>(function(){document.querySelectorAll(".sgap-channel-grid").forEach(function(g){var v=g.parentNode.querySelector(".sgap-channels-value");'
            . 'g.addEventListener("change",function(){var on=[];g.querySelectorAll("input:checked").forEach(function(i){on.push(i.value);});v.value=on.join(",");'
            . 'g.querySelectorAll(".sgap-channel-card").forEach(function(c){c.classList.toggle("on",c.querySelector("input").checked);});});});'
            . 'document.querySelectorAll(".sgap-choice-row").forEach(function(r){r.addEventListener("change",function(){r.querySelectorAll(".sgap-choice").forEach(function(c){c.classList.toggle("on",c.querySelector("input").checked);});'
            . 'var f=r.closest("form");if(f)f.dispatchEvent(new Event("sgap-choice"));});});})();</script>';
    }

    /* ---- Settings page ---- */

    public static function settings_page(): void {
        SGAP_Admin::open('Settings', 'Everything in one place. New here? Use the setup guide instead.');
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }
        echo '<form method="post" action="options.php" class="sgap-settings">';
        settings_fields('sgap_settings');
        echo '<div class="sgap-tabs" role="tablist">';
        $first = true;
        foreach (self::TABS as $id => [$label, $icon]) {
            echo '<button type="button" role="tab" class="sgap-tab' . ($first ? ' on' : '') . '" data-tab="' . esc_attr($id) . '"><span class="dashicons dashicons-' . esc_attr($icon) . '"></span>' . esc_html($label) . '</button>';
            $first = false;
        }
        echo '<a class="sgap-tab sgap-tab-link" href="' . esc_url(admin_url('admin.php?page=sgap-setup')) . '"><span class="dashicons dashicons-welcome-learn-more"></span>Setup guide</a></div>';
        $first = true;
        $shown = [];
        foreach (self::TABS as $id => [$label, $icon, $keys]) {
            echo '<div class="sgap-card sgap-panel" data-panel="' . esc_attr($id) . '"' . ($first ? '' : ' hidden') . '>';
            foreach ($keys as $key) {
                echo self::row($key);
                $shown[] = $key;
            }
            echo '</div>';
            $first = false;
        }
        // Registered settings not on a tab keep their values (options.php only saves fields it receives).
        echo '<div class="sgap-savebar"><span class="description">Changes are saved for all tabs at once.</span>';
        submit_button('Save settings', 'primary', 'submit', false);
        echo '</div></form>';
        echo self::field_js();
        echo '<script>(function(){var tabs=document.querySelectorAll(".sgap-tab[data-tab]"),panels=document.querySelectorAll(".sgap-panel");'
            . 'function show(id){tabs.forEach(function(t){t.classList.toggle("on",t.dataset.tab===id);});panels.forEach(function(p){p.hidden=p.dataset.panel!==id;});try{localStorage.setItem("sgapTab",id);}catch(e){}}'
            . 'tabs.forEach(function(t){t.addEventListener("click",function(){show(t.dataset.tab);});});'
            . 'var h=location.hash.replace("#","");var saved="";try{saved=localStorage.getItem("sgapTab")||"";}catch(e){}'
            . 'if(h&&document.querySelector("[data-panel=\'"+h+"\']"))show(h);else if(saved&&document.querySelector("[data-panel=\'"+saved+"\']"))show(saved);})();</script>';
        SGAP_Admin::close();
    }

    /* ---- Wizard ---- */

    public static function wizard(): void {
        SGAP_Admin::open('Setup guide', 'Five short steps. Everything can be changed later under Settings.');
        $state = self::state();
        $keys = get_option(SGAP_KeyCheck::RESULT);
        $steps = [
            'ai' => ['Connect the AI', 'superhero-alt'],
            'channels' => ['Channels', 'share'],
            'publer' => ['Publer', 'megaphone'],
            'schedule' => ['Schedule', 'clock'],
            'extras' => ['Extras', 'admin-plugins'],
            'done' => ['Done', 'yes-alt']
        ];
        echo '<div class="sgap-wizard"><ol class="sgap-rail">';
        $n = 1;
        foreach ($steps as $id => [$label, $icon]) {
            echo '<li data-step="' . esc_attr($id) . '"><span class="sgap-num">' . $n++ . '</span><span>' . esc_html($label) . '</span></li>';
        }
        echo '</ol><div class="sgap-wizard-body">';

        // 1 AI
        $provider = SGAP_AI::provider();
        echo '<form class="sgap-card sgap-step" data-step="ai"><h2>Which AI should write for you?</h2><p class="sgap-sub">The AI writes your social posts, articles and answers in the chat.</p><div class="sgap-choice-row sgap-big">';
        foreach (['aimlapi' => ['AIMLAPI', 'Recommended. Claude and 300+ models plus images with one key. Pay as you go.'], 'anthropic' => ['Anthropic (Claude)', 'Directly from Anthropic. Text only – images need an AIMLAPI key.'], 'openai_compatible' => ['Other', 'OpenAI, OpenRouter or any OpenAI-compatible API.']] as $value => [$title, $text]) {
            echo '<label class="sgap-choice' . ($provider === $value ? ' on' : '') . '"><input type="radio" name="settings[ai_provider]" value="' . esc_attr($value) . '"' . checked($provider, $value, false) . '><span><strong>' . esc_html($title) . '</strong><small>' . esc_html($text) . '</small></span></label>';
        }
        echo '</div>' . self::row('ai_api_key', true)
            . '<details class="sgap-more"><summary>Model and address (optional)</summary>' . self::row('ai_model', true) . self::row('ai_base_url', true) . '</details>'
            . '<div class="sgap-test-result">' . (is_array($keys) && isset($keys['ai']) ? SGAP_UI::pill('AI ' . ($keys['ai']['ok'] ? '✓' : '✗'), $keys['ai']['ok'] ? 'ok' : 'bad') . ' <span>' . esc_html($keys['ai']['message']) . '</span>' : '') . '</div>'
            . '<div class="sgap-step-nav"><span></span><button class="button button-primary" data-next>Save, test &amp; continue</button></div></form>';

        // 2 Channels
        echo '<form class="sgap-card sgap-step" data-step="channels" hidden><h2>Where should your posts go?</h2><p class="sgap-sub">Pick the networks you use. Instagram and Pinterest need a photo on each post – the AI can make one.</p>'
            . self::channels_field('settings[channels]')
            . '<div class="sgap-step-nav"><button type="button" class="button" data-back>Back</button><button class="button button-primary" data-next>Continue</button></div></form>';

        // 3 Publer
        $mode = SGAP_RSS::enabled() ? 'rss' : 'api';
        echo '<form class="sgap-card sgap-step" data-step="publer" hidden><h2>How do posts reach Publer?</h2><div class="sgap-choice-row sgap-big">'
            . '<label class="sgap-choice' . ($mode === 'rss' ? ' on' : '') . '"><input type="radio" name="settings[publish_mode]" value="rss"' . checked($mode, 'rss', false) . '><span><strong>RSS feeds</strong><small>Publer Professional. One feed per channel that Publer auto-posts from.</small></span></label>'
            . '<label class="sgap-choice' . ($mode === 'api' ? ' on' : '') . '"><input type="radio" name="settings[publish_mode]" value="api"' . checked($mode, 'api', false) . '><span><strong>Publer API</strong><small>Publer Business. Posts are sent straight to Publer.</small></span></label></div>'
            . '<div class="sgap-mode" data-mode="rss"><ol class="sgap-howto"><li>Open Publer → <em>Explore → RSS Feeds → Add feed</em>.</li><li>Paste a feed below, open its <em>Settings</em>, turn on <strong>Auto-post</strong> and pick the account.</li><li>Tick it here when done.</li></ol><div class="sgap-feedlist">';
        foreach (SGAP_Publer::CHANNELS as $channel) {
            $url = SGAP_RSS::url($channel);
            echo '<div class="sgap-feed" data-channel="' . esc_attr($channel) . '">' . SGAP_UI::channel($channel) . '<code>' . esc_html($url) . '</code>'
                . '<button type="button" class="button button-small sgap-copy" data-url="' . esc_attr($url) . '"><span class="dashicons dashicons-admin-page"></span>Copy</button>'
                . '<label class="sgap-done-box"><input type="checkbox" name="feeds_done[]" value="' . esc_attr($channel) . '"' . checked(in_array($channel, (array) $state['feeds_done'], true), true, false) . '> Added</label></div>';
        }
        echo '</div><p><a class="button" href="https://app.publer.com/" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span>Open Publer</a></p></div>'
            . '<div class="sgap-mode" data-mode="api" hidden>' . self::row('publer_api_key', true) . self::row('publer_workspace_id', true) . self::row('publer_pinterest_board', true) . '</div>'
            . '<div class="sgap-step-nav"><button type="button" class="button" data-back>Back</button><button class="button button-primary" data-next>Continue</button></div></form>';

        // 4 Schedule
        echo '<form class="sgap-card sgap-step" data-step="schedule" hidden><h2>When and how much?</h2>'
            . self::row('autopilot_enabled', true) . self::row('full_auto_enabled', true) . self::row('posts_per_day', true) . self::row('daily_cap', true)
            . '<div class="sgap-field"><div class="sgap-field-label"><label>Quiet hours</label><p>Nothing is released in this window (site time).</p></div><div class="sgap-field-input sgap-inline">'
            . self::hour_field('settings[quiet_start]', (int) SGAP_Settings::get('quiet_start', 23)) . '<span>to</span>' . self::hour_field('settings[quiet_end]', (int) SGAP_Settings::get('quiet_end', 7)) . '</div></div>'
            . '<div class="sgap-step-nav"><button type="button" class="button" data-back>Back</button><button class="button button-primary" data-next>Continue</button></div></form>';

        // 5 Extras
        echo '<form class="sgap-card sgap-step" data-step="extras" hidden><h2>Extras for articles and images</h2><p class="sgap-sub">Optional. Make the Content Studio smarter.</p>'
            . self::row('serpapi_key', true) . self::row('content_language', true) . self::row('image_model', true) . self::row('image_api_key', true)
            . '<div class="sgap-step-nav"><button type="button" class="button" data-back>Back</button><button class="button button-primary" data-next>Finish</button></div></form>';

        // 6 Done
        echo '<div class="sgap-card sgap-step" data-step="done" hidden><h2>🎉 You are set up</h2><p class="sgap-sub">Here is what is left – the dashboard keeps this list until everything is ticked.</p><div class="sgap-done-list"></div>'
            . '<div class="sgap-actions"><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=steamgears-autopilot')) . '"><span class="dashicons dashicons-dashboard"></span>Go to the dashboard</a>'
            . '<a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-studio')) . '"><span class="dashicons dashicons-edit-page"></span>Write an article</a>'
            . '<a class="button" href="' . esc_url(admin_url('admin.php?page=sgap-chat')) . '"><span class="dashicons dashicons-format-chat"></span>Ask the AI</a></div></div>';
        echo '</div></div>';

        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_setup')];
        echo self::field_js();
        echo '<script>(function(){var C=' . wp_json_encode($cfg) . ',order=["ai","channels","publer","schedule","extras","done"],rail=document.querySelectorAll(".sgap-rail li");'
            . 'function show(id){document.querySelectorAll(".sgap-step").forEach(function(s){s.hidden=s.dataset.step!==id;});var i=order.indexOf(id);'
            . 'rail.forEach(function(li,k){li.classList.toggle("on",k===i);li.classList.toggle("past",k<i);});history.replaceState(null,"","#step-"+id);window.scrollTo({top:0,behavior:"smooth"});}'
            . 'rail.forEach(function(li){li.addEventListener("click",function(){show(li.dataset.step);});});'
            . 'function post(action,fd){fd.append("action",action);fd.append("nonce",C.nonce);return fetch(C.ajax,{method:"POST",credentials:"same-origin",body:fd}).then(function(r){return r.json();});}'
            . 'function modes(f){var m=f.querySelector("input[name=\'settings[publish_mode]\']:checked");f.querySelectorAll(".sgap-mode").forEach(function(d){d.hidden=!m||d.dataset.mode!==m.value;});}'
            . 'var pf=document.querySelector(".sgap-step[data-step=publer]");modes(pf);pf.addEventListener("change",function(){modes(pf);});'
            . 'document.querySelectorAll("form.sgap-step").forEach(function(f){f.querySelectorAll("[data-back]").forEach(function(b){b.addEventListener("click",function(){show(order[order.indexOf(f.dataset.step)-1]);});});'
            . 'f.addEventListener("submit",function(e){e.preventDefault();var btn=f.querySelector("[data-next]"),label=btn.innerHTML;btn.disabled=true;btn.textContent="Saving…";var fd=new FormData(f);'
            . 'if(f.dataset.step==="publer"&&!fd.has("feeds_done[]"))fd.append("feeds_done","");if(f.dataset.step==="extras")fd.append("finish","1");'
            . 'post("sgap_setup_save",fd).then(function(j){if(!j.success)throw new Error(j.data||"Could not save");'
            . 'if(f.dataset.step==="ai"){btn.textContent="Testing the key…";return post("sgap_setup_test",new FormData()).then(function(t){var a=t.data.ai,box=f.querySelector(".sgap-test-result");'
            . 'box.innerHTML="<span class=\\"sgap-pill "+(a.ok?"ok":"bad")+"\\">AI "+(a.ok?"✓":"✗")+"</span> <span></span>";box.lastChild.textContent=a.message;return a.ok;});}return true;})'
            . '.then(function(go){btn.disabled=false;btn.innerHTML=label;if(go){var next=order[order.indexOf(f.dataset.step)+1];if(next==="done")done();show(next);}})'
            . '.catch(function(err){btn.disabled=false;btn.innerHTML=label;alert(err.message);});});});'
            . 'function done(){location.hash="step-done";location.reload();}'
            . 'document.querySelectorAll(".sgap-copy").forEach(function(b){var h=b.innerHTML;b.addEventListener("click",function(){var u=b.dataset.url,ok=function(){b.textContent="Copied ✓";setTimeout(function(){b.innerHTML=h;},2000);},'
            . 'old=function(){var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();try{document.execCommand("copy");}catch(e){}t.remove();ok();};'
            . 'if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(u).then(ok,old);}else{old();}});});'
            . 'var start=(location.hash.match(/step-(\\w+)/)||[])[1];show(order.indexOf(start)>=0?start:"ai");})();</script>';
        // The "done" step lists the checklist, rendered server-side.
        ob_start();
        self::checklist_card();
        $card = ob_get_clean();
        echo '<template id="sgap-done-tpl">' . ($card !== '' ? $card : '<p>Everything is done. Enjoy the autopilot!</p>') . '</template>'
            . '<script>(function(){var t=document.getElementById("sgap-done-tpl"),d=document.querySelector(".sgap-done-list");if(t&&d)d.appendChild(t.content.cloneNode(true));})();</script>';
        SGAP_Admin::close();
    }
}
