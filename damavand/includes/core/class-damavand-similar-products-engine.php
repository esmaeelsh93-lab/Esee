<?php
/**
 * Similar Products engine — settings-driven query + transient cache.
 *
 * Front display is independent of Link Graph. On product change we only:
 * - delete this product's similar transient
 * - optionally re-upsert affinity edges for the same source (no graph purge)
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Similar_Products_Engine
 */
final class Damavand_Similar_Products_Engine {

	public const TRANSIENT_PREFIX = 'damavand_sim_prod_';
	public const CACHE_TTL        = DAY_IN_SECONDS;

	/** @var array<int,true> */
	private static $handled_saves = array();

	/**
	 * Hooks: cache bust + front render (via existing plugin bootstrap).
	 */
	public static function register_hooks(): void {
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product_saved' ), 20, 1 );
		add_action( 'save_post_product', array( __CLASS__, 'on_product_saved' ), 20, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_product_deleted' ), 20, 1 );
		add_action( 'woocommerce_after_single_product_summary', array( __CLASS__, 'render_on_single' ), 18 );
	}

	/**
	 * Transient key for a product.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function cache_key( int $product_id ): string {
		return self::TRANSIENT_PREFIX . absint( $product_id );
	}

	/**
	 * Whether the Similar Products module is enabled.
	 */
	public static function is_enabled(): bool {
		if ( ! class_exists( 'Damavand_Similar_Products_Settings' ) ) {
			return false;
		}
		$s = Damavand_Similar_Products_Settings::get();
		return ! empty( $s['enabled'] );
	}

	/**
	 * Product updated/saved: drop similar transient; optionally refresh source similarity.
	 * Never purges the link graph.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function on_product_saved( $product_id ): void {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $product_id ) ) {
			return;
		}
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
			return;
		}
		// woocommerce_update_product + save_post_product often fire together.
		if ( isset( self::$handled_saves[ $product_id ] ) ) {
			return;
		}
		self::$handled_saves[ $product_id ] = true;

		delete_transient( self::cache_key( $product_id ) );

		if ( ! self::is_enabled() ) {
			return;
		}

		// Recompute affinity for this source only (upsert) — no purge_target / full wipe.
		if ( class_exists( 'Damavand_Link_Calculator' ) ) {
			$limit = 5;
			if ( class_exists( 'Damavand_Similar_Products_Settings' ) ) {
				$limit = (int) Damavand_Similar_Products_Settings::get()['limit'];
			}
			Damavand_Link_Calculator::calculate_for_source( $product_id, max( 1, min( 12, $limit ) ) );
		}
	}

	/**
	 * Product deleted: drop similar transient only (graph lifecycle stays with Link Manager).
	 *
	 * @param int $product_id Post ID.
	 */
	public static function on_product_deleted( $product_id ): void {
		$product_id = absint( $product_id );
		if ( $product_id < 1 || 'product' !== get_post_type( $product_id ) ) {
			return;
		}
		delete_transient( self::cache_key( $product_id ) );
	}

	/**
	 * Flush all similar-product transients (settings change).
	 */
	public static function flush_all_caches(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::TRANSIENT_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%'
			)
		);
	}

	/**
	 * Find similar product IDs (cached 24h). Empty array when disabled or no matches.
	 *
	 * @param int $product_id Source product.
	 * @return int[]
	 */
	public static function get_similar_products( int $product_id ): array {
		$product_id = absint( $product_id );
		if ( $product_id < 1 || ! self::is_enabled() ) {
			return array();
		}

		$settings  = Damavand_Similar_Products_Settings::get();
		$cache_key = self::cache_key( $product_id );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array_values( array_filter( array_map( 'absint', $cached ) ) );
		}

		$limit = max( 1, min( 12, (int) $settings['limit'] ) );
		$tax_q = self::build_tax_query( $product_id, $settings );
		if ( empty( $tax_q ) ) {
			set_transient( $cache_key, array(), self::CACHE_TTL );
			return array();
		}

		$exclude = array( $product_id );
		if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
			$exclude = array_merge( $exclude, Shojaei_SEO_Helpers::get_410_excluded_ids() );
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'post__not_in'           => array_values( array_unique( array_map( 'absint', $exclude ) ) ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'orderby'                => 'rand',
				'tax_query'              => $tax_q, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			)
		);

		$ids = array_map( 'absint', is_array( $query->posts ) ? $query->posts : array() );
		$ids = array_values( array_filter( $ids ) );

		set_transient( $cache_key, $ids, self::CACHE_TTL );
		return $ids;
	}

	/**
	 * WooCommerce single product hook — no output when off or empty.
	 */
	public static function render_on_single(): void {
		if ( ! is_product() || ! self::is_enabled() ) {
			return;
		}
		$html = self::render_similar_products_box( (int) get_the_ID() );
		if ( '' === $html ) {
			return;
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template.
	}

	/**
	 * Render box HTML, or empty string (keeps layout clean).
	 *
	 * @param int $product_id Product ID.
	 */
	public static function render_similar_products_box( int $product_id = 0 ): string {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			$product_id = absint( get_the_ID() );
		}
		if ( $product_id < 1 || ! self::is_enabled() ) {
			return '';
		}

		$ids = self::get_similar_products( $product_id );
		if ( empty( $ids ) ) {
			return '';
		}

		$products = array();
		foreach ( $ids as $id ) {
			if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::is_410_excluded( $id ) ) {
				continue;
			}
			$p = get_post( $id );
			if ( ! $p || 'publish' !== $p->post_status ) {
				continue;
			}
			$reason = '';
			if ( class_exists( 'Damavand_Link_Calculator' ) ) {
				$scored = Damavand_Link_Calculator::score_pair( $product_id, $id );
				$reason = is_array( $scored ) ? (string) ( $scored['reason'] ?? '' ) : '';
			}
			$products[] = array(
				'id'     => $id,
				'title'  => get_the_title( $id ),
				'url'    => get_permalink( $id ),
				'reason' => $reason,
			);
		}
		if ( empty( $products ) ) {
			return '';
		}

		$template = DAMAVAND_SEO_DIR . 'templates/similar-products.php';
		if ( ! is_readable( $template ) ) {
			return '';
		}

		ob_start();
		$damavand_similar_products = $products;
		$damavand_similar_source   = $product_id;
		include $template;
		return (string) ob_get_clean();
	}

	/**
	 * Build tax_query from enabled criteria (category / tag / attribute).
	 *
	 * @param int   $product_id Product.
	 * @param array $settings   Settings.
	 * @return array<int|string,mixed>
	 */
	private static function build_tax_query( int $product_id, array $settings ): array {
		$clauses = array();

		if ( ! empty( $settings['match_categories'] ) && taxonomy_exists( 'product_cat' ) ) {
			$ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ids ) && ! empty( $ids ) ) {
				$clauses[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', $ids ),
				);
			}
		}

		if ( ! empty( $settings['match_tags'] ) && taxonomy_exists( 'product_tag' ) ) {
			$ids = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $ids ) && ! empty( $ids ) ) {
				$clauses[] = array(
					'taxonomy' => 'product_tag',
					'field'    => 'term_id',
					'terms'    => array_map( 'absint', $ids ),
				);
			}
		}

		if ( ! empty( $settings['match_attributes'] ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				foreach ( $product->get_attributes() as $attribute ) {
					if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'is_taxonomy' ) || ! $attribute->is_taxonomy() ) {
						continue;
					}
					$tax = method_exists( $attribute, 'get_taxonomy' ) ? (string) $attribute->get_taxonomy() : '';
					if ( '' === $tax || ! taxonomy_exists( $tax ) ) {
						continue;
					}
					$ids = wp_get_post_terms( $product_id, $tax, array( 'fields' => 'ids' ) );
					if ( is_wp_error( $ids ) || empty( $ids ) ) {
						continue;
					}
					$clauses[] = array(
						'taxonomy' => $tax,
						'field'    => 'term_id',
						'terms'    => array_map( 'absint', $ids ),
					);
				}
			}
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		if ( count( $clauses ) > 1 ) {
			return array_merge( array( 'relation' => 'OR' ), $clauses );
		}
		return $clauses;
	}
}
