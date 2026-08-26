<?php
/**
 * Performance hints + security response headers (Lighthouse).
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_head', 'bahar_shop_resource_hints', 1 );
add_filter( 'style_loader_tag', 'bahar_shop_defer_noncritical_css', 10, 4 );
add_action( 'send_headers', 'bahar_shop_security_headers' );

/**
 * Preconnect / preload critical font (variable woff2).
 */
function bahar_shop_resource_hints() {
	$font = 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2';
	echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />' . "\n";
	echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $font ) . '" crossorigin />' . "\n";
}

/**
 * Defer non-critical stylesheets after first paint.
 *
 * @param string $html   Link tag.
 * @param string $handle Style handle.
 * @param string $href   URL.
 * @param string $media  Media attr.
 * @return string
 */
function bahar_shop_defer_noncritical_css( $html, $handle, $href, $media ) {
	$defer = array(
		'bahar-shop-gallery',
		'bahar-shop-cart-checkout',
	);

	if ( ! in_array( $handle, $defer, true ) || is_admin() ) {
		return $html;
	}

	$href  = esc_url( $href );
	$media = $media ? esc_attr( $media ) : 'all';

	return '<link rel="preload" id="' . esc_attr( $handle ) . '-css" href="' . $href . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" media="' . $media . '" />'
		. '<noscript><link rel="stylesheet" href="' . $href . '" media="' . $media . '" /></noscript>' . "\n";
}

/**
 * Security headers — single place (theme), avoid duplicates with server if possible.
 */
function bahar_shop_security_headers() {
	if ( headers_sent() || is_admin() ) {
		return;
	}

	header( 'X-Content-Type-Options: nosniff', true );
	header( 'Referrer-Policy: strict-origin-when-cross-origin', true );
	header( 'X-Frame-Options: SAMEORIGIN', true );
	header( 'Content-Security-Policy: upgrade-insecure-requests', false );

	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains', true );
	}
}
