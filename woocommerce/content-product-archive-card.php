<?php
/**
 * Complete archive card for taxonomy and product archive loops.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) ) {
	return;
}

$permalink   = $product->get_permalink();
$product_name = $product->get_name();
$aria_label  = sprintf(
	/* translators: %s: product title */
	__( 'دیدن محصول: %s', 'rezajordaan' ),
	$product_name
);
$image_html = $product->get_image(
	'woocommerce_thumbnail',
	array(
		'class'   => 'rj-archive-card__image',
		'loading' => 'lazy',
		'alt'     => $product_name,
	)
);

if ( ! $image_html ) {
	$image_html = wc_placeholder_img(
		'woocommerce_thumbnail',
		array(
			'class' => 'rj-archive-card__image',
			'alt'   => $product_name,
		)
	);
}
?>
<li <?php wc_product_class( 'rj-archive-card', $product ); ?>>
	<a class="rj-archive-card__media woocommerce-LoopProduct-link woocommerce-loop-product__link" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $aria_label ); ?>">
		<?php echo wp_kses_post( $image_html ); ?>
		<?php if ( $product->is_on_sale() && rezajordaan_get_setting( 'show_sale_badge' ) ) : ?>
			<?php echo wp_kses_post( apply_filters( 'woocommerce_sale_flash', '', get_post(), $product ) ); ?>
		<?php endif; ?>
	</a>
	<?php if ( function_exists( 'rezajordaan_render_wishlist_button' ) ) : ?>
		<?php rezajordaan_render_wishlist_button( $product, 'card' ); ?>
	<?php endif; ?>

	<h3 class="rj-archive-card__title woocommerce-loop-product__title">
		<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product_name ); ?></a>
	</h3>

	<?php rezajordaan_archive_price_box(); ?>

	<?php if ( ! $product->is_in_stock() ) : ?>
		<p class="rj-archive-card__stock"><?php esc_html_e( 'ناموجود', 'rezajordaan' ); ?></p>
	<?php endif; ?>
</li>
