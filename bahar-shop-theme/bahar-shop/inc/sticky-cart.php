<?php
/**
 * Mobile sticky add-to-cart bar (Digikala-style).
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'bahar_shop_sticky_cart_markup', 25 );

/**
 * Output sticky bar markup on single product pages.
 */
function bahar_shop_sticky_cart_markup() {
	if ( ! is_product() ) {
		return;
	}

	global $product;

	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() ) {
		return;
	}

	$is_variable = $product->is_type( 'variable' );
	?>
	<div class="bahar-sticky-cart" id="bahar-sticky-cart" aria-hidden="true" data-variable="<?php echo $is_variable ? '1' : '0'; ?>">
		<div class="bahar-sticky-cart__inner glass-bar">
			<div class="bahar-sticky-cart__price" aria-live="polite">
				<?php echo wp_kses_post( $product->get_price_html() ); ?>
			</div>
			<button type="button" class="bahar-sticky-cart__btn" <?php echo $is_variable ? ' disabled' : ''; ?>>
				<?php esc_html_e( 'افزودن به سبد', 'bahar-shop' ); ?>
			</button>
		</div>
	</div>
	<?php
}
