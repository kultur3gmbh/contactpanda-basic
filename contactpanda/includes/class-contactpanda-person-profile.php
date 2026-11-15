<?php

defined( 'ABSPATH' ) || exit;

/** Canonical personal self-description; available without Plus or WooCommerce. */
final class ContactPanda_Person_Profile {
	const BIOGRAPHY = 'contactpanda_custom_biography';

	public static function boot() {
		add_filter( 'contactpanda_person_detail_tabs', array( __CLASS__, 'tabs' ), 100, 4 );
		add_action( 'contactpanda_render_person_detail_tab_about_me', array( __CLASS__, 'render_about_me' ), 10, 3 );
		add_action( 'admin_post_contactpanda_save_biography', array( __CLASS__, 'save_biography' ) );
		add_action( 'wp_ajax_contactpanda_save_biography', array( __CLASS__, 'save_biography' ) );
		// Prevent duplicate editors while an older Plus is still installed.
		add_action( 'init', array( __CLASS__, 'remove_legacy_renderer' ), 99 );
	}

	public static function remove_legacy_renderer() {
		remove_action( 'contactpanda_render_person_detail_tab_about_me', array( 'ContactPanda_Plus_Donations', 'render_about_me' ), 10 );
	}

	public static function tabs( $tabs ) {
		$tabs['about_me'] = array( 'label' => __( 'About me', 'contactpanda' ), 'position' => 'before_notes' );
		return $tabs;
	}

