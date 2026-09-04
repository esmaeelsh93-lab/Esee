<?php
/**
 * Admin schema validator — local JSON-LD health checks (not Google API).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Schema_Validator
 */
final class Damavand_Schema_Validator {

	/**
	 * Validate a product's generated graph without printing it.
	 *
	 * @param int $product_id Product ID.
	 * @return array{ok:bool,errors:string[],warnings:string[],types:string[],json:?string,graph:?array}
	 */
	public static function validate_product( int $product_id ): array {
		$result = array(
			'ok'       => false,
			'errors'   => array(),
			'warnings' => array(),
			'types'    => array(),
			'json'     => null,
			'graph'    => null,
		);

		if ( $product_id < 1 || ! function_exists( 'wc_get_product' ) ) {
			$result['errors'][] = __( 'شناسه محصول نامعتبر است.', 'shojaei-seo-for-woo' );
			return $result;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			$result['errors'][] = __( 'محصول یافت نشد.', 'shojaei-seo-for-woo' );
			return $result;
		}

		if ( ! class_exists( 'Shojaei_SEO_Schema_Generator' ) ) {
			$result['errors'][] = __( 'مولد Schema بارگذاری نشده است.', 'shojaei-seo-for-woo' );
			return $result;
		}

		$graph = Shojaei_SEO_Schema_Generator::build_product_page_graph( $product, true, true, true );

		if ( ! is_array( $graph ) || empty( $graph ) ) {
			$result['errors'][] = __( 'خروجی Schema خالی است.', 'shojaei-seo-for-woo' );
			return $result;
		}

		$json = wp_json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			$result['errors'][] = __( 'JSON نامعتبر — encode شکست خورد.', 'shojaei-seo-for-woo' );
			return $result;
		}

		$result['json']  = $json;
		$result['graph'] = $graph;

		$nodes = array();
		if ( isset( $graph['@graph'] ) && is_array( $graph['@graph'] ) ) {
			$nodes = $graph['@graph'];
		} elseif ( isset( $graph['@type'] ) ) {
			$nodes = array( $graph );
		}

