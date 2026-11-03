<?php
/**
 * Plugin Name: ContactPanda Basic
 * Description: Free contact and company management for WordPress with newsletters, WooCommerce, migrations, and optional Groundhogg compatibility.
 * Version: 0.9.12
 * Author: Kultur3 GmbH
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: contactpanda
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTPANDA_VERSION', '0.9.12' );
define( 'CONTACTPANDA_ASSET_VERSION', '0.9.12-20261103-1' );
define( 'CONTACTPANDA_EXTENSION_API_VERSION', '1.0' );
define( 'CONTACTPANDA_FILE', __FILE__ );
define( 'CONTACTPANDA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTPANDA_URL', plugin_dir_url( __FILE__ ) );

/** Read a person's canonical VAT ID without depending on WooCommerce user meta. */
function contactpanda_get_person_vat_id( $contact_id ) {
	return class_exists( 'ContactPanda_Plugin' ) ? ContactPanda_Plugin::get_person_vat_id( $contact_id ) : '';
}

/** Read a company's canonical VAT ID without routing it through a contact person. */
function contactpanda_get_company_vat_id( $company_id ) {
	return class_exists( 'ContactPanda_Plugin' ) ? ContactPanda_Plugin::get_company_vat_id( $company_id ) : '';
}

/** Resolve the canonical VAT ID applicable to a connected WordPress customer. */
function contactpanda_get_user_vat_id( $user_id ) {
	return class_exists( 'ContactPanda_Plugin' ) ? ContactPanda_Plugin::get_user_vat_id( $user_id ) : '';
}

/**
 * Sanitize a CSS color shared by every ContactPanda family member.
 *
 * WordPress' sanitize_hex_color() deliberately rejects transparent colors.
 * The shared admin picker also supports RGB/RGBA, so paid extensions use this
 * helper instead of implementing subtly different validators.
 */
function contactpanda_sanitize_css_color( $value, $fallback = '' ) {
	$value = trim( (string) $value );
	$hex   = sanitize_hex_color( $value );
	if ( $hex ) {
		return strtolower( $hex );
	}
	if ( ! preg_match( '/^rgba?\(\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)(?:\s*,\s*(-?(?:\d+(?:\.\d+)?|\.\d+)))?\s*\)$/i', $value, $matches ) ) {
		return $fallback;
	}
	$red   = min( 255, max( 0, (int) round( (float) $matches[1] ) ) );
	$green = min( 255, max( 0, (int) round( (float) $matches[2] ) ) );
	$blue  = min( 255, max( 0, (int) round( (float) $matches[3] ) ) );
	if ( ! isset( $matches[4] ) || '' === $matches[4] ) {
		return sprintf( 'rgb(%d, %d, %d)', $red, $green, $blue );
	}
	$alpha = min( 1, max( 0, (float) $matches[4] ) );
	$alpha_value = rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' );
	return sprintf( 'rgba(%d, %d, %d, %s)', $red, $green, $blue, '' === $alpha_value ? '0' : $alpha_value );
}

require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-runtime.php';
ContactPanda_Runtime::begin_profile();
add_filter( 'debug_information', array( 'ContactPanda_Runtime', 'site_health_info' ) );

/** Load family classes only when WordPress actually invokes them. */
function contactpanda_autoload( $class ) {
	$map = array(
		'ContactPanda_Plugin' => 'class-contactpanda-plugin.php', 'ContactPanda_Modules' => 'class-contactpanda-modules.php',
		'ContactPanda_Company_Profile' => 'class-contactpanda-company-profile.php',
		'ContactPanda_System_Emails' => 'class-contactpanda-system-emails.php', 'ContactPanda_Capabilities' => 'class-contactpanda-capabilities.php',
		'ContactPanda_Schema' => 'class-contactpanda-schema.php', 'ContactPanda_Identity_Store' => 'class-contactpanda-identity-store.php',
		'ContactPanda_Newsletter_Schema' => 'class-contactpanda-newsletter-schema.php', 'ContactPanda_Store' => 'class-contactpanda-store.php',
		'ContactPanda_Trash' => 'class-contactpanda-trash.php', 'ContactPanda_Data_Tools' => 'class-contactpanda-data-tools.php',
		'ContactPanda_Custom_Fields' => 'class-contactpanda-custom-fields.php', 'ContactPanda_Events' => 'class-contactpanda-events.php',
		'ContactPanda_Registration' => 'class-contactpanda-registration.php', 'ContactPanda_Identity' => 'class-contactpanda-identity.php',
		'ContactPanda_Customer_Portal' => 'class-contactpanda-customer-portal.php', 'ContactPanda_Basic_Newsletters' => 'class-contactpanda-newsletters.php',
		'ContactPanda_Basic_Newsletter_Campaigns' => 'class-contactpanda-newsletter-campaigns.php', 'ContactPanda_Basic_Segments' => 'class-contactpanda-segments.php',
		'ContactPanda_MCP' => 'class-contactpanda-mcp.php', 'ContactPanda_My_Account' => 'class-contactpanda-my-account.php',
	);
	if ( isset( $map[ $class ] ) ) require_once CONTACTPANDA_PATH . 'includes/' . $map[ $class ];
}
spl_autoload_register( 'contactpanda_autoload' );

