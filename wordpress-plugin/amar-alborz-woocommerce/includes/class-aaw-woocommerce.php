<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * منطق صحیح سفارش و گزارش‌های فروش ووکامرس.
 *
 * قوانین این کلاس دقیقاً مطابق منطق صحیح درخواستی است:
 * - هر سفارش فقط یک‌بار در «قیف فروش» شمارش می‌شود (با متای سفارش، حتی اگر چند بار وضعیت تغییر کند).
 * - مبنای آمار فروش/درآمد فقط وضعیت‌های processing، completed و on-hold است.
 * - سفارش‌های failed و cancelled هرگز در فروش محاسبه نمی‌شوند.
 * - مرجوعی‌ها (Refund) به‌صورت جداگانه محاسبه و نمایش داده می‌شوند؛ از فروش کم نمی‌شوند به‌صورت پنهان.
 * - تمام اعداد مستقیماً از رکوردهای واقعی سفارش‌های ووکامرس خوانده می‌شوند، نه تخمین.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_WooCommerce {

	const META_PURCHASE_COUNTED = '_aaw_purchase_counted';
	const META_SESSION_ID       = '_aaw_session_id';
	const META_DEVICE_TYPE      = '_aaw_device_type';
	const META_BROWSER          = '_aaw_browser';
	const META_UTM_SOURCE       = '_aaw_utm_source';
	const META_UTM_MEDIUM       = '_aaw_utm_medium';
	const META_UTM_CAMPAIGN     = '_aaw_utm_campaign';
	const META_UTM_TERM         = '_aaw_utm_term';
	const META_UTM_CONTENT      = '_aaw_utm_content';

	const MAX_ORDERS_PER_REPORT = 3000;

	public static function init() {
		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'maybe_track_page' ), 5 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'track_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'sync_cart_snapshot' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'sync_cart_snapshot' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'capture_checkout_meta' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'maybe_count_order' ), 10, 4 );
	}

	public static function is_active() {
		return class_exists( 'WooCommerce' );
	}

	public static function counted_statuses() {
		return array( 'processing', 'on-hold', 'completed' );
	}

	public static function excluded_statuses() {
		return array( 'failed', 'cancelled' );
	}

	/* ===================== ردیابی قیف فروش (رویدادهای واقعی) ===================== */

	public static function maybe_track_page() {
		if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$session_id = AAW_Tracker::get_session_id();

		if ( function_exists( 'is_product' ) && is_product() ) {
			AAW_DB::insert_funnel_event_once( 'product_view', $session_id );
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
				return;
			}
			AAW_DB::insert_funnel_event_once( 'begin_checkout', $session_id );
			AAW_DB::mark_session_interaction( $session_id );
		}
	}

	public static function track_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$session_id = AAW_Tracker::get_session_id();
		AAW_DB::insert_funnel_event_once( 'add_to_cart', $session_id, $product_id );
		AAW_DB::mark_session_interaction( $session_id );
		self::sync_cart_snapshot();
	}

	/**
	 * ذخیره‌ی عکس فوری از محتوای سبد خرید فعلی (برای گزارش سبدهای رها شده) بر اساس نشست واقعی کاربر.
	 */
	public static function sync_cart_snapshot() {
		if ( ! function_exists( 'WC' ) || null === WC()->cart ) {
			return;
		}

		$session_id = AAW_Tracker::get_session_id();
		if ( empty( $session_id ) ) {
			return;
		}

		$items = array();
		$total = 0.0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product ) {
				continue;
			}
			$quantity = (int) $cart_item['quantity'];
			$price    = (float) $product->get_price();

			$items[] = array(
				'product_id' => $cart_item['product_id'],
				'name'       => $product->get_name(),
				'quantity'   => $quantity,
				'price'      => $price,
			);

			$total += $price * $quantity;
		}

		if ( empty( $items ) ) {
			return;
		}

		AAW_DB::upsert_cart_snapshot( $session_id, $items, $total );
	}

	/**
	 * ثبت اطلاعات واقعی نشست (دستگاه، مرورگر، کمپین UTM) روی متای سفارش، دقیقاً در لحظه‌ی ثبت سفارش.
	 * این اطلاعات بعداً برای گزارش UTM و گزارش دستگاه/مرورگر فروش استفاده می‌شود.
	 */
	public static function capture_checkout_meta( $order_id, $posted_data, $order ) {
		$session_id = AAW_Tracker::get_session_id();
		if ( $session_id ) {
			$order->update_meta_data( self::META_SESSION_ID, $session_id );
			AAW_DB::mark_cart_converted( $session_id, $order_id );
			AAW_DB::mark_session_interaction( $session_id );
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$order->update_meta_data( self::META_DEVICE_TYPE, AAW_Device_Detector::detect_device_type( $user_agent ) );
		$order->update_meta_data( self::META_BROWSER, AAW_Device_Detector::detect_browser( $user_agent ) );

		$utm = AAW_Tracker::get_utm_attribution();
		if ( is_array( $utm ) ) {
			$order->update_meta_data( self::META_UTM_SOURCE, isset( $utm['source'] ) ? $utm['source'] : '' );
			$order->update_meta_data( self::META_UTM_MEDIUM, isset( $utm['medium'] ) ? $utm['medium'] : '' );
			$order->update_meta_data( self::META_UTM_CAMPAIGN, isset( $utm['campaign'] ) ? $utm['campaign'] : '' );
			$order->update_meta_data( self::META_UTM_TERM, isset( $utm['term'] ) ? $utm['term'] : '' );
			$order->update_meta_data( self::META_UTM_CONTENT, isset( $utm['content'] ) ? $utm['content'] : '' );
		}

		$order->save();
	}

	/**
	 * منطق صحیح شمارش سفارش: فقط زمانی که سفارش برای اولین‌بار به یکی از وضعیت‌های
	 * شمارش‌شده (processing/on-hold/completed) می‌رسد، دقیقاً یک‌بار در قیف فروش ثبت می‌شود؛
	 * تغییر وضعیت‌های بعدی بین این وضعیت‌ها دیگر شمارش تکراری ایجاد نمی‌کند.
	 */
	public static function maybe_count_order( $order_id, $from_status, $to_status, $order ) {
		if ( ! in_array( $to_status, self::counted_statuses(), true ) ) {
			return;
		}

		if ( 'yes' === $order->get_meta( self::META_PURCHASE_COUNTED ) ) {
			return;
		}

		$order->update_meta_data( self::META_PURCHASE_COUNTED, 'yes' );
		$order->save();

		$session_id = $order->get_meta( self::META_SESSION_ID );
		AAW_DB::insert_purchase_event( $order_id, $order->get_total(), $session_id ? $session_id : null );
	}

	/* ===================== گزارش‌های فروش واقعی ===================== */

	/**
	 * تمام سفارش‌های واقعی ووکامرس در یک بازه‌ی زمانی که در وضعیت‌های مبنای شمارش قرار دارند.
	 *
	 * @return WC_Order[]
	 */
	public static function get_orders_in_range( $from, $to ) {
		if ( ! self::is_active() ) {
			return array();
		}

		return wc_get_orders(
			array(
				'status'       => self::counted_statuses(),
				'date_created' => $from . '...' . $to,
				'limit'        => self::MAX_ORDERS_PER_REPORT,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'type'         => 'shop_order',
			)
		);
	}

	/**
	 * خلاصه‌ی درآمد: فروش ناخالص، مرجوعی (جداگانه)، فروش خالص، تعداد سفارش و میانگین ارزش سفارش.
	 */
	public static function get_revenue_summary( $from, $to ) {
		$orders = self::get_orders_in_range( $from, $to );

		$gross    = 0.0;
		$refunded = 0.0;
		$count    = count( $orders );

		foreach ( $orders as $order ) {
			$gross    += (float) $order->get_total();
			$refunded += (float) $order->get_total_refunded();
		}

		return array(
			'orders_count'   => $count,
			'gross_revenue'  => $gross,
			'refunded_total' => $refunded,
			'net_revenue'    => max( 0, $gross - $refunded ),
			'aov'            => $count > 0 ? $gross / $count : 0,
		);
	}

	/**
	 * لیست سفارش‌های دارای مرجوعی، برای نمایش جداگانه‌ی وضعیت مرجوعی‌ها.
	 */
	public static function get_refunded_orders( $from, $to ) {
		$orders = self::get_orders_in_range( $from, $to );
		$result = array();

		foreach ( $orders as $order ) {
			$refunded = (float) $order->get_total_refunded();
			if ( $refunded <= 0 ) {
				continue;
			}
			$result[] = array(
				'order_id' => $order->get_id(),
				'total'    => (float) $order->get_total(),
				'refunded' => $refunded,
				'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
			);
		}

		return $result;
	}

	/**
	 * گزارش پرفروش‌ترین محصولات بر اساس اقلام واقعی سفارش‌های شمارش‌شده.
	 */
	public static function get_products_report( $from, $to, $limit = 10 ) {
		$orders   = self::get_orders_in_range( $from, $to );
		$products = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				if ( ! $product_id ) {
					continue;
				}
				if ( ! isset( $products[ $product_id ] ) ) {
					$products[ $product_id ] = array(
						'product_id' => $product_id,
						'name'       => $item->get_name(),
						'quantity'   => 0,
						'revenue'    => 0.0,
						'orders'     => 0,
					);
				}
				$products[ $product_id ]['quantity'] += (int) $item->get_quantity();
				$products[ $product_id ]['revenue']  += (float) $item->get_total();
				$products[ $product_id ]['orders']++;
			}
		}

		usort(
			$products,
			function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		return array_slice( array_values( $products ), 0, $limit );
	}

	/**
	 * گزارش دسته‌بندی‌ها بر اساس تجمیع محصولات واقعی فروخته‌شده.
	 */
	public static function get_categories_report( $from, $to, $limit = 10 ) {
		$orders     = self::get_orders_in_range( $from, $to );
		$categories = array();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				if ( ! $product_id ) {
					continue;
				}
				$terms = get_the_terms( $product_id, 'product_cat' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					$terms = array( (object) array( 'term_id' => 0, 'name' => 'دسته‌بندی‌نشده' ) );
				}
				foreach ( $terms as $term ) {
					if ( ! isset( $categories[ $term->term_id ] ) ) {
						$categories[ $term->term_id ] = array(
							'name'     => $term->name,
							'quantity' => 0,
							'revenue'  => 0.0,
						);
					}
					$categories[ $term->term_id ]['quantity'] += (int) $item->get_quantity();
					$categories[ $term->term_id ]['revenue']  += (float) $item->get_total();
				}
			}
		}

		usort(
			$categories,
			function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		return array_slice( array_values( $categories ), 0, $limit );
	}

	/**
	 * گزارش شهرها بر اساس شهر فاکتور (billing_city) واقعی ثبت‌شده توسط خودِ مشتری در سفارش
	 * (نه موقعیت جغرافیایی تخمینی از روی IP)؛ دقیق‌ترین و صادقانه‌ترین منبع داده‌ی «شهر» برای فروشگاه.
	 */
	public static function get_cities_report( $from, $to, $limit = 10 ) {
		$orders = self::get_orders_in_range( $from, $to );
		$cities = array();

		foreach ( $orders as $order ) {
			$city = trim( $order->get_billing_city() );
			if ( '' === $city ) {
				$city = 'نامشخص';
			}
			if ( ! isset( $cities[ $city ] ) ) {
				$cities[ $city ] = array(
					'city'    => $city,
					'orders'  => 0,
					'revenue' => 0.0,
				);
			}
			$cities[ $city ]['orders']++;
			$cities[ $city ]['revenue'] += (float) $order->get_total();
		}

		usort(
			$cities,
			function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		return array_slice( array_values( $cities ), 0, $limit );
	}

	/**
	 * گزارش دستگاه/مرورگر خریداران، بر اساس متای واقعی ثبت‌شده روی سفارش در لحظه‌ی خرید.
	 */
	public static function get_purchase_device_report( $from, $to ) {
		$orders  = self::get_orders_in_range( $from, $to );
		$devices = array();

		foreach ( $orders as $order ) {
			$device = $order->get_meta( self::META_DEVICE_TYPE );
			$device = $device ? $device : 'desktop';
			if ( ! isset( $devices[ $device ] ) ) {
				$devices[ $device ] = 0;
			}
			$devices[ $device ]++;
		}

		return $devices;
	}

	/**
	 * نرخ تبدیل واقعی: تعداد سفارش‌های شمارش‌شده تقسیم بر تعداد بازدیدکنندگان واقعی (نشست‌ها).
	 */
	public static function get_conversion_rate( $from, $to, $visitors_total = null ) {
		if ( null === $visitors_total ) {
			$visitors_total = AAW_DB::get_total( $from, $to );
		}

		if ( $visitors_total <= 0 ) {
			return 0.0;
		}

		$orders_count = count( self::get_orders_in_range( $from, $to ) );

		return round( ( $orders_count / $visitors_total ) * 100, 2 );
	}

	/**
	 * گزارش UTM واقعی: تجمیع سفارش‌های دارای متای کمپین ثبت‌شده در لحظه‌ی خرید.
	 */
	public static function get_utm_report( $from, $to ) {
		$orders = self::get_orders_in_range( $from, $to );
		$rows   = array();

		foreach ( $orders as $order ) {
			$campaign = $order->get_meta( self::META_UTM_CAMPAIGN );
			$source   = $order->get_meta( self::META_UTM_SOURCE );
			$medium   = $order->get_meta( self::META_UTM_MEDIUM );

			if ( ! $campaign && ! $source ) {
				continue;
			}

			$key = ( $campaign ? $campaign : '—' ) . '|' . ( $source ? $source : '—' ) . '|' . ( $medium ? $medium : '—' );

			if ( ! isset( $rows[ $key ] ) ) {
				$rows[ $key ] = array(
					'campaign' => $campaign ? $campaign : '—',
					'source'   => $source ? $source : '—',
					'medium'   => $medium ? $medium : '—',
					'orders'   => 0,
					'revenue'  => 0.0,
				);
			}

			$rows[ $key ]['orders']++;
			$rows[ $key ]['revenue'] += (float) $order->get_total();
		}

		usort(
			$rows,
			function ( $a, $b ) {
				return $b['revenue'] <=> $a['revenue'];
			}
		);

		return array_values( $rows );
	}
}
