<?php
/** Structural contract for the centrally registered protected family updater. */

$root    = dirname( __DIR__ );
$main    = file_get_contents( $root . '/contactpanda/contactpanda.php' );
$updater = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-licensed-updater.php' );

foreach (
	array(
		'contactpanda_register_family_updaters',
		'CONTACTPANDA_PLUS_FILE',
		'CONTACTPANDA_BUSINESS_FILE',
		"'product' => 'contactpanda-plus'",
		"'product' => 'contactpanda-business'",
	)
	as $contract
) {
	if ( false === strpos( $main, $contract ) ) {
		fwrite( STDERR, "Missing central family updater contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'CONTACTPANDA_PRO_FILE', "'product' => 'contactpanda-pro'", 'ContactPanda Pro (Legacy Bridge)' ) as $retired_contract ) {
	if ( false !== strpos( $main, $retired_contract ) ) {
		fwrite( STDERR, "Retired Pro Legacy updater contract is still registered: {$retired_contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'private static $registered', 'plugin_basename', 'pre_set_site_transient_update_plugins' ) as $contract ) {
	if ( false === strpos( $updater, $contract ) ) {
		fwrite( STDERR, "Missing updater deduplication contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "Central ContactPanda family updater contracts passed.\n";
