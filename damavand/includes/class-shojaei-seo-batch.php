<?php
/**
 * Batch job chunk runners — storage/scheduling via Shojaei_SEO_Jobs.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Batch
 */
class Shojaei_SEO_Batch {

	/** @deprecated Use Shojaei_SEO_Jobs::HOOK_TICK */
	public const OPTION_JOBS   = 'shojaei_seo_batch_jobs';
	public const HOOK_TICK     = 'shojaei_seo_batch_tick';
	public const HOOK_AS_CHUNK = 'shojaei_seo_as_batch_chunk';
	public const CRON_SCHEDULE = 'shojaei_seo_every_minute';

	/**
	 * Constructor — thin bridge; Jobs owns the runner.
	 */
	public function __construct() {
		// Legacy AS hook still drains through Jobs when old actions remain in queue.
		add_action( self::HOOK_AS_CHUNK, array( $this, 'as_process_chunk' ), 10, 1 );
		add_filter( 'cron_schedules', array( 'Shojaei_SEO_Jobs', 'register_schedules' ) );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function register_schedules( array $schedules ): array {
		return Shojaei_SEO_Jobs::register_schedules( $schedules );
	}

	/**
	 * Configurable batch size (default 50).
	 */
	public static function batch_size(): int {
		return Shojaei_SEO_Jobs::batch_size();
	}

	/**
	 * Ensure WP-Cron tick is scheduled.
	 */
	public static function ensure_tick_scheduled(): void {
		Shojaei_SEO_Jobs::ensure_tick_scheduled();
	}

	/**
	 * Clear batch cron on deactivate.
	 */
	public static function clear_scheduled(): void {
		wp_clear_scheduled_hook( self::HOOK_TICK );
		Shojaei_SEO_Jobs::clear_scheduled();
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_AS_CHUNK, null, 'shojaei-seo' );
		}
	}

	/**
	 * Create and enqueue a background job.
	 *
	 * @param string $type    Job type: bulk_redirect|dry_run_redirect|daily_oos|rebuild_links|rebuild_schema.
	 * @param array  $payload Job payload.
	 * @return string Job ID.
	 */
	public static function enqueue( string $type, array $payload = array() ): string {
		$args = array();
		if ( 'daily_oos' === $type && empty( $payload['product_ids'] ) ) {
			$args['total'] = self::count_daily_oos_rows();
		}
		if ( 'oos_days_backfill' === $type && class_exists( 'Shojaei_SEO_Helpers' ) ) {
			$args['total'] = Shojaei_SEO_Helpers::count_oos_date_backfill();
		}
		return Shojaei_SEO_Jobs::enqueue( $type, $payload, $args );
	}

	/**
	 * Whether a job of this type is already running/pending.
	 *
	 * @param string $type Job type.
	 */
	public static function has_active_job( string $type ): bool {
		return Shojaei_SEO_Jobs::has_active( $type );
	}

	/**
	 * Get one job by ID.
	 *
	 * @param string $job_id Job ID.
	 * @return array|null
	 */
	public static function get_job( string $job_id ): ?array {
		return Shojaei_SEO_Jobs::get( $job_id );
	}

	/**
	 * List recent / active jobs for admin UI.
	 *
	 * @param int $limit Max jobs.
	 * @return array
	 */
	public static function list_jobs( int $limit = 10 ): array {
		return Shojaei_SEO_Jobs::list_jobs( $limit );
	}

	/**
	 * WP-Cron: process one chunk (legacy entry).
	 */
	public function process_tick(): void {
		Shojaei_SEO_Jobs::run_next();
	}

	/**
	 * Action Scheduler callback (legacy hook name).
	 *
	 * @param string $job_id Job ID.
	 */
	public function as_process_chunk( $job_id ): void {
		Shojaei_SEO_Jobs::run_next( (string) $job_id );
	}

	/**
	 * Process up to batch_size items for a job (legacy public API).
	 *
	 * @param string $job_id Job ID.
	 */
	public static function process_job_chunk( string $job_id ): void {
		Shojaei_SEO_Jobs::run_next( $job_id );
	}

	/**
	 * Execute one chunk for a claimed job (called by Jobs runner).
	 *
	 * @param array $job  Job array.
	 * @param int   $size Batch size.
	 * @return array{processed:int,failed:int,done:bool,message?:string,offset?:int,cursor?:int,status_failed?:bool}
	 */
	public static function execute_chunk( array $job, int $size ): array {
		switch ( $job['type'] ?? '' ) {
			case 'bulk_redirect':
				return self::run_bulk_redirect_chunk( $job, $size );
			case 'dry_run_redirect':
				return self::run_dry_run_chunk( $job, $size );
			case 'daily_oos':
				return self::run_daily_oos_chunk( $job, $size );
			case 'rebuild_links':
				return self::run_rebuild_links_chunk( $job, $size );
			case 'rebuild_schema':
				return self::run_rebuild_schema_chunk( $job, $size );
			case 'initial_scan':
				return self::run_initial_scan_chunk( $job, $size );
			case 'slug_health_scan':
				return self::run_slug_health_scan_chunk( $job, $size );
			case 'link_inventory_crawl':
				return self::run_link_inventory_crawl_chunk( $job, $size );
			case 'link_watchdog_scan':
				return self::run_link_watchdog_scan_chunk( $job, $size );
			case 'seo_pulse_scan':
				return self::run_seo_pulse_scan_chunk( $job, $size );
			case 'damavand_link_calc':
				return self::run_damavand_link_calc_chunk( $job, $size );
			case 'oos_days_backfill':
				return self::run_oos_days_backfill_chunk( $job, 100 );
			case 'ai_alt_batch':
				return class_exists( 'Shojaei_SEO_AI_Engine' )
					? Shojaei_SEO_AI_Engine::execute_alt_chunk( $job, $size )
					: array(
						'processed'     => 0,
						'failed'        => 1,
						'done'          => true,
						'status_failed' => true,
						'message'       => __( 'موتور تولید محتوا در دسترس نیست.', 'shojaei-seo-for-woo' ),
					);
			case 'ollama_generate':
				return array(
					'processed'     => 0,
					'failed'        => 0,
					'done'          => true,
					'status_failed' => true,
					'message'       => __( 'موتور Ollama حذف شده. افزونه را به‌روز کنید و از Groq/OpenRouter استفاده کنید.', 'shojaei-seo-for-woo' ),
				);
			default:
				return array(
					'processed'     => 0,
					'failed'        => 0,
					'done'          => true,
					'status_failed' => true,
					'message'       => __( 'نوع جاب نامعتبر است.', 'shojaei-seo-for-woo' ),
				);
		}
	}

	/**
	 * Notify admin when a user-facing job completes.
	 *
	 * @param array $job Job.
	 */
	public static function notify_job_done( array $job ): void {
		$type = $job['type'] ?? '';
		$labels = array(
			'bulk_redirect'    => __( 'عملیات گروهی ریدایرکت', 'shojaei-seo-for-woo' ),
			'dry_run_redirect' => __( 'شبیه‌سازی ریدایرکت', 'shojaei-seo-for-woo' ),
			'rebuild_links'    => __( 'بازسازی لینک‌های داخلی', 'shojaei-seo-for-woo' ),
			'rebuild_schema'   => __( 'بازتولید داده ساختاریافته', 'shojaei-seo-for-woo' ),
			'initial_scan'         => __( 'اسکن اولیه موجودی', 'shojaei-seo-for-woo' ),
			'slug_health_scan'     => __( 'اسکن کامل سلامت نامک', 'shojaei-seo-for-woo' ),
			'link_inventory_crawl' => __( 'اسکن موجودی لینک', 'shojaei-seo-for-woo' ),
			'link_watchdog_scan'   => __( 'نگهبان لینک داخلی', 'shojaei-seo-for-woo' ),
			'seo_pulse_scan'       => __( 'اسکن نبض سئو', 'shojaei-seo-for-woo' ),
			'damavand_link_calc'   => __( 'محاسبه لینک هوشمند دماوند', 'shojaei-seo-for-woo' ),
			'oos_days_backfill'    => __( 'اسکن روز ناموجودی', 'shojaei-seo-for-woo' ),
			'ai_alt_batch'         => __( 'تولید Alt تصاویر', 'shojaei-seo-for-woo' ),
		);
		if ( ! isset( $labels[ $type ] ) ) {
			return;
		}

		$link = admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-performance' );
		if ( 'slug_health_scan' === $type ) {
			$link = admin_url( 'admin.php?page=shojaei-seo&tab=slugs&section=health' );
		} elseif ( 'seo_pulse_scan' === $type ) {
			$link = admin_url( 'admin.php?page=shojaei-seo&tab=seo-pulse' );
		} elseif ( 'link_inventory_crawl' === $type ) {
			$link = admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory' );
		} elseif ( 'link_watchdog_scan' === $type ) {
			$link = admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory&watchdog=1' );
		} elseif ( 'damavand_link_calc' === $type ) {
			$link = admin_url( 'admin.php?page=shojaei-seo&tab=seo-core' );
		} elseif ( 'oos_days_backfill' === $type ) {
			$link = admin_url( 'admin.php?page=shojaei-seo&tab=oos' );
			update_option( 'shojaei_seo_oos_days_refreshed', '1.39.6', false );
		}

		Shojaei_SEO_Notifications::add(
			'batch_done',
			sprintf(
				/* translators: 1: label, 2: processed, 3: total */
				__( '%1$s تمام شد (%2$d / %3$d).', 'shojaei-seo-for-woo' ),
				$labels[ $type ],
				(int) ( $job['processed'] ?? 0 ),
				(int) ( $job['total'] ?? 0 )
			),
			0,
			$link
		);
	}

	/**
	 * Schedule immediate/next chunk processing.
	 *
	 * @param string $job_id  Job ID.
	 * @param int    $delay_s Delay seconds.
	 */
	public static function schedule_next_chunk( string $job_id, int $delay_s = 0 ): void {
		Shojaei_SEO_Jobs::schedule_next( $job_id, $delay_s );
	}

	/**
	 * Start daily OOS lifecycle as a batched job (called from daily cron).
	 */
	public static function start_daily_oos_job(): ?string {
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'oos' ) ) {
			return null;
		}
		if ( self::has_active_job( 'daily_oos' ) ) {
			return null;
		}
		return self::enqueue( 'daily_oos', array() );
	}

	/**
	 * Count non-redirected OOS rows.
	 */
	private static function count_daily_oos_rows(): int {
		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status != 'redirected'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Chunk: bulk redirect / keep.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_bulk_redirect_chunk( array $job, int $size ): array {
		$ids     = $job['payload']['product_ids'] ?? array();
		$offset  = (int) ( $job['offset'] ?? 0 );
		$slice   = array_slice( $ids, $offset, $size );
		$action  = (string) ( $job['payload']['action'] ?? '' );
		$target  = $job['payload']['target_url'] ?? null;
		$force   = ! empty( $job['payload']['force_confirm'] );
		$batch   = (string) ( $job['batch_id'] ?? '' );

		$manager   = new Shojaei_SEO_OOS_Manager( false );
		$processed = $manager->bulk_action( $slice, $action, $target ?: null, $force, $batch ?: null );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		return array(
			'processed' => $processed,
			'failed'    => max( 0, count( $slice ) - $processed ),
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? sprintf(
					/* translators: %d: count */
					__( 'عملیات گروهی تکمیل شد (%d محصول).', 'shojaei-seo-for-woo' ),
					(int) ( $job['processed'] ?? 0 ) + $processed
				)
				: '',
		);
	}

	/**
	 * Chunk: dry-run redirect simulation.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_dry_run_chunk( array $job, int $size ): array {
		$ids    = $job['payload']['product_ids'] ?? array();
		$offset = (int) ( $job['offset'] ?? 0 );
		$slice  = array_slice( $ids, $offset, $size );
		$action = (string) ( $job['payload']['action'] ?? '' );
		$target = $job['payload']['target_url'] ?? null;
		$batch  = (string) ( $job['batch_id'] ?? '' );

		$result = Shojaei_SEO_Revert_Log::dry_run_bulk_redirect( $slice, $action, $target ?: null, $batch ?: null );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );
		$ok         = count( $result['changes'] ?? array() );
		$blocked    = count( $result['blocked'] ?? array() );

		return array(
			'processed' => $ok,
			'failed'    => $blocked,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? sprintf(
					/* translators: 1: ok, 2: blocked */
					__( 'شبیه‌سازی تکمیل شد — %1$d تغییر، %2$d مسدود.', 'shojaei-seo-for-woo' ),
					(int) ( $job['processed'] ?? 0 ) + $ok,
					(int) ( $job['failed'] ?? 0 ) + $blocked
				)
				: '',
		);
	}

	/**
	 * Historical OOS-start from last paid sale — 100 rows per tick, lookup table only.
	 *
	 * @param array $job  Job.
	 * @param int   $size Chunk size.
	 * @return array
	 */
	private static function run_oos_days_backfill_chunk( array $job, int $size ): array {
		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$pm     = $wpdb->postmeta;
		$cursor = (int) ( $job['cursor'] ?? 0 );
		$size   = max( 20, min( 100, $size ) );
		$now    = current_time( 'mysql' );

		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.id, t.product_id, t.oos_date, t.days_oos, t.status
				FROM {$table} t
				WHERE t.status != 'redirected' AND t.id > %d
				AND NOT EXISTS (
					SELECT 1 FROM {$pm} obs
					WHERE obs.post_id = t.product_id AND obs.meta_key = %s AND obs.meta_value = %s
				)
				AND (
					t.days_oos > 2000
					OR t.oos_date < %s
					OR TIMESTAMPDIFF(DAY, t.oos_date, %s) < 2
				)
				ORDER BY t.id ASC
				LIMIT %d",
				$cursor,
				'_shojaei_seo_oos_observed',
				'1',
				'2000-01-01 00:00:00',
				$now,
				$size
			)
		);

		if ( empty( $records ) ) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => true,
				'message'   => __( 'اسکن روز ناموجودی تمام شد. صفحه را تازه کنید.', 'shojaei-seo-for-woo' ),
			);
		}

		$processed = 0;
		$last_id   = $cursor;
		foreach ( $records as $record ) {
			$last_id = (int) $record->id;
			$pid     = (int) $record->product_id;
			$oos     = (string) $record->oos_date;
			if ( class_exists( 'Shojaei_SEO_OOS_Manager' ) ) {
				$guess = Shojaei_SEO_OOS_Manager::estimate_oos_started_at( $pid, true );
				$gts   = (int) strtotime( $guess );
				$ots   = (int) strtotime( $oos );
				if ( $gts && ( ! $ots || $gts < $ots || ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( $oos ) ) ) {
					$oos = $guess;
				}
			}
			$days   = Shojaei_SEO_Helpers::days_since_oos( $oos );
			$status = (string) $record->status;
			if ( ! in_array( $status, array( 'needs_manual', 'redirected' ), true ) ) {
				$status = Shojaei_SEO_Helpers::get_oos_state( $days )['status'];
			}
			$wpdb->update(
				$table,
				array(
					'oos_date' => $oos,
					'days_oos' => $days,
					'status'   => $status,
				),
				array( 'product_id' => $pid ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			Shojaei_SEO_Helpers::sync_oos_postmeta( $pid, $oos, $days );
			if ( $days < 2 ) {
				update_post_meta( $pid, '_shojaei_seo_oos_probed', '1' );
			}
			++$processed;
		}

		return array(
			'processed' => $processed,
			'failed'    => 0,
			'cursor'    => $last_id,
			'done'      => false,
			'message'   => '',
		);
	}

	/**
	 * Chunk: daily OOS lifecycle update (cursor by row id).
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_daily_oos_chunk( array $job, int $size ): array {
		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$cursor = (int) ( $job['cursor'] ?? 0 );

		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, oos_date, days_oos, status FROM {$table}
				WHERE status != 'redirected' AND id > %d
				ORDER BY id ASC
				LIMIT %d",
				$cursor,
				$size
			)
		);

		if ( empty( $records ) ) {
			return array(
				'processed' => 0,
				'failed'    => 0,
				'cursor'    => $cursor,
				'done'      => true,
				'message'   => __( 'بررسی روزانه ناموجودی تکمیل شد.', 'shojaei-seo-for-woo' ),
			);
		}

		$processed = 0;
		$last_id   = $cursor;

		foreach ( $records as $record ) {
			$last_id   = (int) $record->id;
			$pid       = (int) $record->product_id;
			$prev_days = (int) $record->days_oos;
			$days      = Shojaei_SEO_Helpers::days_since_oos( (string) $record->oos_date );
			if ( ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( (string) $record->oos_date ) || $days > 2000 || $prev_days > 2000 ) {
				$fixed = class_exists( 'Shojaei_SEO_OOS_Manager' )
					? Shojaei_SEO_OOS_Manager::estimate_oos_started_at( (int) $record->product_id, false )
					: Shojaei_SEO_Helpers::mysql_datetime();
				$record->oos_date = $fixed;
				$days             = Shojaei_SEO_Helpers::days_since_oos( $fixed );
			}

			Shojaei_SEO_Helpers::sync_oos_postmeta( $pid, (string) $record->oos_date, $days );

			$state = Shojaei_SEO_Helpers::get_oos_state( $days );
			$phase = (int) ( $state['phase'] ?? 0 );

			// Event-driven: daily job reconciles days; full Rule Engine only on boundary cross or when disabled.
			$event_driven = class_exists( 'Shojaei_SEO_Events' ) && Shojaei_SEO_Events::is_enabled();
			$crossed      = self::crossed_lifecycle_boundary( $prev_days, $days );
			$needs_full   = ! $event_driven || $crossed;

			$decision = null;
			if ( $needs_full && class_exists( 'Shojaei_SEO_Rule_Engine' ) ) {
				$decision = Shojaei_SEO_Rule_Engine::evaluate_product( $pid );
				if ( $decision ) {
					Shojaei_SEO_Rule_Engine::sync_decision_meta( $pid, $decision );
				}
			} elseif ( $event_driven ) {
				$before_flags = class_exists( 'Shojaei_SEO_Revert_Log' )
					? Shojaei_SEO_Revert_Log::snapshot_seo_flags( $pid )
					: array();
				$noindex_on   = $phase >= (int) Shojaei_SEO_Helpers::get_noindex_from_phase();
				update_post_meta( $pid, '_shojaei_seo_noindex', $noindex_on ? 'yes' : 'no' );
				update_post_meta( $pid, '_shojaei_seo_link_deprioritized', $phase >= 3 ? 'yes' : 'no' );
				update_post_meta( $pid, '_shojaei_seo_sitemap_exclude', $phase >= 3 ? 'yes' : 'no' );
				if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
					$flag_batch = (string) ( $job['batch_id'] ?? '' );
					Shojaei_SEO_Revert_Log::record_flag_diffs(
						$pid,
						$before_flags,
						Shojaei_SEO_Revert_Log::snapshot_seo_flags( $pid ),
						$flag_batch !== '' ? $flag_batch : null
					);
				}
			}

			$new_status = $record->status;
			if ( 'needs_manual' !== $record->status ) {
				if ( $decision && $decision->suggested_status ) {
					$new_status = $decision->suggested_status;
				} else {
					$new_status = $state['status'];
				}
			}

			$wpdb->update(
				$table,
				array(
					'oos_date' => (string) $record->oos_date,
					'days_oos' => $days,
					'status'   => $new_status,
				),
				array( 'id' => $record->id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			if (
				'candidate_redirect' === $new_status
				&& 'candidate_redirect' !== $record->status
				&& 'needs_manual' !== $record->status
			) {
				$title = get_the_title( $pid );
				Shojaei_SEO_Notifications::add(
					'candidate_redirect',
					sprintf(
						/* translators: 1: product title, 2: days */
						__( 'محصول «%1$s» پس از %2$d روز ناموجودی، نیاز به تصمیم‌گیری دارد.', 'shojaei-seo-for-woo' ),
						$title,
						$days
					),
					$pid
				);
			}

			$should_enqueue = false;
			if ( $decision ) {
				$should_enqueue = (bool) $decision->enqueue_auto_redirect;
			} elseif ( ! $event_driven ) {
				$should_enqueue = (
					4 === $phase
					&& 'needs_manual' !== $record->status
					&& 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect', 'yes' )
				);
			} elseif ( $crossed && 4 === $phase && 'needs_manual' !== $record->status
				&& 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect', 'yes' ) ) {
				$should_enqueue = true;
			}

			if ( $should_enqueue && 'needs_manual' !== $record->status ) {
				Shojaei_SEO_Queue::enqueue_oos_process( $pid );
			}

			$processed++;
		}

		$done = count( $records ) < $size;

		return array(
			'processed' => $processed,
			'failed'    => 0,
			'cursor'    => $last_id,
			'offset'    => (int) ( $job['offset'] ?? 0 ) + $processed,
			'done'      => $done,
			'message'   => $done ? __( 'بررسی روزانه ناموجودی تکمیل شد.', 'shojaei-seo-for-woo' ) : '',
		);
	}

	/**
	 * Chunk: rebuild internal links cache for posts/products.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_rebuild_links_chunk( array $job, int $size ): array {
		// Principle: bulk link rebuild without Undo path must not auto-apply.
		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) && ! Shojaei_SEO_Revert_Log::is_undoable( 'link_build' ) ) {
			return array(
				'processed'     => 0,
				'failed'        => 0,
				'done'          => true,
				'status_failed' => true,
				'message'       => __( 'بازسازی لینک بدون Undo واقعی مجاز نیست.', 'shojaei-seo-for-woo' ),
			);
		}

		$batch_id = (string) ( $job['batch_id'] ?? '' );
		$explicit = $job['payload']['post_ids'] ?? ( $job['payload']['product_ids'] ?? array() );
		if ( ! empty( $explicit ) && is_array( $explicit ) ) {
			$ids        = array_values( array_filter( array_map( 'absint', $explicit ) ) );
			$offset     = (int) ( $job['offset'] ?? 0 );
			$slice      = array_slice( $ids, $offset, $size );
			$new_offset = $offset + count( $slice );
			$done       = $new_offset >= count( $ids );
			$cursor     = (int) ( $job['cursor'] ?? 0 );
		} else {
			$cursor = (int) ( $job['cursor'] ?? 0 );
			global $wpdb;
			$slice = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type IN ('product','post') AND post_status = 'publish' AND ID > %d
					ORDER BY ID ASC LIMIT %d",
					$cursor,
					$size
				)
			);
			$slice      = array_map( 'absint', $slice ?: array() );
			$new_offset = (int) ( $job['offset'] ?? 0 ) + count( $slice );
			$done       = count( $slice ) < $size;
			$cursor     = ! empty( $slice ) ? (int) end( $slice ) : $cursor;
		}

		$builder = new Shojaei_SEO_Link_Builder( false );
		$ok      = 0;
		$fail    = 0;

		foreach ( $slice as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$fail++;
				continue;
			}
			$cache_key = 'shojaei_seo_linked_' . $post_id;
			$prev      = get_transient( $cache_key );
			$result    = $builder->build_links( (string) $post->post_content, true, (int) $post_id );
			set_transient( $cache_key, $result['content'], DAY_IN_SECONDS );

			if ( class_exists( 'Shojaei_SEO_Revert_Log' ) && $result['links_added'] > 0 ) {
				Shojaei_SEO_Revert_Log::record(
					array(
						'batch_id'    => $batch_id ?: Shojaei_SEO_Revert_Log::new_batch_id(),
						'mode'        => 'applied',
						'action'      => 'link_build',
						'entity_type' => $post->post_type,
						'entity_id'   => $post_id,
						'summary'     => sprintf(
							/* translators: 1: title, 2: count */
							__( 'لینک‌سازی «%1$s»: %2$d لینک در کش', 'shojaei-seo-for-woo' ),
							$post->post_title,
							(int) $result['links_added']
						),
						'before'      => array(
							'transient'     => $cache_key,
							'has_cache'     => false !== $prev,
							'cache_content' => is_string( $prev ) ? $prev : '',
						),
						'after'       => array(
							'links_added' => (int) $result['links_added'],
							'details'     => $result['details'],
							'has_cache'   => true,
						),
					)
				);
			}
			$ok++;
		}

		return array(
			'processed' => $ok,
			'failed'    => $fail,
			'offset'    => $new_offset,
			'cursor'    => $cursor,
			'done'      => $done,
			'message'   => $done ? __( 'بازسازی لینک‌های داخلی تکمیل شد.', 'shojaei-seo-for-woo' ) : '',
		);
	}

	/**
	 * Chunk: refresh schema stamp / rule sync for products.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_rebuild_schema_chunk( array $job, int $size ): array {
		$explicit = $job['payload']['product_ids'] ?? array();
		if ( ! empty( $explicit ) && is_array( $explicit ) ) {
			$ids        = array_values( array_filter( array_map( 'absint', $explicit ) ) );
			$offset     = (int) ( $job['offset'] ?? 0 );
			$slice      = array_slice( $ids, $offset, $size );
			$new_offset = $offset + count( $slice );
			$done       = $new_offset >= count( $ids );
			$cursor     = (int) ( $job['cursor'] ?? 0 );
		} else {
			$cursor = (int) ( $job['cursor'] ?? 0 );
			global $wpdb;
			$slice = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'product' AND post_status = 'publish' AND ID > %d
					ORDER BY ID ASC LIMIT %d",
					$cursor,
					$size
				)
			);
			$slice      = array_map( 'absint', $slice ?: array() );
			$new_offset = (int) ( $job['offset'] ?? 0 ) + count( $slice );
			$done       = count( $slice ) < $size;
			$cursor     = ! empty( $slice ) ? (int) end( $slice ) : $cursor;
		}

		$ok   = 0;
		$fail = 0;

		foreach ( $slice as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$fail++;
				continue;
			}
			update_post_meta( $product_id, '_shojaei_seo_schema_rebuilt_at', time() );
			if ( class_exists( 'Shojaei_SEO_Rule_Engine' ) ) {
				$decision = Shojaei_SEO_Rule_Engine::evaluate_product( $product_id );
				if ( $decision ) {
					Shojaei_SEO_Rule_Engine::sync_decision_meta( $product_id, $decision );
				}
			}
			$ok++;
		}

		return array(
			'processed' => $ok,
			'failed'    => $fail,
			'offset'    => $new_offset,
			'cursor'    => $cursor,
			'done'      => $done,
			'message'   => $done ? __( 'بازتولید داده ساختاریافته تکمیل شد.', 'shojaei-seo-for-woo' ) : '',
		);
	}

	/**
	 * Chunk: initial OOS inventory scan via Jobs queue.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_initial_scan_chunk( array $job, int $size ): array {
		$ids = $job['payload']['product_ids'] ?? array();
		if ( empty( $ids ) ) {
			$ids = Shojaei_SEO_Queue::get_candidate_oos_product_ids();
			if ( ! empty( $ids ) ) {
				global $wpdb;
				$wpdb->update(
					Shojaei_SEO_Jobs::table(),
					array(
						'payload' => wp_json_encode( array( 'product_ids' => $ids ) ),
						'total'   => count( $ids ),
					),
					array( 'job_key' => (string) $job['id'] ),
					array( '%s', '%d' ),
					array( '%s' )
				);
				$job['payload']['product_ids'] = $ids;
				$job['total']                  = count( $ids );
			}
		}

		$offset = (int) ( $job['offset'] ?? 0 );
		$slice  = array_slice( $ids, $offset, $size );
		$queue  = new Shojaei_SEO_Queue();
		$queue->scan_product_ids( $slice );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		if ( $done ) {
			update_option( 'shojaei_seo_initial_scan_done', 'yes', false );
			update_option( 'shojaei_seo_initial_scan_pending', 0, false );
		} else {
			update_option( 'shojaei_seo_initial_scan_pending', max( 0, count( $ids ) - $new_offset ), false );
			if ( (int) get_option( 'shojaei_seo_initial_scan_total', 0 ) < 1 ) {
				update_option( 'shojaei_seo_initial_scan_total', count( $ids ), false );
			}
		}

		return array(
			'processed' => count( $slice ),
			'failed'    => 0,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done ? __( 'اسکن اولیه موجودی کامل شد.', 'shojaei-seo-for-woo' ) : '',
		);
	}

	/**
	 * Chunk: full-catalog slug health scan.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_slug_health_scan_chunk( array $job, int $size ): array {
		$ids = $job['payload']['product_ids'] ?? array();
		if ( empty( $ids ) || ! class_exists( 'Shojaei_SEO_Slug' ) ) {
			return array(
				'processed'     => 0,
				'failed'        => 0,
				'done'          => true,
				'status_failed' => true,
				'message'       => __( 'لیست محصولات برای اسکن نامک خالی است.', 'shojaei-seo-for-woo' ),
			);
		}

		$offset = (int) ( $job['offset'] ?? 0 );
		$slice  = array_slice( $ids, $offset, $size );
		Shojaei_SEO_Slug::process_health_scan_ids( $slice );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		if ( $done ) {
			Shojaei_SEO_Slug::finalize_full_health_report();
		}

		return array(
			'processed' => count( $slice ),
			'failed'    => 0,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? __( 'اسکن کامل سلامت نامک تمام شد.', 'shojaei-seo-for-woo' )
				: sprintf(
					/* translators: 1: offset, 2: total */
					__( 'اسکن نامک: %1$d / %2$d', 'shojaei-seo-for-woo' ),
					$new_offset,
					count( $ids )
				),
		);
	}

	/**
	 * Chunk: SEO Pulse rule-based analysis (background — does not block admin).
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_seo_pulse_scan_chunk( array $job, int $size ): array {
		$ids = $job['payload']['post_ids'] ?? array();
		if ( empty( $ids ) || ! class_exists( 'Shojaei_SEO_Pulse' ) ) {
			return array(
				'processed'     => 0,
				'failed'        => 0,
				'done'          => true,
				'status_failed' => true,
				'message'       => __( 'لیست صفحات برای نبض سئو خالی است.', 'shojaei-seo-for-woo' ),
			);
		}

		$offset = (int) ( $job['offset'] ?? 0 );
		$force  = ! empty( $job['payload']['force'] );
		$slice  = array_slice( $ids, $offset, $size );
		$result = Shojaei_SEO_Pulse::process_ids( $slice, $force );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		return array(
			'processed' => (int) ( $result['processed'] ?? count( $slice ) ),
			'failed'    => 0,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? __( 'اسکن نبض سئو تمام شد.', 'shojaei-seo-for-woo' )
				: sprintf(
					/* translators: 1: offset, 2: total, 3: saved */
					__( 'نبض سئو: %1$d / %2$d (ذخیره این دسته: %3$d)', 'shojaei-seo-for-woo' ),
					$new_offset,
					count( $ids ),
					(int) ( $result['saved'] ?? 0 )
				),
		);
	}

	/**
	 * Chunk: crawl post content into link inventory.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_link_inventory_crawl_chunk( array $job, int $size ): array {
		$ids = $job['payload']['post_ids'] ?? array();
		if ( empty( $ids ) || ! class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			return array(
				'processed'     => 0,
				'failed'        => 0,
				'done'          => true,
				'status_failed' => true,
				'message'       => __( 'لیست نوشته‌ها برای اسکن لینک خالی است.', 'shojaei-seo-for-woo' ),
			);
		}

		$offset = (int) ( $job['offset'] ?? 0 );
		$slice  = array_slice( $ids, $offset, $size );
		$result = Shojaei_SEO_Link_Genius::process_crawl_ids( $slice );
		// Light HTTP check alongside crawl.
		Shojaei_SEO_Link_Genius::check_http_statuses( min( 20, $size ) );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		return array(
			'processed' => (int) ( $result['processed'] ?? count( $slice ) ),
			'failed'    => 0,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? __( 'اسکن موجودی لینک‌ها تمام شد.', 'shojaei-seo-for-woo' )
				: sprintf(
					/* translators: 1: offset, 2: total, 3: links */
					__( 'اسکن لینک: %1$d / %2$d (این دسته: %3$d لینک)', 'shojaei-seo-for-woo' ),
					$new_offset,
					count( $ids ),
					(int) ( $result['links'] ?? 0 )
				),
		);
	}

	/**
	 * Chunk: link watchdog — scan outbound links in product batch.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_link_watchdog_scan_chunk( array $job, int $size ): array {
		$ids = $job['payload']['post_ids'] ?? array();
		if ( empty( $ids ) || ! class_exists( 'Damavand_Link_Watchdog' ) ) {
			return array(
				'processed'     => 0,
				'failed'        => 0,
				'done'          => true,
				'status_failed' => true,
				'message'       => __( 'لیست محصولات برای نگهبان لینک خالی است.', 'shojaei-seo-for-woo' ),
			);
		}

		$offset = (int) ( $job['offset'] ?? 0 );
		$slice  = array_slice( $ids, $offset, max( 10, min( 40, $size ) ) );
		$result = Damavand_Link_Watchdog::process_batch( $slice );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		if ( $done && class_exists( 'Shojaei_SEO_Notifications' ) && Damavand_Link_Watchdog::open_count() > 0 ) {
			Shojaei_SEO_Notifications::add(
				'link_watchdog',
				sprintf(
					/* translators: 1: open alerts, 2: scanned products */
					__( 'نگهبان لینک: %1$d هشدار باز پس از بررسی %2$d محصول.', 'shojaei-seo-for-woo' ),
					Damavand_Link_Watchdog::open_count(),
					count( $ids )
				),
				0,
				admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory&watchdog=1' ),
				__( 'مشاهده هشدارها', 'shojaei-seo-for-woo' )
			);
		}

		return array(
			'processed' => count( $slice ),
			'failed'    => 0,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? sprintf(
					/* translators: 1: alerts */
					__( 'نگهبان لینک تمام شد — %1$d هشدار باز.', 'shojaei-seo-for-woo' ),
					(int) ( $result['alerts'] ?? 0 )
				)
				: sprintf(
					/* translators: 1: offset, 2: total */
					__( 'نگهبان لینک: %1$d / %2$d', 'shojaei-seo-for-woo' ),
					$new_offset,
					count( $ids )
				),
		);
	}

	/**
	 * Chunk: Damavand smart link graph calculator.
	 *
	 * @param array $job  Job.
	 * @param int   $size Batch size.
	 * @return array
	 */
	private static function run_damavand_link_calc_chunk( array $job, int $size ): array {
		$ids = $job['payload']['post_ids'] ?? array();
		if ( empty( $ids ) || ! class_exists( 'Damavand_Link_Calculator' ) ) {
			return array(
				'processed'     => 0,
				'failed'        => 0,
				'done'          => true,
				'status_failed' => true,
				'message'       => __( 'لیست محصولات برای محاسبه لینک خالی است.', 'shojaei-seo-for-woo' ),
			);
		}

		$offset = (int) ( $job['offset'] ?? 0 );
		$slice  = array_slice( $ids, $offset, $size );
		$result = Damavand_Link_Calculator::process_ids( $slice, 5 );

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		return array(
			'processed' => (int) ( $result['processed'] ?? count( $slice ) ),
			'failed'    => 0,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done
				? __( 'محاسبه لینک هوشمند تمام شد.', 'shojaei-seo-for-woo' )
				: sprintf(
					/* translators: 1: offset, 2: total, 3: saved */
					__( 'لینک هوشمند: %1$d / %2$d (یال این دسته: %3$d)', 'shojaei-seo-for-woo' ),
					$new_offset,
					count( $ids ),
					(int) ( $result['saved'] ?? 0 )
				),
		);
	}

	/**
	 * Whether days OOS crossed a lifecycle threshold (message / temp / auto).
	 *
	 * @param int $prev_days Previous days.
	 * @param int $days      Current days.
	 */
	private static function crossed_lifecycle_boundary( int $prev_days, int $days ): bool {
		if ( $days <= $prev_days ) {
			return false;
		}
		$t = Shojaei_SEO_Helpers::get_oos_timeline();
		foreach ( array( (int) $t['message_day'], (int) $t['temp_days'], (int) $t['auto_day'] ) as $bound ) {
			if ( $prev_days < $bound && $days >= $bound ) {
				return true;
			}
		}
		return false;
	}
}
