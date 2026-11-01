<?php
/**
 * Set-and-forget checks (includes/automation.php), run with: wp eval-file plugins/tests/automation.php
 * External images, AIMLAPI text and images are mocked.
 */
$fail = 0;
function ta($name, $ok, $info = null) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($info !== null ? ' — ' . substr(is_string($info) ? $info : wp_json_encode($info), 0, 260) : '') . "\n"; if (!$ok) $fail = 1; }
$jpg = file_get_contents(__DIR__ . '/fixtures/ai-image.jpg');
add_filter('pre_http_request', function ($pre, $args, $url) use ($jpg) {
    if ($pre !== false) return $pre;
    $ok = fn($body, $type = 'application/json') => ['headers' => ['content-type' => $type], 'body' => $body, 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => $args['filename'] ?? null];
    if (strpos($url, 'https://cdn.example.test/') === 0 || strpos($url, 'https://img.example.test/') === 0) {
        if (str_contains($url, 'missing')) return ['headers' => [], 'body' => '', 'response' => ['code' => 404, 'message' => 'Not Found'], 'cookies' => [], 'filename' => null];
        if (!empty($args['filename'])) file_put_contents($args['filename'], $jpg);
        return $ok(empty($args['filename']) ? $jpg : '', 'image/jpeg');
    }
    if ($url === 'https://api.aimlapi.com/v1/images/generations') {
        $GLOBALS['ta_images'] = ($GLOBALS['ta_images'] ?? 0) + 1;
        return $ok(wp_json_encode(['data' => [['url' => 'https://img.example.test/gen-' . $GLOBALS['ta_images']]]]));
    }
    if ($url === 'https://api.aimlapi.com/v1/chat/completions') {
        $b = json_decode((string) $args['body'], true);
        $sys = (string) ($b['messages'][0]['content'] ?? '');
        $user = (string) ($b['messages'][1]['content'] ?? '');
        if (str_contains($sys, 'plan SEO content')) $c = ['topics' => [['topic' => 'Steampunk wedding outfits', 'keyword' => 'steampunk wedding', 'type' => 'article']]];
        elseif (str_contains($sys, 'content_html')) $c = ['title' => 'Steampunk Wedding Outfits', 'slug' => 'steampunk-wedding', 'meta_title' => 'Steampunk Wedding Outfits', 'meta_description' => str_repeat('Brass and lace. ', 9), 'focus_keyword' => 'steampunk wedding', 'excerpt' => 'x', 'content_html' => '<p>Steampunk wedding ideas.</p>', 'faq' => [], 'tags' => ['wedding'], 'image_prompt' => 'wedding', 'image_alt' => 'Steampunk wedding'];
        elseif (str_contains($sys, 'suggestions')) $c = ['focus_keyword' => 'brass compass', 'meta_title' => 'Brass Compass', 'meta_description' => 'A brass compass for your steampunk costume, hand finished and ready to wear at any convention or party.', 'suggestions' => ['Add photos']];
        else {
            preg_match_all('/^- ([a-z]+):/m', $user, $m);
            $c = ['posts' => array_map(fn($ch) => ['channel' => $ch, 'title' => "New $ch", 'text' => "Fresh $ch post.", 'hashtags' => ['#steampunk'], 'alt_text' => 'x'], $m[1])];
        }
        return $ok(wp_json_encode(['choices' => [['message' => ['content' => wp_json_encode($c)], 'finish_reason' => 'stop']]]));
    }
    return $pre;
}, 5, 3);
wp_set_current_user(1);
foreach (['autopilot_enabled' => 'yes', 'full_auto_enabled' => 'no', 'ai_provider' => 'aimlapi', 'ai_api_key' => 'k', 'ai_model' => '', 'publish_mode' => 'rss', 'channels' => 'facebook,instagram', 'quiet_start' => 0, 'quiet_end' => 0, 'auto_images' => 'yes', 'auto_seo' => 'yes', 'rescue_hosts' => 'cdn.example.test', 'serpapi_key' => '', 'daily_cap' => 50, 'batch_size' => 20] as $k => $v) update_option("sgap_$k", $v);
global $wpdb;

