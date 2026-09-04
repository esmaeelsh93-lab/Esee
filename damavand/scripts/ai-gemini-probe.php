<?php
/**
 * Live Gemini endpoint probe (no real API key — expects auth error).
 *
 * Usage: php damavand/scripts/ai-gemini-probe.php
 *
 * @package Shojaei_SEO_For_Woo
 */

declare( strict_types=1 );

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) { // phpcs:ignore
		return json_encode( $data );
	}
}

if ( ! function_exists( 'curl_init' ) ) {
	fwrite( STDERR, "SKIP: curl not available\n" );
	exit( 0 );
}

$url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
$body = wp_json_encode(
	array(
		'contents' => array(
			array(
				'parts' => array(
					array( 'text' => 'Reply with exactly one word: ok' ),
				),
			),
		),
	)
);

$ch = curl_init( $url );
curl_setopt_array(
	$ch,
	array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $body,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 20,
		CURLOPT_HTTPHEADER     => array(
			'Content-Type: application/json',
			'x-goog-api-key: invalid-probe-key-damavand',
		),
	)
);
$resp = curl_exec( $ch );
$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );

if ( false === $resp ) {
	fwrite( STDERR, "FAIL: no response from Gemini API\n" );
	exit( 1 );
}

$data = json_decode( $resp, true );
if ( ! in_array( $code, array( 400, 401, 403 ), true ) ) {
	fwrite( STDERR, "FAIL: expected auth error, got HTTP {$code}\n" );
	exit( 1 );
}
if ( ! is_array( $data ) || empty( $data['error']['message'] ) ) {
	fwrite( STDERR, "FAIL: expected JSON error.message from Gemini\n" );
	exit( 1 );
}

echo "OK: Gemini API reachable — HTTP {$code} with structured error (no real key used)\n";
