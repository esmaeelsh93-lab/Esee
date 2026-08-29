<?php
/**
 * Date, author and category archives for articles.
 *
 * @package ParisaCrop
 */

get_header();
?>
<main id="main" class="blog-page pc-section">
	<div class="pc-container">
		<header class="content-hero">
			<p><?php esc_html_e( 'آرشیو نوشته‌ها', 'parisacrop' ); ?></p>
			<h1><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="content-hero__description">', '</div>' ); ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid">
				<?php while ( have_posts() ) : ?>
					<?php
					the_post();
					get_template_part( 'template-parts/post-card' );
					?>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<div class="content-empty"><?php esc_html_e( 'مطلبی در این بخش پیدا نشد.', 'parisacrop' ); ?></div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
