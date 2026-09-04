#!/usr/bin/env php
<?php
/**
 * Snapshot canonical / robots / schema markers for a list of front URLs.
 *
 * Usage (from WordPress root, with Damavand active):
 *   wp eval-file wp-content/plugins/damavand/scripts/snapshot-seo-head.php -- \
 *     --urls=https://shop.example/product/x/,https://shop.example/shop/page/2/ \
 *     --out=/tmp/damavand-seo-snapshot.json
 *
 * Or without WP-CLI (bootstrap):
 *   php scripts/snapshot-seo-head.php --wp=/path/to/wp --urls=... --out=...
 *
 * This environment has no live WordPress site — run on staging before/after
 * Task 4 deploys and diff the JSON. Captures:
 *   - link[rel=canonical] href
 *   - meta[name=robots] content
 *   - count + @type list of application/ld+json blocks
 *
 * @package Shojaei_SEO_For_Woo
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$opts = array(
	'urls' => '',
	'out'  => '',
	'wp'   => '',
);
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( preg_match( '/^--urls=(.+)$/', $arg, $m ) ) {
		$opts['urls'] = $m[1];
	} elseif ( preg_match( '/^--out=(.+)$/', $arg, $m ) ) {
		$opts['out'] = $m[1];
	} elseif ( preg_match( '/^--wp=(.+)$/', $arg, $m ) ) {
		$opts['wp'] = $m[1];
	}
}

$urls = array_values(
	array_filter(
		array_map( 'trim', preg_split( '/[\s,]+/', (string) $opts['urls'] ) ?: array() )
	)
);

if ( empty( $urls ) ) {
	fwrite( STDERR, "Provide --urls=url1,url2,... (10–15 representative URLs recommended).\n" );
	fwrite( STDERR, "No live WP in Damavand CI VM — this script is for staging baseline/diff.\n" );
	exit( 2 );
}

$results = array();
foreach ( $urls as $url ) {
	$html = '';
	$code = 0;
	// Prefer curl so 404/5xx bodies are still captured for robots/canonical.
	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT       => 20,
				CURLOPT_USERAGENT     => 'DamavandSEO-Snapshot/1.58',
				CURLOPT_SSL_VERIFYPEER => true,
			)
		);
		$body = curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		if ( is_string( $body ) ) {
			$html = $body;
		}
	} else {
		$ctx  = stream_context_create(
			array(
				'http' => array(
					'timeout'         => 20,
					'follow_location' => 1,
					'ignore_errors'   => true,
					'user_agent'      => 'DamavandSEO-Snapshot/1.58',
				),
			)
		);
		$body = @file_get_contents( $url, false, $ctx ); // phpcs:ignore
		if ( is_string( $body ) ) {
			$html = $body;
		}
		if ( isset( $http_response_header[0] ) && preg_match( '/\s(\d{3})\s/', (string) $http_response_header[0], $hm ) ) {
			$code = (int) $hm[1];
		}
	}
	$row  = array(
		'url'        => $url,
		'ok'         => '' !== $html,
		'http_code'  => $code,
		'canonical'  => null,
		'robots'     => null,
		'ld_types'   => array(),
		'ld_count'   => 0,
	);
	if ( '' === $html ) {
		$results[] = $row;
		continue;
	}
	if ( preg_match( '/<link[^>]+rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m )
		|| preg_match( '/<link[^>]+href=["\']([^"\']+)["\'][^>]*rel=["\']canonical["\']/i', $html, $m ) ) {
		$row['canonical'] = $m[1];
	}
	if ( preg_match( '/<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m )
		|| preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*name=["\']robots["\']/i', $html, $m ) ) {
		$row['robots'] = $m[1];
	}
	if ( preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks ) ) {
		$row['ld_count'] = count( $blocks[1] );
		foreach ( $blocks[1] as $json ) {
			$data = json_decode( trim( $json ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$types = array();
			$walk  = static function ( $node ) use ( &$types, &$walk ) {
				if ( ! is_array( $node ) ) {
					return;
				}
				if ( isset( $node['@type'] ) ) {
					$t = $node['@type'];
					if ( is_array( $t ) ) {
						foreach ( $t as $tt ) {
							$types[] = (string) $tt;
						}
					} else {
						$types[] = (string) $t;
					}
				}
				if ( isset( $node['@graph'] ) && is_array( $node['@graph'] ) ) {
					foreach ( $node['@graph'] as $g ) {
						$walk( $g );
					}
				}
			};
			$walk( $data );
			$row['ld_types'] = array_values( array_unique( array_merge( $row['ld_types'], $types ) ) );
		}
	}
	$results[] = $row;
}

$payload = array(
	'generated_at' => gmdate( 'c' ),
	'count'        => count( $results ),
	'rows'         => $results,
);
$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( false === $json ) {
	fwrite( STDERR, "json_encode failed\n" );
	exit( 1 );
}

if ( '' !== $opts['out'] ) {
	file_put_contents( $opts['out'], $json . "\n" );
	fwrite( STDOUT, "Wrote {$opts['out']}\n" );
} else {
	fwrite( STDOUT, $json . "\n" );
}
exit( 0 );
