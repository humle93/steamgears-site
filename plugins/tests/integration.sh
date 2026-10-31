#!/usr/bin/env bash
# Installs WordPress against a MySQL server, activates the plugin and checks
# it end to end (install, REST, click redirect, cleanup, uninstall).
# Needs: php, wp-cli, curl, a reachable MySQL. Used by .github/workflows/plugin-ci.yml.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WP_DIR="${WP_DIR:-/tmp/wordpress}"
WP_VERSION="${WP_VERSION:-latest}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
DB_NAME="${DB_NAME:-wordpress}"
URL="http://127.0.0.1:8080"
FAILED=0

wpc() { wp --path="$WP_DIR" --allow-root "$@"; }
sql() { wpc eval "global \$wpdb; echo \$wpdb->get_var(\"$1\");"; }
check() {
    if [ "$2" = "$3" ]; then echo "PASS $1"; else echo "FAIL $1 (got '$2', want '$3')"; FAILED=1; fi
}

echo "== Install WordPress $WP_VERSION"
mkdir -p "$WP_DIR"
wpc core download --version="$WP_VERSION" --force --quiet
wpc config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --force --quiet
wpc config set WP_ENVIRONMENT_TYPE local --quiet
wpc config set WP_DEBUG true --raw --quiet
wpc config set WP_DEBUG_LOG true --raw --quiet
wpc config set WP_DEBUG_DISPLAY false --raw --quiet
wpc db create || true
wpc core install --url="$URL" --title="Plugin CI" --admin_user=admin --admin_password=admin \
    --admin_email=ci@example.com --skip-email --quiet
wpc rewrite structure '/%postname%/' --quiet

echo "== Activate plugin"
rm -rf "$WP_DIR/wp-content/plugins/steamgears-autopilot"
cp -r "$ROOT/plugins/steamgears-autopilot" "$WP_DIR/wp-content/plugins/"
wpc plugin activate steamgears-autopilot

echo "== In-process checks"
wpc eval-file "$ROOT/plugins/tests/integration.php"

echo "== HTTP checks"
php -S 127.0.0.1:8080 -t "$WP_DIR" "$ROOT/plugins/tests/router.php" >/tmp/php-server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 20); do curl -s -o /dev/null "$URL/" && break; sleep 0.5; done

QID="$(wpc option get sgap_ci_queue_id)"
HUMAN="Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148"

HEADERS="$(curl -s -o /dev/null -D - -A "$HUMAN" "$URL/go/$QID/")"
check "go: human gets 302" "$(echo "$HEADERS" | head -1 | awk '{print $2}')" "302"
check "go: redirects to UTM url" "$(echo "$HEADERS" | grep -ci '^location: .*/ci-goggles/?utm_source=pinterest')" "1"
check "go: sets click cookie" "$(echo "$HEADERS" | grep -ci '^set-cookie: sgap_click=')" "1"

HEADERS="$(curl -s -o /dev/null -D - -A 'Pinterestbot/1.0' "$URL/go/$QID/")"
check "go: bot still redirected" "$(echo "$HEADERS" | head -1 | awk '{print $2}')" "302"
check "go: bot gets no cookie" "$(echo "$HEADERS" | grep -ci '^set-cookie: sgap_click=' || true)" "0"
check "go: only the human click is logged" \
    "$(sql "SELECT COUNT(*) FROM {\$wpdb->prefix}sgap_events WHERE event_type = 'click' AND queue_id = $QID")" "1"
check "go: click row stored" "$(sql "SELECT COUNT(*) FROM {\$wpdb->prefix}sgap_cro WHERE queue_id = $QID")" "1"

HEADERS="$(curl -s -o /dev/null -D - -A "$HUMAN" "$URL/go/999999/")"
check "go: unknown id goes home" "$(echo "$HEADERS" | grep -i '^location:' | tr -d '\r' | awk '{print $2}')" "$URL/"

RSS_ID="$(wpc option get sgap_ci_rss_id)"
FEED="$(curl -s -D /tmp/feed-headers "$URL/sgap-feed/facebook/")"
check "rss: feed served as RSS" "$(grep -ci '^content-type: application/rss+xml' /tmp/feed-headers)" "1"
check "rss: feed contains the released post" "$(echo "$FEED" | grep -c "<link>$URL/go/$RSS_ID/</link>")" "1"
ETAG="$(grep -i '^etag:' /tmp/feed-headers | tr -d '\r' | cut -d' ' -f2-)"
check "rss: unchanged feed answers 304 to If-None-Match" "$(curl -s -o /dev/null -w '%{http_code}' -H "If-None-Match: $ETAG" "$URL/sgap-feed/facebook/")" "304"
check "rss: unknown channel is 404" "$(curl -s -o /dev/null -w '%{http_code}' "$URL/sgap-feed/myspace/")" "404"
HEADERS="$(curl -s -o /dev/null -D - -A 'facebookplatform/1.0 (+http://developers.facebook.com)' "$URL/go/$RSS_ID/")"
check "rss: Publer scraper follows /go/" "$(echo "$HEADERS" | head -1 | awk '{print $2}')" "302"
check "rss: Publer scraper is not counted as a click" \
    "$(sql "SELECT COUNT(*) FROM {\$wpdb->prefix}sgap_events WHERE event_type = 'click' AND queue_id = $RSS_ID")" "0"

APP_PASS="$(wpc user application-password create admin ci --porcelain)"
check "REST: app password works" "$(curl -s -o /dev/null -w '%{http_code}' -u "admin:$APP_PASS" "$URL/wp-json/sgap/v1/stats")" "200"
check "REST: anonymous rejected" "$(curl -s -o /dev/null -w '%{http_code}' "$URL/wp-json/sgap/v1/stats")" "401"
check "frontend still renders" "$(curl -s -o /dev/null -w '%{http_code}' "$URL/")" "200"

echo "== PHP notices from the plugin"
LOG="$WP_DIR/wp-content/debug.log"
if [ -f "$LOG" ] && grep -i 'steamgears-autopilot' "$LOG"; then
    echo "FAIL plugin wrote to debug.log"; FAILED=1
else
    echo "PASS no plugin notices in debug.log"
fi

echo "== Uninstall"
wpc plugin deactivate steamgears-autopilot --quiet
check "deactivate clears cleanup cron" "$(wpc cron event list --hook=sgap_daily_cleanup --format=count)" "0"
wpc plugin uninstall steamgears-autopilot --quiet
check "uninstall drops tables" "$(sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '{\$wpdb->prefix}sgap\\\\_%'")" "0"
check "uninstall removes options" "$(sql "SELECT COUNT(*) FROM {\$wpdb->options} WHERE option_name LIKE 'sgap\\\\_%'")" "0"

if [ "$FAILED" -ne 0 ]; then
    echo "Integration checks FAILED"; exit 1
fi
echo "All HTTP and uninstall checks passed."
