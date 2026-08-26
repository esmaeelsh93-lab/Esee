<?php
/**
 * Homepage sale products slider.
 *
 * @package Bahar_Shop
 */

if ( ! bahar_shop_sale_slider_is_enabled() || ! class_exists( 'WooCommerce' ) ) {
	return;
}

$products = bahar_shop_get_sale_products( 12 );
if ( ! $products->have_posts() ) {
	return;
}

$shop_url = wc_get_page_permalink( 'shop' );
?>
<section class="section sale-slider-section" aria-label="<?php esc_attr_e( 'محصولات تخفیف‌دار', 'bahar-shop' ); ?>">
	<div class="container">
		<div class="section-head">
			<h2><?php esc_html_e( 'تخفیف‌های بهاره 🏷️', 'bahar-shop' ); ?></h2>
			<a class="section-link" href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>"><?php esc_html_e( 'مشاهده همه', 'bahar-shop' ); ?></a>
		</div>

		<div class="sale-slider" data-bahar-sale-slider>
			<div class="sale-slider__track">
				<ul class="products bahar-products-grid sale-slider__grid">
					<?php
					while ( $products->have_posts() ) :
						$products->the_post();
						wc_get_template_part( 'content', 'product' );
					endwhile;
					wp_reset_postdata();
					?>
				</ul>
			</div>
		</div>
	</div>
</section>
