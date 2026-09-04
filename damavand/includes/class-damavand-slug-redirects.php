<?php
/**
 * Slug redirect table, CRUD, serve old-slug 301s, post_updated hooks.
 *
 * Extracted from Shojaei_SEO_Slug (Task 5). Facade wrappers remain on Shojaei_SEO_Slug.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Slug_Redirects
 */
class Damavand_Slug_Redirects {
	/** @var bool|null */
	private static $slug_table_exists = null;

	/**
	 * Slug redirects table.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_slug_redirects';
	}

	/**
	 * Slug redirects table availability (cached per-request).
	 */
	private static function has_slug_table(): bool {
		if ( null !== self::$slug_table_exists ) {
			return self::$slug_table_exists;
		}
		global $wpdb;
		$table = self::table();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		self::$slug_table_exists = ( $found === $table );
		return self::$slug_table_exists;
	}

	/**
	 * When published post/product slug changes → store 301 + activity log.
	 *
	 * @param int     $post_id     ID.
	 * @param WP_Post $post_after  After.
	 * @param WP_Post $post_before Before.
	 */
	public function on_post_updated( int $post_id, $post_after, $post_before ): void {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_301', 'yes' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post_after || ! $post_before || ! Damavand_Slug_Finglish::is_supported_type( (string) $post_after->post_type ) ) {
			return;
		}
		// Only create 301 when the content was already published (real URL change).
		if ( 'publish' !== $post_before->post_status ) {
			return;
		}

		$old_slug = (string) $post_before->post_name;
		$new_slug = (string) $post_after->post_name;
		if ( '' === $old_slug || '' === $new_slug || $old_slug === $new_slug ) {
			return;
		}

		$new_url = get_permalink( $post_id );
		if ( ! $new_url ) {
			return;
		}

		$old_url = self::swap_slug_in_url( $new_url, $new_slug, $old_slug );
		if ( ! $old_url || $old_url === $new_url ) {
			return;
		}

