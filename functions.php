<?php
/**
 * Core theme setup for Parisa Crop.
 *
 * @package ParisaCrop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PARISACROP_VERSION', '1.2.0' );
define( 'PARISACROP_CATEGORY_META_KEY', '_parisacrop_show_home' );

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

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'number'     => absint( $limit ),
			'meta_query' => array(
				array(
					'key'     => PARISACROP_CATEGORY_META_KEY,
					'value'   => 'yes',
					'compare' => '=',
				),
			),
			'orderby'    => 'menu_order',
			'order'      => 'ASC',
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Add the homepage visibility control when a product category is created.
 */
function parisacrop_add_category_field() {
	wp_nonce_field( 'parisacrop_save_category_meta', 'parisacrop_category_nonce' );
	?>
	<div class="form-field">
		<label for="parisacrop-show-home">
			<input type="checkbox" name="parisacrop_show_home" id="parisacrop-show-home" value="yes">
			<?php esc_html_e( 'نمایش در صفحه اصلی', 'parisacrop' ); ?>
		</label>
		<p><?php esc_html_e( 'این دسته را در بخش دسته‌بندی‌های صفحه فروشگاه نمایش بده.', 'parisacrop' ); ?></p>
	</div>
	<?php
}
add_action( 'product_cat_add_form_fields', 'parisacrop_add_category_field' );

/**
 * Add the homepage visibility control when a product category is edited.
 *
 * @param WP_Term $term Current product category.
 */
function parisacrop_edit_category_field( $term ) {
	$is_visible = 'yes' === get_term_meta( $term->term_id, PARISACROP_CATEGORY_META_KEY, true );
	wp_nonce_field( 'parisacrop_save_category_meta', 'parisacrop_category_nonce' );
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e( 'نمایش در صفحه اصلی', 'parisacrop' ); ?></th>
		<td>
			<label for="parisacrop-show-home">
				<input
					type="checkbox"
					name="parisacrop_show_home"
					id="parisacrop-show-home"
					value="yes"
					<?php checked( $is_visible ); ?>
				>
				<?php esc_html_e( 'نمایش این دسته در صفحه فروشگاه', 'parisacrop' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'دسته‌های انتخاب‌شده به‌صورت خودکار در ویترین نمایش داده می‌شوند.', 'parisacrop' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'product_cat_edit_form_fields', 'parisacrop_edit_category_field' );

/**
 * Save homepage visibility for product categories.
 *
 * @param int $term_id Product category ID.
 */
function parisacrop_save_category_field( $term_id ) {
	if (
		! isset( $_POST['parisacrop_category_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['parisacrop_category_nonce'] ) ),
			'parisacrop_save_category_meta'
		)
		|| ! current_user_can( 'manage_product_terms' )
	) {
		return;
	}

	$value = isset( $_POST['parisacrop_show_home'] ) ? 'yes' : 'no';
	update_term_meta( $term_id, PARISACROP_CATEGORY_META_KEY, $value );
}
add_action( 'created_product_cat', 'parisacrop_save_category_field' );
add_action( 'edited_product_cat', 'parisacrop_save_category_field' );

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
