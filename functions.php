<?php
/**
 * Core theme setup for Reza Jordaan.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REZAJORDAAN_VERSION', '1.5.10' );

require_once get_template_directory() . '/inc/theme-settings.php';
require_once get_template_directory() . '/inc/page-content.php';
require_once get_template_directory() . '/inc/plugin-compat.php';
require_once get_template_directory() . '/inc/woocommerce-pages.php';
require_once get_template_directory() . '/inc/woocommerce-cart-session.php';
require_once get_template_directory() . '/inc/litespeed-compat.php';
require_once get_template_directory() . '/inc/wishlist.php';

/**
 * Register theme features.
 */
function rezajordaan_setup() {
	load_theme_textdomain( 'rezajordaan', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus(
		array(
			'primary' => __( 'منوی اصلی', 'rezajordaan' ),
			'footer'  => __( 'منوی فوتر', 'rezajordaan' ),
		)
	);

	add_image_size( 'rezajordaan-category', 720, 620, true );
	add_image_size( 'rezajordaan-product', 600, 760, true );
}
add_action( 'after_setup_theme', 'rezajordaan_setup' );

/**
 * WooCommerce opens its own #primary wrapper; the theme already provides layout.
 */
function rezajordaan_woocommerce_wrapper_setup() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
}
add_action( 'after_setup_theme', 'rezajordaan_woocommerce_wrapper_setup' );

/**
 * Pages that use GSAP-powered motion (landing only).
 */
function rezajordaan_should_enqueue_motion() {
	return is_front_page();
}

/**
 * Load the visual layer and animations.
 */
