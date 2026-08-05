<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * قیف فروش: بازدیدکنندگان ← مشاهده محصول ← افزودن به سبد خرید ← شروع تسویه‌حساب ← خرید نهایی.
 *
 * تمام اعداد این قیف مستقیماً از رویدادهای واقعی ثبت‌شده توسط ووکامرس و ردیاب بازدید خوانده
 * می‌شوند (کلاس‌های AAW_Tracker و AAW_WooCommerce). هیچ عددی در این گزارش تخمینی یا حدسی نیست؛
 * اگر داده‌ی واقعی برای یک مرحله ثبت نشده باشد، همان عدد صفر یا واقعی نمایش داده می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Funnel {

	const STAGE_PRODUCT_VIEW = 'product_view';
	const STAGE_ADD_TO_CART  = 'add_to_cart';
	const STAGE_CHECKOUT     = 'begin_checkout';
	const STAGE_PURCHASE     = 'purchase';

	public static function get_stage_definitions() {
		return array(
			array(
				'key'   => self::STAGE_PRODUCT_VIEW,
				'label' => 'مشاهده محصول',
				'icon'  => '👁',
			),
			array(
				'key'   => self::STAGE_ADD_TO_CART,
				'label' => 'افزودن به سبد خرید',
				'icon'  => '🛒',
			),
			array(
				'key'   => self::STAGE_CHECKOUT,
				'label' => 'شروع تسویه‌حساب',
				'icon'  => '💳',
			),
			array(
				'key'   => self::STAGE_PURCHASE,
				'label' => 'خرید نهایی',
				'icon'  => '✅',
			),
		);
	}

	/**
	 * ساخت گزارش کامل و واقعی قیف فروش برای یک بازه‌ی زمانی.
	 *
	 * مرحله‌ی اول «بازدیدکنندگان» از تعداد نشست‌های واقعی ثبت‌شده در ردیاب بازدید گرفته می‌شود.
	 * مراحل بعدی مستقیماً از شمار رویدادهای واقعی ووکامرس در همین بازه خوانده می‌شوند.
	 *
	 * @return array لیستی از مراحل؛ هر مرحله شامل key, label, icon, count, rate (نسبت به مرحله‌ی قبل), drop (تعداد افت).
	 */
	public static function build_report( $from, $to, $total_visitors ) {
		$woo_active = AAW_WooCommerce::is_active();
		$counts     = AAW_DB::get_funnel_counts( $from, $to );

		$stages = array();

		$stages[] = array(
			'key'   => 'visitors',
			'label' => 'بازدیدکنندگان',
			'icon'  => '👤',
			'count' => (int) $total_visitors,
			'rate'  => 100.0,
			'drop'  => 0,
		);

		$prev_count = (int) $total_visitors;

		foreach ( self::get_stage_definitions() as $stage ) {
			$count = isset( $counts[ $stage['key'] ] ) ? (int) $counts[ $stage['key'] ] : 0;
			$rate  = $prev_count > 0 ? round( ( $count / $prev_count ) * 100, 1 ) : 0.0;
			$drop  = max( 0, $prev_count - $count );

			$stages[] = array(
				'key'   => $stage['key'],
				'label' => $stage['label'],
				'icon'  => $stage['icon'],
				'count' => $count,
				'rate'  => $rate,
				'drop'  => $drop,
			);

			$prev_count = $count;
		}

		return array(
			'stages'          => $stages,
			'woo_active'      => $woo_active,
			'has_any_data'    => array_sum( $counts ) > 0,
		);
	}

	/**
	 * درصد کل تبدیل (خرید نهایی نسبت به بازدیدکنندگان) برای یک گزارش قیف.
	 */
	public static function get_overall_conversion( $report ) {
		$stages = $report['stages'];
		if ( empty( $stages ) ) {
			return 0;
		}

		$visitors_stage = $stages[0];
		$purchase_stage = end( $stages );

		if ( empty( $visitors_stage['count'] ) ) {
			return 0;
		}

		return round( ( $purchase_stage['count'] / $visitors_stage['count'] ) * 100, 2 );
	}
}
