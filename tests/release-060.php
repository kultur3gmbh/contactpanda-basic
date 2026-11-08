<?php

$root       = dirname( __DIR__ );
$main       = file_get_contents( $root . '/contactpanda/contactpanda.php' );
$schema     = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-schema.php' );
$newsletter = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletters.php' );
$campaigns  = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-campaigns.php' );
$segments   = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-segments.php' );
$data_tools = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-data-tools.php' );
$plugin     = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );

foreach ( array( 'contactpanda_boot_newsletter_module', "'free-core'" ) as $contract ) {
	if ( false === strpos( $main . $newsletter, $contract ) ) {
		fwrite( STDERR, "Missing Basic newsletter bootstrap contract {$contract}.\n" );
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

foreach ( array( 'parse_vcard_contents', 'handle_vcard_import', 'export_contacts_vcard', 'subject_b', 'ab_test_percent', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content' ) as $plus_only_contract ) {
	if ( false !== strpos( $data_tools . $plugin . $campaigns, $plus_only_contract ) ) {
		fwrite( STDERR, "Basic still embeds Plus-only implementation {$plus_only_contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda_render_contact_import_extensions', 'contactpanda_render_contact_export_extensions', 'contactpanda_campaign_render_advanced_settings', 'contactpanda_campaign_render_delivery_options' ) as $extension_contract ) {
	if ( false === strpos( $data_tools . $plugin . $campaigns, $extension_contract ) ) {
		fwrite( STDERR, "Basic is missing extension point {$extension_contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda Basic 0.6.0 contracts passed.\n";
