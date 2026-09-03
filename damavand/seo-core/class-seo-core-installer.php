<?php
/**
 * SEO Core Installer — نصب‌کننده خودترمیم (Self-Healing).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Installer
 */
class SEO_Core_Installer {

	public const HEALTH_TRANSIENT     = 'seo_core_last_health_check';
	public const HEALTH_TTL           = 12 * HOUR_IN_SECONDS;
	public const CAPABILITY           = 'manage_shojaei_seo';
	public const SCHEMA_OPTION        = 'seo_core_schema_version';
	public const SCHEMA_VERSION       = '1.29.1';
	public const REWRITE_FLAG         = 'seo_core_rewrite_needs_flush';
	public const DISABLED_OPTION      = 'seo_core_disabled_modules';
	public const LAST_REPORT_OPTION   = 'seo_core_last_heal_report';
	public const OVERRIDES_OPTION     = 'seo_core_overrides';
	public const INDEXNOW_KEY_OPTION  = 'seo_core_indexnow_key';
	public const MODULES_OPTION       = 'shojaei_seo_core_modules';

	/**
	 * ماژول‌های موقتاً غیرفعال (خطای زیرساخت).
	 *
	 * @var array<string,string>
	 */
	private static array $disabled_modules = array();

	/**
	 * هشدارهای فارسی (فقط ادمین).
	 *
	 * @var string[]
	 */
	private static array $warnings = array();

	/**
	 * موارد ترمیم‌شده در این دور.
	 *
	 * @var string[]
	 */
	private static array $repaired = array();

	/**
	 * موارد سالم.
	 *
	 * @var string[]
	 */
	private static array $healthy = array();

	/**
	 * @var bool
	 */
	private static bool $ran_this_request = false;

	/**
	 * @var bool
	 */
	private static bool $notices_registered = false;

	/**
	 * نقطه ورود خودترمیمی.
	 *
	 * @param bool $force اجبار به چک کامل.
	 * @return array<string,mixed>
	 */
	public static function ensure_infrastructure( bool $force = false ): array {
		self::restore_disabled_from_option();

		if ( ! $force && false !== get_transient( self::HEALTH_TRANSIENT ) ) {
			return self::build_report( true );
		}

		if ( self::$ran_this_request && ! $force ) {
			return self::build_report( true );
		}
		self::$ran_this_request = true;

		self::$disabled_modules = array();
		self::$warnings         = array();
		self::$repaired         = array();
		self::$healthy          = array();

		try {
			self::migrate_legacy_options();
			self::ensure_options();
			self::ensure_capabilities();
			self::ensure_database_tables();
			self::ensure_module_registry();
			self::ensure_cron_jobs();
			self::ensure_rewrite_rules();
			self::ensure_admin_views();
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_infrastructure', $e );
			self::$warnings[] = __( 'خطای غیرمنتظره در خودترمیمی هسته سئو رخ داد. جزئیات فقط در لاگ ثبت شد.', 'shojaei-seo-for-woo' );
		}

		update_option( self::DISABLED_OPTION, self::$disabled_modules, false );

		$ok = empty( self::$disabled_modules ) && empty( self::$warnings );
		if ( $ok ) {
			set_transient( self::HEALTH_TRANSIENT, time(), self::HEALTH_TTL );
			self::$healthy[] = __( 'سلامت کلی زیرساخت تأیید شد.', 'shojaei-seo-for-woo' );
		} else {
			// سلامت کامل ثبت نشود تا دور بعد دوباره ترمیم شود.
			delete_transient( self::HEALTH_TRANSIENT );
			self::register_admin_notices();
		}

		$report = self::build_report( false );
		update_option( self::LAST_REPORT_OPTION, $report, false );
		return $report;
	}

	/**
	 * باطل کردن کش سلامت.
	 */
	public static function invalidate_health_cache(): void {
		delete_transient( self::HEALTH_TRANSIENT );
	}

