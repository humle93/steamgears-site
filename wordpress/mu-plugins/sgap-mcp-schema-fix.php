<?php
/**
 * Plugin Name: MCP tool schema fix (Claude / aimlapi)
 * Description: Folds top-level oneOf / anyOf / allOf out of ability input schemas, so the site's MCP tools are accepted by Anthropic-compatible APIs. Fixes: tools.N.custom.input_schema: input_schema does not support oneOf, allOf, or anyOf at the top level.
 * Version: 1.1.0
 * Author: -
 *
 * Install: copy this file and aimlapi-tool-schema-sanitizer.php into
 * wp-content/mu-plugins/ (both in the same folder, flat — not in a subfolder).
 * Nothing else to configure; it is active from the next request.
 *
 * Why here: the site's tools are WordPress abilities turned into MCP tools by
 * the MCP adapter. Every ability passes through the `wp_register_ability_args`
 * filter on its way in, so sanitising there covers this plugin, WordPress core,
 * WooCommerce, Rank Math, AI Engine and anything else that registers an ability
 * — no plugin files have to be edited, and plugin updates cannot undo it.
 *
 * Where to see what it did (no database needed):
 *   - a dismissible notice in wp-admin, and
 *   - Tools > Site Health > Info, section "MCP tool schema fix".
 * Both list the abilities whose schema had to be repaired. The same list is kept
 * in the options `sgap_mcp_schema_fixed` and `sgap_mcp_schema_fixed_time` (both
 * not autoloaded). With WP_DEBUG on, each repair is also written to the PHP
 * error log.
 *
 * To remove the fix later: delete this file (and the sanitizer file).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/aimlapi-tool-schema-sanitizer.php';

/**
 * The option that remembers which abilities had an incompatible schema.
 */
define( 'SGAP_SCHEMA_FIX_OPTION', 'sgap_mcp_schema_fixed' );

/**
 * The option that remembers when that list last changed (unix timestamp).
 */
define( 'SGAP_SCHEMA_FIX_TIME_OPTION', 'sgap_mcp_schema_fixed_time' );

/**
 * The per-user meta key that hides the admin notice once it has been read.
 */
define( 'SGAP_SCHEMA_FIX_DISMISS_META', 'sgap_mcp_schema_fix_dismissed' );

/**
 * Rewrites one ability's input schema when it uses a root-level combinator.
 *
 * @param mixed  $args         Ability registration arguments.
 * @param string $ability_name Full ability name, e.g. "steamgears-tools/get-option".
 * @return mixed
 */
function sgap_schema_fix_args( $args, $ability_name = '' ) {
	if ( ! is_array( $args ) || empty( $args['input_schema'] ) || ! is_array( $args['input_schema'] ) ) {
		return $args;
	}
	if ( ! aimlapi_has_root_combinator( $args['input_schema'] ) ) {
		return $args;
	}

	$args['input_schema'] = aimlapi_sanitize_tool_schema( $args['input_schema'] );
	sgap_schema_fix_note( (string) $ability_name );

	return $args;
}

/**
 * Remembers (once) that an ability had to be rewritten, and logs it in debug mode.
 *
 * @param string $ability_name Name of the rewritten ability.
 */
function sgap_schema_fix_note( $ability_name ) {
	$key = $ability_name !== '' ? $ability_name : '(unnamed ability)';

	$log = isset( $GLOBALS['sgap_schema_fix_log'] ) ? (array) $GLOBALS['sgap_schema_fix_log'] : array();
	if ( in_array( $key, $log, true ) ) {
		return;
	}
	$log[] = $key;
	sort( $log );
	$GLOBALS['sgap_schema_fix_log'] = $log;

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'MCP schema fix: folded a root-level oneOf/anyOf/allOf in ' . $key );
	}
}

/**
 * Writes the list of rewritten abilities to a non-autoloaded option, once per request.
 */
