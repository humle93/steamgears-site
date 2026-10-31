<?php
/**
* Plugin Name: Steamgears Autopilot
* Description: Premium multichannel growth autopilot for Steamgears.eu.
* Version: 7.6.1
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

define('SGAP_VERSION', '7.6.1');
define('SGAP_FILE', __FILE__);

require_once __DIR__ . '/includes/core.php';
require_once __DIR__ . '/includes/publer.php';
require_once __DIR__ . '/includes/generator.php';
require_once __DIR__ . '/includes/abilities.php';
require_once __DIR__ . '/includes/keycheck.php';
require_once __DIR__ . '/includes/rss.php';
require_once __DIR__ . '/includes/updater.php';

SGAP_Publer::init();
SGAP_Generator::init();
SGAP_Abilities::init();
SGAP_KeyCheck::init();
SGAP_RSS::init();
SGAP_Updater::init();
