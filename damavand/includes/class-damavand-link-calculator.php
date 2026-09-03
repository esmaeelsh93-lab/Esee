<?php
/**
 * Damavand Link Calculator — taxonomy affinity → link graph edges.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Link_Calculator
 */
final class Damavand_Link_Calculator {

	public const JOB_TYPE  = 'damavand_link_calc';
	public const CRON_HOOK = 'damavand_link_calc_daily';

	/**
	 * Register cron + job helpers.
	 */
	public static function register_hooks(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_enqueue' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Daily: queue a background calc if idle.
	 */
	public static function cron_enqueue(): void {
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			return;
		}
		if ( Shojaei_SEO_Jobs::has_active( self::JOB_TYPE ) ) {
			return;
		}
		self::start_scan();
	}

	/**
	 * Enqueue full-catalog link calculation.
	 *
	 * @return array{ok:bool,message:string,job_id?:string,total?:int}
	 */
	public static function start_scan(): array {
		if ( ! class_exists( 'Damavand_Link_Manager' ) || ! Damavand_Link_Manager::table_exists() ) {
			return array(
				'ok'      => false,
				'message' => __( 'جدول گراف لینک آماده نیست.', 'shojaei-seo-for-woo' ),
			);
		}
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'صف جاب در دسترس نیست.', 'shojaei-seo-for-woo' ),
			);
		}
		if ( Shojaei_SEO_Jobs::has_active( self::JOB_TYPE ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'محاسبه لینک همین حالا در حال اجراست.', 'shojaei-seo-for-woo' ),
			);
		}

		$ids = get_posts(
			array(
				'post_type'              => 'product',
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

		$job = Shojaei_SEO_Jobs::enqueue(
			self::JOB_TYPE,
			array( 'post_ids' => $ids ),
			array( 'total' => count( $ids ) )
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: count */
				__( 'محاسبه لینک هوشمند برای %d محصول در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
				count( $ids )
			),
			'job_id'  => $job,
			'total'   => count( $ids ),
		);
	}

	/**
	 * Process a chunk of source product IDs.
	 *
	 * @param int[] $ids  Source IDs.
	 * @param int   $max_targets Max edges per source.
	 * @return array{processed:int,saved:int}
	 */
	public static function process_ids( array $ids, int $max_targets = 5 ): array {
		$processed = 0;
		$saved     = 0;
		$max_targets = max( 1, min( 10, $max_targets ) );

		foreach ( $ids as $source_id ) {
			$source_id = absint( $source_id );
			if ( $source_id < 1 ) {
				continue;
			}
			++$processed;
			$saved += self::calculate_for_source( $source_id, $max_targets );
		}

		return array(
			'processed' => $processed,
			'saved'     => $saved,
		);
	}

	/**
	 * Calculate and upsert related_box edges for one source.
	 *
	 * @param int $source_id Source product ID.
	 * @param int $limit     Max targets.
	 * @return int Edges upserted.
	 */
	public static function calculate_for_source( int $source_id, int $limit = 5 ): int {
		if ( ! class_exists( 'Damavand_Link_Manager' ) ) {
			return 0;
		}
		if ( ! Damavand_Link_Manager::is_live_target( $source_id ) ) {
			Damavand_Link_Manager::purge_source( $source_id );
			return 0;
		}

		$candidates = self::find_candidates( $source_id, $limit * 4 );
		if ( empty( $candidates ) ) {
			return 0;
		}

		usort(
			$candidates,
			static function ( $a, $b ) {
				return ( $b['score'] <=> $a['score'] );
			}
		);

		$saved = 0;
		$n     = 0;
		foreach ( $candidates as $item ) {
			if ( $n >= $limit ) {
				break;
			}
			$target_id = (int) $item['target_id'];
			if ( ! Damavand_Link_Manager::is_live_target( $target_id ) ) {
				continue;
			}

			$status = Damavand_Link_Manager::STATUS_APPROVED;
			/**
			 * Auto-approve calculator edges (related box). Default true until Review UI ships.
			 *
			 * @param bool  $approve Approve.
			 * @param array $item    Candidate.
			 */
			if ( ! (bool) apply_filters( 'damavand_link_calc_auto_approve', true, $item ) ) {
				$status = Damavand_Link_Manager::STATUS_PENDING;
			}

			$id = Damavand_Link_Manager::upsert_edge(
				array(
					'source_id'       => $source_id,
					'target_id'       => $target_id,
					'anchor_text'     => $item['anchor'],
					'type'            => Damavand_Link_Manager::TYPE_RELATED_BOX,
					'status'          => $status,
					'relevance_score' => $item['score'],
					'context'         => $item['context'],
					'reason'          => $item['reason'],
				)
			);
			if ( $id > 0 ) {
				++$saved;
				++$n;
			}
		}

		Damavand_Link_Manager::bust_cache( $source_id );
		return $saved;
	}

	/**
	 * Find scored candidates via shared taxonomies.
	 *
	 * @param int $source_id Source.
	 * @param int $pool      Candidate pool size.
	 * @return array<int,array{target_id:int,score:float,reason:string,context:string,anchor:string}>
	 */
	private static function find_candidates( int $source_id, int $pool = 20 ): array {
		$cat_ids = self::term_ids( $source_id, 'product_cat' );
		if ( empty( $cat_ids ) ) {
			return array();
		}

		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => max( 10, min( 40, $pool ) ),
				'post__not_in'           => array_merge(
					array( $source_id ),
					class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_ids() : array()
				),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $cat_ids,
					),
				),
			)
		);

		$out = array();
		foreach ( (array) $q->posts as $tid ) {
			$tid = absint( $tid );
			if ( $tid < 1 ) {
				continue;
			}
			$scored = self::score_pair( $source_id, $tid );
			if ( null === $scored || $scored['score'] < 1 ) {
				continue;
			}
			$min = (float) apply_filters( 'damavand_link_calc_min_score', 25.0, $source_id, $tid );
			if ( $scored['score'] < $min ) {
				continue;
			}
			$out[] = $scored;
		}

		return $out;
	}

	/**
	 * Common Taxonomy Score + Persian reason.
	 *
	 * @param int $source_id Source.
	 * @param int $target_id Target.
	 * @return array{target_id:int,score:float,reason:string,context:string,anchor:string}|null
	 */
	public static function score_pair( int $source_id, int $target_id ): ?array {
		$shared_cats   = self::shared_terms( $source_id, $target_id, 'product_cat' );
		$brand_tax     = self::resolve_brand_taxonomy();
		$shared_brands = $brand_tax ? self::shared_terms( $source_id, $target_id, $brand_tax ) : array();

		$sim = class_exists( 'Damavand_Content_Analyzer' )
			? Damavand_Content_Analyzer::description_similarity( $source_id, $target_id )
			: 0.0;

		if ( empty( $shared_cats ) && empty( $shared_brands ) ) {
			if ( $sim < 0.12 ) {
				return null;
			}
			$score  = round( $sim * 85, 2 );
			$reason = __( 'شباهت توضیحات و کلمات مرتبط', 'shojaei-seo-for-woo' );
			$context = 'content_sim';
		} else {
			$score = 0.0;
			$score += count( $shared_cats ) * 28.0;
			$score += count( $shared_brands ) * 35.0;
			if ( $sim >= 0.08 ) {
				$score += $sim * 40.0;
			}
			$reason  = self::build_reason( $shared_cats, $shared_brands );
			$context = ! empty( $shared_cats ) && ! empty( $shared_brands )
				? 'cat_brand'
				: ( ! empty( $shared_brands ) ? 'brand' : 'product_cat' );
			if ( $sim >= 0.12 ) {
				$reason .= ' · ' . __( 'شباهت توضیحات', 'shojaei-seo-for-woo' );
			}
		}

		if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
			$src_rel = Damavand_Content_Analyzer::related_keywords_for_post( $source_id );
			$tgt_rel = Damavand_Content_Analyzer::related_keywords_for_post( $target_id );
			if ( ! empty( $src_rel ) && ! empty( $tgt_rel ) ) {
				$rel_shared = count(
					array_intersect(
						array_map( static fn( $s ) => mb_strtolower( (string) $s, 'UTF-8' ), $src_rel ),
						array_map( static fn( $s ) => mb_strtolower( (string) $s, 'UTF-8' ), $tgt_rel )
					)
				);
				if ( $rel_shared > 0 ) {
					$score += min( 25, $rel_shared * 12 );
				}
			}
		}

		// In-stock bonus.
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $target_id );
			if ( $product && $product->is_in_stock() ) {
				$score += 10.0;
			} elseif ( $product && ! $product->is_in_stock() ) {
				$score -= 15.0;
			}
		}

		$anchor = get_the_title( $target_id );
		if ( '' === trim( $anchor ) ) {
			$anchor = '#' . $target_id;
		}

		/**
		 * Filter calculator pair score payload.
		 *
		 * @param array $payload Payload.
		 * @param int   $source_id Source.
		 * @param int   $target_id Target.
		 */
		$payload = array(
			'target_id' => $target_id,
			'score'     => round( $score, 2 ),
			'reason'    => $reason,
			'context'   => $context,
			'anchor'    => $anchor,
		);

		return apply_filters( 'damavand_link_calc_score_pair', $payload, $source_id, $target_id );
	}

	/**
	 * Human-readable Persian reason from shared terms.
	 *
	 * @param WP_Term[] $cats    Shared categories.
	 * @param WP_Term[] $brands  Shared brands.
	 */
	private static function build_reason( array $cats, array $brands ): string {
		$cat_names   = array_values( array_filter( array_map( static function ( $t ) {
			return $t instanceof WP_Term ? $t->name : '';
		}, $cats ) ) );
		$brand_names = array_values( array_filter( array_map( static function ( $t ) {
			return $t instanceof WP_Term ? $t->name : '';
		}, $brands ) ) );

		if ( $cat_names && $brand_names ) {
			return sprintf(
				/* translators: 1: category name, 2: brand name */
				__( 'اشتراک در دسته «%1$s» و برند «%2$s»', 'shojaei-seo-for-woo' ),
				$cat_names[0],
				$brand_names[0]
			);
		}
		if ( $cat_names && count( $cat_names ) > 1 ) {
			return sprintf(
				/* translators: 1: cat, 2: cat */
				__( 'اشتراک در دسته‌های «%1$s» و «%2$s»', 'shojaei-seo-for-woo' ),
				$cat_names[0],
				$cat_names[1]
			);
		}
		if ( $cat_names ) {
			return sprintf(
				/* translators: %s: category */
				__( 'اشتراک در دسته «%s»', 'shojaei-seo-for-woo' ),
				$cat_names[0]
			);
		}
		if ( $brand_names ) {
			return sprintf(
				/* translators: %s: brand */
				__( 'اشتراک در برند «%s»', 'shojaei-seo-for-woo' ),
				$brand_names[0]
			);
		}
		return __( 'اشتراک تاکسونومی', 'shojaei-seo-for-woo' );
	}

	/**
	 * @param int    $post_id  Post.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	private static function term_ids( int $post_id, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $terms ) ) );
	}

	/**
	 * Shared term objects between two posts.
	 *
	 * @param int    $a Post A.
	 * @param int    $b Post B.
	 * @param string $taxonomy Taxonomy.
	 * @return WP_Term[]
	 */
	private static function shared_terms( int $a, int $b, string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$ta = wp_get_post_terms( $a, $taxonomy );
		$tb = wp_get_post_terms( $b, $taxonomy );
		if ( is_wp_error( $ta ) || is_wp_error( $tb ) || ! is_array( $ta ) || ! is_array( $tb ) ) {
			return array();
		}
		$map = array();
		foreach ( $tb as $t ) {
			if ( $t instanceof WP_Term ) {
				$map[ (int) $t->term_id ] = $t;
			}
		}
		$shared = array();
		foreach ( $ta as $t ) {
			if ( $t instanceof WP_Term && isset( $map[ (int) $t->term_id ] ) ) {
				$shared[] = $t;
			}
		}
		return $shared;
	}

	/**
	 * Active brand taxonomy slug if any.
	 */
	private static function resolve_brand_taxonomy(): string {
		foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				return $tax;
			}
		}
		return '';
	}
}
