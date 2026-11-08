<?php
/**
 * Plugin Name: Steamgears shop schema
 * Description: Tilføjer ItemList-strukturdata på shop- og produktkategori-arkivet, så kategorisider får samme maskinlæsbare liste som produktsiderne har via Product-schema.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Målt 8/11 2026: alle 20 publicerede produktsider har `"@type":"Product"` med pris, men
 * `/shop/` havde **ingen** strukturdata (0 forekomster). En kategoriliste uden ItemList er
 * usynlig for de maskinlæsbare oversigter, selv om hvert enkelt produkt er beskrevet.
 *
 * Reglerne her:
 *  1. Kun på produktarkiver (shop og produktkategori), aldrig på produktsider — de har Product.
 *  2. Listen bygges af de produkter der faktisk vises på siden, i samme rækkefølge.
 *  3. Højst 100 elementer, så outputtet ikke vokser ukontrolleret på store arkiver.
 *  4. Der skrives intet, hvis der ikke er produkter — en tom ItemList er værre end ingen.
 */
final class SGAP_Shop_Schema
{
    public const MAX_ITEMS = 100;

    public static function boot(): void
    {
        add_action('wp_footer', [self::class, 'render'], 20);
    }

    public static function render(): void
    {
        if (is_admin() || is_feed() || is_robots()) {
            return;
        }

        if (!function_exists('is_shop') || !(is_shop() || is_product_category() || is_product_tag())) {
            return;
        }

        $items = self::items();
        if ($items === []) {
            return;
        }

        $list = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => wp_strip_all_tags(wp_get_document_title()),
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ];

        echo "\n" . '<script type="application/ld+json">'
            . wp_json_encode($list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . '</script>' . "\n";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function items(): array
    {
        global $wp_query;

        if (!($wp_query instanceof WP_Query) || empty($wp_query->posts)) {
            return [];
        }

        $items = [];
        $position = 0;

        foreach ($wp_query->posts as $post) {
            if ($position >= self::MAX_ITEMS) {
                break;
            }

            if (!($post instanceof WP_Post) || $post->post_type !== 'product') {
                continue;
            }

            if (!function_exists('wc_get_product')) {
                return [];
            }

            $product = wc_get_product($post->ID);
            if (!$product) {
                continue;
            }

            $position++;
            $entry = [
                '@type' => 'ListItem',
                'position' => $position,
                'url' => get_permalink($post),
                'name' => wp_strip_all_tags($product->get_name()),
            ];

            $price = $product->get_price();
            if ($price !== '' && is_numeric($price)) {
                $entry['item'] = [
                    '@type' => 'Product',
                    'name' => wp_strip_all_tags($product->get_name()),
                    'url' => get_permalink($post),
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => (string) $price,
                        'priceCurrency' => get_woocommerce_currency(),
                        'availability' => $product->is_in_stock()
                            ? 'https://schema.org/InStock'
                            : 'https://schema.org/OutOfStock',
                    ],
                ];
            }

            $items[] = $entry;
        }

        return $items;
    }
}

SGAP_Shop_Schema::boot();
