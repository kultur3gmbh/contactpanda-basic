<?php

defined( 'ABSPATH' ) || exit;

/** Shared visual frame for transactional emails sent by the ContactPanda family. */
final class ContactPanda_System_Emails {
	const OPTION = 'contactpanda_system_email_template';
	private static $sending = false;

	public static function boot() {
		add_action( 'admin_post_contactpanda_save_system_email_template', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_contactpanda_test_system_email_template', array( __CLASS__, 'send_test' ) );
		add_filter( 'wp_mail', array( __CLASS__, 'filter_wordpress_mail' ), 20 );
	}

	public static function defaults() {
		return array(
			'logo_url'         => '',
			'background_color' => '#f3f5f7',
			'content_color'    => '#ffffff',
			'text_color'       => '#1d2327',
			'accent_color'     => '#3858e9',
			'font_family'      => 'Arial, Helvetica, sans-serif',
			'header_text'      => '',
			'signature'        => '',
			'footer_text'      => '',
			'show_company'     => 1,
			'logo_position'    => 'center',
			'header_color'     => '#ffffff',
			'footer_color'     => '#f3f5f7',
			'footer_text_color'=> '#646970',
			'apply_wordpress'  => 0,
			'header_blocks'    => array( array( 'type' => 'image', 'url' => '' ) ),
			'footer_blocks'    => array( array( 'type' => 'text', 'content' => '' ) ),
		);
	}

	public static function settings() {
		$stored = (array) get_option( self::OPTION, array() );
		if ( ! self::customisation_available() ) {
			$stored = array();
		}
		$settings = wp_parse_args( $stored, self::defaults() );
		if ( ! array_key_exists( 'header_blocks', $stored ) ) {
			$settings['header_blocks'] = array( array( 'type' => 'image', 'url' => '' ) );
			if ( ! empty( $stored['header_text'] ) ) $settings['header_blocks'][] = array( 'type' => 'text', 'content' => $stored['header_text'] );
		}
		if ( ! array_key_exists( 'footer_blocks', $stored ) ) {
			$settings['footer_blocks'] = array();
			if ( ! empty( $stored['signature'] ) ) $settings['footer_blocks'][] = array( 'type' => 'text', 'content' => $stored['signature'] );
			if ( ! empty( $stored['footer_text'] ) ) $settings['footer_blocks'][] = array( 'type' => 'text', 'content' => $stored['footer_text'] );
		}
		return $settings;
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		$settings = self::settings();
		$profile  = class_exists( 'ContactPanda_Company_Profile' ) ? ContactPanda_Company_Profile::get() : array();
		if ( isset( $_GET['system_email_saved'] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'System email template saved.', 'contactpanda' ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'System email design', 'contactpanda' ) . '</h2><p>' . esc_html__( 'This shared frame is used for transactional ContactPanda messages such as confirmations, account messages, form notifications, support replies, project notices, and automation administration messages. Newsletters keep their own design.', 'contactpanda' ) . '</p>';
		if ( ! self::customisation_available() ) {
			echo '<div class="contactpanda-system-email-preview">' . self::render( __( 'Your message subject', 'contactpanda' ), '<h2>' . esc_html__( 'Hello!', 'contactpanda' ) . '</h2><p>' . esc_html__( 'The actual system message is inserted here automatically.', 'contactpanda' ) . '</p>' ) . '</div>';
			echo '<div class="contactpanda-plus-promotion"><p><strong>' . esc_html__( 'Individual email headers and footers are available with ContactPanda Plus.', 'contactpanda' ) . '</strong> ' . esc_html__( 'Plus adds a visual composer for images, formatted text, separators, multiple rows, and reusable header and footer layouts.', 'contactpanda' ) . '</p><p><a class="button button-primary" href="' . esc_url( apply_filters( 'contactpanda_plus_url', 'https://contactpanda.com/', 'system-emails' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Upgrade to ContactPanda Plus', 'contactpanda' ) . '</a></p></div></section>';
			return;
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-system-email-settings"><input type="hidden" name="action" value="contactpanda_save_system_email_template">';
		wp_nonce_field( 'contactpanda_save_system_email_template' );
		echo '<div class="contactpanda-system-email-grid"><div class="contactpanda-system-email-controls">';
		echo '<label><span>' . esc_html__( 'Logo', 'contactpanda' ) . '</span><span class="contactpanda-image-picker-source"><input type="url" name="logo_url" value="' . esc_attr( $settings['logo_url'] ?: ( $profile['logo_url'] ?? '' ) ) . '" data-contactpanda-image-url></span></label>';
		echo '<label><span>' . esc_html__( 'Logo position', 'contactpanda' ) . '</span><select name="logo_position"><option value="left"' . selected( $settings['logo_position'], 'left', false ) . '>' . esc_html__( 'Left', 'contactpanda' ) . '</option><option value="center"' . selected( $settings['logo_position'], 'center', false ) . '>' . esc_html__( 'Centered', 'contactpanda' ) . '</option><option value="right"' . selected( $settings['logo_position'], 'right', false ) . '>' . esc_html__( 'Right', 'contactpanda' ) . '</option></select></label>';
		self::color( 'background_color', __( 'Email background', 'contactpanda' ), $settings );
		self::color( 'content_color', __( 'Content background', 'contactpanda' ), $settings );
		self::color( 'header_color', __( 'Header background', 'contactpanda' ), $settings );
		self::color( 'footer_color', __( 'Footer background', 'contactpanda' ), $settings );
		self::color( 'footer_text_color', __( 'Footer text color', 'contactpanda' ), $settings );
		self::color( 'text_color', __( 'Text color', 'contactpanda' ), $settings );
		self::color( 'accent_color', __( 'Button and link color', 'contactpanda' ), $settings );
		echo '<label><span>' . esc_html__( 'Font family', 'contactpanda' ) . '</span><select name="font_family">';
		foreach ( array( 'Arial, Helvetica, sans-serif' => 'Arial', 'Verdana, Geneva, sans-serif' => 'Verdana', 'Georgia, Times, serif' => 'Georgia', 'system-ui, sans-serif' => __( 'Theme or system font', 'contactpanda' ) ) as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $settings['font_family'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label>';
		echo '<div class="contactpanda-system-email-composer" data-system-email-composer data-header="' . esc_attr( wp_json_encode( $settings['header_blocks'] ) ) . '" data-footer="' . esc_attr( wp_json_encode( $settings['footer_blocks'] ) ) . '"></div>';
		echo '<input type="hidden" name="header_blocks" data-system-email-header value="' . esc_attr( wp_json_encode( $settings['header_blocks'] ) ) . '"><input type="hidden" name="footer_blocks" data-system-email-footer value="' . esc_attr( wp_json_encode( $settings['footer_blocks'] ) ) . '">';
		echo '<label class="contactpanda-system-email-check"><input type="checkbox" name="show_company" value="1"' . checked( ! empty( $settings['show_company'] ), true, false ) . '> <span>' . esc_html__( 'Show company and legal details in the footer', 'contactpanda' ) . '</span></label>';
		echo '<label class="contactpanda-system-email-check"><input type="checkbox" name="apply_wordpress" value="1"' . checked( ! empty( $settings['apply_wordpress'] ), true, false ) . '> <span>' . esc_html__( 'Apply this frame to compatible WordPress system emails', 'contactpanda' ) . '</span></label><p class="description">' . esc_html__( 'Complete HTML emails, including already designed WooCommerce emails, are not framed a second time.', 'contactpanda' ) . '</p>';
		echo '</div><div class="contactpanda-system-email-preview">' . self::render( __( 'Your message subject', 'contactpanda' ), '<h2>' . esc_html__( 'Hello!', 'contactpanda' ) . '</h2><p>' . esc_html__( 'The actual system message is inserted here automatically.', 'contactpanda' ) . '</p><p><a class="contactpanda-email-button" href="#">' . esc_html__( 'Example button', 'contactpanda' ) . '</a></p>' ) . '</div></div>';
		submit_button( __( 'Save system email template', 'contactpanda' ) );
		echo '</form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-system-email-test"><input type="hidden" name="action" value="contactpanda_test_system_email_template">';
		wp_nonce_field( 'contactpanda_test_system_email_template' );
		echo '<label><span>' . esc_html__( 'Test recipient', 'contactpanda' ) . '</span><input type="email" name="test_email" value="' . esc_attr( wp_get_current_user()->user_email ) . '"></label> ';
		submit_button( __( 'Send test email', 'contactpanda' ), 'secondary', 'submit', false );
		echo '</form></section>';
	}

	private static function color( $name, $label, $settings ) {
		$value = self::sanitize_color( $settings[ $name ] ?? '', '#ffffff' );
		$native = sanitize_hex_color( $value ) ?: '#ffffff';
		echo '<label class="contactpanda-settings-color-field"><span>' . esc_html( $label ) . '</span><input type="color" data-contactpanda-color-value="' . esc_attr( $value ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $native ) . '"></label>';
	}

	private static function customisation_available() {
		return defined( 'CONTACTPANDA_PLUS_VERSION' ) && class_exists( 'ContactPanda_License' ) && ContactPanda_License::entitled( 'plus' );
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		if ( ! self::customisation_available() ) wp_die( esc_html__( 'ContactPanda Plus is required to customise system email headers and footers.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_save_system_email_template' );
		$defaults = self::defaults();
		$settings = array(
			'logo_url'         => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
			'font_family'      => sanitize_text_field( wp_unslash( $_POST['font_family'] ?? $defaults['font_family'] ) ),
			'header_text'      => sanitize_text_field( wp_unslash( $_POST['header_text'] ?? '' ) ),
			'signature'        => wp_kses_post( wp_unslash( $_POST['signature'] ?? '' ) ),
			'footer_text'      => wp_kses_post( wp_unslash( $_POST['footer_text'] ?? '' ) ),
			'show_company'     => empty( $_POST['show_company'] ) ? 0 : 1,
			'apply_wordpress'  => empty( $_POST['apply_wordpress'] ) ? 0 : 1,
			'logo_position'    => in_array( wp_unslash( $_POST['logo_position'] ?? '' ), array( 'left', 'center', 'right' ), true ) ? wp_unslash( $_POST['logo_position'] ) : 'center',
			'header_blocks'    => self::sanitize_blocks( wp_unslash( $_POST['header_blocks'] ?? '[]' ) ),
			'footer_blocks'    => self::sanitize_blocks( wp_unslash( $_POST['footer_blocks'] ?? '[]' ) ),
		);
		foreach ( array( 'background_color', 'content_color', 'text_color', 'accent_color', 'header_color', 'footer_color', 'footer_text_color' ) as $key ) {
			$settings[ $key ] = self::sanitize_color( wp_unslash( $_POST[ $key ] ?? '' ), $defaults[ $key ] );
		}
		update_option( self::OPTION, $settings, false );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=newsletter&system_email_saved=1' ) );
		exit;
	}

	private static function sanitize_color( $value, $fallback ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		$hex = sanitize_hex_color( $value );
		if ( $hex ) return $hex;
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $value, $match ) ) {
			return sprintf( 'rgba(%d,%d,%d,%s)', min( 255, absint( $match[1] ) ), min( 255, absint( $match[2] ) ), min( 255, absint( $match[3] ) ), isset( $match[4] ) ? (string) max( 0, min( 1, (float) $match[4] ) ) : '1' );
		}
		return $fallback;
	}

	public static function send_test() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		if ( ! self::customisation_available() ) wp_die( esc_html__( 'ContactPanda Plus is required to customise system email headers and footers.', 'contactpanda' ), '', array( 'response' => 403 ) );
		check_admin_referer( 'contactpanda_test_system_email_template' );
		$email = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
		if ( is_email( $email ) ) self::send( $email, __( 'ContactPanda system email test', 'contactpanda' ), '<h2>' . esc_html__( 'The template is ready.', 'contactpanda' ) . '</h2><p>' . esc_html__( 'This is how future transactional ContactPanda emails will look.', 'contactpanda' ) . '</p>' );
		wp_safe_redirect( admin_url( 'admin.php?page=contactpanda-settings&tab=newsletter&system_email_saved=1' ) );
		exit;
	}

	public static function send( $to, $subject, $content, $headers = array(), $attachments = array() ) {
		$headers = is_array( $headers ) ? $headers : array( $headers );
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		self::$sending = true;
		$result = wp_mail( $to, $subject, self::render( $subject, $content ), array_values( array_unique( $headers ) ), $attachments );
		self::$sending = false;
		return $result;
	}

	public static function filter_wordpress_mail( $args ) {
		$s = self::settings();
		if ( self::$sending || empty( $s['apply_wordpress'] ) || empty( $args['message'] ) ) return $args;
		$message = (string) $args['message'];
		if ( preg_match( '/<(?:!doctype|html|body|head)\b/i', $message ) || false !== strpos( $message, 'data-contactpanda-system-email' ) ) return $args;
		$headers = is_array( $args['headers'] ?? null ) ? $args['headers'] : preg_split( '/\r?\n/', (string) ( $args['headers'] ?? '' ) );
		$is_html = false; foreach ( $headers as $header ) if ( false !== stripos( (string) $header, 'content-type:' ) && false !== stripos( (string) $header, 'text/html' ) ) $is_html = true;
		$content = $is_html ? wp_kses_post( $message ) : wpautop( esc_html( $message ) );
		$args['message'] = self::render( $args['subject'] ?? '', $content );
		$headers = array_values( array_filter( $headers, function( $header ) { return false === stripos( (string) $header, 'content-type:' ); } ) );
		$headers[] = 'Content-Type: text/html; charset=UTF-8'; $args['headers'] = $headers;
		return $args;
	}

	private static function sanitize_blocks( $json ) {
		$decoded = json_decode( (string) $json, true ); $clean = array();
		foreach ( is_array( $decoded ) ? array_slice( $decoded, 0, 12 ) : array() as $block ) {
			$type = sanitize_key( $block['type'] ?? '' ); if ( ! in_array( $type, array( 'image', 'text', 'separator' ), true ) ) continue;
			$clean[] = array( 'type' => $type, 'url' => esc_url_raw( $block['url'] ?? '' ), 'content' => wp_kses_post( $block['content'] ?? '' ) );
		}
		return $clean;
	}

	private static function render_blocks( $blocks, $logo, $position ) {
		$html = '';
		foreach ( (array) $blocks as $block ) {
			if ( 'image' === $block['type'] ) { $url = $block['url'] ?: $logo; if ( $url ) $html .= '<div style="text-align:' . esc_attr( $position ) . ';margin:0 0 16px"><img src="' . esc_url( $url ) . '" alt="" style="display:inline-block;max-height:72px;max-width:240px"></div>'; }
			elseif ( 'text' === $block['type'] && ! empty( $block['content'] ) ) $html .= '<div style="margin:0 0 14px">' . wpautop( wp_kses_post( $block['content'] ) ) . '</div>';
			elseif ( 'separator' === $block['type'] ) $html .= '<hr style="border:0;border-top:1px solid #dcdcde;margin:16px 0">';
		}
		return $html;
	}

	public static function render( $subject, $content ) {
		$s = self::settings();
		$p = class_exists( 'ContactPanda_Company_Profile' ) ? ContactPanda_Company_Profile::get() : array();
		$logo = $s['logo_url'] ?: ( $p['logo_url'] ?? '' );
		$company = trim( implode( ' · ', array_filter( array( $p['company_name'] ?? '', trim( ( $p['street'] ?? '' ) . ' ' . ( $p['postcode'] ?? '' ) . ' ' . ( $p['city'] ?? '' ) ), $p['email'] ?? '', $p['phone'] ?? '' ) ) ) );
		$legal = ! empty( $s['show_company'] ) ? trim( $company . ( ! empty( $p['legal_footer'] ) ? "\n" . $p['legal_footer'] : '' ) ) : '';
		$button_css = 'background:' . $s['accent_color'] . ';border-radius:4px;color:#fff!important;display:inline-block;padding:11px 18px;text-decoration:none';
		$content = preg_replace( '/class=(["\'])contactpanda-email-button\1/', 'style="' . esc_attr( $button_css ) . '"', (string) $content );
		$header = self::render_blocks( $s['header_blocks'], $logo, $s['logo_position'] );
		$footer = self::render_blocks( $s['footer_blocks'], $logo, $s['logo_position'] );
		return '<!doctype html><html><body data-contactpanda-system-email="1" style="margin:0;padding:0;background:' . esc_attr( $s['background_color'] ) . ';color:' . esc_attr( $s['text_color'] ) . ';font-family:' . esc_attr( $s['font_family'] ) . '"><table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:28px 14px"><table role="presentation" width="640" style="max-width:640px;width:100%;background:' . esc_attr( $s['content_color'] ) . ';border-radius:8px;overflow:hidden" cellspacing="0" cellpadding="0"><tr><td style="padding:24px 28px;background:' . esc_attr( $s['header_color'] ) . '">' . $header . '</td></tr><tr><td style="padding:28px"><div style="font-size:16px;line-height:1.6">' . wp_kses_post( $content ) . '</div></td></tr><tr><td align="center" style="text-align:center;border-top:1px solid #e2e5e8;padding:20px 28px;font-size:12px;line-height:1.5;background:' . esc_attr( $s['footer_color'] ) . ';color:' . esc_attr( $s['footer_text_color'] ) . '">' . $footer . ( $legal ? nl2br( esc_html( $legal ) ) : '' ) . '</td></tr></table><div style="font-size:12px;color:#646970;margin-top:12px">' . esc_html( $subject ) . '</div></td></tr></table></body></html>';
	}
}
