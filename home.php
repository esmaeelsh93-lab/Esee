<?php
/**
 * Blog posts index.
 *
 * @package ParisaCrop
 */

get_header();

$posts_page_id = get_option( 'page_for_posts' );
$page_title    = $posts_page_id ? get_the_title( $posts_page_id ) : __( 'مجله رضا جردن', 'parisacrop' );
?>
<main id="main" class="blog-page pc-section">
	<div class="pc-container">
		<header class="content-hero">
			<p>REZA JORDAAN MAGAZINE</p>
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<span><?php esc_html_e( 'تازه‌ترین نوشته‌ها، راهنمای انتخاب و ایده‌های استایل', 'parisacrop' ); ?></span>
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
			<div class="content-empty"><?php esc_html_e( 'هنوز مقاله‌ای منتشر نشده است.', 'parisacrop' ); ?></div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
