<?php
/**
 * P1.18 keys checks: every secret lives on exactly one screen, and that screen never echoes a
 * stored value back. Required from smoke.php, so $opts, $GLOBALS and check() are already here.
 */

// 1. The registry and the settings class agree, in both directions.
$covered = SGAP_Keys::covered();
$secrets = SGAP_Settings::SECRET_KEYS;
sort($covered); $sorted = $secrets; sort($sorted);
check('keys: every secret has exactly one card, and no card invents a key', $covered === $sorted && count($covered) === count(array_unique($covered)));
$unlabelled = [];
foreach ($secrets as $key) {
    if (trim((string) (SGAP_Setup::LABELS[$key] ?? '')) === '') $unlabelled[] = $key;
}
foreach (SGAP_Keys::PROVIDERS as $id => $p) {
    if (trim((string) $p['body']) === '' || trim((string) $p['icon']) === '') $unlabelled[] = 'card:' . $id;
}
check('keys: every card has a label and a purpose', $unlabelled === []);

// 2. The Keys tab is first and is the only tab that lists a secret.
$tabs = array_keys(SGAP_Setup::TABS);
check('keys: the Keys tab comes first', $tabs[0] === 'keys' && SGAP_Setup::TABS['keys'][0] === 'Keys');
check('keys: the Keys tab holds exactly the secrets, in settings order', SGAP_Setup::TABS['keys'][2] === $secrets);
$leaks = [];
foreach (SGAP_Setup::TABS as $id => $tab) {
    if ($id === 'keys') continue;
    foreach (array_intersect((array) $tab[2], $secrets) as $key) $leaks[] = $id . ':' . $key;
}
check('keys: no other tab lists a secret', $leaks === []);

// 3. The rendered page has one field per key, and a saved value is never printed.
$GLOBALS['opts']['sgap_publer_api_key'] = 'PUBLER-SECRET-VALUE';
$GLOBALS['opts']['sgap_mastodon_instance'] = 'https://mastodon.example';
ob_start();
do_action('page_sgap-settings');
$kh = ob_get_clean();
$bad = [];
foreach ($secrets as $key) {
    $fields = substr_count($kh, 'name="sgap_' . $key . '"');
    // A constant in wp-config.php replaces the field with a note that says where the key lives.
    $note = SGAP_Keys::status($key)['state'] === 'constant'
        ? substr_count($kh, 'Set in wp-config.php (<code>' . (string) SGAP_Settings::constant_name($key) . '</code>')
        : 0;
    if ($fields + $note !== 1) $bad[] = $key . '=' . $fields . '/' . $note;
}
check('keys: each key is either one field or one wp-config note', $bad === []);
$pos = strpos($kh, 'sgap-keycard--');
while ($pos !== false) { $snip = substr($kh, $pos - 200, 900); if (str_contains($snip, 'Telegram')) { fwrite(STDERR, 'SNIP ' . preg_replace('/\s+/', ' ', $snip) . "\n"); break; } $pos = strpos($kh, 'sgap-keycard--', $pos + 1); }
fwrite(STDERR, 'CONST ' . substr_count($kh, 'wp-config.php (<code>') . "\n");
$tally = SGAP_Keys::tally();
check('keys: the stored value is never echoed back', !str_contains($kh, 'PUBLER-SECRET-VALUE')
    && str_contains($kh, $tally['saved'] . ' of ' . $tally['total'] . ' saved'));
check('keys: a saved key shows its state, not its value', str_contains($kh, 'sgap-keycard sgap-keycard--saved'));
check('keys: the Mastodon card links to the saved instance', str_contains($kh, 'https://mastodon.example/settings/applications'));
check('keys: every card that needs a source names one', (function () use ($kh) {
    foreach (SGAP_Keys::PROVIDERS as $p) {
        if (empty($p['links']) && empty($p['instance_link']) && trim((string) ($p['hint'] ?? '')) === '') return false;
    }
    return substr_count($kh, 'sgap-key-foot') >= count(SGAP_Keys::PROVIDERS) - 1;
})());

// 4. A constant in wp-config.php wins, and the card says so instead of offering a field.
define('SGAP_SERPAPI_KEY', 'from-config');
check('keys: a wp-config constant is reported as the source', SGAP_Keys::status('serpapi_key')['state'] === 'constant'
    && str_contains(SGAP_Setup::field('serpapi_key'), 'wp-config.php'));

// 5. The wizard only asks for the AI key when it is missing.
$wiz_saved = $GLOBALS['opts']['sgap_ai_api_key'] ?? '';
$GLOBALS['opts']['sgap_ai_api_key'] = 'saved-ai-key';
ob_start();
do_action('page_sgap-setup');
$wiz = ob_get_clean();
check('keys: the wizard stops asking once the AI key is saved', !str_contains($wiz, 'name="settings[ai_api_key]"')
    && str_contains($wiz, 'page=sgap-settings#keys'));
$GLOBALS['opts']['sgap_ai_api_key'] = '';
ob_start();
do_action('page_sgap-setup');
$wiz2 = ob_get_clean();
check('keys: the wizard still asks while the AI key is missing', str_contains($wiz2, 'name="settings[ai_api_key]"'));
$GLOBALS['opts']['sgap_ai_api_key'] = $wiz_saved;
unset($GLOBALS['opts']['sgap_publer_api_key'], $GLOBALS['opts']['sgap_mastodon_instance']);

// 6. The screen is styled like a card grid, with the modern chrome around it.
$css = SGAP_UI::stylesheet();
foreach (['.sgap-keycard', '.sgap-keys{display:grid', '.sgap-tab.on{background:var(--brass)', 'position:sticky;bottom:12px', '.sgap-dirty', ':focus-visible'] as $needle) {
    check('keys: css carries ' . $needle, str_contains($css, $needle));
}
