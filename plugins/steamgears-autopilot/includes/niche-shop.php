<?php
/**
 * Niche shop: one field, one run.
 *
 * The owner types a niche ("steampunk airship", "cottagecore mushroom") and the plugin turns it
 * into a complete shop skeleton: brand suggestions, five collections, a priced product plan across
 * the POD families, page drafts, a menu, the schema types the shop needs, and a readiness list of
 * the steps only the owner (or Printful) can take.
 *
 * Two rules shape the design:
 *
 *   * **Nothing published, nothing overwritten.** Products and pages are created as *drafts* with a
 *     `_sgap_niche` marker, so a run can always be rolled back and never touches a live product.
 *   * **A plan without a key.** `plan()` is pure PHP and deterministic — same niche, same plan —
 *     so the owner can see the whole shop before a single row is written, and the tests can run it
 *     without WordPress. Printful is used when it is connected (real costs); until then every cost
 *     is marked `estimate` and the readiness list says so.
 *
 * Loaded from steamgears-autopilot.php.
 */

if (!defined('ABSPATH')) exit;

class SGAP_Niche_Shop {
    /** Bumping this invalidates every stored run key, on purpose: the plan format changed. */
    const ENGINE = '1';
    const RUNS = 'sgap_niche_runs';
    const MAX_PRODUCTS = 40;
    const MAX_NICHE = 60;
    const MENU_SLUG = 'sgap-niche';

    /**
     * Archetypes: what a niche smells like, and therefore which collections, motifs and voice the
     * shop gets. `keywords` are matched as substrings against the niche, so "cottagecore mushroom"
     * lands on cottagecore without an exact phrase.
     */
    const ARCHETYPES = [
        'steampunk' => [
            'label' => 'Steampunk',
            'keywords' => ['steampunk', 'airship', 'clockwork', 'brass', 'gear', 'victorian', 'aeronaut', 'zeppelin', 'dirigible', 'cog', 'steam'],
            'collections' => ['Airship' => 'Flight, brass and altitude', 'Clockwork' => 'Gears, automata and mechanisms', 'Herbarium' => 'Botanical plates in ink', 'Atelier' => 'The workshop, worn', 'Gallery' => 'Prints and posters'],
            'motifs' => ['Airship Armada', 'Clockwork Octopus', 'Brass Luna Moth', 'Astrolabe Planisphere', 'Copper Pilot Goggles', 'Aeronaut Pocket Watch'],
            'brands' => ['Brass & Bearing', 'The Airship Atelier', 'Clockwork Curiosities'],
            'tagline' => 'Dark clockwork theatre for the curious',
            'tone' => ['precise', 'tactile', 'theatrical']
        ],
        'dark_academia' => [
            'label' => 'Dark academia',
            'keywords' => ['dark academia', 'academia', 'library', 'scholar', 'oxford', 'tweed', 'ink', 'manuscript', 'candle', 'vellum', 'archivist'],
            'collections' => ['Library' => 'Long tables and green lamps', 'Herbarium' => 'Pressed plates and latin names', 'Scriptorium' => 'Ink, wax and seal', 'Gallery' => 'Prints for the study', 'Atelier' => 'Tweed and tailoring'],
            'motifs' => ['Candlelit Library', 'Ink & Vellum', 'Brass Compass', 'Moth & Manuscript', 'Ivy Archway', 'Scholar\'s Lantern'],
            'brands' => ['The Vellum Room', 'Candle & Catalogue', 'Ivy & Ink'],
            'tagline' => 'Old rooms, long reading lists, quiet rebellion',
            'tone' => ['measured', 'literary', 'dry']
        ],
        'gothic' => [
            'label' => 'Gothic',
            'keywords' => ['gothic', 'vampire', 'occult', 'tarot', 'witch', 'raven', 'skull', 'macabre', 'noir', 'crypt', 'coffin'],
            'collections' => ['Nocturne' => 'After midnight', 'Reliquary' => 'Bones, wax and silver', 'Tarot' => 'The deck, drawn dark', 'Crypt' => 'Stone and velvet', 'Gallery' => 'Prints for the wall'],
            'motifs' => ['Raven Chronometer', 'Waxing Moon', 'Bone Reliquary', 'Black Candle', 'Hollow Saint', 'Velvet Coffin'],
            'brands' => ['Nocturne & Ash', 'The Reliquary', 'Black Candle Co.'],
            'tagline' => 'Elegant gloom, worn well',
            'tone' => ['hushed', 'ornate', 'unsentimental']
        ],
        'cottagecore' => [
            'label' => 'Cottagecore',
            'keywords' => ['cottagecore', 'mushroom', 'forest', 'foraging', 'meadow', 'garden', 'linen', 'rustic', 'honey', 'frog', 'clover'],
            'collections' => ['Meadow' => 'Grass, wind and light', 'Foraging' => 'Baskets and field notes', 'Kitchen Garden' => 'Jam, herbs and clay', 'Woodland' => 'Moss and small creatures', 'Pressed Flowers' => 'Flat, dried, framed'],
            'motifs' => ['Mushroom Forager', 'Honey & Clover', 'Pressed Fern', 'Meadow Hare', 'Jam Jar', 'Woodland Snail'],
            'brands' => ['Fern & Fen', 'The Foraging Table', 'Clover & Clay'],
            'tagline' => 'Slow days, grown things, soft edges',
            'tone' => ['soft', 'practical', 'warm']
        ],
        'nautical' => [
            'label' => 'Nautical',
            'keywords' => ['nautical', 'maritime', 'whale', 'sea', 'sailor', 'anchor', 'lighthouse', 'tide', 'ship', 'harbour', 'harbor'],
            'collections' => ['Deep Water' => 'Below the swell', 'Lighthouse' => 'Keepers and beams', 'Harbour' => 'Rope, tar and bells', 'Whale Road' => 'Long migrations', 'Gallery' => 'Charts and prints'],
            'motifs' => ['Whale Airship', 'Lighthouse Keeper', 'Salt & Rope', 'Kraken\'s Chart', 'Harbour Bell', 'Tidal Compass'],
            'brands' => ['Salt & Signal', 'The Lighthouse Press', 'Whale Road'],
            'tagline' => 'Salt in the seams',
            'tone' => ['plainspoken', 'weathered', 'steady']
        ],
        'botanical' => [
            'label' => 'Botanical',
            'keywords' => ['botanical', 'herbarium', 'plant', 'floral', 'leaf', 'fern', 'flower', 'moss', 'seed', 'glasshouse', 'specimen'],
            'collections' => ['Herbarium' => 'Latin names, pressed', 'Glasshouse' => 'Orchids and heat', 'Pressed Plates' => 'Specimen cards', 'Seed Archive' => 'Catalogues and packets', 'Gallery' => 'Prints for the wall'],
            'motifs' => ['Brass Luna Moth', 'Pressed Fern', 'Nightshade Plate', 'Moss Specimen', 'Seed Catalogue', 'Glasshouse Orchid'],
            'brands' => ['The Glasshouse', 'Herbarium & Co.', 'Pressed & Plated'],
            'tagline' => 'Specimens, plates and patience',
            'tone' => ['exact', 'scientific', 'quiet']
        ],
        'cyberpunk' => [
            'label' => 'Cyberpunk',
            'keywords' => ['cyberpunk', 'neon', 'synth', 'chrome', 'hacker', 'dystopia', 'techwear', 'grid', 'circuit', 'android', 'glitch'],
            'collections' => ['Neon Grid' => 'Rain and reflection', 'Chrome' => 'Metal, no warmth', 'Night Market' => 'Stalls and cables', 'Signal' => 'Static and interference', 'Gallery' => 'Prints and decals'],
            'motifs' => ['Neon Rain', 'Chrome Circuit', 'Signal Lost', 'Night Market', 'Static Saint', 'Data Bloom'],
            'brands' => ['Signal & Static', 'Neon Foundry', 'Chrome Bloom'],
            'tagline' => 'Hardware with an attitude',
            'tone' => ['clipped', 'loud', 'technical']
        ],
        'celestial' => [
            'label' => 'Celestial',
            'keywords' => ['celestial', 'astrology', 'star', 'moon', 'zodiac', 'constellation', 'astronomy', 'planet', 'cosmos', 'orbit', 'eclipse'],
            'collections' => ['Star Chart' => 'Maps of the sky', 'Moon Phase' => 'Wax and wane', 'Zodiac' => 'Twelve signs', 'Orbit' => 'Circles and ellipses', 'Gallery' => 'Prints for the wall'],
            'motifs' => ['Star Chart', 'Moon Phase', 'Zodiac Wheel', 'Orbit Line', 'Comet Trail', 'Solar Eclipse'],
            'brands' => ['Orbit & Ink', 'The Star Chart', 'Moon Phase Studio'],
            'tagline' => 'The sky, drawn to scale',
            'tone' => ['calm', 'precise', 'wondering']
        ],
        'retro' => [
            'label' => 'Retro',
            'keywords' => ['retro', 'vintage', 'seventies', 'eighties', '80s', '70s', 'arcade', 'synthwave', 'diner', 'cassette', 'disco', 'pinball'],
            'collections' => ['Arcade' => 'Quarters and high scores', 'Sunset Strip' => 'Chrome and palm trees', 'Cassette' => 'Side A, side B', 'Diner' => 'Booths and neon', 'Gallery' => 'Prints and posters'],
            'motifs' => ['Sunset Strip', 'Cassette Rewind', 'Pinball Wizard', 'Diner Neon', 'Arcade Cabinet', 'Roller Rink'],
            'brands' => ['Rewind & Roll', 'Sunset Arcade', 'Cassette Club'],
            'tagline' => 'Loud colours, short memories',
            'tone' => ['playful', 'punchy', 'nostalgic']
        ],
        'neutral' => [
            'label' => 'General',
            'keywords' => [],
            'collections' => ['Signature' => 'The core range', 'Limited' => 'Small runs', 'Everyday' => 'Worn daily', 'Gift' => 'Boxed and ready', 'Gallery' => 'Prints for the wall'],
            'motifs' => [],
            'brands' => [],
            'tagline' => 'Made to last, made to be noticed',
            'tone' => ['clear', 'warm', 'direct']
        ]
    ];

