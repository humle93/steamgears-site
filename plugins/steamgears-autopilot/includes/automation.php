<?php
/**
 * Set-and-forget: the things that run by themselves.
 *
 * - SGAP_Rescue: images hosted on other servers (e.g. a former agency CDN) are copied
 *   into the media library and every reference is rewritten – posts, pages, products,
 *   template parts, custom CSS, post meta. Runs hourly until nothing is left.
 * - SGAP_Auto: AI photo for Instagram/Pinterest posts that have none; new posts and
 *   products are promoted (and get SEO meta) right after publishing; weekly SEO
 *   articles as drafts; a daily self-check; a weekly e-mail report.
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Rescue {
    const CRON = 'sgap_rescue';
    const MAP = 'sgap_rescue_map';
    const FAILED = 'sgap_rescue_failed';
    const DEFAULT_HOSTS = 'steamgears.juno.page';
    const POST_TYPES = ['post', 'page', 'product', 'wp_template_part', 'wp_template', 'wp_block', 'wp_global_styles', 'wp_navigation', 'custom-css-js'];

    public static function init(): void {
        add_action(self::CRON, [__CLASS__, 'cron']);
        add_action('wp_ajax_sgap_rescue', [__CLASS__, 'ajax']);
    }

    public static function hosts(): array {
        $raw = (string) SGAP_Settings::get('rescue_hosts', self::DEFAULT_HOSTS);
        return array_values(array_filter(array_map(fn($h) => strtolower(trim($h)), preg_split('/[\s,]+/', $raw ?: self::DEFAULT_HOSTS))));
    }

    public static function map(): array {
        $map = get_option(self::MAP);
        return is_array($map) ? $map : [];
    }

    /** Image/file URLs on the given hosts found in a text (plain and JSON-escaped forms). */
    public static function find_urls(string $text, array $hosts): array {
        $text = str_replace('\\/', '/', $text);
        $found = [];
        foreach ($hosts as $host) {
            if (stripos($text, $host) === false) {
                continue;
            }
            if (preg_match_all('#https?://' . preg_quote($host, '#') . '/[^\s"\'()<>\\\\]+#i', $text, $m)) {
                foreach ($m[0] as $url) {
                    $url = rtrim(html_entity_decode($url), '.,;');
                    if (preg_match('#\.(png|jpe?g|webp|gif|svg|avif|pdf|mp4|webm)(\?|$)#i', $url)) {
                        $found[$url] = true;
                    }
                }
            }
        }
        return array_keys($found);
    }

    /** Where external URLs occur: [urls => [...], posts => [id...], meta => [meta_id...]]. */
    public static function scan(): array {
        global $wpdb;
        $hosts = self::hosts();
        $urls = [];
        $posts = [];
        $meta = [];
        $types = "'" . implode("','", self::POST_TYPES) . "'"; // fixed list of slugs
        foreach ($hosts as $host) {
            $like = '%' . $wpdb->esc_like($host) . '%';
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ({$types}) AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE %s", $like
            ));
            foreach ($rows as $row) {
                $posts[(int) $row->ID] = true;
                foreach (self::find_urls($row->post_content, [$host]) as $u) {
                    $urls[$u] = true;
                }
            }
            $metas = $wpdb->get_results($wpdb->prepare("SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE %s AND meta_value LIKE %s", $wpdb->esc_like('_sgap_') . '%', $like));
            foreach ($metas as $row) {
                $meta[(int) $row->meta_id] = true;
                foreach (self::find_urls((string) $row->meta_value, [$host]) as $u) {
                    $urls[$u] = true;
                }
            }
        }
        return ['hosts' => $hosts, 'urls' => array_keys($urls), 'posts' => array_keys($posts), 'meta' => array_keys($meta)];
    }

    public static function status(): array {
        $scan = self::scan();
        $map = self::map();
        $failed = (array) get_option(self::FAILED, []);
        $pending = array_values(array_filter($scan['urls'], fn($u) => !isset($map[$u])));
        return [
            'hosts' => $scan['hosts'],
            'external_files' => count($scan['urls']),
            'copied' => count(array_intersect($scan['urls'], array_keys($map))),
            'waiting' => count(array_filter($pending, fn($u) => ($failed[$u]['tries'] ?? 0) < 3)),
            'given_up' => count(array_filter($pending, fn($u) => ($failed[$u]['tries'] ?? 0) >= 3)),
            'places' => count($scan['posts']) + count($scan['meta']),
            'failed' => array_slice(array_map(fn($u, $f) => ['url' => $u, 'error' => $f['error'] ?? ''], array_keys($failed), $failed), 0, 10)
        ];
    }

    /** Copies one file into the media library. @return string|WP_Error new URL */
    public static function copy(string $url) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url($url, 60);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        $name = wp_basename((string) wp_parse_url($url, PHP_URL_PATH)) ?: 'image.jpg';
        $id = media_handle_sideload(['name' => sanitize_file_name($name), 'tmp_name' => $tmp], 0);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return $id;
        }
        update_post_meta($id, '_sgap_source_url', $url);
        return (string) wp_get_attachment_url($id);
    }

    /** Rewrites every mapped URL in posts and post meta. Returns how many places changed. */
    public static function rewrite(array $map): int {
        global $wpdb;
        if (!$map) {
            return 0;
        }
        $scan = self::scan();
        $pairs = [];
        foreach ($map as $old => $new) {
            $pairs[$old] = $new;
            $pairs[str_replace('/', '\\/', $old)] = str_replace('/', '\\/', $new); // block attributes / JSON
        }
        $changed = 0;
        foreach ($scan['posts'] as $id) {
            $content = (string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id));
            $new = strtr($content, $pairs);
            if ($new !== $content) {
                // Keep the original once, then write directly: wp_update_post would run
                // kses for cron (no user) and strip <style>/<script> from template parts.
                if (!get_post_meta($id, '_sgap_rescue_backup', true)) {
                    add_post_meta($id, '_sgap_rescue_backup', wp_slash($content), true);
                }
                $wpdb->update($wpdb->posts, ['post_content' => $new], ['ID' => $id]);
                clean_post_cache($id);
                $changed++;
            }
        }
        foreach ($scan['meta'] as $meta_id) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_id));
            if (!$row) {
                continue;
            }
            $value = maybe_unserialize($row->meta_value);
            $new = self::replace_deep($value, $pairs);
            if ($new !== $value) {
                update_metadata_by_mid('post', $meta_id, $new);
                $changed++;
            }
        }
        if ($changed) {
            SGAP_AdminTools::purge_cache();
        }
        return $changed;
    }

    private static function replace_deep($value, array $pairs) {
        if (is_string($value)) {
            return strtr($value, $pairs);
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::replace_deep($v, $pairs);
            }
        }
        return $value;
    }

    /**
     * Copies up to $limit new files, then rewrites all references.
     * @return array{copied: int, failed: int, places_updated: int, waiting: int}
     */
    public static function step(int $limit = 8): array {
        $map = self::map();
        $failed = (array) get_option(self::FAILED, []);
        $scan = self::scan();
        $todo = array_values(array_filter($scan['urls'], fn($u) => !isset($map[$u]) && ($failed[$u]['tries'] ?? 0) < 3));
        $copied = 0;
        $errors = 0;
        foreach (array_slice($todo, 0, $limit) as $url) {
            $new = self::copy($url);
            if (is_wp_error($new)) {
                $failed[$url] = ['tries' => ($failed[$url]['tries'] ?? 0) + 1, 'error' => $new->get_error_message()];
                $errors++;
            } else {
                $map[$url] = $new;
                unset($failed[$url]);
                $copied++;
            }
        }
        update_option(self::MAP, $map, false);
        update_option(self::FAILED, $failed, false);
        $places = self::rewrite($map);
        $waiting = max(0, count($todo) - $copied - $errors);
        if ($waiting > 0 && !wp_next_scheduled(self::CRON)) {
            wp_schedule_single_event(time() + 120, self::CRON);
        }
        return ['copied' => $copied, 'failed' => $errors, 'places_updated' => $places, 'waiting' => $waiting];
    }

    public static function cron(): void {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        self::step(10);
    }

    public static function ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_rescue', 'nonce');
        wp_send_json_success(self::step(6) + self::status());
    }

    /** Dashboard card while external files remain. */
    public static function card(): void {
        $s = self::status();
        if ($s['external_files'] === 0 || ($s['waiting'] === 0 && $s['copied'] === $s['external_files'])) {
            return;
        }
        $left = $s['external_files'] - $s['copied'];
        echo '<div class="sgap-card sgap-rescue"><h2><span class="dashicons dashicons-images-alt2"></span>Images on another server</h2>'
            . '<p>' . esc_html("{$left} of {$s['external_files']} images are still loaded from " . implode(', ', $s['hosts']) . ". If that server goes away they disappear from your site. They are being copied to your media library automatically every hour.") . '</p>'
            . '<div class="sgap-bar"><span style="width:' . (int) round(100 * $s['copied'] / max(1, $s['external_files'])) . '%"></span></div>'
            . '<p id="sgap-rescue-text" class="description">' . esc_html("{$s['copied']} copied") . ($s['given_up'] ? esc_html(" · {$s['given_up']} could not be downloaded") : '') . '</p>'
            . '<button type="button" class="button button-primary" id="sgap-rescue-go"><span class="dashicons dashicons-download"></span>Copy them now</button></div>';
        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_rescue')];
        echo '<script>(function(){var C=' . wp_json_encode($cfg) . ',b=document.getElementById("sgap-rescue-go"),t=document.getElementById("sgap-rescue-text"),bar=document.querySelector(".sgap-rescue .sgap-bar span");'
            . 'function run(){var f=new URLSearchParams({action:"sgap_rescue",nonce:C.nonce});fetch(C.ajax,{method:"POST",credentials:"same-origin",body:f}).then(function(r){return r.json();}).then(function(j){'
            . 'if(!j.success){t.textContent=j.data||"Error";b.disabled=false;return;}var d=j.data;bar.style.width=Math.round(100*d.copied/Math.max(1,d.external_files))+"%";'
            . 't.textContent=d.copied+" of "+d.external_files+" copied · "+d.places_updated+" places updated"+(d.given_up?" · "+d.given_up+" could not be downloaded":"");'
            . 'if(d.waiting>0)run();else{b.textContent="Done ✓";}}).catch(function(e){t.textContent=e.message;b.disabled=false;});}'
            . 'b.addEventListener("click",function(){b.disabled=true;b.textContent="Copying…";run();});})();</script>';
    }
}

