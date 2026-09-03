<?php
/**
 * Event-driven SEO Operations — react to product changes instead of blind heavy scans.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Events
 */
class Shojaei_SEO_Events {

	public const HOOK_REACT = 'shojaei_seo_as_react_event';
	public const ACTION     = 'shojaei_seo_event';

	/** @var array<int,true> Request-local dedupe. */
	private static array $done = array();

	/**
	 * Constructor — register listeners.
	 */
	public function __construct() {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_event_driven', 'yes' ) ) {
			return;
		}

		// Central bus (emitted by OOS Manager and others).
		add_action( self::ACTION, array( $this, 'on_bus_event' ), 10, 3 );
		add_action( self::HOOK_REACT, array( $this, 'as_react' ), 10, 3 );

		// Publish / unpublish / trash / delete.
		add_action( 'transition_post_status', array( $this, 'on_transition_status' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete' ), 20, 1 );
		add_action( 'wp_trash_post', array( $this, 'on_trash' ), 20, 1 );

		// Product save / price / props (WooCommerce).
		add_action( 'woocommerce_update_product', array( $this, 'on_product_updated' ), 25, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'on_product_updated' ), 25, 1 );
		add_action( 'woocommerce_product_object_updated_props', array( $this, 'on_product_props' ), 20, 2 );

		// Category / tag / brand terms.
		add_action( 'set_object_terms', array( $this, 'on_set_object_terms' ), 20, 6 );

		// Replacement candidate became relevant: in-stock product saved.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_possible_replacement_stock' ), 30, 3 );
	}

	/**
	 * Whether event-driven mode is on.
	 */
	public static function is_enabled(): bool {
		return 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_event_driven', 'yes' );
	}

	/**
	 * Emit a domain event (sync schedule / async handle).
	 *
	 * @param string $event      Event slug.
	 * @param int    $product_id Product ID.
	 * @param array  $payload    Extra data.
	 */
	public static function emit( string $event, int $product_id, array $payload = array() ): void {
		if ( ! $product_id || ! self::is_enabled() ) {
			return;
		}

		/**
		 * Fires for every Shojaei SEO operational event.
		 *
		 * @param string $event Event.
		 * @param int    $product_id Product ID.
		 * @param array  $payload Payload.
		 */
		do_action( self::ACTION, $event, $product_id, $payload );
	}

	/**
	 * Bus listener — debounce and queue reaction.
	 *
	 * @param string $event      Event.
	 * @param int    $product_id Product ID.
	 * @param array  $payload    Payload.
	 */
	public function on_bus_event( string $event, int $product_id, array $payload = array() ): void {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$key = $product_id; // One reaction window per product (stock+save+props coalesce).
		if ( isset( self::$done[ $key ] ) ) {
			return;
		}
		self::$done[ $key ] = true;

		// Debounce floods from importers / bulk edits.
		$transient = 'shojaei_seo_evt_p_' . $product_id;
		if ( get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, $event, 30 );

		$delay = in_array( $event, array( 'stock_changed', 'published', 'trashed', 'deleted' ), true ) ? 0 : 3;

		if ( class_exists( 'Shojaei_SEO_Queue' ) && Shojaei_SEO_Queue::has_action_scheduler() ) {
			as_schedule_single_action(
				time() + $delay,
				self::HOOK_REACT,
				array( $event, $product_id, $payload ),
				'shojaei-seo'
			);
			return;
		}

		if ( 0 === $delay ) {
			$this->react( $event, $product_id, $payload );
			return;
		}

		wp_schedule_single_event( time() + $delay, self::HOOK_REACT, array( $event, $product_id, $payload ) );
	}

