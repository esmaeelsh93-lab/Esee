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
			'posts_per_page'      => 10,
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
		'description' => __( 'سفارشت را با دقت آماده می‌کنیم تا در کوتاه‌ترین زمان به دستت برسد.', 'parisacrop' ),
	),
	array(
		'icon'        => 'tag',
		'title'       => __( 'قیمت مناسب', 'parisacrop' ),
		'description' => __( 'استایل جذاب نباید دور از دسترس باشد؛ انتخاب‌های خوش‌قیمت همیشه منتظرت هستند.', 'parisacrop' ),
	),
	array(
		'icon'        => 'sparkles',
		'title'       => __( 'تنوع کالا', 'parisacrop' ),
		'description' => __( 'از مدل‌های روزمره تا انتخاب‌های خاص، برای هر حال‌وهوایی چیزی داریم.', 'parisacrop' ),
	),
	array(
		'icon'        => 'heart',
		'title'       => __( 'انتخاب باکیفیت', 'parisacrop' ),
		'description' => __( 'هر محصول با وسواس انتخاب می‌شود تا هم زیبا باشد و هم حس خوبی به تو بدهد.', 'parisacrop' ),
	),
	array(
		'icon'        => 'bag',
		'title'       => __( 'خرید آسان و مطمئن', 'parisacrop' ),
		'description' => __( 'از پیدا کردن مدل محبوبت تا ثبت سفارش، مسیر خرید ساده و امن طراحی شده است.', 'parisacrop' ),
	),
	array(
		'icon'        => 'chat',
		'title'       => __( 'پشتیبانی پاسخ‌گو', 'parisacrop' ),
		'description' => __( 'برای انتخاب بهتر یا پیگیری سفارش، با حوصله کنار تو هستیم.', 'parisacrop' ),
	),
);
?>

<main id="main">
	<section class="hero" aria-labelledby="hero-title">
		<div class="hero__spotlight" aria-hidden="true"></div>
		<div class="hero__orb hero__orb--one" aria-hidden="true"></div>
		<div class="hero__orb hero__orb--two" aria-hidden="true"></div>
		<div class="hero__sparkles" aria-hidden="true">
			<span>✦</span><span>✧</span><span>♥</span><span>✦</span>
		</div>
		<div class="hero__inner pc-container">
			<div class="hero__copy">
				<p class="hero__kicker"><?php esc_html_e( 'CROP & TOP COLLECTION', 'parisacrop' ); ?></p>
				<h1 id="hero-title">
					<span class="hero__title-line">Parisa Crop</span>
					<span class="hero__title-script">Shop</span>
				</h1>
				<p class="hero__tagline"><?php esc_html_e( 'خاص مثل تو', 'parisacrop' ); ?><span aria-hidden="true">♥</span></p>
				<p class="hero__description"><?php esc_html_e( 'تازه‌ترین انتخاب‌ها برای ساختن استایلی که امضای خود تو را دارد.', 'parisacrop' ); ?></p>
				<a class="pc-button pc-button--primary" href="#new-arrivals">
					<?php esc_html_e( 'دیدن تازه‌ها', 'parisacrop' ); ?>
					<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
				</a>
			</div>
			<div class="hero__visual" aria-hidden="true">
				<div class="hero__halo"></div>
				<img class="hero__hanger" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/hanger.svg' ); ?>" alt="">
				<div class="hero__ribbon">
					<span><?php esc_html_e( 'برای تو', 'parisacrop' ); ?></span>
				</div>
				<div class="hero__heart hero__heart--one">♥</div>
				<div class="hero__heart hero__heart--two">♥</div>
			</div>
		</div>
		<a class="hero__scroll" href="#categories" aria-label="<?php esc_attr_e( 'رفتن به دسته‌بندی‌ها', 'parisacrop' ); ?>">
			<span></span>
		</a>
	</section>

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
