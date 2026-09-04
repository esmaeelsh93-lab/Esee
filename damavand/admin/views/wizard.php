<?php
/**
 * ویزارد راه‌اندازی — مسیر ساده برای فروشگاه ایرانی / جایگزینی Rank Math.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$step = absint( $_GET['wizard_step'] ?? 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$step = max( 1, min( 4, $step ) );

$dry_run      = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' );
$event_driven = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_event_driven', 'yes' );
$batch_size   = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_batch_size', 50 );
$timeline     = Shojaei_SEO_Helpers::get_oos_timeline();
$message_day  = (int) ( $timeline['message_day'] ?? 15 );
$temp_days    = (int) ( $timeline['temp_days'] ?? 30 );
$auto_day     = (int) ( $timeline['auto_day'] ?? 45 );
$auto_type    = (string) ( $timeline['auto_type'] ?? '302' );
$setup_done   = class_exists( 'Shojaei_SEO_Status' ) && Shojaei_SEO_Status::is_setup_done();
$meta_on      = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' );
$rm_active    = Shojaei_SEO_Helpers::is_rank_math_active();
$ready        = class_exists( 'Damavand_SEO_Migrator' ) ? Damavand_SEO_Migrator::removal_readiness() : array( 'items' => array(), 'ready' => false, 'rm_active' => $rm_active );
$sitemap_url  = home_url( '/shojaei-sitemap.xml' );
if ( class_exists( 'SEO_Core_Loader' ) ) {
	$loader = SEO_Core_Loader::instance();
	$sm     = $loader ? $loader->get_module( 'sitemap' ) : null;
	if ( $sm && method_exists( $sm, 'public_url' ) ) {
		$sitemap_url = (string) $sm->public_url( 'index' );
	}
}

$base   = admin_url( 'admin.php?page=shojaei-seo&tab=wizard' );
$labels = array(
	1 => __( 'شروع', 'shojaei-seo-for-woo' ),
	2 => __( 'مهاجرت', 'shojaei-seo-for-woo' ),
	3 => __( 'فعال‌سازی', 'shojaei-seo-for-woo' ),
	4 => __( 'آمادگی', 'shojaei-seo-for-woo' ),
);
?>

<div class="shojaei-wizard" dir="rtl">
	<?php if ( ! $setup_done ) : ?>
		<div class="shojaei-edu-tip" style="margin-bottom:12px;">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'راه‌اندازی هنوز تمام نشده — این ویزارد کوتاه‌ترین مسیر برای فروشگاه فارسی است.', 'shojaei-seo-for-woo' ); ?>
		</div>
	<?php endif; ?>

	<div class="shojaei-ops-hero shojaei-wizard-hero">
		<h2><?php esc_html_e( 'راه‌اندازی Damavand — سئوی ساده برای فروشگاه ایرانی', 'shojaei-seo-for-woo' ); ?></h2>
		<p><?php esc_html_e( 'نام فارسی کالا، نامک فینگلیش، امتیاز فارسی، و مدیریت ناموجودی — بدون پیچیدگی Rank Math.', 'shojaei-seo-for-woo' ); ?></p>
		<div class="shojaei-wizard-steps" role="list">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<a class="shojaei-wizard-dot <?php echo $i === $step ? 'is-active' : ( $i < $step ? 'is-done' : '' ); ?>"
					href="<?php echo esc_url( add_query_arg( 'wizard_step', $i, $base ) ); ?>"
					title="<?php echo esc_attr( $labels[ $i ] ); ?>">
					<?php echo esc_html( (string) $i ); ?>
				</a>
			<?php endfor; ?>
		</div>
		<p class="description" style="margin-top:8px;"><?php echo esc_html( $labels[ $step ] ); ?></p>
	</div>

	<?php if ( 1 === $step ) : ?>
		<div class="shojaei-card">
			<h3><?php esc_html_e( '۱) Damavand برای چه کسی است؟', 'shojaei-seo-for-woo' ); ?></h3>
			<ul class="shojaei-wizard-list">
				<li><?php esc_html_e( 'فروشگاه ووکامرس با عنوان فارسی محصول — نامک پیشنهادی فینگلیش لاتین است.', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( 'حروف اضافه مثل از، با، و، در، برای از نامک حذف می‌شوند.', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( 'امتیاز سئو بر اساس نیاز کاربر ایرانی (عنوان/توضیح/کلمه کلیدی فارسی).', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( 'چرخه ناموجودی، ریدایرکت و نقشه سایت — کنار یا به‌جای Rank Math.', 'shojaei-seo-for-woo' ); ?></li>
			</ul>
			<p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( add_query_arg( 'wizard_step', 2, $base ) ); ?>">
					<?php echo $rm_active ? esc_html__( 'Rank Math دارم — برو مهاجرت', 'shojaei-seo-for-woo' ) : esc_html__( 'ادامه', 'shojaei-seo-for-woo' ); ?>
				</a>
				<?php if ( ! $rm_active ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'wizard_step', 3, $base ) ); ?>">
						<?php esc_html_e( 'Rank Math ندارم — برو فعال‌سازی', 'shojaei-seo-for-woo' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>

	<?php elseif ( 2 === $step ) : ?>
		<div class="shojaei-card">
			<h3><?php esc_html_e( '۲) مهاجرت از Rank Math / Yoast', 'shojaei-seo-for-woo' ); ?></h3>
			<p class="shojaei-desc">
				<?php
				echo $rm_active
					? esc_html__( 'Rank Math فعال است. متا و ریدایرکت‌ها را به Damavand منتقل کنید؛ هنوز Rank Math را خاموش نکنید.', 'shojaei-seo-for-woo' )
					: esc_html__( 'رقیب فعالی دیده نشد — می‌توانید این مرحله را رد کنید یا اگر قبلاً داده داشتید مهاجرت را اجرا کنید.', 'shojaei-seo-for-woo' );
				?>
			</p>

			<?php if ( class_exists( 'Damavand_SEO_Migrator' ) ) : ?>
				<p>
					<label>
						<input type="checkbox" id="damavand-migrate-overwrite" />
						<?php esc_html_e( 'بازنویسی متای Damavand اگر از قبل پر باشد', 'shojaei-seo-for-woo' ); ?>
					</label>
				</p>
				<p class="dm-actions">
					<button type="button" class="button button-primary" id="damavand-migrate-start"><?php esc_html_e( 'شروع مهاجرت کامل', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" id="damavand-migrate-meta-only"><?php esc_html_e( 'فقط متا', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" id="damavand-migrate-redirects-only"><?php esc_html_e( 'فقط ریدایرکت', 'shojaei-seo-for-woo' ); ?></button>
				</p>
				<div class="damavand-migrate-progress" id="damavand-migrate-progress" hidden>
					<div class="damavand-migrate-progress__bar"><span id="damavand-migrate-bar"></span></div>
					<p id="damavand-migrate-status" class="description" aria-live="polite"></p>
				</div>
				<div id="damavand-migrate-result" class="damavand-migrate-glass" hidden style="margin-top:12px;"></div>
				<ul id="damavand-ready-list" class="shojaei-wizard-list" style="margin-top:14px;"></ul>
			<?php else : ?>
				<p class="shojaei-tone-error"><?php esc_html_e( 'ماژول مهاجرت در دسترس نیست.', 'shojaei-seo-for-woo' ); ?></p>
			<?php endif; ?>

			<p style="margin-top:16px;">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'wizard_step', 1, $base ) ); ?>"><?php esc_html_e( 'قبلی', 'shojaei-seo-for-woo' ); ?></a>
				<a class="button button-primary button-hero" href="<?php echo esc_url( add_query_arg( 'wizard_step', 3, $base ) ); ?>">
					<?php esc_html_e( 'ادامه به فعال‌سازی Damavand', 'shojaei-seo-for-woo' ); ?>
				</a>
			</p>
		</div>

	<?php elseif ( 3 === $step ) : ?>
		<div class="shojaei-card">
			<h3><?php esc_html_e( '۳) روشن کردن خروجی Damavand', 'shojaei-seo-for-woo' ); ?></h3>
			<p class="shojaei-desc"><?php esc_html_e( 'عنوان، توضیح، Open Graph و نقشه سایت باید از Damavand بیایند تا بتوانید بعداً Rank Math را خاموش کنید.', 'shojaei-seo-for-woo' ); ?></p>

			<div class="notice <?php echo $meta_on ? 'notice-success' : 'notice-warning'; ?> inline">
				<p>
					<?php
					echo $meta_on
						? esc_html__( 'خروجی متای Damavand روشن است.', 'shojaei-seo-for-woo' )
						: esc_html__( 'خروجی متا هنوز خاموش است — دکمه زیر را بزنید.', 'shojaei-seo-for-woo' );
					?>
				</p>
			</div>

			<p>
				<button type="button" class="button button-primary button-hero" id="damavand-wizard-enable-emit">
					<?php esc_html_e( 'فعال‌سازی متا + اسکیما + نقشه سایت', 'shojaei-seo-for-woo' ); ?>
				</button>
				<span id="damavand-wizard-enable-status" class="description" aria-live="polite" style="margin-inline-start:8px;"></span>
			</p>

			<ul class="shojaei-wizard-list">
				<li><?php esc_html_e( 'نامک: فینگلیش از عنوان فارسی + حذف از/با/در/برای/و …', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( 'امتیاز سئو: متاباکس فارسی روی محصول.', 'shojaei-seo-for-woo' ); ?></li>
				<li><?php esc_html_e( 'ناموجودی: پیام سه‌فاز + پیشنهاد جایگزین (در تنظیمات چرخه قابل ویرایش).', 'shojaei-seo-for-woo' ); ?></li>
			</ul>

			<details style="margin-top:16px;">
				<summary><strong><?php esc_html_e( 'پیش‌فرض‌های امن ناموجودی (اختیاری)', 'shojaei-seo-for-woo' ); ?></strong></summary>
				<form method="post" action="" class="shojaei-wizard-defaults-form" style="margin-top:12px;">
					<?php wp_nonce_field( 'shojaei_seo_wizard_defaults', 'shojaei_seo_wizard_defaults_nonce' ); ?>
					<input type="hidden" name="shojaei_seo_save_wizard_defaults" value="1" />
					<div class="shojaei-form-grid">
						<div class="shojaei-form-group">
							<label><?php esc_html_e( 'روز پیام', 'shojaei-seo-for-woo' ); ?></label>
							<input type="number" class="shojaei-oos-message-day" name="shojaei_seo_oos_message_day" min="1" max="365" value="<?php echo esc_attr( (string) $message_day ); ?>" />
						</div>
						<div class="shojaei-form-group">
							<label><?php esc_html_e( 'آستانه دائم', 'shojaei-seo-for-woo' ); ?></label>
							<input type="number" class="shojaei-oos-temp-days" name="shojaei_seo_oos_temp_days" min="1" max="365" value="<?php echo esc_attr( (string) $temp_days ); ?>" data-suggest-field="temp" />
						</div>
						<div class="shojaei-form-group">
							<label><?php esc_html_e( 'روز کاندید', 'shojaei-seo-for-woo' ); ?></label>
							<input type="number" class="shojaei-oos-auto-day" name="shojaei_seo_oos_auto_day" min="1" max="365" value="<?php echo esc_attr( (string) $auto_day ); ?>" data-suggest-field="auto" />
						</div>
						<div class="shojaei-form-group">
							<label><?php esc_html_e( 'نوع ریدایرکت', 'shojaei-seo-for-woo' ); ?></label>
							<select name="shojaei_seo_oos_auto_redirect_type">
								<option value="302" <?php selected( $auto_type, '302' ); ?>>۳۰۲</option>
								<option value="301" <?php selected( $auto_type, '301' ); ?>>۳۰۱</option>
							</select>
						</div>
						<div class="shojaei-form-group">
							<label><?php esc_html_e( 'batch', 'shojaei-seo-for-woo' ); ?></label>
							<input type="number" name="shojaei_seo_batch_size" min="10" max="200" value="<?php echo esc_attr( (string) $batch_size ); ?>" />
						</div>
						<div class="shojaei-form-group">
							<label class="shojaei-setting-item">
								<input type="checkbox" name="shojaei_seo_oos_dry_run" value="yes" <?php checked( $dry_run ); ?> />
								<span><?php esc_html_e( 'Dry-Run', 'shojaei-seo-for-woo' ); ?></span>
							</label>
						</div>
						<div class="shojaei-form-group">
							<label class="shojaei-setting-item">
								<input type="checkbox" name="shojaei_seo_event_driven" value="yes" <?php checked( $event_driven ); ?> />
								<span><?php esc_html_e( 'Event-driven', 'shojaei-seo-for-woo' ); ?></span>
							</label>
						</div>
					</div>
					<p>
						<button type="submit" name="shojaei_seo_wizard_defaults_action" value="save_continue" class="button button-primary">
							<?php esc_html_e( 'ذخیره پیش‌فرض‌ها و ادامه', 'shojaei-seo-for-woo' ); ?>
						</button>
						<button type="submit" name="shojaei_seo_wizard_defaults_action" value="skip" class="button">
							<?php esc_html_e( 'رد کردن', 'shojaei-seo-for-woo' ); ?>
						</button>
					</p>
				</form>
			</details>

			<p style="margin-top:16px;">
				<a class="button" href="<?php echo esc_url( add_query_arg( 'wizard_step', 2, $base ) ); ?>"><?php esc_html_e( 'قبلی', 'shojaei-seo-for-woo' ); ?></a>
				<a class="button button-primary button-hero" href="<?php echo esc_url( add_query_arg( 'wizard_step', 4, $base ) ); ?>">
					<?php esc_html_e( 'ادامه به چک‌لیست آمادگی', 'shojaei-seo-for-woo' ); ?>
				</a>
			</p>
		</div>

	<?php else : ?>
		<div class="shojaei-card">
			<h3><?php esc_html_e( '۴) آمادگی خاموش کردن Rank Math', 'shojaei-seo-for-woo' ); ?></h3>
			<p class="shojaei-desc"><?php esc_html_e( 'وقتی همه موارد سبز شدند، نقشه سایت Damavand را در Search Console ثبت کنید و بعد Rank Math را غیرفعال کنید.', 'shojaei-seo-for-woo' ); ?></p>

			<ul id="damavand-ready-list" class="shojaei-wizard-list">
				<?php foreach ( (array) ( $ready['items'] ?? array() ) as $item ) : ?>
					<li class="<?php echo ! empty( $item['ok'] ) ? 'is-ok' : 'is-bad'; ?>">
						<strong><?php echo ! empty( $item['ok'] ) ? '✓ ' : '✗ '; ?><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></strong>
						<em><?php echo esc_html( (string) ( $item['detail'] ?? '' ) ); ?></em>
						<?php if ( empty( $item['ok'] ) && ! empty( $item['fix'] ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( (string) $item['fix'] ); ?>"><?php esc_html_e( 'رفع', 'shojaei-seo-for-woo' ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<p>
				<button type="button" class="button" id="damavand-ready-refresh"><?php esc_html_e( 'بررسی مجدد آمادگی', 'shojaei-seo-for-woo' ); ?></button>
				<span id="damavand-ready-refresh-status" class="description" aria-live="polite"></span>
			</p>

			<p id="damavand-ready-cta" <?php echo empty( $ready['ready'] ) ? 'hidden' : ''; ?>>
				<span class="shojaei-tone-safe"><?php esc_html_e( 'آماده به‌نظر می‌رسد. نقشه سایت را کپی کنید، در GSC ثبت کنید، سپس Rank Math را خاموش کنید.', 'shojaei-seo-for-woo' ); ?></span>
			</p>
			<p id="damavand-ready-wait" <?php echo ! empty( $ready['ready'] ) ? 'hidden' : ''; ?> class="description">
				<?php esc_html_e( 'هنوز مواردی ناقص است — به مرحله مهاجرت یا فعال‌سازی برگردید.', 'shojaei-seo-for-woo' ); ?>
			</p>

			<p>
				<label for="shojaei-wizard-sitemap-url"><strong><?php esc_html_e( 'آدرس نقشه سایت', 'shojaei-seo-for-woo' ); ?></strong></label><br />
				<input type="text" id="shojaei-wizard-sitemap-url" class="regular-text" readonly dir="ltr" value="<?php echo esc_attr( $sitemap_url ); ?>" style="min-width:min(100%,420px);margin-top:6px;" />
				<button type="button" class="button" id="shojaei-wizard-sitemap-copy"><?php esc_html_e( 'کپی', 'shojaei-seo-for-woo' ); ?></button>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'shojaei_seo_finish_setup', 'shojaei_seo_wizard_nonce' ); ?>
				<input type="hidden" name="shojaei_seo_finish_setup" value="1" />
				<p>
					<a class="button" href="<?php echo esc_url( add_query_arg( 'wizard_step', 3, $base ) ); ?>"><?php esc_html_e( 'قبلی', 'shojaei-seo-for-woo' ); ?></a>
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'پایان ویزارد — مرکز وضعیت', 'shojaei-seo-for-woo' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'صفحه افزونه‌ها (خاموش کردن Rank Math)', 'shojaei-seo-for-woo' ); ?></a>
				</p>
			</form>
		</div>
	<?php endif; ?>
</div>
