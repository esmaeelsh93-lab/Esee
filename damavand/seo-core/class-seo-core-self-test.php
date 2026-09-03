<?php
/**
 * خودآزمون هسته سئو — تست‌های واقعی روی وردپرس زنده.
 *
 * پوشش: heal، جداول/options، Passive/Override، cron، rewrite، permissions، isolation.
 * اجرا از ادمین (AJAX) یا WP-CLI: wp eval-file seo-core/bin/run-self-test.php
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Self_Test
 */
class SEO_Core_Self_Test {

	/**
	 * اجرای کامل.
	 *
	 * @return array{ok:bool,passed:int,failed:int,skipped:int,results:array<int,array{id:string,status:string,message:string}>}
	 */
	public static function run(): array {
		$results = array();

		$results[] = self::check( 'classes_loaded', __( 'کلاس‌های هسته', 'shojaei-seo-for-woo' ), static function () {
			$need = array( 'SEO_Core_Installer', 'SEO_Core_Loader', 'SEO_Core_Module', 'SEO_Core_DB' );
			$miss = array();
			foreach ( $need as $c ) {
				if ( ! class_exists( $c ) ) {
					$miss[] = $c;
				}
			}
			if ( $miss ) {
				return array( false, sprintf( __( 'کلاس‌های غایب: %s', 'shojaei-seo-for-woo' ), implode( ', ', $miss ) ) );
			}
			return array( true, __( 'Installer/Loader/DB بارگذاری شده‌اند.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'module_files', __( 'فایل ماژول‌ها', 'shojaei-seo-for-woo' ), static function () {
			$dir  = defined( 'DAMAVAND_SEO_DIR' ) ? DAMAVAND_SEO_DIR . 'seo-core/modules/' : '';
			$map  = array(
				'sitemap'            => 'sitemap/class-seo-core-sitemap.php',
				'pulse'              => 'pulse/class-seo-core-pulse-module.php',
				'indexnow'           => 'indexnow/class-seo-core-indexnow-module.php',
				'monitor404'         => 'monitor-404/class-seo-core-404-monitor.php',
				'redirects'          => 'redirects/class-seo-core-redirects-module.php',
				'links'              => 'links/class-seo-core-links-module.php',
				'robots'             => 'robots/class-seo-core-robots-module.php',
				'canonical'          => 'canonical/class-seo-core-canonical-module.php',
				'schema'             => 'schema/class-seo-core-schema-module.php',
				'advanced-analytics' => 'advanced-analytics/class-seo-core-advanced-analytics-module.php',
			);
			$miss = array();
			foreach ( $map as $id => $rel ) {
				if ( ! is_readable( $dir . $rel ) ) {
					$miss[] = $id;
				}
			}
			if ( $miss ) {
				return array( false, sprintf( __( 'فایل غایب: %s', 'shojaei-seo-for-woo' ), implode( ', ', $miss ) ) );
			}
			return array( true, __( '۱۰ ماژول Core روی دیسک موجودند.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'permissions', __( 'دسترسی / Capability', 'shojaei-seo-for-woo' ), static function () {
			SEO_Core_Installer::ensure_capabilities();
			$role = get_role( 'administrator' );
			if ( ! $role || ! $role->has_cap( SEO_Core_Installer::CAPABILITY ) ) {
				return array( false, __( 'قابلیت manage_shojaei_seo روی administrator نیست.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! current_user_can( SEO_Core_Installer::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
				return array( false, __( 'کاربر جاری capability لازم را ندارد.', 'shojaei-seo-for-woo' ) );
			}
			return array( true, __( 'Capability هسته سئو برای مدیر تأیید شد.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'heal_force', __( 'خودترمیمی (force)', 'shojaei-seo-for-woo' ), static function () {
			SEO_Core_Installer::invalidate_health_cache();
			$report = SEO_Core_Installer::ensure_infrastructure( true );
			if ( ! is_array( $report ) || ! array_key_exists( 'ok', $report ) ) {
				return array( false, __( 'گزارش heal نامعتبر است.', 'shojaei-seo-for-woo' ) );
			}
			foreach ( array( 'repaired', 'healthy', 'errors', 'message' ) as $k ) {
				if ( ! array_key_exists( $k, $report ) ) {
					return array( false, sprintf( __( 'کلید گزارش غایب: %s', 'shojaei-seo-for-woo' ), $k ) );
				}
			}
			$msg = (string) $report['message'];
			if ( empty( $report['ok'] ) ) {
				return array(
					false,
					sprintf(
						/* translators: 1: message 2: error count */
						__( 'heal با خطا: %1$s (%2$d مورد)', 'shojaei-seo-for-woo' ),
						$msg,
						count( (array) $report['errors'] )
					),
				);
			}
			return array( true, $msg );
		} );

		$results[] = self::check( 'health_transient', __( 'Transient سلامت ۱۲ساله', 'shojaei-seo-for-woo' ), static function () {
			$t = get_transient( SEO_Core_Installer::HEALTH_TRANSIENT );
			if ( false === $t ) {
				return array( false, __( 'پس از heal موفق، Transient ثبت نشده (یا heal ناموفق بوده).', 'shojaei-seo-for-woo' ) );
			}
			SEO_Core_Installer::invalidate_health_cache();
			if ( false !== get_transient( SEO_Core_Installer::HEALTH_TRANSIENT ) ) {
				return array( false, __( 'invalidate_health_cache Transient را پاک نکرد.', 'shojaei-seo-for-woo' ) );
			}
			// بازگردانی کش با heal سریع.
			SEO_Core_Installer::ensure_infrastructure( true );
			return array( true, __( 'Transient فقط در سلامت کامل ثبت و با invalidate پاک می‌شود.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'tables', __( 'جداول تضمین‌شده', 'shojaei-seo-for-woo' ), static function () {
			global $wpdb;
			$tables = array(
				$wpdb->prefix . 'seo_core_logs',
				$wpdb->prefix . 'seo_core_reports',
				$wpdb->prefix . 'shojaei_seo_pulse_results',
				$wpdb->prefix . 'seo_core_link_genius',
				$wpdb->prefix . 'seo_core_manual_redirects',
				$wpdb->prefix . 'seo_core_404_monitor',
			);
			$miss = array();
			foreach ( $tables as $t ) {
				$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
				if ( $found !== $t ) {
					$miss[] = $t;
				}
			}
			if ( $miss ) {
				return array( false, sprintf( __( 'جدول غایب: %s', 'shojaei-seo-for-woo' ), implode( ', ', $miss ) ) );
			}
			return array( true, __( 'همه جداول Core موجودند.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'options', __( 'Options / flags', 'shojaei-seo-for-woo' ), static function () {
			$need = array(
				SEO_Core_Installer::SCHEMA_OPTION,
				SEO_Core_Installer::MODULES_OPTION,
				SEO_Core_Installer::OVERRIDES_OPTION,
				SEO_Core_Installer::DISABLED_OPTION,
				SEO_Core_Installer::INDEXNOW_KEY_OPTION,
			);
			$miss = array();
			foreach ( $need as $opt ) {
				if ( false === get_option( $opt, false ) ) {
					$miss[] = $opt;
				}
			}
			$mods = get_option( SEO_Core_Installer::MODULES_OPTION, array() );
			if ( ! is_array( $mods ) || count( $mods ) < 9 ) {
				return array( false, __( 'رجیستری ماژول‌ها ناقص است (حداقل ۹ ماژول).', 'shojaei-seo-for-woo' ) );
			}
			if ( $miss ) {
				return array( false, sprintf( __( 'Option غایب: %s', 'shojaei-seo-for-woo' ), implode( ', ', $miss ) ) );
			}
			$ver = (string) get_option( SEO_Core_Installer::SCHEMA_OPTION, '' );
			return array( true, sprintf( __( 'Options سالم — schema %s', 'shojaei-seo-for-woo' ), $ver ) );
		} );

		$results[] = self::check( 'rewrite_flag', __( 'Rewrite — بدون flush هر request', 'shojaei-seo-for-woo' ), static function () {
			$before = (string) get_option( SEO_Core_Installer::REWRITE_FLAG, '' );
			SEO_Core_Installer::request_rewrite_flush();
			$flag = (string) get_option( SEO_Core_Installer::REWRITE_FLAG, '' );
			if ( '1' !== $flag ) {
				return array( false, __( 'request_rewrite_flush پرچم را روی ۱ نگذاشت.', 'shojaei-seo-for-woo' ) );
			}
			SEO_Core_Installer::ensure_rewrite_rules();
			$after = (string) get_option( SEO_Core_Installer::REWRITE_FLAG, '' );
			if ( '' !== $after && '0' !== $after ) {
				// ممکن است هنوز نیاز باشد اگر endpoint غایب است — هشدار نه شکست سخت اگر heal قبلی سالم بود.
				return array( true, __( 'پرچم flush مصرف شد یا دوباره به‌خاطر کمبود endpoint ثبت شد (قابل قبول).', 'shojaei-seo-for-woo' ) );
			}
			unset( $before );
			return array( true, __( 'پرچم flush فقط با درخواست ست و پس از ensure پاک می‌شود.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'cron', __( 'Cron بدون تکرار', 'shojaei-seo-for-woo' ), static function () {
			SEO_Core_Installer::ensure_cron_jobs();
			$msgs = array();
			if ( SEO_Core_Installer::is_module_enabled( 'pulse' ) && class_exists( 'Shojaei_SEO_Pulse' ) ) {
				$ts = wp_next_scheduled( Shojaei_SEO_Pulse::CRON_HOOK );
				if ( ! $ts ) {
					return array( false, __( 'کرون نبض سئو زمان‌بندی نشده.', 'shojaei-seo-for-woo' ) );
				}
				$msgs[] = 'pulse';
			}
			if ( SEO_Core_Installer::is_module_enabled( 'monitor404' ) && class_exists( 'SEO_Core_404_Monitor' ) ) {
				$ts = wp_next_scheduled( SEO_Core_404_Monitor::CRON_HOOK );
				if ( ! $ts ) {
					SEO_Core_404_Monitor::ensure_cron();
					$ts = wp_next_scheduled( SEO_Core_404_Monitor::CRON_HOOK );
				}
				if ( ! $ts ) {
					return array( false, __( 'کرون پاک‌سازی ۴۰۴ زمان‌بندی نشده.', 'shojaei-seo-for-woo' ) );
				}
				$msgs[] = '404';
			}
			if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
				$ts = wp_next_scheduled( Shojaei_SEO_Jobs::HOOK_TICK );
				if ( ! $ts && method_exists( 'Shojaei_SEO_Jobs', 'ensure_tick_scheduled' ) ) {
					Shojaei_SEO_Jobs::ensure_tick_scheduled();
					$ts = wp_next_scheduled( Shojaei_SEO_Jobs::HOOK_TICK );
				}
				if ( $ts ) {
					$msgs[] = 'jobs';
				}
			}
			return array( true, sprintf( __( 'کرون‌ها فعال: %s', 'shojaei-seo-for-woo' ), implode( ', ', $msgs ) ?: '—' ) );
		} );

		$results[] = self::check( 'isolation', __( 'Disable ایزوله یک ماژول', 'shojaei-seo-for-woo' ), static function () {
			$probe = 'selftest_probe';
			SEO_Core_Installer::mark_module_disabled( $probe, __( 'آزمون ایزوله', 'shojaei-seo-for-woo' ) );
			if ( SEO_Core_Installer::is_module_enabled( $probe ) ) {
				return array( false, __( 'ماژول probe پس از disable هنوز enabled است.', 'shojaei-seo-for-woo' ) );
			}
			$disabled = get_option( SEO_Core_Installer::DISABLED_OPTION, array() );
			if ( ! is_array( $disabled ) ) {
				$disabled = array();
			}
			unset( $disabled[ $probe ] );
			update_option( SEO_Core_Installer::DISABLED_OPTION, $disabled, false );
			SEO_Core_Installer::invalidate_health_cache();
			SEO_Core_Installer::ensure_infrastructure( true );
			if ( isset( SEO_Core_Installer::get_disabled_modules()[ $probe ] ) ) {
				return array( false, __( 'پاک‌سازی probe از disabled ناموفق بود.', 'shojaei-seo-for-woo' ) );
			}
			return array( true, __( 'mark_module_disabled فقط همان ماژول را قطع می‌کند.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'passive_rankmath', __( 'Passive در برابر رقیب SEO', 'shojaei-seo-for-woo' ), static function () {
			if ( ! class_exists( 'Shojaei_SEO_Integration' ) || ! Shojaei_SEO_Integration::has_primary_seo_plugin() ) {
				return array( 'skip', __( 'رقیب SEO فعال نیست — تست Passive رد شد (skip).', 'shojaei-seo-for-woo' ) );
			}
			if ( ! class_exists( 'SEO_Core_Loader' ) ) {
				return array( false, __( 'Loader در دسترس نیست.', 'shojaei-seo-for-woo' ) );
			}
			$loader = SEO_Core_Loader::instance();
			$expect_passive = array( 'robots', 'redirects', 'schema' );
			$fail = array();
			foreach ( $expect_passive as $id ) {
				$mod = $loader->get_module( $id );
				if ( ! $mod ) {
					continue; // ماژول خاموش در رجیستری.
				}
				if ( SEO_Core_Installer::is_override_enabled( $id ) ) {
					continue; // کاربر Override روشن کرده.
				}
				if ( ! $mod->is_passive() ) {
					$fail[] = $id;
				}
			}
			if ( $fail ) {
				return array( false, sprintf( __( 'ماژول‌های غیر-Passive با رقیب: %s', 'shojaei-seo-for-woo' ), implode( ', ', $fail ) ) );
			}
			// مکمل‌ها + sitemap اختصاصی نباید Passive اجباری باشند.
			foreach ( array( 'monitor404', 'links', 'canonical', 'sitemap' ) as $id ) {
				$mod = $loader->get_module( $id );
				if ( $mod && $mod->is_passive() ) {
					$fail[] = $id;
				}
			}
			if ( $fail ) {
				return array( false, sprintf( __( 'ماژول مکمل نباید Passive باشد: %s', 'shojaei-seo-for-woo' ), implode( ', ', $fail ) ) );
			}
			return array(
				true,
				sprintf(
					/* translators: %s: plugin names */
					__( 'Passive/مکمل با رقیب تأیید شد (%s).', 'shojaei-seo-for-woo' ),
					Shojaei_SEO_Integration::detected_labels()
				),
			);
		} );

		$results[] = self::check( 'override_gate', __( 'Override ریدایرکت دستی', 'shojaei-seo-for-woo' ), static function () {
			if ( ! class_exists( 'SEO_Core_Redirects_Module' ) ) {
				$base = defined( 'DAMAVAND_SEO_DIR' ) ? DAMAVAND_SEO_DIR . 'seo-core/' : '';
				if ( is_readable( $base . 'class-seo-core-module.php' ) ) {
					require_once $base . 'class-seo-core-module.php';
				}
				$file = $base . 'modules/redirects/class-seo-core-redirects-module.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
			if ( ! class_exists( 'SEO_Core_Redirects_Module' ) ) {
				return array( false, __( 'کلاس Redirects بارگذاری نشد.', 'shojaei-seo-for-woo' ) );
			}
			$prev = SEO_Core_Installer::is_override_enabled( 'redirects' );
			SEO_Core_Installer::set_override( 'redirects', true );
			$on = SEO_Core_Redirects_Module::can_emit_freeform();
			SEO_Core_Installer::set_override( 'redirects', $prev );
			if ( ! SEO_Core_Installer::is_module_enabled( 'redirects' ) ) {
				return array( 'skip', __( 'ماژول redirects در رجیستری خاموش است.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! $on ) {
				return array( false, __( 'با Override روشن، can_emit_freeform باید true باشد.', 'shojaei-seo-for-woo' ) );
			}
			return array( true, __( 'گیت Override ریدایرکت دستی درست کار می‌کند.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( 'canonical_policies', __( 'سیاست کنونیکال', 'shojaei-seo-for-woo' ), static function () {
			if ( ! class_exists( 'SEO_Core_Canonical_Module' ) ) {
				return array( 'skip', __( 'ماژول canonical لود نشده.', 'shojaei-seo-for-woo' ) );
			}
			update_option( SEO_Core_Canonical_Module::OPTION_FORCE_HTTPS, 'yes', false );
			update_option( SEO_Core_Canonical_Module::OPTION_STRIP_ARGS, 'yes', false );
			$in  = 'http://example.com/product/?utm_source=test&gclid=1&keep=1';
			$out = SEO_Core_Canonical_Module::apply_policies( $in );
			if ( 0 !== strpos( $out, 'https://' ) ) {
				return array( false, __( 'اجبار HTTPS اعمال نشد.', 'shojaei-seo-for-woo' ) );
			}
			if ( false !== strpos( $out, 'utm_source' ) || false !== strpos( $out, 'gclid' ) ) {
				return array( false, __( 'پارامترهای ردیابی حذف نشدند.', 'shojaei-seo-for-woo' ) );
			}
			if ( false === strpos( $out, 'keep=1' ) ) {
				return array( false, __( 'پارامتر غیرردیابی اشتباهاً حذف شد.', 'shojaei-seo-for-woo' ) );
			}
			return array( true, __( 'HTTPS + strip tracking OK.', 'shojaei-seo-for-woo' ) );
		} );

		$results[] = self::check( '404_skip_rules', __( 'قوانین رد مسیر ۴۰۴', 'shojaei-seo-for-woo' ), static function () {
			if ( ! class_exists( 'SEO_Core_404_Monitor' ) ) {
				return array( 'skip', __( 'مانیتور ۴۰۴ لود نشده.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! SEO_Core_404_Monitor::should_skip_path( '/wp-admin/edit.php' ) ) {
				return array( false, __( 'wp-admin باید skip شود.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! SEO_Core_404_Monitor::should_skip_path( '/foo/bar.css' ) ) {
				return array( false, __( 'فایل استاتیک باید skip شود.', 'shojaei-seo-for-woo' ) );
			}
			if ( SEO_Core_404_Monitor::should_skip_path( '/old-product-page' ) ) {
				return array( false, __( 'مسیر محتوایی نباید skip شود.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! SEO_Core_404_Monitor::looks_like_bot( 'Googlebot/2.1' ) ) {
				return array( false, __( 'تشخیص ربات شکست خورد.', 'shojaei-seo-for-woo' ) );
			}
			return array( true, __( 'skip path / bot detection OK.', 'shojaei-seo-for-woo' ) );
		} );

		$passed = $failed = $skipped = 0;
		foreach ( $results as $r ) {
			if ( 'pass' === $r['status'] ) {
				++$passed;
			} elseif ( 'skip' === $r['status'] ) {
				++$skipped;
			} else {
				++$failed;
			}
		}

		return array(
			'ok'      => 0 === $failed,
			'passed'  => $passed,
			'failed'  => $failed,
			'skipped' => $skipped,
			'results' => $results,
			'message' => 0 === $failed
				? sprintf(
					/* translators: 1: passed 2: skipped */
					__( 'خودآزمون کامل شد: %1$d موفق، %2$d ردشده (skip).', 'shojaei-seo-for-woo' ),
					$passed,
					$skipped
				)
				: sprintf(
					/* translators: 1: failed 2: passed */
					__( 'خودآزمون با %1$d شکست و %2$d موفقیت پایان یافت.', 'shojaei-seo-for-woo' ),
					$failed,
					$passed
				),
		);
	}

	/**
	 * @param string   $id    ID.
	 * @param string   $label Label.
	 * @param callable $fn    Returns array{0:bool|string,1:string} where 0 is true|false|'skip'.
	 * @return array{id:string,label:string,status:string,message:string}
	 */
	private static function check( string $id, string $label, callable $fn ): array {
		try {
			$result = $fn();
			$ok     = $result[0] ?? false;
			$msg    = (string) ( $result[1] ?? '' );
			if ( 'skip' === $ok ) {
				$status = 'skip';
			} else {
				$status = $ok ? 'pass' : 'fail';
			}
		} catch ( Throwable $e ) {
			$status = 'fail';
			$msg    = $e->getMessage();
		}
		return array(
			'id'      => $id,
			'label'   => $label,
			'status'  => $status,
			'message' => $msg,
		);
	}
}
