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

	/** Record an opt-in diagnostic memory sample without logging customer data. */
	public static function record_profile( $label ) {
		if ( ! defined( 'CONTACTPANDA_MEMORY_PROFILING' ) || ! CONTACTPANDA_MEMORY_PROFILING ) return;
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
}

