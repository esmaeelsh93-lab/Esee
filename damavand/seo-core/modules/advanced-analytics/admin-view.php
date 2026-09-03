<?php
/**
 * ویو ادمین — Advanced Analytics & Google Hub.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$mod = $modules['advanced-analytics'] ?? null;
if ( ! $mod instanceof SEO_Core_Advanced_Analytics_Module ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول تحلیل بارگذاری نشده است.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$status   = $mod->status_payload();
$ga4_id   = class_exists( 'Shojaei_SEO_GA4' ) ? Shojaei_SEO_GA4::get_measurement_id() : '';
$ga4_on   = 'yes' === get_option( Shojaei_SEO_GA4::OPTION_ENABLED, 'yes' );
$ga4_force = 'yes' === get_option( 'shojaei_seo_ga4_force', 'no' );
$auto_sm  = ! empty( $status['auto_sitemap'] );
$last     = is_array( $status['last_sitemap'] ?? null ) ? $status['last_sitemap'] : array();
?>

<?php if ( $mod->is_degraded() ) : ?>
	<div class="notice notice-warning inline" style="margin:0 0 14px;">
		<p>
			<strong><?php esc_html_e( 'حالت Passive (خودترمیمی):', 'shojaei-seo-for-woo' ); ?></strong>
			<?php echo esc_html( $mod->degraded_message() ); ?>
		</p>
		<p>
			<button type="button" class="button" id="damavand-aa-heal"><?php esc_html_e( 'تلاش مجدد پیش‌نیازها', 'shojaei-seo-for-woo' ); ?></button>
			<span id="damavand-aa-heal-status" class="description" aria-live="polite"></span>
		</p>
	</div>
<?php endif; ?>

<div class="damavand-aa-guide" dir="rtl">
	<h3><?php esc_html_e( 'راهنمای اتصال گوگل', 'shojaei-seo-for-woo' ); ?></h3>
	<ol>
		<li>
			<strong><?php esc_html_e( 'Measurement ID در GA4:', 'shojaei-seo-for-woo' ); ?></strong>
			<?php esc_html_e( 'وارد Google Analytics شوید → Admin → Data streams → وب‌سایت → مقدار G-… را کپی کنید و در فیلد زیر بچسبانید.', 'shojaei-seo-for-woo' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'فایل JSON سرویس‌اکانت:', 'shojaei-seo-for-woo' ); ?></strong>
			<?php esc_html_e( 'در Google Cloud Console یک پروژه بسازید، Search Console API و Indexing API را فعال کنید، Service Account بسازید و کلید JSON بگیرید. ایمیل سرویس‌اکانت را در Search Console به‌عنوان Owner اضافه کنید. سپس از', 'shojaei-seo-for-woo' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-gsc' ) ); ?>"><?php esc_html_e( 'تنظیمات ← اتصال GSC', 'shojaei-seo-for-woo' ); ?></a>
			<?php esc_html_e( 'فایل را آپلود کنید (همان زیرساخت قبلی حفظ شده است).', 'shojaei-seo-for-woo' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'حریم خصوصی:', 'shojaei-seo-for-woo' ); ?></strong>
			<?php esc_html_e( 'کلید JSON فقط روی سرور شما ذخیره می‌شود و درخواست‌ها صرفاً به APIهای رسمی گوگل می‌روند — هیچ سرویس ثالثی در مسیر نیست.', 'shojaei-seo-for-woo' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Search Analytics:', 'shojaei-seo-for-woo' ); ?></strong>
			<?php esc_html_e( 'بعد از اتصال GSC، از بخش «عملکرد واقعی جستجو» کوئری‌ها و صفحات پربازدید را با کلیک/نمایش/CTR/رتبه ببینید (گوگل معمولاً ۲–۳ روز تأخیر دارد).', 'shojaei-seo-for-woo' ); ?>
		</li>
	</ol>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'Google Analytics 4', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="description"><?php esc_html_e( 'اگر Measurement ID پر باشد، اسکریپت رسمی gtag.js به‌صورت async در wp_head چاپ می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
	<p>
		<label for="damavand-ga4-id"><strong><?php esc_html_e( 'Measurement ID', 'shojaei-seo-for-woo' ); ?></strong></label><br />
		<input type="text" id="damavand-ga4-id" class="regular-text" dir="ltr" value="<?php echo esc_attr( $ga4_id ); ?>" placeholder="G-XXXXXXXX" />
	</p>
	<p>
		<label><input type="checkbox" id="damavand-ga4-enabled" <?php checked( $ga4_on ); ?> /> <?php esc_html_e( 'فعال‌سازی چاپ gtag', 'shojaei-seo-for-woo' ); ?></label>
	</p>
	<?php if ( ! empty( $status['ga4_competitor'] ) ) : ?>
		<p class="description" style="color:#b45309;">
			<?php esc_html_e( 'افزونه Analytics دیگری تشخیص داده شد — برای جلوگیری از دوبل‌تگ، چاپ پیش‌فرض خاموش است مگر اجبار را روشن کنید.', 'shojaei-seo-for-woo' ); ?>
		</p>
		<p>
			<label><input type="checkbox" id="damavand-ga4-force" <?php checked( $ga4_force ); ?> /> <?php esc_html_e( 'اجبار چاپ حتی با افزونه رقیب', 'shojaei-seo-for-woo' ); ?></label>
		</p>
	<?php endif; ?>
	<p>
		<button type="button" class="button button-primary" id="damavand-ga4-save"><?php esc_html_e( 'ذخیره GA4', 'shojaei-seo-for-woo' ); ?></button>
		<span id="damavand-ga4-status" class="description" aria-live="polite"></span>
	</p>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'Search Console — نقشه سایت', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="description">
		<?php
		echo ! empty( $status['gsc_ready'] )
			? esc_html__( 'اتصال GSC آماده است. آپلود JSON از صفحه تنظیمات انجام می‌شود.', 'shojaei-seo-for-woo' )
			: esc_html__( 'هنوز متصل نیستید — ابتدا JSON را در تنظیمات آپلود کنید.', 'shojaei-seo-for-woo' );
		?>
	</p>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-gsc' ) ); ?>">
			<?php esc_html_e( 'مدیریت کلید JSON / تأیید اتصال', 'shojaei-seo-for-woo' ); ?>
		</a>
	</p>
	<p>
		<label>
			<input type="checkbox" id="damavand-aa-auto-sitemap" <?php checked( $auto_sm ); ?> />
			<?php esc_html_e( 'ارسال خودکار نقشه سایت به Search Console هنگام به‌روزرسانی نقشه', 'shojaei-seo-for-woo' ); ?>
		</label>
	</p>
	<p>
		<button type="button" class="button" id="damavand-aa-save-sitemap"><?php esc_html_e( 'ذخیره تنظیم ارسال', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button button-primary" id="damavand-aa-submit-sitemap" <?php disabled( empty( $status['gsc_ready'] ) ); ?>>
			<?php esc_html_e( 'ارسال فوری نقشه به GSC', 'shojaei-seo-for-woo' ); ?>
		</button>
		<span id="damavand-aa-sitemap-status" class="description" aria-live="polite"></span>
	</p>
	<?php if ( ! empty( $last['url'] ) ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: 1: url 2: date */
				esc_html__( 'آخرین ارسال: %1$s — %2$s', 'shojaei-seo-for-woo' ),
				esc_html( (string) $last['url'] ),
				esc_html( ! empty( $last['at'] ) ? wp_date( 'Y-m-d H:i', (int) $last['at'] ) : '—' )
			);
			?>
		</p>
	<?php endif; ?>
