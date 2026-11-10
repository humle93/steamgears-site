<?php
/**
 * P7 — feed health (includes/rss.php).
 *
 * In RSS mode there is no token to test, so the feed itself is the reading: does it carry posts, are
 * they recent, and does each of them have what Publer needs (title, text, and a photo where the
 * channel demands one)? These checks drive every state by hand through the wpdb stub — no network.
 */

// ---- helpers ----------------------------------------------------------------
if (!function_exists('sgap_feed_item')) {
    /** One row as SGAP_RSS::items() returns it. */
    function sgap_feed_item(int $id, string $published, string $title = 'Brass goggles', string $body = 'Hand-polished brass goggles with real glass lenses.', string $image = ''): object {
        return (object) ['id' => $id, 'title' => $title, 'body' => $body, 'image_url' => $image, 'published_at' => $published];
    }
}

$opts['sgap_channels'] = 'facebook,instagram';
$opts['sgap_publish_mode'] = 'rss';
$GLOBALS['filters'] = [];
$GLOBALS['rows'] = [];

// ---- 1) API mode: there is no feed to judge ---------------------------------
$opts['sgap_publish_mode'] = 'api';
$health = SGAP_RSS::health();
$summary = SGAP_RSS::health_summary($health);
check('feed health: API mode reports the feed as off, not as broken', ($health['facebook']['state'] ?? '') === 'off' && ($health['instagram']['state'] ?? '') === 'off');
check('feed health: the summary says why there is nothing to check', str_contains($summary['note'], 'API mode'));
$opts['sgap_publish_mode'] = 'rss';

// ---- 2) a healthy feed ------------------------------------------------------
$GLOBALS['rows'] = [sgap_feed_item(11, '2026-10-29 12:00:00', 'Brass goggles', 'Hand-polished brass goggles with real glass lenses.', 'https://steamgears.eu/goggles.jpg'), sgap_feed_item(10, '2026-10-28 12:00:00', 'Goggles', 'Brass goggles for a costume.', 'https://steamgears.eu/goggles.jpg')];
$health = SGAP_RSS::health();
$summary = SGAP_RSS::health_summary($health);
check('feed health: only the configured channels are judged', array_keys($health) === ['facebook', 'instagram']);
check('feed health: a feed with a fresh, complete post is ok', ($health['facebook']['state'] ?? '') === 'ok' && $health['facebook']['problems'] === []);
check('feed health: the post count and the age are reported', $health['facebook']['posts'] === 2 && $health['facebook']['age_days'] === 1);
check('feed health: the row carries the feed address Publer is given', str_contains($health['facebook']['url'] ?? '', 'sgap_feed=facebook'));
check('feed health: a healthy summary names the count', str_contains($summary['note'], 'All 2 feed(s)') && $summary['ok'] === 2 && $summary['total'] === 2);

// ---- 3) an empty feed is the loud one --------------------------------------
$GLOBALS['rows'] = [];
$health = SGAP_RSS::health();
$summary = SGAP_RSS::health_summary($health);
check('feed health: an empty feed is empty, not ok', ($health['facebook']['state'] ?? '') === 'empty' && $health['facebook']['posts'] === 0);
check('feed health: the empty feed says Publer sees nothing', str_contains(implode(' ', $health['facebook']['problems']), 'empty'));
check('feed health: the summary names the channels that need attention', str_contains($summary['note'], '2 of 2 feed(s)') && str_contains($summary['note'], 'facebook'));
check('feed health: the empty state is counted per channel', $summary['empty'] === 2 && $summary['ok'] === 0);

// ---- 4) stale is about time, not content -----------------------------------
$GLOBALS['rows'] = [sgap_feed_item(12, '2026-10-01 12:00:00')];
$health = SGAP_RSS::health();
check('feed health: a feed older than the limit is stale', ($health['facebook']['state'] ?? '') === 'stale' && $health['facebook']['age_days'] > SGAP_RSS::STALE_DAYS);
check('feed health: the stale note carries the age and the limit', str_contains(implode(' ', $health['facebook']['problems']), 'days ago'));
$GLOBALS['filters']['sgap_feed_stale_days'] = fn($days) => 45;
check('feed health: the limit can be raised by a filter', SGAP_RSS::health()['facebook']['state'] === 'ok');
$GLOBALS['filters']['sgap_feed_stale_days'] = fn($days) => 2;
check('feed health: a lower limit turns the same feed stale', SGAP_RSS::health()['facebook']['state'] === 'stale');
unset($GLOBALS['filters']['sgap_feed_stale_days']);

// ---- 5) what Publer cannot post --------------------------------------------
$GLOBALS['rows'] = [sgap_feed_item(13, '2026-10-29 12:00:00', '', 'Text without a title.')];
$health = SGAP_RSS::health();
check('feed health: a post without a title is invalid', ($health['facebook']['state'] ?? '') === 'invalid' && str_contains(implode(' ', $health['facebook']['problems']), 'no title'));
$GLOBALS['rows'] = [sgap_feed_item(14, '2026-10-29 12:00:00', 'A title', '')];
check('feed health: a post without text is invalid', SGAP_RSS::health()['facebook']['state'] === 'invalid' && str_contains(implode(' ', SGAP_RSS::health()['facebook']['problems']), 'no text'));
$GLOBALS['rows'] = [sgap_feed_item(15, '2026-10-29 12:00:00')];
$health = SGAP_RSS::health();
check('feed health: a channel that needs a photo flags a photoless post', $health['instagram']['state'] === 'invalid' && str_contains(implode(' ', $health['instagram']['problems']), 'needs one'));
check('feed health: the same post is fine where no photo is required', $health['facebook']['state'] === 'ok');
$GLOBALS['rows'] = [sgap_feed_item(16, '2026-10-29 12:00:00', 'Brass goggles', 'Hand-polished brass goggles.', 'https://steamgears.eu/goggles.jpg')];
check('feed health: a photoless channel rule is satisfied by an image', SGAP_RSS::health()['instagram']['state'] === 'ok');

// ---- 6) the two surfaces carry the reading ---------------------------------
// The event table query answers with ARRAY_A, so the stub must not hand it feed rows.
$GLOBALS['rows'] = [];
$ability = SGAP_Abilities::channel_health();
check('feed health: the channel-health ability returns the feeds', isset($ability['feeds']['facebook'], $ability['feeds_summary']['note']));
check('feed health: the ability still returns the token half', isset($ability['channels'], $ability['summary'], $ability['analytics']));
$rest = SGAP_Channel_Health::rest_report();
check('feed health: the REST answer carries the same two fields', isset($rest->data['feeds']['instagram'], $rest->data['feeds_summary']['total']));
check('feed health: the feeds are read-only fields, no new ability', !isset($GLOBALS['abilities']['steamgears-autopilot/feed-health']));

// ---- 7) no channels at all --------------------------------------------------
$opts['sgap_channels'] = '';
$summary = SGAP_RSS::health_summary(SGAP_RSS::health());
check('feed health: no configured channel is said in plain words', str_contains($summary['note'], 'No channel is switched on') && $summary['total'] === 0);
$opts['sgap_channels'] = 'facebook,instagram';
$GLOBALS['rows'] = [];
