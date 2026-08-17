<?php
/**
 * Single article template.
 *
 * @package ParisaCrop
 */

get_header();
?>
<main id="main" class="single-post-page pc-section">
	<div class="pc-container single-post-page__inner">
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'single-article' ); ?>>
				<header class="single-article__header">
					<p><?php echo esc_html( get_the_date() ); ?></p>
					<h1><?php the_title(); ?></h1>
				</header>
				<?php if ( has_post_thumbnail() ) : ?>
					<div class="single-article__image"><?php the_post_thumbnail( 'full' ); ?></div>
				<?php endif; ?>
				<div class="single-article__content">
					<?php the_content(); ?>
					<?php wp_link_pages(); ?>
				</div>
			</article>
			<nav class="post-navigation" aria-label="<?php esc_attr_e( 'مقاله‌های قبلی و بعدی', 'parisacrop' ); ?>">
				<div><?php previous_post_link( '%link', '→ %title' ); ?></div>
				<div><?php next_post_link( '%link', '%title ←' ); ?></div>
			</nav>
		<?php endwhile; ?>
	</div>
</main>
<?php
get_footer();
