<?php
/**
 * Homepage template.
 *
 * @package Bahar_Shop
 */

get_header();

$categories = bahar_shop_get_home_categories();
$products   = bahar_shop_get_newest_products( 8 );
$shop_url   = wc_get_page_permalink( 'shop' );
?>

<?php get_template_part( 'template-parts/home', 'hero' ); ?>

<section class="home-search-categories">
	<?php get_template_part( 'template-parts/home', 'search' ); ?>

	<section class="section categories-section">
		<div class="container">
			<div class="section-head section-head--cute">
				<h2><?php esc_html_e( 'استایل مورد علاقه‌ات رو پیدا کن', 'bahar-shop' ); ?></h2>
			</div>

			<?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : ?>
				<div class="categories-track" data-bahar-cats>
					<div class="categories-grid">
						<?php foreach ( $categories as $term ) : ?>
							<?php get_template_part( 'template-parts/category', 'card', array( 'term' => $term ) ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			<?php else : ?>
				<p class="empty-note"><?php esc_html_e( 'دسته‌بندی‌ای یافت نشد.', 'bahar-shop' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</section>

<?php get_template_part( 'template-parts/home', 'sale-slider' ); ?>

<section id="bahar-newest" class="section newest-section">
	<div class="container">
		<div class="section-head">
			<h2><?php esc_html_e( 'جدیدترین‌ها', 'bahar-shop' ); ?></h2>
			<a class="section-link" href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>"><?php esc_html_e( 'مشاهده همه', 'bahar-shop' ); ?></a>
		</div>

		<?php if ( $products->have_posts() ) : ?>
			<ul class="products bahar-products-grid">
				<?php
				while ( $products->have_posts() ) :
					$products->the_post();
					wc_get_template_part( 'content', 'product' );
				endwhile;
				wp_reset_postdata();
				?>
			</ul>
		<?php else : ?>
			<p class="empty-note"><?php esc_html_e( 'محصولی یافت نشد.', 'bahar-shop' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_footer();
