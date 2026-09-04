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
assert_eq( 'normalize gemini → openrouter', 'openrouter', Shojaei_SEO_AI_Client::normalize_provider( 'gemini' ) );
assert_eq( 'provider() always openrouter', 'openrouter', Shojaei_SEO_AI_Client::provider() );
assert_eq(
	'map paid openrouter model → free default',
	'meta-llama/llama-3.3-70b-instruct:free',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'meta-llama/llama-3.3-70b-instruct', 'openrouter' )
);
assert_eq(
	'map gemini model → free default',
	'meta-llama/llama-3.3-70b-instruct:free',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'gemini-3.6-flash', 'openrouter' )
);
assert_eq(
	'map groq legacy id → free default',
	'meta-llama/llama-3.3-70b-instruct:free',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'llama-3.3-70b-versatile', 'openrouter' )
);
assert_eq(
	'route always openrouter',
	'openrouter',
	Shojaei_SEO_AI_Client::route_from_api_key( 'any-key' )
);
assert_eq(
	'free model unchanged',
	'qwen/qwen-2.5-7b-instruct:free',
	Shojaei_SEO_AI_Client::map_model_to_provider( 'qwen/qwen-2.5-7b-instruct:free', 'openrouter' )
);

$presets = Shojaei_SEO_AI_Client::model_presets();
assert_eq( 'openrouter presets are free', true, isset( $presets['openrouter'] ) && count( $presets['openrouter'] ) >= 3 );
foreach ( $presets['openrouter'] as $row ) {
	if ( ! str_ends_with( $row['id'], ':free' ) ) {
		++$failures;
		fwrite( STDERR, "FAIL: preset not free: {$row['id']}\n" );
	}
}
assert_eq( 'active providers', array( 'openrouter' ), Shojaei_SEO_AI_Client::active_providers() );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "\nAll AI provider smoke checks passed.\n";
