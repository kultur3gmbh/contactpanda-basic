<?php
/** Structural contract for the WooCommerce-independent customer portal. */

$root     = dirname( __DIR__ );
$main     = file_get_contents( $root . '/contactpanda/contactpanda.php' );
$portal   = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-customer-portal.php' );
$identity = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-identity.php' );
$account  = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-my-account.php' );
$plugin   = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );

foreach ( array( 'class-contactpanda-identity.php', 'class-contactpanda-customer-portal.php', 'ContactPanda_Customer_Portal' ) as $contract ) {
	if ( false === strpos( $main, $contract ) ) {
		fwrite( STDERR, "Missing Basic portal bootstrap contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda_customer_portal_tabs', 'contactpanda_portal_profile_saved', 'contactpanda_portal_subscription_changed', 'contactpanda_portal_preferences_saved', 'contactpanda_portal_save_preferences', 'contactpanda_preference_channels', 'contactpanda_preference_topics', 'contactpanda_preference_languages', 'contactpanda/customer-portal', 'contactpanda_customer_portal' ) as $contract ) {
	if ( false === strpos( $portal, $contract ) ) {
		fwrite( STDERR, "Missing customer portal extension contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contact_by_user_id', 'contact_by_email', 'contactpanda_resolve_user_contact' ) as $contract ) {
	if ( false === strpos( $identity, $contract ) ) {
		fwrite( STDERR, "Missing provider-neutral identity contract {$contract}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $plugin, 'extension_request_marketing_optin' ) ) {
	fwrite( STDERR, "Missing provider-neutral extension newsletter consent contract.\n" );
	exit( 1 );
}

foreach ( array( 'title_prefix', 'title_suffix', 'company', 'billing_company' ) as $field ) {
	if ( false === strpos( $portal . $identity, $field ) ) {
		fwrite( STDERR, "Missing backwards-compatible customer profile field {$field}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $account, 'ContactPanda_Customer_Portal::render_profile' ) ) {
	fwrite( STDERR, "WooCommerce profile adapter does not use the central portal renderer.\n" );
	exit( 1 );
}

foreach ( array( 'contactpanda-customer-portal.css', 'contactpanda-portal-account', 'contactpanda-portal-panel' ) as $contract ) {
	if ( false === strpos( $account, $contract ) ) {
		fwrite( STDERR, "WooCommerce profile adapter is missing the portal presentation contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "Customer portal structural contracts passed.\n";
