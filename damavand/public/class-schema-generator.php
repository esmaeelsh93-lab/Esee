<?php
/**
 * Schema / JSON-LD generator with SEO-plugin coexistence policy.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Schema_Generator
 */
class Shojaei_SEO_Schema_Generator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'schema' ) ) {
			return;
		}
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'schema' ) ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'output_schema' ), 5 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'bust_product_schema_cache' ), 20, 1 );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'bust_product_schema_cache' ), 20, 1 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'bust_variation_schema_cache' ), 20, 3 );
	}

	/**
	 * Output JSON-LD schema in head — defers Product/Breadcrumb/Article/Site/Collection
	 * when a primary SEO plugin is active and respect mode is on.
	 */
	public function output_schema(): void {
		// No rich results on noindex / commerce / faceted URLs — crawl budget first.
		if ( class_exists( 'Damavand_Robots' ) && Damavand_Robots::should_skip_schema() ) {
			return;
		}

		$emit_product    = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::should_emit_product_schema()
			: ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_product_enabled', 'yes' ) && ! Shojaei_SEO_Helpers::is_rank_math_active() );
		$emit_breadcrumb   = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::should_emit_breadcrumb_schema()
			: ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_breadcrumb_enabled', 'yes' ) && ! Shojaei_SEO_Helpers::is_rank_math_active() );
		$emit_faq          = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::should_emit_faq_schema()
			: ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_faq_enabled', 'yes' ) );
		$emit_article      = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::should_emit_article_schema()
			: 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_article_enabled', 'yes' );
		$emit_site         = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::should_emit_site_schema()
			: 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_site_enabled', 'yes' );
		$emit_collection   = class_exists( 'Shojaei_SEO_Integration' )
			? Shojaei_SEO_Integration::should_emit_collection_schema()
			: 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_collection_enabled', 'yes' );
		$emit_itemlist     = 'yes' === (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_itemlist_enabled', 'yes' );

		if ( $emit_site && is_front_page() ) {
			$this->output_site_graph();
			return;
		}

		if ( $emit_collection && $this->is_collection_context() ) {
			$this->output_collection_graph( $emit_itemlist, $emit_breadcrumb );
			return;
		}

		if ( is_product() ) {
			$this->output_product_page_graph( $emit_product, $emit_breadcrumb, $emit_faq );
			return;
		}

		if ( is_singular() ) {
			if ( $emit_article ) {
				$this->output_article_schema();
			}
			if ( $emit_breadcrumb ) {
				$this->output_breadcrumb_schema();
			}
			if ( $emit_faq ) {
				$this->output_faq_schema();
			}
		}
	}

	/**
	 * Drop cached Product JSON-LD after price/stock/title change.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function bust_product_schema_cache( $product_id ): void {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return;
		}
		foreach ( array( 'v2', 'v3', 'v4', 'v5', 'v6' ) as $ver ) {
			delete_transient( 'shojaei_seo_schema_product_' . $ver . '_' . $product_id );
		}
	}

	/**
	 * Variation stock change should refresh parent Product schema.
	 *
	 * @param int   $variation_id Variation ID.
	 * @param mixed $status       Status.
	 * @param mixed $variation    Variation product.
	 */
	public static function bust_variation_schema_cache( $variation_id, $status = '', $variation = null ): void {
		$parent = 0;
		if ( $variation && is_object( $variation ) && method_exists( $variation, 'get_parent_id' ) ) {
			$parent = (int) $variation->get_parent_id();
		} elseif ( function_exists( 'wc_get_product' ) ) {
			$v = wc_get_product( absint( $variation_id ) );
			if ( $v ) {
				$parent = (int) $v->get_parent_id();
			}
		}
		if ( $parent > 0 ) {
			self::bust_product_schema_cache( $parent );
		}
		self::bust_product_schema_cache( $variation_id );
	}

	/**
	 * Whether current request is a taxonomy/shop archive suitable for CollectionPage.
	 */
	private function is_collection_context(): bool {
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}
		return is_category() || is_tag() || is_tax();
	}

	/**
	 * Single @graph for product pages: Organization → WebSite → BreadcrumbList → Product (+ FAQ).
	 */
	private function output_product_page_graph( bool $emit_product, bool $emit_breadcrumb, bool $emit_faq ): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$cache_key = 'shojaei_seo_schema_product_v6_' . $product->get_id();
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$graph = self::build_product_page_graph( $product, $emit_product, $emit_breadcrumb, $emit_faq );
		if ( empty( $graph['@graph'] ) ) {
			return;
		}
		self::print_json_ld( 'graph', $graph, $cache_key );
	}

	/**
	 * Unified product-page schema graph (front + admin preview).
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	public static function build_product_page_graph( WC_Product $product, bool $emit_product = true, bool $emit_breadcrumb = true, bool $emit_faq = true ): array {
		$nodes        = array();
		$org_id       = self::entity_id( 'organization' );
		$website_id   = self::entity_id( 'website' );
		$permalink    = get_permalink( $product->get_id() );
		$product_id   = trailingslashit( $permalink ) . '#product';
		$breadcrumb_id = trailingslashit( $permalink ) . '#breadcrumb';
		$webpage_id   = trailingslashit( $permalink ) . '#webpage';

		$org = self::organization_block();
		$org['@id'] = $org_id;
		$nodes[]    = $org;

		$nodes[] = array(
			'@type'     => 'WebSite',
			'@id'       => $website_id,
			'url'       => home_url( '/' ),
			'name'      => get_bloginfo( 'name' ),
			'publisher' => array( '@id' => $org_id ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);

		$webpage = array(
			'@type'      => 'WebPage',
			'@id'        => $webpage_id,
			'url'        => $permalink,
			'name'       => $product->get_name(),
			'isPartOf'   => array( '@id' => $website_id ),
			'about'      => array( '@id' => $product_id ),
			'publisher'  => array( '@id' => $org_id ),
			'inLanguage' => get_bloginfo( 'language' ) ?: 'fa-IR',
		);

		if ( $emit_breadcrumb ) {
			$crumb_items = self::build_breadcrumb_items_for_post( get_post( $product->get_id() ) );
			if ( count( $crumb_items ) >= 2 ) {
				$nodes[] = array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $breadcrumb_id,
					'itemListElement' => $crumb_items,
				);
				$webpage['breadcrumb'] = array( '@id' => $breadcrumb_id );
			}
		}

		if ( $emit_product ) {
			$product_node         = self::build_product_schema( $product );
			$product_node['@id']  = $product_id;
			$product_node['mainEntityOfPage'] = array( '@id' => $webpage_id );
			unset( $product_node['@context'] );
			$nodes[] = $product_node;
			$webpage['mainEntity'] = array( '@id' => $product_id );
		}

		$faq_entities = $emit_faq ? self::faq_entities_for_post( (int) $product->get_id() ) : array();
		if ( ! empty( $faq_entities ) ) {
			$faq_id  = trailingslashit( $permalink ) . '#faq';
			$nodes[] = array(
				'@type'      => 'FAQPage',
				'@id'        => $faq_id,
				'mainEntity' => $faq_entities,
				'isPartOf'   => array( '@id' => $webpage_id ),
			);
			$webpage['hasPart'] = array( '@id' => $faq_id );
		}

		$nodes[] = $webpage;

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);
	}

	/**
	 * Stable #fragment ids for graph entities.
	 */
	private static function entity_id( string $slug ): string {
		return trailingslashit( home_url( '/' ) ) . '#' . ltrim( $slug, '#' );
	}

	/**
	 * Output Product schema (legacy single-node helper kept for callers).
	 */
	private function output_product_schema(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$this->output_product_page_graph( true, true, true );
	}

	/**
	 * Product JSON-LD node (no @context — embeddable in @graph).
	 *
	 * Product.name is always the real WooCommerce product name (never SEO title).
	 * SEO meta description may enrich description only.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	public static function build_product_schema( WC_Product $product ): array {
		$post_id = $product->get_id();
		// Real catalog name — do NOT replace with SEO title ("خرید … | فروشگاه").
		$name = $product->get_name();
		$desc = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );

		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$seo_desc = Damavand_SEO_Meta::get_description( $post_id, false );
			if ( '' !== $seo_desc ) {
				$desc = $seo_desc;
			}
		}

		$images = self::product_image_urls( $product );
		$schema = array(
			'@type'       => 'Product',
			'name'        => $name,
			'description' => $desc,
			'sku'         => $product->get_sku() ?: (string) $product->get_id(),
			'url'         => get_permalink( $post_id ),
			'offers'      => self::product_offers( $product ),
		);

		if ( ! empty( $images ) ) {
			$schema['image'] = 1 === count( $images ) ? $images[0] : $images;
		}

		$brand = self::resolve_brand_name( $product );
		if ( '' !== $brand ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		$gtin = self::resolve_gtin( $product );
		if ( '' !== $gtin ) {
			$len = strlen( preg_replace( '/\D+/', '', $gtin ) );
			$key = 'gtin';
			if ( 8 === $len ) {
				$key = 'gtin8';
			} elseif ( 12 === $len ) {
				$key = 'gtin12';
			} elseif ( 13 === $len ) {
				$key = 'gtin13';
			} elseif ( 14 === $len ) {
				$key = 'gtin14';
			}
			$schema[ $key ] = $gtin;
		}

		$mpn = self::resolve_mpn( $product );
		if ( '' !== $mpn ) {
			$schema['mpn'] = $mpn;
		}

		$rating_count = (int) $product->get_rating_count();
		$review_count = method_exists( $product, 'get_review_count' )
			? (int) $product->get_review_count()
			: $rating_count;
		if ( $rating_count > 0 || $review_count > 0 ) {
			$agg = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (string) $product->get_average_rating(),
				'bestRating'  => '5',
				'worstRating' => '1',
			);
			// ratingCount = تعداد امتیازها؛ reviewCount = تعداد دیدگاه‌های واقعی.
			if ( $rating_count > 0 ) {
				$agg['ratingCount'] = $rating_count;
			}
			if ( $review_count > 0 ) {
				$agg['reviewCount'] = $review_count;
			} elseif ( $rating_count > 0 ) {
				// Fallback only when WC has ratings but no separate review count API.
				$agg['ratingCount'] = $rating_count;
			}
			$schema['aggregateRating'] = $agg;
		}

		$reviews = self::product_review_nodes( $product );
		if ( ! empty( $reviews ) ) {
			$schema['review'] = 1 === count( $reviews ) ? $reviews[0] : $reviews;
		}

		if ( $product->is_type( 'variable' ) ) {
			$variants = self::product_variant_nodes( $product );
			if ( ! empty( $variants ) ) {
				$schema['hasVariant'] = $variants;
			}
		}

		return $schema;
	}

	/**
	 * Brand from attributes / product_brand taxonomy.
	 */
	private static function resolve_brand_name( WC_Product $product ): string {
		$candidates = array(
			$product->get_attribute( 'brand' ),
			$product->get_attribute( 'pa_brand' ),
			$product->get_attribute( 'pa_brands' ),
		);
		foreach ( $candidates as $c ) {
			$c = trim( wp_strip_all_tags( (string) $c ) );
			if ( '' !== $c ) {
				return $c;
			}
		}
		if ( taxonomy_exists( 'product_brand' ) ) {
			$terms = get_the_terms( $product->get_id(), 'product_brand' );
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return (string) $terms[0]->name;
			}
		}
		if ( taxonomy_exists( 'pwb-brand' ) ) {
			$terms = get_the_terms( $product->get_id(), 'pwb-brand' );
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return (string) $terms[0]->name;
			}
		}
		return '';
	}

	/**
	 * GTIN / barcode from WooCommerce core or common meta keys.
	 */
	private static function resolve_gtin( WC_Product $product ): string {
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$v = trim( (string) $product->get_global_unique_id() );
			if ( '' !== $v ) {
				return $v;
			}
		}
		$keys = array( '_global_unique_id', '_gtin', '_gtin13', '_wpm_gtin_code', 'hwp_product_gtin', '_alg_ean' );
		foreach ( $keys as $key ) {
			$v = trim( (string) $product->get_meta( $key, true ) );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return '';
	}

	/**
	 * Manufacturer part number.
	 */
	private static function resolve_mpn( WC_Product $product ): string {
		$keys = array( '_mpn', 'mpn', '_manufacturer_part_number' );
		foreach ( $keys as $key ) {
			$v = trim( (string) $product->get_meta( $key, true ) );
			if ( '' !== $v ) {
				return $v;
			}
		}
		$attr = trim( (string) $product->get_attribute( 'mpn' ) );
		return $attr;
	}

	/**
	 * Up to 5 approved product reviews as Review nodes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function product_review_nodes( WC_Product $product ): array {
		$comments = get_comments(
			array(
				'post_id' => $product->get_id(),
				'status'  => 'approve',
				'type'    => 'review',
				'number'  => 5,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);
		if ( empty( $comments ) ) {
			return array();
		}
		$out = array();
		foreach ( $comments as $c ) {
			$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
			$node   = array(
				'@type'         => 'Review',
				'author'        => array(
					'@type' => 'Person',
					'name'  => $c->comment_author ? $c->comment_author : __( 'خریدار', 'shojaei-seo-for-woo' ),
				),
				'datePublished' => gmdate( 'c', strtotime( $c->comment_date_gmt . ' UTC' ) ),
				'reviewBody'    => wp_strip_all_tags( (string) $c->comment_content ),
			);
			if ( $rating > 0 ) {
				$node['reviewRating'] = array(
					'@type'       => 'Rating',
					'ratingValue' => (string) $rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				);
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * Compact ProductGroup variants for variable products (max 12).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function product_variant_nodes( WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return array();
		}
		if ( ! method_exists( $product, 'get_available_variations' ) ) {
			return array();
		}
		$children = $product->get_available_variations();
		if ( empty( $children ) || ! is_array( $children ) ) {
			return array();
		}
		$currency = Shojaei_SEO_Helpers::get_currency_code();
		$schema_currency = $currency;
		$rial_factor      = 1.0;
		if ( 'IRT' === $currency ) {
			$schema_currency = 'IRR';
			$rial_factor      = 10.0;
		}
		$out   = array();
		$count = 0;
		foreach ( $children as $row ) {
			if ( $count >= 12 ) {
				break;
			}
			$vid = isset( $row['variation_id'] ) ? (int) $row['variation_id'] : 0;
			if ( $vid < 1 ) {
				continue;
			}
			$variation = wc_get_product( $vid );
			if ( ! $variation instanceof WC_Product ) {
				continue;
			}
			$vname = $variation->get_name();
			$node  = array(
				'@type' => 'Product',
				'@id'   => trailingslashit( get_permalink( $product->get_id() ) ) . '#variation-' . $vid,
				'name'  => $vname,
				'sku'   => $variation->get_sku() ?: (string) $vid,
				'offers'=> array(
					'@type'         => 'Offer',
					'price'         => self::schema_price_string( $variation->get_price(), $rial_factor ),
					'priceCurrency' => $schema_currency,
					'availability'  => $variation->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
					'url'           => get_permalink( $product->get_id() ),
					'itemCondition' => 'https://schema.org/NewCondition',
					'seller'        => array( '@id' => self::entity_id( 'organization' ) ),
				),
			);
			$img = wp_get_attachment_url( (int) $variation->get_image_id() );
			if ( $img ) {
				$node['image'] = $img;
			}
			$out[] = $node;
			++$count;
		}
		return $out;
	}

	/**
	 * Admin preview blocks for a post (no HTTP fetch).
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{kind:string,schema:array<string,mixed>}>
	 */
	public static function preview_for_post( int $post_id ): array {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return array();
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$blocks = array();
		if ( 'product' === $post->post_type && class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::should_emit_product_schema() && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product instanceof WC_Product ) {
				$emit_bc  = class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::should_emit_breadcrumb_schema();
				$emit_faq = class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::should_emit_faq_schema();
				$blocks[] = array(
					'kind'   => 'graph',
					'schema' => self::build_product_page_graph( $product, true, $emit_bc, $emit_faq ),
				);
			}
		} elseif ( in_array( $post->post_type, array( 'post', 'page' ), true ) && class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::should_emit_article_schema() ) {
			$article = self::build_article_schema( $post );
			if ( ! empty( $article ) ) {
				$blocks[] = array(
					'kind'   => 'post' === $post->post_type ? 'article' : 'webpage',
					'schema' => $article,
				);
			}
		}

		if ( 'product' !== $post->post_type && class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::should_emit_breadcrumb_schema() ) {
			$crumb = self::build_breadcrumb_schema( $post );
			if ( ! empty( $crumb ) ) {
				$blocks[] = array(
					'kind'   => 'breadcrumb',
					'schema' => $crumb,
				);
			}
		}

		if ( 'product' !== $post->post_type && class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::should_emit_faq_schema() ) {
			$entities = self::faq_entities_for_post( $post_id );
			if ( ! empty( $entities ) ) {
				$blocks[] = array(
					'kind'   => 'faq',
					'schema' => array(
						'@context'   => 'https://schema.org',
						'@type'      => 'FAQPage',
						'mainEntity' => $entities,
					),
				);
			}
		}

		// ItemList is archive/category only — never previewed as product-page schema.
		return $blocks;
	}

	/**
	 * Offer or AggregateOffer block for a product.
	 *
	 * Google rejects IRT (not ISO 4217). When the plugin currency is IRT (تومان),
	 * JSON-LD only emits IRR and multiplies prices by 10 (ریال). WooCommerce /
	 * database / front-end display prices are untouched — conversion happens
	 * solely when building this offers array.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string,mixed>
	 */
	private static function product_offers( WC_Product $product ): array {
		$currency     = Shojaei_SEO_Helpers::get_currency_code();
		$url          = get_permalink( $product->get_id() );
		$availability = $product->is_in_stock()
			? 'https://schema.org/InStock'
			: 'https://schema.org/OutOfStock';

		// Schema-only mapping: IRT → IRR (+ ×10). Other codes pass through.
		$schema_currency = $currency;
		$rial_factor      = 1.0;
		if ( 'IRT' === $currency ) {
			$schema_currency = 'IRR';
			$rial_factor      = 10.0;
		}

		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			if ( ! empty( $prices['price'] ) && is_array( $prices['price'] ) ) {
				$active = array_filter(
					$prices['price'],
					static function ( $price ) {
						return '' !== (string) $price && null !== $price;
					}
				);
				if ( ! empty( $active ) ) {
					return array(
						'@type'           => 'AggregateOffer',
						'lowPrice'        => self::schema_price_string( min( $active ), $rial_factor ),
						'highPrice'       => self::schema_price_string( max( $active ), $rial_factor ),
						'offerCount'      => count( $active ),
						'priceCurrency'   => $schema_currency,
						'availability'    => $availability,
						'url'             => $url,
						'itemCondition'   => 'https://schema.org/NewCondition',
						'seller'          => array( '@id' => self::entity_id( 'organization' ) ),
						'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
					);
				}
			}
		}

		$offer = array(
			'@type'           => 'Offer',
			'price'           => self::schema_price_string( $product->get_price(), $rial_factor ),
			'priceCurrency'   => $schema_currency,
			'availability'    => $availability,
			'url'             => $url,
			'itemCondition'   => 'https://schema.org/NewCondition',
			'seller'          => array( '@id' => self::entity_id( 'organization' ) ),
			'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
		);
		if ( '' !== (string) $product->get_sku() ) {
			$offer['sku'] = (string) $product->get_sku();
		}
		return $offer;
	}

	/**
	 * Format a numeric price for Product JSON-LD (optional toman→rial factor).
	 *
	 * @param mixed $price  Raw WooCommerce price (DB/display value, not mutated).
	 * @param float $factor Multiplier applied only for schema output (10 when IRT→IRR).
	 */
	private static function schema_price_string( $price, float $factor = 1.0 ): string {
		if ( '' === (string) $price || null === $price ) {
			return '';
		}
		$num = (float) $price * $factor;
		if ( is_finite( $num ) && abs( $num - round( $num ) ) < 0.00001 ) {
			return (string) (int) round( $num );
		}
		return (string) $num;
	}

	/**
	 * Featured + gallery image URLs.
	 *
	 * @param WC_Product $product Product.
	 * @return string[]
	 */
	private static function product_image_urls( WC_Product $product ): array {
		$ids  = array_filter(
			array_merge(
				array( $product->get_image_id() ),
				$product->get_gallery_image_ids()
			)
		);
		$urls = array();
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_url( (int) $id );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Output Article / WebPage schema for singular post or page.
	 */
	private function output_article_schema(): void {
		$post_id = get_the_ID();
		if ( ! $post_id || ! is_singular( array( 'post', 'page' ) ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$schema = self::build_article_schema( $post );
		if ( empty( $schema ) ) {
			return;
		}

		self::print_json_ld( 'post' === $post->post_type ? 'article' : 'webpage', $schema );
	}

	/**
	 * Article / WebPage graph for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	public static function build_article_schema( WP_Post $post ): array {
		$post_id  = (int) $post->ID;
		$is_post  = 'post' === $post->post_type;
		$headline = get_the_title( $post );
		$desc     = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40, '…' );

		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$seo_title = Damavand_SEO_Meta::get_title( $post_id, false );
			if ( '' !== $seo_title ) {
				$headline = $seo_title;
			}
			$seo_desc = Damavand_SEO_Meta::get_description( $post_id, false );
			if ( '' !== $seo_desc ) {
				$desc = $seo_desc;
			}
		}

		$schema = array(
			'@context'         => 'https://schema.org',
			'@type'            => $is_post ? 'BlogPosting' : 'WebPage',
			'headline'         => $headline,
			'description'      => wp_strip_all_tags( $desc ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
			'publisher'        => self::organization_block(),
		);

		if ( $is_post ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			);
		}

		$thumb_id = get_post_thumbnail_id( $post );
		if ( $thumb_id ) {
			$img = wp_get_attachment_url( $thumb_id );
			if ( $img ) {
				$schema['image'] = $img;
			}
		}

		return $schema;
	}

	/**
	 * Organization + WebSite as one @graph on the front page.
	 */
	private function output_site_graph(): void {
		$org_id     = self::entity_id( 'organization' );
		$website_id = self::entity_id( 'website' );
		$org        = self::organization_block();
		$org['@id'] = $org_id;
		$graph      = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$org,
				array(
					'@type'           => 'WebSite',
					'@id'             => $website_id,
					'url'             => home_url( '/' ),
					'name'            => get_bloginfo( 'name' ),
					'publisher'       => array( '@id' => $org_id ),
					'potentialAction' => array(
						'@type'       => 'SearchAction',
						'target'      => home_url( '/?s={search_term_string}' ),
						'query-input' => 'required name=search_term_string',
					),
				),
			),
		);
		self::print_json_ld( 'graph', $graph );
	}

	/**
	 * Legacy alias.
	 */
	private function output_site_schema(): void {
		$this->output_site_graph();
	}

	/**
	 * CollectionPage (+ ItemList of listed products) for shop/category archives.
	 */
	private function output_collection_graph( bool $emit_itemlist, bool $emit_breadcrumb = false ): void {
		$name = '';
		$desc = '';
		$url  = '';
		$list_name = '';

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			$name    = $shop_id > 0 ? get_the_title( $shop_id ) : __( 'فروشگاه', 'shojaei-seo-for-woo' );
			$url     = get_post_type_archive_link( 'product' ) ?: home_url( '/shop/' );
			$list_name = $name;
			if ( $shop_id > 0 && class_exists( 'Damavand_SEO_Meta' ) ) {
				$seo_desc = Damavand_SEO_Meta::get_description( $shop_id, false );
				if ( '' !== $seo_desc ) {
					$desc = $seo_desc;
				}
			}
			// CollectionPage.name stays shop title (not SEO "خرید..." style if set on shop page title meta — use page title).
		} else {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term ) {
				return;
			}
			$name      = $term->name;
			$list_name = $term->name;
			$desc      = term_description( $term, $term->taxonomy );
			$link      = get_term_link( $term );
			$url       = is_wp_error( $link ) ? '' : (string) $link;

			if ( class_exists( 'Damavand_SEO_Meta' ) ) {
				$seo_desc = (string) get_term_meta( $term->term_id, Damavand_SEO_Meta::DESC, true );
				if ( '' !== trim( $seo_desc ) ) {
					$desc = $seo_desc;
				}
				// Keep CollectionPage.name as term name (real category name), not SEO title.
			}
		}

		if ( '' === $url ) {
			return;
		}

		$org_id       = self::entity_id( 'organization' );
		$website_id   = self::entity_id( 'website' );
		$collection_id = trailingslashit( $url ) . '#collection';
		$itemlist_id  = trailingslashit( $url ) . '#itemlist';
		$org          = self::organization_block();
		$org['@id']   = $org_id;

		$collection = array(
			'@type'     => 'CollectionPage',
			'@id'       => $collection_id,
			'name'      => wp_strip_all_tags( $name ),
			'url'       => $url,
			'isPartOf'  => array( '@id' => $website_id ),
			'publisher' => array( '@id' => $org_id ),
		);
		$desc = wp_strip_all_tags( (string) $desc );
		if ( '' !== $desc ) {
			$collection['description'] = $desc;
		}

		$nodes = array(
			$org,
			array(
				'@type'     => 'WebSite',
				'@id'       => $website_id,
				'url'       => home_url( '/' ),
				'name'      => get_bloginfo( 'name' ),
				'publisher' => array( '@id' => $org_id ),
			),
			$collection,
		);

		if ( $emit_itemlist ) {
			$itemlist = self::build_archive_itemlist( $list_name, $url, $itemlist_id );
			if ( ! empty( $itemlist['itemListElement'] ) ) {
				$nodes[] = $itemlist;
				$collection['mainEntity'] = array( '@id' => $itemlist_id );
				// Re-assign updated collection node (index 2).
				$nodes[2] = $collection;
			}
		}

		self::print_json_ld(
			'graph',
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $nodes,
			)
		);
	}

	/**
	 * Live ItemList from the current archive loop / query (category/shop only).
	 *
	 * @return array<string,mixed>
	 */
	public static function build_archive_itemlist( string $name, string $url, string $itemlist_id = '' ): array {
		$items = array();
		$pos   = 1;

		global $wp_query;
		if ( $wp_query instanceof WP_Query && ! empty( $wp_query->posts ) ) {
			foreach ( $wp_query->posts as $p ) {
				if ( ! $p instanceof WP_Post || 'product' !== $p->post_type ) {
					continue;
				}
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $p->ID ) : null;
				$label   = $product instanceof WC_Product ? $product->get_name() : get_the_title( $p );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'url'      => get_permalink( $p ),
					'name'     => $label,
				);
				if ( $pos > 24 ) {
					break;
				}
			}
		}

		if ( empty( $items ) ) {
			return array();
		}

		$out = array(
			'@type'           => 'ItemList',
			'name'            => sprintf(
				/* translators: %s: category/shop name */
				__( 'محصولات %s', 'shojaei-seo-for-woo' ),
				wp_strip_all_tags( $name )
			),
			'url'             => $url,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
		if ( '' !== $itemlist_id ) {
			$out['@id'] = $itemlist_id;
		}
		return $out;
	}

	/**
	 * Legacy collection output.
	 */
	private function output_collection_schema(): void {
		$this->output_collection_graph(
			'yes' === (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_itemlist_enabled', 'yes' ),
			false
		);
	}

	/**
	 * Shared Organization block (name, url, logo).
	 *
	 * @return array<string,mixed>
	 */
	private static function organization_block(): array {
		$org = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo    = $logo_id ? wp_get_attachment_url( $logo_id ) : '';
		if ( ! $logo && function_exists( 'get_site_icon_url' ) ) {
			$logo = get_site_icon_url( 512 );
		}
		if ( $logo ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		return $org;
	}

	/**
	 * Output BreadcrumbList schema (product, post, page).
	 */
	private function output_breadcrumb_schema(): void {
		$schema = self::build_breadcrumb_schema_for_context();
		if ( empty( $schema ) ) {
			return;
		}
		self::print_json_ld( 'breadcrumb', $schema );
	}

	/**
	 * BreadcrumbList for admin preview.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	public static function build_breadcrumb_schema( WP_Post $post ): array {
		$items = self::build_breadcrumb_items_for_post( $post );
		if ( count( $items ) < 2 ) {
			return array();
		}
		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * Breadcrumb from current front-end query.
	 *
	 * @return array<string,mixed>
	 */
	private static function build_breadcrumb_schema_for_context(): array {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return array();
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		return self::build_breadcrumb_schema( $post );
	}

	/**
	 * Build breadcrumb ListItem nodes for a post (preview-safe).
	 *
	 * @param WP_Post $post Post.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_breadcrumb_items_for_post( WP_Post $post ): array {
		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'خانه', 'shojaei-seo-for-woo' ),
				'item'     => home_url( '/' ),
			),
		);
		$pos = 2;

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product instanceof WC_Product ) {
				return $items;
			}
			$terms = get_the_terms( $product->get_id(), 'product_cat' );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return $items;
			}
			$term  = $terms[0];
			$tlink = get_term_link( $term );
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $term->name,
				'item'     => is_wp_error( $tlink ) ? home_url( '/' ) : $tlink,
			);
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => $product->get_name(),
				'item'     => get_permalink( $product->get_id() ),
			);
			return $items;
		}

		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
				$cat   = $cats[0];
				$clink = get_category_link( $cat->term_id );
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $cat->name,
					'item'     => is_wp_error( $clink ) ? home_url( '/' ) : $clink,
				);
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => get_the_title( $post ),
				'item'     => get_permalink( $post ),
			);
			return $items;
		}

		if ( 'page' === $post->post_type ) {
			$ancestors = array_reverse( array_map( 'absint', get_post_ancestors( $post->ID ) ) );
			foreach ( $ancestors as $aid ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => get_the_title( $aid ),
					'item'     => get_permalink( $aid ),
				);
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos,
				'name'     => get_the_title( $post ),
				'item'     => get_permalink( $post ),
			);
			return $items;
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $pos,
			'name'     => get_the_title( $post ),
			'item'     => get_permalink( $post ),
		);
		return $items;
	}

	/**
	 * FAQ Question entities for a post (shared by graph + legacy FAQ print).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function faq_entities_for_post( int $post_id ): array {
		$faq = get_post_meta( $post_id, '_shojaei_seo_faq', true );
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			if ( class_exists( 'Damavand_FAQ_Box' ) ) {
				$faq = Damavand_FAQ_Box::get_stored( $post_id );
			}
		}
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return array();
		}
		$entities = array();
		foreach ( $faq as $row ) {
			$q = trim( (string) ( $row['question'] ?? $row['q'] ?? '' ) );
			$a = trim( (string) ( $row['answer'] ?? $row['a'] ?? '' ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $a ),
				),
			);
		}
		return $entities;
	}

	/**
	 * Output FAQPage schema from product/post meta.
	 */
	private function output_faq_schema(): void {
		if ( ! is_singular() ) {
			return;
		}
		$entities = self::faq_entities_for_post( (int) get_the_ID() );
		if ( empty( $entities ) ) {
			return;
		}
		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
		self::print_json_ld( 'faq', $schema );
	}

	/**
	 * Safe JSON-LD print — hex-encode < > & to block </script> breakout XSS.
	 *
	 * @param string               $kind      data-shojaei-seo attribute.
	 * @param array<string,mixed>  $schema    Schema graph.
	 * @param string|null          $cache_key Optional transient key to store markup.
	 */
	private static function print_json_ld( string $kind, array $schema, ?string $cache_key = null ): void {
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
		$json  = wp_json_encode( $schema, $flags );
		if ( false === $json ) {
			return;
		}
		// Defense in depth if a future flag drops HEX_TAG.
		$json   = str_replace( array( '</', '<!--' ), array( '<\/', '<\!--' ), $json );
		$output = '<script type="application/ld+json" data-shojaei-seo="' . esc_attr( $kind ) . '">' . $json . '</script>' . "\n";
		if ( $cache_key ) {
			set_transient( $cache_key, $output, DAY_IN_SECONDS );
		}
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* + str_replace harden script context.
	}
}
