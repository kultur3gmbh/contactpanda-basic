<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$contactpanda_general_settings = (array) get_option( 'contactpanda_general_settings', array() );
if ( empty( $contactpanda_general_settings['delete_on_uninstall'] ) ) {
	return;
}

require_once __DIR__ . '/includes/class-contactpanda-schema.php';
require_once __DIR__ . '/includes/class-contactpanda-newsletter-schema.php';

ContactPanda_Schema::drop_all();
ContactPanda_Newsletter_Schema::drop_all();

delete_option( 'contactpanda_general_settings' );
delete_option( 'contactpanda_registration_settings' );
delete_option( 'contactpanda_db_version' );
delete_option( 'contactpanda_last_migration_report' );
delete_option( 'contactpanda_newsletter_settings' );

global $wpdb;
$contactpanda_meta_pattern = $wpdb->esc_like( 'contactpanda_' ) . '%';
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$contactpanda_meta_pattern
	)
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup explicitly requested by the administrator.

$contactpanda_option_patterns = array(
	$wpdb->esc_like( '_transient_contactpanda_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_contactpanda_' ) . '%',
);
foreach ( $contactpanda_option_patterns as $contactpanda_option_pattern ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$contactpanda_option_pattern
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup explicitly requested by the administrator.
}
