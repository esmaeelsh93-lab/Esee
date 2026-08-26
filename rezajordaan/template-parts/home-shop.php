<?php
/**
 * Animated Reza Jordaan storefront.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$featured_categories = rezajordaan_get_featured_categories();
$latest_products     = array();
$sale_products       = array();

if ( post_type_exists( 'product' ) && function_exists( 'wc_get_product' ) ) {
	$product_query = new WP_Query(
		array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => absint( rezajordaan_get_setting( 'latest_products_count' ) ),
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

	if ( rezajordaan_get_setting( 'show_sale_slider' ) && function_exists( 'wc_get_product_ids_on_sale' ) ) {
		$sale_ids = array_values( array_filter( array_map( 'absint', (array) wc_get_product_ids_on_sale() ) ) );

		if ( $sale_ids ) {
			$sale_query = new WP_Query(
				array(
					'post_type'           => 'product',
					'post_status'         => 'publish',
					'posts_per_page'      => absint( rezajordaan_get_setting( 'sale_products_count' ) ),
					'post__in'            => $sale_ids,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);

			foreach ( $sale_query->posts as $product_post ) {
				$product = wc_get_product( $product_post->ID );
				if ( $product && $product->is_visible() && $product->is_on_sale() ) {
					$sale_products[] = $product;
				}
			}
			wp_reset_postdata();
		}
	}
}

$benefits = array(
	array(
		'icon'        => 'rocket',
		'title'       => __( 'ارسال سریع', 'rezajordaan' ),
		'description' => __( 'سفارشت سریع و با دقت به دستت می‌رسد.', 'rezajordaan' ),
	),
	array(
		'icon'        => 'tag',
		'title'       => __( 'قیمت مناسب', 'rezajordaan' ),
		'description' => __( 'انتخاب‌های خوش‌قیمت همیشه منتظرت هستند.', 'rezajordaan' ),
	),
	array(
		'icon'        => 'sparkles',
		'title'       => __( 'تنوع کالا', 'rezajordaan' ),
		'description' => __( 'مدل‌های متنوع برای هر سلیقه و استایل.', 'rezajordaan' ),
	),
	array(
		'icon'        => 'heart',
		'title'       => __( 'انتخاب باکیفیت', 'rezajordaan' ),
		'description' => __( 'محصولات زیبا و باکیفیت، انتخاب‌شده برای تو.', 'rezajordaan' ),
	),
	array(
		'icon'        => 'bag',
		'title'       => __( 'خرید آسان و مطمئن', 'rezajordaan' ),
		'description' => __( 'خریدی ساده، امن و بدون دردسر.', 'rezajordaan' ),
	),
	array(
		'icon'        => 'chat',
		'title'       => __( 'پشتیبانی پاسخ‌گو', 'rezajordaan' ),
		'description' => __( 'برای انتخاب و پیگیری سفارش کنارت هستیم.', 'rezajordaan' ),
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
				width="1672"
				height="941"
				fetchpriority="high"
				decoding="async"
			>
		</picture>
		<div class="hero__veil" aria-hidden="true"></div>
		<div class="hero__inner rj-container">
			<div class="hero__copy">
				<div class="hero__payment-logos" data-payment-logos aria-label="<?php esc_attr_e( 'خرید اقساطی با ترب‌پی، دیجی‌پی و اسنپ‌پی', 'rezajordaan' ); ?>">
					<img class="hero__payment-logo is-active" data-payment-logo src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/trb-pay.svg' ); ?>" alt="<?php esc_attr_e( 'ترب‌پی', 'rezajordaan' ); ?>">
					<img class="hero__payment-logo" data-payment-logo src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/digipay-logo.svg' ); ?>" alt="<?php esc_attr_e( 'دیجی‌پی', 'rezajordaan' ); ?>">
					<img class="hero__payment-logo" data-payment-logo src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/snapp-pay.svg' ); ?>" alt="<?php esc_attr_e( 'اسنپ‌پی', 'rezajordaan' ); ?>">
				</div>
				<h1 class="hero__title" id="hero-title"><?php esc_html_e( 'خرید اقساطی و آسان', 'rezajordaan' ); ?></h1>
				<p class="hero__tagline"><?php esc_html_e( 'کفش موردعلاقه‌ات را امروز انتخاب کن', 'rezajordaan' ); ?></p>
				<a class="rj-button rj-button--primary rj-button--hero" href="<?php echo esc_url( rezajordaan_shop_url() ); ?>">
					<span aria-hidden="true">✦</span>
					<?php esc_html_e( 'مشاهده فروشگاه', 'rezajordaan' ); ?>
					<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
				</a>
			</div>
		</div>
	</section>

	<?php if ( rezajordaan_get_setting( 'show_product_search' ) ) : ?>
		<section class="product-search" aria-labelledby="product-search-title">
			<div class="rj-container">
				<h2 class="screen-reader-text" id="product-search-title"><?php esc_html_e( 'جستجوی محصولات', 'rezajordaan' ); ?></h2>
				<form class="product-search__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label class="screen-reader-text" for="rezajordaan-product-search"><?php esc_html_e( 'نام محصول', 'rezajordaan' ); ?></label>
					<span class="product-search__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m16.5 16.5 4 4"/></svg>
					</span>
					<input
						id="rezajordaan-product-search"
						type="search"
						name="s"
						placeholder="<?php esc_attr_e( 'کافیه اسم محصول رو جستجو کنی...', 'rezajordaan' ); ?>"
						autocomplete="off"
					>
					<input type="hidden" name="post_type" value="product">
					<button type="submit">
						<?php esc_html_e( 'جستجو', 'rezajordaan' ); ?>
						<span aria-hidden="true">←</span>
					</button>
				</form>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( rezajordaan_get_setting( 'show_categories' ) && ( $featured_categories || current_user_can( 'manage_product_terms' ) ) ) : ?>
	<section class="category-section rj-section" id="categories" aria-labelledby="category-title">
		<div class="rj-container">
			<header class="section-heading">
				<p><?php echo esc_html( rezajordaan_get_setting( 'category_section_kicker' ) ); ?></p>
				<h2 id="category-title"><?php echo esc_html( rezajordaan_get_setting( 'category_section_title' ) ); ?></h2>
				<span aria-hidden="true"></span>
			</header>

			<?php if ( $featured_categories ) : ?>
				<div class="category-grid">
					<?php foreach ( $featured_categories as $index => $category ) : ?>
						<?php
						$thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
						$image_url    = $thumbnail_id
							? wp_get_attachment_image_url( $thumbnail_id, 'rezajordaan-category' )
							: get_template_directory_uri() . '/assets/images/product-placeholder.svg';
						$term_link    = get_term_link( $category );
						?>
						<a class="category-card" href="<?php echo esc_url( is_wp_error( $term_link ) ? rezajordaan_shop_url() : $term_link ); ?>">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $category->name ); ?>" loading="lazy">
							<span class="category-card__shade"></span>
							<span class="category-card__index"><?php echo esc_html( sprintf( '%02d', $index + 1 ) ); ?></span>
							<span class="category-card__content">
								<strong><?php echo esc_html( $category->name ); ?></strong>
								<?php if ( rezajordaan_get_setting( 'show_category_count' ) ) : ?>
									<small>
										<?php
										printf(
											/* translators: %s: product count */
											esc_html__( '%s محصول', 'rezajordaan' ),
											esc_html( number_format_i18n( $category->count ) )
										);
										?>
									</small>
								<?php endif; ?>
							</span>
							<span class="category-card__arrow" aria-hidden="true">←</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php elseif ( current_user_can( 'manage_product_terms' ) ) : ?>
				<div class="rj-admin-note">
					<?php esc_html_e( 'از نمایش ← تنظیمات رضا جردن، دسته‌های موردنظر را برای لندینگ تیک بزنید.', 'rezajordaan' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<section class="new-arrivals rj-section" id="new-arrivals" aria-labelledby="new-arrivals-title">
		<header class="section-heading section-heading--light rj-container">
			<p><?php esc_html_e( 'همین حالا رسیده', 'rezajordaan' ); ?></p>
			<h2 id="new-arrivals-title"><?php esc_html_e( 'جدیدترین‌ها', 'rezajordaan' ); ?></h2>
			<span aria-hidden="true"></span>
		</header>

		<?php if ( $latest_products ) : ?>
			<div class="product-marquee" data-product-marquee data-speed="<?php echo esc_attr( (string) absint( rezajordaan_get_setting( 'marquee_speed' ) ) ); ?>">
				<div class="product-marquee__track">
					<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
						<?php foreach ( $latest_products as $product ) : ?>
							<article class="product-card" <?php echo 1 === $copy ? 'aria-hidden="true"' : ''; ?>>
								<a class="product-card__image" href="<?php echo esc_url( $product->get_permalink() ); ?>" tabindex="<?php echo 1 === $copy ? '-1' : '0'; ?>">
									<?php
									echo wp_kses_post(
										$product->get_image(
											'rezajordaan-product',
											array(
												'loading' => 'lazy',
												'alt'     => $product->get_name(),
											)
										)
									);
									?>
									<span class="product-card__badge"><?php esc_html_e( 'جدید', 'rezajordaan' ); ?></span>
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
			<div class="rj-admin-note rj-container"><?php esc_html_e( 'محصولی برای نمایش در بخش جدیدترین‌ها منتشر نشده است.', 'rezajordaan' ); ?></div>
		<?php endif; ?>
	</section>

	<?php if ( rezajordaan_get_setting( 'show_sale_slider' ) ) : ?>
		<section class="sale-deals rj-section" id="sale-deals" aria-labelledby="sale-deals-title">
			<header class="section-heading section-heading--light rj-container">
				<?php if ( rezajordaan_get_setting( 'sale_section_kicker' ) ) : ?>
					<p><?php echo esc_html( rezajordaan_get_setting( 'sale_section_kicker' ) ); ?></p>
				<?php endif; ?>
				<h2 id="sale-deals-title"><?php echo esc_html( rezajordaan_get_setting( 'sale_section_title' ) ?: __( 'با تخفیف خرید کنید', 'rezajordaan' ) ); ?></h2>
				<span aria-hidden="true"></span>
			</header>

			<?php if ( $sale_products ) : ?>
				<div class="product-marquee" data-product-marquee data-speed="<?php echo esc_attr( (string) absint( rezajordaan_get_setting( 'sale_marquee_speed' ) ) ); ?>">
					<div class="product-marquee__track">
						<?php for ( $copy = 0; $copy < 2; $copy++ ) : ?>
							<?php foreach ( $sale_products as $product ) : ?>
								<article class="product-card" <?php echo 1 === $copy ? 'aria-hidden="true"' : ''; ?>>
									<a class="product-card__image" href="<?php echo esc_url( $product->get_permalink() ); ?>" tabindex="<?php echo 1 === $copy ? '-1' : '0'; ?>">
										<?php
										echo wp_kses_post(
											$product->get_image(
												'rezajordaan-product',
												array(
													'loading' => 'lazy',
													'alt'     => $product->get_name(),
												)
											)
										);
										?>
										<span class="product-card__badge product-card__badge--sale"><?php esc_html_e( 'تخفیف', 'rezajordaan' ); ?></span>
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
				<div class="rj-admin-note rj-container"><?php esc_html_e( 'محصول تخفیف‌داری برای این اسلایدر پیدا نشد.', 'rezajordaan' ); ?></div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( rezajordaan_get_setting( 'show_about' ) ) : ?>
		<section class="about-store rj-section" id="about-us" aria-labelledby="about-store-title">
			<div class="about-store__inner rj-container">
				<div class="about-store__copy">
					<p class="about-store__eyebrow"><?php esc_html_e( 'درباره رضا جردن', 'rezajordaan' ); ?></p>
					<h2 id="about-store-title"><?php echo esc_html( rezajordaan_get_setting( 'about_title' ) ); ?></h2>
					<p><?php echo esc_html( rezajordaan_get_setting( 'about_description' ) ); ?></p>
					<div class="about-store__actions">
						<a class="rj-button rj-button--primary" href="<?php echo esc_url( rezajordaan_get_setting( 'about_url' ) ); ?>"><?php esc_html_e( 'بیشتر درباره ما', 'rezajordaan' ); ?></a>
						<a class="about-store__contact" href="<?php echo esc_url( rezajordaan_get_setting( 'phone_url' ) ); ?>"><?php esc_html_e( 'تماس با فروشگاه', 'rezajordaan' ); ?></a>
					</div>
				</div>
				<aside class="about-store__visit">
					<span aria-hidden="true">
						<svg viewBox="0 0 24 24"><path d="M12 21s7-5.3 7-12A7 7 0 1 0 5 9c0 6.7 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>
					</span>
					<h3><?php esc_html_e( 'خرید حضوری', 'rezajordaan' ); ?></h3>
					<p><?php echo esc_html( rezajordaan_get_setting( 'store_visit_text' ) ); ?></p>
				</aside>
			</div>
		</section>
	<?php endif; ?>

	<section class="why-us rj-section" id="why-us" aria-labelledby="why-us-title">
		<div class="why-us__ribbon" aria-hidden="true"></div>
		<div class="rj-container">
			<header class="section-heading section-heading--dramatic">
				<p><?php esc_html_e( 'تجربه‌ای به لطافت انتخاب تو', 'rezajordaan' ); ?></p>
				<h2 id="why-us-title">
					<span><?php esc_html_e( 'چرا', 'rezajordaan' ); ?></span>
					<em><?php esc_html_e( 'ما؟', 'rezajordaan' ); ?></em>
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
