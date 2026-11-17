<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Panda_License_Lifecycle_V1', false ) ) {
/** Shared signed-policy interpreter; no independent commercial grace calculation. */
final class Panda_License_Lifecycle_V1 { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Versioned Panda family shared component, guarded against duplicate loading.
	public static function normalize( $policy ) {
		if ( ! is_array( $policy ) || ! in_array( (int) ( $policy['version'] ?? 0 ), array( 1, 2 ), true ) ) return array();
		$out = array_intersect_key( $policy, array_flip( array( 'version', 'mode', 'paid_until', 'restrict_at', 'grace', 'source', 'timezone', 'eligible', 'continuity' ) ) );
		$out['version'] = (int) $policy['version'];
		$out['mode'] = in_array( $out['mode'] ?? '', array( 'active', 'grace', 'archive', 'blocked', 'trial' ), true ) ? $out['mode'] : 'blocked';
		return $out;
	}
	public static function mode( array $license, $now = null ) {
		if ( in_array( $license['status'] ?? '', array( 'blocked', 'disconnected', 'deactivated', 'inactive' ), true ) ) return 'blocked';
		$p = self::normalize( $license['lifecycle'] ?? array() );
		if ( ! $p ) return 'legacy'; // Server/network migration cannot fabricate a confirmed expiry.
		if ( 'blocked' === $p['mode'] ) return 'blocked';
		if ( empty( $p['eligible'] ) ) return $p['mode'];
		$end = strtotime( (string) ( $p['paid_until'] ?? '' ) );
		$restrict = strtotime( (string) ( $p['restrict_at'] ?? '' ) );
		if ( ! $end || ! $restrict || $restrict < $end ) return 'blocked';
		$now = null === $now ? time() : (int) $now;
		return $now < $end ? 'active' : ( $now < $restrict ? 'grace' : 'archive' );
	}
	public static function allows( array $license, $operation = 'read', $now = null, array $context = array() ) {
		$mode = self::mode( $license, $now );
		if ( 'blocked' === $mode ) return false;
		if ( 'trial' === ( $license['license_type'] ?? '' ) ) {
			$end = strtotime( (string) ( $license['features_until'] ?? '' ) );
			if ( ! $end || $end <= ( null === $now ? time() : $now ) ) return false;
		}
		if ( 'archive' !== $mode ) return true;
		// Only existing arrangements may keep running. Creation/import/duplication remain denied.
		if ( in_array( $operation, array( 'read', 'search', 'export', 'delete', 'withdraw', 'cleanup', 'cancel', 'stop', 'run', 'renew', 'submit', 'fulfill', 'reply' ), true ) ) return true;
		if ( ! in_array( $operation, array( 'edit_existing', 'maintain', 'assign', 'comment' ), true ) || empty( $context['existing'] ) ) return false;
		// Callers supply database-resolved context, never a request's claimed ID or timestamp.
		if ( ! empty( $context['created_at'] ) ) {
			$created = strtotime( (string) $context['created_at'] );
			$cutoff = strtotime( (string) ( $license['lifecycle']['restrict_at'] ?? '' ) );
			if ( ! $created || ! $cutoff || $created >= $cutoff ) return false;
		}
		return true;
	}
	public static function error( $domain ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain -- Shared family library uses the calling product domain; identical literals are included in every product catalog.
		return new WP_Error( 'panda_license_archive', __( 'Existing setups and automatic billing continue. An active license is required for new paid setups, duplication, imports and extensions. Existing data and necessary management remain available.', $domain ), array( 'status' => 403 ) );
	}
	public static function date_label( $value ) {
		if ( ! $value ) return '—';
		try {
			$date = new DateTimeImmutable( $value, function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' ) );
		} catch ( Exception $error ) { return '—'; }
		if ( ! function_exists( 'wp_date' ) ) return $value;
		$format = get_option( 'date_format', 'Y-m-d' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) $format .= ' ' . get_option( 'time_format', 'H:i' ) . ' T';
		return wp_date( $format, $date->getTimestamp() );
	}

	public static function notice( array $license, $domain ) {
		$mode = self::mode( $license );
		if ( ! in_array( $mode, array( 'grace', 'archive' ), true ) ) return;
		$p = $license['lifecycle'];
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain -- Caller selects its own product catalog in this shared component.
		$state = 'archive' === $mode ? __( 'Existing setups only', $domain ) : __( 'License grace period', $domain );
		/* translators: 1: end of the paid license period, 2: date when new setups become restricted. */
		$dates = sprintf( __( 'Paid period ends: %1$s. New setups restricted from: %2$s.', $domain ), self::date_label( $p['paid_until'] ), self::date_label( $p['restrict_at'] ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain -- Shared product domain, supplied by the calling product.
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain -- Shared product catalog, as above.
		$details = __( 'Existing setups, published content, automatic processes and billing continue. Renew to add new paid setups or extensions. Existing data and necessary management remain available. Updates and support end with their paid entitlement; free features remain available.', $domain );
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html( $license['license_label'] ?? '' ) . '</strong> ' . esc_html( $state ) . ' · ' . esc_html( $dates ) . '</p><p>' . esc_html( $details ) . '</p></div>';
	}
}
}