/* --- image rescue --- */
$p1 = wp_insert_post(['post_title' => 'Rescue me', 'post_status' => 'publish', 'post_content' => '<!-- wp:image {"url":"https:\/\/cdn.example.test\/guides\/a.jpg"} --><figure><img src="https://cdn.example.test/guides/a.jpg" alt=""></figure><!-- /wp:image --><p><img src="https://cdn.example.test/missing.jpg"></p>']);
$tp = wp_insert_post(['post_type' => 'wp_template_part', 'post_title' => 'hdr', 'post_name' => 'hdr', 'post_status' => 'publish', 'post_content' => '<style>.h{background:url(\'https://cdn.example.test/hero.webp\') center}</style><script type="application/ld+json">{"logo":"https:\/\/cdn.example.test\/logo.png"}</script>']);
update_post_meta($p1, 'rank_math_facebook_image', 'https://cdn.example.test/og.png');
$st = SGAP_Rescue::status();
ta('rescue: finds every external file (plain, JSON-escaped, CSS, meta)', $st['external_files'] === 5, $st);
$r = SGAP_Rescue::step(10);
ta('rescue: copies what downloads, reports what does not', $r['copied'] === 4 && $r['failed'] === 1, $r);
$c1 = get_post_field('post_content', $p1, 'raw');
$ct = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $tp));
ta('rescue: post content points to the media library (image block attribute and <img>)', !str_contains($c1, 'cdn.example.test/guides/a.jpg') && substr_count($c1, content_url('/uploads/')) === 2, $c1);
ta('rescue: template part keeps its <style> and <script>, URLs rewritten', str_contains($ct, '<style>') && str_contains($ct, '<script') && !str_contains(str_replace('\\/', '/', $ct), 'cdn.example.test'), $ct);
ta('rescue: post meta rewritten', str_contains((string) get_post_meta($p1, 'rank_math_facebook_image', true), '/wp-content/uploads/'));
ta('rescue: original kept as backup', str_contains((string) get_post_meta($p1, '_sgap_rescue_backup', true), 'cdn.example.test'));
ta('rescue: copied files remember their source', (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_sgap_source_url' AND meta_value LIKE '%cdn.example.test%'") === 4);
$st = SGAP_Rescue::status();
ta('rescue: status after – 1 left (the broken one), nothing re-copied', $st['external_files'] === 1 && $st['copied'] === 0, $st);
SGAP_Rescue::step(10); SGAP_Rescue::step(10);
$st = SGAP_Rescue::status();
ta('rescue: gives up on a file after 3 failed tries', $st['given_up'] === 1 && $st['waiting'] === 0, $st);

/* --- AI photo for Instagram --- */
$q = SGAP_Queue::add(['channel' => 'instagram', 'title' => 'Brass goggles', 'body' => 'Look!', 'destination_url' => home_url('/goggles/')]);
$qid = is_wp_error($q) ? 0 : (int) $q['id'];
$rel = SGAP_RSS::release();
ta('photo: Instagram post without a photo is released with an AI photo', $qid && in_array($qid, array_column($rel['sent'], 'id'), true) && str_contains((string) $wpdb->get_var("SELECT image_url FROM {$wpdb->prefix}sgap_queue WHERE id = $qid"), '/wp-content/uploads/'), $rel);
update_option('sgap_auto_images', 'no');
$q2 = SGAP_Queue::add(['channel' => 'instagram', 'title' => 'No photo', 'body' => 'x', 'destination_url' => home_url('/nophoto/')]);
$rel = SGAP_RSS::release();
ta('photo: with AI photos off it fails with a helpful reason', in_array((int) $q2['id'], array_column($rel['failed'], 'id'), true) && str_contains($rel['failed'][0]['error'], 'AI photos'), $rel['failed'] ?? null);
update_option('sgap_auto_images', 'yes');

/* --- new content gets promoted --- */
$new = wp_insert_post(['post_title' => 'Brass compass', 'post_content' => 'A compass.', 'post_status' => 'draft']);
wp_publish_post($new);
ta('new content: publishing schedules promotion', in_array($new, (array) get_option('sgap_new_content'), true) && wp_next_scheduled(SGAP_Auto::NEW_CONTENT_CRON));
$r = SGAP_Auto::promote_new();
ta('new content: social drafts written and SEO meta added', $r['drafts'] >= 2 && $r['seo'] >= 1 && get_post_meta($new, 'rank_math_focus_keyword', true) === 'brass compass' && (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}sgap_ai_drafts WHERE post_id = %d", $new)) === 2, $r);

/* --- weekly article --- */
update_option('sgap_articles_per_week', 0);
ta('weekly article: off by default', isset(SGAP_Auto::weekly_article()['skipped']));
update_option('sgap_articles_per_week', 1);
$r = SGAP_Auto::weekly_article();
$aid = $r['articles'][0]['post_id'] ?? 0;
ta('weekly article: picks a new topic and writes a draft with an image', $aid && get_post_status($aid) === 'draft' && has_post_thumbnail($aid), $r);

/* --- self-check and report --- */
$old = SGAP_Queue::add(['channel' => 'pinterest', 'title' => 'Old fail', 'body' => 'x', 'destination_url' => home_url('/oldfail/')]);
$wpdb->update($wpdb->prefix . 'sgap_queue', ['status' => 'failed', 'attempts' => 3, 'error' => 'Pinterest needs a photo: give the post a featured image.'], ['id' => (int) $old['id']]);
$h = SGAP_Auto::health();
ta('self-check: photo failures retried when AI photos are on', $h['retried'] >= 1, $h);
ta('self-check: reports images that could not be copied', (bool) array_filter($h['problems'], fn($p) => str_contains($p, 'could not be copied')), $h['problems']);
$mail = null;
add_filter('pre_wp_mail', function ($null, $atts) use (&$mail) { $mail = $atts; return true; }, 10, 2);
ta('weekly report: e-mail sent to the admin', SGAP_Auto::weekly_report() && $mail && $mail['to'] === get_option('admin_email') && str_contains($mail['message'], 'Drafts waiting'), $mail['subject'] ?? null);
update_option('sgap_weekly_report', 'no');
ta('weekly report: can be switched off', SGAP_Auto::weekly_report() === false);
ta('crons scheduled', wp_next_scheduled(SGAP_Auto::HEALTH_CRON) && wp_next_scheduled(SGAP_Auto::ARTICLE_CRON) && wp_next_scheduled(SGAP_Auto::REPORT_CRON) && wp_next_scheduled(SGAP_Rescue::CRON));
if (function_exists('wp_get_ability')) {
    $r = wp_get_ability('steamgears-autopilot/rescue-images')->execute(['dry_run' => true]);
    ta('rescue-images and self-check available as tools', is_array($r) && isset($r['external_files']) && wp_get_ability('steamgears-autopilot/self-check'));
}
if ($fail) { WP_CLI::error('automation: some checks failed'); }
WP_CLI::line('automation: all checks passed');
