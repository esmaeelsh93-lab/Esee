<?php
/**
 * ردیابی ناهمگام سمت کاربر، مستقل از کش کامل صفحه.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Tracker {

	const REST_NAMESPACE = 'custom-visit-stats/v1';

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracker' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * اسکریپت سبک ردیابی را در صفحات عمومی بارگذاری می‌کند.
	 */
	public static function enqueue_tracker() {
		if ( is_admin() || is_feed() || is_robots() || is_preview() ) {
			return;
		}

		$settings = CVS_Admin::get_settings();
		$enabled  = ! ( ! empty( $settings['exclude_staff'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) );

		wp_enqueue_script(
			'cvs-tracker',
			CVS_PLUGIN_URL . 'assets/js/tracker.js',
			array(),
			CVS_VERSION,
			true
		);

		wp_localize_script(
			'cvs-tracker',
			'cvsTrackerSettings',
			array(
				'enabled'         => $enabled,
				'endpoint'        => esc_url_raw( rest_url( self::REST_NAMESPACE . '/collect' ) ),
				'sessionEndpoint' => esc_url_raw( rest_url( self::REST_NAMESPACE . '/session' ) ),
				'sessionMinutes'  => max( 1, (int) $settings['session_timeout'] ),
				'cookieLess'      => ! empty( $settings['cookie_less'] ),
			)
		);
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/collect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'collect' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'update_session' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * دریافت و ثبت رویداد pageview.
	 *
	 * Endpoint عمومی است تا روی صفحات کش‌شده نیز کار کند؛ اعتبارسنجی میزبان،
	 * فیلتر ربات و event_id یکتا جلوی داده‌ی تکراری و ورودی نامعتبر را می‌گیرد.
	 */
	public static function collect( WP_REST_Request $request ) {
		if ( ! self::should_accept_request() ) {
			return new WP_Error( 'cvs_ignored', 'این درخواست قابل ردیابی نیست.', array( 'status' => 202 ) );
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'cvs_invalid_payload', 'بدنه‌ی درخواست معتبر نیست.', array( 'status' => 400 ) );
		}

		$session_token = self::sanitize_token( isset( $payload['session_id'] ) ? $payload['session_id'] : '' );
		$visitor_token = self::sanitize_token( isset( $payload['visitor_id'] ) ? $payload['visitor_id'] : '' );
		$event_token   = self::sanitize_token( isset( $payload['event_id'] ) ? $payload['event_id'] : '' );

		if ( ! $session_token || ! $event_token ) {
			return new WP_Error( 'cvs_missing_identifier', 'شناسه‌ی نشست یا رویداد ارسال نشده است.', array( 'status' => 400 ) );
		}

		$page = self::normalize_page_url( isset( $payload['page_url'] ) ? $payload['page_url'] : '' );
		if ( ! $page ) {
			return new WP_Error( 'cvs_invalid_page', 'نشانی صفحه متعلق به این سایت نیست.', array( 'status' => 400 ) );
		}

		$referrer = isset( $payload['referrer'] ) ? esc_url_raw( $payload['referrer'] ) : '';
		$utm      = isset( $payload['utm'] ) && is_array( $payload['utm'] ) ? $payload['utm'] : array();
		$query    = array(
			'utm_source' => isset( $utm['source'] ) ? sanitize_text_field( $utm['source'] ) : '',
		);
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$source    = CVS_Source_Detector::detect( $referrer, $query, $home_host );
		$client    = self::detect_client();
		$ip        = self::get_client_ip();

		$session_id  = self::hash_identifier( 'session|' . $session_token );
		$event_id    = self::hash_identifier( 'event|' . $event_token );
		$visitor_raw = $visitor_token ? $visitor_token : $session_token;
		$visitor_hash = self::hash_identifier(
			'visitor|' . $visitor_raw . '|' . $ip . '|' . self::get_user_agent() . '|' . current_time( 'Y-m-d' )
		);

		$inserted = CVS_DB::insert_visit(
			array(
				'event_id'      => $event_id,
				'session_id'    => $session_id,
				'visitor_hash'  => $visitor_hash,
				'source_key'    => $source['source_key'],
				'source_label'  => $source['source_label'],
				'referrer_host' => $source['referrer_host'],
				'referrer_url'  => $referrer ? mb_substr( $referrer, 0, 1000 ) : null,
				'page_url'      => $page['url'],
				'request_path'  => $page['path'],
				'utm_source'    => isset( $utm['source'] ) ? mb_substr( sanitize_text_field( $utm['source'] ), 0, 150 ) : null,
				'utm_medium'    => isset( $utm['medium'] ) ? mb_substr( sanitize_text_field( $utm['medium'] ), 0, 150 ) : null,
				'utm_campaign'  => isset( $utm['campaign'] ) ? mb_substr( sanitize_text_field( $utm['campaign'] ), 0, 190 ) : null,
				'device_type'   => $client['device'],
				'browser'       => $client['browser'],
				'os'            => $client['os'],
				'country'       => self::header_value( 'HTTP_CF_IPCOUNTRY', 2 ),
				'city'          => self::header_value( 'HTTP_CF_IPCITY', 120 ),
				'ip_hash'       => $ip ? self::hash_identifier( 'ip|' . $ip . '|' . current_time( 'Y-m-d' ) ) : null,
				'is_bot'        => 0,
			)
		);

		if ( false === $inserted ) {
			return new WP_REST_Response( array( 'accepted' => true, 'duplicate' => true ), 200 );
		}

		return new WP_REST_Response( array( 'accepted' => true, 'id' => $inserted ), 201 );
	}

	/**
	 * مدت حضور را با sendBeacon در رویداد pagehide دریافت می‌کند.
	 */
	public static function update_session( WP_REST_Request $request ) {
		if ( self::is_bot_user_agent() ) {
			return new WP_REST_Response( array( 'accepted' => false ), 202 );
		}

		$payload = $request->get_json_params();
		$token   = is_array( $payload ) && isset( $payload['session_id'] ) ? self::sanitize_token( $payload['session_id'] ) : '';
		$seconds = is_array( $payload ) && isset( $payload['duration'] ) ? absint( $payload['duration'] ) : 0;

		if ( ! $token ) {
			return new WP_Error( 'cvs_invalid_session', 'شناسه‌ی نشست معتبر نیست.', array( 'status' => 400 ) );
		}

		CVS_DB::update_session_duration( self::hash_identifier( 'session|' . $token ), $seconds );
		return new WP_REST_Response( array( 'accepted' => true ), 200 );
	}

	private static function should_accept_request() {
		$settings = CVS_Admin::get_settings();

		if ( self::is_prefetch_request() || self::is_bot_user_agent() || self::is_excluded_ip( $settings ) ) {
			return false;
		}

		if ( ! empty( $settings['exclude_staff'] ) && self::is_staff_request() ) {
			return false;
		}

		return true;
	}

	private static function is_staff_request() {
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return true;
		}

		$user_id = function_exists( 'wp_validate_auth_cookie' ) ? wp_validate_auth_cookie( '', 'logged_in' ) : 0;
		return $user_id && user_can( $user_id, 'edit_posts' );
	}

	private static function is_prefetch_request() {
		$purpose = '';
		if ( ! empty( $_SERVER['HTTP_SEC_PURPOSE'] ) ) {
			$purpose .= ' ' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_PURPOSE'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_PURPOSE'] ) ) {
			$purpose .= ' ' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_PURPOSE'] ) );
		}
		if ( ! empty( $_SERVER['HTTP_X_PURPOSE'] ) ) {
			$purpose .= ' ' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PURPOSE'] ) );
		}

		return false !== stripos( $purpose, 'prefetch' ) || false !== stripos( $purpose, 'prerender' );
	}

	private static function is_excluded_ip( $settings ) {
		if ( empty( $settings['excluded_ips'] ) ) {
			return false;
		}

		$current = self::get_client_ip();
		$excluded = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $settings['excluded_ips'] ) ) );
		return $current && in_array( $current, $excluded, true );
	}

	/**
	 * تشخیص ربات‌های جستجو، مانیتورینگ و مرورگرهای headless.
	 */
	public static function is_bot_user_agent( $user_agent = null ) {
		$ua = null === $user_agent ? self::get_user_agent() : strtolower( (string) $user_agent );
		if ( '' === $ua ) {
			return true;
		}

		$signatures = array(
			'bot', 'spider', 'crawl', 'slurp', 'facebookexternalhit', 'preview',
			'curl', 'wget', 'python-requests', 'python-urllib', 'httpclient', 'okhttp',
			'go-http-client', 'ahrefs', 'semrush', 'mj12bot', 'dotbot', 'petalbot',
			'bingpreview', 'yandex', 'pingdom', 'uptimerobot', 'statuscake',
			'headlesschrome', 'phantomjs', 'lighthouse', 'pagespeed', 'gtmetrix',
		);

		foreach ( $signatures as $signature ) {
			if ( false !== strpos( $ua, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * IP واقعی: Cloudflare، سپس X-Forwarded-For و در پایان REMOTE_ADDR.
	 * اعتماد به X-Forwarded-For با فیلتر cvs_trust_forwarded_for قابل محدودسازی است.
	 */
	public static function get_client_ip() {
		$remote_ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? self::validated_ip( $_SERVER['REMOTE_ADDR'] ) : '';

		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && ! empty( $_SERVER['HTTP_CF_RAY'] ) ) {
			$ip = self::validated_ip( $_SERVER['HTTP_CF_CONNECTING_IP'] );
			if ( $ip ) {
				return $ip;
			}
		}

		$remote_is_private = $remote_ip && ! filter_var(
			$remote_ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
		$trust_forwarded = apply_filters( 'cvs_trust_forwarded_for', (bool) $remote_is_private, $remote_ip );

		if ( $trust_forwarded && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			foreach ( $forwarded as $candidate ) {
				$ip = self::validated_ip( $candidate );
				if ( $ip ) {
					return $ip;
				}
			}
		}

		return $remote_ip;
	}

	private static function validated_ip( $value ) {
		$value = trim( sanitize_text_field( wp_unslash( $value ) ) );
		return filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}

	private static function normalize_page_url( $url ) {
		$url   = esc_url_raw( $url );
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url() );

		if ( empty( $parts['host'] ) || empty( $home['host'] ) ) {
			return false;
		}

		$page_host = strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
		$home_host = strtolower( preg_replace( '/^www\./', '', $home['host'] ) );
		if ( $page_host !== $home_host ) {
			return false;
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		return array(
			'url'  => mb_substr( $url, 0, 2000 ),
			'path' => mb_substr( sanitize_text_field( $path ), 0, 500 ),
		);
	}

	private static function detect_client() {
		$ua = self::get_user_agent();

		$device = 'desktop';
		if ( preg_match( '/ipad|tablet|kindle|silk/', $ua ) ) {
			$device = 'tablet';
		} elseif ( preg_match( '/mobile|iphone|ipod|android/', $ua ) ) {
			$device = 'mobile';
		}

		$browser = 'سایر';
		$browsers = array(
			'Edg/'     => 'Edge',
			'OPR/'     => 'Opera',
			'Chrome/'  => 'Chrome',
			'Firefox/' => 'Firefox',
			'Safari/'  => 'Safari',
		);
		foreach ( $browsers as $needle => $label ) {
			if ( false !== stripos( $ua, $needle ) ) {
				$browser = $label;
				break;
			}
		}

		$os = 'سایر';
		$systems = array(
			'Windows'   => 'Windows',
			'Android'   => 'Android',
			'iPhone'    => 'iOS',
			'iPad'      => 'iPadOS',
			'Macintosh' => 'macOS',
			'Linux'     => 'Linux',
		);
		foreach ( $systems as $needle => $label ) {
			if ( false !== stripos( $ua, $needle ) ) {
				$os = $label;
				break;
			}
		}

		return array( 'device' => $device, 'browser' => $browser, 'os' => $os );
	}

	private static function get_user_agent() {
		return empty( $_SERVER['HTTP_USER_AGENT'] )
			? ''
			: strtolower( mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) );
	}

	private static function sanitize_token( $value ) {
		$value = is_string( $value ) ? $value : '';
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{16,128}$/', $value ) ) {
			return '';
		}
		return $value;
	}

	private static function hash_identifier( $value ) {
		$salt = get_option( 'cvs_salt', wp_salt( 'auth' ) );
		return hash_hmac( 'sha256', $value, $salt );
	}

	private static function header_value( $key, $length ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			return null;
		}
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		return mb_substr( $value, 0, $length );
	}
}
