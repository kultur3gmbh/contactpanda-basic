<?php

defined( 'ABSPATH' ) || exit;

/** Central role and capability policy shared by the ContactPanda family. */
final class ContactPanda_Capabilities {
	const OPTION = 'contactpanda_role_capabilities';
	const AUDIT_OPTION = 'contactpanda_role_capability_audit';

	public static function boot() {
		add_filter( 'contactpanda_settings_tabs', array( __CLASS__, 'settings_tab' ) );
		add_action( 'contactpanda_render_settings_tab_roles', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_save_role_capabilities', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'ensure_default_capabilities' ) );
	}

	public static function definitions() {
		return (array) apply_filters(
			'contactpanda_capability_definitions',
			array(
				'contactpanda_view_contacts'     => __( 'View people and companies', 'contactpanda' ),
				'contactpanda_manage_contacts'   => __( 'Edit people and companies', 'contactpanda' ),
				'contactpanda_manage_marketing'  => __( 'Manage newsletters, forms and automations', 'contactpanda' ),
				'contactpanda_view_reports'      => __( 'View analytics', 'contactpanda' ),
				'contactpanda_manage_support'    => __( 'Manage support requests', 'contactpanda' ),
				'contactpanda_manage_projects'   => __( 'Manage projects and tasks', 'contactpanda' ),
				'contactpanda_manage_settings'   => __( 'Manage ContactPanda settings and migrations', 'contactpanda' ),
			)
		);
	}

	public static function for_area( $area ) {
		$map = array(
			'view'       => 'contactpanda_view_contacts',
			'contacts'   => 'contactpanda_manage_contacts',
			'marketing'  => 'contactpanda_manage_marketing',
			'reports'    => 'contactpanda_view_reports',
			'support'    => 'contactpanda_manage_support',
			'projects'   => 'contactpanda_manage_projects',
			'settings'   => 'contactpanda_manage_settings',
		);
		$capability = $map[ sanitize_key( $area ) ] ?? 'contactpanda_view_contacts';
		return (string) apply_filters( 'contactpanda_capability_for_area', $capability, $area );
	}

	public static function settings_tab( $tabs ) {
		if ( self::management_available() ) {
			$tabs['roles'] = __( 'Users & roles', 'contactpanda' );
		}
		return $tabs;
	}

	public static function management_available() {
		return defined( 'CONTACTPANDA_PLUS_VERSION' ) && class_exists( 'ContactPanda_License' ) && ContactPanda_License::entitled( 'plus' );
	}

	public static function ensure_default_capabilities() {
		$installed = (array) get_option( self::OPTION, array() );
		if ( ! $installed ) {
			$installed = array(
				'administrator' => array_keys( self::definitions() ),
				'shop_manager'  => array( 'contactpanda_view_contacts', 'contactpanda_manage_contacts', 'contactpanda_manage_marketing', 'contactpanda_view_reports', 'contactpanda_manage_support' ),
			);
			update_option( self::OPTION, $installed, false );
		}
		self::apply_matrix( $installed );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! self::management_available() ) return;
		global $wp_roles;
		$matrix = (array) get_option( self::OPTION, array() );
		$definitions = self::definitions();
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Users and roles', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Choose which WordPress roles may see or change each ContactPanda area. Administrators always retain every permission.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_role_capabilities">';
		wp_nonce_field( 'contactpanda_save_role_capabilities' );
		echo '<div class="contactpanda-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Role', 'contactpanda' ) . '</th>';
		foreach ( $definitions as $label ) echo '<th>' . esc_html( $label ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( (array) $wp_roles->roles as $role_key => $role_data ) {
			echo '<tr><th scope="row">' . esc_html( translate_user_role( $role_data['name'] ) ) . '</th>';
			foreach ( $definitions as $capability => $label ) {
				$checked = 'administrator' === $role_key || in_array( $capability, (array) ( $matrix[ $role_key ] ?? array() ), true );
				echo '<td><label><input type="checkbox" name="roles[' . esc_attr( $role_key ) . '][]" value="' . esc_attr( $capability ) . '"' . checked( $checked, true, false ) . ( 'administrator' === $role_key ? ' disabled' : '' ) . '><span class="screen-reader-text">' . esc_html( $label ) . '</span></label></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div><p><button class="button button-primary">' . esc_html__( 'Save role permissions', 'contactpanda' ) . '</button></p></form></section>';
	}

	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		if ( ! self::management_available() ) wp_die( esc_html__( 'ContactPanda Plus is required to manage user roles.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_save_role_capabilities' );
		global $wp_roles;
		$allowed = array_keys( self::definitions() );
		$posted = isset( $_POST['roles'] ) && is_array( $_POST['roles'] ) ? wp_unslash( $_POST['roles'] ) : array();
		$matrix = array( 'administrator' => $allowed );
		foreach ( array_keys( (array) $wp_roles->roles ) as $role_key ) {
			if ( 'administrator' === $role_key ) continue;
			$matrix[ $role_key ] = array_values( array_intersect( $allowed, array_map( 'sanitize_key', (array) ( $posted[ $role_key ] ?? array() ) ) ) );
			if ( $matrix[ $role_key ] && ! in_array( 'contactpanda_view_contacts', $matrix[ $role_key ], true ) ) $matrix[ $role_key ][] = 'contactpanda_view_contacts';
		}
		update_option( self::OPTION, $matrix, false );
		self::apply_matrix( $matrix );
		$audit = (array) get_option( self::AUDIT_OPTION, array() );
		array_unshift( $audit, array( 'user_id' => get_current_user_id(), 'changed_at' => current_time( 'mysql' ), 'matrix' => $matrix ) );
		update_option( self::AUDIT_OPTION, array_slice( $audit, 0, 50 ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=roles&updated=1' ) );
		exit;
	}

	private static function apply_matrix( $matrix ) {
		global $wp_roles;
		if ( ! $wp_roles ) return;
		$capabilities = array_keys( self::definitions() );
		$dependencies = (array) apply_filters( 'contactpanda_role_capability_dependencies', array() );
		foreach ( array_keys( (array) $wp_roles->roles ) as $role_key ) {
			$role = get_role( $role_key );
			if ( ! $role ) continue;
			$granted = 'administrator' === $role_key ? $capabilities : (array) ( $matrix[ $role_key ] ?? array() );
			foreach ( $capabilities as $capability ) {
				if ( in_array( $capability, $granted, true ) ) $role->add_cap( $capability );
				else $role->remove_cap( $capability );
				foreach ( (array) ( $dependencies[ $capability ] ?? array() ) as $dependent ) {
					$dependent = sanitize_key( $dependent );
					if ( ! $dependent ) continue;
					if ( in_array( $capability, $granted, true ) ) $role->add_cap( $dependent );
					else $role->remove_cap( $dependent );
				}
			}
		}
	}
}
