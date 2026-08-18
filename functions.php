<?php
/**
 * Core theme setup for Reza Jordaan.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARISACROP_VERSION', '1.0.0' );

require_once get_template_directory() . '/inc/theme-settings.php';
require_once get_template_directory() . '/inc/page-content.php';

/**
 * Register theme features.
 */
function parisacrop_setup() {
	load_theme_textdomain( 'parisacrop', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 160,
			'width'       => 160,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'منوی اصلی', 'parisacrop' ),
			'footer'  => __( 'منوی فوتر', 'parisacrop' ),
		)
	);

	add_image_size( 'parisacrop-category', 720, 620, true );
	add_image_size( 'parisacrop-product', 600, 760, true );
}
add_action( 'after_setup_theme', 'parisacrop_setup' );

/**
 * Load the visual layer and animations.
 */
function parisacrop_enqueue_assets() {
	wp_enqueue_style(
		'parisacrop-fonts',
		'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'parisacrop-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array( 'parisacrop-fonts' ),
		PARISACROP_VERSION
	);

	wp_enqueue_script(
		'gsap',
		'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js',
		array(),
		'3.13.0',
		true
	);
	wp_enqueue_script(
		'gsap-scroll-trigger',
		'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js',
		array( 'gsap' ),
		'3.13.0',
		true
	);
	wp_enqueue_script(
		'parisacrop-main',
		get_template_directory_uri() . '/assets/js/main.js',
		array( 'gsap', 'gsap-scroll-trigger' ),
		PARISACROP_VERSION,
		true
	);
	wp_localize_script(
		'parisacrop-main',
		'parisacropConfig',
		array(
			'marqueeSpeed' => absint( parisacrop_get_setting( 'marquee_speed' ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'parisacrop_enqueue_assets' );

/**
 * Apply product-card controls as safe CSS custom properties.
 */
function parisacrop_enqueue_dynamic_styles() {
	$settings = parisacrop_get_settings();
	$css      = sprintf(
		':root{--pc-shop-columns-desktop:%1$d;--pc-shop-columns-mobile:%2$d;--pc-shop-image-height-desktop:%3$dpx;--pc-shop-image-height-mobile:%4$dpx;--pc-latest-card-width-desktop:%5$dpx;--pc-latest-card-width-mobile:%6$dpx;--pc-latest-image-height-desktop:%7$dpx;--pc-latest-image-height-mobile:%8$dpx;--pc-product-card-gap:%9$dpx;}',
		absint( $settings['shop_columns_desktop'] ),
		absint( $settings['shop_columns_mobile'] ),
		absint( $settings['shop_image_height_desktop'] ),
		absint( $settings['shop_image_height_mobile'] ),
		absint( $settings['latest_card_width_desktop'] ),
		absint( $settings['latest_card_width_mobile'] ),
		absint( $settings['latest_image_height_desktop'] ),
		absint( $settings['latest_image_height_mobile'] ),
		absint( $settings['product_card_gap'] )
	);

	wp_add_inline_style( 'parisacrop-main', $css );
}
add_action( 'wp_enqueue_scripts', 'parisacrop_enqueue_dynamic_styles', 15 );

/**
 * Return the WooCommerce shop URL, with the requested /shop fallback.
 *
 * @return string
 */
function parisacrop_shop_url() {
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$shop_url = wc_get_page_permalink( 'shop' );
		if ( $shop_url ) {
			return $shop_url;
		}
	}

	return home_url( '/shop/' );
}

/**
 * Retrieve product categories explicitly enabled for the homepage.
 *
 * @param int $limit Maximum category count.
 * @return WP_Term[]
 */
function parisacrop_get_featured_categories( $limit = 12 ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}

	$category_ids = array_slice(
		array_values( array_filter( array_map( 'absint', (array) parisacrop_get_setting( 'featured_category_ids' ) ) ) ),
		0,
		absint( $limit )
	);

	if ( ! $category_ids ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'include'    => $category_ids,
			'orderby'    => 'include',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Add useful classes for styling shop views.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function parisacrop_body_classes( $classes ) {
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$classes[] = 'parisacrop-home-shop';
	}
	return $classes;
}
add_filter( 'body_class', 'parisacrop_body_classes' );