	/**
	 * جداول هسته + ماژول‌ها با dbDelta و schema version.
	 */
	public static function ensure_database_tables(): void {
		try {
			if ( ! class_exists( 'SEO_Core_DB' ) ) {
				require_once __DIR__ . '/class-seo-core-db.php';
			}
			SEO_Core_DB::install();

			if ( self::table_exists( SEO_Core_DB::logs_table() ) ) {
				self::$healthy[] = __( 'جدول seo_core_logs سالم است.', 'shojaei-seo-for-woo' );
			} else {
				self::mark_module_disabled( 'core', __( 'جدول seo_core_logs ساخته نشد.', 'shojaei-seo-for-woo' ) );
			}

			if ( self::table_exists( SEO_Core_DB::reports_table() ) ) {
				self::$healthy[] = __( 'جدول seo_core_reports سالم است.', 'shojaei-seo-for-woo' );
			} else {
				self::mark_module_disabled( 'pulse', __( 'جدول seo_core_reports ساخته نشد؛ ماژول نبض سئو محدود شد.', 'shojaei-seo-for-woo' ) );
			}

			if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
				Shojaei_SEO_Pulse::install();
				if ( self::table_exists( Shojaei_SEO_Pulse::table() ) ) {
					self::$healthy[] = __( 'جدول shojaei_seo_pulse_results سالم است.', 'shojaei-seo-for-woo' );
				} else {
					self::mark_module_disabled( 'pulse', __( 'جدول نتایج نبض سئو ساخته نشد.', 'shojaei-seo-for-woo' ) );
				}
			}

			if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
				Shojaei_SEO_Link_Genius::install();
				$lg = Shojaei_SEO_Link_Genius::inventory_table();
				if ( self::table_exists( $lg ) ) {
					self::$healthy[] = __( 'جدول seo_core_link_genius (موجودی لینک) سالم است.', 'shojaei-seo-for-woo' );
				} else {
					self::mark_module_disabled( 'links', __( 'جدول seo_core_link_genius ساخته نشد؛ ماژول لینک داخلی غیرفعال شد.', 'shojaei-seo-for-woo' ) );
				}
				if ( self::table_exists( Shojaei_SEO_Link_Genius::maps_table() ) ) {
					self::$healthy[] = __( 'جدول نقشه کلمات Link Genius سالم است.', 'shojaei-seo-for-woo' );
				} else {
					self::mark_module_disabled( 'links', __( 'جدول نقشه کلمات ساخته نشد؛ ماژول لینک داخلی غیرفعال شد.', 'shojaei-seo-for-woo' ) );
				}
			}
			if ( class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
				Shojaei_SEO_Manual_Redirect::install();
				if ( self::table_exists( Shojaei_SEO_Manual_Redirect::table() ) ) {
					self::$healthy[] = __( 'جدول seo_core_manual_redirects سالم است.', 'shojaei-seo-for-woo' );
				} else {
					self::mark_module_disabled( 'redirects', __( 'جدول seo_core_manual_redirects ساخته نشد؛ ماژول ریدایرکت غیرفعال شد.', 'shojaei-seo-for-woo' ) );
				}
			}

			// مانیتور ۴۰۴.
			$mon_file = __DIR__ . '/modules/monitor-404/class-seo-core-404-monitor.php';
			if ( is_readable( $mon_file ) ) {
				if ( ! class_exists( 'SEO_Core_Module' ) ) {
					require_once __DIR__ . '/class-seo-core-module.php';
				}
				require_once $mon_file;
			}
			if ( class_exists( 'SEO_Core_404_Monitor' ) ) {
				SEO_Core_404_Monitor::create_table();
				if ( false === get_option( SEO_Core_404_Monitor::OPTION_RETENTION, false ) ) {
					add_option( SEO_Core_404_Monitor::OPTION_RETENTION, 30, '', false );
					self::$repaired[] = __( 'گزینه نگهداری مانیتور ۴۰۴ ایجاد شد.', 'shojaei-seo-for-woo' );
				}
				if ( false === get_option( SEO_Core_404_Monitor::OPTION_IGNORE_BOTS, false ) ) {
					add_option( SEO_Core_404_Monitor::OPTION_IGNORE_BOTS, 'yes', '', false );
				}
				if ( self::table_exists( SEO_Core_404_Monitor::table() ) ) {
					self::$healthy[] = __( 'جدول seo_core_404_monitor سالم است.', 'shojaei-seo-for-woo' );
				} else {
					self::mark_module_disabled( 'monitor404', __( 'جدول مانیتور ۴۰۴ ساخته نشد؛ ماژول غیرفعال شد.', 'shojaei-seo-for-woo' ) );
				}
			}

