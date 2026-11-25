<?php
/**
 * Standard newsletter signup with a fixture WordPress environment.
 */

define( 'ABSPATH', __DIR__ );
define( 'CONTACTPANDA_URL', 'https://example.test/wp-content/plugins/contactpanda/' );
define( 'CONTACTPANDA_ASSET_VERSION', 'fixture' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

class Newsletter_Fixture_Redirect extends RuntimeException {}

class ContactPanda_Store {
	public static $standalone = true;
	public static $contact;
	public static $consents = array();

	public static function standalone() { return self::$standalone; }
	public static function contact_by_email( $email ) { return self::$contact; }
	public static function create_contact( $data ) {
		self::$contact = $data + array( 'id' => 42 );
		return 42;
	}
	public static function update_contact( $id, $data ) {
		if ( $id !== self::$contact['id'] ) return false;
		self::$contact = array_replace_recursive( self::$contact, $data );
		return true;
	}
	public static function record_consent( ...$args ) { self::$consents[] = $args; }
	public static function add_activity( ...$args ) {}
}

class ContactPanda_Spam_Protection {
	public static function render_fields( $form ) { return ''; }
	public static function verify( $form, $post ) { return array( 'status' => 'allow' ); }
}

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}

function __( $text, $domain ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_email( $value ) { return trim( $value ); }
function is_email( $value ) { return filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function sanitize_html_class( $value ) { return sanitize_key( $value ); }
function sanitize_url( $value ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_html__( $text, $domain ) { return esc_html( $text ); }
function esc_attr__( $text, $domain ) { return esc_attr( $text ); }
function esc_url( $value ) { return esc_attr( $value ); }
function esc_url_raw( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function wp_parse_args( $args, $defaults ) { return array_replace( $defaults, $args ); }
function get_option( $name, $default = false ) { return $GLOBALS['fixture_options'][ $name ] ?? $default; }
function get_permalink( $id ) { return 7 === $id ? 'https://example.test/privacy/' : false; }
function get_privacy_policy_url() { return 'https://example.test/default-privacy/'; }
function get_bloginfo( $property ) { return 'Fixture Newsletter'; }
function get_template() { return 'fixture'; }
function wp_specialchars_decode( $value, $flags ) { return html_entity_decode( $value, $flags ); }
function current_user_can( $cap ) { return false; }
function wp_enqueue_style( ...$args ) {}
function wp_enqueue_script( ...$args ) {}
function wp_print_styles( ...$args ) {}
function is_ssl() { return true; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function home_url( $path ) { return 'https://example.test' . $path; }
function admin_url( $path ) { return home_url( '/wp-admin/' . $path ); }
function shortcode_atts( $defaults, $args, $name ) { return wp_parse_args( $args, $defaults ); }
function apply_filters( $filter, $value, ...$args ) {
	return 'contactpanda_newsletter_signup_html' === $filter ? $GLOBALS['fixture_replacement'] : $value;
}
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="fixture">'; }
function wp_verify_nonce( $value, $action ) { return 'fixture' === $value; }
function wp_validate_redirect( $url, $fallback ) { return $url ?: $fallback; }
function wp_generate_password( ...$args ) { return 'fixture-notice'; }
function wp_safe_redirect( $url ) { throw new Newsletter_Fixture_Redirect( $url ); }
function wpautop( $text ) { return '<p>' . $text . '</p>'; }
function add_query_arg( $key, $value, $url = null ) {
	return is_array( $key ) ? $value . '?' . http_build_query( $key ) : $url . '?' . http_build_query( array( $key => $value ) );
}
function do_action( $action, ...$args ) {
	if ( 'contactpanda_newsletter_confirmation_sent' === $action ) $GLOBALS['fixture_confirmation'] = $args[1];
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_time( $format ) { return 'Y-m-d' === $format ? '2026-01-01' : '2026-01-01 00:00:00'; }
function get_user_by( $key, $email ) { return false; }
function wp_mail( $email, $subject, $message, $headers ) { $GLOBALS['fixture_mail'] = $message; return true; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['fixture_transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['fixture_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['fixture_transients'][ $key ] ); }

$root = dirname( __DIR__ ) . '/contactpanda/includes/';
require $root . 'class-contactpanda-registration.php';
require $root . 'class-contactpanda-plugin.php';
$option = ContactPanda_Registration::OPTION;
$fixture_options = array( $option => array( 'privacy_page_id' => 7 ) );
$fixture_replacement = '';
$fixture_transients = array();

function newsletter_expect( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}

function newsletter_fixture_submit( $extra ) {
	$_POST = $extra + array( 'email' => 'contact@example.test', 'contactpanda_newsletter_nonce' => 'fixture' );
	try {
		ContactPanda_Registration::handle_newsletter_signup();
	} catch ( Newsletter_Fixture_Redirect $redirect ) {
		$notice = get_transient( 'contactpanda_registration_notice_fixture-notice' );
		newsletter_expect( is_array( $notice ), 'Signup notice must be present.' );
		return $notice['type'];
	}
	throw new RuntimeException( 'Expected redirect from signup.' );
}

$html = ContactPanda_Registration::newsletter_shortcode();
newsletter_expect( preg_match_all( '/type="checkbox"/', $html ) === 1 && strpos( $html, 'name="salutation"' ) === false, 'The standard form must have exactly one consent checkbox and no salutation.' );
newsletter_expect( strpos( $html, 'name="first_name"' ) !== false && strpos( $html, 'name="last_name"' ) !== false && strpos( $html, 'name="email" required' ) !== false, 'Default fields must include first and last names and require the email address.' );
newsletter_expect( strpos( $html, 'name="marketing_consent" value="1" required' ) !== false && strpos( $html, 'href="https://example.test/privacy/"' ) !== false && strpos( $html, 'from Fixture Newsletter' ) !== false, 'The consent must identify the sender and display a privacy link.' );
$fixture_options[$option]['newsletter_display'] = 'both';
ob_start();
ContactPanda_Registration::newsletter_overlay();
$overlay = ob_get_clean();
newsletter_expect( substr_count( $overlay, 'name="marketing_consent"' ) === 2 && strpos( $overlay, 'name="salutation"' ) === false, 'Popup and footer must share the simplified form.' );

$fixture_options[$option] = ContactPanda_Registration::sanitize_settings( array( 'settings_section' => 'newsletter', 'newsletter_enabled' => 1, 'newsletter_show_first_name' => '0', 'newsletter_show_last_name' => '0' ) );
$html = ContactPanda_Registration::newsletter_shortcode();
newsletter_expect( strpos( $html, 'name="first_name"' ) === false && strpos( $html, 'name="last_name"' ) === false && strpos( $html, 'name="email" required' ) !== false, 'The two name fields must be independently hideable.' );
foreach ( array( 'first_name' => 'last_name', 'last_name' => 'first_name' ) as $shown => $hidden ) {
	$fixture_options[$option]['newsletter_show_' . $shown] = 1;
	$name_markup = ContactPanda_Registration::newsletter_shortcode();
	newsletter_expect( strpos( $name_markup, 'name="' . $shown . '"' ) !== false && strpos( $name_markup, 'name="' . $hidden . '"' ) === false, 'Each name field must be configurable separately.' );
	$fixture_options[$option]['newsletter_show_' . $shown] = 0;
}
newsletter_expect( strpos( $html, 'href="https://example.test/default-privacy/"' ) !== false, 'Use the WordPress privacy page as the fallback.' );
$fixture_replacement = '<form>Fixture Plus form</form>';
newsletter_expect( $fixture_replacement === ContactPanda_Registration::newsletter_shortcode(), 'The separate Plus replacement must still render.' );
$fixture_replacement = '';
ContactPanda_Store::$standalone = false;
newsletter_expect( '' === ContactPanda_Registration::newsletter_shortcode(), 'The standalone signup must stay unavailable in Groundhogg mode.' );
ContactPanda_Store::$standalone = true;

ContactPanda_Store::$contact = array( 'id' => 42, 'email' => 'contact@example.test', 'first_name' => 'Sample', 'last_name' => 'Fixture', 'optin_status' => 'unconfirmed', 'meta' => array( 'anrede' => 'neutral' ) );
newsletter_expect( 'error' === newsletter_fixture_submit( array() ), 'A submission without consent must fail.' );
newsletter_expect( 'success' === newsletter_fixture_submit( array( 'marketing_consent' => '1', 'first_name' => 'Ignored', 'salutation' => 'mr' ) ), 'A newsletter signup must work without supplied names or salutation.' );
newsletter_expect( 'unconfirmed' === ContactPanda_Store::$contact['optin_status'] && ! ContactPanda_Store::$consents, 'The signup must wait for the email link before granting consent.' );
newsletter_expect( isset( $fixture_confirmation ) && strpos( $fixture_mail, 'Hello,' ) !== false, 'Send the confirmation with a greeting that also works without a name.' );

$_GET['contactpanda_newsletter_verify'] = $fixture_confirmation;
$verify = new ReflectionMethod( 'ContactPanda_Registration', 'handle_newsletter_verification' );
if ( PHP_VERSION_ID < 80100 ) $verify->setAccessible( true );
try {
	$verify->invoke( null );
} catch ( Newsletter_Fixture_Redirect $redirect ) {
	newsletter_expect( 'confirmed' === ContactPanda_Store::$contact['optin_status'], 'Only email confirmation makes the contact marketable.' );
	newsletter_expect( 'Sample' === ContactPanda_Store::$contact['first_name'] && 'Fixture' === ContactPanda_Store::$contact['last_name'] && 'neutral' === ContactPanda_Store::$contact['meta']['anrede'], 'Confirmation must preserve existing names and salutation.' );
	newsletter_expect( 'granted' === ContactPanda_Store::$consents[0][1] && 'newsletter_signup_double_opt_in' === ContactPanda_Store::$consents[0][2], 'Document the double opt-in consent.' );
}
newsletter_expect( 'success' === newsletter_fixture_submit( array( 'marketing_consent' => '1' ) ) && 'Sample' === ContactPanda_Store::$contact['first_name'], 'Repeat signup must preserve the existing profile.' );
ContactPanda_Store::$contact['optin_status'] = 'blocked';
newsletter_expect( is_wp_error( ContactPanda_Plugin::extension_confirm_marketing_optin( 'contact@example.test' ) ), 'An existing block must still prevent marketing activation.' );

echo "ContactPanda standard newsletter form and double opt-in checks passed.\n";
