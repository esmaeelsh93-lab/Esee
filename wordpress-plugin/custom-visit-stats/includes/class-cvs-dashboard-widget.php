<?php
/**
 * ویجت خلاصه‌ی آمار بازدید در صفحه‌ی اصلی داشبورد وردپرس.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Dashboard_Widget {

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register_widget' ) );
	}

	public static function register_widget() {
		if ( ! current_user_can( CVS_Admin::CAPABILITY ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cvs_dashboard_widget',
			'آمار بازدید سایت',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		$today     = current_time( 'Y-m-d' );

		$today_total     = CVS_DB::get_total( $today, $today );
		$week_from       = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
		$week_total      = CVS_DB::get_total( $week_from, $today );
		$unique_total    = CVS_DB::get_unique_visitors( $today, $today );
		$online_count    = CVS_DB::get_online_count();

		$top_sources = array_slice( CVS_DB::get_breakdown_by_source( $week_from, $today ), 0, 4 );
		$colors      = CVS_Admin::get_chart_colors();

		?>
		<div class="cvs-widget" dir="rtl" style="direction:rtl; text-align:right; background:#f8fafc; margin:-12px; padding:18px; border-radius:0 0 4px 4px; font-family:Vazirmatn,Tahoma,sans-serif;">
			<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px;">
				<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; text-align:center;">
					<div style="color:#64748b; font-size:12px; margin-bottom:6px;">بازدید امروز</div>
					<div style="color:#0f172a; font-size:20px; font-weight:800;"><?php echo esc_html( CVS_Jalali::format_number( $today_total ) ); ?></div>
				</div>
				<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; text-align:center;">
					<div style="color:#64748b; font-size:12px; margin-bottom:6px;">بازدیدکننده یکتا</div>
					<div style="color:#0f172a; font-size:20px; font-weight:800;"><?php echo esc_html( CVS_Jalali::format_number( $unique_total ) ); ?></div>
				</div>
				<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; text-align:center;">
					<div style="color:#64748b; font-size:12px; margin-bottom:6px;">آنلاین</div>
					<div style="color:#10b981; font-size:20px; font-weight:800;"><?php echo esc_html( CVS_Jalali::format_number( $online_count ) ); ?></div>
				</div>
			</div>

			<?php if ( ! empty( $top_sources ) ) : ?>
				<div style="display:flex; align-items:center; justify-content:space-between; color:#64748b; font-size:12px; margin-bottom:8px;"><span>برترین منابع (۷ روز اخیر)</span><strong style="color:#0f172a;"><?php echo esc_html( CVS_Jalali::format_number( $week_total ) ); ?> بازدید</strong></div>
				<div style="display:flex; flex-direction:column; gap:8px; margin-bottom:16px;">
					<?php foreach ( $top_sources as $row ) : ?>
						<?php $color = isset( $colors[ $row->source_key ] ) ? $colors[ $row->source_key ] : '#64748b'; ?>
						<div style="display:flex; align-items:center; justify-content:space-between; font-size:13px; color:#0f172a;">
							<span style="display:flex; align-items:center; gap:8px;">
								<span style="width:10px; height:10px; border-radius:3px; background:<?php echo esc_attr( $color ); ?>; display:inline-block;"></span>
								<?php echo esc_html( $row->source_label ); ?>
							</span>
							<strong><?php echo esc_html( CVS_Jalali::format_number( $row->total ) ); ?></strong>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p style="color:#64748b;">هنوز داده‌ای ثبت نشده است.</p>
			<?php endif; ?>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CVS_Admin::PAGE_STATS ) ); ?>"
				style="display:inline-block; background:#4f46e5; color:#fff; padding:9px 16px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;">
				مشاهده گزارش کامل
			</a>
		</div>
		<?php
	}
}
