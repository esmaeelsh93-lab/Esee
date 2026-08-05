<?php
/**
 * Plugin Name:       آمار بازدید سایت (Custom Visit Stats)
 * Plugin URI:        https://github.com/esmaeelsh93-lab/Esee
 * Description:       افزونه اختصاصی آمار بازدید که تعداد ورودی‌های سایت را بر اساس تاریخ و منبع ارجاع (گوگل، اینستاگرام، ایکس، تلگرام، مستقیم و ...) در پیشخوان وردپرس نمایش می‌دهد.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Esee
 * Text Domain:       custom-visit-stats
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // دسترسی مستقیم مجاز نیست.
}

define( 'CVS_VERSION', '1.0.0' );
define( 'CVS_DB_VERSION', '1.0' );
define( 'CVS_PLUGIN_FILE', __FILE__ );
define( 'CVS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CVS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CVS_TABLE_NAME', 'cvs_visits' );

require_once CVS_PLUGIN_DIR . 'includes/class-cvs-jalali.php';
require_once CVS_PLUGIN_DIR . 'includes/class-cvs-db.php';
require_once CVS_PLUGIN_DIR . 'includes/class-cvs-source-detector.php';
require_once CVS_PLUGIN_DIR . 'includes/class-cvs-tracker.php';
require_once CVS_PLUGIN_DIR . 'includes/class-cvs-admin.php';
require_once CVS_PLUGIN_DIR . 'includes/class-cvs-dashboard-widget.php';

/**
 * راه‌اندازی فعال‌سازی افزونه: ساخت جدول دیتابیس و مقادیر پیش‌فرض تنظیمات.
 */
function cvs_activate_plugin() {
	CVS_DB::create_table();

	$defaults = array(
		'exclude_staff'      => 1,
		'session_timeout'    => 30,
		'excluded_ips'       => '',
		'retention_days'     => 0,
		'delete_on_uninstall'=> 0,
	);
	if ( false === get_option( 'cvs_settings' ) ) {
		add_option( 'cvs_settings', $defaults );
	}

	if ( false === get_option( 'cvs_salt' ) ) {
		add_option( 'cvs_salt', wp_generate_password( 32, false ) );
	}

	update_option( 'cvs_db_version', CVS_DB_VERSION );

	if ( ! wp_next_scheduled( 'cvs_daily_cleanup_event' ) ) {
		wp_schedule_event( time(), 'daily', 'cvs_daily_cleanup_event' );
	}
}
register_activation_hook( CVS_PLUGIN_FILE, 'cvs_activate_plugin' );

/**
 * راه‌اندازی غیرفعال‌سازی افزونه: فقط زمان‌بندی پاک‌سازی حذف می‌شود، داده‌ها باقی می‌مانند.
 */
function cvs_deactivate_plugin() {
	$timestamp = wp_next_scheduled( 'cvs_daily_cleanup_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'cvs_daily_cleanup_event' );
	}
}
register_deactivation_hook( CVS_PLUGIN_FILE, 'cvs_deactivate_plugin' );

add_action( 'cvs_daily_cleanup_event', array( 'CVS_DB', 'run_scheduled_cleanup' ) );

/**
 * بررسی نسخه دیتابیس هنگام آپدیت افزونه.
 */
function cvs_maybe_upgrade_db() {
	if ( get_option( 'cvs_db_version' ) !== CVS_DB_VERSION ) {
		CVS_DB::create_table();
		update_option( 'cvs_db_version', CVS_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'cvs_maybe_upgrade_db' );

/**
 * راه‌اندازی کلاس‌های اصلی افزونه.
 */
function cvs_init_plugin() {
	CVS_Tracker::init();
	CVS_Admin::init();
	CVS_Dashboard_Widget::init();
}
add_action( 'plugins_loaded', 'cvs_init_plugin' );
