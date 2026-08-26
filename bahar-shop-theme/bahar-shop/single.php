<?php
/**
 * Single post / article template — wider reading layout.
 *
 * @package Bahar_Shop
 */

get_header();
?>

<div class="container page-wrap page-wrap--article">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'glass-card page-article page-article--wide' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="page-article__thumb">
						<?php the_post_thumbnail( 'large' ); ?>
					</div>
				<?php endif; ?>
				<header class="page-article__header">
					<h1><?php the_title(); ?></h1>
					<?php if ( get_the_date() ) : ?>
						<p class="page-article__meta"><?php echo esc_html( get_the_date() ); ?></p>
					<?php endif; ?>
				</header>
				<div class="entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'مطلبی یافت نشد.', 'bahar-shop' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