		$product_node = null;
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$type = $node['@type'] ?? '';
			if ( is_array( $type ) ) {
				foreach ( $type as $t ) {
					$result['types'][] = (string) $t;
				}
			} else {
				$result['types'][] = (string) $type;
			}
			if ( self::node_is_type( $node, 'Product' ) || self::node_is_type( $node, 'ProductGroup' ) ) {
				$product_node = $node;
			}
		}
		$result['types'] = array_values( array_unique( array_filter( $result['types'] ) ) );

		if ( ! $product_node ) {
			$result['errors'][] = __( 'نود Product/ProductGroup در Graph یافت نشد.', 'shojaei-seo-for-woo' );
		} else {
			$is_group = self::node_is_type( $product_node, 'ProductGroup' );

			$name = isset( $product_node['name'] ) ? trim( (string) $product_node['name'] ) : '';
			if ( '' === $name ) {
				$result['errors'][] = __( 'Product.name خالی است.', 'shojaei-seo-for-woo' );
			} else {
				$wc_name = trim( wp_strip_all_tags( (string) $product->get_name() ) );
				if ( '' !== $wc_name && 0 === strpos( $name, 'خرید ' ) && false !== strpos( $name, '|' ) ) {
					$result['warnings'][] = __( 'Product.name شبیه عنوان سئو است (خرید … | …) — باید نام واقعی محصول باشد.', 'shojaei-seo-for-woo' );
				}
			}

			if ( empty( $product_node['description'] ) ) {
				$result['warnings'][] = __( 'description خالی است — Merchant listings ضعیف می‌شود.', 'shojaei-seo-for-woo' );
			}

			if ( empty( $product_node['image'] ) ) {
				$result['warnings'][] = __( 'Product.image خالی است — Rich Result ضعیف می‌شود.', 'shojaei-seo-for-woo' );
			}

			if ( $is_group ) {
				if ( empty( $product_node['hasVariant'] ) || ! is_array( $product_node['hasVariant'] ) ) {
					$result['errors'][] = __( 'ProductGroup بدون hasVariant.', 'shojaei-seo-for-woo' );
				} elseif ( ! empty( $product_node['offers'] ) ) {
					$result['warnings'][] = __( 'ProductGroup نباید offers والد داشته باشد — قیمت روی variantهاست.', 'shojaei-seo-for-woo' );
				} else {
					foreach ( $product_node['hasVariant'] as $variant ) {
						if ( ! is_array( $variant ) ) {
							continue;
						}
						if ( empty( $variant['description'] ) ) {
							$result['warnings'][] = __( 'Variant بدون description.', 'shojaei-seo-for-woo' );
						}
						if ( empty( $variant['brand'] ) && empty( $variant['gtin'] ) && empty( $variant['gtin13'] ) ) {
							$result['warnings'][] = __( 'Variant بدون brand/gtin.', 'shojaei-seo-for-woo' );
						}
						$voffer = $variant['offers'] ?? null;
						if ( is_array( $voffer ) ) {
							if ( empty( $voffer['shippingDetails'] ) ) {
								$result['warnings'][] = __( 'Offer variant بدون shippingDetails.', 'shojaei-seo-for-woo' );
							}
							if ( empty( $voffer['hasMerchantReturnPolicy'] ) ) {
								$result['warnings'][] = __( 'Offer variant بدون hasMerchantReturnPolicy.', 'shojaei-seo-for-woo' );
							}
						}
					}
				}
			} else {
				$offers = $product_node['offers'] ?? null;
				if ( empty( $offers ) ) {
					$result['errors'][] = __( 'Product.offers وجود ندارد.', 'shojaei-seo-for-woo' );
				} else {
					$offer_list = isset( $offers['@type'] ) ? array( $offers ) : ( is_array( $offers ) ? $offers : array() );
					foreach ( $offer_list as $offer ) {
						if ( ! is_array( $offer ) ) {
							continue;
						}
						$currency = isset( $offer['priceCurrency'] ) ? strtoupper( (string) $offer['priceCurrency'] ) : '';
						if ( 'IRT' === $currency ) {
							$result['errors'][] = __( 'priceCurrency=IRT نامعتبر برای Google است — باید IRR باشد.', 'shojaei-seo-for-woo' );
						} elseif ( '' !== $currency && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
							$result['warnings'][] = sprintf(
								/* translators: %s: currency code */
								__( 'priceCurrency مشکوک: %s', 'shojaei-seo-for-woo' ),
								$currency
							);
						}
						if ( ! isset( $offer['price'] ) && ! isset( $offer['lowPrice'] ) ) {
							$result['warnings'][] = __( 'Offer بدون price/lowPrice.', 'shojaei-seo-for-woo' );
						}
						if ( empty( $offer['availability'] ) ) {
							$result['warnings'][] = __( 'Offer.availability خالی است.', 'shojaei-seo-for-woo' );
						}
					}
				}
			}

			if ( ! empty( $product_node['aggregateRating'] ) && is_array( $product_node['aggregateRating'] ) ) {
				$ar = $product_node['aggregateRating'];
				$rc = isset( $ar['ratingCount'] ) ? (int) $ar['ratingCount'] : 0;
				$rv = isset( $ar['reviewCount'] ) ? (int) $ar['reviewCount'] : 0;
				if ( $rc < 1 && $rv < 1 ) {
					$result['warnings'][] = __( 'aggregateRating بدون شمارنده معتبر.', 'shojaei-seo-for-woo' );
				}
			}
		}

		if ( in_array( 'ItemList', $result['types'], true ) ) {
			$result['errors'][] = __( 'ItemList روی صفحه محصول نباید باشد — فقط آرشیو/دسته.', 'shojaei-seo-for-woo' );
		}

		$result['ok'] = empty( $result['errors'] );
		return $result;
	}

	/**
	 * @param array  $node Node.
	 * @param string $type Type name.
	 */
	private static function node_is_type( array $node, string $type ): bool {
		$t = $node['@type'] ?? '';
		if ( is_array( $t ) ) {
			return in_array( $type, $t, true );
		}
		return $type === (string) $t;
	}
}
