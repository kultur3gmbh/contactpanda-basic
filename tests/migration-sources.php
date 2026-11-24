<?php

$root = dirname( __DIR__ );
$registration = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-registration.php' );
$data_tools = file_get_contents( $root . '/contactpanda/includes/class-contactpanda-data-tools.php' );

$checks = array(
	'dedicated migration menu' => "'contactpanda-migration'",
	'dynamic source registry' => 'public static function migration_sources()',
	'extension source filter' => "apply_filters( 'contactpanda_migration_sources'",
	'source-specific renderer' => "do_action( 'contactpanda_render_migration_source_' . \$source",
	'FluentCRM preview handler' => 'handle_migrate_fluentcrm()',
	'stable FluentCRM source identifier' => "'fluentcrm_subscriber_id' => \$source_id",
	'non-destructive status protection' => "array( 'unsubscribed', 'bounced', 'spam' )",
	'Newsletter Plugin AJAX migration' => "wp_ajax_contactpanda_migrate_newsletter_plugin",
	'Newsletter Plugin live progress' => 'contactpanda-live-migration-form',
);

foreach ( $checks as $label => $needle ) {
	$source = in_array( $label, array( 'dedicated migration menu' ), true ) ? $data_tools : $registration;
	if ( false === strpos( $source, $needle ) ) {
		fwrite( STDERR, "Migration source check failed: {$label}.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $data_tools, "! empty( \$source['installed'] )" ) ) {
	fwrite( STDERR, "Migration tabs must hide unavailable source systems.\n" );
	exit( 1 );
}

echo "Migration source checks passed.\n";

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

class Migration_Response extends RuntimeException {}
class ContactPanda_Store {
	public static function ready() { return true; }
}
class Newsletter_Migration_Source_Fixture {
	public $prefix = 'fixture_';
	public function prepare( $query, ...$args ) { return $query; }
	public function get_var( $query ) { return null; }
	public function esc_like( $value ) { return $value; }
	public function get_col( $query, $column ) { throw new RuntimeException( 'Subscriber reads must never run for the template import.' ); }
}
function __($text, $domain) { return $text; }
function get_current_user_id() { return 1; }
function current_user_can( $capability ) { return 'manage_options' === $capability; }
function check_admin_referer( $action ) { if ( 'contactpanda_migrate_newsletter_plugin' !== $action ) throw new RuntimeException( 'Unexpected nonce action.' ); }
function wp_unslash( $value ) { return $value; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function get_transient( $key ) { return $GLOBALS['migration_preview'][$key] ?? false; }
function set_transient( $key, $value, $expiry ) { $GLOBALS['migration_preview'][$key] = $value; }
function delete_transient( $key ) { unset( $GLOBALS['migration_preview'][$key] ); }
function update_option( $key, $value, $autoload ) { $GLOBALS['migration_reports'][$key] = $value; }
function apply_filters( $filter, $value, ...$args ) {
	if ( 'contactpanda_newsletter_plugin_templates_available' === $filter ) return true;
	if ( 'contactpanda_newsletter_plugin_templates_import' === $filter ) {
		$GLOBALS['import_calls'][] = $args;
		return array( 'templates' => 1, 'mailings' => 1 );
	}
	return $value;
}
function current_time( $format ) { return '2026-01-01 00:00:00'; }
function admin_url( $page ) { return $page; }
function add_query_arg( $key, $value, $url ) { return "$url&$key=$value"; }
function wp_doing_ajax() { return true; }
function wp_send_json_success( $details ) { throw new Migration_Response( $details['message'], 200 ); }
function wp_send_json_error( $details, $code ) { throw new Migration_Response( $details['message'], $code ); }

require $root . '/contactpanda/includes/class-contactpanda-registration.php';
$wpdb = new Newsletter_Migration_Source_Fixture();
$import_calls = array();
$migration_reports = array();
function test_newsletter_scope( $scope, $mode, $expected_status ) {
	$_POST = array( 'newsletter_scope' => $scope, 'mode' => $mode );
	try {
		ContactPanda_Registration::handle_migrate_newsletter_plugin();
	} catch ( Migration_Response $result ) {
		if ( $expected_status === $result->getCode() ) return;
		throw $result;
	}
	throw new RuntimeException( 'The migration handler did not send a response.' );
}
test_newsletter_scope( 'templates', 'run', 400 );
test_newsletter_scope( 'templates', 'preview', 200 );
test_newsletter_scope( 'people', 'run', 400 );
test_newsletter_scope( 'templates', 'run', 200 );
test_newsletter_scope( 'templates', 'run', 400 );
if ( array( array( true, '' ), array( false, '' ) ) !== $import_calls
	|| array( 'contactpanda_last_newsletter_plugin_migration_report_templates' ) !== array_keys( $migration_reports ) ) {
	throw new RuntimeException( 'Import scope or confirmation isolation failed.' );
}
echo "Newsletter import scopes use independent previews and confirmation.\n";