function contactpanda_boot_context() {
	// Mail framing and entitlement state can affect any request. All other large
	// administration classes stay unloaded on ordinary front-end page views.
	ContactPanda_System_Emails::boot();
	if ( ContactPanda_Runtime::is_backoffice() ) {
		ContactPanda_Plugin::boot();
		ContactPanda_Capabilities::boot();
		ContactPanda_Trash::boot();
		ContactPanda_Data_Tools::boot();
		ContactPanda_Registration::boot();
		if ( ContactPanda_Runtime::needs_module( array( 'contactpanda-settings' ), array( 'contactpanda_save_company_profile', 'contactpanda_import_woocommerce_company_profile' ) ) ) ContactPanda_Company_Profile::boot();
		if ( ContactPanda_Runtime::needs_module( array( 'contactpanda-settings', 'contactpanda-people', 'contactpanda-firmen' ), array( 'contactpanda_save_custom_', 'contactpanda_delete_custom_', 'contactpanda_export_companies' ) ) ) ContactPanda_Custom_Fields::boot();
		if ( ContactPanda_Runtime::needs_module( array( 'contactpanda-settings' ), array( 'contactpanda_portal_' ) ) ) ContactPanda_Customer_Portal::boot();
		if ( ContactPanda_Runtime::needs_module( array( 'contactpanda-settings' ), array( 'contactpanda_mcp' ) ) ) ContactPanda_MCP::boot();
		return;
	}

	// Public callbacks are registered without parsing their large classes. PHP's
	// autoloader resolves the implementation only if the feature is used.
	add_shortcode( 'contactpanda_registration', array( 'ContactPanda_Registration', 'shortcode' ) );
	add_shortcode( 'contactpanda_newsletter_signup', array( 'ContactPanda_Registration', 'newsletter_shortcode' ) );
	add_shortcode( 'contactpanda_customer_portal', array( 'ContactPanda_Customer_Portal', 'shortcode' ) );
	add_action( 'admin_post_nopriv_contactpanda_register', array( 'ContactPanda_Registration', 'handle_registration' ) );
	add_action( 'admin_post_nopriv_contactpanda_newsletter_signup', array( 'ContactPanda_Registration', 'handle_newsletter_signup' ) );
	add_filter( 'register_url', array( 'ContactPanda_Registration', 'registration_url' ) );
	add_filter( 'authenticate', array( 'ContactPanda_Registration', 'guard_login' ), 40, 3 );
	add_action( 'panda_seasons_contact_registered', array( 'ContactPanda_Plugin', 'handle_extension_contact_registered' ), 10, 3 );
	if ( isset( $_GET['contactpanda_registration'] ) ) add_action( 'template_redirect', array( 'ContactPanda_Registration', 'render_virtual_registration' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public route selector.
	if ( isset( $_GET['contactpanda_portal'] ) ) add_action( 'template_redirect', array( 'ContactPanda_Customer_Portal', 'render_virtual_portal' ), 20 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public route selector.
	if ( isset( $_GET['cp_contact'], $_GET['cp_token'] ) ) add_action( 'template_redirect', array( 'ContactPanda_Customer_Portal', 'confirm_email_change' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed public route.
}
add_action( 'plugins_loaded', 'contactpanda_boot_context', 29 );

/** Register the two public dynamic blocks without loading their renderers. */
function contactpanda_register_public_blocks() {
	if ( ContactPanda_Runtime::is_backoffice() ) return;
	wp_register_script( 'contactpanda-newsletter-signup-block', CONTACTPANDA_URL . 'assets/contactpanda-newsletter-signup-block.js', array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), CONTACTPANDA_ASSET_VERSION, true );
	register_block_type( 'contactpanda/newsletter-signup', array( 'api_version' => 2, 'editor_script' => 'contactpanda-newsletter-signup-block', 'render_callback' => array( 'ContactPanda_Registration', 'newsletter_block' ), 'attributes' => array( 'layout' => array( 'type' => 'string', 'default' => 'one-line' ) ) ) );
	wp_register_script( 'contactpanda-portal-block', CONTACTPANDA_URL . 'assets/contactpanda-portal-block.js', array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-server-side-render' ), CONTACTPANDA_ASSET_VERSION, true );
	register_block_type( 'contactpanda/customer-portal', array( 'api_version' => 2, 'editor_script' => 'contactpanda-portal-block', 'render_callback' => array( 'ContactPanda_Customer_Portal', 'block' ), 'attributes' => array( 'defaultTab' => array( 'type' => 'string', 'default' => 'profile' ) ) ) );
}
add_action( 'init', 'contactpanda_register_public_blocks', 9 );
function contactpanda_boot_events_module() {
	if ( apply_filters( 'contactpanda_events_enabled', false ) ) ContactPanda_Events::boot();
}
add_action( 'plugins_loaded', 'contactpanda_boot_events_module', 30 );
add_action( 'admin_init', array( 'ContactPanda_Store', 'migrate_legacy_vat_ids' ), 25 );

/**
 * The reduced newsletter is part of free ContactPanda. Paid capabilities are
 * checked at the individual action or editor control that requires them.
 */
function contactpanda_boot_newsletter_module() {
	if ( ! ContactPanda_Runtime::is_backoffice() ) return;
	$enabled = true;
	$existing_newsletter = ContactPanda_Modules::get( 'newsletter' );
	$existing_segments   = ContactPanda_Modules::get( 'segments' );
	ContactPanda_Modules::register(
		'newsletter',
		array(
			'name'    => __( 'Newsletter', 'contactpanda' ),
			'package' => $existing_newsletter ? $existing_newsletter['package'] : 'free-core',
			'version' => CONTACTPANDA_VERSION,
			'enabled' => $enabled,
		)
	);
	ContactPanda_Modules::register(
		'segments',
		array(
			'name'    => __( 'Saved searches', 'contactpanda' ),
			'package' => $existing_segments ? $existing_segments['package'] : 'free-core',
			'version' => CONTACTPANDA_VERSION,
			'enabled' => $enabled,
		)
	);
	if ( ! $enabled ) {
		return;
	}
	$result = ContactPanda_Newsletter_Schema::maybe_upgrade();
	if ( is_wp_error( $result ) ) {
		add_action(
			'admin_notices',
			static function () use ( $result ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
			}
		);
		return;
	}
	ContactPanda_Basic_Segments::boot();
	if ( ! class_exists( 'ContactPanda_Segments', false ) ) {
		class_alias( 'ContactPanda_Basic_Segments', 'ContactPanda_Segments' );
	}
	if ( ! ContactPanda_Store::standalone() ) {
		return;
	}
	ContactPanda_Basic_Newsletters::boot();
	ContactPanda_Basic_Newsletter_Campaigns::boot();
	// Preserve the established public class names for current extensions while
	// avoiding redeclaration when an older Plus version already supplies them.
	if ( ! class_exists( 'ContactPanda_Newsletters', false ) ) {
		class_alias( 'ContactPanda_Basic_Newsletters', 'ContactPanda_Newsletters' );
	}
	if ( ! class_exists( 'ContactPanda_Newsletter_Campaigns', false ) ) {
		class_alias( 'ContactPanda_Basic_Newsletter_Campaigns', 'ContactPanda_Newsletter_Campaigns' );
	}
}
add_action( 'plugins_loaded', 'contactpanda_boot_newsletter_module', 45 );

/** Load the shared admin controls on every ContactPanda family screen. */
function contactpanda_admin_ui_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'contactpanda' ) ) {
		return;
	}
	$theme_colors = array( '#4253b5', '#39495a', '#ffffff', '#f1f4f7' );
	if ( class_exists( 'ContactPanda_Basic_Newsletters' ) && is_callable( array( 'ContactPanda_Basic_Newsletters', 'theme_colors' ) ) ) {
		$theme_colors = ContactPanda_Basic_Newsletters::theme_colors();
	}
	wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
	wp_enqueue_style( 'panda-editor-ui', CONTACTPANDA_URL . 'assets/panda-editor-ui.css', array( 'contactpanda' ), CONTACTPANDA_ASSET_VERSION );
	wp_enqueue_media();
	wp_enqueue_script( 'contactpanda-admin-ui', CONTACTPANDA_URL . 'assets/contactpanda-admin-ui.js', array( 'wp-api-fetch' ), CONTACTPANDA_ASSET_VERSION, true );
	wp_enqueue_script( 'panda-editor-ui', CONTACTPANDA_URL . 'assets/panda-editor-ui.js', array( 'contactpanda-admin-ui' ), CONTACTPANDA_ASSET_VERSION, true );
	wp_localize_script(
		'contactpanda-admin-ui',
		'CONTACTPANDA_ADMIN_UI',
		array(
			'themeColors' => array_values( array_unique( array_filter( array_map( 'sanitize_hex_color', (array) $theme_colors ) ) ) ),
			'i18n'        => array(
				'chooseColor'  => __( 'Select color', 'contactpanda' ),
				'colorValue'   => __( 'Color value', 'contactpanda' ),
				'themeColors'  => __( 'Theme and template colors', 'contactpanda' ),
				'invalidColor' => __( 'Enter a valid HEX, RGB, or RGBA color.', 'contactpanda' ),
				'chooseImage'  => __( 'Select image', 'contactpanda' ),
				'useImage'     => __( 'Use image', 'contactpanda' ),
				'dropImage'    => __( 'Click or drop an image here', 'contactpanda' ),
				'changeImage'  => __( 'Change image', 'contactpanda' ),
				'removeImage'  => __( 'Remove image', 'contactpanda' ),
				'uploading'    => __( 'Uploading image…', 'contactpanda' ),
				'uploadFailed' => __( 'The image could not be uploaded.', 'contactpanda' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'contactpanda_admin_ui_assets', 5 );

function contactpanda_newsletter_admin_assets( $hook ) {
	if ( in_array( $hook, array( 'contactpanda_page_contactpanda-campaigns', 'contactpanda_page_contactpanda-settings' ), true ) ) {
		wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
	}
	if ( 'contactpanda_page_contactpanda-settings' === $hook ) {
	}
	if ( 'contactpanda_page_contactpanda-campaigns' === $hook ) {
		wp_enqueue_style( 'contactpanda-newsletter-campaigns', CONTACTPANDA_URL . 'assets/contactpanda-newsletter-campaigns.css', array( 'contactpanda' ), CONTACTPANDA_ASSET_VERSION );
		wp_enqueue_script( 'contactpanda-campaigns', CONTACTPANDA_URL . 'assets/contactpanda-campaigns.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-campaigns', 'CONTACTPANDA_CAMPAIGNS', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contactpanda_newsletter_editor' ), 'noOptions' => __( 'No matching entries found.', 'contactpanda' ), 'noRecipients' => __( 'No eligible contacts found.', 'contactpanda' ), 'recipientSearchLoading' => __( 'Checking recipients…', 'contactpanda' ), 'recipientSearchSummary' => __( '%1$d contacts found; %2$d eligible; %3$d with documented marketing consent; %4$d without documented consent; %5$d permanently excluded.', 'contactpanda' ), 'manualRecipientSummary' => __( '%d people selected. Protected opt-outs and invalid addresses will still be excluded before sending.', 'contactpanda' ), 'recipientSearchTruncated' => __( 'Only the first 200 eligible matches are shown. Refine the search to see further contacts.', 'contactpanda' ), 'marketingConsent' => __( 'Marketing consent', 'contactpanda' ), 'withoutMarketingConsent' => __( 'No documented marketing consent', 'contactpanda' ), 'campaignCreating' => __( 'Creating campaign…', 'contactpanda' ), 'recipientSearchFailed' => __( 'The recipients could not be checked.', 'contactpanda' ), 'campaignCreateFailed' => __( 'The campaign could not be created.', 'contactpanda' ), 'previewFailed' => __( 'The preview could not be created.', 'contactpanda' ), 'testFailed' => __( 'The test email could not be sent.', 'contactpanda' ), 'sendNow' => __( 'Send now', 'contactpanda' ), 'scheduleDeliveryEmpty' => __( 'Schedule delivery', 'contactpanda' ), 'scheduleDelivery' => __( 'Schedule delivery for %1$s at %2$s', 'contactpanda' ), 'confirmSendNow' => __( 'Send this mailing to %d eligible recipients now?', 'contactpanda' ), 'confirmSchedule' => __( 'Schedule this mailing for %1$s? %2$d eligible recipients will be prepared.', 'contactpanda' ), 'overrideRequired' => __( 'Select a reason and confirm the administrative exception.', 'contactpanda' ), 'pollInterval' => 12000 ) );
	}
	if ( 'contactpanda_page_contactpanda-settings' === $hook ) {
		wp_enqueue_script( 'contactpanda-segments', CONTACTPANDA_URL . 'assets/contactpanda-segments.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-segments', 'CONTACTPANDA_SEGMENTS', ContactPanda_Basic_Segments::editor_config() );
	}
	if ( in_array( $hook, array( 'contactpanda_page_contactpanda-people', 'contactpanda_page_contactpanda-campaigns' ), true ) ) {
		wp_enqueue_script( 'contactpanda-inline-search', CONTACTPANDA_URL . 'assets/contactpanda-inline-search.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-inline-search', 'CONTACTPANDA_INLINE_SEARCH', ContactPanda_Basic_Segments::inline_config( 'contactpanda_page_contactpanda-campaigns' === $hook ) );
	}
}
add_action( 'admin_enqueue_scripts', 'contactpanda_newsletter_admin_assets', 20 );

register_activation_hook( __FILE__, array( 'ContactPanda_Registration', 'activate' ) );
