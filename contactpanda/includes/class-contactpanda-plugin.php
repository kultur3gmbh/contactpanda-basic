<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Plugin {
	public static function boot() {
		load_plugin_textdomain( 'contactpanda', false, dirname( plugin_basename( CONTACTPANDA_FILE ) ) . '/languages' );
		add_filter( 'gettext_contactpanda', array( __CLASS__, 'prevent_empty_translation' ), 10, 3 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'submenu_order' ), 999 );
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'menu_assets' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_news_dashboard_widget' ) );
		add_filter( 'manage_contactpanda_page_contactpanda-people_columns', array( __CLASS__, 'people_screen_columns' ) );
		add_filter( 'manage_contactpanda_page_contactpanda-firmen_columns', array( __CLASS__, 'company_screen_columns' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'disable_groundhogg_email_detector' ), 999 );
		add_action( 'wp_ajax_contactpanda_update_company_field', array( __CLASS__, 'update_company_field' ) );
		add_action( 'wp_ajax_contactpanda_save_hidden_columns', array( __CLASS__, 'save_hidden_columns' ) );
		add_action( 'wp_ajax_contactpanda_update_contact_field', array( __CLASS__, 'update_contact_field' ) );
		add_action( 'wp_ajax_contactpanda_save_contact_channel', array( __CLASS__, 'save_contact_channel' ) );
		add_action( 'wp_ajax_contactpanda_delete_contact_channel', array( __CLASS__, 'delete_contact_channel' ) );
		add_action( 'wp_ajax_contactpanda_update_marketing_consent', array( __CLASS__, 'update_marketing_consent' ) );
		add_action( 'wp_ajax_contactpanda_bulk_marketing_consent', array( __CLASS__, 'bulk_marketing_consent' ) );
		add_action( 'wp_ajax_contactpanda_create_contact', array( __CLASS__, 'create_contact' ) );
		add_action( 'wp_ajax_contactpanda_create_company', array( __CLASS__, 'create_company' ) );
		add_action( 'wp_ajax_contactpanda_manage_contact_tag', array( __CLASS__, 'manage_contact_tag' ) );
		add_action( 'wp_ajax_contactpanda_preview_broadcast', array( __CLASS__, 'preview_broadcast' ) );
		add_action( 'wp_ajax_contactpanda_preview_order', array( __CLASS__, 'preview_order' ) );
		add_action( 'wp_ajax_contactpanda_update_user_roles', array( __CLASS__, 'update_user_roles' ) );
		add_action( 'wp_ajax_contactpanda_create_user_account', array( __CLASS__, 'create_user_account' ) );
		add_action( 'wp_ajax_contactpanda_update_customer_address', array( __CLASS__, 'update_customer_address' ) );
		add_action( 'wp_ajax_contactpanda_validate_vat', array( __CLASS__, 'validate_vat' ) );
		add_action( 'admin_post_contactpanda_tag_action', array( __CLASS__, 'handle_tag_action' ) );
		add_action( 'admin_post_contactpanda_export_contacts', array( __CLASS__, 'export_contacts' ) );
		add_action( 'admin_post_contactpanda_export_contacts_vcard', array( __CLASS__, 'export_contacts_vcard' ) );
		add_action( 'admin_post_contactpanda_print_contacts', array( __CLASS__, 'print_contacts' ) );
		add_action( 'admin_post_contactpanda_assign_missing_salutations', array( __CLASS__, 'assign_missing_salutations' ) );
		add_action( 'admin_post_contactpanda_add_contact_note', array( __CLASS__, 'handle_add_contact_note' ) );
		add_action( 'admin_post_contactpanda_contact_company', array( __CLASS__, 'handle_contact_company' ) );
		add_filter( 'contactpanda_misc_settings_sections', array( __CLASS__, 'misc_settings_sections' ) );
		add_action( 'contactpanda_render_misc_settings_section_tags', array( __CLASS__, 'render_tags_settings' ) );
		add_action( 'panda_seasons_contact_registered', array( __CLASS__, 'handle_extension_contact_registered' ), 10, 3 );
	}

	/**
	 * Never let a malformed language catalog remove controls or structural markup.
	 *
	 * Older development catalogs could contain a translated entry followed by an
	 * empty duplicate. WordPress then returned an empty string for that message.
	 * Falling back to the English source is preferable to a broken administrator
	 * screen and also protects installations with a stale compiled catalog.
	 */
	public static function prevent_empty_translation( $translated, $original, $domain ) {
		if ( 'contactpanda' === $domain && '' === trim( (string) $translated ) && '' !== (string) $original ) {
			return $original;
		}
		return $translated;
	}

	public static function menu() {
		$companies_enabled = self::companies_available();
		$capability = self::access_capability();
		add_menu_page(
			'ContactPanda',
			'ContactPanda',
			$capability,
			'contactpanda',
			array( __CLASS__, 'render_dashboard' ),
			plugins_url( 'assets/images/contactpanda-admin-icon.png', CONTACTPANDA_FILE ),
			3
		);
		add_submenu_page(
			'contactpanda',
			__( 'Dashboard', 'contactpanda' ),
			__( 'Dashboard', 'contactpanda' ),
			$capability,
			'contactpanda',
			array( __CLASS__, 'render_dashboard' )
		);
		add_submenu_page(
			'contactpanda',
			__( 'People', 'contactpanda' ),
			__( 'People', 'contactpanda' ),
			$capability,
			'contactpanda-people',
			array( __CLASS__, 'render_people' )
		);
		if ( $companies_enabled ) {
			add_submenu_page(
				'contactpanda',
				__( 'Companies', 'contactpanda' ),
				__( 'Companies', 'contactpanda' ),
				$capability,
				'contactpanda-firmen',
				array( __CLASS__, 'render' )
			);
		}
	}

	public static function people_screen_columns( $columns ) {
		$columns = array(
			'email'     => __( 'Email', 'contactpanda' ),
			'phone'     => __( 'Phone', 'contactpanda' ),
		);
		if ( self::companies_available() ) {
			$columns['company'] = __( 'Company', 'contactpanda' );
		}
		$columns['orders'] = __( 'Orders', 'contactpanda' );
		if ( self::events_available() ) {
			$columns['events'] = __( 'Events', 'contactpanda' );
		}
		$columns['marketing'] = __( 'Email confirmation and marketing status', 'contactpanda' );
		return $columns;
	}

	public static function company_screen_columns( $columns ) {
		return array(
			'people'   => __( 'Assigned people', 'contactpanda' ),
			'website'  => __( 'Website', 'contactpanda' ),
			'phone'    => __( 'Phone', 'contactpanda' ),
			'location' => __( 'Location', 'contactpanda' ),
		);
	}

	public static function save_hidden_columns() {
		if ( ! current_user_can( self::access_capability() ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		check_ajax_referer( 'contactpanda_columns', 'nonce' );
		$screen = sanitize_key( wp_unslash( $_POST['screen'] ?? '' ) );
		$allowed = array( 'contactpanda_page_contactpanda-people', 'contactpanda_page_contactpanda-firmen' );
		if ( ! in_array( $screen, $allowed, true ) ) wp_send_json_error( array( 'message' => __( 'This list view is not supported.', 'contactpanda' ) ), 400 );
		$hidden = isset( $_POST['hidden'] ) && is_array( $_POST['hidden'] ) ? array_values( array_unique( array_map( 'sanitize_key', wp_unslash( $_POST['hidden'] ) ) ) ) : array();
		update_user_option( get_current_user_id(), 'manage' . $screen . 'columnshidden', $hidden, true );
		wp_send_json_success( array( 'hidden' => $hidden ) );
	}

	private static function column_class( $key, $additional = '' ) {
		$classes = array( 'column-' . sanitize_html_class( $key ) );
		if ( $additional ) {
			$classes[] = sanitize_html_class( $additional );
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $key, get_hidden_columns( $screen ), true ) ) {
			$classes[] = 'hidden';
		}
		return implode( ' ', $classes );
	}

	public static function misc_settings_sections( $sections ) {
		$sections['tags'] = __( 'Tags', 'contactpanda' );
		return $sections;
	}

	public static function render_tags_settings() {
		self::render_tags_page( true );
	}

	public static function submenu_order() {
		global $submenu;
		if ( empty( $submenu['contactpanda'] ) || ! is_array( $submenu['contactpanda'] ) ) {
			return;
		}
		$priority = array(
			'contactpanda'               => 10,
			'contactpanda-people'        => 20,
			'contactpanda-firmen'        => 30,
			'contactpanda-newsletters'   => 40,
			'contactpanda-campaigns'     => 50,
			'contactpanda-forms'         => 55,
			'contactpanda-flows'         => 60,
			'contactpanda-tags'          => 70,
			'contactpanda-segments'      => 71,
			'contactpanda-data-transfer' => 100,
			'contactpanda-migration'     => 105,
			'contactpanda-settings'      => 110,
			'contactpanda-trash'         => 120,
		);
		$indexed = array();
		foreach ( array_values( $submenu['contactpanda'] ) as $index => $item ) {
			$indexed[] = array(
				'index' => $index,
				'item'  => $item,
			);
		}
		usort(
			$indexed,
			static function ( $left, $right ) use ( $priority ) {
				$left_slug   = isset( $left['item'][2] ) ? (string) $left['item'][2] : '';
				$right_slug  = isset( $right['item'][2] ) ? (string) $right['item'][2] : '';
				$left_order  = isset( $priority[ $left_slug ] ) ? $priority[ $left_slug ] : 80;
				$right_order = isset( $priority[ $right_slug ] ) ? $priority[ $right_slug ] : 80;
				if ( $left_order === $right_order ) {
					return $left['index'] <=> $right['index'];
				}
				return $left_order <=> $right_order;
			}
		);
		$submenu['contactpanda'] = array_map(
			static function ( $entry ) {
				return $entry['item'];
			},
			$indexed
		);
	}

	/**
	 * Load the tiny menu-only stylesheet on every administrator screen so the
	 * functional pages and the administration pages stay visually separated.
	 */
	public static function menu_assets( $hook = '' ) {
		if ( ! current_user_can( self::access_capability() ) ) {
			return;
		}
		wp_enqueue_style( 'contactpanda-admin-menu', CONTACTPANDA_URL . 'assets/contactpanda-admin-menu.css', array(), CONTACTPANDA_ASSET_VERSION );
		if ( 'index.php' === $hook ) {
			wp_enqueue_style( 'contactpanda-dashboard-widget', CONTACTPANDA_URL . 'assets/contactpanda-dashboard-widget.css', array(), CONTACTPANDA_ASSET_VERSION );
		}
	}

	/** Register ContactPanda news on the regular WordPress dashboard. */
	public static function register_news_dashboard_widget() {
		if ( ! current_user_can( self::access_capability() ) ) return;
		wp_add_dashboard_widget( 'contactpanda-news', __( 'ContactPanda News', 'contactpanda' ), array( __CLASS__, 'render_news_dashboard_widget' ) );
	}

	/** Render the compact WordPress dashboard news widget. */
	public static function render_news_dashboard_widget() {
		echo '<p class="contactpanda-news-intro">' . esc_html__( 'Product news, practical tips, and important information about ContactPanda.', 'contactpanda' ) . '</p>';
		self::render_news_list( 3, true );
	}

	private static function access_capability() {
		return class_exists( 'ContactPanda_Capabilities' ) ? ContactPanda_Capabilities::for_area( 'view' ) : ( class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' );
	}

	/**
	 * Places ContactPanda directly after Avada in the first admin menu group.
	 * Paid products that own a separate top-level menu remain next to the shared
	 * ContactPanda menu instead of being mixed into its Basic and Plus pages.
	 * If Avada is not active, the family starts before the first separator.
	 *
	 * @param string[] $menu_order Registered top-level menu slugs.
	 * @return string[]
	 */
	public static function menu_order( $menu_order ) {
		if ( ! is_array( $menu_order ) ) {
			return $menu_order;
		}

		$contactpanda_slug = 'contactpanda';
		$contactpanda_key  = array_search( $contactpanda_slug, $menu_order, true );

		if ( false === $contactpanda_key ) {
			return $menu_order;
		}

		$family_slugs = array();
		foreach ( array( 'contactpanda-business' ) as $family_slug ) {
			$family_key = array_search( $family_slug, $menu_order, true );
			if ( false === $family_key ) continue;
			$family_slugs[] = $family_slug;
			unset( $menu_order[ $family_key ] );
		}
		unset( $menu_order[ $contactpanda_key ] );
		$menu_order = array_values( $menu_order );
		$avada_slug = '';

		global $menu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $menu_item ) {
				if ( ! is_array( $menu_item ) || ! isset( $menu_item[0], $menu_item[2] ) ) {
					continue;
				}

				$label = strtolower( trim( wp_strip_all_tags( (string) $menu_item[0] ) ) );
				$slug  = (string) $menu_item[2];

				if ( 'avada' === $label || 'avada' === $slug || 0 === strpos( $slug, 'avada-' ) ) {
					$avada_slug = $slug;
					break;
				}
			}
		}

		$insert_at = false;
		if ( '' !== $avada_slug ) {
			$avada_key = array_search( $avada_slug, $menu_order, true );
			if ( false !== $avada_key ) {
				$insert_at = $avada_key + 1;
			}
		}

		if ( false === $insert_at ) {
			$separator_key = array_search( 'separator1', $menu_order, true );
			$insert_at     = false !== $separator_key ? $separator_key : 1;
		}

		array_splice( $menu_order, $insert_at, 0, array_merge( array( $contactpanda_slug ), $family_slugs ) );

		return $menu_order;
	}

	public static function assets( $hook ) {
		if ( 'contactpanda_page_contactpanda-trash' === $hook ) {
			wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
			wp_enqueue_script( 'contactpanda-trash', CONTACTPANDA_URL . 'assets/contactpanda-trash.js', array(), CONTACTPANDA_ASSET_VERSION, true );
			wp_localize_script( 'contactpanda-trash', 'CONTACTPANDA_TRASH', array( 'purge' => __( 'Permanently delete “%s”? This ContactPanda record cannot be restored afterwards.', 'contactpanda' ), 'restore' => __( 'Restore “%s”?', 'contactpanda' ), 'trash' => __( 'Move “%s” to Trash?', 'contactpanda' ) ) );
			return;
		}
		if ( ! in_array( $hook, array( 'toplevel_page_contactpanda', 'contactpanda_page_contactpanda-people', 'contactpanda_page_contactpanda-firmen', 'contactpanda_page_contactpanda-tags', 'contactpanda_page_contactpanda-settings', 'contactpanda_page_contactpanda-data-transfer', 'contactpanda_page_contactpanda-migration' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.css', array(), CONTACTPANDA_ASSET_VERSION );
		wp_enqueue_script( 'contactpanda', CONTACTPANDA_URL . 'assets/contactpanda-admin.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_enqueue_script( 'contactpanda-trash', CONTACTPANDA_URL . 'assets/contactpanda-trash.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-trash', 'CONTACTPANDA_TRASH', array( 'purge' => __( 'Permanently delete “%s”? This ContactPanda record cannot be restored afterwards.', 'contactpanda' ), 'restore' => __( 'Restore “%s”?', 'contactpanda' ), 'trash' => __( 'Move “%s” to Trash?', 'contactpanda' ) ) );
		$salutation_suggestions = array();
		foreach ( ContactPanda_Store::salutations() as $row ) {
			$salutation_suggestions[ (string) $row['first_name_normalized'] ] = (string) $row['salutation'];
		}
		wp_localize_script(
			'contactpanda',
			'CONTACTPANDA_CONFIG',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'campaignUrl'    => admin_url( 'admin.php?page=contactpanda-campaigns&new_campaign=1' ),
				'nonce'          => wp_create_nonce( 'contactpanda_update_company' ),
				'columnsNonce'   => wp_create_nonce( 'contactpanda_columns' ),
				'screenId'       => ( function_exists( 'get_current_screen' ) && get_current_screen() ) ? get_current_screen()->id : '',
				'phoneCountries' => self::phone_countries(),
				'salutationSuggestions' => $salutation_suggestions,
				'salutationLabels' => array(
					'mr'      => __( 'Mr', 'contactpanda' ),
					'ms'      => __( 'Ms', 'contactpanda' ),
					'family'  => __( 'Family', 'contactpanda' ),
					'neutral' => __( 'Neutral', 'contactpanda' ),
				),
				'i18n'           => array(
					'invoice'                  => __( 'Invoice', 'contactpanda' ),
					'order'                    => __( 'Order', 'contactpanda' ),
					'event'                    => __( 'Event', 'contactpanda' ),
					'validateVat'              => __( 'Validate VAT ID', 'contactpanda' ),
					'save'                     => __( 'Save', 'contactpanda' ),
					'cancel'                   => __( 'Cancel', 'contactpanda' ),
					'close'                    => __( 'Close', 'contactpanda' ),
					'preview'                  => __( 'Preview', 'contactpanda' ),
					'openNewWindow'            => __( 'Open in new window', 'contactpanda' ),
					'noUsableResponse'         => __( 'No usable response was received.', 'contactpanda' ),
					'vatValidationFailed'      => __( 'VAT ID validation could not be completed.', 'contactpanda' ),
					'saveFailed'               => __( 'Saving failed.', 'contactpanda' ),
					'confirmWithdrawal'        => __( 'I confirm that the withdrawal or unsubscribe has actually been documented.', 'contactpanda' ),
					'confirmConsent'           => __( 'I confirm that explicit consent has been given.', 'contactpanda' ),
					'confirmBlock'             => __( 'I confirm this permanent administrator block and have documented the reason.', 'contactpanda' ),
					'confirmUnblock'           => __( 'I confirm this administrator override and understand that the address remains unconfirmed.', 'contactpanda' ),
					'onePersonSelected'        => __( '1 person selected', 'contactpanda' ),
					'peopleSelected'           => __( '%d people selected', 'contactpanda' ),
					'checkingContacts'         => __( 'Checking the selected contacts…', 'contactpanda' ),
					'enterTagName'             => __( 'Please enter a tag name.', 'contactpanda' ),
					'runningTagAction'         => __( 'Running tag action…', 'contactpanda' ),
					'selectSuggestedRole'      => __( 'Please select a suggested WordPress role.', 'contactpanda' ),
					'roleAlreadyAssigned'      => __( 'This role is already assigned.', 'contactpanda' ),
					'removeRole'               => __( 'Remove role %s', 'contactpanda' ),
					'confirmSaveRoles'         => __( 'Save the selected roles and the resulting permissions for this account?', 'contactpanda' ),
					'confirmLinkAccount'       => __( 'Link the existing account to this person?', 'contactpanda' ),
					'confirmCreateAccount'     => __( 'Create the new user account now?', 'contactpanda' ),
					'savingAddress'            => __( 'Saving address…', 'contactpanda' ),
					'countryCallingCode'       => __( 'Country calling code', 'contactpanda' ),
					'phoneNumber'              => __( 'Phone number', 'contactpanda' ),
					'invalidServerResponse'    => __( 'The server returned an invalid response.', 'contactpanda' ),
					'checkingVat'              => __( 'Checking EU VIES…', 'contactpanda' ),
					'saving'                   => __( 'Saving…', 'contactpanda' ),
					'saved'                    => __( 'Saved.', 'contactpanda' ),
					'marketingStatus'          => __( '%s', 'contactpanda' ),
					'migrationPreparing'       => __( 'Preparing migration…', 'contactpanda' ),
					'confirmDeleteChannel'     => __( 'Remove this contact detail?', 'contactpanda' ),
					'salutationSuggestion'     => __( 'Suggestion: %s', 'contactpanda' ),
					'savingConfirmed'          => __( 'Saving with confirmation…', 'contactpanda' ),
					'bulkEditFailed'           => __( 'Bulk editing failed.', 'contactpanda' ),
					'creatingPerson'           => __( 'Creating person…', 'contactpanda' ),
					'creatingCompany'          => __( 'Creating company…', 'contactpanda' ),
					'createFailed'             => __( 'Creation failed.', 'contactpanda' ),
					'tagAlreadyAssigned'       => __( 'This tag is already assigned to the person.', 'contactpanda' ),
					'removeTag'                => __( 'Remove tag from this person', 'contactpanda' ),
					'noTags'                   => __( 'No tags assigned yet.', 'contactpanda' ),
					'tagActionFailed'          => __( 'Tag action failed.', 'contactpanda' ),
					'broadcastLoadFailed'      => __( 'The broadcast could not be loaded.', 'contactpanda' ),
					'broadcast'                => __( 'Broadcast', 'contactpanda' ),
					'previewLoadFailed'        => __( 'The preview could not be loaded.', 'contactpanda' ),
					'confirmDeleteOnUninstall' => __( 'Do you really want ContactPanda-owned customer data to be permanently deleted when the plugin is uninstalled? Data from Groundhogg, WooCommerce, German Market, B2B Market, The Events Calendar, and Event Tickets will not be deleted.', 'contactpanda' ),
					'confirmResetTestData'     => __( 'Permanently delete all data from the ContactPanda database now? Groundhogg, WordPress, WooCommerce, invoices, and events will remain untouched.', 'contactpanda' ),
					'confirmDeleteTag'         => __( 'Delete the tag “%1$s”? It is currently assigned to %2$d people. The people themselves will not be deleted.', 'contactpanda' ),
					'confirmBulkDeleteTags'    => __( 'Delete the selected tags and all their contact assignments? The people themselves will not be deleted.', 'contactpanda' ),
					'exportContacts'           => __( 'Export contacts', 'contactpanda' ),
					'printContactList'         => __( 'Print contact list', 'contactpanda' ),
					'chooseOutputScope'        => __( 'Choose whether selected people or all filtered people should be used.', 'contactpanda' ),
					'confirmRollbackImport'    => __( 'Roll back this import? New records will be moved to Trash, earlier values will be restored, and later edits will be retained as conflicts.', 'contactpanda' ),
				),
			)
		);
	}

	public static function disable_groundhogg_email_detector( $hook ) {
		if ( in_array( $hook, array( 'toplevel_page_contactpanda', 'contactpanda_page_contactpanda-people', 'contactpanda_page_contactpanda-firmen', 'contactpanda_page_contactpanda-tags', 'contactpanda_page_contactpanda-trash', 'contactpanda_page_contactpanda-settings', 'contactpanda_page_contactpanda-newsletters' ), true ) ) {
			wp_dequeue_script( 'groundhogg-admin-email-detector' );
		}
	}

	public static function update_company_field() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		}
		check_ajax_referer( 'contactpanda_update_company', 'nonce' );
		if ( ! self::companies_available() ) {
			wp_send_json_error( array( 'message' => __( 'Company management is unavailable because Groundhogg Companies is not active.', 'contactpanda' ) ), 503 );
		}

		$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value      = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
		$allowed    = array( 'name', 'domain', 'phone', 'industry', 'address', 'description', 'uid', 'street', 'address_2', 'postcode', 'city', 'country' );

		if ( ! $company_id || ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field.', 'contactpanda' ) ), 400 );
		}
		if ( 'name' === $field && '' === trim( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'The company name must not be empty.', 'contactpanda' ) ), 400 );
		}
		if ( 'domain' === $field && '' !== $value ) {
			$value = esc_url_raw( $value );
			if ( ! $value ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid website address.', 'contactpanda' ) ), 400 );
			}
		}
		if ( 'phone' === $field ) {
			$value = self::normalize_phone( $value );
			if ( $value && ! self::phone_country_code( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a European country calling code.', 'contactpanda' ) ), 400 );
			}
		}
		if ( 'uid' === $field ) {
			$value = self::normalize_vat( $value );
			if ( '' !== $value && ! self::valid_vat_format( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'The VAT ID does not have a valid EU format.', 'contactpanda' ) ), 400 );
			}
			$user = self::company_primary_user( $company_id );
			if ( ! $user ) {
				wp_send_json_error( array( 'message' => __( 'The primary contact does not have a WordPress user account.', 'contactpanda' ) ), 400 );
			}
			$verified = '' !== $value && hash_equals( (string) get_user_meta( $user->ID, 'contactpanda_vat_verified_hash', true ), hash( 'sha256', $value ) );
			update_user_meta( $user->ID, 'billing_vat', $value );
			update_user_meta( $user->ID, 'b2b_uid', $value );
			if ( ! $verified ) {
				delete_user_meta( $user->ID, 'contactpanda_vat_verified_hash' );
				delete_user_meta( $user->ID, 'contactpanda_vat_verified_at' );
			}
			wp_send_json_success( array( 'value' => $value, 'display' => '' !== $value ? $value : '—', 'verified' => $verified ) );
		}

		if ( ContactPanda_Store::standalone() ) {
			$result = ContactPanda_Store::update_company( $company_id, $field, $value );
			if ( ! $result ) wp_send_json_error( array( 'message' => __( 'ContactPanda could not save the change.', 'contactpanda' ) ), 500 );
			wp_send_json_success( array( 'value' => $value, 'display' => 'phone' === $field ? self::format_phone( $value ) : ( '' !== $value ? $value : '—' ) ) );
		}

		$result = self::execute_ability(
			'groundhogg-companies/update-company',
			array( 'id' => $company_id, $field => $value )
		);
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg could not save the change.', 'contactpanda' ) ), 500 );
		}

		wp_send_json_success( array( 'value' => $value, 'display' => 'phone' === $field ? self::format_phone( $value ) : ( '' !== $value ? $value : '—' ) ) );
	}

	public static function update_contact_field() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value      = isset( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
		$core       = array( 'first_name', 'last_name', 'email' );
		$meta       = array( 'anrede', 'titel_vorangestellt', 'titel_nachgestellt', 'berufsbezeichnung', 'primary_phone' );

		if ( ! $contact_id || ( ! in_array( $field, $core, true ) && ! in_array( $field, $meta, true ) && ! in_array( $field, array( 'uid', 'company' ), true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid person field.', 'contactpanda' ) ), 400 );
		}
		if ( 'company' === $field ) {
			if ( '' === trim( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a company name.', 'contactpanda' ) ), 400 );
			}
			if ( ! self::companies_available() ) {
				wp_send_json_error( array( 'message' => __( 'Company management is unavailable because Groundhogg Companies is not active.', 'contactpanda' ) ), 503 );
			}
			$result = self::assign_contact_company( $contact_id, 0, $value, '' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
			}
			$company_map = self::contact_company_map();
			$assigned = isset( $company_map[ $contact_id ] ) ? $company_map[ $contact_id ] : array();
			$names = array_values( array_filter( array_map( static function ( $company ) { return self::value( $company, array( 'name', 'company_name', 'get_name' ) ); }, $assigned ) ) );
			wp_send_json_success( array( 'value' => $value, 'display' => $names ? implode( ', ', $names ) : $value ) );
		}
		if ( 'uid' === $field ) {
			$value = self::normalize_vat( $value );
			if ( '' !== $value && ! self::valid_vat_format( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'The VAT ID does not have a valid EU format.', 'contactpanda' ) ), 400 );
			}
			$contact = self::contact( $contact_id );
			$user = $contact ? self::contact_user( $contact ) : false;
			if ( ! $user ) {
				wp_send_json_error( array( 'message' => __( 'This person does not have a linked WordPress user account.', 'contactpanda' ) ), 400 );
			}
			$verified = '' !== $value && hash_equals( (string) get_user_meta( $user->ID, 'contactpanda_vat_verified_hash', true ), hash( 'sha256', $value ) );
			update_user_meta( $user->ID, 'billing_vat', $value );
			update_user_meta( $user->ID, 'b2b_uid', $value );
			if ( ! $verified ) {
				delete_user_meta( $user->ID, 'contactpanda_vat_verified_hash' );
				delete_user_meta( $user->ID, 'contactpanda_vat_verified_at' );
			}
			wp_send_json_success( array( 'value' => $value, 'display' => '' !== $value ? $value : '—', 'verified' => $verified ) );
		}
		if ( in_array( $field, array( 'first_name', 'last_name', 'email' ), true ) && '' === trim( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'This field must not be empty.', 'contactpanda' ) ), 400 );
		}
		if ( 'email' === $field && ! is_email( $value ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'contactpanda' ) ), 400 );
		}
		if ( 'primary_phone' === $field ) {
			$value = self::normalize_phone( $value );
			if ( $value && ! self::phone_country_code( $value ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a European country calling code.', 'contactpanda' ) ), 400 );
			}
		}
		if ( 'anrede' === $field && ! in_array( $value, array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid salutation.', 'contactpanda' ) ), 400 );
		}

		$current_contact = self::contact( $contact_id );
		$current_first_name = $current_contact ? self::value( $current_contact, array( 'first_name', 'get_first_name' ) ) : '';
		$current_salutation = $current_contact ? sanitize_key( self::meta( $current_contact, array( 'anrede', 'salutation' ) ) ) : '';
		$automatic_salutation = '';
		if ( 'first_name' === $field && '' === $current_salutation ) {
			$automatic_salutation = ContactPanda_Store::suggested_salutation( $value );
		}

		$input = array( 'id' => $contact_id );
		if ( in_array( $field, $core, true ) ) {
			$input[ $field ] = $value;
		} else {
			$input['meta'] = array( $field => $value );
		}
		if ( ContactPanda_Store::standalone() ) {
			$standalone_input = in_array( $field, $core, true ) ? array( $field => $value ) : array( 'meta' => array( $field => $value ) );
			if ( $automatic_salutation ) {
				$standalone_input['meta'] = array( 'anrede' => $automatic_salutation );
			}
			$result = ContactPanda_Store::update_contact( $contact_id, $standalone_input );
		} else {
			if ( $automatic_salutation ) {
				$input['meta'] = array( 'anrede' => $automatic_salutation );
			}
			$result = self::execute_ability( 'groundhogg/update-contact', $input );
		}
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg could not save the change.', 'contactpanda' ) ), 500 );
		}
		$library_first_name = 'first_name' === $field ? $value : $current_first_name;
		$library_salutation = 'anrede' === $field ? $value : ( $automatic_salutation ?: $current_salutation );
		if ( $library_first_name && in_array( $library_salutation, array( 'mr', 'ms', 'family', 'neutral' ), true ) ) {
			ContactPanda_Store::learn_salutation( $library_first_name, $library_salutation, 'confirmed' );
		}

		$display = 'primary_phone' === $field ? self::format_phone( $value ) : ( 'anrede' === $field ? ( $value ? self::salutation_label( $value ) : __( 'Add salutation', 'contactpanda' ) ) : ( '' !== $value ? $value : '—' ) );
		wp_send_json_success( array( 'value' => $value, 'display' => $display, 'salutation' => $automatic_salutation ) );
	}

	/** Save one email address or telephone number without changing unrelated person data. */
	public static function save_contact_channel() {
		self::guard_ajax();
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$channel_id = isset( $_POST['channel_id'] ) ? sanitize_key( wp_unslash( $_POST['channel_id'] ) ) : '';
		$type = isset( $_POST['channel_type'] ) ? sanitize_key( wp_unslash( $_POST['channel_type'] ) ) : '';
		$label = isset( $_POST['channel_label'] ) ? sanitize_key( wp_unslash( $_POST['channel_label'] ) ) : '';
		$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
		$make_primary = isset( $_POST['is_primary'] ) && '1' === (string) $_POST['is_primary'];
		$contact = $contact_id ? self::contact( $contact_id ) : false;
		$labels = self::contact_channel_labels( $type );
		if ( ! $contact || ! in_array( $type, array( 'email', 'phone' ), true ) || ! isset( $labels[ $label ] ) ) {
			wp_send_json_error( array( 'message' => __( 'The contact detail is invalid.', 'contactpanda' ) ), 400 );
		}
		if ( 'email' === $type ) {
			$value = sanitize_email( $value );
			if ( ! is_email( $value ) ) wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'contactpanda' ) ), 400 );
		} else {
			$value = self::normalize_phone( $value );
			if ( ! $value || ! self::phone_country_code( $value ) ) wp_send_json_error( array( 'message' => __( 'Please select a European country calling code.', 'contactpanda' ) ), 400 );
		}

		$channels = self::contact_channels( $contact );
		$normalized = self::normalize_contact_channel( $type, $value );
		$editing_index = null;
		foreach ( $channels as $index => $channel ) {
			if ( $channel_id && $channel_id === $channel['id'] ) $editing_index = $index;
			if ( $normalized === self::normalize_contact_channel( $channel['type'], $channel['value'] ) && ( null === $editing_index || $index !== $editing_index ) ) {
				wp_send_json_error( array( 'message' => __( 'This contact detail is already assigned to the person.', 'contactpanda' ) ), 409 );
			}
		}
		$has_type = false;
		foreach ( $channels as $channel ) if ( $type === $channel['type'] ) $has_type = true;
		if ( ! $has_type ) $make_primary = true;
		if ( null === $editing_index ) {
			$channel_id = 'cp-' . wp_generate_uuid4();
			$channels[] = array( 'id' => $channel_id, 'type' => $type, 'label' => $label, 'value' => $value, 'is_primary' => $make_primary ? 1 : 0, 'marketing_status' => 'email' === $type ? 'unconfirmed' : '' );
			$editing_index = count( $channels ) - 1;
		} else {
			$channels[ $editing_index ]['label'] = $label;
			$channels[ $editing_index ]['value'] = $value;
			if ( $make_primary ) $channels[ $editing_index ]['is_primary'] = 1;
		}
		if ( ! empty( $channels[ $editing_index ]['is_primary'] ) ) $make_primary = true;

		if ( $make_primary ) {
			foreach ( $channels as $index => $channel ) if ( $type === $channel['type'] ) $channels[ $index ]['is_primary'] = $index === $editing_index ? 1 : 0;
			$result = self::save_primary_contact_channel( $contact_id, $type, $value, $channels[ $editing_index ]['marketing_status'] ?? '' );
			if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 409 );
		}
		if ( ! self::persist_contact_channels( $contact_id, $channels ) ) {
			wp_send_json_error( array( 'message' => __( 'The contact detail could not be saved.', 'contactpanda' ) ), 500 );
		}
		$channel = $channels[ $editing_index ];
		wp_send_json_success( self::contact_channel_response( $channel ) );
	}

	/** Remove only secondary contact details. Primary values remain protected. */
	public static function delete_contact_channel() {
		self::guard_ajax();
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$channel_id = isset( $_POST['channel_id'] ) ? sanitize_key( wp_unslash( $_POST['channel_id'] ) ) : '';
		$contact = $contact_id ? self::contact( $contact_id ) : false;
		if ( ! $contact || ! $channel_id ) wp_send_json_error( array( 'message' => __( 'The contact detail is invalid.', 'contactpanda' ) ), 400 );
		$channels = self::contact_channels( $contact );
		$found = false;
		foreach ( $channels as $channel ) {
			if ( $channel_id !== $channel['id'] ) continue;
			$found = true;
			if ( ! empty( $channel['is_primary'] ) ) wp_send_json_error( array( 'message' => __( 'Choose another primary contact detail before removing this one.', 'contactpanda' ) ), 409 );
		}
		if ( ! $found ) wp_send_json_error( array( 'message' => __( 'The contact detail no longer exists.', 'contactpanda' ) ), 404 );
		$channels = array_values( array_filter( $channels, static function ( $channel ) use ( $channel_id ) { return $channel_id !== $channel['id']; } ) );
		if ( ! self::persist_contact_channels( $contact_id, $channels ) ) wp_send_json_error( array( 'message' => __( 'The contact detail could not be removed.', 'contactpanda' ) ), 500 );
		wp_send_json_success( array( 'message' => __( 'Contact detail removed.', 'contactpanda' ) ) );
	}

	private static function save_primary_contact_channel( $contact_id, $type, $value, $marketing_status = '' ) {
		$status_map = array( '1' => 'unconfirmed', '2' => 'confirmed', '3' => 'unsubscribed', '4' => 'weekly', '5' => 'monthly', '6' => 'hard_bounce', '7' => 'spam', '8' => 'complained', '9' => 'blocked' );
		$marketing_status = $status_map[ (string) $marketing_status ] ?? sanitize_key( $marketing_status );
		if ( ContactPanda_Store::standalone() ) {
			if ( 'email' === $type ) {
				$existing = ContactPanda_Store::contact_by_email( $value, true );
				if ( $existing && absint( $existing['id'] ?? 0 ) !== absint( $contact_id ) ) return new WP_Error( 'contactpanda_channel_email_exists', __( 'This email address belongs to another person.', 'contactpanda' ) );
				return ContactPanda_Store::update_contact( $contact_id, array( 'email' => $value, 'optin_status' => $marketing_status ?: 'unconfirmed' ) );
			}
			return ContactPanda_Store::update_contact( $contact_id, array( 'meta' => array( 'primary_phone' => $value ) ) );
		}
		$input = array( 'id' => $contact_id );
		if ( 'email' === $type ) {
			$input['email'] = $value;
			$input['optin_status'] = $marketing_status ?: 'unconfirmed';
		} else {
			$input['meta'] = array( 'primary_phone' => $value );
		}
		return false === self::execute_ability( 'groundhogg/update-contact', $input ) ? new WP_Error( 'contactpanda_channel_primary_failed', __( 'The primary contact detail could not be updated in the selected data source.', 'contactpanda' ) ) : true;
	}

	private static function persist_contact_channels( $contact_id, $channels ) {
		$clean = array();
		foreach ( (array) $channels as $channel ) {
			$type = sanitize_key( $channel['type'] ?? '' );
			$value = 'email' === $type ? sanitize_email( $channel['value'] ?? '' ) : self::normalize_phone( $channel['value'] ?? '' );
			$labels = self::contact_channel_labels( $type );
			$label = sanitize_key( $channel['label'] ?? '' );
			if ( ! $value || ! isset( $labels[ $label ] ) ) continue;
			$clean[] = array(
				'id'               => sanitize_key( $channel['id'] ?? ( 'cp-' . wp_generate_uuid4() ) ),
				'type'             => $type,
				'label'            => $label,
				'value'            => $value,
				'is_primary'       => empty( $channel['is_primary'] ) ? 0 : 1,
				'marketing_status' => 'email' === $type ? sanitize_key( $channel['marketing_status'] ?? 'unconfirmed' ) : '',
			);
		}
		// Exactly one primary value per channel type. The first value becomes the
		// primary automatically when an imported or older payload has none.
		foreach ( array( 'email', 'phone' ) as $type ) {
			$indexes = array();
			$primary = null;
			foreach ( $clean as $index => $channel ) {
				if ( $type !== $channel['type'] ) continue;
				$indexes[] = $index;
				if ( null === $primary && ! empty( $channel['is_primary'] ) ) $primary = $index;
			}
			if ( $indexes && null === $primary ) $primary = reset( $indexes );
			foreach ( $indexes as $index ) $clean[ $index ]['is_primary'] = $index === $primary ? 1 : 0;
		}
		$input = array( 'id' => absint( $contact_id ), 'meta' => array( 'contactpanda_contact_channels' => $clean ) );
		if ( ContactPanda_Store::standalone() ) return ContactPanda_Store::update_contact( $contact_id, array( 'meta' => $input['meta'] ) );
		return false !== self::execute_ability( 'groundhogg/update-contact', $input );
	}

	private static function contact_channel_response( $channel ) {
		$type = $channel['type'];
		$labels = self::contact_channel_labels( $type );
		$status = sanitize_key( $channel['marketing_status'] ?? '' );
		return array(
			'id'               => $channel['id'],
			'type'             => $type,
			'label'            => $channel['label'],
			'label_text'       => $labels[ $channel['label'] ] ?? __( 'Other', 'contactpanda' ),
			'value'            => $channel['value'],
			'display'          => 'phone' === $type ? self::format_phone( $channel['value'] ) : $channel['value'],
			'is_primary'       => empty( $channel['is_primary'] ) ? 0 : 1,
			'marketing_status' => $status,
			'marketing_label'  => 'email' === $type && 'confirmed' === $status ? __( 'Marketable', 'contactpanda' ) : ( 'email' === $type ? self::status_label( $status ?: 'unconfirmed' ) : '' ),
		);
	}

	private static function normalize_contact_channel( $type, $value ) {
		return 'email' === $type ? strtolower( sanitize_email( $value ) ) : preg_replace( '/\D+/', '', self::normalize_phone( $value ) );
	}

	private static function contact_channel_labels( $type ) {
		if ( 'email' === $type ) {
			return array( 'private' => __( 'Private', 'contactpanda' ), 'work' => __( 'Business', 'contactpanda' ), 'billing' => __( 'Billing', 'contactpanda' ), 'newsletter' => __( 'Newsletter', 'contactpanda' ), 'other' => __( 'Other', 'contactpanda' ) );
		}
		if ( 'phone' === $type ) {
			return array( 'mobile' => __( 'Mobile', 'contactpanda' ), 'private' => __( 'Private', 'contactpanda' ), 'work' => __( 'Business', 'contactpanda' ), 'landline' => __( 'Landline', 'contactpanda' ), 'fax' => __( 'Fax', 'contactpanda' ), 'other' => __( 'Other', 'contactpanda' ) );
		}
		return array();
	}

	/** Return saved secondary channels plus the authoritative primary values. */
	private static function contact_channels( $contact ) {
		$stored = self::raw_meta( $contact, 'contactpanda_contact_channels' );
		if ( is_string( $stored ) ) {
			$stored = maybe_unserialize( $stored );
			if ( is_string( $stored ) && '' !== $stored ) {
				$decoded = json_decode( $stored, true );
				if ( is_array( $decoded ) ) $stored = $decoded;
			}
		}
		$channels = array();
		foreach ( is_array( $stored ) ? $stored : array() as $channel ) {
			if ( ! is_array( $channel ) || ! in_array( $channel['type'] ?? '', array( 'email', 'phone' ), true ) ) continue;
			$type = sanitize_key( $channel['type'] );
			$value = 'email' === $type ? sanitize_email( $channel['value'] ?? '' ) : self::normalize_phone( $channel['value'] ?? '' );
			$label = sanitize_key( $channel['label'] ?? '' );
			if ( ! $value || ! isset( self::contact_channel_labels( $type )[ $label ] ) ) continue;
			$channels[] = array( 'id' => sanitize_key( $channel['id'] ?? ( 'cp-' . wp_generate_uuid4() ) ), 'type' => $type, 'label' => $label, 'value' => $value, 'is_primary' => 0, 'marketing_status' => 'email' === $type ? sanitize_key( $channel['marketing_status'] ?? 'unconfirmed' ) : '' );
		}
		$primary_values = array(
			'email' => sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ),
			'phone' => self::normalize_phone( self::meta( $contact, array( 'primary_phone', 'phone', 'phone_number' ) ) ),
		);
		foreach ( $primary_values as $type => $value ) {
			if ( ! $value ) continue;
			$found = false;
			foreach ( $channels as $index => $channel ) {
				if ( $type === $channel['type'] && self::normalize_contact_channel( $type, $value ) === self::normalize_contact_channel( $type, $channel['value'] ) ) {
					$channels[ $index ]['value'] = $value;
					$channels[ $index ]['is_primary'] = 1;
					if ( 'email' === $type ) $channels[ $index ]['marketing_status'] = sanitize_key( self::optin_status( $contact ) ?: 'unconfirmed' );
					$found = true;
				}
			}
			if ( ! $found ) $channels[] = array( 'id' => 'primary-' . $type, 'type' => $type, 'label' => 'email' === $type ? 'work' : 'mobile', 'value' => $value, 'is_primary' => 1, 'marketing_status' => 'email' === $type ? sanitize_key( self::optin_status( $contact ) ?: 'unconfirmed' ) : '' );
		}
		return $channels;
	}

	public static function assign_missing_salutations() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_assign_missing_salutations' );
		$apply = isset( $_POST['confirm_apply'] ) && '1' === (string) $_POST['confirm_apply'];
		$matches = array();
		foreach ( self::extension_contacts() as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			$first_name = trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) );
			$current = sanitize_key( self::meta( $contact, array( 'anrede', 'salutation' ) ) );
			$suggested = $first_name ? ContactPanda_Store::suggested_salutation( $first_name ) : '';
			if ( ! $id || ! $first_name || $current || ! $suggested ) continue;
			$matches[] = array( 'id' => $id, 'first_name' => $first_name, 'salutation' => $suggested );
		}
		if ( $apply ) {
			foreach ( $matches as $match ) {
				if ( ContactPanda_Store::standalone() ) ContactPanda_Store::update_contact( $match['id'], array( 'meta' => array( 'anrede' => $match['salutation'] ) ) );
				else self::execute_ability( 'groundhogg/update-contact', array( 'id' => $match['id'], 'meta' => array( 'anrede' => $match['salutation'] ) ) );
			}
		}
		set_transient( 'contactpanda_salutation_bulk_' . get_current_user_id(), array( 'applied' => $apply, 'count' => count( $matches ), 'sample' => array_slice( $matches, 0, 20 ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=salutations' ) ); exit;
	}

	public static function update_marketing_consent() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$decision   = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
		$source     = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$date       = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$confirmed  = isset( $_POST['confirmed'] ) && '1' === (string) $_POST['confirmed'];
		$sources    = self::marketing_consent_sources();

		if ( ! $contact_id || ! in_array( $decision, array( 'grant', 'revoke', 'block', 'unblock' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid selection.', 'contactpanda' ) ), 400 );
		}
		if ( ! $confirmed ) {
			wp_send_json_error( array( 'message' => __( 'Confirmation is required.', 'contactpanda' ) ), 400 );
		}
		if ( ! isset( $sources[ $source ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select the source of consent.', 'contactpanda' ) ), 400 );
		}
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid date.', 'contactpanda' ) ), 400 );
		}
		if ( $date > current_time( 'Y-m-d' ) ) {
			wp_send_json_error( array( 'message' => __( 'The date must not be in the future.', 'contactpanda' ) ), 400 );
		}
		if ( in_array( $decision, array( 'block', 'unblock' ), true ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators may block or unblock an email address.', 'contactpanda' ) ), 403 );
		}
		if ( in_array( $decision, array( 'block', 'unblock' ), true ) && '' === trim( $note ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a reason for this administrator override.', 'contactpanda' ) ), 400 );
		}

		$is_grant = 'grant' === $decision;
		$current_contact = self::contact( $contact_id );
		$current_status = $current_contact ? self::optin_status( $current_contact ) : '';
		$email = $current_contact ? self::value( $current_contact, array( 'email', 'get_email' ) ) : '';
		if ( in_array( $decision, array( 'block', 'unblock' ), true ) ) {
			$is_block = 'block' === $decision;
			$existing_suppression = ContactPanda_Newsletter_Schema::suppression( $email );
			if ( $existing_suppression && 'blocked' !== ( $existing_suppression['reason'] ?? '' ) ) {
				wp_send_json_error( array( 'message' => __( 'A delivery, spam, complaint or unsubscribe protection exists. It cannot be replaced or removed with the administrator block control.', 'contactpanda' ) ), 409 );
			}
			$summary = $is_block ? __( 'Email address blocked by administrator', 'contactpanda' ) : __( 'Administrator block removed', 'contactpanda' );
			$current_user = wp_get_current_user();
			$content = sprintf( __( '%1$s on %2$s. Source: %3$s. Edited by: %4$s. Reason: %5$s', 'contactpanda' ), $summary, $date, $sources[ $source ], $current_user->display_name, $note );
			if ( $is_block ) {
				ContactPanda_Newsletter_Schema::set_admin_block( $contact_id, $email, $note );
			} else {
				ContactPanda_Newsletter_Schema::clear_admin_block( $email );
			}
			if ( ContactPanda_Store::standalone() ) {
				ContactPanda_Store::update_contact( $contact_id, array( 'meta' => array( 'optin_status' => $is_block ? 'blocked' : 'unconfirmed', 'marketing_block_reason' => $is_block ? $note : '', 'marketing_block_date' => $is_block ? $date : '' ) ) );
				ContactPanda_Store::add_activity( $contact_id, 0, 'consent', $summary, $content );
			} else {
				self::execute_ability( 'groundhogg/update-contact', array( 'id' => $contact_id, 'optin_status' => $is_block ? 'blocked' : 'unconfirmed', 'meta' => array( 'marketing_block_reason' => $is_block ? $note : '', 'marketing_block_date' => $is_block ? $date : '' ) ) );
				self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $contact_id, 'summary' => $summary, 'content' => $content ) );
			}
			wp_send_json_success( array( 'status' => $is_block ? 'blocked' : 'unconfirmed', 'label' => $is_block ? __( 'Blocked', 'contactpanda' ) : __( 'Unconfirmed', 'contactpanda' ), 'message' => __( 'Marketing status and audit log were saved.', 'contactpanda' ) ) );
		}
		if ( $is_grant && in_array( $current_status, array( 6, 7, 8, 9, 'hard_bounce', 'spam', 'complained', 'blocked' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'This address is blocked because of a delivery, spam, or complaint status. The ordinary consent override cannot remove this protection.', 'contactpanda' ) ), 409 );
		}
		$meta = array(
			'marketing_consent'        => $is_grant ? 'yes' : 'no',
			'marketing_consent_source' => $sources[ $source ],
		);
		if ( $is_grant ) {
			$meta['marketing_consent_date'] = $date;
		} else {
			$meta['marketing_consent_revoked_date'] = $date;
		}
		if ( ContactPanda_Store::standalone() ) {
			$current_user = wp_get_current_user();
			$summary = $is_grant ? __( 'Marketing consent granted manually', 'contactpanda' ) : __( 'Marketing consent revoked manually', 'contactpanda' );
			$content = sprintf( __( '%1$s on %2$s. Source: %3$s. Edited by: %4$s.%5$s', 'contactpanda' ), $summary, $date, $sources[ $source ], $current_user->display_name, $note ? __( ' Note: ', 'contactpanda' ) . $note : '' );
			ContactPanda_Store::record_consent( $contact_id, $is_grant ? 'granted' : 'revoked', $sources[ $source ], $date, $content );
			if ( $is_grant && class_exists( 'ContactPanda_Newsletter_Schema' ) ) {
				ContactPanda_Newsletter_Schema::clear_unsubscribe_suppression( self::value( $current_contact, array( 'email', 'get_email' ) ) );
			}
			wp_send_json_success( array( 'status' => $is_grant ? 'confirmed' : 'unsubscribed', 'label' => $is_grant ? __( 'Marketing allowed', 'contactpanda' ) : __( 'Unsubscribed', 'contactpanda' ), 'message' => __( 'Marketing status and log were saved.', 'contactpanda' ) ) );
		}

		$result = self::execute_ability(
			'groundhogg/update-contact',
			array(
				'id'           => $contact_id,
				'optin_status' => $is_grant ? 'confirmed' : 'unsubscribed',
				'meta'         => $meta,
			)
		);
		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg could not change the marketing status.', 'contactpanda' ) ), 500 );
		}

		$current_user = wp_get_current_user();
		$summary = $is_grant ? __( 'Marketing consent granted manually', 'contactpanda' ) : __( 'Marketing consent revoked manually', 'contactpanda' );
		$content = sprintf(
			__( '%1$s on %2$s. Source: %3$s. Edited by: %4$s.%5$s', 'contactpanda' ),
			$summary,
			$date,
			$sources[ $source ],
			$current_user->display_name,
			$note ? __( ' Note: ', 'contactpanda' ) . $note : ''
		);
		$logged = self::execute_ability(
			'groundhogg/add-contact-note',
			array( 'contact_id' => $contact_id, 'summary' => $summary, 'content' => $content )
		);
		if ( $is_grant && class_exists( 'ContactPanda_Newsletter_Schema' ) ) {
			ContactPanda_Newsletter_Schema::clear_unsubscribe_suppression( self::value( $current_contact, array( 'email', 'get_email' ) ) );
		}

		wp_send_json_success(
			array(
				'status'  => $is_grant ? 'confirmed' : 'unsubscribed',
				'label'   => $is_grant ? __( 'Marketing allowed', 'contactpanda' ) : __( 'Unsubscribed', 'contactpanda' ),
				'message' => false === $logged ? __( 'Status saved; the additional log note could not be created.', 'contactpanda' ) : __( 'Marketing status and log were saved.', 'contactpanda' ),
			)
		);
	}

	public static function bulk_marketing_consent() {
		self::guard_ajax();

		$contact_ids = isset( $_POST['contact_ids'] ) ? (array) wp_unslash( $_POST['contact_ids'] ) : array();
		$contact_ids = array_values( array_unique( array_filter( array_map( 'absint', $contact_ids ) ) ) );
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$confirmed = isset( $_POST['confirmed'] ) && '1' === (string) $_POST['confirmed'];
		$sources = self::marketing_consent_sources();

		if ( ! $contact_ids || count( $contact_ids ) > 100 ) {
			wp_send_json_error( array( 'message' => __( 'Please select between 1 and 100 people.', 'contactpanda' ) ), 400 );
		}
		if ( ! $confirmed ) {
			wp_send_json_error( array( 'message' => __( 'Explicit confirmation is required.', 'contactpanda' ) ), 400 );
		}
		if ( ! isset( $sources[ $source ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select the source of consent.', 'contactpanda' ) ), 400 );
		}
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date || $date > current_time( 'Y-m-d' ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid date that is not in the future.', 'contactpanda' ) ), 400 );
		}

		$result = array( 'changed' => 0, 'already' => 0, 'protected' => 0, 'failed' => 0 );
		$current_user = wp_get_current_user();
		foreach ( $contact_ids as $contact_id ) {
			$contact = self::contact( $contact_id );
			if ( ! $contact ) {
				$result['failed']++;
				continue;
			}
			$status = self::optin_status( $contact );
			if ( self::is_marketable_status( $status ) ) {
				$result['already']++;
				continue;
			}
			if ( self::is_protected_optout_status( $status ) ) {
				$result['protected']++;
				continue;
			}

			if ( ContactPanda_Store::standalone() ) {
				$updated = ContactPanda_Store::record_consent( $contact_id, 'granted', $sources[ $source ], $date, $note );
			} else {
				$updated = self::execute_ability(
				'groundhogg/update-contact',
				array(
					'id'           => $contact_id,
					'optin_status' => 'confirmed',
					'meta'         => array(
						'marketing_consent'        => 'yes',
						'marketing_consent_source' => $sources[ $source ],
						'marketing_consent_date'   => $date,
					),
				)
				);
			}
			if ( false === $updated ) {
				$result['failed']++;
				continue;
			}

			$content = sprintf(
				__( 'Marketing consent enabled by bulk edit on %1$s. Source: %2$s. Edited by: %3$s.%4$s', 'contactpanda' ),
				$date,
				$sources[ $source ],
				$current_user->display_name,
				$note ? __( ' Note: ', 'contactpanda' ) . $note : ''
			);
			if ( ! ContactPanda_Store::standalone() ) self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $contact_id, 'summary' => __( 'Marketing consent enabled by bulk edit', 'contactpanda' ), 'content' => $content ) );
			$result['changed']++;
		}

		$message = sprintf(
			__( '%1$d changed, %2$d already marketable, %3$d skipped due to unsubscribe or blocked status, %4$d failed.', 'contactpanda' ),
			$result['changed'],
			$result['already'],
			$result['protected'],
			$result['failed']
		);
		wp_send_json_success( array_merge( $result, array( 'message' => $message ) ) );
	}

	public static function export_contacts() {
		self::guard_contact_output();
		$contacts = self::contacts_for_output();
		$all_fields = self::contact_output_fields();
		$requested = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ) ) : array();
		$columns = array_values( array_intersect( $requested, array_keys( $all_fields ) ) );
		if ( ! $columns ) {
			$columns = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'postcode', 'city', 'country' );
		}
		$rows = self::contact_output_rows( $contacts );
		$log = (array) get_option( 'contactpanda_export_log', array() );
		$user = wp_get_current_user();
		$log[] = array(
			'created_at' => current_time( 'mysql' ),
			'user'       => $user->display_name,
			'entity'     => 'people',
			'count'      => count( $rows ),
		);
		update_option( 'contactpanda_export_log', array_slice( $log, -100 ), false );
		$filename = 'contactpanda-contacts-' . current_time( 'Y-m-d' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo "\xEF\xBB\xBF";
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array_map( static function ( $key ) use ( $all_fields ) { return $all_fields[ $key ]; }, $columns ), ';' );
		foreach ( $rows as $row ) {
			fputcsv( $output, array_map( static function ( $key ) use ( $row ) { return self::csv_safe_value( isset( $row[ $key ] ) ? $row[ $key ] : '' ); }, $columns ), ';' );
		}
		fclose( $output );
		exit;
	}

	public static function export_contacts_vcard() {
		self::guard_contact_output();
		if ( ! class_exists( 'ContactPanda_Data_Tools' ) || ! ContactPanda_Data_Tools::vcard_enabled() ) {
			wp_die( esc_html__( 'vCard export requires ContactPanda Plus.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$rows = self::contact_output_rows( self::contacts_for_output() );
		$log  = (array) get_option( 'contactpanda_export_log', array() );
		$user = wp_get_current_user();
		$log[] = array( 'created_at' => current_time( 'mysql' ), 'user' => $user->display_name, 'entity' => 'people-vcard', 'count' => count( $rows ) );
		update_option( 'contactpanda_export_log', array_slice( $log, -100 ), false );
		nocache_headers();
		header( 'Content-Type: text/vcard; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="contactpanda-contacts-' . current_time( 'Y-m-d' ) . '.vcf"' );
		foreach ( $rows as $row ) {
			$first   = self::vcard_escape( $row['first_name'] ?? '' );
			$last    = self::vcard_escape( $row['last_name'] ?? '' );
			$email   = sanitize_email( $row['email'] ?? '' );
			$phone   = self::vcard_escape( $row['phone'] ?? '' );
			$company = self::vcard_escape( $row['company'] ?? '' );
			$address = array_map( array( __CLASS__, 'vcard_escape' ), array( $row['address_1'] ?? '', $row['address_2'] ?? '', $row['city'] ?? '', $row['state'] ?? '', $row['postcode'] ?? '', $row['country'] ?? '' ) );
			echo "BEGIN:VCARD\r\nVERSION:3.0\r\n";
			echo 'N:' . $last . ';' . $first . ";;;\r\n";
			echo 'FN:' . self::vcard_escape( trim( ( $row['first_name'] ?? '' ) . ' ' . ( $row['last_name'] ?? '' ) ) ) . "\r\n";
			if ( $company ) echo 'ORG:' . $company . "\r\n";
			if ( $email ) echo 'EMAIL;TYPE=INTERNET:' . $email . "\r\n";
			if ( $phone ) echo 'TEL:' . $phone . "\r\n";
			if ( array_filter( $address ) ) echo 'ADR:;' . $address[1] . ';' . $address[0] . ';' . $address[2] . ';' . $address[3] . ';' . $address[4] . ';' . $address[5] . "\r\n";
			echo "END:VCARD\r\n";
		}
		exit;
	}

	private static function vcard_escape( $value ) {
		return str_replace( array( "\\", ";", ",", "\r", "\n" ), array( "\\\\", "\\;", "\\,", '', "\\n" ), (string) $value );
	}

	public static function print_contacts() {
		self::guard_contact_output();
		$contacts = self::contacts_for_output();
		$all_fields = self::contact_output_fields();
		$requested = isset( $_POST['columns'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['columns'] ) ) : array();
		$columns = array_values( array_intersect( $requested, array_keys( $all_fields ) ) );
		if ( ! $columns ) {
			$columns = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'postcode', 'city', 'country' );
		}
		$rows = self::contact_output_rows( $contacts );
		$landscape = count( $columns ) > 6;
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '"><title>' . esc_html__( 'Contact list', 'contactpanda' ) . '</title><style>@page{size:' . ( $landscape ? 'landscape' : 'portrait' ) . ';margin:12mm}*{box-sizing:border-box}body{margin:0;color:#202830;font:12px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:18px}h1{margin:0;font-size:22px}p{margin:3px 0;color:#65717e}.print-button{padding:8px 13px;border:1px solid #8c8f94;border-radius:4px;background:#fff;cursor:pointer}table{width:100%;border-collapse:collapse;table-layout:auto}th,td{padding:7px 8px;border:1px solid #cfd5db;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#eef1f4;font-weight:700}tbody tr:nth-child(even){background:#f8f9fa}@media print{.print-button{display:none}thead{display:table-header-group}tr{break-inside:avoid}}</style></head><body><header><div><h1>' . esc_html__( 'Contact list', 'contactpanda' ) . '</h1><p>' . esc_html( sprintf( __( '%1$d people · created on %2$s', 'contactpanda' ), count( $rows ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) . '</p></div><button class="print-button" onclick="window.print()">' . esc_html__( 'Print', 'contactpanda' ) . '</button></header><table><thead><tr>';
		foreach ( $columns as $column ) echo '<th>' . esc_html( $all_fields[ $column ] ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $columns as $column ) {
				$value = isset( $row[ $column ] ) ? $row[ $column ] : '';
				if ( 'company' === $column && self::text_length( $value ) > 30 ) {
					$value = self::text_substr( $value, 0, 30 ) . '…';
				}
				echo '<td>' . esc_html( $value ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table><script>window.addEventListener("load",function(){window.print();});</script></body></html>';
		exit;
	}

	private static function guard_contact_output() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to export or print contacts.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'contactpanda_contact_output' );
	}

	private static function contacts_for_output() {
		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$customer_filter = isset( $_POST['kunden'] ) ? sanitize_key( wp_unslash( $_POST['kunden'] ) ) : 'alle';
		$segment_id = isset( $_POST['segment'] ) ? absint( $_POST['segment'] ) : 0;
		$advanced_filter = isset( $_POST['contactpanda_filter'] ) ? substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_POST['contactpanda_filter'] ) ), 0, 12000 ) : '';
		$orderby = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : '';
		$order = isset( $_POST['order'] ) && 'desc' === sanitize_key( wp_unslash( $_POST['order'] ) ) ? 'desc' : 'asc';
		$contacts = self::all_contacts( $search );
		$order_summaries = self::contact_order_summaries( $contacts );
		$event_summaries = self::events_available() ? self::contact_event_summaries( $contacts ) : array();
		if ( 'besteller' === $customer_filter ) {
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $order_summaries ) { $id = absint( self::value( $contact, array( 'id', 'ID' ) ) ); return ! empty( $order_summaries[ $id ]['count'] ); } ) );
		} elseif ( 'event-besucher' === $customer_filter ) {
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $event_summaries ) { $id = absint( self::value( $contact, array( 'id', 'ID' ) ) ); return ! empty( $event_summaries[ $id ]['count'] ); } ) );
		}
		if ( $segment_id || $advanced_filter ) $contacts = apply_filters( 'contactpanda_people_contacts', $contacts, $segment_id, $advanced_filter );
		if ( in_array( $orderby, array( 'first_name', 'last_name', 'company', 'order_total', 'event_count' ), true ) ) {
			$company_map = self::contact_company_map();
			usort( $contacts, static function ( $left, $right ) use ( $orderby, $order, $company_map, $order_summaries, $event_summaries ) { $comparison = self::compare_contacts( $left, $right, $orderby, $company_map, $order_summaries, $event_summaries ); return 'desc' === $order ? -$comparison : $comparison; } );
		}
		$scope = isset( $_POST['scope'] ) && 'selected' === sanitize_key( wp_unslash( $_POST['scope'] ) ) ? 'selected' : 'all';
		if ( 'selected' === $scope ) {
			$ids = isset( $_POST['selected_ids'] ) ? array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['selected_ids'] ) ) ) ) ) : array();
			if ( ! $ids ) wp_die( esc_html__( 'Please select at least one person.', 'contactpanda' ), '', array( 'response' => 400 ) );
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $ids ) { return in_array( absint( self::value( $contact, array( 'id', 'ID' ) ) ), $ids, true ); } ) );
		}
		return $contacts;
	}

	private static function contact_output_fields() {
		return (array) apply_filters( 'contactpanda_contact_output_fields', array(
			'first_name' => __( 'First name', 'contactpanda' ), 'last_name' => __( 'Last name', 'contactpanda' ), 'title_prefix' => __( 'Prefix title', 'contactpanda' ), 'title_suffix' => __( 'Suffix title', 'contactpanda' ),
			'email' => __( 'Email', 'contactpanda' ), 'phone' => __( 'Phone', 'contactpanda' ), 'company' => __( 'Company', 'contactpanda' ), 'position' => __( 'Position / Job title', 'contactpanda' ), 'uid' => __( 'VAT ID', 'contactpanda' ),
			'address_1' => __( 'Street and house number', 'contactpanda' ), 'address_2' => __( 'Address line 2', 'contactpanda' ), 'postcode' => __( 'Postcode', 'contactpanda' ), 'city' => __( 'Location', 'contactpanda' ), 'state' => __( 'State / region', 'contactpanda' ), 'country' => __( 'Country', 'contactpanda' ),
			'tags' => __( 'Tags', 'contactpanda' ), 'roles' => __( 'WordPress roles', 'contactpanda' ), 'optin_status' => __( 'Opt-in status', 'contactpanda' ), 'email_confirmed' => __( 'Email confirmed', 'contactpanda' ), 'marketable' => __( 'Marketable', 'contactpanda' ),
			'orders' => __( 'Orders', 'contactpanda' ), 'order_total' => __( 'Order total', 'contactpanda' ), 'events' => __( 'Events', 'contactpanda' ), 'created_at' => __( 'Date created', 'contactpanda' ),
		) );
	}

	private static function csv_safe_value( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return preg_match( '/^[=+\-@\t\r]/u', $value ) ? "'" . $value : $value;
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
	}

	private static function text_substr( $value, $start, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, $start, $length ) : substr( (string) $value, $start, $length );
	}

	private static function contact_output_rows( $contacts ) {
		$company_map = self::contact_company_map();
		$order_summaries = self::contact_order_summaries( $contacts );
		$event_summaries = self::events_available() ? self::contact_event_summaries( $contacts ) : array();
		$role_labels = self::editable_roles();
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : self::contact_countries();
		$rows = array();
		foreach ( $contacts as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			$user = self::contact_user( $contact );
			$address = self::contact_address( $contact );
			if ( ! array_filter( $address ) && $user && class_exists( 'WC_Customer' ) ) { try { $address = self::customer_address( new WC_Customer( $user->ID ), 'billing' ); } catch ( Throwable $e ) {} }
			$company = isset( $company_map[ $id ][0] ) ? self::value( $company_map[ $id ][0], array( 'name', 'company_name', 'get_name' ) ) : self::meta( $contact, array( 'company_name', 'company', 'billing_company' ) );
			if ( ! $company && $user ) $company = get_user_meta( $user->ID, 'billing_company', true );
			$uid = $user ? ( get_user_meta( $user->ID, 'billing_vat', true ) ?: get_user_meta( $user->ID, 'b2b_uid', true ) ) : self::meta( $contact, array( 'vat_id', 'uid' ) );
			$tags = is_array( $contact ) && isset( $contact['tags'] ) ? self::user_managed_tags( (array) $contact['tags'] ) : array();
			$tag_names = array(); foreach ( $tags as $tag ) { $name = self::value( $tag, array( 'name', 'tag_name', 'get_name' ) ); if ( $name ) $tag_names[] = $name; }
			$roles = array(); if ( $user ) foreach ( (array) $user->roles as $role ) $roles[] = isset( $role_labels[ $role ] ) ? $role_labels[ $role ] : $role;
			$status = self::optin_status( $contact );
			$confirmed = ContactPanda_Store::standalone() ? (bool) self::meta( $contact, array( 'email_confirmed' ) ) : in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
			$summary = isset( $order_summaries[ $id ] ) ? $order_summaries[ $id ] : self::empty_order_summary();
			$row = array(
				'first_name' => self::value( $contact, array( 'first_name', 'get_first_name' ) ), 'last_name' => self::value( $contact, array( 'last_name', 'get_last_name' ) ),
				'title_prefix' => self::meta( $contact, array( 'titel_vorangestellt', 'title_prefix' ) ), 'title_suffix' => self::meta( $contact, array( 'titel_nachgestellt', 'title_suffix' ) ),
				'email' => self::value( $contact, array( 'email', 'get_email' ) ), 'phone' => self::format_phone( self::meta( $contact, array( 'primary_phone', 'phone', 'phone_number' ) ) ), 'company' => $company, 'position' => self::meta( $contact, array( 'berufsbezeichnung', 'job_title', 'position' ) ), 'uid' => $uid,
				'address_1' => $address['address_1'] ?? '', 'address_2' => $address['address_2'] ?? '', 'postcode' => $address['postcode'] ?? '', 'city' => $address['city'] ?? '', 'state' => $address['state'] ?? '', 'country' => isset( $countries[ $address['country'] ?? '' ] ) ? $countries[ $address['country'] ] : ( $address['country'] ?? '' ),
				'tags' => implode( ', ', $tag_names ), 'roles' => implode( ', ', $roles ), 'optin_status' => self::status_label( $status ), 'email_confirmed' => $confirmed ? __( 'Yes', 'contactpanda' ) : __( 'No', 'contactpanda' ), 'marketable' => self::is_marketable_status( $status ) ? __( 'Yes', 'contactpanda' ) : __( 'No', 'contactpanda' ),
				'orders' => (string) $summary['count'], 'order_total' => function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $summary['total'], 2 ) : number_format( (float) $summary['total'], 2, '.', '' ), 'events' => (string) ( $event_summaries[ $id ]['count'] ?? $event_summaries[ $id ]['visited'] ?? 0 ), 'created_at' => self::value( $contact, array( 'created_at', 'date_created', 'get_date_created' ) ),
			);
			$rows[] = (array) apply_filters( 'contactpanda_contact_output_row', $row, $contact );
		}
		return $rows;
	}

	public static function create_contact() {
		self::guard_ajax();

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$salutation = isset( $_POST['salutation'] ) ? sanitize_key( wp_unslash( $_POST['salutation'] ) ) : '';
		if ( ! in_array( $salutation, array( '', 'mr', 'ms', 'family', 'neutral' ), true ) ) $salutation = '';
		if ( '' === $salutation && '' !== $first_name ) {
			$salutation = ContactPanda_Store::suggested_salutation( $first_name );
		}
		$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$position = isset( $_POST['company_position'] ) ? sanitize_text_field( wp_unslash( $_POST['company_position'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'contactpanda' ) ), 400 );
		}
		if ( '' === trim( $first_name . $last_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter at least a first or last name.', 'contactpanda' ) ), 400 );
		}
		if ( ContactPanda_Store::standalone() && ContactPanda_Store::contact_by_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A person with this email address already exists.', 'contactpanda' ) ), 409 );
		}
		if ( ! ContactPanda_Store::standalone() && function_exists( '\\Groundhogg\\is_email_address_in_use' ) && \Groundhogg\is_email_address_in_use( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A person with this email address already exists.', 'contactpanda' ) ), 409 );
		}

		if ( ContactPanda_Store::standalone() ) {
			$created = ContactPanda_Store::create_contact( array( 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'anrede' => $salutation, 'optin_status' => 'unconfirmed', 'source' => 'manual' ) );
			$contact_id = is_wp_error( $created ) ? 0 : absint( $created );
		} else {
			$result = self::execute_ability(
			'groundhogg/create-contact',
			array(
				'email'        => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'optin_status' => 'unconfirmed',
				'meta'         => array( 'anrede' => $salutation ),
				'expand'       => array( 'tags', 'meta' ),
			)
			);
			$contact_id = is_array( $result ) && isset( $result['contact']['id'] ) ? absint( $result['contact']['id'] ) : 0;
		}
		if ( ! $contact_id ) {
			wp_send_json_error( array( 'message' => __( 'The selected data source could not create the person.', 'contactpanda' ) ), 500 );
		}
		if ( $first_name && $salutation ) ContactPanda_Store::learn_salutation( $first_name, $salutation, 'confirmed' );

		$company_warning = '';
		if ( $company_name && self::companies_available() ) {
			$link_result = self::assign_contact_company( $contact_id, $company_id, $company_name, $position );
			if ( is_wp_error( $link_result ) ) {
				// The person already exists at this point. Report the partial result as a
				// warning instead of inviting a retry that would create a duplicate.
				$company_warning = ' ' . $link_result->get_error_message();
			}
		}
		wp_send_json_success(
			array(
				'message'  => __( 'The person was created with the status “Unconfirmed”.', 'contactpanda' ) . $company_warning,
				'redirect' => admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ),
			)
		);
	}

	public static function create_company() {
		self::guard_ajax();
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a company name.', 'contactpanda' ) ), 400 );
		}
		$id = self::create_company_record( $name );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'The company could not be created in the selected data source.', 'contactpanda' ) ), 500 );
		}
		wp_send_json_success( array( 'message' => __( 'The company was created.', 'contactpanda' ), 'redirect' => admin_url( 'admin.php?page=contactpanda-firmen&firma=' . $id ) ) );
	}

	public static function manage_contact_tag() {
		self::guard_ajax();

		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$tag_id = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;
		$tag_name = isset( $_POST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag_name'] ) ) : '';

		if ( ! in_array( $operation, array( 'add', 'remove', 'create' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid tag action.', 'contactpanda' ) ), 400 );
		}
		if ( ! $contact_id || ! self::contact( $contact_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The person was not found.', 'contactpanda' ) ), 404 );
		}

		if ( 'create' === $operation ) {
			if ( '' === $tag_name ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a tag name.', 'contactpanda' ) ), 400 );
			}
			if ( ContactPanda_Store::standalone() ) {
				$tag_id = ContactPanda_Store::create_tag( $tag_name );
			} else {
				$created = self::execute_ability( 'groundhogg/create-tag', array( 'name' => $tag_name ) );
				$tag_id = is_array( $created ) ? absint( self::value( $created, array( 'id', 'tag_id', 'ID' ) ) ) : 0;
				if ( ! $tag_id ) {
					$tag_id = self::groundhogg_create_tag( $tag_name );
				}
			}
			if ( ! $tag_id ) {
				wp_send_json_error( array( 'message' => __( 'Groundhogg could not create the tag.', 'contactpanda' ) ), 500 );
			}
			$operation = 'add';
		}

		if ( in_array( $operation, array( 'add', 'remove' ), true ) ) {
			if ( ! $tag_id ) {
				wp_send_json_error( array( 'message' => __( 'Please select a tag.', 'contactpanda' ) ), 400 );
			}
			$key = 'add' === $operation ? 'tags_add' : 'tags_remove';
			$updated = ContactPanda_Store::standalone() ? ContactPanda_Store::assign_tag( $contact_id, $tag_id, 'add' === $operation ) : self::execute_ability( 'groundhogg/update-contact', array( 'id' => $contact_id, $key => array( $tag_id ), 'expand' => array( 'tags' ) ) );
			if ( ! ContactPanda_Store::standalone() && false === $updated ) {
				$updated = self::groundhogg_assign_tag( $contact_id, $tag_id, 'add' === $operation );
			}
			if ( false === $updated ) {
				wp_send_json_error( array( 'message' => __( 'Groundhogg could not change the tag assignment.', 'contactpanda' ) ), 500 );
			}
			if ( '' === $tag_name ) foreach ( self::all_tags() as $tag ) if ( $tag_id === absint( $tag['id'] ?? 0 ) ) { $tag_name = sanitize_text_field( $tag['name'] ?? '' ); break; }
			wp_send_json_success( array( 'message' => 'add' === $operation ? __( 'Tag assigned.', 'contactpanda' ) : __( 'Tag removed from this person.', 'contactpanda' ), 'operation' => $operation, 'tag_id' => $tag_id, 'tag_name' => $tag_name ) );
		}
	}

	public static function render_tags_page( $embedded = false ) {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage tags.', 'contactpanda' ) );
		}
		$search = isset( $_GET['s'] ) && is_scalar( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$all_tags = self::all_tags();
		$counts = self::tag_assignment_counts();
		$tags = $all_tags;
		if ( '' !== $search ) {
			$tags = array_values( array_filter( $tags, static function ( $tag ) use ( $search ) { return false !== stripos( $tag['name'], $search ); } ) );
		}
		$page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$per_page = 20;
		$total = count( $tags );
		$visible_tags = array_slice( $tags, ( $page - 1 ) * $per_page, $per_page );
		$edit_id = isset( $_GET['tag_id'] ) ? absint( $_GET['tag_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$edit_tag = false;
		foreach ( $all_tags as $candidate ) {
			if ( $edit_id === absint( $candidate['id'] ) ) {
				$edit_tag = $candidate;
				break;
			}
		}
		$notice = get_transient( 'contactpanda_tag_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contactpanda_tag_notice_' . get_current_user_id() );
		}
		$tags_url = admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=tags' );
		if ( ! $embedded ) echo '<div class="wrap contactpanda-wrap contactpanda-tags-page"><header class="contactpanda-heading"><div class="contactpanda-avatar"><span class="dashicons dashicons-tag"></span></div><div><p class="contactpanda-eyebrow">ContactPanda</p><h1>' . esc_html__( 'Tags', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Create and manage tags in the currently selected data source.', 'contactpanda' ) . '</p></div></header>';
		if ( is_array( $notice ) ) {
			echo '<div class="notice notice-' . ( ! empty( $notice['success'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		echo '<div class="contactpanda-taxonomy-layout"><section class="contactpanda-card contactpanda-tag-editor"><h2>' . esc_html( $edit_tag ? __( 'Edit tag', 'contactpanda' ) : __( 'Add new tag', 'contactpanda' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-tag-create-form"><input type="hidden" name="action" value="contactpanda_tag_action"><input type="hidden" name="operation" value="' . esc_attr( $edit_tag ? 'rename' : 'create' ) . '">';
		if ( $edit_tag ) {
			echo '<input type="hidden" name="tag_id" value="' . esc_attr( $edit_tag['id'] ) . '">';
		}
		wp_nonce_field( 'contactpanda_tag_action' );
		echo '<label class="contactpanda-tag-form-field" for="contactpanda-new-tag"><span>' . esc_html__( 'Name', 'contactpanda' ) . '</span><input id="contactpanda-new-tag" class="regular-text" type="text" name="tag_name" required value="' . esc_attr( $edit_tag ? $edit_tag['name'] : '' ) . '"></label>';
		echo '<label class="contactpanda-tag-form-field" for="contactpanda-tag-description"><span>' . esc_html__( 'Description (optional)', 'contactpanda' ) . '</span><input id="contactpanda-tag-description" class="large-text" name="tag_description" value="' . esc_attr( $edit_tag['description'] ?? '' ) . '"></label><p class="submit"><button class="button button-primary">' . esc_html( $edit_tag ? __( 'Update tag', 'contactpanda' ) : __( 'Add new tag', 'contactpanda' ) ) . '</button>';
		if ( $edit_tag ) {
			echo ' <a class="button" href="' . esc_url( $tags_url ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a>';
		}
		echo '</p></form></section><section class="contactpanda-card contactpanda-tag-list"><div class="contactpanda-toolbar"><h2>' . esc_html__( 'Tags', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( $total ) . '</span></div>';
		echo '<form method="get" class="contactpanda-tag-search"><input type="hidden" name="page" value="contactpanda-settings"><input type="hidden" name="tab" value="misc"><input type="hidden" name="misc_section" value="tags"><label class="screen-reader-text" for="contactpanda-tag-search">' . esc_html__( 'Search tags', 'contactpanda' ) . '</label><input id="contactpanda-tag-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search tags', 'contactpanda' ) . '"><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form>';
		if ( ! $visible_tags ) {
			echo '<p class="contactpanda-muted">' . esc_html__( 'No matching tags were found.', 'contactpanda' ) . '</p></section></div>' . ( $embedded ? '' : '</div>' );
			return;
		}
		echo '<form id="contactpanda-tag-bulk-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-tag-bulk-form"><input type="hidden" name="action" value="contactpanda_tag_action"><input type="hidden" name="operation" value="bulk_delete"><input type="hidden" name="delete_confirmed" value="0">';
		wp_nonce_field( 'contactpanda_tag_action' );
		echo '</form><div class="contactpanda-tag-bulk-actions"><select form="contactpanda-tag-bulk-form" name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'contactpanda' ) . '</option><option value="delete">' . esc_html__( 'Delete', 'contactpanda' ) . '</option></select><button form="contactpanda-tag-bulk-form" class="button">' . esc_html__( 'Apply', 'contactpanda' ) . '</button></div>';
		$show_source = defined( 'GROUNDHOGG_VERSION' ) || class_exists( '\\Groundhogg\\Contact' );
		$source_label = ContactPanda_Store::standalone() ? 'ContactPanda' : 'Groundhogg';
		echo '<table class="widefat striped contactpanda-table contactpanda-tags-admin-table"><thead><tr><td class="check-column"><input type="checkbox" class="contactpanda-select-all-tags" aria-label="' . esc_attr__( 'Select all tags', 'contactpanda' ) . '"></td><th>' . esc_html__( 'Name', 'contactpanda' ) . '</th><th>' . esc_html__( 'Description', 'contactpanda' ) . '</th>' . ( $show_source ? '<th>' . esc_html__( 'Data source', 'contactpanda' ) . '</th>' : '' ) . '<th>' . esc_html__( 'Assigned people', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $visible_tags as $tag ) {
			$id = absint( $tag['id'] );
			$count = isset( $counts[ $id ] ) ? absint( $counts[ $id ] ) : 0;
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_tag_action&operation=delete&delete_confirmed=1&tag_id=' . $id ), 'contactpanda_tag_action' );
			$form_id = 'contactpanda-tag-edit-' . $id;
			echo '<tr class="contactpanda-list-inline-edit"><th class="check-column"><input form="contactpanda-tag-bulk-form" type="checkbox" name="tag_ids[]" value="' . esc_attr( $id ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'contactpanda' ), $tag['name'] ) ) . '"></th><td><button type="button" class="button-link contactpanda-inline-edit-open"><strong>' . esc_html( $tag['name'] ) . '</strong></button><div class="contactpanda-inline-edit-fields" hidden><input form="' . esc_attr( $form_id ) . '" type="text" name="tag_name" required value="' . esc_attr( $tag['name'] ) . '"></div><div class="row-actions"><span><button type="button" class="button-link contactpanda-inline-edit-open">' . esc_html__( 'Edit', 'contactpanda' ) . '</button> | </span><span class="delete"><a class="contactpanda-delete-tag-link" data-count="' . esc_attr( $count ) . '" data-name="' . esc_attr( $tag['name'] ) . '" href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'contactpanda' ) . '</a></span></div></td><td><span class="contactpanda-inline-edit-display">' . esc_html( $tag['description'] ?? '—' ) . '</span><div class="contactpanda-inline-edit-fields" hidden><textarea form="' . esc_attr( $form_id ) . '" name="tag_description" rows="2">' . esc_textarea( $tag['description'] ?? '' ) . '</textarea><p><button form="' . esc_attr( $form_id ) . '" class="button button-primary">' . esc_html__( 'Save', 'contactpanda' ) . '</button> <button type="button" class="button contactpanda-inline-edit-cancel">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button></p></div></td>' . ( $show_source ? '<td class="contactpanda-tag-source"><span class="contactpanda-mini-badge">' . esc_html( $source_label ) . '</span></td>' : '' ) . '<td>' . esc_html( number_format_i18n( $count ) ) . '<form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_tag_action"><input type="hidden" name="operation" value="rename"><input type="hidden" name="tag_id" value="' . esc_attr( $id ) . '">'; wp_nonce_field( 'contactpanda_tag_action' ); echo '</form></td></tr>';
		}
		echo '</tbody></table>';
		if ( $total > $per_page ) {
			$base = add_query_arg( array( 's' => $search, 'paged' => '%#%' ), $tags_url );
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => $base, 'format' => '', 'current' => $page, 'total' => (int) ceil( $total / $per_page ) ) ) ) . '</div></div>';
		}
		echo '<p class="description">' . esc_html__( 'Deleting a tag removes only the tag and its assignments. People and their other data remain unchanged.', 'contactpanda' ) . '</p></section></div>' . ( $embedded ? '' : '</div>' );
	}

	public static function handle_tag_action() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage tags.', 'contactpanda' ) );
		}
		check_admin_referer( 'contactpanda_tag_action' );
		$operation = isset( $_REQUEST['operation'] ) ? sanitize_key( wp_unslash( $_REQUEST['operation'] ) ) : '';
		$tag_id = isset( $_REQUEST['tag_id'] ) ? absint( $_REQUEST['tag_id'] ) : 0;
		$name = isset( $_REQUEST['tag_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tag_name'] ) ) : '';
		$description = isset( $_REQUEST['tag_description'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['tag_description'] ) ) : '';
		$success = false;
		$message = __( 'The tag action could not be completed.', 'contactpanda' );
		if ( 'create' === $operation && $name ) {
			$created_id = ContactPanda_Store::standalone() ? ContactPanda_Store::create_tag( $name, $description ) : self::groundhogg_create_tag( $name );
			$success = (bool) $created_id;
			if ( $success && ! ContactPanda_Store::standalone() ) self::set_tag_description( $created_id, $description );
			$message = $success ? __( 'The tag was created.', 'contactpanda' ) : __( 'The tag could not be created or already exists.', 'contactpanda' );
		} elseif ( 'rename' === $operation && $tag_id && $name ) {
			$success = ContactPanda_Store::standalone() ? ContactPanda_Store::rename_tag( $tag_id, $name, $description ) : self::groundhogg_rename_tag( $tag_id, $name );
			if ( $success && ! ContactPanda_Store::standalone() ) self::set_tag_description( $tag_id, $description );
			$message = $success ? __( 'The tag was renamed.', 'contactpanda' ) : __( 'The tag could not be renamed.', 'contactpanda' );
		} elseif ( 'delete' === $operation && $tag_id && isset( $_REQUEST['delete_confirmed'] ) && '1' === (string) $_REQUEST['delete_confirmed'] ) {
			$success = ContactPanda_Store::standalone() ? ContactPanda_Store::delete_tag( $tag_id ) : self::groundhogg_delete_tag( $tag_id );
			$message = $success ? __( 'The tag and its assignments were deleted.', 'contactpanda' ) : __( 'The tag could not be deleted.', 'contactpanda' );
		} elseif ( 'bulk_delete' === $operation && isset( $_POST['bulk_action'], $_POST['delete_confirmed'] ) && 'delete' === sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) && '1' === (string) $_POST['delete_confirmed'] ) {
			$ids = isset( $_POST['tag_ids'] ) && is_array( $_POST['tag_ids'] ) ? array_values( array_filter( array_map( 'absint', $_POST['tag_ids'] ) ) ) : array();
			$deleted = 0;
			foreach ( $ids as $id ) {
				if ( ContactPanda_Store::standalone() ? ContactPanda_Store::delete_tag( $id ) : self::groundhogg_delete_tag( $id ) ) {
					$deleted++;
				}
			}
			$success = $deleted > 0;
			$message = $success ? sprintf( __( 'Deleted tags: %d', 'contactpanda' ), $deleted ) : __( 'Please select at least one tag.', 'contactpanda' );
		}
		set_transient( 'contactpanda_tag_notice_' . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=tags' ) );
		exit;
	}

	private static function tag_assignment_counts() {
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::tag_counts();
		}
		$counts = array();
		foreach ( self::all_contacts() as $contact ) {
			foreach ( isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? $contact['tags'] : array() as $tag ) {
				$id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) );
				if ( $id ) {
					$counts[ $id ] = isset( $counts[ $id ] ) ? $counts[ $id ] + 1 : 1;
				}
			}
		}
		return $counts;
	}

	private static function groundhogg_create_tag( $name ) {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return 0;
		}
		try {
			$db = \Groundhogg\get_db( 'tags' );
			$existing = $db->query( array( 'search' => $name, 'limit' => 100 ) );
			foreach ( (array) $existing as $tag ) {
				if ( 0 === strcasecmp( self::value( $tag, array( 'tag_name', 'name', 'get_name' ) ), $name ) ) {
					return absint( self::value( $tag, array( 'tag_id', 'id', 'ID', 'get_id' ) ) );
				}
			}
			if ( method_exists( $db, 'add' ) ) {
				return absint( $db->add( array( 'tag_name' => $name, 'tag_slug' => sanitize_title( $name ) ) ) );
			}
		} catch ( Throwable $e ) {
			return 0;
		}
		return 0;
	}

	private static function groundhogg_rename_tag( $tag_id, $name ) {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return false;
		}
		try {
			$db = \Groundhogg\get_db( 'tags' );
			if ( method_exists( $db, 'update' ) ) {
				return false !== $db->update( $tag_id, array( 'tag_name' => $name, 'tag_slug' => sanitize_title( $name ) ) );
			}
		} catch ( Throwable $e ) {
			return false;
		}
		return false;
	}

	private static function groundhogg_delete_tag( $tag_id ) {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return false;
		}
		try {
			$db = \Groundhogg\get_db( 'tags' );
			if ( method_exists( $db, 'delete' ) ) {
				return false !== $db->delete( $tag_id );
			}
		} catch ( Throwable $e ) {
			return false;
		}
		return false;
	}

	private static function groundhogg_assign_tag( $contact_id, $tag_id, $assign ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) {
			return false;
		}
		try {
			$contact = new \Groundhogg\Contact( $contact_id );
			if ( method_exists( $contact, 'exists' ) && ! $contact->exists() ) {
				return false;
			}
			$methods = $assign ? array( 'apply_tag', 'add_tag' ) : array( 'remove_tag', 'delete_tag' );
			foreach ( $methods as $method ) {
				if ( method_exists( $contact, $method ) ) {
					$result = $contact->{$method}( $tag_id );
					return false !== $result;
				}
			}
		} catch ( Throwable $e ) {
			return false;
		}
		return false;
	}

	public static function preview_broadcast() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$broadcast_id = isset( $_POST['broadcast_id'] ) ? absint( $_POST['broadcast_id'] ) : 0;
		$allowed = false;
		foreach ( self::contact_broadcasts( $contact_id ) as $broadcast ) {
			if ( $broadcast_id === $broadcast['id'] ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed || ! class_exists( '\\Groundhogg\\Broadcast' ) ) {
			wp_send_json_error( array( 'message' => __( 'This broadcast was not found for the person.', 'contactpanda' ) ), 404 );
		}

		try {
			$broadcast = new \Groundhogg\Broadcast( $broadcast_id );
			$email = $broadcast->get_object();
			if ( ! $broadcast->is_email() || ! $email || ! method_exists( $email, 'build' ) ) {
				wp_send_json_error( array( 'message' => __( 'The broadcast does not contain a displayable email.', 'contactpanda' ) ), 404 );
			}
			$email->set_contact( $contact_id );
			$html = $email->build();
			$title = method_exists( $email, 'get_subject_line' ) ? $email->get_subject_line() : $broadcast->get_title();
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => __( 'The broadcast email could not be rendered.', 'contactpanda' ) ), 500 );
		}
		wp_send_json_success( array( 'title' => wp_strip_all_tags( $title ), 'html' => $html ) );
	}

	public static function preview_order() {
		self::guard_ajax();
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'The order was not found.', 'contactpanda' ) ), 404 );
		}

		$billing = $order->get_formatted_billing_address();
		$shipping = $order->get_formatted_shipping_address();
		$html = '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><style>' . self::preview_document_css() . '</style></head><body>';
		$html .= __( '<header><div><span class="eyebrow">WooCommerce order</span><h1>Order #', 'contactpanda' ) . esc_html( $order->get_order_number() ) . '</h1></div><span class="status">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span></header>';
		$html .= __( '<div class="facts"><div><span>Order date</span><strong>', 'contactpanda' ) . esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '—' ) . __( '</strong></div><div><span>Payment method</span><strong>', 'contactpanda' ) . esc_html( $order->get_payment_method_title() ?: '—' ) . __( '</strong></div><div><span>Invoice number</span><strong>', 'contactpanda' ) . esc_html( self::invoice_number( $order ) ?: '—' ) . '</strong></div></div>';
		$html .= __( '<div class="addresses"><section><h2>Billing address</h2><p>', 'contactpanda' ) . ( $billing ? wp_kses_post( $billing ) : '—' ) . '</p><p>' . esc_html( $order->get_billing_email() ) . '<br>' . esc_html( self::format_phone( $order->get_billing_phone() ) ) . '</p></section>';
		if ( $order->needs_shipping_address() ) {
			$html .= __( '<section><h2>Shipping address</h2><p>', 'contactpanda' ) . ( $shipping ? wp_kses_post( $shipping ) : '—' ) . '</p></section>';
		}
		$html .= __( '</div><table><thead><tr><th>Item</th><th class="num">Quantity</th><th class="num">Total</th></tr></thead><tbody>', 'contactpanda' );
		foreach ( $order->get_items() as $item ) {
			$html .= '<tr><td><strong>' . esc_html( $item->get_name() ) . '</strong></td><td class="num">' . esc_html( $item->get_quantity() ) . '</td><td class="num">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td></tr>';
		}
		$html .= '</tbody><tfoot>';
		foreach ( $order->get_order_item_totals() as $total ) {
			$html .= '<tr><th colspan="2" class="num">' . wp_kses_post( $total['label'] ) . '</th><td class="num"><strong>' . wp_kses_post( $total['value'] ) . '</strong></td></tr>';
		}
		$html .= '</tfoot></table>';
		if ( $order->get_customer_note() ) {
			$html .= __( '<section class="note"><h2>Customer note</h2><p>', 'contactpanda' ) . nl2br( esc_html( $order->get_customer_note() ) ) . '</p></section>';
		}
		$html .= '</body></html>';
		wp_send_json_success(
			array(
				'title'    => __( 'Order #', 'contactpanda' ) . $order->get_order_number(),
				'html'     => $html,
				'edit_url' => $order->get_edit_order_url(),
			)
		);
	}

	public static function update_user_roles() {
		self::guard_user_admin_ajax();
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$contact = self::contact( $contact_id );
		$user = $user_id ? get_user_by( 'id', $user_id ) : false;
		$linked_id = $contact ? absint( self::value( $contact, array( 'user_id', 'get_user_id' ) ) ) : 0;
		if ( ! $contact || ! $user || $linked_id !== $user_id || ! current_user_can( 'edit_user', $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'The user account cannot be edited.', 'contactpanda' ) ), 403 );
		}
		$roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array();
		$result = self::set_user_roles( $user, $roles );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		self::log_contact_activity( $contact_id, 'user_roles', __( 'WordPress roles changed', 'contactpanda' ), sprintf( __( 'WordPress roles changed to: %1$s. Edited by: %2$s.', 'contactpanda' ), implode( ', ', $roles ), wp_get_current_user()->display_name ) );
		wp_send_json_success( array( 'message' => __( 'The WordPress roles were saved.', 'contactpanda' ) ) );
	}

	public static function create_user_account() {
		self::guard_user_admin_ajax();
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'create';
		$contact = self::contact( $contact_id );
		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'The person was not found.', 'contactpanda' ) ), 404 );
		}
		if ( ! ContactPanda_Store::standalone() && ! class_exists( '\\Groundhogg\\Contact' ) ) {
			wp_send_json_error( array( 'message' => __( 'Groundhogg is unavailable; no user account was created.', 'contactpanda' ) ), 503 );
		}
		$created_user = false;
		$send_invite = false;
		$email = sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) );
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'No valid email address is stored for this person.', 'contactpanda' ) ), 400 );
		}
		$existing = get_user_by( 'email', $email );
		if ( 'link' === $mode ) {
			if ( ! $existing || ! current_user_can( 'edit_user', $existing->ID ) ) {
				wp_send_json_error( array( 'message' => __( 'The existing user account cannot be linked.', 'contactpanda' ) ), 403 );
			}
			$user_id = (int) $existing->ID;
		} else {
			if ( $existing ) {
				wp_send_json_error( array( 'message' => __( 'A user account already exists for this email address. Please reload the page and use “Link”.', 'contactpanda' ) ), 409 );
			}
			$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
			if ( ! $username || username_exists( $username ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter an available, valid username.', 'contactpanda' ) ), 400 );
			}
			$roles = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['roles'] ) ) : array( 'customer' );
			$valid_roles = array_keys( self::editable_roles() );
			$roles = array_values( array_intersect( array_unique( $roles ), $valid_roles ) );
			if ( ! $roles ) {
				wp_send_json_error( array( 'message' => __( 'Please select at least one permitted role.', 'contactpanda' ) ), 400 );
			}
			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'first_name'   => self::value( $contact, array( 'first_name', 'get_first_name' ) ),
					'last_name'    => self::value( $contact, array( 'last_name', 'get_last_name' ) ),
					'display_name' => trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) ),
					'role'         => $roles[0],
				)
			);
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ), 400 );
			}
			$created_user = true;
			$user = get_user_by( 'id', $user_id );
			$role_result = self::set_user_roles( $user, $roles );
			if ( is_wp_error( $role_result ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $user_id );
				wp_send_json_error( array( 'message' => $role_result->get_error_message() ), 400 );
			}
			$send_invite = isset( $_POST['send_invite'] ) && '1' === (string) $_POST['send_invite'];
		}

		if ( ContactPanda_Store::standalone() ) {
			if ( ! ContactPanda_Store::link_user( $contact_id, $user_id ) ) {
				if ( $created_user ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $user_id ); }
				wp_send_json_error( array( 'message' => __( 'The user account could not be linked safely to ContactPanda; the newly created account was rolled back.', 'contactpanda' ) ), 500 );
			}
		} elseif ( class_exists( '\\Groundhogg\\Contact' ) ) {
			try {
				$groundhogg_contact = new \Groundhogg\Contact( $contact_id );
				$groundhogg_contact->update( array( 'user_id' => $user_id ) );
				if ( (int) $groundhogg_contact->get_user_id() !== (int) $user_id ) {
					throw new RuntimeException( __( 'Linking failed.', 'contactpanda' ) );
				}
			} catch ( Throwable $e ) {
				if ( $created_user ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $user_id );
				}
				wp_send_json_error( array( 'message' => __( 'The user account could not be linked safely to Groundhogg; the newly created account was rolled back.', 'contactpanda' ) ), 500 );
			}
		}
		if ( $created_user && $send_invite ) {
			wp_new_user_notification( $user_id, null, 'user' );
		}
		self::log_contact_activity( $contact_id, 'user_account', __( 'WordPress user account', 'contactpanda' ), sprintf( __( '%1$s Edited by: %2$s.', 'contactpanda' ), 'link' === $mode ? __( 'Existing WordPress user account linked.', 'contactpanda' ) : __( 'WordPress user account created and linked.', 'contactpanda' ), wp_get_current_user()->display_name ) );
		wp_send_json_success( array( 'message' => 'link' === $mode ? __( 'The user account was linked.', 'contactpanda' ) : __( 'The user account was created and linked.', 'contactpanda' ) ) );
	}

	public static function update_customer_address() {
		self::guard_ajax();

		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		$type = isset( $_POST['address_type'] ) ? sanitize_key( wp_unslash( $_POST['address_type'] ) ) : '';
		if ( ! $contact_id || ! in_array( $type, array( 'contact', 'billing', 'shipping' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address selection.', 'contactpanda' ) ), 400 );
		}

		$contact = self::contact( $contact_id );
		if ( ! $contact ) {
			wp_send_json_error( array( 'message' => __( 'The person could not be loaded from the selected data source.', 'contactpanda' ) ), 404 );
		}
		$user = $contact ? self::contact_user( $contact ) : false;
		if ( 'contact' !== $type && ! class_exists( 'WC_Customer' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is unavailable.', 'contactpanda' ) ), 503 );
		}
		if ( 'contact' !== $type && ! $user ) {
			wp_send_json_error( array( 'message' => __( 'This person does not have a linked WooCommerce customer account.', 'contactpanda' ) ), 400 );
		}

		$fields = 'contact' === $type
			? array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' )
			: array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
		if ( 'billing' === $type ) {
			$fields[] = 'email';
			$fields[] = 'phone';
		}
		$values = array();
		foreach ( $fields as $field ) {
			$values[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		$values['country'] = strtoupper( $values['country'] );
		$countries = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : self::contact_countries();
		if ( $values['country'] && ! isset( $countries[ $values['country'] ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a valid country.', 'contactpanda' ) ), 400 );
		}
		if ( 'billing' === $type && $values['email'] && ! is_email( $values['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid billing email address.', 'contactpanda' ) ), 400 );
		}
		if ( 'billing' === $type ) {
			$values['phone'] = self::normalize_phone( $values['phone'] );
			if ( $values['phone'] && ! self::phone_country_code( $values['phone'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Please select a European telephone calling code.', 'contactpanda' ) ), 400 );
			}
		}

		if ( 'contact' === $type ) {
			$groundhogg_meta = array(
				'street_address_1' => $values['address_1'], 'street_address_2' => $values['address_2'],
				'postal_zip' => $values['postcode'], 'city' => $values['city'],
				'region' => $values['state'], 'country' => $values['country'],
			);
			$input = array( 'id' => $contact_id, 'meta' => $groundhogg_meta );
			$result = ContactPanda_Store::standalone()
				? ContactPanda_Store::update_contact_address( $contact_id, $values )
				: self::execute_ability( 'groundhogg/update-contact', $input );
			if ( false === $result ) {
				wp_send_json_error( array( 'message' => __( 'The contact address could not be saved.', 'contactpanda' ) ), 500 );
			}

			$sync = isset( $_POST['sync_to_woocommerce'] ) && '1' === (string) $_POST['sync_to_woocommerce'];
			if ( $sync ) {
				if ( ! $user || ! class_exists( 'WC_Customer' ) ) {
					wp_send_json_error( array( 'message' => __( 'The contact address was saved, but no linked WooCommerce customer account is available for copying.', 'contactpanda' ) ), 409 );
				}
				try {
					$customer = new WC_Customer( $user->ID );
					foreach ( $values as $field => $value ) {
						$setter = 'set_billing_' . $field;
						if ( method_exists( $customer, $setter ) ) {
							$customer->{$setter}( $value );
						}
					}
					$customer->save();
				} catch ( Throwable $e ) {
					wp_send_json_error( array( 'message' => __( 'The contact address was saved, but WooCommerce could not copy it to the billing address.', 'contactpanda' ) ), 500 );
				}
			}

			$display = self::format_address( $values );
			self::log_contact_activity( $contact_id, 'address_update', __( 'Contact address', 'contactpanda' ), sprintf( __( 'Contact address updated%1$s. Edited by: %2$s.', 'contactpanda' ), $sync ? __( ' and copied to the WooCommerce billing address', 'contactpanda' ) : '', wp_get_current_user()->display_name ) );
			wp_send_json_success( array( 'message' => $sync ? __( 'The contact address was saved and copied to the WooCommerce billing address.', 'contactpanda' ) : __( 'The contact address was saved.', 'contactpanda' ), 'display' => $display ?: '—' ) );
		}

		try {
			$customer = new WC_Customer( $user->ID );
			foreach ( $values as $field => $value ) {
				$setter = 'set_' . $type . '_' . $field;
				if ( method_exists( $customer, $setter ) ) {
					$customer->{$setter}( $value );
				}
			}
			$customer->save();
			foreach ( $values as $field => $value ) {
				update_user_meta( $user->ID, $type . '_' . $field, $value );
			}
			clean_user_cache( $user->ID );
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce could not save the address.', 'contactpanda' ) ), 500 );
		}

		wp_send_json_success( array( 'message' => __( 'The WooCommerce ', 'contactpanda' ) . ( 'billing' === $type ? __( 'Billing address', 'contactpanda' ) : __( 'Shipping address', 'contactpanda' ) ) . __( ' was saved.', 'contactpanda' ) ) );
	}

	public static function validate_vat() {
		self::guard_ajax();
		$vat = isset( $_POST['value'] ) ? self::normalize_vat( wp_unslash( $_POST['value'] ) ) : '';
		if ( ! self::valid_vat_format( $vat ) ) {
			wp_send_json_error( array( 'message' => __( 'The VAT ID does not have a valid EU format.', 'contactpanda' ) ), 400 );
		}

		$country = substr( $vat, 0, 2 );
		$number  = substr( $vat, 2 );
		$body    = '<?xml version="1.0" encoding="UTF-8"?>' .
			'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">' .
			'<soapenv:Body><urn:checkVat><urn:countryCode>' . esc_html( $country ) . '</urn:countryCode><urn:vatNumber>' . esc_html( $number ) . '</urn:vatNumber></urn:checkVat></soapenv:Body></soapenv:Envelope>';
		$response = wp_remote_post(
			'https://ec.europa.eu/taxation_customs/vies/services/checkVatService',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'text/xml; charset=utf-8', 'SOAPAction' => '' ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => __( 'The EU VIES service is currently unavailable. The VAT ID was not evaluated.', 'contactpanda' ) ), 503 );
		}
		$xml = wp_remote_retrieve_body( $response );
		if ( preg_match( '/<(?:[A-Za-z0-9_-]+:)?valid>\s*(true|false)\s*<\/(?:[A-Za-z0-9_-]+:)?valid>/i', $xml, $match ) ) {
			if ( 'true' === strtolower( $match[1] ) ) {
				$user = self::vat_user_from_request();
				if ( $user ) {
					update_user_meta( $user->ID, 'contactpanda_vat_verified_hash', hash( 'sha256', $vat ) );
					update_user_meta( $user->ID, 'contactpanda_vat_verified_at', current_time( 'mysql' ) );
				}
				wp_send_json_success( array( 'message' => __( 'The VAT ID is valid according to EU VIES.', 'contactpanda' ), 'status' => 'valid', 'verified' => true ) );
			}
			$user = self::vat_user_from_request();
			if ( $user ) {
				delete_user_meta( $user->ID, 'contactpanda_vat_verified_hash' );
				delete_user_meta( $user->ID, 'contactpanda_vat_verified_at' );
			}
			wp_send_json_error( array( 'message' => __( 'The VAT ID is not valid according to EU VIES.', 'contactpanda' ), 'status' => 'invalid' ), 422 );
		}
		wp_send_json_error( array( 'message' => __( 'EU VIES did not return a usable response.', 'contactpanda' ) ), 503 );
	}

	public static function render() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contactpanda' ) );
		}
		if ( ! self::companies_available() ) {
			self::render_people();
			return;
		}

		$company_id = isset( $_GET['firma'] ) ? absint( $_GET['firma'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$section = isset( $_GET['section'] ) && 'import' === sanitize_key( wp_unslash( $_GET['section'] ) ) ? 'import' : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing.
		echo '<div class="wrap contactpanda-wrap' . ( $company_id ? ' contactpanda-detail-wrap' : '' ) . '">';
		if ( $company_id ) {
			self::render_company( $company_id );
		} elseif ( 'import' === $section ) {
			self::render_entity_navigation( 'contactpanda-firmen', 'import' );
			ContactPanda_Data_Tools::render_entity_import( 'companies', 'contactpanda-firmen' );
		} else {
			self::render_entity_navigation( 'contactpanda-firmen', 'list' );
			self::render_index();
		}
		echo '</div>';
	}

	public static function render_people() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contactpanda' ) );
		}

		$contact_id = isset( $_GET['person'] ) ? absint( $_GET['person'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		echo '<div class="wrap contactpanda-wrap' . ( $contact_id ? ' contactpanda-detail-wrap' : '' ) . '">';
		if ( $contact_id ) {
			self::render_person( $contact_id );
		} else {
			self::render_people_index();
		}
		echo '</div>';
	}

	public static function render_dashboard() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'contactpanda' ) );
		}
		// Keep bookmarked detail links from earlier releases working after the
		// dashboard became the ContactPanda landing page.
		if ( ! empty( $_GET['person'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only backwards-compatible routing.
			self::render_people();
			return;
		}

		$contact_cards = array(
			array( __( 'People', 'contactpanda' ), self::dashboard_people_count(), admin_url( 'admin.php?page=contactpanda-people' ), 'groups' ),
		);
		if ( self::companies_available() ) $contact_cards[] = array( __( 'Companies', 'contactpanda' ), self::dashboard_company_count(), admin_url( 'admin.php?page=contactpanda-firmen' ), 'building' );
		$contact_cards[] = array( __( 'Potential duplicates', 'contactpanda' ), self::dashboard_duplicate_count(), admin_url( 'admin.php?page=contactpanda-settings&tab=other&other_section=duplicates' ), 'admin-page' );

		$communication_cards = array();
		$last_mailing = false;
		if ( class_exists( 'ContactPanda_Newsletter_Schema' ) && class_exists( 'ContactPanda_License' ) && ContactPanda_License::newsletter_entitled() ) {
			global $wpdb;
			ContactPanda_Newsletter_Schema::maybe_upgrade();
			$tables = ContactPanda_Newsletter_Schema::table_names();
			$contact_cards[] = array( __( 'Saved searches', 'contactpanda' ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tables['segments']}`" ), admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=saved_searches' ), 'filter' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$communication_cards[] = array( __( 'Email templates', 'contactpanda' ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tables['templates']}` WHERE status <> 'archived'" ), admin_url( 'admin.php?page=contactpanda-newsletters' ), 'email-alt' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$communication_cards[] = array( __( 'Mailings', 'contactpanda' ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tables['campaigns']}`" ), admin_url( 'admin.php?page=contactpanda-campaigns' ), 'megaphone' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$communication_cards[] = array( __( 'Campaigns', 'contactpanda' ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tables['campaign_groups']}`" ), admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns' ), 'category' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$communication_cards[] = array( __( 'Blocked email addresses', 'contactpanda' ), (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tables['suppressions']}`" ), admin_url( 'admin.php?page=contactpanda-campaigns' ), 'warning' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$last_mailing = $wpdb->get_row( "SELECT * FROM `{$tables['campaigns']}` WHERE started_at IS NOT NULL OR completed_at IS NOT NULL OR status = 'imported' ORDER BY COALESCE(completed_at,started_at,scheduled_at,created_at) DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name and fixed query.
		}
		$communication_cards = apply_filters( 'contactpanda_dashboard_communication_cards', $communication_cards );

		echo '<div class="wrap contactpanda-wrap contactpanda-dashboard"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CRM</p><h1>' . esc_html__( 'Overview', 'contactpanda' ) . '</h1><p>' . esc_html__( 'The most important ContactPanda figures at a glance.', 'contactpanda' ) . '</p></div></div>';
		self::render_dashboard_product_visual();
		self::render_dashboard_section( __( 'People and companies', 'contactpanda' ), $contact_cards );
		if ( $communication_cards ) self::render_dashboard_section( __( 'Newsletter and automations', 'contactpanda' ), $communication_cards );
		self::render_dashboard_last_mailing( $last_mailing );
		self::render_dashboard_release_news();
		self::render_dashboard_news();
		self::render_dashboard_upgrades();
		echo '</div>';
	}

	/**
	 * Render a compact product introduction. The panda remains a quiet brand
	 * cue instead of competing with the operational dashboard information.
	 */
	private static function render_dashboard_product_visual() {
		$visual = apply_filters( 'contactpanda_dashboard_product_visual', array( 'src' => CONTACTPANDA_URL . 'assets/images/contactpanda-admin-icon.png', 'alt' => '' ) );
		if ( ! is_array( $visual ) || empty( $visual['src'] ) ) return;
		echo '<section class="contactpanda-card contactpanda-dashboard-product-visual"><img src="' . esc_url( $visual['src'] ) . '" alt="' . esc_attr( $visual['alt'] ?? '' ) . '"><div><h2>' . esc_html__( 'Your contacts and communication in one place', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Manage people, companies, consent, newsletters, and connected extensions from a clear shared workspace.', 'contactpanda' ) . '</p></div></section>';
	}

	/** Explain user-relevant changes and provide a prepared feedback path. */
	private static function render_dashboard_release_news() {
		echo '<section class="contactpanda-dashboard-release-grid"><article class="contactpanda-card contactpanda-dashboard-release"><p class="contactpanda-eyebrow">' . esc_html__( 'New in this version', 'contactpanda' ) . '</p><h2>' . esc_html( CONTACTPANDA_VERSION ) . '</h2><ul><li>' . esc_html__( 'ContactPanda now has a clear Basic, Plus, Business, and future Members structure.', 'contactpanda' ) . '</li><li>' . esc_html__( 'The former Pro CRM and Support Desk now belong to ContactPanda Business.', 'contactpanda' ) . '</li><li>' . esc_html__( 'Protected update metadata is published consistently for every paid family component.', 'contactpanda' ) . '</li></ul></article>';
		$feedback_url = add_query_arg( array( 'page' => 'contactpanda-business-support', 'new' => 1, 'source' => 'contactpanda-dashboard', 'subject' => __( 'Feedback from ContactPanda dashboard', 'contactpanda' ) ), admin_url( 'admin.php' ) );
		if ( ! defined( 'CONTACTPANDA_BUSINESS_VERSION' ) ) $feedback_url = apply_filters( 'contactpanda_feedback_url', 'https://mycontactpanda.com/' );
		echo '<article class="contactpanda-card contactpanda-dashboard-feedback"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><div><h2>' . esc_html__( 'Have you noticed something?', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Send feedback without having to collect technical details yourself. When Support Desk is available, the current page and version are prepared automatically.', 'contactpanda' ) . '</p><a class="button" href="' . esc_url( $feedback_url ) . '">' . esc_html__( 'Send feedback', 'contactpanda' ) . '</a></div></article></section>';
	}

	private static function dashboard_people_count() {
		$result = self::contact_search( '', 1, 0 );
		if ( isset( $result['total_items'] ) ) {
			return absint( $result['total_items'] );
		}
		return count( self::extension_contacts() );
	}

	private static function dashboard_company_count() {
		if ( ContactPanda_Store::standalone() ) {
			$counts = ContactPanda_Store::counts();
			return absint( $counts['companies'] ?? 0 );
		}
		$result = self::execute_ability(
			'groundhogg-companies/list-companies',
			array(
				'limit'  => 1,
				'offset' => 0,
			)
		);
		foreach ( array( 'total_items', 'total', 'count' ) as $key ) {
			if ( is_array( $result ) && isset( $result[ $key ] ) ) return absint( $result[ $key ] );
		}
		return count( self::extension_companies() );
	}

	private static function dashboard_duplicate_count() {
		if ( ContactPanda_Store::standalone() ) {
			return count( ContactPanda_Store::duplicate_groups( 5000 ) );
		}

		$seen = array();
		$parents = array();
		$duplicate_ids = array();
		$find = static function ( $id ) use ( &$parents, &$find ) {
			if ( ! isset( $parents[ $id ] ) ) $parents[ $id ] = $id;
			if ( $parents[ $id ] !== $id ) $parents[ $id ] = $find( $parents[ $id ] );
			return $parents[ $id ];
		};
		foreach ( self::extension_contacts() as $index => $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
			if ( ! $id ) $id = $index + 1;
			$keys = array();
			$email = strtolower( sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ) );
			$phone = preg_replace( '/\D+/', '', (string) self::value( $contact, array( 'phone', 'phone_number', 'mobile', 'get_phone' ) ) );
			$postcode = strtolower( preg_replace( '/\s+/', '', (string) self::value( $contact, array( 'postcode', 'postal_code', 'zip' ) ) ) );
			$name = strtolower( remove_accents( trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . '|' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) ) ) );
			if ( $email ) $keys[] = 'e:' . $email;
			if ( strlen( $phone ) >= 7 ) $keys[] = 'p:' . $phone;
			if ( '|' !== $name && $postcode ) $keys[] = 'n:' . $name . '|' . $postcode;
			foreach ( $keys as $key ) {
				if ( isset( $seen[ $key ] ) ) {
					$left_root = $find( $seen[ $key ] );
					$right_root = $find( $id );
					if ( $left_root !== $right_root ) $parents[ $right_root ] = $left_root;
					$duplicate_ids[ $seen[ $key ] ] = true;
					$duplicate_ids[ $id ] = true;
				} else {
					$seen[ $key ] = $id;
				}
			}
		}
		$roots = array();
		foreach ( array_keys( $duplicate_ids ) as $id ) $roots[ $find( $id ) ] = true;
		return count( $roots );
	}

	private static function render_dashboard_section( $title, $cards ) {
		echo '<section class="contactpanda-card contactpanda-dashboard-section"><h2>' . esc_html( $title ) . '</h2><div class="contactpanda-dashboard-grid">';
		foreach ( (array) $cards as $card ) {
			if ( ! is_array( $card ) || count( $card ) < 4 ) continue;
			echo '<a class="contactpanda-dashboard-card" href="' . esc_url( $card[2] ) . '"><span class="dashicons dashicons-' . esc_attr( $card[3] ) . '" aria-hidden="true"></span><strong>' . esc_html( number_format_i18n( absint( $card[1] ) ) ) . '</strong><span>' . esc_html( $card[0] ) . '</span></a>';
		}
		echo '</div></section>';
	}

	private static function render_dashboard_last_mailing( $mailing ) {
		echo '<section class="contactpanda-card contactpanda-dashboard-section contactpanda-dashboard-mailing"><h2>' . esc_html__( 'Last mailing', 'contactpanda' ) . '</h2>';
		if ( ! is_array( $mailing ) ) {
			echo '<p class="contactpanda-dashboard-empty">' . esc_html__( 'No mailing has been sent yet.', 'contactpanda' ) . '</p></section>';
			return;
		}
		$date = $mailing['completed_at'] ?: ( $mailing['started_at'] ?: ( $mailing['scheduled_at'] ?: $mailing['created_at'] ) );
		$status_labels = array(
			'draft'     => __( 'Draft', 'contactpanda' ),
			'scheduled' => __( 'Scheduled', 'contactpanda' ),
			'queued'    => __( 'Queued', 'contactpanda' ),
			'sending'   => __( 'Sending', 'contactpanda' ),
			'paused'    => __( 'Paused', 'contactpanda' ),
			'completed' => __( 'Completed', 'contactpanda' ),
			'cancelled' => __( 'Cancelled', 'contactpanda' ),
			'imported'  => __( 'Imported', 'contactpanda' ),
		);
		$status = $status_labels[ $mailing['status'] ] ?? $mailing['status'];
		$stats = array(
			__( 'Recipients', 'contactpanda' )   => absint( $mailing['total_recipients'] ),
			__( 'Sent', 'contactpanda' )         => absint( $mailing['sent_count'] ),
			__( 'Opened', 'contactpanda' )       => absint( $mailing['opened_count'] ),
			__( 'Clicked', 'contactpanda' )      => absint( $mailing['clicked_count'] ),
			__( 'Unsubscribed', 'contactpanda' ) => absint( $mailing['unsubscribed_count'] ),
		);
		echo '<a class="contactpanda-dashboard-last-mailing" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-campaigns&campaign_id=' . absint( $mailing['id'] ) ) ) . '"><header><div><h3>' . esc_html( $mailing['name'] ) . '</h3><p>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date ) ) . '</p></div><span class="contactpanda-status-badge">' . esc_html( $status ) . '</span></header><div class="contactpanda-dashboard-mailing-stats">';
		foreach ( $stats as $label => $value ) echo '<span><strong>' . esc_html( number_format_i18n( $value ) ) . '</strong><small>' . esc_html( $label ) . '</small></span>';
		echo '</div></a></section>';
	}

	/** Render the central ContactPanda news feed on the product dashboard. */
	private static function render_dashboard_news() {
		$news_url = self::news_url();
		echo '<section class="contactpanda-card contactpanda-dashboard-section contactpanda-dashboard-news"><div class="contactpanda-dashboard-section-heading"><div><p class="contactpanda-eyebrow">' . esc_html__( 'Blog', 'contactpanda' ) . '</p><h2>' . esc_html__( 'ContactPanda News', 'contactpanda' ) . '</h2></div><a class="button" href="' . esc_url( $news_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View all news', 'contactpanda' ) . '<span class="dashicons dashicons-external" aria-hidden="true"></span></a></div>';
		self::render_news_list( 4, false );
		echo '</section>';
	}

	private static function news_url() {
		$default = defined( 'CONTACTPANDA_NEWS_URL' ) ? CONTACTPANDA_NEWS_URL : 'https://mycontactpanda.com/';
		return esc_url_raw( apply_filters( 'contactpanda_news_url', $default ) );
	}

	private static function news_items( $limit ) {
		if ( ! function_exists( 'fetch_feed' ) ) require_once ABSPATH . WPINC . '/feed.php';
		$default = defined( 'CONTACTPANDA_NEWS_FEED_URL' ) ? CONTACTPANDA_NEWS_FEED_URL : 'https://mycontactpanda.com/feed/';
		$feed_url = esc_url_raw( apply_filters( 'contactpanda_news_feed_url', $default ) );
		$cache_key = 'contactpanda_news_' . md5( $feed_url );
		$cached = get_site_transient( $cache_key );
		if ( is_array( $cached ) ) return array_slice( $cached, 0, max( 1, absint( $limit ) ) );
		$feed = $feed_url ? fetch_feed( $feed_url ) : new WP_Error( 'contactpanda_news_feed', 'Missing feed URL.' );
		if ( is_wp_error( $feed ) ) {
			set_site_transient( $cache_key, array(), 30 * MINUTE_IN_SECONDS );
			return array();
		}
		$items = array();
		foreach ( (array) $feed->get_items( 0, 8 ) as $item ) {
			$timestamp = $item->get_date( 'U' );
			$items[] = array(
				'title'   => sanitize_text_field( $item->get_title() ),
				'url'     => esc_url_raw( $item->get_permalink() ?: self::news_url() ),
				'date'    => $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : '',
				'excerpt' => wp_trim_words( wp_strip_all_tags( (string) $item->get_description(), true ), 24 ),
			);
		}
		set_site_transient( $cache_key, $items, 6 * HOUR_IN_SECONDS );
		return array_slice( $items, 0, max( 1, absint( $limit ) ) );
	}

	private static function render_news_list( $limit, $compact = false ) {
		$items = self::news_items( $limit );
		if ( ! $items ) {
			echo '<p class="contactpanda-news-empty">' . esc_html__( 'No ContactPanda news is currently available.', 'contactpanda' ) . '</p>';
		} else {
			echo '<div class="contactpanda-news-list' . ( $compact ? ' is-compact' : '' ) . '">';
			foreach ( $items as $item ) {
				echo '<article class="contactpanda-news-item"><h3><a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $item['title'] ) . '</a></h3>';
				if ( $item['date'] ) echo '<time>' . esc_html( $item['date'] ) . '</time>';
				if ( ! $compact && $item['excerpt'] ) echo '<p>' . esc_html( $item['excerpt'] ) . '</p>';
				echo '</article>';
			}
			echo '</div>';
		}
		if ( $compact ) echo '<p class="contactpanda-news-more"><a href="' . esc_url( self::news_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'View all ContactPanda news', 'contactpanda' ) . '<span class="dashicons dashicons-external" aria-hidden="true"></span></a></p>';
	}

	/** Show relevant family upgrades without advertising products already active. */
	private static function render_dashboard_upgrades() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$offers = array();
		if ( ! defined( 'CONTACTPANDA_PLUS_VERSION' ) && ! ContactPanda_License::entitled( 'plus' ) ) {
			$offers[] = array(
				'product'     => 'plus',
				'name'        => 'ContactPanda Plus',
				'description' => __( 'Grow your marketing with advanced newsletters, visual forms, automations, integrations, and assisted migrations.', 'contactpanda' ),
				'features'    => array(
					__( 'Advanced newsletter campaigns', 'contactpanda' ),
					__( 'Visual forms and form migrations', 'contactpanda' ),
					__( 'Automations and integrations', 'contactpanda' ),
				),
				'button'      => __( 'Discover ContactPanda Plus', 'contactpanda' ),
			);
		}
		if ( ! defined( 'CONTACTPANDA_PRO_VERSION' ) && ! ContactPanda_License::entitled( 'pro' ) ) {
			$offers[] = array(
				'product'     => 'pro',
				'name'        => 'ContactPanda Pro',
				'description' => __( 'Manage professional customer relationships with customer service, commercial workflows, and connected documents.', 'contactpanda' ),
				'features'    => array(
					__( 'Customer management and Support Desk', 'contactpanda' ),
					__( 'Contracts and subscriptions', 'contactpanda' ),
					__( 'Payments and invoice documents', 'contactpanda' ),
				),
				'button'      => __( 'Discover ContactPanda Pro', 'contactpanda' ),
			);
		}
		$offers = apply_filters( 'contactpanda_dashboard_upgrade_offers', $offers );
		if ( ! $offers ) return;

		echo '<section class="contactpanda-card contactpanda-dashboard-section contactpanda-dashboard-upgrades"><div class="contactpanda-dashboard-upgrade-heading"><div><p class="contactpanda-eyebrow">ContactPanda</p><h2>' . esc_html__( 'More possibilities for your business', 'contactpanda' ) . '</h2></div><p>' . esc_html__( 'Extend ContactPanda when your requirements grow. Your existing contact data remains available.', 'contactpanda' ) . '</p></div><div class="contactpanda-dashboard-upgrade-grid">';
		foreach ( (array) $offers as $offer ) {
			if ( ! is_array( $offer ) || empty( $offer['product'] ) || empty( $offer['name'] ) ) continue;
			$product = sanitize_key( $offer['product'] );
			$default_url = defined( 'CONTACTPANDA_' . strtoupper( $product ) . '_SALES_URL' ) ? constant( 'CONTACTPANDA_' . strtoupper( $product ) . '_SALES_URL' ) : 'https://mycontactpanda.com/';
			$url = apply_filters( 'contactpanda_product_sales_url', $default_url, $product, 'dashboard' );
			echo '<article class="contactpanda-dashboard-upgrade-card is-' . esc_attr( $product ) . '"><h3>' . esc_html( $offer['name'] ) . '</h3><p>' . esc_html( $offer['description'] ?? '' ) . '</p>';
			if ( ! empty( $offer['features'] ) ) {
				echo '<ul>';
				foreach ( (array) $offer['features'] as $feature ) echo '<li><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . esc_html( $feature ) . '</li>';
				echo '</ul>';
			}
			echo '<p class="contactpanda-dashboard-upgrade-action"><a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $offer['button'] ?? __( 'Learn more', 'contactpanda' ) ) . '<span class="dashicons dashicons-external" aria-hidden="true"></span></a></p></article>';
		}
		echo '</div></section>';
	}

	private static function render_entity_navigation( $page, $current ) {
		echo '<nav class="nav-tab-wrapper contactpanda-entity-tabs" aria-label="' . esc_attr__( 'View selection', 'contactpanda' ) . '">';
		echo '<a class="nav-tab' . ( 'list' === $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '">' . esc_html__( 'Overview', 'contactpanda' ) . '</a>';
		if ( current_user_can( 'manage_options' ) ) echo '<a class="nav-tab' . ( 'import' === $current ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $page . '&section=import' ) ) . '">' . esc_html__( 'Import', 'contactpanda' ) . '</a>';
		echo '</nav>';
		$notice = get_transient( 'contactpanda_database_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) { delete_transient( 'contactpanda_database_notice_' . get_current_user_id() ); echo '<div class="notice notice-' . ( 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>'; }
	}

	private static function render_people_index() {
		$companies_enabled = self::companies_available();
		$events_enabled = self::events_available();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		$page = isset( $_GET['contactpanda_page'] ) ? max( 1, absint( $_GET['contactpanda_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$customer_filter = isset( $_GET['kunden'] ) ? sanitize_key( wp_unslash( $_GET['kunden'] ) ) : 'alle'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$allowed_filters = array( 'alle', 'besteller' );
		if ( $events_enabled ) {
			$allowed_filters[] = 'event-besucher';
		}
		if ( ! in_array( $customer_filter, $allowed_filters, true ) ) {
			$customer_filter = 'alle';
		}
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sorting.
		$order = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'desc' : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only sorting.
		$segment_id = isset( $_GET['segment'] ) ? absint( $_GET['segment'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$advanced_filter = isset( $_GET['contactpanda_filter'] ) ? substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_GET['contactpanda_filter'] ) ), 0, 12000 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- signed-in read-only filter state.
		$import_run = isset( $_GET['import_run'] ) ? absint( $_GET['import_run'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only import-history filter.
		$sortable = array( 'first_name', 'last_name', 'order_total' );
		if ( $companies_enabled ) {
			$sortable[] = 'company';
		}
		if ( $events_enabled ) {
			$sortable[] = 'event_count';
		}
		if ( ! in_array( $orderby, $sortable, true ) ) {
			$orderby = '';
		}
		$limit = 50;
		$company_map = $companies_enabled ? self::contact_company_map() : array();
		$needs_complete_result = $segment_id || $advanced_filter || $import_run || in_array( $customer_filter, array( 'besteller', 'event-besucher' ), true ) || '' !== $orderby;
		$order_summaries = array();
		$event_summaries = array();
		$navigation_contacts = array();

		if ( $needs_complete_result ) {
			// The two quick filters only need stable contact identities while their
			// WooCommerce/Event relationships are calculated. Loading every complete
			// Groundhogg contact (including tags and meta) can exhaust memory on real
			// sites before the first filtered page is rendered.
			$uses_relationship_filter = in_array( $customer_filter, array( 'besteller', 'event-besucher' ), true )
				|| in_array( $orderby, array( 'order_total', 'event_count' ), true );
			$contacts = $uses_relationship_filter && ! $segment_id && ! $advanced_filter && ! $import_run
				? self::all_contact_identities( $search )
				: self::all_contacts( $search );
			if ( 'besteller' === $customer_filter || 'order_total' === $orderby ) {
				$order_summaries = self::contact_order_summaries( $contacts );
			}
			if ( 'event-besucher' === $customer_filter || 'event_count' === $orderby ) {
				$event_summaries = self::contact_event_summaries( $contacts );
			}
			if ( 'besteller' === $customer_filter ) {
				$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $order_summaries ) {
					$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
					return isset( $order_summaries[ $id ] ) && $order_summaries[ $id ]['count'] > 0;
				} ) );
			}
			if ( 'event-besucher' === $customer_filter ) {
				$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $event_summaries ) {
					$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
					return isset( $event_summaries[ $id ] ) && $event_summaries[ $id ]['count'] > 0;
				} ) );
			}
			if ( $segment_id || $advanced_filter ) {
				$contacts = apply_filters( 'contactpanda_people_contacts', $contacts, $segment_id, $advanced_filter );
			}
			if ( $import_run ) {
				$import_ids = ContactPanda_Store::import_record_ids( $import_run, 'contacts' );
				$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $import_ids ) { return in_array( absint( self::value( $contact, array( 'id', 'ID' ) ) ), $import_ids, true ); } ) );
			}
			if ( $orderby ) {
				usort( $contacts, static function ( $left, $right ) use ( $orderby, $order, $company_map, $order_summaries, $event_summaries ) {
					$comparison = self::compare_contacts( $left, $right, $orderby, $company_map, $order_summaries, $event_summaries );
					return 'desc' === $order ? -$comparison : $comparison;
				} );
			}
			$total = count( $contacts );
			$navigation_contacts = $contacts;
			$pages = max( 1, (int) ceil( $total / $limit ) );
			$page = min( $page, $pages );
			$contacts = array_slice( $contacts, ( $page - 1 ) * $limit, $limit );
			if ( $uses_relationship_filter && ! $segment_id && ! $advanced_filter && ! $import_run ) {
				$contacts = self::hydrate_contact_identities( $contacts );
			}
		} else {
			$result = self::contact_search( $search, $limit, ( $page - 1 ) * $limit );
			$contacts = isset( $result['contacts'] ) && is_array( $result['contacts'] ) ? $result['contacts'] : array();
			$total = isset( $result['total_items'] ) ? absint( $result['total_items'] ) : count( $contacts );
			$navigation_contacts = $total > count( $contacts ) ? self::all_contacts( $search ) : $contacts;
		}
		if ( ! $order_summaries && $contacts ) {
			$order_summaries = self::contact_order_summaries( $contacts );
		}
		if ( $events_enabled && ! $event_summaries ) {
			$event_summaries = self::contact_event_summaries( $contacts );
		}
			$list_state = self::store_people_list_state( $navigation_contacts, array(
				's' => $search, 'contactpanda_page' => $page, 'kunden' => $customer_filter,
				'orderby' => $orderby, 'order' => $order, 'segment' => $segment_id,
				'contactpanda_filter' => $advanced_filter,
			) );

		$heading_text = ContactPanda_Store::standalone()
			? __( 'ContactPanda contacts, company relationships, orders, events, and marketing status in one view.', 'contactpanda' )
			: ( $companies_enabled
			? __( 'Groundhogg contacts, company relationships, and marketing status in one view.', 'contactpanda' )
			: __( 'Groundhogg contacts, orders, and marketing status in one view.', 'contactpanda' ) );
		echo '<div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CRM</p><h1>' . esc_html__( 'People', 'contactpanda' ) . '</h1><p>' . esc_html( $heading_text ) . '</p></div><div class="contactpanda-heading-actions">';
		if ( current_user_can( 'manage_options' ) ) {
			if ( ContactPanda_Store::standalone() ) {
				echo '<a class="button contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=other&other_section=duplicates' ) ) . '">' . esc_html__( 'Find duplicates', 'contactpanda' ) . '</a>';
			}
			echo '<a class="button contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-data-transfer&transfer_section=import&entity=contacts' ) ) . '">' . esc_html__( 'Import', 'contactpanda' ) . '</a>';
		}
		echo '<button type="button" class="button button-primary contactpanda-heading-action contactpanda-open-new-person">' . esc_html__( 'Add person', 'contactpanda' ) . '</button></div></div>';
		echo '<section class="contactpanda-card"><div class="contactpanda-toolbar"><h2>' . esc_html__( 'People overview', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( $total ) . ' ' . esc_html__( 'entries', 'contactpanda' ) . '</span></div>';
		echo '<form class="contactpanda-search" method="get"><input type="hidden" name="page" value="' . esc_attr( self::people_page_slug() ) . '">';
		if ( $advanced_filter ) {
			echo '<input type="hidden" name="contactpanda_filter" value="' . esc_attr( $advanced_filter ) . '">';
		}
		if ( 'alle' !== $customer_filter ) {
			echo '<input type="hidden" name="kunden" value="' . esc_attr( $customer_filter ) . '">';
		}
		if ( $orderby ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $orderby ) . '"><input type="hidden" name="order" value="' . esc_attr( $order ) . '">';
		}
		echo '<div class="contactpanda-people-search-row">';
		do_action( 'contactpanda_people_filter_controls', $segment_id );
		echo '<span class="contactpanda-search-primary"><label class="screen-reader-text" for="contactpanda-person-search">' . esc_html__( 'Quick search', 'contactpanda' ) . '</label><input id="contactpanda-person-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Quick search by name, email, phone, or company', 'contactpanda' ) . '"><button class="button contactpanda-icon-button" aria-label="' . esc_attr__( 'Search', 'contactpanda' ) . '" title="' . esc_attr__( 'Search', 'contactpanda' ) . '"><span class="dashicons dashicons-search" aria-hidden="true"></span></button></span>';
		echo '<span class="contactpanda-filter-buttons">' . self::people_filter_link( __( 'All', 'contactpanda' ), 'alle', $customer_filter, $search, $orderby, $order ) . self::people_filter_link( __( 'Customers', 'contactpanda' ), 'besteller', $customer_filter, $search, $orderby, $order );
		if ( $events_enabled ) {
			echo self::people_filter_link( __( 'Event attendees', 'contactpanda' ), 'event-besucher', $customer_filter, $search, $orderby, $order );
		}
		echo '</span></div></form>';
		do_action( 'contactpanda_people_advanced_search', $advanced_filter, $total );
		if ( ! $contacts ) {
			echo '<div class="contactpanda-empty"><span class="dashicons dashicons-admin-users"></span><h3>' . esc_html__( 'No people found', 'contactpanda' ) . '</h3><p>' . esc_html__( 'The selected data source returned no contacts for this search.', 'contactpanda' ) . '</p></div>';
			self::render_new_person_modal();
			echo '</section>';
			return;
		}

		echo '<div class="contactpanda-bulk-toolbar" data-total="' . esc_attr( $total ) . '" data-list-token="' . esc_attr( $list_state ) . '"><label class="screen-reader-text" for="contactpanda-bulk-action">' . esc_html__( 'Select bulk action', 'contactpanda' ) . '</label><select id="contactpanda-bulk-action"><option value="">' . esc_html__( 'Bulk actions', 'contactpanda' ) . '</option><option value="marketable">' . esc_html__( 'Enable marketable status', 'contactpanda' ) . '</option>';
		if ( class_exists( 'ContactPanda_Basic_Newsletter_Campaigns' ) ) echo '<option value="newsletter">' . esc_html__( 'Send newsletter', 'contactpanda' ) . '</option>';
		echo '</select><button type="button" class="button contactpanda-open-bulk" disabled>' . esc_html__( 'Execute', 'contactpanda' ) . '</button><span class="contactpanda-bulk-selection" aria-live="polite">' . esc_html__( 'No person selected', 'contactpanda' ) . '</span><button type="button" class="button-link contactpanda-select-all-matching" hidden>' . esc_html( sprintf( __( 'Select all %d matching people', 'contactpanda' ), $total ) ) . '</button><span class="contactpanda-list-output-actions"><button type="button" class="button contactpanda-open-export"><span class="dashicons dashicons-download" aria-hidden="true"></span> ' . esc_html__( 'Export', 'contactpanda' ) . '</button><button type="button" class="button contactpanda-open-print"><span class="dashicons dashicons-printer" aria-hidden="true"></span> ' . esc_html__( 'Print list', 'contactpanda' ) . '</button></span></div>';
		echo '<div class="contactpanda-table-scroll"><table class="widefat striped contactpanda-table contactpanda-people-table"><thead><tr class="contactpanda-group-headings">';
			echo __( '<th class="check-column" scope="col" rowspan="2"><input type="checkbox" class="contactpanda-select-all" aria-label="Select all people on this page"></th>', 'contactpanda' );
			echo self::rowspan_heading( self::sortable_heading( __( 'First name', 'contactpanda' ), 'first_name', $orderby, $order, $customer_filter, $search, 'first_name' ) );
			echo self::rowspan_heading( self::sortable_heading( __( 'Last name', 'contactpanda' ), 'last_name', $orderby, $order, $customer_filter, $search, 'last_name' ) );
			echo __( '<th class="column-actions" rowspan="2"><span class="screen-reader-text">Actions</span></th>', 'contactpanda' );
			echo '<th rowspan="2" class="' . esc_attr( self::column_class( 'email', 'contactpanda-email-column' ) ) . '">' . esc_html__( 'Email', 'contactpanda' ) . '</th><th rowspan="2" class="' . esc_attr( self::column_class( 'phone' ) ) . '">' . esc_html__( 'Phone', 'contactpanda' ) . '</th>';
		if ( $companies_enabled ) {
				echo self::rowspan_heading( self::sortable_heading( __( 'Company', 'contactpanda' ), 'company', $orderby, $order, $customer_filter, $search, 'company' ) );
		}
			echo '<th class="' . esc_attr( self::column_class( 'orders', 'contactpanda-orders-group' ) ) . '" colspan="2" scope="colgroup">' . esc_html__( 'Orders', 'contactpanda' ) . '</th>';
		if ( $events_enabled ) {
			echo self::rowspan_heading( self::sortable_heading( __( 'Events', 'contactpanda' ), 'event_count', $orderby, $order, $customer_filter, $search, 'events' ) );
		}
			echo '<th rowspan="2" class="' . esc_attr( self::column_class( 'marketing' ) ) . '"><span class="screen-reader-text">' . esc_html__( 'Email confirmation and marketing status', 'contactpanda' ) . '</span></th></tr><tr class="contactpanda-order-subheadings">';
			echo self::sortable_heading( __( 'Number', 'contactpanda' ), 'order_count', $orderby, $order, $customer_filter, $search, 'orders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes dynamic values.
			echo self::sortable_heading( __( 'Order total', 'contactpanda' ), 'order_total', $orderby, $order, $customer_filter, $search, 'orders' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes dynamic values.
			echo '</tr></thead><tbody>';
		foreach ( $contacts as $contact ) {
			$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			$first_name = self::value( $contact, array( 'first_name' ) );
			$last_name = self::value( $contact, array( 'last_name' ) );
			$company = $companies_enabled && isset( $company_map[ $id ][0] ) ? $company_map[ $id ][0] : false;
			$status = self::optin_status( $contact );
			$order_summary = isset( $order_summaries[ $id ] ) ? $order_summaries[ $id ] : self::empty_order_summary();
			$event_summary = isset( $event_summaries[ $id ] ) ? $event_summaries[ $id ] : array( 'count' => 0, 'visited' => 0 );
			$edit_url = add_query_arg( array( 'page' => self::people_page_slug(), 'person' => $id, 'contactpanda_list_state' => $list_state ), admin_url( 'admin.php' ) );
			$view_url = add_query_arg( 'ansicht', 'anzeigen', $edit_url );
			$display_name = trim( $first_name . ' ' . $last_name );
				echo '<tr class="contactpanda-person-data-row"><th class="check-column" scope="row"><input type="checkbox" class="contactpanda-person-select" value="' . esc_attr( $id ) . '" aria-label="' . esc_attr( ( $display_name ?: __( 'Unnamed person', 'contactpanda' ) ) . __( ' select', 'contactpanda' ) ) . '"></th><td class="column-first_name contactpanda-first-name">';
			self::list_editable_field( __( 'First name', 'contactpanda' ), $first_name, 'first_name', 'contact', $id );
				echo '</td><td class="column-last_name contactpanda-last-name" title="' . esc_attr( $last_name ) . '">';
			self::list_editable_field( __( 'Last name', 'contactpanda' ), $last_name, 'last_name', 'contact', $id );
			echo '</td>';
				echo '<td class="column-actions"><div class="contactpanda-person-actions"><a class="contactpanda-person-row-action" href="' . esc_url( $edit_url ) . '" aria-label="' . esc_attr__( 'Edit person', 'contactpanda' ) . '" title="' . esc_attr__( 'Edit person', 'contactpanda' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><a class="contactpanda-person-row-action" href="' . esc_url( $view_url ) . '" aria-label="' . esc_attr__( 'View person', 'contactpanda' ) . '" title="' . esc_attr__( 'View person', 'contactpanda' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a>';
			if ( ContactPanda_Trash::available() ) {
				echo self::trash_button_html( 'contacts', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes all dynamic values.
			}
			echo '</div></td>';
			$email = self::value( $contact, array( 'email' ) );
				echo '<td class="' . esc_attr( self::column_class( 'email', 'contactpanda-email-column' ) ) . '"><div class="contactpanda-list-email"><span title="' . esc_attr( $email ) . '">' . esc_html( $email ?: '—' ) . '</span>';
			if ( is_email( $email ) ) {
				echo '<a class="contactpanda-mail-link" href="mailto:' . esc_attr( $email ) . '" aria-label="' . esc_attr__( 'Send email', 'contactpanda' ) . '" title="' . esc_attr__( 'Send email', 'contactpanda' ) . '"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span></a>';
			}
			echo '</div></td>';
			$primary_phone = self::meta( $contact, array( 'primary_phone' ) );
			echo '<td class="' . esc_attr( self::column_class( 'phone' ) ) . '">';
			self::list_editable_field( __( 'Phone', 'contactpanda' ), self::format_phone( $primary_phone ), 'primary_phone', 'contact', $id, 'phone', $primary_phone );
			echo '</td>';
			if ( $companies_enabled ) {
				$company_link = '—';
				if ( $company ) {
					$company_id   = absint( self::value( $company, array( 'id' ) ) );
					$company_name = self::value( $company, array( 'name' ) );
					$company_link = '<a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-firmen&firma=' . $company_id ) ) . '">' . esc_html( $company_name ) . '</a>';
				}
				echo '<td class="' . esc_attr( self::column_class( 'company' ) ) . '">';
				self::list_editable_field( __( 'Company', 'contactpanda' ), $company ? $company_name : '', 'company', 'contact', $id );
				echo '</td>';
			}
			echo '<td class="' . esc_attr( self::column_class( 'orders', 'contactpanda-order-count' ) ) . '">' . esc_html( $order_summary['count'] ) . '</td>';
			echo '<td class="' . esc_attr( self::column_class( 'orders', 'contactpanda-order-total' ) ) . '">' . wp_kses_post( $order_summary['formatted_total'] ) . '</td>';
			if ( $events_enabled ) {
				echo '<td class="' . esc_attr( self::column_class( 'events', 'contactpanda-event-count' ) ) . '">' . esc_html( $event_summary['count'] ?? $event_summary['visited'] ) . '</td>';
			}
			echo '<td class="' . esc_attr( self::column_class( 'marketing' ) ) . '"><div class="contactpanda-person-status-actions">' . self::email_confirmation_icon( $status, $contact ) . self::marketing_icon_badge( $status ) . '</div></td></tr>';
		}
		echo '</tbody></table></div>';
		self::render_bulk_marketing_modal();
		if ( class_exists( 'ContactPanda_Basic_Newsletter_Campaigns' ) ) self::render_newsletter_selection_modal();
		self::render_contact_output_modal( $total, $search, $customer_filter, $segment_id, $advanced_filter, $orderby, $order );
		self::render_new_person_modal();
		self::pagination( $page, $limit, $total, $search, array( 'kunden' => $customer_filter, 'orderby' => $orderby, 'order' => $order, 'segment' => $segment_id, 'contactpanda_filter' => $advanced_filter ) );
		echo '</section>';
	}

		private static function store_people_list_state( $contacts, $args ) {
			$user_id = get_current_user_id();
			if ( ! $user_id ) return '';
			$token = strtolower( wp_generate_password( 12, false, false ) );
			$ids = array_values( array_filter( array_map( static function ( $contact ) {
				return absint( self::value( $contact, array( 'id', 'ID' ) ) );
			}, (array) $contacts ) ) );
			$return_args = array( 'page' => self::people_page_slug() );
			foreach ( (array) $args as $key => $value ) {
				if ( '' !== (string) $value && 0 !== $value && 'alle' !== $value && 'asc' !== $value ) $return_args[ sanitize_key( $key ) ] = $value;
			}
			set_transient( 'contactpanda_people_state_' . $user_id . '_' . $token, array( 'ids' => $ids, 'total' => count( $ids ), 'return_url' => add_query_arg( $return_args, admin_url( 'admin.php' ) ) ), 2 * HOUR_IN_SECONDS );
			return $token;
		}

		private static function people_detail_navigation( $contact_id ) {
			$token = isset( $_GET['contactpanda_list_state'] ) ? substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) wp_unslash( $_GET['contactpanda_list_state'] ) ) ), 0, 24 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
			$state = $token ? get_transient( 'contactpanda_people_state_' . get_current_user_id() . '_' . $token ) : false;
			$result = array( 'token' => $token, 'back' => admin_url( 'admin.php?page=' . self::people_page_slug() ), 'previous' => '', 'next' => '', 'position' => 0, 'total' => 0 );
			if ( ! is_array( $state ) ) return $result;
			$result['back'] = ! empty( $state['return_url'] ) ? $state['return_url'] : $result['back'];
			$ids = array_values( array_map( 'absint', (array) ( $state['ids'] ?? array() ) ) );
			$position = array_search( absint( $contact_id ), $ids, true );
			if ( false === $position ) return $result;
			$result['position'] = $position + 1;
			$result['total'] = count( $ids );
			$base = array( 'page' => self::people_page_slug(), 'contactpanda_list_state' => $token );
			if ( $position > 0 ) $result['previous'] = add_query_arg( array_merge( $base, array( 'person' => $ids[ $position - 1 ] ) ), admin_url( 'admin.php' ) );
			if ( $position + 1 < count( $ids ) ) $result['next'] = add_query_arg( array_merge( $base, array( 'person' => $ids[ $position + 1 ] ) ), admin_url( 'admin.php' ) );
			return $result;
		}

	private static function render_person( $id ) {
		$contact = self::contact( $id );
		if ( ! $contact ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The person could not be loaded from the selected data source.', 'contactpanda' ) . '</p></div>';
			return;
		}

		$readonly = isset( $_GET['ansicht'] ) && 'anzeigen' === sanitize_key( wp_unslash( $_GET['ansicht'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$first_name = self::value( $contact, array( 'first_name', 'get_first_name' ) );
		$last_name = self::value( $contact, array( 'last_name', 'get_last_name' ) );
		$name = trim( $first_name . ' ' . $last_name );
		$email = self::value( $contact, array( 'email', 'get_email' ) );
		$orders = self::orders( array( $contact ) );
		$events_enabled = self::events_available();
		$events = $events_enabled ? self::contact_events( $contact ) : array();
		$broadcasts = self::contact_broadcasts( $id );
		$companies_enabled = self::companies_available();
		$company_map = $companies_enabled ? self::contact_company_map() : array();
		$companies = isset( $company_map[ $id ] ) ? $company_map[ $id ] : array();
		$company_names = array_values( array_filter( array_map( static function ( $company ) { return self::value( $company, array( 'name', 'company_name', 'get_name' ) ); }, $companies ) ) );
		$company_function = self::meta( $contact, array( 'company_function', 'funktion', 'role_at_company', 'job_function' ) );
		$user = self::contact_user( $contact );
		$customer = false;
		if ( $user && class_exists( 'WC_Customer' ) ) {
			try {
				$customer = new WC_Customer( $user->ID );
			} catch ( Throwable $e ) {
				$customer = false;
			}
		}
		$uid = $user ? (string) get_user_meta( $user->ID, 'billing_vat', true ) : '';
		if ( '' === $uid && $user ) {
			$uid = (string) get_user_meta( $user->ID, 'b2b_uid', true );
		}
		$uid_verified = $user && '' !== $uid && hash_equals( (string) get_user_meta( $user->ID, 'contactpanda_vat_verified_hash', true ), hash( 'sha256', self::normalize_vat( $uid ) ) );

		$navigation = self::people_detail_navigation( $id );
		echo '<header class="contactpanda-heading contactpanda-company-heading"><div class="contactpanda-avatar">' . esc_html( strtoupper( substr( $first_name ?: $last_name ?: 'P', 0, 1 ) ) ) . '</div><div><p class="contactpanda-eyebrow">' . ( $readonly ? esc_html__( 'View person', 'contactpanda' ) : esc_html__( 'Edit person', 'contactpanda' ) ) . '</p><h1>' . esc_html( $name ?: sprintf( __( 'Person #%d', 'contactpanda' ), $id ) ) . '</h1><p>' . esc_html( sprintf( ContactPanda_Store::standalone() ? __( 'ContactPanda ID %d', 'contactpanda' ) : __( 'Groundhogg ID %d', 'contactpanda' ), $id ) ) . '</p></div>';
		$primary_address = self::person_primary_address( $customer, $contact );
		if ( $readonly ) {
				$edit_args = array( 'page' => self::people_page_slug(), 'person' => $id );
				if ( $navigation['token'] ) $edit_args['contactpanda_list_state'] = $navigation['token'];
				echo '<a class="button button-primary contactpanda-heading-action" href="' . esc_url( add_query_arg( $edit_args, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit', 'contactpanda' ) . '</a>';
		}
		echo '</header>';
		echo '<div class="contactpanda-detail-navigation"><a class="contactpanda-back" href="' . esc_url( $navigation['back'] ) . '">' . esc_html__( '← Back to people overview', 'contactpanda' ) . '</a><span class="contactpanda-detail-navigation-meta">';
		if ( $navigation['position'] && $navigation['total'] ) echo '<strong>' . esc_html( sprintf( __( '%1$d of %2$d entries', 'contactpanda' ), $navigation['position'], $navigation['total'] ) ) . '</strong>';
		echo '<span class="contactpanda-detail-navigation-buttons">';
		if ( $navigation['previous'] ) echo '<a class="button" href="' . esc_url( $navigation['previous'] ) . '">' . esc_html__( '← Previous person', 'contactpanda' ) . '</a>';
		if ( $navigation['next'] ) echo '<a class="button" href="' . esc_url( $navigation['next'] ) . '">' . esc_html__( 'Next person →', 'contactpanda' ) . '</a>';
		echo '</span></span></div>';
		echo '<div class="contactpanda-grid"><aside>';
		echo '<section class="contactpanda-card contactpanda-master-data"><h2>' . esc_html__( 'Master data', 'contactpanda' ) . '</h2>';
		if ( $readonly ) {
			self::field( __( 'First name', 'contactpanda' ), $first_name );
			self::field( __( 'Last name', 'contactpanda' ), $last_name );
			echo '<div class="contactpanda-master-pair">';
			self::field( __( 'Prefix title', 'contactpanda' ), self::meta( $contact, array( 'titel_vorangestellt' ) ) );
			self::field( __( 'Suffix title', 'contactpanda' ), self::meta( $contact, array( 'titel_nachgestellt' ) ) );
			echo '</div>';
			self::field( __( 'Salutation', 'contactpanda' ), self::salutation_label( self::meta( $contact, array( 'anrede', 'salutation' ) ) ) );
			self::render_contact_channels( $id, $contact, false );
			self::field( __( 'Address', 'contactpanda' ), $primary_address );
			self::field( __( 'Position / Job title', 'contactpanda' ), self::meta( $contact, array( 'berufsbezeichnung', 'job_title', 'position' ) ) );
			if ( ContactPanda_Store::standalone() ) self::render_contact_companies( $id, true );
			else self::field( __( 'Company', 'contactpanda' ), implode( ', ', $company_names ) );
			if ( $company_function ) self::field( __( 'Function in company', 'contactpanda' ), $company_function );
			if ( $user ) self::readonly_uid_field( __( 'VAT ID', 'contactpanda' ), $uid, $uid_verified );
		} else {
			self::contact_editable_field( __( 'First name', 'contactpanda' ), $first_name, 'first_name', $id );
			self::contact_editable_field( __( 'Last name', 'contactpanda' ), $last_name, 'last_name', $id );
			echo '<div class="contactpanda-master-pair">';
			self::contact_editable_field( __( 'Prefix title', 'contactpanda' ), self::meta( $contact, array( 'titel_vorangestellt' ) ), 'titel_vorangestellt', $id );
			self::contact_editable_field( __( 'Suffix title', 'contactpanda' ), self::meta( $contact, array( 'titel_nachgestellt' ) ), 'titel_nachgestellt', $id );
			echo '</div>';
			self::salutation_field( self::meta( $contact, array( 'anrede', 'salutation' ) ), $first_name, $id );
			self::render_contact_channels( $id, $contact, true );
			self::editable_address_summary( __( 'Address', 'contactpanda' ), $primary_address );
			self::contact_editable_field( __( 'Position / Job title', 'contactpanda' ), self::meta( $contact, array( 'berufsbezeichnung', 'job_title', 'position' ) ), 'berufsbezeichnung', $id );
			if ( ContactPanda_Store::standalone() ) self::render_contact_companies( $id, false );
			else self::contact_editable_field( __( 'Company', 'contactpanda' ), implode( ', ', $company_names ), 'company', $id );
			if ( $company_function ) self::field( __( 'Function in company', 'contactpanda' ), $company_function );
			if ( $user ) self::uid_field( __( 'VAT ID', 'contactpanda' ), $uid, $id, 'contact', $uid_verified );
		}
		if ( current_user_can( 'manage_options' ) ) {
			self::render_user_account_controls( $id, $contact, $user, $readonly );
		}
		echo '</section>';
		do_action( 'contactpanda_render_person_custom_fields', $id, $contact, $readonly, 'sidebar' );

		self::render_contact_tags( $contact, ! $readonly );
		self::render_marketing( $contact, ! $readonly );
		echo '</aside><main>';

		self::render_person_tabs( $id, $customer, $contact, $orders, $events, $events_enabled, $broadcasts, ! $readonly );
		echo '</main></div>';
	}

	private static function render_contact_channels( $contact_id, $contact, $editable ) {
		$channels = self::contact_channels( $contact );
		foreach ( array( 'email' => __( 'Email addresses', 'contactpanda' ), 'phone' => __( 'Phone numbers', 'contactpanda' ) ) as $type => $heading ) {
			echo '<div class="contactpanda-channel-group" data-contact="' . esc_attr( $contact_id ) . '" data-channel-type="' . esc_attr( $type ) . '"><div class="contactpanda-channel-heading"><span>' . esc_html( $heading ) . '</span>';
			if ( $editable ) {
				$add_label = 'email' === $type ? __( 'Add email address', 'contactpanda' ) : __( 'Add phone number', 'contactpanda' );
				echo '<button type="button" class="button contactpanda-add-channel" aria-label="' . esc_attr( $add_label ) . '" title="' . esc_attr( $add_label ) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $add_label ) . '</span></button>';
			}
			echo '</div><div class="contactpanda-channel-list">';
			$type_channels = array_values( array_filter( $channels, static function ( $channel ) use ( $type ) { return $type === $channel['type']; } ) );
			if ( ! $type_channels ) echo '<span class="contactpanda-muted contactpanda-channel-empty">' . esc_html( 'email' === $type ? __( 'No email address saved.', 'contactpanda' ) : __( 'No phone number saved.', 'contactpanda' ) ) . '</span>';
			foreach ( $type_channels as $channel ) self::render_contact_channel_row( $contact_id, $channel, $editable );
			echo '</div>';
			if ( $editable ) {
				echo '<template class="contactpanda-channel-template">';
				self::render_contact_channel_row( $contact_id, array( 'id' => '', 'type' => $type, 'label' => 'email' === $type ? 'private' : 'mobile', 'value' => '', 'is_primary' => empty( $type_channels ) ? 1 : 0, 'marketing_status' => 'email' === $type ? 'unconfirmed' : '' ), true, true );
				echo '</template>';
			}
			echo '<div class="contactpanda-channel-message" aria-live="polite"></div></div>';
		}
	}

	private static function render_contact_channel_row( $contact_id, $channel, $editable, $is_new = false ) {
		$response = self::contact_channel_response( $channel );
		$type = $response['type'];
		$primary = ! empty( $response['is_primary'] );
		echo '<div class="contactpanda-channel-row' . ( $is_new ? ' is-new' : '' ) . '" data-contact="' . esc_attr( $contact_id ) . '" data-channel-id="' . esc_attr( $response['id'] ) . '" data-channel-type="' . esc_attr( $type ) . '" data-primary="' . ( $primary ? '1' : '0' ) . '">';
		echo '<div class="contactpanda-channel-summary">';
		if ( $editable ) echo '<button type="button" class="button-link contactpanda-channel-value" data-value="' . esc_attr( $response['value'] ) . '">' . esc_html( $response['display'] ?: __( 'Add', 'contactpanda' ) ) . '</button>';
		else echo '<strong>' . esc_html( $response['display'] ?: '—' ) . '</strong>';
		echo '<span class="contactpanda-channel-label">' . esc_html( $response['label_text'] ) . '</span>';
		echo '<span class="contactpanda-primary-badge"' . ( $primary ? '' : ' hidden' ) . '>' . esc_html__( 'Primary', 'contactpanda' ) . '</span>';
		if ( 'email' === $type ) echo '<span class="contactpanda-channel-marketing">' . esc_html( $response['marketing_label'] ) . '</span>';
		if ( $response['value'] ) {
			$action_label = 'email' === $type ? __( 'Send email', 'contactpanda' ) : __( 'Call phone number', 'contactpanda' );
			$href = 'email' === $type ? 'mailto:' . sanitize_email( $response['value'] ) : 'tel:' . preg_replace( '/[^0-9+]/', '', $response['value'] );
			$icon = 'email' === $type ? 'email-alt' : 'phone';
			echo '<a class="contactpanda-channel-action" href="' . esc_attr( $href ) . '" aria-label="' . esc_attr( $action_label ) . '" title="' . esc_attr( $action_label ) . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span></a>';
		}
		echo '</div>';
		if ( $editable ) {
			echo '<div class="contactpanda-channel-editor is-' . esc_attr( $type ) . '"' . ( $is_new ? '' : ' hidden' ) . '><label><span>' . esc_html__( 'Type', 'contactpanda' ) . '</span><select class="contactpanda-channel-label-select">';
			foreach ( self::contact_channel_labels( $type ) as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $response['label'], $key, false ) . '>' . esc_html( $label ) . '</option>';
			echo '</select></label>';
			if ( 'phone' === $type ) echo self::phone_input_html( $response['value'], true );
			else echo '<label class="contactpanda-channel-value-field"><span>' . esc_html__( 'Email address', 'contactpanda' ) . '</span><input type="email" class="contactpanda-channel-input" value="' . esc_attr( $response['value'] ) . '" autocomplete="email"></label>';
			echo '<label class="contactpanda-channel-primary"><input type="checkbox" class="contactpanda-channel-primary-input" value="1"' . checked( $primary, true, false ) . ( $primary && ! $is_new ? ' disabled' : '' ) . '> ' . esc_html( 'email' === $type ? __( 'Use as primary email address', 'contactpanda' ) : __( 'Use as primary phone number', 'contactpanda' ) ) . '</label>';
			echo '<div class="contactpanda-channel-actions"><button type="button" class="button button-primary contactpanda-save-channel">' . esc_html__( 'Save', 'contactpanda' ) . '</button><button type="button" class="button contactpanda-cancel-channel">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button>';
			echo '<button type="button" class="button-link-delete contactpanda-delete-channel"' . ( $primary || $is_new ? ' hidden' : '' ) . '>' . esc_html__( 'Remove', 'contactpanda' ) . '</button>';
			echo '</div></div>';
		}
		echo '</div>';
	}

	private static function render_contact_companies( $contact_id, $readonly ) {
		$relations = ContactPanda_Store::contact_company_relations( $contact_id );
		echo '<div class="contactpanda-company-relations"><div class="contactpanda-channel-heading"><span>' . esc_html__( 'Companies', 'contactpanda' ) . '</span></div>';
		if ( ! $relations ) echo '<p class="contactpanda-muted">' . esc_html__( 'No company assigned.', 'contactpanda' ) . '</p>';
		foreach ( $relations as $relation ) {
			$modal_id = 'contactpanda-company-relation-' . absint( $contact_id ) . '-' . absint( $relation['company_id'] );
			echo '<div class="contactpanda-company-relation">';
			if ( ! $readonly ) echo '<button type="button" class="button-link contactpanda-company-relation-summary contactpanda-open-company-relation" data-modal="' . esc_attr( $modal_id ) . '" aria-haspopup="dialog">';
			else echo '<div class="contactpanda-company-relation-summary">';
			echo '<strong>' . esc_html( $relation['name'] ) . '</strong>';
			if ( $relation['position'] ) echo '<small>' . esc_html( $relation['position'] ) . '</small>';
			if ( ! empty( $relation['is_primary_company'] ) ) echo '<span class="contactpanda-primary-badge">' . esc_html__( 'Primary company', 'contactpanda' ) . '</span>';
			echo $readonly ? '</div>' : '</button>';
			if ( ! $readonly ) {
				echo '<div id="' . esc_attr( $modal_id ) . '" class="contactpanda-consent-modal contactpanda-company-relation-modal" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $modal_id . '-title' ) . '" hidden><div class="contactpanda-modal-panel"><h2 id="' . esc_attr( $modal_id . '-title' ) . '">' . esc_html__( 'Edit company assignment', 'contactpanda' ) . '</h2><p><strong>' . esc_html( $relation['name'] ) . '</strong></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-company-relation-form"><input type="hidden" name="action" value="contactpanda_contact_company"><input type="hidden" name="contact_id" value="' . esc_attr( $contact_id ) . '"><input type="hidden" name="company_id" value="' . esc_attr( $relation['company_id'] ) . '">';
				wp_nonce_field( 'contactpanda_contact_company_' . $contact_id );
				echo '<label><span>' . esc_html__( 'Position / role', 'contactpanda' ) . '</span><input name="position" value="' . esc_attr( $relation['position'] ) . '"></label><label class="contactpanda-company-primary"><input type="checkbox" name="primary_company" value="1"' . checked( ! empty( $relation['is_primary_company'] ), true, false ) . '> ' . esc_html__( 'Use as primary company', 'contactpanda' ) . '</label><div class="contactpanda-modal-actions"><button class="button button-primary" name="operation" value="update">' . esc_html__( 'Save', 'contactpanda' ) . '</button><button type="button" class="button contactpanda-close-company-relation">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button><button class="button-link-delete" name="operation" value="remove" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Remove this company assignment?', 'contactpanda' ) ) ) . ');">' . esc_html__( 'Remove assignment', 'contactpanda' ) . '</button></div></form></div></div>';
			}
			echo '</div>';
		}
		if ( ! $readonly ) {
			$add_modal_id = 'contactpanda-company-relation-add-' . absint( $contact_id );
			$add_label = $relations ? __( 'Assign another company', 'contactpanda' ) : __( 'Assign company', 'contactpanda' );
			echo '<button type="button" class="button contactpanda-open-company-relation contactpanda-company-relation-add" data-modal="' . esc_attr( $add_modal_id ) . '" aria-haspopup="dialog"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html( $add_label ) . '</button>';
			echo '<div id="' . esc_attr( $add_modal_id ) . '" class="contactpanda-consent-modal contactpanda-company-relation-modal" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $add_modal_id . '-title' ) . '" hidden><div class="contactpanda-modal-panel"><h2 id="' . esc_attr( $add_modal_id . '-title' ) . '">' . esc_html( $add_label ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_contact_company"><input type="hidden" name="operation" value="add"><input type="hidden" name="contact_id" value="' . esc_attr( $contact_id ) . '">';
			wp_nonce_field( 'contactpanda_contact_company_' . $contact_id );
			echo '<label><span>' . esc_html__( 'Company', 'contactpanda' ) . '</span><select name="company_id"><option value="0">' . esc_html__( 'Select company', 'contactpanda' ) . '</option>';
			foreach ( ContactPanda_Store::companies() as $company ) echo '<option value="' . esc_attr( $company['id'] ) . '">' . esc_html( $company['name'] ) . '</option>';
			echo '</select></label><label><span>' . esc_html__( 'Or create company', 'contactpanda' ) . '</span><input name="company_name"></label><label><span>' . esc_html__( 'Position / role', 'contactpanda' ) . '</span><input name="position"></label><label><input type="checkbox" name="primary_company" value="1"' . checked( empty( $relations ), true, false ) . '> ' . esc_html__( 'Use as primary company', 'contactpanda' ) . '</label><div class="contactpanda-modal-actions"><button class="button button-primary">' . esc_html( $add_label ) . '</button><button type="button" class="button contactpanda-close-company-relation">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button></div></form></div></div>';
		}
		echo '</div>';
	}

	public static function handle_contact_company() {
		if ( ! current_user_can( self::access_capability() ) || ! ContactPanda_Store::standalone() ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		check_admin_referer( 'contactpanda_contact_company_' . $contact_id );
		$operation = sanitize_key( wp_unslash( $_POST['operation'] ?? '' ) );
		$company_id = absint( $_POST['company_id'] ?? 0 );
		if ( 'add' === $operation && ! $company_id ) {
			$name = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) );
			$company_id = $name ? ContactPanda_Store::company_by_name( $name ) : 0;
			if ( ! $company_id && $name ) $company_id = ContactPanda_Store::create_company( array( 'name' => $name, 'legal_name' => $name ) );
		}
		if ( $contact_id && $company_id ) {
			if ( 'remove' === $operation ) ContactPanda_Store::remove_company_contact( $company_id, $contact_id );
			elseif ( 'update' === $operation ) ContactPanda_Store::update_contact_company_relation( $company_id, $contact_id, sanitize_text_field( wp_unslash( $_POST['position'] ?? '' ) ), ! empty( $_POST['primary_company'] ) );
			elseif ( 'add' === $operation ) ContactPanda_Store::link_company_contact( $company_id, $contact_id, sanitize_text_field( wp_unslash( $_POST['position'] ?? '' ) ), false, ! empty( $_POST['primary_company'] ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-people&person=' . $contact_id ) );
		exit;
	}

	private static function companies_available() {
		if ( ContactPanda_Store::standalone() ) {
			return true;
		}
		if ( defined( 'GROUNDHOGG_COMPANIES_VERSION' ) || class_exists( '\\GroundhoggCompanies\\Company' ) ) {
			return true;
		}
		if ( function_exists( 'wp_get_ability' ) ) {
			return (bool) wp_get_ability( 'groundhogg-companies/list-companies' );
		}
		return false;
	}

	private static function people_page_slug() {
		return 'contactpanda-people';
	}

	private static function events_available() {
		return (bool) apply_filters( 'contactpanda_events_available', false );
	}

	private static function dependencies() {
		if ( ContactPanda_Store::standalone() ) {
			return array( 'ContactPanda database' => ContactPanda_Store::ready() ? ContactPanda_Schema::VERSION : false );
		}
		return array(
			'Groundhogg'           => defined( 'GROUNDHOGG_VERSION' ) ? GROUNDHOGG_VERSION : false,
			'Groundhogg Companies' => self::companies_available(),
		);
	}

	private static function render_trash_link( $entity, $id = 0, $cell = false ) {
		if ( ! ContactPanda_Trash::available() ) return;
		$label = $id ? __( 'Move to Trash', 'contactpanda' ) : __( 'Trash and bulk actions', 'contactpanda' );
		if ( $cell ) {
			echo '<td class="contactpanda-trash-column">' . self::trash_button_html( $entity, $id ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes all dynamic values.
			return;
		}
		echo '<p><a class="button" href="' . esc_url( ContactPanda_Trash::url( $entity, 0, true ) ) . '">' . esc_html( $label ) . '</a></p>';
	}

	private static function trash_button_html( $entity, $id ) {
		$label = __( 'Move to Trash', 'contactpanda' );
		return '<button type="button" class="button contactpanda-trash-row-action contactpanda-trash-direct" data-entity="' . esc_attr( $entity ) . '" data-record-id="' . esc_attr( $id ) . '" data-operation="trash" data-record-name="#' . esc_attr( $id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'contactpanda_record_action' ) ) . '" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html( $label ) . '</span></button>';
	}

	private static function render_index() {
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
		echo '<div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CRM</p><h1>' . esc_html__( 'Companies', 'contactpanda' ) . '</h1><p>' . esc_html__( 'A unified view of companies, contacts, orders, and invoices.', 'contactpanda' ) . '</p></div><div class="contactpanda-heading-actions">';
		do_action( 'contactpanda_company_heading_actions' );
		if ( current_user_can( 'manage_options' ) ) echo '<a class="button contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-data-transfer&transfer_section=import&entity=companies' ) ) . '">' . esc_html__( 'Import', 'contactpanda' ) . '</a>';
		echo '<button type="button" class="button button-primary contactpanda-heading-action contactpanda-open-new-company">' . esc_html__( 'Add company', 'contactpanda' ) . '</button></div></div>';

		$missing = array_filter( self::dependencies(), static function ( $value ) { return false === $value; } );
		if ( $missing ) {
			echo __( '<div class="notice notice-warning inline"><p><strong>Diagnostics:</strong> Not all required extensions were detected: ', 'contactpanda' ) . esc_html( implode( ', ', array_keys( $missing ) ) ) . '.</p></div>';
		}

		$companies = self::companies();
		$import_run = isset( $_GET['import_run'] ) ? absint( $_GET['import_run'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only import-history filter.
		if ( $import_run ) {
			$import_ids = ContactPanda_Store::import_record_ids( $import_run, 'companies' );
			$companies = array_values( array_filter( $companies, static function ( $company ) use ( $import_ids ) { return in_array( absint( self::value( $company, array( 'id', 'ID', 'get_id' ) ) ), $import_ids, true ); } ) );
		}
		if ( '' !== $search ) {
			$companies = array_values( array_filter( $companies, static function ( $company ) use ( $search ) {
				$haystack = self::value( $company, array( 'get_name', 'name', 'company_name' ) ) . ' ' . ContactPanda_Custom_Fields::searchable_text( $company, 'companies' );
				return false !== stripos( $haystack, $search );
			} ) );
		}
		$companies = apply_filters( 'contactpanda_company_records', $companies, isset( $_GET['segment'] ) ? absint( $_GET['segment'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only saved-search filter.
		echo __( '<section class="contactpanda-card"><div class="contactpanda-toolbar"><h2>Companies overview</h2><span class="contactpanda-count">', 'contactpanda' ) . esc_html( count( $companies ) ) . __( ' entries</span></div>', 'contactpanda' );
		echo __( '<form class="contactpanda-search" method="get"><input type="hidden" name="page" value="contactpanda-firmen"><label class="screen-reader-text" for="contactpanda-company-search">Search companies</label><input id="contactpanda-company-search" type="search" name="s" value="', 'contactpanda' ) . esc_attr( $search ) . __( '" placeholder="Search company name"><button class="button">Search</button>', 'contactpanda' );
		if ( '' !== $search ) {
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-firmen' ) ) . __( '">Show all</a>', 'contactpanda' );
		}
		echo '</form>';
		self::render_trash_link( 'companies' );
		if ( ! $companies ) {
			$empty_message = $search
				? __( 'No result was found for this company name.', 'contactpanda' )
				: ( ContactPanda_Store::standalone() ? __( 'No companies have been created in ContactPanda yet.', 'contactpanda' ) : __( 'Groundhogg Companies returned no companies.', 'contactpanda' ) );
			echo __( '<div class="contactpanda-empty"><span class="dashicons dashicons-building"></span><h3>No companies found</h3><p>', 'contactpanda' ) . esc_html( $empty_message ) . '</p></div></section>';
			self::render_new_company_modal();
			self::render_status();
			return;
		}

		echo '<table class="widefat striped contactpanda-table contactpanda-companies-table"><thead><tr><th>' . esc_html__( 'Company', 'contactpanda' ) . '</th>';
			echo '<th class="' . esc_attr( self::column_class( 'people' ) ) . '">' . esc_html__( 'Assigned people', 'contactpanda' ) . '</th><th class="' . esc_attr( self::column_class( 'website' ) ) . '">' . esc_html__( 'Website', 'contactpanda' ) . '</th><th class="' . esc_attr( self::column_class( 'phone' ) ) . '">' . esc_html__( 'Phone', 'contactpanda' ) . '</th><th class="' . esc_attr( self::column_class( 'location' ) ) . '">' . esc_html__( 'Location', 'contactpanda' ) . '</th><th class="contactpanda-company-actions-column"><span class="screen-reader-text">' . esc_html__( 'Actions', 'contactpanda' ) . '</span></th></tr></thead><tbody>';
		foreach ( $companies as $company ) {
			$id = self::value( $company, array( 'get_id', 'ID', 'id' ) );
			$edit_url = admin_url( 'admin.php?page=contactpanda-firmen&firma=' . absint( $id ) );
			$view_url = add_query_arg( 'ansicht', 'anzeigen', $edit_url );
			$person_links = array();
			foreach ( self::contacts( $company ) as $contact ) {
				if ( is_scalar( $contact ) ) {
					$contact = self::contact( absint( $contact ) );
				}
				if ( ! $contact ) {
					continue;
				}
				$contact_id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
				$name = trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) );
				if ( '' === $name ) {
					$name = self::value( $contact, array( 'email', 'get_email' ), $contact_id ? sprintf( __( 'Person #%d', 'contactpanda' ), $contact_id ) : __( 'Unnamed person', 'contactpanda' ) );
				}
				$person_links[] = $contact_id
					? '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ) ) . '">' . esc_html( $name ) . '</a>'
					: esc_html( $name );
			}
			echo '<tr><td><strong>' . esc_html( self::value( $company, array( 'get_name', 'name', 'company_name' ), __( 'Unnamed company', 'contactpanda' ) ) ) . '</strong></td>';
				echo '<td class="' . esc_attr( self::column_class( 'people', 'contactpanda-company-people' ) ) . '">' . ( $person_links ? wp_kses_post( implode( ', ', $person_links ) ) : '&mdash;' ) . '</td>';
				echo '<td class="' . esc_attr( self::column_class( 'website' ) ) . '">' . esc_html( self::value( $company, array( 'domain', 'website', 'get_website' ), '—' ) ) . '</td>';
			$company_phone = self::value( $company, array( 'phone', 'get_phone' ) );
				echo '<td class="' . esc_attr( self::column_class( 'phone' ) ) . '">';
			self::list_editable_field( __( 'Phone', 'contactpanda' ), self::format_phone( $company_phone ), 'phone', 'company', $id, 'phone', $company_phone );
				echo '</td><td class="' . esc_attr( self::column_class( 'location' ) ) . '">';
			self::list_editable_field( __( 'Location', 'contactpanda' ), self::value( $company, array( 'city', 'get_city' ) ), 'city', 'company', $id );
			echo '</td><td class="contactpanda-company-actions-column"><div class="contactpanda-person-actions contactpanda-company-actions"><a class="contactpanda-person-row-action" href="' . esc_url( $edit_url ) . '" aria-label="' . esc_attr__( 'Edit company', 'contactpanda' ) . '" title="' . esc_attr__( 'Edit company', 'contactpanda' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><a class="contactpanda-person-row-action" href="' . esc_url( $view_url ) . '" aria-label="' . esc_attr__( 'View company', 'contactpanda' ) . '" title="' . esc_attr__( 'View company', 'contactpanda' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a>';
			if ( ContactPanda_Trash::available() ) echo self::trash_button_html( 'companies', $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes all dynamic values.
			echo '</div></td>';
			echo '</tr>';
		}
		echo '</tbody></table></section>';
		self::render_new_company_modal();
	}

	private static function create_company_record( $name ) {
		$name = sanitize_text_field( $name );
		if ( ContactPanda_Store::standalone() ) {
			$existing = ContactPanda_Store::company_by_name( $name );
			return $existing ?: ContactPanda_Store::create_company( array( 'name' => $name ) );
		}
		$result = self::execute_ability( 'groundhogg-companies/create-company', array( 'name' => $name ) );
		$id = is_array( $result ) ? absint( self::value( $result, array( 'id', 'company_id', 'ID' ) ) ) : 0;
		if ( ! $id && is_array( $result ) && isset( $result['company'] ) ) {
			$id = absint( self::value( $result['company'], array( 'id', 'company_id', 'ID' ) ) );
		}
		return $id;
	}

	private static function assign_contact_company( $contact_id, $company_id, $company_name, $position ) {
		if ( ContactPanda_Store::standalone() ) {
			$company_id = $company_id && ContactPanda_Store::company( $company_id ) ? $company_id : ContactPanda_Store::company_by_name( $company_name );
			if ( ! $company_id ) {
				$company_id = ContactPanda_Store::create_company( array( 'name' => $company_name ) );
			}
			$linked = $company_id && ContactPanda_Store::link_company_contact( $company_id, $contact_id, $position );
			if ( $linked && '' !== $position ) {
				ContactPanda_Store::update_contact( $contact_id, array( 'position' => $position, 'berufsbezeichnung' => $position ) );
			}
			return $linked ? true : new WP_Error( 'contactpanda_company_link', __( 'The person was created, but the company assignment could not be saved.', 'contactpanda' ) );
		}
		if ( ! $company_id ) {
			foreach ( self::companies() as $company ) {
				if ( 0 === strcasecmp( trim( self::value( $company, array( 'name', 'company_name', 'get_name' ) ) ), trim( $company_name ) ) ) {
					$company_id = absint( self::value( $company, array( 'id', 'ID', 'get_id' ) ) );
					break;
				}
			}
		}
		if ( ! $company_id ) {
			$company_id = self::create_company_record( $company_name );
		}
		if ( ! $company_id ) {
			return new WP_Error( 'contactpanda_company_create', __( 'The person was created, but the company could not be created.', 'contactpanda' ) );
		}
		$result = self::execute_ability( 'groundhogg-companies/add-contact-to-company', array( 'company_id' => $company_id, 'contact_id' => $contact_id ) );
		if ( false !== $result && '' !== $position ) {
			self::execute_ability(
				'groundhogg/update-contact',
				array(
					'id'   => $contact_id,
					'meta' => array(
						'job_title'          => $position,
						'berufsbezeichnung' => $position,
					),
				)
			);
		}
		return false === $result ? new WP_Error( 'contactpanda_company_link', __( 'The person was created, but the company assignment could not be saved.', 'contactpanda' ) ) : true;
	}

	private static function companies() {
		if ( ! self::companies_available() ) {
			return array();
		}
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::companies();
		}
		$result = self::execute_ability(
			'groundhogg-companies/list-companies',
			array(
					'limit'  => 200,
				'offset' => 0,
				'expand' => array( 'meta', 'contacts' ),
			)
		);
		if ( is_array( $result ) && isset( $result['companies'] ) && is_array( $result['companies'] ) ) {
			return $result['companies'];
		}

		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) {
			return array();
		}
		try {
			$db = \Groundhogg\get_db( 'companies' );
			if ( ! $db || ! method_exists( $db, 'query' ) ) {
				return array();
			}
			$rows = $db->query( array( 'limit' => 100, 'orderby' => 'name', 'order' => 'ASC' ) );
			return is_array( $rows ) ? $rows : array();
		} catch ( Throwable $e ) {
			return array();
		}
	}

	private static function company( $id ) {
		if ( ! self::companies_available() ) {
			return false;
		}
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::company( $id );
		}
		$result = self::execute_ability(
			'groundhogg-companies/get-company',
			array(
				'id'     => $id,
				'expand' => array( 'meta', 'contacts' ),
			)
		);
		if ( is_array( $result ) && ! empty( $result['id'] ) ) {
			return $result;
		}

		if ( function_exists( '\\Groundhogg\\get_db' ) ) {
			try {
				$db = \Groundhogg\get_db( 'companies' );
				if ( $db && method_exists( $db, 'get' ) ) {
					return $db->get( $id );
				}
			} catch ( Throwable $e ) {
				return false;
			}
		}
		return false;
	}

	private static function contact_search( $search = '', $limit = 50, $offset = 0 ) {
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::search_contacts( $search, $limit, $offset );
		}
		$input = array(
			'limit'  => $limit,
			'offset' => $offset,
			// The people list only reads contact fields and metadata. Loading tags and
			// work details for every row needlessly inflates Groundhogg responses on
			// larger sites; those expansions remain available in the detail view.
			'expand' => array( 'meta' ),
		);
		if ( '' !== $search ) {
			$input['search'] = $search;
		}
		$result = self::execute_ability( 'groundhogg/search-contacts', $input );
		return is_array( $result ) ? $result : array();
	}

	private static function all_contacts( $search = '' ) {
		// Groundhogg's ability response can report the current page size as its
		// total on some installations. The advanced search must never trust that
		// value, because doing so silently limits every filter to the first page.
		if ( ! ContactPanda_Store::standalone() ) {
			$complete = self::groundhogg_all_contacts( $search );
			if ( is_array( $complete ) ) {
				return $complete;
			}
		}

		$contacts = array();
		$offset = 0;
		$batch_size = 100;
		$total = null;
		$stalled = false;

		do {
			$before = count( $contacts );
			$result = self::contact_search( $search, $batch_size, $offset );
			$batch = isset( $result['contacts'] ) && is_array( $result['contacts'] ) ? $result['contacts'] : array();
			if ( null === $total && isset( $result['total_items'] ) ) {
				$total = absint( $result['total_items'] );
			}
			foreach ( $batch as $contact ) {
				$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				if ( $id ) {
					$contacts[ $id ] = $contact;
				}
			}
			$received = count( $batch );
			$offset += $received;
			$added = count( $contacts ) - $before;
			if ( $received > 0 && 0 === $added ) {
				$stalled = true;
			}
		} while ( $received > 0 && $added > 0 && ( null === $total || $offset < $total ) );

		// Some older Groundhogg ability builds can repeat the first result page or
		// stop before their reported total. In that case, use Groundhogg's own
		// contact query to retrieve the complete read-only result set.
		if ( ! ContactPanda_Store::standalone() && ( null === $total || $stalled || count( $contacts ) < $total ) ) {
			$complete = self::groundhogg_all_contacts( $search );
			if ( is_array( $complete ) ) {
				return $complete;
			}
		}

		return array_values( $contacts );
	}

	/**
	 * Return only the fields needed to match WooCommerce orders and event
	 * attendees. Full contacts are hydrated after filtering and pagination.
	 */
	private static function all_contact_identities( $search = '' ) {
		if ( ContactPanda_Store::standalone() ) {
			return self::all_contacts( $search );
		}
		global $wpdb;
		$table = $wpdb->prefix . 'gh_contacts';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return self::all_contacts( $search );
		}
		$sql = "SELECT ID AS id, user_id, email, first_name, last_name, optin_status FROM `{$table}`"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed Groundhogg table name.
		$needle = trim( (string) $search );
		if ( '' !== $needle ) {
			$like = '%' . $wpdb->esc_like( $needle ) . '%';
			$sql .= $wpdb->prepare( ' WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s', $like, $like, $like );
		}
		$sql .= ' ORDER BY ID ASC';
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- optional search is prepared above.
		return is_array( $rows ) ? $rows : array();
	}

	/** Hydrate only the current result page after a lightweight relationship filter. */
	private static function hydrate_contact_identities( $identities ) {
		$contacts = array();
		foreach ( (array) $identities as $identity ) {
			$id = absint( self::value( $identity, array( 'id', 'ID' ) ) );
			if ( ! $id ) continue;
			if ( class_exists( '\\Groundhogg\\Contact' ) ) {
				try {
					$contact = new \Groundhogg\Contact( $id );
					if ( ! method_exists( $contact, 'exists' ) || $contact->exists() ) {
						$contacts[] = $contact;
						continue;
					}
				} catch ( Throwable $e ) {
					// Fall through to the stable ability adapter.
				}
			}
			$contact = self::contact( $id );
			if ( $contact ) $contacts[] = $contact;
		}
		return $contacts;
	}

	private static function groundhogg_all_contacts( $search = '' ) {
		// Reading the Groundhogg contact IDs directly is the most stable way to
		// avoid version-specific pagination differences in its query/ability APIs.
		// Contact objects remain Groundhogg-owned; ContactPanda does not duplicate or
		// change any data here.
		$from_table = self::groundhogg_contacts_from_table( $search );
		if ( is_array( $from_table ) ) {
			return $from_table;
		}

		if ( ! class_exists( '\\Groundhogg\\Contact_Query' ) ) {
			return null;
		}

		$contacts = array();
		$offset = 0;
		$batch_size = 100;
		do {
			$args = array(
				'number'        => $batch_size,
				'offset'        => $offset,
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'no_found_rows' => false,
			);
			if ( '' !== $search ) {
				$args['search'] = $search;
			}
			try {
				$query = new \Groundhogg\Contact_Query();
				$batch = (array) $query->query( $args, true );
			} catch ( Throwable $e ) {
				return null;
			}
			foreach ( $batch as $contact ) {
				if ( class_exists( '\\Groundhogg\\Abilities\\Schemas\\Contact_Schema' ) ) {
					try {
						$contact = \Groundhogg\Abilities\Schemas\Contact_Schema::transform( $contact, array( 'tags', 'meta', 'work_details' ) );
					} catch ( Throwable $e ) {
						// The original Contact object still exposes all standard fields.
					}
				}
				$id = absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) );
				if ( $id ) {
					$contacts[ $id ] = $contact;
				}
			}
			$received = count( $batch );
			$offset += $received;
		} while ( $received === $batch_size );

		return array_values( $contacts );
	}

	private static function groundhogg_contacts_from_table( $search = '' ) {
		if ( ! class_exists( '\\Groundhogg\\Contact' ) ) {
			return null;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'gh_contacts';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return null;
		}

		$ids = $wpdb->get_col( "SELECT ID FROM `{$table}` ORDER BY ID ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed Groundhogg table name.
		$contacts = array();
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
		foreach ( (array) $ids as $id ) {
			try {
				$contact = new \Groundhogg\Contact( absint( $id ) );
			} catch ( Throwable $e ) {
				continue;
			}
			if ( method_exists( $contact, 'exists' ) && ! $contact->exists() ) {
				continue;
			}
			if ( '' !== $needle ) {
				$haystack = implode( ' ', array(
					self::value( $contact, array( 'first_name', 'get_first_name' ) ),
					self::value( $contact, array( 'last_name', 'get_last_name' ) ),
					self::value( $contact, array( 'email', 'get_email' ) ),
					ContactPanda_Custom_Fields::searchable_text( $contact, 'contacts' ),
				) );
				$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
				if ( false === strpos( $haystack, $needle ) ) {
					continue;
				}
			}
			$contacts[ absint( $id ) ] = $contact;
		}
		return array_values( $contacts );
	}

	/** Public, read-only API for separately loadable ContactPanda modules. */
	public static function extension_contacts( $search = '' ) {
		return self::all_contacts( sanitize_text_field( $search ) );
	}

	/** Public, read-only company relationships for separately loadable modules. */
	public static function extension_company_map() {
		return self::contact_company_map();
	}

	public static function extension_companies_available() {
		return self::companies_available();
	}

	/** Public, read-only company list for separately loadable Basic modules. */
	public static function extension_companies() {
		return self::companies_available() ? self::companies() : array();
	}

	/** Import one CSV company into Groundhogg Companies when that extension exists. */
	public static function extension_import_company( $record, $mode = 'fill', $source_id = '' ) {
		if ( ContactPanda_Store::standalone() || ! self::companies_available() ) return new WP_Error( 'contactpanda_csv_groundhogg_companies', __( 'Groundhogg Companies is required for this company import.', 'contactpanda' ) );
		$name = sanitize_text_field( $record['name'] ?? '' );
		if ( '' === $name ) return new WP_Error( 'contactpanda_csv_company_name', __( 'Company name is missing.', 'contactpanda' ) );
		$existing = false;
		foreach ( self::companies() as $company ) if ( 0 === strcasecmp( self::value( $company, array( 'name', 'company_name', 'get_name' ) ), $name ) ) { $existing = $company; break; }
		if ( $existing && 'skip' === $mode ) return 'skipped';
		$input = array( 'name' => $name, 'meta' => array_merge( array( 'contactpanda_csv_source_id' => sanitize_text_field( $source_id ) ), ContactPanda_Custom_Fields::custom_meta_from_record( $record, 'companies', $existing, $mode ) ) );
		$map = array( 'legal_name', 'email', 'phone', 'website', 'industry', 'vat_id', 'address_1', 'address_2', 'postcode', 'city', 'country' );
		foreach ( $map as $field ) if ( ! empty( $record[ $field ] ) ) $input['meta'][ $field ] = sanitize_text_field( $record[ $field ] );
		if ( $existing ) {
			$input['id'] = absint( self::value( $existing, array( 'id', 'ID', 'get_id' ) ) );
			if ( ! self::execute_ability( 'groundhogg-companies/update-company', $input ) ) return new WP_Error( 'contactpanda_csv_groundhogg_company_update', __( 'Groundhogg Companies could not update this company.', 'contactpanda' ) );
			return 'updated';
		}
		$result = self::execute_ability( 'groundhogg-companies/create-company', $input );
		return $result ? 'created' : new WP_Error( 'contactpanda_csv_groundhogg_company_create', __( 'Groundhogg Companies could not create this company.', 'contactpanda' ) );
	}

	/** Public, read-only user-managed tags for separately loadable modules. */
	public static function extension_tags() {
		return self::all_tags();
	}

	/** Assign a user-managed tag by name in the active data source. */
	public static function extension_assign_tag_by_name( $contact, $tag_name, $source = 'extension' ) {
		$contact_id = self::extension_resolve_contact_id( $contact );
		$tag_name   = trim( sanitize_text_field( $tag_name ) );
		$source     = sanitize_key( $source ) ?: 'extension';
		if ( ! $contact_id ) return new WP_Error( 'contactpanda_extension_contact_missing', __( 'The extension subscription could not be synchronized.', 'contactpanda' ) );
		if ( '' === $tag_name ) return new WP_Error( 'contactpanda_extension_tag_missing', __( 'The extension did not provide a valid tag.', 'contactpanda' ) );

		$tag_id = 0;
		foreach ( self::all_tags() as $tag ) {
			if ( 0 === strcasecmp( self::value( $tag, array( 'name', 'tag_name', 'get_name' ) ), $tag_name ) ) {
				$tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ) );
				break;
			}
		}
		if ( ! $tag_id ) $tag_id = ContactPanda_Store::standalone() ? ContactPanda_Store::create_tag( $tag_name ) : self::groundhogg_create_tag( $tag_name );
		if ( ! $tag_id ) return new WP_Error( 'contactpanda_extension_tag_create', __( 'The extension tag could not be created.', 'contactpanda' ) );
		$assigned = ContactPanda_Store::standalone() ? ContactPanda_Store::assign_tag( $contact_id, $tag_id, true, $source ) : self::groundhogg_assign_tag( $contact_id, $tag_id, true );
		if ( ! $assigned ) return new WP_Error( 'contactpanda_extension_tag_assign', __( 'The extension tag could not be assigned.', 'contactpanda' ) );
		return array( 'contact_id' => $contact_id, 'tag_id' => $tag_id, 'tag_name' => $tag_name, 'assigned' => true );
	}

	/** Store extension-specific consent without changing general marketing. */
	public static function extension_record_purpose_consent( $contact, $purpose, $status = 'pending', $source = 'extension', $proof = '', $occurred_at = '' ) {
		$contact_id = self::extension_resolve_contact_id( $contact );
		$purpose    = sanitize_key( $purpose );
		$status     = sanitize_key( $status );
		$source     = sanitize_key( $source ) ?: 'extension';
		if ( ! $contact_id || '' === $purpose || ! in_array( $status, array( 'pending', 'granted', 'revoked' ), true ) ) return new WP_Error( 'contactpanda_extension_consent_invalid', __( 'The extension consent could not be synchronized.', 'contactpanda' ) );
		$occurred_at = preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $occurred_at ) ? $occurred_at : current_time( 'mysql' );
		$proof       = sanitize_textarea_field( $proof );
		if ( ContactPanda_Store::standalone() ) {
			$saved = ContactPanda_Store::record_purpose_consent( $contact_id, $purpose, $status, $source, $occurred_at, $proof );
		} else {
			$meta_key = 'contactpanda_purpose_' . substr( hash( 'sha256', $purpose ), 0, 20 );
			$saved = false !== self::execute_ability( 'groundhogg/update-contact', array( 'id' => $contact_id, 'meta' => array( $meta_key . '_key' => $purpose, $meta_key . '_status' => $status, $meta_key . '_source' => $source, $meta_key . '_at' => $occurred_at, $meta_key . '_proof' => $proof ) ) );
			if ( $saved ) self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $contact_id, 'summary' => sprintf( 'Consent status for %1$s: %2$s', $purpose, $status ), 'content' => $proof ) );
		}
		if ( ! $saved ) return new WP_Error( 'contactpanda_extension_consent_save', __( 'The extension consent could not be synchronized.', 'contactpanda' ) );
		return array( 'contact_id' => $contact_id, 'purpose' => $purpose, 'status' => $status, 'source' => $source, 'occurred_at' => $occurred_at );
	}

	/** Synchronize a product-specific subscription, its tag and consent state. */
	public static function extension_sync_subscription( $contact, $context, $status = 'pending', $proof = '' ) {
		$context   = is_array( $context ) ? $context : array();
		$source    = sanitize_key( $context['source'] ?? 'extension' ) ?: 'extension';
		$source_id = sanitize_key( (string) ( $context['source_id'] ?? '' ) );
		$purpose   = sanitize_key( $context['purpose'] ?? ( $source . ( $source_id ? '_' . $source_id : '' ) ) );
		$tag_result = null;
		if ( ! empty( $context['tag'] ) ) {
			$tag_result = self::extension_assign_tag_by_name( $contact, $context['tag'], $source );
			if ( is_wp_error( $tag_result ) ) return $tag_result;
		}
		$consent_result = self::extension_record_purpose_consent( $contact, $purpose, $status, $source, $proof, $context['occurred_at'] ?? '' );
		if ( is_wp_error( $consent_result ) ) return $consent_result;
		return array( 'contact_id' => $consent_result['contact_id'], 'purpose' => $purpose, 'status' => $consent_result['status'], 'tag' => $tag_result );
	}

	/** Backward-compatible receiver for the existing Panda Seasons action. */
	public static function handle_extension_contact_registered( $contact_id, $context = array(), $subscription = array() ) {
		$contact = absint( $contact_id );
		if ( ! $contact && is_array( $subscription ) && ! empty( $subscription['email'] ) ) $contact = sanitize_email( $subscription['email'] );
		$status = is_array( $subscription ) && 'active' === ( $subscription['status'] ?? '' ) ? 'granted' : 'pending';
		$result = self::extension_sync_subscription( $contact, $context, $status, '' );
		if ( is_wp_error( $result ) ) do_action( 'contactpanda_extension_sync_error', $result, $contact, $context, $subscription );
		return $result;
	}

	private static function extension_resolve_contact_id( $contact ) {
		if ( is_numeric( $contact ) ) return absint( $contact );
		$record = self::extension_contact_by_email( sanitize_email( $contact ) );
		return $record ? absint( self::value( $record, array( 'id', 'ID', 'get_id' ) ) ) : 0;
	}

	public static function extension_contact( $id ) {
		return self::contact( absint( $id ) );
	}

	/**
	 * Find one contact by an exact email address in the active data source.
	 *
	 * @param string $email Email address.
	 * @return array|object|false
	 */
	public static function extension_contact_by_email( $email ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return false;
		}
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::contact_by_email( $email );
		}
		$result = self::contact_search( $email, 20, 0 );
		$contacts = isset( $result['contacts'] ) && is_array( $result['contacts'] ) ? $result['contacts'] : array();
		foreach ( $contacts as $contact ) {
			$contact_email = strtolower( sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ) );
			if ( $contact_email && hash_equals( $email, $contact_email ) ) {
				return $contact;
			}
		}
		// Older Groundhogg releases expose different search semantics. Fall back
		// to the existing complete read API only when the targeted search misses.
		foreach ( self::all_contacts( $email ) as $contact ) {
			$contact_email = strtolower( sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ) );
			if ( $contact_email && hash_equals( $email, $contact_email ) ) {
				return $contact;
			}
		}
		return false;
	}

	/**
	 * Create one person in the currently selected leading data source.
	 *
	 * This is the small, provider-neutral write contract used by separately
	 * loadable modules such as the Pro support desk. It deliberately creates an
	 * unconfirmed contact and never grants marketing consent.
	 */
	public static function extension_create_contact( $data ) {
		$email = sanitize_email( $data['email'] ?? '' );
		$first_name = sanitize_text_field( $data['first_name'] ?? '' );
		$last_name = sanitize_text_field( $data['last_name'] ?? '' );
		if ( ! is_email( $email ) || '' === trim( $first_name . $last_name ) ) {
			return new WP_Error( 'contactpanda_invalid_contact', __( 'Please enter a name and a valid email address.', 'contactpanda' ) );
		}
		$existing = self::extension_contact_by_email( $email );
		if ( $existing ) {
			return absint( self::value( $existing, array( 'id', 'ID', 'get_id' ) ) );
		}
		$salutation = ContactPanda_Store::suggested_salutation( $first_name );
		if ( ContactPanda_Store::standalone() ) {
			$created = ContactPanda_Store::create_contact( array( 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'anrede' => $salutation, 'optin_status' => 'unconfirmed', 'source' => 'support_desk' ) );
			return is_wp_error( $created ) ? $created : absint( $created );
		}
		$result = self::execute_ability( 'groundhogg/create-contact', array( 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'optin_status' => 'unconfirmed', 'meta' => array( 'anrede' => $salutation ), 'expand' => array( 'meta' ) ) );
		$id = is_array( $result ) && isset( $result['contact']['id'] ) ? absint( $result['contact']['id'] ) : 0;
		return $id ?: new WP_Error( 'contactpanda_contact_create_failed', __( 'The selected data source could not create the person.', 'contactpanda' ) );
	}

	/**
	 * Import one CSV person into the active Groundhogg data source.
	 * Existing contacts are matched by email and source data is never removed.
	 */
	public static function extension_import_contact( $record, $mode = 'fill', $source_id = '' ) {
		$email = strtolower( sanitize_email( $record['email'] ?? '' ) );
		if ( ContactPanda_Store::standalone() || ! is_email( $email ) ) return new WP_Error( 'contactpanda_csv_groundhogg', __( 'A valid Groundhogg email address is required.', 'contactpanda' ) );
		$existing = self::extension_contact_by_email( $email );
		if ( $existing && 'skip' === $mode ) return 'skipped';
		$payload = array( 'email' => $email, 'meta' => array_merge( array( 'contactpanda_csv_source_id' => sanitize_text_field( $source_id ) ), ContactPanda_Custom_Fields::custom_meta_from_record( $record, 'contacts', $existing, $mode ) ), 'expand' => array( 'tags', 'meta' ) );
		foreach ( array( 'first_name', 'last_name' ) as $field ) {
			$value = sanitize_text_field( $record[ $field ] ?? '' );
			$current = $existing ? self::value( $existing, array( $field, 'get_' . $field ) ) : '';
			if ( $value && ( 'update' === $mode || '' === $current ) ) $payload[ $field ] = $value;
		}
		$meta_map = array( 'salutation' => 'anrede', 'title_prefix' => 'titel_vorangestellt', 'title_suffix' => 'titel_nachgestellt', 'phone' => 'primary_phone', 'position' => 'position', 'company' => 'company_name', 'address_1' => 'billing_address_1', 'address_2' => 'billing_address_2', 'postcode' => 'billing_postcode', 'city' => 'billing_city', 'state' => 'billing_state', 'country' => 'billing_country' );
		foreach ( $meta_map as $input => $key ) {
			$value = sanitize_text_field( $record[ $input ] ?? '' );
			$current = $existing ? self::meta( $existing, array( $key ) ) : '';
			if ( $value && ( 'update' === $mode || '' === $current ) ) $payload['meta'][ $key ] = $value;
		}
		if ( ! empty( $record['contact_channels'] ) && is_array( $record['contact_channels'] ) && ( ! $existing || 'update' === $mode || ! self::raw_meta( $existing, 'contactpanda_contact_channels' ) ) ) $payload['meta']['contactpanda_contact_channels'] = $record['contact_channels'];
		if ( $existing ) {
			$id = absint( self::value( $existing, array( 'id', 'ID', 'get_id' ) ) );
			$payload['id'] = $id;
			if ( ! self::execute_ability( 'groundhogg/update-contact', $payload ) ) return new WP_Error( 'contactpanda_csv_groundhogg_update', __( 'Groundhogg could not update this person.', 'contactpanda' ) );
		} else {
			$result = self::execute_ability( 'groundhogg/create-contact', $payload );
			$id = is_array( $result ) && isset( $result['contact']['id'] ) ? absint( $result['contact']['id'] ) : 0;
			if ( ! $id ) return new WP_Error( 'contactpanda_csv_groundhogg_create', __( 'Groundhogg could not create this person.', 'contactpanda' ) );
		}
		$current_status = $existing ? self::optin_status( $existing ) : '';
		$protected_statuses = array( 3, 6, 7, 8, 9, 'unsubscribed', 'hard_bounce', 'bounced', 'spam', 'complained', 'blocked' );
		if ( ! empty( $record['marketing'] ) && in_array( strtolower( $record['marketing'] ), array( '1', 'yes', 'ja', 'true', 'confirmed' ), true ) && ! in_array( $current_status, $protected_statuses, true ) ) {
			self::execute_ability( 'groundhogg/update-contact', array( 'id' => $id, 'optin_status' => 'confirmed', 'meta' => array( 'marketing_consent' => 'yes', 'marketing_consent_source' => 'csv_import', 'marketing_consent_date' => current_time( 'Y-m-d' ) ) ) );
			self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $id, 'summary' => __( 'Marketing consent imported from CSV', 'contactpanda' ), 'content' => __( 'The mapped CSV consent field contained an affirmative value.', 'contactpanda' ) ) );
		}
		if ( ! empty( $record['tags'] ) ) {
			$existing_tags = self::all_tags();
			foreach ( preg_split( '/[,;]+/', $record['tags'] ) as $tag_name ) {
				$tag_name = trim( sanitize_text_field( $tag_name ) ); if ( '' === $tag_name ) continue;
				$tag_id = 0; foreach ( $existing_tags as $tag ) if ( 0 === strcasecmp( self::value( $tag, array( 'name', 'tag_name' ) ), $tag_name ) ) { $tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) ); break; }
				if ( ! $tag_id ) { $created = self::execute_ability( 'groundhogg/create-tag', array( 'name' => $tag_name ) ); $tag_id = is_array( $created ) ? absint( $created['tag']['id'] ?? $created['id'] ?? 0 ) : 0; }
				if ( $tag_id ) self::execute_ability( 'groundhogg/update-contact', array( 'id' => $id, 'add_tags' => array( $tag_id ) ) );
			}
		}
		return $existing ? 'updated' : 'created';
	}

	/**
	 * Store a successfully confirmed double opt-in in the active data source.
	 *
	 * @param string $email      Confirmed email address.
	 * @param string $first_name First name supplied with the request.
	 * @param string $last_name  Last name supplied with the request.
	 * @param string $source     Auditable request source.
	 * @param string $proof      Human-readable proof note.
	 * @return int|WP_Error Contact ID or error.
	 */
	public static function extension_confirm_marketing_optin( $email, $first_name = '', $last_name = '', $source = 'double_opt_in', $proof = '' ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'contactpanda_optin_email', __( 'The newsletter subscription could not be saved.', 'contactpanda' ) );
		}
		$first_name = sanitize_text_field( $first_name );
		$last_name = sanitize_text_field( $last_name );
		$source = sanitize_key( $source );
		$proof = sanitize_textarea_field( $proof );
		$date = current_time( 'Y-m-d' );
		$confirmed_at = current_time( 'mysql' );
		$contact = self::extension_contact_by_email( $email );
		if ( $contact && in_array( self::optin_status( $contact ), array( 6, 7, 8, 9, 'hard_bounce', 'spam', 'complained', 'blocked' ), true ) ) {
			return new WP_Error( 'contactpanda_optin_protected', __( 'This email address cannot be subscribed because it has a protected delivery or complaint status.', 'contactpanda' ) );
		}

		$meta = array(
			'email_confirmed'         => 1,
			'email_confirmed_at'      => $confirmed_at,
			'marketing_consent'       => 'yes',
			'marketing_consent_source' => $source,
			'marketing_consent_date'  => $date,
		);
		if ( ContactPanda_Store::standalone() ) {
			$payload = array(
				'email'        => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'source'       => $source,
				'optin_status' => 'confirmed',
				'meta'         => $meta,
			);
			$contact_id = $contact ? absint( self::value( $contact, array( 'id', 'ID' ) ) ) : ContactPanda_Store::create_contact( $payload );
			if ( is_wp_error( $contact_id ) || ! $contact_id ) {
				return is_wp_error( $contact_id ) ? $contact_id : new WP_Error( 'contactpanda_optin_save', __( 'The newsletter subscription could not be saved.', 'contactpanda' ) );
			}
			if ( $contact && ! ContactPanda_Store::update_contact( $contact_id, $payload ) ) {
				return new WP_Error( 'contactpanda_optin_save', __( 'The newsletter subscription could not be saved.', 'contactpanda' ) );
			}
			ContactPanda_Store::record_consent( $contact_id, 'granted', $source, $date, $proof );
			if ( class_exists( 'ContactPanda_Newsletter_Schema' ) ) {
				ContactPanda_Newsletter_Schema::clear_unsubscribe_suppression( $email );
			}
			self::update_wordpress_marketing_status( $email, $confirmed_at );
			return $contact_id;
		}

		$contact_id = $contact ? absint( self::value( $contact, array( 'id', 'ID', 'get_id' ) ) ) : 0;
		if ( ! $contact_id ) {
			$created = self::execute_ability(
				'groundhogg/create-contact',
				array(
					'email'        => $email,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
					'optin_status' => 'confirmed',
					'meta'         => $meta,
					'expand'       => array( 'tags', 'meta' ),
				)
			);
			$contact_id = is_array( $created ) && isset( $created['contact']['id'] ) ? absint( $created['contact']['id'] ) : 0;
		} else {
			$updated = self::execute_ability(
				'groundhogg/update-contact',
				array(
					'id'           => $contact_id,
					'optin_status' => 'confirmed',
					'meta'         => $meta,
				)
			);
			if ( false === $updated ) {
				return new WP_Error( 'contactpanda_optin_save', __( 'The newsletter subscription could not be saved.', 'contactpanda' ) );
			}
		}
		if ( ! $contact_id ) {
			return new WP_Error( 'contactpanda_optin_save', __( 'The newsletter subscription could not be saved.', 'contactpanda' ) );
		}
		self::execute_ability(
			'groundhogg/add-contact-note',
			array(
				'contact_id' => $contact_id,
				'summary'    => __( 'Marketing consent confirmed by double opt-in', 'contactpanda' ),
				'content'    => $proof,
			)
		);
		if ( class_exists( 'ContactPanda_Newsletter_Schema' ) ) {
			ContactPanda_Newsletter_Schema::clear_unsubscribe_suppression( $email );
		}
		self::update_wordpress_marketing_status( $email, $confirmed_at );
		do_action( 'contactpanda_marketing_confirmed', $contact_id, $email );
		return $contact_id;
	}

	/**
	 * Request a separate newsletter double opt-in for an extension.
	 *
	 * Extensions must call this only after the visitor explicitly selected the
	 * general newsletter. Existing confirmed consent is retained without sending
	 * another confirmation email.
	 *
	 * @param string $email      Requested email address.
	 * @param string $first_name First name supplied with the request.
	 * @param string $last_name  Last name supplied with the request.
	 * @param string $redirect   Safe return URL after confirmation.
	 * @param string $source     Auditable extension source.
	 * @return true|WP_Error
	 */
	public static function extension_request_marketing_optin( $email, $first_name = '', $last_name = '', $redirect = '', $source = 'extension' ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'contactpanda_optin_invalid', __( 'The newsletter signup could not be processed.', 'contactpanda' ) );
		}

		$contact = self::extension_contact_by_email( $email );
		if ( $contact && self::extension_is_marketable( $contact ) ) {
			return true;
		}

		if ( ! class_exists( 'ContactPanda_Registration' ) || ! is_callable( array( 'ContactPanda_Registration', 'request_newsletter_optin' ) ) ) {
			return new WP_Error( 'contactpanda_optin_unavailable', __( 'The newsletter signup is currently unavailable.', 'contactpanda' ) );
		}

		return ContactPanda_Registration::request_newsletter_optin(
			$email,
			sanitize_text_field( $first_name ),
			sanitize_text_field( $last_name ),
			wp_validate_redirect( $redirect, home_url( '/' ) ),
			sanitize_key( $source )
		);
	}

	private static function update_wordpress_marketing_status( $email, $confirmed_at ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return;
		}
		update_user_meta( $user->ID, 'contactpanda_marketing_status', 'confirmed' );
		update_user_meta( $user->ID, 'contactpanda_marketing_confirmed_at', $confirmed_at );
	}

	public static function extension_value( $contact, $keys, $default = '' ) {
		return self::value( $contact, (array) $keys, $default );
	}

	public static function ordered_countries( $countries ) {
		$countries = is_array( $countries ) ? $countries : array();
		$preferred = array();
		foreach ( array( 'AT', 'DE', 'CH' ) as $code ) if ( isset( $countries[ $code ] ) ) { $preferred[ $code ] = $countries[ $code ]; unset( $countries[ $code ] ); }
		asort( $countries, SORT_NATURAL | SORT_FLAG_CASE );
		return $countries ? $preferred + array( '__separator__' => '──────────' ) + $countries : $preferred;
	}

	public static function extension_optin_status( $contact ) {
		return self::optin_status( $contact );
	}

	public static function extension_is_marketable( $contact ) {
		return self::is_marketable_status( self::optin_status( $contact ) );
	}

	public static function extension_unsubscribe_contact( $contact_id, $source = 'newsletter', $note = '', $email = '' ) {
		$contact_id = absint( $contact_id );
		if ( ! $contact_id ) {
			return false;
		}
		$source = sanitize_text_field( $source );
		$note = sanitize_textarea_field( $note );
		$occurred_at = current_time( 'mysql' );
		$date = substr( $occurred_at, 0, 10 );
		$email = sanitize_email( $email );
		$contact = self::contact( $contact_id );
		$primary_email = $contact ? sanitize_email( self::value( $contact, array( 'email', 'get_email' ) ) ) : '';
		$is_primary = ! $email || ( $primary_email && strtolower( $email ) === strtolower( $primary_email ) );
		$email_key = $email ? 'contactpanda_email_status_' . substr( hash( 'sha256', strtolower( $email ) ), 0, 20 ) : '';
		if ( ContactPanda_Store::standalone() ) {
			$meta = array( 'marketing_consent_revoked_at' => $occurred_at );
			if ( $email_key ) $meta[ $email_key ] = 'unsubscribed';
			if ( $is_primary ) { $meta['marketing_consent'] = 'no'; $meta['marketing_consent_revoked_date'] = $date; }
			ContactPanda_Store::update_contact( $contact_id, array( 'optin_status' => $is_primary ? 'unsubscribed' : self::optin_status( $contact ), 'meta' => $meta ) );
			ContactPanda_Store::record_consent( $contact_id, 'revoked', $source, $occurred_at, $note );
			return true;
		}
		$meta = array( 'marketing_consent_revoked_at' => $occurred_at );
		if ( $email_key ) $meta[ $email_key ] = 'unsubscribed';
		if ( $is_primary ) { $meta['marketing_consent'] = 'no'; $meta['marketing_consent_revoked_date'] = $date; }
		$input = array( 'id' => $contact_id, 'meta' => $meta ); if ( $is_primary ) $input['optin_status'] = 'unsubscribed';
		$result = self::execute_ability( 'groundhogg/update-contact', $input );
		self::execute_ability( 'groundhogg/add-contact-note', array( 'contact_id' => $contact_id, 'summary' => __( 'Newsletter unsubscribed', 'contactpanda' ), 'content' => $note ) );
		return false !== $result;
	}

	private static function contact( $id ) {
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::contact( $id );
		}
		$result = self::execute_ability(
			'groundhogg/get-contact',
			array( 'id' => $id, 'expand' => array( 'tags', 'meta', 'work_details' ) )
		);
		return is_array( $result ) && ! empty( $result['id'] ) ? $result : false;
	}

	private static function contact_company_map() {
		if ( ! self::companies_available() ) {
			return array();
		}
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::company_map();
		}
		static $map = null;
		if ( null !== $map ) {
			return $map;
		}
		$map = array();
		foreach ( self::companies() as $company ) {
			foreach ( self::contacts( $company ) as $contact ) {
				$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				if ( $contact_id ) {
					$map[ $contact_id ][] = $company;
				}
			}
		}
		return $map;
	}

	private static function contact_user( $contact ) {
		$user_id = absint( self::value( $contact, array( 'user_id' ) ) );
		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				return $user;
			}
		}
		$email = self::value( $contact, array( 'email', 'get_email' ) );
		return $email ? get_user_by( 'email', $email ) : false;
	}

	private static function optin_status( $contact ) {
		$status = is_array( $contact ) && isset( $contact['optin_status'] ) ? $contact['optin_status'] : self::value( $contact, array( 'optin_status' ) );
		if ( is_array( $status ) ) {
			return isset( $status['value'] ) ? (int) $status['value'] : '';
		}
		return is_numeric( $status ) ? (int) $status : sanitize_key( (string) $status );
	}

	private static function status_badge( $status ) {
		$labels = array(
			1 => __( 'Unconfirmed', 'contactpanda' ), 2 => __( 'Marketing allowed', 'contactpanda' ), 3 => __( 'Unsubscribed', 'contactpanda' ),
			4 => __( 'Weekly', 'contactpanda' ), 5 => __( 'Monthly', 'contactpanda' ), 6 => __( 'Undeliverable', 'contactpanda' ),
			7 => __( 'Spam', 'contactpanda' ), 8 => __( 'Complaint', 'contactpanda' ), 9 => __( 'Blocked', 'contactpanda' ),
			'unconfirmed' => __( 'Unconfirmed', 'contactpanda' ), 'confirmed' => __( 'Marketing allowed', 'contactpanda' ),
			'unsubscribed' => __( 'Unsubscribed', 'contactpanda' ), 'weekly' => __( 'Weekly', 'contactpanda' ),
			'monthly' => __( 'Monthly', 'contactpanda' ), 'hard_bounce' => __( 'Undeliverable', 'contactpanda' ),
			'spam' => __( 'Spam', 'contactpanda' ), 'complained' => __( 'Complaint', 'contactpanda' ), 'blocked' => __( 'Blocked', 'contactpanda' ),
		);
		$positive = in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'contactpanda' );
		return '<span class="contactpanda-status-badge ' . ( $positive ? 'is-positive' : 'is-negative' ) . '">' . esc_html( $label ) . '</span>';
	}

	private static function marketing_icon_badge( $status ) {
		$positive = self::is_marketable_status( $status );
		$protected = self::is_protected_optout_status( $status );
		$label = self::status_label( $status );
		$icon = $positive ? 'dashicons-thumbs-up' : ( $protected ? 'dashicons-dismiss' : 'dashicons-thumbs-down' );
		return '<span class="contactpanda-marketing-icon ' . ( $positive ? 'is-positive' : 'is-negative' ) . ( $protected ? ' is-protected' : '' ) . '" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '"><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span></span>';
	}

	private static function email_confirmation_icon( $status, $contact = array() ) {
		$confirmed = ContactPanda_Store::standalone() ? (bool) self::meta( $contact, array( 'email_confirmed' ) ) : in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
		$label = $confirmed ? __( 'Email address confirmed', 'contactpanda' ) : __( 'Email address not confirmed', 'contactpanda' );
		$mark = $confirmed
			? '<span class="dashicons dashicons-yes" aria-hidden="true"></span>'
			: '<span class="contactpanda-email-confirmation-mark" aria-hidden="true">—</span>';
		return '<span class="contactpanda-email-confirmation-icon" role="img" aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '">' . $mark . '</span>';
	}

	private static function is_marketable_status( $status ) {
		return in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
	}

	private static function is_protected_optout_status( $status ) {
		return in_array( $status, array( 3, 6, 7, 8, 9, 'unsubscribed', 'hard_bounce', 'spam', 'complained', 'blocked' ), true );
	}

	private static function marketing_consent_sources() {
		return array(
			'personally'       => __( 'In person', 'contactpanda' ),
			'personal_request' => __( 'Personal request', 'contactpanda' ),
			'test_address'     => __( 'Test address', 'contactpanda' ),
			'phone'            => __( 'By phone', 'contactpanda' ),
			'written'          => __( 'In writing', 'contactpanda' ),
			'email'            => __( 'By email', 'contactpanda' ),
			'import'           => __( 'Documented import', 'contactpanda' ),
			'other'            => __( 'Other', 'contactpanda' ),
		);
	}

	private static function all_tags() {
		if ( ContactPanda_Store::standalone() ) {
			return ContactPanda_Store::tags();
		}
		$rows = array();
		$offset = 0;
		$limit = 100;
		$seen_pages = array();
		do {
			$result = self::execute_ability( 'groundhogg/list-tags', array( 'limit' => $limit, 'offset' => $offset ) );
			$page_rows = is_array( $result ) && isset( $result['tags'] ) && is_array( $result['tags'] ) ? $result['tags'] : array();
			$fingerprint = md5( wp_json_encode( array_map( static function ( $tag ) { return self::value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ); }, $page_rows ) ) );
			if ( $page_rows && isset( $seen_pages[ $fingerprint ] ) ) break;
			$seen_pages[ $fingerprint ] = true;
			$rows = array_merge( $rows, $page_rows );
			$offset += count( $page_rows );
		} while ( $page_rows && $offset < 100000 );
		if ( ! $rows && function_exists( '\\Groundhogg\\get_db' ) ) {
			try {
				$rows = \Groundhogg\get_db( 'tags' )->query( array( 'limit' => 100000, 'orderby' => 'tag_name', 'order' => 'ASC' ) );
			} catch ( Throwable $e ) {
				$rows = array();
			}
		}
		$tags = array();
		foreach ( $rows as $row ) {
			$id = absint( self::value( $row, array( 'id', 'tag_id', 'ID', 'get_id' ) ) );
			$name = self::value( $row, array( 'name', 'tag_name', 'get_name' ) );
			if ( $id && $name ) {
				$tags[ $id ] = array(
					'id'   => $id,
					'name' => $name,
					'description' => self::tag_description( $id ),
				);
			}
		}
		$tags = array_values( $tags );
		usort( $tags, static function ( $left, $right ) { return strnatcasecmp( $left['name'], $right['name'] ); } );
		return self::user_managed_tags( $tags );
	}

	private static function tag_description( $tag_id ) {
		$descriptions = (array) get_option( 'contactpanda_tag_descriptions', array() );
		return isset( $descriptions[ absint( $tag_id ) ] ) ? sanitize_textarea_field( $descriptions[ absint( $tag_id ) ] ) : '';
	}

	private static function set_tag_description( $tag_id, $description ) {
		$descriptions = (array) get_option( 'contactpanda_tag_descriptions', array() );
		$descriptions[ absint( $tag_id ) ] = sanitize_textarea_field( $description );
		update_option( 'contactpanda_tag_descriptions', $descriptions, false );
	}

	private static function user_managed_tags( $tags ) {
		$status_tag_options = array(
			'gh_confirmed_tag', 'gh_unconfirmed_tag', 'gh_unsubscribed_tag', 'gh_spammed_tag',
			'gh_bounced_tag', 'gh_complained_tag', 'gh_weekly_tag', 'gh_monthly_tag',
			'gh_marketable_tag', 'gh_non_marketable_tag',
		);
		$system_tag_ids = array_values( array_filter( array_map( 'absint', array_map( 'get_option', $status_tag_options ) ) ) );
		$role_names = array();
		if ( ! ContactPanda_Store::standalone() && function_exists( 'wp_roles' ) ) {
			foreach ( (array) wp_roles()->roles as $role_key => $role ) {
				$role_names[] = sanitize_title( $role_key );
				$role_names[] = sanitize_title( isset( $role['name'] ) ? $role['name'] : '' );
				$role_names[] = sanitize_title( isset( $role['name'] ) ? translate_user_role( $role['name'] ) : '' );
			}
			$role_names = array_values( array_unique( array_filter( $role_names ) ) );
		}
		return array_values(
			array_filter(
				(array) $tags,
				static function ( $tag ) use ( $system_tag_ids, $role_names ) {
					$tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID', 'get_id' ) ) );
					if ( $tag_id && in_array( $tag_id, $system_tag_ids, true ) ) {
						return false;
					}
					$tag_name = sanitize_title( self::value( $tag, array( 'name', 'tag_name', 'get_name' ) ) );
					if ( $tag_name && in_array( $tag_name, $role_names, true ) ) {
						return false;
					}
					return true;
				}
			)
		);
	}

	private static function status_label( $status ) {
		$labels = array(
			1 => __( 'Unconfirmed', 'contactpanda' ), 2 => __( 'Marketing allowed', 'contactpanda' ), 3 => __( 'Unsubscribed', 'contactpanda' ),
			4 => __( 'Weekly', 'contactpanda' ), 5 => __( 'Monthly', 'contactpanda' ), 6 => __( 'Undeliverable', 'contactpanda' ),
			7 => __( 'Spam', 'contactpanda' ), 8 => __( 'Complaint', 'contactpanda' ), 9 => __( 'Blocked', 'contactpanda' ),
			'unconfirmed' => __( 'Unconfirmed', 'contactpanda' ), 'confirmed' => __( 'Marketing allowed', 'contactpanda' ),
			'unsubscribed' => __( 'Unsubscribed', 'contactpanda' ), 'weekly' => __( 'Weekly', 'contactpanda' ),
			'monthly' => __( 'Monthly', 'contactpanda' ), 'hard_bounce' => __( 'Undeliverable', 'contactpanda' ),
			'spam' => __( 'Spam', 'contactpanda' ), 'complained' => __( 'Complaint', 'contactpanda' ), 'blocked' => __( 'Blocked', 'contactpanda' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Unknown', 'contactpanda' );
	}

	private static function people_filter_link( $label, $filter, $current_filter, $search, $orderby, $order ) {
		$args = array( 'page' => self::people_page_slug() );
		if ( ! empty( $_GET['segment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['segment'] = absint( $_GET['segment'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['contactpanda_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['contactpanda_filter'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_GET['contactpanda_filter'] ) ), 0, 12000 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( 'alle' !== $filter ) {
			$args['kunden'] = $filter;
		}
		if ( $orderby ) {
			$args['orderby'] = $orderby;
			$args['order'] = $order;
		}
		$class = 'button' . ( $filter === $current_filter ? ' button-primary' : '' );
		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function sortable_heading( $label, $field, $current_field, $current_order, $customer_filter, $search, $column_key = '' ) {
		$active = $field === $current_field;
		$next_order = $active && 'asc' === $current_order ? 'desc' : 'asc';
		$args = array(
			'page'    => self::people_page_slug(),
			'orderby' => $field,
			'order'   => $next_order,
		);
		if ( ! empty( $_GET['segment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['segment'] = absint( $_GET['segment'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['contactpanda_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$args['contactpanda_filter'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_GET['contactpanda_filter'] ) ), 0, 12000 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( 'alle' !== $customer_filter ) {
			$args['kunden'] = $customer_filter;
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$indicator = $active ? ( 'asc' === $current_order ? '↑' : '↓' ) : '↕';
		$aria = sprintf(
			__( 'Sort %1$s %2$s', 'contactpanda' ),
			$label,
			'asc' === $next_order ? __( 'ascending', 'contactpanda' ) : __( 'descending', 'contactpanda' )
		);
		$class = $column_key ? self::column_class( $column_key, 'contactpanda-sortable' ) : 'contactpanda-sortable';
		return '<th class="' . esc_attr( $class ) . ( $active ? ' is-sorted' : '' ) . '"><a href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '" aria-label="' . esc_attr( $aria ) . '"><span>' . esc_html( $label ) . '</span><span class="contactpanda-sort-indicator" aria-hidden="true">' . esc_html( $indicator ) . '</span></a></th>';
	}

	private static function rowspan_heading( $heading ) {
		return preg_replace( '/^<th /', '<th rowspan="2" ', (string) $heading, 1 );
	}

	private static function compare_contacts( $left, $right, $orderby, $company_map, $order_summaries, $event_summaries = array() ) {
		$left_id = absint( self::value( $left, array( 'id', 'ID' ) ) );
		$right_id = absint( self::value( $right, array( 'id', 'ID' ) ) );
		if ( 'order_total' === $orderby ) {
			$left_value = isset( $order_summaries[ $left_id ]['total'] ) ? (float) $order_summaries[ $left_id ]['total'] : 0.0;
			$right_value = isset( $order_summaries[ $right_id ]['total'] ) ? (float) $order_summaries[ $right_id ]['total'] : 0.0;
			$comparison = $left_value <=> $right_value;
		} elseif ( 'event_count' === $orderby ) {
			$left_value = isset( $event_summaries[ $left_id ]['count'] ) ? (int) $event_summaries[ $left_id ]['count'] : ( isset( $event_summaries[ $left_id ]['visited'] ) ? (int) $event_summaries[ $left_id ]['visited'] : 0 );
			$right_value = isset( $event_summaries[ $right_id ]['count'] ) ? (int) $event_summaries[ $right_id ]['count'] : ( isset( $event_summaries[ $right_id ]['visited'] ) ? (int) $event_summaries[ $right_id ]['visited'] : 0 );
			$comparison = $left_value <=> $right_value;
		} elseif ( 'company' === $orderby ) {
			$left_company = isset( $company_map[ $left_id ][0] ) ? self::value( $company_map[ $left_id ][0], array( 'name', 'get_name', 'company_name' ) ) : '';
			$right_company = isset( $company_map[ $right_id ][0] ) ? self::value( $company_map[ $right_id ][0], array( 'name', 'get_name', 'company_name' ) ) : '';
			$comparison = strnatcasecmp( $left_company, $right_company );
		} else {
			$comparison = strnatcasecmp( self::value( $left, array( $orderby ) ), self::value( $right, array( $orderby ) ) );
		}
		return 0 !== $comparison ? $comparison : $left_id <=> $right_id;
	}

	private static function pagination( $page, $limit, $total, $search, $extra = array() ) {
		$pages = (int) ceil( $total / $limit );
		if ( $pages < 2 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		$base_args = array( 'page' => self::people_page_slug(), 's' => $search );
		if ( isset( $extra['kunden'] ) && 'alle' !== $extra['kunden'] ) {
			$base_args['kunden'] = sanitize_key( $extra['kunden'] );
		}
		if ( ! empty( $extra['orderby'] ) ) {
			$base_args['orderby'] = $extra['orderby'];
			$base_args['order'] = isset( $extra['order'] ) ? $extra['order'] : 'asc';
		}
		if ( ! empty( $extra['segment'] ) ) {
			$base_args['segment'] = absint( $extra['segment'] );
		}
		if ( ! empty( $extra['contactpanda_filter'] ) ) {
			$base_args['contactpanda_filter'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $extra['contactpanda_filter'] ), 0, 12000 );
		}
		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'contactpanda_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">← Back</a> ', 'contactpanda' );
		}
		echo '<span class="displaying-num">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'contactpanda' ), $page, $pages ) ) . '</span> ';
		if ( $page < $pages ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'contactpanda_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">Next →</a>', 'contactpanda' );
		}
		echo '</div></div>';
	}

	private static function render_company( $id ) {
		$company = self::company( $id );
		if ( ! $company ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The company could not be loaded from the selected data source.', 'contactpanda' ) . '</p></div>';
			return;
		}

		$readonly = isset( $_GET['ansicht'] ) && 'anzeigen' === sanitize_key( wp_unslash( $_GET['ansicht'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$name = self::value( $company, array( 'get_name', 'name', 'company_name' ), __( 'Company #', 'contactpanda' ) . $id );
		$contacts = self::contacts( $company );
		$orders = self::orders( $contacts );
		$billing = $orders ? self::billing( $orders[0] ) : array();
		$uid_user = self::primary_user( $company, $contacts );
		$uid = $uid_user ? (string) get_user_meta( $uid_user->ID, 'billing_vat', true ) : '';
		if ( '' === $uid && $uid_user ) {
			$uid = (string) get_user_meta( $uid_user->ID, 'b2b_uid', true );
		}
		$uid_verified = $uid_user && '' !== $uid && hash_equals( (string) get_user_meta( $uid_user->ID, 'contactpanda_vat_verified_hash', true ), hash( 'sha256', self::normalize_vat( $uid ) ) );

		echo '<a class="contactpanda-back" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-firmen' ) ) . __( '">← Companies overview</a>', 'contactpanda' );
		echo '<header class="contactpanda-heading contactpanda-company-heading"><div class="contactpanda-avatar">' . esc_html( strtoupper( substr( $name, 0, 1 ) ) ) . '</div><div><p class="contactpanda-eyebrow">' . ( $readonly ? esc_html__( 'View company', 'contactpanda' ) : esc_html__( 'Edit company', 'contactpanda' ) ) . '</p><h1>' . esc_html( $name ) . '</h1><p>' . esc_html( sprintf( ContactPanda_Store::standalone() ? __( 'ContactPanda ID %d', 'contactpanda' ) : __( 'Groundhogg ID %d', 'contactpanda' ), $id ) ) . '</p></div>';
		if ( $readonly ) {
			echo '<a class="button button-primary contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-firmen&firma=' . $id ) ) . __( '">Edit</a>', 'contactpanda' );
		}
		echo '</header>';
		echo '<div class="contactpanda-grid">';
		echo '<aside><section class="contactpanda-card contactpanda-master-data"><h2>' . esc_html__( 'Master data', 'contactpanda' ) . '</h2>';
		if ( $readonly ) {
			self::field( __( 'Company name', 'contactpanda' ), $name );
			self::field( __( 'Website', 'contactpanda' ), self::value( $company, array( 'domain', 'website', 'get_website' ) ) );
			self::field( __( 'Phone', 'contactpanda' ), self::format_phone( self::value( $company, array( 'phone', 'get_phone' ) ) ) );
			self::field( __( 'Industry', 'contactpanda' ), self::value( $company, array( 'industry', 'get_industry' ) ) );
			self::field( __( 'Company address', 'contactpanda' ), self::value( $company, array( 'address', 'get_address' ) ) );
			self::field( __( 'Description', 'contactpanda' ), self::value( $company, array( 'description', 'get_description' ) ) );
			self::readonly_uid_field( __( 'VAT ID', 'contactpanda' ), $uid, $uid_verified );
		} else {
			self::editable_field( __( 'Company name', 'contactpanda' ), $name, 'name', $id );
			self::editable_field( __( 'Website', 'contactpanda' ), self::value( $company, array( 'domain', 'website', 'get_website' ) ), 'domain', $id );
			self::phone_field( __( 'Phone', 'contactpanda' ), self::value( $company, array( 'phone', 'get_phone' ) ), 'phone', $id, 'company' );
			self::editable_field( __( 'Industry', 'contactpanda' ), self::value( $company, array( 'industry', 'get_industry' ) ), 'industry', $id );
			self::editable_field( __( 'Company address', 'contactpanda' ), self::value( $company, array( 'address', 'get_address' ) ), 'address', $id, true );
			self::editable_field( __( 'Description', 'contactpanda' ), self::value( $company, array( 'description', 'get_description' ) ), 'description', $id, true );
			self::uid_field( __( 'VAT ID', 'contactpanda' ), $uid, $id, 'company', $uid_verified );
		}
		echo __( '<p class="description contactpanda-uid-help">Linked to German Market (billing_vat) and B2B Market (b2b_uid) for the primary contact. EU validation only runs when requested.</p>', 'contactpanda' );
		echo '</section>';
		do_action( 'contactpanda_render_company_custom_fields', $id, $company, $readonly, 'sidebar' );
		echo __( '<section class="contactpanda-card"><h2>Billing address</h2>', 'contactpanda' );
		foreach ( array( __( 'Company', 'contactpanda' ) => 'company', __( 'First name', 'contactpanda' ) => 'first_name', __( 'Last name', 'contactpanda' ) => 'last_name', __( 'Street', 'contactpanda' ) => 'address_1', __( 'Address line 2', 'contactpanda' ) => 'address_2', __( 'Postcode', 'contactpanda' ) => 'postcode', __( 'Location', 'contactpanda' ) => 'city', __( 'Country', 'contactpanda' ) => 'country' ) as $label => $key ) {
			self::field( $label, isset( $billing[ $key ] ) ? $billing[ $key ] : '' );
		}
		echo '</section></aside>';

		echo __( '<main><section class="contactpanda-card"><h2>Contacts <span class="contactpanda-count">', 'contactpanda' ) . esc_html( count( $contacts ) ) . '</span></h2>';
		if ( $contacts ) {
			echo '<div class="contactpanda-people">';
			foreach ( $contacts as $contact ) {
				$full = trim( self::value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . self::value( $contact, array( 'last_name', 'get_last_name' ) ) );
				$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
				echo '<div class="contactpanda-person"><span class="dashicons dashicons-admin-users"></span><div><a href="' . esc_url( admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ) ) . '"><strong>' . esc_html( $full ?: __( 'Contact', 'contactpanda' ) ) . '</strong></a><br><span>' . esc_html( self::value( $contact, array( 'email', 'get_email' ), '—' ) ) . '</span></div></div>';
			}
			echo '</div>';
		} else {
			echo __( '<p class="contactpanda-muted">No contacts were returned by the public Companies interface.</p>', 'contactpanda' );
		}
		echo '</section>';

		echo __( '<section class="contactpanda-card"><h2>Orders &amp; invoices <span class="contactpanda-count">', 'contactpanda' ) . esc_html( count( $orders ) ) . '</span></h2>';
		if ( $orders ) {
			self::render_orders_table( $orders );
		} else {
			echo __( '<p class="contactpanda-muted">No order was found using the contacts\' email addresses.</p>', 'contactpanda' );
		}
		echo '</section>';

		self::render_company_notes( $id );
		self::render_company_activity_history( $id );
		echo '</main></div>';
	}

	private static function render_person_tabs( $contact_id, $customer, $contact, $orders, $events, $events_enabled, $broadcasts, $editable ) {
		$tabs_id = 'contactpanda-person-tabs-' . absint( $contact_id );
		$extension_tabs = (array) apply_filters( 'contactpanda_person_detail_tabs', array(), $contact_id, $contact, $editable );
		$normalized_extension_tabs = array();
		foreach ( $extension_tabs as $key => $tab ) {
			$key = sanitize_key( $key );
			if ( ! $key || ! is_array( $tab ) || empty( $tab['label'] ) ) continue;
			$normalized_extension_tabs[ $key ] = array(
				'label' => sanitize_text_field( $tab['label'] ),
				'count' => isset( $tab['count'] ) ? absint( $tab['count'] ) : null,
			);
		}
		$notes = self::contact_notes( $contact_id );
		$activities = ContactPanda_Store::activities( $contact_id, 0, 'activity' );
		$partition = self::partition_contact_notes( $notes );
		$notes = $partition['notes'];
		$activities = array_merge( $activities, $partition['activities'] );
		usort( $activities, static function ( $left, $right ) { return strcmp( (string) self::value( $right, array( 'date_created', 'created_at' ) ), (string) self::value( $left, array( 'date_created', 'created_at' ) ) ); } );
		$broadcast_page_arg = isset( $_GET['broadcast_page'] ) && is_scalar( $_GET['broadcast_page'] ) ? wp_unslash( $_GET['broadcast_page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$show_broadcasts = absint( $broadcast_page_arg ) > 0;
		echo '<section class="contactpanda-card contactpanda-tabs-card" data-tabs><div class="contactpanda-tabs" role="tablist" aria-label="' . esc_attr__( 'Person data', 'contactpanda' ) . '">';
		echo '<button type="button" class="contactpanda-tab' . ( $show_broadcasts ? '' : ' is-active' ) . '" id="' . esc_attr( $tabs_id . '-notes-tab' ) . '" role="tab" aria-selected="' . ( $show_broadcasts ? 'false' : 'true' ) . '" aria-controls="' . esc_attr( $tabs_id . '-notes' ) . '" tabindex="' . ( $show_broadcasts ? '-1' : '0' ) . '">' . esc_html__( 'Notes', 'contactpanda' ) . ' <span class="contactpanda-count">' . esc_html( count( $notes ) ) . '</span></button>';
		echo '<button type="button" class="contactpanda-tab" id="' . esc_attr( $tabs_id . '-activities-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-activities' ) . '" tabindex="-1">' . esc_html__( 'Activities', 'contactpanda' ) . ' <span class="contactpanda-count">' . esc_html( count( $activities ) ) . '</span></button>';
		echo '<button type="button" class="contactpanda-tab" id="' . esc_attr( $tabs_id . '-orders-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-orders' ) . '" tabindex="-1">' . esc_html__( 'Orders', 'contactpanda' ) . ' <span class="contactpanda-count">' . esc_html( count( $orders ) ) . '</span></button>';
		echo '<button type="button" class="contactpanda-tab" id="' . esc_attr( $tabs_id . '-addresses-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-addresses' ) . '" tabindex="-1">' . esc_html__( 'Addresses', 'contactpanda' ) . '</button>';
		foreach ( $normalized_extension_tabs as $key => $tab ) {
			echo '<button type="button" class="contactpanda-tab" id="' . esc_attr( $tabs_id . '-' . $key . '-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-' . $key ) . '" tabindex="-1">' . esc_html( $tab['label'] );
			if ( null !== $tab['count'] ) echo ' <span class="contactpanda-count">' . esc_html( $tab['count'] ) . '</span>';
			echo '</button>';
		}
		if ( $events_enabled ) {
			echo '<button type="button" class="contactpanda-tab" id="' . esc_attr( $tabs_id . '-events-tab' ) . '" role="tab" aria-selected="false" aria-controls="' . esc_attr( $tabs_id . '-events' ) . __( '" tabindex="-1">Events <span class="contactpanda-count">', 'contactpanda' ) . esc_html( count( $events ) ) . '</span></button>';
		}
		echo '<button type="button" class="contactpanda-tab' . ( $show_broadcasts ? ' is-active' : '' ) . '" id="' . esc_attr( $tabs_id . '-broadcasts-tab' ) . '" role="tab" aria-selected="' . ( $show_broadcasts ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $tabs_id . '-broadcasts' ) . '" tabindex="' . ( $show_broadcasts ? '0' : '-1' ) . '">' . esc_html__( 'Mailings', 'contactpanda' ) . ' <span class="contactpanda-count">' . esc_html( count( $broadcasts ) ) . '</span></button>';
		echo '</div>';
		echo '<div class="contactpanda-tab-panel' . ( $show_broadcasts ? '' : ' is-active' ) . '" id="' . esc_attr( $tabs_id . '-notes' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-notes-tab' ) . '"' . ( $show_broadcasts ? ' hidden' : '' ) . '>';
		self::render_contact_notes( $contact_id, $notes, false, $editable );
		echo '</div><div class="contactpanda-tab-panel" id="' . esc_attr( $tabs_id . '-activities' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-activities-tab' ) . '" hidden>';
		self::render_activity_timeline( $activities, false );
		echo '</div><div class="contactpanda-tab-panel" id="' . esc_attr( $tabs_id . '-orders' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-orders-tab' ) . '" hidden>';
		self::render_person_orders( $orders, false );
		echo '</div><div class="contactpanda-tab-panel" id="' . esc_attr( $tabs_id . '-addresses' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-addresses-tab' ) . '" hidden>';
		self::render_customer_addresses( $contact_id, $customer, $contact, false, $editable );
		echo '</div>';
		foreach ( $normalized_extension_tabs as $key => $tab ) {
			echo '<div class="contactpanda-tab-panel" id="' . esc_attr( $tabs_id . '-' . $key ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-' . $key . '-tab' ) . '" hidden>';
			do_action( 'contactpanda_render_person_detail_tab_' . $key, $contact_id, $contact, $editable );
			echo '</div>';
		}
		if ( $events_enabled ) {
			echo '<div class="contactpanda-tab-panel" id="' . esc_attr( $tabs_id . '-events' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-events-tab' ) . '" hidden>';
			self::render_person_events( $events );
			echo '</div>';
		}
		echo '<div class="contactpanda-tab-panel' . ( $show_broadcasts ? ' is-active' : '' ) . '" id="' . esc_attr( $tabs_id . '-broadcasts' ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $tabs_id . '-broadcasts-tab' ) . '"' . ( $show_broadcasts ? '' : ' hidden' ) . '>';
		self::render_person_broadcasts( $contact_id, $broadcasts );
		echo '</div>';
		echo '</section>';
	}

	private static function render_person_events( $events ) {
		echo '<div class="contactpanda-toolbar"><h2>' . esc_html__( 'Events', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( sprintf( _n( '%d booking', '%d bookings', count( $events ), 'contactpanda' ), count( $events ) ) ) . '</span></div>';
		if ( ! $events ) {
			echo __( '<p class="contactpanda-muted">No event booking was found for this person.</p>', 'contactpanda' );
			return;
		}
		echo __( '<table class="widefat contactpanda-table"><thead><tr><th>Event</th><th><span class="screen-reader-text">Preview</span></th><th>Date</th><th>Status</th><th>Booking</th></tr></thead><tbody>', 'contactpanda' );
		foreach ( $events as $event ) {
			$title = $event['title'] ? $event['title'] : __( 'Event #', 'contactpanda' ) . $event['event_id'];
			$event_url = isset( $event['event_url'] ) ? $event['event_url'] : '';
			echo '<tr><td>' . ( $event_url ? '<a href="' . esc_url( $event_url ) . '"><strong>' . esc_html( $title ) . '</strong></a>' : '<strong>' . esc_html( $title ) . '</strong>' ) . '</td>';
			echo '<td><button type="button" class="button-link contactpanda-eye-action contactpanda-preview-event" data-event="' . esc_attr( $event['event_key'] ?? $event['event_id'] ) . '" aria-label="' . esc_attr__( 'View event details', 'contactpanda' ) . '" title="' . esc_attr__( 'View event details', 'contactpanda' ) . '"><span class="dashicons dashicons-visibility"></span></button></td>';
			echo '<td>' . esc_html( $event['date'] ? $event['date'] : '—' ) . '</td>';
			echo '<td><span class="contactpanda-mini-badge">' . esc_html( $event['status_label'] ?? ( $event['visited'] ? __( 'Attended', 'contactpanda' ) : __( 'Booked', 'contactpanda' ) ) ) . '</span></td>';
			echo '<td>';
			if ( ! empty( $event['order_url'] ) ) {
				echo '<a href="' . esc_url( $event['order_url'] ) . __( '">Open order</a>', 'contactpanda' );
			} else {
				echo '—';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Bookings are assigned by matching the email address stored by the selected event source.', 'contactpanda' ) . '</p>';
	}

	private static function render_person_broadcasts( $contact_id, $broadcasts ) {
		$limit = 20;
		$total = count( $broadcasts );
		$pages = max( 1, (int) ceil( $total / $limit ) );
		$broadcast_page_arg = isset( $_GET['broadcast_page'] ) && is_scalar( $_GET['broadcast_page'] ) ? wp_unslash( $_GET['broadcast_page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		$page = $broadcast_page_arg ? max( 1, absint( $broadcast_page_arg ) ) : 1;
		$page = min( $page, $pages );
		$page_items = array_slice( $broadcasts, ( $page - 1 ) * $limit, $limit );
		echo '<div class="contactpanda-toolbar"><h2>' . esc_html__( 'Mailings', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( sprintf( __( '%d sent', 'contactpanda' ), $total ) ) . '</span></div>';
		if ( ! $broadcasts ) {
			echo '<p class="contactpanda-muted">' . esc_html__( 'No mailing with a reliable recipient assignment was found for this person.', 'contactpanda' ) . '</p>';
			return;
		}
		echo '<table class="widefat contactpanda-table contactpanda-broadcast-table"><thead><tr><th>' . esc_html__( 'Mailing', 'contactpanda' ) . '</th><th>' . esc_html__( 'Source', 'contactpanda' ) . '</th><th>' . esc_html__( 'Sent', 'contactpanda' ) . '</th><th>' . esc_html__( 'Delivered', 'contactpanda' ) . '</th><th>' . esc_html__( 'Opened', 'contactpanda' ) . '</th><th>' . esc_html__( 'Clicked', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $page_items as $broadcast ) {
			if ( ! empty( $broadcast['detail_url'] ) ) echo '<tr><td><a href="' . esc_url( $broadcast['detail_url'] ) . '"><strong>' . esc_html( $broadcast['title'] ) . '</strong></a></td>';
			else echo '<tr><td><button type="button" class="button-link contactpanda-preview-broadcast" data-contact="' . esc_attr( $contact_id ) . '" data-broadcast="' . esc_attr( $broadcast['id'] ) . '"><strong>' . esc_html( $broadcast['title'] ) . '</strong></button></td>';
			echo '<td><span class="contactpanda-mini-badge">' . esc_html( $broadcast['source'] ?? __( 'Groundhogg', 'contactpanda' ) ) . '</span></td><td>' . esc_html( $broadcast['date'] ) . '</td>';
			echo '<td>' . self::yes_no_badge( ! isset( $broadcast['delivered'] ) || $broadcast['delivered'] ) . '</td>';
			echo '<td>' . self::yes_no_badge( $broadcast['opened'] ) . '</td>';
			echo '<td>' . self::yes_no_badge( $broadcast['clicked'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $pages > 1 ) {
			$base_args = array( 'page' => self::people_page_slug(), 'person' => absint( $contact_id ) );
			if ( ! empty( $_GET['contactpanda_list_state'] ) ) $base_args['contactpanda_list_state'] = sanitize_key( wp_unslash( $_GET['contactpanda_list_state'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
			echo '<nav class="contactpanda-broadcast-pagination" aria-label="' . esc_attr__( 'Mailing pages', 'contactpanda' ) . '"><span>' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'contactpanda' ), $page, $pages ) ) . '</span><span class="contactpanda-pagination-buttons">';
			if ( $page > 1 ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'broadcast_page' => $page - 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">← Back</a>', 'contactpanda' );
			}
			if ( $page < $pages ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( array_merge( $base_args, array( 'broadcast_page' => $page + 1 ) ), admin_url( 'admin.php' ) ) ) . __( '">Next →</a>', 'contactpanda' );
			}
			echo '</span></nav>';
		}
		echo '<p class="description">' . esc_html__( 'Available delivery, opening, and click information is read from the respective source. Privacy features or blocked images may affect tracking.', 'contactpanda' ) . '</p>';
	}

	private static function yes_no_badge( $value ) {
		return '<span class="contactpanda-status-badge ' . ( $value ? 'is-positive' : 'is-negative' ) . '">' . ( $value ? esc_html__( 'Yes', 'contactpanda' ) : esc_html__( 'No', 'contactpanda' ) ) . '</span>';
	}

	private static function render_bulk_marketing_modal() {
		echo '<div class="contactpanda-consent-modal contactpanda-bulk-modal" hidden><div class="contactpanda-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-bulk-title">';
		echo __( '<h2 id="contactpanda-bulk-title">Enable marketable status for multiple people</h2>', 'contactpanda' );
		echo __( '<p>Only selected people without an unsubscribe or blocked status will be changed. Already marketable contacts remain unchanged.</p>', 'contactpanda' );
		echo __( '<p class="contactpanda-bulk-modal-count"><strong>0 people selected</strong></p>', 'contactpanda' );
		echo __( '<label>Source<select class="contactpanda-bulk-source"><option value="">Please select</option><option value="personally">In person</option><option value="phone">By phone</option><option value="written">In writing</option><option value="email">By email</option><option value="import">Documented import</option><option value="other">Other</option></select></label>', 'contactpanda' );
		echo __( '<label>Date<input type="date" class="contactpanda-bulk-date" value="', 'contactpanda' ) . esc_attr( current_time( 'Y-m-d' ) ) . '"></label>';
		echo __( '<label>Note / evidence<textarea class="contactpanda-bulk-note" rows="3" placeholder="Optional evidence note"></textarea></label>', 'contactpanda' );
		echo __( '<label class="contactpanda-confirm"><input type="checkbox" class="contactpanda-bulk-confirmed"> <span>I confirm that explicit marketing consent exists for every selected person.</span></label>', 'contactpanda' );
		echo __( '<div class="contactpanda-modal-message" aria-live="polite"></div><div class="contactpanda-modal-actions"><button type="button" class="button button-primary contactpanda-save-bulk">Enable with confirmation</button><button type="button" class="button contactpanda-close-bulk">Cancel</button></div>', 'contactpanda' );
		echo '</div></div>';
	}

	private static function render_newsletter_selection_modal() {
		echo '<div class="contactpanda-consent-modal contactpanda-newsletter-selection-modal" hidden><div class="contactpanda-newsletter-selection-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-newsletter-selection-title"><header><h2 id="contactpanda-newsletter-selection-title">' . esc_html__( 'Create newsletter mailing for selected people', 'contactpanda' ) . '</h2><button type="button" class="button-link contactpanda-close-newsletter-selection" aria-label="' . esc_attr__( 'Close', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header><iframe class="contactpanda-newsletter-selection-frame" title="' . esc_attr__( 'Newsletter mailing', 'contactpanda' ) . '"></iframe></div></div>';
	}

	private static function render_contact_output_modal( $total, $search, $customer_filter, $segment_id, $advanced_filter, $orderby, $order ) {
		$default_columns = array( 'first_name', 'last_name', 'email', 'phone', 'company', 'postcode', 'city', 'country' );
		echo '<div class="contactpanda-consent-modal contactpanda-output-modal" hidden><div class="contactpanda-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-output-title">';
		echo '<h2 id="contactpanda-output-title">' . esc_html__( 'Export contacts', 'contactpanda' ) . '</h2><p class="contactpanda-output-help">' . esc_html__( 'Choose whether selected people or all currently filtered people should be used.', 'contactpanda' ) . '</p>';
		echo '<form class="contactpanda-output-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_export_contacts"><input type="hidden" name="selected_ids" value="">';
		wp_nonce_field( 'contactpanda_contact_output' );
		foreach ( array( 's' => $search, 'kunden' => $customer_filter, 'segment' => $segment_id, 'contactpanda_filter' => $advanced_filter, 'orderby' => $orderby, 'order' => $order ) as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '<fieldset class="contactpanda-output-scope"><legend>' . esc_html__( 'People to include', 'contactpanda' ) . '</legend><label><input type="radio" name="scope" value="selected"> <span class="contactpanda-output-selected-label">' . esc_html__( 'Selected people', 'contactpanda' ) . '</span></label><label><input type="radio" name="scope" value="all" checked> ' . esc_html( sprintf( __( 'All filtered people (%d)', 'contactpanda' ), $total ) ) . '</label></fieldset>';
		echo '<fieldset class="contactpanda-output-columns"><legend>' . esc_html__( 'Information to include', 'contactpanda' ) . '</legend><div class="contactpanda-output-column-grid">';
		foreach ( self::contact_output_fields() as $key => $label ) {
			echo '<label><input type="checkbox" name="columns[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $default_columns, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div></fieldset><div class="contactpanda-modal-message" aria-live="polite"></div><div class="contactpanda-modal-actions"><button type="submit" class="button button-primary contactpanda-submit-output">' . esc_html__( 'Export CSV', 'contactpanda' ) . '</button>';
		if ( class_exists( 'ContactPanda_Data_Tools' ) && ContactPanda_Data_Tools::vcard_enabled() ) echo '<button type="submit" class="button contactpanda-export-vcard">' . esc_html__( 'Export vCard', 'contactpanda' ) . '</button>';
		echo '<button type="button" class="button contactpanda-close-output">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button></div></form></div></div>';
	}

	private static function render_new_person_modal() {
		$companies = self::companies_available() ? self::companies() : array();
		echo '<div class="contactpanda-consent-modal contactpanda-new-person-modal" hidden><div class="contactpanda-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-new-person-title">';
		echo '<h2 id="contactpanda-new-person-title">' . esc_html__( 'Create new person', 'contactpanda' ) . '</h2><p>' . esc_html__( 'The person is created in the selected data source with the marketing status “Unconfirmed”.', 'contactpanda' ) . '</p>';
		echo __( '<label>First name<input type="text" class="contactpanda-new-first-name" autocomplete="given-name"></label>', 'contactpanda' );
		echo __( '<label>Last name<input type="text" class="contactpanda-new-last-name" autocomplete="family-name"></label>', 'contactpanda' );
		echo '<label>' . esc_html__( 'Salutation', 'contactpanda' ) . '<select class="contactpanda-new-salutation"><option value="">' . esc_html__( '--- Please select ---', 'contactpanda' ) . '</option><option value="neutral">' . esc_html__( 'Neutral', 'contactpanda' ) . '</option><option value="mr">' . esc_html__( 'Mr', 'contactpanda' ) . '</option><option value="ms">' . esc_html__( 'Ms', 'contactpanda' ) . '</option><option value="family">' . esc_html__( 'Family', 'contactpanda' ) . '</option></select></label>';
		echo __( '<label>Email address<input type="email" class="contactpanda-new-email" autocomplete="email" required></label>', 'contactpanda' );
		if ( self::companies_available() ) {
			echo '<fieldset class="contactpanda-new-company-fields"><legend>' . esc_html__( 'Company assignment (optional)', 'contactpanda' ) . '</legend><p class="description">' . esc_html__( 'Select an existing company by typing its name, or enter a new company name. Similar existing names are suggested to avoid duplicates.', 'contactpanda' ) . '</p>';
			echo '<label>' . esc_html__( 'Company', 'contactpanda' ) . '<input type="text" class="contactpanda-new-company-name" list="contactpanda-new-company-options" autocomplete="organization"><input type="hidden" class="contactpanda-new-company-id" value=""><datalist id="contactpanda-new-company-options">';
			foreach ( $companies as $company ) {
				$id = absint( self::value( $company, array( 'id', 'ID', 'get_id' ) ) );
				$name = self::value( $company, array( 'name', 'company_name', 'get_name' ) );
				if ( $id && $name ) echo '<option value="' . esc_attr( $name ) . '" data-company-id="' . esc_attr( $id ) . '"></option>';
			}
			echo '</datalist></label><label>' . esc_html__( 'Position / function in the company', 'contactpanda' ) . '<input type="text" class="contactpanda-new-company-position"></label></fieldset>';
		}
		echo __( '<div class="contactpanda-modal-message" aria-live="polite"></div><div class="contactpanda-modal-actions"><button type="button" class="button button-primary contactpanda-save-new-person">Create person</button><button type="button" class="button contactpanda-close-new-person">Cancel</button></div>', 'contactpanda' );
		echo '</div></div>';
	}

	private static function render_new_company_modal() {
		echo '<div class="contactpanda-consent-modal contactpanda-new-company-modal" hidden><div class="contactpanda-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-new-company-title"><h2 id="contactpanda-new-company-title">' . esc_html__( 'Create company', 'contactpanda' ) . '</h2><label>' . esc_html__( 'Company name', 'contactpanda' ) . '<input type="text" class="contactpanda-new-company-name-only" required></label><div class="contactpanda-modal-message" aria-live="polite"></div><div class="contactpanda-modal-actions"><button type="button" class="button button-primary contactpanda-save-new-company">' . esc_html__( 'Create company', 'contactpanda' ) . '</button><button type="button" class="button contactpanda-close-new-company">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button></div></div></div>';
	}

	private static function render_marketing( $contact, $editable = true ) {
		$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
		$status = self::optin_status( $contact );
		$source = self::meta( $contact, array( 'marketing_consent_source' ) );
		$is_marketable = in_array( $status, array( 2, 4, 5, 'confirmed', 'weekly', 'monthly' ), true );
		$date = $is_marketable
			? self::meta( $contact, array( 'marketing_consent_date' ) )
			: self::meta( $contact, array( 'marketing_consent_revoked_date', 'marketing_consent_date' ) );
		$user = self::contact_user( $contact );
		$doi_complete = $user ? (bool) get_user_meta( $user->ID, 'contactpanda_email_verified', true ) : (bool) self::meta( $contact, array( 'email_confirmed', 'double_opt_in_completed' ) );
		if ( ! $doi_complete && ! ContactPanda_Store::standalone() ) {
			$doi_complete = in_array( $status, array( 2, 'confirmed' ), true );
		}
		$email = self::value( $contact, array( 'email', 'get_email' ) );
		$suppression = class_exists( 'ContactPanda_Newsletter_Schema' ) ? ContactPanda_Newsletter_Schema::suppression( $email ) : false;
		$blocking_reason = self::meta( $contact, array( 'marketing_block_reason' ) );
		$blocking_date = self::meta( $contact, array( 'marketing_block_date' ) );
		if ( $suppression ) {
			$reason_labels = array( 'unsubscribed' => __( 'Finally unsubscribed', 'contactpanda' ), 'blocked' => __( 'Blocked by administrator', 'contactpanda' ), 'hard_bounce' => __( 'Undeliverable', 'contactpanda' ), 'spam' => __( 'Spam report', 'contactpanda' ), 'complaint' => __( 'Complaint', 'contactpanda' ) );
			$blocking_reason = $blocking_reason ?: ( $reason_labels[ $suppression['reason'] ] ?? sanitize_text_field( $suppression['reason'] ) );
			$blocking_date = $blocking_date ?: (string) ( $suppression['created_at'] ?? '' );
		}
		if ( ! $blocking_reason && in_array( $status, array( 6, 7, 8, 9, 'hard_bounce', 'spam', 'complained', 'blocked' ), true ) ) $blocking_reason = self::status_label( $status );

		echo '<section class="contactpanda-card contactpanda-marketing" data-contact="' . esc_attr( $id ) . '"><h2>' . esc_html__( 'Marketing & consent', 'contactpanda' ) . '</h2><div class="contactpanda-consent-summary"><div class="contactpanda-consent-status-row">' . self::status_badge( $status ) . '<span><strong>' . esc_html__( 'Consent date', 'contactpanda' ) . ':</strong> ' . esc_html( $date ?: '—' ) . '</span></div><div class="contactpanda-consent-pair"><span><strong>' . esc_html__( 'Source', 'contactpanda' ) . ':</strong> ' . esc_html( $source ?: '—' ) . '</span><span><strong>' . esc_html__( 'Double opt-in', 'contactpanda' ) . ':</strong> ' . esc_html( $doi_complete ? __( 'Completed', 'contactpanda' ) : __( 'Not completed', 'contactpanda' ) ) . '</span></div><div class="contactpanda-consent-block-row' . ( $blocking_reason ? ' is-blocked' : '' ) . '"><strong>' . esc_html__( 'Blocking reason', 'contactpanda' ) . ':</strong> ' . esc_html( $blocking_reason ?: __( 'No permanent block', 'contactpanda' ) ) . '<span><strong>' . esc_html__( 'Date', 'contactpanda' ) . ':</strong> ' . esc_html( $blocking_date ?: '—' ) . '</span></div><p class="contactpanda-consent-additional">' . esc_html( sprintf( __( 'Current marketing status: %s. Protected delivery, spam and complaint blocks are never removed by an ordinary consent change.', 'contactpanda' ), self::status_label( $status ) ) ) . '</p></div>';
		if ( $editable ) {
			echo __( '<button type="button" class="button contactpanda-open-consent">Edit consent</button>', 'contactpanda' );
		}
		if ( $editable ) {
			echo '<p class="description">' . esc_html__( 'The change is saved in the selected leading data source and documented as an activity.', 'contactpanda' ) . '</p>';
		}
		if ( ! $editable ) {
			echo '</section>';
			return;
		}
		echo '<div class="contactpanda-consent-modal" hidden><div class="contactpanda-modal-panel" role="dialog" aria-modal="true" aria-labelledby="contactpanda-consent-title-' . esc_attr( $id ) . '">';
		echo '<h2 id="contactpanda-consent-title-' . esc_attr( $id ) . __( '">Edit marketing consent</h2>', 'contactpanda' );
		echo __( '<p>Only change this when consent, withdrawal or an administrator block is actually documented. Every change is recorded in the audit log.</p>', 'contactpanda' );
		echo '<label>' . esc_html__( 'Decision', 'contactpanda' ) . '<select class="contactpanda-consent-decision"><option value="grant">' . esc_html__( 'Grant consent', 'contactpanda' ) . '</option><option value="revoke">' . esc_html__( 'Withdraw consent', 'contactpanda' ) . '</option>';
		if ( current_user_can( 'manage_options' ) ) echo '<option value="block">' . esc_html__( 'Permanently block address', 'contactpanda' ) . '</option><option value="unblock">' . esc_html__( 'Remove administrator block', 'contactpanda' ) . '</option>';
		echo '</select></label><div class="notice notice-warning inline contactpanda-consent-override-warning" hidden><p>' . esc_html__( 'Administrator override: give a precise reason. Delivery, spam and complaint blocks cannot be removed here.', 'contactpanda' ) . '</p></div>';
		echo __( '<label>Source<select class="contactpanda-consent-source"><option value="">Please select</option><option value="personally">In person</option><option value="personal_request">Personal request</option><option value="test_address">Test address</option><option value="phone">By phone</option><option value="written">In writing</option><option value="email">By email</option><option value="import">Documented import</option><option value="other">Other</option></select></label>', 'contactpanda' );
		echo __( '<label>Date<input type="date" class="contactpanda-consent-date" value="', 'contactpanda' ) . esc_attr( current_time( 'Y-m-d' ) ) . '"></label>';
		echo __( '<label>Note / evidence<textarea class="contactpanda-consent-note" rows="3" placeholder="Optional evidence note"></textarea></label>', 'contactpanda' );
		echo __( '<label class="contactpanda-confirm"><input type="checkbox" class="contactpanda-consent-confirmed"> <span>I confirm that explicit consent has been given.</span></label>', 'contactpanda' );
		echo __( '<div class="contactpanda-modal-message" aria-live="polite"></div><div class="contactpanda-modal-actions"><button type="button" class="button button-primary contactpanda-save-consent">Save with confirmation</button><button type="button" class="button contactpanda-close-consent">Cancel</button></div>', 'contactpanda' );
		echo '</div></div></section>';
	}

	private static function render_contact_tags( $contact, $editable = true ) {
		$id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
		$tags = is_array( $contact ) && isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? $contact['tags'] : array();
		$tags = self::user_managed_tags( $tags );
		$all_tags = $editable ? self::all_tags() : array();

		echo '<section class="contactpanda-card contactpanda-tags-card" data-contact="' . esc_attr( $id ) . '"><h2>' . esc_html__( 'Tags', 'contactpanda' ) . '</h2>';
		if ( ! $editable ) {
			echo '<div class="contactpanda-tags"><div>';
			if ( $tags ) {
				foreach ( $tags as $tag ) {
					echo '<span class="contactpanda-tag">' . esc_html( self::value( $tag, array( 'name', 'tag_name' ) ) ) . '</span>';
				}
			} else {
				echo __( '<span class="contactpanda-muted">No tags assigned yet.</span>', 'contactpanda' );
			}
			echo '</div></div></section>';
			return;
		}

		echo '<button type="button" class="button-link contactpanda-tags-summary" aria-label="' . esc_attr__( 'Edit tags', 'contactpanda' ) . '">';
		if ( $tags ) {
			foreach ( $tags as $tag ) echo '<span class="contactpanda-tag" data-tag="' . esc_attr( absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) ) ) . '">' . esc_html( self::value( $tag, array( 'name', 'tag_name' ) ) ) . '</span>';
		} else echo '<span class="contactpanda-muted">' . esc_html__( 'No tags assigned yet.', 'contactpanda' ) . '</span>';
		echo '</button><div class="contactpanda-tag-manager" hidden><div class="contactpanda-token-control"><div class="contactpanda-token-entry"><div class="contactpanda-token-field">';
		foreach ( $tags as $tag ) {
			$tag_id = absint( self::value( $tag, array( 'id', 'tag_id', 'ID' ) ) );
			if ( ! $tag_id ) {
				continue;
			}
			echo '<span class="contactpanda-tag" data-tag="' . esc_attr( $tag_id ) . '">' . esc_html( self::value( $tag, array( 'name', 'tag_name' ) ) ) . '<button type="button" class="button-link contactpanda-remove-tag" data-tag="' . esc_attr( $tag_id ) . __( '" aria-label="Remove tag from this person">&times;</button></span>', 'contactpanda' );
		}
		echo __( '<input type="text" class="contactpanda-token-input contactpanda-tag-input" placeholder="Search or create a tag" aria-label="Search or create a tag" autocomplete="off"></div><div class="contactpanda-token-suggestions contactpanda-tag-suggestions" role="listbox" hidden>', 'contactpanda' );
		foreach ( $all_tags as $tag ) {
			echo '<button type="button" class="button-link contactpanda-token-suggestion contactpanda-tag-suggestion" role="option" data-tag="' . esc_attr( $tag['id'] ) . '" data-name="' . esc_attr( $tag['name'] ) . '">' . esc_html( $tag['name'] ) . '</button>';
		}
		echo __( '</div></div></div><p class="description contactpanda-tag-autosave-note">Selecting a tag saves it immediately for this contact. Press Enter to create a new tag.</p>', 'contactpanda' );
		echo '<button type="button" class="button contactpanda-close-tags">' . esc_html__( 'Done', 'contactpanda' ) . '</button>';
		echo '<div class="contactpanda-tag-message" aria-live="polite"></div></div></section>';
	}

	private static function render_customer_addresses( $contact_id, $customer, $contact, $standalone = true, $editable = true ) {
		if ( $standalone ) {
			echo '<section class="contactpanda-card">';
		}
		echo __( '<h2>Addresses</h2>', 'contactpanda' );
		$countries = self::ordered_countries( function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_countries() : self::contact_countries() );
		$contact_address = self::contact_address( $contact );
		if ( ! array_filter( $contact_address ) && $customer ) {
			$billing_fallback = self::customer_address( $customer, 'billing' );
			foreach ( array_keys( $contact_address ) as $key ) {
				$contact_address[ $key ] = isset( $billing_fallback[ $key ] ) ? $billing_fallback[ $key ] : '';
			}
		}
		self::render_contact_address_form( $contact_address, $countries, $contact_id, $editable, (bool) $customer );
		if ( ! $customer ) {
			echo __( '<p class="contactpanda-muted contactpanda-woocommerce-address-note">No linked WordPress/WooCommerce customer account. The contact address is saved independently; billing and shipping addresses become available after an account is linked.</p>', 'contactpanda' );
			if ( $standalone ) {
				echo '</section>';
			}
			return;
		}
		$billing = self::customer_address( $customer, 'billing' );
		if ( empty( $billing['first_name'] ) ) {
			$billing['first_name'] = self::value( $contact, array( 'first_name' ) );
		}
		if ( empty( $billing['last_name'] ) ) {
			$billing['last_name'] = self::value( $contact, array( 'last_name' ) );
		}
		if ( empty( $billing['email'] ) ) {
			$billing['email'] = self::value( $contact, array( 'email' ) );
		}
		echo '<div class="contactpanda-address-grid">';
		self::render_customer_address_form( 'billing', __( 'Billing address', 'contactpanda' ), $billing, $countries, $contact_id, $editable );
		self::render_customer_address_form( 'shipping', __( 'Shipping address', 'contactpanda' ), self::customer_address( $customer, 'shipping' ), $countries, $contact_id, $editable );
		echo '</div>';
		if ( $editable ) {
			echo __( '<p class="description">Only the WooCommerce customer profile is saved. Existing orders and historical invoices are not changed.</p>', 'contactpanda' );
		}
		if ( $standalone ) {
			echo '</section>';
		}
	}

	private static function render_contact_address_form( $address, $countries, $contact_id, $editable, $can_sync ) {
		echo '<div class="contactpanda-address-form contactpanda-contact-address-form" data-contact="' . esc_attr( $contact_id ) . '" data-address-type="contact"><h3>' . esc_html__( 'Contact address', 'contactpanda' ) . '</h3>';
		$disabled = $editable ? '' : ' disabled';
		foreach ( array( __( 'Street and house number', 'contactpanda' ) => 'address_1', __( 'Address line 2', 'contactpanda' ) => 'address_2', __( 'Postcode', 'contactpanda' ) => 'postcode', __( 'Location', 'contactpanda' ) => 'city', __( 'State / region', 'contactpanda' ) => 'state' ) as $label => $key ) {
			echo '<label>' . esc_html( $label ) . '<input type="text" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( isset( $address[ $key ] ) ? $address[ $key ] : '' ) . '"' . $disabled . '></label>';
		}
		$selected_country = ! empty( $address['country'] ) ? $address['country'] : 'AT';
		echo __( '<label>Country<select data-key="country"', 'contactpanda' ) . $disabled . '>';
		foreach ( $countries as $code => $label ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $label ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $selected_country, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		if ( $editable && $can_sync ) {
			echo '<label class="contactpanda-address-sync"><input type="checkbox" class="contactpanda-sync-to-woocommerce" value="1"> <span>' . esc_html__( 'Also copy this address to the WooCommerce billing address.', 'contactpanda' ) . '</span></label>';
		}
		if ( $editable ) {
			echo '<div class="contactpanda-address-message" aria-live="polite"></div><button type="button" class="button button-primary contactpanda-save-address">' . esc_html__( 'Save contact address', 'contactpanda' ) . '</button>';
		}
		echo '</div>';
	}

	private static function render_customer_address_form( $type, $title, $address, $countries, $contact_id, $editable = true ) {
		echo '<div class="contactpanda-address-form" data-contact="' . esc_attr( $contact_id ) . '" data-address-type="' . esc_attr( $type ) . '"><h3>' . esc_html( $title ) . '</h3>';
		$disabled = $editable ? '' : ' disabled';
		foreach ( array( __( 'First name', 'contactpanda' ) => 'first_name', __( 'Last name', 'contactpanda' ) => 'last_name', __( 'Company', 'contactpanda' ) => 'company', __( 'Street and house number', 'contactpanda' ) => 'address_1', __( 'Address line 2', 'contactpanda' ) => 'address_2', __( 'Postcode', 'contactpanda' ) => 'postcode', __( 'Location', 'contactpanda' ) => 'city', __( 'State / region', 'contactpanda' ) => 'state' ) as $label => $key ) {
			echo '<label>' . esc_html( $label ) . '<input type="text" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( isset( $address[ $key ] ) ? $address[ $key ] : '' ) . '"' . $disabled . '></label>';
		}
		$selected_country = ! empty( $address['country'] ) ? $address['country'] : 'AT';
		echo __( '<label>Country<select data-key="country"', 'contactpanda' ) . $disabled . '>';
		foreach ( $countries as $code => $label ) {
			if ( '__separator__' === $code ) { echo '<option value="" disabled>' . esc_html( $label ) . '</option>'; continue; }
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $selected_country, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		if ( 'billing' === $type ) {
			echo __( '<label>Billing email<input type="email" data-key="email" value="', 'contactpanda' ) . esc_attr( isset( $address['email'] ) ? $address['email'] : '' ) . '"' . $disabled . '></label>';
			if ( $editable ) {
				echo __( '<label>Billing phone', 'contactpanda' ) . self::phone_input_html( isset( $address['phone'] ) ? $address['phone'] : '' ) . '</label>';
			} else {
				echo __( '<label>Billing phone<input type="text" value="', 'contactpanda' ) . esc_attr( self::format_phone( isset( $address['phone'] ) ? $address['phone'] : '' ) ) . '" disabled></label>';
			}
		}
		if ( $editable ) {
			echo '<div class="contactpanda-address-message" aria-live="polite"></div><button type="button" class="button button-primary contactpanda-save-address">' . esc_html( $title ) . __( ' save</button>', 'contactpanda' );
		}
		echo '</div>';
	}

	private static function render_person_orders( $orders, $standalone = true ) {
		if ( $standalone ) {
			echo '<section class="contactpanda-card">';
		}
		echo __( '<h2>Orders &amp; invoices <span class="contactpanda-count">', 'contactpanda' ) . esc_html( count( $orders ) ) . '</span></h2>';
		if ( ! $orders ) {
			echo __( '<p class="contactpanda-muted">No order was found using the email address.</p>', 'contactpanda' );
			if ( $standalone ) {
				echo '</section>';
			}
			return;
		}
		self::render_orders_table( $orders );
		if ( $standalone ) {
			echo '</section>';
		}
	}

	private static function render_order_row( $order ) {
		$invoice_url = self::invoice_url( $order );
		$invoice_number = self::invoice_number( $order );
		echo '<tr><td><a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
		echo '<td>' . esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y' ) : '—' ) . '</td>';
		echo '<td>' . esc_html( $invoice_number ?: '—' ) . '</td>';
		echo '<td><div class="contactpanda-order-actions">';
		echo '<button type="button" class="button-link contactpanda-eye-action contactpanda-preview-order" data-order="' . esc_attr( $order->get_id() ) . __( '" aria-label="View order information" title="View order information"><span class="dashicons dashicons-visibility"></span></button>', 'contactpanda' );
		if ( $invoice_url ) {
			echo '<a class="button-link contactpanda-eye-action contactpanda-invoice-preview" href="' . esc_url( $invoice_url ) . __( '" data-title="Invoice for order #', 'contactpanda' ) . esc_attr( $order->get_order_number() ) . __( '" aria-label="View invoice PDF" title="View invoice PDF"><span class="dashicons dashicons-media-document"></span></a>', 'contactpanda' );
		} else {
			echo __( '<span class="contactpanda-document-unavailable" title="German Market does not provide an invoice action for this order">—</span>', 'contactpanda' );
		}
		echo '</div></td>';
		echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
		echo '<td>' . esc_html( $order->get_payment_method_title() ?: '—' ) . '</td>';
		echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td></tr>';
	}

	private static function render_orders_table( $orders ) {
		$totals = array();
		echo __( '<table class="widefat contactpanda-table contactpanda-orders-table"><thead><tr><th>Order number</th><th>Date</th><th>Invoice number</th><th>Actions</th><th>Status</th><th>Payment method</th><th>Total</th></tr></thead><tbody>', 'contactpanda' );
		foreach ( $orders as $order ) {
			self::render_order_row( $order );
			$currency = $order->get_currency() ?: get_woocommerce_currency();
			$order_total = (float) $order->get_total();
			if ( method_exists( $order, 'get_total_refunded' ) ) {
				$order_total -= (float) $order->get_total_refunded();
			}
			$totals[ $currency ] = isset( $totals[ $currency ] ) ? $totals[ $currency ] + $order_total : $order_total;
		}
		echo __( '</tbody><tfoot><tr><th colspan="6">Grand total</th><td><strong>', 'contactpanda' );
		$formatted = array();
		foreach ( $totals as $currency => $total ) {
			$formatted[] = wc_price( $total, array( 'currency' => $currency ) );
		}
		echo wp_kses_post( implode( '<br>', $formatted ) ) . '</strong></td></tr></tfoot></table>';
	}

	private static function invoice_number( $order ) {
		$candidates = array( '_wgm_invoice_number', 'wgm_invoice_number', '_wp_wc_invoice_pdf_invoice_number', 'wp_wc_invoice_pdf_invoice_number', '_wcpdf_formatted_invoice_number', '_wcpdf_invoice_number', '_invoice_number', 'invoice_number' );
		foreach ( $candidates as $key ) {
			$value = $order->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		foreach ( $order->get_meta_data() as $meta ) {
			$data = is_object( $meta ) && method_exists( $meta, 'get_data' ) ? $meta->get_data() : array();
			$key = isset( $data['key'] ) ? strtolower( (string) $data['key'] ) : '';
			$value = isset( $data['value'] ) ? $data['value'] : '';
			$is_invoice = false !== strpos( $key, 'invoice' ) || false !== strpos( $key, 'rechnung' );
			$is_number = false !== strpos( $key, 'number' ) || false !== strpos( $key, 'nummer' );
			if ( $is_invoice && $is_number && is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return trim( (string) $value );
			}
		}
		$filtered = (string) apply_filters( 'contactpanda_german_market_invoice_number', '', $order );
		return '' !== trim( $filtered ) ? trim( $filtered ) : (string) $order->get_order_number();
	}

	private static function invoice_url( $order ) {
		$actions = apply_filters( 'woocommerce_admin_order_actions', array(), $order );
		foreach ( (array) $actions as $key => $action ) {
			if ( ! is_array( $action ) || empty( $action['url'] ) ) {
				continue;
			}
			$haystack = strtolower( $key . ' ' . ( isset( $action['action'] ) ? $action['action'] : '' ) . ' ' . ( isset( $action['name'] ) ? $action['name'] : '' ) . ' ' . $action['url'] );
			if ( ( false !== strpos( $haystack, 'invoice' ) || false !== strpos( $haystack, 'rechnung' ) ) && false === strpos( $haystack, 'refund' ) ) {
				return esc_url_raw( $action['url'] );
			}
		}
		return (string) apply_filters( 'contactpanda_german_market_invoice_url', '', $order );
	}

	private static function preview_document_css() {
		return 'body{margin:0;padding:28px;font:14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#253342;background:#f5f7fa}header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}h1{font-size:26px;margin:3px 0}h2{font-size:15px;margin:0 0 8px}.eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#5266d4}.status{background:#e8eefc;border-radius:99px;padding:6px 12px;font-weight:600}.facts,.addresses{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin:0 0 20px}.facts>div,.addresses section,.note,.event-fact{background:#fff;border:1px solid #dfe5eb;border-radius:9px;padding:15px}.facts span,.event-fact span{display:block;color:#718096;font-size:12px;margin-bottom:4px}.event-layout{display:grid;grid-template-columns:minmax(210px,260px) minmax(0,1fr);gap:22px;align-items:start}.event-sidebar{display:flex;flex-direction:column;gap:12px}.event-fact strong{display:block}.event-fact small{display:block;color:#5266d4;font-weight:700;margin-top:5px}.event-content{min-width:0}.event-content header{margin-top:2px}.long-info{margin-top:14px}.long-info img{max-width:100%;height:auto}.event-image{display:block;width:100%;max-height:420px;object-fit:cover;border-radius:10px;margin-bottom:22px}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #dfe5eb}th,td{padding:11px;border-bottom:1px solid #e8edf1;text-align:left}.num{text-align:right}p{margin:6px 0}@media(max-width:700px){body{padding:16px}.facts,.addresses,.event-layout{grid-template-columns:1fr}}';
	}

	private static function editable_roles() {
		$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
		$result = array();
		foreach ( $roles as $key => $role ) {
			$result[ sanitize_key( $key ) ] = isset( $role['name'] ) ? translate_user_role( $role['name'] ) : $key;
		}
		return $result;
	}

	private static function render_role_picker( $roles, $selected_roles ) {
		$selected_roles = array_values( array_intersect( array_map( 'sanitize_key', (array) $selected_roles ), array_keys( $roles ) ) );
		echo '<div class="contactpanda-role-picker" data-role-picker><div class="contactpanda-token-entry"><div class="contactpanda-token-field">';
		foreach ( $selected_roles as $role ) {
			echo '<span class="contactpanda-role-token" data-role="' . esc_attr( $role ) . '">' . esc_html( $roles[ $role ] ) . '<button type="button" class="button-link contactpanda-remove-role" aria-label="' . esc_attr( sprintf( __( 'Remove role %s', 'contactpanda' ), $roles[ $role ] ) ) . '">&times;</button></span>';
		}
		echo __( '<input type="text" class="contactpanda-token-input contactpanda-role-input" placeholder="Search WordPress roles" aria-label="Search WordPress roles" autocomplete="off"></div><div class="contactpanda-token-suggestions contactpanda-role-suggestions" role="listbox" hidden>', 'contactpanda' );
		foreach ( $roles as $role => $label ) {
			echo '<button type="button" class="button-link contactpanda-token-suggestion contactpanda-role-suggestion" role="option" data-role="' . esc_attr( $role ) . '" data-name="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</button>';
		}
		echo __( '</div></div><div class="contactpanda-role-picker-message" aria-live="polite"></div></div>', 'contactpanda' );
	}

	private static function render_user_account_controls( $contact_id, $contact, $user, $readonly ) {
		$linked_id = absint( self::value( $contact, array( 'user_id', 'get_user_id' ) ) );
		$roles = self::editable_roles();
		echo __( '<div class="contactpanda-user-account"><span>WordPress user account</span>', 'contactpanda' );
		if ( $user ) {
			echo '<strong>' . esc_html__( 'Account available', 'contactpanda' ) . '</strong><small>' . esc_html( $user->user_login ) . '</small>';
			if ( ! $readonly && ! $linked_id ) {
				echo __( '<button type="button" class="button button-small contactpanda-open-user-account" data-mode="link">Link existing account</button>', 'contactpanda' );
			}
		} else {
			echo __( '<strong>No user account</strong>', 'contactpanda' );
			if ( ! $readonly ) {
				echo __( '<button type="button" class="button button-small contactpanda-open-user-account" data-mode="create">Create user account</button>', 'contactpanda' );
			}
		}
		echo '</div>';
		if ( $user ) {
			$labels = array();
			foreach ( (array) $user->roles as $role ) {
				$labels[] = isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
			}
			echo '<div class="contactpanda-user-roles"><span>' . esc_html__( 'Customer / WordPress roles', 'contactpanda' ) . '</span><strong>' . esc_html( implode( ', ', $labels ) ?: __( 'No role', 'contactpanda' ) ) . '</strong>';
			if ( ! $readonly && $linked_id ) {
				echo __( '<button type="button" class="button button-small contactpanda-open-roles">Edit customer roles</button>', 'contactpanda' );
			}
			echo '</div>';
		}
		if ( ! $readonly && $user && $linked_id ) {
			echo '<div class="contactpanda-consent-modal contactpanda-roles-modal" hidden><div class="contactpanda-modal-panel" data-contact="' . esc_attr( $contact_id ) . '" data-user="' . esc_attr( $user->ID ) . __( '"><h2>Edit customer roles</h2><p>Multiple WordPress roles are supported. Start typing and select a suggested role. Changes immediately affect this user account\'s permissions.</p>', 'contactpanda' );
			self::render_role_picker( $roles, (array) $user->roles );
			echo __( '<div class="contactpanda-modal-message"></div><div class="contactpanda-modal-actions"><button type="button" class="button button-primary contactpanda-save-roles">Save roles</button><button type="button" class="button contactpanda-close-roles">Cancel</button></div></div></div>', 'contactpanda' );
		}
		if ( ! $readonly && ( ! $user || ! $linked_id ) ) {
			$base = sanitize_user( strtolower( trim( self::value( $contact, array( 'first_name' ) ) . '.' . self::value( $contact, array( 'last_name' ) ) ) ), true );
			$base = $base ?: sanitize_user( strtok( self::value( $contact, array( 'email' ) ), '@' ), true );
			echo '<div class="contactpanda-consent-modal contactpanda-user-account-modal" hidden><div class="contactpanda-modal-panel" data-contact="' . esc_attr( $contact_id ) . '" data-mode="' . ( $user ? 'link' : 'create' ) . '"><h2>' . ( $user ? __( 'Link user account', 'contactpanda' ) : __( 'Create user account', 'contactpanda' ) ) . '</h2>';
			if ( $user ) {
				$source_label = ContactPanda_Store::standalone() ? __( 'ContactPanda person', 'contactpanda' ) : __( 'Groundhogg contact', 'contactpanda' );
				echo '<p>' . sprintf( wp_kses( __( 'The existing account <strong>%1$s</strong> with the same email address will be linked to this %2$s.', 'contactpanda' ), array( 'strong' => array() ) ), esc_html( $user->user_login ), esc_html( $source_label ) ) . '</p>';
			} else {
				echo __( '<p>An account with a secure random password will be created. This does not grant marketing consent.</p><label>Username<input type="text" class="contactpanda-account-username" value="', 'contactpanda' ) . esc_attr( $base ) . '"></label>';
				self::render_role_picker( $roles, array( 'customer' ) );
				echo __( '<label class="contactpanda-confirm"><input type="checkbox" class="contactpanda-account-invite" checked> <span>Send an invitation to set the password.</span></label>', 'contactpanda' );
			}
			echo '<div class="contactpanda-modal-message"></div><div class="contactpanda-modal-actions"><button type="button" class="button button-primary contactpanda-save-user-account">' . ( $user ? __( 'Link', 'contactpanda' ) : __( 'Create account', 'contactpanda' ) ) . __( '</button><button type="button" class="button contactpanda-close-user-account">Cancel</button></div></div></div>', 'contactpanda' );
		}
	}

	private static function guard_user_admin_ajax() {
		self::guard_ajax();
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'promote_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators may manage user accounts and roles.', 'contactpanda' ) ), 403 );
		}
	}

	private static function set_user_roles( $user, $roles ) {
		$roles = array_values( array_unique( array_map( 'sanitize_key', (array) $roles ) ) );
		$allowed = self::editable_roles();
		if ( ! $roles || array_diff( $roles, array_keys( $allowed ) ) ) {
			return new WP_Error( 'contactpanda_invalid_roles', __( 'Please select at least one permitted role.', 'contactpanda' ) );
		}
		if ( is_multisite() && is_super_admin( $user->ID ) && ! is_super_admin() ) {
			return new WP_Error( 'contactpanda_super_admin', __( 'This super administrator account cannot be changed.', 'contactpanda' ) );
		}
		if ( get_current_user_id() === (int) $user->ID && in_array( 'administrator', (array) $user->roles, true ) && ! in_array( 'administrator', $roles, true ) ) {
			return new WP_Error( 'contactpanda_self_admin', __( 'You cannot remove your own administrator role here.', 'contactpanda' ) );
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) && ! in_array( 'administrator', $roles, true ) ) {
			$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );
			if ( count( $admins ) <= 1 ) {
				return new WP_Error( 'contactpanda_last_admin', __( 'The role of the last administrator cannot be removed.', 'contactpanda' ) );
			}
		}
		$user->set_role( array_shift( $roles ) );
		foreach ( $roles as $role ) {
			$user->add_role( $role );
		}
		return true;
	}

	private static function log_contact_activity( $contact_id, $type, $summary, $content ) {
		// ContactPanda-generated audit events remain separate from human notes,
		// including while Groundhogg is the leading contact data source.
		return ContactPanda_Store::add_activity( $contact_id, 0, sanitize_key( $type ), $summary, $content );
	}

	public static function handle_add_contact_note() {
		if ( ! current_user_can( self::access_capability() ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		check_admin_referer( 'contactpanda_add_contact_note_' . $contact_id );
		if ( ! $contact_id || ! self::contact( $contact_id ) ) wp_die( esc_html__( 'The person could not be found.', 'contactpanda' ), '', array( 'response' => 404 ) );
		$type = sanitize_key( wp_unslash( $_POST['note_type'] ?? 'note' ) );
		$types = self::note_type_labels();
		if ( ! isset( $types[ $type ] ) ) $type = 'note';
		$title = sanitize_text_field( wp_unslash( $_POST['note_title'] ?? '' ) );
		if ( '' === $title ) $title = $types[ $type ];
		$content = wp_kses_post( wp_unslash( $_POST['note_content'] ?? '' ) );
		$raw_time = sanitize_text_field( wp_unslash( $_POST['note_time'] ?? '' ) );
		$date = DateTime::createFromFormat( 'Y-m-d\TH:i', $raw_time, wp_timezone() );
		$occurred_at = $date && $date->format( 'Y-m-d\TH:i' ) === $raw_time ? $date->format( 'Y-m-d H:i:s' ) : current_time( 'mysql' );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) wp_die( esc_html__( 'Enter note content.', 'contactpanda' ), '', array( 'response' => 400 ) );
		if ( ContactPanda_Store::standalone() ) {
			$saved = ContactPanda_Store::add_activity( $contact_id, 0, 'note_' . $type, $title, $content, $occurred_at );
		} elseif ( class_exists( '\\Groundhogg\\Contact' ) ) {
			try {
				$groundhogg_contact = new \Groundhogg\Contact( $contact_id );
				$groundhogg_title = $types[ $type ] === $title ? $title : $types[ $type ] . ': ' . $title;
				$saved = $groundhogg_contact->add_note( $content, 'user', get_current_user_id(), array( 'summary' => $groundhogg_title, 'date_created' => $occurred_at ) );
			} catch ( Throwable $error ) {
				$saved = false;
			}
		} else {
			$saved = false;
		}
		set_transient( 'contactpanda_note_notice_' . get_current_user_id(), array( 'success' => (bool) $saved, 'message' => $saved ? __( 'The note was saved.', 'contactpanda' ) : __( 'The note could not be saved.', 'contactpanda' ) ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::people_page_slug() . '&person=' . $contact_id ) );
		exit;
	}

	private static function note_type_labels() {
		return array(
			'note'     => __( 'General note', 'contactpanda' ),
			'phone'    => __( 'Telephone call', 'contactpanda' ),
			'email'    => __( 'Email', 'contactpanda' ),
			'message'  => __( 'Message', 'contactpanda' ),
			'meeting'  => __( 'Meeting', 'contactpanda' ),
			'callback' => __( 'Callback', 'contactpanda' ),
		);
	}

	private static function contact_notes( $contact_id ) {
		if ( ContactPanda_Store::standalone() ) {
			$notes = ContactPanda_Store::activities( $contact_id, 0, 'notes' );
		} else {
			$result = self::execute_ability( 'groundhogg/list-contact-notes', array( 'contact_id' => $contact_id, 'limit' => 20, 'offset' => 0 ) );
			$notes = is_array( $result ) && isset( $result['notes'] ) && is_array( $result['notes'] ) ? $result['notes'] : array();
		}
		usort( $notes, static function ( $left, $right ) { return strcmp( (string) self::value( $right, array( 'date_created', 'created_at' ) ), (string) self::value( $left, array( 'date_created', 'created_at' ) ) ); } );
		return $notes;
	}

	/** Move historical automatic Groundhogg notes into the activity timeline. */
	private static function partition_contact_notes( $notes ) {
		$result = array( 'notes' => array(), 'activities' => array() );
		foreach ( (array) $notes as $note ) {
			$title = sanitize_text_field( self::value( $note, array( 'summary', 'title' ), '' ) );
			$content = trim( wp_strip_all_tags( self::value( $note, array( 'content', 'note' ), '' ) ) );
			$normalized = strtolower( remove_accents( trim( $title . ' ' . $content ) ) );
			$is_activity = (bool) preg_match( '/\b(contact address|kontaktadresse|wordpress roles?|wordpress-rollen|wordpress user account|wordpress-benutzerkonto)\b/', $normalized );
			if ( ! $is_activity && false !== strpos( $normalized, 'bearbeitet von:' ) ) {
				$is_activity = false !== strpos( $normalized, 'aktualisiert' ) || false !== strpos( $normalized, 'updated' ) || false !== strpos( $normalized, 'changed' );
			}
			if ( ! $is_activity ) {
				$result['notes'][] = $note;
				continue;
			}
			$activity = $note;
			$activity['activity_type'] = false !== stripos( $title . ' ' . $content, 'role' ) || false !== stripos( $title . ' ' . $content, 'Rollen' ) ? 'user_roles' : ( false !== stripos( $title . ' ' . $content, 'account' ) || false !== stripos( $title . ' ' . $content, 'Benutzerkonto' ) ? 'user_account' : 'address_update' );
			$result['activities'][] = $activity;
		}
		return $result;
	}

	private static function render_contact_notes( $contact_id, $notes, $standalone = true, $editable = false ) {
		if ( $standalone ) {
			echo '<section class="contactpanda-card">';
		}
		echo '<div class="contactpanda-toolbar"><h2>' . esc_html__( 'Notes', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( count( $notes ) ) . '</span></div>';
		$notice = get_transient( 'contactpanda_note_notice_' . get_current_user_id() );
		if ( is_array( $notice ) ) { delete_transient( 'contactpanda_note_notice_' . get_current_user_id() ); echo '<div class="notice notice-' . ( ! empty( $notice['success'] ) ? 'success' : 'error' ) . ' inline"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>'; }
		if ( $editable ) {
			echo '<details class="contactpanda-note-editor"><summary class="button button-primary">' . esc_html__( 'Add note', 'contactpanda' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-note-form"><input type="hidden" name="action" value="contactpanda_add_contact_note"><input type="hidden" name="contact_id" value="' . esc_attr( $contact_id ) . '">';
			wp_nonce_field( 'contactpanda_add_contact_note_' . $contact_id );
			echo '<label><span>' . esc_html__( 'Type', 'contactpanda' ) . '</span><select name="note_type">'; foreach ( self::note_type_labels() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label>';
			echo '<label><span>' . esc_html__( 'Date and time', 'contactpanda' ) . '</span><input type="datetime-local" name="note_time" value="' . esc_attr( current_datetime()->format( 'Y-m-d\TH:i' ) ) . '" required></label>';
			echo '<label class="contactpanda-note-title"><span>' . esc_html__( 'Title (optional)', 'contactpanda' ) . '</span><input type="text" name="note_title"></label>';
			echo '<label class="contactpanda-note-content"><span>' . esc_html__( 'Note', 'contactpanda' ) . '</span><textarea name="note_content" rows="5" required></textarea></label><p><button class="button button-primary">' . esc_html__( 'Save note', 'contactpanda' ) . '</button></p></form></details>';
		}
		echo '<div class="contactpanda-timeline">';
		if ( ! $notes ) {
			echo '<div><span></span><strong>' . esc_html__( 'No notes yet', 'contactpanda' ) . '</strong><p>' . esc_html__( 'Create the first note for this person.', 'contactpanda' ) . '</p></div>';
		} else {
			foreach ( $notes as $note ) {
				$type = preg_replace( '/^note_/', '', sanitize_key( self::value( $note, array( 'activity_type', 'type' ), 'note' ) ) );
				$type_labels = self::note_type_labels();
				echo '<div><span></span><strong>' . esc_html( self::value( $note, array( 'summary', 'title' ), __( 'Contact note', 'contactpanda' ) ) ) . '</strong>';
				if ( isset( $type_labels[ $type ] ) ) echo ' <em class="contactpanda-note-type">' . esc_html( $type_labels[ $type ] ) . '</em>';
				echo '<p>' . nl2br( esc_html( wp_strip_all_tags( self::value( $note, array( 'content', 'note' ) ) ) ) ) . '</p>';
				$date = self::value( $note, array( 'date_created', 'created_at' ) );
				if ( $date ) {
					echo '<small>' . esc_html( $date ) . '</small>';
				}
				echo '</div>';
			}
		}
		echo '</div>';
		if ( $standalone ) {
			echo '</section>';
		}
	}

	private static function render_activity_timeline( $activities, $standalone = true ) {
		if ( $standalone ) echo '<section class="contactpanda-card">';
		echo '<div class="contactpanda-toolbar"><h2>' . esc_html__( 'Activities', 'contactpanda' ) . '</h2><span class="contactpanda-count">' . esc_html( count( $activities ) ) . '</span></div><div class="contactpanda-timeline">';
		if ( ! $activities ) {
			echo '<div><span></span><strong>' . esc_html__( 'No activities yet', 'contactpanda' ) . '</strong><p>' . esc_html__( 'Automatically recorded changes and events appear here.', 'contactpanda' ) . '</p></div>';
		} else {
			foreach ( $activities as $activity ) {
				$type = sanitize_key( self::value( $activity, array( 'activity_type', 'type' ), 'activity' ) );
				echo '<div><span></span><strong>' . esc_html( self::value( $activity, array( 'summary', 'title' ), __( 'Activity', 'contactpanda' ) ) ) . '</strong> <em class="contactpanda-note-type">' . esc_html( self::activity_type_label( $type ) ) . '</em>';
				$content = self::value( $activity, array( 'content', 'note' ) );
				if ( '' !== trim( (string) $content ) ) echo '<p>' . nl2br( esc_html( wp_strip_all_tags( $content ) ) ) . '</p>';
				$date = self::value( $activity, array( 'date_created', 'created_at' ) );
				if ( $date ) echo '<small>' . esc_html( $date ) . '</small>';
				echo '</div>';
			}
		}
		echo '</div>';
		if ( $standalone ) echo '</section>';
	}

	private static function activity_type_label( $type ) {
		$labels = array(
			'address_update'   => __( 'Address change', 'contactpanda' ),
			'consent'         => __( 'Consent', 'contactpanda' ),
			'form_submission' => __( 'Form submission', 'contactpanda' ),
			'merge'           => __( 'Merge', 'contactpanda' ),
			'registration'    => __( 'Registration', 'contactpanda' ),
			'user_account'    => __( 'User account', 'contactpanda' ),
			'user_roles'      => __( 'User roles', 'contactpanda' ),
		);
		return $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
	}

	private static function contacts( $company ) {
		if ( is_array( $company ) && isset( $company['contacts'] ) && is_array( $company['contacts'] ) ) {
			return $company['contacts'];
		}
		foreach ( array( 'get_contacts', 'contacts' ) as $method ) {
			if ( is_object( $company ) && method_exists( $company, $method ) ) {
				$result = $company->{$method}();
				return is_array( $result ) ? $result : array();
			}
		}
		return array();
	}

	private static function primary_user( $company, $contacts ) {
		$primary_id = absint( self::value( $company, array( 'primary_contact_id' ) ) );
		$ordered = $contacts;
		if ( $primary_id ) {
			usort( $ordered, static function ( $a, $b ) use ( $primary_id ) {
				return ( absint( self::value( $b, array( 'id', 'ID' ) ) ) === $primary_id ) <=> ( absint( self::value( $a, array( 'id', 'ID' ) ) ) === $primary_id );
			} );
		}
		foreach ( $ordered as $contact ) {
			$user = self::contact_user( $contact );
			if ( $user ) {
				return $user;
			}
		}
		return false;
	}

	private static function company_primary_user( $company_id ) {
		$company = self::company( $company_id );
		return $company ? self::primary_user( $company, self::contacts( $company ) ) : false;
	}

	private static function normalize_vat( $vat ) {
		$vat = strtoupper( sanitize_text_field( $vat ) );
		return preg_replace( '/[^A-Z0-9]/', '', $vat );
	}

	private static function phone_countries() {
		return array(
			array( 'country' => 'AT', 'label' => __( 'Austria', 'contactpanda' ), 'code' => '+43' ),
			array( 'country' => 'DE', 'label' => 'Deutschland', 'code' => '+49' ),
			array( 'country' => 'CH', 'label' => 'Schweiz', 'code' => '+41' ),
			array( 'country' => 'IT', 'label' => 'Italien / Vatikan', 'code' => '+39' ),
			array( 'country' => 'AL', 'label' => 'Albanien', 'code' => '+355' ),
			array( 'country' => 'AD', 'label' => 'Andorra', 'code' => '+376' ),
			array( 'country' => 'BE', 'label' => 'Belgien', 'code' => '+32' ),
			array( 'country' => 'BA', 'label' => 'Bosnien und Herzegowina', 'code' => '+387' ),
			array( 'country' => 'BG', 'label' => 'Bulgarien', 'code' => '+359' ),
			array( 'country' => 'DK', 'label' => __( 'Denmark', 'contactpanda' ), 'code' => '+45' ),
			array( 'country' => 'EE', 'label' => 'Estland', 'code' => '+372' ),
			array( 'country' => 'FI', 'label' => 'Finnland', 'code' => '+358' ),
			array( 'country' => 'FR', 'label' => 'Frankreich', 'code' => '+33' ),
			array( 'country' => 'FO', 'label' => __( 'Faroe Islands', 'contactpanda' ), 'code' => '+298' ),
			array( 'country' => 'GI', 'label' => 'Gibraltar', 'code' => '+350' ),
			array( 'country' => 'GR', 'label' => 'Griechenland', 'code' => '+30' ),
			array( 'country' => 'GB', 'label' => __( 'United Kingdom', 'contactpanda' ), 'code' => '+44' ),
			array( 'country' => 'IE', 'label' => 'Irland', 'code' => '+353' ),
			array( 'country' => 'IS', 'label' => 'Island', 'code' => '+354' ),
			array( 'country' => 'XK', 'label' => 'Kosovo', 'code' => '+383' ),
			array( 'country' => 'HR', 'label' => 'Kroatien', 'code' => '+385' ),
			array( 'country' => 'LV', 'label' => 'Lettland', 'code' => '+371' ),
			array( 'country' => 'LI', 'label' => 'Liechtenstein', 'code' => '+423' ),
			array( 'country' => 'LT', 'label' => 'Litauen', 'code' => '+370' ),
			array( 'country' => 'LU', 'label' => 'Luxemburg', 'code' => '+352' ),
			array( 'country' => 'MT', 'label' => 'Malta', 'code' => '+356' ),
			array( 'country' => 'MD', 'label' => 'Moldau', 'code' => '+373' ),
			array( 'country' => 'MC', 'label' => 'Monaco', 'code' => '+377' ),
			array( 'country' => 'ME', 'label' => 'Montenegro', 'code' => '+382' ),
			array( 'country' => 'NL', 'label' => 'Niederlande', 'code' => '+31' ),
			array( 'country' => 'MK', 'label' => 'Nordmazedonien', 'code' => '+389' ),
			array( 'country' => 'NO', 'label' => 'Norwegen', 'code' => '+47' ),
			array( 'country' => 'PL', 'label' => 'Polen', 'code' => '+48' ),
			array( 'country' => 'PT', 'label' => 'Portugal', 'code' => '+351' ),
			array( 'country' => 'RO', 'label' => __( 'Romania', 'contactpanda' ), 'code' => '+40' ),
			array( 'country' => 'RU', 'label' => 'Russland', 'code' => '+7' ),
			array( 'country' => 'SM', 'label' => 'San Marino', 'code' => '+378' ),
			array( 'country' => 'SE', 'label' => 'Schweden', 'code' => '+46' ),
			array( 'country' => 'RS', 'label' => 'Serbien', 'code' => '+381' ),
			array( 'country' => 'SK', 'label' => 'Slowakei', 'code' => '+421' ),
			array( 'country' => 'SI', 'label' => 'Slowenien', 'code' => '+386' ),
			array( 'country' => 'ES', 'label' => 'Spanien', 'code' => '+34' ),
			array( 'country' => 'CZ', 'label' => 'Tschechien', 'code' => '+420' ),
			array( 'country' => 'TR', 'label' => __( 'Türkiye', 'contactpanda' ), 'code' => '+90' ),
			array( 'country' => 'UA', 'label' => 'Ukraine', 'code' => '+380' ),
			array( 'country' => 'HU', 'label' => 'Ungarn', 'code' => '+36' ),
			array( 'country' => 'BY', 'label' => 'Belarus', 'code' => '+375' ),
			array( 'country' => 'CY', 'label' => 'Zypern', 'code' => '+357' ),
		);
	}

	private static function normalize_phone( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' === $phone ) {
			return '';
		}
		$phone = preg_replace( '/[^0-9+]/', '', $phone );
		if ( 0 === strpos( $phone, '00' ) ) {
			$phone = '+' . substr( $phone, 2 );
		}
		if ( '+' !== substr( $phone, 0, 1 ) ) {
			$national = preg_replace( '/\D/', '', $phone );
			$national = 0 === strpos( $national, '0' ) ? substr( $national, 1 ) : $national;
			return $national ? '+43' . $national : '';
		}
		$digits = preg_replace( '/\D/', '', substr( $phone, 1 ) );
		$normalized = '+' . $digits;
		$countries = self::phone_countries();
		usort( $countries, static function ( $a, $b ) { return strlen( $b['code'] ) <=> strlen( $a['code'] ); } );
		foreach ( $countries as $country ) {
			if ( 0 === strpos( $normalized, $country['code'] ) ) {
				$national = substr( $normalized, strlen( $country['code'] ) );
				if ( '+39' !== $country['code'] && 0 === strpos( $national, '0' ) ) {
					$national = substr( $national, 1 );
				}
				return $country['code'] . $national;
			}
		}
		return $normalized;
	}

	private static function phone_country_code( $phone ) {
		$phone = self::normalize_phone( $phone );
		$countries = self::phone_countries();
		usort( $countries, static function ( $a, $b ) { return strlen( $b['code'] ) <=> strlen( $a['code'] ); } );
		foreach ( $countries as $country ) {
			if ( 0 === strpos( $phone, $country['code'] ) ) {
				return $country['code'];
			}
		}
		return '';
	}

	private static function format_phone( $phone ) {
		$phone = self::normalize_phone( $phone );
		if ( '' === $phone ) {
			return '—';
		}
		$country_code = self::phone_country_code( $phone );
		if ( ! $country_code ) {
			return $phone;
		}
		$national = substr( $phone, strlen( $country_code ) );
		if ( '+43' !== $country_code ) {
			return trim( $country_code . ' ' . $national );
		}
		$prefix = '';
		foreach ( array( '316', '463', '512', '662', '732' ) as $known ) {
			if ( 0 === strpos( $national, $known ) ) {
				$prefix = $known;
				break;
			}
		}
		if ( ! $prefix && preg_match( '/^(65[0-9]|66[0-9]|67[0-9]|68[0-9]|69[0-9]|710|720|730|740|780|800|810|820|828|900|901|930|931|939)/', $national, $match ) ) {
			$prefix = $match[1];
		}
		if ( ! $prefix && 0 === strpos( $national, '1' ) ) {
			$prefix = '1';
		}
		if ( ! $prefix && preg_match( '/^[2-7][0-9]{3}/', $national, $match ) ) {
			$prefix = $match[0];
		}
		return $prefix ? '+43 ' . $prefix . ' ' . substr( $national, strlen( $prefix ) ) : '+43 ' . $national;
	}

	private static function valid_vat_format( $vat ) {
		$vat = self::normalize_vat( $vat );
		$eu_prefixes = array( 'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI' );
		return (bool) preg_match( '/^[A-Z]{2}[A-Z0-9]{2,12}$/', $vat ) && in_array( substr( $vat, 0, 2 ), $eu_prefixes, true );
	}

	private static function vat_user_from_request() {
		$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
		$contact_id = isset( $_POST['contact_id'] ) ? absint( $_POST['contact_id'] ) : 0;
		if ( $company_id ) {
			return self::company_primary_user( $company_id );
		}
		if ( $contact_id ) {
			$contact = self::contact( $contact_id );
			return $contact ? self::contact_user( $contact ) : false;
		}
		return false;
	}

	private static function guard_ajax() {
		if ( ! current_user_can( self::access_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 );
		}
		check_ajax_referer( 'contactpanda_update_company', 'nonce' );
	}

	private static function contact_event_summaries( $contacts ) {
		$summaries = array();
		foreach ( $contacts as $contact ) {
			$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			if ( $contact_id ) {
				$summaries[ $contact_id ] = array( 'count' => 0, 'visited' => 0 );
			}
		}
		return (array) apply_filters( 'contactpanda_event_summaries', $summaries, $contacts );
	}

	private static function contact_broadcasts( $contact_id ) {
		$contact_id = absint( $contact_id );
		if ( ContactPanda_Store::standalone() ) return (array) apply_filters( 'contactpanda_contact_mailings', array(), $contact_id );
		if ( ! $contact_id || ! function_exists( '\\Groundhogg\\get_db' ) || ! class_exists( '\\Groundhogg\\Broadcast' ) ) {
			return (array) apply_filters( 'contactpanda_contact_mailings', array(), $contact_id );
		}
		try {
			$events = \Groundhogg\get_db( 'events' )->query(
				array(
					'contact_id' => $contact_id,
					'funnel_id'  => 1,
					'event_type' => 2,
					'status'     => 'complete',
					'limit'      => 200,
					'orderby'    => 'time',
					'order'      => 'DESC',
					'found_rows' => false,
				)
			);
			$activity = \Groundhogg\get_db( 'activity' )->query(
				array(
					'contact_id'    => $contact_id,
					'funnel_id'     => 1,
					'activity_type' => array( 'email_opened', 'email_link_click' ),
					'limit'         => 500,
					'orderby'       => 'timestamp',
					'order'         => 'DESC',
					'found_rows'    => false,
				)
			);
		} catch ( Throwable $e ) {
			return (array) apply_filters( 'contactpanda_contact_mailings', array(), $contact_id );
		}

		$engagement = array();
		foreach ( is_array( $activity ) ? $activity : array() as $item ) {
			$step_id = absint( self::value( $item, array( 'step_id' ) ) );
			$type = self::value( $item, array( 'activity_type', 'type' ) );
			if ( ! $step_id ) {
				continue;
			}
			if ( ! isset( $engagement[ $step_id ] ) ) {
				$engagement[ $step_id ] = array( 'opened' => false, 'clicked' => false );
			}
			if ( 'email_opened' === $type ) {
				$engagement[ $step_id ]['opened'] = true;
			}
			if ( 'email_link_click' === $type ) {
				$engagement[ $step_id ]['clicked'] = true;
				$engagement[ $step_id ]['opened'] = true;
			}
		}

		$broadcasts = array();
		foreach ( is_array( $events ) ? $events : array() as $event ) {
			$broadcast_id = absint( self::value( $event, array( 'step_id' ) ) );
			if ( ! $broadcast_id || isset( $broadcasts[ $broadcast_id ] ) ) {
				continue;
			}
			try {
				$broadcast = new \Groundhogg\Broadcast( $broadcast_id );
				if ( ! $broadcast->exists() || ! $broadcast->is_email() ) {
					continue;
				}
				$timestamp = absint( self::value( $event, array( 'time' ) ) );
				if ( ! $timestamp ) {
					$timestamp = $broadcast->get_send_time();
				}
				$tracking = isset( $engagement[ $broadcast_id ] ) ? $engagement[ $broadcast_id ] : array( 'opened' => false, 'clicked' => false );
				$broadcasts[ $broadcast_id ] = array(
					'id'        => $broadcast_id,
					'title'     => $broadcast->get_title(),
					'timestamp' => $timestamp,
					'date'      => $timestamp ? wp_date( 'd.m.Y H:i', $timestamp ) : '—',
					'opened'    => ! empty( $tracking['opened'] ),
					'clicked'   => ! empty( $tracking['clicked'] ),
					'delivered' => true,
					'source'    => __( 'Groundhogg', 'contactpanda' ),
				);
			} catch ( Throwable $e ) {
				continue;
			}
		}
		usort( $broadcasts, static function ( $left, $right ) { return $right['timestamp'] <=> $left['timestamp']; } );
		return array_values( (array) apply_filters( 'contactpanda_contact_mailings', array_values( $broadcasts ), $contact_id ) );
	}

	private static function contact_events( $contact ) {
		return (array) apply_filters( 'contactpanda_contact_events', array(), $contact );
	}

	private static function orders( $contacts ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$emails = array();
		$user_ids = array();
		foreach ( $contacts as $contact ) {
			$emails = array_merge( $emails, self::contact_emails( $contact ) );
			$user = self::contact_user( $contact );
			if ( $user ) {
				$user_ids[] = (int) $user->ID;
				$emails[] = $user->user_email;
				$emails[] = get_user_meta( $user->ID, 'billing_email', true );
			}
		}
		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ) ) ) );
		$user_ids = array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
		if ( ! $emails && ! $user_ids ) {
			return array();
		}
		$orders = array();
		foreach ( $user_ids as $user_id ) {
			try {
				$found = wc_get_orders( array( 'customer_id' => $user_id, 'limit' => 25, 'orderby' => 'date', 'order' => 'DESC' ) );
			} catch ( Throwable $e ) {
				$found = array();
			}
			foreach ( $found as $order ) {
				$orders[ $order->get_id() ] = $order;
			}
		}
		foreach ( $emails as $email ) {
			try {
				$found = wc_get_orders( array( 'billing_email' => $email, 'limit' => 25, 'orderby' => 'date', 'order' => 'DESC' ) );
			} catch ( Throwable $e ) {
				$found = array();
			}
			foreach ( $found as $order ) {
				$orders[ $order->get_id() ] = $order;
			}
		}
		usort( $orders, static function ( $a, $b ) {
			$a_date = $a->get_date_created();
			$b_date = $b->get_date_created();
			return ( $b_date ? $b_date->getTimestamp() : 0 ) <=> ( $a_date ? $a_date->getTimestamp() : 0 );
		} );
		return array_slice( $orders, 0, 25 );
	}

	private static function empty_order_summary() {
		return array(
			'count'           => 0,
			'total'           => 0.0,
			'formatted_total' => function_exists( 'wc_price' ) ? wc_price( 0 ) : '—',
		);
	}

	private static function contact_order_summaries( $contacts ) {
		$summaries = array();
		$user_map = array();
		$email_map = array();

		foreach ( $contacts as $contact ) {
			$contact_id = absint( self::value( $contact, array( 'id', 'ID' ) ) );
			if ( ! $contact_id ) {
				continue;
			}
			$summaries[ $contact_id ] = self::empty_order_summary();
			$summaries[ $contact_id ]['order_ids'] = array();

			$user_id = absint( self::value( $contact, array( 'user_id', 'get_user_id' ) ) );
			if ( $user_id ) {
				$user_map[ $user_id ][] = $contact_id;
			}
			$emails = self::contact_emails( $contact );
			// Only hydrate a WordPress user when the lightweight contact identity
			// already contains its ID. Looking up every contact by email turns the
			// quick filter into hundreds of individual database queries.
			$user = $user_id ? get_user_by( 'id', $user_id ) : false;
			if ( $user ) {
				$emails[] = $user->user_email;
				$emails[] = get_user_meta( $user->ID, 'billing_email', true );
			}
			foreach ( $emails as $email ) {
				$email = strtolower( sanitize_email( $email ) );
				if ( $email ) {
					$email_map[ $email ][] = $contact_id;
				}
			}
		}

		if ( ! $summaries || ! function_exists( 'wc_get_orders' ) || ( ! $user_map && ! $email_map ) ) {
			return $summaries;
		}

		// WooCommerce's public `customer` query accepts both user IDs and billing
		// email addresses and works with the classic posts data store as well as
		// HPOS. The previous classic-store fallback queried every order on the site,
		// which could exhaust WordPress memory merely by opening the people list.
		$customers = array_values( array_unique( array_merge( array_keys( $user_map ), array_keys( $email_map ) ), SORT_REGULAR ) );
		foreach ( array_chunk( $customers, 100 ) as $customer_chunk ) {
			$args = array(
				'customer' => $customer_chunk,
				'limit'    => 100,
				'paginate' => true,
				'return'   => 'objects',
				'status'   => self::summary_order_statuses(),
			);
			try {
				self::accumulate_order_summaries( $args, $user_map, $email_map, $summaries );
			} catch ( Throwable $e ) {
				// A broken optional WooCommerce data source must not take down the CRM list.
				continue;
			}
		}

		foreach ( $summaries as &$summary ) {
			$summary['count'] = count( $summary['order_ids'] );
			$summary['formatted_total'] = function_exists( 'wc_price' ) ? wc_price( $summary['total'] ) : (string) $summary['total'];
			unset( $summary['order_ids'] );
		}
		unset( $summary );
		return $summaries;
	}

	private static function summary_order_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		// Keep the list summary consistent with the order history in the person
		// detail view. Cancelled and failed orders remain historical orders.
		return array_values( array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ) );
	}

	/** Include historical aliases retained by the local duplicate merge. */
	private static function contact_emails( $contact ) {
		$emails = array( self::value( $contact, array( 'email', 'get_email' ) ) );
		foreach ( self::contact_channels( $contact ) as $channel ) if ( 'email' === $channel['type'] ) $emails[] = $channel['value'];
		if ( is_array( $contact ) && ! empty( $contact['meta']['merged_email_aliases'] ) && is_array( $contact['meta']['merged_email_aliases'] ) ) {
			$emails = array_merge( $emails, $contact['meta']['merged_email_aliases'] );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ) ) ) );
	}

	private static function accumulate_order_summaries( $args, $user_map, $email_map, &$summaries ) {
		$page = 1;
		do {
			$args['page'] = $page;
			$result = wc_get_orders( $args );
			$orders = is_object( $result ) && isset( $result->orders ) ? $result->orders : ( is_array( $result ) ? $result : array() );
			$max_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;

			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || is_a( $order, 'WC_Order_Refund' ) || ! method_exists( $order, 'get_id' ) || ! method_exists( $order, 'get_total' ) ) {
					continue;
				}
				$matched_contacts = array();
				$customer_id = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
				$email = method_exists( $order, 'get_billing_email' ) ? strtolower( sanitize_email( $order->get_billing_email() ) ) : '';
				if ( $customer_id && isset( $user_map[ $customer_id ] ) ) {
					$matched_contacts = array_merge( $matched_contacts, $user_map[ $customer_id ] );
				}
				if ( $email && isset( $email_map[ $email ] ) ) {
					$matched_contacts = array_merge( $matched_contacts, $email_map[ $email ] );
				}
				$matched_contacts = array_unique( array_map( 'absint', $matched_contacts ) );
				if ( ! $matched_contacts ) {
					continue;
				}

				$order_id = (int) $order->get_id();
				$order_total = (float) $order->get_total();
				if ( method_exists( $order, 'get_total_refunded' ) ) {
					$order_total -= (float) $order->get_total_refunded();
				}
				$order_total = max( 0, $order_total );
				foreach ( $matched_contacts as $contact_id ) {
					if ( ! isset( $summaries[ $contact_id ] ) || isset( $summaries[ $contact_id ]['order_ids'][ $order_id ] ) ) {
						continue;
					}
					$summaries[ $contact_id ]['order_ids'][ $order_id ] = true;
					$summaries[ $contact_id ]['total'] += $order_total;
				}
			}
			$page++;
		} while ( $page <= $max_pages );
	}

	private static function render_company_notes( $company_id ) {
		if ( ContactPanda_Store::standalone() ) {
			$notes = ContactPanda_Store::activities( 0, $company_id, 'notes' );
		} else {
			$result = self::execute_ability(
			'groundhogg-companies/list-company-notes',
			array( 'company_id' => $company_id, 'limit' => 20, 'offset' => 0 )
			);
			$notes = is_array( $result ) && isset( $result['notes'] ) && is_array( $result['notes'] ) ? $result['notes'] : array();
		}
		echo '<section class="contactpanda-card"><h2>' . esc_html__( 'Notes', 'contactpanda' ) . ' <span class="contactpanda-count">' . esc_html( count( $notes ) ) . '</span></h2><div class="contactpanda-timeline">';
		if ( ! $notes ) {
			echo '<div><span></span><strong>' . esc_html__( 'No company notes yet', 'contactpanda' ) . '</strong></div>';
		} else {
			foreach ( $notes as $note ) {
				$title = self::value( $note, array( 'summary', 'title' ), __( 'Company note', 'contactpanda' ) );
				$content = self::value( $note, array( 'content', 'note' ) );
				$date = self::value( $note, array( 'date_created', 'created_at' ) );
				echo '<div><span></span><strong>' . esc_html( $title ) . '</strong><p>' . esc_html( wp_strip_all_tags( $content ) ) . '</p>';
				if ( $date ) {
					echo '<small>' . esc_html( $date ) . '</small>';
				}
				echo '</div>';
			}
		}
		echo '</div></section>';
	}

	private static function render_company_activity_history( $company_id ) {
		$activities = ContactPanda_Store::standalone() ? ContactPanda_Store::activities( 0, $company_id, 'activity' ) : array();
		self::render_activity_timeline( $activities );
	}

	/** Save extension-owned metadata through the currently selected data source. */
	public static function extension_update_custom_meta( $entity, $id, $meta ) {
		$entity = in_array( $entity, array( 'company', 'companies' ), true ) ? 'companies' : 'contacts';
		$id = absint( $id );
		if ( ! $id || ! is_array( $meta ) ) return false;
		$clean = array();
		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( $key );
			if ( 0 !== strpos( $key, ContactPanda_Custom_Fields::PREFIX ) ) continue;
			$clean[ $key ] = is_scalar( $value ) ? $value : '';
		}
		if ( ContactPanda_Store::standalone() ) {
			if ( 'contacts' === $entity ) return ContactPanda_Store::update_contact( $id, array( 'meta' => $clean ) );
			$result = true;
			foreach ( $clean as $key => $value ) $result = ContactPanda_Store::update_company( $id, $key, $value ) && $result;
			return $result;
		}
		$ability = 'contacts' === $entity ? 'groundhogg/update-contact' : 'groundhogg-companies/update-company';
		return false !== self::execute_ability( $ability, array( 'id' => $id, 'meta' => $clean ) );
	}

	private static function execute_ability( $name, $input ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return false;
		}
		$ability = wp_get_ability( $name );
		if ( ! $ability ) {
			return false;
		}
		$result = $ability->execute( $input );
		return is_wp_error( $result ) ? false : $result;
	}

	private static function billing( $order ) {
		return self::address( $order, 'billing' );
	}

	private static function address( $order, $type ) {
		$result = array();
		$type = 'shipping' === $type ? 'shipping' : 'billing';
		foreach ( array( 'company', 'first_name', 'last_name', 'address_1', 'address_2', 'postcode', 'city', 'country' ) as $key ) {
			$method = 'get_' . $type . '_' . $key;
			$result[ $key ] = method_exists( $order, $method ) ? $order->{$method}() : '';
		}
		return $result;
	}

	private static function customer_address( $customer, $type ) {
		$result = array();
		$type = 'shipping' === $type ? 'shipping' : 'billing';
		$fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
		if ( 'billing' === $type ) {
			$fields[] = 'email';
			$fields[] = 'phone';
		}
		foreach ( $fields as $field ) {
			$getter = 'get_' . $type . '_' . $field;
			$result[ $field ] = method_exists( $customer, $getter ) ? $customer->{$getter}() : '';
		}
		return $result;
	}

	private static function contact_address( $contact ) {
		$result = array();
		foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) {
			$aliases = array( $field );
			if ( 'address_1' === $field ) {
				$aliases[] = 'street_address_1';
				$aliases[] = 'street_address';
			}
			if ( 'address_2' === $field ) {
				$aliases[] = 'street_address_2';
			}
			if ( 'postcode' === $field ) {
				$aliases[] = 'postal_zip';
				$aliases[] = 'postal_code';
			}
			if ( 'state' === $field ) {
				$aliases[] = 'region';
			}
			$result[ $field ] = self::meta( $contact, $aliases );
		}
		$result['country'] = strtoupper( $result['country'] );
		return $result;
	}

	private static function contact_countries() {
		return array(
			'AT' => __( 'Austria', 'contactpanda' ),
			'DE' => __( 'Germany', 'contactpanda' ),
			'CH' => __( 'Switzerland', 'contactpanda' ),
			'IT' => __( 'Italy', 'contactpanda' ),
			'FR' => __( 'France', 'contactpanda' ),
			'NL' => __( 'Netherlands', 'contactpanda' ),
			'BE' => __( 'Belgium', 'contactpanda' ),
			'CZ' => __( 'Czechia', 'contactpanda' ),
			'SK' => __( 'Slovakia', 'contactpanda' ),
			'HU' => __( 'Hungary', 'contactpanda' ),
			'SI' => __( 'Slovenia', 'contactpanda' ),
			'HR' => __( 'Croatia', 'contactpanda' ),
		);
	}

	private static function format_address( $address ) {
		$street = trim( (string) ( $address['address_1'] ?? '' ) . ' ' . (string) ( $address['address_2'] ?? '' ) );
		$place = implode( ', ', array_filter( array( trim( (string) ( $address['postcode'] ?? '' ) . ' ' . (string) ( $address['city'] ?? '' ) ), $address['state'] ?? '', $address['country'] ?? '' ) ) );
		return implode( "\n", array_filter( array( $street, $place ) ) );
	}

	private static function person_primary_address( $customer, $contact ) {
		$address = self::contact_address( $contact );
		if ( ! array_filter( $address ) && $customer ) {
			$address = self::customer_address( $customer, 'billing' );
		}
		return self::format_address( $address );
	}

	private static function phone_input_html( $phone, $with_labels = false ) {
		$phone = self::normalize_phone( $phone );
		$country_code = self::phone_country_code( $phone );
		if ( ! $country_code ) {
			$country_code = '+43';
		}
		$national = $phone && 0 === strpos( $phone, $country_code ) ? substr( $phone, strlen( $country_code ) ) : '';
		$html = '<span class="contactpanda-phone-input-group">';
		if ( $with_labels ) $html .= '<label class="contactpanda-phone-prefix-field"><span>' . esc_html__( 'Country calling code', 'contactpanda' ) . '</span>';
		$html .= '<select class="contactpanda-phone-prefix" aria-label="' . esc_attr__( 'Country calling code', 'contactpanda' ) . '">';
		foreach ( self::phone_countries() as $country ) {
			$html .= '<option value="' . esc_attr( $country['code'] ) . '"' . selected( $country_code, $country['code'], false ) . '>' . esc_html( $country['label'] . ' ' . $country['code'] ) . '</option>';
		}
		$html .= '</select>';
		if ( $with_labels ) $html .= '</label><label class="contactpanda-phone-number-field"><span>' . esc_html__( 'Phone number', 'contactpanda' ) . '</span>';
		$html .= '<input type="tel" class="contactpanda-phone-national" value="' . esc_attr( $national ) . '" placeholder="' . esc_attr__( 'Phone number', 'contactpanda' ) . '">';
		if ( $with_labels ) $html .= '</label>';
		$html .= '</span>';
		return $html;
	}

	private static function meta( $object, $keys ) {
		if ( is_array( $object ) && isset( $object['meta'] ) && is_array( $object['meta'] ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $object['meta'][ $key ] ) && '' !== (string) $object['meta'][ $key ] ) {
					return $object['meta'][ $key ];
				}
			}
		}
		if ( ! is_object( $object ) || ! method_exists( $object, 'get_meta' ) ) {
			return '';
		}
		foreach ( $keys as $key ) {
			$value = $object->get_meta( $key );
			if ( '' !== (string) $value ) {
				return $value;
			}
		}
		return '';
	}

	/** Read metadata without coercing structured values to strings. */
	private static function raw_meta( $object, $key ) {
		if ( is_array( $object ) && isset( $object['meta'] ) && is_array( $object['meta'] ) && array_key_exists( $key, $object['meta'] ) ) {
			return $object['meta'][ $key ];
		}
		if ( is_object( $object ) && method_exists( $object, 'get_meta' ) ) return $object->get_meta( $key );
		return '';
	}

	private static function value( $source, $candidates, $default = '' ) {
		foreach ( $candidates as $candidate ) {
			if ( is_object( $source ) && method_exists( $source, $candidate ) ) {
				$value = $source->{$candidate}();
			} elseif ( is_object( $source ) && method_exists( $source, 'get_' . $candidate ) ) {
				$getter = 'get_' . $candidate;
				$value = $source->{$getter}();
			} elseif ( is_object( $source ) && isset( $source->{$candidate} ) ) {
				$value = $source->{$candidate};
			} elseif ( is_array( $source ) && isset( $source[ $candidate ] ) ) {
				$value = $source[ $candidate ];
			} else {
				continue;
			}
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}
		return $default;
	}

	private static function field( $label, $value ) {
		$display = '' !== (string) $value ? nl2br( esc_html( $value ) ) : '—';
		echo '<div class="contactpanda-field"><span>' . esc_html( $label ) . '</span><strong>' . wp_kses( $display, array( 'br' => array() ) ) . '</strong></div>';
	}

	private static function salutation_label( $value ) {
		$labels = array( 'mr' => __( 'Mr', 'contactpanda' ), 'ms' => __( 'Ms', 'contactpanda' ), 'family' => __( 'Family', 'contactpanda' ), 'neutral' => __( 'Neutral', 'contactpanda' ) );
		return $labels[ sanitize_key( $value ) ] ?? '—';
	}

	private static function salutation_field( $value, $first_name, $contact_id ) {
		$suggested = ContactPanda_Store::suggested_salutation( $first_name );
		$value = sanitize_key( $value );
		$display = $value ? self::salutation_label( $value ) : __( 'Add salutation', 'contactpanda' );
		echo '<div class="contactpanda-field contactpanda-salutation-field" data-contact="' . esc_attr( $contact_id ) . '"><span>' . esc_html__( 'Salutation', 'contactpanda' ) . '</span><button type="button" class="button-link contactpanda-salutation-value' . ( $value ? '' : ' is-empty' ) . '" data-value="' . esc_attr( $value ) . '">' . esc_html( $display ) . '</button><div class="contactpanda-salutation-editor" hidden><select class="contactpanda-salutation-select">';
		echo '<option value=""' . selected( $value, '', false ) . '>' . esc_html__( '--- Please select ---', 'contactpanda' ) . '</option>';
		foreach ( array( 'neutral', 'mr', 'ms', 'family' ) as $option ) echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( self::salutation_label( $option ) ) . '</option>';
		echo '</select><button type="button" class="button contactpanda-cancel-salutation">' . esc_html__( 'Cancel', 'contactpanda' ) . '</button></div>';
		if ( $suggested && $suggested !== $value ) echo '<button type="button" class="button-link contactpanda-salutation-suggestion" data-value="' . esc_attr( $suggested ) . '">' . esc_html( sprintf( __( 'Suggestion: %s', 'contactpanda' ), self::salutation_label( $suggested ) ) ) . '</button>';
		echo '<div class="contactpanda-field-message" aria-live="polite"></div></div>';
	}

	private static function editable_field( $label, $value, $field, $company_id, $multiline = false ) {
		echo '<div class="contactpanda-field contactpanda-editable" data-entity="company" data-company="' . esc_attr( $company_id ) . '" data-field="' . esc_attr( $field ) . '" data-multiline="' . ( $multiline ? '1' : '0' ) . '">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contactpanda-field-row"><button type="button" class="button-link contactpanda-edit contactpanda-field-value" data-value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contactpanda' ), $label ) ) . '">' . esc_html( '' !== (string) $value ? $value : __( 'Add', 'contactpanda' ) ) . '</button></div>';
		echo '<div class="contactpanda-field-message" aria-live="polite"></div></div>';
	}

	private static function list_editable_field( $label, $display, $field, $entity, $id, $type = '', $raw_value = null ) {
		$value = null === $raw_value ? $display : $raw_value;
		echo '<div class="contactpanda-editable contactpanda-list-editable" data-entity="' . esc_attr( $entity ) . '" data-' . esc_attr( $entity ) . '="' . esc_attr( $id ) . '" data-field="' . esc_attr( $field ) . '"' . ( $type ? ' data-type="' . esc_attr( $type ) . '"' : '' ) . '>';
		echo '<div class="contactpanda-field-row"><button type="button" class="button-link contactpanda-edit contactpanda-field-value" data-value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contactpanda' ), $label ) ) . '">' . esc_html( '' !== (string) $display ? $display : __( 'Add', 'contactpanda' ) ) . '</button></div><div class="contactpanda-field-message" aria-live="polite"></div></div>';
	}

	private static function contact_editable_field( $label, $value, $field, $contact_id, $multiline = false ) {
		echo '<div class="contactpanda-field contactpanda-editable" data-entity="contact" data-contact="' . esc_attr( $contact_id ) . '" data-field="' . esc_attr( $field ) . '" data-multiline="' . ( $multiline ? '1' : '0' ) . '">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contactpanda-field-row"><button type="button" class="button-link contactpanda-edit contactpanda-field-value" data-value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contactpanda' ), $label ) ) . '">' . esc_html( '' !== (string) $value ? $value : __( 'Add', 'contactpanda' ) ) . '</button>';
		if ( 'email' === $field && is_email( $value ) ) {
			echo '<a class="button-link contactpanda-mail-link" href="mailto:' . esc_attr( $value ) . '" aria-label="' . esc_attr__( 'Send email', 'contactpanda' ) . '" title="' . esc_attr__( 'Send email', 'contactpanda' ) . '"><span class="dashicons dashicons-email-alt"></span></a>';
		}
		echo '</div>';
		echo '<div class="contactpanda-field-message" aria-live="polite"></div></div>';
	}

	private static function contact_email_field( $label, $email ) {
		echo '<div class="contactpanda-field"><span>' . esc_html( $label ) . '</span><div class="contactpanda-field-row"><strong>' . esc_html( $email ?: '—' ) . '</strong>';
		if ( is_email( $email ) ) {
			echo '<a class="button-link contactpanda-mail-link" href="mailto:' . esc_attr( $email ) . '" aria-label="' . esc_attr__( 'Send email', 'contactpanda' ) . '" title="' . esc_attr__( 'Send email', 'contactpanda' ) . '"><span class="dashicons dashicons-email-alt"></span></a>';
		}
		echo '</div></div>';
	}

	private static function editable_address_summary( $label, $address ) {
		echo '<div class="contactpanda-field contactpanda-address-summary"><span>' . esc_html( $label ) . '</span><div class="contactpanda-field-row"><button type="button" class="button-link contactpanda-edit-address-summary contactpanda-address-summary-value" aria-label="' . esc_attr__( 'Edit address', 'contactpanda' ) . '">' . wp_kses( nl2br( esc_html( $address ?: __( 'Add', 'contactpanda' ) ) ), array( 'br' => array() ) ) . '</button></div><small>' . esc_html__( 'The structured contact address opens for editing.', 'contactpanda' ) . '</small></div>';
	}

	private static function phone_field( $label, $value, $field, $id, $entity ) {
		$value = self::normalize_phone( $value );
		$id_attr = 'contact' === $entity ? 'data-contact="' . esc_attr( $id ) . '"' : 'data-company="' . esc_attr( $id ) . '"';
		echo '<div class="contactpanda-field contactpanda-editable contactpanda-phone-field" data-entity="' . esc_attr( $entity ) . '" ' . $id_attr . ' data-field="' . esc_attr( $field ) . '" data-type="phone" data-multiline="0">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contactpanda-field-row"><button type="button" class="button-link contactpanda-edit contactpanda-field-value" data-value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contactpanda' ), $label ) ) . '">' . esc_html( $value ? self::format_phone( $value ) : __( 'Add', 'contactpanda' ) ) . '</button></div>';
		echo '<div class="contactpanda-field-message" aria-live="polite"></div></div>';
	}

	private static function uid_field( $label, $value, $id, $entity, $verified ) {
		$attr = 'company' === $entity ? 'data-company="' . esc_attr( $id ) . '"' : 'data-contact="' . esc_attr( $id ) . '"';
		echo '<div class="contactpanda-field contactpanda-editable contactpanda-uid-field" data-entity="' . esc_attr( $entity ) . '" ' . $attr . ' data-field="uid" data-multiline="0" data-verified="' . ( $verified ? '1' : '0' ) . '">';
		echo '<span>' . esc_html( $label ) . '</span><div class="contactpanda-field-row"><div class="contactpanda-value-with-status"><button type="button" class="button-link contactpanda-edit contactpanda-field-value" data-value="' . esc_attr( $value ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit %s', 'contactpanda' ), $label ) ) . '">' . esc_html( '' !== (string) $value ? $value : __( 'Add', 'contactpanda' ) ) . '</button><span class="contactpanda-vat-check" title="' . esc_attr__( 'Successfully validated through EU VIES', 'contactpanda' ) . '"' . ( $verified ? '' : ' hidden' ) . '>✓</span></div></div>';
		echo '<div class="contactpanda-field-message" aria-live="polite"></div></div>';
	}

	private static function readonly_uid_field( $label, $value, $verified ) {
		echo '<div class="contactpanda-field"><span>' . esc_html( $label ) . '</span><div class="contactpanda-value-with-status"><strong>' . esc_html( '' !== (string) $value ? $value : '—' ) . '</strong>';
		if ( $verified ) {
			echo '<span class="contactpanda-vat-check" title="' . esc_attr__( 'Successfully validated through EU VIES', 'contactpanda' ) . '">✓</span>';
		}
		echo '</div></div>';
	}

	private static function render_status() {
		echo __( '<section class="contactpanda-card contactpanda-status"><h2>System status</h2>', 'contactpanda' );
		foreach ( self::dependencies() as $name => $version ) {
			echo '<div><span class="contactpanda-dot ' . ( $version ? 'ok' : '' ) . '"></span><strong>' . esc_html( $name ) . '</strong><span>' . esc_html( is_string( $version ) ? $version : ( $version ? __( 'detected', 'contactpanda' ) : __( 'not detected', 'contactpanda' ) ) ) . '</span></div>';
		}
		echo '</section>';
	}
}

