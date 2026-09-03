<?php
/**
 * Checkout cross-sell box module.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Checkout_Cross_Sell
 */
class Shojaei_SEO_Checkout_Cross_Sell {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'checkout_box' ) ) {
			return;
		}

		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_cross_sell_box' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_shojaei_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_shojaei_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	/**
	 * Render cross-sell box on checkout page.
	 */
	public function render_cross_sell_box(): void {
		if ( ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}

		$products = $this->get_suggested_products();
		if ( empty( $products ) ) {
			return;
		}

		$user_name = '';
		if ( is_user_logged_in() ) {
			$user      = wp_get_current_user();
			$user_name = $user->display_name;
		}

		$title = $user_name
			? sprintf( __( '%s، چیزی از قلم نیفتاده؟', 'shojaei-seo-for-woo' ), esc_html( $user_name ) )
			: __( 'چیزی از قلم نیفتاده؟', 'shojaei-seo-for-woo' );
		?>
		<div class="shojaei-checkout-box">
			<h3 class="shojaei-checkout-box-title"><?php echo esc_html( $title ); ?></h3>
			<div class="shojaei-checkout-slider">
				<?php foreach ( $products as $product ) : ?>
					<div class="shojaei-checkout-item">
						<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>">
							<?php echo $product->get_image( 'woocommerce_thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
						<h4><?php echo esc_html( $product->get_name() ); ?></h4>
						<span class="price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
						<button
							type="button"
							class="button shojaei-quick-add"
							data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
						>
							<?php esc_html_e( 'افزودن', 'shojaei-seo-for-woo' ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get suggested complementary products based on cart categories.
	 *
	 * @return array WC_Product[]
	 */
	private function get_suggested_products(): array {
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return array();
		}

		$max       = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_checkout_max_products', 6 );
		$cat_ids   = array();
		$cart_ids  = array();
		$max_price = 0;

		foreach ( $cart->get_cart() as $item ) {
			$product   = $item['data'];
			$cart_ids[] = $product->get_id();
			$max_price  = max( $max_price, (float) $product->get_price() );

			$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				$cat_ids = array_merge( $cat_ids, $terms );
			}
		}

		$cat_ids = array_unique( $cat_ids );
		if ( empty( $cat_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $max,
			'post__not_in'   => $cart_ids,
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_stock_status', 'value' => 'instock' ),
				array(
					'key'     => '_price',
					'value'   => $max_price,
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
			),
			'tax_query'      => array(
				array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat_ids ),
			),
			'meta_key'       => 'total_sales',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		);

		$posts    = get_posts( $args );
		$products = array();

		foreach ( $posts as $post ) {
			$products[] = wc_get_product( $post->ID );
		}

		return $products;
	}

	/**
	 * AJAX add to cart handler.
	 */
	public function ajax_add_to_cart(): void {
		check_ajax_referer( 'shojaei_seo_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			wp_send_json_error( array( 'message' => __( 'محصول نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
		}
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'این محصول الان قابل خرید نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$added = WC()->cart->add_to_cart( $product_id );
		if ( $added ) {
			wp_send_json_success( array( 'message' => __( 'به سبد خرید اضافه شد.', 'shojaei-seo-for-woo' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'خطا در افزودن به سبد.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * Enqueue checkout assets.
	 */
	public function enqueue_assets(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'shojaei-seo-public',
			DAMAVAND_SEO_URL . 'public/css/public-style.css',
			array(),
			DAMAVAND_SEO_VERSION
		);

		wp_enqueue_script(
			'shojaei-seo-checkout',
			DAMAVAND_SEO_URL . 'public/js/checkout.js',
			array( 'jquery' ),
			DAMAVAND_SEO_VERSION,
			true
		);

		wp_localize_script( 'shojaei-seo-checkout', 'shojaeiSeo', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'shojaei_seo_nonce' ),
		) );
	}
}
