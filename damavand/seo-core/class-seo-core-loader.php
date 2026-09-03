<?php
/**
 * SEO Core Loader — هسته سئو.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Loader
 */
final class SEO_Core_Loader {

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @var array<string,SEO_Core_Module>
	 */
	private array $modules = array();

	/**
	 * Singleton — اگر هسته ناقص باشد null برمی‌گرداند تا سایت سفید نشود.
	 */
	public static function instance(): ?self {
		if ( ! class_exists( 'SEO_Core_Module', false ) || ! class_exists( 'SEO_Core_Installer', false ) ) {
			// Try one soft load pass.
			$dir = defined( 'DAMAVAND_SEO_DIR' ) ? DAMAVAND_SEO_DIR . 'seo-core/' : __DIR__ . '/';
			foreach ( array( 'class-seo-core-module.php', 'class-seo-core-db.php', 'class-seo-core-installer.php' ) as $file ) {
				$path = $dir . $file;
				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		}
		if ( ! class_exists( 'SEO_Core_Module', false ) || ! class_exists( 'SEO_Core_Installer', false ) ) {
			return null;
		}
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_framework();
		$this->init();
	}

	/**
	 * init — خودترمیمی سپس ثبت و بوت.
	 */
	private function init(): void {
		SEO_Core_Installer::ensure_infrastructure( false );

		$this->register_builtin_modules();
		/**
		 * ثبت ماژول سفارشی.
		 *
		 * @param SEO_Core_Loader $loader Loader.
		 */
		do_action( 'seo_core_register_modules', $this );

		$this->boot_modules();

		if ( is_admin() && ! empty( SEO_Core_Installer::get_disabled_modules() ) ) {
			SEO_Core_Installer::register_admin_notices();
		}
	}

	/**
	 * فایل‌های پایه — فقط اگر روی دیسک باشند (پکیج ناقص → فاتال سفید نکند).
	 */
	private function load_framework(): void {
		$dir   = DAMAVAND_SEO_DIR . 'seo-core/';
		$files = array(
			'class-seo-core-module.php',
			'class-seo-core-db.php',
			'class-seo-core-installer.php',
			'class-seo-core-self-test.php',
		);
		foreach ( $files as $file ) {
			$path = $dir . $file;
			if ( ! is_readable( $path ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Damavand SEO Core missing: ' . $file );
				}
				continue;
			}
			require_once $path;
		}
	}

	/**
	 * ماژول‌های داخلی.
	 */
	private function register_builtin_modules(): void {
		$map = array(
			'sitemap'    => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/sitemap/class-seo-core-sitemap.php',
				'class' => 'SEO_Core_Sitemap',
			),
			'pulse'      => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/pulse/class-seo-core-pulse-module.php',
				'class' => 'SEO_Core_Pulse_Module',
			),
			'indexnow'   => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/indexnow/class-seo-core-indexnow-module.php',
				'class' => 'SEO_Core_IndexNow_Module',
			),
			'monitor404' => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/monitor-404/class-seo-core-404-monitor.php',
				'class' => 'SEO_Core_404_Monitor',
			),
			'redirects'  => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/redirects/class-seo-core-redirects-module.php',
				'class' => 'SEO_Core_Redirects_Module',
			),
			'links'      => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/links/class-seo-core-links-module.php',
				'class' => 'SEO_Core_Links_Module',
			),
			'robots'     => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/robots/class-seo-core-robots-module.php',
				'class' => 'SEO_Core_Robots_Module',
			),
			'canonical'  => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/canonical/class-seo-core-canonical-module.php',
				'class' => 'SEO_Core_Canonical_Module',
			),
			'schema'     => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/schema/class-seo-core-schema-module.php',
				'class' => 'SEO_Core_Schema_Module',
			),
			'advanced-analytics' => array(
				'file'  => DAMAVAND_SEO_DIR . 'seo-core/modules/advanced-analytics/class-seo-core-advanced-analytics-module.php',
				'class' => 'SEO_Core_Advanced_Analytics_Module',
			),
		);

		foreach ( $map as $id => $meta ) {
			if ( ! SEO_Core_Installer::is_module_enabled( $id ) ) {
				continue;
			}
			if ( ! is_readable( $meta['file'] ) ) {
				continue;
			}
			require_once $meta['file'];
			if ( class_exists( $meta['class'] ) ) {
				$this->register( new $meta['class']() );
			}
		}
	}

	/**
	 * @param SEO_Core_Module $module Module.
	 */
	public function register( SEO_Core_Module $module ): void {
		$this->modules[ $module->get_id() ] = $module;
	}

	/**
	 * @return array<string,SEO_Core_Module>
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	/**
	 * @param string $id Module id.
	 */
	public function get_module( string $id ): ?SEO_Core_Module {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * نصب روی activation/upgrade.
	 */
	public static function install(): void {
		require_once __DIR__ . '/class-seo-core-module.php';
		require_once __DIR__ . '/class-seo-core-db.php';
		require_once __DIR__ . '/class-seo-core-installer.php';

		SEO_Core_Installer::invalidate_health_cache();
		SEO_Core_Installer::ensure_infrastructure( true );
	}

	/**
	 * پاکسازی wipe.
	 */
	public static function uninstall(): void {
		require_once __DIR__ . '/class-seo-core-db.php';
		require_once __DIR__ . '/class-seo-core-installer.php';
		SEO_Core_DB::uninstall();
		SEO_Core_Installer::invalidate_health_cache();
		SEO_Core_Installer::clear_cron_jobs();
		delete_option( SEO_Core_Installer::MODULES_OPTION );
		delete_option( SEO_Core_Installer::OVERRIDES_OPTION );
		delete_option( SEO_Core_Installer::INDEXNOW_KEY_OPTION );
		delete_option( 'shojaei_seo_core_sitemap_override' );
		delete_option( 'shojaei_seo_core_pulse_override' );
		delete_option( 'shojaei_seo_core_indexnow_override' );
		delete_option( 'shojaei_seo_core_disabled_modules' );
		delete_option( 'shojaei_seo_core_flush_rewrite' );
		delete_option( SEO_Core_Installer::REWRITE_FLAG );
		delete_option( SEO_Core_Installer::DISABLED_OPTION );
		delete_option( SEO_Core_Installer::SCHEMA_OPTION );
		delete_option( SEO_Core_Installer::LAST_REPORT_OPTION );
		delete_option( 'shojaei_seo_core_cache_ns' );
		delete_option( 'shojaei_seo_indexnow_history' );
		delete_option( 'shojaei_seo_indexnow_pending' );
		$mon_file = __DIR__ . '/modules/monitor-404/class-seo-core-404-monitor.php';
		if ( is_readable( $mon_file ) ) {
			require_once __DIR__ . '/class-seo-core-module.php';
			require_once $mon_file;
			if ( class_exists( 'SEO_Core_404_Monitor' ) ) {
				SEO_Core_404_Monitor::uninstall();
			}
		}
		foreach ( array(
			__DIR__ . '/modules/robots/class-seo-core-robots-module.php' => 'SEO_Core_Robots_Module',
			__DIR__ . '/modules/canonical/class-seo-core-canonical-module.php' => 'SEO_Core_Canonical_Module',
		) as $file => $class ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}
			require_once __DIR__ . '/class-seo-core-module.php';
			require_once $file;
			if ( class_exists( $class ) && method_exists( $class, 'uninstall' ) ) {
				$class::uninstall();
			}
		}
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_shojaei_seo_core_%' OR option_name LIKE '_transient_timeout_shojaei_seo_core_%' OR option_name LIKE '_transient_seo_core_%' OR option_name LIKE '_transient_timeout_seo_core_%'"
		);
	}

	/**
	 * بوت فقط ماژول‌های enabled.
	 */
	private function boot_modules(): void {
		foreach ( $this->modules as $module ) {
			$id = $module->get_id();
			if ( ! SEO_Core_Installer::is_module_enabled( $id ) || ! $module->is_enabled() ) {
				continue;
			}
			try {
				$module->boot();
			} catch ( Throwable $e ) {
				SEO_Core_Installer::mark_module_disabled(
					$id,
					sprintf(
						/* translators: 1: module 2: error */
						__( 'بوت ماژول «%1$s» شکست خورد.', 'shojaei-seo-for-woo' ),
						$id
					)
				);
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[SEO_Core_Loader] boot ' . $id . ': ' . $e->getMessage() );
				}
			}
		}

		// Flush فقط اگر Installer پرچم گذاشته و هنوز مصرف نشده.
		if ( '1' === (string) get_option( SEO_Core_Installer::REWRITE_FLAG, '' ) ) {
			SEO_Core_Installer::ensure_rewrite_rules();
		}
	}
}
