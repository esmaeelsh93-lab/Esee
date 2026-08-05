<?php
/**
 * قیف تبدیل کوچک فروشگاه (Mini Funnel):
 * کاربران ← مشاهده محصول ← افزودن به سبد خرید ← شروع تسویه‌حساب ← خرید نهایی
 *
 * ردیابی رویدادها فقط زمانی فعال می‌شود که ووکامرس نصب و فعال باشد و کاملاً سمت سرور
 * و به‌صورت هوک‌های رویدادی انجام می‌شود (نه با اسکریپت/پیکسل روی هر بازدید صفحه)؛
 * به همین دلیل هیچ فایل جاوااسکریپت یا CSS اضافه‌ای به سمت کاربر سایت ارسال نمی‌شود
 * و سرعت بارگذاری سایت تحت تأثیر قرار نمی‌گیرد.
 *
 * در صورتی که ووکامرس فعال نباشد یا هنوز داده‌ی واقعی کافی ثبت نشده باشد، گزارش بر
 * اساس «کل ورودی‌های واقعی سایت» و نرخ‌های فرضی قابل‌تنظیم (در تنظیمات) تخمین زده می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Funnel {

	const COOKIE_VIEW     = 'cvs_f_pv';
	const COOKIE_CART     = 'cvs_f_atc';
	const COOKIE_CHECKOUT = 'cvs_f_co';

	const STAGE_PRODUCT_VIEW = 'product_view';
	const STAGE_ADD_TO_CART  = 'add_to_cart';
	const STAGE_CHECKOUT     = 'begin_checkout';
	const STAGE_PURCHASE     = 'purchase';

	public static function init() {
		if ( ! self::is_woocommerce_active() ) {
			return;
		}

		add_action( 'template_redirect', array( __CLASS__, 'maybe_track_page' ), 5 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'track_add_to_cart' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'track_purchase' ) );
	}

	/**
	 * آیا ووکامرس روی این سایت نصب و فعال است؟
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * ردیابی بازدید صفحه‌ی محصول و صفحه‌ی شروع تسویه‌حساب (سمت سرور، بدون جاوااسکریپت).
	 */
	public static function maybe_track_page() {
		if ( is_admin() ) {
			return;
		}

		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			self::track_once( self::COOKIE_VIEW, self::STAGE_PRODUCT_VIEW );
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) ) {
				return; // صفحه‌ی «سفارش با موفقیت ثبت شد» بخشی از خرید نهایی است، نه شروع تسویه‌حساب.
			}
			self::track_once( self::COOKIE_CHECKOUT, self::STAGE_CHECKOUT );
		}
	}

	/**
	 * ثبت افزودن محصول به سبد خرید (فقط یک‌بار برای هر نشست، مثل شمارش بازدید).
	 */
	public static function track_add_to_cart() {
		self::track_once( self::COOKIE_CART, self::STAGE_ADD_TO_CART );
	}

	/**
	 * ثبت خرید نهایی هنگام نمایش صفحه‌ی «سفارش با موفقیت ثبت شد».
	 * برای جلوگیری از شمارش تکراری (مثلاً هنگام رفرش صفحه)، هر سفارش فقط یک‌بار شمارش می‌شود.
	 */
	public static function track_purchase( $order_id ) {
		if ( ! $order_id ) {
			return;
		}

		if ( 'yes' === get_post_meta( $order_id, '_cvs_funnel_counted', true ) ) {
			return;
		}

		update_post_meta( $order_id, '_cvs_funnel_counted', 'yes' );
		CVS_DB::insert_funnel_event( self::STAGE_PURCHASE, $order_id );
	}

	/**
	 * ثبت یک رویداد فقط یک‌بار در هر نشست کاربر (با استفاده از کوکی، مطابق منطق شمارش بازدید اصلی).
	 */
	private static function track_once( $cookie_name, $stage_key ) {
		if ( isset( $_COOKIE[ $cookie_name ] ) && '' !== $_COOKIE[ $cookie_name ] ) {
			return;
		}

		CVS_DB::insert_funnel_event( $stage_key );

		if ( headers_sent() ) {
			return;
		}

		$settings = CVS_Admin::get_settings();
		$minutes  = isset( $settings['session_timeout'] ) ? max( 1, (int) $settings['session_timeout'] ) : 30;

		setcookie(
			$cookie_name,
			'1',
			array(
				'expires'  => time() + ( $minutes * MINUTE_IN_SECONDS ),
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * تعریف مراحل قیف تبدیل به ترتیب، همراه با کلید نرخ فرضی متناظر در تنظیمات.
	 */
	public static function get_stage_definitions() {
		return array(
			array(
				'key'        => self::STAGE_PRODUCT_VIEW,
				'label'      => 'مشاهده محصول',
				'icon'       => '👁',
				'rate_field' => 'funnel_rate_view',
			),
			array(
				'key'        => self::STAGE_ADD_TO_CART,
				'label'      => 'افزودن به سبد خرید',
				'icon'       => '🛒',
				'rate_field' => 'funnel_rate_cart',
			),
			array(
				'key'        => self::STAGE_CHECKOUT,
				'label'      => 'شروع تسویه‌حساب',
				'icon'       => '💳',
				'rate_field' => 'funnel_rate_checkout',
			),
			array(
				'key'        => self::STAGE_PURCHASE,
				'label'      => 'خرید نهایی',
				'icon'       => '✅',
				'rate_field' => 'funnel_rate_purchase',
			),
		);
	}

	/**
	 * ساخت گزارش کامل قیف تبدیل برای یک بازه‌ی زمانی.
	 *
	 * مرحله‌ی اول همیشه «کاربران» است و از تعداد ورودی واقعی سایت (کل بازدید) گرفته می‌شود.
	 * برای مراحل بعدی: اگر ووکامرس فعال باشد و داده‌ی واقعی برای آن مرحله در این بازه ثبت
	 * شده باشد، همان عدد واقعی نمایش داده می‌شود؛ در غیر این صورت با نرخ فرضی قابل‌تنظیم
	 * (تنظیمات افزونه) نسبت به مرحله‌ی قبل تخمین زده می‌شود.
	 *
	 * @param string $from تاریخ شروع (Y-m-d).
	 * @param string $to   تاریخ پایان (Y-m-d).
	 * @param int    $total_users تعداد کل ورودی واقعی سایت در این بازه.
	 * @return array لیستی از مراحل؛ هر مرحله شامل key, label, icon, count, rate, is_estimated.
	 */
	public static function build_report( $from, $to, $total_users ) {
		$settings    = CVS_Admin::get_settings();
		$woo_active  = self::is_woocommerce_active();
		$real_counts = $woo_active ? CVS_DB::get_funnel_counts( $from, $to ) : array();

		$stages = array();

		$stages[] = array(
			'key'          => 'users',
			'label'        => 'کاربران (کل ورودی)',
			'icon'         => '👤',
			'count'        => (int) $total_users,
			'rate'         => 100.0,
			'is_estimated' => false,
		);

		$prev_count = (int) $total_users;

		foreach ( self::get_stage_definitions() as $stage ) {
			$real_count  = isset( $real_counts[ $stage['key'] ] ) ? (int) $real_counts[ $stage['key'] ] : 0;
			$is_estimated = ! ( $woo_active && $real_count > 0 );

			if ( $is_estimated ) {
				$rate_setting = isset( $settings[ $stage['rate_field'] ] ) ? (float) $settings[ $stage['rate_field'] ] : 0;
				$rate_setting = max( 0, min( 100, $rate_setting ) );
				$count        = (int) round( $prev_count * ( $rate_setting / 100 ) );
				$rate         = $prev_count > 0 ? $rate_setting : 0;
			} else {
				$count = $real_count;
				$rate  = $prev_count > 0 ? round( ( $count / $prev_count ) * 100, 1 ) : 0;
			}

			$stages[] = array(
				'key'          => $stage['key'],
				'label'        => $stage['label'],
				'icon'         => $stage['icon'],
				'count'        => $count,
				'rate'         => $rate,
				'is_estimated' => $is_estimated,
			);

			$prev_count = $count;
		}

		return $stages;
	}

	/**
	 * درصد کل تبدیل (خرید نهایی نسبت به کاربران) برای یک گزارش قیف.
	 */
	public static function get_overall_conversion( $stages ) {
		if ( empty( $stages ) ) {
			return 0;
		}

		$users_stage    = $stages[0];
		$purchase_stage = end( $stages );

		if ( empty( $users_stage['count'] ) ) {
			return 0;
		}

		return round( ( $purchase_stage['count'] / $users_stage['count'] ) * 100, 2 );
	}

	/**
	 * آیا حداقل یکی از مراحل قیف (به‌جز «کاربران») داده‌ی واقعی (نه تخمینی) دارد؟
	 */
	public static function has_any_real_data( $stages ) {
		foreach ( $stages as $stage ) {
			if ( 'users' !== $stage['key'] && empty( $stage['is_estimated'] ) ) {
				return true;
			}
		}

		return false;
	}
}
