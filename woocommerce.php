<?php
/**
 * WooCommerce wrapper for product and archive views.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

if ( function_exists( 'is_shop' ) && is_shop() ) {
	?>
	<main id="main" class="content-page rj-section rezajordaan-home-shop">
		<?php get_template_part( 'template-parts/home-shop' ); ?>
	</main>
	<?php
	get_footer( 'shop' );
	return;
}

$use_archive_cards = function_exists( 'rezajordaan_is_archive_product_card' ) && rezajordaan_is_archive_product_card();
?>
<main id="main" class="content-page rj-section woocommerce-page<?php echo $use_archive_cards ? ' product-archive-page' : ''; ?>">
	<?php if ( $use_archive_cards ) : ?>
		<div class="product-archive rj-section">
			<div class="rj-container">
				<?php
				do_action( 'woocommerce_before_main_content' );
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
				<?php do_action( 'woocommerce_after_main_content' ); ?>
			</div>
		</div>
	<?php else : ?>
		<div class="rj-container content-page__inner">
			<?php woocommerce_content(); ?>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer( 'shop' );
