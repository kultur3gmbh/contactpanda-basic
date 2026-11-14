<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Basic_Newsletter_Campaigns {
	// Keep the historic hook name so queued mailings survive the 0.6.0 move
	// from Plus into Basic without being rescheduled or duplicated.
	const CRON_HOOK = 'contactpanda_plus_process_newsletter_queue';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_post_contactpanda_save_campaign', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_contactpanda_campaign_action', array( __CLASS__, 'campaign_action' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ), 10, 1 );
		add_action( 'admin_post_contactpanda_newsletter_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'admin_post_nopriv_contactpanda_newsletter_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'wp_ajax_contactpanda_campaign_preview', array( __CLASS__, 'preview_ajax' ) );
		add_action( 'wp_ajax_contactpanda_campaign_test', array( __CLASS__, 'test_ajax' ) );
		add_action( 'wp_ajax_contactpanda_campaign_statuses', array( __CLASS__, 'statuses_ajax' ) );
		add_action( 'wp_ajax_contactpanda_campaign_customer_search', array( __CLASS__, 'customer_search_ajax' ) );
		add_action( 'wp_ajax_contactpanda_campaign_group_create', array( __CLASS__, 'campaign_group_create_ajax' ) );
		add_action( 'admin_head', array( __CLASS__, 'modal_admin_chrome' ) );
		add_filter( 'contactpanda_misc_settings_sections', array( __CLASS__, 'misc_sections' ) );
		add_action( 'contactpanda_render_misc_settings_section_campaigns', array( __CLASS__, 'render_campaign_groups' ) );
		add_action( 'admin_post_contactpanda_campaign_group', array( __CLASS__, 'campaign_group_action' ) );
	}

	public static function modal_admin_chrome() {
		if ( empty( $_GET['contactpanda_modal'] ) || 'contactpanda-campaigns' !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presentation-only modal mode.
		echo '<style>#adminmenumain,#wpadminbar,#wpfooter{display:none!important}html.wp-toolbar{padding-top:0!important}#wpcontent{margin-left:0!important}.contactpanda-wrap{margin:18px!important}</style>';
	}

	public static function misc_sections( $sections ) { $sections['campaigns'] = __( 'Campaigns', 'contactpanda' ); return $sections; }

	private static function campaign_groups() {
		global $wpdb; ContactPanda_Newsletter_Schema::install(); $table = ContactPanda_Newsletter_Schema::table_names()['campaign_groups'];
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY name ASC", $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function render_campaign_groups() {
		$groups = self::campaign_groups();
		$adding = isset( $_GET['add_campaign_group'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display routing only.
		$edit_id = absint( $_GET['edit_campaign_group'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$editing = false; foreach ( $groups as $candidate ) if ( absint( $candidate['id'] ) === $edit_id ) $editing = $candidate;
		echo '<section class="contactpanda-card contactpanda-settings-card"><div class="contactpanda-toolbar"><div><h2>' . esc_html__( 'Campaigns', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Group related newsletter mailings in campaigns.', 'contactpanda' ) . '</p></div><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns&add_campaign_group=1' ) ) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add campaign', 'contactpanda' ) . '</a></div>';
		if ( $adding || $editing ) { echo '<div class="contactpanda-admin-modal is-open"><section class="contactpanda-admin-modal-panel"><h2>' . esc_html( $editing ? __( 'Edit campaign', 'contactpanda' ) : __( 'Add campaign', 'contactpanda' ) ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-settings-grid"><input type="hidden" name="action" value="contactpanda_campaign_group"><input type="hidden" name="operation" value="save"><input type="hidden" name="id" value="' . esc_attr( $edit_id ) . '">'; wp_nonce_field( 'contactpanda_campaign_group' );
		echo '<label><span>' . esc_html__( 'Campaign name', 'contactpanda' ) . '</span><input name="name" value="' . esc_attr( $editing['name'] ?? '' ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda' ) . '</span><input name="description" value="' . esc_attr( $editing['description'] ?? '' ) . '"></label><div class="contactpanda-modal-actions"><button class="button button-primary"><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html__( 'Save', 'contactpanda' ) . '</button><a class="button" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns' ) ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span>' . esc_html__( 'Cancel', 'contactpanda' ) . '</a></div></form></section></div>'; }
		echo '<table class="widefat striped contactpanda-campaign-groups-table"><thead><tr><th>' . esc_html__( 'Campaign', 'contactpanda' ) . '</th><th>' . esc_html__( 'Description', 'contactpanda' ) . '</th><th>' . esc_html__( 'Mailings', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		global $wpdb; $campaigns_table = ContactPanda_Newsletter_Schema::table_names()['campaigns'];
		foreach ( $groups as $group ) {
			$count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_group_id=%d", $campaigns_table, $group['id'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$id = absint( $group['id'] ); $form_id = 'contactpanda-campaign-group-' . $id;
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_campaign_group&operation=delete&id=' . $id ), 'contactpanda_campaign_group' );
			$mailings = add_query_arg( 'campaign_group', $id, admin_url( 'admin.php?page=contactpanda-campaigns' ) );
			echo '<tr><td><strong>' . esc_html( $group['name'] ) . '</strong></td><td>' . esc_html( $group['description'] ?: '—' ) . '</td><td><a href="' . esc_url( $mailings ) . '">' . esc_html( $count ) . '</a></td><td class="contactpanda-row-actions"><a class="button" href="' . esc_url( add_query_arg( 'edit_campaign_group', $id, admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns' ) ) ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span>' . esc_html__( 'Edit', 'contactpanda' ) . '</a><a class="button contactpanda-destructive" href="' . esc_url( $delete ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this campaign group? Its mailings and statistics will be retained without a campaign assignment.', 'contactpanda' ) ) ) . ');"><span class="dashicons dashicons-trash" aria-hidden="true"></span>' . esc_html__( 'Delete', 'contactpanda' ) . '</a></td></tr>';
		}
		if ( ! $groups ) echo '<tr><td colspan="4">' . esc_html__( 'No campaign has been created yet.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table></section>';
	}

	public static function campaign_group_action() {
		if ( ! current_user_can( self::capability() ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) );
		check_admin_referer( 'contactpanda_campaign_group' ); global $wpdb; ContactPanda_Newsletter_Schema::install(); $table = ContactPanda_Newsletter_Schema::table_names()['campaign_groups'];
		$operation = sanitize_key( wp_unslash( $_REQUEST['operation'] ?? '' ) );
		if ( 'delete' === $operation ) { $id = absint( $_GET['id'] ?? 0 ); $campaigns = ContactPanda_Newsletter_Schema::table_names()['campaigns']; $wpdb->update( $campaigns, array( 'campaign_group_id' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'campaign_group_id' => $id ) ); $wpdb->delete( $table, array( 'id' => $id ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		elseif ( 'save' === $operation ) { $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); if ( $name ) { $id = absint( $_POST['id'] ?? 0 ); $existing = $id ?: absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE name=%s", $table, $name ) ) ); $data = array( 'name' => $name, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ), 'updated_at' => current_time( 'mysql' ) ); if ( $existing ) $wpdb->update( $table, $data, array( 'id' => $existing ) ); else { $data['created_at'] = current_time( 'mysql' ); $wpdb->insert( $table, $data ); } } } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=misc&misc_section=campaigns' ) ); exit;
	}

	public static function campaign_group_create_ajax() {
		check_ajax_referer( self::ajax_nonce_action(), 'nonce' );
		global $wpdb;
		ContactPanda_Newsletter_Schema::install();
		$table = ContactPanda_Newsletter_Schema::table_names()['campaign_groups'];
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Enter a campaign name.', 'contactpanda' ) ), 400 );
		}
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE name=%s", $table, $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		if ( $existing ) {
			wp_send_json_success( array( 'id' => absint( $existing['id'] ), 'name' => $existing['name'], 'description' => $existing['description'], 'existing' => true ) );
		}
		$now = current_time( 'mysql' );
		$wpdb->insert( $table, array( 'name' => $name, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ), 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points.
		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( array( 'message' => __( 'The campaign could not be created.', 'contactpanda' ) ), 500 );
		}
		wp_send_json_success( array( 'id' => absint( $wpdb->insert_id ), 'name' => $name, 'description' => sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) ), 'existing' => false ) );
	}

	public static function menu() {
		add_submenu_page( 'contactpanda', __( 'Newsletters', 'contactpanda' ), __( 'Newsletter', 'contactpanda' ), self::capability(), 'contactpanda-campaigns', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); }
		ContactPanda_Newsletter_Schema::install();
		global $wpdb;
		$t = ContactPanda_Newsletter_Schema::table_names();
		$search = sanitize_text_field( wp_unslash( $_GET['mailing_search'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$status_filter = sanitize_key( wp_unslash( $_GET['mailing_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$group_filter = absint( $_GET['campaign_group'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$group_filters = array_map( 'sanitize_key', (array) wp_unslash( $_GET['campaign_groups'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $group_filter ) $group_filters[] = (string) $group_filter;
		$group_filters = array_values( array_unique( array_filter( $group_filters ) ) );
		$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ); $date_to = sanitize_text_field( wp_unslash( $_GET['date_to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where = array( '1=1' ); $params = array();
		if ( $search ) { $where[] = '(c.name LIKE %s OR c.subject LIKE %s OR g.name LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; array_push( $params, $like, $like, $like ); }
		if ( 'open' === $status_filter ) $where[] = "c.status NOT IN ('completed','cancelled','imported')";
		elseif ( 'completed' === $status_filter ) $where[] = "c.status IN ('completed','cancelled','imported')";
		if ( $group_filters ) {
			$group_ids = array_values( array_filter( array_map( 'absint', $group_filters ) ) );
			$parts = array();
			if ( $group_ids ) { $parts[] = 'c.campaign_group_id IN (' . implode( ',', array_fill( 0, count( $group_ids ), '%d' ) ) . ')'; $params = array_merge( $params, $group_ids ); }
			if ( in_array( 'none', $group_filters, true ) ) $parts[] = 'c.campaign_group_id=0';
			if ( $parts ) $where[] = '(' . implode( ' OR ', $parts ) . ')';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) { $where[] = 'c.created_at >= %s'; $params[] = $date_from . ' 00:00:00'; }
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) { $where[] = 'c.created_at <= %s'; $params[] = $date_to . ' 23:59:59'; }
		$sql = "SELECT c.*,s.name AS segment_name,t.name AS template_name,g.name AS campaign_group_name,(SELECT COUNT(*) FROM %i br WHERE br.campaign_id=c.id AND br.status='bounced') AS bounced_count FROM %i c LEFT JOIN %i s ON s.id=c.segment_id LEFT JOIN %i t ON t.id=c.template_id LEFT JOIN %i g ON g.id=c.campaign_group_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY c.created_at DESC LIMIT 100'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$campaigns = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $t['recipients'], $t['campaigns'], $t['segments'], $t['templates'], $t['campaign_groups'] ), $params ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.NotPrepared -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Only fixed SQL clauses/generated placeholder lists are concatenated; the complete query is passed to wpdb::prepare with identifier and scalar arguments in SQL order. No request value is concatenated.
		$editing = ! empty( $_GET['edit_campaign'] ) ? self::campaign( absint( $_GET['edit_campaign'] ) ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor selection.
		$editor_open = $editing || ! empty( $_GET['new_campaign'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presentation state only.
		$details_open = ! empty( $_GET['campaign_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only details.
		$heading_action = $editor_open || $details_open
			? '<a class="button contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-campaigns' ) ) . '">' . esc_html__( 'Back to mailings', 'contactpanda' ) . '</a>'
			: '<a class="button button-primary contactpanda-heading-action" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-campaigns&new_campaign=1' ) ) . '">' . esc_html__( 'Create new mailing', 'contactpanda' ) . '</a>';
		echo '<div class="wrap contactpanda-wrap"><div class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA</p><h1>' . esc_html__( 'Newsletter mailing', 'contactpanda' ) . '</h1><p>' . esc_html__( 'Prepare, check and send newsletters through a controlled queue.', 'contactpanda' ) . '</p></div>' . $heading_action . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped components.
		if ( 'invalid_schedule' === sanitize_key( wp_unslash( $_GET['campaign_notice'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Choose a valid delivery date and time in the future. The mailing was saved as a draft.', 'contactpanda' ) . '</p></div>';
		}
		if ( $editor_open ) {
			self::render_form( $editing && in_array( $editing['status'], array( 'draft', 'scheduled', 'paused' ), true ) ? $editing : false );
			echo '</div>';
			return;
		}
		if ( $details_open ) {
			self::render_recipients( absint( $_GET['campaign_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only details.
			echo '</div>';
			return;
		}
		$groups = self::campaign_groups();
		$selected_group_names = array();
		if ( in_array( 'none', $group_filters, true ) ) $selected_group_names[] = __( 'Without campaign', 'contactpanda' );
		foreach ( $groups as $group ) if ( in_array( (string) $group['id'], $group_filters, true ) ) $selected_group_names[] = $group['name'];
		$group_filter_label = $selected_group_names ? implode( ', ', $selected_group_names ) : __( 'Campaigns', 'contactpanda' );
		echo '<section class="contactpanda-card"><h2>' . esc_html__( 'Newsletter mailings', 'contactpanda' ) . '</h2><form method="get" class="contactpanda-mailing-filters"><input type="hidden" name="page" value="contactpanda-campaigns"><input type="search" name="mailing_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search mailings and campaigns', 'contactpanda' ) . '"><div class="contactpanda-campaign-filter"><button type="button" class="button contactpanda-campaign-filter-toggle" aria-expanded="false"><span>' . esc_html( $group_filter_label ) . '</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button><div class="contactpanda-campaign-filter-panel" hidden><input type="search" class="contactpanda-campaign-filter-search" placeholder="' . esc_attr__( 'Search campaigns', 'contactpanda' ) . '"><label><input type="checkbox" name="campaign_groups[]" value="none"' . checked( in_array( 'none', $group_filters, true ), true, false ) . '> ' . esc_html__( 'Without campaign', 'contactpanda' ) . '</label>';
		foreach ( $groups as $group ) echo '<label><input type="checkbox" name="campaign_groups[]" value="' . esc_attr( $group['id'] ) . '"' . checked( in_array( (string) $group['id'], $group_filters, true ), true, false ) . '> ' . esc_html( $group['name'] ) . '</label>';
		echo '</div></div><select name="mailing_status"><option value="">' . esc_html__( 'All', 'contactpanda' ) . '</option><option value="open"' . selected( $status_filter, 'open', false ) . '>' . esc_html__( 'Open', 'contactpanda' ) . '</option><option value="completed"' . selected( $status_filter, 'completed', false ) . '>' . esc_html__( 'Completed', 'contactpanda' ) . '</option></select><label>' . esc_html__( 'From', 'contactpanda' ) . ' <input type="date" name="date_from" value="' . esc_attr( $date_from ) . '"></label><label>' . esc_html__( 'To', 'contactpanda' ) . ' <input type="date" name="date_to" value="' . esc_attr( $date_to ) . '"></label><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form><table class="widefat striped contactpanda-mailings-table"><thead><tr><th>' . esc_html__( 'Mailing', 'contactpanda' ) . '</th><th>' . esc_html__( 'Subject', 'contactpanda' ) . '</th><th>' . esc_html__( 'Recipients', 'contactpanda' ) . '</th><th>' . esc_html__( 'Delivery time', 'contactpanda' ) . '</th><th>' . esc_html__( 'Result', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $campaigns as $campaign ) {
			$imported = 'imported' === $campaign['status'];
			$detail_url = admin_url( 'admin.php?page=contactpanda-campaigns&campaign_id=' . absint( $campaign['id'] ) );
			$edit_url = admin_url( 'admin.php?page=contactpanda-campaigns&edit_campaign=' . absint( $campaign['id'] ) );
			$links = '<a href="' . esc_url( $detail_url ) . '">' . esc_html( $imported ? __( 'View archive', 'contactpanda' ) : __( 'View evaluation', 'contactpanda' ) ) . '</a>';
			if ( ! $imported && in_array( $campaign['status'], array( 'draft', 'scheduled', 'paused' ), true ) ) $links .= ' <span aria-hidden="true">|</span> <a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'contactpanda' ) . '</a>';
			$actions = '';
			if ( ! $imported && in_array( $campaign['status'], array( 'draft', 'paused' ), true ) ) $actions .= self::action_link( $campaign['id'], 'start', __( 'Start', 'contactpanda' ), 'button contactpanda-start-mailing' );
			if ( ! $imported && in_array( $campaign['status'], array( 'queued', 'sending' ), true ) ) {
				$actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-running" aria-live="polite">' . esc_html__( 'Sending in progress', 'contactpanda' ) . '</span>';
			} elseif ( ! $imported && 'scheduled' === $campaign['status'] && ! empty( $campaign['scheduled_at'] ) ) {
				$scheduled_label = sprintf(
					/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
					__( 'Sending starts on %1$s at %2$s', 'contactpanda' ),
					mysql2date( get_option( 'date_format' ), $campaign['scheduled_at'] ),
					mysql2date( get_option( 'time_format' ), $campaign['scheduled_at'] )
				);
				$actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-scheduled">' . esc_html( $scheduled_label ) . '</span>';
			}
			if ( ! $imported && 'completed' === $campaign['status'] ) $actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-completed">' . esc_html__( 'Completed', 'contactpanda' ) . '</span>';
			if ( ! $imported && 'cancelled' === $campaign['status'] ) $actions .= '<span class="button contactpanda-mailing-state contactpanda-mailing-cancelled">' . esc_html__( 'Cancelled', 'contactpanda' ) . '</span>';
			if ( ! $imported && ! in_array( $campaign['status'], array( 'completed', 'cancelled' ), true ) ) $actions .= self::action_link( $campaign['id'], 'cancel', __( 'Cancel', 'contactpanda' ), 'button contactpanda-cancel-mailing' );
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$recipient_summary = '<span data-contactpanda-mailing-total>' . esc_html( sprintf( __( '%d selected', 'contactpanda' ), absint( $campaign['total_recipients'] ) ) ) . '</span><span data-contactpanda-mailing-sent>' . esc_html( sprintf( __( '%d sent', 'contactpanda' ), absint( $campaign['sent_count'] ) ) ) . '</span><span data-contactpanda-mailing-failed>' . esc_html( sprintf( __( '%d failed', 'contactpanda' ), absint( $campaign['failed_count'] ) ) ) . '</span><span data-contactpanda-mailing-bounced>' . esc_html( sprintf( __( '%d bounces', 'contactpanda' ), absint( $campaign['bounced_count'] ?? 0 ) ) ) . '</span>';
			if ( ! empty( $campaign['scheduled_at'] ) ) {
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				$delivery_time = sprintf( __( '%1$s at %2$s', 'contactpanda' ), mysql2date( get_option( 'date_format' ), $campaign['scheduled_at'] ), mysql2date( get_option( 'time_format' ), $campaign['scheduled_at'] ) );
			} elseif ( ! empty( $campaign['started_at'] ) ) {
				$delivery_time = __( 'Immediately after start', 'contactpanda' );
			} elseif ( 'draft' === $campaign['status'] ) {
				$delivery_time = __( 'Draft', 'contactpanda' );
			} else {
				$delivery_time = self::status_label( $campaign['status'] );
			}
			if ( ! empty( $campaign['completed_at'] ) ) {
				$delivery_time .= '<small><strong>' . esc_html__( 'Completed', 'contactpanda' ) . ':</strong> ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $campaign['completed_at'] ) ) . '</small>';
			}
			$eligible = max( 0, absint( $campaign['total_recipients'] ) - absint( $campaign['skipped_count'] ) );
			$delivered = absint( $campaign['sent_count'] );
			$rate = static function ( $count, $base ) { return $base ? number_format_i18n( ( 100 * $count ) / $base, 1 ) . '%' : '0%'; };
			$processed = absint( $campaign['sent_count'] ) + absint( $campaign['failed_count'] ) + absint( $campaign['skipped_count'] );
			$progress = absint( $campaign['total_recipients'] ) ? min( 100, (int) round( 100 * $processed / absint( $campaign['total_recipients'] ) ) ) : 0;
			if ( 'completed' === $campaign['status'] ) $progress = 100;
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$progress_bar = ( ! $imported && 'draft' !== $campaign['status'] ) ? '<span class="contactpanda-mailing-progress" aria-label="' . esc_attr( sprintf( __( 'Mailing progress: %d%%', 'contactpanda' ), $progress ) ) . '"><i style="width:' . esc_attr( $progress ) . '%"></i><small>' . esc_html( $progress ) . '%</small></span>' : '';
			$audience_label = 'manual' === ( $campaign['audience_source'] ?? 'contacts' ) ? __( 'Selected people', 'contactpanda' ) : ( 'registered_customers' === ( $campaign['audience_source'] ?? 'contacts' ) ? __( 'Registered customers', 'contactpanda' ) : ( 'all_contacts' === ( $campaign['audience_source'] ?? 'contacts' ) ? __( 'All contacts', 'contactpanda' ) : ( $campaign['segment_name'] ?: __( 'Contacts from the saved search', 'contactpanda' ) ) ) );
			$campaign_group_badge = $campaign['campaign_group_name'] ? '<small class="contactpanda-mailing-campaign">' . esc_html( $campaign['campaign_group_name'] ) . '</small>' : '';
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			$selected_summary = sprintf( __( '%d selected', 'contactpanda' ), absint( $campaign['total_recipients'] ) );
			$result_html = '<span><strong>' . esc_html__( 'Delivered', 'contactpanda' ) . ':</strong> <span data-contactpanda-mailing-delivered>' . esc_html( $delivered . ' · ' . $rate( $delivered, $eligible ) ) . '</span></span><span><strong>' . esc_html__( 'Failed', 'contactpanda' ) . ':</strong> ' . esc_html( absint( $campaign['failed_count'] ) ) . '</span>';
			$result_html = (string) apply_filters( 'contactpanda_campaign_list_result_html', $result_html, $campaign, $delivered, $eligible );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
			echo '<tr class="contactpanda-mailing-main-row" data-contactpanda-campaign-id="' . esc_attr( absint( $campaign['id'] ) ) . '" data-contactpanda-campaign-status="' . esc_attr( $campaign['status'] ) . '"><td><strong>' . esc_html( $campaign['name'] ) . '</strong>' . $campaign_group_badge . '<span class="contactpanda-mailing-row-actions">' . wp_kses_post( $links ) . '</span></td><td>' . esc_html( $campaign['subject'] ?: '—' ) . '</td><td><small class="contactpanda-recipient-summary">' . wp_kses_post( $recipient_summary ) . '</small></td><td><span class="contactpanda-delivery-time">' . wp_kses_post( $delivery_time ) . '</span></td><td class="contactpanda-mailing-statistics">' . wp_kses_post( $result_html ) . '</td><td class="contactpanda-mailing-actions">' . $actions . '</td></tr>';
		}
		if ( ! $campaigns ) { echo '<tr><td colspan="6">' . esc_html__( 'No campaign has been created yet.', 'contactpanda' ) . '</td></tr>'; }
		echo '</tbody></table></section>';
		echo '</div>';
	}

	private static function render_recipients( $campaign_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names();
		$campaign = self::campaign( $campaign_id );
		if ( ! $campaign ) return;
		echo '<section class="contactpanda-card contactpanda-mailing-details contactpanda-mailing-details-page"><header class="contactpanda-mailing-page-header"><h2>' . esc_html__( 'Mailing details', 'contactpanda' ) . '</h2></header>';
		if ( $campaign && 'imported' === $campaign['status'] ) {
			echo '<h3>' . esc_html__( 'Imported Groundhogg broadcast', 'contactpanda' ) . '</h3><div class="notice notice-info inline"><p>' . esc_html__( 'This completed broadcast is a protected archive. It cannot be started, changed, or sent again.', 'contactpanda' ) . '</p></div>';
			echo '<p><strong>' . esc_html__( 'Groundhogg ID', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['external_id'] ) . ' &nbsp; <strong>' . esc_html__( 'Recipients', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['total_recipients'] ) . ' &nbsp; <strong>' . esc_html__( 'Sent', 'contactpanda' ) . ':</strong> ' . esc_html( $campaign['sent_count'] ) . '</p>';
			echo '<div class="contactpanda-newsletter-preview-stage"><iframe sandbox="allow-popups allow-popups-to-escape-sandbox" style="width:100%;min-height:680px;border:1px solid #dcdcde;background:#fff" title="' . esc_attr__( 'Imported broadcast preview', 'contactpanda' ) . '" srcdoc="' . esc_attr( $campaign['archived_html'] ) . '"></iframe></div></section>';
			return;
		}
		$aggregate = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) total, SUM(sent_at IS NOT NULL) sent, SUM(status='failed') failed, SUM(status='skipped') skipped FROM %i WHERE campaign_id=%d", $t['recipients'], $campaign_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$total = absint( $aggregate['total'] ?? 0 ); $sent = absint( $aggregate['sent'] ?? 0 ); $failed = absint( $aggregate['failed'] ?? 0 ); $skipped = absint( $aggregate['skipped'] ?? 0 );
		$filter = sanitize_key( wp_unslash( $_GET['recipient_filter'] ?? 'all' ) ); $search = sanitize_text_field( wp_unslash( $_GET['recipient_search'] ?? '' ) ); $page = max( 1, absint( $_GET['recipient_page'] ?? 1 ) ); $per_page = in_array( absint( $_GET['recipient_per_page'] ?? 50 ), array( 25, 50, 100 ), true ) ? absint( $_GET['recipient_per_page'] ?? 50 ) : 50; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only recipient filtering and pagination, reached through the capability-checked campaign page; all query values are sanitized and bound.
		$conditions = array( 'campaign_id=%d' ); $values = array( $campaign_id ); $filter_sql = array( 'opened' => 'opened_at IS NOT NULL', 'clicked' => 'clicked_at IS NOT NULL', 'sent' => 'sent_at IS NOT NULL', 'failed' => "status='failed'", 'skipped' => "status='skipped'" );
		if ( isset( $filter_sql[ $filter ] ) ) $conditions[] = $filter_sql[ $filter ];
		if ( '' !== $search ) { $conditions[] = '(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; array_push( $values, $like, $like, $like ); }
		$where = implode( ' AND ', $conditions );
		$filtered_total = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", array_merge( array( $t['recipients'] ), $values ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Only fixed SQL clauses/generated placeholder lists are concatenated; the complete query is passed to wpdb::prepare with identifier and scalar arguments in SQL order. No request value is concatenated.
		$pages = max( 1, (int) ceil( $filtered_total / $per_page ) ); $page = min( $page, $pages ); $offset = ( $page - 1 ) * $per_page; $row_values = array_merge( $values, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY status ASC,email ASC LIMIT %d OFFSET %d", array_merge( array( $t['recipients'] ), $row_values ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately. Only fixed SQL clauses/generated placeholder lists are concatenated; the complete query is passed to wpdb::prepare with identifier and scalar arguments in SQL order. No request value is concatenated. The argument array contains table, campaign, optional bound filter values, then limit/offset; its length matches the generated WHERE placeholder count (covered by SQL safety tests).
		echo '<div class="contactpanda-mailing-report">';
		$metrics = array( 'all' => array( __( 'Recipients', 'contactpanda' ), $total ), 'sent' => array( __( 'Sent', 'contactpanda' ), $sent ), 'failed' => array( __( 'Failed', 'contactpanda' ), $failed ), 'skipped' => array( __( 'Skipped', 'contactpanda' ), $skipped ) );
		foreach ( $metrics as $metric_filter => $metric ) {
			$url = add_query_arg( array( 'page' => 'contactpanda-campaigns', 'campaign_id' => $campaign_id, 'recipient_filter' => $metric_filter ), admin_url( 'admin.php' ) );
			echo '<a class="contactpanda-card' . ( $filter === $metric_filter ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"><strong>' . esc_html( $metric[0] ) . '</strong><p>' . esc_html( number_format_i18n( $metric[1] ) ) . '</p></a>';
		}
		echo '</div>';
		$reasons = array( 'consent_missing' => __( 'Consent missing', 'contactpanda' ), 'protected_optout' => __( 'Unsubscribed, blocked or undeliverable', 'contactpanda' ), 'suppressed' => __( 'Globally suppressed', 'contactpanda' ), 'invalid_email' => __( 'Invalid email address', 'contactpanda' ), 'contact_missing' => __( 'Contact no longer exists', 'contactpanda' ), 'customer_missing' => __( 'Customer account no longer exists', 'contactpanda' ), 'wp_mail_failed' => __( 'Mail delivery failed', 'contactpanda' ) );
		if ( ! empty( $campaign['ignore_marketing_consent'] ) ) {
			$user = get_userdata( absint( $campaign['consent_override_user_id'] ) );
			$reason = self::override_reason_label( $campaign['consent_override_reason'] );
			/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Marketing consent override', 'contactpanda' ) . ':</strong> ' . esc_html( sprintf( __( '%1$s enabled this exception on %2$s. Reason: %3$s. %4$d recipients without documented consent were included.', 'contactpanda' ), $user ? $user->display_name : __( 'Administrator', 'contactpanda' ), mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $campaign['consent_override_at'] ), $reason, absint( $campaign['consent_override_count'] ) ) ) . '</p></div>';
		}
		do_action( 'contactpanda_campaign_before_recipient_table', $campaign, $aggregate );
		if ( class_exists( 'ContactPanda_Registration' ) ) {
			ContactPanda_Registration::render_plus_promotion( 'newsletter-reports' );
		}
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		echo '<h3>' . esc_html__( 'Recipient check', 'contactpanda' ) . '</h3><form method="get" class="contactpanda-recipient-filter"><input type="hidden" name="page" value="contactpanda-campaigns"><input type="hidden" name="campaign_id" value="' . esc_attr( $campaign_id ) . '"><input type="hidden" name="recipient_filter" value="' . esc_attr( $filter ) . '"><input type="search" name="recipient_search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search recipients', 'contactpanda' ) . '"><select name="recipient_per_page"><option' . selected( 25, $per_page, false ) . '>25</option><option' . selected( 50, $per_page, false ) . '>50</option><option' . selected( 100, $per_page, false ) . '>100</option></select><button class="button">' . esc_html__( 'Search', 'contactpanda' ) . '</button></form><p>' . esc_html( sprintf( __( 'Showing %1$d–%2$d of %3$d recipients.', 'contactpanda' ), $filtered_total ? $offset + 1 : 0, min( $offset + $per_page, $filtered_total ), $filtered_total ) ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Recipient', 'contactpanda' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda' ) . '</th><th>' . esc_html__( 'Interactions', 'contactpanda' ) . '</th><th>' . esc_html__( 'Sent at', 'contactpanda' ) . '</th><th>' . esc_html__( 'Reason', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$name = trim( $row['first_name'] . ' ' . $row['last_name'] ); $person_url = admin_url( 'admin.php?page=contactpanda-people&person=' . absint( $row['contact_id'] ) );
			$format_time = static function ( $value ) { return $value ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $value ) : '—'; };
			$status_labels = array( 'queued' => __( 'Queued', 'contactpanda' ), 'sent' => __( 'Sent', 'contactpanda' ), 'failed' => __( 'Failed', 'contactpanda' ), 'bounced' => __( 'Bounced', 'contactpanda' ) );
			$status_label  = $status_labels[ $row['status'] ] ?? ucfirst( (string) $row['status'] );
			if ( ! empty( $row['clicked_at'] ) ) $interaction = '<span class="contactpanda-interaction contactpanda-interaction--clicked">' . esc_html__( 'Clicked', 'contactpanda' ) . '</span>';
			elseif ( ! empty( $row['opened_at'] ) ) $interaction = '<span class="contactpanda-interaction contactpanda-interaction--opened">' . esc_html__( 'Opened', 'contactpanda' ) . '</span>';
			elseif ( ! empty( $row['sent_at'] ) ) $interaction = '<span class="contactpanda-interaction contactpanda-interaction--delivered">' . esc_html__( 'Delivered', 'contactpanda' ) . '</span>';
			else $interaction = '<span class="contactpanda-interaction contactpanda-interaction--prepared">' . esc_html__( 'Prepared', 'contactpanda' ) . '</span>';
			echo '<tr><td><a href="' . esc_url( $person_url ) . '">' . esc_html( $name ?: $row['email'] ) . '</a><br><small>' . esc_html( $row['email'] ) . '</small></td><td>' . esc_html( $status_label ) . '</td><td>' . wp_kses_post( $interaction ) . '</td><td>' . esc_html( $format_time( $row['sent_at'] ?? '' ) ) . '</td><td>' . esc_html( $reasons[ $row['reason'] ] ?? $row['reason'] ) . '</td></tr>';
		}
		if ( ! $rows ) { echo '<tr><td colspan="5">' . esc_html__( 'No recipients have been prepared yet.', 'contactpanda' ) . '</td></tr>'; }
		echo '</tbody></table>';
		ContactPanda_Pagination::render( $page, $pages, 1, remove_query_arg( 'recipient_page' ), 'recipient_page' );
		echo '</section>';
	}

	private static function render_form( $campaign = false ) {
		$templates = ContactPanda_Basic_Newsletters::templates(); $segments = ContactPanda_Basic_Segments::all(); $groups = self::campaign_groups();
		$newsletter_defaults = ContactPanda_Basic_Newsletters::public_settings();
		$value = static function ( $key, $default = '' ) use ( $campaign ) { return $campaign && isset( $campaign[ $key ] ) ? $campaign[ $key ] : $default; };
		$manual_ids = $campaign ? array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $campaign['manual_contact_ids'] ?? '' ) ) ) ) ) : self::selected_contact_ids_from_request();
		$default_source = $manual_ids ? 'manual' : 'contacts';
		echo '<section id="contactpanda-mailing-editor" class="contactpanda-card contactpanda-mailing-editor contactpanda-mailing-editor-page"><header class="contactpanda-mailing-page-header"><h2>' . esc_html( $campaign ? __( 'Edit newsletter mailing', 'contactpanda' ) : __( 'Newsletter mailing', 'contactpanda' ) ) . '</h2></header><form id="contactpanda-campaign-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_campaign"><input type="hidden" name="campaign_id" value="' . esc_attr( $value( 'id', 0 ) ) . '">'; wp_nonce_field( 'contactpanda_save_campaign' );
		echo '<div class="contactpanda-mailing-editor-grid"><div class="contactpanda-mailing-column"><div class="contactpanda-searchable-select"><span>' . esc_html__( 'Email template', 'contactpanda' ) . '</span><input type="search" class="contactpanda-select-search" placeholder="' . esc_attr__( 'Search email templates', 'contactpanda' ) . '" autocomplete="off"><select name="template_id" required><option value="">—</option>';
		foreach ( $templates as $template ) { echo '<option value="' . esc_attr( $template['id'] ) . '" data-subject="' . esc_attr( $template['subject'] ?? '' ) . '"' . selected( absint( $value( 'template_id' ) ), absint( $template['id'] ), false ) . '>' . esc_html( $template['name'] ) . '</option>'; }
		echo '</select></div><label><span>' . esc_html__( 'Mailing name', 'contactpanda' ) . '</span><input required name="name" value="' . esc_attr( $value( 'name' ) ) . '"></label><label><span>' . esc_html__( 'Subject', 'contactpanda' ) . '</span><input required name="subject" value="' . esc_attr( $value( 'subject' ) ) . '"></label><div class="contactpanda-searchable-select"><span>' . esc_html__( 'Campaign', 'contactpanda' ) . '</span><input type="search" class="contactpanda-select-search" placeholder="' . esc_attr__( 'Search campaigns', 'contactpanda' ) . '" autocomplete="off"><select name="campaign_group_id"><option value="0">—</option>';
		foreach ( $groups as $group ) echo '<option value="' . esc_attr( $group['id'] ) . '"' . selected( absint( $value( 'campaign_group_id' ) ), absint( $group['id'] ), false ) . '>' . esc_html( $group['name'] ) . '</option>';
		echo '</select></div><button type="button" class="button contactpanda-create-campaign">' . esc_html__( 'Create new campaign', 'contactpanda' ) . '</button><div class="contactpanda-saved-search-source"><div class="contactpanda-searchable-select"><span>' . esc_html__( 'Saved search', 'contactpanda' ) . '</span><input type="search" class="contactpanda-select-search" placeholder="' . esc_attr__( 'Search saved searches', 'contactpanda' ) . '" autocomplete="off"><select name="segment_id"><option value="0">' . esc_html__( 'Select saved search', 'contactpanda' ) . '</option>';
		foreach ( $segments as $segment ) { echo '<option value="' . esc_attr( $segment['id'] ) . '"' . selected( absint( $value( 'segment_id' ) ), absint( $segment['id'] ), false ) . '>' . esc_html( $segment['name'] ) . '</option>'; }
		echo '</select></div><button type="button" class="button contactpanda-open-inline-search">' . esc_html__( 'Create new search', 'contactpanda' ) . '</button></div>';
		echo '<fieldset class="contactpanda-audience-source"><legend>' . esc_html__( 'Recipient source', 'contactpanda' ) . '</legend>';
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		if ( $manual_ids ) echo '<label class="contactpanda-manual-audience"><input type="radio" name="audience_source" value="manual"' . checked( $value( 'audience_source', $default_source ), 'manual', false ) . '> ' . esc_html( sprintf( _n( '%d ausgewählte Person aus der Personenliste', '%d ausgewählte Personen aus der Personenliste', count( $manual_ids ), 'contactpanda' ), count( $manual_ids ) ) ) . '</label><input type="hidden" name="manual_contact_ids" value="' . esc_attr( implode( ',', $manual_ids ) ) . '">';
		echo '<label><input type="radio" name="audience_source" value="contacts"' . checked( $value( 'audience_source', $default_source ), 'contacts', false ) . '> ' . esc_html__( 'Contacts from the saved search', 'contactpanda' ) . '</label><label><input type="radio" name="audience_source" value="registered_customers"' . checked( $value( 'audience_source', $default_source ), 'registered_customers', false ) . '> ' . esc_html__( 'Registered customers', 'contactpanda' ) . '</label><label><input type="radio" name="audience_source" value="all_contacts"' . checked( $value( 'audience_source', $default_source ), 'all_contacts', false ) . '> ' . esc_html__( 'All contacts', 'contactpanda' ) . '</label><div class="contactpanda-customer-recipient-search"><label class="contactpanda-customer-search-field"><span>' . esc_html__( 'Customer search', 'contactpanda' ) . '</span><input type="search" name="recipient_search" value="' . esc_attr( $value( 'recipient_search' ) ) . '" placeholder="' . esc_attr__( 'Search by name, email address or company', 'contactpanda' ) . '" autocomplete="off"></label><p class="description">' . esc_html__( 'Unsubscribed, blocked, complained, undeliverable and suppressed addresses always remain excluded.', 'contactpanda' ) . '</p><div class="contactpanda-customer-search-results" aria-live="polite" data-manual-count="' . esc_attr( count( $manual_ids ) ) . '"></div></div></fieldset>';
		if ( current_user_can( 'manage_options' ) ) {
			$override = ! empty( $campaign['ignore_marketing_consent'] );
			echo '<fieldset class="contactpanda-consent-override"><legend>' . esc_html__( 'Administrative exception', 'contactpanda' ) . '</legend><label><input type="checkbox" name="ignore_marketing_consent" value="1"' . checked( $override, true, false ) . '> ' . esc_html__( 'Ignore missing marketing consent for this mailing', 'contactpanda' ) . '</label><div class="notice notice-warning inline"><p>' . esc_html__( 'This exception does not grant or reactivate consent. Unsubscribed, blocked, complained, undeliverable and suppressed addresses always remain excluded.', 'contactpanda' ) . '</p></div><label><span>' . esc_html__( 'Reason for exception', 'contactpanda' ) . '</span><select name="consent_override_reason"><option value="">—</option><option value="personal_decision"' . selected( $value( 'consent_override_reason' ), 'personal_decision', false ) . '>' . esc_html__( 'Personal decision', 'contactpanda' ) . '</option><option value="existing_customer"' . selected( $value( 'consent_override_reason' ), 'existing_customer', false ) . '>' . esc_html__( 'Existing customer relationship', 'contactpanda' ) . '</option><option value="contractual_information"' . selected( $value( 'consent_override_reason' ), 'contractual_information', false ) . '>' . esc_html__( 'Contractual information', 'contactpanda' ) . '</option><option value="service_information"' . selected( $value( 'consent_override_reason' ), 'service_information', false ) . '>' . esc_html__( 'Service or existing-customer information', 'contactpanda' ) . '</option><option value="other"' . selected( $value( 'consent_override_reason' ), 'other', false ) . '>' . esc_html__( 'Other reason', 'contactpanda' ) . '</option></select></label><label><input type="checkbox" name="consent_override_confirm" value="1"> ' . esc_html__( 'I understand the warning and confirm this exception for this mailing.', 'contactpanda' ) . '</label><p class="contactpanda-consent-override-error" role="alert" hidden></p></fieldset>';
		}
		echo '<fieldset class="contactpanda-audience-mode"><legend>' . esc_html__( 'Audience type', 'contactpanda' ) . '</legend><label><input type="radio" name="audience_mode" value="dynamic"' . checked( $value( 'audience_mode', 'dynamic' ), 'dynamic', false ) . '> ' . esc_html__( 'Dynamic – resolve matching contacts when sending starts', 'contactpanda' ) . '</label><label><input type="radio" name="audience_mode" value="static"' . checked( $value( 'audience_mode', 'dynamic' ), 'static', false ) . '> ' . esc_html__( 'Static – keep the contacts matching when this mailing is saved', 'contactpanda' ) . '</label><small>' . esc_html__( 'Current suppression and protected opt-out status is always checked again immediately before every message is sent.', 'contactpanda' ) . '</small></fieldset></div><div class="contactpanda-mailing-column contactpanda-mailing-actions-panel"><h3>' . esc_html__( 'Mailing settings', 'contactpanda' ) . '</h3><div class="contactpanda-campaign-sender-summary"><strong>' . esc_html__( 'Sender details', 'contactpanda' ) . '</strong><dl><dt>' . esc_html__( 'Sender name', 'contactpanda' ) . '</dt><dd>' . esc_html( $newsletter_defaults['from_name'] ) . '</dd><dt>' . esc_html__( 'Sending email address', 'contactpanda' ) . '</dt><dd>' . esc_html( $newsletter_defaults['from_email'] ) . '</dd><dt>' . esc_html__( 'Reply-to address', 'contactpanda' ) . '</dt><dd>' . esc_html( $newsletter_defaults['reply_to'] ) . '</dd></dl><p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-settings&tab=newsletter' ) ) . '">' . esc_html__( 'Change sender details in Email defaults', 'contactpanda' ) . '</a></p></div><input type="hidden" name="from_name" value="' . esc_attr( $newsletter_defaults['from_name'] ) . '"><input type="hidden" name="from_email" value="' . esc_attr( $newsletter_defaults['from_email'] ) . '"><input type="hidden" name="reply_to" value="' . esc_attr( $newsletter_defaults['reply_to'] ) . '"><label><span>' . esc_html__( 'Language', 'contactpanda' ) . '</span><select name="language"><option value="de_DE"' . selected( $value( 'language', 'de_DE' ), 'de_DE', false ) . '>Deutsch</option><option value="en_US"' . selected( $value( 'language' ), 'en_US', false ) . '>English</option></select></label>';
		do_action( 'contactpanda_campaign_render_advanced_settings', $campaign, $value );
		if ( class_exists( 'ContactPanda_Registration' ) ) {
			ContactPanda_Registration::render_plus_promotion( 'newsletter-advanced' );
		}
		$delivery = (array) apply_filters( 'contactpanda_campaign_delivery_state', array( 'later' => false, 'label' => __( 'Send now', 'contactpanda' ), 'submit_mode' => 'send_now' ), $campaign, $value );
		$later = ! empty( $delivery['later'] );
		echo '<fieldset class="contactpanda-campaign-timing"><legend>' . esc_html__( 'Delivery time', 'contactpanda' ) . '</legend><label><input type="radio" name="send_timing" value="now"' . checked( ! $later, true, false ) . '> ' . esc_html__( 'Immediately', 'contactpanda' ) . '</label>';
		do_action( 'contactpanda_campaign_render_delivery_options', $campaign, $value, $later );
		echo '</fieldset><label><span>' . esc_html__( 'Emails per hour', 'contactpanda' ) . '</span><input required type="number" name="mails_per_hour" min="1" max="3000" step="1" value="' . esc_attr( absint( $value( 'mails_per_hour', 300 ) ) ?: 300 ) . '"><small>' . esc_html__( 'ContactPanda divides the mailing into controlled batches. Actual timing depends on the WordPress cron system and the configured mail service.', 'contactpanda' ) . '</small></label><div class="contactpanda-test-send"><strong>' . esc_html__( 'Test mailing', 'contactpanda' ) . '</strong><input type="email" id="contactpanda-campaign-test-email" value="' . esc_attr( wp_get_current_user()->user_email ) . '"><button type="button" class="button" id="contactpanda-campaign-test">' . esc_html__( 'Send test', 'contactpanda' ) . '</button></div><button type="button" class="button" id="contactpanda-campaign-preview">' . esc_html__( 'Preview', 'contactpanda' ) . '</button><div class="contactpanda-mailing-submit-actions"><button type="submit" class="button" name="submit_mode" value="save">' . esc_html__( 'Save', 'contactpanda' ) . '</button><button type="submit" class="button button-primary contactpanda-delivery-submit" name="submit_mode" value="' . esc_attr( sanitize_key( $delivery['submit_mode'] ?? 'send_now' ) ) . '">' . esc_html( $delivery['label'] ?? __( 'Send now', 'contactpanda' ) ) . '</button></div><span id="contactpanda-campaign-message" aria-live="polite"></span></div></div></form></section>';
		echo '<div id="contactpanda-campaign-create-modal" class="contactpanda-consent-modal" hidden><section class="contactpanda-card contactpanda-small-modal"><header class="contactpanda-mailing-modal-header"><h2>' . esc_html__( 'Create campaign', 'contactpanda' ) . '</h2><button type="button" class="button-link contactpanda-close-campaign-create" aria-label="' . esc_attr__( 'Close', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt"></span></button></header><form id="contactpanda-campaign-create-form"><label><span>' . esc_html__( 'Campaign name', 'contactpanda' ) . '</span><input name="name" required></label><label><span>' . esc_html__( 'Description', 'contactpanda' ) . '</span><textarea name="description" rows="4"></textarea></label><p><button class="button button-primary">' . esc_html__( 'Create campaign', 'contactpanda' ) . '</button> <span class="contactpanda-campaign-create-message" aria-live="polite"></span></p></form></section></div>';
		echo '<div id="contactpanda-campaign-preview-modal" class="contactpanda-consent-modal contactpanda-preview-modal" hidden><div class="contactpanda-preview-panel"><header class="contactpanda-preview-header"><h2>' . esc_html__( 'Newsletter preview', 'contactpanda' ) . '</h2><div class="contactpanda-preview-modes"><button type="button" class="button is-active" data-campaign-preview-width="100%" aria-label="' . esc_attr__( 'Desktop view', 'contactpanda' ) . '" title="' . esc_attr__( 'Desktop view', 'contactpanda' ) . '"><span class="dashicons dashicons-desktop" aria-hidden="true"></span></button><button type="button" class="button" data-campaign-preview-width="768px" aria-label="' . esc_attr__( 'Tablet view', 'contactpanda' ) . '" title="' . esc_attr__( 'Tablet view', 'contactpanda' ) . '"><span class="dashicons dashicons-tablet" aria-hidden="true"></span></button><button type="button" class="button" data-campaign-preview-width="390px" aria-label="' . esc_attr__( 'Mobile view', 'contactpanda' ) . '" title="' . esc_attr__( 'Mobile view', 'contactpanda' ) . '"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span></button><button type="button" class="button" id="contactpanda-close-campaign-preview" aria-label="' . esc_attr__( 'Close preview', 'contactpanda' ) . '" title="' . esc_attr__( 'Close preview', 'contactpanda' ) . '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div></header><div class="contactpanda-newsletter-preview-stage"><iframe id="contactpanda-campaign-preview-frame" title="' . esc_attr__( 'Newsletter preview', 'contactpanda' ) . '"></iframe></div></div></div>';
		echo '<div class="contactpanda-consent-modal contactpanda-search-modal" hidden><div class="contactpanda-search-modal-panel">';
		ContactPanda_Basic_Segments::render_inline_search( '', 0, true );
		echo '</div></div>';
	}

	public static function save() {
		check_admin_referer( self::admin_nonce_action( 'contactpanda_save_campaign' ) ); global $wpdb; ContactPanda_Newsletter_Schema::install(); $t = ContactPanda_Newsletter_Schema::table_names();
		$submit_mode = sanitize_key( wp_unslash( $_POST['submit_mode'] ?? 'save' ) );
		$submit_modes = (array) apply_filters( 'contactpanda_campaign_submit_modes', array( 'save', 'send_now' ) );
		if ( ! in_array( $submit_mode, $submit_modes, true ) ) {
			$submit_mode = 'save';
		}
		$template_id = absint( $_POST['template_id'] ?? 0 ); $template = ContactPanda_Basic_Newsletters::template( $template_id );
		if ( ! $template || ! empty( $template['imported_readonly'] ) || 'published' !== ( $template['status'] ?? '' ) ) { wp_die( esc_html__( 'Please select a published email template.', 'contactpanda' ) ); }
		$now = current_time( 'mysql' ); $id = absint( $_POST['campaign_id'] ?? 0 );
		$language = preg_replace( '/[^A-Za-z0-9_@.\-]/', '', sanitize_text_field( wp_unslash( $_POST['language'] ?? 'de_DE' ) ) );
		$sender_settings = ContactPanda_Basic_Newsletters::public_settings();
		$from_name = sanitize_text_field( $sender_settings['from_name'] ?? '' );
		$from_email = sanitize_email( $sender_settings['from_email'] ?? '' );
		$reply_to = sanitize_email( $sender_settings['reply_to'] ?? '' );
		if ( ! $from_name || ! is_email( $from_email ) || ! is_email( $reply_to ) ) {
			wp_die( esc_html__( 'Please enter valid sender and reply-to details.', 'contactpanda' ) );
		}
		$audience_mode = 'static' === sanitize_key( wp_unslash( $_POST['audience_mode'] ?? 'dynamic' ) ) ? 'static' : 'dynamic';
		$audience_source = sanitize_key( wp_unslash( $_POST['audience_source'] ?? 'contacts' ) );
		if ( ! in_array( $audience_source, array( 'contacts', 'registered_customers', 'all_contacts', 'manual' ), true ) ) $audience_source = 'contacts';
		$ignore_consent = ! empty( $_POST['ignore_marketing_consent'] );
		$override_reason = sanitize_key( wp_unslash( $_POST['consent_override_reason'] ?? '' ) );
		if ( $ignore_consent ) {
			if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Only administrators may ignore marketing consent.', 'contactpanda' ) );
			$allowed_override_reasons = array( 'personal_decision', 'existing_customer', 'contractual_information', 'service_information', 'other' );
			if ( ! in_array( $override_reason, $allowed_override_reasons, true ) || empty( $_POST['consent_override_confirm'] ) ) wp_die( esc_html__( 'Select a reason and confirm the administrative exception.', 'contactpanda' ) );
		}
		$manual_ids = 'manual' === $audience_source ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['manual_contact_ids'] ?? '' ) ) ) ) ) ) ), 0, 10000 ) : array();
		if ( 'manual' === $audience_source && ! $manual_ids ) wp_die( esc_html__( 'No selected people were supplied.', 'contactpanda' ) );
		$segment_id = 'contacts' === $audience_source ? absint( $_POST['segment_id'] ?? 0 ) : 0;
		if ( 'contacts' === $audience_source && ! $segment_id ) wp_die( esc_html__( 'Select a saved search or choose another recipient source.', 'contactpanda' ) );
		$recipient_search = 'registered_customers' === $audience_source ? substr( sanitize_text_field( wp_unslash( $_POST['recipient_search'] ?? '' ) ), 0, 190 ) : '';
		$data = array( 'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 'subject' => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ), 'template_id' => $template_id, 'segment_id' => $segment_id, 'manual_contact_ids' => $manual_ids ? implode( ',', $manual_ids ) : null, 'audience_mode' => $audience_mode, 'audience_source' => $audience_source, 'recipient_search' => $recipient_search, 'audience_resolved_at' => null, 'ignore_marketing_consent' => $ignore_consent ? 1 : 0, 'consent_override_reason' => $ignore_consent ? $override_reason : '', 'consent_override_user_id' => $ignore_consent ? get_current_user_id() : 0, 'consent_override_at' => $ignore_consent ? $now : null, 'consent_override_count' => 0, 'campaign_group_id' => absint( $_POST['campaign_group_id'] ?? 0 ), 'language' => $language ?: 'de_DE', 'from_name' => $from_name, 'from_email' => $from_email, 'reply_to' => $reply_to, 'mails_per_hour' => min( 3000, max( 1, absint( $_POST['mails_per_hour'] ?? 300 ) ) ), 'status' => 'draft', 'scheduled_at' => null, 'updated_at' => $now );
		$data = (array) apply_filters( 'contactpanda_campaign_save_data', $data, $_POST, $id );
		if ( $id ) {
			$existing = self::campaign( $id ); if ( ! $existing || ! in_array( $existing['status'], array( 'draft', 'scheduled', 'paused' ), true ) ) wp_die( esc_html__( 'This campaign can no longer be edited.', 'contactpanda' ) );
			wp_unschedule_hook( self::CRON_HOOK, array( $id ) ); $wpdb->update( $t['campaigns'], $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		} else {
			$data['created_by'] = get_current_user_id(); $data['created_at'] = $now; $wpdb->insert( $t['campaigns'], $data ); $id = absint( $wpdb->insert_id ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points.
		}
		if ( 'static' === $audience_mode ) {
			self::prepare_recipients( $id );
			$wpdb->update( $t['campaigns'], array( 'audience_resolved_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		} else {
			$wpdb->delete( $t['recipients'], array( 'campaign_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$wpdb->update( $t['campaigns'], array( 'total_recipients' => 0, 'queued_count' => 0, 'sent_count' => 0, 'failed_count' => 0, 'skipped_count' => 0, 'audience_resolved_at' => null, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		}
		if ( 'send_now' === $submit_mode ) {
			$wpdb->update( $t['campaigns'], array( 'status' => 'queued', 'scheduled_at' => null, 'started_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			self::schedule( $id, time() + 2 );
		} elseif ( 'save' !== $submit_mode ) {
			apply_filters( 'contactpanda_campaign_handle_submit_mode', false, $submit_mode, $id, $data );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-campaigns' ) ); exit;
	}

	private static function prepare_recipients( $campaign_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $campaign = self::campaign( $campaign_id ); if ( ! $campaign ) { return; }
		$contacts = ContactPanda_Plugin::extension_contacts();
		if ( 'manual' === ( $campaign['audience_source'] ?? 'contacts' ) ) {
			$manual_ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) ( $campaign['manual_contact_ids'] ?? '' ) ) ) ) );
			$manual_lookup = array_fill_keys( $manual_ids, true );
			$contacts = array_values( array_filter( $contacts, static function ( $contact ) use ( $manual_lookup ) { return isset( $manual_lookup[ absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID' ) ) ) ] ); } ) );
		} elseif ( 'registered_customers' === ( $campaign['audience_source'] ?? 'contacts' ) ) {
			$contacts = self::registered_customer_contacts( $contacts, (string) ( $campaign['recipient_search'] ?? '' ) );
		} elseif ( 'contacts' === ( $campaign['audience_source'] ?? 'contacts' ) && $campaign['segment_id'] ) {
			$contacts = ContactPanda_Basic_Segments::filter_contacts( $contacts, $campaign['segment_id'] );
		}
		$wpdb->delete( $t['recipients'], array( 'campaign_id' => $campaign_id ) ); $counts = array( 'queued' => 0, 'skipped' => 0 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$override_count = 0;
		foreach ( $contacts as $contact ) {
			$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ); $contact_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID' ) ) );
			$status = 'queued'; $reason = '';
			if ( ! is_email( $email ) ) { $status = 'skipped'; $reason = 'invalid_email'; }
			elseif ( self::has_protected_optout_status( $contact ) ) { $status = 'skipped'; $reason = 'protected_optout'; }
			elseif ( self::is_suppressed( $email ) ) { $status = 'skipped'; $reason = 'suppressed'; }
			elseif ( ! ContactPanda_Plugin::extension_is_marketable( $contact ) && empty( $campaign['ignore_marketing_consent'] ) ) { $status = 'skipped'; $reason = 'consent_missing'; }
			elseif ( ! ContactPanda_Plugin::extension_is_marketable( $contact ) ) { $override_count++; }
			$variant_b = (bool) apply_filters( 'contactpanda_campaign_recipient_variant_b', false, $campaign, $email, $campaign_id );
			$wpdb->insert( $t['recipients'], array( 'campaign_id' => $campaign_id, 'contact_id' => $contact_id, 'email' => strtolower( $email ), 'first_name' => sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'first_name' ) ) ), 'last_name' => sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'last_name' ) ) ), 'subject_variant' => $variant_b ? 'b' : 'a', 'status' => $status, 'reason' => $reason, 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points.
			$counts[ $status ]++;
		}
		$wpdb->update( $t['campaigns'], array( 'total_recipients' => count( $contacts ), 'queued_count' => $counts['queued'], 'skipped_count' => $counts['skipped'], 'consent_override_count' => $override_count, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function campaign_action() {
		check_admin_referer( self::admin_nonce_action( 'contactpanda_campaign_action' ) ); global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $id = absint( $_GET['campaign_id'] ?? 0 ); $command = sanitize_key( wp_unslash( $_GET['command'] ?? '' ) ); $campaign = self::campaign( $id );
		if ( ! $campaign ) { wp_die( esc_html__( 'Campaign not found.', 'contactpanda' ) ); }
		if ( 'imported' === $campaign['status'] ) { wp_die( esc_html__( 'Imported broadcasts are read-only archives.', 'contactpanda' ) ); }
		if ( 'start' === $command ) {
			$timestamp = $campaign['scheduled_at'] ? get_gmt_from_date( $campaign['scheduled_at'], 'U' ) : time(); $status = $timestamp > time() ? 'scheduled' : 'queued';
			$wpdb->update( $t['campaigns'], array( 'status' => $status, 'started_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); self::schedule( $id, max( time() + 2, $timestamp ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		} elseif ( 'pause' === $command ) { $wpdb->update( $t['campaigns'], array( 'status' => 'paused', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		elseif ( 'cancel' === $command ) { $wpdb->update( $t['campaigns'], array( 'status' => 'cancelled', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-campaigns' ) ); exit;
	}

	public static function process_queue( $campaign_id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $campaign = self::campaign( $campaign_id );
		if ( ! $campaign || ! in_array( $campaign['status'], array( 'queued', 'sending', 'scheduled' ), true ) ) { return; }
		if ( 'dynamic' === ( $campaign['audience_mode'] ?? 'dynamic' ) && empty( $campaign['audience_resolved_at'] ) ) {
			self::prepare_recipients( $campaign_id );
			$wpdb->update( $t['campaigns'], array( 'audience_resolved_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$campaign = self::campaign( $campaign_id );
		}
		$wpdb->update( $t['campaigns'], array( 'status' => 'sending', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$mails_per_hour = min( 3000, max( 1, absint( $campaign['mails_per_hour'] ?? 300 ) ) );
		$batch_size = max( 1, (int) ceil( $mails_per_hour / 60 ) );
		$batch_interval = max( 60, (int) ceil( ( 3600 * $batch_size ) / $mails_per_hour ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE campaign_id=%d AND status IN ('queued','retry') AND attempts<3 ORDER BY id ASC LIMIT %d", $t['recipients'], $campaign_id, $batch_size ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$template = ContactPanda_Basic_Newsletters::template( $campaign['template_id'] ); $settings = ContactPanda_Basic_Newsletters::public_settings();
		foreach ( $rows as $row ) {
			$live_contact = ! empty( $row['contact_id'] ) ? ContactPanda_Plugin::extension_contact( absint( $row['contact_id'] ) ) : false;
			if ( ! $live_contact ) { self::recipient_result( $row, 'skipped', 'contact_missing', '' ); continue; }
			if ( 'registered_customers' === ( $campaign['audience_source'] ?? 'contacts' ) && ! self::is_registered_customer( $live_contact ) ) { self::recipient_result( $row, 'skipped', 'customer_missing', '' ); continue; }
			if ( self::has_protected_optout_status( $live_contact ) ) { self::recipient_result( $row, 'skipped', 'protected_optout', '' ); continue; }
			if ( self::is_suppressed( $row['email'] ) ) { self::recipient_result( $row, 'skipped', 'suppressed', '' ); continue; }
			if ( ! ContactPanda_Plugin::extension_is_marketable( $live_contact ) && empty( $campaign['ignore_marketing_consent'] ) ) { self::recipient_result( $row, 'skipped', 'consent_missing', '' ); continue; }
			$url = self::unsubscribe_url( $row ); $html = ContactPanda_Basic_Newsletters::render_for_recipient( $template, $row, $url );
			$html = (string) apply_filters( 'contactpanda_campaign_recipient_html', $html, $row, $campaign );
			$from_email = sanitize_email( $campaign['from_email'] ?? '' ) ?: sanitize_email( $settings['from_email'] ) ?: sanitize_email( get_option( 'admin_email' ) ); $reply_to = sanitize_email( $campaign['reply_to'] ?? '' ) ?: sanitize_email( $settings['reply_to'] ) ?: $from_email;
			$from_name = sanitize_text_field( $campaign['from_name'] ?? '' ) ?: sanitize_text_field( $settings['from_name'] );
			$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from_name . ' <' . $from_email . '>', 'Reply-To: ' . $reply_to );
			$subject_source = (string) apply_filters( 'contactpanda_campaign_subject_source', $campaign['subject'], $campaign, $row );
			$subject = ContactPanda_Basic_Newsletters::personalize_text( $subject_source, $row );
			$sent = wp_mail( $row['email'], $subject, $html, $headers ); $attempts = absint( $row['attempts'] ) + 1;
			self::recipient_result( $row, $sent ? 'sent' : ( $attempts < 3 ? 'retry' : 'failed' ), $sent ? '' : 'wp_mail_failed', $sent ? '' : __( 'WordPress could not hand the message to the configured mail system.', 'contactpanda' ), $attempts );
		}
		self::refresh_counts( $campaign_id ); $remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id=%d AND status IN ('queued','retry') AND attempts<3", $t['recipients'], $campaign_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		if ( $remaining ) { self::schedule( $campaign_id, time() + $batch_interval ); } else { $wpdb->update( $t['campaigns'], array( 'status' => 'completed', 'completed_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $campaign_id ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	private static function recipient_result( $row, $status, $reason, $error = '', $attempts = null ) {
		global $wpdb; $data = array( 'status' => $status, 'reason' => $reason, 'error_message' => $error, 'updated_at' => current_time( 'mysql' ) ); if ( null !== $attempts ) { $data['attempts'] = $attempts; } if ( 'sent' === $status ) { $data['sent_at'] = current_time( 'mysql' ); }
		$wpdb->update( ContactPanda_Newsletter_Schema::table_names()['recipients'], $data, array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	private static function refresh_counts( $id ) {
		global $wpdb; $t = ContactPanda_Newsletter_Schema::table_names(); $counts = array(); foreach ( array( 'queued', 'retry', 'sent', 'failed', 'skipped' ) as $status ) { $counts[ $status ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id=%d AND status=%s", $t['recipients'], $id, $status ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$wpdb->update( $t['campaigns'], array( 'queued_count' => $counts['queued'] + $counts['retry'], 'sent_count' => $counts['sent'], 'failed_count' => $counts['failed'], 'skipped_count' => $counts['skipped'], 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	}

	public static function statuses_ajax() {
		check_ajax_referer( self::ajax_nonce_action(), 'nonce' );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ?? array() ) ) ) ) );
		$ids = array_slice( $ids, 0, 100 );
		$statuses = array();
		foreach ( $ids as $id ) {
			$campaign = self::campaign( $id );
			if ( ! $campaign ) continue;
			global $wpdb;
			$recipients_table = ContactPanda_Newsletter_Schema::table_names()['recipients'];
			$bounced = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id=%d AND status='bounced'", $recipients_table, $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
			$eligible = max( 0, absint( $campaign['total_recipients'] ) - absint( $campaign['skipped_count'] ) );
			$delivered = absint( $campaign['sent_count'] );
			$rate = static function ( $count, $base ) { return $base ? number_format_i18n( ( 100 * $count ) / $base, 1 ) . '%' : '0%'; };
			$processed = absint( $campaign['sent_count'] ) + absint( $campaign['failed_count'] ) + absint( $campaign['skipped_count'] );
			$progress = absint( $campaign['total_recipients'] ) ? min( 100, (int) round( 100 * $processed / absint( $campaign['total_recipients'] ) ) ) : 0;
			if ( 'completed' === $campaign['status'] ) $progress = 100;
			$statuses[ $id ] = array(
				'status'            => sanitize_key( $campaign['status'] ),
				'terminal'          => in_array( $campaign['status'], array( 'completed', 'cancelled' ), true ),
				'sent'              => absint( $campaign['sent_count'] ),
				'failed'            => absint( $campaign['failed_count'] ),
				'total'             => absint( $campaign['total_recipients'] ),
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				'totalText'         => sprintf( __( '%d selected', 'contactpanda' ), absint( $campaign['total_recipients'] ) ),
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				'sentText'          => sprintf( __( '%d sent', 'contactpanda' ), absint( $campaign['sent_count'] ) ),
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				'failedText'        => sprintf( __( '%d failed', 'contactpanda' ), absint( $campaign['failed_count'] ) ),
				/* translators: %d: Number of bounced messages. */
				'bouncedText'       => sprintf( __( '%d bounces', 'contactpanda' ), $bounced ),
				'delivered'         => $delivered . ' · ' . $rate( $delivered, $eligible ),
				'progress'          => $progress,
				/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
				'progressLabel'     => sprintf( __( 'Mailing progress: %d%%', 'contactpanda' ), $progress ),
				'statusLabel'       => self::status_label( $campaign['status'] ),
			);
		}
		wp_send_json_success( array( 'statuses' => $statuses ) );
	}

	public static function customer_search_ajax() {
		check_ajax_referer( self::ajax_nonce_action(), 'nonce' );
		$source = sanitize_key( wp_unslash( $_POST['source'] ?? 'registered_customers' ) );
		if ( ! in_array( $source, array( 'contacts', 'registered_customers', 'all_contacts' ), true ) ) $source = 'registered_customers';
		$query = substr( sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) ), 0, 190 );
		$contacts = ContactPanda_Plugin::extension_contacts();
		if ( 'registered_customers' === $source ) {
			$contacts = self::registered_customer_contacts( $contacts, $query );
		} elseif ( 'contacts' === $source ) {
			$contacts = ! empty( $_POST['segment_id'] ) ? ContactPanda_Basic_Segments::filter_contacts( $contacts, absint( $_POST['segment_id'] ) ) : array();
		}
		$items = array(); $with_consent = 0; $without_consent = 0; $excluded = 0;
		foreach ( $contacts as $contact ) {
			$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
			if ( ! is_email( $email ) || self::has_protected_optout_status( $contact ) || self::is_suppressed( $email ) ) { $excluded++; continue; }
			$name = trim( sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'first_name' ) ) ) . ' ' . sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'last_name' ) ) ) );
			$marketable = ContactPanda_Plugin::extension_is_marketable( $contact );
			$marketable ? $with_consent++ : $without_consent++;
			if ( count( $items ) < 200 ) $items[] = array( 'name' => $name ?: $email, 'email' => $email, 'marketable' => $marketable );
		}
		wp_send_json_success( array( 'items' => $items, 'total' => count( $contacts ), 'eligible' => $with_consent + $without_consent, 'withConsent' => $with_consent, 'withoutConsent' => $without_consent, 'excluded' => $excluded, 'truncated' => ( $with_consent + $without_consent ) > count( $items ) ) );
	}

	public static function preview_ajax() {
		check_ajax_referer( self::ajax_nonce_action(), 'nonce' );
		$template = ContactPanda_Basic_Newsletters::template( absint( $_POST['template_id'] ?? 0 ) );
		if ( ! $template ) wp_send_json_error( array( 'message' => __( 'Please select an email template.', 'contactpanda' ) ), 400 );
		$user = wp_get_current_user();
		$recipient = array( 'contact_id' => 0, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->user_email );
		wp_send_json_success( array( 'html' => ContactPanda_Basic_Newsletters::render_for_recipient( $template, $recipient, '#' ) ) );
	}

	public static function test_ajax() {
		check_ajax_referer( self::ajax_nonce_action(), 'nonce' );
		$template = ContactPanda_Basic_Newsletters::template( absint( $_POST['template_id'] ?? 0 ) );
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$from_name = sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) );
		$from_email = sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) );
		$reply_to = sanitize_email( wp_unslash( $_POST['reply_to'] ?? '' ) );
		if ( ! $template || ! is_email( $email ) || ! $subject || ! $from_name || ! is_email( $from_email ) || ! is_email( $reply_to ) ) wp_send_json_error( array( 'message' => __( 'Select a template and enter valid subject, sender and test recipient details.', 'contactpanda' ) ), 400 );
		$user = wp_get_current_user(); $recipient = array( 'contact_id' => 0, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $email );
		$headers = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from_name . ' <' . $from_email . '>', 'Reply-To: ' . $reply_to );
		$sent = wp_mail( $email, '[TEST] ' . ContactPanda_Basic_Newsletters::personalize_text( $subject, $recipient ), ContactPanda_Basic_Newsletters::render_for_recipient( $template, $recipient, '#' ), $headers );
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		$sent ? wp_send_json_success( array( 'message' => sprintf( __( 'The test email was sent to %s.', 'contactpanda' ), $email ) ) ) : wp_send_json_error( array( 'message' => __( 'WordPress could not send the test email.', 'contactpanda' ) ), 500 );
	}

	public static function unsubscribe() {
		global $wpdb; ContactPanda_Newsletter_Schema::install(); $request = 'POST' === strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ? $_POST : $_GET; $id = absint( $request['recipient'] ?? 0 ); $token = sanitize_text_field( wp_unslash( $request['token'] ?? '' ) ); $t = ContactPanda_Newsletter_Schema::table_names(); $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id=%d", $t['recipients'], $id ), ARRAY_A ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Public unsubscribe link checks the recipient HMAC; POST additionally checks a purpose-specific confirmation HMAC before suppression. Login/session nonces cannot protect emailed links. Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		if ( ! $row || ! hash_equals( self::token( $row ), $token ) ) { wp_die( esc_html__( 'This unsubscribe link is invalid.', 'contactpanda' ), '', array( 'response' => 403 ) ); }
		if ( self::is_suppressed( $row['email'] ) || 'unsubscribed' === ( $row['reason'] ?? '' ) ) {
			self::render_unsubscribe_page( __( 'Already finally unsubscribed', 'contactpanda' ), __( 'This email address has already been finally unsubscribed from the newsletter.', 'contactpanda' ), $row );
		}
		if ( 'POST' !== strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			self::render_unsubscribe_page( __( 'Confirm final unsubscribe', 'contactpanda' ), __( 'Please confirm that you want to finally unsubscribe this address from the newsletter.', 'contactpanda' ), $row, true, $token );
		}
		$confirmation = sanitize_text_field( wp_unslash( $_POST['confirmation'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public unsubscribe link checks the recipient HMAC; POST additionally checks a purpose-specific confirmation HMAC before suppression. Login/session nonces cannot protect emailed links.
		if ( ! hash_equals( self::confirmation_token( $row ), $confirmation ) ) wp_die( esc_html__( 'The unsubscribe confirmation is invalid or expired.', 'contactpanda' ), '', array( 'response' => 403 ) );
		$now = current_time( 'mysql' );
		$wpdb->replace( $t['suppressions'], array( 'contact_id' => $row['contact_id'], 'email_hash' => hash( 'sha256', strtolower( $row['email'] ) ), 'reason' => 'unsubscribed', 'source' => 'newsletter_link', 'created_at' => $now ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$wpdb->update( $t['recipients'], array( 'reason' => 'unsubscribed', 'updated_at' => $now ), array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$unsubscribed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE campaign_id=%d AND reason='unsubscribed'", $t['recipients'], $row['campaign_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		$wpdb->update( $t['campaigns'], array( 'unsubscribed_count' => $unsubscribed, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $row['campaign_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		$unsubscribe_reason = substr( sanitize_textarea_field( wp_unslash( $_POST['unsubscribe_reason'] ?? '' ) ), 0, 1000 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public unsubscribe link checks the recipient HMAC; POST additionally checks a purpose-specific confirmation HMAC before suppression. Login/session nonces cannot protect emailed links.
		if ( $unsubscribe_reason && $row['contact_id'] ) ContactPanda_Plugin::extension_update_custom_meta( 'contacts', $row['contact_id'], array( 'contactpanda_unsubscribe_reason' => $unsubscribe_reason ) );
		/* translators: 1: Email address, 2: Campaign ID, 3: Unsubscribe date and time. */
		ContactPanda_Plugin::extension_unsubscribe_contact( $row['contact_id'], 'newsletter_link', sprintf( __( 'Email address %1$s finally unsubscribed through campaign %2$d at %3$s.', 'contactpanda' ), sanitize_email( $row['email'] ), $row['campaign_id'], $now ), $row['email'] );
		do_action( 'contactpanda_plus_email_event', 'unsubscribe', array( 'contact_id' => absint( $row['contact_id'] ), 'email' => sanitize_email( $row['email'] ), 'campaign_id' => absint( $row['campaign_id'] ), 'source' => 'newsletter_link', 'occurred_at' => $now, 'reason' => $unsubscribe_reason ) );
		self::render_unsubscribe_page( __( 'Finally unsubscribed', 'contactpanda' ), __( 'Your email address has been finally unsubscribed from the newsletter.', 'contactpanda' ), $row );
	}

	private static function render_unsubscribe_page( $title, $message, $row, $show_form = false, $token = '' ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		status_header( 200 );
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$site_url = home_url( '/' );
		$full_name = trim( sanitize_text_field( $row['first_name'] ?? '' ) . ' ' . sanitize_text_field( $row['last_name'] ?? '' ) );
		$logo = '';
		$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
		if ( $custom_logo_id ) {
			$logo = wp_get_attachment_image( $custom_logo_id, 'medium', false, array( 'class' => 'cp-logo', 'alt' => $site_name ) );
		} elseif ( has_site_icon() ) {
			$logo = '<img class="cp-logo cp-site-icon" src="' . esc_url( get_site_icon_url( 192 ) ) . '" alt="' . esc_attr( $site_name ) . '">';
		}
		wp_enqueue_style( 'contactpanda-unsubscribe', CONTACTPANDA_URL . 'assets/contactpanda-unsubscribe.css', array(), CONTACTPANDA_ASSET_VERSION );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic values are escaped; the remaining markup is generated by ContactPanda.
		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>' . esc_html( $title ) . ' – ' . esc_html( $site_name ) . '</title>';
		wp_print_styles( array( 'contactpanda-unsubscribe' ) );
		echo '</head><body><main class="cp-box"><div class="cp-brand">' . wp_kses_post( $logo ) . '<div class="cp-site-name">' . esc_html( $site_name ) . '</div><a class="cp-site-url" href="' . esc_url( $site_url ) . '">' . esc_html( untrailingslashit( $site_url ) ) . '</a></div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p><div class="cp-person"><strong>' . esc_html( $full_name ?: __( 'Newsletter subscriber', 'contactpanda' ) ) . '</strong><span>' . esc_html( sanitize_email( $row['email'] ?? '' ) ) . '</span></div>';
		if ( $show_form ) {
			echo '<div class="cp-actions"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_newsletter_unsubscribe"><input type="hidden" name="recipient" value="' . esc_attr( absint( $row['id'] ?? 0 ) ) . '"><input type="hidden" name="token" value="' . esc_attr( $token ) . '"><input type="hidden" name="confirmation" value="' . esc_attr( self::confirmation_token( $row ) ) . '"><label class="cp-unsubscribe-reason">' . esc_html__( 'Reason for unsubscribing (optional)', 'contactpanda' ) . '<textarea name="unsubscribe_reason" rows="3" maxlength="1000"></textarea></label><button class="cp-button" type="submit">' . esc_html__( 'Finally unsubscribe', 'contactpanda' ) . '</button></form><a class="cp-button is-secondary" href="' . esc_url( $site_url ) . '">' . esc_html__( 'Do not unsubscribe', 'contactpanda' ) . '</a></div>';
		} else {
			echo '<div class="cp-actions"><a class="cp-button is-secondary" href="' . esc_url( $site_url ) . '">' . esc_html__( 'Back to website', 'contactpanda' ) . '</a></div>';
		}
		echo '</main></body></html>';
		exit;
	}

	private static function campaign( $id ) { global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['campaigns']; return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id=%d", $table, absint( $id ) ), ARRAY_A ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	private static function selected_contact_ids_from_request() {
		$ids = array();
		if ( ! empty( $_GET['contact_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only form prefill.
			$ids = explode( ',', sanitize_text_field( wp_unslash( $_GET['contact_ids'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$token = isset( $_GET['people_selection'] ) ? substr( preg_replace( '/[^a-z0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['people_selection'] ) ) ) ), 0, 24 ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque read-only selection token.
		if ( $token ) {
			$state = get_transient( 'contactpanda_people_state_' . get_current_user_id() . '_' . $token );
			if ( is_array( $state ) ) $ids = (array) ( $state['ids'] ?? array() );
		}
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 10000 );
	}
	private static function is_registered_customer( $contact ) {
		$user_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'user_id', 'wp_user_id', 'get_user_id' ) ) );
		if ( $user_id && get_userdata( $user_id ) ) return true;
		$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
		return $email && (bool) get_user_by( 'email', $email );
	}
	private static function registered_customer_contacts( $contacts, $search = '' ) {
		$query = trim( (string) $search );
		$query = function_exists( 'mb_strtolower' ) ? mb_strtolower( $query ) : strtolower( $query );
		$company_map = ContactPanda_Plugin::extension_company_map();
		return array_values( array_filter( (array) $contacts, static function ( $contact ) use ( $query, $company_map ) {
			$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
			if ( ! is_email( $email ) || ! self::is_registered_customer( $contact ) || self::has_protected_optout_status( $contact ) || self::is_suppressed( $email ) ) return false;
			if ( '' === $query ) return true;
			$id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID' ) ) );
			$companies = array();
			foreach ( (array) ( $company_map[ $id ] ?? array() ) as $company ) $companies[] = ContactPanda_Plugin::extension_value( $company, array( 'name', 'company_name', 'get_name' ) );
			$haystack = implode( ' ', array( ContactPanda_Plugin::extension_value( $contact, array( 'first_name' ) ), ContactPanda_Plugin::extension_value( $contact, array( 'last_name' ) ), $email, implode( ' ', $companies ) ) );
			$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
			return false !== strpos( $haystack, $query );
		} ) );
	}
	private static function has_protected_optout_status( $contact ) {
		$status = ContactPanda_Plugin::extension_optin_status( $contact );
		return in_array( $status, array( 3, 6, 7, 8, 9, '3', '6', '7', '8', '9', 'unsubscribed', 'hard_bounce', 'bounced', 'spam', 'complained', 'blocked' ), true );
	}
	private static function override_reason_label( $reason ) {
		$labels = array(
			'personal_decision'        => __( 'Personal decision', 'contactpanda' ),
			'existing_customer'        => __( 'Existing customer relationship', 'contactpanda' ),
			'contractual_information' => __( 'Contractual information', 'contactpanda' ),
			'service_information'     => __( 'Service or existing-customer information', 'contactpanda' ),
			'other'                   => __( 'Other reason', 'contactpanda' ),
		);
		return $labels[ $reason ] ?? __( 'Administrative exception', 'contactpanda' );
	}
	private static function is_suppressed( $email ) { global $wpdb; $table = ContactPanda_Newsletter_Schema::table_names()['suppressions']; return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE email_hash=%s", $table, hash( 'sha256', strtolower( $email ) ) ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Owned ContactPanda tables have no WordPress object API; this repository method uses wpdb CRUD escaping or prepared identifiers/values, with authorization enforced at its entry points. Fresh CRM/recipient/import state is required for the next decision; no persistent result cache is used because consent, suppression, merges and external writers must be observed immediately.
	private static function token( $row ) { return hash_hmac( 'sha256', absint( $row['id'] ) . '|' . absint( $row['campaign_id'] ), wp_salt( 'auth' ) ); }
	private static function confirmation_token( $row ) { return hash_hmac( 'sha256', 'confirm|' . absint( $row['id'] ) . '|' . strtolower( sanitize_email( $row['email'] ) ), wp_salt( 'secure_auth' ) ); }
	private static function unsubscribe_url( $row ) { return add_query_arg( array( 'action' => 'contactpanda_newsletter_unsubscribe', 'recipient' => absint( $row['id'] ), 'token' => self::token( $row ) ), admin_url( 'admin-post.php' ) ); }
	private static function schedule( $id, $timestamp ) { if ( ! wp_next_scheduled( self::CRON_HOOK, array( absint( $id ) ) ) ) { wp_schedule_single_event( $timestamp, self::CRON_HOOK, array( absint( $id ) ) ); } }
	private static function action_link( $id, $command, $label, $class = '' ) { return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=contactpanda_campaign_action&campaign_id=' . absint( $id ) . '&command=' . $command ), 'contactpanda_campaign_action' ) ) . '">' . esc_html( $label ) . '</a>'; }
	private static function status_label( $status ) { $labels = array( 'draft' => __( 'Draft', 'contactpanda' ), 'scheduled' => __( 'Scheduled', 'contactpanda' ), 'queued' => __( 'Queued', 'contactpanda' ), 'sending' => __( 'Sending', 'contactpanda' ), 'paused' => __( 'Paused', 'contactpanda' ), 'completed' => __( 'Completed', 'contactpanda' ), 'cancelled' => __( 'Cancelled', 'contactpanda' ), 'imported' => __( 'Imported archive', 'contactpanda' ) ); return $labels[ $status ] ?? $status; }
	private static function admin_nonce_action( $nonce ) { if ( ! current_user_can( self::capability() ) ) { wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ) ); } return $nonce; }
	private static function ajax_nonce_action() { if ( ! current_user_can( self::capability() ) ) wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'contactpanda' ) ), 403 ); return 'contactpanda_newsletter_editor'; }
	private static function capability() { return class_exists( 'ContactPanda_Capabilities' ) ? ContactPanda_Capabilities::for_area( 'marketing' ) : ( class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' ); }
}
