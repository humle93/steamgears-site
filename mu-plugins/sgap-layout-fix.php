<?php
/**
 * Plugin Name: SGAP Layout Fix
 * Description: Retter 1 px vandret overflow på sider med temaets alignfull-sidehoved (kurv og kasse): 100vw regner scrollbaren med, så gruppen bliver 12 px for bred.
 * Version: 1.0.0
 * Author: Steamgears
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_head',
	function () {
		echo '<style id="sgap-layout-fix">.wp-block-group.alignfull{max-width:100%}</style>' . "\n";
	},
	99
);
