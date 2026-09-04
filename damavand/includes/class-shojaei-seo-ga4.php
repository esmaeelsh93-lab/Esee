<?php
/**
 * Google Analytics 4 — Measurement ID + gtag async.
 *
 * فقط به API رسمی گوگل (gtag.js) متصل می‌شود؛ داده به سرور ثالث ارسال نمی‌شود.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_GA4
 */
class Shojaei_SEO_GA4 {

	public const OPTION_ID      = 'shojaei_seo_ga4_measurement_id';
	public const OPTION_ENABLED = 'shojaei_seo_ga4_enabled';

	/**
	 * Measurement ID نرمال‌شده (مثلاً G-XXXX) یا خالی.
	 */
	public static function get_measurement_id(): string {
		$raw = class_exists( 'Shojaei_SEO_Helpers' )
			? (string) Shojaei_SEO_Helpers::get_option( self::OPTION_ID, '' )
			: (string) get_option( self::OPTION_ID, '' );
		return self::sanitize_measurement_id( $raw );
	}

	/**
	 * @param string $raw Raw.
	 */
	public static function sanitize_measurement_id( string $raw ): string {
		$raw = strtoupper( trim( $raw ) );
		if ( ! preg_match( '/^G-[A-Z0-9]+$/', $raw ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * آیا چاپ gtag مجاز است؟
	 */
	public static function should_print(): bool {
		if ( 'yes' !== ( class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::get_option( self::OPTION_ENABLED, 'yes' )
			: get_option( self::OPTION_ENABLED, 'yes' ) ) ) {
			return false;
		}
		if ( '' === self::get_measurement_id() ) {
			return false;
		}
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'advanced-analytics' ) ) {
			return false;
		}
		// جلوگیری از دوبل با افزونه‌های رایج Analytics.
		if ( self::has_analytics_competitor() ) {
			$force = class_exists( 'Shojaei_SEO_Helpers' )
				? Shojaei_SEO_Helpers::get_option( 'shojaei_seo_ga4_force', 'no' )
				: get_option( 'shojaei_seo_ga4_force', 'no' );
			return 'yes' === $force;
		}
		return true;
	}

	/**
	 * MonsterInsights / ExactMetrics / Site Kit و مشابه.
	 */
	public static function has_analytics_competitor(): bool {
		$checks = array(
			'MonsterInsights_Plugin',
			'ExactMetrics_Plugin',
			'Google\Site_Kit\Plugin',
			'Ga_Admin',
		);
		foreach ( $checks as $class ) {
			if ( class_exists( $class ) ) {
				return true;
			}
		}
		if ( defined( 'MONSTERINSIGHTS_VERSION' ) || defined( 'EXACTMETRICS_VERSION' ) || defined( 'GOOGLESITEKIT_VERSION' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * ثبت هوک فرانت.
	 */
	public static function register_hooks(): void {
		add_action( 'wp_head', array( __CLASS__, 'print_gtag' ), 5 );
	}

	/**
	 * اسکریپت رسمی gtag.js به‌صورت async.
	 */
	public static function print_gtag(): void {
		if ( is_admin() || ! self::should_print() ) {
			return;
		}
		$id = self::get_measurement_id();
		if ( '' === $id ) {
			return;
		}

		$src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- GA4 official snippet; async intentional.
		echo '<script async src="' . esc_url( $src ) . '"></script>' . "\n";
		echo "<script>\n";
		echo "window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}\n";
		echo "gtag('js', new Date());\n";
		echo "gtag('config', " . wp_json_encode( $id ) . ");\n";
		echo "</script>\n";
	}

	/**
	 * ذخیره از درخواست ادمین.
	 *
	 * @param string $id      Measurement ID.
	 * @param bool   $enabled Enabled.
	 * @param bool   $force   Force with competitors.
	 */
	public static function save( string $id, bool $enabled = true, bool $force = false ): string {
		$clean = self::sanitize_measurement_id( $id );
		update_option( self::OPTION_ID, $clean, false );
		update_option( self::OPTION_ENABLED, $enabled ? 'yes' : 'no', false );
		update_option( 'shojaei_seo_ga4_force', $force ? 'yes' : 'no', false );
		return $clean;
	}
}
