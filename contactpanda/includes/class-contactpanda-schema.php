<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Schema {
	// Request-local schema metadata only; never cache contacts, consent, or queue state.
	private static $table_cache = array();
	private static $column_cache = array();

	const VERSION = '2.3.0';
	const VERSION_OPTION = 'contactpanda_db_version';

	/**
	 * Runs database changes only when the stored schema is older.
	 *
	 * This method is intentionally cheap on normal requests and can therefore
	 * be called before any ContactPanda data is queried.
	 */
	public static function maybe_upgrade() {
		if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			return true;
		}
		return self::install();
	}

	public static function table_names() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'cp_';
		return array(
			'contacts'         => $prefix . 'contacts',
			'companies'        => $prefix . 'companies',
			'company_contacts' => $prefix . 'company_contacts',
			'tags'             => $prefix . 'tags',
			'contact_tags'     => $prefix . 'contact_tags',
			'consents'         => $prefix . 'consents',
			'activities'       => $prefix . 'activities',
			'contactmeta'      => $prefix . 'contactmeta',
			'companymeta'      => $prefix . 'companymeta',
			'salutations'      => $prefix . 'salutations',
			'import_runs'      => $prefix . 'import_runs',
			'import_items'     => $prefix . 'import_items',
			'contact_channels' => $prefix . 'contact_channels',
			'external_identities' => $prefix . 'external_identities',
			'merge_history'    => $prefix . 'merge_history',
		);
	}

	public static function status() {
		$present = array();
		foreach ( self::table_names() as $key => $table ) {
			$present[ $key ] = self::table_exists( $table );
		}
		return array(
			'present' => $present,
			'count'   => count( array_filter( $present ) ),
			'total'   => count( $present ),
			'version' => (string) get_option( self::VERSION_OPTION, '' ),
		);
	}

	public static function install() {
		global $wpdb;
		$status = self::status();
		if ( $status['total'] === $status['count'] && self::VERSION === $status['version'] ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$tables = self::table_names();
		$charset = $wpdb->get_charset_collate();
		$sql = array();

		$sql[] = "CREATE TABLE {$tables['contacts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			email varchar(190) DEFAULT NULL,
			email_normalized varchar(190) DEFAULT NULL,
			title_prefix varchar(100) NOT NULL DEFAULT '',
			first_name varchar(190) NOT NULL DEFAULT '',
			last_name varchar(190) NOT NULL DEFAULT '',
			position varchar(190) NOT NULL DEFAULT '',
			phone_country_code varchar(12) NOT NULL DEFAULT '',
			phone_number varchar(60) NOT NULL DEFAULT '',
			vat_id varchar(40) NOT NULL DEFAULT '',
			vat_status varchar(30) NOT NULL DEFAULT 'unchecked',
			vat_verified_at datetime DEFAULT NULL,
			status varchar(30) NOT NULL DEFAULT 'active',
			source varchar(60) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY wp_user_id (wp_user_id),
			UNIQUE KEY email_normalized (email_normalized),
			KEY name_lookup (last_name,first_name),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['companies']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			legal_name varchar(190) NOT NULL DEFAULT '',
			industry varchar(190) NOT NULL DEFAULT '',
			website varchar(255) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone_country_code varchar(12) NOT NULL DEFAULT '',
			phone_number varchar(60) NOT NULL DEFAULT '',
			vat_id varchar(40) NOT NULL DEFAULT '',
			vat_status varchar(30) NOT NULL DEFAULT 'unchecked',
			vat_verified_at datetime DEFAULT NULL,
			street varchar(190) NOT NULL DEFAULT '',
			address_2 varchar(190) NOT NULL DEFAULT '',
			postcode varchar(30) NOT NULL DEFAULT '',
			city varchar(120) NOT NULL DEFAULT '',
			country char(2) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY name (name),
			KEY vat_id (vat_id),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['company_contacts']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned NOT NULL,
			relationship_type varchar(60) NOT NULL DEFAULT '',
			position varchar(190) NOT NULL DEFAULT '',
			is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
			is_primary_company tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY company_contact (company_id,contact_id),
			KEY contact_id (contact_id),
			KEY contact_primary_company (contact_id,is_primary_company)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['tags']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			slug varchar(190) NOT NULL,
			description text NULL,
			color varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY name (name)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['contact_tags']} (
			contact_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			assigned_at datetime NOT NULL,
			assigned_by bigint(20) unsigned DEFAULT NULL,
			source varchar(60) NOT NULL DEFAULT 'manual',
			PRIMARY KEY  (contact_id,tag_id),
			KEY tag_id (tag_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['consents']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			purpose varchar(100) NOT NULL,
			status varchar(30) NOT NULL,
			legal_basis varchar(60) NOT NULL DEFAULT '',
			source varchar(60) NOT NULL DEFAULT '',
			form_id bigint(20) unsigned DEFAULT NULL,
			policy_page_id bigint(20) unsigned DEFAULT NULL,
			policy_version varchar(60) NOT NULL DEFAULT '',
			proof_text longtext NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			occurred_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_purpose (contact_id,purpose),
			KEY occurred_at (occurred_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['activities']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned DEFAULT NULL,
			company_id bigint(20) unsigned DEFAULT NULL,
			activity_type varchar(60) NOT NULL,
			title varchar(190) NOT NULL DEFAULT '',
			content longtext NULL,
			source varchar(60) NOT NULL DEFAULT '',
			external_type varchar(60) NOT NULL DEFAULT '',
			external_id varchar(190) NOT NULL DEFAULT '',
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			occurred_at datetime NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY company_id (company_id),
			KEY activity_type (activity_type),
			KEY occurred_at (occurred_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['contactmeta']} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) DEFAULT NULL,
			meta_value longtext NULL,
			PRIMARY KEY  (meta_id),
			KEY contact_id (contact_id),
			KEY contact_key (contact_id,meta_key),
			KEY meta_key (meta_key)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['companymeta']} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) DEFAULT NULL,
			meta_value longtext NULL,
			PRIMARY KEY  (meta_id),
			KEY company_id (company_id),
			KEY meta_key (meta_key)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['salutations']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			first_name varchar(190) NOT NULL,
			first_name_normalized varchar(190) NOT NULL,
			language varchar(10) NOT NULL DEFAULT 'de',
			salutation varchar(20) NOT NULL DEFAULT '',
			source varchar(60) NOT NULL DEFAULT 'manual',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY language_name (language,first_name_normalized),
			KEY salutation (salutation)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['import_runs']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			source varchar(60) NOT NULL DEFAULT '',
			entity_type varchar(30) NOT NULL DEFAULT 'contacts',
			original_filename varchar(255) NOT NULL DEFAULT '',
			source_hash char(64) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'running',
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			created_count int(10) unsigned NOT NULL DEFAULT 0,
			updated_count int(10) unsigned NOT NULL DEFAULT 0,
			skipped_count int(10) unsigned NOT NULL DEFAULT 0,
			error_count int(10) unsigned NOT NULL DEFAULT 0,
			started_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			rolled_back_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY source (source),
			KEY entity_type (entity_type),
			KEY started_at (started_at)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['import_items']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_run_id bigint(20) unsigned NOT NULL,
			entity_type varchar(30) NOT NULL DEFAULT 'contacts',
			record_id bigint(20) unsigned NOT NULL,
			action varchar(30) NOT NULL DEFAULT '',
			before_data longtext NULL,
			after_data longtext NULL,
			source_id varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY import_run_id (import_run_id),
			KEY entity_record (entity_type,record_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['contact_channels']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			channel_type varchar(30) NOT NULL,
			label varchar(60) NOT NULL DEFAULT '',
			channel_value varchar(255) NOT NULL,
			normalized_value varchar(255) NOT NULL,
			is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'active',
			consent_status varchar(30) NOT NULL DEFAULT '',
			verified_at datetime DEFAULT NULL,
			source varchar(60) NOT NULL DEFAULT 'contactpanda',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY contact_channel (contact_id,channel_type,normalized_value(160)),
			KEY channel_lookup (channel_type,normalized_value(160)),
			KEY contact_primary (contact_id,channel_type,is_primary)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['external_identities']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			provider varchar(60) NOT NULL,
			identity_type varchar(60) NOT NULL,
			external_id varchar(190) NOT NULL,
			is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'active',
			metadata_json longtext NULL,
			source varchar(60) NOT NULL DEFAULT 'contactpanda',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_identity (provider,identity_type,external_id),
			KEY contact_provider (contact_id,provider,identity_type),
			KEY contact_status (contact_id,status)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['merge_history']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entity_type varchar(30) NOT NULL,
			primary_id bigint(20) unsigned NOT NULL,
			source_ids_json longtext NULL,
			snapshot_json longtext NULL,
			action varchar(30) NOT NULL DEFAULT 'merge',
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			reverted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY entity_primary (entity_type,primary_id),
			KEY created_at (created_at)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		$old_salutation_index = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='first_name_normalized'", $tables['salutations'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		if ( $old_salutation_index ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i DROP INDEX `first_name_normalized`", $tables['salutations'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Explicit lifecycle operation on the fixed ContactPanda table allowlist only; reset/drop requires the administrator action guard and confirmation; no source-system table is changed.
		}
		$new_salutation_index = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='language_name'", $tables['salutations'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		if ( ! $new_salutation_index ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD UNIQUE KEY `language_name` (`language`,`first_name_normalized`)", $tables['salutations'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Explicit lifecycle operation on the fixed ContactPanda table allowlist only; reset/drop requires the administrator action guard and confirmation; no source-system table is changed.
		}

		self::$table_cache = array();
		self::$column_cache = array();
		$status = self::status();
		if ( $status['total'] !== $status['count'] ) {
			return new WP_Error( 'contactpanda_schema_incomplete', __( 'ContactPanda could not create all standalone database tables.', 'contactpanda' ) );
		}
		if ( ! self::required_columns_exist() ) {
			return new WP_Error( 'contactpanda_schema_columns_incomplete', __( 'ContactPanda could not create all required database fields.', 'contactpanda' ) );
		}
		self::backfill_identity_indexes();
		update_option( self::VERSION_OPTION, self::VERSION, false );
		return true;
	}

	public static function drop_all() {
		global $wpdb;
		foreach ( array_reverse( self::table_names() ) as $table ) {
			$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Explicit lifecycle operation on the fixed ContactPanda table allowlist only; reset/drop requires the administrator action guard and confirmation; no source-system table is changed.
		}
		self::$table_cache = array();
		self::$column_cache = array();
		delete_option( self::VERSION_OPTION );
	}

	public static function reset_data() {
		global $wpdb;
		$status = self::status();
		if ( $status['total'] !== $status['count'] ) {
			return new WP_Error( 'contactpanda_schema_incomplete', __( 'ContactPanda standalone tables are incomplete.', 'contactpanda' ) );
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		foreach ( array_reverse( self::table_names() ) as $table ) {
			$wpdb->query( $wpdb->prepare( "TRUNCATE TABLE %i", $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery -- exact internally generated table name.
		}
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		delete_option( 'contactpanda_last_migration_report' );
		do_action( 'contactpanda_after_reset_data' );
		return true;
	}

	private static function table_exists( $table ) {
		global $wpdb;
		if ( array_key_exists( $table, self::$table_cache ) ) return self::$table_cache[ $table ];
		self::$table_cache[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema metadata has a request-local cache keyed by prefixed table/column and invalidated after install/drop; wpdb is required for schema discovery. The immediately preceding lookup uses the request-local schema cache; install/drop invalidates it, including cached negative results.
		return self::$table_cache[ $table ];
	}

	private static function required_columns_exist() {
		$tables = self::table_names();
		$required = array(
			'contacts'   => array( 'uuid', 'title_prefix', 'vat_id', 'vat_status', 'vat_verified_at' ),
			'companies'  => array( 'uuid', 'legal_name', 'vat_id', 'vat_status', 'vat_verified_at' ),
			'company_contacts' => array( 'company_id', 'contact_id', 'is_primary', 'is_primary_company' ),
			'tags'       => array( 'description' ),
			'salutations' => array( 'first_name_normalized', 'language', 'salutation', 'source' ),
			'import_runs' => array( 'uuid', 'source', 'entity_type', 'status', 'started_at' ),
			'import_items' => array( 'import_run_id', 'entity_type', 'record_id', 'action' ),
			'contact_channels' => array( 'contact_id', 'channel_type', 'channel_value', 'normalized_value', 'is_primary', 'status' ),
			'external_identities' => array( 'contact_id', 'provider', 'identity_type', 'external_id', 'status' ),
			'merge_history' => array( 'entity_type', 'primary_id', 'source_ids_json', 'snapshot_json', 'action' ),
		);
		foreach ( $required as $table_key => $columns ) {
			foreach ( $columns as $column ) {
				if ( ! self::column_exists( $tables[ $table_key ], $column ) ) {
					return false;
				}
			}
		}
		return true;
	}

	private static function column_exists( $table, $column ) {
		global $wpdb;
		$key = $table . ':' . $column;
		if ( array_key_exists( $key, self::$column_cache ) ) return self::$column_cache[ $key ];
		self::$column_cache[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, $column ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema metadata has a request-local cache keyed by prefixed table/column and invalidated after install/drop; wpdb is required for schema discovery. The immediately preceding lookup uses the request-local schema cache; install/drop invalidates it, including cached negative results.
		return self::$column_cache[ $key ];
	}

	/**
	 * Build the new lookup indexes from the compatibility columns once.
	 *
	 * This is additive only: source records, consent history and the established
	 * primary email/user columns remain untouched.
	 */
	private static function backfill_identity_indexes() {
		global $wpdb;
		$tables = self::table_names();
		$now = current_time( 'mysql' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$wpdb->prepare( "INSERT IGNORE INTO %i
			(contact_id,channel_type,label,channel_value,normalized_value,is_primary,status,source,created_at,updated_at)
			SELECT id,'email','primary',email,email_normalized,1,'active',source,created_at,updated_at
			FROM %i WHERE email_normalized IS NOT NULL AND email_normalized<>''", $tables['contact_channels'], $tables['contacts'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internally generated table names.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$wpdb->prepare( "INSERT IGNORE INTO %i
				(contact_id,provider,identity_type,external_id,is_primary,status,source,created_at,updated_at)
				SELECT id,'wordpress','user',CAST(wp_user_id AS CHAR),1,'active','schema_backfill',%s,%s
				FROM %i WHERE wp_user_id IS NOT NULL AND wp_user_id>0", $tables['external_identities'], $now, $now, $tables['contacts'] )
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internally generated table names.
		$meta_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT contact_id,meta_key,meta_value FROM %i WHERE meta_key IN ('primary_phone','groundhogg_contact_id')", $tables['contactmeta'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		foreach ( $meta_rows as $meta_row ) {
			$value = trim( (string) maybe_unserialize( $meta_row['meta_value'] ) );
			if ( '' === $value ) continue;
			if ( 'primary_phone' === $meta_row['meta_key'] ) {
				$normalized = preg_replace( '/\D+/', '', $value );
				if ( '' === $normalized ) continue;
				$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (contact_id,channel_type,label,channel_value,normalized_value,is_primary,status,source,created_at,updated_at) VALUES (%d,'phone','primary',%s,%s,1,'active','schema_backfill',%s,%s)", $tables['contact_channels'], absint( $meta_row['contact_id'] ), $value, $normalized, $now, $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			} else {
				$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (contact_id,provider,identity_type,external_id,is_primary,status,source,created_at,updated_at) VALUES (%d,'groundhogg','contact',%s,0,'active','schema_backfill',%s,%s)", $tables['external_identities'], absint( $meta_row['contact_id'] ), sanitize_text_field( $value ), $now, $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			}
		}
	}
}
