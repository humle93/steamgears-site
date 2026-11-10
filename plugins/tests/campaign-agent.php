<?php
/**
 * P6 — the campaign agent (includes/campaign-agent.php).
 *
 * The agent writes drafts and nothing else. These checks follow that line: the validation
 * refusals, the pending-only inserts, the channel notes kept on the draft, the skip when a
 * draft is already waiting, and the two review surfaces (dashboard card, AI Drafts page).
 * AIMLAPI is stubbed through http_queue: no network, no key, no cost.
 */

// ---- options and a published page to promote ----
$opts['sgap_channels'] = 'facebook,instagram';
$opts['sgap_ai_provider'] = 'aimlapi';
$opts['sgap_ai_api_key'] = 'test-key';
$opts['sgap_ai_model'] = 'anthropic/claude-opus-5.5';
$opts['sgap_quality_gate'] = 'off';
$opts['sgap_utm_campaign'] = 'steamgears_premium_evergreen';
$GLOBALS['post_by_id'][501] = new WP_Post([
    'ID' => 501,
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_title' => 'Brass goggles for cosplay',
    'post_excerpt' => 'Hand-polished brass goggles with real glass lenses, built for a costume that has to survive a whole weekend.',
    'post_content' => ''
]);
$GLOBALS['post_by_id'][502] = new WP_Post([
    'ID' => 502,
    'post_type' => 'post',
    'post_status' => 'draft',
    'post_title' => 'Not live yet'
]);
$GLOBALS['post_fields'][501]['post_name'] = 'brass-goggles';
$GLOBALS['thumb'][501] = 'https://steamgears.eu/wp-content/uploads/goggles.jpg';
$GLOBALS['posts_result'] = [$GLOBALS['post_by_id'][501]];

$goal = 'sell the brass goggles to cosplayers before Halloween';

// ---- the DDL carries the two new columns ----
$ddl = implode("\n", $GLOBALS['ddl'] ?? []);
check('campaign: the drafts table stores the campaign name', str_contains($ddl, 'campaign VARCHAR(60)'));
check('campaign: the drafts table stores the gate notes', str_contains($ddl, 'gate_report TEXT NULL'));

// ---- validate: what the agent refuses before it writes anything ----
$bad = SGAP_Campaign_Agent::validate(['post_id' => 0, 'goal' => $goal, 'channels' => ['facebook']]);
check('campaign: a missing page is refused', is_wp_error($bad) && $bad->get_error_code() === 'sgap_campaign_source');
$bad = SGAP_Campaign_Agent::validate(['post_id' => 502, 'goal' => $goal, 'channels' => ['facebook']]);
check('campaign: an unpublished page is refused', is_wp_error($bad) && str_contains($bad->get_error_message(), 'not published'));
$bad = SGAP_Campaign_Agent::validate(['post_id' => 501, 'goal' => 'short', 'channels' => ['facebook']]);
check('campaign: a goal shorter than 8 characters is refused', is_wp_error($bad) && $bad->get_error_code() === 'sgap_campaign_goal');
$bad = SGAP_Campaign_Agent::validate(['post_id' => 501, 'goal' => str_repeat('a', 161), 'channels' => ['facebook']]);
check('campaign: a goal over 160 characters is refused', is_wp_error($bad) && $bad->get_error_code() === 'sgap_campaign_goal');
$bad = SGAP_Campaign_Agent::validate(['post_id' => 501, 'goal' => $goal, 'channels' => []]);
check('campaign: no channel at all is refused', is_wp_error($bad) && $bad->get_error_code() === 'sgap_campaign_channels');
$bad = SGAP_Campaign_Agent::validate(['post_id' => 501, 'goal' => $goal, 'channels' => ['twitter']]);
check('campaign: a channel that is not configured is refused, not silently written', is_wp_error($bad) && str_contains($bad->get_error_message(), 'facebook'));
$ok = SGAP_Campaign_Agent::validate(['post_id' => 501, 'goal' => $goal, 'channels' => ['facebook', 'twitter', 'instagram']]);
check('campaign: unconfigured channels are dropped from a mixed list', is_array($ok) && $ok['channels'] === ['facebook', 'instagram']);
check('campaign: the campaign name defaults to the page slug', is_array($ok) && $ok['campaign'] === 'brass-goggles');
$named = SGAP_Campaign_Agent::validate(['post_id' => 501, 'goal' => $goal, 'channels' => ['facebook'], 'campaign' => 'halloween 2026!']);
check('campaign: a typed campaign name is sanitised', is_array($named) && $named['campaign'] === 'halloween2026');

