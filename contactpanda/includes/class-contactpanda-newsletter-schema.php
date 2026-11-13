<?php

defined( 'ABSPATH' ) || exit;

/**
 * Database contract shared by the free Basic newsletter and optional extensions.
 *
 * Newsletter data deliberately lives in its own tables so activating or
 * disconnecting a licence never changes CRM, WooCommerce, or source data.
 */
final class ContactPanda_Newsletter_Schema {
	// Request-local schema metadata only; never cache contacts, consent, or queue state.
	private static $table_cache = array();
	private static $column_cache = array();

	const VERSION = '1.3.0';
	const VERSION_OPTION = 'contactpanda_newsletter_db_version';

	public static function table_names() {
		global $wpdb;
		return array(
			'templates'       => $wpdb->prefix . 'cp_newsletter_templates',
			'segments'        => $wpdb->prefix . 'cp_segments',
			'campaign_groups' => $wpdb->prefix . 'cp_newsletter_campaign_groups',
			'campaigns'       => $wpdb->prefix . 'cp_newsletter_campaigns',
			'recipients'      => $wpdb->prefix . 'cp_newsletter_recipients',
			'suppressions'    => $wpdb->prefix . 'cp_newsletter_suppressions',
		);
	}

	// Backwards-compatible names used by the newsletter editor contract.
	public static function newsletter_table_names() {
		return self::table_names();
	}

	public static function install_newsletters() {
		return self::install();
	}

