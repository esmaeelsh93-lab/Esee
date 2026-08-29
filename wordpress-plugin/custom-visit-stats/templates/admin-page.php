<?php
/**
 * قالب صفحه‌ی اصلی گزارش آمار بازدید.
 * این فایل فقط توسط CVS_Admin::render_stats_page() فراخوانی می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'cvs_export_csv',
			'range'  => $range,
			'from'   => $from,
			'to'     => $to,
		),
		admin_url( 'admin-post.php' )
	),
	'cvs_export_csv'
);
?>
<div class="wrap">
	<div class="cvs-dashboard" dir="rtl">

		<div class="cvs-header">
			<div>
				<h1>آمار بازدید سایت</h1>
				<div class="cvs-subtitle">از <?php echo esc_html( CVS_Jalali::format( $from, 'full' ) ); ?> تا <?php echo esc_html( CVS_Jalali::format( $to, 'full' ) ); ?></div>
			</div>
			<div class="cvs-actions">
				<a class="cvs-btn cvs-btn-primary" href="<?php echo esc_url( $export_url ); ?>">⬇ دریافت خروجی CSV</a>
				<a class="cvs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CVS_Admin::PAGE_SETTINGS ) ); ?>">⚙ تنظیمات</a>
			</div>
		</div>

		<div class="cvs-filterbar">
			<?php foreach ( CVS_Admin::get_range_options() as $key => $label ) : ?>
				<a class="cvs-pill <?php echo $range === $key ? 'is-active' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( array( 'page' => CVS_Admin::PAGE_STATS, 'range' => $key ), admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>

			<?php if ( 'custom' === $range ) : ?>
				<span class="cvs-filterbar-spacer"></span>
				<form class="cvs-custom-range" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="<?php echo esc_attr( CVS_Admin::PAGE_STATS ); ?>" />
					<input type="hidden" name="range" value="custom" />
					<label>از <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>" /></label>
					<label>تا <input type="date" name="to" value="<?php echo esc_attr( $to ); ?>" /></label>
					<button type="submit" class="cvs-btn cvs-btn-primary">اعمال</button>
				</form>
			<?php endif; ?>
		</div>

		<div class="cvs-kpi-grid">
			<div class="cvs-kpi-card cvs-kpi-slate">
				<span class="cvs-kpi-badge cvs-badge-blue">مشاهده</span>
				<div class="cvs-kpi-label">کل ورودی‌ها</div>
				<div class="cvs-kpi-value"><?php echo esc_html( CVS_Jalali::format_number( $total ) ); ?></div>
				<div class="cvs-kpi-change is-<?php echo esc_attr( $total_change['direction'] ); ?>">
					<?php echo 'up' === $total_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( CVS_Jalali::to_persian_digits( $total_change['percent'] ) ); ?>٪ نسبت به بازه قبل
				</div>
			</div>

			<div class="cvs-kpi-card cvs-kpi-teal">
				<span class="cvs-kpi-badge cvs-badge-yellow">مشاهده</span>
				<div class="cvs-kpi-label">میانگین ورودی روزانه</div>
				<div class="cvs-kpi-value"><?php echo esc_html( CVS_Jalali::format_number( $daily_average ) ); ?></div>
				<div class="cvs-kpi-change is-<?php echo esc_attr( $average_change['direction'] ); ?>">
					<?php echo 'up' === $average_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( CVS_Jalali::to_persian_digits( $average_change['percent'] ) ); ?>٪ نسبت به بازه قبل
				</div>
			</div>

			<div class="cvs-kpi-card cvs-kpi-blue">
				<span class="cvs-kpi-badge cvs-badge-red">مشاهده</span>
				<div class="cvs-kpi-label">برترین منبع ورودی</div>
				<div class="cvs-kpi-value">
					<?php echo $top_source ? esc_html( $top_source->source_label . ' — ' . CVS_Jalali::format_number( $top_source->total ) ) : 'داده‌ای موجود نیست'; ?>
				</div>
				<?php if ( $top_source ) : ?>
					<div class="cvs-kpi-change is-<?php echo esc_attr( $top_change['direction'] ); ?>">
						<?php echo 'up' === $top_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( CVS_Jalali::to_persian_digits( $top_change['percent'] ) ); ?>٪ نسبت به بازه قبل
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="cvs-row cvs-row-2">
			<div class="cvs-panel">
				<div class="cvs-panel-header">
					<h2>روند ورودی به تفکیک منبع</h2>
					<span class="cvs-panel-sub"><?php echo esc_html( CVS_Jalali::to_persian_digits( $days_count ) ); ?> روز</span>
				</div>
				<div class="cvs-chart-wrap cvs-chart-tall">
					<canvas id="cvsBarChart"></canvas>
				</div>
			</div>

			<div class="cvs-panel">
				<div class="cvs-panel-header">
					<h2>خلاصه وضعیت بازدید</h2>
				</div>
				<div class="cvs-mini-metrics">
					<div class="cvs-mini-metric">
						<div class="cvs-mini-label">کل ورودی</div>
						<div class="cvs-mini-value"><?php echo esc_html( CVS_Jalali::format_number( $total ) ); ?></div>
					</div>
					<div class="cvs-mini-metric">
						<div class="cvs-mini-label">میانگین روزانه</div>
						<div class="cvs-mini-value"><?php echo esc_html( CVS_Jalali::format_number( $daily_average ) ); ?></div>
					</div>
					<div class="cvs-mini-metric">
						<div class="cvs-mini-label">منابع فعال</div>
						<div class="cvs-mini-value"><?php echo esc_html( CVS_Jalali::format_number( $active_sources ) ); ?></div>
					</div>
					<div class="cvs-mini-metric">
						<div class="cvs-mini-label">پربازدیدترین روز</div>
						<div class="cvs-mini-value">
							<?php echo $best_day ? esc_html( CVS_Jalali::format( $best_day['date'], 'short' ) ) : '—'; ?>
						</div>
					</div>
				</div>
				<div class="cvs-chart-wrap">
					<canvas id="cvsAreaChart"></canvas>
				</div>
			</div>
		</div>

		<div class="cvs-row cvs-row-3">
			<div class="cvs-panel">
				<div class="cvs-panel-header">
					<h2>توزیع منابع</h2>
				</div>
				<div class="cvs-donut-wrap">
					<canvas id="cvsDonutChart"></canvas>
				</div>
				<div class="cvs-legend-list">
					<?php foreach ( array_slice( $breakdown, 0, 6 ) as $row ) : ?>
						<?php $color = isset( $chart_colors[ $row->source_key ] ) ? $chart_colors[ $row->source_key ] : '#64748b'; ?>
						<div class="cvs-legend-item">
							<span class="cvs-legend-key">
								<span class="cvs-legend-dot" style="background: <?php echo esc_attr( $color ); ?>"></span>
								<?php echo esc_html( $row->source_label ); ?>
							</span>
							<span class="cvs-legend-value"><?php echo esc_html( CVS_Jalali::format_number( $row->total ) ); ?></span>
						</div>
					<?php endforeach; ?>
					<?php if ( empty( $breakdown ) ) : ?>
						<div class="cvs-empty-state">داده‌ای برای نمایش وجود ندارد</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="cvs-panel">
				<div class="cvs-panel-header">
					<h2>روند تجمعی ورودی‌ها</h2>
					<span class="cvs-panel-sub">مجموع تجمعی در بازه</span>
				</div>
				<div class="cvs-chart-wrap cvs-chart-tall">
					<canvas id="cvsLineChart"></canvas>
				</div>
			</div>

			<div class="cvs-panel">
				<div class="cvs-panel-header">
					<h2>منابع برتر</h2>
				</div>
				<div class="cvs-rank-stack">
					<?php foreach ( $ranked_with_change as $i => $row ) : ?>
						<?php $color = isset( $chart_colors[ $row['key'] ] ) ? $chart_colors[ $row['key'] ] : '#64748b'; ?>
						<div class="cvs-rank-card" style="background: <?php echo esc_attr( $color ); ?>">
							<div class="cvs-rank-icon"><?php echo esc_html( CVS_Jalali::to_persian_digits( $i + 1 ) ); ?></div>
							<div class="cvs-rank-info">
								<div class="cvs-rank-value">
									<?php echo esc_html( CVS_Jalali::format_number( $row['total'] ) ); ?>
									<span class="cvs-rank-change">
										<?php echo 'up' === $row['change']['direction'] ? '▲' : '▼'; ?>
										<?php echo esc_html( CVS_Jalali::to_persian_digits( $row['change']['percent'] ) ); ?>٪
									</span>
								</div>
								<div class="cvs-rank-label"><?php echo esc_html( $row['label'] ); ?></div>
							</div>
						</div>
					<?php endforeach; ?>
					<?php if ( empty( $ranked_with_change ) ) : ?>
						<div class="cvs-empty-state">داده‌ای برای نمایش وجود ندارد</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="cvs-table-panel">
			<div class="cvs-panel-header">
				<h2>جدول تفکیکی روزانه</h2>
			</div>
			<?php if ( empty( $daily_table ) ) : ?>
				<div class="cvs-empty-state">هنوز هیچ بازدیدی برای این بازه‌ی زمانی ثبت نشده است.</div>
			<?php else : ?>
				<table class="cvs-table">
					<thead>
						<tr>
							<th>تاریخ</th>
							<th>کل ورودی</th>
							<th>تفکیک منابع</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $daily_table as $day ) : ?>
							<tr>
								<td><?php echo esc_html( CVS_Jalali::format( $day['date'], 'short' ) ); ?></td>
								<td class="cvs-total-cell"><?php echo esc_html( CVS_Jalali::format_number( $day['total'] ) ); ?></td>
								<td>
									<?php
									arsort( $day['sources'] );
									foreach ( $day['sources'] as $src ) :
										?>
										<span class="cvs-source-chip"><?php echo esc_html( $src['label'] ); ?>: <?php echo esc_html( CVS_Jalali::to_persian_digits( $src['total'] ) ); ?></span>
										<?php
									endforeach;
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $other_referrers ) ) : ?>
			<div class="cvs-table-panel">
				<div class="cvs-panel-header">
					<h2>سایر منابع شناسایی‌نشده</h2>
					<span class="cvs-panel-sub">در صورت تمایل می‌توانید این دامنه‌ها را در آینده به لیست منابع شناخته‌شده اضافه کنید</span>
				</div>
				<table class="cvs-table">
					<thead>
						<tr>
							<th>دامنه</th>
							<th>تعداد</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $other_referrers as $ref ) : ?>
							<tr>
								<td><?php echo esc_html( $ref->referrer_host ); ?></td>
								<td><?php echo esc_html( CVS_Jalali::format_number( $ref->total ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

	</div>
</div>
