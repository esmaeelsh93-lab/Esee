<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * Session Replay (نسخه تجاری): ضبط واقعیِ مسیر حرکت ماوس/لمس، کلیک و اسکرول کاربر برای پخش مجدد.
 *
 * تضمین حریم خصوصی: این ماژول هرگز به مقدار فیلدهای فرم (ورودی، ایمیل، رمز، شماره کارت و ...)
 * دسترسی پیدا نمی‌کند و آن را ثبت نمی‌کند؛ فقط مختصات نسبی حرکت/کلیک/اسکرول ذخیره می‌شود.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Session_Replay {

	const REST_NAMESPACE = 'amar-alborz/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_tracker' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/replay',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_submit( WP_REST_Request $request ) {
		$settings = AAW_Admin::get_settings();
		if ( empty( $settings['replay_enabled'] ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$params     = $request->get_json_params();
		$session_id = isset( $params['sid'] ) ? sanitize_text_field( $params['sid'] ) : '';
		$page_url   = isset( $params['url'] ) ? sanitize_text_field( $params['url'] ) : '';
		$events     = isset( $params['events'] ) && is_array( $params['events'] ) ? $params['events'] : array();
		$meta       = isset( $params['meta'] ) && is_array( $params['meta'] ) ? $params['meta'] : array();

		if ( empty( $session_id ) || empty( $events ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$device_type = isset( $meta['device'] ) ? sanitize_key( $meta['device'] ) : 'desktop';
		$browser     = isset( $meta['browser'] ) ? sanitize_text_field( $meta['browser'] ) : '';

		AAW_DB::upsert_replay_session(
			$session_id,
			array(
				'device_type' => $device_type,
				'browser'     => $browser,
				'entry_url'   => $page_url,
				'new_page'    => ! empty( $meta['new_page'] ),
			)
		);

		$clean_events = array();
		foreach ( array_slice( $events, 0, 500 ) as $event ) {
			if ( empty( $event['type'] ) || ! in_array( $event['type'], array( 'move', 'click', 'scroll', 'pageview' ), true ) ) {
				continue;
			}
			$clean_events[] = array(
				'url'    => $page_url,
				'type'   => $event['type'],
				'x'      => isset( $event['x'] ) ? max( 0, min( 100, (float) $event['x'] ) ) : null,
				'y'      => isset( $event['y'] ) ? max( 0, min( 100, (float) $event['y'] ) ) : null,
				'scroll' => isset( $event['scroll'] ) ? max( 0, min( 100, (int) $event['scroll'] ) ) : null,
				't'      => isset( $event['t'] ) ? (int) $event['t'] : 0,
			);
		}

		if ( empty( $clean_events ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		AAW_DB::insert_replay_events( $session_id, $clean_events );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * فقط زمانی اسکریپت ضبط جلسه به صفحه‌ی سایت اضافه می‌شود که این ویژگی از تنظیمات
	 * فعال شده باشد (پیش‌فرض: غیرفعال، چون یک ویژگی نسخه تجاری است).
	 */
	public static function maybe_enqueue_tracker() {
		if ( is_admin() ) {
			return;
		}

		$settings = AAW_Admin::get_settings();
		if ( empty( $settings['replay_enabled'] ) ) {
			return;
		}

		wp_enqueue_script(
			'aaw-replay-tracker',
			AAW_PLUGIN_URL . 'assets/js/session-replay-tracker.js',
			array(),
			AAW_VERSION,
			true
		);

		wp_localize_script(
			'aaw-replay-tracker',
			'aawReplayConfig',
			array(
				'endpoint'   => rest_url( self::REST_NAMESPACE . '/replay' ),
				'cookieName' => AAW_Tracker::COOKIE_NAME,
			)
		);
	}
}
