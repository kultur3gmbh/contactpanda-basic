<?php
/** Regression test for current Event Tickets installs without tribe_attendees(). */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TRIBE_EVENTS_FILE', __FILE__ );
define( 'TRIBE_TICKETS_FILE', __FILE__ );
define( 'MINUTE_IN_SECONDS', 60 );

function get_option( $key, $default = false ) { return $default; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
function wp_cache_get() { return false; }
function wp_cache_set() { return true; }

final class ContactPanda_Current_Events_Test_DB {
	public $prefix = 'fixture_';
	public function esc_like( $value ) { return $value; }
	public function prepare( $query, $value ) { return $value; }
	public function get_var() { return null; }
}

$wpdb = new ContactPanda_Current_Events_Test_DB();

require_once dirname( __DIR__ ) . '/contactpanda/includes/class-contactpanda-events.php';

if ( ! ContactPanda_Events::tec_available() ) {
	fwrite( STDERR, "The Events Calendar and current Event Tickets API were not detected.\n" );
	exit( 1 );
}

$sources = ContactPanda_Events::available_sources();
if ( array( 'tec' => 'The Events Calendar' ) !== $sources ) {
	fwrite( STDERR, "The current Event Tickets source was not exposed.\n" );
	exit( 1 );
}

echo "Current Event Tickets compatibility passed\n";
