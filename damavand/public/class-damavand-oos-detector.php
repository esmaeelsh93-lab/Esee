<?php
/**
 * OOS detection: stock hooks, 410 exclusion, noindex, redirects, tracker rows.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_OOS_Detector
 */
class Damavand_OOS_Detector {

	/** @var array<int,true> Parent IDs to sync once after WC variation bulk AJAX. */
	private static $defer_parent_sync = array();

	/**
	 * Queue parent sync during WC variation bulk save (avoid N× loop timeout).
	 *
	 * @param WC_Product $product Variation or parent product.
	 */
	public function defer_variation_parent_sync( WC_Product $product ): void {
		$parent_id = $product->is_type( 'variation' )
			? (int) $product->get_parent_id()
			: (int) $product->get_id();
		if ( $parent_id > 0 ) {
			self::$defer_parent_sync[ $parent_id ] = true;
		}
	}

	/**
	 * Run queued parent OOS sync once per request.
	 */
	public static function flush_deferred_variation_sync(): void {
		if ( empty( self::$defer_parent_sync ) ) {
			return;
		}
		if ( ! class_exists( 'Shojaei_SEO_Helpers' ) || ! Shojaei_SEO_Helpers::is_module_enabled( 'oos' ) ) {
			self::$defer_parent_sync = array();
			return;
		}
		$detector = new self();
		foreach ( array_keys( self::$defer_parent_sync ) as $parent_id ) {
			$detector->sync_variable_parent_oos( (int) $parent_id );
		}
		self::$defer_parent_sync = array();
	}

