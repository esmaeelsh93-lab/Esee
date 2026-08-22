<?php
/**
 * Template Name: فهرست مقالات
 * Template Post Type: page
 *
 * @package RezaJordaan
 */

get_header();

$paged       = max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) );
$posts_query = new WP_Query(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
		'paged'          => $paged,
	)
);
?>
<main id="main" class="blog-page rj-section">
	<div class="rj-container">
		<header class="content-hero">
			<p>REZA JORDAAN MAGAZINE</p>
			<h1><?php the_title(); ?></h1>
			<?php if ( get_the_content() ) : ?>
				<div class="content-hero__description"><?php the_content(); ?></div>
			<?php else : ?>
				<span><?php esc_html_e( 'تازه‌ترین نوشته‌ها، راهنمای انتخاب و ایده‌های استایل', 'rezajordaan' ); ?></span>
			<?php endif; ?>
		</header>

		<?php if ( $posts_query->have_posts() ) : ?>
			<div class="post-grid">
				<?php while ( $posts_query->have_posts() ) : ?>
					<?php
					$posts_query->the_post();
					get_template_part( 'template-parts/post-card' );
					?>
				<?php endwhile; ?>
			</div>
			<?php
			$pagination = paginate_links(
				array(
					'total'     => $posts_query->max_num_pages,
					'current'   => $paged,
					'mid_size'  => 1,
					'type'      => 'plain',
					'prev_text' => '→',
					'next_text' => '←',
				)
			);
			if ( $pagination ) {
				echo '<nav class="navigation pagination"><div class="nav-links">' . wp_kses_post( $pagination ) . '</div></nav>';
			}
			?>
		<?php else : ?>
			<div class="content-empty"><?php esc_html_e( 'هنوز مقاله‌ای منتشر نشده است.', 'rezajordaan' ); ?></div>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</main>
<?php
get_footer();
