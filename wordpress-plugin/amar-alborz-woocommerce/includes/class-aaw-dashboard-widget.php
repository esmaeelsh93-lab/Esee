<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * ویجت خلاصه‌ی آمار البرز در صفحه‌ی اصلی داشبورد وردپرس.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Dashboard_Widget {

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	public static function register_widget() {
		if ( ! current_user_can( AAW_Admin::CAPABILITY ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'aaw_dashboard_widget',
			'آمار البرز',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		$today     = current_time( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
		$week_from = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );

		$today_visitors     = AAW_DB::get_total( $today, $today );
		$yesterday_visitors = AAW_DB::get_total( $yesterday, $yesterday );
		$week_visitors      = AAW_DB::get_total( $week_from, $today );

		$woo_active = AAW_WooCommerce::is_active();
		$week_revenue = $woo_active ? AAW_WooCommerce::get_revenue_summary( $week_from, $today ) : array( 'orders_count' => 0, 'gross_revenue' => 0 );

		$top_sources = array_slice( AAW_DB::get_breakdown_by_source( $week_from, $today ), 0, 4 );
		$colors      = AAW_Admin::get_chart_colors();
		?>
		<div class="aaw-widget" dir="rtl" style="direction:rtl; text-align:right; background:#0f1626; margin:-12px; padding:18px; border-radius:0 0 4px 4px;">
			<div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
				<div style="flex:1; min-width:90px; background:#1a2338; border:1px solid #2a3552; border-radius:10px; padding:12px; text-align:center;">
					<div style="color:#8b95b3; font-size:12px; margin-bottom:6px;">بازدید امروز</div>
					<div style="color:#fff; font-size:20px; font-weight:800;"><?php echo esc_html( AAW_Jalali::format_number( $today_visitors ) ); ?></div>
				</div>
				<div style="flex:1; min-width:90px; background:#1a2338; border:1px solid #2a3552; border-radius:10px; padding:12px; text-align:center;">
					<div style="color:#8b95b3; font-size:12px; margin-bottom:6px;">بازدید دیروز</div>
					<div style="color:#fff; font-size:20px; font-weight:800;"><?php echo esc_html( AAW_Jalali::format_number( $yesterday_visitors ) ); ?></div>
				</div>
				<div style="flex:1; min-width:90px; background:#1a2338; border:1px solid #2a3552; border-radius:10px; padding:12px; text-align:center;">
					<div style="color:#8b95b3; font-size:12px; margin-bottom:6px;">سفارش ۷ روز اخیر</div>
					<div style="color:#fff; font-size:20px; font-weight:800;"><?php echo esc_html( AAW_Jalali::format_number( $week_revenue['orders_count'] ) ); ?></div>
				</div>
			</div>

			<?php if ( ! empty( $top_sources ) ) : ?>
				<div style="color:#8b95b3; font-size:12px; margin-bottom:8px;">برترین منابع ورودی (۷ روز اخیر)</div>
				<div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
					<?php foreach ( $top_sources as $row ) : ?>
						<?php $color = isset( $colors[ $row->source_key ] ) ? $colors[ $row->source_key ] : '#64748b'; ?>
						<div style="display:flex; align-items:center; justify-content:space-between; font-size:13px; color:#e7ebf5;">
							<span style="display:flex; align-items:center; gap:8px;">
								<span style="width:10px; height:10px; border-radius:3px; background:<?php echo esc_attr( $color ); ?>; display:inline-block;"></span>
								<?php echo esc_html( $row->source_label ); ?>
							</span>
							<strong><?php echo esc_html( AAW_Jalali::format_number( $row->total ) ); ?></strong>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p style="color:#8b95b3;">هنوز داده‌ای ثبت نشده است.</p>
			<?php endif; ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_DASHBOARD ) ); ?>"
				style="display:inline-block; background:linear-gradient(135deg,#4a90f7,#2563d9); color:#fff; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;">
				مشاهده داشبورد کامل
			</a>
		</div>
		<?php
	}
}
