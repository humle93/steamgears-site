<?php
/**
 * Smoke-checks for the in-plugin MCP renewal (7.44.0).
 *
 * Requiret af smoke.php efter HTTP-stubberne, så token-kaldene kan afspilles fra
 * http_queue: intet netværk, ingen rigtig credential. Kører derfor også i CI.
 */
if (!defined('ABSPATH')) exit;

$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [];
foreach (['sgap_mcp_client_id', 'sgap_mcp_refresh_token', 'sgap_mcp_access_token', 'sgap_mcp_access_expires', 'sgap_mcp_refresh_state'] as $k) {
    unset($GLOBALS['opts'][$k]);
}
check('mcp: nothing is configured until a client id and token exist', SGAP_MCP_Auth::configured() === false);
check('mcp: the status names the missing credential and where it goes', str_contains(SGAP_MCP_Auth::status()['problem'], 'Settings → MCP access'));
check('mcp: renewing without a credential is refused before any call', SGAP_MCP_Auth::refresh() instanceof WP_Error && $GLOBALS['http_calls'] === []);
SGAP_MCP_Auth::run();
check('mcp: the cron is silent while nothing is configured', $GLOBALS['http_calls'] === [] && SGAP_MCP_Auth::problem() === '');

$GLOBALS['opts']['sgap_mcp_client_id'] = 'client-abc';
$GLOBALS['opts']['sgap_mcp_refresh_token'] = 'refresh-1';
check('mcp: configured when both are saved', SGAP_MCP_Auth::configured() === true);

$GLOBALS['http_queue'] = [sgap_http(400, ['error' => 'invalid_grant', 'error_description' => 'The refresh token has expired'])];
$refused = SGAP_MCP_Auth::refresh();
check('mcp: a refused renewal carries the API reason', is_wp_error($refused) && str_contains($refused->get_error_message(), 'The refresh token has expired') && ($refused->data['status'] ?? 0) === 400);
check('mcp: the refusal is recorded and raised as a problem', SGAP_MCP_Auth::state()['ok'] === false && str_contains(SGAP_MCP_Auth::problem(), 'MCP access'));

$GLOBALS['http_calls'] = [];
$GLOBALS['http_queue'] = [
    sgap_http(200, ['access_token' => 'access-2', 'refresh_token' => 'refresh-2', 'expires_in' => 3600]),
    ['response' => ['code' => 200], 'headers' => ['mcp-session-id' => 'sess-1'], 'body' => json_encode(['result' => ['protocolVersion' => '2025-06-18']])],
    sgap_http(200, ['result' => true]),
    sgap_http(200, ['result' => ['tools' => [['name' => 'a'], ['name' => 'b'], ['name' => 'c']]]]),
];
$renewed = SGAP_MCP_Auth::refresh();
check('mcp: a renewal stores the new access token and the rotated refresh token', is_array($renewed) && get_option('sgap_mcp_access_token') === 'access-2' && get_option('sgap_mcp_refresh_token') === 'refresh-2');
check('mcp: the renewal is a form-encoded call to the token endpoint', str_contains($GLOBALS['http_calls'][0][0], '/wp-json/novamira/v1/oauth/token') && ($GLOBALS['http_calls'][0][1]['body']['grant_type'] ?? '') === 'refresh_token' && ($GLOBALS['http_calls'][0][1]['body']['client_id'] ?? '') === 'client-abc');
check('mcp: the token is verified with initialize + session + tools/list', count($GLOBALS['http_calls']) === 4 && ($GLOBALS['http_calls'][2][1]['headers']['Mcp-Session-Id'] ?? '') === 'sess-1' && (json_decode((string) $GLOBALS['http_calls'][3][1]['body'], true)['method'] ?? '') === 'tools/list');
check('mcp: the verified tool count is reported', $renewed['tools'] === 3 && SGAP_MCP_Auth::status()['tools'] === 3 && SGAP_MCP_Auth::status()['last_ok'] === true);
check('mcp: a verified renewal clears the problem line', SGAP_MCP_Auth::problem() === '' && SGAP_MCP_Auth::status()['problem'] === '');
check('mcp: the rotated secret is stored without autoload', ($GLOBALS['autoload']['sgap_mcp_refresh_token'] ?? null) === false);
$before = count($GLOBALS['http_calls']);
check('mcp: a valid cached token is reused without a call', SGAP_MCP_Auth::token() === 'access-2' && count($GLOBALS['http_calls']) === $before);
SGAP_MCP_Auth::run();
check('mcp: the cron does not renew twice inside the window', count($GLOBALS['http_calls']) === $before);

