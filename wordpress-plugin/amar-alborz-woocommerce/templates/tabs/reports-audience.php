<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «جغرافیا، دستگاه و مرورگر»: گزارش شهرهای واقعی خریداران (بر اساس شهر فاکتور، نه حدس IP)،
 * و تفکیک دستگاه/مرورگر بازدیدکنندگان (بر اساس تحلیل واقعی User-Agent هر درخواست).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$device_breakdown = AAW_DB::get_device_breakdown( $from, $to );
$browser_breakdown = AAW_DB::get_browser_breakdown( $from, $to );
$cities            = AAW_WooCommerce::is_active() ? AAW_WooCommerce::get_cities_report( $from, $to, 10 ) : array();

$device_total = array_sum( wp_list_pluck( $device_breakdown, 'total' ) );
$browser_total = array_sum( wp_list_pluck( $browser_breakdown, 'total' ) );
$colors = AAW_Admin::get_chart_colors();
?>
<div class="aaw-row aaw-row-2">
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>شهرهای برتر خریداران <?php echo AAW_Admin::tooltip( 'این گزارش بر اساس شهر واقعی وارد شده در فرم پرداخت سفارش‌های موفق است، نه موقعیت جغرافیایی تخمینی از روی IP.' ); ?></h2>
		</div>
		<?php if ( ! AAW_WooCommerce::is_active() ) : ?>
			<div class="aaw-empty-state">این گزارش نیازمند فعال بودن ووکامرس است.</div>
		<?php elseif ( empty( $cities ) ) : ?>
			<div class="aaw-empty-state">هنوز سفارشی با شهر مشخص در این بازه ثبت نشده است.</div>
		<?php else : ?>
			<div class="aaw-table-scroll">
				<table class="aaw-table">
					<thead>
						<tr>
							<th>شهر</th>
							<th>تعداد سفارش</th>
							<th>درآمد</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cities as $city ) : ?>
							<tr>
								<td><?php echo esc_html( $city['city'] ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $city['orders'] ) ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_money( $city['revenue'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>نوع دستگاه بازدیدکنندگان</h2>
		</div>
		<?php if ( empty( $device_breakdown ) ) : ?>
			<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
		<?php else : ?>
			<div class="aaw-bar-list">
				<?php foreach ( $device_breakdown as $row ) : ?>
					<?php
					$percent = $device_total > 0 ? round( ( $row->total / $device_total ) * 100 ) : 0;
					$color   = isset( $colors[ $row->device_type ] ) ? $colors[ $row->device_type ] : '#64748b';
					?>
					<div class="aaw-bar-row">
						<div class="aaw-bar-row-label">
							<span aria-hidden="true"><?php echo esc_html( AAW_Device_Detector::device_icon( $row->device_type ) ); ?></span>
							<?php echo esc_html( AAW_Device_Detector::device_label( $row->device_type ) ); ?>
						</div>
						<div class="aaw-bar-track">
							<div class="aaw-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%; background: <?php echo esc_attr( $color ); ?>;"></div>
						</div>
						<div class="aaw-bar-row-value"><?php echo esc_html( AAW_Jalali::format_number( $row->total ) ); ?> (<?php echo esc_html( AAW_Jalali::to_persian_digits( $percent ) ); ?>٪)</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="aaw-panel">
	<div class="aaw-panel-header">
		<h2>مرورگرهای پرکاربرد</h2>
	</div>
	<?php if ( empty( $browser_breakdown ) ) : ?>
		<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
	<?php else : ?>
		<div class="aaw-bar-list">
			<?php foreach ( $browser_breakdown as $row ) : ?>
				<?php $percent = $browser_total > 0 ? round( ( $row->total / $browser_total ) * 100 ) : 0; ?>
				<div class="aaw-bar-row">
					<div class="aaw-bar-row-label"><?php echo esc_html( $row->browser ); ?></div>
					<div class="aaw-bar-track">
						<div class="aaw-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
					</div>
					<div class="aaw-bar-row-value"><?php echo esc_html( AAW_Jalali::format_number( $row->total ) ); ?> (<?php echo esc_html( AAW_Jalali::to_persian_digits( $percent ) ); ?>٪)</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
