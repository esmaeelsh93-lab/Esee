<?php
/**
 * Product archive partial (loaded only as a WooCommerce fallback).
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'is_shop' ) && is_shop() ) {
	get_template_part( 'template-parts/home-shop' );
	return;
}

do_action( 'woocommerce_before_main_content' );
?>
<div class="product-archive rj-section">
	<div class="rj-container">
		<?php
		do_action( 'woocommerce_shop_loop_header' );
		get_template_part( 'template-parts/product-filters' );
		?>

		<div class="rezajordaan-archive-cards">
			<?php
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
</div>
<?php
do_action( 'woocommerce_after_main_content' );
