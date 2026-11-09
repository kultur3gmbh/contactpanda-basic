<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Store {
	public static function source() {
		$settings = (array) get_option( 'contactpanda_general_settings', array() );
		$default = defined( 'GROUNDHOGG_VERSION' ) || class_exists( '\\Groundhogg\\Contact' ) ? 'groundhogg' : 'unconfigured';
		return isset( $settings['data_source'] ) ? sanitize_key( $settings['data_source'] ) : $default;
	}

	public static function standalone() {
		return 'standalone' === self::source() && self::ready();
	}

	public static function ready() {
		$status = ContactPanda_Schema::status();
		return $status['total'] === $status['count'] && ContactPanda_Schema::VERSION === $status['version'];
	}

	public static function counts() {
		global $wpdb;
		$result = array();
		foreach ( ContactPanda_Schema::table_names() as $key => $table ) {
			$result[ $key ] = self::ready() ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- exact internally generated table name.
		}
		return $result;
	}

	/**
	 * Copy legacy WooCommerce/user VAT metadata into the canonical owner fields.
	 *
	 * The migration only fills empty fields and never rewrites source metadata,
	 * orders, invoices, or already classified ContactPanda records. Company VAT
	 * IDs are recovered from the historic primary-contact storage convention;
	 * unlinked people retain personal ownership of their VAT ID.
	 */
	public static function migrate_legacy_vat_ids() {
		if ( ! self::standalone() || get_option( 'contactpanda_vat_owner_migration_093', false ) ) {
			return;
		}

		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$keys   = (array) apply_filters( 'contactpanda_vat_user_meta_keys', array( 'billing_vat', 'b2b_uid' ), false, false );
		$keys   = array_values( array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) ) );
		if ( empty( $keys ) ) {
			update_option( 'contactpanda_vat_owner_migration_093', current_time( 'mysql' ), false );
			return;
		}

		$legacy_vat = static function ( $user_id ) use ( $keys ) {
			foreach ( $keys as $key ) {
				$value = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) get_user_meta( $user_id, $key, true ) ) );
				if ( '' !== $value ) return $value;
			}
			return '';
		};
		$status_for = static function ( $user_id, $vat_id ) {
			$verified = (string) get_user_meta( $user_id, 'contactpanda_vat_verified_hash', true );
			return $verified && hash_equals( $verified, hash( 'sha256', $vat_id ) ) ? 'valid' : 'unverified';
		};

		$companies = (array) $wpdb->get_results(
			"SELECT co.id, c.wp_user_id FROM `{$tables['companies']}` co INNER JOIN `{$tables['company_contacts']}` cc ON cc.company_id=co.id AND cc.is_primary_company=1 INNER JOIN `{$tables['contacts']}` c ON c.id=cc.contact_id WHERE (co.vat_id IS NULL OR co.vat_id='') AND c.wp_user_id>0 AND co.deleted_at IS NULL",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- exact internal table names.
		foreach ( $companies as $company ) {
			$vat_id = $legacy_vat( absint( $company['wp_user_id'] ) );
			if ( '' === $vat_id ) continue;
			self::update_company( absint( $company['id'] ), 'vat_id', $vat_id );
			self::update_company( absint( $company['id'] ), 'vat_status', $status_for( absint( $company['wp_user_id'] ), $vat_id ) );
		}

		$people = (array) $wpdb->get_results(
			"SELECT c.id, c.wp_user_id FROM `{$tables['contacts']}` c WHERE (c.vat_id IS NULL OR c.vat_id='') AND c.wp_user_id>0 AND c.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM `{$tables['company_contacts']}` cc WHERE cc.contact_id=c.id)",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- exact internal table names.
		foreach ( $people as $person ) {
			$vat_id = $legacy_vat( absint( $person['wp_user_id'] ) );
			if ( '' === $vat_id ) continue;
			self::update_contact(
				absint( $person['id'] ),
				array(
					'vat_id'     => $vat_id,
					'vat_status' => $status_for( absint( $person['wp_user_id'] ), $vat_id ),
				)
			);
		}

		update_option( 'contactpanda_vat_owner_migration_093', current_time( 'mysql' ), false );
	}

	public static function search_contacts( $search = '', $limit = 50, $offset = 0 ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$where = "deleted_at IS NULL";
		$params = array();
		if ( '' !== trim( $search ) ) {
			$like = '%' . $wpdb->esc_like( trim( $search ) ) . '%';
			$where .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR id IN (SELECT custom_meta.contact_id FROM `{$tables['contactmeta']}` custom_meta WHERE custom_meta.meta_key LIKE %s AND custom_meta.meta_value LIKE %s))";
			$params = array( $like, $like, $like, ContactPanda_Custom_Fields::PREFIX . '%', $like );
		}
		$count_sql = "SELECT COUNT(*) FROM `{$tables['contacts']}` WHERE {$where}";
		$total = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- internal allowlisted table and fixed clauses; values are prepared when present.
		$sql = "SELECT * FROM `{$tables['contacts']}` WHERE {$where} ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( absint( $limit ), absint( $offset ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- internal allowlisted table and fixed clauses; all values are prepared.
		return array( 'contacts' => array_map( array( __CLASS__, 'hydrate_contact' ), (array) $rows ), 'total_items' => $total );
	}

	public static function contact( $id, $include_deleted = false ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contacts'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d{$state}", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	public static function contact_by_email( $email, $include_deleted = false ) {
		if ( class_exists( 'ContactPanda_Identity_Store' ) && self::ready() ) {
			$contact_id = ContactPanda_Identity_Store::contact_id_by_channel( 'email', $email, $include_deleted );
			if ( $contact_id ) return self::contact( $contact_id, $include_deleted );
		}
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contacts'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE email_normalized = %s{$state}", strtolower( sanitize_email( $email ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	/** Resolve the active standalone contact explicitly linked to a WordPress user. */
	public static function contact_by_user_id( $user_id, $include_deleted = false ) {
		if ( class_exists( 'ContactPanda_Identity_Store' ) && self::ready() ) {
			$contact_id = ContactPanda_Identity_Store::contact_id_by_identity( 'wordpress', 'user', $user_id, $include_deleted );
			if ( $contact_id ) return self::contact( $contact_id, $include_deleted );
		}
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contacts'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE wp_user_id = %d{$state} ORDER BY id ASC LIMIT 1", absint( $user_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	public static function contact_by_meta( $key, $value, $include_deleted = false ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$state = $include_deleted ? '' : ' AND c.deleted_at IS NULL';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT c.* FROM `{$tables['contacts']}` c INNER JOIN `{$tables['contactmeta']}` m ON m.contact_id=c.id WHERE m.meta_key=%s AND m.meta_value=%s{$state} ORDER BY c.id ASC LIMIT 1", sanitize_key( $key ), maybe_serialize( $value ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names.
		return $row ? self::hydrate_contact( $row ) : false;
	}

	public static function duplicate_candidates( $limit = 50 ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$rows = (array) $wpdb->get_results( "SELECT id, wp_user_id, email, first_name, last_name FROM `{$tables['contacts']}` WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 5000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$meta_rows = (array) $wpdb->get_results( "SELECT contact_id, meta_key, meta_value FROM `{$tables['contactmeta']}` WHERE meta_key IN ('primary_phone','billing_phone','phone','postcode','billing_postcode','contactpanda_contact_channels')", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$meta = array();
		foreach ( $meta_rows as $item ) $meta[ absint( $item['contact_id'] ) ][ $item['meta_key'] ] = maybe_unserialize( $item['meta_value'] );
		$normalized_channels = array();
		foreach ( (array) $wpdb->get_results( "SELECT contact_id,channel_type,channel_value FROM `{$tables['contact_channels']}` WHERE status='active'", ARRAY_A ) as $channel ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
			$normalized_channels[ absint( $channel['contact_id'] ) ][] = $channel;
		}
		$external_identities = array();
		foreach ( (array) $wpdb->get_results( "SELECT contact_id,provider,identity_type,external_id FROM `{$tables['external_identities']}` WHERE status='active'", ARRAY_A ) as $identity ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
			$external_identities[ absint( $identity['contact_id'] ) ][] = $identity;
		}
		$seen = array(); $pairs = array();
		foreach ( $rows as $row ) {
			$id = absint( $row['id'] );
			$phones = array( preg_replace( '/\D+/', '', (string) ( $meta[ $id ]['primary_phone'] ?? $meta[ $id ]['billing_phone'] ?? $meta[ $id ]['phone'] ?? '' ) ) );
			$emails = array( strtolower( sanitize_email( $row['email'] ?? '' ) ) );
			$channels = $meta[ $id ]['contactpanda_contact_channels'] ?? array();
			foreach ( is_array( $channels ) ? $channels : array() as $channel ) {
				if ( ! is_array( $channel ) ) continue;
				if ( 'phone' === ( $channel['type'] ?? '' ) ) $phones[] = preg_replace( '/\D+/', '', (string) ( $channel['value'] ?? '' ) );
				if ( 'email' === ( $channel['type'] ?? '' ) ) $emails[] = strtolower( sanitize_email( $channel['value'] ?? '' ) );
			}
			foreach ( $normalized_channels[ $id ] ?? array() as $channel ) {
				if ( 'phone' === $channel['channel_type'] ) $phones[] = preg_replace( '/\D+/', '', (string) $channel['channel_value'] );
				if ( 'email' === $channel['channel_type'] ) $emails[] = strtolower( sanitize_email( $channel['channel_value'] ) );
			}
			$postcode = preg_replace( '/\s+/', '', strtolower( (string) ( $meta[ $id ]['postcode'] ?? $meta[ $id ]['billing_postcode'] ?? '' ) ) );
			$name = trim( strtolower( remove_accents( (string) $row['first_name'] . '|' . (string) $row['last_name'] ) ) );
			$keys = array();
			if ( ! empty( $row['wp_user_id'] ) ) $keys['wordpress'] = 'u:' . absint( $row['wp_user_id'] );
			foreach ( $external_identities[ $id ] ?? array() as $identity ) {
				$identity_key = sanitize_key( $identity['provider'] ) . ':' . sanitize_key( $identity['identity_type'] ) . ':' . sanitize_text_field( $identity['external_id'] );
				$keys[ __( 'Same external account', 'contactpanda' ) . ':' . $identity_key ] = 'x:' . $identity_key;
			}
			foreach ( array_unique( array_filter( $phones ) ) as $phone ) if ( strlen( $phone ) >= 7 ) $keys[ __( 'Phone number', 'contactpanda' ) . ':' . $phone ] = 'p:' . $phone;
			foreach ( array_unique( array_filter( $emails ) ) as $email ) $keys[ __( 'Email address', 'contactpanda' ) . ':' . $email ] = 'e:' . $email;
			if ( '|' !== $name && $postcode ) $keys[ __( 'Name and postal code', 'contactpanda' ) ] = 'n:' . $name . '|' . $postcode;
			foreach ( $keys as $reason => $key ) {
				if ( isset( $seen[ $key ] ) ) {
					$left = $seen[ $key ];
					$pairs[ $left['id'] . ':' . $id ] = array(
						'left_id' => $left['id'], 'right_id' => $id,
						'left_first_name' => $left['first_name'], 'left_last_name' => $left['last_name'],
						'right_first_name' => $row['first_name'], 'right_last_name' => $row['last_name'],
						'reason' => 'wordpress' === $reason ? __( 'Same WordPress account', 'contactpanda' ) : preg_replace( '/:.+$/', '', $reason ),
					);
					if ( count( $pairs ) >= absint( $limit ) ) return apply_filters( 'contactpanda_duplicate_candidates', array_values( $pairs ), $rows, $meta );
				} else {
					$seen[ $key ] = $row;
				}
			}
		}
		return apply_filters( 'contactpanda_duplicate_candidates', array_values( $pairs ), $rows, $meta );
	}

	/**
	 * Return transitive duplicate groups instead of isolated pairs.
	 *
	 * A-B and B-C are deliberately presented as one review group. No records are
	 * changed here; the administrator still resolves every value in the merge UI.
	 */
	public static function duplicate_groups( $limit = 50 ) {
		$pairs = self::duplicate_candidates( max( 200, absint( $limit ) * 10 ) );
		$parents = array();
		$names = array();
		$reasons = array();
		$find = static function ( $id ) use ( &$parents, &$find ) {
			if ( ! isset( $parents[ $id ] ) ) $parents[ $id ] = $id;
			if ( $parents[ $id ] !== $id ) $parents[ $id ] = $find( $parents[ $id ] );
			return $parents[ $id ];
		};
		foreach ( $pairs as $pair ) {
			$left = absint( $pair['left_id'] ); $right = absint( $pair['right_id'] );
			$left_root = $find( $left ); $right_root = $find( $right );
			if ( $left_root !== $right_root ) $parents[ $right_root ] = $left_root;
			$names[ $left ] = trim( $pair['left_first_name'] . ' ' . $pair['left_last_name'] );
			$names[ $right ] = trim( $pair['right_first_name'] . ' ' . $pair['right_last_name'] );
			$reasons[ $left . ':' . $right ] = $pair['reason'];
		}
		$groups = array();
		foreach ( array_keys( $parents ) as $id ) $groups[ $find( $id ) ][] = $id;
		$result = array();
		foreach ( $groups as $ids ) {
			$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
			if ( count( $ids ) < 2 ) continue;
			$group_reasons = array();
			foreach ( $reasons as $key => $reason ) { list( $a, $b ) = array_map( 'absint', explode( ':', $key ) ); if ( in_array( $a, $ids, true ) && in_array( $b, $ids, true ) ) $group_reasons[] = $reason; }
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$result[] = array( 'ids' => $ids, 'names' => array_map( static function ( $id ) use ( $names ) { return $names[ $id ] ?: sprintf( __( 'Record #%d', 'contactpanda' ), $id ); }, $ids ), 'reasons' => array_values( array_unique( $group_reasons ) ) );
			if ( count( $result ) >= absint( $limit ) ) break;
		}
		return $result;
	}

	public static function create_contact( $data ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contacts'];
		$email = sanitize_email( isset( $data['email'] ) ? $data['email'] : '' );
		if ( $email && self::contact_by_email( $email, true ) ) {
			return new WP_Error( 'contactpanda_duplicate_email', __( 'A person with this email address already exists.', 'contactpanda' ) );
		}
		$now = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			$table,
			array(
				'uuid'            => self::new_uuid(),
				'wp_user_id'      => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'email'           => $email ?: null,
				'email_normalized'=> $email ? strtolower( $email ) : null,
				'first_name'      => sanitize_text_field( isset( $data['first_name'] ) ? $data['first_name'] : '' ),
				'last_name'       => sanitize_text_field( isset( $data['last_name'] ) ? $data['last_name'] : '' ),
				'position'        => sanitize_text_field( isset( $data['position'] ) ? $data['position'] : '' ),
				'status'          => sanitize_key( isset( $data['status'] ) ? $data['status'] : 'active' ),
				'source'          => sanitize_key( isset( $data['source'] ) ? $data['source'] : 'manual' ),
				'created_at'      => $now,
				'updated_at'      => $now,
			)
		);
		if ( ! $inserted ) {
			return new WP_Error( 'contactpanda_contact_insert', __( 'The person could not be saved in ContactPanda.', 'contactpanda' ) );
		}
		$id = (int) $wpdb->insert_id;
		self::update_contact( $id, $data );
		do_action( 'contactpanda_contact_created', $id, $email );
		return $id;
	}

	public static function update_contact( $id, $data ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contacts'];
		$core = array();
		$map = array(
			'first_name'      => 'first_name',
			'last_name'       => 'last_name',
			'position'        => 'position',
			'user_id'         => 'wp_user_id',
			'vat_id'          => 'vat_id',
			'vat_status'      => 'vat_status',
			'vat_verified_at' => 'vat_verified_at',
		);
		foreach ( $map as $input => $column ) {
			if ( array_key_exists( $input, $data ) ) {
				if ( 'user_id' === $input ) {
					$core[ $column ] = absint( $data[ $input ] );
				} elseif ( 'vat_verified_at' === $input ) {
					$core[ $column ] = empty( $data[ $input ] ) ? null : sanitize_text_field( $data[ $input ] );
				} else {
					$core[ $column ] = sanitize_text_field( $data[ $input ] );
				}
			}
		}
		if ( array_key_exists( 'email', $data ) ) {
			$email = sanitize_email( $data['email'] );
			$core['email'] = $email ?: null;
			$core['email_normalized'] = $email ? strtolower( $email ) : null;
		}
		$core['updated_at'] = current_time( 'mysql' );
		$wpdb->update( $table, $core, array( 'id' => absint( $id ) ) );
		$meta = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
		foreach ( array( 'anrede', 'titel_vorangestellt', 'titel_nachgestellt', 'berufsbezeichnung', 'primary_phone', 'optin_status', 'groundhogg_contact_id' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$meta[ $key ] = $data[ $key ];
			}
		}
		foreach ( $meta as $key => $value ) {
			self::set_contact_meta( $id, $key, $value );
		}
		if ( class_exists( 'ContactPanda_Identity_Store' ) && self::ready() ) ContactPanda_Identity_Store::sync_contact( $id );
		return true;
	}

	public static function update_contact_address( $id, $address ) {
		$allowed = array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
		foreach ( $allowed as $key ) {
			$value = isset( $address[ $key ] ) ? sanitize_text_field( $address[ $key ] ) : '';
			if ( 'country' === $key ) {
				$value = strtoupper( $value );
			}
			if ( ! self::set_contact_meta( $id, $key, $value ) ) {
				return false;
			}
		}
		return true;
	}

	public static function companies() {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['companies'];
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 1000", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return array_map( array( __CLASS__, 'hydrate_company' ), (array) $rows );
	}

	public static function company( $id ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['companies'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND deleted_at IS NULL", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $row ? self::hydrate_company( $row ) : false;
	}

	public static function create_company( $data ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['companies'];
		$now = current_time( 'mysql' );
		$wpdb->insert( $table, array( 'uuid' => self::new_uuid(), 'name' => sanitize_text_field( $data['name'] ), 'legal_name' => sanitize_text_field( isset( $data['legal_name'] ) ? $data['legal_name'] : $data['name'] ), 'industry' => sanitize_text_field( isset( $data['industry'] ) ? $data['industry'] : '' ), 'website' => esc_url_raw( isset( $data['website'] ) ? $data['website'] : '' ), 'vat_id' => sanitize_text_field( isset( $data['vat_id'] ) ? $data['vat_id'] : '' ), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
		return $wpdb->insert_id ? (int) $wpdb->insert_id : 0;
	}

	/** Import one standalone company row without clearing existing non-empty data. */
	public static function import_company_row( $data, $mode = 'fill', $source_id = '' ) {
		global $wpdb;
		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) return new WP_Error( 'contactpanda_import_company_name', __( 'Company name is missing.', 'contactpanda' ) );
		$id = self::company_by_name( $name );
		$existed = (bool) $id;
		if ( $id && 'skip' === $mode ) return 'skipped';
		if ( ! $id ) {
			$id = self::create_company( array( 'name' => $name, 'legal_name' => $data['legal_name'] ?? $name, 'industry' => $data['industry'] ?? '', 'website' => $data['website'] ?? '', 'vat_id' => $data['vat_id'] ?? '' ) );
			if ( ! $id ) return new WP_Error( 'contactpanda_import_company_create', __( 'Company could not be created.', 'contactpanda' ) );
		}
		$current = self::company( $id );
		$map = array( 'name', 'legal_name', 'industry', 'website', 'email', 'vat_id', 'street', 'address_2', 'postcode', 'city', 'country' );
		$input_map = array( 'street' => 'address_1' );
		$update = array();
		foreach ( $map as $field ) {
			$input = $input_map[ $field ] ?? $field;
			$value = sanitize_text_field( $data[ $input ] ?? '' );
			if ( '' !== $value && ( ! $current || 'update' === $mode || '' === trim( (string) ( $current[ $field ] ?? '' ) ) ) ) $update[ $field ] = 'website' === $field ? esc_url_raw( $value ) : $value;
		}
		if ( ! empty( $data['phone'] ) && ( ! $current || 'update' === $mode || empty( $current['phone_number'] ) ) ) $update['phone_number'] = sanitize_text_field( $data['phone'] );
		$update['updated_at'] = current_time( 'mysql' );
		if ( false === $wpdb->update( ContactPanda_Schema::table_names()['companies'], $update, array( 'id' => $id ) ) ) return new WP_Error( 'contactpanda_import_company_update', __( 'Company could not be updated.', 'contactpanda' ) );
		self::set_company_meta( $id, 'csv_source_id', sanitize_text_field( $source_id ) );
		return $existed ? 'updated' : 'created';
	}

	public static function company_by_name( $name, $include_deleted = false ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['companies'];
		$state = $include_deleted ? '' : ' AND deleted_at IS NULL';
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE LOWER(name) = LOWER(%s){$state} LIMIT 1", sanitize_text_field( $name ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	/**
	 * Links a standalone contact to a freely entered company name. Existing
	 * active companies are reused; otherwise a minimal company is created.
	 */
	public static function link_contact_company_name( $email, $company_name ) {
		if ( ! self::standalone() ) {
			return true;
		}
		$email = sanitize_email( $email );
		$company_name = sanitize_text_field( $company_name );
		if ( ! $email || '' === trim( $company_name ) ) {
			return true;
		}
		$contact = self::contact_by_email( $email );
		if ( ! $contact ) {
			return false;
		}
		$company_id = self::company_by_name( $company_name );
		if ( ! $company_id ) {
			$company_id = self::create_company(
				array(
					'name'       => $company_name,
					'legal_name' => $company_name,
				)
			);
		}
		return $company_id ? self::link_company_contact( $company_id, $contact['id'] ) : false;
	}

	public static function link_company_contact( $company_id, $contact_id, $position = '', $primary = false, $primary_company = false ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['company_contacts'];
		if ( $primary && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE company_id = %d AND contact_id <> %d AND is_primary = 1 LIMIT 1", absint( $company_id ), absint( $contact_id ) ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
			$primary = false;
		}
		if ( $primary_company ) {
			$wpdb->update( $table, array( 'is_primary_company' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'contact_id' => absint( $contact_id ) ) );
		}
		$saved = false !== $wpdb->replace(
			$table,
			array(
				'company_id'        => absint( $company_id ),
				'contact_id'        => absint( $contact_id ),
				'relationship_type' => 'employee',
				'position'          => sanitize_text_field( $position ),
				'is_primary'        => $primary ? 1 : 0,
				'is_primary_company' => $primary_company ? 1 : 0,
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			)
		);
		if ( $saved ) self::ensure_primary_company( $contact_id );
		return $saved;
	}

	public static function contact_company_relations( $contact_id ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$sql = "SELECT r.company_id,r.contact_id,r.relationship_type,r.position,r.is_primary,r.is_primary_company,c.name,c.legal_name,c.website FROM `{$tables['company_contacts']}` r INNER JOIN `{$tables['companies']}` c ON c.id=r.company_id WHERE r.contact_id=%d AND c.deleted_at IS NULL ORDER BY r.is_primary_company DESC,c.name ASC";
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, absint( $contact_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- internal table names; the only value is prepared.
	}

	public static function remove_company_contact( $company_id, $contact_id ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['company_contacts'];
		$deleted = false !== $wpdb->delete( $table, array( 'company_id' => absint( $company_id ), 'contact_id' => absint( $contact_id ) ) );
		if ( $deleted ) self::ensure_primary_company( $contact_id );
		return $deleted;
	}

	public static function update_contact_company_relation( $company_id, $contact_id, $position, $primary_company ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['company_contacts'];
		$company_id = absint( $company_id );
		$contact_id = absint( $contact_id );
		if ( ! $company_id || ! $contact_id ) return false;
		if ( $primary_company ) {
			$wpdb->update( $table, array( 'is_primary_company' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'contact_id' => $contact_id ) );
		}
		$updated = false !== $wpdb->update(
			$table,
			array( 'position' => sanitize_text_field( $position ), 'is_primary_company' => $primary_company ? 1 : 0, 'updated_at' => current_time( 'mysql' ) ),
			array( 'company_id' => $company_id, 'contact_id' => $contact_id )
		);
		if ( $updated ) self::ensure_primary_company( $contact_id );
		return $updated;
	}

	private static function ensure_primary_company( $contact_id ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['company_contacts'];
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE contact_id=%d AND is_primary_company=1 LIMIT 1", absint( $contact_id ) ) ) ) return;
		$id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE contact_id=%d ORDER BY id ASC LIMIT 1", absint( $contact_id ) ) ) );
		if ( $id ) $wpdb->update( $table, array( 'is_primary_company' => 1, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
	}

	public static function update_company( $id, $field, $value ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$map = array( 'name' => 'name', 'domain' => 'website', 'phone' => 'phone_number', 'industry' => 'industry', 'street' => 'street', 'address_2' => 'address_2', 'postcode' => 'postcode', 'city' => 'city', 'country' => 'country', 'uid' => 'vat_id', 'vat_id' => 'vat_id', 'vat_status' => 'vat_status', 'vat_verified_at' => 'vat_verified_at' );
		if ( isset( $map[ $field ] ) ) {
			$stored_value = 'vat_verified_at' === $field && empty( $value ) ? null : sanitize_text_field( $value );
			return false !== $wpdb->update( $tables['companies'], array( $map[ $field ] => $stored_value, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $id ) ) );
		}
		return self::set_company_meta( $id, $field, $value );
	}

	public static function update_company_profile( $id, $data ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['companies'];
		return false !== $wpdb->update( $table, array( 'legal_name' => sanitize_text_field( $data['company'] ), 'industry' => sanitize_text_field( $data['industry'] ), 'website' => esc_url_raw( $data['website'] ), 'vat_id' => sanitize_text_field( $data['vat_id'] ), 'street' => sanitize_text_field( $data['billing_address_1'] ), 'address_2' => sanitize_text_field( $data['billing_address_2'] ), 'postcode' => sanitize_text_field( $data['billing_postcode'] ), 'city' => sanitize_text_field( $data['billing_city'] ), 'country' => sanitize_key( $data['billing_country'] ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $id ) ) );
	}

	public static function company_map() {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$rows = $wpdb->get_results( "SELECT company_id, contact_id, position, is_primary_company FROM `{$tables['company_contacts']}` ORDER BY is_primary_company DESC, id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$companies = array();
		foreach ( self::companies() as $company ) {
			$companies[ $company['id'] ] = $company;
		}
		$map = array();
		foreach ( (array) $rows as $row ) {
			if ( isset( $companies[ $row['company_id'] ] ) ) {
				$company = $companies[ $row['company_id'] ];
				$company['relationship_position'] = $row['position'];
				$company['is_primary_company'] = ! empty( $row['is_primary_company'] );
				$map[ $row['contact_id'] ][] = $company;
			}
		}
		return $map;
	}

	public static function tags() {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['tags'];
		return (array) $wpdb->get_results( "SELECT id, name, description FROM `{$table}` ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	public static function create_tag( $name, $description = '' ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['tags'];
		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );
		if ( '' === $name || '' === $slug ) return 0;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( $id ) return $id;
		$now = current_time( 'mysql' );
		$wpdb->insert( $table, array( 'name' => $name, 'slug' => $slug, 'description' => sanitize_textarea_field( $description ), 'created_at' => $now, 'updated_at' => $now ) );
		return (int) $wpdb->insert_id;
	}

	public static function tag_counts() {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contact_tags'];
		$rows = $wpdb->get_results( "SELECT tag_id, COUNT(*) AS assignments FROM `{$table}` GROUP BY tag_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ absint( $row['tag_id'] ) ] = absint( $row['assignments'] );
		}
		return $counts;
	}

	public static function rename_tag( $tag_id, $name, $description = '' ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['tags'];
		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $name );
		if ( ! $tag_id || ! $name || ! $slug ) return false;
		$duplicate = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s AND id <> %d LIMIT 1", $slug, absint( $tag_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( $duplicate ) return false;
		return false !== $wpdb->update( $table, array( 'name' => $name, 'slug' => $slug, 'description' => sanitize_textarea_field( $description ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $tag_id ) ) );
	}

	public static function salutation_language( $language = '' ) {
		$language = strtolower( substr( sanitize_key( $language ?: determine_locale() ), 0, 2 ) );
		return in_array( $language, array( 'de', 'en', 'fr', 'es', 'it' ), true ) ? $language : 'de';
	}

	public static function salutations( $language = '', $limit = 0, $offset = 0, $search = '' ) {
		global $wpdb;
		$all_languages = 'all' === sanitize_key( $language );
		$language = $all_languages ? '' : self::salutation_language( $language );
		$table = ContactPanda_Schema::table_names()['salutations'];
		if ( self::ready() ) {
			$where = $all_languages ? '1=1' : 'language=%s'; $args = $all_languages ? array() : array( $language );
			if ( $search ) { $where .= ' AND first_name LIKE %s'; $args[] = '%' . $wpdb->esc_like( $search ) . '%'; }
			$sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY first_name ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name and fixed clause.
			if ( $limit ) { $sql .= ' LIMIT %d OFFSET %d'; $args[] = absint( $limit ); $args[] = absint( $offset ); }
			return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- internal table and fixed clauses; all dynamic values are prepared.
		}
		$rows = array();
		foreach ( (array) get_option( 'contactpanda_salutation_library', array() ) as $key => $row ) {
			if ( ( ! $all_languages && $language !== ( $row['language'] ?? 'de' ) ) || ( $search && false === stripos( $row['first_name'] ?? '', $search ) ) ) continue;
			$row['id'] = (string) $key;
			$rows[] = $row;
		}
		usort( $rows, static function ( $a, $b ) { return strcasecmp( $a['first_name'], $b['first_name'] ); } ); return $limit ? array_slice( $rows, $offset, $limit ) : $rows;
	}

	public static function salutations_count( $language = '', $search = '' ) { return count( self::salutations( $language, 0, 0, $search ) ); }

	public static function suggested_salutation( $first_name, $language = '' ) {
		global $wpdb;
		$normalized = self::normalize_first_name( $first_name );
		$language = self::salutation_language( $language );
		if ( ! $normalized ) return '';
		if ( ! self::ready() ) { $library = (array) get_option( 'contactpanda_salutation_library', array() ); $key = $language . ':' . $normalized; return isset( $library[ $key ]['salutation'] ) ? (string) $library[ $key ]['salutation'] : ( isset( $library[ $normalized ]['salutation'] ) ? (string) $library[ $normalized ]['salutation'] : '' ); }
		$table = ContactPanda_Schema::table_names()['salutations'];
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT salutation FROM `{$table}` WHERE first_name_normalized=%s AND language=%s", $normalized, $language ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	public static function learn_salutation( $first_name, $salutation, $source = 'confirmed', $language = '' ) {
		global $wpdb;
		$salutation = sanitize_key( $salutation );
		$normalized = self::normalize_first_name( $first_name );
		$language = self::salutation_language( $language );
		if ( ! $normalized || ! in_array( $salutation, array( 'mr', 'ms', 'family', 'neutral' ), true ) ) return false;
		$key = $language . ':' . $normalized;
		if ( ! self::ready() ) { $library = (array) get_option( 'contactpanda_salutation_library', array() ); $now = current_time( 'mysql' ); $library[ $key ] = array( 'first_name' => sanitize_text_field( trim( $first_name ) ), 'first_name_normalized' => $normalized, 'language' => $language, 'salutation' => $salutation, 'source' => sanitize_key( $source ), 'created_at' => $library[ $key ]['created_at'] ?? $now, 'updated_at' => $now ); return update_option( 'contactpanda_salutation_library', $library, false ) || isset( $library[ $key ] ); }
		$table = ContactPanda_Schema::table_names()['salutations'];
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE first_name_normalized=%s AND language=%s", $normalized, $language ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data = array( 'first_name' => sanitize_text_field( trim( $first_name ) ), 'first_name_normalized' => $normalized, 'language' => $language, 'salutation' => $salutation, 'source' => sanitize_key( $source ), 'updated_at' => current_time( 'mysql' ) );
		if ( $existing ) return false !== $wpdb->update( $table, $data, array( 'id' => $existing ) );
		$data['created_at'] = current_time( 'mysql' );
		return false !== $wpdb->insert( $table, $data );
	}

	public static function delete_salutation( $id ) {
		global $wpdb;
		if ( ! self::ready() ) { $library = (array) get_option( 'contactpanda_salutation_library', array() ); $key = sanitize_text_field( (string) $id ); if ( ! isset( $library[ $key ] ) ) return false; unset( $library[ $key ] ); return update_option( 'contactpanda_salutation_library', $library, false ); }
		return false !== $wpdb->delete( ContactPanda_Schema::table_names()['salutations'], array( 'id' => absint( $id ) ) );
	}

	/** Return one salutation-library row by its local identifier. */
	public static function salutation( $id ) {
		global $wpdb;
		if ( self::ready() ) return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . ContactPanda_Schema::table_names()['salutations'] . '` WHERE id=%d', absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- internal table name; the identifier is prepared.
		$library = (array) get_option( 'contactpanda_salutation_library', array() );
		$key = sanitize_text_field( (string) $id );
		if ( ! isset( $library[ $key ] ) ) return false;
		return array_merge( $library[ $key ], array( 'id' => $key ) );
	}

	/**
	 * Merge local ContactPanda records and move the source fragments to Trash.
	 *
	 * External systems are deliberately never called from this method. All
	 * relationships and history remain attached to the selected primary record.
	 */
	public static function merge_records( $entity, $ids, $primary_id, $choices = array(), $resolved = array(), $selected_channels = array(), $primary_channels = array() ) {
		global $wpdb;
		$entity = 'companies' === $entity ? 'companies' : 'contacts';
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		$primary_id = absint( $primary_id );
		if ( ! self::standalone() || count( $ids ) < 2 || ! in_array( $primary_id, $ids, true ) ) {
			return new WP_Error( 'contactpanda_merge_invalid', __( 'Select at least two active records and one primary record.', 'contactpanda' ) );
		}
		if ( 'contacts' === $entity && isset( $resolved['email'] ) && '' !== trim( (string) $resolved['email'] ) && ! is_email( $resolved['email'] ) ) {
			return new WP_Error( 'contactpanda_merge_email', __( 'Enter a valid final email address before merging.', 'contactpanda' ) );
		}
		if ( 'companies' === $entity && isset( $resolved['email'] ) && '' !== trim( (string) $resolved['email'] ) && ! is_email( $resolved['email'] ) ) {
			return new WP_Error( 'contactpanda_merge_email', __( 'Enter a valid final email address before merging.', 'contactpanda' ) );
		}
		if ( 'contacts' === $entity ) {
			foreach ( array( 'email', 'phone' ) as $channel_type ) {
				$selected = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $selected_channels[ $channel_type ] ?? array() ) ) ) );
				$primary = sanitize_text_field( $primary_channels[ $channel_type ] ?? '' );
				if ( $selected && ( ! $primary || ! in_array( $primary, $selected, true ) ) ) return new WP_Error( 'contactpanda_merge_primary_channel', __( 'Choose one primary value for every selected email or phone group.', 'contactpanda' ) );
			}
		}
		$tables = ContactPanda_Schema::table_names();
		$table = $tables[ $entity ];
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return new WP_Error( 'contactpanda_merge_transaction', __( 'The merge could not start safely.', 'contactpanda' ) );
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id IN ({$placeholders}) AND deleted_at IS NULL FOR UPDATE", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and numeric placeholders.
		if ( count( $rows ) !== count( $ids ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'contactpanda_merge_missing', __( 'One of the selected records is no longer active.', 'contactpanda' ) ); }
		$by_id = array();
		foreach ( $rows as $row ) $by_id[ absint( $row['id'] ) ] = $row;
		$donors = array_values( array_diff( $ids, array( $primary_id ) ) );
		$snapshot = array( 'records' => $rows, 'metadata' => array() );
		foreach ( $ids as $snapshot_id ) $snapshot['metadata'][ $snapshot_id ] = 'contacts' === $entity ? self::contact_meta( $snapshot_id ) : self::company_meta( $snapshot_id );
		$now = current_time( 'mysql' );
		try {
			if ( 'contacts' === $entity ) {
				$fields = array( 'first_name', 'last_name', 'email', 'position', 'wp_user_id' );
				$update = array();
				foreach ( $fields as $field ) {
					$source = absint( $choices[ $field ] ?? $primary_id );
					if ( isset( $by_id[ $source ] ) && '' !== trim( (string) $by_id[ $source ][ $field ] ) ) $update[ $field ] = $by_id[ $source ][ $field ];
					if ( array_key_exists( $field, $resolved ) ) $update[ $field ] = 'wp_user_id' === $field ? absint( $resolved[ $field ] ) : sanitize_text_field( $resolved[ $field ] );
				}
				// Release unique values on donor fragments before applying a selected value.
				foreach ( $donors as $donor ) $wpdb->update( $table, array( 'email' => null, 'email_normalized' => null, 'wp_user_id' => null ), array( 'id' => $donor ) );
				if ( isset( $update['email'] ) ) {
					$update['email'] = sanitize_email( $update['email'] );
					$update['email_normalized'] = $update['email'] ? strtolower( $update['email'] ) : null;
				}
				if ( isset( $update['wp_user_id'] ) && ! $update['wp_user_id'] ) $update['wp_user_id'] = null;
				$update['updated_at'] = $now;
				if ( false === $wpdb->update( $table, $update, array( 'id' => $primary_id ) ) ) throw new Exception( 'update' );

				$merge_meta = array( 'anrede', 'titel_vorangestellt', 'titel_nachgestellt', 'berufsbezeichnung', 'primary_phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
				foreach ( ContactPanda_Custom_Fields::definitions( 'contacts' ) as $definition ) $merge_meta[] = ContactPanda_Custom_Fields::meta_key( $definition['key'] );
				foreach ( $merge_meta as $meta_key ) {
					$choice_key = 'meta__' . $meta_key;
					$source = absint( $choices[ $choice_key ] ?? 0 );
					if ( $source && isset( $by_id[ $source ] ) ) { $source_meta = self::contact_meta( $source ); if ( '' !== trim( (string) ( $source_meta[ $meta_key ] ?? '' ) ) ) self::set_contact_meta( $primary_id, $meta_key, $source_meta[ $meta_key ] ); }
					if ( array_key_exists( $choice_key, $resolved ) ) {
						$value = sanitize_text_field( $resolved[ $choice_key ] );
						if ( 'anrede' === $meta_key && ! in_array( $value, array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) $value = '';
						if ( 'country' === $meta_key ) $value = strtoupper( $value );
						self::set_contact_meta( $primary_id, $meta_key, $value );
					}
				}
				$status_priority = array( 'spam' => 6, 'complained' => 6, 'blocked' => 6, 'bounced' => 5, 'hard_bounce' => 5, 'unsubscribed' => 4, 'confirmed' => 3, 'unconfirmed' => 1 );
				$best_status = ''; $best_priority = -1;
				foreach ( $ids as $source ) { $source_meta = self::contact_meta( $source ); $status = sanitize_key( $source_meta['optin_status'] ?? '' ); $priority = $status_priority[ $status ] ?? 0; if ( $priority > $best_priority ) { $best_status = $status; $best_priority = $priority; } }
				if ( $best_status ) self::set_contact_meta( $primary_id, 'optin_status', $best_status );
				$primary_meta = self::contact_meta( $primary_id );
				$aliases = array();
				$merged_channels = array();
				foreach ( $rows as $row ) {
					$row_id = absint( $row['id'] );
					$source_meta = self::contact_meta( $row_id );
					$core_email = strtolower( sanitize_email( $row['email'] ?? '' ) );
					if ( $core_email ) {
						$aliases[] = $core_email;
						$key = 'email:' . $core_email;
						if ( ! isset( $merged_channels[ $key ] ) ) {
							$merged_channels[ $key ] = array(
								'id'               => 'merged-email-' . $row_id,
								'type'             => 'email',
								'label'            => 'work',
								'value'            => $core_email,
								'is_primary'       => 0,
								'marketing_status' => sanitize_key( $source_meta['optin_status'] ?? 'unconfirmed' ),
							);
						}
					}
					$core_phone = sanitize_text_field( $source_meta['primary_phone'] ?? $source_meta['billing_phone'] ?? $source_meta['phone'] ?? '' );
					if ( $core_phone ) {
						$key = 'phone:' . preg_replace( '/\D+/', '', $core_phone );
						if ( ! isset( $merged_channels[ $key ] ) ) {
							$merged_channels[ $key ] = array(
								'id'               => 'merged-phone-' . $row_id,
								'type'             => 'phone',
								'label'            => 'mobile',
								'value'            => $core_phone,
								'is_primary'       => 0,
								'marketing_status' => '',
							);
						}
					}
					foreach ( is_array( $source_meta['contactpanda_contact_channels'] ?? null ) ? $source_meta['contactpanda_contact_channels'] : array() as $channel ) {
						if ( ! is_array( $channel ) || ! in_array( $channel['type'] ?? '', array( 'email', 'phone' ), true ) ) continue;
						$key = sanitize_key( $channel['type'] ) . ':' . strtolower( preg_replace( '/\s+/', '', (string) ( $channel['value'] ?? '' ) ) );
						if ( ! isset( $merged_channels[ $key ] ) ) { $channel['is_primary'] = 0; $merged_channels[ $key ] = $channel; }
						if ( 'email' === ( $channel['type'] ?? '' ) && ! empty( $channel['value'] ) ) $aliases[] = strtolower( sanitize_email( $channel['value'] ) );
					}
				}
				if ( $merged_channels ) {
					$has_channel_selection = ! empty( $selected_channels['_submitted'] );
					if ( $has_channel_selection ) {
						$allowed_channels = array();
						foreach ( array( 'email', 'phone' ) as $channel_type ) foreach ( (array) ( $selected_channels[ $channel_type ] ?? array() ) as $selected_value ) {
							$key = 'email' === $channel_type ? 'email:' . strtolower( sanitize_email( $selected_value ) ) : 'phone:' . preg_replace( '/\D+/', '', $selected_value );
							if ( $key ) $allowed_channels[ $key ] = true;
						}
						$merged_channels = array_intersect_key( $merged_channels, $allowed_channels );
					}
					$primary_email = strtolower( sanitize_email( $primary_channels['email'] ?? $update['email'] ?? $by_id[ $primary_id ]['email'] ?? '' ) );
					$selected_meta = self::contact_meta( $primary_id );
					$primary_phone_value = sanitize_text_field( $primary_channels['phone'] ?? $selected_meta['primary_phone'] ?? '' );
					$primary_phone = preg_replace( '/\D+/', '', $primary_phone_value );
					$email_primary_assigned = false;
					$phone_primary_assigned = false;
					foreach ( $merged_channels as $key => $channel ) {
						$merged_channels[ $key ]['is_primary'] = 0;
						if ( 'email' === $channel['type'] && ! $email_primary_assigned && $primary_email && strtolower( sanitize_email( $channel['value'] ) ) === $primary_email ) { $merged_channels[ $key ]['is_primary'] = 1; $email_primary_assigned = true; }
						if ( 'phone' === $channel['type'] && ! $phone_primary_assigned && $primary_phone && preg_replace( '/\D+/', '', (string) $channel['value'] ) === $primary_phone ) { $merged_channels[ $key ]['is_primary'] = 1; $phone_primary_assigned = true; }
					}
					if ( ! $email_primary_assigned ) foreach ( $merged_channels as $key => $channel ) if ( 'email' === $channel['type'] ) { $merged_channels[ $key ]['is_primary'] = 1; break; }
					if ( ! $phone_primary_assigned ) foreach ( $merged_channels as $key => $channel ) if ( 'phone' === $channel['type'] ) { $merged_channels[ $key ]['is_primary'] = 1; break; }
					self::set_contact_meta( $primary_id, 'contactpanda_contact_channels', array_values( $merged_channels ) );
					if ( $has_channel_selection ) {
						$wpdb->update( $table, array( 'email' => $primary_email ?: null, 'email_normalized' => $primary_email ?: null, 'updated_at' => $now ), array( 'id' => $primary_id ) );
						self::set_contact_meta( $primary_id, 'primary_phone', $primary_phone_value );
					}
				}
				foreach ( $donors as $donor ) {
					foreach ( self::contact_meta( $donor ) as $key => $value ) if ( ! array_key_exists( 'meta__' . $key, $resolved ) && ( ! isset( $primary_meta[ $key ] ) || '' === (string) $primary_meta[ $key ] ) ) self::set_contact_meta( $primary_id, $key, $value );
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['contact_tags']}` (contact_id,tag_id,assigned_at,assigned_by,source) SELECT %d,tag_id,assigned_at,assigned_by,source FROM `{$tables['contact_tags']}` WHERE contact_id=%d", $primary_id, $donor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['company_contacts']}` (company_id,contact_id,relationship_type,position,is_primary,is_primary_company,created_at,updated_at) SELECT company_id,%d,relationship_type,position,is_primary,0,created_at,%s FROM `{$tables['company_contacts']}` WHERE contact_id=%d", $primary_id, $now, $donor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( $tables['consents'], array( 'contact_id' => $primary_id ), array( 'contact_id' => $donor ) );
					$wpdb->update( $tables['activities'], array( 'contact_id' => $primary_id ), array( 'contact_id' => $donor ) );
					$wpdb->delete( $tables['contact_tags'], array( 'contact_id' => $donor ) );
					$wpdb->delete( $tables['company_contacts'], array( 'contact_id' => $donor ) );
				}
				self::ensure_primary_company( $primary_id );
				self::set_contact_meta( $primary_id, 'merged_contact_ids', $ids );
				self::set_contact_meta( $primary_id, 'merged_email_aliases', array_values( array_unique( array_filter( $aliases ) ) ) );
				if ( class_exists( 'ContactPanda_Identity_Store' ) ) {
					ContactPanda_Identity_Store::merge_contacts( $primary_id, $donors );
					ContactPanda_Identity_Store::sync_contact( $primary_id );
				}
				$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET deleted_at=%s, updated_at=%s WHERE id IN (" . implode( ',', array_fill( 0, count( $donors ), '%d' ) ) . ')', array_merge( array( $now, $now ), $donors ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table and numeric placeholders.
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				self::add_activity( $primary_id, 0, 'merge', __( 'Contact records merged', 'contactpanda' ), sprintf( __( 'Source record IDs moved to Trash: %s', 'contactpanda' ), implode( ', ', $donors ) ) );
			} else {
				$fields = array( 'name', 'legal_name', 'industry', 'website', 'email', 'phone_number', 'vat_id', 'street', 'address_2', 'postcode', 'city', 'country' );
				$update = array();
				foreach ( $fields as $field ) {
					$source = absint( $choices[ $field ] ?? $primary_id );
					if ( isset( $by_id[ $source ] ) && '' !== trim( (string) $by_id[ $source ][ $field ] ) ) $update[ $field ] = $by_id[ $source ][ $field ];
					if ( array_key_exists( $field, $resolved ) ) {
						$value = 'website' === $field ? esc_url_raw( $resolved[ $field ] ) : ( 'email' === $field ? sanitize_email( $resolved[ $field ] ) : sanitize_text_field( $resolved[ $field ] ) );
						$update[ $field ] = 'country' === $field ? strtoupper( $value ) : $value;
					}
				}
				$update['updated_at'] = $now;
				if ( false === $wpdb->update( $table, $update, array( 'id' => $primary_id ) ) ) throw new Exception( 'update' );
				foreach ( ContactPanda_Custom_Fields::definitions( 'companies' ) as $definition ) {
					$meta_key = ContactPanda_Custom_Fields::meta_key( $definition['key'] );
					$choice_key = 'meta__' . $meta_key;
					$source = absint( $choices[ $choice_key ] ?? 0 );
					if ( $source && isset( $by_id[ $source ] ) ) { $source_meta = self::company_meta( $source ); if ( '' !== trim( (string) ( $source_meta[ $meta_key ] ?? '' ) ) ) self::set_company_meta( $primary_id, $meta_key, $source_meta[ $meta_key ] ); }
					if ( array_key_exists( $choice_key, $resolved ) ) self::set_company_meta( $primary_id, $meta_key, sanitize_text_field( $resolved[ $choice_key ] ) );
				}
				$affected_contacts = array();
				foreach ( $donors as $donor ) {
					foreach ( self::company_meta( $donor ) as $key => $value ) if ( '' === (string) ( self::company_meta( $primary_id )[ $key ] ?? '' ) ) self::set_company_meta( $primary_id, $key, $value );
					$affected_contacts = array_merge( $affected_contacts, array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT contact_id FROM `{$tables['company_contacts']}` WHERE company_id=%d", $donor ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `{$tables['company_contacts']}` (company_id,contact_id,relationship_type,position,is_primary,is_primary_company,created_at,updated_at) SELECT %d,contact_id,relationship_type,position,is_primary,is_primary_company,created_at,%s FROM `{$tables['company_contacts']}` WHERE company_id=%d", $primary_id, $now, $donor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( $tables['activities'], array( 'company_id' => $primary_id ), array( 'company_id' => $donor ) );
					$wpdb->delete( $tables['company_contacts'], array( 'company_id' => $donor ) );
				}
				foreach ( array_unique( $affected_contacts ) as $contact_id ) self::ensure_primary_company( $contact_id );
				self::set_company_meta( $primary_id, 'merged_company_ids', $ids );
				$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET deleted_at=%s, updated_at=%s WHERE id IN (" . implode( ',', array_fill( 0, count( $donors ), '%d' ) ) . ')', array_merge( array( $now, $now ), $donors ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				self::add_activity( 0, $primary_id, 'merge', __( 'Company records merged', 'contactpanda' ), sprintf( __( 'Source record IDs moved to Trash: %s', 'contactpanda' ), implode( ', ', $donors ) ) );
			}
			if ( class_exists( 'ContactPanda_Identity_Store' ) && ! ContactPanda_Identity_Store::record_merge( $entity, $primary_id, $donors, $snapshot ) ) throw new Exception( 'merge_history' );
			if ( false === $wpdb->query( 'COMMIT' ) ) throw new Exception( 'commit' );
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'contactpanda_merge_failed', __( 'The records could not be merged safely. No changes were retained.', 'contactpanda' ) );
		}
		return array( 'primary_id' => $primary_id, 'merged' => count( $donors ), 'trashed_ids' => $donors );
	}

	public static function import_woocommerce( $dry_run = true ) {
		if ( ! self::standalone() || ! function_exists( 'wc_get_orders' ) ) {
			return new WP_Error( 'contactpanda_woocommerce_import_unavailable', __( 'WooCommerce and the ContactPanda database must be available for this import.', 'contactpanda' ) );
		}
		$customers = array();
		$page = 1;
		do {
			$args = array( 'limit' => 100, 'page' => $page, 'paginate' => true, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC' );
			if ( function_exists( 'wc_get_order_statuses' ) ) $args['status'] = array_keys( wc_get_order_statuses() );
			try {
				$result = wc_get_orders( $args );
			} catch ( Throwable $error ) {
				return new WP_Error( 'contactpanda_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactPanda data was changed.', 'contactpanda' ) );
			}
			if ( is_wp_error( $result ) ) {
				return new WP_Error( 'contactpanda_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactPanda data was changed.', 'contactpanda' ) );
			}
			if ( is_object( $result ) && isset( $result->orders ) ) {
				$orders = (array) $result->orders;
			} elseif ( is_array( $result ) ) {
				$orders = $result;
			} else {
				return new WP_Error( 'contactpanda_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactPanda data was changed.', 'contactpanda' ) );
			}
			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_billing_email' ) ) continue;
				$email = strtolower( sanitize_email( $order->get_billing_email() ) );
				if ( ! $email ) continue;
				$order_data = array(
					'email' => $email, 'user_id' => absint( $order->get_customer_id() ), 'first_name' => $order->get_billing_first_name(), 'last_name' => $order->get_billing_last_name(),
					'phone' => $order->get_billing_phone(), 'company' => $order->get_billing_company(), 'address_1' => $order->get_billing_address_1(), 'address_2' => $order->get_billing_address_2(),
					'postcode' => $order->get_billing_postcode(), 'city' => $order->get_billing_city(), 'state' => $order->get_billing_state(), 'country' => $order->get_billing_country(),
				);
				if ( ! isset( $customers[ $email ] ) ) {
					$customers[ $email ] = $order_data;
				} else {
					// The newest order remains authoritative, but older orders fill empty
					// fragments. This is important for guest orders whose latest checkout
					// may contain only an email address.
					foreach ( $order_data as $field => $value ) {
						if ( '' === trim( (string) ( $customers[ $email ][ $field ] ?? '' ) ) && '' !== trim( (string) $value ) ) $customers[ $email ][ $field ] = $value;
					}
				}
			}
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;
			$page++;
		} while ( $page <= $max_pages );

		$user_args = array( 'fields' => array( 'ID', 'user_email' ) );
		$roles = array_filter( array( get_role( 'customer' ) ? 'customer' : '', get_role( 'subscriber' ) ? 'subscriber' : '' ) );
		$users = array();
		if ( $roles ) {
			$user_args['role__in'] = array_values( $roles );
			try {
				$users = get_users( $user_args );
			} catch ( Throwable $error ) {
				return new WP_Error( 'contactpanda_woocommerce_import_read', __( 'WooCommerce customer data could not be read. No ContactPanda data was changed.', 'contactpanda' ) );
			}
		}
		foreach ( $users as $user ) {
			$email = strtolower( sanitize_email( get_user_meta( $user->ID, 'billing_email', true ) ?: $user->user_email ) );
			if ( ! $email ) continue;
			$row = $customers[ $email ] ?? array( 'email' => $email );
			$map = array( 'first_name', 'last_name', 'phone', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
			foreach ( $map as $field ) {
				$key = 'billing_' . $field;
				$value = get_user_meta( $user->ID, $key, true );
				if ( '' === (string) $value && in_array( $field, array( 'first_name', 'last_name' ), true ) ) $value = get_user_meta( $user->ID, $field, true );
				if ( '' !== (string) $value ) $row[ $field ] = $value;
			}
			$row['user_id'] = (int) $user->ID;
			$customers[ $email ] = $row;
		}

		$report = array( 'found' => count( $customers ), 'new' => 0, 'updated' => 0, 'companies' => 0, 'skipped' => 0, 'dry_run' => $dry_run ? 1 : 0, 'completed_at' => current_time( 'mysql' ) );
		foreach ( $customers as $data ) {
			if ( '' === trim( (string) ( $data['first_name'] ?? '' ) ) && '' === trim( (string) ( $data['last_name'] ?? '' ) ) ) {
				$local = strstr( $data['email'], '@', true );
				if ( is_string( $local ) && preg_match( '/^([\p{L}][\p{L}\'\x{2019}]{1,40})[._-]([\p{L}][\p{L}\'\x{2019}-]{1,60})$/u', $local, $parts ) ) {
					$case = static function ( $value ) { return function_exists( 'mb_convert_case' ) ? mb_convert_case( $value, MB_CASE_TITLE, 'UTF-8' ) : ucfirst( strtolower( $value ) ); };
					$data['first_name'] = $case( $parts[1] );
					$data['last_name'] = $case( $parts[2] );
					$data['name_inferred_from_email'] = 1;
				}
			}
			$existing = self::contact_by_email( $data['email'], true );
			if ( $existing && ! empty( $existing['deleted_at'] ) ) { $report['skipped']++; continue; }
			$existing ? $report['updated']++ : $report['new']++;
			if ( $dry_run ) continue;
			$payload = array( 'email' => $data['email'] );
			if ( ! empty( $data['name_inferred_from_email'] ) ) $payload['meta'] = array( 'name_inferred_from_email' => 1 );
			if ( ! $existing ) $payload['source'] = 'woocommerce';
			foreach ( array( 'first_name', 'last_name' ) as $field ) {
				$incoming = trim( (string) ( $data[ $field ] ?? '' ) );
				$current = $existing ? trim( (string) ( $existing[ $field ] ?? '' ) ) : '';
				if ( $incoming && ( ! $existing || '' === $current ) ) $payload[ $field ] = $data[ $field ];
			}
			$current_phone = $existing ? trim( (string) ( $existing['meta']['primary_phone'] ?? $existing['meta']['billing_phone'] ?? $existing['meta']['phone'] ?? '' ) ) : '';
			if ( ! empty( $data['phone'] ) && ( ! $existing || '' === $current_phone ) ) $payload['primary_phone'] = $data['phone'];
			if ( ! empty( $data['user_id'] ) && ( ! $existing || empty( $existing['user_id'] ) ) ) $payload['user_id'] = absint( $data['user_id'] );
			$contact_id = $existing ? absint( $existing['id'] ) : self::create_contact( $payload );
			if ( is_wp_error( $contact_id ) || ! $contact_id ) { $report['skipped']++; continue; }
			if ( $existing ) self::update_contact( $contact_id, $payload );
			$address = array();
			foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
				$current = $existing ? (string) ( $existing['meta'][ $field ] ?? '' ) : '';
				$incoming = (string) ( $data[ $field ] ?? '' );
				$address[ $field ] = $existing && '' !== trim( $current ) ? $current : $incoming;
			}
			if ( ! self::update_contact_address( $contact_id, $address ) ) { $report['skipped']++; continue; }
			$has_company = false;
			if ( $existing ) {
				global $wpdb;
				$company_contacts_table = ContactPanda_Schema::table_names()['company_contacts'];
				$has_company = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$company_contacts_table}` WHERE contact_id = %d LIMIT 1", $contact_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
			}
			if ( ! $has_company && ! empty( $data['company'] ) ) {
				$company_id = self::company_by_name( $data['company'], true );
				if ( $company_id && ! self::company( $company_id ) ) { $report['skipped']++; continue; }
				if ( ! $company_id ) { $company_id = self::create_company( array( 'name' => $data['company'] ) ); $report['companies']++; }
				if ( $company_id ) self::link_company_contact( $company_id, $contact_id, '', false );
			}
		}
		if ( ! $dry_run ) update_option( 'contactpanda_last_woocommerce_import', $report, false );
		return $report;
	}

	private static function normalize_first_name( $first_name ) {
		$first = trim( preg_split( '/[\s\-]+/u', sanitize_text_field( $first_name ) )[0] ?? '' );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $first, 'UTF-8' ) : strtolower( $first );
	}

	public static function delete_tag( $tag_id ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$tag_id = absint( $tag_id );
		if ( ! $tag_id ) return false;
		$wpdb->delete( $tables['contact_tags'], array( 'tag_id' => $tag_id ) );
		return false !== $wpdb->delete( $tables['tags'], array( 'id' => $tag_id ) );
	}

	public static function assign_tag( $contact_id, $tag_id, $assign = true, $source = 'manual' ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contact_tags'];
		$contact_id = absint( $contact_id );
		$tag_id = absint( $tag_id );
		$source = sanitize_key( $source ) ?: 'manual';
		if ( ! $contact_id || ! $tag_id ) return false;
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `{$table}` WHERE contact_id=%d AND tag_id=%d LIMIT 1", $contact_id, $tag_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( ! $assign ) {
			if ( ! $exists ) return true;
			$deleted = false !== $wpdb->delete( $table, array( 'contact_id' => $contact_id, 'tag_id' => $tag_id ) );
			if ( $deleted ) do_action( 'contactpanda_tag_removed', $contact_id, $tag_id, $source );
			return $deleted;
		}
		if ( $exists ) return true;
		$added = false !== $wpdb->insert( $table, array( 'contact_id' => $contact_id, 'tag_id' => $tag_id, 'assigned_at' => current_time( 'mysql' ), 'assigned_by' => get_current_user_id(), 'source' => $source ) );
		if ( $added ) do_action( 'contactpanda_tag_added', $contact_id, $tag_id, $source );
		return $added;
	}

	/** Record a channel-specific opt-in without changing the email marketing status. */
	public static function record_channel_consent( $contact_id, $channel, $status, $source, $date, $note = '' ) {
		$channel = sanitize_key( $channel );
		if ( ! in_array( $channel, array( 'sms', 'whatsapp' ), true ) ) return false;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'granted', 'revoked' ), true ) ) return false;
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ? $date : current_time( 'Y-m-d' );
		$inserted = $wpdb->insert( $tables['consents'], array( 'contact_id' => absint( $contact_id ), 'purpose' => $channel . '_marketing', 'status' => $status, 'legal_basis' => 'consent', 'source' => sanitize_text_field( $source ), 'proof_text' => sanitize_textarea_field( $note ), 'actor_user_id' => get_current_user_id(), 'occurred_at' => $date . ' 00:00:00', 'created_at' => current_time( 'mysql' ) ) );
		if ( ! $inserted ) return false;
		self::set_contact_meta( $contact_id, $channel . '_marketing_status', 'granted' === $status ? 'confirmed' : 'revoked' );
		self::set_contact_meta( $contact_id, $channel . '_marketing_' . ( 'granted' === $status ? 'confirmed' : 'revoked' ) . '_at', $date );
		self::set_contact_meta( $contact_id, $channel . '_marketing_source', sanitize_text_field( $source ) );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		return self::add_activity( $contact_id, 0, 'consent', sprintf( 'granted' === $status ? __( '%s marketing consent granted', 'contactpanda' ) : __( '%s marketing consent revoked', 'contactpanda' ), strtoupper( $channel ) ), $note );
	}

	public static function channel_is_marketable( $contact, $channel ) {
		$channel = sanitize_key( $channel );
		if ( ! in_array( $channel, array( 'sms', 'whatsapp' ), true ) ) return false;
		$status = self::value( $contact, array( $channel . '_marketing_status' ), '' );
		if ( ! $status && is_array( $contact ) && isset( $contact['meta'] ) && is_array( $contact['meta'] ) ) {
			$status = self::value( $contact['meta'], array( $channel . '_marketing_status' ), '' );
		}
		return in_array( sanitize_key( (string) $status ), array( 'confirmed', 'granted' ), true );
	}

	public static function record_consent( $contact_id, $status, $source, $date, $note = '' ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$occurred = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $date ) ? $date : ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ? $date . ' 00:00:00' : current_time( 'mysql' ) );
		$wpdb->insert( $tables['consents'], array( 'contact_id' => absint( $contact_id ), 'purpose' => 'marketing', 'status' => sanitize_key( $status ), 'legal_basis' => 'consent', 'source' => sanitize_text_field( $source ), 'proof_text' => $note, 'actor_user_id' => get_current_user_id(), 'occurred_at' => $occurred, 'created_at' => current_time( 'mysql' ) ) );
		self::set_contact_meta( $contact_id, 'optin_status', 'granted' === $status ? 'confirmed' : 'unsubscribed' );
		self::set_contact_meta( $contact_id, 'marketing_consent_source', $source );
		self::set_contact_meta( $contact_id, 'granted' === $status ? 'marketing_consent_date' : 'marketing_consent_revoked_date', substr( $occurred, 0, 10 ) );
		self::set_contact_meta( $contact_id, 'granted' === $status ? 'marketing_consent_at' : 'marketing_consent_revoked_at', $occurred );
		$result = self::add_activity( $contact_id, 0, 'consent', 'granted' === $status ? __( 'Marketing consent granted manually', 'contactpanda' ) : __( 'Marketing consent revoked manually', 'contactpanda' ), $note );
		if ( 'granted' === $status ) {
			$contact = self::contact( $contact_id );
			do_action( 'contactpanda_marketing_confirmed', absint( $contact_id ), $contact ? sanitize_email( $contact['email'] ?? '' ) : '' );
		}
		return $result;
	}

	/** Record a product-specific consent without altering general marketing. */
	public static function record_purpose_consent( $contact_id, $purpose, $status, $source, $occurred_at, $proof = '' ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$contact_id = absint( $contact_id );
		$purpose = sanitize_key( $purpose );
		$status = sanitize_key( $status );
		$source = sanitize_key( $source ) ?: 'extension';
		if ( ! $contact_id || ! $purpose || ! in_array( $status, array( 'pending', 'granted', 'revoked' ), true ) ) return false;
		$occurred_at = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $occurred_at ) ? $occurred_at : current_time( 'mysql' );
		$proof = sanitize_textarea_field( $proof );
		$inserted = $wpdb->insert( $tables['consents'], array( 'contact_id' => $contact_id, 'purpose' => $purpose, 'status' => $status, 'legal_basis' => 'consent', 'source' => $source, 'proof_text' => $proof, 'actor_user_id' => get_current_user_id(), 'occurred_at' => $occurred_at, 'created_at' => current_time( 'mysql' ) ) );
		if ( ! $inserted ) return false;
		$meta_key = 'purpose_' . substr( hash( 'sha256', $purpose ), 0, 20 );
		self::set_contact_meta( $contact_id, $meta_key . '_key', $purpose );
		self::set_contact_meta( $contact_id, $meta_key . '_status', $status );
		self::set_contact_meta( $contact_id, $meta_key . '_source', $source );
		self::set_contact_meta( $contact_id, $meta_key . '_at', $occurred_at );
		self::set_contact_meta( $contact_id, $meta_key . '_proof', $proof );
		self::add_activity( $contact_id, 0, 'consent', sprintf( 'Consent status for %1$s: %2$s', $purpose, $status ), $proof, $occurred_at );
		return true;
	}

	public static function add_activity( $contact_id, $company_id, $type, $title, $content, $occurred_at = '' ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['activities'];
		$occurred_at = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $occurred_at ) ? $occurred_at : current_time( 'mysql' );
		return false !== $wpdb->insert( $table, array( 'contact_id' => $contact_id ? absint( $contact_id ) : null, 'company_id' => $company_id ? absint( $company_id ) : null, 'activity_type' => sanitize_key( $type ), 'title' => sanitize_text_field( $title ), 'content' => wp_kses_post( $content ), 'source' => 'contactpanda', 'actor_user_id' => get_current_user_id(), 'occurred_at' => $occurred_at, 'created_at' => current_time( 'mysql' ) ) );
	}

	public static function activities( $contact_id = 0, $company_id = 0, $category = 'all' ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['activities'];
		$category = in_array( $category, array( 'notes', 'activity' ), true ) ? $category : 'all';
		if ( $contact_id ) {
			$items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, activity_type, title AS summary, content, occurred_at AS created_at FROM `{$table}` WHERE contact_id = %d ORDER BY occurred_at DESC LIMIT 100", absint( $contact_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		} else {
			$items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, activity_type, title AS summary, content, occurred_at AS created_at FROM `{$table}` WHERE company_id = %d ORDER BY occurred_at DESC LIMIT 100", absint( $company_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		}
		if ( 'all' !== $category ) {
			$items = array_values( array_filter( $items, static function ( $item ) use ( $category ) {
				$is_note = self::activity_is_note( $item );
				return 'notes' === $category ? $is_note : ! $is_note;
			} ) );
		}
		return array_slice( $items, 0, 50 );
	}

	/** Keep historical automatic entries out of Notes even when older builds stored them as note. */
	private static function activity_is_note( $item ) {
		$type = sanitize_key( $item['activity_type'] ?? '' );
		if ( 'note' !== $type && 0 !== strpos( $type, 'note_' ) ) return false;
		$title = sanitize_text_field( $item['summary'] ?? '' );
		$legacy_activity_titles = array(
			'Contact address', 'Kontaktadresse',
			'WordPress roles changed', 'WordPress-Rollen geändert',
			'WordPress user account', 'WordPress-Benutzerkonto',
		);
		return ! in_array( $title, $legacy_activity_titles, true );
	}

	public static function link_user( $contact_id, $user_id ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contacts'];
		$contact_id = absint( $contact_id );
		$user_id = absint( $user_id );
		if ( ! $contact_id || ! $user_id ) {
			return false;
		}
		$linked_contact = self::contact_by_user_id( $user_id );
		if ( $linked_contact && absint( $linked_contact['id'] ) !== $contact_id ) {
			return false;
		}
		$contact = self::contact( $contact_id );
		if ( ! $contact ) return false;
		if ( class_exists( 'ContactPanda_Identity_Store' ) ) {
			$linked = ContactPanda_Identity_Store::add_identity( $contact_id, 'wordpress', 'user', $user_id, array( 'is_primary' => empty( $contact['user_id'] ), 'source' => 'wordpress' ) );
			if ( is_wp_error( $linked ) || ! $linked ) return false;
		}
		if ( ! empty( $contact['user_id'] ) ) return true;
		return false !== $wpdb->update( $table, array( 'wp_user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $contact_id ) );
	}

	public static function sync_registration( $user_id, $data ) {
		$contact = self::contact_by_email( $data['email'] );
		$contact_id = $contact ? (int) $contact['id'] : self::create_contact( array_merge( $data, array( 'user_id' => $user_id, 'source' => 'registration', 'optin_status' => 'unconfirmed' ) ) );
		if ( is_wp_error( $contact_id ) ) return $contact_id;
		self::update_contact(
			$contact_id,
			array(
				'user_id'         => $user_id,
				'position'        => $data['position'],
				'primary_phone'   => $data['phone'],
				'anrede'          => $data['salutation'] ?? '',
				'vat_id'          => empty( $data['company'] ) ? ( $data['vat_id'] ?? '' ) : '',
				'vat_status'      => empty( $data['company'] ) && ! empty( $data['vat_verified'] ) ? 'valid' : ( empty( $data['company'] ) && ! empty( $data['vat_id'] ) ? 'unverified' : 'unchecked' ),
				'vat_verified_at' => empty( $data['company'] ) && ! empty( $data['vat_verified'] ) ? current_time( 'mysql' ) : '',
			)
		);
		self::update_contact_address(
			$contact_id,
			array(
				'address_1' => $data['billing_address_1'], 'address_2' => $data['billing_address_2'],
				'postcode' => $data['billing_postcode'], 'city' => $data['billing_city'],
				'state' => '', 'country' => $data['billing_country'],
			)
		);
		if ( ! empty( $data['company'] ) ) {
			$company_id = self::company_by_name( $data['company'], true );
			if ( $company_id && ! self::company( $company_id ) ) return $contact_id;
			if ( ! $company_id ) $company_id = self::create_company( array( 'name' => $data['company'], 'industry' => $data['industry'], 'website' => $data['website'], 'vat_id' => $data['vat_id'] ) );
			if ( $company_id ) {
				self::update_company_profile( $company_id, $data );
				self::update_company( $company_id, 'vat_status', ! empty( $data['vat_verified'] ) ? 'valid' : ( empty( $data['vat_id'] ) ? 'unchecked' : 'unverified' ) );
				self::update_company( $company_id, 'vat_verified_at', ! empty( $data['vat_verified'] ) ? current_time( 'mysql' ) : '' );
				self::link_company_contact( $company_id, $contact_id, $data['position'] ?? '', true );
			}
		}
		return $contact_id;
	}

	public static function groundhogg_migration_preview() {
		if ( ! self::ready() || ( ! function_exists( 'wp_get_ability' ) && ! class_exists( '\\Groundhogg\\Contact' ) ) ) return new WP_Error( 'contactpanda_migration_unavailable', __( 'The migration prerequisites are not available.', 'contactpanda' ) );
		$counts = self::counts();
		$last = (array) get_option( 'contactpanda_last_migration_report', array() );
		$cutoff = get_option( 'contactpanda_groundhogg_successful_cutoff', '' ) ?: ( empty( $last['errors'] ) ? ( $last['completed_at'] ?? '' ) : '' );
		$new_contacts = 0; $new_companies = 0;
		$contacts = self::groundhogg_contacts_for_migration();
		foreach ( $contacts as $row ) {
			$id = absint( self::value( $row, array( 'id', 'ID' ) ) ); $email = sanitize_email( self::value( $row, array( 'email' ) ) );
			$created = self::source_datetime( self::value( $row, array( 'date_created', 'created_at', 'created' ) ) );
			if ( $id && ! self::contact_by_source_id($id) && ! self::contact_by_email($email,true) && ! self::import_blocked('contacts',$email) && ( ! $cutoff || ( $created && $created > $cutoff ) ) ) $new_contacts++;
		}
		$company_count = 0;
		$offset = 0;
		$seen = array();
		do {
			$result = self::ability( 'groundhogg-companies/list-companies', array( 'limit' => 100, 'offset' => $offset ) );
			$rows = is_array( $result ) && isset( $result['companies'] ) ? (array) $result['companies'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $company ) { return self::value( $company, array( 'id', 'ID' ) ); }, $rows ) ) );
			if ( $rows && isset( $seen[ $fingerprint ] ) ) break;
			$seen[ $fingerprint ] = true;
			$company_count += count( $rows );
			foreach ( $rows as $company ) {
				$id = absint(self::value($company,array('id','ID'))); $name = self::value($company,array('name','company_name')); $created = self::source_datetime(self::value($company,array('date_created','created_at','created')));
				if ( $id && $name && ! self::company_by_source_id($id) && ! self::company_by_name($name,true) && ! self::import_blocked('companies',$name) && ( ! $cutoff || ( $created && $created > $cutoff ) ) ) $new_companies++;
			}
			$offset += count( $rows );
		} while ( 100 === count( $rows ) && $offset < 1000000 );
		return array(
			'contacts'           => count( $contacts ),
			'new_contacts'       => $new_contacts,
			'new_companies'      => $new_companies,
			'companies'          => $company_count,
			'tags'               => count( self::groundhogg_tags() ),
			'existing_contacts'  => absint( $counts['contacts'] ?? 0 ),
			'existing_companies' => absint( $counts['companies'] ?? 0 ),
			'existing_tags'      => absint( $counts['tags'] ?? 0 ),
		);
	}

	public static function migrate_groundhogg( $include_extensions = true, $policy = 'update' ) {
		global $wpdb;
		$new_only = 'new' === $policy;
		$last = (array) get_option( 'contactpanda_last_migration_report', array() );
		$cutoff = get_option( 'contactpanda_groundhogg_successful_cutoff', '' ) ?: ( empty( $last['errors'] ) ? ( $last['completed_at'] ?? '' ) : '' );
		$started_at = current_time( 'mysql' );
		if ( ! self::ready() || ( ! function_exists( 'wp_get_ability' ) && ! class_exists( '\\Groundhogg\\Contact' ) ) ) return new WP_Error( 'contactpanda_migration_unavailable', __( 'The migration prerequisites are not available.', 'contactpanda' ) );
		$report = array( 'contacts' => 0, 'source_contacts' => 0, 'companies' => 0, 'tags' => 0, 'source_tags' => 0, 'custom_fields' => 0, 'assignments' => 0, 'source_assignments' => 0, 'missing_tags' => array(), 'skipped_tags' => array(), 'skipped' => 0, 'errors' => 0 );
		$source_map = array();
		$source_tag_map = array();
		$custom_field_map = self::import_groundhogg_custom_field_definitions();
		$report['custom_fields'] = count( $custom_field_map );
		$source_tags = self::groundhogg_tags();
		$source_tag_total = count( $source_tags );
		$report['source_tags'] = $source_tag_total;
		do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Importing tags…', 'contactpanda' ), 'processed' => 0, 'total' => $source_tag_total, 'percent' => 5 ) );
		$system_tag_ids = self::groundhogg_system_tag_ids();
		$tag_processed = 0;
		foreach ( $source_tags as $source_tag ) {
			$tag_processed++;
			if ( 0 === $tag_processed % 25 || $tag_processed === $source_tag_total ) {
				do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Importing tags…', 'contactpanda' ), 'processed' => $tag_processed, 'total' => $source_tag_total, 'percent' => 5 + ( $source_tag_total ? (int) floor( 15 * $tag_processed / $source_tag_total ) : 15 ) ) );
			}
			$name = self::value( $source_tag, array( 'name', 'tag_name' ) );
			$source_tag_id = absint( self::value( $source_tag, array( 'id', 'tag_id', 'ID' ) ) );
			if ( $source_tag_id && in_array( $source_tag_id, $system_tag_ids, true ) ) {
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				$report['skipped_tags'][] = sprintf( __( 'Internal Groundhogg status tag “%s” was intentionally omitted.', 'contactpanda' ), $name ?: '#' . $source_tag_id );
				continue;
			}
			if ( ! $name || ! $source_tag_id ) {
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				$report['missing_tags'][] = $source_tag_id ? sprintf( __( 'Tag ID %d has no name.', 'contactpanda' ), $source_tag_id ) : __( 'A Groundhogg tag has no readable ID.', 'contactpanda' );
				continue;
			}
			$new_tag_id = self::create_tag( $name, self::value( $source_tag, array( 'description', 'tag_description' ) ) );
			if ( $new_tag_id ) {
				$source_tag_map[ $source_tag_id ] = $new_tag_id;
			} else {
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				$report['missing_tags'][] = sprintf( __( 'Tag “%s” could not be stored.', 'contactpanda' ), $name );
			}
		}
		$rows = self::groundhogg_contacts_for_migration();
		$contact_total = count( $rows );
		$report['source_contacts'] = $contact_total;
		do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Importing contacts…', 'contactpanda' ), 'processed' => 0, 'total' => $contact_total, 'percent' => 20 ) );
		$contact_processed = 0;
		foreach ( $rows as $row ) {
			$contact_processed++;
			if ( 0 === $contact_processed % 25 || $contact_processed === $contact_total ) {
				do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Importing contacts…', 'contactpanda' ), 'processed' => $contact_processed, 'total' => $contact_total, 'percent' => 20 + ( $contact_total ? (int) floor( 60 * $contact_processed / $contact_total ) : 60 ) ) );
			}
				$source_id = absint( self::value( $row, array( 'id', 'ID' ) ) );
				if ( ! $source_id ) { $report['errors']++; continue; }
				$email = sanitize_email( self::value( $row, array( 'email' ) ) );
				$contact = self::contact_by_source_id( $source_id );
				if ( ! $contact && $email ) $contact = self::contact_by_email( $email, true );
				if ( $contact && ! empty( $contact['deleted_at'] ) ) { $report['skipped']++; continue; }
				$created_for_import = self::source_datetime( self::value( $row, array( 'date_created', 'created_at', 'created' ) ) );
				if ( self::import_blocked( 'contacts', $email ) || ( $new_only && ( $contact || ( $cutoff && ( ! $created_for_import || $created_for_import <= $cutoff ) ) ) ) ) { $report['skipped']++; continue; }
				$preserve_source_timestamps = ! $contact || 'groundhogg' === ( $contact['source'] ?? '' ) || $source_id === absint( $contact['groundhogg_contact_id'] ?? 0 );
				$optin = self::optin_value( $row );
				$source_meta = self::groundhogg_source_meta( $row );
				$imported_meta = self::groundhogg_importable_meta( $source_meta, $custom_field_map );
				$source_created = self::source_datetime( self::value( $row, array( 'date_created', 'created_at', 'created' ) ) );
				$source_updated = self::source_datetime( self::value( $row, array( 'date_updated', 'updated_at', 'modified', 'date_modified' ) ) );
				$existing_meta = $contact ? self::contact_meta( (int) $contact['id'] ) : array();
				$canonical_meta = array( 'email_confirmed' => in_array( $optin, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true ) ? 1 : 0, 'address_1' => self::meta_value( $row, 'street_address_1' ) ?: self::meta_value( $row, 'address_1' ), 'address_2' => self::meta_value( $row, 'street_address_2' ) ?: self::meta_value( $row, 'address_2' ), 'postcode' => self::meta_value( $row, 'postal_zip' ) ?: self::meta_value( $row, 'postcode' ), 'city' => self::meta_value( $row, 'city' ), 'state' => self::meta_value( $row, 'region' ) ?: self::meta_value( $row, 'state' ), 'country' => self::meta_value( $row, 'country' ), 'source_created_at' => $source_created, 'source_updated_at' => $source_updated, 'imported_at' => $existing_meta['imported_at'] ?? current_time( 'mysql' ) );
				$data = array( 'email' => $email, 'first_name' => self::value( $row, array( 'first_name' ) ), 'last_name' => self::value( $row, array( 'last_name' ) ), 'user_id' => absint( self::value( $row, array( 'user_id' ) ) ), 'source' => 'groundhogg', 'groundhogg_contact_id' => $source_id, 'optin_status' => $optin, 'primary_phone' => self::meta_value( $row, 'primary_phone' ), 'titel_vorangestellt' => self::meta_value( $row, 'titel_vorangestellt' ), 'titel_nachgestellt' => self::meta_value( $row, 'titel_nachgestellt' ), 'berufsbezeichnung' => self::meta_value( $row, 'berufsbezeichnung' ), 'meta' => array_merge( $imported_meta, $canonical_meta ) );
				$contact_id = $contact ? (int) $contact['id'] : self::create_contact( $data );
				if ( is_wp_error( $contact_id ) || ! $contact_id ) { $report['errors']++; continue; }
				self::update_contact( $contact_id, $data );
				$historical = array();
				if ( $preserve_source_timestamps && $source_created ) $historical['created_at'] = $source_created;
				if ( $preserve_source_timestamps && $source_updated ) $historical['updated_at'] = $source_updated;
				if ( $historical ) $wpdb->update( ContactPanda_Schema::table_names()['contacts'], $historical, array( 'id' => $contact_id ) );
				$source_map[ $source_id ] = $contact_id;
				$report['contacts']++;
				$contact_tags = isset( $row['tags'] ) ? (array) $row['tags'] : array();
				if ( ! $contact_tags && $source_id ) {
					$contact_tags = self::groundhogg_contact_tags( $source_id );
				}
				foreach ( $contact_tags as $tag ) {
					if ( is_scalar( $tag ) ) {
						$source_tag_id = absint( $tag );
						if ( in_array( $source_tag_id, $system_tag_ids, true ) ) continue;
						$report['source_assignments']++;
						if ( isset( $source_tag_map[ $source_tag_id ] ) ) {
							self::assign_tag( $contact_id, $source_tag_map[ $source_tag_id ] );
							$report['assignments']++;
						} else {
							/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
							$report['missing_tags'][] = sprintf( __( 'Tag ID %d is assigned to a contact but was not found in the tag list.', 'contactpanda' ), $source_tag_id );
						}
						continue;
					}
					$name = self::value( $tag, array( 'name', 'tag_name' ) );
					$source_tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) );
					if ( $source_tag_id && in_array( $source_tag_id, $system_tag_ids, true ) ) continue;
					$report['source_assignments']++;
					if ( $source_tag_id && isset( $source_tag_map[ $source_tag_id ] ) ) {
						self::assign_tag( $contact_id, $source_tag_map[ $source_tag_id ] );
						$report['assignments']++;
						continue;
					}
					if ( ! $name ) {
						/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
						$report['missing_tags'][] = sprintf( __( 'An unreadable tag assignment was found on Groundhogg contact %d.', 'contactpanda' ), $source_id );
						continue;
					}
					$tag_id = self::create_tag( $name );
					if ( $tag_id ) {
						self::assign_tag( $contact_id, $tag_id );
						$report['assignments']++;
						if ( $source_tag_id ) $source_tag_map[ $source_tag_id ] = $tag_id;
					}
				}
		}
		$report['tags'] = count( self::tags() );
		$report['missing_tags'] = array_values( array_unique( $report['missing_tags'] ) );
		$report['skipped_tags'] = array_values( array_unique( $report['skipped_tags'] ) );
		$company_offset = 0;
		do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Importing companies…', 'contactpanda' ), 'processed' => 0, 'total' => 0, 'percent' => 82 ) );
		$seen_company_pages = array();
		do {
			$companies = self::ability( 'groundhogg-companies/list-companies', array( 'limit' => 100, 'offset' => $company_offset, 'expand' => array( 'meta', 'contacts' ) ) );
			$company_rows = is_array( $companies ) && isset( $companies['companies'] ) ? (array) $companies['companies'] : array();
			$company_fingerprint = md5( wp_json_encode( array_map( static function ( $company ) { return self::value( $company, array( 'id', 'ID' ) ); }, $company_rows ) ) );
			if ( $company_rows && isset( $seen_company_pages[ $company_fingerprint ] ) ) break;
			$seen_company_pages[ $company_fingerprint ] = true;
			foreach ( $company_rows as $company ) {
				$name = self::value( $company, array( 'name', 'company_name' ) );
				if ( ! $name ) { $report['skipped']++; continue; }
				$source_company_id = absint( self::value( $company, array( 'id', 'ID' ) ) );
				$company_id = self::company_by_source_id( $source_company_id );
				$company_created = self::source_datetime( self::value( $company, array( 'date_created', 'created_at', 'created' ) ) );
				if ( self::import_blocked( 'companies', $name ) || ( $new_only && ( $company_id || self::company_by_name( $name, true ) || ( $cutoff && ( ! $company_created || $company_created <= $cutoff ) ) ) ) ) { $report['skipped']++; continue; }
				if ( $company_id && ! self::company( $company_id ) ) { $report['skipped']++; continue; }
				if ( $company_id ) {
					self::update_company( $company_id, 'name', $name );
					self::update_company( $company_id, 'industry', self::value( $company, array( 'industry' ) ) );
					self::update_company( $company_id, 'domain', self::value( $company, array( 'domain', 'website' ) ) );
				} else {
					$company_id = self::create_company( array( 'name' => $name, 'industry' => self::value( $company, array( 'industry' ) ), 'website' => self::value( $company, array( 'domain', 'website' ) ) ) );
				}
				if ( ! $company_id ) { $report['errors']++; continue; }
				self::set_company_meta( $company_id, 'groundhogg_company_id', $source_company_id );
				self::update_company( $company_id, 'phone', self::value( $company, array( 'phone' ) ) );
				self::update_company( $company_id, 'address', self::value( $company, array( 'address' ) ) );
				self::update_company( $company_id, 'description', self::value( $company, array( 'description' ) ) );
				$primary = absint( self::value( $company, array( 'primary_contact_id' ) ) );
				$source_contacts = is_array( $company ) && isset( $company['contacts'] ) ? (array) $company['contacts'] : ( is_object( $company ) && method_exists( $company, 'get_contacts' ) ? (array) $company->get_contacts() : array() );
				foreach ( $source_contacts as $source_contact ) {
					$source_contact_id = absint( self::value( $source_contact, array( 'id', 'ID' ) ) );
					if ( isset( $source_map[ $source_contact_id ] ) ) self::link_company_contact( $company_id, $source_map[ $source_contact_id ], '', $primary === $source_contact_id );
				}
				$report['companies']++;
			}
			$company_offset += count( $company_rows );
			do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Importing companies…', 'contactpanda' ), 'processed' => $company_offset, 'total' => 0, 'percent' => min( 96, 82 + (int) floor( $company_offset / 100 ) ) ) );
		} while ( $company_rows && $company_offset < 1000000 );
		$report['completed_at'] = current_time( 'mysql' );
		$report['policy'] = $policy;
		if ( empty( $report['errors'] ) ) update_option( 'contactpanda_groundhogg_successful_cutoff', $started_at, false );
		if ( $include_extensions ) $report = (array) apply_filters( 'contactpanda_groundhogg_migration_report', $report );
		update_option( 'contactpanda_last_migration_report', $report, false );
		do_action( 'contactpanda_groundhogg_migration_progress', array( 'stage' => __( 'Migration completed.', 'contactpanda' ), 'processed' => $report['contacts'] + $report['companies'] + $report['tags'], 'total' => $report['contacts'] + $report['companies'] + $report['tags'], 'percent' => 100 ) );
		return $report;
	}

	/** Only salted identifiers survive permanent deletion, to prevent accidental re-import. */
	public static function import_blocked( $entity, $identity ) {
		if ( '' === trim( (string) $identity ) ) return false;
		$key = hash_hmac( 'sha256', $entity . ':' . strtolower( trim( $identity ) ), wp_salt( 'auth' ) );
		return (bool) get_option( 'contactpanda_import_block_' . $key, false );
	}
	public static function block_reimport( $entity, $identity ) {
		if ( '' !== trim( (string) $identity ) ) update_option( 'contactpanda_import_block_' . hash_hmac( 'sha256', $entity . ':' . strtolower( trim( $identity ) ), wp_salt( 'auth' ) ), 1, false );
	}
	private static function new_uuid() {
		return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x', wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0x0fff ) | 0x4000, wp_rand( 0, 0x3fff ) | 0x8000, wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ), wp_rand( 0, 0xffff ) );
	}

	/** Start an auditable import without changing the external source. */
	public static function begin_import_run( $source, $entity, $filename = '', $source_hash = '' ) {
		global $wpdb;
		if ( ! self::standalone() ) return 0;
		$tables = ContactPanda_Schema::table_names();
		$ok = $wpdb->insert(
			$tables['import_runs'],
			array(
				'uuid'              => self::new_uuid(),
				'source'            => sanitize_key( $source ),
				'entity_type'       => 'companies' === $entity ? 'companies' : 'contacts',
				'original_filename' => sanitize_file_name( $filename ),
				'source_hash'       => preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $source_hash ) ),
				'status'            => 'running',
				'actor_user_id'     => get_current_user_id(),
				'started_at'        => current_time( 'mysql' ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function snapshot_for_import( $entity, $record_id ) {
		global $wpdb;
		$entity = 'companies' === $entity ? 'companies' : 'contacts';
		$tables = ContactPanda_Schema::table_names();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tables[ $entity ]}` WHERE id=%d", absint( $record_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( ! $row ) return array();
		$meta = 'contacts' === $entity ? self::contact_meta( $record_id ) : self::company_meta( $record_id );
		return array( 'core' => $row, 'meta' => $meta );
	}

	public static function log_import_item( $run_id, $entity, $record_id, $action, $before, $after, $source_id = '' ) {
		global $wpdb;
		if ( ! absint( $run_id ) || ! in_array( $action, array( 'created', 'updated', 'skipped' ), true ) ) return false;
		$table = ContactPanda_Schema::table_names()['import_items'];
		return false !== $wpdb->insert( $table, array( 'import_run_id' => absint( $run_id ), 'entity_type' => 'companies' === $entity ? 'companies' : 'contacts', 'record_id' => absint( $record_id ), 'action' => $action, 'before_data' => wp_json_encode( $before ), 'after_data' => wp_json_encode( $after ), 'source_id' => sanitize_text_field( $source_id ), 'created_at' => current_time( 'mysql' ) ) );
	}

	public static function finish_import_run( $run_id, $report ) {
		global $wpdb;
		if ( ! absint( $run_id ) ) return false;
		return false !== $wpdb->update(
			ContactPanda_Schema::table_names()['import_runs'],
			array(
				'status'        => empty( $report['errors'] ) ? 'completed' : 'completed_with_errors',
				'created_count' => absint( $report['created'] ?? 0 ),
				'updated_count' => absint( $report['updated'] ?? 0 ),
				'skipped_count' => absint( $report['skipped'] ?? 0 ),
				'error_count'   => is_array( $report['errors'] ?? null ) ? count( $report['errors'] ) : absint( $report['errors'] ?? 0 ),
				'completed_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $run_id ) )
		);
	}

	public static function import_runs( $limit = 50 ) {
		global $wpdb;
		if ( ! self::standalone() ) return array();
		$table = ContactPanda_Schema::table_names()['import_runs'];
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d", max( 1, min( 200, absint( $limit ) ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	public static function import_record_ids( $run_id, $entity ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['import_items'];
		return array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT record_id FROM `{$table}` WHERE import_run_id=%d AND entity_type=%s", absint( $run_id ), 'companies' === $entity ? 'companies' : 'contacts' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}

	/** Roll back unchanged local import results; conflicting later edits are retained. */
	public static function rollback_import_run( $run_id ) {
		global $wpdb;
		if ( ! self::standalone() ) return new WP_Error( 'contactpanda_import_rollback_mode', __( 'Import rollback is available only for local ContactPanda records.', 'contactpanda' ) );
		$tables = ContactPanda_Schema::table_names();
		$run = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tables['import_runs']}` WHERE id=%d", absint( $run_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( ! $run || 'rolled_back' === $run['status'] ) return new WP_Error( 'contactpanda_import_rollback_invalid', __( 'This import cannot be rolled back.', 'contactpanda' ) );
		$items = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$tables['import_items']}` WHERE import_run_id=%d ORDER BY id DESC", absint( $run_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$report = array( 'restored' => 0, 'trashed' => 0, 'conflicts' => 0 );
		foreach ( $items as $item ) {
			$entity = 'companies' === $item['entity_type'] ? 'companies' : 'contacts';
			$current = self::snapshot_for_import( $entity, $item['record_id'] );
			$after = json_decode( (string) $item['after_data'], true );
			if ( ! $current || wp_json_encode( $current ) !== wp_json_encode( $after ) ) { $report['conflicts']++; continue; }
			if ( 'created' === $item['action'] ) {
				$wpdb->update( $tables[ $entity ], array( 'deleted_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => absint( $item['record_id'] ) ) );
				$report['trashed']++;
				continue;
			}
			$before = json_decode( (string) $item['before_data'], true );
			if ( 'updated' !== $item['action'] || empty( $before['core'] ) ) continue;
			$core = $before['core']; unset( $core['id'] );
			$wpdb->update( $tables[ $entity ], $core, array( 'id' => absint( $item['record_id'] ) ) );
			$meta_table = 'contacts' === $entity ? 'contactmeta' : 'companymeta';
			$meta_key = 'contacts' === $entity ? 'contact_id' : 'company_id';
			$wpdb->delete( $tables[ $meta_table ], array( $meta_key => absint( $item['record_id'] ) ) );
			foreach ( (array) ( $before['meta'] ?? array() ) as $key => $value ) self::set_meta( $meta_table, $meta_key, $item['record_id'], $key, $value );
			$report['restored']++;
		}
		$wpdb->update( $tables['import_runs'], array( 'status' => 'rolled_back', 'rolled_back_at' => current_time( 'mysql' ) ), array( 'id' => absint( $run_id ) ) );
		return $report;
	}

	private static function hydrate_contact( $row ) {
		$meta = self::contact_meta( $row['id'] );
		$row['user_id'] = (int) $row['wp_user_id'];
		$row['meta'] = $meta;
		$row['optin_status'] = isset( $meta['optin_status'] ) ? $meta['optin_status'] : 'unconfirmed';
		$row['tags'] = self::contact_tags( $row['id'] );
		return $row;
	}

	private static function hydrate_company( $row ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$meta = self::company_meta( $row['id'] );
		$row['meta'] = $meta;
		$row['domain'] = $row['website'];
		$row['phone'] = $row['phone_number'];
		$row['address'] = isset( $meta['address'] ) ? $meta['address'] : trim( $row['street'] . "\n" . $row['postcode'] . ' ' . $row['city'] );
		$row['description'] = isset( $meta['description'] ) ? $meta['description'] : '';
		$row['primary_contact_id'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM `{$tables['company_contacts']}` WHERE company_id = %d AND is_primary = 1 LIMIT 1", $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT contact_id FROM `{$tables['company_contacts']}` WHERE company_id = %d ORDER BY is_primary DESC, id ASC", $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		$row['contacts'] = array_values( array_filter( array_map( array( __CLASS__, 'contact' ), $ids ) ) );
		return $row;
	}

	/** Public metadata access for provider-neutral Basic services such as the customer portal. */
	public static function contact_meta( $id ) { return self::meta_rows( 'contactmeta', 'contact_id', $id ); }
	private static function company_meta( $id ) { return self::meta_rows( 'companymeta', 'company_id', $id ); }
	private static function meta_rows( $table_key, $id_key, $id ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()[ $table_key ];
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM `{$table}` WHERE {$id_key} = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed key and internal table.
		$meta = array(); foreach ( (array) $rows as $row ) $meta[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] ); return $meta;
	}
	public static function set_contact_meta( $id, $key, $value ) { return self::set_meta( 'contactmeta', 'contact_id', $id, $key, $value ); }
	private static function set_company_meta( $id, $key, $value ) { return self::set_meta( 'companymeta', 'company_id', $id, $key, $value ); }
	private static function set_meta( $table_key, $id_key, $id, $key, $value ) {
		global $wpdb; $table = ContactPanda_Schema::table_names()[ $table_key ]; $key = sanitize_key( $key );
		$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM `{$table}` WHERE {$id_key} = %d AND meta_key = %s LIMIT 1", absint( $id ), $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed key and internal table.
		$data = array( 'meta_value' => maybe_serialize( $value ) );
		return $meta_id ? false !== $wpdb->update( $table, $data, array( 'meta_id' => $meta_id ) ) : false !== $wpdb->insert( $table, array_merge( array( $id_key => absint( $id ), 'meta_key' => $key ), $data ) );
	}
	private static function contact_tags( $id ) {
		global $wpdb; $t = ContactPanda_Schema::table_names();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT tags.id, tags.name FROM `{$t['tags']}` tags INNER JOIN `{$t['contact_tags']}` rel ON rel.tag_id = tags.id WHERE rel.contact_id = %d ORDER BY tags.name", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names.
	}
	private static function contact_by_source_id( $source_id ) {
		global $wpdb; $tables = ContactPanda_Schema::table_names();
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM `{$tables['contactmeta']}` WHERE meta_key = 'groundhogg_contact_id' AND meta_value = %s LIMIT 1", (string) absint( $source_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		return $id ? self::contact( $id, true ) : false;
	}
	private static function company_by_source_id( $source_id ) {
		global $wpdb; $table = ContactPanda_Schema::table_names()['companymeta'];
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT company_id FROM `{$table}` WHERE meta_key = 'groundhogg_company_id' AND meta_value = %s LIMIT 1", (string) absint( $source_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
	}
	private static function groundhogg_tags() {
		$rows = array();
		$offset = 0;
		$limit = 100;
		$seen_pages = array();
		do {
			$result = self::ability( 'groundhogg/list-tags', array( 'limit' => $limit, 'offset' => $offset ) );
			$page_rows = is_array( $result ) && isset( $result['tags'] ) ? (array) $result['tags'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $tag ) { return self::value( $tag, array( 'id', 'tag_id', 'ID' ) ); }, $page_rows ) ) );
			if ( $page_rows && isset( $seen_pages[ $fingerprint ] ) ) break;
			$seen_pages[ $fingerprint ] = true;
			$rows = array_merge( $rows, $page_rows );
			$offset += count( $page_rows );
		} while ( $page_rows && $offset < 100000 );
		if ( ! $rows && function_exists( '\\Groundhogg\\get_db' ) ) {
			try {
				$rows = (array) \Groundhogg\get_db( 'tags' )->query( array( 'limit' => 100000, 'orderby' => 'tag_name', 'order' => 'ASC' ) );
			} catch ( Throwable $e ) {
				$rows = array();
			}
		}
		$unique = array();
		foreach ( $rows as $row ) {
			$id = absint( self::value( $row, array( 'id', 'tag_id', 'ID' ) ) );
			if ( $id ) $unique[ $id ] = $row;
		}
		return array_values( $unique );
	}
	private static function groundhogg_system_tag_ids() {
		$options = array(
			'gh_confirmed_tag', 'gh_unconfirmed_tag', 'gh_unsubscribed_tag', 'gh_spammed_tag',
			'gh_bounced_tag', 'gh_complained_tag', 'gh_weekly_tag', 'gh_monthly_tag',
			'gh_marketable_tag', 'gh_non_marketable_tag',
		);
		return array_values( array_filter( array_map( 'absint', array_map( 'get_option', $options ) ) ) );
	}
	private static function groundhogg_contact_tags( $contact_id ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) return array();
		try {
			$contact = new \Groundhogg\Contact( absint( $contact_id ) );
			foreach ( array( 'get_tags', 'get_tag_ids' ) as $method ) {
				if ( method_exists( $contact, $method ) ) {
					$tags = $contact->{$method}();
					if ( is_array( $tags ) ) return $tags;
				}
			}
		} catch ( Throwable $e ) {
			return array();
		}
		return array();
	}
	private static function groundhogg_contacts_for_migration() {
		global $wpdb;
		$contacts = array();
		if ( class_exists( '\\Groundhogg\\Contact' ) ) {
			$table = $wpdb->prefix . 'gh_contacts';
			try {
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				if ( $table === $exists ) {
					$ids = (array) $wpdb->get_col( "SELECT ID FROM `{$table}` ORDER BY ID ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed Groundhogg table name using the active WordPress prefix.
					foreach ( $ids as $id ) {
						try {
							$contact = new \Groundhogg\Contact( absint( $id ) );
							if ( method_exists( $contact, 'exists' ) && ! $contact->exists() ) continue;
							if ( class_exists( '\\Groundhogg\\Abilities\\Schemas\\Contact_Schema' ) ) {
								try {
									$contact = \Groundhogg\Abilities\Schemas\Contact_Schema::transform( $contact, array( 'tags', 'meta', 'work_details' ) );
								} catch ( Throwable $error ) {
									// The original Groundhogg contact object still exposes its standard fields and metadata.
								}
							}
							$source_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
							if ( $source_id ) $contacts[ $source_id ] = $contact;
						} catch ( Throwable $error ) {
							continue;
						}
					}
					return array_values( $contacts );
				}
			} catch ( Throwable $error ) {
				$contacts = array();
			}
		}

		$offset = 0;
		$seen_pages = array();
		do {
			$result = self::ability( 'groundhogg/search-contacts', array( 'limit' => 100, 'offset' => $offset, 'expand' => array( 'tags', 'meta', 'work_details' ) ) );
			$rows = is_array( $result ) && isset( $result['contacts'] ) ? (array) $result['contacts'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $contact ) { return self::value( $contact, array( 'id', 'ID' ) ); }, $rows ) ) );
			if ( $rows && isset( $seen_pages[ $fingerprint ] ) ) break;
			$seen_pages[ $fingerprint ] = true;
			foreach ( $rows as $contact ) {
				$source_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				if ( $source_id ) $contacts[ $source_id ] = $contact;
			}
			$offset += count( $rows );
		} while ( $rows && $offset < 1000000 );
		return array_values( $contacts );
	}

	/** Import Groundhogg's field schema without changing the source installation. */
	private static function import_groundhogg_custom_field_definitions() {
		$result = self::ability( 'groundhogg/list-custom-fields', array() );
		$fields = is_array( $result ) && isset( $result['fields'] ) ? (array) $result['fields'] : array();
		if ( ! $fields && class_exists( '\\Groundhogg\\Properties' ) ) {
			try {
				$fields = (array) \Groundhogg\Properties::instance()->get_fields();
			} catch ( Throwable $error ) {
				$fields = array();
			}
		}
		$definitions = (array) get_option( 'contactpanda_custom_fields', array() );
		$known = array();
		foreach ( $definitions as $definition ) {
			if ( is_array( $definition ) && ! empty( $definition['key'] ) && 'contacts' === ( $definition['entity'] ?? '' ) ) {
				$known[ sanitize_key( $definition['key'] ) ] = true;
			}
		}
		$type_map = array(
			'area' => 'textarea', 'textarea' => 'textarea', 'number' => 'number', 'date' => 'date',
			'dropdown' => 'select', 'select' => 'select', 'radio' => 'select', 'checkbox' => 'checkbox',
			'url' => 'url', 'website' => 'url',
		);
		$map = array();
		foreach ( $fields as $field ) {
			$key = sanitize_key( self::value( $field, array( 'name', 'key' ) ) );
			if ( ! $key || 0 === strpos( $key, '_' ) ) continue;
			$map[ $key ] = 'contactpanda_custom_' . $key;
			if ( isset( $known[ $key ] ) ) continue;
			$label = sanitize_text_field( self::value( $field, array( 'label', 'name' ), $key ) );
			$source_type = sanitize_key( self::value( $field, array( 'type' ), 'text' ) );
			$type = $type_map[ $source_type ] ?? 'text';
			$options = is_array( $field ) && isset( $field['options'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $field['options'] ) ) ) : array();
			if ( 'select' === $type && ! $options ) $type = 'text';
			$definitions[] = array(
				'id' => 'groundhogg-' . substr( md5( $key ), 0, 20 ),
				'key' => $key,
				'label' => $label ?: $key,
				'entity' => 'contacts',
				'type' => $type,
				'required' => 0,
				'sort' => is_array( $field ) && isset( $field['order'] ) ? (int) $field['order'] : 10,
				'options' => $options,
			);
			$known[ $key ] = true;
		}
		update_option( 'contactpanda_custom_fields', array_values( $definitions ), false );
		return $map;
	}

	/** Read all expanded Groundhogg metadata, including arbitrary integration fields. */
	private static function groundhogg_source_meta( $source ) {
		if ( is_array( $source ) && isset( $source['meta'] ) && is_array( $source['meta'] ) ) return $source['meta'];
		if ( is_object( $source ) && method_exists( $source, 'get_all_meta' ) ) {
			try {
				$meta = $source->get_all_meta();
				return is_array( $meta ) ? $meta : array();
			} catch ( Throwable $error ) {
				return array();
			}
		}
		return array();
	}

	/** Preserve public source metadata and mirror declared fields to ContactPanda custom-field keys. */
	private static function groundhogg_importable_meta( $source_meta, $custom_field_map ) {
		$meta = array();
		foreach ( (array) $source_meta as $source_key => $value ) {
			$key = sanitize_key( $source_key );
			if ( ! $key || 0 === strpos( $key, '_' ) || is_object( $value ) ) continue;
			if ( is_array( $value ) ) {
				$value = array_values( array_filter( array_map( static function ( $item ) { return is_scalar( $item ) ? sanitize_text_field( (string) $item ) : ''; }, $value ), 'strlen' ) );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$value = sanitize_textarea_field( (string) $value );
			} else {
				continue;
			}
			$meta[ $key ] = $value;
			if ( isset( $custom_field_map[ $key ] ) ) {
				$meta[ $custom_field_map[ $key ] ] = is_array( $value ) ? implode( ', ', $value ) : $value;
			}
		}
		return $meta;
	}

	private static function ability( $name, $input ) { try { $ability = wp_get_ability( $name ); if ( ! $ability ) return false; $result = $ability->execute( $input ); return is_wp_error( $result ) ? false : $result; } catch ( Throwable $e ) { return false; } }
	private static function value( $source, $keys, $default = '' ) { foreach ( $keys as $key ) { if ( is_array( $source ) && isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) && '' !== (string) $source[ $key ] ) return (string) $source[ $key ]; if ( is_object( $source ) && method_exists( $source, $key ) ) { $value = $source->{$key}(); if ( is_scalar( $value ) && '' !== (string) $value ) return (string) $value; } $getter = 'get_' . $key; if ( is_object( $source ) && method_exists( $source, $getter ) ) { $value = $source->{$getter}(); if ( is_scalar( $value ) && '' !== (string) $value ) return (string) $value; } if ( is_object( $source ) && isset( $source->{$key} ) && is_scalar( $source->{$key} ) ) return (string) $source->{$key}; } return $default; }
	private static function source_datetime( $value ) { if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) return ''; $timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value ); return $timestamp > 0 ? wp_date( 'Y-m-d H:i:s', $timestamp, wp_timezone() ) : ''; }
	private static function meta_value( $source, $key ) { if ( is_array( $source ) && isset( $source['meta'][ $key ] ) ) return is_scalar( $source['meta'][ $key ] ) ? (string) $source['meta'][ $key ] : ''; if ( is_object( $source ) && method_exists( $source, 'get_meta' ) ) { try { $value = $source->get_meta( $key ); return is_scalar( $value ) ? (string) $value : ''; } catch ( Throwable $error ) { return ''; } } return ''; }
	private static function optin_value( $source ) { $value = is_array( $source ) && isset( $source['optin_status'] ) ? $source['optin_status'] : self::value( $source, array( 'optin_status' ), 'unconfirmed' ); if ( is_array( $value ) && isset( $value['value'] ) ) return is_numeric( $value['value'] ) ? (int) $value['value'] : sanitize_key( $value['value'] ); if ( is_scalar( $value ) ) return is_numeric( $value ) ? (int) $value : sanitize_key( $value ); return 'unconfirmed'; }
}
