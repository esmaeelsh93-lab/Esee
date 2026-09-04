<?php
/**
 * Canonical URL Resolver — Damavand SEO Core
 *
 * OWNER: Sole runtime resolver for HTML `rel=canonical`.
 * Consolidates former Damavand_Canonical + Shojaei_SEO_Canonical (1.58).
 *
 * Resolution order:
 *  1. Manual Damavand / migrated SEO canonical meta (singular)
 *  2. Variation / attribute-query → parent product URL (when variation feature on)
 *  3. Faceted shop/tax query args stripped from URL
 *  4. Paged shop/tax archives → self-canonical /page/N/
 *  5. Fallback (WP filter input or permalink / term link)
 *  6. Module policies (force HTTPS + strip UTM) when module enabled
 *
 * Also: preserve_archive_pagination via redirect_canonical (no stripping /page/N/).
 *
 * @package Shojaei_SEO_For_Woo
 * @since   1.58.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Canonical_Resolver
 */
class SEO_Core_Canonical_Resolver {

	/**
	 * Register filters once from Canonical Module::boot().
	 */
	public static function register_hooks(): void {
		add_filter( 'get_canonical_url', array( __CLASS__, 'filter_get_canonical_url' ), 20, 2 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'filter_external_canonical' ), 18 );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'filter_external_canonical' ), 18 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'preserve_archive_pagination' ), 10, 2 );
	}

	/**
	 * Public API — all canonical consumers should use damavand_get_canonical_url() or this.
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
		if ( ! is_string( $canonical ) ) {
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
		if ( self::variation_feature_on() ) {
			$parent = self::resolve_parent_url();
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
	 * Variation→parent feature gate (module + option).
	 */
	private static function variation_feature_on(): bool {
		return class_exists( 'SEO_Core_Canonical_Module' )
			&& SEO_Core_Canonical_Module::is_runtime_allowed();
	}

	/**
	 * Resolve parent product canonical URL for current request, or empty.
	 */
	public static function resolve_parent_url(): string {
		if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) {
			return '';
		}

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return '';
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			return $parent_id ? (string) get_permalink( $parent_id ) : '';
		}

		// Variable product with attribute query (?attribute_pa_color=…) → clean parent URL.
		if ( $product->is_type( 'variable' ) && self::request_has_variation_attrs() ) {
			return (string) get_permalink( $product->get_id() );
		}

		return '';
	}

	/**
	 * Request carries WooCommerce variation attribute query args.
	 */
	private static function request_has_variation_attrs(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( array_keys( $_GET ) as $key ) {
			$key = (string) $key;
			if ( 0 === strpos( $key, 'attribute_' ) ) {
				return true;
			}
		}
		return false;
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
		if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$link = get_permalink( $shop_id );
				return is_string( $link ) ? $link : '';
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
	 * Stop core from stripping /page/N/ on shop and taxonomy archives.
	 *
	 * @param string|false $redirect_url  Redirect target.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public static function preserve_archive_pagination( $redirect_url, $requested_url ) {
		if ( ! $redirect_url || ! is_paged() ) {
			return $redirect_url;
		}
		$is_shop_archive = ( function_exists( 'is_shop' ) && is_shop() )
			|| is_post_type_archive( 'product' )
			|| is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) )
			|| is_category()
			|| is_tag();
		if ( ! $is_shop_archive ) {
			return $redirect_url;
		}
		$req_path = (string) wp_parse_url( $requested_url, PHP_URL_PATH );
		$red_path = (string) wp_parse_url( (string) $redirect_url, PHP_URL_PATH );
		if ( preg_match( '#/page/\d+/?$#', $req_path ) && ! preg_match( '#/page/\d+/?$#', $red_path ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * HTTPS + UTM strip via seo-core policies when module class is available.
	 *
	 * Policies apply whenever the Canonical module options exist (HTTPS/UTM),
	 * not only when variation→parent is on. Module disable is handled by Loader
	 * (resolver hooks are not registered if module boot is skipped).
	 *
	 * @param string $url URL.
	 */
	public static function finalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$url = esc_url_raw( $url );
		if ( class_exists( 'SEO_Core_Canonical_Module' ) ) {
			$url = SEO_Core_Canonical_Module::apply_policies( $url );
		}

		/**
		 * Final canonical URL after Damavand resolution + policies.
		 *
		 * @param string $url Canonical URL.
		 */
		$url = (string) apply_filters( 'damavand_canonical_url', $url );
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
		if ( class_exists( 'SEO_Core_Canonical_Resolver' ) ) {
			return SEO_Core_Canonical_Resolver::get_url( $post, $fallback );
		}
		return is_string( $fallback ) ? $fallback : '';
	}
}
