<?php
/**
* Plugin Name: Steamgears Autopilot
* Description: Premium multichannel growth autopilot for Steamgears.eu.
* Version: 7.79.0
* Requires at least: 6.4
* Requires PHP: 8.0
* Author: Steamgears.eu
* License: GPL-2.0-or-later
* Text Domain: steamgears-autopilot
* Update URI: https://github.com/humle93/steamgears-site
*/

if (!defined('ABSPATH')) exit;

// Another copy (e.g. an old version in a different folder) is already loaded:
// bail out instead of fatally redeclaring the classes. The classes live in a
// separate file because PHP declares a file's top-level classes before any of
// its code runs, so a guard in the same file would come too late.
if (defined('SGAP_VERSION')) {
    if (is_admin()) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Steamgears Autopilot:</strong> another copy of the plugin is already active. Deactivate the old copy.</p></div>';
        });
    }
    return;
}

define('SGAP_VERSION', '7.79.0');
define('SGAP_FILE', __FILE__);
define('SGAP_PATH', __DIR__ . '/');

require_once __DIR__ . '/includes/admin-ui.php';
require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/publer.php';
require_once __DIR__ . '/includes/bluesky.php';
require_once __DIR__ . '/includes/text.php';
require_once __DIR__ . '/includes/mastodon.php';
require_once __DIR__ . '/includes/telegram.php';
require_once __DIR__ . '/includes/locales.php';
require_once __DIR__ . '/includes/preflight.php';
require_once __DIR__ . '/includes/blackout.php';
require_once __DIR__ . '/includes/quality.php';
require_once __DIR__ . '/includes/duplicate.php';
require_once __DIR__ . '/includes/evergreen.php';
require_once __DIR__ . '/includes/generator.php';
require_once __DIR__ . '/includes/openrouter.php';
require_once __DIR__ . '/includes/campaign-agent.php';
require_once __DIR__ . '/includes/coach.php';
require_once __DIR__ . '/includes/meta-audit.php';
require_once __DIR__ . '/includes/abilities.php';
require_once __DIR__ . '/includes/mcp-tools.php';
require_once __DIR__ . '/includes/keys.php';
require_once __DIR__ . '/includes/channel-health.php';
require_once __DIR__ . '/includes/mcp-auth.php';
require_once __DIR__ . '/includes/mcp-woo.php';
require_once __DIR__ . '/includes/keycheck.php';
require_once __DIR__ . '/includes/rss.php';
require_once __DIR__ . '/includes/updater.php';
require_once __DIR__ . '/includes/admin-tools.php';
require_once __DIR__ . '/includes/profiles.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/studio.php';
require_once __DIR__ . '/includes/site-seo.php';
require_once __DIR__ . '/includes/setup.php';
require_once __DIR__ . '/includes/automation.php';
require_once __DIR__ . '/includes/alerts.php';
require_once __DIR__ . '/includes/reminder.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/scheduler.php';
require_once __DIR__ . '/includes/engagement.php';
require_once __DIR__ . '/includes/pinterest.php';
require_once __DIR__ . '/includes/instagram.php';
require_once __DIR__ . '/includes/diagnostics.php';
require_once __DIR__ . '/includes/facebook.php';
require_once __DIR__ . '/includes/forum.php';
require_once __DIR__ . '/includes/reddit.php';
require_once __DIR__ . '/includes/command-center.php';
require_once __DIR__ . '/includes/niche-shop.php';
require_once __DIR__ . '/includes/admin-menu.php';

// Cirla is a module of its own (docs/library/cirla-teknisk-dokumentation-2026-11-06.md §9.3):
// the file is loaded only while the module is switched on, so a module that is off cannot
// register one ability or one cron job. Switching it on takes effect on the next request.
if (get_option('sgap_cirla_enabled', 'no') === 'yes') {
    require_once __DIR__ . '/modules/cirla/class-cirla.php';
    if (is_admin()) {
        require_once __DIR__ . '/modules/cirla/class-cirla-admin.php';
    }
}

SGAP_Publer::init();
SGAP_Generator::init();
SGAP_Campaign_Agent::init();
SGAP_Coach::init();
SGAP_Evergreen::init();
SGAP_Abilities::init();
SGAP_KeyCheck::init();
SGAP_Keys::init();
SGAP_RSS::init();
SGAP_Updater::init();
SGAP_AdminTools::init();
SGAP_Chat::init();
SGAP_Studio::init();
SGAP_Site_SEO::init();
SGAP_Setup::init();
SGAP_Rescue::init();
SGAP_Auto::init();
SGAP_Alerts::init();
SGAP_Audit::init();
SGAP_Engagement::init();
SGAP_Pinterest::init();
SGAP_Instagram::init();
SGAP_Diagnostics::init();
SGAP_Facebook::init();
SGAP_Forum::init();
SGAP_Reddit::init();
SGAP_MCP_Tools::init();
SGAP_Niche_Shop::init();
SGAP_MCP_Key::init();
SGAP_MCP_Auth::init();
SGAP_Menu::init();
if (class_exists('SGAP_Cirla')) {
    SGAP_Cirla::init();
}
if (class_exists('SGAP_Cirla_Admin')) {
    SGAP_Cirla_Admin::init();
}
