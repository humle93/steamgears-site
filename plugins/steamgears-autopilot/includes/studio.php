<?php
/**
 * Content Studio: SEO-optimised articles, guides and product texts written by
 * OpenRouter and saved as WordPress drafts (with Rank Math / Yoast meta, FAQ and
 * internal links), keyword research, and SEO meta for existing pages. Admin page +
 * abilities (so the AI Chat and Claude over MCP can use them). Loaded from
 * steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Research {
    /**
     * Live Google data for a keyword via SerpApi: "People also ask", related searches
     * and the top-ranking pages. Cached for a week (each search uses SerpApi credit).
     * @return array{keyword: string, questions: string[], related: string[], top: array}|WP_Error
     */
    public static function keyword(string $keyword) {
        $keyword = trim($keyword);
        $key = (string) SGAP_Settings::get('serpapi_key');
        if ($key === '') {
            return new WP_Error('sgap_serpapi_key', 'Add a SerpApi key under Settings → AI for keyword research.');
        }
        if ($keyword === '') {
            return new WP_Error('sgap_keyword', 'Give a keyword.');
        }
        $danish = stripos((string) SGAP_Settings::get('content_language'), 'dan') === 0;
        $cache = 'sgap_serp_' . md5(strtolower($keyword) . ($danish ? '|da' : '|en'));
        $cached = get_transient($cache);
        if (is_array($cached)) {
            return $cached;
        }
        $url = add_query_arg(['engine' => 'google', 'q' => $keyword, 'hl' => $danish ? 'da' : 'en', 'gl' => $danish ? 'dk' : 'us', 'num' => 10, 'api_key' => $key], 'https://serpapi.com/search.json');
        $response = wp_remote_get($url, ['timeout' => 40]);
        if (is_wp_error($response)) {
            return $response;
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ((int) wp_remote_retrieve_response_code($response) !== 200 || !is_array($data) || isset($data['error'])) {
            return new WP_Error('sgap_serpapi', 'SerpApi: ' . (is_array($data) ? (string) ($data['error'] ?? 'HTTP ' . wp_remote_retrieve_response_code($response)) : 'no answer'));
        }
        $result = [
            'keyword' => $keyword,
            'questions' => array_values(array_filter(array_map(fn($q) => (string) ($q['question'] ?? ''), (array) ($data['related_questions'] ?? [])))),
            'related' => array_values(array_filter(array_map(fn($q) => (string) ($q['query'] ?? ''), (array) ($data['related_searches'] ?? [])))),
            'top' => array_map(fn($r) => ['title' => (string) ($r['title'] ?? ''), 'url' => (string) ($r['link'] ?? ''), 'snippet' => (string) ($r['snippet'] ?? '')], array_slice((array) ($data['organic_results'] ?? []), 0, 6))
        ];
        set_transient($cache, $result, WEEK_IN_SECONDS);
        return $result;
    }

    /** Research as prompt text for the writer ('' when there is none). */
    public static function prompt_block(array $r): string {
        $out = "\nLive Google research for \"{$r['keyword']}\" (use it to match search intent and beat these pages):\n";
        if ($r['questions']) $out .= "People also ask (answer these, use the best in the FAQ):\n- " . implode("\n- ", array_slice($r['questions'], 0, 8)) . "\n";
        if ($r['related']) $out .= "Related searches (cover naturally as secondary keywords):\n- " . implode("\n- ", array_slice($r['related'], 0, 8)) . "\n";
        if ($r['top']) $out .= "Currently ranking:\n" . implode("\n", array_map(fn($t) => "- {$t['title']}: {$t['snippet']}", $r['top'])) . "\n";
        return $out;
    }
}

