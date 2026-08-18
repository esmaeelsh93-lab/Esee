<?php
/**
 * Compatibility shims for common WooCommerce plugins on archive cards.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep archive cards clean when third-party plugins inject loop markup.
 */
function rezajordaan_setup_plugin_compat() {
	if ( ! function_exists( 'rezajordaan_is_archive_product_card' ) || ! rezajordaan_is_archive_product_card() ) {
		return;
	}

	// Standard WooCommerce loop hooks that duplicate price/title output on custom cards.
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );

	// TO3EDEV cash purchase plugin and similar badge injectors.
	$loop_hooks = array(
		'woocommerce_before_shop_loop_item',
		'woocommerce_before_shop_loop_item_title',
		'woocommerce_shop_loop_item_title',
		'woocommerce_after_shop_loop_item_title',
		'woocommerce_after_shop_loop_item',
	);

	foreach ( $loop_hooks as $hook ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$callable = $callback['function'];

				if ( ! is_array( $callable ) || ! is_object( $callable[0] ) ) {
					continue;
				}

				$class_name = get_class( $callable[0] );

				if (
					false !== stripos( $class_name, 'to3e' )
					|| false !== stripos( $class_name, 'digipay' )
					|| false !== stripos( $class_name, 'snapppay' )
					|| false !== stripos( $class_name, 'torobpay' )
				) {
					remove_action( $hook, $callable, $priority );
				}
			}
		}
	}
}
add_action( 'wp', 'rezajordaan_setup_plugin_compat', 20 );

/**
 * Strip plugin overlays from archive card thumbnails.
 *
 * @param string     $image       Image HTML.
 * @param WC_Product $product     Product object.
 * @param string     $size        Image size.
 * @param array      $attr        Attributes.
 * @param bool       $placeholder Placeholder flag.
 * @return string
 */
function rezajordaan_archive_product_image( $image, $product, $size, $attr, $placeholder ) {
	if ( ! rezajordaan_is_archive_product_card() ) {
		return $image;
	}

	if ( preg_match( '/<img\b[^>]*>/i', $image, $matches ) ) {
		return $matches[0];
	}

	return $image;
}
add_filter( 'woocommerce_product_get_image', 'rezajordaan_archive_product_image', 100, 5 );

/**
 * Suppress duplicate price HTML from payment/discount plugins in loops.
 *
 * @param string     $price   Price HTML.
 * @param WC_Product $product Product object.
 * @return string
 */
function rezajordaan_suppress_archive_loop_price_html( $price, $product ) {
	if ( ! rezajordaan_is_archive_product_card() || doing_action( 'woocommerce_single_product_summary' ) ) {
		return $price;
	}

	// Our card template renders its own price box; hide plugin-injected loop prices.
	if ( wc_get_loop_prop( 'is_shortcode' ) ) {
		return $price;
	}

	return '';
}
add_filter( 'woocommerce_get_price_html', 'rezajordaan_suppress_archive_loop_price_html', 100, 2 );
