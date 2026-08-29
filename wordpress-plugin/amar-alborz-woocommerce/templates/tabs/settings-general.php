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
		<input type="hidden" name="current_tab" value="general" />
		<?php wp_nonce_field( 'aaw_save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">نادیده گرفتن مدیران و نویسندگان <?php echo AAW_Admin::tooltip( 'اگر فعال باشد، بازدید کاربرانی که وارد سایت شده و حداقل دسترسی نویسندگی دارند، در آمار حساب نمی‌شود.' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="exclude_staff" value="1" <?php checked( ! empty( $settings['exclude_staff'] ) ); ?> />
						بازدید کارکنان فروشگاه در آمار شمارش نشود.
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">مدت زمان نشست (دقیقه) <?php echo AAW_Admin::tooltip( 'بازدیدهای یک کاربر در این بازه‌ی زمانی، فقط یک «بازدیدکننده» محسوب می‌شود؛ حتی اگر چند صفحه ببیند.' ); ?></th>
				<td>
					<input type="number" min="1" name="session_timeout" value="<?php echo esc_attr( $settings['session_timeout'] ); ?>" class="small-text" />
				</td>
			</tr>
			<tr>
				<th scope="row">مدت‌زمان تشخیص سبد رها شده (ساعت) <?php echo AAW_Admin::tooltip( 'اگر سبد خریدی برای این مدت بدون تغییر و بدون ثبت سفارش بماند، به‌عنوان «رها شده» علامت می‌خورد.' ); ?></th>
				<td>
					<input type="number" min="1" name="cart_abandon_hours" value="<?php echo esc_attr( $settings['cart_abandon_hours'] ); ?>" class="small-text" />
				</td>
			</tr>
		</table>

		<h2 class="aaw-settings-subheading">قابلیت‌های حرفه‌ای (نسخه تجاری)</h2>
		<p class="description">این ویژگی‌ها به‌صورت پیش‌فرض غیرفعال هستند تا سرعت سایت شما تحت تأثیر قرار نگیرد؛ فقط در صورت نیاز آن‌ها را فعال کنید.</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">فعال‌سازی Heatmap <?php echo AAW_Admin::tooltip( 'ثبت مختصات نسبی کلیک و عمق اسکرول کاربران در صفحات سایت؛ داده‌ی حساس ذخیره نمی‌شود.' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="heatmap_enabled" value="1" <?php checked( ! empty( $settings['heatmap_enabled'] ) ); ?> />
						ردیابی Heatmap روی صفحات سایت فعال شود.
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">فعال‌سازی Session Replay <?php echo AAW_Admin::tooltip( 'ضبط مسیر حرکت، کلیک و اسکرول کاربران برای پخش مجدد؛ هرگز مقدار فیلدهای فرم ذخیره نمی‌شود.' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="replay_enabled" value="1" <?php checked( ! empty( $settings['replay_enabled'] ) ); ?> />
						ضبط Session Replay روی صفحات سایت فعال شود.
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره تنظیمات', 'aaw-btn aaw-btn-primary', 'submit', false ); ?>
	</form>
</div>
