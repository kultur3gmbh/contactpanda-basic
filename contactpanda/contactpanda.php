<?php
/**
 * Plugin Name: ContactPanda Basic
 * Description: Free contact and company management for WordPress with newsletters, WooCommerce, migrations, and optional Groundhogg compatibility.
 * Version: 0.9.1
 * Update URI: https://github.com/kultur3gmbh/contactpanda-basic
 * Author: Kultur3 GmbH
 * Text Domain: contactpanda
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.4
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTPANDA_VERSION', '0.9.1' );
define( 'CONTACTPANDA_ASSET_VERSION', '0.9.1-20261031-1' );
define( 'CONTACTPANDA_EXTENSION_API_VERSION', '1.0' );
define( 'CONTACTPANDA_FILE', __FILE__ );
define( 'CONTACTPANDA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONTACTPANDA_URL', plugin_dir_url( __FILE__ ) );

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

require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-plugin.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-modules.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-license.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-company-profile.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-system-emails.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-capabilities.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-schema.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-identity-store.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-newsletter-schema.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-store.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-trash.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-data-tools.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-custom-fields.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-events.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-github-updater.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-licensed-updater.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-registration.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-identity.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-customer-portal.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-my-account.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-newsletters.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-newsletter-campaigns.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-segments.php';
require_once CONTACTPANDA_PATH . 'includes/class-contactpanda-mcp.php';

add_action(
	'plugins_loaded',
	static function () {
		new ContactPanda_GitHub_Updater(
			array(
				'slug'       => 'contactpanda',
				'name'       => 'ContactPanda Basic',
				'repo'       => 'contactpanda-basic',
				'zip_prefix' => 'contactpanda-basic',
				'plugin'     => plugin_basename( CONTACTPANDA_FILE ),
				'version'    => CONTACTPANDA_VERSION,
				'enabled'    => 'CONTACTPANDA_GITHUB_UPDATES',
			)
		);
	},
	20
);

/**
 * Register every active paid family member through one shared update client.
 *
 * Older extension releases may still register themselves. The updater registry
 * deduplicates those calls, which makes rolling family updates safe regardless
 * of plugin load order.
 */
function contactpanda_register_family_updaters() {
	$products = array(
		array( 'constant' => 'CONTACTPANDA_PLUS_FILE', 'version' => 'CONTACTPANDA_PLUS_VERSION', 'product' => 'contactpanda-plus', 'entitlement' => 'plus', 'slug' => 'contactpanda-plus', 'name' => 'ContactPanda Plus' ),
		array( 'constant' => 'CONTACTPANDA_BUSINESS_FILE', 'version' => 'CONTACTPANDA_BUSINESS_VERSION', 'product' => 'contactpanda-business', 'entitlement' => 'business', 'slug' => 'contactpanda-business', 'name' => 'ContactPanda Business' ),
	);
	foreach ( $products as $product ) {
		if ( ! defined( $product['constant'] ) || ! defined( $product['version'] ) ) {
			continue;
		}
		new ContactPanda_Licensed_Updater(
			array(
				'product'     => $product['product'],
				'entitlement' => $product['entitlement'],
				'plugin'      => plugin_basename( constant( $product['constant'] ) ),
				'slug'        => $product['slug'],
				'name'        => $product['name'],
				'version'     => constant( $product['version'] ),
				'channel'     => defined( 'CONTACTPANDA_UPDATE_CHANNEL' ) ? CONTACTPANDA_UPDATE_CHANNEL : 'stable',
			)
		);
	}
}
add_action( 'plugins_loaded', 'contactpanda_register_family_updaters', 22 );

add_action( 'plugins_loaded', array( 'ContactPanda_Plugin', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_License', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Company_Profile', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_System_Emails', 'boot' ), 31 );
add_action( 'plugins_loaded', array( 'ContactPanda_Capabilities', 'boot' ), 29 );
add_action( 'plugins_loaded', array( 'ContactPanda_Trash', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Data_Tools', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Custom_Fields', 'boot' ), 30 );
function contactpanda_boot_events_module() {
	if ( apply_filters( 'contactpanda_events_enabled', false ) ) ContactPanda_Events::boot();
}
add_action( 'plugins_loaded', 'contactpanda_boot_events_module', 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Registration', 'boot' ), 30 );
add_action( 'plugins_loaded', array( 'ContactPanda_Customer_Portal', 'boot' ), 31 );
add_action( 'plugins_loaded', array( 'ContactPanda_My_Account', 'boot' ), 31 );
add_action( 'plugins_loaded', array( 'ContactPanda_MCP', 'boot' ), 32 );

/**
 * The reduced newsletter is part of free ContactPanda. Paid capabilities are
 * checked at the individual action or editor control that requires them.
 */
function contactpanda_boot_newsletter_module() {
	$enabled = ContactPanda_License::newsletter_entitled();
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
	wp_enqueue_media();
	wp_enqueue_script( 'contactpanda-admin-ui', CONTACTPANDA_URL . 'assets/contactpanda-admin-ui.js', array( 'wp-api-fetch' ), CONTACTPANDA_ASSET_VERSION, true );
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
	if ( ! ContactPanda_License::newsletter_entitled() ) {
		return;
	}
	if ( in_array( $hook, array( 'contactpanda_page_contactpanda-campaigns', 'contactpanda_page_contactpanda-settings' ), true ) ) {
		wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
	}
	if ( 'contactpanda_page_contactpanda-settings' === $hook ) {
		wp_enqueue_script( 'contactpanda-system-emails', CONTACTPANDA_URL . 'assets/contactpanda-system-emails.js', array( 'contactpanda-admin-ui' ), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-system-emails', 'CONTACTPANDA_SYSTEM_EMAILS', array( 'i18n' => array(
			'header' => __( 'Header', 'contactpanda' ), 'footer' => __( 'Footer', 'contactpanda' ), 'image' => __( 'Image', 'contactpanda' ), 'text' => __( 'Text', 'contactpanda' ), 'separator' => __( 'Separator', 'contactpanda' ),
			'addElement' => __( 'Add element', 'contactpanda' ), 'remove' => __( 'Remove', 'contactpanda' ), 'moveUp' => __( 'Move up', 'contactpanda' ), 'moveDown' => __( 'Move down', 'contactpanda' ), 'content' => __( 'Content', 'contactpanda' ),
		) ) );
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

function contactpanda_deactivate_license_schedule() {
	wp_clear_scheduled_hook( ContactPanda_License::CHECK_HOOK );
}
register_deactivation_hook( __FILE__, 'contactpanda_deactivate_license_schedule' );
