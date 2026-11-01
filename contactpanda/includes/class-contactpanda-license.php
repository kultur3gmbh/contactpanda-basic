<?php

defined( 'ABSPATH' ) || exit;

/**
 * Provider-neutral ContactPanda license client.
 *
 * Licenses may grant one or more durable module entitlements. A normal expiry
 * ends updates and support but never disables an already granted module.
 * Explicitly blocked licenses are excluded from the effective entitlements.
 */
final class ContactPanda_License {
	const OPTION           = 'contactpanda_license_state';
	const INSTALLATION     = 'contactpanda_installation_id';
	const PRODUCT          = 'contactpanda-suite';
	const CHECK_HOOK       = 'contactpanda_license_daily_check';
	const RESPONSE_VERSION   = 1;
	const OFFLINE_GRACE_DAYS = 7;

	public static function boot() {
		add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ), 20 );
		add_action( 'contactpanda_render_general_settings_section_license', array( __CLASS__, 'render_settings' ) );
		add_action( 'admin_post_contactpanda_activate_license', array( __CLASS__, 'activate' ) );
		add_action( 'admin_post_contactpanda_disconnect_license', array( __CLASS__, 'disconnect' ) );
		add_action( 'admin_post_contactpanda_check_licenses', array( __CLASS__, 'manual_check' ) );
		add_action( self::CHECK_HOOK, array( __CLASS__, 'scheduled_check' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_offline_notice' ) );
		if ( ! wp_next_scheduled( self::CHECK_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CHECK_HOOK );
		}
	}

	public static function settings_section( $sections ) {
		$sections['license'] = __( 'Licenses', 'contactpanda' );
		return $sections;
	}

	private static function defaults() {
		return array(
			'version'         => 2,
			'licenses'        => array(),
			'legacy_grants'   => array(),
			'last_checked_at' => '',
			'last_successful_check_at' => '',
			'last_failed_check_at'     => '',
			'check_failure_since'      => '',
		);
	}

	/**
	 * Read version-two state and transparently retain grants made by 0.6.0-rc.
	 */
	public static function state() {
		$raw = (array) get_option( self::OPTION, array() );
		if ( isset( $raw['licenses'] ) ) {
			$state = wp_parse_args( $raw, self::defaults() );
		} else {
			$grants = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) ( $raw['granted_entitlements'] ?? array() ) ) ) ) );
			if ( ! empty( $raw['newsletter_granted'] ) && ! in_array( 'advanced_newsletter', $grants, true ) ) {
				$grants[] = 'advanced_newsletter';
			}
			if ( ! empty( $raw['master_granted'] ) && ! in_array( 'all', $grants, true ) ) {
				$grants[] = 'all';
			}
			$state = self::defaults();
			$state['legacy_grants'] = $grants;
			if ( ! empty( $raw['license_key'] ) ) {
				$id = 'legacy-' . substr( hash( 'sha256', (string) $raw['license_key'] ), 0, 16 );
				$state['licenses'][ $id ] = array(
					'activation_id'    => '',
					'activation_token' => '',
					'license_label'    => sanitize_text_field( $raw['license_label'] ?? self::masked_key( $raw['license_key'] ) ),
					'license_type'     => sanitize_key( $raw['license_type'] ?? 'customer' ),
					'entitlements'     => $grants,
					'limits'           => array(),
					'updates_until'    => self::date_value( $raw['updates_until'] ?? '' ),
					'support_until'    => self::date_value( $raw['support_until'] ?? '' ),
					'activated_at'     => sanitize_text_field( $raw['activated_at'] ?? '' ),
					'last_checked_at'  => sanitize_text_field( $raw['last_checked_at'] ?? '' ),
					'status'           => sanitize_key( $raw['last_status'] ?? 'disconnected' ),
				);
			}
		}

		$state['licenses'] = is_array( $state['licenses'] ) ? $state['licenses'] : array();
		foreach ( $state['licenses'] as $id => $license ) {
			if ( ! is_array( $license ) ) {
				unset( $state['licenses'][ $id ] );
				continue;
			}
			$state['licenses'][ $id ] = self::normalize_license( $license );
		}
		$state['legacy_grants'] = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $state['legacy_grants'] ) ) ) );
		foreach ( array( 'last_checked_at', 'last_successful_check_at', 'last_failed_check_at', 'check_failure_since' ) as $date_key ) {
			$state[ $date_key ] = sanitize_text_field( $state[ $date_key ] ?? '' );
		}
		if ( ! $state['last_successful_check_at'] && $state['last_checked_at'] ) {
			$state['last_successful_check_at'] = $state['last_checked_at'];
		}
		return $state;
	}

	private static function normalize_license( $license ) {
		$license = wp_parse_args(
			(array) $license,
			array(
				'activation_id'    => '',
				'activation_token' => '',
				'license_label'    => '',
				'license_type'     => 'customer',
				'entitlements'     => array(),
				'limits'           => array(),
				'updates_until'    => '',
				'support_until'    => '',
				'activated_at'     => '',
				'last_checked_at'  => '',
				'status'           => 'valid',
			)
		);
		$license['activation_id']    = sanitize_text_field( $license['activation_id'] );
		$license['activation_token'] = sanitize_text_field( $license['activation_token'] );
		$license['license_label']    = sanitize_text_field( $license['license_label'] );
		$license['license_type']     = sanitize_key( $license['license_type'] );
		$license['entitlements']     = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $license['entitlements'] ) ) ) );
		$license['limits']           = is_array( $license['limits'] ) ? self::sanitize_limits( $license['limits'] ) : array();
		$license['updates_until']    = self::date_value( $license['updates_until'] );
		$license['support_until']    = self::date_value( $license['support_until'] );
		$license['status']           = sanitize_key( $license['status'] );
		return $license;
	}

	/** Preserve provider-defined per-product limits without accepting objects or markup. */
	private static function sanitize_limits( $limits ) {
		$clean = array();
		foreach ( (array) $limits as $key => $value ) {
			$key = sanitize_key( $key );
			if ( '' === $key ) continue;
			$clean[ $key ] = is_array( $value ) ? self::sanitize_limits( $value ) : sanitize_text_field( (string) $value );
		}
		return $clean;
	}

	private static function normalize_entitlement( $item ) {
		$item = '*' === trim( (string) $item ) ? 'all' : sanitize_key( $item );
		$aliases = array(
			'newsletter'           => 'advanced_newsletter',
			'contactpanda-newsletter' => 'advanced_newsletter',
			'forms'                => 'forms_editor',
			'flows'                => 'automations',
			'integrations'         => 'advanced_integrations',
			'migration'            => 'form_migration',
			'contactpanda-plus'     => 'plus',
			'contactpanda-pro'      => 'pro',
			'contactpanda-business' => 'business',
			'contactpanda-suite'    => 'all',
			'contactpanda-woocommerce' => 'woocommerce',
			'contactpanda-license-server' => 'license-server',
		);
		return $aliases[ $item ] ?? $item;
	}

	/** Canonical paid features contained in the complete Plus package. */
	public static function plus_features() {
		return array( 'advanced_newsletter', 'forms_editor', 'automations', 'advanced_integrations', 'form_migration' );
	}

	public static function installation_id() {
		$id = (string) get_option( self::INSTALLATION, '' );
		if ( ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) ) {
			$id = wp_generate_uuid4();
			update_option( self::INSTALLATION, $id, false );
		}
		return $id;
	}

	/**
	 * Normal expiry is deliberately ignored here. Only an explicit block revokes.
	 */
	public static function entitled( $module ) {
		$module = sanitize_key( $module );
		$state  = self::state();
		$grants = $state['legacy_grants'];
		foreach ( $state['licenses'] as $license ) {
			if ( 'blocked' === $license['status'] ) {
				continue;
			}
			$grants = array_merge( $grants, $license['entitlements'] );
		}
		$module  = self::normalize_entitlement( $module );
		$grants  = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), $grants ) ) ) );
		$granted = in_array( 'all', $grants, true ) || in_array( $module, $grants, true );
		if ( ! $granted && in_array( $module, self::plus_features(), true ) ) {
			$granted = in_array( 'plus', $grants, true );
		}
		return (bool) apply_filters( 'contactpanda_license_entitled', $granted, $module, $state );
	}

	/** The reduced newsletter is part of free ContactPanda. */
	public static function newsletter_entitled() {
		return (bool) apply_filters( 'contactpanda_newsletter_entitled', true, self::state() );
	}

	public static function advanced_newsletter_entitled() {
		return self::entitled( 'advanced_newsletter' );
	}

	/** Products shown on the shared license page. Extensions append their entry. */
	public static function products() {
		$products = array(
			'contactpanda-basic' => array(
				'label'       => __( 'ContactPanda Basic', 'contactpanda' ),
				'entitlement' => '',
				'version'     => defined( 'CONTACTPANDA_VERSION' ) ? CONTACTPANDA_VERSION : '',
				'free'        => true,
				'modules'     => array(),
			),
		);
		$products = (array) apply_filters( 'contactpanda_license_products', $products );
		$clean = array();
		foreach ( $products as $id => $product ) {
			$id = sanitize_key( $id );
			if ( '' === $id || ! is_array( $product ) || empty( $product['label'] ) ) continue;
			$clean[ $id ] = array(
				'label'       => sanitize_text_field( $product['label'] ),
				'entitlement' => sanitize_key( $product['entitlement'] ?? $id ),
				'version'     => sanitize_text_field( $product['version'] ?? '' ),
				'free'        => ! empty( $product['free'] ),
				'modules'     => array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) ( $product['modules'] ?? array() ) ) ) ) ),
			);
		}
		return $clean;
	}

	private static function product_license_summary( $product, $state ) {
		$matches = array();
		foreach ( $state['licenses'] as $license ) {
			if ( empty( $product['free'] ) && self::update_entitlement_matches( $license['entitlements'], $product['entitlement'] ) ) {
				$matches[] = $license;
			}
		}
		$updates = '';
		$support = '';
		$site_used = '';
		$site_limit = '';
		$status = ! empty( $product['free'] ) ? 'free' : 'unlicensed';
		foreach ( $matches as $license ) {
			if ( 'blocked' === $license['status'] ) {
				$status = 'blocked';
				continue;
			}
			if ( 'disconnected' !== $license['status'] ) $status = 'expired' === $license['status'] ? 'expired' : 'valid';
			if ( $license['updates_until'] && ( ! $updates || $license['updates_until'] > $updates ) ) $updates = $license['updates_until'];
			if ( $license['support_until'] && ( ! $support || $license['support_until'] > $support ) ) $support = $license['support_until'];
			$limits = $license['limits'];
			$product_limits = isset( $limits['products'][ $product['entitlement'] ] ) && is_array( $limits['products'][ $product['entitlement'] ] ) ? $limits['products'][ $product['entitlement'] ] : $limits;
			foreach ( array( 'sites_used', 'site_usage', 'activations_used', 'active_sites' ) as $key ) if ( isset( $product_limits[ $key ] ) && '' !== (string) $product_limits[ $key ] ) { $site_used = (string) $product_limits[ $key ]; break; }
			foreach ( array( 'site_limit', 'max_sites', 'activations', 'activation_limit' ) as $key ) if ( isset( $product_limits[ $key ] ) && '' !== (string) $product_limits[ $key ] ) { $site_limit = (string) $product_limits[ $key ]; break; }
		}
		return compact( 'status', 'updates', 'support', 'site_used', 'site_limit' );
	}

	/**
	 * Check whether durable module grants authorize updates for a product family.
	 *
	 * Licenses can grant a complete package or only selected modules. Updating the
	 * package is required in both cases, otherwise individually licensed modules
	 * could never receive maintenance releases.
	 */
	public static function update_entitlement_matches( $entitlements, $required ) {
		$required     = self::normalize_entitlement( $required );
		$entitlements = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), (array) $entitlements ) ) ) );
		if ( '' === $required || in_array( 'all', $entitlements, true ) ) {
			return true;
		}

		$families = array(
			'plus'     => array_merge( array( 'plus', 'customer_registration', 'customer_account', 'messaging', 'email_delivery', 'sms', 'whatsapp' ), self::plus_features() ),
			'pro'      => array( 'pro', 'support_desk', 'helpdesk' ),
			'business' => array( 'business', 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management', 'software_licensing' ),
		);
		$candidates = $families[ $required ] ?? array( $required );
		return (bool) array_intersect( $candidates, $entitlements );
	}

	public static function updates_allowed_for( $module ) {
		$module = sanitize_key( $module );
		$state  = self::state();
		foreach ( $state['licenses'] as $license ) {
			if ( in_array( $license['status'], array( 'blocked', 'disconnected' ), true ) || ! self::update_entitlement_matches( $license['entitlements'], $module ) ) {
				continue;
			}
			if ( 'expired' !== $license['status'] && ( empty( $license['updates_until'] ) || strtotime( $license['updates_until'] . ' 23:59:59 UTC' ) >= time() ) ) {
				return true;
			}
		}
		return false;
	}

	private static function capability() {
		return 'manage_options';
	}

	private static function endpoint( $action = 'activate' ) {
		$base = defined( 'CONTACTPANDA_LICENSE_ENDPOINT' ) ? CONTACTPANDA_LICENSE_ENDPOINT : 'https://mycontactpanda.com/wp-json/contactpanda-license/v1/activate';
		$base = preg_replace( '#/(?:activate|check|deactivate|updates/check)/?$#', '', (string) $base );
		$base = trailingslashit( $base ) . implode( '/', array_map( 'sanitize_key', explode( '/', trim( (string) $action, '/' ) ) ) );
		return esc_url_raw( apply_filters( 'contactpanda_license_endpoint', $base, $action ) );
	}

	/** Expose only the service URL for read-only diagnostics; never license secrets. */
	public static function diagnostic_endpoint() {
		return self::endpoint( 'updates/check' );
	}

	/**
	 * Request signed update metadata for a licensed product.
	 *
	 * The activation token is transmitted only in the HTTPS request body. It is
	 * never placed in a package URL or persisted by the updater cache.
	 */
	public static function product_update( $product, $entitlement, $installed_version, $channel = 'stable' ) {
		$product          = sanitize_key( $product );
		$entitlement      = sanitize_key( $entitlement );
		$channel          = 'beta' === sanitize_key( $channel ) ? 'beta' : 'stable';
		$installed_version = sanitize_text_field( $installed_version );
		$state            = self::state();
		$last_error       = new WP_Error( 'contactpanda_update_not_licensed' );

		foreach ( $state['licenses'] as $license ) {
			if ( empty( $license['activation_id'] ) || empty( $license['activation_token'] ) || in_array( $license['status'], array( 'blocked', 'disconnected', 'expired' ), true ) ) {
				continue;
			}
			if ( ! self::update_entitlement_matches( $license['entitlements'], $entitlement ) ) {
				continue;
			}
			if ( $license['updates_until'] && strtotime( $license['updates_until'] . ' 23:59:59 UTC' ) < time() ) {
				continue;
			}
			$data = self::request(
				'updates/check',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
					'update_product'   => $product,
					'installed_version'=> $installed_version,
					'channel'          => $channel,
				)
			);
			if ( is_wp_error( $data ) ) {
				$last_error = $data;
				continue;
			}
			return $data;
		}
		return $last_error;
	}

	private static function status_message( $code ) {
		$messages = array(
			'activated'    => __( 'The ContactPanda license has been activated. Granted modules remain available after the update and support period ends.', 'contactpanda' ),
			'disconnected' => __( 'The website was disconnected from the license server. Previously granted modules remain available.', 'contactpanda' ),
			'checked'      => __( 'The license information was updated.', 'contactpanda' ),
			'invalid'      => __( 'The license could not be activated. Please check the license key and try again.', 'contactpanda' ),
			'network'      => __( 'The license service could not be reached. No existing feature or license information was changed.', 'contactpanda' ),
			'signature'    => __( 'The license response could not be verified. No existing license information was changed.', 'contactpanda' ),
		);
		return $messages[ $code ] ?? '';
	}

	public static function render_settings() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$state  = self::state();
		$offline = self::offline_status( $state );
		$notice = sanitize_key( wp_unslash( $_GET['license_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result code.
		if ( $notice && self::status_message( $notice ) ) {
			$class = in_array( $notice, array( 'activated', 'disconnected', 'checked' ), true ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( self::status_message( $notice ) ) . '</p></div>';
		}

		$catalog = apply_filters( 'contactpanda_license_modules', array( 'advanced_newsletter' => __( 'Advanced newsletter', 'contactpanda' ) ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'ContactPanda licenses', 'contactpanda' ) . '</h2>';
		echo '<p>' . esc_html__( 'Activate separate product keys or one MasterKey for several products and plans. The license server decides which signed product permissions a MasterKey grants. The entered key is never stored after activation.', 'contactpanda' ) . '</p>';
		echo '<div class="contactpanda-license-status"><p><strong>' . esc_html__( 'ContactPanda license', 'contactpanda' ) . ':</strong> ' . esc_html__( 'Free license active', 'contactpanda' ) . '</p></div>';
		if ( $offline['failed'] && ! $offline['overdue'] ) {
			echo '<p class="description">' . esc_html__( 'The license service is temporarily unavailable. Cached permissions remain active and ContactPanda will retry automatically.', 'contactpanda' ) . '</p>';
		}

		if ( $state['licenses'] ) {
			echo '<h3>' . esc_html__( 'Activated licenses', 'contactpanda' ) . '</h3>';
			foreach ( $state['licenses'] as $id => $license ) {
				$labels = array();
				foreach ( $license['entitlements'] as $module ) {
					$labels[] = $catalog[ $module ] ?? ( 'all' === $module ? __( 'All modules', 'contactpanda' ) : $module );
				}
				echo '<div class="contactpanda-card contactpanda-license-entry">';
				echo '<h4>' . esc_html( $license['license_label'] ?: __( 'ContactPanda license', 'contactpanda' ) ) . '</h4>';
				echo '<p><strong>' . esc_html__( 'Type', 'contactpanda' ) . ':</strong> ' . esc_html( self::type_label( $license['license_type'] ) ) . '<br>';
				echo '<strong>' . esc_html__( 'Modules', 'contactpanda' ) . ':</strong> ' . esc_html( $labels ? implode( ', ', $labels ) : __( 'No modules assigned', 'contactpanda' ) ) . '<br>';
				echo '<strong>' . esc_html__( 'Status', 'contactpanda' ) . ':</strong> ' . esc_html( self::status_label( $license['status'] ) );
				if ( $license['updates_until'] ) {
					echo '<br><strong>' . esc_html__( 'Updates until', 'contactpanda' ) . ':</strong> ' . esc_html( mysql2date( get_option( 'date_format' ), $license['updates_until'] . ' 00:00:00' ) );
				}
				if ( $license['last_checked_at'] ) {
					echo '<br><strong>' . esc_html__( 'Last checked', 'contactpanda' ) . ':</strong> ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $license['last_checked_at'] ) );
				}
				echo '</p>';
				if ( $license['activation_id'] && $license['activation_token'] ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_disconnect_license"><input type="hidden" name="license_id" value="' . esc_attr( $id ) . '">';
					wp_nonce_field( 'contactpanda_disconnect_license_' . $id );
					echo '<button class="button">' . esc_html__( 'Disconnect this website', 'contactpanda' ) . '</button></form>';
				}
				echo '</div>';
			}
		}

		echo '<h3>' . esc_html__( 'Products and plans', 'contactpanda' ) . '</h3><div class="contactpanda-license-products">';
		foreach ( self::products() as $product ) {
			$summary = self::product_license_summary( $product, $state );
			$status_labels = array( 'free' => __( 'Free product active', 'contactpanda' ), 'valid' => __( 'License active', 'contactpanda' ), 'expired' => __( 'Updates and support expired', 'contactpanda' ), 'blocked' => __( 'License blocked', 'contactpanda' ), 'unlicensed' => __( 'No license activated', 'contactpanda' ) );
			echo '<section class="contactpanda-license-product"><div><h4>' . esc_html( $product['label'] ) . '</h4>';
			if ( $product['version'] ) echo '<p class="description">' . esc_html( sprintf( __( 'Installed version: %s', 'contactpanda' ), $product['version'] ) ) . '</p>';
			echo '</div><p><span class="contactpanda-status-badge ' . ( in_array( $summary['status'], array( 'free', 'valid' ), true ) ? 'is-positive' : '' ) . '">' . esc_html( $status_labels[ $summary['status'] ] ) . '</span></p><dl>';
			echo '<div><dt>' . esc_html__( 'Updates', 'contactpanda' ) . '</dt><dd>' . esc_html( $summary['updates'] ? mysql2date( get_option( 'date_format' ), $summary['updates'] . ' 00:00:00' ) : ( $product['free'] ? __( 'Included', 'contactpanda' ) : __( 'Not available', 'contactpanda' ) ) ) . '</dd></div>';
			echo '<div><dt>' . esc_html__( 'Support', 'contactpanda' ) . '</dt><dd>' . esc_html( $summary['support'] ? mysql2date( get_option( 'date_format' ), $summary['support'] . ' 00:00:00' ) : __( 'Not specified', 'contactpanda' ) ) . '</dd></div>';
			$usage = $summary['site_limit'] ? sprintf( '%1$s / %2$s', $summary['site_used'] ?: '0', $summary['site_limit'] ) : ( $summary['site_used'] ?: __( 'Not specified', 'contactpanda' ) );
			echo '<div><dt>' . esc_html__( 'Website usage', 'contactpanda' ) . '</dt><dd>' . esc_html( $usage ) . '</dd></div></dl></section>';
		}
		echo '</div>';

		echo '<h3>' . esc_html__( 'Module status', 'contactpanda' ) . '</h3><div class="contactpanda-license-groups">';
		$groups = array(
			'plus'     => array( 'label' => __( 'ContactPanda Plus Complete', 'contactpanda' ), 'modules' => array( 'advanced_newsletter', 'forms_editor', 'automations', 'advanced_integrations', 'form_migration' ) ),
			'pro'      => array( 'label' => __( 'ContactPanda Pro', 'contactpanda' ), 'modules' => array( 'support_desk' ) ),
			'business' => array( 'label' => __( 'ContactPanda Business', 'contactpanda' ), 'modules' => array( 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management', 'software_licensing' ) ),
		);
		foreach ( $groups as $group_key => $group ) {
			$package_enabled = self::entitled( $group_key );
			echo '<section class="contactpanda-license-group"><h4>' . esc_html( $group['label'] ) . ( $package_enabled ? ' <span class="contactpanda-status-badge is-positive">' . esc_html__( 'Complete package enabled', 'contactpanda' ) . '</span>' : '' ) . '</h4>';
			foreach ( $group['modules'] as $module ) {
				if ( ! isset( $catalog[ $module ] ) ) continue;
				$enabled = $package_enabled || self::entitled( $module );
				echo '<p><strong>' . esc_html( $catalog[ $module ] ) . '</strong>: ' . esc_html( $enabled ? __( 'Enabled', 'contactpanda' ) : __( 'Not enabled', 'contactpanda' ) ) . '</p>';
			}
			echo '</section>';
		}
		echo '</div>';

		echo '<h3>' . esc_html__( 'Add or renew a license', 'contactpanda' ) . '</h3><p class="description">' . esc_html__( 'Enter either an individual product key or a MasterKey. Additional keys can be activated one after another.', 'contactpanda' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_activate_license">';
		wp_nonce_field( 'contactpanda_activate_license' );
		echo '<label><span>' . esc_html__( 'License key', 'contactpanda' ) . '</span><input type="password" name="license_key" autocomplete="off" required value="" placeholder="CP-…"></label><p><button class="button button-primary">' . esc_html__( 'Activate or renew license', 'contactpanda' ) . '</button></p></form>';
		if ( array_filter( wp_list_pluck( $state['licenses'], 'activation_token' ) ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_check_licenses">';
			wp_nonce_field( 'contactpanda_check_licenses' );
			echo '<button class="button">' . esc_html__( 'Check licenses now', 'contactpanda' ) . '</button></form>';
		}
		echo '<p class="description">' . esc_html__( 'Only technical license and installation data is sent to mycontactpanda.com. Contacts, orders, messages, and newsletter content are never transmitted.', 'contactpanda' ) . '</p></section>';
	}

	private static function type_label( $type ) {
		$labels = array(
			'master'   => __( 'MasterKey', 'contactpanda' ),
			'customer' => __( 'Customer license', 'contactpanda' ),
			'partner'  => __( 'Partner license', 'contactpanda' ),
		);
		return $labels[ $type ] ?? __( 'Module license', 'contactpanda' );
	}

	private static function status_label( $status ) {
		$labels = array(
			'valid'        => __( 'Active', 'contactpanda' ),
			'expired'      => __( 'Updates expired — modules remain usable', 'contactpanda' ),
			'blocked'      => __( 'Blocked', 'contactpanda' ),
			'disconnected' => __( 'Disconnected', 'contactpanda' ),
		);
		return $labels[ $status ] ?? __( 'Unknown', 'contactpanda' );
	}

	private static function telemetry() {
		return array(
			'api_version'       => self::RESPONSE_VERSION,
			'product'           => self::PRODUCT,
			'installation_id'   => self::installation_id(),
			'site_url'          => home_url( '/' ),
			'network_url'       => network_home_url( '/' ),
			'site_name'         => get_bloginfo( 'name' ),
			'locale'            => get_locale(),
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'basic_version'     => defined( 'CONTACTPANDA_VERSION' ) ? CONTACTPANDA_VERSION : '',
			'plus_version'      => defined( 'CONTACTPANDA_PLUS_VERSION' ) ? CONTACTPANDA_PLUS_VERSION : '',
			'pro_version'       => defined( 'CONTACTPANDA_PRO_VERSION' ) ? CONTACTPANDA_PRO_VERSION : '',
			'business_version'  => defined( 'CONTACTPANDA_BUSINESS_VERSION' ) ? CONTACTPANDA_BUSINESS_VERSION : '',
		);
	}

	private static function request( $action, $payload ) {
		$body = array_merge( self::telemetry(), $payload );

		/*
		 * A WordPress installation that also hosts Panda License must not make an
		 * HTTPS request back to itself while its own updater has enabled maintenance
		 * mode. The license server can provide the same signed response in-process.
		 * Other installations leave this filter untouched and keep using HTTPS.
		 */
		$local = apply_filters( 'contactpanda_license_local_response', null, $action, $body );
		if ( is_wp_error( $local ) ) {
			return $local;
		}
		if ( is_array( $local ) ) {
			return self::verified_data( $local );
		}

		$response = wp_safe_remote_post(
			self::endpoint( $action ),
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json; charset=utf-8',
				),
				'body'        => wp_json_encode( $body ),
			)
		);
		$response = apply_filters( 'contactpanda_license_response', $response, $action, $body );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 500 || ! is_array( $data ) ) {
			return new WP_Error( 'contactpanda_license_invalid' );
		}
		return self::verified_data( $data );
	}

	/**
	 * Verify Ed25519 payloads when the deployment public key is configured.
	 * TLS remains mandatory. CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE can enforce
	 * signatures after mycontactpanda.com has been provisioned.
	 */
	private static function verified_data( $data ) {
		$public_key = defined( 'CONTACTPANDA_LICENSE_PUBLIC_KEY' ) ? trim( (string) CONTACTPANDA_LICENSE_PUBLIC_KEY ) : '';
		$required   = defined( 'CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE' ) && CONTACTPANDA_LICENSE_REQUIRE_SIGNATURE;
		if ( empty( $data['signature'] ) ) {
			return $required ? new WP_Error( 'contactpanda_license_signature' ) : $data;
		}
		if ( '' === $public_key ) {
			return $required ? new WP_Error( 'contactpanda_license_signature' ) : $data;
		}

		$signature = base64_decode( (string) $data['signature'], true );
		$key       = base64_decode( $public_key, true );
		$algorithm = sanitize_key( $data['signature_algorithm'] ?? 'ed25519' );
		$payload   = $data;
		unset( $payload['signature'], $payload['signature_algorithm'], $payload['signature_key_id'] );
		$canonical = self::canonical_json( $payload );
		$valid     = false;
		if ( 'ed25519' === $algorithm && function_exists( 'sodium_crypto_sign_verify_detached' ) && false !== $signature && false !== $key ) {
			$valid = sodium_crypto_sign_verify_detached( $signature, $canonical, $key );
		} elseif ( 'rsa-sha256' === $algorithm && function_exists( 'openssl_verify' ) && defined( 'OPENSSL_ALGO_SHA256' ) && false !== $signature && false !== $key ) {
			$valid = 1 === openssl_verify( $canonical, $signature, $key, OPENSSL_ALGO_SHA256 );
		}
		return $valid ? $payload : new WP_Error( 'contactpanda_license_signature' );
	}

	private static function canonical_json( $value ) {
		$value = self::normalize_signature_value( $value );
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function normalize_signature_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$keys    = array_keys( $value );
		$is_list = empty( $value ) || $keys === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize_signature_value( $item );
		}
		return $value;
	}

	public static function activate() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_activate_license' );
		$key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		if ( '' === $key || strlen( $key ) > 190 ) {
			self::redirect( 'invalid' );
		}
		$data = self::request( 'activate', array( 'license_key' => $key ) );
		if ( is_wp_error( $data ) ) {
			self::redirect( 'contactpanda_license_signature' === $data->get_error_code() ? 'signature' : 'network' );
		}
		if ( empty( $data['valid'] ) || empty( $data['activation_id'] ) || empty( $data['activation_token'] ) ) {
			self::redirect( 'invalid' );
		}
		$license = self::license_from_response( $data );
		if ( empty( $license['entitlements'] ) ) {
			self::redirect( 'invalid' );
		}
		$state = self::state();
		$id    = sanitize_text_field( $data['license_id'] ?? $data['activation_id'] );
		$state['licenses'][ $id ] = $license;
		$state['last_checked_at']          = current_time( 'mysql', true );
		$state['last_successful_check_at'] = $state['last_checked_at'];
		$state['last_failed_check_at']     = '';
		$state['check_failure_since']      = '';
		update_option( self::OPTION, $state, false );
		self::clear_update_caches();
		self::redirect( 'activated' );
	}

	private static function license_from_response( $data, $existing = array() ) {
		$existing     = self::normalize_license( $existing );
		$entitlements = is_array( $data['entitlements'] ?? null ) ? array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_entitlement' ), $data['entitlements'] ) ) ) ) : $existing['entitlements'];
		$status       = sanitize_key( $data['status'] ?? 'valid' );
		$status       = 'active' === $status ? 'valid' : ( 'deactivated' === $status ? 'disconnected' : $status );
		return self::normalize_license(
			array(
				'activation_id'    => sanitize_text_field( $data['activation_id'] ?? $existing['activation_id'] ),
				'activation_token' => sanitize_text_field( $data['activation_token'] ?? $existing['activation_token'] ),
				'license_label'    => sanitize_text_field( $data['license_label'] ?? $existing['license_label'] ),
				'license_type'     => sanitize_key( $data['license_type'] ?? $existing['license_type'] ),
				'entitlements'     => $entitlements,
				'limits'           => is_array( $data['limits'] ?? null ) ? $data['limits'] : $existing['limits'],
				'updates_until'    => self::date_value( $data['updates_until'] ?? $existing['updates_until'] ),
				'support_until'    => self::date_value( $data['support_until'] ?? $existing['support_until'] ),
				'activated_at'     => $existing['activated_at'] ?: current_time( 'mysql', true ),
				'last_checked_at'  => current_time( 'mysql', true ),
				'status'           => $status,
			)
		);
	}

	public static function manual_check() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_check_licenses' );
		$result = self::check_all();
		self::redirect( is_wp_error( $result ) ? 'network' : 'checked' );
	}

	public static function scheduled_check() {
		self::check_all();
	}

	private static function check_all() {
		$state       = self::state();
		$had_success = false;
		$attempted   = false;
		foreach ( $state['licenses'] as $id => $license ) {
			if ( empty( $license['activation_id'] ) || empty( $license['activation_token'] ) || 'disconnected' === $license['status'] ) {
				continue;
			}
			$attempted = true;
			$data = self::request(
				'check',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
				)
			);
			if ( is_wp_error( $data ) || ! isset( $data['valid'] ) ) {
				continue;
			}
			$had_success = true;
			if ( empty( $data['valid'] ) ) {
				// A rotated or revoked key invalidates every former activation token.
				$state['licenses'][ $id ]['activation_token'] = '';
				$state['licenses'][ $id ]['status'] = 'disconnected';
				$state['licenses'][ $id ]['last_checked_at'] = current_time( 'mysql', true );
			} elseif ( ! empty( $data['valid'] ) || 'blocked' === ( $data['status'] ?? '' ) ) {
				$state['licenses'][ $id ] = self::license_from_response( $data, $license );
			}
		}
		if ( $had_success || ! $attempted ) {
			$state['last_checked_at']            = current_time( 'mysql', true );
			$state['last_successful_check_at']   = $state['last_checked_at'];
			$state['last_failed_check_at']       = '';
			$state['check_failure_since']        = '';
			update_option( self::OPTION, $state, false );
			self::clear_update_caches();
			return true;
		}
		$state['last_failed_check_at'] = current_time( 'mysql', true );
		if ( ! $state['check_failure_since'] ) {
			$state['check_failure_since'] = $state['last_failed_check_at'];
		}
		update_option( self::OPTION, $state, false );
		return new WP_Error( 'contactpanda_license_network' );
	}

	private static function offline_status( $state = null ) {
		$state = is_array( $state ) ? $state : self::state();
		$since = sanitize_text_field( $state['check_failure_since'] ?? '' );
		$time  = $since ? strtotime( $since . ' UTC' ) : false;
		$age   = $time ? max( 0, time() - $time ) : 0;
		return array(
			'failed'  => (bool) $time,
			'overdue' => (bool) $time && $age >= self::OFFLINE_GRACE_DAYS * DAY_IN_SECONDS,
			'days'    => $time ? (int) floor( $age / DAY_IN_SECONDS ) : 0,
		);
	}

	public static function render_offline_notice() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$status = self::offline_status();
		if ( ! $status['overdue'] ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'The ContactPanda license service has not been reachable for more than seven days.', 'contactpanda' ) . '</strong> ' . esc_html__( 'Cached permissions remain active and all installed functions continue to work. Automatic checks will continue.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_check_licenses">';
		wp_nonce_field( 'contactpanda_check_licenses' );
		echo '<p><button class="button">' . esc_html__( 'Check licenses now', 'contactpanda' ) . '</button></p></form></div>';
	}

	public static function disconnect() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$id = sanitize_text_field( wp_unslash( $_POST['license_id'] ?? '' ) );
		check_admin_referer( 'contactpanda_disconnect_license_' . $id );
		$state = self::state();
		if ( empty( $state['licenses'][ $id ] ) ) {
			self::redirect( 'invalid' );
		}
		$license = $state['licenses'][ $id ];
		if ( $license['activation_id'] && $license['activation_token'] ) {
			self::request(
				'deactivate',
				array(
					'activation_id'    => $license['activation_id'],
					'activation_token' => $license['activation_token'],
				)
			);
		}
		$state['licenses'][ $id ]['activation_token'] = '';
		$state['licenses'][ $id ]['status']           = 'disconnected';
		update_option( self::OPTION, $state, false );
		self::clear_update_caches();
		self::redirect( 'disconnected' );
	}

	/** Clear public and protected update results after every license change. */
	private static function clear_update_caches() {
		delete_site_transient( 'contactpanda_github_release' );
		delete_site_transient( 'update_plugins' );
		do_action( 'contactpanda_license_state_refreshed' );
	}

	private static function date_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = substr( $value, 0, 10 );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ? $value : '';
	}

	private static function masked_key( $key ) {
		return strlen( $key ) > 8 ? substr( $key, 0, 4 ) . '…' . substr( $key, -4 ) : '••••••••';
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=license&license_notice=' . sanitize_key( $notice ) ) );
		exit;
	}
}
