<?php
/**
 * Core theme setup for Reza Jordaan.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARISACROP_VERSION', '1.1.0' );

require_once get_template_directory() . '/inc/theme-settings.php';
require_once get_template_directory() . '/inc/page-content.php';

/**
 * Register theme features.
 */
function parisacrop_setup() {
	load_theme_textdomain( 'parisacrop', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
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
		':root{--pc-shop-columns-desktop:%1$d;--pc-shop-columns-mobile:%2$d;--pc-shop-image-height-desktop:%3$dpx;--pc-shop-image-height-mobile:%4$dpx;--pc-latest-card-width-desktop:%5$dpx;--pc-latest-card-width-mobile:%6$dpx;--pc-latest-image-height-desktop:%7$dpx;--pc-latest-image-height-mobile:%8$dpx;--pc-product-card-gap:%9$dpx;--pc-category-columns-desktop:%10$d;--pc-category-columns-mobile:%11$d;--pc-category-border-width:%12$dpx;--pc-category-border-color:%13$s;--pc-product-border-width:%14$dpx;--pc-product-border-color:%15$s;--pc-sale-bg:%16$s;--pc-sale-color:%17$s;--pc-category-name-color:%18$s;--pc-category-name-size-desktop:%19$dpx;--pc-category-name-size-mobile:%20$dpx;}',
		absint( $settings['shop_columns_desktop'] ),
		absint( $settings['shop_columns_mobile'] ),
		absint( $settings['shop_image_height_desktop'] ),
		absint( $settings['shop_image_height_mobile'] ),
		absint( $settings['latest_card_width_desktop'] ),
		absint( $settings['latest_card_width_mobile'] ),
		absint( $settings['latest_image_height_desktop'] ),
		absint( $settings['latest_image_height_mobile'] ),
		absint( $settings['product_card_gap'] ),
		absint( $settings['category_columns_desktop'] ),
		absint( $settings['category_columns_mobile'] ),
		absint( $settings['category_border_width'] ),
		sanitize_hex_color( $settings['category_border_color'] ) ?: '#408a71',
		absint( $settings['product_border_width'] ),
		sanitize_hex_color( $settings['product_border_color'] ) ?: '#b0e4cc',
		sanitize_hex_color( $settings['sale_badge_background'] ) ?: '#285a48',
		sanitize_hex_color( $settings['sale_badge_color'] ) ?: '#ffffff',
		sanitize_hex_color( $settings['category_name_color'] ) ?: '#ffffff',
		absint( $settings['category_name_size_desktop'] ),
		absint( $settings['category_name_size_mobile'] )
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
 * Return product IDs represented by the current product archive.
 *
 * @return int[]
 */
function parisacrop_get_archive_product_ids() {
	$args = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	if ( is_product_category() || is_product_tag() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$args['tax_query'] = array(
				array(
					'taxonomy'         => $term->taxonomy,
					'field'            => 'term_id',
					'terms'            => array( $term->term_id ),
					'include_children' => true,
				),
			);
		}
	}

	return array_map( 'absint', get_posts( $args ) );
}

/**
 * Detect available size attributes and the price range for this archive.
 *
 * @return array{sizes:array<int,array{taxonomy:string,terms:WP_Term[]}>,min_price:float,max_price:float,step:int}
 */
function parisacrop_get_archive_filter_data() {
	$product_ids = parisacrop_get_archive_product_ids();
	$data        = array(
		'sizes'     => array(),
		'min_price' => 0.0,
		'max_price' => 0.0,
		'step'      => 1,
	);

	if ( ! $product_ids || ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
		return $data;
	}

	foreach ( wc_get_attribute_taxonomies() as $attribute ) {
		$label = isset( $attribute->attribute_label ) ? (string) $attribute->attribute_label : '';
		$name  = isset( $attribute->attribute_name ) ? (string) $attribute->attribute_name : '';

		if ( ! preg_match( '/(size|سایز|اندازه)/iu', $label . ' ' . $name ) ) {
			continue;
		}

		$taxonomy = wc_attribute_taxonomy_name( $name );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'object_ids' => $product_ids,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( ! is_wp_error( $terms ) && $terms ) {
			$data['sizes'][] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $terms,
			);
		}
	}

	global $wpdb;
	$placeholders = implode( ', ', array_fill( 0, count( $product_ids ), '%d' ) );
	$query        = $wpdb->prepare(
		"SELECT MIN(min_price) AS min_price, MAX(max_price) AS max_price
		FROM {$wpdb->wc_product_meta_lookup}
		WHERE product_id IN ({$placeholders})",
		$product_ids
	);
	$prices       = $wpdb->get_row( $query );

	if ( $prices ) {
		$data['min_price'] = max( 0, (float) $prices->min_price );
		$data['max_price'] = max( $data['min_price'], (float) $prices->max_price );
		$digits            = strlen( (string) absint( $data['max_price'] ) );
		$data['step']      = max( 1, (int) pow( 10, max( 0, $digits - 3 ) ) );
	}

	return $data;
}

