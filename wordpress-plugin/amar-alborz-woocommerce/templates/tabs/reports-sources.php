<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «منابع ورودی»: روند و توزیع ورودی‌های واقعی سایت بر اساس منبع ارجاع/UTM.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total           = AAW_DB::get_total( $from, $to );
$prev_total       = AAW_DB::get_total( $prev_from, $prev_to );
$breakdown        = AAW_DB::get_breakdown_by_source( $from, $to );
$daily_series      = AAW_DB::get_daily_series( $from, $to );
$daily_table       = AAW_DB::get_daily_breakdown_table( $from, $to );
$other_referrers   = AAW_DB::get_other_referrers( $from, $to );

$days_count    = max( 1, count( $daily_series['dates'] ) );
$daily_average = round( $total / $days_count, 1 );
$prev_average  = round( $prev_total / $days_count, 1 );
$active_sources = count( $breakdown );

$top_source      = ! empty( $breakdown ) ? $breakdown[0] : null;
$top_source_prev = $top_source ? AAW_DB::get_source_total( $prev_from, $prev_to, $top_source->source_key ) : 0;

$best_day = null;
foreach ( $daily_table as $day ) {
	if ( null === $best_day || $day['total'] > $best_day['total'] ) {
		$best_day = $day;
	}
}

$total_change   = AAW_Admin::calc_percent_change( $total, $prev_total );
$average_change = AAW_Admin::calc_percent_change( $daily_average, $prev_average );
$top_change     = $top_source ? AAW_Admin::calc_percent_change( (int) $top_source->total, $top_source_prev ) : array( 'percent' => 0, 'direction' => 'up' );

$top_sources_ranked = array_slice( $breakdown, 0, 4 );
$ranked_with_change = array();
foreach ( $top_sources_ranked as $row ) {
	$prev_count           = AAW_DB::get_source_total( $prev_from, $prev_to, $row->source_key );
	$ranked_with_change[] = array(
		'key'    => $row->source_key,
		'label'  => $row->source_label,
		'total'  => (int) $row->total,
		'change' => AAW_Admin::calc_percent_change( (int) $row->total, $prev_count ),
	);
}

$chart_colors = AAW_Admin::get_chart_colors();

$daily_totals = array();
foreach ( $daily_series['dates'] as $date ) {
	$sum = 0;
	foreach ( $daily_series['sources'] as $source ) {
		$sum += isset( $source['data'][ $date ] ) ? $source['data'][ $date ] : 0;
	}
	$daily_totals[ $date ] = $sum;
}

$daily_series['datesFormatted'] = array_map(
	function ( $date ) {
		return AAW_Jalali::format( $date, 'short' );
	},
	$daily_series['dates']
);

$export_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'aaw_export_csv',
			'range'  => $range,
			'from'   => $from,
			'to'     => $to,
		),
		admin_url( 'admin-post.php' )
	),
	'aaw_export_csv'
);

wp_localize_script(
	'aaw-admin',
	'aawChartData',
	array(
		'daily'       => $daily_series,
		'dailyTotals' => array_values( $daily_totals ),
		'sources'     => array_map(
			function ( $row ) {
				return array(
					'key'   => $row->source_key,
					'label' => $row->source_label,
					'total' => (int) $row->total,
				);
			},
			$breakdown
		),
		'colors' => $chart_colors,
		'i18n'   => array(
			'totalLabel'      => 'تعداد ورودی',
			'cumulativeLabel' => 'روند تجمعی ورودی‌ها',
		),
	)
);
?>
<div class="aaw-toolbar-row">
	<a class="aaw-btn aaw-btn-primary" href="<?php echo esc_url( $export_url ); ?>">⬇ دریافت خروجی CSV</a>
</div>

<div class="aaw-kpi-grid aaw-kpi-grid-3">
	<div class="aaw-kpi-card aaw-kpi-slate">
		<div class="aaw-kpi-label">کل ورودی‌ها</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_number( $total ) ); ?></div>
		<div class="aaw-kpi-change is-<?php echo esc_attr( $total_change['direction'] ); ?>">
			<?php echo 'up' === $total_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( AAW_Jalali::to_persian_digits( $total_change['percent'] ) ); ?>٪ نسبت به بازه قبل
		</div>
	</div>

	<div class="aaw-kpi-card aaw-kpi-teal">
		<div class="aaw-kpi-label">میانگین ورودی روزانه</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_number( $daily_average ) ); ?></div>
		<div class="aaw-kpi-change is-<?php echo esc_attr( $average_change['direction'] ); ?>">
			<?php echo 'up' === $average_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( AAW_Jalali::to_persian_digits( $average_change['percent'] ) ); ?>٪ نسبت به بازه قبل
		</div>
	</div>

	<div class="aaw-kpi-card aaw-kpi-blue">
		<div class="aaw-kpi-label">برترین منبع ورودی</div>
		<div class="aaw-kpi-value">
			<?php echo $top_source ? esc_html( $top_source->source_label . ' — ' . AAW_Jalali::format_number( $top_source->total ) ) : 'داده‌ای موجود نیست'; ?>
		</div>
		<?php if ( $top_source ) : ?>
			<div class="aaw-kpi-change is-<?php echo esc_attr( $top_change['direction'] ); ?>">
				<?php echo 'up' === $top_change['direction'] ? 'افزایش' : 'کاهش'; ?> <?php echo esc_html( AAW_Jalali::to_persian_digits( $top_change['percent'] ) ); ?>٪ نسبت به بازه قبل
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="aaw-row aaw-row-2">
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>روند ورودی به تفکیک منبع</h2>
			<span class="aaw-panel-sub"><?php echo esc_html( AAW_Jalali::to_persian_digits( $days_count ) ); ?> روز</span>
		</div>
		<div class="aaw-chart-wrap aaw-chart-tall">
			<canvas id="aawBarChart"></canvas>
		</div>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>خلاصه وضعیت بازدید</h2>
		</div>
		<div class="aaw-mini-metrics">
			<div class="aaw-mini-metric">
				<div class="aaw-mini-label">کل ورودی</div>
				<div class="aaw-mini-value"><?php echo esc_html( AAW_Jalali::format_number( $total ) ); ?></div>
			</div>
			<div class="aaw-mini-metric">
				<div class="aaw-mini-label">میانگین روزانه</div>
				<div class="aaw-mini-value"><?php echo esc_html( AAW_Jalali::format_number( $daily_average ) ); ?></div>
			</div>
			<div class="aaw-mini-metric">
				<div class="aaw-mini-label">منابع فعال</div>
				<div class="aaw-mini-value"><?php echo esc_html( AAW_Jalali::format_number( $active_sources ) ); ?></div>
			</div>
			<div class="aaw-mini-metric">
				<div class="aaw-mini-label">پربازدیدترین روز</div>
				<div class="aaw-mini-value">
					<?php echo $best_day ? esc_html( AAW_Jalali::format( $best_day['date'], 'short' ) ) : '—'; ?>
				</div>
			</div>
		</div>
		<div class="aaw-chart-wrap">
			<canvas id="aawAreaChart"></canvas>
		</div>
	</div>
