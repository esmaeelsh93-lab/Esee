<?php
/**
 * Redirect health audits — broken destinations + redirect chains.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Redirect_Audit
 */
class Shojaei_SEO_Redirect_Audit {

	public const OPTION_BROKEN = 'shojaei_seo_broken_redirects';

	public const OPTION_CHAINS = 'shojaei_seo_redirect_chains';

	public const OPTION_LOOPS = 'shojaei_seo_redirect_loops';

	public const MAX_ISSUES = 300;

	public const MAX_CHAIN_HOPS = 6;

	/**
	 * Human labels for issue codes.
	 *
	 * @return array<string,string>
	 */
	public static function broken_labels(): array {
		return array(
			'empty_target'         => __( 'مقصد خالی است', 'shojaei-seo-for-woo' ),
			'invalid_target'       => __( 'آدرس مقصد نامعتبر است', 'shojaei-seo-for-woo' ),
			'not_published'        => __( 'مقصد منتشر نیست (پیش‌نویس/خصوصی/…)', 'shojaei-seo-for-woo' ),
			'trashed'              => __( 'مقصد در سطل زباله است', 'shojaei-seo-for-woo' ),
			'missing_post'         => __( 'صفحه/محصول مقصد پیدا نشد', 'shojaei-seo-for-woo' ),
			'target_410'           => __( 'مقصد با ۴۱۰ Gone علامت خورده', 'shojaei-seo-for-woo' ),
			'unresolved_internal'  => __( 'آدرس داخلی قابل شناسایی نیست (احتمال ۴۰۴)', 'shojaei-seo-for-woo' ),
			'source_missing'       => __( 'محصول مبدأ وجود ندارد', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * Last broken-scan report.
	 *
	 * @return array{scanned_at:string,total_checked:int,broken:int,issues:array<int,array<string,mixed>>}
	 */
	public static function get_broken_report(): array {
		$raw = get_option( self::OPTION_BROKEN, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array(
			'scanned_at'    => (string) ( $raw['scanned_at'] ?? '' ),
			'total_checked' => (int) ( $raw['total_checked'] ?? 0 ),
			'broken'        => (int) ( $raw['broken'] ?? 0 ),
			'issues'        => is_array( $raw['issues'] ?? null ) ? $raw['issues'] : array(),
		);
	}

	/**
	 * Scan active OOS + slug redirects for broken targets.
	 *
	 * @return array{scanned_at:string,total_checked:int,broken:int,issues:array<int,array<string,mixed>>}
	 */
	public static function scan_broken(): array {
		$issues  = array();
		$checked = 0;

		foreach ( self::collect_active_redirects() as $row ) {
			++$checked;
			$hit = self::inspect_redirect( $row );
			if ( null === $hit ) {
				continue;
			}
			$issues[] = $hit;
			if ( count( $issues ) >= self::MAX_ISSUES ) {
				break;
			}
		}

		$report = array(
			'scanned_at'    => current_time( 'mysql' ),
			'total_checked' => $checked,
			'broken'        => count( $issues ),
			'issues'        => $issues,
		);

		update_option( self::OPTION_BROKEN, $report, false );
		return $report;
	}

	/**
	 * Collect active redirect rows (OOS 301/302 + slug).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function collect_active_redirects(): array {
		global $wpdb;
		$out = array();

		$table = Shojaei_SEO_Helpers::oos_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$oos = $wpdb->get_results(
			"SELECT id, product_id, target_url, redirect_type
			FROM {$table}
			WHERE status = 'redirected' AND redirect_type IN ('301','302') AND target_url != ''"
		);
		if ( is_array( $oos ) ) {
			foreach ( $oos as $r ) {
				$pid    = (int) $r->product_id;
				$source = $pid ? (string) get_permalink( $pid ) : '';
				$out[]  = array(
					'kind'          => 'oos',
					'id'            => (int) $r->id,
					'product_id'    => $pid,
					'source_url'    => $source,
					'target_url'    => (string) $r->target_url,
					'redirect_type' => (string) $r->redirect_type,
				);
			}
		}

		if ( class_exists( 'Shojaei_SEO_Slug' ) ) {
			$slug_table = Shojaei_SEO_Slug::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$slug = $wpdb->get_results(
				"SELECT id, product_id, old_url, new_url, redirect_type, old_path
				FROM {$slug_table}
				WHERE is_active = 1 AND new_url != ''"
			);
			if ( is_array( $slug ) ) {
				foreach ( $slug as $r ) {
					$out[] = array(
						'kind'          => 'slug',
						'id'            => (int) $r->id,
						'product_id'    => (int) $r->product_id,
						'source_url'    => (string) $r->old_url,
						'target_url'    => (string) $r->new_url,
						'redirect_type' => (string) $r->redirect_type,
						'old_path'      => (string) $r->old_path,
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Inspect one redirect; return issue array or null if healthy.
	 *
	 * @param array<string,mixed> $row Redirect row.
	 * @return array<string,mixed>|null
	 */
	public static function inspect_redirect( array $row ): ?array {
		$kind   = (string) ( $row['kind'] ?? '' );
		$pid    = (int) ( $row['product_id'] ?? 0 );
		$target = trim( (string) ( $row['target_url'] ?? '' ) );

		// Source product gone (OOS rows keyed by product_id).
		if ( 'oos' === $kind && $pid > 0 && ! get_post( $pid ) ) {
			return self::issue_row( $row, 'source_missing', 0, __( 'خطا', 'shojaei-seo-for-woo' ) );
		}

		if ( '' === $target ) {
			return self::issue_row( $row, 'empty_target', 0, __( 'خطا', 'shojaei-seo-for-woo' ) );
		}

		$class = self::classify_target( $target );
		if ( empty( $class['broken'] ) ) {
			return null;
		}

		return self::issue_row(
			$row,
			(string) $class['code'],
			(int) ( $class['post_id'] ?? 0 ),
			(string) ( $class['severity'] ?? 'error' )
		);
	}

	/**
	 * Classify a destination URL.
	 *
	 * @param string $url Target URL.
	 * @return array{broken:bool,code:string,post_id:int,severity:string}
	 */
	public static function classify_target( string $url ): array {
		$url = trim( $url );
		$ok  = array(
			'broken'   => false,
			'code'     => 'ok',
			'post_id'  => 0,
			'severity' => 'ok',
		);

		if ( '' === $url ) {
			return array(
				'broken'   => true,
				'code'     => 'empty_target',
				'post_id'  => 0,
				'severity' => 'error',
			);
		}

		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) && empty( $parts['path'] ) ) {
			return array(
				'broken'   => true,
				'code'     => 'invalid_target',
				'post_id'  => 0,
				'severity' => 'error',
			);
		}

		$post_id = self::resolve_post_id( $url );
		if ( $post_id > 0 ) {
			$status = get_post_status( $post_id );
			if ( false === $status || 'trash' === $status ) {
				return array(
					'broken'   => true,
					'code'     => 'trash' === $status ? 'trashed' : 'missing_post',
					'post_id'  => $post_id,
					'severity' => 'error',
				);
			}
			if ( 'publish' !== $status ) {
				return array(
					'broken'   => true,
					'code'     => 'not_published',
					'post_id'  => $post_id,
					'severity' => 'error',
				);
			}
			if ( 'product' === get_post_type( $post_id ) && class_exists( 'Shojaei_SEO_Slug' ) && Shojaei_SEO_Slug::is_410_product( $post_id ) ) {
				return array(
					'broken'   => true,
					'code'     => 'target_410',
					'post_id'  => $post_id,
					'severity' => 'error',
				);
			}
			return $ok;
		}

		// Known good non-post destinations.
		if ( self::is_known_safe_destination( $url ) ) {
			return $ok;
		}

		if ( ! self::is_same_host( $url ) ) {
			// External: do not mark broken without HTTP probe.
			return $ok;
		}

		return array(
			'broken'   => true,
			'code'     => 'unresolved_internal',
			'post_id'  => 0,
			'severity' => 'warning',
		);
	}

	/**
	 * Resolve URL to post/product ID with slash variants.
	 *
	 * @param string $url URL.
	 */
	public static function resolve_post_id( string $url ): int {
		$candidates = array_unique(
			array(
				$url,
				trailingslashit( $url ),
				untrailingslashit( $url ),
			)
		);
		foreach ( $candidates as $candidate ) {
			$id = (int) url_to_postid( $candidate );
			if ( $id > 0 ) {
				return $id;
			}
		}

		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$path = trim( $path, '/' );
		if ( '' === $path ) {
			return 0;
		}

		// Try product by path (last segment).
		$slug = basename( $path );
		if ( $slug ) {
			$posts = get_posts(
				array(
					'name'           => $slug,
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'trash' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( ! empty( $posts[0] ) ) {
				return (int) $posts[0];
			}
		}

		return 0;
	}

	/**
	 * Shop / product category / home — valid without a single post.
	 *
	 * @param string $url URL.
	 */
	public static function is_known_safe_destination( string $url ): bool {
		$norm = class_exists( 'Shojaei_SEO_Redirect_Engine' )
			? Shojaei_SEO_Redirect_Engine::normalize_url( $url )
			: untrailingslashit( strtolower( $url ) );

		$home = class_exists( 'Shojaei_SEO_Redirect_Engine' )
			? Shojaei_SEO_Redirect_Engine::normalize_url( home_url( '/' ) )
			: untrailingslashit( strtolower( home_url( '/' ) ) );

		if ( $norm && $norm === $home ) {
			return true;
		}

		if ( function_exists( 'wc_get_page_id' ) ) {
			$shop_id = (int) wc_get_page_id( 'shop' );
			if ( $shop_id > 0 ) {
				$shop = get_permalink( $shop_id );
				if ( $shop && class_exists( 'Shojaei_SEO_Redirect_Engine' )
					&& Shojaei_SEO_Redirect_Engine::normalize_url( $shop ) === $norm ) {
					return true;
				}
			}
		}

		$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$path = trim( $path, '/' );
		if ( '' === $path ) {
			return true;
		}

		// product-category/... or product-tag/...
		$segments = explode( '/', $path );
		$base     = $segments[0] ?? '';
		$cat_base = 'product-category';
		$tag_base = 'product-tag';
		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			$struct   = wc_get_permalink_structure();
			$cat_base = trim( (string) ( $struct['category_rewrite_slug'] ?? 'product-category' ), '/' );
			$tag_base = trim( (string) ( $struct['tag_rewrite_slug'] ?? 'product-tag' ), '/' );
		}

		if ( $base === $cat_base || $base === $tag_base ) {
			$taxonomy = ( $base === $tag_base ) ? 'product_tag' : 'product_cat';
			$slug     = end( $segments );
			if ( $slug && $slug !== $base ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				return ( $term && ! is_wp_error( $term ) );
			}
		}

		return false;
	}

	/**
	 * Same host as site?
	 *
	 * @param string $url URL.
	 */
	public static function is_same_host( string $url ): bool {
		$host = strtolower( (string) ( wp_parse_url( $url, PHP_URL_HOST ) ?? '' ) );
		$site = strtolower( (string) ( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' ) );
		return $host && $site && $host === $site;
	}

	/**
	 * Disable a broken redirect (OOS undo / slug deactivate).
	 *
	 * @param string $kind oos|slug.
	 * @param int    $id   Tracker id (oos) or redirect id (slug). For oos, product_id is preferred via $product_id.
	 * @param int    $product_id Product id for OOS.
	 * @return true|WP_Error
	 */
	public static function disable_redirect( string $kind, int $id, int $product_id = 0 ) {
		if ( 'oos' === $kind ) {
			if ( $product_id < 1 && $id > 0 ) {
				global $wpdb;
				$table      = Shojaei_SEO_Helpers::oos_table();
				$product_id = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT product_id FROM {$table} WHERE id = %d", $id )
				);
			}
			if ( $product_id < 1 ) {
				return new WP_Error( 'missing', __( 'محصول مبدأ پیدا نشد.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! class_exists( 'Shojaei_SEO_OOS_Manager' ) ) {
				return new WP_Error( 'missing', __( 'ماژول موجودی در دسترس نیست.', 'shojaei-seo-for-woo' ) );
			}
			$manager = new Shojaei_SEO_OOS_Manager( false );
			if ( ! $manager->undo_redirect( $product_id, 0 ) ) {
				return new WP_Error( 'undo_failed', __( 'لغو ریدایرکت ممکن نیست.', 'shojaei-seo-for-woo' ) );
			}
			self::forget_issue( 'oos', $id, $product_id );
			return true;
		}

		if ( 'slug' === $kind ) {
			if ( ! class_exists( 'Shojaei_SEO_Slug' ) ) {
				return new WP_Error( 'missing', __( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! Shojaei_SEO_Slug::set_redirect_active( $id, 0 ) ) {
				return new WP_Error( 'toggle_failed', __( 'غیرفعال‌سازی ناموفق بود.', 'shojaei-seo-for-woo' ) );
			}
			self::forget_issue( 'slug', $id, 0 );
			return true;
		}

		return new WP_Error( 'bad_kind', __( 'نوع ریدایرکت نامعتبر است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Last chain-scan report.
	 *
	 * @return array{scanned_at:string,total_checked:int,chains:int,issues:array<int,array<string,mixed>>}
	 */
	public static function get_chain_report(): array {
		$raw = get_option( self::OPTION_CHAINS, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array(
			'scanned_at'    => (string) ( $raw['scanned_at'] ?? '' ),
			'total_checked' => (int) ( $raw['total_checked'] ?? 0 ),
			'chains'        => (int) ( $raw['chains'] ?? 0 ),
			'issues'        => is_array( $raw['issues'] ?? null ) ? $raw['issues'] : array(),
		);
	}

	/**
	 * Scan for redirect chains (A→B→C…). Loops are handled by scan_loops().
	 *
	 * @return array{scanned_at:string,total_checked:int,chains:int,issues:array<int,array<string,mixed>>}
	 */
	public static function scan_chains(): array {
		$rows     = self::collect_active_redirects();
		$by_from  = array();
		$map      = array();
		$checked  = 0;

		foreach ( $rows as $row ) {
			++$checked;
			$from = class_exists( 'Shojaei_SEO_Redirect_Engine' )
				? Shojaei_SEO_Redirect_Engine::normalize_url( (string) ( $row['source_url'] ?? '' ) )
				: '';
			$to   = class_exists( 'Shojaei_SEO_Redirect_Engine' )
				? Shojaei_SEO_Redirect_Engine::normalize_url( (string) ( $row['target_url'] ?? '' ) )
				: '';
			if ( ! $from || ! $to || $from === $to ) {
				continue;
			}
			// Prefer first mapping if duplicate sources.
			if ( ! isset( $map[ $from ] ) ) {
				$map[ $from ]     = $to;
				$by_from[ $from ] = $row;
			}
		}

		$issues = array();
		foreach ( $by_from as $from => $row ) {
			$current   = $map[ $from ];
			$hops      = 0;
			$seen      = array( $from => true );
			$path_norm = array( $from, $current );
			$final_raw = (string) ( $row['target_url'] ?? '' );
			$is_loop   = false;

			while ( isset( $map[ $current ] ) && $hops < self::MAX_CHAIN_HOPS ) {
				$next = $map[ $current ];
				++$hops;
				if ( isset( $seen[ $next ] ) || $next === $from ) {
					$is_loop = true;
					break;
				}
				$seen[ $current ] = true;
				$path_norm[]      = $next;
				if ( isset( $by_from[ $current ] ) ) {
					$final_raw = (string) ( $by_from[ $current ]['target_url'] ?? $final_raw );
				}
				$current = $next;
			}

			// Skip pure loops here (next milestone). Chain = target also redirects (≥1 extra hop).
			if ( $is_loop || $hops < 1 ) {
				continue;
			}

			$path_display = array();
			foreach ( $path_norm as $n ) {
				$path_display[] = self::path_from_norm( $n );
			}

			$issues[] = array(
				'kind'          => (string) ( $row['kind'] ?? '' ),
				'id'            => (int) ( $row['id'] ?? 0 ),
				'product_id'    => (int) ( $row['product_id'] ?? 0 ),
				'source_url'    => (string) ( $row['source_url'] ?? '' ),
				'target_url'    => (string) ( $row['target_url'] ?? '' ),
				'final_url'     => $final_raw,
				'redirect_type' => (string) ( $row['redirect_type'] ?? '' ),
				'old_path'      => (string) ( $row['old_path'] ?? '' ),
				'hops'          => $hops + 1, // source→… hops as user-facing length.
				'path'          => $path_display,
				'label'         => sprintf(
					/* translators: %d: hop count */
					__( 'زنجیره ریدایرکت (%d پرش)', 'shojaei-seo-for-woo' ),
					$hops + 1
				),
				'severity'      => $hops >= 3 ? 'error' : 'warning',
			);

			if ( count( $issues ) >= self::MAX_ISSUES ) {
				break;
			}
		}

		$report = array(
			'scanned_at'    => current_time( 'mysql' ),
			'total_checked' => $checked,
			'chains'        => count( $issues ),
			'issues'        => $issues,
		);
		update_option( self::OPTION_CHAINS, $report, false );
		return $report;
	}

	/**
	 * Last loop-scan report.
	 *
	 * @return array{scanned_at:string,total_checked:int,loops:int,issues:array<int,array<string,mixed>>}
	 */
	public static function get_loop_report(): array {
		$raw = get_option( self::OPTION_LOOPS, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array(
			'scanned_at'    => (string) ( $raw['scanned_at'] ?? '' ),
			'total_checked' => (int) ( $raw['total_checked'] ?? 0 ),
			'loops'         => (int) ( $raw['loops'] ?? 0 ),
			'issues'        => is_array( $raw['issues'] ?? null ) ? $raw['issues'] : array(),
		);
	}

	/**
	 * Scan for redirect loops (A→B→A) and self-redirects (A→A).
	 *
	 * @return array{scanned_at:string,total_checked:int,loops:int,issues:array<int,array<string,mixed>>}
	 */
	public static function scan_loops(): array {
		$rows    = self::collect_active_redirects();
		$by_from = array();
		$map     = array();
		$checked = 0;
		$issues  = array();

		foreach ( $rows as $row ) {
			++$checked;
			$from = class_exists( 'Shojaei_SEO_Redirect_Engine' )
				? Shojaei_SEO_Redirect_Engine::normalize_url( (string) ( $row['source_url'] ?? '' ) )
				: '';
			$to   = class_exists( 'Shojaei_SEO_Redirect_Engine' )
				? Shojaei_SEO_Redirect_Engine::normalize_url( (string) ( $row['target_url'] ?? '' ) )
				: '';
			if ( ! $from || ! $to ) {
				continue;
			}

			// Self-redirect: A → A.
			if ( $from === $to ) {
				$issues[] = array(
					'kind'          => (string) ( $row['kind'] ?? '' ),
					'id'            => (int) ( $row['id'] ?? 0 ),
					'product_id'    => (int) ( $row['product_id'] ?? 0 ),
					'source_url'    => (string) ( $row['source_url'] ?? '' ),
					'target_url'    => (string) ( $row['target_url'] ?? '' ),
					'redirect_type' => (string) ( $row['redirect_type'] ?? '' ),
					'old_path'      => (string) ( $row['old_path'] ?? '' ),
					'hops'          => 1,
					'path'          => array( self::path_from_norm( $from ), self::path_from_norm( $to ) ),
					'code'          => 'self_redirect',
					'label'         => __( 'ریدایرکت به خودش (A → A)', 'shojaei-seo-for-woo' ),
					'severity'      => 'error',
				);
				continue;
			}

			if ( ! isset( $map[ $from ] ) ) {
				$map[ $from ]     = $to;
				$by_from[ $from ] = $row;
			}
		}

		$reported = array();

		foreach ( $by_from as $from => $row ) {
			$current   = $map[ $from ];
			$hops      = 0;
			$seen      = array( $from => true );
			$path_norm = array( $from, $current );
			$is_loop   = false;

			while ( isset( $map[ $current ] ) && $hops < self::MAX_CHAIN_HOPS ) {
				$next = $map[ $current ];
				++$hops;
				$path_norm[] = $next;
				if ( isset( $seen[ $next ] ) || $next === $from ) {
					$is_loop = true;
					break;
				}
				$seen[ $current ] = true;
				$current          = $next;
			}

			if ( ! $is_loop ) {
				continue;
			}

			// One report per cycle (dedupe shared nodes).
			$cycle_key_parts = $path_norm;
			sort( $cycle_key_parts );
			$cycle_key = implode( '|', array_unique( $cycle_key_parts ) );
			if ( isset( $reported[ $cycle_key ] ) ) {
				continue;
			}
			$reported[ $cycle_key ] = true;

			$path_display = array();
			foreach ( $path_norm as $n ) {
				$path_display[] = self::path_from_norm( $n );
			}

			$issues[] = array(
				'kind'          => (string) ( $row['kind'] ?? '' ),
				'id'            => (int) ( $row['id'] ?? 0 ),
				'product_id'    => (int) ( $row['product_id'] ?? 0 ),
				'source_url'    => (string) ( $row['source_url'] ?? '' ),
				'target_url'    => (string) ( $row['target_url'] ?? '' ),
				'redirect_type' => (string) ( $row['redirect_type'] ?? '' ),
				'old_path'      => (string) ( $row['old_path'] ?? '' ),
				'hops'          => max( 1, $hops ),
				'path'          => $path_display,
				'code'          => 'redirect_loop',
				'label'         => sprintf(
					/* translators: %d: hop count in cycle */
					__( 'حلقه ریدایرکت (%d پرش)', 'shojaei-seo-for-woo' ),
					max( 1, $hops )
				),
				'severity'      => 'error',
			);

			if ( count( $issues ) >= self::MAX_ISSUES ) {
				break;
			}
		}

		$report = array(
			'scanned_at'    => current_time( 'mysql' ),
			'total_checked' => $checked,
			'loops'         => count( $issues ),
			'issues'        => $issues,
		);
		update_option( self::OPTION_LOOPS, $report, false );
		return $report;
	}

	/**
	 * Break a loop by disabling/undoing the listed redirect edge.
	 *
	 * @param string $kind oos|slug.
	 * @param int    $id Id.
	 * @param int    $product_id Product.
	 * @return true|WP_Error
	 */
	public static function break_loop( string $kind, int $id, int $product_id = 0 ) {
		$result = self::disable_redirect( $kind, $id, $product_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		self::forget_loop_issue( $kind, $id, $product_id );
		return true;
	}

	/**
	 * Flatten one chain: point source redirect directly at final destination.
	 *
	 * @param string $kind oos|slug.
	 * @param int    $id Row id.
	 * @param int    $product_id OOS product id.
	 * @return true|WP_Error
	 */
	public static function flatten_chain( string $kind, int $id, int $product_id = 0 ) {
		$report = self::get_chain_report();
		$match  = null;
		foreach ( $report['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			if ( (string) ( $issue['kind'] ?? '' ) !== $kind ) {
				continue;
			}
			if ( $id > 0 && (int) ( $issue['id'] ?? 0 ) === $id ) {
				$match = $issue;
				break;
			}
			if ( 'oos' === $kind && $product_id > 0 && (int) ( $issue['product_id'] ?? 0 ) === $product_id ) {
				$match = $issue;
				break;
			}
		}

		if ( ! $match ) {
			// Re-resolve live if report stale.
			$live = self::resolve_chain_for( $kind, $id, $product_id );
			if ( is_wp_error( $live ) ) {
				return $live;
			}
			$match = $live;
		}

		$final = esc_url_raw( (string) ( $match['final_url'] ?? '' ) );
		if ( '' === $final ) {
			return new WP_Error( 'no_final', __( 'مقصد نهایی پیدا نشد.', 'shojaei-seo-for-woo' ) );
		}

		$updated = self::update_redirect_target( $kind, $id, $product_id, $final );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::forget_chain_issue( $kind, $id, $product_id );
		return true;
	}

	/**
	 * Update stored redirect destination.
	 *
	 * @param string $kind Kind.
	 * @param int    $id Id.
	 * @param int    $product_id Product.
	 * @param string $target_url Final URL.
	 * @return true|WP_Error
	 */
	public static function update_redirect_target( string $kind, int $id, int $product_id, string $target_url ) {
		global $wpdb;
		$target_url = esc_url_raw( $target_url );
		if ( '' === $target_url ) {
			return new WP_Error( 'empty', __( 'آدرس مقصد خالی است.', 'shojaei-seo-for-woo' ) );
		}

		if ( 'oos' === $kind ) {
			if ( $product_id < 1 && $id > 0 ) {
				$table      = Shojaei_SEO_Helpers::oos_table();
				$product_id = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT product_id FROM {$table} WHERE id = %d", $id )
				);
			}
			if ( $product_id < 1 ) {
				return new WP_Error( 'missing', __( 'محصول مبدأ پیدا نشد.', 'shojaei-seo-for-woo' ) );
			}
			$table = Shojaei_SEO_Helpers::oos_table();
			$ok    = $wpdb->update(
				$table,
				array( 'target_url' => $target_url ),
				array(
					'product_id' => $product_id,
					'status'     => 'redirected',
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
			if ( false === $ok ) {
				return new WP_Error( 'db', __( 'به‌روزرسانی مقصد ناموفق بود.', 'shojaei-seo-for-woo' ) );
			}
			if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
				Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
			}
			return true;
		}

		if ( 'slug' === $kind ) {
			if ( ! class_exists( 'Shojaei_SEO_Slug' ) ) {
				return new WP_Error( 'missing', __( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ) );
			}
			if ( ! Shojaei_SEO_Slug::update_redirect_target( $id, $target_url ) ) {
				return new WP_Error( 'db', __( 'به‌روزرسانی مقصد نامک ناموفق بود.', 'shojaei-seo-for-woo' ) );
			}
			return true;
		}

		return new WP_Error( 'bad_kind', __( 'نوع ریدایرکت نامعتبر است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Live resolve chain for one redirect.
	 *
	 * @param string $kind Kind.
	 * @param int    $id Id.
	 * @param int    $product_id Product.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function resolve_chain_for( string $kind, int $id, int $product_id ) {
		$fresh = self::scan_chains();
		foreach ( $fresh['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			if ( (string) ( $issue['kind'] ?? '' ) !== $kind ) {
				continue;
			}
			if ( $id > 0 && (int) ( $issue['id'] ?? 0 ) === $id ) {
				return $issue;
			}
			if ( 'oos' === $kind && $product_id > 0 && (int) ( $issue['product_id'] ?? 0 ) === $product_id ) {
				return $issue;
			}
		}
		return new WP_Error( 'not_found', __( 'زنجیره‌ای برای این ریدایرکت پیدا نشد.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Pretty path fragment from normalized host+path.
	 *
	 * @param string $norm Normalized URL.
	 */
	private static function path_from_norm( string $norm ): string {
		$pos = strpos( $norm, '/' );
		if ( false === $pos ) {
			return $norm;
		}
		$path = substr( $norm, $pos );
		return $path ? $path : '/';
	}

	/**
	 * Remove one issue from cached report after fix.
	 *
	 * @param string $kind Kind.
	 * @param int    $id Id.
	 * @param int    $product_id Product.
	 */
	private static function forget_issue( string $kind, int $id, int $product_id ): void {
		$report = self::get_broken_report();
		$issues = array();
		foreach ( $report['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$same_kind = ( (string) ( $issue['kind'] ?? '' ) ) === $kind;
			$same_id   = (int) ( $issue['id'] ?? 0 ) === $id && $id > 0;
			$same_pid  = 'oos' === $kind && (int) ( $issue['product_id'] ?? 0 ) === $product_id && $product_id > 0;
			if ( $same_kind && ( $same_id || $same_pid ) ) {
				continue;
			}
			$issues[] = $issue;
		}
		$report['issues'] = $issues;
		$report['broken'] = count( $issues );
		update_option( self::OPTION_BROKEN, $report, false );
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
	}

	/**
	 * Drop a chain issue from cache after flatten.
	 *
	 * @param string $kind Kind.
	 * @param int    $id Id.
	 * @param int    $product_id Product.
	 */
	private static function forget_chain_issue( string $kind, int $id, int $product_id ): void {
		$report = self::get_chain_report();
		$issues = array();
		foreach ( $report['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$same_kind = ( (string) ( $issue['kind'] ?? '' ) ) === $kind;
			$same_id   = (int) ( $issue['id'] ?? 0 ) === $id && $id > 0;
			$same_pid  = 'oos' === $kind && (int) ( $issue['product_id'] ?? 0 ) === $product_id && $product_id > 0;
			if ( $same_kind && ( $same_id || $same_pid ) ) {
				continue;
			}
			$issues[] = $issue;
		}
		$report['issues'] = $issues;
		$report['chains'] = count( $issues );
		update_option( self::OPTION_CHAINS, $report, false );
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
	}

	/**
	 * Drop a loop issue from cache after break.
	 *
	 * @param string $kind Kind.
	 * @param int    $id Id.
	 * @param int    $product_id Product.
	 */
	private static function forget_loop_issue( string $kind, int $id, int $product_id ): void {
		$report = self::get_loop_report();
		$issues = array();
		foreach ( $report['issues'] as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$same_kind = ( (string) ( $issue['kind'] ?? '' ) ) === $kind;
			$same_id   = (int) ( $issue['id'] ?? 0 ) === $id && $id > 0;
			$same_pid  = 'oos' === $kind && (int) ( $issue['product_id'] ?? 0 ) === $product_id && $product_id > 0;
			if ( $same_kind && ( $same_id || $same_pid ) ) {
				continue;
			}
			$issues[] = $issue;
		}
		$report['issues'] = $issues;
		$report['loops']  = count( $issues );
		update_option( self::OPTION_LOOPS, $report, false );
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
	}

	/**
	 * @param array<string,mixed> $row Row.
	 * @param string              $code Code.
	 * @param int                 $target_post_id Target post.
	 * @param string              $severity Severity label key or error|warning.
	 * @return array<string,mixed>
	 */
	private static function issue_row( array $row, string $code, int $target_post_id, string $severity ): array {
		$labels = self::broken_labels();
		return array(
			'kind'           => (string) ( $row['kind'] ?? '' ),
			'id'             => (int) ( $row['id'] ?? 0 ),
			'product_id'     => (int) ( $row['product_id'] ?? 0 ),
			'source_url'     => (string) ( $row['source_url'] ?? '' ),
			'target_url'     => (string) ( $row['target_url'] ?? '' ),
			'redirect_type'  => (string) ( $row['redirect_type'] ?? '' ),
			'old_path'       => (string) ( $row['old_path'] ?? '' ),
			'code'           => $code,
			'label'          => $labels[ $code ] ?? $code,
			'severity'       => in_array( $severity, array( 'error', 'warning' ), true ) ? $severity : 'error',
			'target_post_id' => $target_post_id,
		);
	}
}
