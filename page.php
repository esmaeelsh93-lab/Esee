<?php
/**
 * Standard editable page template.
 *
 * @package RezaJordaan
 */

get_header();
?>
<main id="main" class="content-page rj-section">
	<div class="rj-container content-page__inner">
		<?php while ( have_posts() ) : ?>
			<?php
			the_post();
			$hide_title = '1' === get_post_meta( get_the_ID(), '_rezajordaan_hide_title', true );
			$full_width = '1' === get_post_meta( get_the_ID(), '_rezajordaan_full_width', true );
			?>
			<article <?php post_class( $full_width ? 'content-entry content-entry--wide' : 'content-entry' ); ?>>
				<?php if ( ! $hide_title ) : ?>
					<h1><?php the_title(); ?></h1>
				<?php endif; ?>
				<div class="content-entry__body">
					<?php the_content(); ?>
					<?php wp_link_pages(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
