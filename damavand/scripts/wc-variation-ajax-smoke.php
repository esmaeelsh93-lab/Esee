<?php
/**
 * Smoke test: WC bulk variation AJAX actions are recognized.
 *
 * Usage: php damavand/scripts/wc-variation-ajax-smoke.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/../' );

require_once dirname( __DIR__ ) . '/includes/class-shojaei-seo-helpers.php';

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { // phpcs:ignore
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { // phpcs:ignore
		return $value;
	}
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() { // phpcs:ignore
		return true;
	}
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

function with_action( string $action ): bool {
	$_REQUEST['action'] = $action;
	return Shojaei_SEO_Helpers::is_wc_product_editor_ajax();
}

assert_true( 'link_all_variations', with_action( 'woocommerce_link_all_variations' ) );
assert_true( 'add_attributes_and_variations', with_action( 'woocommerce_add_attributes_and_variations' ) );
assert_true( 'remove_variations', with_action( 'woocommerce_remove_variations' ) );
assert_true( 'save_variations', with_action( 'woocommerce_save_variations' ) );
assert_true( 'unrelated ajax rejected', ! with_action( 'woocommerce_add_to_cart' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "\nAll WC variation AJAX smoke checks passed.\n";