</div>

<div class="aaw-row aaw-row-3">
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>توزیع منابع</h2>
		</div>
		<div class="aaw-donut-wrap">
			<canvas id="aawDonutChart"></canvas>
		</div>
		<div class="aaw-legend-list">
			<?php foreach ( array_slice( $breakdown, 0, 6 ) as $row ) : ?>
				<?php $color = isset( $chart_colors[ $row->source_key ] ) ? $chart_colors[ $row->source_key ] : '#64748b'; ?>
				<div class="aaw-legend-item">
					<span class="aaw-legend-key">
						<span class="aaw-legend-dot" style="background: <?php echo esc_attr( $color ); ?>"></span>
						<?php echo esc_html( $row->source_label ); ?>
					</span>
					<span class="aaw-legend-value"><?php echo esc_html( AAW_Jalali::format_number( $row->total ) ); ?></span>
				</div>
			<?php endforeach; ?>
			<?php if ( empty( $breakdown ) ) : ?>
				<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>روند تجمعی ورودی‌ها</h2>
			<span class="aaw-panel-sub">مجموع تجمعی در بازه</span>
		</div>
		<div class="aaw-chart-wrap aaw-chart-tall">
			<canvas id="aawLineChart"></canvas>
		</div>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>منابع برتر</h2>
		</div>
		<div class="aaw-rank-stack">
			<?php foreach ( $ranked_with_change as $i => $row ) : ?>
				<?php $color = isset( $chart_colors[ $row['key'] ] ) ? $chart_colors[ $row['key'] ] : '#64748b'; ?>
				<div class="aaw-rank-card" style="background: <?php echo esc_attr( $color ); ?>">
					<div class="aaw-rank-icon"><?php echo esc_html( AAW_Jalali::to_persian_digits( $i + 1 ) ); ?></div>
					<div class="aaw-rank-info">
						<div class="aaw-rank-value">
							<?php echo esc_html( AAW_Jalali::format_number( $row['total'] ) ); ?>
							<span class="aaw-rank-change">
								<?php echo 'up' === $row['change']['direction'] ? '▲' : '▼'; ?>
								<?php echo esc_html( AAW_Jalali::to_persian_digits( $row['change']['percent'] ) ); ?>٪
							</span>
						</div>
						<div class="aaw-rank-label"><?php echo esc_html( $row['label'] ); ?></div>
					</div>
				</div>
			<?php endforeach; ?>
			<?php if ( empty( $ranked_with_change ) ) : ?>
				<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="aaw-table-panel">
	<div class="aaw-panel-header">
		<h2>جدول تفکیکی روزانه</h2>
	</div>
	<?php if ( empty( $daily_table ) ) : ?>
		<div class="aaw-empty-state">هنوز هیچ بازدیدی برای این بازه‌ی زمانی ثبت نشده است.</div>
	<?php else : ?>
		<div class="aaw-table-scroll">
			<table class="aaw-table">
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
							<td><?php echo esc_html( AAW_Jalali::format( $day['date'], 'short' ) ); ?></td>
							<td class="aaw-total-cell"><?php echo esc_html( AAW_Jalali::format_number( $day['total'] ) ); ?></td>
							<td>
								<?php
								arsort( $day['sources'] );
								foreach ( $day['sources'] as $src ) :
									?>
									<span class="aaw-source-chip"><?php echo esc_html( $src['label'] ); ?>: <?php echo esc_html( AAW_Jalali::to_persian_digits( $src['total'] ) ); ?></span>
									<?php
								endforeach;
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>

<?php if ( ! empty( $other_referrers ) ) : ?>
	<div class="aaw-table-panel">
		<div class="aaw-panel-header">
			<h2>سایر منابع شناسایی‌نشده</h2>
			<span class="aaw-panel-sub">در صورت تمایل می‌توانید این دامنه‌ها را در آینده به لیست منابع شناخته‌شده اضافه کنید</span>
		</div>
		<div class="aaw-table-scroll">
			<table class="aaw-table">
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
							<td><?php echo esc_html( AAW_Jalali::format_number( $ref->total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
