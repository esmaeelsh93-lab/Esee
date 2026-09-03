<?php
/**
 * مهاجرت از Rank Math / Yoast / AIOSEO به ساختار Damavand.
 *
 * جدول ریدایرکت مقصد: {prefix}seo_core_manual_redirects
 * (نام canonical افزونه برای ریدایرکت‌های دستی — معادل «seo_core_redirects» در مشخصات).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_SEO_Migrator
 */
class Damavand_SEO_Migrator {

	public const BATCH_SIZE = 100;

	public const META_TITLE    = '_damavand_seo_title';
	public const META_DESC     = '_damavand_seo_metadesc';
	public const META_CANON    = '_damavand_seo_canonical';
	public const META_FOCUS    = '_damavand_seo_focus_keyword';
	public const META_SCORE    = '_damavand_seo_score';
	public const META_OG_TITLE = '_damavand_seo_og_title';
	public const META_OG_DESC  = '_damavand_seo_og_description';
	public const META_OG_IMAGE = '_damavand_seo_og_image';
	public const META_TW_TITLE = '_damavand_seo_twitter_title';
	public const META_TW_DESC  = '_damavand_seo_twitter_description';
	public const META_TW_IMAGE = '_damavand_seo_twitter_image';
	public const META_ROBOTS   = '_damavand_seo_robots';
	public const META_PILLAR   = '_damavand_seo_pillar';

	public const OPTION_PROGRESS = 'damavand_seo_migrate_progress';

	/**
	 * نقشه فیلد مقصد → کلیدهای منبع (اولویت: Rank Math → Yoast → AIOSEO → SEOPress → Squirrly → TSF).
	 *
	 * @return array<string,string[]>
	 */
	public static function meta_source_map(): array {
		$map = array(
			self::META_TITLE => array(
				'rank_math_title',
				'_yoast_wpseo_title',
				'_aioseo_title',
				'_seopress_titles_title',
				'_sq_post_title',
				'_genesis_title',
				'_wpseo_title',
				'_wds_title',
				'_wpms_head_metatitle',
				'_metaseo_metatitle',
				'_su_title',
				'_psp_title',
			),
			self::META_DESC => array(
				'rank_math_description',
				'_yoast_wpseo_metadesc',
				'_aioseo_description',
				'_seopress_titles_desc',
				'_sq_post_description',
				'_genesis_description',
				'_wpseo_metadesc',
				'_wds_metadesc',
				'_wpms_head_metadesc',
				'_metaseo_metadesc',
				'_su_description',
				'_psp_description',
			),
			self::META_CANON => array(
				'rank_math_canonical_url',
				'rank_math_canonical',
				'_yoast_wpseo_canonical',
				'_aioseo_canonical_url',
				'_seopress_robots_canonical',
				'_genesis_canonical_uri',
				'_wds_canonical',
				'_wpms_head_canonical',
				'_psp_canonical',
			),
			self::META_FOCUS => array(
				'rank_math_focus_keyword',
				'rank_math_focuskeyword',
				'_yoast_wpseo_focuskw',
				'_aioseo_keywords',
				'_sq_post_keywords',
				'_seopress_analysis_target_kw',
				'_wds_focus-keywords',
				'_wpms_head_keywords',
				'_metaseo_metaspecific_keywords',
			),
			self::META_OG_TITLE => array(
				'rank_math_facebook_title',
				'_yoast_wpseo_opengraph-title',
				'_aioseo_og_title',
				'_seopress_social_fb_title',
				'_sq_post_facebook_title',
				'_wds_opengraph_title',
				'_metaseo_metaopengraph-title',
			),
			self::META_OG_DESC => array(
				'rank_math_facebook_description',
				'_yoast_wpseo_opengraph-description',
				'_aioseo_og_description',
				'_seopress_social_fb_desc',
				'_sq_post_facebook_description',
				'_wds_opengraph_description',
				'_metaseo_metaopengraph-desc',
			),
			self::META_OG_IMAGE => array(
				'rank_math_facebook_image',
				'rank_math_facebook_image_id',
				'_yoast_wpseo_opengraph-image',
				'_yoast_wpseo_opengraph-image-id',
				'_aioseo_og_image_custom_url',
				'_aioseo_og_image_source',
				'_seopress_social_fb_img',
				'_sq_post_facebook_image',
				'_metaseo_metaopengraph-image',
			),
			self::META_TW_TITLE => array(
				'rank_math_twitter_title',
				'_yoast_wpseo_twitter-title',
				'_aioseo_twitter_title',
				'_seopress_social_twitter_title',
				'_sq_post_twitter_title',
				'_wds_twitter_title',
				'_metaseo_metatwitter-title',
			),
			self::META_TW_DESC => array(
				'rank_math_twitter_description',
				'_yoast_wpseo_twitter-description',
				'_aioseo_twitter_description',
				'_seopress_social_twitter_desc',
				'_sq_post_twitter_description',
				'_wds_twitter_description',
				'_metaseo_metatwitter-desc',
			),
			self::META_TW_IMAGE => array(
				'rank_math_twitter_image',
				'rank_math_twitter_image_id',
				'_yoast_wpseo_twitter-image',
				'_yoast_wpseo_twitter-image-id',
				'_aioseo_twitter_image_custom_url',
				'_seopress_social_twitter_img',
				'_sq_post_twitter_image',
				'_metaseo_metatwitter-image',
			),
		);

		/**
		 * Filter migration source map (dest meta key => source meta keys).
		 *
		 * @param array<string,string[]> $map Map.
		 */
		return (array) apply_filters( 'damavand_seo_migrate_meta_map', $map );
	}

	/**
	 * خوش‌آمدگویی داینامیک — بدون نام استاتیک.
	 */
	public static function get_user_greeting(): string {
		$user = wp_get_current_user();
		if ( $user instanceof WP_User && $user->exists() ) {
			if ( ! empty( $user->display_name ) ) {
				return sanitize_text_field( $user->display_name ) . ' گرامی';
			}
			if ( ! empty( $user->user_login ) ) {
				return sanitize_text_field( $user->user_login ) . ' گرامی';
			}
		}
		return 'کاربر گرامی';
	}

