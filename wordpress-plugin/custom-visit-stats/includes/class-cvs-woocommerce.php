<?php
/**
 * اتصال اختیاری و بدون وابستگی سخت به ووکامرس.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_WooCommerce {

	const COUNTED_META = '_cvs_stats_counted';
	const AMOUNT_META  = '_cvs_stats_amount';
	const DATE_META    = '_cvs_stats_date';

	public static function init() {
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'handle_status_change' ), 10, 4 );
	}

	/**
	 * تنها سفارش‌های processing/completed را، به‌شکل idempotent، در فروش می‌شمارد.
	 */
	public static function handle_status_change( $order_id, $old_status, $new_status, $order = null ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$paid_statuses = array( 'processing', 'completed' );
		$was_paid      = in_array( $old_status, $paid_statuses, true );
		$is_paid       = in_array( $new_status, $paid_statuses, true );
		$counted       = 'yes' === $order->get_meta( self::COUNTED_META, true );

		if ( $is_paid && ! $counted ) {
			$amount = (float) $order->get_total();
			$date   = self::get_order_date( $order );

			CVS_DB::update_daily_sales( $date, $amount, 1 );
			$order->update_meta_data( self::COUNTED_META, 'yes' );
			$order->update_meta_data( self::AMOUNT_META, $amount );
			$order->update_meta_data( self::DATE_META, $date );
			$order->save_meta_data();
			return;
		}

		if ( $was_paid && ! $is_paid && $counted ) {
			$amount = (float) $order->get_meta( self::AMOUNT_META, true );
			$date   = (string) $order->get_meta( self::DATE_META, true );
			if ( ! $date ) {
				$date = self::get_order_date( $order );
			}

			CVS_DB::update_daily_sales( $date, -1 * $amount, -1 );
			$order->delete_meta_data( self::COUNTED_META );
			$order->delete_meta_data( self::AMOUNT_META );
			$order->delete_meta_data( self::DATE_META );
			$order->save_meta_data();
		}
	}

	private static function get_order_date( $order ) {
		$date = $order->get_date_paid();
		if ( ! $date ) {
			$date = $order->get_date_created();
		}

		return $date ? wp_date( 'Y-m-d', $date->getTimestamp(), wp_timezone() ) : current_time( 'Y-m-d' );
	}
}
