<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «درآمد و تبدیل»: فروش ناخالص/خالص، مرجوعی جداگانه، نرخ تبدیل و لیست سفارش‌های مرجوعی‌شده.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woo_active = AAW_WooCommerce::is_active();
$summary    = $woo_active ? AAW_WooCommerce::get_revenue_summary( $from, $to ) : array( 'orders_count' => 0, 'gross_revenue' => 0, 'refunded_total' => 0, 'net_revenue' => 0, 'aov' => 0 );
$conversion = $woo_active ? AAW_WooCommerce::get_conversion_rate( $from, $to ) : 0;
$refunded_orders = $woo_active ? AAW_WooCommerce::get_refunded_orders( $from, $to ) : array();
?>
<?php if ( ! $woo_active ) : ?>
	<div class="aaw-notice aaw-notice-warning">این گزارش نیازمند فعال بودن ووکامرس است.</div>
<?php endif; ?>

<div class="aaw-kpi-grid aaw-kpi-grid-4">
	<div class="aaw-kpi-card aaw-kpi-purple">
		<div class="aaw-kpi-label">فروش ناخالص</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $summary['gross_revenue'] ) ); ?></div>
	</div>
	<div class="aaw-kpi-card aaw-kpi-rose">
		<div class="aaw-kpi-label">مرجوعی <?php echo AAW_Admin::tooltip( 'مجموع مبلغ مرجوعی سفارش‌ها؛ به‌صورت جداگانه و بدون کسر پنهان از فروش نمایش داده می‌شود.' ); ?></div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $summary['refunded_total'] ) ); ?></div>
	</div>
	<div class="aaw-kpi-card aaw-kpi-green">
		<div class="aaw-kpi-label">فروش خالص</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $summary['net_revenue'] ) ); ?></div>
	</div>
	<div class="aaw-kpi-card aaw-kpi-teal">
		<div class="aaw-kpi-label">نرخ تبدیل</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::to_persian_digits( $conversion ) ); ?>٪</div>
	</div>
</div>

<div class="aaw-table-panel">
	<div class="aaw-panel-header">
		<h2>سفارش‌های دارای مرجوعی</h2>
		<span class="aaw-panel-sub">نمایش جداگانه‌ی هر مرجوعی، بدون تأثیر روی عدد فروش ناخالص</span>
	</div>
	<?php if ( empty( $refunded_orders ) ) : ?>
		<div class="aaw-empty-state">در این بازه هیچ مرجوعی ثبت نشده است.</div>
	<?php else : ?>
		<div class="aaw-table-scroll">
			<table class="aaw-table">
				<thead>
					<tr>
						<th>شماره سفارش</th>
						<th>تاریخ</th>
						<th>مبلغ سفارش</th>
						<th>مبلغ مرجوعی</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $refunded_orders as $order ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order['order_id'] . '&action=edit' ) ); ?>">#<?php echo esc_html( AAW_Jalali::to_persian_digits( $order['order_id'] ) ); ?></a></td>
							<td><?php echo esc_html( AAW_Jalali::format( $order['date'], 'short' ) ); ?></td>
							<td><?php echo esc_html( AAW_Jalali::format_money( $order['total'] ) ); ?></td>
							<td><?php echo esc_html( AAW_Jalali::format_money( $order['refunded'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
