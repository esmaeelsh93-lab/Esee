<?php
/**
 * Keep WooCommerce shop and taxonomy pagination on the requested page.
 *
 * Nested product categories (/parent/child/page/2/) are parsed as if "page"
 * were a child term, then WordPress 301s back to page 1. The shop is a Page,
 * so /shop/page/2/ is treated as <!--nextpage--> content and also redirected.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current request path, decoded so Persian category slugs match.
 *
 * @param string $url Optional absolute or relative URL.
 * @return string
 */
function rezajordaan_requested_path( $url = '' ) {
	if ( '' === $url ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	} else {
		$uri = $url;
	}

	$path = wp_parse_url( $uri, PHP_URL_PATH );

	return is_string( $path ) ? rawurldecode( $path ) : '';
}

/**
 * Page number from pretty permalinks or query args.
 *
 * @param string $url Optional URL to inspect.
 * @return int
 */
function rezajordaan_requested_page_number( $url = '' ) {
	$path = rezajordaan_requested_path( $url );

	if ( preg_match( '#/page/([0-9]+)/?$#u', $path, $matches ) ) {
		return max( 1, (int) $matches[1] );
	}

	foreach ( array( 'paged', 'page' ) as $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			return max( 1, absint( wp_unslash( $_GET[ $key ] ) ) );
		}
	}

	if ( function_exists( 'get_query_var' ) ) {
		return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	}

	return 1;
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
		foreach ( array( 'category_rewrite_slug', 'tag_rewrite_slug' ) as $key ) {
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

	return array_values( array_unique( array_filter( $bases ) ) );
}

/**
 * Whether a URL path belongs to the shop or a product taxonomy archive.
 *
 * @param string $path Decoded path.
 * @return bool
 */
