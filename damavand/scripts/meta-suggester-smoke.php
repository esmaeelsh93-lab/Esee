<?php
/**
 * Smoke tests for systemic meta suggestions (no WordPress bootstrap).
 *
 * Usage: php damavand/scripts/meta-suggester-smoke.php
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . '/../' );

$root = dirname( __DIR__ );
require_once $root . '/includes/class-damavand-persian-text.php';
require_once $root . '/includes/class-damavand-seo-templates.php';
require_once $root . '/includes/class-damavand-meta-suggester.php';

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) { // phpcs:ignore
		return strip_tags( (string) $text );
	}
}
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) { // phpcs:ignore
		return 'بهار شاپ';
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore
		return $text;
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

$short = '<p>کراپ زنانه نخی با دوخت تمیز و رنگ ثابت. مناسب استایل روزمره و مهمانی.</p>';
$long  = '<h2>معرفی</h2><p>این کراپ از پارچه نخی با بافت نرم تولید شده و در فصل گرم راحت است.</p>';

$result = Damavand_Meta_Suggester::suggest( 1, $short, $long, 'کراپ زنانه', 'کراپ زنانه نخی' );

assert_true( 'title not empty', '' !== trim( $result['title'] ) );
assert_true( 'desc not empty', '' !== trim( $result['desc'] ) );
assert_true( 'title contains kharid', false !== mb_stripos( $result['title'], 'خرید', 0, 'UTF-8' ) );
assert_true( 'desc uses short copy', false !== mb_stripos( $result['desc'], 'کراپ', 0, 'UTF-8' ) );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} assertion(s) failed.\n" );
	exit( 1 );
}

echo "\nAll meta suggester smoke checks passed.\n";
