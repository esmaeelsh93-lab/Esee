<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * ثبت بازدید و نشست کاربران (ردیابی سمت سرور، بدون نیاز به جاوااسکریپت اضافه روی سایت).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Tracker {

	const COOKIE_NAME = 'aaw_sid';

	private static $current_session_id = null;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_track_visit' ), 1 );
	}

	/**
	 * شناسه‌ی نشست جاری (برای استفاده در سایر ماژول‌ها مثل قیف فروش و سبد خرید).
	 */
	public static function get_session_id() {
		if ( null !== self::$current_session_id ) {
			return self::$current_session_id;
		}

		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && '' !== $_COOKIE[ self::COOKIE_NAME ] ) {
			self::$current_session_id = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
			return self::$current_session_id;
		}

		return null;
	}

	/**
	 * بررسی شرایط و در صورت لزوم ثبت بازدید جدید یا افزایش شمار صفحات نشست فعلی.
	 */
	public static function maybe_track_visit() {
		if ( ! self::should_track() ) {
			return;
		}

		$settings     = AAW_Admin::get_settings();
		$session_id   = self::get_session_id();
		$is_new_session = empty( $session_id );

		if ( $is_new_session ) {
			$session_id = wp_generate_password( 24, false );
			self::$current_session_id = $session_id;
			self::set_session_cookie( $session_id, $settings['session_timeout'] );
		} else {
			self::extend_session_cookie( $session_id, $settings['session_timeout'] );
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( ! $is_new_session ) {
			AAW_DB::upsert_visit( $session_id, array() );
			return;
		}

		$referrer  = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$query     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		$source = AAW_Source_Detector::detect( $referrer, $query, $home_host );

		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = mb_substr( $request_path, 0, 500 );

		AAW_DB::upsert_visit(
			$session_id,
			array(
				'source_key'    => $source['source_key'],
				'source_label'  => $source['source_label'],
				'referrer_host' => $source['referrer_host'],
				'referrer_url'  => $referrer ? mb_substr( $referrer, 0, 1000 ) : null,
				'entry_path'    => $request_path,
				'utm_source'    => $source['utm_source'],
				'utm_medium'    => $source['utm_medium'],
				'utm_campaign'  => $source['utm_campaign'],
				'utm_term'      => $source['utm_term'],
				'utm_content'   => $source['utm_content'],
				'device_type'   => AAW_Device_Detector::detect_device_type( $user_agent ),
				'browser'       => AAW_Device_Detector::detect_browser( $user_agent ),
				'os_name'       => AAW_Device_Detector::detect_os( $user_agent ),
				'ip_hash'       => self::get_ip_hash(),
			)
		);

		if ( ! empty( $source['utm_source'] ) ) {
			self::set_utm_attribution_cookie( $source );
		}
	}

	/**
	 * بررسی می‌کند آیا درخواست فعلی باید ردیابی شود یا خیر.
	 */
	private static function should_track() {
		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return false;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return false;
		}

		if ( function_exists( 'is_robots' ) && is_robots() ) {
			return false;
		}

		if ( function_exists( 'is_trackback' ) && is_trackback() ) {
			return false;
		}

		if ( function_exists( 'is_preview' ) && is_preview() ) {
			return false;
		}

		$settings = AAW_Admin::get_settings();

		if ( ! empty( $settings['exclude_staff'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return false;
		}

		if ( self::is_excluded_ip( $settings ) ) {
			return false;
		}

		if ( self::is_bot_user_agent() ) {
			return false;
		}

		return true;
	}

	private static function is_excluded_ip( $settings ) {
		if ( empty( $settings['excluded_ips'] ) ) {
			return false;
		}

		$current_ip = self::get_client_ip();
		if ( empty( $current_ip ) ) {
			return false;
		}

		$excluded = array_filter( array_map( 'trim', explode( "\n", $settings['excluded_ips'] ) ) );

		return in_array( $current_ip, $excluded, true );
	}

	private static function is_bot_user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return true; // درخواست بدون User-Agent معمولاً ربات یا اسکریپت است.
		}

		$ua = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) );

		$bot_signatures = array(
			'bot', 'spider', 'crawl', 'slurp', 'facebookexternalhit', 'curl',
			'wget', 'python-requests', 'python-urllib', 'httpclient', 'okhttp',
			'go-http-client', 'ahrefs', 'semrush', 'mj12bot', 'dotbot',
			'petalbot', 'bingpreview', 'yandexbot', 'pingdom', 'uptimerobot',
			'headlesschrome', 'phantomjs', 'lighthouse',
		);

		foreach ( $bot_signatures as $signature ) {
			if ( false !== strpos( $ua, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * دریافت آدرس IP واقعی کاربر (با در نظر گرفتن پروکسی‌های رایج).
	 */
	public static function get_client_ip() {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				$ip    = trim( explode( ',', $value )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}

	private static function get_ip_hash() {
		$ip = self::get_client_ip();
		if ( empty( $ip ) ) {
			return null;
		}
		$salt = get_option( 'aaw_salt', '' );
		return hash( 'sha256', $ip . $salt );
	}

	private static function set_session_cookie( $token, $minutes ) {
		if ( headers_sent() ) {
			return;
		}

		$minutes = max( 1, (int) $minutes );

		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires'  => time() + ( $minutes * MINUTE_IN_SECONDS ),
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	private static function extend_session_cookie( $token, $minutes ) {
		self::set_session_cookie( $token, $minutes );
	}

	/**
	 * تنظیم کوکی جداگانه برای انتساب کمپین (UTM) که هنگام ثبت سفارش خوانده می‌شود
	 * تا گزارش UTM بتواند سفارش را به کمپین صحیح نسبت دهد (انتساب آخرین کلیک).
	 */
	private static function set_utm_attribution_cookie( $source ) {
		if ( headers_sent() ) {
			return;
		}

		$payload = wp_json_encode(
			array(
				'source'   => $source['source_label'],
				'medium'   => $source['utm_medium'],
				'campaign' => $source['utm_campaign'],
				'term'     => $source['utm_term'],
				'content'  => $source['utm_content'],
			)
		);

		setcookie(
			'aaw_utm',
			rawurlencode( $payload ),
			array(
				'expires'  => time() + ( 30 * DAY_IN_SECONDS ),
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * خوانش کوکی انتساب کمپین (در صورت وجود) - استفاده در هنگام ثبت سفارش.
	 */
	public static function get_utm_attribution() {
		if ( empty( $_COOKIE['aaw_utm'] ) ) {
			return null;
		}

		$decoded = json_decode( rawurldecode( wp_unslash( $_COOKIE['aaw_utm'] ) ), true );

		return is_array( $decoded ) ? $decoded : null;
	}
}