function rezajordaan_enqueue_assets() {
	wp_enqueue_style(
		'rezajordaan-fonts',
		'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Vazirmatn:wght@300;400;500;600;700;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'rezajordaan-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array( 'rezajordaan-fonts' ),
		REZAJORDAAN_VERSION
	);

	$script_deps = array();

	if ( rezajordaan_should_enqueue_motion() ) {
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
		$script_deps = array( 'gsap', 'gsap-scroll-trigger' );
	}

	wp_enqueue_script(
		'rezajordaan-main',
		get_template_directory_uri() . '/assets/js/main.js',
		$script_deps,
		REZAJORDAAN_VERSION,
		true
	);
	wp_localize_script(
		'rezajordaan-main',
		'rezajordaanConfig',
		array(
			'marqueeSpeed' => absint( rezajordaan_get_setting( 'marquee_speed' ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_enqueue_assets' );

/**
 * Apply product-card controls as safe CSS custom properties.
 */
function rezajordaan_enqueue_dynamic_styles() {
	$settings = rezajordaan_get_settings();
	$css      = sprintf(
		':root{--rj-shop-columns-desktop:%1$d;--rj-shop-columns-mobile:%2$d;--rj-shop-image-height-desktop:%3$dpx;--rj-shop-image-height-mobile:%4$dpx;--rj-latest-card-width-desktop:%5$dpx;--rj-latest-card-width-mobile:%6$dpx;--rj-latest-image-height-desktop:%7$dpx;--rj-latest-image-height-mobile:%8$dpx;--rj-product-card-gap:%9$dpx;--rj-category-columns-desktop:%10$d;--rj-category-columns-mobile:%11$d;--rj-category-border-width:%12$dpx;--rj-category-border-color:%13$s;--rj-product-border-width:%14$dpx;--rj-product-border-color:%15$s;--rj-sale-bg:%16$s;--rj-sale-color:%17$s;--rj-category-name-color:%18$s;--rj-category-name-size-desktop:%19$dpx;--rj-category-name-size-mobile:%20$dpx;}',
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

	wp_add_inline_style( 'rezajordaan-main', $css );
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_enqueue_dynamic_styles', 15 );

/**
 * Return the WooCommerce shop URL, with the requested /shop fallback.
 *
 * @return string
 */
function rezajordaan_shop_url() {
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
function rezajordaan_get_featured_categories( $limit = 12 ) {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}

	$category_ids = array_slice(
		array_values( array_filter( array_map( 'absint', (array) rezajordaan_get_setting( 'featured_category_ids' ) ) ) ),
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
function rezajordaan_get_archive_product_ids() {
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
function rezajordaan_get_archive_filter_data() {
	$product_ids = rezajordaan_get_archive_product_ids();
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
function rezajordaan_apply_archive_size_filters( $query ) {
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
add_action( 'pre_get_posts', 'rezajordaan_apply_archive_size_filters', 20 );

/**
 * Customize or hide WooCommerce sale badges from theme settings.
 *
 * @param string     $html Existing badge markup.
 * @param WP_Post    $post Product post.
 * @param WC_Product $product Product object.
 * @return string
 */
function rezajordaan_sale_badge( $html, $post, $product ) {
	if ( ! rezajordaan_get_setting( 'show_sale_badge' ) ) {
		return '';
	}

	$label   = (string) rezajordaan_get_setting( 'sale_badge_text' );
	$percent = '';
	$regular = (float) $product->get_regular_price();
	$sale    = (float) $product->get_sale_price();

	if ( $regular > 0 && $sale >= 0 && $sale < $regular ) {
		$percent = (string) round( ( ( $regular - $sale ) / $regular ) * 100 );
	}

	$label = '' === $percent && str_contains( $label, '{percent}' )
		? __( 'تخفیف', 'rezajordaan' )
		: trim( str_replace( '{percent}', $percent, $label ) );
	return '<span class="onsale">' . esc_html( $label ?: __( 'تخفیف', 'rezajordaan' ) ) . '</span>';
}
add_filter( 'woocommerce_sale_flash', 'rezajordaan_sale_badge', 10, 3 );

/**
 * Replace long taxonomy SEO copy with a compact disclosure.
 */
function rezajordaan_setup_collapsible_archive_description() {
	if ( ! is_product_category() && ! is_product_tag() ) {
		return;
	}

	remove_action( 'woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10 );
	add_action( 'woocommerce_archive_description', 'rezajordaan_collapsible_archive_description', 10 );
}
add_action( 'wp', 'rezajordaan_setup_collapsible_archive_description' );

/**
 * Render the current product taxonomy description as expandable content.
 */
function rezajordaan_collapsible_archive_description() {
	$description = get_the_archive_description();
	if ( ! $description ) {
		return;
	}
	?>
	<details class="archive-seo-copy">
		<summary>
			<span><?php esc_html_e( 'راهنمای خرید و توضیحات بیشتر', 'rezajordaan' ); ?></span>
			<i aria-hidden="true"></i>
		</summary>
		<div class="archive-seo-copy__content"><?php echo wp_kses_post( $description ); ?></div>
	</details>
	<?php
}

/**
 * Reorder single-product content so purchasing controls follow the gallery.
 */
function rezajordaan_reorder_single_product() {
	if ( ! is_product() ) {
		return;
	}

	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

	add_action( 'woocommerce_before_single_product_summary', 'rezajordaan_single_product_heading', 1 );
	add_action( 'woocommerce_single_product_summary', 'rezajordaan_single_purchase_box_open', 4 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 5 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 10 );
	add_action( 'woocommerce_single_product_summary', 'rezajordaan_single_purchase_box_close', 11 );
	add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
}
add_action( 'wp', 'rezajordaan_reorder_single_product' );

/**
 * Open the boxed purchase panel on single-product pages.
 */
function rezajordaan_single_purchase_box_open() {
	echo '<div class="single-product-purchase">';
}

/**
 * Close the boxed purchase panel on single-product pages.
 */
function rezajordaan_single_purchase_box_close() {
	echo '</div>';
}

/**
 * Determine whether the current loop should use the compact archive card.
 */
function rezajordaan_is_archive_product_card() {
	if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}

	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return true;
	}

	if ( is_product_taxonomy() || is_post_type_archive( 'product' ) ) {
		return true;
	}

	if ( is_page( 'wishlist' ) ) {
		return true;
	}

	if ( is_search() ) {
		$post_type = get_query_var( 'post_type' );

		if ( 'product' === $post_type ) {
			return true;
		}

		if ( is_array( $post_type ) && in_array( 'product', $post_type, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Shop catalog: newest products first.
 *
 * @param string $orderby Default orderby.
 * @return string
 */
function rezajordaan_default_catalog_orderby( $orderby ) {
	return 'date';
}
add_filter( 'woocommerce_default_catalog_orderby', 'rezajordaan_default_catalog_orderby' );

/**
 * Force date DESC when no explicit orderby is requested on shop/archives.
 *
 * @param array $args Ordering args.
 * @return array
 */
function rezajordaan_catalog_ordering_args( $args ) {
	$requested = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : '';

	if ( '' === $requested || 'menu_order' === $requested || 'date' === $requested ) {
		$args['orderby'] = 'date ID';
		$args['order']   = 'DESC';
	}

	return $args;
}
add_filter( 'woocommerce_get_catalog_ordering_args', 'rezajordaan_catalog_ordering_args' );

/**
 * Show more products per shop page (full catalog via pagination).
 *
 * @param int $per_page Current per-page count.
 * @return int
 */
function rezajordaan_loop_shop_per_page( $per_page ) {
	return 48;
}
add_filter( 'loop_shop_per_page', 'rezajordaan_loop_shop_per_page', 20 );

/**
 * Do not render leftover shop-page editor content above the catalog.
 */
function rezajordaan_disable_shop_page_content_description() {
	remove_action( 'woocommerce_archive_description', 'woocommerce_product_archive_description', 10 );
}
add_action( 'wp', 'rezajordaan_disable_shop_page_content_description' );

/**
 * Compact cards for archives plus related / upsell / cross-sell loops.
 */
function rezajordaan_is_loop_product_card() {
	if ( rezajordaan_is_archive_product_card() ) {
		return true;
	}

	if ( ! function_exists( 'wc_get_loop_prop' ) ) {
		return false;
	}

	$name = (string) wc_get_loop_prop( 'name' );

	return in_array( $name, array( 'related', 'up-sells', 'cross-sells' ), true );
}

/**
 * Keep related products as a compact two-column grid.
 *
 * @param array $args Related products query args.
 * @return array
 */
function rezajordaan_related_products_args( $args ) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 2;
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'rezajordaan_related_products_args' );

/**
 * Match WooCommerce loop columns with theme settings.
 *
 * @param int $columns Default loop columns.
 * @return int
 */
function rezajordaan_loop_shop_columns( $columns ) {
	if ( ! rezajordaan_is_archive_product_card() ) {
		return $columns;
	}

	if ( wp_is_mobile() ) {
		return max( 1, absint( rezajordaan_get_setting( 'shop_columns_mobile' ) ) );
	}

	return max( 2, absint( rezajordaan_get_setting( 'shop_columns_desktop' ) ) );
}
add_filter( 'loop_shop_columns', 'rezajordaan_loop_shop_columns' );

/**
 * Render a readable price box for archive cards.
 */
function rezajordaan_archive_price_box() {
	global $product;

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$regular = (float) $product->get_regular_price();
	$current = (float) $product->get_price();
	$on_sale = $product->is_on_sale() && $regular > $current;
	?>
	<div class="rj-archive-price-box">
		<?php if ( $on_sale ) : ?>
			<span class="rj-archive-price-box__regular"><?php echo wp_kses_post( wc_price( $regular ) ); ?></span>
		<?php endif; ?>
		<span class="rj-archive-price-box__current">
			<?php
			if ( '' !== $product->get_price() ) {
				echo wp_kses_post( wc_price( $current ) );
			} else {
				esc_html_e( 'تماس بگیرید', 'rezajordaan' );
			}
			?>
		</span>
	</div>
	<?php
}

/**
 * Simplify category archive cards to price and a view button.
 */
function rezajordaan_setup_archive_product_cards() {
	if ( ! rezajordaan_is_archive_product_card() ) {
		return;
	}

	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
}
add_action( 'wp', 'rezajordaan_setup_archive_product_cards' );

/**
 * Render a clean full-width heading before the product gallery.
 */
function rezajordaan_single_product_heading() {
	?>
	<header class="single-product-heading">
		<p><?php esc_html_e( 'انتخاب و خرید محصول', 'rezajordaan' ); ?></p>
		<h1 class="product_title entry-title"><?php the_title(); ?></h1>
	</header>
	<?php
}

/**
 * Keep WooCommerce's widget sidebar out of product and archive content.
 */
function rezajordaan_remove_woocommerce_sidebar() {
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}
add_action( 'wp', 'rezajordaan_remove_woocommerce_sidebar' );

/**
 * Add useful classes for styling shop views.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function rezajordaan_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'rezajordaan-home-shop';
	}
	if ( ! rezajordaan_get_setting( 'show_subcategories' ) ) {
		$classes[] = 'hide-product-subcategories';
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$classes[] = 'rezajordaan-single-product';
	}
	if ( rezajordaan_is_archive_product_card() ) {
		$classes[] = 'rezajordaan-archive-cards';
	}
	if ( rezajordaan_get_setting( 'archive_full_width' ) && ( is_product_taxonomy() || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
		$classes[] = 'archive-full-width';
	}
	$classes[] = 'category-mobile-columns-' . absint( rezajordaan_get_setting( 'category_columns_mobile' ) );
	return $classes;
}
add_filter( 'body_class', 'rezajordaan_body_classes' );
