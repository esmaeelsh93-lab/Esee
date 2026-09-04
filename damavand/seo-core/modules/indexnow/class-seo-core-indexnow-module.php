<?php
/**
 * ماژول نمایه‌سازی فوری (IndexNow) — هسته سئو.
 *
 * ارسال دستی گروهی + تنظیمات کلید؛ اتوماتیک در Shojaei_SEO_IndexNow.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_IndexNow_Module
 */
class SEO_Core_IndexNow_Module extends SEO_Core_Module {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'indexnow';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'نمایه‌سازی فوری', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'ارسال دستی و خودکار URL به IndexNow (Bing/Yandex) — مکمل نقشه سایت.', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_indexnow_manual', array( $this, 'ajax_manual' ) );
		}
	}

	/**
	 * AJAX: ارسال دستی / ذخیره کلید / پاک تاریخچه.
	 */
	public function ajax_manual(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}
		if ( ! class_exists( 'Shojaei_SEO_IndexNow' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول IndexNow در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['in_action'] ?? '' ) );
		$engine = new Shojaei_SEO_IndexNow( false );

		switch ( $action ) {
			case 'submit':
				$raw  = isset( $_POST['urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['urls'] ) ) : '';
				$lines = preg_split( '/\r\n|\r|\n/', $raw ) ?: array();
				$result = $engine->submit_urls( $lines, true );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'save_key':
				$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
				$key = preg_replace( '/[^a-zA-Z0-9\-]/', '', $key );
				if ( strlen( $key ) < 8 ) {
					wp_send_json_error( array( 'message' => __( 'کلید باید حداقل ۸ کاراکتر لاتین/عدد باشد.', 'shojaei-seo-for-woo' ) ) );
				}
				if ( class_exists( 'SEO_Core_Installer' ) ) {
					SEO_Core_Installer::set_indexnow_key( $key );
				} else {
					update_option( 'seo_core_indexnow_key', $key );
					update_option( 'shojaei_seo_indexnow_key', $key );
				}
				update_option( 'shojaei_seo_indexnow_enabled', ! empty( $_POST['enabled'] ) ? 'yes' : 'no' );
				if ( class_exists( 'SEO_Core_Installer' ) ) {
					SEO_Core_Installer::request_rewrite_flush();
				} else {
					update_option( 'seo_core_rewrite_needs_flush', '1', false );
				}
				wp_send_json_success(
					array(
						'message' => __( 'تنظیمات IndexNow ذخیره شد.', 'shojaei-seo-for-woo' ),
						'key_url' => home_url( '/' . $key . '.txt' ),
					)
				);
				break;

			case 'clear_history':
				Shojaei_SEO_IndexNow::clear_history();
				wp_send_json_success( array( 'message' => __( 'تاریخچه پاک شد.', 'shojaei-seo-for-woo' ) ) );
				break;

			case 'scan_suggest':
				$result = Shojaei_SEO_IndexNow::suggest_from_slug_redirects( 50 );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'confirm_pending':
				$ids_raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_string( $ids_raw ) ) {
					$ids = array_filter( array_map( 'sanitize_text_field', explode( ',', $ids_raw ) ) );
				} elseif ( is_array( $ids_raw ) ) {
					$ids = array_map( 'sanitize_text_field', $ids_raw );
				} else {
					$ids = array();
				}
				$result = $engine->confirm_pending( $ids );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'dismiss_pending':
				$ids_raw = isset( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_string( $ids_raw ) ) {
					$ids = array_filter( array_map( 'sanitize_text_field', explode( ',', $ids_raw ) ) );
				} elseif ( is_array( $ids_raw ) ) {
					$ids = array_map( 'sanitize_text_field', $ids_raw );
				} else {
					$ids = array();
				}
				$result = Shojaei_SEO_IndexNow::dismiss_pending( $ids );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}
}
