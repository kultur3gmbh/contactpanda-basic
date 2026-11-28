<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Translations {
	/** @var array<string, string> Bundled catalog directories by text domain. */
	private static $directories = array();

	/**
	 * Register a German catalog fallback without changing WordPress's active locale.
	 */
	public static function register( $domain, $directory ) {
		if ( empty( self::$directories ) ) {
			add_filter( 'load_textdomain_mofile', array( __CLASS__, 'resolve_german_catalog' ), 10, 2 );
		}
		self::$directories[ $domain ] = rtrim( $directory, '/\\' );
	}

	public static function resolve_german_catalog( $mofile, $domain ) {
		if ( ! isset( self::$directories[ $domain ] ) || self::catalog_exists( $mofile ) ) {
			return $mofile;
		}

		$pattern = '/^' . preg_quote( $domain, '/' ) . '-(de_[A-Z]{2}(?:_(?:in)?formal)?)\.mo$/D';
		if ( ! preg_match( $pattern, basename( $mofile ), $matches ) ) {
			return $mofile;
		}

		$locale = $matches[1];
		$preferred = substr( $locale, -7 ) === '_formal' ? 'de_DE_formal' : 'de_DE';
		$locales = array_unique( array( $locale, $preferred, 'de_DE' ) );
		$directories = array( WP_LANG_DIR . '/plugins', self::$directories[ $domain ] );

		foreach ( $locales as $candidate_locale ) {
			foreach ( $directories as $directory ) {
				$candidate = $directory . '/' . $domain . '-' . $candidate_locale . '.mo';
				if ( self::catalog_exists( $candidate ) ) {
					return $candidate;
				}
			}
		}

		return $mofile;
	}

	private static function catalog_exists( $mofile ) {
		return is_readable( $mofile ) || (
			class_exists( 'WP_Translation_Controller', false )
			&& '.mo' === substr( $mofile, -3 )
			&& is_readable( substr( $mofile, 0, -3 ) . '.l10n.php' )
		);
	}
}
