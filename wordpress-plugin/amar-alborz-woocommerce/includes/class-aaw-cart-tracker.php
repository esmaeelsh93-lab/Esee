<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تحلیل سبدهای رها شده (نسخه تجاری):
 * با استفاده از عکس‌فوری واقعی سبد خرید (ثبت‌شده در AAW_WooCommerce::sync_cart_snapshot)،
 * سبدهایی که مدتی از آخرین تغییرشان گذشته و به سفارش تبدیل نشده‌اند، «رها شده» علامت می‌خورند.
 * هیچ داده‌ای حدسی نیست: محصولات همان اقلام واقعی سبد خرید کاربر هستند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Cart_Tracker {

	/**
	 * علامت‌گذاری سبدهای فعالی که مدت‌زمان مشخصی (بر اساس تنظیمات) تغییری نداشته‌اند به‌عنوان «رها شده».
	 */
	public static function mark_stale_carts_abandoned() {
		$settings       = AAW_Admin::get_settings();
		$timeout_hours  = isset( $settings['cart_abandon_hours'] ) ? max( 1, (int) $settings['cart_abandon_hours'] ) : 2;
		$threshold_time = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - ( $timeout_hours * HOUR_IN_SECONDS ) );

		AAW_DB::mark_carts_abandoned_before( $threshold_time );
	}
}
