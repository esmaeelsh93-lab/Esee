<?php
/**
 * Product loop card router.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) ) {
	return;
}

if ( function_exists( 'rezajordaan_is_archive_product_card' ) && rezajordaan_is_archive_product_card() ) {
	load_template( get_template_directory() . '/woocommerce/content-product-archive-card.php', false );
	return;
}

?>
<li <?php wc_product_class( '', $product ); ?>>
	<?php
	do_action( 'woocommerce_before_shop_loop_item' );
	do_action( 'woocommerce_before_shop_loop_item_title' );
	do_action( 'woocommerce_shop_loop_item_title' );
	do_action( 'woocommerce_after_shop_loop_item_title' );
	do_action( 'woocommerce_after_shop_loop_item' );
	?>
</li>
