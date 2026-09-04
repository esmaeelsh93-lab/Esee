<?php
/**
 * Smoke tests for ProductGroup / variant merchant schema (no WordPress bootstrap).
 *
 * Usage: php damavand/scripts/schema-productgroup-smoke.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/../' );

$root = dirname( __DIR__ );

if ( ! class_exists( 'WC_Product' ) ) {
	class WC_Product {} // phpcs:ignore
}

require_once $root . '/public/class-schema-generator.php';

if ( ! class_exists( 'Shojaei_SEO_Helpers' ) ) {
	class Shojaei_SEO_Helpers {
		public static function get_currency_code(): string {
			return 'IRT';
		}
	}
}

if ( ! class_exists( 'Damavand_FAQ_Box' ) ) {
	class Damavand_FAQ_Box {
		public static function get_returns_url(): string {
			return 'https://example.test/returns/';
		}
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $id = 0 ) { // phpcs:ignore
		return 'https://example.test/product/demo/';
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { // phpcs:ignore
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) { // phpcs:ignore
		return 'بهار شاپ';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) { // phpcs:ignore
		return strip_tags( (string) $text );
	}
}
if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $num = 55, $more = null ) { // phpcs:ignore
		return (string) $text;
	}
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) { // phpcs:ignore
		return $id > 0 ? 'https://example.test/img.jpg' : false;
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) { // phpcs:ignore
		return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', (string) $title ) );
	}
}
if ( ! function_exists( 'get_comments' ) ) {
	function get_comments( $args = array() ) { // phpcs:ignore
		return array();
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) { // phpcs:ignore
		return rtrim( (string) $string, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) { // phpcs:ignore
		return false;
	}
}
if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $taxonomy ) { // phpcs:ignore
		return false;
	}
}
if ( ! function_exists( 'wc_get_product' ) ) {
	function wc_get_product( $id ) { // phpcs:ignore
		global $mock_variations;
		return $mock_variations[ $id ] ?? null;
	}
}

/**
 * Minimal WC_Product stub for schema smoke.
 */
class Mock_WC_Product extends WC_Product {
	private string $type;
	private int $id;
	private string $name;
	private string $sku;
	private string $short_desc;
	private array $variation_attrs;
	private array $variation_rows;
	private int $image_id;

	public function __construct( string $type, int $id, string $name, string $sku = '', string $short_desc = '' ) {
		$this->type       = $type;
		$this->id         = $id;
		$this->name       = $name;
		$this->sku        = $sku;
		$this->short_desc = $short_desc;
		$this->variation_attrs = array();
		$this->variation_rows  = array();
		$this->image_id        = 0;
	}

	public function get_id(): int { return $this->id; }
	public function get_name(): string { return $this->name; }
	public function get_sku(): string { return $this->sku; }
	public function get_short_description(): string { return $this->short_desc; }
	public function get_description(): string { return ''; }
	public function is_type( string $type ): bool { return $this->type === $type; }
	public function get_image_id(): int { return $this->image_id; }
	public function get_gallery_image_ids(): array { return array(); }
	public function get_rating_count(): int { return 0; }
	public function get_review_count(): int { return 0; }
	public function get_average_rating(): float { return 0.0; }
	public function get_price(): string { return '100000'; }
	public function is_in_stock(): bool { return true; }
	public function get_meta( $key, $single = true ) { return ''; } // phpcs:ignore
	public function get_attribute( $name ) { return ''; } // phpcs:ignore

	public function set_variation_attrs( array $attrs ): void {
		$this->variation_attrs = $attrs;
	}

	public function set_variation_rows( array $rows ): void {
		$this->variation_rows = $rows;
	}

	public function get_variation_attributes(): array {
		return $this->variation_attrs;
	}

	public function get_available_variations(): array {
		return $this->variation_rows;
	}
}

class Mock_WC_Variation extends Mock_WC_Product {
	public function __construct( int $id, string $name, string $price, string $sku = '' ) {
		parent::__construct( 'variation', $id, $name, $sku );
		$this->price = $price;
	}

	private string $price;

	public function get_price(): string { return $this->price; }
	public function get_parent_id(): int { return 100; }
}

$failures = 0;

function assert_true( string $label, bool $cond ): void {
	global $failures;
	if ( ! $cond ) {
		++$failures;
		fwrite( STDERR, "FAIL: {$label}\n" );
		return;
	}
	echo "OK: {$label}\n";
}

$parent = new Mock_WC_Product( 'variable', 100, 'تیشرت رگلان', 'P-100', 'تیشرت دخترانه با طرح عنکبوت' );
$parent->set_variation_attrs( array( 'pa_color' => array( 'black', 'white' ) ) );
$parent->set_variation_rows(
	array(
		array( 'variation_id' => 4091 ),
		array( 'variation_id' => 4092 ),
	)
);

global $mock_variations;
$mock_variations = array(
	4091 => new Mock_WC_Variation( 4091, 'تیشرت رگلان - مشکی', '8970000', '4091' ),
	4092 => new Mock_WC_Variation( 4092, 'تیشرت رگلان - سفید', '8970000', '4092' ),
);

$schema = Shojaei_SEO_Schema_Generator::build_product_schema( $parent );

assert_true( 'parent is ProductGroup', ( $schema['@type'] ?? '' ) === 'ProductGroup' );
assert_true( 'has productGroupID', ! empty( $schema['productGroupID'] ) );
assert_true( 'has variesBy', ! empty( $schema['variesBy'] ) && is_array( $schema['variesBy'] ) );
assert_true( 'parent has no offers', empty( $schema['offers'] ) );
assert_true( 'parent has description', ! empty( $schema['description'] ) );
assert_true( 'parent has brand fallback', ! empty( $schema['brand']['name'] ) );
assert_true( 'hasVariant count', isset( $schema['hasVariant'] ) && 2 === count( $schema['hasVariant'] ) );

$variant = $schema['hasVariant'][0];
assert_true( 'variant is Product', ( $variant['@type'] ?? '' ) === 'Product' );
assert_true( 'variant has description', ! empty( $variant['description'] ) );
assert_true( 'variant has brand', ! empty( $variant['brand']['name'] ) );
assert_true( 'variant offer currency IRR', ( $variant['offers']['priceCurrency'] ?? '' ) === 'IRR' );
assert_true( 'variant offer shippingDetails', ! empty( $variant['offers']['shippingDetails']['@type'] ) );
assert_true( 'variant offer return policy', ! empty( $variant['offers']['hasMerchantReturnPolicy']['@type'] ) );
assert_true( 'variant offer returnPolicyUrl', ! empty( $variant['offers']['hasMerchantReturnPolicy']['returnPolicyUrl'] ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "\nAll ProductGroup schema smoke checks passed.\n";
