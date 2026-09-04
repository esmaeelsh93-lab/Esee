<?php
/**
 * UI هاب لینک داخلی در هسته سئو.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_Links_Module $mod */
$mod = $modules['links'] ?? null;
if ( ! $mod instanceof SEO_Core_Links_Module ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول لینک داخلی در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$counts = array(
	'total'    => 0,
	'broken'   => 0,
	'redirect' => 0,
);
$maps_n = 0;
if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
	if ( method_exists( 'Shojaei_SEO_Link_Genius', 'inventory_counts' ) ) {
		$counts = Shojaei_SEO_Link_Genius::inventory_counts();
	}
	$maps = Shojaei_SEO_Link_Genius::list_maps( 500 );
	$maps_n = is_array( $maps ) ? count( $maps ) : 0;
}
?>

<div class="shojaei-card">
	<h3 style="margin-top:0;"><?php echo esc_html( $mod->get_label() ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $mod->get_description() ); ?></p>
	<div class="notice notice-info inline">
		<p><?php esc_html_e( 'این ماژول مکمل است و با نقشه سایت یا متای Rank Math تداخل ندارد. خاموش کردن آن لینک‌سازی خودکار و ابزارهای نابغه لینک را متوقف می‌کند.', 'shojaei-seo-for-woo' ); ?></p>
	</div>
</div>

<div class="shojaei-pulse-stats" style="margin-bottom:14px;">
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'نقشه‌های کلمه', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) $maps_n ); ?></strong>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'لینک‌های ایندکس‌شده', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) (int) ( $counts['total'] ?? 0 ) ); ?></strong>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'شکسته', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) (int) ( $counts['broken'] ?? 0 ) ); ?></strong>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'ریدایرکت‌شده', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) (int) ( $counts['redirect'] ?? 0 ) ); ?></strong>
	</div>
</div>

<div class="shojaei-pulse-stats">
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'نقشه کلمات', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'قوانین لینک خودکار', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=keyword-maps' ) ); ?>"><?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?></a>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'موجودی لینک', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'خزش و وضعیت HTTP', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory' ) ); ?>"><?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?></a>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'آمار پست‌ها', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'امتیاز لینک هر نوشته', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=link-posts' ) ); ?>"><?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?></a>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
		<strong><?php esc_html_e( 'اجرای گروهی', 'shojaei-seo-for-woo' ); ?></strong>
		<p class="description"><?php esc_html_e( 'اعمال نقشه روی محتوا', 'shojaei-seo-for-woo' ); ?></p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=link-bulk' ) ); ?>"><?php esc_html_e( 'باز کردن ←', 'shojaei-seo-for-woo' ); ?></a>
	</div>
</div>
