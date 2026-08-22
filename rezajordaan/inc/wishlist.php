<?php
/**
 * Theme wishlist (cookie + logged-in user meta). Independent of WoodMart.
 *
 * Shortcode for the wishlist page: [rezajordaan_wishlist]
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'REZAJORDAAN_WISHLIST_COOKIE', 'rj_wishlist' );
define( 'REZAJORDAAN_WISHLIST_META', '_rezajordaan_wishlist' );

/**
 * Wishlist page URL (defaults to /wishlist/).
 *
 * @return string
 */
function rezajordaan_wishlist_url() {
	$page = get_page_by_path( 'wishlist' );
	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/wishlist/' );
}

/**
 * Sanitize a list of product IDs.
 *
 * @param mixed $ids Raw IDs.
 * @return int[]
 */
function rezajordaan_sanitize_wishlist_ids( $ids ) {
	if ( ! is_array( $ids ) ) {
		if ( is_string( $ids ) ) {
			$ids = preg_split( '/[\s,]+/', $ids ) ?: array();
		} else {
			$ids = array();
		}
	}

	$clean = array();
	foreach ( $ids as $id ) {
		$id = absint( $id );
		if ( $id > 0 ) {
			$clean[ $id ] = $id;
		}
	}

	return array_values( $clean );
}

/**
 * Read wishlist IDs from the browser cookie.
 *
 * @return int[]
 */
function rezajordaan_wishlist_ids_from_cookie() {
	if ( empty( $_COOKIE[ REZAJORDAAN_WISHLIST_COOKIE ] ) ) {
		return array();
	}

	$raw = sanitize_text_field( wp_unslash( $_COOKIE[ REZAJORDAAN_WISHLIST_COOKIE ] ) );
	return rezajordaan_sanitize_wishlist_ids( $raw );
}

/**
 * Persist wishlist IDs for guests (cookie) and logged-in users (user meta).
 *
 * @param int[] $ids Product IDs.
 */
function rezajordaan_set_wishlist_ids( array $ids ) {
	$ids = rezajordaan_sanitize_wishlist_ids( $ids );

	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), REZAJORDAAN_WISHLIST_META, $ids );
	}

	if ( ! headers_sent() ) {
		$expire = time() + YEAR_IN_SECONDS;
		setcookie( REZAJORDAAN_WISHLIST_COOKIE, implode( ',', $ids ), $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false );
		$_COOKIE[ REZAJORDAAN_WISHLIST_COOKIE ] = implode( ',', $ids );
	}
}

/**
 * Current wishlist product IDs.
 *
 * @return int[]
 */
function rezajordaan_get_wishlist_ids() {
	if ( is_user_logged_in() ) {
		$meta = get_user_meta( get_current_user_id(), REZAJORDAAN_WISHLIST_META, true );
		$ids  = rezajordaan_sanitize_wishlist_ids( $meta );
		if ( $ids ) {
			return $ids;
		}
	}

	return rezajordaan_wishlist_ids_from_cookie();
}

/**
 * Whether a product is in the wishlist.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function rezajordaan_is_in_wishlist( $product_id ) {
	$product_id = absint( $product_id );
	return $product_id > 0 && in_array( $product_id, rezajordaan_get_wishlist_ids(), true );
}

/**
 * Toggle a product in the wishlist.
 *
 * @param int $product_id Product ID.
 * @return array{ids:int[],in_wishlist:bool,count:int}
 */
function rezajordaan_toggle_wishlist( $product_id ) {
	$product_id = absint( $product_id );
	$ids        = rezajordaan_get_wishlist_ids();
	$in         = false;

	if ( $product_id > 0 && post_type_exists( 'product' ) && 'product' === get_post_type( $product_id ) ) {
		$key = array_search( $product_id, $ids, true );
		if ( false !== $key ) {
			unset( $ids[ $key ] );
			$ids = array_values( $ids );
			$in  = false;
		} else {
			array_unshift( $ids, $product_id );
			$ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
			$in  = true;
		}
		rezajordaan_set_wishlist_ids( $ids );
	}

	return array(
		'ids'         => $ids,
		'in_wishlist' => $in,
		'count'       => count( $ids ),
	);
}

/**
 * Merge cookie wishlist into user meta after login.
 *
 * @param string  $user_login Username.
 * @param WP_User $user       User object.
 */
function rezajordaan_merge_wishlist_on_login( $user_login, $user ) {
	if ( ! $user instanceof WP_User ) {
		return;
	}

	$cookie_ids = rezajordaan_wishlist_ids_from_cookie();
	$meta_ids   = rezajordaan_sanitize_wishlist_ids( get_user_meta( $user->ID, REZAJORDAAN_WISHLIST_META, true ) );
	$merged     = array_values( array_unique( array_merge( $cookie_ids, $meta_ids ) ) );

	update_user_meta( $user->ID, REZAJORDAAN_WISHLIST_META, $merged );
}
add_action( 'wp_login', 'rezajordaan_merge_wishlist_on_login', 10, 2 );

/**
 * AJAX: toggle wishlist item.
 */
function rezajordaan_ajax_wishlist_toggle() {
	check_ajax_referer( 'rezajordaan_wishlist', 'nonce' );

	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
		wp_send_json_error( array( 'message' => __( 'محصول معتبر نیست.', 'rezajordaan' ) ), 400 );
	}

	$result = rezajordaan_toggle_wishlist( $product_id );

	wp_send_json_success(
		array(
			'product_id'  => $product_id,
			'in_wishlist' => $result['in_wishlist'],
			'count'       => $result['count'],
			'ids'         => $result['ids'],
			'label'       => $result['in_wishlist']
				? __( 'حذف از علاقه‌مندی', 'rezajordaan' )
				: __( 'افزودن به علاقه‌مندی', 'rezajordaan' ),
		)
	);
}
add_action( 'wp_ajax_rezajordaan_wishlist_toggle', 'rezajordaan_ajax_wishlist_toggle' );
add_action( 'wp_ajax_nopriv_rezajordaan_wishlist_toggle', 'rezajordaan_ajax_wishlist_toggle' );

