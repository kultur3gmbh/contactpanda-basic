<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Registration {
	const OPTION = 'contactpanda_registration_settings';
	const GENERAL_OPTION = 'contactpanda_general_settings';
	const NOTICE_PREFIX = 'contactpanda_registration_notice_';

	/** Customer and business account registration is supplied by Plus. */
	public static function customer_registration_enabled() {
		return (bool) apply_filters( 'contactpanda_customer_registration_enabled', false );
	}

	public static function boot() {
		// Complete pending schema changes before assets, menus, AJAX handlers, or
		// front-end account screens can issue their first ContactPanda query.
		self::ensure_contactpanda_database( true );
		add_action( 'admin_init', array( __CLASS__, 'ensure_contactpanda_database' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		if ( self::customer_registration_enabled() ) {
			add_shortcode( 'contactpanda_registration', array( __CLASS__, 'shortcode' ) );
			add_action( 'admin_post_nopriv_contactpanda_register', array( __CLASS__, 'handle_registration' ) );
			add_action( 'admin_post_contactpanda_register', array( __CLASS__, 'handle_registration' ) );
			add_action( 'wp_ajax_nopriv_contactpanda_registration_validate_vat', array( __CLASS__, 'validate_vat_ajax' ) );
			add_action( 'wp_ajax_contactpanda_registration_validate_vat', array( __CLASS__, 'validate_vat_ajax' ) );
			add_filter( 'register_url', array( __CLASS__, 'registration_url' ) );
			add_action( 'login_form_register', array( __CLASS__, 'maybe_redirect_registration' ) );
			add_action( 'template_redirect', array( __CLASS__, 'render_virtual_registration' ) );
		}
		add_shortcode( 'contactpanda_newsletter_signup', array( __CLASS__, 'newsletter_shortcode' ) );
		add_action( 'admin_post_nopriv_contactpanda_newsletter_signup', array( __CLASS__, 'handle_newsletter_signup' ) );
		add_action( 'admin_post_contactpanda_newsletter_signup', array( __CLASS__, 'handle_newsletter_signup' ) );
		add_action( 'admin_post_nopriv_contactpanda_pending_resend', array( __CLASS__, 'handle_pending_resend' ) );
		add_action( 'admin_post_contactpanda_pending_resend', array( __CLASS__, 'handle_pending_resend' ) );
		add_action( 'admin_post_nopriv_contactpanda_pending_email', array( __CLASS__, 'handle_pending_email_change' ) );
		add_action( 'admin_post_contactpanda_pending_email', array( __CLASS__, 'handle_pending_email_change' ) );
		add_action( 'admin_post_contactpanda_registration_decision', array( __CLASS__, 'handle_decision' ) );
		add_action( 'admin_post_contactpanda_registration_resend', array( __CLASS__, 'handle_resend' ) );
		add_action( 'admin_post_contactpanda_setup_standalone', array( __CLASS__, 'handle_setup_standalone' ) );
		add_action( 'admin_post_contactpanda_migrate_groundhogg', array( __CLASS__, 'handle_migrate_groundhogg' ) );
		add_action( 'wp_ajax_contactpanda_migrate_groundhogg', array( __CLASS__, 'handle_migrate_groundhogg_ajax' ) );
		add_action( 'admin_post_contactpanda_reset_standalone', array( __CLASS__, 'handle_reset_standalone' ) );
		add_action( 'admin_post_contactpanda_adopt_theme_styles', array( __CLASS__, 'handle_adopt_theme_styles' ) );
		add_action( 'admin_post_contactpanda_salutation_dictionary', array( __CLASS__, 'handle_salutation_dictionary' ) );
		add_action( 'admin_post_contactpanda_export_salutations', array( __CLASS__, 'export_salutations' ) );
		add_action( 'admin_post_contactpanda_import_woocommerce', array( __CLASS__, 'handle_import_woocommerce' ) );
		add_action( 'admin_post_contactpanda_migrate_wordpress_users', array( __CLASS__, 'handle_migrate_wordpress_users' ) );
		add_action( 'admin_post_contactpanda_migrate_newsletter_plugin', array( __CLASS__, 'handle_migrate_newsletter_plugin' ) );
		add_action( 'wp_ajax_contactpanda_migrate_newsletter_plugin', array( __CLASS__, 'handle_migrate_newsletter_plugin' ) );
		add_action( 'admin_post_contactpanda_migrate_fluentcrm', array( __CLASS__, 'handle_migrate_fluentcrm' ) );
		add_action( 'admin_post_contactpanda_toggle_automatic_updates', array( __CLASS__, 'handle_automatic_updates' ) );
		add_action( 'wp_ajax_contactpanda_migration_progress', array( __CLASS__, 'migration_progress_ajax' ) );
		add_action( 'contactpanda_groundhogg_migration_progress', array( __CLASS__, 'store_migration_progress' ) );
		add_action( 'init', array( __CLASS__, 'handle_verification' ) );
		add_filter( 'the_content', array( __CLASS__, 'success_page_notice' ), 8 );
		add_filter( 'authenticate', array( __CLASS__, 'guard_login' ), 40, 3 );
	}

	public static function menu() {
		add_submenu_page(
			'contactpanda',
			__( 'ContactPanda settings', 'contactpanda' ),
			__( 'Settings', 'contactpanda' ),
			'manage_options',
			'contactpanda-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function register_settings() {
		register_setting(
			'contactpanda_general',
			self::GENERAL_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_general_settings' ),
				'default'           => self::general_defaults(),
			)
		);
		register_setting(
			'contactpanda_registration',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
	}

	private static function general_defaults() {
		return array(
			'data_source'        => self::groundhogg_active() ? 'groundhogg' : 'unconfigured',
			'theme_styles'       => 0,
			'theme_stylesheet'   => '',
			'theme_name'         => '',
			'portal_page_id'     => 0,
			'delete_on_uninstall' => 0,
		);
	}

	private static function general_settings() {
		return wp_parse_args( (array) get_option( self::GENERAL_OPTION, array() ), self::general_defaults() );
	}

	public static function activate() {
		self::ensure_contactpanda_database();
	}

	public static function ensure_contactpanda_database( $upgrade_only = false ) {
		$stored = get_option( self::GENERAL_OPTION, false );
		$source = is_array( $stored ) && isset( $stored['data_source'] ) ? sanitize_key( $stored['data_source'] ) : 'unconfigured';
		if ( 'standalone' === $source ) {
			$upgrade_only ? ContactPanda_Schema::maybe_upgrade() : ContactPanda_Schema::install();
			return;
		}
		if ( self::groundhogg_active() ) {
			return;
		}
		if ( ! in_array( $source, array( 'unconfigured', '' ), true ) ) {
			return;
		}
		$result = ContactPanda_Schema::install();
		if ( is_wp_error( $result ) ) {
			return;
		}
		$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::general_defaults() );
		$settings['data_source'] = 'standalone';
		update_option( self::GENERAL_OPTION, $settings, false );
	}

	public static function sanitize_general_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$current = self::general_settings();
		$source = isset( $input['data_source'] ) ? sanitize_key( $input['data_source'] ) : $current['data_source'];
		if ( 'groundhogg' === $source && ! self::groundhogg_active() && 'groundhogg' !== $current['data_source'] ) {
			$source = 'unconfigured';
		}
		if ( 'standalone' === $source && ! ContactPanda_Store::ready() ) {
			add_settings_error( self::GENERAL_OPTION, 'contactpanda_standalone_pending', __( 'Create the ContactPanda database before selecting ContactPanda mode.', 'contactpanda' ), 'warning' );
			$source = $current['data_source'];
		}
		if ( ! in_array( $source, array( 'groundhogg', 'standalone', 'unconfigured' ), true ) ) {
			$source = $current['data_source'];
		}
		$theme = wp_get_theme();
		$theme_styles = array_key_exists( 'theme_styles', $input ) ? ( empty( $input['theme_styles'] ) ? 0 : 1 ) : (int) ( $current['theme_styles'] ?? 0 );
		return array(
			'data_source'         => $source,
			'theme_styles'        => $theme_styles,
			'theme_stylesheet'    => $theme_styles ? sanitize_key( $theme->get_stylesheet() ) : '',
			'theme_name'          => $theme_styles ? sanitize_text_field( $theme->get( 'Name' ) ?: $theme->get_stylesheet() ) : '',
			'portal_page_id'      => absint( $input['portal_page_id'] ?? ( $current['portal_page_id'] ?? 0 ) ),
			'delete_on_uninstall' => array_key_exists( 'delete_on_uninstall', $input ) ? ( empty( $input['delete_on_uninstall'] ) ? 0 : 1 ) : (int) $current['delete_on_uninstall'],
		);
	}

	private static function groundhogg_active() {
		return defined( 'GROUNDHOGG_VERSION' ) || class_exists( '\\Groundhogg\\Contact' ) || function_exists( '\\Groundhogg\\get_db' );
	}

	private static function defaults() {
		$default_role = get_role( 'customer' ) ? 'customer' : 'subscriber';
		return array(
			'enabled'              => 0,
			'business_enabled'     => 1,
			'newsletter_enabled'   => 1,
			'require_email_verify' => 1,
			'require_approval'     => 1,
			'require_vat'          => 0,
			'allow_marketing'      => 1,
			'replace_registration' => 0,
			'allowed_roles'        => array( $default_role ),
			'registration_page_id' => 0,
			'privacy_page_id'      => absint( get_option( 'wp_page_for_privacy_policy' ) ),
			'terms_page_id'        => 0,
			'admin_email'          => sanitize_email( get_option( 'admin_email' ) ),
			'success_page_id'      => 0,
			'submission_message'   => __( 'Thank you. Please check your inbox and confirm your email address. Your requested customer role will only be assigned after approval.', 'contactpanda' ),
			'customer_submission_message' => __( 'Thank you. Please check your inbox and confirm your email address.', 'contactpanda' ),
			'confirmation_subject' => __( 'Confirm your registration at {site_name}', 'contactpanda' ),
			'confirmation_message' => __( 'Hello {first_name},

please confirm your email address by opening this link:
{confirmation_link}

The link is valid for 24 hours.', 'contactpanda' ),
			'verified_message'     => __( 'Your email address has been confirmed. Your requested customer role is still awaiting approval.', 'contactpanda' ),
			'customer_verified_message' => __( 'Your email address has been confirmed. You can now sign in.', 'contactpanda' ),
			'verified_page_id'     => 0,
			'verified_delay'       => 5,
			'newsletter_subject'   => __( 'Confirm your newsletter subscription at {site_name}', 'contactpanda' ),
			'newsletter_message'   => __( 'Hello {first_name},

please confirm your newsletter subscription by opening this link:
{confirmation_link}

The link is valid for 24 hours.', 'contactpanda' ),
			'newsletter_success'   => __( 'Thank you. Please check your inbox and confirm your newsletter subscription.', 'contactpanda' ),
			'newsletter_verified'  => __( 'Your newsletter subscription has been confirmed.', 'contactpanda' ),
			'newsletter_already_registered' => __( 'This email address is already registered for the newsletter.', 'contactpanda' ),
			'newsletter_privacy_text' => __( 'I have read the privacy policy.', 'contactpanda' ),
			'newsletter_consent_text' => __( 'I would like to receive the newsletter and confirm that I can withdraw my consent at any time.', 'contactpanda' ),
			'newsletter_submit_label' => __( 'Subscribe to newsletter', 'contactpanda' ),
			'newsletter_error'     => __( 'Please complete all required fields and confirm the newsletter consent.', 'contactpanda' ),
		);
	}

	private static function settings() {
		$settings = (array) get_option( self::OPTION, array() );
		if ( empty( $settings['allowed_roles'] ) && ! empty( $settings['approved_role'] ) ) {
			$settings['allowed_roles'] = array( sanitize_key( $settings['approved_role'] ) );
		}
		return wp_parse_args( $settings, self::defaults() );
	}

	public static function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$roles = self::allowed_roles();
		$requested_roles = isset( $input['allowed_roles'] ) && is_array( $input['allowed_roles'] ) ? array_map( 'sanitize_key', $input['allowed_roles'] ) : array();
		$allowed_roles = array();
		foreach ( $requested_roles as $role ) {
			if ( isset( $roles[ $role ] ) ) {
				$allowed_roles[] = $role;
			}
		}
		foreach ( array( 'submission_message', 'customer_submission_message', 'confirmation_subject', 'confirmation_message', 'verified_message', 'customer_verified_message', 'newsletter_subject', 'newsletter_message', 'newsletter_success', 'newsletter_verified', 'newsletter_already_registered', 'newsletter_privacy_text', 'newsletter_consent_text', 'newsletter_submit_label', 'newsletter_error' ) as $required_text ) {
			if ( empty( $input[ $required_text ] ) ) {
				$input[ $required_text ] = $defaults[ $required_text ];
			}
		}
		$sanitized = array(
			'enabled'              => empty( $input['enabled'] ) ? 0 : 1,
			'business_enabled'     => empty( $input['business_enabled'] ) ? 0 : 1,
			'newsletter_enabled'   => empty( $input['newsletter_enabled'] ) ? 0 : 1,
			'require_email_verify' => empty( $input['require_email_verify'] ) ? 0 : 1,
			'require_approval'     => 1,
			'require_vat'          => empty( $input['require_vat'] ) ? 0 : 1,
			'allow_marketing'      => empty( $input['allow_marketing'] ) ? 0 : 1,
			'replace_registration' => empty( $input['replace_registration'] ) ? 0 : 1,
			'allowed_roles'        => array_values( array_unique( $allowed_roles ) ),
			'registration_page_id' => isset( $input['registration_page_id'] ) ? absint( $input['registration_page_id'] ) : 0,
			'privacy_page_id'      => isset( $input['privacy_page_id'] ) ? absint( $input['privacy_page_id'] ) : 0,
			'terms_page_id'        => isset( $input['terms_page_id'] ) ? absint( $input['terms_page_id'] ) : 0,
			'admin_email'          => isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'],
			'success_page_id'      => isset( $input['success_page_id'] ) ? absint( $input['success_page_id'] ) : 0,
			'submission_message'   => isset( $input['submission_message'] ) ? sanitize_textarea_field( $input['submission_message'] ) : $defaults['submission_message'],
			'customer_submission_message' => isset( $input['customer_submission_message'] ) ? sanitize_textarea_field( $input['customer_submission_message'] ) : $defaults['customer_submission_message'],
			'confirmation_subject' => isset( $input['confirmation_subject'] ) ? sanitize_text_field( $input['confirmation_subject'] ) : $defaults['confirmation_subject'],
			'confirmation_message' => isset( $input['confirmation_message'] ) ? wp_kses_post( $input['confirmation_message'] ) : $defaults['confirmation_message'],
			'verified_message'     => isset( $input['verified_message'] ) ? sanitize_textarea_field( $input['verified_message'] ) : $defaults['verified_message'],
			'customer_verified_message' => isset( $input['customer_verified_message'] ) ? sanitize_textarea_field( $input['customer_verified_message'] ) : $defaults['customer_verified_message'],
			'verified_page_id'     => isset( $input['verified_page_id'] ) ? absint( $input['verified_page_id'] ) : 0,
			'verified_delay'       => isset( $input['verified_delay'] ) ? min( 30, max( 0, absint( $input['verified_delay'] ) ) ) : $defaults['verified_delay'],
			'newsletter_subject'   => isset( $input['newsletter_subject'] ) ? sanitize_text_field( $input['newsletter_subject'] ) : $defaults['newsletter_subject'],
			'newsletter_message'   => isset( $input['newsletter_message'] ) ? wp_kses_post( $input['newsletter_message'] ) : $defaults['newsletter_message'],
			'newsletter_success'   => isset( $input['newsletter_success'] ) ? sanitize_textarea_field( $input['newsletter_success'] ) : $defaults['newsletter_success'],
			'newsletter_verified'  => isset( $input['newsletter_verified'] ) ? sanitize_textarea_field( $input['newsletter_verified'] ) : $defaults['newsletter_verified'],
			'newsletter_already_registered' => isset( $input['newsletter_already_registered'] ) ? sanitize_textarea_field( $input['newsletter_already_registered'] ) : $defaults['newsletter_already_registered'],
			'newsletter_privacy_text' => isset( $input['newsletter_privacy_text'] ) ? sanitize_text_field( $input['newsletter_privacy_text'] ) : $defaults['newsletter_privacy_text'],
			'newsletter_consent_text' => isset( $input['newsletter_consent_text'] ) ? sanitize_textarea_field( $input['newsletter_consent_text'] ) : $defaults['newsletter_consent_text'],
			'newsletter_submit_label' => isset( $input['newsletter_submit_label'] ) ? sanitize_text_field( $input['newsletter_submit_label'] ) : $defaults['newsletter_submit_label'],
			'newsletter_error'     => isset( $input['newsletter_error'] ) ? sanitize_textarea_field( $input['newsletter_error'] ) : $defaults['newsletter_error'],
		);
		if ( ! self::customer_registration_enabled() ) {
			$current = wp_parse_args( (array) get_option( self::OPTION, array() ), $defaults );
			foreach ( array( 'enabled', 'business_enabled', 'require_email_verify', 'require_approval', 'require_vat', 'allow_marketing', 'replace_registration', 'allowed_roles', 'registration_page_id', 'privacy_page_id', 'terms_page_id', 'admin_email', 'success_page_id', 'submission_message', 'customer_submission_message', 'confirmation_subject', 'confirmation_message', 'verified_message', 'customer_verified_message', 'verified_page_id', 'verified_delay' ) as $key ) {
				$sanitized[ $key ] = $current[ $key ];
			}
		}
		return $sanitized;
	}

	private static function allowed_roles() {
		$registry = function_exists( 'wp_roles' ) ? wp_roles() : null;
		$roles = $registry && isset( $registry->roles ) && is_array( $registry->roles ) ? $registry->roles : array();
		$result = array();
		foreach ( $roles as $key => $role ) {
			$capabilities = isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ? $role['capabilities'] : array();
			if ( 'administrator' === $key || ! empty( $capabilities['manage_options'] ) || ! empty( $capabilities['edit_users'] ) || ! empty( $capabilities['activate_plugins'] ) ) {
				continue;
			}
			$result[ $key ] = isset( $role['name'] ) ? translate_user_role( $role['name'] ) : $key;
		}
		return $result;
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contactpanda' ) );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation.
		if ( 'duplicates' === $tab ) { $_GET['other_section'] = $tab; $tab = 'other'; }
		if ( 'license' === $tab ) { $_GET['general_section'] = 'license'; $tab = 'general'; }
		$tabs = (array) apply_filters(
			'contactpanda_settings_tabs',
			array(
				'general'      => __( 'General', 'contactpanda' ),
				'portal'       => __( 'Customer portal', 'contactpanda' ),
				'integrations' => __( 'Integrations', 'contactpanda' ),
				'misc'         => __( 'Miscellaneous parameters', 'contactpanda' ),
				'registration' => __( 'Registration & accounts', 'contactpanda' ),
				'other'        => __( 'Miscellaneous', 'contactpanda' ),
			)
		);
		$tabs = array_filter( $tabs, static function ( $label, $key ) {
			return '' !== sanitize_key( $key ) && '' !== trim( wp_strip_all_tags( (string) $label ) );
		}, ARRAY_FILTER_USE_BOTH );
		if ( isset( $tabs['other'] ) ) {
			$other_label = $tabs['other'];
			unset( $tabs['other'] );
			$tabs['other'] = $other_label;
		}
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		echo '<div class="wrap contactpanda-wrap"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'Settings', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Configure ContactPanda and its optional integrations.', 'contactpanda' ) . '</p></div></div>';
		echo '<nav class="nav-tab-wrapper contactpanda-settings-tabs" aria-label="' . esc_attr__( 'ContactPanda settings', 'contactpanda' ) . '">';
		foreach ( $tabs as $tab_key => $tab_label ) {
			$tab_key = sanitize_key( $tab_key );
			echo '<a class="nav-tab ' . ( $tab_key === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=' . $tab_key ) ) . '">' . esc_html( $tab_label ) . '</a>';
		}
		echo '</nav>';
		settings_errors();
		if ( 'misc' === $tab ) {
			self::render_misc_settings();
		} elseif ( 'general' === $tab ) {
			self::render_general_tab();
		} elseif ( 'portal' === $tab ) {
			self::render_customer_portal_settings();
		} elseif ( 'integrations' === $tab ) {
			self::render_integrations_settings();
		} elseif ( 'registration' === $tab ) {
			self::render_registration_settings();
		} elseif ( 'other' === $tab ) {
			self::render_other_settings();
		} else {
			do_action( 'contactpanda_render_settings_tab_' . $tab );
		}
		echo '</div>';
	}

	private static function render_general_tab() {
		$section = isset( $_GET['general_section'] ) ? sanitize_key( wp_unslash( $_GET['general_section'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sections = (array) apply_filters(
			'contactpanda_general_settings_sections',
			array(
				'settings' => __( 'General settings', 'contactpanda' ),
			)
		);
		if ( ! isset( $sections[ $section ] ) ) {
			$section = 'settings';
		}
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs">';
		foreach ( $sections as $key => $label ) {
			$url = 'settings' === $key
				? admin_url( 'admin.php?page=contactpanda-settings&tab=general' )
				: admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=' . sanitize_key( $key ) );
			echo '<a class="nav-tab ' . ( $section === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'settings' === $section ) {
			self::render_general_settings();
		} else {
			do_action( 'contactpanda_render_general_settings_section_' . $section );
		}
	}

	private static function render_other_settings() {
		$base_sections = array();
		if ( ContactPanda_Store::standalone() ) {
			$base_sections['duplicates'] = __( 'Duplicates', 'contactpanda' );
		}
		$base_sections['admin_notices'] = __( 'Admin notices', 'contactpanda' );
		$sections = (array) apply_filters( 'contactpanda_other_settings_sections', $base_sections );
		$section_keys = array_keys( $sections );
		$default_section = (string) reset( $section_keys );
		$current = isset( $_GET['other_section'] ) ? sanitize_key( wp_unslash( $_GET['other_section'] ) ) : $default_section; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $sections[ $current ] ) ) $current = $default_section;
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs">'; foreach ( $sections as $key => $label ) echo '<a class="nav-tab ' . ( $current === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=other&other_section=' . sanitize_key( $key ) ) ) . '">' . esc_html( $label ) . '</a>'; echo '</nav>';
		if ( 'admin_notices' === $current ) ContactPanda_Data_Tools::render_notice_settings(); else do_action( 'contactpanda_render_other_settings_section_' . $current );
	}

	private static function render_misc_settings() {
		$notice = get_transient( 'contactpanda_database_notice_' . get_current_user_id() );
		if ( $notice ) { delete_transient( 'contactpanda_database_notice_' . get_current_user_id() ); echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; }
		$sections = (array) apply_filters( 'contactpanda_misc_settings_sections', array( 'salutations' => __( 'First names', 'contactpanda' ) ) );
		$current = isset( $_GET['misc_section'] ) ? sanitize_key( wp_unslash( $_GET['misc_section'] ) ) : 'salutations'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing.
		if ( ! isset( $sections[ $current ] ) ) $current = 'salutations';
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs">';
		foreach ( $sections as $key => $label ) echo '<a class="nav-tab ' . ( $current === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=' . sanitize_key( $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</nav>';
		if ( 'salutations' !== $current ) { do_action( 'contactpanda_render_misc_settings_section_' . $current ); return; }
		$language = ContactPanda_Store::salutation_language( isset( $_GET['library_language'] ) ? wp_unslash( $_GET['library_language'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$search = isset( $_GET['salutation_search'] ) ? sanitize_text_field( wp_unslash( $_GET['salutation_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$page = max( 1, absint( $_GET['salutation_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list pagination.
		$per_page = 50;
		$total_rows = ContactPanda_Store::salutations_count( $language, $search );
		$rows = ContactPanda_Store::salutations( $language, $per_page, ( $page - 1 ) * $per_page, $search );
		$central_settings = (array) get_option( 'contactpanda_central_salutation_library', array() );
		$last_sync_value = $central_settings['last_success_at'] ?? ( empty( $central_settings['error'] ) ? ( $central_settings['updated_at'] ?? '' ) : '' );
		$last_sync = $last_sync_value ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_value ) : __( 'Never', 'contactpanda' );
		$edit_id = 0;
		$editing = false;
		do_action( 'contactpanda_render_salutation_tools' );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Salutation library', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Saved assignments are applied automatically when the same first name is entered again. A manually selected salutation updates the library.', 'contactpanda' ) . '</p><p class="description"><strong>' . esc_html__( 'Last central synchronization:', 'contactpanda' ) . '</strong> ' . esc_html( $last_sync ) . '</p>';
		echo '<form method="get" class="contactpanda-search"><input type="hidden" name="page" value="contactpanda-settings"><input type="hidden" name="tab" value="misc"><input type="hidden" name="misc_section" value="salutations"><select name="library_language" aria-label="' . esc_attr__( 'Language', 'contactpanda' ) . '">'; foreach ( array( 'de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano' ) as $code => $label ) echo '<option value="' . esc_attr( $code ) . '"' . selected( $language, $code, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select><input type="search" name="salutation_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search first names', 'contactpanda' ) . '"><button class="button">' . esc_html__( 'Apply', 'contactpanda' ) . '</button></form>';
		$bulk = get_transient( 'contactpanda_salutation_bulk_' . get_current_user_id() );
		if ( is_array( $bulk ) ) { delete_transient( 'contactpanda_salutation_bulk_' . get_current_user_id() ); echo '<div class="notice notice-info inline"><p>' . esc_html( $bulk['applied'] ? sprintf( __( '%d missing salutations were assigned.', 'contactpanda' ), absint( $bulk['count'] ) ) : sprintf( __( '%d contacts can be assigned from the library. Confirm to apply the changes.', 'contactpanda' ), absint( $bulk['count'] ) ) ) . '</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_salutation_dictionary"><input type="hidden" name="operation" value="save"><input type="hidden" name="original_id" value="' . esc_attr( $edit_id ) . '"><input type="hidden" name="language" value="' . esc_attr( $language ) . '">'; wp_nonce_field( 'contactpanda_salutation_dictionary' );
		echo '<label><span>' . esc_html__( 'First name', 'contactpanda' ) . '</span><input required name="first_name" value="' . esc_attr( $editing['first_name'] ?? '' ) . '"></label><label><span>' . esc_html__( 'Salutation', 'contactpanda' ) . '</span><select name="salutation" required><option value="">' . esc_html__( '--- Please select ---', 'contactpanda' ) . '</option><option value="mr"' . selected( $editing['salutation'] ?? '', 'mr', false ) . '>' . esc_html__( 'Mr', 'contactpanda' ) . '</option><option value="ms"' . selected( $editing['salutation'] ?? '', 'ms', false ) . '>' . esc_html__( 'Ms', 'contactpanda' ) . '</option><option value="family"' . selected( $editing['salutation'] ?? '', 'family', false ) . '>' . esc_html__( 'Family', 'contactpanda' ) . '</option><option value="neutral"' . selected( $editing['salutation'] ?? '', 'neutral', false ) . '>' . esc_html__( 'Neutral', 'contactpanda' ) . '</option></select></label><p><button class="button button-primary">' . ( $editing ? esc_html__( 'Save changes', 'contactpanda' ) : esc_html__( 'Add entry', 'contactpanda' ) ) . '</button>' . ( $editing ? ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=salutations' ) ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a>' : '' ) . '</p></form>';
		echo '<table class="widefat striped contactpanda-table"><thead><tr><th>' . esc_html__( 'First name', 'contactpanda' ) . '</th><th>' . esc_html__( 'Salutation', 'contactpanda' ) . '</th><th>' . esc_html__( 'Source', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_salutation_dictionary&operation=delete&id=' . rawurlencode( (string) $row['id'] ) ), 'contactpanda_salutation_dictionary' );
			$salutation_labels = array( 'mr' => __( 'Mr', 'contactpanda' ), 'ms' => __( 'Ms', 'contactpanda' ), 'family' => __( 'Family', 'contactpanda' ), 'neutral' => __( 'Neutral', 'contactpanda' ) );
			$source_labels = array( 'manual' => __( 'Manual', 'contactpanda' ), 'confirmed' => __( 'Confirmed selection', 'contactpanda' ), 'import' => __( 'Import', 'contactpanda' ), 'central' => __( 'Central library', 'contactpanda' ) );
			echo '<tr><td colspan="4"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-salutation-inline"><input type="hidden" name="action" value="contactpanda_salutation_dictionary"><input type="hidden" name="operation" value="save"><input type="hidden" name="original_id" value="' . esc_attr( $row['id'] ) . '"><input type="hidden" name="language" value="' . esc_attr( $language ) . '">'; wp_nonce_field( 'contactpanda_salutation_dictionary' );
			echo '<input aria-label="' . esc_attr__( 'First name', 'contactpanda' ) . '" name="first_name" value="' . esc_attr( $row['first_name'] ) . '"><select aria-label="' . esc_attr__( 'Salutation', 'contactpanda' ) . '" name="salutation">'; foreach ( $salutation_labels as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $row['salutation'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select><span>' . esc_html( $source_labels[ $row['source'] ] ?? $row['source'] ) . '</span><button class="button button-small">' . esc_html__( 'Save', 'contactpanda' ) . '</button><a class="button-link-delete" href="' . esc_url( $delete ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this salutation assignment?', 'contactpanda' ) ) ) . ');">' . esc_html__( 'Delete', 'contactpanda' ) . '</a></form></td></tr>';
		}
		if ( ! $rows ) echo '<tr><td colspan="4">' . esc_html__( 'The dictionary is empty.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table>';
		if ( $total_rows > $per_page ) { $base = array( 'page' => 'contactpanda-settings', 'tab' => 'misc', 'misc_section' => 'salutations', 'library_language' => $language, 'salutation_search' => $search ); echo '<div class="tablenav"><div class="tablenav-pages">'; if ( $page > 1 ) echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base, array( 'salutation_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Previous', 'contactpanda' ) . '</a>'; echo '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'contactpanda' ), $page, (int) ceil( $total_rows / $per_page ) ) ) . '</span>'; if ( $page * $per_page < $total_rows ) echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base, array( 'salutation_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Next', 'contactpanda' ) . '</a>'; echo '</div></div>'; }
		echo '<div class="contactpanda-database-actions"><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_export_salutations&language=' . $language ), 'contactpanda_export_salutations' ) ) . '">' . esc_html__( 'Export CSV', 'contactpanda' ) . '</a>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_salutation_dictionary"><input type="hidden" name="operation" value="import"><input type="hidden" name="language" value="' . esc_attr( $language ) . '">'; wp_nonce_field( 'contactpanda_salutation_dictionary' );
		echo '<input type="file" name="dictionary_file" accept=".csv,text/csv" required><button class="button">' . esc_html__( 'Import and merge CSV', 'contactpanda' ) . '</button></form></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-salutation-bulk">'; wp_nonce_field( 'contactpanda_assign_missing_salutations' ); echo '<input type="hidden" name="action" value="contactpanda_assign_missing_salutations"><label><input type="checkbox" name="confirm_apply" value="1"> ' . esc_html__( 'Apply the assignments immediately after checking.', 'contactpanda' ) . '</label> <button class="button">' . esc_html__( 'Reconcile now', 'contactpanda' ) . '</button></form></section>';
	}

	public static function handle_salutation_dictionary() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_salutation_dictionary' );
		$operation = sanitize_key( wp_unslash( $_REQUEST['operation'] ?? '' ) );
		if ( 'delete' === $operation ) {
			ContactPanda_Store::delete_salutation( sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) ) );
		} elseif ( 'save' === $operation ) {
			$original_id = sanitize_text_field( wp_unslash( $_POST['original_id'] ?? '' ) );
			$original = $original_id ? ContactPanda_Store::salutation( $original_id ) : false;
			$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
			if ( $original && 0 !== strcasecmp( $original['first_name'], $first_name ) ) ContactPanda_Store::delete_salutation( $original_id );
			ContactPanda_Store::learn_salutation( $first_name, sanitize_key( wp_unslash( $_POST['salutation'] ?? '' ) ), 'manual', sanitize_key( wp_unslash( $_POST['language'] ?? '' ) ) );
		} elseif ( 'import' === $operation && ! empty( $_FILES['dictionary_file']['tmp_name'] ) ) {
			$handle = fopen( $_FILES['dictionary_file']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- protected uploaded CSV stream.
			if ( $handle ) {
				$first = true;
				while ( false !== ( $row = fgetcsv( $handle, 0, ';' ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
					if ( $first && isset( $row[0] ) && false !== stripos( $row[0], 'vorname' ) ) { $first = false; continue; }
					$first = false; if ( count( $row ) < 2 ) continue;
					ContactPanda_Store::learn_salutation( $row[0], $row[1], 'import', sanitize_key( wp_unslash( $_POST['language'] ?? '' ) ) );
				}
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc' ) ); exit;
	}

	public static function export_salutations() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_export_salutations' );
		nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="contactpanda-anreden.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'Vorname', 'Anrede', 'Quelle' ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		$language = ContactPanda_Store::salutation_language( isset( $_GET['language'] ) ? wp_unslash( $_GET['language'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed export filter.
		foreach ( ContactPanda_Store::salutations( $language ) as $row ) fputcsv( $out, array( $row['first_name'], $row['salutation'], $row['source'] ), ';' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv
		fclose( $out ); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	private static function render_registration_settings() {
		$settings = self::settings();
		if ( ! self::customer_registration_enabled() ) {
			self::render_newsletter_registration_settings( $settings );
			return;
		}
		$roles = self::allowed_roles();
		$section = isset( $_GET['registration_section'] ) ? sanitize_key( wp_unslash( $_GET['registration_section'] ) ) : 'customers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing only.
		if ( ! in_array( $section, array( 'customers', 'business', 'newsletter' ), true ) ) $section = 'customers';
		if ( isset( $_GET['contactpanda_role_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The requested role is no longer enabled and was not assigned. Please update the allowed roles before approving this registration.', 'contactpanda' ) . '</p></div>';
		}
		if ( isset( $_GET['contactpanda_email_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result flag.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The requested role was not assigned because the email address has not yet been confirmed.', 'contactpanda' ) . '</p></div>';
		}
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs">';
		$registration_sections = array( 'customers' => __( 'General customer registration', 'contactpanda' ), 'business' => __( 'Business customers', 'contactpanda' ), 'newsletter' => __( 'Newsletter signup', 'contactpanda' ) );
		foreach ( $registration_sections as $key => $label ) echo '<a class="nav-tab ' . ( $section === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=registration&registration_section=' . $key ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</nav><form method="post" action="options.php"><section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Customer account registration', 'contactpanda' ) . '</h2>';
		settings_fields( 'contactpanda_registration' );
		echo '<div class="contactpanda-registration-panel"' . ( 'customers' === $section ? '' : ' hidden' ) . '>';
		self::checkbox_setting( 'enabled', __( 'Enable standard customer registration', 'contactpanda' ), __( 'Makes the standard customer account form available through [contactpanda_registration].', 'contactpanda' ), $settings );
		self::checkbox_setting( 'require_email_verify', __( 'Require email confirmation', 'contactpanda' ), __( 'The account remains blocked until the email address has been confirmed.', 'contactpanda' ), $settings );
		echo '<input type="hidden" name="' . esc_attr( self::OPTION ) . '[allow_marketing]" value="1"><p class="description">' . esc_html__( 'Every account registration offers an optional, unchecked marketing consent. Account registration never implies marketing consent.', 'contactpanda' ) . '</p>';
		self::checkbox_setting( 'replace_registration', __( 'Replace the standard WordPress registration', 'contactpanda' ), __( 'Redirects the public WordPress registration to the selected ContactPanda registration page. Administrative login remains available.', 'contactpanda' ), $settings );
		self::page_setting( 'registration_page_id', __( 'Registration page', 'contactpanda' ), $settings );
		if ( ! $settings['registration_page_id'] ) echo '<div class="contactpanda-setting-row"><strong>' . esc_html__( 'Built-in registration page', 'contactpanda' ) . '</strong><p><a class="button" href="' . esc_url( self::virtual_registration_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'View registration page', 'contactpanda' ) . '</a></p><p class="description">' . esc_html__( 'ContactPanda uses a theme-compatible built-in page while no WordPress page is selected.', 'contactpanda' ) . '</p></div>';
		self::page_setting( 'terms_page_id', __( 'Terms and conditions page', 'contactpanda' ), $settings );
		self::page_setting( 'success_page_id', __( 'Page after successful submission', 'contactpanda' ), $settings );
		self::textarea_setting( 'customer_submission_message', __( 'Text after standard customer registration', 'contactpanda' ), __( 'This text is displayed after a standard customer account registration.', 'contactpanda' ), $settings );
		echo '</div><div class="contactpanda-registration-panel"' . ( 'business' === $section ? '' : ' hidden' ) . '>';
		self::checkbox_setting( 'business_enabled', __( 'Offer business customer registration', 'contactpanda' ), __( 'Adds a separate business registration with company data, VAT ID validation, and a requested customer role.', 'contactpanda' ), $settings );
		self::checkbox_setting( 'require_vat', __( 'Require VAT ID', 'contactpanda' ), __( 'A VAT ID is optional unless this setting is enabled. Every entered VAT ID must be successfully validated through EU VIES.', 'contactpanda' ), $settings );
		echo '<div class="contactpanda-setting-row"><strong>' . esc_html__( 'Roles customers may request', 'contactpanda' ) . '</strong><div class="contactpanda-role-options">';
		foreach ( $roles as $role => $label ) {
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[allowed_roles][]" value="' . esc_attr( $role ) . '"' . checked( in_array( $role, (array) $settings['allowed_roles'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div><p class="description">' . esc_html__( 'The customer only requests a role. It is assigned exclusively after an administrator approves the registration. Privileged administrative roles are excluded.', 'contactpanda' ) . '</p></div>';

		self::textarea_setting( 'submission_message', __( 'Text after business customer registration', 'contactpanda' ), __( 'This text is displayed after a business customer registration.', 'contactpanda' ), $settings );
		echo '</div><div class="contactpanda-registration-panel"' . ( 'newsletter' === $section ? '' : ' hidden' ) . '>';
		self::checkbox_setting( 'newsletter_enabled', __( 'Enable separate newsletter signup', 'contactpanda' ), __( 'Provides [contactpanda_newsletter_signup]. The form creates or updates a ContactPanda contact but never creates a WordPress user account.', 'contactpanda' ), $settings );
		self::render_newsletter_form_settings( $settings );
		self::text_setting( 'newsletter_subject', __( 'Newsletter confirmation subject', 'contactpanda' ), $settings );
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-newsletter-message"><strong>' . esc_html__( 'Newsletter confirmation email', 'contactpanda' ) . '</strong></label>';
		wp_editor( $settings['newsletter_message'], 'contactpanda-newsletter-message', array( 'textarea_name' => self::OPTION . '[newsletter_message]', 'textarea_rows' => 8, 'media_buttons' => false, 'quicktags' => true ) );
		echo '<p class="description">' . esc_html__( 'Available placeholders: {first_name}, {site_name}, {confirmation_link}.', 'contactpanda' ) . '</p></div>';
		self::textarea_setting( 'newsletter_success', __( 'Text after newsletter signup', 'contactpanda' ), __( 'Shown after the form has been submitted.', 'contactpanda' ), $settings );
		self::textarea_setting( 'newsletter_verified', __( 'Text after newsletter confirmation', 'contactpanda' ), __( 'Shown after the confirmation link has been opened.', 'contactpanda' ), $settings );
		echo '</div>';
		self::page_setting( 'privacy_page_id', __( 'Privacy policy page', 'contactpanda' ), $settings );
		echo '<div class="contactpanda-registration-common"' . ( 'newsletter' === $section ? ' hidden' : '' ) . '>';
		echo '<h3 class="contactpanda-settings-subheading">' . esc_html__( 'Double opt-in email', 'contactpanda' ) . '</h3>';
		self::text_setting( 'confirmation_subject', __( 'Email subject', 'contactpanda' ), $settings );
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-confirmation-message"><strong>' . esc_html__( 'Email text', 'contactpanda' ) . '</strong></label>';
		wp_editor(
			$settings['confirmation_message'],
			'contactpanda-confirmation-message',
			array(
				'textarea_name' => self::OPTION . '[confirmation_message]',
				'textarea_rows' => 10,
				'media_buttons' => false,
				'quicktags'     => true,
			)
		);
		echo '<p class="description">' . esc_html__( 'Available placeholders: {first_name}, {site_name}, {confirmation_link}. If the confirmation link is omitted, it is appended automatically.', 'contactpanda' ) . '</p></div>';
		echo '<h3 class="contactpanda-settings-subheading">' . esc_html__( 'After double opt-in confirmation', 'contactpanda' ) . '</h3>';
		self::textarea_setting( 'customer_verified_message', __( 'Confirmation text for standard customers', 'contactpanda' ), __( 'This text is displayed after a standard customer confirms the email address.', 'contactpanda' ), $settings );
		self::textarea_setting( 'verified_message', __( 'Confirmation text for business customers', 'contactpanda' ), __( 'This text is displayed after a business customer confirms the email address.', 'contactpanda' ), $settings );
		self::page_setting( 'verified_page_id', __( 'Redirect page after confirmation', 'contactpanda' ), $settings );
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-verified-delay"><strong>' . esc_html__( 'Redirect delay in seconds', 'contactpanda' ) . '</strong></label><input id="contactpanda-verified-delay" class="small-text" type="number" min="0" max="30" name="' . esc_attr( self::OPTION ) . '[verified_delay]" value="' . esc_attr( $settings['verified_delay'] ) . '"><p class="description">' . esc_html__( 'Use 0 for an immediate redirect or up to 30 seconds.', 'contactpanda' ) . '</p></div>';
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-admin-email"><strong>' . esc_html__( 'Notification email address', 'contactpanda' ) . '</strong></label><input id="contactpanda-admin-email" class="regular-text" type="email" name="' . esc_attr( self::OPTION ) . '[admin_email]" value="' . esc_attr( $settings['admin_email'] ) . '"></div>';
		echo '</div>';
		submit_button();
		echo '</section></form>';

		self::render_integration_status();
		self::render_pending_registrations();
	}

	private static function render_newsletter_registration_settings( $settings ) {
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs"><span class="nav-tab nav-tab-active">' . esc_html__( 'Newsletter signup', 'contactpanda' ) . '</span></nav>';
		echo '<form method="post" action="options.php"><section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Newsletter signup', 'contactpanda' ) . '</h2>';
		settings_fields( 'contactpanda_registration' );
		self::checkbox_setting( 'newsletter_enabled', __( 'Enable separate newsletter signup', 'contactpanda' ), __( 'Provides [contactpanda_newsletter_signup]. The form creates or updates a ContactPanda contact but never creates a WordPress user account.', 'contactpanda' ), $settings );
		self::page_setting( 'privacy_page_id', __( 'Privacy policy page', 'contactpanda' ), $settings );
		self::render_newsletter_form_settings( $settings );
		self::text_setting( 'newsletter_subject', __( 'Newsletter confirmation subject', 'contactpanda' ), $settings );
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-newsletter-message"><strong>' . esc_html__( 'Newsletter confirmation email', 'contactpanda' ) . '</strong></label>';
		wp_editor( $settings['newsletter_message'], 'contactpanda-newsletter-message', array( 'textarea_name' => self::OPTION . '[newsletter_message]', 'textarea_rows' => 8, 'media_buttons' => false, 'quicktags' => true ) );
		echo '<p class="description">' . esc_html__( 'Available placeholders: {first_name}, {site_name}, {confirmation_link}.', 'contactpanda' ) . '</p></div>';
		self::textarea_setting( 'newsletter_success', __( 'Text after newsletter signup', 'contactpanda' ), __( 'Shown after the form has been submitted.', 'contactpanda' ), $settings );
		self::textarea_setting( 'newsletter_verified', __( 'Text after newsletter confirmation', 'contactpanda' ), __( 'Shown after the confirmation link has been opened.', 'contactpanda' ), $settings );
		submit_button();
		echo '</section></form>';
		self::render_integration_status();
	}

	private static function render_newsletter_form_settings( $settings ) {
		echo '<h3 class="contactpanda-settings-subheading">' . esc_html__( 'Standard newsletter form', 'contactpanda' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'The compact form contains salutation, first name, last name, email address, privacy confirmation, and newsletter consent. ContactPanda Plus can replace it with a reusable full form.', 'contactpanda' ) . '</p>';
		self::text_setting( 'newsletter_privacy_text', __( 'Privacy confirmation text', 'contactpanda' ), $settings );
		self::textarea_setting( 'newsletter_consent_text', __( 'Newsletter consent text', 'contactpanda' ), __( 'This checkbox is mandatory and records the declared purpose of consent.', 'contactpanda' ), $settings );
		self::text_setting( 'newsletter_submit_label', __( 'Submit button label', 'contactpanda' ), $settings );
		self::textarea_setting( 'newsletter_error', __( 'Validation error text', 'contactpanda' ), __( 'Shown when required information or consent is missing.', 'contactpanda' ), $settings );
		self::textarea_setting( 'newsletter_already_registered', __( 'Text for existing subscribers', 'contactpanda' ), __( 'Shown instead of sending another double opt-in email when valid newsletter consent already exists.', 'contactpanda' ), $settings );
		do_action( 'contactpanda_newsletter_signup_form_settings', $settings );
	}

	private static function render_general_settings() {
		$settings = self::general_settings();
		$notice = get_transient( 'contactpanda_database_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contactpanda_database_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		self::render_theme_settings( $settings );
		self::render_automatic_updates();
		echo '<form method="post" action="options.php">';
		settings_fields( 'contactpanda_general' );
		echo '<section class="contactpanda-card contactpanda-settings-card contactpanda-danger-card"><h2>' . esc_html__( 'Uninstallation and data', 'contactpanda' ) . '</h2><p><strong>' . esc_html__( 'By default, uninstalling ContactPanda does not delete customer data.', 'contactpanda' ) . '</strong></p>';
		self::render_preserved_integrations();
		echo '<div class="contactpanda-setting-row"><input type="hidden" name="' . esc_attr( self::GENERAL_OPTION ) . '[delete_on_uninstall]" value="0"><label><input type="checkbox" data-contactpanda-delete-on-uninstall name="' . esc_attr( self::GENERAL_OPTION ) . '[delete_on_uninstall]" value="1"' . checked( ! empty( $settings['delete_on_uninstall'] ), true, false ) . '> <strong>' . esc_html__( 'Permanently delete all ContactPanda-owned customer data when the plugin is uninstalled.', 'contactpanda' ) . '</strong></label><p class="description">' . esc_html__( 'This affects only ContactPanda cp_ tables, ContactPanda options, and ContactPanda user metadata. Data belonging to other plugins is never deleted.', 'contactpanda' ) . '</p></div>';
		submit_button( __( 'Save general settings', 'contactpanda' ) );
		echo '</section></form>';
	}

	private static function render_customer_portal_settings() {
		$settings = self::general_settings();
		echo '<form method="post" action="options.php">';
		settings_fields( 'contactpanda_general' );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Customer portal', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Choose the WordPress page that contains the ContactPanda customer portal block or shortcode. The portal works without WooCommerce.', 'contactpanda' ) . '</p><label class="contactpanda-setting-row"><span><strong>' . esc_html__( 'Portal page', 'contactpanda' ) . '</strong></span>';
		wp_dropdown_pages( array( 'name' => self::GENERAL_OPTION . '[portal_page_id]', 'selected' => absint( $settings['portal_page_id'] ?? 0 ), 'show_option_none' => __( 'Select a page', 'contactpanda' ), 'option_none_value' => 0 ) );
		echo '</label><p class="description"><code>[contactpanda_customer_portal]</code></p>';
		submit_button( __( 'Save portal settings', 'contactpanda' ) );
		echo '</section></form>';
	}

	private static function render_data_source_settings() {
		$settings = self::general_settings();
		$groundhogg = self::groundhogg_active();
		$schema = ContactPanda_Schema::status();
		echo '<form method="post" action="options.php">'; settings_fields( 'contactpanda_general' );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Leading data source', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Only one system may be the leading source for contacts, companies, tags, and consent data.', 'contactpanda' ) . '</p><div class="contactpanda-source-options">';
		$standalone_ready = ContactPanda_Store::ready();
		echo '<label class="contactpanda-source-option' . ( $standalone_ready ? '' : ' is-disabled' ) . '"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[data_source]" value="standalone"' . checked( 'standalone', $settings['data_source'], false ) . ( $standalone_ready ? '' : ' disabled' ) . '><span><strong>' . esc_html__( 'ContactPanda database', 'contactpanda' ) . '</strong><small>' . esc_html__( 'ContactPanda stores independent changes in its own database. Groundhogg remains unchanged. A later reverse migration to Groundhogg is not provided.', 'contactpanda' ) . '</small></span></label>';
		if ( $groundhogg ) echo '<label class="contactpanda-source-option"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[data_source]" value="groundhogg"' . checked( 'groundhogg', $settings['data_source'], false ) . '><span><strong>' . esc_html__( 'Groundhogg frontend mode', 'contactpanda' ) . '</strong><small>' . esc_html__( 'Groundhogg remains the leading system. Changes made through ContactPanda are written directly to Groundhogg.', 'contactpanda' ) . '</small></span></label>';
		elseif ( 'groundhogg' === $settings['data_source'] ) echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Groundhogg is configured as the data source but is currently inactive. ContactPanda has not switched the data source automatically.', 'contactpanda' ) . '</p></div>';
		echo '</div><p class="description">' . esc_html( sprintf( __( '%1$d of %2$d ContactPanda database tables are present. Schema version: %3$s.', 'contactpanda' ), $schema['count'], $schema['total'], $schema['version'] ?: __( 'unknown', 'contactpanda' ) ) ) . '</p>';
		submit_button( __( 'Save data source', 'contactpanda' ) ); echo '</section></form>';
	}

	/**
	 * Return source systems that are actually installed on this site.
	 *
	 * Extensions may add adapters through contactpanda_migration_sources. A
	 * source definition needs a label and may provide an active state and a
	 * short description. Only installed sources become navigation tabs.
	 */
	public static function migration_sources() {
		$sources = array(
			'wordpress' => array(
				'label'       => 'WordPress',
				'active'      => true,
				'description' => __( 'WordPress users are matched by email address, linked to their ContactPanda person, and missing profile values can be completed without changing WordPress.', 'contactpanda' ),
			),
		);
		if ( self::plugin_installed( array( 'groundhogg' ) ) || self::groundhogg_active() ) {
			$sources['groundhogg'] = array(
				'label'       => 'Groundhogg',
				'active'      => self::groundhogg_active(),
				'description' => __( 'Contacts, companies, tags, email templates, completed broadcasts, and automation drafts can be analyzed and migrated without changing Groundhogg.', 'contactpanda' ),
			);
		}
		if ( self::fluentcrm_installed() ) {
			$sources['fluentcrm'] = array(
				'label'       => 'FluentCRM',
				'active'      => defined( 'FLUENTCRM' ) || defined( 'FLUENTCRM_PLUGIN_VERSION' ) || class_exists( '\\FluentCrm\\App\\App' ),
				'description' => __( 'Contacts and their marketing state can be migrated now. Additional FluentCRM content can be supplied by an installed ContactPanda extension.', 'contactpanda' ),
			);
		}
		if ( self::newsletter_plugin_installed() ) {
			$sources['newsletter_plugin'] = array(
				'label'       => __( 'The Newsletter Plugin', 'contactpanda' ),
				'active'      => defined( 'NEWSLETTER_VERSION' ) || class_exists( 'Newsletter' ),
				'description' => __( 'Subscribers are migrated by email address. ContactPanda Plus can additionally preserve historical mailings and available statistics as read-only archives.', 'contactpanda' ),
			);
		}
		if ( self::plugin_installed( array( 'woocommerce', 'woo commerce' ) ) || class_exists( 'WooCommerce' ) ) {
			$sources['woocommerce'] = array(
				'label'       => 'WooCommerce',
				'active'      => class_exists( 'WooCommerce' ),
				'description' => __( 'Customer profiles can be merged into ContactPanda. Orders, invoices, prices, and all WooCommerce source records remain unchanged.', 'contactpanda' ),
			);
		}
		return (array) apply_filters( 'contactpanda_migration_sources', $sources );
	}

	private static function plugin_installed( $needles ) {
		if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( (array) get_plugins() as $file => $plugin ) {
			$haystack = strtolower( (string) $file . ' ' . ( $plugin['Name'] ?? '' ) . ' ' . ( $plugin['TextDomain'] ?? '' ) );
			foreach ( (array) $needles as $needle ) if ( false !== strpos( $haystack, strtolower( (string) $needle ) ) ) return true;
		}
		return false;
	}

	public static function render_migration_settings( $source = 'overview' ) {
		$schema = ContactPanda_Schema::status();
		$notice = get_transient( 'contactpanda_database_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contactpanda_database_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		$source = sanitize_key( $source );
		if ( 'overview' === $source ) {
			echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Migration center', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Imports are started only after an explicit confirmation. Source systems remain unchanged, and existing ContactPanda contacts are matched by stable source identifiers and email addresses before new records are created.', 'contactpanda' ) . '</p><p>' . esc_html__( 'Select an installed source system above. Each source has its own preview, migration report, and repeatable import process while using the same protected ContactPanda migration foundation.', 'contactpanda' ) . '</p></section>';
			self::render_database_tools( $schema, false, true );
			return;
		}
		if ( 'wordpress' === $source ) {
			self::render_source_intro( self::migration_sources()['wordpress'] ?? array() );
			self::render_wordpress_user_migration();
		} elseif ( 'groundhogg' === $source ) {
			self::render_source_intro( self::migration_sources()['groundhogg'] ?? array() );
			self::render_database_tools( $schema, true, false );
		} elseif ( 'fluentcrm' === $source ) {
			self::render_source_intro( self::migration_sources()['fluentcrm'] ?? array() );
			self::render_fluentcrm_migration();
		} elseif ( 'newsletter_plugin' === $source ) {
			self::render_source_intro( self::migration_sources()['newsletter_plugin'] ?? array() );
			self::render_newsletter_plugin_migration();
		} elseif ( 'woocommerce' === $source ) {
			self::render_source_intro( self::migration_sources()['woocommerce'] ?? array() );
			self::render_woocommerce_import();
		}
		do_action( 'contactpanda_render_migration_source_' . $source, $source );
		do_action( 'contactpanda_render_migration_tools', $source );
	}

	private static function render_wordpress_user_migration() {
		if ( ! ContactPanda_Store::standalone() ) {
			echo '<section class="contactpanda-card contactpanda-settings-card"><p>' . esc_html__( 'WordPress user matching is available when the ContactPanda database is the leading data source.', 'contactpanda' ) . '</p></section>';
			return;
		}
		$preview = (array) get_transient( 'contactpanda_wordpress_user_preview_' . get_current_user_id() );
		$report  = (array) get_option( 'contactpanda_last_wordpress_user_migration_report', array() );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Match WordPress users', 'contactpanda' ) . '</h2><p>' . esc_html__( 'All WordPress users with a valid email address are matched to ContactPanda people. Existing values are preserved; only missing names, addresses, and telephone numbers are completed. Ambiguous links are skipped and reported.', 'contactpanda' ) . '</p>';
		if ( $preview ) echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Import preview', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d users found: %2$d new people, %3$d existing people to link, and %4$d already linked. No data has been changed yet.', 'contactpanda' ), absint( $preview['found'] ?? 0 ), absint( $preview['new'] ?? 0 ), absint( $preview['updated'] ?? 0 ), absint( $preview['linked'] ?? 0 ) ) ) . '</p></div>';
		if ( $report ) echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Last import', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d new, %2$d updated, %3$d linked, %4$d skipped, %5$d conflicts, %6$d errors. Completed: %7$s', 'contactpanda' ), absint( $report['new'] ?? 0 ), absint( $report['updated'] ?? 0 ), absint( $report['linked'] ?? 0 ), absint( $report['skipped'] ?? 0 ), absint( $report['conflicts'] ?? 0 ), absint( $report['errors'] ?? 0 ), sanitize_text_field( $report['completed_at'] ?? '' ) ) ) . '</p></div>';
		echo '<div class="contactpanda-database-actions">';
		self::wordpress_user_import_form( 'preview', __( 'Preview matching', 'contactpanda' ), 'button-secondary' );
		if ( $preview ) self::wordpress_user_import_form( 'run', __( 'Import, complete, and link now', 'contactpanda' ), 'button-primary' );
		echo '</div></section>';
	}

	private static function wordpress_user_import_form( $mode, $label, $class ) {
		echo '<form class="contactpanda-inline-action-form contactpanda-migration-progress-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_migrate_wordpress_users"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="migration_source" value="wordpress">';
		wp_nonce_field( 'contactpanda_migrate_wordpress_users' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function handle_migrate_wordpress_users() {
		self::guard_database_action( 'contactpanda_migrate_wordpress_users' );
		if ( ! ContactPanda_Store::standalone() ) self::database_redirect( 'error', __( 'WordPress user matching requires the ContactPanda database as leading source.', 'contactpanda' ) );
		$preview_only = 'preview' === sanitize_key( wp_unslash( $_POST['mode'] ?? 'preview' ) );
		$report = array( 'found' => 0, 'new' => 0, 'updated' => 0, 'linked' => 0, 'skipped' => 0, 'conflicts' => 0, 'errors' => 0, 'completed_at' => current_time( 'mysql' ) );
		foreach ( get_users( array( 'fields' => 'all_with_meta' ) ) as $user ) {
			++$report['found'];
			$email = sanitize_email( $user->user_email );
			if ( ! is_email( $email ) ) { ++$report['skipped']; continue; }
			$by_user = ContactPanda_Store::contact_by_user_id( $user->ID );
			$by_email = ContactPanda_Store::contact_by_email( $email );
			if ( $by_user && $by_email && absint( $by_user['id'] ) !== absint( $by_email['id'] ) ) { ++$report['conflicts']; continue; }
			$contact = $by_user ?: $by_email;
			$data = array(
				'email' => $email,
				'first_name' => sanitize_text_field( get_user_meta( $user->ID, 'first_name', true ) ?: get_user_meta( $user->ID, 'billing_first_name', true ) ),
				'last_name' => sanitize_text_field( get_user_meta( $user->ID, 'last_name', true ) ?: get_user_meta( $user->ID, 'billing_last_name', true ) ),
				'source' => 'wordpress',
				'user_id' => $user->ID,
				'primary_phone' => sanitize_text_field( get_user_meta( $user->ID, 'billing_phone', true ) ),
				'meta' => array( 'wordpress_user_id' => $user->ID ),
			);
			if ( ! $contact ) {
				++$report['new'];
				if ( $preview_only ) continue;
				$id = ContactPanda_Store::create_contact( $data );
				if ( is_wp_error( $id ) || ! $id ) { ++$report['errors']; continue; }
				ContactPanda_Store::link_user( $id, $user->ID );
				self::complete_wordpress_address( $id, $user->ID );
				continue;
			}
			$already_linked = ! empty( $by_user );
			$already_linked ? ++$report['linked'] : ++$report['updated'];
			if ( $preview_only ) continue;
			$changes = array( 'meta' => $data['meta'] );
			if ( ! $already_linked ) $changes['user_id'] = $user->ID;
			foreach ( array( 'first_name', 'last_name' ) as $field ) if ( empty( $contact[ $field ] ) && ! empty( $data[ $field ] ) ) $changes[ $field ] = $data[ $field ];
			if ( ! empty( $data['primary_phone'] ) && empty( $contact['meta']['primary_phone'] ) ) $changes['primary_phone'] = $data['primary_phone'];
			ContactPanda_Store::update_contact( absint( $contact['id'] ), $changes );
			if ( ! $already_linked ) ContactPanda_Store::link_user( absint( $contact['id'] ), $user->ID );
			self::complete_wordpress_address( absint( $contact['id'] ), $user->ID );
		}
		if ( $preview_only ) {
			set_transient( 'contactpanda_wordpress_user_preview_' . get_current_user_id(), $report, 30 * MINUTE_IN_SECONDS );
			self::database_redirect( 'success', __( 'The WordPress user preview is ready.', 'contactpanda' ) );
		}
		delete_transient( 'contactpanda_wordpress_user_preview_' . get_current_user_id() );
		update_option( 'contactpanda_last_wordpress_user_migration_report', $report, false );
		self::database_redirect( 'success', sprintf( __( 'WordPress user matching completed at %1$s: %2$d new, %3$d updated, %4$d already linked, %5$d conflicts.', 'contactpanda' ), $report['completed_at'], $report['new'], $report['updated'], $report['linked'], $report['conflicts'] ) );
	}

	private static function complete_wordpress_address( $contact_id, $user_id ) {
		$contact = ContactPanda_Store::contact( $contact_id );
		$current = is_array( $contact ) && isset( $contact['meta'] ) && is_array( $contact['meta'] ) ? $contact['meta'] : array();
		$address = array();
		foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
			$existing = sanitize_text_field( $current[ $field ] ?? '' );
			$incoming = sanitize_text_field( get_user_meta( $user_id, 'billing_' . $field, true ) );
			$address[ $field ] = '' !== trim( $existing ) ? $existing : $incoming;
		}
		if ( array_filter( $address ) ) ContactPanda_Store::update_contact_address( $contact_id, $address );
	}

	private static function render_source_intro( $source ) {
		if ( empty( $source ) ) return;
		echo '<section class="contactpanda-card contactpanda-settings-card contactpanda-migration-source-intro"><div class="contactpanda-migration-source-heading"><div><h2>' . esc_html( $source['label'] ?? '' ) . '</h2><p>' . esc_html( $source['description'] ?? '' ) . '</p></div><span class="contactpanda-status-badge ' . ( ! empty( $source['active'] ) ? 'is-positive' : 'is-negative' ) . '">' . ( ! empty( $source['active'] ) ? esc_html__( 'Active', 'contactpanda' ) : esc_html__( 'Installed, inactive', 'contactpanda' ) ) . '</span></div></section>';
	}

	private static function render_woocommerce_import() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			echo '<section class="contactpanda-card contactpanda-settings-card"><p>' . esc_html__( 'WooCommerce is installed but inactive. Activate it before starting this migration.', 'contactpanda' ) . '</p></section>';
			return;
		}
		if ( ! ContactPanda_Store::standalone() ) {
			echo '<section class="contactpanda-card contactpanda-settings-card"><p>' . esc_html__( 'WooCommerce customer migration is available after the ContactPanda database has been selected as the leading data source.', 'contactpanda' ) . '</p></section>';
			return;
		}
		$preview = get_transient( 'contactpanda_woocommerce_import_' . get_current_user_id() );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Import WooCommerce customers', 'contactpanda' ) . '</h2><p>' . esc_html__( 'ContactPanda can merge customer profiles from WooCommerce into the ContactPanda database using the email address as the unique key. Orders, invoices, prices, and WooCommerce data remain unchanged.', 'contactpanda' ) . '</p>';
		if ( $preview ) {
			echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Pre-import information', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d unique customers found: %2$d new and %3$d already present. This information has not changed any data.', 'contactpanda' ), absint( $preview['found'] ?? 0 ), absint( $preview['new'] ?? 0 ), absint( $preview['updated'] ?? 0 ) ) ) . '</p></div>';
		}
		echo '<div class="contactpanda-database-actions">';
		self::woocommerce_import_form( 'preview', __( 'Show pre-import information', 'contactpanda' ), 'button-secondary' );
		if ( $preview ) self::woocommerce_import_form( 'run', __( 'Import and merge now', 'contactpanda' ), 'button-primary' );
		echo '</div></section>';
	}

	private static function newsletter_plugin_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'newsletter';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table ? $table : '';
	}

	public static function newsletter_plugin_installed() {
		if ( defined( 'NEWSLETTER_VERSION' ) || class_exists( 'Newsletter' ) ) return true;
		if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( (array) get_plugins() as $file => $plugin ) {
			if ( 0 === strpos( $file, 'newsletter/' ) || 'newsletter' === sanitize_key( $plugin['TextDomain'] ?? '' ) ) return true;
		}
		return false;
	}

	public static function fluentcrm_installed() {
		return defined( 'FLUENTCRM' ) || defined( 'FLUENTCRM_PLUGIN_VERSION' ) || class_exists( '\\FluentCrm\\App\\App' ) || self::plugin_installed( array( 'fluent-crm', 'fluentcrm', 'fluent crm' ) );
	}

	private static function fluentcrm_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'fc_subscribers';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		return $found === $table ? $table : '';
	}

	private static function render_fluentcrm_migration() {
		$table = self::fluentcrm_table();
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Import FluentCRM data', 'contactpanda' ) . '</h2>';
		if ( ! ContactPanda_Store::ready() ) {
			echo '<p>' . esc_html__( 'Create the ContactPanda database before importing FluentCRM contacts.', 'contactpanda' ) . '</p></section>';
			return;
		}
		if ( ! $table ) {
			echo '<p>' . esc_html__( 'No compatible FluentCRM subscriber table was detected. Nothing will be changed.', 'contactpanda' ) . '</p></section>';
			return;
		}
		$preview = (array) get_transient( 'contactpanda_fluentcrm_preview_' . get_current_user_id() );
		$report = (array) get_option( 'contactpanda_last_fluentcrm_migration_report', array() );
		echo '<p>' . esc_html__( 'Contacts are matched first by their FluentCRM source ID and then by email address. Existing unsubscribe, bounce, and complaint states always take precedence. The FluentCRM database remains unchanged.', 'contactpanda' ) . '</p>';
		if ( $preview ) echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Import preview', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d contacts found: %2$d new, %3$d already present, and %4$d without a usable email address. No data has been changed yet.', 'contactpanda' ), absint( $preview['found'] ?? 0 ), absint( $preview['new'] ?? 0 ), absint( $preview['updated'] ?? 0 ), absint( $preview['skipped'] ?? 0 ) ) ) . '</p></div>';
		if ( $report ) echo '<p><strong>' . esc_html__( 'Last import:', 'contactpanda' ) . '</strong> ' . esc_html( sprintf( __( '%1$d new, %2$d updated, %3$d skipped, %4$d errors. Completed: %5$s', 'contactpanda' ), absint( $report['new'] ?? 0 ), absint( $report['updated'] ?? 0 ), absint( $report['skipped'] ?? 0 ), absint( $report['errors'] ?? 0 ), sanitize_text_field( $report['completed_at'] ?? '' ) ) ) . '</p>';
		echo '<div class="contactpanda-database-actions">';
		self::fluentcrm_import_form( 'preview', __( 'Preview import', 'contactpanda' ), 'button-secondary' );
		if ( $preview ) self::fluentcrm_import_form( 'run', __( 'Import and merge now', 'contactpanda' ), 'button-primary' );
		echo '<p class="description">' . esc_html__( 'Additional email templates, campaigns, and automations are imported only by compatible ContactPanda extensions and are never activated silently.', 'contactpanda' ) . '</p></div></section>';
	}

	private static function fluentcrm_import_form( $mode, $label, $class ) {
		echo '<form class="contactpanda-inline-action-form contactpanda-migration-progress-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_migrate_fluentcrm"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="migration_source" value="fluentcrm">';
		wp_nonce_field( 'contactpanda_migrate_fluentcrm' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function render_newsletter_plugin_migration() {
		if ( ! self::newsletter_plugin_installed() || ! apply_filters( 'contactpanda_can_migrate_newsletter_plugin', true ) ) return;
		$table = self::newsletter_plugin_table();
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Import The Newsletter Plugin data', 'contactpanda' ) . '</h2>';
		if ( ! defined( 'NEWSLETTER_VERSION' ) && ! class_exists( 'Newsletter' ) ) echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The Newsletter Plugin is installed but inactive. Existing source tables can still be read without changing them.', 'contactpanda' ) . '</p></div>';
		if ( ! ContactPanda_Store::ready() ) {
			echo '<p>' . esc_html__( 'Create the ContactPanda database before importing newsletter subscribers.', 'contactpanda' ) . '</p></section>';
			return;
		}
		if ( ! $table ) {
			echo '<p>' . esc_html__( 'No compatible The Newsletter Plugin subscriber table was detected. Nothing will be changed.', 'contactpanda' ) . '</p></section>';
			return;
		}
		$report = (array) get_option( 'contactpanda_last_newsletter_plugin_migration_report', array() );
		$preview = (array) get_transient( 'contactpanda_newsletter_plugin_preview_' . get_current_user_id() );
		echo '<p>' . esc_html__( 'Subscribers are matched by email address. Historical mailings and available statistics are imported as read-only archives by ContactPanda Plus. Unsubscribed and bounced states take precedence and are never changed into a marketing consent by the import.', 'contactpanda' ) . '</p>';
		if ( $preview ) echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Import preview', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d subscribers, %2$d reusable email sources, and %3$d historical mailings were found. No data has been changed yet.', 'contactpanda' ), absint( $preview['subscribers'] ?? 0 ), absint( $preview['templates'] ?? 0 ), absint( $preview['mailings'] ?? 0 ) ) ) . '</p></div>';
		if ( $report ) {
			echo '<p><strong>' . esc_html__( 'Last import:', 'contactpanda' ) . '</strong> ' . esc_html( sprintf( __( '%1$d new, %2$d updated, %3$d templates, %4$d mailings, %5$d skipped, %6$d errors. Completed: %7$s', 'contactpanda' ), absint( $report['new'] ?? 0 ), absint( $report['updated'] ?? 0 ), absint( $report['templates'] ?? 0 ), absint( $report['mailings'] ?? 0 ), absint( $report['skipped'] ?? 0 ), absint( $report['errors'] ?? 0 ), sanitize_text_field( $report['completed_at'] ?? '' ) ) ) . '</p>';
			if ( isset( $report['mailing_recipients'] ) ) echo '<p>' . esc_html( sprintf( __( '%d historical recipient assignments were imported and linked to matching people.', 'contactpanda' ), absint( $report['mailing_recipients'] ) ) ) . '</p>';
			if ( isset( $report['editable_blocks'] ) ) echo '<p>' . esc_html( sprintf( __( '%1$d editable content blocks were created; %2$d source fragments required an HTML fallback.', 'contactpanda' ), absint( $report['editable_blocks'] ), absint( $report['html_fallbacks'] ?? 0 ) ) ) . '</p>';
		}
		echo '<div class="contactpanda-database-actions">';
		self::newsletter_import_form( 'preview', __( 'Preview import', 'contactpanda' ), 'button-secondary' );
		if ( $preview ) self::newsletter_import_form( 'run', __( 'Import and merge now', 'contactpanda' ), 'button-primary' );
		echo '<p class="description">' . esc_html__( 'The original newsletter plugin data remains unchanged.', 'contactpanda' ) . '</p></div>';
		echo '<div class="contactpanda-migration-progress" hidden aria-live="polite"><strong>' . esc_html__( 'Preparing migration…', 'contactpanda' ) . '</strong><progress value="0" max="100"></progress><span>0%</span><small></small></div>';
		echo '</section>';
	}

	private static function newsletter_import_form( $mode, $label, $class ) {
		$live_class = 'run' === $mode ? ' contactpanda-live-migration-form' : '';
		echo '<form class="contactpanda-inline-action-form contactpanda-migration-progress-form' . esc_attr( $live_class ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_migrate_newsletter_plugin"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="migration_source" value="newsletter_plugin">';
		wp_nonce_field( 'contactpanda_migrate_newsletter_plugin' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function handle_migrate_newsletter_plugin() {
		self::guard_database_action( 'contactpanda_migrate_newsletter_plugin' );
		delete_transient( 'contactpanda_groundhogg_migration_' . get_current_user_id() );
		self::store_migration_progress( array( 'stage' => __( 'Preparing migration…', 'contactpanda' ), 'processed' => 0, 'total' => 0, 'percent' => 0 ) );
		if ( ! apply_filters( 'contactpanda_can_migrate_newsletter_plugin', true ) ) wp_die( esc_html__( 'The Newsletter Plugin migration is not available.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$table = self::newsletter_plugin_table();
		if ( ! $table || ! ContactPanda_Store::ready() ) {
			self::database_redirect( 'error', __( 'The newsletter subscriber source or the ContactPanda database is not available.', 'contactpanda' ) );
		}
		global $wpdb;
		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- verified WordPress-prefixed plugin table.
		$wanted = array_values( array_intersect( array( 'id', 'email', 'name', 'surname', 'status', 'created', 'token' ), $columns ) );
		if ( ! in_array( 'email', $wanted, true ) ) {
			self::database_redirect( 'error', __( 'The detected newsletter table does not contain a compatible email field.', 'contactpanda' ) );
		}
		$quoted = '`' . implode( '`,`', $wanted ) . '`';
		$rows = (array) $wpdb->get_results( "SELECT {$quoted} FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- columns are allow-listed above.
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'run';
		$preview_only = 'preview' === $mode;
		$report = array( 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'subscribers' => count( $rows ), 'completed_at' => current_time( 'mysql' ) );
		$processed = 0;
		foreach ( $rows as $row ) {
			++$processed;
			$email = sanitize_email( $row['email'] ?? '' );
			if ( ! is_email( $email ) ) { ++$report['skipped']; self::newsletter_migration_progress( $processed, count( $rows ) ); continue; }
			$status_code = strtoupper( sanitize_key( $row['status'] ?? '' ) );
			$status_map = array( 'C' => 'confirmed', 'U' => 'unconfirmed', 'S' => 'unsubscribed', 'B' => 'bounced' );
			$incoming = $status_map[ $status_code ] ?? 'unconfirmed';
			$contact = ContactPanda_Store::contact_by_email( $email );
			$data = array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $row['name'] ?? '' ),
				'last_name'  => sanitize_text_field( $row['surname'] ?? '' ),
				'source'     => 'newsletter_plugin',
				'meta'       => array( 'newsletter_plugin_id' => absint( $row['id'] ?? 0 ), 'newsletter_plugin_status' => $status_code ),
			);
			if ( $contact ) {
				if ( $preview_only ) { ++$report['updated']; self::newsletter_migration_progress( $processed, count( $rows ) ); continue; }
				$current = sanitize_key( $contact['optin_status'] ?? '' );
				$was_confirmed = 'confirmed' === $current;
				if ( in_array( $current, array( 'unsubscribed', 'bounced', 'spam' ), true ) && 'confirmed' === $incoming ) $incoming = $current;
				$data['first_name'] = $contact['first_name'] ?: $data['first_name'];
				$data['last_name'] = $contact['last_name'] ?: $data['last_name'];
				$data['optin_status'] = $incoming;
				ContactPanda_Store::update_contact( $contact['id'], $data );
				if ( 'confirmed' === $incoming && ! $was_confirmed ) ContactPanda_Store::record_consent( $contact['id'], 'granted', 'newsletter_plugin_import', self::valid_import_date( $row['created'] ?? '' ), __( 'Imported newsletter subscription.', 'contactpanda' ) );
				++$report['updated'];
			} else {
				if ( $preview_only ) { ++$report['new']; self::newsletter_migration_progress( $processed, count( $rows ) ); continue; }
				$data['optin_status'] = $incoming;
				$id = ContactPanda_Store::create_contact( $data );
				if ( is_wp_error( $id ) || ! $id ) { ++$report['errors']; continue; }
				if ( 'confirmed' === $incoming ) ContactPanda_Store::record_consent( $id, 'granted', 'newsletter_plugin_import', self::valid_import_date( $row['created'] ?? '' ), __( 'Imported newsletter subscription.', 'contactpanda' ) );
				++$report['new'];
			}
			self::newsletter_migration_progress( $processed, count( $rows ) );
		}
		self::store_migration_progress( array( 'stage' => __( 'Preparing migration…', 'contactpanda' ), 'processed' => count( $rows ), 'total' => count( $rows ), 'percent' => 70 ) );
		$report = (array) apply_filters( 'contactpanda_newsletter_plugin_migration_report', $report, $preview_only, $table );
		if ( $preview_only ) {
			set_transient( 'contactpanda_newsletter_plugin_preview_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
			self::database_redirect( 'success', __( 'The newsletter import preview is ready. No source or ContactPanda data has been changed.', 'contactpanda' ) );
		}
		delete_transient( 'contactpanda_newsletter_plugin_preview_' . get_current_user_id() );
		$report['completed_at'] = current_time( 'mysql' );
		update_option( 'contactpanda_last_newsletter_plugin_migration_report', $report, false );
		self::store_migration_progress( array( 'stage' => __( 'Migration completed.', 'contactpanda' ), 'processed' => count( $rows ), 'total' => count( $rows ), 'percent' => 100 ) );
		self::database_redirect( 'success', sprintf( __( 'Newsletter subscriber import completed: %1$d new contacts, %2$d updated contacts, %3$d skipped.', 'contactpanda' ), $report['new'], $report['updated'], $report['skipped'] ) );
	}

	private static function newsletter_migration_progress( $processed, $total ) {
		if ( 0 !== $processed % 25 && $processed !== $total ) return;
		self::store_migration_progress( array( 'stage' => __( 'Importing contacts…', 'contactpanda' ), 'processed' => $processed, 'total' => $total, 'percent' => $total ? min( 68, 5 + (int) floor( 63 * $processed / $total ) ) : 68 ) );
	}

	/** Preview or import FluentCRM contacts without modifying the source tables. */
	public static function handle_migrate_fluentcrm() {
		self::guard_database_action( 'contactpanda_migrate_fluentcrm' );
		$table = self::fluentcrm_table();
		if ( ! $table || ! ContactPanda_Store::ready() ) {
			self::database_redirect( 'error', __( 'The FluentCRM subscriber source or the ContactPanda database is not available.', 'contactpanda' ), 'fluentcrm' );
		}
		global $wpdb;
		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- verified WordPress-prefixed plugin table.
		$allow = array( 'id', 'user_id', 'prefix', 'first_name', 'last_name', 'email', 'phone', 'status', 'address_line_1', 'address_line_2', 'postal_code', 'city', 'state', 'country', 'created_at', 'updated_at' );
		$wanted = array_values( array_intersect( $allow, $columns ) );
		if ( ! in_array( 'id', $wanted, true ) || ! in_array( 'email', $wanted, true ) ) {
			self::database_redirect( 'error', __( 'The detected FluentCRM table does not contain compatible ID and email fields.', 'contactpanda' ), 'fluentcrm' );
		}
		$quoted = '`' . implode( '`,`', $wanted ) . '`';
		$rows = (array) $wpdb->get_results( "SELECT {$quoted} FROM `{$table}` ORDER BY `id` ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- columns are allow-listed above.
		$preview_only = 'preview' === sanitize_key( wp_unslash( $_POST['mode'] ?? 'run' ) );
		$report = array( 'found' => count( $rows ), 'new' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'completed_at' => current_time( 'mysql' ) );
		$status_map = array(
			'subscribed'   => 'confirmed',
			'confirmed'    => 'confirmed',
			'pending'      => 'unconfirmed',
			'unconfirmed'  => 'unconfirmed',
			'unsubscribed' => 'unsubscribed',
			'bounced'      => 'bounced',
			'complained'   => 'spam',
			'spam'         => 'spam',
		);
		foreach ( $rows as $row ) {
			$email = sanitize_email( $row['email'] ?? '' );
			$source_id = absint( $row['id'] ?? 0 );
			if ( ! $source_id || ! is_email( $email ) ) { ++$report['skipped']; continue; }
			$contact = ContactPanda_Store::contact_by_meta( 'fluentcrm_subscriber_id', $source_id, true );
			if ( ! $contact ) $contact = ContactPanda_Store::contact_by_email( $email, true );
			$incoming = $status_map[ sanitize_key( $row['status'] ?? '' ) ] ?? 'unconfirmed';
			$data = array(
				'email'        => $email,
				'first_name'   => sanitize_text_field( $row['first_name'] ?? '' ),
				'last_name'    => sanitize_text_field( $row['last_name'] ?? '' ),
				'user_id'      => absint( $row['user_id'] ?? 0 ),
				'source'       => 'fluentcrm',
				'optin_status' => $incoming,
				'meta'         => array(
					'fluentcrm_subscriber_id' => $source_id,
					'fluentcrm_status'        => sanitize_key( $row['status'] ?? '' ),
					'anrede'                  => sanitize_text_field( $row['prefix'] ?? '' ),
					'primary_phone'           => sanitize_text_field( $row['phone'] ?? '' ),
					'address_1'               => sanitize_text_field( $row['address_line_1'] ?? '' ),
					'address_2'               => sanitize_text_field( $row['address_line_2'] ?? '' ),
					'postcode'                 => sanitize_text_field( $row['postal_code'] ?? '' ),
					'city'                     => sanitize_text_field( $row['city'] ?? '' ),
					'state'                    => sanitize_text_field( $row['state'] ?? '' ),
					'country'                  => strtoupper( sanitize_text_field( $row['country'] ?? '' ) ),
				),
			);
			if ( $contact ) {
				++$report['updated'];
				if ( $preview_only ) continue;
				$current = sanitize_key( $contact['optin_status'] ?? ( $contact['meta']['optin_status'] ?? '' ) );
				if ( in_array( $current, array( 'unsubscribed', 'bounced', 'spam' ), true ) || ( 'confirmed' === $current && 'unconfirmed' === $incoming ) ) $data['optin_status'] = $current;
				$data['first_name'] = $contact['first_name'] ?: $data['first_name'];
				$data['last_name'] = $contact['last_name'] ?: $data['last_name'];
				foreach ( $data['meta'] as $key => $value ) if ( '' === (string) $value && isset( $contact['meta'][ $key ] ) ) unset( $data['meta'][ $key ] );
				ContactPanda_Store::update_contact( $contact['id'], $data );
				if ( 'confirmed' === $data['optin_status'] && 'confirmed' !== $current ) ContactPanda_Store::record_consent( $contact['id'], 'granted', 'fluentcrm_import', self::valid_import_date( $row['created_at'] ?? '' ), __( 'Imported FluentCRM subscription.', 'contactpanda' ) );
				continue;
			}
			++$report['new'];
			if ( $preview_only ) continue;
			$id = ContactPanda_Store::create_contact( $data );
			if ( is_wp_error( $id ) || ! $id ) { --$report['new']; ++$report['errors']; continue; }
			if ( 'confirmed' === $incoming ) ContactPanda_Store::record_consent( $id, 'granted', 'fluentcrm_import', self::valid_import_date( $row['created_at'] ?? '' ), __( 'Imported FluentCRM subscription.', 'contactpanda' ) );
		}
		$report = (array) apply_filters( 'contactpanda_fluentcrm_migration_report', $report, $preview_only, $table );
		if ( $preview_only ) {
			set_transient( 'contactpanda_fluentcrm_preview_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
			self::database_redirect( 'success', __( 'The FluentCRM import preview is ready. No source or ContactPanda data has been changed.', 'contactpanda' ), 'fluentcrm' );
		}
		delete_transient( 'contactpanda_fluentcrm_preview_' . get_current_user_id() );
		update_option( 'contactpanda_last_fluentcrm_migration_report', $report, false );
		self::database_redirect( 'success', sprintf( __( 'FluentCRM import completed: %1$d new contacts, %2$d updated contacts, %3$d skipped.', 'contactpanda' ), $report['new'], $report['updated'], $report['skipped'] ), 'fluentcrm' );
	}

	private static function valid_import_date( $value ) {
		$date = substr( sanitize_text_field( (string) $value ), 0, 10 );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_time( 'Y-m-d' );
	}

	public static function render_duplicate_preview() {
		if ( ! ContactPanda_Store::ready() ) return;
		$search = isset( $_GET['duplicate_search'] ) ? sanitize_text_field( wp_unslash( $_GET['duplicate_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		$page = max( 1, absint( $_GET['duplicate_page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list pagination.
		$per_page = 20;
		$candidates = ContactPanda_Store::duplicate_groups( 250 );
		if ( $search ) $candidates = array_values( array_filter( $candidates, static function ( $group ) use ( $search ) { return false !== stripos( implode( ' ', (array) $group['names'] ), $search ); } ) );
		$total = count( $candidates );
		$candidates = array_slice( $candidates, ( $page - 1 ) * $per_page, $per_page );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Duplicate review', 'contactpanda' ) . '</h2><p>' . esc_html__( 'ContactPanda lists likely duplicates without changing them. Use the merge tool below to select a primary record and confirm every conflict.', 'contactpanda' ) . '</p>';
		echo '<form method="get" class="contactpanda-search"><input type="hidden" name="page" value="contactpanda-settings"><input type="hidden" name="tab" value="other"><input type="hidden" name="other_section" value="duplicates"><label class="screen-reader-text" for="contactpanda-duplicate-search">' . esc_html__( 'Search duplicates', 'contactpanda' ) . '</label><input id="contactpanda-duplicate-search" type="search" name="duplicate_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search by name', 'contactpanda' ) . '"><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form>';
		if ( ! $candidates ) {
			echo '<p>' . esc_html__( 'No likely duplicates were found using phone number, WordPress account, or matching name and address.', 'contactpanda' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Possible duplicate', 'contactpanda' ) . '</th><th>' . esc_html__( 'Match', 'contactpanda' ) . '</th><th>' . esc_html__( 'Review', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $candidates as $group ) {
			$names = array_values( $group['names'] );
			$primary = array_shift( $names );
			echo '<tr><td><strong>' . esc_html( $primary ) . '</strong>' . ( $names ? '<br><span class="contactpanda-muted">' . esc_html( implode( ', ', $names ) ) . '</span>' : '' ) . '</td><td>' . esc_html( implode( ', ', $group['reasons'] ) ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-duplicate-selection"><input type="hidden" name="action" value="contactpanda_merge_group">';
			foreach ( $group['ids'] as $index => $id ) echo '<label><input type="checkbox" name="ids[]" value="' . esc_attr( $id ) . '" checked> ' . esc_html( $group['names'][ $index ] ?: sprintf( __( 'Record #%d', 'contactpanda' ), $id ) ) . ' <small>#' . esc_html( $id ) . '</small></label>';
			wp_nonce_field( 'contactpanda_merge_group' ); echo '<p class="description">' . esc_html__( 'Select only the records that should be combined. At least two are required.', 'contactpanda' ) . '</p><button class="button">' . esc_html__( 'Edit duplicates', 'contactpanda' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';
		if ( $total > $per_page ) {
			$base = array( 'page' => 'contactpanda-settings', 'tab' => 'other', 'other_section' => 'duplicates', 'duplicate_search' => $search );
			echo '<div class="tablenav"><div class="tablenav-pages">';
			if ( $page > 1 ) echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base, array( 'duplicate_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Previous', 'contactpanda' ) . '</a>';
			echo '<span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'contactpanda' ), $page, (int) ceil( $total / $per_page ) ) ) . '</span>';
			if ( $page * $per_page < $total ) echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base, array( 'duplicate_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Next', 'contactpanda' ) . '</a>';
			echo '</div></div>';
		}
		echo '</section>';
	}

	private static function render_integrations_settings() {
		$sections = array( 'woocommerce' => 'WooCommerce', 'groundhogg' => 'Groundhogg' );
		if ( class_exists( 'ContactPanda_Plus_Messaging' ) ) {
			$sections['messaging'] = __( 'SMS and WhatsApp gateways', 'contactpanda' );
		}
		if ( class_exists( 'ContactPanda_Plus_Email_Delivery' ) ) {
			$sections['email'] = __( 'Email delivery', 'contactpanda' );
		}
		$sections = (array) apply_filters( 'contactpanda_integration_sections', $sections );
		$current = isset( $_GET['integration_section'] ) ? sanitize_key( wp_unslash( $_GET['integration_section'] ) ) : 'woocommerce'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		if ( ! isset( $sections[ $current ] ) ) $current = 'woocommerce';
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs">'; foreach ( $sections as $key => $label ) echo '<a class="nav-tab ' . ( $current === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=integrations&integration_section=' . sanitize_key( $key ) ) ) . '">' . esc_html( $label ) . '</a>'; echo '</nav>';
		if ( 'woocommerce' === $current && class_exists( 'WooCommerce' ) ) {
			echo '<section class="contactpanda-card contactpanda-settings-card"><h2>WooCommerce</h2><p>' . esc_html__( 'WooCommerce is active. Orders and customer information can be displayed in ContactPanda; existing orders, invoices, prices, and WooCommerce records are never changed.', 'contactpanda' ) . '</p>';
			self::render_plus_promotion( 'woocommerce' ); echo '</section>';
		} elseif ( 'woocommerce' === $current ) echo '<section class="contactpanda-card contactpanda-settings-card"><h2>WooCommerce</h2><p>' . esc_html__( 'WooCommerce is not active.', 'contactpanda' ) . '</p></section>';
		if ( 'groundhogg' === $current ) {
			self::render_data_source_settings();
			if ( self::newsletter_plugin_installed() ) self::render_newsletter_plugin_readonly();
			if ( self::groundhogg_active() ) {
			$settings = self::general_settings();
			$leading = 'groundhogg' === $settings['data_source'];
			echo '<section class="contactpanda-card contactpanda-settings-card"><h2>Groundhogg</h2><p>' . esc_html( $leading ? __( 'Groundhogg is active and is currently the leading contact data source.', 'contactpanda' ) : __( 'Groundhogg is active but is currently not the leading contact data source.', 'contactpanda' ) ) . '</p><p>' . esc_html( $leading ? __( 'ContactPanda is the user interface for Groundhogg in this mode. Changes made in ContactPanda are saved directly in Groundhogg.', 'contactpanda' ) : __( 'ContactPanda now stores new changes in its own database. These standalone changes cannot be migrated back to Groundhogg.', 'contactpanda' ) ) . '</p></section>';
			}
		}
		do_action( 'contactpanda_render_integration_section_' . $current );
		do_action( 'contactpanda_render_integrations_settings', $current );
	}

	private static function render_newsletter_plugin_readonly() {
		global $wpdb;
		$email_table = $wpdb->prefix . 'newsletter_emails';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $email_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>The Newsletter Plugin</h2><p>' . esc_html__( 'The Newsletter Plugin is active. ContactPanda provides a read-only overview; the source plugin remains unchanged.', 'contactpanda' ) . '</p>';
		if ( $exists ) {
			$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$email_table}`", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$title_column = in_array( 'subject', $columns, true ) ? 'subject' : ( in_array( 'name', $columns, true ) ? 'name' : 'id' );
			$sent_column = in_array( 'sent', $columns, true ) ? 'sent' : ( in_array( 'total', $columns, true ) ? 'total' : '0' );
			$open_column = in_array( 'open', $columns, true ) ? 'open' : ( in_array( 'opens', $columns, true ) ? 'opens' : '0' );
			$rows = (array) $wpdb->get_results( "SELECT id, `{$title_column}` AS title, {$sent_column} AS sent, {$open_column} AS opened FROM `{$email_table}` ORDER BY id DESC LIMIT 20", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Mailing', 'contactpanda' ) . '</th><th>' . esc_html__( 'Delivered', 'contactpanda' ) . '</th><th>' . esc_html__( 'Opened', 'contactpanda' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) echo '<tr><td>' . esc_html( $row['title'] ?: sprintf( __( 'Mailing #%d', 'contactpanda' ), $row['id'] ) ) . '</td><td>' . esc_html( absint( $row['sent'] ) ) . '</td><td>' . esc_html( absint( $row['opened'] ) ) . '</td></tr>';
			if ( ! $rows ) echo '<tr><td colspan="3">' . esc_html__( 'No previous mailings were found.', 'contactpanda' ) . '</td></tr>';
			echo '</tbody></table>';
		}
		self::render_plus_promotion( 'newsletter' ); echo '</section>';
	}

	public static function render_plus_promotion( $context = '' ) {
		if ( ! current_user_can( 'manage_options' ) || defined( 'CONTACTPANDA_PLUS_VERSION' ) ) return;
		$url = apply_filters( 'contactpanda_plus_url', 'https://contactpanda.com/', $context );
		echo '<div class="contactpanda-plus-promotion"><p><strong>' . esc_html__( 'Manage this data centrally in ContactPanda Plus.', 'contactpanda' ) . '</strong> ' . esc_html__( 'Save time and costs, avoid duplicate work, and unlock additional automation, newsletter, and reporting options.', 'contactpanda' ) . '</p><p><a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn about ContactPanda Plus', 'contactpanda' ) . '</a></p></div>';
	}

	private static function woocommerce_import_form( $mode, $label, $class ) {
		echo '<form class="contactpanda-inline-action-form contactpanda-migration-progress-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_import_woocommerce"><input type="hidden" name="mode" value="' . esc_attr( $mode ) . '"><input type="hidden" name="migration_source" value="woocommerce">';
		wp_nonce_field( 'contactpanda_import_woocommerce' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	public static function handle_import_woocommerce() {
		self::guard_database_action( 'contactpanda_import_woocommerce' );
		if ( ! ContactPanda_Store::standalone() ) {
			self::database_redirect( 'error', __( 'WooCommerce customer import is available only when the ContactPanda database is the leading data source.', 'contactpanda' ) );
		}
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'preview' ) );
		try {
			$result = ContactPanda_Store::import_woocommerce( 'run' !== $mode );
		} catch ( Throwable $error ) {
			self::database_redirect( 'error', __( 'The WooCommerce import could not be completed. WooCommerce data was not changed. Please review the ContactPanda data before trying again.', 'contactpanda' ) );
		}
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		if ( 'run' !== $mode ) {
			set_transient( 'contactpanda_woocommerce_import_' . get_current_user_id(), $result, 30 * MINUTE_IN_SECONDS );
			self::database_redirect( 'success', __( 'The preview is ready. Review the totals before starting the import.', 'contactpanda' ) );
		}
		delete_transient( 'contactpanda_woocommerce_import_' . get_current_user_id() );
		$result['completed_at'] = current_time( 'mysql' );
		update_option( 'contactpanda_last_woocommerce_migration_report', $result, false );
		self::database_redirect( 'success', sprintf( __( 'WooCommerce import completed at %1$s: %2$d new contacts, %3$d updated contacts, %4$d new companies, %5$d skipped.', 'contactpanda' ), $result['completed_at'], absint( $result['new'] ), absint( $result['updated'] ), absint( $result['companies'] ), absint( $result['skipped'] ) ) );
	}

	private static function render_theme_settings( $settings ) {
		$theme = wp_get_theme();
		$name = $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $theme->get_stylesheet();
		$active = ! empty( $settings['theme_styles'] );
		echo '<section class="contactpanda-card contactpanda-settings-card contactpanda-theme-settings"><h2>' . esc_html__( 'Appearance', 'contactpanda' ) . '</h2>';
		echo '<div class="contactpanda-active-theme"><span><strong>' . esc_html__( 'Active theme', 'contactpanda' ) . '</strong><small>' . esc_html( $name ) . '</small></span><span class="contactpanda-status-badge is-positive">' . ( $active ? esc_html( sprintf( __( '%s theme style', 'contactpanda' ), $name ) ) : esc_html__( 'ContactPanda style', 'contactpanda' ) ) . '</span></div>';
		echo '<p class="description">' . esc_html__( 'ContactPanda can inherit the theme typography, colors, form fields, buttons, borders, and corner radii. Its functional layout remains intact.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="options.php">'; settings_fields( 'contactpanda_general' );
		echo '<div class="contactpanda-style-switch"><label class="contactpanda-style-option"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[theme_styles]" value="0"' . checked( ! $active, true, false ) . '><span><strong>' . esc_html__( 'ContactPanda style', 'contactpanda' ) . '</strong><small>' . esc_html__( 'Use the neutral ContactPanda form and account design.', 'contactpanda' ) . '</small><span class="contactpanda-theme-button-sample"><small>' . esc_html__( 'Button preview', 'contactpanda' ) . '</small><span class="contactpanda-theme-button-preview is-contactpanda">' . esc_html__( 'Example button', 'contactpanda' ) . '</span></span></span></label>';
		echo '<label class="contactpanda-style-option"><input type="radio" name="' . esc_attr( self::GENERAL_OPTION ) . '[theme_styles]" value="1"' . checked( $active, true, false ) . '><span><strong>' . esc_html( sprintf( __( '%s theme style', 'contactpanda' ), $name ) ) . '</strong><small>' . esc_html__( 'Inherit compatible typography, colors, fields, buttons, borders, and spacing from the active theme.', 'contactpanda' ) . '</small><span class="contactpanda-style-theme-colors" aria-label="' . esc_attr__( 'Detected theme colors', 'contactpanda' ) . '">';
		if ( class_exists( 'ContactPanda_Basic_Newsletters' ) ) foreach ( ContactPanda_Basic_Newsletters::detected_theme_colors() as $color ) echo '<i title="' . esc_attr( $color ) . '" style="background:' . esc_attr( $color ) . '"></i>';
		echo '</span><span class="contactpanda-theme-button-sample"><small>' . esc_html__( 'Button preview', 'contactpanda' ) . '</small><span class="contactpanda-theme-button-preview is-theme">' . esc_html__( 'Example button', 'contactpanda' ) . '</span></span></span></label></div>';
		submit_button( __( 'Save appearance', 'contactpanda' ) ); echo '</form></section>';
	}

	public static function handle_adopt_theme_styles() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'contactpanda' ) );
		}
		check_admin_referer( 'contactpanda_adopt_theme_styles' );
		$theme = wp_get_theme();
		$settings = self::general_settings();
		$settings['theme_styles'] = 1;
		$settings['theme_stylesheet'] = sanitize_key( $theme->get_stylesheet() );
		$settings['theme_name'] = sanitize_text_field( $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $theme->get_stylesheet() );
		update_option( self::GENERAL_OPTION, $settings, false );
		set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => 'success', 'message' => sprintf( __( 'ContactPanda now adopts the styles from %s.', 'contactpanda' ), $settings['theme_name'] ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general' ) );
		exit;
	}

	private static function render_database_tools( $schema, $show_groundhogg = true, $show_reset = true ) {
		$counts = ContactPanda_Store::counts();
		$report = (array) get_option( 'contactpanda_last_migration_report', array() );
		$preview = get_transient( 'contactpanda_groundhogg_contacts_preview_' . get_current_user_id() );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'ContactPanda database', 'contactpanda' ) . '</h2>';
		if ( ! ContactPanda_Store::ready() ) {
			echo '<p>' . esc_html__( 'The ContactPanda database tables do not exist yet or require an update. Creating or updating them does not change Groundhogg or any connected plugin.', 'contactpanda' ) . '</p>';
			self::admin_action_form( 'contactpanda_setup_standalone', __( 'Create or update ContactPanda database and activate', 'contactpanda' ), 'button-primary' );
		} else {
			echo '<div class="contactpanda-database-counts"><span><strong>' . esc_html( $counts['contacts'] ) . '</strong>' . esc_html__( 'Contacts', 'contactpanda' ) . '</span><span><strong>' . esc_html( $counts['companies'] ) . '</strong>' . esc_html__( 'Companies', 'contactpanda' ) . '</span><span><strong>' . esc_html( $counts['tags'] ) . '</strong>' . esc_html__( 'Tags', 'contactpanda' ) . '</span><span><strong>' . esc_html( $counts['activities'] ) . '</strong>' . esc_html__( 'Activities', 'contactpanda' ) . '</span></div>';
			echo '<div class="contactpanda-database-actions">';
			if ( $show_groundhogg && self::groundhogg_active() ) {
				echo '<p>' . esc_html__( 'People, companies and tags are analyzed and migrated independently from email templates, forms and automations. Groundhogg remains unchanged.', 'contactpanda' ) . '</p>';
				if ( $preview ) {
					echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Analysis', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d people, %2$d companies and %3$d tags found. ContactPanda currently contains %4$d people, %5$d companies and %6$d tags. No data was changed.', 'contactpanda' ), absint( $preview['contacts'] ?? 0 ), absint( $preview['companies'] ?? 0 ), absint( $preview['tags'] ?? 0 ), absint( $preview['existing_contacts'] ?? 0 ), absint( $preview['existing_companies'] ?? 0 ), absint( $preview['existing_tags'] ?? 0 ) ) ) . '</p></div>';
				}
				self::groundhogg_contacts_migration_form( 'preview', __( 'Analyze people, companies and tags', 'contactpanda' ), 'button-secondary' );
				if ( $preview ) self::groundhogg_contacts_migration_form( 'run', __( 'Import and update people, companies and tags', 'contactpanda' ), 'button-primary contactpanda-groundhogg-migration-form' );
				echo '<div class="contactpanda-migration-progress" hidden aria-live="polite"><strong>' . esc_html__( 'Preparing migration…', 'contactpanda' ) . '</strong><progress value="0" max="100"></progress><span>0%</span><small></small></div>';
			}
			if ( $show_reset ) self::admin_action_form( 'contactpanda_reset_standalone', __( 'Reset ContactPanda data', 'contactpanda' ), 'button-secondary contactpanda-reset-test-data', __( 'Deletes ContactPanda CRM records and imported Groundhogg newsletter archives. ContactPanda-created newsletter drafts, Groundhogg, WordPress, WooCommerce, invoices, and events remain untouched.', 'contactpanda' ), true );
			echo '</div>';
			if ( $show_groundhogg && $report ) {
				echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Last migration', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d contacts, %2$d companies, %3$d tags, %4$d tag assignments, %5$d skipped, %6$d errors. Completed: %7$s', 'contactpanda' ), absint( $report['contacts'] ?? 0 ), absint( $report['companies'] ?? 0 ), absint( $report['tags'] ?? 0 ), absint( $report['assignments'] ?? 0 ), absint( $report['skipped'] ?? 0 ), absint( $report['errors'] ?? 0 ), sanitize_text_field( $report['completed_at'] ?? '' ) ) ) . '</p>';
				if ( isset( $report['source_contacts'] ) ) {
					echo '<p>' . esc_html( sprintf( __( 'Groundhogg contact comparison: %1$d found in the source, %2$d imported or updated.', 'contactpanda' ), absint( $report['source_contacts'] ), absint( $report['contacts'] ?? 0 ) ) ) . '</p>';
				}
				if ( isset( $report['source_tags'] ) ) {
					echo '<p>' . esc_html( sprintf( __( 'Tag comparison: %1$d in Groundhogg, %2$d in ContactPanda; %3$d source assignments processed.', 'contactpanda' ), absint( $report['source_tags'] ), absint( $report['tags'] ?? 0 ), absint( $report['source_assignments'] ?? 0 ) ) ) . '</p>';
				}
				if ( isset( $report['email_templates'] ) || isset( $report['broadcasts'] ) ) {
					echo '<p>' . esc_html( sprintf( __( 'Newsletter archive: %1$d email templates and %2$d completed broadcasts imported; %3$d errors.', 'contactpanda' ), absint( $report['email_templates'] ?? 0 ), absint( $report['broadcasts'] ?? 0 ), absint( $report['newsletter_errors'] ?? 0 ) ) ) . '</p>';
					if ( isset( $report['broadcast_recipients'] ) ) {
						echo '<p>' . esc_html( sprintf( __( '%d historical broadcast recipient assignments were imported where Groundhogg made them available.', 'contactpanda' ), absint( $report['broadcast_recipients'] ) ) ) . '</p>';
					}
				}
				if ( ! empty( $report['missing_tags'] ) && is_array( $report['missing_tags'] ) ) {
					echo '<details><summary>' . esc_html( sprintf( __( 'Tag warnings: %d', 'contactpanda' ), count( $report['missing_tags'] ) ) ) . '</summary><ul>';
					foreach ( array_slice( $report['missing_tags'], 0, 100 ) as $warning ) {
						echo '<li>' . esc_html( $warning ) . '</li>';
					}
					echo '</ul></details>';
				}
				if ( ! empty( $report['skipped_tags'] ) && is_array( $report['skipped_tags'] ) ) {
					echo '<details><summary>' . esc_html( sprintf( __( 'Intentionally omitted system tags: %d', 'contactpanda' ), count( $report['skipped_tags'] ) ) ) . '</summary><ul>';
					foreach ( array_slice( $report['skipped_tags'], 0, 100 ) as $warning ) {
						echo '<li>' . esc_html( $warning ) . '</li>';
					}
					echo '</ul></details>';
				}
				echo '</div>';
			}
		}
		echo '</section>';
	}

	private static function groundhogg_contacts_migration_form( $mode, $label, $class ) {
		echo '<form class="contactpanda-inline-action-form' . ( 'run' === $mode ? ' contactpanda-groundhogg-migration-form' : '' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_migrate_groundhogg"><input type="hidden" name="migration_source" value="groundhogg"><input type="hidden" name="migration_mode" value="' . esc_attr( $mode ) . '">';
		wp_nonce_field( 'contactpanda_migrate_groundhogg' );
		echo '<button type="submit" class="button ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	private static function admin_action_form( $action, $label, $class = '', $description = '', $danger = false ) {
		echo '<form class="contactpanda-inline-action-form' . ( $danger ? ' contactpanda-reset-form' : '' ) . ( 'contactpanda_migrate_groundhogg' === $action ? ' contactpanda-groundhogg-migration-form' : '' ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		if ( 'contactpanda_migrate_groundhogg' === $action ) echo '<input type="hidden" name="migration_source" value="groundhogg">';
		wp_nonce_field( $action );
		if ( $danger ) echo '<input type="hidden" name="confirm_reset" value="1">';
		echo '<button type="' . ( $danger ? 'button' : 'submit' ) . '" class="button ' . esc_attr( $class ) . '"' . ( $danger ? ' data-contactpanda-reset-open' : '' ) . '>' . esc_html( $label ) . '</button>';
		if ( $description ) echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</form>';
		if ( $danger ) {
			echo '<div class="contactpanda-consent-modal contactpanda-reset-modal" hidden><div class="contactpanda-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-reset-title"><h2 id="contactpanda-reset-title">' . esc_html__( 'Permanently delete ContactPanda data?', 'contactpanda' ) . '</h2><p><strong>' . esc_html__( 'This action cannot be undone.', 'contactpanda' ) . '</strong></p><p>' . esc_html__( 'ContactPanda CRM records and imported newsletter archives will be deleted. ContactPanda-created email drafts, WordPress users, WooCommerce orders and invoices, Groundhogg data, and event data remain unchanged.', 'contactpanda' ) . '</p><div class="contactpanda-modal-actions"><button type="button" class="button contactpanda-reset-cancel">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button><button type="button" class="button button-primary contactpanda-reset-confirm">' . esc_html__( 'Permanently delete data', 'contactpanda' ) . '</button></div></div></div>';
		}
	}

	public static function handle_setup_standalone() {
		self::guard_database_action( 'contactpanda_setup_standalone' );
		$result = ContactPanda_Schema::install();
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		$settings = self::general_settings(); $settings['data_source'] = 'standalone'; update_option( self::GENERAL_OPTION, $settings, false );
		self::database_redirect( 'success', __( 'The ContactPanda database was created and activated.', 'contactpanda' ) );
	}

	public static function handle_migrate_groundhogg() {
		self::guard_database_action( 'contactpanda_migrate_groundhogg' );
		if ( 'run' !== sanitize_key( wp_unslash( $_POST['migration_mode'] ?? 'preview' ) ) ) {
			$preview = ContactPanda_Store::groundhogg_migration_preview();
			if ( is_wp_error( $preview ) ) self::database_redirect( 'error', $preview->get_error_message() );
			set_transient( 'contactpanda_groundhogg_contacts_preview_' . get_current_user_id(), $preview, HOUR_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-migration&migration_source=groundhogg&migration_area=contacts' ) );
			exit;
		}
		$result = self::run_groundhogg_migration();
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		self::database_redirect( 'success', __( 'The Groundhogg migration was completed and ContactPanda is now the leading data source. Groundhogg remains active and unchanged. You may switch the data source back later, but ContactPanda changes are not migrated back to Groundhogg.', 'contactpanda' ) );
	}

	public static function handle_migrate_groundhogg_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'contactpanda' ) ), 403 );
		}
		check_ajax_referer( 'contactpanda_migrate_groundhogg', '_wpnonce' );
		$result = self::run_groundhogg_migration();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		set_transient(
			'contactpanda_database_notice_' . get_current_user_id(),
			array(
				'type'    => 'success',
				'message' => __( 'The Groundhogg migration was completed and ContactPanda is now the leading data source. Groundhogg remains active and unchanged. You may switch the data source back later, but ContactPanda changes are not migrated back to Groundhogg.', 'contactpanda' ),
			),
			MINUTE_IN_SECONDS
		);
		wp_send_json_success(
			array(
				'redirect' => admin_url( 'admin.php?page=contactpanda-migration&migration_source=groundhogg' ),
			)
		);
	}

	private static function run_groundhogg_migration() {
		delete_transient( 'contactpanda_groundhogg_migration_' . get_current_user_id() );
		$result = ContactPanda_Store::migrate_groundhogg( false );
		if ( is_wp_error( $result ) ) return $result;
		$settings = self::general_settings();
		$settings['data_source'] = 'standalone';
		update_option( self::GENERAL_OPTION, $settings, false );
		return $result;
	}

	public static function store_migration_progress( $progress ) {
		if ( ! is_array( $progress ) || ! get_current_user_id() ) return;
		set_transient(
			'contactpanda_groundhogg_migration_' . get_current_user_id(),
			array(
				'stage'     => sanitize_text_field( $progress['stage'] ?? '' ),
				'processed' => absint( $progress['processed'] ?? 0 ),
				'total'     => absint( $progress['total'] ?? 0 ),
				'percent'   => min( 100, absint( $progress['percent'] ?? 0 ) ),
			),
			HOUR_IN_SECONDS
		);
	}

	public static function migration_progress_ajax() {
		check_ajax_referer( 'contactpanda_update_company', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		$progress = get_transient( 'contactpanda_groundhogg_migration_' . get_current_user_id() );
		wp_send_json_success( is_array( $progress ) ? $progress : array( 'stage' => __( 'Preparing migration…', 'contactpanda' ), 'processed' => 0, 'total' => 0, 'percent' => 0 ) );
	}

	private static function render_automatic_updates() {
		$plugin = plugin_basename( CONTACTPANDA_FILE );
		$enabled = in_array( $plugin, (array) get_site_option( 'auto_update_plugins', array() ), true );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Automatic ContactPanda updates', 'contactpanda' ) . '</h2><p>' . esc_html__( 'ContactPanda checks the public GitHub release channel and validates version metadata, compatibility, and the ZIP checksum before WordPress installs an update.', 'contactpanda' ) . '</p>';
		if ( ! current_user_can( 'update_plugins' ) ) {
			echo '<p>' . esc_html__( 'Only an administrator who may update plugins can change this setting.', 'contactpanda' ) . '</p></section>';
			return;
		}
		echo '<p><span class="contactpanda-status-badge ' . ( $enabled ? 'is-positive' : 'is-negative' ) . '">' . esc_html( $enabled ? __( 'Automatic updates enabled', 'contactpanda' ) : __( 'Automatic updates disabled', 'contactpanda' ) ) . '</span></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_toggle_automatic_updates"><input type="hidden" name="enabled" value="' . ( $enabled ? '0' : '1' ) . '">';
		wp_nonce_field( 'contactpanda_toggle_automatic_updates' );
		echo '<button class="button ' . ( $enabled ? '' : 'button-primary' ) . '">' . esc_html( $enabled ? __( 'Disable automatic updates', 'contactpanda' ) : __( 'Enable automatic updates', 'contactpanda' ) ) . '</button></form></section>';
	}

	public static function handle_automatic_updates() {
		if ( ! current_user_can( 'update_plugins' ) ) wp_die( esc_html__( 'You do not have permission to change plugin updates.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_toggle_automatic_updates' );
		$plugin = plugin_basename( CONTACTPANDA_FILE );
		$plugins = array_values( array_filter( (array) get_site_option( 'auto_update_plugins', array() ), 'is_string' ) );
		$plugins = array_values( array_diff( $plugins, array( $plugin ) ) );
		if ( ! empty( $_POST['enabled'] ) ) $plugins[] = $plugin;
		update_site_option( 'auto_update_plugins', array_values( array_unique( $plugins ) ) );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=general' ) );
		exit;
	}

	public static function handle_reset_standalone() {
		self::guard_database_action( 'contactpanda_reset_standalone' );
		if ( empty( $_POST['confirm_reset'] ) ) self::database_redirect( 'error', __( 'The required deletion confirmation was not provided.', 'contactpanda' ) );
		$result = ContactPanda_Schema::reset_data();
		if ( is_wp_error( $result ) ) self::database_redirect( 'error', $result->get_error_message() );
		self::database_redirect( 'success', __( 'Only ContactPanda data was deleted. The empty ContactPanda database is ready for a new migration.', 'contactpanda' ) );
	}

	private static function guard_database_action( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to perform this action.', 'contactpanda' ) );
		check_admin_referer( $action );
	}

	private static function database_redirect( $type, $message, $source = '' ) {
		set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		$source = sanitize_key( $source ?: wp_unslash( $_POST['migration_source'] ?? '' ) );
		$url = admin_url( 'admin.php?page=contactpanda-migration' );
		if ( $source ) $url = add_query_arg( 'migration_source', $source, $url );
		if ( wp_doing_ajax() ) {
			if ( 'error' === $type ) wp_send_json_error( array( 'message' => $message ), 400 );
			wp_send_json_success( array( 'redirect' => $url, 'message' => $message ) );
		}
		wp_safe_redirect( $url ); exit;
	}

	private static function render_preserved_integrations() {
		$integrations = self::installed_integrations();
		if ( ! $integrations ) {
			return;
		}
		echo '<div class="contactpanda-preserved-integrations"><h3>' . esc_html__( 'Data preserved in detected plugins', 'contactpanda' ) . '</h3>';
		foreach ( $integrations as $integration ) {
			echo '<div class="contactpanda-preserved-row"><span><strong>' . esc_html( $integration['name'] ) . '</strong><small>' . esc_html( $integration['message'] ) . '</small></span><span class="contactpanda-status-badge ' . ( $integration['active'] ? 'is-positive' : 'is-negative' ) . '">' . ( $integration['active'] ? esc_html__( 'Active', 'contactpanda' ) : esc_html__( 'Installed, inactive', 'contactpanda' ) ) . '</span></div>';
		}
		echo '</div>';
	}

	private static function installed_integrations() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$definitions = array(
			array( 'name' => 'Groundhogg', 'needles' => array( 'groundhogg' ), 'message' => __( 'Groundhogg contacts, companies, tags, funnels, and activities remain untouched.', 'contactpanda' ) ),
			array( 'name' => 'WooCommerce', 'needles' => array( 'woocommerce' ), 'message' => __( 'WordPress accounts, customer profiles, and orders remain untouched.', 'contactpanda' ) ),
			array( 'name' => 'German Market', 'needles' => array( 'german market' ), 'message' => __( 'Invoices and invoice documents remain untouched.', 'contactpanda' ) ),
			array( 'name' => 'B2B Market', 'needles' => array( 'b2b market' ), 'message' => __( 'Customer groups, roles, prices, and tax rules remain untouched.', 'contactpanda' ) ),
			array( 'name' => 'The Events Calendar', 'needles' => array( 'the events calendar' ), 'message' => __( 'Events and venue data remain untouched.', 'contactpanda' ) ),
			array( 'name' => 'Event Tickets', 'needles' => array( 'event tickets' ), 'message' => __( 'Tickets, attendees, and check-ins remain untouched.', 'contactpanda' ) ),
		);
		$result = array();
		foreach ( $definitions as $definition ) {
			$matched_files = array();
			foreach ( $plugins as $file => $plugin ) {
				$haystack = strtolower( (string) ( $plugin['Name'] ?? '' ) . ' ' . $file . ' ' . ( $plugin['TextDomain'] ?? '' ) );
				foreach ( $definition['needles'] as $needle ) {
					if ( false !== strpos( $haystack, $needle ) ) {
						$matched_files[] = $file;
						break;
					}
				}
			}
			if ( ! $matched_files ) {
				continue;
			}
			$active = false;
			foreach ( $matched_files as $file ) {
				if ( is_plugin_active( $file ) ) {
					$active = true;
					break;
				}
			}
			$result[] = array( 'name' => $definition['name'], 'message' => $definition['message'], 'active' => $active );
		}
		return $result;
	}

	private static function checkbox_setting( $key, $label, $description, $settings ) {
		echo '<div class="contactpanda-setting-row"><label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="1"' . checked( ! empty( $settings[ $key ] ), true, false ) . '> <strong>' . esc_html( $label ) . '</strong></label><p class="description">' . esc_html( $description ) . '</p></div>';
	}

	private static function page_setting( $key, $label, $settings ) {
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
		wp_dropdown_pages(
			array(
				'name'              => self::OPTION . '[' . $key . ']',
				'id'                => 'contactpanda-' . $key,
				'show_option_none'  => __( '— Select page —', 'contactpanda' ),
				'option_none_value' => 0,
				'sort_column'       => 'post_title',
				'selected'          => absint( $settings[ $key ] ),
			)
		);
		echo '</div>';
	}

	private static function text_setting( $key, $label, $settings ) {
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><input id="contactpanda-' . esc_attr( $key ) . '" class="large-text" type="text" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '"></div>';
	}

	private static function textarea_setting( $key, $label, $description, $settings ) {
		echo '<div class="contactpanda-setting-row"><label for="contactpanda-' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><textarea id="contactpanda-' . esc_attr( $key ) . '" class="large-text" rows="6" name="' . esc_attr( self::OPTION ) . '[' . esc_attr( $key ) . ']">' . esc_textarea( $settings[ $key ] ) . '</textarea><p class="description">' . esc_html( $description ) . '</p></div>';
	}

	private static function render_integration_status() {
		$integrations = array(
			'WordPress'    => true,
			'WooCommerce'  => class_exists( 'WooCommerce' ),
			'German Market' => defined( 'WGM_PLUGIN_VERSION' ) || class_exists( 'Woocommerce_German_Market' ),
			'B2B Market'   => defined( 'BM_VERSION' ) || class_exists( 'BM' ) || class_exists( 'B2B_Market' ),
		);
		echo '<section class="contactpanda-card"><h2>' . esc_html__( 'Integration status', 'contactpanda' ) . '</h2><div class="contactpanda-integration-grid">';
		foreach ( $integrations as $name => $active ) {
			echo '<div><strong>' . esc_html( $name ) . '</strong><span class="contactpanda-status-badge ' . ( $active ? 'is-positive' : 'is-negative' ) . '">' . ( $active ? esc_html__( 'Available', 'contactpanda' ) : esc_html__( 'Not detected', 'contactpanda' ) ) . '</span></div>';
		}
		echo '</div><p class="description">' . esc_html__( 'Registration data is stored in compatible WordPress and WooCommerce profile fields. Existing orders and invoices are never changed.', 'contactpanda' ) . '</p></section>';
	}

	private static function render_pending_registrations() {
		$settings = self::settings();
		$roles = self::allowed_roles();
		$configured_roles = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
		$users = get_users(
			array(
				'meta_key'   => 'contactpanda_business_status',
				'meta_value' => 'pending',
				'number'     => 100,
				'orderby'    => 'registered',
				'order'      => 'ASC',
			)
		);
		echo '<section class="contactpanda-card"><div class="contactpanda-toolbar"><h2>' . esc_html__( 'Pending business registrations', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( count( $users ) ) . '</span></div>';
		if ( ! $users ) {
			echo '<p class="contactpanda-muted">' . esc_html__( 'There are currently no registrations awaiting approval.', 'contactpanda' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped contactpanda-table"><thead><tr><th>' . esc_html__( 'Person', 'contactpanda' ) . '</th><th>' . esc_html__( 'Company', 'contactpanda' ) . '</th><th>' . esc_html__( 'Requested role', 'contactpanda' ) . '</th><th>' . esc_html__( 'VAT ID', 'contactpanda' ) . '</th><th>' . esc_html__( 'Email confirmed', 'contactpanda' ) . '</th><th>' . esc_html__( 'Registered', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $users as $user ) {
			$requested_role = sanitize_key( get_user_meta( $user->ID, 'contactpanda_requested_role', true ) );
			$role_label = isset( $roles[ $requested_role ] ) ? $roles[ $requested_role ] : __( 'No longer available', 'contactpanda' );
			$email_confirmed = (bool) get_user_meta( $user->ID, 'contactpanda_email_verified', true );
			$role_can_be_approved = isset( $configured_roles[ $requested_role ] );
			echo '<tr><td><strong>' . esc_html( $user->display_name ) . '</strong><br><a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></td><td>' . esc_html( get_user_meta( $user->ID, 'billing_company', true ) ) . '</td><td>' . esc_html( $role_label ) . '</td><td>' . esc_html( get_user_meta( $user->ID, 'billing_vat', true ) ) . '</td><td>' . ( get_user_meta( $user->ID, 'contactpanda_email_verified', true ) ? esc_html__( 'Yes', 'contactpanda' ) : esc_html__( 'No', 'contactpanda' ) ) . '</td><td>' . esc_html( get_date_from_gmt( $user->user_registered, get_option( 'date_format' ) ) ) . '</td><td><div class="contactpanda-registration-actions">';
			if ( $role_can_be_approved && $email_confirmed ) {
				self::decision_form( $user->ID, 'approve', __( 'Approve', 'contactpanda' ), 'button-primary' );
			} elseif ( ! $email_confirmed ) {
				echo '<span class="contactpanda-muted">' . esc_html__( 'Email confirmation is still pending.', 'contactpanda' ) . '</span>';
			} else {
				echo '<span class="contactpanda-muted">' . esc_html__( 'Select this role again in the settings before approval.', 'contactpanda' ) . '</span>';
			}
			self::decision_form( $user->ID, 'reject', __( 'Reject', 'contactpanda' ), '' );
			if ( ! get_user_meta( $user->ID, 'contactpanda_email_verified', true ) ) {
				self::resend_form( $user->ID );
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function decision_form( $user_id, $decision, $label, $class ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_registration_decision"><input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '"><input type="hidden" name="decision" value="' . esc_attr( $decision ) . '">';
		wp_nonce_field( 'contactpanda_registration_decision_' . $user_id );
		echo '<button class="button ' . esc_attr( $class ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	private static function resend_form( $user_id ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_registration_resend"><input type="hidden" name="user_id" value="' . esc_attr( $user_id ) . '">';
		wp_nonce_field( 'contactpanda_registration_resend_' . $user_id );
		echo '<button class="button" type="submit">' . esc_html__( 'Resend confirmation', 'contactpanda' ) . '</button></form>';
	}

	public static function shortcode( $atts = array() ) {
		if ( ! self::customer_registration_enabled() ) {
			return '';
		}
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return current_user_can( 'manage_options' ) ? '<div class="contactpanda-registration-notice is-error">' . esc_html__( 'The ContactPanda registration form is disabled in the settings.', 'contactpanda' ) . '</div>' : '';
		}
		if ( is_user_logged_in() ) {
			return '<div class="contactpanda-registration-notice">' . esc_html__( 'You are already signed in.', 'contactpanda' ) . '</div>';
		}
		$atts = shortcode_atts( array( 'type' => '' ), $atts, 'contactpanda_registration' );
		$type = sanitize_key( $atts['type'] );
		if ( ! in_array( $type, array( 'customer', 'business' ), true ) ) {
			$type = isset( $_GET['contactpanda_registration_type'] ) && 'business' === sanitize_key( wp_unslash( $_GET['contactpanda_registration_type'] ) ) ? 'business' : 'customer'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display choice only.
		}
		if ( 'business' === $type && empty( $settings['business_enabled'] ) ) {
			$type = 'customer';
		}
		$available_roles = array_intersect_key( self::allowed_roles(), array_flip( (array) $settings['allowed_roles'] ) );
		if ( 'business' === $type && ! $available_roles ) {
			return '<div class="contactpanda-registration-notice is-error">' . esc_html__( 'Registration is currently unavailable because no customer role has been configured.', 'contactpanda' ) . '</div>';
		}
		wp_enqueue_style( 'contactpanda-registration', CONTACTPANDA_URL . 'assets/contactpanda-registration.css', array(), CONTACTPANDA_ASSET_VERSION );
		wp_enqueue_script( 'contactpanda-registration', CONTACTPANDA_URL . 'assets/contactpanda-registration.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script(
			'contactpanda-registration',
			'CONTACTPANDA_REGISTRATION',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'vatNonce' => wp_create_nonce( 'contactpanda_registration_validate_vat' ),
				'i18n' => array(
					'checkingVat' => __( 'Checking EU VIES…', 'contactpanda' ),
					'vatValidationFailed' => __( 'VAT ID validation could not be completed.', 'contactpanda' ),
				),
			)
		);
		$notice = self::consume_notice();
		$countries = self::countries();
		ob_start();
		if ( $notice ) {
			echo self::render_front_notice( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped markup.
			if ( ! empty( $notice['verification_complete'] ) || ! empty( $notice['confirmation_pending'] ) ) {
				return ob_get_clean();
			}
		}
		$general = self::general_settings();
		$theme_class = '';
		if ( ! empty( $general['theme_styles'] ) ) {
			$theme_class = ' contactpanda-use-theme-styles contactpanda-theme-' . sanitize_html_class( (string) $general['theme_stylesheet'] );
		}
		if ( ! empty( $settings['business_enabled'] ) && '' === sanitize_key( $atts['type'] ) ) {
			$base = remove_query_arg( 'contactpanda_registration_type', self::current_url() );
			$customer_url = add_query_arg( 'contactpanda_registration_type', 'customer', $base ) . '#contactpanda-account-registration-form';
			$business_url = add_query_arg( 'contactpanda_registration_type', 'business', $base ) . '#contactpanda-account-registration-form';
			echo '<nav class="contactpanda-registration-types" role="tablist" aria-label="' . esc_attr__( 'Registration type', 'contactpanda' ) . '"><a role="tab" aria-selected="' . ( 'customer' === $type ? 'true' : 'false' ) . '" class="' . ( 'customer' === $type ? 'is-active' : '' ) . '" href="' . esc_url( $customer_url ) . '">' . esc_html__( 'Private customer', 'contactpanda' ) . '</a><a role="tab" aria-selected="' . ( 'business' === $type ? 'true' : 'false' ) . '" class="' . ( 'business' === $type ? 'is-active' : '' ) . '" href="' . esc_url( $business_url ) . '">' . esc_html__( 'Business customer', 'contactpanda' ) . '</a></nav>';
		}
		echo '<form class="contactpanda-registration-form' . esc_attr( $theme_class ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_register"><input type="hidden" name="registration_type" value="' . esc_attr( $type ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( self::current_url() ) . '">';
		wp_nonce_field( 'contactpanda_register', 'contactpanda_registration_nonce' );
		echo '<div class="contactpanda-registration-trap" aria-hidden="true"><label>Website<input type="text" name="contactpanda_website" tabindex="-1" autocomplete="off"></label></div>';
		self::form_section_account();
		self::form_section_person();
		if ( 'business' === $type ) self::form_section_company( $settings );
		self::form_section_address( 'billing', __( 'Billing address', 'contactpanda' ), $countries, true );
		self::form_section_shipping( $countries );
		if ( 'business' === $type ) self::form_section_role( $settings );
		self::form_section_consent( $settings );
		echo '<button class="contactpanda-registration-submit" type="submit">' . esc_html( 'business' === $type ? __( 'Submit business registration', 'contactpanda' ) : __( 'Create customer account', 'contactpanda' ) ) . '</button></form>';
		return ob_get_clean();
	}

	public static function newsletter_shortcode() {
		$settings = self::settings();
		if ( empty( $settings['newsletter_enabled'] ) ) {
			return current_user_can( 'manage_options' ) ? '<div class="contactpanda-registration-notice is-error">' . esc_html__( 'The separate ContactPanda newsletter signup is not enabled.', 'contactpanda' ) . '</div>' : '';
		}
		$replacement = apply_filters( 'contactpanda_newsletter_signup_html', '', $settings );
		if ( is_string( $replacement ) && '' !== trim( $replacement ) ) return $replacement;
		if ( ! ContactPanda_Store::standalone() ) {
			return current_user_can( 'manage_options' ) ? '<div class="contactpanda-registration-notice is-error">' . esc_html__( 'The separate ContactPanda newsletter signup is available only when the ContactPanda database is the active data source.', 'contactpanda' ) . '</div>' : '';
		}
		wp_enqueue_style( 'contactpanda-registration', CONTACTPANDA_URL . 'assets/contactpanda-registration.css', array(), CONTACTPANDA_ASSET_VERSION );
		$notice = self::consume_notice();
		ob_start();
		if ( $notice ) {
			echo self::render_front_notice( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped markup.
			if ( ! empty( $notice['confirmation_pending'] ) ) return ob_get_clean();
		}
		$general = self::general_settings();
		$theme_class = ! empty( $general['theme_styles'] ) ? ' contactpanda-use-theme-styles contactpanda-theme-' . sanitize_html_class( (string) $general['theme_stylesheet'] ) : '';
		echo '<form class="contactpanda-registration-form contactpanda-newsletter-signup' . esc_attr( $theme_class ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_newsletter_signup"><input type="hidden" name="redirect_to" value="' . esc_url( self::current_url() ) . '">';
		wp_nonce_field( 'contactpanda_newsletter_signup', 'contactpanda_newsletter_nonce' );
		echo '<div class="contactpanda-registration-trap" aria-hidden="true"><label>Website<input type="text" name="contactpanda_website" tabindex="-1" autocomplete="off"></label></div><section class="contactpanda-form-section"><h3>' . esc_html__( 'Newsletter signup', 'contactpanda' ) . '</h3><div class="contactpanda-form-grid contactpanda-newsletter-fields">';
		self::input( 'first_name', __( 'First name', 'contactpanda' ), 'text', true, 'given-name' );
		self::input( 'last_name', __( 'Last name', 'contactpanda' ), 'text', false, 'family-name' );
		echo '<label><span>' . esc_html__( 'Salutation', 'contactpanda' ) . ' <em>*</em></span><select name="salutation" required><option value="">' . esc_html__( 'Please select', 'contactpanda' ) . '</option><option value="neutral">' . esc_html__( 'Neutral', 'contactpanda' ) . '</option><option value="mr">' . esc_html__( 'Mr', 'contactpanda' ) . '</option><option value="ms">' . esc_html__( 'Ms', 'contactpanda' ) . '</option><option value="family">' . esc_html__( 'Family', 'contactpanda' ) . '</option></select></label>';
		self::input( 'email', __( 'Email address', 'contactpanda' ), 'email', true, 'email' );
		echo '</div>';
		$privacy = self::linked_label( $settings['newsletter_privacy_text'], $settings['privacy_page_id'] );
		echo '<label class="contactpanda-form-check"><input type="checkbox" name="privacy_accepted" value="1" required> <span>' . wp_kses_post( $privacy ) . '</span></label><label class="contactpanda-form-check"><input type="checkbox" name="marketing_consent" value="1" required> <span>' . esc_html( $settings['newsletter_consent_text'] ) . '</span></label></section><button class="contactpanda-registration-submit contactpanda-theme-button" type="submit">' . esc_html( $settings['newsletter_submit_label'] ) . '</button></form>';
		return ob_get_clean();
	}

	public static function handle_newsletter_signup() {
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), home_url( '/' ) ) : home_url( '/' );
		if ( ! isset( $_POST['contactpanda_newsletter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['contactpanda_newsletter_nonce'] ) ), 'contactpanda_newsletter_signup' ) ) self::redirect_notice( $redirect, 'error', __( 'The form session has expired. Please try again.', 'contactpanda' ) );
		$settings = self::settings();
		if ( empty( $settings['newsletter_enabled'] ) || ! ContactPanda_Store::standalone() || ! empty( $_POST['contactpanda_website'] ) ) self::redirect_notice( $redirect, 'error', __( 'The newsletter signup could not be processed.', 'contactpanda' ) );
		if ( ! self::rate_limit_ok( 'newsletter_signup', 5 ) ) self::redirect_notice( $redirect, 'error', __( 'Too many signup attempts. Please wait and try again.', 'contactpanda' ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$salutation = sanitize_key( wp_unslash( $_POST['salutation'] ?? '' ) );
		if ( ! in_array( $salutation, array( 'neutral', 'mr', 'ms', 'family' ), true ) ) $salutation = '';
		if ( ! is_email( $email ) || '' === $first_name || '' === $salutation || empty( $_POST['privacy_accepted'] ) || empty( $_POST['marketing_consent'] ) ) self::redirect_notice( $redirect, 'error', $settings['newsletter_error'] );
		$existing = ContactPanda_Store::contact_by_email( $email );
		if ( $existing && 'confirmed' === sanitize_key( $existing['optin_status'] ?? '' ) ) {
			ContactPanda_Store::update_contact( absint( $existing['id'] ), array( 'first_name' => $first_name, 'last_name' => $last_name, 'meta' => array( 'anrede' => $salutation ) ) );
			ContactPanda_Store::add_activity( absint( $existing['id'] ), 0, 'newsletter_signup', __( 'Newsletter signup recognized', 'contactpanda' ), __( 'A new signup was submitted, but valid newsletter consent already existed. No further double opt-in email was sent.', 'contactpanda' ) );
			self::redirect_notice( $redirect, 'success', $settings['newsletter_already_registered'] );
		}
		$management_token = self::create_pending_management(
			array(
				'kind'       => 'newsletter',
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'salutation' => $salutation,
				'redirect'   => $redirect,
				'source'     => 'newsletter_signup',
			)
		);
		$result = self::send_pending_newsletter_confirmation( $management_token );
		if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), self::pending_notice_extra( $management_token, $email, 'newsletter' ) );
		self::redirect_notice( $redirect, 'success', $settings['newsletter_success'], self::pending_notice_extra( $management_token, $email, 'newsletter' ) );
	}

	public static function request_newsletter_optin( $email, $first_name = '', $last_name = '', $redirect = '', $source = 'newsletter_signup' ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) return new WP_Error( 'contactpanda_optin_invalid', __( 'The newsletter signup could not be processed.', 'contactpanda' ) );
		$result = self::send_newsletter_confirmation(
			array(
				'email'      => $email,
				'first_name' => sanitize_text_field( $first_name ),
				'last_name'  => sanitize_text_field( $last_name ),
				'redirect'   => wp_validate_redirect( $redirect, home_url( '/' ) ),
				'source'     => sanitize_key( $source ),
			)
		);
		return is_wp_error( $result ) ? $result : true;
	}

	private static function send_newsletter_confirmation( $data, $management_key = '' ) {
		$settings = self::settings();
		$token = bin2hex( random_bytes( 32 ) );
		$verification_key = hash( 'sha256', $token );
		$payload = array(
			'email'          => sanitize_email( $data['email'] ?? '' ),
			'first_name'     => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'      => sanitize_text_field( $data['last_name'] ?? '' ),
			'salutation'     => in_array( sanitize_key( $data['salutation'] ?? '' ), array( 'neutral', 'mr', 'ms', 'family' ), true ) ? sanitize_key( $data['salutation'] ) : '',
			'redirect'       => wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ),
			'source'         => sanitize_key( $data['source'] ?? 'newsletter_signup' ),
			'management_key' => sanitize_key( $management_key ),
		);
		$verification_ttl = max( HOUR_IN_SECONDS, absint( apply_filters( 'contactpanda_newsletter_confirmation_ttl', DAY_IN_SECONDS, $payload ) ) );
		set_transient( 'contactpanda_newsletter_verify_' . $verification_key, $payload, $verification_ttl );
		$link = add_query_arg( array( 'contactpanda_newsletter_verify' => $token ), home_url( '/' ) );
		$replacements = array( '{first_name}' => esc_html( $payload['first_name'] ), '{site_name}' => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), '{confirmation_link}' => '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm newsletter subscription', 'contactpanda' ) . '</a>' );
		$subject = strtr( $settings['newsletter_subject'], $replacements );
		$message = strtr( wpautop( $settings['newsletter_message'] ), $replacements );
		if ( false === strpos( $settings['newsletter_message'], '{confirmation_link}' ) ) $message .= '<p>' . $replacements['{confirmation_link}'] . '</p>';
		$sent = class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $payload['email'], $subject, $message ) : wp_mail( $payload['email'], $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
		if ( ! $sent ) {
			delete_transient( 'contactpanda_newsletter_verify_' . $verification_key );
			return new WP_Error( 'contactpanda_optin_mail', __( 'The confirmation email could not be sent. Please try again later.', 'contactpanda' ) );
		}
		do_action( 'contactpanda_newsletter_confirmation_sent', $verification_key, $token, $payload );
		return $verification_key;
	}

	private static function handle_newsletter_verification() {
		if ( empty( $_GET['contactpanda_newsletter_verify'] ) ) return false;
		$token = sanitize_text_field( wp_unslash( $_GET['contactpanda_newsletter_verify'] ) );
		$key = 'contactpanda_newsletter_verify_' . hash( 'sha256', $token );
		$data = get_transient( $key );
		$redirect = is_array( $data ) ? wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ) : home_url( '/' );
		if ( ! is_array( $data ) || empty( $data['email'] ) ) self::redirect_notice( $redirect, 'error', __( 'The confirmation link is invalid or has expired.', 'contactpanda' ) );
		delete_transient( $key );
		if ( ! empty( $data['management_key'] ) ) delete_transient( 'contactpanda_pending_confirmation_' . sanitize_key( $data['management_key'] ) );
		$source = ! empty( $data['source'] ) ? sanitize_key( $data['source'] ) . '_double_opt_in' : 'newsletter_double_opt_in';
		$contact_id = ContactPanda_Plugin::extension_confirm_marketing_optin(
			$data['email'],
			$data['first_name'] ?? '',
			$data['last_name'] ?? '',
			$source,
			__( 'Newsletter consent confirmed through double opt-in.', 'contactpanda' )
		);
		if ( is_wp_error( $contact_id ) || ! $contact_id ) self::redirect_notice( $redirect, 'error', is_wp_error( $contact_id ) ? $contact_id->get_error_message() : __( 'The newsletter subscription could not be saved.', 'contactpanda' ) );
		if ( ! empty( $data['salutation'] ) && ContactPanda_Store::standalone() ) ContactPanda_Store::update_contact( absint( $contact_id ), array( 'meta' => array( 'anrede' => sanitize_key( $data['salutation'] ) ) ) );
		self::redirect_notice( $redirect, 'success', self::settings()['newsletter_verified'] );
		return true;
	}

	public static function success_page_notice( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() || empty( $_GET['contactpanda_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque one-time display token.
			return $content;
		}
		$settings = self::settings();
		if ( ! $settings['success_page_id'] || get_queried_object_id() !== absint( $settings['success_page_id'] ) ) {
			return $content;
		}
		$notice = self::consume_notice();
		if ( ! $notice ) {
			return $content;
		}
		wp_enqueue_style( 'contactpanda-registration', CONTACTPANDA_URL . 'assets/contactpanda-registration.css', array(), CONTACTPANDA_ASSET_VERSION );
		$html = self::render_front_notice( $notice );
		return $html . $content;
	}

	private static function create_pending_management( $data ) {
		$token = bin2hex( random_bytes( 32 ) );
		$key = hash( 'sha256', $token );
		$data = is_array( $data ) ? $data : array();
		$data['kind'] = in_array( $data['kind'] ?? '', array( 'registration', 'newsletter' ), true ) ? $data['kind'] : '';
		$data['email'] = sanitize_email( $data['email'] ?? '' );
		$data['redirect'] = wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) );
		$data['created_at'] = time();
		set_transient( 'contactpanda_pending_confirmation_' . $key, $data, DAY_IN_SECONDS );
		return $token;
	}

	private static function pending_management( $token ) {
		$token = sanitize_text_field( $token );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) return false;
		$key = hash( 'sha256', $token );
		$data = get_transient( 'contactpanda_pending_confirmation_' . $key );
		if ( ! is_array( $data ) || empty( $data['kind'] ) ) return false;
		$data['_key'] = $key;
		return $data;
	}

	private static function save_pending_management( $token, $data ) {
		$key = hash( 'sha256', sanitize_text_field( $token ) );
		unset( $data['_key'] );
		return set_transient( 'contactpanda_pending_confirmation_' . $key, $data, DAY_IN_SECONDS );
	}

	private static function pending_notice_extra( $token, $email, $kind ) {
		return array(
			'confirmation_pending' => 1,
			'pending_token'        => sanitize_text_field( $token ),
			'pending_email'        => sanitize_email( $email ),
			'pending_kind'         => sanitize_key( $kind ),
		);
	}

	private static function send_pending_newsletter_confirmation( $management_token ) {
		$data = self::pending_management( $management_token );
		if ( ! $data || 'newsletter' !== $data['kind'] ) return new WP_Error( 'contactpanda_pending_invalid', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contactpanda' ) );
		if ( ! empty( $data['verification_key'] ) ) delete_transient( 'contactpanda_newsletter_verify_' . sanitize_key( $data['verification_key'] ) );
		$key = $data['_key'];
		$result = self::send_newsletter_confirmation( $data, $key );
		if ( is_wp_error( $result ) ) return $result;
		$data['verification_key'] = $result;
		self::save_pending_management( $management_token, $data );
		return true;
	}

	private static function pending_registration_user( $data ) {
		$user_id = absint( $data['user_id'] ?? 0 );
		$user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$key = $user ? sanitize_key( get_user_meta( $user_id, 'contactpanda_pending_management_key', true ) ) : '';
		$expires = $user ? absint( get_user_meta( $user_id, 'contactpanda_pending_management_expires', true ) ) : 0;
		if ( ! $user || ! $key || empty( $data['_key'] ) || ! hash_equals( $key, $data['_key'] ) || ! $expires || time() > $expires || get_user_meta( $user_id, 'contactpanda_email_verified', true ) ) {
			return new WP_Error( 'contactpanda_pending_invalid', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contactpanda' ) );
		}
		return $user;
	}

	public static function handle_pending_resend() {
		$token = isset( $_POST['pending_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pending_token'] ) ) : '';
		$data = self::pending_management( $token );
		$redirect = $data ? wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ) : home_url( '/' );
		if ( ! $data ) self::redirect_notice( $redirect, 'error', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contactpanda' ) );
		$extra = self::pending_notice_extra( $token, $data['email'] ?? '', $data['kind'] );
		if ( ! self::rate_limit_ok( 'pending_resend', 3 ) ) self::redirect_notice( $redirect, 'error', __( 'A confirmation email was requested too often. Please wait a few minutes and try again.', 'contactpanda' ), $extra );
		if ( 'registration' === $data['kind'] ) {
			$user = self::pending_registration_user( $data );
			if ( is_wp_error( $user ) ) self::redirect_notice( $redirect, 'error', $user->get_error_message() );
			$data['email'] = $user->user_email;
			self::save_pending_management( $token, $data );
			if ( ! self::issue_confirmation( $user->ID ) ) self::redirect_notice( $redirect, 'error', __( 'The confirmation email could not be sent. Please try again later.', 'contactpanda' ), self::pending_notice_extra( $token, $user->user_email, 'registration' ) );
		} else {
			$result = self::send_pending_newsletter_confirmation( $token );
			if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), $extra );
		}
		$email = 'registration' === $data['kind'] && isset( $user ) ? $user->user_email : $data['email'];
		self::redirect_notice( $redirect, 'success', __( 'A new confirmation email has been sent.', 'contactpanda' ), self::pending_notice_extra( $token, $email, $data['kind'] ) );
	}

	public static function handle_pending_email_change() {
		$token = isset( $_POST['pending_token'] ) ? sanitize_text_field( wp_unslash( $_POST['pending_token'] ) ) : '';
		$data = self::pending_management( $token );
		$redirect = $data ? wp_validate_redirect( $data['redirect'] ?? '', home_url( '/' ) ) : home_url( '/' );
		if ( ! $data ) self::redirect_notice( $redirect, 'error', __( 'This confirmation request is no longer available. Please submit the signup form again.', 'contactpanda' ) );
		$old_email = sanitize_email( $data['email'] ?? '' );
		$extra = self::pending_notice_extra( $token, $old_email, $data['kind'] );
		if ( ! self::rate_limit_ok( 'pending_email_change', 3 ) ) self::redirect_notice( $redirect, 'error', __( 'The email address was changed too often. Please wait a few minutes and try again.', 'contactpanda' ), $extra );
		$new_email = isset( $_POST['new_email'] ) ? sanitize_email( wp_unslash( $_POST['new_email'] ) ) : '';
		if ( ! is_email( $new_email ) ) self::redirect_notice( $redirect, 'error', __( 'Please enter a valid email address.', 'contactpanda' ), $extra );

		if ( 'registration' === $data['kind'] ) {
			$user = self::pending_registration_user( $data );
			if ( is_wp_error( $user ) ) self::redirect_notice( $redirect, 'error', $user->get_error_message() );
			$existing_user = email_exists( $new_email );
			if ( $existing_user && absint( $existing_user ) !== $user->ID ) self::redirect_notice( $redirect, 'error', __( 'A user account already exists for this email address.', 'contactpanda' ), $extra );
			$contact = ContactPanda_Store::standalone() ? ContactPanda_Store::contact_by_email( $user->user_email ) : false;
			$existing_contact = ContactPanda_Store::standalone() ? ContactPanda_Store::contact_by_email( $new_email, true ) : false;
			if ( $existing_contact && ( ! $contact || absint( $existing_contact['id'] ) !== absint( $contact['id'] ) ) ) self::redirect_notice( $redirect, 'error', __( 'A person with this email address already exists.', 'contactpanda' ), $extra );
			$result = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $new_email ) );
			if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), $extra );
			update_user_meta( $user->ID, 'billing_email', $new_email );
			if ( $contact ) ContactPanda_Store::update_contact( $contact['id'], array( 'email' => $new_email ) );
			$data['email'] = $new_email;
			self::save_pending_management( $token, $data );
			if ( ! self::issue_confirmation( $user->ID ) ) self::redirect_notice( $redirect, 'error', __( 'The email address was changed, but the new confirmation email could not be sent. Please request it again.', 'contactpanda' ), self::pending_notice_extra( $token, $new_email, 'registration' ) );
		} else {
			$data['email'] = $new_email;
			self::save_pending_management( $token, $data );
			$result = self::send_pending_newsletter_confirmation( $token );
			if ( is_wp_error( $result ) ) self::redirect_notice( $redirect, 'error', $result->get_error_message(), self::pending_notice_extra( $token, $new_email, 'newsletter' ) );
		}
		self::redirect_notice( $redirect, 'success', __( 'The email address was corrected and a new confirmation email has been sent.', 'contactpanda' ), self::pending_notice_extra( $token, $new_email, $data['kind'] ) );
	}

	private static function render_front_notice( $notice ) {
		$type = 'error' === ( $notice['type'] ?? '' ) ? 'error' : 'success';
		$redirect_url = ! empty( $notice['redirect_url'] ) ? esc_url( $notice['redirect_url'] ) : '';
		$delay = isset( $notice['delay'] ) ? absint( $notice['delay'] ) : 0;
		$attributes = $redirect_url ? ' data-contactpanda-redirect="' . esc_attr( $redirect_url ) . '" data-contactpanda-delay="' . esc_attr( $delay ) . '"' : '';
		$html = '<div class="contactpanda-registration-notice is-' . esc_attr( $type ) . '" role="' . ( 'error' === $type ? 'alert' : 'status' ) . '"' . $attributes . '><p class="contactpanda-notice-message">' . nl2br( esc_html( $notice['message'] ?? '' ) ) . '</p>';
		if ( ! empty( $notice['confirmation_pending'] ) && ! empty( $notice['pending_token'] ) && is_email( $notice['pending_email'] ?? '' ) ) {
			$token = sanitize_text_field( $notice['pending_token'] );
			$email = sanitize_email( $notice['pending_email'] );
			$html .= '<div class="contactpanda-confirmation-pending"><h3>' . esc_html__( 'Please confirm your email address', 'contactpanda' ) . '</h3>';
			$html .= '<p>' . sprintf( esc_html__( 'We sent the confirmation email to %s.', 'contactpanda' ), '<strong>' . esc_html( $email ) . '</strong>' ) . '</p>';
			$html .= '<p>' . esc_html__( 'If you cannot find the message, please also check your spam folder.', 'contactpanda' ) . '</p>';
			$html .= '<div class="contactpanda-pending-actions"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_pending_resend"><input type="hidden" name="pending_token" value="' . esc_attr( $token ) . '"><button type="submit" class="contactpanda-pending-button">' . esc_html__( 'Send confirmation email again', 'contactpanda' ) . '</button></form>';
			$html .= '<details class="contactpanda-pending-correction"><summary>' . esc_html__( 'Correct email address', 'contactpanda' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_pending_email"><input type="hidden" name="pending_token" value="' . esc_attr( $token ) . '"><label><span>' . esc_html__( 'Email address', 'contactpanda' ) . '</span><input type="email" name="new_email" value="' . esc_attr( $email ) . '" autocomplete="email" required></label><button type="submit" class="contactpanda-pending-button">' . esc_html__( 'Save and send again', 'contactpanda' ) . '</button></form></details></div></div>';
		}
		return $html . '</div>';
	}

	private static function form_section_account() {
		echo '<section class="contactpanda-form-section"><h3>' . esc_html__( 'User account', 'contactpanda' ) . '</h3><div class="contactpanda-form-grid">';
		self::input( 'user_login', __( 'Username', 'contactpanda' ), 'text', true, 'username' );
		self::input( 'email', __( 'Email address', 'contactpanda' ), 'email', true, 'email' );
		self::input( 'password', __( 'Password', 'contactpanda' ), 'password', true, 'new-password' );
		self::input( 'password_confirm', __( 'Repeat password', 'contactpanda' ), 'password', true, 'new-password' );
		echo '</div></section>';
	}

	private static function form_section_person() {
		echo '<section class="contactpanda-form-section"><h3>' . esc_html__( 'Name', 'contactpanda' ) . '</h3><div class="contactpanda-form-grid">';
		echo '<label><span>' . esc_html__( 'Salutation', 'contactpanda' ) . '</span><select name="salutation"><option value="">' . esc_html__( '--- Please select ---', 'contactpanda' ) . '</option><option value="neutral">' . esc_html__( 'Neutral', 'contactpanda' ) . '</option><option value="mr">' . esc_html__( 'Mr', 'contactpanda' ) . '</option><option value="ms">' . esc_html__( 'Ms', 'contactpanda' ) . '</option><option value="family">' . esc_html__( 'Family', 'contactpanda' ) . '</option></select></label>';
		self::input( 'title_prefix', __( 'Prefix title', 'contactpanda' ) );
		self::input( 'first_name', __( 'First name', 'contactpanda' ), 'text', true, 'given-name' );
		self::input( 'last_name', __( 'Last name', 'contactpanda' ), 'text', true, 'family-name' );
		self::input( 'position', __( 'Position / Job title', 'contactpanda' ), 'text', false, 'organization-title' );
		self::phone_input();
		echo '</div></section>';
	}

	private static function form_section_company( $settings ) {
		echo '<details class="contactpanda-form-section contactpanda-company-section contactpanda-inline-disclosure"><summary><span class="contactpanda-disclosure-label">' . esc_html__( 'Add company details', 'contactpanda' ) . '</span></summary><div class="contactpanda-form-grid">';
		self::input( 'company', __( 'Legal company name', 'contactpanda' ), 'text', false, 'organization' );
		self::input( 'industry', __( 'Industry', 'contactpanda' ) );
		self::input( 'website', __( 'Website', 'contactpanda' ), 'url', false, 'url' );
		self::vat_input( ! empty( $settings['require_vat'] ) );
		echo '</div></details>';
	}

	private static function form_section_address( $prefix, $title, $countries, $required ) {
		echo '<section class="contactpanda-form-section"><h3>' . esc_html( $title ) . '</h3><div class="contactpanda-form-grid">';
		self::input( $prefix . '_address_1', __( 'Street and house number', 'contactpanda' ), 'text', $required, 'address-line1' );
		self::input( $prefix . '_address_2', __( 'Address line 2', 'contactpanda' ), 'text', false, 'address-line2' );
		self::input( $prefix . '_postcode', __( 'Postal code', 'contactpanda' ), 'text', $required, 'postal-code' );
		self::input( $prefix . '_city', __( 'City', 'contactpanda' ), 'text', $required, 'address-level2' );
		self::country_select( $prefix . '_country', __( 'Country', 'contactpanda' ), $countries, $required );
		echo '</div></section>';
	}

	private static function form_section_shipping( $countries ) {
		echo '<details class="contactpanda-form-section contactpanda-shipping-section contactpanda-inline-disclosure"><summary><span class="contactpanda-disclosure-label">' . esc_html__( 'Add a separate shipping address', 'contactpanda' ) . '</span></summary><input type="hidden" name="shipping_different" value="0"><div class="contactpanda-form-grid contactpanda-shipping-fields">';
		self::input( 'shipping_address_1', __( 'Street and house number', 'contactpanda' ), 'text', false, 'shipping address-line1' );
		self::input( 'shipping_address_2', __( 'Address line 2', 'contactpanda' ), 'text', false, 'shipping address-line2' );
		self::input( 'shipping_postcode', __( 'Postal code', 'contactpanda' ), 'text', false, 'shipping postal-code' );
		self::input( 'shipping_city', __( 'City', 'contactpanda' ), 'text', false, 'shipping address-level2' );
		self::country_select( 'shipping_country', __( 'Country', 'contactpanda' ), $countries, false );
		echo '</div></details>';
	}

	private static function form_section_role( $settings ) {
		$roles = self::allowed_roles();
		$allowed = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
		if ( ! $allowed ) {
			return;
		}
		echo '<section class="contactpanda-form-section"><h3>' . esc_html__( 'Requested customer role', 'contactpanda' ) . '</h3><p class="contactpanda-form-help">' . esc_html__( 'Please select the customer group you would like to request. The role is assigned only after approval.', 'contactpanda' ) . '</p><div class="contactpanda-role-request"><label><span>' . esc_html__( 'Customer role', 'contactpanda' ) . ' <em>*</em></span><select name="requested_role" required><option value="">' . esc_html__( 'Please select', 'contactpanda' ) . '</option>';
		foreach ( $allowed as $role => $label ) {
			echo '<option value="' . esc_attr( $role ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></div></section>';
	}

	private static function form_section_consent( $settings ) {
		echo '<section class="contactpanda-form-section"><h3>' . esc_html__( 'Confirmations', 'contactpanda' ) . '</h3>';
		$privacy = self::linked_label( __( 'I have read the privacy policy.', 'contactpanda' ), $settings['privacy_page_id'] );
		echo '<label class="contactpanda-form-check"><input type="checkbox" name="privacy_accepted" value="1" required> <span>' . wp_kses_post( $privacy ) . '</span></label>';
		if ( $settings['terms_page_id'] ) {
			$terms = self::linked_label( __( 'I accept the terms and conditions.', 'contactpanda' ), $settings['terms_page_id'] );
			echo '<label class="contactpanda-form-check"><input type="checkbox" name="terms_accepted" value="1" required> <span>' . wp_kses_post( $terms ) . '</span></label>';
		}
		echo '<label class="contactpanda-form-check"><input type="checkbox" name="marketing_consent" value="1"> <span>' . esc_html__( 'I would also like to receive news and offers by email. I can withdraw this consent at any time.', 'contactpanda' ) . '</span></label>';
		echo '</section>';
	}

	private static function linked_label( $text, $page_id ) {
		if ( ! $page_id ) {
			return esc_html( $text );
		}
		return '<a href="' . esc_url( get_permalink( $page_id ) ) . '" target="_blank" rel="noopener">' . esc_html( $text ) . '</a>';
	}

	private static function input( $name, $label, $type = 'text', $required = false, $autocomplete = '' ) {
		echo '<label><span>' . esc_html( $label ) . ( $required ? ' <em>*</em>' : '' ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . ( $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '' ) . '></label>';
	}

	private static function phone_input() {
		$codes = array( 'AT' => '+43', 'DE' => '+49', 'CH' => '+41', 'IT' => '+39', 'AL' => '+355', 'AD' => '+376', 'BE' => '+32', 'BA' => '+387', 'BG' => '+359', 'DK' => '+45', 'EE' => '+372', 'FI' => '+358', 'FR' => '+33', 'FO' => '+298', 'GI' => '+350', 'GR' => '+30', 'GB' => '+44', 'IE' => '+353', 'IS' => '+354', 'XK' => '+383', 'HR' => '+385', 'LV' => '+371', 'LI' => '+423', 'LT' => '+370', 'LU' => '+352', 'MT' => '+356', 'MD' => '+373', 'MC' => '+377', 'ME' => '+382', 'NL' => '+31', 'MK' => '+389', 'NO' => '+47', 'PL' => '+48', 'PT' => '+351', 'RO' => '+40', 'RU' => '+7', 'SM' => '+378', 'SE' => '+46', 'RS' => '+381', 'SK' => '+421', 'SI' => '+386', 'ES' => '+34', 'CZ' => '+420', 'TR' => '+90', 'UA' => '+380', 'HU' => '+36', 'BY' => '+375', 'CY' => '+357' );
		$countries = self::countries();
		echo '<label class="contactpanda-registration-phone"><span>' . esc_html__( 'Phone', 'contactpanda' ) . ' <em>*</em></span><span class="contactpanda-profile-phone"><select name="phone_prefix" class="contactpanda-phone-prefix" aria-label="' . esc_attr__( 'Country calling code', 'contactpanda' ) . '">';
		foreach ( $codes as $country => $code ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( '+43', $code, false ) . '>' . esc_html( ( isset( $countries[ $country ] ) ? $countries[ $country ] : $country ) . ' ' . $code ) . '</option>';
		}
		echo '</select><input type="tel" name="phone_national" class="contactpanda-phone-national" required autocomplete="tel-national" placeholder="' . esc_attr__( 'Phone number', 'contactpanda' ) . '"></span></label>';
	}

	private static function vat_input( $required ) {
		$button_label = __( 'Validate VAT ID', 'contactpanda' );
		echo '<label class="contactpanda-vat-field"><span>' . esc_html__( 'VAT ID', 'contactpanda' ) . ( $required ? ' <em>*</em>' : '' ) . '</span><span class="contactpanda-vat-controls"><input type="text" name="vat_id" autocomplete="off"' . ( $required ? ' required' : '' ) . '><button type="button" class="contactpanda-vat-button" data-default-label="' . esc_attr( $button_label ) . '" data-success-label="' . esc_attr__( 'VAT ID successfully validated', 'contactpanda' ) . '">' . esc_html( $button_label ) . '</button></span><span class="contactpanda-vat-message" aria-live="polite"></span></label>';
	}

	private static function country_select( $name, $label, $countries, $required ) {
		echo '<label><span>' . esc_html( $label ) . ( $required ? ' <em>*</em>' : '' ) . '</span><select name="' . esc_attr( $name ) . '"' . ( $required ? ' required' : '' ) . '><option value="">' . esc_html__( 'Please select', 'contactpanda' ) . '</option>';
		foreach ( $countries as $code => $country ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $country ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( 'AT', $code, false ) . '>' . esc_html( $country ) . '</option>';
		}
		echo '</select></label>';
	}

	private static function countries() {
		if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
			return ContactPanda_Plugin::ordered_countries( WC()->countries->get_countries() );
		}
		return ContactPanda_Plugin::ordered_countries( array(
			'AT' => __( 'Austria', 'contactpanda' ),
			'DE' => __( 'Germany', 'contactpanda' ),
			'CH' => __( 'Switzerland', 'contactpanda' ),
			'IT' => __( 'Italy', 'contactpanda' ),
			'FR' => __( 'France', 'contactpanda' ),
			'NL' => __( 'Netherlands', 'contactpanda' ),
		) );
	}

	private static function normalize_vat( $vat ) {
		return strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $vat ) );
	}

	private static function valid_vat_format( $vat ) {
		$vat = self::normalize_vat( $vat );
		$eu_prefixes = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI' );
		return (bool) preg_match( '/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat ) && in_array( substr( $vat, 0, 2 ), $eu_prefixes, true );
	}

	public static function check_vat_vies( $vat ) {
		$vat = self::normalize_vat( $vat );
		if ( ! self::valid_vat_format( $vat ) ) {
			return new WP_Error( 'contactpanda_vat_format', __( 'The VAT ID does not have a valid EU format.', 'contactpanda' ) );
		}
		$cache_key = 'contactpanda_vies_' . hash( 'sha256', $vat );
		$cached = get_transient( $cache_key );
		if ( 'valid' === $cached || 'invalid' === $cached ) {
			return 'valid' === $cached;
		}
		$country = substr( $vat, 0, 2 );
		$number = substr( $vat, 2 );
		$body = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">' .
			'<soapenv:Body><urn:checkVat><urn:countryCode>' . esc_html( $country ) . '</urn:countryCode><urn:vatNumber>' . esc_html( $number ) . '</urn:vatNumber></urn:checkVat></soapenv:Body></soapenv:Envelope>';
		$response = wp_remote_post(
			'https://ec.europa.eu/taxation_customs/vies/services/checkVatService',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '' ),
				'body' => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'contactpanda_vies_unavailable', __( 'The EU VIES service is currently unavailable. The VAT ID was not evaluated.', 'contactpanda' ) );
		}
		$xml = wp_remote_retrieve_body( $response );
		if ( preg_match( '/<(?:[A-Za-z0-9_-]+:)?valid>\s*(true|false)\s*<\/(?:[A-Za-z0-9_-]+:)?valid>/i', $xml, $match ) ) {
			$is_valid = 'true' === strtolower( $match[1] );
			set_transient( $cache_key, $is_valid ? 'valid' : 'invalid', 5 * MINUTE_IN_SECONDS );
			return $is_valid;
		}
		return new WP_Error( 'contactpanda_vies_response', __( 'EU VIES did not return a usable response.', 'contactpanda' ) );
	}

	public static function validate_vat_ajax() {
		check_ajax_referer( 'contactpanda_registration_validate_vat', 'nonce' );
		if ( ! self::rate_limit_ok( 'vat', 10 ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many VAT ID checks. Please wait and try again.', 'contactpanda' ) ), 429 );
		}
		$vat = isset( $_POST['value'] ) ? self::normalize_vat( wp_unslash( $_POST['value'] ) ) : '';
		$result = self::check_vat_vies( $vat );
		if ( is_wp_error( $result ) ) {
			$status = 'contactpanda_vat_format' === $result->get_error_code() ? 400 : 503;
			wp_send_json_error( array( 'message' => $result->get_error_message(), 'status' => 'unavailable' ), $status );
		}
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'The VAT ID is not valid according to EU VIES.', 'contactpanda' ), 'status' => 'invalid' ), 422 );
		}
		wp_send_json_success( array( 'message' => __( 'The VAT ID is valid according to EU VIES.', 'contactpanda' ), 'status' => 'valid' ) );
	}

	public static function handle_registration() {
		if ( ! self::customer_registration_enabled() ) {
			wp_die( esc_html__( 'Customer account registration requires ContactPanda Plus.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), home_url( '/' ) ) : home_url( '/' );
		if ( ! isset( $_POST['contactpanda_registration_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['contactpanda_registration_nonce'] ) ), 'contactpanda_register' ) ) {
			self::redirect_notice( $redirect, 'error', __( 'The form session has expired. Please try again.', 'contactpanda' ) );
		}
		$settings = self::settings();
		$type = isset( $_POST['registration_type'] ) && 'business' === sanitize_key( wp_unslash( $_POST['registration_type'] ) ) ? 'business' : 'customer';
		if ( empty( $settings['enabled'] ) || ( 'business' === $type && empty( $settings['business_enabled'] ) ) || ! empty( $_POST['contactpanda_website'] ) ) {
			self::redirect_notice( $redirect, 'error', __( 'The registration could not be processed.', 'contactpanda' ) );
		}
		if ( ! self::rate_limit_ok() ) {
			self::redirect_notice( $redirect, 'error', __( 'Too many registration attempts. Please wait and try again.', 'contactpanda' ) );
		}

		$data = self::registration_data();
		$data['registration_type'] = $type;
		$errors = self::validate_registration( $data, $settings );
		$vat_verified = false;
		if ( ! $errors && '' !== $data['vat_id'] ) {
			$vat_result = self::check_vat_vies( $data['vat_id'] );
			if ( is_wp_error( $vat_result ) ) {
				$errors[] = $vat_result->get_error_message();
			} elseif ( ! $vat_result ) {
				$errors[] = __( 'The VAT ID is not valid according to EU VIES.', 'contactpanda' );
			} else {
				$vat_verified = true;
			}
		}
		if ( $errors ) {
			self::redirect_notice( $redirect, 'error', implode( ' ', $errors ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => $data['user_login'],
				'user_email'   => $data['email'],
				'user_pass'    => $data['password'],
				'first_name'   => $data['first_name'],
				'last_name'    => $data['last_name'],
				'display_name' => trim( $data['first_name'] . ' ' . $data['last_name'] ),
				'role'         => 'business' === $type ? 'subscriber' : ( get_role( 'customer' ) ? 'customer' : 'subscriber' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			self::redirect_notice( $redirect, 'error', $user_id->get_error_message() );
		}

		self::save_profile( $user_id, $data, $settings );
		$stored = false;
		if ( ContactPanda_Store::standalone() ) {
			$stored = ContactPanda_Store::sync_registration( $user_id, $data );
			if ( is_wp_error( $stored ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user_id );
				self::redirect_notice( $redirect, 'error', $stored->get_error_message() );
			}
			if ( $stored ) ContactPanda_Store::learn_salutation( $data['first_name'], $data['salutation'], 'confirmed' );
		}
		if ( $vat_verified ) {
			update_user_meta( $user_id, 'contactpanda_vat_verified_hash', hash( 'sha256', $data['vat_id'] ) );
			update_user_meta( $user_id, 'contactpanda_vat_verified_at', current_time( 'mysql' ) );
		}
		$needs_confirmation = ! empty( $settings['require_email_verify'] ) || ! empty( $data['marketing_consent'] );
		$management_token = '';
		$confirmation_sent = true;
		if ( $needs_confirmation ) {
			$management_token = self::create_pending_management(
				array(
					'kind'     => 'registration',
					'user_id'  => $user_id,
					'email'    => $data['email'],
					'redirect' => $settings['success_page_id'] ? get_permalink( $settings['success_page_id'] ) : $redirect,
				)
			);
			$management_key = hash( 'sha256', $management_token );
			update_user_meta( $user_id, 'contactpanda_pending_management_key', $management_key );
			update_user_meta( $user_id, 'contactpanda_pending_management_expires', time() + DAY_IN_SECONDS );
			$confirmation_sent = self::issue_confirmation( $user_id );
		} else {
			update_user_meta( $user_id, 'contactpanda_email_verified', 1 );
			if ( $stored ) ContactPanda_Store::update_contact( $stored, array( 'meta' => array( 'email_confirmed' => 1, 'email_confirmed_at' => current_time( 'mysql' ) ) ) );
		}
		if ( 'business' === $type ) self::notify_admin( $user_id );
		do_action( 'business' === $type ? 'contactpanda/business_registration_created' : 'contactpanda/customer_registration_created', $user_id, $data );

		$success_url = $settings['success_page_id'] ? get_permalink( $settings['success_page_id'] ) : $redirect;
		$extra = $management_token ? self::pending_notice_extra( $management_token, $data['email'], 'registration' ) : array();
		if ( ! $confirmation_sent ) {
			self::redirect_notice( $success_url, 'error', __( 'The confirmation email could not be sent. Please try again later.', 'contactpanda' ), $extra );
		}
		self::redirect_notice( $success_url, 'success', 'business' === $type ? $settings['submission_message'] : $settings['customer_submission_message'], $extra );
	}

	private static function registration_data() {
		$text_fields = array( 'first_name', 'last_name', 'title_prefix', 'position', 'phone', 'company', 'industry', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city' );
		$data = array();
		foreach ( $text_fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$data['phone'] = self::normalize_phone_parts(
			isset( $_POST['phone_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_prefix'] ) ) : '+43',
			isset( $_POST['phone_national'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_national'] ) ) : $data['phone']
		);
		$data['email'] = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$data['user_login'] = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$data['password'] = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$data['password_confirm'] = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		$data['website'] = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$data['vat_id'] = isset( $_POST['vat_id'] ) ? self::normalize_vat( wp_unslash( $_POST['vat_id'] ) ) : '';
		$data['billing_country'] = isset( $_POST['billing_country'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['billing_country'] ) ) ) : '';
		$data['shipping_country'] = isset( $_POST['shipping_country'] ) ? strtoupper( sanitize_key( wp_unslash( $_POST['shipping_country'] ) ) ) : '';
		$data['shipping_same'] = empty( $_POST['shipping_different'] ) ? 1 : 0;
		$data['salutation'] = isset( $_POST['salutation'] ) ? sanitize_key( wp_unslash( $_POST['salutation'] ) ) : '';
		if ( ! in_array( $data['salutation'], array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) $data['salutation'] = '';
		if ( '' === $data['salutation'] && '' !== $data['first_name'] ) {
			$data['salutation'] = ContactPanda_Store::suggested_salutation( $data['first_name'] );
		}
		$data['requested_role'] = isset( $_POST['requested_role'] ) ? sanitize_key( wp_unslash( $_POST['requested_role'] ) ) : '';
		$data['privacy_accepted'] = empty( $_POST['privacy_accepted'] ) ? 0 : 1;
		$data['terms_accepted'] = empty( $_POST['terms_accepted'] ) ? 0 : 1;
		$data['marketing_consent'] = empty( $_POST['marketing_consent'] ) ? 0 : 1;
		$data['registration_type'] = isset( $_POST['registration_type'] ) && 'business' === sanitize_key( wp_unslash( $_POST['registration_type'] ) ) ? 'business' : 'customer';
		return $data;
	}

	private static function normalize_phone_parts( $prefix, $national ) {
		$allowed = array( '+43', '+49', '+41', '+39', '+355', '+376', '+32', '+387', '+359', '+45', '+372', '+358', '+33', '+298', '+350', '+30', '+44', '+353', '+354', '+383', '+385', '+371', '+423', '+370', '+352', '+356', '+373', '+377', '+382', '+31', '+389', '+47', '+48', '+351', '+40', '+7', '+378', '+46', '+381', '+421', '+386', '+34', '+420', '+90', '+380', '+36', '+375', '+357' );
		$prefix = in_array( $prefix, $allowed, true ) ? $prefix : '+43';
		$national = preg_replace( '/\D/', '', (string) $national );
		if ( '+39' !== $prefix ) {
			$national = ltrim( $national, '0' );
		}
		return $national ? $prefix . $national : '';
	}

	private static function validate_registration( $data, $settings ) {
		$errors = array();
		if ( ! $data['user_login'] || ! validate_username( $data['user_login'] ) ) {
			$errors[] = __( 'Please enter a valid username.', 'contactpanda' );
		} elseif ( username_exists( $data['user_login'] ) ) {
			$errors[] = __( 'This username is already in use.', 'contactpanda' );
		}
		if ( ! is_email( $data['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'contactpanda' );
		} elseif ( email_exists( $data['email'] ) ) {
			$errors[] = __( 'A user account already exists for this email address.', 'contactpanda' );
		}
		if ( strlen( $data['password'] ) < 10 ) {
			$errors[] = __( 'The password must contain at least 10 characters.', 'contactpanda' );
		} elseif ( ! hash_equals( $data['password'], $data['password_confirm'] ) ) {
			$errors[] = __( 'The passwords do not match.', 'contactpanda' );
		}
		if ( '' === $data['first_name'] || '' === $data['last_name'] || '' === $data['phone'] ) {
			$errors[] = __( 'Please complete all required contact details.', 'contactpanda' );
		}
		if ( '' === $data['billing_address_1'] || '' === $data['billing_postcode'] || '' === $data['billing_city'] || '' === $data['billing_country'] ) {
			$errors[] = __( 'Please complete all required billing details.', 'contactpanda' );
		}
		if ( 'business' === $data['registration_type'] && ! empty( $settings['require_vat'] ) && '' === $data['vat_id'] ) {
			$errors[] = __( 'Please enter a VAT ID.', 'contactpanda' );
		} elseif ( '' !== $data['vat_id'] && ! self::valid_vat_format( $data['vat_id'] ) ) {
			$errors[] = __( 'Please enter a formally valid EU VAT ID.', 'contactpanda' );
		}
		if ( 'business' === $data['registration_type'] ) {
			$roles = self::allowed_roles();
			$allowed_roles = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
			if ( ! $allowed_roles || ! isset( $allowed_roles[ $data['requested_role'] ] ) ) {
				$errors[] = __( 'Please select one of the available customer roles.', 'contactpanda' );
			}
		}
		if ( empty( $data['privacy_accepted'] ) ) {
			$errors[] = __( 'The privacy policy must be acknowledged.', 'contactpanda' );
		}
		if ( $settings['terms_page_id'] && empty( $data['terms_accepted'] ) ) {
			$errors[] = __( 'The terms and conditions must be accepted.', 'contactpanda' );
		}
		$countries = self::countries();
		if ( $data['billing_country'] && ! isset( $countries[ $data['billing_country'] ] ) ) {
			$errors[] = __( 'Please select a valid billing country.', 'contactpanda' );
		}
		if ( empty( $data['shipping_same'] ) ) {
			if ( '' === $data['shipping_address_1'] || '' === $data['shipping_postcode'] || '' === $data['shipping_city'] || ! isset( $countries[ $data['shipping_country'] ] ) ) {
				$errors[] = __( 'Please complete the separate shipping address.', 'contactpanda' );
			}
		}
		return $errors;
	}

	private static function save_profile( $user_id, $data, $settings ) {
		$meta = array(
			'contactpanda_salutation'           => $data['salutation'],
			'contactpanda_title_prefix'        => $data['title_prefix'],
			'contactpanda_position'            => $data['position'],
			'contactpanda_industry'            => $data['industry'],
			'contactpanda_company_website'     => $data['website'],
			'contactpanda_email_verified'      => empty( $settings['require_email_verify'] ) ? 1 : 0,
			'contactpanda_business_status'     => 'business' === $data['registration_type'] ? 'pending' : 'not_required',
			'contactpanda_registration_source' => 'business' === $data['registration_type'] ? 'contactpanda_business_registration' : 'contactpanda_customer_registration',
			'contactpanda_form_version'         => 'business' === $data['registration_type'] ? 'business-registration-3' : 'customer-registration-1',
			'contactpanda_requested_role'       => $data['requested_role'],
			'contactpanda_registered_at'       => current_time( 'mysql', true ),
			'contactpanda_privacy_accepted_at' => current_time( 'mysql', true ),
			'contactpanda_privacy_page_id'     => absint( $settings['privacy_page_id'] ),
			'billing_first_name'             => $data['first_name'],
			'billing_last_name'              => $data['last_name'],
			'billing_company'                => $data['company'],
			'billing_email'                  => $data['email'],
			'billing_phone'                  => $data['phone'],
			'billing_address_1'              => $data['billing_address_1'],
			'billing_address_2'              => $data['billing_address_2'],
			'billing_postcode'               => $data['billing_postcode'],
			'billing_city'                   => $data['billing_city'],
			'billing_country'                => $data['billing_country'],
			'billing_vat'                    => $data['vat_id'],
			'b2b_uid'                        => $data['vat_id'],
		);
		$shipping = $data['shipping_same']
			? array( 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'company' => $data['company'], 'address_1' => $data['billing_address_1'], 'address_2' => $data['billing_address_2'], 'postcode' => $data['billing_postcode'], 'city' => $data['billing_city'], 'country' => $data['billing_country'] )
			: array( 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'company' => $data['company'], 'address_1' => $data['shipping_address_1'], 'address_2' => $data['shipping_address_2'], 'postcode' => $data['shipping_postcode'], 'city' => $data['shipping_city'], 'country' => $data['shipping_country'] );
		foreach ( $shipping as $key => $value ) {
			$meta[ 'shipping_' . $key ] = $value;
		}
		if ( $settings['terms_page_id'] ) {
			$meta['contactpanda_terms_accepted_at'] = current_time( 'mysql', true );
			$meta['contactpanda_terms_page_id'] = absint( $settings['terms_page_id'] );
			$meta['contactpanda_terms_page_version'] = (string) get_post_field( 'post_modified_gmt', $settings['terms_page_id'] );
		}
		if ( $settings['privacy_page_id'] ) {
			$meta['contactpanda_privacy_page_version'] = (string) get_post_field( 'post_modified_gmt', $settings['privacy_page_id'] );
		}
		if ( $data['marketing_consent'] ) {
			$meta['contactpanda_marketing_status'] = 'pending';
			$meta['contactpanda_marketing_requested_at'] = current_time( 'mysql', true );
			$meta['contactpanda_marketing_consent_version'] = hash( 'sha256', 'business-registration-marketing-consent-1' );
		}
		foreach ( $meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
	}

	private static function issue_confirmation( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}
		$token = bin2hex( random_bytes( 32 ) );
		update_user_meta( $user_id, 'contactpanda_verification_token', hash( 'sha256', $token ) );
		update_user_meta( $user_id, 'contactpanda_verification_expires', time() + DAY_IN_SECONDS );
		$link = add_query_arg( array( 'contactpanda_verify' => $user_id, 'key' => $token ), home_url( '/' ) );
		$settings = self::settings();
		$replacements = array(
			'{first_name}' => esc_html( $user->first_name ),
			'{site_name}' => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'{confirmation_link}' => '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm email address', 'contactpanda' ) . '</a>',
		);
		$subject = strtr( $settings['confirmation_subject'], $replacements );
		$message = strtr( wpautop( $settings['confirmation_message'] ), $replacements );
		if ( false === strpos( $settings['confirmation_message'], '{confirmation_link}' ) ) {
			$message .= '<p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Confirm email address', 'contactpanda' ) . '</a></p>';
		}
		return class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $user->user_email, $subject, $message ) : wp_mail( $user->user_email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function handle_verification() {
		if ( self::handle_newsletter_verification() ) return;
		if ( ! isset( $_GET['contactpanda_verify'], $_GET['key'] ) ) {
			return;
		}
		$settings = self::settings();
		$notice_page = $settings['registration_page_id'] ? get_permalink( $settings['registration_page_id'] ) : self::virtual_registration_url();
		$user_id = absint( $_GET['contactpanda_verify'] );
		$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		$stored = (string) get_user_meta( $user_id, 'contactpanda_verification_token', true );
		$expires = absint( get_user_meta( $user_id, 'contactpanda_verification_expires', true ) );
		if ( ! $user_id || ! $stored || ! $expires || time() > $expires || ! hash_equals( $stored, hash( 'sha256', $key ) ) ) {
			self::redirect_notice( $notice_page ?: home_url( '/' ), 'error', __( 'The confirmation link is invalid or has expired.', 'contactpanda' ) );
		}
		update_user_meta( $user_id, 'contactpanda_email_verified', 1 );
		if ( ContactPanda_Store::standalone() ) {
			$user = get_user_by( 'id', $user_id );
			$contact = $user ? ContactPanda_Store::contact_by_email( $user->user_email ) : false;
			if ( $contact ) ContactPanda_Store::update_contact( $contact['id'], array( 'meta' => array( 'email_confirmed' => 1, 'email_confirmed_at' => current_time( 'mysql' ) ) ) );
		}
		delete_user_meta( $user_id, 'contactpanda_verification_token' );
		delete_user_meta( $user_id, 'contactpanda_verification_expires' );
		$management_key = sanitize_key( get_user_meta( $user_id, 'contactpanda_pending_management_key', true ) );
		if ( $management_key ) delete_transient( 'contactpanda_pending_confirmation_' . $management_key );
		delete_user_meta( $user_id, 'contactpanda_pending_management_key' );
		delete_user_meta( $user_id, 'contactpanda_pending_management_expires' );
		if ( 'pending' === get_user_meta( $user_id, 'contactpanda_marketing_status', true ) ) {
			update_user_meta( $user_id, 'contactpanda_marketing_status', 'confirmed' );
			update_user_meta( $user_id, 'contactpanda_marketing_confirmed_at', current_time( 'mysql', true ) );
			if ( ContactPanda_Store::standalone() && ! empty( $contact ) ) ContactPanda_Store::record_consent( $contact['id'], 'granted', 'registration_double_opt_in', current_time( 'Y-m-d' ), __( 'Marketing consent confirmed through the registration double opt-in.', 'contactpanda' ) );
		}
		$redirect_url = $settings['verified_page_id'] ? get_permalink( $settings['verified_page_id'] ) : '';
		$registration_source = get_user_meta( $user_id, 'contactpanda_registration_source', true );
		self::redirect_notice(
			$notice_page ?: home_url( '/' ),
			'success',
			'contactpanda_customer_registration' === $registration_source ? $settings['customer_verified_message'] : $settings['verified_message'],
			array(
				'verification_complete' => 1,
				'redirect_url' => $redirect_url,
				'delay' => absint( $settings['verified_delay'] ),
			)
		);
	}

	public static function guard_login( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
			return $user;
		}
		$source = get_user_meta( $user->ID, 'contactpanda_registration_source', true );
		if ( ! in_array( $source, array( 'contactpanda_business_registration', 'contactpanda_customer_registration' ), true ) ) {
			return $user;
		}
		if ( ! get_user_meta( $user->ID, 'contactpanda_email_verified', true ) ) {
			return new WP_Error( 'contactpanda_email_unverified', __( 'Please confirm your email address before signing in.', 'contactpanda' ) );
		}
		if ( 'contactpanda_customer_registration' === $source ) return $user;
		$status = get_user_meta( $user->ID, 'contactpanda_business_status', true );
		if ( 'pending' === $status ) {
			return new WP_Error( 'contactpanda_approval_pending', __( 'Your business account is still awaiting approval.', 'contactpanda' ) );
		}
		if ( 'rejected' === $status ) {
			return new WP_Error( 'contactpanda_registration_rejected', __( 'This business registration was not approved. Please contact us.', 'contactpanda' ) );
		}
		return $user;
	}

	public static function registration_url( $url ) {
		if ( ! self::customer_registration_enabled() ) {
			return $url;
		}
		$settings = self::settings();
		if ( ! is_multisite() && ! empty( $settings['enabled'] ) && ! empty( $settings['replace_registration'] ) ) {
			$page_url = $settings['registration_page_id'] ? get_permalink( $settings['registration_page_id'] ) : self::virtual_registration_url();
			if ( $page_url ) return $page_url;
		}
		return $url;
	}

	private static function virtual_registration_url() {
		return add_query_arg( 'contactpanda_registration', '1', home_url( '/' ) );
	}

	public static function render_virtual_registration() {
		if ( ! self::customer_registration_enabled() ) {
			return;
		}
		$virtual = isset( $_GET['contactpanda_registration'] ) ? sanitize_key( wp_unslash( $_GET['contactpanda_registration'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only endpoint.
		if ( is_admin() || '1' !== $virtual ) return;
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) || $settings['registration_page_id'] ) return;
		status_header( 200 ); nocache_headers(); get_header();
		echo '<main id="primary" class="site-main contactpanda-virtual-registration"><div class="contactpanda-virtual-registration-inner"><h1>' . esc_html__( 'Registration', 'contactpanda' ) . '</h1>' . do_shortcode( '[contactpanda_registration]' ) . '</div></main>';
		get_footer(); exit;
	}

	public static function maybe_redirect_registration() {
		if ( ! self::customer_registration_enabled() ) {
			return;
		}
		$settings = self::settings();
		if ( is_multisite() || empty( $settings['enabled'] ) || empty( $settings['replace_registration'] ) ) {
			return;
		}
		$page_url = $settings['registration_page_id'] ? get_permalink( $settings['registration_page_id'] ) : self::virtual_registration_url();
		if ( $page_url ) {
			wp_safe_redirect( $page_url );
			exit;
		}
	}

	public static function handle_decision() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		}
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'contactpanda_registration_decision_' . $user_id );
		$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$user = get_user_by( 'id', $user_id );
		$is_contactpanda_registration = $user && 'contactpanda_business_registration' === get_user_meta( $user_id, 'contactpanda_registration_source', true );
		$is_pending = $user && 'pending' === get_user_meta( $user_id, 'contactpanda_business_status', true );
		if ( ! $is_contactpanda_registration || ! $is_pending || ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings' ) );
			exit;
		}
		if ( 'approve' === $decision ) {
			if ( ! get_user_meta( $user_id, 'contactpanda_email_verified', true ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&contactpanda_email_error=1' ) );
				exit;
			}
			$settings = self::settings();
			$roles = self::allowed_roles();
			$configured_roles = array_intersect_key( $roles, array_flip( (array) $settings['allowed_roles'] ) );
			$requested_role = sanitize_key( get_user_meta( $user_id, 'contactpanda_requested_role', true ) );
			if ( ! isset( $configured_roles[ $requested_role ] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&contactpanda_role_error=1' ) );
				exit;
			}
			$user->set_role( $requested_role );
			update_user_meta( $user_id, 'contactpanda_business_status', 'approved' );
			update_user_meta( $user_id, 'contactpanda_approved_at', current_time( 'mysql', true ) );
			update_user_meta( $user_id, 'contactpanda_approved_by', get_current_user_id() );
			class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $user->user_email, __( 'Your business account has been approved', 'contactpanda' ), wpautop( __( 'Your business account and requested customer role have been approved. You can now sign in.', 'contactpanda' ) ) ) : wp_mail( $user->user_email, __( 'Your business account has been approved', 'contactpanda' ), __( 'Your business account and requested customer role have been approved. You can now sign in.', 'contactpanda' ) );
		} else {
			update_user_meta( $user_id, 'contactpanda_business_status', 'rejected' );
			update_user_meta( $user_id, 'contactpanda_rejected_at', current_time( 'mysql', true ) );
			update_user_meta( $user_id, 'contactpanda_rejected_by', get_current_user_id() );
			class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $user->user_email, __( 'Update about your business registration', 'contactpanda' ), wpautop( __( 'Your business registration could not be approved. Please contact us if you have questions.', 'contactpanda' ) ) ) : wp_mail( $user->user_email, __( 'Update about your business registration', 'contactpanda' ), __( 'Your business registration could not be approved. Please contact us if you have questions.', 'contactpanda' ) );
		}
		if ( ContactPanda_Store::standalone() ) {
			$contact = ContactPanda_Store::contact_by_email( $user->user_email );
			if ( $contact ) ContactPanda_Store::add_activity( $contact['id'], 0, 'registration', 'approve' === $decision ? __( 'Registration approved', 'contactpanda' ) : __( 'Registration rejected', 'contactpanda' ), sprintf( __( 'Edited by: %s', 'contactpanda' ), wp_get_current_user()->display_name ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&updated=1' ) );
		exit;
	}

	public static function handle_resend() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		}
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'contactpanda_registration_resend_' . $user_id );
		$user = get_user_by( 'id', $user_id );
		$is_contactpanda_registration = $user && 'contactpanda_business_registration' === get_user_meta( $user_id, 'contactpanda_registration_source', true );
		$is_pending = $user && 'pending' === get_user_meta( $user_id, 'contactpanda_business_status', true );
		if ( $is_contactpanda_registration && $is_pending && ! get_user_meta( $user_id, 'contactpanda_email_verified', true ) ) {
			self::issue_confirmation( $user_id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&updated=1' ) );
		exit;
	}

	private static function notify_admin( $user_id ) {
		$settings = self::settings();
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_email( $settings['admin_email'] ) ) {
			return;
		}
		$subject = sprintf( __( 'New business registration: %s', 'contactpanda' ), $user->display_name );
		$roles = self::allowed_roles();
		$requested_role = sanitize_key( get_user_meta( $user_id, 'contactpanda_requested_role', true ) );
		$role_label = isset( $roles[ $requested_role ] ) ? $roles[ $requested_role ] : $requested_role;
		$message = sprintf( __( 'A new business registration was submitted.

Person: %1$s
Company: %2$s
Email: %3$s
Requested role: %4$s

Review: %5$s', 'contactpanda' ), $user->display_name, get_user_meta( $user_id, 'billing_company', true ), $user->user_email, $role_label, admin_url( 'admin.php?page=contactpanda-settings' ) );
		class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $settings['admin_email'], $subject, wpautop( esc_html( $message ) ) ) : wp_mail( $settings['admin_email'], $subject, $message );
	}

	private static function rate_limit_ok( $scope = 'registration', $limit = 5 ) {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'contactpanda_' . sanitize_key( $scope ) . '_rate_' . hash( 'sha256', $address . wp_salt( 'nonce' ) );
		$count = absint( get_transient( $key ) );
		if ( $count >= absint( $limit ) ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	private static function redirect_notice( $url, $type, $message, $extra = array() ) {
		$token = wp_generate_password( 20, false, false );
		$notice = array_merge( array( 'type' => $type, 'message' => $message ), is_array( $extra ) ? $extra : array() );
		set_transient( self::NOTICE_PREFIX . $token, $notice, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'contactpanda_notice', $token, $url ) );
		exit;
	}

	private static function consume_notice() {
		if ( empty( $_GET['contactpanda_notice'] ) ) {
			return false;
		}
		$token = sanitize_key( wp_unslash( $_GET['contactpanda_notice'] ) );
		$notice = get_transient( self::NOTICE_PREFIX . $token );
		delete_transient( self::NOTICE_PREFIX . $token );
		return is_array( $notice ) ? $notice : false;
	}

	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return esc_url_raw( $scheme . $host . $uri );
	}
}
