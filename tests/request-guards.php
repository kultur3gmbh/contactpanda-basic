<?php
/** Permission and forged-request regression tests; no site or customer data. */
define( 'ABSPATH', __DIR__ . '/' );
class RequestRejected extends RuntimeException {}
function current_user_can( $capability ) { return $GLOBALS['request_allowed']; }
function is_user_logged_in() { return true; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function wp_die( $message, ...$rest ) { throw new RequestRejected( 'permission' ); }
function wp_send_json_error( $data, $status = 400 ) { throw new RequestRejected( 'permission' ); }
function check_admin_referer( $action ) { throw new RequestRejected( 'nonce:' . $action ); }
function check_ajax_referer( $action, $key = false ) { throw new RequestRejected( 'nonce:' . $action ); }
foreach ( array( 'plugin', 'registration', 'data-tools', 'newsletters', 'newsletter-campaigns', 'segments', 'events', 'my-account', 'import-review' ) as $file ) require_once __DIR__ . '/../contactpanda/includes/class-contactpanda-' . $file . '.php';
$handlers = array(
 'ContactPanda_Plugin' => array( 'update_contact_field', 'save_contact_channel', 'delete_contact_channel', 'update_marketing_consent', 'bulk_marketing_consent', 'export_contacts', 'print_contacts', 'create_contact', 'create_company', 'manage_contact_tag', 'bulk_manage_contact_tags', 'preview_broadcast', 'preview_order', 'create_user_account', 'update_customer_address', 'validate_vat' ),
 'ContactPanda_Registration' => array( 'handle_migrate_wordpress_users', 'handle_migrate_newsletter_plugin', 'handle_migrate_fluentcrm', 'handle_import_woocommerce', 'handle_setup_standalone', 'handle_migrate_groundhogg', 'handle_reset_standalone' ),
 'ContactPanda_Data_Tools' => array( 'handle_csv_preview', 'handle_csv_import', 'handle_merge_preview', 'handle_merge_group', 'handle_merge_commit', 'handle_central_settings', 'handle_notice_settings' ),
 'ContactPanda_Basic_Newsletters' => array( 'save_template', 'template_action', 'preview_ajax', 'test_ajax', 'products_ajax' ),
 'ContactPanda_Basic_Newsletter_Campaigns' => array( 'save', 'campaign_action', 'campaign_group_create_ajax', 'statuses_ajax', 'customer_search_ajax', 'preview_ajax', 'test_ajax' ),
 'ContactPanda_Basic_Segments' => array( 'ajax_filter_count', 'ajax_save_inline_search' ),
 'ContactPanda_Events' => array( 'preview_event' ),
);
$_POST = array( 'ids' => array( '1 OR 1=1', array( 'unexpected' ) ), 'value' => '<script>fixture</script>', '_wpnonce' => 'forged' );
$_GET = array( 'id' => 1, 'command' => 'delete', '_wpnonce' => 'forged' );
$attempts = 0;
foreach ( $handlers as $class => $methods ) foreach ( $methods as $method ) foreach ( array( false, true ) as $allowed ) {
 $GLOBALS['request_allowed'] = $allowed;
 try { call_user_func( array( $class, $method ) ); throw new RuntimeException( $class . '::' . $method . ' accepted an unauthorized request.' ); }
 catch ( RequestRejected $error ) {
  $expected = $allowed ? 'nonce:' : 'permission';
  if ( 0 !== strpos( $error->getMessage(), $expected ) ) throw new RuntimeException( $class . '::' . $method . ': unexpected guard order.' );
 }
 ++$attempts;
}
$GLOBALS['request_allowed'] = true;
foreach ( array( 'change_password', 'save_addresses', 'save_addresses_ajax' ) as $method ) {
 try { call_user_func( array( 'ContactPanda_My_Account', $method ) ); throw new RuntimeException( 'Forged account request accepted.' ); }
 catch ( RequestRejected $error ) { if ( 0 !== strpos( $error->getMessage(), 'nonce:' ) ) throw $error; }
 ++$attempts;
}
echo 'Request guards: ' . $attempts . " unauthorized/forged entry-point attempts rejected before data access.\n";
