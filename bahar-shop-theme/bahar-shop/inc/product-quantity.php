<?php
/**
 * Product quantity +/- stepper on single product pages.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_before_quantity_input_field', 'bahar_shop_quantity_minus_button' );
add_action( 'woocommerce_after_quantity_input_field', 'bahar_shop_quantity_plus_button' );

/**
 * Minus button before qty input.
 */
function bahar_shop_quantity_minus_button() {
	if ( ! is_product() ) {
		return;
	}

	echo '<button type="button" class="bahar-qty-btn bahar-qty-btn--minus" aria-label="' . esc_attr__( 'کاهش تعداد', 'bahar-shop' ) . '">−</button>';
}

/**
 * Plus button after qty input.
 */
function bahar_shop_quantity_plus_button() {
	if ( ! is_product() ) {
		return;
	}

	echo '<button type="button" class="bahar-qty-btn bahar-qty-btn--plus" aria-label="' . esc_attr__( 'افزایش تعداد', 'bahar-shop' ) . '">+</button>';
}
