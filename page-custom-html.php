<?php
/**
 * Template Name: HTML اختصاصی
 * Template Post Type: page
 *
 * Full-control page template for administrator-authored HTML and per-page CSS.
 *
 * @package ParisaCrop
 */

get_header();
?>
<main id="main" class="custom-html-page">
	<?php while ( have_posts() ) : ?>
		<?php
		the_post();
		$hide_title = '1' === get_post_meta( get_the_ID(), '_parisacrop_hide_title', true );
		$full_width = '1' === get_post_meta( get_the_ID(), '_parisacrop_full_width', true );
		?>
		<article <?php post_class( $full_width ? 'custom-html-page__content' : 'custom-html-page__content pc-container' ); ?>>
			<?php if ( ! $hide_title ) : ?>
				<header class="custom-html-page__header pc-container">
					<h1><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
