<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * قالب صفحه‌ی «تنظیمات». این فایل فقط توسط AAW_Admin::render_settings_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_subtitle  = 'تنظیمات افزونه';
$header_actions = array(
	array( 'label' => '← بازگشت به داشبورد', 'url' => admin_url( 'admin.php?page=' . AAW_Admin::PAGE_DASHBOARD ), 'class' => '' ),
);

$tab_groups = array( '' => $settings_tabs );
$tab_page   = AAW_Admin::PAGE_SETTINGS;
?>
<div class="wrap">
	<div class="aaw-app" id="aawApp" data-theme="<?php echo esc_attr( $settings['theme_default'] ); ?>" dir="rtl">

		<?php include AAW_PLUGIN_DIR . 'templates/partials/header.php'; ?>
		<?php include AAW_PLUGIN_DIR . 'templates/partials/tab-nav.php'; ?>

		<?php if ( $saved ) : ?>
			<div class="aaw-notice aaw-notice-success">تنظیمات با موفقیت ذخیره شد.</div>
		<?php endif; ?>

		<?php if ( $reset ) : ?>
			<div class="aaw-notice aaw-notice-success">آمار با موفقیت بازنشانی (حذف) شد.</div>
		<?php endif; ?>

		<?php
		$aaw_tab_file = AAW_PLUGIN_DIR . 'templates/tabs/settings-' . $tab . '.php';
		if ( file_exists( $aaw_tab_file ) ) {
			include $aaw_tab_file;
		}
		?>

	</div>
</div>
