<?php
/**
 * Standard editable page template.
 *
 * @package ParisaCrop
 */

get_header();
?>
<main id="main" class="content-page pc-section">
	<div class="pc-container content-page__inner">
		<?php while ( have_posts() ) : ?>
			<?php
			the_post();
			$hide_title = '1' === get_post_meta( get_the_ID(), '_parisacrop_hide_title', true );
			$full_width = '1' === get_post_meta( get_the_ID(), '_parisacrop_full_width', true );
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
