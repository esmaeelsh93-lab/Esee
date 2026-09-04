<?php
/**
 * Smart Sitemap — GSC-ready XML (pagination, real lastmod, product images).
 *
 * Endpoint: /shojaei-sitemap.xml (+ typed / paginated submaps).
 * Better than core wp-sitemap: Woo images, 410/noindex filters, Transient+object cache.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Sitemap
 */
class SEO_Core_Sitemap extends SEO_Core_Module {

	public const QUERY_VAR = 'shojaei_seo_sitemap';
	public const BASE      = 'shojaei-sitemap';
	public const STATS_OPT = 'seo_core_sitemap_stats';

	/** Soft cap per file (GSC allows 50k; keep smaller for shared hosts + image XML). */
	private const MAX_URLS = 2000;

	/** XML / id-list cache TTL (also exposed for health UI). */
	public const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** True when this request used REQUEST_URI hijack (rewrite miss). */
	private $served_via_fallback = false;

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'sitemap';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'نقشه سایت هوشمند', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'نقشه XML برای همه فروشگاه‌ها: محصول/دسته/برچسب، تصویر گالری، lastmod واقعی، ثبت در robots و GSC.', 'shojaei-seo-for-woo' );
	}

	/**
	 * Unique path — never force Passive when Rank Math is active.
	 */
	public function is_passive(): bool {
		return false;
	}

	/**
	 * Max URLs per sub-sitemap file.
	 */
	public static function max_urls_per_file(): int {
		$max = (int) apply_filters( 'seo_core_sitemap_max_urls', self::MAX_URLS );
		return max( 100, min( 50000, $max ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		// Never flush on plugins_loaded — $wp_rewrite may be null.
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::request_rewrite_flush();
		} else {
			update_option( 'seo_core_rewrite_needs_flush', '1', false );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		$health = dirname( __FILE__ ) . '/class-seo-core-sitemap-health.php';
		if ( is_readable( $health ) ) {
			require_once $health;
		}

		add_action( 'init', array( $this, 'register_rewrites' ), 20 );
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::schedule_deferred_rewrite_flush();
		}
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
		add_action( 'parse_request', array( $this, 'maybe_hijack_request' ), 1 );

		// Prefer Damavand sitemap over core wp-sitemap when we can emit.
		add_filter( 'wp_sitemaps_enabled', array( $this, 'disable_core_sitemaps' ) );

		// حتی وقتی ماژول robots در Passive است، خط Sitemap درست را برای همه سایت‌ها تزریق کن.
		add_filter( 'robots_txt', array( $this, 'ensure_sitemap_in_robots' ), 1000000, 2 );

		add_action( 'save_post', array( $this, 'invalidate_cache' ), 20 );
		add_action( 'deleted_post', array( $this, 'invalidate_cache' ), 20 );
		add_action( 'edited_term', array( $this, 'invalidate_cache' ), 20 );
		add_action( 'delete_term', array( $this, 'invalidate_cache' ), 20 );
		add_action( 'set_object_terms', array( $this, 'invalidate_cache' ), 20 );
		add_action( 'woocommerce_update_product', array( $this, 'invalidate_cache' ), 20 );

		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_core_sitemap', array( $this, 'ajax' ) );
		}
	}

	/**
	 * Turn off WP 5.5 core sitemaps when ours is live (avoids duplicate weak maps).
	 *
	 * @param bool $enabled Core flag.
	 */
	public function disable_core_sitemaps( $enabled ): bool {
		if ( $this->can_emit() ) {
			return false;
		}
		return (bool) $enabled;
	}

	/**
	 * آیا نوع محتوا در نقشه بیاید؟ (برای همه فروشگاه‌ها قابل تنظیم)
	 *
	 * @param string $type posts|pages|products|categories|product-cats|product-tags.
	 */
	public static function is_type_enabled( string $type ): bool {
		$map = array(
			'posts'         => 'seo_core_sitemap_include_posts',
			'pages'         => 'seo_core_sitemap_include_pages',
			'products'      => 'seo_core_sitemap_include_products',
			'categories'    => 'seo_core_sitemap_include_categories',
			'product-cats'  => 'seo_core_sitemap_include_product_cats',
			'product-tags'  => 'seo_core_sitemap_include_product_tags',
		);
		if ( ! isset( $map[ $type ] ) ) {
			return false;
		}
		$default = 'yes';
		if ( in_array( $type, array( 'product-cats', 'product-tags', 'products' ), true ) && ! post_type_exists( 'product' ) ) {
			return false;
		}
		if ( 'product-cats' === $type && ! taxonomy_exists( 'product_cat' ) ) {
			return false;
		}
		if ( 'product-tags' === $type && ! taxonomy_exists( 'product_tag' ) ) {
			return false;
		}
		return 'yes' === (string) get_option( $map[ $type ], $default );
	}

	/**
	 * انواع ساب‌مپ فعال برای این فروشگاه.
	 *
	 * @return string[]
	 */
	public function enabled_types(): array {
		$types = array();
		foreach ( array( 'posts', 'pages', 'categories', 'product-cats', 'product-tags', 'products' ) as $t ) {
			if ( self::is_type_enabled( $t ) ) {
				$types[] = $t;
			}
		}
		return $types;
	}

	/**
	 * اطمینان از وجود Sitemap: Damavand در robots.txt همه سایت‌ها
	 * (حتی اگر Rank Math هنوز خط مرده sitemap_index.xml بگذارد).
	 *
	 * @param string $output Robots body.
	 * @param bool   $public Blog public.
	 */
	public function ensure_sitemap_in_robots( string $output, $public ): string {
		if ( ! $this->can_emit() || ! $public ) {
			return $output;
		}
		if ( 'yes' !== (string) get_option( 'seo_core_sitemap_claim_robots', 'yes' ) ) {
			return $output;
		}

		$ours = $this->public_url( 'index' );

		// خط‌های ایندکس قدیمی رقیب را با Damavand عوض کن تا GSC به ۴۰۴ نرود.
		$output = preg_replace(
			'#^Sitemap:\s*\S*(?:sitemap_index\.xml|wp-sitemap\.xml)\s*$#mi',
			'Sitemap: ' . $ours,
			(string) $output
		);

		if ( false === stripos( (string) $output, $ours ) ) {
			$output = rtrim( (string) $output ) . "\nSitemap: " . $ours . "\n";
		}

		return (string) $output;
	}

	/**
	 * Register rewrite rules (paginated + legacy single-file).
	 */
	public function register_rewrites(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}
		// add_rewrite_rule() calls $wp_rewrite->add_rule() — must exist.
		global $wp_rewrite;
		if ( ! ( $wp_rewrite instanceof WP_Rewrite ) ) {
			if ( class_exists( 'SEO_Core_Installer' ) ) {
				SEO_Core_Installer::request_rewrite_flush();
			}
			return;
		}
		$base = self::BASE;
		// More specific first.
		add_rewrite_rule( '^' . $base . '\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule(
			'^' . $base . '-([a-z0-9\-]+)-([0-9]+)\.xml$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]-$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '-([a-z0-9\-]+)\.xml$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
		// Alias رایج برای همه سایت‌ها (وقتی ما مالک نقشه هستیم).
		if ( 'yes' === (string) get_option( 'seo_core_sitemap_alias_xml', 'yes' ) ) {
			add_rewrite_rule( '^sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		}
	}

	/**
	 * Force flush rewrite rules (deferred safely if too early).
	 *
	 * @param string $reason Context for logs.
	 */
	public function force_flush_rewrites( string $reason = '' ): void {
		$this->register_rewrites();
		$flushed = false;
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			$flushed = SEO_Core_Installer::safe_flush_rewrite_rules( false );
		} else {
			global $wp_rewrite;
			if ( $wp_rewrite instanceof WP_Rewrite ) {
				flush_rewrite_rules( false );
				$flushed = true;
			} else {
				update_option( 'seo_core_rewrite_needs_flush', '1', false );
			}
		}
		$this->log(
			'info',
			$flushed ? 'Rewrite نقشه سایت فلاش شد.' : 'فلاش rewrite نقشه سایت موکول به init شد.',
			array( 'reason' => $reason, 'flushed' => $flushed )
		);
	}

	/**
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Safety-net only: if rewrite missed, set query var from REQUEST_URI and log.
	 *
	 * @param WP $wp WP.
	 */
	public function maybe_hijack_request( $wp ): void {
		if ( ! $this->can_emit() || ! is_object( $wp ) ) {
			return;
		}
		$qv = self::QUERY_VAR;
		if ( ! empty( $wp->query_vars[ $qv ] ) ) {
			return;
		}
		$token = $this->detect_token_from_request();
		if ( '' === $token ) {
			return;
		}

		$wp->query_vars[ $qv ]   = $token;
		$wp->query_vars['error'] = '';
		unset( $wp->query_vars['pagename'], $wp->query_vars['name'], $wp->query_vars['page_id'], $wp->query_vars['p'] );

		$this->served_via_fallback = true;
		$hits = (int) get_option( 'seo_core_sitemap_fallback_hits', 0 ) + 1;
		update_option( 'seo_core_sitemap_fallback_hits', $hits, false );
		if ( class_exists( 'SEO_Core_Sitemap_Health' ) ) {
			SEO_Core_Sitemap_Health::log_fallback( $token );
		}
		$this->log(
			'warning',
			'فالبک REQUEST_URI نقشه سایت فعال شد (rewrite miss).',
			array(
				'token' => $token,
				'hits'  => $hits,
			)
		);
		// Self-heal: request a real flush on next admin/ensure pass.
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::request_rewrite_flush();
		}
	}

	/**
	 * Public URL for index / type / page.
	 *
	 * @param string $type index|posts|pages|products|categories.
	 * @param int    $page 1-based page.
	 */
	public function public_url( string $type = 'index', int $page = 1 ): string {
		$type = sanitize_key( $type );
		$page = max( 1, $page );
		if ( 'index' === $type || '' === $type ) {
			return home_url( '/' . self::BASE . '.xml' );
		}
		if ( $page <= 1 ) {
			return home_url( '/' . self::BASE . '-' . $type . '.xml' );
		}
		return home_url( '/' . self::BASE . '-' . $type . '-' . $page . '.xml' );
	}

	/**
	 * Render XML.
	 */
	public function maybe_render(): void {
		$token = get_query_var( self::QUERY_VAR );
		if ( '' === $token || false === $token ) {
			$token = $this->detect_token_from_request();
			if ( '' !== $token && false !== $token ) {
				$this->served_via_fallback = true;
				if ( class_exists( 'SEO_Core_Sitemap_Health' ) ) {
					SEO_Core_Sitemap_Health::log_fallback( (string) $token );
				}
			}
		}
		if ( '' === $token || false === $token ) {
			return;
		}
		if ( ! $this->can_emit() ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$parsed = $this->parse_token( (string) $token );
		if ( null === $parsed ) {
			status_header( 404 );
			nocache_headers();
			header( 'Content-Type: application/xml; charset=UTF-8' );
			echo '<?xml version="1.0" encoding="UTF-8"?><error>invalid</error>';
			exit;
		}

		$xml = $this->get_xml( $parsed['type'], $parsed['page'] );
		if ( null === $xml ) {
			status_header( 404 );
			nocache_headers();
			header( 'Content-Type: application/xml; charset=UTF-8' );
			echo '<?xml version="1.0" encoding="UTF-8"?><error>not found</error>';
			exit;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		header( 'X-Damavand-Sitemap: 1', true );
		header( 'X-Damavand-Sitemap-Via: ' . ( $this->served_via_fallback ? 'fallback' : 'rewrite' ), true );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
		exit;
	}

	/**
	 * @return string Token or empty.
	 */
	public function detect_token_from_request(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $uri ) {
			return '';
		}
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = rawurldecode( $path );
		$path = untrailingslashit( $path );

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && '' !== $home_path ) {
			$home_path = untrailingslashit( $home_path );
			if ( 0 === strpos( $path, $home_path ) ) {
				$path = substr( $path, strlen( $home_path ) );
				if ( '' === $path ) {
					$path = '/';
				}
			}
		}

		$base = preg_quote( self::BASE, '#' );
		if ( preg_match( '#^/' . $base . '\.xml$#i', $path ) ) {
			return 'index';
		}
		if ( 'yes' === (string) get_option( 'seo_core_sitemap_alias_xml', 'yes' ) && preg_match( '#^/sitemap\.xml$#i', $path ) ) {
			return 'index';
		}
		if ( preg_match( '#^/' . $base . '-([a-z0-9\-]+)-([0-9]+)\.xml$#i', $path, $m ) ) {
			return sanitize_key( $m[1] ) . '-' . absint( $m[2] );
		}
		if ( preg_match( '#^/' . $base . '-([a-z0-9\-]+)\.xml$#i', $path, $m ) ) {
			return sanitize_key( $m[1] );
		}
		return '';
	}

	/**
	 * Allowed submap type keys.
	 *
	 * @return string[]
	 */
	public static function allowed_types(): array {
		return array( 'posts', 'pages', 'products', 'categories', 'product-cats', 'product-tags' );
	}

	/**
	 * @param string $token Token.
	 * @return array{type:string,page:int}|null
	 */
	public function parse_token( string $token ): ?array {
		$token = strtolower( str_replace( '_', '-', $token ) );
		$token = preg_replace( '/[^a-z0-9\-]/', '', (string) $token );
		if ( 'index' === $token ) {
			return array( 'type' => 'index', 'page' => 1 );
		}
		$allowed = self::allowed_types();
		if ( in_array( $token, $allowed, true ) ) {
			return array( 'type' => $token, 'page' => 1 );
		}
		if ( preg_match( '/^(' . implode( '|', $allowed ) . ')-([0-9]+)$/', (string) $token, $m ) ) {
			return array(
				'type' => $m[1],
				'page' => max( 1, absint( $m[2] ) ),
			);
		}
		return null;
	}

	/**
	 * Build/cached XML for type+page.
	 *
	 * @param string $type Type.
	 * @param int    $page Page.
	 */
	public function get_xml( string $type, int $page = 1 ): ?string {
		$type = strtolower( str_replace( '_', '-', $type ) );
		$type = preg_replace( '/[^a-z0-9\-]/', '', (string) $type );
		$page = max( 1, $page );
		$allowed = array_merge( array( 'index' ), self::allowed_types() );
		if ( ! in_array( $type, $allowed, true ) ) {
			return null;
		}
		if ( 'index' !== $type && ! self::is_type_enabled( $type ) ) {
			return null;
		}
		if ( 'index' === $type ) {
			$page = 1;
		}

		$cache_key = 'xml_' . $type . ( 'index' === $type ? '' : '_' . $page );
		$cached    = $this->cache_get_layered( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		if ( 'index' === $type ) {
			$built = $this->build_index();
		} else {
			$built = $this->build_urlset_page( $type, $page );
		}
		if ( null === $built ) {
			return null;
		}

		$this->cache_set_layered( $cache_key, $built['xml'], self::CACHE_TTL );
		$this->merge_stats(
			$type,
			$page,
			array(
				'urls'        => (int) $built['urls'],
				'bytes'       => strlen( $built['xml'] ),
				'lastmod'     => (string) $built['lastmod'],
				'regenerated' => gmdate( 'c' ),
				'pages'       => (int) ( $built['pages'] ?? 1 ),
			)
		);
		$this->log(
			'info',
			sprintf( 'نقشه «%s» صفحه %d بازتولید شد.', $type, $page ),
			array( 'bytes' => strlen( $built['xml'] ), 'urls' => $built['urls'] )
		);

		return $built['xml'];
	}

	/**
	 * Layered cache: object cache then Transient.
	 *
	 * @param string $key Relative key.
	 * @return mixed
	 */
	private function cache_get_layered( string $key ) {
		$full = $this->cache_key( $key );
		$hit  = wp_cache_get( $full, 'damavand_sitemap' );
		if ( false !== $hit && null !== $hit ) {
			return $hit;
		}
		return $this->cache_get( $key );
	}

	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl TTL.
	 */
	private function cache_set_layered( string $key, $value, int $ttl ): void {
		$full = $this->cache_key( $key );
		wp_cache_set( $full, $value, 'damavand_sitemap', $ttl );
		$this->cache_set( $key, $value, $ttl );
		$meta = array(
			'generated' => time(),
			'ttl'       => $ttl,
		);
		wp_cache_set( $this->cache_key( $key . '_meta' ), $meta, 'damavand_sitemap', $ttl );
		$this->cache_set( $key . '_meta', $meta, $ttl );
	}

	/**
	 * Cache generation meta for health UI (real transient time, not "now").
	 *
	 * @param string $type index|posts|…
	 * @param int    $page Page.
	 * @return array{generated?:int,ttl?:int}
	 */
	public function get_cache_meta( string $type, int $page = 1 ): array {
		$type = sanitize_key( $type );
		$page = max( 1, $page );
		$key  = 'xml_' . $type . ( 'index' === $type ? '' : '_' . $page ) . '_meta';
		$meta = $this->cache_get_layered( $key );
		if ( is_array( $meta ) && ! empty( $meta['generated'] ) ) {
			return array(
				'generated' => (int) $meta['generated'],
				'ttl'       => (int) ( $meta['ttl'] ?? self::CACHE_TTL ),
			);
		}
		// Fallback: stats row regenerated ISO → unix.
		$stats = $this->get_stats();
		$row   = isset( $stats[ $type ] ) && is_array( $stats[ $type ] ) ? $stats[ $type ] : array();
		if ( ! empty( $row['regenerated'] ) ) {
			$ts = strtotime( (string) $row['regenerated'] );
			if ( $ts ) {
				return array(
					'generated' => (int) $ts,
					'ttl'       => self::CACHE_TTL,
				);
			}
		}
		return array();
	}

	/**
	 * Invalidate all known sitemap caches.
	 */
	public function invalidate_cache(): void {
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::should_skip_product_save_side_effects() ) {
			return;
		}
		$stats = $this->get_stats();
		$keys  = array( 'xml_index', 'xml_index_meta' );
		foreach ( self::allowed_types() as $type ) {
			$keys[] = 'idlist_' . $type;
			$pages  = isset( $stats[ $type ]['pages'] ) ? max( 1, (int) $stats[ $type ]['pages'] ) : 50;
			for ( $p = 1; $p <= $pages; $p++ ) {
				$keys[] = 'xml_' . $type . '_' . $p;
				$keys[] = 'xml_' . $type . '_' . $p . '_meta';
			}
			$keys[] = 'xml_' . $type;
			$keys[] = 'xml_' . $type . '_meta';
		}
		foreach ( array_unique( $keys ) as $key ) {
			$full = $this->cache_key( $key );
			wp_cache_delete( $full, 'damavand_sitemap' );
			$this->cache_delete( $key );
		}
		if ( class_exists( 'SEO_Core_Sitemap_Health' ) ) {
			SEO_Core_Sitemap_Health::bust_report_cache();
		}
		/**
		 * After sitemap cache bust.
		 *
		 * @param SEO_Core_Sitemap $sitemap Module.
		 */
		do_action( 'seo_core_sitemap_invalidated', $this );
	}

	/**
	 * Stats for admin debug UI.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_stats(): array {
		$raw = get_option( self::STATS_OPT, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param string               $type Type.
	 * @param int                  $page Page.
	 * @param array<string,mixed>  $row  Stats row.
	 */
	private function merge_stats( string $type, int $page, array $row ): void {
		$stats = $this->get_stats();
		if ( 'index' === $type ) {
			$stats['index'] = array_merge( $stats['index'] ?? array(), $row, array( 'page' => 1 ) );
		} else {
			if ( ! isset( $stats[ $type ] ) || ! is_array( $stats[ $type ] ) ) {
				$stats[ $type ] = array();
			}
			$stats[ $type ]['pages']       = max( (int) ( $stats[ $type ]['pages'] ?? 1 ), (int) ( $row['pages'] ?? $page ) );
			$stats[ $type ]['regenerated'] = $row['regenerated'] ?? gmdate( 'c' );
			if ( empty( $stats[ $type ]['lastmod'] ) || ( ! empty( $row['lastmod'] ) && strcmp( (string) $row['lastmod'], (string) $stats[ $type ]['lastmod'] ) > 0 ) ) {
				$stats[ $type ]['lastmod'] = $row['lastmod'];
			}
			$stats[ $type ]['urls_total'] = (int) ( $stats[ $type ]['urls_total'] ?? 0 );
			$files = isset( $stats[ $type ]['files'] ) && is_array( $stats[ $type ]['files'] ) ? $stats[ $type ]['files'] : array();
			$files[ (string) $page ] = array(
				'urls'    => (int) $row['urls'],
				'bytes'   => (int) $row['bytes'],
				'lastmod' => (string) $row['lastmod'],
			);
			$stats[ $type ]['files'] = $files;
			$total = 0;
			foreach ( $files as $f ) {
				$total += (int) ( $f['urls'] ?? 0 );
			}
			$stats[ $type ]['urls_total'] = $total;
		}
		update_option( self::STATS_OPT, $stats, false );
	}

	/**
	 * Sitemap index with real lastmod + paginated locs.
	 *
	 * @return array{xml:string,urls:int,lastmod:string,pages:int}
	 */
	private function build_index(): array {
		$types = $this->enabled_types();

		$max_last = '';
		$entries  = array();
		foreach ( $types as $type ) {
			$list  = $this->get_entry_list( $type );
			$total = count( $list );
			if ( $total < 1 ) {
				continue;
			}
			$per   = self::max_urls_per_file();
			$pages = max( 1, (int) ceil( $total / $per ) );
			for ( $p = 1; $p <= $pages; $p++ ) {
				$chunk   = array_slice( $list, ( $p - 1 ) * $per, $per );
				$lastmod = $this->max_lastmod( $chunk );
				if ( '' === $lastmod ) {
					$lastmod = gmdate( 'c' );
				}
				if ( '' === $max_last || strcmp( $lastmod, $max_last ) > 0 ) {
					$max_last = $lastmod;
				}
				$entries[] = array(
					'loc'     => $this->public_url( $type, $p ),
					'lastmod' => $lastmod,
					'type'    => $type,
					'page'    => $p,
					'urls'    => count( $chunk ),
					'pages'   => $pages,
				);
				$this->merge_stats(
					$type,
					$p,
					array(
						'urls'        => count( $chunk ),
						'bytes'       => 0,
						'lastmod'     => $lastmod,
						'regenerated' => gmdate( 'c' ),
						'pages'       => $pages,
					)
				);
			}
		}

		$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $entries as $row ) {
			$out .= "  <sitemap>\n";
			$out .= '    <loc>' . $this->xml_esc_url( $row['loc'] ) . "</loc>\n";
			$out .= '    <lastmod>' . esc_html( $row['lastmod'] ) . "</lastmod>\n";
			$out .= "  </sitemap>\n";
		}
		$out .= '</sitemapindex>';

		return array(
			'xml'     => $out,
			'urls'    => count( $entries ),
			'lastmod' => $max_last ? $max_last : gmdate( 'c' ),
			'pages'   => 1,
		);
	}

	/**
	 * One urlset page.
	 *
	 * @param string $type Type.
	 * @param int    $page Page.
	 * @return array{xml:string,urls:int,lastmod:string,pages:int}|null
	 */
	private function build_urlset_page( string $type, int $page ): ?array {
		$list  = $this->get_entry_list( $type );
		$per   = self::max_urls_per_file();
		$total = count( $list );
		$pages = max( 1, (int) ceil( max( 1, $total ) / $per ) );
		if ( $page > $pages && $total > 0 ) {
			return null;
		}
		$chunk = array_slice( $list, ( $page - 1 ) * $per, $per );
		$with_images = in_array( $type, array( 'products', 'posts', 'pages' ), true );

		$ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
		if ( $with_images ) {
			$ns .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
		}

		$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$out .= '<urlset ' . $ns . ">\n";
		foreach ( $chunk as $row ) {
			$out .= "  <url>\n";
			$out .= '    <loc>' . $this->xml_esc_url( $row['loc'] ) . "</loc>\n";
			if ( ! empty( $row['lastmod'] ) ) {
				$out .= '    <lastmod>' . esc_html( $row['lastmod'] ) . "</lastmod>\n";
			}
			if ( $with_images ) {
				$images = array();
				if ( ! empty( $row['images'] ) && is_array( $row['images'] ) ) {
					$images = $row['images'];
				} elseif ( ! empty( $row['image'] ) ) {
					$images[] = array(
						'loc'   => (string) $row['image'],
						'title' => (string) ( $row['image_title'] ?? '' ),
					);
				}
				foreach ( $images as $img ) {
					if ( empty( $img['loc'] ) ) {
						continue;
					}
					$out .= "    <image:image>\n";
					$out .= '      <image:loc>' . $this->xml_esc_url( (string) $img['loc'] ) . "</image:loc>\n";
					if ( ! empty( $img['title'] ) ) {
						$out .= '      <image:title>' . esc_html( (string) $img['title'] ) . "</image:title>\n";
					}
					$out .= "    </image:image>\n";
				}
			}
			$out .= "  </url>\n";
		}
		$out .= '</urlset>';

		return array(
			'xml'     => $out,
			'urls'    => count( $chunk ),
			'lastmod' => $this->max_lastmod( $chunk ),
			'pages'   => $pages,
		);
	}

	/**
	 * Cached list of sitemap entries for a type.
	 *
	 * @param string $type Type.
	 * @return array<int,array{loc:string,lastmod:string,image?:string,image_title?:string}>
	 */
	private function get_entry_list( string $type ): array {
		$ck = 'idlist_' . $type;
		$hit = $this->cache_get_layered( $ck );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		switch ( $type ) {
			case 'posts':
				$list = $this->collect_posts( 'post', $this->include_post_images() );
				break;
			case 'pages':
				$list = $this->collect_posts( 'page', $this->include_post_images() );
				$list = $this->ensure_home_url( $list );
				break;
			case 'products':
				$list = $this->collect_posts( 'product', true );
				break;
			case 'categories':
				$list = $this->collect_terms( array( 'category' ) );
				break;
			case 'product-cats':
				$list = $this->collect_terms( array( 'product_cat' ) );
				break;
			case 'product-tags':
				$list = $this->collect_terms( array( 'product_tag' ) );
				break;
			default:
				$list = array();
		}
		$this->cache_set_layered( $ck, $list, self::CACHE_TTL );
		return $list;
	}

	/**
	 * @param array<int,array{lastmod?:string}> $rows Rows.
	 */
	private function max_lastmod( array $rows ): string {
		$max = '';
		foreach ( $rows as $row ) {
			$lm = isset( $row['lastmod'] ) ? (string) $row['lastmod'] : '';
			if ( '' !== $lm && ( '' === $max || strcmp( $lm, $max ) > 0 ) ) {
				$max = $lm;
			}
		}
		return $max;
	}

	/**
	 * Escape URL for XML (keeps percent-encoding intact for Persian slugs).
	 *
	 * @param string $url URL.
	 */
	private function xml_esc_url( string $url ): string {
		$url = esc_url_raw( $url );
		return htmlspecialchars( $url, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Collect publishable posts in chunks (no hard 5k ceiling).
	 *
	 * @param string $post_type Post type.
	 * @param bool   $with_image Include featured image.
	 * @return array<int,array{loc:string,lastmod:string,image?:string,image_title?:string}>
	 */
	private function collect_posts( string $post_type, bool $with_image = false ): array {
		if ( ! post_type_exists( $post_type ) ) {
			return array();
		}

		$exclude = array();
		if ( 'product' === $post_type && class_exists( 'Shojaei_SEO_Helpers' ) ) {
			$exclude = Shojaei_SEO_Helpers::get_410_excluded_ids();
		}
		$exclude = array_values( array_unique( array_filter( array_map( 'absint', $exclude ) ) ) );

		$out      = array();
		$paged    = 1;
		$batch    = 200;
		$safety   = 0;
		$max_loop = 500; // 200*500 = 100k IDs max guard.

		while ( $safety < $max_loop ) {
			++$safety;
			$args = array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $batch,
				'paged'                  => $paged,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'post__not_in'           => $exclude,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_shojaei_seo_sitemap_exclude',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_shojaei_seo_sitemap_exclude',
						'value'   => 'yes',
						'compare' => '!=',
					),
				),
			);
			$args = apply_filters( 'seo_core_sitemap_post_args', $args, $post_type );
			$ids  = get_posts( $args );
			if ( empty( $ids ) ) {
				break;
			}
			foreach ( $ids as $id ) {
				$id = absint( $id );
				if ( $id < 1 || $this->is_noindex_post( $id ) ) {
					continue;
				}
				$post_obj = get_post( $id );
				if ( ! $post_obj instanceof WP_Post ) {
					continue;
				}
				if ( '' !== (string) $post_obj->post_password ) {
					continue;
				}
				if ( 'product' === $post_type && $this->is_hidden_catalog_product( $id ) ) {
					continue;
				}
				$url = get_permalink( $id );
				if ( ! $url ) {
					continue;
				}
				$mod = get_post_modified_time( 'c', true, $id );
				$row = array(
					'loc'     => $url,
					'lastmod' => is_string( $mod ) ? $mod : '',
				);
				if ( $with_image ) {
					$images = $this->collect_post_images( $id, 'product' === $post_type );
					if ( ! empty( $images ) ) {
						$row['images']      = $images;
						$row['image']       = (string) $images[0]['loc'];
						$row['image_title'] = (string) ( $images[0]['title'] ?? '' );
					}
				}
				$out[] = $row;
			}
			if ( count( $ids ) < $batch ) {
				break;
			}
			++$paged;
		}

		return $out;
	}

	/**
	 * Whether featured images should be embedded for posts/pages.
	 */
	private function include_post_images(): bool {
		return 'yes' === (string) get_option( 'seo_core_sitemap_post_images', 'yes' );
	}

	/**
	 * WooCommerce catalog visibility = hidden.
	 *
	 * @param int $post_id Product ID.
	 */
	private function is_hidden_catalog_product( int $post_id ): bool {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return false;
		}
		return 'hidden' === $product->get_catalog_visibility();
	}

	/**
	 * Featured image (+ product gallery when enabled).
	 *
	 * @param int  $post_id   Post ID.
	 * @param bool $with_gallery Include Woo gallery for products.
	 * @return array<int,array{loc:string,title:string}>
	 */
	private function collect_post_images( int $post_id, bool $with_gallery = false ): array {
		$title  = wp_strip_all_tags( (string) get_the_title( $post_id ) );
		$images = array();
		$seen   = array();

		$add = static function ( $att_id ) use ( &$images, &$seen, $title ) {
			$att_id = absint( $att_id );
			if ( $att_id < 1 || isset( $seen[ $att_id ] ) ) {
				return;
			}
			$url = wp_get_attachment_image_url( $att_id, 'full' );
			if ( ! $url ) {
				return;
			}
			$alt = trim( (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true ) );
			$seen[ $att_id ] = true;
			$images[]        = array(
				'loc'   => $url,
				'title' => '' !== $alt ? $alt : $title,
			);
		};

		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			$add( $thumb );
		}

		if ( $with_gallery && 'yes' === (string) get_option( 'seo_core_sitemap_product_gallery', 'yes' ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				foreach ( (array) $product->get_gallery_image_ids() as $gid ) {
					$add( $gid );
					if ( count( $images ) >= 8 ) {
						break;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * اگر صفحهٔ اصلی جدا از برگه‌ها باشد، به لیست برگه‌ها اضافه کن.
	 *
	 * @param array<int,array{loc:string,lastmod:string}> $list List.
	 * @return array<int,array{loc:string,lastmod:string}>
	 */
	private function ensure_home_url( array $list ): array {
		$home = home_url( '/' );
		foreach ( $list as $row ) {
			if ( ! empty( $row['loc'] ) && untrailingslashit( (string) $row['loc'] ) === untrailingslashit( $home ) ) {
				return $list;
			}
		}
		// وقتی نوشته‌ها روی خانه هستند، URL خانه را هم ایندکس کن.
		if ( 'posts' === get_option( 'show_on_front' ) ) {
			$home_mod = $this->home_lastmod();
			array_unshift(
				$list,
				array(
					'loc'     => $home,
					'lastmod' => $home_mod,
				)
			);
		}
		return $list;
	}

	/**
	 * Latest published post modified time for blog-on-front home URL.
	 */
	private function home_lastmod(): string {
		$posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( empty( $posts ) ) {
			return '';
		}
		$mod = get_post_modified_time( 'c', true, (int) $posts[0] );
		return is_string( $mod ) ? $mod : '';
	}

	/**
	 * Terms list (chunk-ready) with lastmod from newest assigned post.
	 *
	 * @param string[] $taxonomies Taxonomies.
	 * @return array<int,array{loc:string,lastmod:string}>
	 */
	private function collect_terms( array $taxonomies ): array {
		$out = array();
		foreach ( $taxonomies as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$offset = 0;
			$batch  = 200;
			while ( true ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $tax,
						'hide_empty' => true,
						'number'     => $batch,
						'offset'     => $offset,
					)
				);
				if ( is_wp_error( $terms ) || empty( $terms ) ) {
					break;
				}
				foreach ( $terms as $term ) {
					if ( $this->is_noindex_term( (int) $term->term_id, $tax ) ) {
						continue;
					}
					$link = get_term_link( $term );
					if ( is_wp_error( $link ) ) {
						continue;
					}
					$out[] = array(
						'loc'     => $link,
						'lastmod' => $this->term_lastmod( (int) $term->term_id, $tax ),
					);
				}
				if ( count( $terms ) < $batch ) {
					break;
				}
				$offset += $batch;
			}
		}
		return $out;
	}

	/**
	 * @param int    $term_id Term.
	 * @param string $tax     Taxonomy.
	 */
	private function term_lastmod( int $term_id, string $tax ): string {
		$post_type = ( 'product_cat' === $tax || 'product_tag' === $tax ) ? 'product' : 'any';
		$offset    = 0;
		$batch     = 10;

		while ( $offset < 100 ) {
			$posts = get_posts(
				array(
					'post_type'              => $post_type,
					'posts_per_page'         => $batch,
					'offset'                 => $offset,
					'post_status'            => 'publish',
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => $tax,
							'field'    => 'term_id',
							'terms'    => array( $term_id ),
						),
					),
				)
			);
			if ( empty( $posts ) ) {
				return '';
			}
			foreach ( $posts as $pid ) {
				$pid = absint( $pid );
				if ( $pid < 1 || $this->is_noindex_post( $pid ) ) {
					continue;
				}
				$post_obj = get_post( $pid );
				if ( $post_obj instanceof WP_Post && '' !== (string) $post_obj->post_password ) {
					continue;
				}
				if ( 'product' === $post_type && $this->is_hidden_catalog_product( $pid ) ) {
					continue;
				}
				$mod = get_post_modified_time( 'c', true, $pid );
				return is_string( $mod ) ? $mod : '';
			}
			if ( count( $posts ) < $batch ) {
				break;
			}
			$offset += $batch;
		}
		return '';
	}

	/**
	 * @param int    $term_id Term.
	 * @param string $tax     Taxonomy.
	 */
	private function is_noindex_term( int $term_id, string $tax ): bool {
		$rm = get_term_meta( $term_id, 'rank_math_robots', true );
		if ( is_array( $rm ) && in_array( 'noindex', $rm, true ) ) {
			return true;
		}
		$yoast_key = 'category' === $tax ? 'wpseo_noindex_cat' : ( 'product_cat' === $tax ? 'wpseo_noindex' : '' );
		if ( $yoast_key && 'noindex' === (string) get_term_meta( $term_id, $yoast_key, true ) ) {
			return true;
		}
		if ( 'yes' === (string) get_term_meta( $term_id, '_damavand_seo_noindex', true ) ) {
			return true;
		}
		return (bool) apply_filters( 'seo_core_sitemap_is_noindex_term', false, $term_id, $tax );
	}

	/**
	 * @param int $post_id Post ID.
	 */
	private function is_noindex_post( int $post_id ): bool {
		if ( 'yes' === get_post_meta( $post_id, '_shojaei_seo_noindex', true ) ) {
			return true;
		}
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$robots = get_post_meta( $post_id, '_damavand_seo_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				return true;
			}
		}
		$rm = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rm ) && in_array( 'noindex', $rm, true ) ) {
			return true;
		}
		$yoast = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === (string) $yoast ) {
			return true;
		}
		return (bool) apply_filters( 'seo_core_sitemap_is_noindex', false, $post_id );
	}

	/**
	 * AJAX admin actions.
	 */
	public function ajax(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['sitemap_action'] ?? '' ) );
		if ( 'flush_cache' === $action ) {
			$this->invalidate_cache();
			$this->force_flush_rewrites( 'ajax_flush' );
			wp_send_json_success(
				array(
					'message' => __( 'کش پاک و rewrite فلاش شد. یک‌بار XML را تازه باز کنید.', 'shojaei-seo-for-woo' ),
				)
			);
		}
		if ( 'rebuild_index' === $action ) {
			$this->invalidate_cache();
			$xml = $this->get_xml( 'index', 1 );
			wp_send_json_success(
				array(
					'message' => __( 'ایندکس بازسازی شد.', 'shojaei-seo-for-woo' ),
					'bytes'   => is_string( $xml ) ? strlen( $xml ) : 0,
					'stats'   => $this->get_stats(),
				)
			);
		}
		if ( 'health_run' === $action ) {
			if ( ! class_exists( 'SEO_Core_Sitemap_Health' ) ) {
				wp_send_json_error( array( 'message' => __( 'کلاس سلامت نقشه سایت یافت نشد.', 'shojaei-seo-for-woo' ) ) );
			}
			SEO_Core_Sitemap_Health::bust_report_cache();
			$report = SEO_Core_Sitemap_Health::get_report( $this, true );
			wp_send_json_success(
				array(
					'message' => __( 'تست کامل سلامت دوباره اجرا شد.', 'shojaei-seo-for-woo' ),
					'report'  => $report,
				)
			);
		}

		if ( 'save_settings' === $action ) {
			$keys = array(
				'seo_core_sitemap_include_posts',
				'seo_core_sitemap_include_pages',
				'seo_core_sitemap_include_products',
				'seo_core_sitemap_include_categories',
				'seo_core_sitemap_include_product_cats',
				'seo_core_sitemap_include_product_tags',
				'seo_core_sitemap_product_gallery',
				'seo_core_sitemap_alias_xml',
				'seo_core_sitemap_claim_robots',
			);
			foreach ( $keys as $key ) {
				update_option( $key, ! empty( $_POST[ $key ] ) ? 'yes' : 'no', false );
			}
			$this->invalidate_cache();
			$this->force_flush_rewrites( 'ajax_settings' );
			wp_send_json_success(
				array(
					'message' => __( 'تنظیمات نقشه ذخیره شد؛ کش پاک و rewrite فلاش شد.', 'shojaei-seo-for-woo' ),
					'types'   => $this->enabled_types(),
					'index'   => $this->public_url( 'index' ),
					'alias'   => home_url( '/sitemap.xml' ),
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
	}
}
