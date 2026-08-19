<?php
/**
 * Product archive pagination and filter query handling.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the current archive page number.
 *
 * @return int
 */
function rezajordaan_get_archive_paged() {
	$paged = get_query_var( 'paged' );
	$page  = get_query_var( 'page' );

	if ( $paged ) {
		return max( 1, absint( $paged ) );
	}

	if ( $page ) {
		return max( 1, absint( $page ) );
	}

	return 1;
}

/**
 * Whether a query targets WooCommerce product archives.
 *
 * @param WP_Query $query Query instance.
 * @return bool
 */
function rezajordaan_is_product_archive_query( $query ) {
	return $query->is_tax( array( 'product_cat', 'product_tag' ) ) || $query->is_post_type_archive( 'product' );
}

/**
 * Keep the main archive query on the requested page.
 *
 * @param WP_Query $query Current query.
 */
function rezajordaan_protect_archive_pagination( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! rezajordaan_is_product_archive_query( $query ) ) {
		return;
	}

	$query->set( 'paged', rezajordaan_get_archive_paged() );
}
add_action( 'pre_get_posts', 'rezajordaan_protect_archive_pagination', 5 );

/**
 * Reinforce pagination after WordPress parses the request.
 *
 * @param WP_Query $query Current query.
 */
function rezajordaan_parse_archive_pagination( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! rezajordaan_is_product_archive_query( $query ) ) {
		return;
	}

	$paged = rezajordaan_get_archive_paged();

	if ( $paged > 1 ) {
		$query->set( 'paged', $paged );
		$query->is_paged = true;
	}
}
add_action( 'parse_query', 'rezajordaan_parse_archive_pagination', 5 );

/**
 * Apply pagination to WooCommerce's product query hook.
 *
 * @param WP_Query $query Product query.
 */
function rezajordaan_woocommerce_product_query_pagination( $query ) {
	$query->set( 'paged', rezajordaan_get_archive_paged() );
}
add_action( 'woocommerce_product_query', 'rezajordaan_woocommerce_product_query_pagination', 5 );

/**
 * Apply selected intelligent size filters to product archive queries.
 *
 * @param WP_Query $query Current query.
 */
function rezajordaan_apply_archive_size_filters( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! rezajordaan_is_product_archive_query( $query ) ) {
		return;
	}

	$selected = isset( $_GET['rz_size'] ) ? (array) wp_unslash( $_GET['rz_size'] ) : array();
	$grouped  = array();

	foreach ( $selected as $value ) {
		$term_id = is_scalar( $value ) ? absint( $value ) : 0;
		$term    = $term_id ? get_term( $term_id ) : null;

		if ( $term instanceof WP_Term && str_starts_with( $term->taxonomy, 'pa_' ) ) {
			$grouped[ $term->taxonomy ][] = $term_id;
		}
	}

	if ( ! $grouped ) {
		return;
	}

	$tax_query = $query->get( 'tax_query' );
	if ( ! is_array( $tax_query ) ) {
		$tax_query = array();
	}

	if ( ! isset( $tax_query['relation'] ) ) {
		$tax_query['relation'] = 'AND';
	}

	foreach ( $grouped as $taxonomy => $term_ids ) {
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => array_unique( array_map( 'absint', $term_ids ) ),
			'operator' => 'IN',
		);
	}

	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'rezajordaan_apply_archive_size_filters', 20 );

/**
 * Keep active archive filters when shoppers move between pages.
 *
 * @param array $args Pagination arguments.
 * @return array
 */
function rezajordaan_archive_pagination_args( $args ) {
	if ( ! ( is_product_category() || is_product_tag() || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
		return $args;
	}

	$add_args = isset( $args['add_args'] ) && is_array( $args['add_args'] ) ? $args['add_args'] : array();

	foreach ( array( 'orderby', 'min_price', 'max_price' ) as $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			$add_args[ $key ] = wc_clean( wp_unslash( $_GET[ $key ] ) );
		}
	}

	if ( isset( $_GET['rz_size'] ) ) {
		$add_args['rz_size'] = array_map( 'absint', (array) wp_unslash( $_GET['rz_size'] ) );
	}

	$args['add_args'] = $add_args;
	$args['current']  = rezajordaan_get_archive_paged();

	return $args;
}
add_filter( 'woocommerce_pagination_args', 'rezajordaan_archive_pagination_args' );

/**
 * Prevent canonical redirects from collapsing paginated product archives to page 1.
 *
 * @param string $redirect_url  Redirect target.
 * @param string $requested_url Requested URL.
 * @return string|false
 */
function rezajordaan_preserve_archive_pagination( $redirect_url, $requested_url ) {
	if ( preg_match( '#/page/\d+/?#', (string) $requested_url ) ) {
		if ( is_product_category() || is_product_tag() || ( function_exists( 'is_shop' ) && is_shop() ) || is_post_type_archive( 'product' ) ) {
			return false;
		}
	}

	if ( is_paged() && ( is_product_category() || is_product_tag() || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'rezajordaan_preserve_archive_pagination', 10, 2 );

/**
 * Send no-cache headers on paginated product archives.
 */
function rezajordaan_nocache_paginated_archives() {
	if ( rezajordaan_get_archive_paged() <= 1 || headers_sent() ) {
		return;
	}

	if ( ! ( is_product_category() || is_product_tag() || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
		return;
	}

	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
	header( 'Pragma: no-cache', true );
}
add_action( 'template_redirect', 'rezajordaan_nocache_paginated_archives', 0 );
