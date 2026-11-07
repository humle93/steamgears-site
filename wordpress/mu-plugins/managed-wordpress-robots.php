<?php
/**
 * Managed robots.txt additions (Simply / driftsregler).
 *
 * Rettet 7/11 2026 (S0.2). Den oprindelige fil tilføjede reglerne som en NY `User-agent: *`-gruppe
 * efter Rank Maths gruppe og efter Sitemap-linjen. To grupper med samme user-agent er teknisk lovligt
 * (RFC 9309 §2.2.1 slår dem sammen), men filen blev svær at læse, og reglerne stod efter Sitemap, hvor
 * de let overses. Nu indsættes de i Rank Maths gruppe, før Sitemap.
 *
 * Filen er identisk med den der ligger i wp-content/mu-plugins/ på sitet. Backup af den gamle version:
 * wp-content/novamira-sandbox/s0-2-backup-managed-wordpress-robots.php
 *
 * Rul tilbage ved at genskabe den gamle fil — se docs/library/robots-og-nedbrud-2026-11-07.md.
 */
add_filter('robots_txt', function ($output) {
    $rules = array(
        'Disallow: /*?*filter_cat=',
        'Disallow: /*?*min_price=',
        'Disallow: /*?*max_price=',
        'Disallow: /*?*orderby=',
        'Disallow: /*?*stock_status=',
        'Disallow: /*?*sale_status=',
        'Disallow: /*?*filter_brand=',
        'Disallow: /*?*et_columns-count=',
    );
    $block = implode("\n", $rules);

    if (strpos($output, $block) !== false) {
        return $output; // idempotent: reglerne står der allerede
    }

    $pos = strpos($output, "\n\nSitemap:");
    if ($pos === false) {
        return rtrim($output) . "\n" . $block . "\n";
    }

    return substr($output, 0, $pos) . "\n" . $block . substr($output, $pos);
});
