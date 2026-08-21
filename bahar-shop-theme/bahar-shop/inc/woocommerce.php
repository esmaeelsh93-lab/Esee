<?php
/**
 * WooCommerce integration.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', 'bahar_shop_woocommerce_setup' );

/**
 * Declare WooCommerce support.
 */
function bahar_shop_woocommerce_setup() {
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 720,
			'single_image_width'    => 720,
			'product_grid'          => array(
				'default_rows'    => 4,
				'min_rows'        => 2,
				'max_rows'        => 8,
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 4,
			),
		)
	);

	// سایز کارت: عرض ۷۲۰، ارتفاع متناسب، بدون برش سخت (مناسب ۷۲۰×۱۲۸۰).
	add_image_size( 'bahar_card', 720, 9999, false );

	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}

/**
 * Disable hard-crop on WooCommerce thumbnails (keeps full garment).
 *
 * @param array $size Size config.
 * @return array
 */
function bahar_shop_uncrop_wc_thumbnail( $size ) {
	return array(
		'width'  => 720,
		'height' => 1280,
		'crop'   => 0,
	);
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'bahar_shop_uncrop_wc_thumbnail' );

/**
 * Best uncropped image size for product cards.
 *
 * @return string
 */
function bahar_shop_card_image_size() {
	return 'bahar_card';
}

/**
 * Render card image — prefers uncropped bahar_card, falls back to large/full.
 *
 * @param int                  $attachment_id Attachment ID.
 * @param array<string,string> $attr          Img attributes.
 * @return string
 */
function bahar_shop_get_card_image_html( $attachment_id, $attr = array() ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id < 1 ) {
		return '';
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	$size = ( ! empty( $meta['sizes']['bahar_card'] ) ) ? 'bahar_card' : 'large';
	$html = wp_get_attachment_image( $attachment_id, $size, false, $attr );
	if ( $html ) {
		return $html;
	}

	return wp_get_attachment_image( $attachment_id, 'full', false, $attr );
}

/**
 * Card image URL for blur backdrop.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function bahar_shop_get_card_image_url( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id < 1 ) {
		return '';
	}
	foreach ( array( 'bahar_card', 'large', 'medium_large', 'full' ) as $size ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return '';
}

add_filter( 'woocommerce_enqueue_styles', 'bahar_shop_wc_styles' );

/**
 * Load only gallery-related WooCommerce CSS.
 *
 * @param array $styles Registered styles.
 * @return array
 */
function bahar_shop_wc_styles( $styles ) {
	unset( $styles['woocommerce-general'] );
	unset( $styles['woocommerce-layout'] );
	unset( $styles['woocommerce-smallscreen'] );

	return $styles;
}

add_action( 'wp_enqueue_scripts', 'bahar_shop_enqueue_wc_assets', 25 );

/**
 * Ensure WooCommerce cart/gallery scripts load.
 */
function bahar_shop_enqueue_wc_assets() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( is_product() ) {
		wp_enqueue_script( 'flexslider' );
		wp_enqueue_script( 'wc-single-product' );
		wp_enqueue_style( 'photoswipe' );
		wp_enqueue_style( 'photoswipe-default-skin' );
		wp_enqueue_script( 'photoswipe' );
		wp_enqueue_script( 'photoswipe-ui-default' );
		wp_enqueue_script( 'zoom' );
	}

	if ( is_woocommerce() || is_cart() || is_checkout() || is_front_page() || is_customize_preview() ) {
		wp_enqueue_script( 'wc-add-to-cart' );
		wp_enqueue_script( 'wc-cart-fragments' );
	}

	if ( ! is_admin() && function_exists( 'WC' ) ) {
		wp_enqueue_script( 'wc-cart-fragments' );
	}
}


add_action( 'wp', 'bahar_shop_woocommerce_layout_cleanup' );

/**
 * Remove default WooCommerce wrappers/sidebar for custom layout.
 */
function bahar_shop_woocommerce_layout_cleanup() {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}

add_filter( 'woocommerce_catalog_orderby', 'bahar_shop_catalog_orderby' );

