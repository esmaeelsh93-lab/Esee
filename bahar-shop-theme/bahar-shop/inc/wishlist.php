<?php
/**
 * Wishlist shortcode [bhr-wishlist] + helpers.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wishlist page URL.
 *
 * @return string
 */
function bahar_shop_wishlist_url() {
	$page = get_page_by_path( 'wishlist' );
	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}
	return home_url( '/wishlist/' );
}

/**
 * Cookie / user-meta wishlist IDs.
 *
 * @return int[]
 */
function bahar_shop_wishlist_ids() {
	$ids = array();

	if ( is_user_logged_in() ) {
		$saved = get_user_meta( get_current_user_id(), 'bahar_wishlist', true );
		if ( is_array( $saved ) ) {
			$ids = array_map( 'intval', $saved );
		}
	} elseif ( ! empty( $_COOKIE['bahar_wishlist'] ) ) {
		$raw = sanitize_text_field( wp_unslash( $_COOKIE['bahar_wishlist'] ) );
		$ids = array_filter( array_map( 'intval', explode( ',', $raw ) ) );
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Persist wishlist IDs.
 *
 * @param int[] $ids Product IDs.
 */
function bahar_shop_wishlist_save( $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), 'bahar_wishlist', $ids );
	}

	$cookie = implode( ',', $ids );
	if ( ! headers_sent() ) {
		setcookie( 'bahar_wishlist', $cookie, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
	}
	$_COOKIE['bahar_wishlist'] = $cookie;
}

/**
 * Shortcode: [bhr-wishlist]
 *
 * @return string
 */
function bahar_shop_wishlist_shortcode() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return '<p class="empty-note">' . esc_html__( 'ووکامرس فعال نیست.', 'bahar-shop' ) . '</p>';
	}

	$ids = bahar_shop_wishlist_ids();
	ob_start();
	?>
	<div class="bahar-wishlist" data-bahar-wishlist>
		<div class="bahar-wishlist__head">
			<h2 class="bahar-wishlist__title"><?php esc_html_e( 'علاقه‌مندی‌ها', 'bahar-shop' ); ?></h2>
			<p class="bahar-wishlist__count">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d count */
						_n( '%d محصول', '%d محصول', count( $ids ), 'bahar-shop' ),
						count( $ids )
					)
				);
				?>
			</p>
		</div>

		<?php if ( empty( $ids ) ) : ?>
			<p class="empty-note bahar-wishlist__empty"><?php esc_html_e( 'هنوز محصولی به علاقه‌مندی‌ها اضافه نکردی.', 'bahar-shop' ); ?></p>
			<p class="bahar-wishlist__cta">
				<a class="bahar-btn" href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
					<?php esc_html_e( 'مشاهده فروشگاه', 'bahar-shop' ); ?>
				</a>
			</p>
		<?php else : ?>
			<ul class="products bahar-products-grid bahar-wishlist__grid">
				<?php
				$q = new WP_Query(
					array(
						'post_type'      => 'product',
						'post__in'       => $ids,
						'orderby'        => 'post__in',
						'posts_per_page' => count( $ids ),
					)
				);
				while ( $q->have_posts() ) {
					$q->the_post();
					wc_get_template_part( 'content', 'product' );
				}
				wp_reset_postdata();
				?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'bhr-wishlist', 'bahar_shop_wishlist_shortcode' );

add_action( 'wp_ajax_bahar_wishlist_toggle', 'bahar_shop_wishlist_toggle_ajax' );
add_action( 'wp_ajax_nopriv_bahar_wishlist_toggle', 'bahar_shop_wishlist_toggle_ajax' );

/**
 * AJAX toggle wishlist item.
 */
function bahar_shop_wishlist_toggle_ajax() {
	check_ajax_referer( 'bahar_wishlist', 'nonce' );
	$id  = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
	$ids = bahar_shop_wishlist_ids();

	if ( $id < 1 ) {
		wp_send_json_error();
	}

	$added = false;
	if ( in_array( $id, $ids, true ) ) {
		$ids = array_values( array_diff( $ids, array( $id ) ) );
	} else {
		$ids[] = $id;
		$added = true;
	}

	bahar_shop_wishlist_save( $ids );

	wp_send_json_success(
		array(
			'added' => $added,
			'count' => count( $ids ),
			'ids'   => $ids,
		)
	);
}

add_action( 'woocommerce_single_product_summary', 'bahar_shop_wishlist_loop_button', 28 );

/**
 * Heart button on product cards / single.
 */
function bahar_shop_wishlist_loop_button() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$id      = $product->get_id();
	$in_list = in_array( $id, bahar_shop_wishlist_ids(), true );
	?>
	<button
		type="button"
		class="bahar-wish-btn<?php echo $in_list ? ' is-active' : ''; ?>"
		data-bahar-wish="<?php echo esc_attr( (string) $id ); ?>"
		aria-pressed="<?php echo $in_list ? 'true' : 'false'; ?>"
		aria-label="<?php esc_attr_e( 'افزودن به علاقه‌مندی‌ها', 'bahar-shop' ); ?>"
	>
		<?php bahar_shop_the_icon( 'heart' ); ?>
	</button>
	<?php
}

add_action( 'wp_enqueue_scripts', 'bahar_shop_wishlist_assets' );

/**
 * Wishlist front script.
 */
function bahar_shop_wishlist_assets() {
	wp_enqueue_script(
		'bahar-wishlist',
		BAHAR_SHOP_URI . '/assets/js/wishlist.js',
		array(),
		BAHAR_SHOP_VERSION,
		true
	);
	wp_localize_script(
		'bahar-wishlist',
		'baharWishlist',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bahar_wishlist' ),
		)
	);
}
