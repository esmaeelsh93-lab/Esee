<?php
/**
 * Standalone smoke tests (no WordPress bootstrap required for pure helpers).
 *
 * Run: php tests/smoke.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$failures = 0;

function wbop_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		echo "PASS: {$message}\n";
		return;
	}
	$failures++;
	echo "FAIL: {$message}\n";
}

// Minimal WordPress stubs for settings class.
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( (string) $str );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $n ) {
		return abs( (int) $n );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return $default;
	}
}
if ( ! function_exists( 'get_post_mime_type' ) ) {
	function get_post_mime_type( $id ) {
		return $id > 0 ? 'image/png' : false;
	}
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-wbop-settings.php';

// File structure.
$root = dirname( __DIR__ );
$required = array(
	'woocommerce-bulk-order-print.php',
	'includes/class-wbop-plugin.php',
	'includes/class-wbop-settings.php',
	'includes/class-wbop-printer.php',
	'assets/admin.css',
	'assets/admin.js',
	'assets/admin-settings.js',
	'readme.txt',
	'CHANGELOG.md',
);
foreach ( $required as $rel ) {
	wbop_assert( file_exists( $root . '/' . $rel ), "exists {$rel}" );
}

$main = file_get_contents( $root . '/woocommerce-bulk-order-print.php' );
wbop_assert( false !== strpos( $main, "Version:     1.3.0" ), 'plugin header version 1.3.0' );
wbop_assert( false !== strpos( $main, "WBOP_VERSION', '1.3.0'" ), 'WBOP_VERSION constant' );
wbop_assert( false !== strpos( $main, 'custom_order_tables' ), 'HPOS compatibility declared' );
wbop_assert( false !== strpos( $main, 'پرینت هوشمند شجاعی' ), 'Persian plugin name' );

$printer = file_get_contents( $root . '/includes/class-wbop-printer.php' );
wbop_assert( false !== strpos( $printer, 'نحوه ارسال' ), 'shipping label is نحوه ارسال' );
wbop_assert( false === strpos( $printer, 'نحوه ارسال واقعی' ), 'no نحوه ارسال واقعی' );
wbop_assert( false !== strpos( $printer, 'توضیحات مشتری' ), 'customer note block present' );
wbop_assert( false !== strpos( $printer, 'page-break-after:always' ), 'page break CSS present' );
wbop_assert( false !== strpos( $printer, 'page-break-after:auto' ), 'last page no empty sheet' );
wbop_assert( false !== strpos( $printer, 'wbop-print-order' ), 'print order wrapper class' );

$plugin = file_get_contents( $root . '/includes/class-wbop-plugin.php' );
wbop_assert( false !== strpos( $plugin, 'عملیات چاپ' ), 'operations tab label' );
wbop_assert( false !== strpos( $plugin, 'تنظیمات' ), 'settings tab label' );
wbop_assert( false !== strpos( $plugin, 'وضعیت سفارشات' ), 'status label exact' );
wbop_assert( false !== strpos( $plugin, 'تعداد سفارش‌های قابل نمایش' ), 'limit label exact' );
wbop_assert( false !== strpos( $plugin, 'چاپ سفارش‌های انتخاب‌شده' ), 'print button exact' );
wbop_assert( false !== strpos( $plugin, 'لطفاً حداقل یک سفارش را انتخاب کنید.' ), 'empty selection warning' );
wbop_assert( false !== strpos( $plugin, 'wbop-printed-badge' ), 'printed badge in list' );
wbop_assert( false !== strpos( $plugin, 'wp_enqueue_media' ), 'media library enqueued' );
wbop_assert( false !== strpos( $plugin, 'admin-post.php' ), 'POST via admin-post' );

$settings_js = file_get_contents( $root . '/assets/admin-settings.js' );
wbop_assert( false !== strpos( $settings_js, 'wp.media' ), 'media uploader uses wp.media' );
wbop_assert( false !== strpos( $settings_js, 'toggleCustomPaper' ), 'custom paper toggle' );

// Sanitization tests.
$clean = WBOP_Settings::sanitize(
	array(
		'sender_name'    => '  فروشگاه تست  ',
		'sender_address' => "خیابان یک\nپلاک ۲",
		'sender_phone'   => '09120000000',
		'header_image'   => '12',
		'paper_type'     => 'custom',
		'paper_width'    => '12.55',
		'paper_height'   => '999',
		'print_margin'   => '2',
	)
);
wbop_assert( 'فروشگاه تست' === $clean['sender_name'], 'sender name sanitized' );
wbop_assert( 'custom' === $clean['paper_type'], 'custom paper accepted' );
wbop_assert( 12.6 === $clean['paper_width'], 'width rounded to 1 decimal' );
wbop_assert( 21.0 === $clean['paper_height'], 'invalid height falls back' );
wbop_assert( 7.0 === $clean['print_margin'], 'invalid margin falls back' );

$bad_paper = WBOP_Settings::sanitize( array( 'paper_type' => 'hack;}' ) );
wbop_assert( 'a5' === $bad_paper['paper_type'], 'invalid paper type falls back to a5' );

$css_a5 = WBOP_Settings::page_size_css( array( 'paper_type' => 'a5', 'print_margin' => 7 ) );
wbop_assert( false !== strpos( $css_a5, 'size:A5 portrait' ), 'A5 page size CSS' );
wbop_assert( false === strpos( $css_a5, 'hack' ), 'no injection in CSS' );

$css_custom = WBOP_Settings::page_size_css(
	array(
		'paper_type'   => 'custom',
		'paper_width'  => 12,
		'paper_height' => 20,
		'print_margin' => 5,
	)
);
wbop_assert( false !== strpos( $css_custom, 'size:12cm 20cm' ), 'custom cm page size CSS' );

$css_inject = WBOP_Settings::page_size_css(
	array(
		'paper_type'   => 'custom',
		'paper_width'  => '12cm; } body{display:none',
		'paper_height' => 20,
		'print_margin' => 5,
	)
);
wbop_assert( false === strpos( $css_inject, 'display:none' ), 'CSS injection blocked' );
wbop_assert( false !== strpos( $css_inject, 'size:14.8cm 20cm' ) || false !== strpos( $css_inject, 'size:14.8cm' ), 'unsafe width falls back' );

// PHP syntax lint.
$php_files = array(
	$root . '/woocommerce-bulk-order-print.php',
	$root . '/includes/class-wbop-plugin.php',
	$root . '/includes/class-wbop-settings.php',
	$root . '/includes/class-wbop-printer.php',
);
foreach ( $php_files as $file ) {
	$output = array();
	$code   = 0;
	exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $output, $code );
	wbop_assert( 0 === $code, 'php -l ' . basename( $file ) );
}

if ( $failures > 0 ) {
	echo "\n{$failures} failure(s)\n";
	exit( 1 );
}

echo "\nAll smoke tests passed.\n";
exit( 0 );