/**
 * Customize catalog sort options — newest first.
 *
 * @param array $options Sort options.
 * @return array
 */
function bahar_shop_catalog_orderby( $options ) {
	return array(
		'date'       => __( 'جدیدترین', 'bahar-shop' ),
		'popularity' => __( 'محبوب‌ترین', 'bahar-shop' ),
		'rating'     => __( 'بیشترین امتیاز', 'bahar-shop' ),
		'price'      => __( 'ارزان‌ترین', 'bahar-shop' ),
		'price-desc' => __( 'گران‌ترین', 'bahar-shop' ),
	);
}

add_filter( 'woocommerce_default_catalog_orderby', 'bahar_shop_default_catalog_orderby' );

/**
 * Default shop sort: newest.
 *
 * @return string
 */
function bahar_shop_default_catalog_orderby() {
	return 'date';
}

add_action( 'pre_get_posts', 'bahar_shop_shop_default_order' );

/**
 * Apply default newest order on shop archives.
 *
 * @param WP_Query $query Query.
 */
function bahar_shop_shop_default_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}

	if ( ! isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}

remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

add_action( 'woocommerce_before_shop_loop', 'bahar_shop_shop_toolbar', 15 );

/**
 * Render shop toolbar with price filter and sorting.
 */
function bahar_shop_shop_toolbar() {
	if ( ! woocommerce_products_will_display() ) {
		return;
	}

	$min_price = isset( $_GET['min_price'] ) ? wc_clean( wp_unslash( $_GET['min_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$max_price = isset( $_GET['max_price'] ) ? wc_clean( wp_unslash( $_GET['max_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$action    = esc_url( wc_get_page_permalink( 'shop' ) );

	if ( is_product_taxonomy() ) {
		$term   = get_queried_object();
		$action = $term ? get_term_link( $term ) : $action;
	}

	?>
	<button type="button" class="bahar-toolbar-toggle glass-card" aria-expanded="false" aria-controls="bahar-shop-toolbar">
		<?php esc_html_e( 'فیلتر و مرتب‌سازی', 'bahar-shop' ); ?>
	</button>
	<div id="bahar-shop-toolbar" class="bahar-shop-toolbar glass-card">
		<form class="bahar-price-filter" method="get" action="<?php echo esc_url( $action ); ?>">
			<label class="bahar-toolbar-label" for="min_price"><?php esc_html_e( 'فیلتر قیمت (تومان)', 'bahar-shop' ); ?></label>
			<div class="bahar-price-inputs">
				<input type="number" id="min_price" name="min_price" placeholder="از" value="<?php echo esc_attr( $min_price ); ?>" min="0" step="1000" />
				<span>—</span>
				<input type="number" id="max_price" name="max_price" placeholder="تا" value="<?php echo esc_attr( $max_price ); ?>" min="0" step="1000" />
				<button type="submit" class="bahar-btn bahar-btn--small"><?php esc_html_e( 'اعمال', 'bahar-shop' ); ?></button>
			</div>
			<?php wc_query_string_form_fields( null, array( 'min_price', 'max_price', 'submit' ) ); ?>
		</form>
		<div class="bahar-sort-wrap">
			<label class="bahar-toolbar-label" for="bahar-orderby"><?php esc_html_e( 'مرتب‌سازی', 'bahar-shop' ); ?></label>
			<?php woocommerce_catalog_ordering(); ?>
		</div>
	</div>
	<?php
}

add_filter( 'loop_shop_per_page', 'bahar_shop_products_per_page' );

/**
 * Products per page.
 *
 * @return int
 */
function bahar_shop_products_per_page() {
	return 12;
}

add_filter( 'woocommerce_product_loop_start', 'bahar_shop_product_loop_start' );

/**
 * Custom product loop wrapper class.
 *
 * @param string $html Loop start HTML.
 * @return string
 */
function bahar_shop_product_loop_start( $html ) {
	return '<ul class="products bahar-products-grid">';
}

/**
 * Query newest products for homepage.
 *
 * @param int $limit Product count.
 * @return WP_Query
 */
function bahar_shop_get_newest_products( $limit = 8 ) {
	return new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}
