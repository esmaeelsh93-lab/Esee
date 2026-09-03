<?php
/**
 * Central canonical resolver — guide section 8.
 *
 * Priority: manual meta → variation/filter rules → WP fallback → site-wide normalize.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Canonical
 */
final class Damavand_Canonical {

	/**
	 * Register canonical filter (single pipeline for head output).
	 */
	public static function register_hooks(): void {
		add_filter( 'get_canonical_url', array( __CLASS__, 'filter_get_canonical_url' ), 20, 2 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'filter_external_canonical' ), 18 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'filter_external_canonical' ), 18 );
	}

	/**
	 * Public API — all canonical output should use this.
	 *
	 * @param int|WP_Post|null $post     Post context (optional).
	 * @param string           $fallback Pre-computed URL (optional).
	 */
	public static function get_url( $post = null, string $fallback = '' ): string {
		return self::resolve( $post, $fallback );
	}

	/**
	 * WordPress get_canonical_url filter.
	 *
	 * @param string       $canonical Current.
	 * @param WP_Post|null $post      Post.
	 */
	public static function filter_get_canonical_url( $canonical, $post = null ) {
		$base = is_string( $canonical ) ? $canonical : '';
		$url  = self::resolve( $post, $base );
		return '' !== $url ? $url : $canonical;
	}

	/**
	 * Rank Math / Yoast canonical filters.
	 *
	 * @param string $canonical Plugin canonical.
	 */
	public static function filter_external_canonical( $canonical ) {
		if ( ! is_string( $canonical ) || '' === $canonical ) {
			$canonical = '';
		}
		$url = self::resolve( null, $canonical );
		return '' !== $url ? $url : $canonical;
	}

	/**
	 * Core resolver.
	 *
	 * @param int|WP_Post|null $post     Context.
	 * @param string           $fallback Fallback URL.
	 */
	private static function resolve( $post, string $fallback ): string {
		$post_id = self::resolve_post_id( $post );

		// 1) Manual admin canonical (cross-domain allowed).
		if ( $post_id > 0 && class_exists( 'Damavand_SEO_Meta' ) ) {
			$manual = Damavand_SEO_Meta::get_canonical( $post_id );
			if ( '' !== $manual ) {
				return self::finalize( $manual );
			}
		}

		// 2) WooCommerce variation / attribute query → parent product URL.
		if ( class_exists( 'Shojaei_SEO_Canonical' ) && Shojaei_SEO_Canonical::is_enabled() ) {
			$parent = Shojaei_SEO_Canonical::resolve_parent_url();
			if ( '' !== $parent ) {
				return self::finalize( $parent );
			}
		}

		$url = $fallback;
		if ( '' === $url ) {
			$url = self::default_fallback( $post_id );
		}

		// 3) Shop / taxonomy faceted URLs → strip filter & sort query args.
		$url = self::strip_faceted_query_args( $url );

		// 4) Paginated archives → self-referencing /page/N/.
		$url = self::apply_paged_archive( $url );

		return self::finalize( $url );
	}

	/**
	 * @param int|WP_Post|null $post Post.
	 */
	private static function resolve_post_id( $post ): int {
		if ( $post instanceof WP_Post ) {
			return (int) $post->ID;
		}
		if ( is_numeric( $post ) ) {
			return (int) $post;
		}
		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}
		return 0;
	}

	/**
	 * WP default when no fallback supplied.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function default_fallback( int $post_id ): string {
		if ( $post_id > 0 ) {
			$link = get_permalink( $post_id );
			return is_string( $link ) ? $link : '';
		}
		if ( is_search() || is_404() ) {
			return '';
		}
		if ( function_exists( 'get_queried_object' ) ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Term ) {
				$link = get_term_link( $obj );
				return is_wp_error( $link ) ? '' : (string) $link;
			}
		}
		return '';
	}

	/**
	 * Remove WooCommerce filter/sort/attribute query noise from canonical.
	 *
	 * @param string $url URL.
	 */
	public static function strip_faceted_query_args( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return $url;
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
			return $url;
		}
		parse_str( (string) $parts['query'], $query );
		if ( ! is_array( $query ) || empty( $query ) ) {
			return $url;
		}

		$strip_exact = array(
			'orderby',
			'order',
			'min_price',
			'max_price',
			'rating_filter',
			'onsale',
			'stock_status',
			'filter',
			'post_type',
			's',
			'add-to-cart',
		);

		foreach ( array_keys( $query ) as $key ) {
			$key = (string) $key;
			if ( in_array( $key, $strip_exact, true ) ) {
				unset( $query[ $key ] );
				continue;
			}
			if ( 0 === strpos( $key, 'filter_' ) || 0 === strpos( $key, 'query_type_' ) ) {
				unset( $query[ $key ] );
				continue;
			}
			if ( 0 === strpos( $key, 'attribute_' ) || 0 === strpos( $key, 'pa_' ) ) {
				unset( $query[ $key ] );
			}
		}

		$parts['query'] = $query ? http_build_query( $query ) : null;
		return self::build_url( $parts );
	}

	/**
	 * Archive page 2+ should canonicalize to themselves.
	 *
	 * @param string $url URL.
	 */
	public static function apply_paged_archive( string $url ): string {
		if ( '' === $url || ! is_paged() ) {
			return $url;
		}
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		if ( $paged < 2 ) {
			return $url;
		}
		if ( ! ( is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) ) || is_post_type_archive( 'product' ) || is_category() || is_tag() || ( function_exists( 'is_shop' ) && is_shop() ) ) ) {
			return $url;
		}
		if ( preg_match( '#/page/' . $paged . '/?$#', $url ) ) {
			return $url;
		}
		$base = trailingslashit( preg_replace( '#/page/\d+/?$#', '', untrailingslashit( $url ) ) );
		return $base . 'page/' . $paged . '/';
	}

	/**
	 * HTTPS + UTM strip via seo-core policies.
	 *
	 * @param string $url URL.
	 */
	public static function finalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$url = esc_url_raw( $url );
		if ( class_exists( 'SEO_Core_Canonical_Module' ) && SEO_Core_Canonical_Module::is_runtime_allowed() ) {
			$url = SEO_Core_Canonical_Module::apply_policies( $url );
		}
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Rebuild URL from parse_url parts.
	 *
	 * @param array<string,mixed> $parts Parts.
	 */
	private static function build_url( array $parts ): string {
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = $parts['host'] ?? '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$user   = $parts['user'] ?? '';
		$pass   = isset( $parts['pass'] ) ? ':' . $parts['pass'] : '';
		$pass   = ( $user || $pass ) ? "$pass@" : '';
		$path   = $parts['path'] ?? '';
		$query  = ! empty( $parts['query'] ) ? '?' . $parts['query'] : '';
		$frag   = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		return esc_url_raw( "$scheme$user$pass$host$port$path$query$frag" );
	}
}

if ( ! function_exists( 'damavand_get_canonical_url' ) ) {
	/**
	 * Global helper — guide section 8.
	 *
	 * @param int|WP_Post|null $post     Optional post context.
	 * @param string           $fallback Optional fallback URL.
	 */
	function damavand_get_canonical_url( $post = null, string $fallback = '' ): string {
		return Damavand_Canonical::get_url( $post, $fallback );
	}
}