	/**
	 * تشخیص منابع فعال / داده موجود.
	 *
	 * @return array<string,mixed>
	 */
	public static function detect_sources(): array {
		global $wpdb;

		$rm_table = $wpdb->prefix . 'rank_math_redirections';
		$yoast_t  = $wpdb->prefix . 'yoast_seo_redirects';
		$aio_t    = $wpdb->prefix . 'aioseo_redirects';

		return array(
			'greeting'           => self::get_user_greeting(),
			'rank_math_active'   => self::is_rank_math_active(),
			'yoast_active'       => self::is_yoast_active(),
			'aioseo_active'      => defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin' ),
			'seopress_active'    => defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ),
			'squirrly_active'    => defined( 'SQ_VERSION' ) || class_exists( 'SQ_Classes_Helpers_Tools' ),
			'tsf_active'         => defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ),
			'redirection_active' => defined( 'REDIRECTION_VERSION' ) || class_exists( 'Redirection' ),
			'slim_seo_active'    => defined( 'SLIM_SEO_VERSION' ) || class_exists( 'SlimSEO\\Container' ),
			'smartcrawl_active'  => defined( 'WDS_PLUGIN_VERSION' ) || class_exists( 'Smartcrawl_Controller' ) || class_exists( 'SmartCrawl\\Controllers\\Controller' ),
			'wp_meta_seo_active' => defined( 'WPMSEO_VERSION' ) || class_exists( 'WpMetaSeo' ),
			'seo_ultimate_active'=> defined( 'SU_PLUGIN_NAME' ) || class_exists( 'SEO_Ultimate' ),
			'psp_active'         => defined( 'PSP_VERSION' ) || class_exists( 'Premium_SEO_Pack' ),
			'rank_math_redirects_table' => self::table_exists( $rm_table ),
			'yoast_redirects_table'     => self::table_exists( $yoast_t ),
			'aioseo_redirects_table'    => self::table_exists( $aio_t ),
			'seopress_redirects_table'  => self::table_exists( $wpdb->prefix . 'seopress_redirection' ),
			'redirection_items_table'   => self::table_exists( $wpdb->prefix . 'redirection_items' ),
			'aioseo_posts_table'        => self::table_exists( $wpdb->prefix . 'aioseo_posts' ),
			'yoast_redirects_option'    => is_array( get_option( 'wpseo-premium-redirects-base', null ) ),
			'dest_redirects_table'      => class_exists( 'Shojaei_SEO_Manual_Redirect' )
				? Shojaei_SEO_Manual_Redirect::table()
				: $wpdb->prefix . 'seo_core_manual_redirects',
			'post_types'         => self::post_types(),
			'batch_size'         => self::BATCH_SIZE,
			'dry_run'            => self::dry_run_stats(),
		);
	}

	/**
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = array( 'post', 'page' );
		if ( post_type_exists( 'product' ) ) {
			$types[] = 'product';
		}
		/**
		 * فیلتر انواع پست برای مهاجرت متا.
		 *
		 * @param string[] $types Types.
		 */
		return array_values( array_unique( (array) apply_filters( 'damavand_seo_migrate_post_types', $types ) ) );
	}

	/**
	 * یک بچ مهاجرت متای پست.
	 *
	 * @param int  $offset Offset.
	 * @param bool $overwrite بازنویسی مقصد اگر پر باشد.
	 * @return array<string,mixed>
	 */
	public static function migrate_meta_batch( int $offset = 0, bool $overwrite = false ): array {
		$offset = max( 0, $offset );
		$q      = new WP_Query(
			array(
				'post_type'              => self::post_types(),
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'         => self::BATCH_SIZE,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$ids     = array_map( 'absint', (array) $q->posts );
		$updated = 0;
		$scanned = 0;

		foreach ( $ids as $post_id ) {
			++$scanned;
			if ( self::migrate_one_post( $post_id, $overwrite ) ) {
				++$updated;
			}
		}

		$next     = $offset + self::BATCH_SIZE;
		$done     = $scanned < self::BATCH_SIZE || $next >= (int) $q->found_posts;
		$progress = array(
			'offset'         => $done ? (int) $q->found_posts : $next,
			'total'          => (int) $q->found_posts,
			'batch_scanned'  => $scanned,
			'batch_updated'  => $updated,
			'done'           => $done,
		);

		$stored = get_option( self::OPTION_PROGRESS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored['posts_scanned']  = (int) ( $stored['posts_scanned'] ?? 0 ) + $scanned;
		$stored['posts_migrated'] = (int) ( $stored['posts_migrated'] ?? 0 ) + $updated;
		$stored['meta_done']      = $done;
		update_option( self::OPTION_PROGRESS, $stored, false );

		return $progress;
	}

	/**
	 * مهاجرت یک پست.
	 *
	 * @param int  $post_id    Post ID.
	 * @param bool $overwrite  Overwrite.
	 */
	public static function migrate_one_post( int $post_id, bool $overwrite = false ): bool {
		if ( $post_id < 1 ) {
			return false;
		}
		$changed = false;

		foreach ( self::meta_source_map() as $dest => $sources ) {
			if ( self::copy_meta_field( $post_id, $dest, $sources, $overwrite ) ) {
				$changed = true;
			}
		}

		if ( self::migrate_aioseo_table_row( $post_id, $overwrite ) ) {
			$changed = true;
		}
		if ( self::migrate_slim_seo_blob( $post_id, $overwrite ) ) {
			$changed = true;
		}
		if ( self::migrate_robots_flags( $post_id, $overwrite ) ) {
			$changed = true;
		}
		if ( self::migrate_pillar_flag( $post_id, $overwrite ) ) {
			$changed = true;
		}

		if ( class_exists( 'Damavand_Persian_SEO_Score' ) && Damavand_Persian_SEO_Score::seed_from_rank_math( $post_id ) ) {
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Copy one destination meta from first non-empty source.
	 *
	 * @param int      $post_id   Post.
	 * @param string   $dest      Destination key.
	 * @param string[] $sources   Source keys.
	 * @param bool     $overwrite Overwrite dest.
	 */
	public static function copy_meta_field( int $post_id, string $dest, array $sources, bool $overwrite = false ): bool {
		$current = get_post_meta( $post_id, $dest, true );
		if ( ! $overwrite && self::meta_has_value( $current ) ) {
			return false;
		}

		$value = self::first_source_value( $post_id, $sources );
		if ( null === $value ) {
			return false;
		}

		if ( in_array( $dest, array( self::META_OG_IMAGE, self::META_TW_IMAGE ), true ) ) {
			$value = is_numeric( $value ) ? (string) absint( $value ) : esc_url_raw( (string) $value );
		} elseif ( self::META_CANON === $dest ) {
			$value = esc_url_raw( (string) $value );
		} else {
			$value = sanitize_text_field( (string) $value );
		}

		if ( '' === $value || $value === $current ) {
			return false;
		}

		update_post_meta( $post_id, $dest, $value );
		return true;
	}

	/**
	 * @param mixed $val Meta value.
	 */
	private static function meta_has_value( $val ): bool {
		if ( is_array( $val ) ) {
			return ! empty( array_filter( $val ) );
		}
		return '' !== trim( (string) $val );
	}

	/**
	 * @param int      $post_id Post.
	 * @param string[] $keys    Keys.
	 * @return string|int|null
	 */
	private static function first_source_value( int $post_id, array $keys ) {
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_array( $raw ) ) {
				$raw = implode( ', ', array_map( 'strval', $raw ) );
			}
			$raw = trim( (string) $raw );
			if ( '' !== $raw ) {
				return $raw;
			}
		}
		return null;
	}

	/**
	 * AIOSEO v4+ row in aioseo_posts table.
	 *
	 * @param int  $post_id   Post.
	 * @param bool $overwrite Overwrite.
	 */
	public static function migrate_aioseo_table_row( int $post_id, bool $overwrite = false ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_posts';
		if ( ! self::table_exists( $table ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", $post_id ), ARRAY_A );
		if ( ! is_array( $row ) || empty( $row ) ) {
			return false;
		}

		$map = array(
			self::META_TITLE   => array( 'title' ),
			self::META_DESC    => array( 'description' ),
			self::META_CANON   => array( 'canonical_url' ),
			self::META_OG_TITLE => array( 'og_title' ),
			self::META_OG_DESC  => array( 'og_description' ),
			self::META_TW_TITLE => array( 'twitter_title' ),
			self::META_TW_DESC  => array( 'twitter_description' ),
		);

		$changed = false;
		foreach ( $map as $dest => $cols ) {
			foreach ( $cols as $col ) {
				if ( empty( $row[ $col ] ) ) {
					continue;
				}
				$current = get_post_meta( $post_id, $dest, true );
				if ( ! $overwrite && self::meta_has_value( $current ) ) {
					continue 2;
				}
				$val = trim( (string) $row[ $col ] );
				if ( '' === $val ) {
					continue 2;
				}
				if ( self::META_CANON === $dest ) {
					$val = esc_url_raw( $val );
				} else {
					$val = sanitize_text_field( $val );
				}
				if ( '' !== $val && $val !== $current ) {
					update_post_meta( $post_id, $dest, $val );
					$changed = true;
				}
				break;
			}
		}

		if ( ! $overwrite && self::meta_has_value( get_post_meta( $post_id, self::META_FOCUS, true ) ) ) {
			// skip focus from keyphrases.
		} elseif ( ! empty( $row['keyphrases'] ) ) {
			$kp = json_decode( (string) $row['keyphrases'], true );
			if ( is_array( $kp ) && ! empty( $kp['focus']['keyphrase'] ) ) {
				$focus = sanitize_text_field( (string) $kp['focus']['keyphrase'] );
				if ( '' !== $focus ) {
					update_post_meta( $post_id, self::META_FOCUS, $focus );
					$changed = true;
				}
			}
		}

		$robots = array();
		if ( ! empty( $row['robots_noindex'] ) ) {
			$robots[] = 'noindex';
		}
		if ( ! empty( $row['robots_nofollow'] ) ) {
			$robots[] = 'nofollow';
		}
		if ( ! empty( $robots ) && ( $overwrite || ! self::meta_has_value( get_post_meta( $post_id, self::META_ROBOTS, true ) ) ) ) {
			update_post_meta( $post_id, self::META_ROBOTS, array_values( array_unique( $robots ) ) );
			$changed = true;
		}

		return $changed;
	}

	/**
	 * Slim SEO JSON blob in _slim_seo.
	 *
	 * @param int  $post_id   Post.
	 * @param bool $overwrite Overwrite.
	 */
	public static function migrate_slim_seo_blob( int $post_id, bool $overwrite = false ): bool {
		$raw = get_post_meta( $post_id, '_slim_seo', true );
		if ( ! is_array( $raw ) ) {
			return false;
		}
		$map = array(
			self::META_TITLE => 'title',
			self::META_DESC  => 'description',
			self::META_CANON => 'canonical',
		);
		$changed = false;
		foreach ( $map as $dest => $key ) {
			if ( empty( $raw[ $key ] ) ) {
				continue;
			}
			$current = get_post_meta( $post_id, $dest, true );
			if ( ! $overwrite && self::meta_has_value( $current ) ) {
				continue;
			}
			$val = self::META_CANON === $dest
				? esc_url_raw( (string) $raw[ $key ] )
				: sanitize_text_field( (string) $raw[ $key ] );
			if ( '' !== $val && $val !== $current ) {
				update_post_meta( $post_id, $dest, $val );
				$changed = true;
			}
		}
		return $changed;
	}

	/**
	 * robots noindex/nofollow from RM / Yoast / SEOPress flags.
	 *
	 * @param int  $post_id   Post.
	 * @param bool $overwrite Overwrite.
	 */
	public static function migrate_robots_flags( int $post_id, bool $overwrite = false ): bool {
		if ( ! $overwrite && self::meta_has_value( get_post_meta( $post_id, self::META_ROBOTS, true ) ) ) {
			return false;
		}

		$robots = array();
		$rm     = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rm ) ) {
			foreach ( $rm as $flag ) {
				$flag = strtolower( trim( (string) $flag ) );
				if ( in_array( $flag, array( 'noindex', 'nofollow', 'noarchive', 'nosnippet' ), true ) ) {
					$robots[] = $flag;
				}
			}
		}
		if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			$robots[] = 'noindex';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true ) ) {
			$robots[] = 'nofollow';
		}
		$sp_index = (string) get_post_meta( $post_id, '_seopress_robots_index', true );
		if ( in_array( $sp_index, array( 'no', '0', 'off' ), true ) ) {
			$robots[] = 'noindex';
		}
		$sp_follow = (string) get_post_meta( $post_id, '_seopress_robots_follow', true );
		if ( in_array( $sp_follow, array( 'no', '0', 'off' ), true ) ) {
			$robots[] = 'nofollow';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_aioseo_robots_noindex', true ) ) {
			$robots[] = 'noindex';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_aioseo_robots_nofollow', true ) ) {
			$robots[] = 'nofollow';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_wds_meta-robots-noindex', true ) ) {
			$robots[] = 'noindex';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_wds_meta-robots-nofollow', true ) ) {
			$robots[] = 'nofollow';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_su_meta_robots_noindex', true ) ) {
			$robots[] = 'noindex';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_su_meta_robots_nofollow', true ) ) {
			$robots[] = 'nofollow';
		}
		if ( '1' === (string) get_post_meta( $post_id, '_wpms_head_meta_robots_index', true ) ) {
			$robots[] = 'noindex';
		}
		$page_index = strtolower( trim( (string) get_post_meta( $post_id, '_metaseo_metaindex', true ) ) );
		if ( in_array( $page_index, array( 'noindex', '0', 'no', 'off' ), true ) ) {
			$robots[] = 'noindex';
		}
		$page_follow = strtolower( trim( (string) get_post_meta( $post_id, '_metaseo_metafollow', true ) ) );
		if ( in_array( $page_follow, array( 'nofollow', '0', 'no', 'off' ), true ) ) {
			$robots[] = 'nofollow';
		}

		$robots = array_values( array_unique( $robots ) );
		if ( empty( $robots ) ) {
			return false;
		}
		update_post_meta( $post_id, self::META_ROBOTS, $robots );
		return true;
	}

	/**
	 * Pillar / cornerstone content flag.
	 *
	 * @param int  $post_id   Post.
	 * @param bool $overwrite Overwrite.
	 */
	public static function migrate_pillar_flag( int $post_id, bool $overwrite = false ): bool {
		if ( ! $overwrite && self::meta_has_value( get_post_meta( $post_id, self::META_PILLAR, true ) ) ) {
			return false;
		}
		$yes = false;
		if ( 'on' === (string) get_post_meta( $post_id, 'rank_math_pillar_content', true ) ) {
			$yes = true;
		}
		if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_is_cornerstone', true ) ) {
			$yes = true;
		}
		if ( ! $yes ) {
			return false;
		}
		update_post_meta( $post_id, self::META_PILLAR, 'yes' );
		return true;
	}

	/**
	 * Dry-run stats + sample previews (no writes).
	 *
	 * @return array<string,mixed>
	 */
	public static function dry_run_stats(): array {
		global $wpdb;

		$types = self::post_types();
		$in    = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";
		$keys  = array();
		foreach ( self::meta_source_map() as $sources ) {
			foreach ( $sources as $k ) {
				$keys[ $k ] = true;
			}
		}
		$keys = array_keys( $keys );
		if ( empty( $keys ) ) {
			return array(
				'eligible_posts' => 0,
				'samples'          => array(),
				'redirect_counts'  => self::redirect_source_counts(),
			);
		}

		$key_in = "'" . implode( "','", array_map( 'esc_sql', $keys ) ) . "'";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$eligible = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type IN ({$in})
			AND pm.meta_key IN ({$key_in})
			AND pm.meta_value <> ''"
		);

		$aioseo_table = $wpdb->prefix . 'aioseo_posts';
		if ( self::table_exists( $aioseo_table ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$eligible += (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT ap.post_id) FROM {$aioseo_table} ap
				INNER JOIN {$wpdb->posts} p ON p.ID = ap.post_id
				WHERE p.post_type IN ({$in})
				AND (ap.title <> '' OR ap.description <> '' OR ap.canonical_url <> '')"
			);
		}

		$samples = array();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sample_ids = $wpdb->get_col(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type IN ({$in})
			AND pm.meta_key IN ({$key_in})
			AND pm.meta_value <> ''
			ORDER BY p.ID DESC
			LIMIT 3"
		);
		foreach ( array_map( 'absint', (array) $sample_ids ) as $sid ) {
			if ( $sid > 0 ) {
				$samples[] = self::preview_post( $sid );
			}
		}

		return array(
			'eligible_posts' => $eligible,
			'samples'        => $samples,
			'redirect_counts'=> self::redirect_source_counts(),
		);
	}

	/**
	 * Before/after preview for one post (no DB writes).
	 *
	 * @param int $post_id Post.
	 * @return array<string,mixed>
	 */
	public static function preview_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$before = array();
		$after  = array();
		foreach ( self::meta_source_map() as $dest => $sources ) {
			$before[ $dest ] = get_post_meta( $post_id, $dest, true );
			$val             = self::first_source_value( $post_id, $sources );
			$after[ $dest ]  = null === $val ? '' : $val;
		}

		return array(
			'post_id'    => $post_id,
			'post_title' => get_the_title( $post_id ),
			'post_type'  => $post->post_type,
			'before'     => $before,
			'after'      => $after,
		);
	}

	/**
	 * Estimated redirect rows per source (no import).
	 *
	 * @return array<string,int>
	 */
	public static function redirect_source_counts(): array {
		global $wpdb;
		$out = array(
			'rank_math'   => 0,
			'yoast'       => 0,
			'aioseo'      => 0,
			'seopress'    => 0,
			'redirection' => 0,
		);

		$rm = $wpdb->prefix . 'rank_math_redirections';
		if ( self::table_exists( $rm ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['rank_math'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rm}" );
		}
		$yo = $wpdb->prefix . 'yoast_seo_redirects';
		if ( self::table_exists( $yo ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['yoast'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$yo}" );
		}
		$opt = get_option( 'wpseo-premium-redirects-base', array() );
		if ( is_array( $opt ) ) {
			$out['yoast'] += count( $opt );
		}
		$aio = $wpdb->prefix . 'aioseo_redirects';
		if ( self::table_exists( $aio ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['aioseo'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$aio}" );
		}
		$sp = $wpdb->prefix . 'seopress_redirection';
		if ( self::table_exists( $sp ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['seopress'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sp}" );
		}
		$rd = $wpdb->prefix . 'redirection_items';
		if ( self::table_exists( $rd ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['redirection'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rd} WHERE status = 'enabled' OR status IS NULL" );
		}

		return $out;
	}

	/**
	 * @return bool
	 */
	private static function is_rank_math_active(): bool {
		return class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::is_rank_math_active()
			: ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) );
	}

	/**
	 * @return bool
	 */
	private static function is_yoast_active(): bool {
		return class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::is_yoast_active()
			: ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) );
	}

	/**
	 * Explicit Rank Math meta migration helper (skip if Damavand target already set).
	 *
	 * @param int  $post_id   Post.
	 * @param bool $overwrite Overwrite Damavand values.
	 */
	public static function migrate_rank_math_meta( int $post_id, bool $overwrite = false ): bool {
		return self::migrate_one_post( $post_id, $overwrite );
	}

	/**
	 * Checklist: safe to deactivate Rank Math?
	 *
	 * @return array{items:array<int,array{id:string,label:string,ok:bool,detail:string}>,ready:bool}
	 */
	public static function removal_readiness(): array {
		global $wpdb;

		$progress = get_option( self::OPTION_PROGRESS, array() );
		if ( ! is_array( $progress ) ) {
			$progress = array();
		}

		$types = self::post_types();
		$in    = "'" . implode( "','", array_map( 'esc_sql', $types ) ) . "'";

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rm_titles = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} rm ON rm.post_id = p.ID AND rm.meta_key = 'rank_math_title' AND rm.meta_value <> ''
			LEFT JOIN {$wpdb->postmeta} dm ON dm.post_id = p.ID AND dm.meta_key = '" . self::META_TITLE . "' AND dm.meta_value <> ''
			WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND dm.post_id IS NULL"
		);

		$posts_migrated = (int) ( $progress['posts_migrated'] ?? 0 );
		$redir_done     = ! empty( $progress['redirects_done'] );
		$redir_imported = (int) ( $progress['redirects_imported'] ?? 0 );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$scored = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
			WHERE meta_key IN ('" . self::META_SCORE . "','_damavand_seo_score_seed') AND meta_value <> ''"
		);

		$sitemap_ok = false;
		if ( class_exists( 'SEO_Core_Loader' ) ) {
			$loader     = SEO_Core_Loader::instance();
			$sm         = $loader ? $loader->get_module( 'sitemap' ) : null;
			$sitemap_ok = $sm && method_exists( $sm, 'can_emit' ) && $sm->can_emit();
		}

		$rm_active = class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::is_rank_math_active()
			: defined( 'RANK_MATH_VERSION' );

		$items = array(
			array(
				'id'     => 'meta',
				'label'  => __( 'متای عنوان/توضیح به Damavand', 'shojaei-seo-for-woo' ),
				'ok'     => $rm_titles < 1 || $posts_migrated > 0,
				'detail' => $rm_titles > 0
					? sprintf(
						/* translators: 1: remaining 2: migrated */
						__( '%1$d نوشته هنوز فقط Rank Math دارند؛ مهاجرت‌شده در این نشست: %2$d', 'shojaei-seo-for-woo' ),
						$rm_titles,
						$posts_migrated
					)
					: __( 'متای بازماندهٔ بحرانی یافت نشد (یا مهاجرت انجام شده).', 'shojaei-seo-for-woo' ),
				'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=wizard&wizard_step=1' ),
			),
			array(
				'id'     => 'redirects',
				'label'  => __( 'ریدایرکت‌ها وارد Damavand شده‌اند', 'shojaei-seo-for-woo' ),
				'ok'     => $redir_done || $redir_imported > 0 || ! $rm_active,
				'detail' => sprintf(
					/* translators: %d: count */
					__( 'واردشده: %d', 'shojaei-seo-for-woo' ),
					$redir_imported
				),
				'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=wizard&wizard_step=1' ),
			),
			array(
				'id'     => 'scores',
				'label'  => __( 'امتیاز سئو (seed یا محاسبه‌شده)', 'shojaei-seo-for-woo' ),
				'ok'     => $scored > 0 || ! $rm_active,
				'detail' => sprintf(
					/* translators: %d: count */
					__( '%d نوشته امتیاز Damavand دارند', 'shojaei-seo-for-woo' ),
					$scored
				),
				'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=wizard&wizard_step=1' ),
			),
			array(
				'id'     => 'sitemap',
				'label'  => __( 'نقشه سایت Damavand فعال است', 'shojaei-seo-for-woo' ),
				'ok'     => $sitemap_ok,
				'detail' => $sitemap_ok
					? __( 'shojaei-sitemap.xml آماده ثبت در Search Console است.', 'shojaei-seo-for-woo' )
					: __( 'ماژول نقشه سایت را در هسته سئو روشن/فلاش کنید.', 'shojaei-seo-for-woo' ),
				'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=seo-core' ),
			),
			array(
				'id'     => 'meta_emit',
				'label'  => __( 'خروجی متای Damavand روشن است', 'shojaei-seo-for-woo' ),
				'ok'     => ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' ) )
					|| ( ! $rm_active && class_exists( 'Damavand_SEO_Meta' ) && Damavand_SEO_Meta::should_emit_frontend() ),
				'detail' => ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' ) )
					? __( 'سوئیچ متای عمومی روشن است.', 'shojaei-seo-for-woo' )
					: __( 'از ویزارد «فعال‌سازی متا» را بزنید یا در متای عمومی روشن کنید.', 'shojaei-seo-for-woo' ),
				'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=wizard&wizard_step=2' ),
			),
		);

		$schema_ok = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_schema_enabled', 'yes' ) );
		$items[]   = array(
			'id'     => 'schema',
			'label'  => __( 'اسکیمای محصول Damavand', 'shojaei-seo-for-woo' ),
			'ok'     => $schema_ok,
			'detail' => $schema_ok
				? __( 'اسکیما روشن است (کنار یا پس از خاموش کردن Rank Math).', 'shojaei-seo-for-woo' )
				: __( 'اسکیما را در تنظیمات پیشرفته روشن کنید.', 'shojaei-seo-for-woo' ),
			'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=settings' ),
		);

		$slug_ok = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_tools_enabled', 'yes' ) );
		$items[] = array(
			'id'     => 'slug',
			'label'  => __( 'ابزار نامک فینگلیش', 'shojaei-seo-for-woo' ),
			'ok'     => $slug_ok,
			'detail' => $slug_ok
				? __( 'تبدیل نامک فارسی به فینگلیش فعال است.', 'shojaei-seo-for-woo' )
				: __( 'ابزار نامک را در تنظیمات روشن کنید.', 'shojaei-seo-for-woo' ),
			'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=slugs' ),
		);

		$tpl_ok = class_exists( 'Damavand_SEO_Templates' )
			&& '' !== Damavand_SEO_Templates::get_template( Damavand_SEO_Templates::OPT_PRODUCT_TITLE );
		$items[] = array(
			'id'     => 'templates',
			'label'  => __( 'قالب عنوان/توضیح SERP', 'shojaei-seo-for-woo' ),
			'ok'     => $tpl_ok,
			'detail' => $tpl_ok
				? __( 'قالب محصول تنظیم شده (متای عمومی).', 'shojaei-seo-for-woo' )
				: __( 'قالب محصول را در متای عمومی ذخیره کنید.', 'shojaei-seo-for-woo' ),
			'fix'   => admin_url( 'admin.php?page=shojaei-seo&tab=general-meta' ),
		);

		$force = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_force_with_competitors', 'no' ) );
		$items[] = array(
			'id'     => 'no_double_meta',
			'label'  => __( 'بدون دو متای همزمان', 'shojaei-seo-for-woo' ),
			'ok'     => ! $rm_active || ! $force,
			'detail' => ( $rm_active && $force )
				? __( 'Rank Math هنوز فعال است و اجبار خروجی Damavand هم روشن است — خطر متای دوبل.', 'shojaei-seo-for-woo' )
				: ( $rm_active
					? __( 'بعد از سبز شدن بقیه، Rank Math را خاموش کنید (فعلاً بدون اجبار متای دوبل).', 'shojaei-seo-for-woo' )
					: __( 'رقیب فعال نیست یا اجبار همزمان خاموش است.', 'shojaei-seo-for-woo' ) ),
			'fix'   => admin_url( 'plugins.php' ),
		);

		$ready = true;
		foreach ( $items as $item ) {
			if ( empty( $item['ok'] ) ) {
				$ready = false;
				break;
			}
		}

		// Stricter: if Rank Math still has unmigrated titles, not ready.
		if ( $rm_titles > 0 ) {
			$ready = false;
			$items[0]['ok'] = false;
		}

		return array(
			'items'     => $items,
			'ready'     => $ready,
			'rm_active' => $rm_active,
		);
	}

	/**
	 * ایمپورت همه ریدایرکت‌های شناخته‌شده.
	 *
	 * @return array{imported:int,skipped:int,sources:array<string,int>}
	 */
	public static function migrate_redirects(): array {
		$stats = array(
			'imported' => 0,
			'skipped'  => 0,
			'sources'  => array(
				'rank_math'   => 0,
				'yoast'       => 0,
				'aioseo'      => 0,
				'seopress'    => 0,
				'redirection' => 0,
			),
		);

		$rm = self::import_rank_math_redirects();
		$stats['imported'] += $rm['imported'];
		$stats['skipped']  += $rm['skipped'];
		$stats['sources']['rank_math'] = $rm['imported'];

		$yo = self::import_yoast_redirects();
		$stats['imported'] += $yo['imported'];
		$stats['skipped']  += $yo['skipped'];
		$stats['sources']['yoast'] = $yo['imported'];

		$aio = self::import_aioseo_redirects();
		$stats['imported'] += $aio['imported'];
		$stats['skipped']  += $aio['skipped'];
		$stats['sources']['aioseo'] = $aio['imported'];

		$sp = self::import_seopress_redirects();
		$stats['imported'] += $sp['imported'];
		$stats['skipped']  += $sp['skipped'];
		$stats['sources']['seopress'] = $sp['imported'];

		$rd = self::import_redirection_plugin_redirects();
		$stats['imported'] += $rd['imported'];
		$stats['skipped']  += $rd['skipped'];
		$stats['sources']['redirection'] = $rd['imported'];

		$stored = get_option( self::OPTION_PROGRESS, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$stored['redirects_imported'] = (int) ( $stored['redirects_imported'] ?? 0 ) + (int) $stats['imported'];
		$stored['redirects_skipped']  = (int) ( $stored['redirects_skipped'] ?? 0 ) + (int) $stats['skipped'];
		$stored['redirects_done']     = true;
		update_option( self::OPTION_PROGRESS, $stored, false );

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}

		return $stats;
	}

	/**
	 * Rank Math → Damavand.
	 *
	 * @return array{imported:int,skipped:int}
	 */
	public static function import_rank_math_redirects(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( ! self::table_exists( $table ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, sources, url_to, header_code, status FROM {$table}" );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$imported = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			$status = strtolower( (string) ( $row->status ?? 'active' ) );
			if ( in_array( $status, array( 'inactive', 'trash', '0', 'disabled' ), true ) ) {
				++$skipped;
				continue;
			}
			$sources_raw = maybe_unserialize( $row->sources );
			$patterns    = self::extract_rank_math_patterns( $sources_raw );
			$code        = self::normalize_redirect_code( (string) ( $row->header_code ?? '301' ) );
			$dest        = (string) ( $row->url_to ?? '' );
			if ( empty( $patterns ) ) {
				++$skipped;
				continue;
			}
			$result = self::insert_redirect_safe( $patterns, $dest, $code );
			if ( $result > 0 ) {
				$imported += $result;
			} else {
				++$skipped;
			}
		}
		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	/**
	 * @param mixed $sources Unserialized sources.
	 * @return string[]
	 */
	private static function extract_rank_math_patterns( $sources ): array {
		$out = array();
		if ( ! is_array( $sources ) ) {
			return $out;
		}
		foreach ( $sources as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$out[] = trim( $item );
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$pattern = '';
			if ( isset( $item['pattern'] ) ) {
				$pattern = (string) $item['pattern'];
			} elseif ( isset( $item['source'] ) ) {
				$pattern = (string) $item['source'];
			}
			$pattern = trim( $pattern );
			if ( '' !== $pattern ) {
				$out[] = $pattern;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Yoast Premium redirects (جدول یا option).
	 *
	 * @return array{imported:int,skipped:int}
	 */
	public static function import_yoast_redirects(): array {
		global $wpdb;
		$imported = 0;
		$skipped  = 0;

		$table = $wpdb->prefix . 'yoast_seo_redirects';
		if ( self::table_exists( $table ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT origin, url, type, format FROM {$table}" );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$origin = (string) ( $row->origin ?? '' );
					$target = (string) ( $row->url ?? '' );
					$code   = self::normalize_redirect_code( (string) ( $row->type ?? '301' ) );
					$n      = self::insert_redirect_safe( array( $origin ), $target, $code );
					if ( $n > 0 ) {
						$imported += $n;
					} else {
						++$skipped;
					}
				}
			}
		}

		$opt = get_option( 'wpseo-premium-redirects-base', array() );
		if ( is_array( $opt ) ) {
			foreach ( $opt as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$origin = (string) ( $item['origin'] ?? $item['url'] ?? '' );
				$target = (string) ( $item['url'] ?? $item['target'] ?? '' );
				// در برخی نسخه‌ها origin کلید و url مقصد است.
				if ( isset( $item['origin'] ) && isset( $item['url'] ) ) {
					$origin = (string) $item['origin'];
					$target = (string) $item['url'];
				}
				$code = self::normalize_redirect_code( (string) ( $item['type'] ?? '301' ) );
				$n    = self::insert_redirect_safe( array( $origin ), $target, $code );
				if ( $n > 0 ) {
					$imported += $n;
				} else {
					++$skipped;
				}
			}
		}

		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	/**
	 * AIOSEO redirects table (در صورت وجود).
	 *
	 * @return array{imported:int,skipped:int}
	 */
	public static function import_aioseo_redirects(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'aioseo_redirects';
		if ( ! self::table_exists( $table ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT source_url, target_url, type, enabled FROM {$table} WHERE enabled = 1 OR enabled IS NULL" );
		if ( ! is_array( $rows ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}
		$imported = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			$n = self::insert_redirect_safe(
				array( (string) ( $row->source_url ?? '' ) ),
				(string) ( $row->target_url ?? '' ),
				self::normalize_redirect_code( (string) ( $row->type ?? '301' ) )
			);
			if ( $n > 0 ) {
				$imported += $n;
			} else {
				++$skipped;
			}
		}
		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	/**
	 * SEOPress redirection table.
	 *
	 * @return array{imported:int,skipped:int}
	 */
	public static function import_seopress_redirects(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'seopress_redirection';
		if ( ! self::table_exists( $table ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT url, redirect_url, type, enabled FROM {$table}" );
		if ( ! is_array( $rows ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}
		$imported = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			if ( isset( $row->enabled ) && '0' === (string) $row->enabled ) {
				++$skipped;
				continue;
			}
			$n = self::insert_redirect_safe(
				array( (string) ( $row->url ?? '' ) ),
				(string) ( $row->redirect_url ?? '' ),
				self::normalize_redirect_code( (string) ( $row->type ?? '301' ) )
			);
			if ( $n > 0 ) {
				$imported += $n;
			} else {
				++$skipped;
			}
		}
		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	/**
	 * John Godley Redirection plugin.
	 *
	 * @return array{imported:int,skipped:int}
	 */
	public static function import_redirection_plugin_redirects(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'redirection_items';
		if ( ! self::table_exists( $table ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT url, action_data, action_code, status FROM {$table}" );
		if ( ! is_array( $rows ) ) {
			return array( 'imported' => 0, 'skipped' => 0 );
		}
		$imported = 0;
		$skipped  = 0;
		foreach ( $rows as $row ) {
			$status = strtolower( (string) ( $row->status ?? 'enabled' ) );
			if ( in_array( $status, array( 'disabled', 'trash' ), true ) ) {
				++$skipped;
				continue;
			}
			$dest = '';
			$data = maybe_unserialize( $row->action_data ?? '' );
			if ( is_array( $data ) && ! empty( $data['url'] ) ) {
				$dest = (string) $data['url'];
			} elseif ( is_string( $data ) ) {
				$dest = $data;
			}
			$n = self::insert_redirect_safe(
				array( (string) ( $row->url ?? '' ) ),
				$dest,
				self::normalize_redirect_code( (string) ( $row->action_code ?? '301' ) )
			);
			if ( $n > 0 ) {
				$imported += $n;
			} else {
				++$skipped;
			}
		}
		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	/**
	 * درج امن بدون تکرار (بر اساس source_path).
	 *
	 * @param string[] $sources Sources.
	 * @param string   $dest    Destination.
	 * @param string   $code    301|302|410|…
	 * @return int تعداد ردیف درج‌شده.
	 */
	public static function insert_redirect_safe( array $sources, string $dest, string $code ): int {
		global $wpdb;

		if ( ! class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
			return 0;
		}

		$table = Shojaei_SEO_Manual_Redirect::table();
		$code  = self::normalize_redirect_code( $code );
		$clean = array();
		foreach ( $sources as $src ) {
			$path = Shojaei_SEO_Manual_Redirect::normalize_source( (string) $src );
			if ( '' === $path || '/' === $path ) {
				continue;
			}
			// تکراری؟
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE source_path = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$path
				)
			);
			if ( $exists > 0 ) {
				continue;
			}
			$clean[] = $path;
		}
		if ( empty( $clean ) ) {
			return 0;
		}

		// ۴۱۰ بدون مقصد مجاز است.
		if ( in_array( $code, array( '301', '302', '307' ), true ) && '' === trim( $dest ) ) {
			return 0;
		}

		$result = Shojaei_SEO_Manual_Redirect::add_redirect(
			array(
				'sources'       => $clean,
				'destination'   => $dest,
				'redirect_type' => $code,
				'match_type'    => 'exact',
				'is_active'     => true,
			)
		);

		if ( empty( $result['ok'] ) ) {
			return 0;
		}
		return count( $result['ids'] ?? array() );
	}

	/**
	 * @param string $code Code.
	 */
	public static function normalize_redirect_code( string $code ): string {
		$code = preg_replace( '/[^0-9]/', '', $code );
		if ( ! in_array( $code, array( '301', '302', '307', '410', '451' ), true ) ) {
			return '301';
		}
		return $code;
	}

	/**
	 * ریست پیشرفت و شروع تازه.
	 */
	public static function reset_progress(): void {
		delete_option( self::OPTION_PROGRESS );
		update_option(
			self::OPTION_PROGRESS,
			array(
				'posts_scanned'       => 0,
				'posts_migrated'      => 0,
				'redirects_imported'  => 0,
				'redirects_skipped'   => 0,
				'meta_done'           => false,
				'redirects_done'      => false,
				'started_at'          => time(),
			),
			false
		);
	}

	/**
	 * آمار نهایی + پیام فارسی شخصی‌سازی‌شده.
	 *
	 * @return array<string,mixed>
	 */
	public static function build_completion_payload(): array {
		$prog = get_option( self::OPTION_PROGRESS, array() );
		if ( ! is_array( $prog ) ) {
			$prog = array();
		}
		$posts = (int) ( $prog['posts_migrated'] ?? 0 );
		$redir = (int) ( $prog['redirects_imported'] ?? 0 );
		$name  = self::get_user_greeting();

		$readiness = self::removal_readiness();
		if ( ! empty( $readiness['ready'] ) ) {
			$message = sprintf(
				/* translators: 1: greeting */
				__( 'سلام %1$s، مهاجرت و چک‌لیست آماده‌اند. می‌توانید Rank Math را موقتاً غیرفعال کنید و چند روز تست بگیرید.', 'shojaei-seo-for-woo' ),
				$name
			);
		} else {
			$message = sprintf(
				/* translators: 1: greeting */
				__( 'سلام %1$s، همگام‌سازی انجام شد. قبل از حذف Rank Math چک‌لیست زیر را سبز کنید.', 'shojaei-seo-for-woo' ),
				$name
			);
		}

		return array(
			'ok'                 => true,
			'greeting'           => $name,
			'message'            => $message,
			'posts_migrated'     => $posts,
			'posts_scanned'      => (int) ( $prog['posts_scanned'] ?? 0 ),
			'redirects_imported' => $redir,
			'redirects_skipped'  => (int) ( $prog['redirects_skipped'] ?? 0 ),
			'readiness'          => $readiness,
		);
	}

	/**
	 * ثبت هوک AJAX.
	 */
	public static function register_hooks(): void {
		add_action( 'wp_ajax_damavand_seo_migrate', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * هندلر AJAX امن.
	 */
	public static function ajax(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		$can = current_user_can( 'manage_options' )
			|| current_user_can( 'manage_woocommerce' )
			|| ( class_exists( 'SEO_Core_Installer' ) && current_user_can( SEO_Core_Installer::CAPABILITY ) );
		if ( ! $can ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['migrate_action'] ?? '' ) );

		switch ( $action ) {
			case 'detect':
				wp_send_json_success( self::detect_sources() );

			case 'dry_run':
				wp_send_json_success( self::dry_run_stats() );

			case 'preview':
				$post_id = absint( $_POST['post_id'] ?? 0 );
				if ( $post_id < 1 ) {
					wp_send_json_error( array( 'message' => __( 'شناسه نامعتبر.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success( self::preview_post( $post_id ) );

			case 'reset':
				self::reset_progress();
				wp_send_json_success(
					array(
						'message'  => __( 'پیشرفت مهاجرت ریست شد.', 'shojaei-seo-for-woo' ),
						'greeting' => self::get_user_greeting(),
					)
				);

			case 'meta_batch':
				$offset    = absint( $_POST['offset'] ?? 0 );
				$overwrite = ! empty( $_POST['overwrite'] );
				if ( 0 === $offset ) {
					self::reset_progress();
				}
				$batch = self::migrate_meta_batch( $offset, $overwrite );
				wp_send_json_success( $batch );

			case 'redirects':
				$stats = self::migrate_redirects();
				wp_send_json_success( $stats );

			case 'finish':
				wp_send_json_success( self::build_completion_payload() );

			case 'readiness':
				wp_send_json_success( self::removal_readiness() );

			case 'enable_emit':
				wp_send_json_success( self::enable_damavand_emit() );

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * روشن کردن خروجی Damavand برای مسیر جایگزینی Rank Math.
	 *
	 * @return array<string,mixed>
	 */
	public static function enable_damavand_emit(): array {
		update_option( 'shojaei_seo_meta_enabled', 'yes' );

		if ( class_exists( 'SEO_Core_Installer' ) ) {
			$mods = get_option( 'shojaei_seo_core_modules', array() );
			if ( ! is_array( $mods ) ) {
				$mods = array();
			}
			foreach ( array( 'sitemap', 'schema', 'indexnow', 'robots', 'canonical' ) as $id ) {
				$mods[ $id ] = true;
			}
			update_option( 'shojaei_seo_core_modules', $mods, false );
			update_option( 'shojaei_seo_schema_product_enabled', 'yes' );
			update_option( 'shojaei_seo_schema_breadcrumb_enabled', 'yes' );
			update_option( 'shojaei_seo_schema_faq_enabled', 'yes' );
			// کنار Rank Math هنوز احترام بماند تا وقتی خاموش شود؛ اگر رقیب نیست، احترام بی‌اثر است.
			if ( ! Shojaei_SEO_Helpers::is_rank_math_active() && ! Shojaei_SEO_Helpers::is_yoast_active() ) {
				update_option( 'shojaei_seo_schema_respect_seo_plugins', 'no' );
			}
			SEO_Core_Installer::request_rewrite_flush();
		}

		update_option( 'shojaei_seo_slug_tools_enabled', 'yes' );
		update_option( 'shojaei_seo_slug_auto_finglish', 'yes' );

		return array(
			'message'   => __( 'خروجی Damavand روشن شد: متا، اسکیما، نقشه سایت و نامک فینگلیش.', 'shojaei-seo-for-woo' ),
			'readiness' => self::removal_readiness(),
			'meta_on'   => true,
		);
	}

	/**
	 * @param string $table Table name.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ( $found === $table );
	}
}

/**
 * تابع سراسری خوش‌آمدگویی — سازگار با snippet درخواستی.
 */
function get_damavand_seo_user_greeting(): string {
	return Damavand_SEO_Migrator::get_user_greeting();
}
