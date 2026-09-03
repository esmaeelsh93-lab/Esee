<?php
/**
 * Local Page Value scoring — no external SEO APIs.
 *
 * Uses WooCommerce sales, reviews, Rank Math local meta (if present),
 * internal link signals, and a manual protect flag.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Page_Value
 */
class Shojaei_SEO_Page_Value {

	public const META_SCORE     = '_shojaei_seo_page_value';
	public const META_SCORE_AT  = '_shojaei_seo_page_value_at';
	public const META_PROTECTED = '_shojaei_seo_page_protected';

	/**
	 * Whether page-value gate is enabled.
	 */
	public static function is_enabled(): bool {
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_page_value_enabled', 'yes' );
	}

	/**
	 * Score threshold that requires manual confirmation.
	 */
	public static function threshold(): int {
		return max( 1, min( 100, (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_page_value_threshold', 60 ) ) );
	}

	/**
	 * Manual protect flag.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function is_protected( int $product_id ): bool {
		return 'yes' === get_post_meta( $product_id, self::META_PROTECTED, true );
	}

	/**
	 * Set / clear protect flag.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $protect    Protect.
	 */
	public static function set_protected( int $product_id, bool $protect ): void {
		if ( $protect ) {
			update_post_meta( $product_id, self::META_PROTECTED, 'yes' );
		} else {
			delete_post_meta( $product_id, self::META_PROTECTED );
		}
	}

	/**
	 * Whether redirect/410 must be confirmed by admin.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function requires_manual( int $product_id ): bool {
		if ( ! self::is_enabled() ) {
			return self::is_protected( $product_id );
		}

		if ( self::is_protected( $product_id ) ) {
			return true;
		}

		$score = self::get_score( $product_id );
		return $score >= self::threshold();
	}

	/**
	 * Get cached or fresh score (0–100).
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $fresh      Force recalculate.
	 */
	public static function get_score( int $product_id, bool $fresh = false ): int {
		if ( ! $fresh ) {
			$cached = get_post_meta( $product_id, self::META_SCORE, true );
			$at     = get_post_meta( $product_id, self::META_SCORE_AT, true );
			if ( '' !== $cached && $at && ( time() - strtotime( (string) $at ) ) < DAY_IN_SECONDS ) {
				return max( 0, min( 100, (int) $cached ) );
			}
		}

		$result = self::calculate( $product_id );
		update_post_meta( $product_id, self::META_SCORE, $result['score'] );
		update_post_meta( $product_id, self::META_SCORE_AT, current_time( 'mysql' ) );

		return $result['score'];
	}

	/**
	 * Full breakdown for UI / diagnose.
	 *
	 * @param int $product_id Product ID.
	 * @return array{score:int,level:string,signals:array,requires_manual:bool,protected:bool}
	 */
	public static function evaluate( int $product_id ): array {
		$result = self::calculate( $product_id );
		update_post_meta( $product_id, self::META_SCORE, $result['score'] );
		update_post_meta( $product_id, self::META_SCORE_AT, current_time( 'mysql' ) );

		$protected = self::is_protected( $product_id );
		$requires  = $protected || ( self::is_enabled() && $result['score'] >= self::threshold() );

		$level = 'low';
		if ( $result['score'] >= self::threshold() ) {
			$level = 'high';
		} elseif ( $result['score'] >= (int) round( self::threshold() * 0.6 ) ) {
			$level = 'medium';
		}

		return array(
			'score'            => $result['score'],
			'level'            => $level,
			'signals'          => $result['signals'],
			'requires_manual'  => $requires,
			'protected'        => $protected,
			'threshold'        => self::threshold(),
			'note'             => __( 'امتیاز محلی است (فروش، نظرات، سیگنال‌های سئو ذخیره‌شده، لینک داخلی) — بدون API گوگل.', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * Calculate score from local signals only.
	 *
	 * @param int $product_id Product ID.
	 * @return array{score:int,signals:array}
	 */
	private static function calculate( int $product_id ): array {
		$signals = array();
		$points  = 0;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array( 'score' => 0, 'signals' => array() );
		}

		// Sales volume (0–35).
		$sales = (int) $product->get_total_sales();
		$sales_pts = 0;
		if ( $sales >= 200 ) {
			$sales_pts = 35;
		} elseif ( $sales >= 50 ) {
			$sales_pts = 28;
		} elseif ( $sales >= 10 ) {
			$sales_pts = 18;
		} elseif ( $sales >= 1 ) {
			$sales_pts = 8;
		}
		$points += $sales_pts;
		$signals['sales'] = array(
			'label'  => __( 'فروش تجمعی', 'shojaei-seo-for-woo' ),
			'value'  => $sales,
			'points' => $sales_pts,
		);

		// Reviews (0–15).
		$rating_count = (int) $product->get_rating_count();
		$review_pts   = min( 15, $rating_count * 3 );
		$points      += $review_pts;
		$signals['reviews'] = array(
			'label'  => __( 'نظرات', 'shojaei-seo-for-woo' ),
			'value'  => $rating_count,
			'points' => $review_pts,
		);

		// Rank Math local SEO score if stored (0–25).
		$rm_score = 0;
		$rm_raw   = get_post_meta( $product_id, 'rank_math_seo_score', true );
		if ( '' === $rm_raw || false === $rm_raw ) {
			$rm_raw = get_post_meta( $product_id, 'rank_math_internal_links_processed', true );
			$rm_score_pts = 0;
		} else {
			$rm_score     = (int) $rm_raw;
			$rm_score_pts = (int) round( min( 100, max( 0, $rm_score ) ) * 0.25 );
		}
		// Focus keyword: Damavand first, then Rank Math / Yoast.
		$focus = '';
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$focus = Damavand_SEO_Meta::get_focus_keyword( $product_id );
		} else {
			$focus = (string) get_post_meta( $product_id, 'rank_math_focus_keyword', true );
		}
		if ( $focus && $rm_score_pts < 10 ) {
			$rm_score_pts = max( $rm_score_pts, 8 );
		}
		$points += $rm_score_pts;
		$signals['rank_math'] = array(
			'label'  => __( 'سیگنال سئوی محلی (Damavand / Rank Math)', 'shojaei-seo-for-woo' ),
			'value'  => $rm_score ?: ( $focus ? __( 'کلمه کلیدی دارد', 'shojaei-seo-for-woo' ) : 0 ),
			'points' => $rm_score_pts,
		);

		// Content length / description richness (0–10).
		$content  = (string) get_post_field( 'post_content', $product_id );
		$words    = Shojaei_SEO_Helpers::count_words( $content );
		$content_pts = 0;
		if ( $words >= 800 ) {
			$content_pts = 10;
		} elseif ( $words >= 300 ) {
			$content_pts = 6;
		} elseif ( $words >= 100 ) {
			$content_pts = 3;
		}
		$points += $content_pts;
		$signals['content'] = array(
			'label'  => __( 'غنای محتوا', 'shojaei-seo-for-woo' ),
			'value'  => $words,
			'points' => $content_pts,
		);

		// Incoming internal links from our link builder rules (0–15).
		$permalink = get_permalink( $product_id );
		$link_hits = 0;
		if ( $permalink ) {
			global $wpdb;
			$table = Shojaei_SEO_Helpers::links_table();
			$path  = wp_parse_url( $permalink, PHP_URL_PATH );
			if ( $path ) {
				$like = '%' . $wpdb->esc_like( $path ) . '%';
				$link_hits = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$table} WHERE is_active = 1 AND target_url LIKE %s",
						$like
					)
				);
			}
		}
		$link_pts = min( 15, $link_hits * 5 );
		$points  += $link_pts;
		$signals['internal_links'] = array(
			'label'  => __( 'لینک داخلی هدف‌دار', 'shojaei-seo-for-woo' ),
			'value'  => $link_hits,
			'points' => $link_pts,
		);

		$score = max( 0, min( 100, $points ) );

		return array(
			'score'   => $score,
			'signals' => $signals,
		);
	}
}
