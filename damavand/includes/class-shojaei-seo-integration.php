<?php
/**
 * Integration policy with Yoast, Rank Math, and other SEO plugins.
 *
 * Strategic rule: operations (OOS, redirects, recovery) are core;
 * meta and competing Product schema stay with the primary SEO plugin.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Integration
 */
class Shojaei_SEO_Integration {

	/**
	 * Known primary SEO plugins (slug => label).
	 *
	 * @return array<string,string>
	 */
	public static function known_seo_plugins(): array {
		return array(
			'seo-by-rank-math/rank-math.php'           => 'Rank Math',
			'wordpress-seo/wp-seo.php'                 => 'Yoast SEO',
			'wordpress-seo-premium/wp-seo-premium.php' => 'Yoast SEO Premium',
			'wp-seopress/seopress.php'                 => 'SEOPress',
			'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
		);
	}

	/**
	 * Ensure plugin.php helpers are loaded.
	 */
	private static function ensure_plugin_functions(): void {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	/**
	 * Whether a plugin file is active.
	 *
	 * @param string $file Plugin basename.
	 */
	public static function is_plugin_file_active( string $file ): bool {
		self::ensure_plugin_functions();
		return (bool) is_plugin_active( $file );
	}

	/**
	 * All in One SEO active.
	 */
	public static function is_aioseo_active(): bool {
		return self::is_plugin_file_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' )
			|| class_exists( 'AIOSEO\\Plugin\\AIOSEO' )
			|| function_exists( 'aioseo' )
			|| defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Rank Math active.
	 */
	public static function is_rank_math_active(): bool {
		return self::is_plugin_file_active( 'seo-by-rank-math/rank-math.php' )
			|| class_exists( 'RankMath' )
			|| defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * Yoast SEO (free or premium) active.
	 */
	public static function is_yoast_active(): bool {
		return self::is_plugin_file_active( 'wordpress-seo/wp-seo.php' )
			|| self::is_plugin_file_active( 'wordpress-seo-premium/wp-seo-premium.php' )
			|| defined( 'WPSEO_VERSION' )
			|| class_exists( 'WPSEO_Options' );
	}

	/**
	 * List of active primary SEO plugins.
	 *
	 * @return array<int,array{file:string,name:string}>
	 */
	public static function detected_seo_plugins(): array {
		$found = array();
		foreach ( self::known_seo_plugins() as $file => $name ) {
			if ( self::is_plugin_file_active( $file ) ) {
				$found[] = array(
					'file' => $file,
					'name' => $name,
				);
			}
		}

		// Class/constant fallbacks when basename differs (mu-plugins, renamed).
		if ( self::is_rank_math_active() && ! self::list_has_name( $found, 'Rank Math' ) ) {
			$found[] = array(
				'file' => 'seo-by-rank-math/rank-math.php',
				'name' => 'Rank Math',
			);
		}
		if ( self::is_yoast_active() && ! self::list_has_name( $found, 'Yoast SEO' ) && ! self::list_has_name( $found, 'Yoast SEO Premium' ) ) {
			$found[] = array(
				'file' => 'wordpress-seo/wp-seo.php',
				'name' => 'Yoast SEO',
			);
		}
		if ( self::is_aioseo_active() && ! self::list_has_name( $found, 'All in One SEO' ) ) {
			$found[] = array(
				'file' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'name' => 'All in One SEO',
			);
		}

		return $found;
	}

	/**
	 * @param array  $list Detected list.
	 * @param string $name Name.
	 */
	private static function list_has_name( array $list, string $name ): bool {
		foreach ( $list as $row ) {
			if ( ( $row['name'] ?? '' ) === $name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether any primary SEO plugin is active.
	 */
	public static function has_primary_seo_plugin(): bool {
		return ! empty( self::detected_seo_plugins() );
	}

	/**
	 * Human label of detected plugins (comma-separated).
	 */
	public static function detected_labels(): string {
		$names = array_map(
			static function ( $row ) {
				return (string) ( $row['name'] ?? '' );
			},
			self::detected_seo_plugins()
		);
		$names = array_filter( $names );
		return $names ? implode( '، ', $names ) : __( 'هیچ افزونه SEO اصلی یافت نشد', 'shojaei-seo-for-woo' );
	}

	/**
	 * Respect external SEO for Product/Breadcrumb (default on).
	 */
	public static function respect_external_seo(): bool {
		// Override هسته سئو = صدور کامل حتی با Rank Math.
		if ( class_exists( 'SEO_Core_Schema_Module' ) && SEO_Core_Schema_Module::wants_full_emit() ) {
			return false;
		}
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_respect_seo_plugins', 'yes' );
	}

	/**
	 * Meta title/description ownership — always deferred by default.
	 *
	 * Option exists for future optional output; never enabled by default
	 * and no emitter ships while this remains "no".
	 */
	public static function owns_meta(): bool {
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' );
	}

	/**
	 * Whether this plugin may emit Product JSON-LD.
	 */
	public static function should_emit_product_schema(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_product_enabled', 'yes' ) ) {
			return false;
		}
		if ( self::respect_external_seo() && self::has_primary_seo_plugin() ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether this plugin may emit BreadcrumbList JSON-LD.
	 */
	public static function should_emit_breadcrumb_schema(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_breadcrumb_enabled', 'yes' ) ) {
			return false;
		}
		if ( self::respect_external_seo() && self::has_primary_seo_plugin() ) {
			return false;
		}
		return true;
	}

	/**
	 * FAQ is supplementary — allowed unless user disabled (even with Rank Math/Yoast).
	 */
	public static function should_emit_faq_schema(): bool {
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_faq_enabled', 'yes' );
	}

	/**
	 * Whether this plugin may emit Article / WebPage JSON-LD.
	 */
	public static function should_emit_article_schema(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_article_enabled', 'yes' ) ) {
			return false;
		}
		if ( self::respect_external_seo() && self::has_primary_seo_plugin() ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether this plugin may emit Organization / WebSite JSON-LD.
	 */
	public static function should_emit_site_schema(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_site_enabled', 'yes' ) ) {
			return false;
		}
		if ( self::respect_external_seo() && self::has_primary_seo_plugin() ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether this plugin may emit CollectionPage JSON-LD on archives.
	 */
	public static function should_emit_collection_schema(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_collection_enabled', 'yes' ) ) {
			return false;
		}
		if ( self::respect_external_seo() && self::has_primary_seo_plugin() ) {
			return false;
		}
		return true;
	}

	/**
	 * Schema operating mode slug.
	 *
	 * @return string full|supplementary|disabled
	 */
	public static function schema_mode(): string {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'schema' ) ) {
			return 'disabled';
		}
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'schema' ) ) {
			return 'disabled';
		}
		if ( class_exists( 'SEO_Core_Schema_Module' ) && SEO_Core_Schema_Module::wants_full_emit() ) {
			return 'full';
		}
		if ( self::respect_external_seo() && self::has_primary_seo_plugin() ) {
			return 'supplementary';
		}
		return 'full';
	}

	/**
	 * Human schema mode label.
	 */
	public static function schema_mode_label(): string {
		switch ( self::schema_mode() ) {
			case 'disabled':
				return __( 'اسکیما خاموش است', 'shojaei-seo-for-woo' );
			case 'supplementary':
				return sprintf(
					/* translators: %s: SEO plugin names */
					__( 'مکمل — اسکیمای اصلی به %s واگذار شد؛ فقط FAQ اختیاری', 'shojaei-seo-for-woo' ),
					self::detected_labels()
				);
			default:
				if ( class_exists( 'SEO_Core_Schema_Module' ) && SEO_Core_Schema_Module::wants_full_emit() && self::has_primary_seo_plugin() ) {
					return __( 'کامل با Override — Product + Article + Site + Collection + Breadcrumb + FAQ', 'shojaei-seo-for-woo' );
				}
				return __( 'کامل — Product + Article + Site + Collection + Breadcrumb + FAQ', 'shojaei-seo-for-woo' );
		}
	}

	/**
	 * Role matrix for admin UI.
	 *
	 * @return array<int,array{area:string,role:string,note:string,owner:string}>
	 */
	public static function role_matrix(): array {
		$seo = self::has_primary_seo_plugin() ? self::detected_labels() : __( 'افزونه SEO (در صورت نصب)', 'shojaei-seo-for-woo' );

		return array(
			array(
				'area'  => __( 'Meta Title / Description', 'shojaei-seo-for-woo' ),
				'owner' => $seo,
				'role'  => __( 'غیرفعال پیش‌فرض در این افزونه', 'shojaei-seo-for-woo' ),
				'note'  => __( 'برای جلوگیری از تداخل — مالکیت با افزونه SEO', 'shojaei-seo-for-woo' ),
			),
			array(
				'area'  => __( 'Schema Product / Breadcrumb / Article / Site', 'shojaei-seo-for-woo' ),
				'owner' => self::should_emit_product_schema()
					? __( 'این افزونه (قابل کنترل)', 'shojaei-seo-for-woo' )
					: $seo,
				'role'  => self::schema_mode_label(),
				'note'  => __( 'خروجی تکراری ممنوع؛ با Schema Detector کنترل می‌شود', 'shojaei-seo-for-woo' ),
			),
			array(
				'area'  => __( 'Schema FAQ', 'shojaei-seo-for-woo' ),
				'owner' => self::should_emit_faq_schema()
					? __( 'این افزونه (مکمل)', 'shojaei-seo-for-woo' )
					: __( 'خاموش', 'shojaei-seo-for-woo' ),
				'role'  => __( 'اختیاری / تکمیلی', 'shojaei-seo-for-woo' ),
				'note'  => __( 'فقط وقتی FAQ اختصاصی در متای محصول تعریف شده باشد', 'shojaei-seo-for-woo' ),
			),
			array(
				'area'  => __( 'Canonical محصولات متغیر', 'shojaei-seo-for-woo' ),
				'owner' => __( 'این افزونه (+ فیلتر Rank Math/Yoast)', 'shojaei-seo-for-woo' ),
				'role'  => __( 'هسته ووکامرس', 'shojaei-seo-for-woo' ),
				'note'  => __( 'حالت رنگ/سایز به صفحه والد جمع می‌شود — بدون جنگ متا', 'shojaei-seo-for-woo' ),
			),
			array(
				'area'  => __( 'نامک محصول / ریدایرکت اسلاگ', 'shojaei-seo-for-woo' ),
				'owner' => __( 'این افزونه (هسته)', 'shojaei-seo-for-woo' ),
				'role'  => __( 'هسته عملیاتی', 'shojaei-seo-for-woo' ),
				'note'  => __( 'فینگلیش + ۳۰۱ روی تغییر نامک — بدون بازنویسی انبوه قدیمی‌ها', 'shojaei-seo-for-woo' ),
			),
			array(
				'area'  => __( 'Redirect Logic', 'shojaei-seo-for-woo' ),
				'owner' => __( 'این افزونه (هسته)', 'shojaei-seo-for-woo' ),
				'role'  => __( 'هسته اصلی', 'shojaei-seo-for-woo' ),
				'note'  => __( 'مزیت رقابتی — تداخل عمدی با Yoast/Rank Math ندارد', 'shojaei-seo-for-woo' ),
			),
			array(
				'area'  => __( 'Out-of-stock SEO', 'shojaei-seo-for-woo' ),
				'owner' => __( 'این افزونه (هسته)', 'shojaei-seo-for-woo' ),
				'role'  => __( 'هسته اصلی', 'shojaei-seo-for-woo' ),
				'note'  => __( 'Inventory-aware — مزیت اصلی محصول', 'shojaei-seo-for-woo' ),
			),
		);
	}

	/**
	 * Admin notice when external SEO is present (once per dismissible window).
	 */
	public static function maybe_admin_notice(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! self::has_primary_seo_plugin() ) {
			return;
		}
		if ( get_transient( 'shojaei_seo_integration_notice_dismissed' ) ) {
			return;
		}
		// Only on our screens.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'shojaei-seo' !== $page ) {
			return;
		}

		$labels = self::detected_labels();
		$url    = admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-integration' );
		?>
		<div class="notice notice-info is-dismissible shojaei-integration-notice">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: plugin names */
						__( 'همزیستی با %s فعال است: Meta و Product Schema به افزونه SEO واگذار می‌شود؛ ریدایرکت و Out-of-stock هسته این افزونه می‌ماند.', 'shojaei-seo-for-woo' ),
						$labels
					)
				);
				?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'سیاست یکپارچگی', 'shojaei-seo-for-woo' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Dismiss integration notice via AJAX (optional soft dismiss by transient).
	 */
	public static function dismiss_notice(): void {
		set_transient( 'shojaei_seo_integration_notice_dismissed', 1, WEEK_IN_SECONDS );
	}
}
