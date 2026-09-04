<?php
/**
 * Plugin Name:       افزونه سئو حرفه‌ای دماوند (Damavand)
 * Plugin URI:        https://shojaei.com
 * Description:       جایگزین ساده‌تر و بهتر Rank Math برای فروشگاه‌های ووکامرس ایرانی — Schema، متا، crawl budget و OOS
 * Version:           1.61.2
 * Author:            اسماعیل شجاعی
 * Author URI:        https://shojaei.com
 * Text Domain:       shojaei-seo-for-woo
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/*
 * ثابت‌های مسیر باید متعلق به همین فایل باشند.
 * اگر افزونه دیگری قبلاً SHOJAEI_SEO_PLUGIN_* را گرفته باشد، از DAMAVAND_SEO_* استفاده می‌کنیم.
 */
if ( ! defined( 'DAMAVAND_SEO_VERSION' ) ) {
	define( 'DAMAVAND_SEO_VERSION', '1.61.2' );
}
if ( ! defined( 'DAMAVAND_SEO_DB_VERSION' ) ) {
	define( 'DAMAVAND_SEO_DB_VERSION', '1.33.0' );
}
if ( ! defined( 'DAMAVAND_SEO_FILE' ) ) {
	define( 'DAMAVAND_SEO_FILE', __FILE__ );
}
if ( ! defined( 'DAMAVAND_SEO_DIR' ) ) {
	define( 'DAMAVAND_SEO_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DAMAVAND_SEO_URL' ) ) {
	define( 'DAMAVAND_SEO_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'DAMAVAND_SEO_BASENAME' ) ) {
	define( 'DAMAVAND_SEO_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'DAMAVAND_SEO_CURRENCY' ) ) {
	define( 'DAMAVAND_SEO_CURRENCY', 'IRT' );
}
if ( ! defined( 'DAMAVAND_SEO_CURRENCY_LABEL' ) ) {
	define( 'DAMAVAND_SEO_CURRENCY_LABEL', 'تومان' );
}

/* سازگاری عقب‌رو — فقط اگر آزاد باشند (افزونه دیگر ممکن است این نام‌ها را قبلاً گرفته باشد) */
if ( ! defined( 'SHOJAEI_SEO_VERSION' ) ) {
	define( 'SHOJAEI_SEO_VERSION', DAMAVAND_SEO_VERSION );
}
if ( ! defined( 'SHOJAEI_SEO_DB_VERSION' ) ) {
	define( 'SHOJAEI_SEO_DB_VERSION', DAMAVAND_SEO_DB_VERSION );
}
if ( ! defined( 'SHOJAEI_SEO_PLUGIN_FILE' ) ) {
	define( 'SHOJAEI_SEO_PLUGIN_FILE', DAMAVAND_SEO_FILE );
}
if ( ! defined( 'SHOJAEI_SEO_PLUGIN_DIR' ) ) {
	define( 'SHOJAEI_SEO_PLUGIN_DIR', DAMAVAND_SEO_DIR );
}
if ( ! defined( 'SHOJAEI_SEO_PLUGIN_URL' ) ) {
	define( 'SHOJAEI_SEO_PLUGIN_URL', DAMAVAND_SEO_URL );
}
if ( ! defined( 'SHOJAEI_SEO_PLUGIN_BASENAME' ) ) {
	define( 'SHOJAEI_SEO_PLUGIN_BASENAME', DAMAVAND_SEO_BASENAME );
}
if ( ! defined( 'SHOJAEI_SEO_CURRENCY' ) ) {
	define( 'SHOJAEI_SEO_CURRENCY', DAMAVAND_SEO_CURRENCY );
}
if ( ! defined( 'SHOJAEI_SEO_CURRENCY_LABEL' ) ) {
	define( 'SHOJAEI_SEO_CURRENCY_LABEL', DAMAVAND_SEO_CURRENCY_LABEL );
}

/**
 * Main plugin bootstrap class.
 */
if ( ! class_exists( 'Shojaei_SEO_For_Woo', false ) ) {

	/**
	 * Class Shojaei_SEO_For_Woo
	 */
	final class Shojaei_SEO_For_Woo {

		/**
		 * Singleton instance.
		 *
		 * @var Shojaei_SEO_For_Woo|null
		 */
		private static $instance = null;
		/** @var bool */
		private static $booted = false;

		/**
		 * Get singleton instance.
		 *
		 * @return Shojaei_SEO_For_Woo
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 */
		private function __construct() {
			$this->load_dependencies();
			$this->init_hooks();
		}

		/**
		 * Load required files.
		 */
		private function load_dependencies() {
			$dir = DAMAVAND_SEO_DIR;

			$files = array(
				'includes/class-shojaei-seo-helpers.php',
				'includes/class-shojaei-seo-activator.php',
				'includes/class-shojaei-seo-deactivator.php',
				'includes/class-shojaei-seo-i18n.php',
				'includes/class-shojaei-seo-queue.php',
				'includes/class-shojaei-seo-jobs.php',
				'includes/class-shojaei-seo-batch.php',
				'includes/class-shojaei-seo-gsc-error-mapper.php',
				'includes/class-shojaei-seo-gsc.php',
				'includes/class-shojaei-seo-ga4.php',
				'seo-core/modules/canonical/class-seo-core-canonical-resolver.php',
				'includes/class-damavand-slug-finglish.php',
				'includes/class-damavand-slug-redirects.php',
				'includes/class-damavand-slug-health.php',
				'includes/class-damavand-slug-editor.php',
				'includes/class-shojaei-seo-slug.php',
				'includes/class-shojaei-seo-manual-redirect.php',
				'includes/class-shojaei-seo-general-meta.php',
				'includes/class-shojaei-seo-link-genius.php',
				'includes/class-shojaei-seo-pulse.php',
				'includes/class-shojaei-seo-pulse-engine.php',
				'includes/class-shojaei-seo-notifications.php',
				'includes/class-shojaei-seo-analytics.php',
				'includes/class-shojaei-seo-cache.php',
				'includes/class-shojaei-seo-activity-log.php',
				'includes/class-shojaei-seo-page-value.php',
				'includes/class-shojaei-seo-persian.php',
				'includes/class-shojaei-seo-redirect-engine.php',
				'includes/class-shojaei-seo-redirect-audit.php',
				'includes/class-shojaei-seo-rule-engine.php',
				'includes/class-shojaei-seo-link-rules.php',
				'includes/class-shojaei-seo-integration.php',
				'includes/class-shojaei-seo-impact.php',
				'includes/class-shojaei-seo-events.php',
				'includes/class-shojaei-seo-status.php',
				'includes/class-shojaei-seo-schema-detector.php',
				'includes/class-shojaei-seo-revert-log.php',
				'includes/class-damavand-seo-meta.php',
				'includes/class-damavand-seo-templates.php',
				'includes/class-damavand-meta-suggester.php',
				'includes/class-damavand-seo-migrator.php',
				'includes/class-damavand-persian-text.php',
				'includes/class-damavand-content-analyzer.php',
				'includes/class-damavand-robots.php',
				'includes/class-damavand-schema-validator.php',
				'includes/class-damavand-duplicate-scan.php',
				'includes/class-damavand-persian-seo-score.php',
				'includes/class-damavand-gutenberg-sidebar.php',
				'includes/class-damavand-taxonomy-seo.php',
				'includes/class-damavand-seo-icons.php',
				'includes/class-damavand-link-manager.php',
				'includes/class-damavand-link-calculator.php',
				'includes/class-damavand-link-suggestions.php',
				'includes/class-damavand-link-watchdog.php',
				'includes/class-damavand-faq-box.php',
				'includes/class-damavand-delete-redirect.php',
				'includes/admin/class-damavand-similar-products-settings.php',
				'includes/core/class-damavand-similar-products-engine.php',
				'public/class-damavand-link-resolver.php',
				'seo-core/class-seo-core-loader.php',
				'public/class-shojaei-seo-public.php',
				'public/class-schema-generator.php',
				'public/class-link-builder.php',
				'public/class-damavand-oos-order-lookup.php',
				'public/class-damavand-oos-notifier.php',
				'public/class-damavand-oos-detector.php',
				'public/class-damavand-oos-admin.php',
				'public/class-oos-manager.php',
				'public/class-indexnow.php',
			);

			if ( is_admin() ) {
				$files[] = 'admin/class-shojaei-seo-admin.php';
			}

			foreach ( $files as $rel ) {
				$path = $dir . $rel;
				if ( ! is_readable( $path ) ) {
					throw new RuntimeException( 'Damavand SEO missing file: ' . $rel );
				}
				require_once $path;
			}
		}

		/**
		 * Register hooks.
		 */
		private function init_hooks() {
			register_activation_hook( DAMAVAND_SEO_FILE, array( 'Shojaei_SEO_Activator', 'activate' ) );
			register_deactivation_hook( DAMAVAND_SEO_FILE, array( 'Shojaei_SEO_Deactivator', 'deactivate' ) );

			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		}

		/**
		 * Declare compatibility with WooCommerce HPOS.
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'custom_order_tables',
					DAMAVAND_SEO_FILE,
					true
				);
			}
		}

		/**
		 * Initialize after all plugins loaded.
		 */
		public function on_plugins_loaded() {
			if ( self::$booted ) {
				return;
			}
			if ( ! did_action( 'init' ) ) {
				add_action( 'init', array( $this, 'on_plugins_loaded' ), 1 );
				return;
			}
			self::$booted = true;

			new Shojaei_SEO_i18n();

			if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
				Shojaei_SEO_Helpers::register_hooks();
			}

			$activate_err = get_option( 'damavand_seo_activate_error', '' );
			if ( is_string( $activate_err ) && '' !== $activate_err ) {
				add_action(
					'admin_notices',
					static function () use ( $activate_err ) {
						echo '<div class="notice notice-warning is-dismissible"><p><strong>Damavand SEO:</strong> ';
						echo esc_html( $activate_err );
						echo '</p></div>';
						delete_option( 'damavand_seo_activate_error' );
					}
				);
			}

			if ( ! $this->is_woocommerce_active() ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
				return;
			}

			try {
				Shojaei_SEO_Activator::maybe_upgrade();

				if ( 'yes' === get_option( 'shojaei_seo_need_health_baseline', '' ) && class_exists( 'Shojaei_SEO_Impact' ) ) {
					Shojaei_SEO_Impact::maybe_capture_baseline();
					delete_option( 'shojaei_seo_need_health_baseline' );
				}

				if ( 'yes' === get_option( 'shojaei_seo_need_initial_scan', '' ) ) {
					delete_option( 'shojaei_seo_need_initial_scan' );
					Shojaei_SEO_Queue::schedule_initial_scan();
				}

				new Shojaei_SEO_Queue();
				new Shojaei_SEO_Jobs();
				new Shojaei_SEO_Batch();
				new Shojaei_SEO_Events();
				new Shojaei_SEO_GSC();
				new Shojaei_SEO_Slug();
				new Shojaei_SEO_Manual_Redirect();
				new Shojaei_SEO_General_Meta();

				if ( class_exists( 'SEO_Core_Loader' ) ) {
					$core = SEO_Core_Loader::instance();
					if ( null === $core && is_admin() ) {
						add_action(
							'admin_notices',
							static function () {
								echo '<div class="notice notice-error"><p><strong>Damavand SEO:</strong> ';
								echo esc_html__( 'هسته seo-core ناقص است (فایل‌های ماژول روی سرور نیست). پوشهٔ افزونه را دوباره کامل آپلود کنید؛ بقیهٔ افزونه بدون هسته به کار ادامه می‌دهد.', 'shojaei-seo-for-woo' );
								echo '</p></div>';
							}
						);
					}
				}

				new Shojaei_SEO_Pulse();
				new Shojaei_SEO_Analytics();
				new Shojaei_SEO_Cache();

				if ( class_exists( 'Damavand_SEO_Meta' ) ) {
					Damavand_SEO_Meta::register_frontend_hooks();
				}

				if ( class_exists( 'Damavand_SEO_Migrator' ) ) {
					Damavand_SEO_Migrator::register_hooks();
				}
				// Canonical runtime hooks: SEO_Core_Canonical_Module::boot() → Resolver (seo-core).
				if ( class_exists( 'Damavand_Persian_SEO_Score' ) ) {
					Damavand_Persian_SEO_Score::register_hooks();
				}
				if ( class_exists( 'Damavand_Gutenberg_Sidebar' ) ) {
					Damavand_Gutenberg_Sidebar::register_hooks();
				}
				if ( class_exists( 'Damavand_Taxonomy_SEO' ) ) {
					Damavand_Taxonomy_SEO::register_hooks();
				}

				if ( class_exists( 'Damavand_Link_Manager' ) ) {
					Damavand_Link_Manager::register_hooks();
				}
				if ( class_exists( 'Damavand_Link_Calculator' ) ) {
					Damavand_Link_Calculator::register_hooks();
				}
				if ( class_exists( 'Damavand_Link_Suggestions' ) ) {
					Damavand_Link_Suggestions::register_hooks();
				}
				if ( class_exists( 'Damavand_Link_Watchdog' ) ) {
					Damavand_Link_Watchdog::register_hooks();
					Damavand_Link_Watchdog::ensure_scheduled();
				}
				if ( class_exists( 'Damavand_FAQ_Box' ) ) {
					Damavand_FAQ_Box::register_hooks();
				}
				if ( class_exists( 'Damavand_Delete_Redirect' ) ) {
					Damavand_Delete_Redirect::register_hooks();
				}
				if ( class_exists( 'Damavand_Link_Resolver' ) ) {
					Damavand_Link_Resolver::register_hooks();
				}
				if ( class_exists( 'Damavand_Similar_Products_Settings' ) ) {
					Damavand_Similar_Products_Settings::register_hooks();
				}
				if ( class_exists( 'Damavand_Similar_Products_Engine' ) ) {
					Damavand_Similar_Products_Engine::register_hooks();
				}

				if ( class_exists( 'Shojaei_SEO_Activator' ) ) {
					Shojaei_SEO_Activator::maybe_sync_plugin_version();
				}

				new Shojaei_SEO_Schema_Generator();
				new Shojaei_SEO_Schema_Detector();

				if ( is_admin() && class_exists( 'Shojaei_SEO_Admin' ) ) {
					new Shojaei_SEO_Admin();
				}

				new Shojaei_SEO_Public();
				new Shojaei_SEO_Link_Builder();
				new Shojaei_SEO_OOS_Manager();
				new Shojaei_SEO_IndexNow();

				if ( is_admin() && class_exists( 'Shojaei_SEO_Integration' ) ) {
					add_action( 'admin_notices', array( 'Shojaei_SEO_Integration', 'maybe_admin_notice' ) );
				}
			} catch ( Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[Damavand SEO] boot: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
				}
				add_action(
					'admin_notices',
					static function () use ( $e ) {
						echo '<div class="notice notice-error"><p><strong>Damavand SEO:</strong> ';
						echo esc_html( $e->getMessage() );
						echo '</p></div>';
					}
				);
			}
		}

		/**
		 * Check if WooCommerce is active.
		 */
		private function is_woocommerce_active() {
			return class_exists( 'WooCommerce' );
		}

		/**
		 * Admin notice when WooCommerce is missing.
		 */
		public function woocommerce_missing_notice() {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'افزونه سئو حرفه‌ای دماوند نیاز به نصب و فعال‌سازی ووکامرس دارد.', 'shojaei-seo-for-woo' ); ?></p>
			</div>
			<?php
		}
	}
}

/**
 * Returns the main plugin instance.
 *
 * @return Shojaei_SEO_For_Woo
 */
if ( ! function_exists( 'shojaei_seo' ) ) {
	function shojaei_seo() {
		return Shojaei_SEO_For_Woo::instance();
	}
}

/**
 * مسیر ریشه افزونه دماوند (ضدتداخل با افزونه‌های دیگر).
 */
if ( ! function_exists( 'damavand_seo_dir' ) ) {
	function damavand_seo_dir() {
		return DAMAVAND_SEO_DIR;
	}
}

try {
	shojaei_seo();
} catch ( Throwable $e ) {
	$detail = $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
	if ( is_admin() ) {
		add_action(
			'admin_notices',
			static function () use ( $detail ) {
				echo '<div class="notice notice-error"><p><strong>Damavand SEO load error:</strong> ';
				echo esc_html( $detail );
				echo '</p></div>';
			}
		);
	}
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Damavand SEO] ' . $detail );
	}
}
