<?php

defined( 'ABSPATH' ) || exit;

/**
 * Normalized, provider-neutral contact channels and external identities.
 *
 * The legacy primary columns stay authoritative compatibility mirrors. This
 * registry adds multiple channels and identities without rewriting source
 * systems, orders, invoices, consent history, or WordPress users.
 */
final class ContactPanda_Identity_Store {
	public static function normalize_channel( $type, $value ) {
		$type = sanitize_key( $type );
		if ( 'email' === $type ) return strtolower( sanitize_email( $value ) );
		if ( 'phone' === $type ) return preg_replace( '/\D+/', '', (string) $value );
		return strtolower( trim( sanitize_text_field( $value ) ) );
	}

	public static function contact_id_by_channel( $type, $value, $include_deleted = false ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$normalized = self::normalize_channel( $type, $value );
		if ( '' === $normalized ) return 0;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT ch.contact_id FROM %i ch INNER JOIN %i c ON c.id=ch.contact_id WHERE ch.channel_type=%s AND ch.normalized_value=%s AND ch.status='active' AND (%d = 1 OR c.deleted_at IS NULL) ORDER BY ch.is_primary DESC,ch.id ASC LIMIT 1", $tables['contact_channels'], $tables['contacts'], sanitize_key( $type ), $normalized, (int) $include_deleted ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function contact_id_by_identity( $provider, $identity_type, $external_id, $include_deleted = false ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$external_id = trim( sanitize_text_field( $external_id ) );
		if ( '' === $external_id ) return 0;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT i.contact_id FROM %i i INNER JOIN %i c ON c.id=i.contact_id WHERE i.provider=%s AND i.identity_type=%s AND i.external_id=%s AND i.status='active' AND (%d = 1 OR c.deleted_at IS NULL) ORDER BY i.is_primary DESC,i.id ASC LIMIT 1", $tables['external_identities'], $tables['contacts'], sanitize_key( $provider ), sanitize_key( $identity_type ), $external_id, (int) $include_deleted ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function add_channel( $contact_id, $type, $value, $args = array() ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['contact_channels'];
		$contact_id = absint( $contact_id );
		$type = sanitize_key( $type );
		$value = 'email' === $type ? sanitize_email( $value ) : sanitize_text_field( $value );
		$normalized = self::normalize_channel( $type, $value );
		if ( ! $contact_id || ! $type || ! $normalized ) return false;
		$args = wp_parse_args( $args, array( 'label' => '', 'is_primary' => 0, 'status' => 'active', 'consent_status' => '', 'verified_at' => null, 'source' => 'contactpanda' ) );
		$now = current_time( 'mysql' );
		if ( ! empty( $args['is_primary'] ) ) $wpdb->update( $table, array( 'is_primary' => 0, 'updated_at' => $now ), array( 'contact_id' => $contact_id, 'channel_type' => $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$sql = "INSERT INTO %i (contact_id,channel_type,label,channel_value,normalized_value,is_primary,status,consent_status,verified_at,source,created_at,updated_at) VALUES (%d,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE label=VALUES(label),channel_value=VALUES(channel_value),is_primary=VALUES(is_primary),status=VALUES(status),consent_status=VALUES(consent_status),verified_at=VALUES(verified_at),source=VALUES(source),updated_at=VALUES(updated_at)";
		return false !== $wpdb->query( $wpdb->prepare( $sql, $table, $contact_id, $type, sanitize_key( $args['label'] ), $value, $normalized, empty( $args['is_primary'] ) ? 0 : 1, sanitize_key( $args['status'] ), sanitize_key( $args['consent_status'] ), $args['verified_at'] ?: null, sanitize_key( $args['source'] ), $now, $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Only fixed SQL clauses/generated placeholder lists are concatenated; the complete query is passed to wpdb::prepare with identifier and scalar arguments in SQL order. No request value is concatenated.
	}

	public static function add_identity( $contact_id, $provider, $identity_type, $external_id, $args = array() ) {
		global $wpdb;
		$table = ContactPanda_Schema::table_names()['external_identities'];
		$contact_id = absint( $contact_id );
		$provider = sanitize_key( $provider );
		$identity_type = sanitize_key( $identity_type );
		$external_id = trim( sanitize_text_field( $external_id ) );
		if ( ! $contact_id || ! $provider || ! $identity_type || '' === $external_id ) return false;
		$existing = self::contact_id_by_identity( $provider, $identity_type, $external_id, true );
		if ( $existing && $existing !== $contact_id ) return new WP_Error( 'contactpanda_identity_conflict', __( 'This external account is already linked to another person.', 'contactpanda' ) );
		$args = wp_parse_args( $args, array( 'is_primary' => 0, 'status' => 'active', 'metadata' => array(), 'source' => 'contactpanda' ) );
		$now = current_time( 'mysql' );
		$sql = "INSERT INTO %i (contact_id,provider,identity_type,external_id,is_primary,status,metadata_json,source,created_at,updated_at) VALUES (%d,%s,%s,%s,%d,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id),is_primary=VALUES(is_primary),status=VALUES(status),metadata_json=VALUES(metadata_json),source=VALUES(source),updated_at=VALUES(updated_at)";
		return false !== $wpdb->query( $wpdb->prepare( $sql, $table, $contact_id, $provider, $identity_type, $external_id, empty( $args['is_primary'] ) ? 0 : 1, sanitize_key( $args['status'] ), wp_json_encode( (array) $args['metadata'] ), sanitize_key( $args['source'] ), $now, $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Only fixed SQL clauses/generated placeholder lists are concatenated; the complete query is passed to wpdb::prepare with identifier and scalar arguments in SQL order. No request value is concatenated.
	}

	public static function sync_contact( $contact_id ) {
		$contact = ContactPanda_Store::contact( $contact_id, true );
		if ( ! $contact ) return false;
		if ( ! empty( $contact['email'] ) ) self::add_channel( $contact_id, 'email', $contact['email'], array( 'label' => 'primary', 'is_primary' => 1, 'source' => $contact['source'] ?? 'contactpanda' ) );
		$meta = (array) ( $contact['meta'] ?? array() );
		if ( ! empty( $meta['primary_phone'] ) ) self::add_channel( $contact_id, 'phone', $meta['primary_phone'], array( 'label' => 'primary', 'is_primary' => 1, 'source' => $contact['source'] ?? 'contactpanda' ) );
		foreach ( is_array( $meta['contactpanda_contact_channels'] ?? null ) ? $meta['contactpanda_contact_channels'] : array() as $channel ) {
			if ( ! is_array( $channel ) || empty( $channel['type'] ) || empty( $channel['value'] ) ) continue;
			self::add_channel( $contact_id, $channel['type'], $channel['value'], array( 'label' => $channel['label'] ?? '', 'is_primary' => $channel['is_primary'] ?? 0, 'consent_status' => $channel['marketing_status'] ?? '', 'source' => $contact['source'] ?? 'contactpanda' ) );
		}
		if ( ! empty( $contact['user_id'] ) ) self::add_identity( $contact_id, 'wordpress', 'user', $contact['user_id'], array( 'is_primary' => 1, 'source' => 'wordpress' ) );
		if ( ! empty( $meta['groundhogg_contact_id'] ) ) self::add_identity( $contact_id, 'groundhogg', 'contact', $meta['groundhogg_contact_id'], array( 'source' => 'groundhogg' ) );
		return true;
	}

	public static function merge_contacts( $primary_id, $donor_ids ) {
		global $wpdb;
		$tables = ContactPanda_Schema::table_names();
		$primary_id = absint( $primary_id );
		foreach ( array_filter( array_map( 'absint', (array) $donor_ids ) ) as $donor_id ) {
			$wpdb->query( $wpdb->prepare( "DELETE donor FROM %i donor INNER JOIN %i target ON target.contact_id=%d AND target.channel_type=donor.channel_type AND target.normalized_value=donor.normalized_value WHERE donor.contact_id=%d", $tables['contact_channels'], $tables['contact_channels'], $primary_id, $donor_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$wpdb->update( $tables['contact_channels'], array( 'contact_id' => $primary_id, 'updated_at' => current_time( 'mysql' ) ), array( 'contact_id' => $donor_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$wpdb->update( $tables['external_identities'], array( 'contact_id' => $primary_id, 'updated_at' => current_time( 'mysql' ) ), array( 'contact_id' => $donor_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		}
	}

	public static function record_merge( $entity_type, $primary_id, $source_ids, $snapshot ) {
		global $wpdb;
		return false !== $wpdb->insert( ContactPanda_Schema::table_names()['merge_history'], array( 'entity_type' => sanitize_key( $entity_type ), 'primary_id' => absint( $primary_id ), 'source_ids_json' => wp_json_encode( array_values( array_map( 'absint', (array) $source_ids ) ) ), 'snapshot_json' => wp_json_encode( $snapshot ), 'action' => 'merge', 'actor_user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points.
	}
}