/**
 * Apply selected intelligent size filters to product archive queries.
 *
 * @param WP_Query $query Current query.
 */
function parisacrop_apply_archive_size_filters( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! ( is_product_category() || is_product_tag() ) ) {
		return;
	}

	$selected = isset( $_GET['rz_size'] ) ? (array) wp_unslash( $_GET['rz_size'] ) : array();
	$grouped  = array();

	foreach ( $selected as $value ) {
		$term_id = is_scalar( $value ) ? absint( $value ) : 0;
		$term    = $term_id ? get_term( $term_id ) : null;

		if ( $term instanceof WP_Term && str_starts_with( $term->taxonomy, 'pa_' ) ) {
			$grouped[ $term->taxonomy ][] = $term_id;
		}
	}

	if ( ! $grouped ) {
		return;
	}

	$tax_query             = (array) $query->get( 'tax_query' );
	$tax_query['relation'] = 'AND';

	foreach ( $grouped as $taxonomy => $term_ids ) {
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => array_unique( array_map( 'absint', $term_ids ) ),
			'operator' => 'IN',
		);
	}

	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'parisacrop_apply_archive_size_filters', 20 );

/**
 * Customize or hide WooCommerce sale badges from theme settings.
 *
 * @param string     $html Existing badge markup.
 * @param WP_Post    $post Product post.
 * @param WC_Product $product Product object.
 * @return string
 */
function parisacrop_sale_badge( $html, $post, $product ) {
	if ( ! parisacrop_get_setting( 'show_sale_badge' ) ) {
		return '';
	}

	$label   = (string) parisacrop_get_setting( 'sale_badge_text' );
	$percent = '';
	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();

	if ( $regular > 0 && $sale >= 0 && $sale < $regular ) {
		$percent = (string) round( ( ( $regular - $sale ) / $regular ) * 100 );
	}

	$label = '' === $percent && str_contains( $label, '{percent}' )
		? __( 'تخفیف', 'parisacrop' )
		: trim( str_replace( '{percent}', $percent, $label ) );
	return '<span class="onsale">' . esc_html( $label ?: __( 'تخفیف', 'parisacrop' ) ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'parisacrop_sale_badge', 10, 3 );

/**
 * Keep WooCommerce's widget sidebar out of product and archive content.
 */
function parisacrop_remove_woocommerce_sidebar() {
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}
add_action( 'wp', 'parisacrop_remove_woocommerce_sidebar' );

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
	if ( ! parisacrop_get_setting( 'show_subcategories' ) ) {
		$classes[] = 'hide-product-subcategories';
	}
	$classes[] = 'category-mobile-columns-' . absint( parisacrop_get_setting( 'category_columns_mobile' ) );
	return $classes;
}
add_filter( 'body_class', 'parisacrop_body_classes' );
