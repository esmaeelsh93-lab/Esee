<?php
/**
 * LiteSpeed Cache compatibility for WooCommerce and theme assets.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exclude dynamic WooCommerce URIs from LiteSpeed page cache.
 *
 * @param string[] $uris Excluded URI patterns.
 * @return string[]
 */
function rezajordaan_litespeed_exclude_uris( $uris ) {
	$patterns = array(
		'/cart',
		'/checkout',
		'/my-account',
		'/shop',
		'/product',
		'/product-category',
		'/سبد-خرید',
		'/تسویه-حساب',
		'wc-ajax',
		'/?wc-ajax=',
		'admin-ajax.php',
		'add-to-cart',
	);

	return array_values( array_unique( array_merge( (array) $uris, $patterns ) ) );
}
add_filter( 'litespeed_cache_exclude_uri', 'rezajordaan_litespeed_exclude_uris' );

/**
 * Disable LiteSpeed page cache on catalog and checkout pages.
 */
function rezajordaan_litespeed_nocache_dynamic_pages() {
	$should_bypass = false;

	if ( function_exists( 'is_product' ) && is_product() ) {
		$should_bypass = true;
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$should_bypass = true;
	}

	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		$should_bypass = true;
	}

	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		$should_bypass = true;
	}

	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		$should_bypass = true;
	}

	if ( ! $should_bypass ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
		define( 'DONOTCACHEOBJECT', true );
	}

	do_action( 'litespeed_control_set_nocache', 'rezajordaan dynamic storefront page' );
}
add_action( 'litespeed_control_finalize', 'rezajordaan_litespeed_nocache_dynamic_pages' );

/**
 * Disable LiteSpeed page cache for WooCommerce AJAX and native cart mutations.
 */
function rezajordaan_litespeed_nocache_wc_ajax() {
	$wc_ajax             = isset( $_REQUEST['wc-ajax'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ) : '';
	$action              = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
	$method              = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
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
 * Keep theme and WooCommerce scripts out of LiteSpeed defer/combine.
 *
 * @param string[] $excludes Script path fragments to exclude.
 * @return string[]
 */
function rezajordaan_litespeed_js_excludes( $excludes ) {
	$patterns = array(
		'rezajordaan',
		'/themes/rezajordaan/',
		'woocommerce',
		'wc-add-to-cart-variation',
		'wc-checkout',
		'wc-cart',
		'cart-fragments',
		'js.cookie',
		'jquery.blockUI',
		'select2',
		'persian',
		'pws',
	);

	return array_values( array_unique( array_merge( (array) $excludes, $patterns ) ) );
}
add_filter( 'litespeed_optimize_js_excludes', 'rezajordaan_litespeed_js_excludes' );
add_filter( 'litespeed_optm_js_defer_exc', 'rezajordaan_litespeed_js_excludes' );
add_filter( 'litespeed_optm_js_exc', 'rezajordaan_litespeed_js_excludes' );

/**
 * Keep theme CSS out of LiteSpeed combine/minify buckets.
 *
 * @param string[] $excludes CSS path fragments to exclude.
 * @return string[]
 */
function rezajordaan_litespeed_css_excludes( $excludes ) {
	$patterns = array(
		'rezajordaan',
		'/themes/rezajordaan/',
		'main.css',
	);

	return array_values( array_unique( array_merge( (array) $excludes, $patterns ) ) );
}
add_filter( 'litespeed_optimize_css_excludes', 'rezajordaan_litespeed_css_excludes' );
add_filter( 'litespeed_optm_css_exc', 'rezajordaan_litespeed_css_excludes' );

/**
 * Disable LiteSpeed CSS/JS optimization on storefront pages that rely on live scripts.
 *
 * @param bool $allow Whether optimization is allowed on this page.
 * @return bool
 */
function rezajordaan_litespeed_disable_page_optimization( $allow ) {
	if ( ! function_exists( 'is_cart' ) ) {
		return $allow;
	}

	if (
		is_product()
		|| is_shop()
		|| is_product_taxonomy()
		|| is_cart()
		|| is_checkout()
		|| ( function_exists( 'is_account_page' ) && is_account_page() )
	) {
		return false;
	}

	return $allow;
}
add_filter( 'litespeed_can_optm', 'rezajordaan_litespeed_disable_page_optimization' );

/**
 * Send no-cache headers on product and shop pages.
 */
function rezajordaan_litespeed_send_nocache_headers() {
	if ( ! function_exists( 'is_product' ) ) {
		return;
	}

	if ( ! is_product() && ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	if ( headers_sent() ) {
		return;
	}

	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
	header( 'Pragma: no-cache', true );
	header( 'Vary: Cookie', false );
}
add_action( 'template_redirect', 'rezajordaan_litespeed_send_nocache_headers', 0 );

/**
 * Purge LiteSpeed cache after theme updates or activation.
 */
function rezajordaan_litespeed_purge_all() {
	if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
		LiteSpeed_Cache_API::purge_all();
	}

	do_action( 'litespeed_purge_all' );
}
add_action( 'after_switch_theme', 'rezajordaan_litespeed_purge_all', 20 );
add_action( 'upgrader_process_complete', 'rezajordaan_litespeed_purge_after_theme_update', 20, 2 );

/**
 * Purge LiteSpeed when this theme is updated from the admin.
 *
 * @param WP_Upgrader $upgrader Upgrader instance.
 * @param array       $options  Upgrade options.
 */
function rezajordaan_litespeed_purge_after_theme_update( $upgrader, $options ) {
	if ( empty( $options['type'] ) || 'theme' !== $options['type'] ) {
		return;
	}

	if ( empty( $options['themes'] ) || ! is_array( $options['themes'] ) ) {
		return;
	}

	$theme = wp_get_theme();

	foreach ( $options['themes'] as $updated_theme ) {
		if ( $theme->get_stylesheet() === $updated_theme || $theme->get_template() === $updated_theme ) {
			rezajordaan_litespeed_purge_all();
			break;
		}
	}
}

/**
 * Show the active theme version in the admin bar for quick verification.
 *
 * @param WP_Admin_Bar $admin_bar Admin bar instance.
 */
function rezajordaan_admin_bar_theme_version( $admin_bar ) {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'switch_themes' ) ) {
		return;
	}

	$admin_bar->add_node(
		array(
			'id'    => 'rezajordaan-theme-version',
			'title' => 'RJ Theme v' . REZAJORDAAN_VERSION,
			'href'  => admin_url( 'themes.php' ),
			'meta'  => array(
				'title' => __( 'نسخه فعال قالب رضا جردن', 'rezajordaan' ),
			),
		)
	);
}
add_action( 'admin_bar_menu', 'rezajordaan_admin_bar_theme_version', 120 );

/**
 * Add a version marker to the HTML for troubleshooting cached pages.
 */
function rezajordaan_theme_version_marker() {
	echo "\n<!-- RezaJordaan Theme " . esc_html( REZAJORDAAN_VERSION ) . " -->\n";
}
add_action( 'wp_footer', 'rezajordaan_theme_version_marker', 99 );
