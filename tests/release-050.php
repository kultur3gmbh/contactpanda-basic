<?php

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );
$registration = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-registration.php' );
$data_tools = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-data-tools.php' );
$schema = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-schema.php' );
$store = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-store.php' );
$mcp = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-mcp.php' );

foreach ( array( 'contactpanda-data-transfer', 'Import / Export', 'contactpanda_export_log' ) as $contract ) {
	if ( false === strpos( $plugin . $data_tools, $contract ) ) {
		fwrite( STDERR, "Missing import/export contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-migration', 'render_migration', 'render_migration_settings', 'contactpanda_render_migration_tools' ) as $contract ) {
	if ( false === strpos( $registration . $data_tools, $contract ) ) {
		fwrite( STDERR, "Missing migration-tab contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( "'data_source'", 'Leading data source', 'ContactPanda database', 'Groundhogg frontend mode' ) as $contract ) {
	if ( false === strpos( $registration, $contract ) ) {
		fwrite( STDERR, "Missing data-source contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'language varchar(10)', 'language_name', 'salutations_count', 'salutation_language' ) as $contract ) {
	if ( false === strpos( $schema . $registration . $store, $contract ) ) {
		fwrite( STDERR, "Missing multilingual salutation contract {$contract}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $mcp, "apply_filters( 'contactpanda_mcp_allowed'" ) ) {
	fwrite( STDERR, "Missing family MCP authorization extension point.\n" );
	exit( 1 );
}

echo "ContactPanda Basic 0.5.0 contracts passed.\n";
