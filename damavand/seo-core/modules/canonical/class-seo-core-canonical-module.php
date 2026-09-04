<?php
/**
 * ماژول Canonical — هسته سئو.
 *
 * OWNER (settings / policies / boot): این کلاس.
 * OWNER (runtime HTML canonical URL): SEO_Core_Canonical_Resolver
 *   — sole filter owner for get_canonical_url / wpseo_canonical / rank_math.
 *   Former Damavand_Canonical + Shojaei_SEO_Canonical stacks were merged here (1.58).
 *
 * پوشش: variation→parent، facet strip، pagination self، سیاست‌های سایت‌واید.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Canonical_Module
 */
class SEO_Core_Canonical_Module extends SEO_Core_Module {

	public const OPTION_FORCE_HTTPS = 'seo_core_canonical_force_https';
	public const OPTION_STRIP_ARGS  = 'seo_core_canonical_strip_args';

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'canonical';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'کنونیکال', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'کنونیکال ورییشن محصول به والد + نرمال‌سازی سبک URL. فیلتر Rank Math/Yoast همیشه مکمل است.', 'shojaei-seo-for-woo' );
	}

	/**
	 * ورییشن→والد مکمل است؛ چاپ تگ مستقل در Passive خاموش می‌ماند (منطق Resolver).
	 * ماژول خودش Passive اجباری ندارد تا فیلتر خارجی کار کند.
	 */
	public function is_passive(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		self::ensure_options();
		if ( false === get_option( 'shojaei_seo_variation_canonical', false ) ) {
			add_option( 'shojaei_seo_variation_canonical', 'yes', '', false );
		}
	}

	/**
	 * گزینه‌های پیش‌فرض.
	 */
	public static function ensure_options(): void {
		if ( false === get_option( self::OPTION_FORCE_HTTPS, false ) ) {
			add_option( self::OPTION_FORCE_HTTPS, 'yes', '', false );
		}
		if ( false === get_option( self::OPTION_STRIP_ARGS, false ) ) {
			add_option( self::OPTION_STRIP_ARGS, 'yes', '', false );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function uninstall(): void {
		delete_option( self::OPTION_FORCE_HTTPS );
		delete_option( self::OPTION_STRIP_ARGS );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		if ( ! class_exists( 'SEO_Core_Canonical_Resolver' ) ) {
			require_once dirname( __FILE__ ) . '/class-seo-core-canonical-resolver.php';
		}
		// Single pipeline — policies applied inside resolver finalize(); do not add @30 policy filters here.
		SEO_Core_Canonical_Resolver::register_hooks();

		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_core_canonical', array( $this, 'ajax' ) );
		}
	}

	/**
	 * آیا موتور ورییشن canonical مجاز است؟
	 */
	public static function is_runtime_allowed(): bool {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'canonical' ) ) {
			return false;
		}
		return 'yes' === ( class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::get_option( 'shojaei_seo_variation_canonical', 'yes' )
			: get_option( 'shojaei_seo_variation_canonical', 'yes' ) );
	}

	/**
	 * سیاست‌های سایت‌واید (HTTPS + strip UTM).
	 *
	 * @param string $url URL.
	 */
	public static function apply_policies( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return $url;
		}

		if ( 'yes' === (string) get_option( self::OPTION_FORCE_HTTPS, 'yes' ) && 0 === strpos( $url, 'http://' ) ) {
			$url = 'https://' . substr( $url, 7 );
		}

		if ( 'yes' === (string) get_option( self::OPTION_STRIP_ARGS, 'yes' ) ) {
			$parts = wp_parse_url( $url );
			if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
				parse_str( (string) $parts['query'], $q );
				$strip = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_cid', 'mc_eid' );
				foreach ( $strip as $key ) {
					unset( $q[ $key ] );
				}
				$parts['query'] = $q ? http_build_query( $q ) : null;
				$url            = self::build_url( $parts );
			}
		}

		return esc_url_raw( $url );
	}

	/**
	 * @param array<string,mixed> $parts Parts.
	 */
	private static function build_url( array $parts ): string {
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = $parts['host'] ?? '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$user   = $parts['user'] ?? '';
		$pass   = isset( $parts['pass'] ) ? ':' . $parts['pass'] : '';
		$pass   = ( $user || $pass ) ? "$pass@" : '';
		$path   = $parts['path'] ?? '';
		$query  = ! empty( $parts['query'] ) ? '?' . $parts['query'] : '';
		$frag   = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$frag";
	}

	/**
	 * AJAX ذخیره.
	 */
	public function ajax(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		$can = current_user_can( 'manage_options' )
			|| current_user_can( 'manage_woocommerce' )
			|| ( class_exists( 'SEO_Core_Installer' ) && current_user_can( SEO_Core_Installer::CAPABILITY ) );
		if ( ! $can ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['canonical_action'] ?? '' ) );
		if ( 'save' !== $action ) {
			wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}

		$variation = ! empty( $_POST['variation'] ) ? 'yes' : 'no';
		$https     = ! empty( $_POST['force_https'] ) ? 'yes' : 'no';
		$strip     = ! empty( $_POST['strip_args'] ) ? 'yes' : 'no';

		update_option( 'shojaei_seo_variation_canonical', $variation );
		update_option( self::OPTION_FORCE_HTTPS, $https, false );
		update_option( self::OPTION_STRIP_ARGS, $strip, false );

		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::invalidate_health_cache();
		}

		wp_send_json_success(
			array(
				'message' => __( 'تنظیمات کنونیکال ذخیره شد. برای اعمال کامل یک بار صفحه را رفرش کنید.', 'shojaei-seo-for-woo' ),
			)
		);
	}
}
