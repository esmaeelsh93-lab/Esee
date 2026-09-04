<?php
/**
 * Out-of-Stock product lifecycle manager (orchestrator + frontend UI + BC wrappers).
 *
 * Implementation lives in:
 * - Damavand_OOS_Order_Lookup
 * - Damavand_OOS_Notifier
 * - Damavand_OOS_Detector
 * - Damavand_OOS_Admin
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

	/** @var bool */
	private static $notice_rendered = false;

	/** @var bool */
	private static $related_rendered = false;

	/** @var Damavand_OOS_Detector|null */
	private $detector = null;

	/** @var Damavand_OOS_Notifier|null */
	private $notifier = null;

	/** @var Damavand_OOS_Admin|null */
	private $admin = null;

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

		$detector = $this->detector();
		$notifier = $this->notifier();
		$admin    = $this->admin();

		add_action( 'woocommerce_product_set_stock_status', array( $detector, 'on_stock_status_change' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( $detector, 'on_variation_stock_change' ), 10, 3 );
		add_action( 'woocommerce_product_set_stock', array( $detector, 'on_stock_quantity_change' ), 10, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $detector, 'on_stock_quantity_change' ), 10, 1 );
		add_action( 'updated_post_meta', array( $detector, 'on_stock_meta_updated' ), 10, 4 );
		add_action( 'added_post_meta', array( $detector, 'on_stock_meta_updated' ), 10, 4 );
		add_action( 'template_redirect', array( $detector, 'handle_redirects' ) );
		add_action( 'pre_get_posts', array( $detector, 'exclude_410_from_queries' ), 11 );
		add_filter( 'woocommerce_product_is_visible', array( $detector, 'hide_410_from_visibility' ), 10, 2 );
		add_filter( 'woocommerce_shortcode_products_query', array( $detector, 'exclude_410_from_shortcode' ) );
		add_filter( 'woocommerce_product_related_posts_query', array( $detector, 'exclude_410_from_related_query' ) );
		add_filter( 'woocommerce_related_products', array( $detector, 'exclude_410_from_id_list' ) );
		add_filter( 'woocommerce_product_get_upsell_ids', array( $detector, 'exclude_410_from_id_list' ) );
		add_filter( 'woocommerce_product_get_cross_sell_ids', array( $detector, 'exclude_410_from_id_list' ) );
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $detector, 'exclude_410_from_wc_get_products' ), 10, 2 );
		add_action( 'admin_init', array( $detector, 'maybe_sync_410_catalog_hide' ) );
		add_filter( 'woocommerce_is_purchasable', array( $detector, 'make_unpurchasable' ), 10, 2 );
		// قالب‌های کلاسیک.
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_oos_notice' ), 25 );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_related_products_section' ), 5 );
		add_action( 'woocommerce_after_single_product', array( $this, 'render_related_products_section' ), 12 );
		// قالب‌های Elementor / بلوکی / سفارشی که فقط HTML موجودی را چاپ می‌کنند.
		add_filter( 'woocommerce_get_stock_html', array( $this, 'append_oos_ui_to_stock_html' ), 30, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'wp_robots', array( $detector, 'maybe_noindex_oos_product' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $detector, 'exclude_from_core_sitemap' ), 10, 2 );
		add_action( 'wp_ajax_shojaei_seo_oos_notify', array( $notifier, 'ajax_restock_notify' ) );
		add_action( 'wp_ajax_nopriv_shojaei_seo_oos_notify', array( $notifier, 'ajax_restock_notify' ) );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $admin, 'register_admin_metabox' ) );
		}

		add_action( 'shutdown', array( 'Damavand_OOS_Detector', 'flush_deferred_variation_sync' ), 1 );
	}

	/**
	 * @return Damavand_OOS_Detector
	 */
	private function detector(): Damavand_OOS_Detector {
		if ( null === $this->detector ) {
			$this->detector = new Damavand_OOS_Detector();
		}
		return $this->detector;
	}

	/**
	 * @return Damavand_OOS_Notifier
	 */
	private function notifier(): Damavand_OOS_Notifier {
		if ( null === $this->notifier ) {
			$this->notifier = new Damavand_OOS_Notifier();
		}
		return $this->notifier;
	}

	/**
	 * @return Damavand_OOS_Admin
	 */
	private function admin(): Damavand_OOS_Admin {
		if ( null === $this->admin ) {
			$this->admin = new Damavand_OOS_Admin();
		}
		return $this->admin;
	}

	/**
	 * Current product (Elementor/custom templates may not set global $product).
	 */
	public static function current_product(): ?WC_Product {
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

	/* -------------------------------------------------------------------------
	 * BC wrappers — Detector
	 * ---------------------------------------------------------------------- */

	/**
	 * Run queued parent OOS sync once per request.
	 */
	public static function flush_deferred_variation_sync(): void {
		Damavand_OOS_Detector::flush_deferred_variation_sync();
	}

	/**
	 * @param array  $args      Query args.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function exclude_from_core_sitemap( array $args, string $post_type ): array {
		return $this->detector()->exclude_from_core_sitemap( $args, $post_type );
	}

	/**
	 * @param WP_Query $query Query.
	 */
	public function exclude_410_from_queries( $query ): void {
		$this->detector()->exclude_410_from_queries( $query );
	}

	/**
	 * @param bool $visible    Visible.
	 * @param int  $product_id Product ID.
	 */
	public function hide_410_from_visibility( bool $visible, $product_id ): bool {
		return $this->detector()->hide_410_from_visibility( $visible, $product_id );
	}

	/**
	 * @param array $args Shortcode query args.
	 */
	public function exclude_410_from_shortcode( array $args ): array {
		return $this->detector()->exclude_410_from_shortcode( $args );
	}

	/**
	 * @param array $query Related query.
	 */
	public function exclude_410_from_related_query( array $query ): array {
		return $this->detector()->exclude_410_from_related_query( $query );
	}

	/**
	 * @param int[] $ids Product IDs.
	 * @return int[]
	 */
	public function exclude_410_from_id_list( $ids ): array {
		return $this->detector()->exclude_410_from_id_list( $ids );
	}

	/**
	 * @param array $wp_query_args WP_Query args.
	 * @param array $query_vars    wc_get_products vars.
	 */
	public function exclude_410_from_wc_get_products( array $wp_query_args, array $query_vars ): array {
		return $this->detector()->exclude_410_from_wc_get_products( $wp_query_args, $query_vars );
	}

	/**
	 * @param int $product_id Product ID.
	 */
	public static function hide_410_from_catalog( int $product_id ): void {
		Damavand_OOS_Detector::hide_410_from_catalog( $product_id );
	}

	/**
	 * @param int $product_id Product ID.
	 */
	public static function restore_410_catalog( int $product_id ): void {
		Damavand_OOS_Detector::restore_410_catalog( $product_id );
	}

	/**
	 * One-time: hide already-410 products from catalog.
	 */
	public function maybe_sync_410_catalog_hide(): void {
		$this->detector()->maybe_sync_410_catalog_hide();
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $status     New status.
	 * @param object $product    Product object.
	 */
	public function on_stock_status_change( int $product_id, string $status, $product ): void {
		$this->detector()->on_stock_status_change( $product_id, $status, $product );
	}

	/**
	 * @param int    $variation_id Variation ID.
	 * @param string $status       New status.
	 * @param object $variation    Variation object.
	 */
	public function on_variation_stock_change( int $variation_id, string $status, $variation ): void {
		$this->detector()->on_variation_stock_change( $variation_id, $status, $variation );
	}

	/**
	 * @param int $parent_id Parent product ID.
	 */
	public function sync_variable_parent_oos( int $parent_id ): void {
		$this->detector()->sync_variable_parent_oos( $parent_id );
	}

	/**
	 * @param int  $product_id  Product ID.
	 * @param bool $is_variable Whether variable.
	 */
	public function register_oos_public( int $product_id, bool $is_variable = false ): void {
		$this->detector()->register_oos_public( $product_id, $is_variable );
	}

	/**
	 * @param int  $product_id  Product ID.
	 * @param bool $look_orders Scan orders (slow; off for bulk/cron).
	 */
	public function refresh_oos_days_public( int $product_id, bool $look_orders = false ): void {
		$this->detector()->refresh_oos_days_public( $product_id, $look_orders );
	}

	/**
	 * @param mixed $product Product.
	 */
	public function on_stock_quantity_change( $product ): void {
		$this->detector()->on_stock_quantity_change( $product );
	}

	/**
	 * @param int    $meta_id    Meta id.
	 * @param int    $object_id  Post id.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value Value.
	 */
	public function on_stock_meta_updated( $meta_id, $object_id, $meta_key, $meta_value ): void {
		$this->detector()->on_stock_meta_updated( $meta_id, $object_id, $meta_key, $meta_value );
	}

	/**
	 * @param int  $product_id  Product ID.
	 * @param bool $look_orders Read Woo order lookup.
	 */
	public static function estimate_oos_started_at( int $product_id, bool $look_orders = true ): string {
		return Damavand_OOS_Detector::estimate_oos_started_at( $product_id, $look_orders );
	}

	/**
	 * @param int  $product_id Product ID.
	 * @param bool $skip_cache Skip transient cache.
	 */
	public static function last_paid_order_timestamp( int $product_id, bool $skip_cache = false ): int {
		return Damavand_OOS_Order_Lookup::last_paid_order_timestamp( $product_id, $skip_cache );
	}

	/**
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public function maybe_noindex_oos_product( array $robots ): array {
		return $this->detector()->maybe_noindex_oos_product( $robots );
	}

	/**
	 * Handle 301/302 redirects and 410 Gone responses.
	 */
	public function handle_redirects(): void {
		$this->detector()->handle_redirects();
	}

	/**
	 * @param bool       $purchasable Whether purchasable.
	 * @param WC_Product $product     Product object.
	 * @return bool
	 */
	public function make_unpurchasable( bool $purchasable, $product ): bool {
		return $this->detector()->make_unpurchasable( $purchasable, $product );
	}

	/* -------------------------------------------------------------------------
	 * BC wrappers — Notifier
	 * ---------------------------------------------------------------------- */

	/**
	 * AJAX: subscribe email for restock notice on this product.
	 */
	public function ajax_restock_notify(): void {
		$this->notifier()->ajax_restock_notify();
	}

	/* -------------------------------------------------------------------------
	 * BC wrappers — Admin
	 * ---------------------------------------------------------------------- */

	/**
	 * متاباکس ادمین: وضعیت سئوی ناموجودی روی ویرایش محصول.
	 */
	public function register_admin_metabox(): void {
		$this->admin()->register_admin_metabox();
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_admin_metabox( $post ): void {
		$this->admin()->render_admin_metabox( $post );
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public static function build_redirect_plan_static( $product ): array {
		return Damavand_OOS_Admin::build_redirect_plan_static( $product );
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public function build_redirect_plan( $product ): array {
		return $this->admin()->build_redirect_plan( $product );
	}

	/**
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public static function build_quick_redirect_plan_static( $product ): array {
		return Damavand_OOS_Admin::build_quick_redirect_plan_static( $product );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function diagnose_product( int $product_id ): array {
		return $this->admin()->diagnose_product( $product_id );
	}

	/**
	 * @param int         $product_id    Product ID.
	 * @param string      $redirect_type 301, 302 or 410.
	 * @param string      $target_url    Target URL.
	 * @param bool        $force_confirm Allow high page-value override.
	 * @param string|null $batch_id      Optional revert-log batch.
	 * @return true|WP_Error
	 */
	public function apply_manual_redirect( int $product_id, string $redirect_type, string $target_url = '', bool $force_confirm = false, ?string $batch_id = null ) {
		return $this->admin()->apply_manual_redirect( $product_id, $redirect_type, $target_url, $force_confirm, $batch_id );
	}

	/**
	 * @param int $product_id Product ID.
	 * @param int $log_id     Optional log entry ID.
	 * @return bool
	 */
	public function undo_redirect( int $product_id, int $log_id = 0 ): bool {
		return $this->admin()->undo_redirect( $product_id, $log_id );
	}

	/**
	 * @param array       $product_ids Product IDs.
	 * @param string      $action      Action slug.
	 * @param string|null $target_url  Optional shared target URL.
	 * @return int Number of processed items.
	 */
	public function bulk_action( array $product_ids, string $action, ?string $target_url = null, bool $force_confirm = false, ?string $batch_id = null ): int {
		return $this->admin()->bulk_action( $product_ids, $action, $target_url, $force_confirm, $batch_id );
	}

	/**
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_suggested_target_url( int $product_id ): string {
		return $this->admin()->get_suggested_target_url( $product_id );
	}

	/**
	 * @param int         $product_id Product ID.
	 * @param string|null $batch_id   Optional batch.
	 */
	public function keep_page( int $product_id, ?string $batch_id = null ): void {
		$this->admin()->keep_page( $product_id, $batch_id );
	}

	/* -------------------------------------------------------------------------
	 * Frontend UI (stays on manager)
	 * ---------------------------------------------------------------------- */

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
	 * تعداد پیشنهاد جایگزین روی صفحه محصول ناموجود.
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
