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
 * Sanitize sale slider settings.
 *
 * @param mixed $input Raw input.
 * @return array<string,mixed>
 */
function bahar_shop_sanitize_sale_slider( $input ) {
	$out = bahar_shop_sale_slider_defaults();
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
 * Query on-sale products for homepage slider.
 *
 * @param int $limit Max products.
 * @return WP_Query
 */
function bahar_shop_get_sale_products( $limit = 12 ) {
	return new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $limit ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_sale_price',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
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
