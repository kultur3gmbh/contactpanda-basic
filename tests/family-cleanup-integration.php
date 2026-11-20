<?php
/** Non-destructive schema reuse and shared UI/policy regression checks. */
$wp_root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
$family_root = getenv( 'CONTACTPANDA_TEST_FAMILY_ROOT' );
if ( ! $wp_root || ! is_file( $wp_root . '/.panda-test-fixture' ) || ! $family_root ) {
	exit( "An explicitly disposable WordPress fixture and family sources are required.\n" );
}
define( 'WP_ADMIN', true );
require $wp_root . '/wp-load.php';
foreach ( array( 'schema', 'service-schema', 'service-access', 'access' ) as $name ) {
	require_once $family_root . '/business/contactpanda-business/includes/class-contactpanda-business-' . $name . '.php';
}
function cleanup_expect( $condition, $message ) {
	if ( ! $condition ) throw new RuntimeException( $message );
	echo 'PASS ' . $message . "\n";
}
$admin = (int) get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 1 ) )[0];
wp_set_current_user( $admin );
$schemas = array( 'ContactPanda_Schema', 'ContactPanda_Newsletter_Schema', 'ContactPanda_Plus_Schema', 'ContactPanda_Business_Schema', 'ContactPanda_Business_Service_Schema' );
$owned = array();
foreach ( $schemas as $schema ) {
	cleanup_expect( ! is_wp_error( $schema::install() ), $schema . ' initializes safely' );
	$tables = 'ContactPanda_Plus_Schema' === $schema ? $schema::owned_table_names() : $schema::table_names();
	cleanup_expect( ! array_intersect( $owned, $tables ), $schema . ' has no duplicate table ownership' );
	$owned = array_merge( $owned, array_values( $tables ) );
}
global $wpdb;
$snapshot = static function () use ( $wpdb, $owned ) {
	$out = array();
	foreach ( $owned as $table ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
		if ( $wpdb->last_error ) throw new RuntimeException( 'Owned table cannot be read.' );
		$rows = array_map( 'wp_json_encode', $rows ); sort( $rows );
		$out[ $table ] = hash( 'sha256', implode( "\n", $rows ) );
	}
	return $out;
};
$ids = array(); $user = 0;
try {
	$contact = ContactPanda_Store::create_contact( array( 'first_name' => 'Cleanup fixture', 'email' => 'cleanup-' . wp_generate_uuid4() . '@example.invalid', 'meta' => array( 'contactpanda_custom_biography' => '<p>Retained fixture biography</p>' ) ) );
	cleanup_expect( $contact > 0, 'Existing-format contact and biography seeded' );
	$ids[] = array( ContactPanda_Schema::table_names()['contacts'], $contact );
	$now = current_time( 'mysql' );
	foreach ( array(
		array( ContactPanda_Newsletter_Schema::table_names()['templates'], array( 'name' => 'Cleanup fixture', 'created_at' => $now, 'updated_at' => $now ) ),
		array( ContactPanda_Plus_Schema::owned_table_names()['forms'], array( 'name' => 'Cleanup fixture', 'created_at' => $now, 'updated_at' => $now ) ),
		array( ContactPanda_Business_Schema::table_names()['projects'], array( 'uuid' => wp_generate_uuid4(), 'name' => 'Cleanup fixture', 'created_by' => $admin, 'created_at' => $now, 'updated_at' => $now ) ),
	) as $seed ) {
		cleanup_expect( false !== $wpdb->insert( $seed[0], $seed[1] ), 'Existing-format domain record seeded' );
		$ids[] = array( $seed[0], $wpdb->insert_id );
	}
	$before = $snapshot();
	foreach ( $schemas as $schema ) cleanup_expect( ! is_wp_error( $schema::install() ), 'Repeated schema initialization succeeds' );
	cleanup_expect( $before === $snapshot(), 'All ' . count( $owned ) . ' owned tables retain every existing row byte-for-byte' );
	cleanup_expect( false === has_action( 'wp_ajax_contactpanda_plus_save_biography' ) && false !== has_action( 'wp_ajax_contactpanda_save_biography' ), 'Only the canonical Basic biography handler is registered' );
	cleanup_expect( ! method_exists( 'ContactPanda_Newsletter_Schema', 'newsletter_table_names' ), 'No old newsletter schema alias remains' );
	ob_start();
	Panda_Transfer_UI::render( array( 'product' => 'contactpanda', 'context' => 'import-contacts', 'fields' => array(), 'kind' => 'import' ), CONTACTPANDA_URL . 'assets/', CONTACTPANDA_VERSION, 'contactpanda' );
	$html = ob_get_clean();
	cleanup_expect( false !== strpos( $html, 'data-panda-transfer=' ) && false !== strpos( wp_scripts()->registered['panda-transfer-ui']->src, '/contactpanda/assets/' ), 'Transfer builder uses the Basic-owned script and preset namespace' );
	ContactPanda_Business_Access::boot();
	$user = wp_create_user( 'cleanup-' . wp_generate_uuid4(), wp_generate_password( 30 ), 'cleanup-policy-' . wp_generate_uuid4() . '@example.invalid' );
	cleanup_expect( ! is_wp_error( $user ), 'Isolated policy test user created' );
	wp_set_current_user( $user );
	cleanup_expect( ! ContactPanda_Business_Access::can( 'edit', 'project' ), 'User without permission remains denied' );
	wp_get_current_user()->add_cap( 'contactpanda_projects_edit' );
	cleanup_expect( ContactPanda_Business_Access::can( 'edit', 'project' ), 'Explicit project capability remains effective' );
	$deny = static function () { return false; };
	add_filter( 'contactpanda_business_service_access_decision', $deny );
	cleanup_expect( ! ContactPanda_Business_Access::can( 'edit', 'project' ), 'Shared policy denial cannot be bypassed by a local fallback' );
	remove_filter( 'contactpanda_business_service_access_decision', $deny );
} finally {
	wp_set_current_user( $admin );
	if ( $user && ! is_wp_error( $user ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user ); }
	foreach ( array_reverse( $ids ) as $row ) $wpdb->delete( $row[0], array( 'id' => $row[1] ) );
	if ( ! empty( $contact ) ) foreach ( array( 'contactmeta', 'contact_channels', 'external_identities' ) as $key ) $wpdb->delete( ContactPanda_Schema::table_names()[$key], array( 'contact_id' => $contact ) );
}
