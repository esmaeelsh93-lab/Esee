<?php
/**
 * Crawl-budget robots — structural noindex + per-post flags (Damavand).
 *
 * Rank Math parity for Iranian WooCommerce: never burn crawl on cart,
 * checkout, account, search, faceted filters, or thin system archives.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Robots
 */
final class Damavand_Robots {

	/**
	 * Query args that almost never deserve an indexable URL.
	 *
	 * @return string[]
	 */
	public static function faceted_query_keys(): array {
		return array(
			'orderby',
			'order',
			'min_price',
			'max_price',
			'rating_filter',
			'onsale',
			'stock_status',
			'filter',
			'add-to-cart',
			'remove_item',
			'removed_item',
			'undo_item',
			'update_cart',
			'apply_coupon',
			'remove_coupon',
		);
	}

	/**
	 * Damavand owns robots when no primary SEO competitor (or force).
	 * Structural rules do not require the "general meta" toggle —
	 * cart/checkout must stay noindex even if the merchant never opened that tab.
	 */
	public static function is_primary(): bool {
		$has_comp = class_exists( 'Shojaei_SEO_General_Meta' )
			? Shojaei_SEO_General_Meta::has_meta_competitor()
			: false;
		if ( ! $has_comp ) {
			return true;
		}
		$force = class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_force_with_competitors', 'no' )
			: get_option( 'shojaei_seo_meta_force_with_competitors', 'no' );
		return 'yes' === $force;
	}

	/**
	 * Site-wide default robots from General Meta settings.
	 */
	public static function meta_defaults_enabled(): bool {
		if ( ! self::is_primary() ) {
			return false;
		}
		$enabled = class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' )
			: get_option( 'shojaei_seo_meta_enabled', 'no' );
		return 'yes' === $enabled;
	}

