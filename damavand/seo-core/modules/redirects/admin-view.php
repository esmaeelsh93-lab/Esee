<?php
/**
 * UI هاب ریدایرکت‌ها در هسته سئو.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_Redirects_Module $mod */
$mod = $modules['redirects'] ?? null;
if ( ! $mod instanceof SEO_Core_Redirects_Module ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول ریدایرکت‌ها در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$active_n = class_exists( 'Shojaei_SEO_Manual_Redirect' ) ? Shojaei_SEO_Manual_Redirect::count_active() : 0;
$passive  = $mod->is_passive();
$can_emit = $mod->can_emit();
$comps    = $mod->detect_competitors();
?>

<div class="shojaei-card">
	<h3 style="margin-top:0;"><?php echo esc_html( $mod->get_label() ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $mod->get_description() ); ?></p>

	<?php if ( $passive ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php esc_html_e( 'حالت کمکی: اجرای ریدایرکت دستی آزاد خاموش است تا با Rank Math/Yoast تداخل نداشته باشد. داشبورد و مدیریت قواعد فعال است. برای اجرای خروجی دماوند، «حالت جایگزینی» را روشن کنید.', 'shojaei-seo-for-woo' ); ?>
			</p>
		</div>
	<?php elseif ( $can_emit ) : ?>
		<div class="notice notice-success inline">
			<p><?php esc_html_e( 'ریدایرکت دستی آزاد فعال است و روی درخواست‌های فرانت اعمال می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>

	<ul class="description" style="line-height:1.8;margin:10px 0 0;">
		<li>
			<?php esc_html_e( 'قواعد دستی فعال:', 'shojaei-seo-for-woo' ); ?>
			<strong><?php echo esc_html( (string) $active_n ); ?></strong>
		</li>
		<li><?php esc_html_e( 'ریدایرکت نامک محصول و OOS همیشه عملیاتی می‌مانند (جدا از Passive).', 'shojaei-seo-for-woo' ); ?></li>
	</ul>
</div>

<div class="shojaei-pulse-stats" style="margin-top:12px;">
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'ریدایرکت دستی', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'افزودن و مدیریت مسیر → مقصد', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=manual-redirects' ) ); ?>">
			<?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?>
		</a>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'سلامت ریدایرکت', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'شکسته، زنجیره، حلقه', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=redirects' ) ); ?>">
			<?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?>
		</a>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'نامک محصول', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( '۳۰۱ خودکار هنگام تغییر اسلاگ', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=slugs' ) ); ?>">
			<?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?>
		</a>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'مانیتور ۴۰۴', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'مسیرهای گم‌شده برای ساخت ریدایرکت', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=seo-core&module=monitor404' ) ); ?>">
			<?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?>
		</a>
	</div>
</div>

<?php if ( ! empty( $comps ) ) : ?>
	<div class="shojaei-edu-tip" style="margin-top:12px;">
		<span class="dashicons dashicons-lightbulb"></span>
		<?php esc_html_e( 'اگر Rank Math Redirects فعال است، بهتر است فقط یک موتور freeform روشن باشد. Override را فقط وقتی آگاهانه می‌خواهید دماوند جایگزین کند روشن کنید.', 'shojaei-seo-for-woo' ); ?>
	</div>
<?php endif; ?>