class SGAP_Auto {
    const ARTICLE_CRON = 'sgap_weekly_article';
    const HEALTH_CRON = 'sgap_daily_health';
    const REPORT_CRON = 'sgap_weekly_email';
    const NEW_CONTENT_CRON = 'sgap_promote_new';
    const HEALTH = 'sgap_health';

    public static function init(): void {
        add_filter('sgap_missing_photo', [__CLASS__, 'photo_for_queue_item'], 10, 2);
        add_action('transition_post_status', [__CLASS__, 'on_publish'], 10, 3);
        add_action(self::NEW_CONTENT_CRON, [__CLASS__, 'promote_new']);
        add_action(self::ARTICLE_CRON, [__CLASS__, 'weekly_article']);
        add_action(self::HEALTH_CRON, [__CLASS__, 'health']);
        add_action(self::REPORT_CRON, [__CLASS__, 'weekly_report']);
        add_action('init', [__CLASS__, 'schedule'], 20);
    }

    public static function schedule(): void {
        foreach ([self::HEALTH_CRON => ['daily', 2 * HOUR_IN_SECONDS], self::ARTICLE_CRON => ['weekly', DAY_IN_SECONDS], self::REPORT_CRON => ['weekly', 3 * DAY_IN_SECONDS], SGAP_Rescue::CRON => ['hourly', 15 * MINUTE_IN_SECONDS]] as $hook => [$rec, $delay]) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + $delay, $rec, $hook);
            }
        }
    }

    public static function clear(): void {
        foreach ([self::HEALTH_CRON, self::ARTICLE_CRON, self::REPORT_CRON, self::NEW_CONTENT_CRON, SGAP_Rescue::CRON] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /* ---- photos for Instagram / Pinterest ---- */

    /** Filter: returns an image URL for a queued post that has none ('' when not possible). */
    public static function photo_for_queue_item(string $url, array $row): string {
        if ($url !== '' || !SGAP_Settings::enabled('auto_images') || SGAP_Images::key() === '') {
            return $url;
        }
        $post_id = (int) ($row['post_id'] ?? 0);
        if ($post_id && ($thumb = get_the_post_thumbnail_url($post_id, 'large'))) {
            return (string) $thumb;
        }
        $shape = ($row['channel'] ?? '') === 'pinterest' ? 'pin' : 'square';
        $subject = trim((string) ($row['title'] ?? '')) ?: get_the_title($post_id);
        $image = SGAP_Studio::image([
            'prompt' => "A photographic, atmospheric steampunk image for a social media post about: {$subject}. Brass, copper, leather, warm light; no text, no logos.",
            'alt' => $subject,
            'post_id' => $post_id && !has_post_thumbnail($post_id) ? $post_id : 0,
            'queue_id' => (int) ($row['id'] ?? 0),
            'shape' => $shape
        ]);
        return is_wp_error($image) ? '' : (string) $image['url'];
    }

    /* ---- new posts and products ---- */

    public static function on_publish($new, $old, $post): void {
        if ($new !== 'publish' || $old === 'publish' || !in_array($post->post_type, ['post', 'product'], true) || !SGAP_Settings::enabled('autopilot_enabled')) {
            return;
        }
        $pending = (array) get_option('sgap_new_content', []);
        $pending[] = (int) $post->ID;
        update_option('sgap_new_content', array_values(array_unique($pending)), false);
        if (!wp_next_scheduled(self::NEW_CONTENT_CRON)) {
            wp_schedule_single_event(time() + 5 * MINUTE_IN_SECONDS, self::NEW_CONTENT_CRON);
        }
    }

    /** Writes social posts (and SEO meta when missing) for freshly published content. */
    public static function promote_new(): array {
        $ids = (array) get_option('sgap_new_content', []);
        delete_option('sgap_new_content');
        $report = ['sources' => 0, 'drafts' => 0, 'queued' => 0, 'errors' => [], 'seo' => 0];
        $channels = SGAP_Publer::channels();
        foreach (array_slice($ids, 0, 5) as $id) {
            $post = get_post((int) $id);
            if (!$post || $post->post_status !== 'publish' || get_post_meta($post->ID, SGAP_Generator::META, true)) {
                continue;
            }
            if (SGAP_Settings::enabled('auto_seo') && !get_post_meta($post->ID, 'rank_math_description', true)) {
                $seo = SGAP_Studio::optimize((int) $post->ID, true);
                $report['seo'] += is_wp_error($seo) ? 0 : 1;
            }
            if ($channels) {
                SGAP_Generator::write_for($post, $channels, SGAP_Settings::enabled('full_auto_enabled'), $report);
            }
        }
        update_option('sgap_last_promote_new', ['at' => current_time('mysql')] + $report, false);
        return $report;
    }

    /* ---- weekly article ---- */

    /** Picks a topic the site does not cover yet and writes it as a draft (or publishes). */
    public static function weekly_article(): array {
        $per_week = (int) SGAP_Settings::get('articles_per_week', 0);
        if ($per_week < 1 || !SGAP_Settings::enabled('autopilot_enabled')) {
            return ['skipped' => 'articles_per_week is 0 or the autopilot is off'];
        }
        $titles = array_map(fn($p) => get_the_title($p), get_posts(['post_type' => 'post', 'post_status' => ['publish', 'draft', 'future'], 'posts_per_page' => 80, 'no_found_rows' => true]));
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['topics'], 'properties' => ['topics' => ['type' => 'array', 'items' => [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['topic', 'keyword', 'type'],
            'properties' => ['topic' => ['type' => 'string'], 'keyword' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => array_keys(SGAP_Studio::TYPES)]]
        ]]]];
        $ideas = SGAP_AI::json_call(
            SGAP_AI::SYSTEM . ' You plan SEO content for the blog.',
            "Suggest {$per_week} new article or guide topics for steamgears.eu that people search for and the site does not cover yet. "
            . "Prefer seasonal or evergreen searches with buying intent that can link to the shop. Existing titles:\n- " . implode("\n- ", $titles),
            $schema, 4000, 'low'
        );
        if (is_wp_error($ideas)) {
            return ['error' => $ideas->get_error_message()];
        }
        $done = [];
        foreach (array_slice((array) ($ideas['topics'] ?? []), 0, $per_week) as $idea) {
            $r = SGAP_Studio::write(['topic' => $idea['topic'] ?? '', 'keyword' => $idea['keyword'] ?? '', 'type' => $idea['type'] ?? 'article', 'image' => true]);
            if (!is_wp_error($r) && SGAP_Settings::enabled('auto_publish_articles')) {
                wp_publish_post($r['post_id']);
                $r['status'] = 'publish';
            }
            $done[] = is_wp_error($r) ? ['topic' => $idea['topic'] ?? '', 'error' => $r->get_error_message()] : ['topic' => $idea['topic'], 'post_id' => $r['post_id'], 'status' => $r['status']];
        }
        update_option('sgap_last_articles', ['at' => current_time('mysql'), 'articles' => $done], false);
        return ['articles' => $done];
    }

    /* ---- self-check and report ---- */

    /** Daily: retries failed posts once, checks the feed and the AI key, records problems. */
    public static function health(): array {
        global $wpdb;
        $problems = [];
        $q = $wpdb->prefix . 'sgap_queue';
        // Posts that failed only for want of a photo get another go once AI photos are on.
        $retry = SGAP_Settings::enabled('auto_images') && SGAP_Images::key() !== ''
            ? (int) $wpdb->query("UPDATE {$q} SET status = 'queued', attempts = 0, error = NULL WHERE status = 'failed' AND error LIKE '%needs a photo%'")
            : 0;
        $failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$q} WHERE status = 'failed'");
        if ($failed > 0) {
            $problems[] = "{$failed} posts failed – see the Queue page for the reason.";
        }
        if (SGAP_RSS::enabled()) {
            foreach (SGAP_Publer::channels() as $channel) {
                $r = wp_remote_get(SGAP_RSS::url($channel), ['timeout' => 15]);
                if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200 || stripos((string) wp_remote_retrieve_header($r, 'content-type'), 'xml') === false) {
                    $problems[] = "The {$channel} feed does not answer as RSS.";
                }
            }
        }
        if (SGAP_Settings::get('ai_api_key') === '') {
            $problems[] = 'No AI key: nothing new is written.';
        } elseif ((int) wp_date('N') === 1) {
            $keys = SGAP_KeyCheck::run(); // weekly
            if (empty($keys['ai']['ok'])) {
                $problems[] = 'AI key: ' . $keys['ai']['message'];
            }
        }
        if (!SGAP_Settings::enabled('autopilot_enabled')) {
            $problems[] = 'The autopilot is off.';
        }
        $lag = self::cron_lag();
        if ($lag >= 30) {
            $problems[] = self::cron_advice($lag);
        }
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts WHERE status = 'pending'");
        if (!SGAP_Settings::enabled('full_auto_enabled') && $pending >= 12) {
            $problems[] = "{$pending} drafts are waiting for approval – nothing goes out until you approve them (or switch on Full auto).";
        }
        $rescue = SGAP_Rescue::status();
        if ($rescue['given_up'] > 0) {
            $problems[] = "{$rescue['given_up']} images on another server could not be copied.";
        }
        $result = ['at' => current_time('mysql'), 'problems' => $problems, 'retried' => $retry];
        update_option(self::HEALTH, $result, false);
        return $result;
    }

    /** Weekly e-mail to the admin: what went out, what worked, what needs you. */
    public static function weekly_report(): bool {
        if (!SGAP_Settings::enabled('weekly_report')) {
            return false;
        }
        global $wpdb;
        $since = wp_date('Y-m-d H:i:s', time() - WEEK_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT channel, SUM(event_type = 'publish') AS published, SUM(event_type = 'click') AS clicks, SUM(event_type = 'order') AS orders,
                    SUM(CASE WHEN event_type = 'order' THEN value ELSE 0 END) AS revenue
             FROM {$wpdb->prefix}sgap_events WHERE created_at >= %s GROUP BY channel ORDER BY clicks DESC", $since
        ));
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts WHERE status = 'pending'");
        $health = get_option(self::HEALTH);
        $lines = ['Steamgears Autopilot – the last 7 days', ''];
        if ($rows) {
            foreach ($rows as $r) {
                $lines[] = sprintf('%-10s %3d posts · %4d clicks · %2d orders · %s', $r->channel, $r->published, $r->clicks, $r->orders, number_format_i18n((float) $r->revenue, 2));
            }
        } else {
            $lines[] = 'Nothing was published this week.';
        }
        $lines[] = '';
        $lines[] = "Drafts waiting for you: {$pending}";
        $articles = get_option('sgap_last_articles');
        if (is_array($articles) && !empty($articles['articles'])) {
            $lines[] = 'New articles: ' . implode(', ', array_map(fn($a) => $a['topic'] . (isset($a['error']) ? ' (failed)' : ''), $articles['articles']));
        }
        if (is_array($health) && $health['problems']) {
            $lines[] = '';
            $lines[] = 'Needs your attention:';
            foreach ($health['problems'] as $p) {
                $lines[] = '- ' . $p;
            }
        }
        $lines[] = '';
        $lines[] = 'Dashboard: ' . admin_url('admin.php?page=steamgears-autopilot');
        return wp_mail(get_option('admin_email'), 'Steamgears weekly: ' . (array_sum(array_map(fn($r) => (int) $r->clicks, $rows)) . ' clicks'), implode("\n", $lines));
    }

    /**
     * Minutes the 15-minute publisher is overdue (0 when on time). WP-Cron only runs when
     * WordPress handles a request; a full page cache plus quiet hours of traffic can stop it.
     */
    public static function cron_lag(): int {
        $next = wp_next_scheduled(SGAP_Install::PUBLISH_CRON);
        if (!$next || (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && $next > time() - 1800)) {
            return 0;
        }
        return max(0, (int) floor((time() - $next) / 60));
    }

    public static function cron_advice(int $lag): string {
        return "WP-Cron is {$lag} minutes behind, so posts are released late. Set up a free external cron that calls "
            . site_url('wp-cron.php') . ' every 5 minutes (e.g. cron-job.org or your host\'s cron), or add the feeds in Publer – its polling also keeps cron running.';
    }

    /** Dashboard card with the last self-check (plus a live cron check). */
    public static function health_card(): void {
        $h = get_option(self::HEALTH);
        $lag = self::cron_lag();
        if (!is_array($h)) {
            $h = ['at' => '–', 'problems' => []];
        }
        if ($lag >= 30) {
            array_unshift($h['problems'], self::cron_advice($lag));
        }
        if ($h['at'] === '–' && !$h['problems']) {
            return;
        }
        SGAP_UI::card_open('Self-check', 'heart');
        if (!$h['problems']) {
            echo '<p>' . SGAP_UI::pill('All good', 'ok') . ' <span class="description">' . esc_html('Checked ' . $h['at']) . '</span></p>';
        } else {
            echo '<ul class="sgap-problems">';
            foreach ($h['problems'] as $p) {
                echo '<li>' . SGAP_UI::pill('!', 'warn') . ' ' . esc_html($p) . '</li>';
            }
            echo '</ul><p class="description">' . esc_html('Checked ' . $h['at']) . '</p>';
        }
        SGAP_UI::card_close();
    }
}

