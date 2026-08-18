<?php
/**
 * WooCommerce session, cart fragments, and cache-plugin compatibility.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load dedicated cart/checkout templates (avoid the_content filter side effects).
 *
 * @param string $template Current template path.
 * @return string
 */
function rezajordaan_cart_checkout_template( $template ) {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		$cart_template = get_template_directory() . '/templates/cart-page.php';
		if ( file_exists( $cart_template ) ) {
			return $cart_template;
		}
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		$checkout_template = get_template_directory() . '/templates/checkout-page.php';
		if ( file_exists( $checkout_template ) ) {
			return $checkout_template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'rezajordaan_cart_checkout_template', 99 );

/**
 * Keep WooCommerce cart fragments active site-wide.
 */
function rezajordaan_enqueue_cart_fragments() {
	if ( ! function_exists( 'WC' ) || is_admin() ) {
		return;
	}

	wp_enqueue_script( 'wc-cart-fragments' );
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_enqueue_cart_fragments', 20 );

/**
 * Update header mini-cart markup after AJAX add-to-cart.
 *
 * @param array $fragments Cart fragments.
 * @return array
 */
function rezajordaan_cart_fragments( $fragments ) {
	ob_start();
	rezajordaan_render_header_cart_link();
	$fragments['a.rj-header-cart'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'rezajordaan_cart_fragments' );

/**
 * Render the header cart link with live item count.
 */
function rezajordaan_render_header_cart_link() {
	if ( ! function_exists( 'wc_get_cart_url' ) ) {
		return;
	}

	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	?>
	<a class="rj-header-cart<?php echo $count > 0 ? ' is-active' : ''; ?>" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php echo esc_attr( sprintf( _n( 'سبد خرید، %d محصول', 'سبد خرید، %d محصول', $count, 'rezajordaan' ), $count ) ); ?>">
		<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
		<span class="rj-header-cart__count" data-cart-count><?php echo esc_html( (string) $count ); ?></span>
	</a>
	<?php
}

/**
 * WP Rocket / LiteSpeed: keep WooCommerce session cookies dynamic.
 *
 * @param string[] $cookies Cookie names.
 * @return string[]
 */
function rezajordaan_cache_dynamic_cookies( $cookies ) {
	$cookies[] = 'woocommerce_items_in_cart';
	$cookies[] = 'woocommerce_cart_hash';

	return $cookies;
}
add_filter( 'rocket_cache_dynamic_cookies', 'rezajordaan_cache_dynamic_cookies' );
add_filter( 'litespeed_cache_vary_cookies', 'rezajordaan_cache_dynamic_cookies' );

/**
 * WP Rocket: never delay/defer WooCommerce cart scripts.
 *
 * @param string[] $excluded Excluded script paths.
 * @return string[]
 */
function rezajordaan_rocket_exclude_wc_scripts( $excluded ) {
	$excluded[] = '/woocommerce/assets/js/frontend/cart-fragments.min.js';
	$excluded[] = '/woocommerce/assets/js/frontend/add-to-cart.min.js';
	$excluded[] = '/woocommerce/assets/js/jquery-blockui/jquery.blockUI.min.js';
	$excluded[] = '/woocommerce/assets/js/js-cookie/js.cookie.min.js';

	return $excluded;
}
add_filter( 'rocket_delay_js_exclusions', 'rezajordaan_rocket_exclude_wc_scripts' );
add_filter( 'rocket_exclude_js', 'rezajordaan_rocket_exclude_wc_scripts' );
add_filter( 'rocket_exclude_defer_js', 'rezajordaan_rocket_exclude_wc_scripts' );

/**
 * Send no-cache headers on cart/checkout/account to avoid stale empty carts.
 */
function rezajordaan_nocache_wc_pages() {
	if ( ! function_exists( 'is_cart' ) ) {
		return;
	}

	if ( ! is_cart() && ! is_checkout() && ! ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
		return;
	}

	if ( ! headers_sent() ) {
		nocache_headers();
	}
}
add_action( 'template_redirect', 'rezajordaan_nocache_wc_pages', 20 );

/**
 * Ensure cart session cookie works on HTTPS stores.
 *
 * @param bool $secure Whether the cookie should be secure.
 * @return bool
 */
function rezajordaan_wc_session_cookie_secure( $secure ) {
	if ( is_ssl() ) {
		return true;
	}

	return $secure;
}
add_filter( 'wc_session_use_secure_cookie', 'rezajordaan_wc_session_cookie_secure' );
