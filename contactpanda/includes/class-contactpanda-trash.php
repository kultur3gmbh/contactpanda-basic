<?php

defined( 'ABSPATH' ) || exit;

/** Lifecycle operations are deliberately limited to the standalone ContactPanda tables. */
final class ContactPanda_Trash {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_post_contactpanda_record_action', array( __CLASS__, 'handle' ) );
	}

	public static function available() {
		return current_user_can( 'manage_options' ) && ContactPanda_Store::standalone();
	}

	public static function menu() {
		add_submenu_page( 'contactpanda', __( 'Trash', 'contactpanda' ), __( 'Trash', 'contactpanda' ), 'manage_options', 'contactpanda-trash', array( __CLASS__, 'render' ) );
	}

	private static function valid_entity( $entity ) {
		return in_array( $entity, array( 'contacts', 'companies' ), true );
	}

	public static function url( $entity, $id = 0, $trashed = false ) {
		return add_query_arg( array( 'page' => 'contactpanda-trash', 'entity' => $entity, 'record' => absint( $id ), 'trashed' => $trashed ? 1 : 0 ), admin_url( 'admin.php' ) );
	}

	/** Read raw local rows, including deleted_at, without loading any external objects. */
	public static function records( $entity, $trashed, $page = 1, $search = '', $id = 0 ) {
		global $wpdb;
		if ( ! self::available() || ! self::valid_entity( $entity ) ) return array( 'rows' => array(), 'total' => 0 );
		$table = ContactPanda_Schema::table_names()[ $entity ];
		$where = $trashed ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
		$params = array();
		if ( $id ) { $where .= ' AND id = %d'; $params[] = absint( $id ); }
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( 'contacts' === $entity ) { $where .= ' AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)'; $params = array_merge( $params, array( $like, $like, $like ) ); }
			else { $where .= ' AND name LIKE %s'; $params[] = $like; }
		}
		$count = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
		$total = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count, $params ) : $count ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- entity table is selected from a fixed allowlist; values are prepared when present.
		$sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( 50, ( max( 1, absint( $page ) ) - 1 ) * 50 ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- entity table is selected from a fixed allowlist; all values are prepared.
		return array( 'rows' => (array) $rows, 'total' => $total );
	}

	/** No source-system API, deletion hook, or extension table is invoked here. */
	public static function change( $entity, $id, $operation, $confirmed = false ) {
		global $wpdb;
		if ( ! self::available() || ! self::valid_entity( $entity ) || ! absint( $id ) || true !== $confirmed || ! in_array( $operation, array( 'trash', 'restore', 'purge' ), true ) ) return false;
		$id = absint( $id );
		$tables = ContactPanda_Schema::table_names();
		$table = $tables[ $entity ];
		$state = 'trash' === $operation ? 'IS NULL' : 'IS NOT NULL';
		if ( 'purge' !== $operation ) {
			$now = current_time( 'mysql' );
			$sql = 'restore' === $operation
				? $wpdb->prepare( "UPDATE `{$table}` SET deleted_at = NULL, updated_at = %s WHERE id = %d AND deleted_at {$state}", $now, $id )
				: $wpdb->prepare( "UPDATE `{$table}` SET deleted_at = %s, updated_at = %s WHERE id = %d AND deleted_at {$state}", $now, $now, $id );
			return 1 === $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- query was prepared above; entity table is selected from a fixed allowlist.
		}

		$key = 'contacts' === $entity ? 'contact_id' : 'company_id';
		$owned = 'contacts' === $entity ? array( 'contactmeta', 'contact_tags', 'consents', 'company_contacts' ) : array( 'companymeta', 'company_contacts' );
		// Refuse partial cleanup on non-transactional installations.
		foreach ( array_merge( array( $entity, 'activities' ), $owned ) as $name ) {
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $tables[ $name ] ) );
			if ( 'INNODB' !== strtoupper( (string) $engine ) ) return false;
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) return false;
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE id = %d AND deleted_at IS NOT NULL FOR UPDATE", $id ), ARRAY_A );
		if ( ! $record ) { $wpdb->query( 'ROLLBACK' ); return false; }
		foreach ( $owned as $name ) {
			if ( false === $wpdb->delete( $tables[ $name ], array( $key => $id ) ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		}
		// Keep activities (including imported order/event references); detach only this local link.
		if ( false === $wpdb->update( $tables['activities'], array( $key => null ), array( $key => $id ) ) || 1 !== $wpdb->delete( $table, array( 'id' => $id ) ) ) {
			$wpdb->query( 'ROLLBACK' ); return false;
		}
		if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		return true;
	}

	public static function handle() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) || ! self::available() ) wp_die( esc_html__( 'This action requires an administrator and standalone ContactPanda mode.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_record_action' );
		$entity = isset( $_POST['entity'] ) && is_string( $_POST['entity'] ) ? sanitize_key( wp_unslash( $_POST['entity'] ) ) : '';
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_values( array_unique( array_filter( array_map( static function ( $id ) { return is_scalar( $id ) && preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ? absint( $id ) : 0; }, $_POST['ids'] ) ) ) ) : array();
		if ( '1' === ( $_POST['direct_confirmed'] ?? '' ) && ! empty( $_POST['direct_id'] ) ) {
			$ids = array( absint( $_POST['direct_id'] ) );
			$operation = sanitize_key( wp_unslash( $_POST['direct_operation'] ?? '' ) );
			$_POST['confirmed'] = '1';
		}
		if ( ! self::valid_entity( $entity ) || ! in_array( $operation, array( 'trash', 'restore', 'purge' ), true ) || ! $ids || count( $ids ) > 50 || ( 'purge' === $operation && '1' !== ( $_POST['confirmed'] ?? '' ) ) ) wp_die( esc_html__( 'Select records and explicitly confirm permanent deletion.', 'contactpanda' ), '', array( 'response' => 400 ) );
		$changed = 0;
		foreach ( $ids as $id ) if ( self::change( $entity, $id, $operation, true ) ) $changed++;
		$redirect = self::url( $entity, 0, 'trash' !== $operation );
		if ( 'trash' === $operation && ! empty( $_POST['return_url'] ) ) {
			$candidate = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['return_url'] ) ), '' );
			if ( $candidate && 0 === strpos( $candidate, admin_url() ) ) $redirect = remove_query_arg( array( 'changed', 'failed' ), $candidate );
		}
		wp_safe_redirect( add_query_arg( array( 'changed' => $changed, 'failed' => count( $ids ) - $changed ), $redirect ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'This action requires an administrator and standalone ContactPanda mode.', 'contactpanda' ) );
		echo '<div class="wrap"><h1>' . esc_html__( 'Trash', 'contactpanda' ) . '</h1>';
		if ( ! self::available() ) { echo '<p>' . esc_html__( 'Trash is available only for standalone ContactPanda records. Groundhogg records cannot be deleted here.', 'contactpanda' ) . '</p></div>'; return; }
		$entity = isset( $_GET['entity'] ) && 'companies' === $_GET['entity'] ? 'companies' : 'contacts';
		$trashed = true;
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$id = isset( $_GET['record'] ) ? absint( $_GET['record'] ) : 0;
		$search = isset( $_GET['s'] ) && is_string( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		echo '<p>' . esc_html__( 'Only ContactPanda records are affected. WordPress users, orders, invoices, events, Groundhogg records and external source data are never deleted.', 'contactpanda' ) . '</p>';
		/* translators: Placeholder values are replaced with the record-specific values shown in this message. */
		if ( isset( $_GET['changed'], $_GET['failed'] ) ) echo '<div class="notice notice-info"><p>' . esc_html( sprintf( __( 'Changed: %1$d. Failed or no longer in the required state: %2$d.', 'contactpanda' ), absint( $_GET['changed'] ), absint( $_GET['failed'] ) ) ) . '</p></div>';
		echo '<p class="description">' . esc_html__( 'Move records to Trash from the People or Companies list. This page only restores or permanently deletes records that are already in Trash.', 'contactpanda' ) . '</p>';
		$result = self::records( $entity, $trashed, $page, $search, $id );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_record_action"><input type="hidden" name="entity" value="' . esc_attr( $entity ) . '">';
		wp_nonce_field( 'contactpanda_record_action' );
		echo '<div class="contactpanda-trash-toolbar"><span class="contactpanda-trash-filters">';
		foreach ( array( 'contacts' => __( 'People', 'contactpanda' ), 'companies' => __( 'Companies', 'contactpanda' ) ) as $key => $label ) echo '<a class="button' . ( $entity === $key ? ' is-active' : '' ) . '" href="' . esc_url( self::url( $key, 0, $trashed ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</span><label><span class="screen-reader-text">' . esc_html__( 'Bulk actions', 'contactpanda' ) . '</span><select name="operation">';
		$operations = array( 'restore' => __( 'Restore', 'contactpanda' ), 'purge' => __( 'Delete permanently', 'contactpanda' ) );
		$selected_operation = isset( $_GET['operation'] ) && is_string( $_GET['operation'] ) ? sanitize_key( wp_unslash( $_GET['operation'] ) ) : key( $operations );
		foreach ( $operations as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . ( $selected_operation === $key ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><button class="button" data-trash-apply>' . esc_html__( 'Apply', 'contactpanda' ) . '</button><span class="contactpanda-trash-search"><input type="search" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search', 'contactpanda' ) . '" data-trash-search><button type="button" class="button" data-trash-search-submit>' . esc_html__( 'Search', 'contactpanda' ) . '</button></span></div><p class="contactpanda-trash-confirm"><label><input type="checkbox" name="confirmed" value="1" data-trash-confirm> ' . esc_html__( 'I explicitly confirm the selected action.', 'contactpanda' ) . '</label><span class="description"> ' . esc_html__( 'Required only for permanent deletion.', 'contactpanda' ) . '</span></p>';
		echo '<table class="widefat striped"><thead><tr><th>' . '<label><input type="checkbox" class="contactpanda-trash-select-all"><span class="screen-reader-text">' . esc_html__( 'Select all records on this page', 'contactpanda' ) . '</span></label></th><th>' . esc_html__( 'Name', 'contactpanda' ) . '</th><th>' . esc_html__( 'Deleted at', 'contactpanda' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda' ) . '</th></tr></thead><tbody>';
		foreach ( $result['rows'] as $row ) {
			$name = 'contacts' === $entity ? trim( $row['first_name'] . ' ' . $row['last_name'] ) . ' (' . $row['email'] . ')' : $row['name'];
			echo '<tr><td><label><input type="checkbox" name="ids[]" value="' . esc_attr( $row['id'] ) . '"' . ( $id ? ' checked' : '' ) . '><span class="screen-reader-text">' . esc_html( $name ) . '</span></label></td><td>' . esc_html( $name ) . ' (#' . esc_html( $row['id'] ) . ')</td><td>' . esc_html( $row['deleted_at'] ?: '—' ) . '</td><td>';
			foreach ( $operations as $operation => $label ) {
				echo '<button type="button" class="button contactpanda-trash-direct" data-record-id="' . esc_attr( $row['id'] ) . '" data-operation="' . esc_attr( $operation ) . '" data-record-name="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</button> ';
			}
			echo '</td></tr>';
		}
		if ( ! $result['rows'] ) echo '<tr><td colspan="4">' . esc_html__( 'No records found.', 'contactpanda' ) . '</td></tr>';
		echo '</tbody></table></form><p>';
		$base = add_query_arg( array( 's' => $search, 'record' => $id ), self::url( $entity, 0, $trashed ) );
		if ( $page > 1 ) echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $page - 1, $base ) ) . '">' . esc_html__( 'Previous page', 'contactpanda' ) . '</a> ';
		if ( $page * 50 < $result['total'] ) echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $page + 1, $base ) ) . '">' . esc_html__( 'Next page', 'contactpanda' ) . '</a>';
		echo '</p></div>';
	}
}

