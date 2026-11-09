<?php

defined( 'ABSPATH' ) || exit;

/** Central role and capability policy shared by the ContactPanda family. */
final class ContactPanda_Capabilities {
	const OPTION = 'contactpanda_role_capabilities';
	const AUDIT_OPTION = 'contactpanda_role_capability_audit';

	public static function boot() {
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

	public static function apply_matrix( $matrix ) {
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
