<?php
/**
 * Damavand Link Graph — storage, CRUD, cache, guardians.
 *
 * Dynamic internal linking foundation (no HTML written into post_content).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Link_Manager
 */
final class Damavand_Link_Manager {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	public const TYPE_AUTO        = 'auto';
	public const TYPE_MANUAL      = 'manual';
	public const TYPE_RELATED_BOX = 'related_box';

	public const CACHE_GROUP = 'damavand_link_graph';
	public const CACHE_TTL   = 12 * HOUR_IN_SECONDS;
	public const DEFAULT_LIMIT = 5;

	/**
	 * Whether guardians are registered.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Table name with blog prefix.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'damavand_link_graph';
	}

	/**
	 * Create / upgrade schema (dbDelta-safe).
	 */
	public static function install(): void {
		global $wpdb;

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_id BIGINT(20) UNSIGNED NOT NULL,
			target_id BIGINT(20) UNSIGNED NOT NULL,
			anchor_text VARCHAR(191) NOT NULL DEFAULT '',
			type VARCHAR(20) NOT NULL DEFAULT 'auto',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			relevance_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
			context VARCHAR(40) NOT NULL DEFAULT '',
			reason VARCHAR(255) NOT NULL DEFAULT '',
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY source_target_type (source_id, target_id, type),
			KEY source_id (source_id),
			KEY target_id (target_id),
			KEY status (status),
			KEY source_status (source_id, status)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Drop table (full uninstall wipe only).
	 */
	public static function uninstall(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Register lifecycle guardians.
	 */
	public static function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		add_action( 'delete_post', array( __CLASS__, 'on_delete_post' ), 20, 1 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition_post_status' ), 20, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete_post' ), 20, 1 );
	}

