<?php

$root = dirname( __DIR__ ) . '/contactpanda';
$newsletter = file_get_contents( $root . '/includes/class-contactpanda-newsletters.php' );
$mcp = file_get_contents( $root . '/includes/class-contactpanda-mcp.php' );
$store = file_get_contents( $root . '/includes/class-contactpanda-store.php' );

foreach ( array( 'template_planned_as_newsletter', 'render_locked_template', 'Create editable copy' ) as $contract ) {
	if ( false === strpos( $newsletter, $contract ) ) { fwrite( STDERR, "Missing newsletter 0.8.63 contract: {$contract}\n" ); exit( 1 ); }
}
foreach ( array( 'contactpanda_email_template_create_draft', 'create_template_draft' ) as $contract ) {
	if ( false === strpos( $mcp, $contract ) ) { fwrite( STDERR, "Missing MCP 0.8.63 contract: {$contract}\n" ); exit( 1 ); }
}
foreach ( array( 'source_created_at', 'source_updated_at', 'imported_at', 'preserve_source_timestamps' ) as $contract ) {
	if ( false === strpos( $store, $contract ) ) { fwrite( STDERR, "Missing migration 0.8.63 contract: {$contract}\n" ); exit( 1 ); }
}

echo "ContactPanda Basic 0.8.63 contracts passed.\n";
