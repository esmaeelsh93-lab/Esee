<?php
/**
 * Homepage sale products slider + admin settings.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default sale slider settings.
 *
 * @return array<string,mixed>
 */
function bahar_shop_sale_slider_defaults() {
	return array(
		'enabled' => 1,
		'speed'   => 35,
	);
}

/**
 * Saved sale slider settings.
 *
 * @return array<string,mixed>
 */
function bahar_shop_sale_slider_settings() {
	$saved = get_option( 'bahar_shop_sale_slider', array() );
	$out   = wp_parse_args( is_array( $saved ) ? $saved : array(), bahar_shop_sale_slider_defaults() );
	$out['enabled'] = ! empty( $out['enabled'] ) ? 1 : 0;
	$out['speed']   = max( 10, min( 120, (int) $out['speed'] ) );
	return $out;
}

/**
 * Whether the sale slider should render.
 *
 * @return bool
 */
function bahar_shop_sale_slider_is_enabled() {
	$settings = bahar_shop_sale_slider_settings();
	return ! empty( $settings['enabled'] );
}

add_action( 'admin_init', 'bahar_shop_register_sale_slider_settings' );

/**
 * Register sale slider option (shared settings group).
 */
function bahar_shop_register_sale_slider_settings() {
	register_setting(
		'bahar_shop_settings',
		'bahar_shop_sale_slider',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bahar_shop_sanitize_sale_slider',
			'default'           => bahar_shop_sale_slider_defaults(),
		)
	);
}

/**
 * Sanitize sale slider settings — merge onto currently saved values.
 *
 * @param mixed $input Raw input.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_sale_slider( $input ) {
	$current = get_option( 'bahar_shop_sale_slider', array() );
	$out     = wp_parse_args( is_array( $current ) ? $current : array(), bahar_shop_sale_slider_defaults() );
	if ( ! is_array( $input ) ) {
		return $out;
	}
	$out['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
	if ( isset( $input['speed'] ) ) {
		$out['speed'] = max( 10, min( 120, (int) $input['speed'] ) );
	}
	return $out;
}

/**
 * Collect parent product IDs that are on sale (WooCommerce + Sabalan / variation sales).
 *
 * @param int $limit Max products.
 * @return int[]
 */
function bahar_shop_collect_on_sale_parent_ids( $limit = 12 ) {
	$limit = max( 1, min( 48, (int) $limit ) );
	$ids   = array();

	if ( function_exists( 'wc_get_product_ids_on_sale' ) ) {
		$raw = (array) wc_get_product_ids_on_sale();
		foreach ( $raw as $pid ) {
			$pid = absint( $pid );
			if ( ! $pid ) {
				continue;
			}
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			if ( $product->is_type( 'variation' ) ) {
				$parent = absint( $product->get_parent_id() );
				if ( $parent ) {
					$ids[] = $parent;
				}
				continue;
			}
			if ( 'publish' === $product->get_status() && $product->is_visible() ) {
				$ids[] = $pid;
			}
		}
	}

	// Fallback: simple products with _sale_price (covers edge cases / stale WC transient).
	if ( empty( $ids ) ) {
		$fallback = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_sale_price',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		$ids = array_map( 'absint', (array) $fallback );
	}

	// Also pull parents that have a variation with a sale price (Sabalan bulk on variations).
	global $wpdb;
	if ( isset( $wpdb ) ) {
		$variation_parents = $wpdb->get_col(
			"SELECT DISTINCT p.post_parent
			FROM {$wpdb->posts} AS p
			INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
			WHERE p.post_type = 'product_variation'
				AND p.post_status = 'publish'
				AND p.post_parent > 0
				AND pm.meta_key = '_sale_price'
				AND pm.meta_value != ''
				AND CAST(pm.meta_value AS DECIMAL(20,6)) > 0
			LIMIT 100"
		);
		if ( $variation_parents ) {
			foreach ( $variation_parents as $parent_id ) {
				$ids[] = absint( $parent_id );
			}
		}
	}

	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

	// Prefer products WooCommerce still reports as on sale.
	$verified = array();
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
			continue;
		}
		if ( ! $product->is_on_sale() ) {
			continue;
		}
		$verified[] = $id;
		if ( count( $verified ) >= $limit ) {
			break;
		}
	}

	return $verified;
}

/**
 * Query on-sale products for homepage slider (includes Sabalan / variation sales).
 *
 * @param int $limit Max products.
 * @return WP_Query
 */
function bahar_shop_get_sale_products( $limit = 12 ) {
	$limit = max( 1, (int) $limit );
	$ids   = bahar_shop_collect_on_sale_parent_ids( $limit );

	if ( empty( $ids ) ) {
		return new WP_Query(
			array(
				'post_type'      => 'product',
				'post__in'       => array( 0 ),
				'posts_per_page' => 1,
			)
		);
	}

	return new WP_Query(
		array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => true,
		)
	);
}

add_action( 'wp_enqueue_scripts', 'bahar_shop_enqueue_sale_slider_assets' );

/**
 * Enqueue sale slider assets on homepage when enabled.
 */
function bahar_shop_enqueue_sale_slider_assets() {
	if ( ! is_front_page() || ! bahar_shop_sale_slider_is_enabled() || ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	wp_enqueue_style(
		'bahar-shop-sale-slider',
		bahar_shop_asset_uri( 'assets/css/sale-slider.css' ),
		array( 'bahar-shop-main' ),
		BAHAR_SHOP_VERSION
	);

	wp_enqueue_script(
		'bahar-shop-sale-slider',
		bahar_shop_asset_uri( 'assets/js/sale-slider.js' ),
		array(),
		BAHAR_SHOP_VERSION,
		true
	);

	$settings = bahar_shop_sale_slider_settings();
	wp_localize_script(
		'bahar-shop-sale-slider',
		'baharSaleSlider',
		array(
			'speed' => (int) $settings['speed'],
		)
	);
}
