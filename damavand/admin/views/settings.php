<?php
/**
 * Settings view.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;
?>

<form method="post" action="" class="shojaei-settings-form">
	<?php wp_nonce_field( 'shojaei_seo_save_settings', 'shojaei_seo_settings_nonce' ); ?>

	<div class="shojaei-card shojaei-settings-intro">
		<h3><?php esc_html_e( 'تنظیمات', 'shojaei-seo-for-woo' ); ?></h3>
		<p class="shojaei-desc"><?php esc_html_e( 'هر بخش را باز کنید، سوئیچ‌ها را تنظیم کنید، پایین صفحه ذخیره کنید.', 'shojaei-seo-for-woo' ); ?></p>
		<p class="shojaei-settings-jump" style="margin-top:8px;">
			<a href="#shojaei-content-server"><strong><?php esc_html_e( 'سرور تولید محتوا', 'shojaei-seo-for-woo' ); ?></strong></a>
			<?php esc_html_e( ' — Groq/OpenRouter و llms.txt', 'shojaei-seo-for-woo' ); ?>
		</p>
	</div>

	<div class="shojaei-accordion shojaei-settings-accordion">

		<div class="shojaei-accordion-item is-open" data-accordion="set-inventory">
			<button class="shojaei-accordion-header" type="button" aria-expanded="true">
				<span class="shojaei-accordion-icon shojaei-icon-blue"><span class="dashicons dashicons-archive"></span></span>
				<span class="shojaei-accordion-title"><?php esc_html_e( '۱ · موجودی و چرخه عمر', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-meta"><?php esc_html_e( 'هسته', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body" style="display:block">
				<div class="shojaei-accordion-content">
					<p class="shojaei-module-note"><?php esc_html_e( 'تصمیم بر اساس موجودی و رفتار صفحه — نه اسکن کور کل فروشگاه.', 'shojaei-seo-for-woo' ); ?></p>
					<div class="shojaei-settings-grid">
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_oos_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_enabled' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'عملیات موجودی و چرخه عمر محصول', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_event_driven" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_event_driven', 'yes' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'حالت رویدادمحور — با تغییر محصول تصمیم بگیر', 'shojaei-seo-for-woo' ); ?></span>
						</label>
					</div>
					<p class="shojaei-settings-jump">
						<a href="#shojaei-acc-oos-lifecycle"><?php esc_html_e( 'جزئیات روزها و ریدایرکت خودکار ↓', 'shojaei-seo-for-woo' ); ?></a>
					</p>
				</div>
			</div>
		</div>

		<div class="shojaei-accordion-item" data-accordion="set-slug">
			<button class="shojaei-accordion-header" type="button" aria-expanded="false">
				<span class="shojaei-accordion-icon shojaei-icon-green"><span class="dashicons dashicons-editor-code"></span></span>
				<span class="shojaei-accordion-title"><?php esc_html_e( '۲ · نامک و Canonical', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-meta"><?php esc_html_e( 'URL', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body">
				<div class="shojaei-accordion-content">
					<div class="shojaei-settings-grid">
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_slug_tools_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_tools_enabled', 'yes' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'ابزار نامک در ویرایشگر محصول (امتیاز خوانایی + هشدار)', 'shojaei-seo-for-woo' ); ?></span>
							<small class="description" style="display:block;margin-top:4px;"><?php esc_html_e( 'خاموش کردن این گزینه ریدایرکت‌های ذخیره‌شده را قطع نمی‌کند.', 'shojaei-seo-for-woo' ); ?></small>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_slug_auto_finglish" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_finglish', 'yes' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'فینگلیش خودکار برای محصولات جدید', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_slug_auto_301" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_301', 'yes' ), 'yes' ); ?> />
							<span><?php esc_html_e( '۳۰۱ خودکار وقتی نامک محصول منتشرشده عوض شود', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_variation_canonical" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_variation_canonical', 'yes' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'Canonical متغیر → آدرس والد', 'shojaei-seo-for-woo' ); ?></span>
						</label>
					</div>
					<div class="shojaei-form-grid" style="margin-top:12px;">
						<div class="shojaei-form-group">
							<label><?php esc_html_e( 'تعداد پیشنهاد جایگزین ناموجود', 'shojaei-seo-for-woo' ); ?></label>
							<input type="number" name="shojaei_seo_oos_related_limit" value="<?php echo esc_attr( (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_related_limit', 4 ) ); ?>" min="2" max="8" />
							<small><?php esc_html_e( 'روی صفحه محصول ناموجود — پیشنهاد جایگزین مبتنی بر شباهت OOS.', 'shojaei-seo-for-woo' ); ?></small>
						</div>
					</div>

					<?php
					$custom_dict   = class_exists( 'Shojaei_SEO_Slug' ) ? Shojaei_SEO_Slug::custom_word_map() : array();
					$dict_text     = class_exists( 'Shojaei_SEO_Slug' ) ? Shojaei_SEO_Slug::format_dictionary_text( $custom_dict ) : '';
					$builtin_count = class_exists( 'Shojaei_SEO_Slug' ) ? count( Shojaei_SEO_Slug::builtin_word_map() ) : 0;
					?>
					<div class="shojaei-form-group" style="margin-top:16px;">
						<label for="shojaei_seo_finglish_dictionary">
							<?php esc_html_e( 'دیکشنری فینگلیش فروشگاه (سفارشی)', 'shojaei-seo-for-woo' ); ?>
						</label>
						<p class="description" style="margin-top:4px;">
							<?php
							printf(
								/* translators: 1: custom count, 2: builtin count */
								esc_html__( 'هر خط: فارسی = latin — واژه‌های شما روی دیکشنری داخلی (%2$d واژه) اولویت دارند. الان %1$d واژهٔ سفارشی دارید. حداکثر ۵۰۰ خط.', 'shojaei-seo-for-woo' ),
								count( $custom_dict ),
								(int) $builtin_count
							);
							?>
						</p>
						<textarea
							name="shojaei_seo_finglish_dictionary"
							id="shojaei_seo_finglish_dictionary"
							rows="10"
							class="large-text code"
							dir="rtl"
							placeholder="<?php echo esc_attr( "بهارشاپ = baharshop\nکراپ‌تاپ = croptop\n# خط با # توضیح است" ); ?>"
						><?php echo esc_textarea( $dict_text ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'مثال: نایک = nike | تیشرت = tshirt | بهار = bahar — بعد از ذخیره، پیشنهادهای سلامت نامک و محصولات جدید از همین واژه‌ها استفاده می‌کنند.', 'shojaei-seo-for-woo' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<div class="shojaei-accordion-item" data-accordion="set-index">
			<button class="shojaei-accordion-header" type="button" aria-expanded="false">
				<span class="shojaei-accordion-icon shojaei-icon-purple"><span class="dashicons dashicons-cloud"></span></span>
				<span class="shojaei-accordion-title"><?php esc_html_e( '۳ · ایندکس و اعلان', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-meta"><?php esc_html_e( 'بازیابی', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body">
				<div class="shojaei-accordion-content">
					<div class="shojaei-settings-grid">
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_indexnow_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_enabled' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'IndexNow — اعلان سریع تغییرات', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_gsc_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_enabled', 'no' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'سرچ‌کنسول — Request Indexing', 'shojaei-seo-for-woo' ); ?></span>
						</label>
					</div>
					<p class="shojaei-settings-jump">
						<a href="#shojaei-gsc"><?php esc_html_e( 'جزئیات اتصال GSC و آپلود کلید ↓', 'shojaei-seo-for-woo' ); ?></a>
					</p>
				</div>
			</div>
		</div>

		<div class="shojaei-accordion-item" data-accordion="set-links">
			<button class="shojaei-accordion-header" type="button" aria-expanded="false">
				<span class="shojaei-accordion-icon shojaei-icon-green"><span class="dashicons dashicons-admin-links"></span></span>
				<span class="shojaei-accordion-title"><?php esc_html_e( '۴ · لینک‌ساز', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body">
				<div class="shojaei-accordion-content">
					<label class="shojaei-setting-item">
						<input type="checkbox" name="shojaei_seo_link_builder_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_builder_enabled' ), 'yes' ); ?> />
						<span><?php esc_html_e( 'لینک‌سازی داخلی / بازیابی', 'shojaei-seo-for-woo' ); ?></span>
					</label>
					<p class="shojaei-settings-jump">
						<a href="#shojaei-acc-link-rules"><?php esc_html_e( 'سقف لینک و whitelist ↓', 'shojaei-seo-for-woo' ); ?></a>
					</p>
				</div>
			</div>
		</div>

		<?php
		if ( class_exists( 'Damavand_Similar_Products_Settings' ) ) {
			Damavand_Similar_Products_Settings::render_section();
		}
		?>

		<div class="shojaei-accordion-item" data-accordion="set-support">
			<button class="shojaei-accordion-header" type="button" aria-expanded="false">
				<span class="shojaei-accordion-icon shojaei-icon-blue"><span class="dashicons dashicons-admin-generic"></span></span>
				<span class="shojaei-accordion-title"><?php esc_html_e( '۵ · لایه‌های پشتیبان', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body">
				<div class="shojaei-accordion-content">
					<p class="shojaei-module-note"><?php esc_html_e( 'جایگزین هسته نیستند؛ کمکی برای تبدیل یا مارک‌آپ.', 'shojaei-seo-for-woo' ); ?></p>
					<div class="shojaei-settings-grid">
						<label class="shojaei-setting-item">
							<input type="checkbox" name="shojaei_seo_schema_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_enabled' ), 'yes' ); ?> />
							<span><?php esc_html_e( 'اسکیمای JSON-LD مکمل (کنار Yoast / Rank Math)', 'shojaei-seo-for-woo' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</div>

		<div class="shojaei-accordion-item" data-accordion="set-content" id="shojaei-acc-content-server">
			<button class="shojaei-accordion-header" type="button" aria-expanded="false">
				<span class="shojaei-accordion-icon shojaei-icon-orange"><span class="dashicons dashicons-edit"></span></span>
				<span class="shojaei-accordion-title"><?php esc_html_e( '۶ · سرور تولید محتوا', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-meta"><?php esc_html_e( 'دماوند', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body">
				<div class="shojaei-accordion-content">
					<p class="shojaei-module-note"><?php esc_html_e( 'تولید محتوای سئو با Groq یا OpenRouter — کلید API خودتان.', 'shojaei-seo-for-woo' ); ?></p>
					<p class="shojaei-settings-jump">
						<a href="#shojaei-content-server"><?php esc_html_e( 'تنظیم Provider، کلید API، تست اتصال و llms.txt ↓', 'shojaei-seo-for-woo' ); ?></a>
					</p>
				</div>
			</div>
		</div>

	</div>

	<div class="shojaei-accordion shojaei-settings-accordion" style="margin-bottom:16px;">
	<div class="shojaei-accordion-item" data-accordion="oos-lifecycle" id="shojaei-acc-oos-lifecycle">
		<button class="shojaei-accordion-header" type="button" aria-expanded="false">
			<span class="shojaei-accordion-icon shojaei-icon-purple"><span class="dashicons dashicons-backup"></span></span>
			<span class="shojaei-accordion-title"><?php esc_html_e( 'جزئیات چرخه ناموجودی', 'shojaei-seo-for-woo' ); ?></span>
			<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<div class="shojaei-accordion-body">
		<div class="shojaei-accordion-content shojaei-oos-lifecycle-card">
		<p class="shojaei-desc"><?php esc_html_e( 'دیگر «فوراً ریدایرکت» نداریم. بر اساس روز ناموجودی، جایگزین و ارزش صفحه تصمیم گرفته می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
		<div class="shojaei-timeline-presets" role="group" aria-label="<?php esc_attr_e( 'پیشنهاد روز پیام', 'shojaei-seo-for-woo' ); ?>">
			<span class="shojaei-timeline-presets-label"><?php esc_html_e( 'شروع سریع (روز پیام):', 'shojaei-seo-for-woo' ); ?></span>
			<?php foreach ( array( 7, 10, 15, 20, 30 ) as $preset_day ) : ?>
				<button type="button" class="button button-small shojaei-timeline-preset" data-message-day="<?php echo esc_attr( (string) $preset_day ); ?>">
					<?php echo esc_html( (string) $preset_day ); ?>
				</button>
			<?php endforeach; ?>
			<button type="button" class="button button-small shojaei-timeline-apply-suggest">
				<?php esc_html_e( 'اعمال پیشنهاد بهینه', 'shojaei-seo-for-woo' ); ?>
			</button>
		</div>
		<p class="shojaei-timeline-suggest-hint" id="shojaei-timeline-suggest-hint" aria-live="polite"></p>
		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'روز تغییر پیام صفحه', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" class="shojaei-oos-message-day" name="shojaei_seo_oos_message_day" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_message_day', 15 ) ); ?>" min="1" max="365" />
				<small><?php esc_html_e( 'با تغییر این عدد، بقیه روزها پیشنهاد بهینه می‌گیرند (قابل ویرایش دستی). سه فاز پیام صفحه: تا این روز = موقت؛ تا آستانه دائم = کم‌احتمال؛ از روز کاندید = نهایی/ریدایرکت.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'آستانه ناموجود دائم (روز)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" class="shojaei-oos-temp-days" name="shojaei_seo_oos_temp_days" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_temp_days', 30 ) ); ?>" min="1" max="365" data-suggest-field="temp" />
				<small><?php esc_html_e( 'پیشنهادی ≈ ۲× روز پیام. زیر این عدد: موقت — از این عدد: دائم.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'روز کاندید / ریدایرکت خودکار', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" class="shojaei-oos-auto-day" name="shojaei_seo_oos_auto_day" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_day', 45 ) ); ?>" min="1" max="365" data-suggest-field="auto" />
				<small><?php esc_html_e( 'پیشنهادی ≈ ۳× روز پیام — ورود به کاندید و صف ریدایرکت.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'نوع ریدایرکت خودکار', 'shojaei-seo-for-woo' ); ?></label>
				<select name="shojaei_seo_oos_auto_redirect_type">
					<option value="302" <?php selected( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect_type', '302' ), '302' ); ?>><?php esc_html_e( '۳۰۲ (موقت — پیشنهادی)', 'shojaei-seo-for-woo' ); ?></option>
					<option value="301" <?php selected( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect_type', '302' ), '301' ); ?>><?php esc_html_e( '۳۰۱ (دائمی)', 'shojaei-seo-for-woo' ); ?></option>
				</select>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'حداقل امتیاز شباهت (%)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" name="shojaei_seo_oos_match_threshold" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_match_threshold', 70 ) ); ?>" min="1" max="100" />
				<small><?php esc_html_e( 'ترکیب عنوان + برچسب + ویژگی + قیمت. زیر این حد → ریدایرکت به دسته (جلوگیری از Soft 404).', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_oos_auto_redirect" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'فعال‌سازی ریدایرکت خودکار پس از روز کاندید', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_oos_notify_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'فرم «خبرم کن» با ایمیل روی صفحه ناموجود', 'shojaei-seo-for-woo' ); ?></span>
				</label>
				<small><?php esc_html_e( 'ساده: ثبت ایمیل روی همان صفحه؛ هنگام موجود شدن دوباره، ایمیل ارسال می‌شود. فاز نهایی (کاندید) فرم را نشان نمی‌دهد.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_oos_dry_run" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'حالت Dry-Run سراسری (اتوماسیون فقط پیشنهاد بدهد)', 'shojaei-seo-for-woo' ); ?></span>
				</label>
				<small><?php esc_html_e( 'برای تغییرات انبوه از عملیات → Dry-Run استفاده کنید: پیش‌نمایش → CSV → اجرای واقعی. Undo همیشه در دسترس است.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_oos_noindex_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_enabled', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'noindex برای محصولات ناموجود طولانی‌مدت', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'noindex از فاز', 'shojaei-seo-for-woo' ); ?></label>
				<select name="shojaei_seo_oos_noindex_from_phase">
					<option value="2" <?php selected( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_from_phase', 2 ), 2 ); ?>><?php esc_html_e( 'فاز ۲ (پس از تغییر پیام)', 'shojaei-seo-for-woo' ); ?></option>
					<option value="3" <?php selected( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_from_phase', 2 ), 3 ); ?>><?php esc_html_e( 'فاز ۳ (ناموجود دائم)', 'shojaei-seo-for-woo' ); ?></option>
				</select>
				<small><?php esc_html_e( 'صفحه follow می‌ماند؛ فقط از ایندکس خارج می‌شود.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
		</div>
		</div>
		</div>
	</div>

	<div class="shojaei-accordion-item" data-accordion="oos-messages" id="shojaei-acc-oos-messages">
		<button class="shojaei-accordion-header" type="button" aria-expanded="false">
			<span class="shojaei-accordion-icon shojaei-icon-green"><span class="dashicons dashicons-edit-large"></span></span>
			<span class="shojaei-accordion-title"><?php esc_html_e( 'پیام اختصاصی', 'shojaei-seo-for-woo' ); ?></span>
			<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<div class="shojaei-accordion-body">
		<div class="shojaei-accordion-content">
		<p class="shojaei-desc"><?php esc_html_e( 'متن هر سه فاز را به زبان فروشگاه خودتان بنویسید. خالی بگذارید تا همان پیام پیش‌فرض نمایش داده شود.', 'shojaei-seo-for-woo' ); ?></p>
		<?php
		$phase_fields = array(
			'temp'     => array(
				'label' => __( 'فاز موقت (تا روز تغییر پیام)', 'shojaei-seo-for-woo' ),
				'title' => __( 'فعلاً ناموجود — در حال تأمین مجدد', 'shojaei-seo-for-woo' ),
				'body'  => __( 'این کالا موقتاً موجود نیست. به‌زودی دوباره تأمین می‌شود؛ تا آن موقع می‌توانید گزینه‌های مشابه را ببینید.', 'shojaei-seo-for-woo' ),
				'cta'   => __( 'مشاهده محصولات مشابه', 'shojaei-seo-for-woo' ),
			),
			'unlikely' => array(
				'label' => __( 'فاز کم‌احتمال (تا آستانه دائم)', 'shojaei-seo-for-woo' ),
				'title' => __( 'احتمال موجود شدن کمتر است', 'shojaei-seo-for-woo' ),
				'body'  => __( 'مدت بیشتری از ناموجودی گذشته. پیشنهاد می‌کنیم همین حالا گزینه‌های نزدیک را بررسی کنید.', 'shojaei-seo-for-woo' ),
				'cta'   => __( 'پیشنهادهای جایگزین', 'shojaei-seo-for-woo' ),
			),
			'final'    => array(
				'label' => __( 'فاز نهایی / کاندید (از روز کاندید)', 'shojaei-seo-for-woo' ),
				'title' => __( 'این کالا فعلاً در دسترس نیست', 'shojaei-seo-for-woo' ),
				'body'  => __( 'انتظار موجود شدن دوباره نداریم. از پیشنهادهای زیر انتخاب کنید یا در صورت فعال بودن مسیر جایگزین هدایت می‌شوید.', 'shojaei-seo-for-woo' ),
				'cta'   => __( 'مشاهده جایگزین‌ها', 'shojaei-seo-for-woo' ),
			),
		);
		foreach ( $phase_fields as $suffix => $meta ) :
			$title_key = 'shojaei_seo_oos_msg_' . $suffix . '_title';
			$body_key  = 'shojaei_seo_oos_msg_' . $suffix . '_body';
			$cta_key   = 'shojaei_seo_oos_msg_' . $suffix . '_cta';
			?>
			<div class="shojaei-oos-msg-block" style="margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #e8e8e8;">
				<strong><?php echo esc_html( $meta['label'] ); ?></strong>
				<div class="shojaei-form-grid" style="margin-top:10px;">
					<div class="shojaei-form-group">
						<label><?php esc_html_e( 'عنوان', 'shojaei-seo-for-woo' ); ?></label>
						<input type="text" class="regular-text" name="<?php echo esc_attr( $title_key ); ?>" value="<?php echo esc_attr( (string) Shojaei_SEO_Helpers::get_option( $title_key, '' ) ); ?>" placeholder="<?php echo esc_attr( $meta['title'] ); ?>" />
					</div>
					<div class="shojaei-form-group">
						<label><?php esc_html_e( 'متن دکمه', 'shojaei-seo-for-woo' ); ?></label>
						<input type="text" class="regular-text" name="<?php echo esc_attr( $cta_key ); ?>" value="<?php echo esc_attr( (string) Shojaei_SEO_Helpers::get_option( $cta_key, '' ) ); ?>" placeholder="<?php echo esc_attr( $meta['cta'] ); ?>" />
					</div>
					<div class="shojaei-form-group" style="grid-column:1/-1;">
						<label><?php esc_html_e( 'متن پیام', 'shojaei-seo-for-woo' ); ?></label>
						<textarea name="<?php echo esc_attr( $body_key ); ?>" rows="3" class="large-text" placeholder="<?php echo esc_attr( $meta['body'] ); ?>"><?php echo esc_textarea( (string) Shojaei_SEO_Helpers::get_option( $body_key, '' ) ); ?></textarea>
					</div>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="shojaei-form-group">
			<label><?php esc_html_e( 'CSS اختصاصی جعبه ناموجودی', 'shojaei-seo-for-woo' ); ?></label>
			<textarea name="shojaei_seo_oos_custom_css" rows="8" class="large-text code" dir="ltr" placeholder=".shojaei-oos-notice {&#10;  border-radius: 12px;&#10;}&#10;.shojaei-oos-phase-temp {&#10;  background: #f0f7f4;&#10;}"><?php echo esc_textarea( (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_custom_css', '' ) ); ?></textarea>
			<small><?php esc_html_e( 'فقط CSS. کلاس‌های آماده: .shojaei-oos-notice ، .shojaei-oos-phase-temp ، .shojaei-oos-phase-unlikely ، .shojaei-oos-phase-final ، .shojaei-oos-similar-btn ، .shojaei-related-products', 'shojaei-seo-for-woo' ); ?></small>
		</div>
		</div>
		</div>
	</div>

	<div class="shojaei-accordion-item" data-accordion="page-value">
		<button class="shojaei-accordion-header" type="button" aria-expanded="false">
			<span class="shojaei-accordion-icon shojaei-icon-blue"><span class="dashicons dashicons-chart-area"></span></span>
			<span class="shojaei-accordion-title"><?php esc_html_e( 'ارزش صفحه (قفل صفحات مهم)', 'shojaei-seo-for-woo' ); ?></span>
			<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<div class="shojaei-accordion-body">
		<div class="shojaei-accordion-content">
		<p class="shojaei-desc"><?php esc_html_e( 'قبل از ریدایرکت یا ۴۱۰، امتیاز محلی صفحه چک می‌شود. صفحات پربازدید بدون تأیید شما جابه‌جا نمی‌شوند. نیازی به API گوگل نیست.', 'shojaei-seo-for-woo' ); ?></p>
		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_oos_page_value_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_page_value_enabled', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'فعال‌سازی قفل ارزش صفحه', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'آستانه امتیاز (۰–۱۰۰)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" name="shojaei_seo_oos_page_value_threshold" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_page_value_threshold', 60 ) ); ?>" min="1" max="100" />
				<small><?php esc_html_e( 'سیگنال‌ها: فروش، نظرات، Rank Math محلی، غنای محتوا، لینک داخلی.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
		</div>
		</div>
		</div>
	</div>

	<div class="shojaei-accordion-item" data-accordion="link-rules" id="shojaei-acc-link-rules">
		<button class="shojaei-accordion-header" type="button" aria-expanded="false">
			<span class="shojaei-accordion-icon shojaei-icon-green"><span class="dashicons dashicons-admin-links"></span></span>
			<span class="shojaei-accordion-title"><?php esc_html_e( 'سقف و قوانین لینک‌ساز', 'shojaei-seo-for-woo' ); ?></span>
			<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<div class="shojaei-accordion-body">
		<div class="shojaei-accordion-content">
		<p class="shojaei-desc"><?php esc_html_e( 'سقف لینک، فیلتر مقصد و لیست سیاه/سفید — محافظه‌کار تا اسپم نشود.', 'shojaei-seo-for-woo' ); ?></p>
		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'حداکثر لینک در هر صفحه (سقف سخت)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" name="shojaei_seo_link_max_per_page" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_max_per_page', 5 ) ); ?>" min="1" max="20" />
				<small><?php esc_html_e( 'پیش‌فرض ۵ — حتی اگر محتوا طولانی باشد از این عدد بیشتر نمی‌شود.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'حداکثر لینک در ۱۰۰۰ کلمه', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" name="shojaei_seo_link_max_per_1000" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_max_per_1000', 3 ) ); ?>" min="1" max="10" />
				<small><?php esc_html_e( 'پیش‌فرض ۳ — برای جلوگیری از اور‌اپتیمایز و اسپم.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'حداقل فاصله کلمات بین لینک‌ها', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" name="shojaei_seo_link_min_word_gap" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_min_word_gap', 200 ) ); ?>" min="50" />
				<small><?php esc_html_e( 'لینک در هدینگ، دکمه، منو و تگ style درج نمی‌شود.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_link_whitelist_only" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_whitelist_only', 'no' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'فقط whitelist (انحصاری)', 'shojaei-seo-for-woo' ); ?></span>
				</label>
				<small><?php esc_html_e( 'اگر فعال باشد، فقط کلمات/آدرس‌های whitelist اجازه درج دارند.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
		</div>
		<div class="shojaei-form-grid" style="margin-top:1rem;">
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'Blacklist کلمات (هر خط یکی)', 'shojaei-seo-for-woo' ); ?></label>
				<textarea name="shojaei_seo_link_keyword_blacklist" rows="4" placeholder="<?php esc_attr_e( 'مثال: اینجا\nکلیک کنید', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_keyword_blacklist', '' ) ); ?></textarea>
				<small><?php esc_html_e( 'علاوه بر لیست پیش‌فرض (اینجا، لینک، خرید...).', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'Whitelist کلمات (هر خط یکی)', 'shojaei-seo-for-woo' ); ?></label>
				<textarea name="shojaei_seo_link_keyword_whitelist" rows="4" placeholder="<?php esc_attr_e( 'اولویت بالاتر؛ در حالت انحصاری فقط این‌ها', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_keyword_whitelist', '' ) ); ?></textarea>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'Blacklist آدرس (هر خط یکی)', 'shojaei-seo-for-woo' ); ?></label>
				<textarea name="shojaei_seo_link_url_blacklist" rows="4" placeholder="<?php esc_attr_e( 'URL کامل یا بخشی از مسیر', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_url_blacklist', '' ) ); ?></textarea>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'Whitelist آدرس (هر خط یکی)', 'shojaei-seo-for-woo' ); ?></label>
				<textarea name="shojaei_seo_link_url_whitelist" rows="4" placeholder="<?php esc_attr_e( 'اولویت بالاتر؛ در حالت انحصاری فقط این‌ها', 'shojaei-seo-for-woo' ); ?>"><?php echo esc_textarea( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_url_whitelist', '' ) ); ?></textarea>
			</div>
			</div>
		</div>
		</div>
		</div>
	</div><!-- /.shojaei-settings-accordion early groups -->

	<?php
	$last_schema = class_exists( 'Shojaei_SEO_Schema_Detector' ) ? Shojaei_SEO_Schema_Detector::get_last_scan() : null;
	// سبک: بدون کوئری محصول روی هر لود تنظیمات.
	$sample_url = home_url( '/' );
	$integration = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::role_matrix() : array();
	$seo_detected = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_labels() : '';
	$schema_mode_label = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::schema_mode_label() : '';
	?>

	<div class="shojaei-card shojaei-settings-panel" id="shojaei-integration">
		<details class="shojaei-details" open>
			<summary class="shojaei-details-summary">
				<span class="dashicons dashicons-plugins-checked"></span>
				<?php esc_html_e( 'یکپارچگی با Yoast / Rank Math', 'shojaei-seo-for-woo' ); ?>
			</summary>
			<div class="shojaei-details-body">
		<p class="shojaei-desc"><?php esc_html_e( 'این افزونه وارد جنگ مستقیم با Yoast یا Rank Math نمی‌شود. تمرکز روی عملیات سئو (ریدایرکت، موجودی) است — نه جایگزینی کامل افزونه SEO.', 'shojaei-seo-for-woo' ); ?></p>

		<div class="shojaei-edu-tip" style="margin-bottom:1rem;">
			<span class="dashicons dashicons-plugins-checked"></span>
			<?php
			printf(
				/* translators: 1: detected plugins, 2: schema mode */
				esc_html__( 'تشخیص: %1$s — حالت اسکیما: %2$s', 'shojaei-seo-for-woo' ),
				esc_html( $seo_detected ),
				esc_html( $schema_mode_label )
			);
			?>
		</div>

		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_schema_respect_seo_plugins" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_respect_seo_plugins', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'احترام به افزونه SEO — واگذاری Product/Breadcrumb', 'shojaei-seo-for-woo' ); ?></span>
				</label>
				<small><?php esc_html_e( 'اگر Yoast یا Rank Math فعال باشد، این افزونه Product و Breadcrumb چاپ نمی‌کند (فقط FAQ اختیاری).', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=general-meta' ) ); ?>">
						<?php esc_html_e( 'باز کردن صفحه متای عمومی ←', 'shojaei-seo-for-woo' ); ?>
					</a>
				</p>
				<small><?php esc_html_e( 'ربات‌ها، جداکننده عنوان (-)، تصویر OpenGraph و هشدار تداخل با Rank Math / Yoast.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
		</div>

		<?php if ( ! empty( $integration ) ) : ?>
			<table class="shojaei-table" style="margin-top:1rem;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'بخش', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'مالک پیشنهادی', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'نقش', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'یادداشت', 'shojaei-seo-for-woo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $integration as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['area'] ); ?></strong></td>
							<td><?php echo esc_html( $row['owner'] ); ?></td>
							<td><?php echo esc_html( $row['role'] ); ?></td>
							<td><?php echo esc_html( $row['note'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
			</div>
		</details>
	</div>

	<div class="shojaei-card shojaei-settings-panel" id="shojaei-schema-conflict">
		<details class="shojaei-details">
			<summary class="shojaei-details-summary">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'تشخیص تداخل اسکیما', 'shojaei-seo-for-woo' ); ?>
			</summary>
			<div class="shojaei-details-body">
		<p class="shojaei-desc"><?php esc_html_e( 'خروجی صفحه برای تگ‌های موازی application/ld+json بررسی می‌شود. در صورت تکرار Product یا Breadcrumb به مدیر هشدار داده می‌شود.', 'shojaei-seo-for-woo' ); ?></p>

		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_schema_detect_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_detect_enabled', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'فعال‌سازی تشخیص خودکار (برای مدیران در فرانت‌اند)', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_disable_wc_schema" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_disable_wc_schema', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'غیرفعال‌سازی اسکیمای پیش‌فرض ووکامرس', 'shojaei-seo-for-woo' ); ?></span>
				</label>
				<small><?php esc_html_e( 'برای جلوگیری از Product موازی وقتی Yoast، Rank Math یا اسکیمای این افزونه فعال است.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_schema_product_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_product_enabled', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'خروجی Product این افزونه', 'shojaei-seo-for-woo' ); ?></span>
				</label>
				<small><?php esc_html_e( 'با «احترام به افزونه SEO» در صورت وجود Yoast/Rank Math عملاً خاموش می‌ماند.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_schema_breadcrumb_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_breadcrumb_enabled', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'خروجی Breadcrumb این افزونه', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_schema_faq_enabled" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_faq_enabled', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'خروجی FAQ این افزونه (مکمل)', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<?php
			$returns_page_id = absint( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_faq_returns_page_id', 0 ) );
			$returns_url     = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_faq_returns_url', '' );
			$detected_id     = class_exists( 'Damavand_FAQ_Box' ) ? Damavand_FAQ_Box::detect_returns_page_id() : 0;
			$detected_title  = $detected_id ? get_the_title( $detected_id ) : '';
			?>
			<div class="shojaei-form-group" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--dm-border,#E6EAF0);">
				<label for="shojaei_seo_faq_returns_url"><strong><?php esc_html_e( 'لینک شرایط تعویض و مرجوعی (FAQ)', 'shojaei-seo-for-woo' ); ?></strong></label>
				<p class="description"><?php esc_html_e( 'در سؤال FAQ «شرایط تعویض و مرجوعی» یک دکمه به این آدرس اضافه می‌شود. اگر خالی بماند، افزونه سعی می‌کند برگه مرتبط را پیدا کند.', 'shojaei-seo-for-woo' ); ?></p>
				<input type="url" class="regular-text" dir="ltr" name="shojaei_seo_faq_returns_url" id="shojaei_seo_faq_returns_url" value="<?php echo esc_attr( $returns_url ); ?>" placeholder="https://..." />
			</div>
			<div class="shojaei-form-group">
				<label for="shojaei_seo_faq_returns_page_id"><?php esc_html_e( 'یا انتخاب برگه', 'shojaei-seo-for-woo' ); ?></label>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'shojaei_seo_faq_returns_page_id',
						'id'                => 'shojaei_seo_faq_returns_page_id',
						'show_option_none'  => __( '— خودکار / بدون انتخاب —', 'shojaei-seo-for-woo' ),
						'option_none_value' => '0',
						'selected'          => $returns_page_id,
					)
				);
				?>
				<?php if ( $detected_id && ! $returns_page_id && '' === $returns_url ) : ?>
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: page title */
								__( 'برگه تشخیص‌داده‌شده: «%s»', 'shojaei-seo-for-woo' ),
								$detected_title
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="shojaei-preview-form" style="margin-top:16px;">
			<input type="url" id="shojaei-schema-scan-url" value="<?php echo esc_url( $sample_url ); ?>" class="regular-text" dir="ltr" style="min-width:280px;" />
			<button type="button" class="button button-primary" id="shojaei-schema-scan-btn"><?php esc_html_e( 'اسکن تداخل اسکیما', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<div id="shojaei-schema-scan-result" class="shojaei-test-result" style="display:none;margin-top:12px;"></div>

		<?php if ( $last_schema ) : ?>
			<div class="shojaei-edu-tip <?php echo ! empty( $last_schema['has_conflict'] ) ? 'shojaei-edu-warn' : ''; ?>" style="margin-top:16px;">
				<span class="dashicons <?php echo ! empty( $last_schema['has_conflict'] ) ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"></span>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: blocks, 2: url */
						__( 'آخرین اسکن: %1$d بلوک JSON-LD در %2$s', 'shojaei-seo-for-woo' ),
						(int) ( $last_schema['block_count'] ?? 0 ),
						$last_schema['url'] ?? ''
					)
				);
				?>
				<?php if ( ! empty( $last_schema['conflicts'] ) ) : ?>
					<ul style="margin:8px 0 0;">
						<?php foreach ( $last_schema['conflicts'] as $c ) : ?>
							<li><?php echo esc_html( $c['message'] ?? '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>
			</div>
		</details>
	</div>

	<div class="shojaei-card shojaei-settings-panel" id="shojaei-gsc">
		<details class="shojaei-details">
			<summary class="shojaei-details-summary">
				<span class="dashicons dashicons-google"></span>
				<?php esc_html_e( 'اتصال سرچ‌کنسول گوگل', 'shojaei-seo-for-woo' ); ?>
			</summary>
			<div class="shojaei-details-body">
		<p class="shojaei-desc"><?php esc_html_e( 'سه مرحله: کلید و ورود → آدرس خاصیت سایت → تست ایندکس. اگر فهرست خودکار خاصیت‌ها خطا داد، خاصیت دستی کافی است.', 'shojaei-seo-for-woo' ); ?></p>

		<?php
		$gsc_status = class_exists( 'Shojaei_SEO_GSC' ) ? Shojaei_SEO_GSC::get_status() : array();
		$gsc_ok     = ! empty( $gsc_status['connected'] );
		$gsc_site   = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_site_url', $gsc_status['site_url'] ?? '' );
		$host_hint  = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$gsc_layers = is_array( $gsc_status['layers'] ?? null ) ? $gsc_status['layers'] : array();
		?>
		<div class="shojaei-gsc-status <?php echo $gsc_ok ? 'is-connected' : 'is-disconnected'; ?>" id="shojaei-gsc-status-box">
			<span class="shojaei-gsc-light" aria-hidden="true"></span>
			<div>
				<strong id="shojaei-gsc-status-label">
					<?php echo $gsc_ok ? esc_html__( 'اتصال قابل استفاده', 'shojaei-seo-for-woo' ) : esc_html__( 'اتصال ناقص / قطع', 'shojaei-seo-for-woo' ); ?>
				</strong>
				<p id="shojaei-gsc-status-msg"><?php echo esc_html( $gsc_status['message'] ?? '' ); ?></p>
				<?php if ( ! empty( $gsc_status['client_email'] ) ) : ?>
					<small dir="ltr"><?php echo esc_html( $gsc_status['client_email'] ); ?></small>
				<?php endif; ?>
				<?php if ( ! empty( $gsc_status['site_url'] ) ) : ?>
					<br /><small dir="ltr"><?php echo esc_html( $gsc_status['site_url'] ); ?></small>
				<?php endif; ?>
			</div>
		</div>

		<ul class="shojaei-gsc-layers" id="shojaei-gsc-layers">
			<?php
			$layer_order = array( 'json_key', 'auth', 'property', 'sites_list', 'indexing' );
			foreach ( $layer_order as $lk ) :
				$layer = $gsc_layers[ $lk ] ?? null;
				if ( ! $layer ) {
					continue;
				}
				$state = (string) ( $layer['state'] ?? '' );
				if ( '' === $state ) {
					$ok = $layer['ok'] ?? null;
					$state = null === $ok ? 'pending' : ( $ok ? 'success' : 'fail' );
				}
				$cls  = 'is-' . ( 'success' === $state ? 'ok' : ( 'warning' === $state ? 'warn' : ( 'pending' === $state ? 'pending' : 'fail' ) ) );
				$mark = 'success' === $state ? '✓' : ( 'warning' === $state ? '!' : ( 'pending' === $state ? '○' : '✗' ) );
				$label_fa = class_exists( 'Shojaei_SEO_GSC_Error_Mapper' )
					? Shojaei_SEO_GSC_Error_Mapper::layer_label_fa( $lk )
					: (string) ( $layer['label'] ?? $lk );
				?>
				<li class="<?php echo esc_attr( $cls ); ?>" dir="rtl">
					<strong><?php echo esc_html( $mark . ' ' . $label_fa ); ?></strong>
					<span><?php echo esc_html( (string) ( $layer['detail'] ?? '' ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="shojaei-form-grid" style="margin-top:16px;">
			<div class="shojaei-form-group">
				<label class="shojaei-setting-item">
					<input type="checkbox" name="shojaei_seo_gsc_auto_index" value="yes" <?php checked( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_auto_index', 'yes' ), 'yes' ); ?> />
					<span><?php esc_html_e( 'اتوماسیون: Request Indexing هنگام ناموجودی / ریدایرکت', 'shojaei-seo-for-woo' ); ?></span>
				</label>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'فایل کلید JSON (Service Account)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="file" id="shojaei-gsc-key-file" accept=".json,application/json" />
				<small><?php esc_html_e( 'ایمیل داخل JSON را در Search Console → Users به‌عنوان Owner اضافه کنید.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'ترجیح نرمال‌سازی خاصیت', 'shojaei-seo-for-woo' ); ?></label>
				<select name="shojaei_seo_gsc_property_prefer" id="shojaei-gsc-property-prefer">
					<option value="domain" <?php selected( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_property_prefer', 'domain' ), 'domain' ); ?>><?php esc_html_e( 'Domain (sc-domain:example.com) — پیشنهادی', 'shojaei-seo-for-woo' ); ?></option>
					<option value="url_prefix" <?php selected( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_property_prefer', 'domain' ), 'url_prefix' ); ?>><?php esc_html_e( 'URL-prefix (https://example.com/)', 'shojaei-seo-for-woo' ); ?></option>
				</select>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'خاصیت Search Console (مرجع)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="text" name="shojaei_seo_gsc_site_url" id="shojaei-gsc-site-url" value="<?php echo esc_attr( $gsc_site ); ?>" dir="ltr" placeholder="<?php echo esc_attr( 'sc-domain:' . $host_hint ); ?>" />
				<small>
					<?php
					printf(
						/* translators: %s: example */
						esc_html__( 'خالی بگذارید تا از home_url ساخته شود، یا دقیق بنویسید: %s', 'shojaei-seo-for-woo' ),
						esc_html( 'sc-domain:' . $host_hint )
					);
					?>
				</small>
			</div>
		</div>

		<p class="shojaei-preview-form" style="margin-top:12px;">
			<button type="button" class="button button-primary" id="shojaei-gsc-upload-btn"><?php esc_html_e( 'آپلود JSON', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button" id="shojaei-gsc-verify-btn"><?php esc_html_e( 'بررسی لایه‌ای (A+B)', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button" id="shojaei-gsc-test-btn"><?php esc_html_e( 'تست لایه C — Request Indexing', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-link-delete" id="shojaei-gsc-disconnect-btn"><?php esc_html_e( 'قطع اتصال', 'shojaei-seo-for-woo' ); ?></button>
		</p>
		<div id="shojaei-gsc-result" class="shojaei-test-result" style="display:none;margin-top:12px;"></div>

		<div class="shojaei-edu-tip" style="margin-top:16px;">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'اگر تست ایندکس قرمز شد ولی ورود سبز بود: معمولاً سرور هاست به گوگل راه ندارد (شکن لپ‌تاپ کافی نیست). IndexNow را روشن نگه دارید. آموزش کامل در راهنما → آموزش.', 'shojaei-seo-for-woo' ); ?>
		</div>
			</div>
		</details>
	</div>

	<div class="shojaei-card shojaei-settings-panel" id="shojaei-performance">
		<details class="shojaei-details" id="shojaei-performance-details">
			<summary class="shojaei-details-summary">
				<span class="dashicons dashicons-performance"></span>
				<?php esc_html_e( 'صف Job و عملکرد', 'shojaei-seo-for-woo' ); ?>
			</summary>
			<div class="shojaei-details-body">
		<p class="shojaei-desc"><?php esc_html_e( 'عملیات سنگین به‌صورت Job در جدول اختصاصی ذخیره می‌شوند و در batchهای کوچک اجرا می‌گردند. رانر: Action Scheduler (در صورت وجود) + Ajax/REST هنگام حضور ادمین + cron داخلی به‌عنوان fallback — نه اتکای کامل به WP-Cron.', 'shojaei-seo-for-woo' ); ?></p>

		<?php
		$runner       = class_exists( 'Shojaei_SEO_Jobs' ) ? Shojaei_SEO_Jobs::runner_label() : 'internal_cron_ajax';
		$active       = class_exists( 'Shojaei_SEO_Jobs' ) ? Shojaei_SEO_Jobs::count_active() : 0;
		$failed_count = class_exists( 'Shojaei_SEO_Jobs' ) ? Shojaei_SEO_Jobs::count_failed_unacked() : 0;
		$failed_jobs  = class_exists( 'Shojaei_SEO_Jobs' ) ? Shojaei_SEO_Jobs::list_failed( 8 ) : array();
		?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: runner, 2: active count */
				esc_html__( 'رانر فعلی: %1$s — جاب فعال: %2$d', 'shojaei-seo-for-woo' ),
				esc_html( 'action_scheduler' === $runner ? 'Action Scheduler + Jobs DB' : 'Jobs DB + Ajax/Cron' ),
				(int) $active
			);
			?>
		</p>

		<?php if ( $failed_count > 0 ) : ?>
			<div class="shojaei-edu-tip shojaei-edu-warn" id="shojaei-jobs-failed-banner" style="margin:12px 0;">
				<span class="dashicons dashicons-warning"></span>
				<?php
				printf(
					/* translators: %d: failed jobs */
					esc_html__( '%d جاب ناموفق در هفتهٔ اخیر — اگر صف فعال خالی است، معمولاً خطای قدیمی است؛ با دکمه زیر هشدار داشبورد را پاک کنید.', 'shojaei-seo-for-woo' ),
					(int) $failed_count
				);
				?>
			</div>
		<?php endif; ?>

		<p class="shojaei-jobs-actions" style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0;">
			<button type="button" class="button button-primary" id="shojaei-jobs-run-tick">
				<?php esc_html_e( 'اجرای یک تیک صف الان', 'shojaei-seo-for-woo' ); ?>
			</button>
			<button type="button" class="button" id="shojaei-jobs-cancel-stale">
				<?php esc_html_e( 'لغو جاب‌های گیرکرده', 'shojaei-seo-for-woo' ); ?>
			</button>
			<button type="button" class="button" id="shojaei-jobs-ack-errors" <?php disabled( $failed_count < 1 ); ?>>
				<?php esc_html_e( 'پاک‌سازی هشدار جاب‌های ناموفق', 'shojaei-seo-for-woo' ); ?>
			</button>
		</p>
		<p class="description" id="shojaei-jobs-actions-status" style="margin-top:0;"></p>

		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label for="shojaei_seo_batch_size"><?php esc_html_e( 'اندازه دسته (محصول در هر اجرا)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" id="shojaei_seo_batch_size" name="shojaei_seo_batch_size" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_batch_size', 50 ) ); ?>" min="10" max="200" />
				<small><?php esc_html_e( 'پیش‌فرض ۵۰ — Bulk Redirect، روزانه ناموجودی، لینک‌سازی، اسکیما.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label for="shojaei_seo_job_max_attempts"><?php esc_html_e( 'حداکثر retry در خطا', 'shojaei-seo-for-woo' ); ?></label>
				<input type="number" id="shojaei_seo_job_max_attempts" name="shojaei_seo_job_max_attempts" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_job_max_attempts', 3 ) ); ?>" min="1" max="10" />
				<small><?php esc_html_e( 'پس از این تعداد شکست، وضعیت جاب = failed و قابل‌ردیابی می‌ماند.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
		</div>

		<?php
		$batch_jobs = class_exists( 'Shojaei_SEO_Jobs' ) ? Shojaei_SEO_Jobs::list_jobs( 8 ) : array();
		$status_labels = array(
			'pending'   => __( 'در صف', 'shojaei-seo-for-woo' ),
			'queued'    => __( 'در صف', 'shojaei-seo-for-woo' ),
			'running'   => __( 'در حال اجرا', 'shojaei-seo-for-woo' ),
			'done'      => __( 'تمام', 'shojaei-seo-for-woo' ),
			'failed'    => __( 'ناموفق', 'shojaei-seo-for-woo' ),
			'cancelled' => __( 'لغو', 'shojaei-seo-for-woo' ),
		);
		?>
		<?php if ( ! empty( $failed_jobs ) ) : ?>
			<h4 style="margin:16px 0 8px;"><?php esc_html_e( 'جاب‌های ناموفق اخیر', 'shojaei-seo-for-woo' ); ?></h4>
			<table class="widefat striped" id="shojaei-jobs-failed-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'پیام / خطا', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'زمان', 'shojaei-seo-for-woo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $failed_jobs as $fj ) : ?>
						<tr>
							<td><code><?php echo esc_html( (string) ( $fj['type'] ?? '' ) ); ?></code></td>
							<td><?php echo esc_html( (string) ( $fj['last_error'] ?? $fj['message'] ?? '' ) ); ?></td>
							<td dir="ltr"><?php echo esc_html( (string) ( $fj['updated_at'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ( ! empty( $batch_jobs ) ) : ?>
			<h4 style="margin:16px 0 8px;"><?php esc_html_e( 'آخرین جاب‌ها', 'shojaei-seo-for-woo' ); ?></h4>
			<table class="widefat striped" style="margin-top:8px;" id="shojaei-jobs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'پیشرفت', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'تلاش', 'shojaei-seo-for-woo' ); ?></th>
						<th><?php esc_html_e( 'پیام', 'shojaei-seo-for-woo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $batch_jobs as $bj ) : ?>
						<?php
						$st = (string) ( $bj['status'] ?? '' );
						?>
						<tr>
							<td><code><?php echo esc_html( (string) ( $bj['type'] ?? '' ) ); ?></code></td>
							<td><?php echo esc_html( $status_labels[ $st ] ?? $st ); ?></td>
							<td>
								<?php
								echo esc_html(
									sprintf(
										'%d / %d',
										(int) ( $bj['processed'] ?? 0 ),
										(int) ( $bj['total'] ?? 0 )
									)
								);
								?>
							</td>
							<td>
								<?php
								echo esc_html(
									sprintf(
										'%d / %d',
										(int) ( $bj['attempts'] ?? 0 ),
										(int) ( $bj['max_attempts'] ?? 3 )
									)
								);
								?>
							</td>
							<td><?php echo esc_html( (string) ( $bj['message'] ?? $bj['last_error'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="description" style="margin-top:12px;"><?php esc_html_e( 'جاب فعالی در صف نیست.', 'shojaei-seo-for-woo' ); ?></p>
		<?php endif; ?>
		<div id="shojaei-batch-progress" class="shojaei-test-result" style="display:none;margin-top:12px;"></div>
			</div>
		</details>
	</div>

	<div class="shojaei-card shojaei-settings-panel" id="shojaei-content-server">
		<details class="shojaei-details" id="shojaei-content-server-details">
			<summary class="shojaei-details-summary">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'سرور تولید محتوا', 'shojaei-seo-for-woo' ); ?>
			</summary>
			<div class="shojaei-details-body">
				<?php require DAMAVAND_SEO_DIR . 'admin/views/partials/settings-content-server.php'; ?>
			</div>
		</details>
	</div>

	<div class="shojaei-card shojaei-settings-panel">
		<details class="shojaei-details">
			<summary class="shojaei-details-summary">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'پیشرفته', 'shojaei-seo-for-woo' ); ?>
			</summary>
			<div class="shojaei-details-body">
		<div class="shojaei-form-grid">
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'واحد پولی (اسکیما / نمایش)', 'shojaei-seo-for-woo' ); ?></label>
				<?php $cur = Shojaei_SEO_Helpers::get_currency_code(); ?>
				<select name="shojaei_seo_currency">
					<option value="IRT" <?php selected( $cur, 'IRT' ); ?>><?php esc_html_e( 'تومان (IRT)', 'shojaei-seo-for-woo' ); ?></option>
					<option value="IRR" <?php selected( $cur, 'IRR' ); ?>><?php esc_html_e( 'ریال (IRR)', 'shojaei-seo-for-woo' ); ?></option>
					<option value="USD" <?php selected( $cur, 'USD' ); ?>>USD</option>
					<option value="EUR" <?php selected( $cur, 'EUR' ); ?>>EUR</option>
					<option value="AED" <?php selected( $cur, 'AED' ); ?>><?php esc_html_e( 'درهم (AED)', 'shojaei-seo-for-woo' ); ?></option>
				</select>
				<small><?php esc_html_e( 'برچسب قیمت داخل افزونه. اگر تومان (IRT) باشد، در JSON-LD فقط برای گوگل به ریال (IRR) و ×۱۰ تبدیل می‌شود — قیمت ووکامرس و دیتابیس عوض نمی‌شود.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'برچسب واحد (اختیاری)', 'shojaei-seo-for-woo' ); ?></label>
				<input type="text" name="shojaei_seo_currency_label" value="<?php echo esc_attr( (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_currency_label', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( Shojaei_SEO_Helpers::get_currency_label() ); ?>" />
				<small><?php esc_html_e( 'خالی = برچسب پیش‌فرض همان واحد بالا.', 'shojaei-seo-for-woo' ); ?></small>
			</div>
			<div class="shojaei-form-group">
				<label><?php esc_html_e( 'کلید API — IndexNow', 'shojaei-seo-for-woo' ); ?></label>
				<input type="text" name="shojaei_seo_indexnow_key" value="<?php echo esc_attr( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_key', '' ) ); ?>" class="regular-text" dir="ltr" />
			</div>
		</div>

		<p class="shojaei-settings-jump" style="margin-top:12px;">
			<a href="#shojaei-content-server"><?php esc_html_e( 'تولید محتوا (Groq/OpenRouter) ↓', 'shojaei-seo-for-woo' ); ?></a>
		</p>

		<div id="shojaei-uninstall-policy" class="shojaei-uninstall-policy" style="margin-top:18px;padding:14px;border:1px solid #dcdcde;border-radius:8px;background:#fff;">
			<h4 style="margin:0 0 8px;"><?php esc_html_e( 'غیرفعال‌سازی و حذف افزونه', 'shojaei-seo-for-woo' ); ?></h4>
			<p class="description" style="margin-top:0;">
				<?php esc_html_e( 'نامک محصولات در وردپرس می‌ماند. ریدایرکت‌های ۳۰۱/۴۱۰ فقط وقتی افزونه فعال است اجرا می‌شوند.', 'shojaei-seo-for-woo' ); ?>
			</p>
			<?php $wipe = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_remove_data_on_uninstall', 'no' ); ?>
			<label class="shojaei-setting-item" style="display:block;margin:10px 0;padding:10px;border:1px solid #c3e6cb;background:#f0fff4;">
				<input type="radio" name="shojaei_seo_remove_data_on_uninstall" value="no" <?php checked( $wipe, 'no' ); ?> />
				<strong><?php esc_html_e( 'امن (پیشنهادی): نگه داشتن داده بعد از حذف', 'shojaei-seo-for-woo' ); ?></strong>
				<small class="description" style="display:block;margin:6px 0 0 24px;">
					<?php esc_html_e( 'جداول ریدایرکت و تنظیمات در دیتابیس می‌مانند. با نصب دوباره قابل استفاده‌اند. بدون افزونه فعال، ریدایرکت اجرا نمی‌شود.', 'shojaei-seo-for-woo' ); ?>
				</small>
			</label>
			<label class="shojaei-setting-item" style="display:block;margin:10px 0;padding:10px;border:1px solid #f0b7b7;background:#fff8f8;">
				<input type="radio" name="shojaei_seo_remove_data_on_uninstall" value="yes" id="shojaei-uninstall-wipe" <?php checked( $wipe, 'yes' ); ?> />
				<strong><?php esc_html_e( 'پاک‌سازی کامل با حذف افزونه', 'shojaei-seo-for-woo' ); ?></strong>
				<small class="description" style="display:block;margin:6px 0 0 24px;color:#b32d2e;">
					<?php esc_html_e( 'خطرناک: با Delete از فهرست افزونه‌ها، همه ریدایرکت‌های نامک/OOS، لاگ‌ها و تنظیمات برای همیشه پاک می‌شوند. لینک‌های قدیم ممکن است ۴۰۴ شوند.', 'shojaei-seo-for-woo' ); ?>
				</small>
			</label>
		</div>
			</div>
		</details>
	</div>

	<div class="shojaei-settings-savebar">
		<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'ذخیره تنظیمات', 'shojaei-seo-for-woo' ); ?></button>
		<span class="shojaei-settings-savebar-hint"><?php esc_html_e( 'تغییرات فقط با ذخیره اعمال می‌شوند.', 'shojaei-seo-for-woo' ); ?></span>
	</div>
</form>
