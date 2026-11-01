<?php
/**
 * Content Studio: SEO-optimised articles, guides and product texts written by the
 * configured AI and saved as WordPress drafts (with Rank Math / Yoast meta, FAQ,
 * internal links and a featured image), AI images through AIMLAPI saved to the
 * media library, and SEO meta for existing pages. Admin page + abilities (so the
 * AI Chat and Claude over MCP can use them). Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Images {
    const ENDPOINT = 'https://api.aimlapi.com/v1/images/generations';
    const DEFAULT_MODEL = 'bytedance/seedream-5-0-pro';

    /** AIMLAPI key: the image key if set, else the AI key when the AI provider is AIMLAPI. */
    public static function key(): string {
        $key = (string) SGAP_Settings::get('image_api_key');
        if ($key === '' && SGAP_AI::provider() === 'aimlapi') {
            $key = (string) SGAP_Settings::get('ai_api_key');
        }
        return $key;
    }

    public static function model(): string {
        return trim((string) SGAP_Settings::get('image_model')) ?: self::DEFAULT_MODEL;
    }

    /** Request body for the chosen model (sizes are passed the way each family expects). */
    public static function body(string $model, string $prompt, int $width, int $height): array {
        $body = ['model' => $model, 'prompt' => $prompt];
        if (strpos($model, 'bytedance/') === 0) {
            // Seedream: both sides 1024–2752.
            $body['image_size'] = ['width' => max(1024, min(2752, $width)), 'height' => max(1024, min(2752, $height))];
            $body['watermark'] = false;
        } elseif (strpos($model, 'flux') !== false || strpos($model, 'blackforestlabs/') === 0) {
            $body['image_size'] = ['width' => max(256, min(2048, $width)), 'height' => max(256, min(2048, $height))];
        } else {
            $body['size'] = $width > $height ? '1536x1024' : ($width < $height ? '1024x1536' : '1024x1024');
        }
        return $body;
    }

    /**
     * Generates an image and saves it to the media library.
     * @return array{attachment_id: int, url: string}|WP_Error
     */
    public static function create(string $prompt, string $alt = '', int $width = 1600, int $height = 1024, int $parent = 0) {
        $key = self::key();
        if ($key === '') {
            return new WP_Error('sgap_image_key', 'Images need an AIMLAPI key: choose AIMLAPI as AI provider, or set "Image api key" in Settings → AI content.');
        }
        $prompt = trim($prompt);
        if ($prompt === '') {
            return new WP_Error('sgap_image_prompt', 'Describe the image.');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        $model = self::model();
        $response = wp_remote_post(self::ENDPOINT, [
            'timeout' => 180,
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'body' => wp_json_encode(self::body($model, $prompt, $width, $height))
        ]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            $message = is_array($data) ? (string) ($data['error']['message'] ?? $data['message'] ?? '') : '';
            return new WP_Error('sgap_image_http', "AIMLAPI image ({$model}): " . ($message !== '' ? $message : "HTTP {$code}"));
        }
        $first = $data['data'][0] ?? $data['images'][0] ?? null;
        $url = is_array($first) ? (string) ($first['url'] ?? '') : '';
        $b64 = is_array($first) ? (string) ($first['b64_json'] ?? '') : '';
        if ($url === '' && $b64 === '') {
            return new WP_Error('sgap_image_empty', 'AIMLAPI returned no image.');
        }
        return self::save($url, $b64, $prompt, $alt, $parent);
    }

    /** @return array{attachment_id: int, url: string}|WP_Error */
    public static function save(string $url, string $b64, string $prompt, string $alt, int $parent) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        if ($url !== '') {
            $tmp = download_url($url, 120);
            if (is_wp_error($tmp)) {
                return $tmp;
            }
        } else {
            $tmp = wp_tempnam('sgap-image');
            file_put_contents($tmp, base64_decode($b64));
        }
        $type = function_exists('mime_content_type') ? (string) @mime_content_type($tmp) : '';
        $ext = ['image/png' => 'png', 'image/webp' => 'webp', 'image/jpeg' => 'jpg'][$type] ?? (preg_match('/\.(png|webp|jpe?g)(\?|$)/i', $url, $m) ? strtolower($m[1]) : 'jpg');
        $name = sanitize_title(wp_trim_words($alt !== '' ? $alt : $prompt, 8, '')) ?: 'steamgears-ai-image';
        $file = ['name' => $name . '.' . $ext, 'tmp_name' => $tmp];
        $id = media_handle_sideload($file, $parent, $alt !== '' ? $alt : wp_trim_words($prompt, 12, ''));
        if (is_wp_error($id)) {
            @unlink($tmp);
            return $id;
        }
        update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field($alt !== '' ? $alt : wp_trim_words($prompt, 16, '')));
        update_post_meta($id, '_sgap_ai_image', ['model' => self::model(), 'prompt' => $prompt]);
        return ['attachment_id' => (int) $id, 'url' => (string) wp_get_attachment_url($id)];
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
            'required' => ['title', 'slug', 'meta_title', 'meta_description', 'focus_keyword', 'excerpt', 'content_html', 'faq', 'tags', 'image_prompt', 'image_alt'],
            'properties' => [
                'title' => $str,
                'slug' => $str,
                'meta_title' => $str,
                'meta_description' => $str,
                'focus_keyword' => $str,
                'excerpt' => $str,
                'content_html' => $str,
                'faq' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['question', 'answer'], 'properties' => ['question' => $str, 'answer' => $str]]],
                'tags' => ['type' => 'array', 'items' => $str],
                'image_prompt' => $str,
                'image_alt' => $str
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
            . "- image_prompt: a detailed, photographic description of the ideal featured image (no text in the image, no logos); image_alt: a descriptive alt text with the keyword.\n"
            . "\nPages on the site you may link to:\n" . ($list !== '' ? $list : '(none)');
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
            'notes' => trim(sanitize_textarea_field((string) ($args['notes'] ?? ''))),
            'image' => !isset($args['image']) || (bool) $args['image']
        ];
        if ($a['topic'] === '') {
            return new WP_Error('sgap_topic', 'Give a topic.');
        }
        $system = SGAP_AI::SYSTEM . ' You are also an expert SEO copywriter: helpful, original, specific content that answers search intent (E-E-A-T), never keyword stuffing.';
        $system = str_replace('Write in English. ', '', $system);
        $data = SGAP_AI::json_call($system, self::prompt($a, self::link_targets()), self::schema(), 20000, 'medium');
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
            'image' => null
        ];
        if ($a['image'] && trim((string) ($data['image_prompt'] ?? '')) !== '') {
            $image = SGAP_Images::create((string) $data['image_prompt'], (string) ($data['image_alt'] ?? ''), 1600, 1024, (int) $post_id);
            if (is_wp_error($image)) {
                $result['image'] = ['error' => $image->get_error_message()];
            } else {
                set_post_thumbnail($post_id, $image['attachment_id']);
                $result['image'] = $image;
            }
        }
        return $result;
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
        $text = wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 900, '…');
        $prompt = "Improve the SEO of this {$post->post_type} on steamgears.eu. Keep the language of the page.\n"
            . "Return: focus_keyword (what people search for), meta_title (max 60 characters, keyword first), meta_description (140-155 characters, keyword, reason to click), "
            . "and 3-6 concrete suggestions to improve the content itself (headings, missing sections, internal links, images, alt texts).\n\n"
            . 'Title: ' . get_the_title($post) . "\nURL: " . get_permalink($post) . "\nCurrent text:\n" . $text;
        $data = SGAP_AI::json_call(SGAP_AI::SYSTEM . ' You are an expert SEO editor.', $prompt, $schema, 8000, 'low');
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

    /**
     * An AI image for a post (featured image), a queued social post or an AI draft, or just for the library.
     * @return array|WP_Error
     */
    public static function image(array $args) {
        $prompt = trim((string) ($args['prompt'] ?? ''));
        $post_id = (int) ($args['post_id'] ?? 0);
        $queue_id = (int) ($args['queue_id'] ?? 0);
        $shape = (string) ($args['shape'] ?? 'landscape');
        [$w, $h] = ['square' => [1024, 1024], 'portrait' => [1024, 1536], 'pin' => [1024, 1536]][$shape] ?? [1600, 1024];
        if ($prompt === '' && $post_id) {
            $prompt = 'A photographic, atmospheric steampunk scene illustrating: ' . get_the_title($post_id) . '. Brass, copper, leather and warm light; no text, no logos.';
        }
        $image = SGAP_Images::create($prompt, sanitize_text_field((string) ($args['alt'] ?? '')), $w, $h, $post_id);
        if (is_wp_error($image)) {
            return $image;
        }
        if ($post_id && get_post($post_id)) {
            set_post_thumbnail($post_id, $image['attachment_id']);
            $image['featured_image_of'] = $post_id;
        }
        if ($queue_id) {
            global $wpdb;
            $wpdb->update($wpdb->prefix . 'sgap_queue', ['image_url' => $image['url']], ['id' => $queue_id]);
            $image['queue_post'] = $queue_id;
        }
        $image['model'] = SGAP_Images::model();
        return $image;
    }

    /* ---- abilities ---- */

    public static function register_abilities(): void {
        if (!function_exists('wp_register_ability')) {
            return;
        }
        $defs = [
            'write-article' => ['Write an SEO article or guide',
                'Writes an SEO-optimised blog article, step-by-step guide or product description for steamgears.eu and saves it as a DRAFT: title, slug, Rank Math/Yoast meta title + description + focus keyword, headings, internal links to existing pages, FAQ (with FAQ schema), tags and an AI featured image. Takes 1-3 minutes. Returns the edit link.',
                ['topic' => ['type' => 'string', 'minLength' => 3], 'keyword' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => array_keys(self::TYPES), 'default' => 'article'],
                 'length' => ['type' => 'string', 'enum' => array_keys(self::LENGTHS), 'default' => 'medium'], 'language' => ['type' => 'string', 'description' => 'Default: the Content language setting (English).'],
                 'notes' => ['type' => 'string'], 'image' => ['type' => 'boolean', 'default' => true]],
                ['topic'], fn($i) => self::write((array) $i)],
            'generate-image' => ['Generate an AI image',
                'Creates an image with AIMLAPI (default model Seedream 5.0 Pro) and saves it to the media library. With post_id it becomes that post\'s featured image (prompt optional then); with queue_id it becomes the image of a queued social post (needed for Instagram/Pinterest).',
                ['prompt' => ['type' => 'string'], 'alt' => ['type' => 'string'], 'post_id' => ['type' => 'integer', 'minimum' => 1], 'queue_id' => ['type' => 'integer', 'minimum' => 1],
                 'shape' => ['type' => 'string', 'enum' => ['landscape', 'square', 'portrait', 'pin'], 'default' => 'landscape']],
                [], fn($i) => self::image((array) $i)],
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
                'execute_callback' => $cb,
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
                'language' => $in['language'] ?? '', 'notes' => $in['notes'] ?? '', 'image' => !empty($in['image'])]);
        } elseif ($task === 'image') {
            $r = self::image(['prompt' => $in['prompt'] ?? '', 'shape' => $in['shape'] ?? 'landscape', 'post_id' => absint($in['post_id'] ?? 0)]);
        } elseif ($task === 'optimize') {
            $r = self::optimize(absint($in['post_id'] ?? 0), true);
        } else {
            $r = new WP_Error('sgap_task', 'Unknown task.');
        }
        is_wp_error($r) ? wp_send_json_error($r->get_error_message()) : wp_send_json_success($r);
    }

    public static function page(): void {
        SGAP_Admin::open('Content Studio', 'SEO articles, guides and product texts as drafts, AI images, and SEO fixes for existing pages.');
        $ai_ok = SGAP_Settings::get('ai_api_key') !== '';
        $img_ok = SGAP_Images::key() !== '';
        echo '<div class="sgap-pills">' . SGAP_UI::pill('Writer: ' . SGAP_AI::provider_name() . ' · ' . (SGAP_AI::model() ?: '?'), $ai_ok ? 'ok' : 'bad')
            . SGAP_UI::pill('Images: ' . ($img_ok ? SGAP_Images::model() : 'needs an AIMLAPI key'), $img_ok ? 'ok' : 'warn') . '</div>';
        $recent = get_posts(['post_type' => array_values(array_filter(['post', 'page', 'product'], 'post_type_exists')), 'post_status' => ['publish', 'draft'], 'posts_per_page' => 60, 'orderby' => 'modified', 'no_found_rows' => true]);
        $options = '';
        foreach ($recent as $p) {
            $options .= '<option value="' . (int) $p->ID . '">' . esc_html(get_the_title($p) . ' (' . $p->post_type . ($p->post_status === 'draft' ? ', draft' : '') . ')') . '</option>';
        }
        echo '<div class="sgap-grid"><div>';
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
            . '<p><label><input type="checkbox" name="image" value="1"' . ($img_ok ? ' checked' : ' disabled') . '> Create an AI featured image</label></p>'
            . '<p><button class="button button-primary"><span class="dashicons dashicons-edit"></span>Write draft</button></p></form>';
        SGAP_UI::card_close();
        echo '</div><div>';
        SGAP_UI::card_open('AI image', 'format-image');
        echo '<form class="sgap-studio-form" data-task="image">'
            . '<p><label><strong>Describe the image</strong><br><textarea name="prompt" rows="3" class="large-text" placeholder="Close-up of brass steampunk goggles on a leather workbench, warm window light, photographic"></textarea></label></p>'
            . '<p class="sgap-row"><label>Shape<br><select name="shape"><option value="landscape">Landscape (blog)</option><option value="square">Square (Instagram)</option><option value="pin">Tall (Pinterest)</option></select></label>'
            . ' <label>Featured image of<br><select name="post_id"><option value="0">– just the media library –</option>' . $options . '</select></label></p>'
            . '<p><button class="button button-primary"' . ($img_ok ? '' : ' disabled') . '><span class="dashicons dashicons-format-image"></span>Create image</button></p></form>';
        SGAP_UI::card_close();
        SGAP_UI::card_open('Optimise SEO of a page', 'search');
        echo '<form class="sgap-studio-form" data-task="optimize"><p><select name="post_id" style="max-width:100%">' . $options . '</select></p>'
            . '<p><button class="button"><span class="dashicons dashicons-search"></span>Optimise meta</button></p></form>';
        SGAP_UI::card_close();
        echo '</div></div><div id="sgap-studio-out"></div>';
        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_studio')];
        echo '<script>(function(){var C=' . wp_json_encode($cfg) . ',out=document.getElementById("sgap-studio-out");'
            . 'function esc(s){return String(s==null?"":s).replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"}[c];});}'
            . 'var steps={write:["Researching the topic…","Writing the draft…","Adding headings, FAQ and links…","Creating the featured image…","Saving the draft…"],image:["Painting the image…","Saving to the media library…"],optimize:["Reading the page…","Writing better meta…"]};'
            . 'function card(html){var d=document.createElement("div");d.className="sgap-card";d.innerHTML=html;out.prepend(d);return d;}'
            . 'document.querySelectorAll(".sgap-studio-form").forEach(function(f){f.addEventListener("submit",function(e){e.preventDefault();var task=f.dataset.task,btn=f.querySelector("button"),fd=new FormData(f);'
            . 'var b=new URLSearchParams();fd.forEach(function(v,k){b.append(k,v);});b.append("action","sgap_studio");b.append("task",task);b.append("nonce",C.nonce);'
            . 'btn.disabled=true;var i=0,s=steps[task],c=card("<h2><span class=\\"dashicons dashicons-update sgap-spin\\"></span>Working</h2><div class=\\"sgap-progress\\"><progress></progress><p>"+s[0]+"</p></div>");'
            . 'var timer=setInterval(function(){i=Math.min(i+1,s.length-1);c.querySelector(".sgap-progress p").textContent=s[i];},task==="write"?20000:12000);'
            . 'fetch(C.ajax,{method:"POST",credentials:"same-origin",body:b}).then(function(r){return r.json();}).then(function(j){clearInterval(timer);btn.disabled=false;'
            . 'if(!j||!j.success){c.innerHTML="<h2>Something went wrong</h2><p>"+esc((j&&j.data)||"No answer")+"</p>";c.classList.add("sgap-bad");return;}var d=j.data;'
            . 'if(task==="write"){c.innerHTML="<h2><span class=\\"dashicons dashicons-yes-alt\\"></span>Draft ready: "+esc(d.title)+"</h2>"'
            . '+(d.image&&d.image.url?"<img src=\\""+esc(d.image.url)+"\\" alt=\\"\\" class=\\"sgap-studio-img\\">":"")'
            . '+"<p>"+["Focus keyword: <b>"+esc(d.focus_keyword)+"</b>","~"+d.words+" words",d.internal_links+" internal links","SEO meta: "+esc((d.seo_meta_saved_for||[]).join(", "))].join(" · ")+"</p>"'
            . '+"<p class=\\"description\\"><b>"+esc(d.meta_title)+"</b><br>"+esc(d.meta_description)+"</p>"'
            . '+(d.image&&d.image.error?"<p class=\\"description\\">Image: "+esc(d.image.error)+"</p>":"")'
            . '+"<p class=\\"sgap-actions\\"><a class=\\"button button-primary\\" href=\\""+esc(d.edit_url)+"\\">Edit draft</a><a class=\\"button\\" target=\\"_blank\\" href=\\""+esc(d.preview_url)+"\\">Preview</a></p>";}'
            . 'else if(task==="image"){c.innerHTML="<h2><span class=\\"dashicons dashicons-yes-alt\\"></span>Image created</h2><img src=\\""+esc(d.url)+"\\" alt=\\"\\" class=\\"sgap-studio-img\\"><p class=\\"description\\">"+esc(d.model)+(d.featured_image_of?" · set as featured image":"")+" · <a href=\\""+esc(d.url)+"\\" target=\\"_blank\\">open</a></p>";}'
            . 'else{c.innerHTML="<h2><span class=\\"dashicons dashicons-yes-alt\\"></span>SEO updated: "+esc(d.title)+"</h2><p>Focus keyword: <b>"+esc(d.focus_keyword)+"</b></p><p class=\\"description\\"><b>"+esc(d.meta_title)+"</b><br>"+esc(d.meta_description)+"</p>"'
            . '+(d.suggestions&&d.suggestions.length?"<p><b>Ideas for the content:</b></p><ul style=\\"list-style:disc;margin-left:18px\\">"+d.suggestions.map(function(x){return "<li>"+esc(x)+"</li>";}).join("")+"</ul>":"");}'
            . '}).catch(function(err){clearInterval(timer);btn.disabled=false;c.innerHTML="<h2>Could not reach the site</h2><p>"+esc(err.message)+"</p>";});});});})();</script>';
        SGAP_Admin::close();
    }
}
