<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'Panda_Transfer_UI' ) ) {
final class Panda_Transfer_UI {
 private static $products = array();
 public static function register( $product, $capability ) {
  self::$products[$product] = $capability;
  if ( ! has_action( 'wp_ajax_panda_transfer_preset', array( __CLASS__, 'preset' ) ) ) add_action( 'wp_ajax_panda_transfer_preset', array( __CLASS__, 'preset' ) );
 }
 public static function presets( $product, $context ) {
  $out = array();
  foreach ( array( 'personal' => get_user_meta( get_current_user_id(), '_panda_transfer_' . $product, true ), 'shared' => get_option( 'panda_transfer_' . $product, array() ) ) as $visibility => $all ) {
   foreach ( (array) ( is_array( $all ) ? ( $all[$context] ?? array() ) : array() ) as $id => $preset ) { $preset['id'] = $visibility . ':' . $id; $preset['visibility'] = $visibility; $out[] = $preset; }
  } return $out;
 }
 public static function preset() {
  $product = sanitize_key( $_POST['product'] ?? '' );
  if ( empty( self::$products[$product] ) || ! current_user_can( self::$products[$product] ) ) wp_send_json_error( null, 403 );
  check_ajax_referer( 'panda_transfer_' . $product, 'nonce' );
  $context = sanitize_key( $_POST['context'] ?? '' ); if ( ! preg_match( '/^(import|export)-(contacts|companies|products)$/D', $context ) ) wp_send_json_error( null, 400 );
  $visibility = 'shared' === ( $_POST['visibility'] ?? '' ) ? 'shared' : 'personal';
  if ( 'shared' === $visibility && ! current_user_can( 'manage_options' ) ) wp_send_json_error( null, 403 );
  $key = 'panda_transfer_' . $product;
  $all = 'shared' === $visibility ? get_option( $key, array() ) : get_user_meta( get_current_user_id(), '_' . $key, true ); $all = is_array( $all ) ? $all : array();
  $id = sanitize_key( $_POST['id'] ?? '' );
  if ( 'delete' === ( $_POST['operation'] ?? '' ) ) { if ( ! $id || empty( $all[$context][$id] ) ) wp_send_json_error( null, 404 ); unset( $all[$context][$id] ); }
  else {
   $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); $data = json_decode( wp_unslash( $_POST['data'] ?? '' ), true );
   if ( ! $name || ! is_array( $data ) || strlen( wp_json_encode( $data ) ) > 30000 ) wp_send_json_error( null, 400 );
   // Store configuration only. Never store file rows or contact/product values in a preset.
   $safe = array( 'fields' => array_values( array_map( 'sanitize_text_field', array_filter( (array) ( $data['fields'] ?? array() ), 'is_scalar' ) ) ), 'mapping' => array(), 'options' => array() );
   foreach ( (array) ( $data['mapping'] ?? array() ) as $field => $header ) { if ( is_scalar( $header ) ) $safe['mapping'][sanitize_key($field)] = sanitize_text_field( $header ); elseif ( is_array( $header ) ) $safe['mapping'][sanitize_key($field)] = array_values( array_unique( array_map( 'sanitize_text_field', array_filter( $header, 'is_scalar' ) ) ) ); }
   foreach ( array( 'mode', 'option_policy', 'sheet', 'scope', 'variations' ) as $option ) if ( isset( $data['options'][$option] ) && is_scalar( $data['options'][$option] ) ) $safe['options'][$option] = sanitize_text_field( $data['options'][$option] );
   $id = wp_generate_password( 16, false, false ); if ( count( $all[$context] ?? array() ) >= 100 ) wp_send_json_error( null, 400 );
   $all[$context][$id] = array( 'name' => $name, 'data' => $safe, 'owner' => get_current_user_id() );
  }
  if ( 'shared' === $visibility ) update_option( $key, $all, false ); else update_user_meta( get_current_user_id(), '_' . $key, $all );
  wp_send_json_success( self::presets( $product, $context ) );
 }
 public static function render( $config, $url, $version, $domain ) {
  wp_enqueue_style( 'panda-transfer-ui', $url . 'panda-transfer-ui.css', array(), $version );
  wp_print_styles( 'panda-transfer-ui' );
  wp_enqueue_script( 'panda-transfer-ui', $url . 'panda-transfer-ui.js', array(), $version, true );
  $config['ajax'] = admin_url( 'admin-ajax.php' ); $config['nonce'] = wp_create_nonce( 'panda_transfer_' . $config['product'] );
  $config['presets'] = self::presets( $config['product'], $config['context'] ); $config['canShare'] = current_user_can( 'manage_options' );
  $config['labels'] = array();
  foreach ( array( 'Available fields', 'Selected fields', 'File columns', 'Search fields', 'Remove', 'Up', 'Down', 'Do not import', 'Saved templates', 'Load', 'Save template', 'Delete template', 'Template name', 'Share with administrators', 'Save', 'Cancel', 'Delete this template?', 'Template could not be saved.', 'Drag fields from the pool or click to add them. Use the arrow buttons to reorder.', 'Import target', 'No fields selected.', 'Search products', 'Selected products', 'Available filters', 'Minimum', 'Maximum', 'Yes', 'Previous', 'Next', 'Details could not be loaded.' ) as $label ) $config['labels'][$label] = __( $label, $domain );
  echo '<div class="panda-transfer-ui" data-panda-transfer="' . esc_attr( wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ) . '"></div>';
 }
}
}