</div>

<div class="shojaei-card" id="damavand-gsc-analytics">
	<h4 style="margin-top:0;"><?php esc_html_e( 'Search Analytics — عملکرد واقعی جستجو', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="description">
		<?php esc_html_e( 'کوئری‌ها، صفحات، کلیک و نمایش از API رسمی Search Console (با تأخیر معمول ۲–۳ روزه گوگل). داده فقط روی سرور شما کش می‌شود.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<p style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
		<label>
			<?php esc_html_e( 'ابعاد', 'shojaei-seo-for-woo' ); ?><br />
			<select id="damavand-sa-dimension">
				<option value="query"><?php esc_html_e( 'کلمه کلیدی (Query)', 'shojaei-seo-for-woo' ); ?></option>
				<option value="page"><?php esc_html_e( 'صفحه', 'shojaei-seo-for-woo' ); ?></option>
				<option value="device"><?php esc_html_e( 'دستگاه', 'shojaei-seo-for-woo' ); ?></option>
				<option value="country"><?php esc_html_e( 'کشور', 'shojaei-seo-for-woo' ); ?></option>
				<option value="date"><?php esc_html_e( 'تاریخ', 'shojaei-seo-for-woo' ); ?></option>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'بازه', 'shojaei-seo-for-woo' ); ?><br />
			<select id="damavand-sa-days">
				<option value="7">۷ روز</option>
				<option value="28" selected>۲۸ روز</option>
				<option value="90">۹۰ روز</option>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'تعداد ردیف', 'shojaei-seo-for-woo' ); ?><br />
			<select id="damavand-sa-limit">
				<option value="10">۱۰</option>
				<option value="25" selected>۲۵</option>
				<option value="50">۵۰</option>
			</select>
		</label>
		<button type="button" class="button button-primary" id="damavand-sa-fetch" <?php disabled( empty( $status['gsc_ready'] ) ); ?>>
			<?php esc_html_e( 'دریافت گزارش', 'shojaei-seo-for-woo' ); ?>
		</button>
		<button type="button" class="button" id="damavand-sa-refresh" <?php disabled( empty( $status['gsc_ready'] ) ); ?>>
			<?php esc_html_e( 'تازه‌سازی (بدون کش)', 'shojaei-seo-for-woo' ); ?>
		</button>
		<span id="damavand-sa-status" class="description" aria-live="polite"></span>
	</p>
	<div id="damavand-sa-totals" class="damavand-sa-totals" hidden></div>
	<div class="damavand-sa-table-wrap">
		<table class="widefat striped damavand-sa-table" id="damavand-sa-table" hidden>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'مورد', 'shojaei-seo-for-woo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'کلیک', 'shojaei-seo-for-woo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'نمایش', 'shojaei-seo-for-woo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'CTR٪', 'shojaei-seo-for-woo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'رتبه میانگین', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'پیشنهاد رایگان کلمات کلیدی (فارسی)', 'shojaei-seo-for-woo' ); ?></h4>
	<p class="description"><?php esc_html_e( 'کلمه اصلی را وارد کنید؛ پیشنهادها از Google Suggest دریافت می‌شوند (فقط سرور شما → گوگل).', 'shojaei-seo-for-woo' ); ?></p>
	<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<input type="text" id="damavand-kw-input" class="regular-text" placeholder="<?php esc_attr_e( 'مثال: کفش ورزشی مردانه', 'shojaei-seo-for-woo' ); ?>" />
		<button type="button" class="button button-primary" id="damavand-kw-suggest"><?php esc_html_e( 'پیشنهاد بگیر', 'shojaei-seo-for-woo' ); ?></button>
		<span id="damavand-kw-status" class="description" aria-live="polite"></span>
	</p>
	<ul id="damavand-kw-results" class="damavand-kw-results" hidden></ul>
</div>