	/**
	 * Remove only a voluntary newsletter unsubscribe suppression.
	 *
	 * Delivery, spam, complaint and administrator blocks are deliberately left
	 * untouched and must never be cleared by an ordinary opt-in.
	 *
	 * @param string $email Confirmed email address.
	 * @return bool Whether an unsubscribe suppression was removed.
	 */
	public static function clear_unsubscribe_suppression( $email ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$table = self::table_names()['suppressions'];
		if ( ! self::table_exists( $table ) ) {
			return false;
		}
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$table,
			array(
				'email_hash' => hash( 'sha256', $email ),
				'reason'     => 'unsubscribed',
			)
		);
	}

	public static function suppression( $email ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) return false;
		$table = self::table_names()['suppressions'];
		if ( ! self::table_exists( $table ) ) return false;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE email_hash=%s", $table, hash( 'sha256', $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function set_admin_block( $contact_id, $email, $source = 'administrator' ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) return false;
		$existing = self::suppression( $email );
		if ( $existing && 'blocked' !== ( $existing['reason'] ?? '' ) ) return false;
		$table = self::table_names()['suppressions'];
		if ( ! self::table_exists( $table ) ) return false;
		return false !== $wpdb->replace( $table, array( 'contact_id' => absint( $contact_id ), 'email_hash' => hash( 'sha256', $email ), 'reason' => 'blocked', 'source' => sanitize_text_field( $source ), 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function clear_admin_block( $email ) {
		global $wpdb;
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) return false;
		$table = self::table_names()['suppressions'];
		if ( ! self::table_exists( $table ) ) return false;
		return (bool) $wpdb->delete( $table, array( 'email_hash' => hash( 'sha256', $email ), 'reason' => 'blocked' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda schema/lifecycle data has no WordPress object API; identifiers are fixed/allowlisted and query values are bound. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
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

	public static function maybe_upgrade() {
		$status = self::status();
		if ( self::VERSION === $status['version'] && $status['total'] === $status['count'] ) {
			return true;
		}
		return self::install();
	}

	public static function install() {
		if ( self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ) {
			$status = self::status();
			if ( $status['count'] === $status['total'] ) return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::table_names();
		$charset = $wpdb->get_charset_collate();
		$sql     = array();

		$sql[] = "CREATE TABLE {$t['templates']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			subject varchar(255) NOT NULL DEFAULT '',
			preview_text varchar(255) NOT NULL DEFAULT '',
			from_name varchar(190) NOT NULL DEFAULT '',
			reply_to varchar(190) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'draft',
			content_json longtext NULL,
			content_html longtext NULL,
			email_background varchar(20) NOT NULL DEFAULT '',
			accent_color varchar(20) NOT NULL DEFAULT '',
			content_background varchar(20) NOT NULL DEFAULT '',
			text_color varchar(20) NOT NULL DEFAULT '',
			font_family varchar(190) NOT NULL DEFAULT '',
			content_width smallint unsigned NOT NULL DEFAULT 600,
			external_source varchar(60) NOT NULL DEFAULT '',
			external_id bigint(20) unsigned NOT NULL DEFAULT 0,
			imported_readonly tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source_object (external_source,external_id),
			KEY status (status),
			KEY updated_at (updated_at)
		) $charset;";
		$sql[] = "CREATE TABLE {$t['segments']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			entity_type varchar(20) NOT NULL DEFAULT 'contacts',
			relation_mode varchar(3) NOT NULL DEFAULT 'and',
			rules_json longtext NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY updated_at (updated_at)
		) $charset;";
		$sql[] = "CREATE TABLE {$t['campaign_groups']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			description text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
		) $charset;";
		$sql[] = "CREATE TABLE {$t['campaigns']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL,
			template_id bigint(20) unsigned NOT NULL,
			segment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			manual_contact_ids longtext NULL,
			audience_mode varchar(12) NOT NULL DEFAULT 'dynamic',
			audience_source varchar(30) NOT NULL DEFAULT 'contacts',
			recipient_search varchar(190) NOT NULL DEFAULT '',
			audience_resolved_at datetime DEFAULT NULL,
			ignore_marketing_consent tinyint(1) unsigned NOT NULL DEFAULT 0,
			consent_override_reason varchar(60) NOT NULL DEFAULT '',
			consent_override_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			consent_override_at datetime DEFAULT NULL,
			consent_override_count int unsigned NOT NULL DEFAULT 0,
			campaign_group_id bigint(20) unsigned NOT NULL DEFAULT 0,
			language varchar(12) NOT NULL DEFAULT 'de_DE',
			subject varchar(255) NOT NULL DEFAULT '',
			subject_b varchar(255) NOT NULL DEFAULT '',
			ab_test_percent tinyint unsigned NOT NULL DEFAULT 0,
			utm_source varchar(190) NOT NULL DEFAULT '',
			utm_medium varchar(190) NOT NULL DEFAULT '',
			utm_campaign varchar(190) NOT NULL DEFAULT '',
			utm_content varchar(190) NOT NULL DEFAULT '',
			from_name varchar(190) NOT NULL DEFAULT '',
			from_email varchar(190) NOT NULL DEFAULT '',
			reply_to varchar(190) NOT NULL DEFAULT '',
			mails_per_hour smallint unsigned NOT NULL DEFAULT 300,
			status varchar(30) NOT NULL DEFAULT 'draft',
			scheduled_at datetime DEFAULT NULL,
			external_source varchar(60) NOT NULL DEFAULT '',
			external_id bigint(20) unsigned NOT NULL DEFAULT 0,
			archived_html longtext NULL,
			opened_count int unsigned NOT NULL DEFAULT 0,
			clicked_count int unsigned NOT NULL DEFAULT 0,
			unsubscribed_count int unsigned NOT NULL DEFAULT 0,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			total_recipients int unsigned NOT NULL DEFAULT 0,
			queued_count int unsigned NOT NULL DEFAULT 0,
			sent_count int unsigned NOT NULL DEFAULT 0,
			failed_count int unsigned NOT NULL DEFAULT 0,
			skipped_count int unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY source_object (external_source,external_id),
			KEY campaign_group_id (campaign_group_id),
			KEY status (status),
			KEY scheduled_at (scheduled_at)
		) $charset;";
		$sql[] = "CREATE TABLE {$t['recipients']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL,
			first_name varchar(190) NOT NULL DEFAULT '',
			last_name varchar(190) NOT NULL DEFAULT '',
			subject_variant char(1) NOT NULL DEFAULT 'a',
			status varchar(30) NOT NULL DEFAULT 'queued',
			reason varchar(80) NOT NULL DEFAULT '',
			attempts tinyint unsigned NOT NULL DEFAULT 0,
			sent_at datetime DEFAULT NULL,
			opened_at datetime DEFAULT NULL,
			clicked_at datetime DEFAULT NULL,
			error_message text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_email (campaign_id,email),
			KEY campaign_status (campaign_id,status)
		) $charset;";
		$sql[] = "CREATE TABLE {$t['suppressions']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email_hash char(64) NOT NULL,
			reason varchar(80) NOT NULL DEFAULT 'unsubscribed',
			source varchar(80) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email_hash (email_hash),
			KEY contact_id (contact_id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::$table_cache = array();
		self::$column_cache = array();
		$status = self::status();
		if ( $status['total'] !== $status['count'] || ! self::required_columns_exist() ) {
			return new WP_Error( 'contactpanda_newsletter_schema_incomplete', __( 'ContactPanda could not create all required newsletter tables.', 'contactpanda' ) );
		}
		update_option( self::VERSION_OPTION, self::VERSION, false );
		return true;
	}

	public static function drop_all() {
		global $wpdb;
		foreach ( array_reverse( self::table_names() ) as $table ) {
			$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery -- Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Explicit lifecycle operation on the fixed ContactPanda table allowlist only; reset/drop requires the administrator action guard and confirmation; no source-system table is changed.
		}
		self::$table_cache = array();
		self::$column_cache = array();
		delete_option( self::VERSION_OPTION );
	}

	private static function table_exists( $table ) {
		global $wpdb;
		if ( array_key_exists( $table, self::$table_cache ) ) return self::$table_cache[ $table ];
		self::$table_cache[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema metadata has a request-local cache keyed by prefixed table/column and invalidated after install/drop; wpdb is required for schema discovery. The immediately preceding lookup uses the request-local schema cache; install/drop invalidates it, including cached negative results.
		return self::$table_cache[ $table ];
	}

	private static function required_columns_exist() {
		$t = self::table_names();
		$required = array(
			'templates'       => array( 'content_json', 'content_html', 'status' ),
			'segments'        => array( 'entity_type', 'relation_mode', 'rules_json' ),
			'campaign_groups' => array( 'name', 'description' ),
			'campaigns'       => array( 'segment_id', 'manual_contact_ids', 'audience_mode', 'audience_source', 'recipient_search', 'ignore_marketing_consent', 'from_email', 'status', 'subject_b', 'ab_test_percent', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content' ),
			'recipients'      => array( 'campaign_id', 'email', 'status', 'subject_variant' ),
			'suppressions'    => array( 'email_hash', 'reason' ),
		);
		foreach ( $required as $key => $columns ) {
			foreach ( $columns as $column ) {
				if ( ! self::column_exists( $t[ $key ], $column ) ) {
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
}
