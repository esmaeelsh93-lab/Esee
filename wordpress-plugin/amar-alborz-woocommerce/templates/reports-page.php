<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * قالب صفحه‌ی «گزارش‌ها». این فایل فقط توسط AAW_Admin::render_reports_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_subtitle  = 'گزارش‌های تفصیلی بازدیدکنندگان و فروش';
$header_actions = array(
	array( 'label' => '← بازگشت به داشبورد', 'url' => admin_url( 'admin.php?page=' . AAW_Admin::PAGE_DASHBOARD ), 'class' => '' ),
);

$tab_page   = AAW_Admin::PAGE_REPORTS;
$range_page = AAW_Admin::PAGE_REPORTS;
$range_tab  = $tab;
?>
<div class="wrap">
	<div class="aaw-app" id="aawApp" data-theme="<?php echo esc_attr( AAW_Admin::get_settings()['theme_default'] ); ?>" dir="rtl">

		<?php include AAW_PLUGIN_DIR . 'templates/partials/header.php'; ?>
		<?php include AAW_PLUGIN_DIR . 'templates/partials/tab-nav.php'; ?>
		<?php include AAW_PLUGIN_DIR . 'templates/partials/range-bar.php'; ?>

		<?php
		$aaw_tab_file = AAW_PLUGIN_DIR . 'templates/tabs/reports-' . $tab . '.php';
		if ( file_exists( $aaw_tab_file ) ) {
			include $aaw_tab_file;
		}
		?>

	</div>
</div>
