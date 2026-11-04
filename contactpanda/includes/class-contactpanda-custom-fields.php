<?php

defined( 'ABSPATH' ) || exit;

/** User-defined person and company fields stored in the existing meta tables. */
final class ContactPanda_Custom_Fields {
	const OPTION = 'contactpanda_custom_fields';
	const PREFIX = 'contactpanda_custom_';

	public static function boot() {
		add_filter( 'contactpanda_misc_settings_sections', array( __CLASS__, 'settings_section' ) );
		add_action( 'contactpanda_render_misc_settings_section_custom_fields', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_save_custom_field', array( __CLASS__, 'handle_save_definition' ) );
		add_action( 'admin_post_contactpanda_delete_custom_field', array( __CLASS__, 'handle_delete_definition' ) );
		add_action( 'admin_post_contactpanda_save_custom_values', array( __CLASS__, 'handle_save_values' ) );
		add_action( 'admin_post_contactpanda_export_companies', array( __CLASS__, 'export_companies' ) );
		add_action( 'contactpanda_company_heading_actions', array( __CLASS__, 'company_export_button' ) );
		add_action( 'contactpanda_render_person_custom_fields', array( __CLASS__, 'render_person_fields' ), 10, 4 );
		add_action( 'contactpanda_render_company_custom_fields', array( __CLASS__, 'render_company_fields' ), 10, 4 );
		add_filter( 'contactpanda_contact_output_fields', array( __CLASS__, 'contact_output_fields' ) );
		add_filter( 'contactpanda_contact_output_row', array( __CLASS__, 'contact_output_row' ), 10, 2 );
	}

	public static function settings_section( $sections ) {
		if ( ContactPanda_Store::standalone() ) {
			$sections['custom_fields'] = __( 'Custom fields', 'contactpanda' );
		}
		return $sections;
	}

	public static function company_export_button() {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_export_companies' ), 'contactpanda_export_companies' );
		echo '<a class="button contactpanda-heading-action" href="' . esc_url( $url ) . '">' . esc_html__( 'Export CSV', 'contactpanda' ) . '</a>';
	}

	public static function definitions( $entity = '' ) {
		if ( ! ContactPanda_Store::standalone() ) {
			return array();
		}
		return self::integration_definitions( $entity );
	}

	/**
	 * Return stored definitions to trusted integrations in either data-source mode.
	 *
	 * The regular Basic settings UI deliberately hides ContactPanda-owned custom
	 * fields while Groundhogg is the leading source. Migrated ContactPanda forms
	 * still need their stable mappings so that editing or submitting a form does
	 * not discard source fields.
	 *
	 * @param string $entity Optional entity filter.
	 * @return array
	 */
	public static function integration_definitions( $entity = '' ) {
		$definitions = array_values( array_filter( (array) get_option( self::OPTION, array() ), array( __CLASS__, 'valid_definition' ) ) );
		usort( $definitions, static function ( $left, $right ) {
			$group = strcasecmp( $left['group'] ?? '', $right['group'] ?? '' );
			if ( 0 !== $group ) return $group;
			$sort = (int) $left['sort'] <=> (int) $right['sort'];
			return 0 !== $sort ? $sort : strcasecmp( $left['label'], $right['label'] );
		} );
		if ( $entity ) {
			$entity = self::normalize_entity( $entity );
			$definitions = array_values( array_filter( $definitions, static function ( $definition ) use ( $entity ) { return $entity === $definition['entity']; } ) );
		}
		return $definitions;
	}

	/**
	 * Ensure that a stable custom-field definition exists for an imported field.
	 *
	 * Importers use this instead of silently dropping source fields that have no
	 * ContactPanda core equivalent. Existing definitions are never overwritten.
	 *
	 * @param string $key     Stable source key.
	 * @param string $label   Human-readable label.
	 * @param string $type    ContactPanda field type.
	 * @param string $entity  contacts or companies.
	 * @param array  $options Selection options, when applicable.
	 * @return array|false
	 */
	public static function ensure_definition( $key, $label = '', $type = 'text', $entity = 'contacts', $options = array() ) {
		$key = sanitize_key( $key );
		$entity = self::normalize_entity( $entity );
		if ( ! $key ) return false;
		$definitions = self::integration_definitions();
		foreach ( $definitions as $definition ) {
			if ( $key === $definition['key'] && $entity === $definition['entity'] ) return $definition;
		}
		$type = sanitize_key( $type );
		if ( ! isset( self::type_labels()[ $type ] ) ) $type = 'text';
		$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $options ) ) ) );
		if ( 'select' === $type && ! $options ) $type = 'text';
		$definition = array(
			'id'       => 'imported-' . substr( md5( $entity . ':' . $key ), 0, 20 ),
			'key'      => $key,
			'label'    => sanitize_text_field( $label ) ?: $key,
			'group'    => 'Imported fields',
			'entity'   => $entity,
			'type'     => $type,
			'required' => 0,
			'sort'     => 10 + count( $definitions ),
			'options'  => $options,
		);
		$definitions[] = $definition;
		update_option( self::OPTION, $definitions, false );
		return $definition;
	}

	public static function meta_key( $key ) {
		return self::PREFIX . sanitize_key( $key );
	}

	public static function csv_fields( $entity ) {
		$fields = array();
		foreach ( self::definitions( self::normalize_entity( $entity ) ) as $definition ) {
			$fields[ 'custom__' . $definition['key'] ] = $definition['label'];
		}
		return $fields;
	}

	public static function custom_meta_from_record( $record, $entity, $existing = false, $mode = 'update' ) {
		$meta = array();
		foreach ( self::definitions( self::normalize_entity( $entity ) ) as $definition ) {
			$record_key = 'custom__' . $definition['key'];
			if ( ! array_key_exists( $record_key, $record ) || '' === trim( (string) $record[ $record_key ] ) ) continue;
			if ( $existing && 'fill' === $mode && '' !== trim( (string) self::record_value( $existing, $definition ) ) ) continue;
			$value = self::sanitize_value( $definition, $record[ $record_key ] );
			if ( ! is_wp_error( $value ) ) $meta[ self::meta_key( $definition['key'] ) ] = $value;
		}
		return $meta;
	}

	public static function searchable_text( $record, $entity = 'contacts' ) {
		$values = array();
		foreach ( self::definitions( self::normalize_entity( $entity ) ) as $definition ) $values[] = self::record_value( $record, $definition );
		return implode( ' ', array_filter( array_map( 'strval', $values ) ) );
	}

	private static function valid_definition( $definition ) {
		return is_array( $definition ) && ! empty( $definition['id'] ) && ! empty( $definition['key'] ) && ! empty( $definition['label'] ) && in_array( $definition['entity'] ?? '', array( 'contacts', 'companies' ), true );
	}

	private static function normalize_entity( $entity ) {
		return in_array( $entity, array( 'company', 'companies' ), true ) ? 'companies' : 'contacts';
	}

	private static function settings_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=custom_fields' ) );
	}

	private static function notice( $type, $message ) {
		set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
	}

	public static function render_settings() {
		if ( ! ContactPanda_Store::standalone() ) {
			return;
		}
		$definitions = self::definitions();
		$edit_id = isset( $_GET['edit_custom_field'] ) ? sanitize_key( wp_unslash( $_GET['edit_custom_field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$editing = false;
		foreach ( $definitions as $definition ) if ( $edit_id === $definition['id'] ) $editing = $definition;
		$delete_id = isset( $_GET['delete_custom_field'] ) ? sanitize_key( wp_unslash( $_GET['delete_custom_field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- confirmation routing only.
		if ( $delete_id ) {
			foreach ( $definitions as $definition ) {
				if ( $delete_id !== $definition['id'] ) continue;
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Delete custom field?', 'contactpanda' ) . '</h2><p>' . esc_html( sprintf( __( 'The field “%s” will no longer be displayed. Existing values are retained and can be restored by creating the same internal key again.', 'contactpanda' ), $definition['label'] ) ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_delete_custom_field"><input type="hidden" name="field_id" value="' . esc_attr( $definition['id'] ) . '">';
				wp_nonce_field( 'contactpanda_delete_custom_field_' . $definition['id'] );
				echo '<p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'I understand that this field definition will be removed.', 'contactpanda' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Delete field definition', 'contactpanda' ) . '</button> <a class="button" href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a></p></form></section>';
				return;
			}
		}

		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Custom fields', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Create additional fields for people or companies in the ContactPanda database. Existing values remain available if a field definition is later removed.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-custom-field-form"><input type="hidden" name="action" value="contactpanda_save_custom_field"><input type="hidden" name="field_id" value="' . esc_attr( $editing['id'] ?? '' ) . '">';
		wp_nonce_field( 'contactpanda_save_custom_field' );
		echo '<div class="contactpanda-custom-field-primary"><label><span>' . esc_html__( 'Field name', 'contactpanda' ) . '</span><input name="label" required value="' . esc_attr( $editing['label'] ?? '' ) . '"></label><label><span>' . esc_html__( 'Group', 'contactpanda' ) . '</span><input name="field_group" value="' . esc_attr( $editing['group'] ?? '' ) . '" placeholder="' . esc_attr__( 'General', 'contactpanda' ) . '"><small>' . esc_html__( 'Fields with the same group are displayed together.', 'contactpanda' ) . '</small></label><label><span>' . esc_html__( 'Internal key', 'contactpanda' ) . '</span><input name="field_key" required pattern="[a-z0-9_]+" value="' . esc_attr( $editing['key'] ?? '' ) . '"' . ( $editing ? ' readonly' : '' ) . '><small>' . esc_html__( 'Lowercase letters, numbers and underscores. The key cannot be changed later.', 'contactpanda' ) . '</small></label><label class="contactpanda-custom-field-required"><input type="checkbox" name="required" value="1"' . checked( ! empty( $editing['required'] ), true, false ) . '> <span>' . esc_html__( 'Use as a required field', 'contactpanda' ) . '</span></label></div>';
		echo '<div class="contactpanda-custom-field-properties">';
		echo '<label><span>' . esc_html__( 'Applies to', 'contactpanda' ) . '</span><select name="entity"><option value="contacts"' . selected( $editing['entity'] ?? 'contacts', 'contacts', false ) . '>' . esc_html__( 'People', 'contactpanda' ) . '</option><option value="companies"' . selected( $editing['entity'] ?? '', 'companies', false ) . '>' . esc_html__( 'Companies', 'contactpanda' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Field type', 'contactpanda' ) . '</span><select name="field_type">';
		foreach ( self::type_labels() as $type => $label ) echo '<option value="' . esc_attr( $type ) . '"' . selected( $editing['type'] ?? 'text', $type, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label><span>' . esc_html__( 'Sort order', 'contactpanda' ) . '</span><input type="number" name="sort" value="' . esc_attr( $editing['sort'] ?? 10 ) . '"></label><label class="contactpanda-custom-field-options"><span>' . esc_html__( 'Dropdown options', 'contactpanda' ) . '</span><textarea name="options" rows="3" placeholder="' . esc_attr__( 'One option per line', 'contactpanda' ) . '">' . esc_textarea( implode( "\n", (array) ( $editing['options'] ?? array() ) ) ) . '</textarea><small>' . esc_html__( 'Used only for Dropdown fields.', 'contactpanda' ) . '</small></label></div>';
		echo '<p><button class="button button-primary">' . ( $editing ? esc_html__( 'Save changes', 'contactpanda' ) : esc_html__( 'Add custom field', 'contactpanda' ) ) . '</button>' . ( $editing ? ' <a class="button" href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a>' : '' ) . '</p></form>';
		echo '<table class="widefat striped contactpanda-table"><thead><tr><th>' . esc_html__( 'Field', 'contactpanda' ) . '</th><th>' . esc_html__( 'Group', 'contactpanda' ) . '</th><th>' . esc_html__( 'Internal key', 'contactpanda' ) . '</th><th>' . esc_html__( 'Applies to', 'contactpanda' ) . '</th><th>' . esc_html__( 'Type', 'contactpanda' ) . '</th><th>' . esc_html__( 'Order', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $definitions as $definition ) {
			$edit_url = self::settings_url( array( 'edit_custom_field' => $definition['id'] ) );
			$delete_url = self::settings_url( array( 'delete_custom_field' => $definition['id'] ) );
			echo '<tr><td><strong>' . esc_html( $definition['label'] ) . '</strong>' . ( $definition['required'] ? ' <span class="contactpanda-mini-badge">' . esc_html__( 'Required', 'contactpanda' ) . '</span>' : '' ) . '</td><td>' . esc_html( $definition['group'] ?? __( 'General', 'contactpanda' ) ) . '</td><td><code>' . esc_html( $definition['key'] ) . '</code></td><td>' . esc_html( 'contacts' === $definition['entity'] ? __( 'People', 'contactpanda' ) : __( 'Companies', 'contactpanda' ) ) . '</td><td>' . esc_html( self::type_labels()[ $definition['type'] ] ?? $definition['type'] ) . '</td><td>' . esc_html( $definition['sort'] ) . '</td><td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'contactpanda' ) . '</a> · <a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'contactpanda' ) . '</a></td></tr>';
		}
		if ( ! $definitions ) echo '<tr><td colspan="7">' . esc_html__( 'No custom fields have been created yet.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table></section>';
	}

	public static function handle_save_definition() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		if ( ! ContactPanda_Store::standalone() ) wp_die( esc_html__( 'Custom fields are managed by Groundhogg while Groundhogg is the leading data source.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_save_custom_field' );
		$id = sanitize_key( wp_unslash( $_POST['field_id'] ?? '' ) );
		$key = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
		$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
		$group = sanitize_text_field( wp_unslash( $_POST['field_group'] ?? '' ) );
		$type = sanitize_key( wp_unslash( $_POST['field_type'] ?? 'text' ) );
		$entity = self::normalize_entity( sanitize_key( wp_unslash( $_POST['entity'] ?? 'contacts' ) ) );
		if ( ! $key || ! $label || ! isset( self::type_labels()[ $type ] ) ) {
			self::notice( 'error', __( 'Enter a valid field name, internal key, and field type.', 'contactpanda' ) );
			wp_safe_redirect( self::settings_url() ); exit;
		}
		$definitions = self::definitions();
		foreach ( $definitions as $definition ) {
			if ( $definition['key'] === $key && $definition['entity'] === $entity && $definition['id'] !== $id ) {
				self::notice( 'error', __( 'This internal key is already used for that data type.', 'contactpanda' ) );
				wp_safe_redirect( self::settings_url() ); exit;
			}
		}
		$options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', preg_split( '/\R/', (string) wp_unslash( $_POST['options'] ?? '' ) ) ) ) ) );
		if ( 'select' === $type && ! $options ) {
			self::notice( 'error', __( 'Add at least one option for a selection field.', 'contactpanda' ) );
			wp_safe_redirect( self::settings_url( $id ? array( 'edit_custom_field' => $id ) : array() ) ); exit;
		}
		$new = array( 'id' => $id ?: wp_generate_uuid4(), 'key' => $key, 'label' => $label, 'group' => $group ?: 'General', 'entity' => $entity, 'type' => $type, 'required' => empty( $_POST['required'] ) ? 0 : 1, 'sort' => intval( $_POST['sort'] ?? 10 ), 'options' => $options );
		$replaced = false;
		foreach ( $definitions as $index => $definition ) if ( $new['id'] === $definition['id'] ) { $new['key'] = $definition['key']; $definitions[ $index ] = $new; $replaced = true; }
		if ( ! $replaced ) $definitions[] = $new;
		update_option( self::OPTION, array_values( $definitions ), false );
		self::notice( 'success', $replaced ? __( 'The custom field was updated.', 'contactpanda' ) : __( 'The custom field was created.', 'contactpanda' ) );
		wp_safe_redirect( self::settings_url() ); exit;
	}

	public static function handle_delete_definition() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		if ( ! ContactPanda_Store::standalone() ) wp_die( esc_html__( 'Custom fields are managed by Groundhogg while Groundhogg is the leading data source.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$id = sanitize_key( wp_unslash( $_POST['field_id'] ?? '' ) );
		check_admin_referer( 'contactpanda_delete_custom_field_' . $id );
		if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) wp_die( esc_html__( 'Confirm the deletion first.', 'contactpanda' ), '', array( 'response' => 400 ) );
		$definitions = array_values( array_filter( self::definitions(), static function ( $definition ) use ( $id ) { return $id !== $definition['id']; } ) );
		update_option( self::OPTION, $definitions, false );
		self::notice( 'success', __( 'The field definition was removed. Existing values were retained.', 'contactpanda' ) );
		wp_safe_redirect( self::settings_url() ); exit;
	}

	public static function render_person_fields( $id, $record, $readonly = false ) {
		self::render_value_card( 'contacts', $id, $record, $readonly );
	}

	public static function render_company_fields( $id, $record, $readonly = false ) {
		self::render_value_card( 'companies', $id, $record, $readonly );
	}

	private static function render_value_card( $entity, $id, $record, $readonly ) {
		if ( ! ContactPanda_Store::standalone() ) return;
		$definitions = self::definitions( $entity );
		if ( ! $definitions ) return;
		echo '<section class="contactpanda-card contactpanda-custom-values"><h2>' . esc_html__( 'Custom fields', 'contactpanda' ) . '</h2>';
		if ( isset( $_GET['custom_fields_saved'] ) ) echo '<div class="notice notice-success inline"><p>' . esc_html__( 'The custom fields were saved.', 'contactpanda' ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- success flag only.
		if ( $readonly ) {
			$current_group = null;
			foreach ( $definitions as $definition ) {
				$group = $definition['group'] ?? __( 'General', 'contactpanda' );
				if ( $group !== $current_group ) {
					if ( null !== $current_group ) echo '</div>';
					echo '<div class="contactpanda-custom-field-group"><h3>' . esc_html( $group ) . '</h3>';
					$current_group = $group;
				}
				echo '<div class="contactpanda-field"><span>' . esc_html( $definition['label'] ) . '</span><strong>' . esc_html( self::display_value( $definition, self::record_value( $record, $definition ) ) ) . '</strong></div>';
			}
			if ( null !== $current_group ) echo '</div>';
			echo '</section>'; return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_custom_values"><input type="hidden" name="entity" value="' . esc_attr( $entity ) . '"><input type="hidden" name="record_id" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'contactpanda_save_custom_values_' . $entity . '_' . $id );
		$current_group = null;
		foreach ( $definitions as $definition ) {
			$group = $definition['group'] ?? __( 'General', 'contactpanda' );
			if ( $group !== $current_group ) {
				if ( null !== $current_group ) echo '</fieldset>';
				echo '<fieldset class="contactpanda-custom-field-group"><legend>' . esc_html( $group ) . '</legend>';
				$current_group = $group;
			}
			self::render_input( $definition, self::record_value( $record, $definition ) );
		}
		if ( null !== $current_group ) echo '</fieldset>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Save custom fields', 'contactpanda' ) . '</button></p></form></section>';
	}

	private static function render_input( $definition, $value ) {
		$name = 'custom_values[' . $definition['key'] . ']';
		$required = $definition['required'] ? ' required' : '';
		echo '<div class="contactpanda-custom-value"><label for="contactpanda-custom-' . esc_attr( $definition['key'] ) . '">' . esc_html( $definition['label'] ) . ( $definition['required'] ? ' *' : '' ) . '</label>';
		$input_id = 'contactpanda-custom-' . $definition['key'];
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
		if ( 'textarea' === $definition['type'] ) echo '<textarea id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $name ) . '" rows="4"' . $required . '>' . esc_textarea( $value ) . '</textarea>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
		elseif ( 'select' === $definition['type'] ) { echo '<select id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $name ) . '"' . $required . '><option value="">' . esc_html__( '— Please select —', 'contactpanda' ) . '</option>'; foreach ( $definition['options'] as $option ) echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $option ) . '</option>'; echo '</select>'; }
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
		elseif ( 'checkbox' === $definition['type'] ) echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><label class="contactpanda-custom-checkbox"><input id="' . esc_attr( $input_id ) . '" type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . $required . '> ' . esc_html__( 'Yes', 'contactpanda' ) . '</label>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
		else { $type = in_array( $definition['type'], array( 'number', 'date', 'url' ), true ) ? $definition['type'] : 'text'; echo '<input id="' . esc_attr( $input_id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $required . '>'; }
		echo '</div>';
	}

	public static function handle_save_values() {
		if ( ! current_user_can( 'edit_users' ) && ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		if ( ! ContactPanda_Store::standalone() ) wp_die( esc_html__( 'Custom fields are managed by Groundhogg while Groundhogg is the leading data source.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$entity = self::normalize_entity( sanitize_key( wp_unslash( $_POST['entity'] ?? 'contacts' ) ) );
		$id = absint( $_POST['record_id'] ?? 0 );
		check_admin_referer( 'contactpanda_save_custom_values_' . $entity . '_' . $id );
		$posted = isset( $_POST['custom_values'] ) && is_array( $_POST['custom_values'] ) ? wp_unslash( $_POST['custom_values'] ) : array();
		$meta = array();
		foreach ( self::definitions( $entity ) as $definition ) {
			$value = self::sanitize_value( $definition, $posted[ $definition['key'] ] ?? '' );
			if ( is_wp_error( $value ) ) wp_die( esc_html( $value->get_error_message() ), '', array( 'response' => 400 ) );
			$meta[ self::meta_key( $definition['key'] ) ] = $value;
		}
		$result = ContactPanda_Plugin::extension_update_custom_meta( $entity, $id, $meta );
		if ( ! $result ) wp_die( esc_html__( 'The custom fields could not be saved.', 'contactpanda' ), '', array( 'response' => 500 ) );
		$page = 'companies' === $entity ? 'contactpanda-firmen' : 'contactpanda-people';
		$key = 'companies' === $entity ? 'firma' : 'person';
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&' . $key . '=' . $id . '&custom_fields_saved=1' ) ); exit;
	}

	public static function export_companies() {
		if ( ! current_user_can( 'edit_users' ) && ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to export companies.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_export_companies' );
		$definitions = self::definitions( 'companies' );
		$headers = array( __( 'Company name', 'contactpanda' ), __( 'Legal name', 'contactpanda' ), __( 'Email', 'contactpanda' ), __( 'Phone', 'contactpanda' ), __( 'Website', 'contactpanda' ), __( 'Industry', 'contactpanda' ), __( 'VAT ID', 'contactpanda' ), __( 'Street address', 'contactpanda' ), __( 'Address line 2', 'contactpanda' ), __( 'Postal code', 'contactpanda' ), __( 'City', 'contactpanda' ), __( 'Country', 'contactpanda' ) );
		foreach ( $definitions as $definition ) $headers[] = $definition['label'];
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="contactpanda-companies-' . current_time( 'Y-m-d' ) . '.csv"' );
		echo "\xEF\xBB\xBF";
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, $headers, ';' );
		foreach ( ContactPanda_Plugin::extension_companies() as $company ) {
			$value = static function ( $keys ) use ( $company ) {
				foreach ( (array) $keys as $key ) {
					if ( is_array( $company ) && isset( $company[ $key ] ) ) return $company[ $key ];
					if ( is_object( $company ) && method_exists( $company, $key ) ) return $company->{$key}();
					$getter = 'get_' . $key; if ( is_object( $company ) && method_exists( $company, $getter ) ) return $company->{$getter}();
				}
				return '';
			};
			$row = array( $value( array( 'name', 'company_name' ) ), $value( 'legal_name' ), $value( 'email' ), $value( array( 'phone_number', 'phone' ) ), $value( array( 'website', 'domain' ) ), $value( 'industry' ), $value( 'vat_id' ), $value( array( 'street', 'address_1' ) ), $value( 'address_2' ), $value( 'postcode' ), $value( 'city' ), $value( 'country' ) );
			foreach ( $definitions as $definition ) $row[] = self::display_value( $definition, self::record_value( $company, $definition ) );
			fputcsv( $output, array_map( array( __CLASS__, 'csv_safe_value' ), $row ), ';' );
		}
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the streamed CSV response created above.
		exit;
	}

	public static function csv_safe_value( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return preg_match( '/^[=+\-@\t\r]/u', $value ) ? "'" . $value : $value;
	}

	private static function sanitize_value( $definition, $value ) {
		if ( is_array( $value ) ) $value = '';
		if ( 'textarea' === $definition['type'] ) $value = sanitize_textarea_field( $value );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		elseif ( 'url' === $definition['type'] ) { $value = trim( (string) $value ); if ( $value ) { $value = esc_url_raw( $value ); if ( ! $value ) return new WP_Error( 'contactpanda_custom_url', sprintf( __( 'Enter a valid URL for “%s”.', 'contactpanda' ), $definition['label'] ) ); } }
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		elseif ( 'number' === $definition['type'] ) { $value = trim( (string) $value ); if ( '' !== $value && ! is_numeric( $value ) ) return new WP_Error( 'contactpanda_custom_number', sprintf( __( 'Enter a valid number for “%s”.', 'contactpanda' ), $definition['label'] ) ); }
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		elseif ( 'date' === $definition['type'] ) { $value = sanitize_text_field( $value ); $date = $value ? DateTime::createFromFormat( 'Y-m-d', $value ) : false; if ( $value && ( ! $date || $date->format( 'Y-m-d' ) !== $value ) ) return new WP_Error( 'contactpanda_custom_date', sprintf( __( 'Enter a valid date for “%s”.', 'contactpanda' ), $definition['label'] ) ); }
		elseif ( 'checkbox' === $definition['type'] ) $value = in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'ja', 'on' ), true ) ? 1 : 0;
		else $value = sanitize_text_field( $value );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		if ( 'select' === $definition['type'] && '' !== $value && ! in_array( $value, $definition['options'], true ) ) return new WP_Error( 'contactpanda_custom_select', sprintf( __( 'Select a valid option for “%s”.', 'contactpanda' ), $definition['label'] ) );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		if ( $definition['required'] && ( '' === (string) $value || ( 'checkbox' === $definition['type'] && ! $value ) ) ) return new WP_Error( 'contactpanda_custom_required', sprintf( __( 'The field “%s” is required.', 'contactpanda' ), $definition['label'] ) );
		return $value;
	}

	private static function record_value( $record, $definition ) {
		$key = self::meta_key( $definition['key'] );
		if ( is_array( $record ) ) return $record['meta'][ $key ] ?? $record[ $key ] ?? '';
		if ( is_object( $record ) && method_exists( $record, 'get_meta' ) ) return $record->get_meta( $key );
		return '';
	}

	private static function display_value( $definition, $value ) {
		if ( 'checkbox' === $definition['type'] ) return $value ? __( 'Yes', 'contactpanda' ) : __( 'No', 'contactpanda' );
		return '' !== trim( (string) $value ) ? (string) $value : '—';
	}

	private static function type_labels() {
		return array( 'text' => __( 'Text', 'contactpanda' ), 'textarea' => __( 'Multi-line text', 'contactpanda' ), 'number' => __( 'Number', 'contactpanda' ), 'date' => __( 'Date', 'contactpanda' ), 'select' => __( 'Dropdown', 'contactpanda' ), 'checkbox' => __( 'Checkbox', 'contactpanda' ), 'url' => __( 'URL', 'contactpanda' ) );
	}

	public static function contact_output_fields( $fields ) {
		foreach ( self::definitions( 'contacts' ) as $definition ) $fields[ 'custom__' . $definition['key'] ] = $definition['label'];
		return $fields;
	}

	public static function contact_output_row( $row, $record ) {
		foreach ( self::definitions( 'contacts' ) as $definition ) $row[ 'custom__' . $definition['key'] ] = self::display_value( $definition, self::record_value( $record, $definition ) );
		return $row;
	}
}
