<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * Plugin Name:       افزونه ووکامرسی آمار البرز
 * Description:       تحلیل هوشمند فروشگاه ووکامرسی: داشبورد آماری کامل، قیف فروش، منابع ورودی، گزارش شهر/دستگاه/مرورگر، تحلیل محصولات و دسته‌ها، درآمد و نرخ تبدیل و سبدهای رها شده؛ کاملاً فارسی، راست‌چین و واکنش‌گرا.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            آمار البرز
 * Text Domain:       amar-alborz-woocommerce
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // دسترسی مستقیم مجاز نیست.
}

define( 'AAW_VERSION', '2.0.0' );
define( 'AAW_DB_VERSION', '2.0' );
define( 'AAW_PLUGIN_FILE', __FILE__ );
define( 'AAW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AAW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AAW_PLUGIN_DIR . 'includes/class-aaw-jalali.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-device-detector.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-source-detector.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-db.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-tracker.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-woocommerce.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-funnel.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-cart-tracker.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-alerts.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-heatmap.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-session-replay.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-admin.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-education.php';
require_once AAW_PLUGIN_DIR . 'includes/class-aaw-dashboard-widget.php';

/**
 * راه‌اندازی فعال‌سازی افزونه: ساخت جدول‌های دیتابیس و مقادیر پیش‌فرض تنظیمات.
 */
function aaw_activate_plugin() {
	AAW_DB::create_tables();

	if ( false === get_option( 'aaw_settings' ) ) {
		add_option( 'aaw_settings', AAW_Admin::get_settings() );
	}

	if ( false === get_option( 'aaw_salt' ) ) {
		add_option( 'aaw_salt', wp_generate_password( 32, false ) );
	}

	update_option( 'aaw_db_version', AAW_DB_VERSION );

	if ( ! wp_next_scheduled( 'aaw_daily_cleanup_event' ) ) {
		wp_schedule_event( time(), 'daily', 'aaw_daily_cleanup_event' );
	}

	if ( ! wp_next_scheduled( 'aaw_daily_alerts_check' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'aaw_daily_alerts_check' );
	}
}
register_activation_hook( AAW_PLUGIN_FILE, 'aaw_activate_plugin' );

/**
 * راه‌اندازی غیرفعال‌سازی افزونه: فقط زمان‌بندی‌های Cron حذف می‌شوند، داده‌ها باقی می‌مانند.
 */
function aaw_deactivate_plugin() {
	foreach ( array( 'aaw_daily_cleanup_event', 'aaw_daily_alerts_check' ) as $event ) {
		$timestamp = wp_next_scheduled( $event );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $event );
		}
	}
}
register_deactivation_hook( AAW_PLUGIN_FILE, 'aaw_deactivate_plugin' );

add_action( 'aaw_daily_cleanup_event', array( 'AAW_DB', 'run_scheduled_cleanup' ) );

/**
 * بررسی نسخه دیتابیس هنگام آپدیت افزونه.
 */
function aaw_maybe_upgrade_db() {
	if ( get_option( 'aaw_db_version' ) !== AAW_DB_VERSION ) {
		AAW_DB::create_tables();
		update_option( 'aaw_db_version', AAW_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'aaw_maybe_upgrade_db' );

/**
 * راه‌اندازی کلاس‌های اصلی افزونه.
 */
function aaw_init_plugin() {
	AAW_Tracker::init();
	AAW_WooCommerce::init();
	AAW_Admin::init();
	AAW_Education::init();
	AAW_Dashboard_Widget::init();
	AAW_Alerts::init();
	AAW_Heatmap::init();
	AAW_Session_Replay::init();
}
add_action( 'plugins_loaded', 'aaw_init_plugin' );

/**
 * نمایش یادآوری در صورت غیرفعال بودن ووکامرس؛ بخش‌های عمومی (منابع ورودی و بازدید) بدون
 * ووکامرس هم کار می‌کنند، اما گزارش‌های فروش/قیف/محصولات نیاز به ووکامرس فعال دارند.
 */
function aaw_maybe_show_woocommerce_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || false === strpos( (string) $screen->id, 'aaw-' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>آمار البرز:</strong> افزونه‌ی ووکامرس روی این سایت فعال نیست. گزارش‌های بازدید و منابع ورودی همچنان کار می‌کنند، اما برای مشاهده‌ی قیف فروش، محصولات، درآمد و سایر گزارش‌های فروش، لطفاً ووکامرس را نصب و فعال کنید.</p></div>';
}
add_action( 'admin_notices', 'aaw_maybe_show_woocommerce_notice' );
