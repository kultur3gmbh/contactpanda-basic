<?php
/** Shared accessible list navigation. The caller supplies its existing filter URL. */
defined( 'ABSPATH' ) || exit;
final class ContactPanda_Pagination {
	public static function render( $page, $total, $limit, $url, $argument = 'paged' ) {
		$pages = max( 1, (int) ceil( max( 0, (int) $total ) / max( 1, (int) $limit ) ) );
		$page = max( 1, min( $pages, (int) $page ) );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'panda-pagination', CONTACTPANDA_URL . 'assets/panda-pagination.css', array(), CONTACTPANDA_ASSET_VERSION );
		wp_enqueue_script( 'panda-pagination', CONTACTPANDA_URL . 'assets/panda-pagination.js', array(), CONTACTPANDA_ASSET_VERSION, true );

		echo '<nav class="panda-pagination" aria-label="' . esc_attr__( 'Pages', 'contactpanda' ) . '">';
		echo '<span class="panda-pagination-steps">';
		self::step( $url, $argument, 1, 'controls-skipback', __( 'First page', 'contactpanda' ), 1 === $page );
		self::step( $url, $argument, $page - 1, 'arrow-left-alt2', __( 'Previous', 'contactpanda' ), 1 === $page );
		echo '</span>';

		/* translators: 1: Current page number, 2: Total number of pages. */
		$pattern = __( 'Page %1$s of %2$s', 'contactpanda' );
		$select = '<select aria-label="' . esc_attr__( 'Jump to page', 'contactpanda' ) . '" data-panda-page-url="' . esc_url( $url ) . '" data-panda-page-argument="' . esc_attr( $argument ) . '" disabled>';
		for ( $number = 1; $number <= $pages; $number++ ) {
			$select .= '<option value="' . esc_attr( $number ) . '"' . selected( $page, $number, false ) . '>' . esc_html( number_format_i18n( $number ) ) . '</option>';
		}
		$select .= '</select>';
		echo '<span class="panda-pagination-summary">' . sprintf( esc_html( $pattern ), $select, esc_html( number_format_i18n( $pages ) ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Format and select values escaped above.

		echo '<span class="panda-pagination-steps">';
		self::step( $url, $argument, $page + 1, 'arrow-right-alt2', __( 'Next', 'contactpanda' ), $pages === $page );
		self::step( $url, $argument, $pages, 'controls-skipforward', __( 'Last page', 'contactpanda' ), $pages === $page );
		echo '</span></nav>';
	}

	private static function step( $url, $argument, $page, $icon, $label, $disabled ) {
		$content = '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span><span>' . esc_html( $label ) . '</span>';
		if ( $disabled ) {
			echo '<button type="button" class="button panda-button-regular" disabled>' . $content . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon and label escaped above.
		} else {
			echo '<a class="button panda-button-regular" href="' . esc_url( add_query_arg( $argument, $page, $url ) ) . '">' . $content . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon and label escaped above.
		}
	}
}
