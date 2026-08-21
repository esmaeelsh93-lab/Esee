<?php
/**
 * Homepage hero — full-bleed product photo + brand copy.
 * Desktop / mobile images from Media Library uploads.
 *
 * @package Bahar_Shop
 */

$newest_url = home_url( '/#bahar-newest' );
$img_desk   = 'https://baharrshopp.ir/wp-content/uploads/2026/08/baharshopp-product-1787322566-279.webp';
$img_mob    = 'https://baharrshopp.ir/wp-content/uploads/2026/08/baharshopp-product-1787322561-619.webp';
?>
<section class="hero hero--photo" data-bahar-hero aria-label="<?php esc_attr_e( 'بهار شاپ', 'bahar-shop' ); ?>">
	<div class="hero__media">
		<picture>
			<source media="(max-width: 900px)" srcset="<?php echo esc_url( $img_mob ); ?>" type="image/webp" />
			<img
				src="<?php echo esc_url( $img_desk ); ?>"
				alt="<?php esc_attr_e( 'بهار شاپ — پوشاک دخترانه', 'bahar-shop' ); ?>"
				width="1600"
				height="900"
				loading="eager"
				decoding="async"
				fetchpriority="high"
			/>
		</picture>
		<div class="hero__shade" aria-hidden="true"></div>
	</div>

	<div class="hero__content">
		<p class="hero__brand"><?php esc_html_e( 'بهار شاپ', 'bahar-shop' ); ?></p>
		<p class="hero__tagline"><?php esc_html_e( 'انتخاب دخترای تاپ', 'bahar-shop' ); ?></p>
		<a class="hero__cta" href="<?php echo esc_url( $newest_url ); ?>">
			<?php esc_html_e( 'دیدن جدیدترین‌ها', 'bahar-shop' ); ?>
		</a>
	</div>
</section>
