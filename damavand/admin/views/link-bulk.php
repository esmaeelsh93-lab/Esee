<?php
/**
 * Bulk link update wizard — skeleton (phase later expands).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'به‌روزرسانی گروهی', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'فرآیند چندمرحله‌ای برای اصلاح لینک‌ها در مقیاس بالا — با پیش‌نمایش و اجرای پس‌زمینه.', 'shojaei-seo-for-woo' ); ?></p>
</div>

<div class="shojaei-card">
	<ol class="shojaei-wizard-list" style="margin:0;padding-right:1.2em;line-height:1.9;">
		<li><strong><?php esc_html_e( '۱) فیلتر', 'shojaei-seo-for-woo' ); ?></strong> — <?php esc_html_e( 'انتخاب نوشته‌ها بر اساس دسته و نوع محتوا، و نوع لینک (داخلی/خارجی).', 'shojaei-seo-for-woo' ); ?></li>
		<li><strong><?php esc_html_e( '۲) پیکربندی', 'shojaei-seo-for-woo' ); ?></strong> — <?php esc_html_e( 'جایگزینی دامنه/آدرس، افزودن لینک کلمات کلیدی نقشه، یا حذف لینک‌های مشخص.', 'shojaei-seo-for-woo' ); ?></li>
		<li><strong><?php esc_html_e( '۳) پیش‌نمایش', 'shojaei-seo-for-woo' ); ?></strong> — <?php esc_html_e( 'لیست تغییرات قبل از اجرا.', 'shojaei-seo-for-woo' ); ?></li>
		<li><strong><?php esc_html_e( '۴) اجرا', 'shojaei-seo-for-woo' ); ?></strong> — <?php esc_html_e( 'جاب پس‌زمینه تا سرور تایم‌اوت نشود.', 'shojaei-seo-for-woo' ); ?></li>
	</ol>
	<p class="description" style="margin-top:14px;">
		<?php esc_html_e( 'هستهٔ اسکن و نقشه آماده است. ویزارد کامل جایگزینی در به‌روزرسانی بعدی فعال می‌شود؛ فعلاً از «نقشه کلمات» + «اسکن لینک‌ها» استفاده کنید.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=keyword-maps' ) ); ?>"><?php esc_html_e( 'رفتن به نقشه کلمات', 'shojaei-seo-for-woo' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory' ) ); ?>"><?php esc_html_e( 'نگهبان لینک', 'shojaei-seo-for-woo' ); ?></a>
	</p>
</div>
