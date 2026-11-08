<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Basic_Segments {
	public static function boot() {
		add_filter( 'contactpanda_misc_settings_sections', array( __CLASS__, 'misc_settings_sections' ) );
		add_action( 'contactpanda_render_misc_settings_section_saved_searches', array( __CLASS__, 'render_settings_section' ) );
		add_action( 'admin_post_contactpanda_save_segment', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_contactpanda_delete_segment', array( __CLASS__, 'delete' ) );
		add_action( 'contactpanda_people_filter_controls', array( __CLASS__, 'filter_control' ) );
		add_action( 'contactpanda_people_advanced_search', array( __CLASS__, 'render_inline_search' ), 10, 2 );
		add_action( 'wp_ajax_contactpanda_filter_count', array( __CLASS__, 'ajax_filter_count' ) );
		add_action( 'wp_ajax_contactpanda_save_inline_search', array( __CLASS__, 'ajax_save_inline_search' ) );
		add_filter( 'contactpanda_people_contacts', array( __CLASS__, 'filter_contacts' ), 10, 3 );
		add_filter( 'contactpanda_company_records', array( __CLASS__, 'filter_companies' ), 10, 2 );
	}

	public static function misc_settings_sections( $sections ) {
		$sections['saved_searches'] = __( 'Saved searches', 'contactpanda' );
		return $sections;
	}

	public static function render_settings_section() {
		self::render( true );
	}

	public static function all() {
		global $wpdb;
		ContactPanda_Newsletter_Schema::install();
		$table = ContactPanda_Newsletter_Schema::table_names()['segments'];
		return $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	public static function get( $id ) {
		global $wpdb;
		$table = ContactPanda_Newsletter_Schema::table_names()['segments'];
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table.
	}

	public static function filter_control( $selected ) {
		$segments = self::all();
		echo '<button type="button" class="button contactpanda-open-inline-search"><span class="dashicons dashicons-filter"></span>' . esc_html__( 'Filter contacts', 'contactpanda' ) . '</button><span class="contactpanda-people-segment-control"><label class="screen-reader-text" for="contactpanda-segment-filter">' . esc_html__( 'Saved search', 'contactpanda' ) . '</label><select id="contactpanda-segment-filter" name="segment"><option value="0">' . esc_html__( 'Load saved search', 'contactpanda' ) . '</option>';
		foreach ( $segments as $segment ) {
			if ( 'companies' === ( $segment['entity_type'] ?? 'contacts' ) ) continue;
			echo '<option value="' . esc_attr( $segment['id'] ) . '" ' . selected( $selected, $segment['id'], false ) . '>' . esc_html( $segment['name'] ) . '</option>';
		}
		echo '</select></span>';
	}

	public static function filter_contacts( $contacts, $segment_id, $advanced_filter = '' ) {
		if ( $segment_id ) {
			$segment = self::get( $segment_id );
			if ( $segment ) {
				$rules = json_decode( (string) $segment['rules_json'], true );
				$rules = is_array( $rules ) ? $rules : array();
				if ( $rules && isset( $rules[0]['field'] ) ) {
					$contacts = self::filter_by_state( $contacts, array( 'relation' => $segment['relation_mode'], 'rules' => $rules ) );
				} else {
					$contacts = self::filter_legacy_rules( $contacts, $rules, $segment['relation_mode'] );
				}
			}
		}
		if ( $advanced_filter ) {
			$state = self::decode_filter( $advanced_filter );
			if ( $state['rules'] ) {
				$contacts = self::filter_by_state( $contacts, $state );
			}
		}
		return array_values( $contacts );
	}

	/**
	 * Evaluate one contact against a saved contact search.
	 *
	 * Flows use this public contract for visual yes/no branches without
	 * duplicating the saved-search rule engine.
	 */
	public static function contact_matches( $contact, $segment_id ) {
		if ( ! $contact || ! absint( $segment_id ) ) return false;
		return 1 === count( self::filter_contacts( array( $contact ), absint( $segment_id ) ) );
	}

	public static function filter_companies( $companies, $segment_id ) {
		$segment = $segment_id ? self::get( $segment_id ) : array();
		if ( ! $segment || 'companies' !== ( $segment['entity_type'] ?? 'contacts' ) ) return $companies;
		$rules = json_decode( (string) $segment['rules_json'], true );
		$mode = 'or' === ( $segment['relation_mode'] ?? 'and' ) ? 'or' : 'and';
		return array_values( array_filter( $companies, static function ( $company ) use ( $rules, $mode ) {
			$results = array();
			foreach ( (array) $rules as $rule ) {
				$type = sanitize_key( $rule['type'] ?? '' ); $needle = strtolower( sanitize_text_field( $rule['value'] ?? '' ) );
				$map = array( 'company_name_contains' => array( 'name', 'company_name' ), 'company_email_contains' => array( 'email' ), 'company_phone_contains' => array( 'phone', 'phone_number' ), 'company_city_contains' => array( 'city' ), 'company_country_is' => array( 'country' ) );
				$haystack = strtolower( (string) ContactPanda_Plugin::extension_value( $company, $map[ $type ] ?? array(), '' ) );
				$results[] = 'company_country_is' === $type ? $haystack === $needle : false !== strpos( $haystack, $needle );
			}
			return $results && ( 'or' === $mode ? in_array( true, $results, true ) : ! in_array( false, $results, true ) );
		} ) );
	}

	private static function filter_legacy_rules( $contacts, $rules, $mode ) {
		$mode = 'or' === $mode ? 'or' : 'and';
		$context = self::filter_context( $contacts, $rules );
		return array_values( array_filter( $contacts, static function ( $contact ) use ( $rules, $mode, $context ) {
			$results = array();
			foreach ( $rules as $rule ) {
				$results[] = self::matches( $contact, $rule, $context );
			}
			return $results && ( 'or' === $mode ? in_array( true, $results, true ) : ! in_array( false, $results, true ) );
		} ) );
	}

	public static function render_inline_search( $encoded_state, $current_total, $force_open = false ) {
		$state = self::decode_filter( $encoded_state );
		$is_open = $force_open || ! empty( $state['rules'] );
		echo '<div class="contactpanda-inline-search' . ( $is_open ? ' is-open' : '' ) . '" data-current-total="' . esc_attr( $current_total ) . '"' . ( $is_open ? '' : ' hidden' ) . '>';
		echo '<div class="contactpanda-inline-search-top"><div><strong>' . esc_html__( 'Filter contacts', 'contactpanda' ) . '</strong><span>' . esc_html__( 'Combine personal data, addresses, tags, WooCommerce purchases, and events.', 'contactpanda' ) . '</span></div><button type="button" class="button-link contactpanda-close-inline-search" aria-label="' . esc_attr__( 'Close filter', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt"></span></button></div>';
		echo '<div class="contactpanda-inline-relation"><span>' . esc_html__( 'People must match', 'contactpanda' ) . '</span><label><input type="radio" name="contactpanda_inline_relation" value="and"' . checked( $state['relation'], 'and', false ) . '> ' . esc_html__( 'all conditions', 'contactpanda' ) . '</label><label><input type="radio" name="contactpanda_inline_relation" value="or"' . checked( $state['relation'], 'or', false ) . '> ' . esc_html__( 'at least one condition', 'contactpanda' ) . '</label></div>';
		echo '<div class="contactpanda-inline-rules"></div><div class="contactpanda-inline-search-actions"><button type="button" class="button contactpanda-add-inline-rule"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__( 'Add filter', 'contactpanda' ) . '</button><a class="button button-primary contactpanda-show-filter-results" href="#" aria-disabled="true">' . esc_html__( 'Complete the filter', 'contactpanda' ) . '</a><button type="button" class="button contactpanda-save-inline-search">' . esc_html__( 'Save this search', 'contactpanda' ) . '</button><button type="button" class="button-link contactpanda-clear-inline-search">' . esc_html__( 'Clear filters', 'contactpanda' ) . '</button><span class="contactpanda-inline-search-status" aria-live="polite"></span></div>';
		echo '<div class="contactpanda-inline-save" hidden><label><span>' . esc_html__( 'Name of this search', 'contactpanda' ) . '</span><input type="text" class="contactpanda-inline-save-name" placeholder="' . esc_attr__( 'e.g. Customers from Vienna', 'contactpanda' ) . '"></label><button type="button" class="button button-primary contactpanda-confirm-inline-save">' . esc_html__( 'Save search', 'contactpanda' ) . '</button></div></div>';
	}

	public static function inline_config( $campaign_mode = false ) {
		$active_state = self::decode_filter( isset( $_GET['contactpanda_filter'] ) ? wp_unslash( $_GET['contactpanda_filter'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI state.
		if ( ! $active_state['rules'] && ! empty( $_GET['segment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only saved search.
			$segment = self::get( absint( $_GET['segment'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rules = $segment ? json_decode( (string) $segment['rules_json'], true ) : array();
			if ( is_array( $rules ) && $rules && isset( $rules[0]['field'] ) ) $active_state = self::sanitize_state( array( 'relation' => $segment['relation_mode'], 'rules' => $rules ) );
		}
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'contactpanda_inline_search' ),
			'fields' => self::inline_fields(),
			'operators' => self::operator_labels(),
			'activeState' => $active_state,
			'peopleUrl' => admin_url( 'admin.php?page=contactpanda-people' ),
			'campaignMode' => (bool) $campaign_mode,
			'i18n' => array(
				'chooseField' => __( 'Choose a field', 'contactpanda' ), 'chooseValue' => __( 'Please select', 'contactpanda' ),
				'value' => __( 'Value', 'contactpanda' ), 'remove' => __( 'Remove filter', 'contactpanda' ),
				'addFirst' => __( 'Add a filter to narrow the people list.', 'contactpanda' ),
				'checking' => __( 'Counting matching people…', 'contactpanda' ),
				'complete' => __( 'Complete all filters', 'contactpanda' ),
				'showOne' => __( 'Search finds 1 person – show now', 'contactpanda' ),
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				'showMany' => __( 'Search finds %d people – show now', 'contactpanda' ),
				'campaignOne' => __( '1 matching person', 'contactpanda' ),
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				'campaignMany' => __( '%d matching people', 'contactpanda' ),
				'countFailed' => __( 'The matching people could not be counted.', 'contactpanda' ),
				'enterName' => __( 'Please enter a name for this search.', 'contactpanda' ),
				'saving' => __( 'Saving search…', 'contactpanda' ), 'saved' => __( 'The search was saved.', 'contactpanda' ),
				'searchEvents' => __( 'Search events', 'contactpanda' ),
				'searchProducts' => __( 'Search products', 'contactpanda' ),
				'noOptions' => __( 'No matching entries', 'contactpanda' ),
			),
		);
	}

	public static function ajax_filter_count() {
		self::guard_inline_ajax();
		$state = self::sanitize_state( json_decode( (string) wp_unslash( $_POST['state'] ?? '' ), true ) );
		if ( ! $state['rules'] ) {
			wp_send_json_error( array( 'message' => __( 'Please add at least one complete filter.', 'contactpanda' ) ), 400 );
		}
		$contacts = ContactPanda_Plugin::extension_contacts();
		$matches = self::filter_by_state( $contacts, $state );
		wp_send_json_success( array( 'count' => count( $matches ), 'filter' => self::encode_filter( $state ) ) );
	}

	public static function ajax_save_inline_search() {
		self::guard_inline_ajax();
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$state = self::sanitize_state( json_decode( (string) wp_unslash( $_POST['state'] ?? '' ), true ) );
		if ( '' === $name || ! $state['rules'] ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a name and at least one complete condition.', 'contactpanda' ) ), 400 );
		}
		global $wpdb;
		ContactPanda_Newsletter_Schema::install();
		$table = ContactPanda_Newsletter_Schema::table_names()['segments'];
		$inserted = $wpdb->insert( $table, array( 'name' => $name, 'relation_mode' => $state['relation'], 'rules_json' => wp_json_encode( $state['rules'] ), 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );
		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => __( 'The search could not be saved.', 'contactpanda' ) ), 500 );
		}
		wp_send_json_success( array( 'id' => (int) $wpdb->insert_id, 'name' => $name, 'message' => __( 'The search was saved.', 'contactpanda' ) ) );
	}

	private static function guard_inline_ajax() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		}
		check_ajax_referer( 'contactpanda_inline_search', 'nonce' );
	}

	private static function encode_filter( $state ) {
		return rtrim( strtr( base64_encode( wp_json_encode( self::sanitize_state( $state ) ) ), '+/', '-_' ), '=' );
	}

	private static function decode_filter( $encoded ) {
		$encoded = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $encoded ), 0, 12000 );
		if ( '' === $encoded ) {
			return array( 'relation' => 'and', 'rules' => array() );
		}
		$padding = strlen( $encoded ) % 4;
		if ( $padding ) { $encoded .= str_repeat( '=', 4 - $padding ); }
		$decoded = base64_decode( strtr( $encoded, '-_', '+/' ), true );
		return self::sanitize_state( $decoded ? json_decode( $decoded, true ) : array() );
	}

	private static function sanitize_state( $state ) {
		$state = is_array( $state ) ? $state : array();
		$definitions = self::inline_fields();
		$allowed = array(); foreach ( $definitions as $group ) { foreach ( $group['fields'] as $field ) { $allowed[ $field['key'] ] = $field; } }
		$rules = array();
		foreach ( array_slice( (array) ( $state['rules'] ?? array() ), 0, 20 ) as $rule ) {
			$field = sanitize_key( $rule['field'] ?? '' ); $operator = sanitize_key( $rule['operator'] ?? '' );
			if ( 'event' === $field && in_array( $operator, array( 'booked', 'attended', 'not_attended' ), true ) ) $operator = 'is';
			if ( 'event_category' === $field && 'booked' === $operator ) $operator = 'is';
			if ( ! isset( $allowed[ $field ] ) || ! in_array( $operator, $allowed[ $field ]['operators'], true ) ) { continue; }
			$value = isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '';
			if ( ! in_array( $operator, array( 'empty', 'not_empty' ), true ) && '' === $value ) { continue; }
			$rules[] = array( 'field' => $field, 'operator' => $operator, 'value' => $value );
		}
		return array( 'relation' => isset( $state['relation'] ) && 'or' === $state['relation'] ? 'or' : 'and', 'rules' => $rules );
	}

	private static function filter_by_state( $contacts, $state ) {
		$state = self::sanitize_state( $state ); $mode = $state['relation']; $rules = $state['rules'];
		if ( ! $rules ) { return $contacts; }
		$context = self::filter_context( $contacts, $rules );
		return array_values( array_filter( $contacts, static function ( $contact ) use ( $rules, $mode, $context ) {
			$results = array(); foreach ( $rules as $rule ) { $results[] = self::matches_inline( $contact, $rule, $context ); }
			return 'or' === $mode ? in_array( true, $results, true ) : ! in_array( false, $results, true );
		} ) );
	}

	private static function matches_inline( $contact, $rule, $context = array() ) {
		$field = $rule['field']; $operator = $rule['operator']; $expected = (string) $rule['value'];
		if ( in_array( $field, array( 'has_orders', 'has_events' ), true ) ) {
			$count = self::inline_numeric_value( $contact, 'has_orders' === $field ? 'order_count' : 'event_count', $context );
			$actual = $count > 0 ? 'yes' : 'no';
			return 'is_not' === $operator ? $actual !== $expected : $actual === $expected;
		}
		if ( in_array( $field, array( 'order_number', 'invoice_number' ), true ) ) {
			$orders = self::context_orders( $contact, $context );
			foreach ( $orders as $order ) {
				$actual = 'order_number' === $field ? (string) $order->get_order_number() : self::invoice_number( $order );
				if ( self::matches_text( $actual, $expected, $operator ) ) return true;
			}
			return false;
		}
		if ( in_array( $field, array( 'order_count', 'order_total', 'event_count' ), true ) ) {
			$actual = self::inline_numeric_value( $contact, $field, $context );
			if ( 'lte' === $operator ) return $actual <= (float) $expected;
			if ( 'equals' === $operator ) return $actual === (float) $expected;
			return $actual >= (float) $expected;
		}
		if ( in_array( $field, array( 'product', 'product_category', 'ordered_within_days', 'event', 'event_category', 'event_within_days' ), true ) ) {
			$map = array(
				'product' => 'not_purchased' === $operator ? 'product_not_purchased' : 'product_purchased',
				'product_category' => 'product_category',
				'order_count' => 'order_count_min', 'order_total' => 'order_total_min',
				'ordered_within_days' => 'not_within' === $operator ? 'not_ordered_within_days' : 'ordered_within_days',
				'event' => 'event_booked',
				'event_category' => 'event_category',
				'event_count' => 'event_count_min', 'event_within_days' => 'event_within_days',
			);
			return self::matches( $contact, array( 'type' => $map[ $field ], 'value' => $expected ), $context );
		}

		$actual = self::inline_contact_value( $contact, $field );
		if ( is_array( $actual ) ) {
			$contains = in_array( strtolower( $expected ), array_map( 'strtolower', array_map( 'strval', $actual ) ), true );
			return in_array( $operator, array( 'not_has', 'is_not' ), true ) ? ! $contains : $contains;
		}
		$actual = (string) $actual;
		if ( 'empty' === $operator ) return '' === trim( $actual );
		if ( 'not_empty' === $operator ) return '' !== trim( $actual );
		if ( in_array( $operator, array( 'gte', 'lte' ), true ) ) return 'gte' === $operator ? (float) $actual >= (float) $expected : (float) $actual <= (float) $expected;
		$left = function_exists( 'mb_strtolower' ) ? mb_strtolower( $actual ) : strtolower( $actual );
		$right = function_exists( 'mb_strtolower' ) ? mb_strtolower( $expected ) : strtolower( $expected );
		switch ( $operator ) {
			case 'equals': case 'is': return $left === $right;
			case 'not_equals': case 'is_not': return $left !== $right;
			case 'contains': return false !== strpos( $left, $right );
			case 'not_contains': return false === strpos( $left, $right );
			case 'starts_with': return 0 === strpos( $left, $right );
			case 'ends_with': return '' === $right || substr( $left, -strlen( $right ) ) === $right;
		}
		return false;
	}

	private static function matches_text( $actual, $expected, $operator ) {
		$left = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $actual ) ) : strtolower( trim( (string) $actual ) );
		$right = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $expected ) ) : strtolower( trim( (string) $expected ) );
		if ( 'equals' === $operator ) return $left === $right;
		if ( 'not_equals' === $operator ) return $left !== $right;
		if ( 'not_contains' === $operator ) return false === strpos( $left, $right );
		return false !== strpos( $left, $right );
	}

	private static function inline_numeric_value( $contact, $field, $context = array() ) {
		static $order_cache = array(); static $event_cache = array();
		$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
		$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id' ) ) );
		$key = $user_id ? 'u' . $user_id : 'e' . strtolower( $email );
		if ( 'event_count' === $field ) {
			if ( isset( $context['events'] ) ) return (float) count( self::context_events( $contact, $context ) );
			if ( ! isset( $event_cache[ $key ] ) ) $event_cache[ $key ] = apply_filters( 'contactpanda_contact_events', array(), $contact );
			return (float) count( $event_cache[ $key ] );
		}
		if ( ! function_exists( 'wc_get_orders' ) || ( ! $user_id && ! $email ) ) return 0.0;
		if ( isset( $context['orders'] ) ) {
			$orders = self::context_orders( $contact, $context );
			if ( 'order_count' === $field ) return (float) count( $orders );
			$total = 0.0; foreach ( $orders as $order ) $total += (float) $order->get_total(); return $total;
		}
		if ( ! isset( $order_cache[ $key ] ) ) {
			$args = array( 'limit' => -1, 'return' => 'objects', 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ) );
			if ( $user_id ) $args['customer_id'] = $user_id; else $args['billing_email'] = $email;
			$order_cache[ $key ] = wc_get_orders( $args );
		}
		if ( 'order_count' === $field ) return (float) count( $order_cache[ $key ] );
		$total = 0.0; foreach ( $order_cache[ $key ] as $order ) $total += (float) $order->get_total(); return $total;
	}

	private static function inline_contact_value( $contact, $field ) {
		if ( 0 === strpos( $field, 'custom__' ) && class_exists( 'ContactPanda_Custom_Fields' ) ) {
			return self::inline_meta( $contact, array( ContactPanda_Custom_Fields::meta_key( substr( $field, 8 ) ) ) );
		}
		$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id' ) ) );
		$user = $user_id ? get_user_by( 'id', $user_id ) : false;
		if ( ! $user ) {
			$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
			$user = $email ? get_user_by( 'email', $email ) : false;
			$user_id = $user ? (int) $user->ID : 0;
		}
		$meta_map = array(
			'title_prefix' => array( 'titel_vorangestellt', 'title_prefix' ), 'title_suffix' => array( 'titel_nachgestellt', 'title_suffix' ),
			'phone' => array( 'primary_phone', 'phone', 'phone_number', 'mobile_phone' ), 'address_1' => array( 'address_1', 'street_address_1', 'street_address' ),
			'address_2' => array( 'address_2', 'street_address_2' ), 'postcode' => array( 'postcode', 'postal_zip', 'postal_code' ),
			'city' => array( 'city' ), 'state' => array( 'state', 'region' ), 'country' => array( 'country' ),
			'position' => array( 'berufsbezeichnung', 'job_title', 'position' ), 'birthday' => array( 'birthday' ),
		);
		if ( isset( $meta_map[ $field ] ) ) {
			$value = self::inline_meta( $contact, $meta_map[ $field ] );
			$billing_fields = array( 'phone' => 'billing_phone', 'address_1' => 'billing_address_1', 'address_2' => 'billing_address_2', 'postcode' => 'billing_postcode', 'city' => 'billing_city', 'state' => 'billing_state', 'country' => 'billing_country' );
			return '' !== (string) $value || ! $user || ! isset( $billing_fields[ $field ] ) ? $value : get_user_meta( $user_id, $billing_fields[ $field ], true );
		}
		if ( 'company' === $field ) {
			static $company_map = null;
			if ( null === $company_map ) $company_map = ContactPanda_Plugin::extension_company_map();
			$contact_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID' ) ) );
			if ( isset( $company_map[ $contact_id ][0] ) ) return ContactPanda_Plugin::extension_value( $company_map[ $contact_id ][0], array( 'name', 'company_name', 'get_name' ) );
			$value = self::inline_meta( $contact, array( 'company_name', 'company', 'billing_company' ) );
			if ( '' === (string) $value && $user ) $value = get_user_meta( $user_id, 'billing_company', true );
			if ( '' !== (string) $value || ! ContactPanda_Store::standalone() ) return $value;
			global $wpdb; $tables = ContactPanda_Schema::table_names();
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT c.name FROM `{$tables['companies']}` c INNER JOIN `{$tables['company_contacts']}` cc ON cc.company_id=c.id WHERE cc.contact_id=%d AND c.deleted_at IS NULL ORDER BY cc.is_primary DESC,cc.id ASC LIMIT 1", $contact_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names.
		}
		if ( 'first_name' === $field ) return ContactPanda_Plugin::extension_value( $contact, array( 'first_name', 'get_first_name' ) );
		if ( 'last_name' === $field ) return ContactPanda_Plugin::extension_value( $contact, array( 'last_name', 'get_last_name' ) );
		if ( 'email' === $field ) return ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) );
		if ( 'created_at' === $field ) return ContactPanda_Plugin::extension_value( $contact, array( 'created_at', 'date_created', 'get_date_created' ) );
		if ( 'optin_status' === $field ) {
			$status = ContactPanda_Plugin::extension_optin_status( $contact );
			$map = array( 1 => 'unconfirmed', 2 => 'confirmed', 3 => 'unsubscribed', 4 => 'weekly', 5 => 'monthly', 6 => 'hard_bounce', 7 => 'spam', 8 => 'complained', 9 => 'blocked' );
			return isset( $map[ $status ] ) ? $map[ $status ] : (string) $status;
		}
		if ( 'marketable' === $field ) return ContactPanda_Plugin::extension_is_marketable( $contact ) ? 'yes' : 'no';
		if ( 'has_account' === $field ) return $user ? 'yes' : 'no';
		if ( 'user_role' === $field ) return $user ? (array) $user->roles : array();
		if ( 'uid' === $field ) {
			$value = self::inline_meta( $contact, array( 'vat_id', 'uid' ) );
			return $value ?: ( $user ? ( get_user_meta( $user_id, 'billing_vat', true ) ?: get_user_meta( $user_id, 'b2b_uid', true ) ) : '' );
		}
		if ( 'email_confirmed' === $field ) {
			$value = self::inline_meta( $contact, array( 'email_confirmed', 'contactpanda_email_verified' ) );
			if ( '' === (string) $value && $user ) $value = get_user_meta( $user_id, 'contactpanda_email_verified', true );
			if ( '' === (string) $value && ! ContactPanda_Store::standalone() ) {
				$value = in_array( ContactPanda_Plugin::extension_optin_status( $contact ), array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
			}
			return $value ? 'yes' : 'no';
		}
		if ( 'tag' === $field ) {
			$tags = is_array( $contact ) && isset( $contact['tags'] ) ? (array) $contact['tags'] : ( is_object( $contact ) && method_exists( $contact, 'get_tags' ) ? (array) $contact->get_tags() : array() );
			$result = array(); foreach ( $tags as $tag ) { $result[] = (string) absint( is_scalar( $tag ) ? $tag : ContactPanda_Plugin::extension_value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ) ); }
			return $result;
		}
		return '';
	}

	private static function inline_meta( $contact, $keys ) {
		foreach ( $keys as $key ) {
			if ( is_array( $contact ) && isset( $contact['meta'][ $key ] ) && '' !== (string) $contact['meta'][ $key ] ) return $contact['meta'][ $key ];
			if ( is_object( $contact ) && method_exists( $contact, 'get_meta' ) ) { $value = $contact->get_meta( $key ); if ( '' !== (string) $value ) return $value; }
		}
		return '';
	}

	private static function inline_fields() {
		static $cached = null;
		if ( null !== $cached ) return $cached;
		// Text filters should behave like the standard people search by default.
		// The editor selects the first available operator for a newly chosen field.
		$string = array( 'contains', 'equals', 'not_equals', 'not_contains', 'starts_with', 'ends_with', 'empty', 'not_empty' );
		$select = array( 'is', 'is_not' );
		$number = array( 'gte', 'lte', 'equals' );
		$yes_no = array( array( 'value' => 'yes', 'label' => __( 'Yes', 'contactpanda' ) ), array( 'value' => 'no', 'label' => __( 'No', 'contactpanda' ) ) );
		$groups = array(
			array( 'label' => __( 'Personal data', 'contactpanda' ), 'fields' => array(
				self::field_definition( 'first_name', __( 'First name', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'last_name', __( 'Last name', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'title_prefix', __( 'Prefix title', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'title_suffix', __( 'Suffix title', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'email', __( 'Email address', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'phone', __( 'Phone', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'position', __( 'Position / Job title', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'company', __( 'Company', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'uid', __( 'VAT ID', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'birthday', __( 'Birthday', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'created_at', __( 'Date created', 'contactpanda' ), 'text', $string ),
			) ),
			array( 'label' => __( 'Address', 'contactpanda' ), 'fields' => array(
				self::field_definition( 'address_1', __( 'Street and house number', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'address_2', __( 'Address line 2', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'postcode', __( 'Postcode', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'city', __( 'Location', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'state', __( 'State / region', 'contactpanda' ), 'text', $string ),
				self::field_definition( 'country', __( 'Country', 'contactpanda' ), 'select', $select, self::country_options() ),
			) ),
			array( 'label' => __( 'Status and assignment', 'contactpanda' ), 'fields' => array(
				self::field_definition( 'tag', __( 'Tags', 'contactpanda' ), 'select', array( 'has', 'not_has' ), self::tag_options() ),
				self::field_definition( 'user_role', __( 'WordPress role', 'contactpanda' ), 'select', array( 'has', 'not_has' ), self::role_options() ),
				self::field_definition( 'optin_status', __( 'Opt-in status', 'contactpanda' ), 'select', $select, self::optin_options() ),
				self::field_definition( 'marketable', __( 'Marketable', 'contactpanda' ), 'select', $select, $yes_no ),
				self::field_definition( 'email_confirmed', __( 'Email address confirmed', 'contactpanda' ), 'select', $select, $yes_no ),
				self::field_definition( 'has_account', __( 'WordPress/WooCommerce account available', 'contactpanda' ), 'select', $select, $yes_no ),
			) ),
		);
		if ( class_exists( 'ContactPanda_Custom_Fields' ) ) {
			$custom = array();
			foreach ( ContactPanda_Custom_Fields::definitions( 'contacts' ) as $definition ) {
				$type = 'text'; $operators = $string; $options = array();
				if ( 'number' === $definition['type'] ) { $type = 'number'; $operators = $number; }
				elseif ( 'select' === $definition['type'] ) { $type = 'select'; $operators = $select; foreach ( $definition['options'] as $option ) $options[] = array( 'value' => $option, 'label' => $option ); }
				elseif ( 'checkbox' === $definition['type'] ) { $type = 'select'; $operators = $select; $options = array( array( 'value' => '1', 'label' => __( 'Yes', 'contactpanda' ) ), array( 'value' => '0', 'label' => __( 'No', 'contactpanda' ) ) ); }
				$custom[] = self::field_definition( 'custom__' . $definition['key'], $definition['label'], $type, $operators, $options );
			}
			if ( $custom ) $groups[] = array( 'label' => __( 'Custom fields', 'contactpanda' ), 'fields' => $custom );
		}
		$type_options = self::type_options();
		if ( function_exists( 'wc_get_orders' ) ) {
			$groups[] = array( 'label' => __( 'WooCommerce', 'contactpanda' ), 'fields' => array(
				self::field_definition( 'has_orders', __( 'Has placed an order', 'contactpanda' ), 'select', $select, $yes_no ),
				self::field_definition( 'product', __( 'Product', 'contactpanda' ), 'select', array( 'purchased', 'not_purchased' ), $type_options['product_purchased'] ?? array() ),
				self::field_definition( 'product_category', __( 'Product category', 'contactpanda' ), 'select', array( 'purchased' ), $type_options['product_category'] ?? array() ),
				self::field_definition( 'order_count', __( 'Number of orders', 'contactpanda' ), 'number', $number ),
				self::field_definition( 'order_total', __( 'Order total', 'contactpanda' ), 'number', $number ),
				self::field_definition( 'ordered_within_days', __( 'Last order', 'contactpanda' ), 'number', array( 'within', 'not_within' ) ),
				self::field_definition( 'order_number', __( 'Order number', 'contactpanda' ), 'text', array( 'equals', 'contains' ) ),
				self::field_definition( 'invoice_number', __( 'Invoice number', 'contactpanda' ), 'text', array( 'equals', 'contains' ) ),
			) );
		}
		if ( post_type_exists( 'tribe_events' ) ) {
			$groups[] = array( 'label' => __( 'Events', 'contactpanda' ), 'fields' => array(
				self::field_definition( 'has_events', __( 'Has an event registration', 'contactpanda' ), 'select', $select, $yes_no ),
				self::field_definition( 'event', __( 'Event', 'contactpanda' ), 'select', array( 'is' ), $type_options['event_booked'] ?? array() ),
				self::field_definition( 'event_category', __( 'Event category', 'contactpanda' ), 'select', array( 'is' ), $type_options['event_category'] ?? array() ),
				self::field_definition( 'event_count', __( 'Number of events', 'contactpanda' ), 'number', $number ),
				self::field_definition( 'event_within_days', __( 'Event within the last days', 'contactpanda' ), 'number', array( 'within' ) ),
			) );
		}
		$cached = $groups;
		return $cached;
	}

	private static function field_definition( $key, $label, $type, $operators, $options = array() ) {
		return array( 'key' => $key, 'label' => $label, 'type' => $type, 'operators' => $operators, 'options' => array_values( $options ) );
	}

	private static function operator_labels() {
		return array(
			'equals' => __( 'Equals', 'contactpanda' ), 'not_equals' => __( 'Does not equal', 'contactpanda' ),
			'contains' => __( 'Contains', 'contactpanda' ), 'not_contains' => __( 'Does not contain', 'contactpanda' ),
			'starts_with' => __( 'Starts with', 'contactpanda' ), 'ends_with' => __( 'Ends with', 'contactpanda' ),
			'empty' => __( 'Is empty', 'contactpanda' ), 'not_empty' => __( 'Is not empty', 'contactpanda' ),
			'is' => __( 'Is', 'contactpanda' ), 'is_not' => __( 'Is not', 'contactpanda' ), 'has' => __( 'Has', 'contactpanda' ), 'not_has' => __( 'Does not have', 'contactpanda' ),
			'gte' => __( 'Is at least', 'contactpanda' ), 'lte' => __( 'Is at most', 'contactpanda' ),
			'purchased' => __( 'Was purchased', 'contactpanda' ), 'not_purchased' => __( 'Was not purchased', 'contactpanda' ),
			'within' => __( 'Within the last days', 'contactpanda' ), 'not_within' => __( 'Not within the last days', 'contactpanda' ),
			'booked' => __( 'Was booked', 'contactpanda' ), 'attended' => __( 'Was attended', 'contactpanda' ), 'not_attended' => __( 'Booked but not attended', 'contactpanda' ),
		);
	}

	private static function tag_options() {
		$tags = method_exists( 'ContactPanda_Plugin', 'extension_tags' ) ? ContactPanda_Plugin::extension_tags() : array();
		$options = array();
		foreach ( $tags as $tag ) {
			$id = absint( ContactPanda_Plugin::extension_value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ) );
			$name = ContactPanda_Plugin::extension_value( $tag, array( 'name', 'tag_name', 'get_name' ) );
			if ( $id && $name ) $options[] = array( 'value' => (string) $id, 'label' => $name );
		}
		return $options;
	}

	private static function role_options() {
		$roles = wp_roles()->roles; $options = array();
		foreach ( $roles as $key => $role ) { $options[] = array( 'value' => $key, 'label' => translate_user_role( $role['name'] ) ); }
		return $options;
	}

	private static function country_options() {
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : array( 'AT' => __( 'Austria', 'contactpanda' ), 'DE' => __( 'Germany', 'contactpanda' ), 'CH' => __( 'Switzerland', 'contactpanda' ), 'IT' => __( 'Italy', 'contactpanda' ), 'FR' => __( 'France', 'contactpanda' ) );
		$options = array(); foreach ( $countries as $code => $label ) { $options[] = array( 'value' => $code, 'label' => $label ); } return $options;
	}

	private static function optin_options() {
		return array(
			array( 'value' => 'unconfirmed', 'label' => __( 'Unconfirmed', 'contactpanda' ) ), array( 'value' => 'confirmed', 'label' => __( 'Confirmed', 'contactpanda' ) ),
			array( 'value' => 'weekly', 'label' => __( 'Weekly', 'contactpanda' ) ), array( 'value' => 'monthly', 'label' => __( 'Monthly', 'contactpanda' ) ),
			array( 'value' => 'unsubscribed', 'label' => __( 'Unsubscribed', 'contactpanda' ) ), array( 'value' => 'hard_bounce', 'label' => __( 'Bounced', 'contactpanda' ) ),
			array( 'value' => 'spam', 'label' => __( 'Spam', 'contactpanda' ) ), array( 'value' => 'complained', 'label' => __( 'Complaint', 'contactpanda' ) ),
			array( 'value' => 'blocked', 'label' => __( 'Blocked', 'contactpanda' ) ),
		);
	}

	/**
	 * Build expensive WooCommerce and Event Tickets relationships once for the
	 * complete contact set. Search results must never depend on the visible page.
	 */
	private static function filter_context( $contacts, $rules ) {
		$needs_orders = false;
		$needs_events = false;
		$product_ids = array();
		foreach ( (array) $rules as $rule ) {
			$field = sanitize_key( $rule['field'] ?? '' );
			$type = sanitize_key( $rule['type'] ?? '' );
			if ( 'product' === $field || in_array( $type, array( 'product_purchased', 'product_not_purchased' ), true ) ) {
				$product_ids[] = absint( $rule['value'] ?? 0 );
			} elseif ( in_array( $field, array( 'product_category', 'order_count', 'order_total', 'ordered_within_days', 'order_number', 'invoice_number' ), true ) || 0 === strpos( $type, 'order_' ) || 0 === strpos( $type, 'ordered_' ) || 0 === strpos( $type, 'not_ordered_' ) || 'product_category' === $type ) {
				$needs_orders = true;
			}
			if ( in_array( $field, array( 'event', 'event_category', 'event_count', 'event_within_days' ), true ) || 0 === strpos( $type, 'event_' ) ) {
				$needs_events = true;
			}
		}

		$context = array();
		if ( $product_ids ) {
			$context['products'] = self::product_contact_map( $contacts, array_filter( array_unique( $product_ids ) ) );
		}
		if ( $needs_orders ) {
			$context['orders'] = self::contact_order_map( $contacts );
		}
		if ( $needs_events ) {
			$context['events'] = apply_filters( 'contactpanda_contact_event_map', array(), $contacts );
		}
		return $context;
	}

	/** Resolve exact product purchases without loading every WooCommerce order. */
	private static function product_contact_map( $contacts, $product_ids ) {
		global $wpdb;
		$result = array();
		$user_map = array();
		$email_map = array();
		foreach ( (array) $product_ids as $product_id ) $result[ absint( $product_id ) ] = array();
		foreach ( (array) $contacts as $contact ) {
			$contact_id = self::context_contact_id( $contact );
			if ( ! $contact_id ) continue;
			$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id' ) ) );
			$email = strtolower( sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ) );
			$user = $user_id ? get_user_by( 'id', $user_id ) : ( $email ? get_user_by( 'email', $email ) : false );
			if ( $user ) {
				$user_id = absint( $user->ID );
				$user_map[ $user_id ][] = $contact_id;
				$email_map[ strtolower( sanitize_email( $user->user_email ) ) ][] = $contact_id;
				$email_map[ strtolower( sanitize_email( get_user_meta( $user_id, 'billing_email', true ) ) ) ][] = $contact_id;
			}
			if ( $email ) $email_map[ $email ][] = $contact_id;
		}
		unset( $email_map[''] );
		if ( ! function_exists( 'wc_get_order' ) || ( ! $user_map && ! $email_map ) ) return $result;
		$order_items = $wpdb->prefix . 'woocommerce_order_items';
		$order_meta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		foreach ( array_keys( $result ) as $product_id ) {
			$order_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT DISTINCT oi.order_id FROM `{$order_items}` oi INNER JOIN `{$order_meta}` oim ON oim.order_item_id=oi.order_item_id WHERE oi.order_item_type='line_item' AND oim.meta_key IN ('_product_id','_variation_id') AND CAST(oim.meta_value AS UNSIGNED)=%d",
				$product_id
			) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WooCommerce core tables.
			foreach ( array_map( 'absint', $order_ids ) as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order || in_array( 'wc-' . $order->get_status(), array( 'wc-cancelled', 'wc-failed', 'wc-checkout-draft' ), true ) ) continue;
				$contact_ids = array();
				$customer_id = absint( $order->get_customer_id() );
				$billing_email = strtolower( sanitize_email( $order->get_billing_email() ) );
				if ( $customer_id && isset( $user_map[ $customer_id ] ) ) $contact_ids = array_merge( $contact_ids, $user_map[ $customer_id ] );
				if ( $billing_email && isset( $email_map[ $billing_email ] ) ) $contact_ids = array_merge( $contact_ids, $email_map[ $billing_email ] );
				foreach ( array_unique( array_map( 'absint', $contact_ids ) ) as $contact_id ) $result[ $product_id ][ $contact_id ] = true;
			}
		}
		return $result;
	}

	private static function context_contact_id( $contact ) {
		return absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) );
	}

	private static function context_orders( $contact, $context ) {
		$contact_id = self::context_contact_id( $contact );
		if ( isset( $context['orders'] ) ) {
			return $contact_id && isset( $context['orders'][ $contact_id ] ) ? $context['orders'][ $contact_id ] : array();
		}
		return self::orders_for_contact( $contact );
	}

	private static function context_events( $contact, $context ) {
		$contact_id = self::context_contact_id( $contact );
		if ( isset( $context['events'] ) ) {
			return $contact_id && isset( $context['events'][ $contact_id ] ) ? $context['events'][ $contact_id ] : array();
		}
		return apply_filters( 'contactpanda_contact_events', array(), $contact );
	}

	private static function contact_order_map( $contacts ) {
		$map = array();
		$user_map = array();
		$email_map = array();
		foreach ( (array) $contacts as $contact ) {
			$contact_id = self::context_contact_id( $contact );
			if ( ! $contact_id ) continue;
			$map[ $contact_id ] = array();
			$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id' ) ) );
			$email = strtolower( sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ) );
			$user = $user_id ? get_user_by( 'id', $user_id ) : ( $email ? get_user_by( 'email', $email ) : false );
			if ( $user ) {
				$user_id = (int) $user->ID;
				$user_map[ $user_id ][] = $contact_id;
			}
			$emails = array( $email );
			if ( $user ) {
				$emails[] = strtolower( sanitize_email( $user->user_email ) );
				$emails[] = strtolower( sanitize_email( get_user_meta( $user_id, 'billing_email', true ) ) );
			}
			foreach ( array_filter( array_unique( $emails ) ) as $identity_email ) {
				$email_map[ $identity_email ][] = $contact_id;
			}
		}
		if ( ! function_exists( 'wc_get_orders' ) || ( ! $user_map && ! $email_map ) ) return $map;

		$args = array(
			'limit' => 250,
			'paginate' => true,
			'return' => 'objects',
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => self::search_order_statuses(),
		);
		$page = 1;
		do {
			$args['page'] = $page;
			$args['paged'] = $page;
			try {
				$result = wc_get_orders( $args );
			} catch ( Throwable $e ) {
				break;
			}
			$orders = is_object( $result ) && isset( $result->orders ) ? (array) $result->orders : ( is_array( $result ) ? $result : array() );
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;
			foreach ( $orders as $order ) {
				$contact_ids = array();
				$customer_id = method_exists( $order, 'get_customer_id' ) ? absint( $order->get_customer_id() ) : 0;
				$billing_email = method_exists( $order, 'get_billing_email' ) ? strtolower( sanitize_email( $order->get_billing_email() ) ) : '';
				if ( $customer_id && isset( $user_map[ $customer_id ] ) ) $contact_ids = array_merge( $contact_ids, $user_map[ $customer_id ] );
				if ( $billing_email && isset( $email_map[ $billing_email ] ) ) $contact_ids = array_merge( $contact_ids, $email_map[ $billing_email ] );
				foreach ( array_unique( array_map( 'absint', $contact_ids ) ) as $contact_id ) {
					$map[ $contact_id ][ $order->get_id() ] = $order;
				}
			}
			$page++;
		} while ( $page <= $max_pages );
		foreach ( $map as &$orders ) $orders = array_values( $orders );
		unset( $orders );
		return $map;
	}

	private static function search_order_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) return array();
		return array_values( array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-checkout-draft' ) ) );
	}

	private static function matches( $contact, $rule, $context = array() ) {
		static $order_cache = array();
		static $event_cache = array();
		$type = isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : '';
		$raw_value = isset( $rule['value'] ) ? (string) $rule['value'] : '';
		$value = absint( $raw_value );
		$email = strtolower( trim( sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ) ) );
		$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id' ) ) );
		$cache_key = $user_id ? 'u' . $user_id : 'e' . strtolower( $email );
		if ( in_array( $type, array( 'product_purchased', 'product_not_purchased' ), true ) && isset( $context['products'][ $value ] ) ) {
			$found = isset( $context['products'][ $value ][ self::context_contact_id( $contact ) ] );
			return 'product_not_purchased' === $type ? ! $found : $found;
		}
		if ( 0 === strpos( $type, 'event_' ) ) {
			if ( isset( $context['events'] ) ) {
				$events = self::context_events( $contact, $context );
			} else {
				if ( 'event_booked' === $type && apply_filters( 'contactpanda_contact_has_event', false, $contact, $value ) ) return true;
				if ( ! isset( $event_cache[ $cache_key ] ) ) $event_cache[ $cache_key ] = apply_filters( 'contactpanda_contact_events', array(), $contact );
				$events = $event_cache[ $cache_key ];
			}
			if ( 'event_count_min' === $type ) { return count( $events ) >= $value; }
			if ( 'event_category' === $type ) {
				foreach ( $events as $event ) { if ( has_term( $value, 'tribe_events_cat', absint( $event['event_id'] ?? 0 ) ) ) { return true; } }
				return false;
			}
			if ( 'event_within_days' === $type ) {
				$cutoff = time() - ( DAY_IN_SECONDS * $value );
				foreach ( $events as $event ) { if ( absint( $event['timestamp'] ?? 0 ) >= $cutoff ) { return true; } }
				return false;
			}
			foreach ( $events as $event ) {
				if ( $value === absint( $event['event_id'] ?? 0 ) ) {
					// Event Tickets associates people through attendee email addresses.
					// For contact filtering, the booking/check-in distinction is irrelevant.
					return true;
				}
			}
			return false;
		}
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		if ( ! $user_id && ! $email ) return false;
		if ( isset( $context['orders'] ) ) {
			$orders = self::context_orders( $contact, $context );
		} else {
			if ( ! isset( $order_cache[ $cache_key ] ) ) $order_cache[ $cache_key ] = self::orders_for_contact( $contact );
			$orders = $order_cache[ $cache_key ];
		}
		if ( 'order_count_min' === $type ) {
			return count( $orders ) >= $value;
		}
		if ( 'order_total_min' === $type ) {
			$total = 0;
			foreach ( $orders as $order ) { $total += (float) $order->get_total(); }
			return $total >= (float) $raw_value;
		}
		if ( in_array( $type, array( 'ordered_within_days', 'not_ordered_within_days' ), true ) ) {
			$cutoff = time() - ( DAY_IN_SECONDS * $value ); $recent = false;
			foreach ( $orders as $order ) { $created = $order->get_date_created(); if ( $created && $created->getTimestamp() >= $cutoff ) { $recent = true; break; } }
			return 'not_ordered_within_days' === $type ? ! $recent : $recent;
		}
		$found = false;
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = absint( $item->get_product_id() );
				$variation_id = method_exists( $item, 'get_variation_id' ) ? absint( $item->get_variation_id() ) : 0;
				if ( 'product_category' === $type && has_term( $value, 'product_cat', $product_id ) ) { $found = true; }
				if ( in_array( $type, array( 'product_purchased', 'product_not_purchased' ), true ) && in_array( $value, array_filter( array( $product_id, $variation_id ) ), true ) ) { $found = true; }
			}
		}
		return 'product_not_purchased' === $type ? ! $found : $found;
	}

	private static function orders_for_contact( $contact ) {
		if ( ! function_exists( 'wc_get_orders' ) ) return array();
		$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id' ) ) );
		$email = strtolower( trim( sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ) ) );
		$user = $user_id ? get_user_by( 'id', $user_id ) : ( $email ? get_user_by( 'email', $email ) : false );
		if ( $user ) $user_id = (int) $user->ID;
		$orders = array();
		$base = array( 'limit' => -1, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC' );
		$emails = array_filter( array_unique( array( $email, $user ? strtolower( sanitize_email( $user->user_email ) ) : '', $user ? strtolower( sanitize_email( get_user_meta( $user_id, 'billing_email', true ) ) ) : '' ) ) );
		$identities = $user_id ? array( array( 'customer_id' => $user_id ) ) : array(); foreach ( $emails as $identity_email ) $identities[] = array( 'billing_email' => $identity_email );
		foreach ( $identities as $identity ) {
			try {
				foreach ( (array) wc_get_orders( array_merge( $base, $identity ) ) as $order ) $orders[ $order->get_id() ] = $order;
			} catch ( Throwable $e ) {}
		}
		return array_values( $orders );
	}

	private static function invoice_number( $order ) {
		foreach ( array( '_wgm_invoice_number', 'wgm_invoice_number', '_wp_wc_invoice_pdf_invoice_number', 'wp_wc_invoice_pdf_invoice_number', '_wcpdf_formatted_invoice_number', '_wcpdf_invoice_number', '_invoice_number', 'invoice_number' ) as $key ) {
			$value = $order->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) return trim( (string) $value );
		}
		foreach ( (array) $order->get_meta_data() as $meta ) {
			$key = method_exists( $meta, 'get_data' ) ? (string) ( $meta->get_data()['key'] ?? '' ) : '';
			$value = method_exists( $meta, 'get_data' ) ? ( $meta->get_data()['value'] ?? '' ) : '';
			if ( ( false !== stripos( $key, 'invoice' ) || false !== stripos( $key, 'rechnung' ) ) && false !== stripos( $key, 'number' ) && is_scalar( $value ) && '' !== trim( (string) $value ) ) return trim( (string) $value );
		}
		return '';
	}

	public static function render( $embedded = false ) {
		if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); }
		ContactPanda_Newsletter_Schema::install();
		$segments = self::all();
		$new_url = admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=saved_searches&search_entity=contacts#contactpanda-search-editor' );
		if ( ! $embedded ) echo '<div class="wrap contactpanda-wrap"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'Saved searches', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Create and manage searches independently from the people and company lists.', 'contactpanda' ) . '</p></div><a class="button button-primary contactpanda-heading-action" href="' . esc_url( $new_url ) . '">' . esc_html__( 'Add new search', 'contactpanda' ) . '</a></div>';
		else echo '<p><a class="button button-primary" href="' . esc_url( $new_url ) . '">' . esc_html__( 'Add new search', 'contactpanda' ) . '</a></p>';
		$edit_id = isset( $_GET['segment_id'] ) ? absint( $_GET['segment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit = $edit_id ? self::get( $edit_id ) : array();
		$entity = 'companies' === ( $edit['entity_type'] ?? sanitize_key( wp_unslash( $_GET['search_entity'] ?? 'contacts' ) ) ) ? 'companies' : 'contacts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor state.
		$rules = $edit ? json_decode( $edit['rules_json'], true ) : array();
		$rules = is_array( $rules ) && $rules ? array_slice( $rules, 0, 20 ) : array( array( 'type' => '', 'value' => '' ) );

		if ( ! $embedded ) echo '<div class="contactpanda-segment-management">';
		echo '<div id="contactpanda-search-editor" class="contactpanda-segment-modal"><div class="contactpanda-segment-modal-panel"><div class="contactpanda-merge-header"><h2>' . esc_html( $edit_id ? __( 'Edit saved search', 'contactpanda' ) : __( 'Add new search', 'contactpanda' ) ) . '</h2><a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=saved_searches' ) ) . '"><span class="dashicons dashicons-no-alt"></span></a></div>';
		echo '<nav class="nav-tab-wrapper contactpanda-search-entity-tabs"><a class="nav-tab' . ( 'contacts' === $entity ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'search_entity', 'contacts', $new_url ) ) . '">' . esc_html__( 'People search', 'contactpanda' ) . '</a><a class="nav-tab' . ( 'companies' === $entity ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'search_entity', 'companies', $new_url ) ) . '">' . esc_html__( 'Company search', 'contactpanda' ) . '</a></nav>';
		echo '<form class="contactpanda-card contactpanda-segment-builder" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_segment"><input type="hidden" name="segment_id" value="' . esc_attr( $edit_id ) . '"><input type="hidden" name="entity_type" value="' . esc_attr( $entity ) . '">';
		wp_nonce_field( 'contactpanda_save_segment' );
		$relation_legend = 'companies' === $entity ? __( 'Companies must match', 'contactpanda' ) : __( 'People must match', 'contactpanda' );
		echo '<div class="contactpanda-segment-header"><label><span>' . esc_html__( 'Name of this search', 'contactpanda' ) . '</span><input class="regular-text" required name="name" value="' . esc_attr( $edit['name'] ?? '' ) . '" placeholder="' . esc_attr__( 'e.g. Coffee customers without an event booking', 'contactpanda' ) . '"></label><fieldset><legend>' . esc_html( $relation_legend ) . '</legend><label><input type="radio" name="relation_mode" value="and"' . checked( $edit['relation_mode'] ?? 'and', 'and', false ) . '> ' . esc_html__( 'all conditions', 'contactpanda' ) . '</label><label><input type="radio" name="relation_mode" value="or"' . checked( $edit['relation_mode'] ?? '', 'or', false ) . '> ' . esc_html__( 'at least one condition', 'contactpanda' ) . '</label></fieldset></div>';
		echo '<div class="contactpanda-segment-rules">';
		foreach ( $rules as $index => $rule ) { self::render_rule( $index, $rule, $entity ); }
		echo '</div><button type="button" class="button contactpanda-add-segment-rule"><span class="dashicons dashicons-plus-alt2"></span>' . esc_html__( 'Add condition', 'contactpanda' ) . '</button>';
		echo '<template class="contactpanda-segment-rule-template">'; self::render_rule( 999, array( 'type' => '', 'value' => '' ), $entity ); echo '</template>';
		echo '<div class="contactpanda-segment-actions"><button type="submit" class="button button-primary" name="run_search" value="1">' . esc_html__( 'Save and show results', 'contactpanda' ) . '</button><button type="submit" class="button">' . esc_html__( 'Save search', 'contactpanda' ) . '</button></div></form></div></div>';

		echo '<section class="contactpanda-card contactpanda-saved-searches"><div class="contactpanda-toolbar"><h2>' . esc_html__( 'Saved searches', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( count( self::all() ) ) . '</span></div><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'contactpanda' ) . '</th><th>' . esc_html__( 'Type', 'contactpanda' ) . '</th><th>' . esc_html__( 'Combination', 'contactpanda' ) . '</th><th>' . esc_html__( 'Conditions', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( self::all() as $segment ) {
			$count = count( (array) json_decode( $segment['rules_json'], true ) );
			$segment_entity = 'companies' === ( $segment['entity_type'] ?? 'contacts' ) ? 'companies' : 'contacts';
			$edit_url = admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=saved_searches&segment_id=' . absint( $segment['id'] ) . '#contactpanda-search-editor' );
			$show_url = admin_url( 'admin.php?page=' . ( 'companies' === $segment_entity ? 'contactpanda-firmen' : 'contactpanda-people' ) . '&segment=' . absint( $segment['id'] ) );
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_delete_segment&segment_id=' . absint( $segment['id'] ) ), 'contactpanda_delete_segment' );
			echo '<tr><td><strong>' . esc_html( $segment['name'] ) . '</strong></td><td>' . esc_html( 'companies' === $segment_entity ? __( 'Companies', 'contactpanda' ) : __( 'People', 'contactpanda' ) ) . '</td><td>' . esc_html( 'or' === $segment['relation_mode'] ? __( 'Any', 'contactpanda' ) : __( 'All', 'contactpanda' ) ) . '</td><td>' . esc_html( $count ) . '</td><td><a href="' . esc_url( $show_url ) . '">' . esc_html__( 'Show results', 'contactpanda' ) . '</a> · <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'View / edit', 'contactpanda' ) . '</a> · <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this saved search?', 'contactpanda' ) ) ) . ');">' . esc_html__( 'Delete', 'contactpanda' ) . '</a></td></tr>';
		}
		echo '</tbody></table></section>' . ( $embedded ? '' : '</div></div>' );
	}

	private static function render_rule( $index, $rule, $entity = 'contacts' ) {
		$type = sanitize_key( $rule['type'] ?? '' );
		if ( in_array( $type, array( 'event_attended', 'event_not_attended' ), true ) ) $type = 'event_booked';
		$value = $rule['value'] ?? '';
		echo '<div class="contactpanda-segment-rule" data-index="' . esc_attr( $index ) . '"><span class="contactpanda-segment-rule-number">' . esc_html( $index + 1 ) . '</span><label><span>' . esc_html__( 'Condition', 'contactpanda' ) . '</span><select class="contactpanda-segment-type" name="rules[' . esc_attr( $index ) . '][type]" required><option value="">' . esc_html__( 'Choose a condition', 'contactpanda' ) . '</option>';
		foreach ( self::rule_groups( $entity ) as $group => $types ) {
			echo '<optgroup label="' . esc_attr( $group ) . '">';
			foreach ( $types as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( $type, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
			echo '</optgroup>';
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
		echo '</select></label><label><span>' . esc_html__( 'Value', 'contactpanda' ) . '</span><span class="contactpanda-segment-value">' . self::value_control( $type, $index, $value ) . '</span></label><button type="button" class="button-link contactpanda-remove-segment-rule" aria-label="' . esc_attr__( 'Remove condition', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt"></span></button></div>';
	}

	private static function rule_groups( $entity = 'contacts' ) {
		if ( 'companies' === $entity ) {
			return array( __( 'Company data', 'contactpanda' ) => array(
				'company_name_contains' => __( 'Company name contains', 'contactpanda' ),
				'company_email_contains' => __( 'Email contains', 'contactpanda' ),
				'company_phone_contains' => __( 'Phone contains', 'contactpanda' ),
				'company_city_contains' => __( 'City contains', 'contactpanda' ),
				'company_country_is' => __( 'Country is', 'contactpanda' ),
			) );
		}
		$groups = array();
		if ( function_exists( 'wc_get_orders' ) ) {
			$groups[ __( 'WooCommerce', 'contactpanda' ) ] = array(
				'product_purchased' => __( 'Purchased product', 'contactpanda' ), 'product_not_purchased' => __( 'Did not purchase product', 'contactpanda' ), 'product_category' => __( 'Purchased from product category', 'contactpanda' ), 'order_count_min' => __( 'Number of orders is at least', 'contactpanda' ), 'order_total_min' => __( 'Order total is at least', 'contactpanda' ), 'ordered_within_days' => __( 'Ordered within the last days', 'contactpanda' ), 'not_ordered_within_days' => __( 'Did not order within the last days', 'contactpanda' ),
			);
		}
		if ( post_type_exists( 'tribe_events' ) ) {
			$groups[ __( 'Events', 'contactpanda' ) ] = array(
				'event_booked' => __( 'Event', 'contactpanda' ), 'event_count_min' => __( 'Number of events is at least', 'contactpanda' ), 'event_category' => __( 'Event category', 'contactpanda' ), 'event_within_days' => __( 'Event within the last days', 'contactpanda' ),
			);
		}
		return $groups;
	}

	private static function value_control( $type, $index, $value ) {
		$options = self::type_options();
		$name = 'rules[' . absint( $index ) . '][value]';
		if ( isset( $options[ $type ] ) ) {
			$html = '<select required name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Please select', 'contactpanda' ) . '</option>';
			foreach ( $options[ $type ] as $option ) { $html .= '<option value="' . esc_attr( $option['value'] ) . '"' . selected( (string) $value, (string) $option['value'], false ) . '>' . esc_html( $option['label'] ) . '</option>'; }
			return $html . '</select>';
		}
		if ( 0 === strpos( $type, 'company_' ) ) return '<input required type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'Value', 'contactpanda' ) . '">';
		$placeholders = self::value_labels();
		return '<input required type="number" min="0" step="' . ( 'order_total_min' === $type ? '0.01' : '1' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholders[ $type ] ?? __( 'Value', 'contactpanda' ) ) . '">';
	}

	private static function type_options() {
		static $cached = null;
		if ( null !== $cached ) {
			return $cached;
		}
		$options = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products( array( 'limit' => 250, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC', 'return' => 'objects' ) );
			$product_options = array(); foreach ( $products as $product ) { $product_options[] = array( 'value' => $product->get_id(), 'label' => $product->get_name() ); }
			$options['product_purchased'] = $product_options; $options['product_not_purchased'] = $product_options;
			$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
			$options['product_category'] = is_wp_error( $terms ) ? array() : array_map( static function ( $term ) { return array( 'value' => $term->term_id, 'label' => $term->name ); }, $terms );
		}
		if ( post_type_exists( 'tribe_events' ) ) {
			$events = get_posts( array( 'post_type' => 'tribe_events', 'post_status' => 'publish', 'posts_per_page' => 250, 'orderby' => 'date', 'order' => 'DESC' ) );
			$event_options = array(); foreach ( $events as $event ) { $event_options[] = array( 'value' => $event->ID, 'label' => get_the_title( $event ) ); }
			$options['event_booked'] = $event_options;
			$terms = get_terms( array( 'taxonomy' => 'tribe_events_cat', 'hide_empty' => false ) );
			$options['event_category'] = is_wp_error( $terms ) ? array() : array_map( static function ( $term ) { return array( 'value' => $term->term_id, 'label' => $term->name ); }, $terms );
		}
		$cached = $options;
		return $cached;
	}

	private static function value_labels() {
		return array( 'order_count_min' => __( 'Orders', 'contactpanda' ), 'order_total_min' => __( 'Amount', 'contactpanda' ), 'ordered_within_days' => __( 'Days', 'contactpanda' ), 'not_ordered_within_days' => __( 'Days', 'contactpanda' ), 'event_count_min' => __( 'Bookings', 'contactpanda' ), 'event_within_days' => __( 'Days', 'contactpanda' ) );
	}

	public static function editor_config() {
		return array( 'typeOptions' => self::type_options(), 'valueLabels' => self::value_labels(), 'choose' => __( 'Please select', 'contactpanda' ), 'number' => __( 'Value', 'contactpanda' ) );
	}

	public static function save() {
		if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); }
		check_admin_referer( 'contactpanda_save_segment' );
		global $wpdb;
		ContactPanda_Newsletter_Schema::install();
		$id = isset( $_POST['segment_id'] ) ? absint( $_POST['segment_id'] ) : 0;
		$entity = isset( $_POST['entity_type'] ) && 'companies' === sanitize_key( wp_unslash( $_POST['entity_type'] ) ) ? 'companies' : 'contacts';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$mode = isset( $_POST['relation_mode'] ) && 'or' === sanitize_key( wp_unslash( $_POST['relation_mode'] ) ) ? 'or' : 'and';
		$rules = array();
		$allowed_types = array();
		foreach ( self::rule_groups( $entity ) as $types ) { $allowed_types = array_merge( $allowed_types, array_keys( $types ) ); }
		foreach ( (array) ( $_POST['rules'] ?? array() ) as $rule ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$type = sanitize_key( wp_unslash( $rule['type'] ?? '' ) );
			$value = 'companies' === $entity ? sanitize_text_field( wp_unslash( $rule['value'] ?? '' ) ) : (float) wp_unslash( $rule['value'] ?? 0 );
			if ( in_array( $type, $allowed_types, true ) && ( 'companies' === $entity ? '' !== $value : $value >= 0 ) ) { $rules[] = array( 'type' => $type, 'value' => $value ); }
		}
		if ( '' === $name || ! $rules ) {
			wp_die( esc_html__( 'Please enter a name and at least one complete condition.', 'contactpanda' ), esc_html__( 'Search could not be saved', 'contactpanda' ), array( 'back_link' => true ) );
		}
		$data = array( 'name' => $name, 'entity_type' => $entity, 'relation_mode' => $mode, 'rules_json' => wp_json_encode( $rules ), 'updated_at' => current_time( 'mysql' ) );
		$table = ContactPanda_Newsletter_Schema::table_names()['segments'];
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
		} else {
			$data['created_by'] = get_current_user_id(); $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $table, $data ); $id = (int) $wpdb->insert_id;
		}
		$redirect = isset( $_POST['run_search'] ) ? admin_url( 'admin.php?page=' . ( 'companies' === $entity ? 'contactpanda-firmen' : 'contactpanda-people' ) . '&segment=' . absint( $id ) ) : admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=saved_searches' );
		wp_safe_redirect( $redirect ); exit;
	}

	public static function delete() {
		if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); }
		check_admin_referer( 'contactpanda_delete_segment' );
		global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['segments'];
		$wpdb->delete( $table, array( 'id' => absint( $_GET['segment_id'] ?? 0 ) ) );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=saved_searches' ) ); exit;
	}

	private static function capability() { return class_exists( 'ContactPanda_Capabilities' ) ? ContactPanda_Capabilities::for_area( 'marketing' ) : ( class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' ); }
}
