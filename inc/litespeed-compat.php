<?php
/**
 * LiteSpeed Cache compatibility for WooCommerce cart/checkout/shipping AJAX.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclude WooCommerce dynamic URIs from LiteSpeed page cache.
 *
 * @param string[] $uris Excluded URI patterns.
 * @return string[]
 */
function rezajordaan_litespeed_exclude_uris( $uris ) {
	$uris[] = '/cart';
	$uris[] = '/checkout';
	$uris[] = '/my-account';
	$uris[] = '/سبد-خرید';
	$uris[] = '/تسویه-حساب';
	$uris[] = 'wc-ajax';
	$uris[] = '/?wc-ajax=';
	$uris[] = 'admin-ajax.php';

	return array_values( array_unique( $uris ) );
}
add_filter( 'litespeed_cache_exclude_uri', 'rezajordaan_litespeed_exclude_uris' );

/**
 * Never cache cart/checkout responses in LiteSpeed.
 */
function rezajordaan_litespeed_nocache_wc_pages() {
	if ( ! function_exists( 'is_cart' ) ) {
		return;
	}

	if ( is_cart() || is_checkout() || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
		do_action( 'litespeed_control_set_nocache', 'rezajordaan woocommerce page' );
	}
}
add_action( 'litespeed_control_finalize', 'rezajordaan_litespeed_nocache_wc_pages' );

/**
 * Disable LiteSpeed page cache for WooCommerce AJAX and native cart mutations.
 */
function rezajordaan_litespeed_nocache_wc_ajax() {
	$wc_ajax = isset( $_REQUEST['wc-ajax'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ) : '';
	$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	$is_native_add_to_cart = 'POST' === $method && isset( $_POST['add-to-cart'] );
	$is_wc_admin_ajax      = wp_doing_ajax() && 0 === strpos( $action, 'woocommerce_' );

	if ( '' !== $wc_ajax || $is_wc_admin_ajax || $is_native_add_to_cart ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'rezajordaan wc cart mutation' );
	}
}
add_action( 'init', 'rezajordaan_litespeed_nocache_wc_ajax', 0 );

/**
 * Keep WooCommerce/Checkout JS out of LiteSpeed defer/combine (breaks shipping refresh).
 *
 * @param string[] $excludes Script path fragments to exclude.
 * @return string[]
 */
function rezajordaan_litespeed_js_excludes( $excludes ) {
	$patterns = array(
		'woocommerce',
		'wc-checkout',
		'wc-cart',
		'cart-fragments',
		'js.cookie',
		'jquery.blockUI',
		'select2',
		'persian',
		'pws',
		'rezajordaan-checkout',
	);

	return array_values( array_unique( array_merge( (array) $excludes, $patterns ) ) );
}
add_filter( 'litespeed_optimize_js_excludes', 'rezajordaan_litespeed_js_excludes' );
add_filter( 'litespeed_optm_js_defer_exc', 'rezajordaan_litespeed_js_excludes' );
add_filter( 'litespeed_optm_js_exc', 'rezajordaan_litespeed_js_excludes' );

/**
 * Optional: disable LiteSpeed CSS combine on checkout (prevents shipping list glitches).
 *
 * @param bool $allow Whether CSS combine is allowed on this page.
 * @return bool
 */
function rezajordaan_litespeed_allow_css_combine( $allow ) {
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) {
		return false;
	}

	return $allow;
}
add_filter( 'litespeed_can_optm', 'rezajordaan_litespeed_allow_css_combine' );
