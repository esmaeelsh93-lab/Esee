<?php
/**
 * Classic cart/checkout rendering and page helpers.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Blocks break many Iranian shipping plugins (MahdiY PWS, etc.).
 * Render the classic shortcodes instead of block templates.
 *
 * @param string $content Page content.
 * @return string
 */
function rezajordaan_classic_cart_checkout_content( $content ) {
	if ( ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}

	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return do_shortcode( '[woocommerce_cart]' );
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		return do_shortcode( '[woocommerce_checkout]' );
	}

	return $content;
}
add_filter( 'the_content', 'rezajordaan_classic_cart_checkout_content', 5 );

/**
 * One-time migration: replace block markup on cart/checkout pages in the database.
 */
function rezajordaan_maybe_migrate_wc_pages_to_classic() {
	$migrated_version = get_option( 'rezajordaan_wc_pages_migrated', '' );

	if ( $migrated_version === REZAJORDAAN_VERSION ) {
		return;
	}

	$pages = array(
		'cart'     => '[woocommerce_cart]',
		'checkout' => '[woocommerce_checkout]',
	);

	foreach ( $pages as $slug => $shortcode ) {
		$page = get_page_by_path( $slug );

		if ( ! $page instanceof WP_Post ) {
			continue;
		}

		if ( has_blocks( $page->post_content ) || false !== strpos( $page->post_content, 'wp:woocommerce/' ) ) {
			wp_update_post(
				array(
					'ID'           => $page->ID,
					'post_content' => $shortcode,
				)
			);
		}
	}

	update_option( 'rezajordaan_wc_pages_migrated', REZAJORDAAN_VERSION );
}
add_action( 'after_setup_theme', 'rezajordaan_maybe_migrate_wc_pages_to_classic', 20 );

/**
 * Reduce block assets on cart/checkout when classic templates are used.
 */
function rezajordaan_trim_cart_checkout_block_assets() {
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
		return;
	}

	if ( ! is_cart() && ! is_checkout() ) {
		return;
	}

	wp_dequeue_style( 'wc-blocks-style' );
	wp_dequeue_style( 'wc-blocks-vendors-style' );
	wp_dequeue_style( 'wc-blocks-style-cart' );
	wp_dequeue_style( 'wc-blocks-style-checkout' );
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_trim_cart_checkout_block_assets', 100 );

/**
 * Add body classes for cart and checkout styling hooks.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function rezajordaan_cart_checkout_body_classes( $classes ) {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$classes[] = 'rezajordaan-cart';
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		$classes[] = 'rezajordaan-checkout';
	}

	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$classes[] = 'rezajordaan-account';
	}

	return $classes;
}
add_filter( 'body_class', 'rezajordaan_cart_checkout_body_classes' );

/**
 * Checkout pages must stay dynamic (shipping depends on city/state).
 */
function rezajordaan_prevent_checkout_page_cache() {
	if ( ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
		return;
	}

	if ( ! is_cart() && ! is_checkout() ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
		define( 'DONOTCACHEOBJECT', true );
	}
}
add_action( 'template_redirect', 'rezajordaan_prevent_checkout_page_cache', 0 );

/**
 * Ask common cache plugins (WP Rocket, etc.) not to cache cart/checkout.
 *
 * @param string[] $uris Rejected URI patterns.
 * @return string[]
 */
function rezajordaan_cache_reject_cart_checkout( $uris ) {
	$uris[] = '/cart(.*)';
	$uris[] = '/checkout(.*)';
	$uris[] = '/سبد-خرید(.*)';
	$uris[] = '/تسویه-حساب(.*)';

	return $uris;
}
add_filter( 'rocket_cache_reject_uri', 'rezajordaan_cache_reject_cart_checkout' );
add_filter( 'litespeed_cache_exclude_uri', 'rezajordaan_cache_reject_cart_checkout' );

/**
 * Refresh shipping methods when Iranian address fields change (mobile-friendly).
 */
function rezajordaan_enqueue_checkout_script() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	wp_enqueue_script( 'wc-checkout' );
	wp_enqueue_script(
		'rezajordaan-checkout',
		get_template_directory_uri() . '/assets/js/checkout.js',
		array( 'jquery', 'wc-checkout' ),
		REZAJORDAAN_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_enqueue_checkout_script', 30 );
