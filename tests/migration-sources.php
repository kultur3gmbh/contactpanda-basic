<?php

$root = dirname( __DIR__ );
$registration = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-registration.php' );
$data_tools = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-data-tools.php' );

$checks = array(
	'dedicated migration menu' => "'contactpanda-migration'",
	'dynamic source registry' => 'public static function migration_sources()',
	'extension source filter' => "apply_filters( 'contactpanda_migration_sources'",
	'source-specific renderer' => "do_action( 'contactpanda_render_migration_source_' . \$source",
	'FluentCRM preview handler' => 'handle_migrate_fluentcrm()',
	'stable FluentCRM source identifier' => "'fluentcrm_subscriber_id' => \$source_id",
	'non-destructive status protection' => "array( 'unsubscribed', 'bounced', 'spam' )",
	'Newsletter Plugin AJAX migration' => "wp_ajax_contactpanda_migrate_newsletter_plugin",
	'Newsletter Plugin live progress' => 'contactpanda-live-migration-form',
);

foreach ( $checks as $label => $needle ) {
	$source = in_array( $label, array( 'dedicated migration menu' ), true ) ? $data_tools : $registration;
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Migration source check failed: {$label}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $data_tools, "! empty( \$source['installed'] )" ) ) {
	fwrite( STDERR, "Migration tabs must hide unavailable source systems.\n" );
	exit( 1 );
}

echo "Migration source checks passed.\n";
