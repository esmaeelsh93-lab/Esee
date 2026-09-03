<?php
/**
 * Impact report, operational health score, and store profiles.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Impact
 */
class Shojaei_SEO_Impact {

	public const OPTION_BASELINE = 'shojaei_seo_health_baseline';
	public const OPTION_PROFILE  = 'shojaei_seo_store_profile';

	/**
	 * Store profiles → timeline / policy presets.
	 *
	 * @return array<string,array>
	 */
	public static function profiles(): array {
		return array(
			'general' => array(
				'label'       => __( 'عمومی', 'shojaei-seo-for-woo' ),
				'description' => __( 'پیش‌فرض متعادل برای اکثر فروشگاه‌ها.', 'shojaei-seo-for-woo' ),
				'group'       => 'basic',
				'options'     => array(
					'shojaei_seo_oos_message_day'        => 15,
					'shojaei_seo_oos_temp_days'          => 30,
					'shojaei_seo_oos_auto_day'           => 45,
					'shojaei_seo_oos_phase1_days'        => 15,
					'shojaei_seo_oos_phase2_days'        => 30,
					'shojaei_seo_oos_phase3_days'        => 45,
					'shojaei_seo_oos_auto_redirect_type' => '302',
					'shojaei_seo_oos_match_threshold'    => 70,
					'shojaei_seo_oos_dry_run'            => 'yes',
					'shojaei_seo_event_driven'           => 'yes',
					'shojaei_seo_schema_respect_seo_plugins' => 'yes',
				),
			),
			'fashion' => array(
				'label'       => __( 'مد و پوشاک', 'shojaei-seo-for-woo' ),
				'description' => __( 'فصلی — آستانه کوتاه‌تر، ریدایرکت موقت (۳۰۲) زودتر.', 'shojaei-seo-for-woo' ),
				'group'       => 'retail',
				'options'     => array(
					'shojaei_seo_oos_message_day'        => 10,
					'shojaei_seo_oos_temp_days'          => 21,
					'shojaei_seo_oos_auto_day'           => 35,
					'shojaei_seo_oos_phase1_days'        => 10,
					'shojaei_seo_oos_phase2_days'        => 21,
					'shojaei_seo_oos_phase3_days'        => 35,
					'shojaei_seo_oos_auto_redirect_type' => '302',
					'shojaei_seo_oos_match_threshold'    => 65,
					'shojaei_seo_oos_dry_run'            => 'yes',
					'shojaei_seo_event_driven'           => 'yes',
					'shojaei_seo_schema_respect_seo_plugins' => 'yes',
				),
			),
			'beauty' => array(
				'label'       => __( 'آرایشی و بهداشتی', 'shojaei-seo-for-woo' ),
				'description' => __( 'گردش موجودی متوسط؛ جایگزین هم‌برند مهم است.', 'shojaei-seo-for-woo' ),
				'group'       => 'retail',
				'options'     => array(
					'shojaei_seo_oos_message_day'        => 12,
					'shojaei_seo_oos_temp_days'          => 28,
					'shojaei_seo_oos_auto_day'           => 42,
					'shojaei_seo_oos_phase1_days'        => 12,
					'shojaei_seo_oos_phase2_days'        => 28,
					'shojaei_seo_oos_phase3_days'        => 42,
					'shojaei_seo_oos_auto_redirect_type' => '302',
					'shojaei_seo_oos_match_threshold'    => 72,
					'shojaei_seo_oos_dry_run'            => 'yes',
					'shojaei_seo_event_driven'           => 'yes',
					'shojaei_seo_schema_respect_seo_plugins' => 'yes',
				),
			),
			'digital' => array(
				'label'       => __( 'کالای دیجیتال', 'shojaei-seo-for-woo' ),
				'description' => __( 'مدل‌ها سریع عوض می‌شوند؛ آستانه کمی کوتاه‌تر از عمومی.', 'shojaei-seo-for-woo' ),
				'group'       => 'tech',
				'options'     => array(
					'shojaei_seo_oos_message_day'        => 12,
					'shojaei_seo_oos_temp_days'          => 25,
					'shojaei_seo_oos_auto_day'           => 40,
					'shojaei_seo_oos_phase1_days'        => 12,
					'shojaei_seo_oos_phase2_days'        => 25,
					'shojaei_seo_oos_phase3_days'        => 40,
					'shojaei_seo_oos_auto_redirect_type' => '302',
					'shojaei_seo_oos_match_threshold'    => 68,
					'shojaei_seo_oos_dry_run'            => 'yes',
					'shojaei_seo_event_driven'           => 'yes',
					'shojaei_seo_schema_respect_seo_plugins' => 'yes',
				),
			),
			'electronics' => array(
				'label'       => __( 'الکترونیک / قطعات', 'shojaei-seo-for-woo' ),
				'description' => __( 'آستانه بلندتر، سخت‌گیری بیشتر روی جایگزین، ۴۱۰ دیرتر.', 'shojaei-seo-for-woo' ),
				'group'       => 'tech',
				'options'     => array(
					'shojaei_seo_oos_message_day'        => 20,
					'shojaei_seo_oos_temp_days'          => 45,
					'shojaei_seo_oos_auto_day'           => 75,
					'shojaei_seo_oos_phase1_days'        => 20,
					'shojaei_seo_oos_phase2_days'        => 45,
					'shojaei_seo_oos_phase3_days'        => 75,
					'shojaei_seo_oos_auto_redirect_type' => '302',
					'shojaei_seo_oos_match_threshold'    => 80,
					'shojaei_seo_oos_dry_run'            => 'yes',
					'shojaei_seo_event_driven'           => 'yes',
					'shojaei_seo_schema_respect_seo_plugins' => 'yes',
				),
			),
			'downloads' => array(
				'label'       => __( 'فروش فایل و کتاب', 'shojaei-seo-for-woo' ),
				'description' => __( 'ناموجودی معمولاً موقت/مجوزی است؛ ریدایرکت دیرتر و محافظه‌کارانه.', 'shojaei-seo-for-woo' ),
				'group'       => 'digital',
				'options'     => array(
					'shojaei_seo_oos_message_day'        => 20,
					'shojaei_seo_oos_temp_days'          => 40,
					'shojaei_seo_oos_auto_day'           => 90,
					'shojaei_seo_oos_phase1_days'        => 20,
					'shojaei_seo_oos_phase2_days'        => 40,
					'shojaei_seo_oos_phase3_days'        => 90,
					'shojaei_seo_oos_auto_redirect_type' => '302',
					'shojaei_seo_oos_match_threshold'    => 75,
					'shojaei_seo_oos_dry_run'            => 'yes',
					'shojaei_seo_event_driven'           => 'yes',
					'shojaei_seo_schema_respect_seo_plugins' => 'yes',
				),
			),
		);
	}