		self::save_redirect( $post_id, $old_slug, $old_url, $new_url, '301' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'slug_redirect',
				sprintf(
					/* translators: 1: old slug, 2: new slug */
					__( 'نامک عوض شد؛ ریدایرکت ۳۰۱ از «%1$s» به «%2$s»', 'shojaei-seo-for-woo' ),
					$old_slug,
					$new_slug
				),
				$post_id
			);
		}

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @param int     $post_id     ID.
	 * @param WP_Post $post_after  After.
	 * @param WP_Post $post_before Before.
	 */
	public function on_product_updated( int $post_id, $post_after, $post_before ): void {
		$this->on_post_updated( $post_id, $post_after, $post_before );
	}

	/**
	 * Replace trailing slug in product URL.
	 *
	 * @param string $url      New URL.
	 * @param string $new_slug New slug.
	 * @param string $old_slug Old slug.
	 */
	public static function swap_slug_in_url( string $url, string $new_slug, string $old_slug ): string {
		$to = rawurldecode( trim( $old_slug, '/' ) );
		if ( '' === $to ) {
			$to = trim( $old_slug, '/' );
		}
		foreach ( self::slug_variants( $new_slug ) as $from ) {
			$pattern = '#/' . preg_quote( $from, '#' ) . '(/)?$#u';
			$out     = preg_replace( $pattern, '/' . $to . '$1', $url, 1 );
			if ( is_string( $out ) && $out !== $url ) {
				return $out;
			}
		}
		return '';
	}

	/**
	 * Encoded + decoded forms of a post_name (WP stores Persian as %d8%aa…).
	 *
	 * @param string $slug Slug.
	 * @return string[]
	 */
	public static function slug_variants( string $slug ): array {
		$slug = trim( str_replace( '\\', '', $slug ), '/' );
		if ( '' === $slug ) {
			return array();
		}
		$decoded = rawurldecode( $slug );
		$pieces  = preg_split( '/(-)/', $decoded, -1, PREG_SPLIT_DELIM_CAPTURE );
		$encoded = '';
		if ( is_array( $pieces ) ) {
			foreach ( $pieces as $piece ) {
				$encoded .= ( '-' === $piece ) ? '-' : rawurlencode( $piece );
			}
		}
		$out = array();
		foreach ( array( $slug, $decoded, $encoded, strtolower( $encoded ), strtolower( $slug ) ) as $v ) {
			$v = trim( (string) $v, '/' );
			if ( '' !== $v ) {
				$out[ $v ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Persist slug redirect row.
	 *
	 * @param int    $product_id Product.
	 * @param string $old_slug   Old slug.
	 * @param string $old_url    Old URL.
	 * @param string $new_url    New URL.
	 * @param string $type       301/302.
	 */
	public static function save_redirect( int $product_id, string $old_slug, string $old_url, string $new_url, string $type = '301' ): int {
		global $wpdb;
		$table = self::table();

		$old_path = self::path_key( $old_url );
		if ( '' === $old_path ) {
			return 0;
		}

		$store_slug = rawurldecode( trim( $old_slug, '/' ) );
		if ( '' === $store_slug ) {
			$store_slug = trim( $old_slug, '/' );
		}
		$store_slug = substr( $store_slug, 0, 500 );

		// Deactivate duplicate active rows for same path (decoded + encoded keys).
		foreach ( array_unique( array( $old_path, self::path_key( rawurldecode( $old_url ) ) ) ) as $key ) {
			if ( '' === $key ) {
				continue;
			}
			$wpdb->update(
				$table,
				array( 'is_active' => 0 ),
				array(
					'old_path'  => $key,
					'is_active' => 1,
				),
				array( '%d' ),
				array( '%s', '%d' )
			);
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'product_id'    => $product_id,
				'old_slug'      => $store_slug,
				'old_path'      => $old_path,
				'old_url'       => esc_url_raw( $old_url ),
				'new_url'       => esc_url_raw( $new_url ),
				'redirect_type' => in_array( $type, array( '301', '302' ), true ) ? $type : '301',
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Normalize path key for lookup (lowercase, no trailing slash).
	 *
	 * @param string $url URL.
	 */
	public static function path_key( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		// Prefer decoded path so 404 lookup matches $wp->request for Persian slugs.
		$path = rawurldecode( $path );
		$path = untrailingslashit( strtolower( $path ) );
		return $path ? $path : '';
	}

	/**
	 * Build path keys to match stored redirects (encoded + decoded Persian).
	 *
	 * @param string $req_path Request path.
	 * @return string[]
	 */
	public static function path_lookup_candidates( string $req_path ): array {
		$req_path = trim( $req_path );
		if ( '' === $req_path ) {
			return array();
		}
		if ( '/' !== substr( $req_path, 0, 1 ) ) {
			$req_path = '/' . $req_path;
		}

		$decoded = rawurldecode( $req_path );
		$parts   = explode( '/', trim( $decoded, '/' ) );
		$encoded = '/' . implode(
			'/',
			array_map(
				static function ( $part ) {
					return rawurlencode( (string) $part );
				},
				$parts
			)
		);

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );

		$raw = array( $req_path, $decoded, $encoded );
		if ( $home_path && '/' !== $home_path ) {
			foreach ( array( $req_path, $decoded, $encoded ) as $p ) {
				if ( 0 === strpos( $p, $home_path . '/' ) || $p === $home_path ) {
					continue;
				}
				$raw[] = untrailingslashit( $home_path ) . $p;
			}
		}

		$out = array();
		foreach ( $raw as $p ) {
			$key = untrailingslashit( strtolower( (string) $p ) );
			if ( '' !== $key && '/' !== $key ) {
				$out[ $key ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * On 404, apply stored slug redirect.
	 */
	public function maybe_redirect_old_slug(): void {
		if ( ! is_404() ) {
			return;
		}
		if ( ! self::has_slug_table() ) {
			return;
		}

		global $wpdb, $wp;
		$candidates = array();

		if ( isset( $wp->request ) && is_string( $wp->request ) && '' !== $wp->request ) {
			$candidates = array_merge( $candidates, self::path_lookup_candidates( '/' . trim( $wp->request, '/' ) ) );
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$uri_path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( $uri_path ) {
			$candidates = array_merge( $candidates, self::path_lookup_candidates( $uri_path ) );
		}

		$candidates = array_values( array_unique( $candidates ) );
		if ( empty( $candidates ) ) {
			return;
		}

		$table = self::table();
		$row   = null;
		foreach ( $candidates as $key ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT new_url, redirect_type FROM {$table} WHERE old_path = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
					$key
				)
			);
			if ( $row && ! empty( $row->new_url ) ) {
				break;
			}
			$row = null;
		}

		if ( ! $row || empty( $row->new_url ) ) {
			return;
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::do_not_cache();
		}

		$code = ( '302' === $row->redirect_type ) ? 302 : 301;
		wp_safe_redirect( esc_url_raw( $row->new_url ), $code );
		exit;
	}

	/**
	 * List slug redirects for admin UI.
	 *
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public static function list_redirects( int $limit = 100 ): array {
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
	 * Count active slug redirects.
	 */
	public static function count_active_redirects(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1" );
	}

	/**
	 * Toggle redirect active flag.
	 *
	 * @param int $id     Row ID.
	 * @param int $active 1|0.
	 */
	public static function set_redirect_active( int $id, int $active ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$ok = $wpdb->update(
			self::table(),
			array( 'is_active' => $active ? 1 : 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Update destination URL of a slug redirect (chain flatten).
	 *
	 * @param int    $id      Row ID.
	 * @param string $new_url Absolute target URL.
	 */
	public static function update_redirect_target( int $id, string $new_url ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$new_url = esc_url_raw( $new_url );
		if ( '' === $new_url ) {
			return false;
		}
		$ok = $wpdb->update(
			self::table(),
			array( 'new_url' => $new_url ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Delete a slug redirect row.
	 *
	 * @param int $id Row ID.
	 */
	public static function delete_redirect( int $id ): bool {
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
	 * Latest active slug redirect id for product + old slug.
	 */
	public static function latest_redirect_id_for_product( int $product_id, string $old_slug = '' ): int {
		global $wpdb;
		$table = self::table();
		if ( $old_slug ) {
			$variants = self::slug_variants( $old_slug );
			if ( empty( $variants ) ) {
				return 0;
			}
			$in  = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
			$sql = $wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND is_active = 1 AND old_slug IN ({$in}) ORDER BY id DESC LIMIT 1",
				array_merge( array( $product_id ), $variants )
			);
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
				$product_id
			)
		);
		return (int) $id;
	}

	/**
	 * WordPress core redirect_canonical / wp_old_slug_redirect will 301 this slug.
	 */
	public static function wp_old_slug_covers( int $product_id, string $old_slug ): bool {
		$stored = get_post_meta( $product_id, '_wp_old_slug', false );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return false;
		}
		$want = self::slug_variants( $old_slug );
		if ( empty( $want ) ) {
			return false;
		}
		$want_map = array_fill_keys( $want, true );
		foreach ( $stored as $s ) {
			foreach ( self::slug_variants( (string) $s ) as $v ) {
				if ( isset( $want_map[ $v ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

}
