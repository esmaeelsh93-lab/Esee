<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «گزارش UTM» (نسخه تجاری): تحلیل واقعی Campaign، Source، Medium و نرخ تبدیل هر کمپین.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woo_active = AAW_WooCommerce::is_active();
$rows       = $woo_active ? AAW_WooCommerce::get_utm_report( $from, $to ) : array();
?>
<div class="aaw-pro-intro">
	<div class="aaw-pro-badge">نسخه تجاری</div>
	<h2>گزارش UTM</h2>
	<p>عملکرد واقعی هر کمپین تبلیغاتی را با افزودن پارامترهای <code>utm_source</code>، <code>utm_medium</code> و <code>utm_campaign</code> به لینک‌های خود، دقیقاً اندازه‌گیری کنید.
	<?php echo AAW_Admin::tooltip( 'مثال: yoursite.com/?utm_source=instagram&utm_medium=story&utm_campaign=summer-sale' ); ?></p>
</div>

<?php if ( ! $woo_active ) : ?>
	<div class="aaw-notice aaw-notice-warning">این گزارش نیازمند فعال بودن ووکامرس است.</div>
<?php elseif ( empty( $rows ) ) : ?>
	<div class="aaw-empty-state">هنوز سفارشی از طریق لینک دارای پارامتر UTM ثبت نشده است.</div>
<?php else : ?>
	<div class="aaw-table-panel">
		<div class="aaw-table-scroll">
			<table class="aaw-table">
				<thead>
					<tr>
						<th>کمپین (Campaign)</th>
						<th>منبع (Source)</th>
						<th>رسانه (Medium)</th>
						<th>تعداد سفارش</th>
						<th>درآمد</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['campaign'] ); ?></td>
							<td><?php echo esc_html( $row['source'] ); ?></td>
							<td><?php echo esc_html( $row['medium'] ); ?></td>
							<td><?php echo esc_html( AAW_Jalali::format_number( $row['orders'] ) ); ?></td>
							<td><?php echo esc_html( AAW_Jalali::format_money( $row['revenue'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
