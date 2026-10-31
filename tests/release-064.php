<?php

$root = dirname( __DIR__ ) . '/contactpanda';
$campaigns = file_get_contents( $root . '/includes/class-contactpanda-newsletter-campaigns.php' );
$templates = file_get_contents( $root . '/includes/class-contactpanda-newsletters.php' );
$registration = file_get_contents( $root . '/includes/class-contactpanda-registration.php' );
$store = file_get_contents( $root . '/includes/class-contactpanda-store.php' );

foreach ( array( '%d sent', '%d failed', "completed_at" ) as $contract ) {
	if ( false === strpos( $campaigns, $contract ) ) { fwrite( STDERR, "Missing newsletter 0.8.64 contract: {$contract}\n" ); exit( 1 ); }
}
if ( false !== strpos( $templates, 'implode( \', \', array_slice( $usage[\'campaigns\']' ) ) {
	fwrite( STDERR, "Template usage still exposes campaign names.\n" ); exit( 1 );
}
foreach ( array( 'groundhogg_contacts_migration_form', 'migration_mode', 'groundhogg_migration_preview' ) as $contract ) {
	if ( false === strpos( $registration . $store, $contract ) ) { fwrite( STDERR, "Missing split migration 0.8.64 contract: {$contract}\n" ); exit( 1 ); }
}
if ( false === strpos( $store, 'if ( $include_extensions )' ) ) {
	fwrite( STDERR, "Groundhogg contact migration still invokes extension migrations.\n" ); exit( 1 );
}

echo "ContactPanda Basic 0.8.64 contracts passed.\n";