	/**
	 * Exclude products flagged for sitemap removal (undoable meta).
	 *
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function exclude_from_core_sitemap( array $args, string $post_type ): array {
		if ( 'product' !== $post_type ) {
			return $args;
		}

		$mq = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$mq[] = array(
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
		);
		$args['meta_query'] = $mq;
		$args['post__not_in'] = Shojaei_SEO_Helpers::merge_410_not_in( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array() );

		return $args;
	}

	/**
	 * Keep 410 products out of shop / category / search / widgets.
	 * Direct product URL still loads so template_redirect can return 410.
	 *
	 * @param WP_Query $query Query.
	 */
	public function exclude_410_from_queries( $query ): void {
		if ( ! $query instanceof WP_Query ) {
			return;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( $query->is_singular() ) {
			return;
		}
		if ( ! self::query_targets_products( $query ) ) {
			return;
		}
		$gone = Shojaei_SEO_Helpers::get_410_excluded_ids();
		if ( empty( $gone ) ) {
			return;
		}
		$query->set( 'post__not_in', Shojaei_SEO_Helpers::merge_410_not_in( (array) $query->get( 'post__not_in' ) ) );
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public static function query_targets_products( WP_Query $query ): bool {
		$type = $query->get( 'post_type' );
		if ( 'product' === $type || 'product_variation' === $type ) {
			return true;
		}
		if ( is_array( $type ) && ( in_array( 'product', $type, true ) || in_array( 'product_variation', $type, true ) ) ) {
			return true;
		}
		if ( $query->is_post_type_archive( 'product' ) ) {
			return true;
		}
		if ( $query->is_tax( array( 'product_cat', 'product_tag', 'product_brand' ) ) ) {
			return true;
		}
		if ( $query->is_search() && ( empty( $type ) || 'any' === $type ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param bool $visible    Visible.
	 * @param int  $product_id Product ID.
	 */
	public function hide_410_from_visibility( bool $visible, $product_id ): bool {
		if ( ! $visible ) {
			return false;
		}
		return ! Shojaei_SEO_Helpers::is_410_excluded( (int) $product_id );
	}

	/**
	 * @param array $args Shortcode query args.
	 */
	public function exclude_410_from_shortcode( array $args ): array {
		$args['post__not_in'] = Shojaei_SEO_Helpers::merge_410_not_in( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array() );
		return $args;
	}

	/**
	 * @param array $query Related query.
	 */
	public function exclude_410_from_related_query( array $query ): array {
		if ( ! isset( $query['where'] ) ) {
			$query['where'] = '';
		}
		$ids = Shojaei_SEO_Helpers::get_410_excluded_ids();
		if ( empty( $ids ) ) {
			return $query;
		}
		$query['where'] .= ' AND p.ID NOT IN (' . implode( ',', array_map( 'absint', $ids ) ) . ') ';
		return $query;
	}

	/**
	 * @param int[] $ids Product IDs.
	 * @return int[]
	 */
	public function exclude_410_from_id_list( $ids ): array {
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return Shojaei_SEO_Helpers::strip_410_ids( $ids );
	}

	/**
	 * @param array $wp_query_args WP_Query args.
	 * @param array $query_vars    wc_get_products vars.
	 */
	public function exclude_410_from_wc_get_products( array $wp_query_args, array $query_vars ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $wp_query_args;
		}
		$wp_query_args['post__not_in'] = Shojaei_SEO_Helpers::merge_410_not_in(
			isset( $wp_query_args['post__not_in'] ) ? (array) $wp_query_args['post__not_in'] : array()
		);
		return $wp_query_args;
	}

	/**
	 * Hide a 410 product from catalog/search; keep published so the URL still returns 410.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function hide_410_from_catalog( int $product_id ): void {
		if ( $product_id < 1 || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$prev = (string) $product->get_catalog_visibility();
		if ( 'hidden' !== $prev ) {
			update_post_meta( $product_id, '_damavand_vis_before_410', $prev );
		}
		if ( 'hidden' === $prev ) {
			return;
		}
		$product->set_catalog_visibility( 'hidden' );
		$product->save();
	}

	/**
	 * Restore catalog visibility after undoing 410.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function restore_410_catalog( int $product_id ): void {
		if ( $product_id < 1 || ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$prev = (string) get_post_meta( $product_id, '_damavand_vis_before_410', true );
		if ( '' === $prev || ! in_array( $prev, array( 'visible', 'catalog', 'search', 'hidden' ), true ) ) {
			$prev = 'visible';
		}
		$product->set_catalog_visibility( $prev );
		$product->save();
		delete_post_meta( $product_id, '_damavand_vis_before_410' );
	}

	/**
	 * One-time: hide already-410 products from catalog (they stayed visible before 1.41.1).
	 */
	public function maybe_sync_410_catalog_hide(): void {
		if ( '1' === (string) get_option( 'damavand_seo_410_catalog_sync', '' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$ids = Shojaei_SEO_Helpers::get_410_excluded_ids();
		foreach ( $ids as $id ) {
			self::hide_410_from_catalog( (int) $id );
		}
		update_option( 'damavand_seo_410_catalog_sync', '1', false );
		if ( class_exists( 'Shojaei_SEO_Cache' ) && ! empty( $ids ) ) {
			Shojaei_SEO_Cache::purge_shop_archives( (int) $ids[0] );
		}
	}

	/**
	 * Track stock status changes for simple and variable products.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     New status.
	 * @param object $product    Product object.
	 */
	public function on_stock_status_change( int $product_id, string $status, $product ): void {
		if ( ! $product ) {
			$product = wc_get_product( $product_id );
		}

		if ( ! $product ) {
			return;
		}

		if ( $product->is_type( 'variation' ) ) {
			if ( Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
				$this->defer_variation_parent_sync( $product );
				return;
			}
			$this->sync_variable_parent_oos( (int) $product->get_parent_id() );
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			if ( Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
				$this->defer_variation_parent_sync( $product );
				return;
			}
			$this->sync_variable_parent_oos( $product_id );
			return;
		}

		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}

		if ( Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
			return;
		}

		if ( 'outofstock' === $status ) {
			$this->register_oos( $product_id );
		} else {
			$this->remove_oos_record( $product_id, true );
		}

		if ( class_exists( 'Shojaei_SEO_Events' ) ) {
			Shojaei_SEO_Events::emit( 'stock_changed', $product_id, array( 'status' => $status ) );
		}
	}

	/**
	 * Track variation stock changes and sync parent product.
	 *
	 * @param int    $variation_id Variation ID.
	 * @param string $status       New status.
	 * @param object $variation    Variation object.
	 */
	public function on_variation_stock_change( int $variation_id, string $status, $variation ): void {
		if ( ! $variation ) {
			$variation = wc_get_product( $variation_id );
		}

		if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
			return;
		}

		if ( Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
			$this->defer_variation_parent_sync( $variation );
			return;
		}

		$this->sync_variable_parent_oos( (int) $variation->get_parent_id() );

		if ( class_exists( 'Shojaei_SEO_Events' ) ) {
			Shojaei_SEO_Events::emit(
				'stock_changed',
				(int) $variation->get_parent_id(),
				array(
					'status'    => $status,
					'variation' => $variation_id,
				)
			);
		}
	}

	/**
	 * Sync OOS tracker for a variable product based on all variations.
	 *
	 * @param int $parent_id Parent product ID.
	 */
	public function sync_variable_parent_oos( int $parent_id ): void {
		if ( ! $parent_id ) {
			return;
		}

		$parent = wc_get_product( $parent_id );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return;
		}

		if ( Shojaei_SEO_Helpers::variable_product_has_stock( $parent_id ) ) {
			$this->remove_oos_record( $parent_id, true );
			return;
		}

		$this->register_oos( $parent_id, true );
	}

	/**
	 * Public wrapper to register OOS (used by initial scan).
	 *
	 * @param int  $product_id  Product ID.
	 * @param bool $is_variable Whether variable.
	 */
	public function register_oos_public( int $product_id, bool $is_variable = false ): void {
		$this->register_oos( $product_id, $is_variable, false );
	}

	/**
	 * Recalculate days_oos from oos_date. If start was wrongly set to "today"
	 * (legacy scan), re-estimate from product modified date.
	 *
	 * @param int  $product_id  Product ID.
	 * @param bool $look_orders Scan orders (slow; off for bulk/cron).
	 */
	public function refresh_oos_days_public( int $product_id, bool $look_orders = false ): void {
		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT oos_date, days_oos, status FROM {$table} WHERE product_id = %d",
				$product_id
			)
		);
		if ( ! $record || 'redirected' === $record->status ) {
			return;
		}

		$oos_date = (string) $record->oos_date;
		$now      = (int) current_time( 'timestamp' );
		$age      = $now - (int) strtotime( $oos_date );

		// Junk / Jalali-as-Gregorian, absurd days, or "today" stub from first scan → re-estimate.
		$needs_estimate = ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( $oos_date )
			|| (int) $record->days_oos > 2000
			|| ( $age < ( 2 * DAY_IN_SECONDS ) && (int) $record->days_oos < 2 );

		if ( $needs_estimate ) {
			$guess = self::estimate_oos_started_at( $product_id, $look_orders );
			$gts   = (int) strtotime( $guess );
			$ots   = (int) strtotime( $oos_date );
			// Never move the start date forward (that resets the counter to 0).
			if ( $gts && ( ! $ots || $gts < $ots ) ) {
				$oos_date = $guess;
			} elseif ( ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( $oos_date ) ) {
				$oos_date = $guess;
			}
		}

		$days = Shojaei_SEO_Helpers::days_since_oos( $oos_date );
		$wpdb->update(
			$table,
			array(
				'oos_date' => $oos_date,
				'days_oos' => $days,
			),
			array( 'product_id' => $product_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
		Shojaei_SEO_Helpers::sync_oos_postmeta( $product_id, $oos_date, $days );
	}

	/**
	 * Quantity hit zero (order, admin, REST, invoice API via Woo CRUD).
	 *
	 * @param mixed $product Product.
	 */
	public function on_stock_quantity_change( $product ): void {
		if ( ! $product instanceof WC_Product || ! $product->managing_stock() ) {
			return;
		}
		$qty = $product->get_stock_quantity();
		if ( null === $qty || (float) $qty > 0 ) {
			return;
		}
		if ( Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
			if ( $product->is_type( 'variation' ) || $product->is_type( 'variable' ) ) {
				$this->defer_variation_parent_sync( $product );
			}
			return;
		}
		if ( $product->is_type( 'variation' ) ) {
			$this->sync_variable_parent_oos( (int) $product->get_parent_id() );
			return;
		}
		if ( $product->is_type( 'variable' ) ) {
			$this->sync_variable_parent_oos( (int) $product->get_id() );
			return;
		}
		$this->register_oos( (int) $product->get_id(), false, true );
	}

	/**
	 * Invoice/API plugins that write _stock / _stock_status without Woo CRUD.
	 *
	 * @param int    $meta_id    Meta id.
	 * @param int    $object_id  Post id.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value Value.
	 */
	public function on_stock_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ): void {
		if ( '_stock' !== $meta_key && '_stock_status' !== $meta_key ) {
			return;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}
		$product = wc_get_product( (int) $object_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( Shojaei_SEO_Helpers::is_wc_product_editor_ajax() ) {
			if ( $product->is_type( 'variation' ) || $product->is_type( 'variable' ) ) {
				$this->defer_variation_parent_sync( $product );
			}
			return;
		}
		if ( '_stock_status' === $meta_key ) {
			$this->on_stock_status_change( (int) $object_id, (string) $meta_value, $product );
			return;
		}
		if ( (float) $meta_value > 0 ) {
			return;
		}
		$this->on_stock_quantity_change( $product );
	}

	/**
	 * Register a product as out of stock in tracker.
	 *
	 * @param int  $product_id    Product ID.
	 * @param bool $is_variable   Whether this is a variable product.
	 * @param bool $observed_now  True = stock just hit zero (start clock now). False = historical scan.
	 */
	public function register_oos( int $product_id, bool $is_variable = false, bool $observed_now = true ): void {
		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();

		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( ! $exists ) {
			if ( $observed_now ) {
				$oos_date = Shojaei_SEO_Helpers::mysql_datetime();
				update_post_meta( $product_id, '_shojaei_seo_oos_observed', '1' );
			} else {
				$oos_date = self::estimate_oos_started_at( $product_id, true );
			}
			$days     = Shojaei_SEO_Helpers::days_since_oos( $oos_date );

			$wpdb->insert(
				$table,
				array(
					'product_id' => $product_id,
					'oos_date'   => $oos_date,
					'days_oos'   => $days,
					'status'     => 'temp_oos',
				),
				array( '%d', '%s', '%d', '%s' )
			);

			Shojaei_SEO_Helpers::sync_oos_postmeta( $product_id, $oos_date, $days );

			if ( class_exists( 'Shojaei_SEO_Page_Value' ) ) {
				Shojaei_SEO_Page_Value::get_score( $product_id, true );
			}

			if ( $is_variable ) {
				$title = get_the_title( $product_id );
				Shojaei_SEO_Notifications::add(
					'variable_oos',
					sprintf(
						/* translators: %s: product title */
						__( 'همه variationهای محصول «%s» ناموجود شدند.', 'shojaei-seo-for-woo' ),
						$title
					),
					$product_id
				);
			}

			if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
				Shojaei_SEO_Cache::on_seo_state_change( $product_id );
			}

			if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
				Shojaei_SEO_GSC::notify_product_change( $product_id, 'oos' );
			}
		}
	}

	/**
	 * Best-effort OOS start datetime for products already out of stock before install.
	 *
	 * Woo has no stock-history. Last paid order (lookup table) is the portable proxy:
	 * last time it was still selling. post_modified is ignored when fresh (stock plugins
	 * rewrite it). Never-sold products stay "today" — that is honest, not a fake 30 days.
	 *
	 * @param int  $product_id  Product ID.
	 * @param bool $look_orders Read Woo order lookup (cheap; off on storefront).
	 */
	public static function estimate_oos_started_at( int $product_id, bool $look_orders = true ): string {
		$now = (int) current_time( 'timestamp' );
		$ts  = 0;

		$meta = (string) get_post_meta( $product_id, '_shojaei_seo_oos_date', true );
		if ( $meta && Shojaei_SEO_Helpers::is_plausible_oos_datetime( $meta ) ) {
			$mts = (int) strtotime( $meta );
			if ( $mts && ( $now - $mts ) >= DAY_IN_SECONDS ) {
				$ts = $mts;
			}
		}

		if ( $look_orders ) {
			$order_ts = Damavand_OOS_Order_Lookup::last_paid_order_timestamp( $product_id, true );
			if ( $order_ts > 0 && ( ! $ts || $order_ts < $ts ) ) {
				$ts = $order_ts;
			}
		}

		// Only use last catalog edit if it is already old (not a stock-zero plugin touch).
		$post = get_post( $product_id );
		if ( $post && ! empty( $post->post_modified ) && Shojaei_SEO_Helpers::is_plausible_oos_datetime( (string) $post->post_modified ) ) {
			$mod = (int) strtotime( $post->post_modified );
			if ( $mod && ( $now - $mod ) >= ( 7 * DAY_IN_SECONDS ) && ( ! $ts || $mod < $ts ) ) {
				$ts = $mod;
			}
		}

		if ( $ts < 1 ) {
			$ts = $now;
		}
		if ( $ts > $now ) {
			$ts = $now;
		}

		$max_age = (int) ( 180 * DAY_IN_SECONDS );
		if ( ( $now - $ts ) > $max_age ) {
			$ts = $now - $max_age;
		}

		return Shojaei_SEO_Helpers::mysql_datetime( $ts );
	}

	/**
	 * Remove product from OOS tracker.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $notify     Send back-in-stock notification.
	 */
	public function remove_oos_record( int $product_id, bool $notify = false ): void {
		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( ! $exists ) {
			return;
		}

		$wpdb->delete( $table, array( 'product_id' => $product_id ), array( '%d' ) );

		Shojaei_SEO_Helpers::clear_oos_postmeta( $product_id );

		if ( $notify ) {
			$title = get_the_title( $product_id );
			Shojaei_SEO_Notifications::add(
				'back_in_stock',
				sprintf(
					/* translators: %s: product title */
					__( 'محصول «%s» دوباره موجود شد و از لیست ناموجود حذف شد.', 'shojaei-seo-for-woo' ),
					$title
				),
				$product_id
			);
			Damavand_OOS_Notifier::notify_restock_subscribers( $product_id );
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::on_seo_state_change( $product_id );
		}

		if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
			Shojaei_SEO_GSC::notify_product_change( $product_id, 'oos' );
		}
	}

