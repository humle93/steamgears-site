<?php
/**
 * Plugin Name: Steamgears site events
 * Description: Log-only vagt: hvem slog et plugin fra eller til, hvem loggede ind i wp-admin, og hvilke fatale fejl skete der. Ændrer intet andet.
 * Version: 1.0.0
 * Author: Steamgears
 *
 * Skrevet 6/11 2026, efter at WooCommerce, Stripe, Pinterest for WooCommerce og Google for
 * WooCommerce blev deaktiveret på samme sekund uden et spor af hvem eller hvorfor: WordPress
 * gemmer kun tidspunktet i `recently_activated`, og der var ingen `debug.log`.
 *
 * Loggen ligger UDEN FOR webroden, i /var/www/steamgears.eu/steamgears-logs/, med 0700 på
 * mappen og 0600 på filerne, så den ikke kan hentes over HTTP og ikke kan læses af andre konti
 * på hosten. Der logges ingen cookies, tokens eller adgangskoder — kun bruger, IP, user-agent,
 * request-URI, plugin-fil og kaldstak.
 *
 * Filen er en ren lytter: ingen hooks ændrer adfærd, ingen filer skrives andre steder, og
 * intet kastes videre. Slet filen for at stoppe logningen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'sgap_events_log' ) ) {
	/**
	 * Skriver én linje til hændelsesloggen. Fejler aldrig hårdt: logning må ikke kunne
	 * vælte et request, så alt er pakket i @ og der returneres stille.
	 *
	 * @param string $event Kort hændelsesnavn, fx "plugin-deaktiveret".
	 * @param array  $data  Felter der skal med på linjen.
	 */
	function sgap_events_log( $event, array $data = array() ) {
		$dir = '/var/www/steamgears.eu/steamgears-logs';
		$file = $dir . '/site-events.log';

		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0700, true );
		}
		// Defense in depth: hvis mappen en dag skulle havne under webroden.
		if ( is_dir( $dir ) && ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
			@chmod( $dir . '/.htaccess', 0600 );
		}
		// Rotér ved 5 MB, så loggen ikke kan fylde disken.
		if ( is_file( $file ) && filesize( $file ) > 5 * 1024 * 1024 ) {
			@rename( $file, $file . '.1' );
		}

		$base = array(
			'tid'        => gmdate( 'c' ),
			'bruger'     => function_exists( 'wp_get_current_user' ) ? (string) wp_get_current_user()->user_login : '',
			'bruger_id'  => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'ip'         => isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '',
			'ua'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 160 ) : '',
			'uri'        => isset( $_SERVER['REQUEST_URI'] ) ? substr( (string) $_SERVER['REQUEST_URI'], 0, 200 ) : '',
			'metode'     => isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '',
			'cron'       => defined( 'DOING_CRON' ) && DOING_CRON,
			'ajax'       => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax(),
			'rest'       => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'cli'        => defined( 'WP_CLI' ) && WP_CLI,
		);

		$line = wp_json_encode( array_merge( array( 'hændelse' => $event ), $base, $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $line ) ) {
			$line = json_encode( array( 'hændelse' => $event ) );
		}
		@file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX );
		@chmod( $file, 0600 );
	}
}

if ( ! function_exists( 'sgap_events_caller' ) ) {
	/**
	 * De første rammer af kaldstakken, uden WordPress' egen trampolin-støj.
	 * Det er her svaret på "hvem kaldte deactivate_plugins()" ligger.
	 *
	 * @param int $frames Antal rammer.
	 * @return array
	 */
	function sgap_events_caller( $frames = 6 ) {
		$out = array();
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 24 );
		foreach ( $trace as $frame ) {
			$file = isset( $frame['file'] ) ? $frame['file'] : '';
			if ( $file === __FILE__ ) {
				continue;
			}
			if ( $file === '' ) {
				continue;
			}
			$out[] = str_replace( ABSPATH, '', $file ) . ':' . ( isset( $frame['line'] ) ? (int) $frame['line'] : 0 )
				. ( isset( $frame['function'] ) ? ' ' . $frame['function'] : '' );
			if ( count( $out ) >= $frames ) {
				break;
			}
		}
		return $out;
	}
}

add_action( 'deactivated_plugin', function ( $plugin, $network_wide = false ) {
	sgap_events_log( 'plugin-deaktiveret', array(
		'plugin'    => (string) $plugin,
		'netværk'   => (bool) $network_wide,
		'kalder'    => sgap_events_caller(),
	) );
}, 10, 2 );

add_action( 'activated_plugin', function ( $plugin, $network_wide = false ) {
	sgap_events_log( 'plugin-aktiveret', array(
		'plugin'  => (string) $plugin,
		'netværk' => (bool) $network_wide,
		'kalder'  => sgap_events_caller( 4 ),
	) );
}, 10, 2 );

add_action( 'upgrader_process_complete', function ( $upgrader, $hook_extra = array() ) {
	sgap_events_log( 'opdatering', array(
		'type'    => isset( $hook_extra['type'] ) ? (string) $hook_extra['type'] : '',
		'handling'=> isset( $hook_extra['action'] ) ? (string) $hook_extra['action'] : '',
		'emner'   => isset( $hook_extra['plugins'] ) ? array_values( (array) $hook_extra['plugins'] ) : ( isset( $hook_extra['plugin'] ) ? array( (string) $hook_extra['plugin'] ) : array() ),
	) );
}, 10, 2 );

/* Sikkerhedsnet: fanger også en ændring af active_plugins der ikke går gennem hookene ovenfor. */
add_filter( 'pre_update_option_active_plugins', function ( $value, $old = array() ) {
	$old = is_array( $old ) ? $old : array();
	$new = is_array( $value ) ? $value : array();
	$fjernet = array_values( array_diff( $old, $new ) );
	$tilføjet = array_values( array_diff( $new, $old ) );
	if ( $fjernet || $tilføjet ) {
		sgap_events_log( 'active_plugins-ændret', array(
			'fjernet' => $fjernet,
			'tilføjet' => $tilføjet,
			'kalder'  => sgap_events_caller( 5 ),
		) );
	}
	return $value;
}, 10, 2 );

add_action( 'wp_login', function ( $login, $user = null ) {
	if ( is_object( $user ) && ! empty( $user->roles ) && array_intersect( array( 'administrator' ), (array) $user->roles ) ) {
		sgap_events_log( 'admin-login', array( 'login' => (string) $login ) );
	}
}, 10, 2 );

/* Fatale fejl: registreres før WordPress' egen handler, så vi ser den rå fejl. */
register_shutdown_function( function () {
	$error = error_get_last();
	if ( ! is_array( $error ) ) {
		return;
	}
	if ( ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
		return;
	}
	sgap_events_log( 'fatal-fejl', array(
		'besked' => substr( (string) $error['message'], 0, 500 ),
		'fil'    => isset( $error['file'] ) ? str_replace( ABSPATH, '', (string) $error['file'] ) : '',
		'linje'  => isset( $error['line'] ) ? (int) $error['line'] : 0,
	) );
} );

/* PHP's egen fejllog til den private mappe, så advarsler også lander et sted vi kan læse. */
@ini_set( 'log_errors', '1' );
@ini_set( 'error_log', '/var/www/steamgears.eu/steamgears-logs/php-errors.log' );