    /** POD families: what the shop sells, with a default cost per unit until Printful is connected. */
    const FAMILIES = [
        'tee' => ['label' => 'Tee', 'cost' => 12.50, 'blurb' => 'heavyweight cotton, unisex fit', 'sizes' => true],
        'hoodie' => ['label' => 'Hoodie', 'cost' => 27.00, 'blurb' => 'brushed fleece, kangaroo pocket', 'sizes' => true],
        'print' => ['label' => 'Art print', 'cost' => 8.00, 'blurb' => 'matte 200 gsm paper, ships rolled', 'sizes' => false],
        'tote' => ['label' => 'Tote', 'cost' => 9.00, 'blurb' => 'heavy canvas, long handles', 'sizes' => false],
        'mug' => ['label' => 'Mug', 'cost' => 8.50, 'blurb' => 'ceramic, dishwasher safe', 'sizes' => false],
        'phone_case' => ['label' => 'Phone case', 'cost' => 9.50, 'blurb' => 'hard shell, matte finish', 'sizes' => false]
    ];

    /** Pages every shop needs before the first ad, in the order they should be written. */
    const PAGE_PLAN = [
        'start-here' => ['Start here', 'Where to begin: the three collections worth seeing first, and what the shop is for.'],
        'about' => ['About', 'Who is behind the shop, what the designs are made of, and the tone of voice.'],
        'shipping-and-returns' => ['Shipping & returns', 'Zones, times, prices and the 14-day right of withdrawal.'],
        'size-guide' => ['Size guide', 'Chest, length and sleeve in centimetres and inches, plus how to measure a favourite garment.'],
        'contact' => ['Contact', 'How to reach the shop, and how fast to expect an answer.']
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu'], 21);
        add_action('rest_api_init', [__CLASS__, 'routes']);
        add_action('wp_ajax_sgap_niche_run', [__CLASS__, 'ajax_run']);
        add_action('wp_ajax_sgap_niche_rollback', [__CLASS__, 'ajax_rollback']);
    }