	/**
	 * Add noindex for long-term out-of-stock products (phase 2+).
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function maybe_noindex_oos_product( array $robots ): array {
		if ( ! is_singular( 'product' ) ) {
			return $robots;
		}

		$product = Shojaei_SEO_OOS_Manager::current_product();
		if ( ! $product ) {
			return $robots;
		}

		$product_id = (int) $product->get_id();

		// Prefer persisted undoable flag (set by Rule Engine sync / cron).
		// Never call Rule Engine on the frontend — evaluate_product is too heavy for TTFB.
		$flag = get_post_meta( $product_id, '_shojaei_seo_noindex', true );
		if ( 'yes' === $flag ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
			return $robots;
		}
		if ( 'no' === $flag ) {
			return $robots;
		}

		// Lightweight tracker fallback when flag not yet synced.
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_enabled', 'yes' ) ) {
			return $robots;
		}
		if ( $product->is_in_stock() ) {
			return $robots;
		}

		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT days_oos FROM {$table} WHERE product_id = %d", $product_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$days  = $record ? (int) $record->days_oos : 0;
		$phase = Shojaei_SEO_Helpers::get_oos_phase( $days );
		if ( $phase >= Shojaei_SEO_Helpers::get_noindex_from_phase() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}

		return $robots;
	}

	/**
	 * Handle 301/302 redirects and 410 Gone responses.
	 */
	public function handle_redirects(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		global $wpdb;
		$product_id = get_the_ID();
		$table      = Shojaei_SEO_Helpers::oos_table();

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d AND status = 'redirected'",
				$product_id
			)
		);

		if ( ! $record ) {
			return;
		}

		if ( '410' === $record->redirect_type ) {
			if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
				Shojaei_SEO_Cache::do_not_cache();
			}
			status_header( 410 );
			nocache_headers();
			wp_die(
				esc_html__( 'این محصول دیگر در فروشگاه موجود نیست و به‌طور دائمی حذف شده است.', 'shojaei-seo-for-woo' ),
				esc_html__( 'محصول حذف شده (410 Gone)', 'shojaei-seo-for-woo' ),
				array( 'response' => 410 )
			);
		}

		if ( in_array( $record->redirect_type, array( '301', '302' ), true ) && ! empty( $record->target_url ) ) {
			if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
				Shojaei_SEO_Cache::do_not_cache();
			}
			$code = '301' === $record->redirect_type ? 301 : 302;
			$target = esc_url_raw( (string) $record->target_url );
			if ( $target ) {
				wp_safe_redirect( $target, $code );
				exit;
			}
		}
	}

	/**
	 * Make out-of-stock products unpurchasable.
	 *
	 * @param bool       $purchasable Whether purchasable.
	 * @param WC_Product $product     Product object.
	 * @return bool
	 */
	public function make_unpurchasable( bool $purchasable, $product ): bool {
		if ( ! $product instanceof WC_Product ) {
			return $purchasable;
		}
		if ( Shojaei_SEO_Helpers::is_410_excluded( (int) $product->get_id() ) ) {
			return false;
		}
		if ( ! $product->is_in_stock() ) {
			return false;
		}
		return $purchasable;
	}
}