			$prev = (string) get_option( self::SCHEMA_OPTION, '' );
			if ( $prev !== self::SCHEMA_VERSION ) {
				update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
				self::$repaired[] = sprintf(
					/* translators: %s: version */
					__( 'نسخه اسکیمای هسته سئو به %s به‌روز شد.', 'shojaei-seo-for-woo' ),
					self::SCHEMA_VERSION
				);
			} else {
				self::$healthy[] = sprintf(
					/* translators: %s: version */
					__( 'Schema version هسته سئو: %s', 'shojaei-seo-for-woo' ),
					self::SCHEMA_VERSION
				);
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_database_tables', $e );
			self::mark_module_disabled( 'core', __( 'خطا در ساخت/ترمیم جداول هسته سئو.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * گزینه‌ها و پیش‌فرض‌ها.
	 */
	public static function ensure_options(): void {
		try {
			$defaults_modules = array(
				'sitemap'             => true,
				'pulse'               => true,
				'indexnow'            => true,
				'monitor404'          => true,
				'redirects'           => true,
				'links'               => true,
				'robots'              => true,
				'canonical'           => true,
				'schema'              => true,
				'advanced-analytics'  => true,
			);
			$mods = get_option( self::MODULES_OPTION, false );
			if ( false === $mods || ! is_array( $mods ) ) {
				add_option( self::MODULES_OPTION, $defaults_modules, '', false );
				self::$repaired[] = __( 'رجیستری ماژول‌ها (shojaei_seo_core_modules) ایجاد شد.', 'shojaei-seo-for-woo' );
			} else {
				$merged = array_merge( $defaults_modules, $mods );
				if ( $merged !== $mods ) {
					update_option( self::MODULES_OPTION, $merged, false );
					self::$repaired[] = __( 'ورودی‌های گم‌شده رجیستری ماژول تکمیل شد.', 'shojaei-seo-for-woo' );
				} else {
					self::$healthy[] = __( 'گزینه shojaei_seo_core_modules سالم است.', 'shojaei-seo-for-woo' );
				}
			}

			if ( false === get_option( self::OVERRIDES_OPTION, false ) ) {
				add_option(
					self::OVERRIDES_OPTION,
					array(
						'sitemap'            => false,
						'pulse'              => false,
						'indexnow'           => false,
						'monitor404'         => false,
						'redirects'          => false,
						'links'              => false,
						'robots'             => false,
						'canonical'          => false,
						'schema'             => false,
						'advanced-analytics' => false,
					),
					'',
					false
				);
				self::$repaired[] = __( 'گزینه seo_core_overrides ایجاد شد.', 'shojaei-seo-for-woo' );
			} else {
				$ov = get_option( self::OVERRIDES_OPTION, array() );
				if ( is_array( $ov ) ) {
					$need_keys = array( 'monitor404', 'redirects', 'links', 'robots', 'canonical', 'schema', 'advanced-analytics' );
					$changed   = false;
					foreach ( $need_keys as $k ) {
						if ( ! array_key_exists( $k, $ov ) ) {
							$ov[ $k ] = false;
							$changed = true;
						}
					}
					if ( $changed ) {
						update_option( self::OVERRIDES_OPTION, $ov, false );
					}
				}
				self::$healthy[] = __( 'گزینه seo_core_overrides سالم است.', 'shojaei-seo-for-woo' );
			}

			if ( false === get_option( self::DISABLED_OPTION, false ) ) {
				add_option( self::DISABLED_OPTION, array(), '', false );
				self::$repaired[] = __( 'گزینه seo_core_disabled_modules ایجاد شد.', 'shojaei-seo-for-woo' );
			}
			if ( false === get_option( self::SCHEMA_OPTION, false ) ) {
				add_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, '', false );
			}
			if ( false === get_option( 'shojaei_seo_indexnow_enabled', false ) ) {
				add_option( 'shojaei_seo_indexnow_enabled', 'yes', '', false );
			}
			if ( false === get_option( self::INDEXNOW_KEY_OPTION, false ) ) {
				$legacy = (string) get_option( 'shojaei_seo_indexnow_key', '' );
				$key    = '' !== $legacy ? $legacy : wp_generate_password( 32, false );
				add_option( self::INDEXNOW_KEY_OPTION, $key, '', false );
				update_option( 'shojaei_seo_indexnow_key', $key, false );
				self::$repaired[] = __( 'گزینه seo_core_indexnow_key ایجاد شد.', 'shojaei-seo-for-woo' );
			} else {
				$canon  = (string) get_option( self::INDEXNOW_KEY_OPTION, '' );
				$legacy = (string) get_option( 'shojaei_seo_indexnow_key', '' );
				if ( '' !== $canon && $canon !== $legacy ) {
					update_option( 'shojaei_seo_indexnow_key', $canon, false );
				}
				self::$healthy[] = __( 'گزینه seo_core_indexnow_key سالم است.', 'shojaei-seo-for-woo' );
			}

			if ( false === get_option( 'shojaei_seo_ga4_measurement_id', false ) ) {
				add_option( 'shojaei_seo_ga4_measurement_id', '', '', false );
				self::$repaired[] = __( 'گزینه shojaei_seo_ga4_measurement_id ایجاد شد.', 'shojaei-seo-for-woo' );
			}
			if ( false === get_option( 'shojaei_seo_ga4_enabled', false ) ) {
				add_option( 'shojaei_seo_ga4_enabled', 'yes', '', false );
			}
			if ( false === get_option( 'shojaei_seo_gsc_auto_sitemap_submit', false ) ) {
				add_option( 'shojaei_seo_gsc_auto_sitemap_submit', 'yes', '', false );
			}

			if ( '1' === get_option( 'shojaei_seo_core_flush_rewrite', '' ) ) {
				update_option( self::REWRITE_FLAG, '1', false );
				delete_option( 'shojaei_seo_core_flush_rewrite' );
				self::$repaired[] = __( 'پرچم rewrite به seo_core_rewrite_needs_flush منتقل شد.', 'shojaei-seo-for-woo' );
			}

			// گزینه‌های ماژول Robots / Canonical.
			$robots_file = __DIR__ . '/modules/robots/class-seo-core-robots-module.php';
			if ( is_readable( $robots_file ) ) {
				if ( ! class_exists( 'SEO_Core_Module' ) ) {
					require_once __DIR__ . '/class-seo-core-module.php';
				}
				require_once $robots_file;
				if ( class_exists( 'SEO_Core_Robots_Module' ) ) {
					SEO_Core_Robots_Module::ensure_options();
					self::$healthy[] = __( 'گزینه‌های ماژول robots.txt آماده است.', 'shojaei-seo-for-woo' );
				}
			}
			$canon_file = __DIR__ . '/modules/canonical/class-seo-core-canonical-module.php';
			if ( is_readable( $canon_file ) ) {
				if ( ! class_exists( 'SEO_Core_Module' ) ) {
					require_once __DIR__ . '/class-seo-core-module.php';
				}
				require_once $canon_file;
				if ( class_exists( 'SEO_Core_Canonical_Module' ) ) {
					SEO_Core_Canonical_Module::ensure_options();
					if ( false === get_option( 'shojaei_seo_variation_canonical', false ) ) {
						add_option( 'shojaei_seo_variation_canonical', 'yes', '', false );
						self::$repaired[] = __( 'گزینه کنونیکال ورییشن ایجاد شد.', 'shojaei-seo-for-woo' );
					}
					self::$healthy[] = __( 'گزینه‌های ماژول کنونیکال آماده است.', 'shojaei-seo-for-woo' );
				}
			}
			$schema_file = __DIR__ . '/modules/schema/class-seo-core-schema-module.php';
			if ( is_readable( $schema_file ) ) {
				if ( ! class_exists( 'SEO_Core_Module' ) ) {
					require_once __DIR__ . '/class-seo-core-module.php';
				}
				require_once $schema_file;
				if ( class_exists( 'SEO_Core_Schema_Module' ) ) {
					SEO_Core_Schema_Module::ensure_options();
					self::$healthy[] = __( 'گزینه‌های ماژول اسکیما آماده است.', 'shojaei-seo-for-woo' );
				}
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_options', $e );
			self::$warnings[] = __( 'ترمیم تنظیمات پیش‌فرض هسته سئو ناموفق بود.', 'shojaei-seo-for-woo' );
		}
	}

	/**
	 * مهاجرت کلیدهای قدیمی به نام‌های canonical چک‌لیست.
	 */
	public static function migrate_legacy_options(): void {
		$pairs = array(
			'shojaei_seo_core_disabled_modules' => self::DISABLED_OPTION,
			'shojaei_seo_core_last_heal_report' => self::LAST_REPORT_OPTION,
			'shojaei_seo_indexnow_key'          => self::INDEXNOW_KEY_OPTION,
		);
		foreach ( $pairs as $old => $new ) {
			$old_val = get_option( $old, null );
			$new_val = get_option( $new, null );
			if ( null !== $old_val && false !== $old_val && ( null === $new_val || false === $new_val ) ) {
				add_option( $new, $old_val, '', false );
				self::$repaired[] = sprintf(
					/* translators: 1: old 2: new */
					__( 'مهاجرت option: %1$s → %2$s', 'shojaei-seo-for-woo' ),
					$old,
					$new
				);
			}
		}

		// Overrideهای تکی → seo_core_overrides.
		$ov = get_option( self::OVERRIDES_OPTION, null );
		if ( ! is_array( $ov ) ) {
			$ov = array();
			foreach ( array( 'sitemap', 'pulse', 'indexnow', 'monitor404', 'redirects', 'links', 'robots', 'canonical', 'schema', 'advanced-analytics' ) as $mod ) {
				$legacy = get_option( 'shojaei_seo_core_' . $mod . '_override', null );
				if ( null !== $legacy && false !== $legacy ) {
					$ov[ $mod ] = ( 'yes' === $legacy || true === $legacy || 1 === (int) $legacy );
				} else {
					$ov[ $mod ] = false;
				}
			}
			update_option( self::OVERRIDES_OPTION, $ov, false );
			self::$repaired[] = __( 'Overrideها به seo_core_overrides یکپارچه شدند.', 'shojaei-seo-for-woo' );
		}

		self::maybe_rename_table( 'shojaei_seo_manual_redirects', 'seo_core_manual_redirects' );
		self::maybe_rename_table( 'shojaei_seo_link_inventory', 'seo_core_link_genius' );
	}

	/**
	 * RENAME امن جدول اگر نام جدید وجود ندارد.
	 *
	 * @param string $old_suffix بدون prefix.
	 * @param string $new_suffix بدون prefix.
	 */
	private static function maybe_rename_table( string $old_suffix, string $new_suffix ): void {
		global $wpdb;
		$old = $wpdb->prefix . $old_suffix;
		$new = $wpdb->prefix . $new_suffix;
		if ( self::table_exists( $new ) || ! self::table_exists( $old ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ok = $wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" );
		if ( false !== $ok ) {
			self::$repaired[] = sprintf(
				/* translators: 1: old 2: new */
				__( 'جدول %1$s به %2$s تغییر نام داد.', 'shojaei-seo-for-woo' ),
				$old_suffix,
				$new_suffix
			);
		}
	}

	/**
	 * کلید IndexNow (canonical).
	 */
	public static function get_indexnow_key(): string {
		$key = (string) get_option( self::INDEXNOW_KEY_OPTION, '' );
		if ( '' === $key ) {
			$key = (string) get_option( 'shojaei_seo_indexnow_key', '' );
		}
		return $key;
	}

	/**
	 * @param string $key Key.
	 */
	public static function set_indexnow_key( string $key ): void {
		$key = preg_replace( '/[^a-zA-Z0-9\-]/', '', $key );
		update_option( self::INDEXNOW_KEY_OPTION, $key, false );
		update_option( 'shojaei_seo_indexnow_key', $key, false ); // همگام با تنظیمات قدیمی.
	}

	/**
	 * @return array<string,bool>
	 */
	public static function get_overrides(): array {
		$ov = get_option( self::OVERRIDES_OPTION, array() );
		return is_array( $ov ) ? $ov : array();
	}

	/**
	 * @param string $module Module.
	 */
	public static function is_override_enabled( string $module ): bool {
		$ov = self::get_overrides();
		return ! empty( $ov[ sanitize_key( $module ) ] );
	}

	/**
	 * @param string $module Module.
	 * @param bool   $on     Enabled.
	 */
	public static function set_override( string $module, bool $on ): void {
		$module = sanitize_key( $module );
		$ov     = self::get_overrides();
		$ov[ $module ] = $on;
		update_option( self::OVERRIDES_OPTION, $ov, false );
		update_option( 'shojaei_seo_core_' . $module . '_override', $on ? 'yes' : 'no', false );
	}

	/**
	 * Capability سفارشی.
	 */
	public static function ensure_capabilities(): void {
		try {
			$role = get_role( 'administrator' );
			if ( ! $role ) {
				self::$warnings[] = __( 'نقش administrator یافت نشد.', 'shojaei-seo-for-woo' );
				return;
			}
			if ( ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
				self::$repaired[] = __( 'قابلیت manage_shojaei_seo به مدیر اضافه شد.', 'shojaei-seo-for-woo' );
			} else {
				self::$healthy[] = __( 'Capability هسته سئو موجود است.', 'shojaei-seo-for-woo' );
			}
			$shop = get_role( 'shop_manager' );
			if ( $shop && ! $shop->has_cap( self::CAPABILITY ) ) {
				$shop->add_cap( self::CAPABILITY );
				self::$repaired[] = __( 'قابلیت هسته سئو به shop_manager اضافه شد.', 'shojaei-seo-for-woo' );
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_capabilities', $e );
			self::$warnings[] = __( 'ثبت capability ناموفق بود.', 'shojaei-seo-for-woo' );
		}
	}

	/**
	 * Cron — بدون تکرار.
	 */
	public static function ensure_cron_jobs(): void {
		try {
			if ( class_exists( 'Shojaei_SEO_Pulse' ) && self::is_module_enabled( 'pulse' ) ) {
				if ( ! wp_next_scheduled( Shojaei_SEO_Pulse::CRON_HOOK ) ) {
					wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Shojaei_SEO_Pulse::CRON_HOOK );
					self::$repaired[] = __( 'کرون روزانه نبض سئو زمان‌بندی شد.', 'shojaei-seo-for-woo' );
				} else {
					self::$healthy[] = __( 'کرون نبض سئو از قبل فعال است.', 'shojaei-seo-for-woo' );
				}
			}
			if ( class_exists( 'Shojaei_SEO_Jobs' ) && method_exists( 'Shojaei_SEO_Jobs', 'ensure_tick_scheduled' ) ) {
				$before = wp_next_scheduled( Shojaei_SEO_Jobs::HOOK_TICK );
				Shojaei_SEO_Jobs::ensure_tick_scheduled();
				if ( ! $before && wp_next_scheduled( Shojaei_SEO_Jobs::HOOK_TICK ) ) {
					self::$repaired[] = __( 'کرون صف جاب‌ها زمان‌بندی شد.', 'shojaei-seo-for-woo' );
				} else {
					self::$healthy[] = __( 'کرون صف جاب‌ها سالم است.', 'shojaei-seo-for-woo' );
				}
			}
			if ( self::is_module_enabled( 'monitor404' ) ) {
				$mon_file = __DIR__ . '/modules/monitor-404/class-seo-core-404-monitor.php';
				if ( is_readable( $mon_file ) ) {
					require_once $mon_file;
				}
				if ( class_exists( 'SEO_Core_404_Monitor' ) ) {
					$had = wp_next_scheduled( SEO_Core_404_Monitor::CRON_HOOK );
					SEO_Core_404_Monitor::ensure_cron();
					if ( ! $had && wp_next_scheduled( SEO_Core_404_Monitor::CRON_HOOK ) ) {
						self::$repaired[] = __( 'کرون پاک‌سازی مانیتور ۴۰۴ زمان‌بندی شد.', 'shojaei-seo-for-woo' );
					} else {
						self::$healthy[] = __( 'کرون مانیتور ۴۰۴ سالم است.', 'shojaei-seo-for-woo' );
					}
				}
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_cron_jobs', $e );
			self::$warnings[] = __( 'زمان‌بندی cron ناموفق بود.', 'shojaei-seo-for-woo' );
		}
	}

	/**
	 * Rewrite — فقط با flag و در صورت نیاز.
	 */
	public static function ensure_rewrite_rules(): void {
		try {
			$need = ( '1' === (string) get_option( self::REWRITE_FLAG, '' ) );

			if ( ! $need && self::is_module_enabled( 'sitemap' ) ) {
				$mods = get_option( self::MODULES_OPTION, array() );
				if ( ! empty( $mods['sitemap'] ) || ! isset( $mods['sitemap'] ) ) {
					$rules = get_option( 'rewrite_rules' );
					$found = false;
					if ( is_array( $rules ) ) {
						foreach ( array_keys( $rules ) as $pattern ) {
							if ( false !== strpos( (string) $pattern, 'shojaei-sitemap' ) ) {
								$found = true;
								break;
							}
						}
					}
					if ( ! $found ) {
						update_option( self::REWRITE_FLAG, '1', false );
						$need = true;
						self::$repaired[] = __( 'اندپوینت نقشه سایت یافت نشد؛ پرچم flush ثبت شد.', 'shojaei-seo-for-woo' );
					} else {
						self::$healthy[] = __( 'قوانین rewrite نقشه سایت موجود است.', 'shojaei-seo-for-woo' );
					}
				}
			}

			if ( $need ) {
				if ( self::safe_flush_rewrite_rules( false ) ) {
					self::$repaired[] = __( 'Rewrite rules یک‌بار فلاش شد.', 'shojaei-seo-for-woo' );
				} else {
					self::$repaired[] = __( 'فلاش rewrite به init موکول شد (wp_rewrite هنوز آماده نبود).', 'shojaei-seo-for-woo' );
				}
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_rewrite_rules', $e );
			self::mark_module_disabled( 'sitemap', __( 'ثبت اندپوینت نقشه سایت ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * Whether global $wp_rewrite is usable.
	 */
	public static function rewrite_ready(): bool {
		global $wp_rewrite;
		return $wp_rewrite instanceof WP_Rewrite;
	}

	/**
	 * Flush rewrite only when WP_Rewrite exists; otherwise defer to init@99.
	 *
	 * @param bool $hard Hard flush.
	 * @return bool True if flushed now.
	 */
	public static function safe_flush_rewrite_rules( bool $hard = false ): bool {
		if ( ! self::rewrite_ready() ) {
			update_option( self::REWRITE_FLAG, '1', false );
			self::schedule_deferred_rewrite_flush();
			return false;
		}

		flush_rewrite_rules( $hard );
		delete_option( self::REWRITE_FLAG );
		delete_option( 'shojaei_seo_core_flush_rewrite' );
		return true;
	}

	/**
	 * Hook deferred flush once.
	 */
	public static function schedule_deferred_rewrite_flush(): void {
		if ( ! has_action( 'init', array( __CLASS__, 'maybe_deferred_rewrite_flush' ) ) ) {
			add_action( 'init', array( __CLASS__, 'maybe_deferred_rewrite_flush' ), 99 );
		}
	}

	/**
	 * Run pending rewrite flush after init (when $wp_rewrite is alive).
	 */
	public static function maybe_deferred_rewrite_flush(): void {
		if ( '1' !== (string) get_option( self::REWRITE_FLAG, '' ) ) {
			return;
		}
		if ( ! self::rewrite_ready() ) {
			return;
		}

		if ( class_exists( 'SEO_Core_Loader' ) ) {
			$loader = SEO_Core_Loader::instance();
			$sm     = $loader ? $loader->get_module( 'sitemap' ) : null;
			if ( $sm && method_exists( $sm, 'register_rewrites' ) ) {
				$sm->register_rewrites();
			}
		}

		flush_rewrite_rules( false );
		delete_option( self::REWRITE_FLAG );
		delete_option( 'shojaei_seo_core_flush_rewrite' );
	}

	/**
	 * رجیستری ماژول + وجود فایل کلاس.
	 */
	public static function ensure_module_registry(): void {
		try {
			$required = array( 'sitemap', 'pulse', 'indexnow', 'monitor404', 'redirects', 'links', 'robots', 'canonical', 'schema', 'advanced-analytics' );
			$mods     = get_option( self::MODULES_OPTION, array() );
			if ( ! is_array( $mods ) ) {
				$mods = array();
			}
			$changed = false;
			foreach ( $required as $id ) {
				if ( ! array_key_exists( $id, $mods ) ) {
					$mods[ $id ] = true;
					$changed     = true;
				}
			}
			if ( $changed ) {
				update_option( self::MODULES_OPTION, $mods, false );
				self::$repaired[] = __( 'رجیستری ماژول‌ها تکمیل شد.', 'shojaei-seo-for-woo' );
			}

			$map = array(
				'sitemap'            => __DIR__ . '/modules/sitemap/class-seo-core-sitemap.php',
				'pulse'              => __DIR__ . '/modules/pulse/class-seo-core-pulse-module.php',
				'indexnow'           => __DIR__ . '/modules/indexnow/class-seo-core-indexnow-module.php',
				'monitor404'         => __DIR__ . '/modules/monitor-404/class-seo-core-404-monitor.php',
				'redirects'          => __DIR__ . '/modules/redirects/class-seo-core-redirects-module.php',
				'links'              => __DIR__ . '/modules/links/class-seo-core-links-module.php',
				'robots'             => __DIR__ . '/modules/robots/class-seo-core-robots-module.php',
				'canonical'          => __DIR__ . '/modules/canonical/class-seo-core-canonical-module.php',
				'schema'             => __DIR__ . '/modules/schema/class-seo-core-schema-module.php',
				'advanced-analytics' => __DIR__ . '/modules/advanced-analytics/class-seo-core-advanced-analytics-module.php',
			);
			foreach ( $map as $id => $file ) {
				if ( ! is_readable( $file ) ) {
					self::mark_module_disabled(
						$id,
						sprintf(
							/* translators: %s: module */
							__( 'فایل ماژول «%s» یافت نشد.', 'shojaei-seo-for-woo' ),
							$id
						)
					);
				} else {
					self::$healthy[] = sprintf(
						/* translators: %s: module */
						__( 'فایل ماژول «%s» موجود است.', 'shojaei-seo-for-woo' ),
						$id
					);
				}
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_module_registry', $e );
			self::$warnings[] = __( 'بروزرسانی رجیستری ماژول‌ها ناموفق بود.', 'shojaei-seo-for-woo' );
		}
	}

	/**
	 * وجود ویوهای ادمین حیاتی.
	 */
	public static function ensure_admin_views(): void {
		try {
			$views = array(
				'seo-core'  => defined( 'DAMAVAND_SEO_DIR' ) ? DAMAVAND_SEO_DIR . 'admin/views/seo-core.php' : '',
				'seo-pulse' => defined( 'DAMAVAND_SEO_DIR' ) ? DAMAVAND_SEO_DIR . 'admin/views/seo-pulse.php' : '',
			);
			foreach ( $views as $tab => $file ) {
				if ( ! $file || ! is_readable( $file ) ) {
					self::$warnings[] = sprintf(
						/* translators: %s: tab */
						__( 'صفحه ادمین «%s» یافت نشد.', 'shojaei-seo-for-woo' ),
						$tab
					);
				} else {
					self::$healthy[] = sprintf(
						/* translators: %s: tab */
						__( 'ویو ادمین «%s» موجود است.', 'shojaei-seo-for-woo' ),
						$tab
					);
				}
			}
		} catch ( Throwable $e ) {
			self::log_error( 'ensure_admin_views', $e );
			self::$warnings[] = __( 'بررسی صفحات ادمین ناموفق بود.', 'shojaei-seo-for-woo' );
		}
	}

	/**
	 * علامت‌گذاری ماژول آسیب‌دیده.
	 *
	 * @param string $module  شناسه.
	 * @param string $message پیام فارسی.
	 */
	public static function mark_module_disabled( $module, $message ): void {
		$module  = sanitize_key( (string) $module );
		$message = sanitize_text_field( (string) $message );
		if ( '' === $module || '' === $message ) {
			return;
		}
		self::restore_disabled_from_option();
		self::$disabled_modules[ $module ] = $message;
		self::$warnings[]                  = $message;
		update_option( self::DISABLED_OPTION, self::$disabled_modules, false );
		self::invalidate_health_cache();
		self::register_admin_notices();
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_disabled_modules(): array {
		self::restore_disabled_from_option();
		return self::$disabled_modules;
	}

	/**
	 * آیا ماژول برای بوت مجاز است؟ (فعال در رجیستری و غیرفعال‌شده توسط Installer نباشد).
	 *
	 * @param string $module شناسه.
	 */
	public static function is_module_enabled( $module ): bool {
		$module = sanitize_key( (string) $module );
		if ( '' === $module ) {
			return false;
		}
		if ( isset( self::get_disabled_modules()[ $module ] ) ) {
			return false;
		}
		$opts = get_option( self::MODULES_OPTION, array() );
		if ( is_array( $opts ) && array_key_exists( $module, $opts ) ) {
			return (bool) $opts[ $module ];
		}
		return true;
	}

	/**
	 * سازگاری با نام قبلی.
	 *
	 * @param string $module_id شناسه.
	 */
	public static function is_module_disabled( string $module_id ): bool {
		return ! self::is_module_enabled( $module_id ) && isset( self::get_disabled_modules()[ $module_id ] );
	}

	/**
	 * ثبت admin_notice فقط در ادمین.
	 */
	public static function register_admin_notices(): void {
		if ( self::$notices_registered || ! is_admin() ) {
			return;
		}
		self::$notices_registered = true;
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
	}

	/**
	 * نمایش هشدار فارسی برای مدیر (نه فرانت).
	 */
	public static function render_admin_notices(): void {
		// فقط مدیر مجاز (capability هسته یا manage_options).
		if ( ! current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msgs = array_unique( array_merge( self::$warnings, array_values( self::get_disabled_modules() ) ) );
		if ( empty( $msgs ) ) {
			$saved = get_option( self::LAST_REPORT_OPTION, array() );
			if ( is_array( $saved ) && ! empty( $saved['errors'] ) ) {
				$msgs = (array) $saved['errors'];
			}
		}
		if ( empty( $msgs ) ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'هسته سئو — خودترمیمی', 'shojaei-seo-for-woo' ) . '</strong></p><ul style="list-style:disc;margin:0.5em 1.5em;">';
		foreach ( $msgs as $msg ) {
			echo '<li>' . esc_html( (string) $msg ) . '</li>';
		}
		echo '</ul><p class="description">' . esc_html__( 'بقیه افزونه فعال است. از «هسته سئو → اجرای خودترمیمی اکنون» دوباره تلاش کنید.', 'shojaei-seo-for-woo' ) . '</p></div>';
	}

	/**
	 * وضعیت سلامت برای UI.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_health_status(): array {
		$last = get_transient( self::HEALTH_TRANSIENT );
		$report = get_option( self::LAST_REPORT_OPTION, array() );
		return array(
			'cached_ok'   => false !== $last,
			'checked_at'  => is_numeric( $last ) ? (int) $last : 0,
			'disabled'    => self::get_disabled_modules(),
			'last_report' => is_array( $report ) ? $report : array(),
			'schema'      => (string) get_option( self::SCHEMA_OPTION, '' ),
			'rewrite_flag'=> (string) get_option( self::REWRITE_FLAG, '' ),
		);
	}

	/**
	 * درخواست flush در درخواست بعدی سالم (نه هر request).
	 */
	public static function request_rewrite_flush(): void {
		update_option( self::REWRITE_FLAG, '1', false );
		self::schedule_deferred_rewrite_flush();
		self::invalidate_health_cache();
	}

	/**
	 * پاک‌سازی cronهای هسته سئو روی deactivate.
	 */
	public static function clear_cron_jobs(): void {
		if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
			wp_clear_scheduled_hook( Shojaei_SEO_Pulse::CRON_HOOK );
		}
		wp_clear_scheduled_hook( 'shojaei_seo_pulse_daily' );
		wp_clear_scheduled_hook( 'seo_core_404_purge' );
	}

	/**
	 * @param bool $skipped آیا از چک سنگین عبور شد؟
	 * @return array<string,mixed>
	 */
	private static function build_report( bool $skipped ): array {
		$errors = array_values( array_unique( array_merge( self::$warnings, array_values( self::$disabled_modules ) ) ) );
		return array(
			'ok'       => empty( self::$disabled_modules ) && empty( self::$warnings ),
			'skipped'  => $skipped,
			'repaired' => array_values( array_unique( self::$repaired ) ),
			'healthy'  => array_values( array_unique( self::$healthy ) ),
			'errors'   => $errors,
			'disabled' => self::$disabled_modules,
			'warnings' => self::$warnings,
			'message'  => $skipped
				? __( 'بررسی سلامت اخیراً انجام شده (کش Transient).', 'shojaei-seo-for-woo' )
				: ( empty( $errors )
					? __( 'خودترمیمی کامل شد — زیرساخت سالم است.', 'shojaei-seo-for-woo' )
					: __( 'خودترمیمی انجام شد؛ برخی موارد نیاز به توجه دارند.', 'shojaei-seo-for-woo' ) ),
		);
	}

	/**
	 * بازیابی لیست از option.
	 */
	private static function restore_disabled_from_option(): void {
		if ( ! empty( self::$disabled_modules ) ) {
			return;
		}
		$saved = get_option( self::DISABLED_OPTION, null );
		if ( ! is_array( $saved ) ) {
			$saved = get_option( 'shojaei_seo_core_disabled_modules', array() );
		}
		if ( is_array( $saved ) ) {
			self::$disabled_modules = $saved;
		}
	}

	/**
	 * لاگ امن — بدون نمایش به بازدیدکننده.
	 *
	 * @param string    $context Context.
	 * @param Throwable $e       Exception.
	 */
	private static function log_error( string $context, Throwable $e ): void {
		if ( class_exists( 'SEO_Core_DB' ) ) {
			try {
				SEO_Core_DB::log(
					'installer',
					'error',
					$context . ': ' . $e->getMessage(),
					array(
						'file' => $e->getFile(),
						'line' => $e->getLine(),
					)
				);
			} catch ( Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// سقوط به error_log.
			}
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[SEO_Core_Installer] ' . $context . ': ' . $e->getMessage() );
		}
	}

	/**
	 * @param string $table Table.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ( $found === $table );
	}
}
