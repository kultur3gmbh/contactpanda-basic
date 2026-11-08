<?php

defined( 'ABSPATH' ) || exit;

/** Import, merge, shared-library and administrator quality-of-life tools. */
final class ContactPanda_Data_Tools {
	const CENTRAL_OPTION = 'contactpanda_central_salutation_library';
	const NOTICE_OPTION  = 'contactpanda_admin_notice_settings';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 25 );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_migration_url' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_filter( 'contactpanda_settings_tabs', array( __CLASS__, 'settings_tabs' ) );
		add_action( 'contactpanda_render_other_settings_section_duplicates', array( __CLASS__, 'render_duplicate_settings' ) );
		add_action( 'contactpanda_render_salutation_tools', array( __CLASS__, 'render_central_library' ) );
		add_filter( 'contactpanda_misc_settings_sections', array( __CLASS__, 'misc_sections' ) );
		add_action( 'contactpanda_render_misc_settings_section_admin_notices', array( __CLASS__, 'render_notice_settings' ) );
		add_action( 'admin_post_contactpanda_csv_preview', array( __CLASS__, 'handle_csv_preview' ) );
		add_action( 'admin_post_contactpanda_csv_import', array( __CLASS__, 'handle_csv_import' ) );
		add_action( 'admin_post_contactpanda_rollback_import', array( __CLASS__, 'handle_import_rollback' ) );
		add_action( 'admin_post_contactpanda_merge_preview', array( __CLASS__, 'handle_merge_preview' ) );
		add_action( 'admin_post_contactpanda_merge_group', array( __CLASS__, 'handle_merge_group' ) );
		add_action( 'admin_post_contactpanda_merge_commit', array( __CLASS__, 'handle_merge_commit' ) );
		add_action( 'admin_post_contactpanda_central_salutations', array( __CLASS__, 'handle_central_settings' ) );
		add_action( 'admin_post_contactpanda_sync_central_salutations', array( __CLASS__, 'handle_central_sync' ) );
		add_action( 'admin_post_contactpanda_admin_notice_settings', array( __CLASS__, 'handle_notice_settings' ) );
		add_action( 'admin_post_contactpanda_import_groundhogg_flow_draft', array( __CLASS__, 'handle_groundhogg_flow_draft' ) );
		add_action( 'wp_ajax_contactpanda_dismiss_admin_notice', array( __CLASS__, 'dismiss_admin_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_notice_helper' ), 100 );
		add_action( 'contactpanda_central_salutation_sync', array( __CLASS__, 'scheduled_central_sync' ) );
	}

	/** Preserve old bookmarks while keeping migration out of Settings. */
	public static function redirect_legacy_migration_url() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$section = isset( $_GET['other_section'] ) ? sanitize_key( wp_unslash( $_GET['other_section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$legacy_transfer = 'contactpanda-data-transfer' === $page && 'migration' === sanitize_key( wp_unslash( $_GET['transfer_section'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$legacy_settings = 'contactpanda-settings' === $page && ( 'migration' === $tab || 'migration' === $section );
		if ( ! $legacy_transfer && ! $legacy_settings ) return;
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-migration' ) );
		exit;
	}

	public static function menu() {
		add_submenu_page(
			'contactpanda',
			__( 'Import and export', 'contactpanda' ),
			__( 'Import / Export', 'contactpanda' ),
			'manage_options',
			'contactpanda-data-transfer',
			array( __CLASS__, 'render_data_transfer' )
		);
		add_submenu_page(
			'contactpanda',
			__( 'Migration', 'contactpanda' ),
			__( 'Migration', 'contactpanda' ),
			'manage_options',
			'contactpanda-migration',
			array( __CLASS__, 'render_migration' )
		);
	}

	public static function render_data_transfer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$section = isset( $_GET['transfer_section'] ) ? sanitize_key( wp_unslash( $_GET['transfer_section'] ) ) : 'import'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		if ( ! in_array( $section, array( 'import', 'export' ), true ) ) $section = 'import';
		echo '<div class="wrap contactpanda-wrap"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'Import and export', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Move data with previews, filters, and a traceable history.', 'contactpanda' ) . '</p></div></div>';
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs"><a class="nav-tab ' . ( 'import' === $section ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-data-transfer&transfer_section=import' ) ) . '">' . esc_html__( 'Import', 'contactpanda' ) . '</a><a class="nav-tab ' . ( 'export' === $section ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-data-transfer&transfer_section=export' ) ) . '">' . esc_html__( 'Export', 'contactpanda' ) . '</a></nav>';
		if ( 'export' === $section ) self::render_export();
		else {
			$entity = isset( $_GET['entity'] ) ? sanitize_key( wp_unslash( $_GET['entity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only import filter.
			if ( in_array( $entity, array( 'contacts', 'companies' ), true ) ) {
				self::render_csv_import( $entity );
				if ( 'contacts' === $entity ) do_action( 'contactpanda_render_contact_import_extensions', '' );
				self::render_import_history( $entity );
			} else self::render_migration_tools();
		}
		echo '</div>';
	}

	/** Render migrations separately from ordinary file import and export. */
	public static function render_migration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		}
		$sources = ContactPanda_Registration::migration_sources();
		$sources = array_filter( $sources, static function ( $source ) { return ! isset( $source['installed'] ) || ! empty( $source['installed'] ); } );
		$source = isset( $_GET['migration_source'] ) ? sanitize_key( wp_unslash( $_GET['migration_source'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		if ( 'overview' !== $source && ! isset( $sources[ $source ] ) ) $source = 'overview';
		echo '<div class="wrap contactpanda-wrap"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'Migration', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Move data from supported systems into ContactPanda with previews and without changing the source records.', 'contactpanda' ) . '</p></div></div>';
		echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs contactpanda-migration-tabs" aria-label="' . esc_attr__( 'Migration sources', 'contactpanda' ) . '">';
		echo '<a class="nav-tab ' . ( 'overview' === $source ? 'nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-migration' ) ) . '">' . esc_html__( 'Overview', 'contactpanda' ) . '</a>';
		foreach ( $sources as $key => $definition ) {
			$key = sanitize_key( $key );
			if ( ! $key || empty( $definition['label'] ) ) continue;
			$url = add_query_arg( 'migration_source', $key, admin_url( 'admin.php?page=contactpanda-migration' ) );
			echo '<a class="nav-tab ' . ( $key === $source ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $definition['label'] ) . '</a>';
		}
		echo '</nav>';
		$groundhogg_area = 'contacts';
		if ( 'groundhogg' === $source ) {
			$groundhogg_area = sanitize_key( wp_unslash( $_GET['migration_area'] ?? 'contacts' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
			$areas = array( 'contacts' => __( 'People & companies', 'contactpanda' ), 'emails' => __( 'Email templates & mailings', 'contactpanda' ), 'forms' => __( 'Forms', 'contactpanda' ), 'flows' => __( 'Automations', 'contactpanda' ) );
			if ( ! isset( $areas[ $groundhogg_area ] ) ) $groundhogg_area = 'contacts';
			echo '<nav class="nav-tab-wrapper contactpanda-settings-subtabs contactpanda-migration-area-tabs" aria-label="' . esc_attr__( 'Groundhogg migration areas', 'contactpanda' ) . '">';
			foreach ( $areas as $key => $label ) echo '<a class="nav-tab ' . ( $key === $groundhogg_area ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'migration_source' => 'groundhogg', 'migration_area' => $key ), admin_url( 'admin.php?page=contactpanda-migration' ) ) ) . '">' . esc_html( $label ) . '</a>';
			echo '</nav>';
		}
		if ( ! $sources && 'overview' === $source ) echo '<div class="notice notice-info inline"><p>' . esc_html__( 'No supported source system is currently installed. A matching migration tab appears automatically when a supported plugin is detected.', 'contactpanda' ) . '</p></div>';
		if ( 'groundhogg' !== $source || 'contacts' === $groundhogg_area ) ContactPanda_Registration::render_migration_settings( $source );
		if ( 'groundhogg' === $source && 'flows' === $groundhogg_area ) self::render_groundhogg_flow_migration();
		if ( 'groundhogg' === $source ) do_action( 'contactpanda_render_groundhogg_migration_area_' . $groundhogg_area );
		echo '</div>';
	}

	private static function render_export() {
		if ( apply_filters( 'contactpanda_render_transfer_export', false ) ) return;
		$people_url = admin_url( 'admin.php?page=contactpanda-people#contactpanda-export' );
		$companies_url = admin_url( 'admin.php?page=contactpanda-firmen' );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Filtered export', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Open a list, apply the required search and filters, and export exactly that result. This keeps the exported selection visible and verifiable before download.', 'contactpanda' ) . '</p><p><a class="button button-primary" href="' . esc_url( $people_url ) . '">' . esc_html__( 'Filter and export people', 'contactpanda' ) . '</a> <a class="button" href="' . esc_url( $companies_url ) . '">' . esc_html__( 'Open companies', 'contactpanda' ) . '</a></p></section>';
		$log = array_slice( array_reverse( (array) get_option( 'contactpanda_export_log', array() ) ), 0, 25 );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Export log', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Exports are recorded without storing exported customer data in the log.', 'contactpanda' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'contactpanda' ) . '</th><th>' . esc_html__( 'User', 'contactpanda' ) . '</th><th>' . esc_html__( 'Type', 'contactpanda' ) . '</th><th>' . esc_html__( 'Records', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $log as $entry ) echo '<tr><td>' . esc_html( $entry['created_at'] ?? '' ) . '</td><td>' . esc_html( $entry['user'] ?? '' ) . '</td><td>' . esc_html( $entry['entity'] ?? '' ) . '</td><td>' . esc_html( absint( $entry['count'] ?? 0 ) ) . '</td></tr>';
		if ( ! $log ) echo '<tr><td colspan="4">' . esc_html__( 'No exports have been recorded yet.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table></section>';
	}

	public static function settings_tabs( $tabs ) {
		return $tabs;
	}

	public static function cron_schedules( $schedules ) {
		$schedules['contactpanda_weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once weekly', 'contactpanda' ) );
		return $schedules;
	}

	public static function misc_sections( $sections ) {
		return $sections;
	}

	private static function require_admin( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( $nonce_action );
	}

	private static function import_url( $return_page = '', $args = array() ) {
		if ( in_array( $return_page, array( 'contactpanda', 'contactpanda-firmen' ), true ) ) {
			return add_query_arg( array_merge( array( 'page' => $return_page, 'section' => 'import' ), $args ), admin_url( 'admin.php' ) );
		}
		return add_query_arg( array_merge( array( 'page' => 'contactpanda-data-transfer', 'transfer_section' => 'import' ), $args ), admin_url( 'admin.php' ) );
	}

	private static function duplicates_url( $args = array() ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=contactpanda-settings&tab=other&other_section=duplicates' ) );
	}

	private static function redirect_import_notice( $type, $message, $return_page = '', $args = array() ) {
		set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::import_url( $return_page, $args ) );
		exit;
	}

	private static function redirect_duplicate_notice( $type, $message, $args = array() ) {
		set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::duplicates_url( $args ) );
		exit;
	}

	public static function render_migration_tools() {
		self::render_csv_import();
		do_action( 'contactpanda_render_contact_import_extensions', '' );
		self::render_import_history();
	}

	/**
	 * Show a read-only compatibility report for Groundhogg funnels.
	 *
	 * The source records are never changed. ContactPanda Plus can attach an
	 * importer through the filter used by handle_groundhogg_flow_draft().
	 */
	private static function render_groundhogg_flow_migration() {
		if ( ! self::groundhogg_available() ) return;
		$flows = self::groundhogg_flows();
		$notice = get_transient( 'contactpanda_groundhogg_flow_notice_' . get_current_user_id() );
		if ( $notice ) delete_transient( 'contactpanda_groundhogg_flow_notice_' . get_current_user_id() );
		echo '<section id="groundhogg-flows" class="contactpanda-card contactpanda-settings-card contactpanda-groundhogg-flow-migration"><h2>' . esc_html__( 'Groundhogg flow migration', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Analyze existing Groundhogg funnels before importing them. The analysis is read only. Imported automations are always created as inactive drafts and must be checked before they can be published.', 'contactpanda' ) . '</p>';
		if ( is_array( $notice ) ) echo '<div class="notice notice-' . ( 'success' === ( $notice['type'] ?? '' ) ? 'success' : 'error' ) . ' inline"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>';
		if ( ! $flows ) {
			echo '<p>' . esc_html__( 'No readable Groundhogg funnels were found.', 'contactpanda' ) . '</p></section>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Flow', 'contactpanda' ) . '</th><th>' . esc_html__( 'Steps', 'contactpanda' ) . '</th><th>' . esc_html__( 'Compatibility', 'contactpanda' ) . '</th><th>' . esc_html__( 'Assessment', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $flows as $flow ) {
			$assessment = self::assess_groundhogg_flow( $flow );
			$flow_map = (array) get_option( 'contactpanda_plus_groundhogg_flow_map', array() );
			$already_imported = ! empty( $flow_map[ $flow['source_id'] ] );
			$labels = array( 'supported' => __( 'Directly supported', 'contactpanda' ), 'review' => __( 'Review required', 'contactpanda' ), 'unsupported' => __( 'Not yet supported', 'contactpanda' ) );
			$summary = array();
			foreach ( $assessment['counts'] as $key => $count ) if ( $count ) $summary[] = sprintf( '%1$d × %2$s', $count, $labels[ $key ] );
			echo '<tr><td><strong>' . esc_html( $flow['name'] ) . '</strong><br><small>' . esc_html( $flow['source_id'] ) . '</small></td><td>' . esc_html( count( $flow['steps'] ) ) . '</td><td><span class="contactpanda-migration-status is-' . esc_attr( $assessment['status'] ) . '">' . esc_html( $labels[ $assessment['status'] ] ) . '</span></td><td>' . esc_html( implode( ' · ', $summary ) ) . '<details><summary>' . esc_html__( 'Show step analysis', 'contactpanda' ) . '</summary><ol>';
			foreach ( $assessment['steps'] as $step ) echo '<li><strong>' . esc_html( $step['label'] ) . '</strong> — ' . esc_html( $labels[ $step['status'] ] ) . '</li>';
			echo '</ol></details></td><td>';
			if ( has_filter( 'contactpanda_import_groundhogg_flow_draft' ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_import_groundhogg_flow_draft"><input type="hidden" name="source_id" value="' . esc_attr( $flow['source_id'] ) . '">';
				wp_nonce_field( 'contactpanda_import_groundhogg_flow_draft_' . $flow['source_id'] );
				if ( $already_imported ) {
					echo '<span class="contactpanda-migration-status is-supported">' . esc_html__( 'Already imported', 'contactpanda' ) . '</span><br>';
					echo '<label class="description"><input type="checkbox" name="confirm_update" value="1" required> ' . esc_html__( 'Update the existing draft from Groundhogg again. Local draft changes will be replaced.', 'contactpanda' ) . '</label><br>';
					echo '<button class="button">' . esc_html__( 'Update again', 'contactpanda' ) . '</button></form>';
				} else {
					echo '<button class="button">' . esc_html__( 'Create draft', 'contactpanda' ) . '</button></form>';
				}
			} else {
				echo '<span class="description">' . esc_html__( 'ContactPanda Plus is required to create an automation draft.', 'contactpanda' ) . '</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Green steps can be translated directly. Yellow steps need your review. Red steps are retained as review notes instead of being activated silently.', 'contactpanda' ) . '</p></section>';
	}

	/** Detect Groundhogg without requiring any optional class during ContactPanda startup. */
	private static function groundhogg_available() {
		return defined( 'GROUNDHOGG_VERSION' ) || function_exists( '\\Groundhogg\\get_db' ) || class_exists( '\\Groundhogg\\Contact' );
	}

	/** Create an inactive Plus draft from one re-read Groundhogg source flow. */
	public static function handle_groundhogg_flow_draft() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$source_id = sanitize_text_field( wp_unslash( $_POST['source_id'] ?? '' ) );
		check_admin_referer( 'contactpanda_import_groundhogg_flow_draft_' . $source_id );
		$flow_map = (array) get_option( 'contactpanda_plus_groundhogg_flow_map', array() );
		if ( ! empty( $flow_map[ $source_id ] ) && '1' !== ( $_POST['confirm_update'] ?? '' ) ) wp_die( esc_html__( 'Confirm that the existing automation draft may be updated.', 'contactpanda' ), '', array( 'response' => 400 ) );
		$flow = false;
		foreach ( self::groundhogg_flows() as $candidate ) if ( hash_equals( (string) $candidate['source_id'], $source_id ) ) { $flow = $candidate; break; }
		$result = $flow ? apply_filters( 'contactpanda_import_groundhogg_flow_draft', new WP_Error( 'contactpanda_plus_required', __( 'ContactPanda Plus is required to import automation drafts.', 'contactpanda' ) ), $flow, self::assess_groundhogg_flow( $flow ) ) : new WP_Error( 'contactpanda_groundhogg_flow_missing', __( 'The Groundhogg flow could not be read again.', 'contactpanda' ) );
		$notice = is_wp_error( $result ) ? array( 'type' => 'error', 'message' => $result->get_error_message() ) : array( 'type' => 'success', 'message' => __( 'The inactive automation draft was created or updated. Review every step before publishing it.', 'contactpanda' ) );
		set_transient( 'contactpanda_groundhogg_flow_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-migration&migration_source=groundhogg#groundhogg-flows' ) );
		exit;
	}

	/** Read Groundhogg funnels through its database API without modifying them. */
	private static function groundhogg_flows() {
		if ( ! function_exists( '\\Groundhogg\\get_db' ) ) return array();
		try {
			$rows = (array) \Groundhogg\get_db( 'funnels' )->query( array( 'limit' => 1000, 'orderby' => 'ID', 'order' => 'ASC' ) );
		} catch ( Throwable $error ) {
			return array();
		}
		$flows = array();
		foreach ( $rows as $row ) {
			$id = absint( self::groundhogg_value( $row, array( 'ID', 'id', 'get_id' ) ) );
			if ( ! $id ) continue;
			$name = sanitize_text_field( self::groundhogg_value( $row, array( 'title', 'name', 'get_title', 'get_name' ) ) );
			$steps = array();
			try {
				$steps = (array) \Groundhogg\get_db( 'steps' )->query( array( 'funnel_id' => $id, 'limit' => 1000, 'orderby' => 'step_order', 'order' => 'ASC' ) );
			} catch ( Throwable $error ) {
				$steps = array();
			}
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$flows[] = array( 'source_id' => 'groundhogg:funnel:' . $id, 'source_numeric_id' => $id, 'name' => $name ?: sprintf( __( 'Groundhogg flow #%d', 'contactpanda' ), $id ), 'steps' => array_map( array( __CLASS__, 'normalize_groundhogg_step' ), $steps ) );
		}
		return $flows;
	}

	private static function normalize_groundhogg_step( $step ) {
		$type = sanitize_key( self::groundhogg_value( $step, array( 'step_type', 'type', 'get_step_type', 'get_type' ) ) );
		$id = absint( self::groundhogg_value( $step, array( 'ID', 'id', 'get_id' ) ) );
		$meta_source = $step;
		if ( $id && class_exists( '\\Groundhogg\\Step' ) && ! $step instanceof \Groundhogg\Step ) {
			try { $meta_source = new \Groundhogg\Step( $id ); } catch ( Throwable $error ) { $meta_source = $step; }
		}
		$data = array();
		foreach ( array( array( $step, 'data' ), array( $step, 'get_data' ), array( $step, 'meta' ), array( $meta_source, 'get_meta' ) ) as $source ) {
			$value = self::groundhogg_value( $source[0], array( $source[1] ) );
			if ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) $value = $decoded;
			}
			if ( is_array( $value ) ) $data = array_replace_recursive( $data, $value );
		}
		$class = is_object( $step ) ? get_class( $step ) : '';
		$label = sanitize_text_field( self::groundhogg_value( $step, array( 'title', 'name', 'get_title', 'get_name' ) ) );
		if ( ! $type ) $type = sanitize_key( basename( str_replace( '\\', '/', $class ) ) );
		return array( 'source_step_id' => $id, 'type' => $type ?: 'unknown', 'label' => $label ?: ucwords( str_replace( array( '-', '_' ), ' ', $type ?: 'unknown' ) ), 'data' => $data );
	}

	private static function assess_groundhogg_flow( $flow ) {
		$direct = array( 'wait', 'delay', 'timer', 'delay_timer', 'apply_tag', 'add_tag', 'remove_tag', 'remove_tag_action', 'send_email', 'email', 'edit_meta', 'update_contact', 'email_confirmed', 'confirmed_email', 'optin_status_changed', 'create_user', 'create_wp_user', 'admin_notification', 'notify_admin', 'change_role', 'add_role', 'set_user_role', 'link_click', 'link_clicked', 'email_link_clicked', 'role_changed', 'user_role_changed', 'wc_new_order', 'woocommerce_new_order', 'new_order' );
		$review = array( 'benchmark', 'tag_applied', 'form_filled', 'web_form', 'webhook', 'condition', 'logic', 'date_timer', 'field_changed' );
		$result = array( 'status' => 'supported', 'counts' => array( 'supported' => 0, 'review' => 0, 'unsupported' => 0 ), 'steps' => array() );
		foreach ( (array) $flow['steps'] as $step ) {
			$type = sanitize_key( $step['type'] ?? '' );
			$status = in_array( $type, $direct, true ) ? 'supported' : ( in_array( $type, $review, true ) ? 'review' : 'unsupported' );
			$result['counts'][ $status ]++;
			$result['steps'][] = array( 'label' => $step['label'] ?: $type, 'status' => $status );
		}
		if ( $result['counts']['unsupported'] ) $result['status'] = 'unsupported'; elseif ( $result['counts']['review'] ) $result['status'] = 'review';
		return $result;
	}

	private static function groundhogg_value( $record, $keys ) {
		foreach ( $keys as $key ) {
			if ( is_array( $record ) && array_key_exists( $key, $record ) ) return $record[ $key ];
			if ( is_object( $record ) && isset( $record->{$key} ) ) return $record->{$key};
			if ( is_object( $record ) && method_exists( $record, $key ) ) { try { return $record->{$key}(); } catch ( Throwable $error ) { continue; } }
		}
		return '';
	}

	/** Render the same non-destructive import workflow on People or Companies. */
	public static function render_entity_import( $entity, $return_page ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'You do not have permission to import data.', 'contactpanda' ) . '</p></div>';
			return;
		}
		$entity = 'companies' === $entity ? 'companies' : 'contacts';
		echo '<div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CRM</p><h1>' . esc_html__( 'Import', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Review every file in a preview before ContactPanda saves any records.', 'contactpanda' ) . '</p></div></div>';
		self::render_csv_import( $entity, $return_page );
		if ( 'contacts' === $entity ) do_action( 'contactpanda_render_contact_import_extensions', $return_page );
		self::render_import_history( $entity );
	}

	private static function render_import_history( $entity = '' ) {
		if ( ! ContactPanda_Store::standalone() ) return;
		$runs = ContactPanda_Store::import_runs( 50 );
		if ( $entity ) $runs = array_values( array_filter( $runs, static function ( $run ) use ( $entity ) { return $entity === $run['entity_type']; } ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Import history', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Every local import is recorded with its source, time, result, and affected records. Rollback retains later edits as conflicts instead of overwriting them.', 'contactpanda' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'contactpanda' ) . '</th><th>' . esc_html__( 'Source', 'contactpanda' ) . '</th><th>' . esc_html__( 'Type', 'contactpanda' ) . '</th><th>' . esc_html__( 'Result', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $runs as $run ) {
			$records_url = add_query_arg( array( 'page' => 'companies' === $run['entity_type'] ? 'contactpanda-firmen' : 'contactpanda', 'import_run' => absint( $run['id'] ) ), admin_url( 'admin.php' ) );
			$rollback_url = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_rollback_import&run_id=' . absint( $run['id'] ) ), 'contactpanda_rollback_import_' . absint( $run['id'] ) );
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$summary = sprintf( __( '%1$d created, %2$d updated, %3$d skipped, %4$d errors', 'contactpanda' ), absint( $run['created_count'] ), absint( $run['updated_count'] ), absint( $run['skipped_count'] ), absint( $run['error_count'] ) );
			echo '<tr><td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $run['started_at'] ) ) . '</td><td><strong>' . esc_html( strtoupper( $run['source'] ) ) . '</strong><br><small>' . esc_html( $run['original_filename'] ?: '—' ) . '</small></td><td>' . esc_html( 'companies' === $run['entity_type'] ? __( 'Companies', 'contactpanda' ) : __( 'People', 'contactpanda' ) ) . '</td><td>' . esc_html( $summary ) . '<br><small>' . esc_html( $run['status'] ) . '</small></td><td><a href="' . esc_url( $records_url ) . '">' . esc_html__( 'Show records', 'contactpanda' ) . '</a>';
			if ( 'rolled_back' !== $run['status'] ) echo ' · <a class="contactpanda-rollback-import" href="' . esc_url( $rollback_url ) . '">' . esc_html__( 'Roll back', 'contactpanda' ) . '</a>';
			echo '</td></tr>';
		}
		if ( ! $runs ) echo '<tr><td colspan="5">' . esc_html__( 'No imports have been recorded yet.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table></section>';
	}

	public static function handle_import_rollback() {
		$run_id = absint( $_GET['run_id'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) || ! $run_id ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_rollback_import_' . $run_id );
		$result = ContactPanda_Store::rollback_import_run( $run_id );
		if ( is_wp_error( $result ) ) self::redirect_import_notice( 'error', $result->get_error_message() );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		self::redirect_import_notice( 'success', sprintf( __( 'Import rolled back: %1$d records restored, %2$d new records moved to Trash, %3$d later edits retained as conflicts.', 'contactpanda' ), absint( $result['restored'] ), absint( $result['trashed'] ), absint( $result['conflicts'] ) ) );
	}

	public static function render_duplicate_settings() {
		if ( ! ContactPanda_Store::standalone() ) {
			return;
		}
		$notice = get_transient( 'contactpanda_database_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'contactpanda_database_notice_' . get_current_user_id() );
			echo '<div class="notice notice-' . ( 'success' === $notice['type'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
		}
		ContactPanda_Registration::render_duplicate_preview();
		self::render_merge_tool();
	}

	private static function render_csv_import( $forced_entity = '', $return_page = '' ) {
		if ( apply_filters( 'contactpanda_render_transfer_import', false, $forced_entity, $return_page ) ) return;
		$token = isset( $_GET['csv_token'] ) ? sanitize_key( wp_unslash( $_GET['csv_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque transient reference.
		$preview = $token ? get_transient( 'contactpanda_csv_' . get_current_user_id() . '_' . $token ) : false;
		echo '<section id="contactpanda-import" class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Import CSV data', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Import people or companies from a CSV file. The preview and field mapping are shown before anything is saved. Empty CSV values never erase existing data.', 'contactpanda' ) . '</p>';
		$report = get_transient( 'contactpanda_csv_report_' . get_current_user_id() );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		if ( is_array( $report ) ) { delete_transient( 'contactpanda_csv_report_' . get_current_user_id() ); echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Last CSV import', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d created, %2$d updated, %3$d skipped, %4$d errors.', 'contactpanda' ), $report['created'], $report['updated'], $report['skipped'], count( $report['errors'] ) ) ) . '</p>'; if ( $report['errors'] ) { echo '<details><summary>' . esc_html__( 'Show import errors', 'contactpanda' ) . '</summary><ul>'; foreach ( array_slice( $report['errors'], 0, 100 ) as $error ) echo '<li>' . esc_html( $error ) . '</li>'; echo '</ul></details>'; } echo '</div>'; }
		if ( ! $preview ) {
			echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_csv_preview"><input type="hidden" name="return_page" value="' . esc_attr( $return_page ) . '">';
			wp_nonce_field( 'contactpanda_csv_preview' );
			if ( $forced_entity ) echo '<input type="hidden" name="entity" value="' . esc_attr( $forced_entity ) . '">';
			else echo '<label><span>' . esc_html__( 'Data type', 'contactpanda' ) . '</span><select name="entity"><option value="contacts">' . esc_html__( 'People', 'contactpanda' ) . '</option><option value="companies">' . esc_html__( 'Companies', 'contactpanda' ) . '</option></select></label>';
			echo '<label><span>' . esc_html__( 'Duplicate handling', 'contactpanda' ) . '</span><select name="mode"><option value="fill">' . esc_html__( 'Fill missing values', 'contactpanda' ) . '</option><option value="skip">' . esc_html__( 'Skip existing records', 'contactpanda' ) . '</option><option value="update">' . esc_html__( 'Update with non-empty CSV values', 'contactpanda' ) . '</option></select></label>';
			echo '<label class="contactpanda-file-drop"><span class="dashicons dashicons-upload" aria-hidden="true"></span><strong>' . esc_html__( 'Drop a CSV file here', 'contactpanda' ) . '</strong><span>' . esc_html__( 'or click to select a file', 'contactpanda' ) . '</span><input type="file" name="csv_file" accept=".csv,text/csv,text/plain" required><small class="contactpanda-file-drop-name"></small></label><p class="contactpanda-file-drop-actions"><button class="button button-primary">' . esc_html__( 'Upload and preview', 'contactpanda' ) . '</button></p></form>';
			echo '<p class="description">' . esc_html__( 'In Groundhogg frontend mode, people are imported directly into Groundhogg and companies into Groundhogg Companies when available. Source files and external records are never changed.', 'contactpanda' ) . '</p></section>';
			return;
		}

		$fields = self::csv_fields( $preview['entity'] );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		echo '<div class="contactpanda-migration-report"><h3>' . esc_html__( 'Import preview', 'contactpanda' ) . '</h3><p>' . esc_html( sprintf( __( '%1$d data rows detected using “%2$s” as the delimiter. Map the columns before importing.', 'contactpanda' ), count( $preview['rows'] ), $preview['delimiter_label'] ) ) . '</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_csv_import"><input type="hidden" name="csv_token" value="' . esc_attr( $token ) . '">';
		wp_nonce_field( 'contactpanda_csv_import' );
		echo '<table class="widefat striped contactpanda-csv-mapping"><thead><tr><th>' . esc_html__( 'ContactPanda field', 'contactpanda' ) . '</th><th>' . esc_html__( 'CSV column', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $fields as $key => $label ) {
			$guess = self::guess_header( $key, $preview['headers'] );
			echo '<tr><th>' . esc_html( $label ) . ( in_array( $key, array( 'email', 'name' ), true ) ? ' *' : '' ) . '</th><td><select name="mapping[' . esc_attr( $key ) . ']" class="regular-text"><option value="">' . esc_html__( 'Do not import', 'contactpanda' ) . '</option>';
			foreach ( $preview['headers'] as $index => $header ) echo '<option value="' . esc_attr( $index ) . '"' . selected( $guess, $index, false ) . '>' . esc_html( $header ) . '</option>';
			echo '</select></td></tr>';
		}
		echo '</tbody></table><h3>' . esc_html__( 'First rows', 'contactpanda' ) . '</h3><div class="contactpanda-csv-preview"><table class="widefat striped"><thead><tr>';
		foreach ( $preview['headers'] as $header ) echo '<th>' . esc_html( $header ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( array_slice( $preview['rows'], 0, 10 ) as $row ) { echo '<tr>'; foreach ( $preview['headers'] as $index => $unused ) echo '<td>' . esc_html( $row[ $index ] ?? '' ) . '</td>'; echo '</tr>'; }
		echo '</tbody></table></div><p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'I checked the preview and field mapping.', 'contactpanda' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Start import', 'contactpanda' ) . '</button> <a class="button" href="' . esc_url( self::import_url( $preview['return_page'] ?? '' ) ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a></p></form></section>';
	}

	public static function handle_csv_preview() {
		self::require_admin( 'contactpanda_csv_preview' );
		$return_page = sanitize_key( wp_unslash( $_POST['return_page'] ?? '' ) );
		if ( ! in_array( $return_page, array( 'contactpanda', 'contactpanda-firmen' ), true ) ) $return_page = '';
		if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) || (int) $_FILES['csv_file']['size'] > 5 * MB_IN_BYTES ) { set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => 'error', 'message' => __( 'Select a CSV file no larger than 5 MB.', 'contactpanda' ) ), MINUTE_IN_SECONDS ); wp_safe_redirect( self::import_url( $return_page ) ); exit; }
		$entity = 'companies' === sanitize_key( wp_unslash( $_POST['entity'] ?? '' ) ) ? 'companies' : 'contacts';
		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'fill' ) );
		if ( ! in_array( $mode, array( 'skip', 'fill', 'update' ), true ) ) $mode = 'fill';
		$parsed = apply_filters( 'contactpanda_parse_transfer_upload', null, $_FILES['csv_file']['tmp_name'], sanitize_file_name( $_FILES['csv_file']['name'] ?? '' ) );
		if ( null === $parsed ) $parsed = self::parse_csv( $_FILES['csv_file']['tmp_name'] );
		if ( is_wp_error( $parsed ) ) { set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => 'error', 'message' => $parsed->get_error_message() ), MINUTE_IN_SECONDS ); wp_safe_redirect( self::import_url( $return_page ) ); exit; }
		$token = wp_generate_password( 12, false, false );
		$parsed['entity'] = $entity; $parsed['mode'] = $mode; $parsed['source_hash'] = hash_file( 'sha256', $_FILES['csv_file']['tmp_name'] ); $parsed['source_name'] = sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ?? '' ) ); $parsed['return_page'] = $return_page;
		set_transient( 'contactpanda_csv_' . get_current_user_id() . '_' . $token, $parsed, HOUR_IN_SECONDS );
		wp_safe_redirect( self::import_url( $return_page, array( 'csv_token' => $token ) ) ); exit;
	}

	private static function parse_csv( $path ) {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- protected upload stream.
		if ( ! $handle ) return new WP_Error( 'contactpanda_csv_open', __( 'The CSV file could not be read.', 'contactpanda' ) );
		$sample = (string) fgets( $handle ); rewind( $handle );
		$counts = array( ',' => substr_count( $sample, ',' ), ';' => substr_count( $sample, ';' ), "\t" => substr_count( $sample, "\t" ) ); arsort( $counts ); $delimiter = (string) key( $counts );
		$headers = fgetcsv( $handle, 0, $delimiter ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
		if ( ! is_array( $headers ) || count( $headers ) < 1 ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the validated temporary upload stream.
			return new WP_Error( 'contactpanda_csv_header', __( 'No CSV header row was detected.', 'contactpanda' ) );
		}
		$headers = array_map( static function ( $value ) { return sanitize_text_field( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $value ) ); }, $headers );
		$rows = array();
		while ( count( $rows ) < 5000 && false !== ( $row = fgetcsv( $handle, 0, $delimiter ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv
			if ( ! array_filter( $row, static function ( $value ) { return '' !== trim( (string) $value ); } ) ) continue;
			$rows[] = array_map( 'sanitize_text_field', $row );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( ! $rows ) return new WP_Error( 'contactpanda_csv_empty', __( 'The CSV file contains no data rows.', 'contactpanda' ) );
		return array( 'headers' => $headers, 'rows' => $rows, 'delimiter' => $delimiter, 'delimiter_label' => "\t" === $delimiter ? __( 'tab', 'contactpanda' ) : $delimiter );
	}

	public static function csv_fields( $entity ) {
		if ( 'companies' === $entity ) $fields = array( 'name' => __( 'Company name', 'contactpanda' ), 'legal_name' => __( 'Legal name', 'contactpanda' ), 'email' => __( 'Email', 'contactpanda' ), 'phone' => __( 'Phone', 'contactpanda' ), 'website' => __( 'Website', 'contactpanda' ), 'industry' => __( 'Industry', 'contactpanda' ), 'vat_id' => __( 'VAT ID', 'contactpanda' ), 'address_1' => __( 'Street address', 'contactpanda' ), 'address_2' => __( 'Address line 2', 'contactpanda' ), 'postcode' => __( 'Postal code', 'contactpanda' ), 'city' => __( 'City', 'contactpanda' ), 'country' => __( 'Country', 'contactpanda' ) );
		else $fields = array( 'email' => __( 'Email', 'contactpanda' ), 'first_name' => __( 'First name', 'contactpanda' ), 'last_name' => __( 'Last name', 'contactpanda' ), 'salutation' => __( 'Salutation', 'contactpanda' ), 'title_prefix' => __( 'Title before name', 'contactpanda' ), 'title_suffix' => __( 'Title after name', 'contactpanda' ), 'phone' => __( 'Phone', 'contactpanda' ), 'position' => __( 'Position', 'contactpanda' ), 'company' => __( 'Company', 'contactpanda' ), 'address_1' => __( 'Street address', 'contactpanda' ), 'address_2' => __( 'Address line 2', 'contactpanda' ), 'postcode' => __( 'Postal code', 'contactpanda' ), 'city' => __( 'City', 'contactpanda' ), 'state' => __( 'State', 'contactpanda' ), 'country' => __( 'Country', 'contactpanda' ), 'tags' => __( 'Tags (comma separated)', 'contactpanda' ), 'marketing' => __( 'Marketing consent', 'contactpanda' ) );
		return array_merge( $fields, ContactPanda_Custom_Fields::csv_fields( $entity ) );
	}

	public static function guess_header( $field, $headers ) {
		$aliases = array( 'email' => array( 'email', 'e-mail', 'mail' ), 'first_name' => array( 'first name', 'firstname', 'vorname' ), 'last_name' => array( 'last name', 'lastname', 'nachname' ), 'name' => array( 'company', 'company name', 'firma', 'firmenname', 'name' ), 'company' => array( 'company', 'firma' ), 'phone' => array( 'phone', 'telephone', 'telefon' ), 'postcode' => array( 'postcode', 'postal code', 'zip', 'plz' ), 'city' => array( 'city', 'ort', 'stadt' ), 'country' => array( 'country', 'land' ), 'address_1' => array( 'street', 'address', 'adresse', 'strasse', 'straße' ), 'vat_id' => array( 'vat', 'vat id', 'uid', 'uid nummer' ), 'tags' => array( 'tags', 'tag' ), 'marketing' => array( 'marketing', 'newsletter', 'consent', 'zustimmung' ) );
		if ( 0 === strpos( $field, 'custom__' ) ) {
			$key = substr( $field, 8 );
			foreach ( ContactPanda_Custom_Fields::definitions() as $definition ) if ( $key === $definition['key'] ) $aliases[ $field ] = array( strtolower( remove_accents( $definition['label'] ) ), str_replace( '_', ' ', $key ), $key );
		}
		$needles = $aliases[ $field ] ?? array( str_replace( '_', ' ', $field ) );
		foreach ( $headers as $index => $header ) { $normalized = strtolower( trim( remove_accents( $header ) ) ); if ( in_array( $normalized, $needles, true ) ) return $index; }
		return '';
	}

	public static function handle_csv_import() {
		self::require_admin( 'contactpanda_csv_import' );
		$token = sanitize_key( wp_unslash( $_POST['csv_token'] ?? '' ) );
		$data = get_transient( 'contactpanda_csv_' . get_current_user_id() . '_' . $token );
		$return_page = is_array( $data ) ? sanitize_key( $data['return_page'] ?? '' ) : '';
		if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) self::redirect_import_notice( 'error', __( 'Confirm the field mapping before importing.', 'contactpanda' ), $return_page, array( 'csv_token' => $token ) );
		if ( ! is_array( $data ) ) self::redirect_import_notice( 'error', __( 'The import preview expired. Upload the CSV file again.', 'contactpanda' ), $return_page );
		if ( isset( $_POST['mode'] ) && in_array( $_POST['mode'], array( 'skip', 'fill', 'update' ), true ) ) $data['mode'] = sanitize_key( $_POST['mode'] );
		foreach ( (array) ( $_POST['mapping'] ?? array() ) as $field => $index ) {
			if ( '' !== (string) $index && ( ! ctype_digit( (string) $index ) || ! array_key_exists( (int) $index, $data['headers'] ) ) ) self::redirect_import_notice( 'error', __( 'Invalid field mapping.', 'contactpanda' ), $return_page, array( 'csv_token' => $token ) );
		}
		$mapping = isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] ) ? array_map( 'absint', wp_unslash( $_POST['mapping'] ) ) : array();
		$required = 'companies' === $data['entity'] ? 'name' : 'email';
		if ( ! isset( $_POST['mapping'][ $required ] ) || '' === (string) $_POST['mapping'][ $required ] ) self::redirect_import_notice( 'error', __( 'Map the required identifying field before importing.', 'contactpanda' ), $return_page, array( 'csv_token' => $token ) );
		$report = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => array() );
		$run_id = ContactPanda_Store::begin_import_run( 'csv', $data['entity'], $data['source_name'] ?? '', $data['source_hash'] ?? '' );
		foreach ( $data['rows'] as $number => $row ) {
			$record = array(); foreach ( self::csv_fields( $data['entity'] ) as $field => $label ) if ( isset( $_POST['mapping'][ $field ] ) && '' !== (string) $_POST['mapping'][ $field ] ) $record[ $field ] = sanitize_text_field( $row[ $mapping[ $field ] ] ?? '' );
			$before_record = 'companies' === $data['entity'] ? ContactPanda_Store::company_by_name( $record['name'] ?? '' ) : ContactPanda_Store::contact_by_email( $record['email'] ?? '' );
			$before_id = is_array( $before_record ) ? absint( $before_record['id'] ?? 0 ) : absint( $before_record );
			$before = $before_id ? ContactPanda_Store::snapshot_for_import( $data['entity'], $before_id ) : array();
			$result = self::import_csv_record( $data['entity'], $record, $data['mode'], $data['source_hash'] . ':' . ( $number + 2 ) );
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			if ( is_wp_error( $result ) ) { $report['errors'][] = sprintf( __( 'Row %1$d: %2$s', 'contactpanda' ), $number + 2, $result->get_error_message() ); continue; }
			$report[ $result ]++;
			$after_record = 'companies' === $data['entity'] ? ContactPanda_Store::company_by_name( $record['name'] ?? '' ) : ContactPanda_Store::contact_by_email( $record['email'] ?? '' );
			$record_id = is_array( $after_record ) ? absint( $after_record['id'] ?? 0 ) : absint( $after_record );
			if ( $run_id && $record_id ) ContactPanda_Store::log_import_item( $run_id, $data['entity'], $record_id, $result, $before, ContactPanda_Store::snapshot_for_import( $data['entity'], $record_id ), $data['source_hash'] . ':' . ( $number + 2 ) );
		}
		if ( $run_id ) { ContactPanda_Store::finish_import_run( $run_id, $report ); $report['run_id'] = $run_id; }
		delete_transient( 'contactpanda_csv_' . get_current_user_id() . '_' . $token );
		set_transient( 'contactpanda_csv_report_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		self::redirect_import_notice( 'success', sprintf( __( 'CSV import completed: %1$d created, %2$d updated, %3$d skipped, %4$d errors.', 'contactpanda' ), $report['created'], $report['updated'], $report['skipped'], count( $report['errors'] ) ), $return_page );
	}

	private static function import_csv_record( $entity, $record, $mode, $source_id ) {
		if ( ContactPanda_Store::standalone() && ContactPanda_Store::import_blocked( $entity, 'companies' === $entity ? ( $record['name'] ?? '' ) : ( $record['email'] ?? '' ) ) ) return 'skipped';
		if ( 'companies' === $entity ) {
			if ( ! ContactPanda_Store::standalone() ) return ContactPanda_Plugin::extension_import_company( $record, $mode, $source_id );
			$name = sanitize_text_field( $record['name'] ?? '' ); if ( '' === $name ) return new WP_Error( 'contactpanda_csv_name', __( 'Company name is missing.', 'contactpanda' ) );
			$deleted_id = ContactPanda_Store::company_by_name( $name, true );
			if ( $deleted_id && ! ContactPanda_Store::company( $deleted_id ) ) return 'skipped';
			$existing_id = ContactPanda_Store::company_by_name( $name );
			$existing = $existing_id ? ContactPanda_Store::company( $existing_id ) : false;
			$result = ContactPanda_Store::import_company_row( $record, $mode, $source_id );
			if ( ! is_wp_error( $result ) && 'skipped' !== $result ) {
				$id = ContactPanda_Store::company_by_name( $name );
				$meta = ContactPanda_Custom_Fields::custom_meta_from_record( $record, 'companies', $existing, $mode );
				if ( $id && $meta ) ContactPanda_Plugin::extension_update_custom_meta( 'companies', $id, $meta );
			}
			return $result;
		}
		$email = strtolower( sanitize_email( $record['email'] ?? '' ) ); if ( ! is_email( $email ) ) return new WP_Error( 'contactpanda_csv_email', __( 'A valid email address is required.', 'contactpanda' ) );
		if ( ! ContactPanda_Store::standalone() ) return ContactPanda_Plugin::extension_import_contact( $record, $mode, $source_id );
		$including_deleted = ContactPanda_Store::contact_by_email( $email, true );
		if ( $including_deleted && ! empty( $including_deleted['deleted_at'] ) ) return 'skipped';
		$current = ContactPanda_Store::contact_by_email( $email ); if ( $current && 'skip' === $mode ) return 'skipped';
		$payload = array( 'email' => $email, 'source' => 'csv', 'meta' => array_merge( array( 'csv_source_id' => $source_id ), ContactPanda_Custom_Fields::custom_meta_from_record( $record, 'contacts', $current, $mode ) ) );
		$map = array( 'first_name', 'last_name', 'position' ); foreach ( $map as $field ) if ( ! $current || ( ! empty( $record[ $field ] ) && ( 'update' === $mode || empty( $current[ $field ] ) ) ) ) $payload[ $field ] = $record[ $field ] ?? '';
		foreach ( array( 'salutation' => 'anrede', 'title_prefix' => 'titel_vorangestellt', 'title_suffix' => 'titel_nachgestellt', 'phone' => 'primary_phone' ) as $input => $field ) if ( ! empty( $record[ $input ] ) && ( 'update' === $mode || empty( $current['meta'][ $field ] ) ) ) $payload[ $field ] = $record[ $input ];
		$id = $current ? absint( $current['id'] ) : ContactPanda_Store::create_contact( $payload ); if ( is_wp_error( $id ) || ! $id ) return new WP_Error( 'contactpanda_csv_contact', __( 'Person could not be saved.', 'contactpanda' ) );
		if ( $current ) ContactPanda_Store::update_contact( $id, $payload );
		$address = array(); foreach ( array( 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' ) as $field ) $address[ $field ] = isset( $record[ $field ] ) && '' !== $record[ $field ] && ( 'update' === $mode || empty( $current['meta'][ $field ] ) ) ? $record[ $field ] : ( $current['meta'][ $field ] ?? '' ); ContactPanda_Store::update_contact_address( $id, $address );
		if ( ! empty( $record['company'] ) ) ContactPanda_Store::link_contact_company_name( $email, $record['company'] );
		if ( ! empty( $record['tags'] ) ) foreach ( preg_split( '/[,;]+/', $record['tags'] ) as $tag ) { $tag_id = ContactPanda_Store::create_tag( trim( $tag ) ); if ( $tag_id ) ContactPanda_Store::assign_tag( $id, $tag_id ); }
		$current_status = sanitize_key( (string) ( $current['optin_status'] ?? '' ) );
		$protected_statuses = array( 'unsubscribed', 'hard_bounce', 'bounced', 'spam', 'complained', 'blocked' );
		if ( ! empty( $record['marketing'] ) && in_array( strtolower( $record['marketing'] ), array( '1', 'yes', 'ja', 'true', 'confirmed' ), true ) && ! in_array( $current_status, $protected_statuses, true ) && 'confirmed' !== $current_status ) ContactPanda_Store::record_consent( $id, 'granted', 'csv_import', current_time( 'Y-m-d' ), __( 'Imported from a confirmed CSV field.', 'contactpanda' ) );
		return $current ? 'updated' : 'created';
	}

	private static function render_merge_tool() {
		if ( ! ContactPanda_Store::standalone() ) { echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Merge duplicates', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Record merging is available in standalone ContactPanda mode. Groundhogg records must be merged in Groundhogg so its source identifiers remain authoritative.', 'contactpanda' ) . '</p></section>'; return; }
		$token = isset( $_GET['merge_token'] ) ? sanitize_key( wp_unslash( $_GET['merge_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque transient reference.
		$state = $token ? get_transient( 'contactpanda_merge_' . get_current_user_id() . '_' . $token ) : false;
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Merge duplicates', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Select two or more fragments, choose the primary record, and resolve conflicting fields. Related tags, consents, notes, and company links are retained; source fragments are moved to Trash.', 'contactpanda' ) . '</p>';
		if ( $state ) { self::render_merge_preview( $token, $state ); echo '</section>'; return; }
		$entity = isset( $_GET['merge_entity'] ) && 'companies' === $_GET['merge_entity'] ? 'companies' : 'contacts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['merge_search'] ) ? sanitize_text_field( wp_unslash( $_GET['merge_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<form method="get" class="contactpanda-search"><input type="hidden" name="page" value="contactpanda-settings"><input type="hidden" name="tab" value="other"><input type="hidden" name="other_section" value="duplicates"><select name="merge_entity"><option value="contacts"' . selected( $entity, 'contacts', false ) . '>' . esc_html__( 'People', 'contactpanda' ) . '</option><option value="companies"' . selected( $entity, 'companies', false ) . '>' . esc_html__( 'Companies', 'contactpanda' ) . '</option></select><input type="search" name="merge_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search records', 'contactpanda' ) . '"><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form>';
		if ( '' === $search ) { echo '<p class="description">' . esc_html__( 'Enter a name, company, or email fragment to find records to merge.', 'contactpanda' ) . '</p></section>'; return; }
		$records = 'contacts' === $entity ? ContactPanda_Store::search_contacts( $search, 50, 0 )['contacts'] : array_values( array_filter( ContactPanda_Store::companies(), static function ( $company ) use ( $search ) { return false !== stripos( (string) $company['name'], $search ); } ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_merge_preview"><input type="hidden" name="entity" value="' . esc_attr( $entity ) . '">'; wp_nonce_field( 'contactpanda_merge_preview' );
		echo '<table class="widefat striped"><thead><tr><th class="check-column"></th><th>' . esc_html__( 'Record', 'contactpanda' ) . '</th><th>' . esc_html__( 'Details', 'contactpanda' ) . '</th></tr></thead><tbody>';
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		foreach ( $records as $record ) { $id = absint( $record['id'] ); $name = 'contacts' === $entity ? trim( $record['first_name'] . ' ' . $record['last_name'] ) : $record['name']; $details = 'contacts' === $entity ? $record['email'] : trim( ( $record['city'] ?? '' ) . ' ' . ( $record['vat_id'] ?? '' ) ); echo '<tr><td><input type="checkbox" name="ids[]" value="' . esc_attr( $id ) . '"></td><td>' . esc_html( $name ?: sprintf( __( 'Record #%d', 'contactpanda' ), $id ) ) . '</td><td>' . esc_html( $details ) . '</td></tr>'; }
		if ( ! $records ) echo '<tr><td colspan="3">' . esc_html__( 'No matching records found.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Compare selected records', 'contactpanda' ) . '</button></p></form></section>';
	}

	public static function handle_merge_preview() {
		self::require_admin( 'contactpanda_merge_preview' );
		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $_POST['ids'] ) ) ) ) : array();
		if ( count( $ids ) < 2 || count( $ids ) > 20 ) self::redirect_duplicate_notice( 'error', __( 'Select between two and twenty records to merge.', 'contactpanda' ) );
		$token = wp_generate_password( 12, false, false ); $state = array( 'entity' => 'companies' === ( $_POST['entity'] ?? '' ) ? 'companies' : 'contacts', 'ids' => $ids );
		set_transient( 'contactpanda_merge_' . get_current_user_id() . '_' . $token, $state, HOUR_IN_SECONDS ); wp_safe_redirect( self::duplicates_url( array( 'merge_token' => $token ) ) ); exit;
	}

	public static function handle_merge_group() {
		self::require_admin( 'contactpanda_merge_group' );
		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $_POST['ids'] ) ) ) ) : array();
		if ( count( $ids ) < 2 || count( $ids ) > 20 ) self::redirect_duplicate_notice( 'error', __( 'Select between two and twenty records to merge.', 'contactpanda' ) );
		$token = wp_generate_password( 12, false, false );
		set_transient( 'contactpanda_merge_' . get_current_user_id() . '_' . $token, array( 'entity' => 'contacts', 'ids' => $ids ), HOUR_IN_SECONDS );
		wp_safe_redirect( self::duplicates_url( array( 'merge_token' => $token ) ) ); exit;
	}

	private static function render_merge_preview( $token, $state ) {
		$records = array(); foreach ( $state['ids'] as $id ) { $record = 'contacts' === $state['entity'] ? ContactPanda_Store::contact( $id ) : ContactPanda_Store::company( $id ); if ( $record ) $records[ $id ] = $record; }
		$fields = 'contacts' === $state['entity'] ? array( 'first_name' => __( 'First name', 'contactpanda' ), 'last_name' => __( 'Last name', 'contactpanda' ), 'position' => __( 'Position', 'contactpanda' ), 'wp_user_id' => __( 'WordPress user', 'contactpanda' ), 'meta__anrede' => __( 'Salutation', 'contactpanda' ), 'meta__titel_vorangestellt' => __( 'Title before name', 'contactpanda' ), 'meta__titel_nachgestellt' => __( 'Title after name', 'contactpanda' ), 'meta__berufsbezeichnung' => __( 'Job title', 'contactpanda' ), 'meta__address_1' => __( 'Street address', 'contactpanda' ), 'meta__address_2' => __( 'Address line 2', 'contactpanda' ), 'meta__postcode' => __( 'Postal code', 'contactpanda' ), 'meta__city' => __( 'City', 'contactpanda' ), 'meta__state' => __( 'State', 'contactpanda' ), 'meta__country' => __( 'Country', 'contactpanda' ) ) : array( 'name' => __( 'Company name', 'contactpanda' ), 'legal_name' => __( 'Legal name', 'contactpanda' ), 'industry' => __( 'Industry', 'contactpanda' ), 'website' => __( 'Website', 'contactpanda' ), 'email' => __( 'Email', 'contactpanda' ), 'phone_number' => __( 'Phone', 'contactpanda' ), 'vat_id' => __( 'VAT ID', 'contactpanda' ), 'street' => __( 'Street address', 'contactpanda' ), 'address_2' => __( 'Address line 2', 'contactpanda' ), 'postcode' => __( 'Postal code', 'contactpanda' ), 'city' => __( 'City', 'contactpanda' ), 'country' => __( 'Country', 'contactpanda' ) );
		foreach ( ContactPanda_Custom_Fields::definitions( $state['entity'] ) as $definition ) $fields[ 'meta__' . ContactPanda_Custom_Fields::meta_key( $definition['key'] ) ] = $definition['label'];
		echo '<div class="contactpanda-modal contactpanda-merge-modal" role="dialog" aria-modal="true" aria-labelledby="contactpanda-merge-title"><div class="contactpanda-modal-panel contactpanda-merge-panel"><div class="contactpanda-merge-header"><h2 id="contactpanda-merge-title">' . esc_html__( 'Merge duplicates', 'contactpanda' ) . '</h2><a class="button-link" href="' . esc_url( self::duplicates_url() ) . '" aria-label="' . esc_attr__( 'Close', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></a></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_merge_commit"><input type="hidden" name="merge_token" value="' . esc_attr( $token ) . '">'; wp_nonce_field( 'contactpanda_merge_commit' );
		echo '<h3>' . esc_html__( 'Choose records and the primary record', 'contactpanda' ) . '</h3><p class="description">' . esc_html__( 'Keep at least two records selected. Unselected candidates remain unchanged.', 'contactpanda' ) . '</p><div class="contactpanda-merge-primary">';
		foreach ( $records as $id => $record ) { $name = 'contacts' === $state['entity'] ? trim( $record['first_name'] . ' ' . $record['last_name'] ) : $record['name']; echo '<label data-contactpanda-merge-record-card="' . esc_attr( $id ) . '"><input type="checkbox" class="contactpanda-merge-record-toggle" name="merge_ids[]" value="' . esc_attr( $id ) . '" checked> <span>' . esc_html( ( $name ?: __( 'Unnamed record', 'contactpanda' ) ) . ' #' . $id ) . '</span> <input type="radio" name="primary_id" value="' . esc_attr( $id ) . '" data-contactpanda-primary-record="' . esc_attr( $id ) . '"' . checked( $id, array_key_first( $records ), false ) . '> <small>' . esc_html__( 'Primary', 'contactpanda' ) . '</small></label>'; }
		echo '</div><h3>' . esc_html__( 'Resolve and complete field values', 'contactpanda' ) . '</h3><p class="description">' . esc_html__( 'Choose an existing value or edit the final value directly before merging.', 'contactpanda' ) . '</p><table class="widefat striped contactpanda-merge-table"><thead><tr><th>' . esc_html__( 'Field', 'contactpanda' ) . '</th><th>' . esc_html__( 'Available values', 'contactpanda' ) . '</th><th>' . esc_html__( 'Final value', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $fields as $field => $label ) {
			$first = true;
			$default = '';
			echo '<tr><th>' . esc_html( $label ) . '</th><td>';
			foreach ( $records as $id => $record ) {
				$value = 0 === strpos( $field, 'meta__' ) ? ( $record['meta'][ substr( $field, 6 ) ] ?? '' ) : ( $record[ $field ] ?? '' );
				if ( '' === trim( (string) $value ) ) continue;
				if ( $first ) $default = (string) $value;
				echo '<label class="contactpanda-merge-choice" data-contactpanda-merge-source="' . esc_attr( $id ) . '"><input type="radio" name="choices[' . esc_attr( $field ) . ']" value="' . esc_attr( $id ) . '" data-contactpanda-merge-field="' . esc_attr( $field ) . '" data-contactpanda-merge-value="' . esc_attr( (string) $value ) . '"' . checked( $first, true, false ) . '> ' . esc_html( (string) $value ) . ' <small>#' . esc_html( $id ) . '</small></label>';
				$first = false;
			}
			if ( $first ) echo '&mdash;';
			echo '</td><td>';
			if ( 'meta__anrede' === $field ) {
				echo '<select class="contactpanda-merge-resolved" data-contactpanda-merge-target="' . esc_attr( $field ) . '" name="resolved[' . esc_attr( $field ) . ']"><option value="">' . esc_html__( 'Neutral / not specified', 'contactpanda' ) . '</option><option value="mr"' . selected( $default, 'mr', false ) . '>' . esc_html__( 'Mr', 'contactpanda' ) . '</option><option value="ms"' . selected( $default, 'ms', false ) . '>' . esc_html__( 'Ms', 'contactpanda' ) . '</option><option value="family"' . selected( $default, 'family', false ) . '>' . esc_html__( 'Family', 'contactpanda' ) . '</option><option value="neutral"' . selected( $default, 'neutral', false ) . '>' . esc_html__( 'Neutral', 'contactpanda' ) . '</option></select>';
			} else {
				$type = 'email' === $field ? 'email' : ( 'wp_user_id' === $field ? 'number' : 'text' );
				echo '<input class="regular-text contactpanda-merge-resolved" data-contactpanda-merge-target="' . esc_attr( $field ) . '" type="' . esc_attr( $type ) . '" name="resolved[' . esc_attr( $field ) . ']" value="' . esc_attr( $default ) . '"' . ( 'number' === $type ? ' min="0"' : '' ) . '>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		if ( 'contacts' === $state['entity'] ) self::render_merge_channels( $records );
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'After confirmation, source fragments are moved to the ContactPanda Trash. External systems, orders, invoices, and source records are not changed.', 'contactpanda' ) . '</p></div><p><label><input type="checkbox" name="confirmed" value="1" required> ' . esc_html__( 'I reviewed the primary record and every conflicting value.', 'contactpanda' ) . '</label></p><p><button class="button button-primary">' . esc_html__( 'Merge records', 'contactpanda' ) . '</button> <a class="button" href="' . esc_url( self::duplicates_url() ) . '">' . esc_html__( 'Cancel', 'contactpanda' ) . '</a></p></form></div></div>';
	}

	private static function render_merge_channels( $records ) {
		$channels = array( 'email' => array(), 'phone' => array() );
		foreach ( $records as $record_id => $record ) {
			$meta = is_array( $record['meta'] ?? null ) ? $record['meta'] : array();
			if ( ! empty( $record['email'] ) ) $channels['email'][] = array( 'value' => sanitize_email( $record['email'] ), 'source' => absint( $record_id ) );
			if ( ! empty( $meta['primary_phone'] ) ) $channels['phone'][] = array( 'value' => sanitize_text_field( $meta['primary_phone'] ), 'source' => absint( $record_id ) );
			foreach ( is_array( $meta['contactpanda_contact_channels'] ?? null ) ? $meta['contactpanda_contact_channels'] : array() as $channel ) {
				$type = sanitize_key( $channel['type'] ?? '' );
				if ( isset( $channels[ $type ] ) && ! empty( $channel['value'] ) ) $channels[ $type ][] = array( 'value' => sanitize_text_field( $channel['value'] ), 'source' => absint( $record_id ) );
			}
		}
		echo '<input type="hidden" name="channel_selection_submitted" value="1"><section class="contactpanda-merge-channels"><h3>' . esc_html__( 'Keep email addresses and phone numbers', 'contactpanda' ) . '</h3><p class="description">' . esc_html__( 'Select every value that should remain and choose exactly one primary value in each group.', 'contactpanda' ) . '</p>';
		foreach ( $channels as $type => $values ) {
			$unique = array();
			foreach ( $values as $entry ) {
				$value = $entry['value'];
				$key = 'email' === $type ? strtolower( sanitize_email( $value ) ) : preg_replace( '/\D+/', '', $value );
				if ( ! $key ) continue;
				if ( ! isset( $unique[ $key ] ) ) $unique[ $key ] = array( 'value' => $value, 'sources' => array() );
				$unique[ $key ]['sources'][] = absint( $entry['source'] );
			}
			if ( ! $unique ) continue;
			echo '<fieldset><legend>' . esc_html( 'email' === $type ? __( 'Email addresses', 'contactpanda' ) : __( 'Phone numbers', 'contactpanda' ) ) . '</legend>';
			$first = true;
			foreach ( $unique as $entry ) {
				$value = $entry['value'];
				echo '<label data-contactpanda-merge-channel-sources="' . esc_attr( implode( ',', array_unique( $entry['sources'] ) ) ) . '"><input type="checkbox" name="channels[' . esc_attr( $type ) . '][]" value="' . esc_attr( $value ) . '" checked> <span>' . esc_html( $value ) . '</span> <input type="radio" name="primary_channel[' . esc_attr( $type ) . ']" value="' . esc_attr( $value ) . '"' . checked( $first, true, false ) . '> <small>' . esc_html__( 'Primary', 'contactpanda' ) . '</small></label>';
				$first = false;
			}
			echo '</fieldset>';
		}
		echo '</section>';
	}

	public static function handle_merge_commit() {
		self::require_admin( 'contactpanda_merge_commit' );
		if ( '1' !== ( $_POST['confirmed'] ?? '' ) ) self::redirect_duplicate_notice( 'error', __( 'Confirm the reviewed merge before continuing.', 'contactpanda' ) );
		$token = sanitize_key( wp_unslash( $_POST['merge_token'] ?? '' ) ); $state = get_transient( 'contactpanda_merge_' . get_current_user_id() . '_' . $token );
		if ( ! is_array( $state ) ) self::redirect_duplicate_notice( 'error', __( 'The merge preview expired. Select the records again.', 'contactpanda' ) );
		$selected_ids = isset( $_POST['merge_ids'] ) && is_array( $_POST['merge_ids'] ) ? array_values( array_intersect( array_map( 'absint', wp_unslash( $_POST['merge_ids'] ) ), array_map( 'absint', $state['ids'] ) ) ) : array();
		if ( count( $selected_ids ) < 2 ) self::redirect_duplicate_notice( 'error', __( 'Keep at least two records selected for the merge.', 'contactpanda' ), array( 'merge_token' => $token ) );
		$primary_id = absint( $_POST['primary_id'] ?? 0 );
		if ( ! in_array( $primary_id, $selected_ids, true ) ) self::redirect_duplicate_notice( 'error', __( 'The primary record must be one of the selected records.', 'contactpanda' ), array( 'merge_token' => $token ) );
		$choices = isset( $_POST['choices'] ) && is_array( $_POST['choices'] ) ? array_map( 'absint', wp_unslash( $_POST['choices'] ) ) : array();
		$resolved = isset( $_POST['resolved'] ) && is_array( $_POST['resolved'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['resolved'] ) ) : array();
		$channels = array();
		foreach ( array( 'email', 'phone' ) as $type ) $channels[ $type ] = isset( $_POST['channels'][ $type ] ) && is_array( $_POST['channels'][ $type ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['channels'][ $type ] ) ) : array();
		if ( 'contacts' === $state['entity'] ) {
			$allowed_channels = array( 'email' => array(), 'phone' => array() );
			foreach ( $selected_ids as $selected_id ) {
				$record = ContactPanda_Store::contact( $selected_id );
				if ( ! $record ) continue;
				$meta = is_array( $record['meta'] ?? null ) ? $record['meta'] : array();
				if ( ! empty( $record['email'] ) ) $allowed_channels['email'][] = strtolower( sanitize_email( $record['email'] ) );
				if ( ! empty( $meta['primary_phone'] ) ) $allowed_channels['phone'][] = preg_replace( '/\D+/', '', (string) $meta['primary_phone'] );
				foreach ( is_array( $meta['contactpanda_contact_channels'] ?? null ) ? $meta['contactpanda_contact_channels'] : array() as $channel ) {
					$type = sanitize_key( $channel['type'] ?? '' );
					if ( ! isset( $allowed_channels[ $type ] ) || empty( $channel['value'] ) ) continue;
					$allowed_channels[ $type ][] = 'email' === $type ? strtolower( sanitize_email( $channel['value'] ) ) : preg_replace( '/\D+/', '', (string) $channel['value'] );
				}
			}
			foreach ( array( 'email', 'phone' ) as $type ) {
				$channels[ $type ] = array_values( array_filter( $channels[ $type ], function ( $value ) use ( $type, $allowed_channels ) {
					$normalized = 'email' === $type ? strtolower( sanitize_email( $value ) ) : preg_replace( '/\D+/', '', (string) $value );
					return $normalized && in_array( $normalized, $allowed_channels[ $type ], true );
				} ) );
			}
		}
		$channels['_submitted'] = ! empty( $_POST['channel_selection_submitted'] );
		$primary_channels = isset( $_POST['primary_channel'] ) && is_array( $_POST['primary_channel'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['primary_channel'] ) ) : array();
		foreach ( array( 'email', 'phone' ) as $type ) {
			if ( empty( $channels[ $type ] ) ) {
				$primary_channels[ $type ] = '';
			} elseif ( empty( $primary_channels[ $type ] ) || ! in_array( $primary_channels[ $type ], $channels[ $type ], true ) ) {
				$primary_channels[ $type ] = reset( $channels[ $type ] );
			}
		}
		$result = ContactPanda_Store::merge_records( $state['entity'], $selected_ids, $primary_id, $choices, $resolved, $channels, $primary_channels );
		if ( is_wp_error( $result ) ) self::redirect_duplicate_notice( 'error', $result->get_error_message(), array( 'merge_token' => $token ) );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		delete_transient( 'contactpanda_merge_' . get_current_user_id() . '_' . $token ); self::redirect_duplicate_notice( 'success', sprintf( __( '%d duplicate fragments were merged and moved to Trash.', 'contactpanda' ), $result['merged'] ) );
	}

	public static function render_central_library() {
		$settings = wp_parse_args( (array) get_option( self::CENTRAL_OPTION, array() ), array( 'enabled' => 0, 'auto' => 0, 'source_url' => '', 'version' => '', 'updated_at' => '', 'last_success_at' => '', 'error' => '' ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Optional central salutation library', 'contactpanda' ) . '</h2><p>' . esc_html__( 'You can optionally supplement your local library with a centrally maintained name list. Local and manually confirmed assignments always take precedence. No contact or customer data is uploaded.', 'contactpanda' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-central-library-form"><input type="hidden" name="action" value="contactpanda_central_salutations">'; wp_nonce_field( 'contactpanda_central_salutations' );
		echo '<p><label class="contactpanda-switch"><input type="checkbox" name="enabled" value="1"' . checked( ! empty( $settings['enabled'] ), true, false ) . '> <strong>' . esc_html__( 'Use central library', 'contactpanda' ) . '</strong></label></p>';
		echo '<div class="contactpanda-central-library-options"><p class="description">' . esc_html__( 'By enabling the central library you accept that name-based salutations can be incomplete or incorrect and remain responsible for reviewing them. Disabling synchronization keeps names already added to your local library.', 'contactpanda' ) . '</p>';
		if ( empty( $settings['enabled'] ) ) echo '<p><label><input type="checkbox" name="confirmed" value="1"> ' . esc_html__( 'I understand the limitations of name-based salutation assignments.', 'contactpanda' ) . '</label></p>';
		echo '<p><label><input type="checkbox" name="auto" value="1"' . checked( ! empty( $settings['auto'] ), true, false ) . '> ' . esc_html__( 'Check weekly for library updates', 'contactpanda' ) . '</label></p></div>';
		echo '<p class="contactpanda-central-library-actions"><button class="button button-primary" name="save" value="1">' . esc_html__( 'Save library settings', 'contactpanda' ) . '</button>';
		if ( ! empty( $settings['enabled'] ) ) echo ' <button class="button" name="sync" value="1">' . esc_html__( 'Update central library now', 'contactpanda' ) . '</button>';
		echo '</p></form>';
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		if ( $settings['version'] || $settings['updated_at'] ) echo '<p class="description">' . esc_html( sprintf( __( 'Installed central version: %1$s. Last check: %2$s.', 'contactpanda' ), $settings['version'] ?: '—', $settings['updated_at'] ?: '—' ) ) . '</p>';
		if ( $settings['error'] ) echo '<div class="notice notice-warning inline"><p>' . esc_html( $settings['error'] ) . '</p></div>';
		echo '</section>';
	}

	public static function handle_central_settings() {
		self::require_admin( 'contactpanda_central_salutations' );
		$enabled = ! empty( $_POST['enabled'] );
		$settings = (array) get_option( self::CENTRAL_OPTION, array() );
		$was_enabled = ! empty( $settings['enabled'] );
		if ( $enabled && ! $was_enabled && empty( $_POST['confirmed'] ) ) {
			set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => 'error', 'message' => __( 'Confirm the limitations before enabling the central library.', 'contactpanda' ) ), MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=salutations' ) ); exit;
		}
		$settings['enabled'] = $enabled ? 1 : 0; $settings['auto'] = $enabled && ! empty( $_POST['auto'] ) ? 1 : 0; update_option( self::CENTRAL_OPTION, $settings, false );
		if ( $settings['auto'] && ! wp_next_scheduled( 'contactpanda_central_salutation_sync' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'contactpanda_weekly', 'contactpanda_central_salutation_sync' );
		if ( ! $settings['auto'] ) wp_clear_scheduled_hook( 'contactpanda_central_salutation_sync' );
		if ( $enabled && ! empty( $_POST['sync'] ) ) {
			$result = self::sync_central_library();
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => is_wp_error( $result ) ? 'error' : 'success', 'message' => is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( 'Central library updated: %d new names added.', 'contactpanda' ), $result ) ), MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=salutations' ) ); exit;
	}

	/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
	public static function handle_central_sync() { self::require_admin( 'contactpanda_sync_central_salutations' ); $result = self::sync_central_library(); set_transient( 'contactpanda_database_notice_' . get_current_user_id(), array( 'type' => is_wp_error( $result ) ? 'error' : 'success', 'message' => is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( 'Central library updated: %d new names added.', 'contactpanda' ), $result ) ), MINUTE_IN_SECONDS ); wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=salutations' ) ); exit; }
	public static function scheduled_central_sync() { $settings = (array) get_option( self::CENTRAL_OPTION, array() ); if ( ! empty( $settings['enabled'] ) && ! empty( $settings['auto'] ) ) self::sync_central_library(); }

	private static function sync_central_library() {
		$settings = wp_parse_args( (array) get_option( self::CENTRAL_OPTION, array() ), array( 'enabled' => 0, 'source_url' => '', 'version' => '', 'updated_at' => '', 'last_success_at' => '', 'error' => '' ) );
		if ( empty( $settings['enabled'] ) ) return new WP_Error( 'contactpanda_central_disabled', __( 'Enable the central library before updating it.', 'contactpanda' ) );
		// The public library contains no customer data and may be replaced by a filtered URL.
		// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- This documented, administrator-enabled external service supplies public salutation data and receives no site or customer data.
		$url = apply_filters( 'contactpanda_central_salutation_library_url', 'https://raw.githubusercontent.com/kultur3gmbh/contactpanda-salutations/main/salutations.json' );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'ContactPanda/' . CONTACTPANDA_VERSION ) ); // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Optional, administrator-enabled synchronization from the documented public salutation library.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$reason = is_wp_error( $response ) ? sanitize_text_field( $response->get_error_message() ) : sprintf( 'HTTP %d', wp_remote_retrieve_response_code( $response ) );
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$settings['error'] = sprintf( __( 'The central library could not be downloaded (%s). Your local library was not changed.', 'contactpanda' ), $reason );
			$settings['updated_at'] = current_time( 'mysql' );
			update_option( self::CENTRAL_OPTION, $settings, false );
			return new WP_Error( 'contactpanda_central_download', $settings['error'] );
		}
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		$entries = isset( $json['entries'] ) && is_array( $json['entries'] ) ? $json['entries'] : array();
		if ( ! $entries ) {
			$settings['error'] = __( 'The central library returned no valid entries. Your local library was not changed.', 'contactpanda' );
			$settings['updated_at'] = current_time( 'mysql' );
			update_option( self::CENTRAL_OPTION, $settings, false );
			return new WP_Error( 'contactpanda_central_invalid', $settings['error'] );
		}
		$added = 0;
		foreach ( $entries as $entry ) { if ( ! is_array( $entry ) ) continue; $name = sanitize_text_field( $entry['first_name'] ?? '' ); $salutation = sanitize_key( $entry['salutation'] ?? '' ); $language = ContactPanda_Store::salutation_language( $entry['language'] ?? 'de' ); if ( $name && ! ContactPanda_Store::suggested_salutation( $name, $language ) && ContactPanda_Store::learn_salutation( $name, $salutation, 'central', $language ) ) $added++; }
		$settings['version'] = sanitize_text_field( $json['version'] ?? gmdate( 'Y-m-d' ) ); $settings['source_url'] = esc_url_raw( $url ); $settings['updated_at'] = current_time( 'mysql' ); $settings['last_success_at'] = $settings['updated_at']; $settings['error'] = ''; update_option( self::CENTRAL_OPTION, $settings, false ); return $added;
	}

	public static function render_notice_settings() {
		$settings = wp_parse_args( (array) get_option( self::NOTICE_OPTION, array() ), array( 'enabled' => 0 ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Optional notice cleanup', 'contactpanda' ) . '</h2><p>' . esc_html__( 'ContactPanda can add a Hide link to non-critical promotional, license-key, and upgrade reminders. Error messages, security warnings, and WordPress core update notices are never hidden.', 'contactpanda' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_admin_notice_settings">'; wp_nonce_field( 'contactpanda_admin_notice_settings' );
		echo '<label class="contactpanda-setting-row"><span><strong>' . esc_html__( 'Display of admin notices', 'contactpanda' ) . '</strong></span><select name="notice_mode"><option value="all"' . selected( empty( $settings['enabled'] ), true, false ) . '>' . esc_html__( 'Show all admin notices', 'contactpanda' ) . '</option><option value="hide_optional"' . selected( ! empty( $settings['enabled'] ), true, false ) . '>' . esc_html__( 'Allow hiding non-critical admin notices', 'contactpanda' ) . '</option></select><small>' . esc_html__( 'Switching to “Show all admin notices” automatically restores notices previously hidden for your user account. Security, error, legal, and WordPress core notices can never be hidden.', 'contactpanda' ) . '</small></label><p><button class="button button-primary"><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html__( 'Save notice settings', 'contactpanda' ) . '</button></p></form></section>';
	}

	public static function handle_notice_settings() { self::require_admin( 'contactpanda_admin_notice_settings' ); $enabled = 'hide_optional' === sanitize_key( wp_unslash( $_POST['notice_mode'] ?? 'all' ) ); update_option( self::NOTICE_OPTION, array( 'enabled' => $enabled ? 1 : 0 ), false ); if ( ! $enabled ) delete_user_meta( get_current_user_id(), 'contactpanda_hidden_admin_notices' ); wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=other&other_section=admin_notices' ) ); exit; }

	public static function enqueue_notice_helper() {
		$settings = (array) get_option( self::NOTICE_OPTION, array() );
		if ( ! current_user_can( 'manage_options' ) || empty( $settings['enabled'] ) ) return;
		wp_enqueue_script( 'contactpanda-admin-notices', CONTACTPANDA_URL . 'assets/contactpanda-admin-notices.js', array(), CONTACTPANDA_ASSET_VERSION, true );
		wp_localize_script( 'contactpanda-admin-notices', 'CONTACTPANDA_NOTICE_CONFIG', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contactpanda_dismiss_admin_notice' ), 'hidden' => array_keys( (array) get_user_meta( get_current_user_id(), 'contactpanda_hidden_admin_notices', true ) ), 'hideLabel' => __( 'Hide this reminder', 'contactpanda' ) ) );
	}

	public static function dismiss_admin_notice() {
		check_ajax_referer( 'contactpanda_dismiss_admin_notice', 'nonce' ); if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error(); $key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) ); $excerpt = sanitize_text_field( wp_unslash( $_POST['excerpt'] ?? '' ) ); if ( ! preg_match( '/^[a-f0-9]{8}$/', $key ) ) wp_send_json_error(); $hidden = (array) get_user_meta( get_current_user_id(), 'contactpanda_hidden_admin_notices', true ); $hidden[ $key ] = substr( $excerpt, 0, 180 ); update_user_meta( get_current_user_id(), 'contactpanda_hidden_admin_notices', $hidden ); wp_send_json_success();
	}
}
