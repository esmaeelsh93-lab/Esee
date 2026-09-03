<?php
/**
 * Complementary products block — crawl depth & time on site.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Complementary
 */
class Shojaei_SEO_Complementary {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_complementary_enabled', 'yes' ) ) {
			return;
		}

		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render' ), 8 );
	}

	/**
	 * Render complementary products.
	 */
	public function render(): void {
		global $product;
		if ( ! $product instanceof WC_Product && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_the_ID() );
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$mode = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_complementary_mode', 'always' );
		if ( 'oos_only' === $mode && $product->is_in_stock() ) {
			return;
		}

		// روی ناموجودی، بلوک مشابه OOS (امتیاز شباهت) اولویت دارد — از دوبل جلوگیری.
		if ( ! $product->is_in_stock() && Shojaei_SEO_Helpers::is_module_enabled( 'oos' ) ) {
			return;
		}

		$limit = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_complementary_limit', 4 );
		$limit = max( 2, min( 8, $limit ) );

		$items = self::get_products( (int) $product->get_id(), $limit );
		if ( empty( $items ) ) {
			return;
		}
		?>
		<section id="shojaei-complementary" class="shojaei-complementary" dir="rtl">
			<h2><?php esc_html_e( 'محصولات مکمل پیشنهادی', 'shojaei-seo-for-woo' ); ?></h2>
			<p class="shojaei-complementary-desc"><?php esc_html_e( 'موارد مرتبط از همین دسته — برای ادامه خرید و لینک داخلی سالم.', 'shojaei-seo-for-woo' ); ?></p>
			<ul class="products columns-4">
				<?php
				foreach ( $items as $item ) {
					$post_object = get_post( $item->get_id() );
					if ( ! $post_object ) {
						continue;
					}
					setup_postdata( $GLOBALS['post'] = $post_object ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					wc_get_template_part( 'content', 'product' );
				}
				wp_reset_postdata();
				?>
			</ul>
		</section>
		<?php
	}

	/**
	 * Related in-stock products from same categories (sales-ordered).
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Limit.
	 * @return WC_Product[]
	 */
	public static function get_products( int $product_id, int $limit = 4 ): array {
		$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$q = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'post__not_in'           => Shojaei_SEO_Helpers::merge_410_not_in( array( $product_id ) ),
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_stock_status',
						'value' => 'instock',
					),
				),
				'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				),
				'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'orderby'                => 'meta_value_num',
				'order'                  => 'DESC',
			)
		);

		$out = array();
		foreach ( $q->posts as $id ) {
			$p = wc_get_product( (int) $id );
			if ( $p ) {
				$out[] = $p;
			}
		}
		return $out;
	}
}
