<?php
/**
 * تنظیمات یکپارچه با پوسته‌ی اصلی.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$theme = in_array( $settings['dashboard_theme'], array( 'light', 'dark', 'auto' ), true ) ? $settings['dashboard_theme'] : 'light';
?>
<div class="wrap cvs-admin-wrap">
	<div class="cvs-app cvs-theme-<?php echo esc_attr( $theme ); ?>" dir="rtl">
		<aside class="cvs-sidebar" aria-label="ناوبری افزونه">
			<div class="cvs-brand">
				<span class="cvs-brand-mark dashicons dashicons-chart-area" aria-hidden="true"></span>
				<span class="cvs-brand-copy"><strong>دیدبان</strong><small>آمار و تحلیل بازدید</small></span>
			</div>
			<nav class="cvs-nav">
				<?php foreach ( $navigation as $key => $item ) : ?>
					<a class="cvs-nav-item <?php echo $active_tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( CVS_Admin::get_tab_url( $key ) ); ?>" <?php echo $active_tab === $key ? 'aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
						<span class="cvs-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="cvs-sidebar-status"><span class="cvs-status-dot"></span><div><strong>ردیابی فعال</strong><small>نسخه <?php echo esc_html( CVS_VERSION ); ?></small></div></div>
		</aside>

		<main class="cvs-main">
			<header class="cvs-page-header">
				<div><p class="cvs-eyebrow">پیکربندی</p><h1>تنظیمات</h1><p>حریم خصوصی، نشست‌ها و نگهداری داده را مدیریت کنید.</p></div>
				<a class="cvs-icon-btn" href="<?php echo esc_url( CVS_Admin::get_tab_url( 'guide' ) ); ?>" aria-label="راهنما"><span class="dashicons dashicons-editor-help"></span></a>
			</header>

			<?php if ( $saved ) : ?>
				<div class="cvs-notice cvs-notice-success"><span class="dashicons dashicons-yes-alt"></span>تنظیمات با موفقیت ذخیره شد.</div>
			<?php endif; ?>
			<?php if ( $reset ) : ?>
				<div class="cvs-notice cvs-notice-success"><span class="dashicons dashicons-yes-alt"></span>همه‌ی داده‌های آماری حذف شد.</div>
			<?php endif; ?>

			<form class="cvs-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cvs_save_settings">
				<?php wp_nonce_field( 'cvs_save_settings' ); ?>

				<section class="cvs-panel cvs-settings-section">
					<header class="cvs-panel-header"><div><h2>ردیابی و حریم خصوصی</h2><p>رفتار ثبت بازدید در صفحات عمومی</p></div><span class="dashicons dashicons-shield"></span></header>
					<div class="cvs-setting-row">
						<div><label for="cvs-exclude-staff">حذف مدیران و نویسندگان</label><p>بازدید کاربران واردشده با دسترسی نویسندگی یا بالاتر شمارش نشود.</p></div>
						<label class="cvs-switch"><input id="cvs-exclude-staff" type="checkbox" name="exclude_staff" value="1" <?php checked( ! empty( $settings['exclude_staff'] ) ); ?>><span></span></label>
					</div>
					<div class="cvs-setting-row">
						<div><label for="cvs-cookie-less">حالت بدون کوکی</label><p>شناسه‌ها فقط در sessionStorage نگهداری شوند؛ مناسب سیاست‌های سخت‌گیرانه‌تر حریم خصوصی.</p></div>
						<label class="cvs-switch"><input id="cvs-cookie-less" type="checkbox" name="cookie_less" value="1" <?php checked( ! empty( $settings['cookie_less'] ) ); ?>><span></span></label>
					</div>
					<div class="cvs-setting-row cvs-setting-row-field">
						<div><label for="cvs-session-timeout">مدت نشست</label><p>پس از این مدت بی‌فعالیتی، نشست تازه ساخته می‌شود.</p></div>
						<div class="cvs-input-suffix"><input id="cvs-session-timeout" type="number" min="1" max="1440" name="session_timeout" value="<?php echo esc_attr( $settings['session_timeout'] ); ?>"><span>دقیقه</span></div>
					</div>
					<div class="cvs-setting-row cvs-setting-row-field">
						<div><label for="cvs-excluded-ips">IPهای مستثنی</label><p>هر IP را در یک خط وارد کنید. مقدار خام هرگز در جدول آمار ذخیره نمی‌شود.</p></div>
						<textarea id="cvs-excluded-ips" name="excluded_ips" rows="4" placeholder="192.0.2.1"><?php echo esc_textarea( $settings['excluded_ips'] ); ?></textarea>
					</div>
				</section>

				<section class="cvs-panel cvs-settings-section">
					<header class="cvs-panel-header"><div><h2>نمایش و نگهداری</h2><p>پوسته‌ی داشبورد و چرخه‌ی عمر داده</p></div><span class="dashicons dashicons-admin-appearance"></span></header>
					<div class="cvs-setting-row cvs-setting-row-field">
						<div><label for="cvs-dashboard-theme">پوسته‌ی داشبورد</label><p>حالت خودکار از تنظیم سیستم‌عامل پیروی می‌کند.</p></div>
						<select id="cvs-dashboard-theme" name="dashboard_theme">
							<option value="light" <?php selected( $settings['dashboard_theme'], 'light' ); ?>>روشن</option>
							<option value="dark" <?php selected( $settings['dashboard_theme'], 'dark' ); ?>>تیره</option>
							<option value="auto" <?php selected( $settings['dashboard_theme'], 'auto' ); ?>>خودکار</option>
						</select>
					</div>
					<div class="cvs-setting-row">
						<div><label for="cvs-persian-digits">نمایش اعداد فارسی</label><p>در صورت غیرفعال‌سازی، اعداد لاتین نمایش داده می‌شوند.</p></div>
						<label class="cvs-switch"><input id="cvs-persian-digits" type="checkbox" name="persian_digits" value="1" <?php checked( ! empty( $settings['persian_digits'] ) ); ?>><span></span></label>
					</div>
					<div class="cvs-setting-row cvs-setting-row-field">
						<div><label for="cvs-retention">مدت نگهداری داده</label><p>عدد صفر یعنی نگهداری همیشگی. پاک‌سازی با زمان محلی وردپرس انجام می‌شود.</p></div>
						<div class="cvs-input-suffix"><input id="cvs-retention" type="number" min="0" name="retention_days" value="<?php echo esc_attr( $settings['retention_days'] ); ?>"><span>روز</span></div>
					</div>
					<div class="cvs-setting-row">
						<div><label for="cvs-delete-uninstall">حذف داده هنگام پاک‌کردن افزونه</label><p>در حذف کامل افزونه، همه‌ی جداول و تنظیمات آن نیز حذف شوند.</p></div>
						<label class="cvs-switch"><input id="cvs-delete-uninstall" type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?>><span></span></label>
					</div>
				</section>

				<div class="cvs-form-actions"><button type="submit" class="cvs-btn cvs-btn-primary"><span class="dashicons dashicons-saved"></span>ذخیره تنظیمات</button></div>
			</form>

			<section class="cvs-danger-zone">
				<div><h2>بازنشانی کامل آمار</h2><p>همه‌ی بازدیدها، نشست‌ها و خلاصه‌ها برای همیشه حذف می‌شوند.</p></div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('آیا از حذف کامل آمار مطمئن هستید؟ این عمل قابل بازگشت نیست.');">
					<input type="hidden" name="action" value="cvs_reset_stats">
					<?php wp_nonce_field( 'cvs_reset_stats' ); ?>
					<button type="submit" class="cvs-btn cvs-btn-danger">حذف کامل داده‌ها</button>
				</form>
			</section>
		</main>

		<nav class="cvs-bottom-nav" aria-label="ناوبری موبایل">
			<?php foreach ( array( 'dashboard', 'visitors', 'sources', 'sales' ) as $key ) : ?>
				<a href="<?php echo esc_url( CVS_Admin::get_tab_url( $key ) ); ?>">
					<span class="dashicons <?php echo esc_attr( $navigation[ $key ]['icon'] ); ?>"></span>
					<small><?php echo esc_html( $navigation[ $key ]['label'] ); ?></small>
				</a>
			<?php endforeach; ?>
			<details class="cvs-more-nav">
				<summary class="is-active"><span class="dashicons dashicons-menu"></span><small>بیشتر</small></summary>
				<div>
					<?php foreach ( array( 'funnel', 'heatmap', 'geography', 'events', 'settings', 'guide' ) as $key ) : ?>
						<a class="<?php echo 'settings' === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( CVS_Admin::get_tab_url( $key ) ); ?>"><span class="dashicons <?php echo esc_attr( $navigation[ $key ]['icon'] ); ?>"></span><?php echo esc_html( $navigation[ $key ]['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</details>
		</nav>
	</div>
</div>
