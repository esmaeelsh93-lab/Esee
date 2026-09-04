<?php
/**
 * Settings partial: cloud AI (Groq / OpenRouter + relay).
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
$llms_txt    = (string) get_option( 'shojaei_seo_llms_txt', '' );

$known_ids = array();
foreach ( $presets as $rows ) {
	foreach ( $rows as $row ) {
		$known_ids[] = $row['id'];
	}
}
$custom_model = in_array( $model, $known_ids, true ) ? '' : $model;
$store_name   = Shojaei_SEO_Store_Profile::name();
$store_city   = Shojaei_SEO_Store_Profile::city();
$store_niche  = Shojaei_SEO_Store_Profile::niche();
$store_about  = Shojaei_SEO_Store_Profile::about();
$store_suffix = (string) Shojaei_SEO_Helpers::get_option( Shojaei_SEO_Store_Profile::OPT_SUFFIX, 'خرید از {store}' );
$store_tone   = (string) Shojaei_SEO_Helpers::get_option( Shojaei_SEO_Store_Profile::OPT_TONE, 'friendly' );
$store_voice  = Shojaei_SEO_Store_Profile::voice();
$store_neg    = Shojaei_SEO_Store_Profile::negative_rules();
$store_samples = Shojaei_SEO_Store_Profile::samples();
$draft_mode   = Shojaei_SEO_Store_Profile::draft_mode();
$relay_https  = (string) Shojaei_SEO_Helpers::get_option( Shojaei_SEO_AI_Client::OPT_RELAY_HTTPS, '' );
$relay_backup = (string) Shojaei_SEO_Helpers::get_option( Shojaei_SEO_AI_Client::OPT_RELAY_BACKUP, '' );
?>

<div class="shojaei-content-server" id="shojaei-store-profile-fields" style="margin-bottom:24px;">
	<h3 style="margin:0 0 8px;font-size:15px;"><?php esc_html_e( 'پروفایل فروشگاه', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="description"><?php esc_html_e( 'این اطلاعات در پرامپت‌های تولید محتوا استفاده می‌شود — مثلاً «خرید کفش ورزشی از رضا جردن — تهران».', 'shojaei-seo-for-woo' ); ?></p>
	<div class="shojaei-settings-grid" style="margin-top:12px;">
		<div class="shojaei-form-group">
			<label for="shojaei_seo_store_name"><?php esc_html_e( 'نام فروشگاه / برند', 'shojaei-seo-for-woo' ); ?></label>
			<input type="text" id="shojaei_seo_store_name" name="shojaei_seo_store_name" value="<?php echo esc_attr( $store_name ); ?>" class="regular-text" />
		</div>
		<div class="shojaei-form-group">
			<label for="shojaei_seo_store_city"><?php esc_html_e( 'شهر', 'shojaei-seo-for-woo' ); ?></label>
			<input type="text" id="shojaei_seo_store_city" name="shojaei_seo_store_city" value="<?php echo esc_attr( $store_city ); ?>" class="regular-text" />
		</div>
		<div class="shojaei-form-group">
			<label for="shojaei_seo_store_niche"><?php esc_html_e( 'حوزه کاری', 'shojaei-seo-for-woo' ); ?></label>
			<input type="text" id="shojaei_seo_store_niche" name="shojaei_seo_store_niche" value="<?php echo esc_attr( $store_niche ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'مثلاً: پوشاک ورزشی، لوازم خانگی…', 'shojaei-seo-for-woo' ); ?>" />
		</div>
		<div class="shojaei-form-group">
			<label for="shojaei_seo_store_tone"><?php esc_html_e( 'لحن محتوا', 'shojaei-seo-for-woo' ); ?></label>
			<select id="shojaei_seo_store_tone" name="shojaei_seo_store_tone">
				<option value="friendly" <?php selected( $store_tone, 'friendly' ); ?>><?php esc_html_e( 'صمیمی فروشگاهی', 'shojaei-seo-for-woo' ); ?></option>
				<option value="formal" <?php selected( $store_tone, 'formal' ); ?>><?php esc_html_e( 'رسمی و حرفه‌ای', 'shojaei-seo-for-woo' ); ?></option>
				<option value="expert" <?php selected( $store_tone, 'expert' ); ?>><?php esc_html_e( 'کارشناسی و دقیق', 'shojaei-seo-for-woo' ); ?></option>
				<option value="comparison" <?php selected( $store_tone, 'comparison' ); ?>><?php esc_html_e( 'مقایسه‌ای و انتخابی', 'shojaei-seo-for-woo' ); ?></option>
				<option value="guide" <?php selected( $store_tone, 'guide' ); ?>><?php esc_html_e( 'راهنمای خرید', 'shojaei-seo-for-woo' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'لحن ثابت همه محتوا — دیگر تصادفی نیست.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	</div>
	<div class="shojaei-form-group" style="margin-top:12px;">
		<label for="shojaei_seo_store_voice"><?php esc_html_e( 'لحن و قوانین برند', 'shojaei-seo-for-woo' ); ?></label>
		<textarea id="shojaei_seo_store_voice" name="shojaei_seo_store_voice" rows="4" class="large-text" placeholder="<?php esc_attr_e( 'مثلاً: با مشتری مثل آدم حرف بزن، از «شما» استفاده کن، ایموجی نگذار…', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( $store_voice ); ?></textarea>
	</div>
	<div class="shojaei-form-group">
		<label for="shojaei_seo_store_negative_rules"><?php esc_html_e( 'قوانین منفی (ممنوعیت‌ها)', 'shojaei-seo-for-woo' ); ?></label>
		<textarea id="shojaei_seo_store_negative_rules" name="shojaei_seo_store_negative_rules" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'کلمات ممنوع، ادعای ۱۰۰٪، بهترین دنیا، تضمین درمان…', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( $store_neg ); ?></textarea>
	</div>
	<div class="shojaei-form-group">
		<label for="shojaei_seo_store_samples"><?php esc_html_e( 'نمونه متن واقعی (۱–۲ پارagraph)', 'shojaei-seo-for-woo' ); ?></label>
		<textarea id="shojaei_seo_store_samples" name="shojaei_seo_store_samples" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'متن محصولی که از سبک نوشتاری‌تان راضی هستید…', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( $store_samples ); ?></textarea>
	</div>
	<div class="shojaei-form-group" style="margin-top:12px;">
		<label for="shojaei_seo_store_meta_suffix"><?php esc_html_e( 'الگوی انتهای عنوان متا', 'shojaei-seo-for-woo' ); ?></label>
		<input type="text" id="shojaei_seo_store_meta_suffix" name="shojaei_seo_store_meta_suffix" value="<?php echo esc_attr( $store_suffix ); ?>" class="regular-text" dir="rtl" />
		<p class="description"><?php esc_html_e( 'توکن‌ها: {store} {city} {niche} {product} {site}', 'shojaei-seo-for-woo' ); ?></p>
	</div>
	<div class="shojaei-form-group">
		<label for="shojaei_seo_store_about"><?php esc_html_e( 'درباره فروشگاه (اختیاری)', 'shojaei-seo-for-woo' ); ?></label>
		<textarea id="shojaei_seo_store_about" name="shojaei_seo_store_about" rows="3" class="large-text"><?php echo esc_textarea( $store_about ); ?></textarea>
	</div>
</div>

<div class="shojaei-content-server" id="shojaei-content-server-fields">
	<div class="shojaei-gsc-status <?php echo $enabled && $configured ? ( $health_ok ? 'is-connected' : 'is-disconnected' ) : 'is-disconnected'; ?>" id="shojaei-ai-status-box" style="margin-bottom:16px;">
		<span class="shojaei-gsc-light" aria-hidden="true"></span>
		<div>
			<strong id="shojaei-ai-status-label">
				<?php
				if ( ! $enabled ) {
					esc_html_e( 'تولید محتوا خاموش است', 'shojaei-seo-for-woo' );
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
				<span dir="ltr"><?php echo esc_html( $provider ); ?></span>
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

	<p class="shojaei-desc"><?php esc_html_e( 'سرویس‌دهنده و کلید API خود را انتخاب کنید. درخواست از هاست فروشگاه ارسال می‌شود — نیازی به VPN یا تنظیم سرور نیست.', 'shojaei-seo-for-woo' ); ?></p>

	<div class="shojaei-settings-grid">
		<label class="shojaei-setting-item">
			<input type="checkbox" name="shojaei_seo_ai_enabled" value="yes" <?php checked( $enabled ); ?> />
			<span><?php esc_html_e( 'فعال‌سازی تولید محتوا', 'shojaei-seo-for-woo' ); ?></span>
		</label>
		<label class="shojaei-setting-item">
			<input type="checkbox" name="shojaei_seo_schema_itemlist_enabled" value="yes" <?php checked( $itemlist ); ?> />
			<span><?php esc_html_e( 'چاپ ItemList Schema در فرانت (فقط صفحه دسته / آرشیو فروشگاه — نه صفحه محصول)', 'shojaei-seo-for-woo' ); ?></span>
		</label>
	</div>

	<h4 style="margin:16px 0 8px;font-size:14px;"><?php esc_html_e( 'ارائه‌دهنده', 'shojaei-seo-for-woo' ); ?></h4>
	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_provider"><?php esc_html_e( 'سرویس هوش مصنوعی', 'shojaei-seo-for-woo' ); ?></label>
		<select id="shojaei_seo_ai_provider" name="shojaei_seo_ai_provider">
			<option value="openrouter" <?php selected( $provider, 'openrouter' ); ?>><?php esc_html_e( 'OpenRouter (پیشنهادی — روی Relay پایدار)', 'shojaei-seo-for-woo' ); ?></option>
			<option value="groq" <?php selected( $provider, 'groq' ); ?>><?php esc_html_e( 'Groq مستقیم (ممکن است روی Relay محدود شود)', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<p class="description" style="margin-top:6px;"><?php esc_html_e( 'کلید OpenRouter (sk-or-) برای همه مدل‌ها کافی است. Groq مستقیم (gsk_) از Relay مسدود است؛ افزونه خودکار از هاست شما امتحان می‌کند.', 'shojaei-seo-for-woo' ); ?></p>
	</div>

	<div class="shojaei-notice" style="margin:12px 0;padding:10px 12px;border:1px solid #c3c4c7;border-radius:6px;background:#fff;">
		<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'کلید رایگان از کجا بگیرم؟', 'shojaei-seo-for-woo' ); ?></strong></p>
		<p class="description" style="margin:0;">
			<?php esc_html_e( 'Groq:', 'shojaei-seo-for-woo' ); ?>
			<a href="https://console.groq.com/keys" target="_blank" rel="noopener noreferrer" dir="ltr">console.groq.com/keys</a>
			&nbsp;|&nbsp;
			<?php esc_html_e( 'OpenRouter:', 'shojaei-seo-for-woo' ); ?>
			<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer" dir="ltr">openrouter.ai/keys</a>
		</p>
		<p class="description" style="margin:6px 0 0;"><?php esc_html_e( 'ثبت‌نام با ایمیل معمولاً یک دقیقه طول می‌کشد. کلید را فقط در همین صفحه ذخیره کنید.', 'shojaei-seo-for-woo' ); ?></p>
	</div>

	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_api_key"><?php esc_html_e( 'کلید API', 'shojaei-seo-for-woo' ); ?></label>
		<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
			<input type="password" id="shojaei_seo_ai_api_key" name="shojaei_seo_ai_api_key" value="" class="regular-text" dir="ltr" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr( '••••••••••••  ' . __( '(ذخیره شده — برای تغییر، کلید جدید بنویسید)', 'shojaei-seo-for-woo' ) ) : ''; ?>" />
			<button type="button" class="button" id="shojaei-ai-key-toggle"><?php esc_html_e( 'نمایش', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<small><?php esc_html_e( 'اگر خالی بماند، کلید قبلی حفظ می‌شود.', 'shojaei-seo-for-woo' ); ?></small>
	</div>

	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_model"><?php esc_html_e( 'مدل', 'shojaei-seo-for-woo' ); ?></label>
		<select id="shojaei_seo_ai_model" name="shojaei_seo_ai_model">
			<?php foreach ( $presets[ $provider ] ?? $presets['groq'] as $row ) : ?>
				<option value="<?php echo esc_attr( $row['id'] ); ?>" <?php selected( $model, $row['id'] ); ?>><?php echo esc_html( $row['label'] ); ?></option>
			<?php endforeach; ?>
			<option value="__custom__" <?php selected( $custom_model !== '' ); ?>><?php esc_html_e( 'مدل سفارشی…', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<input type="text" id="shojaei_seo_ai_model_custom" name="shojaei_seo_ai_model_custom" value="<?php echo esc_attr( $custom_model ); ?>" class="regular-text" dir="ltr" style="margin-top:8px;" placeholder="custom-model-id" />
	</div>

	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_relay_https_url"><?php esc_html_e( 'Relay اختصاصی (HTTPS — اختیاری)', 'shojaei-seo-for-woo' ); ?></label>
		<input type="url" id="shojaei_seo_ai_relay_https_url" name="shojaei_seo_ai_relay_https_url" value="<?php echo esc_attr( $relay_https ); ?>" class="regular-text" dir="ltr" placeholder="https://ai.example.com" />
		<p class="description"><?php esc_html_e( 'Relay اصلی با TLS. در غیر این صورت HTTPS/HTTP پیش‌فرض امتحان می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
	</div>
	<div class="shojaei-form-group">
		<label for="shojaei_seo_ai_relay_backup_urls"><?php esc_html_e( 'Relay پشتیبان (هر خط یک URL)', 'shojaei-seo-for-woo' ); ?></label>
		<textarea id="shojaei_seo_ai_relay_backup_urls" name="shojaei_seo_ai_relay_backup_urls" rows="3" class="large-text code" dir="ltr" placeholder="https://relay2.example.com&#10;http://194.60.231.229"><?php echo esc_textarea( $relay_backup ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Failover خودکار — اگر relay اول جواب نداد، بعدی امتحان می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
	</div>

	<label class="shojaei-setting-item" style="display:block;margin:12px 0;">
		<input type="checkbox" name="<?php echo esc_attr( Shojaei_SEO_Store_Profile::OPT_DRAFT ); ?>" value="yes" <?php checked( $draft_mode ); ?> />
		<span><?php esc_html_e( 'حالت پیش‌نویس — بسته کامل مستقیم در متا ذخیره نشود تا تأیید دستی', 'shojaei-seo-for-woo' ); ?></span>
	</label>

	<p class="shojaei-preview-form" style="margin-top:12px;">
		<button type="button" class="button button-primary" id="shojaei-ai-health"><?php esc_html_e( 'ذخیره و تست اتصال', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<pre id="shojaei-ai-health-result" class="shojaei-test-result" style="display:none;margin-top:8px;"></pre>

	<h4 style="margin:20px 0 8px;font-size:14px;"><?php esc_html_e( 'Alt تصاویر (انبوه)', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="description"><?php esc_html_e( 'محصولاتی که تصویر بدون Alt دارند اسکن می‌شوند و متن فارسی سئو نوشته می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
	<p>
		<button type="button" class="button" id="shojaei-ai-bulk-alt"><?php esc_html_e( 'تولید Alt برای تصاویر بدون متن', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<p class="description" id="shojaei-ai-bulk-alt-status"></p>

	<h4 style="margin:20px 0 8px;font-size:14px;"><?php esc_html_e( 'قابلیت‌ها در ویرایش محصول', 'shojaei-seo-for-woo' ); ?></h4>
	<ul class="shojaei-feature-list" style="margin:8px 0 16px;padding-right:18px;list-style:disc;">
		<li><?php esc_html_e( 'پیشنهاد کلمه کلیدی، عنوان سئو، Meta Description', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'توضیح کوتاه و توضیح کامل HTML', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'سئو خودکار محصول (یک کلیک)، FAQ + Schema، Alt تصاویر، ItemList، بسته کامل', 'shojaei-seo-for-woo' ); ?></li>
	</ul>

	<h4 style="margin:20px 0 8px;font-size:14px;"><?php esc_html_e( 'فایل llms.txt', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="shojaei-preview-form">
		<button type="button" class="button" id="shojaei-ai-llms"><?php esc_html_e( 'تولید llms.txt', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button" id="shojaei-ai-llms-write"><?php esc_html_e( 'نوشتن در /llms.txt', 'shojaei-seo-for-woo' ); ?></button>
		<?php if ( file_exists( ABSPATH . 'llms.txt' ) ) : ?>
			<a class="button" href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مشاهده فایل', 'shojaei-seo-for-woo' ); ?></a>
		<?php endif; ?>
	</p>
	<textarea id="shojaei-ai-llms-preview" rows="10" class="large-text code" dir="ltr" readonly placeholder="llms.txt"><?php echo esc_textarea( $llms_txt ); ?></textarea>
</div>
