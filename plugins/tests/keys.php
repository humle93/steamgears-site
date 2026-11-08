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

// 6. Every card says whose account the key is and why it exists — and documentation() says the same.
$docs = SGAP_Keys::documentation();
$doc_bad = [];
foreach (SGAP_Keys::PROVIDERS as $id => $p) {
    $owner = trim((string) ($p['owner'] ?? ''));
    $why = trim((string) ($p['why'] ?? ''));
    if (strlen($owner) < 25 || !str_starts_with($owner, 'You')) $doc_bad[] = $id . ':owner';
    if (strlen($why) < 40) $doc_bad[] = $id . ':why';
}
check('keys: every card says whose account the key belongs to, and why', $doc_bad === []);
check('keys: documentation has one row per key, in card order', array_column($docs, 'key') === SGAP_Keys::covered());
$doc_sources = array_unique(array_column($docs, 'source'));
check('keys: documentation names where each key comes from', array_diff($doc_sources, ['database', 'wp-config.php', 'not set']) === []);
$GLOBALS['opts']['sgap_publer_api_key'] = 'PUBLER-SECRET-VALUE';
ob_start();
do_action('page_sgap-settings');
$kh_docs = ob_get_clean();
check('keys: the card spells out owner and reason', substr_count($kh_docs, '<dt>Belongs to</dt>') >= count(SGAP_Keys::PROVIDERS)
    && substr_count($kh_docs, '<dt>Why it is here</dt>') >= count(SGAP_Keys::PROVIDERS));
check('keys: the owner table lists every key exactly once', substr_count($kh_docs, 'sgap-key-owner-row') === count(SGAP_Settings::SECRET_KEYS));
check('keys: the owner table repeats the documented reason, not a paraphrase', str_contains($kh_docs, 'your Pinterest developer app') && str_contains($kh_docs, 'Reads impressions, saves and outbound clicks'));
check('keys: ownership documentation never prints a stored key', !str_contains($kh_docs, 'PUBLER-SECRET-VALUE'));

// 7. A key change is recorded without the key: length, hash, time and actor only.
delete_option('sgap_key_changes');
delete_option('sgap_publer_api_key');
delete_option(SGAP_KeyCheck::RESULT);
$GLOBALS['opts'][SGAP_KeyCheck::RESULT] = ['ai' => ['ok' => true, 'message' => 'stale result from the old key']];
SGAP_Settings::set('publer_api_key', 'ROTATE-ME-0001');
$log = SGAP_Keys::changes();
$first = $log[0] ?? [];
check('keys: saving a key is recorded once', count($log) === 1 && ($first['action'] ?? '') === 'set' && ($first['key'] ?? '') === 'publer_api_key');
check('keys: the record keeps length and a hash, never the key', ($first['chars'] ?? 0) === strlen('ROTATE-ME-0001')
    && ($first['fingerprint'] ?? '') === substr(hash('sha256', 'ROTATE-ME-0001'), 0, 8)
    && !str_contains(serialize($log), 'ROTATE-ME-0001'));
check('keys: the record says when and by whom', ($first['at'] ?? '') !== '' && ($first['by'] ?? '') !== '');
check('keys: a key change drops the test result made with the old key', !isset($GLOBALS['opts'][SGAP_KeyCheck::RESULT]));
SGAP_Settings::set('publer_api_key', 'ROTATE-ME-0001');
check('keys: saving the same value again is not a rotation', count(SGAP_Keys::changes()) === 1);
SGAP_Settings::set('publer_api_key', 'ROTATE-ME-0002');
$log = SGAP_Keys::changes();
$last = $log[1] ?? [];
check('keys: a new value is recorded as a change with a new fingerprint', count($log) === 2
    && ($last['action'] ?? '') === 'changed' && ($last['fingerprint'] ?? '') !== ($first['fingerprint'] ?? '')
    && ($last['chars'] ?? 0) === strlen('ROTATE-ME-0002') && !str_contains(serialize($log), 'ROTATE-ME-0002'));
SGAP_Settings::set('publer_api_key', '');
$log = SGAP_Keys::changes();
$cleared = $log[2] ?? [];
check('keys: removing a key is recorded as cleared, with no hash left behind', count($log) === 3
    && ($cleared['action'] ?? '') === 'cleared' && ($cleared['chars'] ?? -1) === 0 && ($cleared['fingerprint'] ?? 'x') === '');
$before_constant = count(SGAP_Keys::changes());
SGAP_Settings::set('serpapi_key', 'from-database');
check('keys: a write under a wp-config constant is not logged as a rotation', count(SGAP_Keys::changes()) === $before_constant);
unset($GLOBALS['opts']['sgap_serpapi_key']);

// 8. A change clears what was cached from the old value, so a rotation cannot look like a dead key.
SGAP_Settings::set('ai_api_key', 'AI-KEY-ROTATED');
check('keys: changing the AI key clears the cached model lists', in_array('sgap_ai_catalog_aimlapi', SGAP_Keys::flushed(), true)
    && in_array('sgap_ai_catalog_anthropic', SGAP_Keys::flushed(), true)
    && in_array('sgap_ai_catalog_openai_compatible', SGAP_Keys::flushed(), true));
SGAP_Settings::set('publer_api_key', 'PUBLER-ROTATED');
check('keys: changing the Publer key clears the cached account list', SGAP_Keys::flushed() === ['sgap_publer_accounts']);
ob_start();
do_action('page_sgap-settings');
$kh_hist = ob_get_clean();
check('keys: the card shows the rotation history without the key', str_contains($kh_hist, 'sgap-key-history')
    && str_contains($kh_hist, 'fingerprint') && !str_contains($kh_hist, 'AI-KEY-ROTATED') && !str_contains($kh_hist, 'PUBLER-ROTATED'));
check('keys: the history is per card, not one global list', substr_count($kh_hist, 'sgap-key-history') >= 2);
delete_option('sgap_key_changes');
delete_option('sgap_ai_api_key');
delete_option('sgap_publer_api_key');

// 9. The screen is styled like a card grid, with the modern chrome around it.
$css = SGAP_UI::stylesheet();
foreach (['.sgap-keycard', '.sgap-keys{display:grid', '.sgap-key-owner{', '.sgap-key-owners{', '.sgap-key-history{', '.sgap-tab.on{background:var(--brass)', 'position:sticky;bottom:12px', '.sgap-dirty', ':focus-visible'] as $needle) {
    check('keys: css carries ' . $needle, str_contains($css, $needle));
}
