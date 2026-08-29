<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «قیف فروش»: بازدیدکننده ← مشاهده محصول ← افزودن به سبد ← تسویه‌حساب ← خرید.
 * تمام اعداد این صفحه از رویدادهای واقعی ثبت‌شده می‌آیند؛ هیچ نرخ فرضی یا تخمینی وجود ندارد.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_visitors = AAW_DB::get_total( $from, $to );
$report         = AAW_Funnel::build_report( $from, $to, $total_visitors );
$conversion     = AAW_Funnel::get_overall_conversion( $report );
?>
<div class="aaw-funnel-panel">
	<div class="aaw-panel-header">
		<h2>قیف فروش</h2>
		<span class="aaw-panel-sub">بازدیدکننده ← مشاهده محصول ← افزودن به سبد ← تسویه‌حساب ← خرید</span>
	</div>

	<?php if ( ! $report['woo_active'] ) : ?>
		<div class="aaw-funnel-note">
			ووکامرس روی این سایت فعال نیست؛ مرحله‌ی «بازدیدکنندگان» واقعی است، اما مراحل بعدی نیازمند فعال بودن ووکامرس هستند و در حال حاضر صفر نمایش داده می‌شوند (بدون هیچ‌گونه تخمین).
		</div>
	<?php elseif ( ! $report['has_any_data'] ) : ?>
		<div class="aaw-funnel-note">
			هنوز رویداد واقعی‌ای از ووکامرس در این بازه‌ی زمانی ثبت نشده است. با شروع بازدید از محصولات و ثبت سفارش، این اعداد به‌صورت خودکار و واقعی نمایش داده می‌شوند.
		</div>
	<?php endif; ?>

	<div class="aaw-funnel-toolbar">
		<span class="aaw-funnel-conversion">نرخ تبدیل کل (بازدیدکننده تا خرید): <strong><?php echo esc_html( AAW_Jalali::to_persian_digits( $conversion ) ); ?>٪</strong></span>
		<button type="button" class="aaw-btn aaw-funnel-copy-all" data-aaw-copy-all>⧉ کپی کل قیف</button>
	</div>

	<div class="aaw-funnel-flow">
		<?php foreach ( $report['stages'] as $i => $stage ) : ?>
			<?php if ( $i > 0 ) : ?>
				<div class="aaw-funnel-arrow" aria-hidden="true">↓</div>
			<?php endif; ?>
			<?php
			$row_text = $stage['label'] . ': ' . AAW_Jalali::format_number( $stage['count'] );
			if ( 'visitors' !== $stage['key'] ) {
				$row_text .= ' (' . AAW_Jalali::to_persian_digits( $stage['rate'] ) . '٪ نسبت به مرحله قبل، افت ' . AAW_Jalali::to_persian_digits( $stage['drop'] ) . ' نفر)';
			}
			?>
			<div class="aaw-funnel-stage">
				<div class="aaw-funnel-stage-icon" aria-hidden="true"><?php echo esc_html( $stage['icon'] ); ?></div>
				<div class="aaw-funnel-stage-info">
					<div class="aaw-funnel-stage-label"><?php echo esc_html( $stage['label'] ); ?></div>
					<div class="aaw-funnel-stage-meta">
						<span class="aaw-funnel-stage-count"><?php echo esc_html( AAW_Jalali::format_number( $stage['count'] ) ); ?></span>
						<?php if ( 'visitors' !== $stage['key'] ) : ?>
							<span class="aaw-funnel-stage-rate"><?php echo esc_html( AAW_Jalali::to_persian_digits( $stage['rate'] ) ); ?>٪ نسبت به مرحله قبل</span>
							<?php if ( $stage['drop'] > 0 ) : ?>
								<span class="aaw-funnel-stage-drop">افت <?php echo esc_html( AAW_Jalali::format_number( $stage['drop'] ) ); ?> نفر</span>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
				<button type="button" class="aaw-funnel-copy-btn" data-aaw-copy-row="<?php echo esc_attr( $row_text ); ?>" title="کپی این ردیف" aria-label="کپی این ردیف">⧉</button>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="aaw-panel">
	<div class="aaw-panel-header">
		<h2>راهنمای تفسیر قیف فروش <?php echo AAW_Admin::tooltip( 'اگر بین دو مرحله افت زیادی می‌بینید، آن نقطه دقیقاً جایی است که باید تجربه‌ی کاربری آن مرحله را بهبود دهید.' ); ?></h2>
	</div>
	<p class="aaw-help-text">
		افت بزرگ بین «مشاهده محصول» و «افزودن به سبد» معمولاً به قیمت، تصاویر محصول یا توضیحات مربوط است.
		افت زیاد بین «افزودن به سبد» و «تسویه‌حساب» معمولاً نشانه‌ی هزینه‌ی ارسال بالا یا فرآیند پیچیده‌ی خرید است.
		افت بین «تسویه‌حساب» و «خرید نهایی» معمولاً به روش‌های پرداخت یا خطای فنی هنگام تکمیل خرید برمی‌گردد.
		برای راهنمای کامل‌تر به بخش «<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Education::PAGE_SLUG . '&tab=interpret' ) ); ?>">آموزش ← تفسیر گزارش‌ها</a>» مراجعه کنید.
	</p>
</div>
