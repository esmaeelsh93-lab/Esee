<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="aaw-settings-panel">
	<h2 class="aaw-settings-subheading" style="margin-top:0; padding-top:0; border-top:none;">درباره‌ی آمار البرز</h2>
	<p>
		آمار البرز افزونه‌ای اختصاصی برای تحلیل فروشگاه‌های ووکامرسی است: داشبورد آماری کامل، قیف فروش واقعی،
		منابع ورودی، گزارش شهر/دستگاه/مرورگر، تحلیل محصولات و دسته‌ها، درآمد و نرخ تبدیل، سبدهای رها شده و ابزارهای
		حرفه‌ای نسخه‌ی تجاری مانند Heatmap، Session Replay، هشدار هوشمند و گزارش UTM.
	</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">نسخه‌ی افزونه</th>
			<td><?php echo esc_html( AAW_Jalali::to_persian_digits( AAW_VERSION ) ); ?></td>
		</tr>
		<tr>
			<th scope="row">وضعیت ووکامرس</th>
			<td><?php echo AAW_WooCommerce::is_active() ? '<span style="color:#4ade80;">فعال ✓</span>' : '<span style="color:#f87171;">غیرفعال</span>'; ?></td>
		</tr>
	</table>

	<h2 class="aaw-settings-subheading">اصل صحت داده‌ها</h2>
	<p>
		تمام گزارش‌های این افزونه بر اساس رویدادهای واقعی ووکامرس و ردیابی سمت سرور محاسبه می‌شوند؛ هیچ عددی حدسی یا تخمینی نیست.
		مبنای آمار سفارش، وضعیت‌های «در حال انجام»، «تکمیل‌شده» و «در انتظار پرداخت» است. سفارش‌های «ناموفق» و «لغوشده» هرگز در فروش محاسبه نمی‌شوند
		و هر سفارش فقط یک‌بار شمارش می‌شود. مبلغ مرجوعی همیشه به‌صورت جداگانه نمایش داده می‌شود.
	</p>

	<h2 class="aaw-settings-subheading">راهنما</h2>
	<p>
		برای آموزش کامل استفاده از افزونه، توضیح اصطلاحات و پاسخ به سوالات متداول، به منوی
		«<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Education::PAGE_SLUG ) ); ?>">آموزش</a>» مراجعه کنید.
	</p>
</div>
