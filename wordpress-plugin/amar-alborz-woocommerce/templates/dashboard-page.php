<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * قالب صفحه‌ی داشبورد اصلی. این فایل فقط توسط AAW_Admin::render_dashboard_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_subtitle  = 'داشبورد آماری کامل فروشگاه شما در یک نگاه';
$header_actions = array(
	array( 'label' => '📈 گزارش‌ها', 'url' => admin_url( 'admin.php?page=' . AAW_Admin::PAGE_REPORTS ), 'class' => '' ),
	array( 'label' => '⚙ تنظیمات', 'url' => admin_url( 'admin.php?page=' . AAW_Admin::PAGE_SETTINGS ), 'class' => '' ),
);

$range_page = AAW_Admin::PAGE_DASHBOARD;
?>
<div class="wrap">
	<div class="aaw-app" id="aawApp" data-theme="<?php echo esc_attr( AAW_Admin::get_settings()['theme_default'] ); ?>" dir="rtl">

		<?php include AAW_PLUGIN_DIR . 'templates/partials/header.php'; ?>
		<?php include AAW_PLUGIN_DIR . 'templates/partials/range-bar.php'; ?>

		<?php if ( $unread_alerts > 0 ) : ?>
			<div class="aaw-alert-banner">
				<div class="aaw-alert-banner-icon" aria-hidden="true">🔔</div>
				<div class="aaw-alert-banner-body">
					<strong><?php echo esc_html( AAW_Jalali::to_persian_digits( $unread_alerts ) ); ?> هشدار هوشمند خوانده‌نشده</strong>
					<span><?php echo esc_html( $alerts[0]->title ); ?></span>
				</div>
				<a class="aaw-btn aaw-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_PRO_TOOLS . '&tab=alerts' ) ); ?>">مشاهده هشدارها</a>
			</div>
		<?php endif; ?>

		<?php if ( ! $woo_active ) : ?>
			<div class="aaw-notice aaw-notice-warning">
				ووکامرس روی این سایت فعال نیست. گزارش‌های بازدید و منابع ورودی نمایش داده می‌شود، اما برای فروش، قیف فروش و محصولات، ووکامرس را فعال کنید.
			</div>
		<?php endif; ?>

		<div class="aaw-kpi-grid aaw-kpi-grid-4">
			<div class="aaw-kpi-card aaw-kpi-blue">
				<div class="aaw-kpi-label">بازدیدکنندگان <?php echo AAW_Admin::tooltip( 'تعداد نشست‌های واقعی و یکتای بازدیدکنندگان در این بازه (هر بازدیدکننده در بازه‌ی نشست خود فقط یک‌بار شمارش می‌شود).' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_number( $visitors ) ); ?></div>
				<div class="aaw-kpi-change is-<?php echo esc_attr( $visitors_change['direction'] ); ?>">
					<?php echo 'up' === $visitors_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( AAW_Jalali::to_persian_digits( $visitors_change['percent'] ) ); ?>٪ نسبت به بازه قبل
				</div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-teal">
				<div class="aaw-kpi-label">نرخ تبدیل <?php echo AAW_Admin::tooltip( 'درصد بازدیدکنندگانی که به خریدار واقعی تبدیل شده‌اند (تعداد سفارش تقسیم بر تعداد بازدیدکننده).' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::to_persian_digits( $conversion_rate ) ); ?>٪</div>
				<div class="aaw-kpi-change is-<?php echo esc_attr( $conversion_change['direction'] ); ?>">
					<?php echo 'up' === $conversion_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( AAW_Jalali::to_persian_digits( $conversion_change['percent'] ) ); ?>٪ نسبت به بازه قبل
				</div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-purple">
				<div class="aaw-kpi-label">فروش ناخالص <?php echo AAW_Admin::tooltip( 'مجموع مبلغ سفارش‌های در وضعیت در حال انجام، تکمیل‌شده و در انتظار؛ سفارش‌های ناموفق و لغوشده در این عدد نیستند.' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $revenue['gross_revenue'] ) ); ?></div>
				<div class="aaw-kpi-change is-<?php echo esc_attr( $revenue_change['direction'] ); ?>">
					<?php echo 'up' === $revenue_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( AAW_Jalali::to_persian_digits( $revenue_change['percent'] ) ); ?>٪ نسبت به بازه قبل
				</div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-orange">
				<div class="aaw-kpi-label">تعداد سفارش <?php echo AAW_Admin::tooltip( 'تعداد سفارش‌های واقعی و یکتا در این بازه (هر سفارش فقط یک‌بار شمارش می‌شود).' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_number( $revenue['orders_count'] ) ); ?></div>
				<div class="aaw-kpi-change">میانگین سبد خرید: <?php echo esc_html( AAW_Jalali::format_money( $revenue['aov'] ) ); ?></div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-slate">
				<div class="aaw-kpi-label">نرخ خروج بدون تعامل (Bounce) <?php echo AAW_Admin::tooltip( 'درصد بازدیدکنندگانی که فقط یک صفحه دیده‌اند و هیچ تعامل واقعی (افزودن به سبد، تسویه‌حساب) نداشته‌اند.' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::to_persian_digits( $bounce_rate ) ); ?>٪</div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-rose">
				<div class="aaw-kpi-label">مرجوعی (Refund) <?php echo AAW_Admin::tooltip( 'مجموع مبلغ مرجوعی‌ها به‌صورت جداگانه؛ این عدد از فروش ناخالص کسر پنهان نمی‌شود.' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $revenue['refunded_total'] ) ); ?></div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-green">
				<div class="aaw-kpi-label">فروش خالص <?php echo AAW_Admin::tooltip( 'فروش ناخالص منهای مرجوعی؛ برآورد واقعی درآمد نهایی.' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $revenue['net_revenue'] ) ); ?></div>
			</div>

			<div class="aaw-kpi-card aaw-kpi-amber">
				<div class="aaw-kpi-label">سبدهای رها شده <?php echo AAW_Admin::tooltip( 'سبدهایی که کاربر محصولی به آن‌ها افزوده اما پس از مدتی بدون خرید ترک کرده است.' ); ?></div>
				<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_number( $abandoned['total'] ) ); ?></div>
				<div class="aaw-kpi-change">ارزش ازدست‌رفته: <?php echo esc_html( AAW_Jalali::format_money( $abandoned['total_value'] ) ); ?></div>
			</div>
		</div>

		<div class="aaw-funnel-panel">
			<div class="aaw-panel-header">
				<h2>قیف فروش (خلاصه)</h2>
				<a class="aaw-panel-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_REPORTS . '&tab=funnel' ) ); ?>">مشاهده جزئیات ←</a>
			</div>
			<div class="aaw-funnel-flow aaw-funnel-flow-compact">
				<?php foreach ( $funnel_report['stages'] as $i => $stage ) : ?>
					<?php if ( $i > 0 ) : ?><div class="aaw-funnel-arrow" aria-hidden="true">←</div><?php endif; ?>
					<div class="aaw-funnel-mini-stage">
						<div class="aaw-funnel-mini-icon" aria-hidden="true"><?php echo esc_html( $stage['icon'] ); ?></div>
						<div class="aaw-funnel-mini-count"><?php echo esc_html( AAW_Jalali::format_number( $stage['count'] ) ); ?></div>
						<div class="aaw-funnel-mini-label"><?php echo esc_html( $stage['label'] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="aaw-row aaw-row-2">
			<div class="aaw-panel">
				<div class="aaw-panel-header">
					<h2>برترین منابع ورودی</h2>
					<a class="aaw-panel-link" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_REPORTS . '&tab=sources' ) ); ?>">مشاهده کامل ←</a>
				</div>
				<?php if ( empty( $top_sources ) ) : ?>
					<div class="aaw-empty-state">هنوز داده‌ای ثبت نشده است.</div>
				<?php else : ?>
					<div class="aaw-legend-list">
						<?php $colors = AAW_Admin::get_chart_colors(); ?>
						<?php foreach ( $top_sources as $row ) : ?>
							<?php $color = isset( $colors[ $row->source_key ] ) ? $colors[ $row->source_key ] : '#64748b'; ?>
							<div class="aaw-legend-item">
								<span class="aaw-legend-key">
									<span class="aaw-legend-dot" style="background: <?php echo esc_attr( $color ); ?>"></span>
									<?php echo esc_html( $row->source_label ); ?>
								</span>
								<span class="aaw-legend-value"><?php echo esc_html( AAW_Jalali::format_number( $row->total ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="aaw-panel">
				<div class="aaw-panel-header">
					<h2>دسترسی سریع</h2>
				</div>
				<div class="aaw-quicklinks">
					<a class="aaw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_REPORTS . '&tab=audience' ) ); ?>"><span aria-hidden="true">📍</span> جغرافیا، دستگاه و مرورگر</a>
					<a class="aaw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_REPORTS . '&tab=products' ) ); ?>"><span aria-hidden="true">📦</span> محصولات و دسته‌ها</a>
					<a class="aaw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_REPORTS . '&tab=cart-abandon' ) ); ?>"><span aria-hidden="true">🛒</span> سبدهای رها شده</a>
					<a class="aaw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_PRO_TOOLS . '&tab=heatmap' ) ); ?>"><span aria-hidden="true">🔥</span> Heatmap (نسخه تجاری)</a>
					<a class="aaw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_PRO_TOOLS . '&tab=replay' ) ); ?>"><span aria-hidden="true">🎬</span> Session Replay (نسخه تجاری)</a>
					<a class="aaw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Education::PAGE_SLUG ) ); ?>"><span aria-hidden="true">🚀</span> آموزش شروع کار با افزونه</a>
				</div>
			</div>
		</div>

	</div>
</div>
