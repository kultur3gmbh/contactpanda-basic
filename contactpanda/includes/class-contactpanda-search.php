<?php

defined( 'ABSPATH' ) || exit;

/** Shared Basic search policy. Integrations use the same fields and word semantics. */
final class ContactPanda_Search {
	public static function fields() {
		$fields = array( 'first_name' => __( 'First name', 'contactpanda' ), 'last_name' => __( 'Last name', 'contactpanda' ), 'email' => __( 'Email addresses', 'contactpanda' ), 'phone' => __( 'Phone numbers', 'contactpanda' ), 'company' => __( 'Company', 'contactpanda' ) );
		foreach ( ContactPanda_Custom_Fields::integration_definitions( 'contacts' ) as $field ) {
			if ( in_array( $field['type'], array( 'text', 'textarea', 'email', 'url', 'tel', 'number', 'select', 'multiselect', 'date' ), true ) ) $fields[ ContactPanda_Custom_Fields::meta_key( $field['key'] ) ] = $field['label'];
		}
		return $fields;
	}

	public static function selected() {
		$settings = (array) get_option( 'contactpanda_general_settings', array() );
		return array_values( array_intersect( array_keys( self::fields() ), (array) ( $settings['search_fields'] ?? array_keys( self::fields() ) ) ) );
	}

