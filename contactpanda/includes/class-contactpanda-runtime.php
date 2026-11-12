<?php

defined( 'ABSPATH' ) || exit;

/**
 * Small shared runtime used by ContactPanda family members.
 *
 * Keep this file deliberately dependency-free: it is loaded on every request
 * and decides which larger feature classes are allowed to boot.
 */
final class ContactPanda_Runtime {
	const PROFILE_OPTION = 'contactpanda_runtime_profiles';
	private static $started_at = 0.0;
	private static $started_memory = 0;

	/** Return the current WordPress execution context. */
	public static function context() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) return 'cli';
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) return 'cron';
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return 'ajax';
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return 'rest';
		return is_admin() ? 'admin' : 'frontend';
	}

	/** Whether heavy administrative modules may be initialized. */
	public static function is_backoffice() {
		return in_array( self::context(), array( 'admin', 'ajax', 'rest', 'cron', 'cli' ), true );
	}

	/** Whether a database upgrade is safe and expected in this request. */
	public static function may_upgrade_schema() {
		return in_array( self::context(), array( 'admin', 'cron', 'cli' ), true );
	}

	/** Consistent upper bound for background processing batches. */
	public static function batch_size( $requested = 0, $maximum = 100 ) {
		$default = 50;
		$value   = $requested ? absint( $requested ) : $default;
		return max( 1, min( absint( $maximum ), $value ) );
	}

	/** Return the sanitized ContactPanda page slug selected in wp-admin. */
	public static function admin_page() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
	}

	/** Return the current action without exposing request payloads. */
	public static function request_action() {
		$value = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		return $value;
	}

	/** Match an admin page or action while keeping cron/REST/AJAX workers functional. */
	public static function needs_module( $pages = array(), $action_prefixes = array() ) {
		if ( 'admin' !== self::context() ) return true;
		$page = self::admin_page();
		foreach ( (array) $pages as $candidate ) {
			$candidate = sanitize_key( $candidate );
			if ( $page === $candidate || ( substr( $candidate, -1 ) === '*' && 0 === strpos( $page, rtrim( $candidate, '*' ) ) ) ) return true;
		}
		$action = self::request_action();
		foreach ( (array) $action_prefixes as $prefix ) if ( 0 === strpos( $action, sanitize_key( $prefix ) ) ) return true;
		return false;
	}

	/** Begin an opt-in, privacy-safe request profile. */
	public static function begin_profile() {
		if ( ! self::profiling_enabled() ) return;
		self::$started_at = microtime( true );
		self::$started_memory = memory_get_usage( true );
		register_shutdown_function( array( __CLASS__, 'finish_profile' ) );
	}

	private static function profiling_enabled() {
		$enabled = defined( 'CONTACTPANDA_MEMORY_PROFILING' ) && CONTACTPANDA_MEMORY_PROFILING;
		return (bool) apply_filters( 'contactpanda_runtime_profiling_enabled', $enabled );
	}

	/** Store one bounded diagnostic sample; no URL, payload or customer data is retained. */
	public static function finish_profile() {
		if ( ! self::$started_at || ! self::profiling_enabled() ) return;
		global $wpdb;
		$label = self::admin_page();
		if ( ! $label ) $label = self::context();
		$profiles = (array) get_option( self::PROFILE_OPTION, array() );
		$profiles[] = array(
			'label'       => sanitize_key( $label ),
			'context'     => self::context(),
			'used'        => max( 0, memory_get_usage( true ) - self::$started_memory ),
			'peak'        => memory_get_peak_usage( true ),
			'limit'       => (string) ini_get( 'memory_limit' ),
			'duration_ms' => (int) round( ( microtime( true ) - self::$started_at ) * 1000 ),
			'queries'     => isset( $wpdb->num_queries ) ? absint( $wpdb->num_queries ) : 0,
			'time'        => gmdate( 'c' ),
		);
		update_option( self::PROFILE_OPTION, array_slice( $profiles, -30 ), false );
	}

	/** Record an opt-in diagnostic memory sample without logging customer data. */
	public static function record_profile( $label ) {
		if ( ! self::profiling_enabled() ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;
		$profiles   = (array) get_option( self::PROFILE_OPTION, array() );
		$profiles[] = array(
			'label'   => sanitize_key( $label ),
			'context' => self::context(),
			'current' => memory_get_usage( true ),
			'peak'    => memory_get_peak_usage( true ),
			'limit'   => (string) ini_get( 'memory_limit' ),
			'time'    => gmdate( 'c' ),
		);
		update_option( self::PROFILE_OPTION, array_slice( $profiles, -30 ), false );
	}

	/** Add one central, read-only family diagnosis to WordPress Site Health. */
	public static function site_health_info( $info ) {
		$basic_available = '';
		$updates = get_site_transient( 'update_plugins' );
		$basic_plugin = defined( 'CONTACTPANDA_FILE' ) ? plugin_basename( CONTACTPANDA_FILE ) : '';
		if ( $basic_plugin && is_object( $updates ) && isset( $updates->response[ $basic_plugin ]->new_version ) ) $basic_available = sanitize_text_field( $updates->response[ $basic_plugin ]->new_version );
		$fields = array(
			'basic' => array( 'label' => 'ContactPanda Basic', 'value' => sprintf( '%s → %s', defined( 'CONTACTPANDA_VERSION' ) ? CONTACTPANDA_VERSION : '—', $basic_available ?: '—' ) ),
		);
		$profiles = (array) get_option( self::PROFILE_OPTION, array() );
		if ( $profiles ) {
			$latest = end( $profiles );
			$fields['runtime'] = array(
				'label' => __( 'ContactPanda runtime', 'contactpanda' ),
				'value' => sprintf( '%s · peak %s · %d ms · %d queries', sanitize_key( $latest['label'] ?? '' ), size_format( absint( $latest['peak'] ?? 0 ) ), absint( $latest['duration_ms'] ?? 0 ), absint( $latest['queries'] ?? 0 ) ),
			);
		}
		$info['contactpanda'] = array(
			'label'       => 'ContactPanda',
			'description' => __( 'Installed version, available WordPress update, and optional privacy-safe runtime measurements.', 'contactpanda' ),
			'fields'      => $fields,
		);
		return $info;
	}
}

