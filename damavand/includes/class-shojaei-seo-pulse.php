<?php
/**
 * SEO Pulse — rule-based analyzer (نبض سئو).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Pulse
 */
class Shojaei_SEO_Pulse {

	public const CRON_HOOK = 'shojaei_seo_pulse_daily';

	/**
	 * Constructor — schedule light daily rescan trigger.
	 */
	public function __construct() {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'pulse' ) ) {
			return;
		}
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_enqueue_scan' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ), 30 );
	}

	/**
	 * Results table (= pulse_results با پیشوند وردپرس).
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_pulse_results';
	}

	/**
	 * آیا زیرساخت Pulse آماده است؟ (بدون Fatal).
	 */
	public static function is_ready(): bool {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'pulse' ) ) {
			return false;
		}
		if ( self::table_exists() ) {
			return true;
		}
		// تلاش ترمیم سبک.
		try {
			self::install();
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// ادامه با غیرفعال‌سازی.
		}
		if ( self::table_exists() ) {
			return true;
		}
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::mark_module_disabled(
				'pulse',
				__( 'جدول نتایج نبض سئو در دسترس نیست. ماژول تحلیلگر موقتاً غیرفعال است.', 'shojaei-seo-for-woo' )
			);
		}
		return false;
	}

	/**
	 * وجود جدول.
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ( $found === $table );
	}

	/**
	 * برچسب وضعیت کلی از روی امتیاز.
	 *
	 * @param int $score Score 0–100.
	 * @return array{key:string,label:string}
	 */
	public static function status_from_score( int $score ): array {
		if ( $score >= 75 ) {
			return array(
				'key'   => 'good',
				'label' => __( 'خوب', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $score >= 50 ) {
			return array(
				'key'   => 'needs_improvement',
				'label' => __( 'نیازمند بهبود', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'key'   => 'poor',
			'label' => __( 'ضعیف', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * تحلیل یک نوشته (دستی از پنل).
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force.
	 * @return array<string,mixed>
	 */
	public static function analyze_one( int $post_id, bool $force = true ): array {
		if ( ! self::is_ready() ) {
			return array(
				'ok'      => false,
				'message' => __( 'ماژول نبض سئو آماده نیست (جدول یا زیرساخت).', 'shojaei-seo-for-woo' ),
			);
		}
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::is_410_excluded( $post_id ) ) {
			self::forget_post( $post_id );
			return array(
				'ok'          => false,
				'skipped_410' => true,
				'message'     => __( 'این محصول وضعیت ۴۱۰ Gone دارد و از نبض سئو حذف شده است.', 'shojaei-seo-for-woo' ),
			);
		}
		if ( ! class_exists( 'Shojaei_SEO_Pulse_Engine' ) ) {
			return array( 'ok' => false, 'message' => __( 'موتور تحلیل یافت نشد.', 'shojaei-seo-for-woo' ) );
		}
		$engine = new Shojaei_SEO_Pulse_Engine();
		$result = $engine->analyze_post( $post_id, $force );
		$score  = (int) ( $result['score'] ?? 0 );
		$status = self::status_from_score( $score );
		return array_merge(
			$result,
			array(
				'ok'           => ! empty( $result['saved'] ) || ! empty( $result['skipped'] ),
				'status'       => $status['key'],
				'status_label' => $status['label'],
				'message'      => ! empty( $result['saved'] )
					? sprintf(
						/* translators: 1: score 2: status */
						__( 'تحلیل ذخیره شد — امتیاز %1$d (%2$s).', 'shojaei-seo-for-woo' ),
						$score,
						$status['label']
					)
					: ( ! empty( $result['skipped'] )
						? __( 'محتوا تغییر نکرده؛ تحلیل قبلی معتبر است.', 'shojaei-seo-for-woo' )
						: __( 'تحلیل انجام نشد.', 'shojaei-seo-for-woo' ) ),
			)
		);
	}

	/**
	 * Remove a post from pulse results (e.g. after 410 Gone).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function forget_post( int $post_id ): void {
		if ( $post_id < 1 || ! self::table_exists() ) {
			return;
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete( $table, array( 'post_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Drop all pulse rows for current 410 products.
	 */
	public static function purge_410_rows(): void {
		if ( ! self::table_exists() || ! class_exists( 'Shojaei_SEO_Helpers' ) ) {
			return;
		}
		$ids = Shojaei_SEO_Helpers::get_410_excluded_ids();
		if ( empty( $ids ) ) {
			return;
		}
		global $wpdb;
		$table = self::table();
		$in    = implode( ',', array_map( 'absint', $ids ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$table} WHERE post_id IN ({$in})" );
	}

	/**
	 * Install schema (activation / upgrade).
	 */
	public static function install(): void {
		self::create_tables();
		self::ensure_cron();
	}

	/**
	 * Drop tables (full uninstall wipe only — not on deactivate).
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Create tables.
	 */
	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$table} (
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
	 * Ensure daily cron exists (enqueues background job — does not block admin).
	 */
	public static function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback: queue a pulse scan job (chunked).
	 */
	public static function cron_enqueue_scan(): void {
		if ( class_exists( 'Shojaei_SEO_Jobs' ) && Shojaei_SEO_Jobs::has_active( 'seo_pulse_scan' ) ) {
			return;
		}
		self::start_scan( false );
	}

	/**
	 * Start full site pulse scan in background.
	 *
	 * @param bool $force Re-analyze even if content hash unchanged.
	 * @return array{ok:bool,message:string,job_id?:string,total?:int}
	 */
	public static function start_scan( bool $force = true ): array {
		if ( ! self::is_ready() ) {
			return array( 'ok' => false, 'message' => __( 'ماژول نبض سئو آماده نیست؛ جدول نتایج ساخته نشد.', 'shojaei-seo-for-woo' ) );
		}
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			return array( 'ok' => false, 'message' => __( 'صف جاب در دسترس نیست.', 'shojaei-seo-for-woo' ) );
		}
		if ( Shojaei_SEO_Jobs::has_active( 'seo_pulse_scan' ) ) {
			return array( 'ok' => false, 'message' => __( 'اسکن نبض سئو همین حالا در حال اجراست.', 'shojaei-seo-for-woo' ) );
		}

		$ids = get_posts(
			array(
				'post_type'              => self::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'post__not_in'           => class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_ids() : array(),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = array_map( 'absint', is_array( $ids ) ? $ids : array() );
		self::purge_410_rows();

		$job = Shojaei_SEO_Jobs::enqueue(
			'seo_pulse_scan',
			array(
				'post_ids' => $ids,
				'force'    => $force ? 1 : 0,
			),
			array( 'total' => count( $ids ) )
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: count */
				__( 'تحلیل نبض سئو برای %d صفحه در پس‌زمینه صف شد (بدون کند کردن ادمین).', 'shojaei-seo-for-woo' ),
				count( $ids )
			),
			'job_id'  => $job,
			'total'   => count( $ids ),
		);
	}

	/**
	 * Analyzable post types.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = array( 'post', 'page', 'product' );
		return array_values( array_unique( apply_filters( 'shojaei_seo_pulse_post_types', $types ) ) );
	}

	/**
	 * Process a chunk of post IDs.
	 *
	 * @param int[] $ids   IDs.
	 * @param bool  $force Force.
	 * @return array{processed:int,saved:int}
	 */
	public static function process_ids( array $ids, bool $force = false ): array {
		if ( ! self::is_ready() ) {
			return array(
				'processed' => 0,
				'saved'     => 0,
			);
		}
		$engine    = new Shojaei_SEO_Pulse_Engine();
		$processed = 0;
		$saved     = 0;
		$gone      = class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_map() : array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				continue;
			}
			if ( isset( $gone[ $id ] ) ) {
				self::forget_post( $id );
				continue;
			}
			++$processed;
			$result = $engine->analyze_post( $id, $force );
			if ( ! empty( $result['saved'] ) ) {
				++$saved;
			}
		}
		return array(
			'processed' => $processed,
			'saved'     => $saved,
		);
	}

	/**
	 * Upsert analysis row.
	 *
	 * @param array $row Row data.
	 */
	public static function save_result( array $row ): bool {
		if ( ! self::table_exists() ) {
			return false;
		}
		global $wpdb;
		$table   = self::table();
		$post_id = absint( $row['post_id'] ?? 0 );
		if ( $post_id < 1 ) {
			return false;
		}

		$issues = $row['issues'] ?? array();
		if ( is_array( $issues ) ) {
			$issues = wp_json_encode( $issues, JSON_UNESCAPED_UNICODE );
		}

		$data = array(
			'post_id'         => $post_id,
			'post_type'       => sanitize_key( (string) ( $row['post_type'] ?? 'post' ) ),
			'score'           => max( 0, min( 100, (int) ( $row['score'] ?? 0 ) ) ),
			'score_onpage'    => max( 0, min( 100, (int) ( $row['score_onpage'] ?? 0 ) ) ),
			'score_content'   => max( 0, min( 100, (int) ( $row['score_content'] ?? 0 ) ) ),
			'score_technical' => max( 0, min( 100, (int) ( $row['score_technical'] ?? 0 ) ) ),
			'score_links'     => max( 0, min( 100, (int) ( $row['score_links'] ?? 0 ) ) ),
			'critical_count'  => max( 0, (int) ( $row['critical_count'] ?? 0 ) ),
			'warning_count'   => max( 0, (int) ( $row['warning_count'] ?? 0 ) ),
			'is_orphan'       => ! empty( $row['is_orphan'] ) ? 1 : 0,
			'issues'          => (string) $issues,
			'content_hash'    => substr( sanitize_text_field( (string) ( $row['content_hash'] ?? '' ) ), 0, 32 ),
			'analyzed_at'     => current_time( 'mysql' ),
		);

		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) {
			$ok = false !== $wpdb->update( $table, $data, array( 'post_id' => $post_id ) );
		} else {
			$ok = false !== $wpdb->insert( $table, $data );
		}
		if ( $ok ) {
			/**
			 * پس از ذخیره نتیجه نبض سئو — برای همگام‌سازی با هسته سئو.
			 *
			 * @param array $row Row.
			 */
			do_action( 'shojaei_seo_pulse_result_saved', $data );
		}
		return $ok;
	}

	/**
	 * Dashboard aggregates.
	 *
	 * @return array<string,mixed>
	 */
	public static function dashboard_stats(): array {
		$empty = array(
			'total'     => 0,
			'avg_score' => 0,
			'orphan'    => 0,
			'critical'  => 0,
			'low_score' => 0,
			'broken'    => 0,
			'scanning'  => false,
			'ready'     => false,
		);
		if ( ! self::is_ready() ) {
			return $empty;
		}
		self::purge_410_rows();
		global $wpdb;
		$table = self::table();
		$excl  = self::sql_exclude_410( 'post_id' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE 1=1{$excl}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$avg = (int) round( (float) $wpdb->get_var( "SELECT AVG(score) FROM {$table} WHERE 1=1{$excl}" ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$orphan = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_orphan = 1{$excl}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$critical = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE critical_count > 0{$excl}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$low = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE score < 50{$excl}" );

		$broken = 0;
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			$counts = Shojaei_SEO_Link_Genius::inventory_counts();
			$broken = (int) ( $counts['broken'] ?? 0 );
		}

		$busy = class_exists( 'Shojaei_SEO_Jobs' ) && Shojaei_SEO_Jobs::has_active( 'seo_pulse_scan' );

		return array(
			'total'     => $total,
			'avg_score' => $avg,
			'orphan'    => $orphan,
			'critical'  => $critical,
			'low_score' => $low,
			'broken'    => $broken,
			'scanning'  => $busy,
			'ready'     => true,
			'status'    => self::status_from_score( $avg ),
		);
	}

	/**
	 * SQL fragment: AND post_id NOT IN (...410...).
	 *
	 * @param string $column Column name.
	 */
	private static function sql_exclude_410( string $column = 'post_id' ): string {
		if ( ! class_exists( 'Shojaei_SEO_Helpers' ) ) {
			return '';
		}
		$ids = Shojaei_SEO_Helpers::get_410_excluded_ids();
		if ( empty( $ids ) ) {
			return '';
		}
		$col = preg_replace( '/[^a-zA-Z0-9_.]/', '', $column );
		$in  = implode( ',', array_map( 'absint', $ids ) );
		return " AND {$col} NOT IN ({$in})";
	}

	/**
	 * List results for admin table.
	 *
	 * @param array $args Filters.
	 * @return array{rows:object[],total:int}
	 */
	public static function query_results( array $args = array() ): array {
		if ( ! self::table_exists() ) {
			return array(
				'rows'  => array(),
				'total' => 0,
			);
		}
		global $wpdb;
		$table  = self::table();
		$filter = sanitize_key( (string) ( $args['filter'] ?? 'all' ) );
		$q      = trim( (string) ( $args['q'] ?? '' ) );
		$page   = max( 1, absint( $args['page'] ?? 1 ) );
		$per    = max( 10, min( 100, absint( $args['per_page'] ?? 40 ) ) );
		$offset = ( $page - 1 ) * $per;

		$where  = array( '1=1' );
		$params = array();

		if ( 'orphan' === $filter ) {
			$where[] = 'is_orphan = 1';
		} elseif ( 'critical' === $filter ) {
			$where[] = 'critical_count > 0';
		} elseif ( 'low' === $filter ) {
			$where[] = 'score < 50';
		} elseif ( 'good' === $filter ) {
			$where[] = 'score >= 75';
		}

		$excl = self::sql_exclude_410( 'r.post_id' );
		$excl_plain = self::sql_exclude_410( 'post_id' );

		$sql_where = implode( ' AND ', $where );

		if ( '' !== $q ) {
			// Join titles via posts.
			$like = '%' . $wpdb->esc_like( $q ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} r
					INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id
					WHERE {$sql_where}{$excl} AND p.post_title LIKE %s",
					$like
				)
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.* FROM {$table} r
					INNER JOIN {$wpdb->posts} p ON p.ID = r.post_id
					WHERE {$sql_where}{$excl} AND p.post_title LIKE %s
					ORDER BY r.score ASC, r.critical_count DESC
					LIMIT %d OFFSET %d",
					$like,
					$per,
					$offset
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}{$excl_plain}" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$sql_where}{$excl_plain}
					ORDER BY score ASC, critical_count DESC
					LIMIT %d OFFSET %d",
					$per,
					$offset
				)
			);
		}

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}
}
