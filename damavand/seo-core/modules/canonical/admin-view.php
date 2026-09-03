<?php
/**
 * UI ماژول Canonical.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_Canonical_Module $canon */
$canon = $modules['canonical'] ?? null;
if ( ! $canon instanceof SEO_Core_Canonical_Module ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول کنونیکال در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$variation = 'yes' === (string) ( class_exists( 'Shojaei_SEO_Helpers' )
	? Shojaei_SEO_Helpers::get_option( 'shojaei_seo_variation_canonical', 'yes' )
	: get_option( 'shojaei_seo_variation_canonical', 'yes' ) );
$https = 'yes' === (string) get_option( SEO_Core_Canonical_Module::OPTION_FORCE_HTTPS, 'yes' );
$strip = 'yes' === (string) get_option( SEO_Core_Canonical_Module::OPTION_STRIP_ARGS, 'yes' );
$comps = $canon->detect_competitors();
?>

<div class="shojaei-card">
	<h3 style="margin-top:0;"><?php echo esc_html( $canon->get_label() ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $canon->get_description() ); ?></p>
	<?php if ( ! empty( $comps ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'رقیب SEO فعال است: کنونیکال ورییشن از طریق فیلتر Rank Math/Yoast اعمال می‌شود (مکمل). چاپ تگ مستقل فقط وقتی رقیب نباشد.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'سیاست‌ها', 'shojaei-seo-for-woo' ); ?></h4>
	<p>
		<label style="display:flex;align-items:center;gap:8px;">
			<input type="checkbox" id="shojaei-canonical-variation" <?php checked( $variation ); ?> />
			<?php esc_html_e( 'کنونیکال ورییشن محصول → والد (توصیه‌شده)', 'shojaei-seo-for-woo' ); ?>
		</label>
	</p>
	<p>
		<label style="display:flex;align-items:center;gap:8px;">
			<input type="checkbox" id="shojaei-canonical-https" <?php checked( $https ); ?> />
			<?php esc_html_e( 'اجبار HTTPS در URL کنونیکال', 'shojaei-seo-for-woo' ); ?>
		</label>
	</p>
	<p>
		<label style="display:flex;align-items:center;gap:8px;">
			<input type="checkbox" id="shojaei-canonical-strip" <?php checked( $strip ); ?> />
			<?php esc_html_e( 'حذف پارامترهای ردیابی (utm_*, gclid, fbclid و …)', 'shojaei-seo-for-woo' ); ?>
		</label>
	</p>
	<p>
		<button type="button" class="button button-primary" id="shojaei-canonical-save"><?php esc_html_e( 'ذخیره', 'shojaei-seo-for-woo' ); ?></button>
		<span id="shojaei-canonical-status" class="description" aria-live="polite"></span>
	</p>
</div>

<div class="shojaei-edu-tip">
	<span class="dashicons dashicons-lightbulb"></span>
	<?php esc_html_e( 'Soft 404 و بودجه خزش: ورییشن‌های جدا با URL منحصربه‌فرد نباید کنونیکال جدا داشته باشند — والد مرجع است.', 'shojaei-seo-for-woo' ); ?>
</div>
