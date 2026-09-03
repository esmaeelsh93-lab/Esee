<?php
/**
 * Redirect Engine — loop/chain guards + relevance scoring.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Redirect_Engine
 */
class Shojaei_SEO_Redirect_Engine {

	private const MAX_CHAIN_HOPS = 6;

	/**
	 * Normalize a URL for comparison (host + path, no trailing slash, lowercase).
	 *
	 * @param string $url URL.
	 */
	public static function normalize_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		// Relative → absolute.
		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) && empty( $parts['path'] ) ) {
			return untrailingslashit( strtolower( $url ) );
		}

		$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$path = isset( $parts['path'] ) ? untrailingslashit( strtolower( $parts['path'] ) ) : '';
		if ( '' === $path ) {
			$path = '/';
		}

		return $host . $path;
	}

	/**
	 * In-request redirect map cache (for batch runs).
	 *
	 * @var array<string,string>|null
	 */
	private static $map_cache = null;

	/**
	 * Whether map cache is active for the current request.
	 *
	 * @var bool
	 */
	private static $map_cache_enabled = false;

	/**
	 * Enable map cache for a batch job request.
	 */
	public static function begin_map_cache(): void {
		self::$map_cache_enabled = true;
		self::$map_cache         = null;
	}

	/**
	 * Disable and clear map cache.
	 */
	public static function end_map_cache(): void {
		self::$map_cache_enabled = false;
		self::$map_cache         = null;
	}

	/**
	 * Invalidate cached redirect map after a redirect change.
	 */
	public static function clear_redirect_map_cache(): void {
		self::$map_cache = null;
	}

	/**
	 * Map of normalized source URL → target URL from active redirects.
	 * Always request-cached (building the map hits permalinks for every redirected row).
	 *
	 * @return array<string,string>
	 */
	public static function get_active_redirect_map(): array {
		if ( null !== self::$map_cache ) {
			return self::$map_cache;
		}

		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();

		$rows = $wpdb->get_results(
			"SELECT product_id, target_url, redirect_type FROM {$table}
			WHERE status = 'redirected' AND redirect_type IN ('301','302') AND target_url != ''"
		);

		$map = array();
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$source = get_permalink( (int) $row->product_id );
				if ( ! $source ) {
					continue;
				}
				$from = self::normalize_url( $source );
				$to   = self::normalize_url( (string) $row->target_url );
				if ( $from && $to ) {
					$map[ $from ] = $to;
				}
			}
		}

		// Merge active product-slug redirects (name changes).
		if ( class_exists( 'Shojaei_SEO_Slug' ) ) {
			global $wpdb;
			$slug_table = Shojaei_SEO_Slug::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug_rows = $wpdb->get_results( "SELECT old_url, new_url FROM {$slug_table} WHERE is_active = 1 AND old_url != '' AND new_url != ''" );
			if ( ! empty( $slug_rows ) ) {
				foreach ( $slug_rows as $sr ) {
					$from = self::normalize_url( (string) $sr->old_url );
					$to   = self::normalize_url( (string) $sr->new_url );
					if ( $from && $to && ! isset( $map[ $from ] ) ) {
						$map[ $from ] = $to;
					}
				}
			}
		}

		// Merge manual redirects.
		if ( class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
			foreach ( Shojaei_SEO_Manual_Redirect::active_map() as $from_url => $to_url ) {
				$from = self::normalize_url( (string) $from_url );
				$to   = self::normalize_url( (string) $to_url );
				if ( $from && $to && ! isset( $map[ $from ] ) ) {
					$map[ $from ] = $to;
				}
			}
		}

		self::$map_cache = $map;
		return $map;
	}

	/**
	 * Validate that source → target does not create a loop or unsafe chain.
	 *
	 * @param string   $source_url  Source product URL.
	 * @param string   $target_url  Destination URL.
	 * @param int|null $exclude_id  Product being redirected (ignore its old mapping).
	 * @return true|WP_Error
	 */
	public static function validate_redirect( string $source_url, string $target_url, ?int $exclude_id = null ) {
		$source = self::normalize_url( $source_url );
		$target = self::normalize_url( $target_url );

		if ( ! $source || ! $target ) {
			return new WP_Error( 'invalid_url', __( 'آدرس مبدا یا مقصد نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		if ( $source === $target ) {
			return new WP_Error( 'self_redirect', __( 'ریدایرکت به همان آدرس مبدأ مجاز نیست.', 'shojaei-seo-for-woo' ) );
		}

		$map = self::get_active_redirect_map();

		// Ignore the product we are about to overwrite.
		if ( $exclude_id ) {
			$old = get_permalink( $exclude_id );
			if ( $old ) {
				unset( $map[ self::normalize_url( $old ) ] );
			}
		}

		// Walk from target following existing redirects.
		$seen    = array( $source => true );
		$current = $target;
		$hops    = 0;

		while ( isset( $map[ $current ] ) && $hops < self::MAX_CHAIN_HOPS ) {
			$next = $map[ $current ];
			$hops++;

			if ( isset( $seen[ $next ] ) || $next === $source ) {
				return new WP_Error(
					'redirect_loop',
					sprintf(
						/* translators: %d: hop count */
						__( 'این ریدایرکت به حلقه یا زنجیرهٔ خطرناک منجر می‌شود (پس از %d پرش).', 'shojaei-seo-for-woo' ),
						$hops
					),
					array(
						'source' => $source,
						'target' => $target,
						'hops'   => $hops,
					)
				);
			}

			$seen[ $current ] = true;
			$current          = $next;
		}

		if ( $hops >= self::MAX_CHAIN_HOPS ) {
			return new WP_Error(
				'redirect_chain',
				__( 'زنجیره ریدایرکت بیش از حد طولانی است. مقصد دیگری انتخاب کنید.', 'shojaei-seo-for-woo' )
			);
		}

		// Also reject if target already redirects away (soft chain warning as hard block for auto quality).
		if ( isset( $map[ $target ] ) ) {
			return new WP_Error(
				'redirect_chain',
				__( 'مقصد خودش ریدایرکت فعال دارد و زنجیره می‌سازد. یک URL نهایی انتخاب کنید.', 'shojaei-seo-for-woo' ),
				array( 'next' => $map[ $target ] )
			);
		}

		return true;
	}

	/**
	 * Relevance score between two products (0–100) with breakdown.
	 *
	 * Formula mixes title, tags, attributes, and price — not “first same category”.
	 *
	 * @param WC_Product $source Source.
	 * @param WC_Product $candidate Candidate.
	 * @return array{score:int,parts:array{title:int,tags:int,attributes:int,price:int}}
	 */
	public static function score_relevance( $source, $candidate ): array {
		$title = Shojaei_SEO_Helpers::title_similarity( $source->get_name(), $candidate->get_name() );
		$tags  = self::jaccard_term_ids(
			wp_get_post_terms( $source->get_id(), 'product_tag', array( 'fields' => 'ids' ) ),
			wp_get_post_terms( $candidate->get_id(), 'product_tag', array( 'fields' => 'ids' ) )
		);
		$attrs = self::attribute_similarity( $source, $candidate );
		$price = self::price_similarity( $source, $candidate );

		// Weights: title 35, tags 25, attributes 25, price 15.
		$score = (int) round(
			( $title * 0.35 ) +
			( $tags * 0.25 ) +
			( $attrs * 0.25 ) +
			( $price * 0.15 )
		);

		return array(
			'score' => max( 0, min( 100, $score ) ),
			'parts' => array(
				'title'      => $title,
				'tags'       => $tags,
				'attributes' => $attrs,
				'price'      => $price,
			),
		);
	}

	/**
	 * Find best in-stock replacement by relevance (same category pool).
	 *
	 * @param WC_Product $product   Source product.
	 * @param int        $threshold Minimum composite score.
	 * @return array{id:int,score:int,parts:array,skipped_loops:int}
	 */
	public static function find_best_replacement( $product, int $threshold = 70 ): array {
		$empty = array(
			'id'            => 0,
			'score'         => 0,
			'parts'         => array(),
			'skipped_loops' => 0,
		);

		if ( ! $product ) {
			return $empty;
		}

		$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $empty;
		}

		$posts = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => 80,
			'post_status'    => 'publish',
			'post__not_in'   => array( $product->get_id() ),
			'meta_query'     => array(
				array( 'key' => '_stock_status', 'value' => 'instock' ),
			),
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $terms,
				),
			),
		) );

		if ( empty( $posts ) ) {
			return $empty;
		}

		$source_url     = get_permalink( $product->get_id() );
		$best_id        = 0;
		$best_score     = 0;
		$best_parts     = array();
		$skipped_loops  = 0;

		foreach ( $posts as $post ) {
			$candidate = wc_get_product( $post->ID );
			if ( ! $candidate || ! $candidate->is_in_stock() ) {
				continue;
			}

			// Skip variable parents that are fully OOS edge cases already filtered by meta.
			$target_url = get_permalink( $post->ID );
			$valid      = self::validate_redirect( (string) $source_url, (string) $target_url, (int) $product->get_id() );
			if ( is_wp_error( $valid ) ) {
				$skipped_loops++;
				continue;
			}

			$result = self::score_relevance( $product, $candidate );
			if ( $result['score'] >= $threshold && $result['score'] > $best_score ) {
				$best_score = $result['score'];
				$best_id    = (int) $post->ID;
				$best_parts = $result['parts'];
			}
		}

		return array(
			'id'            => $best_id,
			'score'         => $best_score,
			'parts'         => $best_parts,
			'skipped_loops' => $skipped_loops,
		);
	}

	/**
	 * Top in-stock replacements: score ≥ threshold first, then same-category fill.
	 *
	 * @param WC_Product $product   Source.
	 * @param int        $limit     Max products (default 5).
	 * @param int        $threshold Min similarity % (default from settings / 70).
	 * @return array<int,array{id:int,score:int,via:string}>
	 */
	public static function find_top_replacements( $product, int $limit = 5, int $threshold = 0 ): array {
		$limit = max( 1, min( 12, $limit ) );
		if ( $threshold < 1 ) {
			$threshold = max( 1, min( 100, (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_match_threshold', 70 ) ) );
		}
		if ( ! $product ) {
			return array();
		}

		$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 80,
				'post_status'    => 'publish',
				'post__not_in'   => array( $product->get_id() ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_stock_status',
						'value' => 'instock',
					),
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
				'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);

		if ( empty( $posts ) ) {
			return array();
		}

		$scored = array();
		foreach ( $posts as $post ) {
			$candidate = wc_get_product( $post->ID );
			if ( ! $candidate || ! $candidate->is_in_stock() ) {
				continue;
			}
			$result = self::score_relevance( $product, $candidate );
			$scored[] = array(
				'id'    => (int) $post->ID,
				'score' => (int) $result['score'],
			);
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);

		$out   = array();
		$seen  = array();
		foreach ( $scored as $row ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			if ( (int) $row['score'] < $threshold ) {
				continue;
			}
			$out[]           = array(
				'id'    => (int) $row['id'],
				'score' => (int) $row['score'],
				'via'   => 'similarity',
			);
			$seen[ $row['id'] ] = true;
		}

		// Fill from same category (already sorted by sales in original list).
		if ( count( $out ) < $limit ) {
			foreach ( $posts as $post ) {
				if ( count( $out ) >= $limit ) {
					break;
				}
				$id = (int) $post->ID;
				if ( isset( $seen[ $id ] ) ) {
					continue;
				}
				$candidate = wc_get_product( $id );
				if ( ! $candidate || ! $candidate->is_in_stock() ) {
					continue;
				}
				$score = 0;
				foreach ( $scored as $row ) {
					if ( (int) $row['id'] === $id ) {
						$score = (int) $row['score'];
						break;
					}
				}
				$out[]        = array(
					'id'    => $id,
					'score' => $score,
					'via'   => 'category',
				);
				$seen[ $id ] = true;
			}
		}

		return $out;
	}

	/**
	 * Jaccard similarity for term ID lists (0–100).
	 *
	 * @param array|WP_Error $a Term IDs.
	 * @param array|WP_Error $b Term IDs.
	 */
	private static function jaccard_term_ids( $a, $b ): int {
		if ( is_wp_error( $a ) || is_wp_error( $b ) ) {
			return 0;
		}

		$a = array_map( 'intval', (array) $a );
		$b = array_map( 'intval', (array) $b );

		if ( empty( $a ) && empty( $b ) ) {
			return 50; // Neutral when neither has tags.
		}
		if ( empty( $a ) || empty( $b ) ) {
			return 0;
		}

		$inter = count( array_intersect( $a, $b ) );
		$union = count( array_unique( array_merge( $a, $b ) ) );
		if ( $union < 1 ) {
			return 0;
		}

		return (int) round( ( $inter / $union ) * 100 );
	}

	/**
	 * Attribute similarity (taxonomy + custom attributes).
	 *
	 * @param WC_Product $a Product A.
	 * @param WC_Product $b Product B.
	 */
	private static function attribute_similarity( $a, $b ): int {
		$set_a = self::flatten_attributes( $a );
		$set_b = self::flatten_attributes( $b );

		if ( empty( $set_a ) && empty( $set_b ) ) {
			return 50;
		}
		if ( empty( $set_a ) || empty( $set_b ) ) {
			return 0;
		}

		$inter = count( array_intersect( $set_a, $set_b ) );
		$union = count( array_unique( array_merge( $set_a, $set_b ) ) );
		if ( $union < 1 ) {
			return 0;
		}

		return (int) round( ( $inter / $union ) * 100 );
	}

	/**
	 * Flatten product attributes to comparable tokens.
	 *
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	private static function flatten_attributes( $product ): array {
		$tokens = array();
		$attrs  = $product->get_attributes();

		foreach ( $attrs as $key => $attr ) {
			$name = is_object( $attr ) ? $attr->get_name() : (string) $key;

			if ( is_object( $attr ) && $attr->is_taxonomy() ) {
				$terms = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'slugs' ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $slug ) {
						$tokens[] = strtolower( $name . ':' . $slug );
					}
				}
				continue;
			}

			$options = is_object( $attr ) ? $attr->get_options() : (array) $attr;
			foreach ( (array) $options as $option ) {
				$tokens[] = strtolower( $name . ':' . sanitize_title( (string) $option ) );
			}
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Price closeness score (0–100).
	 *
	 * @param WC_Product $a Product A.
	 * @param WC_Product $b Product B.
	 */
	private static function price_similarity( $a, $b ): int {
		$pa = (float) $a->get_price();
		$pb = (float) $b->get_price();

		if ( $pa <= 0 && $pb <= 0 ) {
			return 50;
		}
		if ( $pa <= 0 || $pb <= 0 ) {
			return 0;
		}

		$max = max( $pa, $pb );
		$diff_ratio = abs( $pa - $pb ) / $max;

		// Within 10% → high; within 40% → medium; beyond → low.
		$score = (int) round( max( 0, 100 * ( 1 - min( 1, $diff_ratio / 0.5 ) ) ) );
		return $score;
	}
}
