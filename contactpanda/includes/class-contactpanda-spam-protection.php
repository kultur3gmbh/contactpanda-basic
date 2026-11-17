<?php

defined( 'ABSPATH' ) || exit;

/** Central anti-spam contract shared by ContactPanda forms and family plugins. */
final class ContactPanda_Spam_Protection {
	const OPTION = 'contactpanda_spam_protection';

	public static function boot() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_filter( 'contactpanda_general_settings_sections', array( __CLASS__, 'settings_section' ) );
			add_action( 'contactpanda_render_general_settings_section_spam_protection', array( __CLASS__, 'render_settings' ) );
		}
		add_action( 'wpcf7_init', array( __CLASS__, 'register_contact_form_7_tag' ) );
		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'contact_form_7_elements' ) );
		add_filter( 'wpcf7_spam', array( __CLASS__, 'contact_form_7_spam' ), 20, 1 );
	}

	public static function register_settings() {
		register_setting( 'contactpanda_spam_protection', self::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'default' => self::defaults() ) );
	}

	public static function settings_section( $sections ) {
		$sections['spam_protection'] = __( 'Spamschutz', 'contactpanda' );
		return $sections;
	}

	public static function defaults() {
		return array(
			'enabled' => 1, 'provider' => 'basic', 'minimum_seconds' => 2, 'attempt_limit' => 8,
			'akismet_key' => '', 'turnstile_site_key' => '', 'turnstile_secret_key' => '',
			'recaptcha_site_key' => '', 'recaptcha_secret_key' => '', 'recaptcha_score' => '0.5',
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$provider = sanitize_key( $input['provider'] ?? 'basic' );
		if ( ! in_array( $provider, array( 'basic', 'akismet', 'turnstile', 'recaptcha_v3' ), true ) ) $provider = 'basic';
		return array(
			'enabled' => 1,
			'provider' => $provider,
			'minimum_seconds' => min( 30, max( 0, absint( $input['minimum_seconds'] ?? 2 ) ) ),
			'attempt_limit' => min( 100, max( 1, absint( $input['attempt_limit'] ?? 8 ) ) ),
			'akismet_key' => sanitize_text_field( $input['akismet_key'] ?? '' ),
			'turnstile_site_key' => sanitize_text_field( $input['turnstile_site_key'] ?? '' ),
			'turnstile_secret_key' => sanitize_text_field( $input['turnstile_secret_key'] ?? '' ),
			'recaptcha_site_key' => sanitize_text_field( $input['recaptcha_site_key'] ?? '' ),
			'recaptcha_secret_key' => sanitize_text_field( $input['recaptcha_secret_key'] ?? '' ),
			'recaptcha_score' => (string) min( 1, max( 0, (float) ( $input['recaptcha_score'] ?? 0.5 ) ) ),
		);
	}

	public static function render_settings() {
		$s = self::settings();
		echo '<form method="post" action="options.php"><section class="contactpanda-card contactpanda-settings-card contactpanda-spam-settings"><h2>' . esc_html__( 'Spamschutz für öffentliche Formulare', 'contactpanda' ) . '</h2><p>' . esc_html__( 'Der Spamschutz ist immer aktiv und gilt automatisch für Newsletter- und Registrierungsformulare, den ContactPanda-Formulareditor, Panda-Seasons-Anmeldungen sowie Contact Form 7. Abgewiesene Anfragen erzeugen weder einen Kontakt noch eine Double-Opt-in-E-Mail.', 'contactpanda' ) . '</p>';
		settings_fields( 'contactpanda_spam_protection' );
		echo '<div class="contactpanda-spam-status"><span class="dashicons dashicons-shield" aria-hidden="true"></span><div><strong>' . esc_html__( 'Zentraler Schutz ist aktiv', 'contactpanda' ) . '</strong><p>' . esc_html__( 'Mindestens der unsichtbare Basisschutz wird immer angewendet. Die gewählte Methode gilt für alle unterstützten Formulare.', 'contactpanda' ) . '</p></div></div>';
		echo '<label class="contactpanda-spam-field"><span>' . esc_html__( 'Schutzmethode', 'contactpanda' ) . '</span><select data-contactpanda-spam-provider name="' . esc_attr( self::OPTION ) . '[provider]">';
		foreach ( self::provider_labels() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['provider'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select><small>' . esc_html__( 'Die Methode wird zentral festgelegt und automatisch in alle unterstützten Formulare integriert.', 'contactpanda' ) . '</small></label>';
		echo '<div class="contactpanda-spam-provider" data-contactpanda-spam-panel="basic"><p class="description">' . esc_html__( 'Schützt ohne externen Dienst durch ein unsichtbares Köderfeld, eine Mindest-Ausfüllzeit und ein Versuchslimit.', 'contactpanda' ) . '</p></div>';
		echo '<div class="contactpanda-spam-common contactpanda-settings-grid"><label><span>' . esc_html__( 'Mindest-Ausfüllzeit in Sekunden', 'contactpanda' ) . '</span><input type="number" min="0" max="30" name="' . esc_attr( self::OPTION ) . '[minimum_seconds]" value="' . esc_attr( $s['minimum_seconds'] ) . '"></label><label><span>' . esc_html__( 'Maximale Versuche innerhalb von 15 Minuten', 'contactpanda' ) . '</span><input type="number" min="1" max="100" name="' . esc_attr( self::OPTION ) . '[attempt_limit]" value="' . esc_attr( $s['attempt_limit'] ) . '"></label></div>';
		echo '<div class="contactpanda-spam-provider" data-contactpanda-spam-panel="akismet"><label class="contactpanda-spam-field"><span>' . esc_html__( 'Akismet-API-Schlüssel', 'contactpanda' ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION ) . '[akismet_key]" value="' . esc_attr( $s['akismet_key'] ) . '"><small>' . esc_html__( 'Optional, wenn das aktive Akismet-Plugin bereits einen Schlüssel bereitstellt.', 'contactpanda' ) . '</small></label></div>';
		echo '<div class="contactpanda-spam-provider contactpanda-settings-grid" data-contactpanda-spam-panel="turnstile"><label><span>' . esc_html__( 'Turnstile-Website-Schlüssel', 'contactpanda' ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION ) . '[turnstile_site_key]" value="' . esc_attr( $s['turnstile_site_key'] ) . '"></label><label><span>' . esc_html__( 'Turnstile-Geheimschlüssel', 'contactpanda' ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION ) . '[turnstile_secret_key]" value="' . esc_attr( $s['turnstile_secret_key'] ) . '"></label></div>';
		echo '<div class="contactpanda-spam-provider contactpanda-settings-grid" data-contactpanda-spam-panel="recaptcha_v3"><label><span>' . esc_html__( 'reCAPTCHA-Website-Schlüssel', 'contactpanda' ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION ) . '[recaptcha_site_key]" value="' . esc_attr( $s['recaptcha_site_key'] ) . '"></label><label><span>' . esc_html__( 'reCAPTCHA-Geheimschlüssel', 'contactpanda' ) . '</span><input type="password" autocomplete="new-password" name="' . esc_attr( self::OPTION ) . '[recaptcha_secret_key]" value="' . esc_attr( $s['recaptcha_secret_key'] ) . '"></label><label><span>' . esc_html__( 'Mindestbewertung', 'contactpanda' ) . '</span><input type="number" min="0" max="1" step="0.1" name="' . esc_attr( self::OPTION ) . '[recaptcha_score]" value="' . esc_attr( $s['recaptcha_score'] ) . '"><small>' . esc_html__( 'Niedrigere Werte sind großzügiger, höhere Werte strenger.', 'contactpanda' ) . '</small></label></div>';
		// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- This is a clickable provider privacy-policy link, not a remotely loaded asset.
		echo '<p class="description">' . esc_html__( 'The basic method works locally. Selecting and saving Akismet, Turnstile or reCAPTCHA enables the selected external service for supported public forms. Akismet receives the submitted name, email address, message and technical request data; Turnstile and reCAPTCHA process browser and challenge data, and verification sends the response token and IP address to the provider.', 'contactpanda' ) . '</p><p><a href="https://akismet.com/privacy/" target="_blank" rel="noopener">Akismet</a> · <a href="https://www.cloudflare.com/privacypolicy/" target="_blank" rel="noopener">Cloudflare</a> · <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google</a></p>';
		echo '<button type="submit" class="button button-primary contactpanda-button-with-icon"><span class="dashicons dashicons-saved" aria-hidden="true"></span><span>' . esc_html__( 'Spamschutz speichern', 'contactpanda' ) . '</span></button>';
		echo '<script>(function(){var s=document.querySelector("[data-contactpanda-spam-provider]");if(!s)return;function u(){document.querySelectorAll("[data-contactpanda-spam-panel]").forEach(function(p){p.hidden=p.getAttribute("data-contactpanda-spam-panel")!==s.value;});}s.addEventListener("change",u);u();}());</script></section></form>';
	}

	public static function provider_labels() {
		return array( 'basic' => __( 'Unsichtbarer Basisschutz', 'contactpanda' ), 'akismet' => 'Akismet', 'turnstile' => 'Cloudflare Turnstile', 'recaptcha_v3' => 'Google reCAPTCHA v3' );
	}

	public static function provider_label() {
		$s = self::settings(); $labels = self::provider_labels();
		return $labels[ $s['provider'] ] ?? $labels['basic'];
	}

	public static function settings_url() {
		return admin_url( 'admin.php?page=contactpanda-settings&tab=general&general_section=spam_protection' );
	}

	/** Render fields required by the selected central method. */
	public static function render_fields( $context = 'signup' ) {
		$s = self::settings();
		$context = sanitize_key( $context );
		$html = '<input type="hidden" name="contactpanda_spam_started" value="' . esc_attr( time() ) . '"><div class="contactpanda-registration-trap" aria-hidden="true"><label>Website<input type="text" name="contactpanda_spam_website" tabindex="-1" autocomplete="off"></label></div>';
		if ( 'turnstile' === $s['provider'] && $s['turnstile_site_key'] ) {
			wp_enqueue_script( 'contactpanda-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion,PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- The explicitly enabled Turnstile service requires its official challenge script; data use and consent are documented in readme.txt.
			$html .= '<div class="cf-turnstile" data-sitekey="' . esc_attr( $s['turnstile_site_key'] ) . '"></div>';
		} elseif ( 'recaptcha_v3' === $s['provider'] && $s['recaptcha_site_key'] ) {
			wp_enqueue_script( 'contactpanda-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $s['recaptcha_site_key'] ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_script( 'contactpanda-spam-protection', CONTACTPANDA_URL . 'assets/contactpanda-spam-protection.js', array( 'contactpanda-recaptcha' ), CONTACTPANDA_ASSET_VERSION, true );
			$html .= '<input type="hidden" class="contactpanda-recaptcha-response" name="g-recaptcha-response" value="" data-sitekey="' . esc_attr( $s['recaptcha_site_key'] ) . '" data-action="' . esc_attr( $context ) . '" data-error="' . esc_attr__( 'Spam protection could not be completed. Please try submitting the form again.', 'contactpanda' ) . '">';
		}
		return $html;
	}

	/** Return the common allow/quarantine/block decision used by every module. */
	public static function verify( $context = 'signup', $request = null ) {
		$s = self::settings();
		$request = is_array( $request ) ? $request : $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public anti-spam validator; provider-specific input validation runs here, while the submitting form owns authorization and any contact write.
		$context = sanitize_key( $context );
		if ( ! empty( $request['contactpanda_spam_website'] ) || ! empty( $request['contactpanda_website'] ) ) return self::decision( 'block', 'honeypot', $context );
		$started = absint( $request['contactpanda_spam_started'] ?? 0 );
		if ( $s['minimum_seconds'] && ( ! $started || time() - $started < $s['minimum_seconds'] ) ) return self::decision( 'block', 'submitted_too_quickly', $context );
		$key = 'contactpanda_spam_' . md5( $context . '|' . self::remote_ip() );
		$count = absint( get_transient( $key ) );
		set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		if ( $count >= $s['attempt_limit'] ) return self::decision( 'block', 'rate_limit', $context );
		if ( 'akismet' === $s['provider'] ) return self::verify_akismet( $context, $request, $s );
		// phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- Required verification API for the explicitly enabled Turnstile service, not asset offloading.
		if ( 'turnstile' === $s['provider'] ) return self::verify_remote_token( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', $s['turnstile_secret_key'], $request['cf-turnstile-response'] ?? '', $context );
		if ( 'recaptcha_v3' === $s['provider'] ) return self::verify_recaptcha( $request, $context, $s );
		return array( 'status' => 'allow', 'reason' => 'basic_checks_passed' );
	}

	private static function verify_akismet( $context, $request, $s ) {
		$key = $s['akismet_key'];
		if ( ! $key && class_exists( 'Akismet' ) && is_callable( array( 'Akismet', 'get_api_key' ) ) ) $key = Akismet::get_api_key();
		if ( ! $key ) return self::decision( 'quarantine', 'akismet_key_missing', $context );
		$body = array( 'blog' => home_url( '/' ), 'user_ip' => self::remote_ip(), 'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 'referrer' => esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ?? '' ) ), 'comment_type' => 'contact_form' === $context ? 'contact_form' : 'signup', 'comment_author' => sanitize_text_field( wp_unslash( $request['first_name'] ?? '' ) . ' ' . wp_unslash( $request['last_name'] ?? '' ) ), 'comment_author_email' => sanitize_email( wp_unslash( $request['email'] ?? '' ) ), 'comment_content' => sanitize_textarea_field( wp_unslash( $request['message'] ?? '' ) ) );
		$response = wp_remote_post( 'https://' . rawurlencode( $key ) . '.rest.akismet.com/1.1/comment-check', array( 'timeout' => 8, 'body' => $body ) );
		if ( is_wp_error( $response ) ) return self::decision( 'quarantine', 'akismet_unavailable', $context );
		return 'true' === trim( wp_remote_retrieve_body( $response ) ) ? self::decision( 'quarantine', 'akismet_spam', $context ) : array( 'status' => 'allow', 'reason' => 'akismet_ham' );
	}

	private static function verify_remote_token( $url, $secret, $token, $context ) {
		if ( ! $secret || ! $token ) return self::decision( 'block', 'challenge_missing', $context );
		$response = wp_remote_post( $url, array( 'timeout' => 8, 'body' => array( 'secret' => $secret, 'response' => sanitize_text_field( wp_unslash( $token ) ), 'remoteip' => self::remote_ip() ) ) );
		if ( is_wp_error( $response ) ) return self::decision( 'quarantine', 'challenge_unavailable', $context );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return ! empty( $data['success'] ) ? array( 'status' => 'allow', 'reason' => 'challenge_passed' ) : self::decision( 'block', 'challenge_failed', $context );
	}

	private static function verify_recaptcha( $request, $context, $s ) {
		$token = $request['g-recaptcha-response'] ?? '';
		if ( ! $s['recaptcha_secret_key'] || ! is_string( $token ) || '' === trim( $token ) || strlen( $token ) > 4096 ) return self::decision( 'block', 'challenge_missing', $context );
		// A reCAPTCHA token is single-use. Validate success, origin, action and score
		// from this one response rather than sending the same token twice.
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'timeout' => 8, 'body' => array( 'secret' => $s['recaptcha_secret_key'], 'response' => sanitize_text_field( wp_unslash( $token ) ), 'remoteip' => self::remote_ip() ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return self::decision( 'quarantine', 'challenge_unavailable', $context );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) ) return self::decision( 'block', 'challenge_failed', $context );
		$hostname = strtolower( rtrim( (string) ( $data['hostname'] ?? '' ), '.' ) );
		$expected_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( ! $expected_host || $hostname !== $expected_host || ( $data['action'] ?? '' ) !== $context ) return self::decision( 'block', 'challenge_origin', $context );
		$challenge_time = is_string( $data['challenge_ts'] ?? null ) ? strtotime( $data['challenge_ts'] ) : false;
		if ( ! $challenge_time || $challenge_time < time() - 120 || $challenge_time > time() + 30 ) return self::decision( 'block', 'challenge_expired', $context );
		if ( ! isset( $data['score'] ) || ! is_numeric( $data['score'] ) || (float) $data['score'] < 0 || (float) $data['score'] > 1 ) return self::decision( 'block', 'challenge_failed', $context );
		return (float) $data['score'] >= (float) $s['recaptcha_score'] ? array( 'status' => 'allow', 'reason' => 'recaptcha_passed' ) : self::decision( 'quarantine', 'recaptcha_score', $context );
	}

	private static function decision( $status, $reason, $context ) {
		$result = array( 'status' => $status, 'reason' => $reason );
		if ( 'quarantine' === $status ) {
			$items = (array) get_option( 'contactpanda_spam_quarantine', array() );
			$items[] = array(
				'context' => sanitize_key( $context ),
				'reason' => sanitize_key( $reason ),
				'email_hash' => hash( 'sha256', strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Rejected public-form telemetry only: sanitized email is irreversibly hashed in a bounded quarantine list; this does not authorize a form submission.
				'ip_hash' => hash_hmac( 'sha256', self::remote_ip(), wp_salt( 'nonce' ) ),
				'created_at' => current_time( 'mysql' ),
			);
			update_option( 'contactpanda_spam_quarantine', array_slice( $items, -200 ), false );
		}
		do_action( 'contactpanda_spam_rejected', $result, $context );
		return $result;
	}

	private static function remote_ip() {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	}

	public static function register_contact_form_7_tag() {
		if ( function_exists( 'wpcf7_add_form_tag' ) ) wpcf7_add_form_tag( 'contactpanda_spamschutz', array( __CLASS__, 'contact_form_7_tag' ) );
	}

	public static function contact_form_7_tag() {
		return '<span class="contactpanda-cf7-spamschutz">' . self::render_fields( 'contact_form_7' ) . '</span>';
	}

	public static function contact_form_7_elements( $html ) {
		if ( false === strpos( (string) $html, 'contactpanda-cf7-spamschutz' ) ) $html .= self::contact_form_7_tag();
		return $html;
	}

	public static function contact_form_7_spam( $spam ) {
		if ( $spam ) return $spam;
		$decision = self::verify( 'contact_form_7', $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 spam hook delegates public submissions to the anti-spam verifier; CF7 owns its form validation and delivery.
		if ( 'allow' === ( $decision['status'] ?? '' ) ) return false;
		if ( class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
			if ( $submission && method_exists( $submission, 'add_spam_log' ) ) $submission->add_spam_log( array( 'agent' => 'contactpanda', 'reason' => sanitize_text_field( $decision['reason'] ?? 'spam_protection' ) ) );
		}
		return true;
	}
}

