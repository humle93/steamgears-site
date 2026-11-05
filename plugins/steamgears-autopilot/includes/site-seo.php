<?php
/**
 * Whole-site SEO, GEO and AEO audit — one pass over every published page,
 * post and product instead of clicking through them one at a time.
 *
 * Three layers:
 *   1. scan()     — pure PHP + database, no AI, no cost. Scores every URL and
 *                   writes the result to one option, so the admin screen and the
 *                   MCP ability read the same numbers.
 *   2. fix()      — writes better meta (title, description, focus keyword) for
 *                   the weakest URLs in batches of AI calls. Content rewrites stay
 *                   in SGAP_Studio::optimize(); a scan never touches post text.
 *   3. site_checks() — the handful of checks that only make sense once for the
 *                   whole domain: llms.txt, sitemap, robots, duplicate titles.
 *
 * Standards used, and why each one is in the list:
 *   SEO  — what a crawler needs to index and rank the page (title, description,
 *          one H1, enough text, internal links, alt text, keyword in title).
 *   AEO  — answer engines: question-shaped headings, an FAQ block with schema, a
 *          direct answer in the first paragraph, and scannable lists or tables.
 *   GEO  — generative engines: named entities, quotable facts, structured data,
 *          an author and a date, outbound citations, and an llms.txt.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Site_SEO {
    const STORE = 'sgap_seo_site';        // last scan result (one option)
    const GUARD = 'sgap_seo_site_guard';  // stops the lazy rescan from running twice a day
    const MAX_ITEMS = 400;                // safety cap on one scan
    const MAX_FIX = 25;                   // AI calls in one fix() request
    const SCAN_MAX_AGE = 604800;          // rescan lazily after 7 days

    /** How much text a page needs before it is not "thin". */
    const WORD_TARGET = ['post' => 500, 'page' => 400, 'product' => 120];
    const TYPES = ['post', 'page', 'product'];

    public static function init(): void {
        add_action('wp_ajax_sgap_seo_site', [__CLASS__, 'ajax']);
        add_action('admin_init', [__CLASS__, 'maybe_scan']);
        add_action('wp_abilities_api_init', [__CLASS__, 'register_abilities']);
    }

    /* ------------------------------------------------------------------ store */

    /** Last stored scan, or an empty shell. */
    public static function last(): array {
        $scan = get_option(self::STORE, []);
        return is_array($scan) && isset($scan['items']) ? $scan : ['at' => '', 'at_ts' => 0, 'score' => 0, 'items' => [], 'site' => [], 'totals' => []];
    }

    /**
     * Lazy weekly rescan. WP-Cron is not dependable on a cached site (the plugin
     * already carries that lesson in the roadmap), so the scan is refreshed when an
     * administrator opens wp-admin and the stored scan is older than a week.
     */
    public static function maybe_scan(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return;
        }
        if (get_transient(self::GUARD)) {
            return;
        }
        set_transient(self::GUARD, 1, DAY_IN_SECONDS);
        $last = self::last();
        if ($last['at_ts'] && (time() - (int) $last['at_ts']) < self::SCAN_MAX_AGE) {
            return;
        }
        self::scan();
    }

    /* --------------------------------------------------------------- scanning */

    /** Every published page, post and product, ready to be evaluated. */
    public static function collect(): array {
        $types = array_values(array_filter(self::TYPES, 'post_type_exists'));
        if (!$types) {
            return [];
        }
        $posts = get_posts([
            'post_type' => $types,
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_ITEMS,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => true
        ]);
        $out = [];
        foreach ($posts as $post) {
            $meta_title = (string) get_post_meta($post->ID, 'rank_math_title', true);
            $description = (string) get_post_meta($post->ID, 'rank_math_description', true);
            $keyword = (string) get_post_meta($post->ID, 'rank_math_focus_keyword', true);
            if ($description === '') {
                $description = (string) get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
            }
            if ($meta_title === '') {
                $meta_title = (string) get_post_meta($post->ID, '_yoast_wpseo_title', true);
            }
            if ($keyword === '') {
                $keyword = (string) get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
            }
            $content = (string) $post->post_content;
            if (function_exists('strip_shortcodes')) {
                $content = strip_shortcodes($content);
            }
            $out[] = [
                'id' => (int) $post->ID,
                'type' => (string) $post->post_type,
                'title' => (string) get_the_title($post),
                'content' => $content,
                'excerpt' => (string) $post->post_excerpt,
                'meta_title' => $meta_title,
                'description' => $description,
                'keyword' => $keyword,
                'slug' => (string) $post->post_name,
                'url' => (string) get_permalink($post),
                'modified' => (string) $post->post_modified,
                'featured' => (bool) get_post_thumbnail_id($post)
            ];
        }
        return $out;
    }

    /**
     * Runs the full scan: every URL, then the once-per-domain checks, then stores
     * the result. No AI is called here, so it is safe to run on every wp-admin load
     * once a week and cheap enough to run on demand.
     */
    public static function scan(array $args = []): array {
        $deep = !empty($args['links']);
        $items = self::collect();
        if (!$items) {
            return ['at' => current_time('mysql'), 'at_ts' => time(), 'score' => 0, 'items' => [], 'site' => [], 'totals' => [], 'error' => 'No published content found.'];
        }

        // Duplicate titles and descriptions can only be seen across the whole set.
        $titles = [];
        $descs = [];
        foreach ($items as $item) {
            $t = mb_strtolower(trim($item['title']));
            $d = mb_strtolower(trim($item['description']));
            if ($t !== '') $titles[$t] = ($titles[$t] ?? 0) + 1;
            if ($d !== '') $descs[$d] = ($descs[$d] ?? 0) + 1;
        }

        $rows = [];
        foreach ($items as $item) {
            $item['duplicate_title'] = ($titles[mb_strtolower(trim($item['title']))] ?? 0) > 1;
            $item['duplicate_description'] = $item['description'] !== '' && ($descs[mb_strtolower(trim($item['description']))] ?? 0) > 1;
            $checks = self::evaluate($item);
            $rows[] = [
                'id' => $item['id'],
                'type' => $item['type'],
                'title' => $item['title'],
                'url' => $item['url'],
                'edit' => (string) get_edit_post_link($item['id'], 'raw'),
                'score' => self::score($checks),
                'fails' => self::ids($checks, 'fail'),
                'warns' => self::ids($checks, 'warn'),
                'keyword' => $item['keyword'],
                'meta_title' => $item['meta_title'],
                'description' => $item['description'],
                'fixed_at' => ''
            ];
        }

        usort($rows, fn($a, $b) => $a['score'] <=> $b['score']);
        $site = self::site_checks();
        $scan = [
            'at' => current_time('mysql'),
            'at_ts' => time(),
            'score' => self::average($rows, $site),
            'items' => $rows,
            'site' => $site,
            'totals' => self::summarize($rows),
            'deep' => $deep
        ];
        update_option(self::STORE, $scan, false);
        return self::public_summary($scan);
    }

    /**
     * The rule set. Pure: takes plain data, returns checks; no WordPress calls
     * beyond wp_strip_all_tags, so smoke tests can drive it directly.
     *
     * @return array<int,array{id:string,group:string,label:string,status:string,detail:string,advice:string,weight:float}>
     */
    public static function evaluate(array $in): array {
        $type = (string) ($in['type'] ?? 'post');
        $title = trim((string) ($in['title'] ?? ''));
        $html = (string) ($in['content'] ?? '');
        $plain = self::text_of($html);
        $words = $plain === '' ? 0 : count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY));
        $meta_title = trim((string) ($in['meta_title'] ?? ''));
        $description = trim((string) ($in['description'] ?? ''));
        $keyword = trim((string) ($in['keyword'] ?? ''));
        $slug = trim((string) ($in['slug'] ?? ''));
        $headings = self::headings($html);
        $links = self::links($html);
        $images = self::images($html);
        $first = self::first_paragraph($html);
        $brand = trim((string) ($in['brand'] ?? 'Steamgears'));
        $checks = [];

        /* ---- SEO ---- */
        $title_len = mb_strlen($title);
        $checks[] = self::check('title_length', 'seo', 'Title length', self::band($title_len, 30, 60, 15, 70),
            $title_len . ' characters', 'Keep the title between 30 and 60 characters so Google does not cut it.');
        $checks[] = self::check('meta_title', 'seo', 'SEO title written', $meta_title === '' ? 'fail' : self::band(mb_strlen($meta_title), 30, 60, 20, 70),
            $meta_title === '' ? 'no Rank Math/Yoast title' : mb_strlen($meta_title) . ' characters',
            'Write a Rank Math title of 30-60 characters with the keyword first.');
        $desc_len = mb_strlen($description);
        $checks[] = self::check('meta_description', 'seo', 'Meta description', $desc_len === 0 ? 'fail' : self::band($desc_len, 120, 160, 80, 180),
            $desc_len === 0 ? 'missing' : $desc_len . ' characters', 'Write 140-155 characters: the keyword, then the reason to click.');
        $checks[] = self::check('focus_keyword', 'seo', 'Focus keyword', $keyword === '' ? 'fail' : 'pass',
            $keyword === '' ? 'missing' : $keyword, 'Set the focus keyword the page should rank for.');
        $kw_in_title = $keyword !== '' && $title !== '' && mb_stripos($title, $keyword) !== false;
        $checks[] = self::check('keyword_in_title', 'seo', 'Keyword in title', $keyword === '' ? 'skip' : ($kw_in_title ? 'pass' : 'warn'),
            $kw_in_title ? 'yes' : 'no', 'The focus keyword belongs in the title and in the first paragraph.');

        $h1 = count($headings['h1'] ?? []);
        $checks[] = self::check('h1', 'seo', 'Exactly one H1', $h1 === 1 ? 'pass' : ($h1 === 0 ? 'warn' : 'fail'),
            $h1 . ' H1 element(s)', 'One H1 per page; the theme renders it from the title on most templates.');

        $target = self::WORD_TARGET[$type] ?? 400;
        $checks[] = self::check('length', 'seo', 'Enough text', $words >= $target ? 'pass' : ($words >= $target * 0.6 ? 'warn' : 'fail'),
            $words . ' words (target ' . $target . ')', 'Thin pages rarely rank; add the missing sections rather than padding.');

        $internal = count($links['internal']);
        $need = $type === 'product' ? 1 : 3;
        $checks[] = self::check('internal_links', 'seo', 'Internal links', $internal >= $need ? 'pass' : ($internal > 0 ? 'warn' : 'fail'),
            $internal . ' internal link(s)', 'Link to at least ' . $need . ' related page(s) on this site, with descriptive anchor text.');

        $missing_alt = $images['missing_alt'];
        $checks[] = self::check('image_alt', 'seo', 'Image alt text', $images['total'] === 0 ? 'skip' : ($missing_alt === 0 ? 'pass' : 'fail'),
            $images['total'] === 0 ? 'no images' : $missing_alt . ' of ' . $images['total'] . ' without alt',
            'Every image needs a descriptive alt text; it is both accessibility and image search.');
        $checks[] = self::check('featured', 'seo', 'Featured image', !empty($in['featured']) ? 'pass' : 'warn',
            !empty($in['featured']) ? 'set' : 'none', 'A featured image drives the social card and the OG preview.');

        $slug_len = mb_strlen($slug);
        $checks[] = self::check('slug', 'seo', 'Slug', $slug === '' ? 'skip' : ($slug_len <= 60 && !str_contains($slug, '--') ? 'pass' : 'warn'),
            $slug === '' ? 'none' : $slug_len . ' characters', 'Keep slugs short and readable; do not change existing ones without a redirect.');

        $dup = !empty($in['duplicate_title']);
        $checks[] = self::check('duplicate', 'seo', 'Unique title', $dup ? 'fail' : 'pass',
            $dup ? 'another page uses this title' : 'unique', 'Two pages with the same title compete against each other.');
        $dup_desc = !empty($in['duplicate_description']);
        $checks[] = self::check('duplicate_description', 'seo', 'Unique description', $dup_desc ? 'fail' : 'pass',
            $dup_desc ? 'shared with another page' : 'unique', 'Duplicate meta descriptions are usually ignored by Google.');

        /* ---- AEO: answer engines ---- */
        $questions = self::question_headings(array_merge($headings['h2'] ?? [], $headings['h3'] ?? []));
        $checks[] = self::check('question_headings', 'aeo', 'Question headings', count($questions) >= 1 ? 'pass' : 'warn',
            count($questions) . ' question heading(s)', 'Phrase at least one H2/H3 as the question a customer actually types.');

        $faq = preg_match('/"@type"\s*:\s*"FAQPage"/i', $html) || preg_match('/<h[23][^>]*>\s*FAQ\b/i', $html);
        $checks[] = self::check('faq', 'aeo', 'FAQ block with schema', $faq ? 'pass' : 'warn',
            $faq ? 'found' : 'none', 'A FAQPage block with 3-6 questions is the cheapest way into answer boxes and AI summaries.');

        $first_words = $first === '' ? 0 : count(preg_split('/\s+/u', self::text_of($first), -1, PREG_SPLIT_NO_EMPTY));
        $checks[] = self::check('answer_first', 'aeo', 'Direct answer up front', $first_words === 0 ? 'fail' : ($first_words <= 90 ? 'pass' : 'warn'),
            $first_words === 0 ? 'no opening paragraph' : $first_words . ' words in the first paragraph',
            'Open with a 40-70 word answer that stands alone; AI summaries quote that paragraph.');

        $scannable = (bool) preg_match('/<(ul|ol|table)\b/i', $html);
        $checks[] = self::check('scannable', 'aeo', 'Lists or table', $scannable ? 'pass' : 'warn',
            $scannable ? 'found' : 'none', 'Steps, specs and comparisons belong in a list or a table; that is what gets extracted.');

        /* ---- GEO: generative engines ---- */
        $head_text = mb_substr($plain, 0, 1200);
        $named = $brand !== '' && mb_stripos($head_text, $brand) !== false;
        $checks[] = self::check('entity_named', 'geo', 'Named entity up front', $named ? 'pass' : 'warn',
            $named ? $brand . ' is named' : 'brand not named in the first 1200 characters',
            'Name the subject and the site explicitly; generative engines quote what they can identify.');

        $facts = preg_match('/\d/', $plain);
        $checks[] = self::check('quotable_facts', 'geo', 'Quotable facts', $facts ? 'pass' : 'warn',
            $facts ? 'numbers present' : 'no numbers', 'Concrete numbers (cm, minutes, prices, dates) are what gets quoted with a citation.');

        $schema = preg_match('/"@context"\s*:\s*"https:\/\/schema\.org"/i', $html) || $keyword !== '';
        $checks[] = self::check('structured_data', 'geo', 'Structured data', $schema ? 'pass' : 'warn',
            $schema ? 'JSON-LD or Rank Math schema' : 'none found', 'Article, Product and FAQPage schema tell engines what the page is.');

        $checks[] = self::check('outbound_citations', 'geo', 'Outbound citations', count($links['external']) >= 1 ? 'pass' : 'warn',
            count($links['external']) . ' external link(s)', 'One or two links to a primary source make the page citable.');

        $checks[] = self::check('fresh', 'geo', 'Recently updated', self::fresh($in['modified'] ?? '') ? 'pass' : 'warn',
            (string) ($in['modified'] ?? 'unknown'), 'Update the page when prices, sizes or availability change; stale pages drop out of AI answers.');

        return $checks;
    }

    /* ---------------------------------------------------------------- scoring */

    /** Weighted score 0-100: pass = 1, warn = 0.5, skip = ignored, fail = 0. */
    public static function score(array $checks): int {
        $sum = 0.0;
        $weight = 0.0;
        foreach ($checks as $c) {
            if (($c['status'] ?? '') === 'skip') continue;
            $w = (float) ($c['weight'] ?? 1);
            $weight += $w;
            $sum += $w * (($c['status'] ?? '') === 'pass' ? 1 : (($c['status'] ?? '') === 'warn' ? 0.5 : 0));
        }
        return $weight > 0 ? (int) round(100 * $sum / $weight) : 100;
    }

    /** Checks with a given status, as ids. */
    public static function ids(array $checks, string $status): array {
        $out = [];
        foreach ($checks as $c) {
            if (($c['status'] ?? '') === $status) $out[] = (string) ($c['id'] ?? '');
        }
        return $out;
    }

    /** Counts by status and the worst five URLs. */
    public static function summarize(array $rows): array {
        $t = ['items' => count($rows), 'fail' => 0, 'warn' => 0, 'pass' => 0, 'needs_work' => 0, 'fixed' => 0];
        foreach ($rows as $r) {
            $t['fail'] += count($r['fails'] ?? []);
            $t['warn'] += count($r['warns'] ?? []);
            $t['pass'] += max(0, count($r['checks'] ?? []) - count($r['fails'] ?? []) - count($r['warns'] ?? []));
            if (!empty($r['fails'])) $t['needs_work']++;
            if (!empty($r['fixed_at'])) $t['fixed']++;
        }
        return $t;
    }

    private static function average(array $rows, array $site): int {
        $scores = array_map(fn($r) => (int) $r['score'], $rows);
        $site_penalty = 0;
        foreach ($site as $s) {
            if (($s['status'] ?? '') === 'fail') $site_penalty += 4;
            if (($s['status'] ?? '') === 'warn') $site_penalty += 2;
        }
        $avg = $scores ? array_sum($scores) / count($scores) : 100;
        return max(0, (int) round($avg - min(20, $site_penalty)));
    }

    /* -------------------------------------------------------- domain checks */

    /** Checks that only make sense once for the whole domain. */
    public static function site_checks(): array {
        $home = home_url('/');
        $out = [];
        $out[] = self::remote_check('llms_txt', 'GEO', 'llms.txt', home . 'llms.txt',
            'An llms.txt tells AI crawlers what the site is and which pages matter.');
        $out[] = self::remote_check('sitemap', 'SEO', 'Sitemap index', home . 'sitemap_index.xml',
            'The sitemap is how crawlers find the pages you did not link.');
        $out[] = self::remote_check('robots', 'SEO', 'robots.txt', home . 'robots.txt',
            'robots.txt must not block the pages you want indexed.');
        $out[] = self::remote_check('feed', 'AEO', 'Product feed', home . 'feed/merchant',
            'A machine-readable product feed is what shopping and AI agents read.');
        return $out;
    }

    /** One HTTP GET, mapped to a check. Network errors become "unknown", never "fail". */
    private static function remote_check(string $id, string $group, string $label, string $url, string $advice): array {
        $res = wp_remote_get($url, ['timeout' => 8, 'redirection' => 3, 'user-agent' => 'SteamgearsAutopilot/self-check']);
        if (is_wp_error($res)) {
            return self::check($id, strtolower($group), $label, 'skip', 'could not be fetched: ' . $res->get_error_message(), $advice);
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $status = $code === 200 ? 'pass' : ($code === 404 ? 'fail' : 'warn');
        return self::check($id, strtolower($group), $label, $status, 'HTTP ' . $code, $advice);
    }

    /* ----------------------------------------------------------------- fixing */

    /**
     * Writes meta for the weakest URLs, worst first. Only meta is written — the
     * body text is left alone; content rewrites belong to SGAP_Studio::optimize().
     */
    public static function fix(array $args = []): array {
        $scan = self::last();
        if (empty($scan['items'])) {
            return ['error' => 'Run a scan first.', 'fixed' => 0, 'items' => [], 'remaining' => 0];
        }
        $limit = max(1, min(self::MAX_FIX, (int) ($args['limit'] ?? 8)));
        $apply = !isset($args['apply']) || (bool) $args['apply'];
        $only = array_filter(array_map('absint', (array) ($args['post_ids'] ?? [])));
        $wanted = ['meta_title', 'meta_description', 'focus_keyword'];

        $queue = [];
        foreach ($scan['items'] as $item) {
            if ($only && !in_array((int) $item['id'], $only, true)) continue;
            if (!$only && (int) $item['score'] >= 90) continue;
            $missing = array_values(array_intersect($wanted, (array) $item['fails']));
            if (!$missing) continue;
            $queue[] = $item;
        }
        if (!$queue) {
            return ['fixed' => 0, 'items' => [], 'remaining' => 0, 'message' => 'Nothing missing meta — the weakest pages are already written.'];
        }
        $batch = array_slice($queue, 0, $limit);
        $done = [];
        $errors = [];
        $language = (string) SGAP_Settings::get('content_language') ?: 'English';
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['focus_keyword', 'meta_title', 'meta_description'], 'properties' => [
            'focus_keyword' => ['type' => 'string'], 'meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string']
        ]];
        foreach ($batch as $item) {
            $post = get_post((int) $item['id']);
            if (!$post) {
                $errors[] = '#' . $item['id'] . ' no longer exists';
                continue;
            }
            $text = wp_trim_words(self::text_of((string) $post->post_content), 700, '…');
            $prompt = "Write the missing SEO meta for this {$item['type']} on steamgears.eu. Language: {$language}. Do not change the page text.\n"
                . "Rules: focus_keyword is what a customer actually searches for; meta_title is 30-60 characters with the keyword first; "
                . "meta_description is 140-155 characters with the keyword, a concrete benefit and no clickbait.\n\n"
                . 'Title: ' . $item['title'] . "\nURL: " . $item['url'] . "\nCurrent meta title: " . ($item['meta_title'] ?: '(none)')
                . "\nCurrent description: " . ($item['description'] ?: '(none)') . "\nPage text:\n" . $text;
            $data = SGAP_AI::json_call(SGAP_AI::SYSTEM . ' You are an expert SEO editor for a small Danish POD shop.', $prompt, $schema, 4000, 'low');
            if (is_wp_error($data)) {
                $errors[] = '#' . $item['id'] . ': ' . $data->get_error_message();
                continue;
            }
            $meta_title = sanitize_text_field((string) ($data['meta_title'] ?? ''));
            $description = sanitize_text_field((string) ($data['meta_description'] ?? ''));
            $keyword = sanitize_text_field((string) ($data['focus_keyword'] ?? ''));
            if ($meta_title === '' || $description === '') {
                $errors[] = '#' . $item['id'] . ': the AI returned an empty title or description';
                continue;
            }
            $applied = $apply ? SGAP_Studio::set_seo_meta((int) $item['id'], $meta_title, $description, $keyword) : [];
            $done[] = ['id' => (int) $item['id'], 'title' => $item['title'], 'meta_title' => $meta_title,
                'meta_description' => $description, 'focus_keyword' => $keyword, 'applied' => $applied];
            // Keep the stored scan honest: mark the row fixed and re-score the meta checks.
            foreach ($scan['items'] as $i => $row) {
                if ((int) $row['id'] !== (int) $item['id']) continue;
                $scan['items'][$i]['meta_title'] = $meta_title;
                $scan['items'][$i]['description'] = $description;
                $scan['items'][$i]['keyword'] = $keyword;
                $scan['items'][$i]['fails'] = array_values(array_diff((array) $row['fails'], $wanted));
                $scan['items'][$i]['fixed_at'] = current_time('mysql');
                $scan['items'][$i]['score'] = min(100, (int) $row['score'] + 15);
            }
        }
        $scan['totals'] = self::summarize($scan['items']);
        $scan['score'] = self::average($scan['items'], $scan['site'] ?: []);
        update_option(self::STORE, $scan, false);
        return [
            'fixed' => count($done),
            'applied' => $apply,
            'items' => $done,
            'errors' => $errors,
            'remaining' => max(0, count($queue) - count($done)),
            'score' => (int) $scan['score']
        ];
    }

    /* ------------------------------------------------------------------- ajax */

    public static function ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_studio', 'nonce');
        $task = sanitize_key((string) ($_POST['task'] ?? ''));
        if ($task === 'scan' || $task === 'site') {
            wp_send_json_success(self::scan(['links' => !empty($_POST['links'])]));
        }
        if ($task === 'fix') {
            $scan = self::scan();
            wp_send_json_success(['scan' => $scan, 'fix' => self::fix(['limit' => absint($_POST['limit'] ?? 8)])]);
        }
        wp_send_json_error('Unknown task.');
    }

    /* -------------------------------------------------------------- abilities */

    public static function register_abilities(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        wp_register_ability(SGAP_Abilities::CATEGORY . '/optimize-site-seo', [
            'label' => 'Optimise SEO for the whole site',
            'description' => 'Scans every published page, post and product in one pass against SEO, AEO (answer engines) and GEO (generative engines) rules, returns a score per URL plus the site-wide checks (llms.txt, sitemap, robots, product feed). With fix=true it also writes Rank Math/Yoast title, description and focus keyword for the weakest URLs — body text is never changed.',
            'category' => SGAP_Abilities::CATEGORY,
            'input_schema' => SGAP_Abilities::object([
                'fix' => ['type' => 'boolean', 'default' => false, 'description' => 'Also write missing meta for the weakest pages.'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25, 'default' => 8, 'description' => 'How many pages to fix when fix=true.'],
                'report_only' => ['type' => 'boolean', 'default' => true, 'description' => 'When false the full per-check detail is returned instead of the summary.']
            ], []),
            'output_schema' => ['type' => 'object'],
            'execute_callback' => function ($input = null) {
                $in = (array) $input;
                $scan = self::scan();
                if (!empty($in['fix'])) {
                    $scan['fix'] = self::fix(['limit' => (int) ($in['limit'] ?? 8)]);
                }
                if (!empty($in['report_only']) || !isset($in['report_only'])) {
                    $scan['items'] = array_slice($scan['items'], 0, 10);
                }
                return $scan;
            },
            'permission_callback' => [SGAP_Abilities::class, 'can_manage'],
            'meta' => SGAP_Abilities::meta(false, false, false)
        ]);
    }

    /* ----------------------------------------------------------------- helpers */

    /** Public shape of a scan: no per-check detail unless asked for. */
    private static function public_summary(array $scan): array {
        $items = [];
        foreach ($scan['items'] as $row) {
            $items[] = ['id' => $row['id'], 'type' => $row['type'], 'title' => $row['title'], 'url' => $row['url'],
                'score' => $row['score'], 'fails' => $row['fails'], 'warns' => $row['warns']];
        }
        return ['at' => $scan['at'], 'score' => $scan['score'], 'totals' => $scan['totals'], 'site' => $scan['site'], 'items' => $items];
    }

    private static function check(string $id, string $group, string $label, string $status, string $detail, string $advice, float $weight = 1.0): array {
        return ['id' => $id, 'group' => $group, 'label' => $label, 'status' => $status, 'detail' => $detail, 'advice' => $advice, 'weight' => $weight];
    }

    /** pass inside the target band, warn inside the wide band, fail outside. */
    private static function band(int $value, int $min, int $max, int $hard_min, int $hard_max): string {
        if ($value >= $min && $value <= $max) return 'pass';
        if ($value >= $hard_min && $value <= $hard_max) return 'warn';
        return 'fail';
    }

    /** Plain text with collapsed whitespace, no tags, no scripts. */
    private static function text_of(string $html): string {
        $html = preg_replace('#<(script|style|iframe)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return array{h1:array,h2:array,h3:array} */
    private static function headings(string $html): array {
        $out = ['h1' => [], 'h2' => [], 'h3' => []];
        foreach (['h1', 'h2', 'h3'] as $tag) {
            if (preg_match_all('#<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>#is', $html, $m)) {
                $out[$tag] = array_values(array_filter(array_map(fn($s) => trim(wp_strip_all_tags($s)), $m[1])));
            }
        }
        return $out;
    }

    /** Which headings read as questions. */
    private static function question_headings(array $headings): array {
        $out = [];
        foreach ($headings as $h) {
            if (preg_match('/\b(why|how|what|when|which|who|where|can|does|do|is|are|should)\b/i', $h) && str_contains($h, '?')) {
                $out[] = $h;
            }
        }
        return $out;
    }

    /** @return array{all:array,int:array,internal:array,external:array} */
    private static function links(string $html): array {
        $internal = [];
        $external = [];
        if (preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\']#i', $html, $m)) {
            foreach ($m[1] as $href) {
                if ($href === '' || str_starts_with($href, '#')) continue;
                if (str_starts_with($href, '/') || str_contains($href, home_url('/')) || str_contains($href, (string) wp_parse_url(home_url('/'), PHP_URL_HOST))) {
                    $internal[] = $href;
                } else {
                    $external[] = $href;
                }
            }
        }
        return ['all' => array_merge($internal, $external), 'int' => $internal, 'internal' => $internal, 'external' => $external];
    }

    /** @return array{total:int,missing_alt:int} */
    private static function images(string $html): array {
        $total = 0;
        $missing = 0;
        if (preg_match_all('#<img\b[^>]*>#i', $html, $m)) {
            foreach ($m[0] as $tag) {
                $total++;
                if (!preg_match('/\balt=["\']\s*\S[^"\']*["\']/i', $tag)) {
                    $missing++;
                }
            }
        }
        return ['total' => $total, 'missing_alt' => $missing];
    }

    /** The opening paragraph, which is what answer engines quote. */
    private static function first_paragraph(string $html): string {
        if (preg_match('#<p\b[^>]*>(.*?)</p>#is', $html, $m)) {
            $text = trim(wp_strip_all_tags($m[1]));
            if (mb_strlen($text) > 40) return $text;
        }
        return '';
    }

    /** Updated within the last year? Accepts a MySQL date, returns false for empty. */
    private static function fresh(string $modified): bool {
        $ts = $modified !== '' ? strtotime($modified) : false;
        return $ts !== false && (time() - $ts) < 365 * DAY_IN_SECONDS;
    }
    /** The admin script for the site-wide card. Nowdoc keeps the JS readable. */
    public static function script(): string {
        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_studio')];
        return 'var SGAP_SEO=' . wp_json_encode($cfg) . ';' . <<<'JS'
(function(){
  var out = document.getElementById('sgap-seo-site-out');
  if(!out){ return; }
  function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
  function post(task, limit){
    var b = new URLSearchParams();
    b.append('action','sgap_seo_site'); b.append('task',task); b.append('nonce',SGAP_SEO.nonce); b.append('limit',limit||8);
    return fetch(SGAP_SEO.ajax, {method:'POST', credentials:'same-origin', body:b}).then(function(r){ return r.json(); });
  }
  function pills(ids, kind){ return (ids||[]).map(function(id){ return '<span class="sgap-pill '+kind+'">'+esc(id)+'</span>'; }).join(' '); }
  function render(d){
    var t = d.totals||{};
    var html = '<div class="sgap-card"><h2>Site score: '+esc(d.score)+' / 100</h2>'
      + '<p>'+esc(t.items||0)+' URLs scanned &middot; '+esc(t.needs_work||0)+' need work &middot; '+esc(t.fail||0)+' failing checks &middot; '+esc(t.warn||0)+' warnings</p>';
    var site = (d.site||[]).map(function(s){ return '<li>'+(s.status==='pass'?'&#10003; ':(s.status==='fail'?'&#10007; ':'&#8226; '))+esc(s.label)+': '+esc(s.detail)+'</li>'; }).join('');
    if(site){ html += '<h3>Whole-domain checks</h3><ul style="list-style:disc;margin-left:18px">'+site+'</ul>'; }
    html += '<table class="widefat striped" style="margin-top:10px"><thead><tr><th style="width:60px">Score</th><th style="width:38%">Page</th><th>Missing or weak</th></tr></thead><tbody>';
    (d.items||[]).forEach(function(it){
      html += '<tr><td><b>'+esc(it.score)+'</b></td><td><a href="'+esc(it.url)+'" target="_blank" rel="noopener">'+esc(it.title)+'</a><br><span class="description">'+esc(it.type)+'</span></td><td>'
        + ((it.fails&&it.fails.length)||(it.warns&&it.warns.length) ? pills(it.fails,'bad')+' '+pills(it.warns,'warn') : '<span class="description">no issues</span>')
        + '</td></tr>';
    });
    html += '</tbody></table></div>';
    return html;
  }
  function renderFix(d){
    var html = '<div class="sgap-card"><h2>'+esc(d.fixed||0)+' page(s) got new meta</h2>';
    if(d.errors && d.errors.length){ html += '<p class="description">'+d.errors.map(esc).join('<br>')+'</p>'; }
    if(d.items && d.items.length){
      html += '<ul style="list-style:disc;margin-left:18px">';
      d.items.forEach(function(it){ html += '<li><b>'+esc(it.title)+'</b>: '+esc(it.meta_title)+' &mdash; '+esc(it.meta_description)+'</li>'; });
      html += '</ul>';
    }
    if(d.remaining){ html += '<p>'+esc(d.remaining)+' page(s) still need meta &mdash; press the button again for the next batch.</p>'; }
    if(d.message){ html += '<p class="description">'+esc(d.message)+'</p>'; }
    html += '</div>';
    return html;
  }
  document.querySelectorAll('.sgap-seo-site-form button[data-task]').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var form = btn.closest('form');
      var limit = form && form.querySelector('[name=limit]') ? form.querySelector('[name=limit]').value : 8;
      var task = btn.getAttribute('data-task') === 'fix' ? 'fix' : 'scan';
      var box = document.createElement('div');
      box.className = 'sgap-card';
      box.innerHTML = '<h2><span class="dashicons dashicons-update sgap-spin"></span>'+(task==='fix'?'Scanning and writing meta':'Scanning every URL')+'</h2>'
        + '<div class="sgap-progress"><progress></progress><p>Reading titles, meta and content\u2026</p></div>';
      out.prepend(box);
      var slow = setTimeout(function(){ var p = box.querySelector('p'); if(p){ p.textContent = 'Still working \u2014 a full scan reads every published page.'; } }, 12000);
      post(task, limit).then(function(j){
        clearTimeout(slow); box.remove();
        if(!j || !j.success){
          var err = document.createElement('div'); err.className = 'sgap-card sgap-bad';
          err.innerHTML = '<h2>Scan failed</h2><p>'+esc((j && j.data) || 'No answer')+'</p>'; out.prepend(err); return;
        }
        if(j.data && j.data.fix){ out.insertAdjacentHTML('afterbegin', renderFix(j.data.fix)); out.insertAdjacentHTML('afterbegin', render(j.data.scan)); }
        else { out.insertAdjacentHTML('afterbegin', render(j.data)); }
      }).catch(function(err){
        clearTimeout(slow); box.remove();
        var e2 = document.createElement('div'); e2.className = 'sgap-card sgap-bad';
        e2.innerHTML = '<h2>Could not reach the site</h2><p>'+esc(err.message)+'</p>'; out.prepend(e2);
      });
    });
  });
})();
JS;
    }
}
