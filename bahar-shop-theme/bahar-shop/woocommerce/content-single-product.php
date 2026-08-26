<?php
/**
 * Single product content.
 *
 * @package Bahar_Shop
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'bahar-single-product', $product ); ?>>

	<div class="bahar-single-product__gallery-col">
		<?php woocommerce_show_product_images(); ?>
	</div>

	<div class="bahar-single-product__summary-col summary entry-summary">
		<?php bahar_shop_product_back_link(); ?>
		<?php
		/* Order: title → price → variations → short description → meta.
		 * Full description stays in tabs below. Sticky add-to-cart is separate.
		 */
		woocommerce_template_single_title();
		woocommerce_template_single_price();
		/* Allows plugins (e.g. OOS notice) to render right under the price. */
		do_action( 'bahar_shop_after_price' );
		woocommerce_template_single_add_to_cart();
		woocommerce_template_single_excerpt();
		woocommerce_template_single_meta();
		?>
	</div>

	<div class="bahar-single-product__tabs">
		<?php woocommerce_output_product_data_tabs(); ?>
	</div>

</div>

<?php
/* Full-width hook under the product (e.g. smart suggestions). */
do_action( 'bahar_shop_after_single_product' );
?>
