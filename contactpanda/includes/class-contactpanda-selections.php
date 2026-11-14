<?php
/** Fixed contact selections, separate from dynamic saved searches. */
defined( 'ABSPATH' ) || exit;
final class ContactPanda_Selections {
 const TYPE = 'cp_person_selection';
 public static function boot() {
  add_action( 'init', static function () { register_post_type( self::TYPE, array( 'public' => false, 'show_ui' => false, 'rewrite' => false, 'supports' => array( 'title' ) ) ); } );
  add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 100 );
  add_action( 'wp_ajax_contactpanda_selection', array( __CLASS__, 'ajax' ) );
 }
 public static function source() { return ContactPanda_Store::standalone() ? 'contactpanda' : 'groundhogg'; }
 public static function clean_ids( $ids ) { return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 10000 ); }
 public static function get( $id ) {
  $post = get_post( absint( $id ) );
  if ( ! $post || self::TYPE !== $post->post_type || 'private' !== $post->post_status || self::source() !== $post->post_excerpt ) return null;
  return array( 'id' => $post->ID, 'name' => $post->post_title, 'ids' => self::clean_ids( json_decode( $post->post_content, true ) ) );
 }
 public static function all() {
  $rows = get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
  $result = array(); foreach ( $rows as $post ) { $row = self::get( $post->ID ); if ( $row ) $result[] = array( 'id' => $row['id'], 'name' => $row['name'], 'count' => count( $row['ids'] ) ); } return $result;
 }
 public static function requested() {
  if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['selection'] ) ) return null; // Read-only list reference; mutations have separate nonce checks.
  $row = self::get( absint( $_GET['selection'] ) ); return $row ? $row['ids'] : array();
 }
 public static function assets() {
  $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // Read-only asset routing.
  if ( strpos( $page, 'contactpanda' ) !== 0 || ! current_user_can( 'manage_options' ) ) return;
  wp_enqueue_script( 'contactpanda-selections', CONTACTPANDA_URL . 'assets/contactpanda-selections.js', array(), CONTACTPANDA_ASSET_VERSION, true );
  wp_localize_script( 'contactpanda-selections', 'CP_SELECTIONS', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contactpanda_selection' ), 'mergeNonce' => wp_create_nonce( 'contactpanda_merge_preview' ), 'post' => admin_url( 'admin-post.php' ), 'source' => self::source(), 'user' => get_current_user_id(), 'rows' => self::all(), 'current' => absint( $_GET['selection'] ?? 0 ), 'i18n' => array( 'name' => __( 'Name of selection', 'contactpanda' ), 'save' => __( 'Save selection', 'contactpanda' ), 'cancel' => __( 'Cancel', 'contactpanda' ), 'load' => __( 'Load saved selection', 'contactpanda' ), 'delete' => __( 'Delete this saved selection? Contacts are retained.', 'contactpanda' ), 'error' => __( 'The selection could not be saved.', 'contactpanda' ), 'count' => __( '%d selected people', 'contactpanda' ), 'empty' => __( 'No person selected', 'contactpanda' ) ) ) );
 }
 public static function add_button() {
  if ( ! current_user_can( 'manage_options' ) ) return;
  $label = __( 'Add to existing selection', 'contactpanda' );
  echo '<button type="button" class="button contactpanda-icon-button cp-selection-add" aria-pressed="false" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span aria-hidden="true">?+</span></button>';
 }
 public static function ajax() {
  if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
  check_ajax_referer( 'contactpanda_selection', 'nonce' );
  $op = sanitize_key( $_POST['operation'] ?? '' ); $id = absint( $_POST['id'] ?? 0 );
  $row = $id ? self::get( $id ) : null;
  if ( $id && ! $row ) wp_send_json_error( array( 'message' => __( 'The selection no longer exists in this data source.', 'contactpanda' ) ), 404 );
  if ( 'load' === $op && $row ) wp_send_json_success( $row );
  if ( 'delete' === $op && $row ) {
   if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) wp_send_json_error( array( 'message' => __( 'Confirm deletion first.', 'contactpanda' ) ), 400 );
   $result = wp_trash_post( $id );
  } elseif ( in_array( $op, array( 'save', 'rename' ), true ) ) {
   $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
   if ( '' === $name || strlen( $name ) > 200 ) wp_send_json_error( array( 'message' => __( 'Enter a selection name of up to 200 characters.', 'contactpanda' ) ), 400 );
   $ids = 'rename' === $op && $row ? $row['ids'] : self::clean_ids( wp_unslash( $_POST['ids'] ?? array() ) );
   if ( ! $ids ) wp_send_json_error( array( 'message' => __( 'Select at least one person.', 'contactpanda' ) ), 400 );
   // Validate membership against the active provider; deleted records never become sendable again.
   if ( 'save' === $op ) $ids = array_values( array_filter( $ids, static function ( $contact_id ) { return (bool) ContactPanda_Plugin::extension_contact( $contact_id ); } ) );
   if ( ! $ids ) wp_send_json_error( array( 'message' => __( 'No active people remain in this selection.', 'contactpanda' ) ), 400 );
   $result = wp_insert_post( wp_slash( array( 'ID' => $id, 'post_type' => self::TYPE, 'post_status' => 'private', 'post_title' => $name, 'post_content' => wp_json_encode( $ids ), 'post_excerpt' => self::source() ) ), true );
  } else wp_send_json_error( array( 'message' => __( 'Unknown selection action.', 'contactpanda' ) ), 400 );
  if ( ! $result || is_wp_error( $result ) ) wp_send_json_error( array( 'message' => __( 'The selection could not be saved.', 'contactpanda' ) ), 500 );
  wp_send_json_success( array( 'id' => is_numeric( $result ) ? (int) $result : $id ) );
 }
 public static function render_management() {
  if ( ! current_user_can( 'manage_options' ) ) return;
  echo '<section class="contactpanda-card"><h2>' . esc_html__( 'Saved person selections', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Fixed lists of selected people. Dynamic saved searches are managed separately above.', 'contactpanda' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'contactpanda' ) . '</th><th>' . esc_html__( 'People', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
  $rows = self::all();
  foreach ( $rows as $row ) {
   $url = add_query_arg( array( 'page' => 'contactpanda-people', 'selection' => $row['id'] ), admin_url( 'admin.php' ) );
   echo '<tr data-cp-selection="' . esc_attr( $row['id'] ) . '"><td class="cp-selection-name">' . esc_html( $row['name'] ) . '</td><td>' . esc_html( $row['count'] ) . '</td><td><a class="button" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>' . esc_html__( 'View', 'contactpanda' ) . '</a> <button type="button" class="button cp-selection-rename"><span class="dashicons dashicons-edit" aria-hidden="true"></span>' . esc_html__( 'Rename', 'contactpanda' ) . '</button> <button type="button" class="button cp-selection-delete"><span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__( 'Delete', 'contactpanda' ) . '</button></td></tr>';
  }
  if ( ! $rows ) echo '<tr><td colspan="3">' . esc_html__( 'No saved person selections yet.', 'contactpanda' ) . '</td></tr>';
  echo '</tbody></table></section>';
 }
}
