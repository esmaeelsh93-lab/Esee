<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aaw-settings-panel">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="aaw_save_settings" />
		<input type="hidden" name="current_tab" value="privacy" />
		<?php wp_nonce_field( 'aaw_save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">حذف آدرس‌های IP خاص <?php echo AAW_Admin::tooltip( 'برای مثال آدرس IP خودتان را وارد کنید تا بازدیدهایتان در آمار حساب نشود.' ); ?></th>
				<td>
					<textarea name="excluded_ips" rows="4" class="large-text" placeholder="هر آدرس IP در یک خط"><?php echo esc_textarea( $settings['excluded_ips'] ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row">مدت نگهداری داده‌ها (روز) <?php echo AAW_Admin::tooltip( 'عدد ۰ یعنی نگهداری همیشگی. در غیر این صورت داده‌های قدیمی‌تر از این تعداد روز هر شب خودکار حذف می‌شوند.' ); ?></th>
				<td>
					<input type="number" min="0" name="retention_days" value="<?php echo esc_attr( $settings['retention_days'] ); ?>" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">حذف داده‌ها هنگام حذف افزونه</th>
				<td>
					<label>
						<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
						با حذف کامل افزونه از سایت، تمام آمار ثبت‌شده نیز پاک شود.
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره تنظیمات', 'aaw-btn aaw-btn-primary', 'submit', false ); ?>
	</form>

	<div class="aaw-danger-zone">
		<h2>بازنشانی آمار</h2>
		<p>با این کار تمام رکوردهای آماری ثبت‌شده (بازدید، قیف فروش، سبد خرید، Heatmap، Session Replay، هشدارها) برای همیشه حذف می‌شوند. این عمل غیرقابل بازگشت است و سفارش‌های واقعی ووکامرس شما را تغییر نمی‌دهد.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('آیا از حذف کامل آمار مطمئن هستید؟ این عمل قابل بازگشت نیست.');">
			<input type="hidden" name="action" value="aaw_reset_stats" />
			<?php wp_nonce_field( 'aaw_reset_stats' ); ?>
			<button type="submit" class="aaw-btn aaw-btn-danger">حذف کامل آمار</button>
		</form>
	</div>
</div>