	public static function words( $query ) {
		return array_values( array_unique( preg_split( '/\s+/u', trim( sanitize_text_field( (string) $query ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() ) );
	}

	public static function matches( $query, $values ) {
		foreach ( self::words( $query ) as $word ) {
			$found = false;
			foreach ( (array) $values as $value ) {
				if ( ! is_scalar( $value ) ) continue;
				$value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
				if ( function_exists( 'mb_stripos' ) ? false !== mb_stripos( $value, $word, 0, 'UTF-8' ) : false !== stripos( $value, $word ) ) { $found = true; break; }
			}
			if ( ! $found ) return false;
		}
		return true;
	}

	/** Prepared predicate, with one AND group per word and OR across enabled fields. */
	public static function predicate( $query ) {
		global $wpdb;
		$t = ContactPanda_Schema::table_names(); $clauses = array(); $params = array();
		foreach ( self::words( $query ) as $word ) {
			$parts = array(); $like = '%' . $wpdb->esc_like( $word ) . '%';
			foreach ( self::selected() as $field ) {
				if ( in_array( $field, array( 'first_name', 'last_name', 'email' ), true ) ) { $parts[] = 'c.' . $field . ' LIKE %s'; $params[] = $like; }
				if ( in_array( $field, array( 'email', 'phone' ), true ) ) {
					$parts[] = "EXISTS (SELECT 1 FROM %i ch WHERE ch.contact_id=c.id AND ch.channel_type=%s AND ch.status='active' AND ch.channel_value LIKE %s)";
					array_push( $params, $t['contact_channels'], $field, $like );
				}
				if ( 'phone' === $field ) {
					$parts[] = "(c.phone_number LIKE %s OR EXISTS (SELECT 1 FROM %i pm WHERE pm.contact_id=c.id AND pm.meta_key IN ('primary_phone','phone','phone_number','contactpanda_custom_phone') AND pm.meta_value LIKE %s))";
					array_push( $params, $like, $t['contactmeta'], $like );
				} elseif ( 'company' === $field ) {
					$parts[] = 'EXISTS (SELECT 1 FROM %i rel INNER JOIN %i co ON co.id=rel.company_id WHERE rel.contact_id=c.id AND co.deleted_at IS NULL AND co.name LIKE %s)';
					array_push( $params, $t['company_contacts'], $t['companies'], $like );
					$parts[] = "EXISTS (SELECT 1 FROM %i cm WHERE cm.contact_id=c.id AND cm.meta_key IN ('company','company_name','contactpanda_custom_company_name') AND cm.meta_value LIKE %s)";
					array_push( $params, $t['contactmeta'], $like );
				} elseif ( 0 === strpos( $field, ContactPanda_Custom_Fields::PREFIX ) ) {
					$parts[] = 'EXISTS (SELECT 1 FROM %i m WHERE m.contact_id=c.id AND m.meta_key=%s AND m.meta_value LIKE %s)';
					array_push( $params, $t['contactmeta'], $field, $like );
				}
			}
			$clauses[] = $parts ? '(' . implode( ' OR ', $parts ) . ')' : '1=0';
		}
		return array( $clauses ? ' AND ' . implode( ' AND ', $clauses ) : '', $params );
	}

	/** Read-only provider fallback uses exactly the same selected fields. */
	public static function filter_contacts( $contacts, $query ) {
		$fields = self::selected(); $companies = in_array( 'company', $fields, true ) ? ContactPanda_Plugin::extension_company_map() : array();
		return array_values( array_filter( (array) $contacts, static function ( $contact ) use ( $fields, $companies, $query ) {
			$values = array(); $id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) );
			foreach ( $fields as $field ) {
				if ( in_array( $field, array( 'first_name', 'last_name', 'email' ), true ) ) $values[] = ContactPanda_Plugin::extension_value( $contact, array( $field, 'get_' . $field ) );
				elseif ( 'phone' === $field ) { foreach ( array( 'primary_phone', 'phone', 'phone_number', 'contactpanda_custom_phone' ) as $key ) $values[] = ContactPanda_Plugin::extension_contact_meta_value( $contact, $key ); }
				elseif ( 'company' === $field ) {
					foreach ( (array) ( $companies[ $id ] ?? array() ) as $company ) $values[] = ContactPanda_Plugin::extension_value( $company, array( 'name', 'company_name', 'get_name' ) );
					foreach ( array( 'company', 'company_name', 'contactpanda_custom_company_name' ) as $key ) $values[] = ContactPanda_Plugin::extension_contact_meta_value( $contact, $key );
				} else $values[] = ContactPanda_Plugin::extension_contact_meta_value( $contact, $field );
			}
			$channels = ContactPanda_Plugin::extension_contact_meta_value( $contact, 'contactpanda_contact_channels' );
			if ( is_string( $channels ) ) { $channels = maybe_unserialize( $channels ); if ( is_string( $channels ) ) $channels = json_decode( $channels, true ); }
			foreach ( is_array( $channels ) ? $channels : array() as $channel ) {
				if ( is_array( $channel ) && in_array( $channel['type'] ?? '', array( 'email', 'phone' ), true ) && in_array( $channel['type'], $fields, true ) ) $values[] = $channel['value'] ?? '';
			}
			return self::matches( $query, $values );
		} ) );
	}

	public static function render_settings( $settings ) {
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Quick search', 'contactpanda' ) . '</h2><p>' . esc_html__( 'All search words must occur in the same person record, in any order. These fields apply to person searches in lists and connected Panda selection windows.', 'contactpanda' ) . '</p><input type="hidden" name="contactpanda_general_settings[search_fields_present]" value="1"><fieldset><legend>' . esc_html__( 'Searchable person fields', 'contactpanda' ) . '</legend>';
		foreach ( self::fields() as $key => $label ) echo '<label class="contactpanda-search-field"><input type="checkbox" name="contactpanda_general_settings[search_fields][]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, self::selected(), true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		echo '</fieldset><p class="description">' . esc_html__( 'Selecting no fields disables text matching. Access permissions remain unchanged.', 'contactpanda' ) . '</p></section>';
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Person and company workspace', 'contactpanda' ) . '</h2><input type="hidden" name="contactpanda_general_settings[independent_scroll]" value="0"><label><input type="checkbox" name="contactpanda_general_settings[independent_scroll]" value="1"' . checked( ! empty( $settings['independent_scroll'] ), true, false ) . '> ' . esc_html__( 'Scroll the left and right columns independently', 'contactpanda' ) . '</label><p class="description">' . esc_html__( 'Applies to person and company details on wide screens. Narrow layouts scroll as one page.', 'contactpanda' ) . '</p></section>';
	}
}
