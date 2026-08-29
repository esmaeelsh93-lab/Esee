<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * هشدار هوشمند (نسخه تجاری): هر روز به‌صورت خودکار وضعیت ۷ روز اخیر با ۷ روز قبل از آن
 * مقایسه می‌شود و در صورت عبور از آستانه‌های قابل‌تنظیم (تنظیمات افزونه)، هشدار ثبت می‌شود:
 * افت نرخ تبدیل، افزایش نرخ خروج بدون تعامل (Bounce Rate)، کاهش فروش، افزایش سبدهای رها شده.
 * تمام مقایسه‌ها بر پایه‌ی داده‌های واقعی ثبت‌شده در دیتابیس افزونه است.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Alerts {

	public static function init() {
		add_action( 'aaw_daily_alerts_check', array( __CLASS__, 'run_daily_check' ) );
	}

	/**
	 * اجرای بررسی روزانه‌ی هشدارها (فراخوانی‌شده از رویداد زمان‌بندی‌شده‌ی وردپرس).
	 */
	public static function run_daily_check() {
		$settings = AAW_Admin::get_settings();

		if ( empty( $settings['alerts_enabled'] ) ) {
			return;
		}

		$today      = current_time( 'Y-m-d' );
		$from       = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
		$to         = $today;
		list( $prev_from, $prev_to ) = AAW_DB::get_previous_range( $from, $to );

		self::check_conversion_rate( $from, $to, $prev_from, $prev_to, $settings );
		self::check_bounce_rate( $from, $to, $prev_from, $prev_to, $settings );
		self::check_revenue( $from, $to, $prev_from, $prev_to, $settings );
		self::check_abandoned_carts( $from, $to, $prev_from, $prev_to, $settings );
	}

	private static function check_conversion_rate( $from, $to, $prev_from, $prev_to, $settings ) {
		if ( ! AAW_WooCommerce::is_active() || self::already_alerted( 'conversion_drop' ) ) {
			return;
		}

		$current  = AAW_WooCommerce::get_conversion_rate( $from, $to );
		$previous = AAW_WooCommerce::get_conversion_rate( $prev_from, $prev_to );

		if ( $previous <= 0 ) {
			return;
		}

		$drop_percent  = ( ( $previous - $current ) / $previous ) * 100;
		$threshold     = isset( $settings['alert_conversion_drop'] ) ? (float) $settings['alert_conversion_drop'] : 20;

		if ( $drop_percent >= $threshold ) {
			self::fire(
				'conversion_drop',
				'critical',
				'افت نرخ تبدیل',
				sprintf( 'نرخ تبدیل ۷ روز اخیر از %s٪ به %s٪ رسیده؛ کاهش %s٪ نسبت به ۷ روز قبل.', self::fmt( $previous ), self::fmt( $current ), self::fmt( round( $drop_percent ) ) ),
				$previous,
				$current
			);
		}
	}

	private static function check_bounce_rate( $from, $to, $prev_from, $prev_to, $settings ) {
		if ( self::already_alerted( 'bounce_increase' ) ) {
			return;
		}

		$current  = AAW_DB::get_bounce_rate( $from, $to );
		$previous = AAW_DB::get_bounce_rate( $prev_from, $prev_to );

		if ( $previous <= 0 ) {
			return;
		}

		$increase_percent = ( ( $current - $previous ) / $previous ) * 100;
		$threshold        = isset( $settings['alert_bounce_increase'] ) ? (float) $settings['alert_bounce_increase'] : 25;

		if ( $increase_percent >= $threshold ) {
			self::fire(
				'bounce_increase',
				'warning',
				'افزایش نرخ خروج بدون تعامل (Bounce Rate)',
				sprintf( 'نرخ خروج بدون تعامل از %s٪ به %s٪ رسیده؛ افزایش %s٪ نسبت به ۷ روز قبل.', self::fmt( $previous ), self::fmt( $current ), self::fmt( round( $increase_percent ) ) ),
				$previous,
				$current
			);
		}
	}

	private static function check_revenue( $from, $to, $prev_from, $prev_to, $settings ) {
		if ( ! AAW_WooCommerce::is_active() || self::already_alerted( 'revenue_drop' ) ) {
			return;
		}

		$current  = AAW_WooCommerce::get_revenue_summary( $from, $to );
		$previous = AAW_WooCommerce::get_revenue_summary( $prev_from, $prev_to );

		if ( $previous['gross_revenue'] <= 0 ) {
			return;
		}

		$drop_percent = ( ( $previous['gross_revenue'] - $current['gross_revenue'] ) / $previous['gross_revenue'] ) * 100;
		$threshold    = isset( $settings['alert_revenue_drop'] ) ? (float) $settings['alert_revenue_drop'] : 20;

		if ( $drop_percent >= $threshold ) {
			self::fire(
				'revenue_drop',
				'critical',
				'کاهش فروش',
				sprintf( 'فروش ۷ روز اخیر نسبت به ۷ روز قبل %s٪ کاهش یافته است.', self::fmt( round( $drop_percent ) ) ),
				$previous['gross_revenue'],
				$current['gross_revenue']
			);
		}
	}

	private static function check_abandoned_carts( $from, $to, $prev_from, $prev_to, $settings ) {
		if ( self::already_alerted( 'cart_abandon_increase' ) ) {
			return;
		}

		$current  = AAW_DB::get_abandoned_summary( $from, $to );
		$previous = AAW_DB::get_abandoned_summary( $prev_from, $prev_to );

		if ( $previous['total'] <= 0 ) {
			return;
		}

		$increase_percent = ( ( $current['total'] - $previous['total'] ) / $previous['total'] ) * 100;
		$threshold        = isset( $settings['alert_cart_abandon_increase'] ) ? (float) $settings['alert_cart_abandon_increase'] : 30;

		if ( $increase_percent >= $threshold ) {
			self::fire(
				'cart_abandon_increase',
				'warning',
				'افزایش سبدهای رها شده',
				sprintf( 'تعداد سبدهای رها شده از %s به %s رسیده؛ افزایش %s٪ نسبت به ۷ روز قبل.', self::fmt( $previous['total'] ), self::fmt( $current['total'] ), self::fmt( round( $increase_percent ) ) ),
				$previous['total'],
				$current['total']
			);
		}
	}

	private static function already_alerted( $key ) {
		return AAW_DB::had_alert_today( $key );
	}

	private static function fire( $key, $severity, $title, $message, $before, $after ) {
		AAW_DB::insert_alert( $key, $severity, $title, $message, $before, $after );

		$settings = AAW_Admin::get_settings();
		if ( ! empty( $settings['alert_email_enabled'] ) ) {
			wp_mail(
				get_option( 'admin_email' ),
				'⚠ هشدار آمار البرز: ' . $title,
				$message . "\n\nمشاهده‌ی جزئیات: " . admin_url( 'admin.php?page=aaw-pro-tools&tab=alerts' )
			);
		}
	}

	private static function fmt( $number ) {
		return AAW_Jalali::to_persian_digits( $number );
	}
}
