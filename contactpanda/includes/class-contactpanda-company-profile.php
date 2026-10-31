<?php

defined( 'ABSPATH' ) || exit;

/** Central website-owner identity shared by every ContactPanda family member. */
final class ContactPanda_Company_Profile {
	const OPTION = 'contactpanda_company_profile';

	public static function boot() {
		add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ), 15 );
		add_action( 'contactpanda_render_general_settings_section_company', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_contactpanda_save_company_profile', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_contactpanda_import_woocommerce_company_profile', array( __CLASS__, 'import_woocommerce' ) );
	}

	public static function settings_section( $sections ) {
		$sections['company'] = __( 'Website owner', 'contactpanda' );
		return $sections;
	}

	public static function defaults() {
		return array(
			'company_name'       => get_bloginfo( 'name' ),
			'legal_form'         => '',
			'management'         => '',
			'company_register'   => '',
			'street'             => (string) get_option( 'woocommerce_store_address', '' ),
			'postcode'           => (string) get_option( 'woocommerce_store_postcode', '' ),
			'city'               => (string) get_option( 'woocommerce_store_city', '' ),
			'country'            => substr( (string) get_option( 'woocommerce_default_country', '' ), 0, 2 ),
			'email'              => (string) get_option( 'admin_email', '' ),
			'phone'              => '',
			'website'            => home_url( '/' ),
			'tax_number'         => '',
			'bank_name'          => '',
			'iban'               => '',
			'bic'                => '',
			'payment_terms_days' => 14,
			'logo_url'           => '',
			'alternate_logo_url' => '',
			'stamp_url'          => '',
			'signature_url'      => '',
			'legal_footer'       => '',
		);
	}

	/**
	 * Read the shared profile without losing values saved by earlier Pro builds.
	 *
	 * The legacy option remains untouched and is used only as a one-way fallback.
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || empty( array_filter( $stored ) ) ) {
			$legacy = get_option( 'contactpanda_pro_company_profile', array() );
			if ( is_array( $legacy ) ) {
				$stored = $legacy;
			}
		}
		return wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		}
		$profile = self::get();
		if ( isset( $_GET['company_profile_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Website owner details saved.', 'contactpanda' ) . '</p></div>';
		}
		if ( isset( $_GET['woocommerce_company_imported'] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Missing website owner details were adopted from WooCommerce.', 'contactpanda' ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Responsible for this website', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Maintain the responsible person or organisation, address, contact, tax, and logo details once. ContactPanda extensions use this shared source for messages and customer-facing pages.', 'contactpanda' ) . '</p>';
		if ( class_exists( 'WooCommerce' ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-inline-action-form"><input type="hidden" name="action" value="contactpanda_import_woocommerce_company_profile">';
			wp_nonce_field( 'contactpanda_import_woocommerce_company_profile' );
			echo '<button class="button button-secondary" type="submit">' . esc_html__( 'Adopt missing data from WooCommerce', 'contactpanda' ) . '</button><p class="description">' . esc_html__( 'Only empty ContactPanda fields are completed. WooCommerce settings are never changed.', 'contactpanda' ) . '</p></form>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-company-profile"><input type="hidden" name="action" value="contactpanda_save_company_profile">';
		wp_nonce_field( 'contactpanda_save_company_profile' );
		self::heading( __( 'Identity and address', 'contactpanda' ) );
		self::identity_row( $profile );
		self::address_row( $profile );
		self::heading( __( 'Contact and tax details', 'contactpanda' ) );
		self::row( $profile, array( 'email' => __( 'Email address', 'contactpanda' ), 'phone' => __( 'Telephone', 'contactpanda' ), 'website' => __( 'Website', 'contactpanda' ), 'tax_number' => __( 'Tax number or VAT ID', 'contactpanda' ) ), array( 'email' => 'email', 'website' => 'url' ) );
		self::heading( __( 'Branding', 'contactpanda' ) );
		self::row( $profile, array( 'logo_url' => __( 'Logo', 'contactpanda' ) ), array(), true );
		echo '<p class="description">' . esc_html__( 'Available WooCommerce store values are used only as initial suggestions. Saving here never changes WooCommerce settings.', 'contactpanda' ) . '</p>';
		submit_button( __( 'Save website owner details', 'contactpanda' ) );
		echo '</form></section>';
	}

	private static function identity_row( $profile ) {
		$legal_forms = self::legal_forms();
		$legal_form  = self::normalized_legal_form( $profile['legal_form'] ?? '' );
		$labels      = self::representative_labels();
		$hidden      = 'individual' === $legal_form;
		$label       = $labels[ $legal_form ] ?? $labels['other'];

		echo '<div class="contactpanda-company-profile-row">';
		echo '<label><span>' . esc_html__( 'Name or organisation', 'contactpanda' ) . '</span><input type="text" name="company_name" value="' . esc_attr( $profile['company_name'] ?? '' ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Legal form', 'contactpanda' ) . '</span><select name="legal_form" data-contactpanda-legal-form>';
		echo '<option value="">' . esc_html__( 'Please select', 'contactpanda' ) . '</option>';
		foreach ( $legal_forms as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $legal_form, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		if ( '' !== $legal_form && ! isset( $legal_forms[ $legal_form ] ) ) {
			echo '<option value="' . esc_attr( $legal_form ) . '" selected>' . esc_html( $profile['legal_form'] ) . '</option>';
		}
		echo '</select></label>';
		echo '<label data-contactpanda-representative-field data-labels="' . esc_attr( wp_json_encode( $labels ) ) . '"' . ( $hidden ? ' hidden' : '' ) . '><span data-contactpanda-representative-label>' . esc_html( $label ) . '</span><input type="text" name="management" value="' . esc_attr( $profile['management'] ?? '' ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Company or association register', 'contactpanda' ) . '</span><input type="text" name="company_register" value="' . esc_attr( $profile['company_register'] ?? '' ) . '"></label>';
		echo '</div>';
	}

	private static function address_row( $profile ) {
		$countries = self::countries();
		$country   = strtoupper( (string) ( $profile['country'] ?? '' ) );
		echo '<div class="contactpanda-company-profile-row">';
		foreach ( array( 'street' => __( 'Street and house number', 'contactpanda' ), 'postcode' => __( 'Postal code', 'contactpanda' ), 'city' => __( 'City', 'contactpanda' ) ) as $name => $label ) {
			echo '<label><span>' . esc_html( $label ) . '</span><input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[ $name ] ?? '' ) . '"></label>';
		}
		echo '<label><span>' . esc_html__( 'Country', 'contactpanda' ) . '</span><select name="country"><option value="">' . esc_html__( 'Please select', 'contactpanda' ) . '</option>';
		foreach ( $countries as $code => $name ) {
			if ( '__separator__' === $code ) {
				echo '<option value="" disabled>' . esc_html( $name ) . '</option>';
				continue;
			}
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $country, $code, false ) . '>' . esc_html( $name ) . '</option>';
		}
		if ( '' !== $country && ! isset( $countries[ $country ] ) ) {
			echo '<option value="' . esc_attr( $country ) . '" selected>' . esc_html( $country ) . '</option>';
		}
		echo '</select></label></div>';
	}

	private static function legal_forms() {
		return array(
			'individual'          => __( 'Individual', 'contactpanda' ),
			'sole_proprietorship' => __( 'Sole proprietorship', 'contactpanda' ),
			'gmbh'                => __( 'Limited liability company (GmbH)', 'contactpanda' ),
			'ag'                  => __( 'Stock corporation (AG)', 'contactpanda' ),
			'og'                  => __( 'General partnership (OG)', 'contactpanda' ),
			'kg'                  => __( 'Limited partnership (KG)', 'contactpanda' ),
			'association'         => __( 'Association', 'contactpanda' ),
			'cooperative'         => __( 'Cooperative', 'contactpanda' ),
			'public_body'         => __( 'Public-law entity', 'contactpanda' ),
			'other'               => __( 'Other legal form', 'contactpanda' ),
		);
	}

	private static function representative_labels() {
		return array(
			'individual'          => '',
			'sole_proprietorship' => __( 'Owner', 'contactpanda' ),
			'gmbh'                => __( 'Managing director', 'contactpanda' ),
			'ag'                  => __( 'Executive board', 'contactpanda' ),
			'og'                  => __( 'Authorised partner', 'contactpanda' ),
			'kg'                  => __( 'Authorised partner', 'contactpanda' ),
			'association'         => __( 'Chairperson or president', 'contactpanda' ),
			'cooperative'         => __( 'Executive board', 'contactpanda' ),
			'public_body'         => __( 'Authorised representative', 'contactpanda' ),
			'other'               => __( 'Responsible person', 'contactpanda' ),
		);
	}

	private static function normalized_legal_form( $value ) {
		$value = sanitize_text_field( (string) $value );
		$key   = strtolower( trim( $value ) );
		$map   = array(
			'einzelperson' => 'individual', 'individual' => 'individual',
			'einzelunternehmen' => 'sole_proprietorship', 'sole proprietorship' => 'sole_proprietorship',
			'gmbh' => 'gmbh', 'ag' => 'ag', 'og' => 'og', 'kg' => 'kg',
			'verein' => 'association', 'association' => 'association',
			'genossenschaft' => 'cooperative', 'cooperative' => 'cooperative',
		);
		return $map[ $key ] ?? $value;
	}

	private static function countries() {
		if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
			return ContactPanda_Plugin::ordered_countries( WC()->countries->get_countries() );
		}
		return ContactPanda_Plugin::ordered_countries(
			array(
				'AT' => __( 'Austria', 'contactpanda' ), 'DE' => __( 'Germany', 'contactpanda' ), 'CH' => __( 'Switzerland', 'contactpanda' ),
				'IT' => __( 'Italy', 'contactpanda' ), 'FR' => __( 'France', 'contactpanda' ), 'NL' => __( 'Netherlands', 'contactpanda' ),
				'BE' => __( 'Belgium', 'contactpanda' ), 'CZ' => __( 'Czechia', 'contactpanda' ), 'SK' => __( 'Slovakia', 'contactpanda' ),
				'HU' => __( 'Hungary', 'contactpanda' ), 'SI' => __( 'Slovenia', 'contactpanda' ), 'HR' => __( 'Croatia', 'contactpanda' ),
				'DK' => __( 'Denmark', 'contactpanda' ), 'EE' => __( 'Estonia', 'contactpanda' ), 'FI' => __( 'Finland', 'contactpanda' ),
				'GR' => __( 'Greece', 'contactpanda' ), 'IE' => __( 'Ireland', 'contactpanda' ), 'IS' => __( 'Iceland', 'contactpanda' ),
				'LI' => __( 'Liechtenstein', 'contactpanda' ), 'LT' => __( 'Lithuania', 'contactpanda' ), 'LU' => __( 'Luxembourg', 'contactpanda' ),
				'MT' => __( 'Malta', 'contactpanda' ), 'NO' => __( 'Norway', 'contactpanda' ), 'PL' => __( 'Poland', 'contactpanda' ),
				'PT' => __( 'Portugal', 'contactpanda' ), 'RO' => __( 'Romania', 'contactpanda' ), 'SE' => __( 'Sweden', 'contactpanda' ),
				'ES' => __( 'Spain', 'contactpanda' ), 'GB' => __( 'United Kingdom', 'contactpanda' ), 'US' => __( 'United States', 'contactpanda' ),
				'CA' => __( 'Canada', 'contactpanda' ), 'AU' => __( 'Australia', 'contactpanda' ),
			)
		);
	}

	private static function heading( $label ) {
		echo '<h3 class="contactpanda-settings-subheading">' . esc_html( $label ) . '</h3>';
	}

	private static function row( $profile, $fields, $types = array(), $images = false ) {
		echo '<div class="contactpanda-company-profile-row">';
		foreach ( $fields as $name => $label ) {
			$type = $types[ $name ] ?? 'text';
			echo '<label><span>' . esc_html( $label ) . '</span>';
			if ( $images ) {
				echo '<span class="contactpanda-image-picker-source"><input type="url" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[ $name ] ?? '' ) . '" data-contactpanda-image-url></span>';
			} else {
				echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[ $name ] ?? '' ) . '">';
			}
			echo '</label>';
		}
		echo '</div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		}
		check_admin_referer( 'contactpanda_save_company_profile' );
		$text    = array( 'company_name', 'management', 'company_register', 'street', 'postcode', 'city', 'country', 'phone', 'tax_number' );
		$profile = self::get();
		foreach ( $text as $key ) {
			$profile[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		}
		$profile['country']    = strtoupper( $profile['country'] );
		$profile['legal_form'] = self::normalized_legal_form( wp_unslash( $_POST['legal_form'] ?? '' ) );
		$profile['email'] = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		foreach ( array( 'website', 'logo_url' ) as $key ) {
			$profile[ $key ] = esc_url_raw( wp_unslash( $_POST[ $key ] ?? '' ) );
		}
		update_option( self::OPTION, $profile, false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=company&company_profile_saved=1' ) );
		exit;
	}

	public static function import_woocommerce() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_import_woocommerce_company_profile' );
		$profile = self::get();
		$country = explode( ':', (string) get_option( 'woocommerce_default_country', '' ) );
		$source = array(
			'company_name' => (string) get_option( 'woocommerce_store_name', get_bloginfo( 'name' ) ),
			'street'       => trim( (string) get_option( 'woocommerce_store_address', '' ) . ' ' . (string) get_option( 'woocommerce_store_address_2', '' ) ),
			'postcode'     => (string) get_option( 'woocommerce_store_postcode', '' ),
			'city'         => (string) get_option( 'woocommerce_store_city', '' ),
			'country'      => sanitize_text_field( $country[0] ?? '' ),
			'email'        => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email', '' ) ),
			'phone'        => (string) get_option( 'woocommerce_store_phone', '' ),
			'logo_url'     => (string) get_option( 'woocommerce_email_header_image', '' ),
		);
		foreach ( $source as $key => $value ) if ( empty( $profile[ $key ] ) && '' !== trim( $value ) ) $profile[ $key ] = 'email' === $key ? sanitize_email( $value ) : ( 'logo_url' === $key ? esc_url_raw( $value ) : sanitize_text_field( $value ) );
		update_option( self::OPTION, $profile, false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=company&woocommerce_company_imported=1' ) );
		exit;
	}
}
