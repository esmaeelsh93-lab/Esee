<?php
/**
 * Fallback index template.
 *
 * @package Bahar_Shop
 */

get_header();
?>

<div class="container page-wrap">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'glass-card page-article' ); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'مطلبی یافت نشد.', 'bahar-shop' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