$GLOBALS['http_queue'] = [sgap_http(200, ['access_token' => 'access-3', 'refresh_token' => 'refresh-3', 'expires_in' => 3600]), ['response' => ['code' => 200], 'headers' => ['mcp-session-id' => 's2'], 'body' => json_encode(['result' => []])], sgap_http(200, []), sgap_http(200, ['result' => ['tools' => [['name' => 'x']]]])];
$report = SGAP_MCP_Auth::refresh_and_report();
check('mcp: the mcp-refresh tool reports the renewal and the status', $report['renewed'] === true && $report['tools'] === 1 && get_option('sgap_mcp_refresh_token') === 'refresh-3');

$GLOBALS['http_queue'] = [sgap_http(200, ['access_token' => 'access-4', 'refresh_token' => 'refresh-4', 'expires_in' => 3600]), ['response' => ['code' => 200], 'headers' => ['mcp-session-id' => 's3'], 'body' => json_encode(['result' => []])], sgap_http(200, []), sgap_http(200, ['result' => ['tools' => []]])];
$unusable = SGAP_MCP_Auth::refresh();
check('mcp: a token that lists no tools is not reported as renewed', is_wp_error($unusable) && str_contains($unusable->get_error_message(), 'no tools') && SGAP_MCP_Auth::status()['last_ok'] === false);

check('mcp: an SSE-framed answer is decoded', SGAP_MCP_Auth::decode("event: message\ndata: {\"result\":{\"tools\":[{\"name\":\"a\"}]}}\n") === ['result' => ['tools' => [['name' => 'a']]]]);
check('mcp: a broken body is an empty array, not a warning', SGAP_MCP_Auth::decode('<html>404</html>') === []);
check('mcp: the API reason is read from its own field', SGAP_MCP_Auth::detail(['error' => 'invalid_grant', 'error_description' => 'The refresh token has expired']) === 'The refresh token has expired');
check('mcp: a JSON-RPC error object gives its message', SGAP_MCP_Auth::detail(['error' => ['message' => 'Invalid session']]) === 'Invalid session');

do_action('wp_abilities_api_categories_init');
do_action('wp_abilities_api_init');
$mcp_status = $GLOBALS['abilities']['steamgears-tools/mcp-status'] ?? null;
$mcp_refresh = $GLOBALS['abilities']['steamgears-tools/mcp-refresh'] ?? null;
check('mcp: both MCP tools are registered', $mcp_status !== null && $mcp_refresh !== null);
check('mcp: status is read-only and the renewal is not', ($mcp_status['meta']['annotations']['readonly'] ?? false) === true && ($mcp_refresh['meta']['annotations']['readonly'] ?? true) === false);
check('mcp: the status tool answers through the ability', SGAP_MCP_Tools::mcp_status([])['configured'] === true && is_array(call_user_func($mcp_status['execute_callback'], [])));
$GLOBALS['http_queue'] = [sgap_http(500, ['error' => 'server_error'])];
$tool_report = call_user_func($mcp_refresh['execute_callback'], []);
check('mcp: the renewal tool reports a failed attempt instead of throwing', $tool_report['renewed'] === false && str_contains($tool_report['error'], 'HTTP 500'));
