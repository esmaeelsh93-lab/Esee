<?php
/**
 * تست‌های بدون وابستگی برای منطق خالص افزونه.
 *
 * اجرا: php tests/smoke.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CVS_TABLE_NAME', 'cvs_visits' );
define( 'CVS_SESSIONS_TABLE_NAME', 'cvs_sessions' );
define( 'CVS_DAILY_SUMMARY_TABLE_NAME', 'cvs_daily_summary' );
define( 'CVS_CITY_DAILY_TABLE_NAME', 'cvs_city_daily' );

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function apply_filters( $hook, $value ) {
	return $value;
}

require_once dirname( __DIR__ ) . '/includes/class-cvs-source-detector.php';
require_once dirname( __DIR__ ) . '/includes/class-cvs-db.php';
require_once dirname( __DIR__ ) . '/includes/class-cvs-tracker.php';

$passed = 0;

function cvs_assert_same( $expected, $actual, $message ) {
	global $passed;
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
	$passed++;
}

$source = CVS_Source_Detector::detect(
	'https://example.org/campaign',
	array( 'utm_source' => 'instagram' ),
	'store.test'
);
cvs_assert_same( 'instagram', $source['source_key'], 'UTM must take precedence over referrer.' );

$source = CVS_Source_Detector::detect( 'https://www.google.com/search?q=test', array(), 'store.test' );
cvs_assert_same( 'google', $source['source_key'], 'Google referrer must be recognized.' );

$source = CVS_Source_Detector::detect( 'https://www.store.test/product', array(), 'store.test' );
cvs_assert_same( 'direct', $source['source_key'], 'Internal referrer must not create a new source.' );

cvs_assert_same( true, CVS_Tracker::is_bot_user_agent( 'Mozilla/5.0 compatible Googlebot/2.1' ), 'Known crawler must be filtered.' );
cvs_assert_same( false, CVS_Tracker::is_bot_user_agent( 'Mozilla/5.0 Chrome/126.0 Safari/537.36' ), 'Normal browser must be accepted.' );

$_SERVER['REMOTE_ADDR']         = '10.0.0.12';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 10.0.0.12';
cvs_assert_same( '203.0.113.10', CVS_Tracker::get_client_ip(), 'First valid forwarded IP must be selected.' );
unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
cvs_assert_same( '10.0.0.12', CVS_Tracker::get_client_ip(), 'Remote address must be the fallback.' );

cvs_assert_same(
	array( '2026-08-06', '2026-08-07', '2026-08-08' ),
	CVS_DB::get_date_range_list( '2026-08-06', '2026-08-08' ),
	'Date range must be inclusive.'
);
cvs_assert_same(
	array( '2026-07-30', '2026-08-05' ),
	CVS_DB::get_previous_range( '2026-08-06', '2026-08-12' ),
	'Previous comparison range must have equal length.'
);

echo "OK: {$passed} smoke assertions passed.\n";
