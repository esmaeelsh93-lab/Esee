<?php
/**
 * Settings partial: cloud AI (OpenRouter free models).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$enabled     = Shojaei_SEO_AI_Client::is_enabled();
$configured  = Shojaei_SEO_AI_Client::is_configured();
$provider    = Shojaei_SEO_AI_Client::provider();
$model       = Shojaei_SEO_AI_Client::model();
$has_key     = '' !== Shojaei_SEO_AI_Client::api_key();
$presets     = Shojaei_SEO_AI_Client::model_presets();
$last_health = get_option( Shojaei_SEO_AI_Client::OPT_HEALTH, array() );
$last_health = is_array( $last_health ) ? $last_health : array();
$health_ok   = ! empty( $last_health['ok'] );
$health_time = ! empty( $last_health['time'] ) ? (int) $last_health['time'] : 0;
$itemlist    = 'yes' === (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_itemlist_enabled', 'yes' );

$known_ids = array();
foreach ( $presets as $rows ) {
	foreach ( $rows as $row ) {
		$known_ids[] = $row['id'];
	}
}
$custom_model = in_array( $model, $known_ids, true ) ? '' : $model;
$relay_https  = (string) Shojaei_SEO_Helpers::get_option( Shojaei_SEO_AI_Client::OPT_RELAY_HTTPS, '' );
$relay_backup = (string) Shojaei_SEO_Helpers::get_option( Shojaei_SEO_AI_Client::OPT_RELAY_BACKUP, '' );
?>

<div class="shojaei-content-server" id="shojaei-content-server-fields">
	<div class="shojaei-gsc-status <?php echo $enabled && $configured ? ( $health_ok ? 'is-connected' : 'is-disconnected' ) : 'is-disconnected'; ?>" id="shojaei-ai-status-box" style="margin-bottom:16px;">
		<span class="shojaei-gsc-light" aria-hidden="true"></span>
		<div>
			<strong id="shojaei-ai-status-label">
				<?php
				if ( ! $enabled ) {
					esc_html_e( 'هوش مصنوعی خاموش است', 'shojaei-seo-for-woo' );
				} elseif ( ! $configured ) {
					esc_html_e( 'کلید API ذخیره نشده', 'shojaei-seo-for-woo' );
				} elseif ( $health_ok ) {
					esc_html_e( 'متصل — آخرین تست موفق', 'shojaei-seo-for-woo' );
				} else {
					esc_html_e( 'فعال — «تست اتصال» را بزنید', 'shojaei-seo-for-woo' );
				}
				?>
			</strong>
			<p class="description" style="margin:4px 0 0;">
				<span dir="ltr">OpenRouter</span>
				&nbsp;·&nbsp;
				<span dir="ltr"><?php echo esc_html( $model ); ?></span>
			</p>
			<?php if ( $health_time ) : ?>
				<small>
					<?php
					echo esc_html( sprintf( __( 'آخرین تست: %s', 'shojaei-seo-for-woo' ), wp_date( 'Y-m-d H:i', $health_time ) ) );
					if ( ! empty( $last_health['latency'] ) ) {
						echo ' — ' . esc_html( (int) $last_health['latency'] ) . ' ms';
					}
					?>
				</small>
			<?php endif; ?>
			<?php if ( ! empty( $last_health['message'] ) && empty( $last_health['ok'] ) ) : ?>
				<p class="description" style="color:#b32d2e;margin-top:4px;"><?php echo esc_html( (string) $last_health['message'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<p class="shojaei-desc"><?php esc_html_e( 'OpenRouter با مدل‌های رایگان (:free) — فقط Alt تصاویر و کلمات مرتبط. درخواست از هاست فروشگاه از طریق Relay ارسال می‌شود.', 'shojaei-seo-for-woo' ); ?></p>

	<div class="shojaei-settings-grid">
		<label class="shojaei-setting-item">
			<input type="checkbox" name="shojaei_seo_ai_enabled" value="yes" <?php checked( $enabled ); ?> />
			<span><?php esc_html_e( 'فعال‌سازی هوش مصنوعی (Alt + کلمات مرتبط)', 'shojaei-seo-for-woo' ); ?></span>
		</label>
		<label class="shojaei-setting-item">
			<input type="checkbox" name="shojaei_seo_schema_itemlist_enabled" value="yes" <?php checked( $itemlist ); ?> />
			<span><?php esc_html_e( 'چاپ ItemList Schema در فرانت (فقط صفحه دسته / آرشیو فروشگاه — نه صفحه محصول)', 'shojaei-seo-for-woo' ); ?></span>
		</label>
	</div>

	<input type="hidden" name="shojaei_seo_ai_provider" value="openrouter" />

	<div class="shojaei-notice" style="margin:12px 0;padding:10px 12px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;">
		<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'کلید رایگان OpenRouter', 'shojaei-seo-for-woo' ); ?></strong></p>
		<p class="description" style="margin:0;">
			<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer" dir="ltr">openrouter.ai/keys</a>
		</p>
		<p class="description" style="margin:6px 0 0;"><?php esc_html_e( 'مدل‌های :free هزینه ندارند. کلید را فقط در همین صفحه ذخیره کنید.', 'shojaei-seo-for-woo' ); ?></p>
	</div>

	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_api_key"><?php esc_html_e( 'API Key', 'shojaei-seo-for-woo' ); ?></label>
		<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="password" id="shojaei_seo_ai_api_key" name="shojaei_seo_ai_api_key" value="" class="regular-text" dir="ltr" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr( '••••••••••••  ' . __( '(ذخیره شده — برای تغییر، کلید جدید بنویسید)', 'shojaei-seo-for-woo' ) ) : ''; ?>" />
			<button type="button" class="button" id="shojaei-ai-key-toggle"><?php esc_html_e( 'نمایش', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<small><?php esc_html_e( 'اگر خالی بماند، کلید قبلی حفظ می‌شود.', 'shojaei-seo-for-woo' ); ?></small>
	</div>

	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_model"><?php esc_html_e( 'مدل رایگان', 'shojaei-seo-for-woo' ); ?></label>
		<select id="shojaei_seo_ai_model" name="shojaei_seo_ai_model">
			<?php foreach ( $presets['openrouter'] ?? array() as $row ) : ?>
				<option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( $model, $row['id'] ); ?>><?php echo esc_html( $row['label'] ); ?></option>
			<?php endforeach; ?>
			<option value="__custom__" <?php selected( $custom_model !== '' ); ?>><?php esc_html_e( 'مدل سفارشی…', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<input type="text" id="shojaei_seo_ai_model_custom" name="shojaei_seo_ai_model_custom" value="<?php echo esc_attr( $custom_model ); ?>" class="regular-text" dir="ltr" style="margin-top:8px;<?php echo '' === $custom_model ? ' display:none;' : ''; ?>" placeholder="provider/model:free" />
		<p class="description"><?php esc_html_e( 'فقط مدل‌های :free پشتیبانی می‌شوند.', 'shojaei-seo-for-woo' ); ?></p>
	</div>

	<div class="shojaei-form-group shojaei-ai-relay-fields">
		<label for="shojaei_seo_ai_relay_https_url"><?php esc_html_e( 'Relay اختصاصی (HTTPS — اختیاری)', 'shojaei-seo-for-woo' ); ?></label>
		<input type="url" id="shojaei_seo_ai_relay_https_url" name="shojaei_seo_ai_relay_https_url" value="<?php echo esc_attr( $relay_https ); ?>" class="regular-text" dir="ltr" placeholder="https://ai.example.com" />
	</div>
	<div class="shojaei-form-group shojaei-ai-relay-fields">
		<label for="shojaei_seo_ai_relay_backup_urls"><?php esc_html_e( 'Relay پشتیبان (هر خط یک URL)', 'shojaei-seo-for-woo' ); ?></label>
		<textarea id="shojaei_seo_ai_relay_backup_urls" name="shojaei_seo_ai_relay_backup_urls" rows="3" class="large-text code" dir="ltr" placeholder="https://relay2.example.com&#10;http://194.60.231.229"><?php echo esc_textarea( $relay_backup ); ?></textarea>
	</div>

	<p class="shojaei-preview-form" style="margin-top:12px;">
		<button type="button" class="button button-primary" id="shojaei-ai-health"><?php esc_html_e( 'ذخیره و تست اتصال', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<pre id="shojaei-ai-health-result" class="shojaei-test-result" style="display:none;margin-top:8px;"></pre>

	<h4 style="margin:20px 0 8px;font-size:14px;"><?php esc_html_e( 'Alt تصاویر (انبوه)', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="description"><?php esc_html_e( 'محصولاتی که تصویر بدون Alt دارند اسکن می‌شوند.', 'shojaei-seo-for-woo' ); ?></p>
	<p>
		<button type="button" class="button" id="shojaei-ai-bulk-alt"><?php esc_html_e( 'تولید Alt برای تصاویر بدون متن', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<p class="description" id="shojaei-ai-bulk-alt-status"></p>

	<h4 style="margin:20px 0 8px;font-size:14px;"><?php esc_html_e( 'قابلیت‌ها در ویرایش محصول', 'shojaei-seo-for-woo' ); ?></h4>
	<ul class="shojaei-feature-list" style="margin:8px 0 16px;padding-right:18px;list-style:disc;">
		<li><?php esc_html_e( 'پیشنهاد کلمات مرتبط (LSI) برای فیلد کلمات مرتبط', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'تولید Alt فارسی برای تصویر شاخص و گالری', 'shojaei-seo-for-woo' ); ?></li>
	</ul>
</div>
