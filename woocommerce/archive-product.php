<?php
/**
 * Product archive and the custom /shop storefront.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

if ( is_shop() ) {
	get_template_part( 'template-parts/home-shop' );
	get_footer( 'shop' );
	return;
}

do_action( 'woocommerce_before_main_content' );
?>
<div class="product-archive rj-section">
	<div class="rj-container">
		<?php
		do_action( 'woocommerce_shop_loop_header' );
		get_template_part( 'template-parts/product-filters' );

		if ( woocommerce_product_loop() ) {
			do_action( 'woocommerce_before_shop_loop' );
			woocommerce_product_loop_start();

			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();
					do_action( 'woocommerce_shop_loop' );
					wc_get_template_part( 'content', 'product' );
				}
			}

			woocommerce_product_loop_end();
			do_action( 'woocommerce_after_shop_loop' );
		} else {
			do_action( 'woocommerce_no_products_found' );
		}

		?>
	</div>
</div>
<?php
do_action( 'woocommerce_after_main_content' );
get_footer( 'shop' );
