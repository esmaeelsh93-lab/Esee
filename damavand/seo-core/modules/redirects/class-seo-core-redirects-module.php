<?php
/**
 * ماژول ریدایرکت‌ها — هسته سئو (آداپتر روی Manual Redirect + Audit).
 *
 * خروجی رقابتی (ریدایرکت دستی آزاد) در Passive خاموش می‌شود تا با Rank Math تداخل نکند.
 * ریدایرکت‌های عملیاتی ووکامرس (slug / OOS) مستقل می‌مانند.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Redirects_Module
 */
class SEO_Core_Redirects_Module extends SEO_Core_Module {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'redirects';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'ریدایرکت‌ها', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'ریدایرکت دستی آزاد، سلامت زنجیره/شکسته، و اتصال به نامک محصول — بدون جنگ با Rank Math در حالت کمکی.', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		if ( class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
			Shojaei_SEO_Manual_Redirect::install();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// موتور emit در Shojaei_SEO_Manual_Redirect::maybe_redirect با can_emit_freeform() گیت می‌شود.
		// اینجا فقط لاگ سبک بوت.
		$this->log( 'info', __( 'ماژول ریدایرکت‌ها آماده است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * آیا ریدایرکت دستی آزاد مجاز به اجراست؟
	 */
	public static function can_emit_freeform(): bool {
		if ( ! class_exists( 'SEO_Core_Installer' ) ) {
			return true;
		}
		if ( ! SEO_Core_Installer::is_module_enabled( 'redirects' ) ) {
			return false;
		}
		if ( SEO_Core_Installer::is_override_enabled( 'redirects' ) ) {
			return true;
		}
		if ( class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::has_primary_seo_plugin() ) {
			return false;
		}
		return true;
	}
}