function sgap_schema_fix_store() {
	$log = isset( $GLOBALS['sgap_schema_fix_log'] ) ? (array) $GLOBALS['sgap_schema_fix_log'] : array();
	if ( ! $log ) {
		return;
	}
	$stored = get_option( SGAP_SCHEMA_FIX_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	if ( $stored === $log ) {
		return;
	}
	update_option( SGAP_SCHEMA_FIX_OPTION, $log, false );
	update_option( SGAP_SCHEMA_FIX_TIME_OPTION, time(), false );
}

/**
 * Reads the stored list of repaired abilities.
 *
 * @return string[]
 */
function sgap_schema_fix_list() {
	if ( ! function_exists( 'get_option' ) ) {
		return array();
	}
	$stored = get_option( SGAP_SCHEMA_FIX_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$list = array();
	foreach ( $stored as $name ) {
		$name = (string) $name;
		if ( '' !== $name ) {
			$list[] = $name;
		}
	}
	return $list;
}

/**
 * The time the stored list last changed, or 0 if it never has.
 *
 * @return int
 */
function sgap_schema_fix_updated() {
	if ( ! function_exists( 'get_option' ) ) {
		return 0;
	}
	return (int) get_option( SGAP_SCHEMA_FIX_TIME_OPTION, 0 );
}

/**
 * One-line description of the repair, e.g. "Repaired 2 ability schema(s): a, b."
 *
 * Pure function (no WordPress needed) so it can be unit tested.
 *
 * @param string[] $list Repaired ability names.
 * @return string
 */
function sgap_schema_fix_summary( $list ) {
	$list = is_array( $list ) ? array_values( array_filter( array_map( 'strval', $list ) ) ) : array();
	$count = count( $list );

	if ( 0 === $count ) {
		return 'No ability schemas needed repairing yet.';
	}
	if ( 1 === $count ) {
		return 'Repaired 1 ability schema: ' . $list[0] . '.';
	}
	return 'Repaired ' . $count . ' ability schemas: ' . implode( ', ', $list ) . '.';
}

/**
 * The notice/section title.
 *
 * @param string[] $list Repaired ability names.
 * @return string
 */
function sgap_schema_fix_status( $list ) {
	$count = is_array( $list ) ? count( $list ) : 0;
	if ( 0 === $count ) {
		return 'Active - nothing needed repair yet.';
	}
	return 'Active - ' . $count . ' ability schema(s) repaired.';
}

/**
 * The key a user's dismissal is remembered under. Changes when the list changes,
 * so a newly repaired ability brings the notice back.
 *
 * @param string[] $list Repaired ability names.
 * @return string
 */
function sgap_schema_fix_dismiss_key( $list ) {
	$list = is_array( $list ) ? array_values( array_map( 'strval', $list ) ) : array();
	return md5( implode( "\n", $list ) );
}

/**
 * Builds the Tools > Site Health > Info section (pure, testable).
 *
 * @param string[] $list         Repaired ability names.
 * @param string   $updated_text Already formatted date, or "never".
 * @return array
 */
function sgap_schema_fix_site_health_section( $list, $updated_text = 'never' ) {
	$list = is_array( $list ) ? array_values( array_filter( array_map( 'strval', $list ) ) ) : array();

	return array(
		'label'      => 'MCP tool schema fix',
		'show_count' => false,
		'fields'     => array(
			'status'    => array(
				'label' => 'Status',
				'value' => sgap_schema_fix_status( $list ),
			),
			'updated'   => array(
				'label' => 'Last repair',
				'value' => (string) $updated_text,
			),
			'abilities' => array(
				'label' => 'Repaired abilities',
				'value' => $list ? implode( ', ', $list ) : 'None',
			),
		),
	);
}

/**
 * Adds the section to Tools > Site Health > Info.
 *
 * @param array $info Site Health debug sections.
 * @return array
 */
function sgap_schema_fix_site_health( $info ) {
	if ( ! is_array( $info ) ) {
		return $info;
	}
	$updated = sgap_schema_fix_updated();
	$text    = 'never';
	if ( $updated > 0 ) {
		$format = get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' );
		$text   = function_exists( 'wp_date' ) ? wp_date( $format, $updated ) : gmdate( $format, $updated );
	}
	$info['sgap_schema_fix'] = sgap_schema_fix_site_health_section( sgap_schema_fix_list(), $text );
	return $info;
}

/**
 * Shows a dismissible notice in wp-admin listing the repaired abilities.
 */
function sgap_schema_fix_notice() {
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$list = sgap_schema_fix_list();
	if ( ! $list ) {
		return;
	}

	$key = sgap_schema_fix_dismiss_key( $list );
	if ( (string) get_user_meta( get_current_user_id(), SGAP_SCHEMA_FIX_DISMISS_META, true ) === $key ) {
		return;
	}

	$url = add_query_arg(
		'sgap_schema_fix_dismiss',
		wp_create_nonce( 'sgap_schema_fix_dismiss' ),
		admin_url( 'index.php' )
	);

	printf(
		'<div class="notice notice-info"><p><strong>MCP tool schema fix:</strong> %1$s</p><p><a href="%2$s">Hide this message</a></p></div>',
		esc_html( sgap_schema_fix_summary( $list ) ),
		esc_url( $url )
	);
}

/**
 * Remembers that the current user has read the notice.
 */
function sgap_schema_fix_dismiss() {
	if ( empty( $_GET['sgap_schema_fix_dismiss'] ) ) {
		return;
	}
	if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_GET['sgap_schema_fix_dismiss'] ) );
	if ( ! wp_verify_nonce( $nonce, 'sgap_schema_fix_dismiss' ) ) {
		return;
	}

	update_user_meta(
		get_current_user_id(),
		SGAP_SCHEMA_FIX_DISMISS_META,
		sgap_schema_fix_dismiss_key( sgap_schema_fix_list() )
	);

	wp_safe_redirect( remove_query_arg( 'sgap_schema_fix_dismiss', admin_url( 'index.php' ) ) );
	exit;
}

if ( function_exists( 'add_filter' ) ) {
	add_filter( 'wp_register_ability_args', 'sgap_schema_fix_args', 10, 2 );
	add_action( 'shutdown', 'sgap_schema_fix_store' );
	add_action( 'admin_notices', 'sgap_schema_fix_notice' );
	add_action( 'admin_init', 'sgap_schema_fix_dismiss' );
	add_filter( 'debug_information', 'sgap_schema_fix_site_health' );
}
