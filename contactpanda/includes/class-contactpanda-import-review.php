<?php
defined( 'ABSPATH' ) || exit;

/** User-bound, two-step import review. No writes occur while preparing a review. */
final class ContactPanda_Import_Review {
	/** Names are staged here; tags are created only when the reviewed import commits. */
	public static function render_extra_tags() {
		$names = array(); foreach ( ContactPanda_Plugin::extension_tags() as $tag ) { $name = ContactPanda_Plugin::extension_value( $tag, array( 'name', 'tag_name' ), '' ); if ( $name ) $names[] = $name; }
		echo '<section class="contactpanda-import-extra-tags"><h3>' . esc_html__( 'Additional tags for this import', 'contactpanda' ) . '</h3><p>' . esc_html__( 'Add these tags to every successfully imported person. Existing tags and tags from file columns are retained. Tags are saved only after confirmation.', 'contactpanda' ) . '</p><div data-panda-tag-select data-name="extra_tags[]" data-choices="' . esc_attr( wp_json_encode( $names, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ) . '" data-placeholder="' . esc_attr__( 'Search or create a tag', 'contactpanda' ) . '" data-create="' . esc_attr__( 'Create new tag', 'contactpanda' ) . '"></div></section>';
	}

	public static function extra_tags( $record, $tags ) {
		$names = array(); foreach ( array_merge( preg_split( '/[,;\r\n]+/u', $record['tags'] ?? '' ), (array) $tags ) as $name ) { if ( ! is_scalar( $name ) ) continue; $name = trim( sanitize_text_field( $name ) ); if ( '' === $name || strlen( $name ) > 200 ) continue; $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name ); $names[$key] = $name; }
		if ( $names ) $record['tags'] = implode( ',', array_values( $names ) );
		return $record;
	}

	public static function mapping( $raw, $headers, $fields ) {
		$out = array();
		foreach ( (array) $raw as $field => $indexes ) {
			if ( ! isset( $fields[$field] ) ) continue;
			$values = array();
			foreach ( (array) $indexes as $index ) {
				if ( is_scalar( $index ) && '' === (string) $index ) continue;
				if ( ! is_scalar( $index ) || ! ctype_digit( (string) $index ) || ! array_key_exists( (int) $index, $headers ) ) return new WP_Error( 'mapping', __( 'Invalid field mapping.', 'contactpanda' ) );
				$values[] = (int) $index;
			}
			if ( $values ) $out[$field] = array_values( array_unique( $values ) );
		}
		return $out;
	}

	public static function split_name( $name ) {
		$name = trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
		if ( false !== strpos( $name, ',' ) ) { list( $last, $first ) = explode( ',', $name, 2 ); return array( trim( $first ), trim( $last ) ); }
		$parts = explode( ' ', $name );
		if ( count( $parts ) < 2 ) return array( $name, '' );
		$last = array_pop( $parts );
		while ( count( $parts ) > 1 && in_array( strtolower( end( $parts ) ), array( 'von', 'van', 'der', 'den', 'de', 'del', 'da', 'di', 'zu', 'zur' ), true ) ) $last = array_pop( $parts ) . ' ' . $last;
		return array( implode( ' ', $parts ), $last );
	}

	public static function record( $row, $mapping ) {
		$record = array();
		foreach ( $mapping as $field => $indexes ) {
			$values = array_values( array_filter( array_map( static function ( $i ) use ( $row ) { return sanitize_textarea_field( $row[$i] ?? '' ); }, (array) $indexes ), static function ( $v ) { return '' !== $v; } ) );
			$record[$field] = implode( 'tags' === $field ? ',' : ( 'note_title' === $field ? ' · ' : "\n\n" ), $values );
		}
		if ( ! empty( $record['full_name'] ) ) {
			list( $first, $last ) = self::split_name( $record['full_name'] );
			if ( empty( $record['first_name'] ) ) $record['first_name'] = $first;
			if ( empty( $record['last_name'] ) ) $record['last_name'] = $last;
		}
		unset( $record['full_name'] );
		if ( isset( $record['tags'] ) ) {
			$tags = array(); foreach ( preg_split( '/[,;\r\n]+/u', $record['tags'] ) as $tag ) { $tag = trim( $tag ); if ( '' !== $tag ) $tags[ function_exists( 'mb_strtolower' ) ? mb_strtolower( $tag ) : strtolower( $tag ) ] = $tag; }
			$record['tags'] = implode( ',', array_values( $tags ) );
		}
		return $record;
	}

