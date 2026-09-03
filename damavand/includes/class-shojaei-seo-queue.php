<?php
/**
 * Background queue — prefers WooCommerce Action Scheduler, falls back to WP-Cron.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Queue
 */
class Shojaei_SEO_Queue {

	public const HOOK_PROCESS_OOS   = 'shojaei_seo_as_process_oos';
	public const HOOK_INITIAL_SCAN  = 'shojaei_seo_as_initial_scan';
	public const HOOK_SCAN_BATCH    = 'shojaei_seo_as_scan_batch';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'shojaei_seo_process_queue', array( $this, 'process_queue' ) );
		add_action( 'shojaei_seo_daily_oos_check', array( $this, 'daily_oos_check' ) );

		add_action( self::HOOK_PROCESS_OOS, array( $this, 'as_process_oos' ), 10, 1 );
		add_action( self::HOOK_INITIAL_SCAN, array( $this, 'as_start_initial_scan' ) );
		add_action( self::HOOK_SCAN_BATCH, array( $this, 'as_scan_batch' ), 10, 1 );
	}

	/**
	 * Batch size for queue/scan chunks (default 50).
	 */
	public static function batch_size(): int {
		if ( class_exists( 'Shojaei_SEO_Batch' ) ) {
			return Shojaei_SEO_Batch::batch_size();
		}
		return 50;
	}

	/**
	 * Whether Action Scheduler is available.
	 */
	public static function has_action_scheduler(): bool {
		return function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Process legacy option-based queue (fallback when AS is unavailable).
	 */
	public function process_queue(): void {
		$queue = get_option( 'shojaei_seo_queue', array() );
		if ( empty( $queue ) ) {
			return;
		}

		$batch   = array_splice( $queue, 0, self::batch_size() );
		$manager = new Shojaei_SEO_OOS_Manager( false );

		foreach ( $batch as $task ) {
			$type = $task['type'] ?? '';
			if ( 'oos_check' === $type ) {
				$manager->process_product_oos( (int) $task['product_id'] );
			} elseif ( 'initial_scan_batch' === $type ) {
				$this->scan_product_ids( $task['product_ids'] ?? array() );
			} elseif ( 'bootstrap_initial_scan' === $type ) {
				$this->as_start_initial_scan();
			}
		}

		update_option( 'shojaei_seo_queue', $queue, false );
	}

	/**
	 * Action Scheduler callback: process one OOS redirect.
	 *
	 * @param int $product_id Product ID.
	 */
	public function as_process_oos( int $product_id ): void {
		$manager = new Shojaei_SEO_OOS_Manager( false );
		$manager->process_product_oos( $product_id );
	}

	/**
	 * Kick off initial inventory scan in batches.
	 */
	public function as_start_initial_scan(): void {
		delete_option( 'shojaei_seo_initial_scan_queued' );
		$ids = self::get_candidate_oos_product_ids();
		if ( empty( $ids ) ) {
			update_option( 'shojaei_seo_initial_scan_done', 'yes', false );
			update_option( 'shojaei_seo_initial_scan_pending', 0, false );
			update_option( 'shojaei_seo_initial_scan_total', 0, false );
			Shojaei_SEO_Notifications::add(
				'initial_scan',
				__( 'اسکن اولیه موجودی انجام شد؛ محصول ناموجودی یافت نشد.', 'shojaei-seo-for-woo' ),
				0,
				admin_url( 'admin.php?page=shojaei-seo&tab=oos' ),
				__( 'لیست ناموجودها', 'shojaei-seo-for-woo' )
			);
			return;
		}

		update_option( 'shojaei_seo_initial_scan_total', count( $ids ), false );
		update_option( 'shojaei_seo_initial_scan_pending', count( $ids ), false );

		$batches = array_chunk( $ids, self::batch_size() );
		foreach ( $batches as $index => $batch ) {
			if ( self::has_action_scheduler() ) {
				as_schedule_single_action(
					time() + ( $index * 15 ),
					self::HOOK_SCAN_BATCH,
					array( $batch ),
					'shojaei-seo'
				);
			} else {
				self::add( 'initial_scan_batch', array( 'product_ids' => $batch ) );
			}
		}
	}

	/**
	 * Process one scan batch.
	 *
	 * @param array $product_ids Product IDs.
	 */
	public function as_scan_batch( $product_ids ): void {
		$ids = is_array( $product_ids ) ? $product_ids : array();
		$this->scan_product_ids( $ids );

		$remaining = max( 0, (int) get_option( 'shojaei_seo_initial_scan_pending', 0 ) - count( $ids ) );
		update_option( 'shojaei_seo_initial_scan_pending', $remaining, false );

		if ( 0 === $remaining ) {
			update_option( 'shojaei_seo_initial_scan_done', 'yes', false );
			Shojaei_SEO_Notifications::add(
				'initial_scan',
				__( 'اسکن اولیه موجودی کامل شد. محصولات ناموجود قبلی وارد سیستم شدند.', 'shojaei-seo-for-woo' ),
				0,
				admin_url( 'admin.php?page=shojaei-seo&tab=oos' ),
				__( 'لیست ناموجودها', 'shojaei-seo-for-woo' )
			);
		}
	}

	/**
	 * Register OOS records for a list of product IDs.
	 *
	 * @param array $product_ids Product IDs.
	 */
	public function scan_product_ids( array $product_ids ): void {
		$manager = new Shojaei_SEO_OOS_Manager( false );

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				$manager->sync_variable_parent_oos( $product_id );
			} elseif ( $product->is_type( 'simple' ) && ! $product->is_in_stock() ) {
				$manager->register_oos_public( $product_id );
			}

			// Refresh days for already-tracked OOS using stored/estimated start date.
			$manager->refresh_oos_days_public( $product_id );
		}
	}

	/**
	 * Collect parent product IDs that appear fully out of stock (paged queries).
	 *
	 * @return int[]
	 */
	public static function get_candidate_oos_product_ids(): array {
		$ids      = array();
		$page_size = max( 50, self::batch_size() * 2 );

		// Simple + variable parents Woo already marks outofstock (fast meta query).
		$page = 1;
		do {
			$chunk = get_posts( array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $page_size,
				'paged'                  => $page,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_stock_status',
						'value' => 'outofstock',
					),
				),
				'tax_query'              => array(
					array(
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => array( 'simple', 'variable' ),
					),
				),
			) );
			$ids = array_merge( $ids, array_map( 'absint', $chunk ) );
			$page++;
		} while ( count( $chunk ) === $page_size );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Daily cron: enqueue batched lifecycle check (not all products at once).
	 */
	public function daily_oos_check(): void {
		if ( class_exists( 'Shojaei_SEO_Batch' ) ) {
			Shojaei_SEO_Batch::start_daily_oos_job();
			return;
		}

		// Fallback: process a single SQL-limited batch if Batch class missing.
		global $wpdb;
		$table   = Shojaei_SEO_Helpers::oos_table();
		$size    = self::batch_size();
		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, oos_date, status FROM {$table}
				WHERE status != 'redirected' ORDER BY id ASC LIMIT %d",
				$size
			)
		);

		if ( empty( $records ) || ! Shojaei_SEO_Helpers::is_module_enabled( 'oos' ) ) {
			return;
		}

		foreach ( $records as $record ) {
			$days  = Shojaei_SEO_Helpers::days_since_oos( (string) $record->oos_date );
			if ( $days <= 0 && ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( (string) $record->oos_date ) ) {
				$fixed = class_exists( 'Shojaei_SEO_OOS_Manager' )
					? Shojaei_SEO_OOS_Manager::estimate_oos_started_at( (int) $record->product_id, false )
					: Shojaei_SEO_Helpers::mysql_datetime();
				$days  = Shojaei_SEO_Helpers::days_since_oos( $fixed );
				$record->oos_date = $fixed;
			}
			$state = Shojaei_SEO_Helpers::get_oos_state( $days );
			Shojaei_SEO_Helpers::sync_oos_postmeta( (int) $record->product_id, (string) $record->oos_date, $days );
			$new_status = ( 'needs_manual' !== $record->status ) ? $state['status'] : $record->status;
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
				4 === $state['phase']
				&& 'needs_manual' !== $record->status
				&& 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect', 'yes' )
			) {
				self::enqueue_oos_process( (int) $record->product_id );
			}
		}
	}

	/**
	 * Enqueue a single product for OOS auto-redirect processing.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function enqueue_oos_process( int $product_id ): void {
		if ( ! $product_id ) {
			return;
		}

		if ( self::has_action_scheduler() ) {
			$scheduled = function_exists( 'as_has_scheduled_action' )
				? as_has_scheduled_action( self::HOOK_PROCESS_OOS, array( $product_id ), 'shojaei-seo' )
				: false;
			if ( ! $scheduled ) {
				as_enqueue_async_action( self::HOOK_PROCESS_OOS, array( $product_id ), 'shojaei-seo' );
			}
			return;
		}

		self::add( 'oos_check', array( 'product_id' => $product_id ) );
	}

	/**
	 * Add a task to the legacy option queue.
	 *
	 * @param string $type Task type.
	 * @param array  $data Task data.
	 */
	public static function add( string $type, array $data = array() ): void {
		$queue   = get_option( 'shojaei_seo_queue', array() );
		$queue[] = array_merge( array( 'type' => $type ), $data );
		update_option( 'shojaei_seo_queue', $queue, false );
	}

	/**
	 * Force a fresh inventory scan (manual from admin).
	 *
	 * @return bool
	 */
	public static function force_rescan(): bool {
		// Already mid-scan: treat as continue (UI keeps polling).
		if ( (int) get_option( 'shojaei_seo_initial_scan_pending', 0 ) > 0 ) {
			return true;
		}

		delete_option( 'shojaei_seo_initial_scan_done' );
		update_option( 'shojaei_seo_initial_scan_pending', 0, false );
		update_option( 'shojaei_seo_initial_scan_total', 0, false );
		update_option( 'shojaei_seo_initial_scan_queued', 'yes', false );

		if ( self::has_action_scheduler() ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::HOOK_INITIAL_SCAN, null, 'shojaei-seo' );
				as_unschedule_all_actions( self::HOOK_SCAN_BATCH, null, 'shojaei-seo' );
			}
			// Delay so admin-ajax does not run the heavy bootstrap in the same request (timeout / 🚫).
			as_schedule_single_action( time() + 3, self::HOOK_INITIAL_SCAN, array(), 'shojaei-seo' );
		} else {
			self::add( 'bootstrap_initial_scan' );
			if ( ! wp_next_scheduled( 'shojaei_seo_process_queue' ) ) {
				wp_schedule_single_event( time() + 3, 'shojaei_seo_process_queue' );
			}
		}

		Shojaei_SEO_Notifications::add(
			'initial_scan',
			__( 'اسکن مجدد موجودی شروع شد. پیشرفت را در داشبورد ببینید.', 'shojaei-seo-for-woo' ),
			0,
			admin_url( 'admin.php?page=shojaei-seo&tab=dashboard' ),
			__( 'رفتن به داشبورد', 'shojaei-seo-for-woo' )
		);

		return true;
	}

	/**
	 * Progress payload for UI (pending / total / percent).
	 *
	 * @return array{done:bool,pending:int,total:int,processed:int,percent:int,label:string}
	 */
	public static function get_scan_progress(): array {
		$pending = max( 0, (int) get_option( 'shojaei_seo_initial_scan_pending', 0 ) );
		$total   = max( 0, (int) get_option( 'shojaei_seo_initial_scan_total', 0 ) );
		$done    = 'yes' === get_option( 'shojaei_seo_initial_scan_done', '' );
		$queued  = 'yes' === get_option( 'shojaei_seo_initial_scan_queued', '' );

		if ( $total < 1 && $pending > 0 ) {
			$total = $pending;
		}
		if ( $done ) {
			$pending = 0;
			$queued  = false;
		}

		$processed = ( $total > 0 ) ? max( 0, $total - $pending ) : 0;
		$percent   = $done ? 100 : ( $total > 0 ? (int) min( 99, round( ( $processed / $total ) * 100 ) ) : ( $queued ? 1 : 0 ) );

		if ( $done ) {
			$label = __( 'اسکن موجودی کامل شد.', 'shojaei-seo-for-woo' );
		} elseif ( $total > 0 ) {
			$label = sprintf(
				/* translators: 1: processed 2: total */
				__( 'در حال اسکن: %1$d از %2$d محصول', 'shojaei-seo-for-woo' ),
				$processed,
				$total
			);
		} elseif ( $queued || $pending > 0 ) {
			$label = __( 'اسکن در صف است…', 'shojaei-seo-for-woo' );
		} else {
			$label = __( 'آماده برای شروع اسکن', 'shojaei-seo-for-woo' );
		}

		return array(
			'done'      => $done && 0 === $pending && ! $queued,
			'pending'   => $pending,
			'total'     => $total,
			'processed' => $processed,
			'percent'   => $percent,
			'label'     => $label,
			'running'   => $queued || $pending > 0 || ( ! $done && $total > 0 ),
		);
	}

	/**
	 * Schedule the initial inventory scan.
	 */
	public static function schedule_initial_scan(): void {
		if ( 'yes' === get_option( 'shojaei_seo_initial_scan_done', '' ) ) {
			return;
		}

		if ( self::has_action_scheduler() ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK_INITIAL_SCAN, array(), 'shojaei-seo' ) ) {
				return;
			}
			as_enqueue_async_action( self::HOOK_INITIAL_SCAN, array(), 'shojaei-seo' );
			return;
		}

		self::add( 'bootstrap_initial_scan' );
	}
}