    public static function menu(): void {
        add_submenu_page('steamgears-autopilot', 'Niche shop', 'Niche shop', 'manage_options', self::MENU_SLUG, [__CLASS__, 'page'], 11);
    }

    /* ------------------------------------------------------------------ *
     * Plan: pure, deterministic, testable without WordPress
     * ------------------------------------------------------------------ */

    /** A niche as the rest of the module wants it: trimmed, collapsed, capped. */
    public static function clean(string $niche): string {
        $niche = preg_replace('/\s+/u', ' ', strip_tags($niche));
        $niche = trim((string) $niche);
        if (mb_strlen($niche) > self::MAX_NICHE) {
            $niche = mb_substr($niche, 0, self::MAX_NICHE);
        }
        return $niche;
    }

    /** Words worth using in titles: 3+ letters, no duplicates, lower case. */
    public static function tokens(string $niche): array {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($niche), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3 && !in_array($word, $out, true)) {
                $out[] = $word;
            }
        }
        return $out;
    }

    /** The archetype whose keywords cover most of the niche; `neutral` when nothing matches. */
    public static function archetype(string $niche): array {
        $hay = ' ' . mb_strtolower($niche) . ' ';
        $best = 'neutral';
        $best_hits = 0;
        $best_words = [];
        foreach (self::ARCHETYPES as $id => $row) {
            $hits = [];
            foreach ($row['keywords'] as $keyword) {
                if (mb_strpos($hay, $keyword) !== false) {
                    $hits[] = $keyword;
                }
            }
            // A longer keyword is a more specific match, so "dark academia" beats "academia".
            $score = 0;
            foreach ($hits as $hit) {
                $score += 1 + mb_strlen($hit) / 40;
            }
            if ($score > $best_hits) {
                $best = $id;
                $best_hits = $score;
                $best_words = $hits;
            }
        }
        $row = self::ARCHETYPES[$best];
        return [
            'id' => $best,
            'label' => $row['label'],
            'matched' => $best_words,
            'confidence' => $best_hits > 0 ? min(100, (int) round(45 + 15 * count($best_words))) : 0
        ];
    }

    /** URL-friendly slug without needing WordPress, so a plan can be built in a test. */
    public static function slug(string $text): string {
        $map = ['æ' => 'ae', 'ø' => 'oe', 'å' => 'aa', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ç' => 'c', '&' => 'and'];
        $text = strtr(mb_strtolower($text), $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim((string) $text, '-');
    }

    /** Title case that keeps small words down, so "airship of the north" reads like a product. */
    public static function title(string $text): string {
        $small = ['of', 'the', 'and', 'a', 'an', 'in', 'on', 'for', 'to', 'with'];
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $out = [];
        foreach ($words as $i => $word) {
            $lower = mb_strtolower($word);
            $out[] = ($i > 0 && in_array($lower, $small, true)) ? $lower : mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        }
        return implode(' ', $out);
    }

    /** The lowest price that both clears the margin floor and stays a shop price (…,95). */
    public static function price(float $cost, int $margin): float {
        $floor = class_exists('SGAP_MCP_Woo') ? SGAP_MCP_Woo::floor_price($cost, $margin) : round($cost * (1 + $margin / 100), 2);
        $retail = floor($cost * 2.6) + 0.95;
        if ($retail < $floor) {
            $retail = $floor;
        }
        return round($retail, 2);
    }

    public static function margin_floor(): int {
        if (class_exists('SGAP_MCP_Woo')) {
            return SGAP_MCP_Woo::margin();
        }
        $saved = class_exists('SGAP_Settings') ? (int) SGAP_Settings::get('woo_min_margin', 45) : 45;
        return min(500, max(0, $saved));
    }

    /**
     * The whole shop as data: brand, collections, products, pages, menu, schema and readiness.
     *
     * @return array|WP_Error
     */
    public static function plan(string $niche, array $args = []) {
        $niche = self::clean($niche);
        if (mb_strlen($niche) < 2) {
            return new WP_Error('sgap_niche_short', 'Write a niche of at least two characters, for example "steampunk airship".');
        }
        $count = (int) ($args['count'] ?? 10);
        $count = max(1, min(self::MAX_PRODUCTS, $count));
        $margin = isset($args['margin']) ? max(0, min(500, (int) $args['margin'])) : self::margin_floor();
        $arch = self::archetype($niche);
        $row = self::ARCHETYPES[$arch['id']];
        $tokens = self::tokens($niche);
        $brand = self::brand($niche, $row, $tokens);
        $collections = self::collections($row);
        $products = self::products($niche, $row, $tokens, $collections, $count, $margin);
        $pages = self::pages($niche, $brand, $row, $collections);
        $readiness = self::readiness();
        $owner_fields = 0;
        foreach ($pages as $page) {
            $owner_fields += preg_match_all('/\[EJER[^\]]*\]/u', $page['body']);
        }
        foreach ($products as $product) {
            $owner_fields += preg_match_all('/\[EJER[^\]]*\]/u', $product['description']);
        }
        return [
            'niche' => $niche,
            'engine' => self::ENGINE,
            'plan_key' => substr(sha1(self::ENGINE . '|' . mb_strtolower($niche) . '|' . $count . '|' . $margin), 0, 12),
            'archetype' => $arch,
            'brand' => $brand,
            'collections' => $collections,
            'products' => $products,
            'pages' => $pages,
            'menu' => self::menu_items($collections),
            'schema' => ['Organization', 'WebSite', 'BreadcrumbList', 'ItemList', 'Product', 'FAQPage'],
            'readiness' => $readiness,
            'counts' => [
                'collections' => count($collections),
                'products' => count($products),
                'pages' => count($pages),
                'owner_fields' => (int) $owner_fields,
                'owner_steps' => count($readiness),
                'estimated_hours' => round(count($products) * 0.15 + count($pages) * 0.25 + 0.5, 2)
            ],
            'currency' => class_exists('SGAP_MCP_Woo') ? 'store currency' : '',
            'margin_floor' => $margin
        ];
    }

    /** Escape for HTML without assuming WordPress is loaded (the planner is testable on its own). */
    private static function h(string $text): string {
        return function_exists('esc_html') ? esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /** Brand suggestions: never applied to the site, only shown, because renaming a live shop is the owner's call. */
    private static function brand(string $niche, array $row, array $tokens): array {
        $names = (array) $row['brands'];
        $title = self::title($niche);
        $names[] = $title . ' Atelier';
        $names[] = 'The ' . $title . ' Company';
        $names[] = $title . ' & Co.';
        $names = array_values(array_unique(array_filter($names)));
        return [
            'names' => array_slice($names, 0, 5),
            'suggested' => $names[0] ?? $title,
            'tagline' => $row['tagline'],
            'tone' => $row['tone'],
            'keywords' => array_slice(array_values(array_unique(array_merge($tokens, [mb_strtolower($row['label'])]))), 0, 6),
            'confirmed' => false
        ];
    }

    private static function collections(array $row): array {
        $out = [];
        foreach ($row['collections'] as $name => $description) {
            $out[] = ['name' => $name, 'slug' => self::slug($name), 'description' => $description];
        }
        return $out;
    }

    private static function menu_items(array $collections): array {
        $items = ['Start here', 'Shop'];
        foreach (array_slice($collections, 0, 3) as $collection) {
            $items[] = $collection['name'];
        }
        return array_merge($items, ['Guides', 'About', 'Contact']);
    }

    /** The catalogue: families × motifs, round-robin over the collections, priced against the margin. */
    private static function products(string $niche, array $row, array $tokens, array $collections, int $count, int $margin): array {
        $motifs = (array) $row['motifs'];
        foreach ($tokens as $token) {
            $motifs[] = self::title($token);
        }
        $motifs = array_values(array_unique($motifs));
        if ($motifs === []) {
            $motifs = [self::title($niche)];
        }
        $families = array_keys(self::FAMILIES);
        $products = [];
        $used = [];
        $i = 0;
        while (count($products) < $count) {
            $motif = $motifs[$i % count($motifs)];
            $family = $families[$i % count($families)];
            $collection = $collections[$i % count($collections)];
            $name = $motif . ' ' . self::FAMILIES[$family]['label'];
            $slug = self::slug($name);
            // Two motifs can collapse to the same slug; keep going instead of writing a duplicate.
            if (isset($used[$slug])) {
                $i++;
                if ($i > $count * 20) {
                    break;
                }
                continue;
            }
            $cost = (float) self::FAMILIES[$family]['cost'];
            $price = self::price($cost, $margin);
            $products[] = [
                'title' => $name,
                'slug' => $slug,
                'family' => $family,
                'collection' => $collection['slug'],
                'collection_name' => $collection['name'],
                'sku' => 'SG-' . strtoupper(substr(sha1($slug), 0, 6)),
                'cost' => $cost,
                'cost_source' => 'estimate',
                'price' => $price,
                'margin_percent' => class_exists('SGAP_MCP_Woo') ? SGAP_MCP_Woo::margin_percent($price, $cost) : round((($price - $cost) / $price) * 100, 1),
                'short_description' => $motif . ' on a ' . mb_strtolower(self::FAMILIES[$family]['label']) . ' — ' . $row['tagline'] . '.',
                'description' => self::product_body($niche, $motif, $family, $collection, $row),
                'tags' => array_values(array_unique(array_merge(array_slice($tokens, 0, 3), [mb_strtolower($row['label']), mb_strtolower($collection['name'])])))
            ];
            $used[$slug] = true;
            $i++;
        }
        return $products;
    }

    private static function product_body(string $niche, string $motif, string $family, array $collection, array $row): string {
        $family_row = self::FAMILIES[$family];
        $html = '<p><strong>' . $motif . '</strong> — ' . $family_row['blurb'] . '.</p>';
        $html .= '<p>' . self::title($niche) . ', drawn for the ' . $collection['name'] . ' collection: ' . $collection['description'] . '.</p>';
        $html .= '<ul>';
        $html .= '<li>Print: high-DPI, printed to order — no warehouse, no dead stock.</li>';
        if (!empty($family_row['sizes'])) {
            $html .= '<li>Fit: unisex, true to size; size guide with centimetres and inches is linked below.</li>';
        }
        $html .= '<li>Production: print-on-demand, fulfilled by Printful.</li>';
        $html .= '<li>Shipping: [EJER: zones and delivery times] · [EJER: shipping price].</li>';
        $html .= '<li>Returns: 14-day right of withdrawal, [EJER: return address].</li>';
        $html .= '</ul>';
        return $html;
    }

    private static function pages(string $niche, array $brand, array $row, array $collections): array {
        $title = self::title($niche);
        $first = $collections[0]['name'] ?? 'Signature';
        $bodies = [
            'start-here' => '<p>' . self::h($brand['suggested']) . ' makes ' . mb_strtolower($title) . ' print-on-demand: ' . $brand['tagline'] . '.</p>'
                . '<h2>Start with</h2><ol><li><strong>' . esc_html($first) . '</strong> — ' . self::h($collections[0]['description'] ?? '') . '.</li>'
                . '<li><strong>Size guide</strong> — measure a garment you already like.</li>'
                . '<li><strong>Shipping &amp; returns</strong> — what arrives when.</li></ol>'
                . '<p>[EJER: what makes the shop different in one sentence the owner would actually say.]</p>',
            'about' => '<p>' . self::h($brand['suggested']) . ' is a small print-on-demand shop for ' . mb_strtolower($title) . '.</p>'
                . '<p>Every design is printed when it is ordered, so nothing is produced to sit in a warehouse. Tone of voice: ' . self::h(implode(', ', (array) $row['tone'])) . '.</p>'
                . '<p>[EJER: registered company name, CVR, address and phone — required by Forbrugerombudsmanden and GPSR.]</p>',
            'shipping-and-returns' => '<p>Orders are printed to order and shipped from the Printful facility closest to you.</p>'
                . '<ul><li>Processing: [EJER: days].</li><li>Delivery: [EJER: zones and days].</li><li>Shipping cost: [EJER: price per zone, free above X].</li></ul>'
                . '<h2>Right of withdrawal</h2><p>You have 14 days from delivery. [EJER: return address and who pays return shipping.]</p>'
                . '<p>Use the withdrawal form on <a href="/right-of-withdrawal/">Right of withdrawal</a>.</p>',
            'size-guide' => '<p>All garments are unisex and printed to order. Measure a garment you already own, flat, and compare.</p>'
                . '<table><thead><tr><th>Size</th><th>Chest (cm)</th><th>Length (cm)</th><th>Chest (in)</th><th>Length (in)</th></tr></thead>'
                . '<tbody><tr><td>S</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td></tr>'
                . '<tr><td>M</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td></tr>'
                . '<tr><td>L</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td></tr>'
                . '<tr><td>XL</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td><td>[EJER]</td></tr></tbody></table>'
                . '<p>[EJER: table copied from the Printful size chart for the exact blank used.]</p>',
            'contact' => '<p>Questions about an order, a size or a return?</p>'
                . '<p>E-mail: [EJER: shop e-mail] · Phone: [EJER: phone] · Address: [EJER: address].</p>'
                . '<p>We answer within [EJER: hours].</p>'
        ];
        $out = [];
        foreach (self::PAGE_PLAN as $slug => [$title_text, $purpose]) {
            $out[] = [
                'slug' => $slug,
                'title' => $title_text,
                'purpose' => $purpose,
                'body' => $bodies[$slug] ?? '<p>' . self::h($purpose) . '</p>',
                'status' => 'draft'
            ];
        }
        return $out;
    }

    /** What a run cannot do, in the order it blocks a launch. Everything here is the owner's. */
    private static function readiness(): array {
        return [
            ['id' => 'printful', 'owner' => true, 'step' => 'Connect Printful and map the variants', 'why' => 'Real costs and 5–8 mockups per product. Until then every cost in the plan is an estimate and every product has one image at most.'],
            ['id' => 'publish', 'owner' => true, 'step' => 'Approve the drafts and publish', 'why' => 'The agent creates drafts only; a human decides what goes live.'],
            ['id' => 'currency', 'owner' => true, 'step' => 'Currency and price list', 'why' => 'A currency code alone turns $29.95 into 29,95 kr; prices have to be recalculated.'],
            ['id' => 'payments', 'owner' => true, 'step' => 'Payment methods', 'why' => 'MobilePay, PayPal or Klarna need agreements in the owner\'s name; Stripe is the only one wired today.'],
            ['id' => 'legal', 'owner' => true, 'step' => 'CVR, registered name, address and phone', 'why' => 'Imprint, GPSR block and the withdrawal page cannot be finished without them, and they may not be guessed.'],
            ['id' => 'consent', 'owner' => true, 'step' => 'Consent model for the EU', 'why' => 'An opt-out banner in front of EU customers is not a valid consent.'],
            ['id' => 'shipping', 'owner' => true, 'step' => 'Shipping zones, classes and free-shipping limit', 'why' => 'Checkout cannot price a delivery until the zones exist.'],
            ['id' => 'brand', 'owner' => true, 'step' => 'Confirm shop name and tagline', 'why' => 'The plan suggests names; renaming a live shop is the owner\'s decision, so nothing is renamed automatically.']
        ];
    }

    /* ------------------------------------------------------------------ *
     * Run: preview or write the plan as drafts, always reversible
     * ------------------------------------------------------------------ */

    /** @return array|WP_Error */
    public static function run(array $args) {
        $niche = self::clean((string) ($args['niche'] ?? ''));
        $plan = self::plan($niche, $args);
        if (is_wp_error($plan)) {
            return $plan;
        }
        $write = !empty($args['apply']);
        $result = self::apply($plan, $write, !empty($args['force']));
        if (is_wp_error($result)) {
            return $result;
        }
        return ['plan' => self::summary($plan), 'result' => $result];
    }

    /** A plan without its long bodies: what the UI and the REST answer can show cheaply. */
    public static function summary(array $plan): array {
        return [
            'niche' => $plan['niche'],
            'plan_key' => $plan['plan_key'],
            'archetype' => $plan['archetype'],
            'brand' => $plan['brand'],
            'collections' => array_map(fn($c) => $c['name'] . ' (' . $c['slug'] . ')', $plan['collections']),
            'products' => array_map(fn($p) => ['title' => $p['title'], 'slug' => $p['slug'], 'sku' => $p['sku'], 'price' => $p['price'], 'cost' => $p['cost'], 'margin_percent' => $p['margin_percent'], 'collection' => $p['collection_name']], $plan['products']),
            'pages' => array_map(fn($p) => $p['title'] . ' (/' . $p['slug'] . '/)', $plan['pages']),
            'menu' => $plan['menu'],
            'schema' => $plan['schema'],
            'readiness' => $plan['readiness'],
            'counts' => $plan['counts'],
            'margin_floor' => $plan['margin_floor']
        ];
    }

    /**
     * Create the shop as drafts — or, with $write false, only say what would be created.
     *
     * @return array|WP_Error
     */
    public static function apply(array $plan, bool $write, bool $force = false) {
        $key = (string) $plan['plan_key'];
        $runs = self::runs();
        if (isset($runs[$key]) && !$force) {
            return ['already_applied' => true, 'plan_key' => $key, 'created' => $runs[$key]['created'], 'created_at' => $runs[$key]['created_at']];
        }
        $wanted = [
            'collections' => count($plan['collections']),
            'products' => count($plan['products']),
            'pages' => count($plan['pages'])
        ];
        if (!$write) {
            return ['dry_run' => true, 'plan_key' => $key, 'would_create' => $wanted];
        }
        if (!post_type_exists('product')) {
            return new WP_Error('sgap_niche_no_woo', 'WooCommerce is not active, so there is no product post type to create drafts in. Activate WooCommerce first, or use the plan without applying it.');
        }
        $created = ['collections' => [], 'products' => [], 'pages' => []];
        $skipped = [];
        $errors = [];

        foreach ($plan['collections'] as $collection) {
            if ($collection['slug'] === '') {
                continue;
            }
            $exists = function_exists('term_exists') ? term_exists($collection['slug'], 'product_cat') : null;
            if ($exists) {
                $skipped[] = 'collection:' . $collection['slug'] . ' (exists)';
                continue;
            }
            $term = wp_insert_term($collection['name'], 'product_cat', ['slug' => $collection['slug'], 'description' => $collection['description']]);
            if (is_wp_error($term)) {
                $errors[] = 'collection ' . $collection['slug'] . ': ' . $term->get_error_message();
                continue;
            }
            $created['collections'][] = ['id' => (int) ($term['term_id'] ?? 0), 'slug' => $collection['slug'], 'name' => $collection['name']];
        }

        foreach ($plan['products'] as $product) {
            $existing = self::find('product', $product['slug']);
            if ($existing) {
                $skipped[] = 'product:' . $product['slug'] . ' (exists)';
                continue;
            }
            $id = wp_insert_post([
                'post_type' => 'product',
                'post_status' => 'draft',
                'post_title' => $product['title'],
                'post_name' => $product['slug'],
                'post_content' => $product['description'],
                'post_excerpt' => $product['short_description'],
                'post_author' => get_current_user_id()
            ], true);
            if (is_wp_error($id)) {
                $errors[] = 'product ' . $product['slug'] . ': ' . $id->get_error_message();
                continue;
            }
            if (!$id) {
                $errors[] = 'product ' . $product['slug'] . ': WordPress returned no id';
                continue;
            }
            self::write_meta((int) $id, $plan, $product);
            if (function_exists('wp_set_object_terms') && $product['collection'] !== '') {
                wp_set_object_terms((int) $id, [$product['collection']], 'product_cat');
            }
            $created['products'][] = ['id' => (int) $id, 'slug' => $product['slug'], 'title' => $product['title'], 'price' => $product['price']];
        }

        foreach ($plan['pages'] as $page) {
            if (self::find('page', $page['slug'])) {
                $skipped[] = 'page:' . $page['slug'] . ' (exists)';
                continue;
            }
            $id = wp_insert_post([
                'post_type' => 'page',
                'post_status' => 'draft',
                'post_title' => $page['title'],
                'post_name' => $page['slug'],
                'post_content' => $page['body'],
                'post_author' => get_current_user_id()
            ], true);
            if (is_wp_error($id) || !$id) {
                $errors[] = 'page ' . $page['slug'] . ': ' . (is_wp_error($id) ? $id->get_error_message() : 'WordPress returned no id');
                continue;
            }
            update_post_meta((int) $id, '_sgap_niche', $key);
            update_post_meta((int) $id, '_sgap_niche_purpose', (string) $page['purpose']);
            $created['pages'][] = ['id' => (int) $id, 'slug' => $page['slug'], 'title' => $page['title']];
        }

        $runs[$key] = [
            'plan_key' => $key,
            'niche' => $plan['niche'],
            'archetype' => $plan['archetype']['id'],
            'created_at' => gmdate('c'),
            'user' => get_current_user_id(),
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors
        ];
        self::store($runs);
        return [
            'dry_run' => false,
            'plan_key' => $key,
            'created' => $created,
            'counts' => ['collections' => count($created['collections']), 'products' => count($created['products']), 'pages' => count($created['pages'])],
            'skipped' => $skipped,
            'errors' => $errors,
            'rollback' => 'POST /wp-json/sgap/v1/niche/rollback with {"plan_key":"' . $key . '"}'
        ];
    }

    /** Product meta WooCommerce reads, plus the run marker rollback depends on. */
    private static function write_meta(int $id, array $plan, array $product): void {
        $meta = [
            '_sku' => $product['sku'],
            '_regular_price' => (string) $product['price'],
            '_price' => (string) $product['price'],
            '_stock_status' => 'instock',
            '_manage_stock' => 'no',
            '_virtual' => 'no',
            '_sgap_niche' => (string) $plan['plan_key'],
            '_sgap_cost' => (string) $product['cost'],
            '_sgap_cost_source' => 'estimate'
        ];
        foreach ($meta as $field => $value) {
            update_post_meta($id, $field, $value);
        }
        update_post_meta($id, '_product_attributes', []);
    }

    /** A product or page by slug, without assuming WordPress' helpers exist in a test. */
    private static function find(string $type, string $slug): int {
        if ($slug === '') {
            return 0;
        }
        if (function_exists('get_page_by_path')) {
            $found = get_page_by_path($slug, OBJECT, $type);
            return $found ? (int) $found->ID : 0;
        }
        return 0;
    }

    /** Undo one run: delete the drafts and empty terms it created, and nothing else. */
    public static function rollback(string $plan_key, bool $write = true) {
        $runs = self::runs();
        $key = trim($plan_key);
        if ($key === '' || !isset($runs[$key])) {
            return new WP_Error('sgap_niche_no_run', 'There is no run with that key, so there is nothing to roll back.');
        }
        $run = $runs[$key];
        $report = ['plan_key' => $key, 'deleted' => [], 'kept' => []];
        // Singular labels: a report line names the thing, not the array it came from.
        $label = ['collections' => 'collection', 'products' => 'product', 'pages' => 'page'];
        foreach ($run['created'] as $type => $items) {
            foreach ((array) $items as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $kind = $label[$type] ?? $type;
                if ($type === 'collections') {
                    if (!$write) {
                        $report['deleted'][] = $kind . ':' . ($item['slug'] ?? $id);
                        continue;
                    }
                    $term = function_exists('get_term') ? get_term($id, 'product_cat') : null;
                    if (!$term || is_wp_error($term) || (int) $term->count > 0) {
                        // A collection that already has products is content now, not a leftover.
                        $report['kept'][] = $kind . ':' . ($item['slug'] ?? $id) . ' (in use)';
                        continue;
                    }
                    wp_delete_term($id, 'product_cat');
                    $report['deleted'][] = $kind . ':' . ($item['slug'] ?? $id);
                    continue;
                }
                $status = (string) get_post_status($id);
                $marker = (string) get_post_meta($id, '_sgap_niche', true);
                if (!$write) {
                    $report['deleted'][] = $kind . ':' . ($item['slug'] ?? $id);
                    continue;
                }
                if (!in_array($status, ['draft', 'auto-draft', 'pending'], true)) {
                    $report['kept'][] = $kind . ':' . ($item['slug'] ?? $id) . ' (' . $status . ')';
                    continue;
                }
                if ($marker !== $key) {
                    $report['kept'][] = $kind . ':' . ($item['slug'] ?? $id) . ' (not from this run)';
                    continue;
                }
                wp_delete_post($id, true);
                $report['deleted'][] = $kind . ':' . ($item['slug'] ?? $id);
            }
        }
        if ($write) {
            unset($runs[$key]);
            self::store($runs);
        }
        $report['dry_run'] = !$write;
        return $report;
    }

    public static function runs(): array {
        $stored = get_option(self::RUNS, []);
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        return is_array($stored) ? $stored : [];
    }

    private static function store(array $runs): void {
        // Keep the ten newest runs: enough to undo a mistake, small enough to stay an option.
        if (count($runs) > 10) {
            uasort($runs, fn($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
            $runs = array_slice($runs, 0, 10, true);
        }
        update_option(self::RUNS, $runs, false);
    }

    /* ------------------------------------------------------------------ *
     * Entry points: admin page, AJAX, REST
     * ------------------------------------------------------------------ */

    public static function routes(): void {
        register_rest_route('sgap/v1', '/niche', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_run'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
        register_rest_route('sgap/v1', '/niche/rollback', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_rollback'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
        register_rest_route('sgap/v1', '/niche/runs', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_runs'],
            'permission_callback' => fn() => current_user_can('manage_options')
        ]);
    }

    public static function rest_run($request) {
        $params = is_object($request) && method_exists($request, 'get_params') ? (array) $request->get_params() : (array) $request;
        $result = self::run([
            'niche' => (string) ($params['niche'] ?? ''),
            'count' => (int) ($params['count'] ?? 10),
            'margin' => isset($params['margin']) ? (int) $params['margin'] : self::margin_floor(),
            'apply' => !empty($params['apply']),
            'force' => !empty($params['force'])
        ]);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message(), 'code' => $result->get_error_code()], 400);
        }
        return new WP_REST_Response($result, 200);
    }

    public static function rest_rollback($request) {
        $params = is_object($request) && method_exists($request, 'get_params') ? (array) $request->get_params() : (array) $request;
        $result = self::rollback((string) ($params['plan_key'] ?? ''), !isset($params['dry']) || !$params['dry']);
        if (is_wp_error($result)) {
            return new WP_REST_Response(['error' => $result->get_error_message(), 'code' => $result->get_error_code()], 404);
        }
        return new WP_REST_Response($result, 200);
    }

    public static function rest_runs() {
        $out = [];
        foreach (self::runs() as $key => $run) {
            $out[] = [
                'plan_key' => $key,
                'niche' => $run['niche'] ?? '',
                'created_at' => $run['created_at'] ?? '',
                'counts' => [
                    'collections' => count($run['created']['collections'] ?? []),
                    'products' => count($run['created']['products'] ?? []),
                    'pages' => count($run['created']['pages'] ?? [])
                ]
            ];
        }
        return new WP_REST_Response(['count' => count($out), 'runs' => $out], 200);
    }

    private static function guard(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
        }
        check_ajax_referer('sgap_niche', 'nonce');
    }

    public static function ajax_run(): void {
        self::guard();
        $result = self::run([
            'niche' => sanitize_text_field(wp_unslash($_POST['niche'] ?? '')),
            'count' => absint($_POST['count'] ?? 10),
            'margin' => absint($_POST['margin'] ?? self::margin_floor()),
            'apply' => !empty($_POST['apply']),
            'force' => !empty($_POST['force'])
        ]);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result);
    }

    public static function ajax_rollback(): void {
        self::guard();
        $result = self::rollback(sanitize_text_field(wp_unslash($_POST['plan_key'] ?? '')), empty($_POST['dry']));
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result);
    }

    /* ---- the one field ---- */

    public static function page(): void {
        SGAP_Admin::open('Niche shop', 'Type a niche. The plan is built first — nothing is written until you press Create drafts, and drafts are always reversible.');
        $runs = self::runs();
        echo '<div class="sgap-card"><h2><span class="dashicons dashicons-store"></span>What is the shop about?</h2>'
            . '<p class="sgap-sub">One field. The plan turns a niche into collections, priced products, page drafts and a readiness list. Products are created as <strong>drafts</strong>; the owner publishes.</p>'
            . '<div class="sgap-field"><div class="sgap-field-label"><label for="sgap_niche">Niche</label><p>For example “steampunk airship”, “cottagecore mushroom” or “celestial zodiac”.</p></div>'
            . '<div class="sgap-field-input"><input type="text" id="sgap_niche" class="sgap-text" placeholder="steampunk airship" maxlength="' . (int) self::MAX_NICHE . '"></div></div>'
            . '<div class="sgap-inline"><label class="sgap-mini">Products <input type="number" id="sgap_niche_count" value="10" min="1" max="' . (int) self::MAX_PRODUCTS . '" class="sgap-num"></label>'
            . '<label class="sgap-mini">Margin floor % <input type="number" id="sgap_niche_margin" value="' . (int) self::margin_floor() . '" min="0" max="500" class="sgap-num"></label></div>'
            . '<div class="sgap-actions"><button class="button button-primary" id="sgap_niche_plan">Build the plan</button>'
            . '<button class="button" id="sgap_niche_apply" disabled>Create drafts</button>'
            . '<span class="sgap-hint" id="sgap_niche_hint">Nothing is written before you press Create drafts.</span></div>'
            . '<div id="sgap_niche_out"></div></div>';

        echo '<div class="sgap-card"><h2><span class="dashicons dashicons-backup"></span>Runs</h2>';
        if (!$runs) {
            echo '<p class="sgap-hint">No run yet. A run keeps the ids it created, so it can be undone in full.</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>When</th><th>Niche</th><th>Drafts</th><th>Key</th><th></th></tr></thead><tbody>';
            foreach ($runs as $key => $run) {
                $counts = [count($run['created']['collections'] ?? []), count($run['created']['products'] ?? []), count($run['created']['pages'] ?? [])];
                echo '<tr><td>' . esc_html((string) ($run['created_at'] ?? '')) . '</td><td>' . esc_html((string) ($run['niche'] ?? '')) . '</td>'
                    . '<td>' . esc_html($counts[0] . ' collections · ' . $counts[1] . ' products · ' . $counts[2] . ' pages') . '</td>'
                    . '<td><code>' . esc_html((string) $key) . '</code></td>'
                    . '<td><button class="button button-small sgap-niche-undo" data-key="' . esc_attr((string) $key) . '">Roll back</button></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        $cfg = ['ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('sgap_niche')];
        $js = <<<'JS'
(function () {
  var C = __CFG__, out = document.getElementById("sgap_niche_out");
  var planBtn = document.getElementById("sgap_niche_plan"), applyBtn = document.getElementById("sgap_niche_apply");
  var hint = document.getElementById("sgap_niche_hint");

  function post(action, data) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fd.append("action", action);
    fd.append("nonce", C.nonce);
    return fetch(C.ajax, { method: "POST", credentials: "same-origin", body: fd }).then(function (r) { return r.json(); });
  }

  function esc(s) {
    return String(s == null ? "" : s).replace(/[&<>"]/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c];
    });
  }

  function table(rows, head) {
    var h = '<table class="widefat striped"><thead><tr>';
    head.forEach(function (c) { h += "<th>" + esc(c) + "</th>"; });
    h += "</tr></thead><tbody>";
    rows.forEach(function (r) {
      h += "<tr>";
      r.forEach(function (c) { h += "<td>" + esc(c) + "</td>"; });
      h += "</tr>";
    });
    return h + "</tbody></table>";
  }

  function niche() { return document.getElementById("sgap_niche").value.trim(); }
  function count() { return document.getElementById("sgap_niche_count").value; }
  function margin() { return document.getElementById("sgap_niche_margin").value; }

  function render(payload) {
    var p = payload.plan || payload, r = payload.result || {}, c = p.counts || {};
    var html = "<h3>" + esc(p.brand.suggested) + "</h3>";
    html += '<p class="sgap-sub">' + esc(p.brand.tagline) + " · " + esc(p.archetype.label) + " (" + esc(p.archetype.confidence) + " % match) · key <code>" + esc(p.plan_key) + "</code></p>";
    html += "<p>" + esc(c.products) + " products · " + esc(c.collections) + " collections · " + esc(c.pages) + " pages · " + esc(c.owner_steps) + " owner steps · " + esc(c.owner_fields) + " fields marked [EJER] · ~" + esc(c.estimated_hours) + " h of setup left</p>";
    if (r.created) {
      html += '<div class="notice notice-success"><p>Created ' + esc(r.counts.products) + " product drafts, " + esc(r.counts.collections) + " collections and " + esc(r.counts.pages) + " page drafts. Roll back below if it is not right.</p></div>";
    }
    html += "<h4>Products</h4>" + table((p.products || []).map(function (x) {
      return [x.title, x.collection, x.price, x.cost, x.margin_percent + " %"];
    }), ["Product", "Collection", "Price", "Cost", "Margin"]);
    html += "<h4>Pages</h4><p>" + (p.pages || []).map(esc).join(" · ") + "</p>";
    html += "<h4>Menu</h4><p>" + (p.menu || []).map(esc).join(" · ") + "</p>";
    html += "<h4>What only the owner can do</h4>" + table((p.readiness || []).map(function (x) {
      return [x.step, x.why];
    }), ["Step", "Why"]);
    out.innerHTML = html;
    applyBtn.disabled = false;
  }

  planBtn.addEventListener("click", function () {
    if (niche().length < 2) { alert("Write a niche first."); return; }
    planBtn.disabled = true;
    hint.textContent = "Building the plan…";
    post("sgap_niche_run", { niche: niche(), count: count(), margin: margin() }).then(function (j) {
      planBtn.disabled = false;
      if (!j.success) { alert(j.data || "Could not build the plan"); hint.textContent = ""; return; }
      render(j.data);
      hint.textContent = "Plan only — nothing written. Press Create drafts to save it as drafts.";
    });
  });

  applyBtn.addEventListener("click", function () {
    applyBtn.disabled = true;
    hint.textContent = "Creating drafts…";
    post("sgap_niche_run", { niche: niche(), count: count(), margin: margin(), apply: 1 }).then(function (j) {
      if (!j.success) { alert(j.data || "Could not create the drafts"); applyBtn.disabled = false; hint.textContent = ""; return; }
      render(j.data);
      hint.textContent = "Drafts created. Review them under Products, then publish — or roll back.";
    });
  });

  Array.prototype.forEach.call(document.querySelectorAll(".sgap-niche-undo"), function (b) {
    b.addEventListener("click", function () {
      if (!confirm("Delete the drafts this run created? Published items are kept.")) return;
      b.disabled = true;
      post("sgap_niche_rollback", { plan_key: b.dataset.key }).then(function (j) {
        if (!j.success) { alert(j.data || "Could not roll back"); b.disabled = false; return; }
        location.reload();
      });
    });
  });
})();
JS;
        echo '<script>' . str_replace('__CFG__', wp_json_encode($cfg), $js) . '</script>';
        SGAP_Admin::close();
    }
}
