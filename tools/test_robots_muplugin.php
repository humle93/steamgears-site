<?php
/**
 * Offline test af wordpress/mu-plugins/managed-wordpress-robots.php.
 *
 * Filen er den rettede version af det mu-plugin, der lagde sitet ned 7/11 2026: den skal indsætte de
 * otte query-param-regler i Rank Maths `User-agent: *`-gruppe, før Sitemap-linjen, og være idempotent —
 * ellers kan et genkald duplikere dem. Testen kræver hverken WordPress eller netværk:
 *
 *     ./.tools/php tools/test_robots_muplugin.php
 *
 * Se docs/library/robots-og-nedbrud-2026-11-07.md §4 (vej C).
 */

$filters = [];
function add_filter(string $hook, callable $cb): void
{
    global $filters;
    $filters[$hook][] = $cb;
}

require __DIR__ . '/../wordpress/mu-plugins/managed-wordpress-robots.php';

function apply_robots(string $output): string
{
    global $filters;
    foreach ($filters['robots_txt'] ?? [] as $cb) {
        $output = $cb($output);
    }

    return $output;
}

$pass = 0;
$fail = 0;
function check_robots(string $name, bool $ok, string $info = ''): void
{
    global $pass, $fail;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : ' — ' . $info) . "\n";
    $ok ? $pass++ : $fail++;
}

// 1) Sådan ser Rank Maths output ud: én gruppe, derefter en tom linje og Sitemap.
$rankMath = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /?s=\n\n"
    . "Sitemap: https://steamgears.eu/sitemap_index.xml\n";
$out = apply_robots($rankMath);

check_robots('én User-agent-gruppe', substr_count($out, 'User-agent:') === 1, $out);
check_robots('reglerne står før Sitemap-linjen', strpos($out, 'filter_cat') < strpos($out, 'Sitemap:'), $out);
check_robots('alle otte regler er med', substr_count($out, 'Disallow: /*?*') === 8, (string) substr_count($out, 'Disallow: /*?*'));
check_robots('Sitemap-linjen er urørt', str_contains($out, "\nSitemap: https://steamgears.eu/sitemap_index.xml"), $out);
check_robots('ingen blank-linje-skade', !str_starts_with($out, "\n") && !str_contains($out, "\n\n\n"), json_encode($out));

// 2) Et genkald må ikke duplikere noget (filtre kan køre flere gange pr. request).
$twice = apply_robots($out);
check_robots('idempotent ved anden kørsel', $twice === $out, 'andet gennemløb ændrede output');

// 3) Uden en Sitemap-linje må reglerne ikke forsvinde.
$plain = "User-agent: *\nDisallow: /wp-admin/\n";
$fallback = apply_robots($plain);
check_robots('uden Sitemap tilføjes reglerne stadig', str_contains($fallback, 'filter_cat'), $fallback);
check_robots('og stadig kun én gruppe', substr_count($fallback, 'User-agent:') === 1, $fallback);

echo "\n{$pass} PASS / {$fail} FAIL\n";
exit($fail ? 1 : 0);
