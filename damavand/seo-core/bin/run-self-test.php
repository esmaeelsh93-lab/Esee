<?php
/**
 * WP-CLI: wp eval-file seo-core/bin/run-self-test.php
 *
 * @package Shojaei_SEO_For_Woo
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run via WP-CLI: wp eval-file seo-core/bin/run-self-test.php\n" );
	exit( 1 );
}

if ( ! class_exists( 'SEO_Core_Self_Test' ) ) {
	$file = dirname( __DIR__ ) . '/class-seo-core-self-test.php';
	if ( is_readable( $file ) ) {
		require_once dirname( __DIR__ ) . '/class-seo-core-module.php';
		require_once dirname( __DIR__ ) . '/class-seo-core-db.php';
		require_once dirname( __DIR__ ) . '/class-seo-core-installer.php';
		require_once $file;
	}
}

if ( ! class_exists( 'SEO_Core_Self_Test' ) ) {
	fwrite( STDERR, "SEO_Core_Self_Test not found.\n" );
	exit( 1 );
}

$report = SEO_Core_Self_Test::run();
echo $report['message'] . PHP_EOL;
foreach ( $report['results'] as $row ) {
	$icon = 'pass' === $row['status'] ? '[PASS]' : ( 'skip' === $row['status'] ? '[SKIP]' : '[FAIL]' );
	echo $icon . ' ' . $row['label'] . ' — ' . $row['message'] . PHP_EOL;
}

exit( ! empty( $report['ok'] ) ? 0 : 1 );
