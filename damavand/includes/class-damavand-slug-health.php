<?php
/**
 * Slug health scan/report/apply/batch/undo/search and 410 map.
 *
 * Extracted from Shojaei_SEO_Slug (Task 5). Facade wrappers remain on Shojaei_SEO_Slug.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Slug_Health
 */
class Damavand_Slug_Health {
	/**
	 * Product IDs currently marked 410 Gone in OOS tracker.
	 *
	 * @return array<int,true> Map of product_id => true.
	 */
	public static function get_410_product_map(): array {
		if ( ! class_exists( 'Shojaei_SEO_Helpers' ) ) {
			return array();
		}
		return Shojaei_SEO_Helpers::get_410_excluded_map();
	}

	/**
	 * Whether product has an active 410 Gone decision.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function is_410_product( int $product_id ): bool {
		if ( $product_id < 1 ) {
			return false;
		}
		$map = self::get_410_product_map();
		return isset( $map[ $product_id ] );
	}

	/**
	 * Option key for full-catalog slug health report.
	 */
	public static function full_report_option(): string {
		return 'shojaei_seo_slug_health_full';
	}

	/**
	 * Stored full health report (may be in-progress).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_stored_full_report(): array {
		$raw = get_option( self::full_report_option(), array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Drop products from the cached health list (after apply / already finglish).
	 *
	 * @param int[] $product_ids IDs.
	 */
	public static function prune_health_report_ids( array $product_ids ): void {
		$want = array();
		foreach ( $product_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$want[ $id ] = true;
			}
		}
		if ( empty( $want ) ) {
			return;
		}
		$report = self::get_stored_full_report();
		$rows   = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();
		if ( empty( $rows ) ) {
			return;
		}
		$kept = array();
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['product_id'] ?? 0 );
			if ( isset( $want[ $pid ] ) ) {
				continue;
			}
			$kept[] = $row;
		}
		if ( count( $kept ) === count( $rows ) ) {
			return;
		}
		$report['rows']        = $kept;
		$report['issues']      = count( $kept );
		$report['stored_rows'] = count( $kept );
		update_option( self::full_report_option(), $report, false );
	}

	/**
	 * Current slug already is the Finglish suggestion (or uniquified -2).
	 */
	public static function slug_is_applied_suggestion( string $slug, string $suggest ): bool {
		$slug    = trim( rawurldecode( $slug ), '/' );
		$suggest = trim( $suggest, '/' );
		if ( '' === $slug || '' === $suggest ) {
			return false;
		}
		if ( $slug === $suggest ) {
			return true;
		}
		return (bool) preg_match( '/^' . preg_quote( $suggest, '/' ) . '-[0-9]+$/', $slug );
	}

	/**
	 * Drop cached health rows whose live slug is already fixed.
	 *
	 * @param array<int,array<string,mixed>> $rows Stored rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sync_stored_health_rows( array $rows ): array {
		if ( empty( $rows ) ) {
			return array();
		}
		global $wpdb;
		$ids = array();
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['product_id'] ?? 0 );
			if ( $pid > 0 ) {
				$ids[] = $pid;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$live = array();
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$in     = implode( ',', array_map( 'absint', $chunk ) );
			$found  = $wpdb->get_results(
				"SELECT ID, post_name, post_status FROM {$wpdb->posts} WHERE ID IN ({$in})"
			);
			if ( ! is_array( $found ) ) {
				continue;
			}
			foreach ( $found as $p ) {
				$live[ (int) $p->ID ] = $p;
			}
		}

		$gone    = self::get_410_product_map();
		$out     = array();
		$changed = false;
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['product_id'] ?? 0 );
			$p   = $live[ $pid ] ?? null;
			if ( ! $p || 'publish' !== (string) $p->post_status ) {
				$changed = true;
				continue;
			}
			if ( isset( $gone[ $pid ] ) ) {
				$changed = true;
				continue;
			}
			$current = (string) $p->post_name;
			if ( $current === (string) ( $row['slug'] ?? '' ) ) {
				$out[] = $row;
				continue;
			}
			$changed  = true;
			$analyzed = self::analyze_product_health( $pid, $gone );
			if ( ! empty( $analyzed['row'] ) ) {
				$fresh = $analyzed['row'];
				unset( $fresh['view_url'] );
				$out[] = $fresh;
			}
		}

		if ( $changed ) {
			$report = self::get_stored_full_report();
			$report['rows']        = $out;
			$report['issues']      = count( $out );
			$report['stored_rows'] = count( $out );
			update_option( self::full_report_option(), $report, false );
		}

		return $out;
	}

	/**
	 * All published product IDs (newest first).
	 *
	 * @return int[]
	 */
	public static function get_all_published_product_ids(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'product' AND post_status = 'publish'
			ORDER BY ID DESC"
		);
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$ids  = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$gone = self::get_410_product_map();
		if ( empty( $gone ) ) {
			return $ids;
		}
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $gone ) {
					return ! isset( $gone[ $id ] );
				}
			)
		);
	}

	/**
	 * Analyze one product for health row (or null if OK / skipped).
	 *
	 * @param int             $product_id Product ID.
	 * @param array<int,true> $gone_410   410 map.
	 * @return array{row:?array,skipped_410:bool}
	 */
	public static function analyze_product_health( int $product_id, array $gone_410 = array() ): array {
		if ( isset( $gone_410[ $product_id ] ) ) {
			return array(
				'row'         => null,
				'skipped_410' => true,
			);
		}

		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return array(
				'row'         => null,
				'skipped_410' => false,
			);
		}

		$slug    = (string) $post->post_name;
		$slug_ui = rawurldecode( $slug );
		$title   = (string) $post->post_title;
		$suggest = Damavand_Slug_Finglish::transliterate( $title );
		if ( $suggest && self::slug_is_applied_suggestion( $slug, $suggest ) ) {
			return array(
				'row'         => null,
				'skipped_410' => false,
			);
		}
		$score   = Damavand_Slug_Finglish::score_slug( $slug );
		$reasons = array();

		if ( Damavand_Slug_Finglish::has_persian( $slug ) || Damavand_Slug_Finglish::has_persian( $slug_ui ) ) {
			$reasons[] = 'persian';
		}
		if ( strlen( $slug_ui ) > 60 || strlen( $slug ) > 80 ) {
			$reasons[] = 'long';
		}
		if ( $score['score'] < 70 ) {
			$reasons[] = 'low_score';
		}
		if ( $suggest && $suggest !== $slug && $suggest !== $slug_ui && ( Damavand_Slug_Finglish::has_persian( $slug_ui ) || $score['score'] < 75 ) ) {
			$reasons[] = 'finglish_better';
		}

		if ( empty( $reasons ) ) {
			return array(
				'row'         => null,
				'skipped_410' => false,
			);
		}

		return array(
			'row'         => array(
				'product_id'  => $product_id,
				'title'       => $title,
				'slug'        => $slug,
				'suggest'     => $suggest,
				'score'       => (int) $score['score'],
				'reasons'     => array_values( array_unique( $reasons ) ),
				'has_persian' => in_array( 'persian', $reasons, true ) ? 1 : 0,
				'has_long'    => in_array( 'long', $reasons, true ) ? 1 : 0,
				'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
				'view_url'    => get_permalink( $product_id ),
			),
			'skipped_410' => false,
		);
	}

	/**
	 * Start background full-catalog slug health scan.
	 *
	 * @return array{ok:bool,message:string,job_id?:string,total?:int}
	 */
	public static function start_full_health_scan(): array {
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'صف جاب در دسترس نیست.', 'shojaei-seo-for-woo' ),
			);
		}
		if ( Shojaei_SEO_Jobs::has_active( 'slug_health_scan' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'اسکن نامک قبلاً در حال اجراست.', 'shojaei-seo-for-woo' ),
			);
		}

		$ids = self::get_all_published_product_ids();
		update_option(
			self::full_report_option(),
			array(
				'rows'         => array(),
				'scanned'      => 0,
				'skipped_410'  => 0,
				'issues'       => 0,
				'complete'     => false,
				'total'        => count( $ids ),
				'started_at'   => current_time( 'mysql' ),
				'finished_at'  => '',
			),
			false
		);

		$job_id = Shojaei_SEO_Jobs::enqueue(
			'slug_health_scan',
			array( 'product_ids' => $ids ),
			array( 'total' => count( $ids ) )
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: product count */
				__( 'اسکن کامل نامک برای %d محصول در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
				count( $ids )
			),
			'job_id'  => $job_id,
			'total'   => count( $ids ),
		);
	}

	/**
	 * Process a chunk of product IDs for full health scan.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array{processed:int,issues_added:int}
	 */
	public static function process_health_scan_ids( array $ids ): array {
		$gone   = self::get_410_product_map();
		$report = self::get_stored_full_report();
		if ( empty( $report ) || ! isset( $report['rows'] ) ) {
			$report = array(
				'rows'        => array(),
				'scanned'     => 0,
				'skipped_410' => 0,
				'issues'      => 0,
				'complete'    => false,
			);
		}

		$added = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				continue;
			}
			$result = self::analyze_product_health( $id, $gone );
			++$report['scanned'];
			if ( ! empty( $result['skipped_410'] ) ) {
				++$report['skipped_410'];
			}
			if ( ! empty( $result['row'] ) ) {
				$row = $result['row'];
				unset( $row['view_url'] );
				$report['rows'][] = $row;
				++$added;
			}
		}

		$report['issues']   = count( $report['rows'] );
		$report['complete'] = false;
		update_option( self::full_report_option(), $report, false );

		return array(
			'processed'    => count( $ids ),
			'issues_added' => $added,
		);
	}

	/**
	 * Finalize full report: dup flags, sort, trim heavy fields, mark complete.
	 * Keeps at most 2000 worst-scoring issues to avoid bloating wp_options.
	 */
	public static function finalize_full_health_report(): void {
		$report = self::get_stored_full_report();
		$rows   = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();

		$by_suggest = array();
		foreach ( $rows as $row ) {
			$s = (string) ( $row['suggest'] ?? '' );
			if ( '' === $s ) {
				continue;
			}
			if ( ! isset( $by_suggest[ $s ] ) ) {
				$by_suggest[ $s ] = array();
			}
			$by_suggest[ $s ][] = (int) ( $row['product_id'] ?? 0 );
		}

		foreach ( $rows as &$row ) {
			$s = (string) ( $row['suggest'] ?? '' );
			if ( $s && isset( $by_suggest[ $s ] ) && count( $by_suggest[ $s ] ) > 1 ) {
				$row['reasons'][] = 'dup_suggest';
				$row['reasons']   = array_values( array_unique( $row['reasons'] ) );
			}
			// Drop regenerable heavy fields.
			unset( $row['view_url'] );
		}
		unset( $row );

		usort(
			$rows,
			static function ( $a, $b ) {
				return ( (int) ( $a['score'] ?? 0 ) ) <=> ( (int) ( $b['score'] ?? 0 ) );
			}
		);

		$issues_total = count( $rows );
		$max_store    = 2000;
		if ( $issues_total > $max_store ) {
			$rows = array_slice( $rows, 0, $max_store );
		}

		$report['rows']         = $rows;
		$report['issues']       = $issues_total;
		$report['stored_rows']  = count( $rows );
		$report['complete']     = true;
		$report['finished_at']  = current_time( 'mysql' );
		update_option( self::full_report_option(), $report, false );
	}

	/**
	 * Health scan for published product slugs.
	 * Prefers completed full-catalog report when available.
	 *
	 * @param int $scan_limit   How many recent products to inspect (quick mode).
	 * @param int $return_limit Per-page size.
	 * @param int $page         1-based page.
	 * @return array{rows:array<int,array>,scanned:int,issues:int,skipped_410:int,source:string,page:int,per_page:int,pages:int,finished_at?:string,total?:int,complete?:bool,stored_rows?:int}
	 */
	public static function get_health_report( int $scan_limit = 400, int $return_limit = 100, int $page = 1 ): array {
		$return_limit = max( 20, min( 200, $return_limit ) );
		$page         = max( 1, $page );
		$stored       = self::get_stored_full_report();

		if ( ! empty( $stored['complete'] ) && isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) {
			$all     = self::sync_stored_health_rows( $stored['rows'] );
			$issues  = count( $all );
			$pages   = max( 1, (int) ceil( count( $all ) / $return_limit ) );
			$page    = min( $page, $pages );
			$offset  = ( $page - 1 ) * $return_limit;
			$slice   = array_slice( $all, $offset, $return_limit );

			// Refresh edit_url for current admin (stored may be stale).
			foreach ( $slice as &$row ) {
				$pid = (int) ( $row['product_id'] ?? 0 );
				if ( $pid > 0 ) {
					$row['edit_url'] = get_edit_post_link( $pid, 'raw' );
				}
			}
			unset( $row );

			return array(
				'rows'         => $slice,
				'scanned'      => (int) ( $stored['scanned'] ?? count( $all ) ),
				'issues'       => $issues,
				'skipped_410'  => (int) ( $stored['skipped_410'] ?? 0 ),
				'source'       => 'full',
				'finished_at'  => (string) ( $stored['finished_at'] ?? '' ),
				'total'        => (int) ( $stored['total'] ?? $stored['scanned'] ?? 0 ),
				'complete'     => true,
				'page'         => $page,
				'per_page'     => $return_limit,
				'pages'        => $pages,
				'stored_rows'  => count( $all ),
			);
		}

		global $wpdb;
		$scan_limit = max( 50, min( 1000, $scan_limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts}
				WHERE post_type = 'product' AND post_status = 'publish'
				ORDER BY ID DESC LIMIT %d",
				$scan_limit
			)
		);

		if ( ! is_array( $posts ) ) {
			$posts = array();
		}

		$gone_410    = self::get_410_product_map();
		$skipped_410 = 0;
		$by_suggest  = array();
		$rows        = array();

		foreach ( $posts as $p ) {
			$analyzed = self::analyze_product_health( (int) $p->ID, $gone_410 );
			if ( ! empty( $analyzed['skipped_410'] ) ) {
				++$skipped_410;
				continue;
			}
			if ( empty( $analyzed['row'] ) ) {
				continue;
			}
			$row = $analyzed['row'];
			unset( $row['view_url'] );
			$s = (string) $row['suggest'];
			if ( $s ) {
				if ( ! isset( $by_suggest[ $s ] ) ) {
					$by_suggest[ $s ] = array();
				}
				$by_suggest[ $s ][] = (int) $row['product_id'];
			}
			$rows[] = $row;
		}

		foreach ( $rows as &$row ) {
			$s = $row['suggest'];
			if ( $s && isset( $by_suggest[ $s ] ) && count( $by_suggest[ $s ] ) > 1 ) {
				$row['reasons'][] = 'dup_suggest';
				$row['reasons']   = array_values( array_unique( $row['reasons'] ) );
			}
		}
		unset( $row );

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['score'] <=> $b['score'];
			}
		);

		$issues = count( $rows );
		$pages  = max( 1, (int) ceil( $issues / $return_limit ) );
		$page   = min( $page, $pages );
		$offset = ( $page - 1 ) * $return_limit;

		return array(
			'rows'         => array_slice( $rows, $offset, $return_limit ),
			'scanned'      => count( $posts ),
			'issues'       => $issues,
			'skipped_410'  => $skipped_410,
			'source'       => 'quick',
			'complete'     => false,
			'total'        => count( $posts ),
			'page'         => $page,
			'per_page'     => $return_limit,
			'pages'        => $pages,
			'stored_rows'  => $issues,
		);
	}

	/**
	 * Reason labels for health UI.
	 *
	 * @param string $code Reason code.
	 */
	public static function reason_label( string $code ): string {
		$map = array(
			'persian'         => __( 'نامک فارسی', 'shojaei-seo-for-woo' ),
			'long'            => __( 'خیلی طولانی', 'shojaei-seo-for-woo' ),
			'low_score'       => __( 'امتیاز پایین', 'shojaei-seo-for-woo' ),
			'finglish_better' => __( 'پیشنهاد فینگلیش بهتر', 'shojaei-seo-for-woo' ),
			'dup_suggest'     => __( 'پیشنهاد تکراری با محصول دیگر', 'shojaei-seo-for-woo' ),
			'search'          => __( 'نتیجه جستجو', 'shojaei-seo-for-woo' ),
		);
		return $map[ $code ] ?? $code;
	}

	/**
	 * Preview or apply Finglish slug for one published product (creates 301 via post_updated).
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $dry_run    If true, only preview.
	 * @return array{ok:bool,message:string,old_slug?:string,new_slug?:string,old_url?:string,new_url?:string,redirect_id?:int,indexnow?:bool,loop_blocked?:bool}
	 */
	public static function apply_suggested_slug( int $product_id, bool $dry_run = true ): array {
		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return array(
				'ok'      => false,
				'message' => __( 'محصول منتشرشده یافت نشد.', 'shojaei-seo-for-woo' ),
			);
		}

		if ( self::is_410_product( $product_id ) ) {
			self::prune_health_report_ids( array( $product_id ) );
			return array(
				'ok'          => false,
				'skipped_410' => true,
				'product_id'  => $product_id,
				'title'       => (string) $post->post_title,
				'message'     => __( 'این محصول وضعیت ۴۱۰ Gone دارد؛ از فهرست سلامت حذف شد.', 'shojaei-seo-for-woo' ),
			);
		}

		$old_slug = (string) $post->post_name;
		$latin    = Damavand_Slug_Finglish::transliterate( (string) $post->post_title );
		if ( '' === $latin ) {
			return array(
				'ok'      => false,
				'message' => __( 'از عنوان نمی‌توان نامک لاتین ساخت.', 'shojaei-seo-for-woo' ),
			);
		}

		$new_slug = Damavand_Slug_Finglish::uniquify_slug( $latin, $product_id, 'product', 'publish', (int) $post->post_parent );
		if ( $new_slug === $old_slug ) {
			self::prune_health_report_ids( array( $product_id ) );
			return array(
				'ok'           => true,
				'already_done' => true,
				'message'      => __( 'نامک از قبل فینگلیش است؛ از فهرست سلامت حذف شد.', 'shojaei-seo-for-woo' ),
				'old_slug'     => $old_slug,
				'new_slug'     => $new_slug,
				'product_id'   => $product_id,
				'title'        => (string) $post->post_title,
			);
		}

		$old_url = (string) get_permalink( $product_id );
		$new_url_preview = Damavand_Slug_Redirects::swap_slug_in_url( $old_url, $old_slug, $new_slug );

		// Loop / chain check against OOS + slug redirects.
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) && $old_url && $new_url_preview ) {
			$valid = Shojaei_SEO_Redirect_Engine::validate_redirect( $old_url, $new_url_preview, $product_id );
			if ( is_wp_error( $valid ) ) {
				return array(
					'ok'           => false,
					'message'      => $valid->get_error_message(),
					'old_slug'     => $old_slug,
					'new_slug'     => $new_slug,
					'old_url'      => $old_url,
					'new_url'      => $new_url_preview,
					'loop_blocked' => true,
				);
			}
		}

		if ( $dry_run ) {
			return array(
				'ok'       => true,
				'message'  => __( 'پیش‌نمایش Dry-Run: با اعمال واقعی، نامک عوض و ۳۰۱ ساخته می‌شود.', 'shojaei-seo-for-woo' ),
				'old_slug' => $old_slug,
				'new_slug' => $new_slug,
				'old_url'  => $old_url,
				'new_url'  => $new_url_preview ?: '',
				'dry_run'  => true,
				'product_id' => $product_id,
				'title'    => (string) $post->post_title,
			);
		}

		$result = wp_update_post(
			array(
				'ID'        => $product_id,
				'post_name' => $new_slug,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => $result->get_error_message(),
			);
		}

		$new_url     = (string) get_permalink( $product_id );
		$redirect_id = Damavand_Slug_Redirects::latest_redirect_id_for_product( $product_id, $old_slug );

		// Health/admin apply promises a 301 even if auto-301 setting is off.
		if ( $redirect_id < 1 ) {
			$built_old = Damavand_Slug_Redirects::swap_slug_in_url( $new_url, $new_slug, $old_slug );
			if ( ! $built_old || $built_old === $new_url ) {
				$built_old = $old_url;
			}
			if ( $built_old && $new_url && $built_old !== $new_url ) {
				$redirect_id = Damavand_Slug_Redirects::save_redirect( $product_id, $old_slug, $built_old, $new_url, '301' );
				if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
					Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
				}
			}
			if ( $redirect_id < 1 ) {
				$redirect_id = Damavand_Slug_Redirects::latest_redirect_id_for_product( $product_id, $old_slug );
			}
		}

		$indexnow_queued = false;
		if ( class_exists( 'Shojaei_SEO_IndexNow' ) && $old_url && $new_url && $old_url !== $new_url ) {
			$q = Shojaei_SEO_IndexNow::queue_suggestion(
				$old_url,
				$new_url,
				array(
					'post_id' => $product_id,
					'title'   => (string) $post->post_title,
					'reason'  => __( 'اعمال نامک فینگلیش', 'shojaei-seo-for-woo' ),
					'source'  => 'slug_apply',
				)
			);
			$indexnow_queued = ! empty( $q['ok'] );
		}

		self::prune_health_report_ids( array( $product_id ) );

		update_post_meta(
			$product_id,
			'_shojaei_seo_last_slug_change',
			array(
				'old_slug'    => $old_slug,
				'new_slug'    => $new_slug,
				'old_url'     => $old_url,
				'new_url'     => $new_url,
				'redirect_id' => $redirect_id,
				'at'          => current_time( 'mysql' ),
			)
		);

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'slug_apply',
				sprintf(
					/* translators: 1: old slug, 2: new slug */
					__( 'اعمال فینگلیش: «%1$s» → «%2$s» — لینک قدیم ۳۰۱ شد', 'shojaei-seo-for-woo' ),
					$old_slug,
					$new_slug
				),
				$product_id,
				array(
					'redirect_id'      => $redirect_id,
					'indexnow_queued'  => $indexnow_queued,
				)
			);
		}

		$wp_covers = Damavand_Slug_Redirects::wp_old_slug_covers( $product_id, $old_slug );

		if ( $redirect_id > 0 ) {
			$msg = __( 'نامک اعمال شد. لینک قدیم با ریدایرکت ۳۰۱ به آدرس جدید می‌رود.', 'shojaei-seo-for-woo' );
			$redirect_notice = __( 'لینک قدیمی دیگر ۴۰۴ نمی‌شود — ۳۰۱ فعال است (تب ریدایرکت‌ها).', 'shojaei-seo-for-woo' );
		} elseif ( $wp_covers ) {
			$msg = __( 'نامک اعمال شد. ریدایرکت لینک قدیم توسط وردپرس (_wp_old_slug) پوشش داده می‌شود.', 'shojaei-seo-for-woo' );
			$redirect_notice = __( 'لینک قدیم ۴۰۴ نمی‌شود؛ هسته وردپرس ۳۰۱ می‌کند.', 'shojaei-seo-for-woo' );
		} else {
			$msg = __( 'نامک اعمال شد، اما ریدایرکت ۳۰۱ ثبت نشد. لینک قدیم ممکن است ۴۰۴ شود.', 'shojaei-seo-for-woo' );
			$redirect_notice = __( '۳۰۱ ساخته نشد — وضعیت را در تب ریدایرکت‌ها بررسی کنید.', 'shojaei-seo-for-woo' );
		}
		if ( $indexnow_queued ) {
			$msg .= ' ' . __( 'پیشنهاد IndexNow (آدرس قدیم/جدید) در صف تأیید قرار گرفت — از هسته سئو → نمایه‌سازی فوری تأیید کنید.', 'shojaei-seo-for-woo' );
		} else {
			$msg .= ' ' . __( 'IndexNow در صف نرفت؛ از هسته سئو → نمایه‌سازی فوری → «پیشنهاد از ریدایرکت‌های نامک» را بزنید.', 'shojaei-seo-for-woo' );
		}

		return array(
			'ok'              => true,
			'message'         => $msg,
			'old_slug'        => $old_slug,
			'new_slug'        => $new_slug,
			'old_url'         => $old_url,
			'new_url'         => $new_url,
			'redirect_id'     => $redirect_id,
			'indexnow'        => false,
			'indexnow_queued' => $indexnow_queued,
			'redirect_notice' => $redirect_notice,
			'dry_run'         => false,
			'product_id'      => $product_id,
			'title'           => (string) $post->post_title,
			'can_undo'        => $redirect_id > 0,
		);
	}

	/**
	 * Batch dry-run / apply (hard cap 20).
	 *
	 * @param int[] $product_ids IDs.
	 * @param bool  $dry_run     Dry-run.
	 * @return array{ok:bool,dry_run:bool,applied:int,failed:int,items:array}
	 */
	public static function batch_apply( array $product_ids, bool $dry_run = true ): array {
		$ids = array();
		foreach ( $product_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		$ids = array_slice( $ids, 0, 20 );

		$items        = array();
		$applied      = 0;
		$failed       = 0;
		$skipped_410  = 0;
		$gone_ids     = array();
		foreach ( $ids as $id ) {
			$r = self::apply_suggested_slug( $id, $dry_run );
			if ( empty( $r['product_id'] ) ) {
				$r['product_id'] = $id;
			}
			$items[] = $r;
			if ( ! empty( $r['skipped_410'] ) ) {
				++$skipped_410;
				$gone_ids[] = $id;
			} elseif ( ! empty( $r['ok'] ) ) {
				++$applied;
			} else {
				++$failed;
			}
		}
		if ( ! $dry_run && ! empty( $gone_ids ) ) {
			self::prune_health_report_ids( $gone_ids );
		}

		$msg_parts = array();
		if ( $dry_run ) {
			$msg_parts[] = sprintf(
				/* translators: 1: ready, 2: blocked */
				__( 'Dry-Run: %1$d آماده اعمال، %2$d ناموفق/مسدود.', 'shojaei-seo-for-woo' ),
				$applied,
				$failed + $skipped_410
			);
		} else {
			$msg_parts[] = sprintf(
				/* translators: %d: applied */
				__( '%d نامک اعمال شد.', 'shojaei-seo-for-woo' ),
				$applied
			);
			if ( $skipped_410 > 0 ) {
				$msg_parts[] = sprintf(
					/* translators: %d: 410 count */
					__( '%d محصول ۴۱۰ از فهرست حذف شد.', 'shojaei-seo-for-woo' ),
					$skipped_410
				);
			}
			if ( $failed > 0 ) {
				$msg_parts[] = sprintf(
					/* translators: %d: failed */
					__( '%d ناموفق.', 'shojaei-seo-for-woo' ),
					$failed
				);
			}
		}

		return array(
			'ok'          => true,
			'dry_run'     => $dry_run,
			'applied'     => $applied,
			'failed'      => $failed,
			'skipped_410' => $skipped_410,
			'total'       => count( $ids ),
			'items'       => $items,
			'message'     => implode( ' ', $msg_parts ),
		);
	}

	/**
	 * Undo a health/auto slug apply: restore old slug + deactivate 301.
	 *
	 * @param int $redirect_id Slug redirect row ID.
	 * @return array{ok:bool,message:string}
	 */
	public static function undo_slug_redirect( int $redirect_id ): array {
		global $wpdb;
		$table = Damavand_Slug_Redirects::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $redirect_id ) );
		if ( ! $row ) {
			return array(
				'ok'      => false,
				'message' => __( 'ریدایرکت یافت نشد.', 'shojaei-seo-for-woo' ),
			);
		}

		$product_id = (int) $row->product_id;
		$old_slug   = (string) $row->old_slug;
		$post       = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			Damavand_Slug_Redirects::set_redirect_active( $redirect_id, 0 );
			return array(
				'ok'      => false,
				'message' => __( 'محصول موجود نیست؛ فقط ریدایرکت غیرفعال شد.', 'shojaei-seo-for-woo' ),
			);
		}

		if ( '' === $old_slug ) {
			return array(
				'ok'      => false,
				'message' => __( 'نامک قدیم در رکورد نیست؛ Undo ممکن نیست.', 'shojaei-seo-for-woo' ),
			);
		}

		$unique = wp_unique_post_slug( $old_slug, $product_id, $post->post_status, 'product', (int) $post->post_parent );
		// Temporarily disable auto-301 to avoid reverse redirect chain noise.
		$was_301 = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_301', 'yes' );
		update_option( 'shojaei_seo_slug_auto_301', 'no' );

		$result = wp_update_post(
			array(
				'ID'        => $product_id,
				'post_name' => $unique,
			),
			true
		);

		update_option( 'shojaei_seo_slug_auto_301', $was_301 );
		Damavand_Slug_Redirects::set_redirect_active( $redirect_id, 0 );

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => $result->get_error_message(),
			);
		}

		delete_post_meta( $product_id, '_shojaei_seo_last_slug_change' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'slug_undo',
				sprintf(
					/* translators: %s: restored slug */
					__( 'Undo نامک — بازگشت به «%s» و غیرفعال‌سازی ۳۰۱', 'shojaei-seo-for-woo' ),
					$unique
				),
				$product_id,
				array( 'redirect_id' => $redirect_id )
			);
		}

		return array(
			'ok'       => true,
			'message'  => __( 'نامک برگردانده و ریدایرکت ۳۰۱ غیرفعال شد.', 'shojaei-seo-for-woo' ),
			'old_slug' => (string) $post->post_name,
			'new_slug' => $unique,
			'product_id' => $product_id,
		);
	}

	/**
	 * Search published products for slug tools UI.
	 *
	 * @param string $query Search term (title / ID / slug).
	 * @param int    $limit Max results.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_products_for_slug( string $query, int $limit = 20 ): array {
		$query = trim( wp_strip_all_tags( $query ) );
		$limit = max( 1, min( 50, $limit ) );
		if ( '' === $query ) {
			return array();
		}

		$ids = array();

		if ( ctype_digit( $query ) ) {
			$ids[] = absint( $query );
		} else {
			$q = new WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					's'                      => $query,
					'orderby'                => 'relevance',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$ids = array_map( 'absint', $q->posts );

			// Also match by Latin slug / partial post_name.
			global $wpdb;
			$like = '%' . $wpdb->esc_like( sanitize_title( $query ) ) . '%';
			if ( '%' !== $like ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$by_slug = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
						WHERE post_type = 'product' AND post_status = 'publish'
						AND post_name LIKE %s
						ORDER BY ID DESC
						LIMIT %d",
						$like,
						$limit
					)
				);
				if ( is_array( $by_slug ) ) {
					$ids = array_merge( $ids, array_map( 'absint', $by_slug ) );
				}
			}

			// Raw title LIKE for Persian titles WP_Query may miss under some configs.
			$like_title = '%' . $wpdb->esc_like( $query ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$by_title = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'product' AND post_status = 'publish'
					AND post_title LIKE %s
					ORDER BY ID DESC
					LIMIT %d",
					$like_title,
					$limit
				)
			);
			if ( is_array( $by_title ) ) {
				$ids = array_merge( $ids, array_map( 'absint', $by_title ) );
			}
		}

		$ids  = array_values( array_unique( array_filter( $ids ) ) );
		$ids  = array_slice( $ids, 0, $limit );
		$gone = self::get_410_product_map();
		$out  = array();

		foreach ( $ids as $product_id ) {
			$post = get_post( $product_id );
			if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$analyzed = self::analyze_product_health( $product_id, $gone );
			if ( ! empty( $analyzed['skipped_410'] ) ) {
				continue;
			}
			$row      = $analyzed['row'];
			if ( ! $row ) {
				$slug    = (string) $post->post_name;
				$suggest = Damavand_Slug_Finglish::transliterate( (string) $post->post_title );
				$score   = Damavand_Slug_Finglish::score_slug( $slug );
				$row     = array(
					'product_id'  => $product_id,
					'title'       => (string) $post->post_title,
					'slug'        => $slug,
					'suggest'     => $suggest,
					'score'       => (int) $score['score'],
					'reasons'     => array( 'search' ),
					'has_persian' => Damavand_Slug_Finglish::has_persian( $slug ) || Damavand_Slug_Finglish::has_persian( rawurldecode( $slug ) ) ? 1 : 0,
					'has_long'    => strlen( rawurldecode( $slug ) ) > 60 ? 1 : 0,
					'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
					'view_url'    => get_permalink( $product_id ),
					'healthy'     => empty( $analyzed['skipped_410'] ),
				);
			} else {
				$row['healthy'] = false;
			}
			$out[] = $row;
		}

		return $out;
	}

}
