<?php
/**
 * پوسته‌ی یکپارچه‌ی گزارش‌ها با ناوبری RTL و واکنش‌گرا.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = CVS_Admin::get_settings();
$theme    = in_array( $settings['dashboard_theme'], array( 'light', 'dark', 'auto' ), true ) ? $settings['dashboard_theme'] : 'light';

$descriptions = array(
	'dashboard' => 'تصویر کلی عملکرد سایت و مقایسه با بازه‌ی مشابه قبل',
	'visitors'  => 'نشست‌ها، دستگاه‌ها و رفتار بازدیدکنندگان',
	'sources'   => 'کانال‌های ورودی، کمپین‌ها و ارجاع‌دهنده‌ها',
	'funnel'    => 'مسیر تبدیل بازدیدکننده به سفارش موفق',
	'heatmap'   => 'زیرساخت تحلیل کلیک و عمق اسکرول',
	'geography' => 'پراکندگی بازدیدها بر اساس کشور و شهر',
	'sales'     => 'فروش ثبت‌شده از سفارش‌های تکمیل یا در حال پردازش ووکامرس',
	'events'    => 'اهداف و تعامل‌های سفارشی سایت',
	'guide'     => 'شروع سریع، عیب‌یابی و پاسخ پرسش‌های متداول',
);

$format_duration = static function ( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	$minutes = (int) floor( $seconds / 60 );
	$remain  = $seconds % 60;
	return CVS_Jalali::to_persian_digits( $minutes . ':' . str_pad( (string) $remain, 2, '0', STR_PAD_LEFT ) );
};

$format_money = static function ( $amount ) {
	return CVS_Jalali::to_persian_digits( number_format_i18n( (float) $amount, 0 ) );
};
$currency_label = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان';

$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'cvs_export_csv',
			'range'  => $range,
			'from'   => $from,
			'to'     => $to,
			'report' => $active_tab,
		),
		admin_url( 'admin-post.php' )
	),
	'cvs_export_csv'
);

$data_tabs = array( 'dashboard', 'visitors', 'sources', 'geography', 'sales' );
?>
<div class="wrap cvs-admin-wrap">
	<div class="cvs-app cvs-theme-<?php echo esc_attr( $theme ); ?>" dir="rtl">
		<aside class="cvs-sidebar" aria-label="ناوبری افزونه">
			<div class="cvs-brand">
				<span class="cvs-brand-mark dashicons dashicons-chart-area" aria-hidden="true"></span>
				<span class="cvs-brand-copy">
					<strong>دیدبان</strong>
					<small>آمار و تحلیل بازدید</small>
				</span>
			</div>
			<nav class="cvs-nav">
				<?php foreach ( $navigation as $key => $item ) : ?>
					<a
						class="cvs-nav-item <?php echo $active_tab === $key ? 'is-active' : ''; ?>"
						href="<?php echo esc_url( CVS_Admin::get_tab_url( $key ) ); ?>"
						<?php echo $active_tab === $key ? 'aria-current="page"' : ''; ?>
					>
						<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
						<span class="cvs-nav-label"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="cvs-sidebar-status">
				<span class="cvs-status-dot"></span>
				<div>
					<strong><?php echo esc_html( CVS_Jalali::format_number( $online_count ) ); ?> کاربر آنلاین</strong>
					<small>در ۵ دقیقه‌ی اخیر</small>
				</div>
			</div>
		</aside>

		<main class="cvs-main">
			<header class="cvs-page-header">
				<div>
					<p class="cvs-eyebrow">گزارش مدیریتی</p>
					<h1><?php echo esc_html( $navigation[ $active_tab ]['label'] ); ?></h1>
					<p><?php echo esc_html( isset( $descriptions[ $active_tab ] ) ? $descriptions[ $active_tab ] : '' ); ?></p>
				</div>
				<div class="cvs-header-actions">
					<?php if ( in_array( $active_tab, $data_tabs, true ) ) : ?>
						<span class="cvs-date-caption">
							<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
							<?php echo esc_html( CVS_Jalali::format( $from, 'short' ) ); ?> تا <?php echo esc_html( CVS_Jalali::format( $to, 'short' ) ); ?>
						</span>
					<?php endif; ?>
					<a class="cvs-icon-btn" href="<?php echo esc_url( CVS_Admin::get_tab_url( 'guide' ) ); ?>" aria-label="راهنما">
						<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
					</a>
				</div>
			</header>

			<?php if ( in_array( $active_tab, $data_tabs, true ) ) : ?>
				<section class="cvs-filterbar" aria-label="فیلتر بازه‌ی زمانی">
					<div class="cvs-range-options">
						<?php foreach ( CVS_Admin::get_range_options() as $key => $label ) : ?>
							<a
								class="cvs-pill <?php echo $range === $key ? 'is-active' : ''; ?>"
								href="<?php echo esc_url( add_query_arg( array( 'page' => CVS_Admin::PAGE_STATS, 'tab' => $active_tab, 'range' => $key ), admin_url( 'admin.php' ) ) ); ?>"
							>
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</div>
					<a class="cvs-btn cvs-btn-secondary" href="<?php echo esc_url( $export_url ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						خروجی CSV
					</a>
					<?php if ( 'custom' === $range ) : ?>
						<form class="cvs-custom-range" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
							<input type="hidden" name="page" value="<?php echo esc_attr( CVS_Admin::PAGE_STATS ); ?>">
							<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>">
							<input type="hidden" name="range" value="custom">
							<label>از <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
							<label>تا <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>"></label>
							<button type="submit" class="cvs-btn cvs-btn-primary">اعمال بازه</button>
						</form>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( 'dashboard' === $active_tab ) : ?>
				<section class="cvs-kpi-grid" aria-label="شاخص‌های کلیدی">
					<article class="cvs-kpi-card">
						<div class="cvs-kpi-top">
							<span class="cvs-kpi-icon is-primary dashicons dashicons-visibility" aria-hidden="true"></span>
							<span class="cvs-change is-<?php echo esc_attr( $total_change['direction'] ); ?>">
								<?php echo 'up' === $total_change['direction'] ? '↑' : '↓'; ?>
								<?php echo esc_html( CVS_Jalali::to_persian_digits( $total_change['percent'] ) ); ?>٪
							</span>
						</div>
						<p>کل بازدید صفحات</p>
						<strong><?php echo esc_html( CVS_Jalali::format_number( $total ) ); ?></strong>
						<small>نسبت به بازه‌ی مشابه قبل</small>
					</article>
					<article class="cvs-kpi-card">
						<div class="cvs-kpi-top">
							<span class="cvs-kpi-icon is-success dashicons dashicons-groups" aria-hidden="true"></span>
							<span class="cvs-change is-<?php echo esc_attr( $unique_change['direction'] ); ?>">
								<?php echo 'up' === $unique_change['direction'] ? '↑' : '↓'; ?>
								<?php echo esc_html( CVS_Jalali::to_persian_digits( $unique_change['percent'] ) ); ?>٪
							</span>
						</div>
						<p>بازدیدکننده یکتا</p>
						<strong><?php echo esc_html( CVS_Jalali::format_number( $unique_visitors ) ); ?></strong>
						<small>شناسه‌ی ناشناس با نمک روزانه</small>
					</article>
					<article class="cvs-kpi-card">
						<div class="cvs-kpi-top">
							<span class="cvs-kpi-icon is-warning dashicons dashicons-clock" aria-hidden="true"></span>
							<span class="cvs-change is-<?php echo esc_attr( $sessions_change['direction'] ); ?>">
								<?php echo 'up' === $sessions_change['direction'] ? '↑' : '↓'; ?>
								<?php echo esc_html( CVS_Jalali::to_persian_digits( $sessions_change['percent'] ) ); ?>٪
							</span>
						</div>
						<p>تعداد نشست‌ها</p>
						<strong><?php echo esc_html( CVS_Jalali::format_number( $sessions_count ) ); ?></strong>
						<small>میانگین <?php echo esc_html( $format_duration( $avg_duration ) ); ?> دقیقه حضور</small>
					</article>
					<article class="cvs-kpi-card">
						<div class="cvs-kpi-top">
							<span class="cvs-kpi-icon is-danger dashicons dashicons-cart" aria-hidden="true"></span>
							<span class="cvs-change is-<?php echo esc_attr( $sales_change['direction'] ); ?>">
								<?php echo 'up' === $sales_change['direction'] ? '↑' : '↓'; ?>
								<?php echo esc_html( CVS_Jalali::to_persian_digits( $sales_change['percent'] ) ); ?>٪
							</span>
						</div>
						<p>فروش</p>
						<strong><?php echo esc_html( $format_money( $sales['total_sales'] ) ); ?> <small><?php echo esc_html( $currency_label ); ?></small></strong>
						<small><?php echo esc_html( CVS_Jalali::format_number( $sales['orders_count'] ) ); ?> سفارش معتبر</small>
					</article>
				</section>

				<section class="cvs-compact-stats cvs-dashboard-secondary">
					<div><span>کاربران آنلاین</span><strong><i class="cvs-online-dot"></i><?php echo esc_html( CVS_Jalali::format_number( $online_count ) ); ?></strong></div>
					<div><span>نرخ پرش</span><strong><?php echo esc_html( CVS_Jalali::to_persian_digits( $bounce_rate ) ); ?>٪</strong></div>
					<div><span>میانگین حضور</span><strong><?php echo esc_html( $format_duration( $avg_duration ) ); ?></strong></div>
				</section>

				<section class="cvs-dashboard-grid">
					<article class="cvs-panel cvs-chart-panel">
						<header class="cvs-panel-header">
							<div>
								<h2>روند بازدید</h2>
								<p>تعداد بازدید روزانه در بازه‌ی انتخاب‌شده</p>
							</div>
							<span class="cvs-panel-tag"><?php echo esc_html( CVS_Jalali::to_persian_digits( $days_count ) ); ?> روز</span>
						</header>
						<div class="cvs-chart-wrap"><canvas id="cvsAreaChart"></canvas></div>
					</article>
					<article class="cvs-panel cvs-source-panel">
						<header class="cvs-panel-header">
							<div><h2>منابع برتر</h2><p>سهم کانال‌ها از بازدید</p></div>
							<a href="<?php echo esc_url( CVS_Admin::get_tab_url( 'sources' ) ); ?>">مشاهده همه</a>
						</header>
						<div class="cvs-donut-layout">
							<div class="cvs-donut-wrap"><canvas id="cvsDonutChart"></canvas></div>
							<div class="cvs-legend-list">
								<?php foreach ( array_slice( $breakdown, 0, 5 ) as $row ) : ?>
									<?php $color = isset( $chart_colors[ $row->source_key ] ) ? $chart_colors[ $row->source_key ] : '#64748b'; ?>
									<div class="cvs-legend-item">
										<span><i style="--cvs-source-color: <?php echo esc_attr( $color ); ?>"></i><?php echo esc_html( $row->source_label ); ?></span>
										<strong><?php echo esc_html( CVS_Jalali::format_number( $row->total ) ); ?></strong>
									</div>
								<?php endforeach; ?>
								<?php if ( empty( $breakdown ) ) : ?>
									<p class="cvs-empty-inline">هنوز داده‌ای ثبت نشده است.</p>
								<?php endif; ?>
							</div>
						</div>
					</article>
				</section>

				<article class="cvs-panel">
					<header class="cvs-panel-header">
						<div><h2>صفحات پرترافیک</h2><p>مقایسه‌ی بازدید کل و بازدیدکننده یکتا</p></div>
					</header>
					<?php if ( $top_pages ) : ?>
						<div class="cvs-table-wrap">
							<table class="cvs-table">
								<thead><tr><th>صفحه</th><th>بازدید</th><th>بازدیدکننده یکتا</th></tr></thead>
								<tbody>
									<?php foreach ( $top_pages as $page ) : ?>
										<tr>
											<td class="cvs-path-cell"><?php echo esc_html( $page->request_path ); ?></td>
											<td><strong><?php echo esc_html( CVS_Jalali::format_number( $page->total ) ); ?></strong></td>
											<td><?php echo esc_html( CVS_Jalali::format_number( $page->unique_visitors ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<div class="cvs-mobile-card-list">
							<?php foreach ( $top_pages as $page ) : ?>
								<div class="cvs-data-card">
									<strong><?php echo esc_html( $page->request_path ); ?></strong>
									<span>بازدید: <?php echo esc_html( CVS_Jalali::format_number( $page->total ) ); ?></span>
									<span>یکتا: <?php echo esc_html( CVS_Jalali::format_number( $page->unique_visitors ) ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="cvs-empty-state"><span class="dashicons dashicons-chart-line"></span><h3>منتظر اولین بازدید هستیم</h3><p>پس از باز شدن صفحات سایت، داده‌ها بدون تأثیر کش ثبت می‌شوند.</p></div>
					<?php endif; ?>
				</article>

			<?php elseif ( 'visitors' === $active_tab ) : ?>
				<section class="cvs-compact-stats">
					<div><span>کاربران آنلاین</span><strong><?php echo esc_html( CVS_Jalali::format_number( $online_count ) ); ?></strong></div>
					<div><span>میانگین حضور</span><strong><?php echo esc_html( $format_duration( $avg_duration ) ); ?></strong></div>
					<div><span>نرخ پرش</span><strong><?php echo esc_html( CVS_Jalali::to_persian_digits( $bounce_rate ) ); ?>٪</strong></div>
				</section>
				<article class="cvs-panel">
					<header class="cvs-panel-header"><div><h2>آخرین نشست‌ها</h2><p>ورود، خروج و مشخصات فنی بدون ذخیره‌ی IP خام</p></div></header>
					<?php if ( $recent_sessions ) : ?>
						<div class="cvs-table-wrap">
							<table class="cvs-table">
								<thead><tr><th>آخرین فعالیت</th><th>صفحه ورود</th><th>صفحه خروج</th><th>صفحات</th><th>مدت</th><th>دستگاه</th></tr></thead>
								<tbody>
									<?php foreach ( $recent_sessions as $session ) : ?>
										<tr>
											<td><?php echo esc_html( CVS_Jalali::format( substr( $session->last_seen, 0, 10 ), 'short' ) ); ?></td>
											<td class="cvs-path-cell"><?php echo esc_html( wp_parse_url( $session->entry_page, PHP_URL_PATH ) ?: $session->entry_page ); ?></td>
											<td class="cvs-path-cell"><?php echo esc_html( wp_parse_url( $session->exit_page, PHP_URL_PATH ) ?: $session->exit_page ); ?></td>
											<td><?php echo esc_html( CVS_Jalali::format_number( $session->page_count ) ); ?></td>
											<td><?php echo esc_html( $format_duration( $session->duration_seconds ) ); ?></td>
											<td><span class="cvs-device-badge"><?php echo esc_html( $session->device_type ); ?> · <?php echo esc_html( $session->browser ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<div class="cvs-mobile-card-list">
							<?php foreach ( $recent_sessions as $session ) : ?>
								<div class="cvs-data-card">
									<strong><?php echo esc_html( wp_parse_url( $session->entry_page, PHP_URL_PATH ) ?: '/' ); ?></strong>
									<span><?php echo esc_html( CVS_Jalali::format_number( $session->page_count ) ); ?> صفحه · <?php echo esc_html( $format_duration( $session->duration_seconds ) ); ?></span>
									<span><?php echo esc_html( $session->device_type . ' · ' . $session->browser ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="cvs-empty-state"><span class="dashicons dashicons-groups"></span><h3>نشستی در این بازه وجود ندارد</h3><p>بازه‌ی دیگری انتخاب کنید یا وضعیت نصب اسکریپت ردیابی را در راهنما ببینید.</p></div>
					<?php endif; ?>
				</article>

			<?php elseif ( 'sources' === $active_tab ) : ?>
				<section class="cvs-dashboard-grid">
					<article class="cvs-panel cvs-chart-panel">
						<header class="cvs-panel-header"><div><h2>روند منابع ترافیک</h2><p>تفکیک روزانه‌ی کانال‌های ورودی</p></div></header>
						<div class="cvs-chart-wrap cvs-chart-tall"><canvas id="cvsBarChart"></canvas></div>
					</article>
					<article class="cvs-panel">
						<header class="cvs-panel-header"><div><h2>سهم منابع</h2><p>کل بازدیدهای بازه</p></div></header>
						<div class="cvs-donut-wrap cvs-donut-large"><canvas id="cvsDonutChart"></canvas></div>
					</article>
				</section>
				<article class="cvs-panel">
					<header class="cvs-panel-header"><div><h2>جزئیات منابع</h2><p>بازدید کل و کاربران یکتا برای هر کانال</p></div></header>
					<?php if ( $breakdown ) : ?>
						<div class="cvs-table-wrap">
							<table class="cvs-table">
								<thead><tr><th>منبع</th><th>بازدید</th><th>یکتا</th><th>سهم</th></tr></thead>
								<tbody>
									<?php foreach ( $breakdown as $row ) : ?>
										<?php $share = $total ? round( ( (int) $row->total / $total ) * 100, 1 ) : 0; ?>
										<tr>
											<td><strong><?php echo esc_html( $row->source_label ); ?></strong></td>
											<td><?php echo esc_html( CVS_Jalali::format_number( $row->total ) ); ?></td>
											<td><?php echo esc_html( CVS_Jalali::format_number( $row->unique_visitors ) ); ?></td>
											<td><span class="cvs-progress"><i style="width: <?php echo esc_attr( $share ); ?>%"></i></span><?php echo esc_html( CVS_Jalali::to_persian_digits( $share ) ); ?>٪</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<div class="cvs-mobile-card-list">
							<?php foreach ( $breakdown as $row ) : ?>
								<?php $mobile_share = $total ? round( ( (int) $row->total / $total ) * 100, 1 ) : 0; ?>
								<div class="cvs-data-card">
									<strong><?php echo esc_html( $row->source_label ); ?></strong>
									<span>بازدید: <?php echo esc_html( CVS_Jalali::format_number( $row->total ) ); ?></span>
									<span>سهم: <?php echo esc_html( CVS_Jalali::to_persian_digits( $mobile_share ) ); ?>٪</span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="cvs-empty-state"><span class="dashicons dashicons-share"></span><h3>منبعی ثبت نشده است</h3></div>
					<?php endif; ?>
				</article>

			<?php elseif ( 'geography' === $active_tab ) : ?>
				<article class="cvs-panel">
					<header class="cvs-panel-header"><div><h2>کشورها و شهرها</h2><p>اطلاعات مکان از هدرهای امن CDN مانند Cloudflare دریافت می‌شود.</p></div></header>
					<?php if ( $city_breakdown ) : ?>
						<div class="cvs-table-wrap">
							<table class="cvs-table">
								<thead><tr><th>کشور</th><th>شهر</th><th>بازدید</th><th>بازدیدکننده یکتا</th></tr></thead>
								<tbody>
									<?php foreach ( $city_breakdown as $city_row ) : ?>
										<tr>
											<td><span class="cvs-country-code"><?php echo esc_html( $city_row->country ?: '—' ); ?></span></td>
											<td><?php echo esc_html( $city_row->city ?: 'نامشخص' ); ?></td>
											<td><strong><?php echo esc_html( CVS_Jalali::format_number( $city_row->visits ) ); ?></strong></td>
											<td><?php echo esc_html( CVS_Jalali::format_number( $city_row->unique_visitors ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<div class="cvs-mobile-card-list">
							<?php foreach ( $city_breakdown as $city_row ) : ?>
								<div class="cvs-data-card">
									<strong><?php echo esc_html( $city_row->city ?: 'نامشخص' ); ?> · <?php echo esc_html( $city_row->country ?: '—' ); ?></strong>
									<span>بازدید: <?php echo esc_html( CVS_Jalali::format_number( $city_row->visits ) ); ?></span>
									<span>یکتا: <?php echo esc_html( CVS_Jalali::format_number( $city_row->unique_visitors ) ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="cvs-empty-state"><span class="dashicons dashicons-admin-site-alt3"></span><h3>داده‌ی جغرافیایی در دسترس نیست</h3><p>برای تشخیص کشور، هدر CF-IPCountry را در CDN فعال کنید. IP خام ذخیره نمی‌شود.</p></div>
					<?php endif; ?>
				</article>

			<?php elseif ( 'sales' === $active_tab ) : ?>
				<section class="cvs-kpi-grid cvs-kpi-grid-3">
					<article class="cvs-kpi-card"><div class="cvs-kpi-top"><span class="cvs-kpi-icon is-success dashicons dashicons-money-alt"></span><span class="cvs-change is-<?php echo esc_attr( $sales_change['direction'] ); ?>"><?php echo 'up' === $sales_change['direction'] ? '↑' : '↓'; ?> <?php echo esc_html( CVS_Jalali::to_persian_digits( $sales_change['percent'] ) ); ?>٪</span></div><p>فروش خالص</p><strong><?php echo esc_html( $format_money( $sales['total_sales'] ) ); ?> <small><?php echo esc_html( $currency_label ); ?></small></strong><small>سفارش‌های معتبر ووکامرس</small></article>
					<article class="cvs-kpi-card"><div class="cvs-kpi-top"><span class="cvs-kpi-icon is-primary dashicons dashicons-cart"></span></div><p>تعداد سفارش</p><strong><?php echo esc_html( CVS_Jalali::format_number( $sales['orders_count'] ) ); ?></strong><small>processing و completed</small></article>
					<article class="cvs-kpi-card"><div class="cvs-kpi-top"><span class="cvs-kpi-icon is-warning dashicons dashicons-chart-bar"></span></div><p>میانگین ارزش سفارش</p><strong><?php echo esc_html( $format_money( $sales['orders_count'] ? $sales['total_sales'] / $sales['orders_count'] : 0 ) ); ?> <small><?php echo esc_html( $currency_label ); ?></small></strong><small>در بازه‌ی انتخاب‌شده</small></article>
				</section>
				<div class="cvs-info-banner"><span class="dashicons dashicons-yes-alt"></span><div><strong>شمارش فروش دقیق و برگشت‌پذیر است</strong><p>هر سفارش فقط یک‌بار ثبت می‌شود و با خروج از وضعیت معتبر، مبلغ آن از گزارش کسر خواهد شد.</p></div></div>

			<?php elseif ( 'funnel' === $active_tab ) : ?>
				<?php
				$funnel_sessions = max( 0, $sessions_count );
				$funnel_orders   = max( 0, $sales['orders_count'] );
				$conversion      = $funnel_sessions ? round( ( $funnel_orders / $funnel_sessions ) * 100, 1 ) : 0;
				?>
				<article class="cvs-panel">
					<header class="cvs-panel-header"><div><h2>قیف تبدیل پایه</h2><p>این نما از داده‌های واقعی بازدید، نشست و سفارش استفاده می‌کند.</p></div><span class="cvs-panel-tag"><?php echo esc_html( CVS_Jalali::to_persian_digits( $conversion ) ); ?>٪ تبدیل</span></header>
					<div class="cvs-funnel">
						<div style="--funnel-width: 100%"><span>بازدید صفحه</span><strong><?php echo esc_html( CVS_Jalali::format_number( $total ) ); ?></strong></div>
						<div style="--funnel-width: 76%"><span>نشست فعال</span><strong><?php echo esc_html( CVS_Jalali::format_number( $sessions_count ) ); ?></strong></div>
						<div style="--funnel-width: 48%"><span>سفارش موفق</span><strong><?php echo esc_html( CVS_Jalali::format_number( $sales['orders_count'] ) ); ?></strong></div>
					</div>
					<p class="cvs-muted-note">برای مراحل «مشاهده محصول»، «افزودن به سبد» و «شروع پرداخت»، ردیابی رویداد سفارشی در نسخه‌ی بعدی فعال می‌شود.</p>
				</article>

			<?php elseif ( 'heatmap' === $active_tab ) : ?>
				<div class="cvs-feature-intro">
					<span class="cvs-feature-icon dashicons dashicons-location-alt"></span>
					<p class="cvs-eyebrow">فاز دوم تحلیل رفتار</p>
					<h2>نقشه‌ی حرارتی کلیک و اسکرول</h2>
					<p>ساختار رابط برای نمایش جداگانه‌ی دسکتاپ و لمس آماده است. جمع‌آوری مختصات تنها پس از فعال‌سازی آگاهانه انجام خواهد شد تا حجم داده و حریم خصوصی کنترل شود.</p>
					<div class="cvs-feature-checks"><span>مختصات درصدی</span><span>تفکیک موبایل</span><span>نگهداری محدود</span></div>
				</div>

			<?php elseif ( 'events' === $active_tab ) : ?>
				<div class="cvs-feature-intro">
					<span class="cvs-feature-icon dashicons dashicons-flag"></span>
					<p class="cvs-eyebrow">قابلیت تکمیلی</p>
					<h2>رویدادهای سفارشی</h2>
					<p>در این بخش اهدافی مانند کلیک تماس، دانلود فایل و ارسال فرم تعریف می‌شوند. مدل pageview فعلی از این داده‌ها جدا نگه داشته شده تا آمار پایه دقیق بماند.</p>
					<div class="cvs-feature-checks"><span>کلیک تماس</span><span>دانلود فایل</span><span>ارسال فرم</span></div>
				</div>

			<?php elseif ( 'guide' === $active_tab ) : ?>
				<section class="cvs-guide-grid">
					<article class="cvs-panel">
						<header class="cvs-panel-header"><div><h2>شروع سریع</h2><p>سه گام تا اولین گزارش دقیق</p></div></header>
						<ol class="cvs-steps">
							<li><span>۱</span><div><strong>افزونه را فعال کنید</strong><p>جداول و زمان‌بندی خلاصه‌سازی خودکار ساخته می‌شوند.</p></div></li>
							<li><span>۲</span><div><strong>یک صفحه‌ی عمومی را باز کنید</strong><p>بازدید پس از رویداد load و مستقل از کش ارسال می‌شود.</p></div></li>
							<li><span>۳</span><div><strong>داشبورد را بررسی کنید</strong><p>کاربران مدیر به‌صورت پیش‌فرض از آمار حذف شده‌اند.</p></div></li>
						</ol>
					</article>
					<article class="cvs-panel">
						<header class="cvs-panel-header"><div><h2>عیب‌یابی سریع</h2><p>اگر عددها مطابق انتظار نیستند</p></div></header>
						<div class="cvs-accordion">
							<details><summary>چرا بازدید امروز صفر است؟</summary><p>صفحه را در پنجره ناشناس باز کنید. مدیران و نویسندگان واردشده شمارش نمی‌شوند و ارسال پس از لود کامل انجام می‌شود.</p></details>
							<details><summary>آیا افزونه با کش سازگار است؟</summary><p>بله؛ ثبت در REST API و از سمت مرورگر انجام می‌شود و HTML کش‌شده مانع ثبت بازدید نیست.</p></details>
							<details><summary>آیا IP کاربر ذخیره می‌شود؟</summary><p>خیر؛ فقط هش روزانه با نمک اختصاصی سایت ذخیره می‌شود و امکان بازیابی IP وجود ندارد.</p></details>
							<details><summary>چرا موقعیت شهر خالی است؟</summary><p>نام شهر فقط در صورت ارسال هدر معتبر توسط CDN ثبت می‌شود. افزونه سرویس مکان‌یابی خارجی فراخوانی نمی‌کند.</p></details>
						</div>
					</article>
				</section>
				<div class="cvs-info-banner"><span class="dashicons dashicons-shield"></span><div><strong>حریم خصوصی از ابتدا</strong><p>بدون سرویس خارجی، بدون IP خام و با حالت بدون‌کوکی اختیاری در تنظیمات.</p></div></div>
			<?php endif; ?>
		</main>

		<nav class="cvs-bottom-nav" aria-label="ناوبری موبایل">
			<?php foreach ( array( 'dashboard', 'visitors', 'sources', 'sales' ) as $key ) : ?>
				<a class="<?php echo $active_tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( CVS_Admin::get_tab_url( $key ) ); ?>">
					<span class="dashicons <?php echo esc_attr( $navigation[ $key ]['icon'] ); ?>"></span>
					<small><?php echo esc_html( $navigation[ $key ]['label'] ); ?></small>
				</a>
			<?php endforeach; ?>
			<details class="cvs-more-nav">
				<summary><span class="dashicons dashicons-menu"></span><small>بیشتر</small></summary>
				<div>
					<?php foreach ( array( 'funnel', 'heatmap', 'geography', 'events', 'settings', 'guide' ) as $key ) : ?>
						<a href="<?php echo esc_url( CVS_Admin::get_tab_url( $key ) ); ?>"><span class="dashicons <?php echo esc_attr( $navigation[ $key ]['icon'] ); ?>"></span><?php echo esc_html( $navigation[ $key ]['label'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</details>
		</nav>
	</div>
</div>
