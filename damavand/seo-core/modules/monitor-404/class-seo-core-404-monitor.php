<?php
/**
 * ماژول مانیتور 404 — هسته سئو.
 *
 * ثبت URLهای 404 پس از عبور از ریدایرکت‌ها؛ مکمل Rank Math (بدون تداخل خروجی).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_404_Monitor
 */
class SEO_Core_404_Monitor extends SEO_Core_Module {

	public const CRON_HOOK       = 'seo_core_404_purge';
	public const OPTION_RETENTION = 'seo_core_404_retention_days';
	public const OPTION_IGNORE_BOTS = 'seo_core_404_ignore_bots';

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'monitor404';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'مانیتور ۴۰۴', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'ثبت مسیرهای ۴۰۴، تعداد بازدید و ارجاع‌دهنده — برای ساخت ریدایرکت یا نادیده گرفتن.', 'shojaei-seo-for-woo' );
	}

	/**
	 * مانیتور ۴۰۴ مکمل است؛ با Rank Math تداخل خروجی ندارد.
	 */
	public function is_passive(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		self::create_table();
		if ( false === get_option( self::OPTION_RETENTION, false ) ) {
			add_option( self::OPTION_RETENTION, 30, '', false );
		}
		if ( false === get_option( self::OPTION_IGNORE_BOTS, false ) ) {
			add_option( self::OPTION_IGNORE_BOTS, 'yes', '', false );
		}
		self::ensure_cron();
	}

	/**
	 * {@inheritdoc}
	 */
	public function uninstall(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::OPTION_RETENTION );
		delete_option( self::OPTION_IGNORE_BOTS );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// بعد از ریدایرکت‌های slug/manual/oos (اولویت ۰) تا فقط ۴۰۴ واقعی ثبت شود.
		add_action( 'template_redirect', array( $this, 'maybe_log' ), 99 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'purge_old' ) );

		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_core_404', array( $this, 'ajax' ) );
		}
	}

	/**
	 * نام جدول.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_404_monitor';
	}

	/**
	 * ساخت جدول.
	 */
	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE {$table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				url_path VARCHAR(500) NOT NULL DEFAULT '',
				hits BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
				referer VARCHAR(500) NOT NULL DEFAULT '',
				user_agent VARCHAR(255) NOT NULL DEFAULT '',
				status VARCHAR(20) NOT NULL DEFAULT 'open',
				first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY url_path (url_path(191)),
				KEY status_hits (status, hits),
				KEY last_seen (last_seen)
			) {$charset};"
		);
	}

	/**
	 * کرون پاک‌سازی — بدون تکرار.
	 */
	public static function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * ثبت ۴۰۴ در صورت نیاز.
	 */
	public function maybe_log(): void {
		if ( ! $this->is_enabled() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( ! is_404() ) {
			return;
		}

		$path = self::current_path();
		if ( '' === $path || '/' === $path ) {
			return;
		}

		// فایل‌های استاتیک و مسیرهای سیستمی را رد کن.
		if ( self::should_skip_path( $path ) ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '';
		if ( 'yes' === (string) get_option( self::OPTION_IGNORE_BOTS, 'yes' ) && self::looks_like_bot( $ua ) ) {
			return;
		}

		$referer = '';
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$referer = substr( (string) $referer, 0, 500 );
		}

		// محدودیت نرخ: حداکثر ۱۲۰ ثبت جدید در ساعت برای کل سایت.
		$bucket = (int) get_transient( 'seo_core_404_hour_count' );
		if ( $bucket >= 120 ) {
			return;
		}
		set_transient( 'seo_core_404_hour_count', $bucket + 1, HOUR_IN_SECONDS );

		self::upsert_hit( $path, $referer, $ua );
	}

	/**
	 * مسیر نرمال‌شده درخواست فعلی.
	 */
	public static function current_path(): string {
		global $wp;
		$path = '';
		if ( ! empty( $wp->request ) ) {
			$path = '/' . ltrim( (string) $wp->request, '/' );
		} elseif ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		}
		$path = rawurldecode( $path );
		$path = untrailingslashit( '/' . ltrim( $path, '/' ) );
		if ( '' === $path ) {
			$path = '/';
		}

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );
		if ( $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) ) ?: '/';
			$path = untrailingslashit( $path ) ?: '/';
		}

		return substr( $path, 0, 500 );
	}

	/**
	 * @param string $path Path.
	 */
	public static function should_skip_path( string $path ): bool {
		$lower = strtolower( $path );
		$exts  = array( '.css', '.js', '.map', '.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.mp4', '.webm', '.xml', '.txt', '.json' );
		foreach ( $exts as $ext ) {
			if ( substr( $lower, -strlen( $ext ) ) === $ext ) {
				return true;
			}
		}
		$prefixes = array( '/wp-admin', '/wp-json', '/wp-cron.php', '/xmlrpc.php', '/cdn-cgi' );
		foreach ( $prefixes as $p ) {
			if ( 0 === strpos( $lower, $p ) ) {
				return true;
			}
		}
		return (bool) apply_filters( 'seo_core_404_should_skip', false, $path );
	}

	/**
	 * @param string $ua User-Agent.
	 */
	public static function looks_like_bot( string $ua ): bool {
		if ( '' === $ua ) {
			return false;
		}
		$bots = array( 'bot', 'spider', 'crawl', 'slurp', 'facebookexternalhit', 'preview', 'wget', 'curl', 'python-requests', 'scrapy' );
		$ua_l = strtolower( $ua );
		foreach ( $bots as $needle ) {
			if ( false !== strpos( $ua_l, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * افزایش شمارنده یا درج ردیف جدید.
	 *
	 * @param string $path    Path.
	 * @param string $referer Referer.
	 * @param string $ua      UA.
	 */
	public static function upsert_hit( string $path, string $referer = '', string $ua = '' ): void {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM {$table} WHERE url_path = %s LIMIT 1", $path ) );
		if ( $row ) {
			if ( 'ignored' === (string) $row->status ) {
				return;
			}
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET hits = hits + 1, last_seen = %s, referer = IF(%s = '', referer, %s), user_agent = IF(%s = '', user_agent, %s) WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$now,
					$referer,
					$referer,
					$ua,
					$ua,
					(int) $row->id
				)
			);
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'url_path'   => $path,
				'hits'       => 1,
				'referer'    => $referer,
				'user_agent' => $ua,
				'status'     => 'open',
				'first_seen' => $now,
				'last_seen'  => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * پاک‌سازی ردیف‌های قدیمی.
	 */
	public static function purge_old(): void {
		global $wpdb;
		$table = self::table();
		$days  = max( 7, min( 365, absint( get_option( self::OPTION_RETENTION, 30 ) ) ) );
		$cut   = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE last_seen < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cut
			)
		);
	}

	/**
	 * آمار داشبورد.
	 *
	 * @return array{total:int,open:int,ignored:int,hits:int}
	 */
	public static function stats(): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_n,
				SUM(CASE WHEN status = 'ignored' THEN 1 ELSE 0 END) AS ignored_n,
				COALESCE(SUM(hits),0) AS hits
			FROM {$table}",
			ARRAY_A
		);
		return array(
			'total'   => (int) ( $row['total'] ?? 0 ),
			'open'    => (int) ( $row['open_n'] ?? 0 ),
			'ignored' => (int) ( $row['ignored_n'] ?? 0 ),
			'hits'    => (int) ( $row['hits'] ?? 0 ),
		);
	}

	/**
	 * لیست برای UI.
	 *
	 * @param string $status open|ignored|all|fixed.
	 * @param int    $limit  Limit.
	 * @param int    $offset Offset.
	 * @return object[]
	 */
	public static function list_rows( string $status = 'open', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;
		$table  = self::table();
		$limit  = max( 1, min( 200, $limit ) );
		$offset = max( 0, $offset );
		$status = sanitize_key( $status );

		if ( 'all' === $status ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit,
				$offset
			);
		} else {
			if ( ! in_array( $status, array( 'open', 'ignored', 'fixed' ), true ) ) {
				$status = 'open';
			}
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$limit,
				$offset
			);
		}
		$rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * AJAX ادمین.
	 */
	public function ajax(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		$can = current_user_can( 'manage_options' )
			|| current_user_can( 'manage_woocommerce' )
			|| ( class_exists( 'SEO_Core_Installer' ) && current_user_can( SEO_Core_Installer::CAPABILITY ) );
		if ( ! $can ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['monitor_action'] ?? '' ) );

		switch ( $action ) {
			case 'stats':
				wp_send_json_success( self::stats() );

			case 'ignore':
				$id = absint( $_POST['id'] ?? 0 );
				if ( $id < 1 || ! self::set_status( $id, 'ignored' ) ) {
					wp_send_json_error( array( 'message' => __( 'ردیف یافت نشد.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success( array( 'message' => __( 'مسیر نادیده گرفته شد.', 'shojaei-seo-for-woo' ) ) );

			case 'reopen':
				$id = absint( $_POST['id'] ?? 0 );
				if ( $id < 1 || ! self::set_status( $id, 'open' ) ) {
					wp_send_json_error( array( 'message' => __( 'ردیف یافت نشد.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success( array( 'message' => __( 'مسیر دوباره باز شد.', 'shojaei-seo-for-woo' ) ) );

			case 'delete':
				$id = absint( $_POST['id'] ?? 0 );
				if ( $id < 1 || ! self::delete_row( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'حذف ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success( array( 'message' => __( 'ردیف حذف شد.', 'shojaei-seo-for-woo' ) ) );

			case 'clear_open':
				self::clear_by_status( 'open' );
				wp_send_json_success( array( 'message' => __( 'همه مسیرهای باز پاک شدند.', 'shojaei-seo-for-woo' ) ) );

			case 'purge_now':
				self::purge_old();
				wp_send_json_success( array( 'message' => __( 'پاک‌سازی ردیف‌های قدیمی اجرا شد.', 'shojaei-seo-for-woo' ) ) );

			case 'save_settings':
				$days = max( 7, min( 365, absint( $_POST['retention_days'] ?? 30 ) ) );
				$bots = ! empty( $_POST['ignore_bots'] ) ? 'yes' : 'no';
				update_option( self::OPTION_RETENTION, $days, false );
				update_option( self::OPTION_IGNORE_BOTS, $bots, false );
				wp_send_json_success( array( 'message' => __( 'تنظیمات مانیتور ۴۰۴ ذخیره شد.', 'shojaei-seo-for-woo' ) ) );

			case 'create_redirect':
				$id   = absint( $_POST['id'] ?? 0 );
				$dest = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
				$row  = self::get_row( $id );
				if ( ! $row ) {
					wp_send_json_error( array( 'message' => __( 'ردیف یافت نشد.', 'shojaei-seo-for-woo' ) ) );
				}
				if ( ! class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
					wp_send_json_error( array( 'message' => __( 'ماژول ریدایرکت دستی در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
				}
				$result = Shojaei_SEO_Manual_Redirect::add_redirect(
					array(
						'sources'       => array( (string) $row->url_path ),
						'destination'   => $dest,
						'redirect_type' => '301',
						'match_type'    => 'exact',
						'is_active'     => true,
					)
				);
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? __( 'ساخت ریدایرکت ناموفق.', 'shojaei-seo-for-woo' ) ) ) );
				}
				self::set_status( $id, 'fixed' );
				wp_send_json_success(
					array(
						'message' => __( 'ریدایرکت ۳۰۱ ساخته شد و این ۴۰۴ به‌عنوان «رفع‌شده» علامت خورد.', 'shojaei-seo-for-woo' ),
					)
				);

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * @param int $id ID.
	 */
	public static function get_row( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row instanceof stdClass ? $row : null;
	}

	/**
	 * @param int    $id     ID.
	 * @param string $status Status.
	 */
	public static function set_status( int $id, string $status ): bool {
		global $wpdb;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, array( 'open', 'ignored', 'fixed' ), true ) ) {
			return false;
		}
		$n = $wpdb->update(
			self::table(),
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		return false !== $n;
	}

	/**
	 * @param int $id ID.
	 */
	public static function delete_row( int $id ): bool {
		global $wpdb;
		$n = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $n && $n > 0;
	}

	/**
	 * @param string $status Status.
	 */
	public static function clear_by_status( string $status ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'status' => sanitize_key( $status ) ), array( '%s' ) );
	}
}
