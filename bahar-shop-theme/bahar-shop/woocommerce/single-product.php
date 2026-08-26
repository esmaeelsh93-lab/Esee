<?php
/**
 * Single product template.
 *
 * @package Bahar_Shop
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container woocommerce-wrap woocommerce-wrap--single">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<?php wc_get_template_part( 'content', 'single-product' ); ?>
	<?php endwhile; ?>
</div>

<?php
get_footer();
