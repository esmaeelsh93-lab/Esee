<?php
/**
 * Keep WooCommerce shop and taxonomy pagination on the requested page.
 *
 * WordPress treats the shop as a singular Page and also strips /page/N/
 * from catalog URLs when `paged` is missing. That 301s every "next" click
 * back to page 1. Restore the page number from the URL and skip that redirect.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current request path, decoded so Persian category slugs match.
 *
 * @return string
 */
function rezajordaan_requested_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = wp_parse_url( $uri, PHP_URL_PATH );

	return is_string( $path ) ? rawurldecode( $path ) : '';
}

/**
 * Page number from pretty permalinks or query args.
 *
 * @return int
 */
function rezajordaan_requested_page_number() {
	$path = rezajordaan_requested_path();

	if ( preg_match( '#/page/([0-9]+)/?$#u', $path, $matches ) ) {
		return max( 1, (int) $matches[1] );
	}

	foreach ( array( 'paged', 'page' ) as $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			return max( 1, absint( wp_unslash( $_GET[ $key ] ) ) );
		}
	}

	return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
}

/**
 * Rewrite slugs used by WooCommerce catalog archives.
 *
 * @return string[]
 */
function rezajordaan_catalog_path_bases() {
	$bases = array( 'product-category', 'product-tag', 'shop' );

	if ( function_exists( 'wc_get_permalink_structure' ) ) {
		$permalinks = wc_get_permalink_structure();
		foreach ( array( 'category_rewrite_slug', 'tag_rewrite_slug', 'product_rewrite_slug' ) as $key ) {
			if ( ! empty( $permalinks[ $key ] ) ) {
				$bases[] = trim( (string) $permalinks[ $key ], '/' );
			}
		}
	}

	if ( function_exists( 'wc_get_page_id' ) ) {
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id > 0 ) {
			$shop_uri = get_page_uri( $shop_id );
			if ( $shop_uri ) {
				$bases[] = trim( $shop_uri, '/' );
			}
		}
	}

	$bases = array_values( array_unique( array_filter( $bases ) ) );

	return $bases;
}

/**
 * Whether this request is a paginated WooCommerce catalog URL.
 *
 * @return bool
 */
