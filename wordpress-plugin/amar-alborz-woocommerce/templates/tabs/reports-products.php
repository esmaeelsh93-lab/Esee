<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «محصولات و دسته‌ها»: پرفروش‌ترین محصولات و دسته‌بندی‌ها بر اساس اقلام واقعی سفارش‌های شمارش‌شده.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$woo_active = AAW_WooCommerce::is_active();
$products   = $woo_active ? AAW_WooCommerce::get_products_report( $from, $to, 10 ) : array();
$categories = $woo_active ? AAW_WooCommerce::get_categories_report( $from, $to, 10 ) : array();
?>
<?php if ( ! $woo_active ) : ?>
	<div class="aaw-notice aaw-notice-warning">این گزارش نیازمند فعال بودن ووکامرس است.</div>
<?php endif; ?>

<div class="aaw-row aaw-row-2">
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>پرفروش‌ترین محصولات</h2>
		</div>
		<?php if ( empty( $products ) ) : ?>
			<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
		<?php else : ?>
			<div class="aaw-table-scroll">
				<table class="aaw-table">
					<thead>
						<tr>
							<th>محصول</th>
							<th>تعداد فروش</th>
							<th>درآمد</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $products as $p ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $p['product_id'] ) ); ?>"><?php echo esc_html( $p['name'] ); ?></a>
								</td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $p['quantity'] ) ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_money( $p['revenue'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>دسته‌بندی‌های پرفروش</h2>
		</div>
		<?php if ( empty( $categories ) ) : ?>
			<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
		<?php else : ?>
			<div class="aaw-table-scroll">
				<table class="aaw-table">
					<thead>
						<tr>
							<th>دسته‌بندی</th>
							<th>تعداد فروش</th>
							<th>درآمد</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $categories as $c ) : ?>
							<tr>
								<td><?php echo esc_html( $c['name'] ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $c['quantity'] ) ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_money( $c['revenue'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
