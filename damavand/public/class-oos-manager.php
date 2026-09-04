<?php
/**
 * Out-of-Stock product lifecycle manager.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_OOS_Manager
 */
class Shojaei_SEO_OOS_Manager {

	/** @var bool */
	private static $hooks_registered = false;

	/**
	 * Constructor.
	 *
	 * @param bool $register_hooks False when only calling methods (scan/AJAX) — do not re-attach front hooks.
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks || self::$hooks_registered ) {
			return;
		}
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'oos' ) ) {
			return;
		}
		self::$hooks_registered = true;

		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_status_change' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_variation_stock_change' ), 10, 3 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_quantity_change' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock_quantity_change' ), 10, 1 );
		add_action( 'updated_post_meta', array( $this, 'on_stock_meta_updated' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'on_stock_meta_updated' ), 10, 4 );
		add_action( 'template_redirect', array( $this, 'handle_redirects' ) );
		add_action( 'pre_get_posts', array( $this, 'exclude_410_from_queries' ), 11 );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'hide_410_from_visibility' ), 10, 2 );
		add_filter( 'woocommerce_shortcode_products_query', array( $this, 'exclude_410_from_shortcode' ) );
		add_filter( 'woocommerce_product_related_posts_query', array( $this, 'exclude_410_from_related_query' ) );
		add_filter( 'woocommerce_related_products', array( $this, 'exclude_410_from_id_list' ) );
		add_filter( 'woocommerce_product_get_upsell_ids', array( $this, 'exclude_410_from_id_list' ) );
		add_filter( 'woocommerce_product_get_cross_sell_ids', array( $this, 'exclude_410_from_id_list' ) );
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'exclude_410_from_wc_get_products' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'maybe_sync_410_catalog_hide' ) );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'make_unpurchasable' ), 10, 2 );
		// قالب‌های کلاسیک.
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_oos_notice' ), 25 );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_related_products_section' ), 5 );
		add_action( 'woocommerce_after_single_product', array( $this, 'render_related_products_section' ), 12 );
		// قالب‌های Elementor / بلوکی / سفارشی که فقط HTML موجودی را چاپ می‌کنند.
		add_filter( 'woocommerce_get_stock_html', array( $this, 'append_oos_ui_to_stock_html' ), 30, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'wp_robots', array( $this, 'maybe_noindex_oos_product' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'exclude_from_core_sitemap' ), 10, 2 );
		add_action( 'wp_ajax_shojaei_seo_oos_notify', array( $this, 'ajax_restock_notify' ) );
		add_action( 'wp_ajax_nopriv_shojaei_seo_oos_notify', array( $this, 'ajax_restock_notify' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'register_admin_metabox' ) );
		}

		add_action( 'shutdown', array( __CLASS__, 'flush_deferred_variation_sync' ), 1 );
	}

	/**
	 * Queue parent sync during WC variation bulk save (avoid N× loop timeout).
	 *
	 * @param WC_Product $product Variation or parent product.
	 */
	private function defer_variation_parent_sync( WC_Product $product ): void {
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
		$manager = new self( false );
		foreach ( array_keys( self::$defer_parent_sync ) as $parent_id ) {
			$manager->sync_variable_parent_oos( (int) $parent_id );
		}
		self::$defer_parent_sync = array();
	}

	/** @var bool */
	private static $notice_rendered = false;

	/** @var bool */
	private static $related_rendered = false;

	/** @var array<int,true> Parent IDs to sync once after WC variation bulk AJAX. */
	private static $defer_parent_sync = array();

	/**
	 * Current product (Elementor/custom templates may not set global $product).
	 */
	private static function current_product(): ?WC_Product {
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$id = (int) get_the_ID();
		if ( $id < 1 ) {
			return null;
		}
		$obj = wc_get_product( $id );
		return $obj instanceof WC_Product ? $obj : null;
	}

