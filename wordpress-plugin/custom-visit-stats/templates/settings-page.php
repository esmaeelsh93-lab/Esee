<?php
/**
 * قالب صفحه‌ی تنظیمات افزونه.
 * این فایل فقط توسط CVS_Admin::render_settings_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<div class="cvs-dashboard" dir="rtl">

		<div class="cvs-header">
			<div>
				<h1>تنظیمات آمار بازدید</h1>
				<div class="cvs-subtitle">تنظیمات نحوه‌ی ثبت و نگهداری آمار بازدید سایت</div>
			</div>
			<div class="cvs-actions">
				<a class="cvs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CVS_Admin::PAGE_STATS ) ); ?>">← بازگشت به گزارش آمار</a>
			</div>
		</div>

		<?php if ( $saved ) : ?>
			<div class="cvs-notice cvs-notice-success">تنظیمات با موفقیت ذخیره شد.</div>
		<?php endif; ?>

		<?php if ( $reset ) : ?>
			<div class="cvs-notice cvs-notice-success">آمار بازدید با موفقیت بازنشانی (حذف) شد.</div>
		<?php endif; ?>

		<div class="cvs-settings-panel">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cvs_save_settings" />
				<?php wp_nonce_field( 'cvs_save_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">نادیده گرفتن مدیران و نویسندگان</th>
						<td>
							<label>
								<input type="checkbox" name="exclude_staff" value="1" <?php checked( ! empty( $settings['exclude_staff'] ) ); ?> />
								بازدید کاربرانی که وارد سایت شده‌اند و حداقل دسترسی نویسندگی دارند، در آمار شمارش نشود.
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">مدت زمان نشست (دقیقه)</th>
						<td>
							<input type="number" min="1" name="session_timeout" value="<?php echo esc_attr( $settings['session_timeout'] ); ?>" class="small-text" />
							<p class="description">بازدیدهای یک کاربر در این بازه‌ی زمانی، فقط یک «ورودی» محسوب می‌شود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">حذف آدرس‌های IP خاص</th>
						<td>
							<textarea name="excluded_ips" rows="4" class="large-text" placeholder="هر آدرس IP در یک خط"><?php echo esc_textarea( $settings['excluded_ips'] ); ?></textarea>
							<p class="description">برای مثال آدرس IP خودتان را وارد کنید تا بازدیدهایتان در آمار حساب نشود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">مدت نگهداری داده‌ها (روز)</th>
						<td>
							<input type="number" min="0" name="retention_days" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text" />
							<p class="description">عدد ۰ به معنای نگهداری همیشگی داده‌هاست. در غیر این صورت داده‌های قدیمی‌تر از این تعداد روز، هر شب به‌صورت خودکار حذف می‌شوند.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">حذف داده‌ها هنگام حذف افزونه</th>
						<td>
							<label>
								<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
								در صورت فعال بودن، با حذف کامل افزونه از سایت، تمام آمار ثبت‌شده نیز پاک خواهد شد.
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( 'ذخیره تنظیمات', 'cvs-btn cvs-btn-primary', 'submit', false ); ?>
			</form>

			<div class="cvs-danger-zone">
				<h2>بازنشانی آمار</h2>
				<p>با این کار تمام رکوردهای آماری ثبت‌شده برای همیشه حذف می‌شوند. این عمل غیرقابل بازگشت است.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('آیا از حذف کامل آمار بازدید مطمئن هستید؟ این عمل قابل بازگشت نیست.');">
					<input type="hidden" name="action" value="cvs_reset_stats" />
					<?php wp_nonce_field( 'cvs_reset_stats' ); ?>
					<button type="submit" class="cvs-btn cvs-btn-danger">حذف کامل آمار بازدید</button>
				</form>
			</div>
		</div>

	</div>
</div>
