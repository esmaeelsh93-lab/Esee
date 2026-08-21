<?php
/**
 * Product archive template.
 *
 * @package Bahar_Shop
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container woocommerce-wrap">
	<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
		<header class="woocommerce-products-header glass-card">
			<?php if ( is_product_category() ) : ?>
				<?php $term = get_queried_object(); ?>
				<?php if ( $term instanceof WP_Term ) : ?>
					<?php $style = bahar_get_category_style( $term ); ?>
					<div class="archive-cat-banner <?php echo esc_attr( $style['class'] ); ?>">
						<img
							class="archive-cat-banner__photo"
							src="<?php echo esc_url( bahar_get_category_image( $term ) ); ?>"
							alt="<?php echo esc_attr( $term->name ); ?>"
							width="88"
							height="88"
							loading="lazy"
						/>
						<h1 class="woocommerce-products-header__title page-title"><?php woocommerce_page_title(); ?></h1>
						<p><?php echo esc_html( $style['label'] ); ?></p>
					</div>
				<?php else : ?>
					<h1 class="woocommerce-products-header__title page-title"><?php woocommerce_page_title(); ?></h1>
				<?php endif; ?>
			<?php else : ?>
				<h1 class="woocommerce-products-header__title page-title"><?php woocommerce_page_title(); ?></h1>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<?php if ( woocommerce_product_loop() ) : ?>
		<?php do_action( 'woocommerce_before_shop_loop' ); ?>
		<?php woocommerce_product_loop_start(); ?>

		<?php if ( wc_get_loop_prop( 'total' ) ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<?php wc_get_template_part( 'content', 'product' ); ?>
			<?php endwhile; ?>
		<?php endif; ?>

		<?php woocommerce_product_loop_end(); ?>
		<?php do_action( 'woocommerce_after_shop_loop' ); ?>
	<?php else : ?>
		<?php do_action( 'woocommerce_no_products_found' ); ?>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_main_content' ); ?>
</div>

<?php
get_footer();
