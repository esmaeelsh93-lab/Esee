<?php
/**
 * Page template.
 *
 * @package Bahar_Shop
 */

get_header();
?>

<div class="container page-wrap">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<article <?php post_class( 'glass-card page-article' ); ?>>
			<h1 class="page-title"><?php the_title(); ?></h1>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</div>

<?php
get_footer();
