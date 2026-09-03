<?php
/**
 * Canonical for WooCommerce variations — parent URL is authoritative.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Canonical
 */
class Shojaei_SEO_Canonical {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'redirect_canonical', array( $this, 'preserve_archive_pagination' ), 10, 2 );
		add_filter( 'get_canonical_url', array( $this, 'filter_paged_archive_canonical' ), 15, 2 );

		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'canonical' ) ) {
			return;
		}
		if ( class_exists( 'SEO_Core_Canonical_Module' ) && ! SEO_Core_Canonical_Module::is_runtime_allowed() ) {
			return;
		}
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_variation_canonical', 'yes' ) ) {
			return;
		}

		// External plugins + wp_head: Damavand_Canonical::register_hooks() owns the pipeline.
	}

	/**
	 * Whether feature is on.
	 */
	public static function is_enabled(): bool {
		if ( class_exists( 'SEO_Core_Canonical_Module' ) ) {
			return SEO_Core_Canonical_Module::is_runtime_allowed();
		}
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'canonical' ) ) {
			return false;
		}
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_variation_canonical', 'yes' );
	}

	/**
	 * Resolve parent product canonical URL for current request, or empty.
	 */
	public static function resolve_parent_url(): string {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return '';
		}

		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return '';
		}

		// Direct variation permalink → parent.
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
	 * Filter Yoast / Rank Math canonical.
	 *
	 * @param string $canonical Current canonical.
	 */
	public function filter_external_canonical( $canonical ) {
		$parent = self::resolve_parent_url();
		$url    = $parent ? $parent : $canonical;
		if ( is_string( $url ) && '' !== $url && class_exists( 'SEO_Core_Canonical_Module' ) ) {
			$url = SEO_Core_Canonical_Module::apply_policies( $url );
		}
		return $url;
	}

	/**
	 * Print <link rel="canonical"> when we own the tag (no Rank Math / Yoast).
	 */
	public function maybe_print_canonical(): void {
		if ( class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::has_primary_seo_plugin() ) {
			return;
		}

		$parent = self::resolve_parent_url();
		if ( ! $parent ) {
			return;
		}

		if ( class_exists( 'SEO_Core_Canonical_Module' ) ) {
			$parent = SEO_Core_Canonical_Module::apply_policies( $parent );
		}

		// Avoid duplicate with core rel_canonical on same URL.
		remove_action( 'wp_head', 'rel_canonical' );

		printf(
			'<link rel="canonical" href="%s" data-shojaei-seo="variation-canonical" />' . "\n",
			esc_url( $parent )
		);
	}

	/**
	 * Stop core from stripping /page/N/ on shop and taxonomy archives.
	 *
	 * @param string|false $redirect_url  Redirect target.
	 * @param string       $requested_url Requested URL.
	 * @return string|false
	 */
	public function preserve_archive_pagination( $redirect_url, $requested_url ) {
		if ( ! $redirect_url || ! is_paged() ) {
			return $redirect_url;
		}
		if ( ! ( is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) ) || is_post_type_archive( 'product' ) || is_category() || is_tag() ) ) {
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
	 * Ensure paginated archives canonicalize to their own page URL.
	 *
	 * @param string       $canonical Canonical URL.
	 * @param WP_Post|null $post      Post object when singular.
	 */
	public function filter_paged_archive_canonical( $canonical, $post = null ) {
		if ( $post instanceof WP_Post || ! is_paged() ) {
			return $canonical;
		}
		$paged = max( 1, (int) get_query_var( 'paged' ) );
		if ( $paged < 2 || ! is_string( $canonical ) || '' === $canonical ) {
			return $canonical;
		}
		if ( ! ( is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) ) || is_post_type_archive( 'product' ) || is_category() || is_tag() ) ) {
			return $canonical;
		}
		if ( preg_match( '#/page/' . $paged . '/?$#', $canonical ) ) {
			return $canonical;
		}
		$base = trailingslashit( preg_replace( '#/page/\d+/?$#', '', untrailingslashit( $canonical ) ) );
		return $base . 'page/' . $paged . '/';
	}
}
