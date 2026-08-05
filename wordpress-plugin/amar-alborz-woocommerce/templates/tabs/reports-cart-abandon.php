<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «سبدهای رها شده»: نمایش مراحل رها شدن خرید و محصولات واقعی داخل سبدهای رها شده.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$summary        = AAW_DB::get_abandoned_summary( $from, $to );
$top_products   = AAW_DB::get_abandoned_top_products( $from, $to, 8 );
$recent_carts   = AAW_DB::get_abandoned_carts( $from, $to, 15 );
$total_visitors = AAW_DB::get_total( $from, $to );
$report         = AAW_Funnel::build_report( $from, $to, $total_visitors );
?>
<div class="aaw-kpi-grid aaw-kpi-grid-3">
	<div class="aaw-kpi-card aaw-kpi-amber">
		<div class="aaw-kpi-label">تعداد سبدهای رها شده</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_number( $summary['total'] ) ); ?></div>
	</div>
	<div class="aaw-kpi-card aaw-kpi-rose">
		<div class="aaw-kpi-label">ارزش ازدست‌رفته</div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::format_money( $summary['total_value'] ) ); ?></div>
	</div>
	<div class="aaw-kpi-card aaw-kpi-slate">
		<div class="aaw-kpi-label">زمان تشخیص رهاشدن <?php echo AAW_Admin::tooltip( 'مدت‌زمانی که پس از آخرین تغییر سبد، بدون ثبت سفارش، سبد را «رها شده» در نظر می‌گیریم؛ از تنظیمات قابل تغییر است.' ); ?></div>
		<div class="aaw-kpi-value"><?php echo esc_html( AAW_Jalali::to_persian_digits( AAW_Admin::get_settings()['cart_abandon_hours'] ) ); ?> ساعت</div>
	</div>
</div>

<div class="aaw-panel">
	<div class="aaw-panel-header">
		<h2>مراحل رها شدن خرید</h2>
		<span class="aaw-panel-sub">میزان افت واقعی کاربران در هر مرحله از قیف فروش</span>
	</div>
	<div class="aaw-funnel-flow aaw-funnel-flow-compact">
		<?php foreach ( $report['stages'] as $i => $stage ) : ?>
			<?php if ( $i > 0 ) : ?><div class="aaw-funnel-arrow" aria-hidden="true">←</div><?php endif; ?>
			<div class="aaw-funnel-mini-stage">
				<div class="aaw-funnel-mini-icon" aria-hidden="true"><?php echo esc_html( $stage['icon'] ); ?></div>
				<div class="aaw-funnel-mini-count"><?php echo esc_html( AAW_Jalali::format_number( $stage['count'] ) ); ?></div>
				<div class="aaw-funnel-mini-label"><?php echo esc_html( $stage['label'] ); ?></div>
				<?php if ( $i > 0 && $stage['drop'] > 0 ) : ?>
					<div class="aaw-funnel-mini-drop">افت <?php echo esc_html( AAW_Jalali::format_number( $stage['drop'] ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="aaw-row aaw-row-2">
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>محصولاتی که بیشتر در سبد رها می‌شوند</h2>
		</div>
		<?php if ( empty( $top_products ) ) : ?>
			<div class="aaw-empty-state">داده‌ای برای نمایش وجود ندارد</div>
		<?php else : ?>
			<div class="aaw-table-scroll">
				<table class="aaw-table">
					<thead>
						<tr>
							<th>محصول</th>
							<th>تعداد سبد</th>
							<th>مجموع تعداد</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_products as $p ) : ?>
							<tr>
								<td><?php echo esc_html( $p['name'] ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $p['count'] ) ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $p['quantity'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>سبدهای رها شده‌ی اخیر</h2>
		</div>
		<?php if ( empty( $recent_carts ) ) : ?>
			<div class="aaw-empty-state">هنوز سبد رها شده‌ای ثبت نشده است.</div>
		<?php else : ?>
			<div class="aaw-cart-list">
				<?php foreach ( $recent_carts as $cart ) : ?>
					<?php $items = json_decode( $cart->cart_contents, true ); ?>
					<div class="aaw-cart-card">
						<div class="aaw-cart-card-head">
							<span><?php echo esc_html( AAW_Jalali::format_datetime( $cart->updated_at ) ); ?></span>
							<strong><?php echo esc_html( AAW_Jalali::format_money( $cart->cart_total ) ); ?></strong>
						</div>
						<div class="aaw-cart-card-items">
							<?php foreach ( (array) $items as $item ) : ?>
								<span class="aaw-source-chip"><?php echo esc_html( isset( $item['name'] ) ? $item['name'] : '' ); ?> × <?php echo esc_html( AAW_Jalali::to_persian_digits( isset( $item['quantity'] ) ? $item['quantity'] : 1 ) ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
