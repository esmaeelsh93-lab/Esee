<?php
/**
 * OOS order / last-sale lookup helpers.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_OOS_Order_Lookup
 */
class Damavand_OOS_Order_Lookup {

	/**
	 * Last paid order datetime for a product (proxy for "still selling / not yet OOS").
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $skip_cache Skip transient cache.
	 */
	public static function last_paid_order_timestamp( int $product_id, bool $skip_cache = false ): int {
		if ( $product_id < 1 ) {
			return 0;
		}
		if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() ) {
			return 0;
		}

		$cache_key = 'damavand_last_paid_v2_' . $product_id;
		if ( ! $skip_cache ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		$ts = self::query_last_paid_order_timestamp( $product_id );
		set_transient( $cache_key, $ts, $ts > 0 ? 12 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS );
		return $ts;
	}

	/**
	 * Uncached last-sale lookup. Lookup table first; if empty, real order items.
	 * Never stop at an empty analytics table — many shops never filled it.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function query_last_paid_order_timestamp( int $product_id ): int {
		global $wpdb;

		$ids = self::sale_lookup_ids( $product_id );
		if ( empty( $ids ) ) {
			return 0;
		}
		$in_ids = implode( ',', array_map( 'intval', $ids ) );
		$paid   = array(
			'wc-completed',
			'wc-processing',
			'wc-on-hold',
			'completed',
			'processing',
			'on-hold',
		);
		$in_st  = implode( ',', array_fill( 0, count( $paid ), '%s' ) );

		$lookup = $wpdb->prefix . 'wc_order_product_lookup';
		if ( self::table_exists( $lookup ) ) {
			$date = $wpdb->get_var(
				"SELECT MAX(date_created) FROM {$lookup}
				WHERE product_id IN ({$in_ids}) OR variation_id IN ({$in_ids})"
			);
			$ts = self::order_datetime_to_ts( $date );
			if ( $ts > 0 ) {
				return $ts;
			}
		}

		$items = $wpdb->prefix . 'woocommerce_order_items';
		$meta  = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$posts = $wpdb->posts;
		$sql   = $wpdb->prepare(
			"SELECT p.post_date FROM {$items} oi
			INNER JOIN {$meta} oim ON oim.order_item_id = oi.order_item_id
				AND oim.meta_key IN ('_product_id','_variation_id')
			INNER JOIN {$posts} p ON p.ID = oi.order_id AND p.post_type = 'shop_order'
			WHERE oim.meta_value IN ({$in_ids}) AND p.post_status IN ($in_st)
			ORDER BY p.post_date DESC LIMIT 1",
			$paid
		);
		$ts = self::order_datetime_to_ts( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $ts > 0 ) {
			return $ts;
		}

		$orders = $wpdb->prefix . 'wc_orders';
		if ( self::table_exists( $orders ) ) {
			$sql = $wpdb->prepare(
				"SELECT o.date_created_gmt FROM {$items} oi
				INNER JOIN {$meta} oim ON oim.order_item_id = oi.order_item_id
					AND oim.meta_key IN ('_product_id','_variation_id')
				INNER JOIN {$orders} o ON o.id = oi.order_id
				WHERE oim.meta_value IN ({$in_ids}) AND o.status IN ($in_st)
				ORDER BY o.date_created_gmt DESC LIMIT 1",
				$paid
			);
			$ts = self::order_datetime_to_ts( $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( $ts > 0 ) {
				return $ts;
			}
		}

		return 0;
	}

	/**
	 * Parent + variation IDs for order-line matching.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public static function sale_lookup_ids( int $product_id ): array {
		global $wpdb;
		$ids = array( $product_id );
		$children = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_parent = %d AND post_type = 'product_variation' AND post_status != 'trash'
				LIMIT 80",
				$product_id
			)
		);
		if ( is_array( $children ) ) {
			foreach ( $children as $cid ) {
				$ids[] = (int) $cid;
			}
		}
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * @param mixed $date Datetime string.
	 */
	public static function order_datetime_to_ts( $date ): int {
		if ( ! is_string( $date ) || '' === trim( $date ) ) {
			return 0;
		}
		$date = trim( $date );
		if ( preg_match( '/^(\d{4})-/', $date, $m ) ) {
			$year = (int) $m[1];
			if ( $year < 2000 || $year > 2100 ) {
				return 0;
			}
		}
		$ts = (int) strtotime( $date );
		return $ts > 0 ? $ts : 0;
	}

	/**
	 * @param string $table Full table name.
	 */
	public static function table_exists( string $table ): bool {
		static $cache = array();
		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}
		global $wpdb;
		$like  = $wpdb->esc_like( $table );
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		$cache[ $table ] = ( $found === $table );
		return $cache[ $table ];
	}
}
