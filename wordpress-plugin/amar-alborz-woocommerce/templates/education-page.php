<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * قالب صفحه‌ی مستقل «آموزش». این فایل فقط توسط AAW_Education::render_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_subtitle  = 'راهنمای کامل فارسی استفاده از آمار البرز';
$header_actions = array(
	array( 'label' => '← بازگشت به داشبورد', 'url' => admin_url( 'admin.php?page=' . AAW_Admin::PAGE_DASHBOARD ), 'class' => '' ),
);

$tab_groups = array( '' => $tabs );
$tab_page   = AAW_Education::PAGE_SLUG;
?>
<div class="wrap">
	<div class="aaw-app" id="aawApp" data-theme="<?php echo esc_attr( AAW_Admin::get_settings()['theme_default'] ); ?>" dir="rtl">

		<?php include AAW_PLUGIN_DIR . 'templates/partials/header.php'; ?>
		<?php include AAW_PLUGIN_DIR . 'templates/partials/tab-nav.php'; ?>

		<?php
		$aaw_tab_file = AAW_PLUGIN_DIR . 'templates/tabs/edu-' . $tab . '.php';
		if ( file_exists( $aaw_tab_file ) ) {
			include $aaw_tab_file;
		}
		?>

	</div>
</div>
