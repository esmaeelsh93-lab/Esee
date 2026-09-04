<?php
/**
 * Sitemap health / debug probes for admin (GSC readiness).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Sitemap_Health
 */
final class SEO_Core_Sitemap_Health {

	public const REPORT_TRANSIENT = 'seo_core_sitemap_health_report';
	public const FALLBACK_LOG_OPT = 'seo_core_sitemap_fallback_log';
	public const REPORT_TTL       = 5 * MINUTE_IN_SECONDS;
	public const NEAR_LIMIT       = 45000;

	/**
	 * Run or return cached health report.
	 *
	 * @param SEO_Core_Sitemap $sitemap Module.
	 * @param bool             $force   Bypass cache.
	 * @return array<string,mixed>
	 */
	public static function get_report( SEO_Core_Sitemap $sitemap, bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::REPORT_TRANSIENT );
			if ( is_array( $cached ) && ! empty( $cached['maps'] ) ) {
				$cached['from_cache'] = true;
				return $cached;
			}
		}

		$report = self::build_report( $sitemap );
		set_transient( self::REPORT_TRANSIENT, $report, self::REPORT_TTL );
		$report['from_cache'] = false;
		return $report;
	}

	/**
	 * Bust health cache.
	 */
	public static function bust_report_cache(): void {
		delete_transient( self::REPORT_TRANSIENT );
	}

	/**
	 * Append fallback event (keep last 10).
	 *
	 * @param string $token Token.
	 */
	public static function log_fallback( string $token ): void {
		$log = get_option( self::FALLBACK_LOG_OPT, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'time'  => time(),
				'token' => sanitize_key( $token ),
				'uri'   => isset( $_SERVER['REQUEST_URI'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 0, 180 ) : '',
			)
		);
		$log = array_slice( $log, 0, 10 );
		update_option( self::FALLBACK_LOG_OPT, $log, false );
	}

	/**
	 * @return array<int,array{time:int,token:string,uri:string}>
	 */
	public static function get_fallback_log(): array {
		$log = get_option( self::FALLBACK_LOG_OPT, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * @param SEO_Core_Sitemap $sitemap Module.
	 * @return array<string,mixed>
	 */
	private static function build_report( SEO_Core_Sitemap $sitemap ): array {
		$types = array_merge( array( 'index' ), $sitemap->enabled_types() );
		$maps  = array();
		foreach ( $types as $type ) {
			$maps[ $type ] = self::probe_map( $sitemap, $type );
		}

		return array(
			'generated_at' => time(),
			'ttl'          => self::REPORT_TTL,
			'maps'         => $maps,
			'exclusions'   => self::exclusion_stats(),
			'robots'       => self::probe_robots( $sitemap ),
			'fallback_log' => self::get_fallback_log(),
			'fallback_hits'=> (int) get_option( 'seo_core_sitemap_fallback_hits', 0 ),
			'max_urls'     => SEO_Core_Sitemap::max_urls_per_file(),
		);
	}

	/**
	 * Probe one sitemap type (page 1 URL).
	 *
	 * @param SEO_Core_Sitemap $sitemap Module.
	 * @param string           $type    Type.
	 * @return array<string,mixed>
	 */
	private static function probe_map( SEO_Core_Sitemap $sitemap, string $type ): array {
		$url  = $sitemap->public_url( $type, 1 );
		$meta = $sitemap->get_cache_meta( $type, 1 );

		$row = array(
			'type'            => $type,
			'url'             => $url,
			'http_code'       => 0,
			'http_ok'         => false,
			'via_fallback'    => false,
			'redirected'      => false,
			'final_url'       => $url,
			'xml_ok'          => false,
			'xml_error'       => '',
			'item_count'      => 0,
			'lastmod_newest'  => '',
			'lastmod_oldest'  => '',
			'images'          => 0,
			'images_total'    => 0,
			'near_limit'      => false,
			'cache_generated' => (int) ( $meta['generated'] ?? 0 ),
			'cache_ttl'       => (int) ( $meta['ttl'] ?? SEO_Core_Sitemap::CACHE_TTL ),
			'cache_remaining' => 0,
			'bytes'           => 0,
			'content_type'    => '',
			'warning'         => '',
		);

		if ( $row['cache_generated'] > 0 ) {
			$age                    = max( 0, time() - $row['cache_generated'] );
			$row['cache_remaining'] = max( 0, $row['cache_ttl'] - $age );
		}

		$args = array(
			'timeout'     => 12,
			'redirection' => 3,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			'headers'     => array(
				'Accept' => 'application/xml,text/xml,*/*',
			),
		);
		/**
		 * Filter health probe HTTP args.
		 *
		 * @param array  $args Args.
		 * @param string $url  URL.
		 */
		$args = apply_filters( 'seo_core_sitemap_health_http_args', $args, $url );

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			$row['xml_error'] = $response->get_error_message();
			$row['warning']   = __( 'درخواست HTTP ناموفق بود.', 'shojaei-seo-for-woo' );
			return $row;
		}

		$code              = (int) wp_remote_retrieve_response_code( $response );
		$body              = (string) wp_remote_retrieve_body( $response );
		$headers           = wp_remote_retrieve_headers( $response );
		$row['http_code']  = $code;
		$row['http_ok']    = ( 200 === $code );
		$row['bytes']      = strlen( $body );
		$row['final_url']  = (string) ( wp_remote_retrieve_header( $response, 'location' ) ?: $url );
		$row['content_type'] = (string) wp_remote_retrieve_header( $response, 'content-type' );

		$via = '';
		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			$via = (string) ( $headers['x-damavand-sitemap-via'] ?? '' );
		} else {
			$via = (string) wp_remote_retrieve_header( $response, 'x-damavand-sitemap-via' );
		}
		$row['via_fallback'] = ( 'fallback' === strtolower( $via ) );

		// Unexpected redirect (final URL changed and not 200 on first hop).
		$request_url = wp_remote_retrieve_header( $response, 'x-redirect-by' );
		if ( $code >= 300 && $code < 400 ) {
			$row['redirected'] = true;
			$row['http_ok']    = false;
			$row['warning']    = __( 'ریدایرکت غیرمنتظره روی URL نقشه سایت.', 'shojaei-seo-for-woo' );
		} elseif ( 200 !== $code ) {
			$row['warning'] = sprintf(
				/* translators: %d: HTTP code */
				__( 'کد HTTP نامعتبر: %d (باید ۲۰۰ باشد).', 'shojaei-seo-for-woo' ),
				$code
			);
		}

		if ( $row['via_fallback'] ) {
			$row['warning'] = trim( $row['warning'] . ' ' . __( 'از طریق فالبک سرو شد — rewrite احتمالاً ثبت نشده.', 'shojaei-seo-for-woo' ) );
		}

		if ( '' === $body ) {
			$row['xml_error'] = __( 'بدنه پاسخ خالی است.', 'shojaei-seo-for-woo' );
			return $row;
		}

		$parsed = self::parse_xml( $body, $type );
		$row    = array_merge( $row, $parsed );

		$stats_total = 0;
		$all_stats   = $sitemap->get_stats();
		if ( isset( $all_stats[ $type ]['urls_total'] ) ) {
			$stats_total = (int) $all_stats[ $type ]['urls_total'];
		}
		if ( max( $row['item_count'], $stats_total ) >= self::NEAR_LIMIT ) {
			$row['near_limit'] = true;
			$row['warning']    = trim(
				$row['warning'] . ' ' . __( 'نزدیک سقف ۵۰٬۰۰۰ URL — صفحه‌بندی ضروری است.', 'shojaei-seo-for-woo' )
			);
		}

		return $row;
	}

	/**
	 * Parse sitemap XML body.
	 *
	 * @param string $body XML.
	 * @param string $type Type.
	 * @return array<string,mixed>
	 */
	private static function parse_xml( string $body, string $type ): array {
		$out = array(
			'xml_ok'         => false,
			'xml_error'      => '',
			'item_count'     => 0,
			'lastmod_newest' => '',
			'lastmod_oldest' => '',
			'images'         => 0,
			'images_total'   => 0,
		);

		$prev = libxml_use_internal_errors( true );
		libxml_clear_errors();
		$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET );
		$errs = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		if ( false === $xml ) {
			$msg = __( 'XML نامعتبر است.', 'shojaei-seo-for-woo' );
			if ( ! empty( $errs[0] ) ) {
				$e    = $errs[0];
				$msg .= ' ' . trim( (string) $e->message ) . ' (line ' . (int) $e->line . ')';
			}
			// Likely HTML 404 theme.
			if ( false !== stripos( $body, '<html' ) ) {
				$msg = __( 'پاسخ HTML است نه XML (احتمالاً ۴۰۴ قالب).', 'shojaei-seo-for-woo' );
			}
			$out['xml_error'] = $msg;
			return $out;
		}

		$out['xml_ok'] = true;
		$ns            = $xml->getDocNamespaces( true );
		$lastmods      = array();

		if ( 'index' === $type || isset( $xml->sitemap ) ) {
			foreach ( $xml->sitemap as $sm ) {
				++$out['item_count'];
				$lm = trim( (string) ( $sm->lastmod ?? '' ) );
				if ( '' !== $lm ) {
					$lastmods[] = $lm;
				}
			}
		} else {
			// Register image namespace if present.
			if ( ! empty( $ns['image'] ) ) {
				$xml->registerXPathNamespace( 'image', (string) $ns['image'] );
			}
			foreach ( $xml->url as $u ) {
				++$out['item_count'];
				++$out['images_total'];
				$lm = trim( (string) ( $u->lastmod ?? '' ) );
				if ( '' !== $lm ) {
					$lastmods[] = $lm;
				}
				$img_nodes = $u->children( 'http://www.google.com/schemas/sitemap-image/1.1' );
				if ( $img_nodes && isset( $img_nodes->image ) ) {
					++$out['images'];
				}
			}
		}

		if ( ! empty( $lastmods ) ) {
			sort( $lastmods );
			$out['lastmod_oldest'] = $lastmods[0];
			$out['lastmod_newest'] = $lastmods[ count( $lastmods ) - 1 ];
		}

		return $out;
	}

	/**
	 * Exclusion / coverage stats vs published catalog.
	 *
	 * @return array<string,mixed>
	 */
	public static function exclusion_stats(): array {
		$published_products = (int) wp_count_posts( 'product' )->publish;
		$published_posts    = (int) wp_count_posts( 'post' )->publish;
		$published_pages    = (int) wp_count_posts( 'page' )->publish;

		$gone_410 = array();
		if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
			$gone_410 = Shojaei_SEO_Helpers::get_410_excluded_ids();
		}
		$gone_410 = array_values( array_unique( array_filter( array_map( 'absint', (array) $gone_410 ) ) ) );

		$noindex_products = self::count_noindex_for_type( 'product' );
		$noindex_posts    = self::count_noindex_for_type( 'post' );
		$noindex_pages    = self::count_noindex_for_type( 'page' );

		return array(
			'products_published' => $published_products,
			'posts_published'    => $published_posts,
			'pages_published'    => $published_pages,
			'products_410'       => count( $gone_410 ),
			'products_noindex'   => $noindex_products,
			'posts_noindex'      => $noindex_posts,
			'pages_noindex'      => $noindex_pages,
		);
	}

	/**
	 * Count noindex among published posts of a type (sampled via meta query, capped).
	 *
	 * @param string $post_type Type.
	 */
	private static function count_noindex_for_type( string $post_type ): int {
		if ( ! post_type_exists( $post_type ) ) {
			return 0;
		}
		$q = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'   => '_shojaei_seo_noindex',
						'value' => 'yes',
					),
					array(
						'key'     => '_yoast_wpseo_meta-robots-noindex',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);
		// Rank Math noindex is array — approximate with PHP scan of a limited set if found_posts low.
		$count = (int) $q->found_posts;

		// Supplement Rank Math noindex (serialized array) — sample up to 500.
		$ids = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'meta_key'               => 'rank_math_robots', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		$rm = 0;
		foreach ( (array) $ids as $id ) {
			$robots = get_post_meta( (int) $id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				++$rm;
			}
		}
		return max( $count, $rm );
	}

	/**
	 * Live robots.txt Sitemap line check.
	 *
	 * @param SEO_Core_Sitemap $sitemap Module.
	 * @return array<string,mixed>
	 */
	private static function probe_robots( SEO_Core_Sitemap $sitemap ): array {
		$robots_url = home_url( '/robots.txt' );
		$index_url  = $sitemap->public_url( 'index' );
		$out        = array(
			'url'            => $robots_url,
			'http_code'      => 0,
			'ok'             => false,
			'has_sitemap'    => false,
			'has_damavand'   => false,
			'has_dead_rm'    => false,
			'lines'          => array(),
			'error'          => '',
			'tip'            => '',
		);

		$res = wp_remote_get(
			$robots_url,
			array(
				'timeout'     => 8,
				'redirection' => 2,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
		if ( is_wp_error( $res ) ) {
			$out['error'] = $res->get_error_message();
			return $out;
		}
		$out['http_code'] = (int) wp_remote_retrieve_response_code( $res );
		$body             = (string) wp_remote_retrieve_body( $res );
		if ( 200 !== $out['http_code'] ) {
			$out['error'] = sprintf(
				/* translators: %d: code */
				__( 'robots.txt کد %d برگرداند.', 'shojaei-seo-for-woo' ),
				$out['http_code']
			);
			return $out;
		}
		$out['ok'] = true;
		foreach ( preg_split( '/\r\n|\r|\n/', $body ) as $line ) {
			$line = trim( (string) $line );
			if ( 0 === stripos( $line, 'Sitemap:' ) ) {
				$out['has_sitemap'] = true;
				$out['lines'][]     = $line;
				if ( false !== stripos( $line, 'shojaei-sitemap' ) || false !== stripos( $line, $index_url ) || false !== stripos( $line, '/sitemap.xml' ) ) {
					$out['has_damavand'] = true;
				}
				if ( false !== stripos( $line, 'sitemap_index.xml' ) ) {
					$out['has_dead_rm'] = true;
				}
			}
		}
		if ( $out['has_dead_rm'] && ! $out['has_damavand'] ) {
			$out['tip'] = __( 'robots.txt هنوز به sitemap_index.xml رنک‌مث اشاره می‌کند — گزینه «ثبت خودکار در robots» را روشن کنید یا در GSC آدرس Damavand را ثبت کنید.', 'shojaei-seo-for-woo' );
		} elseif ( ! $out['has_damavand'] ) {
			$out['tip'] = __( 'خط Sitemap دماوند در robots.txt نیست.', 'shojaei-seo-for-woo' );
		}
		return $out;
	}
}
