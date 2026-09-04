<?php
/**
 * ماژول Robots.txt — هسته سئو.
 *
 * OWNER: robots.txt virtual file only (`robots_txt` filter).
 * HTML meta robots / crawl-budget noindex: Damavand_Robots + Shojaei_SEO_General_Meta.
 * Keep these stacks parallel — do not merge into this module.
 *
 * ویرایش/افزودن به robots.txt مجازی وردپرس. در Passive با Rank Math/Yoast تداخل نمی‌کند.
 * متای robots صفحه در «متای عمومی» باقی می‌ماند.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Robots_Module
 */
class SEO_Core_Robots_Module extends SEO_Core_Module {

	public const OPTION_MODE    = 'seo_core_robots_mode';
	public const OPTION_EXTRA   = 'seo_core_robots_extra';
	public const OPTION_SITEMAP = 'seo_core_robots_add_sitemap';

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'robots';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'ربات‌ها (robots.txt)', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'مدیریت محتوای robots.txt مجازی وردپرس و افزودن Sitemap — بدون جنگ با ویرایشگر Rank Math در حالت کمکی.', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		self::ensure_options();
	}

	/**
	 * گزینه‌های پیش‌فرض.
	 */
	public static function ensure_options(): void {
		if ( false === get_option( self::OPTION_MODE, false ) ) {
			add_option( self::OPTION_MODE, 'append', '', false );
		}
		if ( false === get_option( self::OPTION_EXTRA, false ) ) {
			add_option( self::OPTION_EXTRA, self::default_extra(), '', false );
		}
		if ( false === get_option( self::OPTION_SITEMAP, false ) ) {
			add_option( self::OPTION_SITEMAP, 'yes', '', false );
		}
	}

	/**
	 * متن پیش‌فرض کمکی.
	 */
	public static function default_extra(): string {
		return "# Damavand SEO\nUser-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
	}

	/**
	 * {@inheritdoc}
	 */
	public function uninstall(): void {
		delete_option( self::OPTION_MODE );
		delete_option( self::OPTION_EXTRA );
		delete_option( self::OPTION_SITEMAP );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 99, 2 );
		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_core_robots', array( $this, 'ajax' ) );
		}
	}

	/**
	 * فیلتر robots.txt.
	 *
	 * @param string $output Current.
	 * @param bool   $public Blog public.
	 */
	public function filter_robots_txt( string $output, $public ): string {
		if ( ! $this->can_emit() ) {
			return $output;
		}
		if ( ! $public ) {
			return $output;
		}

		$mode  = (string) get_option( self::OPTION_MODE, 'append' );
		$extra = (string) get_option( self::OPTION_EXTRA, '' );
		$extra = str_replace( array( "\r\n", "\r" ), "\n", $extra );
		$extra = trim( $extra );

		if ( 'replace' === $mode && '' !== $extra ) {
			$output = $extra . "\n";
		} elseif ( '' !== $extra ) {
			$output = rtrim( $output ) . "\n\n" . $extra . "\n";
		}

		if ( 'yes' === (string) get_option( self::OPTION_SITEMAP, 'yes' ) ) {
			$sitemap = self::preferred_sitemap_url();
			if ( $sitemap && false === stripos( $output, $sitemap ) ) {
				$output = rtrim( $output ) . "\nSitemap: " . $sitemap . "\n";
			}
		}

		return $output;
	}

	/**
	 * URL نقشه سایت ترجیحی.
	 */
	public static function preferred_sitemap_url(): string {
		if ( class_exists( 'SEO_Core_Loader' ) ) {
			$loader = SEO_Core_Loader::instance();
			$sm     = $loader ? $loader->get_module( 'sitemap' ) : null;
			if ( $sm && method_exists( $sm, 'can_emit' ) && $sm->can_emit() && method_exists( $sm, 'public_url' ) ) {
				return (string) $sm->public_url( 'index' );
			}
		}
		return home_url( '/wp-sitemap.xml' );
	}

	/**
	 * پیش‌نمایش متن نهایی (شبیه‌سازی سبک).
	 */
	public static function preview_output(): string {
		$public = ( '1' === (string) get_option( 'blog_public', '1' ) );
		$base   = "User-agent: *\nDisallow:\n";
		if ( ! $public ) {
			$base = "User-agent: *\nDisallow: /\n";
		}
		$mod = null;
		if ( class_exists( 'SEO_Core_Loader' ) ) {
			$loader = SEO_Core_Loader::instance();
			$mod    = $loader ? $loader->get_module( 'robots' ) : null;
		}
		if ( $mod instanceof self ) {
			return $mod->filter_robots_txt( $base, $public );
		}
		return $base;
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

		$action = sanitize_key( wp_unslash( $_POST['robots_action'] ?? '' ) );
		if ( 'save' !== $action ) {
			wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}

		$mode = sanitize_key( wp_unslash( $_POST['mode'] ?? 'append' ) );
		if ( ! in_array( $mode, array( 'append', 'replace' ), true ) ) {
			$mode = 'append';
		}
		$extra = isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : '';
		// محدود کردن اندازه برای جلوگیری از گزینهٔ غول‌پیکر.
		if ( strlen( $extra ) > 20000 ) {
			$extra = substr( $extra, 0, 20000 );
		}
		$add_sm = ! empty( $_POST['add_sitemap'] ) ? 'yes' : 'no';

		update_option( self::OPTION_MODE, $mode, false );
		update_option( self::OPTION_EXTRA, $extra, false );
		update_option( self::OPTION_SITEMAP, $add_sm, false );

		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::invalidate_health_cache();
		}

		wp_send_json_success(
			array(
				'message' => __( 'تنظیمات robots.txt ذخیره شد.', 'shojaei-seo-for-woo' ),
				'preview' => self::preview_output(),
			)
		);
	}
}
