<?php
/**
 * Minimal archive card for taxonomy product lists.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) ) {
	return;
}
?>
<li <?php wc_product_class( 'rj-archive-card', $product ); ?>>
	<a class="rj-archive-card__media woocommerce-LoopProduct-link woocommerce-loop-product__link" href="<?php echo esc_url( $product->get_permalink() ); ?>">
		<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy' ) ) ); ?>
	</a>
	<?php rezajordaan_archive_price_box(); ?>
	<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="button rj-view-product-button">
		<?php esc_html_e( 'دیدن محصول', 'rezajordaan' ); ?>
	</a>
</li>
