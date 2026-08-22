<?php
/**
 * Catalog "load more" instead of numbered pages.
 *
 * Numbered /page/2/ URLs conflict with nested/Persian category slugs and
 * the shop Page, causing 301 loops. Archives now append the next batch
 * of cards in place.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Current request path, decoded so Persian slugs match.
 *
 * @param string $url Optional URL.
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
 * Shop page URI slug.
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
 * Whether a path is a shop or product taxonomy archive.
 *
 * @param string $path Decoded path.
 * @return bool
 */
function rezajordaan_path_is_catalog( $path ) {
	$path  = '/' . trim( (string) $path, '/' ) . '/';
	$bases = array( 'product-category', 'product-tag', rezajordaan_shop_path_slug() );

	if ( function_exists( 'wc_get_permalink_structure' ) ) {
		$permalinks = wc_get_permalink_structure();
		foreach ( array( 'category_rewrite_slug', 'tag_rewrite_slug' ) as $key ) {
			if ( ! empty( $permalinks[ $key ] ) ) {
				$bases[] = trim( (string) $permalinks[ $key ], '/' );
			}
		}
	}

	foreach ( array_unique( array_filter( $bases ) ) as $base ) {
		if ( preg_match( '#/' . preg_quote( $base, '#' ) . '/#u', $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Send leftover /page/N/ catalog links to the first screen (load-more lives there).
 */
function rezajordaan_flatten_catalog_paged_urls() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	$path = rezajordaan_requested_path();
	if ( ! preg_match( '#/page/([0-9]+)/?$#u', $path, $matches ) ) {
		return;
	}

	if ( (int) $matches[1] < 2 || ! rezajordaan_path_is_catalog( $path ) ) {
		return;
	}

	$clean = preg_replace( '#/page/[0-9]+/?$#u', '/', $path );
	$clean = user_trailingslashit( $clean );
	$target = home_url( $clean );

	$query = $_GET;
	unset( $query['paged'], $query['page'] );
	if ( $query ) {
		$target = add_query_arg( wc_clean( wp_unslash( $query ) ), $target );
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'rezajordaan_flatten_catalog_paged_urls', 0 );

/**
 * Replace numbered pagination with a load-more control.
 */
function rezajordaan_replace_catalog_pagination() {
	if ( ! function_exists( 'woocommerce_pagination' ) ) {
		return;
	}

	remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
	add_action( 'woocommerce_after_shop_loop', 'rezajordaan_render_load_more', 10 );
}
add_action( 'wp', 'rezajordaan_replace_catalog_pagination' );

/**
 * Size-attribute tax query from request values.
 *
 * @param array $selected Raw size term ids.
 * @return array
 */
function rezajordaan_size_filter_tax_query( $selected ) {
	$grouped = array();

	foreach ( (array) $selected as $value ) {
		$term_id = is_scalar( $value ) ? absint( $value ) : 0;
		$term    = $term_id ? get_term( $term_id ) : null;

		if ( $term instanceof WP_Term && str_starts_with( $term->taxonomy, 'pa_' ) ) {
			$grouped[ $term->taxonomy ][] = $term_id;
		}
	}

	$tax_query = array();
	foreach ( $grouped as $taxonomy => $term_ids ) {
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => array_unique( array_map( 'absint', $term_ids ) ),
			'operator' => 'IN',
		);
	}

	return $tax_query;
}

/**
 * Render the load-more button under the product grid.
 */
function rezajordaan_render_load_more() {
	if ( ! function_exists( 'rezajordaan_is_archive_product_card' ) || ! rezajordaan_is_archive_product_card() ) {
		return;
	}

	$total_pages = (int) wc_get_loop_prop( 'total_pages' );
	if ( $total_pages < 2 ) {
		return;
	}

	$term     = get_queried_object();
	$taxonomy = '';
	$term_id  = 0;

	if ( $term instanceof WP_Term && in_array( $term->taxonomy, array( 'product_cat', 'product_tag' ), true ) ) {
		$taxonomy = $term->taxonomy;
		$term_id  = (int) $term->term_id;
	}

	$sizes = isset( $_GET['rz_size'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['rz_size'] ) ) ) ) : array();

	$payload = array(
		'page'      => 1,
		'max'       => $total_pages,
		'taxonomy'  => $taxonomy,
		'term'      => $term_id,
		'orderby'   => isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : '',
		'min_price' => isset( $_GET['min_price'] ) ? wc_clean( wp_unslash( $_GET['min_price'] ) ) : '',
		'max_price' => isset( $_GET['max_price'] ) ? wc_clean( wp_unslash( $_GET['max_price'] ) ) : '',
		'sizes'     => $sizes,
		'search'    => is_search() ? get_search_query() : '',
	);
	?>
	<div class="rj-load-more" data-load-more>
		<button
			type="button"
			class="rj-button rj-button--primary"
			data-load-more-button
			data-load-more-config="<?php echo esc_attr( wp_json_encode( $payload ) ); ?>"
		>
			<span><?php esc_html_e( 'بارگذاری بیشتر', 'rezajordaan' ); ?></span>
		</button>
	</div>
	<?php
}

/**
 * AJAX: next batch of archive product cards.
 */
function rezajordaan_ajax_load_more_products() {
	$nonce_ok = isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rezajordaan_load_more' );
	if ( ! $nonce_ok ) {
		$referer   = wp_get_raw_referer();
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$ref_host  = $referer ? (string) wp_parse_url( $referer, PHP_URL_HOST ) : '';
		if ( ! $home_host || $ref_host !== $home_host ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
	}

	if ( ! function_exists( 'wc_get_products' ) ) {
		wp_send_json_error( array( 'message' => 'woocommerce' ), 400 );
	}

	$GLOBALS['rezajordaan_ajax_archive_cards'] = true;

	$page     = max( 2, absint( wp_unslash( $_POST['page'] ?? 0 ) ) );
	$max      = max( 1, absint( wp_unslash( $_POST['max'] ?? 0 ) ) );
	$term_id  = absint( wp_unslash( $_POST['term'] ?? 0 ) );
	$taxonomy = sanitize_key( wp_unslash( $_POST['taxonomy'] ?? '' ) );
	$orderby  = isset( $_POST['orderby'] ) ? wc_clean( wp_unslash( $_POST['orderby'] ) ) : '';
	$search   = isset( $_POST['search'] ) ? wc_clean( wp_unslash( $_POST['search'] ) ) : '';
	$sizes    = isset( $_POST['sizes'] ) ? (array) wp_unslash( $_POST['sizes'] ) : array();
	$min      = isset( $_POST['min_price'] ) ? (float) wc_clean( wp_unslash( $_POST['min_price'] ) ) : 0;
	$max_p    = isset( $_POST['max_price'] ) ? (float) wc_clean( wp_unslash( $_POST['max_price'] ) ) : 0;
	$per_page = (int) apply_filters( 'loop_shop_per_page', 48 );

	if ( $page > $max ) {
		wp_send_json_success(
			array(
				'html'     => '',
				'page'     => $page,
				'hasMore'  => false,
			)
		);
	}

	$tax_query = array( 'relation' => 'AND' );

	if ( $term_id && taxonomy_exists( $taxonomy ) ) {
		$tax_query[] = array(
			'taxonomy'         => $taxonomy,
			'field'            => 'term_id',
			'terms'            => array( $term_id ),
			'include_children' => true,
		);
	}

	$visibility_ids = wc_get_product_visibility_term_ids();
	if ( ! empty( $visibility_ids['exclude-from-catalog'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'term_taxonomy_id',
			'terms'    => array( (int) $visibility_ids['exclude-from-catalog'] ),
			'operator' => 'NOT IN',
		);
	}

	if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! empty( $visibility_ids['outofstock'] ) ) {
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'term_taxonomy_id',
			'terms'    => array( (int) $visibility_ids['outofstock'] ),
			'operator' => 'NOT IN',
		);
	}

	$size_query = rezajordaan_size_filter_tax_query( $sizes );
	if ( $size_query ) {
		$tax_query = array_merge( $tax_query, $size_query );
	}

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'posts_per_page'      => $per_page,
		'paged'               => $page,
		'tax_query'           => $tax_query,
		'rezajordaan_load_more' => true,
	);

	if ( '' !== $search ) {
		$args['s'] = $search;
	}

	if ( $orderby ) {
		$_GET['orderby'] = $orderby;
	}

	if ( function_exists( 'WC' ) && WC()->query ) {
		$ordering = WC()->query->get_catalog_ordering_args( $orderby ? $orderby : 'date' );
	} else {
		$ordering = array(
			'orderby' => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'order'   => 'DESC',
		);
	}

	$args['orderby'] = $ordering['orderby'];
	$args['order']   = $ordering['order'];
	if ( ! empty( $ordering['meta_key'] ) ) {
		$args['meta_key'] = $ordering['meta_key'];
	}

	$price_filter = static function ( $clauses, $query ) use ( $min, $max_p ) {
		if ( ! $query instanceof WP_Query || ! $query->get( 'rezajordaan_load_more' ) ) {
			return $clauses;
		}

		if ( $min <= 0 && $max_p <= 0 ) {
			return $clauses;
		}

		global $wpdb;
		$clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} rezajordaan_price ON {$wpdb->posts}.ID = rezajordaan_price.product_id ";
		if ( $min > 0 ) {
			$clauses['where'] .= $wpdb->prepare( ' AND rezajordaan_price.min_price >= %f ', $min );
		}
		if ( $max_p > 0 ) {
			$clauses['where'] .= $wpdb->prepare( ' AND rezajordaan_price.max_price <= %f ', $max_p );
		}

		return $clauses;
	};

	add_filter( 'posts_clauses', $price_filter, 10, 2 );

	if ( function_exists( 'wc_set_loop_prop' ) ) {
		wc_set_loop_prop( 'name', '' );
		wc_set_loop_prop( 'is_shortcode', false );
	}

	$query = new WP_Query( $args );
	remove_filter( 'posts_clauses', $price_filter, 10 );

	ob_start();
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			wc_get_template_part( 'content', 'product' );
		}
	}
	$html = ob_get_clean();
	wp_reset_postdata();

	if ( function_exists( 'wc_reset_loop' ) ) {
		wc_reset_loop();
	}

	wp_send_json_success(
		array(
			'html'    => $html,
			'page'    => $page,
			'hasMore' => $page < (int) $query->max_num_pages,
		)
	);
}
add_action( 'wp_ajax_rezajordaan_load_more_products', 'rezajordaan_ajax_load_more_products' );
add_action( 'wp_ajax_nopriv_rezajordaan_load_more_products', 'rezajordaan_ajax_load_more_products' );