	/**
	 * Whether the current request should be noindex for crawl-budget reasons.
	 */
	public static function should_noindex_request(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( is_search() || is_404() ) {
			return true;
		}

		if ( is_attachment() ) {
			return true;
		}

		// Author / date archives are almost never useful for Iranian shops.
		if ( is_author() || is_date() ) {
			return 'yes' === self::opt( 'shojaei_seo_meta_noindex_author_date', 'yes' );
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return 'yes' === self::opt( 'shojaei_seo_meta_noindex_wc_system', 'yes' );
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return 'yes' === self::opt( 'shojaei_seo_meta_noindex_wc_system', 'yes' );
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return 'yes' === self::opt( 'shojaei_seo_meta_noindex_wc_system', 'yes' );
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return 'yes' === self::opt( 'shojaei_seo_meta_noindex_wc_system', 'yes' );
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
			if ( 'yes' !== self::opt( 'shojaei_seo_meta_noindex_wc_system', 'yes' ) ) {
				return false;
			}
			// Keep useful account? No — endpoints are transactional.
			$skip = array( 'order-pay', 'order-received', 'view-order', 'edit-account', 'edit-address', 'lost-password', 'customer-logout', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method' );
			foreach ( $skip as $ep ) {
				if ( is_wc_endpoint_url( $ep ) ) {
					return true;
				}
			}
		}

		if ( self::request_has_faceted_args() ) {
			return 'yes' === self::opt( 'shojaei_seo_meta_noindex_facets', 'yes' );
		}

		return false;
	}

	/**
	 * Faceted / filter / sort query present.
	 */
	public static function request_has_faceted_args(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get = isset( $_GET ) && is_array( $_GET ) ? $_GET : array();
		if ( empty( $get ) ) {
			return false;
		}
		foreach ( array_keys( $get ) as $key ) {
			$key = (string) $key;
			if ( in_array( $key, self::faceted_query_keys(), true ) ) {
				return true;
			}
			if ( 0 === strpos( $key, 'filter_' ) || 0 === strpos( $key, 'query_type_' ) ) {
				return true;
			}
			// Variation attrs on archives / shop are filter URLs; on product pages canonical handles them.
			if ( ( 0 === strpos( $key, 'attribute_' ) || 0 === strpos( $key, 'pa_' ) )
				&& function_exists( 'is_product' ) && ! is_product() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Empty taxonomy archive (noindex when setting on).
	 */
	public static function is_empty_tax_archive(): bool {
		if ( 'yes' !== self::opt( 'shojaei_seo_meta_noindex_empty_tax', 'yes' ) ) {
			return false;
		}
		if ( ! ( is_category() || is_tag() || is_tax() ) || is_paged() ) {
			return false;
		}
		global $wp_query;
		return $wp_query instanceof WP_Query && 0 === (int) $wp_query->found_posts;
	}

	/**
	 * Per-post / per-term robots flags from Damavand meta.
	 *
	 * @return string[] Lowercase directives e.g. noindex, nofollow.
	 */
	public static function object_robots_flags(): array {
		$flags = array();

		if ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
			if ( $post_id > 0 ) {
				$flags = self::normalize_robots_meta( get_post_meta( $post_id, '_damavand_seo_robots', true ) );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$raw = get_term_meta( (int) $term->term_id, '_damavand_seo_robots', true );
				$flags = self::normalize_robots_meta( $raw );
				if ( empty( $flags ) && 'yes' === (string) get_term_meta( (int) $term->term_id, '_damavand_seo_noindex', true ) ) {
					$flags[] = 'noindex';
				}
			}
		}

		/**
		 * Filter Damavand object-level robots flags.
		 *
		 * @param string[] $flags Flags.
		 */
		return array_values( array_unique( (array) apply_filters( 'damavand_object_robots_flags', $flags ) ) );
	}

	/**
	 * Normalize stored robots meta (array|string) to flag list.
	 *
	 * @param mixed $raw Meta value.
	 * @return string[]
	 */
	public static function normalize_robots_meta( $raw ): array {
		$allowed = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex' );
		$out     = array();
		if ( is_string( $raw ) && '' !== $raw ) {
			$parts = preg_split( '/[\s,]+/', strtolower( $raw ) );
			$raw   = is_array( $parts ) ? $parts : array();
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		foreach ( $raw as $flag ) {
			$flag = strtolower( trim( (string) $flag ) );
			if ( in_array( $flag, $allowed, true ) ) {
				$out[] = $flag;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Apply noindex + keep follow unless nofollow requested.
	 *
	 * @param array<string,mixed> $robots Robots.
	 * @param bool                $nofollow Also nofollow.
	 * @return array<string,mixed>
	 */
	public static function force_noindex( array $robots, bool $nofollow = false ): array {
		$robots['noindex'] = true;
		unset( $robots['index'] );
		if ( $nofollow ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		} elseif ( empty( $robots['nofollow'] ) ) {
			$robots['follow'] = true;
		}
		return $robots;
	}

	/**
	 * Whether schema / rich results should be suppressed for this request.
	 */
	public static function should_skip_schema(): bool {
		if ( self::should_noindex_request() ) {
			return true;
		}
		$flags = self::object_robots_flags();
		if ( in_array( 'noindex', $flags, true ) ) {
			return true;
		}
		if ( self::is_empty_tax_archive() ) {
			return true;
		}
		// OOS product flagged noindex.
		if ( is_singular( 'product' ) ) {
			$pid = (int) get_queried_object_id();
			if ( $pid > 0 && 'yes' === (string) get_post_meta( $pid, '_shojaei_seo_noindex', true ) ) {
				return true;
			}
		}
		return (bool) apply_filters( 'damavand_should_skip_schema', false );
	}

	/**
	 * Merge Damavand robots into wp_robots array.
	 *
	 * @param array<string,mixed> $robots Current.
	 * @return array<string,mixed>
	 */
	public static function apply_to_robots( array $robots ): array {
		if ( ! self::is_primary() ) {
			return $robots;
		}

		// 1) Site defaults (only when General Meta module enabled).
		if ( self::meta_defaults_enabled() && class_exists( 'Shojaei_SEO_General_Meta' ) ) {
			foreach ( Shojaei_SEO_General_Meta::default_robots_directives() as $key => $val ) {
				$robots[ $key ] = $val;
			}
		}

		// 2) Structural / faceted / search crawl-budget (always when primary).
		if ( self::should_noindex_request() ) {
			$robots = self::force_noindex( $robots, false );
		}

		// 3) Empty tax archives.
		if ( self::is_empty_tax_archive() ) {
			$robots = self::force_noindex( $robots, false );
		}

		// 4) Per-object Damavand robots meta.
		foreach ( self::object_robots_flags() as $flag ) {
			if ( 'noindex' === $flag ) {
				$robots = self::force_noindex( $robots, ! empty( $robots['nofollow'] ) );
				continue;
			}
			if ( 'nofollow' === $flag ) {
				$robots['nofollow'] = true;
				unset( $robots['follow'] );
				continue;
			}
			$robots[ $flag ] = true;
		}

		return $robots;
	}

	/**
	 * Option helper with Helpers fallback.
	 *
	 * @param string $key     Option.
	 * @param string $default Default.
	 */
	private static function opt( string $key, string $default = 'no' ): string {
		if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
			return (string) Shojaei_SEO_Helpers::get_option( $key, $default );
		}
		return (string) get_option( $key, $default );
	}
}
