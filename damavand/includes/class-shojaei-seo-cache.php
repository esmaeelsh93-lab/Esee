<?php
/**
 * Cache compatibility — keep redirects / 410 / noindex working with popular caches.
 * Local hooks only (LiteSpeed, WP Rocket, W3TC, WP Super Cache, Cloudflare plugin).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Cache
 */
class Shojaei_SEO_Cache {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_bypass_cache_for_redirected' ), 1 );
	}

	/**
	 * Prevent page cache from serving stale product pages that must redirect or return 410.
	 */
	public function maybe_bypass_cache_for_redirected(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		global $wpdb;
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT redirect_type, status FROM {$table} WHERE product_id = %d AND status = 'redirected' LIMIT 1",
				$product_id
			)
		);

		if ( ! $record ) {
			return;
		}

		self::do_not_cache();
	}

	/**
	 * Mark current request as uncacheable.
	 */
	public static function do_not_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}

		nocache_headers();

		// LiteSpeed Cache.
		if ( ! defined( 'LSCACHE_NO_CACHE' ) ) {
			define( 'LSCACHE_NO_CACHE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'shojaei-seo-redirect' );

		// WP Rocket.
		if ( ! defined( 'DONOTROCKETOPTIMIZE' ) ) {
			define( 'DONOTROCKETOPTIMIZE', true );
		}
	}

	/**
	 * Purge caches for a product URL after stock/redirect changes.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function purge_product( int $product_id ): void {
		if ( ! $product_id ) {
			return;
		}

		$url = get_permalink( $product_id );
		if ( ! $url || is_wp_error( $url ) ) {
			return;
		}

		// LiteSpeed Cache.
		do_action( 'litespeed_purge_url', $url );
		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_url' ) ) {
			\LiteSpeed\Purge::purge_url( $url );
		}

		// WP Rocket.
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $url );
		}
		if ( function_exists( 'rocket_clean_post' ) ) {
			rocket_clean_post( $product_id );
		}

		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_url' ) ) {
			w3tc_flush_url( $url );
		}
		if ( function_exists( 'w3tc_flush_post' ) ) {
			w3tc_flush_post( $product_id );
		}

		// WP Super Cache.
		if ( function_exists( 'wp_cache_post_change' ) ) {
			wp_cache_post_change( $product_id );
		}

		// Cloudflare plugin (optional local purge API via plugin, not our cloud call).
		if ( class_exists( '\CF\WordPress\Hooks' ) && function_exists( 'do_action' ) ) {
			do_action( 'cloudflare_purge_by_url', $url );
		}

		/**
		 * Allow other cache plugins to react.
		 *
		 * @param int    $product_id Product ID.
		 * @param string $url        Product URL.
		 */
		do_action( 'shojaei_seo_purge_product_cache', $product_id, $url );
	}

	/**
	 * Purge after redirect / 410 / stock change.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function on_seo_state_change( int $product_id ): void {
		self::purge_product( $product_id );
		self::purge_shop_archives( $product_id );
		self::bust_schema_cache( $product_id );
	}

	/**
	 * Shop / category HTML often stays cached after 410 — drop those URLs too.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function purge_shop_archives( int $product_id ): void {
		$urls = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$shop = get_permalink( $shop_id );
				if ( $shop && ! is_wp_error( $shop ) ) {
					$urls[] = $shop;
				}
			}
		}
		if ( $product_id > 0 && function_exists( 'wc_get_product_term_ids' ) ) {
			$term_ids = wc_get_product_term_ids( $product_id, 'product_cat' );
			foreach ( (array) $term_ids as $tid ) {
				$link = get_term_link( (int) $tid, 'product_cat' );
				if ( $link && ! is_wp_error( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		foreach ( array_unique( $urls ) as $url ) {
			do_action( 'litespeed_purge_url', $url );
			if ( function_exists( 'rocket_clean_files' ) ) {
				rocket_clean_files( $url );
			}
			if ( function_exists( 'w3tc_flush_url' ) ) {
				w3tc_flush_url( $url );
			}
		}

		do_action( 'shojaei_seo_purge_shop_archives', $product_id, $urls );
	}

	/**
	 * Clear Damavand Product JSON-LD transient (stock/price/currency freshness).
	 *
	 * @param int $product_id Product ID.
	 */
	public static function bust_schema_cache( int $product_id ): void {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return;
		}
		delete_transient( 'shojaei_seo_schema_product_v2_' . $product_id );
		delete_transient( 'shojaei_seo_schema_product_' . $product_id );
	}
}