	/**
	 * AS / cron callback.
	 *
	 * @param string $event      Event.
	 * @param int    $product_id Product ID.
	 * @param array  $payload    Payload.
	 */
	public function as_react( $event, $product_id = 0, $payload = array() ): void {
		$this->react( (string) $event, absint( $product_id ), is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Apply Rule Engine + light side effects for one product.
	 *
	 * @param string $event      Event.
	 * @param int    $product_id Product ID.
	 * @param array  $payload    Payload.
	 */
	public function react( string $event, int $product_id, array $payload = array() ): void {
		if ( ! $product_id || ! Shojaei_SEO_Helpers::is_module_enabled( 'oos' ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product && ! in_array( $event, array( 'deleted', 'trashed' ), true ) ) {
			return;
		}

		if ( in_array( $event, array( 'deleted', 'trashed' ), true ) ) {
			$url = (string) ( $payload['url'] ?? '' );
			$this->cleanup_tracker( $product_id );
			if ( $url && class_exists( 'Shojaei_SEO_GSC' ) ) {
				Shojaei_SEO_GSC::enqueue_indexing( $url, 'URL_DELETED' );
			}
			$this->log_event( $event, $product_id, array( 'cleaned' => true ) );
			return;
		}

		if ( ! class_exists( 'Shojaei_SEO_Rule_Engine' ) ) {
			return;
		}

		$decision = Shojaei_SEO_Rule_Engine::evaluate_product( $product_id );
		if ( ! $decision ) {
			return;
		}

		Shojaei_SEO_Rule_Engine::sync_decision_meta( $product_id, $decision );
		update_post_meta( $product_id, '_shojaei_seo_last_event_at', time() );

		// Sync suggested tracker status from rules (without heavy redirect apply here).
		$this->sync_tracker_status( $product_id, $decision );

		if ( $decision->enqueue_auto_redirect ) {
			Shojaei_SEO_Queue::enqueue_oos_process( $product_id );
		}

		// IndexNow / GSC when structural SEO state may have changed.
		if ( in_array( $event, array( 'stock_changed', 'published', 'unpublished', 'terms_changed', 'price_changed', 'replacement_pool_changed' ), true ) ) {
			if ( class_exists( 'Shojaei_SEO_GSC' ) && method_exists( 'Shojaei_SEO_GSC', 'is_ready' ) && Shojaei_SEO_GSC::is_ready() ) {
				Shojaei_SEO_GSC::notify_product_change( $product_id, 'oos' );
			}
		}

		$this->log_event( $event, $product_id, array(
			'primary' => $decision->primary_action,
			'mode'    => $decision->redirect_apply_mode,
		) );

		/**
		 * After an event reaction completed.
		 *
		 * @param string                      $event Event.
		 * @param int                         $product_id Product.
		 * @param Shojaei_SEO_Rule_Decision   $decision Decision.
		 * @param array                       $payload Payload.
		 */
		do_action( 'shojaei_seo_event_reacted', $event, $product_id, $decision, $payload );
	}

	/**
	 * transition_post_status for products.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public function on_transition_status( string $new_status, string $old_status, $post ): void {
		if ( ! $post || 'product' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			self::emit( 'published', (int) $post->ID, array( 'from' => $old_status ) );
		} elseif ( 'publish' === $old_status ) {
			self::emit( 'unpublished', (int) $post->ID, array( 'to' => $new_status ) );
		}
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function on_before_delete( int $post_id ): void {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		$url = get_permalink( $post_id );
		self::emit( 'deleted', $post_id, array( 'url' => $url ? (string) $url : '' ) );
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function on_trash( int $post_id ): void {
		if ( 'product' !== get_post_type( $post_id ) ) {
			return;
		}
		$url = get_permalink( $post_id );
		self::emit( 'trashed', $post_id, array( 'url' => $url ? (string) $url : '' ) );
	}

	/**
	 * Product created/updated.
	 *
	 * @param int $product_id Product ID.
	 */
	public function on_product_updated( int $product_id ): void {
		self::emit( 'product_updated', $product_id );
	}

	/**
	 * Detect price / catalog visibility prop changes.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $updated Updated props.
	 */
	public function on_product_props( $product, $updated ): void {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! is_array( $updated ) ) {
			return;
		}

		$watch = array( 'regular_price', 'sale_price', 'price', 'catalog_visibility', 'status', 'stock_status' );
		$hit   = array_intersect( $watch, $updated );
		if ( empty( $hit ) ) {
			return;
		}

		$event = in_array( 'stock_status', $hit, true ) ? 'stock_changed' : 'price_changed';
		if ( in_array( 'status', $hit, true ) || in_array( 'catalog_visibility', $hit, true ) ) {
			$event = 'product_updated';
		}

		self::emit( $event, (int) $product->get_id(), array( 'props' => array_values( $hit ) ) );
	}

	/**
	 * Category / tag / brand assignment changed.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Terms.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy.
	 * @param bool   $append     Append.
	 * @param array  $old_tt_ids Old.
	 */
	public function on_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
		$object_id = absint( $object_id );
		if ( ! $object_id || 'product' !== get_post_type( $object_id ) ) {
			return;
		}

		$watched = array( 'product_cat', 'product_tag', 'product_brand', 'pwb-brand' );
		if ( ! in_array( (string) $taxonomy, $watched, true ) ) {
			return;
		}

		self::emit(
			'terms_changed',
			$object_id,
			array(
				'taxonomy' => (string) $taxonomy,
			)
		);
	}

	/**
	 * When a product becomes in-stock, lightly re-check a few OOS siblings (replacement pool changed).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Status.
	 * @param mixed  $product    Product.
	 */
	public function on_possible_replacement_stock( int $product_id, string $status, $product ): void {
		if ( 'outofstock' === $status ) {
			return;
		}

		$product = $product ?: wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return;
		}

		// Emit for this product (replacement candidate updated).
		self::emit( 'replacement_updated', $product_id, array( 'status' => $status ) );

		// Queue tiny sibling re-eval (max 5) — not a full catalog scan.
		$cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( empty( $cats ) || is_wp_error( $cats ) ) {
			return;
		}

		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.product_id FROM {$table} t
				INNER JOIN {$wpdb->term_relationships} tr ON t.product_id = tr.object_id
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
					AND tt.taxonomy = 'product_cat' AND tt.term_id = %d
				WHERE t.status IN ('temp_oos','permanent_oos','candidate_redirect','needs_manual')
					AND t.product_id != %d
				ORDER BY t.days_oos DESC
				LIMIT 5",
				(int) $cats[0],
				$product_id
			)
		);

		foreach ( (array) $ids as $oos_id ) {
			self::emit( 'replacement_pool_changed', (int) $oos_id, array( 'source' => $product_id ) );
		}
	}

	/**
	 * Update tracker status from decision (light).
	 *
	 * @param int                       $product_id Product ID.
	 * @param Shojaei_SEO_Rule_Decision $decision   Decision.
	 */
	private function sync_tracker_status( int $product_id, Shojaei_SEO_Rule_Decision $decision ): void {
		if ( ! $decision->suggested_status ) {
			return;
		}

		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, status FROM {$table} WHERE product_id = %d", $product_id )
		);
		if ( ! $record || 'redirected' === $record->status || 'needs_manual' === $record->status ) {
			return;
		}

		if ( $record->status === $decision->suggested_status ) {
			return;
		}

		$wpdb->update(
			$table,
			array( 'status' => $decision->suggested_status ),
			array( 'id' => $record->id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Remove tracker row for deleted products.
	 *
	 * @param int $product_id Product ID.
	 */
	private function cleanup_tracker( int $product_id ): void {
		global $wpdb;
		$wpdb->delete( Shojaei_SEO_Helpers::oos_table(), array( 'product_id' => $product_id ), array( '%d' ) );
		delete_post_meta( $product_id, '_shojaei_seo_oos_date' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_lifecycle' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_days' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_observed' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_probed' );
		delete_post_meta( $product_id, '_shojaei_seo_rule_action' );
		delete_post_meta( $product_id, '_shojaei_seo_link_deprioritized' );
		delete_post_meta( $product_id, '_shojaei_seo_sitemap_exclude' );
	}

	/**
	 * @param string $event Event.
	 * @param int    $product_id Product.
	 * @param array  $extra Extra.
	 */
	private function log_event( string $event, int $product_id, array $extra = array() ): void {
		if ( ! class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			return;
		}
		Shojaei_SEO_Activity_Log::add(
			'event_' . $event,
			sprintf(
				/* translators: 1: event, 2: product id */
				__( 'رویداد «%1$s» برای محصول #%2$d', 'shojaei-seo-for-woo' ),
				$event,
				$product_id
			),
			$product_id,
			$extra
		);
	}
}
