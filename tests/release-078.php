<?php

$root      = dirname( __DIR__ );
$plugin    = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );
$campaigns = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-campaigns.php' );
$schema    = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletter-schema.php' );
$script    = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin.js' );
$styles    = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin.css' );

foreach ( array( 'render_dashboard_product_visual', 'contactpanda_dashboard_product_visual', 'contactpanda-admin-icon.png', 'contactpanda-dashboard-product-visual' ) as $contract ) {
	if ( false === strpos( $plugin . $styles, $contract ) ) {
		fwrite( STDERR, "Missing ContactPanda dashboard visual contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'extension_assign_tag_by_name', 'extension_record_purpose_consent', 'extension_sync_subscription', 'panda_seasons_contact_registered' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing extension subscription contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'render_dashboard', 'contactpanda-dashboard-grid', 'contactpanda-dashboard-section', 'contactpanda-dashboard-last-mailing', 'dashboard_duplicate_count', 'contactpanda_dashboard_communication_cards', 'render_dashboard_upgrades', 'contactpanda_dashboard_upgrade_offers', 'contactpanda_product_sales_url', 'register_news_dashboard_widget', 'wp_add_dashboard_widget', 'render_dashboard_news', 'contactpanda_news_feed_url', 'contactpanda_news_url', "array( 'contactpanda-business' )", 'CONTACTPANDA_PLUS_VERSION', 'CONTACTPANDA_PRO_VERSION', 'People and companies', 'Newsletter and automations', 'Last mailing', 'ContactPanda News', 'More possibilities for your business', 'contactpanda-people-search-row', 'Select all %d matching people', '%1$d of %2$d entries' ) as $contract ) {
	if ( false === strpos( $plugin . $styles, $contract ) ) {
		fwrite( STDERR, "Missing ContactPanda 0.7.8 list or dashboard contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'manual_contact_ids', "'manual' === \$audience_source", 'Selected people', 'Delivered / opened / clicked' ) as $contract ) {
	if ( false === strpos( $schema . $campaigns, $contract ) ) {
		fwrite( STDERR, "Missing ContactPanda 0.7.8 newsletter-selection contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'openNewsletterSelection', 'selectAllMatchingPeople', 'people_selection', 'contactpanda-newsletter-selection-frame' ) as $contract ) {
	if ( false === strpos( $script . $styles, $contract ) ) {
		fwrite( STDERR, "Missing ContactPanda 0.7.8 newsletter interaction contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'set_admin_block', 'clear_admin_block', 'Permanently block address', 'marketing_block_reason' ) as $contract ) {
	if ( false === strpos( $schema . $plugin, $contract ) ) {
		fwrite( STDERR, "Missing ContactPanda 0.7.8 consent-block contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda Basic 0.7.8 contracts passed.\n";
