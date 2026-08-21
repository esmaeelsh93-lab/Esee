<?php
/**
 * Product card in loop — optimized for 720×1280 (9:16) photos.
 *
 * @package Bahar_Shop
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

$image_ids = array();
$main_id   = (int) $product->get_image_id();

if ( $main_id ) {
	$image_ids[] = $main_id;
}

foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
	$gallery_id = (int) $gallery_id;
	if ( $gallery_id && ! in_array( $gallery_id, $image_ids, true ) ) {
		$image_ids[] = $gallery_id;
	}
}

if ( empty( $image_ids ) ) {
	$image_ids[] = 0;
}

$slide_count = count( $image_ids );
$card_class  = 'bahar-product-card glass-card';
if ( ! $product->is_in_stock() ) {
	$card_class .= ' is-sold-out';
}
?>
<li <?php wc_product_class( $card_class, $product ); ?>>
	<div class="bahar-product-card__media<?php echo $slide_count > 1 ? ' has-slider' : ''; ?>" data-bahar-card-gallery>
		<?php echo bahar_shop_product_card_badges_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( function_exists( 'bahar_shop_wishlist_loop_button' ) ) { bahar_shop_wishlist_loop_button(); } ?>
		<div class="bahar-product-card__image">
			<?php foreach ( $image_ids as $index => $image_id ) : ?>
				<?php
				$classes = 'bahar-product-card__slide' . ( 0 === $index ? ' is-active' : '' );
				if ( $image_id && function_exists( 'bahar_shop_get_card_image_html' ) ) {
					echo bahar_shop_get_card_image_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$image_id,
						array(
							'class'    => $classes,
							'loading'  => 0 === $index ? 'eager' : 'lazy',
							'decoding' => 'async',
							'sizes'    => '(max-width:900px) 50vw, 33vw',
						)
					);
				} elseif ( $image_id ) {
					echo wp_get_attachment_image(
						$image_id,
						'large',
						false,
						array(
							'class'    => $classes,
							'loading'  => 0 === $index ? 'eager' : 'lazy',
							'decoding' => 'async',
						)
					);
				} else {
					echo $product->get_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'large',
						array(
							'class' => $classes,
						)
					);
				}
				?>
			<?php endforeach; ?>
		</div>
		<?php if ( $slide_count > 1 ) : ?>
			<button type="button" class="bahar-product-card__nav bahar-product-card__nav--prev" aria-label="<?php esc_attr_e( 'عکس قبلی', 'bahar-shop' ); ?>">‹</button>
			<button type="button" class="bahar-product-card__nav bahar-product-card__nav--next" aria-label="<?php esc_attr_e( 'عکس بعدی', 'bahar-shop' ); ?>">›</button>
			<div class="bahar-product-card__dots" aria-hidden="true">
				<?php for ( $i = 0; $i < $slide_count; $i++ ) : ?>
					<span class="bahar-product-card__dot<?php echo 0 === $i ? ' is-active' : ''; ?>"></span>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
		<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="bahar-product-card__hit" aria-label="<?php echo esc_attr( $product->get_name() ); ?>"></a>
	</div>
	<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="bahar-product-card__link">
		<div class="bahar-product-card__body">
			<h2 class="woocommerce-loop-product__title"><?php echo esc_html( $product->get_name() ); ?></h2>
			<span class="price"><?php echo bahar_shop_loop_price_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
	</a>
	<div class="bahar-product-card__actions">
		<a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="bahar-product-card__visit">
			<?php esc_html_e( 'بازدید', 'bahar-shop' ); ?>
		</a>
		<div class="bahar-product-card__cart-wrap">
			<?php
			woocommerce_template_loop_add_to_cart(
				array(
					'class' => 'button add_to_cart_button',
				)
			);
			?>
		</div>
	</div>
</li>