/**
 * Enqueue wishlist script site-wide (header count + buttons).
 */
function rezajordaan_enqueue_wishlist_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'rezajordaan-wishlist',
		get_template_directory_uri() . '/assets/js/wishlist.js',
		array(),
		REZAJORDAAN_VERSION,
		true
	);

	wp_localize_script(
		'rezajordaan-wishlist',
		'rezajordaanWishlist',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'rezajordaan_wishlist' ),
			'ids'     => rezajordaan_get_wishlist_ids(),
			'i18n'    => array(
				'add'    => __( 'افزودن به علاقه‌مندی', 'rezajordaan' ),
				'remove' => __( 'حذف از علاقه‌مندی', 'rezajordaan' ),
				'empty'  => __( 'لیست علاقه‌مندی خالی است.', 'rezajordaan' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_enqueue_wishlist_assets', 25 );

/**
 * Render header wishlist link.
 */
function rezajordaan_render_header_wishlist_link() {
	$count = count( rezajordaan_get_wishlist_ids() );
	?>
	<a class="rj-header-wishlist<?php echo $count > 0 ? ' is-active' : ''; ?>" href="<?php echo esc_url( rezajordaan_wishlist_url() ); ?>" aria-label="<?php echo esc_attr( sprintf( _n( 'علاقه‌مندی‌ها، %d محصول', 'علاقه‌مندی‌ها، %d محصول', $count, 'rezajordaan' ), $count ) ); ?>">
		<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.2A4.2 4.2 0 0 1 12 7a4.2 4.2 0 0 1 7 3.8C19 15.6 12 20 12 20Z"/></svg>
		<span class="rj-header-wishlist__count" data-wishlist-count><?php echo esc_html( (string) $count ); ?></span>
	</a>
	<?php
}

/**
 * Render a wishlist toggle button for a product.
 *
 * @param int|WC_Product|null $product Product or ID.
 * @param string              $context Button context class suffix.
 */
function rezajordaan_render_wishlist_button( $product = null, $context = 'card' ) {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	if ( null === $product ) {
		global $product;
	}

	if ( is_numeric( $product ) ) {
		$product = wc_get_product( absint( $product ) );
	}

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$product_id = $product->get_id();
	$in         = rezajordaan_is_in_wishlist( $product_id );
	$label      = $in ? __( 'حذف از علاقه‌مندی', 'rezajordaan' ) : __( 'افزودن به علاقه‌مندی', 'rezajordaan' );
	$classes    = array(
		'rj-wishlist-toggle',
		'rj-wishlist-toggle--' . sanitize_html_class( $context ),
	);
	if ( $in ) {
		$classes[] = 'is-active';
	}
	?>
	<button
		type="button"
		class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		data-wishlist-toggle
		data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
		aria-pressed="<?php echo $in ? 'true' : 'false'; ?>"
		aria-label="<?php echo esc_attr( $label ); ?>"
	>
		<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-9.2A4.2 4.2 0 0 1 12 7a4.2 4.2 0 0 1 7 3.8C19 15.6 12 20 12 20Z"/></svg>
		<?php if ( 'single' === $context ) : ?>
			<span data-wishlist-label><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
	</button>
	<?php
}

/**
 * Wishlist page markup via shortcode.
 *
 * @return string
 */
function rezajordaan_wishlist_shortcode() {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '<p class="rj-wishlist-empty">' . esc_html__( 'ووکامرس فعال نیست.', 'rezajordaan' ) . '</p>';
	}

	$ids = rezajordaan_get_wishlist_ids();

	ob_start();
	?>
	<div class="rj-wishlist" data-wishlist-page>
		<?php if ( ! $ids ) : ?>
			<div class="rj-wishlist-empty">
				<p><?php esc_html_e( 'لیست علاقه‌مندی خالی است.', 'rezajordaan' ); ?></p>
				<a class="button" href="<?php echo esc_url( rezajordaan_shop_url() ); ?>"><?php esc_html_e( 'مشاهده فروشگاه', 'rezajordaan' ); ?></a>
			</div>
		<?php else : ?>
			<ul class="products columns-2 rezajordaan-archive-cards rj-wishlist__grid">
				<?php
				foreach ( $ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( ! $product || ! $product->is_visible() ) {
						continue;
					}
					$GLOBALS['product'] = $product;
					wc_setup_product_data( get_post( $product_id ) );
					load_template( get_template_directory() . '/woocommerce/content-product-archive-card.php', false );
				}
				wp_reset_postdata();
				?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'rezajordaan_wishlist', 'rezajordaan_wishlist_shortcode' );

/**
 * Add wishlist button on single product purchase box.
 */
function rezajordaan_single_wishlist_button() {
	rezajordaan_render_wishlist_button( null, 'single' );
}
add_action( 'woocommerce_single_product_summary', 'rezajordaan_single_wishlist_button', 12 );

/**
 * Body class for wishlist page styling.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function rezajordaan_wishlist_body_class( $classes ) {
	if ( is_page( 'wishlist' ) ) {
		$classes[] = 'rezajordaan-wishlist-page';
		$classes[] = 'rezajordaan-archive-cards';
	}
	return $classes;
}
add_filter( 'body_class', 'rezajordaan_wishlist_body_class' );
