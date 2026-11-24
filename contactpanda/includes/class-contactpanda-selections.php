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
  $rows = get_posts( array( 'post_type' => self::TYPE, 'post_status' => 'private', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Internal selection records must not be filtered by content-language plugins; individual records are validated by get().
  $result = array(); foreach ( $rows as $post ) { $row = self::get( $post->ID ); if ( $row ) $result[] = array( 'id' => $row['id'], 'name' => $row['name'], 'count' => count( $row['ids'] ) ); } return $result;
 }
 public static function requested() {
  if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['selection'] ) ) return null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/filter routing; no mutation or permission grant. Writes use separate capability and nonce checks.
  $row = self::get( absint( $_GET['selection'] ) ); return $row ? $row['ids'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/filter routing; no mutation or permission grant. Writes use separate capability and nonce checks.
 }
 public static function assets() {
  $page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/filter routing; no mutation or permission grant. Writes use separate capability and nonce checks.
  if ( strpos( $page, 'contactpanda' ) !== 0 || ! current_user_can( 'manage_options' ) ) return;
  wp_enqueue_script( 'contactpanda-selections', CONTACTPANDA_URL . 'assets/contactpanda-selections.js', array(), CONTACTPANDA_ASSET_VERSION, true );
  wp_localize_script( 'contactpanda-selections', 'CP_SELECTIONS', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contactpanda_selection' ), 'mergeNonce' => wp_create_nonce( 'contactpanda_merge_preview' ), 'post' => admin_url( 'admin-post.php' ), 'source' => self::source(), 'user' => get_current_user_id(), 'rows' => self::all(), 'current' => self::get( absint( $_GET['selection'] ?? 0 ) ) ? absint( $_GET['selection'] ) : 0, 'i18n' => array( 'name' => __( 'Name of selection', 'contactpanda' ), 'save' => __( 'Save selection', 'contactpanda' ), 'cancel' => __( 'Cancel', 'contactpanda' ), 'load' => __( 'Load saved selection', 'contactpanda' ), 'delete' => __( 'Delete this saved selection? Contacts are retained.', 'contactpanda' ), 'error' => __( 'The selection could not be saved.', 'contactpanda' ), 'count' => // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view/filter routing; no mutation or permission grant. Writes use separate capability and nonce checks.
  /* translators: %d: number of selected people. */
  __( '%d selected people', 'contactpanda' ), 'empty' => __( 'No person selected', 'contactpanda' ), 'added' => __( 'People added to the selection: %d.', 'contactpanda' ), 'capacity' => __( 'The selection can hold up to 10,000 people.', 'contactpanda' ), 'unnamed' => __( 'Unnamed person', 'contactpanda' ), 'remove' => __( 'Remove from selection', 'contactpanda' ), 'more' => __( 'Show more', 'contactpanda' ) ) ) );
 }
 public static function add_button( $list_state = '' ) {
  if ( ! current_user_can( 'manage_options' ) ) return;
  $label = __( 'Add to existing selection', 'contactpanda' );
  echo '<button type="button" class="button contactpanda-icon-button cp-selection-add" data-contactpanda-action="searchAdd" data-contactpanda-action-mode="icon" data-list-token="' . esc_attr( $list_state ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="contactpanda-action-icon contactpanda-action-icon-search-add" aria-hidden="true"></span></button><span class="cp-selection-result" role="status"></span>';
 }
 public static function people_state( $token ) {
  if ( ! preg_match( '/^[a-z0-9]{12}$/D', $token ) ) return array();
  $state = get_transient( 'contactpanda_people_state_' . get_current_user_id() . '_' . $token );
  return is_array( $state ) && ( $state['source'] ?? '' ) === self::source() ? $state : array();
 }
 public static function ajax() {
  if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
  check_ajax_referer( 'contactpanda_selection', 'nonce' );
  $op = sanitize_key( wp_unslash( $_POST['cp_selection_operation'] ?? $_POST['operation'] ?? '' ) ); $id = absint( $_POST['id'] ?? 0 );
  $row = $id ? self::get( $id ) : null;
  if ( $id && ! $row ) wp_send_json_error( array( 'message' => __( 'The selection no longer exists in this data source.', 'contactpanda' ) ), 404 );
  if ( 'search_results' === $op ) {
   $token = sanitize_key( wp_unslash( $_POST['list_token'] ?? '' ) );
   $state = self::people_state( $token );
   if ( ! $state ) wp_send_json_error( array( 'message' => __( 'The search has expired. Run the search again.', 'contactpanda' ) ), 404 );
   wp_send_json_success( array( 'ids' => self::clean_ids( $state['ids'] ), 'total' => count( $state['ids'] ) ) );
  }
  if ( 'people_labels' === $op ) {
   $requested = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array();
   $names = array();
   foreach ( array_slice( self::clean_ids( $requested ), 0, 50 ) as $contact_id ) {
    $contact = ContactPanda_Plugin::extension_contact( $contact_id );
    if ( ! $contact ) continue;
    $first = is_array( $contact ) ? ( $contact['first_name'] ?? '' ) : ( is_callable( array( $contact, 'get_first_name' ) ) ? $contact->get_first_name() : '' );
    $last = is_array( $contact ) ? ( $contact['last_name'] ?? '' ) : ( is_callable( array( $contact, 'get_last_name' ) ) ? $contact->get_last_name() : '' );
    $names[ $contact_id ] = trim( $first . ' ' . $last ) ?: __( 'Unnamed person', 'contactpanda' );
   }
   wp_send_json_success( (object) $names );
  }
  if ( 'load' === $op ) {
   if ( ! $row ) wp_send_json_error( array( 'message' => __( 'The selection no longer exists in this data source.', 'contactpanda' ) ), 404 );
   wp_send_json_success( $row );
  }
  if ( 'delete' === $op && $row ) {
   if ( '1' !== sanitize_text_field( wp_unslash( $_POST['confirmed'] ?? '' ) ) ) wp_send_json_error( array( 'message' => __( 'Confirm deletion first.', 'contactpanda' ) ), 400 );
   $result = wp_trash_post( $id );
  } elseif ( in_array( $op, array( 'save', 'rename' ), true ) ) {
   $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
   if ( '' === $name || strlen( $name ) > 200 ) wp_send_json_error( array( 'message' => __( 'Enter a selection name of up to 200 characters.', 'contactpanda' ) ), 400 );
   $ids = 'rename' === $op && $row ? $row['ids'] : self::clean_ids( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) ) );
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
