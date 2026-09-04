<?php
/**
 * Conservative rule-based internal linking policy (token similarity only).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Link_Rules
 */
class Shojaei_SEO_Link_Rules {

	/**
	 * Built-in generic anchor blacklist (spam-prone).
	 *
	 * @return string[]
	 */
	public static function default_keyword_blacklist(): array {
		return array(
			'اینجا',
			'لینک',
			'خرید',
			'مشاهده',
			'کلیک',
			'بیشتر',
			'here',
			'link',
			'buy',
			'click',
			'more',
			'view',
		);
	}

	/**
	 * Absolute max links per page (hard cap).
	 */
	public static function max_per_page(): int {
		$n = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_max_per_page', 5 );
		return max( 1, min( 20, $n ?: 5 ) );
	}

	/**
	 * Density: max links per 1000 words.
	 */
	public static function max_per_1000(): int {
		$n = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_max_per_1000', 3 );
		return max( 1, min( 10, $n ?: 3 ) );
	}

	/**
	 * Min word gap between links.
	 */
	public static function min_word_gap(): int {
		$n = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_min_word_gap', 200 );
		return max( 50, min( 1000, $n ?: 200 ) );
	}

	/**
	 * Whether whitelist-only mode is on.
	 */
	public static function whitelist_only(): bool {
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_link_whitelist_only', 'no' );
	}

