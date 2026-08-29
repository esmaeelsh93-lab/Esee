<?php
/**
 * ثبت بازدیدهای سایت (ردیابی سمت سرور، بدون نیاز به جاوااسکریپت).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Tracker {

	const COOKIE_NAME = 'cvs_vid';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_track_visit' ), 1 );
	}

	/**
	 * بررسی شرایط و در صورت لزوم ثبت یک ورودی جدید.
	 */
	public static function maybe_track_visit() {
		if ( ! self::should_track() ) {
			return;
		}

		// اگر کوکی نشست معتبر موجود باشد، یعنی این بازدید ادامه‌ی همان ورود قبلی است.
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && '' !== $_COOKIE[ self::COOKIE_NAME ] ) {
			return;
		}

		$settings = CVS_Admin::get_settings();

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$query    = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		$source = CVS_Source_Detector::detect( $referrer, $query, $home_host );

		$request_path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request_path = mb_substr( $request_path, 0, 500 );

		CVS_DB::insert_visit(
			array(
				'source_key'    => $source['source_key'],
				'source_label'  => $source['source_label'],
				'referrer_host' => $source['referrer_host'],
				'referrer_url'  => $referrer ? mb_substr( $referrer, 0, 1000 ) : null,
				'request_path'  => $request_path,
				'ip_hash'       => self::get_ip_hash(),
			)
		);

		self::set_session_cookie( $settings['session_timeout'] );
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

		$settings = CVS_Admin::get_settings();

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

	/**
	 * بررسی می‌کند آیا آدرس IP بازدیدکننده در فهرست مستثنی‌شده قرار دارد.
	 */
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

	/**
	 * تشخیص ساده‌ی ربات‌ها و خزنده‌ها بر اساس User-Agent.
	 */
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

	/**
	 * هش امن آدرس IP برای حفظ حریم خصوصی (بدون نگهداری IP خام).
	 */
	private static function get_ip_hash() {
		$ip = self::get_client_ip();
		if ( empty( $ip ) ) {
			return null;
		}
		$salt = get_option( 'cvs_salt', '' );
		return hash( 'sha256', $ip . $salt );
	}

	/**
	 * تنظیم کوکی نشست برای جلوگیری از شمارش تکراری بازدیدهای یک کاربر.
	 */
	private static function set_session_cookie( $minutes ) {
		if ( headers_sent() ) {
			return;
		}

		$minutes = max( 1, (int) $minutes );
		$token   = wp_generate_password( 20, false );
		$expire  = time() + ( $minutes * MINUTE_IN_SECONDS );

		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires'  => $expire,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
