<?php
/**
 * WordPress fallback template.
 *
 * @package ParisaCrop
 */

get_header();
?>
<main id="main" class="content-page pc-section">
	<div class="pc-container content-page__inner">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>
				<article <?php post_class( 'content-entry' ); ?>>
					<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
					<div class="content-entry__body"><?php the_content(); ?></div>
				</article>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( '??????? ???? ???.', 'parisacrop' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
