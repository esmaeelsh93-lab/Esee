<?php
/**
 * Homepage hero — settings-driven copy, colors, and alignment.
 *
 * @package Bahar_Shop
 */

$hero = function_exists( 'bahar_shop_hero_settings' ) ? bahar_shop_hero_settings() : array();
$newest_url = ! empty( $hero['cta_url'] ) ? $hero['cta_url'] : home_url( '/#bahar-newest' );
$img_desk   = ! empty( $hero['img_desktop'] ) ? $hero['img_desktop'] : 'https://baharrshopp.ir/wp-content/uploads/2026/08/baharshopp-product-1787322566-279.webp';
$img_mob    = ! empty( $hero['img_mobile'] ) ? $hero['img_mobile'] : 'https://baharrshopp.ir/wp-content/uploads/2026/08/baharshopp-product-1787322561-619.webp';
$brand      = isset( $hero['brand'] ) ? $hero['brand'] : 'بهار شاپ';
$tagline    = isset( $hero['tagline'] ) ? $hero['tagline'] : 'انتخاب دخترای تاپ';
$cta_text   = isset( $hero['cta_text'] ) ? $hero['cta_text'] : 'دیدن جدیدترین‌ها';
$align_d    = isset( $hero['align_desktop'] ) ? $hero['align_desktop'] : 'center';
$align_m    = isset( $hero['align_mobile'] ) ? $hero['align_mobile'] : 'top-center-cta-bottom-left';
?>
<section
	class="hero hero--photo"
	data-bahar-hero
	data-align-desktop="<?php echo esc_attr( $align_d ); ?>"
	data-align-mobile="<?php echo esc_attr( $align_m ); ?>"
	aria-label="<?php echo esc_attr( $brand ); ?>"
>
	<div class="hero__media">
		<picture>
			<source media="(max-width: 900px)" srcset="<?php echo esc_url( $img_mob ); ?>" type="image/webp" />
			<img
				src="<?php echo esc_url( $img_desk ); ?>"
				alt="<?php echo esc_attr( $brand ); ?>"
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
		<?php if ( $brand ) : ?>
			<p class="hero__brand"><?php echo esc_html( $brand ); ?></p>
		<?php endif; ?>
		<?php if ( $tagline ) : ?>
			<p class="hero__tagline"><?php echo esc_html( $tagline ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $hero['extra_text'] ) ) : ?>
			<p class="hero__extra" style="color:<?php echo esc_attr( $hero['extra_text_color'] ?: '#ffffff' ); ?>"><?php echo esc_html( $hero['extra_text'] ); ?></p>
		<?php endif; ?>
		<div class="hero__actions">
			<?php if ( $cta_text ) : ?>
				<a class="hero__cta" href="<?php echo esc_url( $newest_url ); ?>"><?php echo esc_html( $cta_text ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $hero['extra_btn_text'] ) && ! empty( $hero['extra_btn_url'] ) ) : ?>
				<a class="hero__cta hero__cta--extra" href="<?php echo esc_url( $hero['extra_btn_url'] ); ?>"><?php echo esc_html( $hero['extra_btn_text'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
