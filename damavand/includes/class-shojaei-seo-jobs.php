<?php
/**
 * Dedicated Job Queue — DB table + batch runner (no Redis/SaaS).
 *
 * Runners: Action Scheduler (preferred) → Ajax/REST drain → internal WP-Cron fallback.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Jobs
 */
class Shojaei_SEO_Jobs {

	public const HOOK_TICK     = 'shojaei_seo_jobs_tick';
	public const HOOK_AS_CHUNK = 'shojaei_seo_as_job_chunk';
	public const CRON_SCHEDULE = 'shojaei_seo_every_minute';

	public const STATUS_PENDING   = 'pending';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_DONE      = 'done';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_CANCELLED = 'cancelled';

	public const LOCK_STALE_SECONDS = 300;
	public const DEFAULT_MAX_ATTEMPTS = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) );
		add_action( self::HOOK_TICK, array( $this, 'cron_tick' ) );
		add_action( self::HOOK_AS_CHUNK, array( $this, 'as_process_chunk' ), 10, 1 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_ajax_shojaei_seo_job_tick', array( $this, 'ajax_tick' ) );

		// Keep legacy batch tick draining the same queue.
		add_action( 'shojaei_seo_batch_tick', array( $this, 'cron_tick' ) );

		self::ensure_tick_scheduled();
	}

	/**
	 * Jobs table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_jobs';
	}

	/**
	 * Create / upgrade jobs table (dbDelta).
	 */
	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_key VARCHAR(32) NOT NULL,
			type VARCHAR(50) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			total INT NOT NULL DEFAULT 0,
			offset_n INT NOT NULL DEFAULT 0,
			processed INT NOT NULL DEFAULT 0,
			failed INT NOT NULL DEFAULT 0,
			cursor_n BIGINT(20) NOT NULL DEFAULT 0,
			attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
			max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
			batch_id VARCHAR(36) NOT NULL DEFAULT '',
			payload LONGTEXT NULL,
			message TEXT NULL,
			last_error TEXT NULL,
			locked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY job_key (job_key),
			KEY status_created (status, created_at),
			KEY type_status (type, status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function register_schedules( array $schedules ): array {
		if ( ! isset( $schedules[ self::CRON_SCHEDULE ] ) ) {
			$schedules[ self::CRON_SCHEDULE ] = array(
				'interval' => 60,
				'display'  => __( 'هر دقیقه (Shojaei SEO Jobs)', 'shojaei-seo-for-woo' ),
			);
		}
		return $schedules;
	}

	/**
	 * Ensure internal cron tick exists.
	 */
	public static function ensure_tick_scheduled(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) );
		if ( ! wp_next_scheduled( self::HOOK_TICK ) ) {
			wp_schedule_event( time() + 5, self::CRON_SCHEDULE, self::HOOK_TICK );
		}
	}

	/**
	 * Clear schedules on deactivate.
	 */
	public static function clear_scheduled(): void {
		wp_clear_scheduled_hook( self::HOOK_TICK );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_AS_CHUNK, null, 'shojaei-seo' );
		}
	}

	/**
	 * Configurable batch size.
	 */
	public static function batch_size(): int {
		$size = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_batch_size', 50 );
		return max( 10, min( 200, $size ?: 50 ) );
	}

	/**
	 * Max retries for a failed chunk.
	 */
	public static function max_attempts(): int {
		$n = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_job_max_attempts', self::DEFAULT_MAX_ATTEMPTS );
		return max( 1, min( 10, $n ?: self::DEFAULT_MAX_ATTEMPTS ) );
	}

	/**
	 * Enqueue a bulk job.
	 *
	 * @param string $type    Job type.
	 * @param array  $payload Payload.
	 * @param array  $args    Optional: total, batch_id, max_attempts.
	 * @return string Job key.
	 */
	public static function enqueue( string $type, array $payload = array(), array $args = array() ): string {
		global $wpdb;

		$job_key = 'job_' . wp_generate_password( 12, false, false );
		$now     = current_time( 'mysql', true );

		$total = (int) ( $args['total'] ?? 0 );
		if ( ! $total && ! empty( $payload['product_ids'] ) && is_array( $payload['product_ids'] ) ) {
			$payload['product_ids'] = array_values( array_filter( array_map( 'absint', $payload['product_ids'] ) ) );
			$total = count( $payload['product_ids'] );
		}

		$batch_id = (string) ( $args['batch_id'] ?? ( $payload['batch_id'] ?? '' ) );
		if ( ! $batch_id && class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$batch_id = Shojaei_SEO_Revert_Log::new_batch_id();
		}

		$wpdb->insert(
			self::table(),
			array(
				'job_key'      => $job_key,
				'type'         => sanitize_key( $type ),
				'status'       => self::STATUS_PENDING,
				'total'        => $total,
				'offset_n'     => 0,
				'processed'    => 0,
				'failed'       => 0,
				'cursor_n'     => 0,
				'attempts'     => 0,
				'max_attempts' => (int) ( $args['max_attempts'] ?? self::max_attempts() ),
				'batch_id'     => $batch_id,
				'payload'      => wp_json_encode( $payload ),
				'message'      => '',
				'last_error'   => '',
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		self::ensure_tick_scheduled();
		self::schedule_next( $job_key, 0 );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'job_enqueue',
				sprintf(
					/* translators: 1: type, 2: total */
					__( 'جاب «%1$s» با %2$d آیتم در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
					$type,
					$total
				),
				0,
				array( 'job_key' => $job_key, 'type' => $type, 'total' => $total )
			);
		}

		/**
		 * Fires after a job is enqueued.
		 *
		 * @param string $job_key Job key.
		 * @param string $type    Type.
		 * @param array  $payload Payload.
		 */
		do_action( 'shojaei_seo_job_enqueued', $job_key, $type, $payload );

		return $job_key;
	}

	/**
	 * Whether a job of this type is pending/running.
	 *
	 * @param string $type Type.
	 */
	public static function has_active( string $type ): bool {
		global $wpdb;
		$table = self::table();
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE type = %s AND status IN (%s, %s)",
				$type,
				self::STATUS_PENDING,
				self::STATUS_RUNNING
			)
		);
		return $count > 0;
	}

	/**
	 * Get job by key as array (Batch-compatible shape).
	 *
	 * @param string $job_key Job key.
	 * @return array|null
	 */
	public static function get( string $job_key ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE job_key = %s',
				$job_key
			),
			ARRAY_A
		);
		return $row ? self::row_to_job( $row ) : null;
	}

	/**
	 * List recent jobs.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function list_jobs( int $limit = 10 ): array {
		global $wpdb;
		$limit = max( 1, min( 50, $limit ) );
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		if ( ! $rows ) {
			return array();
		}
		return array_map( array( __CLASS__, 'row_to_job' ), $rows );
	}

	/**
	 * Count active (pending/running) jobs.
	 */
	public static function count_active(): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status IN (%s, %s)",
				self::STATUS_PENDING,
				self::STATUS_RUNNING
			)
		);
	}

	/**
	 * Option key: admin acknowledged job errors up to this UTC datetime.
	 */
	public const ERRORS_ACK_OPTION = 'shojaei_seo_job_errors_acked_at';

	/**
	 * Count failed jobs since ack (default: last 7 days, excluding dismissed).
	 */
	public static function count_failed_unacked( int $within_seconds = WEEK_IN_SECONDS ): int {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - max( HOUR_IN_SECONDS, $within_seconds ) );
		$acked = (string) get_option( self::ERRORS_ACK_OPTION, '' );
		if ( $acked !== '' && $acked > $since ) {
			$since = $acked;
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND updated_at > %s",
				self::STATUS_FAILED,
				$since
			)
		);
	}

	/**
	 * List recent failed jobs (for settings UI).
	 *
	 * @param int $limit Limit.
	 * @return array<int,array>
	 */
	public static function list_failed( int $limit = 10 ): array {
		global $wpdb;
		$limit = max( 1, min( 50, $limit ) );
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND updated_at >= %s ORDER BY updated_at DESC LIMIT %d",
				self::STATUS_FAILED,
				$since,
				$limit
			),
			ARRAY_A
		);
		if ( ! $rows ) {
			return array();
		}
		return array_map( array( __CLASS__, 'row_to_job' ), $rows );
	}

	/**
	 * Acknowledge / clear dashboard warning for failed jobs (does not delete history unless $delete).
	 *
	 * @param bool $delete Also delete failed rows from the jobs table.
	 * @return array{acked_at:string,deleted:int}
	 */
	public static function acknowledge_failed_errors( bool $delete = false ): array {
		$acked = gmdate( 'Y-m-d H:i:s' );
		update_option( self::ERRORS_ACK_OPTION, $acked, false );
		$deleted = 0;
		if ( $delete ) {
			global $wpdb;
			$table   = self::table();
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table} WHERE status = %s",
					self::STATUS_FAILED
				)
			);
		}
		return array(
			'acked_at' => $acked,
			'deleted'  => $deleted,
		);
	}

	/**
	 * Cancel stale running jobs (lock older than reclaim window).
	 *
	 * @return int Cancelled count.
	 */
	public static function cancel_stale_running(): int {
		global $wpdb;
		$table = self::table();
		$cut   = gmdate( 'Y-m-d H:i:s', time() - ( defined( 'MINUTE_IN_SECONDS' ) ? 30 * MINUTE_IN_SECONDS : 1800 ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, message = %s, updated_at = %s
				WHERE status = %s AND updated_at < %s",
				self::STATUS_CANCELLED,
				__( 'لغو خودکار — جاب گیرکرده (قفل کهنه)', 'shojaei-seo-for-woo' ),
				gmdate( 'Y-m-d H:i:s' ),
				self::STATUS_RUNNING,
				$cut
			)
		);
	}

	/**
	 * Cron / AS entry: process one chunk of next or given job.
	 *
	 * @param string|null $job_key Optional job key.
	 * @return array Result summary.
	 */
	public static function run_next( ?string $job_key = null ): array {
		$job = $job_key ? self::claim( $job_key ) : self::claim_next();
		if ( ! $job ) {
			return array(
				'ok'      => true,
				'idle'    => true,
				'message' => __( 'جاب فعالی نیست.', 'shojaei-seo-for-woo' ),
			);
		}

		return self::process_claimed( $job );
	}

	/**
	 * WP-Cron tick.
	 */
	public function cron_tick(): void {
		self::run_next();
	}

	/**
	 * Action Scheduler callback.
	 *
	 * @param string $job_key Job key.
	 */
	public function as_process_chunk( $job_key ): void {
		self::run_next( (string) $job_key );
	}

	/**
	 * AJAX: drain one chunk while admin is present.
	 */
	public function ajax_tick(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$job_key = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? $_POST['job_key'] ?? '' ) );
		$result  = self::run_next( $job_key ?: null );

		if ( ! empty( $result['job'] ) ) {
			wp_send_json_success( $result['job'] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * REST: POST /wp-json/shojaei-seo/v1/jobs/tick
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'shojaei-seo/v1',
			'/jobs/tick',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_tick' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);

		register_rest_route(
			'shojaei-seo/v1',
			'/jobs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_list' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_tick( $request ) {
		$job_key = sanitize_text_field( (string) $request->get_param( 'job_key' ) );
		$result  = self::run_next( $job_key ?: null );
		return rest_ensure_response( $result );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_list( $request ) {
		$limit = (int) $request->get_param( 'limit' );
		return rest_ensure_response(
			array(
				'jobs'        => self::list_jobs( $limit ?: 10 ),
				'active'      => self::count_active(),
				'batch_size'  => self::batch_size(),
				'runner'      => self::runner_label(),
			)
		);
	}

	/**
	 * Human-readable primary runner.
	 */
	public static function runner_label(): string {
		if ( class_exists( 'Shojaei_SEO_Queue' ) && Shojaei_SEO_Queue::has_action_scheduler() ) {
			return 'action_scheduler';
		}
		return 'internal_cron_ajax';
	}

	/**
	 * Schedule next chunk (AS preferred, else cron + optional spawn).
	 *
	 * @param string $job_key Job key.
	 * @param int    $delay_s Delay seconds.
	 */
	public static function schedule_next( string $job_key, int $delay_s = 0 ): void {
		self::ensure_tick_scheduled();

		if ( class_exists( 'Shojaei_SEO_Queue' ) && Shojaei_SEO_Queue::has_action_scheduler() ) {
			as_schedule_single_action(
				time() + max( 0, $delay_s ),
				self::HOOK_AS_CHUNK,
				array( $job_key ),
				'shojaei-seo'
			);
		} elseif ( ! wp_next_scheduled( self::HOOK_TICK ) ) {
			wp_schedule_single_event( time() + max( 1, $delay_s ), self::HOOK_TICK );
		}

		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) && function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Migrate legacy option-stored batch jobs into the DB table once.
	 */
	public static function migrate_from_options(): void {
		$legacy = get_option( 'shojaei_seo_batch_jobs', null );
		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			return;
		}

		global $wpdb;
		$table = self::table();

		foreach ( $legacy as $job ) {
			if ( empty( $job['id'] ) ) {
				continue;
			}
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE job_key = %s", $job['id'] )
			);
			if ( $exists ) {
				continue;
			}

			$status = (string) ( $job['status'] ?? 'pending' );
			if ( 'queued' === $status ) {
				$status = self::STATUS_PENDING;
			}

			$created = ! empty( $job['created_at'] )
				? gmdate( 'Y-m-d H:i:s', (int) $job['created_at'] )
				: current_time( 'mysql', true );
			$updated = ! empty( $job['updated_at'] )
				? gmdate( 'Y-m-d H:i:s', (int) $job['updated_at'] )
				: $created;

			$wpdb->insert(
				$table,
				array(
					'job_key'      => (string) $job['id'],
					'type'         => sanitize_key( (string) ( $job['type'] ?? 'unknown' ) ),
					'status'       => $status,
					'total'        => (int) ( $job['total'] ?? 0 ),
					'offset_n'     => (int) ( $job['offset'] ?? 0 ),
					'processed'    => (int) ( $job['processed'] ?? 0 ),
					'failed'       => (int) ( $job['failed'] ?? 0 ),
					'cursor_n'     => (int) ( $job['cursor'] ?? 0 ),
					'attempts'     => 0,
					'max_attempts' => self::max_attempts(),
					'batch_id'     => (string) ( $job['batch_id'] ?? '' ),
					'payload'      => wp_json_encode( $job['payload'] ?? array() ),
					'message'      => (string) ( $job['message'] ?? '' ),
					'last_error'   => '',
					'created_at'   => $created,
					'updated_at'   => $updated,
				)
			);
		}

		delete_option( 'shojaei_seo_batch_jobs' );
	}

	/**
	 * Prune finished jobs older than a week; cap table size.
	 */
	public static function prune(): void {
		global $wpdb;
		$table = self::table();
		$cut   = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status IN (%s, %s, %s) AND updated_at < %s",
				self::STATUS_DONE,
				self::STATUS_FAILED,
				self::STATUS_CANCELLED,
				$cut
			)
		);

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 100 ) {
			$keep_from = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT updated_at FROM {$table} ORDER BY updated_at DESC LIMIT 1 OFFSET %d",
					99
				)
			);
			if ( $keep_from ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$table} WHERE updated_at < %s AND status IN (%s, %s, %s)",
						$keep_from,
						self::STATUS_DONE,
						self::STATUS_FAILED,
						self::STATUS_CANCELLED
					)
				);
			}
		}
	}

	/**
	 * Claim a specific job for processing.
	 *
	 * @param string $job_key Job key.
	 * @return array|null
	 */
	private static function claim( string $job_key ): ?array {
		$job = self::get( $job_key );
		if ( ! $job ) {
			return null;
		}
		if ( in_array( $job['status'], array( self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELLED ), true ) ) {
			return null;
		}

		// Stale lock reclaim.
		if ( self::STATUS_RUNNING === $job['status'] && ! self::is_lock_stale( $job ) ) {
			return null;
		}

		return self::lock_job( $job_key ) ? self::get( $job_key ) : null;
	}

	/**
	 * Claim oldest pending (or stale running) job.
	 *
	 * @return array|null
	 */
	private static function claim_next(): ?array {
		global $wpdb;
		$table = self::table();
		$stale = gmdate( 'Y-m-d H:i:s', time() - self::LOCK_STALE_SECONDS );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = %s
				   OR (status = %s AND (locked_at IS NULL OR locked_at < %s))
				ORDER BY created_at ASC
				LIMIT 1",
				self::STATUS_PENDING,
				self::STATUS_RUNNING,
				$stale
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$job_key = (string) $row['job_key'];
		return self::lock_job( $job_key ) ? self::get( $job_key ) : null;
	}

	/**
	 * @param array $job Job.
	 */
	private static function is_lock_stale( array $job ): bool {
		$locked = (int) ( $job['locked_at'] ?? 0 );
		if ( ! $locked ) {
			return true;
		}
		return ( time() - $locked ) >= self::LOCK_STALE_SECONDS;
	}

	/**
	 * Atomically mark job running + bump attempt.
	 *
	 * @param string $job_key Job key.
	 */
	private static function lock_job( string $job_key ): bool {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql', true );
		$stale = gmdate( 'Y-m-d H:i:s', time() - self::LOCK_STALE_SECONDS );

		// Bump attempts only when reclaiming a stale running lock (not every successful chunk).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET status = %s,
					locked_at = %s,
					updated_at = %s,
					attempts = CASE
						WHEN status = %s AND (locked_at IS NULL OR locked_at < %s) THEN attempts + 1
						ELSE attempts
					END
				WHERE job_key = %s
				  AND (
					status = %s
					OR (status = %s AND (locked_at IS NULL OR locked_at < %s))
				  )",
				self::STATUS_RUNNING,
				$now,
				$now,
				self::STATUS_RUNNING,
				$stale,
				$job_key,
				self::STATUS_PENDING,
				self::STATUS_RUNNING,
				$stale
			)
		);

		return false !== $updated && (int) $updated > 0;
	}

	/**
	 * Run chunk handlers for a claimed job.
	 *
	 * @param array $job Job array.
	 * @return array
	 */
	private static function process_claimed( array $job ): array {
		$job_key = (string) $job['id'];

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::begin_map_cache();
		}

		$size   = self::batch_size();
		$result = array(
			'processed' => 0,
			'failed'    => 0,
			'done'      => false,
			'message'   => '',
		);

		try {
			if ( ! class_exists( 'Shojaei_SEO_Batch' ) ) {
				throw new RuntimeException( 'Batch runner missing.' );
			}
			$result = Shojaei_SEO_Batch::execute_chunk( $job, $size );
		} catch ( Throwable $e ) {
			$error = $e->getMessage();
			self::bump_attempts( $job_key );
			self::record_error( $job_key, $error );

			$fresh = self::get( $job_key );
			if ( $fresh && (int) $fresh['attempts'] >= (int) $fresh['max_attempts'] ) {
				self::fail( $job_key, $error );
			} else {
				// Release back to pending for retry.
				self::release_for_retry( $job_key, $error );
				self::schedule_next( $job_key, 15 );
			}

			if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
				Shojaei_SEO_Redirect_Engine::end_map_cache();
			}

			return array(
				'ok'    => false,
				'job'   => self::get( $job_key ),
				'error' => $error,
			);
		}

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::end_map_cache();
		}

		$fresh = self::get( $job_key );
		if ( ! $fresh ) {
			return array( 'ok' => false, 'done' => true );
		}

		$processed = (int) $fresh['processed'] + (int) ( $result['processed'] ?? 0 );
		$failed    = (int) $fresh['failed'] + (int) ( $result['failed'] ?? 0 );
		$offset    = (int) ( $result['offset'] ?? ( (int) $fresh['offset'] + (int) ( $result['processed'] ?? 0 ) ) );
		$cursor    = (int) ( $result['cursor'] ?? $fresh['cursor'] );
		$message   = (string) ( $result['message'] ?? $fresh['message'] );
		$done      = ! empty( $result['done'] );

		if ( ! empty( $result['status_failed'] ) ) {
			self::fail( $job_key, $message ?: __( 'جاب ناموفق بود.', 'shojaei-seo-for-woo' ) );
			self::prune();
			return array(
				'ok'   => false,
				'job'  => self::get( $job_key ),
				'done' => true,
			);
		}

		if ( $done ) {
			self::complete(
				$job_key,
				array(
					'processed' => $processed,
					'failed'    => $failed,
					'offset'    => $offset,
					'cursor'    => $cursor,
					'message'   => $message ?: sprintf(
						/* translators: 1: processed, 2: total */
						__( 'تکمیل شد: %1$d از %2$d', 'shojaei-seo-for-woo' ),
						$processed,
						(int) $fresh['total']
					),
				)
			);
			$done_job = self::get( $job_key );
			if ( $done_job ) {
				Shojaei_SEO_Batch::notify_job_done( $done_job );
			}
			self::prune();
			return array(
				'ok'   => true,
				'job'  => $done_job,
				'done' => true,
			);
		}

		self::update(
			$job_key,
			array(
				'status'    => self::STATUS_PENDING,
				'processed' => $processed,
				'failed'    => $failed,
				'offset'    => $offset,
				'cursor'    => $cursor,
				'message'   => $message,
				'locked_at' => null,
			)
		);

		self::schedule_next( $job_key, 5 );

		return array(
			'ok'   => true,
			'job'  => self::get( $job_key ),
			'done' => false,
		);
	}

	/**
	 * @param string $job_key Job key.
	 * @param array  $fields  Fields (API shape).
	 */
	public static function update( string $job_key, array $fields ): void {
		global $wpdb;
		$map = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$fmt = array( '%s' );

		$allowed = array(
			'status'     => '%s',
			'total'      => '%d',
			'processed'  => '%d',
			'failed'     => '%d',
			'message'    => '%s',
			'last_error' => '%s',
			'batch_id'   => '%s',
		);

		foreach ( $allowed as $key => $format ) {
			if ( array_key_exists( $key, $fields ) ) {
				$map[ $key ] = $fields[ $key ];
				$fmt[]       = $format;
			}
		}
		if ( array_key_exists( 'offset', $fields ) ) {
			$map['offset_n'] = (int) $fields['offset'];
			$fmt[]           = '%d';
		}
		if ( array_key_exists( 'cursor', $fields ) ) {
			$map['cursor_n'] = (int) $fields['cursor'];
			$fmt[]           = '%d';
		}
		$clear_lock = array_key_exists( 'locked_at', $fields ) && null === $fields['locked_at'];
		if ( array_key_exists( 'locked_at', $fields ) && ! $clear_lock ) {
			$map['locked_at'] = $fields['locked_at'];
			$fmt[]            = '%s';
		}

		$wpdb->update( self::table(), $map, array( 'job_key' => $job_key ), $fmt, array( '%s' ) );

		if ( $clear_lock ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . self::table() . ' SET locked_at = NULL WHERE job_key = %s',
					$job_key
				)
			);
		}
	}

	/**
	 * @param string $job_key Job key.
	 * @param array  $fields  Progress fields.
	 */
	private static function complete( string $job_key, array $fields ): void {
		$fields['status']    = self::STATUS_DONE;
		$fields['locked_at'] = null;
		self::update( $job_key, $fields );
		do_action( 'shojaei_seo_job_done', $job_key, self::get( $job_key ) );
	}

	/**
	 * @param string $job_key Job key.
	 * @param string $error   Error.
	 */
	private static function fail( string $job_key, string $error ): void {
		self::update(
			$job_key,
			array(
				'status'     => self::STATUS_FAILED,
				'last_error' => $error,
				'message'    => $error,
				'locked_at'  => null,
			)
		);
		do_action( 'shojaei_seo_job_failed', $job_key, $error );
	}

	/**
	 * Increment attempts counter after a thrown failure.
	 *
	 * @param string $job_key Job key.
	 */
	private static function bump_attempts( string $job_key ): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET attempts = attempts + 1, updated_at = %s WHERE job_key = %s",
				current_time( 'mysql', true ),
				$job_key
			)
		);
	}

	/**
	 * @param string $job_key Job key.
	 * @param string $error   Error.
	 */
	private static function record_error( string $job_key, string $error ): void {
		self::update(
			$job_key,
			array(
				'last_error' => $error,
				'message'    => $error,
			)
		);
	}

	/**
	 * @param string $job_key Job key.
	 * @param string $error   Error.
	 */
	private static function release_for_retry( string $job_key, string $error ): void {
		self::update(
			$job_key,
			array(
				'status'     => self::STATUS_PENDING,
				'last_error' => $error,
				'message'    => sprintf(
					/* translators: %s: error */
					__( 'خطا — تلاش مجدد: %s', 'shojaei-seo-for-woo' ),
					$error
				),
				'locked_at'  => null,
			)
		);
	}

	/**
	 * Map DB row to Batch-compatible job array.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private static function row_to_job( array $row ): array {
		$payload = array();
		if ( ! empty( $row['payload'] ) ) {
			$decoded = json_decode( (string) $row['payload'], true );
			$payload = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'id'           => (string) $row['job_key'],
			'job_key'      => (string) $row['job_key'],
			'type'         => (string) $row['type'],
			'status'       => (string) $row['status'],
			'total'        => (int) $row['total'],
			'offset'       => (int) $row['offset_n'],
			'processed'    => (int) $row['processed'],
			'failed'       => (int) $row['failed'],
			'cursor'       => (int) $row['cursor_n'],
			'attempts'     => (int) $row['attempts'],
			'max_attempts' => (int) $row['max_attempts'],
			'batch_id'     => (string) $row['batch_id'],
			'payload'      => $payload,
			'message'      => (string) ( $row['message'] ?? '' ),
			'last_error'   => (string) ( $row['last_error'] ?? '' ),
			'locked_at'    => ! empty( $row['locked_at'] ) ? strtotime( $row['locked_at'] . ' UTC' ) : 0,
			'created_at'   => ! empty( $row['created_at'] ) ? strtotime( $row['created_at'] . ' UTC' ) : 0,
			'updated_at'   => ! empty( $row['updated_at'] ) ? strtotime( $row['updated_at'] . ' UTC' ) : 0,
			'runner'       => self::runner_label(),
		);
	}
}
