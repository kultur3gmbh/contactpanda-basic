<?php

define( 'ABSPATH', __DIR__ );
define( 'TRIBE_EVENTS_FILE', __FILE__ );
define( 'EVENT_TICKETS_VERSION', '1.0' );
define( 'MINUTE_IN_SECONDS', 60 );

$contactpanda_test_option = '';

function tribe_attendees() { return new stdClass(); }
function get_option( $key, $default = false ) { global $contactpanda_test_option; return 'contactpanda_event_source' === $key ? $contactpanda_test_option : $default; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }
function get_user_by() { return false; }
function get_user_meta() { return ''; }
function get_bloginfo() { return ''; }

final class ContactPanda_Events_Test_DB {
	public $prefix = 'fixture_';
	public function esc_like( $value ) { return $value; }
	public function prepare( $query, $value ) { return $value; }
	public function get_var( $table ) {
		return in_array( $table, array( 'fixture_amelia_users', 'fixture_amelia_customer_bookings', 'fixture_amelia_appointments' ), true ) ? $table : null;
	}
}

$wpdb = new ContactPanda_Events_Test_DB();
require_once dirname( __DIR__ ) . '/contactpanda/includes/class-contactpanda-events.php';

function verify_event( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
}

verify_event( array( 'tec', 'amelia' ) === array_keys( ContactPanda_Events::sources() ), 'Detect both supported event sources.' );
verify_event( '' === ContactPanda_Events::source(), 'Require a choice when both event sources are active.' );
verify_event( ! ContactPanda_Events::available(), 'Keep event output disabled until a source is selected.' );
$contactpanda_test_option = 'amelia';
verify_event( 'amelia' === ContactPanda_Events::source(), 'Use the saved Amelia source.' );
verify_event( ContactPanda_Events::available(), 'Enable event output after a valid selection.' );
$contactpanda_test_option = 'unknown';
verify_event( '' === ContactPanda_Events::source(), 'Reject unavailable saved sources.' );

$identity_method = new ReflectionMethod( 'ContactPanda_Events', 'contact_identity_maps' );
$identity_method->setAccessible( true );
$identity = $identity_method->invoke(
	null,
	array(
		array(
			'id'      => 17,
			'email'   => 'primary@example.test',
			'user_id' => 0,
			'meta'    => array(
				'merged_email_aliases'         => array( 'former@example.test' ),
				'contactpanda_contact_channels' => array(
					array( 'type' => 'email', 'value' => 'tickets@example.test' ),
				),
			),
		),
	)
);
verify_event( array( 17 ) === $identity['emails']['primary@example.test'], 'Map the primary contact email to event attendees.' );
verify_event( array( 17 ) === $identity['emails']['former@example.test'], 'Map merged historical email aliases to event attendees.' );
verify_event( array( 17 ) === $identity['emails']['tickets@example.test'], 'Map additional contact-channel emails to event attendees.' );

echo "ContactPanda event source tests passed.\n";
