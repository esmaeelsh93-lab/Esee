<?php
/**
 * AJAX product search for homepage.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_bahar_product_search', 'bahar_shop_ajax_product_search' );
add_action( 'wp_ajax_nopriv_bahar_product_search', 'bahar_shop_ajax_product_search' );

/**
 * Search products by title, SKU and category.
 */
function bahar_shop_ajax_product_search() {
	check_ajax_referer( 'bahar_search', 'nonce' );

	$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

	if ( strlen( $term ) < 2 ) {
		wp_send_json_success( array( 'items' => array() ) );
	}

	$product_ids = array();

	$title_query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			's'              => $term,
			'posts_per_page' => 8,
			'fields'         => 'ids',
		)
	);

	if ( $title_query->have_posts() ) {
		$product_ids = array_merge( $product_ids, $title_query->posts );
	}

	$sku_query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_sku',
					'value'   => $term,
					'compare' => 'LIKE',
				),
			),
		)
	);

	if ( $sku_query->have_posts() ) {
		$product_ids = array_merge( $product_ids, $sku_query->posts );
	}

	$cat_ids = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'name__like' => $term,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $cat_ids ) && ! empty( $cat_ids ) ) {
		$cat_query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 8,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $cat_ids,
					),
				),
			)
		);

		if ( $cat_query->have_posts() ) {
			$product_ids = array_merge( $product_ids, $cat_query->posts );
		}
	}

	$product_ids = array_values( array_unique( array_map( 'absint', $product_ids ) ) );
	$product_ids = array_slice( $product_ids, 0, 8 );

	$items = array();

	foreach ( $product_ids as $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			continue;
		}

		$image_id = $product->get_image_id();
		$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();

		$items[] = array(
			'id'    => $product_id,
			'title' => $product->get_name(),
			'url'   => get_permalink( $product_id ),
			'price' => wp_strip_all_tags( $product->get_price_html() ),
			'image' => $image,
			'stock' => $product->is_in_stock() ? 'in' : 'out',
		);
	}

	wp_send_json_success(
		array(
			'items' => $items,
			'term'  => $term,
			'shop'  => add_query_arg(
				array(
					'post_type' => 'product',
					's'         => $term,
				),
				home_url( '/' )
			),
		)
	);
}