	/**
	 * متاباکس ادمین: وضعیت سئوی ناموجودی روی ویرایش محصول.
	 */
	public function register_admin_metabox(): void {
		add_meta_box(
			'damavand_oos_status',
			__( 'موجودی و سئو (Damavand)', 'shojaei-seo-for-woo' ),
			array( $this, 'render_admin_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_admin_metabox( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
		if ( ! $product ) {
			echo '<p>' . esc_html__( 'محصول ووکامرس یافت نشد.', 'shojaei-seo-for-woo' ) . '</p>';
			return;
		}

		$in_stock = $product->is_in_stock();
		$emails   = get_post_meta( $post->ID, '_shojaei_seo_restock_emails', true );
		$waiters  = is_array( $emails ) ? count( $emails ) : 0;
		$notify   = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ) );
		$oos_url  = admin_url( 'admin.php?page=shojaei-seo&tab=oos' );

		if ( $in_stock ) {
			echo '<p><strong class="shojaei-tone-safe">' . esc_html__( 'موجود', 'shojaei-seo-for-woo' ) . '</strong></p>';
			if ( $waiters > 0 ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %d: count */
						__( '%d نفر برای خبر موجود شدن ثبت‌نام کرده‌اند (با برگشت موجودی ایمیل می‌گیرند).', 'shojaei-seo-for-woo' ),
						$waiters
					)
				) . '</p>';
			}
			echo '<p><a href="' . esc_url( $oos_url ) . '">' . esc_html__( 'مرکز ناموجودی', 'shojaei-seo-for-woo' ) . '</a></p>';
			return;
		}

		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $post->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$days   = $record ? (int) $record->days_oos : 0;
		$state  = Shojaei_SEO_Helpers::get_oos_state( $days );
		$phase  = (int) ( $state['phase'] ?? 0 );
		$labels = array(
			1 => __( 'فاز ۱ — موقت', 'shojaei-seo-for-woo' ),
			2 => __( 'فاز ۲ — کم‌احتمال', 'shojaei-seo-for-woo' ),
			3 => __( 'فاز ۳ — نهایی / کاندید ریدایرکت', 'shojaei-seo-for-woo' ),
		);
		$phase_label = $labels[ $phase ] ?? __( 'ناموجود (بدون رکورد چرخه)', 'shojaei-seo-for-woo' );

		$related = $this->get_related_in_stock_products( (int) $post->ID, 3 );
		$plan    = self::build_quick_redirect_plan_static( $product );

		echo '<p><strong class="shojaei-tone-warning">' . esc_html__( 'ناموجود', 'shojaei-seo-for-woo' ) . '</strong></p>';
		echo '<ul style="margin:0 0 8px 1.1em;padding:0;">';
		echo '<li>' . esc_html( $phase_label ) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: days */
				__( 'روز ناموجودی: %d', 'shojaei-seo-for-woo' ),
				$days
			)
		) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: count */
				__( 'لیست خبرم‌کن: %d نفر', 'shojaei-seo-for-woo' ),
				$waiters
			)
		) . ( $notify ? '' : ' — ' . esc_html__( 'اطلاع‌رسانی خاموش است', 'shojaei-seo-for-woo' ) ) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: count */
				__( 'پیشنهاد جایگزین موجود: %d', 'shojaei-seo-for-woo' ),
				count( $related )
			)
		) . '</li>';
		if ( ! empty( $plan['target_url'] ) ) {
			echo '<li>' . esc_html__( 'مقصد پیشنهادی ریدایرکت:', 'shojaei-seo-for-woo' ) . ' <code dir="ltr">' . esc_html( (string) $plan['target_url'] ) . '</code></li>';
		}
		echo '</ul>';
		echo '<p><a class="button button-small" href="' . esc_url( $oos_url ) . '">' . esc_html__( 'اقدام در مرکز ناموجودی', 'shojaei-seo-for-woo' ) . '</a></p>';
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
	private static function query_targets_products( WP_Query $query ): bool {
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
	private function register_oos( int $product_id, bool $is_variable = false, bool $observed_now = true ): void {
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
			$order_ts = self::last_paid_order_timestamp( $product_id, true );
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
	 * Last paid order datetime for a product (proxy for "still selling / not yet OOS").
	 *
	 * @param int $product_id Product ID.
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
	private static function query_last_paid_order_timestamp( int $product_id ): int {
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
	private static function sale_lookup_ids( int $product_id ): array {
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
	private static function order_datetime_to_ts( $date ): int {
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
	private static function table_exists( string $table ): bool {
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

	/**
	 * Remove product from OOS tracker.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $notify     Send back-in-stock notification.
	 */
	private function remove_oos_record( int $product_id, bool $notify = false ): void {
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
			$this->notify_restock_subscribers( $product_id );
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

		$product = self::current_product();
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
	 * @param bool        $purchasable Whether purchasable.
	 * @param WC_Product  $product     Product object.
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

	/**
	 * Render OOS notice on single product page (3-phase copy from cycle settings).
	 */
	public function render_oos_notice(): void {
		$product = self::current_product();
		if ( ! $product ) {
			return;
		}
		$this->print_oos_notice( $product );
	}

	/**
	 * برای قالب‌هایی که هوک خلاصه محصول را ندارند — چسباندن به HTML موجودی ووکامرس.
	 *
	 * @param string     $html    Stock HTML.
	 * @param WC_Product $product Product.
	 */
	public function append_oos_ui_to_stock_html( $html, $product ): string {
		if ( ! $product instanceof WC_Product || $product->is_in_stock() ) {
			return (string) $html;
		}
		if ( ! is_product() ) {
			return (string) $html;
		}

		ob_start();
		$this->print_oos_notice( $product );
		// اگر قالب after_summary ندارد، پیشنهادها را هم همین‌جا بگذار.
		$this->print_related_products_section( $product );
		$extra = (string) ob_get_clean();

		return (string) $html . $extra;
	}

	/**
	 * @param WC_Product $product Product.
	 */
	private function print_oos_notice( WC_Product $product ): void {
		if ( self::$notice_rendered || $product->is_in_stock() ) {
			return;
		}
		self::$notice_rendered = true;

		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product->get_id() )
		);

		$days      = $record ? (int) $record->days_oos : 0;
		$state     = Shojaei_SEO_Helpers::get_oos_state( $days );
		$copy      = Shojaei_SEO_Helpers::get_oos_front_copy( (string) $state['message_key'] );
		$notify_on = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ) );
		?>
		<div class="<?php echo esc_attr( $copy['css'] ); ?>" data-oos-phase="<?php echo esc_attr( (string) (int) $state['phase'] ); ?>" data-oos-days="<?php echo esc_attr( (string) $days ); ?>" data-damavand-oos="1">
			<p class="shojaei-oos-title"><strong><?php echo esc_html( $copy['title'] ); ?></strong></p>
			<p><?php echo esc_html( $copy['body'] ); ?></p>
			<a href="#shojaei-related-products" class="shojaei-oos-similar-btn button alt">
				<?php echo esc_html( $copy['cta'] ); ?>
			</a>
			<?php if ( $notify_on && 'final' !== $state['message_key'] ) : ?>
				<form class="shojaei-oos-notify-form" id="shojaei-oos-notify-form" action="#" method="post">
					<label for="shojaei-oos-notify-email"><?php esc_html_e( 'خبرم کن وقتی موجود شد', 'shojaei-seo-for-woo' ); ?></label>
					<div class="shojaei-oos-notify-row">
						<input type="email" id="shojaei-oos-notify-email" name="email" required placeholder="<?php esc_attr_e( 'ایمیل شما', 'shojaei-seo-for-woo' ); ?>" autocomplete="email" />
						<button type="submit" class="button"><?php esc_html_e( 'خبرم کن', 'shojaei-seo-for-woo' ); ?></button>
					</div>
					<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" />
					<p class="shojaei-oos-notify-status" id="shojaei-oos-notify-status" aria-live="polite"></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render related in-stock products (similarity ≥ threshold, else same category).
	 */
	public function render_related_products_section(): void {
		$product = self::current_product();
		if ( ! $product ) {
			return;
		}
		$this->print_related_products_section( $product );
	}

	/**
	 * @param WC_Product $product Product.
	 */
	private function print_related_products_section( WC_Product $product ): void {
		if ( self::$related_rendered || $product->is_in_stock() ) {
			return;
		}
		self::$related_rendered = true;

		$limit   = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_related_limit', 4 );
		$limit   = max( 2, min( 8, $limit ) );
		$related = $this->get_related_in_stock_products( $product->get_id(), $limit );

		if ( empty( $related ) ) {
			$cat_url = Shojaei_SEO_Helpers::get_primary_category_url( $product->get_id() );
			if ( $cat_url ) {
				echo '<section class="shojaei-related-products shojaei-related-products--fallback" id="shojaei-related-products" data-damavand-oos="1">';
				echo '<h2>' . esc_html__( 'مشاهده محصولات هم‌دسته', 'shojaei-seo-for-woo' ) . '</h2>';
				echo '<p><a class="button alt" href="' . esc_url( $cat_url ) . '">' . esc_html__( 'رفتن به دسته مرتبط', 'shojaei-seo-for-woo' ) . '</a></p>';
				echo '</section>';
			}
			return;
		}

		$heading = __( 'محصولات مشابه موجود', 'shojaei-seo-for-woo' );
		$days    = 0;
		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT days_oos FROM {$table} WHERE product_id = %d", $product->get_id() )
		);
		if ( $record ) {
			$days = (int) $record->days_oos;
		}
		$state = Shojaei_SEO_Helpers::get_oos_state( $days );
		if ( in_array( $state['message_key'], array( 'unlikely', 'final', 'long_term' ), true ) ) {
			$heading = __( 'پیشنهادهای جایگزین برای شما', 'shojaei-seo-for-woo' );
		}
		$cols = min( 4, max( 2, $limit ) );
		?>
		<div id="shojaei-related-products" class="shojaei-related-products" data-damavand-oos="1">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<ul class="products columns-<?php echo esc_attr( (string) $cols ); ?>">
				<?php
				foreach ( $related as $related_product ) {
					$post_object = get_post( $related_product->get_id() );
					setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					wc_get_template_part( 'content', 'product' );
				}
				wp_reset_postdata();
				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * تعداد پیشنهاد ناموجود (جدا از «محصولات مکمل»).
	 */
	public static function related_limit(): int {
		$limit = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_related_limit', 4 );
		return max( 2, min( 8, $limit ) );
	}

	/**
	 * Related in-stock products: score ≥ match threshold, then category fill to $limit.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Max products.
	 * @return WC_Product[]
	 */
	public function get_related_in_stock_products( int $product_id, int $limit = 5 ): array {
		$limit   = max( 1, min( 12, $limit ) );
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$threshold = max( 1, min( 100, (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_match_threshold', 70 ) ) );
		$rows      = array();

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			$rows = Shojaei_SEO_Redirect_Engine::find_top_replacements( $product, $limit, $threshold );
		}

		$products = array();
		foreach ( $rows as $row ) {
			$p = wc_get_product( (int) $row['id'] );
			if ( $p && $p->is_in_stock() ) {
				$products[] = $p;
			}
		}

		if ( count( $products ) >= $limit ) {
			return array_slice( $products, 0, $limit );
		}

		// Legacy category fallback if engine unavailable or thin pool.
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return $products;
		}

		$exclude = array( $product_id );
		foreach ( $products as $p ) {
			$exclude[] = (int) $p->get_id();
		}
		$posts   = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => $limit,
				'post__not_in'   => $exclude,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_stock_status',
						'value' => 'instock',
					),
				),
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
				'meta_key'       => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
			)
		);

		foreach ( $posts as $post ) {
			if ( count( $products ) >= $limit ) {
				break;
			}
			$p = wc_get_product( $post->ID );
			if ( $p && $p->is_in_stock() ) {
				$products[] = $p;
			}
		}

		return $products;
	}

	/**
	 * AJAX: subscribe email for restock notice on this product.
	 */
	public function ajax_restock_notify(): void {
		check_ajax_referer( 'shojaei_seo_oos_notify', 'nonce' );

		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ) ) {
			wp_send_json_error( array( 'message' => __( 'اطلاع‌رسانی موجودی خاموش است.', 'shojaei-seo-for-woo' ) ) );
		}

		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$rate_key = 'shojaei_oos_notify_' . md5( $ip );
		$hits     = (int) get_transient( $rate_key );
		if ( $hits >= 8 ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.', 'shojaei-seo-for-woo' ) ) );
		}
		set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( $product_id < 1 || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل معتبر وارد کنید.', 'shojaei-seo-for-woo' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'این محصول الان ناموجود نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$list = get_post_meta( $product_id, '_shojaei_seo_restock_emails', true );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$email_l = strtolower( $email );
		foreach ( $list as $row ) {
			if ( isset( $row['email'] ) && strtolower( (string) $row['email'] ) === $email_l ) {
				wp_send_json_success( array( 'message' => __( 'قبلاً ثبت شده‌اید؛ به‌محض موجود شدن خبر می‌دهیم.', 'shojaei-seo-for-woo' ) ) );
			}
		}

		$list[] = array(
			'email' => $email,
			'time'  => time(),
			'user'  => get_current_user_id(),
		);
		// Cap list size per product.
		if ( count( $list ) > 200 ) {
			$list = array_slice( $list, -200 );
		}
		update_post_meta( $product_id, '_shojaei_seo_restock_emails', $list );

		wp_send_json_success( array( 'message' => __( 'ثبت شد. وقتی موجود شود به ایمیلتان خبر می‌دهیم.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * Email restock subscribers and clear the list.
	 *
	 * @param int $product_id Product ID.
	 */
	private function notify_restock_subscribers( int $product_id ): void {
		$list = get_post_meta( $product_id, '_shojaei_seo_restock_emails', true );
		if ( ! is_array( $list ) || empty( $list ) ) {
			return;
		}

		$title = get_the_title( $product_id );
		$url   = get_permalink( $product_id );
		$subj  = sprintf(
			/* translators: %s: product title */
			__( 'موجود شد: %s', 'shojaei-seo-for-woo' ),
			$title
		);
		$body = sprintf(
			/* translators: 1: product title, 2: url */
			__( "سلام،\n\nمحصول «%1\$s» دوباره موجود شد:\n%2\$s\n", 'shojaei-seo-for-woo' ),
			$title,
			$url
		);

		foreach ( $list as $row ) {
			$email = isset( $row['email'] ) ? sanitize_email( (string) $row['email'] ) : '';
			if ( ! is_email( $email ) ) {
				continue;
			}
			wp_mail( $email, $subj, $body );
		}

		delete_post_meta( $product_id, '_shojaei_seo_restock_emails' );
	}

	/**
	 * Process auto-redirect for a product (respects Page Value gate + Dry-Run).
	 *
	 * @param int $product_id Product ID.
	 */
	public function process_product_oos( int $product_id ): void {
		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();

		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( ! $record || 'redirected' === $record->status ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$decision = class_exists( 'Shojaei_SEO_Rule_Engine' )
			? Shojaei_SEO_Rule_Engine::evaluate_product( $product_id )
			: null;

		if ( $decision ) {
			Shojaei_SEO_Rule_Engine::sync_decision_meta( $product_id, $decision );
		}

		$title = $product->get_name();
		$plan  = ( $decision && is_array( $decision->redirect_plan ) )
			? $decision->redirect_plan
			: $this->build_redirect_plan( $product );

		// Page Value / needs_manual from Rule Engine.
		if ( $decision && 'needs_manual' === $decision->redirect_apply_mode ) {
			$ctx   = Shojaei_SEO_Rule_Context::from_product_id( $product_id );
			$score = (int) ( $ctx->page_value['score'] ?? 0 );
			$wpdb->update(
				$table,
				array( 'status' => 'needs_manual' ),
				array( 'product_id' => $product_id ),
				array( '%s' ),
				array( '%d' )
			);

			Shojaei_SEO_Notifications::add(
				'needs_manual',
				sprintf(
					/* translators: 1: product title, 2: score */
					__( 'صفحه «%1$s» ارزش بالا دارد (امتیاز %2$d) — ریدایرکت خودکار متوقف شد؛ تایید دستی لازم است.', 'shojaei-seo-for-woo' ),
					$title,
					$score
				),
				$product_id,
				admin_url( 'admin.php?page=shojaei-seo&tab=oos' )
			);

			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'needs_manual',
					sprintf(
						/* translators: 1: product, 2: score */
						__( 'قفل Page Value برای «%1$s» (امتیاز %2$d) — Rule Engine', 'shojaei-seo-for-woo' ),
						$title,
						$score
					),
					$product_id,
					$decision->to_array()
				);
			}
			return;
		}

		if ( $decision && 'blocked' === $decision->redirect_apply_mode ) {
			Shojaei_SEO_Notifications::add(
				'redirect_loop',
				sprintf(
					/* translators: 1: product, 2: reason */
					__( 'ریدایرکت خودکار برای «%1$s» متوقف شد: %2$s', 'shojaei-seo-for-woo' ),
					$title,
					$decision->block_reason ?: __( 'سیاست موتور قوانین', 'shojaei-seo-for-woo' )
				),
				$product_id,
				admin_url( 'admin.php?page=shojaei-seo&tab=oos' )
			);
			return;
		}

		// Fallback loop check when engine missing.
		if ( ! $decision ) {
			$loop_check = Shojaei_SEO_Redirect_Engine::validate_redirect(
				(string) get_permalink( $product_id ),
				(string) $plan['target_url'],
				$product_id
			);
			if ( is_wp_error( $loop_check ) ) {
				Shojaei_SEO_Notifications::add(
					'redirect_loop',
					sprintf(
						/* translators: 1: product, 2: error */
						__( 'ریدایرکت خودکار برای «%1$s» متوقف شد: %2$s', 'shojaei-seo-for-woo' ),
						$title,
						$loop_check->get_error_message()
					),
					$product_id,
					admin_url( 'admin.php?page=shojaei-seo&tab=oos' )
				);
				return;
			}
			if ( class_exists( 'Shojaei_SEO_Page_Value' ) && Shojaei_SEO_Page_Value::requires_manual( $product_id ) ) {
				// Legacy path handled below via existing code — simplify: re-run without engine is rare.
			}
		}

		$apply_mode = $decision ? $decision->redirect_apply_mode : (
			( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ) ) ? 'dry_run' : 'apply'
		);

		// Principle: never auto-apply without Undo.
		if ( 'apply' === $apply_mode && class_exists( 'Shojaei_SEO_Revert_Log' ) && ! Shojaei_SEO_Revert_Log::can_auto_apply( 'auto_redirect' ) ) {
			$apply_mode = 'dry_run';
		}
		if ( 'apply' === $apply_mode && ! class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$apply_mode = 'dry_run';
		}

		$type_label = $plan['redirect_type'] ?? '302';

		if ( 'dry_run' === $apply_mode || ( ! $decision && 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ) ) ) {
			Shojaei_SEO_Notifications::add(
				'dry_run',
				sprintf(
					/* translators: 1: product title, 2: type, 3: target url */
					__( 'Dry-Run: برای «%1$s» ریدایرکت %2$s به %3$s پیشنهاد شد (اعمال نشد).', 'shojaei-seo-for-woo' ),
					$title,
					$type_label,
					$plan['target_url'] ?? ''
				),
				$product_id,
				admin_url( 'admin.php?page=shojaei-seo&tab=oos' )
			);

			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'dry_run',
					sprintf(
						/* translators: 1: product, 2: type, 3: target */
						__( 'شبیه‌سازی ریدایرکت %2$s برای «%1$s» → %3$s', 'shojaei-seo-for-woo' ),
						$title,
						$type_label,
						$plan['target_url'] ?? ''
					),
					$product_id,
					$decision ? $decision->to_array() : $plan
				);
			}
			return;
		}

		if ( 'apply' !== $apply_mode ) {
			return;
		}

		$before = class_exists( 'Shojaei_SEO_Revert_Log' )
			? Shojaei_SEO_Revert_Log::snapshot_full( $product_id )
			: array();

		$wpdb->update(
			$table,
			array(
				'status'        => 'redirected',
				'redirect_type' => $plan['redirect_type'],
				'target_url'    => $plan['target_url'],
			),
			array( 'product_id' => $product_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		$wpdb->insert(
			Shojaei_SEO_Helpers::redirect_log_table(),
			array(
				'product_id'    => $product_id,
				'redirect_type' => $plan['redirect_type'],
				'target_url'    => $plan['target_url'],
				'reason'        => $plan['reason'] ?? 'auto',
				'user_id'       => 0,
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		Shojaei_SEO_Helpers::increment_stat( 'redirects' );

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$after = Shojaei_SEO_Revert_Log::snapshot_full( $product_id );
			Shojaei_SEO_Revert_Log::record_applied_oos(
				Shojaei_SEO_Revert_Log::new_batch_id(),
				'auto_redirect',
				$product_id,
				$before,
				$after,
				sprintf(
					/* translators: 1: title, 2: type, 3: url */
					__( 'ریدایرکت خودکار %2$s برای «%1$s» → %3$s', 'shojaei-seo-for-woo' ),
					$title,
					$plan['redirect_type'],
					$plan['target_url']
				)
			);
		}

		Shojaei_SEO_Notifications::add(
			'auto_redirect',
			sprintf(
				/* translators: 1: product title, 2: redirect type */
				__( 'ریدایرکت خودکار %2$s برای «%1$s» اعمال شد.', 'shojaei-seo-for-woo' ),
				$title,
				$plan['redirect_type']
			),
			$product_id,
			admin_url( 'admin.php?page=shojaei-seo&tab=dashboard' )
		);

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'auto_redirect',
				sprintf(
					/* translators: 1: product, 2: type, 3: url */
					__( 'ریدایرکت خودکار %2$s برای «%1$s» → %3$s', 'shojaei-seo-for-woo' ),
					$title,
					$plan['redirect_type'],
					$plan['target_url']
				),
				$product_id,
				$decision ? $decision->to_array() : $plan
			);
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::on_seo_state_change( $product_id );
		}

		if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
			Shojaei_SEO_GSC::notify_product_change( $product_id, 'redirect' );
		}
	}

	/**
	 * Build suggested redirect plan without applying (static — safe for Rule Engine).
	 *
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public static function build_redirect_plan_static( $product ): array {
		$timeline  = Shojaei_SEO_Helpers::get_oos_timeline();
		$auto_type = $timeline['auto_type'];
		$threshold = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_match_threshold', 70 );
		$match     = class_exists( 'Shojaei_SEO_Redirect_Engine' )
			? Shojaei_SEO_Redirect_Engine::find_best_replacement( $product, $threshold )
			: array( 'id' => 0, 'score' => 0 );

		if ( ! empty( $match['id'] ) ) {
			$target = get_permalink( (int) $match['id'] );
			$valid  = Shojaei_SEO_Redirect_Engine::validate_redirect(
				(string) get_permalink( $product->get_id() ),
				(string) $target,
				(int) $product->get_id()
			);

			if ( ! is_wp_error( $valid ) ) {
				return array(
					'redirect_type' => $auto_type,
					'target_url'    => $target,
					'reason'        => 'relevance_match',
					'match_id'      => (int) $match['id'],
					'match_score'   => (int) ( $match['score'] ?? 0 ),
					'score_parts'   => $match['parts'] ?? array(),
					'loop_safe'     => true,
				);
			}
		}

		$category_url = Shojaei_SEO_Helpers::get_primary_category_url( $product->get_id() );
		$cat_valid    = Shojaei_SEO_Redirect_Engine::validate_redirect(
			(string) get_permalink( $product->get_id() ),
			(string) $category_url,
			(int) $product->get_id()
		);

		return array(
			'redirect_type' => $auto_type,
			'target_url'    => ! is_wp_error( $cat_valid ) ? $category_url : home_url( '/shop/' ),
			'reason'        => 'auto_category',
			'match_id'      => 0,
			'match_score'   => 0,
			'score_parts'   => array(),
			'loop_safe'     => ! is_wp_error( $cat_valid ),
		);
	}

	/**
	 * Build suggested redirect plan without applying.
	 *
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public function build_redirect_plan( $product ): array {
		return self::build_redirect_plan_static( $product );
	}

	/**
	 * Fast plan for admin diagnose — category/home only (no catalog similarity scan).
	 *
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public static function build_quick_redirect_plan_static( $product ): array {
		$timeline  = Shojaei_SEO_Helpers::get_oos_timeline();
		$auto_type = $timeline['auto_type'] ?? '302';
		$category  = Shojaei_SEO_Helpers::get_primary_category_url( $product->get_id() );
		$source    = (string) get_permalink( $product->get_id() );
		$valid     = class_exists( 'Shojaei_SEO_Redirect_Engine' )
			? Shojaei_SEO_Redirect_Engine::validate_redirect( $source, (string) $category, (int) $product->get_id() )
			: true;

		return array(
			'redirect_type' => $auto_type,
			'target_url'    => ! is_wp_error( $valid ) ? $category : home_url( '/shop/' ),
			'reason'        => 'quick_category',
			'match_id'      => 0,
			'match_score'   => 0,
			'score_parts'   => array(),
			'loop_safe'     => ! is_wp_error( $valid ),
		);
	}

	/**
	 * Diagnose a product for the test panel.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function diagnose_product( int $product_id ): array {
		global $wpdb;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array( 'error' => __( 'محصول یافت نشد.', 'shojaei-seo-for-woo' ) );
		}

		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
		);

		$days  = $record ? (int) $record->days_oos : 0;
		// Repair absurd Jalali-as-Gregorian day counts on the fly.
		if ( $record && ( $days > 2000 || ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( (string) $record->oos_date ) ) ) {
			$this->refresh_oos_days_public( $product_id );
			$record = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
			);
			$days = $record ? (int) $record->days_oos : 0;
		}

		$phase = $record ? Shojaei_SEO_Helpers::get_oos_phase( $days ) : 0;
		$state = $record ? Shojaei_SEO_Helpers::get_oos_state( $days ) : null;
		$plan  = null;

		$robots_note = '';
		if ( $record && $phase >= Shojaei_SEO_Helpers::get_noindex_from_phase() && 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_enabled', 'yes' ) ) {
			$robots_note = 'noindex, follow';
		} else {
			$robots_note = 'index, follow (پیش‌فرض)';
		}

		$type = $product->get_type();
		$stock_detail = '';
		if ( $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			$in_stock = 0;
			foreach ( $children as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v && $v->is_in_stock() ) {
					$in_stock++;
				}
			}
			$stock_detail = sprintf(
				/* translators: 1: in stock vars, 2: total */
				__( '%1$d از %2$d variation موجود', 'shojaei-seo-for-woo' ),
				$in_stock,
				count( $children )
			);
		}

		// Cached page value first — avoid slow recalculation on every View click.
		$page_value = null;
		if ( class_exists( 'Shojaei_SEO_Page_Value' ) ) {
			$cached = (int) Shojaei_SEO_Page_Value::get_score( $product_id, false );
			$page_value = array(
				'score'           => $cached,
				'requires_manual' => Shojaei_SEO_Page_Value::requires_manual( $product_id ),
			);
		}

		// Light rule eval for flags; full redirect plan for real similarity scores.
		$rule = class_exists( 'Shojaei_SEO_Rule_Engine' )
			? Shojaei_SEO_Rule_Engine::evaluate_product( $product_id, true )
			: null;

		$is_oos = ! $product->is_in_stock()
			|| ( $product->is_type( 'variable' ) && ! Shojaei_SEO_Helpers::variable_product_has_stock( $product_id ) );

		if ( $is_oos ) {
			$plan = self::build_redirect_plan_static( $product );
		} elseif ( $rule && ! empty( $rule->redirect_plan ) ) {
			$plan = $rule->redirect_plan;
		}

		if ( $rule ) {
			$robots_note = $rule->apply_noindex ? 'noindex, follow' : 'index, follow (پیش‌فرض)';
		}

		return array(
			'product_id'   => $product_id,
			'title'        => $product->get_name(),
			'type'         => $type,
			'permalink'    => get_permalink( $product_id ),
			'in_stock'     => $product->is_in_stock(),
			'stock_detail' => $stock_detail,
			'tracked'      => (bool) $record,
			'oos_status'   => $record->status ?? '',
			'oos_date'     => $record->oos_date ?? get_post_meta( $product_id, '_shojaei_seo_oos_date', true ),
			'days_oos'     => $days,
			'phase'        => $phase,
			'lifecycle'    => $state['type'] ?? '',
			'lifecycle_label' => $state['label'] ?? '',
			'redirect_type'=> $record->redirect_type ?? 'none',
			'target_url'   => $record->target_url ?? '',
			'robots'       => $robots_note,
			'dry_run'      => 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ),
			'plan'         => $plan,
			'page_value'   => $page_value,
			'rank_math'    => Shojaei_SEO_Helpers::is_rank_math_active(),
			'yoast'        => Shojaei_SEO_Helpers::is_yoast_active(),
			'seo_plugins'  => class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_labels() : '',
			'schema_mode'  => class_exists( 'Shojaei_SEO_Integration' )
				? Shojaei_SEO_Integration::schema_mode_label()
				: ( Shojaei_SEO_Helpers::is_rank_math_active()
					? __( 'فقط FAQ تکمیلی (Rank Math فعال)', 'shojaei-seo-for-woo' )
					: __( 'Product + Breadcrumb فعال', 'shojaei-seo-for-woo' ) ),
			'rule_engine'  => $rule ? array(
				'primary_action' => $rule->primary_action,
				'primary_label'  => Shojaei_SEO_Rule_Engine::action_label( $rule->primary_action ),
				'apply_mode'     => $rule->redirect_apply_mode,
				'show_replacements' => $rule->show_replacements,
				'reduce_link_priority' => $rule->reduce_link_priority,
				'remove_from_sitemap'  => $rule->remove_from_sitemap,
				'traces'         => $rule->traces,
			) : null,
		);
	}

	/**
	 * Find best match with score details (legacy wrapper → Redirect Engine).
	 *
	 * @param WC_Product $product   Source product.
	 * @param int        $threshold Threshold.
	 * @return array{id:int,score:int,parts?:array,skipped_loops?:int}
	 */
	private function find_best_match_detailed( $product, int $threshold ): array {
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			return Shojaei_SEO_Redirect_Engine::find_best_replacement( $product, $threshold );
		}

		return array( 'id' => 0, 'score' => 0 );
	}

	/**
	 * Find best matching in-stock product by title similarity.
	 *
	 * @param WC_Product $product   Source product.
	 * @param int        $threshold Minimum similarity percentage.
	 * @return int|null Product ID or null.
	 */
	private function find_best_match( $product, int $threshold ): ?int {
		$data = $this->find_best_match_detailed( $product, $threshold );
		return $data['id'] ? $data['id'] : null;
	}

	/**
	 * Apply manual redirect from admin.
	 *
	 * @param int         $product_id    Product ID.
	 * @param string      $redirect_type 301, 302 or 410.
	 * @param string      $target_url    Target URL.
	 * @param bool        $force_confirm Allow high page-value override.
	 * @param string|null $batch_id      Optional revert-log batch.
	 * @return true|WP_Error
	 */
	public function apply_manual_redirect( int $product_id, string $redirect_type, string $target_url = '', bool $force_confirm = false, ?string $batch_id = null ) {
		global $wpdb;

		$redirect_type = Shojaei_SEO_Helpers::sanitize_redirect_type( $redirect_type );
		$before        = class_exists( 'Shojaei_SEO_Revert_Log' ) ? Shojaei_SEO_Revert_Log::snapshot_full( $product_id ) : array();

		if ( '410' !== $redirect_type ) {
			$target_url = esc_url_raw( $target_url );
			if ( empty( $target_url ) ) {
				return new WP_Error( 'missing_target', __( 'آدرس مقصد الزامی است.', 'shojaei-seo-for-woo' ) );
			}

			$source_url = get_permalink( $product_id );
			$loop_check = Shojaei_SEO_Redirect_Engine::validate_redirect(
				(string) $source_url,
				$target_url,
				$product_id
			);
			if ( is_wp_error( $loop_check ) ) {
				return $loop_check;
			}
		} else {
			$target_url = '';
		}

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) && Shojaei_SEO_Page_Value::requires_manual( $product_id ) && ! $force_confirm ) {
			$eval = Shojaei_SEO_Page_Value::evaluate( $product_id );
			return new WP_Error(
				'high_page_value',
				sprintf(
					/* translators: %d: score */
					__( 'این صفحه ارزش بالایی دارد (امتیاز %d). برای ریدایرکت/حذف باید صریحاً تایید کنید.', 'shojaei-seo-for-woo' ),
					$eval['score']
				),
				$eval
			);
		}

		$table  = Shojaei_SEO_Helpers::oos_table();
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d", $product_id )
		);
		$row    = array(
			'status'        => 'redirected',
			'redirect_type' => $redirect_type,
			'target_url'    => $target_url,
		);
		if ( $exists > 0 ) {
			$wpdb->update( $table, $row, array( 'product_id' => $product_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert(
				$table,
				array_merge(
					array(
						'product_id' => $product_id,
						'oos_date'   => Shojaei_SEO_Helpers::mysql_datetime(),
						'days_oos'   => 0,
					),
					$row
				),
				array( '%d', '%s', '%d', '%s', '%s', '%s' )
			);
		}
		Shojaei_SEO_Helpers::flush_410_excluded_cache();

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}

		$wpdb->insert(
			Shojaei_SEO_Helpers::redirect_log_table(),
			array(
				'product_id'    => $product_id,
				'redirect_type' => $redirect_type,
				'target_url'    => $target_url,
				'reason'        => $force_confirm ? 'manual_forced' : 'manual',
				'user_id'       => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) && $force_confirm ) {
			Shojaei_SEO_Page_Value::set_protected( $product_id, false );
		}

		if ( '410' === $redirect_type ) {
			self::hide_410_from_catalog( $product_id );
			$key   = 'shojaei_seo_stats_redirects';
			$count = (int) Shojaei_SEO_Helpers::get_option( $key, 0 );
			update_option( $key, $count + 1 );
			if ( class_exists( 'Shojaei_SEO_Analytics' ) ) {
				Shojaei_SEO_Analytics::bump( 'gone_410' );
			}

			if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
				Shojaei_SEO_Pulse::forget_post( $product_id );
			}
			if ( class_exists( 'Damavand_Link_Manager' ) ) {
				Damavand_Link_Manager::purge_post( $product_id );
			}

			$title = get_the_title( $product_id );
			Shojaei_SEO_Notifications::add(
				'gone_410',
				sprintf(
					/* translators: %s: product title */
					__( 'وضعیت 410 Gone برای «%s» اعمال شد.', 'shojaei-seo-for-woo' ),
					$title
				),
				$product_id
			);
		} else {
			Shojaei_SEO_Helpers::increment_stat( 'redirects' );
		}

		$title  = get_the_title( $product_id );
		$action = '410' === $redirect_type ? 'redirect_410' : ( '302' === $redirect_type ? 'redirect_302' : 'redirect_301' );

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$after = Shojaei_SEO_Revert_Log::snapshot_full( $product_id );
			Shojaei_SEO_Revert_Log::record_applied_oos(
				$batch_id ?: Shojaei_SEO_Revert_Log::new_batch_id(),
				$action,
				$product_id,
				$before,
				$after,
				sprintf(
					/* translators: 1: type, 2: title, 3: url */
					__( 'اعمال %1$s برای «%2$s» → %3$s', 'shojaei-seo-for-woo' ),
					$redirect_type,
					$title,
					$target_url ?: '—'
				)
			);
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				$action,
				sprintf(
					/* translators: 1: type, 2: title, 3: url */
					__( 'اعمال دستی %1$s برای «%2$s» → %3$s', 'shojaei-seo-for-woo' ),
					$redirect_type,
					$title,
					$target_url ?: '—'
				),
				$product_id,
				array( 'redirect_type' => $redirect_type, 'target_url' => $target_url, 'forced' => $force_confirm )
			);
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::on_seo_state_change( $product_id );
		}

		if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
			$event = ( '410' === $redirect_type ) ? 'gone' : 'redirect';
			Shojaei_SEO_GSC::notify_product_change( $product_id, $event );
		}

		/**
		 * Fires after OOS manual redirect/410 is saved.
		 *
		 * @param int    $product_id     Product ID.
		 * @param string $redirect_type  301|302|410.
		 * @param string $target_url     Target URL (empty for 410).
		 */
		do_action( 'damavand_seo_redirect_applied', $product_id, $redirect_type, $target_url );

		return true;
	}

	/**
	 * Undo a redirect and restore product to OOS tracking.
	 *
	 * @param int $product_id Product ID.
	 * @param int $log_id     Optional log entry ID.
	 * @return bool
	 */
	public function undo_redirect( int $product_id, int $log_id = 0 ): bool {
		global $wpdb;

		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( ! $record || 'redirected' !== $record->status ) {
			return false;
		}

		$was_410 = ( '410' === (string) $record->redirect_type );

		$days   = (int) $record->days_oos;
		$state  = Shojaei_SEO_Helpers::get_oos_state( $days );
		$status = $state['status'];

		$wpdb->update(
			$table,
			array(
				'status'        => $status,
				'redirect_type' => 'none',
				'target_url'    => '',
			),
			array( 'product_id' => $product_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		Shojaei_SEO_Helpers::flush_410_excluded_cache();
		if ( $was_410 ) {
			self::restore_410_catalog( $product_id );
		}

		$log_table = Shojaei_SEO_Helpers::redirect_log_table();

		if ( $log_id > 0 ) {
			$wpdb->update(
				$log_table,
				array( 'is_undone' => 1 ),
				array( 'id' => $log_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$log_table} SET is_undone = 1 WHERE product_id = %d AND is_undone = 0 ORDER BY id DESC LIMIT 1",
					$product_id
				)
			);
		}

		$wpdb->insert(
			$log_table,
			array(
				'product_id'    => $product_id,
				'redirect_type' => 'none',
				'target_url'    => '',
				'reason'        => 'undo',
				'user_id'       => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		Shojaei_SEO_Helpers::decrement_stat( 'redirects' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'undo_redirect',
				sprintf(
					/* translators: %s: product title */
					__( 'لغو ریدایرکت برای «%s»', 'shojaei-seo-for-woo' ),
					get_the_title( $product_id )
				),
				$product_id
			);
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::on_seo_state_change( $product_id );
		}

		if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
			Shojaei_SEO_GSC::notify_product_change( $product_id, 'undo' );
		}

		return true;
	}

	/**
	 * Apply bulk redirect or keep action.
	 *
	 * @param array       $product_ids Product IDs.
	 * @param string      $action      Action slug.
	 * @param string|null $target_url  Optional shared target URL.
	 * @return int Number of processed items.
	 */
	public function bulk_action( array $product_ids, string $action, ?string $target_url = null, bool $force_confirm = false, ?string $batch_id = null ): int {
		$processed = 0;
		if ( null === $batch_id && class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$batch_id = Shojaei_SEO_Revert_Log::new_batch_id();
		}

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				continue;
			}

			switch ( $action ) {
				case 'redirect_301':
				case 'redirect_302':
					$url = $target_url ?: $this->get_suggested_target_url( $product_id );
					if ( $url ) {
						$type   = 'redirect_301' === $action ? '301' : '302';
						$result = $this->apply_manual_redirect( $product_id, $type, $url, $force_confirm, $batch_id );
						if ( ! is_wp_error( $result ) ) {
							$processed++;
						}
					}
					break;
				case 'redirect_410':
					$result = $this->apply_manual_redirect( $product_id, '410', '', $force_confirm, $batch_id );
					if ( ! is_wp_error( $result ) ) {
						$processed++;
					}
					break;
				case 'keep':
					$this->keep_page( $product_id, $batch_id );
					$processed++;
					break;
			}
		}

		return $processed;
	}

	/**
	 * Get suggested redirect target for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_suggested_target_url( int $product_id ): string {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return Shojaei_SEO_Helpers::get_primary_category_url( $product_id );
		}

		$plan = $this->build_redirect_plan( $product );
		return ! empty( $plan['target_url'] ) ? (string) $plan['target_url'] : Shojaei_SEO_Helpers::get_primary_category_url( $product_id );
	}

	/**
	 * Keep page without redirect.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $batch_id   Optional batch.
	 */
	public function keep_page( int $product_id, ?string $batch_id = null ): void {
		global $wpdb;

		$before = class_exists( 'Shojaei_SEO_Revert_Log' ) ? Shojaei_SEO_Revert_Log::snapshot_full( $product_id ) : array();

		$record = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT days_oos, oos_date FROM ' . Shojaei_SEO_Helpers::oos_table() . ' WHERE product_id = %d',
				$product_id
			)
		);

		$days  = $record ? (int) $record->days_oos : 0;
		$state = Shojaei_SEO_Helpers::get_oos_state( $days );

		$wpdb->update(
			Shojaei_SEO_Helpers::oos_table(),
			array( 'status' => $state['status'] === 'candidate_redirect' ? 'permanent_oos' : $state['status'], 'redirect_type' => 'none', 'target_url' => '' ),
			array( 'product_id' => $product_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) ) {
			Shojaei_SEO_Page_Value::set_protected( $product_id, true );
		}

		if ( $record ) {
			Shojaei_SEO_Helpers::sync_oos_postmeta( $product_id, (string) $record->oos_date, $days );
		}

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$after = Shojaei_SEO_Revert_Log::snapshot_full( $product_id );
			Shojaei_SEO_Revert_Log::record_applied_oos(
				$batch_id ?: Shojaei_SEO_Revert_Log::new_batch_id(),
				'keep_page',
				$product_id,
				$before,
				$after,
				sprintf(
					/* translators: %s: product title */
					__( 'نگهداری صفحه (محافظت دستی) برای «%s»', 'shojaei-seo-for-woo' ),
					get_the_title( $product_id )
				)
			);
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'keep_page',
				sprintf(
					/* translators: %s: product title */
					__( 'نگهداری صفحه (محافظت دستی) برای «%s»', 'shojaei-seo-for-woo' ),
					get_the_title( $product_id )
				),
				$product_id
			);
		}
	}

	/**
	 * Enqueue frontend styles + notify script on OOS product pages.
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'shojaei-seo-public',
			DAMAVAND_SEO_URL . 'public/css/public-style.css',
			array(),
			DAMAVAND_SEO_VERSION
		);

		$custom_css = Shojaei_SEO_Helpers::get_oos_custom_css();
		if ( '' !== $custom_css ) {
			wp_add_inline_style( 'shojaei-seo-public', $custom_css );
		}

		$product = self::current_product();
		if ( ! $product || $product->is_in_stock() ) {
			return;
		}
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ) ) {
			return;
		}

		wp_enqueue_script(
			'shojaei-seo-oos-notify',
			DAMAVAND_SEO_URL . 'public/js/oos-notify.js',
			array(),
			DAMAVAND_SEO_VERSION,
			true
		);
		wp_localize_script(
			'shojaei-seo-oos-notify',
			'shojaeiSeoOos',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shojaei_seo_oos_notify' ),
			)
		);
	}
}