function rezajordaan_path_is_catalog( $path ) {
	$path = '/' . trim( (string) $path, '/' ) . '/';

	foreach ( rezajordaan_catalog_path_bases() as $base ) {
		$base = trim( $base, '/' );
		if ( '' === $base ) {
			continue;
		}

		if ( preg_match( '#/' . preg_quote( $base, '#' ) . '/#u', $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Shop page URI slug (usually shop).
 *
 * @return string
 */
function rezajordaan_shop_path_slug() {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$shop_id = (int) wc_get_page_id( 'shop' );
		if ( $shop_id > 0 ) {
			$shop_uri = get_page_uri( $shop_id );
			if ( $shop_uri ) {
				return trim( $shop_uri, '/' );
			}
		}
	}

	return 'shop';
}

/**
 * Put catalog /page/N/ rewrite rules first so nested categories keep working.
 */
function rezajordaan_add_catalog_pagination_rewrites() {
	$category_bases = array( 'product-category' );
	$tag_bases      = array( 'product-tag' );

	if ( function_exists( 'wc_get_permalink_structure' ) ) {
		$permalinks = wc_get_permalink_structure();
		if ( ! empty( $permalinks['category_rewrite_slug'] ) ) {
			$category_bases[] = trim( (string) $permalinks['category_rewrite_slug'], '/' );
		}
		if ( ! empty( $permalinks['tag_rewrite_slug'] ) ) {
			$tag_bases[] = trim( (string) $permalinks['tag_rewrite_slug'], '/' );
		}
	}

	foreach ( array_unique( array_filter( $category_bases ) ) as $base ) {
		add_rewrite_rule(
			$base . '/(.+?)/page/?([0-9]{1,})/?$',
			'index.php?product_cat=$matches[1]&paged=$matches[2]',
			'top'
		);
	}

	foreach ( array_unique( array_filter( $tag_bases ) ) as $base ) {
		add_rewrite_rule(
			$base . '/(.+?)/page/?([0-9]{1,})/?$',
			'index.php?product_tag=$matches[1]&paged=$matches[2]',
			'top'
		);
	}

	$shop = rezajordaan_shop_path_slug();
	if ( $shop ) {
		add_rewrite_rule(
			$shop . '/page/?([0-9]{1,})/?$',
			'index.php?post_type=product&paged=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'rezajordaan_add_catalog_pagination_rewrites', 8 );

/**
 * Strip a trailing /page or /page/N that WordPress folded into the term slug.
 *
 * @param string $value Term path from rewrite.
 * @return array{0:string,1:int} Term path and page number (0 if none).
 */
function rezajordaan_split_term_paged_path( $value ) {
	$value = rawurldecode( (string) $value );
	$page  = 0;

	if ( preg_match( '#^(.+)/page/([0-9]+)$#u', $value, $matches ) ) {
		return array( $matches[1], (int) $matches[2] );
	}

	if ( preg_match( '#^(.+)/page$#u', $value, $matches ) ) {
		return array( $matches[1], 0 );
	}

	return array( $value, $page );
}

/**
 * Map /page/N/ onto the query vars WordPress and WooCommerce actually read.
 *
 * @param array<string, mixed> $vars Parsed request vars.
 * @return array<string, mixed>
 */
function rezajordaan_catalog_request_vars( $vars ) {
	$page_number = rezajordaan_requested_page_number();

	if ( ! empty( $vars['paged'] ) ) {
		$page_number = max( $page_number, (int) $vars['paged'] );
	}

	if ( ! empty( $vars['page'] ) ) {
		$page_number = max( $page_number, (int) $vars['page'] );
	}

	foreach ( array( 'product_cat', 'product_tag' ) as $tax_var ) {
		if ( empty( $vars[ $tax_var ] ) || ! is_string( $vars[ $tax_var ] ) ) {
			continue;
		}

		list( $term_path, $term_page ) = rezajordaan_split_term_paged_path( $vars[ $tax_var ] );
		$vars[ $tax_var ]              = $term_path;
		$page_number                   = max( $page_number, $term_page );
	}

	if ( ! empty( $vars['pagename'] ) && is_string( $vars['pagename'] ) && preg_match( '#^(.+)/page/([0-9]+)$#u', $vars['pagename'], $matches ) ) {
		$vars['pagename'] = $matches[1];
		$page_number      = max( $page_number, (int) $matches[2] );
	}

	$shop_id  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
	$shop_uri = $shop_id > 0 ? trim( (string) get_page_uri( $shop_id ), '/' ) : 'shop';

	$is_catalog = isset( $vars['product_cat'] ) || isset( $vars['product_tag'] ) || ( isset( $vars['post_type'] ) && 'product' === $vars['post_type'] );

	if ( $shop_id && ! empty( $vars['pagename'] ) && trim( (string) $vars['pagename'], '/' ) === $shop_uri ) {
		$is_catalog       = true;
		$vars['page_id']  = $shop_id;
		$vars['pagename'] = $shop_uri;
	}

	if ( ! $is_catalog && rezajordaan_path_is_catalog( rezajordaan_requested_path() ) ) {
		$is_catalog = true;
	}

	if ( $is_catalog && $page_number > 1 ) {
		$vars['paged'] = $page_number;
		$vars['page']  = $page_number;
	}

	return $vars;
}
add_filter( 'request', 'rezajordaan_catalog_request_vars', 1 );

/**
 * Force the main catalog query onto the requested page.
 *
 * @param WP_Query $query Current query.
 */
function rezajordaan_apply_catalog_paged_query( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}

	$path        = rezajordaan_requested_path();
	$page_number = rezajordaan_requested_page_number();
	$page_number = max( $page_number, (int) $query->get( 'paged' ), (int) $query->get( 'page' ) );

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

	if ( ! $is_catalog && rezajordaan_path_is_catalog( $path ) ) {
		$is_catalog = true;
	}

	if ( ! $is_catalog || $page_number <= 1 ) {
		return;
	}

	$query->set( 'paged', $page_number );
	$query->is_paged = true;
	$query->is_404   = false;
}
add_action( 'pre_get_posts', 'rezajordaan_apply_catalog_paged_query', 1 );
add_action( 'woocommerce_product_query', 'rezajordaan_apply_catalog_paged_query', 1 );

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

	$path        = rezajordaan_requested_path();
	$page_number = rezajordaan_requested_page_number();

	if ( $page_number <= 1 || ! rezajordaan_path_is_catalog( $path ) ) {
		return $preempt;
	}

	$wp_query->is_404 = false;
	status_header( 200 );

	return true;
}
add_filter( 'pre_handle_404', 'rezajordaan_pre_handle_catalog_404', 1, 2 );

/**
 * Stop WordPress from 301ing catalog /page/2/ back to page 1.
 *
 * Compare the requested URL with the canonical target so nested categories
 * keep /page/N/ even when is_shop()/is_product_taxonomy() are still false.
 *
 * @param string|false $redirect_url  Canonical redirect target.
 * @param string       $requested_url Original request.
 * @return string|false
 */
function rezajordaan_preserve_catalog_pagination( $redirect_url, $requested_url ) {
	if ( ! $redirect_url ) {
		return $redirect_url;
	}

	$requested_path = rezajordaan_requested_path( $requested_url );
	if ( '' === $requested_path ) {
		$requested_path = rezajordaan_requested_path();
	}

	if ( ! preg_match( '#/page/([0-9]+)/?$#u', $requested_path, $matches ) ) {
		return $redirect_url;
	}

	$page_number = (int) $matches[1];
	if ( $page_number <= 1 || ! rezajordaan_path_is_catalog( $requested_path ) ) {
		return $redirect_url;
	}

	$redirect_path = rezajordaan_requested_path( $redirect_url );
	if ( ! preg_match( '#/page/' . $page_number . '/?$#u', $redirect_path ) ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'rezajordaan_preserve_catalog_pagination', 0, 2 );
add_filter( 'redirect_canonical', 'rezajordaan_preserve_catalog_pagination', 9999, 2 );

/**
 * Keep /page/N/ when a plugin redirects an old category slug to a new one.
 *
 * A rule like شلوار → pants must become شلوار/page/2/ → pants/page/2/,
 * not pants/ (which looks like pagination is broken).
 *
 * @param string $location Redirect target.
 * @param int    $status   HTTP status.
 * @return string
 */
function rezajordaan_preserve_paged_on_redirect( $location, $status ) {
	if ( $status < 300 || $status > 399 || ! is_string( $location ) || '' === $location ) {
		return $location;
	}

	$requested_path = rezajordaan_requested_path();
	if ( ! preg_match( '#/page/([0-9]+)/?$#u', $requested_path, $matches ) ) {
		return $location;
	}

	$page_number = (int) $matches[1];
	if ( $page_number < 2 || ! rezajordaan_path_is_catalog( $requested_path ) ) {
		return $location;
	}

	$destination_path = rezajordaan_requested_path( $location );
	if ( '' === $destination_path || preg_match( '#/page/[0-9]+/?$#u', $destination_path ) ) {
		return $location;
	}

	if ( ! rezajordaan_path_is_catalog( $destination_path ) ) {
		return $location;
	}

	$parts = wp_parse_url( $location );
	if ( ! is_array( $parts ) ) {
		return $location;
	}

	$path  = trailingslashit( isset( $parts['path'] ) ? $parts['path'] : '/' );
	$built = $path . 'page/' . $page_number . '/';

	if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
		$built = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $built;
	}

	if ( ! empty( $parts['query'] ) ) {
		$built .= '?' . $parts['query'];
	}

	return $built;
}
add_filter( 'wp_redirect', 'rezajordaan_preserve_paged_on_redirect', 1000, 2 );

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
