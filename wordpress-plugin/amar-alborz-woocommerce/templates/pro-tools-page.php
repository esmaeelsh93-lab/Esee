<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * قالب صفحه‌ی «ابزار حرفه‌ای» (نسخه تجاری). این فایل فقط توسط AAW_Admin::render_pro_tools_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_subtitle  = 'قابلیت‌های حرفه‌ای نسخه تجاری';
$header_actions = array(
	array( 'label' => '← بازگشت به داشبورد', 'url' => admin_url( 'admin.php?page=' . AAW_Admin::PAGE_DASHBOARD ), 'class' => '' ),
);

$tab_page   = AAW_Admin::PAGE_PRO_TOOLS;
$range_page = AAW_Admin::PAGE_PRO_TOOLS;
$range_tab  = $tab;

$aaw_needs_range = in_array( $tab, array( 'heatmap', 'replay', 'utm', 'alerts' ), true );
?>
<div class="wrap">
	<div class="aaw-app" id="aawApp" data-theme="<?php echo esc_attr( AAW_Admin::get_settings()['theme_default'] ); ?>" dir="rtl">

		<?php include AAW_PLUGIN_DIR . 'templates/partials/header.php'; ?>
		<?php include AAW_PLUGIN_DIR . 'templates/partials/tab-nav.php'; ?>
		<?php if ( $aaw_needs_range ) : ?>
			<?php include AAW_PLUGIN_DIR . 'templates/partials/range-bar.php'; ?>
		<?php endif; ?>

		<?php
		$aaw_tab_file = AAW_PLUGIN_DIR . 'templates/tabs/pro-' . $tab . '.php';
		if ( file_exists( $aaw_tab_file ) ) {
			include $aaw_tab_file;
		}
		?>

	</div>
</div>
