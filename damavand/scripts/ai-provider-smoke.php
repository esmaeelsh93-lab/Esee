<?php
/**
 * Smoke tests for AI provider routing (no live API keys required).
 *
 * Usage: php damavand/scripts/ai-provider-smoke.php
 *
 * @package Shojaei_SEO_For_Woo
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/../' );

$root = dirname( __DIR__ );
require_once $root . '/includes/class-shojaei-seo-helpers.php';
require_once $root . '/includes/class-shojaei-seo-ai-client.php';

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) { // phpcs:ignore
		global $smoke_options;
		return $smoke_options[ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = true ) { // phpcs:ignore
		global $smoke_options;
		$smoke_options[ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) { // phpcs:ignore
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) { // phpcs:ignore
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) { // phpcs:ignore
		return strip_tags( (string) $text );
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) { // phpcs:ignore
		return rtrim( (string) $string, '/\\' );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore
		return $value;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) { // phpcs:ignore
		return 'https://example.test' . $path;
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) { // phpcs:ignore
		return 'Test Shop';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $str ) { // phpcs:ignore
		return strip_tags( (string) $str );
	}
}

$smoke_options = array(
	'shojaei_seo_ai_provider' => 'gemini',
	'shojaei_seo_ai_model'    => 'meta-llama/llama-3.3-70b-instruct',
);

$failures = 0;

function assert_eq( string $label, $expected, $actual ): void {
	global $failures;
	if ( $expected !== $actual ) {
		++$failures;
		fwrite( STDERR, "FAIL: {$label}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
		return;
	}
	echo "OK: {$label}\n";
}

assert_eq( 'normalize groq → openrouter', 'openrouter', Shojaei_SEO_AI_Client::normalize_provider( 'groq' ) );
assert_eq( 'normalize gemini', 'gemini', Shojaei_SEO_AI_Client::normalize_provider( 'gemini' ) );
assert_eq( 'provider() reads gemini', 'gemini', Shojaei_SEO_AI_Client::provider() );
assert_eq(
	'map openrouter model on gemini → default flash',
	'gemini-3.6-flash',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'meta-llama/llama-3.3-70b-instruct', 'gemini' )
);
assert_eq(
	'retire gemini-2.0-flash',
	'gemini-3.6-flash',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'gemini-2.0-flash', 'gemini' )
);
assert_eq(
	'gemini endpoint',
	'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
	Shojaei_SEO_AI_Client::gemini_endpoint( 'gemini-3.6-flash' )
);
assert_eq(
	'route gemini when configured',
	'gemini',
	Shojaei_SEO_AI_Client::route_from_api_key( 'any-auth-key-without-prefix' )
);

$smoke_options['shojaei_seo_ai_provider'] = 'openrouter';
assert_eq(
	'route sk-or when openrouter configured',
	'openrouter',
	Shojaei_SEO_AI_Client::route_from_api_key( 'sk-or-test-key' )
);
assert_eq(
	'openrouter model unchanged',
	'meta-llama/llama-3.3-70b-instruct',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'meta-llama/llama-3.3-70b-instruct', 'openrouter' )
);

$presets = Shojaei_SEO_AI_Client::model_presets();
assert_eq( 'gemini presets exist', true, isset( $presets['gemini'] ) && count( $presets['gemini'] ) >= 3 );
assert_eq( 'active providers', array( 'openrouter', 'gemini' ), Shojaei_SEO_AI_Client::active_providers() );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "\nAll AI provider smoke checks passed.\n";
