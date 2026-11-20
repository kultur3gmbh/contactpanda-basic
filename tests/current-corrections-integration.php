<?php
/** Actual UI and mutations against disposable WordPress with synthetic records only. */
$root = getenv( 'CONTACTPANDA_TEST_WP_ROOT' );
if ( ! $root || ! is_file( $root . '/.panda-test-fixture' ) ) throw new RuntimeException( 'Disposable fixture required' );
define( 'WP_ADMIN', true ); define( 'DOING_AJAX', true ); define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $root . '/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/admin.php';
ContactPanda_Capabilities::ensure_default_capabilities(); wp_set_current_user( 0 ); wp_set_current_user( 1 ); switch_to_locale( 'en_US' );
function current_expect( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( $label ); echo 'PASS ' . $label . "\n"; }
function current_call( $method, array $args = array() ) { return ( new ReflectionMethod( 'ContactPanda_Plugin', $method ) )->invokeArgs( null, $args ); }
function current_render( $method, array $args = array() ) { ob_start(); current_call( $method, $args ); return ob_get_clean(); }
$exit = static function () { return static function () { throw new RuntimeException( 'fixture_ajax_exit' ); }; };
add_filter( 'wp_die_ajax_handler', $exit );
function current_ajax( $method, array $data ) {
 $_POST = $data; $_REQUEST = $data; ob_start();
 try { ContactPanda_Plugin::$method(); } catch ( RuntimeException $e ) { if ( 'fixture_ajax_exit' !== $e->getMessage() ) { ob_end_clean(); throw $e; } }
 return json_decode( ob_get_clean(), true );
}
$general = get_option( 'contactpanda_general_settings' ); $ids = array(); $get = $_GET; $post = $_POST; $request = $_REQUEST;
try {
 update_option( 'contactpanda_general_settings', array_merge( (array) $general, array( 'data_source' => 'standalone', 'external_news_enabled' => 0 ) ) );
 $suffix = wp_generate_uuid4();
 $id = ContactPanda_Store::create_contact( array( 'first_name' => 'UI Fixture', 'last_name' => $suffix, 'email' => 'primary-' . $suffix . '@example.invalid', 'meta' => array( 'primary_phone' => '+431234567890', 'contactpanda_contact_channels' => array(
  array( 'id' => 'fixture-secondary', 'type' => 'email', 'label' => 'work', 'value' => 'secondary-' . $suffix . '@example.invalid' ),
  array( 'id' => 'fixture-primary', 'type' => 'email', 'label' => 'private', 'value' => 'primary-' . $suffix . '@example.invalid' ),
  array( 'id' => 'fixture-phone-secondary', 'type' => 'phone', 'label' => 'work', 'value' => '+431234567891' ),
 ) ) ) );
 current_expect( ! is_wp_error( $id ), 'Synthetic person created' ); $ids[] = $id;
 $before = ContactPanda_Store::contact( $id );
 $channels = current_call( 'contact_channels', array( $before ) );
 $emails = array_values( array_filter( $channels, static function ( $row ) { return 'email' === $row['type']; } ) );
 $phones = array_values( array_filter( $channels, static function ( $row ) { return 'phone' === $row['type']; } ) );
 current_expect( ! empty( $emails[0]['is_primary'] ) && ! empty( $phones[0]['is_primary'] ), 'Primary email and phone display first' );
 current_expect( $before === ContactPanda_Store::contact( $id ), 'Display sorting does not write to the stored person' );
 $auth = array( 'contact_id' => $id, 'nonce' => wp_create_nonce( 'contactpanda_edit' ), 'channel_type' => 'email', 'channel_id' => 'fixture-secondary', 'channel_label' => 'work', 'value' => 'secondary-' . $suffix . '@example.invalid', 'is_primary' => '1' );
 // Use the actual capability-checked nonce contract, rather than assuming its name.
 $auth['nonce'] = wp_create_nonce( current_call( 'ajax_nonce_action' ) );
 $switched = current_ajax( 'save_contact_channel', $auth );
 current_expect( ! empty( $switched['success'] ) && ! empty( $switched['data']['is_primary'] ), 'Actual AJAX switches the primary email' );
 $after = ContactPanda_Store::contact( $id ); $channels = current_call( 'contact_channels', array( $after ) );
 $emails = array_values( array_filter( $channels, static function ( $row ) { return 'email' === $row['type']; } ) );
 current_expect( 'fixture-secondary' === $emails[0]['id'] && count( $emails ) === 2 && 1 === count( array_filter( $emails, static function ( $row ) { return $row['is_primary']; } ) ), 'Reload retains two emails with exactly one primary at the front' );
 $channel_html = current_render( 'render_contact_channels', array( $id, $after, true ) );
 current_expect( strpos( $channel_html, 'fixture-secondary' ) < strpos( $channel_html, 'fixture-primary' ) && strpos( $channel_html, 'button panda-button-compact contactpanda-channel-action' ) !== false, 'Rendered fields keep primary order and compact separate actions' );
 $address = array( 'address_1' => 'Fixture Street 1', 'address_2' => '', 'postcode' => '0000', 'city' => 'Fixture Town', 'state' => '', 'country' => 'AT' );
 $address_html = current_render( 'render_contact_address_form', array( $address, array( 'AT' => 'Austria', 'DE' => 'Germany' ), $id, true, true ) );
 current_expect( strpos( $address_html, 'contactpanda-cancel-address' ) !== false && strpos( $address_html, 'contactpanda-address-saved' ) !== false, 'Contact address has Cancel and a saved-summary alternative' );
 $saved = current_ajax( 'update_customer_address', array_merge( $address, array( 'contact_id' => $id, 'address_type' => 'contact', 'nonce' => $auth['nonce'] ) ) );
 current_expect( ! empty( $saved['success'] ) && $saved['data']['values'] === $address, 'Saved normalized address is returned for the cancellation baseline' );
 $copied = current_ajax( 'update_customer_address', array_merge( $address, array( 'address_1' => 'Fixture Street 2', 'contact_id' => $id, 'address_type' => 'contact', 'sync_to_woocommerce' => '1', 'nonce' => $auth['nonce'] ) ) );
 current_expect( empty( $copied['success'] ) && ! empty( $copied['data']['address_saved'] ) && 'Fixture Street 2' === $copied['data']['values']['address_1'], 'Partial address save exposes saved state when no WooCommerce account can receive a copy' );
 $_GET = array( 's' => $suffix );
 $table_html = current_render( 'render_people_index' );
 $doc = new DOMDocument(); @$doc->loadHTML( '<?xml encoding="UTF-8">' . $table_html ); $xpath = new DOMXPath( $doc );
 $row = $xpath->query( '//tr[contains(@class,"contactpanda-person-data-row")]' )->item( 0 );
 current_expect( $row && strpos( $row->lastChild->getAttribute( 'class' ), 'column-actions' ) !== false && strpos( $row->lastChild->previousSibling->getAttribute( 'class' ), 'column-marketing' ) !== false, 'Actual people row ends with status followed by record actions' );
 $installed = current_call( 'installed_panda_products' );
 current_expect( ! empty( $installed['plus'] ) && ! empty( $installed['business'] ), 'Installed products are detected even when inactive' );
 $offers = current_render( 'render_dashboard_upgrades' );
 current_expect( strpos( $offers, 'is-plus' ) === false && strpos( $offers, 'is-business' ) === false && strpos( $offers, 'PandaWoo Quick' ) !== false, 'Recommendations omit inactive installed products and include missing Panda products' );
 // Real menu entries and capabilities, including extension registrations.
 global $menu, $submenu, $admin_page_hooks, $_registered_pages, $_parent_pages;
 $menu = array(); $submenu = array(); $admin_page_hooks = array(); $_registered_pages = array(); $_parent_pages = array();
 do_action( 'admin_menu', '' );
 $areas = current_render( 'render_dashboard_areas' );
 current_expect( strpos( $areas, 'contactpanda-people' ) !== false && strpos( $areas, 'contactpanda-firmen' ) !== false && strpos( $areas, 'plus-email-delivery' ) === false, 'Dashboard area links come from real available menu entries' );
 $yes = static function () { return true; }; add_filter( 'contactpanda_license_entitled', $yes );
 if ( getenv( 'PANDA_TEST_LICENSED' ) === '1' ) current_expect( (bool) has_action( 'contactpanda_render_email_delivery_settings' ), 'Licensed Plus boots the real email delivery settings hook' );
 $cards = apply_filters( 'contactpanda_dashboard_area_cards', array() );
 current_expect( isset( $cards['plus-duplicates'] ) && isset( $cards['plus-email-delivery'] ) === (bool) has_action( 'contactpanda_render_email_delivery_settings' ), 'Licensed Plus tools extend the area overview without inventing unavailable settings' );
 remove_filter( 'contactpanda_license_entitled', $yes );
 $dashboard = current_render( 'render_dashboard' );
 current_expect( strpos( $dashboard, 'contactpanda-dashboard-grid' ) < strpos( $dashboard, 'contactpanda-dashboard-areas' ) && strpos( $dashboard, 'contactpanda-dashboard-areas' ) < strpos( $dashboard, 'contactpanda-dashboard-product-visual' ) && strpos( $dashboard, 'contactpanda-dashboard-product-visual' ) < strpos( $dashboard, 'contactpanda-dashboard-upgrades' ), 'Dashboard order is statistics, menu areas, operational content, then recommendations' );
 $readonly = current_render( 'render_contact_address_form', array( $address, array( 'AT' => 'Austria' ), $id, false, false ) );
 current_expect( strpos( $readonly, 'contactpanda-save-address' ) === false && strpos( $readonly, 'contactpanda-cancel-address' ) === false, 'Read-only address view cannot save or cancel edits' );
 update_option( 'contactpanda_general_settings', array_merge( (array) $general, array( 'data_source' => 'groundhogg' ) ) );
 $rejected = current_ajax( 'save_contact_channel', $auth );
 current_expect( empty( $rejected['success'] ) && $after['email'] === ContactPanda_Store::contact( $id )['email'], 'Groundhogg mode rejects writes and leaves the primary email unchanged' );
 if ( getenv( 'PANDA_UI_FIXTURE_DIR' ) ) {
  $directory = getenv( 'PANDA_UI_FIXTURE_DIR' ); if ( ! is_dir( $directory ) ) mkdir( $directory, 0700, true );
  file_put_contents( $directory . '/person.html', $channel_html . $address_html . $table_html );
  file_put_contents( $directory . '/dashboard.html', $dashboard );
 }
 echo "Current ContactPanda correction integration checks passed.\n";
} finally {
 update_option( 'contactpanda_general_settings', $general ); $_GET = $get; $_POST = $post; $_REQUEST = $request;
 global $wpdb; $tables = ContactPanda_Schema::table_names();
 foreach ( $ids as $id ) { foreach ( array( 'company_contacts', 'contact_channels', 'contactmeta', 'contact_tags', 'notes', 'activities' ) as $key ) if ( isset( $tables[$key] ) ) $wpdb->delete( $tables[$key], array( 'contact_id' => $id ) ); $wpdb->delete( $tables['contacts'], array( 'id' => $id ) ); }
 remove_filter( 'wp_die_ajax_handler', $exit ); restore_previous_locale();
}
