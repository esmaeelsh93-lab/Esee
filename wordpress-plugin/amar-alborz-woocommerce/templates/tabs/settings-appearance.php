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
		<input type="hidden" name="current_tab" value="appearance" />
		<?php wp_nonce_field( 'aaw_save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">پوسته‌ی پیش‌فرض <?php echo AAW_Admin::tooltip( 'هر کاربر می‌تواند با دکمه‌ی 🌙/☀️ در بالای صفحه، پوسته را برای خودش موقتاً تغییر دهد؛ این گزینه فقط حالت پیش‌فرض را تعیین می‌کند.' ); ?></th>
				<td>
					<label style="display:inline-flex; align-items:center; gap:6px; margin-left:16px;">
						<input type="radio" name="theme_default" value="dark" <?php checked( 'dark', $settings['theme_default'] ); ?> /> تاریک (Dark)
					</label>
					<label style="display:inline-flex; align-items:center; gap:6px;">
						<input type="radio" name="theme_default" value="light" <?php checked( 'light', $settings['theme_default'] ); ?> /> روشن (Light)
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( 'ذخیره تنظیمات', 'aaw-btn aaw-btn-primary', 'submit', false ); ?>
	</form>
</div>
