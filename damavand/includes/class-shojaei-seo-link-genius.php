<?php
/**
 * Link Genius — keyword maps, link inventory, post link stats.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Link_Genius
 */
class Shojaei_SEO_Link_Genius {

	/**
	 * Keyword maps table.
	 */
	public static function maps_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_keyword_maps';
	}

	/**
	 * Link inventory table.
	 */
	public static function inventory_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_link_genius';
	}

	/**
	 * Install schema (activation / upgrade).
	 */
	public static function install(): void {
		self::create_tables();
	}

	/**
	 * Drop tables (full uninstall wipe only — not on deactivate).
	 */
	public static function uninstall(): void {
		global $wpdb;
		foreach ( array( self::maps_table(), self::inventory_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Create DB tables.
	 */
	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$maps = self::maps_table();
		dbDelta(
			"CREATE TABLE {$maps} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(191) NOT NULL DEFAULT '',
				target_url TEXT NOT NULL,
				keywords LONGTEXT NOT NULL,
				max_per_post INT NOT NULL DEFAULT 3,
				case_sensitive TINYINT(1) NOT NULL DEFAULT 0,
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY is_active (is_active)
			) {$charset};"
		);

		$inv = self::inventory_table();
		dbDelta(
			"CREATE TABLE {$inv} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				source_post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				source_url TEXT NOT NULL,
				dest_url TEXT NOT NULL,
				dest_host VARCHAR(255) NOT NULL DEFAULT '',
				anchor_text VARCHAR(500) NOT NULL DEFAULT '',
				link_type VARCHAR(20) NOT NULL DEFAULT 'internal',
				http_status SMALLINT NOT NULL DEFAULT 0,
				is_redirect TINYINT(1) NOT NULL DEFAULT 0,
				redirect_url TEXT NULL,
				content_hash CHAR(32) NOT NULL DEFAULT '',
				first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				last_checked DATETIME NULL,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY source_post_id (source_post_id),
				KEY link_type (link_type),
				KEY http_status (http_status),
				KEY is_redirect (is_redirect),
				KEY dest_host (dest_host(191)),
				KEY content_hash (content_hash)
			) {$charset};"
		);
	}

	/**
	 * Normalize keywords textarea → unique list.
	 *
	 * @param string $raw Raw.
	 * @return string[]
	 */
	public static function parse_keywords( string $raw ): array {
		$parts = preg_split( '/[\r\n,]+/u', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $p ) {
			$p = trim( wp_strip_all_tags( (string) $p ) );
			if ( '' === $p ) {
				continue;
			}
			$out[ mb_strtolower( $p, 'UTF-8' ) ] = $p;
		}
		return array_values( $out );
	}

	/**
	 * List keyword maps.
	 *
	 * @param int $limit Max.
	 * @return object[]
	 */
	public static function list_maps( int $limit = 200 ): array {
		global $wpdb;
		$table = self::maps_table();
		$limit = max( 1, min( 500, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Save / update a keyword map.
	 *
	 * @param array $args Args.
	 * @return array{ok:bool,message:string,id?:int}
	 */
	public static function save_map( array $args ): array {
		global $wpdb;
		$id       = absint( $args['id'] ?? 0 );
		$name     = sanitize_text_field( (string) ( $args['name'] ?? '' ) );
		$url      = esc_url_raw( (string) ( $args['target_url'] ?? '' ) );
		$keywords = self::parse_keywords( (string) ( $args['keywords'] ?? '' ) );
		$max      = max( 1, min( 20, absint( $args['max_per_post'] ?? 3 ) ) );
		$case     = ! empty( $args['case_sensitive'] ) ? 1 : 0;
		$active   = array_key_exists( 'is_active', $args ) ? ( ! empty( $args['is_active'] ) ? 1 : 0 ) : 1;

		if ( '' === $name ) {
			return array( 'ok' => false, 'message' => __( 'نام نقشه الزامی است.', 'shojaei-seo-for-woo' ) );
		}
		if ( '' === $url ) {
			return array( 'ok' => false, 'message' => __( 'آدرس مقصد نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}
		if ( empty( $keywords ) ) {
			return array( 'ok' => false, 'message' => __( 'حداقل یک کلمه کلیدی لازم است.', 'shojaei-seo-for-woo' ) );
		}

		$data = array(
			'name'           => $name,
			'target_url'     => $url,
			'keywords'       => implode( "\n", $keywords ),
			'max_per_post'   => $max,
			'case_sensitive' => $case,
			'is_active'      => $active,
			'updated_at'     => current_time( 'mysql' ),
		);
		$fmt = array( '%s', '%s', '%s', '%d', '%d', '%d', '%s' );

		if ( $id > 0 ) {
			$ok = $wpdb->update( self::maps_table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );
			if ( false === $ok ) {
				return array( 'ok' => false, 'message' => __( 'به‌روزرسانی ناموفق بود.', 'shojaei-seo-for-woo' ) );
			}
			self::sync_map_to_dictionary( $id );
			return array( 'ok' => true, 'message' => __( 'نقشه به‌روز شد.', 'shojaei-seo-for-woo' ), 'id' => $id );
		}

		$data['created_at'] = current_time( 'mysql' );
		$ok = $wpdb->insert( self::maps_table(), $data, array_merge( $fmt, array( '%s' ) ) );
		if ( ! $ok ) {
			return array( 'ok' => false, 'message' => __( 'ذخیره ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}
		$new_id = (int) $wpdb->insert_id;
		self::sync_map_to_dictionary( $new_id );
		return array( 'ok' => true, 'message' => __( 'نقشه ذخیره شد.', 'shojaei-seo-for-woo' ), 'id' => $new_id );
	}

	/**
	 * Push map keywords into legacy dictionary table (for Link Builder).
	 *
	 * @param int $map_id Map ID.
	 */
	public static function sync_map_to_dictionary( int $map_id ): void {
		global $wpdb;
		$map = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::maps_table() . ' WHERE id = %d', $map_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $map ) {
			return;
		}
		$dict = Shojaei_SEO_Helpers::links_table();
		$kws  = self::parse_keywords( (string) $map->keywords );
		$url  = (string) $map->target_url;
		$on   = (int) $map->is_active;

		foreach ( $kws as $kw ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$dict} WHERE keyword = %s AND target_url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$kw,
					$url
				)
			);
			if ( $existing ) {
				$wpdb->update(
					$dict,
					array( 'is_active' => $on ),
					array( 'id' => (int) $existing ),
					array( '%d' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$dict,
					array(
						'keyword'    => $kw,
						'target_url' => $url,
						'is_active'  => $on,
						'created_at' => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%d', '%s' )
				);
			}
		}
	}

	/**
	 * Delete map.
	 *
	 * @param int $id ID.
	 */
	public static function delete_map( int $id ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		return false !== $wpdb->delete( self::maps_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Toggle map.
	 *
	 * @param int $id     ID.
	 * @param int $active 1|0.
	 */
	public static function set_map_active( int $id, int $active ): bool {
		global $wpdb;
		$ok = $wpdb->update(
			self::maps_table(),
			array(
				'is_active'  => $active ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false !== $ok ) {
			self::sync_map_to_dictionary( $id );
		}
		return false !== $ok;
	}

	/**
	 * Active map keyword candidates for Link Builder.
	 *
	 * @return array<int,array{keyword:string,target_url:string,max_per_post:int,case_sensitive:bool,map_id:int,map_name:string}>
	 */
	public static function active_map_candidates(): array {
		global $wpdb;
		$table = self::maps_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1" );
		$out  = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			foreach ( self::parse_keywords( (string) $row->keywords ) as $kw ) {
				$out[] = array(
					'keyword'        => $kw,
					'target_url'     => (string) $row->target_url,
					'max_per_post'   => max( 1, (int) $row->max_per_post ),
					'case_sensitive' => ! empty( $row->case_sensitive ),
					'map_id'         => (int) $row->id,
					'map_name'       => (string) $row->name,
				);
			}
		}
		return $out;
	}

	/**
	 * Extract links from HTML.
	 *
	 * @param string $html    HTML.
	 * @param int    $post_id Source post.
	 * @return array<int,array<string,mixed>>
	 */
	public static function extract_links_from_html( string $html, int $post_id ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}
		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$links     = array();

		if ( class_exists( 'DOMDocument' ) ) {
			$prev = libxml_use_internal_errors( true );
			$dom  = new DOMDocument();
			$wrapped = '<?xml encoding="utf-8"?><div id="shojaei-root">' . $html . '</div>';
			@$dom->loadHTML( $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ); // phpcs:ignore
			libxml_clear_errors();
			libxml_use_internal_errors( $prev );
			$xpath = new DOMXPath( $dom );
			foreach ( $xpath->query( '//a[@href]' ) as $node ) {
				if ( ! $node instanceof DOMElement ) {
					continue;
				}
				$href = trim( $node->getAttribute( 'href' ) );
				if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) || 0 === strpos( $href, 'javascript:' ) ) {
					continue;
				}
				$abs = self::absolutize_url( $href );
				if ( '' === $abs ) {
					continue;
				}
				$host = (string) wp_parse_url( $abs, PHP_URL_HOST );
				$type = ( $host && $home_host && strtolower( $host ) === strtolower( $home_host ) ) ? 'internal' : 'external';
				$links[] = array(
					'source_post_id' => $post_id,
					'dest_url'       => $abs,
					'dest_host'      => $host,
					'anchor_text'    => mb_substr( trim( $node->textContent ), 0, 500, 'UTF-8' ),
					'link_type'      => $type,
				);
			}
			return $links;
		}

		if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$href = trim( $match[1] );
				if ( '' === $href || 0 === strpos( $href, '#' ) ) {
					continue;
				}
				$abs = self::absolutize_url( $href );
				if ( '' === $abs ) {
					continue;
				}
				$host = (string) wp_parse_url( $abs, PHP_URL_HOST );
				$type = ( $host && $home_host && strtolower( $host ) === strtolower( $home_host ) ) ? 'internal' : 'external';
				$links[] = array(
					'source_post_id' => $post_id,
					'dest_url'       => $abs,
					'dest_host'      => $host,
					'anchor_text'    => mb_substr( trim( wp_strip_all_tags( $match[2] ) ), 0, 500, 'UTF-8' ),
					'link_type'      => $type,
				);
			}
		}
		return $links;
	}

	/**
	 * Make absolute URL.
	 *
	 * @param string $url URL.
	 */
	public static function absolutize_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url_raw( $url );
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			return esc_url_raw( $scheme . $url );
		}
		return esc_url_raw( home_url( $url ) );
	}

	/**
	 * Index links for one post into inventory.
	 *
	 * @param int $post_id Post ID.
	 * @return int Rows upserted.
	 */
	public static function index_post_links( int $post_id ): int {
		global $wpdb;
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return 0;
		}
		if ( ! in_array( $post->post_type, array( 'post', 'page', 'product' ), true ) ) {
			return 0;
		}

		$html  = (string) $post->post_content;
		$links = self::extract_links_from_html( $html, $post_id );
		$table = self::inventory_table();
		$src   = (string) get_permalink( $post_id );
		$now   = current_time( 'mysql' );

		// Remove old rows for this post then insert fresh (simple + consistent).
		$wpdb->delete( $table, array( 'source_post_id' => $post_id ), array( '%d' ) );

		$count = 0;
		foreach ( $links as $link ) {
			$hash = md5( $post_id . '|' . $link['dest_url'] . '|' . $link['anchor_text'] );
			$ok   = $wpdb->insert(
				$table,
				array(
					'source_post_id' => $post_id,
					'source_url'     => $src,
					'dest_url'       => $link['dest_url'],
					'dest_host'      => $link['dest_host'],
					'anchor_text'    => $link['anchor_text'],
					'link_type'      => $link['link_type'],
					'http_status'    => 0,
					'is_redirect'    => 0,
					'content_hash'   => $hash,
					'first_seen'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);
			if ( $ok ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Start full inventory crawl job.
	 *
	 * @return array{ok:bool,message:string,job_id?:string,total?:int}
	 */
	public static function start_inventory_crawl(): array {
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			return array( 'ok' => false, 'message' => __( 'صف جاب در دسترس نیست.', 'shojaei-seo-for-woo' ) );
		}
		if ( Shojaei_SEO_Jobs::has_active( 'link_inventory_crawl' ) ) {
			return array( 'ok' => false, 'message' => __( 'اسکن لینک‌ها همین حالا در حال اجراست.', 'shojaei-seo-for-woo' ) );
		}

		$ids = get_posts(
			array(
				'post_type'              => array( 'post', 'page', 'product' ),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'post__not_in'           => class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_ids() : array(),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$ids = array_map( 'absint', is_array( $ids ) ? $ids : array() );
		$job = Shojaei_SEO_Jobs::enqueue(
			'link_inventory_crawl',
			array( 'post_ids' => $ids ),
			array( 'total' => count( $ids ) )
		);
		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: count */
				__( 'اسکن لینک برای %d نوشته در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
				count( $ids )
			),
			'job_id'  => $job,
			'total'   => count( $ids ),
		);
	}

	/**
	 * Process crawl chunk.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array{processed:int,links:int}
	 */
	public static function process_crawl_ids( array $ids ): array {
		$processed = 0;
		$links     = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				continue;
			}
			$links += self::index_post_links( $id );
			++$processed;
		}
		return array(
			'processed' => $processed,
			'links'     => $links,
		);
	}

	/**
	 * Check HTTP status for inventory rows (HEAD/GET).
	 *
	 * @param int $limit Rows.
	 * @return array{checked:int}
	 */
	public static function check_http_statuses( int $limit = 40 ): array {
		global $wpdb;
		$table = self::inventory_table();
		$limit = max( 1, min( 100, $limit ) );
		// Prefer unchecked, then oldest.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, dest_url FROM {$table}
				ORDER BY (last_checked IS NULL) DESC, last_checked ASC
				LIMIT %d",
				$limit
			)
		);
		$n = 0;
		if ( ! is_array( $rows ) ) {
			return array( 'checked' => 0 );
		}
		foreach ( $rows as $row ) {
			$result = self::probe_url( (string) $row->dest_url );
			$wpdb->update(
				$table,
				array(
					'http_status' => (int) $result['status'],
					'is_redirect' => ! empty( $result['redirect'] ) ? 1 : 0,
					'redirect_url'=> $result['redirect_url'] ?? null,
					'last_checked'=> current_time( 'mysql' ),
					'updated_at'  => current_time( 'mysql' ),
				),
				array( 'id' => (int) $row->id ),
				array( '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			++$n;
		}
		return array( 'checked' => $n );
	}

	/**
	 * Probe URL status.
	 *
	 * @param string $url URL.
	 * @return array{status:int,redirect:bool,redirect_url?:string}
	 */
	public static function probe_url( string $url ): array {
		$url = esc_url_raw( $url );
		if ( ! $url || ( class_exists( 'Shojaei_SEO_Helpers' ) && ! Shojaei_SEO_Helpers::is_safe_remote_url( $url, true ) ) ) {
			return array( 'status' => 0, 'redirect' => false );
		}

		$args_head = array(
			'timeout'     => 12,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array( 'Accept-Encoding' => 'identity' ),
		);

		$response = wp_remote_head( $url, $args_head );

		// HEAD موفق — اما سرورهایی که PHP را lazy اجرا می‌کنند ممکن است 200 بدهند
		// قبل از اینکه PHP خراب شود. برای کدهای 2xx یک GET سریع هم می‌زنیم.
		if ( ! is_wp_error( $response ) ) {
			$code_head = (int) wp_remote_retrieve_response_code( $response );
			if ( $code_head >= 200 && $code_head < 300 ) {
				$verify = wp_remote_get(
					$url,
					array(
						'timeout'     => 15,
						'redirection' => 0,
						'sslverify'   => true,
						'headers'     => array( 'Accept-Encoding' => 'identity', 'Range' => 'bytes=0-0' ),
					)
				);
				if ( ! is_wp_error( $verify ) ) {
					$code_verify = (int) wp_remote_retrieve_response_code( $verify );
					// 206 = Range OK (یعنی واقعاً 200 است). 500/503 روی GET = مشکل واقعی.
					if ( $code_verify >= 400 ) {
						return array( 'status' => $code_verify, 'redirect' => false, 'redirect_url' => '' );
					}
				}
			}
		}

		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 15,
					'redirection' => 0,
					'sslverify'   => true,
					'headers'     => array( 'Accept-Encoding' => 'identity' ),
				)
			);
		}
		if ( is_wp_error( $response ) ) {
			return array( 'status' => 0, 'redirect' => false );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$loc  = wp_remote_retrieve_header( $response, 'location' );
		$redir = ( $code >= 300 && $code < 400 && $loc );
		return array(
			'status'       => $code,
			'redirect'     => (bool) $redir,
			'redirect_url' => $redir ? esc_url_raw( is_array( $loc ) ? (string) $loc[0] : (string) $loc ) : '',
		);
	}

	/**
	 * Query inventory with filters.
	 *
	 * @param array $args Filters.
	 * @return array{rows:object[],total:int}
	 */
	public static function query_inventory( array $args = array() ): array {
		global $wpdb;
		$table  = self::inventory_table();
		$type   = sanitize_key( (string) ( $args['type'] ?? 'all' ) );
		$status = sanitize_key( (string) ( $args['status'] ?? 'all' ) );
		$q      = trim( (string) ( $args['q'] ?? '' ) );
		$page   = max( 1, absint( $args['page'] ?? 1 ) );
		$per    = max( 10, min( 100, absint( $args['per_page'] ?? 50 ) ) );
		$offset = ( $page - 1 ) * $per;

		$where = array( '1=1' );
		$params = array();

		if ( in_array( $type, array( 'internal', 'external' ), true ) ) {
			$where[]  = 'link_type = %s';
			$params[] = $type;
		}
		if ( 'broken' === $status ) {
			$where[] = 'http_status >= 400';
		} elseif ( 'ok' === $status ) {
			$where[] = 'http_status >= 200 AND http_status < 300';
		} elseif ( 'redirect' === $status ) {
			$where[] = 'is_redirect = 1 OR (http_status >= 300 AND http_status < 400)';
		} elseif ( 'unchecked' === $status ) {
			$where[] = 'http_status = 0 OR last_checked IS NULL';
		}
		if ( '' !== $q ) {
			$like     = '%' . $wpdb->esc_like( $q ) . '%';
			$where[]  = '(dest_url LIKE %s OR anchor_text LIKE %s OR source_url LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql_where = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";
		$list_sql  = "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
			$params2 = array_merge( $params, array( $per, $offset ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $params2 ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}" );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d", $per, $offset ) );
		}

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Post link stats (incoming/outgoing) for dashboard.
	 *
	 * @param array $args Args.
	 * @return array{rows:array,total:int}
	 */
	public static function query_post_stats( array $args = array() ): array {
		global $wpdb;
		$table  = self::inventory_table();
		$q      = trim( (string) ( $args['q'] ?? '' ) );
		$orphan = ! empty( $args['orphan_only'] );
		$page   = max( 1, absint( $args['page'] ?? 1 ) );
		$per    = max( 10, min( 100, absint( $args['per_page'] ?? 40 ) ) );
		$offset = ( $page - 1 ) * $per;

		$post_types = array( 'post', 'page', 'product' );
		$query_args = array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => $per,
			'offset'                 => $offset,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( '' !== $q ) {
			$query_args['s'] = $q;
		}
		$pq = new WP_Query( $query_args );

		// Incoming counts map.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$incoming_rows = $wpdb->get_results(
			"SELECT source_post_id, dest_url FROM {$table} WHERE link_type = 'internal'"
		);
		$incoming = array();
		if ( is_array( $incoming_rows ) ) {
			foreach ( $incoming_rows as $ir ) {
				$dest_id = url_to_postid( (string) $ir->dest_url );
				if ( $dest_id > 0 ) {
					$incoming[ $dest_id ] = ( $incoming[ $dest_id ] ?? 0 ) + 1;
				}
			}
		}

		$out = array();
		foreach ( $pq->posts as $post ) {
			$pid = (int) $post->ID;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$counts = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						SUM(link_type='internal') AS internal_out,
						SUM(link_type='external') AS external_out
					FROM {$table} WHERE source_post_id = %d",
					$pid
				),
				ARRAY_A
			);
			$in  = (int) ( $incoming[ $pid ] ?? 0 );
			$int = (int) ( $counts['internal_out'] ?? 0 );
			$ext = (int) ( $counts['external_out'] ?? 0 );
			if ( $orphan && $in > 0 ) {
				continue;
			}
			$score = self::simple_link_score( $in, $int, $ext );
			$out[] = array(
				'post_id'       => $pid,
				'title'         => get_the_title( $pid ),
				'post_type'     => $post->post_type,
				'edit_url'      => get_edit_post_link( $pid, 'raw' ),
				'internal_out'  => $int,
				'external_out'  => $ext,
				'incoming'      => $in,
				'is_orphan'     => ( 0 === $in ),
				'seo_score'     => $score,
			);
		}

		return array(
			'rows'  => $out,
			'total' => (int) $pq->found_posts,
		);
	}

	/**
	 * Simple 0–100 score from link signals.
	 *
	 * @param int $incoming In.
	 * @param int $internal Internal out.
	 * @param int $external External out.
	 */
	public static function simple_link_score( int $incoming, int $internal, int $external ): int {
		$score = 40;
		$score += min( 30, $incoming * 6 );
		$score += min( 20, $internal * 4 );
		if ( $external > 15 ) {
			$score -= 10;
		}
		if ( 0 === $incoming ) {
			$score -= 25;
		}
		return max( 0, min( 100, $score ) );
	}

	/**
	 * Inventory counts for badges.
	 *
	 * @return array<string,int>
	 */
	public static function inventory_counts(): array {
		global $wpdb;
		$table = self::inventory_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$broken = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE http_status >= 400" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$redir = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_redirect = 1 OR (http_status >= 300 AND http_status < 400)" );
		return array(
			'total'    => $total,
			'broken'   => $broken,
			'redirect' => $redir,
		);
	}

	/**
	 * Extract linkable keyword phrases from a Persian/Latin title.
	 *
	 * @param string $title Title.
	 * @return string[]
	 */
	public static function keywords_from_title( string $title ): array {
		$title = trim( wp_strip_all_tags( $title ) );
		if ( '' === $title ) {
			return array();
		}

		$stop = array(
			'از', 'با', 'و', 'در', 'برای', 'به', 'که', 'این', 'آن', 'را', 'یک', 'یه', 'تا', 'روی', 'یا', 'هم', 'نیز',
			'است', 'بود', 'شد', 'شده', 'می', 'های', 'ها', 'ِ', 'ِ',
			'the', 'a', 'an', 'and', 'or', 'of', 'for', 'with', 'from', 'to', 'on', 'in', 'at',
		);
		if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
			$stop = array_merge( $stop, Damavand_Content_Analyzer::commerce_stopwords(), Damavand_Content_Analyzer::generic_product_tokens() );
		}
		$stop_map = array();
		foreach ( $stop as $s ) {
			$stop_map[ mb_strtolower( $s, 'UTF-8' ) ] = true;
		}

		$parts = preg_split( '/[\s\x{200C}\x{200B}\-\_\,\.\;\:\!\?\(\)\[\]「」«»\"\'\/\\\\]+/u', $title );
		if ( ! is_array( $parts ) ) {
			return array( $title );
		}

		$tokens = array();
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( mb_strlen( $p, 'UTF-8' ) < 2 ) {
				continue;
			}
			$key = mb_strtolower( $p, 'UTF-8' );
			if ( isset( $stop_map[ $key ] ) ) {
				continue;
			}
			if ( class_exists( 'Damavand_Content_Analyzer' ) && Damavand_Content_Analyzer::is_low_value_token( $p ) ) {
				continue;
			}
			$tokens[] = $p;
		}

		$out = array();
		if ( count( $tokens ) >= 2 ) {
			// Prefer 2–3 word phrases first (more natural anchors).
			$phrase = implode( ' ', array_slice( $tokens, 0, min( 3, count( $tokens ) ) ) );
			$out[]  = $phrase;
			if ( count( $tokens ) > 3 ) {
				$out[] = implode( ' ', array_slice( $tokens, 0, 2 ) );
			}
		}
		foreach ( array_slice( $tokens, 0, 4 ) as $t ) {
			$out[] = $t;
		}
		$out[] = $title;

		return self::parse_keywords( implode( "\n", $out ) );
	}

	/**
	 * Suggest source posts that could link to an orphan target.
	 *
	 * @param int $target_id Orphan post ID.
	 * @param int $limit     Max suggestions (3–8).
	 * @return array{ok:bool,message?:string,target?:array,keywords?:string[],suggestions?:array}
	 */
	public static function suggest_orphan_sources( int $target_id, int $limit = 5 ): array {
		$target = get_post( $target_id );
		if ( ! $target || 'publish' !== $target->post_status ) {
			return array( 'ok' => false, 'message' => __( 'صفحه مقصد معتبر نیست.', 'shojaei-seo-for-woo' ) );
		}

		$limit     = max( 3, min( 8, $limit ) );
		$permalink = (string) get_permalink( $target_id );
		$keywords  = class_exists( 'Damavand_Content_Analyzer' )
			? Damavand_Content_Analyzer::link_keywords_for_post( $target_id )
			: self::keywords_from_title( (string) $target->post_title );
		$path      = (string) wp_parse_url( $permalink, PHP_URL_PATH );
		$path      = $path ? untrailingslashit( $path ) : '';

		$already = array();
		if ( $path ) {
			global $wpdb;
			$inv  = self::inventory_table();
			$like = '%' . $wpdb->esc_like( $path ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT source_post_id FROM {$inv} WHERE link_type = 'internal' AND dest_url LIKE %s",
					$like
				)
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $sid ) {
					$already[ (int) $sid ] = true;
				}
			}
		}

		$tax_query = array( 'relation' => 'OR' );
		$taxonomies = get_object_taxonomies( $target->post_type, 'names' );
		$has_terms  = false;
		foreach ( (array) $taxonomies as $tax ) {
			if ( in_array( $tax, array( 'post_format', 'product_type', 'product_visibility', 'product_shipping_class' ), true ) ) {
				continue;
			}
			$terms = wp_get_object_terms( $target_id, $tax, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$has_terms    = true;
			$tax_query[]  = array(
				'taxonomy' => $tax,
				'field'    => 'term_id',
				'terms'    => $terms,
			);
		}

		$post_types = array( 'post', 'product' );
		if ( 'page' === $target->post_type ) {
			$post_types[] = 'page';
		}

		$query_args = array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => 60,
			'post__not_in'           => array( $target_id ),
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);
		if ( $has_terms && count( $tax_query ) > 1 ) {
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$candidates = get_posts( $query_args );
		if ( count( $candidates ) < $limit ) {
			$extra = get_posts(
				array(
					'post_type'              => $post_types,
					'post_status'            => 'publish',
					'posts_per_page'         => 40,
					'post__not_in'           => array_merge( array( $target_id ), wp_list_pluck( $candidates, 'ID' ) ),
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => true,
				)
			);
			$candidates = array_merge( $candidates, $extra );
		}

		$target_tokens = array();
		foreach ( $keywords as $kw ) {
			foreach ( preg_split( '/\s+/u', mb_strtolower( $kw, 'UTF-8' ) ) as $t ) {
				$t = trim( (string) $t );
				if ( mb_strlen( $t, 'UTF-8' ) >= 2 && ! ( class_exists( 'Damavand_Content_Analyzer' ) && Damavand_Content_Analyzer::is_low_value_token( $t ) ) ) {
					$target_tokens[ $t ] = true;
				}
			}
		}

		$scored = array();
		foreach ( $candidates as $post ) {
			$pid = (int) $post->ID;
			if ( isset( $already[ $pid ] ) ) {
				continue;
			}

			$score   = 0;
			$reasons = array();
			$html    = class_exists( 'Shojaei_SEO_Link_Builder' )
				? Shojaei_SEO_Link_Builder::resolve_linkable_html( $pid, (string) $post->post_content )
				: (string) $post->post_content;
			$text    = mb_strtolower( wp_strip_all_tags( $html ), 'UTF-8' );
			$title_l = mb_strtolower( (string) $post->post_title, 'UTF-8' );

			$shared = 0;
			foreach ( (array) $taxonomies as $tax ) {
				if ( in_array( $tax, array( 'post_format', 'product_type', 'product_visibility', 'product_shipping_class' ), true ) ) {
					continue;
				}
				$t_terms = wp_get_object_terms( $target_id, $tax, array( 'fields' => 'ids' ) );
				$s_terms = wp_get_object_terms( $pid, $tax, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $t_terms ) || is_wp_error( $s_terms ) || empty( $t_terms ) || empty( $s_terms ) ) {
					continue;
				}
				$shared += count( array_intersect( $t_terms, $s_terms ) );
			}
			if ( $shared > 0 ) {
				$score    += min( 40, $shared * 12 );
				$reasons[] = sprintf(
					/* translators: %d: shared terms */
					__( '%d دسته/برچسب مشترک', 'shojaei-seo-for-woo' ),
					$shared
				);
			}

			$overlap = 0;
			foreach ( array_keys( $target_tokens ) as $tok ) {
				if ( class_exists( 'Damavand_Content_Analyzer' ) && Damavand_Content_Analyzer::is_low_value_token( $tok ) ) {
					continue;
				}
				if ( false !== mb_strpos( $title_l, $tok, 0, 'UTF-8' ) || false !== mb_strpos( $text, $tok, 0, 'UTF-8' ) ) {
					++$overlap;
				}
			}
			if ( $overlap > 0 ) {
				$score    += min( 30, $overlap * 6 );
				$reasons[] = __( 'هم‌پوشانی کلمات متمایز', 'shojaei-seo-for-woo' );
			}

			if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
				$sim = Damavand_Content_Analyzer::description_similarity( $target_id, $pid );
				if ( $sim >= 0.08 ) {
					$score    += (int) round( min( 35, $sim * 120 ) );
					$reasons[] = __( 'شباهت توضیحات', 'shojaei-seo-for-woo' );
				}
				foreach ( Damavand_Content_Analyzer::related_keywords_for_post( $target_id ) as $rel ) {
					$needle = mb_strtolower( $rel, 'UTF-8' );
					if ( mb_strlen( $needle, 'UTF-8' ) < 2 ) {
						continue;
					}
					if ( false !== mb_strpos( $text, $needle, 0, 'UTF-8' ) || false !== mb_strpos( $title_l, $needle, 0, 'UTF-8' ) ) {
						$score    += 18;
						$reasons[] = sprintf(
							/* translators: %s: related phrase */
							__( 'کلمه مرتبط «%s» در مبدأ', 'shojaei-seo-for-woo' ),
							$rel
						);
						break;
					}
				}
			}

			$kw_hit = '';
			foreach ( $keywords as $kw ) {
				$needle = mb_strtolower( $kw, 'UTF-8' );
				if ( mb_strlen( $needle, 'UTF-8' ) < 2 ) {
					continue;
				}
				if ( false !== mb_strpos( $text, $needle, 0, 'UTF-8' ) ) {
					$kw_hit = $kw;
					$score += 25;
					$reasons[] = sprintf(
						/* translators: %s: keyword */
						__( 'کلمه «%s» در محتوا هست', 'shojaei-seo-for-woo' ),
						$kw
					);
					break;
				}
			}

			if ( $post->post_type === $target->post_type ) {
				$score += 5;
			}

			$words = str_word_count( wp_strip_all_tags( $html ) );
			if ( function_exists( 'mb_strlen' ) ) {
				$words = max( $words, (int) preg_match_all( '/[\p{L}\p{N}]+/u', wp_strip_all_tags( $html ) ) );
			}
			if ( $words >= 120 ) {
				$score += 8;
			} elseif ( $words < 40 ) {
				$score -= 10;
			}

			if ( $score < 8 && empty( $kw_hit ) && $shared < 1 ) {
				continue;
			}

			$scored[] = array(
				'post_id'       => $pid,
				'title'         => get_the_title( $pid ),
				'post_type'     => $post->post_type,
				'edit_url'      => get_edit_post_link( $pid, 'raw' ),
				'permalink'     => get_permalink( $pid ),
				'score'         => $score,
				'reasons'       => $reasons,
				'keyword_match' => $kw_hit,
				'checked'       => true,
			);
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return (int) $b['score'] <=> (int) $a['score'];
			}
		);
		$scored = array_slice( $scored, 0, $limit );
		foreach ( $scored as $i => $row ) {
			$scored[ $i ]['checked'] = $i < 3;
		}

		if ( empty( $scored ) ) {
			return array(
				'ok'          => true,
				'message'     => __( 'منبع مرتبط کافی پیدا نشد. ابتدا اسکن لینک‌ها را اجرا کنید یا نوشته‌های مرتبط‌تری منتشر کنید.', 'shojaei-seo-for-woo' ),
				'target'      => array(
					'post_id'   => $target_id,
					'title'     => get_the_title( $target_id ),
					'permalink' => $permalink,
					'edit_url'  => get_edit_post_link( $target_id, 'raw' ),
				),
				'keywords'    => $keywords,
				'suggestions' => array(),
			);
		}

		return array(
			'ok'          => true,
			'target'      => array(
				'post_id'   => $target_id,
				'title'     => get_the_title( $target_id ),
				'permalink' => $permalink,
				'edit_url'  => get_edit_post_link( $target_id, 'raw' ),
			),
			'keywords'    => $keywords,
			'suggestions' => $scored,
		);
	}

	/**
	 * Confirm orphan fix: create keyword map and insert one inbound link per selected source.
	 *
	 * Content is only changed after explicit admin confirmation (this AJAX action).
	 *
	 * @param int      $target_id  Orphan post ID.
	 * @param int[]    $source_ids Selected source post IDs.
	 * @param string   $keywords   Optional keyword override (newline/comma).
	 * @return array{ok:bool,message:string,map_id?:int,preview?:array}
	 */
	public static function apply_orphan_fix( int $target_id, array $source_ids, string $keywords = '' ): array {
		$target = get_post( $target_id );
		if ( ! $target || 'publish' !== $target->post_status ) {
			return array( 'ok' => false, 'message' => __( 'صفحه مقصد معتبر نیست.', 'shojaei-seo-for-woo' ) );
		}

		$permalink = (string) get_permalink( $target_id );
		if ( '' === $permalink ) {
			return array( 'ok' => false, 'message' => __( 'آدرس مقصد نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		$kw_list = self::parse_keywords( $keywords );
		if ( empty( $kw_list ) ) {
			$kw_list = self::keywords_from_title( (string) $target->post_title );
		}
		if ( empty( $kw_list ) ) {
			return array( 'ok' => false, 'message' => __( 'حداقل یک کلمه کلیدی لازم است.', 'shojaei-seo-for-woo' ) );
		}

		$sources = array();
		foreach ( $source_ids as $sid ) {
			$sid = absint( $sid );
			if ( $sid < 1 || $sid === $target_id ) {
				continue;
			}
			$p = get_post( $sid );
			if ( $p && 'publish' === $p->post_status ) {
				$sources[] = $sid;
			}
		}
		$sources = array_values( array_unique( $sources ) );
		if ( empty( $sources ) ) {
			return array( 'ok' => false, 'message' => __( 'حداقل یک نوشته مبدأ را انتخاب کنید.', 'shojaei-seo-for-woo' ) );
		}

		$map_name = sprintf(
			/* translators: %s: post title */
			__( 'یتیم: %s', 'shojaei-seo-for-woo' ),
			wp_trim_words( (string) $target->post_title, 8, '…' )
		);

		// Reuse existing map for same target URL if present.
		global $wpdb;
		$maps = self::maps_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$maps} WHERE target_url = %s ORDER BY id DESC LIMIT 1",
				$permalink
			)
		);

		$saved = self::save_map(
			array(
				'id'             => $existing_id,
				'name'           => $map_name,
				'target_url'     => $permalink,
				'keywords'       => implode( "\n", $kw_list ),
				'max_per_post'   => 2,
				'case_sensitive' => false,
				'is_active'      => true,
			)
		);
		if ( empty( $saved['ok'] ) ) {
			return $saved;
		}

		$preview = array();
		$inserted = 0;
		foreach ( $sources as $sid ) {
			delete_transient( 'shojaei_seo_linked_' . $sid );
			delete_transient( 'shojaei_seo_linked_short_' . $sid );

			$ins = self::insert_one_inbound_link( $sid, $permalink, $kw_list );
			if ( ! empty( $ins['inserted'] ) ) {
				++$inserted;
				self::index_post_links( $sid );
			}

			$html = class_exists( 'Shojaei_SEO_Link_Builder' )
				? Shojaei_SEO_Link_Builder::resolve_linkable_html( $sid, '' )
				: (string) get_post_field( 'post_content', $sid );
			$text = mb_strtolower( wp_strip_all_tags( $html ), 'UTF-8' );
			$hits = 0;
			foreach ( $kw_list as $kw ) {
				$needle = mb_strtolower( $kw, 'UTF-8' );
				if ( '' !== $needle && false !== mb_strpos( $text, $needle, 0, 'UTF-8' ) ) {
					++$hits;
				}
			}
			$preview[] = array(
				'post_id'      => $sid,
				'title'        => get_the_title( $sid ),
				'keyword_hits' => $hits,
				'inserted'     => ! empty( $ins['inserted'] ),
				'note'         => (string) ( $ins['message'] ?? '' ),
			);
		}

		if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
			Shojaei_SEO_Pulse::analyze_one( $target_id, true );
		}

		$map_url = admin_url( 'admin.php?page=shojaei-seo&tab=keyword-maps' );
		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: 1: map id, 2: inserted count, 3: source count */
				__( 'نقشه لینک #%1$d ذخیره شد. در %2$d از %3$d مبدأ لینک ورودی درج شد. اسکن لینک/نبض را برای به‌روز شدن وضعیت یتیم اجرا کنید.', 'shojaei-seo-for-woo' ),
				(int) ( $saved['id'] ?? 0 ),
				$inserted,
				count( $sources )
			),
			'map_id'  => (int) ( $saved['id'] ?? 0 ),
			'map_url' => $map_url,
			'preview' => $preview,
		);
	}

	/**
	 * Insert a single confirmed inbound link into a source post (first keyword hit).
	 *
	 * @param int      $source_id Source post ID.
	 * @param string   $target_url Destination permalink.
	 * @param string[] $keywords   Keywords longest-first preferred.
	 * @return array{inserted:bool,message:string}
	 */
	public static function insert_one_inbound_link( int $source_id, string $target_url, array $keywords ): array {
		$post = get_post( $source_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array( 'inserted' => false, 'message' => __( 'مبدأ نامعتبر.', 'shojaei-seo-for-woo' ) );
		}

		$field = 'post_content';
		$html  = (string) $post->post_content;
		if ( 'product' === $post->post_type && '' === trim( wp_strip_all_tags( $html ) ) && '' !== trim( wp_strip_all_tags( (string) $post->post_excerpt ) ) ) {
			$field = 'post_excerpt';
			$html  = (string) $post->post_excerpt;
		}
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array( 'inserted' => false, 'message' => __( 'محتوای قابل لینک ندارد.', 'shojaei-seo-for-woo' ) );
		}

		$path = (string) wp_parse_url( $target_url, PHP_URL_PATH );
		$path = $path ? untrailingslashit( $path ) : '';
		if ( $path && false !== stripos( $html, $path ) ) {
			return array( 'inserted' => false, 'message' => __( 'قبلاً به این مقصد لینک دارد.', 'shojaei-seo-for-woo' ) );
		}

		// Longest keyword first.
		usort(
			$keywords,
			static function ( $a, $b ) {
				return mb_strlen( (string) $b, 'UTF-8' ) <=> mb_strlen( (string) $a, 'UTF-8' );
			}
		);

		$new_html = null;
		$used_kw  = '';
		foreach ( $keywords as $kw ) {
			$kw = trim( (string) $kw );
			if ( mb_strlen( $kw, 'UTF-8' ) < 2 ) {
				continue;
			}
			$replaced = self::replace_first_keyword_outside_anchors( $html, $kw, $target_url );
			if ( null !== $replaced ) {
				$new_html = $replaced;
				$used_kw  = $kw;
				break;
			}
		}

		if ( null === $new_html ) {
			// Append a short related sentence with link (last resort, still confirmed).
			$anchor = ! empty( $keywords[0] ) ? $keywords[0] : get_the_title( url_to_postid( $target_url ) );
			$anchor = $anchor ? $anchor : __( 'این صفحه', 'shojaei-seo-for-woo' );
			$append = '<p>' . sprintf(
				/* translators: %s: linked anchor */
				__( 'همچنین ببینید: %s', 'shojaei-seo-for-woo' ),
				'<a href="' . esc_url( $target_url ) . '">' . esc_html( $anchor ) . '</a>'
			) . '</p>';
			$new_html = rtrim( $html ) . "\n\n" . $append;
			$used_kw  = (string) $anchor;
		}

		$update = array(
			'ID'   => $source_id,
			$field => $new_html,
		);
		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return array( 'inserted' => false, 'message' => $result->get_error_message() );
		}

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			Shojaei_SEO_Revert_Log::record(
				array(
					'batch_id'    => Shojaei_SEO_Revert_Log::new_batch_id(),
					'mode'        => 'applied',
					'action'      => 'orphan_inbound_link',
					'entity_type' => $post->post_type,
					'entity_id'   => $source_id,
					'summary'     => sprintf(
						/* translators: 1: source title, 2: keyword */
						__( 'لینک ورودی یتیم در «%1$s» با لنگر «%2$s»', 'shojaei-seo-for-woo' ),
						$post->post_title,
						$used_kw
					),
					'before'      => array( $field => $html ),
					'after'       => array( $field => $new_html, 'target_url' => $target_url ),
				)
			);
		}

		return array(
			'inserted' => true,
			'message'  => sprintf(
				/* translators: %s: keyword */
				__( 'لینک با لنگر «%s» درج شد.', 'shojaei-seo-for-woo' ),
				$used_kw
			),
		);
	}

	/**
	 * Replace first plain-text occurrence of keyword outside existing <a> tags.
	 *
	 * @param string $html HTML.
	 * @param string $keyword Keyword.
	 * @param string $url Target URL.
	 * @return string|null New HTML or null if not found.
	 */
	private static function replace_first_keyword_outside_anchors( string $html, string $keyword, string $url ): ?string {
		$parts = preg_split( '/(<a\b[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$quoted = preg_quote( $keyword, '/' );
		$pattern = '/' . $quoted . '/u';
		$link    = '<a href="' . esc_url( $url ) . '">' . esc_html( $keyword ) . '</a>';
		$done    = false;
		foreach ( $parts as $i => $chunk ) {
			if ( $done ) {
				continue;
			}
			if ( preg_match( '/^<a\b/i', $chunk ) ) {
				continue;
			}
			// Skip inside other tags' attributes roughly by only replacing in text nodes between tags.
			$sub = preg_split( '/(<[^>]+>)/', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( ! is_array( $sub ) ) {
				continue;
			}
			foreach ( $sub as $j => $piece ) {
				if ( $done || '' === $piece || '<' === $piece[0] ) {
					continue;
				}
				if ( preg_match( $pattern, $piece ) ) {
					$sub[ $j ] = preg_replace( $pattern, $link, $piece, 1 );
					$done      = true;
				}
			}
			$parts[ $i ] = implode( '', $sub );
		}
		if ( ! $done ) {
			return null;
		}
		return implode( '', $parts );
	}
}
