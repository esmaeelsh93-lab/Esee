<?php
/**
 * SEO Core — جداول مشترک (لاگ‌ها و گزارش‌ها).
 *
 * جدول‌ها با پیشوند وردپرس ساخته می‌شوند؛ روی نصب پیش‌فرض:
 * wp_seo_core_logs و wp_seo_core_reports
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_DB
 */
class SEO_Core_DB {

	/**
	 * جدول لاگ مشترک همه ماژول‌ها.
	 */
	public static function logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_logs';
	}

	/**
	 * جدول گزارش آنالیز (نبض سئو / Analyzer).
	 */
	public static function reports_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_reports';
	}

	/**
	 * نصب اسکیما.
	 */
	public static function install(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$logs = self::logs_table();
		dbDelta(
			"CREATE TABLE {$logs} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				module VARCHAR(40) NOT NULL DEFAULT '',
				level VARCHAR(20) NOT NULL DEFAULT 'info',
				message TEXT NOT NULL,
				context LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY module_level (module, level),
				KEY created_at (created_at)
			) {$charset};"
		);

		$reports = self::reports_table();
		dbDelta(
			"CREATE TABLE {$reports} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_id BIGINT(20) UNSIGNED NOT NULL,
				post_type VARCHAR(40) NOT NULL DEFAULT 'post',
				score TINYINT UNSIGNED NOT NULL DEFAULT 0,
				score_onpage TINYINT UNSIGNED NOT NULL DEFAULT 0,
				score_content TINYINT UNSIGNED NOT NULL DEFAULT 0,
				score_technical TINYINT UNSIGNED NOT NULL DEFAULT 0,
				score_links TINYINT UNSIGNED NOT NULL DEFAULT 0,
				critical_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				warning_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				is_orphan TINYINT(1) NOT NULL DEFAULT 0,
				issues LONGTEXT NULL,
				content_hash CHAR(32) NOT NULL DEFAULT '',
				analyzed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE KEY post_id (post_id),
				KEY score (score),
				KEY post_type (post_type),
				KEY is_orphan (is_orphan),
				KEY critical_count (critical_count),
				KEY analyzed_at (analyzed_at)
			) {$charset};"
		);
	}

	/**
	 * حذف جداول (فقط wipe کامل).
	 */
	public static function uninstall(): void {
		global $wpdb;
		foreach ( array( self::logs_table(), self::reports_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * درج لاگ سبک — بدون کوئری سنگین در مسیر داغ.
	 *
	 * @param string              $module  شناسه ماژول.
	 * @param string              $level   سطح.
	 * @param string              $message پیام.
	 * @param array<string,mixed> $context زمینه.
	 */
	public static function log( string $module, string $level, string $message, array $context = array() ): void {
		global $wpdb;
		$table = self::logs_table();
		$level = sanitize_key( $level );
		if ( ! in_array( $level, array( 'info', 'warning', 'error', 'debug' ), true ) ) {
			$level = 'info';
		}

		$wpdb->insert(
			$table,
			array(
				'module'     => sanitize_key( $module ),
				'level'      => $level,
				'message'    => wp_strip_all_tags( $message ),
				'context'    => $context ? wp_json_encode( $context, JSON_UNESCAPED_UNICODE ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		// نگهداری سبک: ردیف‌های قدیمی‌تر از ۳۰ روز را گاه‌به‌گاه پاک کن.
		if ( 1 === wp_rand( 1, 40 ) ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$cutoff
				)
			);
		}
	}
}
