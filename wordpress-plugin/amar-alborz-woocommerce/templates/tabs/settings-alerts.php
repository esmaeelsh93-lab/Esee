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
		<input type="hidden" name="current_tab" value="alerts" />
		<?php wp_nonce_field( 'aaw_save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">فعال‌سازی هشدار هوشمند</th>
				<td>
					<label>
						<input type="checkbox" name="alerts_enabled" value="1" <?php checked( ! empty( $settings['alerts_enabled'] ) ); ?> />
						بررسی روزانه‌ی خودکار وضعیت فروشگاه فعال باشد.
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">ارسال ایمیل هشدار <?php echo AAW_Admin::tooltip( 'در صورت فعال بودن، هنگام ثبت هر هشدار جدید، ایمیلی به آدرس مدیر سایت ارسال می‌شود.' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="alert_email_enabled" value="1" <?php checked( ! empty( $settings['alert_email_enabled'] ) ); ?> />
						ارسال ایمیل به مدیر سایت هنگام ثبت هشدار جدید.
					</label>
				</td>
			</tr>
		</table>

		<h2 class="aaw-settings-subheading">آستانه‌های هشدار</h2>
		<p class="description">هر آستانه، حداقل درصد تغییر (نسبت به ۷ روز قبل) برای فعال‌شدن هشدار است.</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">افت نرخ تبدیل (٪) <?php echo AAW_Admin::tooltip( 'اگر نرخ تبدیل بیش از این درصد نسبت به هفته‌ی قبل کاهش یابد، هشدار ثبت می‌شود.' ); ?></th>
				<td><input type="number" min="1" max="100" name="alert_conversion_drop" value="<?php echo esc_attr( $settings['alert_conversion_drop'] ); ?>" class="small-text" />٪</td>
			</tr>
			<tr>
				<th scope="row">افزایش Bounce Rate (٪)</th>
				<td><input type="number" min="1" max="200" name="alert_bounce_increase" value="<?php echo esc_attr( $settings['alert_bounce_increase'] ); ?>" class="small-text" />٪</td>
			</tr>
			<tr>
				<th scope="row">کاهش فروش (٪)</th>
				<td><input type="number" min="1" max="100" name="alert_revenue_drop" value="<?php echo esc_attr( $settings['alert_revenue_drop'] ); ?>" class="small-text" />٪</td>
			</tr>
			<tr>
				<th scope="row">افزایش سبدهای رها شده (٪)</th>
				<td><input type="number" min="1" max="200" name="alert_cart_abandon_increase" value="<?php echo esc_attr( $settings['alert_cart_abandon_increase'] ); ?>" class="small-text" />٪</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره تنظیمات', 'aaw-btn aaw-btn-primary', 'submit', false ); ?>
	</form>
</div>
