<?php
/**
 * Category card — uses WooCommerce / Woodmart category images.
 *
 * @package Bahar_Shop
 */

$term = $args['term'] ?? null;
if ( ! $term instanceof WP_Term ) {
	return;
}

$style = bahar_get_category_style( $term );
$link  = get_term_link( $term );
$image = bahar_get_category_image( $term );
?>
<a href="<?php echo esc_url( $link ); ?>" class="cat-card cat-card--photo <?php echo esc_attr( $style['class'] ); ?>">
	<div class="cat-card__photo-wrap">
		<img
			src="<?php echo esc_url( $image ); ?>"
			alt="<?php echo esc_attr( $term->name ); ?>"
			class="cat-card__photo"
			loading="lazy"
			decoding="async"
			width="300"
			height="300"
		/>
	</div>
	<h3 class="cat-card__title"><?php echo esc_html( $term->name ); ?></h3>
	<p class="cat-card__count"><?php echo esc_html( sprintf( '%d محصول', (int) $term->count ) ); ?></p>
</a>
