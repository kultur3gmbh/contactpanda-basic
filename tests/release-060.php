<?php

$root       = dirname( __DIR__ );
$main       = file_get_contents( $root . '/contactpanda/contactpanda.php' );
$license    = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-license.php' );
$schema     = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-schema.php' );
$newsletter = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletters.php' );
$campaigns  = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-campaigns.php' );
$segments   = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-segments.php' );
$updater    = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-github-updater.php' );
$licensed_updater = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-licensed-updater.php' );

foreach ( array( 'ContactPanda_License::newsletter_entitled', 'ContactPanda_License::advanced_newsletter_entitled', 'contactpanda_boot_newsletter_module', "'free-core'" ) as $contract ) {
	if ( false === strpos( $main . $newsletter, $contract ) ) {
		fwrite( STDERR, "Missing Basic newsletter bootstrap contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( "'licenses'", "'legacy_grants'", 'updates_allowed_for', 'contactpanda_license_daily_check', 'activation_token', 'signature_algorithm', 'canonical_json', 'Previously granted modules remain available', 'OFFLINE_GRACE_DAYS', "'advanced_newsletter'", "'forms_editor'", "'automations'", "'advanced_integrations'", "'form_migration'", 'contactpanda_license_products', 'Products and plans', 'Website usage', 'MasterKey', 'sanitize_limits' ) as $contract ) {
	if ( false === strpos( $license, $contract ) ) {
		fwrite( STDERR, "Missing durable license contract {$contract}.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $license, "\$license['license_key'] =" ) || preg_match( '/update_(?:site_)?option\s*\([^;]*license_key/s', $license ) ) {
	fwrite( STDERR, "A raw license key must never be persisted after activation.\n" );
	exit( 1 );
}

foreach ( array( 'product_update', "'updates/check'", 'ContactPanda_Licensed_Updater', 'upgrader_pre_download', 'hash_file' ) as $contract ) {
	if ( false === strpos( $license . $licensed_updater, $contract ) ) {
		fwrite( STDERR, "Missing licensed update contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'cp_newsletter_templates', 'cp_segments', 'cp_newsletter_campaigns', 'cp_newsletter_recipients', 'cp_newsletter_suppressions' ) as $contract ) {
	if ( false === strpos( $schema, $contract ) ) {
		fwrite( STDERR, "Missing newsletter schema contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'audience_source', 'manual_contact_ids', 'recipient_search', 'ignore_marketing_consent', 'consent_override_reason', 'consent_override_user_id', 'consent_override_at', 'consent_override_count' ) as $contract ) {
	if ( false === strpos( $schema, $contract ) ) {
		fwrite( STDERR, "Missing auditable recipient-selection schema contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'registered_customer_contacts', 'has_protected_optout_status', 'Only administrators may ignore marketing consent.', 'consent_override_count' ) as $contract ) {
	if ( false === strpos( $campaigns, $contract ) ) {
		fwrite( STDERR, "Missing protected customer-recipient contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-email-caption', 'contactpanda-video-provider-email', 'contactpanda-email-body', 'contactpanda-email-video-button' ) as $contract ) {
	if ( false === strpos( $newsletter, $contract ) ) {
		fwrite( STDERR, "Missing responsive email contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'final class ContactPanda_Basic_Newsletters', 'final class ContactPanda_Basic_Newsletter_Campaigns', 'final class ContactPanda_Basic_Segments' ) as $contract ) {
	if ( false === strpos( $newsletter . $campaigns . $segments, $contract ) ) {
		fwrite( STDERR, "Missing shared newsletter class {$contract}.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $license, "add_filter( 'contactpanda_updates_allowed'" ) ) {
	fwrite( STDERR, "The free Basic updater must not be disabled by a paid module expiry.\n" );
	exit( 1 );
}

echo "ContactPanda Basic 0.6.0 contracts passed.\n";