function rezajordaan_is_catalog_paged_request() {
	$page_number = rezajordaan_requested_page_number();
	$path        = rezajordaan_requested_path();

	if ( $page_number <= 1 && ! preg_match( '#/page/[0-9]+/?$#u', $path ) ) {
		return false;
	}

	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) ) ) {
		return $page_number > 1;
	}

	if ( ! $path ) {
		return false;
	}

	$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	$shop_uri = $shop_id > 0 ? trim( (string) get_page_uri( $shop_id ), '/' ) : 'shop';

	if ( $shop_uri && preg_match( '#/' . preg_quote( $shop_uri, '#' ) . '/page/[0-9]+/?$#u', $path ) ) {
		return true;
	}

	foreach ( rezajordaan_catalog_path_bases() as $base ) {
		if ( preg_match( '#/' . preg_quote( $base, '#' ) . '/.+/page/[0-9]+/?$#u', $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Map /page/N/ onto the query vars WordPress and WooCommerce actually read.
 *
 * @param array<string, mixed> $vars Parsed request vars.
 * @return array<string, mixed>
 */
function rezajordaan_catalog_request_vars( $vars ) {
	$page_number = 0;

	if ( ! empty( $vars['paged'] ) ) {
		$page_number = (int) $vars['paged'];
	} elseif ( ! empty( $vars['page'] ) ) {
		$page_number = (int) $vars['page'];
	}

	if ( $page_number < 2 ) {
		$page_number = rezajordaan_requested_page_number();
	}

	if ( $page_number < 2 ) {
		return $vars;
	}

	$is_catalog = isset( $vars['product_cat'] ) || isset( $vars['product_tag'] ) || ( isset( $vars['post_type'] ) && 'product' === $vars['post_type'] );

	if ( ! empty( $vars['pagename'] ) && preg_match( '#^(.+)/page/([0-9]+)$#u', (string) $vars['pagename'], $matches ) ) {
		$vars['pagename'] = $matches[1];
		$page_number      = max( $page_number, (int) $matches[2] );
	}

	$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	$shop_uri = $shop_id > 0 ? trim( (string) get_page_uri( $shop_id ), '/' ) : '';

	if ( $shop_id && $shop_uri && ! empty( $vars['pagename'] ) && trim( (string) $vars['pagename'], '/' ) === $shop_uri ) {
		$is_catalog        = true;
		$vars['page_id']   = $shop_id;
		$vars['pagename']  = $shop_uri;
	}

	if ( ! $is_catalog && rezajordaan_is_catalog_paged_request() ) {
		$is_catalog = true;
	}

	if ( $is_catalog ) {
		$vars['paged'] = $page_number;
	}

	return $vars;
}
add_filter( 'request', 'rezajordaan_catalog_request_vars', 20 );

/**
 * Force the main catalog query onto the requested page.
 *
 * @param WP_Query $query Current query.
 */
function rezajordaan_apply_catalog_paged_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$page_number = rezajordaan_requested_page_number();
	if ( $page_number <= 1 ) {
		$page_number = max( 1, (int) $query->get( 'paged' ), (int) $query->get( 'page' ) );
	}

	if ( $page_number <= 1 ) {
		return;
	}

	$is_catalog = $query->is_post_type_archive( 'product' ) || $query->is_tax( 'product_cat' ) || $query->is_tax( 'product_tag' );

	$post_type = $query->get( 'post_type' );
	if ( 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) ) {
		$is_catalog = true;
	}

	$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	if ( $shop_id && ( (int) $query->get( 'page_id' ) === $shop_id || $query->is_page( $shop_id ) ) ) {
		$is_catalog = true;
	}

	if ( ! $is_catalog && function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
		$is_catalog = true;
	}

	if ( ! $is_catalog && rezajordaan_is_catalog_paged_request() ) {
		$is_catalog = true;
	}

	if ( ! $is_catalog ) {
		return;
	}

	$query->set( 'paged', $page_number );
	$query->is_paged = true;
	$query->is_404   = false;
}
add_action( 'pre_get_posts', 'rezajordaan_apply_catalog_paged_query', 9 );
add_action( 'woocommerce_product_query', 'rezajordaan_apply_catalog_paged_query', 20 );

/**
 * Shop is a Page: WordPress 404s /shop/page/2/ when there is no <!--nextpage-->.
 *
 * @param bool     $preempt Whether to short-circuit 404 handling.
 * @param WP_Query $wp_query Query object.
 * @return bool
 */
function rezajordaan_pre_handle_catalog_404( $preempt, $wp_query ) {
	if ( $preempt || ! $wp_query instanceof WP_Query || ! $wp_query->is_main_query() ) {
		return $preempt;
	}

	if ( ! rezajordaan_is_catalog_paged_request() ) {
		$page_number = max( 1, (int) $wp_query->get( 'paged' ), (int) $wp_query->get( 'page' ) );
		$is_shop     = function_exists( 'is_shop' ) && is_shop();
		$is_tax      = function_exists( 'is_product_taxonomy' ) && is_product_taxonomy();

		if ( $page_number <= 1 || ( ! $is_shop && ! $is_tax ) ) {
			return $preempt;
		}
	}

	$wp_query->is_404 = false;
	status_header( 200 );

	return true;
}
add_filter( 'pre_handle_404', 'rezajordaan_pre_handle_catalog_404', 5, 2 );

/**
 * Stop WordPress from 301ing catalog /page/2/ back to page 1.
 *
 * @param string|false $redirect_url  Canonical redirect target.
 * @param string       $requested_url Original request.
 * @return string|false
 */
function rezajordaan_preserve_catalog_pagination( $redirect_url, $requested_url ) {
	if ( ! $redirect_url ) {
		return $redirect_url;
	}

	if ( rezajordaan_is_catalog_paged_request() ) {
		return false;
	}

	$page_number = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	if ( $page_number <= 1 ) {
		$path = (string) wp_parse_url( (string) $requested_url, PHP_URL_PATH );
		if ( ! preg_match( '#/page/[0-9]+/?$#u', rawurldecode( $path ) ) ) {
			return $redirect_url;
		}
	}

	if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'rezajordaan_preserve_catalog_pagination', 999, 2 );

/**
 * Flush rewrite rules once after this pagination fix lands.
 */
function rezajordaan_maybe_flush_catalog_rewrites() {
	$stored = get_option( 'rezajordaan_rewrite_version', '' );

	if ( $stored === REZAJORDAAN_VERSION ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'rezajordaan_rewrite_version', REZAJORDAAN_VERSION );
}
add_action( 'init', 'rezajordaan_maybe_flush_catalog_rewrites', 99 );
add_action( 'after_switch_theme', 'flush_rewrite_rules' );