// ---- run: pending drafts, the campaign name and the channel notes ----
if (!function_exists('sgap_campaign_response')) {
    /** One AIMLAPI answer with a post per channel, in the shape parse_posts expects. */
    function sgap_campaign_response(array $channels): array {
        $posts = [];
        foreach ($channels as $channel) {
            $posts[] = ['channel' => $channel, 'title' => ucfirst($channel) . ' goggles', 'text' => 'Brass goggles with real glass lenses, built for a costume that survives a whole weekend of walking.', 'hashtags' => ['#steampunk'], 'alt_text' => 'Brass goggles'];
        }
        return ['headers' => [], 'body' => wp_json_encode(['choices' => [['message' => ['content' => wp_json_encode(['posts' => $posts])], 'finish_reason' => 'stop']]]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
}
$opts['sgap_full_auto_enabled'] = 'yes'; // Full Auto must not change what the agent writes
$GLOBALS['http_queue'] = [sgap_campaign_response(['facebook', 'instagram'])];
$GLOBALS['http_calls'] = [];
$wpdb->next = null;
$wpdb->log = [];
$report = SGAP_Campaign_Agent::run(['post_id' => 501, 'goal' => $goal, 'channels' => ['facebook', 'instagram'], 'campaign' => 'halloween_2026']);
$inserted = implode("\n", array_filter($wpdb->log, fn($q) => str_starts_with($q, 'INSERT')));
check('campaign: one draft per channel is written', is_array($report) && count($report['drafts'] ?? []) === 2, $report);
check('campaign: every draft is saved as pending, Full Auto or not', substr_count($inserted, '"status":"pending"') === 2, $inserted);
check('campaign: nothing is put in the queue', !str_contains($inserted, 'sgap_queue'), $inserted);
check('campaign: the campaign name is stored on the draft', substr_count($inserted, '"campaign":"halloween_2026"') === 2, $inserted);
check('campaign: the channel notes travel with the draft', substr_count($inserted, 'gate_report') === 2, $inserted);
check('campaign: the source keeps its AI cooldown stamp', isset($GLOBALS['postmeta'][501]['_sgap_campaign_at']));
check('campaign: the report points at AI Drafts for review', str_contains($report['review_url'] ?? '', 'sgap-ai'));
check('campaign: the goal reaches the model, not just the database', str_contains(json_encode($GLOBALS['http_calls'][0][1] ?? []), 'cosplayers before Halloween'));

// ---- skip: one draft per channel and campaign, never two ----
$wpdb->next = [7];
$wpdb->log = [];
$GLOBALS['http_calls'] = [];
$again = SGAP_Campaign_Agent::run(['post_id' => 501, 'goal' => $goal, 'channels' => ['facebook'], 'campaign' => 'halloween_2026']);
check('campaign: a draft already waiting is skipped', is_array($again) && ($again['skipped'][0]['channel'] ?? '') === 'facebook' && $again['drafts'] === []);
check('campaign: the skip costs no AI call', $GLOBALS['http_calls'] === [], $GLOBALS['http_calls']);
check('campaign: a skipped run says so in plain words', str_contains(SGAP_Campaign_Agent::summary($again), 'Nothing written'));
$wpdb->next = null;

// ---- gate: the findings are lines the owner can read on the draft ----
$notes = SGAP_Campaign_Agent::gate('instagram', ['title' => 'Goggles'], 'Brass goggles for a whole weekend of walking.', 'https://steamgears.eu/brass-goggles/', '');
check('campaign: a channel that needs a photo says so', str_starts_with($notes, 'Instagram:') && str_contains($notes, 'photo'), $notes);
$clean = SGAP_Campaign_Agent::gate('facebook', ['title' => 'Goggles'], 'Brass goggles for a whole weekend of walking.', 'https://steamgears.eu/brass-goggles/', 'https://steamgears.eu/goggles.jpg');
check('campaign: a clean channel writes no notes', $clean === '', $clean);

// ---- prompt: the goal shapes the angle, and an ordinary run is unchanged ----
$plain = SGAP_AI::prompt(['title' => 'Goggles', 'type' => 'product', 'summary' => 'Brass'], ['facebook']);
check('campaign: an ordinary prompt has no campaign goal block', !str_contains($plain, 'Campaign goal'));
$aimed = SGAP_AI::prompt(['title' => 'Goggles', 'type' => 'product', 'summary' => 'Brass', 'campaign_goal' => $goal], ['facebook']);
check('campaign: the goal is in the prompt when the agent sets it', str_contains($aimed, 'Campaign goal') && str_contains($aimed, 'cosplayers'), $aimed);
check('campaign: the prompt says the goal is not a fact to state', str_contains($aimed, 'do not state it as a new fact'));

// ---- the ability: drafts only, so it is neither read-only nor idempotent ----
$ability = $GLOBALS['abilities']['steamgears-autopilot/campaign-drafts'] ?? null;
check('campaign: the ability is registered', is_array($ability));
check('campaign: the ability asks for the page, the goal and the channels', isset($ability['input_schema']['properties']['post_id'], $ability['input_schema']['properties']['goal'], $ability['input_schema']['properties']['channels'], $ability['input_schema']['properties']['campaign']));
check('campaign: the ability writes, so it is not read-only', ($ability['meta']['annotations']['readonly'] ?? true) === false);
check('campaign: the ability is not idempotent', ($ability['meta']['annotations']['idempotent'] ?? true) === false);
$GLOBALS['http_queue'] = [sgap_campaign_response(['facebook'])];
$wpdb->next = null;
$via = SGAP_Abilities::campaign_drafts(['post_id' => 501, 'goal' => $goal, 'channels' => ['facebook']]);
check('campaign: the ability runs the same agent', is_array($via) && count($via['drafts']) === 1, $via);
check('campaign: the ability takes being called with no input', is_wp_error(SGAP_Abilities::campaign_drafts()));

// ---- the two surfaces: dashboard card and AI Drafts ----
ob_start(); SGAP_Campaign_Agent::card(); $card = ob_get_clean();
check('campaign: the dashboard has the campaign form', str_contains($card, 'Campaign agent') && str_contains($card, 'value="sgap_campaign"'));
check('campaign: the form asks for goal and campaign name', str_contains($card, 'name="goal"') && str_contains($card, 'utm_campaign'));
check('campaign: the card says the drafts are reviewed, not published', str_contains($card, 'never queues or publishes'));
check('campaign: the card links to AI Drafts', str_contains($card, 'page=sgap-ai'));

$draft_row = (object) ['id' => 12, 'post_id' => 501, 'channel' => 'facebook', 'title' => 'Goggles', 'body' => 'Brass goggles.', 'hashtags' => '#steampunk', 'campaign' => 'halloween_2026', 'gate_report' => "Facebook: the first line is too long", 'created_at' => '2026-11-10 09:00:00', 'source' => 'Brass goggles for cosplay'];
$GLOBALS['rows'] = [$draft_row];
$wpdb->next = 'wp_sgap_ai_drafts';
ob_start(); do_action('page_sgap-ai'); $page = ob_get_clean();
check('campaign: AI Drafts shows which campaign a draft belongs to', str_contains($page, 'campaign: halloween_2026'), $page);
check('campaign: AI Drafts shows the notes from the gate', str_contains($page, 'the first line is too long'));
$GLOBALS['rows'] = [];
$wpdb->next = null;

// ---- from_draft: the campaign follows the draft into the queue ----
$wpdb->row = (object) ['post_id' => 501, 'channel' => 'facebook', 'angle' => 'campaign', 'content_type' => 'guide', 'format' => 'post', 'title' => 'Goggles', 'body' => 'Brass goggles with real glass lenses.', 'hashtags' => '#steampunk', 'campaign' => 'halloween_2026'];
$wpdb->log = [];
$queued = SGAP_Queue::from_draft(12);
$queue_insert = implode("\n", array_filter($wpdb->log, fn($q) => str_contains($q, 'INSERT') && str_contains($q, 'sgap_queue')));
check('campaign: approving a campaign draft keeps its name in the queue', is_array($queued) && str_contains($queue_insert, 'halloween_2026'), $queue_insert);
$wpdb->row = null;

// ---- the GitHub link the owner asked for ----
ob_start(); SGAP_Updater::panel(); $panel = ob_get_clean();
check('plugin: the dashboard links to the repository', str_contains($panel, 'github.com/humle93/steamgears-site'), $panel);
check('plugin: the dashboard link says the repository is private', str_contains($panel, 'private'));