add_action('wp_abilities_api_init', function () {
    if (!function_exists('wp_register_ability')) {
        return;
    }
    $defs = [
        'rescue-images' => ['Copy external images to the media library',
            'Images loaded from another server (default steamgears.juno.page; Settings → Automation) are copied into the media library and every reference is rewritten (posts, pages, products, templates, CSS, meta). dry_run=true only reports what is left. Runs hourly by itself too.',
            ['dry_run' => ['type' => 'boolean', 'default' => false], 'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 10]],
            fn($i) => !empty($i['dry_run']) ? SGAP_Rescue::status() : SGAP_Rescue::step((int) ($i['limit'] ?? 10)) + SGAP_Rescue::status()],
        'self-check' => ['Run the self-check',
            'Runs the daily self-check now: feeds answer as RSS, AI key works, autopilot on, drafts piling up, failed posts (photo failures are retried when AI photos are on), images on other servers. Returns the problems found.',
            [], fn($i) => SGAP_Auto::health()],
        'write-weekly-articles' => ['Write this week\'s articles now',
            'Plans new SEO topics the site does not cover yet and writes them (Settings → Automation → articles per week; drafts unless auto-publish is on). Takes a few minutes per article.',
            [], fn($i) => SGAP_Auto::weekly_article()]
    ];
    foreach ($defs as $name => [$label, $description, $props, $cb]) {
        $args = [
            'label' => $label, 'description' => $description, 'category' => SGAP_Abilities::CATEGORY,
            'output_schema' => ['type' => 'object'], 'execute_callback' => $cb,
            'permission_callback' => [SGAP_Abilities::class, 'can_manage'], 'meta' => SGAP_Abilities::meta(false, false, false)
        ];
        if ($props) {
            $args['input_schema'] = SGAP_Abilities::object($props);
        }
        wp_register_ability(SGAP_Abilities::CATEGORY . '/' . $name, $args);
    }
});
