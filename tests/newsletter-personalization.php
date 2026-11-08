<?php

$root = dirname( __DIR__ );
$newsletter = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletters.php' );

foreach ( array( "'{email}'", "'{unsubscribe_url}'", '(?:custom:|meta\\.)?([a-zA-Z0-9_-]+)', "'contactpanda_custom_' . \$key", "'primary_phone'", "'address_1'", "implode( ', ', array_map( 'sanitize_text_field'" ) as $contract ) {
	if ( false === strpos( $newsletter, $contract ) ) {
		fwrite( STDERR, "Missing newsletter personalization contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda newsletter personalization checks passed.\n";
