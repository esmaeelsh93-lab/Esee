<?php
/**
 * UI هاب اسکیما در هسته سئو.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_Schema_Module $mod */
$mod = $modules['schema'] ?? null;
if ( ! $mod instanceof SEO_Core_Schema_Module ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول اسکیما در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$passive  = $mod->is_passive();
$can_emit = $mod->can_emit();
$ov       = $mod->is_override_mode();
$mode_lbl = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::schema_mode_label() : '';
$last     = class_exists( 'Shojaei_SEO_Schema_Detector' ) ? Shojaei_SEO_Schema_Detector::get_last_scan() : null;

$respect    = 'yes' === (string) get_option( 'shojaei_seo_schema_respect_seo_plugins', 'yes' );
$product    = 'yes' === (string) get_option( 'shojaei_seo_schema_product_enabled', 'yes' );
$breadcrumb = 'yes' === (string) get_option( 'shojaei_seo_schema_breadcrumb_enabled', 'yes' );
$faq        = 'yes' === (string) get_option( 'shojaei_seo_schema_faq_enabled', 'yes' );
$article    = 'yes' === (string) get_option( 'shojaei_seo_schema_article_enabled', 'yes' );
$site       = 'yes' === (string) get_option( 'shojaei_seo_schema_site_enabled', 'yes' );
$collection = 'yes' === (string) get_option( 'shojaei_seo_schema_collection_enabled', 'yes' );
$detect     = 'yes' === (string) get_option( 'shojaei_seo_schema_detect_enabled', 'yes' );
$disable_wc = 'yes' === (string) get_option( 'shojaei_seo_disable_wc_schema', 'no' );
?>

<div class="shojaei-card">
	<h3 style="margin-top:0;"><?php echo esc_html( $mod->get_label() ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $mod->get_description() ); ?></p>

	<p>
		<strong><?php esc_html_e( 'حالت فعلی:', 'shojaei-seo-for-woo' ); ?></strong>
		<?php echo esc_html( $mode_lbl ); ?>
	</p>

	<?php if ( $passive && ! $ov ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'حالت کمکی (Passive): Product/Breadcrumb به Rank Math/Yoast واگذار شده؛ FAQ و Detector می‌توانند فعال بمانند. برای صدور کامل دماوند، Override را در نمای کلی روشن کنید.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php elseif ( $ov ) : ?>
		<div class="notice notice-warning inline">
			<p><?php esc_html_e( 'حالت جایگزینی روشن است — Product/Breadcrumb از دماوند صادر می‌شود حتی اگر Rank Math فعال باشد. مراقب تداخل JSON-LD باشید.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php elseif ( $can_emit ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'صدور کامل فعال است (رقیب SEO تشخیص داده نشد).', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'تنظیمات سریع', 'shojaei-seo-for-woo' ); ?></h4>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-respect" <?php checked( $respect ); ?> /> <?php esc_html_e( 'احترام به افزونه SEO (واگذاری Product/Breadcrumb)', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-product" <?php checked( $product ); ?> /> <?php esc_html_e( 'Product JSON-LD', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-breadcrumb" <?php checked( $breadcrumb ); ?> /> <?php esc_html_e( 'BreadcrumbList JSON-LD', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-article" <?php checked( $article ); ?> /> <?php esc_html_e( 'Article / WebPage JSON-LD (نوشته و برگه)', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-site" <?php checked( $site ); ?> /> <?php esc_html_e( 'Organization + WebSite (صفحه اصلی)', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-collection" <?php checked( $collection ); ?> /> <?php esc_html_e( 'CollectionPage (دسته / آرشیو فروشگاه)', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-faq" <?php checked( $faq ); ?> /> <?php esc_html_e( 'FAQ JSON-LD (مکمل)', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-detect" <?php checked( $detect ); ?> /> <?php esc_html_e( 'تشخیص تداخل JSON-LD برای مدیر', 'shojaei-seo-for-woo' ); ?></label></p>
	<p><label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="shojaei-schema-disable-wc" <?php checked( $disable_wc ); ?> /> <?php esc_html_e( 'خاموش کردن اسکیمای پیش‌فرض ووکامرس', 'shojaei-seo-for-woo' ); ?></label></p>
	<p>
		<button type="button" class="button button-primary" id="shojaei-schema-save"><?php esc_html_e( 'ذخیره', 'shojaei-seo-for-woo' ); ?></button>
		<span id="shojaei-schema-status" class="description" aria-live="polite"></span>
	</p>
</div>

<?php if ( is_array( $last ) && ! empty( $last['url'] ) ) : ?>
	<div class="shojaei-card">
		<h4 style="margin-top:0;"><?php esc_html_e( 'آخرین اسکن تداخل', 'shojaei-seo-for-woo' ); ?></h4>
		<ul class="description" style="line-height:1.8;">
			<li><code dir="ltr"><?php echo esc_html( (string) $last['url'] ); ?></code></li>
			<?php if ( ! empty( $last['has_conflict'] ) ) : ?>
				<li><span class="shojaei-slug-score shojaei-tone-warning"><?php esc_html_e( 'تداخل تشخیص داده شد', 'shojaei-seo-for-woo' ); ?></span></li>
			<?php else : ?>
				<li><span class="shojaei-slug-score shojaei-tone-safe"><?php esc_html_e( 'تداخل جدی گزارش نشد', 'shojaei-seo-for-woo' ); ?></span></li>
			<?php endif; ?>
		</ul>
	</div>
<?php endif; ?>

<div class="shojaei-edu-tip">
	<span class="dashicons dashicons-lightbulb"></span>
	<?php esc_html_e( 'تنظیمات کامل‌تر و اسکن URL در صفحه تنظیمات افزونه:', 'shojaei-seo-for-woo' ); ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-integration' ) ); ?>"><?php esc_html_e( 'یکپارچگی / اسکیما ←', 'shojaei-seo-for-woo' ); ?></a>
</div>
