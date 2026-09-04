<?php
/**
 * Manual redirects — Rank Math–style freeform source → destination.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Manual_Redirect
 */
class Shojaei_SEO_Manual_Redirect {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 0 );
	}

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'seo_core_manual_redirects';
	}

	/**
	 * Allowed redirect types.
	 *
	 * @return string[]
	 */
	public static function allowed_types(): array {
		return array( '301', '302', '307', '410', '451' );
	}

	/**
	 * Allowed match modes.
	 *
	 * @return string[]
	 */
	public static function allowed_match_types(): array {
		return array( 'exact', 'contains', 'start', 'regex', 'archive' );
	}

	/**
	 * Normalize a source URL/path to a comparable path key.
	 *
	 * @param string $raw Raw input.
	 */
	public static function normalize_source( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $raw ) ) {
			$path = (string) wp_parse_url( $raw, PHP_URL_PATH );
		} else {
			$path = $raw;
		}
		$path = '/' . ltrim( $path, '/' );
		$path = rawurldecode( $path );
		$path = untrailingslashit( $path );
		if ( '' === $path ) {
			$path = '/';
		}
		return $path;
	}

	/**
	 * Current request path candidates.
	 *
	 * @return string[]
	 */
	public static function request_paths(): array {
		global $wp;
		$candidates = array();
		if ( ! empty( $wp->request ) ) {
			$candidates[] = '/' . ltrim( (string) $wp->request, '/' );
		}
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$uri  = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
			if ( $path ) {
				$candidates[] = untrailingslashit( $path ) ?: '/';
				$candidates[] = rawurldecode( untrailingslashit( $path ) ?: '/' );
			}
		}
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );
		$out       = array();
		foreach ( $candidates as $c ) {
			$c = untrailingslashit( '/' . ltrim( $c, '/' ) );
			if ( '' === $c ) {
				$c = '/';
			}
			$out[] = $c;
			if ( $home_path && 0 === strpos( $c, $home_path . '/' ) ) {
				$stripped = substr( $c, strlen( $home_path ) );
				$stripped = untrailingslashit( $stripped ?: '/' );
				$out[]    = $stripped ?: '/';
			}
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Install schema (activation / upgrade).
	 */
	public static function install(): void {
		self::create_table();
	}

	/**
	 * Drop table (full uninstall wipe only — not on deactivate).
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Create table (dbDelta).
	 */
	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		$sql     = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id VARCHAR(32) NOT NULL DEFAULT '',
			source_raw VARCHAR(500) NOT NULL DEFAULT '',
			source_path VARCHAR(500) NOT NULL DEFAULT '',
			match_type VARCHAR(20) NOT NULL DEFAULT 'exact',
			ignore_case TINYINT(1) NOT NULL DEFAULT 0,
			destination TEXT NULL,
			redirect_type VARCHAR(10) NOT NULL DEFAULT '301',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			hits BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY source_active (source_path(191), is_active),
			KEY group_id (group_id),
			KEY is_active (is_active)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * List redirects (newest first), grouped visually by group_id.
	 *
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public static function list_redirects( int $limit = 200 ): array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 500, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count active.
	 */
	public static function count_active(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1" );
	}

	/**
	 * Sanitize type.
	 *
	 * @param string $type Raw.
	 */
	public static function sanitize_type( string $type ): string {
		$type = strtoupper( trim( $type ) );
		return in_array( $type, self::allowed_types(), true ) ? $type : '301';
	}

	/**
	 * Sanitize match type.
	 *
	 * @param string $match Raw.
	 */
	public static function sanitize_match( string $match ): string {
		$match = strtolower( trim( $match ) );
		return in_array( $match, self::allowed_match_types(), true ) ? $match : 'exact';
	}

	/**
	 * Insert one or more sources as a group.
	 *
	 * @param array $args {
	 *   @type string[] $sources
	 *   @type string   $destination
	 *   @type string   $redirect_type
	 *   @type string   $match_type
	 *   @type bool     $ignore_case
	 *   @type bool     $is_active
	 * }
	 * @return array{ok:bool,message:string,ids?:int[],group_id?:string}
	 */
	public static function add_redirect( array $args ): array {
		global $wpdb;

		$sources = $args['sources'] ?? array();
		if ( is_string( $sources ) ) {
			$sources = preg_split( '/\r\n|\r|\n/', $sources );
		}
		if ( ! is_array( $sources ) ) {
			$sources = array();
		}
		$sources = array_values(
			array_filter(
				array_map(
					static function ( $s ) {
						return trim( (string) $s );
					},
					$sources
				)
			)
		);
		if ( empty( $sources ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'حداقل یک آدرس مبدأ لازم است.', 'shojaei-seo-for-woo' ),
			);
		}

		$type     = self::sanitize_type( (string) ( $args['redirect_type'] ?? '301' ) );
		$match    = self::sanitize_match( (string) ( $args['match_type'] ?? 'exact' ) );
		$ignore   = ! empty( $args['ignore_case'] ) ? 1 : 0;
		$active   = array_key_exists( 'is_active', $args ) ? ( ! empty( $args['is_active'] ) ? 1 : 0 ) : 1;
		$dest_raw = trim( (string) ( $args['destination'] ?? '' ) );
		$covers_pagination = ! array_key_exists( 'covers_pagination', $args ) || ! empty( $args['covers_pagination'] );

		if ( in_array( $type, array( '301', '302', '307' ), true ) && '' === $dest_raw ) {
			return array(
				'ok'      => false,
				'message' => __( 'آدرس مقصد برای ریدایرکت لازم است.', 'shojaei-seo-for-woo' ),
			);
		}

		$destination = '';
		if ( '' !== $dest_raw ) {
			if ( preg_match( '#^https?://#i', $dest_raw ) ) {
				$destination = esc_url_raw( $dest_raw );
			} else {
				$destination = esc_url_raw( home_url( '/' . ltrim( $dest_raw, '/' ) ) );
			}
			if ( '' === $destination ) {
				return array(
					'ok'      => false,
					'message' => __( 'آدرس مقصد نامعتبر است.', 'shojaei-seo-for-woo' ),
				);
			}
		}

		$group_id = substr( md5( uniqid( 'mr', true ) ), 0, 16 );
		$ids      = array();
		$table    = self::table();

		foreach ( $sources as $src ) {
			$path = self::normalize_source( $src );
			if ( '' === $path ) {
				continue;
			}

			$row_match = $match;
			if ( $covers_pagination && 'exact' === $match && self::resolve_archive_term( $path ) ) {
				$row_match = 'archive';
			}

			if ( in_array( $type, array( '301', '302', '307' ), true ) && $destination && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
				$from = home_url( $path );
				$valid = Shojaei_SEO_Redirect_Engine::validate_redirect( $from, $destination, 0 );
				if ( is_wp_error( $valid ) ) {
					return array(
						'ok'      => false,
						'message' => $valid->get_error_message(),
					);
				}
			}

			$ok = $wpdb->insert(
				$table,
				array(
					'group_id'      => $group_id,
					'source_raw'    => $src,
					'source_path'   => $path,
					'match_type'    => $row_match,
					'ignore_case'   => $ignore,
					'destination'   => $destination,
					'redirect_type' => $type,
					'is_active'     => $active,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
			);
			if ( $ok ) {
				$ids[] = (int) $wpdb->insert_id;
			}
		}

		if ( empty( $ids ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'ذخیره ریدایرکت ناموفق بود.', 'shojaei-seo-for-woo' ),
			);
		}

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'manual_redirect',
				sprintf(
					/* translators: 1: count, 2: type */
					__( 'ریدایرکت دستی %2$s برای %1$d مبدأ ثبت شد', 'shojaei-seo-for-woo' ),
					count( $ids ),
					$type
				),
				0,
				array( 'group_id' => $group_id, 'ids' => $ids )
			);
		}

		return array(
			'ok'       => true,
			'message'  => __( 'ریدایرکت ذخیره شد.', 'shojaei-seo-for-woo' ),
			'ids'      => $ids,
			'group_id' => $group_id,
		);
	}

	/**
	 * Toggle active.
	 *
	 * @param int $id     Row.
	 * @param int $active 1|0.
	 */
	public static function set_active( int $id, int $active ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$ok = $wpdb->update(
			self::table(),
			array(
				'is_active'  => $active ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Delete row.
	 *
	 * @param int $id Row.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$ok = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Does a rule match a request path?
	 *
	 * @param object $row  DB row.
	 * @param string $path Request path.
	 */
	public static function row_matches( object $row, string $path ): bool {
		$src    = (string) ( $row->source_path ?? '' );
		$match  = self::sanitize_match( (string) ( $row->match_type ?? 'exact' ) );
		$ignore = ! empty( $row->ignore_case );
		if ( '' === $src || '' === $path ) {
			return false;
		}
		$a = $ignore ? mb_strtolower( $path, 'UTF-8' ) : $path;
		$b = $ignore ? mb_strtolower( $src, 'UTF-8' ) : $src;

		switch ( $match ) {
			case 'archive':
				$base = untrailingslashit( $b );
				if ( $a === $b || $a === $base ) {
					return true;
				}
				return (bool) preg_match( '#^' . preg_quote( $base, '#' ) . '/page/\d+/?$#u', $a );
			case 'contains':
				return false !== mb_strpos( $a, $b, 0, 'UTF-8' );
			case 'start':
				return 0 === mb_strpos( $a, $b, 0, 'UTF-8' );
			case 'regex':
				$pattern = (string) ( $row->source_raw ?: $src );
				if ( ! preg_match( '/^\/.+\/[imsxuADU]*$/', $pattern ) ) {
					$pattern = '/' . str_replace( '/', '\/', $src ) . '/u' . ( $ignore ? 'i' : '' );
				}
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$result = @preg_match( $pattern, $path );
				return 1 === $result;
			case 'exact':
			default:
				return $a === $b || $a === untrailingslashit( $b ) || untrailingslashit( $a ) === untrailingslashit( $b );
		}
	}

	/**
	 * Find first matching active redirect for current request.
	 *
	 * @return object|null
	 */
	public static function find_match(): ?object {
		global $wpdb;
		$table = self::table();
		$paths = self::request_paths();
		if ( empty( $paths ) ) {
			return null;
		}

		// Fast path: exact matches via SQL.
		foreach ( $paths as $path ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE is_active = 1 AND match_type = 'exact' AND (source_path = %s OR source_path = %s)
					ORDER BY id DESC LIMIT 1",
					$path,
					untrailingslashit( $path ) ?: '/'
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		// Non-exact: load active non-exact rules (bounded).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT * FROM {$table}
			WHERE is_active = 1 AND match_type != 'exact'
			ORDER BY id DESC LIMIT 300"
		);
		if ( ! is_array( $rows ) ) {
			return null;
		}
		foreach ( $rows as $row ) {
			foreach ( $paths as $path ) {
				if ( self::row_matches( $row, $path ) ) {
					return $row;
				}
			}
		}
		return null;
	}

	/**
	 * Bump hit counter.
	 *
	 * @param int $id Row.
	 */
	public static function bump_hits( int $id ): void {
		global $wpdb;
		if ( $id < 1 ) {
			return;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = hits + 1 WHERE id = %d", $id ) );
	}

	/**
	 * Execute redirect on frontend.
	 */
	public function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		// Avoid hijacking WP login / cron / REST unnecessarily.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		// هسته سئو: در Passive یا ماژول خاموش، emit ریدایرکت دستی متوقف شود.
		if ( class_exists( 'SEO_Core_Redirects_Module' ) && ! SEO_Core_Redirects_Module::can_emit_freeform() ) {
			return;
		}
		if ( class_exists( 'SEO_Core_Installer' ) && ! class_exists( 'SEO_Core_Redirects_Module' ) && ! SEO_Core_Installer::is_module_enabled( 'redirects' ) ) {
			return;
		}

		$row = self::find_match();
		if ( ! $row ) {
			return;
		}

		$type = self::sanitize_type( (string) $row->redirect_type );
		self::bump_hits( (int) $row->id );

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::do_not_cache();
		}

		if ( '410' === $type ) {
			status_header( 410 );
			nocache_headers();
			wp_die(
				esc_html__( 'این محتوا برای همیشه حذف شده است (410 Gone).', 'shojaei-seo-for-woo' ),
				'410 Gone',
				array( 'response' => 410 )
			);
		}

		if ( '451' === $type ) {
			status_header( 451 );
			nocache_headers();
			wp_die(
				esc_html__( 'این محتوا به دلایل قانونی در دسترس نیست (451).', 'shojaei-seo-for-woo' ),
				'451 Unavailable For Legal Reasons',
				array( 'response' => 451 )
			);
		}

		$dest = (string) ( $row->destination ?? '' );
		if ( '' === $dest ) {
			return;
		}

		$code = (int) $type;
		if ( ! in_array( $code, array( 301, 302, 307 ), true ) ) {
			$code = 301;
		}
		wp_safe_redirect( esc_url_raw( $dest ), $code );
		exit;
	}

	/**
	 * Active map for loop detection (source URL → destination).
	 *
	 * @return array<string,string>
	 */
	public static function active_map(): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT source_path, destination, redirect_type FROM {$table}
			WHERE is_active = 1 AND redirect_type IN ('301','302','307') AND destination != ''
			LIMIT 1000"
		);
		$map = array();
		if ( ! is_array( $rows ) ) {
			return $map;
		}
		foreach ( $rows as $row ) {
			$from = home_url( (string) $row->source_path );
			$to   = (string) $row->destination;
			if ( $from && $to ) {
				$map[ $from ] = $to;
			}
		}
		return $map;
	}

	/**
	 * Detect taxonomy archive from normalized path.
	 *
	 * @param string $path Normalized path.
	 * @return array{term_id:int,taxonomy:string,name:string}|null
	 */
	public static function resolve_archive_term( string $path ): ?array {
		$path = preg_replace( '#/page/\d+/?$#', '', untrailingslashit( $path ) );
		$path = untrailingslashit( $path ) ?: '/';

		$home_path = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( $home_path && 0 === strpos( $path, $home_path . '/' ) ) {
			$path = untrailingslashit( substr( $path, strlen( $home_path ) ) ) ?: '/';
		}
		if ( '/' !== $path[0] ) {
			$path = '/' . ltrim( $path, '/' );
		}

		$bases = array(
			'product_cat' => self::wc_term_base( 'category_base', 'product-category' ),
			'product_tag' => self::wc_term_base( 'tag_base', 'product-tag' ),
			'category'    => 'category',
			'post_tag'    => 'tag',
		);

		foreach ( $bases as $tax => $base ) {
			$base = trim( (string) $base, '/' );
			if ( '' === $base ) {
				continue;
			}
			$pattern = '#/' . preg_quote( $base, '#' ) . '/([^/]+)/?$#u';
			if ( ! preg_match( $pattern, $path, $m ) ) {
				continue;
			}
			$slug = rawurldecode( (string) ( $m[1] ?? '' ) );
			if ( '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				return array(
					'term_id'   => (int) $term->term_id,
					'taxonomy'  => (string) $term->taxonomy,
					'name'      => (string) $term->name,
				);
			}
		}

		return null;
	}

	/**
	 * @param string $key     Woo permalinks key.
	 * @param string $default Default base.
	 */
	private static function wc_term_base( string $key, string $default ): string {
		$permalinks = get_option( 'woocommerce_permalinks', array() );
		if ( is_array( $permalinks ) && ! empty( $permalinks[ $key ] ) ) {
			return trim( (string) $permalinks[ $key ], '/' );
		}
		return $default;
	}

	/**
	 * Estimate archive page count for a term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function estimate_archive_pages( int $term_id, string $taxonomy ): int {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return 1;
		}
		$count = (int) $term->count;
		if ( in_array( $taxonomy, array( 'product_cat', 'product_tag' ), true ) && function_exists( 'wc_get_default_products_per_row' ) ) {
			$per_page = (int) apply_filters(
				'loop_shop_per_page',
				max( 1, wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() )
			);
		} else {
			$per_page = max( 1, (int) get_option( 'posts_per_page' ) );
		}
		return max( 1, (int) ceil( $count / max( 1, $per_page ) ) );
	}

	/**
	 * Preview archive redirect implications for admin UI.
	 *
	 * @param string $raw Raw source input.
	 * @return array<string,mixed>
	 */
	public static function preview_archive_source( string $raw ): array {
		$path = self::normalize_source( $raw );
		$term = self::resolve_archive_term( $path );
		if ( ! $term ) {
			return array( 'is_archive' => false );
		}
		$pages = self::estimate_archive_pages( (int) $term['term_id'], (string) $term['taxonomy'] );
		return array(
			'is_archive'   => true,
			'term_name'    => (string) $term['name'],
			'taxonomy'     => (string) $term['taxonomy'],
			'page_count'   => $pages,
			'needs_paging' => $pages > 1,
			'message'      => $pages > 1
				? sprintf(
					/* translators: 1: term name, 2: page count */
					__( 'دسته «%1$s» حدود %2$d صفحه دارد. با گزینه «شامل صفحه‌بندی»، آدرس‌های page/2 تا page/%2$d هم خودکار پوشش داده می‌شوند.', 'shojaei-seo-for-woo' ),
					(string) $term['name'],
					(int) $pages
				)
				: sprintf(
					/* translators: %s: term name */
					__( 'آرشیو دسته «%s» شناسایی شد.', 'shojaei-seo-for-woo' ),
					(string) $term['name']
				),
		);
	}
}
