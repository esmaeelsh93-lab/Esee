<?php
/**
 * Drop-in relay for Iranian WordPress hosts.
 *
 * Deploy on a VPS with unfiltered outbound HTTPS, then point the plugin
 * Gateway URL to this script (e.g. https://api.example.com/v1/proxy).
 *
 * @package Shojaei_SEO_For_Woo
 */

declare(strict_types=1);

header( 'Content-Type: application/json; charset=utf-8' );
header( 'X-Content-Type-Options: nosniff' );

if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	http_response_code( 204 );
	exit;
}

if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	http_response_code( 405 );
	echo wp_json_encode_compat( array( 'error' => array( 'message' => 'POST only' ) ) );
	exit;
}

$raw  = file_get_contents( 'php://input' );
$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
if ( ! is_array( $data ) ) {
	http_response_code( 400 );
	echo wp_json_encode_compat( array( 'error' => array( 'message' => 'Invalid JSON' ) ) );
	exit;
}

$allowed = array(
	'https://api.groq.com/openai/v1/chat/completions',
	'https://openrouter.ai/api/v1/chat/completions',
);

$upstream = isset( $data['upstream'] ) ? trim( (string) $data['upstream'] ) : '';
if ( ! in_array( $upstream, $allowed, true ) ) {
	http_response_code( 400 );
	echo wp_json_encode_compat( array( 'error' => array( 'message' => 'Upstream not allowed' ) ) );
	exit;
}

$auth = '';
if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
	$auth = trim( (string) $_SERVER['HTTP_AUTHORIZATION'] );
} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
	$auth = trim( (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
}
if ( '' === $auth || 0 !== stripos( $auth, 'Bearer ' ) ) {
	http_response_code( 401 );
	echo wp_json_encode_compat( array( 'error' => array( 'message' => 'Missing API key' ) ) );
	exit;
}

$body = isset( $data['body'] ) && is_array( $data['body'] ) ? $data['body'] : $data;
unset( $body['upstream'], $body['provider'], $body['api_key'] );

$payload = json_encode( $body, JSON_UNESCAPED_UNICODE );
if ( false === $payload ) {
	http_response_code( 400 );
	echo wp_json_encode_compat( array( 'error' => array( 'message' => 'Encode failed' ) ) );
	exit;
}

$headers = array(
	'Content-Type: application/json; charset=utf-8',
	'Accept: application/json',
	'Authorization: ' . $auth,
);

if ( false !== strpos( $upstream, 'openrouter.ai' ) ) {
	$referer = isset( $_SERVER['HTTP_X_SITE_DOMAIN'] ) ? trim( (string) $_SERVER['HTTP_X_SITE_DOMAIN'] ) : '';
	if ( '' !== $referer ) {
		$headers[] = 'HTTP-Referer: ' . $referer;
	}
	$headers[] = 'X-Title: Damavand SEO';
}

$ch = curl_init( $upstream );
curl_setopt_array(
	$ch,
	array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $payload,
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 60,
		CURLOPT_CONNECTTIMEOUT => 12,
	)
);
$resp = curl_exec( $ch );
$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
$err  = curl_error( $ch );
curl_close( $ch );

if ( false === $resp ) {
	http_response_code( 502 );
	echo wp_json_encode_compat( array( 'error' => array( 'message' => $err ?: 'Upstream failed' ) ) );
	exit;
}

http_response_code( $code > 0 ? $code : 200 );
echo $resp;

/**
 * json_encode fallback name (this file is not loaded inside WordPress).
 *
 * @param mixed $data Data.
 */
function wp_json_encode_compat( $data ): string {
	$out = json_encode( $data, JSON_UNESCAPED_UNICODE );
	return false === $out ? '{"error":{"message":"json"}}' : $out;
}