	/**
	 * Apply a store profile.
	 *
	 * @param string $profile_id Profile key.
	 * @return bool
	 */
	public static function apply_profile( string $profile_id ): bool {
		$profiles = self::profiles();
		if ( ! isset( $profiles[ $profile_id ] ) ) {
			return false;
		}
		foreach ( $profiles[ $profile_id ]['options'] as $key => $value ) {
			update_option( $key, is_int( $value ) ? (string) $value : $value );
		}
		update_option( self::OPTION_PROFILE, $profile_id, false );
		return true;
	}

	/**
	 * Current profile id.
	 */
	public static function current_profile(): string {
		$id = (string) get_option( self::OPTION_PROFILE, 'general' );
		return isset( self::profiles()[ $id ] ) ? $id : 'general';
	}

	/**
	 * Lifetime / current operational counters from DB + options.
	 *
	 * @return array<string,int>
	 */
	public static function lifetime_counts(): array {
		global $wpdb;

		$oos_table = Shojaei_SEO_Helpers::oos_table();
		$log_table = Shojaei_SEO_Helpers::redirect_log_table();

		$count_type = static function ( string $type ) use ( $wpdb, $log_table ): int {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$log_table}
					WHERE is_undone = 0 AND reason != 'undo' AND redirect_type = %s",
					$type
				)
			);
		};

		$active_301 = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status = 'redirected' AND redirect_type = '301'"
		);
		$active_302 = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status = 'redirected' AND redirect_type = '302'"
		);
		$active_410 = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status = 'redirected' AND redirect_type = '410'"
		);

		$oos_open = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status != 'redirected'"
		);
		$candidates = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status = 'candidate_redirect'"
		);
		$manual = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status = 'needs_manual'"
		);

		$timeline = Shojaei_SEO_Helpers::get_oos_timeline();
		$temp     = (int) $timeline['temp_days'];
		$over = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$oos_table}
				WHERE status NOT IN ('redirected') AND days_oos >= %d",
				$temp
			)
		);

		$noindex = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				'_shojaei_seo_noindex',
				'yes'
			)
		);

		$links = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_stats_links_built', 0 );
		$gsc   = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_stats_gsc_indexed', 0 );
		$indexed_today = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_stats_indexed_today', 0 );

		return array(
			'redirect_301_log'  => $count_type( '301' ),
			'redirect_302_log'  => $count_type( '302' ),
			'redirect_410_log'  => $count_type( '410' ),
			'active_301'        => $active_301,
			'active_302'        => $active_302,
			'active_410'        => $active_410,
			'oos_open'          => $oos_open,
			'candidates'        => $candidates,
			'manual'            => $manual,
			'over_threshold'    => $over,
			'noindex'           => $noindex,
			'links_built'       => $links,
			'gsc_indexed'       => $gsc,
			'indexed_today'     => $indexed_today,
		);
	}

	/**
	 * Compute operational health score (0–100) with factor breakdown.
	 *
	 * @return array{score:int,factors:array,tone:string,summary:string}
	 */
	public static function compute_health(): array {
		$c = self::lifetime_counts();
		return self::health_from_counts( $c );
	}

	/**
	 * Cached health for dashboard (avoids many COUNTs on every page open).
	 *
	 * @param int $ttl Seconds.
	 * @return array{score:int,factors:array,tone:string,summary:string}
	 */
	public static function compute_health_cached( int $ttl = 600 ): array {
		$cached = get_transient( 'shojaei_seo_health_board' );
		if ( is_array( $cached ) && isset( $cached['score'] ) ) {
			return $cached;
		}
		$health = self::compute_health();
		set_transient( 'shojaei_seo_health_board', $health, max( 60, $ttl ) );
		return $health;
	}

	/**
	 * Build health payload from lifetime counters.
	 *
	 * @param array<string,int> $c Counts.
	 * @return array{score:int,factors:array,tone:string,summary:string}
	 */
	private static function health_from_counts( array $c ): array {
		$score = 55; // Neutral mid baseline for a fresh store.
		$factors = array();

		$decision_pool = max( 1, (int) $c['over_threshold'] + (int) $c['active_301'] + (int) $c['active_302'] + (int) $c['active_410'] );
		$resolved      = (int) $c['active_301'] + (int) $c['active_302'] + (int) $c['active_410'];
		$ratio         = $resolved / $decision_pool;
		$ratio_pts     = (int) round( min( 25, $ratio * 25 ) );
		$score        += $ratio_pts;
		$factors[]     = array(
			'label'  => __( 'نسبت تصمیم‌گرفته به OOS قدیمی', 'shojaei-seo-for-woo' ),
			'delta'  => $ratio_pts,
			'detail' => sprintf( '%d / %d', $resolved, $decision_pool ),
		);

		$open_penalty = min( 20, (int) floor( (int) $c['over_threshold'] / 5 ) );
		$score       -= $open_penalty;
		if ( $open_penalty > 0 ) {
			$factors[] = array(
				'label'  => __( 'OOS طولانی بدون تصمیم', 'shojaei-seo-for-woo' ),
				'delta'  => -$open_penalty,
				'detail' => (string) $c['over_threshold'],
			);
		}

		$manual_penalty = min( 15, (int) $c['manual'] * 2 );
		$score         -= $manual_penalty;
		if ( $manual_penalty > 0 ) {
			$factors[] = array(
				'label'  => __( 'نیاز به تایید دستی', 'shojaei-seo-for-woo' ),
				'delta'  => -$manual_penalty,
				'detail' => (string) $c['manual'],
			);
		}

		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ) ) {
			$score    += 5;
			$factors[] = array(
				'label'  => __( 'Dry-Run فعال (ایمن)', 'shojaei-seo-for-woo' ),
				'delta'  => 5,
				'detail' => 'ON',
			);
		}

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$score    += 4;
			$factors[] = array(
				'label'  => __( 'Undo واقعی در دسترس', 'shojaei-seo-for-woo' ),
				'delta'  => 4,
				'detail' => 'OK',
			);
		}

		if ( class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::respect_external_seo() ) {
			$score    += 4;
			$factors[] = array(
				'label'  => __( 'احترام به افزونه SEO', 'shojaei-seo-for-woo' ),
				'delta'  => 4,
				'detail' => class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_labels() : '',
			);
		}

		$schema_conflict = false;
		if ( class_exists( 'Shojaei_SEO_Schema_Detector' ) ) {
			$last = Shojaei_SEO_Schema_Detector::get_last_scan();
			if ( ! empty( $last['has_conflict'] ) ) {
				$schema_conflict = true;
				$score          -= 10;
				$factors[]       = array(
					'label'  => __( 'تداخل اسکیما شناسایی‌شده', 'shojaei-seo-for-woo' ),
					'delta'  => -10,
					'detail' => (string) ( $last['url'] ?? '' ),
				);
			}
		}

		if ( class_exists( 'Shojaei_SEO_GSC' ) && Shojaei_SEO_GSC::is_connected() ) {
			$score    += 6;
			$factors[] = array(
				'label'  => __( 'اتصال GSC', 'shojaei-seo-for-woo' ),
				'delta'  => 6,
				'detail' => 'connected',
			);
			if ( (int) $c['gsc_indexed'] > 0 ) {
				$bonus     = min( 6, (int) floor( (int) $c['gsc_indexed'] / 10 ) );
				$score    += $bonus;
				$factors[] = array(
					'label'  => __( 'درخواست‌های ایندکس ارسال‌شده', 'shojaei-seo-for-woo' ),
					'delta'  => $bonus,
					'detail' => (string) $c['gsc_indexed'],
				);
			}
		}

		if ( (int) $c['links_built'] > 0 ) {
			$bonus     = min( 5, (int) floor( (int) $c['links_built'] / 50 ) );
			$score    += $bonus;
			if ( $bonus > 0 ) {
				$factors[] = array(
					'label'  => __( 'لینک‌سازی بازیابی', 'shojaei-seo-for-woo' ),
					'delta'  => $bonus,
					'detail' => (string) $c['links_built'],
				);
			}
		}

		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			$failed = 0;
			if ( method_exists( 'Shojaei_SEO_Jobs', 'count_by_status' ) ) {
				$failed = (int) Shojaei_SEO_Jobs::count_by_status( Shojaei_SEO_Jobs::STATUS_FAILED );
			} else {
				global $wpdb;
				$table  = Shojaei_SEO_Jobs::table();
				$failed = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE status = %s AND updated_at >= %s",
						Shojaei_SEO_Jobs::STATUS_FAILED,
						gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS )
					)
				);
			}
			if ( $failed > 0 ) {
				$pen       = min( 12, $failed * 3 );
				$score    -= $pen;
				$factors[] = array(
					'label'  => __( 'خطاهای Job اخیر', 'shojaei-seo-for-woo' ),
					'delta'  => -$pen,
					'detail' => (string) $failed,
				);
			}
		}

		$score = max( 5, min( 98, $score ) );

		usort(
			$factors,
			static function ( $a, $b ) {
				return abs( (int) $b['delta'] ) <=> abs( (int) $a['delta'] );
			}
		);

		$tone = 'safe';
		if ( $score < 45 ) {
			$tone = 'error';
		} elseif ( $score < 65 ) {
			$tone = 'warning';
		} elseif ( $score < 80 ) {
			$tone = 'action';
		}

		$summary = sprintf(
			/* translators: %d: score */
			__( 'سلامت عملیات موجودی/ایندکس‌پذیری حدود %d٪ است (رتبه گوگل نیست).', 'shojaei-seo-for-woo' ),
			$score
		);

		return array(
			'score'           => $score,
			'factors'         => array_slice( $factors, 0, 6 ),
			'tone'            => $tone,
			'summary'         => $summary,
			'schema_conflict' => $schema_conflict,
			'counts'          => $c,
		);
	}

	/**
	 * Ensure baseline snapshot exists (first activate / upgrade).
	 */
	public static function maybe_capture_baseline(): void {
		$existing = get_option( self::OPTION_BASELINE, null );
		if ( is_array( $existing ) && isset( $existing['score'] ) ) {
			return;
		}
		self::capture_baseline();
	}

	/**
	 * Store baseline health (force).
	 */
	public static function capture_baseline(): void {
		$health = self::compute_health();
		update_option(
			self::OPTION_BASELINE,
			array(
				'score'      => (int) $health['score'],
				'captured_at'=> current_time( 'mysql' ),
				'counts'     => $health['counts'],
				'factors'    => $health['factors'],
				'note'       => __( 'Snapshot اول نصب/ارتقاء — مبنای «قبل»', 'shojaei-seo-for-woo' ),
			),
			false
		);
	}

	/**
	 * Get baseline or null.
	 *
	 * @return array|null
	 */
	public static function get_baseline(): ?array {
		$b = get_option( self::OPTION_BASELINE, null );
		return is_array( $b ) && isset( $b['score'] ) ? $b : null;
	}

	/**
	 * Full report for the Impact tab.
	 *
	 * @return array
	 */
	public static function get_report(): array {
		$health   = self::compute_health();
		$baseline = self::get_baseline();
		$counts   = $health['counts'];
		$trend    = class_exists( 'Shojaei_SEO_Analytics' )
			? Shojaei_SEO_Analytics::get_trend( 30 )
			: array( 'labels' => array(), 'oos' => array(), 'redirects' => array(), 'gone_410' => array(), 'links' => array(), 'candidates' => array() );

		$before = $baseline ? (int) $baseline['score'] : null;
		$after  = (int) $health['score'];
		$delta  = ( null !== $before ) ? ( $after - $before ) : null;

		$story = sprintf(
			/* translators: 1: 301 2: 302 3: 410 4: noindex 5: gsc */
			__( 'از شروع کار افزونه: %1$d ریدایرکت ۳۰۱ · %2$d ریدایرکت ۳۰۲ · %3$d وضعیت ۴۱۰ · %4$d صفحه noindex · %5$d درخواست ایندکس GSC.', 'shojaei-seo-for-woo' ),
			(int) $counts['active_301'] ?: (int) $counts['redirect_301_log'],
			(int) $counts['active_302'] ?: (int) $counts['redirect_302_log'],
			(int) $counts['active_410'] ?: (int) $counts['redirect_410_log'],
			(int) $counts['noindex'],
			(int) $counts['gsc_indexed']
		);

		// Prefer active redirects for donut; fall back to log lifetime.
		$donut = array(
			'301' => max( (int) $counts['active_301'], 0 ),
			'302' => max( (int) $counts['active_302'], 0 ),
			'410' => max( (int) $counts['active_410'], 0 ),
		);
		if ( 0 === array_sum( $donut ) ) {
			$donut = array(
				'301' => (int) $counts['redirect_301_log'],
				'302' => (int) $counts['redirect_302_log'],
				'410' => (int) $counts['redirect_410_log'],
			);
		}

		return array(
			'health'    => $health,
			'baseline'  => $baseline,
			'before'    => $before,
			'after'     => $after,
			'delta'     => $delta,
			'story'     => $story,
			'donut'     => $donut,
			'trend'     => $trend,
			'profile'   => self::current_profile(),
			'gsc'       => self::gsc_impact_rows( 8 ),
			'disclaimer'=> __( 'این درصد رتبه گوگل نیست؛ میزان نظم عملیات موجودی، ریدایرکت و ایندکس‌پذیری است.', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * Light GSC impact rows for recent redirected products.
	 *
	 * @param int $limit Limit.
	 * @return array{connected:bool,rows:array,message:string}
	 */
	public static function gsc_impact_rows( int $limit = 8 ): array {
		$connected = class_exists( 'Shojaei_SEO_GSC' ) && Shojaei_SEO_GSC::is_connected();
		if ( ! $connected ) {
			return array(
				'connected' => false,
				'rows'      => array(),
				'message'   => __( 'برای اثر ایندکس واقعی‌تر، Google Search Console را وصل کنید.', 'shojaei-seo-for-woo' ),
			);
		}

		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, redirect_type, target_url FROM {$table}
				WHERE status = 'redirected'
				ORDER BY id DESC
				LIMIT %d",
				max( 1, min( 20, $limit ) )
			)
		);

		$out = array();
		foreach ( $rows ?: array() as $row ) {
			$pid = (int) $row->product_id;
			$url = get_permalink( $pid );
			$out[] = array(
				'product_id'    => $pid,
				'title'         => get_the_title( $pid ),
				'url'           => $url,
				'redirect_type' => (string) $row->redirect_type,
				'target_url'    => (string) ( $row->target_url ?? '' ),
				'status'        => __( 'صف ایندکس / بازرسی در دسترس', 'shojaei-seo-for-woo' ),
			);
		}

		return array(
			'connected' => true,
			'rows'      => $out,
			'message'   => __( 'آخرین محصولات ریدایرکت‌شده — برای بازرسی دقیق از GSC URL Inspection استفاده کنید.', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * SVG donut for redirect mix (no CDN).
	 *
	 * @param array $parts Keys 301/302/410 => int.
	 * @param int   $size  Diameter.
	 * @return string
	 */
	public static function render_donut( array $parts, int $size = 180 ): string {
		$total = max( 0, (int) ( $parts['301'] ?? 0 ) + (int) ( $parts['302'] ?? 0 ) + (int) ( $parts['410'] ?? 0 ) );
		$cx    = $size / 2;
		$cy    = $size / 2;
		$r     = ( $size / 2 ) - 14;
		$stroke = 22;

		if ( $total < 1 ) {
			return sprintf(
				'<svg class="shojaei-donut" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img" aria-label="%2$s">
					<circle cx="%3$s" cy="%4$s" r="%5$s" fill="none" stroke="#e3eaf2" stroke-width="%6$s"/>
					<text x="%3$s" y="%4$s" text-anchor="middle" dominant-baseline="middle" font-size="14" fill="#90a4ae">0</text>
				</svg>',
				$size,
				esc_attr__( 'بدون ریدایرکت', 'shojaei-seo-for-woo' ),
				$cx,
				$cy,
				$r,
				$stroke
			);
		}

		$colors = array(
			'301' => '#1565c0',
			'302' => '#43a047',
			'410' => '#ef6c00',
		);
		$circumference = 2 * M_PI * $r;
		$offset        = 0;
		$arcs          = '';

		foreach ( array( '301', '302', '410' ) as $key ) {
			$val = (int) ( $parts[ $key ] ?? 0 );
			if ( $val < 1 ) {
				continue;
			}
			$frac = $val / $total;
			$len  = $frac * $circumference;
			$arcs .= sprintf(
				'<circle cx="%1$s" cy="%2$s" r="%3$s" fill="none" stroke="%4$s" stroke-width="%5$s" stroke-dasharray="%6$s %7$s" stroke-dashoffset="%8$s" transform="rotate(-90 %1$s %2$s)"><title>%9$s: %10$d</title></circle>',
				$cx,
				$cy,
				$r,
				esc_attr( $colors[ $key ] ),
				$stroke,
				esc_attr( (string) round( $len, 2 ) ),
				esc_attr( (string) round( $circumference - $len, 2 ) ),
				esc_attr( (string) round( -$offset, 2 ) ),
				esc_attr( $key ),
				$val
			);
			$offset += $len;
		}

		return sprintf(
			'<svg class="shojaei-donut" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" role="img" aria-label="%2$s">
				%3$s
				<text x="%4$s" y="%5$s" text-anchor="middle" dominant-baseline="middle" font-size="22" font-weight="700" fill="#1565c0">%6$d</text>
			</svg>',
			$size,
			esc_attr__( 'تفکیک ریدایرکت', 'shojaei-seo-for-woo' ),
			$arcs,
			$cx,
			$cy,
			$total
		);
	}

	/**
	 * Horizontal bar for health before/after.
	 *
	 * @param int $score Score 0-100.
	 * @param string $label Label.
	 * @param string $tone Tone class.
	 * @return string
	 */
	public static function render_score_bar( int $score, string $label, string $tone = 'safe' ): string {
		$score = max( 0, min( 100, $score ) );
		return sprintf(
			'<div class="shojaei-score-bar shojaei-tone-%1$s">
				<div class="shojaei-score-bar-meta"><span>%2$s</span><strong>%3$d%%</strong></div>
				<div class="shojaei-score-bar-track"><span style="width:%3$d%%"></span></div>
			</div>',
			esc_attr( $tone ),
			esc_html( $label ),
			$score
		);
	}
}
