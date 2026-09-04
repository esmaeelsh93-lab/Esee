<?php
/**
 * ماژول لینک داخلی / نابغه لینک — هسته سئو.
 *
 * مکمل است (Passive اجباری ندارد). جدول موجودی + نقشه کلمات را به Installer وصل می‌کند.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Links_Module
 */
class SEO_Core_Links_Module extends SEO_Core_Module {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'links';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'لینک داخلی (نابغه لینک)', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'نقشه کلمات، موجودی لینک، و آمار پست‌ها — مکمل Rank Math بدون تداخل خروجی رقابتی.', 'shojaei-seo-for-woo' );
	}

	/**
	 * همیشه مکمل.
	 */
	public function is_passive(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			Shojaei_SEO_Link_Genius::install();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		$this->log( 'info', __( 'ماژول لینک داخلی آماده است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * آیا موتور لینک‌ساز / نابغه لینک مجاز است؟
	 */
	public static function is_runtime_allowed(): bool {
		if ( ! class_exists( 'SEO_Core_Installer' ) ) {
			return true;
		}
		return SEO_Core_Installer::is_module_enabled( 'links' );
	}
}
