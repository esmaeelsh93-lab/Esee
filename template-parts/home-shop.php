<?php
/**
 * Animated Parisa Crop storefront.
 *
 * @package ParisaCrop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$featured_categories = parisacrop_get_featured_categories();
$latest_products     = array();

if ( post_type_exists( 'product' ) && function_exists( 'wc_get_product' ) ) {
	$product_query = new WP_Query(
		array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => absint( parisacrop_get_setting( 'latest_products_count' ) ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	foreach ( $product_query->posts as $product_post ) {
		$product = wc_get_product( $product_post->ID );
		if ( $product && $product->is_visible() ) {
			$latest_products[] = $product;
		}
	}
	wp_reset_postdata();
}

$benefits = array(
	array(
		'icon'        => 'rocket',
		'title'       => __( 'ارسال سریع', 'parisacrop' ),
		'description' => __( 'سفارشت سریع و با دقت به دستت می‌رسد.', 'parisacrop' ),
	),
	array(
		'icon'        => 'tag',
		'title'       => __( 'قیمت مناسب', 'parisacrop' ),
		'description' => __( 'انتخاب‌های خوش‌قیمت همیشه منتظرت هستند.', 'parisacrop' ),
	),
	array(
		'icon'        => 'sparkles',
		'title'       => __( 'تنوع کالا', 'parisacrop' ),
		'description' => __( 'مدل‌های متنوع برای هر سلیقه و استایل.', 'parisacrop' ),
	),
	array(
		'icon'        => 'heart',
		'title'       => __( 'انتخاب باکیفیت', 'parisacrop' ),
		'description' => __( 'محصولات زیبا و باکیفیت، انتخاب‌شده برای تو.', 'parisacrop' ),
	),
	array(
		'icon'        => 'bag',
		'title'       => __( 'خرید آسان و مطمئن', 'parisacrop' ),
		'description' => __( 'خریدی ساده، امن و بدون دردسر.', 'parisacrop' ),
	),
	array(
		'icon'        => 'chat',
		'title'       => __( 'پشتیبانی پاسخ‌گو', 'parisacrop' ),
		'description' => __( 'برای انتخاب و پیگیری سفارش کنارت هستیم.', 'parisacrop' ),
	),
);
?>

<main id="main">
	<section class="hero" aria-labelledby="hero-title">
		<picture class="hero__media" aria-hidden="true">
			<source
				media="(max-width: 700px)"
				srcset="<?php echo esc_url( get_template_directory_uri() . '/assets/images/hero-mobile.webp' ); ?>"
			>
			<img
				src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/hero-desktop.webp' ); ?>"
				alt=""
				width="1983"
				height="793"
				fetchpriority="high"
				decoding="async"
			>
		</picture>
		<div class="hero__veil" aria-hidden="true"></div>
		<div class="hero__inner pc-container">
			<div class="hero__copy">
				<h1 class="hero__title" id="hero-title"><?php esc_html_e( 'پریسا کراپ شاپ', 'parisacrop' ); ?></h1>
				<p class="hero__tagline"><?php esc_html_e( 'خاص مثل تو', 'parisacrop' ); ?></p>
				<a class="pc-button pc-button--primary pc-button--hero" href="<?php echo esc_url( parisacrop_shop_url() ); ?>">
					<span aria-hidden="true">♥</span>
					<?php esc_html_e( 'بریم فروشگاه', 'parisacrop' ); ?>
					<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
				</a>
			</div>
		</div>
	</section>

	<?php if ( parisacrop_get_setting( 'show_product_search' ) ) : ?>
		<section class="product-search" aria-labelledby="product-search-title">
			<div class="pc-container">
				<h2 class="screen-reader-text" id="product-search-title"><?php esc_html_e( 'جستجوی محصولات', 'parisacrop' ); ?></h2>
				<form class="product-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="parisacrop-product-search"><?php esc_html_e( 'نام محصول', 'parisacrop' ); ?></label>
					<span class="product-search__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
					</span>
					<input
						id="parisacrop-product-search"
						type="search"
						name="s"
						placeholder="<?php esc_attr_e( 'کافیه اسم محصول رو جستجو کنی...', 'parisacrop' ); ?>"
						autocomplete="off"
					>
					<input type="hidden" name="post_type" value="product">
					<button type="submit">
						<?php esc_html_e( 'جستجو', 'parisacrop' ); ?>
						<span aria-hidden="true">←</span>
					</button>
				</form>
			</div>
		</section>
	<?php endif; ?>

	<section class="category-section pc-section" id="categories" aria-labelledby="category-title">
		<div class="pc-container">
			<header class="section-heading">
				<p><?php esc_html_e( 'برای هر سلیقه', 'parisacrop' ); ?></p>
				<h2 id="category-title"><?php esc_html_e( 'دسته‌بندی‌ها', 'parisacrop' ); ?></h2>
				<span aria-hidden="true"></span>
			</header>

			<?php if ( $featured_categories ) : ?>
				<div class="category-grid">
					<?php foreach ( $featured_categories as $index => $category ) : ?>
						<?php
						$thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
						$image_url    = $thumbnail_id
							? wp_get_attachment_image_url( $thumbnail_id, 'parisacrop-category' )
							: get_template_directory_uri() . '/assets/images/product-placeholder.svg';
						$term_link    = get_term_link( $category );
						?>
						<a class="category-card" href="<?php echo esc_url( is_wp_error( $term_link ) ? parisacrop_shop_url() : $term_link ); ?>">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $category->name ); ?>" loading="lazy">
							<span class="category-card__shade"></span>
							<span class="category-card__index"><?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?></span>
							<span class="category-card__content">
								<strong><?php echo esc_html( $category->name ); ?></strong>
								<small>
									<?php
									printf(
										/* translators: %s: product count */
										esc_html__( '%s محصول', 'parisacrop' ),
										esc_html( number_format_i18n( $category->count ) )
									);
									?>
								</small>
							</span>
							<span class="category-card__arrow" aria-hidden="true">←</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php elseif ( current_user_can( 'manage_product_terms' ) ) : ?>
				<div class="pc-admin-note">
					<?php esc_html_e( 'از بخش محصولات ← دسته‌بندی‌ها، گزینه «نمایش در صفحه اصلی» را برای دسته‌های موردنظر فعال کنید.', 'parisacrop' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<section class="new-arrivals pc-section" id="new-arrivals" aria-labelledby="new-arrivals-title">
		<header class="section-heading section-heading--light pc-container">
			<p><?php esc_html_e( 'همین حالا رسیده', 'parisacrop' ); ?></p>
			<h2 id="new-arrivals-title"><?php esc_html_e( 'جدیدترین‌ها', 'parisacrop' ); ?></h2>
			<span aria-hidden="true"></span>
		</header>

		<?php if ( $latest_products ) : ?>
			<div class="product-marquee" data-product-marquee>
				<div class="product-marquee__track">
					<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
						<?php foreach ( $latest_products as $product ) : ?>
							<article class="product-card" <?php echo 1 === $copy ? 'aria-hidden="true"' : ''; ?>>
								<a class="product-card__image" href="<?php echo esc_url( $product->get_permalink() ); ?>" tabindex="<?php echo 1 === $copy ? '-1' : '0'; ?>">
									<?php
									echo wp_kses_post(
										$product->get_image(
											'parisacrop-product',
											array(
												'loading' => 'lazy',
												'alt'     => $product->get_name(),
											)
										)
									);
									?>
									<span class="product-card__badge"><?php esc_html_e( 'جدید', 'parisacrop' ); ?></span>
								</a>
								<div class="product-card__body">
									<h3><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3>
									<div class="product-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
								</div>
							</article>
						<?php endforeach; ?>
					<?php endfor; ?>
				</div>
			</div>
		<?php elseif ( current_user_can( 'edit_products' ) ) : ?>
			<div class="pc-admin-note pc-container"><?php esc_html_e( 'محصولی برای نمایش در بخش جدیدترین‌ها منتشر نشده است.', 'parisacrop' ); ?></div>
		<?php endif; ?>
	</section>

	<section class="why-us pc-section" id="why-us" aria-labelledby="why-us-title">
		<div class="why-us__ribbon" aria-hidden="true"></div>
		<div class="pc-container">
			<header class="section-heading section-heading--dramatic">
				<p><?php esc_html_e( 'تجربه‌ای به لطافت انتخاب تو', 'parisacrop' ); ?></p>
				<h2 id="why-us-title">
					<span><?php esc_html_e( 'چرا', 'parisacrop' ); ?></span>
					<em><?php esc_html_e( 'ما؟', 'parisacrop' ); ?></em>
				</h2>
				<span aria-hidden="true"></span>
			</header>

			<div class="benefit-grid">
				<?php foreach ( $benefits as $benefit ) : ?>
					<article class="benefit-card">
						<div class="benefit-card__icon benefit-card__icon--<?php echo esc_attr( $benefit['icon'] ); ?>">
							<?php get_template_part( 'template-parts/icon', null, array( 'icon' => $benefit['icon'] ) ); ?>
						</div>
						<h3><?php echo esc_html( $benefit['title'] ); ?></h3>
						<p><?php echo esc_html( $benefit['description'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<div class="footer-separator" aria-hidden="true">
		<span></span><span>♥</span><span></span>
	</div>
</main>
