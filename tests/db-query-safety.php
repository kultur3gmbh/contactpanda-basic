<?php
/** Exercise the actual query builders and schema-cache lifecycle with strict fixture SQL. */
$fixture_root = sys_get_temp_dir() . '/contactpanda-db-test-' . uniqid();
mkdir( $fixture_root . '/wp-admin/includes', 0700, true );
file_put_contents( $fixture_root . '/wp-admin/includes/upgrade.php', '<?php' );
define( 'ABSPATH', $fixture_root . '/' ); define( 'ARRAY_A', 'ARRAY_A' );
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_email( $v ) { return filter_var( $v, FILTER_SANITIZE_EMAIL ); }
function maybe_serialize( $v ) { return is_array( $v ) ? serialize( $v ) : $v; }
function current_time( $v ) { return '2026-01-01 00:00:00'; }
function __( $v, $d = '' ) { return $v; }
function get_option( $key, $default = false ) { return $GLOBALS['db_options'][$key] ?? $default; }
function update_option( $key, $value, ...$unused ) { $GLOBALS['db_options'][$key] = $value; }
function delete_option( $key ) { unset( $GLOBALS['db_options'][$key] ); }
function do_action( ...$unused ) {}
function wp_unslash( $v ) { return $v; }
function current_user_can( $cap ) { return true; }
function esc_html__( $v, $d = '' ) { return $v; }
class SafetyQueryReached extends RuntimeException {}
function dbDelta( $sql ) { preg_match( '/CREATE TABLE ([a-zA-Z0-9_]+)/', $sql, $m ); $GLOBALS['wpdb']->tables[$m[1]] = true; ++$GLOBALS['delta_count']; }
class WP_Error { public function __construct( ...$unused ) {} }
class ContactPanda_Custom_Fields { const PREFIX = 'custom_'; }
class SafetyDB {
 public $stop_on = ''; public $campaign_fixture = false;
 public $prefix = 'fixture_73_'; public $tables = array(); public $prepared = array(); public $reads = 0;
 public function esc_like( $v ) { return addcslashes( $v, '_%\\' ); }
 public function get_charset_collate() { return ''; }
 public function prepare( $sql, ...$args ) {
  if ( count( $args ) === 1 && is_array( $args[0] ) ) $args = $args[0];
  preg_match_all( '/(?<!%)%[disf]/', $sql, $matches );
  if ( count( $matches[0] ) !== count( $args ) ) throw new RuntimeException( 'Placeholder mismatch: ' . $sql );
  if ( strpos( $sql, 'phpcs:' ) !== false ) throw new RuntimeException( 'An audit comment leaked into the SQL literal.' );
  $this->prepared[] = array( $sql, $args ); $i = 0;
  return preg_replace_callback( '/(?<!%)%[disf]/', static function ( $m ) use ( &$i, $args ) {
   $v = $args[$i++];
   if ( '%i' === $m[0] ) return '`' . str_replace( '`', '``', $v ) . '`';
   if ( '%d' === $m[0] ) return (string) (int) $v;
   if ( '%f' === $m[0] ) return (string) (float) $v;
   return "'" . str_replace( "'", "''", $v ) . "'";
  }, $sql );
 }
 public function get_var( $sql ) {
  ++$this->reads;
  if ( preg_match( "/SHOW TABLES LIKE '(.+)'/", $sql, $m ) ) { $table = stripslashes( $m[1] ); return ! empty( $this->tables[$table] ) ? $table : null; }
  if ( strpos( $sql, 'SHOW COLUMNS' ) === 0 ) return 'fixture-column';
  if ( strpos( $sql, 'SHOW INDEX' ) === 0 ) return strpos( $sql, "Key_name='language_name'" ) !== false ? 'language_name' : null;
  return 0;
 }
 public function get_results( $sql, $format = null ) { ++$this->reads; if ( $this->stop_on && strpos( $sql, $this->stop_on ) !== false ) throw new SafetyQueryReached(); return array(); }
 public function get_row( $sql, $format = null ) { ++$this->reads; return $this->campaign_fixture ? array( 'id' => 17, 'status' => 'draft' ) : null; }
 public function get_col( $sql ) { ++$this->reads; return array(); }
 public function query( $sql ) { if ( preg_match( '/DROP TABLE IF EXISTS `([^`]+)`/', $sql, $m ) ) unset( $this->tables[$m[1]] ); return 0; }
}
function db_assert( $ok, $why ) { if ( ! $ok ) throw new RuntimeException( $why ); }
require __DIR__ . '/../contactpanda/includes/class-contactpanda-schema.php';
require __DIR__ . '/../contactpanda/includes/class-contactpanda-newsletter-schema.php';
require __DIR__ . '/../contactpanda/includes/class-contactpanda-store.php';
require __DIR__ . '/../contactpanda/includes/class-contactpanda-newsletter-campaigns.php';
require __DIR__ . '/../contactpanda/includes/class-contactpanda-identity-store.php';
require __DIR__ . '/../contactpanda/includes/class-contactpanda-newsletters.php';
$GLOBALS['wpdb'] = new SafetyDB(); $db = $GLOBALS['wpdb']; $GLOBALS['delta_count'] = 0;
try {
 foreach ( array( 'ContactPanda_Schema', 'ContactPanda_Newsletter_Schema' ) as $schema ) {
  $missing = $schema::status(); db_assert( $missing['count'] === 0, 'Fresh installation must detect missing tables.' );
  $reads = $db->reads; $schema::status(); db_assert( $reads === $db->reads, 'Repeated metadata lookup must stay within request cache.' );
  db_assert( true === $schema::install(), 'Install/upgrade must succeed after a cached negative lookup.' );
  $ready = $schema::status(); db_assert( $ready['count'] === $ready['total'], 'DDL must invalidate negative schema cache.' );
  $deltas = $GLOBALS['delta_count']; $reads = $db->reads; $schema::install();
  db_assert( $deltas === $GLOBALS['delta_count'] && $reads === $db->reads, 'Up-to-date installation must avoid repeated DDL and metadata reads.' );
 }
 foreach ( array( '', "O'Reilly%_ OR 1=1 --" ) as $search ) {
  $result = ContactPanda_Store::search_contacts( $search, 12, 24 ); db_assert( $result['total_items'] === 0, 'Empty fixture result.' );
  $call = end( $db->prepared ); $args = $call[1];
  db_assert( array_slice( $args, -2 ) === array( 12, 24 ), 'Search pagination remains bound as integers.' );
  if ( $search ) db_assert( in_array( '%' . $db->esc_like( $search ) . '%', $args, true ), 'Wildcard characters must remain escaped search values.' );
 }
 foreach ( array( false, true ) as $trash ) {
  ContactPanda_Store::contact( 17, $trash ); $call = end( $db->prepared ); db_assert( $call[1] === array( 'fixture_73_cp_contacts', 17, (int) $trash ), 'Trash visibility must remain a bound boolean.' );
  ContactPanda_Store::contact_by_email( 'safe@example.invalid', $trash );
  ContactPanda_Store::contact_by_user_id( 9, $trash );
  ContactPanda_Store::contact_by_meta( 'custom_fixture', 'quoted value', $trash );
  ContactPanda_Store::company_by_name( "Fixture's company", $trash );
 }
 foreach ( array( 'all', 'de', 'en' ) as $language ) {
  ContactPanda_Store::salutations( $language, 0, 0, '' );
  ContactPanda_Store::salutations( $language, 20, 40, "Fi_%'" );
 }
 ContactPanda_Store::contact_company_relations( 17 ); ContactPanda_Store::salutation( 2 );
 // Exercise the actual campaign render query and all recipient filter variants,
 // stopping at the result boundary before template rendering or any side effect.
 $db->stop_on = 'SELECT c.*';
 foreach ( array( array(), array( 'mailing_search' => "Quoted_%'", 'mailing_status' => 'completed', 'campaign_groups' => array( '4', '8', 'none' ), 'date_from' => '2026-01-01', 'date_to' => '2026-12-31' ) ) as $request ) {
  $_GET = $request;
  try { ContactPanda_Basic_Newsletter_Campaigns::render(); throw new RuntimeException( 'Campaign query not reached.' ); } catch ( SafetyQueryReached $ok ) {}
  $call = end( $db->prepared );
  db_assert( count( array_filter( array_slice( $call[1], 0, 5 ), static function ( $v ) { return strpos( $v, 'fixture_73_cp_' ) === 0; } ) ) === 5, 'Campaign joins require five leading identifiers.' );
 }
 $db->stop_on = 'ORDER BY status ASC,email ASC'; $db->campaign_fixture = true;
 $method = new ReflectionMethod( 'ContactPanda_Basic_Newsletter_Campaigns', 'render_recipients' );
 if ( PHP_VERSION_ID < 80100 ) $method->setAccessible( true );
 foreach ( array( 'all', 'opened', 'clicked', 'failed', 'skipped', 'sent', 'invalid' ) as $filter ) {
  foreach ( array( '', "Quoted_%'" ) as $search ) {
   $_GET = array( 'recipient_filter' => $filter, 'recipient_search' => $search, 'recipient_page' => 3, 'recipient_per_page' => 25 );
   ob_start();
   try { $method->invoke( null, 17 ); throw new RuntimeException( 'Recipient query not reached.' ); } catch ( SafetyQueryReached $ok ) {} finally { ob_end_clean(); }
   $call = end( $db->prepared );
   db_assert( $call[1][0] === 'fixture_73_cp_newsletter_recipients' && $call[1][1] === 17 && array_slice( $call[1], -2 ) === array( 25, 0 ), 'Recipient identifiers, campaign and paging remain in SQL order.' );
  }
 }
 $db->stop_on = ''; $db->campaign_fixture = false;
 foreach ( array( false, true ) as $trash ) {
  ContactPanda_Identity_Store::contact_id_by_channel( 'email', 'safe@example.invalid', $trash );
  $call = end( $db->prepared ); db_assert( end( $call[1] ) === (int) $trash, 'Channel trash flag must be bound.' );
  ContactPanda_Identity_Store::contact_id_by_identity( 'fixture', 'id', "value'", $trash );
 }
 $blocks = new ReflectionMethod( 'ContactPanda_Basic_Newsletters', 'sanitize_blocks' );
 if ( PHP_VERSION_ID < 80100 ) $blocks->setAccessible( true );
 foreach ( array( array(), str_repeat( 'x', 1024 * 1024 + 1 ), '[null,1,"text"]', 'null', '{"invalid":true}' ) as $invalid ) db_assert( $blocks->invoke( null, $invalid ) === array(), 'Malformed/oversized editor JSON must not become stored blocks.' );
 foreach ( array( 'ContactPanda_Schema', 'ContactPanda_Newsletter_Schema' ) as $schema ) {
  $schema::drop_all(); $empty = $schema::status(); db_assert( $empty['count'] === 0, 'Drop must invalidate cached positive schema results.' );
 }
 echo "SQL safety passed: identifier binding, quoted/wildcard searches, pagination, trash visibility, language filters and schema-cache invalidation.\n";
} finally { unlink( ABSPATH . 'wp-admin/includes/upgrade.php' ); rmdir( ABSPATH . 'wp-admin/includes' ); rmdir( ABSPATH . 'wp-admin' ); rmdir( ABSPATH ); }
