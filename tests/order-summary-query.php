<?php
/** Run with: php tests/order-summary-query.php. Sanitized fixtures; no WordPress installation needed. */
define( 'ABSPATH', __DIR__ );

class ContactPanda_Store {
	public static function standalone() { return false; }
}

class OrderSummaryFixture {
	private $id;
	private $email;
	private $total;
	private $refunded;
	public function __construct( $id, $email, $total, $refunded = 0 ) {
		$this->id = $id;
		$this->email = $email;
		$this->total = $total;
		$this->refunded = $refunded;
	}
	public function get_id() { return $this->id; }
	public function get_customer_id() { return 0; }
	public function get_billing_email() { return $this->email; }
	public function get_total() { return $this->total; }
	public function get_total_refunded() { return $this->refunded; }
}

class WC_Order_Refund {
	public function get_id() { return 999; }
	public function get_total() { return 999; }
}

function __( $value, $domain = '' ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_email( $value ) { return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function maybe_unserialize( $value ) { return $value; }
function wp_generate_uuid4() { return '00000000-0000-4000-8000-000000000000'; }
function get_user_by( $field, $value ) { return false; }
function get_user_meta( $user_id, $key, $single ) { return ''; }
function wc_get_order_statuses() {
	return array(
		'wc-completed'      => 'Completed',
		'wc-cancelled'      => 'Cancelled',
		'wc-failed'         => 'Failed',
		'wc-checkout-draft' => 'Checkout draft',
	);
}
function wc_price( $value ) { return number_format( (float) $value, 2, '.', '' ); }
function wc_get_orders( $args ) {
	$GLOBALS['summary_queries'][] = $args;
	if ( empty( $args['customer'] ) || ! is_array( $args['customer'] ) ) {
		throw new RuntimeException( 'Order summary query was not limited to selected customers.' );
	}
	if ( count( $args['customer'] ) > 100 ) {
		throw new RuntimeException( 'Order summary customer chunk is too large.' );
	}
	$page = isset( $args['page'] ) ? (int) $args['page'] : 0;
	if ( 1 === $page ) {
		return (object) array(
			'orders' => array(
				new OrderSummaryFixture( 101, 'person1@example.test', 100, 10 ),
				new WC_Order_Refund(),
			),
			'max_num_pages' => 2,
		);
	}
	return (object) array(
		'orders' => array( new OrderSummaryFixture( 102, 'person2@example.test', 50 ) ),
		'max_num_pages' => 2,
	);
}
function verify( $condition, $label ) { if ( ! $condition ) throw new RuntimeException( $label ); }

require __DIR__ . '/../contactpanda/includes/class-contactpanda-plugin.php';

$contacts = array();
for ( $index = 1; $index <= 205; $index++ ) {
	$contacts[] = array(
		'id'           => $index,
		'email'        => 'person' . $index . '@example.test',
		'optin_status' => array( 'value' => 2, 'label' => 'Confirmed' ),
		'meta'         => array(),
	);
}

$GLOBALS['summary_queries'] = array();
$method = new ReflectionMethod( 'ContactPanda_Plugin', 'contact_order_summaries' );
$method->setAccessible( true );
$summaries = $method->invoke( null, $contacts );

verify( 1 === $summaries[1]['count'] && 90.0 === $summaries[1]['total'], 'Completed order and refund amount are summarized for the matching contact.' );
verify( 1 === $summaries[2]['count'] && 50.0 === $summaries[2]['total'], 'Second result page is included in the summary.' );
verify( 0 === $summaries[3]['count'], 'Unmatched contacts keep an empty summary.' );
verify( 6 === count( $GLOBALS['summary_queries'] ), 'Large contact sets are processed as three bounded customer chunks and two result pages.' );

foreach ( $GLOBALS['summary_queries'] as $query ) {
	verify( isset( $query['customer'] ) && is_array( $query['customer'] ) && $query['customer'], 'Every WooCommerce query is customer-filtered.' );
	verify( ! isset( $query['field_query'] ) && ! isset( $query['paged'] ), 'Summary query uses the portable WooCommerce customer and page arguments.' );
	verify( isset( $query['page'] ) && in_array( $query['page'], array( 1, 2 ), true ), 'WooCommerce result pagination advances with the supported page argument.' );
	verify( in_array( 'wc-cancelled', $query['status'], true ) && in_array( 'wc-failed', $query['status'], true ), 'Historical cancelled and failed orders remain visible in person summaries.' );
	verify( ! in_array( 'wc-checkout-draft', $query['status'], true ), 'Incomplete checkout drafts are not counted as orders.' );
}

echo "WooCommerce order summary query tests passed.\n";
