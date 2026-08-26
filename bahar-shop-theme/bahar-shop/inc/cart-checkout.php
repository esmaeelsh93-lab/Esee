<?php
/**
 * Cart & checkout layout hooks.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_before_cart', 'bahar_cart_layout_open', 5 );

/**
 * Open cart page layout wrapper.
 */
function bahar_cart_layout_open() {
	echo '<div class="bahar-cart-layout">';
}

add_action( 'woocommerce_after_cart', 'bahar_cart_layout_close', 50 );

/**
 * Close cart page layout wrapper.
 */
function bahar_cart_layout_close() {
	echo '</div>';
}

add_action( 'woocommerce_before_checkout_form', 'bahar_checkout_layout_open', 5 );

/**
 * Open checkout layout wrapper.
 */
function bahar_checkout_layout_open() {
	echo '<div class="bahar-checkout-layout">';
}

add_action( 'woocommerce_after_checkout_form', 'bahar_checkout_layout_close', 50 );

/**
 * Close checkout layout wrapper.
 */
function bahar_checkout_layout_close() {
	echo '</div>';
}

add_filter( 'woocommerce_cart_item_name', 'bahar_cart_item_name_markup', 10, 3 );

/**
 * Wrap cart item name for cleaner stacking.
 *
 * @param string $name    Product name HTML.
 * @param array  $cart_item Cart item.
 * @param string $cart_item_key Key.
 * @return string
 */
function bahar_cart_item_name_markup( $name, $cart_item, $cart_item_key ) {
	return '<div class="bahar-cart-item__name">' . $name . '</div>';
}