	public static function existing( $entity, $record ) {
		if ( 'companies' !== $entity ) return ContactPanda_Plugin::extension_contact_by_email( $record['email'] ?? '' );
		if ( ContactPanda_Store::standalone() ) { $id = ContactPanda_Store::company_by_name( $record['name'] ?? '' ); return $id ? ContactPanda_Store::company( $id ) : false; }
		foreach ( ContactPanda_Plugin::extension_companies() as $company ) if ( 0 === strcasecmp( ContactPanda_Plugin::extension_value( $company, array( 'name', 'company_name' ) ), $record['name'] ?? '' ) ) return $company;
		return false;
	}

	public static function old_value( $existing, $field ) {
		$aliases = array( 'phone' => 'primary_phone', 'salutation' => 'anrede', 'title_prefix' => 'titel_vorangestellt', 'title_suffix' => 'titel_nachgestellt', 'address_1' => 'street', 'city' => 'billing_city', 'postcode' => 'billing_postcode', 'country' => 'billing_country', 'state' => 'billing_state', 'address_2' => 'billing_address_2' );
		$key = 0 === strpos( $field, 'custom__' ) ? ContactPanda_Custom_Fields::meta_key( substr( $field, 8 ) ) : $field;
		if ( is_array( $existing ) ) $existing = array_merge( (array) ( $existing['meta'] ?? array() ), $existing );
		$value = ContactPanda_Plugin::extension_value( $existing, array( $key, $aliases[$field] ?? $key ), '' );
		if ( '' === $value && is_object( $existing ) && method_exists( $existing, 'get_meta' ) ) $value = $existing->get_meta( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Returns reviewed rows on commit; otherwise renders the review and stops. */
	public static function resolve( &$data, $token, $mapping ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_csv_import' );
		$key = 'contactpanda_review_' . get_current_user_id() . '_' . $token;
		$review = get_transient( $key );
		if ( ! empty( $_POST['review_confirmed'] ) && is_array( $review ) && ! empty( $_POST['review_id'] ) && hash_equals( $review['id'], sanitize_text_field( wp_unslash( $_POST['review_id'] ) ) ) ) {
			$data['mode'] = $review['mode']; $rows = $review['rows'];
			foreach ( $rows as $i => &$record ) {
				$existing = self::existing( $data['entity'], $record );
				if ( ! hash_equals( $review['fingerprints'][$i], self::fingerprint( $existing, array_keys( $record ) ) ) ) { delete_transient( $key ); wp_die( esc_html__( 'A record changed after the preview. Return to the import and review it again.', 'contactpanda' ) ); }
				foreach ( $review['conflicts'][$i] ?? array() as $field ) if ( empty( $_POST['replace'][$i][$field] ) ) unset( $record[$field] );
				if ( ! empty( $review['split'][$i] ) ) foreach ( array( 'first_name', 'last_name' ) as $field ) if ( isset( $record[$field] ) ) $record[$field] = sanitize_text_field( wp_unslash( $_POST['names'][$i][$field] ?? $record[$field] ) );
			}
			unset( $record ); delete_transient( $key ); return $rows;
		}
		$review = array( 'id' => wp_generate_uuid4(), 'rows' => array(), 'conflicts' => array(), 'fingerprints' => array(), 'split' => array(), 'mode' => $data['mode'] );
		foreach ( $data['rows'] as $i => $row ) {
			$record = self::record( $row, $mapping ); if ( 'contacts' === $data['entity'] ) $record = self::extra_tags( $record, map_deep( wp_unslash( $_POST['extra_tags'] ?? array() ), 'sanitize_text_field' ) ); $existing = self::existing( $data['entity'], $record );
			$review['rows'][$i] = $record; $review['fingerprints'][$i] = self::fingerprint( $existing, array_keys( $record ) );
			if ( isset( $mapping['full_name'] ) ) $review['split'][$i] = true;
			if ( $existing && 'update' === $data['mode'] ) foreach ( $record as $field => $value ) {
				if ( in_array( $field, array( 'email', 'name', 'tags', 'note_title', 'note_content', 'marketing' ), true ) ) continue;
				$old = self::old_value( $existing, $field ); if ( '' !== $value && '' !== $old && $old !== $value ) $review['conflicts'][$i][] = $field;
			}
		}
		set_transient( $key, $review, HOUR_IN_SECONDS );
		$labels = ContactPanda_Data_Tools::csv_fields( $data['entity'] );
		wp_enqueue_style( 'contactpanda-import-review', CONTACTPANDA_URL . 'assets/panda-import-review.css', array(), CONTACTPANDA_ASSET_VERSION );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html__( 'Review import changes', 'contactpanda' ) . '</title>';
		wp_print_styles( 'contactpanda-import-review' );
		echo '</head><body><main><h1>' . esc_html__( 'Review import changes', 'contactpanda' ) . '</h1><p>' . esc_html__( 'No changes have been saved. Select each existing value you want to replace. Notes are appended and tags are combined. Check the proposed name split before confirming.', 'contactpanda' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'contactpanda_csv_import' );
		foreach ( array( 'action' => 'contactpanda_csv_import', 'csv_token' => $token, 'review_id' => $review['id'], 'mode' => $data['mode'], 'confirmed' => '1', 'review_confirmed' => '1' ) as $name => $value ) echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		foreach ( $mapping as $field => $indexes ) foreach ( $indexes as $index ) echo '<input type="hidden" name="mapping[' . esc_attr( $field ) . '][]" value="' . esc_attr( $index ) . '">';
		echo '<table><thead><tr><th>' . esc_html__( 'Row', 'contactpanda' ) . '</th><th>' . esc_html__( 'Field', 'contactpanda' ) . '</th><th>' . esc_html__( 'Existing value', 'contactpanda' ) . '</th><th>' . esc_html__( 'Imported value', 'contactpanda' ) . '</th><th>' . esc_html__( 'Replace', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $review['rows'] as $i => $record ) {
			$existing = self::existing( $data['entity'], $record );
			echo '<tr class="record"><th colspan="5">' . esc_html( ( $i + 2 ) . ' · ' . ( $record['email'] ?? $record['name'] ?? '' ) ) . '</th></tr>';
			foreach ( $record as $field => $value ) {
				$conflict = in_array( $field, $review['conflicts'][$i] ?? array(), true );
				echo '<tr><td>' . absint( $i + 2 ) . '</td><td>' . esc_html( $labels[$field] ?? $field ) . '</td><td>' . esc_html( self::old_value( $existing, $field ) ) . '</td><td>';
				if ( ! empty( $review['split'][$i] ) && in_array( $field, array( 'first_name', 'last_name' ), true ) ) echo '<input name="names[' . absint( $i ) . '][' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '">';
				else echo nl2br( esc_html( $value ) );
				echo '</td><td>' . ( $conflict ? '<input type="checkbox" name="replace[' . absint( $i ) . '][' . esc_attr( $field ) . ']" value="1" aria-label="' . esc_attr__( 'Replace existing value', 'contactpanda' ) . '">' : '—' ) . '</td></tr>';
			}
		}
		echo '</tbody></table><p><label><input type="checkbox" required> ' . esc_html__( 'I checked the proposed changes.', 'contactpanda' ) . '</label></p><button type="submit">✓ ' . esc_html__( 'Confirm import', 'contactpanda' ) . '</button> <a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-data-transfer&csv_token=' . $token ) ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a></form></main></body></html>'; exit;
	}

	private static function fingerprint( $record, $fields ) {
		// Hash only: no customer data is placed in logs, URLs or browser storage.
		$values = array( 'exists' => (bool) $record ); foreach ( $fields as $field ) $values[$field] = self::old_value( $record, $field );
		return hash( 'sha256', wp_json_encode( $values ) );
	}
}
