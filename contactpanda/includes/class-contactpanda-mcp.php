<?php

defined( 'ABSPATH' ) || exit;

/**
 * Central MCP server for the complete ContactPanda family.
 *
 * ContactPanda owns authentication, discovery and auditing. Family plugins
 * only add their domain tools through the public filters below. Entitlements
 * protect the domain action, never the existence of the MCP transport.
 */
final class ContactPanda_MCP {
	const ROUTE = 'contactpanda/v1';
	const AUDIT_OPTION = 'contactpanda_mcp_audit';

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_filter( 'contactpanda_integration_sections', array( __CLASS__, 'integration_section' ) );
		add_action( 'contactpanda_render_integration_section_mcp', array( __CLASS__, 'render_settings' ) );
	}

	public static function register_route() {
		register_rest_route(
			self::ROUTE,
			'/mcp',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'allowed' ),
			)
		);
	}

	public static function allowed() {
		$allowed = current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
		return (bool) apply_filters( 'contactpanda_mcp_allowed', $allowed, wp_get_current_user() );
	}

	public static function integration_section( $sections ) {
		$sections['mcp'] = __( 'MCP interface', 'contactpanda' );
		return $sections;
	}

	public static function render_settings() {
		$endpoint = rest_url( self::ROUTE . '/mcp' );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'MCP interface', 'contactpanda' ) . '</h2><p>' . esc_html__( 'This central interface provides the available ContactPanda tools from ContactPanda and every active family plugin. Additional actions appear when the corresponding extension is installed and active.', 'contactpanda' ) . '</p><label><span>' . esc_html__( 'MCP endpoint', 'contactpanda' ) . '</span><input class="large-text code" readonly value="' . esc_attr( $endpoint ) . '"></label><p class="description">' . esc_html__( 'Use a dedicated WordPress application password for an authorized administrator or shop manager. Revoke that password to disable access immediately.', 'contactpanda' ) . '</p></section>';
		self::render_audit();
	}

	public static function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) return self::error( null, -32700, 'Parse error' );
		$id = $payload['id'] ?? null;
		$method = sanitize_text_field( $payload['method'] ?? '' );
		if ( 'initialize' === $method ) {
			return self::result( $id, array( 'protocolVersion' => '2025-03-26', 'capabilities' => array( 'tools' => (object) array( 'listChanged' => false ) ), 'serverInfo' => array( 'name' => 'ContactPanda', 'version' => CONTACTPANDA_VERSION ), 'instructions' => __( 'Use read tools freely. Creating or sending data requires explicit confirmation and the relevant ContactPanda permission.', 'contactpanda' ) ) );
		}
		if ( 'ping' === $method ) return self::result( $id, (object) array() );
		if ( 'notifications/initialized' === $method ) return new WP_REST_Response( null, 202 );
		if ( 'tools/list' === $method ) return self::result( $id, array( 'tools' => self::registered_tools() ) );
		if ( 'tools/call' !== $method ) return self::error( $id, -32601, 'Method not found' );
		$params = is_array( $payload['params'] ?? null ) ? $payload['params'] : array();
		$name = sanitize_key( $params['name'] ?? '' );
		$arguments = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array();
		try {
			$outcome = self::call_core_tool( $name, $arguments );
			if ( null === $outcome ) $outcome = apply_filters( 'contactpanda_mcp_tool_call', null, $name, $arguments );
			// Compatibility for extensions released before the central MCP server.
			if ( null === $outcome ) $outcome = apply_filters( 'contactpanda_plus_mcp_tool_call', null, $name, $arguments );
			if ( null === $outcome ) return self::error( $id, -32602, 'Unknown tool' );
			if ( is_wp_error( $outcome ) ) {
				self::audit( $name, false );
				return self::result( $id, array( 'isError' => true, 'content' => array( array( 'type' => 'text', 'text' => $outcome->get_error_message() ) ) ) );
			}
			self::audit( $name, true );
			return self::result( $id, array( 'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ), 'structuredContent' => $outcome ) );
		} catch ( Throwable $error ) {
			self::audit( $name, false );
			return self::result( $id, array( 'isError' => true, 'content' => array( array( 'type' => 'text', 'text' => __( 'The requested action could not be completed.', 'contactpanda' ) ) ) ) );
		}
	}

	private static function registered_tools() {
		$tools = apply_filters( 'contactpanda_mcp_tools', self::core_tools() );
		$tools = apply_filters( 'contactpanda_plus_mcp_tools', $tools );
		$indexed = array();
		foreach ( (array) $tools as $tool ) {
			if ( empty( $tool['name'] ) ) continue;
			$indexed[ sanitize_key( $tool['name'] ) ] = $tool;
		}
		return array_values( $indexed );
	}

	private static function core_tools() {
		return array(
			array( 'name' => 'contactpanda_contacts_search', 'description' => __( 'Search people in the active ContactPanda data source.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ) ) ) ),
			array( 'name' => 'contactpanda_contact_get', 'description' => __( 'Read one person from the active ContactPanda data source.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'contact_id' ), 'properties' => array( 'contact_id' => array( 'type' => 'integer', 'minimum' => 1 ) ) ) ),
			array( 'name' => 'contactpanda_contact_create', 'description' => __( 'Create an unconfirmed person. The confirm argument must be true.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'first_name', 'email', 'confirm' ), 'properties' => array( 'first_name' => array( 'type' => 'string' ), 'last_name' => array( 'type' => 'string' ), 'email' => array( 'type' => 'string' ), 'confirm' => array( 'type' => 'boolean' ) ) ) ),
			array( 'name' => 'contactpanda_companies_search', 'description' => __( 'Search companies in the active ContactPanda data source.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ) ) ) ),
			array( 'name' => 'contactpanda_tags_list', 'description' => __( 'List ContactPanda tags.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'properties' => (object) array() ) ),
			array( 'name' => 'contactpanda_email_templates_list', 'description' => __( 'List ContactPanda email drafts and templates.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'properties' => (object) array() ) ),
			array( 'name' => 'contactpanda_email_template_create_draft', 'description' => __( 'Create an editable ContactPanda email draft. The confirm argument must be true.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'name', 'subject', 'confirm' ), 'properties' => array( 'name' => array( 'type' => 'string' ), 'subject' => array( 'type' => 'string' ), 'preview_text' => array( 'type' => 'string' ), 'content' => array( 'type' => 'string' ), 'confirm' => array( 'type' => 'boolean' ) ) ) ),
			array( 'name' => 'contactpanda_newsletter_audience_preview', 'description' => __( 'Count eligible and excluded newsletter recipients without returning personal data.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'segment_id' => array( 'type' => 'integer', 'minimum' => 0 ) ) ) ),
			array( 'name' => 'contactpanda_email_test_send', 'description' => __( 'Send one explicit newsletter test email. The confirm argument must be true.', 'contactpanda' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'template_id', 'recipient', 'confirm' ), 'properties' => array( 'template_id' => array( 'type' => 'integer' ), 'recipient' => array( 'type' => 'string' ), 'confirm' => array( 'type' => 'boolean' ) ) ) ),
		);
	}

	private static function call_core_tool( $name, $arguments ) {
		if ( 'contactpanda_contacts_search' === $name ) return self::contacts( $arguments );
		if ( 'contactpanda_contact_get' === $name ) return self::contact( absint( $arguments['contact_id'] ?? 0 ) );
		if ( 'contactpanda_contact_create' === $name ) return self::create_contact( $arguments );
		if ( 'contactpanda_companies_search' === $name ) return self::companies( $arguments );
		if ( 'contactpanda_tags_list' === $name ) return self::tags();
		if ( 'contactpanda_email_templates_list' === $name ) return self::templates();
		if ( 'contactpanda_email_template_create_draft' === $name ) return self::create_template_draft( $arguments );
		if ( 'contactpanda_newsletter_audience_preview' === $name ) return self::audience( absint( $arguments['segment_id'] ?? 0 ) );
		if ( 'contactpanda_email_test_send' === $name ) return self::test_send( $arguments );
		return null;
	}

	private static function contacts( $args ) {
		$rows = ContactPanda_Plugin::extension_contacts( sanitize_text_field( $args['search'] ?? '' ) );
		$limit = min( 200, max( 1, absint( $args['limit'] ?? 50 ) ) );
		return array( 'contacts' => array_map( array( __CLASS__, 'contact_row' ), array_slice( $rows, 0, $limit ) ) );
	}

	private static function contact( $id ) {
		$contact = $id ? ContactPanda_Plugin::extension_contact( $id ) : false;
		return $contact ? array( 'contact' => self::contact_row( $contact ) ) : new WP_Error( 'contact_missing', __( 'The person does not exist.', 'contactpanda' ) );
	}

	private static function contact_row( $contact ) {
		return array(
			'id'               => absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) ),
			'first_name'       => sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'first_name', 'get_first_name' ) ) ),
			'last_name'        => sanitize_text_field( ContactPanda_Plugin::extension_value( $contact, array( 'last_name', 'get_last_name' ) ) ),
			'email'            => sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) ),
			'marketing_allowed'=> (bool) ContactPanda_Plugin::extension_is_marketable( $contact ),
		);
	}

	private static function create_contact( $args ) {
		if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', __( 'Set confirm to true to create the person.', 'contactpanda' ) );
		$id = ContactPanda_Plugin::extension_create_contact( array( 'first_name' => sanitize_text_field( $args['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $args['last_name'] ?? '' ), 'email' => sanitize_email( $args['email'] ?? '' ) ) );
		if ( is_wp_error( $id ) ) return $id;
		return array( 'contact_id' => absint( $id ), 'marketing_allowed' => false, 'created_or_found' => true );
	}

	private static function companies( $args ) {
		$needle = strtolower( sanitize_text_field( $args['search'] ?? '' ) );
		$limit = min( 200, max( 1, absint( $args['limit'] ?? 50 ) ) );
		$rows = array();
		foreach ( ContactPanda_Plugin::extension_companies() as $company ) {
			$name = sanitize_text_field( ContactPanda_Plugin::extension_value( $company, array( 'name', 'company_name', 'get_name' ) ) );
			if ( $needle && false === strpos( strtolower( $name ), $needle ) ) continue;
			$rows[] = array( 'id' => absint( ContactPanda_Plugin::extension_value( $company, array( 'id', 'ID', 'get_id' ) ) ), 'name' => $name, 'email' => sanitize_email( ContactPanda_Plugin::extension_value( $company, array( 'email' ) ) ) );
			if ( count( $rows ) >= $limit ) break;
		}
		return array( 'companies' => $rows );
	}

	private static function tags() {
		$rows = array();
		foreach ( ContactPanda_Plugin::extension_tags() as $tag ) $rows[] = array( 'id' => absint( ContactPanda_Plugin::extension_value( $tag, array( 'id', 'ID', 'tag_id' ) ) ), 'name' => sanitize_text_field( ContactPanda_Plugin::extension_value( $tag, array( 'name', 'tag_name' ) ) ) );
		return array( 'tags' => $rows );
	}

	private static function templates() {
		$rows = array();
		foreach ( (array) ContactPanda_Basic_Newsletters::templates() as $template ) $rows[] = array( 'id' => absint( $template['id'] ?? 0 ), 'name' => sanitize_text_field( $template['name'] ?? '' ), 'subject' => sanitize_text_field( $template['subject'] ?? '' ), 'status' => sanitize_key( $template['status'] ?? '' ), 'updated_at' => sanitize_text_field( $template['updated_at'] ?? '' ) );
		return array( 'templates' => $rows );
	}

	private static function create_template_draft( $args ) {
		if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', __( 'Set confirm to true to create the email draft.', 'contactpanda' ) );
		$name = sanitize_text_field( $args['name'] ?? '' );
		$subject = sanitize_text_field( $args['subject'] ?? '' );
		if ( '' === $name || '' === $subject ) return new WP_Error( 'template_fields_required', __( 'Template name and subject are required.', 'contactpanda' ) );
		global $wpdb;
		$installed = ContactPanda_Newsletter_Schema::install_newsletters();
		if ( is_wp_error( $installed ) ) return $installed;
		$settings = ContactPanda_Basic_Newsletters::settings();
		$content = wp_kses_post( $args['content'] ?? '' );
		$blocks = array();
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) $blocks[] = array( 'type' => 'text', 'content' => $content );
		$now = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			ContactPanda_Newsletter_Schema::newsletter_table_names()['templates'],
			array(
				'name' => $name, 'subject' => $subject, 'preview_text' => sanitize_text_field( $args['preview_text'] ?? '' ),
				'from_name' => sanitize_text_field( $settings['from_name'] ?? '' ), 'reply_to' => sanitize_email( $settings['reply_to'] ?? '' ),
				'status' => 'draft', 'content_json' => wp_json_encode( $blocks ), 'content_html' => $content,
				'email_background' => sanitize_text_field( $settings['background_color'] ?? '#f4f5f7' ), 'accent_color' => sanitize_text_field( $settings['base_color'] ?? '#3858e9' ),
				'content_background' => sanitize_text_field( $settings['body_color'] ?? '#ffffff' ), 'text_color' => sanitize_text_field( $settings['text_color'] ?? '#172033' ),
				'font_family' => sanitize_text_field( $settings['font_family'] ?? 'Arial, sans-serif' ), 'content_width' => absint( $settings['container_width'] ?? 640 ),
				'created_at' => $now, 'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( false === $inserted ) return new WP_Error( 'template_create_failed', __( 'The email draft could not be created.', 'contactpanda' ) );
		$id = absint( $wpdb->insert_id );
		return array( 'template_id' => $id, 'status' => 'draft', 'edit_url' => admin_url( 'admin.php?page=contactpanda-newsletters&action=edit&template_id=' . $id ) );
	}

	private static function audience( $segment_id ) {
		$contacts = ContactPanda_Plugin::extension_contacts();
		if ( $segment_id ) {
			if ( ! ContactPanda_Basic_Segments::get( $segment_id ) ) return new WP_Error( 'segment_missing', __( 'The saved search does not exist.', 'contactpanda' ) );
			$contacts = ContactPanda_Basic_Segments::filter_contacts( $contacts, $segment_id );
		}
		$counts = array( 'total' => count( $contacts ), 'eligible' => 0, 'invalid_email' => 0, 'consent_missing' => 0 );
		foreach ( $contacts as $contact ) {
			$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
			if ( ! is_email( $email ) ) ++$counts['invalid_email'];
			elseif ( ! ContactPanda_Plugin::extension_is_marketable( $contact ) ) ++$counts['consent_missing'];
			else ++$counts['eligible'];
		}
		return $counts;
	}

	private static function test_send( $args ) {
		if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', __( 'Set confirm to true to send the test email.', 'contactpanda' ) );
		$recipient = sanitize_email( $args['recipient'] ?? '' );
		if ( ! is_email( $recipient ) ) return new WP_Error( 'invalid_recipient', __( 'Enter a valid test recipient.', 'contactpanda' ) );
		$template = ContactPanda_Basic_Newsletters::template( absint( $args['template_id'] ?? 0 ) );
		if ( ! $template ) return new WP_Error( 'template_missing', __( 'The email template does not exist.', 'contactpanda' ) );
		$html = ContactPanda_Basic_Newsletters::render_for_recipient( $template, array( 'email' => $recipient, 'first_name' => '', 'last_name' => '' ), home_url( '/' ) );
		$sent = wp_mail( $recipient, '[TEST] ' . sanitize_text_field( $template['subject'] ?? '' ), $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
		return $sent ? array( 'sent' => true, 'recipient' => $recipient ) : new WP_Error( 'send_failed', __( 'WordPress could not send the test email.', 'contactpanda' ) );
	}

	private static function audit( $tool, $success ) {
		$log = (array) get_option( self::AUDIT_OPTION, array() );
		$log[] = array( 'time' => current_time( 'mysql', true ), 'user_id' => get_current_user_id(), 'tool' => sanitize_key( $tool ), 'success' => (bool) $success );
		update_option( self::AUDIT_OPTION, array_slice( $log, -200 ), false );
	}

	private static function render_audit() {
		$log = array_reverse( (array) get_option( self::AUDIT_OPTION, array() ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'Recent MCP activity', 'contactpanda' ) . '</h2>';
		if ( ! $log ) echo '<p>' . esc_html__( 'No MCP activity has been recorded yet.', 'contactpanda' ) . '</p>';
		else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time', 'contactpanda' ) . '</th><th>' . esc_html__( 'Tool', 'contactpanda' ) . '</th><th>' . esc_html__( 'Result', 'contactpanda' ) . '</th></tr></thead><tbody>';
			foreach ( array_slice( $log, 0, 25 ) as $entry ) echo '<tr><td>' . esc_html( $entry['time'] ?? '' ) . '</td><td><code>' . esc_html( $entry['tool'] ?? '' ) . '</code></td><td>' . esc_html( ! empty( $entry['success'] ) ? __( 'Successful', 'contactpanda' ) : __( 'Failed', 'contactpanda' ) ) . '</td></tr>';
			echo '</tbody></table>';
		}
		echo '</section>';
	}

	private static function result( $id, $result ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ) ); }
	private static function error( $id, $code, $message ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) ) ); }
}
