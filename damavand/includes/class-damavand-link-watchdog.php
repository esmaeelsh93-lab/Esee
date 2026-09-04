<?php
/**
 * Periodic internal link health watchdog — alerts with fixes.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Link_Watchdog
 */
class Damavand_Link_Watchdog {

	public const CRON_HOOK = 'shojaei_seo_link_watchdog';

	public const OPTION_ALERTS = 'damavand_link_watchdog_alerts';

	public const OPTION_STATE = 'damavand_link_watchdog_state';

	public const BATCH_CATS = 5;

	public const BATCH_PRODUCTS = 80;

	public const MAX_ALERTS = 400;

	/**
	 * Register cron + notices + hooks.
	 */
	public static function register_hooks(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_tick' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'damavand_seo_redirect_applied', array( __CLASS__, 'on_redirect_applied' ), 10, 3 );
	}

	/**
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function register_schedules( array $schedules ): array {
		if ( ! isset( $schedules['shojaei_seo_every_two_days'] ) ) {
			$schedules['shojaei_seo_every_two_days'] = array(
				'interval' => 2 * DAY_IN_SECONDS,
				'display'  => __( 'هر دو روز (دماوند)', 'shojaei-seo-for-woo' ),
			);
		}
		return $schedules;
	}

	/**
	 * Schedule cron on activate/upgrade.
	 */
	public static function ensure_scheduled(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedules' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'shojaei_seo_every_two_days', self::CRON_HOOK );
		}
	}

	/**
	 * Clear cron on deactivate.
	 */
	public static function clear_scheduled(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Cron: enqueue category batch scan.
	 */
	public static function cron_tick(): void {
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			self::run_inline_batch();
			return;
		}
		if ( Shojaei_SEO_Jobs::has_active( 'link_watchdog_scan' ) ) {
			return;
		}

		$ids = self::next_product_batch();
		if ( empty( $ids ) ) {
			return;
		}

		Shojaei_SEO_Jobs::enqueue(
			'link_watchdog_scan',
			array(
				'post_ids' => $ids,
				'cats'     => self::state_get( 'last_cat_ids', array() ),
			),
			count( $ids )
		);

		// Light redirect audit refresh after each batch enqueue.
		if ( class_exists( 'Shojaei_SEO_Redirect_Audit' ) ) {
			Shojaei_SEO_Redirect_Audit::scan_broken();
		}
	}

	/**
	 * Fallback when job queue unavailable.
	 */
	private static function run_inline_batch(): void {
		$ids = self::next_product_batch();
		foreach ( array_slice( $ids, 0, 20 ) as $pid ) {
			self::scan_post( (int) $pid );
		}
		self::merge_redirect_alerts();
	}

	/**
	 * Process one chunk (called from Batch).
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return array{alerts:int,scanned:int}
	 */
	public static function process_batch( array $post_ids ): array {
		$n = 0;
		foreach ( $post_ids as $pid ) {
			$n += self::scan_post( (int) $pid );
		}
		self::merge_redirect_alerts();
		return array(
			'alerts'  => self::open_count(),
			'scanned' => count( $post_ids ),
		);
	}

	/**
	 * Scan outbound links in one post.
	 *
	 * @param int $post_id Post ID.
	 * @return int New/updated alerts count.
	 */
	public static function scan_post( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return 0;
		}

		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			Shojaei_SEO_Link_Genius::index_post_links( $post_id );
		}

		$html  = class_exists( 'Shojaei_SEO_Link_Builder' )
			? Shojaei_SEO_Link_Builder::resolve_linkable_html( $post_id, (string) $post->post_content )
			: (string) $post->post_content;
		$links = class_exists( 'Shojaei_SEO_Link_Genius' )
			? Shojaei_SEO_Link_Genius::extract_links_from_html( $html, $post_id )
			: array();

		$added = 0;
		foreach ( $links as $link ) {
			if ( 'internal' !== ( $link['link_type'] ?? '' ) ) {
				continue;
			}
			$dest = (string) ( $link['dest_url'] ?? '' );
			if ( '' === $dest ) {
				continue;
			}
			$issue = self::classify_dest( $dest );
			if ( empty( $issue['alert'] ) ) {
				self::clear_matching_alerts( $post_id, $dest );
				continue;
			}
			if ( self::upsert_alert( $post_id, $dest, $issue ) ) {
				++$added;
			}
		}

		return $added;
	}

	/**
	 * Live reaction when OOS/410/301 applied to a product.
	 *
	 * @param int    $product_id     Product.
	 * @param string $redirect_type  301|302|410.
	 * @param string $target_url     Target.
	 */
	public static function on_redirect_applied( int $product_id, string $redirect_type, string $target_url = '' ): void {
		if ( $product_id < 1 ) {
			return;
		}
		$url = (string) get_permalink( $product_id );
		if ( '' === $url ) {
			return;
		}

		global $wpdb;
		if ( ! class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			return;
		}
		$table = Shojaei_SEO_Link_Genius::inventory_table();
		$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path  = $path ? untrailingslashit( $path ) : '';
		if ( ! $path ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT source_post_id FROM {$table} WHERE link_type = 'internal' AND dest_url LIKE %s",
				'%' . $wpdb->esc_like( $path ) . '%'
			)
		);
		if ( ! is_array( $rows ) ) {
			return;
		}

		$code = '410' === $redirect_type ? 'target_410' : 'target_redirect';
		foreach ( $rows as $row ) {
			$sid = (int) ( $row->source_post_id ?? 0 );
			if ( $sid < 1 ) {
				continue;
			}
			self::upsert_alert(
				$sid,
				$url,
				array(
					'alert'    => true,
					'code'     => $code,
					'severity' => '410' === $redirect_type ? 'error' : 'warning',
					'label'    => '410' === $redirect_type
						? __( 'مقصد ۴۱۰ Gone شده', 'shojaei-seo-for-woo' )
						: __( 'مقصد ریدایرکت شده', 'shojaei-seo-for-woo' ),
					'fix'      => '410' === $redirect_type ? 'remove_link' : 'update_url',
					'fix_label'=> '410' === $redirect_type
						? __( 'حذف لینک از متن', 'shojaei-seo-for-woo' )
						: __( 'به‌روزرسانی آدرس', 'shojaei-seo-for-woo' ),
					'fix_url'  => '410' !== $redirect_type ? $target_url : '',
					'dest_id'  => $product_id,
				)
			);
		}

		if ( class_exists( 'Shojaei_SEO_Notifications' ) ) {
			$count = count( $rows );
			if ( $count > 0 ) {
				Shojaei_SEO_Notifications::add(
					'link_at_risk',
					sprintf(
						/* translators: 1: product title, 2: count */
						__( '«%1$s» %2$d لینک داخلی در محتوا دارد که باید بررسی شود.', 'shojaei-seo-for-woo' ),
						get_the_title( $product_id ),
						$count
					),
					$product_id,
					admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory&status=at_risk' ),
					__( 'لینکهای در خطر', 'shojaei-seo-for-woo' )
				);
			}
		}
	}

	/**
	 * Classify internal destination.
	 *
	 * @param string $url URL.
	 * @return array{alert:bool,code:string,severity:string,label:string,fix:string,fix_label:string,fix_url?:string,dest_id?:int}
	 */
	public static function classify_dest( string $url ): array {
		$ok = array(
			'alert'     => false,
			'code'      => 'ok',
			'severity'  => 'ok',
			'label'     => '',
			'fix'       => '',
			'fix_label' => '',
		);

		if ( class_exists( 'Shojaei_SEO_Redirect_Audit' ) ) {
			$class = Shojaei_SEO_Redirect_Audit::classify_target( $url );
			if ( empty( $class['broken'] ) ) {
				return $ok;
			}
			$code = (string) ( $class['code'] ?? 'broken' );
			$map  = self::issue_map();
			$row  = $map[ $code ] ?? $map['broken'];
			return array(
				'alert'     => true,
				'code'      => $code,
				'severity'  => (string) ( $class['severity'] ?? 'error' ),
				'label'     => (string) ( Shojaei_SEO_Redirect_Audit::broken_labels()[ $code ] ?? $row['label'] ),
				'fix'       => (string) $row['fix'],
				'fix_label' => (string) $row['fix_label'],
				'dest_id'   => (int) ( $class['post_id'] ?? 0 ),
			);
		}

		$pid = url_to_postid( $url );
		if ( $pid > 0 && class_exists( 'Shojaei_SEO_Slug' ) && Shojaei_SEO_Slug::is_410_product( $pid ) ) {
			return array(
				'alert'     => true,
				'code'      => 'target_410',
				'severity'  => 'error',
				'label'     => __( 'مقصد ۴۱۰ Gone', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک از متن', 'shojaei-seo-for-woo' ),
				'dest_id'   => $pid,
			);
		}

		return $ok;
	}

	/**
	 * @return array<string,array{label:string,fix:string,fix_label:string}>
	 */
	private static function issue_map(): array {
		return array(
			'target_410'          => array(
				'label'     => __( 'مقصد ۴۱۰ Gone', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
			),
			'not_published'       => array(
				'label'     => __( 'مقصد منتشر نیست', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
			),
			'trashed'             => array(
				'label'     => __( 'مقصد در سطل زباله', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
			),
			'missing_post'        => array(
				'label'     => __( 'صفحه مقصد پیدا نشد', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
			),
			'unresolved_internal' => array(
				'label'     => __( 'آدرس داخلی نامعتبر', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
			),
			'broken'              => array(
				'label'     => __( 'لینک شکسته', 'shojaei-seo-for-woo' ),
				'fix'       => 'remove_link',
				'fix_label' => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
			),
		);
	}

	/**
	 * Pull redirect chain/loop issues into alert store.
	 */
	public static function merge_redirect_alerts(): void {
		if ( ! class_exists( 'Shojaei_SEO_Redirect_Audit' ) ) {
			return;
		}
		$chains = Shojaei_SEO_Redirect_Audit::get_chain_report();
		foreach ( (array) ( $chains['issues'] ?? array() ) as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$pid = (int) ( $issue['product_id'] ?? 0 );
			if ( $pid < 1 ) {
				continue;
			}
			$url = (string) get_permalink( $pid );
			self::upsert_alert(
				0,
				$url,
				array(
					'alert'     => true,
					'code'      => 'redirect_chain',
					'severity'  => 'warning',
					'label'     => __( 'زنجیره ریدایرکت', 'shojaei-seo-for-woo' ),
					'fix'       => 'flatten_redirect',
					'fix_label' => __( 'صاف کردن', 'shojaei-seo-for-woo' ),
					'dest_id'   => $pid,
					'meta'      => array(
						'path'      => $issue['path'] ?? array(),
						'final_url' => $issue['final_url'] ?? '',
					),
				)
			);
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_alerts(): array {
		$raw = get_option( self::OPTION_ALERTS, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Open (unresolved) alert count.
	 */
	public static function open_count(): int {
		$n = 0;
		foreach ( self::get_alerts() as $row ) {
			if ( empty( $row['resolved'] ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Alerts for one source post (editor banner).
	 *
	 * @param int $post_id Post.
	 * @param int $limit   Max.
	 * @return array<int,array<string,mixed>>
	 */
	public static function alerts_for_post( int $post_id, int $limit = 5 ): array {
		$out = array();
		foreach ( self::get_alerts() as $row ) {
			if ( ! empty( $row['resolved'] ) ) {
				continue;
			}
			if ( (int) ( $row['source_post_id'] ?? 0 ) !== $post_id ) {
				continue;
			}
			$out[] = $row;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * @param string $alert_id Alert ID.
	 */
	public static function dismiss_alert( string $alert_id ): void {
		$items = self::get_alerts();
		foreach ( $items as &$row ) {
			if ( (string) ( $row['id'] ?? '' ) === $alert_id ) {
				$row['resolved']    = true;
				$row['resolved_at'] = current_time( 'mysql' );
			}
		}
		unset( $row );
		update_option( self::OPTION_ALERTS, $items, false );
	}

	/**
	 * Admin notice when open alerts exist.
	 */
	public static function render_admin_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_products' ) ) {
			return;
		}

		$count = self::open_count();
		if ( $count < 1 ) {
			return;
		}

		$link = admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory&watchdog=1' );
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: count */
				__( 'نگهبان لینک دماوند: %d لینک داخلی مشکل‌دار یا در خطر — از «نگهبان لینک» اصلاح کنید.', 'shojaei-seo-for-woo' ),
				$count
			)
		);
		echo ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'مشاهده و رفع', 'shojaei-seo-for-woo' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Next product IDs from rotating product_cat batches.
	 *
	 * @return int[]
	 */
	private static function next_product_batch(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'fields'     => 'ids',
				'orderby'    => 'term_id',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return self::fallback_product_ids();
		}

		$terms = array_values( array_map( 'absint', $terms ) );
		$idx   = (int) self::state_get( 'cat_index', 0 );
		if ( $idx >= count( $terms ) ) {
			$idx = 0;
		}

		$slice = array_slice( $terms, $idx, self::BATCH_CATS );
		$idx  += count( $slice );
		if ( $idx >= count( $terms ) ) {
			$idx = 0;
		}
		self::state_set(
			array(
				'cat_index'    => $idx,
				'last_cat_ids' => $slice,
				'last_run'     => current_time( 'mysql' ),
			)
		);

		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => self::BATCH_PRODUCTS,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'post__not_in'           => class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_ids() : array(),
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $slice,
					),
				),
			)
		);

		$ids = array_map( 'absint', (array) $q->posts );
		return ! empty( $ids ) ? $ids : self::fallback_product_ids();
	}

	/**
	 * @return int[]
	 */
	private static function fallback_product_ids(): array {
		$ids = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => self::BATCH_PRODUCTS,
				'fields'                 => 'ids',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'offset'                 => (int) self::state_get( 'fallback_offset', 0 ),
				'post__not_in'           => class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_ids() : array(),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);
		$off = (int) self::state_get( 'fallback_offset', 0 ) + self::BATCH_PRODUCTS;
		self::state_set( array( 'fallback_offset' => $off ) );
		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * @param int    $source_id Source post (0 = system).
	 * @param string $dest_url  Dest URL.
	 * @param array  $issue     Issue payload.
	 */
	private static function upsert_alert( int $source_id, string $dest_url, array $issue ): bool {
		if ( empty( $issue['alert'] ) ) {
			return false;
		}

		$items  = self::get_alerts();
		$key    = md5( $source_id . '|' . $dest_url . '|' . ( $issue['code'] ?? '' ) );
		$found  = false;

		foreach ( $items as &$row ) {
			if ( (string) ( $row['key'] ?? '' ) === $key ) {
				$row['updated_at'] = current_time( 'mysql' );
				$row['label']      = (string) ( $issue['label'] ?? $row['label'] );
				$row['severity']   = (string) ( $issue['severity'] ?? 'warning' );
				$row['resolved']   = false;
				$found             = true;
				break;
			}
		}
		unset( $row );

		if ( ! $found ) {
			if ( count( $items ) >= self::MAX_ALERTS ) {
				array_shift( $items );
			}
			$items[] = array(
				'id'             => uniqid( 'dlw_', true ),
				'key'            => $key,
				'source_post_id' => $source_id,
				'dest_url'       => $dest_url,
				'dest_post_id'   => (int) ( $issue['dest_id'] ?? url_to_postid( $dest_url ) ),
				'code'           => (string) ( $issue['code'] ?? 'broken' ),
				'severity'       => (string) ( $issue['severity'] ?? 'warning' ),
				'label'          => (string) ( $issue['label'] ?? '' ),
				'fix'            => (string) ( $issue['fix'] ?? 'remove_link' ),
				'fix_label'      => (string) ( $issue['fix_label'] ?? __( 'حذف لینک', 'shojaei-seo-for-woo' ) ),
				'fix_url'        => (string) ( $issue['fix_url'] ?? '' ),
				'meta'           => is_array( $issue['meta'] ?? null ) ? $issue['meta'] : array(),
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
				'resolved'       => false,
			);
		}

		update_option( self::OPTION_ALERTS, $items, false );
		return ! $found;
	}

	/**
	 * @param int    $source_id Source.
	 * @param string $dest_url  Dest.
	 */
	private static function clear_matching_alerts( int $source_id, string $dest_url ): void {
		$key   = md5( $source_id . '|' . $dest_url . '|' );
		$items = self::get_alerts();
		$changed = false;
		foreach ( $items as &$row ) {
			if ( (int) ( $row['source_post_id'] ?? 0 ) !== $source_id ) {
				continue;
			}
			$row_dest = (string) ( $row['dest_url'] ?? '' );
			if ( $row_dest && ( $row_dest === $dest_url || ( $path && false !== stripos( $row_dest, $path ) ) ) ) {
				$row['resolved']    = true;
				$row['resolved_at'] = current_time( 'mysql' );
				$changed            = true;
			}
		}
		unset( $row );
		if ( $changed ) {
			update_option( self::OPTION_ALERTS, $items, false );
		}
	}

	/**
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	private static function state_get( string $key, $default = null ) {
		$state = get_option( self::OPTION_STATE, array() );
		if ( ! is_array( $state ) ) {
			return $default;
		}
		return array_key_exists( $key, $state ) ? $state[ $key ] : $default;
	}

	/**
	 * @param array $patch Patch.
	 */
	private static function state_set( array $patch ): void {
		$state = get_option( self::OPTION_STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		update_option( self::OPTION_STATE, array_merge( $state, $patch ), false );
	}
}
