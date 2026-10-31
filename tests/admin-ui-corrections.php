<?php

$root       = dirname( __DIR__ );
$plugin     = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-plugin.php' );
$store      = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-store.php' );
$data_tools = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-data-tools.php' );
$script     = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin.js' );
$shared_ui  = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin-ui.js' );
$styles     = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin.css' );
$menu_styles = file_get_contents( $root . '/contactpanda/assets/contactpanda-admin-menu.css' );
$bootstrap  = file_get_contents( $root . '/contactpanda/contactpanda.php' );
$registration = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-registration.php' );
$company_profile = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-company-profile.php' );
$newsletters = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-newsletters.php' );
$system_emails = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-system-emails.php' );
$capabilities = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-capabilities.php' );

foreach ( array( 'data-contactpanda-legal-form', 'Name or organisation', 'Chairperson or president', "<select name=\"country\"", "\$profile = self::get();" ) as $contract ) {
	if ( false === strpos( $company_profile, $contract ) ) {
		fwrite( STDERR, "Missing inclusive website-owner contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( "Payments and documents", "Stamp image", "Signature image", "Country code" ) as $obsolete_contract ) {
	if ( false !== strpos( $company_profile, "esc_html__( '" . $obsolete_contract ) ) {
		fwrite( STDERR, "Basic must not render {$obsolete_contract} in website-owner settings.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $system_emails, "contactpanda_general_settings_sections" ) || false === strpos( $newsletters, 'ContactPanda_System_Emails::render_settings();' ) ) {
	fwrite( STDERR, "System email design must be consolidated into Email defaults.\n" );
	exit( 1 );
}

foreach ( array( "class_exists( 'ContactPanda_Plus_Messaging' )", "class_exists( 'ContactPanda_Plus_Email_Delivery' )" ) as $contract ) {
	if ( false === strpos( $registration, $contract ) ) {
		fwrite( STDERR, "Missing optional Plus integration visibility contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'management_available()', "ContactPanda_License::entitled( 'plus' )", 'ContactPanda Plus is required to manage user roles.' ) as $contract ) {
	if ( false === strpos( $capabilities, $contract ) ) {
		fwrite( STDERR, "Missing Plus-only role management contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'Button preview', 'contactpanda-theme-button-preview is-contactpanda', 'contactpanda-theme-button-preview is-theme', 'Detected theme colors' ) as $contract ) {
	if ( false === strpos( $registration, $contract ) ) {
		fwrite( STDERR, "Missing theme and button preview contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'Drop a CSV file here', 'Drop a vCard file here', 'contactpanda-file-drop-actions' ) as $contract ) {
	if ( false === strpos( $data_tools, $contract ) ) {
		fwrite( STDERR, "Missing import dropzone contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-people-search-row', 'contactpanda-search-primary', 'Quick search by name, email, phone, or company', "esc_html__( 'Execute', 'contactpanda' )" ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing people-list UI contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'prevent_empty_translation', 'extension_create_contact', "__( 'Order total', 'contactpanda' )" ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing resilient administrator rendering contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-heading', 'contactpanda-master-data', 'contactpanda-tabs-card' ) as $structural_class ) {
	if ( ! preg_match( '/echo\s+[\'\"][^;]*' . preg_quote( $structural_class, '/' ) . '/', $plugin ) ) {
		fwrite( STDERR, "Critical layout wrapper {$structural_class} must be emitted independently of translations.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $styles, '.contactpanda-people-table{table-layout:fixed}' ) ) {
	fwrite( STDERR, "The people table must not use Safari-sensitive fixed layout.\n" );
	exit( 1 );
}

foreach ( array( 'contactpanda-table-scroll', 'contactpanda-people-table', 'contactpanda-email-column' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing responsive people-table contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( "'contactpanda_page_contactpanda-data-transfer'", "'contactpanda_page_contactpanda-migration'" ) as $screen ) {
	if ( false === strpos( $plugin, $screen ) ) {
		fwrite( STDERR, "The Import / Export or Migration page does not load the ContactPanda administrator assets.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-company-relation-modal', 'Assign another company', 'Assign company', 'Remove this company assignment?' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing company-assignment popup contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-open-company-relation', 'contactpanda-close-company-relation', 'contactpanda-file-drop' ) as $contract ) {
	if ( false === strpos( $script, $contract ) ) {
		fwrite( STDERR, "Missing administrator interaction contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-file-drop-actions', 'contactpanda-people-search-row', 'contactpanda-search-primary', 'contactpanda-company-relation-modal', 'contactpanda-table-scroll', 'contactpanda-email-column' ) as $contract ) {
	if ( false === strpos( $styles, $contract ) ) {
		fwrite( STDERR, "Missing administrator style contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'createImagePicker', 'enhanceColorPicker', 'enhanceListActions', 'contactpanda-list-action', 'contactpanda-unified-color-popover', 'contactpanda-unified-color-palette' ) as $contract ) {
	if ( false === strpos( $shared_ui, $contract ) ) {
		fwrite( STDERR, "Missing shared family UI contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda_sanitize_css_color', 'Theme and template colors', 'Enter a valid HEX, RGB, or RGBA color.' ) as $contract ) {
	if ( false === strpos( $bootstrap, $contract ) ) {
		fwrite( STDERR, "Missing shared family color contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( '.contactpanda-unified-color-popover', '.contactpanda-unified-color-slider.is-hue', '.contactpanda-unified-color-slider.is-alpha' ) as $contract ) {
	if ( false === strpos( $styles, $contract ) ) {
		fwrite( STDERR, "Missing shared family color style {$contract}.\n" );
		exit( 1 );
	}
}
foreach ( array( 'grid-template-columns:minmax(140px,190px) 30px 30px', 'width:min(296px,calc(100vw - 48px))', 'repeat(auto-fit,minmax(min(280px,100%),1fr))' ) as $contract ) {
	if ( false === strpos( $styles, $contract ) ) {
		fwrite( STDERR, "Missing non-clipping responsive color picker contract {$contract}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $styles, '.contactpanda-newsletter-inspector{z-index:20;min-width:0;overflow:visible}' ) ) {
	fwrite( STDERR, "The newsletter inspector must not clip shared color popovers.\n" );
	exit( 1 );
}

foreach ( array( 'contactpanda-admin-ui', 'wp_enqueue_media', 'CONTACTPANDA_ADMIN_UI' ) as $contract ) {
	if ( false === strpos( $bootstrap, $contract ) ) {
		fwrite( STDERR, "Missing shared family UI bootstrap contract {$contract}.\n" );
		exit( 1 );
	}
}

foreach ( array( 'contactpanda-admin-menu', 'contactpanda-newsletters', 'contactpanda-campaigns', 'contactpanda-flows', 'contactpanda-data-transfer', 'contactpanda-migration', 'contactpanda-settings', 'contactpanda-trash' ) as $contract ) {
	if ( false === strpos( $plugin, $contract ) ) {
		fwrite( STDERR, "Missing ContactPanda menu order contract {$contract}.\n" );
		exit( 1 );
	}
}

$menu_positions = array();
foreach ( array( 'contactpanda', 'contactpanda-people', 'contactpanda-firmen', 'contactpanda-newsletters', 'contactpanda-campaigns', 'contactpanda-flows', 'contactpanda-data-transfer', 'contactpanda-migration', 'contactpanda-settings', 'contactpanda-trash' ) as $slug ) {
	if ( ! preg_match( "/'" . preg_quote( $slug, '/' ) . "'\\s*=>\\s*(\\d+)/", $plugin, $match ) ) {
		fwrite( STDERR, "Missing ordered ContactPanda submenu entry {$slug}.\n" );
		exit( 1 );
	}
	$menu_positions[] = (int) $match[1];
}
$sorted_menu_positions = $menu_positions;
sort( $sorted_menu_positions );
if ( $menu_positions !== array_values( array_unique( $menu_positions ) ) || $menu_positions !== $sorted_menu_positions ) {
	fwrite( STDERR, "The ContactPanda submenu entries are not in the required order.\n" );
	exit( 1 );
}

if ( false === strpos( $menu_styles, 'page=contactpanda-data-transfer' ) || false === strpos( $menu_styles, 'border-top' ) ) {
	fwrite( STDERR, "The ContactPanda submenu administration separator is missing.\n" );
	exit( 1 );
}

foreach ( array( 'activity_is_note', 'partition_contact_notes', 'address_update', 'user_account', 'user_roles' ) as $contract ) {
	if ( false === strpos( $store . $plugin, $contract ) ) {
		fwrite( STDERR, "Missing note/activity separation contract {$contract}.\n" );
		exit( 1 );
	}
}

echo "ContactPanda administrator UI correction checks passed.\n";
