<?php
/**
 * مدیریت منوها، صفحات پیشخوان، تنظیمات، خروجی CSV و بازنشانی آمار.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Admin {

	const CAPABILITY   = 'manage_options';
	const PAGE_STATS   = 'cvs-stats';
	const PAGE_SETTINGS = 'cvs-settings';

	/**
	 * hook suffix واقعی صفحات که وردپرس برمی‌گرداند (چون عنوان منو فارسی است،
	 * نمی‌توان صرفاً با جست‌وجوی رشته‌ی اسلاگ در $hook_suffix آن را تشخیص داد).
	 */
	private static $stats_hook;
	private static $settings_hook;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_cvs_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_cvs_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		add_action( 'admin_post_cvs_reset_stats', array( __CLASS__, 'handle_reset_stats' ) );
	}

	/**
	 * ثبت منوی افزونه در پیشخوان.
	 */
	public static function add_menu() {
		self::$stats_hook = add_menu_page(
			'آمار بازدید سایت',
			'آمار بازدید',
			self::CAPABILITY,
			self::PAGE_STATS,
			array( __CLASS__, 'render_stats_page' ),
			'dashicons-chart-bar',
			26
		);

		add_submenu_page(
			self::PAGE_STATS,
			'آمار بازدید سایت',
			'گزارش آمار',
			self::CAPABILITY,
			self::PAGE_STATS,
			array( __CLASS__, 'render_stats_page' )
		);

		self::$settings_hook = add_submenu_page(
			self::PAGE_STATS,
			'تنظیمات آمار بازدید',
			'تنظیمات',
			self::CAPABILITY,
			self::PAGE_SETTINGS,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * بارگذاری فایل‌های CSS/JS فقط در صفحات مربوط به این افزونه.
	 *
	 * توجه: چون عنوان منو فارسی است، وردپرس هوک‌نیم صفحات زیرمنو را از روی
	 * عنوانِ سنیتایزشده‌ی منو می‌سازد، نه اسلاگ آن؛ بنابراین باید hook_suffix
	 * دقیقی که خودِ add_menu_page/add_submenu_page برگردانده‌اند را مقایسه کنیم.
	 */
	public static function enqueue_assets( $hook ) {
		if ( $hook !== self::$stats_hook && $hook !== self::$settings_hook ) {
			return;
		}

		wp_enqueue_style( 'cvs-admin', CVS_PLUGIN_URL . 'assets/css/admin.css', array(), CVS_VERSION );

		if ( $hook === self::$stats_hook ) {
			wp_enqueue_script( 'cvs-chartjs', CVS_PLUGIN_URL . 'assets/js/chart.umd.min.js', array(), '4.4.4', true );
			wp_enqueue_script( 'cvs-admin', CVS_PLUGIN_URL . 'assets/js/admin.js', array( 'cvs-chartjs' ), CVS_VERSION, true );
		}
	}

	/**
	 * دریافت تنظیمات ذخیره‌شده با مقادیر پیش‌فرض.
	 */
	public static function get_settings() {
		$defaults = array(
			'exclude_staff'       => 1,
			'session_timeout'     => 30,
			'excluded_ips'        => '',
			'retention_days'      => 0,
			'delete_on_uninstall' => 0,
		);

		$saved = get_option( 'cvs_settings', array() );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * تعیین بازه‌ی تاریخی بر اساس پارامترهای درخواست (پیش‌فرض یا سفارشی).
	 */
	private static function resolve_date_range() {
		$today = current_time( 'Y-m-d' );
		$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7days';

		$from = $today;
		$to   = $today;

		switch ( $range ) {
			case 'today':
				$from = $today;
				$to   = $today;
				break;
			case 'yesterday':
				$from = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
				$to   = $from;
				break;
			case '7days':
				$from = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
				$to   = $today;
				break;
			case '30days':
				$from = gmdate( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) );
				$to   = $today;
				break;
			case 'this_month':
				$from = gmdate( 'Y-m-01', strtotime( $today ) );
				$to   = $today;
				break;
			case 'last_month':
				$first_this_month = strtotime( gmdate( 'Y-m-01', strtotime( $today ) ) );
				$last_month_end   = strtotime( '-1 day', $first_this_month );
				$from             = gmdate( 'Y-m-01', $last_month_end );
				$to               = gmdate( 'Y-m-d', $last_month_end );
				break;
			case 'custom':
				$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $today;
				$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $today;
				if ( ! self::is_valid_date( $from ) ) {
					$from = $today;
				}
				if ( ! self::is_valid_date( $to ) ) {
					$to = $today;
				}
				if ( $from > $to ) {
					list( $from, $to ) = array( $to, $from );
				}
				break;
			default:
				$range = '7days';
				$from  = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
				$to    = $today;
				break;
		}

		return array( $from, $to, $range );
	}

	/**
	 * گزینه‌های بازه‌ی زمانی برای نمایش به‌صورت دکمه‌های فیلتر.
	 */
	public static function get_range_options() {
		return array(
			'today'      => 'امروز',
			'yesterday'  => 'دیروز',
			'7days'      => '۷ روز اخیر',
			'30days'     => '۳۰ روز اخیر',
			'this_month' => 'این ماه',
			'last_month' => 'ماه قبل',
			'custom'     => 'بازه دلخواه',
		);
	}

	private static function is_valid_date( $date ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}

	/**
	 * رندر صفحه‌ی اصلی گزارش آمار.
	 */
	public static function render_stats_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		list( $from, $to, $range ) = self::resolve_date_range();
		list( $prev_from, $prev_to ) = CVS_DB::get_previous_range( $from, $to );

		$total           = CVS_DB::get_total( $from, $to );
		$prev_total      = CVS_DB::get_total( $prev_from, $prev_to );
		$breakdown       = CVS_DB::get_breakdown_by_source( $from, $to );
		$daily_series    = CVS_DB::get_daily_series( $from, $to );
		$daily_table     = CVS_DB::get_daily_breakdown_table( $from, $to );
		$other_referrers = CVS_DB::get_other_referrers( $from, $to );

		$days_count    = max( 1, count( $daily_series['dates'] ) );
		$daily_average = round( $total / $days_count, 1 );
		$prev_average  = round( $prev_total / $days_count, 1 );

		$active_sources = count( $breakdown );

		$top_source      = ! empty( $breakdown ) ? $breakdown[0] : null;
		$top_source_prev = $top_source ? CVS_DB::get_source_total( $prev_from, $prev_to, $top_source->source_key ) : 0;

		$best_day = null;
		foreach ( $daily_table as $day ) {
			if ( null === $best_day || $day['total'] > $best_day['total'] ) {
				$best_day = $day;
			}
		}

		$total_change   = self::calc_percent_change( $total, $prev_total );
		$average_change = self::calc_percent_change( $daily_average, $prev_average );
		$top_change     = $top_source ? self::calc_percent_change( (int) $top_source->total, $top_source_prev ) : array( 'percent' => 0, 'direction' => 'up' );

		$top_sources_ranked = array_slice( $breakdown, 0, 4 );
		$ranked_with_change = array();
		foreach ( $top_sources_ranked as $row ) {
			$prev_count           = CVS_DB::get_source_total( $prev_from, $prev_to, $row->source_key );
			$ranked_with_change[] = array(
				'key'    => $row->source_key,
				'label'  => $row->source_label,
				'total'  => (int) $row->total,
				'change' => self::calc_percent_change( (int) $row->total, $prev_count ),
			);
		}

		$chart_colors = self::get_chart_colors();

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
				return CVS_Jalali::format( $date, 'short' );
			},
			$daily_series['dates']
		);

		wp_localize_script(
			'cvs-admin',
			'cvsChartData',
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

		include CVS_PLUGIN_DIR . 'templates/admin-page.php';
	}

	/**
	 * محاسبه‌ی درصد تغییر بین دو مقدار به همراه جهت (صعودی/نزولی).
	 */
	public static function calc_percent_change( $current, $previous ) {
		if ( $previous > 0 ) {
			$percent = round( ( ( $current - $previous ) / $previous ) * 100 );
		} else {
			$percent = $current > 0 ? 100 : 0;
		}

		return array(
			'percent'   => abs( $percent ),
			'direction' => $percent < 0 ? 'down' : 'up',
		);
	}

	/**
	 * پالت رنگ ثابت برای نمایش یکسان منابع در نمودارها.
	 */
	public static function get_chart_colors() {
		return array(
			'google'     => '#4285F4',
			'instagram'  => '#E1306C',
			'x'          => '#111827',
			'facebook'   => '#1877F2',
			'telegram'   => '#26A5E4',
			'whatsapp'   => '#25D366',
			'eitaa'      => '#00A99D',
			'bale'       => '#3EB985',
			'rubika'     => '#F8A51B',
			'aparat'     => '#F9A61A',
			'linkedin'   => '#0A66C2',
			'pinterest'  => '#E60023',
			'youtube'    => '#FF0000',
			'bing'       => '#008373',
			'yahoo'      => '#6001D2',
			'yandex'     => '#FF0000',
			'duckduckgo' => '#DE5833',
			'direct'     => '#6B7280',
			'other'      => '#9CA3AF',
			'ads'        => '#F59E0B',
			'sms'        => '#8B5CF6',
			'email'      => '#10B981',
		);
	}

	/**
	 * رندر صفحه‌ی تنظیمات.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		$settings = self::get_settings();
		$saved    = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
		$reset    = isset( $_GET['reset'] ) && '1' === $_GET['reset'];

		include CVS_PLUGIN_DIR . 'templates/settings-page.php';
	}

	/**
	 * پردازش ذخیره‌ی تنظیمات.
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'cvs_save_settings' );

		$settings = array(
			'exclude_staff'       => ! empty( $_POST['exclude_staff'] ) ? 1 : 0,
			'session_timeout'     => isset( $_POST['session_timeout'] ) ? max( 1, (int) $_POST['session_timeout'] ) : 30,
			'excluded_ips'        => isset( $_POST['excluded_ips'] ) ? sanitize_textarea_field( wp_unslash( $_POST['excluded_ips'] ) ) : '',
			'retention_days'      => isset( $_POST['retention_days'] ) ? max( 0, (int) $_POST['retention_days'] ) : 0,
			'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ) ? 1 : 0,
		);

		update_option( 'cvs_settings', $settings );

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SETTINGS, 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * پردازش بازنشانی (حذف کامل) آمار.
	 */
	public static function handle_reset_stats() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'cvs_reset_stats' );

		CVS_DB::truncate_all();

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SETTINGS, 'reset' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * خروجی گرفتن از گزارش تفکیکی روزانه به صورت CSV.
	 */
	public static function handle_export_csv() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'cvs_export_csv' );

		list( $from, $to ) = self::resolve_date_range();

		$daily_table = CVS_DB::get_daily_breakdown_table( $from, $to );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=visit-stats-' . $from . '-to-' . $to . '.csv' );

		$output = fopen( 'php://output', 'w' );
		// BOM برای نمایش صحیح حروف فارسی در اکسل.
		fwrite( $output, "\xEF\xBB\xBF" );

		fputcsv( $output, array( 'تاریخ', 'منبع', 'تعداد ورودی' ) );

		foreach ( $daily_table as $day ) {
			foreach ( $day['sources'] as $source ) {
				fputcsv( $output, array( $day['date'], $source['label'], $source['total'] ) );
			}
		}

		fclose( $output );
		exit;
	}
}
