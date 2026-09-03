<?php
/**
 * ماژول Schema / JSON-LD — هسته سئو.
 *
 * آداپتر روی Schema Generator + Detector.
 * Passive = واگذاری Product/Breadcrumb به Rank Math/Yoast؛ Override = صدور کامل دماوند.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Schema_Module
 */
class SEO_Core_Schema_Module extends SEO_Core_Module {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'schema';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'اسکیما (JSON-LD)', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'Product / Breadcrumb / FAQ و تشخیص تداخل JSON-LD — با Passive در برابر Rank Math و Override آگاهانه.', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		self::ensure_options();
	}

	/**
	 * گزینه‌های پیش‌فرض اسکیما.
	 */
	public static function ensure_options(): void {
		$defaults = array(
			'shojaei_seo_schema_enabled'               => 'yes',
			'shojaei_seo_schema_detect_enabled'        => 'yes',
			'shojaei_seo_disable_wc_schema'            => 'no',
			'shojaei_seo_schema_product_enabled'       => 'yes',
			'shojaei_seo_schema_breadcrumb_enabled'    => 'yes',
			'shojaei_seo_schema_faq_enabled'           => 'yes',
			'shojaei_seo_schema_respect_seo_plugins'   => 'yes',
		);
		foreach ( $defaults as $key => $val ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $val, '', false );
			}
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_core_schema', array( $this, 'ajax' ) );
		}
		$this->log( 'info', __( 'ماژول اسکیما آماده است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * آیا ماژول برای runtime مجاز است؟
	 */
	public static function is_runtime_allowed(): bool {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'schema' ) ) {
			return false;
		}
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && ! Shojaei_SEO_Helpers::is_module_enabled( 'schema' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Override هسته سئو برای صدور کامل حتی با رقیب.
	 */
	public static function wants_full_emit(): bool {
		return class_exists( 'SEO_Core_Installer' ) && SEO_Core_Installer::is_override_enabled( 'schema' );
	}

	/**
	 * AJAX ذخیره تنظیمات ماژول.
	 */
	public function ajax(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		$can = current_user_can( 'manage_options' )
			|| current_user_can( 'manage_woocommerce' )
			|| ( class_exists( 'SEO_Core_Installer' ) && current_user_can( SEO_Core_Installer::CAPABILITY ) );
		if ( ! $can ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['schema_action'] ?? '' ) );
		if ( 'save' !== $action ) {
			wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}

		$map = array(
			'shojaei_seo_schema_respect_seo_plugins' => 'respect',
			'shojaei_seo_schema_product_enabled'     => 'product',
			'shojaei_seo_schema_breadcrumb_enabled'  => 'breadcrumb',
			'shojaei_seo_schema_faq_enabled'         => 'faq',
			'shojaei_seo_schema_article_enabled'     => 'article',
			'shojaei_seo_schema_site_enabled'        => 'site',
			'shojaei_seo_schema_collection_enabled'  => 'collection',
			'shojaei_seo_schema_detect_enabled'      => 'detect',
			'shojaei_seo_disable_wc_schema'          => 'disable_wc',
		);
		foreach ( $map as $option => $post_key ) {
			update_option( $option, ! empty( $_POST[ $post_key ] ) ? 'yes' : 'no' );
		}

		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::invalidate_health_cache();
		}

		$mode = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::schema_mode_label()
			: '';

		wp_send_json_success(
			array(
				'message' => __( 'تنظیمات اسکیما ذخیره شد.', 'shojaei-seo-for-woo' ),
				'mode'    => $mode,
			)
		);
	}
}
