<?php
/** Shared accessible list navigation. The caller supplies its existing filter URL. */
defined( 'ABSPATH' ) || exit;
final class ContactPanda_Pagination {
	public static function render( $page, $total, $limit, $url, $argument = 'paged' ) {
		$pages = max( 1, (int) ceil( max( 0, (int) $total ) / max( 1, (int) $limit ) ) );
		$page = max( 1, min( $pages, (int) $page ) );
		/* translators: 1: Current page number, 2: Total number of pages. */
		$summary = sprintf( __( 'Page %1$d of %2$d', 'contactpanda' ), $page, $pages );
		echo '<nav class="panda-pagination" aria-label="' . esc_attr__( 'Pages', 'contactpanda' ) . '"><span class="panda-pagination-summary">' . esc_html( $summary ) . '</span>';
		foreach ( array( array( 1, 'controls-skipback', __( 'First page', 'contactpanda' ), $page <= 1 ), array( $page - 1, 'arrow-left-alt2', __( 'Previous', 'contactpanda' ), $page <= 1 ), array( $page + 1, 'arrow-right-alt2', __( 'Next', 'contactpanda' ), $page >= $pages ), array( $pages, 'controls-skipforward', __( 'Last page', 'contactpanda' ), $page >= $pages ) ) as $item ) {
			$inside = '<span class="dashicons dashicons-' . esc_attr( $item[1] ) . '" aria-hidden="true"></span><span>' . esc_html( $item[2] ) . '</span>';
			if ( $item[3] ) echo '<button type="button" class="button" disabled>' . $inside . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Every dynamic fragment escaped above.
			else echo '<a class="button" href="' . esc_url( add_query_arg( $argument, max( 1, $item[0] ), $url ) ) . '">' . $inside . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</nav>';
	}
}