	/**
	 * Parse multiline option into normalized strings.
	 *
	 * @param string $option_key Option.
	 * @return string[]
	 */
	public static function parse_list_option( string $option_key ): array {
		$raw = (string) Shojaei_SEO_Helpers::get_option( $option_key, '' );
		if ( '' === trim( $raw ) ) {
			return array();
		}
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		foreach ( $lines ?: array() as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			$out[] = self::normalize_token( $line );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Normalize keyword/URL token for comparisons.
	 *
	 * @param string $value Value.
	 */
	public static function normalize_token( string $value ): string {
		$value = trim( $value );
		if ( class_exists( 'Shojaei_SEO_Persian' ) ) {
			return Shojaei_SEO_Persian::normalize( $value );
		}
		return mb_strtolower( $value, 'UTF-8' );
	}

	/**
	 * Combined keyword blacklist (defaults + admin).
	 *
	 * @return string[]
	 */
	public static function keyword_blacklist(): array {
		$custom = self::parse_list_option( 'shojaei_seo_link_keyword_blacklist' );
		$base   = array_map( array( __CLASS__, 'normalize_token' ), self::default_keyword_blacklist() );
		return array_values( array_unique( array_merge( $base, $custom ) ) );
	}

	/**
	 * Keyword whitelist (normalized).
	 *
	 * @return string[]
	 */
	public static function keyword_whitelist(): array {
		return self::parse_list_option( 'shojaei_seo_link_keyword_whitelist' );
	}

	/**
	 * URL blacklist (normalized full URLs or path fragments).
	 *
	 * @return string[]
	 */
	public static function url_blacklist(): array {
		return self::parse_list_option( 'shojaei_seo_link_url_blacklist' );
	}

	/**
	 * URL whitelist.
	 *
	 * @return string[]
	 */
	public static function url_whitelist(): array {
		return self::parse_list_option( 'shojaei_seo_link_url_whitelist' );
	}

	/**
	 * Compute max allowed links for a page.
	 *
	 * @param int $word_count Word count.
	 */
	public static function max_allowed_for_content( int $word_count ): int {
		$page_cap = self::max_per_page();
		if ( $word_count < 40 ) {
			return 0;
		}

		$by_density = (int) floor( ( $word_count / 1000 ) * self::max_per_1000() );
		// Short product descriptions (< 1000 words) still get at least one link.
		if ( $by_density < 1 ) {
			$by_density = 1;
		}

		return min( $page_cap, $by_density );
	}

	/**
	 * Whether a keyword is allowed by list rules.
	 *
	 * @param string $keyword Keyword.
	 */
	public static function is_keyword_allowed( string $keyword ): bool {
		$norm = self::normalize_token( $keyword );
		if ( '' === $norm ) {
			return false;
		}
		if ( in_array( $norm, self::keyword_blacklist(), true ) ) {
			return false;
		}
		$white = self::keyword_whitelist();
		if ( self::whitelist_only() && ! empty( $white ) && ! in_array( $norm, $white, true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether a target URL is allowed by URL lists.
	 *
	 * Blacklist always blocks. Whitelist alone only boosts priority;
	 * exclusive allow requires whitelist_only mode (same as keywords).
	 *
	 * @param string $url URL.
	 */
	public static function is_url_allowed( string $url ): bool {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return false;
		}
		$norm = self::normalize_token( $url );
		foreach ( self::url_blacklist() as $blocked ) {
			if ( '' !== $blocked && false !== strpos( $norm, $blocked ) ) {
				return false;
			}
		}
		$white = self::url_whitelist();
		if ( self::whitelist_only() && ! empty( $white ) ) {
			foreach ( $white as $allowed ) {
				if ( '' !== $allowed && false !== strpos( $norm, $allowed ) ) {
					return true;
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * Whether URL matches admin URL whitelist (for priority boost).
	 *
	 * @param string $url URL.
	 */
	public static function is_url_whitelisted( string $url ): bool {
		$norm  = self::normalize_token( $url );
		$white = self::url_whitelist();
		if ( empty( $white ) || '' === $norm ) {
			return false;
		}
		foreach ( $white as $allowed ) {
			if ( '' !== $allowed && false !== strpos( $norm, $allowed ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether target post/product is linkable (not noindex / redirected / missing).
	 *
	 * @param string $url Target URL.
	 * @return array{ok:bool,reason:string,post_id:int}
	 */
	public static function evaluate_target( string $url ): array {
		if ( ! self::is_url_allowed( $url ) ) {
			return array(
				'ok'      => false,
				'reason'  => 'url_blacklist',
				'post_id' => 0,
			);
		}

		$post_id = url_to_postid( $url );
		if ( ! $post_id ) {
			// External or non-resolvable: allow only if URL whitelist empty (legacy keyword→URL).
			return array(
				'ok'      => true,
				'reason'  => 'unresolved_url',
				'post_id' => 0,
			);
		}

		$status = get_post_status( $post_id );
		if ( 'publish' !== $status ) {
			return array(
				'ok'      => false,
				'reason'  => 'not_published',
				'post_id' => $post_id,
			);
		}

		if ( 'yes' === get_post_meta( $post_id, '_shojaei_seo_noindex', true ) ) {
			return array(
				'ok'      => false,
				'reason'  => 'noindex',
				'post_id' => $post_id,
			);
		}

		// Deprioritized targets stay linkable — priority_score reduces them instead of hard-block.
		// (Hard-block previously zeroed all suggestions after bad OOS day counts.)

		if ( 'product' === get_post_type( $post_id ) ) {
			global $wpdb;
			$table  = Shojaei_SEO_Helpers::oos_table();
			$record = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT status, redirect_type FROM {$table} WHERE product_id = %d",
					$post_id
				)
			);
			if ( $record && 'redirected' === $record->status ) {
				return array(
					'ok'      => false,
					'reason'  => 'redirected',
					'post_id' => $post_id,
				);
			}
			if ( $record && in_array( (string) $record->redirect_type, array( '301', '302', '410' ), true ) ) {
				return array(
					'ok'      => false,
					'reason'  => 'redirect_type',
					'post_id' => $post_id,
				);
			}
		}

		return array(
			'ok'      => true,
			'reason'  => 'ok',
			'post_id' => $post_id,
		);
	}

	/**
	 * Priority score for a keyword row relative to source post (rule-based).
	 *
	 * Higher = earlier injection attempt.
	 *
	 * @param object $row            DB row with keyword, target_url.
	 * @param int    $source_post_id Source post/product.
	 * @param int    $target_post_id Resolved target.
	 */
	public static function priority_score( object $row, int $source_post_id, int $target_post_id ): int {
		$score   = 10;
		$keyword = (string) ( $row->keyword ?? '' );
		$norm    = self::normalize_token( $keyword );

		if ( in_array( $norm, self::keyword_whitelist(), true ) ) {
			$score += 50;
		}

		$url = (string) ( $row->target_url ?? '' );
		if ( $url && self::is_url_whitelisted( $url ) ) {
			$score += 40;
		}

		// Longer anchors are usually more specific → prefer first (also helps avoid partial spam).
		$score += min( 20, mb_strlen( $keyword, 'UTF-8' ) );

		if ( ! $source_post_id || ! $target_post_id ) {
			return $score;
		}

		// Same product category.
		$shared_cats = self::shared_term_count( $source_post_id, $target_post_id, 'product_cat' );
		$score      += $shared_cats * 25;

		// Same brand (Woo brand or Perfect Woo Brands).
		foreach ( array( 'product_brand', 'pwb-brand', 'pa_brand' ) as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$shared = self::shared_term_count( $source_post_id, $target_post_id, $tax );
			$score += $shared * 30;
		}

		// Product attributes as light signal (first shared attribute taxonomy).
		if ( 'product' === get_post_type( $source_post_id ) && 'product' === get_post_type( $target_post_id ) ) {
			$score += self::shared_attribute_bonus( $source_post_id, $target_post_id );
			$score += self::replacement_bonus( $source_post_id, $target_post_id );
		}

		// Prefer in-stock product targets.
		if ( 'product' === get_post_type( $target_post_id ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $target_post_id );
			if ( $product && $product->is_in_stock() ) {
				$score += 15;
			} elseif ( $product && ! $product->is_in_stock() ) {
				$score -= 20;
			}
		}

		if ( 'yes' === get_post_meta( $target_post_id, '_shojaei_seo_link_deprioritized', true ) ) {
			$score -= 35;
		}

		if ( ! empty( $row->_priority_boost ) ) {
			$score += (int) $row->_priority_boost;
		}

		/**
		 * Filter link priority score.
		 *
		 * @param int    $score Score.
		 * @param object $row Row.
		 * @param int    $source_post_id Source.
		 * @param int    $target_post_id Target.
		 */
		return (int) apply_filters( 'shojaei_seo_link_priority_score', $score, $row, $source_post_id, $target_post_id );
	}

	/**
	 * Last skipped candidates from prepare_candidates (for preview/debug).
	 *
	 * @var array
	 */
	private static array $last_skipped = array();

	/**
	 * Get last skipped list from prepare_candidates.
	 *
	 * @return array
	 */
	public static function last_skipped(): array {
		return self::$last_skipped;
	}

	/**
	 * Filter + sort keyword rows for a source page.
	 *
	 * @param array $rows            Keyword rows.
	 * @param int   $source_post_id  Source post.
	 * @return array
	 */
	public static function prepare_candidates( array $rows, int $source_post_id = 0 ): array {
		// Merge manual keywords with auto suggestions from similar in-stock products.
		if ( $source_post_id > 0 ) {
			$rows = array_merge( $rows, self::auto_related_candidates( $source_post_id ) );
		}

		$prepared = array();
		$skipped  = array();
		$seen     = array();

		foreach ( $rows as $row ) {
			$keyword = trim( (string) ( $row->keyword ?? '' ) );
			$url     = trim( (string) ( $row->target_url ?? '' ) );
			if ( '' === $keyword || '' === $url ) {
				continue;
			}

			$dedupe = self::normalize_token( $keyword ) . '|' . self::normalize_token( $url );
			if ( isset( $seen[ $dedupe ] ) ) {
				continue;
			}
			$seen[ $dedupe ] = true;

			if ( ! self::is_keyword_allowed( $keyword ) ) {
				$skipped[] = array(
					'keyword' => $keyword,
					'reason'  => 'keyword_blocked',
				);
				continue;
			}

			$eval = self::evaluate_target( $url );
			if ( ! $eval['ok'] ) {
				$skipped[] = array(
					'keyword' => $keyword,
					'url'     => $url,
					'reason'  => $eval['reason'],
				);
				continue;
			}

			// Never link a page to itself.
			if ( $source_post_id && (int) $eval['post_id'] === $source_post_id ) {
				$skipped[] = array(
					'keyword' => $keyword,
					'reason'  => 'self_link',
				);
				continue;
			}

			$row->_target_post_id = (int) $eval['post_id'];
			$row->_priority       = self::priority_score( $row, $source_post_id, (int) $eval['post_id'] );
			$prepared[]           = $row;
		}

		usort(
			$prepared,
			static function ( $a, $b ) {
				$pa = (int) ( $a->_priority ?? 0 );
				$pb = (int) ( $b->_priority ?? 0 );
				if ( $pa === $pb ) {
					return mb_strlen( (string) $b->keyword, 'UTF-8' ) <=> mb_strlen( (string) $a->keyword, 'UTF-8' );
				}
				return $pb <=> $pa;
			}
		);

		self::$last_skipped = $skipped;

		/**
		 * After candidates prepared.
		 *
		 * @param array $prepared Prepared.
		 * @param array $skipped Skipped.
		 * @param int   $source_post_id Source.
		 */
		do_action( 'shojaei_seo_link_candidates_prepared', $prepared, $skipped, $source_post_id );

		return $prepared;
	}

	/**
	 * Auto keyword candidates from similar in-stock products (same category).
	 * Uses shared title tokens / bigrams for similarity scoring.
	 *
	 * @param int $source_post_id Source product.
	 * @return object[]
	 */
	public static function auto_related_candidates( int $source_post_id ): array {
		if ( $source_post_id < 1 || 'product' !== get_post_type( $source_post_id ) || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}

		$source = wc_get_product( $source_post_id );
		if ( ! $source ) {
			return array();
		}

		$cats = wp_get_post_terms( $source_post_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( empty( $cats ) || is_wp_error( $cats ) ) {
			return array();
		}

		$related = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'post__not_in'           => array( $source_post_id ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_stock_status',
						'value' => 'instock',
					),
				),
				'tax_query'              => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => array_map( 'absint', $cats ),
					),
				),
			)
		);

		if ( empty( $related ) ) {
			return array();
		}

		$source_title = $source->get_name();
		$out          = array();

		foreach ( $related as $rid ) {
			$rid = absint( $rid );
			$p   = get_post( $rid );
			if ( ! $p ) {
				continue;
			}

			$sim = Shojaei_SEO_Helpers::title_similarity( $source_title, $p->post_title );
			if ( $sim < 20 ) {
				continue;
			}

			$url = get_permalink( $rid );
			if ( ! $url ) {
				continue;
			}

			foreach ( self::title_anchor_phrases( $p->post_title, $source_title ) as $phrase ) {
				$out[] = (object) array(
					'keyword'         => $phrase,
					'target_url'      => $url,
					'_auto'           => true,
					'_priority_boost' => min( 40, (int) round( $sim / 2 ) ),
				);
			}
		}

		return $out;
	}

	/**
	 * Build linkable phrases from a related product title (bigrams + shared tokens).
	 *
	 * @param string $related_title Related title.
	 * @param string $source_title  Source title.
	 * @return string[]
	 */
	public static function title_anchor_phrases( string $related_title, string $source_title ): array {
		$related_words = Shojaei_SEO_Helpers::extract_keywords( $related_title );
		$source_words  = Shojaei_SEO_Helpers::extract_keywords( $source_title );
		$shared        = array_values( array_intersect( $related_words, $source_words ) );
		$phrases       = array();

		$count = count( $related_words );
		for ( $i = 0; $i < $count - 1; $i++ ) {
			$a = $related_words[ $i ];
			$b = $related_words[ $i + 1 ];
			if ( mb_strlen( $a, 'UTF-8' ) < 3 || mb_strlen( $b, 'UTF-8' ) < 3 ) {
				continue;
			}
			// Prefer bigrams that touch shared vocabulary with the source.
			if ( in_array( $a, $shared, true ) || in_array( $b, $shared, true ) || $count <= 4 ) {
				$phrases[] = $a . ' ' . $b;
			}
		}

		foreach ( $shared as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) >= 4 ) {
				$phrases[] = $word;
			}
		}

		// Unique, longest first.
		$phrases = array_values( array_unique( $phrases ) );
		usort(
			$phrases,
			static function ( $x, $y ) {
				return mb_strlen( $y, 'UTF-8' ) <=> mb_strlen( $x, 'UTF-8' );
			}
		);

		return array_slice( $phrases, 0, 6 );
	}

	/**
	 * Shared term count between two posts.
	 *
	 * @param int    $a  Post A.
	 * @param int    $b  Post B.
	 * @param string $taxonomy Taxonomy.
	 */
	private static function shared_term_count( int $a, int $b, string $taxonomy ): int {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$ta = wp_get_post_terms( $a, $taxonomy, array( 'fields' => 'ids' ) );
		$tb = wp_get_post_terms( $b, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $ta ) || is_wp_error( $tb ) || empty( $ta ) || empty( $tb ) ) {
			return 0;
		}
		return count( array_intersect( array_map( 'intval', $ta ), array_map( 'intval', $tb ) ) );
	}

	/**
	 * Bonus when target is a real replacement / related product for source.
	 *
	 * Uses Woo upsell/cross-sell and OOS suggested redirect target — no NLP.
	 *
	 * @param int $source_id Source product.
	 * @param int $target_id Target product.
	 */
	private static function replacement_bonus( int $source_id, int $target_id ): int {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		$source = wc_get_product( $source_id );
		if ( ! $source ) {
			return 0;
		}

		$explicit = array_map( 'intval', array_merge( $source->get_upsell_ids(), $source->get_cross_sell_ids() ) );
		if ( in_array( $target_id, $explicit, true ) ) {
			return 55;
		}

		// OOS table: planned/suggested redirect destination.
		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT target_url FROM {$table} WHERE product_id = %d AND target_url <> '' LIMIT 1",
				$source_id
			)
		);
		if ( $row && ! empty( $row->target_url ) ) {
			$planned = url_to_postid( (string) $row->target_url );
			if ( $planned && (int) $planned === $target_id ) {
				return 70;
			}
		}

		return 0;
	}

	/**
	 * Light bonus for shared WooCommerce attributes.
	 *
	 * @param int $a Product A.
	 * @param int $b Product B.
	 */
	private static function shared_attribute_bonus( int $a, int $b ): int {
		$product_a = wc_get_product( $a );
		$product_b = wc_get_product( $b );
		if ( ! $product_a || ! $product_b ) {
			return 0;
		}
		$attrs_a = $product_a->get_attributes();
		$attrs_b = $product_b->get_attributes();
		if ( empty( $attrs_a ) || empty( $attrs_b ) ) {
			return 0;
		}
		$bonus = 0;
		foreach ( $attrs_a as $key => $attr ) {
			if ( empty( $attrs_b[ $key ] ) ) {
				continue;
			}
			$tax = $attr->get_taxonomy();
			if ( $tax && taxonomy_exists( $tax ) ) {
				$shared = self::shared_term_count( $a, $b, $tax );
				if ( $shared > 0 ) {
					$bonus += min( 20, $shared * 10 );
				}
			}
		}
		return min( 40, $bonus );
	}

	/**
	 * Human labels for skip reasons (admin/education).
	 *
	 * @param string $reason Reason slug.
	 */
	public static function reason_label( string $reason ): string {
		$map = array(
			'keyword_blocked' => __( 'کلمه در blacklist / خارج از whitelist', 'shojaei-seo-for-woo' ),
			'url_blacklist'   => __( 'آدرس در blacklist', 'shojaei-seo-for-woo' ),
			'noindex'         => __( 'صفحه مقصد noindex است', 'shojaei-seo-for-woo' ),
			'redirected'      => __( 'صفحه مقصد ریدایرکت شده', 'shojaei-seo-for-woo' ),
			'redirect_type'   => __( 'مقصد دارای ریدایرکت فعال است', 'shojaei-seo-for-woo' ),
			'deprioritized'   => __( 'مقصد توسط Rule Engine کم‌اولویت شده', 'shojaei-seo-for-woo' ),
			'not_published'   => __( 'مقصد منتشر نیست', 'shojaei-seo-for-woo' ),
			'self_link'       => __( 'لینک به خود صفحه', 'shojaei-seo-for-woo' ),
			'dup_anchor'      => __( 'anchor تکراری در همین صفحه', 'shojaei-seo-for-woo' ),
			'dup_url'         => __( 'URL تکراری در همین صفحه', 'shojaei-seo-for-woo' ),
		);
		return $map[ $reason ] ?? $reason;
	}
}
