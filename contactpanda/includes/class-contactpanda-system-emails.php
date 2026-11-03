<?php

defined( 'ABSPATH' ) || exit;

/** Shared visual frame for transactional emails sent by the ContactPanda family. */
final class ContactPanda_System_Emails {
	const OPTION = 'contactpanda_system_email_template';
	private static $sending = false;

	public static function boot() {
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
		return (array) apply_filters( 'contactpanda_system_email_settings', self::defaults(), self::defaults() );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You are not allowed to manage these settings.', 'contactpanda' ) );
		echo '<section class="contactpanda-card contactpanda-settings-card"><h2>' . esc_html__( 'System email design', 'contactpanda' ) . '</h2><p>' . esc_html__( 'This shared frame is used for transactional ContactPanda messages such as confirmations, account messages, form notifications, support replies, project notices, and automation administration messages. Newsletters keep their own design.', 'contactpanda' ) . '</p>';
		if ( has_action( 'contactpanda_system_email_customisation_settings' ) ) {
			do_action( 'contactpanda_system_email_customisation_settings' );
			echo '</section>';
			return;
		}
		echo '<div class="contactpanda-system-email-preview">' . wp_kses_post( self::render( __( 'Your message subject', 'contactpanda' ), '<h2>' . esc_html__( 'Hello!', 'contactpanda' ) . '</h2><p>' . esc_html__( 'The actual system message is inserted here automatically.', 'contactpanda' ) . '</p>' ) ) . '</div>';
		echo '<div class="contactpanda-plus-promotion"><p><strong>' . esc_html__( 'Individual email headers and footers are available with ContactPanda Plus.', 'contactpanda' ) . '</strong> ' . esc_html__( 'Plus adds a visual composer for images, formatted text, separators, multiple rows, and reusable header and footer layouts.', 'contactpanda' ) . '</p><p><a class="button" href="' . esc_url( apply_filters( 'contactpanda_plus_url', 'https://contactpanda.com/', 'system-emails' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Upgrade to ContactPanda Plus', 'contactpanda' ) . '</a></p></div></section>';
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

	private static function render_blocks( $blocks, $logo, $position ) {
		$html = '';
		foreach ( (array) $blocks as $block ) {
			$type = $block['type'] ?? '';
			if ( 'image' === $type ) { $url = $block['url'] ?? ''; $url = $url ?: $logo; if ( $url ) $html .= '<div style="text-align:' . esc_attr( $position ) . ';margin:0 0 16px"><img src="' . esc_url( $url ) . '" alt="" style="display:inline-block;max-height:72px;max-width:240px"></div>'; }
			elseif ( 'text' === $type && ! empty( $block['content'] ) ) $html .= '<div style="margin:0 0 14px">' . wpautop( wp_kses_post( $block['content'] ) ) . '</div>';
			elseif ( 'separator' === $type ) $html .= '<hr style="border:0;border-top:1px solid #dcdcde;margin:16px 0">';
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