class SGAP_Studio {
    const TYPES = [
        'guide' => 'Step-by-step DIY guide / how-to',
        'article' => 'Blog article (inspiration, history, style, lists)',
        'product' => 'Product description (WooCommerce product text)'
    ];
    const LENGTHS = ['short' => 700, 'medium' => 1300, 'long' => 2200];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 21);
        add_action('wp_ajax_sgap_studio', [__CLASS__, 'ajax']);
        add_action('wp_abilities_api_init', [__CLASS__, 'register_abilities']);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'Content Studio', 'Content Studio', 'manage_options', 'sgap-studio', [__CLASS__, 'page'], 2);
    }

    /* ---- writing ---- */

    /** Published posts/products the AI may link to: [title, url]. */
    public static function link_targets(int $limit = 40): array {
        $types = array_values(array_filter(['post', 'page', 'product'], 'post_type_exists'));
        $posts = get_posts(['post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => $limit, 'orderby' => 'modified', 'no_found_rows' => true]);
        return array_map(fn($p) => ['title' => get_the_title($p), 'url' => get_permalink($p), 'type' => $p->post_type], $posts);
    }

    public static function schema(): array {
        $str = ['type' => 'string'];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'slug', 'meta_title', 'meta_description', 'focus_keyword', 'excerpt', 'content_html', 'faq', 'tags'],
            'properties' => [
                'title' => $str,
                'slug' => $str,
                'meta_title' => $str,
                'meta_description' => $str,
                'focus_keyword' => $str,
                'excerpt' => $str,
                'content_html' => $str,
                'faq' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['question', 'answer'], 'properties' => ['question' => $str, 'answer' => $str]]],
                'tags' => ['type' => 'array', 'items' => $str]
            ]
        ];
    }

    public static function prompt(array $a, array $links): string {
        $words = self::LENGTHS[$a['length']] ?? 1300;
        $list = implode("\n", array_map(fn($l) => "- {$l['title']} ({$l['type']}): {$l['url']}", $links));
        return "Write a {$a['type']} for steamgears.eu.\n"
            . 'Type: ' . (self::TYPES[$a['type']] ?? $a['type']) . "\n"
            . "Topic: {$a['topic']}\n"
            . ($a['keyword'] !== '' ? "Focus keyword: {$a['keyword']}\n" : "Choose the best focus keyword (what people actually search for).\n")
            . "Language: {$a['language']}\n"
            . "Length: about {$words} words of body text.\n"
            . ($a['notes'] !== '' ? "Extra instructions: {$a['notes']}\n" : '')
            . "\nSEO rules:\n"
            . "- title: compelling H1 with the focus keyword near the start, max 65 characters.\n"
            . "- meta_title: max 60 characters incl. the keyword; meta_description: 140-155 characters, keyword included, ends with a reason to click.\n"
            . "- slug: short, lowercase, hyphens, keyword-based.\n"
            . "- content_html: clean HTML only (<h2>, <h3>, <p>, <ul>/<ol>, <li>, <strong>, <a>, <blockquote>), no <h1>, no inline styles. "
            . "Keyword in the first 100 words and in at least one <h2>; short paragraphs; a scannable structure; for guides a materials list and numbered steps; "
            . "end with a short conclusion and call to action. Do not include the FAQ in content_html.\n"
            . "- Link 2-4 times naturally to the most relevant pages below (use their exact URLs). Never invent URLs, prices, stock or facts.\n"
            . "- faq: 3-5 real questions people ask, concise answers.\n"
            . "- tags: 3-6 short tags. excerpt: 1-2 sentences.\n"
            . "\nPages on the site you may link to:\n" . ($list !== '' ? $list : '(none)')
            // P8: the same measured brief the social writer gets, so a guide is planned on what
            // the shop has measured — not on the catalogue as it looked when this prompt was written.
            . (class_exists('SGAP_Coach') ? SGAP_Coach::block() : '');
    }

    /** FAQ as HTML plus FAQPage JSON-LD (Rank Math/Yoast may add their own; this works without them). */
    public static function faq_html(array $faq): string {
        $faq = array_values(array_filter($faq, fn($f) => is_array($f) && trim((string) ($f['question'] ?? '')) !== '' && trim((string) ($f['answer'] ?? '')) !== ''));
        if (!$faq) {
            return '';
        }
        $html = "\n<h2>FAQ</h2>\n";
        $entities = [];
        foreach ($faq as $f) {
            $q = wp_strip_all_tags((string) $f['question']);
            $a = wp_strip_all_tags((string) $f['answer']);
            $html .= '<h3>' . esc_html($q) . "</h3>\n<p>" . esc_html($a) . "</p>\n";
            $entities[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
        $html .= '<script type="application/ld+json">' . wp_json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities]) . '</script>';
        return $html;
    }

    /** SEO plugin meta for a post (Rank Math and/or Yoast, whichever is active; both when unsure). */
    public static function set_seo_meta(int $post_id, string $title, string $description, string $keyword): array {
        $rank_math = defined('RANK_MATH_VERSION') || class_exists('RankMath');
        $yoast = defined('WPSEO_VERSION');
        $set = [];
        if ($rank_math || !$yoast) {
            update_post_meta($post_id, 'rank_math_title', $title);
            update_post_meta($post_id, 'rank_math_description', $description);
            update_post_meta($post_id, 'rank_math_focus_keyword', $keyword);
            $set[] = 'Rank Math';
        }
        if ($yoast || !$rank_math) {
            update_post_meta($post_id, '_yoast_wpseo_title', $title);
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $keyword);
            $set[] = 'Yoast';
        }
        return $set;
    }

    /**
     * Writes an article/guide/product text and saves it as a draft.
     * @return array|WP_Error
     */
    public static function write(array $args) {
        $a = [
            'topic' => trim(sanitize_text_field((string) ($args['topic'] ?? ''))),
            'keyword' => trim(sanitize_text_field((string) ($args['keyword'] ?? ''))),
            'type' => array_key_exists((string) ($args['type'] ?? ''), self::TYPES) ? (string) $args['type'] : 'article',
            'length' => array_key_exists((string) ($args['length'] ?? ''), self::LENGTHS) ? (string) $args['length'] : 'medium',
            'language' => trim(sanitize_text_field((string) ($args['language'] ?? ''))) ?: ((string) SGAP_Settings::get('content_language') ?: 'English'),
            'notes' => trim(sanitize_textarea_field((string) ($args['notes'] ?? '')))
        ];
        if ($a['topic'] === '') {
            return new WP_Error('sgap_topic', 'Give a topic.');
        }
        $system = SGAP_AI::SYSTEM . ' You are also an expert SEO copywriter: helpful, original, specific content that answers search intent (E-E-A-T), never keyword stuffing.';
        $system = str_replace('Write in English. ', '', $system);
        $prompt = self::prompt($a, self::link_targets());
        $research = null;
        if (SGAP_Settings::get('serpapi_key') !== '') {
            $research = SGAP_Research::keyword($a['keyword'] !== '' ? $a['keyword'] : $a['topic']);
            if (!is_wp_error($research)) {
                $prompt .= SGAP_Research::prompt_block($research);
            }
        }
        $data = SGAP_AI::json_call($system, $prompt, self::schema(), 20000);
        if (is_wp_error($data)) {
            return $data;
        }
        // Drop script/style blocks with their contents, then keep only post-safe HTML.
        $content = wp_kses_post((string) preg_replace('#<(script|style|iframe)\b[^>]*>.*?</\1>#is', '', (string) ($data['content_html'] ?? '')));
        if (trim(wp_strip_all_tags($content)) === '') {
            return new WP_Error('sgap_empty', 'The AI returned no article text.');
        }
        $content .= self::faq_html((array) ($data['faq'] ?? []));
        $post_type = $a['type'] === 'product' && post_type_exists('product') ? 'product' : 'post';
        $post_id = wp_insert_post([
            'post_type' => $post_type,
            'post_status' => 'draft',
            'post_title' => sanitize_text_field((string) ($data['title'] ?? $a['topic'])),
            'post_name' => sanitize_title((string) ($data['slug'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($data['excerpt'] ?? '')),
            'post_content' => $content
        ], true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        $keyword = sanitize_text_field((string) ($data['focus_keyword'] ?? $a['keyword']));
        $seo = self::set_seo_meta($post_id, sanitize_text_field((string) ($data['meta_title'] ?? '')), sanitize_text_field((string) ($data['meta_description'] ?? '')), $keyword);
        if ($post_type === 'post') {
            wp_set_post_tags($post_id, array_map('sanitize_text_field', array_slice((array) ($data['tags'] ?? []), 0, 8)));
        }
        update_post_meta($post_id, '_sgap_studio', ['type' => $a['type'], 'topic' => $a['topic'], 'model' => SGAP_AI::model(), 'at' => current_time('mysql')]);
        $result = [
            'post_id' => (int) $post_id,
            'post_type' => $post_type,
            'status' => 'draft',
            'title' => get_the_title($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'preview_url' => get_preview_post_link($post_id),
            'focus_keyword' => $keyword,
            'meta_title' => (string) ($data['meta_title'] ?? ''),
            'meta_description' => (string) ($data['meta_description'] ?? ''),
            'words' => str_word_count(wp_strip_all_tags($content)),
            'seo_meta_saved_for' => $seo,
            'internal_links' => substr_count($content, home_url('/')),
            'keyword_research' => $research === null ? 'off (no SerpApi key)' : (is_wp_error($research) ? $research->get_error_message() : count($research['questions']) . ' questions, ' . count($research['related']) . ' related searches used')
        ];
        return $result;
    }

    /**
     * The prompt for one page's SEO rewrite. P8: the measured brief is appended, so the editor
     * plans on what the shop has measured rather than on a fixed picture of the catalogue.
     */
    public static function optimize_prompt($post): string {
        $text = wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $post->post_content)), 900, '…');
        return "Improve the SEO of this {$post->post_type} on steamgears.eu. Keep the language of the page.\n"
            . "Return: focus_keyword (what people search for), meta_title (max 60 characters, keyword first), meta_description (140-155 characters, keyword, reason to click), "
            . "and 3-6 concrete suggestions to improve the content itself (headings, missing sections, internal links, images, alt texts).\n\n"
            . 'Title: ' . get_the_title($post) . "\nURL: " . get_permalink((int) $post->ID) . "\nCurrent text:\n" . $text
            . (class_exists('SGAP_Coach') ? SGAP_Coach::block() : '');
    }

    /**
     * New SEO title/description/focus keyword for an existing post, page or product.
     * @return array|WP_Error
     */
    public static function optimize(int $post_id, bool $apply = true) {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['post', 'page', 'product'], true)) {
            return new WP_Error('sgap_no_post', "No post, page or product #{$post_id}.");
        }
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['focus_keyword', 'meta_title', 'meta_description', 'suggestions'], 'properties' => [
            'focus_keyword' => ['type' => 'string'], 'meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string'],
            'suggestions' => ['type' => 'array', 'items' => ['type' => 'string']]
        ]];
        $prompt = self::optimize_prompt($post);
        $data = SGAP_AI::json_call(SGAP_AI::SYSTEM . ' You are an expert SEO editor.', $prompt, $schema, 8000);
        if (is_wp_error($data)) {
            return $data;
        }
        $out = [
            'post_id' => $post_id,
            'title' => get_the_title($post),
            'focus_keyword' => (string) ($data['focus_keyword'] ?? ''),
            'meta_title' => (string) ($data['meta_title'] ?? ''),
            'meta_description' => (string) ($data['meta_description'] ?? ''),
            'suggestions' => array_values(array_map('strval', (array) ($data['suggestions'] ?? []))),
            'applied' => false
        ];
        if ($apply) {
            $out['applied'] = self::set_seo_meta($post_id, sanitize_text_field($out['meta_title']), sanitize_text_field($out['meta_description']), sanitize_text_field($out['focus_keyword']));
        }
        return $out;
    }

    /* ---- abilities ---- */

    public static function register_abilities(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        $defs = [
            'write-article' => ['Write an SEO article or guide',
                'Writes an SEO-optimised blog article, step-by-step guide or product description for steamgears.eu and saves it as a DRAFT: title, slug, Rank Math/Yoast meta title + description + focus keyword, headings, internal links to existing pages, FAQ (with FAQ schema) and tags. Takes 1-3 minutes. Returns the edit link.',
                ['topic' => ['type' => 'string', 'minLength' => 3], 'keyword' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => array_keys(self::TYPES), 'default' => 'article'],
                 'length' => ['type' => 'string', 'enum' => array_keys(self::LENGTHS), 'default' => 'medium'], 'language' => ['type' => 'string', 'description' => 'Default: the Content language setting (English).'],
                 'notes' => ['type' => 'string']],
                ['topic'], fn($i) => self::write((array) $i)],
            'keyword-research' => ['Keyword research (Google)',
                'Live Google data for a keyword via SerpApi: People also ask questions, related searches and the top-ranking pages. Use before writing to pick the right angle and keyword. Cached for a week.',
                ['keyword' => ['type' => 'string', 'minLength' => 2]], ['keyword'], fn($i) => SGAP_Research::keyword((string) $i['keyword'])],
            'optimize-seo' => ['Optimise SEO of a page',
                'Suggests a focus keyword, meta title and meta description for an existing post, page or product, plus concrete content improvements, and saves the meta to Rank Math/Yoast (apply=false only suggests).',
                ['post_id' => ['type' => 'integer', 'minimum' => 1], 'apply' => ['type' => 'boolean', 'default' => true]],
                ['post_id'], fn($i) => self::optimize((int) $i['post_id'], !isset($i['apply']) || (bool) $i['apply'])]
        ];
        foreach ($defs as $name => [$label, $description, $props, $required, $cb]) {
            wp_register_ability(SGAP_Abilities::CATEGORY . '/' . $name, [
                'label' => $label,
                'description' => $description,
                'category' => SGAP_Abilities::CATEGORY,
                'input_schema' => SGAP_Abilities::object($props, $required),
                'output_schema' => ['type' => 'object'],
                'execute_callback' => static fn($input = null) => call_user_func($cb, $input),
                'permission_callback' => [SGAP_Abilities::class, 'can_manage'],
                'meta' => SGAP_Abilities::meta(false, false, false)
            ]);
        }
    }

    /* ---- admin page ---- */

    public static function ajax(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_studio', 'nonce');
        $task = sanitize_key((string) ($_POST['task'] ?? ''));
        $in = wp_unslash($_POST);
        if ($task === 'write') {
            $r = self::write(['topic' => $in['topic'] ?? '', 'keyword' => $in['keyword'] ?? '', 'type' => $in['type'] ?? '', 'length' => $in['length'] ?? '',
                'language' => $in['language'] ?? '', 'notes' => $in['notes'] ?? '']);
        } elseif ($task === 'optimize') {
            $r = self::optimize(absint($in['post_id'] ?? 0), true);
        } else {
            $r = new WP_Error('sgap_task', 'Unknown task.');
        }
        is_wp_error($r) ? wp_send_json_error($r->get_error_message()) : wp_send_json_success($r);
    }

    public static function page(): void {
        SGAP_Admin::open('Content Studio', 'SEO articles, guides and product texts as drafts, keyword research, and SEO fixes for existing pages.');
        $ai_ok = SGAP_Settings::get('ai_api_key') !== '' && SGAP_AI::model() !== '';
        echo '<div class="sgap-pills">' . SGAP_UI::pill('Writer: ' . SGAP_AI::provider_name() . ' · ' . (SGAP_AI::model() ?: 'no model picked'), $ai_ok ? 'ok' : 'bad')
            . SGAP_UI::pill('Keyword research: ' . (SGAP_Settings::get('serpapi_key') !== '' ? 'SerpApi on' : 'off – add a SerpApi key'), SGAP_Settings::get('serpapi_key') !== '' ? 'ok' : 'warn') . '</div>';
        $recent = get_posts(['post_type' => array_values(array_filter(['post', 'page', 'product'], 'post_type_exists')), 'post_status' => ['publish', 'draft'], 'posts_per_page' => 60, 'orderby' => 'modified', 'no_found_rows' => true]);
        $options = '';
        foreach ($recent as $p) {
            $options .= '<option value="' . (int) $p->ID . '">' . esc_html(get_the_title($p) . ' (' . $p->post_type . ($p->post_status === 'draft' ? ', draft' : '') . ')') . '</option>';
        }
        SGAP_UI::section('Write', 'New content: a draft you edit and publish yourself. Nothing is published from here.');
        SGAP_UI::card_open('Write an article or guide', 'edit-page');
        echo '<form class="sgap-studio-form" data-task="write">'
            . '<p><label><strong>Topic</strong><br><input name="topic" class="large-text" required placeholder="e.g. How to make steampunk goggles from scrap brass"></label></p>'
            . '<p><label><strong>Focus keyword</strong> <span class="description">(optional – the AI picks one if empty)</span><br><input name="keyword" class="regular-text" placeholder="steampunk goggles"></label></p>'
            . '<p class="sgap-row"><label>Type<br><select name="type">';
        foreach (self::TYPES as $k => $label) {
            echo '<option value="' . esc_attr($k) . '">' . esc_html(ucfirst($k)) . '</option>';
        }
        echo '</select></label> <label>Length<br><select name="length"><option value="short">Short (~700 words)</option><option value="medium" selected>Medium (~1300)</option><option value="long">Long (~2200)</option></select></label>'
            . ' <label>Language<br><input name="language" class="small-text" style="width:110px" value="' . esc_attr((string) SGAP_Settings::get('content_language') ?: 'English') . '"></label></p>'
            . '<p><label>Extra instructions <span class="description">(optional)</span><br><textarea name="notes" rows="2" class="large-text" placeholder="Mention our brass goggles product, beginner level, …"></textarea></label></p>'
            . '<p><button class="button button-primary"><span class="dashicons dashicons-edit"></span>Write draft</button></p></form>';
        SGAP_UI::card_close();
        echo '<div id="sgap-studio-out"></div>';

        SGAP_UI::section('Fix', 'Pages you already have: better meta for one of them, or one pass over the whole site.');
        echo '<div class="sgap-grid"><div>';
        SGAP_UI::card_open('Optimise SEO of a page', 'search');
        echo '<form class="sgap-studio-form" data-task="optimize"><p><select name="post_id" style="max-width:100%">' . $options . '</select></p>'
            . '<p><button class="button"><span class="dashicons dashicons-search"></span>Optimise meta</button></p></form>';
        SGAP_UI::card_close();
        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_studio')];
        SGAP_Assets::page_script('studio', 'SGAP_STUDIO', $cfg);
        /* ---- whole-site SEO / AEO / GEO ---- */
        SGAP_UI::card_open('Optimise the whole site', 'admin-site-alt3');
        echo '<p>One pass over every published page, post and product: SEO, answer engines (AEO) and generative engines (GEO). '
            . 'The scan reads titles, meta, headings, links and images — the button with the AI also writes the missing meta.'
            . '</p>'
            . '<form class="sgap-seo-site-form"><p class="sgap-row"><label>Pages to fix per round '
            . '<input type="number" name="limit" value="8" min="1" max="25" class="small-text"></label></p>'
            . '<p><button class="button" data-task="scan"><span class="dashicons dashicons-search"></span>Scan the whole site</button> '
            . '<button class="button button-primary" data-task="fix"><span class="dashicons dashicons-update"></span>Scan and fix meta</button></p>'
            . '<p class="description">Body text is never rewritten here — only the meta title, description and focus keyword. '
            . 'Content suggestions stay in &ldquo;Optimise SEO of a page&rdquo; above.</p></form>';
        SGAP_UI::card_close();
        echo '</div><div>';
        SGAP_UI::card_open('What the score measures', 'info-outline');
        echo '<p class="description"><b>SEO</b> — title and meta length, one H1, enough text for the type, internal links, image alt text, unique title and description across the site.</p>'
            . '<p class="description"><b>AEO</b> — question-shaped headings, an FAQ block with FAQPage schema, a direct answer in the first paragraph, lists or tables.</p>'
            . '<p class="description"><b>GEO</b> — named entities, quotable numbers, structured data, outbound citations, freshness, and the whole-domain checks: llms.txt, sitemap, robots.txt and the product feed.</p>'
            . '<p class="description">The scan is free: database and HTTP only. The AI is called only for pages that are actually missing meta.</p>';
        SGAP_UI::card_close();
        echo '</div></div><div id="sgap-seo-site-out"></div>';
        if (class_exists('SGAP_Site_SEO')) {
            SGAP_Assets::page_script('seo', 'SGAP_SEO', ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_studio')]);
        }
        SGAP_Admin::close();
    }
}
