<?php
/** Offline tests for the protected licensed updater. */
define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
class WP_Error { private $code; private $message; public function __construct( $code, $message = '' ) { $this->code = $code; $this->message = $message; } public function get_error_code() { return $this->code; } public function get_error_message() { return $this->message; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $value, $domain = '' ) { return $value; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][ $hook ][] = array( $callback, $accepted_args ); }
function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $filter ) { $value = call_user_func_array( $filter[0], array_slice( array_merge( array( $value ), $args ), 0, $filter[1] ) ); } return $value; }
function add_action( $hook, $callback ) { $GLOBALS['actions'][ $hook ][] = $callback; }
function do_action( $hook ) { foreach ( $GLOBALS['actions'][ $hook ] ?? array() as $callback ) call_user_func( $callback ); }
function current_user_can() { return true; }
function sanitize_text_field( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return (string) $value; }
function wp_kses_post( $value ) { return (string) $value; }
function plugin_basename( $value ) { return ltrim( (string) $value, '/' ); }
function get_site_transient( $key ) { return $GLOBALS['cache'][ $key ] ?? false; }
function set_site_transient( $key, $value, $ttl ) { $GLOBALS['cache'][ $key ] = $value; }
function delete_site_transient( $key ) { unset( $GLOBALS['cache'][ $key ] ); $GLOBALS['deleted'][] = $key; }
function wp_delete_file( $file ) { if ( is_file( $file ) ) unlink( $file ); }
function download_url( $url, $timeout ) { $GLOBALS['downloads'][] = $url; $file = tempnam( sys_get_temp_dir(), 'cplu-' ); file_put_contents( $file, $GLOBALS['bytes'] ); return $file; }
function verify( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
class ContactPanda_License { public static function product_update( $product, $entitlement, $version, $channel ) { $GLOBALS['metadata_calls'][] = $product; return array_shift( $GLOBALS['metadata_queue'] ); } }
require __DIR__ . '/../contactpanda/includes/class-contactpanda-licensed-updater.php';
function updater( $product, $plugin ) { return new ContactPanda_Licensed_Updater( array( 'product' => $product, 'entitlement' => $product, 'plugin' => $plugin, 'slug' => $product, 'name' => $product, 'version' => '1.0.0' ) ); }
$GLOBALS['cache'] = array(); $GLOBALS['deleted'] = array(); $GLOBALS['downloads'] = array(); $GLOBALS['metadata_calls'] = array(); $GLOBALS['actions'] = array(); $GLOBALS['filters'] = array();
$GLOBALS['bytes'] = 'verified release bytes';
$stale = array( 'available' => true, 'version' => '1.1.0', 'package' => 'https://example.test/expired', 'sha256' => hash( 'sha256', $GLOBALS['bytes'] ) );
$fresh = $stale; $fresh['package'] = 'https://example.test/fresh';
$GLOBALS['metadata_queue'] = array( $stale, $fresh );
$plus = updater( 'plus', 'contactpanda-plus/contactpanda-plus.php' );
$pro = updater( 'pro', 'contactpanda-pro/contactpanda-pro.php' );
$GLOBALS['metadata_queue'][] = $fresh;
$plus->updates( (object) array( 'checked' => array( 'contactpanda-plus/contactpanda-plus.php' => '1.0.0' ) ) );
$file = $plus->verify_download( false, $stale['package'], null, array( 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) );
verify( is_string( $file ) && 'https://example.test/fresh' === $GLOBALS['downloads'][0], 'Expired URL replaced immediately before download' ); wp_delete_file( $file );
verify( false === $pro->verify_download( false, $stale['package'], null, array( 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) ), 'Parallel updater does not intercept another plugin' );
$local_file = tempnam( sys_get_temp_dir(), 'cplu-local-' ); file_put_contents( $local_file, $GLOBALS['bytes'] );
add_filter( 'contactpanda_update_local_package', static function ( $file, $data, $config ) use ( $local_file ) { return 'plus' === $config['product'] && is_file( $local_file ) ? $local_file : $file; }, 10, 3 );
$downloads_before = count( $GLOBALS['downloads'] ); $GLOBALS['metadata_queue'] = array( $fresh );
verify( $local_file === $plus->verify_download( false, $fresh['package'], null, array( 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) ), 'Verified local package bypasses loopback download' );
verify( $downloads_before === count( $GLOBALS['downloads'] ), 'Local package path does not make an HTTP download' );
wp_delete_file( $local_file );
$GLOBALS['bytes'] = 'tampered'; $GLOBALS['metadata_queue'] = array( $fresh );
verify( 'contactpanda_update_checksum' === $plus->verify_download( false, $fresh['package'], null, array( 'plugins' => array( 'contactpanda-plus/contactpanda-plus.php', 'contactpanda-pro/contactpanda-pro.php' ) ) )->get_error_code(), 'Checksum mismatch rejected in bulk update' );
$plus->clear_update_cache( null, array( 'type' => 'plugin', 'plugin' => 'contactpanda-plus/contactpanda-plus.php' ) );
verify( in_array( 'update_plugins', $GLOBALS['deleted'], true ), 'WordPress update cache cleared after success' );
$deleted_product_caches = array_values( array_filter( $GLOBALS['deleted'], static function ( $key ) { return 0 === strpos( $key, 'contactpanda_update_' ) && 'contactpanda_update_refresh_required' !== $key; } ) );
verify( 2 <= count( array_unique( $deleted_product_caches ) ), 'A family update clears every registered product metadata cache' );
$GLOBALS['cache'] = array(); $GLOBALS['metadata_calls'] = array();
$GLOBALS['metadata_queue'] = array( array( 'available' => false, 'status' => 'up_to_date' ), array( 'available' => true, 'version' => '1.1.0', 'package' => 'https://example.test/fresh', 'sha256' => hash( 'sha256', 'verified release bytes' ) ) );
updater( 'business', 'contactpanda-business/contactpanda-business.php' )->updates( (object) array( 'checked' => array( 'contactpanda-business/contactpanda-business.php' => '1.0.0' ) ) );
updater( 'business', 'contactpanda-business/contactpanda-business.php' )->updates( (object) array( 'checked' => array( 'contactpanda-business/contactpanda-business.php' => '1.0.0' ) ) );
verify( 2 === count( $GLOBALS['metadata_calls'] ), 'Negative update responses are not cached across requests' );
echo "Licensed updater stale URL, parallel routing, checksum and cache tests passed.\n";
