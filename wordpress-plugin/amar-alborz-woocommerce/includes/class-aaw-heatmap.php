<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * Heatmap (نسخه تجاری): ثبت واقعی نقاط کلیک و عمق اسکرول بازدیدکنندگان روی صفحات فروشگاه.
 * ردیابی کاملاً سبک است (رویدادها دسته‌ای و با فاصله‌ی زمانی ارسال می‌شوند) و هیچ داده‌ی
 * حساس (مقدار فرم‌ها، اطلاعات پرداخت و ...) جمع‌آوری نمی‌شود؛ فقط مختصات نسبی کلیک/اسکرول.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Heatmap {

	const REST_NAMESPACE = 'amar-alborz/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_tracker' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/heatmap',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_submit( WP_REST_Request $request ) {
		$settings = AAW_Admin::get_settings();
		if ( empty( $settings['heatmap_enabled'] ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$params  = $request->get_json_params();
		$page_url = isset( $params['url'] ) ? sanitize_text_field( $params['url'] ) : '';
		$events   = isset( $params['events'] ) && is_array( $params['events'] ) ? $params['events'] : array();
		$device   = isset( $params['device'] ) ? sanitize_key( $params['device'] ) : 'desktop';

		if ( empty( $page_url ) || empty( $events ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$clean_events = array();
		foreach ( array_slice( $events, 0, 200 ) as $event ) {
			if ( empty( $event['type'] ) || ! in_array( $event['type'], array( 'click', 'scroll' ), true ) ) {
				continue;
			}
			$clean_events[] = array(
				'type'   => $event['type'],
				'x'      => isset( $event['x'] ) ? max( 0, min( 100, (float) $event['x'] ) ) : null,
				'y'      => isset( $event['y'] ) ? max( 0, min( 100, (float) $event['y'] ) ) : null,
				'scroll' => isset( $event['scroll'] ) ? max( 0, min( 100, (int) $event['scroll'] ) ) : null,
				'vw'     => isset( $event['vw'] ) ? (int) $event['vw'] : null,
				'vh'     => isset( $event['vh'] ) ? (int) $event['vh'] : null,
			);
		}

		if ( empty( $clean_events ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}

		AAW_DB::insert_heatmap_events( $page_url, $clean_events, $device );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * فقط زمانی اسکریپت ردیاب Heatmap را به صفحه‌ی سایت اضافه می‌کند که این ویژگی
	 * از تنظیمات فعال شده باشد (پیش‌فرض: غیرفعال، چون یک ویژگی نسخه تجاری است).
	 */
	public static function maybe_enqueue_tracker() {
		if ( is_admin() ) {
			return;
		}

		$settings = AAW_Admin::get_settings();
		if ( empty( $settings['heatmap_enabled'] ) ) {
			return;
		}

		wp_enqueue_script(
			'aaw-heatmap-tracker',
			AAW_PLUGIN_URL . 'assets/js/heatmap-tracker.js',
			array(),
			AAW_VERSION,
			true
		);

		wp_localize_script(
			'aaw-heatmap-tracker',
			'aawHeatmapConfig',
			array(
				'endpoint' => rest_url( self::REST_NAMESPACE . '/heatmap' ),
			)
		);
	}
}