	/**
	 * Upsert one graph edge.
	 *
	 * @param array<string,mixed> $edge Edge data.
	 * @return int Edge ID (0 on failure).
	 */
	public static function upsert_edge( array $edge ): int {
		global $wpdb;

		$source_id = absint( $edge['source_id'] ?? 0 );
		$target_id = absint( $edge['target_id'] ?? 0 );
		if ( $source_id < 1 || $target_id < 1 || $source_id === $target_id ) {
			return 0;
		}

		$type   = self::sanitize_type( (string) ( $edge['type'] ?? self::TYPE_AUTO ) );
		$status = self::sanitize_status( (string) ( $edge['status'] ?? self::STATUS_PENDING ) );
		$anchor = self::sanitize_anchor( (string) ( $edge['anchor_text'] ?? '' ) );
		$score  = round( (float) ( $edge['relevance_score'] ?? 0 ), 2 );
		$ctx    = sanitize_key( (string) ( $edge['context'] ?? '' ) );
		$reason = sanitize_text_field( (string) ( $edge['reason'] ?? '' ) );
		if ( strlen( $reason ) > 255 ) {
			$reason = substr( $reason, 0, 255 );
		}

		$table = self::table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_id = %d AND target_id = %d AND type = %s LIMIT 1",
				$source_id,
				$target_id,
				$type
			)
		);

		$data = array(
			'source_id'       => $source_id,
			'target_id'       => $target_id,
			'anchor_text'     => $anchor,
			'type'            => $type,
			'status'          => $status,
			'relevance_score' => $score,
			'context'         => $ctx,
			'reason'          => $reason,
			'updated_at'      => $now,
		);
		$format = array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s' );

		if ( $existing ) {
			$ok = false !== $wpdb->update(
				$table,
				$data,
				array( 'id' => (int) $existing ),
				$format,
				array( '%d' )
			);
			if ( $ok ) {
				self::bust_cache( $source_id );
				return (int) $existing;
			}
			return 0;
		}

		$ok = false !== $wpdb->insert( $table, $data, $format );
		if ( $ok ) {
			self::bust_cache( $source_id );
			return (int) $wpdb->insert_id;
		}
		return 0;
	}

	/**
	 * Remove all edges pointing to a target, then invalidate source caches.
	 *
	 * Collects affected source_ids BEFORE delete, returns them for callers,
	 * and busts each source cache.
	 *
	 * @param int $target_id Target post ID.
	 * @return int[] Affected source_ids (unique, absint).
	 */
	public static function purge_target( int $target_id ): array {
		global $wpdb;

		$target_id = absint( $target_id );
		if ( $target_id < 1 || ! self::table_exists() ) {
			return array();
		}

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$raw = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT source_id FROM {$table} WHERE target_id = %d",
				$target_id
			)
		);

		$source_ids = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $sid ) {
				$sid = absint( $sid );
				if ( $sid > 0 ) {
					$source_ids[ $sid ] = $sid;
				}
			}
		}
		$source_ids = array_values( $source_ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE target_id = %d",
				$target_id
			)
		);

		foreach ( $source_ids as $sid ) {
			self::bust_cache( $sid );
		}

		return $source_ids;
	}

	/**
	 * Remove all outgoing edges from a source.
	 *
	 * @param int $source_id Source post ID.
	 * @return int Rows deleted.
	 */
	public static function purge_source( int $source_id ): int {
		global $wpdb;

		$source_id = absint( $source_id );
		if ( $source_id < 1 || ! self::table_exists() ) {
			return 0;
		}

		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE source_id = %d",
				$source_id
			)
		);

		self::bust_cache( $source_id );
		return max( 0, $deleted );
	}

	/**
	 * Full cleanup when a post leaves the public graph.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function purge_post( int $post_id ): void {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		self::purge_target( $post_id );
		self::purge_source( $post_id );
	}

	/**
	 * Approved edges for a source, verified live (publish + not 410), limited.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $limit     Max links (default 5).
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_approved_for_source( int $source_id, int $limit = 0 ): array {
		$source_id = absint( $source_id );
		if ( $source_id < 1 || ! self::table_exists() ) {
			return array();
		}

		/**
		 * Max approved links injected per source page.
		 *
		 * @param int $limit     Limit.
		 * @param int $source_id Source.
		 */
		$limit = (int) apply_filters(
			'damavand_link_graph_limit',
			$limit > 0 ? $limit : self::DEFAULT_LIMIT,
			$source_id
		);
		$limit = max( 1, min( 20, $limit ) );

		$cache_key = self::cache_key( $source_id, $limit );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false === $cached ) {
			$cached = get_transient( $cache_key );
		}
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, source_id, target_id, anchor_text, type, status, relevance_score, context, reason, updated_at
				FROM {$table}
				WHERE source_id = %d AND status = %s
				ORDER BY relevance_score DESC, updated_at DESC
				LIMIT %d",
				$source_id,
				self::STATUS_APPROVED,
				$limit * 3 // fetch extra; filter dead targets in PHP.
			),
			ARRAY_A
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$target_id = absint( $row['target_id'] ?? 0 );
				if ( ! self::is_live_target( $target_id ) ) {
					continue;
				}
				$out[] = array(
					'id'              => absint( $row['id'] ?? 0 ),
					'source_id'       => $source_id,
					'target_id'       => $target_id,
					'anchor_text'     => (string) ( $row['anchor_text'] ?? '' ),
					'type'            => (string) ( $row['type'] ?? self::TYPE_AUTO ),
					'status'          => self::STATUS_APPROVED,
					'relevance_score' => (float) ( $row['relevance_score'] ?? 0 ),
					'context'         => (string) ( $row['context'] ?? '' ),
					'reason'          => (string) ( $row['reason'] ?? '' ),
					'target_url'      => get_permalink( $target_id ),
					'target_title'    => get_the_title( $target_id ),
					'updated_at'      => (string) ( $row['updated_at'] ?? '' ),
				);
				if ( count( $out ) >= $limit ) {
					break;
				}
			}
		}

		wp_cache_set( $cache_key, $out, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $cache_key, $out, self::CACHE_TTL );

		return $out;
	}

	/**
	 * Invalidate cached approved list for a source.
	 *
	 * @param int $source_id Source post ID.
	 */
	public static function bust_cache( int $source_id ): void {
		$source_id = absint( $source_id );
		if ( $source_id < 1 ) {
			return;
		}
		foreach ( array( 5, 10, 20, self::DEFAULT_LIMIT ) as $lim ) {
			$key = self::cache_key( $source_id, (int) $lim );
			wp_cache_delete( $key, self::CACHE_GROUP );
			delete_transient( $key );
		}
		/**
		 * After link-graph cache bust for a source.
		 *
		 * @param int $source_id Source.
		 */
		do_action( 'damavand_link_graph_cache_busted', $source_id );
	}

	/**
	 * Guardian: post permanently deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete_post( int $post_id ): void {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return;
		}
		self::purge_post( $post_id );
	}

	/**
	 * Guardian: leave publish (draft/trash/private/…).
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_transition_post_status( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( $new_status === $old_status ) {
			return;
		}
		if ( 'publish' !== $old_status ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			return;
		}
		if ( ! self::is_graph_post_type( (string) $post->post_type ) ) {
			return;
		}
		self::purge_post( (int) $post->ID );
	}

	/**
	 * Whether table exists.
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ( $found === $table );
	}

	/**
	 * Target is publicly usable.
	 *
	 * @param int $target_id Target ID.
	 */
	public static function is_live_target( int $target_id ): bool {
		$target_id = absint( $target_id );
		if ( $target_id < 1 ) {
			return false;
		}
		$post = get_post( $target_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}
		if ( ! self::is_graph_post_type( (string) $post->post_type ) ) {
			return false;
		}
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::is_410_excluded( $target_id ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Post types allowed in the graph.
	 *
	 * @param string $post_type Type.
	 */
	public static function is_graph_post_type( string $post_type ): bool {
		$types = array( 'post', 'page', 'product' );
		/**
		 * Post types participating in Damavand link graph.
		 *
		 * @param string[] $types Types.
		 */
		$types = apply_filters( 'damavand_link_graph_post_types', $types );
		return in_array( $post_type, (array) $types, true );
	}

	/**
	 * @param string $type Type.
	 */
	private static function sanitize_type( string $type ): string {
		$type = sanitize_key( $type );
		$ok   = array( self::TYPE_AUTO, self::TYPE_MANUAL, self::TYPE_RELATED_BOX );
		return in_array( $type, $ok, true ) ? $type : self::TYPE_AUTO;
	}

	/**
	 * @param string $status Status.
	 */
	private static function sanitize_status( string $status ): string {
		$status = sanitize_key( $status );
		$ok     = array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED );
		return in_array( $status, $ok, true ) ? $status : self::STATUS_PENDING;
	}

	/**
	 * @param string $anchor Anchor.
	 */
	private static function sanitize_anchor( string $anchor ): string {
		$anchor = sanitize_text_field( $anchor );
		if ( strlen( $anchor ) > 191 ) {
			$anchor = substr( $anchor, 0, 191 );
		}
		return $anchor;
	}

	/**
	 * @param int $source_id Source.
	 * @param int $limit     Limit.
	 */
	private static function cache_key( int $source_id, int $limit ): string {
		return 'damavand_lg_' . $source_id . '_l' . $limit;
	}
}