	/** Sanitized public biography only; no email, phone, notes or tags. */
	public static function biography_html( $contact_id ) {
		$contact = ContactPanda_Plugin::extension_contact( absint( $contact_id ) );
		if ( ! $contact ) return '';
		$value = wp_kses_post( do_shortcode( wpautop( self::biography( $contact ) ) ) );
		$text = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );
		return '' === preg_replace( '/[\s\x{00a0}\x{200b}\x{feff}]+/u', '', $text ) ? '' : $value;
	}
	public static function can_edit() {
		$capability = class_exists( 'ContactPanda_Capabilities' ) ? ContactPanda_Capabilities::for_area( 'contacts' ) : 'manage_options';
		return current_user_can( $capability );
	}

	private static function biography( $contact ) {
		if ( ! class_exists( 'ContactPanda_Plugin' ) || ! is_callable( array( 'ContactPanda_Plugin', 'extension_contact_meta_value' ) ) ) return '';
		$value = ContactPanda_Plugin::extension_contact_meta_value( $contact, self::BIOGRAPHY );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Trusted integration API: callers verify their HTTP nonce before invoking writes. */
	public static function get( $contact_id ) {
		if ( ! current_user_can( ContactPanda_Capabilities::for_area( 'view' ) ) ) return new WP_Error( 'contactpanda_profile_forbidden', __( 'Access denied.', 'contactpanda' ) );
		$contact = ContactPanda_Plugin::extension_contact( absint( $contact_id ) );
		if ( ! $contact ) return new WP_Error( 'contactpanda_profile_missing', __( 'Person not found.', 'contactpanda' ) );
		$result = array( 'api_version' => '1.1', 'id' => absint( $contact_id ) );
		foreach ( array( 'first_name', 'last_name' ) as $key ) $result[ $key ] = ContactPanda_Plugin::extension_value( $contact, array( $key, 'get_' . $key ) );
		foreach ( array( 'company_name', 'website', 'phone', 'biography' ) as $key ) $result[ $key ] = (string) ContactPanda_Plugin::extension_contact_meta_value( $contact, 'contactpanda_custom_' . $key );
		$result['biography'] = wp_kses_post( $result['biography'] );
		return $result;
	}

	public static function update( $contact_id, $values ) {
		if ( ! self::can_edit() ) return new WP_Error( 'contactpanda_profile_forbidden', __( 'Access denied.', 'contactpanda' ) );
		$contact_id = absint( $contact_id );
		if ( ! $contact_id || ! ContactPanda_Plugin::extension_contact( $contact_id ) ) return new WP_Error( 'contactpanda_profile_missing', __( 'Person not found.', 'contactpanda' ) );
		if ( ! is_array( $values ) ) return new WP_Error( 'contactpanda_profile_invalid', __( 'Invalid profile data.', 'contactpanda' ) );
		$core = array(); $meta = array();
		foreach ( $values as $key => $value ) {
			if ( ! in_array( $key, array( 'first_name', 'last_name', 'company_name', 'website', 'phone', 'biography' ), true ) || ! is_string( $value ) ) return new WP_Error( 'contactpanda_profile_invalid', __( 'Invalid profile data.', 'contactpanda' ) );
			if ( in_array( $key, array( 'first_name', 'last_name' ), true ) ) $core[ $key ] = sanitize_text_field( $value );
			else $meta[ 'contactpanda_custom_' . $key ] = 'biography' === $key ? wp_kses_post( $value ) : ( 'website' === $key ? esc_url_raw( $value, array( 'http', 'https' ) ) : sanitize_text_field( $value ) );
		}
		if ( ! $core && ! $meta ) return true;
		if ( ContactPanda_Store::standalone() ) {
			global $wpdb;
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic update of Basic-owned profile fields.
			$ok = ContactPanda_Store::update_contact( $contact_id, array_merge( $core, array( 'meta' => $meta ) ) );
			$wpdb->query( $ok ? 'COMMIT' : 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Complete or undo the same profile update.
		} else {
			$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'groundhogg/update-contact' ) : null;
			$result = $ability ? $ability->execute( array_merge( array( 'id' => $contact_id, 'meta' => $meta ), $core ) ) : false;
			$ok = false !== $result && ! is_wp_error( $result );
		}
		return $ok ? true : new WP_Error( 'contactpanda_profile_save_failed', __( 'The profile could not be saved.', 'contactpanda' ) );
	}

	public static function render_about_me( $contact_id, $contact, $editable ) {
		self::render_biography( $contact_id, $contact, ! $editable, 'tab' );
	}

	public static function render_biography( $contact_id, $contact, $readonly, $placement ) {
		if ( ! in_array( $placement, array( 'tab', 'sidebar' ), true ) ) return;
		$value = self::biography( $contact );
		echo '<section class="contactpanda-profile-description" id="contactpanda-biography-card"><h2>' . esc_html__( 'About me', 'contactpanda' ) . '</h2>';
		if ( ! self::can_edit() || ( $readonly && 'groundhogg' !== ContactPanda_Store::source() ) || 'anzeigen' === ( $_GET['ansicht'] ?? '' ) ) {
			echo $value ? '<div class="contactpanda-formatted-content">' . wp_kses_post( wpautop( $value ) ) . '</div>' : '<p class="contactpanda-muted">' . esc_html__( 'No self-description saved.', 'contactpanda' ) . '</p>';
		} else {
			echo '<form class="contactpanda-biography-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_save_biography"><input type="hidden" name="contact_id" value="' . esc_attr( $contact_id ) . '">';
			wp_nonce_field( 'contactpanda_save_biography_' . absint( $contact_id ) );
			wp_enqueue_script( 'contactpanda-donations', CONTACTPANDA_URL . 'assets/contactpanda-person-profile.js', array( 'editor' ), CONTACTPANDA_VERSION, true );
			wp_localize_script( 'contactpanda-donations', 'CP_PERSON_PROFILE', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'saved' => __( 'Self-description saved.', 'contactpanda' ), 'error' => __( 'The self-description could not be saved.', 'contactpanda' ) ) );
			echo '<label class="screen-reader-text" for="contactpanda-biography">' . esc_html__( 'Self-description', 'contactpanda' ) . '</label>';
			wp_editor( $value, 'contactpanda-biography', array( 'textarea_name' => 'biography', 'textarea_rows' => 8, 'media_buttons' => false, 'quicktags' => false, 'tinymce' => array( 'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,outdent,indent,alignleft,aligncenter,alignright,link,unlink,removeformat,undo,redo', 'toolbar2' => '', 'block_formats' => 'Paragraph=p;Heading 2=h2;Heading 3=h3' ) ) );
			echo '<p class="description">' . esc_html__( 'This text can be shown in a separate PandaWoo product tab.', 'contactpanda' ) . '</p><p role="status" aria-live="polite"></p>';

			echo '<button type="submit" class="button button-primary"><span class="dashicons dashicons-saved" aria-hidden="true"></span>' . esc_html__( 'Save self-description', 'contactpanda' ) . '</button></form>';
		}
		echo '</section>';
	}

	public static function save_biography() {
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		if ( ! $contact_id || ! self::can_edit() ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_save_biography_' . $contact_id );
		$value = is_string( $_POST['biography'] ?? null ) ? wp_kses_post( wp_unslash( $_POST['biography'] ) ) : '';
		if ( ! ContactPanda_Plugin::extension_contact( $contact_id ) ) wp_die( esc_html__( 'Person not found.', 'contactpanda' ), '', array( 'response' => 404 ) );
		$ok = true === self::update( $contact_id, array( 'biography' => $value ) );
		if ( wp_doing_ajax() ) {
			if ( ! $ok ) wp_send_json_error( array( 'message' => __( 'The self-description could not be saved.', 'contactpanda' ) ), 500 );
			wp_send_json_success( array( 'saved' => true ) );
		}
		$url = wp_get_referer();
		if ( ! $url ) $url = add_query_arg( array( 'page' => 'contactpanda-people', 'person' => $contact_id ), admin_url( 'admin.php' ) );
		$url = add_query_arg( 'contactpanda_biography_saved', $ok ? 1 : 0, $url );
		wp_safe_redirect( $url ); exit;
	}

}
