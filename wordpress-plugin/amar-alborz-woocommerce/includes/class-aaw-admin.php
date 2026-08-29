<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * مدیریت منوها، صفحات پیشخوان، تنظیمات، خروجی CSV و بازنشانی آمار.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Admin {

	const CAPABILITY      = 'manage_options';
	const PAGE_DASHBOARD  = 'aaw-dashboard';
	const PAGE_REPORTS    = 'aaw-reports';
	const PAGE_PRO_TOOLS  = 'aaw-pro-tools';
	const PAGE_SETTINGS   = 'aaw-settings';

	private static $page_hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_aaw_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_aaw_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		add_action( 'admin_post_aaw_reset_stats', array( __CLASS__, 'handle_reset_stats' ) );
		add_action( 'admin_post_aaw_mark_alerts_read', array( __CLASS__, 'handle_mark_alerts_read' ) );
	}

	/**
	 * ثبت منوی افزونه در پیشخوان. منوی «آموزش» جداگانه توسط AAW_Education ثبت می‌شود.
	 */
	public static function add_menu() {
		self::$page_hooks['dashboard'] = add_menu_page(
			'آمار البرز',
			'آمار البرز',
			self::CAPABILITY,
			self::PAGE_DASHBOARD,
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-chart-area',
			26
		);

		self::$page_hooks['dashboard_sub'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			'داشبورد آمار البرز',
			'داشبورد',
			self::CAPABILITY,
			self::PAGE_DASHBOARD,
			array( __CLASS__, 'render_dashboard_page' )
		);

		self::$page_hooks['reports'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			'گزارش‌های آمار البرز',
			'گزارش‌ها',
			self::CAPABILITY,
			self::PAGE_REPORTS,
			array( __CLASS__, 'render_reports_page' )
		);

		self::$page_hooks['pro_tools'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			'ابزار حرفه‌ای آمار البرز',
			'ابزار حرفه‌ای',
			self::CAPABILITY,
			self::PAGE_PRO_TOOLS,
			array( __CLASS__, 'render_pro_tools_page' )
		);

		self::$page_hooks['settings'] = add_submenu_page(
			self::PAGE_DASHBOARD,
			'تنظیمات آمار البرز',
			'تنظیمات',
			self::CAPABILITY,
			self::PAGE_SETTINGS,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function get_page_hooks() {
		return self::$page_hooks;
	}

	/**
	 * بارگذاری فایل‌های CSS/JS فقط در صفحات مربوط به این افزونه.
	 */
	public static function enqueue_assets( $hook ) {
		$own_hooks = array_merge( array_values( self::$page_hooks ), array_values( AAW_Education::get_page_hooks() ) );

		if ( ! in_array( $hook, $own_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'aaw-admin', AAW_PLUGIN_URL . 'assets/css/admin.css', array(), AAW_VERSION );
		wp_enqueue_script( 'aaw-chartjs', AAW_PLUGIN_URL . 'assets/js/chart.umd.min.js', array(), '4.4.4', true );
		wp_enqueue_script( 'aaw-admin', AAW_PLUGIN_URL . 'assets/js/admin.js', array( 'aaw-chartjs' ), AAW_VERSION, true );

		if ( $hook === self::$page_hooks['pro_tools'] ) {
			wp_enqueue_script( 'aaw-heatmap-viewer', AAW_PLUGIN_URL . 'assets/js/heatmap-viewer.js', array(), AAW_VERSION, true );
			wp_enqueue_script( 'aaw-replay-player', AAW_PLUGIN_URL . 'assets/js/session-replay-player.js', array(), AAW_VERSION, true );
		}

		wp_localize_script(
			'aaw-admin',
			'aawAdmin',
			array(
				'themeDefault' => self::get_settings()['theme_default'],
			)
		);
	}

	/**
	 * دریافت تنظیمات ذخیره‌شده با مقادیر پیش‌فرض.
	 */
	public static function get_settings() {
		$defaults = array(
			'exclude_staff'               => 1,
			'session_timeout'             => 30,
			'excluded_ips'                => '',
			'retention_days'               => 0,
			'delete_on_uninstall'         => 0,
			'cart_abandon_hours'          => 2,
			'theme_default'               => 'dark',
			'alerts_enabled'              => 1,
			'alert_email_enabled'         => 0,
			'alert_conversion_drop'       => 20,
			'alert_bounce_increase'       => 25,
			'alert_revenue_drop'          => 20,
			'alert_cart_abandon_increase' => 30,
			'heatmap_enabled'             => 0,
			'replay_enabled'              => 0,
		);

		$saved = get_option( 'aaw_settings', array() );
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * تعیین بازه‌ی تاریخی بر اساس پارامترهای درخواست (پیش‌فرض یا سفارشی).
	 */
	public static function resolve_date_range() {
		$today = current_time( 'Y-m-d' );
		$range = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7days'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
				$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$to   = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
			'mobile'     => '#4a90f7',
			'tablet'     => '#22c9a8',
			'desktop'    => '#f59e0b',
		);
	}

	/**
	 * تولید نشانگر تولتیپ فارسی برای کنار گزینه‌های پیچیده (کاملاً CSS، بدون نیاز به جاوااسکریپت).
	 */
	public static function tooltip( $text ) {
		return sprintf(
			'<span class="aaw-tooltip" tabindex="0"><span class="aaw-tooltip-icon" aria-hidden="true">؟</span><span class="aaw-tooltip-bubble">%s</span></span>',
			esc_html( $text )
		);
	}

	/**
	 * ساختار گروه‌بندی‌شده‌ی تب‌های صفحه‌ی «گزارش‌ها».
	 */
	public static function get_reports_tab_groups() {
		return array(
			'بازدیدکنندگان' => array(
				'sources'  => array( 'label' => 'منابع ورودی', 'icon' => '🌐' ),
				'audience' => array( 'label' => 'جغرافیا، دستگاه و مرورگر', 'icon' => '📍' ),
			),
			'فروش'           => array(
				'funnel'       => array( 'label' => 'قیف فروش', 'icon' => '🧭' ),
				'products'     => array( 'label' => 'محصولات و دسته‌ها', 'icon' => '📦' ),
				'revenue'      => array( 'label' => 'درآمد و تبدیل', 'icon' => '💰' ),
				'cart-abandon' => array( 'label' => 'سبدهای رها شده', 'icon' => '🛒' ),
			),
		);
	}

	/**
	 * ساختار گروه‌بندی‌شده‌ی تب‌های صفحه‌ی «ابزار حرفه‌ای» (نسخه تجاری).
	 */
	public static function get_pro_tools_tab_groups() {
		return array(
			'رفتار کاربر' => array(
				'heatmap' => array( 'label' => 'Heatmap', 'icon' => '🔥' ),
				'replay'  => array( 'label' => 'Session Replay', 'icon' => '🎬' ),
			),
			'هوشمند'       => array(
				'alerts'     => array( 'label' => 'هشدار هوشمند', 'icon' => '🔔' ),
				'utm'        => array( 'label' => 'گزارش UTM', 'icon' => '🏷' ),
				'ab-testing' => array( 'label' => 'A/B Testing', 'icon' => '🧪' ),
			),
		);
	}

	/**
	 * ساختار تب‌های صفحه‌ی «تنظیمات».
	 */
	public static function get_settings_tabs() {
		return array(
			'general'    => array( 'label' => 'عمومی', 'icon' => '⚙' ),
			'privacy'    => array( 'label' => 'حریم‌خصوصی و نگهداری داده', 'icon' => '🔒' ),
			'alerts'     => array( 'label' => 'هشدارها', 'icon' => '🔔' ),
			'appearance' => array( 'label' => 'ظاهر (دارک/لایت)', 'icon' => '🎨' ),
			'about'      => array( 'label' => 'درباره افزونه', 'icon' => 'ℹ' ),
		);
	}

	public static function current_tab( $groups_or_tabs, $default ) {
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$flat_keys = array();
		foreach ( $groups_or_tabs as $key => $value ) {
			if ( is_array( $value ) && isset( $value['label'] ) ) {
				$flat_keys[] = $key;
			} elseif ( is_array( $value ) ) {
				$flat_keys = array_merge( $flat_keys, array_keys( $value ) );
			}
		}

		return in_array( $requested, $flat_keys, true ) ? $requested : $default;
	}

	/* ===================== رندر صفحات ===================== */

	public static function render_dashboard_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		list( $from, $to, $range ) = self::resolve_date_range();
		list( $prev_from, $prev_to ) = AAW_DB::get_previous_range( $from, $to );

		$visitors      = AAW_DB::get_total( $from, $to );
		$prev_visitors = AAW_DB::get_total( $prev_from, $prev_to );
		$bounce_rate   = AAW_DB::get_bounce_rate( $from, $to );

		$woo_active = AAW_WooCommerce::is_active();
		$revenue    = $woo_active ? AAW_WooCommerce::get_revenue_summary( $from, $to ) : array( 'orders_count' => 0, 'gross_revenue' => 0, 'refunded_total' => 0, 'net_revenue' => 0, 'aov' => 0 );
		$prev_revenue = $woo_active ? AAW_WooCommerce::get_revenue_summary( $prev_from, $prev_to ) : array( 'gross_revenue' => 0, 'orders_count' => 0 );
		$conversion_rate = $woo_active ? AAW_WooCommerce::get_conversion_rate( $from, $to, $visitors ) : 0;
		$prev_conversion = $woo_active ? AAW_WooCommerce::get_conversion_rate( $prev_from, $prev_to, $prev_visitors ) : 0;

		$funnel_report = AAW_Funnel::build_report( $from, $to, $visitors );
		$breakdown     = AAW_DB::get_breakdown_by_source( $from, $to );
		$top_sources   = array_slice( $breakdown, 0, 4 );
		$abandoned     = AAW_DB::get_abandoned_summary( $from, $to );
		$alerts        = AAW_DB::get_recent_alerts( 5 );
		$unread_alerts = AAW_DB::get_unread_alerts_count();

		$visitors_change   = self::calc_percent_change( $visitors, $prev_visitors );
		$revenue_change    = self::calc_percent_change( $revenue['gross_revenue'], $prev_revenue['gross_revenue'] );
		$conversion_change = self::calc_percent_change( $conversion_rate, $prev_conversion );

		include AAW_PLUGIN_DIR . 'templates/dashboard-page.php';
	}

	public static function render_reports_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		$tab_groups = self::get_reports_tab_groups();
		$tab        = self::current_tab( $tab_groups, 'sources' );

		list( $from, $to, $range ) = self::resolve_date_range();
		list( $prev_from, $prev_to ) = AAW_DB::get_previous_range( $from, $to );

		include AAW_PLUGIN_DIR . 'templates/reports-page.php';
	}

	public static function render_pro_tools_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		$tab_groups = self::get_pro_tools_tab_groups();
		$tab        = self::current_tab( $tab_groups, 'heatmap' );

		list( $from, $to, $range ) = self::resolve_date_range();
		list( $prev_from, $prev_to ) = AAW_DB::get_previous_range( $from, $to );

		include AAW_PLUGIN_DIR . 'templates/pro-tools-page.php';
	}

	public static function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		$settings_tabs = self::get_settings_tabs();
		$tab           = self::current_tab( $settings_tabs, 'general' );
		$settings      = self::get_settings();
		$saved         = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
		$reset         = isset( $_GET['reset'] ) && '1' === $_GET['reset'];

		include AAW_PLUGIN_DIR . 'templates/settings-page.php';
	}

	/* ===================== پردازش فرم‌ها ===================== */

	public static function handle_save_settings() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'aaw_save_settings' );

		$settings = array(
			'exclude_staff'               => ! empty( $_POST['exclude_staff'] ) ? 1 : 0,
			'session_timeout'             => isset( $_POST['session_timeout'] ) ? max( 1, (int) $_POST['session_timeout'] ) : 30,
			'excluded_ips'                => isset( $_POST['excluded_ips'] ) ? sanitize_textarea_field( wp_unslash( $_POST['excluded_ips'] ) ) : '',
			'retention_days'               => isset( $_POST['retention_days'] ) ? max( 0, (int) $_POST['retention_days'] ) : 0,
			'delete_on_uninstall'         => ! empty( $_POST['delete_on_uninstall'] ) ? 1 : 0,
			'cart_abandon_hours'          => isset( $_POST['cart_abandon_hours'] ) ? max( 1, (int) $_POST['cart_abandon_hours'] ) : 2,
			'theme_default'               => isset( $_POST['theme_default'] ) && 'light' === $_POST['theme_default'] ? 'light' : 'dark',
			'alerts_enabled'              => ! empty( $_POST['alerts_enabled'] ) ? 1 : 0,
			'alert_email_enabled'         => ! empty( $_POST['alert_email_enabled'] ) ? 1 : 0,
			'alert_conversion_drop'       => isset( $_POST['alert_conversion_drop'] ) ? max( 1, min( 100, (int) $_POST['alert_conversion_drop'] ) ) : 20,
			'alert_bounce_increase'       => isset( $_POST['alert_bounce_increase'] ) ? max( 1, min( 200, (int) $_POST['alert_bounce_increase'] ) ) : 25,
			'alert_revenue_drop'          => isset( $_POST['alert_revenue_drop'] ) ? max( 1, min( 100, (int) $_POST['alert_revenue_drop'] ) ) : 20,
			'alert_cart_abandon_increase' => isset( $_POST['alert_cart_abandon_increase'] ) ? max( 1, min( 200, (int) $_POST['alert_cart_abandon_increase'] ) ) : 30,
			'heatmap_enabled'             => ! empty( $_POST['heatmap_enabled'] ) ? 1 : 0,
			'replay_enabled'              => ! empty( $_POST['replay_enabled'] ) ? 1 : 0,
		);

		update_option( 'aaw_settings', $settings );

		$redirect_tab = isset( $_POST['current_tab'] ) ? sanitize_key( wp_unslash( $_POST['current_tab'] ) ) : 'general';

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SETTINGS, 'tab' => $redirect_tab, 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_reset_stats() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'aaw_reset_stats' );

		AAW_DB::truncate_all();

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SETTINGS, 'tab' => 'privacy', 'reset' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_mark_alerts_read() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'aaw_mark_alerts_read' );

		AAW_DB::mark_all_alerts_read();

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_PRO_TOOLS, 'tab' => 'alerts' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * خروجی گرفتن از گزارش تفکیکی روزانه‌ی منابع ورودی به‌صورت CSV.
	 */
	public static function handle_export_csv() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم را ندارید.' );
		}

		check_admin_referer( 'aaw_export_csv' );

		list( $from, $to ) = self::resolve_date_range();

		$daily_table = AAW_DB::get_daily_breakdown_table( $from, $to );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=amar-alborz-' . $from . '-to-' . $to . '.csv' );

		$output = fopen( 'php://output', 'w' );
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
