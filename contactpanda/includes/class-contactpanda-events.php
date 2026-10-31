<?php

defined( 'ABSPATH' ) || exit;

/**
 * Read-only event adapters included with ContactPanda Basic.
 *
 * Source plugins remain authoritative. This class never writes to The Events
 * Calendar, Event Tickets, or Amelia tables.
 */
final class ContactPanda_Events {
	const SOURCE_OPTION = 'contactpanda_event_source';

	public static function boot() {
		add_filter( 'contactpanda_events_available', array( __CLASS__, 'available' ), 100 );
		// Run after older Plus releases so Basic 0.4+ remains the single source
		// selector and two active event plugins can never be mixed accidentally.
		add_filter( 'contactpanda_event_summaries', array( __CLASS__, 'event_summaries' ), 100, 2 );
		add_filter( 'contactpanda_contact_events', array( __CLASS__, 'contact_events' ), 100, 2 );
		add_filter( 'contactpanda_contact_event_map', array( __CLASS__, 'contact_event_map' ), 100, 2 );
		add_filter( 'contactpanda_contact_has_event', array( __CLASS__, 'contact_has_event' ), 100, 3 );
		add_filter( 'contactpanda_account_attended_event_count', array( __CLASS__, 'account_event_count' ), 100, 2 );
		add_action( 'contactpanda_render_integration_section_woocommerce', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'source_notice' ) );
		add_action( 'admin_post_contactpanda_event_source', array( __CLASS__, 'save_source' ) );
		add_action( 'wp_ajax_contactpanda_preview_event', array( __CLASS__, 'preview_event' ) );
	}

	public static function sources() {
		return array_filter(
			array(
				'tec'    => self::tec_available(),
				'amelia' => self::amelia_available(),
			)
		);
	}

	/**
	 * Backward-compatible labelled source list used by earlier Plus releases.
	 *
	 * Keep the compact boolean map in sources() for internal feature detection,
	 * while extensions may continue to render the established labels.
	 */
	public static function available_sources() {
		$labels = array(
			'tec'    => 'The Events Calendar',
			'amelia' => 'Amelia',
		);
		return array_intersect_key( $labels, self::sources() );
	}

	public static function source() {
		$sources = array_keys( self::sources() );
		if ( 1 === count( $sources ) ) {
			return $sources[0];
		}
		$saved = sanitize_key( (string) get_option( self::SOURCE_OPTION, '' ) );
		return in_array( $saved, $sources, true ) ? $saved : '';
	}

	public static function available() {
		return '' !== self::source();
	}

	public static function tec_available() {
		$calendar = class_exists( 'Tribe__Events__Main' ) || defined( 'TRIBE_EVENTS_FILE' ) || ( function_exists( 'post_type_exists' ) && post_type_exists( 'tribe_events' ) );
		$tickets  = class_exists( 'Tribe__Tickets__Main' ) || defined( 'EVENT_TICKETS_VERSION' ) || defined( 'TRIBE_TICKETS_VERSION' ) || defined( 'TRIBE_TICKETS_FILE' ) || class_exists( 'TEC\\Tickets\\Main' );
		return $calendar && $tickets;
	}

	public static function amelia_available() {
		return self::table_exists( 'amelia_users' )
			&& self::table_exists( 'amelia_customer_bookings' )
			&& ( self::table_exists( 'amelia_appointments' ) || ( self::table_exists( 'amelia_events' ) && self::table_exists( 'amelia_events_periods' ) ) );
	}

	public static function render_settings() {
		$sources = self::sources();
		if ( ! $sources ) {
			return;
		}
		$selected = self::source();
		echo '<section id="contactpanda-event-source" class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Events and appointments', 'contactpanda' ) . '</h2>';
		echo '<p>' . esc_html__( 'ContactPanda displays bookings from one event source in the people list and person details. Source data is never changed.', 'contactpanda' ) . '</p>';
		if ( count( $sources ) > 1 ) {
			echo '<p><strong>' . esc_html__( 'Choose which source ContactPanda should display. Data from different event systems is never mixed.', 'contactpanda' ) . '</strong></p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_event_source">';
		wp_nonce_field( 'contactpanda_event_source' );
		foreach ( $sources as $key => $enabled ) {
			$label = 'amelia' === $key ? 'Amelia' : 'The Events Calendar / Event Tickets';
			echo '<label class="contactpanda-source-option"><input type="radio" name="event_source" value="' . esc_attr( $key ) . '" ' . checked( $selected, $key, false ) . '> <span><strong>' . esc_html( $label ) . '</strong><small>' . esc_html__( 'Read-only display', 'contactpanda' ) . '</small></span></label>';
		}
		echo '<p><button class="button button-primary">' . esc_html__( 'Save event source', 'contactpanda' ) . '</button></p></form>';
		echo '<p class="description">' . esc_html__( 'Advanced segments, automated actions, and flows are provided by ContactPanda Plus.', 'contactpanda' ) . '</p></section>';
	}

	public static function source_notice() {
		if ( ! current_user_can( 'manage_options' ) || 2 > count( self::sources() ) || self::source() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;
		if ( ! $screen || false === strpos( (string) $screen->id, 'contactpanda' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Select the event source for ContactPanda.', 'contactpanda' ) . '</strong> ' . esc_html__( 'Amelia and The Events Calendar are active. ContactPanda will not combine their bookings.', 'contactpanda' ) . '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=integrations#contactpanda-event-source' ) ) . '">' . esc_html__( 'Choose event source', 'contactpanda' ) . '</a></p></div>';
	}

	public static function save_source() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_event_source' );
		$source = isset( $_POST['event_source'] ) ? sanitize_key( wp_unslash( $_POST['event_source'] ) ) : '';
		if ( ! isset( self::sources()[ $source ] ) ) {
			wp_die( esc_html__( 'The selected event source is unavailable.', 'contactpanda' ), '', array( 'response' => 400 ) );
		}
		update_option( self::SOURCE_OPTION, $source, false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=integrations&updated=1#contactpanda-event-source' ) );
		exit;
	}

	public static function event_summaries( $summaries, $contacts ) {
		if ( ! self::available() ) {
			return $summaries;
		}
		$map = self::contact_event_map( array(), $contacts );
		foreach ( $summaries as $contact_id => $summary ) {
			$events = $map[ $contact_id ] ?? array();
			$summaries[ $contact_id ]['count']   = count( $events );
			$summaries[ $contact_id ]['visited'] = count( array_filter( $events, static function ( $event ) { return ! empty( $event['visited'] ); } ) );
		}
		return $summaries;
	}

	public static function contact_event_map( $map, $contacts ) {
		if ( ! self::available() ) {
			return $map;
		}
		foreach ( (array) $contacts as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
			if ( $id ) {
				$map[ $id ] = array();
			}
		}
		if ( 'amelia' === self::source() ) {
			return self::amelia_contact_event_map( $map, $contacts );
		}
		return self::tec_contact_event_map( $map, $contacts );
	}

	public static function contact_events( $events, $contact ) {
		if ( ! self::available() ) {
			return $events;
		}
		return 'amelia' === self::source() ? self::amelia_contact_events( $contact ) : self::tec_contact_events( $contact );
	}

	public static function contact_has_event( $matched, $contact, $event_id ) {
		if ( ! self::available() ) {
			return (bool) $matched;
		}
		if ( $matched && 'tec' === self::source() ) {
			return true;
		}
		$event_id = sanitize_text_field( (string) $event_id );
		foreach ( self::contact_events( array(), $contact ) as $event ) {
			if ( $event_id === (string) ( $event['event_id'] ?? '' ) || $event_id === (string) ( $event['event_key'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function account_event_count( $count, $user_id ) {
		$user = get_user_by( 'id', absint( $user_id ) );
		if ( ! $user || ! self::available() ) {
			return $count;
		}
		$contact = array( 'id' => 1, 'user_id' => $user->ID, 'email' => $user->user_email );
		$events = self::contact_events( array(), $contact );
		return count( array_filter( $events, static function ( $event ) { return ! empty( $event['visited'] ); } ) );
	}

	public static function preview_event() {
		self::guard_ajax();
		$key = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
		if ( '' === $key || ! self::available() ) {
			wp_send_json_error( array( 'message' => __( 'The event was not found.', 'contactpanda' ) ), 404 );
		}
		if ( 'amelia' === self::source() ) {
			self::preview_amelia( $key );
		}
		self::preview_tec( absint( $key ) );
	}

	private static function tec_contact_event_map( $map, $contacts ) {
		$identity = self::contact_identity_maps( $contacts );
		$attendees = self::tec_attendees( array_keys( $identity['users'] ), array_keys( $identity['emails'] ) );
		$repository = self::tec_repository();
		foreach ( $attendees as $attendee ) {
			$attendee_id = self::tec_attendee_id( $attendee );
			$event_id = self::tec_event_id( $attendee_id, $repository );
			if ( ! $attendee_id || ! $event_id ) {
				continue;
			}
			$timestamp = function_exists( 'tribe_get_start_date' ) ? (int) tribe_get_start_date( $event_id, false, 'U' ) : (int) get_post_timestamp( $event_id );
			foreach ( self::tec_contact_ids( $attendee_id, $identity, $repository ) as $contact_id ) {
				$existing = $map[ $contact_id ][ $event_id ] ?? array( 'event_id' => $event_id, 'event_key' => (string) $event_id, 'timestamp' => $timestamp, 'visited' => false );
				$existing['visited'] = ! empty( $existing['visited'] ) || self::tec_checked_in( $attendee_id, $repository );
				$map[ $contact_id ][ $event_id ] = $existing;
			}
		}
		foreach ( $map as &$events ) {
			$events = array_values( $events );
		}
		unset( $events );
		return $map;
	}

	private static function tec_contact_events( $contact ) {
		$identity = self::contact_identity_maps( array( $contact ) );
		$attendees = self::tec_attendees( array_keys( $identity['users'] ), array_keys( $identity['emails'] ) );
		$repository = self::tec_repository();
		$found = array();
		foreach ( $attendees as $attendee ) {
			$attendee_id = self::tec_attendee_id( $attendee );
			$event_id = self::tec_event_id( $attendee_id, $repository );
			if ( ! $attendee_id || ! $event_id || ! self::tec_contact_ids( $attendee_id, $identity, $repository ) ) {
				continue;
			}
			if ( ! isset( $found[ $event_id ] ) ) {
				$timestamp = function_exists( 'tribe_get_start_date' ) ? (int) tribe_get_start_date( $event_id, false, 'U' ) : (int) get_post_timestamp( $event_id );
				$url = get_edit_post_link( $event_id, '' );
				$found[ $event_id ] = array(
					'event_id' => $event_id, 'event_key' => (string) $event_id, 'source' => 'tec', 'type' => 'event',
					'title' => get_the_title( $event_id ), 'date' => $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '',
					'timestamp' => $timestamp, 'visited' => false, 'status_label' => __( 'Booked', 'contactpanda' ),
					'event_url' => $url ?: get_permalink( $event_id ), 'order_url' => '',
				);
			}
			$found[ $event_id ]['visited'] = $found[ $event_id ]['visited'] || self::tec_checked_in( $attendee_id, $repository );
			if ( $found[ $event_id ]['visited'] ) {
				$found[ $event_id ]['status_label'] = __( 'Attended', 'contactpanda' );
			}
			if ( ! $found[ $event_id ]['order_url'] ) {
				$found[ $event_id ]['order_url'] = self::tec_order_url( $attendee_id );
			}
		}
		usort( $found, static function ( $left, $right ) { return $right['timestamp'] <=> $left['timestamp']; } );
		return array_values( $found );
	}

	private static function amelia_contact_event_map( $map, $contacts ) {
		$identities = self::contact_identity_maps( $contacts );
		$rows = self::amelia_rows( array_keys( $identities['emails'] ) );
		foreach ( $rows as $row ) {
			$email = self::normalize_email( $row['email'] ?? '' );
			if ( ! $email || empty( $identities['emails'][ $email ] ) ) {
				continue;
			}
			foreach ( $identities['emails'][ $email ] as $contact_id ) {
				$key = (string) $row['event_key'];
				$existing = $map[ $contact_id ][ $key ] ?? array( 'event_id' => $row['event_id'], 'event_key' => $key, 'timestamp' => $row['timestamp'], 'visited' => false );
				$existing['visited'] = ! empty( $existing['visited'] ) || ! empty( $row['visited'] );
				$map[ $contact_id ][ $key ] = $existing;
			}
		}
		foreach ( $map as &$events ) {
			$events = array_values( $events );
		}
		unset( $events );
		return $map;
	}

	private static function amelia_contact_events( $contact ) {
		$identity = self::contact_identity_maps( array( $contact ) );
		$rows = self::amelia_rows( array_keys( $identity['emails'] ) );
		$found = array();
		foreach ( $rows as $row ) {
			$email = self::normalize_email( $row['email'] ?? '' );
			if ( ! $email || empty( $identity['emails'][ $email ] ) ) {
				continue;
			}
			$key = (string) $row['event_key'];
			if ( ! isset( $found[ $key ] ) ) {
				$found[ $key ] = $row;
			} else {
				$found[ $key ]['visited'] = ! empty( $found[ $key ]['visited'] ) || ! empty( $row['visited'] );
			}
		}
		usort( $found, static function ( $left, $right ) { return $right['timestamp'] <=> $left['timestamp']; } );
		return array_values( $found );
	}

	private static function amelia_rows( $emails ) {
		global $wpdb;
		$emails = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_email' ), (array) $emails ) ) ) );
		if ( ! $emails || ! self::amelia_available() ) {
			return array();
		}
		$placeholders = implode( ', ', array_fill( 0, count( $emails ), '%s' ) );
		$tables = array(
			'users' => $wpdb->prefix . 'amelia_users', 'bookings' => $wpdb->prefix . 'amelia_customer_bookings',
			'appointments' => $wpdb->prefix . 'amelia_appointments', 'services' => $wpdb->prefix . 'amelia_services',
			'locations' => $wpdb->prefix . 'amelia_locations', 'links' => $wpdb->prefix . 'amelia_customer_bookings_to_events_periods',
			'periods' => $wpdb->prefix . 'amelia_events_periods', 'events' => $wpdb->prefix . 'amelia_events',
		);
		$rows = array();
		if ( self::table_exists( 'amelia_appointments' ) && self::table_exists( 'amelia_services' ) ) {
			$sql = "SELECT cb.id AS booking_id, cb.status AS booking_status, u.email, a.id AS source_id, a.bookingStart AS starts_at, a.bookingEnd AS ends_at, s.name AS title, l.name AS location FROM `{$tables['bookings']}` cb INNER JOIN `{$tables['users']}` u ON u.id = cb.customerId INNER JOIN `{$tables['appointments']}` a ON a.id = cb.appointmentId LEFT JOIN `{$tables['services']}` s ON s.id = a.serviceId LEFT JOIN `{$tables['locations']}` l ON l.id = a.locationId WHERE LOWER(u.email) IN ({$placeholders})";
			$appointment_rows = $wpdb->get_results( $wpdb->prepare( $sql, $emails ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- dynamic placeholders and read-only optional integration.
			foreach ( (array) $appointment_rows as $row ) {
				$rows[] = self::amelia_normalize_row( $row, 'appointment' );
			}
		}
		if ( self::table_exists( 'amelia_events' ) && self::table_exists( 'amelia_events_periods' ) && self::table_exists( 'amelia_customer_bookings_to_events_periods' ) ) {
			$sql = "SELECT cb.id AS booking_id, cb.status AS booking_status, u.email, e.id AS source_id, MIN(ep.periodStart) AS starts_at, MAX(ep.periodEnd) AS ends_at, e.name AS title, COALESCE(NULLIF(e.customLocation, ''), l.name) AS location FROM `{$tables['bookings']}` cb INNER JOIN `{$tables['users']}` u ON u.id = cb.customerId INNER JOIN `{$tables['links']}` rel ON rel.customerBookingId = cb.id INNER JOIN `{$tables['periods']}` ep ON ep.id = rel.eventPeriodId INNER JOIN `{$tables['events']}` e ON e.id = ep.eventId LEFT JOIN `{$tables['locations']}` l ON l.id = e.locationId WHERE LOWER(u.email) IN ({$placeholders}) GROUP BY cb.id, cb.status, u.email, e.id, e.name, e.customLocation, l.name";
			$event_rows = $wpdb->get_results( $wpdb->prepare( $sql, $emails ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- dynamic placeholders and read-only optional integration.
			foreach ( (array) $event_rows as $row ) {
				$rows[] = self::amelia_normalize_row( $row, 'event' );
			}
		}
		return array_values( array_filter( $rows ) );
	}

	private static function amelia_normalize_row( $row, $type ) {
		$source_id = absint( $row['source_id'] ?? 0 );
		if ( ! $source_id ) {
			return false;
		}
		$start = (string) ( $row['starts_at'] ?? '' );
		$end = (string) ( $row['ends_at'] ?? $start );
		$timestamp = $start ? (int) mysql2date( 'U', $start, false ) : 0;
		$status = sanitize_key( (string) ( $row['booking_status'] ?? '' ) );
		$end_timestamp = $end ? (int) mysql2date( 'U', $end, false ) : 0;
		$visited = 'approved' === $status && $end_timestamp && $end_timestamp <= current_time( 'timestamp' );
		$labels = array(
			'approved' => $visited ? __( 'Attended', 'contactpanda' ) : __( 'Booked', 'contactpanda' ),
			'pending' => __( 'Pending', 'contactpanda' ), 'waiting' => __( 'Waiting list', 'contactpanda' ),
			'canceled' => __( 'Canceled', 'contactpanda' ), 'rejected' => __( 'Rejected', 'contactpanda' ), 'no-show' => __( 'No-show', 'contactpanda' ),
		);
		$key = 'amelia-' . $type . '-' . $source_id;
		return array(
			'event_id' => $source_id, 'event_key' => $key, 'source' => 'amelia', 'type' => $type,
			'title' => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
			'date' => $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '',
			'timestamp' => $timestamp, 'visited' => $visited, 'status_label' => $labels[ $status ] ?? ucfirst( $status ),
			'event_url' => admin_url( 'admin.php?page=wpamelia-' . ( 'event' === $type ? 'events' : 'appointments' ) ),
			'order_url' => '', 'location' => sanitize_text_field( (string) ( $row['location'] ?? '' ) ),
			'booking_id' => absint( $row['booking_id'] ?? 0 ),
		);
	}

	private static function preview_amelia( $key ) {
		if ( ! preg_match( '/^amelia-(appointment|event)-(\d+)$/', $key, $matches ) ) {
			wp_send_json_error( array( 'message' => __( 'The event was not found.', 'contactpanda' ) ), 404 );
		}
		$type = $matches[1];
		$id = absint( $matches[2] );
		$rows = self::amelia_rows_for_source( $type, $id );
		if ( ! $rows ) {
			wp_send_json_error( array( 'message' => __( 'The event was not found.', 'contactpanda' ) ), 404 );
		}
		$event = $rows[0];
		$title = $event['title'] ?: ( 'event' === $type ? __( 'Event', 'contactpanda' ) : __( 'Appointment', 'contactpanda' ) );
		$html = self::preview_document( $title, 'event' === $type ? __( 'Amelia event', 'contactpanda' ) : __( 'Amelia appointment', 'contactpanda' ), $event['date'], $event['location'] ?? '', $event['status_label'] );
		wp_send_json_success( array( 'title' => $title, 'html' => $html, 'edit_url' => $event['event_url'] ) );
	}

	private static function amelia_rows_for_source( $type, $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return array();
		}
		if ( 'appointment' === $type ) {
			$appointments = $wpdb->prefix . 'amelia_appointments';
			$services = $wpdb->prefix . 'amelia_services';
			$locations = $wpdb->prefix . 'amelia_locations';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT a.id AS source_id, a.status AS booking_status, '' AS email, a.bookingStart AS starts_at, a.bookingEnd AS ends_at, s.name AS title, l.name AS location FROM `{$appointments}` a LEFT JOIN `{$services}` s ON s.id = a.serviceId LEFT JOIN `{$locations}` l ON l.id = a.locationId WHERE a.id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		} else {
			$events = $wpdb->prefix . 'amelia_events';
			$periods = $wpdb->prefix . 'amelia_events_periods';
			$locations = $wpdb->prefix . 'amelia_locations';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT e.id AS source_id, e.status AS booking_status, '' AS email, MIN(ep.periodStart) AS starts_at, MAX(ep.periodEnd) AS ends_at, e.name AS title, COALESCE(NULLIF(e.customLocation, ''), l.name) AS location FROM `{$events}` e INNER JOIN `{$periods}` ep ON ep.eventId = e.id LEFT JOIN `{$locations}` l ON l.id = e.locationId WHERE e.id = %d GROUP BY e.id, e.status, e.name, e.customLocation, l.name", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
		$normalized = $row ? self::amelia_normalize_row( $row, $type ) : false;
		return $normalized ? array( $normalized ) : array();
	}

	private static function preview_tec( $event_id ) {
		$post = $event_id ? get_post( $event_id ) : false;
		if ( ! $post || ! self::tec_available() || ! current_user_can( 'edit_post', $event_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The event was not found.', 'contactpanda' ) ), 404 );
		}
		$start = function_exists( 'tribe_get_start_date' ) ? tribe_get_start_date( $event_id, false, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : get_the_date( '', $event_id );
		$venue = function_exists( 'tribe_get_venue' ) ? tribe_get_venue( $event_id ) : '';
		$html = self::preview_document( get_the_title( $event_id ), __( 'The Events Calendar event', 'contactpanda' ), $start, $venue, '' );
		wp_send_json_success( array( 'title' => get_the_title( $event_id ), 'html' => $html, 'edit_url' => get_edit_post_link( $event_id, '' ) ) );
	}

	private static function preview_document( $title, $type, $date, $location, $status ) {
		$html = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><style>body{margin:0;padding:28px;font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#253342;background:#f5f7fa}.card{background:#fff;border:1px solid #dfe5eb;border-radius:10px;padding:22px}h1{font-size:26px;margin:3px 0 20px}.eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#5266d4}.facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.fact{border:1px solid #dfe5eb;border-radius:8px;padding:13px}.fact span{display:block;color:#718096;font-size:12px;margin-bottom:4px}.fact strong{display:block}</style></head><body><main class="card"><span class="eyebrow">' . esc_html( $type ) . '</span><h1>' . esc_html( $title ) . '</h1><div class="facts">';
		foreach ( array( __( 'Date', 'contactpanda' ) => $date, __( 'Location', 'contactpanda' ) => $location, __( 'Status', 'contactpanda' ) => $status ) as $label => $value ) {
			if ( '' !== (string) $value ) {
				$html .= '<div class="fact"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
			}
		}
		return $html . '</div></main></body></html>';
	}

	private static function tec_attendees( $user_ids, $emails ) {
		$attendees = array();
		$queries = array();
		foreach ( array_chunk( array_values( array_filter( array_map( 'absint', $user_ids ) ) ), 100 ) as $chunk ) $queries[] = array( 'user', $chunk );
		foreach ( array_chunk( array_values( array_filter( array_map( 'sanitize_email', $emails ) ) ), 100 ) as $chunk ) {
			$queries[] = array( 'purchaser_email', $chunk );
			$queries[] = array( 'holder_email', $chunk );
		}
		foreach ( $queries as $query ) {
			if ( ! function_exists( 'tribe_attendees' ) ) {
				break;
			}
			try {
				$repository = tribe_attendees();
				$repository->per_page( 1000 );
				$repository->by( $query[0], $query[1] );
				foreach ( (array) $repository->all() as $attendee ) {
					$id = self::tec_attendee_id( $attendee );
					if ( $id ) $attendees[ $id ] = $attendee;
				}
			} catch ( Throwable $error ) {
				// Optional integrations must never take down ContactPanda.
			}
		}
		// Event Tickets stores attendee records in private post types. A WP_Query
		// with post_type=any excludes those post types on several current plugin
		// versions, so use their stable attendee metadata as the compatibility
		// index instead. This remains read-only and works with every table prefix.
		foreach ( self::tec_attendee_meta_ids( $user_ids, $emails ) as $id ) {
			$attendees[ $id ] = $id;
		}
		return array_values( $attendees );
	}

	/**
	 * Find attendee post IDs without relying on public post-type visibility.
	 */
	private static function tec_attendee_meta_ids( $user_ids, $emails ) {
		global $wpdb;
		if ( ! isset( $wpdb->postmeta ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return array();
		}

		$conditions = array();
		$values     = array();
		$user_ids   = array_values( array_unique( array_filter( array_map( 'absint', (array) $user_ids ) ) ) );
		$emails     = array_values( array_unique( array_filter( array_map( 'sanitize_email', (array) $emails ) ) ) );
		if ( $user_ids ) {
			$conditions[] = '(meta_key = %s AND meta_value IN (' . implode( ', ', array_fill( 0, count( $user_ids ), '%s' ) ) . '))';
			$values[]     = '_tribe_tickets_attendee_user_id';
			$values       = array_merge( $values, array_map( 'strval', $user_ids ) );
		}
		foreach ( array( '_tribe_rsvp_email', '_tribe_tpp_email', '_tribe_tickets_email', '_tribe_wooticket_email', '_tec_tickets_commerce_attendee_email' ) as $key ) {
			if ( ! $emails ) {
				break;
			}
			$conditions[] = '(meta_key = %s AND meta_value IN (' . implode( ', ', array_fill( 0, count( $emails ), '%s' ) ) . '))';
			$values[]     = $key;
			$values       = array_merge( $values, $emails );
		}
		if ( ! $conditions ) {
			return array();
		}

		$sql = "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE " . implode( ' OR ', $conditions ) . ' LIMIT 2000'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table property and generated placeholders only.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	}

	/**
	 * Return the optional Event Tickets attendee repository when available.
	 *
	 * Recent Event Tickets releases do not expose tribe_attendees() during
	 * every admin request. The metadata fallback below remains fully usable in
	 * that case, so callers receive a harmless empty object instead of failing.
	 */
	private static function tec_repository() {
		if ( function_exists( 'tribe_attendees' ) ) {
			try {
				$repository = tribe_attendees();
				if ( is_object( $repository ) ) {
					return $repository;
				}
			} catch ( Throwable $error ) {
				// Continue with the read-only post-meta compatibility adapter.
			}
		}
		return new stdClass();
	}

	private static function contact_identity_maps( $contacts ) {
		$users = array();
		$emails = array();
		foreach ( (array) $contacts as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
			if ( ! $id ) continue;
			$user_id = absint( self::value( $contact, array( 'user_id', 'get_user_id' ) ) );
			if ( $user_id ) $users[ $user_id ][] = $id;
			$contact_emails = array( self::value( $contact, array( 'email', 'get_email' ) ) );
			$meta = is_array( $contact ) && isset( $contact['meta'] ) && is_array( $contact['meta'] ) ? $contact['meta'] : array();
			if ( ! empty( $meta['merged_email_aliases'] ) && is_array( $meta['merged_email_aliases'] ) ) {
				$contact_emails = array_merge( $contact_emails, $meta['merged_email_aliases'] );
			}
			if ( ! empty( $meta['contactpanda_contact_channels'] ) && is_array( $meta['contactpanda_contact_channels'] ) ) {
				foreach ( $meta['contactpanda_contact_channels'] as $channel ) {
					if ( is_array( $channel ) && 'email' === ( $channel['type'] ?? '' ) ) {
						$contact_emails[] = $channel['value'] ?? '';
					}
				}
			}
			if ( $user_id ) {
				$user = get_user_by( 'id', $user_id );
				if ( $user ) $contact_emails = array_merge( $contact_emails, array( $user->user_email, get_user_meta( $user->ID, 'billing_email', true ) ) );
			}
			foreach ( $contact_emails as $email ) {
				$email = self::normalize_email( $email );
				if ( $email ) $emails[ $email ][] = $id;
			}
		}
		foreach ( $users as &$ids ) $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		unset( $ids );
		foreach ( $emails as &$ids ) $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
		unset( $ids );
		return array( 'users' => $users, 'emails' => $emails );
	}

	private static function tec_attendee_id( $attendee ) {
		if ( is_object( $attendee ) && isset( $attendee->ID ) ) return absint( $attendee->ID );
		if ( is_array( $attendee ) ) return absint( self::value( $attendee, array( 'attendee_id', 'ID', 'id' ) ) );
		return absint( $attendee );
	}

	private static function tec_event_id( $attendee_id, $repository ) {
		$keys = method_exists( $repository, 'attendee_to_event_keys' ) ? (array) $repository->attendee_to_event_keys() : array();
		foreach ( array_unique( array_merge( array_values( $keys ), array( '_tribe_wooticket_event', '_tribe_rsvp_event', '_tribe_tpp_event', '_tec_tickets_commerce_event' ) ) ) as $key ) {
			$id = absint( get_post_meta( $attendee_id, $key, true ) );
			if ( $id ) return $id;
		}
		return 0;
	}

	private static function tec_checked_in( $attendee_id, $repository ) {
		$keys = method_exists( $repository, 'checked_in_keys' ) ? (array) $repository->checked_in_keys() : array();
		foreach ( array_unique( array_merge( array_values( $keys ), array( '_tribe_wooticket_checkedin', '_tribe_rsvp_checkedin', '_tribe_tpp_checkedin' ) ) ) as $key ) {
			$value = get_post_meta( $attendee_id, $key, true );
			if ( function_exists( 'tribe_is_truthy' ) ? tribe_is_truthy( $value ) : in_array( $value, array( 1, '1', true, 'yes' ), true ) ) return true;
		}
		return false;
	}

	private static function tec_contact_ids( $attendee_id, $identity, $repository ) {
		$ids = array();
		$user_id = absint( get_post_meta( $attendee_id, '_tribe_tickets_attendee_user_id', true ) );
		if ( $user_id && isset( $identity['users'][ $user_id ] ) ) $ids = array_merge( $ids, $identity['users'][ $user_id ] );
		foreach ( self::tec_emails( $attendee_id, $repository ) as $email ) if ( isset( $identity['emails'][ $email ] ) ) $ids = array_merge( $ids, $identity['emails'][ $email ] );
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	private static function tec_emails( $attendee_id, $repository ) {
		$keys = array( '_tribe_rsvp_email', '_tribe_tpp_email', '_tribe_tickets_email', '_tribe_wooticket_email', '_tec_tickets_commerce_attendee_email' );
		foreach ( array( 'holder_email_keys', 'purchaser_email_keys' ) as $method ) if ( method_exists( $repository, $method ) ) $keys = array_merge( $keys, (array) $repository->{$method}() );
		$emails = array();
		foreach ( array_unique( $keys ) as $key ) {
			$email = self::normalize_email( get_post_meta( $attendee_id, $key, true ) );
			if ( $email ) $emails[] = $email;
		}
		$user_id = absint( get_post_meta( $attendee_id, '_tribe_tickets_attendee_user_id', true ) );
		if ( $user_id ) { $user = get_user_by( 'id', $user_id ); if ( $user ) $emails[] = self::normalize_email( $user->user_email ); }
		$order_id = self::tec_order_id( $attendee_id );
		if ( $order_id && function_exists( 'wc_get_order' ) ) { $order = wc_get_order( $order_id ); if ( $order ) $emails[] = self::normalize_email( $order->get_billing_email() ); }
		return array_values( array_unique( array_filter( $emails ) ) );
	}

	private static function tec_order_id( $attendee_id ) {
		foreach ( array( '_tribe_wooticket_order', '_tribe_eddticket_order', '_tribe_tpp_order', '_tribe_rsvp_order', '_tec_tickets_commerce_order_id' ) as $key ) {
			$id = absint( get_post_meta( $attendee_id, $key, true ) );
			if ( $id ) return $id;
		}
		return 0;
	}

	private static function tec_order_url( $attendee_id ) {
		$order_id = self::tec_order_id( $attendee_id );
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) return '';
		$order = wc_get_order( $order_id );
		return $order ? $order->get_edit_order_url() : '';
	}

	private static function normalize_email( $email ) {
		return strtolower( trim( sanitize_email( html_entity_decode( (string) $email, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) ) );
	}

	private static function table_exists( $suffix ) {
		global $wpdb;
		$table = $wpdb->prefix . preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $suffix ) );
		$cache_key = 'contactpanda_table_' . md5( $table );
		$cached = wp_cache_get( $cache_key, 'contactpanda' );
		if ( false !== $cached ) return (bool) $cached;
		$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_cache_set( $cache_key, $exists ? 1 : 0, 'contactpanda', MINUTE_IN_SECONDS );
		return $exists;
	}

	private static function value( $source, $candidates, $default = '' ) {
		foreach ( (array) $candidates as $candidate ) {
			if ( is_array( $source ) && isset( $source[ $candidate ] ) && is_scalar( $source[ $candidate ] ) ) return (string) $source[ $candidate ];
			if ( is_object( $source ) && method_exists( $source, $candidate ) ) { $value = $source->{$candidate}(); if ( is_scalar( $value ) ) return (string) $value; }
			if ( is_object( $source ) && isset( $source->{$candidate} ) && is_scalar( $source->{$candidate} ) ) return (string) $source->{$candidate};
		}
		return $default;
	}

	private static function guard_ajax() {
		$capability = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
		if ( ! current_user_can( $capability ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		check_ajax_referer( 'contactpanda_update_company', 'nonce' );
	}
}
